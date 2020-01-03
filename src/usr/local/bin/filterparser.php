#!/usr/local/bin/php-cgi -q
<?php
/*
 * filterparser.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2009-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Originally based on m0n0wall (http://m0n0.ch/wall)
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

/*
 * A quick CLI log parser.
 * Examples:
 *  tail -50 /var/log/filter.log | /usr/local/www/filterparser.php
 *  tail -F /var/log/filter.log | /usr/local/www/filterparser.php
 */

include_once("functions.inc");
include_once("syslog.inc");

$log = fopen("php://stdin", "r");
$lastline = "";
while (!feof($log)) {
	$line = fgets($log);
	$line = rtrim($line);
	$flent = parse_firewall_log_line(trim($line));
	if ($flent != "") {
		$flags = (($flent['proto'] == "TCP") && !empty($flent['tcpflags'])) ? ":" . $flent['tcpflags'] : "";
		echo "{$flent['time']} {$flent['act']} {$flent['realint']} {$flent['proto']}{$flags} {$flent['src']} {$flent['dst']}\n";
		$flent = "";
	}
}
fclose($log); ?>
