#!/usr/local/bin/php -f 
<?php

	/* upgrade embedded users serial console */
	require_once("globals.inc");
	require_once("config.inc");
	require_once("functions.inc");

	if(file_exists("/usr/local/bin/git") && isset($config['system']['gitsync']['synconupgrade'])) {
		if(!empty($config['system']['gitsync']['repositoryurl']))
			exec("cd /root/pfsense/pfSenseGITREPO/pfSenseGITREPO && git config remote.origin.url " . escapeshellarg($config['system']['gitsync']['repositoryurl']));
		if(!empty($config['system']['gitsync']['branch']))
			system("pfSsh.php playback gitsync " . escapeshellarg($config['system']['gitsync']['branch']) . " --upgrading");
	}

	$newslicedir = "";
	if ($argv[1] != "")
		$newslicedir = '/tmp/' . $argv[1];

	if($g['enableserial_force'] || file_exists("{$newslicedir}/enableserial_force")) {
		$config['system']['enableserial'] = true;
		write_config();
	}

	system("echo \"Adding serial port settings ({$newslicedir})...\" >> /conf/upgrade_log.txt");
	setup_serial_port("upgrade", $newslicedir);
		
	$files_to_process = file("/etc/pfSense.obsoletedfiles");
	foreach($files_to_process as $filename) 
		if(file_exists($filename)) 
			exec("/bin/rm -f $filename");

?>
