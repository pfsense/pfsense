#!/usr/local/bin/php -f
<?php

if(empty($argv[1])) {
	echo "No modem device given \n";
	exit(0);
}

/* Huawei example */
$device = "/dev/{$argv[1]}";
$statfile = "/tmp/3gstats.{$argv[2]}";
$header = "#seconds,rssi,mode,upstream,downstream,sentbytes,receivedbyts,bwupstream,bwdownstream\n";

$i = 0;

$record = array();
$handle = fopen($device, "r");
if(! $handle) {
	echo "Can not open modem stats device\n";
	exit(1);
}
$record['mode'] = 0;
$record['rssi'] = 0;
$record['time'] = 0;
$record['upstream'] = 0;
$record['downstream'] = 0;
$record['sent'] = 0;
$record['received'] = 0;
$record['bwupstream'] = 0;
$record['bwdownstream'] = 0;
while(true) {
	$string = "";
	$string = fgets($handle, 256);

	$elements = array();
	$elements = split(":", $string);
	$elements[0] = trim($elements[0]);
	$elements[1] = trim($elements[1]);

	switch($elements[0]) {
		case "^MODE":
			$record['mode'] = $elements[1];
			break;
		case "^RSSI":
			$record['rssi'] = $elements[1];
			break;
		case "^DSFLOWRPT":
			$items = array();
			$items = split(",", $elements[1]);
			$record['time'] = hexdec($items[0]);
			$record['upstream'] = hexdec($items[1]);
			$record['downstream'] = hexdec($items[2]);
			$record['sent'] = hexdec($items[3]);
			$record['received'] = hexdec($items[4]);
			$record['bwupstream'] = hexdec($items[5]);
			$record['bwdownstream'] = hexdec($items[6]);
			break;
	}

	if($i > 10) {
		$csv = $header . "{$record['time']},{$record['rssi']},{$record['mode']},{$record['upstream']},{$record['downstream']},{$record['sent']},{$record['received']},{$record['bwupstream']},{$record['bwdownstream']}\n";
		file_put_contents($statfile, $csv);
		$i = 0;
	}
	$i++;
}
fclose($handle);
?>
