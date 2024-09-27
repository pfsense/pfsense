<?php
/*
 * config.lib.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2009 Erik Kristensen
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
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

require_once("xmlparse.inc");
require_once('util.inc');
require_once('globals.inc');

/****f* config/encrypted_configxml
 * NAME
 *   encrypted_configxml - Checks to see if config.xml is encrypted and if so, prompts to unlock.
 * INPUTS
 *   None
 * RESULT
 *   Rewrites config.xml without encryption
 ******/
function encrypted_configxml(string $file_name = null) {
	// we can only prompt for password if the PHP script is running in a cli
	if (!is_cli_sapi()) {
		return false;
	}

	if (!isset($file_name)) {
		$file_name = g_get('conf_path') . "/config.xml";
	}

	if (!file_exists($file_name)) {
		return false;
	}

	$configtxt = file_get_contents($file_name);
	if (tagfile_deformat($configtxt, $configtxt, "config.xml")) {
		$fp = fopen('php://stdin', 'r');
		$data = "";
		echo "\n\n*** Encrypted config.xml detected ***\n";
		while ($data == "") {
			echo "\nEnter the password to decrypt config.xml: ";
			$decrypt_password = chop(fgets($fp));
			$data = decrypt_data($configtxt, $decrypt_password);
			if (!strstr($data, "<pfsense>")) {
				$data = "";
			}
			if ($data) {
				$fd = fopen("{$file_name}.tmp", "w");
				fwrite($fd, $data);
				fclose($fd);
				exec("/bin/mv '{$file_name}.tmp' '{$file_name}.decrypted'");
				echo "\n" . gettext("config.xml unlocked.") . "\n";
				fclose($fp);
				return "{$file_name}.decrypted";
			} else {
				echo "\n" . gettext("Invalid password entered. Please try again.") . "\n";
			}
		}
	}

	return false;
}

/****f* config/generate_config_cache
 * NAME
 *   generate_config_cache - Write serialized configuration to cache.
 * INPUTS
 *   $config	- array containing current firewall configuration
 * RESULT
 *   boolean	- true on completion
 ******/
function generate_config_cache($config) {
	global $g, $config_extra;

	$configcache = fopen(g_get('tmp_path') . '/config.cache', "w");
	if (!$configcache) {
		/* Cannot open file for writing, so stop here. */
		return false;
	}
	fwrite($configcache, serialize($config));
	fclose($configcache);

	unset($configcache);
	/* Used for config.extra.xml */
	if (file_exists(g_get('tmp_path') . '/config.extra.cache') && $config_extra) {
		$configcacheextra = fopen(g_get('tmp_path') . '/config.extra.cache', "w");
		fwrite($configcacheextra, serialize($config_extra));
		fclose($configcacheextra);
		unset($configcacheextra);
	}
	return true;
}

function discover_last_backup() {
	global $g;

	$backup_files = glob('/cf/conf/backup/*.xml');
	if (!is_array($backup_files)) {
		return false;
	}
	$backup_files = array_filter($backup_files,'is_file');
	natsort($backup_files);

	// check newest file first
	foreach (array_reverse($backup_files) as $file_name) {
		/* checking multiple backups when detecting invalid configuration
		 * https://redmine.pfsense.org/issues/11748 */
		if (empty(filesize($file_name))) {
			continue;
		}

		// Check for encrypted config.xml
		$backup_decrypted = encrypted_configxml($file_name);
		if (is_string($backup_decrypted)) {
			$file_name = $backup_decrypted;
		}

		$backup_config = parse_xml_config($file_name, g_get('xml_rootobj'));
		if (is_array($backup_config)) {
			return [
				'path' => $file_name,
				'config' => $backup_config
			];
		}
	}

	return false;
}

function restore_backup(string|array $backup) {
	if (is_array($backup)) {
		if (!is_string($backup['path']) || empty($backup['path'])) {
			return false;
		}
		$file = $backup['path'];

		if (is_array($backup['config']) && !empty($backup['config'])) {
			$conf = $backup['config'];
		} else {
			$conf = parse_xml_config($file, g_get('xml_rootobj'));
			if (!is_array($conf)) {
				return false;
			}
		}
	} else {
		$file = $backup;
		if (!file_exists($file)) {
			return false;
		}
		$conf = parse_xml_config($file, g_get('xml_rootobj'));
		if (!is_array($conf)) {
			return false;
		}
	}

	$data = file_get_contents($file);
	// restore RRD
	if ($conf['rrddata']) {
		restore_rrddata($conf);
		$data = clear_tagdata("rrd", $data);
	}
	// restore SSH keys
	if ($conf['sshdata']) {
		restore_sshdata($conf);
		$data = clear_tagdata("ssh", $data);
	}
	// restore additional data
	foreach (g_get('backuppath') as $bk => $path) {
		if (!is_array(array_get_path($conf, "{$bk}/{$bk}data/xmldatafile"))) {
			continue;
		}
		restore_xmldatafile($bk, $conf);
		$data = clear_tagdata($bk, $data);
	}
	file_put_contents($file, $data);

	$lockkey = lock('config', LOCK_EX);
	if (!isset($lockkey)) {
		return false;
	}
	unlink_if_exists(g_get('tmp_path') . '/config.cache');
	copy($file, g_get('cf_conf_path') . '/config.xml');
	unlock($lockkey);

	disable_security_checks();

	return true;
}

/* If the config on disk had rrddata/xmldata tags already, remove that section first.
 * See https://redmine.pfsense.org/issues/8994,
 *     https://redmine.pfsense.org/issues/10508,
 *     https://redmine.pfsense.org/issues/11050 */
function clear_tagdata($tag, $data) {
	$data = preg_replace("/[[:blank:]]*<{$tag}data>.*<\\/{$tag}data>[[:blank:]]*\n*/s", "", $data);
	$data = preg_replace("/[[:blank:]]*<{$tag}data\\/>[[:blank:]]*\n*/", "", $data);

	return $data;
}

function restore_xmldatafile($type='voucher', $conf = false) {
	global $g;

	if (!$conf) {
		$conf = config_get_path('');
	}

	foreach ($conf[$type]["{$type}data"]["xmldatafile"] as $file) {
		$basename = basename($file['filename']);
		$dirname = '';
		if (isset($file['path'])) {
			$dirname = $file['path'];
		} else {
			/* Handle restoring older backups without a path. If
			   multiple paths are given, use the first path with a
			   matching file name. If there are no matching names,
			   default to the first path. */
			$paths = explode(',', trim($g['backuppath'][$type], '{}'));
			$dirname = dirname($paths[array_key_first($paths)]);
			foreach ($paths as $path) {
				if (basename($path) == $basename) {
					$dirname = dirname($path);
					break;
				}
			}
		}

		$xmldata_file = "{$dirname}/{$basename}";
		if (!is_dir($dirname)) {
			safe_mkdir($dirname);
		}
		if (file_put_contents($xmldata_file, gzinflate(base64_decode($file['data']))) === false) {
			log_error(sprintf(gettext("Cannot write %s"), $xmldata_file));
			continue;
		}
	}
}

function restore_rrddata($conf = false) {
	global $g, $rrdtool, $input_errors;

	if (!$conf) {
		$conf = config_get_path('');
	}

	foreach ($conf['rrddata']['rrddatafile'] as $rrd) {
		if ($rrd['xmldata']) {
			$rrd_file = "{$g['vardb_path']}/rrd/" . basename($rrd['filename']);
			$xml_file = preg_replace('/\.rrd$/', ".xml", $rrd_file);
			if (file_put_contents($xml_file, gzinflate(base64_decode($rrd['xmldata']))) === false) {
				log_error(sprintf(gettext("Cannot write %s"), $xml_file));
				continue;
			}
			$output = array();
			$status = null;
			exec("{$rrdtool} restore -f " . escapeshellarg($xml_file) . ' ' . escapeshellarg($rrd_file), $output, $status);
			if ($status) {
				log_error("rrdtool restore -f '{$xml_file}' '{$rrd_file}' failed returning {$status}.");
				continue;
			}
			unlink($xml_file);
		} else if ($rrd['data']) {
			$rrd_file = "{$g['vardb_path']}/rrd/" . basename($rrd['filename']);
			$rrd_fd = fopen($rrd_file, "w");
			if (!$rrd_fd) {
				log_error(sprintf(gettext("Cannot write %s"), $rrd_file));
				continue;
			}
			$data = base64_decode($rrd['data']);
			/* Try to decompress the data. */
			$dcomp = @gzinflate($data);
			if ($dcomp) {
				/* If the decompression worked, write the decompressed data */
				if (fwrite($rrd_fd, $dcomp) === false) {
					log_error(sprintf(gettext("fwrite %s failed"), $rrd_file));
					continue;
				}
			} else {
				/* If the decompression failed, it wasn't compressed, so write raw data */
				if (fwrite($rrd_fd, $data) === false) {
					log_error(sprintf(gettext("fwrite %s failed"), $rrd_file));
					continue;
				}
			}
			if (fclose($rrd_fd) === false) {
				log_error(sprintf(gettext("fclose %s failed"), $rrd_file));
				continue;
			}
		}
	}
}

function restore_sshdata($conf = false) {
	global $sshConfigDir;

	if (!$conf) {
		$conf = config_get_path('');
	}

	$oldmask = umask();
	foreach ($conf["sshdata"]["sshkeyfile"] as $sshkey) {
		$keypath = "{$sshConfigDir}/{$sshkey['filename']}";
		if (strstr($sshkey['filename'], 'pub')) {
			umask(0133);
		} else {
			umask(0177);
		}
		if (file_put_contents($keypath, gzinflate(base64_decode($sshkey['xmldata']))) === false) {
			log_error(sprintf(gettext("Cannot write %s"), $sshkey['filename']));
			continue;
		}
	}
	umask($oldmask);
}

/****f* config/parse_config_bootup
 * NAME
 *   parse_config_bootup - Bootup-specific configuration checks.
 * RESULT
 *   null
 ******/
function parse_config_bootup() {
	if (!config_read_file(true)) {
		die(gettext("Could not find a usable configuration file or it's backup! Exiting...."));
	}
	if ((float)config_get_path('version') > (float)g_get('latest_config')) {
		$product = g_get('product_label');
		echo <<<EOD


*******************************************************************************
* WARNING!                                                                    *
* The current configuration has been created with a newer version of {$product}  *
* than this one! This can lead to serious misbehavior and even security       *
* holes! You are urged to either upgrade to a newer version of {$product} or     *
* revert to the default configuration immediately!                            *
*******************************************************************************


EOD;
		}
	/* make alias table (for faster lookups) */
	alias_make_table();
}

/****f* config/conf_mount_rw
 * NAME
 *   conf_mount_rw - Mount filesystems read/write.
 * RESULT
 *   null
 ******/
/* mount flash card read/write */
function conf_mount_rw() {
	/* Obsoleted. Keep it here until all calls are removed */
	return;
}

/****f* config/conf_mount_ro
 * NAME
 *   conf_mount_ro - Mount filesystems readonly.
 * RESULT
 *   null
 ******/
function conf_mount_ro() {
	/* Obsoleted. Keep it here until all calls are removed */
	return;
}

/****f* config/convert_config
 * NAME
 *   convert_config - Attempt to update config.xml.
 * DESCRIPTION
 *   convert_config() reads the current global configuration
 *   and attempts to convert it to conform to the latest
 *   config.xml version. This allows major formatting changes
 *   to be made with a minimum of breakage.
 * RESULT
 *   null
 ******/
/* convert configuration, if necessary */
function convert_config() {
	global $g;
	$now = date("H:i:s");
	log_error(sprintf(gettext("Start Configuration upgrade at %s, set execution timeout to 15 minutes"), $now));

	/* special case upgrades */
	/* fix every minute crontab bogons entry */
	$cron_config = config_get_path('cron');
	if (is_array($cron_config)) {
		$cron_item_count = count($cron_config['item']);
		for ($x = 0; $x < $cron_item_count; $x++) {
			if (stristr($cron_config['item'][$x]['command'], "rc.update_bogons.sh")) {
				if ($cron_config['item'][$x]['hour'] == "*") {
					$cron_config['item'][$x]['hour'] = "3";
					write_config(gettext("Updated bogon update frequency to 3am"));
					log_error(gettext("Updated bogon update frequency to 3am"));
				}
			}
		}
		config_set_path('cron', $cron_config);
	}

	// Save off config version
	$prev_version = config_get_path('version');

	include_once('auth.inc');
	include_once('upgrade_config.inc');
	if (file_exists("/etc/inc/upgrade_config_custom.inc")) {
		include_once("upgrade_config_custom.inc");
	}

	if (config_get_path('version') == g_get('latest_config')) {
		/* already at latest version */
		additional_config_upgrade();
		return;
	}

	config_init_path('system/already_run_config_upgrade');
	$already_run = config_get_path('system/already_run_config_upgrade');

	/* Loop and run upgrade_VER_to_VER() until we're at current version */
	while (config_get_path('version') < g_get('latest_config')) {
		$cur = config_get_path('version') * 10;
		$next = $cur + 1;
		$migration_function = sprintf('upgrade_%03d_to_%03d', $cur,
		    $next);

		foreach (array("", "_custom") as $suffix) {
			$migration_function .= $suffix;
			if (!function_exists($migration_function)) {
				continue;
			}
			if (isset($already_run[$migration_function])) {
				config_del_path("system/already_run_config_upgrade/{$migration_function}");
			} else {
				$migration_function();
			}
		}
		config_set_path('version', sprintf('%.1f', $next / 10));
		if (is_platform_booting()) {
			echo ".";
		}
	}

	if ($prev_version != config_get_path('version')) {
		$now = date("H:i:s");
		log_error(sprintf(gettext("Ended Configuration upgrade at %s"), $now));

		write_config(sprintf(gettext('Upgraded config version level from %1$s to %2$s'), $prev_version, config_get_path('version')));
	}

	additional_config_upgrade();
}

/****f* config/safe_write_file
 * NAME
 *   safe_write_file - Write a file out atomically
 * DESCRIPTION
 *   safe_write_file() Writes a file out atomically by first writing to a
 *   temporary file of the same name but ending with the pid of the current
 *   process, them renaming the temporary file over the original.
 * INPUTS
 *   $filename - string containing the filename of the file to write
 *   $content - string or array containing the file content to write to file
 *   $force_binary - boolean denoting whether we should force binary
 *   mode writing.
 * RESULT
 *   boolean - true if successful, false if not
 ******/
function safe_write_file($file, $content, $force_binary = false) {
	$tmp_file = $file . "." . getmypid();
	$write_mode = $force_binary ? "wb" : "w";

	$fd = fopen($tmp_file, $write_mode);
	if (!$fd) {
		// Unable to open temporary file for writing
		return false;
	}
	if (is_array($content)) {
		foreach ($content as $line) {
			if (!fwrite($fd, $line . "\n")) {
				// Unable to write to temporary file
				fclose($fd);
				return false;
			}
		}
	} elseif (!fwrite($fd, $content)) {
		// Unable to write to temporary file
		fclose($fd);
		return false;
	}
	fflush($fd);
	fclose($fd);

	if (!rename($tmp_file, $file)) {
		// Unable to move temporary file to original
		@unlink($tmp_file);
		return false;
	}

	return true;
}

/****f* config/write_config
 * NAME
 *   write_config - Backup and write the firewall configuration.
 * DESCRIPTION
 *   write_config() handles backing up the current configuration,
 *   applying changes, and regenerating the configuration cache.
 * INPUTS
 *   $desc	- string containing the a description of configuration changes
 *   $backup	- boolean: do not back up current configuration if false.
 *   $write_config_only	- boolean: do not sync or reload anything; just save the configuration if true.
 * RESULT
 *   null
 ******/
/* save the system configuration */
function write_config($desc="Unknown", $backup = true, $write_config_only = false) {
	global $g;

	// Certain strings may be embedded in the $desc (reason) parameter to trigger certain behavior.
	// If detected, those strings are removed and a variable set.
	$doacb = true;
	$manual_acb = false;
	$rcnt = 0;

	$desc = str_replace("-MaNuAlBaCkUp", "", $desc, $rcnt);
	if ($rcnt > 0) {
		// Manual backups require special processing on the server
		$manual_acb = true;
	}

	$rcnt = 0;
	$desc = str_replace("-NoReMoTeBaCkUp", "", $desc, $rcnt);
	if ($rcnt > 0) {
		// No ACB will be performed if this string is detected
		$doacb = false;
	}

	/*
	* Syncing vouchers happens every minute and sometimes multiple times. We don't
	* want to fill up our db with a lot of the same config so just ignore that case.
	*/
	if((strpos($desc, 'Syncing vouchers') !== false ||
		strpos($desc, 'Captive Portal Voucher database synchronized') !== false) ) {
		$doacb = false;
	}

	if (!empty($_SERVER['REMOTE_ADDR'])) {
		@phpsession_begin();
		if (!empty($_SESSION['Username']) && ($_SESSION['Username'] != "admin")) {
			$user = getUserEntry($_SESSION['Username']);
			$user = $user['item'];
			if (is_array($user) && userHasPrivilege($user, "user-config-readonly")) {
				syslog(LOG_AUTHPRIV, sprintf(gettext("Save config permission denied by the 'User - Config: Deny Config Write' permission for user '%s'."), get_config_user()));
				phpsession_end(true);
				return false;
			}
		}
		if (!isset($argc)) {
			phpsession_end(true);
		}
	}

	if (config_path_enabled('', 'reset_factory_defaults')) {
		/*
		   We have put a default config.xml on disk and are about to reboot
		   or reload it. Do not let any system or package code try to save
		   state to config because that would overwrite the default config
		   with the running config.
		*/
		return false;
	}

	if ($backup) {
		backup_config();
	}

	if ($desc == "Unknown") {
		file_notice("config.xml", gettext(
		    'WARNING: write_config() was called without description'));
	}
	config_set_path('revision', make_config_revision_entry($desc));

	// write the config file
	$write_result = config_write_file();
	if ($write_result !== true) {
		file_notice("config.xml", $write_result . PHP_EOL);
		return -1;
	}

	if (config_get_path('syslog/logconfigchanges') != "disabled") {
		log_error(gettext('Configuration Change: ' . config_get_path('revision/description')));
	}

	cleanup_backupcache(true);

	/* re-read configuration */
	/* NOTE: We assume that the file can be parsed since we wrote it. */
	config_read_file(true);

	if ($write_config_only) {
		return config_get_path('');
	}

	unlink_if_exists("/usr/local/pkg/pf/carp_sync_client.php");

	/* sync carp entries to other firewalls */
	carp_sync_client();

	if (is_dir("/usr/local/pkg/write_config")) {
		/* process packager manager custom rules */
		run_plugins("/usr/local/pkg/write_config/");
	}

	// Try the core AutoConfigBackup system
	$acb_config = config_get_path('system/acb', []);
	if ($acb_config['enable'] == "yes" &&
	    (!isset($acb_config['frequency']) || $acb_config['frequency'] == "every") || file_exists("/tmp/forceacb")) {
	    if ($doacb) {
			require_once("acb.inc");
			upload_config($manual_acb);
		}

		if (file_exists("/tmp/forceacb")) {
			unlink("/tmp/forceacb");
		}
	}

	return config_get_path('');
}

/****f* config/reset_factory_defaults
 * NAME
 *   reset_factory_defaults - Reset the system to its default configuration.
 * RESULT
 *   integer	- indicates completion
 ******/
function reset_factory_defaults($lock = false, $reboot_required = true) {
	global $g;

	/* Remove all additional packages */
	mwexec("/bin/sh /usr/local/sbin/{$g['product_name']}-upgrade " .
	    "-r ALL_PACKAGES -f");

	/*
	   Let write_config know that we are awaiting reload of the current config
	   to factory defaults. Either the system is about to reboot, throwing away
	   the current in-memory config as it shuts down, or the in-memory config
	   is about to be reloaded on-the-fly by parse_config.

	   In both cases, we want to ensure that write_config does not flush the
	   in-memory config back to disk.
	*/
	config_set_path('reset_factory_defaults', true);

	/* create conf directory, if necessary */
	safe_mkdir(g_get('cf_conf_path'));

	if (!$lock) {
		$lockkey = lock('config', LOCK_EX);
	}

	/* clear out /conf */
	$dh = opendir(g_get('conf_path'));
	while ($filename = readdir($dh)) {
		if (($filename != ".") && ($filename != "..") &&
		    (!is_dir(g_get('conf_path') . "/" . $filename))) {
			if ($filename == "enableserial_force")
				continue;
			unlink_if_exists(g_get('conf_path') . "/" . $filename);
		}
	}
	closedir($dh);
	unlink_if_exists(g_get('tmp_path') . "/config.cache");

	/* copy default configuration */
	copy("{$g['conf_default_path']}/config.xml", "{$g['cf_conf_path']}/config.xml");

	if (!$lock) {
		unlock($lockkey);
	}

	disable_security_checks();

	/* call the wizard */
	if ($reboot_required) {
		// If we need a reboot first then touch a different trigger file.
		touch("/conf/trigger_initial_wizard_after_reboot");
	} else {
		touch("/conf/trigger_initial_wizard");
	}
	console_configure();
	return 0;
}

function config_restore(string $path, string $descr): bool {
	if (!file_exists($path) || !backup_config()) {
		return false;
	}

	$lockkey = lock('config', LOCK_EX);
	if (!isset($lockkey)) {
		return false;
	}
	unlink_if_exists(g_get('tmp_path'). '/config.cache');
	$copy_result = copy($path, g_get('cf_conf_path') . '/config.xml');
	unlock($lockkey);

	if ($copy_result) {
		disable_security_checks();
		config_read_file(true);
		write_config(sprintf(gettext('Reverted to "%s"'), $descr), false);
		return true;
	}

	return false;
}

function config_install($conffile) {
	global $g;

	if (!file_exists($conffile)) {
		return 1;
	}

	if (!config_validate("{$conffile}")) {
		return 1;
	}

	if (is_platform_booting()) {
		echo gettext("Installing configuration...") . "\n";
	} else {
		log_error(gettext("Installing configuration ...."));
	}

	$lockkey = lock('config', LOCK_EX);
	if (!isset($lockkey)) {
		return 1;
	}
	unlink_if_exists("{$g['tmp_path']}/config.cache");
	$copy_result = copy($conffile, "{$g['conf_path']}/config.xml");
	unlock($lockkey);

	if ($copy_result) {
		disable_security_checks();
		return 0;
	}

	return 1;
}

/*
 * Disable security checks for DNS rebind and HTTP referrer until next time
 * they pass (or reboot), to aid in preventing accidental lockout when
 * restoring settings like hostname, domain, IP addresses, and settings
 * related to the DNS rebind and HTTP referrer checks.
 * Intended for use when restoring a configuration or directly
 * modifying config.xml without an unconditional reboot.
 */
function disable_security_checks() {
	global $g;
	touch("{$g['tmp_path']}/disable_security_checks");
}

/* Restores security checks. Should be called after all succeed. */
function restore_security_checks() {
	global $g;
	unlink_if_exists("{$g['tmp_path']}/disable_security_checks");
}

/* Returns status of security check temporary disable. */
function security_checks_disabled() {
	global $g;
	return file_exists("{$g['tmp_path']}/disable_security_checks");
}

function config_validate($conffile) {

	global $g, $xmlerr;

	$xml_parser = xml_parser_create();

	if (!($fp = fopen($conffile, "r"))) {
		$xmlerr = gettext("XML error: unable to open file");
		return false;
	}

	while ($data = fread($fp, 4096)) {
		if (!xml_parse($xml_parser, $data, feof($fp))) {
			$xmlerr = sprintf(gettext('%1$s at line %2$d'),
						xml_error_string(xml_get_error_code($xml_parser)),
						xml_get_current_line_number($xml_parser));
			return false;
		}
	}
	xml_parser_free($xml_parser);

	fclose($fp);

	return true;
}

function cleanup_backupcache($lock = false) {
	global $g;
	$i = false;

	$revisions = config_get_path('system/backupcount');
	$revisions = intval(is_numericint($revisions) ? $revisions : g_get('default_config_backup_count'));

	if (!$lock) {
		$lockkey = lock('config');
	}

	$backups = get_backups();
	if ($backups) {
		$baktimes = $backups['versions'];
		unset($backups['versions']);
	} else {
		$backups = array();
		$baktimes = array();
	}
	$newbaks = array();
	$bakfiles = glob(g_get('cf_conf_path') . "/backup/config-*");
	$tocache = array();

	// Check for backups in the directory not represented in the cache.
	foreach ($bakfiles as $backup) {
		$backupsize = filesize($backup);
		if ($backupsize == 0) {
			unlink($backup);
			continue;
		}
		$backupexp = explode('-', $backup);
		$backupexp = explode('.', array_pop($backupexp));
		$tocheck = array_shift($backupexp);
		unset($backupexp);
		if (!in_array($tocheck, $baktimes)) {
			$i = true;
			if (is_platform_booting()) {
				echo ".";
			}
			try {
				$newxml = parse_xml_config($backup, array(g_get('xml_rootobj'), 'pfsense'));
			} catch (Exception $exc) {
				log_error(sprintf(gettext("The backup cache file %s is corrupted. Parser error message: %s"), $backup, $exc->getMessage()));
				$newxml = "-1";
			}

			if ($newxml == "-1") {
				log_error(sprintf(gettext("The backup cache file %s is corrupted. Unlinking."), $backup));
				unlink($backup);
				continue;
			}
			if ($newxml['revision']['description'] == "") {
				$newxml['revision']['description'] = "Unknown";
			}
			if ($newxml['version'] == "") {
				$newxml['version'] = "?";
			}
			$tocache[$tocheck] = array('description' => $newxml['revision']['description'], 'version' => $newxml['version'], 'filesize' => $backupsize);
		}
	}
	foreach ($backups as $checkbak) {
		if (count(preg_grep('/' . $checkbak['time'] . '/i', $bakfiles)) != 0) {
			$newbaks[] = $checkbak;
		} else {
			$i = true;
			if (is_platform_booting()) print " " . $tocheck . "r";
		}
	}
	foreach ($newbaks as $todo) {
		$tocache[$todo['time']] = array('description' => $todo['description'], 'version' => $todo['version'], 'filesize' => $todo['filesize']);
	}
	if (is_int($revisions) and (count($tocache) > $revisions)) {
		$toslice = array_slice(array_keys($tocache), 0, $revisions);
		$newcache = array();
		foreach ($toslice as $sliced) {
			$newcache[$sliced] = $tocache[$sliced];
		}
		foreach ($tocache as $version => $versioninfo) {
			if (!in_array($version, array_keys($newcache))) {
				unlink_if_exists(g_get('conf_path') . '/backup/config-' . $version . '.xml');
			}
		}
		$tocache = $newcache;
	}
	$bakout = fopen(g_get('cf_conf_path') . '/backup/backup.cache', "w");
	fwrite($bakout, serialize($tocache));
	fclose($bakout);

	if (!$lock) {
		unlock($lockkey);
	}
}

function get_backups() {
	global $g;
	if (file_exists("{$g['cf_conf_path']}/backup/backup.cache")) {
		$confvers = unserialize_data(file_get_contents("{$g['cf_conf_path']}/backup/backup.cache"), []);
		$bakvers = array_keys($confvers);
		$toreturn = array();
		sort($bakvers);
		foreach (array_reverse($bakvers) as $bakver) {
			$toreturn[] = array('time' => $bakver, 'description' => $confvers[$bakver]['description'], 'version' => $confvers[$bakver]['version'], 'filesize' => $confvers[$bakver]['filesize']);
		}
	} else {
		return false;
	}
	$toreturn['versions'] = $bakvers;
	return $toreturn;
}

function backup_config(): bool
{
	$backup_dir = g_get('cf_conf_path') . '/backup';

	/* Create backup directory if needed */
	safe_mkdir($backup_dir);

	/* Generate config backup file name */
	$backup_time = config_get_path('revision/time', 0);
	$backup_file = $backup_dir . '/config-' . $backup_time . '.xml';

	/* Handle backup cache */
	$bakdesc = config_get_path('revision/description', 'Unknown');
	$bakver = config_get_path('version', "?");
	if (file_exists(g_get('cf_conf_path') . '/backup/backup.cache')) {
		$backupcache = unserialize_data(file_get_contents(g_get('cf_conf_path') . '/backup/backup.cache'), []);
	} else {
		$backupcache = array();
	}
	$backupcache[$backup_time] = array('description' => $bakdesc, 'version' => $bakver, 'filesize' => filesize($backup_file));
	$bakout = fopen(g_get('cf_conf_path') . '/backup/backup.cache', "w");
	fwrite($bakout, serialize($backupcache));
	fclose($bakout);

	/* Do the backup */
	return copy(g_get('cf_conf_path') . '/config.xml', $backup_file);
}

function backup_info($backup_info, $number) {
	if ($backup_info['time'] != 0) {
		$date = date(gettext("n/j/y H:i:s"), $backup_info['time']);
	} else {
		$date = gettext("Unknown");
	}

	list($page, $reason) = explode(": ", $backup_info['description'], 2);
	if (empty($reason)) {
		$reason = $page;
		$page = gettext("Unknown Page");
	}

	$backup_info = sprintf("%02d", $number) . ". {$date}\tv{$backup_info['version']}\t{$page}\n";
	if ($reason) {
		$backup_info .= "    {$reason}\n";
	}
	return $backup_info;
}

function set_device_perms() {
	$devices = array(
		'pf' => array(
			'user' => 'root',
			'group' => 'proxy',
			'mode' => 0660),
		);

	foreach ($devices as $name => $attr) {
		$path = "/dev/{$name}";
		if (file_exists($path)) {
			chown($path, $attr['user']);
			chgrp($path, $attr['group']);
			chmod($path, $attr['mode']);
		}
	}
}

function get_config_user() {
	if (empty($_SESSION["Username"])) {
		$username = getenv("USER");
		if (empty($conuser) || $conuser == "root") {
			$username = "(system)";
		}
	} else {
		$username = $_SESSION["Username"];
	}

	if (!empty($_SERVER['REMOTE_ADDR'])) {
		$username .= '@' . get_user_remote_address() . get_user_remote_authsource();
	}

	return $username;
}

function make_config_revision_entry($desc = null, $override_user = null) {
	if (empty($override_user)) {
		$username = get_config_user();
	} else {
		$username = $override_user;
	}

	$revision = array();

	/* make sure the clock settings are plausible */
	if (time() > mktime(0, 0, 0, 9, 1, 2004)) {
		$revision['time'] = time();
	}

	/* Log the running script so it's not entirely unlogged what changed */
	if ($desc == "Unknown") {
		$desc = sprintf(gettext("%s made unknown change"), $_SERVER['SCRIPT_NAME']);
	}
	if (!empty($desc)) {
		$revision['description'] = "{$username}: " . $desc;
	}
	$revision['username'] = $username;
	return $revision;
}

function pfSense_clear_globals() {
	global $config, $g, $FilterIfList, $GatewaysList, $filterdns, $aliases, $aliastable;

	$error = error_get_last();

	// Errors generated by user code (diag_commands.php) are identified by path and not added to notices
	$is_user_code = preg_match('|^' . preg_quote(g_get('tmp_path_user_code')) . '/[^/]{1,16}$|', $error['file']);

	if (!is_null($error)) {
		if (in_array($error['type'], array(E_ERROR, E_COMPILE_ERROR, E_CORE_ERROR, E_RECOVERABLE_ERROR))) {
			$errortype = "error";
		} elseif ($error['type'] != E_NOTICE) {
			$errortype = "warning";
		}
		$errorstr = "PHP " . strtoupper($errortype) . ": " .
				"Type: {$error['type']}, " .
				"File: {$error['file']}, " .
				"Line: {$error['line']}, " .
				"Message: {$error['message']}";

		if (!$is_user_code) {
			if (($errortype == "error") ||
			    (config_path_enabled('system','developerspew') &&
			    ($errortype = "warning"))) {
				print('<pre style="white-space: pre-wrap;">' . htmlentities($errorstr) . '</pre>');
				log_error($errorstr);
				file_notice("php{$errortype}", $errorstr, 'PHP {$errortype}s');
			}
		} else {
			print(str_replace(',', "\n", $errorstr));
		}
	}

	if (isset($FilterIfList)) {
		unset($FilterIfList);
	}

	if (isset($GatewaysList)) {
		unset($GatewaysList);
	}

	/* Used for the hostname dns resolver */
	if (isset($filterdns)) {
		unset($filterdns);
	}

	/* Used for aliases and interface macros */
	if (isset($aliases)) {
		unset($aliases);
	}
	if (isset($aliastable)) {
		unset($aliastable);
	}

	unset($config);
}

/**
 * Initialize the leaf node as an array. Any scalars in the path, including the
 * leaf node, are overridden with an array.
 * 
 * @param string $path Path with '/' separators
 */
function config_init_path(string $path) {
	// safety check in case $path was called with null keys
	if (str_ends_with(trim($path), '/') || str_contains($path, '//')) {
		log_error(gettext('Warning: invalid config path') . " \"{$path}\"");
		return false;
	}
	global $config;
	return array_init_path($config, $path);
}

/**
 * Return a value specified by path in the config, if it exists.
 * 
 * @param string $path Path with '/' separators
 * @param mixed $default Value to return if the path is not found
 * 
 * @return mixed Value at path or $default if the path does not exist or if the
 *               path keys an empty string and $default is non-null
 */
function config_get_path(string $path, $default = null) {
	// safety check in case $path was called with null keys
	if (str_ends_with(trim($path), '/') || str_contains($path, '//')) {
		log_error(gettext('Warning: invalid config path') . " \"{$path}\"");
		return $default;
	}
	global $config;
	return is_array($config) ? array_get_path($config, $path, $default) : $default;
}

/**
 * Set a value by path in the config, creating arrays for intermediary keys as
 * necessary. If the path cannot be reached because an intermediary exists but
 * is not empty or an array, return $default.
 * 
 * @param string $path Path with '/' separators
 * @param mixed $val Value to set
 * @param mixed $default Value to return if the path is not found
 * 
 * @return mixed $val or $default if the path prefix does not exist
 */
function config_set_path(string $path, $value, $default = null) {
	// safety check in case $path was called with null keys
	if (str_contains($path, '//')) {
		log_error(gettext('Warning: invalid config path') . " \"{$path}\"");
		return $default;
	}
	global $config;

	if ($path == '' && !is_array($config)) {
		// initialize $config when it's being completely replaced
		$config = [];
	} elseif ($path == '/') {
		// prevent unintentionally treating $config as a list array
		return ($default);
	}

	return is_array($config) ? array_set_path($config, $path, $value, $default) : $default;
}

/**
 * Determine whether a path in the config has a non-null value keyed by
 * $enable_key. Some parts of the config historically identify services as
 * enabled by having a key to a non-null value named 'enable', and checking it
 * with isset(). This can be counter-intuitive as isset() will return true if
 * the array element is any non-null value that evaluates to false.
 * 
 * @param string $path Path with '/' separators
 * @param string $enable_key Optional alternative key value for the enable key
 * 
 * @return mixed|bool true if $enable_key exists in the array at $path, and has
 *                    a non-null value, otherwise false; or $default if the
 *                    path prefix does not exist.
 */
function config_path_enabled(string $path, $enable_key = "enable", $default = false) {
	// safety check in case $path was called with null keys
	if (str_ends_with(trim($path), '/') || str_contains($path, '//')) {
		log_error(gettext('Warning: invalid config path') . " \"{$path}\"");
		return $default;
	}
	global $config;
	return is_array($config) ? array_path_enabled($config, $path, $enable_key, $default) : $default;
}

/**
 * Remove a key from the config by path.
 * 
 * @param string $path Path with '/' separators
 * 
 * @return mixed|array Copy of the removed value or null; or $default if the
 *                     path prefix does not exist.
 */
function config_del_path(string $path, $default = null) {
	// safety check in case $path was called with null keys
	if (str_ends_with(trim($path), '/') || str_contains($path, '//')) {
		log_error(gettext('Warning: invalid config path') . " \"{$path}\"");
		return $default;
	}
	global $config;
	return is_array($config) ? array_del_path($config, $path, $default) : $default;
}

/**
 * Writes the in-memory configuration to config.xml.
 *
 * @return bool|string True if the config.xml file was successfully
 *                     written; otherwise an error string.
 */
function config_write_file() {
		// generate configuration XML
		$tmp_config = config_get_path('');
		if (!is_array($tmp_config)) {
			return gettext("Config contents could not be saved. Config is empty!");
		}

		// write configuration XML
		$lockkey = lock('config', LOCK_EX);
		if (!isset($lockkey)) {
			return gettext("Config contents could not be saved. Could not create temporary file!");
		}
		$result = safe_write_file(g_get('cf_conf_path') . "/config.xml", dump_xml_config($tmp_config, g_get('xml_rootobj')));
		unlock($lockkey);

		if (!$result) {
			return sprintf(gettext('Unable to open %1$s for writing.'), g_get('cf_conf_path') . '/config.xml');
		}

		// success
		return true;
}

/**
 * Reloads the in-memory configuration with config.xml.
 *
 * @param bool $use_backup If True, try to restore config backups
 *                         when a valid config.xml is not available.
 * @param bool $use_cache If True, try to use the config cache file.
 *
 * @return bool True if the configuration was successfully reloaded.
 */
function config_read_file(bool $use_backup = false, bool $use_cache = false) {
	/* We leave calls to file_notice() for the end before returning.
	 * This mitigates the potential for PHP errors to interfere with
	 * the config recovery. */
	$config_read = function () {
		$update_cache = false;
		$parsed_config = @file_get_contents(g_get('tmp_path') . '/config.cache');
		if (is_string($parsed_config)) {
			$parsed_config = unserialize($parsed_config);
		}
		if (!is_array($parsed_config)) {
			$parsed_config = parse_xml_config((g_get('conf_path') . '/config.xml'), [g_get('xml_rootobj')]);
			$update_cache = true;
		}
	
		if (is_array($parsed_config) && !empty($parsed_config) &&
			is_array(config_set_path('', $parsed_config))) {
			if ($update_cache) {
				generate_config_cache($parsed_config);
			}
			return true;
		}
	
		return false;
	};

	if (!$use_cache) {
		@unlink(g_get('tmp_path') . '/config.cache');
	}
	global $config, $config_parsed;
	$config_parsed = false;

	$config_parsed = $config_read();

	if ($config_parsed || !$use_backup) {
		if (!$config_parsed) {
			$log_message = gettext("A valid config file could not be found.");
			log_error($log_message);
			file_notice("config.xml", $log_message, "pfSenseConfigurator", "", 1, true);
		}
		return $config_parsed;
	}

	log_error(gettext("A valid config file backup could not be found."));
	if (file_exists(g_get('conf_path') . '/config.xml')) {
		// adapted from write_config()
		copy((g_get('conf_path') . '/config.xml'), (g_get('conf_path') . '/config.xml.bad'));
		log_error(gettext("The failed configuration file has been saved to ") . (g_get('conf_path') . '/config.xml.bad'));
	}

	if (is_platform_booting()) {
		// adapted from parse_config() and parse_config_bootup()
		if (is_cli_sapi()) {
			echo ".";
		}

		// adapted from write_config()
		$log_message = "\n\n************** " . gettext("WARNING") . " **************";
		$log_message .= "\n\n " . gettext("Configuration could not be validated. A previous configuration was restored.");
		$log_message .= "\n\n " . gettext("The failed configuration file has been saved to ") . (g_get('conf_path') . '/config.xml.bad') . "\n\n";
		echo $log_message;
	}

	$last_backup = discover_last_backup();
	if (!is_array($last_backup)) {
		$log_message = gettext("A valid config file backup could not be found.");
		log_error($log_message);

		// adapted from parse_config() - alternatively it could return false
		die($log_message);
	}

	if (restore_backup($last_backup)) {
		// update in-memory config and generate a cache file
		$config_parsed = $config_read();
		if ($config_parsed) {
			$log_message = sprintf(gettext('Restored "%1$s" because "%2$s" is invalid or does not exist.'),
			    $last_backup['path'], (g_get('cf_conf_path') . '/config.xml')
			);
			$log_message .= ' ' . gettext('Currently running PHP scripts may encounter errors.');
			log_error($log_message);

			// adapted from parse_config()
			file_notice("config.xml", $log_message, "pfSenseConfigurator", "", 1, true);
		} else {
			$log_message = gettext("A valid config file could not be recovered.");
			$log_message .= ' ' . gettext('Currently running PHP scripts may encounter errors.');
			log_error($log_message);

			// adapted from parse_config()
			die($log_message);
		}
	}

	return $config_parsed;
}

/**
 * Reloads the in-memory configuration with config.xml.
 *
 * @param bool $parse If False, use the config cache when available.
 *
 * @return mixed Config array.
 *
 * @deprecated Retained for backwards compatibility with versions <= 24.03.
 *
 * @see config_read_file() Supports updated recovery code.
 */
function parse_config($parse = false) {
	config_read_file(!$parse);
	return config_get_path('');
}

/**
 * Initialize the config path.
 * 
 * @param array $keys The path as an array of keys.
 * 
 * @deprecated Retained for backwards compatibility with versions <= 24.03.
 * 
 * @see config_init_path() Uses a string path.
 */
function init_config_arr($keys) {
	config_init_path(implode('/', $keys));
}

register_shutdown_function('pfSense_clear_globals');
