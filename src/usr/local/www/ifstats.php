<?php
/*
 * ifstats.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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

##|+PRIV
##|*IDENT=page-xmlrpcinterfacestats
##|*NAME=XMLRPC Interface Stats
##|*DESCR=Allow access to the 'XMLRPC Interface Stats' page.
##|*MATCH=ifstats.php*
##|-PRIV

$nocsrf = true;

require_once('auth_check.inc');
require_once("interfaces.inc");


//overload the use of this page until the conversion of both traffic graphs have been completed
if($_POST['if']) {

	$ifs = $_POST['if'];

	$ifarray = explode("|", $ifs);

	$temp = gettimeofday();
	$timing = (double)$temp["sec"] + (double)$temp["usec"] / 1000000.0;
	$obj = [];
	$count = 0;

	foreach ($ifarray as $if) {

		$realif = get_real_interface($if);

		if (!$realif) {
			$realif = $if; // Need for IPsec case interface.
		}

		$ifinfo = pfSense_get_interface_stats($realif);

		$obj[$if] = [];

		$obj[$if][0]['key'] = $if . "in";
		$obj[$if][0]['values'] = array($timing, $ifinfo['inbytes']);

		$obj[$if][1]['key'] = $if . "out";
		$obj[$if][1]['values'] = array($timing, $ifinfo['outbytes']);
/*
		$obj[$count]['key'] = $if . "in";
		$obj[$count]['name'] = $if . " (in)";
		$obj[$count]['values'] = array($timing, $ifinfo['inbytes']);

		$count++;

		$obj[$count]['key'] = $if . "out";
		$obj[$count]['name'] = $if . " (out)";
		$obj[$count]['values'] = array($timing, $ifinfo['outbytes']);

		$count++;
*/
	}

	header('Content-Type: application/json');
	echo json_encode($obj,JSON_PRETTY_PRINT|JSON_PARTIAL_OUTPUT_ON_ERROR|JSON_NUMERIC_CHECK);

} else {

	$if = $_REQUEST['if'];

	$realif = get_real_interface($if);

	if (!$realif) {
		$realif = $if; // Need for IPsec case interface.
	}

	$ifinfo = pfSense_get_interface_stats($realif);

	$temp = gettimeofday();
	$timing = (double)$temp["sec"] + (double)$temp["usec"] / 1000000.0;

	header("Last-Modified: " . gmdate("D, j M Y H:i:s") . " GMT");
	header("Expires: " . gmdate("D, j M Y H:i:s", time()) . " GMT");
	header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP/1.1
	header("Pragma: no-cache"); // HTTP/1.0

	echo "$timing|" . $ifinfo['inbytes'] . "|" . $ifinfo['outbytes'] . "\n";

}

?>
