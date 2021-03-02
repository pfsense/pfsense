<?php
/*
 * bandwidth_by_ip.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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

$has_dhcp = is_dhcp_server_enabled_on_interface($interface);

$intip = find_interface_ip($real_interface);
$intip6 = find_interface_ipv6($real_interface);
//get interface subnet
$netmask = find_interface_subnet($real_interface);
$netmask6 = find_interface_subnetv6($real_interface);
$intsubnet = gen_subnet($intip, $netmask) . "/$netmask";
$intsubnet6 = gen_subnet($intip6, $netmask6) . "/$netmask";

// see if they want local, remote or all IPs returned
$filter = $_REQUEST['filter'];

if ($filter == "") {
	$filter = "local";
}

if ($filter == "local") {
	$ratesubnet = "-c " . $intsubnet;
	$iftopParam = $has_dhcp ? "hidesource" : "all";
} else {
	// Tell the rate utility to consider the whole internet (0.0.0.0/0)
	// and to consider local "l" traffic - i.e. traffic within the whole internet
	// then we can filter the resulting output as we wish below.
	$ratesubnet = "-lc 0.0.0.0/0";
	$iftopParam = ($filter == "all" || !$has_dhcp) ? "all" : "hidedestination";
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

$tryToResolveHostAssignmentFromStaticRouteSubnets = function(string $ip, bool $shortenIp) {
    	global $config;
	if ($ip) {
	    $routes = $config['staticroutes']['route'];
	    foreach ($routes as $route) {
	        if (ip_in_subnet($ip, $route['network'])) {
	            if ($shortenIp && preg_match('~([0-9]{1,3}\.[0-9]{1,3}|[0-9a-f]{1,4})[:]*$~i', $ip, $match)) {
	                $shortIpIdent = $match[1];
		    }
	            return ($shortIpIdent ?? $ip) . '@' . ($route['descr'] ?: $route['gateway']);
		}
	    }
	}
	return $ip;
};

$format_bits = function ($num) {
	$units = array('', 'k', 'M', 'G', 'T');

	$i = 0;
	while ($num >= 1000 && $i < count($units)) {
		$num /= 1000;
		$i++;
	}
	$precision = $i > 0 ? 1 : 0;
	$num = number_format($num, $precision, '.', '');

	return ("$num {$units[$i]}");
};

//get the mode
$mode = !empty($_REQUEST['mode']) ? $_REQUEST['mode'] : '';
if ($mode == "iftop") {
    $current_ts = time();
    $pidFile = "/var/run/iftop_{$real_interface}_{$iftopParam}.pid";
    $logFile = "/var/db/iftop_{$real_interface}_{$iftopParam}.log";

    $since = null;
    if (file_exists($pidFile)) {
	$since = $current_ts - filemtime($pidFile);
    }

    $logExists = file_exists($logFile);
    if (!$since || $since >= 3 || !$logExists) {
	$_grb = exec("/usr/local/bin/iftop_parser.sh {$real_interface} {$iftopParam} 1>/dev/null 2>&1 &", $listedIPs);
    }

    if ($logExists) {
	$listedIPs = file($logFile);
    }

	// order and group by
	$arr_in = array();
	$arr_out = array();
	foreach ($listedIPs as $k => $line) {
		if ($line != "") {
			$arrLine = explode (";", $line);
			$ip  = $arrLine[0];
			$in  = unformat_number($arrLine[1]);
			$out = unformat_number($arrLine[2]);
			if (isset($arr_in[$ip])) {
				$arr_in[$ip]  += $in;
				$arr_out[$ip] += $out;
			} else {
				$arr_in[$ip]  = $in;
				$arr_out[$ip] = $out;
			}
		}
	}

	if ($sort == "out") {
		arsort($arr_out,SORT_NUMERIC);
		$arrIP = array_keys($arr_out);
	} else {
		arsort($arr_in,SORT_NUMERIC);
		$arrIP = array_keys($arr_in);
	}

	unset($listedIPs);
	$listedIPs[] = "";
	$listedIPs[] = "";
	foreach ($arrIP as $k => $ip) {
		$listedIPs[] = $ip . ";" . $format_bits($arr_in[$ip]) . ";" . $format_bits($arr_out[$ip]);
	}

} else {
	$_grb = exec("/usr/local/bin/rate -i {$real_interface} -nlq 1 -Aba 20 {$sort_method} {$ratesubnet} | tr \"|\" \" \" | awk '{ printf \"%s;%s;%s;%s;%s\\n\", $1,  $2,  $4,  $6,  $8 }'", $listedIPs);
}


$someinfo = false;
$formatNumericRate = function(&$value){
    // rate and iftop output format is inconsistent, unify it
    $value = preg_replace('~^([\d.]+)[ ]?([a-z]?)$~i', '\\1 \\2', $value);
};

for ($x=2; $x<12; $x++) {

	$bandwidthinfo = $listedIPs[$x];

	// echo $bandwidthinfo;
	$emptyinfocounter = 1;
	if ($bandwidthinfo != "") {
		$infoarray = explode (";", $bandwidthinfo);
		if ($infoarray[0] == '*') {
			continue;
		}
		if (($filter == "all") ||
		    (($filter == "local") && ((ip_in_subnet($infoarray[0], $intsubnet)) || (ip_in_subnet($infoarray[0], $intsubnet6)))) ||
		    (($filter == "remote") && ((!ip_in_subnet($infoarray[0], $intsubnet)) || (!ip_in_subnet($infoarray[0], $intsubnet6))))) {
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
					} else {
						$addrdata = $tryToResolveHostAssignmentFromStaticRouteSubnets($infoarray[0], $hostipformat != "fqdn");
					}
				}
			}

			$formatNumericRate($infoarray[1]);
			$formatNumericRate($infoarray[2]);
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
