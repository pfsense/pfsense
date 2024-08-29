<?php
/*
 * config.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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

if (!function_exists('is_platform_booting')) {
	require_once('globals.inc');
}

/* do not load this file twice. */
//if (in_array("/etc/inc/config.inc", get_included_files()))
//	return;

/* include globals from notices.inc /utility/XML parser files */
require_once("notices.inc");
require_once("util.inc");
require_once("Net/IPv6.php");
require_once('config.lib.inc');
require_once("xmlparse.inc");
require_once("crypt.inc");

// Set the memory limit to 128M on i386.  When someone has something like 500+ tunnels
// the parser needs quite a bit of ram.   Do not remove this line unless you
// know what you are doing.  If in doubt, check with dev@ _/FIRST/_!
if (!$ARCH) {
	$ARCH = php_uname("m");
}

// Set memory limit to 512M on amd64.
ini_set("memory_limit", get_php_default_memory($ARCH) . 'M');

/* if /debugging exists, lets set $debugging
   so we can output more information */
if (file_exists("/debugging")) {
	$debugging = true;
	$g['debug'] = true;
}

if (is_platform_booting() && is_cli_sapi() &&
    !file_exists(g_get('cf_conf_path') . "/config.xml")) {
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
				if (is_platform_booting()) {
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
		if (!$cfgdevice && !config_read_file(true)) {
			log_error(gettext("No config.xml or config backups found, resetting to factory defaults."));
			if (restore_backup('/conf.default/config.xml')) {
				config_read_file();
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

config_read_file(true, true);

/* set timezone */
$timezone = config_get_path('system/timezone');
if (isset($timezone) &&
    !empty($timezone)) {
	// noop
} elseif (isset($g['default_timezone']) && !empty(g_get('default_timezone'))) {
	$timezone = g_get('default_timezone');
} else {
	$timezone = "Etc/UTC";
}
date_default_timezone_set("$timezone");

/* override php memory limit */
$php_memory_limit = config_get_path('system/php_memory_limit', 0);
if (($php_memory_limit >= 128) && ($php_memory_limit <= get_php_max_memory())) {
    ini_set("memory_limit", config_get_path('system/php_memory_limit') . "M");
}

if ($config_parsed == true) {
	/* process packager manager custom rules */
	if (is_dir("/usr/local/pkg/parse_config")) {
		run_plugins("/usr/local/pkg/parse_config/");
	}
}

?>
