<?php

/* make sure we are coming from 127.0.0.1 */
$ip = getenv('REMOTE_ADDR');
if($ip <> "127.0.0.1") 
	exit;

/* preload */
require("config.inc");
require("functions.inc");
require("auth.inc");

?>