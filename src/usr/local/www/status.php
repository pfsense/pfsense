<?php
/*
 * status.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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

global $console;
global $show_output;

$console = false;
$show_output = !isset($_GET['archiveonly']);

if ((php_sapi_name() == 'cli') || (defined('STDIN'))) {
	/* Running from console/shell, not web */
	$console = true;
	$show_output = false;
	parse_str($argv[1], $_GET);
}

/* include all configuration functions */
if ($console) {
	require_once("config.inc");
} else {
	require_once("guiconfig.inc");
}
require_once("functions.inc");
require_once("gwlb.inc");
$output_path = "/tmp/status_output/";
$output_file = "/tmp/status_output.tgz";

$filtered_tags = array(
	'accountkey', 'authorizedkeys', 'auth_pass', 'auth_user',
	'barnyard_dbpwd', 'bcrypt-hash', 'cert_key', 'community', 'crypto_password',
	'crypto_password2', 'dns_nsupdatensupdate_key', 'encryption_password',
	'etpro_code', 'etprocode', 'gold_encryption_password', 'gold_password',
	'influx_pass', 'ipsecpsk', 'ldap_bindpw', 'ldapbindpass', 'ldap_pass',
	'lighttpd_ls_password', 'maxmind_geoipdb_key', 'maxmind_key', 'md5-hash',
	'md5password', 'md5sigkey', 'md5sigpass', 'nt-hash', 'oinkcode', 
	'oinkmastercode', 'passphrase', 'password', 'passwordagain', 
	'pkcs11pin', 'postgresqlpasswordenc', 'pre-shared-key',	'proxypass', 
	'proxy_passwd', 'proxyuser', 'proxy_user', 'prv', 'radius_secret',
	'redis_password', 'redis_passwordagain', 'rocommunity',	'secret',
	'shared_key', 'stats_password', 'tls', 'tlspskidentity', 'tlspskfile',
	'varclientpasswordinput', 'varclientsharedsecret', 'varsqlconfpassword',
	'varsqlconf2password', 	'varsyncpassword', 'varmodulesldappassword', 'varmodulesldap2password',
	'varusersmotpinitsecret', 'varusersmotppin', 'varuserspassword', 'webrootftppassword'
);

$acme_filtered_tags = array('key', 'password', 'secret', 'token', 'pwd', 'pw');

if ($_POST['submit'] == "DOWNLOAD" && file_exists($output_file)) {
	session_cache_limiter('public');
	send_user_download('file', $output_file);
}

if (is_dir($output_path)) {
	unlink_if_exists("{$output_path}/*");
	@rmdir($output_path);
}
unlink_if_exists($output_file);
mkdir($output_path);

function doCmdT($title, $command, $method) {
	global $output_path, $output_file, $filtered_tags, $acme_filtered_tags, $show_output;
	/* Fixup output directory */

	if ($show_output) {
		$rubbish = array('|', '-', '/', '.', ' ');  /* fixes the <a> tag to be W3C compliant */
		echo "\n<a name=\"" . str_replace($rubbish, '', $title) . "\" id=\"" . str_replace($rubbish, '', $title) . "\"></a>\n";
		print('<div class="panel panel-default">');
		print('<div class="panel-heading"><h2 class="panel-title">' . $title . '</h2></div>');
		print('<div class="panel-body">');
		print('<pre>');
	}

	if ($command == "dumpconfigxml") {
		$ofd = @fopen("{$output_path}/config-sanitized.xml", "w");
		$fd = @fopen("/conf/config.xml", "r");
		if ($fd) {
			while (!feof($fd)) {
				$line = fgets($fd);
				/* remove sensitive contents */
				foreach ($filtered_tags as $tag) {
					$line = preg_replace("/<{$tag}>.*?<\\/{$tag}>/", "<{$tag}>xxxxx</{$tag}>", $line);
				}
				/* remove ACME pkg sensitive contents */
				foreach ($acme_filtered_tags as $tag) {
					$line = preg_replace("/<dns_(.+){$tag}>.*?<\\/dns_(.+){$tag}>/", "<dns_$1{$tag}>xxxxx</dns_$1{$tag}>", $line);
				}
				if ($show_output) {
					echo htmlspecialchars(str_replace("\t", "    ", $line), ENT_NOQUOTES);
				}
				fwrite($ofd, $line);
			}
		}
		fclose($fd);
		fclose($ofd);
	} else {
		$execOutput = "";
		$execStatus = "";
		$fn = "{$output_path}/{$title}.txt";
		if ($method == "exec") {
			exec($command . " > " . escapeshellarg($fn) . " 2>&1", $execOutput, $execStatus);
			if ($show_output) {
				$ofd = @fopen($fn, "r");
				if ($ofd) {
					while (!feof($ofd)) {
						echo htmlspecialchars(fgets($ofd), ENT_NOQUOTES);
					}
				}
				fclose($ofd);
			}
		} elseif ($method == "php_func") {
			$execOutput = $command();
			if ($show_output) {
				echo htmlspecialchars($execOutput, ENT_NOQUOTES);
			}
			file_put_contents($fn, $execOutput);
		}
	}

	if ($show_output) {
		print('</pre>');
		print('</div>');
		print('</div>');
	}
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
	print('<div class="panel-heading"><h2 class="panel-title">' . sprintf(gettext("Firewall Status on %s"), $currentDate) . '</h2></div>');
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

	if (file_exists('/var/db/uniqueid')) {
		$ngid = file_get_contents('/var/db/uniqueid');
		if (!empty($ngid)) {
			$firewall_info .= "<br/>Netgate Device ID: " . htmlspecialchars($ngid);
		}
	}

	if (function_exists("system_get_thothid") &&
	    (php_uname("m") == "arm64")) {
		$thothid = system_get_thothid();
		if (!empty($thothid)) {
			$firewall_info .= "<br/>Netgate Crypto ID: " . htmlspecialchars(chop($thothid));
		}
	}

	$serial = system_get_serial();
	if (!empty($serial)) {
		$firewall_info .= "<br/>Serial: " . htmlspecialchars($serial);
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

	file_put_contents("{$output_path}/Product-Info.txt", str_replace("<br/>", "\n", $firewall_info) . "\n");
	return $firewall_info;
}

function get_gateway_status() {
	return return_gateways_status_text(true, false);
}

global $g, $config;

/* Set up all of the commands we want to execute. */

/* OS stats/info */
if (function_exists("system_get_thothid") &&
    (php_uname("m") == "arm64")) {
	$thothid = system_get_thothid();
	if (!empty($thothid)) {
		defCmdT("Product-Public Key", "/usr/local/sbin/ping-auth -p");
	}
}

defCmdT("OS-Uptime", "/usr/bin/uptime");
defCmdT("Network-Interfaces", "/sbin/ifconfig -vvvvvam");
defCmdT("Network-Interface Statistics", "/usr/bin/netstat -nWi");
defCmdT("Process-Top Usage", "/usr/bin/top | /usr/bin/head -n5");
defCmdT("Process-List", "/bin/ps xauwwd");
defCmdT("Disk-Mounted Filesystems", "/sbin/mount");
defCmdT("Disk-Free Space", "/bin/df -hi");
defCmdT("Network-Routing tables", "/usr/bin/netstat -nWr");
defCmdT("Network-Gateway Status", 'get_gateway_status', "php_func");
defCmdT("Network-Mbuf Usage", "/usr/bin/netstat -mb");
defCmdT("Network-Protocol Statistics", "/usr/bin/netstat -s");
defCmdT("Network-Buffer and Timer Statistics", "/usr/bin/netstat -nWx");
defCmdT("Network-Listen Queues", "/usr/bin/netstat -LaAn");
defCmdT("Network-Sockets", "/usr/bin/sockstat");
defCmdT("Network-ARP Table", "/usr/sbin/arp -an");
defCmdT("Network-NDP Table", "/usr/sbin/ndp -na");
defCmdT("OS-Kernel Modules", "/sbin/kldstat -v");
defCmdT("OS-Kernel VMStat", "/usr/bin/vmstat -afimsz");

/* If a device has a switch, put the switch configuration in the status output */
if (file_exists("/dev/etherswitch0")) {
	defCmdT("Network-Switch Configuration", "/sbin/etherswitchcfg -f /dev/etherswitch0 info");
}

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
defCmdT("Firewall-IPFW Tables", "/sbin/ipfw table all list");

/* Configuration Files */
defCmdT("Disk-Contents of var run", "/bin/ls /var/run");
defCmdT("Disk-Contents of conf", "/bin/ls /conf");
defCmdT("config.xml", "dumpconfigxml");
defCmdT("DNS-Resolution Configuration", "/bin/cat /etc/resolv.conf");
defCmdT("DNS-Resolver Access Lists", "/bin/cat /var/unbound/access_lists.conf");
defCmdT("DNS-Resolver Configuration", "/bin/cat /var/unbound/unbound.conf");
defCmdT("DNS-Resolver Domain Overrides", "/bin/cat /var/unbound/domainoverrides.conf");
defCmdT("DNS-Resolver Host Overrides", "/bin/cat /var/unbound/host_entries.conf");
defCmdT("DHCP-IPv4 Configuration", "/bin/cat /var/dhcpd/etc/dhcpd.conf");
defCmdT("DHCP-IPv6-Configuration", "/bin/cat /var/dhcpd/etc/dhcpdv6.conf");
defCmdT("IPsec-strongSwan Configuration", '/usr/bin/sed "s/\([[:blank:]]secret = \).*/\1<redacted>/" /var/etc/ipsec/strongswan.conf');
defCmdT("IPsec-Configuration", '/usr/bin/sed "s/\([[:blank:]]secret = \).*/\1<redacted>/" /var/etc/ipsec/swanctl.conf');
defCmdT("IPsec-Status-Statistics", "/usr/local/sbin/swanctl --stats --pretty");
defCmdT("IPsec-Status-Connections", "/usr/local/sbin/swanctl --list-conns");
defCmdT("IPsec-Status-Active SAs", "/usr/local/sbin/swanctl --list-sas");
defCmdT("IPsec-Status-Policies", "/usr/local/sbin/swanctl --list-pols");
defCmdT("IPsec-Status-Certificates", "/usr/local/sbin/swanctl --list-certs --utc");
defCmdT("IPsec-Status-Pools", "/usr/local/sbin/swanctl --list-pools --leases");
defCmdT("IPsec-SPD", "/sbin/setkey -DP");
defCmdT("IPsec-SAD", "/sbin/setkey -D");
if (file_exists("/cf/conf/upgrade_log.txt")) {
	defCmdT("OS-Upgrade Log", "/bin/cat /cf/conf/upgrade_log.txt");
}
if (file_exists("/cf/conf/upgrade_log.latest.txt")) {
	defCmdT("OS-Upgrade Log Latest", "/bin/cat /cf/conf/upgrade_log.latest.txt");
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

if (is_dir("/var/etc/openvpn")) {
	foreach(glob('/var/etc/openvpn/*/config.ovpn') as $file) {
		$ovpnfile = explode('/', $file);
		if (!count($ovpnfile) || (count($ovpnfile) < 6)) {
			continue;
		}
		defCmdT("OpenVPN-Configuration {$ovpnfile[4]}", "/bin/cat " . escapeshellarg($file));
	}
}

if (file_exists("/var/etc/l2tp-vpn/mpd.conf")) {
	defCmdT("L2TP-Configuration", '/usr/bin/sed -E "s/([[:blank:]](secret|radius server .*) ).*/\1<redacted>/" /var/etc/l2tp-vpn/mpd.conf');
}

/* Config History */
$confvers = get_backups();
unset($confvers['versions']);
if (count($confvers) != 0) {
	for ($c = count($confvers)-1; $c >= 0; $c--) {
		$conf_history .= backup_info($confvers[$c], $c+1);
		$conf_history .= "\n";
	}
	defCmdT("Config History", "echo " . escapeshellarg($conf_history));
}

/* Logs */
function status_add_log($name, $logfile, $number = 1000) {
	if (!file_exists($logfile)) {
		return;
	}
	$descr = "Log-{$name}";
	$tail = '';
	if ($number != "all") {
		$descr .= "-Last {$number} entries";
		$tail = ' | tail -n ' . escapeshellarg($number);
	}
	defCmdT($descr, system_log_get_cat() . ' ' . sort_related_log_files($logfile, true, true) . $tail);
}

status_add_log("System", '/var/log/system.log');
status_add_log("DHCP", '/var/log/dhcpd.log');
status_add_log("Filter", '/var/log/filter.log');
status_add_log("Gateways", '/var/log/gateways.log');
status_add_log("IPsec", '/var/log/ipsec.log');
status_add_log("L2TP", '/var/log/l2tps.log');
status_add_log("NTP", '/var/log/ntpd.log');
status_add_log("OpenVPN", '/var/log/openvpn.log');
status_add_log("Captive Portal Authentication", '/var/log/portalauth.log');
status_add_log("PPP", '/var/log/ppp.log');
status_add_log("PPPoE Server", '/var/log/poes.log');
status_add_log("DNS", '/var/log/resolver.log');
status_add_log("Routing", '/var/log/routing.log');
status_add_log("Wireless", '/var/log/wireless.log');
status_add_log("PHP Errors", '/tmp/PHP_errors.log', 'all');

defCmdT("OS-Message Buffer", "/sbin/dmesg -a");
defCmdT("OS-Message Buffer (Boot)", "/bin/cat /var/log/dmesg.boot");

/* OS/Hardware Status */
defCmdT("OS-sysctl values", "/sbin/sysctl -aq");
defCmdT("OS-Kernel Environment", "/bin/kenv");
defCmdT("OS-Kernel Memory Usage", "/usr/local/sbin/kmemusage.sh");
defCmdT("OS-Installed Packages", "/usr/local/sbin/pkg-static info");
defCmdT("OS-Package Manager Configuration", "/usr/local/sbin/pkg-static -vv");
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

if (!$console):
include("head.inc"); ?>

<form action="status.php" method="post">

<?php print_info_box(
	gettext("Make sure all sensitive information is removed! (Passwords, etc.) before posting information from this page in public places such as forum or social media sites.") .
	'<br />' .
	gettext("Common password and other private fields in config.xml have been automatically redacted.") .
	'<br />' .
	sprintf(gettext('When the page has finished loading, the output is stored in %1$s. It may be downloaded via scp or using this button: '), $output_file) .
	' <button name="submit" type="submit" class="btn btn-primary btn-sm" id="download" value="DOWNLOAD">' .
	'<i class="fa fa-download icon-embed-btn"></i>' .
	gettext("Download") .
	'</button>'); ?>

</form>

<?php print_info_box(get_firewall_info(), 'info', false);

if ($show_output) {
	listCmds();
} else {
	print_info_box(gettext("Status output suppressed. Download archive to view."), 'info', false);
}

endif;

if ($console) {
	print(gettext("Gathering status data...") . "\n");
	get_firewall_info();
}
execCmds();

print(gettext("Saving output to archive..."));

if (is_dir($output_path)) {
	mwexec("/usr/bin/tar czpf " . escapeshellarg($output_file) . " -C " . escapeshellarg(dirname($output_path)) . " " . escapeshellarg(basename($output_path)));

	if (!isset($_GET["nocleanup"])) {
		unlink_if_exists("{$output_path}/*");
		@rmdir($output_path);
	}
}

print(gettext("Done.") . "\n");

if (!$console) {
	include("foot.inc");
}
