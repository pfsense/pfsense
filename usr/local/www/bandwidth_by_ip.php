<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 *
 *
 *
 *
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
$real_interface = convert_friendly_interface_to_real_interface_name($interface);
$intip = find_interface_ip($real_interface);
$intip = explode (".", $intip);

//use class A subnet to make sure we capture all traffic on specified interface
$intsubnet = $intip[0] . ".0.0.0/8";

exec("rate -i {$real_interface} -nlq 1 -A -c {$intsubnet}", $listedIPs);

unset($bandwidthinfo);
unset($receivebytesarray);
unset($transmitbytesarray);

$someinfo = false;
for ($x=2; $x<12; $x++){

    $bandwidthinfo = $listedIPs[$x];

   // echo $bandwidthinfo;
    $emptyinfocounter = 1;
    if ($bandwidthinfo != "") {
        $splitinfo = explode ("|",$bandwidthinfo);
        $receivebytesarray = explode(" ",$splitinfo[0]);
        //print IP of host;
        echo $receivebytesarray[0] . ";";

        //skip empty array elements until first element found with data
        while ($receivebytesarray[$emptyinfocounter] == "")
        {
            $emptyinfocounter++;
        }
        //print received bytes for host
        echo $receivebytesarray[$emptyinfocounter] . ";";

        $transmitbytesarray = explode(" ",$splitinfo[1]);

        $emptyinfocounter = 1;

        //skip empty array elements until first element found with data
        while ($transmitbytesarray[$emptyinfocounter] == "")
        {
            $emptyinfocounter++;
        }
        //print transmitted bytes for host
        echo $transmitbytesarray[$emptyinfocounter] . "|";

        //mark that we collected information
        $someinfo = true;
    }
}


//no bandwidth usage found
if ($someinfo == false)
    echo "no info";

?>
