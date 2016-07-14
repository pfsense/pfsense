#!/usr/local/bin/php-cgi -q
<?php
/*
 * filterparser.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2009-2016 Electric Sheep Fencing, LLC
 * All rights reserved.
 *
 * Originally based on m0n0wall (http://m0n0.ch/wall)
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

/*
 * A quick CLI log parser.
 * Examples:
 *  clog /var/log/filter.log | tail -50 | /usr/local/www/filterparser.php
 *  clog -f /var/log/filter.log | /usr/local/www/filterparser.php
 */

include_once("functions.inc");
include_once("filter_log.inc");

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
