<?php
/* $Id$ */
/* Run various commands and collect their output into HTML tables.
 * Jim McBeath <jimmc@macrovision.com> Nov 2003
 *
 * (modified for m0n0wall by Manuel Kasper <mk@neon1.net>)
 * (modified for pfSense by Scott Ullrich geekgod@pfsense.com)
 *
 */
/*
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1.	Redistributions of source code must retain the above copyright notice,
		this list of conditions and the following disclaimer.

	2.	Redistributions in binary form must reproduce the above copyright
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
/*
	pfSense_BUILDER_BINARIES:	/usr/bin/vmstat	/usr/bin/netstat	/sbin/dmesg	/sbin/mount	/sbin/setkey	/usr/local/sbin/pftop
	pfSense_BUILDER_BINARIES:	/sbin/pfctl	/sbin/sysctl	/usr/bin/top	/usr/bin/netstat	/sbin/pfctl	/sbin/ifconfig
	pfSense_MODULE:	support
*/

##|+PRIV
##|*IDENT=page-hidden-detailedstatus
##|*NAME=Hidden: Detailed Status page
##|*DESCR=Allow access to the 'Hidden: Detailed Status' page.
##|*MATCH=status.php*
##|-PRIV

/* Execute a command, with a title, and generate an HTML table
 * showing the results.
 */

/* include all configuration functions */
require_once("guiconfig.inc");
require_once("functions.inc");
$output_path = "/tmp/status_output/";
$output_file = "/tmp/status_output.tgz";

if (is_dir($output_path)) {
	unlink_if_exists("{$output_path}/*");
	@rmdir($output_path);
}
unlink_if_exists($output_file);
mkdir($output_path);

function doCmdT($title, $command) {
	global $output_path, $output_file;
	/* Fixup output directory */

	$rubbish = array('|', '-', '/', '.', ' ');  /* fixes the <a> tag to be W3C compliant */
	echo "\n<a name=\"" . str_replace($rubbish, '', $title) . "\" id=\"" . str_replace($rubbish, '', $title) . "\"></a>\n";

	print('<div class="panel panel-default">');
	print(	  '<div class="panel-heading">' . $title . '</div>');
	print(	  '<div class="panel-body">');
	print(		  '<pre>');

	if ($command == "dumpconfigxml") {
		$ofd = @fopen("{$output_path}/config-sanitized.xml", "w");
		$fd = @fopen("/conf/config.xml", "r");
		if ($fd) {
			while (!feof($fd)) {
				$line = fgets($fd);
				/* remove sensitive contents */
				$line = preg_replace("/<password>.*?<\\/password>/", "<password>xxxxx</password>", $line);
				$line = preg_replace("/<pre-shared-key>.*?<\\/pre-shared-key>/", "<pre-shared-key>xxxxx</pre-shared-key>", $line);
				$line = preg_replace("/<rocommunity>.*?<\\/rocommunity>/", "<rocommunity>xxxxx</rocommunity>", $line);
				$line = preg_replace("/<prv>.*?<\\/prv>/", "<prv>xxxxx</prv>", $line);
				$line = preg_replace("/<shared_key>.*?<\\/shared_key>/", "<shared_key>xxxxx</shared_key>", $line);
				$line = preg_replace("/<tls>.*?<\\/tls>/", "<tls>xxxxx</tls>", $line);
				$line = preg_replace("/<ipsecpsk>.*?<\\/ipsecpsk>/", "<ipsecpsk>xxxxx</ipsecpsk>", $line);
				$line = preg_replace("/<md5-hash>.*?<\\/md5-hash>/", "<md5-hash>xxxxx</md5-hash>", $line);
				$line = preg_replace("/<md5password>.*?<\\/md5password>/", "<md5password>xxxxx</md5password>", $line);
				$line = preg_replace("/<nt-hash>.*?<\\/nt-hash>/", "<nt-hash>xxxxx</nt-hash>", $line);
				$line = preg_replace("/<radius_secret>.*?<\\/radius_secret>/", "<radius_secret>xxxxx</radius_secret>", $line);
				$line = preg_replace("/<ldap_bindpw>.*?<\\/ldap_bindpw>/", "<ldap_bindpw>xxxxx</ldap_bindpw>", $line);
				$line = preg_replace("/<passwordagain>.*?<\\/passwordagain>/", "<passwordagain>xxxxx</passwordagain>", $line);
				$line = preg_replace("/<crypto_password>.*?<\\/crypto_password>/", "<crypto_password>xxxxx</crypto_password>", $line);
				$line = preg_replace("/<crypto_password2>.*?<\\/crypto_password2>/", "<crypto_password2>xxxxx</crypto_password2>", $line);
				$line = str_replace("\t", "    ", $line);
				echo htmlspecialchars($line, ENT_NOQUOTES);
				fwrite($ofd, $line);
			}
		}
		fclose($fd);
		fclose($ofd);
	} else {
		$ofd = @fopen("{$output_path}/{$title}.txt", "w");
		$execOutput = "";
		$execStatus = "";
		exec ($command . " 2>&1", $execOutput, $execStatus);
		for ($i = 0; isset($execOutput[$i]); $i++) {
			if ($i > 0) {
				echo "\n";
			}
			echo htmlspecialchars($execOutput[$i], ENT_NOQUOTES);
			fwrite($ofd, $execOutput[$i] . "\n");
		}
		fclose($ofd);
	}

	print(		  '</pre>');
	print(	  '</div>');
	print('</div>');
}

/* Define a command, with a title, to be executed later. */
function defCmdT($title, $command) {
	global $commands;
	$title = htmlspecialchars($title, ENT_NOQUOTES);
	$commands[] = array($title, $command);
}

/* List all of the commands as an index. */
function listCmds() {
	global $currentDate;
	global $commands;

	$rubbish = array('|', '-', '/', '.', ' ');	/* fixes the <a> tag to be W3C compliant */

	print('<div class="panel panel-default">');
	print(	  '<div class="panel-heading">' . gettext("System status on ") . $currentDate . '</div>');
	print(	  '<div class="panel-body">');
	print(	  '    <div class="content">');
	print("\n<p>" . gettext("This status page includes the following information") . ":\n");
	print("<ul>\n");
	for ($i = 0; isset($commands[$i]); $i++ ) {
		print("\t<li><strong><a href=\"#" . str_replace($rubbish,'',$commands[$i][0]) . "\">" . $commands[$i][0] . "</a></strong></li>\n");
	}

	print("</ul>\n");
	print('	       </div>');
	print('	   </div>');
	print('</div>');
}

/* Execute all of the commands which were defined by a call to defCmd. */
function execCmds() {
	global $commands;
	for ($i = 0; isset($commands[$i]); $i++) {
		doCmdT($commands[$i][0], $commands[$i][1]);
	}
}

global $g, $config;

/* Set up all of the commands we want to execute. */

/* System stats/info */
defCmdT("System uptime","/usr/bin/uptime");
defCmdT("Interfaces","/sbin/ifconfig -a");
defCmdT("Interface Statistics","/usr/bin/netstat -ni");
defCmdT("Top Process Info", "/usr/bin/top | /usr/bin/head -n5");
defCmdT("Processes","/bin/ps xauww");
defCmdT("Mounted Filesystems", "/sbin/mount");
defCmdT("Free Disk Space","/bin/df -hi");
defCmdT("Routing tables","/usr/bin/netstat -nWr");
defCmdT("Mbuf Usage","/usr/bin/netstat -mb");
defCmdT("VMStat", "/usr/bin/vmstat -afimsz");
defCmdT("Sockets", "/usr/bin/sockstat");

/* Firewall rules and info */
defCmdT("Generated Ruleset","/bin/cat {$g['tmp_path']}/rules.debug");
defCmdT("Generated Ruleset Limiters","/bin/cat {$g['tmp_path']}/rules.limiter");
defCmdT("Generated Ruleset Limits","/bin/cat {$g['tmp_path']}/rules.limits");
defCmdT("pf NAT Rules", "/sbin/pfctl -vvsn");
defCmdT("pf Firewall Rules", "/sbin/pfctl -vvsr");
defCmdT("pf Tables","/sbin/pfctl -vs Tables");
defCmdT("pf State Table Contents", "/sbin/pfctl -ss");
defCmdT("pf Info", "/sbin/pfctl -si");
defCmdT("pf Show All", "/sbin/pfctl -sa");
defCmdT("pf Queues","/sbin/pfctl -s queue -v");
defCmdT("pf OSFP","/sbin/pfctl -s osfp");
defCmdT("pfsync stats","/usr/bin/netstat -s -ppfsync");
defCmdT("pftop Default","/usr/local/sbin/pftop -a -b");
defCmdT("pftop Long","/usr/local/sbin/pftop -w 150 -a -b -v long");
defCmdT("pftop Queue","/usr/local/sbin/pftop -w 150 -a -b -v queue");
defCmdT("pftop Rules","/usr/local/sbin/pftop -w 150 -a -b -v rules");
defCmdT("pftop Size","/usr/local/sbin/pftop -w 150 -a -b -v size");
defCmdT("pftop Speed","/usr/local/sbin/pftop -w 150 -a -b -v speed");
if (isset($config['captiveportal']) && is_array($config['captiveportal'])) {
	foreach ($config['captiveportal'] as $cpZone => $cpdata) {
		if (isset($cpdata['enable']))
			defCmdT("IPFW rules for {$cpdata['zone']}", "/sbin/ipfw -x " . escapeshellarg($cpdata['zoneid']) . " show");
	}
}

/* Configuration Files */
defCmdT("Contents of var run", "/bin/ls /var/run");
defCmdT("Contents of conf", "/bin/ls /conf");
defCmdT("config.xml","dumpconfigxml");
defCmdT("resolv.conf","/bin/cat /etc/resolv.conf");
defCmdT("DHCP Configuration","/bin/cat /var/dhcpd/etc/dhcpd.conf");
defCmdT("DHCPv6 Configuration","/bin/cat /var/dhcpd/etc/dhcpdv6.conf");
defCmdT("strongSwan config","/bin/cat /var/etc/ipsec/strongswan.conf");
defCmdT("IPsec config","/bin/cat /var/etc/ipsec/ipsec.conf");
defCmdT("IPsec Status","/usr/local/sbin/ipsec statusall");
defCmdT("SPD","/sbin/setkey -DP");
defCmdT("SAD","/sbin/setkey -D");
if (file_exists("/cf/conf/upgrade_log.txt")) {
	defCmdT("Upgrade Log", "/bin/cat /cf/conf/upgrade_log.txt");
}
if (file_exists("/boot/loader.conf")) {
	defCmdT("Loader Configuration", "/bin/cat /boot/loader.conf");
}
if (file_exists("/boot/loader.conf.local")) {
	defCmdT("Loader Configuration (Local)", "/bin/cat /boot/loader.conf.local");
}
if (file_exists("/var/run/apinger.status")) {
	defCmdT("Gateway Status", "/bin/cat /var/run/apinger.status");
}
if (file_exists("/var/etc/apinger.conf")) {
	defCmdT("Gateway Monitoring Config", "/bin/cat /var/etc/apinger.conf");
}
if (file_exists("/var/etc/filterdns.conf")) {
	defCmdT("Filter DNS Daemon Config", "/bin/cat /var/etc/filterdns.conf");
}
if(isset($config['system']['usefifolog']))  {
	defCmdT("last 1000 system log entries","/usr/sbin/fifolog_reader /var/log/system.log 2>&1 | tail -n 1000");
	defCmdT("last 1000 DHCP log entries","/usr/sbin/fifolog_reader /var/log/dhcpd.log 2>&1 | tail -n 1000");
	defCmdT("last 500 filter log entries","/usr/sbin/fifolog_reader /var/log/filter.log 2>&1 | tail -n 500");
	defCmdT("last 1000 gateways log entries","/usr/sbin/fifolog_reader /var/log/gateways.log 2>&1 | tail -n 1000");
	defCmdT("last 1000 IPsec log entries","/usr/sbin/fifolog_reader /var/log/ipsec.log 2>&1 | tail -n 1000");
	defCmdT("last 1000 L2TP log entries","/usr/sbin/fifolog_reader /var/log/l2tps.log 2>&1 | tail -n 1000");
	defCmdT("last 1000 NTP log entries","/usr/sbin/fifolog_reader /var/log/ntpd.log 2>&1 | tail -n 1000");
	defCmdT("last 1000 OpenVPN log entries","/usr/sbin/fifolog_reader /var/log/openvpn.log 2>&1 | tail -n 1000");
	defCmdT("last 1000 Captive Portal auth log entries","/usr/sbin/fifolog_reader /var/log/portalauth.log 2>&1 | tail -n 1000");
	defCmdT("last 1000 PPP log entries","/usr/sbin/fifolog_reader /var/log/poes.log 2>&1 | tail -n 1000");
	defCmdT("last 1000 relayd log entries","/usr/sbin/fifolog_reader /var/log/relayd.log 2>&1 | tail -n 1000");
	defCmdT("last 1000 resolver log entries","/usr/sbin/fifolog_reader /var/log/resolver.log 2>&1 | tail -n 1000");
	defCmdT("last 1000 routing log entries","/usr/sbin/fifolog_reader /var/log/routing.log 2>&1 | tail -n 1000");
	defCmdT("last 1000 wireless log entries","/usr/sbin/fifolog_reader /var/log/wireless.log 2>&1 | tail -n 1000");
} else {
	defCmdT("last 1000 system log entries","/usr/local/sbin/clog /var/log/system.log 2>&1 | tail -n 1000");
	defCmdT("last 1000 DHCP log entries","/usr/local/sbin/clog /var/log/dhcpd.log 2>&1 | tail -n 1000");
	defCmdT("last 500 filter log entries","/usr/local/sbin/clog /var/log/filter.log 2>&1 | tail -n 500");
	defCmdT("last 1000 gateways log entries","/usr/local/sbin/clog /var/log/gateways.log 2>&1 | tail -n 1000");
	defCmdT("last 1000 IPsec log entries","/usr/local/sbin/clog /var/log/ipsec.log 2>&1 | tail -n 1000");
	defCmdT("last 1000 L2TP log entries","/usr/local/sbin/clog /var/log/l2tps.log 2>&1 | tail -n 1000");
	defCmdT("last 1000 NTP log entries","/usr/local/sbin/clog /var/log/ntpd.log 2>&1 | tail -n 1000");
	defCmdT("last 1000 OpenVPN log entries","/usr/local/sbin/clog /var/log/openvpn.log 2>&1 | tail -n 1000");
	defCmdT("last 1000 Captive Portal auth log entries","/usr/local/sbin/clog /var/log/portalauth.log 2>&1 | tail -n 1000");
	defCmdT("last 1000 PPP log entries","/usr/local/sbin/clog /var/log/poes.log 2>&1 | tail -n 1000");
	defCmdT("last 1000 relayd log entries","/usr/local/sbin/clog /var/log/relayd.log 2>&1 | tail -n 1000");
	defCmdT("last 1000 resolver log entries","/usr/local/sbin/clog /var/log/resolver.log 2>&1 | tail -n 1000");
	defCmdT("last 1000 routing log entries","/usr/local/sbin/clog /var/log/routing.log 2>&1 | tail -n 1000");
	defCmdT("last 1000 wireless log entries","/usr/local/sbin/clog /var/log/wireless.log 2>&1 | tail -n 1000");
}
if (file_exists("/tmp/PHP_errors.log")) {
	defCmdT("PHP Error Log", "/bin/cat /tmp/PHP_errors.log");
}
defCmdT("System Message Buffer","/sbin/dmesg -a");
defCmdT("System Message Buffer (Boot)","/bin/cat /var/log/dmesg.boot");
defCmdT("sysctl values","/sbin/sysctl -a");

exec("/bin/date", $dateOutput, $dateStatus);
$currentDate = $dateOutput[0];

$pgtitle = array("{$g['product_name']}", "status");
include("head.inc");

print_info_box(gettext("Make sure all sensitive information is removed! (Passwords, etc.) before posting " .
			   "information from this page in public places (like mailing lists)") . '<br />' .
		gettext("Common password fields in config.xml have been automatically redacted.") . '<br />' .
		gettext("When the page has finished loading, the output will be stored in {$output_file}. It may be downloaded via scp or ") .
		"<a href=\"/exec.php?dlPath={$output_file}\">" . gettext("Diagnostics > Command Prompt") . '</a>');

listCmds();
execCmds();

include("foot.inc");
