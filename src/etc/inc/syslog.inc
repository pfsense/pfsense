<?php
/*
 * syslog.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
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

function system_syslogd_fixup_server($server) {
	/* If it's an IPv6 IP alone, encase it in brackets */
	if (is_ipaddrv6($server)) {
		return "[$server]";
	} else {
		return $server;
	}
}

function system_syslogd_get_remote_servers($syslogcfg, $facility = "*.*") {
	// Rather than repeatedly use the same code, use this function to build a list of remote servers.
	$facility .= " ".
	$remote_servers = "";
	$pad_to  = max(strlen($facility), 56);
	$padding = ceil(($pad_to - strlen($facility))/8)+1;
	if (isset($syslogcfg['enable'])) {
		if ($syslogcfg['remoteserver']) {
			$remote_servers .= "{$facility}" . str_repeat("\t", $padding) . "@" . system_syslogd_fixup_server($syslogcfg['remoteserver']) . "\n";
		}
		if ($syslogcfg['remoteserver2']) {
			$remote_servers .= "{$facility}" . str_repeat("\t", $padding) . "@" . system_syslogd_fixup_server($syslogcfg['remoteserver2']) . "\n";
		}
		if ($syslogcfg['remoteserver3']) {
			$remote_servers .= "{$facility}" . str_repeat("\t", $padding) . "@" . system_syslogd_fixup_server($syslogcfg['remoteserver3']) . "\n";
		}
	}
	return $remote_servers;
}

function system_syslogd_get_all_logfilenames() {
	global $config, $g, $system_log_files;

	if ($config['installedpackages']['package']) {
		foreach ($config['installedpackages']['package'] as $package) {
			if (isset($package['logging']['logfilename'])) {
				$system_log_files[] = $package['logging']['logfilename'];
			}
		}
	}

	return $system_log_files;
}

/* For a given log filename prefix, return a list with the filename and
 * rotated copies sorted in a way that utilities such as cat/bzcat/bzgrep will
 * see all log entries in chronological order (e.g. name.log.2 name.log.1 name.log)
 */
function sort_related_log_files($logfile = "/var/log/system.log", $string = true, $escape = false) {
	$related_files = glob("{$logfile}*");
	usort($related_files, "rsort_log_filename");

	if ($escape) {
		$related_files = array_map("escapeshellarg", $related_files);
	}

	if ($string) {
		$related_files = implode(" ", $related_files);
	}

	return $related_files;
}

function rsort_log_filename($a, $b) {
	list($abasename, $anumber, $aext) = explode('.', $a);
	list($bbasename, $bnumber, $bext) = explode('.', $b);
	return ($anumber > $bnumber) ? -1 : 1;
}

function clear_log_file($logfile = "/var/log/system.log", $restart_syslogd = true, $remove_rotated = true) {
	global $config, $g;

	if ($restart_syslogd) {
		if (isvalidpid("{$g['varrun_path']}/syslog.pid")) {
			sigkillbypid("{$g['varrun_path']}/syslog.pid", "KILL");
		}
	}
	exec("/usr/bin/truncate -s 0 " . escapeshellarg($logfile));
	if ($remove_rotated) {
		unlink_if_exists("{$logfile}.*");
	}
	if ($restart_syslogd) {
		system_syslogd_start();
	}
	// Bug #6915
	if ($logfile == "/var/log/resolver.log") {
		services_unbound_configure(true);
	}
}

function clear_all_log_files($restart = false) {
	global $g, $system_log_files;
	if ($restart) {
		if (isvalidpid("{$g['varrun_path']}/syslog.pid")) {
			sigkillbypid("{$g['varrun_path']}/syslog.pid", "KILL");
		}
	}

	foreach (system_syslogd_get_all_logfilenames() as $lfile) {
		clear_log_file("{$g['varlog_path']}/{$lfile}.log", false);
	}

	if ($restart) {
		system_syslogd_start();
		killbyname("dhcpd");
		if (!function_exists('services_dhcpd_configure')) {
			require_once('services.inc');
		}
		services_dhcpd_configure();
		// Bug #6915
		services_unbound_configure(false);
	}
	return;
}

function system_syslogd_start($sighup = false) {
	global $config, $g;
	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_syslogd_start() being called $mt\n";
	}

	mwexec("/etc/rc.d/hostid start");

	$syslogcfg = $config['syslog'];

	if (platform_booting()) {
		echo gettext("Starting syslog...");
	}

	$mainsyslogconf = <<<EOD
# Automatically generated, do not edit!
# Place configuration files in {$g['varetc_path']}/syslog.d
!*\n
include						{$g['varetc_path']}/syslog.d
# /* Manually added files with non-conflicting names will not be automatically removed */

EOD;

	if (!@file_put_contents("{$g['etc_path']}/syslog.conf", $mainsyslogconf)) {
		printf(gettext("Error: cannot open syslog.conf in system_syslogd_start().%s"), "\n");
		unset($syslogconf);
		return 1;
	}
	safe_mkdir("{$g['varetc_path']}/syslog.d");

	$syslogd_extra = "";
	if (isset($syslogcfg)) {
		$separatelogfacilities = array('ntp', 'ntpd', 'ntpdate', 'charon', 'ipsec_starter', 'openvpn', 'poes', 'l2tps', 'hostapd', 'dnsmasq', 'named', 'filterdns', 'unbound', 'dhcpd', 'dhcrelay', 'dhclient', 'dhcp6c', 'dpinger', 'radvd', 'routed', 'zebra', 'ospfd', 'ospf6d', 'bgpd', 'miniupnpd', 'filterlog');
		$syslogconf = "# Automatically generated, do not edit!\n";

		if ($config['installedpackages']['package']) {
			foreach ($config['installedpackages']['package'] as $package) {
				if (isset($package['logging']['facilityname']) && isset($package['logging']['logfilename'])) {
					array_push($separatelogfacilities, $package['logging']['facilityname']);
					if (!is_file($g['varlog_path'].'/'.$package['logging']['logfilename'])) {
						mwexec("/usr/bin/touch {$g['varlog_path']}/{$package['logging']['logfilename']}");
					}
					$pkgsyslogconf = "# Automatically generated for package {$package['name']}. Do not edit.\n";
					$pkgsyslogconf .= "!{$package['logging']['facilityname']}\n*.*\t\t\t\t\t\t {$g['varlog_path']}/{$package['logging']['logfilename']}\n";
					@file_put_contents("{$g['varetc_path']}/syslog.d/" . basename($package['logging']['logfilename']) . ".conf", $pkgsyslogconf);
				}
			}
		}
		$facilitylist = implode(',', array_unique($separatelogfacilities));
		$syslogconf .= "!radvd,routed,zebra,ospfd,ospf6d,bgpd,miniupnpd\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.*								{$g['varlog_path']}/routing.log\n";
		}
		if (isset($syslogcfg['routing'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		$syslogconf .= "!ntp,ntpd,ntpdate\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.*								{$g['varlog_path']}/ntpd.log\n";
		}
		if (isset($syslogcfg['ntpd'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		$syslogconf .= "!ppp\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.*								{$g['varlog_path']}/ppp.log\n";
		}
		if (isset($syslogcfg['ppp'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		$syslogconf .= "!poes\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.*								{$g['varlog_path']}/poes.log\n";
		}
		if (isset($syslogcfg['vpn'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		$syslogconf .= "!l2tps\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.*								{$g['varlog_path']}/l2tps.log\n";
		}
		if (isset($syslogcfg['vpn'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		$syslogconf .= "!charon,ipsec_starter\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.*								{$g['varlog_path']}/ipsec.log\n";
		}
		if (isset($syslogcfg['vpn'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		$syslogconf .= "!openvpn\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.*								{$g['varlog_path']}/openvpn.log\n";
		}
		if (isset($syslogcfg['vpn'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		$syslogconf .= "!dpinger\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.*								{$g['varlog_path']}/gateways.log\n";
		}
		if (isset($syslogcfg['dpinger'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		$syslogconf .= "!dnsmasq,named,filterdns,unbound\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.*								{$g['varlog_path']}/resolver.log\n";
		}
		if (isset($syslogcfg['resolver'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		$syslogconf .= "!dhcpd,dhcrelay,dhclient,dhcp6c,dhcpleases,dhcpleases6\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.*								{$g['varlog_path']}/dhcpd.log\n";
		}
		if (isset($syslogcfg['dhcp'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		$syslogconf .= "!hostapd\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.* 								{$g['varlog_path']}/wireless.log\n";
		}
		if (isset($syslogcfg['hostapd'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		$syslogconf .= "!filterlog\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.* 								{$g['varlog_path']}/filter.log\n";
		}
		if (isset($syslogcfg['filter'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		$syslogconf .= "!-{$facilitylist}\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= <<<EOD
local3.*							{$g['varlog_path']}/vpn.log
local4.*							{$g['varlog_path']}/portalauth.log
local5.*							{$g['varlog_path']}/nginx.log
local7.*							{$g['varlog_path']}/dhcpd.log
*.notice;kern.debug;lpr.info;mail.crit;daemon.none;news.err;local0.none;local3.none;local4.none;local7.none;security.*;auth.info;authpriv.info;daemon.info	{$g['varlog_path']}/system.log
auth.info;authpriv.info 					|exec /usr/local/sbin/sshguard
*.emerg								*

EOD;
		}
		if (isset($syslogcfg['vpn'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "local3.*");
		}
		if (isset($syslogcfg['portalauth'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "local4.*");
		}
		if (isset($syslogcfg['dhcp'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "local7.*");
		}
		if (isset($syslogcfg['system'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.emerg;*.notice;kern.debug;lpr.info;mail.crit;news.err;local0.none;local3.none;local7.none;security.*;auth.info;authpriv.info;daemon.info");
		}
		if (isset($syslogcfg['logall'])) {
			// Make everything mean everything, including facilities excluded above.
			$syslogconf .= "!*\n";
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		if (isset($syslogcfg['zmqserver'])) {
				$syslogconf .= <<<EOD
*.*								^{$syslogcfg['zmqserver']}

EOD;
		}
		/* write syslog config to pfSense.conf */
		if (!@file_put_contents("{$g['varetc_path']}/syslog.d/pfSense.conf", $syslogconf)) {
			printf(gettext("Error: cannot open pfSense.conf in system_syslogd_start().%s"), "\n");
			unset($syslogconf);
			return 1;
		}
		unset($syslogconf);

		$sourceip = "";
		if (!empty($syslogcfg['sourceip'])) {
			if ($syslogcfg['ipproto'] == "ipv6") {
				$ifaddr = is_ipaddr($syslogcfg['sourceip']) ? $syslogcfg['sourceip'] : get_interface_ipv6($syslogcfg['sourceip']);
				if (!is_ipaddr($ifaddr)) {
					$ifaddr = get_interface_ip($syslogcfg['sourceip']);
				}
			} else {
				$ifaddr = is_ipaddr($syslogcfg['sourceip']) ? $syslogcfg['sourceip'] : get_interface_ip($syslogcfg['sourceip']);
				if (!is_ipaddr($ifaddr)) {
					$ifaddr = get_interface_ipv6($syslogcfg['sourceip']);
				}
			}
			if (is_ipaddr($ifaddr)) {
				$sourceip = "-b {$ifaddr}";
			}
		}

		$syslogd_extra = "-f {$g['etc_path']}/syslog.conf {$sourceip}";
	}

	$log_sockets = array("{$g['dhcpd_chroot_path']}/var/run/log");

	if (isset($config['installedpackages']['package'])) {
		foreach ($config['installedpackages']['package'] as $package) {
			if (isset($package['logging']['logsocket']) && $package['logging']['logsocket'] != '' &&
			    !in_array($package['logging']['logsocket'], $log_sockets)) {
				$log_sockets[] = $package['logging']['logsocket'];
			}
		}
	}

	$syslogd_sockets = "";
	foreach ($log_sockets as $log_socket) {
		// Ensure that the log directory exists
		$logpath = dirname($log_socket);
		safe_mkdir($logpath);
		$syslogd_sockets .= " -l {$log_socket}";
	}

	/* Setup log rotation */
	system_log_rotation_setup();

	/* If HUP was requested, but syslogd is not running, restart it instead. */
	if ($sighup && !isvalidpid("{$g['varrun_path']}/syslog.pid")) {
		$sighup = false;
	}

	$sshguard_whitelist = array();
	if (!empty($config['system']['sshguard_whitelist'])) {
		$sshguard_whitelist = explode(' ',
		    $config['system']['sshguard_whitelist']);
	}

	$sshguard_config = array();
	$sshguard_config[] = 'BACKEND="/usr/local/libexec/sshg-fw-pf"' . "\n";
	if (!empty($config['system']['sshguard_threshold'])) {
		$sshguard_config[] = 'THRESHOLD=' .
		    $config['system']['sshguard_threshold'] . "\n";
	}
	if (!empty($config['system']['sshguard_blocktime'])) {
		$sshguard_config[] = 'BLOCK_TIME=' .
		    $config['system']['sshguard_blocktime'] . "\n";
	}
	if (!empty($config['system']['sshguard_detection_time'])) {
		$sshguard_config[] = 'DETECTION_TIME=' .
		    $config['system']['sshguard_detection_time'] . "\n";
	}
	if (!empty($sshguard_whitelist)) {
		@file_put_contents("/usr/local/etc/sshguard.whitelist",
		    implode(PHP_EOL, $sshguard_whitelist));
		$sshguard_config[] =
		    'WHITELIST_FILE=/usr/local/etc/sshguard.whitelist' . "\n";
	} else {
		unlink_if_exists("/usr/local/etc/sshguard.whitelist");
	}
	file_put_contents("/usr/local/etc/sshguard.conf", $sshguard_config);

	if (!$sighup) {
		sigkillbyname("sshguard", "TERM");
		if (isvalidpid("{$g['varrun_path']}/syslog.pid")) {
			sigkillbypid("{$g['varrun_path']}/syslog.pid", "TERM");
			usleep(100000); // syslogd often doesn't respond to a TERM quickly enough for the starting of syslogd below to be successful
		}

		if (isvalidpid("{$g['varrun_path']}/syslog.pid")) {
			// if it still hasn't responded to the TERM, KILL it.
			sigkillbypid("{$g['varrun_path']}/syslog.pid", "KILL");
			usleep(100000);
		}

		$retval = mwexec_bg("/usr/sbin/syslogd -s -c -c {$syslogd_sockets} -P {$g['varrun_path']}/syslog.pid {$syslogd_extra}");
	} else {
		$retval = sigkillbypid("{$g['varrun_path']}/syslog.pid", "HUP");
	}

	if (platform_booting()) {
		echo gettext("done.") . "\n";
	}

	return $retval;
}

function system_log_get_compression() {
	global $config, $g, $system_log_compression_types;
	/* Default is bzip2 */
	if (empty($config['syslog']['logcompressiontype']) ||
	    !array_key_exists($config['syslog']['logcompressiontype'], $system_log_compression_types)) {
		$compressiontype = 'bzip2';
	} else {
		$compressiontype = $config['syslog']['logcompressiontype'];
	}
	return $compressiontype;
}

function system_log_get_cat() {
	global $system_log_compression_types;
	return $system_log_compression_types[system_log_get_compression()]['cat'];
}

/* Setup newsyslog log rotation */
function system_log_rotation_setup() {
	global $config, $g, $system_log_files, $system_log_compression_types;
	$syslogcfg = $config['syslog'];

	$mainnewsyslogconf = <<<EOD
# Automatically generated, do not edit!
# Place configuration files in {$g['varetc_path']}/newsyslog.conf.d
<include> {$g['varetc_path']}/newsyslog.conf.d/*
# /* Manually added files with non-conflicting names will not be automatically removed */

EOD;

	file_put_contents("{$g['etc_path']}/newsyslog.conf", $mainnewsyslogconf);
	safe_mkdir("{$g['varetc_path']}/newsyslog.conf.d");
	$log_size = isset($syslogcfg['logfilesize']) ? $syslogcfg['logfilesize'] : "511488";
	$log_size = ($log_size < (2**32)/2) ? $log_size : "511488";
	$log_size = (int)$log_size/1024;

	$rotatecount = isset($syslogcfg['rotatecount']) ? $syslogcfg['rotatecount'] : 7;

	$compression_flag = $system_log_compression_types[system_log_get_compression()]['flag'];


	if (isset($syslogcfg)) {
		$separatelogfacilities = array('ntp', 'ntpd', 'ntpdate', 'charon', 'ipsec_starter', 'openvpn', 'poes', 'l2tps', 'hostapd', 'dnsmasq', 'named', 'filterdns', 'unbound', 'dhcpd', 'dhcrelay', 'dhclient', 'dhcp6c', 'dpinger', 'radvd', 'routed', 'zebra', 'ospfd', 'ospf6d', 'bgpd', 'miniupnpd', 'filterlog');
		$newsyslogconf = <<<EOD
/var/log/userlog			600	3	{$log_size}	*	B
/var/log/utx.log			644	3	{$log_size}	*	B
/var/log/nginx/error.log		644	{$rotatecount}	{$log_size}	*	{$compression_flag}C /var/run/nginx.pid 30

EOD;

		if ($config['installedpackages']['package']) {
			foreach ($config['installedpackages']['package'] as $package) {

				if (isset($package['logging']['logfilename'])) {

					$pkg_log_mode = isset($package['logging']['logmode']) ? $package['logging']['logmode'] : 600;
					$pkg_rotate_count = isset($package['logging']['rotatecount']) ? (int) $package['logging']['rotatecount'] : $rotatecount;
					$pkg_log_size = isset($package['logging']['logfilesize']) ? (int) $package['logging']['logfilesize'] / 1024: $log_size;
					$pkg_rotate_time = isset($package['logging']['rotatetime']) ? $package['logging']['rotatetime'] : '*';
					$pkg_extra_flags = isset($package['logging']['rotateflags']) ? $package['logging']['rotateflags'] : '';
					$pkg_pidcmd = isset($package['logging']['pidcmd']) ? $package['logging']['pidcmd'] : '';
					$pkg_signal = isset($package['logging']['signal']) ? $package['logging']['signal'] : '';

					$pkgnewsyslogconf = "# Automatically generated for package {$package['name']}. Do not edit.\n";
					$pkgnewsyslogconf .= system_log_rotation_make_line("{$g['varlog_path']}/{$package['logging']['logfilename']}",
												$pkg_log_mode,
												$pkg_rotate_count,
												$pkg_log_size,
												$pkg_rotate_time,
												"{$compression_flag}C{$pkg_extra_flags}",
												$pkg_pidcmd,
												$pkg_signal);
					@file_put_contents("{$g['varetc_path']}/newsyslog.conf.d/" . basename($package['logging']['logfilename']) . ".conf", $pkgnewsyslogconf);
				}
			}
		}

		foreach($system_log_files as $logfile) {
			$local_log_size = isset($syslogcfg[basename($logfile, '.log') . '_settings']['logfilesize']) ? (int) $syslogcfg[basename($logfile, '.log') . '_settings']['logfilesize'] / 1024: $log_size;
			$local_rotate_count = isset($syslogcfg[basename($logfile, '.log') . '_settings']['rotatecount']) ? (int) $syslogcfg[basename($logfile, '.log') . '_settings']['rotatecount'] : $rotatecount;

			$newsyslogconf .= system_log_rotation_make_line("{$g['varlog_path']}/{$logfile}.log", 600, $local_rotate_count, $local_log_size, '*', "{$compression_flag}C");
		}

		if (!@file_put_contents("{$g['varetc_path']}/newsyslog.conf.d/pfSense.conf", $newsyslogconf)) {
			printf(gettext("Error: cannot open pfSense.conf in system_log_rotation_setup().%s"), "\n");
		}
		unset($newsyslogconf);
	}
}

function system_log_rotation_make_line($filename, $mode='644', $count=7, $size=1000, $time='*', $flags='C', $pidcmd = '', $signal = '') {
	/* TODO: Fix default size, flags, etc. */
	$nslline = $filename;
	$nslline .= "			{$mode}";
	$nslline .= "	{$count}";
	$nslline .= "	{$size}";
	$nslline .= "	{$time}";
	$nslline .= "	{$flags}";
	if (!empty($pidcmd)) {
		$nslline .= "	{$pidcmd}";
	}
	if (!empty($signal)) {
		$nslline .= "	{$signal}";
	}
	$nslline .= "\n";
	return $nslline;
}

// This version of dump_log() does not output <td></td> or any other table elements.
function dump_log_no_table($logfile, $tail, $withorig = true, $grepfor = "", $grepinvert = "") {
	global $g, $config;
	$sor = isset($config['syslog']['reverse']) ? "-r" : "";
	$specific_log = basename($logfile, '.log') . '_settings';
	if ($config['syslog'][$specific_log]['cronorder'] == 'forward') $sor = "";
	if ($config['syslog'][$specific_log]['cronorder'] == 'reverse') $sor = "-r";
	$logarr = array();
	$grepline = "  ";
	if (is_array($grepfor)) {
		$invert = '';
		if ((strpos($grepfor[0], '!') === 0)) {
			$grepfor[0] = substr($grepfor[0], 1);
			$invert = '-v';
		}
		$grepline .= " | /usr/bin/egrep {$invert} " . escapeshellarg(implode("|", $grepfor));
	}
	if (is_array($grepinvert)) {
		$grepline .= " | /usr/bin/egrep -v " . escapeshellarg(implode("|", $grepinvert));
	}
	if (is_dir($logfile)) {
		$logarr = array(sprintf(gettext("File %s is a directory."), $logfile));
	} elseif (file_exists($logfile) && filesize($logfile) == 0) {
		$logarr = array(gettext("Log file started."));
	} else {
		exec(system_log_get_cat() . ' ' . sort_related_log_files($logfile, true, true) . "{$grepline} | /usr/bin/tail {$sor} -n " . escapeshellarg($tail), $logarr);
	}
	echo "\n";

	$rows = 0;
	foreach ($logarr as $logent) {
		$rows++;
		$logent = preg_split("/\s+/", $logent, 6);

		if ($withorig) {
				$entry_date_time = htmlspecialchars(join(" ", array_slice($logent, 0, 3)));
				$entry_text = ($logent[3] ==  $config['system']['hostname']) ? "" : $logent[3] . " ";
				$entry_text .= htmlspecialchars($logent[4] . " " . $logent[5]);
				echo "{$entry_date_time}";
				echo " " . "{$entry_text}"	. "\n";
		} else {
				echo htmlspecialchars($logent[5]) . "\n";
		}

	}
	return($rows);
}

function dump_log($logfile, $tail, $withorig = true, $grepfor = "", $grepinvert = "") {
	global $g, $config;
	$sor = isset($config['syslog']['reverse']) ? "-r" : "";
	$specific_log = basename($logfile, '.log') . '_settings';
	if ($config['syslog'][$specific_log]['cronorder'] == 'forward') $sor = "";
	if ($config['syslog'][$specific_log]['cronorder'] == 'reverse') $sor = "-r";
	$logarr = array();
	$grepline = "  ";
	if (is_array($grepfor)) {
		$invert = '';
		if ((strpos($grepfor[0], '!') === 0)) {
			$grepfor[0] = substr($grepfor[0], 1);
			$invert = '-v';
		}
		$grepline .= " | /usr/bin/egrep {$invert} " . escapeshellarg(implode("|", $grepfor));
	}
	if (is_array($grepinvert)) {
		$grepline .= " | /usr/bin/egrep -v " . escapeshellarg(implode("|", $grepinvert));
	}
	if (is_dir($logfile)) {
		$logarr = array(sprintf(gettext("File %s is a directory."), $logfile));
	} elseif (file_exists($logfile) && filesize($logfile) == 0) {
		$logarr = array(gettext("Log file started."));
	} else {
		exec(system_log_get_cat() . ' ' . sort_related_log_files($logfile, true, true) . "{$grepline} | /usr/bin/tail {$sor} -n " . escapeshellarg($tail), $logarr);
	}

	$rows = 0;
	foreach ($logarr as $logent) {
		$rows++;
		$logent = preg_split("/\s+/", $logent, 6);
		echo "<tr>\n";
		if ($withorig) {
			$entry_date_time = htmlspecialchars(join(" ", array_slice($logent, 0, 3)));
			$entry_text = ($logent[3] == $config['system']['hostname']) ? "" : $logent[3] . " ";
			$entry_text .= htmlspecialchars($logent[4] . " " . $logent[5]);
			echo "<td class=\"text-nowrap\">{$entry_date_time}</td>\n";
			echo "<td style=\"word-wrap:break-word; word-break:break-all; white-space:normal\">{$entry_text}</td>\n";
		} else {
				echo "<td>" . htmlspecialchars($logent[5]) . "</td>\n";
		}
		echo "</tr>\n";
	}
	return($rows);
}

function return_log($logfile, $tail, $withorig = true, $grepfor = "", $grepinvert = "", $grepreverse = false) {
	global $g, $config;
	$sor = (isset($config['syslog']['reverse']) || $grepreverse) ? "-r" : "";
	$specific_log = basename($logfile, '.log') . '_settings';
	if (($config['syslog'][$specific_log]['cronorder'] == 'forward') && !$grepreverse) $sor = "";
	if (($config['syslog'][$specific_log]['cronorder'] == 'reverse') ||  $grepreverse) $sor = "-r";
	$logarr = array();
	$grepline = "  ";
	if (is_array($grepfor)) {
		$grepline .= " | /usr/bin/egrep " . escapeshellarg(implode("|", $grepfor));
	}
	if (is_array($grepinvert)) {
		$grepline .= " | /usr/bin/egrep -v " . escapeshellarg(implode("|", $grepinvert));
	}
	exec(system_log_get_cat() . ' ' . sort_related_log_files($logfile, true, true) . "{$grepline} | /usr/bin/tail {$sor} -n " . escapeshellarg($tail), $logarr);
	return($logarr);
}

/* Compatibility stubs for old clog functions until packages catch up.
 * Remove once packages all use the new function names. */
function dump_clog_no_table($logfile, $tail, $withorig = true, $grepfor = "", $grepinvert = "") {
	return dump_log_no_table($logfile, $tail, $withorig, $grepfor, $grepinvert);
}
function dump_clog($logfile, $tail, $withorig = true, $grepfor = "", $grepinvert = "") {
	return dump_log($logfile, $tail, $withorig, $grepfor, $grepinvert);
}
function return_clog($logfile, $tail, $withorig = true, $grepfor = "", $grepinvert = "", $grepreverse = false) {
	return return_log($logfile, $tail, $withorig, $grepfor, $grepinvert, $grepreverse);
}
