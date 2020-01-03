<?php
/*
 * bandwidth_by_ip.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once('auth_check.inc');
require_once('interfaces.inc');
require_once('pfsense-utils.inc');
require_once('util.inc');

$listedIPs = "";

//get interface IP and break up into an array
$interface = $_REQUEST['if'];
$real_interface = get_real_interface($interface);

if (!does_interface_exist($real_interface)) {
	echo gettext("Wrong Interface");
	return;
}

$intip = find_interface_ip($real_interface);
//get interface subnet
$netmask = find_interface_subnet($real_interface);
$intsubnet = gen_subnet($intip, $netmask) . "/$netmask";

// see if they want local, remote or all IPs returned
$filter = $_REQUEST['filter'];

if ($filter == "") {
	$filter = "local";
}

if ($filter == "local") {
	$ratesubnet = "-c " . $intsubnet;
} else {
	// Tell the rate utility to consider the whole internet (0.0.0.0/0)
	// and to consider local "l" traffic - i.e. traffic within the whole internet
	// then we can filter the resulting output as we wish below.
	$ratesubnet = "-lc 0.0.0.0/0";
}

//get the sort method
$sort = $_REQUEST['sort'];
if ($sort == "out") {
	$sort_method = "-T";
} else {
	$sort_method = "-R";
}

// get the desired format for displaying the host name or IP
$hostipformat = $_REQUEST['hostipformat'];
$iplookup = array();
// If hostname, description or FQDN is requested then load the locally-known IP address - host/description mappings into an array keyed by IP address.
if ($hostipformat != "") {
	if (is_array($config['dhcpd'])) {
		// Build an array of static-mapped DHCP entries keyed by IP address.
		foreach ($config['dhcpd'] as $ifdata) {
			if (is_array($ifdata['staticmap'])) {
				foreach ($ifdata['staticmap'] as $hostent) {
					if (($hostent['ipaddr'] != "") && ($hostent['hostname'] != "")) {
						if ($hostipformat == "descr" && $hostent['descr'] != "") {
							$iplookup[$hostent['ipaddr']] = $hostent['descr'];
						} else {
							$iplookup[$hostent['ipaddr']] = $hostent['hostname'];
							if ($hostipformat == "fqdn") {
								$iplookup[$hostent['ipaddr']] .= "." . $config['system']['domain'];
							}
						}
					}
				}
			}
		}
	}
	// Add any DNS host override data keyed by IP address.
	foreach (array('dnsmasq', 'unbound') as $dns_type) {
		if (isset($config[$dns_type]['enable'])) {
			if (is_array($config[$dns_type]['hosts'])) {
				foreach ($config[$dns_type]['hosts'] as $hostent) {
					if (($hostent['ip'] != "") && ($hostent['host'] != "")) {
						if ($hostipformat == "descr" && $hostent['descr'] != "") {
							$iplookup[$hostent['ip']] = $hostent['descr'];
						} else {
							$iplookup[$hostent['ip']] = $hostent['host'];
							if ($hostipformat == "fqdn") {
								$iplookup[$hostent['ip']] .= "." . $hostent['domain'];
							}
						}
					}
				}
			}
		}
	}
}

$_grb = exec("/usr/local/bin/rate -i {$real_interface} -nlq 1 -Aba 20 {$sort_method} {$ratesubnet} | tr \"|\" \" \" | awk '{ printf \"%s:%s:%s:%s:%s\\n\", $1,  $2,  $4,  $6,  $8 }'", $listedIPs);

$someinfo = false;
for ($x=2; $x<12; $x++) {

	$bandwidthinfo = $listedIPs[$x];

	// echo $bandwidthinfo;
	$emptyinfocounter = 1;
	if ($bandwidthinfo != "") {
		$infoarray = explode (":", $bandwidthinfo);
		if (($filter == "all") ||
		    (($filter == "local") && (ip_in_subnet($infoarray[0], $intsubnet))) ||
		    (($filter == "remote") && (!ip_in_subnet($infoarray[0], $intsubnet)))) {
			if ($hostipformat == "") {
				// pass back just the raw IP address
				$addrdata = $infoarray[0];
			} else {
				// $hostipformat is one of "hostname", "descr" or "fqdn" - we want a text representation if we can get it.
				if ($iplookup[$infoarray[0]] != "") {
					// We have a local entry, so use it.
					$addrdata = $iplookup[$infoarray[0]];
				} else {
					// Try to reverse lookup the IP address.
					$addrdata = gethostbyaddr($infoarray[0]);
					if ($addrdata != $infoarray[0]) {
						// Reverse lookup returned something other than the IP address (FQDN, we hope!)
						if ($hostipformat != "fqdn") {
							// The user does not want the whole FQDN, so only pass back the first part of the name.
							$name_array = explode(".", $addrdata);
							$addrdata = $name_array[0];
						}
					}
				}
			}
			//print host information;
			echo $addrdata . ";" . $infoarray[1] . ";" . $infoarray[2] . "|";

			//mark that we collected information
			$someinfo = true;
		}
	}
}
unset($bandwidthinfo, $_grb);
unset($listedIPs);

//no bandwidth usage found
if ($someinfo == false) {
	echo gettext("no info");
}
?>
