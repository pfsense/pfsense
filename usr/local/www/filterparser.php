#!/usr/local/bin/php -q
<?php
/*
 A quick CLI log parser. 
 Examples: 
	clog /var/log/filter.log | tail -50 | /usr/local/www/filterparser.php
	clog -f /var/log/filter.log | /usr/local/www/filterparser.php
*/
include_once("includes/log.inc.php");
include_once("interfaces.inc");

$log = fopen("php://stdin", "r");
while(!feof($log)) { 
	$line = fgets($log);
	$flent = parse_filter_line(trim($line));
	/* Available fields:
	 time       - Time the packet was seen
	 rulenum    - Rule number matched
	 act        - Action (pass/block)
	 interface  - Friendly interface name (WAN, LAN, etc)
	 realint    - Real interface name (fxp0, em0, vr0, etc)
	 proto      - Protocol (e.g. TCP, UDP, ICMP, etc)
	 tcpflags   - TCP flags/control bits
	 src        - Source address with port
	 dst        - Destination address with port
	*/
	echo $flent['time'] . " " . $flent['act'] . " " . $flent['realint'] . " " . $flent['proto'] . " " . $flent['src'] . " " . $flent['dst'] . "\n";
}
fclose($log); ?>