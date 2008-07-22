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
	if(file_exists("/usr/local/www/index.html"))
		unlink("/usr/local/www/index.html"); 
	if(file_exists("/usr/local/www/fred.png"))
		unlink("/usr/local/www/fred.png"); 
	if(file_exists("/usr/local/www/dfuife.cgi"))
		unlink("/usr/local/www/dfuife.cgi");
		
	if($g['platform'] == "pfSense")
		touch("/conf/needs_package_sync");

?>