#!/usr/local/bin/php -q
<?php
require_once("config.inc");
require_once("globals.inc");
require_once("notices.inc");
$options = getopt("s::");

$message = "";

if($options['s'] <> "") {
	$subject = $options['s'];
}


$in = file("php://stdin");
foreach($in as $line){
	$message .= "$line";
}

if (!empty($subject))
	send_smtp_message($message, $subject);
else
	send_smtp_message($message);
?>