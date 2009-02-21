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

function activate_sysctls() {
	global $config, $g;
	
	exec("/sbin/sysctl net.enc.out.ipsec_bpf_mask=0x00000000");
	exec("/sbin/sysctl net.enc.out.ipsec_filter_mask=0x00000001");
	exec("/sbin/sysctl net.enc.in.ipsec_bpf_mask=0x00000000");
	exec("/sbin/sysctl net.enc.in.ipsec_filter_mask=0x00000002");

	if (is_array($config['sysctl'])) 
		foreach ($config['sysctl']['item'] as $tunable) 
			mwexec("sysctl " . $tunable['tunable'] . "=\"" 
				. $tunable['value'] .  "\"");
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

	/* setup static routes for DNS servers. */
	for ($dnscounter=1; $dnscounter<5; $dnscounter++) {
		/* setup static routes for dns servers */
		$dnsgw = "dns{$dnscounter}gwint";
		if (isset($config['system'][$dnsgw])) {
			$interface = $config['system'][$dnsgw];
			if (($interface <> "") && ($interface <> "none")) {
				$gatewayip = get_interface_gateway($interface);
				if(is_ipaddr($gatewayip)) {
					/* dns server array starts at 0 */
					$dnscountermo = $dnscounter - 1;
					mwexec("route delete -host {$syscfg['dnsserver'][$dnscountermo]}");
					mwexec("route add -host {$syscfg['dnsserver'][$dnscountermo]} {$gatewayip}");
				}
			}
		}
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
	return mwexec("/bin/hostname " .
		escapeshellarg("{$syscfg['hostname']}.{$syscfg['domain']}"));
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

	exec("/usr/bin/netstat -rn", $route_arr, $retval);
	$route_str = implode("\n", $route_arr);

	/* clear out old routes, if necessary */
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

	/* if list */
	$iflist = get_configured_interface_list();

	$dont_remove_route = false;
	foreach ($iflist as $ifent => $ifname) {
		/* do not process interfaces that will end up with gateways */
		if (interface_has_gateway($ifent))
			$dont_remove_route = true;
	}

	if($config['interfaces']['wan']['ipaddr'] == "carpdev-dhcp")
		$dont_remove_route = true;

	if($dont_remove_route == false) {
		/* remove default route */
		mwexec("/sbin/route delete default", true);
	}

	$dont_add_route = false;
	/* if OLSRD is enabled, allow WAN to house DHCP. */
	if($config['installedpackages']['olsrd']) {
		foreach($config['installedpackages']['olsrd']['config'] as $olsrd) {
			if($olsrd['enabledyngw'] == "on") {
				$dont_add_route = true;
			}
		}
	}

	if($dont_add_route == false) {
		if(is_array($config['gateways']['gateway_item'])) {
			foreach($config['gateways']['gateway_item'] as $gateway) {
		        	if(isset($gateway['defaultgw'])) {
					$gatewayip = $gateway['gateway'];
					$interfacegw = $gateway['interface'];
				}
			}
			if(($interfacegw <> "bgpd") && (is_ipaddr($gatewayip)))
				mwexec("/sbin/route add default " . escapeshellarg($gatewayip), true);
		} else {
			/* FIXME */
			/* adding gateway for 1.2-style configs without the new
			  gateway setup configured.
			  Force WAN to be default gateway because that is the
			  1.2 behavior.
			*/
			if (is_ipaddr($config['interfaces']['wan']['gateway'])) {
				$gatewayip = $config['interfaces']['wan']['gateway'];
				mwexec("/sbin/route add default " . escapeshellarg($gatewayip), true);
			}
		}
	}

	if (is_array($config['staticroutes']['route'])) {

		$fd = fopen("{$g['vardb_path']}/routes.db", "w");
		if (!$fd) {
			printf("Error: cannot open routes DB file in system_routing_configure().\n");
			return 1;
		}

		foreach ($config['staticroutes']['route'] as $rtent) {
			if(is_array($config['gateways']['gateway_item'])) {
				foreach($config['gateways']['gateway_item'] as $gateway) {
					if($rtent['gateway'] == $gateway['name']) {
						$gatewayip = $gateway['gateway'];
						$interfacegw = $gateway['interface'];
					}
				}
			}
			if((is_ipaddr($rtent['gateway'])) && ($gatewayip == ""))  {
				$gatewayip = $rtent['gateway'];
				$interfacegw = $rtent['interface'];
			}			
			if(isset($rtent['interfacegateway'])) {
				mwexec("/sbin/route add " . escapeshellarg($rtent['network']) .
					" -iface " . escapeshellarg(convert_friendly_interface_to_real_interface_name($interfacegw)));
			} else {
				mwexec("/sbin/route add " . escapeshellarg($rtent['network']) .
					" " . escapeshellarg($gatewayip));
			}
			/* record route so it can be easily removed later (if necessary) */
			fwrite($fd, $rtent['network'] . "\n");
		}
		fclose($fd);
	}

	return 0;
}


function system_routing_enable() {
	global $config, $g;
	$retval = false;

	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_routing_enable() being called $mt\n";
	}

	$retval = mwexec("/sbin/sysctl net.inet.ip.forwarding=1") && mwexec("/sbin/sysctl net.inet6.ip6.forwarding=1");

	return $retval;

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
local1.*					%{$g['varlog_path']}/relayd.log
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
		fwrite($fd, $syslogconf);
		fclose($fd);

		// Are we logging to a least one remote server ?
		if(strpos($syslogconf, "@") != false)
			$retval = mwexec("/usr/sbin/syslogd -s -f {$g['varetc_path']}/syslog.conf");
		else
			$retval = mwexec("/usr/sbin/syslogd -ss -f {$g['varetc_path']}/syslog.conf");

	} else {
		$retval = mwexec("/usr/sbin/syslogd -ss");
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

	chdir($g['www_path']);

	/* defaults */
	$portarg = "80";
	$crt = "";
	$key = "";

	/* non-standard port? */
	if ($config['system']['webgui']['port'])
		$portarg = "{$config['system']['webgui']['port']}";

	if ($config['system']['webgui']['protocol'] == "https") {

		$cert =& lookup_cert($config['system']['webgui']['ssl-certref']);
		if(is_array($cert) && $cert['crt'] && $cert['prv']) {
			$crt = base64_decode($cert['crt']);
			$key = base64_decode($cert['prv']);
			if(!$config['system']['webgui']['port'])
				$portarg = "443";
		} else
			log_error("Invalid webConfigurator https certificate, defaulting to http");
	}

	/* generate lighttpd configuration */
	system_generate_lighty_config("{$g['varetc_path']}/lighty-webConfigurator.conf",
		$crt, $key, "lighty-webConfigurator.pid", $portarg, "/usr/local/www/");

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

	if($avail > 383) {
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

	} else if ($avail > 0 and $avail < 128) {
		$bin_environment = <<<EOC
	"bin-environment" => (
		"PHP_FCGI_CHILDREN" => "1",
		"PHP_FCGI_MAX_REQUESTS" => "{$max_requests}"
	),

EOC;
	} else
		$bin_environment = "";
		
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
			"min-procs" => 2,
			"max-procs" => {$max_procs},
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
server.network-backend		= "freebsd-sendfile"  ## was writev - Fixes 7.x upload issues

## modules to load
server.modules              =   (
									{$captive_portal_module}
									"mod_access", "mod_accesslog", "mod_expire", "mod_compress",
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

{$server_upload_dirs}

{$server_max_request_size}

{$fastcgi_config}

{$cgi_config}

{$captive_portal_mod_evasive}

# Turn on Lighty caching directives
compress.cache-dir         = "/tmp/"
compress.filetype          = ("text/plain", "text/html", "text/javascript", "text/css")

expire.url = (
				"" => "access 50 hours",	
        )

server.use-ipv6 = "enable"

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

	/*
	 * Hard-code sysctl knob to ensure that lighttpd would work
	 * with IPv4 + IPv6.
	 */
	mwexec("/sbin/sysctl net.inet6.ip6.v6only=0");

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

	/* Setup listener(s) if the user has configured one */
        if ($config['installedpackages']['openntpd']) {
    		/* server config is in coregui1 */
		$xmlsettings = $config['installedpackages']['openntpd']['config'][0];
		if ($xmlsettings['enable'] == 'on') {
			$ifaces = explode(',', $xmlsettings['interface']);
			$ifaces = array_map('convert_friendly_interface_to_real_interface_name', $ifaces);
			$ifaces = array_filter($ifaces, 'does_interface_exist');
			$ips = array_map('find_interface_ip', $ifaces);
			foreach ($ips as $ip) {
				if (is_ipaddr($ip) || Net_IPv6::checkIPv6($ip))
					fwrite($fd, "listen on $ip\n");
			}
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

	activate_sysctls();	

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

function enable_watchdog() {
	global $config;
	$install_watchdog = false;
	$supported_watchdogs = array("Geode");
	$file = file_get_contents("/var/log/dmesg.boot");
	foreach($supported_watchdogs as $sd) {
		if(stristr($file, "Geode")) {
			$install_watchdog = true;
		}
	}
	if($install_watchdog == true) {
		if(is_process_running("watchdogd"))
			mwexec("/usr/bin/killall watchdogd", true);
		exec("/usr/sbin/watchdogd");
	}
}

?>