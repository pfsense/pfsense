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
	Copyright (C) 2004 Scott Ullrich
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

/* include globals/utility/XML parser files */
require_once("globals.inc");
require_once("util.inc");
require_once("pfsense-utils.inc");
require_once("xmlparse.inc");

/* read platform */
if (file_exists("{$g['etc_path']}/platform")) {
	$g['platform'] = chop(file_get_contents("{$g['etc_path']}/platform"));
} else {
	$g['platform'] = "unknown";
}

/* if our config file exists bail out, we're already set. */
if ($g['booting'] and !file_exists($g['cf_conf_path'] . "/config.xml")  ) {
	/* find the device where config.xml resides and write out an fstab */
	unset($cfgdevice);

	/* check if there's already an fstab (NFS booting?) */
	if (!file_exists("{$g['etc_path']}/fstab")) {

		if (strstr($g['platform'], "cdrom")) {
			/* config is on floppy disk for CD-ROM version */
			$cfgdevice = $cfgpartition = "fd0";
			$dmesg = `dmesg -a`;
			if(ereg("da0", $desg) == true) {
				$cfgdevice = $cfgpartition = "da0" ;
				if (mwexec("/sbin/mount -r da0 /cf") <> 0) {
					/* could not mount, fallback to floppy */
					$cfgdevice = $cfgpartition = "fd0";
				}
			}
			$cfgfstype = "msdos";
		} else {
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

		if (!$cfgdevice) {
			/* no device found, print an error and die */
			echo <<<EOD


*******************************************************************************
* FATAL ERROR                                                                 *
* The device that contains the configuration file (config.xml) could not be   *
* found. pfSense cannot continue booting.                                     *
*******************************************************************************


EOD;

			mwexec("/sbin/halt");
			exit;
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

	/* mount all filesystems */
	mwexec("/sbin/mount -a");
}

$config = parse_config();

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
			$config = parse_config(true);
		}
	} else {
		$config = parse_xml_config($g['conf_path'] . '/config.xml', $g['xml_rootobj']);
		generate_config_cache($config);
	}
	
	/* ugly hack!  config.cache is really borked.  don't use until its fixed. */
	//$config = parse_xml_config($g['conf_path'] . '/config.xml', $g['xml_rootobj']);

	alias_make_table($config);
	config_unlock();
	return $config;
}

function generate_config_cache($config) {
	global $g;
	$configcache = fopen($g['tmp_path'] . '/config.cache', "w");
	fwrite($configcache, serialize($config));
	fclose($configcache);
	return true;
}

function parse_config_bootup() {
	global $config, $g;
	if (!$noparseconfig) {
		if (!file_exists("{$g['conf_path']}/config.xml")) {
			config_lock();
			if ($g['booting']) {
				if (strstr($g['platform'], "cdrom")) {
					/* try copying the default config. to the floppy */
					reset_factory_defaults();
	
					echo "No XML configuration file found - using factory defaults.\n";
					echo "Make sure that the configuration floppy disk with the conf/config.xml\n";
					echo "file is inserted. If it isn't, your configuration changes will be lost\n";
					echo "on reboot.\n";
				} else {
					echo "XML configuration file not found.  pfSense cannot continue booting.\n";
					mwexec("/sbin/halt");
					exit;
				}
			} else {
				config_unlock();
				exit(0);
			}
		}
	}

	parse_config(true);
	
	if ((float)$config['version'] > (float)$g['latest_config']) {
		echo <<<EOD


*******************************************************************************
* WARNING!                                                                    *
* The current configuration has been created with a newer version of pfSense  *
* than this one! This can lead to serious misbehavior and even security       *
* holes! You are urged to either upgrade to a newer version of pfSense or     *
* revert to the default configuration immediately!                            *
*******************************************************************************


EOD;
		}

	/* make alias table (for faster lookups) */
	alias_make_table($config);
	config_unlock();
}

/* mount flash card read/write */
function conf_mount_rw() {
	global $g;

	/* don't use mount -u anymore
	   (doesn't sync the files properly and /bin/sync won't help either) */
	mwexec("/sbin/umount -f {$g['cf_path']}");
	$status = mwexec("/sbin/mount -w -o noatime {$g['cf_path']}");
	while($status == -1) {
		mwexec("fsck -y");
		$status = mwexec("/sbin/mount -w -o noatime {$g['cf_path']}");
	}
	/* if the platform is soekris or wrap, lets mount the
	   compact flash card. */
	if($g['platform'] == "wrap" or $g['platform'] == "net45xx") {
		mwexec("/sbin/umount -f {$g['embeddedbootupslice']}");
		mwexec("/sbin/mount -w {$g['embeddedbootupslice']}");
	}
}

/* mount flash card read only */
function conf_mount_ro() {
	global $g, $do_not_mount_ro;

	if($g['booting'] == true) return;

	mwexec("/sbin/umount -f {$g['cf_path']}");
	mwexec("/sbin/mount -r {$g['cf_path']}");
	/* if the platform is soekris or wrap, lets unmount the
	   compact flash card. */
	if($g['platform'] == "wrap" or $g['platform'] == "net45xx") {
		mwexec("/sbin/umount -f {$g['embeddedbootupslice']}");
		mwexec("/sbin/mount -r {$g['embeddedbootupslice']}");
	}
}

/* convert configuration, if necessary */
function convert_config() {
	global $config, $pkg_config, $g;

	if ($config['version'] == $g['latest_config'])
		return;		/* already at latest version */

	// Save off config version
	$prev_version = $config['version'];

	/* convert 1.0 -> 1.1 */
	if ($config['version'] == "1.0") {
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
	if ($config['version'] == "1.1") {
		/* move LAN DHCP server config */
		$tmp = $config['dhcpd'];
		$config['dhcpd'] = array();
		$config['dhcpd']['lan'] = $tmp;

		/* encrypt password */
		$config['system']['password'] = crypt($config['system']['password']);

		$config['version'] = "1.2";
	}

	/* convert 1.2 -> 1.3 */
	if ($config['version'] == "1.2") {
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
	if ($config['version'] == "1.3") {
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
	if ($config['version'] == "1.4") {

		/* Default route moved */
		if (isset($config['interfaces']['wan']['gateway']))
			if ($config['interfaces']['wan']['gateway'] <> "")
				$config['system']['gateway'] = $config['interfaces']['wan']['gateway'];
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
	if ($config['version'] == "1.5") {
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

	if ($prev_version != $config['version'])
		write_config("Upgraded config version level from {$prev_version} to {$config['version']}");
}

/* save the system configuration */
function write_config($desc="Unknown", $backup = true) {
	global $config, $g;

	if($backup) backup_config();

	if (time() > mktime(0, 0, 0, 9, 1, 2004))       /* make sure the clock settings are plausible */
                $changetime = time();

	/* Log the running script so it's not entirely unlogged what changed */ 
        if ($desc == "Unknown") 
        	$desc = "{$_SERVER['SCRIPT_NAME']} made unknown change";  

	$config['revision']['description'] = $desc;
	$config['revision']['time'] = $changetime;
	
	config_lock();
	conf_mount_rw();

	/* generate configuration XML */
	$xmlconfig = dump_xml_config($config, $g['xml_rootobj']);

	/* write new configuration */
	$fd = fopen("{$g['cf_conf_path']}/config.xml", "w");
	if (!$fd)
		die("Unable to open {$g['cf_conf_path']}/config.xml for writing in write_config()\n");
	fwrite($fd, $xmlconfig);
	fclose($fd);

	conf_mount_ro();
	config_unlock();

	$config = parse_config(true);
	return $config;
}

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
        
        conf_mount_ro();
        config_unlock();

        return 0;
}



function config_install($conffile) {
        global $config, $g;
        
        if (!file_exists($conffile))
                return 1;
 
        config_lock();
        conf_mount_rw();
        
        copy($conffile, "{$g['conf_path']}/config.xml");
                
        conf_mount_ro();
        config_unlock();

        return 0;
}

/* lock configuration file, decide that the lock file is stale after
   10 seconds */
function config_lock() {
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

	$iflist = get_interface_list();

	echo <<<EOD

Valid interfaces are:


EOD;

	foreach ($iflist as $iface => $ifa) {
		echo sprintf("% -8s%s%s\n", $iface, $ifa['mac'],
			$ifa['up'] ? "   (up)" : "");
	}

	echo <<<EOD

Do you want to set up VLANs first?
If you are not going to use VLANs, or only for optional interfaces, you
should say no here and use the webGUI to configure VLANs later, if required.

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

If you do not know the names of your interfaces, you may choose to use
auto-detection. In that case, disconnect all interfaces before you begin,
and reconnect each one when prompted to do so.

EOD;

	do {
		echo "\nEnter the LAN interface name or 'a' for auto-detection: ";
		$lanif = chop(fgets($fp));
		if ($lanif === "") {
			exit(0);
		}

		if ($lanif === "a")
			$lanif = autodetect_interface("LAN", $fp);
		else if (!array_key_exists($lanif, $iflist)) {
			echo "\nInvalid interface name '{$lanif}'\n";
			unset($lanif);
			continue;
		}
	} while (!$lanif);

	do {
		echo "\nEnter the WAN interface name or 'a' for auto-detection: ";
		$wanif = chop(fgets($fp));
		if ($wanif === "") {
			exit(0);
		}
		if ($wanif === "a")
			$wanif = autodetect_interface("WAN", $fp);
		else if (!array_key_exists($wanif, $iflist)) {
			echo "\nInvalid interface name '{$wanif}'\n";
			unset($wanif);
			continue;
		}
	} while (!$wanif);

	/* optional interfaces */
	$i = 0;
	$optif = array();

	while (1) {
		if ($optif[$i])
			$i++;
		$i1 = $i + 1;
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

	/* check for double assignments */
	$ifarr = array_merge(array($lanif, $wanif), $optif);

	for ($i = 0; $i < (count($ifarr)-1); $i++) {
		for ($j = ($i+1); $j < count($ifarr); $j++) {
			if ($ifarr[$i] == $ifarr[$j]) {
				echo <<<EOD

Error: you cannot assign the same interface name twice!

EOD;

				exit(0);
			}
		}
	}

	echo <<<EOD

The interfaces will be assigned as follows:

LAN  -> {$lanif}
WAN  -> {$wanif}

EOD;

	for ($i = 0; $i < count($optif); $i++) {
		echo "OPT" . ($i+1) . " -> " . $optif[$i] . "\n";
	}

	if(!$noreboot) echo "\npfSense will reboot after saving the changes.\n";

echo <<<EOD

Do you want to proceed [y|n]?
EOD;

	if (strcasecmp(chop(fgets($fp)), "y") == 0) {

		$config['interfaces']['lan']['if'] = $lanif;
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
		
		write_config();
		
		echo <<<EOD



EOD;

		if($noreboot <> true)
			system_reboot_sync();
	}
}

function autodetect_interface($ifname, $fp) {
	$iflist_prev = get_interface_list();
	echo <<<EOD

Connect the {$ifname} interface now and make sure that the link is up.
Then press ENTER to continue.

EOD;
	fgets($fp);
	$iflist = get_interface_list();

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

		echo "\nEnter the parent interface name for the new VLAN (or nothing if finished): ";
		$vlan['if'] = chop(fgets($fp));

		if ($vlan['if']) {
			if (!array_key_exists($vlan['if'], $iflist)) {
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
	global $config, $g;
	if($config['system']['disableftpproxy'] <> "") return;
	$wanif = get_real_wan_interface();
	mwexec("/usr/sbin/inetd -wW -C 60");	
	mwexec("/usr/local/sbin/pftpx -g 8021");
}

function cleanup_backupcache($revisions = "all") {
	global $g;
	$i = false;
	if(file_exists($g['cf_conf_path'] . '/backup/backup.cache')) {
		$backups = get_backups();
		$newbaks = array();
		$bakfiles = glob($g['cf_conf_path'] . "/backup/config-*");
		$baktimes = $backups['versions'];
		$tocache = array();
		unset($backups['versions']);
       		foreach($bakfiles as $backup) { // Check for backups in the directory not represented in the cache.
			$tocheck = array_shift(explode('.', array_pop(explode('-', $backup))));	
                	if(!in_array($tocheck, $baktimes)) {
				$i = true;
				if($bootup) print " " . $tocheck . "a";
				$newxml = parse_xml_config($backup, $g['xml_rootobj']);
				if($newxml['revision']['description'] == "") $newxml['revision']['description'] = "Unknown";
				$tocache[$tocheck] = array('description' => $newxml['revision']['description']);
			}
        	}
		foreach($backups as $checkbak) {
			if(count(preg_grep('/' . $checkbak['time'] . '/i', $bakfiles)) != 0) {
				$newbaks[] = $checkbak;
			} else {
				$i = true;
				if($bootup) print " " . $tocheck . "r";
			}
		}
		foreach($newbaks as $todo) $tocache[$todo['time']] = array('description' => $todo['description']);	
		if(is_integer($revisions)) {
			$toslice = array_slice(array_keys($tocache), 0, $revisions);
			foreach($toslice as $sliced) $newcache[$sliced] = $tocache[$sliced];
			foreach($tocache as $version => $versioninfo) {
				if(!in_array($version, array_keys($newcache))) {
					unlink_if_exists($g['conf_path'] . '/backup/config-' . $version . '.xml');
					if($bootup) print " " . $tocheck . "d";
				}
			}
			$tocache = $newcache;
		}
		$bakout = fopen($g['cf_conf_path'] . '/backup/backup.cache', "w");
        	fwrite($bakout, serialize($tocache));
  	        fclose($bakout);
	}
	if($g['booting']) {
		if($i) {
			print ".\n";
		}
	}
}
  	 
function get_backups() { 	 
	global $g;

        if(file_exists("{$g['cf_conf_path']}/backup/backup.cache")) {
                $confvers = unserialize(file_get_contents("{$g['cf_conf_path']}/backup/backup.cache"));
		$bakvers = array_keys($confvers);
		$toreturn = array();
		sort($bakvers);
		// $bakvers = array_reverse($bakvers);
		foreach(array_reverse($bakvers) as $bakver) $toreturn[] = array('time' => $bakver,
								 'description' => $confvers[$bakver]['description']
								);
        } else { 	 
                return false; 	 
        }
	$toreturn['versions'] = $bakvers;
        return $toreturn;
}

function backup_config() {
	global $config, $g;

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

function mute_kernel_msgs() {
	exec("/sbin/conscontrol mute on");
}

function unmute_kernel_msgs() {
	exec("/sbin/conscontrol mute off");
}

?>
