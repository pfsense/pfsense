#!/usr/local/bin/php-cgi -q
<?php
require_once("config.inc");
require_once("globals.inc");
require_once("notices.inc");
$options = getopt("s::");

$message = "";

if ($options['s'] <> "") {
	$subject = $options['s'];
}
else if (!empty($_GET['subject'])) {
        $subject = $_GET['subject'];
}


$in = file("php://stdin");
foreach ($in as $line) {
	$message .= "$line";
}

if (!empty($subject)) {
	send_smtp_message($message, $subject);
} else {
	send_smtp_message($message);
}
?>
