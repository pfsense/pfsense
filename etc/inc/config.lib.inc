<?php
/****h* pfSense/config
 * NAME
 *   config.inc - Functions to manipulate config.xml
 * DESCRIPTION
 *   This include contains various config.xml specific functions.
 * HISTORY
 * $Id$
 ******

	config.lib.inc
	Ported from config.inc by Erik Kristensen
	Copyright (C) 2004-2006 Scott Ullrich
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.


	pfSense_BUILDER_BINARIES:	/sbin/mount	/sbin/sysctl	/sbin/umount	/sbin/halt	/sbin/fsck	/bin/sync
	pfSense_MODULE:	config
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
	if(file_exists($g['conf_path'] . "/config.xml")) {
		if($g['booting']) {
			$configtxt = file_get_contents($g['conf_path'] . "/config.xml");			
			if(tagfile_deformat($configtxt, $configtxt, "config.xml")) {
				$fp = fopen('php://stdin', 'r');
				$data = "";
				echo "\n\n*** Encrypted config.xml detected ***\n";
				while($data == "") {
					echo "\nEnter the password to decrypt config.xml: ";
					$decrypt_password = chop(fgets($fp));
					$data = decrypt_data($configtxt, $decrypt_password);
					if(!strstr($data, "<pfsense>"))
						$data = "";
					if($data) {
						$fd = fopen($g['conf_path'] . "/config.xml.tmp", "w");
						fwrite($fd, $data);
						fclose($fd);
						exec("/bin/mv {$g['conf_path']}/config.xml.tmp {$g['conf_path']}/config.xml");
						echo "\nConfig.xml unlocked.\n";
						fclose($fp);
					} else {
						echo "\nInvalid password entered.  Please try again.\n";
					}
				}
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
	global $g, $config_parsed;
	
	$lockkey = lock('config');
	$config_parsed = false;
	if (!file_exists("{$g['conf_path']}/config.xml") || filesize("{$g['conf_path']}/config.xml") == 0) {
		$last_backup = discover_last_backup();
		if($last_backup) {
			log_error("No config.xml found, attempting last known config restore.");
			file_notice("config.xml", "No config.xml found, attempting last known config restore.", "pfSenseConfigurator", "");
			restore_backup("{$g['conf_path']}/backup/{$last_backup}");
		} else {
			unlock($lockkey);
			die("Config.xml is corrupted and is 0 bytes.  Could not restore a previous backup.");
		}
	}
	if($g['booting']) echo ".";
	// Check for encrypted config.xml
	encrypted_configxml();
	if(!$parse) {
		if(file_exists($g['tmp_path'] . '/config.cache')) {
			$config = unserialize(file_get_contents($g['tmp_path'] . '/config.cache'));
			if(is_null($config)) {
				unlock($lockkey);
				parse_config(true);
				$lockkey = lock('config');
			}
		} else {
			if(!file_exists($g['conf_path'] . "/config.xml")) {
				log_error("No config.xml found, attempting last known config restore.");
				file_notice("config.xml", "No config.xml found, attempting last known config restore.", "pfSenseConfigurator", "");
				$last_backup = discover_last_backup();
				if ($last_backup)
					restore_backup("/cf/conf/backup/{$last_backup}");
				else
					log_error("Could not restore config.xml.");
			}
			unlock($lockkey);
			$config = parse_config(true);
			$lockkey = lock('config');
		}
	} else {
		if(!file_exists($g['conf_path'] . "/config.xml")) {
			if($g['booting']) echo ".";
			log_error("No config.xml found, attempting last known config restore.");
			file_notice("config.xml", "No config.xml found, attempting last known config restore.", "pfSenseConfigurator", "");
			$last_backup = discover_last_backup();
			if ($last_backup)
				restore_backup("/cf/conf/backup/{$last_backup}");
			else
				log_error("Could not restore config.xml.");
		}
		$config = parse_xml_config($g['conf_path'] . '/config.xml', $g['xml_rootobj']);
		if($config == "-1") {
			$last_backup = discover_last_backup();
			if ($last_backup)
				restore_backup("/cf/conf/backup/{$last_backup}");
			else
				log_error(gettext("Could not restore config.xml."));
		}
		generate_config_cache($config);
	}
	if($g['booting']) echo ".";
	alias_make_table($config);
	$config_parsed = true;
	unlock($lockkey);

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
	global $g;

	$configcache = fopen($g['tmp_path'] . '/config.cache', "w");
	fwrite($configcache, serialize($config));
	fclose($configcache);
}

function discover_last_backup() {
        $backups = split("\n", `cd /cf/conf/backup && ls -ltr *.xml | awk '{print \$9}'`);
	$last_backup = "";
        foreach($backups as $backup)
        	if($backup)
	        	$last_backup = $backup;

        return $last_backup;
}

function restore_backup($file) {
	global $g;

	if (file_exists($file)) {
		conf_mount_rw();
		unlink_if_exists("{$g['tmp_path']}/config.cache");
		copy("$file","/cf/conf/config.xml");
		log_error("{$g['product_name']} is restoring the configuration $file");
		file_notice("config.xml", "{$g['product_name']} is restoring the configuration $file", "pfSenseConfigurator", "");
		conf_mount_ro();
	}
}

/****f* config/parse_config_bootup
 * NAME
 *   parse_config_bootup - Bootup-specific configuration checks.
 * RESULT
 *   null
 ******/
function parse_config_bootup() {
	global $config, $g, $noparseconfig;

	if($g['booting']) echo ".";

	$lockkey = lock('config');
	if (!$noparseconfig) {
		if (!file_exists("{$g['conf_path']}/config.xml")) {
			if ($g['booting']) {
				if (strstr($g['platform'], "cdrom")) {
					/* try copying the default config. to the floppy */
					echo "Resetting factory defaults...\n";
					reset_factory_defaults(true);
					if (file_exists("{$g['conf_path']}/config.xml")) {
						/* do nothing, we have a file. */
					} else {
						echo "No XML configuration file found - using factory defaults.\n";
						echo "Make sure that the configuration floppy disk with the conf/config.xml\n";
						echo "file is inserted. If it isn't, your configuration changes will be lost\n";
						echo "on reboot.\n";
					}
				} else {
					$last_backup = discover_last_backup();
					if($last_backup) {
						log_error("No config.xml found, attempting last known config restore.");
						file_notice("config.xml", "No config.xml found, attempting last known config restore.", "pfSenseConfigurator", "");
						restore_backup("/cf/conf/backup/{$last_backup}");
					}
					if(!file_exists("{$g['conf_path']}/config.xml")) {
						echo "XML configuration file not found.  {$g['product_name']} cannot continue booting.\n";
						mwexec("/sbin/halt");
						exit;
					}
					log_error("Last known config found and restored.  Please double check your configuration file for accuracy.");
					file_notice("config.xml", "Last known config found and restored.  Please double check your configuration file for accuracy.", "pfSenseConfigurator", "");
				}
			} else {
				unlock($lockkey);
				exit(0);
			}
		}
	}
	if (filesize("{$g['conf_path']}/config.xml") == 0) {
		$last_backup = discover_last_backup();
		if($last_backup) {
			log_error("No config.xml found, attempting last known config restore.");
			file_notice("config.xml", "No config.xml found, attempting last known config restore.", "pfSenseConfigurator", "");
			restore_backup("{$g['conf_path']}/backup/{$last_backup}");
		} else {
			unlock($lockkey);
			die("Config.xml is corrupted and is 0 bytes.  Could not restore a previous backup.");
		}
	}
	unlock($lockkey);
	parse_config(true);

	if ((float)$config['version'] > (float)$g['latest_config']) {
		echo <<<EOD


*******************************************************************************
* WARNING!                                                                    *
* The current configuration has been created with a newer version of {$g['product_name']}  *
* than this one! This can lead to serious misbehavior and even security       *
* holes! You are urged to either upgrade to a newer version of {$g['product_name']} or     *
* revert to the default configuration immediately!                            *
*******************************************************************************


EOD;
		}

	/* make alias table (for faster lookups) */
	alias_make_table($config);
}

/****f* config/conf_mount_rw
 * NAME
 *   conf_mount_rw - Mount filesystems read/write.
 * RESULT
 *   null
 ******/
/* mount flash card read/write */
function conf_mount_rw() {
	global $g;

	/* do not mount on cdrom platform */
	if($g['platform'] == "cdrom" or $g['platform'] == "pfSense")
		return;

	if (refcount_reference(1000) > 1)
		return;

	$status = mwexec("/sbin/mount -u -w {$g['cf_path']}");
	if($status <> 0) {
		if($g['booting'])
			echo "Disk is dirty.  Running fsck -y\n";
		mwexec("/sbin/fsck -y {$g['cf_path']}");
		$status = mwexec("/sbin/mount -u -w {$g['cf_path']}");
	}

	/*    if the platform is soekris or wrap or pfSense, lets mount the
	 *    compact flash cards root.
         */
	$status = mwexec("/sbin/mount -u -w /");
	/* we could not mount this correctly.  kick off fsck */
	if($status <> 0) {
		log_error("File system is dirty.  Launching FSCK for /");
		mwexec("/sbin/fsck -y /");
		$status = mwexec("/sbin/mount -u -w /");
	}
	
	mark_subsystem_dirty('mount');
}

/****f* config/conf_mount_ro
 * NAME
 *   conf_mount_ro - Mount filesystems readonly.
 * RESULT
 *   null
 ******/
function conf_mount_ro() {
	global $g;

	/* do not umount on cdrom or pfSense platforms */
	if($g['platform'] == "cdrom" or $g['platform'] == "pfSense")
		return;

	if (refcount_unreference(1000) > 0)
		return;

	clear_subsystem_dirty('mount');
	/* sync data, then force a remount of /cf */
	mwexec("/bin/sync; /bin/sync");
	mwexec("/sbin/mount -u -r -f {$g['cf_path']}");
	mwexec("/sbin/mount -u -r -f /");
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
	log_error("Start Configuration upgrade at $now, set execution timeout to 15 minutes");
	ini_set("max_execution_time", "900");

	/* special case upgrades */
	/* fix every minute crontab bogons entry */
	$cron_item_count = count($config['cron']['item']);
	for($x=0; $x<$cron_item_count; $x++) {
		if(stristr($config['cron']['item'][$x]['command'], "rc.update_bogons.sh")) {
			if($config['cron']['item'][$x]['hour'] == "*" ) {
		        $config['cron']['item'][$x]['hour'] = "3";
		 		write_config("Updated bogon update frequency to 3am");
		 		log_error("Updated bogon update frequency to 3am");
		 	}       
		}
	}
	if ($config['version'] == $g['latest_config'])
		return;		/* already at latest version */

	// Save off config version
	$prev_version = $config['version'];
	
	include_once('auth.inc');
	include_once('upgrade_config.inc');
	/* Loop and run upgrade_VER_to_VER() until we're at current version */
	while ($config['version'] < $g['latest_config']) {
		$cur = $config['version'] * 10;
		$next = $cur + 1;
		$migration_function = sprintf('upgrade_%03d_to_%03d', $cur, $next);
		$migration_function();
		$config['version'] = sprintf('%.1f', $next / 10);
		if($g['booting'])
			echo ".";
	}

	$now = date("H:i:s");
	log_error("Ended Configuration upgrade at $now");

	if ($prev_version != $config['version'])
		write_config("Upgraded config version level from {$prev_version} to {$config['version']}");

	if($g['booting'])
		echo "Loading new configuration...";
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
 *   $content   - string containing the file content to write to file
 *   $force_binary      - boolean denoting whether we should force binary
 *   mode writing.
 * RESULT
 *   boolean - true if successful, false if not
 ******/
function safe_write_file($file, $content, $force_binary) {
        $tmp_file = $file . "." . getmypid();
        $write_mode = $force_binary ? "wb" : "w";

        $fd = fopen($tmp_file, $write_mode);
        if (!$fd) {
                // Unable to open temporary file for writing
                return false;
        }
        if (!fwrite($fd, $content)) {
                // Unable to write to temporary file
                fclose($fd);
                return false;
        }
        fclose($fd);

        if (!rename($tmp_file, $file)) {
                // Unable to move temporary file to original
                unlink($tmp_file);
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
 * RESULT
 *   null
 ******/
/* save the system configuration */
function write_config($desc="Unknown", $backup = true) {
	global $config, $g;

	if($g['bootup']) 
		log_error("WARNING! Configuration written on bootup.  This can cause stray openvpn and load balancing items in config.xml");

	if($backup)
		backup_config();

	if (time() > mktime(0, 0, 0, 9, 1, 2004))       /* make sure the clock settings are plausible */
		$config['revision']['time'] = time();

	/* Log the running script so it's not entirely unlogged what changed */
	if ($desc == "Unknown")
		$desc = "{$_SERVER['SCRIPT_NAME']} made unknown change";

	$config['revision']['description'] = "{$_SESSION['Username']}: " . $desc;
	$config['revision']['username'] = $_SESSION["Username"];

	conf_mount_rw();
	$lockkey = lock('config', LOCK_EX);

	/* generate configuration XML */
	$xmlconfig = dump_xml_config($config, $g['xml_rootobj']);

	/* write new configuration */
	if (!safe_write_file("{$g['cf_conf_path']}/config.xml", $xmlconfig, false)) {
		log_error("WARNING: Config contents could not be save. Could not open file!");
		unlock($lockkey);
		file_notice("config.xml", "Unable to open {$g['cf_conf_path']}/config.xml for writing in write_config()\n");
		return -1;
	}
	
	if($g['platform'] == "embedded" or $g['platform'] == "nanobsd") {
		cleanup_backupcache(5, true);
	} else {
		cleanup_backupcache(30, true);
	}

	/* re-read configuration */
	/* NOTE: We assume that the file can be parsed since we wrote it. */
	$config = parse_xml_config("{$g['conf_path']}/config.xml", $g['xml_rootobj']);
	if ($config == -1) {
		$last_backup = discover_last_backup();
		if ($last_backup)
			restore_backup("/cf/conf/backup/{$last_backup}");
		else
			log_error(gettext("Could not restore config.xml."));
	} else
		generate_config_cache($config);

	unlock($lockkey);

	unlink_if_exists("/usr/local/pkg/pf/carp_sync_client.php");

	/* tell kernel to sync fs data */
	conf_mount_ro();

	/* sync carp entries to other firewalls */
	carp_sync_client();

	if(is_dir("/usr/local/pkg/write_config")) {
		/* process packager manager custom rules */
		run_plugins("/usr/local/pkg/write_config/");
	}

	return $config;
}

/****f* config/reset_factory_defaults
 * NAME
 *   reset_factory_defaults - Reset the system to its default configuration.
 * RESULT
 *   integer	- indicates completion
 ******/
function reset_factory_defaults($lock = false) {
	global $g;

	conf_mount_rw();
	if (!$lock)
		$lockkey = lock('config', LOCK_EX);

	/* create conf directory, if necessary */
	safe_mkdir("{$g['cf_conf_path']}");

	/* clear out /conf */
	$dh = opendir($g['conf_path']);
	while ($filename = readdir($dh)) {
		if (($filename != ".") && ($filename != "..")) {
			unlink_if_exists($g['conf_path'] . "/" . $filename);
		}
	}
	closedir($dh);

	/* copy default configuration */
	copy("{$g['conf_default_path']}/config.xml", "{$g['conf_path']}/config.xml");

	/* call the wizard */
	touch("/conf/trigger_initial_wizard");
	if (!$lock)
		unlock($lockkey);
	conf_mount_ro();

	return 0;
}

function config_restore($conffile) {
	global $config, $g;

	if (!file_exists($conffile))
		return 1;

	backup_config();

	conf_mount_rw();
	
	$lockkey = lock('config', LOCK_EX);

	unlink_if_exists("{$g['tmp_path']}/config.cache");
	copy($conffile, "{$g['cf_conf_path']}/config.xml");

	unlock($lockkey);

	$config = parse_config(true);

	conf_mount_ro();

	write_config("Reverted to " . array_pop(explode("/", $conffile)) . ".", false);

	return 0;
}

function config_install($conffile) {
	global $config, $g;

	if (!file_exists($conffile))
		return 1;

	if (!config_validate("{$conffile}"))
		return 1;

	if($g['booting'] == true)
		echo "Installing configuration...\n";
	else
		log_error("Installing configuration ....");

	conf_mount_rw();
	$lockkey = lock('config', LOCK_EX);

	copy($conffile, "{$g['conf_path']}/config.xml");

	/* unlink cache file if it exists */
	if(file_exists("{$g['tmp_path']}/config.cache"))
		unlink("{$g['tmp_path']}/config.cache");

	unlock($lockkey);
	conf_mount_ro();

    return 0;
}

function config_validate($conffile) {

	global $g, $xmlerr;

	$xml_parser = xml_parser_create();

	if (!($fp = fopen($conffile, "r"))) {
		$xmlerr = "XML error: unable to open file";
		return false;
	}

	while ($data = fread($fp, 4096)) {
		if (!xml_parse($xml_parser, $data, feof($fp))) {
			$xmlerr = sprintf("%s at line %d",
						xml_error_string(xml_get_error_code($xml_parser)),
						xml_get_current_line_number($xml_parser));
			return false;
		}
	}
	xml_parser_free($xml_parser);

	fclose($fp);

	return true;
}

function set_networking_interfaces_ports() {
	global $noreboot;
	global $config;
	global $g;
	global $fp;

	$fp = fopen('php://stdin', 'r');

	$memory = get_memory();
	$avail = $memory[0];

	if($avail < $g['minimum_ram_warning']) {
		echo "\n\n\n";
		echo "DANGER!  WARNING!  ACHTUNG!\n\n";
		echo "{$g['product_name']} requires *AT LEAST* {$g['minimum_ram_warning_text']} RAM to function correctly.\n";
		echo "Only ({$avail}) MB RAM has been detected.\n";
		echo "\nPress ENTER to continue. ";
		fgets($fp);
		echo "\n";
	}

	$iflist = get_interface_list();

/* Function flow is based on $key and $auto_assign or the lack thereof */	
	$key = null;

/* Only present auto interface option if running from LiveCD and interface mismatch*/
	if ((ereg("cdrom", $g['platform'])) && is_interface_mismatch())
		$auto_assign = false;

	echo <<<EOD

Valid interfaces are:


EOD;

	if(!is_array($iflist)) {
		echo "No interfaces found!\n";
		$iflist = array();
	} else {
		foreach ($iflist as $iface => $ifa) {
			echo sprintf("% -6s%s%s\t%s\n", $iface, $ifa['mac'],
				$ifa['up'] ? "   (up)" : "   (down)", $ifa['dmesg']);
		}
	}

	if ($auto_assign) {
		echo <<<EOD
		
		!!! LiveCD Detected: Auto Interface Option !!!!
BEGIN MANUAL CONFIGURATION OR WE WILL PROCEED WITH AUTO CONFIGURATION.

EOD;
	}	
	
	echo <<<EOD

Do you want to set up VLANs first? 

If you are not going to use VLANs, or only for optional interfaces, you should
say no here and use the webConfigurator to configure VLANs later, if required.

Do you want to set up VLANs now [y|n]? 
EOD;

	if ($auto_assign) {
		$key = timeout();

	} else
		$key = chop(fgets($fp));

	if (!isset($key) and $auto_assign) {	// Auto Assign Interfaces
		do {
			echo <<<EOD

   !!! Auto Assigning Interfaces !!!

For installation purposes, you must plug in at least one NIC
for the LAN connection. If you plug in a second NIC it will be
assigned to WAN. Otherwise, we'll temporarily assign WAN to the
next available NIC found regardless of activity. You should
assign and configure the WAN interface according to your requirements

If you haven't plugged in any network cables yet,
now is the time to do so.
We'll keep trying until you do.

Searching for active interfaces...
 
EOD;
			unset($wanif, $lanif);

			$media_iflist = $plugged_in = array();
			$media_iflist = get_interface_list("media");
			foreach ($media_iflist as $iface => $ifa) {
				if ($ifa['up']) 
					$plugged_in[] = $iface;
				
			}

			$lanif = array_shift($plugged_in);
			$wanif = array_shift($plugged_in);

			if(isset($lanif) && !isset($wanif)) {
				foreach ($iflist as $iface => $ifa) {
					if ($iface != $lanif) {
						$wanif = $iface;
						break;
					}
				}
			}

			echo <<<EOD

Assigned WAN to : $wanif 
Assigned LAN to : $lanif

If you don't like this assignment,
press any key to go back to manual configuration. 

EOD;
			$key = timeout(20);
			if(isset($key))
				return;
		} while (!isset($wanif));

		$config['system']['enablesshd'] = 'enabled';	
		$key = 'y';

	} else {		//Manually assign interfaces	
		if (in_array($key, array('y', 'Y')))
			vlan_setup();
	
		if (is_array($config['vlans']['vlan']) && count($config['vlans']['vlan'])) {
	
			echo "\n\nVLAN interfaces:\n\n";
			foreach ($config['vlans']['vlan'] as $vlan) {
	
				echo sprintf("% -16s%s\n", "{$vlan['if']}_vlan{$vlan['tag']}",
					"VLAN tag {$vlan['tag']}, parent interface {$vlan['if']}");
	
				$iflist[$vlan['if'] . '_vlan' . $vlan['tag']] = array();
			}
		}
	
		echo <<<EOD
	
*NOTE*  {$g['product_name']} requires {$g['minimum_nic_count_text']} assigned interfaces to function.
        If you do not have {$g['minimum_nic_count_text']} interfaces you CANNOT continue. 

        If you do not have at least {$g['minimum_nic_count']} *REAL* network interface cards
        or one interface with multiple VLANs then {$g['product_name']}
        *WILL NOT* function correctly.

If you do not know the names of your interfaces, you may choose to use
auto-detection. In that case, disconnect all interfaces now before
hitting 'a' to initiate auto detection.
	
EOD;
	
		do {
			echo "\nEnter the WAN interface name or 'a' for auto-detection: ";
			$wanif = chop(fgets($fp));
			if ($wanif === "") {
				return;
			}
			if ($wanif === "a")
				$wanif = autodetect_interface("WAN", $fp);
			else if (!array_key_exists($wanif, $iflist)) {
				echo "\nInvalid interface name '{$wanif}'\n";
				unset($wanif);
				continue;
			}
		} while (!$wanif);
	
		do {
			echo "\nEnter the LAN interface name or 'a' for auto-detection \n" .
			    "NOTE: this enables full Firewalling/NAT mode.\n" .
				"(or nothing if finished): ";
	
			$lanif = chop(fgets($fp));
			
			if($lanif == "exit") {
				exit;
			}
			
			if($lanif == "") {
				if($g['minimum_nic_count'] < 2) {
					break;	
				} else {
					fclose($fp);
					return;
				}
			}
	
			if ($lanif === "a")
				$lanif = autodetect_interface("LAN", $fp);
			else if (!array_key_exists($lanif, $iflist)) {
				echo "\nInvalid interface name '{$lanif}'\n";
				unset($lanif);
				continue;
			}
		} while (!$lanif);
	
		/* optional interfaces */
		$i = 0;
		$optif = array();
	
		if($lanif <> "") {
			while (1) {
				if ($optif[$i])
					$i++;
				$i1 = $i + 1;
		
				if($config['interfaces']['opt' . $i1]['descr'])
					echo "\nOptional interface {$i1} description found: {$config['interfaces']['opt' . $i1]['descr']}";
	
				echo "\nEnter the Optional {$i1} interface name or 'a' for auto-detection\n" .
					"(or nothing if finished): ";
		
				$optif[$i] = chop(fgets($fp));
		
				if ($optif[$i]) {
					if ($optif[$i] === "a") {
						$ad = autodetect_interface("Optional " . $i1, $fp);
						if ($ad)
							$optif[$i] = $ad;
						else
							unset($optif[$i]);
					} else if (!array_key_exists($optif[$i], $iflist)) {
						echo "\nInvalid interface name '{$optif[$i]}'\n";
						unset($optif[$i]);
						continue;
					}
				} else {
					unset($optif[$i]);
					break;
				}
			}
		}
		
		/* check for double assignments */
		$ifarr = array_merge(array($lanif, $wanif), $optif);
		
		for ($i = 0; $i < (count($ifarr)-1); $i++) {
			for ($j = ($i+1); $j < count($ifarr); $j++) {
				if ($ifarr[$i] == $ifarr[$j]) {
					echo <<<EOD
	
Error: you cannot assign the same interface name twice!
	
EOD;
					fclose($fp);
					return;
				}
			}
		}
	
		echo "\nThe interfaces will be assigned as follows: \n\n";
	
		if ($lanif != "")
			echo "LAN  -> " . $lanif . "\n";
		echo "WAN  -> " . $wanif . "\n";
		for ($i = 0; $i < count($optif); $i++) {
			echo "OPT" . ($i+1) . " -> " . $optif[$i] . "\n";
		}
	
		echo <<<EOD
	
Do you want to proceed [y|n]?
EOD;
			$key = chop(fgets($fp));		
	}

	if (in_array($key, array('y', 'Y'))) {
		if($lanif) {
			$config['interfaces']['lan']['if'] = $lanif;
			$config['interfaces']['lan']['enable'] = true;
		} elseif (!$g['booting'] && !$auto_assign) {

echo <<<EODD

You have chosen to remove the LAN interface.

Would you like to remove the LAN IP address and
unload the interface now? [y|n]? 
EODD;

				if (strcasecmp(chop(fgets($fp)), "y") == 0) {
					if($config['interfaces']['lan']['if'])
						mwexec("/sbin/ifconfig delete " . $config['interfaces']['lan']['if']);
				}
				if(isset($config['interfaces']['lan']))
					unset($config['interfaces']['lan']);
				if(isset($config['dhcpd']['lan']))
					unset($config['dhcpd']['lan']);
				if(isset($config['interfaces']['lan']['if']))
					unset($config['interfaces']['lan']['if']);
				if(isset($config['interfaces']['wan']['blockpriv']))
					unset($config['interfaces']['wan']['blockpriv']);
				if(isset($config['shaper']))
					unset($config['shaper']);
				if(isset($config['ezshaper']))
					unset($config['ezshaper']);
				if(isset($config['nat']))
					unset($config['nat']);				
		} else {
			if(isset($config['interfaces']['lan']['if']))
				mwexec("/sbin/ifconfig delete " . $config['interfaces']['lan']['if']);
			if(isset($config['interfaces']['lan']))
				unset($config['interfaces']['lan']);
			if(isset($config['dhcpd']['lan']))
				unset($config['dhcpd']['lan']);
			if(isset($config['interfaces']['lan']['if']))
				unset($config['interfaces']['lan']['if']);
			if(isset($config['interfaces']['wan']['blockpriv']))
				unset($config['interfaces']['wan']['blockpriv']);
			if(isset($config['shaper']))
				unset($config['shaper']);
			if(isset($config['ezshaper']))
				unset($config['ezshaper']);
			if(isset($config['nat']))
				unset($config['nat']);				
		}
		if (preg_match($g['wireless_regex'], $lanif)) {
			if (is_array($config['interfaces']['lan']) &&
				(!is_array($config['interfaces']['lan']['wireless'])))
				$config['interfaces']['lan']['wireless'] = array();
		} else {
			unset($config['interfaces']['lan']['wireless']);
		}

		$config['interfaces']['wan']['if'] = $wanif;
		$config['interfaces']['wan']['enable'] = true;
		if (preg_match($g['wireless_regex'], $wanif)) {
			if (is_array($config['interfaces']['lan']) &&
				(!is_array($config['interfaces']['wan']['wireless'])))
				$config['interfaces']['wan']['wireless'] = array();
		} else {
			unset($config['interfaces']['wan']['wireless']);
		}

		for ($i = 0; $i < count($optif); $i++) {
			if (!is_array($config['interfaces']['opt' . ($i+1)]))
				$config['interfaces']['opt' . ($i+1)] = array();

			$config['interfaces']['opt' . ($i+1)]['if'] = $optif[$i];

			/* wireless interface? */
			if (preg_match($g['wireless_regex'], $optif[$i])) {
				if (!is_array($config['interfaces']['opt' . ($i+1)]['wireless']))
					$config['interfaces']['opt' . ($i+1)]['wireless'] = array();
			} else {
				unset($config['interfaces']['opt' . ($i+1)]['wireless']);
			}

			unset($config['interfaces']['opt' . ($i+1)]['enable']);
			$config['interfaces']['opt' . ($i+1)]['descr'] = "OPT" . ($i+1);
		}

		/* remove all other (old) optional interfaces */
		for (; isset($config['interfaces']['opt' . ($i+1)]); $i++)
			unset($config['interfaces']['opt' . ($i+1)]);

		echo "\nWriting configuration...";
		write_config();
		echo "done.\n";

		echo <<<EOD



EOD;

		fclose($fp);
		if($g['booting'])
			return;

		echo "One moment while we reload the settings...";

		$g['booting'] = false;

		/* XXX: ermal - disable it for now this is used during bootup at best so shouldn't be needed.
		 * 		For now just comment it out and later remove it completely.
		 * resync everything 
			reload_all_sync();
		 */

		echo " done!\n";

		touch("{$g['tmp_path']}/assign_complete");

	}
}

function autodetect_interface($ifname, $fp) {
	$iflist_prev = get_interface_list("media");
	echo <<<EOD

Connect the {$ifname} interface now and make sure that the link is up.
Then press ENTER to continue.

EOD;
	fgets($fp);
	$iflist = get_interface_list("media");

	foreach ($iflist_prev as $ifn => $ifa) {
		if (!$ifa['up'] && $iflist[$ifn]['up']) {
			echo "Detected link-up on interface {$ifn}.\n";
			return $ifn;
		}
	}

	echo "No link-up detected.\n";

	return null;
}

function vlan_setup() {
	global $iflist, $config, $g, $fp;

	$iflist = get_interface_list();

	if (is_array($config['vlans']['vlan']) && count($config['vlans']['vlan'])) {

	echo <<<EOD

WARNING: all existing VLANs will be cleared if you proceed!

Do you want to proceed [y|n]?
EOD;

	if (strcasecmp(chop(fgets($fp)), "y") != 0)
		return;
	}

	$config['vlans']['vlan'] = array();
	echo "\n";

	$vlanif = 0;

	while (1) {
		$vlan = array();

		echo "\n\nVLAN Capable interfaces:\n\n";
		if(!is_array($iflist)) {
			echo "No interfaces found!\n";
		} else {
			$vlan_capable=0;
			foreach ($iflist as $iface => $ifa) {
				if (is_jumbo_capable($iface)) {
					echo sprintf("% -8s%s%s\n", $iface, $ifa['mac'],
						$ifa['up'] ? "   (up)" : "");
					$vlan_capable++;
				}
			}
		}

		if($vlan_capable == 0) {
			echo "No VLAN capable interfaces detected.\n";
			return;
		}

		echo "\nEnter the parent interface name for the new VLAN (or nothing if finished): ";
		$vlan['if'] = chop(fgets($fp));

		if ($vlan['if']) {
			if (!array_key_exists($vlan['if'], $iflist) or
			    !is_jumbo_capable($vlan['if'])) {
				echo "\nInvalid interface name '{$vlan['if']}'\n";
				continue;
			}
		} else {
			break;
		}

		echo "Enter the VLAN tag (1-4094): ";
		$vlan['tag'] = chop(fgets($fp));
		$vlan['vlanif'] = "{$vlan['if']}_vlan{$vlan['tag']}";
		if (!is_numericint($vlan['tag']) || ($vlan['tag'] < 1) || ($vlan['tag'] > 4094)) {
			echo "\nInvalid VLAN tag '{$vlan['tag']}'\n";
			continue;
		}
		
		$config['vlans']['vlan'][] = $vlan;
		$vlanif++;
	}
}

function cleanup_backupcache($revisions = 30, $lock = false) {
	global $g;
	$i = false;
	
	if (!$lock)
		$lockkey = lock('config');

	conf_mount_rw();

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

	foreach($bakfiles as $backup) { // Check for backups in the directory not represented in the cache.
		if(filesize($backup) == 0) {
			unlink($backup);
			continue;
		}
		$tocheck = array_shift(explode('.', array_pop(explode('-', $backup))));
		if(!in_array($tocheck, $baktimes)) {
			$i = true;
			if($g['booting'])
				echo ".";
			$newxml = parse_xml_config($backup, $g['xml_rootobj']);
			if($newxml == "-1") {
				log_error("The backup cache file $backup is corrupted.  Unlinking.");
				unlink($backup);
				log_error("The backup cache file $backup is corrupted.  Unlinking.");
				continue;
			}
			if($newxml['revision']['description'] == "")
				$newxml['revision']['description'] = "Unknown";
			$tocache[$tocheck] = array('description' => $newxml['revision']['description']);
		}
	}
	foreach($backups as $checkbak) {
		if(count(preg_grep('/' . $checkbak['time'] . '/i', $bakfiles)) != 0) {
			$newbaks[] = $checkbak;
		} else {
			$i = true;
			if($g['booting']) print " " . $tocheck . "r";
		}
	}
	foreach($newbaks as $todo) $tocache[$todo['time']] = array('description' => $todo['description']);
	if(is_int($revisions) and (count($tocache) > $revisions)) {
		$toslice = array_slice(array_keys($tocache), 0, $revisions);
		foreach($toslice as $sliced)
			$newcache[$sliced] = $tocache[$sliced];
		foreach($tocache as $version => $versioninfo) {
			if(!in_array($version, array_keys($newcache))) {
				unlink_if_exists($g['conf_path'] . '/backup/config-' . $version . '.xml');
				if($g['booting']) print " " . $tocheck . "d";
			}
		}
		$tocache = $newcache;
	}
	$bakout = fopen($g['cf_conf_path'] . '/backup/backup.cache', "w");
	fwrite($bakout, serialize($tocache));
	fclose($bakout);
	conf_mount_ro();

	if($g['booting'] && $i)
		print "done.\n";
	if (!$lock)
		unlock($lockkey);
}

function get_backups() {
	global $g;
	if(file_exists("{$g['cf_conf_path']}/backup/backup.cache")) {
		$confvers = unserialize(file_get_contents("{$g['cf_conf_path']}/backup/backup.cache"));
		$bakvers = array_keys($confvers);
		$toreturn = array();
		sort($bakvers);
		// 	$bakvers = array_reverse($bakvers);
		foreach(array_reverse($bakvers) as $bakver)
			$toreturn[] = array('time' => $bakver, 'description' => $confvers[$bakver]['description']);
	} else {
		return false;
	}
	$toreturn['versions'] = $bakvers;
	return $toreturn;
}

function backup_config() {
	global $config, $g;

	if($g['platform'] == "cdrom")
		return;

	conf_mount_rw();

	/* Create backup directory if needed */
	safe_mkdir("{$g['cf_conf_path']}/backup");

    if($config['revision']['time'] == "") {
            $baktime = 0;
    } else {
            $baktime = $config['revision']['time'];
    }
    if($config['revision']['description'] == "") {
            $bakdesc = "Unknown";
    } else {
            $bakdesc = $config['revision']['description'];
    }
    copy($g['cf_conf_path'] . '/config.xml', $g['cf_conf_path'] . '/backup/config-' . $baktime . '.xml');
    if(file_exists($g['cf_conf_path'] . '/backup/backup.cache')) {
            $backupcache = unserialize(file_get_contents($g['cf_conf_path'] . '/backup/backup.cache'));
    } else {
            $backupcache = array();
    }
    $backupcache[$baktime] = array('description' => $bakdesc);
    $bakout = fopen($g['cf_conf_path'] . '/backup/backup.cache', "w");
    fwrite($bakout, serialize($backupcache));
    fclose($bakout);

	conf_mount_ro();

	return true;
}

function set_device_perms() {
	$devices = array(
		'pf'	=> array(	'user'	=> 'root',
					'group'	=> 'proxy',
					'mode'	=> 0660),
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

?>
