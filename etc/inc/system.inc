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

	/* Enable fast routing, if enabled */
	if(isset($config['staticroutes']['enablefastrouting']))
		mwexec("/sbin/sysctl net.inet.ip.fastforwarding=1");

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
		echo "Starting syslog... ";
	else
		killbypid("{$g['varrun_path']}/syslog.pid");

	if (isset($syslogcfg)) {
		if($config['installedpackages']['package']) {
                        foreach($config['installedpackages']['package'] as $package) {
                                if($package['logging']) {	
					$pkgfacilities[] = $package['logging']['facilityname'];
					$facilitylist = implode(',', $pkgfacilities);
					mwexec("clog -i -s 10000 {$g['varlog_path']}/{$package['logging']['logfilename']}");
                                	$syslogconf .= "!{$package['logging']['facilityname']}\n*.*\t\t\t\t\t\t%{$g['varlog_path']}/{$package['logging']['logfilename']}\n!-{$facilitylist}\n";
				}
                        }
                }
		/* write syslog.conf */
		$fd = fopen("{$g['varetc_path']}/syslog.conf", "w");
		if (!$fd) {
			printf("Error: cannot open syslog.conf in system_syslogd_start().\n");
			return 1;
		}
		if (!isset($syslogcfg['disablelocallogging'])) {
		$syslogconf .= <<<EOD
!racoon
*.*						%{$g['varlog_path']}/ipsec.log
!-racoon,{$facilitylist}
local0.*					%{$g['varlog_path']}/filter.log
local3.*					%{$g['varlog_path']}/vpn.log
local4.*					%{$g['varlog_path']}/portalauth.log
local7.*					%{$g['varlog_path']}/dhcpd.log
*.notice;kern.debug;lpr.info;mail.crit;		%{$g['varlog_path']}/system.log
news.err;local0.none;local3.none;local4.none;	%{$g['varlog_path']}/system.log
local7.none					%{$g['varlog_path']}/system.log
security.*					%{$g['varlog_path']}/system.log
auth.info;authpriv.info;daemon.info		%{$g['varlog_path']}/system.log
local1.*					%{$g['varlog_path']}/slbd.log
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

		$retval = mwexec("/usr/sbin/syslogd -s -f {$g['varetc_path']}/syslog.conf");

	} else {
		$retval = mwexec("/usr/sbin/syslogd -ss");
	}

	if ($g['booting'])
		echo "done.\n";

	return $retval;
}

function system_pccard_start() {
	global $config, $g;

	if ($g['booting'])
		echo "Initializing PCMCIA... ";

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

function system_generate_lighty_config($filename, $cert, $key, $pid_file,
	$port, $document_root = "/usr/local/www/",
	$cert_location = "cert.pem", $max_procs,
	$max_requests = "1", $fast_cgi_enable = true) {
	
	if($port <> "")
		$lighty_port = $port;
	else
		$lighty_port = "80";
	
	if($fast_cgi_enable == true) {
		$module = "\"mod_fastcgi\", \"mod_cgi\"";
		$cgi_config = "";
		$fastcgi_config = <<<EOD
#### fastcgi module
## read fastcgi.txt for more info
fastcgi.server             = ( ".php" =>
                               ( "localhost" =>
                                 (
                                   "socket" => "/tmp/php-fastcgi.socket",
				   "min-procs" => 1,
				   "max-procs" => {$max_procs},
				   "max-load-per-proc" => 1,
				   "idle-timeout" => 1,
				   "bin-environment" => ( 
				      "PHP_FCGI_CHILDREN" => "{$max_procs}",
				      "PHP_FCGI_MAX_REQUESTS" => "{$max_requests}"
				   ),				   
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

## modules to load
server.modules              =   (
				  "mod_access",
                                  {$module}
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

## where to send error-messages to
#server.errorlog             = "/var/log/lighttpd.error.log"

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

{$fastcgi_config}

{$cgi_config}

EOD;

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

	$fd = fopen("{$g['varetc_path']}/{$filename}", "w");
	if (!$fd) {
		printf("Error: cannot open {$g['varetc_path']}/{$filename} in system_generate_lighty_config().\n");
		return 1;
	}
	fwrite($fd, $lighty_config);
	fclose($fd);

	return 0;

}

function system_password_configure() {
	global $config, $g;

	conf_mount_rw();

	$fd = fopen("{$g['varrun_path']}/htpasswd", "w");
	if (!$fd) {
		printf("Error: cannot open htpasswd in system_password_configure().\n");
		return 1;
	}

	/* set admin account */
	$username = "admin";
	fwrite($fd, $username . ":" . $config['system']['password'] . "\n");

	/* set defined user account */
	if($username <> "admin") {
		$username = $config['system']['username'];
		fwrite($fd, $username . ":" . $config['system']['password'] . "\n");
	}

	fclose($fd);
	chmod("{$g['varrun_path']}/htpasswd", 0600);	
	
	/* sync passwords */
	sync_webgui_passwords();

	/* !NOTE! conf_mount_ro is done by sync_webgui_passwords() */

	return 0;
}

function system_timezone_configure() {
	global $config, $g;

	$syscfg = $config['system'];

	if ($g['booting'])
		echo "Setting timezone... ";

	/* extract appropriate timezone file */
	$timezone = $syscfg['timezone'];
	if (!$timezone)
		$timezone = "Etc/UTC";

	conf_mount_rw();

	exec("/usr/bin/tar xzfO /usr/share/zoneinfo.tgz " .
		escapeshellarg($timezone) . " > /etc/localtime");

	conf_mount_ro();

	if ($g['booting'])
		echo "done.\n";
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

function system_do_extensions($early = false) {
	global $config, $g;

	if (!is_dir("{$g['etc_path']}/inc/ext"))
		return;

	$dh = @opendir("{$g['etc_path']}/inc/ext");
	if ($dh) {
		while (($extd = readdir($dh)) !== false) {
			if (($extd === ".") || ($extd === ".."))
				continue;
			$rcfile = "{$g['etc_path']}/inc/ext/" . $extd . "/" . ($early ? "rc.early" : "rc");
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

	$sysctl = return_filename_as_array("/etc/sysctl.conf");
	foreach($sysctl as $sysc) {
		if($sysc <> "")
			mwexec("sysctl {$sysc}");
	}
	if (isset($config['system']['sharednet'])) {
		system_disable_arp_wrong_if();
	}
}

function system_disable_arp_wrong_if() {
	mwexec("/sbin/sysctl -n net.link.ether.inet.log_arp_wrong_iface=0");
}

function system_enable_arp_wrong_if() {
	mwexec("/sbin/sysctl -n net.link.ether.inet.log_arp_wrong_iface=1");
}


?>
