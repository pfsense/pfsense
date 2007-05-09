#!/usr/local/bin/php -f 
<?php
	/* upgrade embedded users serial console */
	require_once("globals.inc");
	require_once("functions.inc");
	require_once("config.inc");
	if($g['platform'] == "embedded") {
		$config['system']['enableserial'] = true;
		write_config();
	}
	setup_serial_port();
?>