#!/usr/local/bin/php -q
<?php
/* $Id$ */
/*
	filterparser.php
	part of pfSesne by Scott Ullrich
	originally based on m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2009 Jim Pingle <myfirstname>@<mylastname>.org
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.

 A quick CLI log parser. 
 Examples: 
	clog /var/log/filter.log | tail -50 | /usr/local/www/filterparser.php
	clog -f /var/log/filter.log | /usr/local/www/filterparser.php
*/
/*
	pfSense_MODULE:	logs
*/

include_once("functions.inc");
include_once("filter_log.inc");

$log = fopen("php://stdin", "r");
$lastline = "";
while(!feof($log)) { 
	$line = fgets($log);
	$line = rtrim($line);
	$line_split = "";
	preg_match("/.*\spf:\s(.*)/", $line, $line_split);
	if (substr($line_split[1], 0, 4) != "    ") {
		$flent = "";
		if (($lastline != "") && (substr($lastline, 0, 1) != " ")) {
			$flent = parse_filter_line(trim($lastline));
		}
		$lastline = $line;
	} else {
		$lastline .= substr($line_split[1], 3);
	}
	/* Available fields:
	 time       - Time the packet was seen
	 rulenum    - Rule number matched
	 act        - Action (pass/block)
	 interface  - Friendly interface name (WAN, LAN, etc)
	 realint    - Real interface name (fxp0, em0, vr0, etc)
	 proto      - Protocol (e.g. TCP, UDP, ICMP, etc)
	 tcpflags   - TCP flags/control bits
	 src        - Source address with port
	 srcip      - Source IP
	 srcport    - Source Port
	 dst        - Destination address with port
	 dstip      - Destination IP
	 dstport    - Destination Port
	*/
	$flags = (($flent['proto'] == "TCP") && !empty($flent['tcpflags'])) ? ":" . $flent['tcpflags'] : "";
	if ($flent != "") {
		echo "{$flent['time']} {$flent['act']} {$flent['realint']} {$flent['proto']}{$flags} {$flent['src']} {$flent['dst']}\n";
		$flent = "";
	}
}
fclose($log); ?>