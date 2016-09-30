#!/usr/local/bin/php -f
<?php
/*
 * post_upgrade_command.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *
 * 3. All advertising materials mentioning features or use of this software
 *    must display the following acknowledgment:
 *    "This product includes software developed by the pfSense Project
 *    for use in the pfSense® software distribution. (http://www.pfsense.org/).
 *
 * 4. The names "pfSense" and "pfSense Project" must not be used to
 *    endorse or promote products derived from this software without
 *    prior written permission. For written permission, please contact
 *    coreteam@pfsense.org.
 *
 * 5. Products derived from this software may not be called "pfSense"
 *    nor may "pfSense" appear in their names without prior written
 *    permission of the Electric Sheep Fencing, LLC.
 *
 * 6. Redistributions of any form whatsoever must retain the following
 *    acknowledgment:
 *
 * "This product includes software developed by the pfSense Project
 * for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 * THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 * EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 * ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 * STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 * OF THE POSSIBILITY OF SUCH DAMAGE.
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

			$options  = isset($config['system']['gitsync']['minimal']) ? ' --minimal' : '';
			$options .= isset($config['system']['gitsync']['diff']) ? ' --diff' : '';
			$options .= isset($config['system']['gitsync']['show_files']) ? ' --show-files' : '';
			$options .= isset($config['system']['gitsync']['show_command']) ? ' --show-command' : '';
			$options .= isset($config['system']['gitsync']['dryrun']) ? ' --dry-run' : '';

			system("pfSsh.php playback gitsync " . escapeshellarg($config['system']['gitsync']['branch']) . $options . " --upgrading");
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
