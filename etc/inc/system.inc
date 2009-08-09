<?php
/* $Id$ */
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

function opcode_cache_configuration() {
		global $g;
        if($g['platform'] == "cdrom")
        	return;

        /* get system memory amount */
        $memory = get_memory();
        $avail = $memory[0];

		/* disable apc for platforms less than 90 megs of ram */
        if($memory > 90) {
        	$opcode_cacher = "extension=apc.so\n";
        	$opcode_cacher .= "apc.enabled=\"1\"\n";
			$opcode_cacher .= "apc.enable_cli=\"1\"\n";
			$opcode_cacher .= "apc.shm_size=\"8\"\n";
        } else {
			$opcode_cacher = "";
        }

		/* create a php.ini variable */
		$php_conf = file_get_contents("/usr/local/lib/php.ini");

$php_ini = <<<EOFF
output_buffering = "0"
implicit_flush = true
magic_quotes_gpc = Off
max_execution_time = 99999999
max_input_time = 99999999
memory_limit = 32M
register_argc_argv = On
file_uploads = On
extension_dir=/usr/local/lib/php/extensions/no-debug-non-zts-20020429/
upload_tmp_dir = /tmp
upload_max_filesize = 100M
post_max_size = 100M
html_errors = Off
include_path = ".:/etc/inc:/usr/local/www:/usr/local/captiveportal:/usr/local/pkg"
extension=radius.so
{$opcode_cacher}

EOFF;

        config_lock();
		conf_mount_rw();

		/* open up php.ini and write back out contents */
		$fd = fopen("/usr/local/lib/php.ini","w");
		fwrite($fd, $php_ini);
		fclose($fd);

		mwexec("sync");
		conf_mount_ro();
		exec("sync");
		config_unlock();
}

function system_resolvconf_generate($dynupdate = false) {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_resolvconf_generate() being called $mt\n";
	}

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
		$ns = array_unique(get_nameservers());
		foreach($ns as $nameserver) {
			if($nameserver) {
				$resolvconf .= "nameserver $nameserver\n";
				$havedns = true;
			}
		}
        }
        if (!$havedns && is_array($syscfg['dnsserver'])) {
                foreach ($syscfg['dnsserver'] as $ns) {
                        if ($ns) {
                                $resolvconf .= "nameserver $ns\n";
				$havedns = true;
			}
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

function get_nameservers() {
	global $config, $g;
	$master_list = array();
	$dns_lists = split("\n", `ls /var/etc/nameserver_* 2>/dev/null`);
	foreach($dns_lists as $dns) {
		$items = split("\n", file_get_contents($dns));
		foreach($items as $item)
			if($item <> "")
				$master_list[] = $item;
	}
	if(!file_exists("/var/etc/nameservers.conf"))
		return $master_list;
	$dns = `cat /var/etc/nameservers.conf`;
	$dns_s = split("\n", $dns);
	if(is_array($dns_s))
		foreach($dns_s as $dns)
			$master_list[] = $dns;
	return $master_list;
}

function system_hosts_generate() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_hosts_generate() being called $mt\n";
	}

	$syscfg = $config['system'];
	$lancfg = $config['interfaces']['lan'];
	$dnsmasqcfg = $config['dnsmasq'];

	if (!is_array($dnsmasqcfg['hosts'])) {
		$dnsmasqcfg['hosts'] = array();
	}
	$hostscfg = $dnsmasqcfg['hosts'];

	$fd = fopen("{$g['varetc_path']}/hosts", "w");
	if (!$fd) {
		log_error("Error: cannot open hosts file in system_hosts_generate().\n");
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
	if (isset($dnsmasqcfg['regdhcpstatic'])) {
		foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf)
			if(is_array($dhcpifconf['staticmap']) && isset($dhcpifconf['enable']))
					foreach ($dhcpifconf['staticmap'] as $host)
						if ($host['ipaddr'] && $host['hostname'])
							$hosts .= "{$host['ipaddr']}	{$host['hostname']}.{$syscfg['domain']} {$host['hostname']}\n";
	}
	fwrite($fd, $hosts);
	fclose($fd);

	return 0;
}

function system_hostname_configure() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_hostname_configure() being called $mt\n";
	}

	$syscfg = $config['system'];

	/* set hostname */
	$status = mwexec("/bin/hostname " .
		escapeshellarg("{$syscfg['hostname']}.{$syscfg['domain']}"));
		
    /* Setup host GUID ID.  This is used by ZFS. */
	mwexec("/etc/rc.d/hostid start");

	return $status;
}

function system_routing_configure() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_routing_configure() being called $mt\n";
	}

	/* Enable fast routing, if enabled */
	if(isset($config['staticroutes']['enablefastrouting']))
		mwexec("/sbin/sysctl net.inet.ip.fastforwarding=1");

	/* clear out old routes, if necessary */
	exec("/usr/bin/netstat -rn", $route_arr, $retval);
	$route_str = implode("\n", $route_arr);

	if (file_exists("{$g['vardb_path']}/routes.db")) {
		$fd = fopen("{$g['vardb_path']}/routes.db", "r");
		if (!$fd) {
			printf("Error: cannot open routes DB file in system_routing_configure().\n");
			return 1;
		}
		while (!feof($fd)) {
			$oldrt = trim(fgets($fd));
			if (($oldrt) && (stristr($route_str, $oldrt)))
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
			if(isset($rtent['interfacegateway'])) {
				mwexec("/sbin/route add " . escapeshellarg($rtent['network']) .
					" -iface " . escapeshellarg(convert_friendly_interface_to_real_interface_name($rtent['interface'])));
			} else {
				mwexec("/sbin/route add " . escapeshellarg($rtent['network']) .
					" " . escapeshellarg($rtent['gateway']));
			}
			/* record route so it can be easily removed later (if necessary) */
			fwrite($fd, $rtent['network'] . "\n");
		}

		fclose($fd);
	}

	/* Make sure default gateway is present */
	$result = `/usr/bin/netstat -rn | grep default`;
	if(!$result) {
		if(is_ipaddr($config['interfaces']['wan']['gateway'])) {
			log_error("No default gateway detected, adding {$config['interfaces']['wan']['gateway']}");
			mwexec("/sbin/route add default " . escapeshellarg($config['interfaces']['wan']['gateway']));
		}
	}
	return 0;
}

function system_routing_enable() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_routing_enable() being called $mt\n";
	}

	return mwexec("/sbin/sysctl net.inet.ip.forwarding=1");
}

function system_syslogd_start() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_syslogd_start() being called $mt\n";
	}

	$syslogcfg = $config['syslog'];

	if ($g['booting'])
		echo "Starting syslog...";
	else
		killbypid("{$g['varrun_path']}/syslog.pid");

	if (isset($syslogcfg)) {
		$separatelogfacilities = array('ntpd','racoon','openvpn');
		if($config['installedpackages']['package']) {
                        foreach($config['installedpackages']['package'] as $package) {
                                if($package['logging']) {
					$pkgfacilities[] = $package['logging']['facilityname'];
					$separatelogfacilities = $separatelogfacilities + $pkgfacilities;
					$facilitylist = implode(',', $pkgfacilities);
					mwexec("clog -i -s 10000 {$g['varlog_path']}/{$package['logging']['logfilename']}");
                                	$syslogconf .= "!{$facilitylist}\n*.*\t\t\t\t\t\t%{$g['varlog_path']}/{$package['logging']['logfilename']}\n";
				}
                        }
                }
		$facilitylist = implode(',', array_unique($separatelogfacilities));
		/* write syslog.conf */
		$fd = fopen("{$g['varetc_path']}/syslog.conf", "w");
		if (!$fd) {
			printf("Error: cannot open syslog.conf in system_syslogd_start().\n");
			return 1;
		}
		$syslogconf .= "!ntpdate,!ntpd\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= <<<EOD
*.*						%{$g['varlog_path']}/ntpd.log

EOD;
		}
		$syslogconf .= "!apinger\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= <<<EOD
*.*						%{$g['varlog_path']}/slbd.log

EOD;
		}
		$syslogconf .= "!racoon\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= <<<EOD
*.*						%{$g['varlog_path']}/ipsec.log

EOD;
		}
		if (isset($syslogcfg['vpn'])) {
			$syslogconf .= <<<EOD
*.*						@{$syslogcfg['remoteserver']}

EOD;
		}
		$syslogconf .= "!openvpn\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= <<<EOD
*.*						%{$g['varlog_path']}/openvpn.log

EOD;
		}
		if (isset($syslogcfg['vpn'])) {
			$syslogconf .= <<<EOD
*.*						@{$syslogcfg['remoteserver']}

EOD;
		}
		$syslogconf .= "!-{$facilitylist}\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
		$syslogconf .= <<<EOD
local0.*					%{$g['varlog_path']}/filter.log
local3.*					%{$g['varlog_path']}/vpn.log
local4.*					%{$g['varlog_path']}/portalauth.log
local7.*					%{$g['varlog_path']}/dhcpd.log
*.notice;kern.debug;lpr.info;mail.crit; 	%{$g['varlog_path']}/system.log
news.err;local0.none;local3.none;local4.none; 	%{$g['varlog_path']}/system.log
local7.none					%{$g['varlog_path']}/system.log
security.*					%{$g['varlog_path']}/system.log
auth.info;authpriv.info;daemon.info		%{$g['varlog_path']}/system.log
local1.*					%{$g['varlog_path']}/slbd.log
auth.info;authpriv.info 			|exec /usr/local/sbin/sshlockout_pf
*.emerg						*

EOD;
		}

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


		if (isset($syslogcfg['portalauth'])) {
			$syslogconf .= <<<EOD
local4.*					@{$syslogcfg['remoteserver']}

EOD;
		}


		if (isset($syslogcfg['dhcp'])) {
			$syslogconf .= <<<EOD
local7.*					@{$syslogcfg['remoteserver']}

EOD;
		}

		if (isset($syslogcfg['system'])) {
			$syslogconf .= <<<EOD
*.notice;kern.debug;lpr.info;mail.crit;		@{$syslogcfg['remoteserver']}
news.err;local0.none;local3.none;local7.none	@{$syslogcfg['remoteserver']}
security.*					@{$syslogcfg['remoteserver']}
auth.info;authpriv.info;daemon.info		@{$syslogcfg['remoteserver']}
*.emerg						@{$syslogcfg['remoteserver']}

EOD;
		}
                if (isset($syslogcfg['logall'])) {
			$syslogconf .= <<<EOD
*.*		                                @{$syslogcfg['remoteserver']}

EOD;
		}
		fwrite($fd, $syslogconf);
		fclose($fd);

		// Are we logging to a least one remote server ?
		if(strpos($syslogconf, "@") != false)
			$retval = mwexec("/usr/sbin/syslogd -c -s -f {$g['varetc_path']}/syslog.conf");
		else
			$retval = mwexec("/usr/sbin/syslogd -c -ss -f {$g['varetc_path']}/syslog.conf");

	} else {
		$retval = mwexec("/usr/sbin/syslogd -c -ss");
	}

	if ($g['booting'])
		echo "done.\n";

	return $retval;
}

function system_pccard_start() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_pccard_start() being called $mt\n";
	}

	if ($g['booting'])
		echo "Initializing PCMCIA...";

	/* kill any running pccardd */
	killbypid("{$g['varrun_path']}/pccardd.pid");

	/* fire up pccardd */
	$res = mwexec("/usr/sbin/pccardd -z -f {$g['etc_path']}/pccard.conf");

	if ($g['booting']) {
		if ($res == 0)
			echo "done.\n";
		else
			echo "failed!\n";
	}

	return $res;
}


function system_webgui_start() {
	global $config, $g;

	if ($g['booting'])
		echo "Starting webConfigurator...";

	/* kill any running lighttpd */
	killbypid("{$g['varrun_path']}/lighty-webConfigurator.pid");

	sleep(1);

	/* generate password file */
	system_password_configure();

	chdir($g['www_path']);

	/* non-standard port? */
	if ($config['system']['webgui']['port'])
		$portarg = "{$config['system']['webgui']['port']}";
	else
		$portarg = "";

	if ($config['system']['webgui']['protocol'] == "https") {

	if(!$config['system']['webgui']['port'])
		$portarg = "443";

		if ($config['system']['webgui']['certificate'] && $config['system']['webgui']['private-key']) {
			$cert = base64_decode($config['system']['webgui']['certificate']);
			$key = base64_decode($config['system']['webgui']['private-key']);
		} else {
			/* default certificate/key */
			$cert = <<<EOD
-----BEGIN CERTIFICATE-----
MIIDEzCCAnygAwIBAgIJAJM91W+s6qptMA0GCSqGSIb3DQEBBAUAMGUxCzAJBgNV
BAYTAlVTMQswCQYDVQQIEwJLWTETMBEGA1UEBxMKTG91aXN2aWxsZTEQMA4GA1UE
ChMHcGZTZW5zZTEQMA4GA1UECxMHcGZTZW5zZTEQMA4GA1UEAxMHcGZTZW5zZTAe
Fw0wNjAzMTAyMzQ1MTlaFw0xNjAzMDcyMzQ1MTlaMGUxCzAJBgNVBAYTAlVTMQsw
CQYDVQQIEwJLWTETMBEGA1UEBxMKTG91aXN2aWxsZTEQMA4GA1UEChMHcGZTZW5z
ZTEQMA4GA1UECxMHcGZTZW5zZTEQMA4GA1UEAxMHcGZTZW5zZTCBnzANBgkqhkiG
9w0BAQEFAAOBjQAwgYkCgYEA3lPNTFH6qge/ygaqe/BS4oH59O6KvAesWcRzSu5N
21lyVE5tBbL0zqOSXmlLyReMSbtAMZqt1P8EPYFoOcaEQHIWm2VQF80Z18+8Gh4O
UQGjHq88OeaLqyk3OLpSKzSpXuCFrSN7q9Kez8zp5dQEu7sIW30da3pAbdqYOimA
1VsCAwEAAaOByjCBxzAdBgNVHQ4EFgQUAnx+ggC4SzJ0CK+rhPhJ2ZpyunEwgZcG
A1UdIwSBjzCBjIAUAnx+ggC4SzJ0CK+rhPhJ2ZpyunGhaaRnMGUxCzAJBgNVBAYT
AlVTMQswCQYDVQQIEwJLWTETMBEGA1UEBxMKTG91aXN2aWxsZTEQMA4GA1UEChMH
cGZTZW5zZTEQMA4GA1UECxMHcGZTZW5zZTEQMA4GA1UEAxMHcGZTZW5zZYIJAJM9
1W+s6qptMAwGA1UdEwQFMAMBAf8wDQYJKoZIhvcNAQEEBQADgYEAAviQpdoeabL8
1HSZiD7Yjx82pdLpyQOdXvAu3jEAYz53ckx0zSMrzsQ5r7Vae6AE7Xd7Pj+1Yihs
AJZzOQujnmsuim7qu6YSxzP34xonKwd1C9tZUlyNRNnEmtXOEDupn05bih1ugtLG
kqfPIgDbDLXuPtEAA6QDUypaunI6+1E=
-----END CERTIFICATE-----

EOD;

			$key = <<<EOD
-----BEGIN RSA PRIVATE KEY-----
MIICXgIBAAKBgQDeU81MUfqqB7/KBqp78FLigfn07oq8B6xZxHNK7k3bWXJUTm0F
svTOo5JeaUvJF4xJu0Axmq3U/wQ9gWg5xoRAchabZVAXzRnXz7waHg5RAaMerzw5
5ourKTc4ulIrNKle4IWtI3ur0p7PzOnl1AS7uwhbfR1rekBt2pg6KYDVWwIDAQAB
AoGAP7E0VFP8Aq/7os3sE1uS8y8XQ7L+7cUo/AKKoQHKLjfeyAY7t3FALt6vdPqn
anGjkA/j4RIWELoKJfCnwj17703NDCPwB7klcmZvmTx5Om1ZrRyZdQ6RJs0pOOO1
r2wOnZNaNWStXE9Afpw3dj20Gh0V/Ioo5HXn3sHfxZm8dnkCQQDwv8OaUdp2Hl8t
FDfXB1CMvUG1hEAvbQvZK1ODkE7na2/ChKjVPddEI3DvfzG+nLrNuTrAyVWgRLte
r8qX5PQHAkEA7GlKx0S18LdiKo6wy2QeGu6HYkPncaHNFOWX8cTpvGGtQoWYSh0J
tjCt1/mz4/XkvZWuZyTNx2FdkVlNF5nHDQJBAIRWVTZqEjVlwpmsCHnp6mxCyHD4
DrRDNAUfnNuwIr9xPlDlzUzSnpc1CCqOd5C45LKbRGGfCrN7tKd66FmQoFcCQQCy
Kvw3R1pTCvHJnvYwoshphaC0dvaDVeyINiwYAk4hMf/wpVxLZqz+CJvLrB1dzOBR
3O+uPjdzbrakpweJpNQ1AkEA3ZtlgEj9eWsLAJP8aKlwB8VqD+EtG9OJSUMnCDiQ
WFFNj/t3Ze3IVuAyL/yMpiv3JNEnZhIxCta42eDFpIZAKw==
-----END RSA PRIVATE KEY-----

EOD;
		}
	} else {
		$cert = "";
		$key = "";
	}

	/* generate lighttpd configuration */
	system_generate_lighty_config("{$g['varetc_path']}/lighty-webConfigurator.conf",
		$cert, $key, "lighty-webConfigurator.pid", $portarg, "/usr/local/www/");

	/* attempt to start lighthttpd */
	$res = mwexec("/usr/local/sbin/lighttpd -f {$g['varetc_path']}/lighty-webConfigurator.conf");

	if ($g['booting']) {
		if ($res == 0)
			echo "done.\n";
		else
			echo "failed!\n";
	}

	return $res;
}

function system_webgui_start_old() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_webgui_start() being called $mt\n";
	}

        if ($g['booting'])
                echo "Starting webConfigurator...";

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
                        $key  = base64_decode($config['system']['webgui']['private-key']);
                } else {
                        /* default certificate/key */
                        $cert = <<<EOD
-----BEGIN CERTIFICATE-----
MIIBlDCB/gIBADANBgkqhkiG9w0BAQQFADATMREwDwYDVQQKEwhtMG4wd2FsbDAe
Fw0wNTA1MTAxMjI0NDRaFw0wNzA1MTAxMjI0NDRaMBMxETAPBgNVBAoTCG0wbjB3
YWxsMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDAShszhFz+o8lsMWTGgTxs
TMPR+v4+qL5jXDyY97MLTGFK7aqQOtpIQc+TcTc4jklgOVlHoR7oBXrsi8YrbCd+
83LPQmQoSPC0VqhfU3uYf3NzxiK8r97aPCsmWgwT2pQ6TcESTm6sF7nLprOf/zFP
C4jE2fvjkbzyVolPywBuewIDAQABMA0GCSqGSIb3DQEBBAUAA4GBAFR962c4R5tV
cTn0OQcszYoW6WC+ini9tQQh5ku5jYDAiC+00atawJEVLnL3lwAcpSKTIWlTkD20
tl3lz5br1qFgYky+Rd0kwS2nk9jRbkxSXxd6KJVnNRCKre28aw3ENzZfCSurPQsX
UPp5er+NtwMT1g7s/JDmKTC4w1rGr5/c
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

				$cert = str_replace("\r", "", $cert);
				$key = str_replace("\r", "", $key);

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

function system_generate_lighty_config($filename,
	$cert,
	$key,
	$pid_file,
	$port = 80,
	$document_root = "/usr/local/www/",
	$cert_location = "cert.pem",
	$max_procs = 2,
	$max_requests = "1",
	$fast_cgi_enable = true,
	$captive_portal = false) {

	global $config, $g;

	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_generate_lighty_config() being called $mt\n";
	}

	if($captive_portal == true)  {
		$captiveportal = ",\"mod_rewrite\"";
		$captive_portal_rewrite = "url.rewrite-once = ( \"(.*captiveportal.*)\" => \"$1\", \"(.*)\" => \"/index.php?redirurl=$1\" )\n";
		$captive_portal_module = "\"mod_accesslog\", ";
		$maxprocperip = $config['captiveportal']['maxprocperip'];
		if(!$maxprocperip and $maxprocperip > 0)
			$captive_portal_mod_evasive = "evasive.max-conns-per-ip = {$maxprocperip}";
		else
			$captive_portal_mod_evasive = "";
		$server_upload_dirs = "server.upload-dirs = ( \"/tmp/captiveportal/\" )\n";
		exec("mkdir -p /tmp/captiveportal");
		exec("chmod a-w /tmp/captiveportal");
		$server_max_request_size = "server.max-request-size    = 384";
	} else {
		$captive_portal_module = "";
		$captive_portal_mod_evasive = "";
		$server_upload_dirs = "server.upload-dirs = ( \"{$g['upload_path']}/\", \"/tmp/\", \"/var/\" )\n";
		$server_max_request_size = "server.max-request-size    = 2097152";
	}

	if($port <> "")
		$lighty_port = $port;
	else
		$lighty_port = "80";

	$memory = get_memory();
	$avail = $memory[0];

	if($avail > 0 and $avail < 98) {
		$max_procs = 1;
		$max_requests = 1;
	}

	if($avail > 97 and $avail < 128) {
		$max_procs = 1;
		$max_requests = 3;
	}

	if($avail > 127 and $avail < 256) {
		$max_procs = 1;
		$max_requests = 5;
	}

	if($avail > 255 and $avail < 384) {
		$max_procs = 3;
		$max_requests = 10;
	}

	if($avail > 384) {
		$max_procs = 4;
		$max_requests = 16;
	}

		if($captive_portal == true)  {	
			$bin_environment =  <<<EOC
	        "bin-environment" => (
	           "PHP_FCGI_CHILDREN" => "16",
	           "PHP_FCGI_MAX_REQUESTS" => "{$max_requests}"
	        ), 
EOC;

		} else {
			$bin_environment = "";
		}
		
	if($fast_cgi_enable == true) {
		$module = "\"mod_fastcgi\", \"mod_cgi\"";
		$cgi_config = "";
		$fastcgi_config = <<<EOD
#### fastcgi module
## read fastcgi.txt for more info
fastcgi.server = ( ".php" =>
	( "localhost" =>
		(
			"socket" => "/tmp/php-fastcgi.socket",
			"min-procs" => 1,
			"max-procs" => {$max_procs},
			"idle-timeout" => 0,
			{$bin_environment}			
			"bin-path" => "/usr/local/bin/php"
		)
	)
)

#### CGI module
cgi.assign                 = ( ".cgi" => "" )

EOD;
	} else {
		$fastcgi_config = "";
		$module = "\"mod_cgi\"";
		$cgi_config = <<<EOD
#### CGI module
cgi.assign                 = ( ".php"  => "/usr/local/bin/php",
                               ".cgi" => "" )

EOD;
	}

	$lighty_config .= <<<EOD
#
# lighttpd configuration file
#
# use a it as base for lighttpd 1.0.0 and above
#
############ Options you really have to take care of ####################

## FreeBSD!
server.event-handler		= "freebsd-kqueue"
server.network-backend		= "writev"  ## Fixes 7.x upload issues

{$network_handler}

## modules to load
server.modules              =   (
				  {$captive_portal_module}
				  "mod_access", "mod_accesslog",
                                  {$module}{$captiveportal}
				)

## Unused modules
#                               "mod_setenv",
#                               "mod_compress"
#				"mod_redirect",
#                               "mod_rewrite",
#                               "mod_ssi",
#                               "mod_usertrack",
#                               "mod_expire",
#                               "mod_secdownload",
#                               "mod_rrdtool",
#                               "mod_auth",
#                               "mod_status",
#                               "mod_alias",
#                               "mod_proxy",
#                               "mod_simple_vhost",
#                               "mod_evhost",
#                               "mod_userdir",
#                               "mod_cgi",
#                                "mod_accesslog"

## a static document-root, for virtual-hosting take look at the
## server.virtual-* options
server.document-root        = "{$document_root}"
{$captive_portal_rewrite}

# Maximum idle time with nothing being written (php downloading)
server.max-write-idle = 999

## where to send error-messages to
server.errorlog             = "/var/log/lighttpd.error.log"

# files to check for if .../ is requested
server.indexfiles           = ( "index.php", "index.html",
                                "index.htm", "default.htm" )

# mimetype mapping
mimetype.assign             = (
  ".pdf"          =>      "application/pdf",
  ".sig"          =>      "application/pgp-signature",
  ".spl"          =>      "application/futuresplash",
  ".class"        =>      "application/octet-stream",
  ".ps"           =>      "application/postscript",
  ".torrent"      =>      "application/x-bittorrent",
  ".dvi"          =>      "application/x-dvi",
  ".gz"           =>      "application/x-gzip",
  ".pac"          =>      "application/x-ns-proxy-autoconfig",
  ".swf"          =>      "application/x-shockwave-flash",
  ".tar.gz"       =>      "application/x-tgz",
  ".tgz"          =>      "application/x-tgz",
  ".tar"          =>      "application/x-tar",
  ".zip"          =>      "application/zip",
  ".mp3"          =>      "audio/mpeg",
  ".m3u"          =>      "audio/x-mpegurl",
  ".wma"          =>      "audio/x-ms-wma",
  ".wax"          =>      "audio/x-ms-wax",
  ".ogg"          =>      "audio/x-wav",
  ".wav"          =>      "audio/x-wav",
  ".gif"          =>      "image/gif",
  ".jpg"          =>      "image/jpeg",
  ".jpeg"         =>      "image/jpeg",
  ".png"          =>      "image/png",
  ".xbm"          =>      "image/x-xbitmap",
  ".xpm"          =>      "image/x-xpixmap",
  ".xwd"          =>      "image/x-xwindowdump",
  ".css"          =>      "text/css",
  ".html"         =>      "text/html",
  ".htm"          =>      "text/html",
  ".js"           =>      "text/javascript",
  ".asc"          =>      "text/plain",
  ".c"            =>      "text/plain",
  ".conf"         =>      "text/plain",
  ".text"         =>      "text/plain",
  ".txt"          =>      "text/plain",
  ".dtd"          =>      "text/xml",
  ".xml"          =>      "text/xml",
  ".mpeg"         =>      "video/mpeg",
  ".mpg"          =>      "video/mpeg",
  ".mov"          =>      "video/quicktime",
  ".qt"           =>      "video/quicktime",
  ".avi"          =>      "video/x-msvideo",
  ".asf"          =>      "video/x-ms-asf",
  ".asx"          =>      "video/x-ms-asf",
  ".wmv"          =>      "video/x-ms-wmv",
  ".bz2"          =>      "application/x-bzip",
  ".tbz"          =>      "application/x-bzip-compressed-tar",
  ".tar.bz2"      =>      "application/x-bzip-compressed-tar"
 )

# Use the "Content-Type" extended attribute to obtain mime type if possible
#mimetypes.use-xattr        = "enable"

#### accesslog module
#accesslog.filename          = "/dev/null"

## deny access the file-extensions
#
# ~    is for backupfiles from vi, emacs, joe, ...
# .inc is often used for code includes which should in general not be part
#      of the document-root
url.access-deny             = ( "~", ".inc" )


######### Options that are good to be but not neccesary to be changed #######

## bind to port (default: 80)
server.port                = {$lighty_port}

## error-handler for status 404
#server.error-handler-404   = "/error-handler.html"
#server.error-handler-404   = "/error-handler.php"

## to help the rc.scripts
server.pid-file            = "/var/run/{$pid_file}"

## virtual directory listings
server.dir-listing         = "disable"

## enable debugging
debug.log-request-header   = "disable"
debug.log-response-header  = "disable"
debug.log-request-handling = "disable"
debug.log-file-not-found   = "disable"

#### compress module
#compress.cache-dir         = "/tmp/lighttpd/cache/compress/"
#compress.filetype          = ("text/plain", "text/html")

#server.network-backend = "writev"

{$server_upload_dirs}

{$server_max_request_size}

{$fastcgi_config}

{$cgi_config}

{$captive_portal_mod_evasive}

EOD;

	$cert = str_replace("\r", "", $cert);
	$key = str_replace("\r", "", $key);

	$cert = str_replace("\n\n", "\n", $cert);
	$key = str_replace("\n\n", "\n", $key);

	if($cert <> "" and $key <> "") {
		$fd = fopen("{$g['varetc_path']}/{$cert_location}", "w");
		if (!$fd) {
			printf("Error: cannot open cert.pem in system_webgui_start().\n");
			return 1;
		}
		chmod("{$g['varetc_path']}/{$cert_location}", 0600);
		fwrite($fd, $cert);
		fwrite($fd, "\n");
		fwrite($fd, $key);
		fclose($fd);
		$lighty_config .= "\n";
		$lighty_config .= "## ssl configuration\n";
		$lighty_config .= "ssl.engine = \"enable\"\n";
		$lighty_config .= "ssl.pemfile = \"{$g['varetc_path']}/{$cert_location}\"\n\n";
	}

	$fd = fopen("{$filename}", "w");
	if (!$fd) {
		printf("Error: cannot open {$filename} in system_generate_lighty_config().\n");
		return 1;
	}
	fwrite($fd, $lighty_config);
	fclose($fd);

	return 0;

}

function system_password_configure() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_password_configure() being called $mt\n";
	}

	/* sync passwords */
	sync_webgui_passwords();

	/* !NOTE! conf_mount_ro is done by sync_webgui_passwords() */

	return 0;
}

function system_timezone_configure() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_timezone_configure() being called $mt\n";
	}

	$syscfg = $config['system'];

	if ($g['booting'])
		echo "Setting timezone...";

	/* extract appropriate timezone file */
	$timezone = $syscfg['timezone'];
	if (!$timezone)
		$timezone = "Etc/UTC";

	conf_mount_rw();

	exec("LANG=C /usr/bin/tar xzfO /usr/share/zoneinfo.tgz " .
		escapeshellarg($timezone) . " > /etc/localtime");

	mwexec("sync");
	conf_mount_ro();

	if ($g['booting'])
		echo "done.\n";
}

function system_ntp_configure() {
	global $config, $g;

	$syscfg = $config['system'];

	/* open configuration for wrting or bail */
	$fd = fopen("{$g['varetc_path']}/ntpd.conf","w");
	if(!$fd) {
		log_error("Could not open {$g['varetc_path']}/ntpd.conf for writing");
		return;
	}

	fwrite($fd, "# \n");
	fwrite($fd, "# pfSense OpenNTPD configuration file \n");
	fwrite($fd, "# \n\n");

	/* foreach through servers and write out to ntpd.conf */
	foreach (explode(' ', $syscfg['timeservers']) as $ts)
		fwrite($fd, "servers {$ts}\n");

    /* server config is in coregui1 */
	$xmlsettings = $config['installedpackages']['openntpd']['config'][0];
	if ($xmlsettings['enable'] == 'on') {
		$ifaces = explode(',', $xmlsettings['interface']);
		$ifaces = array_map('convert_friendly_interface_to_real_interface_name', $ifaces);
		$ifaces = array_filter($ifaces, 'does_interface_exist');
		$ips = array_map('find_interface_ip', $ifaces);
		foreach ($ips as $ip) {
			if (is_ipaddr($ip))
				fwrite($fd, "listen on $ip\n");
		}
	}

	fwrite($fd, "\n");

	/* slurp! */
	fclose($fd);

	/* if openntpd is running, kill it */
	while(is_process_running("ntpd")) {
		mwexec("/usr/bin/killall ntpd", true);
	}

	/* if /var/empty does not exist, create it */
	if(!is_dir("/var/empty"))
		exec("/bin/mkdir -p /var/empty && chmod ug+rw /var/empty/.");

	if($g['booting'])
		return;

	/* start opentpd, set time now and use /var/etc/ntpd.conf */
	exec("/usr/local/sbin/ntpd -s -f {$g['varetc_path']}/ntpd.conf");

}

function sync_system_time() {
	global $config, $g;

	$syscfg = $config['system'];

	if ($g['booting'])
		echo "Syncing system time before startup...";

	/* foreach through servers and write out to ntpd.conf */
	foreach (explode(' ', $syscfg['timeservers']) as $ts) {
		mwexec("/usr/sbin/ntpdate -s $ts");
	}
	
	if ($g['booting'])
		echo "done.\n";
	
}

function system_halt() {
	global $g;

	system_reboot_cleanup();

	mwexec("nohup /etc/rc.halt > /dev/null 2>&1 &");
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
	mwexec("/usr/local/bin/beep.sh stop");
	captiveportal_radius_stop_all();
}

function system_do_shell_commands($early = 0) {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_do_shell_commands() being called $mt\n";
	}

	if ($early)
		$cmdn = "earlyshellcmd";
	else
		$cmdn = "shellcmd";

	if (is_array($config['system'][$cmdn])) {

		/* *cmd is an array, loop through */
		foreach ($config['system'][$cmdn] as $cmd) {
			exec($cmd);
		}

	} elseif($config['system'][$cmdn] <> "") {

		/* execute single item */
		exec($config['system'][$cmdn]);

	}
}

function system_console_configure() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_console_configure() being called $mt\n";
	}

	if (isset($config['system']['disableconsolemenu'])) {
		touch("{$g['varetc_path']}/disableconsole");
	} else {
		unlink_if_exists("{$g['varetc_path']}/disableconsole");
	}
}

function system_dmesg_save() {
	global $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_dmesg_save() being called $mt\n";
	}

	$dmesg = "";
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
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_set_harddisk_standby() being called $mt\n";
	}

	if (isset($config['system']['harddiskstandby'])) {
		if ($g['booting']) {
			echo 'Setting hard disk standby... ';
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
					echo "done.\n";
				}
			} else if ($g['booting']) {
				echo "failed!\n";
			}
		} else if ($g['booting']) {
			echo "failed!\n";
		}
	}
}

function system_setup_sysctl() {
	global $config;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_setup_sysctl() being called $mt\n";
	}

	$sysctl = return_filename_as_array("/etc/sysctl.conf");
	foreach($sysctl as $sysc) {
		$sysc = rtrim($sysc);
		if($sysc <> "")
			mwexec("sysctl {$sysc} 2>/dev/null");
	}
	if (isset($config['system']['sharednet'])) {
		system_disable_arp_wrong_if();
	}
}

function system_disable_arp_wrong_if() {
	global $config;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_disable_arp_wrong_if() being called $mt\n";
	}
	mwexec("/sbin/sysctl -n net.link.ether.inet.log_arp_wrong_iface=0");
	mwexec("/sbin/sysctl -n net.link.ether.inet.log_arp_movements=0");
}

function system_enable_arp_wrong_if() {
	global $config;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_enable_arp_wrong_if() being called $mt\n";
	}
	mwexec("/sbin/sysctl -n net.link.ether.inet.log_arp_wrong_iface=1");
	mwexec("/sbin/sysctl -n net.link.ether.inet.log_arp_movements=1");
}

?>