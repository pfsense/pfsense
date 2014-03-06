<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 *
 */

/*
	pfSense_BUILDER_BINARIES:	/usr/local/bin/rate
	pfSense_MODULE:	trafficgraph
*/

require_once('guiconfig.inc');
require_once('interfaces.inc');
require_once('pfsense-utils.inc');
require_once('util.inc');

$listedIPs = "";

//get interface IP and break up into an array
$interface = $_GET['if'];
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
$filter = $_GET['filter'];

if ($filter == "")
	$filter = "local";

if ($filter == "local") {
	$ratesubnet = "-c " . $intsubnet;
} else {
	// Tell the rate utility to consider the whole internet (0.0.0.0/0)
	// and to consider local "l" traffic - i.e. traffic within the whole internet
	// then we can filter the resulting output as we wish below.
	$ratesubnet = "-lc 0.0.0.0/0";
}

//get the sort method
$sort = $_GET['sort'];
if ($sort == "out") 
	{$sort_method = "-T";}
else
	{$sort_method = "-R";}

// get the desired format for displaying the host name or IP
$hostipformat = $_GET['hostipformat'];
$iplookup = array();
// If hostname display is requested and the DNS forwarder does not already have DHCP static names registered,
// then load the DHCP static mappings into an array keyed by IP address.
if (($hostipformat != "") && (!isset($config['dnsmasq']['enable']) || !isset($config['dnsmasq']['regdhcpstatic']))) {
	if (is_array($config['dhcpd'])) {
		foreach ($config['dhcpd'] as $ifdata) {
			if (is_array($ifdata['staticmap'])) {
				foreach ($ifdata['staticmap'] as $hostent) {
					if (($hostent['ipaddr'] != "") && ($hostent['hostname'] != "")) {
						$iplookup[$hostent['ipaddr']] = $hostent['hostname'];
					}
				}
			}
		}
	}
}

$_grb = exec("/usr/local/bin/rate -i {$real_interface} -nlq 1 -Aba 20 {$sort_method} {$ratesubnet} | tr \"|\" \" \" | awk '{ printf \"%s:%s:%s:%s:%s\\n\", $1,  $2,  $4,  $6,  $8 }'", $listedIPs);

$someinfo = false;
for ($x=2; $x<12; $x++){

    $bandwidthinfo = $listedIPs[$x];

   // echo $bandwidthinfo;
    $emptyinfocounter = 1;
    if ($bandwidthinfo != "") {
        $infoarray = explode (":",$bandwidthinfo);
		if (($filter == "all") ||
		    (($filter == "local") && (ip_in_subnet($infoarray[0], $intsubnet))) ||
		    (($filter == "remote") && (!ip_in_subnet($infoarray[0], $intsubnet)))) {
			if ($hostipformat == "") {
				$addrdata = $infoarray[0];
			} else {
				// $hostipformat is "hostname" or "fqdn"
				$addrdata = gethostbyaddr($infoarray[0]);
				if ($addrdata == $infoarray[0]) {
					// gethostbyaddr() gave us back the IP address, so try the static mapping array
					if ($iplookup[$infoarray[0]] != "")
						$addrdata = $iplookup[$infoarray[0]];
				} else {
					if ($hostipformat == "hostname") {
						// Only pass back the first part of the name, not the FQDN.
						$name_array = explode(".", $addrdata);
						$addrdata = $name_array[0];
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
if ($someinfo == false)
    echo gettext("no info");

?>
