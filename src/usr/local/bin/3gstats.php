#!/usr/local/bin/php-cgi -f
<?php
/*
 * 3gstats.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *
 * 3. All advertising materials mentioning features or use of this software
 *    must display the following acknowledgment:
 *    "This product includes software developed by the pfSense Project
 *    for use in the pfSense® software distribution. (http://www.pfsense.org/).
 *
 * 4. The names "pfSense" and "pfSense Project" must not be used to
 *    endorse or promote products derived from this software without
 *    prior written permission. For written permission, please contact
 *    coreteam@pfsense.org.
 *
 * 5. Products derived from this software may not be called "pfSense"
 *    nor may "pfSense" appear in their names without prior written
 *    permission of the Electric Sheep Fencing, LLC.
 *
 * 6. Redistributions of any form whatsoever must retain the following
 *    acknowledgment:
 *
 * "This product includes software developed by the pfSense Project
 * for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 * THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 * EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 * ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 * STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 * OF THE POSSIBILITY OF SUCH DAMAGE.
 */

ini_set("max_execution_time", "0");

if(empty($argv[1])) {
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
if(! $handle) {
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
$record['simstate'] = 0;
$record['service'] = 0;

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
