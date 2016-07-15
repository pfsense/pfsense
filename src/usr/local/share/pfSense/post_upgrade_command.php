#!/usr/local/bin/php -f
<?php
/*
 * post_upgrade_command.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

	/* upgrade embedded users serial console */
	require_once("globals.inc");
	require_once("config.inc");
	require_once("functions.inc");

	if (file_exists("/usr/local/bin/git") && isset($config['system']['gitsync']['synconupgrade'])) {
		if (!empty($config['system']['gitsync']['repositoryurl'])) {
			exec("cd /root/pfsense/pfSenseGITREPO/pfSenseGITREPO && git config remote.origin.url " . escapeshellarg($config['system']['gitsync']['repositoryurl']));
		}
		if (!empty($config['system']['gitsync']['branch'])) {
			system("pfSsh.php playback gitsync " . escapeshellarg($config['system']['gitsync']['branch']) . " --upgrading");
		}
	}

	$newslicedir = "";
	if (isset($argv[1]) && $argv[1] != "") {
		$newslicedir = '/tmp/' . $argv[1];
	}

	if ($g['enableserial_force'] || file_exists("{$newslicedir}/enableserial_force")) {
		$config['system']['enableserial'] = true;
		write_config();
	}

	system("echo \"Adding serial port settings ({$newslicedir})...\" >> /conf/upgrade_log.txt");
	setup_serial_port("upgrade", $newslicedir);

?>
