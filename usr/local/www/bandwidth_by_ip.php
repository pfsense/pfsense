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
//get the sort method
$sort = $_GET['sort'];
if ($sort == "out") 
	{$sort_method = "-T";}
else
	{$sort_method = "-R";}

$_grb = exec("/usr/local/bin/rate -i {$real_interface} -nlq 1 -Aba 20 {$sort_method} -c {$intsubnet} | tr \"|\" \" \" | awk '{ printf \"%s:%s:%s:%s:%s\\n\", $1,  $2,  $4,  $6,  $8 }'", $listedIPs);

$someinfo = false;
for ($x=2; $x<12; $x++){

    $bandwidthinfo = $listedIPs[$x];

   // echo $bandwidthinfo;
    $emptyinfocounter = 1;
    if ($bandwidthinfo != "") {
        $infoarray = explode (":",$bandwidthinfo);
        //print IP of host;
        echo $infoarray[0] . ";" . $infoarray[1] . ";" . $infoarray[2] . "|";

        //mark that we collected information
        $someinfo = true;
    }
}
unset($bandwidthinfo, $_grb);
unset($listedIPs);

//no bandwidth usage found
if ($someinfo == false)
    echo gettext("no info");

?>
