<?php
/*
 * ifstats.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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

if($_POST['if']) {
	$ifs = $_POST['if'];
	$realifs = $_POST['realif'];

	$ifarray = explode("|", $ifs);
	$realifarray = explode("|", $realifs);

	$temp = gettimeofday();
	$timing = (double)$temp["sec"] + (double)$temp["usec"] / 1000000.0;
	$obj = [];
	$count = 0;

	$i = 0;
	for ($i = 0; $i < count($ifarray); $i++) {
		$if = $ifarray[$i];
		$realif = $realifarray[$i];
		if (!$realif) {
			$realif = $if; // Need for IPsec case interface.
		}

		$ifinfo = pfSense_get_interface_stats($realif);

		$obj[$if] = [];

		$obj[$if][0]['key'] = $if . "in";
		$obj[$if][0]['values'] = array($timing, $ifinfo['inbytes']);

		$obj[$if][1]['key'] = $if . "out";
		$obj[$if][1]['values'] = array($timing, $ifinfo['outbytes']);
	}

	header('Content-Type: application/json');
	echo json_encode($obj,JSON_PRETTY_PRINT|JSON_PARTIAL_OUTPUT_ON_ERROR|JSON_NUMERIC_CHECK);
}
