<?php
/*
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
require_once("xmlparse.inc");

/* read platform */
if (file_exists("{$g['etc_path']}/platform")) {
	$g['platform'] = chop(file_get_contents("{$g['etc_path']}/platform"));
} else {
	$g['platform'] = "unknown";
}

if ($g['booting']) {
	/* find the device where config.xml resides and write out an fstab */
	unset($cfgdevice);

	/* check if there's already an fstab (NFS booting?) */
	if (!file_exists("{$g['etc_path']}/fstab")) {

		if (strstr($g['platform'], "cdrom")) {
			/* config is on floppy disk for CD-ROM version */
			$cfgdevice = $cfgpartition = "fd0";
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

/* parse configuration */
if (!$noparseconfig) {

	config_lock();

	/* see if there's a newer cache file */
	if (file_exists("{$g['tmp_path']}/config.cache") &&
		(filemtime("{$g['tmp_path']}/config.cache") >=
		 filemtime("{$g['conf_path']}/config.xml"))) {

		/* read cache */
		$config = unserialize(file_get_contents("{$g['tmp_path']}/config.cache"));

	} else {

		if (!file_exists("{$g['conf_path']}/config.xml")) {
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

		$config = parse_xml_config("{$g['conf_path']}/config.xml", $g['xml_rootobj']);

		if ((float)$config['version'] > (float)$g['latest_config']) {
			if ($g['booting']) {
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
		}

		/* write config cache */
		$fd = @fopen("{$g['tmp_path']}/config.cache", "wb");
		if ($fd) {
			fwrite($fd, serialize($config));
			fclose($fd);
		}
	}

	config_unlock();

	/* make alias table (for faster lookups) */
	alias_make_table();
}

/* mount flash card read/write */
function conf_mount_rw() {
	global $g;

	/* don't use mount -u anymore
	   (doesn't sync the files properly and /bin/sync won't help either) */
	mwexec("/sbin/umount -f {$g['cf_path']}");
	mwexec("/sbin/mount -w -o noatime {$g['cf_path']}");
}

/* mount flash card read only */
function conf_mount_ro() {
	global $g;

	mwexec("/sbin/umount -f {$g['cf_path']}");
	mwexec("/sbin/mount -r {$g['cf_path']}");
}

/* convert configuration, if necessary */
function convert_config() {
	global $config, $pkg_config, $g;

	if ($config['version'] == $g['latest_config'])
		return;		/* already at latest version */

	if ($g['booting'])
		echo "Converting configuration... ";

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

	write_config();

	if ($g['booting'])
		echo "done\n";
}

/* save the system configuration */
function write_config() {

	global $config, $g;

	config_lock();

	conf_mount_rw();

	if (time() > mktime(0, 0, 0, 9, 1, 2004))	/* make sure the clock settings is plausible */
		$config['lastchange'] = time();

	/* generate configuration XML */
	$xmlconfig = dump_xml_config($config, $g['xml_rootobj']);

	/* write configuration */
	$fd = fopen("{$g['cf_conf_path']}/config.xml", "w");

	if (!$fd)
		die("Unable to open config.xml for writing in write_config()\n");

	fwrite($fd, $xmlconfig);
	fclose($fd);

	conf_mount_ro();

	/* re-read configuration */
	$config = parse_xml_config("{$g['conf_path']}/config.xml", $g['xml_rootobj']);

	/* write config cache */
	$fd = @fopen("{$g['tmp_path']}/config.cache", "wb");
	if ($fd) {
		fwrite($fd, serialize($config));
		fclose($fd);
	}

	config_unlock();
}

function reset_factory_defaults() {

	global $g;

	config_lock();

	conf_mount_rw();

	/* create conf directory, if necessary */
	if (!file_exists("{$g['cf_conf_path']}"))
		@mkdir("{$g['cf_conf_path']}");

	/* clear out /conf */
	$dh = opendir($g['conf_path']);
	while ($filename = readdir($dh)) {
		if (($filename != ".") && ($filename != "..")) {
			unlink($g['conf_path'] . "/" . $filename);
		}
	}
	closedir($dh);

	/* copy default configuration */
	@copy("{$g['conf_default_path']}/config.xml", "{$g['conf_path']}/config.xml");

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

	global $g;

	$lockfile = "{$g['varrun_path']}/config.lock";

	$n = 0;
	while ($n < 10) {
		/* open the lock file in append mode to avoid race condition */
		if ($fd = @fopen($lockfile, "x")) {
			/* succeeded */
			fclose($fd);
			return;
		} else {
			/* file locked, wait and try again */
			sleep(1);
			$n++;
		}
	}
}

/* unlock configuration file */
function config_unlock() {

	global $g;

	$lockfile = "{$g['varrun_path']}/config.lock";

	if (file_exists($lockfile))
		unlink($lockfile);
}

?>
