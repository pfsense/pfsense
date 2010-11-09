#!/usr/local/bin/php -f 
<?php

	/* upgrade embedded users serial console */
	require_once("globals.inc");
	require_once("config.inc");
	require_once("functions.inc");

	if($g['platform'] == "embedded") {
		$config['system']['enableserial'] = true;
		write_config();
	}

	setup_serial_port();
		
	if($g['platform'] == "pfSense" or $g['platform'] == "nanobsd")
		touch("/conf/needs_package_sync");

	$files_to_process = split("\n", file_get_contents("/etc/pfSense.obsoletedfiles"));
	foreach($files_to_process as $filename) 
		if(file_exists($filename)) 
			exec("/bin/rm -f $filename");

?>