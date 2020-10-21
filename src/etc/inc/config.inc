<?php
/*
 * config.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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

if (!function_exists('platform_booting')) {
	require_once('globals.inc');
}

/* do not load this file twice. */
//if (in_array("/etc/inc/config.inc", get_included_files()))
//	return;

// Set the memory limit to 128M on i386.  When someone has something like 500+ tunnels
// the parser needs quite a bit of ram.   Do not remove this line unless you
// know what you are doing.  If in doubt, check with dev@ _/FIRST/_!
if (!$ARCH) {
	$ARCH = php_uname("m");
}

// Set memory limit to 512M on amd64.
if ($ARCH == "amd64") {
	ini_set("memory_limit", "512M");
} else {
	ini_set("memory_limit", "128M");
}

/* include globals from notices.inc /utility/XML parser files */
require_once("notices.inc");
require_once("util.inc");
require_once("Net/IPv6.php");
require_once('config.lib.inc');
if (file_exists("/cf/conf/use_xmlreader")) {
	require_once("xmlreader.inc");
} else {
	require_once("xmlparse.inc");
}
require_once("crypt.inc");

/* if /debugging exists, lets set $debugging
   so we can output more information */
if (file_exists("/debugging")) {
	$debugging = true;
	$g['debug'] = true;
}

if (platform_booting(true) &&
    !file_exists($g['cf_conf_path'] . "/config.xml")) {
	echo ".";
	/* find the device where config.xml resides and write out an fstab */
	unset($cfgdevice);
	echo ".";
	/* check if there's already an fstab (NFS booting?) */
	if (!file_exists("{$g['etc_path']}/fstab")) {
		echo ".";
		/* probe kernel known disks until we find one with config.xml */
		$disks = explode(" ", get_single_sysctl("kern.disks"));
		foreach ($disks as $mountdisk) {
			/* skip mfs mounted filesystems */
			if (strstr($mountdisk, "md")) {
				continue;
			}
			if (mwexec("/sbin/mount -r /dev/{$mountdisk}a {$g['cf_path']}") == 0) {
				if (file_exists("{$g['cf_conf_path']}/config.xml")) {
					/* found it */
					$cfgdevice = $mountdisk;
					$cfgpartition = $cfgdevice . "a";
					$cfgfstype = "ufs";
					printf(gettext('Found configuration on %1$s.%2$s'), $cfgdevice, "\n");
				}

				mwexec("/sbin/umount -f {$g['cf_path']}");

				if ($cfgdevice) {
					break;
				}
			}
			if (mwexec("/sbin/mount -r /dev/{$mountdisk}d {$g['cf_path']}") == 0) {
				if (platform_booting()) {
					echo ".";
				}
				if (file_exists("{$g['cf_conf_path']}/config.xml")) {
					/* found it */
					$cfgdevice = $mountdisk;
					$cfgpartition = $cfgdevice . "d";
					$cfgfstype = "ufs";
					printf(gettext('Found configuration on %1$s.%2$s'), $cfgdevice, "\n");
				}

				mwexec("/sbin/umount -f {$g['cf_path']}");

				if ($cfgdevice) {
					break;
				}
			}
		}
		echo ".";
		if (!$cfgdevice) {
			$last_backup = discover_last_backup();
			if ($last_backup) {
				log_error(gettext("No config.xml found, attempting last known config restore."));
				file_notice("config.xml", gettext("No config.xml found, attempting last known config restore."), "pfSenseConfigurator", "");
				restore_backup("/cf/conf/backup/{$last_backup}");
			} else {
				log_error(gettext("No config.xml or config backups found, resetting to factory defaults."));
				restore_backup('/conf.default/config.xml');
			}
		}

		/* write out an fstab */

		$fstab = "/dev/{$cfgpartition} {$g['cf_path']} {$cfgfstype} ro,noatime 1 1\n";
		$fstab .= "proc /proc procfs rw 0 0\n";
		file_put_contents("{$g['etc_path']}/fstab", $fstab);
	}
	echo ".";
	/* mount all filesystems */
	mwexec("/sbin/mount -a");
	echo ".";
}

$config = parse_config();

/* set timezone */
if (isset($config['system']['timezone']) &&
    !empty($config['system']['timezone'])) {
	$timezone = $config['system']['timezone'];
} elseif (isset($g['default_timezone']) && !empty($g['default_timezone'])) {
	$timezone = $g['default_timezone'];
} else {
	$timezone = "Etc/UTC";
}
date_default_timezone_set("$timezone");

if ($config_parsed == true) {
	/* process packager manager custom rules */
	if (is_dir("/usr/local/pkg/parse_config")) {
		run_plugins("/usr/local/pkg/parse_config/");
	}
}

?>
