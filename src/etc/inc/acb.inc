<?php
/*
 * autoconfigbackup.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
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

require_once("config.inc");
require_once("filter.inc");
require_once("notices.inc");

global $acb_base_url;
$acb_base_url = "https://acb.netgate.com";

global $acb_last_backup_file;
$acb_last_backup_file = "/cf/conf/lastACBentry.txt";

global $acb_force_file;
$acb_force_file = "/tmp/forceacb";

/* Set up time zones for conversion. See #5250 */
global $acb_server_tz;
$acb_server_tz = new DateTimeZone('America/Chicago');

/* Backup reason strings for which ACB will not create remote backup entries */
global $acb_ignore_reasons;
$acb_ignore_reasons = [
	'snort',
	'pfblocker',
	'minicron',
	'merged in config',
	'intermediate config write during package',
	'acbupload.php',
	'execacb.php'
];

/* Check a string to determine if it is a valid device key */
function is_valid_acb_device_key($dk) {
	$dk = trim($dk);
	if (!is_null($dk) &&
	    !empty($dk) &&
	    (strlen($dk) == 64) &&
	    ctype_xdigit($dk)) {
		return true;
	}
	return false;
}

/* Check a string to determine if it is a valid revision identifier */
function is_valid_acb_revision($revision) {
	/* The revision must be a valid date string */
	/* Ensure this returns boolean true/false not a timestamp when true */
	return (strtotime($revision) !== false);
}

/* Check if a reason string should be ignored by ACB. */
function is_acb_ignored_reason($reason) {
	global $acb_ignore_reasons;
	foreach ($acb_ignore_reasons as $term) {
		if (stripos($reason, $term) !== false) {
			return true;
		}
	}
	return false;
}

/* Generate a new random device key */
function acb_generate_device_key() {
	$keyoutput = "";
	$keystatus = "";
	exec("/bin/dd status=none if=/dev/random bs=4096 count=1 | /usr/bin/openssl sha256 | /usr/bin/cut -f2 -d' '", $keyoutput, $keystatus);
	if (($keystatus == 0) &&
	    is_array($keyoutput)) {
		$keyoutput = trim($keyoutput[0]);

		if (is_valid_acb_device_key($keyoutput)) {
			return $keyoutput;
		}
	}
	return null;
}

/* Locate a legacy ACB key for a device, which is derived from the SSH key */
function get_acb_legacy_device_key() {
	if (file_exists('/etc/ssh/ssh_host_ed25519_key.pub')) {
		$pkey =  file_get_contents("/etc/ssh/ssh_host_ed25519_key.pub");
		// Check that the SSH key looks reasonable
		if (substr($pkey, 0, 3) == "ssh") {
			return hash("sha256", $pkey);
		}
	}
	return null;
}

/* Locate and return the ACB device key for this installation. If there is no
 * viable key, generate and store a new key. */
function get_acb_device_key() {
	$config_device_key = config_get_path('system/acb/device_key');

	/* If there is no device key in the configuration, check for a legacy key */
	if (!is_valid_acb_device_key($config_device_key) &&
	    acb_enabled() &&
	    file_exists('/etc/ssh/ssh_host_ed25519_key.pub')) {
		$config_device_key = get_acb_legacy_device_key();
	}

	/* Still no key, so generate a new random key */
	if (!is_valid_acb_device_key($config_device_key)) {
		$config_device_key = acb_generate_device_key();
		/* Only store the key if it's valid */
		if (is_valid_acb_device_key($config_device_key)) {
			config_set_path('system/acb/device_key', $config_device_key);
			write_config(gettext('Generated new randomized AutoConfigBackup device key'));
		}
	}

	/* Still no valid key, something went wrong */
	if (!is_valid_acb_device_key($config_device_key)) {
		log_error(gettext('Unable to locate or generate a valid AutoConfigBackup device key'));
		return null;
	} else {
		return $config_device_key;
	}
}

/* Check whether ACB is enabled */
function acb_enabled() {
	return (config_get_path('system/acb/enable', '') == "yes");
}

/* Check if this device can resolve the ACB hostname via DNS. */
function acb_check_dns() {
	global $acb_base_url;

	if (!resolve_address($acb_base_url)) {
		acb_error_log(sprintf(gettext('Unable to resolve %s'),
			parse_url($acb_base_url, PHP_URL_HOST)));
		return false;
	} else {
		return true;
	}
}

/* Change the time zone to reflect local time of ACB revisions.
 * See Redmine #5250 */
function acb_time_shift($revision, $format = DATE_RFC2822) {
	global $acb_server_tz;
	$budate = new DateTime($revision, $acb_server_tz);
	$mytz = new DateTimeZone(date_default_timezone_get());
	$budate->setTimezone($mytz);
	return htmlspecialchars($budate->format($format));
}

/*
 * Query the ACB server via cURL and return the data
 *
 * Parameters:
 *   endpoint:
 *      Relative URL endpoint on the ACB service, not including the base
 *      hostname.
 *   postfields:
 *      Array containing post fields and their values to submit.
 *   multipart:
 *      True when submitting multi-part form data (e.g. save/upload)
 *
 * Returns:
 *   data:
 *     Content returned from the server
 *   httpcode:
 *     HTTP code returned by the server
 */
function acb_query_service($endpoint, $post_fields, $multipart = false) {
	global $acb_base_url;
	$url = "{$acb_base_url}/{$endpoint}";

	/* Bail if passed invalid data */
	if (empty($endpoint) ||
	    empty($post_fields) ||
	    !is_array($post_fields)) {
		return [null, null, 1];
	}

	/* Add UID */
	$post_fields['uid'] = system_get_uniqueid();

	/* Store this now as it may be lost in the next step. */
	$post_fields_count = count($post_fields);
	if (!$multipart) {
		$post_fields = http_build_query($post_fields);
	}

	$curl_session = curl_init();
	curl_setopt($curl_session, CURLOPT_URL, $url);
	curl_setopt($curl_session, CURLOPT_POST, $post_fields_count);
	curl_setopt($curl_session, CURLOPT_POSTFIELDS, $post_fields);
	curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 1);
	curl_setopt($curl_session, CURLOPT_CONNECTTIMEOUT, 55);
	curl_setopt($curl_session, CURLOPT_TIMEOUT, 30);
	curl_setopt($curl_session, CURLOPT_USERAGENT, g_get('product_label') . '/' . rtrim(file_get_contents("/etc/version")));
	// Proxy
	set_curlproxy($curl_session);

	$data = curl_exec($curl_session);
	$httpcode = curl_getinfo($curl_session, CURLINFO_RESPONSE_CODE);
	$errno = curl_errno($curl_session);

	if ($errno) {
		$fd = fopen("/tmp/acb_debug.txt", "w");
		fwrite($fd, $url . "\n\n");
		fwrite($fd, var_export($post_fields, true));
		fwrite($fd, $data);
		fwrite($fd, curl_error($curl_session));
		fclose($fd);
	} else {
		curl_close($curl_session);
	}
	return [$data, $httpcode, $errno];
}

/* Check if a backup is necessary (has config changed since last upload) */
function is_acb_upload_needed() {
	global $acb_last_backup_file;

	if (file_exists($acb_last_backup_file)) {
		$last_backup_date = trim(file_get_contents($acb_last_backup_file));
	} else {
		$last_backup_date = "";
	}

	return ($last_backup_date <> config_get_path('revision/time'));
}

/* Stage a config backup for uploading which will be picked up later by the
 * acbupload.php cron job which performs the actual upload process.
 */
function acb_backup_stage_upload($manual = false) {
	global $acb_base_url;

	/* Do nothing when booting or when not enabled */
	if (is_platform_booting() ||
	    !acb_enabled()) {
		return;
	}

	/* Define required variables */
	$userkey = get_acb_device_key();
	$hostname = config_get_path('system/hostname') . "." . config_get_path('system/domain');
	$reason = config_get_path('revision/description');
	$manmax = config_get_path('system/acb/numman', '0');
	$encryptpw = config_get_path('system/acb/encryption_password');

	if (is_acb_ignored_reason($reason)) {
		log_error(sprintf(gettext('Skipping staging AutoConfigBackup entry for ignored reason: %s.'), $reason));
		return;
	}

	if (!$encryptpw) {
		if (!file_exists("/cf/conf/autoconfigback.notice")) {
			$notice_text = gettext('The Automatic Configuration Backup Encryption Password is not set. ' .
				'Configure the Encryption Password at Services > AutoConfigBackup > Settings.');
			log_error($notice_text);
			file_notice("AutoConfigBackup", $notice_text, $notice_text, "");
			touch("/cf/conf/autoconfigback.notice");
		}
	} else {
		/* If the configuration has changed, upload to ACB service */
		if (is_acb_upload_needed()) {
			$notice_text = sprintf(gettext('Staging AutoConfigBackup encrypted configuration backup for deferred upload to %s'), $acb_base_url);
			log_error($notice_text);
			update_filter_reload_status($notice_text);

			/* Encrypt config.xml contents */
			$data = file_get_contents("/cf/conf/config.xml");
			$raw_config_sha256_hash = trim(shell_exec("/sbin/sha256 /cf/conf/config.xml | /usr/bin/awk '{ print $4 }'"));
			$data = encrypt_data($data, $encryptpw);
			$tmpname = "/tmp/" . $raw_config_sha256_hash . ".tmp";
			tagfile_reformat($data, $data, "config.xml");
			file_put_contents($tmpname, $data);

			/* Define backup metadata */
			$post_fields = array(
				'reason' => substr(htmlspecialchars($reason), 0, 1024),
				'file' => curl_file_create($tmpname, 'image/jpg', 'config.jpg'),
				'userkey' => htmlspecialchars($userkey),
				'sha256_hash' => $raw_config_sha256_hash,
				'version' => g_get('product_version'),
				'hint' => substr(config_get_path('system/acb/hint'), 0, 255),
				'manmax' => (int)$manmax
			);

			unlink_if_exists($tmpname);

			/* Location to stage backup file pairs */
			$acbuploadpath = g_get('acbbackuppath');

			if (!is_dir($acbuploadpath)) {
				mkdir($acbuploadpath);
			}

			file_put_contents($acbuploadpath . $post_fields['sha256_hash'] . ".form", json_encode($post_fields));
			file_put_contents($acbuploadpath . $post_fields['sha256_hash'] . ".data", $data);
		} else {
			/* Debugging */
			//log_error(gettext('No AutoConfigBackup action required.'));
		}
	}
}

/* Upload all backup entries staged by acb_backup_stage_upload(). */
function acb_backup_upload($basename) {
	global $acb_base_url, $acb_last_backup_file;

	/* Location of staged backup file pairs */
	$acbuploadpath = g_get('acbbackuppath');

	/* If the ACB service cannot be resolved, remove staged backup files
	 * and exit.
	 * The check function logs an error, no need to log an error manually.
	 */
	if (!acb_check_dns()) {
		unlink_if_exists($acbuploadpath . $basename . ".data");
		unlink_if_exists($acbuploadpath . $basename . ".form");
		return;
	}

	/* Read the staged form file containing backup metadata */
	$formdata = file_get_contents($acbuploadpath . $basename . ".form");
	$post_fields = json_decode($formdata, true);

	/* Check backup reason in metadata against ignore list */
	if (is_acb_ignored_reason($post_fields['reason'])) {
		log_error(sprintf(gettext('Skipping staged AutoConfigBackup entry for ignored reason: %s.'), $post_fields['reason']));
		/* Delete the staged backup files */
		unlink_if_exists($acbuploadpath . $basename . ".data");
		unlink_if_exists($acbuploadpath . $basename . ".form");
		return;
	}

	/* Add the encrytped backup data */
	$post_fields['file'] = curl_file_create($acbuploadpath . $basename . ".data", 'image/jpg', 'config.jpg');

	/* Upload encrypted backup entry and its metadata to the ACB service */
	[$data, $httpcode, $errno] = acb_query_service("save", $post_fields, true);

	/* Delete the staged backup files no matter the outcome */
	unlink_if_exists($acbuploadpath . $basename . ".data");
	unlink_if_exists($acbuploadpath . $basename . ".form");

	if (strpos(strval($httpcode), '20') === false) {
		if (empty($data) && $errno) {
			$data = $errno;
		} else {
			$data = "Unknown error";
		}
		acb_error_log($data);
	} else {
		/* Update last ACB backup time */
		$fd = fopen($acb_last_backup_file, "w");
		fwrite($fd, config_get_path('revision/time'));
		fclose($fd);
		$notice_text = sprintf(gettext('Completed AutoConfigBackup encrypted configuration backup upload to %s (success)'), $acb_base_url);
		log_error($notice_text);
		update_filter_reload_status($notice_text);
	}
}

/* Get a specific backup entry from the ACB service */
function acb_backup_get($userkey, $revision) {
	$post_fields = [
		'userkey'  => $userkey,
		'revision' => $revision,
		'version'  => g_get('product_version'),
	];
	return acb_query_service("getbkp", $post_fields);
}

/* Get metadata for a specific backup entry from the list as this
 * metadata is not included when using the getbkp endpoint.
 */
function acb_backup_get_metadata($userkey, $revision) {
	/* Reverse the list since the ACB server getbkp returns last match,
	 * otherwise if two entries have the same revision the metadata will
	 * not match. */
	$backups = array_reverse(acb_backup_list($userkey));
	foreach ($backups as $b) {
		if ($b['time'] == $revision) {
			return $b;
		}
	}
	return [];
}

/* Decrypt the configuration data from an ACB service backup entry. */
function acb_backup_decrypt($data, $password) {
	$errors = [];

	$data_split = explode('++++', $data);
	$sha256 = trim($data_split[0]);
	$encrypted = $data_split[1];

	if (!tagfile_deformat($encrypted, $encrypted, "config.xml")) {
		$errors[] = gettext('The fetched backup entry does not appear to contain an encrypted configuration.');
	}
	$decrypted = decrypt_data($encrypted, $password);
	if (!strstr($decrypted, "pfsense") ||
	    (strlen($decrypted) < 50)) {
		$errors[] = gettext('Could not decrypt the fetched configuration backup entry. Check the encryption key and try again.');
	} else {
		$pos = stripos($decrypted, "</pfsense>");
		$decrypted = substr($decrypted, 0, $pos);
		$decrypted .= "</pfsense>\n";
	}

	return [$decrypted, $encrypted, $sha256, $errors];
}

/* Fetch a list of backups stored for a given device key on the ACB service */
function acb_backup_list($userkey) {
	/* Separator used during client / server communications */
	$oper_sep = "\|\|";

	$backups = [];

	$post_fields = [
		'userkey'  => $userkey,
		'version'  => g_get('product_version')
	];
	/* Fetch backup data for this device key from the ACB service */
	[$data, $httpcode, $errno] = acb_query_service("list", $post_fields);

	/* Loop through fetched data and create a backup list */
	foreach (explode("\n", $data) as $ds) {
		$ds_split = [];
		preg_match("/^(.*?){$oper_sep}(.*){$oper_sep}(.*)/", $ds, $ds_split);

		$tmp_array = [
			'username'  => $ds_split[1],
			'reason'    => $ds_split[2],
			'time'      => $ds_split[3],
			'localtime' => acb_time_shift($ds_split[3])
		];

		if ($ds_split[3] && $ds_split[1]) {
			$backups[] = $tmp_array;
		}
	}

	return $backups;
}

/* Delete a specific backup entry from the ACB service */
function acb_backup_delete($userkey, $revision) {
	global $acb_base_url;

	$savemsg = "";

	$post_fields = [
		'userkey'  => $userkey,
		'revision' => $revision,
		'version'  => g_get('product_version'),
	];
	[$data, $httpcode, $errno] = acb_query_service("rmbkp", $post_fields);

	if ($errno) {
		$savemsg = sprintf(gettext('An error occurred while trying to remove the backup revision from %s'), $acb_base_url);
	} else {
		$savemsg = sprintf(gettext('Backup revision %s has been removed.'), acb_time_shift($revision));
	}
	return $savemsg;
}

/* Save the ACB configuration.
 * Creates or removes ACB crontab entry for scheduled backups when necessary.
 */
function setup_ACB($enable, $hint, $frequency, $minute, $hours, $month, $day, $dow, $numman, $reverse, $pwd) {
	/* Randomize the minutes if not specified */
	if (!isset($minute) || strlen($minute) == 0 || $minute == "0") {
		$minute = rand(1, 59);
	}

	config_set_path('system/acb/enable', $enable);
	config_set_path('system/acb/hint', $hint);
	config_set_path('system/acb/frequency', $frequency);
	config_set_path('system/acb/minute', $minute);
	config_set_path('system/acb/hour', $hours);
	config_set_path('system/acb/month', $month);
	config_set_path('system/acb/day', $day);
	config_set_path('system/acb/dow', $dow);
	config_set_path('system/acb/numman', $numman);
	config_set_path('system/acb/reverse', $reverse);
	if (strlen($pwd) >= 8) {
		config_set_path('system/acb/encryption_password', $pwd);
	}

	/* Install or remove cron job for scheduled periodic backups. */
	install_cron_job("/usr/bin/nice -n20 /usr/local/bin/php /usr/local/sbin/execacb.php",
		($frequency == "cron"),
		$minute,
		is_numeric($hours) ? $hours : "*",
		is_numeric($day) ? $day : "*",
		is_numeric($month) ? $month : "*",
		is_numeric($dow) ? $dow : "*"
	);

	/* Install or remove cron job for uploading staged backups */
	install_cron_job("/usr/bin/nice -n20 /usr/local/bin/php /usr/local/sbin/acbupload.php",
		($enable == "yes"),
		"*");

	write_config("AutoConfigBackup settings updated");

	return config_get_path('system/acb');
}

/* Log ACB errors when necessary. */
function acb_error_log($data) {
	global $acb_base_url;
	$notice_text = sprintf(
		gettext("An error occurred while uploading the encrypted %s configuration backup to %s (%s)"),
		g_get('product_label'),
		$acb_base_url,
		htmlspecialchars($data));
	log_error($notice_text . " - " . $data);
	file_notice("AutoConfigBackup", $notice_text);
	update_filter_reload_status($notice_text);
}

/* Generate a self-contained HTML download link for a device key string. */
function acb_key_download_link($name, $key) {
	$hostname = config_get_path('system/hostname') . "." . config_get_path('system/domain');
	$dltext = gettext('Download This Key');
	$keystring = base64_encode($key . "\n");

	return <<<EOL
<a download="acb_{$hostname}_{$name}_key.txt"
   title="{$dltext}"
   href="data:text/plain;base64,{$keystring}">
   <i class="fa-solid fa-download"></i></a>
EOL;

}
