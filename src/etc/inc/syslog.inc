<?php
/*
 * syslog.inc
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

require_once('config.inc');

global $buffer_rules_rdr, $buffer_rules_normal;
$buffer_rules_rdr = array();
$buffer_rules_normal = array();

global $syslog_formats;
$syslog_formats = array(
	'rfc3164' => gettext('BSD (RFC 3164, default)'),
	'rfc5424' => gettext('syslog (RFC 5424, with RFC 3339 microsecond-precision timestamps)'),
);

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

function system_syslogd_get_all_logfilenames($add_extension = false) {
	global $config, $g, $system_log_files;

	if ($add_extension) {
		$all_syslog_files = array();
		foreach ($system_log_files as $syslogfile) {
			$all_syslog_files[] = "{$syslogfile}.log";
		}
	} else {
		$all_syslog_files = $system_log_files;
	}

	if ($config['installedpackages']['package']) {
		foreach ($config['installedpackages']['package'] as $package) {
			if (isset($package['logging']['logfilename'])) {
				$all_syslog_files[] = $package['logging']['logfilename'];
			}
		}
	}

	return $all_syslog_files;
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
	global $g, $system_log_files, $system_log_non_syslog_files;
	if ($restart) {
		if (isvalidpid("{$g['varrun_path']}/syslog.pid")) {
			sigkillbypid("{$g['varrun_path']}/syslog.pid", "KILL");
		}
	}

	/* Combine list of syslog and non-syslog log files */
	foreach (array_merge(system_syslogd_get_all_logfilenames(true), $system_log_non_syslog_files) as $lfile) {
		clear_log_file("{$g['varlog_path']}/{$lfile}", false);
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
		$separatelogfacilities = array('ntp', 'ntpd', 'ntpdate', 'charon', 'ipsec_starter', 'openvpn', 'poes', 'l2tps', 'hostapd', 'dnsmasq', 'named', 'filterdns', 'unbound', 'dhcpd', 'dhcrelay', 'dhclient', 'dhcp6c', 'dpinger', 'radvd', 'routed', 'zebra', 'ospfd', 'ospf6d', 'bgpd', 'miniupnpd', 'igmpproxy', 'filterlog');
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

		$syslogconf .= "!*\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "auth.*;authpriv.* 						{$g['varlog_path']}/auth.log\n";
		}
		if (isset($syslogcfg['auth'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		$syslogconf .= "!radvd,routed,zebra,ospfd,ospf6d,bgpd,miniupnpd,igmpproxy\n";
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

		$syslogconf .= "!logportalauth\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.* 								{$g['varlog_path']}/portalauth.log\n";
		}
		if (isset($syslogcfg['portalauth'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		$syslogconf .= "!-{$facilitylist}\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= <<<EOD
local3.*							{$g['varlog_path']}/vpn.log
local5.*							{$g['varlog_path']}/nginx.log
*.notice;kern.debug;lpr.info;mail.crit;daemon.none;news.err;local0.none;local3.none;local4.none;local7.none;security.*;auth.info;authpriv.info;daemon.info	{$g['varlog_path']}/system.log
auth.info;authpriv.info 					|exec /usr/local/sbin/sshguard
*.emerg								*

EOD;
		}
		if (isset($syslogcfg['vpn'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "local3.*");
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

	$syslogd_format = empty($config['syslog']['format']) ? '' : '-O ' . escapeshellarg($config['syslog']['format']);

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

		$retval = mwexec_bg("/usr/sbin/syslogd {$syslogd_format} -s -c -c {$syslogd_sockets} -P {$g['varrun_path']}/syslog.pid {$syslogd_extra}");
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
	$log_size = isset($syslogcfg['logfilesize']) ? $syslogcfg['logfilesize'] : $g['default_log_size'];
	$log_size = ($log_size < (2**32)/2) ? $log_size : $g['default_log_size'];
	$log_size = (int)$log_size/1024;

	$rotatecount = is_numericint($syslogcfg['rotatecount']) ? $syslogcfg['rotatecount'] : 7;

	$compression_flag = $system_log_compression_types[system_log_get_compression()]['flag'];


	if (isset($syslogcfg)) {
		$separatelogfacilities = array('ntp', 'ntpd', 'ntpdate', 'charon', 'ipsec_starter', 'openvpn', 'poes', 'l2tps', 'hostapd', 'dnsmasq', 'named', 'filterdns', 'unbound', 'dhcpd', 'dhcrelay', 'dhclient', 'dhcp6c', 'dpinger', 'radvd', 'routed', 'zebra', 'ospfd', 'ospf6d', 'bgpd', 'miniupnpd', 'igmpproxy', 'filterlog');
		$newsyslogconf = <<<EOD
/var/log/userlog		root:wheel	600	3	{$log_size}	*	B
/var/log/utx.log		root:wheel	644	3	{$log_size}	*	B

EOD;

		if ($config['installedpackages']['package']) {
			foreach ($config['installedpackages']['package'] as $package) {

				if (isset($package['logging']['logfilename'])) {

					$pkg_log_owner = isset($package['logging']['logowner']) ? $package['logging']['logowner'] : 'root:wheel';
					$pkg_log_mode = isset($package['logging']['logmode']) ? $package['logging']['logmode'] : 600;
					$pkg_rotate_count = isset($package['logging']['rotatecount']) ? (int) $package['logging']['rotatecount'] : $rotatecount;
					$pkg_log_size = isset($package['logging']['logfilesize']) ? (int) $package['logging']['logfilesize'] / 1024: $log_size;
					$pkg_rotate_time = isset($package['logging']['rotatetime']) ? $package['logging']['rotatetime'] : '*';
					$pkg_extra_flags = isset($package['logging']['rotateflags']) ? $package['logging']['rotateflags'] : '';
					$pkg_pidcmd = isset($package['logging']['pidcmd']) ? $package['logging']['pidcmd'] : '';
					$pkg_signal = isset($package['logging']['signal']) ? $package['logging']['signal'] : '';

					$pkgnewsyslogconf = "# Automatically generated for package {$package['name']}. Do not edit.\n";
					$pkgnewsyslogconf .= system_log_rotation_make_line("{$g['varlog_path']}/{$package['logging']['logfilename']}",
												$pkg_log_owner,
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

			$newsyslogconf .= system_log_rotation_make_line("{$g['varlog_path']}/{$logfile}.log", 'root:wheel', 600, $local_rotate_count, $local_log_size, '*', "{$compression_flag}C");
		}

		if (!@file_put_contents("{$g['varetc_path']}/newsyslog.conf.d/pfSense.conf", $newsyslogconf)) {
			printf(gettext("Error: cannot open pfSense.conf in system_log_rotation_setup().%s"), "\n");
		}
		unset($newsyslogconf);
	}
}

function system_log_rotation_make_line($filename, $owner = 'root:wheel', $mode='644', $count=7, $size=1000, $time='*', $flags='C', $pidcmd = '', $signal = '') {
	/* TODO: Fix default size, flags, etc. */
	$nslline = $filename;
	$nslline .= "		{$owner}";
	$nslline .= "	{$mode}";
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

function dump_log($logfile, $tail, $withorig = true, $grepfor = "", $grepinvert = "", $format = 'table', $grepreverse = false) {
	global $g, $config;
	$sor = (isset($config['syslog']['reverse']) || $grepreverse) ? "-r" : "";
	$specific_log = basename($logfile, '.log') . '_settings';
	if (($config['syslog'][$specific_log]['cronorder'] == 'forward') && !$grepreverse) $sor = "";
	if (($config['syslog'][$specific_log]['cronorder'] == 'reverse') ||  $grepreverse) $sor = "-r";
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

	if ($format == 'none') {
		return($logarr);
	}

	$rows = 0;
	foreach ($logarr as $logent) {
		$rows++;
		$entry_date_time = "";

		/* Determine log entry content */
		$splitlogent = preg_split("/\s+/", $logent, 10);
		if (strpos($splitlogent[0], '>') === false) {
			/* RFC 3164 Format */
			$syslogformat = 'rfc3164';
		} else {
			/* RFC 5424 Format */
			$syslogformat = 'rfc5424';
		}

		if ($format == 'raw') {
			$entry_text = $logent;
		} elseif ($withorig) {
			if ($syslogformat == 'rfc3164') {
				$entry_date_time = htmlspecialchars(join(" ", array_slice($splitlogent, 0, 3)));
				$entry_text = ($splitlogent[3] == $config['system']['hostname']) ? "" : $splitlogent[3] . " ";
				$entry_text .= implode(' ', array_slice($splitlogent, 4));
			} else {
				$entry_date_time = htmlspecialchars($splitlogent[1]);
				$entry_text = ($splitlogent[2] == "{$config['system']['hostname']}.{$config['system']['domain']}" ) ? '' : $splitlogent[2] . ' ';
				$entry_text .= implode(' ', array_slice($splitlogent, 3));
			}
		} else {
			$entry_text = implode(' ', array_slice($splitlogent, ($syslogformat == 'rfc3164') ? 5 : 9));
		}
		$entry_text = htmlspecialchars($entry_text);

		/* Output content in desired format. */
		switch ($format) {
			case 'notable':
				echo implode(' ', array($entry_date_time, $entry_text)) . "\n";
				break;
			case 'raw':
				$span = 'colspan="2"';
			case 'table':
			default:
				echo "<tr>\n";
				if (!empty($entry_date_time)) {
					echo "	<td class=\"text-nowrap\">{$entry_date_time}</td>\n";
				}
				echo "	<td {$span} style=\"word-wrap:break-word; word-break:break-all; white-space:normal\">{$entry_text}</td>\n";
				echo "</tr>\n";
				break;
		}
	}

	return($rows);
}

/* format filter logs */
function conv_log_filter($logfile, $nentries, $tail = 500, $filtertext = "", $filterinterface = null) {
	global $config, $g, $pattern;

	/* Make sure this is a number before using it in a system call */
	if (!(is_numeric($tail))) {
		return;
	}

	/* Safety belt to ensure we get enough lines for filtering without overloading the parsing code */
	if ($filtertext) {
		$tail = 10000;
	}

	/* Always do a reverse tail, to be sure we're grabbing the 'end' of the log. */
	$logarr = "";

	$logtypecheck = preg_replace('/\.log$/', '', basename($logfile));
	if (in_array($logtypecheck, array('system', 'gateways', 'routing', 'resolver', 'wireless', 'nginx', 'dmesg.boot', 'portalauth', 'dhcpd', 'ipsec', 'ppp', 'openvpn', 'ntpd', 'userlog', 'auth'))) {
		$logfile_type = "system";
	} elseif (in_array($logtypecheck, array('filter'))) {
		$logfile_type = "firewall";
	} elseif (in_array($logtypecheck, array('vpn'))) {
		$logfile_type = "vpn_login";
	} elseif (in_array($logtypecheck, array('poes', 'l2tps'))) {
		$logfile_type = "vpn_service";
	} elseif (in_array($logtypecheck, array('utx'))) {
		$logfile_type = "utx";
	} else {
		$logfile_type = "unknown";
	}

# Common Regular Expression Patterns
	$base_patterns = array(
		'rfc3164' => array('start_pattern' => "^"),
		'rfc5424' => array('start_pattern' => "^<[0-9]{1,3}>[0-9]*\ "),
	);

	/* bsd log date */
	$base_patterns['rfc3164']['date_pattern'] = "\([a-zA-Z]{3}\ +[0-9]{1,2}\ +[0-9]{2}:[0-9]{2}:[0-9]{2}\)";
	/* RFC3339 Extended format date */
	$base_patterns['rfc5424']['date_pattern'] = "\((?:[0-9]+)-(?:0[1-9]|1[012])-(?:0[1-9]|[12][0-9]|3[01])[Tt](?:[01][0-9]|2[0-3]):(?:[0-5][0-9]):(?:[0-5][0-9]|60)(?:\.[0-9]+)?(?:(?:[Zz])|(?:[\+|\-](?:[01][0-9]|2[0-3]):[0-5][0-9]))\)";

	/* hostname only, might not be present */
	$base_patterns['rfc3164']['host_pattern'] = "\(.*?\)";
	/* FQDN, always present */
	$base_patterns['rfc5424']['host_pattern'] = "\(\S+?\)";

	/* name[pid]:, pid may not be present */
	$base_patterns['rfc3164']['process_pattern'] = "\(.*?\)\(?::\ +\)?";
	/* name alone, nothing special */
	$base_patterns['rfc5424']['process_pattern'] = "\(\S+?\)\ ";

	/* pid in brackets, may not be present */
	$base_patterns['rfc3164']['pid_pattern'] = "\(?:\\\[\([0-9:]*\)\\\]\)?:?";
	/* pid (which we want) alone, always present but may be '-', message ID is next, but we ignore it */
	$base_patterns['rfc5424']['pid_pattern'] = "\(\S+?\)\ \S+?\ \S+?";

	/* match anything from here on */
	$base_patterns['rfc3164']['log_message_pattern'] = "\(.*\)";
	$base_patterns['rfc5424']['log_message_pattern'] = "\(.*\)";

	$patterns = array(
		'rfc3164' => $base_patterns['rfc3164']['start_pattern'],
		'rfc5424' => $base_patterns['rfc5424']['start_pattern'],
	);

	# Construct RegEx for specific log file type.
	switch ($logfile_type) {
		case 'firewall':
			$patterns['rfc3164'] = "filterlog\[[0-9]+\]:";
			$patterns['rfc5424'] = "filterlog";
			break;
		case 'system':
		case 'vpn_service':
			$patterns['rfc3164'] .= $base_patterns['rfc3164']['date_pattern'] . "\ +" .
						$base_patterns['rfc3164']['host_pattern'] . "\ +" .
						$base_patterns['rfc3164']['process_pattern'] .
						$base_patterns['rfc3164']['pid_pattern'] . "\ +" .
						$base_patterns['rfc3164']['log_message_pattern'] . "$";
			$patterns['rfc5424'] .= $base_patterns['rfc5424']['date_pattern'] . "\ " .
						$base_patterns['rfc5424']['host_pattern'] . "\ " .
						$base_patterns['rfc5424']['process_pattern'] .
						$base_patterns['rfc5424']['pid_pattern'] . "\ " .
						$base_patterns['rfc5424']['log_message_pattern'] . "$";
			break;
		case 'vpn_login':
			$action_pattern = "\(.*?\)";
			$type_pattern = "\(.*?\)";
			$ip_address_pattern = "\(.*?\)";
			$user_pattern = "\(.*?\)";
			$patterns['rfc3164'] .= $base_patterns['rfc3164']['date_pattern'] . "\ +" .
						$base_patterns['rfc3164']['host_pattern'] . "\ +" .
						$base_patterns['rfc3164']['process_pattern'] . "\ +" .
						"\(.*?\)\,\ *" .
						"\(.*?\)\,\ *" .
						"\(.*?\)\,\ *" .
						"\(.*?\)$";
			$patterns['rfc5424'] .= $base_patterns['rfc5424']['date_pattern'] . "\ " .
						$base_patterns['rfc5424']['host_pattern'] . "\ " .
						$base_patterns['rfc5424']['process_pattern'] .
						"\S+?\ \S+?\ \S+?\ " .
						"\(\S*?\)\,\ *" .
						"\(\S*?\)\,\ *" .
						"\(\S*?\)\,\ *" .
						"\(\S*?\)$";
			break;
		case 'unknown':
			$patterns['rfc3164'] .= $base_patterns['rfc3164']['date_pattern'] . "*\ +" .
						$base_patterns['rfc3164']['log_message_pattern'] . "$";
			$patterns['rfc5424'] .= $base_patterns['rfc5424']['date_pattern'] . "*\ " .
						$base_patterns['rfc5424']['log_message_pattern'] . "$";
			break;
		default:
			$patterns['rfc3164'] .= "\(.*\)$";
			$patterns['rfc5424'] .= "\(.*\)$";
	}

	if ($logfile_type != 'utx') {
		# Get a bunch of log entries.
		exec(system_log_get_cat() . ' ' . sort_related_log_files($logfile, true, true) . " | /usr/bin/tail -r -n {$tail}", $logarr);
	} else {
		$_gb = exec("/usr/bin/last --libxo=json", $rawdata, $rc);
		if ($rc == 0) {
			$logarr = json_decode(implode(" ", $rawdata), JSON_OBJECT_AS_ARRAY);
			$logarr = $logarr['last-information']['last'];
		}
	}

	# Remove escapes and fix up the pattern for preg_match.
	$patterns['rfc3164'] = '/' . str_replace(array('\(', '\)', '\[', '\]'), array('(', ')', '[', ']'), $patterns['rfc3164']) . '/';
	$patterns['rfc5424'] = '/' . str_replace(array('\(', '\)', '\[', '\]'), array('(', ')', '[', ']'), $patterns['rfc5424']) . '/';

	$filterlog = array();
	$counter = 0;

	$filterinterface = strtoupper($filterinterface);
	foreach ($logarr as $logent) {
		if ($counter >= $nentries) {
			break;
		}
		if ($logfile_type != 'utx') {
			$pattern = (substr($logent, 0, 1) == '<') ? $patterns['rfc5424'] : $patterns['rfc3164'];
		}
		switch($logfile_type) {
			case 'firewall':
				$flent = parse_firewall_log_line($logent);
				break;
			case 'system':
				$flent = parse_system_log_line($logent);
				break;
			case 'vpn_login':
				$flent = parse_vpn_login_log_line($logent);
				break;
			case 'vpn_service':
				$flent = parse_vpn_service_log_line($logent);
				break;
			case 'utx':
				$flent = parse_utx_log_line($logent);
				break;
			case 'unknown':
				$flent = parse_unknown_log_line($logent);
				break;
			default:
				$flent = array();
				break;
		}

		if (!$filterinterface || ($filterinterface == $flent['interface'])) {
			if ((($flent != "") && (!is_array($filtertext)) && (match_filter_line($flent, $filtertext))) ||
			    (($flent != "") && (is_array($filtertext)) && (match_filter_field($flent, $filtertext)))) {
				$counter++;
				$filterlog[] = $flent;
			}
		}
	}
	/* Since the lines are in reverse order, flip them around if needed based on the user's preference */
	# First get the "General Logging Options" (global) chronological order setting.  Then apply specific log override if set.
	$reverse = isset($config['syslog']['reverse']);
	$specific_log = basename($logfile, '.log') . '_settings';
	if (isset($config['syslog'][$specific_log]['cronorder']) && ($config['syslog'][$specific_log]['cronorder'] == 'forward')) $reverse = false;
	if (isset($config['syslog'][$specific_log]['cronorder']) && ($config['syslog'][$specific_log]['cronorder'] == 'reverse')) $reverse = true;

	return ($reverse) ? $filterlog : array_reverse($filterlog);
}

function escape_filter_regex($filtertext) {
	/* If the caller (user) has not already put a backslash before a slash, to escape it in the regex, */
	/* then this will do it. Take out any "\/" already there, then turn all ordinary "/" into "\/".    */
	return str_replace('/', '\/', str_replace('\/', '/', $filtertext));
}

function match_filter_line($flent, $filtertext = "") {
	if (!$filtertext) {
		return true;
	}
	$filtertext = escape_filter_regex(str_replace(' ', '\s+', $filtertext));
	return @preg_match("/{$filtertext}/i", implode(" ", array_values($flent)));
}

function match_filter_field($flent, $fields) {
	foreach ($fields as $key => $field) {
		if ($field == "All") {
			continue;
		}
		if ((strpos($field, '!') === 0)) {
			$field = substr($field, 1);
			if (strtolower($key) == 'act') {
				if (in_arrayi($flent[$key], explode(" ", $field))) {
					return false;
				}
			} else {
				$field_regex = escape_filter_regex($field);
				if (@preg_match("/{$field_regex}/i", $flent[$key])) {
					return false;
				}
			}
		} else {
			if (strtolower($key) == 'act') {
				if (!in_arrayi($flent[$key], explode(" ", $field))) {
					return false;
				}
			} else {
				$field_regex = escape_filter_regex($field);
				if (!@preg_match("/{$field_regex}/i", $flent[$key])) {
					return false;
				}
			}
		}
	}
	return true;
}

// Case Insensitive in_array function
function in_arrayi($needle, $haystack) {
	return in_array(strtolower($needle), array_map('strtolower', $haystack));
}

function parse_vpn_login_log_line($line) {
	global $config, $g, $pattern;

	$flent = array();
	$log_split = "";

	if (!preg_match($pattern, $line, $log_split))
		return "";

	list($all, $flent['time'], $flent['host'], $flent['process'], $flent['action'], $flent['type'], $flent['ip_address'], $flent['user']) = $log_split;
	$flent['time'] = str_replace('T', ' ', $flent['time']);

	/* If there is time, action, user, and IP address fields, then the line should be usable/good */
	if (!( (trim($flent['time']) == "") && (trim($flent['action']) == "") && (trim($flent['user']) == "") && (trim($flent['ip_address']) == "") )) {
		return $flent;
	} else {
		if ($g['debug']) {
			log_error(sprintf(gettext("There was a error parsing log entry: %s. Please report to mailing list or forum."), $line));
		}
		return "";
	}
}

function parse_vpn_service_log_line($line) {
	global $config, $g, $pattern;

	$flent = array();
	$log_split = "";

	if (!preg_match($pattern, $line, $log_split))
		return "";

	list($all, $flent['time'], $flent['host'], $flent['type'], $flent['pid'], $flent['message']) = $log_split;
	$flent['time'] = str_replace('T', ' ', $flent['time']);

	/* If there is time, type, and message fields, then the line should be usable/good */
	if (!( (trim($flent['time']) == "") && (trim($flent['type']) == "") && (trim($flent['message']) == "") )) {
		return $flent;
	} else {
		if ($g['debug']) {
			log_error(sprintf(gettext("There was a error parsing log entry: %s. Please report to mailing list or forum."), $line));
		}
		return "";
	}
}

function parse_utx_log_line($line) {
	$flent['time'] = "{$line['login-time']}";
	if ($line['logout-time']) {
		$flent['time'] .= " - {$line['logout-time']}";
	}
	$flent['host'] = $line['from'];
	$flent['pid'] = $line['tty'];
	$flent['message'] = $line['user'];
	if ($line['session-length']) {
		$flent['process'] = $line['session-length'];
	}
	return $flent;
}

function parse_unknown_log_line($line) {
	global $config, $g, $pattern;

	$flent = array();
	$log_split = "";

	if (!preg_match($pattern, $line, $log_split)) {
		return "";
	}

	list($all, $flent['time'], $flent['message']) = $log_split;
	$flent['time'] = str_replace('T', ' ', $flent['time']);

	/* If there is time, and message, fields, then the line should be usable/good */
	if (!((trim($flent['time']) == "") && (trim($flent['message']) == ""))) {
		return $flent;
	} else {
		if ($g['debug']) {
			log_error(sprintf(gettext("There was a error parsing log entry: %s. Please report to mailing list or forum."), $line));
		}
		return "";
	}
}

function parse_system_log_line($line) {
	global $config, $g, $pattern;

	$flent = array();
	$log_split = "";

	if (!preg_match($pattern, $line, $log_split)) {
		return "";
	}

	list($all, $flent['time'], $flent['host'], $flent['process'], $flent['pid'], $flent['message']) = $log_split;
	$flent['time'] = str_replace('T', ' ', $flent['time']);

	/* If there is time, process, and message, fields, then the line should be usable/good */
	if (!((trim($flent['time']) == "") && (trim($flent['process']) == "") && (trim($flent['message']) == ""))) {
		return $flent;
	} else {
		if ($g['debug']) {
			log_error(sprintf(gettext("There was a error parsing log entry: %s. Please report to mailing list or forum."), $line));
		}
		return "";
	}
}

function parse_firewall_log_line($line) {
	global $config, $g;

	$flent = array();
	$log_split = "";

	if (substr($line, 0, 1) == '<') {
		/* RFC 5424 */;
		$pattern = "/^<[0-9]{1,3}>[0-9]*\ (\S+?)\ (\S+?)\ filterlog\ \S+?\ \S+?\ \S+?\ (.*)$/U";
	} else {
		/* RFC 3164 */
		$pattern = "/(.*)\s(.*)\sfilterlog\[[0-9]+\]:\s(.*)$/";
	}

	if (!preg_match($pattern, $line, $log_split)) {
		return "";
	}

	list($all, $flent['time'], $host, $rule) = $log_split;
	$flent['time'] = str_replace('T', ' ', $flent['time']);

	$rule_data = explode(",", $rule);
	$field = 0;

	$flent['rulenum'] = $rule_data[$field++];
	$flent['subrulenum'] = $rule_data[$field++];
	$flent['anchor'] = $rule_data[$field++];
	$flent['tracker'] = $rule_data[$field++];
	$flent['realint'] = $rule_data[$field++];
	$flent['interface'] = convert_real_interface_to_friendly_descr($flent['realint']);
	$flent['reason'] = $rule_data[$field++];
	$flent['act'] = $rule_data[$field++];
	$flent['direction'] = $rule_data[$field++];
	$flent['version'] = $rule_data[$field++];

	if ($flent['version'] == '4' || $flent['version'] == '6') {
		if ($flent['version'] == '4') {
			$flent['tos'] = $rule_data[$field++];
			$flent['ecn'] = $rule_data[$field++];
			$flent['ttl'] = $rule_data[$field++];
			$flent['id'] = $rule_data[$field++];
			$flent['offset'] = $rule_data[$field++];
			$flent['flags'] = $rule_data[$field++];
			$flent['protoid'] = $rule_data[$field++];
			$flent['proto'] = strtoupper($rule_data[$field++]);
		} else {
			$flent['class'] = $rule_data[$field++];
			$flent['flowlabel'] = $rule_data[$field++];
			$flent['hlim'] = $rule_data[$field++];
			$flent['proto'] = $rule_data[$field++];
			$flent['protoid'] = $rule_data[$field++];
		}

		$flent['length'] = $rule_data[$field++];
		$flent['srcip'] = $rule_data[$field++];
		$flent['dstip'] = $rule_data[$field++];

		if ($flent['protoid'] == '6' || $flent['protoid'] == '17') { // TCP or UDP
			$flent['srcport'] = $rule_data[$field++];
			$flent['dstport'] = $rule_data[$field++];

			$flent['src'] = $flent['srcip'] . ':' . $flent['srcport'];
			$flent['dst'] = $flent['dstip'] . ':' . $flent['dstport'];

			$flent['datalen'] = $rule_data[$field++];
			if ($flent['protoid'] == '6') { // TCP
				$flent['tcpflags'] = $rule_data[$field++];
				$flent['seq'] = $rule_data[$field++];
				$flent['ack'] = $rule_data[$field++];
				$flent['window'] = $rule_data[$field++];
				$flent['urg'] = $rule_data[$field++];
				$flent['options'] = explode(";", $rule_data[$field++]);
			}
		} else if ($flent['protoid'] == '1' || $flent['protoid'] == '58') {	// ICMP (IPv4 & IPv6)
			$flent['src'] = $flent['srcip'];
			$flent['dst'] = $flent['dstip'];

			$flent['icmp_type'] = $rule_data[$field++];

			switch ($flent['icmp_type']) {
				case "request":
				case "reply":
					$flent['icmp_id'] = $rule_data[$field++];
					$flent['icmp_seq'] = $rule_data[$field++];
					break;
				case "unreachproto":
					$flent['icmp_dstip'] = $rule_data[$field++];
					$flent['icmp_protoid'] = $rule_data[$field++];
					break;
				case "unreachport":
					$flent['icmp_dstip'] = $rule_data[$field++];
					$flent['icmp_protoid'] = $rule_data[$field++];
					$flent['icmp_port'] = $rule_data[$field++];
					break;
				case "unreach":
				case "timexceed":
				case "paramprob":
				case "redirect":
				case "maskreply":
					$flent['icmp_descr'] = $rule_data[$field++];
					break;
				case "needfrag":
					$flent['icmp_dstip'] = $rule_data[$field++];
					$flent['icmp_mtu'] = $rule_data[$field++];
					break;
				case "tstamp":
					$flent['icmp_id'] = $rule_data[$field++];
					$flent['icmp_seq'] = $rule_data[$field++];
					break;
				case "tstampreply":
					$flent['icmp_id'] = $rule_data[$field++];
					$flent['icmp_seq'] = $rule_data[$field++];
					$flent['icmp_otime'] = $rule_data[$field++];
					$flent['icmp_rtime'] = $rule_data[$field++];
					$flent['icmp_ttime'] = $rule_data[$field++];
					break;
				default :
					$flent['icmp_descr'] = $rule_data[$field++];
					break;
			}

		} else if ($flent['protoid'] == '2') { // IGMP
			$flent['src'] = $flent['srcip'];
			$flent['dst'] = $flent['dstip'];
		} else if ($flent['protoid'] == '112') { // CARP
			$flent['type'] = $rule_data[$field++];
			$flent['ttl'] = $rule_data[$field++];
			$flent['vhid'] = $rule_data[$field++];
			$flent['version'] = $rule_data[$field++];
			$flent['advskew'] = $rule_data[$field++];
			$flent['advbase'] = $rule_data[$field++];
			$flent['src'] = $flent['srcip'];
			$flent['dst'] = $flent['dstip'];
		}
	} else {
		if ($g['debug']) {
			log_error(sprintf(gettext("There was a error parsing rule number: %s. Please report to mailing list or forum."), $flent['rulenum']));
		}
		return "";
	}

	/* If there is a src, a dst, and a time, then the line should be usable/good */
	if (!((trim($flent['src']) == "") || (trim($flent['dst']) == "") || (trim($flent['time']) == ""))) {
		return $flent;
	} else {
		if ($g['debug']) {
			log_error(sprintf(gettext("There was a error parsing rule: %s. Please report to mailing list or forum."), $line));
		}
		return "";
	}
}

function get_port_with_service($port, $proto) {
	if (!$port) {
		return '';
	}

	$service = getservbyport($port, $proto);
	$portstr = "";
	if ($service) {
		$portstr = sprintf('<span title="' . gettext('Service %1$s/%2$s: %3$s') . '">' . htmlspecialchars($port) . '</span>', $port, $proto, $service);
	} else {
		$portstr = htmlspecialchars($port);
	}
	return ':' . $portstr;
}

function find_rule_by_number($rulenum, $trackernum, $type="block") {
	global $g;

	/* Passing arbitrary input to grep could be a Very Bad Thing(tm) */
	if (!is_numeric($rulenum) || !is_numeric($trackernum) || !in_array($type, array('pass', 'block', 'match', 'rdr'))) {
		return;
	}

	if ($trackernum == "0") {
		$lookup_pattern = "^@{$rulenum}\([0-9]+\)[[:space:]]{$type}[[:space:]].*[[:space:]]log[[:space:]]";
	} else {
		$lookup_pattern = "^@[0-9]+\({$trackernum}\)[[:space:]]{$type}[[:space:]].*[[:space:]]log[[:space:]]";
	}

	/* At the moment, miniupnpd is the only thing I know of that
	   generates logging rdr rules */
	unset($buffer);
	if ($type == "rdr") {
		$_gb = exec("/sbin/pfctl -vvPsn -a \"miniupnpd\" | /usr/bin/egrep " . escapeshellarg("^@{$rulenum}"), $buffer);
	} else {
		$_gb = exec("/sbin/pfctl -vvPsr | /usr/bin/egrep " . escapeshellarg($lookup_pattern), $buffer);
	}
	if (is_array($buffer)) {
		return $buffer[0];
	}

	return "";
}

function buffer_rules_load() {
	global $g, $buffer_rules_rdr, $buffer_rules_normal;
	unset($buffer, $buffer_rules_rdr, $buffer_rules_normal);
	/* Redeclare globals after unset to work around PHP */
	global $buffer_rules_rdr, $buffer_rules_normal;
	$buffer_rules_rdr = array();
	$buffer_rules_normal = array();

	$_gb = exec("/sbin/pfctl -vvPsn -a \"miniupnpd\" | /usr/bin/grep '^@'", $buffer);
	if (is_array($buffer)) {
		foreach ($buffer as $line) {
			list($key, $value) = explode (" ", $line, 2);
			$buffer_rules_rdr[$key] = $value;
		}
	}
	unset($buffer, $_gb);
	$_gb = exec("/sbin/pfctl -vvPsr | /usr/bin/egrep '^@[0-9]+\([0-9]+\)[[:space:]].*[[:space:]]log[[:space:]]'", $buffer);

	if (is_array($buffer)) {
		foreach ($buffer as $line) {
			list($key, $value) = explode (" ", $line, 2);
			# pfctl rule number output with tracker number: @dd(dddddddddd)
			$matches = array();
			if (preg_match('/\@(?P<rulenum>\d+)\((?<trackernum>\d+)\)/', $key, $matches) == 1) {
				if ($matches['trackernum'] > 0) {
					$key = $matches['trackernum'];
				} else {
					$key = "@{$matches['rulenum']}";
				}
			}
			$buffer_rules_normal[$key] = $value;
		}
	}
	unset($_gb, $buffer);
}

function buffer_rules_clear() {
	unset($GLOBALS['buffer_rules_normal']);
	unset($GLOBALS['buffer_rules_rdr']);
}

function find_rule_by_number_buffer($rulenum, $trackernum, $type) {
	global $g, $buffer_rules_rdr, $buffer_rules_normal;

	if ($trackernum == "0") {
		$lookup_key = "@{$rulenum}";
	} else {
		$lookup_key = $trackernum;
	}

	if ($type == "rdr") {
		$ruleString = $buffer_rules_rdr[$lookup_key];
		//TODO: get the correct 'description' part of a RDR log line. currently just first 30 characters..
		$rulename = substr($ruleString, 0, 30);
	} else {
		$ruleString = $buffer_rules_normal[$lookup_key];
		list(,$rulename,) = explode("\"", $ruleString);
		$rulename = str_replace("USER_RULE: ", '<i class="fa fa-user"></i> ', $rulename);
	}
	return "{$rulename} ({$lookup_key})";
}

function find_action_image($action) {
	global $g;
	if ((strstr(strtolower($action), "p")) || (strtolower($action) == "rdr")) {
		return "fa-check-circle-o";
	} else if (strstr(strtolower($action), "r")) {
		return "fa-times-circle-o";
	} else {
		return "fa-ban";
	}
}

/* AJAX specific handlers */
function handle_ajax() {
	global $config;
	if ($_REQUEST['lastsawtime'] && $_REQUEST['logfile']) {

		$lastsawtime = getGETPOSTsettingvalue('lastsawtime', null);
		$logfile = getGETPOSTsettingvalue('logfile', null);
		$nentries = getGETPOSTsettingvalue('nentries', null);
		$type = getGETPOSTsettingvalue('type', null);
		$filter = getGETPOSTsettingvalue('filter', null);
		$interfacefilter = getGETPOSTsettingvalue('interfacefilter', null);

		if (!empty(trim($filter)) || is_numeric($filter)) {
			$filter = json_decode($filter, true);	# Filter Fields Array or Filter Text
		}

		/* compare lastsawrule's time stamp to filter logs.
		 * afterwards return the newer records so that client
		 * can update AJAX interface screen.
		 */
		$new_rules = "";

		$filterlog = conv_log_filter($logfile, $nentries, $nentries + 100, $filter, $interfacefilter);

		/* We need this to always be in forward order for the AJAX update to work properly */
		/* Since the lines are in reverse order, flip them around if needed based on the user's preference */
		# First get the "General Logging Options" (global) chronological order setting.  Then apply specific log override if set.
		$reverse = isset($config['syslog']['reverse']);
		$specific_log = basename($logfile, '.log') . '_settings';
		if ($config['syslog'][$specific_log]['cronorder'] == 'forward') $reverse = false;
		if ($config['syslog'][$specific_log]['cronorder'] == 'reverse') $reverse = true;

		$filterlog = ($reverse) ? array_reverse($filterlog) : $filterlog;

		foreach ($filterlog as $log_row) {
			$row_time = strtotime($log_row['time']);
			if ($row_time > $lastsawtime) {
				if ($log_row['proto'] == "TCP") {
					$log_row['proto'] .= ":{$log_row['tcpflags']}";
				}

				if ($log_row['act'] == "block") {
					$icon_act = "fa-times text-danger";
				} else {
					$icon_act = "fa-check text-success";
				}

				$btn = '<i class="fa ' . $icon_act . ' icon-pointer" title="' . $log_row['act'] . '/' . $log_row['tracker'] . '" onclick="javascript:getURL(\'status_logs_filter.php?getrulenum=' . $log_row['rulenum'] . ',' . $log_row['tracker'] . ',' . $log_row['act'] . '\', outputrule);"></i>';
				$new_rules .= "{$btn}||{$log_row['time']}||{$log_row['interface']}||{$log_row['srcip']}||{$log_row['srcport']}||{$log_row['dstip']}||{$log_row['dstport']}||{$log_row['proto']}||{$log_row['version']}||" . time() . "||\n";
			}
		}
		echo $new_rules;
		exit;
	}
}

/* Compatibility stubs for old clog functions until packages catch up.
 * Remove once packages all use the new function names. */
function dump_clog_no_table($logfile, $tail, $withorig = true, $grepfor = "", $grepinvert = "") {
	return dump_log($logfile, $tail, $withorig, $grepfor, $grepinvert, $format = 'notable');
}
function dump_log_no_table($logfile, $tail, $withorig = true, $grepfor = "", $grepinvert = "") {
	return dump_log($logfile, $tail, $withorig, $grepfor, $grepinvert, $format = 'notable');
}
function dump_clog($logfile, $tail, $withorig = true, $grepfor = "", $grepinvert = "") {
	return dump_log($logfile, $tail, $withorig, $grepfor, $grepinvert);
}
function return_clog($logfile, $tail, $withorig = true, $grepfor = "", $grepinvert = "", $grepreverse = false) {
	return dump_log($logfile, $tail, $withorig, $grepfor, $grepinvert, $format = 'none', $grepreverse);
}
function return_log($logfile, $tail, $withorig = true, $grepfor = "", $grepinvert = "", $grepreverse = false) {
	return dump_log($logfile, $tail, $withorig, $grepfor, $grepinvert, $format = 'none', $grepreverse);
}
