<?php
/*
	system.inc
	part of m0n0wall (http://m0n0.ch/wall)
	
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

/* include all configuration functions */
require_once("functions.inc");
	
function system_resolvconf_generate($dynupdate = false) {
	global $config, $g;
	
	$syscfg = $config['system'];
	
	$fd = fopen("{$g['varetc_path']}/resolv.conf", "w");
	if (!$fd) {
		printf("Error: cannot open resolv.conf in system_resolvconf_generate().\n");
		return 1;
	}
		
	$resolvconf = "domain {$syscfg['domain']}\n";
	
	$havedns = false;
	
	if (isset($syscfg['dnsallowoverride'])) {
		/* get dynamically assigned DNS servers (if any) */
		$nfd = @fopen("{$g['varetc_path']}/nameservers.conf", "r");
		if ($nfd) {
			while (!feof($nfd)) {
				$dnss = trim(fgets($nfd));
				if ($dnss) {
					$resolvconf .= "nameserver $dnss\n";
					$havedns = true;
				}
			}
			fclose($nfd);
		}
	}
	if (!$havedns && is_array($syscfg['dnsserver'])) {
		foreach ($syscfg['dnsserver'] as $ns) {
			if ($ns)
				$resolvconf .= "nameserver $ns\n";
			$havedns = true;
		}
	}
		
	fwrite($fd, $resolvconf);
	fclose($fd);
	
	if (!$g['booting']) {
		/* restart dhcpd (nameservers may have changed) */
		if (!$dynupdate)
			services_dhcpd_configure();
	}
	
	return 0;
}

function system_hosts_generate() {
	global $config, $g;
	
	$syscfg = $config['system'];
	$lancfg = $config['interfaces']['lan'];
	$dnsmasqcfg = $config['dnsmasq'];

	if (!is_array($dnsmasqcfg['hosts'])) {
		$dnsmasqcfg['hosts'] = array();
	}
	$hostscfg = $dnsmasqcfg['hosts'];
	
	$fd = fopen("{$g['varetc_path']}/hosts", "w");
	if (!$fd) {
		printf("Error: cannot open hosts file in system_hosts_generate().\n");
		return 1;
	}
		
	$hosts = <<<EOD
127.0.0.1	localhost localhost.{$syscfg['domain']}
{$lancfg['ipaddr']}	{$syscfg['hostname']}.{$syscfg['domain']} {$syscfg['hostname']}

EOD;
	
	foreach ($hostscfg as $host) {
		if ($host['host'])
			$hosts .= "{$host['ip']}	{$host['host']}.{$host['domain']} {$host['host']}\n";
		else
			$hosts .= "{$host['ip']}	{$host['domain']}\n";
	}
	fwrite($fd, $hosts);
	fclose($fd);
	
	return 0;
}

function system_hostname_configure() {
	global $config, $g;
	
	$syscfg = $config['system'];
	
	/* set hostname */
	return mwexec("/bin/hostname " .
		escapeshellarg("{$syscfg['hostname']}.{$syscfg['domain']}"));
}

function system_routing_configure() {
	global $config, $g;
	
	/* clear out old routes, if necessary */
	if (file_exists("{$g['vardb_path']}/routes.db")) {
		$fd = fopen("{$g['vardb_path']}/routes.db", "r");
		if (!$fd) {
			printf("Error: cannot open routes DB file in system_routing_configure().\n");
			return 1;		
		}
		while (!feof($fd)) {
			$oldrt = fgets($fd);
			if ($oldrt)
				mwexec("/sbin/route delete " . escapeshellarg($oldrt));
		}
		fclose($fd);
		unlink("{$g['vardb_path']}/routes.db");
	}
	
	if (is_array($config['staticroutes']['route'])) {
		
		$fd = fopen("{$g['vardb_path']}/routes.db", "w");
		if (!$fd) {
			printf("Error: cannot open routes DB file in system_routing_configure().\n");
			return 1;		
		}
		
		foreach ($config['staticroutes']['route'] as $rtent) {
			mwexec("/sbin/route add " . escapeshellarg($rtent['network']) . 
				" " . escapeshellarg($rtent['gateway']));
			
			/* record route so it can be easily removed later (if necessary) */
			fwrite($fd, $rtent['network'] . "\n");
		}
		
		fclose($fd); 
	}
	
	return 0;
}

function system_routing_enable() {
	global $config, $g;
	
	return mwexec("/sbin/sysctl net.inet.ip.forwarding=1");
}

function system_syslogd_start() {
	global $config, $g;
	
	$syslogcfg = $config['syslog'];

	if ($g['booting']) 
		echo "Starting syslog service... ";
	else
		killbypid("{$g['varrun_path']}/syslog.pid");
			
	if (isset($syslogcfg['enable'])) {

		/* write syslog.conf */
		$fd = fopen("{$g['varetc_path']}/syslog.conf", "w");
		if (!$fd) {
			printf("Error: cannot open syslog.conf in system_syslogd_start().\n");
			return 1;
		}
		
		$syslogconf = <<<EOD
local0.*					%/var/log/filter.log
local3.*					%/var/log/vpn.log
local7.*					%/var/log/dhcpd.log
*.notice;kern.debug;lpr.info;mail.crit;news.err;local0.none;local3.none;local7.none %/var/log/system.log
security.*					%/var/log/system.log
auth.info;authpriv.info;daemon.info		%/var/log/system.log
*.emerg						*

EOD;

		if (isset($syslogcfg['filter'])) {
			$syslogconf .= <<<EOD
local0.*					@{$syslogcfg['remoteserver']}

EOD;
		}
		
		if (isset($syslogcfg['vpn'])) {
			$syslogconf .= <<<EOD
local3.*					@{$syslogcfg['remoteserver']}

EOD;
		}

		if (isset($syslogcfg['dhcp'])) {
			$syslogconf .= <<<EOD
local7.*					@{$syslogcfg['remoteserver']}

EOD;
		}

		if (isset($syslogcfg['system'])) {
			$syslogconf .= <<<EOD
*.notice;kern.debug;lpr.info;mail.crit;news.err;local0.none;local7.none @{$syslogcfg['remoteserver']}
security.*					@{$syslogcfg['remoteserver']}
auth.info;authpriv.info;daemon.info		@{$syslogcfg['remoteserver']}
*.emerg						@{$syslogcfg['remoteserver']}

EOD;
		}

		fwrite($fd, $syslogconf);
		fclose($fd);
		
		$retval = mwexec("/usr/sbin/syslogd -s -f {$g['varetc_path']}/syslog.conf");

	} else {
		$retval = mwexec("/usr/sbin/syslogd -ss");
	}
	
	if ($g['booting'])
		echo "done\n";
		
	return $retval;
}

function system_pccard_start() {
	global $config, $g;
	
	if ($g['booting'])
		echo "Initializing PC cards... ";
	
	/* kill any running pccardd */
	killbypid("{$g['varrun_path']}/pccardd.pid");
	
	/* fire up pccardd */
	$res = mwexec("/usr/sbin/pccardd -z -f {$g['etc_path']}/pccard.conf");
	
	if ($g['booting']) {
		if ($res == 0)
			echo "done\n";
		else
			echo "failed (probably no PC card controller present)\n";
	}
		
	return $res;
}

function system_webgui_start() {
	global $config, $g;
	
	if ($g['booting'])
		echo "Starting webGUI... ";
	
	/* kill any running mini_httpd */
	killbypid("{$g['varrun_path']}/mini_httpd.pid");
	
	/* generate password file */
	system_password_configure();
	
	chdir($g['www_path']);
	
	/* non-standard port? */
	if ($config['system']['webgui']['port'])
		$portarg = "-p {$config['system']['webgui']['port']}";
	else
		$portarg = "";
	
	if ($config['system']['webgui']['protocol'] == "https") {
	
		if ($config['system']['webgui']['certificate'] && $config['system']['webgui']['private-key']) {
			$cert = base64_decode($config['system']['webgui']['certificate']);
			$key = base64_decode($config['system']['webgui']['private-key']);
		} else {
			/* default certificate/key */
			$cert = <<<EOD
-----BEGIN CERTIFICATE-----
MIIBlDCB/gIBADANBgkqhkiG9w0BAQQFADATMREwDwYDVQQKEwhtMG4wd2FsbDAe
Fw0wMzA5MDgxNzAzNDZaFw0wNDA5MDcxNzAzNDZaMBMxETAPBgNVBAoTCG0wbjB3
YWxsMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDAShszhFz+o8lsMWTGgTxs
TMPR+v4+qL5jXDyY97MLTGFK7aqQOtpIQc+TcTc4jklgOVlHoR7oBXrsi8YrbCd+
83LPQmQoSPC0VqhfU3uYf3NzxiK8r97aPCsmWgwT2pQ6TcESTm6sF7nLprOf/zFP
C4jE2fvjkbzyVolPywBuewIDAQABMA0GCSqGSIb3DQEBBAUAA4GBAK2D8NqQSlUs
pFCe5J9ue1LrjfGHHy4HE9zA9avgrz3Qju+1JOshEwy/1BJjZ93tQUbiRS7RwvDO
4crGG4IejjhFczzA2CIX3rd2rYM2oGpojKgm5YuuhV5lYPwAHUOLbBaLOVqlLhzw
VqjD7R2DkXUIfhJ5ZekqK5ZwzqJXta8U
-----END CERTIFICATE-----

EOD;

			$key = <<<EOD
-----BEGIN RSA PRIVATE KEY-----
MIICXQIBAAKBgQDAShszhFz+o8lsMWTGgTxsTMPR+v4+qL5jXDyY97MLTGFK7aqQ
OtpIQc+TcTc4jklgOVlHoR7oBXrsi8YrbCd+83LPQmQoSPC0VqhfU3uYf3NzxiK8
r97aPCsmWgwT2pQ6TcESTm6sF7nLprOf/zFPC4jE2fvjkbzyVolPywBuewIDAQAB
AoGAbJJrQW9fQrggJuLMz/hwsYW2m31oyOBmf5u463YQtjRuSuxe/gj87weZuNqY
H2rXq2k2K+ehl8hgW+egASyUL3L7kCkEAsVREujKTEyhSqqIRDPWTxo9S/YA9Gvn
2ZnJvkrcKjqCO9aHX3rvJOK/ErYI6akctgI3KmgkYw5XNmECQQDuZU97RTWH9rmP
aQr57ysNXxgFsyhetOOqeYkPtIVwpOiNbfwE1zi5RGdtO4Ku3fG1lV4J2UoWJ9yD
awdoyYIHAkEAzn0xJ90IjPsHk+8SODEj5JGdHSZPNu1tgtrbjEi9sfGWg4K7XTxr
QW90pWb1bKKU1uh5FzW6OhnFfuQXt1kC7QJAPSthqY+onKqCEnoxhtAHi/bKgyvl
P+fKQwPMV2tKkgy+XwvJjrRqqZ8TqsOKVLQ+QQmCh6RpjiXMPyxHSmvqIQJBAKLR
HF1ucDuaBROkwx0DwmWMW/KMLpIFDQDNSaiIAuu4rxHrl4mhBoGGPNffI04RtILw
s+qVNs5xW8T+XaT4ztECQQDFHPnZeoPWE5z+AX/UUQIUWaDExz3XRzmIxRbOrlFi
CsF1s0TdJLi/wzNQRAL37A8vqCeVFR/ng3Xpg96Yg+8Z
-----END RSA PRIVATE KEY-----

EOD;
		}
		
		$fd = fopen("{$g['varetc_path']}/cert.pem", "w");
		if (!$fd) {
			printf("Error: cannot open cert.pem in system_webgui_start().\n");
			return 1;
		}
		chmod("{$g['varetc_path']}/cert.pem", 0600);
		fwrite($fd, $cert);
		fwrite($fd, "\n");
		fwrite($fd, $key);
		fclose($fd);
	
		$res = mwexec("/usr/local/sbin/mini_httpd -S -E {$g['varetc_path']}/cert.pem" .
			" -c \"**.php|**.cgi\" -u root -maxproc 16 $portarg" .
			" -i {$g['varrun_path']}/mini_httpd.pid");
	} else {
		$res = mwexec("/usr/local/sbin/mini_httpd -c \"**.php|**.cgi\" -u root" .
			" -maxproc 16 $portarg -i {$g['varrun_path']}/mini_httpd.pid");
	}
	
	if ($g['booting']) {
		if ($res == 0)
			echo "done\n";
		else
			echo "failed\n";
	}
	
	return $res;
}

function system_password_configure() {
	global $config, $g;
	
	$fd = fopen("{$g['varrun_path']}/htpasswd", "w");
	if (!$fd) {
		printf("Error: cannot open htpasswd in system_password_configure().\n");
		return 1;
	}
	
	if ($config['system']['username'])
		$username = $config['system']['username'];
	else
		$username = "admin";
	
	fwrite($fd, $username . ":" . $config['system']['password'] . "\n");
	fclose($fd);
	chmod("{$g['varrun_path']}/htpasswd", 0600);
	
	return 0;
}

function system_timezone_configure() {
	global $config, $g;

	$syscfg = $config['system'];

	if ($g['booting'])
		echo "Initializing timezone... ";

	/* extract appropriate timezone file */
	$timezone = $syscfg['timezone'];
	if (!$timezone)
		$timezone = "Etc/UTC";
		
	exec("/usr/bin/tar xzfO /usr/share/zoneinfo.tgz " . 
		escapeshellarg($timezone) . " > /etc/localtime");

	if ($g['booting'])
		echo "done\n";
}

function system_ntp_configure() {
	global $config, $g;

	$syscfg = $config['system'];

	if ($g['booting'])
		echo "Starting NTP client... ";
	else {
		killbypid("{$g['varrun_path']}/runmsntp.pid");
		killbypid("{$g['varrun_path']}/msntp.pid");
	}

	/* start ntp client if needed - needs to be forced into background */
	$updateinterval = $syscfg['time-update-interval'];
	
	if ($updateinterval > 0) {
		if ($updateinterval < 6)
			$updateinterval = 6;
		
		$timeservers = "";
		foreach (explode(' ', $syscfg['timeservers']) as $ts)
			$timeservers .= " " . $ts;
		
		mwexec_bg("/usr/local/bin/runmsntp.sh " .
			escapeshellarg("{$g['varrun_path']}/runmsntp.pid") . " " .
			escapeshellarg("{$g['varrun_path']}/msntp.pid") . " " .
			escapeshellarg($updateinterval) . " " .
			escapeshellarg($timeservers));
	}
		
	if ($g['booting'])
		echo "done\n";
}

function system_reboot() {
	global $g;
	
	system_reboot_cleanup();
	
	mwexec("nohup /etc/rc.reboot > /dev/null 2>&1 &");
}

function system_reboot_sync() {
	global $g;
	
	system_reboot_cleanup();
	
	mwexec("/etc/rc.reboot > /dev/null 2>&1");
}

function system_reboot_cleanup() {
	captiveportal_radius_stop_all();
}

function system_do_shell_commands($early = 0) {
	global $config, $g;
	
	if ($early)
		$cmdn = "earlyshellcmd";
	else
		$cmdn = "shellcmd";
	
	if (is_array($config['system'][$cmdn])) {
		
		foreach ($config['system'][$cmdn] as $cmd) {
			exec($cmd);
		}
	}
}

function system_do_extensions() {
	global $config, $g;
	
	if (!is_dir("{$g['etc_path']}/inc/ext"))
		return;
	
	$dh = @opendir("{$g['etc_path']}/inc/ext");
	if ($dh) {
		while (($extd = readdir($dh)) !== false) {
			if (($extd === ".") || ($extd === ".."))
				continue;
			$rcfile = "{$g['etc_path']}/inc/ext/" . $extd . "/rc";
			if (file_exists($rcfile))
				passthru($rcfile);
		}
		closedir($dh);
	}
}

function system_console_configure() {
	global $config, $g;
	
	if (isset($config['system']['disableconsolemenu'])) {
		touch("{$g['varetc_path']}/disableconsole");
	} else {
		unlink_if_exists("{$g['varetc_path']}/disableconsole");
	}
}

function system_dmesg_save() {
	global $g;
	
	exec("/sbin/dmesg", $dmesg);
	
	/* find last copyright line (output from previous boots may be present) */
	$lastcpline = 0;
	
	for ($i = 0; $i < count($dmesg); $i++) {
		if (strstr($dmesg[$i], "Copyright (c) 1992-"))
			$lastcpline = $i;
	}
	
	$fd = fopen("{$g['varlog_path']}/dmesg.boot", "w");
	if (!$fd) {
		printf("Error: cannot open dmesg.boot in system_dmesg_save().\n");
		return 1;
	}
	
	for ($i = $lastcpline; $i < count($dmesg); $i++)
		fwrite($fd, $dmesg[$i] . "\n");
	
	fclose($fd);
	
	return 0;
}

function system_set_harddisk_standby() {
	global $g, $config;

	if ($g['platform'] != "generic-pc")
		return;

	if (isset($config['system']['harddiskstandby'])) {
		if ($g['booting']) {
			echo 'Setting harddisk standby time... ';
		}

		$standby = $config['system']['harddiskstandby'];
		// Check for a numeric value
		if (is_numeric($standby)) {
			// Sync the disk(s)
			mwexec('/bin/sync');
			if (!mwexec('/sbin/sysctl hw.ata.standby=' . ((int)$standby))) {
				// Reinitialize ATA-drives
				mwexec('/usr/local/sbin/atareinit');
				if ($g['booting']) {
					echo "done\n";
				}
			} else if ($g['booting']) {
				echo "failed\n";
			}
		} else if ($g['booting']) {
			echo "failed\n";
		}
	}
}

?>
