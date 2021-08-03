<?php
/*
 * autoconfigbackup.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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

require_once("filter.inc");
require_once("notices.inc");

// If there is no ssh key in the system to identify this firewall, generate a pair now
function get_device_key() {
	if (!file_exists("/etc/ssh/ssh_host_ed25519_key.pub")) {
		// Remove any possible matching key so we don't get an overwrite prompt
		if (file_exists('/etc/ssh/ssh_host_ed25519_key')) {
			unlink('/etc/ssh/ssh_host_ed25519_key');
		}

		// Generate a new key pair
		exec("/usr/bin/nice -n20 /usr/bin/ssh-keygen -t ed25519 -b 4096 -N '' -f /etc/ssh/ssh_host_ed25519_key");
		sleep(2);

		$cnt = 0;
		while(!file_exists("/etc/ssh/ssh_host_ed25519_key.pub")) {
			sleep(2);
			if (++$cnt > 10) {
				return "";
			}
		}
	}

	$pkey =  file_get_contents("/etc/ssh/ssh_host_ed25519_key.pub");

	// Check that the key looks reasonable
	if (substr($pkey, 0, 3) != "ssh") {
		return "";
	}

	return hash("sha256", $pkey);
}

$origkey = get_device_key();

if (isset($_REQUEST['userkey']) && !empty($_REQUEST['userkey'])) {
	$userkey = htmlentities($_REQUEST['userkey']);
} else {
	$userkey = get_device_key();
}

$uniqueID = system_get_uniqueid();

/* Check whether ACB is enabled */
function acb_enabled() {
	global $config;
	$acb_enabled = false;

	if (is_array($config['system']['acb'])) {
		if ($config['system']['acb']['enable'] == "yes") {
			$acb_enabled = true;
		}
	}

	return $acb_enabled;
}

// Ensures patches match
function acb_custom_php_validation_command($post, &$input_errors) {
	global $_POST, $savemsg, $config;

	// Do nothing when ACB is disabled in configuration
	// This also makes it possible to delete the credentials from config.xml
	if (!acb_enabled()) {
		// We do not need to store this value.
		unset($_POST['testconnection']);
		return;
	}

	if (!$post['crypto_password'] or !$post['crypto_password2']) {
		$input_errors[] = "The encryption password is required.";
	}

	if ($post['crypto_password'] <> $post['crypto_password2']) {
		$input_errors[] = "Sorry, the entered encryption passwords do not match.";
	}

	if ($post['testconnection']) {
		$status = test_connection($post);
		if ($status) {
			$savemsg = "Connection to the ACB server was tested with no errors.";
		}
	}

	// We do not need to store this value.
	unset($_POST['testconnection']);
}

function acb_custom_php_resync_config_command() {
	// Do nothing when ACB is disabled in configuration
	if (!acb_enabled()) {
		return;
	}

	unlink_if_exists("/cf/conf/lastpfSbackup.txt");

	if (!function_exists("filter_configure")) {
		require_once("filter.inc");
	}

	filter_configure();

	if ($savemsg) {
		$savemsg .= "<br/>";
	}

	$savemsg .= "A configuration backup has been queued.";
}

function test_connection($post) {
	global $savemsg, $config, $g;

	// Do nothing when booting or when not enabled
	if (platform_booting() || !acb_enabled()) {
		return;
	}

	// Separator used during client / server communications
	$oper_sep = "\|\|";

	// Encryption password
	$decrypt_password = $post['crypto_password'];

	// Defined username. Username must be sent lowercase. See Redmine #7127 and Netgate Redmine #163
	$username = strtolower($post['username']);

	// Defined password
	$password = $post['password'];

	// Set hostname
	$hostname = $config['system']['hostname'] . "." . $config['system']['domain'];

	// URL to restore.php
	$get_url = "https://acb.netgate.com/getbkp";

	// Populate available backups
	$curl_session = curl_init();
	curl_setopt($curl_session, CURLOPT_URL, $get_url);
	curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 1);
	curl_setopt($curl_session, CURLOPT_POST, 1);
	curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl_session, CURLOPT_CONNECTTIMEOUT, 55);
	curl_setopt($curl_session, CURLOPT_TIMEOUT, 30);
	curl_setopt($curl_session, CURLOPT_USERAGENT, $g['product_label'] . '/' . rtrim(file_get_contents("/etc/version")));
	// Proxy
	set_curlproxy($curl_session);

	curl_setopt($curl_session, CURLOPT_POSTFIELDS, "action=showbackups&hostname={$hostname}");
	$data = curl_exec($curl_session);

	if (curl_errno($curl_session)) {
		return("An error occurred " . curl_error($curl_session));
	} else {
		curl_close($curl_session);
	}

	return;
}

function upload_config($manual = false) {
	global $config, $g, $input_errors, $userkey, $uniqueID;

	if (empty($userkey)) {
		$userkey = get_device_key();
	}

	if (empty($uniqueID)) {
		$uniqueID = system_get_uniqueid();
	}

	// Do nothing when booting or when not enabled
	if (platform_booting() || !acb_enabled()) {
		return;
	}

	/*
	 * pfSense upload config to acb.netgate.com script
	 * This file plugs into config.inc (/usr/local/pkg/parse_config)
	 * and runs every time the running firewall filter changes.
	 *
	 */

	if (file_exists("/tmp/acb_nooverwrite")) {
		unlink("/tmp/acb_nooverwrite");
		$nooverwrite = "true";
	} else {
		$nooverwrite = "false";
	}

	// Define some needed variables
	if (file_exists("/cf/conf/lastpfSbackup.txt")) {
		$last_backup_date = str_replace("\n", "", file_get_contents("/cf/conf/lastpfSbackup.txt"));
	} else {
		$last_backup_date = "";
	}

	$last_config_change = $config['revision']['time'];
	$hostname = $config['system']['hostname'] . "." . $config['system']['domain'];
	$reason	= $config['revision']['description'];

	if ($manual && array_key_exists('numman', $config['system']['acb'])) {
		$manmax = $config['system']['acb']['numman'];
	} else {
		$manmax = "0";
	}

	$encryptpw = $config['system']['acb']['encryption_password'];

	// Define upload_url, must be present after other variable definitions due to username, password
	$upload_url = "https://acb.netgate.com/save";

	if (!$encryptpw) {
		if (!file_exists("/cf/conf/autoconfigback.notice")) {
			$notice_text = "The encryption password is not set for Automatic Configuration Backup.";
			$notice_text .= " Please correct this in Services -> AutoConfigBackup -> Settings.";
			log_error($notice_text);
			file_notice("AutoConfigBackup", $notice_text, $notice_text, "");
			touch("/cf/conf/autoconfigback.notice");
		}
	} else {
		/* If configuration has changed, upload to pfS */
		if ($last_backup_date <> $last_config_change) {

			$notice_text = "Beginning configuration backup to " . $upload_url;
			log_error($notice_text);
			update_filter_reload_status($notice_text);

			// Encrypt config.xml
			$data = file_get_contents("/cf/conf/config.xml");
			$raw_config_sha256_hash = trim(shell_exec("/sbin/sha256 /cf/conf/config.xml | /usr/bin/awk '{ print $4 }'"));
			$data = encrypt_data($data, $encryptpw);
			$tmpname = "/tmp/" . $raw_config_sha256_hash . ".tmp";
			tagfile_reformat($data, $data, "config.xml");
			file_put_contents($tmpname, $data);

			$post_fields = array(
				'reason' => htmlspecialchars($reason),
				'uid' => $uniqueID,
				'file' => curl_file_create($tmpname, 'image/jpg', 'config.jpg'),
				'userkey' => htmlspecialchars($userkey),
				'sha256_hash' => $raw_config_sha256_hash,
				'version' => $g['product_version'],
				'hint' => $config['system']['acb']['hint'],
				'manmax' => $manmax
			);

			if (!is_dir($g['acbbackuppath'])) {
				mkdir($g['acbbackuppath']);
			}

			file_put_contents($g['acbbackuppath'] . $post_fields['sha256_hash'] . ".form", json_encode($post_fields));
			file_put_contents($g['acbbackuppath'] . $post_fields['sha256_hash'] . ".data", $data);

			/*
			This functionality is now provided by a cron job /usr/local/sbin/acbupload.php run once per minute when ACB is enabled.
			Commented block can be removed after testing.

			// Check configuration into the ESF repo
			$curl_session = curl_init();

			curl_setopt($curl_session, CURLOPT_URL, "https://acb.netgate.com/save");
			curl_setopt($curl_session, CURLOPT_POST, count($post_fields));
			curl_setopt($curl_session, CURLOPT_POSTFIELDS, $post_fields);
			curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 1);
			curl_setopt($curl_session, CURLOPT_CONNECTTIMEOUT, 55);
			curl_setopt($curl_session, CURLOPT_TIMEOUT, 30);
			curl_setopt($curl_session, CURLOPT_USERAGENT, $g['product_label'] . '/' . rtrim(file_get_contents("/etc/version")));
			// Proxy
			set_curlproxy($curl_session);

			$data = curl_exec($curl_session);

			unlink_if_exists($tmpname);

			if (curl_errno($curl_session)) {
				$fd = fopen("/tmp/backupdebug.txt", "w");
				fwrite($fd, $upload_url . "" . $fields_string . "\n\n");
				fwrite($fd, $data);
				fwrite($fd, curl_error($curl_session));
				fclose($fd);
			} else {
				curl_close($curl_session);
			}

			if (strpos($data, "500") != false) {
				$notice_text = sprintf(gettext(
				    "An error occurred while uploading your %s configuration to "), $g['product_label']) .
				    $upload_url . " (" . htmlspecialchars($data) . ")";
				log_error($notice_text . " - " . $data);
				file_notice("AutoConfigBackup", $notice_text);
				update_filter_reload_status($notice_text);
				$input_errors["acb_upload"] = $notice_text;
			} else {
				// Update last pfS backup time
				$fd = fopen("/cf/conf/lastpfSbackup.txt", "w");
				fwrite($fd, $config['revision']['time']);
				fclose($fd);
				$notice_text = "End of configuration backup to " . $upload_url . " (success).";
				log_error($notice_text);
				update_filter_reload_status($notice_text);
			}
*/
		} else {
			// Debugging
			//log_error("No https://acb.netgate.com backup required.");
		}
	}
}

// Save the updated ACB configuration
// Create a crontab entry for scheduled backups
// if frequency == "cron", a new crontab entry is created, otherwise any existing
// ACB entry is removed
function setup_ACB($enable, $hint, $frequency, $minute, $hours, $month, $day, $dow, $numman, $pwd) {
	global $config;

	// Randomize the minutes if not specified
	if (!isset($minute) || strlen($minute) == 0 || minute == "0") {
		$minute = rand(1, 59);
	}

	$config['system']['acb']['enable'] = $enable;
	$config['system']['acb']['hint'] = $hint;
	$config['system']['acb']['frequency'] = $frequency;
	$config['system']['acb']['minute'] = $minute;
	$config['system']['acb']['hour'] = $hours;
	$config['system']['acb']['month'] = $month;
	$config['system']['acb']['day'] = $day;
	$config['system']['acb']['dow'] = $dow;
	$config['system']['acb']['numman'] = $numman;
	if (strlen($pwd) >= 8) {
		$config['system']['acb']['encryption_password'] = $pwd;
	}

	install_cron_job("/usr/bin/nice -n20 /usr/local/bin/php /usr/local/sbin/execacb.php", $frequency == "cron",
	   $minute,
	   is_numeric($hours) ? $hours : "*",
	   is_numeric($day) ? $day : "*",
	   is_numeric($month) ? $month : "*",
	   is_numeric($dow) ? $dow : "*"
	);

	// Install cron job 
	install_cron_job("/usr/bin/nice -n20 /usr/local/bin/php /usr/local/sbin/acbupload.php", $enable == "yes", "*");

	write_config("AutoConfigBackup settings updated");

	return($config['system']['acb']);
}
