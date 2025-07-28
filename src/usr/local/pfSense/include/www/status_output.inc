<?php
/*
 * status.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://neon1.net/m0n0wall)
 * Copyright (c) 2003 Jim McBeath <jimmc@macrovision.com>
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

global $console;
global $show_output;
global $errors;

/* include all configuration functions */
if ($console) {
	require_once("config.inc");
} else {
	require_once("guiconfig.inc");
}
require_once("functions.inc");
require_once("gwlb.inc");

$filtered_tags = array(
	'accountkey', 'authorizedkeys', 'auth_pass',
	'auth_server_shared_secret', 'auth_server_shared_secret2', 'auth_user',
	'backwardsyncpassword', 'barnyard_dbpwd', 'bcrypt-hash', 'cert_key', 'community', 'crypto_password',
	'crypto_password2', 'dns_nsupdatensupdate_key', 'ddnsdomainkey', 'encryption_password',
	'etpro_code', 'etprocode', 'gold_encryption_password', 'gold_password',
	'influx_pass', 'ipsecpsk', 'keydata', 'ldap_bindpw', 'ldapbindpass', 'ldap_pass',
	'lighttpd_ls_password', 'maxmind_geoipdb_key', 'maxmind_key', 'md5-hash',
	'md5password', 'md5sigkey', 'md5sigpass', 'nt-hash', 'oinkcode',
	'oinkmastercode', 'pass', 'passphrase', 'password', 'passwordagain',
	'pkcs11pin', 'postgresqlpasswordenc', 'pre-shared-key', 'presharedkey', 'privatekey', 'proxypass',
	'proxy_passwd', 'proxyuser', 'proxy_user', 'prv', 'radmac_secret', 'radius_secret',
	'redis_password', 'redis_passwordagain', 'rocommunity',	'secret', 'secret2', 'securiteinfo_id',
	'serverauthkey', 'sha512-hash', 'shared_key', 'stats_password', 'tls', 'tlspskidentity', 'tlspskfile',
	'varclientpasswordinput', 'varclientsharedsecret', 'varsqlconfpassword',
	'varsqlconf2password', 	'varsyncpassword', 'varmodulesldappassword', 'varmodulesldap2password',
	'varusersmotpinitsecret', 'varusersmotppin', 'varuserspassword', 'webrootftppassword'
);

$acme_filtered_tags = array('key', 'password', 'secret', 'token', 'pwd', 'pw');

function status_cmd_run($title, $command, $method) {
	global $output_path, $output_file, $filtered_tags, $acme_filtered_tags, $show_output, $errors;
	/* Fixup output directory */

	if ($show_output) {
		$rubbish = array('|', '-', '/', '.', ' ');  /* fixes the <a> tag to be W3C compliant */
		echo "\n<a name=\"" . str_replace($rubbish, '', $title) . "\" id=\"" . str_replace($rubbish, '', $title) . "\"></a>\n";
		print('<div class="panel panel-default">');
		print('<div class="panel-heading"><h2 class="panel-title">' . $title . '</h2></div>');
		print('<div class="panel-body">');
		print('<pre>');
	}

	if ($command == "dumpconfigxml") {
		try {
			$ofd = @fopen("{$output_path}/config-sanitized.xml", "w");
			$fd = @fopen("/conf/config.xml", "r");
			if ($fd && $ofd) {
				while (!feof($fd)) {
					$line = fgets($fd);
					/* remove sensitive contents */
					foreach ($filtered_tags as $tag) {
						$line = preg_replace("/<{$tag}>.*?<\\/{$tag}>/", "<{$tag}>xxxxx</{$tag}>", $line);
					}
					/* remove ACME pkg sensitive contents */
					foreach ($acme_filtered_tags as $tag) {
						$line = preg_replace("/<dns_(.+){$tag}>.*?<\\/dns_(.+){$tag}>/", "<dns_$1{$tag}>xxxxx</dns_$1{$tag}>", $line);
					}
					if ($show_output) {
						echo htmlspecialchars(str_replace("\t", "    ", $line), ENT_NOQUOTES);
					}
					fwrite($ofd, $line);
				}
			}
			if ($fd) {
				fclose($fd);
			}
			if ($ofd) {
				fclose($ofd);
			}
		} catch (Exception $e) {
			status_error_add(sprintf(gettext("Failed to sanitize configuration (%s): %s\n"), $title, $e->getMessage()));
		}
	} else {
		try {
			$execOutput = "";
			$execStatus = "";
			$fn = "{$output_path}/" . basename("{$title}.txt");
			if ($method == "exec") {
				exec($command . " > " . escapeshellarg($fn) . " 2>&1", $execOutput, $execStatus);
				if ($show_output) {
					$ofd = @fopen($fn, "r");
					if ($ofd) {
						while (!feof($ofd)) {
							echo htmlspecialchars(fgets($ofd), ENT_NOQUOTES);
						}
						fclose($ofd);
					}
				}
				if ($execStatus !== 0) {
					throw new Exception(sprintf(gettext('Command had non-zero exit status: %d'), $execStatus));
				}
			} elseif ($method == "php_func") {
				$execOutput = $command();
				if ($show_output) {
					echo htmlspecialchars($execOutput, ENT_NOQUOTES);
				}
				file_put_contents($fn, $execOutput);
			}
		} catch (Exception $e) {
			status_error_add(sprintf(gettext("Failed to execute command (%s): %s\n"), $title, $e->getMessage()));
		}
	}

	if ($show_output) {
		print('</pre>');
		print('</div>');
		print('</div>');
	}
}

/* Define a command, with a title, to be executed later. */
function status_cmd_define($title, $command, $method = "exec") {
	global $commands;
	$title = htmlspecialchars($title, ENT_NOQUOTES);
	$commands[] = array($title, $command, $method);
}

/* List all of the commands as an index. */
function status_cmd_list() {
	global $currentDate;
	global $commands;

	$rubbish = array('|', '-', '/', '.', ' ');	/* fixes the <a> tag to be W3C compliant */

	print('<div class="panel panel-default">');
	print('<div class="panel-heading"><h2 class="panel-title">' . sprintf(gettext("Firewall Status on %s"), $currentDate) . '</h2></div>');
	print('<div class="panel-body">');
	print('    <div class="content">');
	print("\n<p>" . gettext("This status page includes the following information") . ":\n");
	print("<ul>\n");
	for ($i = 0; isset($commands[$i]); $i++) {
		print("\t<li><strong><a href=\"#" . str_replace($rubbish, '', $commands[$i][0]) . "\">" . $commands[$i][0] . "</a></strong></li>\n");
	}

	print("</ul>\n");
	print('	       </div>');
	print('	   </div>');
	print('</div>');
}

/* Execute all of the commands which were defined by a call to defCmd. */
function status_cmd_run_all() {
	global $commands;
	for ($i = 0; isset($commands[$i]); $i++) {
		status_cmd_run($commands[$i][0], $commands[$i][1], $commands[$i][2]);
	}
}

function status_get_firewall_info() {
	global $g, $output_path;
	/* Firewall Platform/Serial */
	$firewall_info = "Product Name: " . htmlspecialchars(g_get('product_label'));
	$platform = system_identify_specific_platform();
	if (!empty($platform['descr'])) {
		$firewall_info .= "<br/>Platform: " . htmlspecialchars($platform['descr']);
	}

	if (file_exists('/var/db/uniqueid')) {
		$ngid = file_get_contents('/var/db/uniqueid');
		if (!empty($ngid)) {
			$firewall_info .= "<br/>Netgate Device ID: " . htmlspecialchars($ngid);
		}
	}

	$serial = system_get_serial();
	if (!empty($serial)) {
		$firewall_info .= "<br/>Serial: " . htmlspecialchars($serial);
	}

	if (!empty(g_get('product_version_string'))) {
		$firewall_info .= "<br/>" . htmlspecialchars(g_get('product_label')) .
		    " version: " . htmlspecialchars(g_get('product_version_string'));
	}

	if (file_exists('/etc/version.buildtime')) {
		$build_time = file_get_contents('/etc/version.buildtime');
		if (!empty($build_time)) {
			$firewall_info .= "<br/>Built On: " . htmlspecialchars($build_time);
		}
	}
	if (file_exists('/etc/version.lastcommit')) {
		$build_commit = file_get_contents('/etc/version.lastcommit');
		if (!empty($build_commit)) {
			$firewall_info .= "<br/>Last Commit: " . htmlspecialchars($build_commit);
		}
	}

	if (file_exists('/etc/version.gitsync')) {
		$gitsync = file_get_contents('/etc/version.gitsync');
		if (!empty($gitsync)) {
			$firewall_info .= "<br/>A gitsync was performed at " .
			    date("D M j G:i:s T Y", filemtime('/etc/version.gitsync')) .
			    " to commit " . htmlspecialchars($gitsync);
		}
	}

	file_put_contents("{$output_path}/Product-Info.txt", str_replace("<br/>", "\n", $firewall_info) . "\n");
	return $firewall_info;
}

function status_get_gateway_status() {
	return return_gateways_status_text(true, false);
}

/* Logs */
function status_log_add($name, $logfile, $number = 1000) {
	if (!file_exists($logfile)) {
		status_error_add(gettext('Requested log file is not present') . ": " . $logfile . "\n");
		return;
	}
	if (!is_readable($logfile)) {
		status_error_add(gettext('Requested log file is not readable') . ": " . $logfile . "\n");
		return;
	}
	$descr = "Log-{$name}";
	$tail = '';
	if ($number != "all") {
		$descr .= "-Last {$number} entries";
		$tail = ' | tail -n ' . escapeshellarg($number);
	}
	status_cmd_define($descr, system_log_get_cat() . ' ' . sort_related_log_files($logfile, true, true) . $tail);
}

function status_error_add($error) {
	global $errors, $show_output, $console;
	if ($console) {
		echo $error;
	}
	$errors[] = $error;
}
