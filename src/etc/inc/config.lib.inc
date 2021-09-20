<?php
/*
 * config.lib.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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

/****f* config/encrypted_configxml
 * NAME
 *   encrypted_configxml - Checks to see if config.xml is encrypted and if so, prompts to unlock.
 * INPUTS
 *   None
 * RESULT
 *   $config 	- rewrites config.xml without encryption
 ******/
function encrypted_configxml() {
	global $g, $config;

	if (!file_exists($g['conf_path'] . "/config.xml")) {
		return;
	}

	if (!platform_booting()) {
		return;
	}

	$configtxt = file_get_contents($g['conf_path'] . "/config.xml");
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
				$fd = fopen($g['conf_path'] . "/config.xml.tmp", "w");
				fwrite($fd, $data);
				fclose($fd);
				exec("/bin/mv {$g['conf_path']}/config.xml.tmp {$g['conf_path']}/config.xml");
				echo "\n" . gettext("Config.xml unlocked.") . "\n";
				fclose($fp);
				//pfSense_fsync("{$g['conf_path']}/config.xml");
			} else {
				echo "\n" . gettext("Invalid password entered.  Please try again.") . "\n";
			}
		}
	}
}

/****f* config/parse_config
 * NAME
 *   parse_config - Read in config.cache or config.xml if needed and return $config array
 * INPUTS
 *   $parse       - boolean to force parse_config() to read config.xml and generate config.cache
 * RESULT
 *   $config      - array containing all configuration variables
 ******/
function parse_config($parse = false) {
	global $g, $config_parsed, $config_extra;

	$lockkey = lock('config');
	$config_parsed = false;

	if (!file_exists("{$g['conf_path']}/config.xml") || filesize("{$g['conf_path']}/config.xml") == 0) {
		$last_backup = discover_last_backup();
		if ($last_backup) {
			log_error(gettext("No config.xml found, attempting last known config restore."));
			file_notice("config.xml", gettext("No config.xml found, attempting last known config restore."), "pfSenseConfigurator", "");
			restore_backup("{$g['conf_path']}/backup/{$last_backup}");
		} else {
			unlock($lockkey);
			die(gettext("Config.xml is corrupted and is 0 bytes.  Could not restore a previous backup."));
		}
	}

	if (platform_booting(true)) {
		echo ".";
	}

	// Check for encrypted config.xml
	encrypted_configxml();

	if (!$parse) {
		if (file_exists($g['tmp_path'] . '/config.cache')) {
			$config = unserialize(file_get_contents($g['tmp_path'] . '/config.cache'));
			if (!is_array($config)) {
				$parse = true;
			}
		} else {
			$parse = true;
		}
	}
	if ($parse == true) {
		if (!file_exists($g['conf_path'] . "/config.xml")) {
			if (platform_booting(true)) {
				echo ".";
			}
			log_error("No config.xml found, attempting last known config restore.");
			file_notice("config.xml", "No config.xml found, attempting last known config restore.", "pfSenseConfigurator", "");
			$last_backup = discover_last_backup();
			if ($last_backup) {
				restore_backup("/cf/conf/backup/{$last_backup}");
			} else {
				log_error(gettext("Could not restore config.xml."));
				unlock($lockkey);
				die(gettext("Config.xml is corrupted and is 0 bytes.  Could not restore a previous backup."));
			}
		}
		$config = parse_xml_config($g['conf_path'] . '/config.xml', array($g['xml_rootobj'], 'pfsense'));
		if ($config == -1) {
			$last_backup = discover_last_backup();
			if ($last_backup) {
				restore_backup("/cf/conf/backup/{$last_backup}");
			} else {
				log_error(gettext("Could not restore config.xml."));
				unlock($lockkey);
				die("Config.xml is corrupted and is 0 bytes.  Could not restore a previous backup.");
			}
		}
		generate_config_cache($config);
	}

	if (platform_booting(true)) {
		echo ".";
	}

	$config_parsed = true;
	unlock($lockkey);

	alias_make_table();

	return $config;
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

	$configcache = fopen($g['tmp_path'] . '/config.cache', "w");
	fwrite($configcache, serialize($config));
	fclose($configcache);
	//pfSense_fsync("{$g['tmp_path']}/config.cache");

	unset($configcache);
	/* Used for config.extra.xml */
	if (file_exists($g['tmp_path'] . '/config.extra.cache') && $config_extra) {
		$configcacheextra = fopen($g['tmp_path'] . '/config.extra.cache', "w");
		fwrite($configcacheextra, serialize($config_extra));
		fclose($configcacheextra);
		//pfSense_fsync("{$g['tmp_path']}/config.extra.cache");
		unset($configcacheextra);
	}
}

function discover_last_backup() {
	global $g;

	$backups = glob('/cf/conf/backup/*.xml');
	foreach (array_reverse($backups) as $backup) {
		/* checking multiple backups when detecting invalid configuration
		 * https://redmine.pfsense.org/issues/11748 */
		if (filesize($backup) != 0) {
			$testconfig = parse_xml_config($backup, $g['xml_rootobj']);
			if ($testconfig != -1) {
				return basename($backup);
			}
		} 
	}

	return false;
}

function restore_backup($file) {
	global $g;

	if (file_exists($file)) {
		/* restore rrddata/xmldata and clear appropriate data,
		 * see https://redmine.pfsense.org/issues/11050 */
		$data = file_get_contents("$file");
		$conf = parse_xml_config("$file", $g['xml_rootobj']);
		if ($conf['rrddata']) {
			restore_rrddata($conf);
			$data = clear_tagdata("rrd", $data);
		}
		foreach ($g['backuppath'] as $bk => $path) {
			if (!empty($conf[$bk][$bk.'data'])) {
				restore_xmldatafile($bk, $conf);
				$data = clear_tagdata($bk, $data);
			}
		}
		file_put_contents($file, $data);
		unlink_if_exists("{$g['tmp_path']}/config.cache");
		copy("$file", "/cf/conf/config.xml");
		//pfSense_fsync("/cf/conf/config.xml");
		//pfSense_fsync($g['conf_path']);
		disable_security_checks();
		log_error(sprintf(gettext('%1$s is restoring the configuration %2$s'), $g['product_label'], $file));
		file_notice("config.xml", sprintf(gettext('%1$s is restoring the configuration %2$s'), $g['product_label'], $file), "pfSenseConfigurator", "");
	}
}

/*
 *	Backup RRD/XML Data
 */

/* If the config on disk had rrddata/xmldata tags already, remove that section first.
 * See https://redmine.pfsense.org/issues/8994,
 *     https://redmine.pfsense.org/issues/10508, 
 *     https://redmine.pfsense.org/issues/11050 */
function clear_tagdata($tag = "rrd", $data) {
	$data = preg_replace("/[[:blank:]]*<{$tag}data>.*<\\/{$tag}data>[[:blank:]]*\n*/s", "", $data);
	$data = preg_replace("/[[:blank:]]*<{$tag}data\\/>[[:blank:]]*\n*/", "", $data);

	return $data;
}

function restore_xmldatafile($type='voucher', $conf = false) {
	global $config, $g;

	if (!$conf) {
		$conf = & $config;
	}

	foreach ($conf[$type]["{$type}data"]["xmldatafile"] as $file) {
		$basename = basename($file['filename']);
		$dirname = dirname($g['backuppath'][$type]);
		$xmldata_file = "{$dirname}/{$basename}";
		if (file_put_contents($xmldata_file, gzinflate(base64_decode($file['data']))) === false) {
			log_error(sprintf(gettext("Cannot write %s"), $xmldata_file));
			continue;
		}
	}
}

function restore_rrddata($conf = false) {
	global $config, $g, $rrdtool, $input_errors;

	if (!$conf) {
		$conf = & $config;
	}

	foreach ($conf['rrddata']['rrddatafile'] as $rrd) {
		if ($rrd['xmldata']) {
			$rrd_file = "{$g['vardb_path']}/rrd/{$rrd['filename']}";
			$xml_file = preg_replace('/\.rrd$/', ".xml", $rrd_file);
			if (file_put_contents($xml_file, gzinflate(base64_decode($rrd['xmldata']))) === false) {
				log_error(sprintf(gettext("Cannot write %s"), $xml_file));
				continue;
			}
			$output = array();
			$status = null;
			exec("$rrdtool restore -f '{$xml_file}' '{$rrd_file}'", $output, $status);
			if ($status) {
				log_error("rrdtool restore -f '{$xml_file}' '{$rrd_file}' failed returning {$status}.");
				continue;
			}
			unlink($xml_file);
		} else if ($rrd['data']) {
			$rrd_file = "{$g['vardb_path']}/rrd/{$rrd['filename']}";
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

/****f* config/parse_config_bootup
 * NAME
 *   parse_config_bootup - Bootup-specific configuration checks.
 * RESULT
 *   null
 ******/
function parse_config_bootup() {
	global $config, $g;

	if (platform_booting()) {
		echo ".";
	}

	$lockkey = lock('config');
	if (!file_exists("{$g['conf_path']}/config.xml")) {
		if (platform_booting()) {
			$last_backup = discover_last_backup();
			if ($last_backup) {
				log_error("No config.xml found, attempting last known config restore.");
				file_notice("config.xml", gettext("No config.xml found, attempting last known config restore."), "pfSenseConfigurator", "");
				restore_backup("/cf/conf/backup/{$last_backup}");
			}
			if (!file_exists("{$g['conf_path']}/config.xml")) {
				echo sprintf(gettext("XML configuration file not found.  %s cannot continue booting."), $g['product_label']) . "\n";
				unlock($lockkey);
				die(gettext("Could not find a usable configuration file or it's backup! Exiting...."));
			} else {
				log_error("Last known config found and restored.  Please double check the configuration file for accuracy.");
				file_notice("config.xml", gettext("Last known config found and restored.  Please double check the configuration file for accuracy."), "pfSenseConfigurator", "");
			}
		} else {
			unlock($lockkey);
			log_error(gettext("Could not find a usable configuration file! Exiting...."));
			exit(0);
		}
	}

	if (filesize("{$g['conf_path']}/config.xml") == 0) {
		$last_backup = discover_last_backup();
		if ($last_backup) {
			log_error(gettext("No config.xml found, attempting last known config restore."));
			file_notice("config.xml", gettext("No config.xml found, attempting last known config restore."), "pfSenseConfigurator", "");
			restore_backup("{$g['conf_path']}/backup/{$last_backup}");
		} else {
			unlock($lockkey);
			die(gettext("Config.xml is corrupted and is 0 bytes.  Could not restore a previous backup."));
		}
	}
	unlock($lockkey);

	$config = parse_config(true);

	if ((float)$config['version'] > (float)$g['latest_config']) {
		echo <<<EOD


*******************************************************************************
* WARNING!                                                                    *
* The current configuration has been created with a newer version of {$g['product_label']}  *
* than this one! This can lead to serious misbehavior and even security       *
* holes! You are urged to either upgrade to a newer version of {$g['product_label']} or     *
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
	global $config, $g;
	$now = date("H:i:s");
	log_error(sprintf(gettext("Start Configuration upgrade at %s, set execution timeout to 15 minutes"), $now));
	//ini_set("max_execution_time", "900");

	/* special case upgrades */
	/* fix every minute crontab bogons entry */
	if (is_array($config['cron'])) {
		$cron_item_count = count($config['cron']['item']);
		for ($x = 0; $x < $cron_item_count; $x++) {
			if (stristr($config['cron']['item'][$x]['command'], "rc.update_bogons.sh")) {
				if ($config['cron']['item'][$x]['hour'] == "*") {
					$config['cron']['item'][$x]['hour'] = "3";
					write_config(gettext("Updated bogon update frequency to 3am"));
					log_error(gettext("Updated bogon update frequency to 3am"));
				}
			}
		}
	}

	// Save off config version
	$prev_version = $config['version'];

	include_once('auth.inc');
	include_once('upgrade_config.inc');
	if (file_exists("/etc/inc/upgrade_config_custom.inc")) {
		include_once("upgrade_config_custom.inc");
	}

	if ($config['version'] == $g['latest_config']) {
		additional_config_upgrade();
		return;		/* already at latest version */
	}

	if (!is_array($config['system']['already_run_config_upgrade'])) {
		$config['system']['already_run_config_upgrade'] = array();
	}
	$already_run = $config['system']['already_run_config_upgrade'];

	/* Loop and run upgrade_VER_to_VER() until we're at current version */
	while ($config['version'] < $g['latest_config']) {
		$cur = $config['version'] * 10;
		$next = $cur + 1;
		$migration_function = sprintf('upgrade_%03d_to_%03d', $cur,
		    $next);

		foreach (array("", "_custom") as $suffix) {
			$migration_function .= $suffix;
			if (!function_exists($migration_function)) {
				continue;
			}
			if (isset($already_run[$migration_function])) {
				/* Already executed, skip now */
				unset($config['system']
				    ['already_run_config_upgrade']
				    [$migration_function]);
			} else {
				$migration_function();
			}
		}
		$config['version'] = sprintf('%.1f', $next / 10);
		if (platform_booting()) {
			echo ".";
		}
	}

	if ($prev_version != $config['version']) {
		$now = date("H:i:s");
		log_error(sprintf(gettext("Ended Configuration upgrade at %s"), $now));

		write_config(sprintf(gettext('Upgraded config version level from %1$s to %2$s'), $prev_version, $config['version']));
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
 *   $filename  - string containing the filename of the file to write
 *   $content   - string or array containing the file content to write to file
 *   $force_binary      - boolean denoting whether we should force binary
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

	/* XXX Re-add pfSense_fsync() call here after it's fixed */
	// if (!pfSense_fsync($tmp_file) || !rename($tmp_file, $file)) {
	if (!rename($tmp_file, $file)) {
		// Unable to move temporary file to original
		@unlink($tmp_file);
		return false;
	}

	// Sync file before returning
	//return pfSense_fsync($file);
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
	global $config, $g;

	// Certain strings may be embedded in the $desc (reason) parameter to trigger certain behavior.
	// If detected, those strings are removed and a variable set.
	$doacb = true;
	$manual_acb = false;
	$rcnt = 0;

	$desc = str_replace("-MaNuAlBaCkUp", "", $desc, $rcnt);
	if ($rcnt > 0) {
		$manual_acb = true; // Manual backups require special processing on the server
	}

	$rcnt = 0;
	$desc = str_replace("-NoReMoTeBaCkUp", "", $desc, $rcnt);
	if ($rcnt > 0) {
		$doacb = false; // No ACB will be performed if this string is detected
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

	if (isset($config['reset_factory_defaults'])) {
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
	$config['revision'] = make_config_revision_entry($desc);

	$lockkey = lock('config', LOCK_EX);

	/* generate configuration XML */
	$xmlconfig = dump_xml_config($config, $g['xml_rootobj']);

	/* write new configuration */
	if (!safe_write_file("{$g['cf_conf_path']}/config.xml", $xmlconfig)) {
		log_error(gettext("WARNING: Config contents could not be saved. Could not open file!"));
		unlock($lockkey);
		file_notice("config.xml", sprintf(gettext('Unable to open %1$s/config.xml for writing in write_config()%2$s'), $g['cf_conf_path'], "\n"));
		return -1;
	}

	init_config_arr('syslog');
	if ($config['syslog']['logconfigchanges'] != "disabled") {
		log_error(gettext("Configuration Change") . ": {$config['revision']['description']}");
	}

	cleanup_backupcache(true);

	/* re-read configuration */
	/* NOTE: We assume that the file can be parsed since we wrote it. */
	$config = parse_xml_config("{$g['conf_path']}/config.xml", $g['xml_rootobj']);
	if ($config == -1) {
		copy("{$g['conf_path']}/config.xml", "{$g['conf_path']}/config.xml.bad");
		$last_backup = discover_last_backup();
		if ($last_backup) {
			restore_backup("/cf/conf/backup/{$last_backup}");
			$config = parse_xml_config("{$g['conf_path']}/config.xml", $g['xml_rootobj']);
			if (platform_booting()) {
				echo "\n\n ************** WARNING **************";
				echo "\n\n Configuration could not be validated. A previous configuration was restored. \n";
				echo "\n The failed configuration file has been saved as {$g['conf_path']}/config.xml.bad \n\n";
			}
		} else {
			log_error(gettext("Could not restore config.xml."));
		}
	} else {
		generate_config_cache($config);
	}

	unlock($lockkey);

	if ($write_config_only) {
		return $config;
	}

	unlink_if_exists("/usr/local/pkg/pf/carp_sync_client.php");

	/* sync carp entries to other firewalls */
	carp_sync_client();

	if (is_dir("/usr/local/pkg/write_config")) {
		/* process packager manager custom rules */
		run_plugins("/usr/local/pkg/write_config/");
	}

	// Try the core AutoConfigBackup system
	if (is_array($config['system']['acb']) && $config['system']['acb']['enable'] == "yes" &&
	    (!isset($config['system']['acb']['frequency']) || $config['system']['acb']['frequency'] == "every") || file_exists("/tmp/forceacb")) {
	    if ($doacb) {
			require_once("acb.inc");
			upload_config($manual_acb);
		}

		if (file_exists("/tmp/forceacb")) {
			unlink("/tmp/forceacb");
		}
	}

	return $config;
}

/****f* config/reset_factory_defaults
 * NAME
 *   reset_factory_defaults - Reset the system to its default configuration.
 * RESULT
 *   integer	- indicates completion
 ******/
function reset_factory_defaults($lock = false, $reboot_required = true) {
	global $config, $g;

	/* Remove all additional packages */
	mwexec("/bin/sh /usr/local/sbin/{$g['product_name']}-upgrade " .
	    "-r ALL_PACKAGES -f");

	if (!$lock) {
		$lockkey = lock('config', LOCK_EX);
	}

	/* create conf directory, if necessary */
	safe_mkdir($g['cf_conf_path']);

	/* clear out /conf */
	$dh = opendir($g['conf_path']);
	while ($filename = readdir($dh)) {
		if (($filename != ".") && ($filename != "..") &&
		    (!is_dir($g['conf_path'] . "/" . $filename))) {
			if ($filename == "enableserial_force")
				continue;
			unlink_if_exists($g['conf_path'] . "/" . $filename);
		}
	}
	closedir($dh);
	unlink_if_exists($g['tmp_path'] . "/config.cache");

	/* copy default configuration */
	copy("{$g['conf_default_path']}/config.xml",
	    "{$g['cf_conf_path']}/config.xml");

	disable_security_checks();

	/*
	   Let write_config know that we are awaiting reload of the current config
	   to factory defaults. Either the system is about to reboot, throwing away
	   the current in-memory config as it shuts down, or the in-memory config
	   is about to be reloaded on-the-fly by parse_config.

	   In both cases, we want to ensure that write_config does not flush the
	   in-memory config back to disk.
	*/
	$config['reset_factory_defaults'] = true;

	/* call the wizard */
	if ($reboot_required) {
		// If we need a reboot first then touch a different trigger file.
		touch("/conf/trigger_initial_wizard_after_reboot");
	} else {
		touch("/conf/trigger_initial_wizard");
	}
	if (!$lock) {
		unlock($lockkey);
	}
	console_configure();
	return 0;
}

function config_restore($conffile) {
	global $config, $g;

	if (!file_exists($conffile)) {
		return 1;
	}

	backup_config();


	$lockkey = lock('config', LOCK_EX);

	unlink_if_exists("{$g['tmp_path']}/config.cache");
	copy($conffile, "{$g['cf_conf_path']}/config.xml");

	disable_security_checks();

	unlock($lockkey);

	$config = parse_config(true);


	write_config(sprintf(gettext("Reverted to %s."), array_pop(explode("/", $conffile))), false);

	return 0;
}

function config_install($conffile) {
	global $config, $g;

	if (!file_exists($conffile)) {
		return 1;
	}

	if (!config_validate("{$conffile}")) {
		return 1;
	}

	if (platform_booting()) {
		echo gettext("Installing configuration...") . "\n";
	} else {
		log_error(gettext("Installing configuration ...."));
	}

	$lockkey = lock('config', LOCK_EX);

	copy($conffile, "{$g['conf_path']}/config.xml");

	disable_security_checks();

	/* unlink cache file if it exists */
	if (file_exists("{$g['tmp_path']}/config.cache")) {
		unlink("{$g['tmp_path']}/config.cache");
	}

	unlock($lockkey);

	return 0;
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

/* Restores security checks.  Should be called after all succeed. */
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
	global $config, $g;
	$i = false;

	$revisions = intval(is_numericint($config['system']['backupcount']) ? $config['system']['backupcount'] : $g['default_config_backup_count']);

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
	$bakfiles = glob($g['cf_conf_path'] . "/backup/config-*");
	$tocache = array();

	foreach ($bakfiles as $backup) { // Check for backups in the directory not represented in the cache.
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
			if (platform_booting()) {
				echo ".";
			}
			try {
				$newxml = parse_xml_config($backup, array($g['xml_rootobj'], 'pfsense'));
			} catch (Exception $exc) {
				log_error(sprintf(gettext("The backup cache file %s is corrupted. Parser error message: %s"), $backup, $exc->getMessage()));
				$newxml = "-1";
			}

			if ($newxml == "-1") {
				log_error(sprintf(gettext("The backup cache file %s is corrupted.  Unlinking."), $backup));
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
			if (platform_booting()) print " " . $tocheck . "r";
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
				unlink_if_exists($g['conf_path'] . '/backup/config-' . $version . '.xml');
			}
		}
		$tocache = $newcache;
	}
	$bakout = fopen($g['cf_conf_path'] . '/backup/backup.cache', "w");
	fwrite($bakout, serialize($tocache));
	fclose($bakout);
	//pfSense_fsync("{$g['cf_conf_path']}/backup/backup.cache");

	if (!$lock) {
		unlock($lockkey);
	}
}

function get_backups() {
	global $g;
	if (file_exists("{$g['cf_conf_path']}/backup/backup.cache")) {
		$confvers = unserialize(file_get_contents("{$g['cf_conf_path']}/backup/backup.cache"));
		$bakvers = array_keys($confvers);
		$toreturn = array();
		sort($bakvers);
		// 	$bakvers = array_reverse($bakvers);
		foreach (array_reverse($bakvers) as $bakver) {
			$toreturn[] = array('time' => $bakver, 'description' => $confvers[$bakver]['description'], 'version' => $confvers[$bakver]['version'], 'filesize' => $confvers[$bakver]['filesize']);
		}
	} else {
		return false;
	}
	$toreturn['versions'] = $bakvers;
	return $toreturn;
}

function backup_config() {
	global $config, $g;


	/* Create backup directory if needed */
	safe_mkdir("{$g['cf_conf_path']}/backup");
	if ($config['revision']['time'] == "") {
		$baktime = 0;
	} else {
		$baktime = $config['revision']['time'];
	}

	if ($config['revision']['description'] == "") {
		$bakdesc = "Unknown";
	} else {
		$bakdesc = $config['revision']['description'];
	}

	$bakver = ($config['version'] == "") ? "?" : $config['version'];
	$bakfilename = $g['cf_conf_path'] . '/backup/config-' . $baktime . '.xml';
	copy($g['cf_conf_path'] . '/config.xml', $bakfilename);

	if (file_exists($g['cf_conf_path'] . '/backup/backup.cache')) {
		$backupcache = unserialize(file_get_contents($g['cf_conf_path'] . '/backup/backup.cache'));
	} else {
		$backupcache = array();
	}
	$backupcache[$baktime] = array('description' => $bakdesc, 'version' => $bakver, 'filesize' => filesize($bakfilename));
	$bakout = fopen($g['cf_conf_path'] . '/backup/backup.cache', "w");
	fwrite($bakout, serialize($backupcache));
	fclose($bakout);
	//pfSense_fsync("{$g['cf_conf_path']}/backup/backup.cache");


	return true;
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
		$path = "/dev/$name";
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

	if (time() > mktime(0, 0, 0, 9, 1, 2004)) {     /* make sure the clock settings are plausible */
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
	if ($error !== NULL && !preg_match('|^' . preg_quote($g['tmp_path_user_code']) . '/[^/]{1,16}$|', $error['file'])) {
		if (in_array($error['type'], array(E_ERROR, E_COMPILE_ERROR, E_CORE_ERROR, E_RECOVERABLE_ERROR))) {
			$errorstr = "PHP ERROR: Type: {$error['type']}, File: {$error['file']}, Line: {$error['line']}, Message: {$error['message']}";
			print($errorstr);
			log_error($errorstr);
			file_notice("phperror", $errorstr, 'PHP errors');
		} else if ($error['type'] != E_NOTICE) {
			$errorstr = "PHP WARNING: Type: {$error['type']}, File: {$error['file']}, Line: {$error['line']}, Message: {$error['message']}";
			// XXX: comment out for now, should re-enable post-2.2
			//print($errorstr);
			//log_error($errorstr);
			//file_notice("phpwarning", $errorstr, 'PHP warning');
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

/* Initialize a config array multiple levels deep only if unset
 * Pass it an array of keys to test and create
 * init_config_arr(array('virtualip', 'vip'));
 */
function init_config_arr($keys) {
	global $config;
	$c = &$config;
	if (!is_array($keys)) {
		return null;
	}
	foreach ($keys as $k) {
		if (!is_array($c[$k])) {
			$c[$k] = array();
		}
		$c = &$c[$k];
	}
}

register_shutdown_function('pfSense_clear_globals');

?>
