#!/usr/local/bin/php-cgi -f
<?php
/*
 * 3gstats.php
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

ini_set("max_execution_time", "0");

if (empty($argv[1])) {
	echo "No modem device given \n";
	exit(0);
}

/* Huawei example */
$device = "/dev/{$argv[1]}";
$statfile = "/tmp/3gstats.{$argv[2]}";
/* mode is a comma separated value, thus submode is born */
$header = "#seconds,rssi,mode,submode,upstream,downstream,sentbytes,receivedbyts,bwupstream,bwdownstream,simstate,service\n";

$i = 0;

$record = array();
$handle = fopen($device, "r");
if (!$handle) {
	echo "Can not open modem stats device\n";
	exit(1);
}

$record['time'] = 0;
$record['rssi'] = 0;
$record['mode'] = "0,0";
$record['upstream'] = 0;
$record['downstream'] = 0;
$record['sent'] = 0;
$record['received'] = 0;
$record['bwupstream'] = 0;
$record['bwdownstream'] = 0;
$record['simstate'] = 255;
$record['service'] = 255;

while (true) {
	$string = "";
	$string = fgets($handle, 256);

	$elements = array();
	$elements = explode(':', $string);
	$elements[0] = trim($elements[0]);
	$elements[1] = trim($elements[1]);

	switch ($elements[0]) {
		case "^MODE":
			$record['mode'] = $elements[1];
			break;
		case "^SRVST":
			$record['service'] = $elements[1];
			break;
		case "^SIMST":
			$record['simstate'] = $elements[1];
			break;
		case "^RSSI":
			$record['rssi'] = $elements[1];
			break;
		case "^DSFLOWRPT":
			$items = array();
			$items = explode(',', $elements[1]);
			$record['time'] = hexdec($items[0]);
			$record['upstream'] = round((floatval(hexdec($items[1])) * 8) /1024);
			$record['downstream'] = round((floatval(hexdec($items[2])) * 8) /1024);
			$record['sent'] = hexdec($items[3]);
			$record['received'] = hexdec($items[4]);
			$record['bwupstream'] = round((floatval(hexdec($items[5])) * 8) /1024);
			$record['bwdownstream'] = round((floatval(hexdec($items[6])) * 8) /1024);
			break;
	}

	if ($i > 10) {
		$csv = $header;
		$csv .= implode(",", $record);
		$csv .= "\n";
		file_put_contents($statfile, $csv);
		$i = 0;
	}
	$i++;
}
fclose($handle);
?>
