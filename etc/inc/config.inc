<?php
/****h* pfSense/config
 * NAME
 *   config.inc - Functions to manipulate config.xml
 * DESCRIPTION
 *   This include contains various config.xml specific functions.
 * HISTORY
 * $Id$
 ******

	config.inc
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
*/
/*
 * XXX: Hack around the cvs syntax checks. 
 * DISABLE_PHP_LINT_CHECKING
 */
 

if($g['booting']) echo ".";

/* do not load this file twice. */
if($config_inc_loaded == true)
	return;
else
	$config_inc_loaded = true;

/* include globals/utility/XML parser files */
require_once("globals.inc");
if($g['booting']) echo ".";
require_once("util.inc");
if($g['booting']) echo ".";
require_once("pfsense-utils.inc");
if($g['booting']) echo ".";
require_once("xmlparse.inc");
if($g['booting']) echo ".";
require_once("services.inc");

/* read platform */
if($g['booting']) echo ".";
if (file_exists("{$g['etc_path']}/platform")) {
	$g['platform'] = chop(file_get_contents("{$g['etc_path']}/platform"));
} else {
	$g['platform'] = "unknown";
}

/* if /debugging exists, lets set $debugging
   so we can output more information */
if(file_exists("/debugging")) {
	$debugging = true;
	$g['debug'] = true;
}

if($g['booting']) echo ".";
if(file_exists("/cf/conf/config.xml")) {
	$config_contents = file_get_contents("/cf/conf/config.xml");
	if(stristr($config_contents, "<m0n0wall>") == true) {
		if($g['booting']) echo ".";
		/* user has just upgraded to m0n0wall, replace root xml tags */
		log_error("Upgrading m0n0wall configuration to pfSense... ");
		$config_contents = str_replace("m0n0wall","pfsense", $config_contents);
		if (!config_validate("{$g['conf_path']}/config.xml"))
			log_error("ERROR!  Could not convert m0n0wall -> pfsense in config.xml");
		conf_mount_rw();
		$fd = fopen("/cf/conf/config.xml", "w");
		fwrite($fd, $config_contents);
		fclose($fd);
		mwexec("sync");
		conf_mount_ro();
	}
}

/* if our config file exists bail out, we're already set. */
if ($g['booting'] and !file_exists($g['cf_conf_path'] . "/config.xml")  ) {
	if($g['booting']) echo ".";
	/* find the device where config.xml resides and write out an fstab */
	unset($cfgdevice);
	if($g['booting']) echo ".";
	/* check if there's already an fstab (NFS booting?) */
	if (!file_exists("{$g['etc_path']}/fstab")) {
		if($g['booting']) echo ".";
		if (strstr($g['platform'], "cdrom")) {
			/* config is on floppy disk for CD-ROM version */
			$cfgdevice = $cfgpartition = "fd0";
			$dmesg = `dmesg -a`;
			if(ereg("da0", $dmesg) == true) {
				$cfgdevice = $cfgpartition = "da0" ;
				if (mwexec("/sbin/mount -r /dev/{$cfgdevice} /cf")) {
					/* could not mount, fallback to floppy */
					$cfgdevice = $cfgpartition = "fd0";
				}
			}
			$cfgfstype = "msdos";
			echo "CDROM build\n";
			echo "   CFG: {$cfgpartition}\n";
			echo "  TYPE: {$cfgfstype}\n";
		} else {
			if($g['booting']) echo ".";
			/* probe kernel known disks until we find one with config.xml */
			$disks = explode(" ", trim(preg_replace("/kern.disks: /", "", exec("/sbin/sysctl kern.disks"))));
			foreach ($disks as $mountdisk) {
				/* skip mfs mounted filesystems */
				if (strstr($mountdisk, "md"))
					continue;
				if (mwexec("/sbin/mount -r /dev/{$mountdisk}a {$g['cf_path']}") == 0) {
					if (file_exists("{$g['cf_conf_path']}/config.xml")) {
						/* found it */
						$cfgdevice = $mountdisk;
						$cfgpartition = $cfgdevice . "a";
						$cfgfstype = "ufs";
						echo "Found configuration on $cfgdevice.\n";
					}

					mwexec("/sbin/umount -f {$g['cf_path']}");

					if ($cfgdevice)
						break;
				}
				if (mwexec("/sbin/mount -r /dev/{$mountdisk}d {$g['cf_path']}") == 0) {
					if($g['booting']) echo ".";
					if (file_exists("{$g['cf_conf_path']}/config.xml")) {
						/* found it */
						$cfgdevice = $mountdisk;
						$cfgpartition = $cfgdevice . "d";
						$cfgfstype = "ufs";
						echo "Found configuration on $cfgdevice.\n";
					}

					mwexec("/sbin/umount -f {$g['cf_path']}");

					if ($cfgdevice)
						break;
				}
			}
		}
		if($g['booting']) echo ".";
		if (!$cfgdevice) {
			$last_backup = discover_last_backup();
			if($last_backup) {
				log_error("No config.xml found, attempting last known config restore.");
				file_notice("config.xml", "No config.xml found, attempting last known config restore.", "pfSenseConfigurator", "");
				restore_backup("/cf/conf/backup/{$last_backup}");
			} else {
				/* no device found, print an error and die */
				echo <<<EOD

*******************************************************************************
* FATAL ERROR                                                                 *
* The device that contains the configuration file (config.xml) could not be   *
* found. {$g['product_name']} cannot continue booting.                                     *
*******************************************************************************


EOD;

				mwexec("/sbin/halt");
				exit;
			}
		}

		/* write device name to a file for rc.firmware */
		$fd = fopen("{$g['varetc_path']}/cfdevice", "w");
		fwrite($fd, $cfgdevice . "\n");
		fclose($fd);

		/* write out an fstab */
		$fd = fopen("{$g['etc_path']}/fstab", "w");

		$fstab = "/dev/{$cfgpartition} {$g['cf_path']} {$cfgfstype} ro 1 1\n";
		$fstab .= "proc /proc procfs rw 0 0\n";

		fwrite($fd, $fstab);
		fclose($fd);
	}
	if($g['booting']) echo ".";
	/* mount all filesystems */
	mwexec("/sbin/mount -a");
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
	global $g;
	if(filesize("{$g['conf_path']}/config.xml") == 0) {
		$last_backup = discover_last_backup();
		if($last_backup) {
			log_error("No config.xml found, attempting last known config restore.");
			file_notice("config.xml", "No config.xml found, attempting last known config restore.", "pfSenseConfigurator", "");
			restore_backup("{$g['conf_path']}/backup/{$last_backup}");
		} else {
			die("Config.xml is corrupted and is 0 bytes.  Could not restore a previous backup.");
		}
	}
	if($g['booting']) echo ".";
	config_lock();
	if(!$parse) {
		if(file_exists($g['tmp_path'] . '/config.cache')) {
			$config = unserialize(file_get_contents($g['tmp_path'] . '/config.cache'));
			if(is_null($config)) {
				config_unlock();
				parse_config(true);
			}
		} else {
			config_unlock();
			if(!file_exists($g['conf_path'] . "/config.xml")) {
				log_error("No config.xml found, attempting last known config restore.");
				file_notice("config.xml", "No config.xml found, attempting last known config restore.", "pfSenseConfigurator", "");
				$last_backup = discover_last_backup();
				if ($last_backup)
					restore_backup("/cf/conf/backup/{$last_backup}");
				else
					log_error("Could not restore config.xml.");
			}
			$config = parse_config(true);
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
	config_unlock();

	/*    override some global configuration parms if they exist
	 *    instead of hard coding these checks into the codebase
     */
	if($config['pptp']['n_pptp_units'])
		$g['n_pptp_units'] = $config['pptp']['n_pptp_units'];
	if($config['pptp']['pptp_subnet'])
		$g['pptp_subnet'] = $config['pptp']['pptp_subnet'];

	if($config['pppoe']['n_pppoe_units'])
		$g['n_pppoe_units'] = $config['pppoe']['n_pppoe_units'];
	if($config['pppoe']['pppoe_subnet'])
		$g['pppoe_subnet'] = $config['pppoe']['pppoe_subnet'];

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
	config_lock();
	conf_mount_rw();
	$configcache = fopen($g['tmp_path'] . '/config.cache', "w");
	fwrite($configcache, serialize($config));
	fclose($configcache);
	mwexec("sync");
	conf_mount_ro();
	config_unlock();
	return true;
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
	config_lock();
	if(file_exists($file)) {
		conf_mount_rw();
		copy("$file","/cf/conf/config.xml");
		unlink_if_exists("/tmp/config.cache");
		log_error("{$g['product_name']} is restoring the configuration $file");
		file_notice("config.xml", "{$g['product_name']} is restoring the configuration $file", "pfSenseConfigurator", "");
		mwexec("sync");
		conf_mount_ro();
	}
	config_unlock();
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
	if (!$noparseconfig) {
		if (!file_exists("{$g['conf_path']}/config.xml")) {
			config_lock();
			if ($g['booting']) {
				if (strstr($g['platform'], "cdrom")) {
					/* try copying the default config. to the floppy */
					echo "Resetting factory defaults...\n";
					reset_factory_defaults();
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
				config_unlock();
				exit(0);
			}
		}
	}
	if(filesize("{$g['conf_path']}/config.xml") == 0) {
		$last_backup = discover_last_backup();
		if($last_backup) {
			log_error("No config.xml found, attempting last known config restore.");
			file_notice("config.xml", "No config.xml found, attempting last known config restore.", "pfSenseConfigurator", "");
			restore_backup("{$g['conf_path']}/backup/{$last_backup}");
		} else {
			die("Config.xml is corrupted and is 0 bytes.  Could not restore a previous backup.");
		}
	}
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
	config_unlock();
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
	if($g['platform'] == "wrap" or $g['platform'] == "net45xx"
	   or $g['platform'] == "embedded") {
		$status = mwexec("/sbin/mount -u -w /");
		/* we could not mount this correctly.  kick off fsck */
		if($status <> 0) {
			log_error("File system is dirty.  Launching FSCK for /");
			mwexec("/sbin/fsck -y /");
			$status = mwexec("/sbin/mount -u -w /");
		}
	}
}

/****f* config/conf_mount_ro
 * NAME
 *   conf_mount_ro - Mount filesystems readonly.
 * RESULT
 *   null
 ******/
function conf_mount_ro() {
	global $g;

	if($g['booting'] == true)
		return;

	/* firmare upgrade in progress */
	if(file_exists($g['varrun_path'] . "/fwup.enabled"))
		return;

	/* do not umount if generating ssh keys */
	if(file_exists("/tmp/keys_generating"))
		return;

	/* do not umount on cdrom or pfSense platforms */
	if($g['platform'] == "cdrom" or $g['platform'] == "pfSense")
		return;

	/* sync data, then force a remount of /cf */
	mwexec("/bin/sync");
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

	/* convert 1.0 -> 1.1 */
	if ($config['version'] <= 1.0) {
		$opti = 1;
		$ifmap = array('lan' => 'lan', 'wan' => 'wan', 'pptp' => 'pptp');

		/* convert DMZ to optional, if necessary */
		if (isset($config['interfaces']['dmz'])) {

			$dmzcfg = &$config['interfaces']['dmz'];

			if ($dmzcfg['if']) {
				$config['interfaces']['opt' . $opti] = array();
				$optcfg = &$config['interfaces']['opt' . $opti];

				$optcfg['enable'] = $dmzcfg['enable'];
				$optcfg['descr'] = "DMZ";
				$optcfg['if'] = $dmzcfg['if'];
				$optcfg['ipaddr'] = $dmzcfg['ipaddr'];
				$optcfg['subnet'] = $dmzcfg['subnet'];

				$ifmap['dmz'] = "opt" . $opti;
				$opti++;
			}

			unset($config['interfaces']['dmz']);
		}

		/* convert WLAN1/2 to optional, if necessary */
		for ($i = 1; isset($config['interfaces']['wlan' . $i]); $i++) {

			if (!$config['interfaces']['wlan' . $i]['if']) {
				unset($config['interfaces']['wlan' . $i]);
				continue;
			}

			$wlancfg = &$config['interfaces']['wlan' . $i];
			$config['interfaces']['opt' . $opti] = array();
			$optcfg = &$config['interfaces']['opt' . $opti];

			$optcfg['enable'] = $wlancfg['enable'];
			$optcfg['descr'] = "WLAN" . $i;
			$optcfg['if'] = $wlancfg['if'];
			$optcfg['ipaddr'] = $wlancfg['ipaddr'];
			$optcfg['subnet'] = $wlancfg['subnet'];
			$optcfg['bridge'] = $wlancfg['bridge'];

			$optcfg['wireless'] = array();
			$optcfg['wireless']['mode'] = $wlancfg['mode'];
			$optcfg['wireless']['ssid'] = $wlancfg['ssid'];
			$optcfg['wireless']['channel'] = $wlancfg['channel'];
			$optcfg['wireless']['wep'] = $wlancfg['wep'];

			$ifmap['wlan' . $i] = "opt" . $opti;

			unset($config['interfaces']['wlan' . $i]);
			$opti++;
		}

		/* convert filter rules */
		$n = count($config['filter']['rule']);
		for ($i = 0; $i < $n; $i++) {

			$fr = &$config['filter']['rule'][$i];

			/* remap interface */
			if (array_key_exists($fr['interface'], $ifmap))
				$fr['interface'] = $ifmap[$fr['interface']];
			else {
				/* remove the rule */
				echo "\nWarning: filter rule removed " .
					"(interface '{$fr['interface']}' does not exist anymore).";
				unset($config['filter']['rule'][$i]);
				continue;
			}

			/* remap source network */
			if (isset($fr['source']['network'])) {
				if (array_key_exists($fr['source']['network'], $ifmap))
					$fr['source']['network'] = $ifmap[$fr['source']['network']];
				else {
					/* remove the rule */
					echo "\nWarning: filter rule removed " .
						"(source network '{$fr['source']['network']}' does not exist anymore).";
					unset($config['filter']['rule'][$i]);
					continue;
				}
			}

			/* remap destination network */
			if (isset($fr['destination']['network'])) {
				if (array_key_exists($fr['destination']['network'], $ifmap))
					$fr['destination']['network'] = $ifmap[$fr['destination']['network']];
				else {
					/* remove the rule */
					echo "\nWarning: filter rule removed " .
						"(destination network '{$fr['destination']['network']}' does not exist anymore).";
					unset($config['filter']['rule'][$i]);
					continue;
				}
			}
		}

		/* convert shaper rules */
		$n = count($config['pfqueueing']['rule']);
		if (is_array($config['pfqueueing']['rule']))
			for ($i = 0; $i < $n; $i++) {

			$fr = &$config['pfqueueing']['rule'][$i];

			/* remap interface */
			if (array_key_exists($fr['interface'], $ifmap))
				$fr['interface'] = $ifmap[$fr['interface']];
			else {
				/* remove the rule */
				echo "\nWarning: traffic shaper rule removed " .
					"(interface '{$fr['interface']}' does not exist anymore).";
				unset($config['pfqueueing']['rule'][$i]);
				continue;
			}

			/* remap source network */
			if (isset($fr['source']['network'])) {
				if (array_key_exists($fr['source']['network'], $ifmap))
					$fr['source']['network'] = $ifmap[$fr['source']['network']];
				else {
					/* remove the rule */
					echo "\nWarning: traffic shaper rule removed " .
						"(source network '{$fr['source']['network']}' does not exist anymore).";
					unset($config['pfqueueing']['rule'][$i]);
					continue;
				}
			}

			/* remap destination network */
			if (isset($fr['destination']['network'])) {
				if (array_key_exists($fr['destination']['network'], $ifmap))
					$fr['destination']['network'] = $ifmap[$fr['destination']['network']];
				else {
					/* remove the rule */
					echo "\nWarning: traffic shaper rule removed " .
						"(destination network '{$fr['destination']['network']}' does not exist anymore).";
					unset($config['pfqueueing']['rule'][$i]);
					continue;
				}
			}
		}

		$config['version'] = "1.1";
	}

	/* convert 1.1 -> 1.2 */
	if ($config['version'] <= 1.1) {
		/* move LAN DHCP server config */
		$tmp = $config['dhcpd'];
		$config['dhcpd'] = array();
		$config['dhcpd']['lan'] = $tmp;

		/* encrypt password */
		$config['system']['password'] = crypt($config['system']['password']);

		$config['version'] = "1.2";
	}

	/* convert 1.2 -> 1.3 */
	if ($config['version'] <= 1.2) {
		/* convert advanced outbound NAT config */
		for ($i = 0; isset($config['nat']['advancedoutbound']['rule'][$i]); $i++) {
			$curent = &$config['nat']['advancedoutbound']['rule'][$i];
			$src = $curent['source'];
			$curent['source'] = array();
			$curent['source']['network'] = $src;
			$curent['destination'] = array();
			$curent['destination']['any'] = true;
		}

		/* add an explicit type="pass" to all filter rules to make things consistent */
		for ($i = 0; isset($config['filter']['rule'][$i]); $i++) {
			$config['filter']['rule'][$i]['type'] = "pass";
		}

		$config['version'] = "1.3";
	}

	/* convert 1.3 -> 1.4 */
	if ($config['version'] <= 1.3) {
		/* convert shaper rules (make pipes) */
		if (is_array($config['pfqueueing']['rule'])) {
			$config['pfqueueing']['pipe'] = array();

			for ($i = 0; isset($config['pfqueueing']['rule'][$i]); $i++) {
				$curent = &$config['pfqueueing']['rule'][$i];

				/* make new pipe and associate with this rule */
				$newpipe = array();
				$newpipe['descr'] = $curent['descr'];
				$newpipe['bandwidth'] = $curent['bandwidth'];
				$newpipe['delay'] = $curent['delay'];
				$newpipe['mask'] = $curent['mask'];
				$config['pfqueueing']['pipe'][$i] = $newpipe;

				$curent['targetpipe'] = $i;

				unset($curent['bandwidth']);
				unset($curent['delay']);
				unset($curent['mask']);
			}
		}

		$config['version'] = "1.4";
	}

	/* Convert 1.4 -> 1.5 */
	if ($config['version'] <= 1.4) {

		/* Default route moved */
		if (isset($config['interfaces']['wan']['gateway']))
			if ($config['interfaces']['wan']['gateway'] <> "")
				$config['interfaces']['wan']['gateway'] = $config['interfaces']['wan']['gateway'];
		unset($config['interfaces']['wan']['gateway']);

                /* Queues are no longer interface specific */
                if (isset($config['interfaces']['lan']['schedulertype']))
                        unset($config['interfaces']['lan']['schedulertype']);
                if (isset($config['interfaces']['wan']['schedulertype']))
                        unset($config['interfaces']['wan']['schedulertype']);

                for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
                        if(isset($config['interfaces']['opt' . $i]['schedulertype']))
                                unset($config['interfaces']['opt' . $i]['schedulertype']);
                }

		$config['version'] = "1.5";
	}

	/* Convert 1.5 -> 1.6 */
	if ($config['version'] <= 1.5) {
		/* Alternate firmware URL moved */
		if (isset($config['system']['firmwareurl']) && isset($config['system']['firmwarename'])) { // Only convert if *both* are defined.
			$config['system']['alt_firmware_url'] = array();
			$config['system']['alt_firmware_url']['enabled'] = "";
			$config['system']['alt_firmware_url']['firmware_base_url'] = $config['system']['firmwareurl'];
			$config['system']['alt_firmware_url']['firmware_filename'] = $config['system']['firmwarename'];
			unset($config['system']['firmwareurl'], $config['system']['firmwarename']);
		} else {
			unset($config['system']['firmwareurl'], $config['system']['firmwarename']);
		}

		$config['version'] = "1.6";
	}

	/* Convert 1.6 -> 1.7 */
	if ($config['version'] <= 1.6) {
		/* wipe previous shaper configuration */
		unset($config['shaper']['queue']);
		unset($config['shaper']['rule']);
		unset($config['interfaces']['wan']['bandwidth']);
		unset($config['interfaces']['wan']['bandwidthtype']);
		unset($config['interfaces']['lan']['bandwidth']);
		unset($config['interfaces']['lan']['bandwidthtype']);
		$config['shaper']['enable'] = FALSE;
		$config['version'] = "1.7";
	}
	/* Convert 1.7 -> 1.8 */
	if ($config['version'] <= 1.7) {
		if(isset($config['proxyarp']) && is_array($config['proxyarp']['proxyarpnet'])) {
			$proxyarp = &$config['proxyarp']['proxyarpnet'];
			foreach($proxyarp as $arpent){
				$vip = array();
				$vip['mode'] = "proxyarp";
				$vip['interface'] = $arpent['interface'];
				$vip['descr'] = $arpent['descr'];
				if (isset($arpent['range'])) {
					$vip['range'] = $arpent['range'];
					$vip['type'] = "range";
				} else {
					$subnet = explode('/', $arpent['network']);
					$vip['subnet'] = $subnet[0];
					if (isset($subnet[1])) {
						$vip['subnet_bits'] = $subnet[1];
						$vip['type'] = "network";
					} else {
						$vip['subnet_bits'] = "32";
						$vip['type'] = "single";
					}
				}
				$config['virtualip']['vip'][] = $vip;
			}
			unset($config['proxyarp']);
		}
		if(isset($config['installedpackages']) && isset($config['installedpackages']['carp']) && is_array($config['installedpackages']['carp']['config'])) {
			$carp = &$config['installedpackages']['carp']['config'];
			foreach($carp as $carpent){
				$vip = array();
				$vip['mode'] = "carp";
				$vip['interface'] = "AUTO";
				$vip['descr'] = "CARP vhid {$carpent['vhid']}";
				$vip['type'] = "single";
				$vip['vhid'] = $carpent['vhid'];
				$vip['advskew'] = $carpent['advskew'];
				$vip['password'] = $carpent['password'];
				$vip['subnet'] = $carpent['ipaddress'];
				$vip['subnet_bits'] = $carpent['netmask'];
				$config['virtualip']['vip'][] = $vip;
			}
			unset($config['installedpackages']['carp']);
		}
		/* Server NAT is no longer needed */
		unset($config['nat']['servernat']);

		/* enable SSH */
		if ($config['version'] == "1.8") {
			$config['system']['sshenabled'] = true;
		}

		$config['version'] = "1.9";
	}

	/* Convert 1.8 -> 1.9 */
	if ($config['version'] <= 1.8) {
		$config['theme']="metallic";
		$config['version'] = "1.9";
	}
	/* Convert 1.9 -> 2.0 */
	if ($config['version'] <= 1.9) {
		if(is_array($config['ipsec']['tunnel'])) {
			reset($config['ipsec']['tunnel']);
			while (list($index, $tunnel) = each($config['ipsec']['tunnel'])) {
				/* Sanity check on required variables */
				/* This fixes bogus <tunnel> entries - remnant of bug #393 */
				if (!isset($tunnel['local-subnet']) && !isset($tunnel['remote-subnet'])) {
					unset($config['ipsec']['tunnel'][$tunnel]);
				}
			}
        	}
		$config['version'] = "2.0";
	}
	/* Convert 2.0 -> 2.1 */
	if ($config['version'] <= 2.0) {
		/* shaper scheduler moved */
		if(isset($config['system']['schedulertype'])) {
			$config['shaper']['schedulertype'] = $config['system']['schedulertype'];
			unset($config['system']['schedulertype']);
		}
		$config['version'] = "2.1";
	}
	/* Convert 2.1 -> 2.2 */
	if ($config['version'] <= 2.1) {
		/* move gateway to wan interface */
		$config['interfaces']['wan']['gateway'] = $config['system']['gateway'];
		$config['version'] = "2.2";
	}
	/* Convert 2.2 -> 2.3 */
	if ($config['version'] <= 2.2) {
		if(isset($config['shaper'])) {
			/* wipe previous shaper configuration */
			unset($config['shaper']);
		}
		$config['version'] = "2.3";
	}

	/* Convert 2.4 -> 2.5 */
	if ($config['version'] <= 2.4) {
		$config['interfaces']['wan']['use_rrd_gateway'] = $config['system']['use_rrd_gateway'];
		unset($config['system']['use_rrd_gateway']);
 		$config['version'] = "2.5";
	}

	/* Convert 2.5 -> 2.6 */
	if ($config['version'] <= 2.5) {
		$cron_item = array();
		$cron_item['minute'] = "0";
		$cron_item['hour'] = "*";
		$cron_item['mday'] = "*";
		$cron_item['month'] = "*";
		$cron_item['wday'] = "*";
		$cron_item['who'] = "root";
		$cron_item['command'] = "/usr/bin/nice -n20 newsyslog";

		$config['cron']['item'][] = $cron_item;

		$cron_item = array();
		$cron_item['minute'] = "1,31";
		$cron_item['hour'] = "0-5";
		$cron_item['mday'] = "*";
		$cron_item['month'] = "*";
		$cron_item['wday'] = "*";
		$cron_item['who'] = "root";
		$cron_item['command'] = "/usr/bin/nice -n20 adjkerntz -a";

		$config['cron']['item'][] = $cron_item;

		$cron_item = array();
		$cron_item['minute'] = "1";
		$cron_item['hour'] = "*";
		$cron_item['mday'] = "1";
		$cron_item['month'] = "*";
		$cron_item['wday'] = "*";
		$cron_item['who'] = "root";
		$cron_item['command'] = "/usr/bin/nice -n20 /etc/rc.update_bogons.sh";

		$config['cron']['item'][] = $cron_item;

		$cron_item = array();
		$cron_item['minute'] = "*/60";
		$cron_item['hour'] = "*";
		$cron_item['mday'] = "*";
		$cron_item['month'] = "*";
		$cron_item['wday'] = "*";
		$cron_item['who'] = "root";
		$cron_item['command'] = "/usr/bin/nice -n20 /usr/local/sbin/expiretable -v -t 3600 sshlockout";

		$config['cron']['item'][] = $cron_item;

		$cron_item = array();
		$cron_item['minute'] = "1";
		$cron_item['hour'] = "1";
		$cron_item['mday'] = "*";
		$cron_item['month'] = "*";
		$cron_item['wday'] = "*";
		$cron_item['who'] = "root";
		$cron_item['command'] = "/usr/bin/nice -n20 /etc/rc.dyndns.update";

		$config['cron']['item'][] = $cron_item;

		$cron_item = array();
		$cron_item['minute'] = "*/60";
		$cron_item['hour'] = "*";
		$cron_item['mday'] = "*";
		$cron_item['month'] = "*";
		$cron_item['wday'] = "*";
		$cron_item['who'] = "root";
		$cron_item['command'] = "/usr/bin/nice -n20 /usr/local/sbin/expiretable -v -t 3600 virusprot";

		$config['cron']['item'][] = $cron_item;

		$cron_item = array();
		$cron_item['minute'] = "*/60";
		$cron_item['hour'] = "*";
		$cron_item['mday'] = "*";
		$cron_item['month'] = "*";
		$cron_item['wday'] = "*";
		$cron_item['who'] = "root";
		$cron_item['command'] = "/usr/bin/nice -n20 /usr/local/sbin/expiretable -t 1800 snort2c";

		$config['cron']['item'][] = $cron_item;

		$cron_item = array();
		$cron_item['minute'] = "*/5";
		$cron_item['hour'] = "*";
		$cron_item['mday'] = "*";
		$cron_item['month'] = "*";
		$cron_item['wday'] = "*";
		$cron_item['who'] = "root";
		$cron_item['command'] = "/usr/local/bin/checkreload.sh";

		$config['cron']['item'][] = $cron_item;

		/* write crontab entries to file */
		configure_cron();

 		$config['version'] = "2.6";
	}

	/* Convert 2.7 -> 2.8 */
	if ($config['version'] <= 2.7) {
		$founditem = false;
		foreach($config['cron']['item'] as $cronitem) {
			if($cronitem['command'] == "/usr/local/bin/checkreload.sh")
				$founditem = true;
		}
		if($founditem == false) {
			$cron_item = array();
			$cron_item['minute'] = "*/5";
			$cron_item['hour'] = "*";
			$cron_item['mday'] = "*";
			$cron_item['month'] = "*";
			$cron_item['wday'] = "*";
			$cron_item['who'] = "root";
			$cron_item['command'] = "/usr/local/bin/checkreload.sh";
			$config['cron']['item'][] = $cron_item;
		}
		$config['version'] = "2.8";
	}

	/* Convert 2.8 -> 2.9 */
	if ($config['version'] <= 2.8) {
		$rule_item = array();
		$a_filter = &$config['filter']['rule'];
		$rule_item['interface'] = "enc0";
		$rule_item['type'] = "pass";
		$rule_item['source']['any'] = true;
		$rule_item['destination']['any'] = true;
		$rule_item['descr'] = "Permit IPsec traffic.";
		$rule_item['statetype'] = "keep state";
		$a_filter[] = $rule_item;
		$config['version'] = "2.9";
	}

	/* Convert 2.9 -> 3.0 */
	if ($config['version'] <= 2.9) {
		/* enable the rrd config setting by default */
		$config['rrd']['enable'] = true;
		$config['version'] = "3.0";
	}

	/* Convert 3.0 -> 4.0 */
	if ($config['version'] <= 3.9) {
		$config['system']['webgui']['auth_method'] = "session";
		$config['system']['webgui']['backing_method'] = "htpasswd";

		if (isset ($config['system']['username'])) {
			$config['system']['group'] = array();
			$config['system']['group'][0]['name'] = "admins";
			$config['system']['group'][0]['description'] = "System Administrators";
			$config['system']['group'][0]['scope'] = "system";
			$config['system']['group'][0]['pages'] = "ANY";
			$config['system']['group'][0]['home'] = "index.php";
			$config['system']['group'][0]['gid'] = "110";

			$config['system']['user'] = array();
			$config['system']['user'][0]['name'] = "{$config['system']['username']}";
			$config['system']['user'][0]['fullname'] = "System Administrator";
			$config['system']['user'][0]['scope'] = "system";
			$config['system']['user'][0]['groupname'] = "admins";
			$config['system']['user'][0]['password'] = "{$config['system']['password']}";
			$config['system']['user'][0]['uid'] = "0";

			$config['system']['user'][0]['priv'] = array();
			$config['system']['user'][0]['priv'][0]['id'] = "lockwc";
			$config['system']['user'][0]['priv'][0]['name'] = "Lock webConfigurator";
			$config['system']['user'][0]['priv'][0]['descr'] = "Indicates whether this user will lock access to the webConfigurator for other users.";
			$config['system']['user'][0]['priv'][1]['id'] = "lock-ipages";
			$config['system']['user'][0]['priv'][1]['name'] = "Lock individual pages";
			$config['system']['user'][0]['priv'][1]['descr'] = "Indicates whether this user will lock individual HTML pages after having accessed a particular page (the lock will be freed if the user leaves or saves the page form).";
			$config['system']['user'][0]['priv'][2]['id'] = "hasshell";
			$config['system']['user'][0]['priv'][2]['name'] = "Has shell access";
			$config['system']['user'][0]['priv'][2]['descr'] = "Indicates whether this user is able to login for example via SSH.";
			$config['system']['user'][0]['priv'][3]['id'] = "copyfiles";
			$config['system']['user'][0]['priv'][3]['name'] = "Is allowed to copy files";
			$config['system']['user'][0]['priv'][3]['descr'] = "Indicates whether this user is allowed to copy files onto the {$g['product_name']} appliance via SCP/SFTP. If you are going to use this privilege, you must install scponly on the appliance (Hint: pkg_add -r scponly).";
			$config['system']['user'][0]['priv'][4]['id'] = "isroot";
			$config['system']['user'][0]['priv'][4]['name'] = "Is root user";
			$config['system']['user'][0]['priv'][4]['descr'] = "This user is associated with the UNIX root user (you should associate this privilege only with one single user).";

			$config['system']['nextuid'] = "111";
			$config['system']['nextgid'] = "111";

			/* wipe previous auth configuration */
			unset ($config['system']['username']);
			unset ($config['system']['password']);
			
			$config['version'] = "4.0";
		}

	}
		
	/* Convert 4.0 -> 4.1 */
	if ($config['version'] <= 4.0) {
		if(!$config['sysctl']) {

			$config['sysctl']['item'] = array();
	
			$config['sysctl']['item'][0]['tunable'] = "net.inet.tcp.blackhole";
			$config['sysctl']['item'][0]['desc'] =    "Drop packets to closed TCP ports without returning a RST";
			$config['sysctl']['item'][0]['value'] =   "2";
	
			$config['sysctl']['item'][1]['tunable'] = "net.inet.udp.blackhole";
			$config['sysctl']['item'][1]['desc'] =    "Do not send ICMP port unreachable messages for closed UDP ports";
			$config['sysctl']['item'][1]['value'] =   "1";
	
			$config['sysctl']['item'][2]['tunable'] = "net.inet.ip.random_id";
			$config['sysctl']['item'][2]['desc'] =    "Randomize the ID field in IP packets (default is 0: sequential IP IDs)";
			$config['sysctl']['item'][2]['value'] =   "1";
	
			$config['sysctl']['item'][3]['tunable'] = "net.inet.tcp.drop_synfin";
			$config['sysctl']['item'][3]['desc'] =    "Drop SYN-FIN packets (breaks RFC1379, but nobody uses it anyway)";
			$config['sysctl']['item'][3]['value'] =   "1";
	
			$config['sysctl']['item'][4]['tunable'] = "net.inet.ip.redirect";
			$config['sysctl']['item'][4]['desc'] =    "Disable sending IPv4 redirects";
			$config['sysctl']['item'][4]['value'] =   "0";
	
			$config['sysctl']['item'][5]['tunable'] = "net.inet6.ip6.redirect";
			$config['sysctl']['item'][5]['desc'] =    "Disable sending IPv6 redirects";
			$config['sysctl']['item'][5]['value'] =   "0";
	
			$config['sysctl']['item'][6]['tunable'] = "net.inet.tcp.syncookies";
			$config['sysctl']['item'][6]['desc'] =    "Generate SYN cookies for outbound SYN-ACK packets";
			$config['sysctl']['item'][6]['value'] =   "1";
	
			$config['sysctl']['item'][7]['tunable'] = "net.inet.tcp.recvspace";
			$config['sysctl']['item'][7]['desc'] =    "Maximum incoming TCP datagram size";
			$config['sysctl']['item'][7]['value'] =   "65228";
	
			$config['sysctl']['item'][8]['tunable'] = "net.inet.tcp.sendspace";
			$config['sysctl']['item'][8]['desc'] =    "Maximum outgoing TCP datagram size";
			$config['sysctl']['item'][8]['value'] =   "65228";
	
			$config['sysctl']['item'][9]['tunable'] = "net.inet.ip.fastforwarding";
			$config['sysctl']['item'][9]['desc'] =    "Fastforwarding (see http://lists.freebsd.org/pipermail/freebsd-net/2004-January/002534.html)";
			$config['sysctl']['item'][9]['value'] =   "1";
	
			$config['sysctl']['item'][10]['tunable'] = "net.inet.tcp.delayed_ack";
			$config['sysctl']['item'][10]['desc'] =    "Do not delay ACK to try and piggyback it onto a data packet";
			$config['sysctl']['item'][10]['value'] =   "0";
	
			$config['sysctl']['item'][11]['tunable'] = "net.inet.udp.maxdgram";
			$config['sysctl']['item'][11]['desc'] =    "Maximum outgoing UDP datagram size";
			$config['sysctl']['item'][11]['value'] =   "57344";
	
			$config['sysctl']['item'][12]['tunable'] = "net.link.bridge.pfil_onlyip";
			$config['sysctl']['item'][12]['desc'] =    "Handling of non-IP packets which are not passed to pfil (see if_bridge(4))";
			$config['sysctl']['item'][12]['value'] =   "0";
	
			$config['sysctl']['item'][13]['tunable'] = "net.link.tap.user_open";
			$config['sysctl']['item'][13]['desc'] =    "Allow unprivileged access to tap(4) device nodes";
			$config['sysctl']['item'][13]['value'] =   "1";
	
			$config['sysctl']['item'][14]['tunable'] = "kern.rndtest.verbose";
			$config['sysctl']['item'][14]['desc'] =    "Verbosity of the rndtest driver (0: do not display results on console)";
			$config['sysctl']['item'][14]['value'] =   "0";
	
			$config['sysctl']['item'][15]['tunable'] = "kern.randompid";
			$config['sysctl']['item'][15]['desc'] =    "Randomize PID's (see src/sys/kern/kern_fork.c: sysctl_kern_randompid())";
			$config['sysctl']['item'][15]['value'] =   "347";
	
			$config['sysctl']['item'][16]['tunable'] = "net.inet.tcp.inflight.enable";
			$config['sysctl']['item'][16]['desc'] =    "The system will attempt to calculate the bandwidth delay product for each connection and limit the amount of data queued to the network to just the amount required to maintain optimum throughput. ";
			$config['sysctl']['item'][16]['value'] =   "1";

			$config['version'] = "4.1";
		}
	}

	/* Convert 4.1 -> 4.2 */
	if ($config['version'] <= 4.1) {
		if (isset($config['shaper']))
			unset($config['shaper']);
		if (isset($config['ezshaper']))
			unset($config['ezshaper']);
		$config['version'] = "4.2";
	}

	/* Convert 4.2 -> 4.3 */
	if ($config['version'] <= 4.2) {
		/* migrate old interface gateway to the new gateways config */
		$old_gateways = array();
		$gateways = array();
		$i = 0;
		$old_gateways = get_interfaces_with_gateway();
		foreach($old_gateways as $ifname => $interface) {
			if(is_ipaddr($config['interfaces'][$ifname]['gateway'])) {
				$config['gateways'][$i][$ifname]['gateway'] = $config['interfaces'][$ifname]['gateway'];
				$config['gateways'][$i][$ifname]['interface'] = $ifname;
				$config['gateways'][$i][$ifname]['name'] = $ifname ."-". $config['interfaces'][$ifname]['gateway'];
				if(is_ipaddr($config['interfaces'][$ifname]['use_rrd_gateway'])) {
					$config['gateways'][$i][$ifname]['monitor'] = $config['interfaces'][$ifname]['use_rrd_gateway'];
					unset($config['interfaces'][$ifname]['use_rrd_gateway']);
				}
				$config['interfaces'][$ifname]['gateway'] = $config['gateways'][$i][$ifname]['name'];
				$i++;
			}
		}
		$config['version'] = "4.3";
	}

	/* Convert 4.3 -> 4.4 */
	if ($config['version'] <= 4.3) {
		if (isset($config['installedpackages']['openvpnserver']['config'])) {
			$ocfg =& $config['installedpackages']['openvpnserver']['config'];
			if (!isset($config['openvpn']))
				$config['openvpn'] = array();
			if (!isset($config['openvpn']['keys']))
				$config['openvpn']['keys'] = array();
			$ncfg =& $config['openvpn']['keys'];
			foreach ($ocfg as $id => &$cfg) {
				if ($cfg['auth_method'] == 'shared_key') {
					$ncfg["converted{$id}"]['shared.key'] = $cfg['shared_key'];
					$ncfg["converted{$id}"]['existing'] = "yes";
					$ncfg["converted{$id}"]['auth_method'] = "shared_key";
					$cfg['cipher'] = "converted{$id}";
					unset($cfg['shared_key']);
				} else  {
					if (isset($cfg['ca_cert']))  {
						$ncfg["converted{$id}"]['ca.crt'] = $cfg['ca_cert'];
						unset($cfg['ca_cert']);
					}
					if (isset($cfg['server_cert']))  {
						$ncfg["converted{$id}"]['server.crt'] = $cfg['server_cert'];
						unset($cfg['server_cert']);
					}
					if (isset($cfg['server_key']))  {
						$ncfg["converted{$id}"]['server.key'] = $cfg['server_key'];
						unset($cfg['ca_cert']);
					}
					if (isset($cfg['dh_params']))  {
						$ncfg["converted{$id}"]['dh_params.dh'] = $cfg['dh_params'];
						unset($cfg['dh_params']);
					}
					if (isset($cfg['crl']))  {
						$ncfg["converted{$id}"]['crl'] = $cfg['crl'];
						unset($cfg['crl']);
					}
					$ncfg["converted{$id}"]['existing'] = "yes";
					$cfg['cipher'] = "converted{$id}";
				}

			}
		}
		$config['version'] = "4.4";
	}

        /* Convert 4.4 -> 4.5 */
        if ($config['version'] <= 4.4) {
                if (is_array($config['vlans']['vlan']) && count($config['vlans']['vlan'])) {
                        foreach ($config['vlans']['vlan'] as $id => $vlan)
                                $config['vlans']['vlan'][$id]['vlanif'] = "vlan{$id}";
                }
                $config['version'] = "4.5";
        }

	/* Upgrade load balancer from slb to relayd */
        /* Convert 4.5 -> 4.6 */
        if ($config['version'] <= 4.5) {
                if (is_array($config['load_balancer']['virtual_server']) && count($config['load_balancer']['virtual_server'])) {
			$vs_a = &$config['load_balancer']['virtual_server'];
			$pool_a = &$config['load_balancer']['lbpool'];
			$pools = array();
			/* Index pools by name */
			if(is_array($pool_a)) {
				for ($i = 0; isset($pool_a[$i]); $i++) {
					if ($pool_a[$i]['type'] == "server") {
						$pools[$pool_a[$i]['name']] = $pool_a[$i];
					}
				}
			}
			/* Convert sitedown entries to pools and re-attach */
			for ($i = 0; isset($vs_a[$i]); $i++) {
				if (isset($vs_a[$i]['sitedown'])) {
					$pool = array();
					$pool['type'] = 'server';
					$pool['behaviour'] = 'balance';
					$pool['name'] = "{$vs_a[$i]['name']}-sitedown";
					$pool['desc'] = "Sitedown pool for VS: {$vs_a[$i]['name']}";
					$pool['port'] = $pools[$vs_a[$i]['pool']]['port'];
					$pool['servers'] = array();
					$pool['servers'][] = $vs_a[$i]['sitedown'];
					$pool['monitor'] = $pools[$vs_a[$i]['pool']]['monitor'];
					$pool_a[] = $pool;
					$vs_a[$i]['sitedown'] = $pool['name'];
				}
			}
                }
                $config['version'] = "4.6";
        }

	/* Convert 4.6 -> 4.7 */
	if ($config['version'] <= 4.7) {

		/* Upgrade IPsec from tunnel to phase1/phase2 */

        if(is_array($config['ipsec']['tunnel'])) {

			$a_phase1 = array();
			$a_phase2 = array();
			$ikeid = 0;

			foreach ($config['ipsec']['tunnel'] as $tunnel) {

				unset($ph1ent);
				unset($ph2ent);

				/*
				 *  attempt to locate an enabled phase1
				 *  entry that matches the peer gateway
				 */

				if (!isset($tunnel['disabled'])) {

					$remote_gateway = $tunnel['remote-gateway'];

					foreach ($a_phase1 as $ph1tmp) {
						if ($ph1tmp['remote-gateway'] == $remote_gateway) {
							$ph1ent = $ph1tmp;
							break;
						}
					}
				}

				/* none found, create a new one */

				if (!isset( $ph1ent )) {

					/* build new phase1 entry */

					$ph1ent = array();

					$ph1ent['ikeid'] = ++$ikeid;

					if (isset($tunnel['disabled']))
						$ph1ent['disabled'] = $tunnel['disabled'];

					$ph1ent['interface'] = $tunnel['interface'];
					$ph1ent['remote-gateway'] = $tunnel['remote-gateway'];
					$ph1ent['descr'] = $tunnel['descr'];

					$ph1ent['mode'] = $tunnel['p1']['mode'];

					if (isset($tunnel['p1']['myident']['myaddress']))
						$ph1ent['myid_type'] = "myaddress";
					if (isset($tunnel['p1']['myident']['address'])) {
						$ph1ent['myid_type'] = "address";
						$ph1ent['myid_data'] = $tunnel['p1']['myident']['address'];
					}
					if (isset($tunnel['p1']['myident']['fqdn'])) {
						$ph1ent['myid_type'] = "fqdn";
						$ph1ent['myid_data'] = $tunnel['p1']['myident']['fqdn'];
					}
					if (isset($tunnel['p1']['myident']['user_fqdn'])) {
						$ph1ent['myid_type'] = "user_fqdn";
						$ph1ent['myid_data'] = $tunnel['p1']['myident']['user_fqdn'];
					}
					if (isset($tunnel['p1']['myident']['asn1dn'])) {
						$ph1ent['myid_type'] = "asn1dn";
						$ph1ent['myid_data'] = $tunnel['p1']['myident']['asn1dn'];
					}
					if (isset($tunnel['p1']['myident']['dyn_dns'])) {
						$ph1ent['myid_type'] = "dyn_dns";
						$ph1ent['myid_data'] = $tunnel['p1']['myident']['dyn_dns'];
					}

					$ph1ent['peerid_type'] = "peeraddress";

					switch ($tunnel['p1']['encryption-algorithm']) {
					case "des":
						$ph1alg = array( 'name' => 'des' );
						break;
					case "3des":
						$ph1alg = array( 'name' => '3des' );
						break;
					case "blowfish":
						$ph1alg = array( 'name' => 'blowfish', 'keylen' => '128'  );
						break;
					case "cast128":
						$ph1alg = array( 'name' => 'cast128' );
						break;
					case "rijndael":
						$ph1alg = array( 'name' => 'aes', 'keylen' => '128' );
						break;
					case "rijndael 256":
						$ph1alg = array( 'name' => 'aes', 'keylen' => '256' );
						break;
					}

					$ph1ent['encryption-algorithm'] = $ph1alg;
					$ph1ent['hash-algorithm'] = $tunnel['p1']['hash-algorithm'];
					$ph1ent['dhgroup'] = $tunnel['p1']['dhgroup'];
					$ph1ent['lifetime'] = $tunnel['p1']['lifetime'];
					$ph1ent['authentication_method'] = $tunnel['p1']['authentication_method'];

					if (isset($tunnel['p1']['pre-shared-key']))
						$ph1ent['pre-shared-key'] = $tunnel['p1']['pre-shared-key'];
					if (isset($tunnel['p1']['cert']))
						$ph1ent['cert'] = $tunnel['p1']['cert'];
					if (isset($tunnel['p1']['peercert']))
						$ph1ent['peercert'] = $tunnel['p1']['peercert'];
					if (isset($tunnel['p1']['private-key']))
						$ph1ent['private-key'] = $tunnel['p1']['private-key'];

					if (isset($tunnel['pinghost']['pinghost']))
						$ph1ent['pinghost'] = $tunnel['pinghost'];

					$ph1ent['nat_traversal'] = "on";
					$ph1ent['dpd_enable'] = 1;
					$ph1ent['dpd_delay'] = 10;
					$ph1ent['dpd_maxfail'] = 5;

					$a_phase1[] = $ph1ent;
				}

				/* build new phase2 entry */

				$ph2ent = array();

				$ph2ent['ikeid'] = $ph1ent['ikeid'];

				if (isset($tunnel['disabled']))
					$ph1ent['disabled'] = $tunnel['disabled'];

				$ph2ent['descr'] = "phase2 for ".$tunnel['descr'];

				$type = "lan";
				if ($tunnel['local-subnet']['network'])
					$type = $tunnel['local-subnet']['network'];
				if ($tunnel['local-subnet']['address']) {
					list($address,$netbits) = explode("/",$tunnel['local-subnet']['address']);
					if (is_null($netbits))
						$type = "address";
					else
						$type = "network";
				}
				
				switch ($type) {
					case "address":
						$ph2ent['localid'] = array('type' => $type,'address' => $address);
						break;
					case "network":
						$ph2ent['localid'] = array('type' => $type,'address' => $address,'netbits' => $netbits);
						break;
					default:
						$ph2ent['localid'] = array('type' => $type);
						break;
				}

				list($address,$netbits) = explode("/",$tunnel['remote-subnet']);
				$ph2ent['remoteid'] = array('type' => 'network','address' => $address,'netbits' => $netbits);

				$ph2ent['protocol'] = $tunnel['p2']['protocol'];

				$aes_count = 0;
				foreach( $tunnel['p2']['encryption-algorithm-option'] as $tunalg ) {
					$aes_found = false;
					switch ($tunalg) {
						case "des":
							$ph2alg = array( 'name' => 'des' );
							break;
						case "3des":
							$ph2alg = array( 'name' => '3des' );
							break;
						case "blowfish":
							$ph2alg = array( 'name' => 'blowfish', 'keylen' => 'auto'  );
							break;
						case "cast128":
							$ph2alg = array( 'name' => 'cast128' );
							break;
						case "rijndael":
						case "rijndael 256":
							$ph2alg = array( 'name' => 'aes', 'keylen' => 'auto' );
							$aes_found = true;
							$aes_count++;
							break;
					}

					if( !$aes_found || ($aes_count < 2))
						$ph2ent['encryption-algorithm-option'][] = $ph2alg;
				}

				$ph2ent['hash-algorithm-option'] = $tunnel['p2']['hash-algorithm-option'];
				$ph2ent['pfsgroup'] = $tunnel['p2']['pfsgroup'];
				$ph2ent['lifetime'] = $tunnel['p2']['lifetime'];

				$a_phase2[] = $ph2ent;
			}

			unset($config['ipsec']['tunnel']);
			$config['ipsec']['phase1'] = $a_phase1;
			$config['ipsec']['phase2'] = $a_phase2;
		}

		$config['version'] = "4.7";
	}
	
	/* Convert 4.7 -> 4.8 */
	if ($config['version'] <= 4.7) {
		$config['dyndnses']['dyndns'] = array();
		if (isset($config['dyndns']['enable'])) {
			$tempdyn = array();
			$tempdyn['enable'] = isset($config['dyndns']['enable']);
			$tempdyn['type'] = $config['dyndns']['type'];
			$tempdyn['wildcard'] = isset($config['dyndns']['wildcard']);
			$tempdyn['usernamefld'] = $config['dyndns']['username'];
			$tempdyn['passwordfld'] = $config['dyndns']['password'];
			$tempdyn['host'] = $config['dyndns']['host'];
			$tempdyn['mx'] = $config['dyndns']['mx'];		
			$config['dyndnses']['dyndns'][] = $tempdyn;
			unset($config['dyndns']);
		}		
		$config['dnsupdates']['dnsupdate'] = array();
		if (isset($config['dnsupdate']['enable'])) {
			$pconfig = array();
			$pconfig['dnsupdate_enable'] = isset($config['dnsupdate']['enable']);
			$pconfig['dnsupdate_host'] = $config['dnsupdate']['host'];
			$pconfig['dnsupdate_ttl'] = $config['dnsupdate']['ttl'];
			if (!$pconfig['dnsupdate_ttl'])
				$pconfig['dnsupdate_ttl'] = 60;
			$pconfig['dnsupdate_keydata'] = $config['dnsupdate']['keydata'];
			$pconfig['dnsupdate_keyname'] = $config['dnsupdate']['keyname'];
			$pconfig['dnsupdate_keytype'] = $config['dnsupdate']['keytype'];
			if (!$pconfig['dnsupdate_keytype'])
				$pconfig['dnsupdate_keytype'] = "zone";
			$pconfig['dnsupdate_server'] = $config['dnsupdate']['server'];
			$pconfig['dnsupdate_usetcp'] = isset($config['dnsupdate']['usetcp']);
			$config['dnsupdates']['dnsupdate'][] = $pconfig;
			unset($config['dnsupdate']);
		}
	
		if (is_array($config['pppoe'])) {
			$pconfig = array();
			$pconfig['username'] = $config['pppoe']['username'];
			$pconfig['password'] = $config['pppoe']['password'];
			$pconfig['provider'] = $config['pppoe']['provider'];
			$pconfig['ondemand'] = isset($config['pppoe']['ondemand']);
			$pconfig['timeout'] = $config['pppoe']['timeout'];
			unset($config['pppoe']);
			$config['interfaces']['wan']['username'] = $pconfig['username'];
			$config['interfaces']['wan']['password'] = $pconfig['password'];
			$config['interfaces']['wan']['provider'] = $pconfig['provider'];
			$config['interfaces']['wan']['username'] = isset($pconfig['ondemand']);
			$config['interfaces']['wan']['timeout'] = $pconfig['timeout'];
		}
		if (is_array($config['pptp'])) {
			$pconfig = array();
			$pconfig['username'] = $config['pptp']['username'];
			$pconfig['password'] = $config['pptp']['password'];
			$pconfig['provider'] = $config['pptp']['provider'];
			$pconfig['ondemand'] = isset($config['pptp']['ondemand']);
			$pconfig['timeout'] = $config['pptp']['timeout'];
			unset($config['pptp']);
			$config['interfaces']['wan']['username'] = $pconfig['username'];
			$config['interfaces']['wan']['password'] = $pconfig['password'];
			$config['interfaces']['wan']['provider'] = $pconfig['provider'];
			$config['interfaces']['wan']['username'] = isset($pconfig['ondemand'] );
			$config['interfaces']['wan']['timeout'] = $pconfig['timeout'];
		}

		$config['version'] = "4.8";
	}

	/* Convert 4.8 -> 4.9 */
	if ($config['version'] <= 4.8) {

		/* setup new all users group */
		$all = array();
		$all['name'] = "all";
		$all['description'] = "All Users";
		$all['scope'] = "system";
		$all['gid'] = 1998;
		$all['member'] = array();

		if (!is_array($config['system']['group']))
			$config['system']['group'] = array();

		/* work around broken uid assignments */
		$config['system']['nextuid'] = 2000;
		foreach ($config['system']['user'] as & $user) {
			if (isset($user['uid']) && !$user['uid'])
				continue;
			$user['uid'] = $config['system']['nextuid']++;
		}

		/* work around broken gid assignments */
		$config['system']['nextgid'] = 2000;
		foreach ($config['system']['group'] as & $group) {
			if ($group['name'] == $g['admin_group'])
				$group['gid'] = 1999;
			else
				$group['gid'] = $config['system']['nextgid']++;
		}

		/* build group membership information */
		foreach ($config['system']['group'] as & $group) {
			$group['member'] = array();
			foreach ($config['system']['user'] as & $user) {
				$groupnames = explode(",", $user['groupname']);
				if (in_array($group['name'],$groupnames))
					$group['member'][] = $user['uid'];
			}
		}

		/* reset user group information */
		foreach ($config['system']['user'] as & $user) {
			unset($user['groupname']);
			$all['member'][] = $user['uid'];
		}

		/* reset group scope information */
		foreach ($config['system']['group'] as & $group)
			if ($group['name'] != $g['admin_group'])
				$group['scope'] = "user";

		/* insert new all group */
		$groups = Array();
		$groups[] = $all;
		$groups = array_merge($config['system']['group'],$groups);
		$config['system']['group'] = $groups;

		$config['version'] = "4.9";
	}

	/* Convert 4.9 -> 5.0 */
	if ($config['version'] <= 5.0) {
		
		/* update user privileges */
		foreach ($config['system']['user'] as & $user) {
			$privs = array();
			if (!is_array($user['priv'])) {
				unset($user['priv']);
				continue;
			}
			foreach ($user['priv'] as $priv) {
				switch($priv['id']) {
					case "lockwc":
						$privs[] = "user-lock-webcfg";
						break;
					case "lock-ipages":
						$privs[] = "user-lock-ipages";
						break;
					case "hasshell":
						$privs[] = "user-shell-access";
						break;
					case "copyfiles":
						$privs[] = "user-copy-files";
						break;
				}
			}
			$user['priv'] = $privs;
		}

		/* update group privileges */
		foreach ($config['system']['group'] as & $group) {
			$privs = array();
			if (!is_array($group['pages'])) {
				unset($group['pages']);
				continue;
			}
			foreach ($group['pages'] as $page) {
				$priv = map_page_privname($page);
				if ($priv)
					$privs[] = $priv;
			}
			unset($group['pages']);
			$group['priv'] = $privs;
		}

		/* sync all local account information */
		local_sync_accounts();

		$config['version'] = "5.0";
	}

        /* Convert 5.0 -> 5.1 */
        if ($config['version'] <= 5.1) {
		$pconfig = array();
		$pconfig['desc'] = "Set to 0 to disable filtering on the incoming and outgoing member interfaces.";
		$pconfig['tunable'] = "net.link.bridge.pfil_member";
		$pconfig['value'] = "1";
		$config['sysctl']['item'][] = $pconfig;
		$pconfig = array();
                $pconfig['desc'] = "Set to 1 to enable filtering on the bridge interface";
                $pconfig['tunable'] = "net.link.bridge.pfil_bridge";
                $pconfig['value'] = "0";
                $config['sysctl']['item'][] = $pconfig;

		unset($config['bridge']);

		$convert_bridges = false;
		foreach($config['interfaces'] as $intf) {
			if (isset($intf['bridge']) && $intf['bridge'] <> "") {
				$config['bridges'] = array();
				$config['bridges']['bridge'] = array();
				$convert_bridges = true;
				break;
			}
		}
		if ($convert_bridges == true) {
			$i = 0;
			foreach ($config['interfaces'] as $ifr => &$intf) {
				if (isset($intf['bridge']) && $intf['bridge'] <> "") {
					$nbridge = array();
					$nbridge['members'] = "{$ifr},{$intf['bridge']}";
					$nbridge['descr'] = "Converted bridged {$ifr}";
					$nbridge['bridgeif'] = "bridge{$i}";
					$config['bridges']['bridge'][] = $nbridge;
					unset($intf['bridge']);
					$i++;
				}
			}
		}
		$config['version'] = "5.1";
	}

	$now = date("H:i:s");
	log_error("Ended Configuration upgrade at $now");

	if ($prev_version != $config['version'])
		write_config("Upgraded config version level from {$prev_version} to {$config['version']}");
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
		$changetime = time();

	/* Log the running script so it's not entirely unlogged what changed */
    if ($desc == "Unknown")
		$desc = "{$_SERVER['SCRIPT_NAME']} made unknown change";

	$config['revision']['description'] = $desc;
	$config['revision']['time'] = $changetime;

	config_lock();

	/* generate configuration XML */
	$xmlconfig = dump_xml_config($config, $g['xml_rootobj']);

	conf_mount_rw();

	/* write new configuration */
	if (!safe_write_file("{$g['cf_conf_path']}/config.xml", $xmlconfig, false)) {
		die("Unable to open {$g['cf_conf_path']}/config.xml for writing in write_config()\n");
	}

	if($g['platform'] == "embedded") {
		cleanup_backupcache(5);
	} else {
		cleanup_backupcache(30);
	}

	if($g['booting'] <> true) {
		mwexec("sync");
		conf_mount_ro();
	}

	/* re-read configuration */
	$config = parse_xml_config("{$g['conf_path']}/config.xml", $g['xml_rootobj']);

	/* write config cache */
	safe_write_file("{$g['tmp_path']}/config.cache", serialize($config), true);

	/* tell kernel to sync fs data */
	mwexec("/bin/sync");

	config_unlock();

	return $config;
}

/****f* config/reset_factory_defaults
 * NAME
 *   reset_factory_defaults - Reset the system to its default configuration.
 * RESULT
 *   integer	- indicates completion
 ******/
function reset_factory_defaults() {
	global $g;

	config_lock();
	conf_mount_rw();

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

	mwexec("sync");
	conf_mount_ro();
	config_unlock();

	return 0;
}

function config_restore($conffile) {
	global $config, $g;

	if (!file_exists($conffile))
		return 1;

    config_lock();
    conf_mount_rw();

    backup_config();
    copy($conffile, "{$g['cf_conf_path']}/config.xml");
	$config = parse_config(true);
    write_config("Reverted to " . array_pop(explode("/", $conffile)) . ".", false);

	mwexec("sync");
    conf_mount_ro();
    config_unlock();

    return 0;
}

function config_install($conffile) {
	global $config, $g;

	if (!file_exists($conffile))
		return 1;

	if (!config_validate("{$g['conf_path']}/config.xml"))
		return 1;

	if($g['booting'] == true)
		echo "Installing configuration...\n";

    config_lock();
    conf_mount_rw();

    copy($conffile, "{$g['conf_path']}/config.xml");

	/* unlink cache file if it exists */
	if(file_exists("{$g['tmp_path']}/config.cache"))
		unlink("{$g['tmp_path']}/config.cache");

	mwexec("sync");
    conf_mount_ro();
    config_unlock();

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

/*   lock configuration file, decide that the lock file
 *   is stale after 10 seconds
 */
function config_lock($reason = "") {
	global $g, $process_lock;

	/* No need to continue if we're the ones holding the lock */
	if ($process_lock)
		return;

	$lockfile = "{$g['varrun_path']}/config.lock";

	$n = 0;
	while ($n < 10) {
		/* open the lock file in append mode to avoid race condition */
		if ($fd = @fopen($lockfile, "x")) {
			/* succeeded */
			fwrite($fd, $reason);
			$process_lock = true;
			fclose($fd);
			return;
		} else {
			/* file locked, wait and try again */
			$process_lock = false;
			sleep(1);
			$n++;
		}
	}
}

/* unlock configuration file */
function config_unlock() {
	global $g, $process_lock;

	$lockfile = "{$g['varrun_path']}/config.lock";
	$process_lock = false;

	unlink_if_exists($lockfile);
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
		echo "{$g['product_name']} requires *AT LEAST* {$g['minimum_ram_warning_text']} ram to function correctly.\n";
		echo "Only ({$avail}) megs of ram has been detected.\n";
		echo "\nPress ENTER to continue. ";
		fgets($fp);
		echo "\n";
	}

	$iflist = get_interface_list();

	echo <<<EOD

Valid interfaces are:


EOD;

	if(!is_array($iflist)) {
		echo "No interfaces found!\n";
	} else {
		foreach ($iflist as $iface => $ifa) {
			echo sprintf("% -8s%s%s\n", $iface, $ifa['mac'],
				$ifa['up'] ? "   (up)" : "");
		}
	}

	echo <<<EOD

Do you want to set up VLANs first?
If you are not going to use VLANs, or only for optional interfaces, you should
say no here and use the webConfigurator to configure VLANs later, if required.

Do you want to set up VLANs now [y|n]?
EOD;

	if (strcasecmp(chop(fgets($fp)), "y") == 0)
		vlan_setup();

	if (is_array($config['vlans']['vlan']) && count($config['vlans']['vlan'])) {

		echo "\n\nVLAN interfaces:\n\n";
		$i = 0;
		foreach ($config['vlans']['vlan'] as $vlan) {

			echo sprintf("% -8s%s\n", "vlan{$i}",
				"VLAN tag {$vlan['tag']}, interface {$vlan['if']}");

			$iflist['vlan' . $i] = array();
			$i++;
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

	echo "The interfaces will be assigned as follows: \n\n";

	if ($lanif != "")
		echo "LAN  ->" . $lanif . "\n";
	echo "WAN  ->" . $wanif . "\n";
	for ($i = 0; $i < count($optif); $i++) {
		echo "OPT" . ($i+1) . " -> " . $optif[$i] . "\n";
	}

echo <<<EOD

Do you want to proceed [y|n]?
EOD;

	if (strcasecmp(chop(fgets($fp)), "y") == 0) {
		if($lanif) {
			$config['interfaces']['lan']['if'] = $lanif;
		} elseif (!$g['booting']) {

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
			if (!is_array($config['interfaces']['lan']['wireless']))
				$config['interfaces']['lan']['wireless'] = array();
		} else {
			unset($config['interfaces']['lan']['wireless']);
		}

		$config['interfaces']['wan']['if'] = $wanif;
		if (preg_match($g['wireless_regex'], $wanif)) {
			if (!is_array($config['interfaces']['wan']['wireless']))
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

		/* resync everything */
		reload_all_sync();

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

		if (!is_numericint($vlan['tag']) || ($vlan['tag'] < 1) || ($vlan['tag'] > 4094)) {
			echo "\nInvalid VLAN tag '{$vlan['tag']}'\n";
			continue;
		}

		$config['vlans']['vlan'][] = $vlan;
	}
}

function system_start_ftp_helpers() {
	require_once("interfaces.inc");
	global $config, $g;

	mwexec("/usr/bin/killall ftpsesame");

	/* if list */
	$iflist = get_configured_interface_list();

	/* loop through all interfaces and handle ftp-proxy */
	$interface_counter = 0;
	foreach ($iflist as $ifent => $ifname) {
		/* XXX: needed?! */
		if ($ifent == "wan")
			continue;

		/*    if the ftp proxy is disabled for this interface then kill ftp-proxy
		 *    instance and continue. note that the helpers for port forwards are
		 *    launched in a  different sequence so we are filtering them out
	         *    here by not including -c {$port} -g 8021 first.
		 */

		/* Get the ftp queue for this interface */
		if (isset($config['shaper'][$ifname]['ftpqueue']))
			$shaper_queue = $config['interfaces'][$ifname]['ftpqueue'];

		$port = 8021 + $interface_counter;
		if(isset($config['interfaces'][$ifname]['disableftpproxy'])) {
			/*    item is disabled.  lets ++ the interface counter and
			 *    keep processing interfaces. kill ftp-proxy if already
			 *    running for this instance.
			 */
			$helpers = exec("/bin/ps awux | grep \"/usr/local/sbin/ftp-proxy {$shaper_queue} -p {$port}\" | grep -v grep | sed \"s/  */ /g\" | cut -f2 -d\" \"");
			if($helpers)
				mwexec("/bin/kill {$helpers}");
			$interface_counter++;
		} else {
			/* grab the current interface IP address */
			$int = convert_friendly_interface_to_real_interface_name($ifname);
			$ip = find_interface_ip($int);
			/* are we in routed mode? no source nat rules and not a outside interface? */
			/* If we have advanced outbound nat we skip the FTP proxy, we use ftpsesame */
			if((isset($config['nat']['advancedoutbound']['enable'])) && (! interface_has_gateway($ifname))) {
				$sourcenat = 0;
				/* we are using advanced outbound nat, are we in routing mode? */
				/* if the interface address lies within a outbound NAT source network we should skip */
				if(! empty($config['nat']['advancedoutbound']['rule'])) {
					foreach($config['nat']['advancedoutbound']['rule'] as $natnetwork) {
						if(ip_in_subnet($ip, $natnetwork['source']['network'])) {
							/* if the interface address is matched in the AON Rule we need the ftp proxy */
							if(is_ipaddr($natnetwork['target']) && ($natnetwork['interface'] == "wan")) {
								$pftpxsourceaddr = "-a {$natnetwork['target']}";
								if($g['debug'])
									log_error("Config: AON: using the external ip source {$pftpxsourceaddr} for the ftp proxy");
							}
							$sourcenat++;
						}
					}
				}
				if($sourcenat == 0) {
					if($g['debug'])
						log_error("Config: No AON rule matched for interface {$ifname} - not using FTP proxy");
					mwexec("/usr/local/sbin/ftpsesame -i $int");
					$interface_counter++;
					continue;
				} else {
					if($g['debug'])
						log_error("Config: AON rule matched for interface {$ifname} - using FTP proxy");
				}
			}
			/* if ftp-proxy is already running then do not launch it again */
			$helpers = exec("/bin/ps awux | grep \"/usr/local/sbin/ftp-proxy {$shaper_queue} -p {$port}\" | grep -v grep | sed \"s/  */ /g\"");
			if(!$helpers && $ip)
 				mwexec("/usr/local/sbin/ftp-proxy {$shaper_queue} -p {$port} {$pftpxsourceaddr} {$ip}");
			if(!$ip)
				mwexec("/usr/local/sbin/ftpsesame {$shaper_queue} -i $int");
			$interface_counter++;
		}
	}
	/* support bridged interfaces.  even they need ftp mojo */
	$num_bridges = find_number_of_created_bridges();
	$num_bridges++;
	for($x=0; $x<$num_bridges; $x++) {
		mwexec("/usr/local/sbin/ftpsesame {$shaper_queue} -i bridge{$x}");
	}
}

function cleanup_backupcache($revisions = 30) {
	global $g;
	$i = false;
	config_lock();
	if(file_exists($g['cf_conf_path'] . '/backup/backup.cache')) {
		conf_mount_rw();
		$backups = get_backups();
		$newbaks = array();
		$bakfiles = glob($g['cf_conf_path'] . "/backup/config-*");
		$baktimes = $backups['versions'];
		$tocache = array();
		unset($backups['versions']);
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
		mwexec("sync");
		conf_mount_ro();
	}
	if($g['booting']) {
		if($i) {
			print "done.\n";
		}
	}
	config_unlock();
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

	mwexec("sync");
	conf_mount_ro();

	return true;
}

function mute_kernel_msgs() {
	return;
	exec("/sbin/conscontrol mute on");
}

function unmute_kernel_msgs() {
	exec("/sbin/conscontrol mute off");
}

function start_devd() {
	exec("/sbin/devd");
	sleep(1);
	if(file_exists("/tmp/rc.linkup"))
		unlink("/tmp/rc.linkup");
}

function is_interface_mismatch() {
	global $config, $g;
	if(!$config['interfaces']['lan']) 	
		return false;
	if($config['interfaces']['wan']['ipaddr'] == "carpdev-dhcp")
		return false;		
	$lan_if = $config['interfaces']['lan']['if'];
	$wan_if = get_real_wan_interface();
	$do_assign = 0;
	/* we need to ignore the vlan interface checks) */
	if (stristr($lan_if, "vlan") == false and stristr($wan_if, "vlan") == false) {
		if (does_interface_exist($lan_if) == false)
			if($g['minimum_nic_count'] > 1)
				$do_assign = 1;
		if ($config['interfaces']['wan']['ipaddr'] <> "pppoe" && $config['interfaces']['wan']['ipaddr'] <> "pptp" && $do_assign == 0)
			if (does_interface_exist($wan_if) == false)
				$do_assign = 1;
	}
	/* XXX: enumerate OPT interfaces looking for mismatches */
	if (file_exists("{$g['tmp_path']}/assign_complete"))
		return false;
	if ($do_assign == 1)
		return true;
	else
		return false;
}

function set_device_perms() {
	$devices = array(
		'pf'	=> array(	'user'	=> 'proxy',
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

if($g['booting']) echo ".";
$config = parse_config();

?>
