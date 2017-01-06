<?php
/*
 * status.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://neon1.net/m0n0wall)
 * Copyright (c) 2003 Jim McBeath <jimmc@macrovision.com>
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

##|+PRIV
##|*IDENT=page-hidden-detailedstatus
##|*NAME=Hidden: Detailed Status
##|*DESCR=Allow access to the 'Hidden: Detailed Status' page.
##|*MATCH=status.php*
##|-PRIV

/* Execute a command, with a title, and generate an HTML table
 * showing the results.
 */

/* include all configuration functions */
require_once("guiconfig.inc");
require_once("functions.inc");
require_once("gwlb.inc");
$output_path = "/tmp/status_output/";
$output_file = "/tmp/status_output.tgz";

if (is_dir($output_path)) {
	unlink_if_exists("{$output_path}/*");
	@rmdir($output_path);
}
unlink_if_exists($output_file);
mkdir($output_path);

function doCmdT($title, $command, $method) {
	global $output_path, $output_file;
	/* Fixup output directory */

	$rubbish = array('|', '-', '/', '.', ' ');  /* fixes the <a> tag to be W3C compliant */
	echo "\n<a name=\"" . str_replace($rubbish, '', $title) . "\" id=\"" . str_replace($rubbish, '', $title) . "\"></a>\n";

	print('<div class="panel panel-default">');
	print('<div class="panel-heading"><h2 class="panel-title">' . $title . '</h2></div>');
	print('<div class="panel-body">');
	print('<pre>');

	if ($command == "dumpconfigxml") {
		$ofd = @fopen("{$output_path}/config-sanitized.xml", "w");
		$fd = @fopen("/conf/config.xml", "r");
		if ($fd) {
			while (!feof($fd)) {
				$line = fgets($fd);
				/* remove sensitive contents */
				$line = preg_replace("/<authorizedkeys>.*?<\\/authorizedkeys>/", "<authorizedkeys>xxxxx</authorizedkeys>", $line);
				$line = preg_replace("/<secret>.*?<\\/secret>/", "<secret>xxxxx</secret>", $line);
				$line = preg_replace("/<bcrypt-hash>.*?<\\/bcrypt-hash>/", "<bcrypt-hash>xxxxx</bcrypt-hash>", $line);
				$line = preg_replace("/<password>.*?<\\/password>/", "<password>xxxxx</password>", $line);
				$line = preg_replace("/<auth_user>.*?<\\/auth_user>/", "<auth_user>xxxxx</auth_user>", $line);
				$line = preg_replace("/<auth_pass>.*?<\\/auth_pass>/", "<auth_pass>xxxxx</auth_pass>", $line);
				$line = preg_replace("/<proxy_user>.*?<\\/proxy_user>/", "<proxy_user>xxxxx</proxy_user>", $line);
				$line = preg_replace("/<proxy_passwd>.*?<\\/proxy_passwd>/", "<proxy_passwd>xxxxx</proxy_passwd>", $line);
				$line = preg_replace("/<proxyuser>.*?<\\/proxyuser>/", "<proxyuser>xxxxx</proxyuser>", $line);
				$line = preg_replace("/<proxypass>.*?<\\/proxypass>/", "<proxypass>xxxxx</proxypass>", $line);
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
		if ($method == "exec") {
			exec($command . " 2>&1", $execOutput, $execStatus);
		} elseif ($method == "php_func") {
			$execOutput = explode("\n", $command());
		}
		for ($i = 0; isset($execOutput[$i]); $i++) {
			if ($i > 0) {
				echo "\n";
			}
			echo htmlspecialchars($execOutput[$i], ENT_NOQUOTES);
			fwrite($ofd, $execOutput[$i] . "\n");
		}
		fclose($ofd);
	}

	print('</pre>');
	print('</div>');
	print('</div>');
}

/* Define a command, with a title, to be executed later. */
function defCmdT($title, $command, $method = "exec") {
	global $commands;
	$title = htmlspecialchars($title, ENT_NOQUOTES);
	$commands[] = array($title, $command, $method);
}

/* List all of the commands as an index. */
function listCmds() {
	global $currentDate;
	global $commands;

	$rubbish = array('|', '-', '/', '.', ' ');	/* fixes the <a> tag to be W3C compliant */

	print('<div class="panel panel-default">');
	print('<div class="panel-heading"><h2 class="panel-title">' . gettext("Firewall Status on ") . $currentDate . '</h2></div>');
	print('<div class="panel-body">');
	print('    <div class="content">');
	print("\n<p>" . gettext("This status page includes the following information") . ":\n");
	print("<ul>\n");
	for ($i = 0; isset($commands[$i]); $i++) {
		print("\t<li><strong><a href=\"#" . str_replace($rubbish, '', $commands[$i][0]) . "\">" . $commands[$i][0] . "</a></strong></li>\n");
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
		doCmdT($commands[$i][0], $commands[$i][1], $commands[$i][2]);
	}
}

function get_firewall_info() {
	global $g, $output_path;
	/* Firewall Platform/Serial */
	$firewall_info = "Product Name: " . htmlspecialchars($g['product_name']);
	$platform = system_identify_specific_platform();
	if (!empty($platform['descr'])) {
		$firewall_info .= "<br/>Platform: " . htmlspecialchars($platform['descr']);
	}
	$serial = system_get_serial();
	if (!empty($serial)) {
		$firewall_info .= "<br/>SN/UUID: " . htmlspecialchars($serial);
	}

	if (!empty($g['product_version_string'])) {
		$firewall_info .= "<br/>" . htmlspecialchars($g['product_name']) .
		    " version: " . htmlspecialchars($g['product_version_string']);
	}

	if (file_exists('/etc/version.buildtime')) {
		$build_time = file_get_contents('/etc/version.buildtime');
		if (!empty($build_time)) {
			$firewall_info .= "<br/>Built On: " . htmlspecialchars($build_time);
		}
	}
	if (file_exists('/etc/version.lastcommit')) {
		$build_commit = file_get_contents('/etc/version.lastcommit');
		if (!empty($build_commit)) {
			$firewall_info .= "<br/>Last Commit: " . htmlspecialchars($build_commit);
		}
	}

	if (file_exists('/etc/version.gitsync')) {
		$gitsync = file_get_contents('/etc/version.gitsync');
		if (!empty($gitsync)) {
			$firewall_info .= "<br/>A gitsync was performed at " .
			    date("D M j G:i:s T Y", filemtime('/etc/version.gitsync')) .
			    " to commit " . htmlspecialchars($gitsync);
		}
	}

	file_put_contents("{$output_path}/Product Info.txt", str_replace("<br/>", "\n", $firewall_info) . "\n");
	return $firewall_info;
}

function get_gateway_status() {
	return return_gateways_status_text(true, false);
}

global $g, $config;

/* Set up all of the commands we want to execute. */

/* OS stats/info */
defCmdT("OS-Uptime", "/usr/bin/uptime");
defCmdT("Network-Interfaces", "/sbin/ifconfig -a");
defCmdT("Network-Interface Statistics", "/usr/bin/netstat -nWi");
defCmdT("Process-Top Usage", "/usr/bin/top | /usr/bin/head -n5");
defCmdT("Process-List", "/bin/ps xauwwd");
defCmdT("Disk-Mounted Filesystems", "/sbin/mount");
defCmdT("Disk-Free Space", "/bin/df -hi");
defCmdT("Network-Routing tables", "/usr/bin/netstat -nWr");
defCmdT("Network-Gateway Status", 'get_gateway_status', "php_func");
defCmdT("Network-Mbuf Usage", "/usr/bin/netstat -mb");
defCmdT("Network-Protocol Statistics", "/usr/bin/netstat -s");
defCmdT("Network-Sockets", "/usr/bin/sockstat");
defCmdT("Network-ARP Table", "/usr/sbin/arp -an");
defCmdT("Network-NDP Table", "/usr/sbin/ndp -na");
defCmdT("OS-Kernel VMStat", "/usr/bin/vmstat -afimsz");

/* Firewall rules and info */
defCmdT("Firewall-Generated Ruleset", "/bin/cat {$g['tmp_path']}/rules.debug");
defCmdT("Firewall-Generated Ruleset Limiters", "/bin/cat {$g['tmp_path']}/rules.limiter");
defCmdT("Firewall-Generated Ruleset Limits", "/bin/cat {$g['tmp_path']}/rules.limits");
defCmdT("Firewall-pf NAT Rules", "/sbin/pfctl -vvsn");
defCmdT("Firewall-pf Firewall Rules", "/sbin/pfctl -vvsr");
defCmdT("Firewall-pf Tables", "/sbin/pfctl -vs Tables");
defCmdT("Firewall-pf State Table Contents", "/sbin/pfctl -vvss");
defCmdT("Firewall-pf Info", "/sbin/pfctl -si");
defCmdT("Firewall-pf Show All", "/sbin/pfctl -sa");
defCmdT("Firewall-pf Queues", "/sbin/pfctl -s queue -v");
defCmdT("Firewall-pf OSFP", "/sbin/pfctl -s osfp");
defCmdT("Firewall-pftop Default", "/usr/local/sbin/pftop -a -b");
defCmdT("Firewall-pftop Long", "/usr/local/sbin/pftop -w 150 -a -b -v long");
defCmdT("Firewall-pftop Queue", "/usr/local/sbin/pftop -w 150 -a -b -v queue");
defCmdT("Firewall-pftop Rules", "/usr/local/sbin/pftop -w 150 -a -b -v rules");
defCmdT("Firewall-pftop Size", "/usr/local/sbin/pftop -w 150 -a -b -v size");
defCmdT("Firewall-pftop Speed", "/usr/local/sbin/pftop -w 150 -a -b -v speed");
defCmdT("Firewall-IPFW Rules for Captive Portal", "/sbin/ipfw show");
defCmdT("Firewall-IPFW Limiter Info", "/sbin/ipfw pipe show");
defCmdT("Firewall-IPFW Queue Info", "/sbin/ipfw queue show");

if (is_array($config['load_balancer']['lbpool']) && is_array($config['load_balancer']['virtual_server'])) {
	defCmdT("Load Balancer-Redirects", "/usr/local/sbin/relayctl show redirects");
	defCmdT("Load Balancer-Relays", "/usr/local/sbin/relayctl show relays");
	defCmdT("Load Balancer-Summary", "/usr/local/sbin/relayctl show summary");
}

/* Configuration Files */
defCmdT("Disk-Contents of var run", "/bin/ls /var/run");
defCmdT("Disk-Contents of conf", "/bin/ls /conf");
defCmdT("config.xml", "dumpconfigxml");
defCmdT("DNS-Resolution Configuration", "/bin/cat /etc/resolv.conf");
defCmdT("DHCP-IPv4 Configuration", "/bin/cat /var/dhcpd/etc/dhcpd.conf");
defCmdT("DHCP-IPv6-Configuration", "/bin/cat /var/dhcpd/etc/dhcpdv6.conf");
defCmdT("IPsec-strongSwan Configuration", "/bin/cat /var/etc/ipsec/strongswan.conf");
defCmdT("IPsec-Configuration", "/bin/cat /var/etc/ipsec/ipsec.conf");
defCmdT("IPsec-Status", "/usr/local/sbin/ipsec statusall");
defCmdT("IPsec-SPD", "/sbin/setkey -DP");
defCmdT("IPsec-SAD", "/sbin/setkey -D");
if (file_exists("/cf/conf/upgrade_log.txt")) {
	defCmdT("OS-Upgrade Log", "/bin/cat /cf/conf/upgrade_log.txt");
}
if (file_exists("/boot/loader.conf")) {
	defCmdT("OS-Boot Loader Configuration", "/bin/cat /boot/loader.conf");
}
if (file_exists("/boot/loader.conf.local")) {
	defCmdT("OS-Boot Loader Configuration (Local)", "/bin/cat /boot/loader.conf.local");
}
if (file_exists("/var/etc/filterdns.conf")) {
	defCmdT("DNS-filterdns Daemon Configuration", "/bin/cat /var/etc/filterdns.conf");
}

/* Logs */
defCmdT("Log-System-Last 1000 entries", "/usr/local/sbin/clog /var/log/system.log 2>&1 | tail -n 1000");
defCmdT("Log-DHCP-Last 1000 entries", "/usr/local/sbin/clog /var/log/dhcpd.log 2>&1 | tail -n 1000");
defCmdT("Log-Filter-Last 500 entries", "/usr/local/sbin/clog /var/log/filter.log 2>&1 | tail -n 500");
defCmdT("Log-Gateways-Last 1000 entries", "/usr/local/sbin/clog /var/log/gateways.log 2>&1 | tail -n 1000");
defCmdT("Log-IPsec-Last 1000 entries", "/usr/local/sbin/clog /var/log/ipsec.log 2>&1 | tail -n 1000");
defCmdT("Log-L2TP-Last 1000 entries", "/usr/local/sbin/clog /var/log/l2tps.log 2>&1 | tail -n 1000");
defCmdT("Log-NTP-Last 1000 entries", "/usr/local/sbin/clog /var/log/ntpd.log 2>&1 | tail -n 1000");
defCmdT("Log-OpenVPN-Last 1000 entries", "/usr/local/sbin/clog /var/log/openvpn.log 2>&1 | tail -n 1000");
defCmdT("Log-Captive Portal Authentication-Last 1000 entries", "/usr/local/sbin/clog /var/log/portalauth.log 2>&1 | tail -n 1000");
defCmdT("Log-PPP-Last 1000 entries", "/usr/local/sbin/clog /var/log/poes.log 2>&1 | tail -n 1000");
defCmdT("Log-relayd-Last 1000 entries", "/usr/local/sbin/clog /var/log/relayd.log 2>&1 | tail -n 1000");
defCmdT("Log-DNS-Last 1000 entries", "/usr/local/sbin/clog /var/log/resolver.log 2>&1 | tail -n 1000");
defCmdT("Log-Routing-Last 1000 entries", "/usr/local/sbin/clog /var/log/routing.log 2>&1 | tail -n 1000");
defCmdT("Log-Wireless-Last 1000 entries", "/usr/local/sbin/clog /var/log/wireless.log 2>&1 | tail -n 1000");
if (file_exists("/tmp/PHP_errors.log")) {
	defCmdT("Log-PHP Errors", "/bin/cat /tmp/PHP_errors.log");
}
defCmdT("OS-Message Buffer", "/sbin/dmesg -a");
defCmdT("OS-Message Buffer (Boot)", "/bin/cat /var/log/dmesg.boot");

/* OS/Hardware Status */
defCmdT("OS-sysctl values", "/sbin/sysctl -a");
defCmdT("OS-Kernel Environment", "/bin/kenv");
defCmdT("OS-Installed Packages", "/usr/sbin/pkg info");
defCmdT("Hardware-PCI Devices", "/usr/sbin/pciconf -lvb");
defCmdT("Hardware-USB Devices", "/usr/sbin/usbconfig dump_device_desc");

if (is_module_loaded("zfs.ko")) {
	defCmdT("Disk-ZFS List", "/sbin/zfs list");
	defCmdT("Disk-ZFS Properties", "/sbin/zfs get all");
	defCmdT("Disk-ZFS Pool List", "/sbin/zpool list");
	defCmdT("Disk-ZFS Pool Status", "/sbin/zpool status");
}
defCmdT("Disk-GEOM Mirror Status", "/sbin/gmirror status");

exec("/bin/date", $dateOutput, $dateStatus);
$currentDate = $dateOutput[0];

$pgtitle = array($g['product_name'], "Status");
include("head.inc");

print_info_box(gettext("Make sure all sensitive information is removed! (Passwords, etc.) before posting " .
			   "information from this page in public places (like mailing lists).") . '<br />' .
		gettext("Common password fields in config.xml have been automatically redacted.") . '<br />' .
		gettext("When the page has finished loading, the output will be stored in {$output_file}. It may be downloaded via scp or ") .
		"<a href=\"/diag_command.php?dlPath={$output_file}\">" . gettext("Diagnostics > Command Prompt.") . '</a>');

print_info_box(get_firewall_info(), 'info', false);

listCmds();
execCmds();

print(gettext("Saving output to archive..."));

if (is_dir($output_path)) {
	mwexec("/usr/bin/tar czpf " . escapeshellarg($output_file) . " -C " . escapeshellarg(dirname($output_path)) . " " . escapeshellarg(basename($output_path)));
	unlink_if_exists("{$output_path}/*");
	@rmdir($output_path);
}

print(gettext("Done."));

include("foot.inc");
