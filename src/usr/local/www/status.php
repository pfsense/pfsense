<?php
/*
 * status.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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
global $errors;

$console = false;
$show_output = !isset($_GET['archiveonly']);
$errors = [];
$output_path = "/tmp/status_output/";
$output_file = "/tmp/status_output.tgz";

if ((php_sapi_name() == 'cli') ||
    (defined('STDIN'))) {
	/* Running from console/shell, not web */
	$console = true;
	$show_output = false;
	parse_str($argv[1], $_GET);
}

require_once('status_output.inc');

if ($_POST['submit'] == "DOWNLOAD" &&
    file_exists($output_file)) {
	session_cache_limiter('public');
	send_user_download('file', $output_file);
}

if (is_dir($output_path)) {
	unlink_if_exists("{$output_path}/*");
	@rmdir($output_path);
}
unlink_if_exists($output_file);
mkdir($output_path);

if ($console) {
	print(gettext("Gathering status data...") . "\n");
}

/* Set up all of the commands we want to execute. */

/* OS stats/info */

status_cmd_define("OS-Uptime", "/usr/bin/uptime");
status_cmd_define("Network-Interfaces", "/sbin/ifconfig -vvvvvam");
status_cmd_define("Network-Interface Statistics", "/usr/bin/netstat -nWi");
status_cmd_define("Network-Multicast Groups", "/usr/sbin/ifmcstat");
status_cmd_define("Process-Top Usage", "/usr/bin/top | /usr/bin/head -n5");
status_cmd_define("Process-List", "/bin/ps xauwwd");
status_cmd_define("Disk-Mounted Filesystems", "/sbin/mount");
status_cmd_define("Disk-Free Space", "/bin/df -hi");
status_cmd_define("Network-Routing tables", "/usr/bin/netstat -nWr");
status_cmd_define("Network-IPv4 Nexthop Data", "/usr/bin/netstat -4onW");
status_cmd_define("Network-IPv6 Nexthop Data", "/usr/bin/netstat -6onW");
status_cmd_define("Network-IPv4 Nexthop Group Data", "/usr/bin/netstat -4OnW");
status_cmd_define("Network-IPv6 Nexthop Group Data", "/usr/bin/netstat -6OnW");
status_cmd_define("Network-Gateway Status", 'status_get_gateway_status', "php_func");
status_cmd_define("Network-Mbuf Usage", "/usr/bin/netstat -mb");
status_cmd_define("Network-Protocol Statistics", "/usr/bin/netstat -s");
status_cmd_define("Network-Buffer and Timer Statistics", "/usr/bin/netstat -nWx");
status_cmd_define("Network-Listen Queues", "/usr/bin/netstat -LaAn");
status_cmd_define("Network-Sockets", "/usr/bin/sockstat");
status_cmd_define("Network-ARP Table", "/usr/sbin/arp -an");
status_cmd_define("Network-NDP Table", "/usr/sbin/ndp -na");
status_cmd_define("OS-Kernel Modules", "/sbin/kldstat -v");
status_cmd_define("OS-Kernel VMStat", "/usr/bin/vmstat -afimsz");

/* If a device has a switch, put the switch configuration in the status output */
if (file_exists("/dev/etherswitch0")) {
	status_cmd_define("Network-Switch Configuration", "/sbin/etherswitchcfg -f /dev/etherswitch0 info");
}

/* Firewall rules and info */
status_cmd_define("Firewall-Generated Ruleset", "/bin/cat " . g_get('tmp_path') . "/rules.debug");
status_cmd_define("Firewall-Generated Ruleset Limiters", "/bin/cat " . g_get('tmp_path') . "/rules.limiter");
status_cmd_define("Firewall-Generated Ruleset Limits", "/bin/cat " . g_get('tmp_path') . "/rules.limits");
foreach (glob(g_get('tmp_path') . "/rules.packages.*") as $pkgrules) {
	$pkgname = substr($pkgrules, strrpos($pkgrules, '.') + 1);
	status_cmd_define("Firewall-Generated Package Invalid Ruleset {$pkgname}", "/bin/cat " . escapeshellarg($pkgrules));
}
$ovpnradrules = array();
foreach (glob(g_get('tmp_path') . "/ovpn_ovpns*.rules") as $ovpnrules) {
	if (preg_match('/ovpn_ovpns(\d+)\_(\w+)\_(\d+)\.rules/', basename($ovpnrules), $matches)) {
		$ovpnradrules[$matches[1]] .= "# user '{$matches[2]}' remote port {$matches[3]}\n";
		$ovpnradrules[$matches[1]] .= file_get_contents($ovpnrules);
		$ovpnradrules[$matches[1]] .= "\n";
	}
}
foreach ($ovpnradrules as $ovpns => $genrules) {
	status_cmd_define("OpenVPN-Generated RADIUS ACL Ruleset for server{$ovpns}",
	  "echo " .  escapeshellarg($genrules));
}
status_cmd_define("Firewall-pf NAT Rules", "/sbin/pfctl -vvsn");
status_cmd_define("Firewall-pf Firewall Rules", "/sbin/pfctl -vvsr");
status_cmd_define("Firewall-pf Tables", "/sbin/pfctl -vs Tables");
status_cmd_define("Firewall-pf State Table Contents", "/sbin/pfctl -vvss");
status_cmd_define("Firewall-pf Info", "/sbin/pfctl -si");
status_cmd_define("Firewall-pf Show All", "/sbin/pfctl -sa");
status_cmd_define("Firewall-pf Queues", "/sbin/pfctl -s queue -v");
status_cmd_define("Firewall-pf OSFP", "/sbin/pfctl -s osfp");
status_cmd_define("Firewall-pftop Default", "/usr/local/sbin/pftop -a -b");
status_cmd_define("Firewall-pftop Long", "/usr/local/sbin/pftop -w 150 -a -b -v long");
status_cmd_define("Firewall-pftop Queue", "/usr/local/sbin/pftop -w 150 -a -b -v queue");
status_cmd_define("Firewall-pftop Rules", "/usr/local/sbin/pftop -w 150 -a -b -v rules");
status_cmd_define("Firewall-pftop Size", "/usr/local/sbin/pftop -w 150 -a -b -v size");
status_cmd_define("Firewall-pftop Speed", "/usr/local/sbin/pftop -w 150 -a -b -v speed");
status_cmd_define("Firewall-Limiter Info", "/sbin/dnctl pipe show");
status_cmd_define("Firewall-Queue Info", "/sbin/dnctl queue show");

/* Configuration Files */
status_cmd_define("Disk-Contents of var run", "/bin/ls /var/run");
status_cmd_define("Disk-Contents of conf", "/bin/ls /conf");
status_cmd_define("config.xml", "dumpconfigxml");
status_cmd_define("DNS-Resolution Configuration", "/bin/cat /etc/resolv.conf");
status_cmd_define("DNS-Resolver Access Lists", "/bin/cat /var/unbound/access_lists.conf");
status_cmd_define("DNS-Resolver Configuration", "/bin/cat /var/unbound/unbound.conf");
status_cmd_define("DNS-Resolver Domain Overrides", "/bin/cat /var/unbound/domainoverrides.conf");
status_cmd_define("DNS-Resolver Host Overrides", "/bin/cat /var/unbound/host_entries.conf");

if (file_exists("/var/dhcpd/etc/dhcpd.conf")) {
	status_cmd_define("DHCP-ISC-IPv4 Configuration", '/usr/bin/sed "s/\([[:blank:]]secret \).*/\1<redacted>/" /var/dhcpd/etc/dhcpd.conf');
}
if (file_exists("/var/dhcpd/etc/dhcpdv6.conf")) {
	status_cmd_define("DHCP-ISC-IPv6-Configuration", '/usr/bin/sed "s/\([[:blank:]]secret \).*/\1<redacted>/" /var/dhcpd/etc/dhcpdv6.conf');
}
if (file_exists("/usr/local/etc/kea/kea-dhcp4.conf") &&
    !compare_files("/usr/local/etc/kea/kea-dhcp4.conf", "/usr/local/etc/kea/kea-dhcp4.conf.sample")) {
	status_cmd_define("DHCP-Kea-IPv4 Configuration", '/bin/cat /usr/local/etc/kea/kea-dhcp4.conf');
}
if (file_exists("/usr/local/etc/kea/kea-dhcp6.conf") &&
    !compare_files("/usr/local/etc/kea/kea-dhcp6.conf", "/usr/local/etc/kea/kea-dhcp6.conf.sample")) {
	status_cmd_define("DHCP-Kea-IPv6 Configuration", '/bin/cat /usr/local/etc/kea/kea-dhcp6.conf');
}

if (file_exists("/var/etc/ipsec/strongswan.conf")) {
	status_cmd_define("IPsec-strongSwan Configuration", '/usr/bin/sed "s/\([[:blank:]]secret = \).*/\1<redacted>/" /var/etc/ipsec/strongswan.conf');
}
if (file_exists("/var/etc/ipsec/swanctl.conf")) {
	status_cmd_define("IPsec-Configuration", '/usr/bin/sed -E "s/([[:blank:]]*(secret|pin) = ).*/\1<redacted>/" /var/etc/ipsec/swanctl.conf');
}
if (file_exists("/var/run/charon.vici")) {
	status_cmd_define("IPsec-Status-Statistics", "/usr/local/sbin/swanctl --stats --pretty");
	status_cmd_define("IPsec-Status-Connections", "/usr/local/sbin/swanctl --list-conns");
	status_cmd_define("IPsec-Status-Active SAs", "/usr/local/sbin/swanctl --list-sas");
	status_cmd_define("IPsec-Status-Policies", "/usr/local/sbin/swanctl --list-pols");
	status_cmd_define("IPsec-Status-Certificates", "/usr/local/sbin/swanctl --list-certs --utc");
	status_cmd_define("IPsec-Status-Pools", "/usr/local/sbin/swanctl --list-pools --leases");
}

status_cmd_define("IPsec-SPD", "/sbin/setkey -DP");
status_cmd_define("IPsec-SAD", "/sbin/setkey -D");
if (file_exists("/cf/conf/upgrade_log.txt")) {
	status_cmd_define("OS-Upgrade Log", "/bin/cat /cf/conf/upgrade_log.txt");
}
if (file_exists("/cf/conf/upgrade_log.latest.txt")) {
	status_cmd_define("OS-Upgrade Log Latest", "/bin/cat /cf/conf/upgrade_log.latest.txt");
}
if (file_exists("/boot/loader.conf")) {
	status_cmd_define("OS-Boot Loader Configuration", "/bin/cat /boot/loader.conf");
}
if (file_exists("/boot/loader.conf.local")) {
	status_cmd_define("OS-Boot Loader Configuration (Local)", "/bin/cat /boot/loader.conf.local");
}
if (file_exists("/boot/loader.conf.lua")) {
	status_cmd_define("OS-Boot Loader Configuration (Lua)", "/bin/cat /boot/loader.conf.lua");
}
if (file_exists("/var/etc/filterdns.conf")) {
	status_cmd_define("DNS-filterdns Daemon Configuration", "/bin/cat /var/etc/filterdns.conf");
}

if (is_dir("/var/etc/openvpn")) {
	foreach(glob('/var/etc/openvpn/*/config.ovpn') as $file) {
		$ovpnfile = explode('/', $file);
		if (!count($ovpnfile) || (count($ovpnfile) < 6)) {
			continue;
		}
		status_cmd_define("OpenVPN-Configuration {$ovpnfile[4]}", "/bin/cat " . escapeshellarg($file));
	}
}

if (file_exists("/var/etc/l2tp-vpn/mpd.conf")) {
	status_cmd_define("L2TP-Configuration", '/usr/bin/sed -E "s/([[:blank:]](secret|radius server .*) ).*/\1<redacted>/" /var/etc/l2tp-vpn/mpd.conf');
}

/* Config History */
$confvers = get_backups();
unset($confvers['versions']);
if (count($confvers) != 0) {
	for ($c = count($confvers)-1; $c >= 0; $c--) {
		$conf_history .= backup_info($confvers[$c], $c+1);
		$conf_history .= "\n";
	}
	status_cmd_define("Config History", "echo " . escapeshellarg($conf_history));
}

status_log_add("System", '/var/log/system.log');
status_log_add("DHCP", '/var/log/dhcpd.log');
status_log_add("Filter", '/var/log/filter.log');
status_log_add("Gateways", '/var/log/gateways.log');
status_log_add("IPsec", '/var/log/ipsec.log');
status_log_add("L2TP", '/var/log/l2tps.log');
status_log_add("NTP", '/var/log/ntpd.log');
status_log_add("OpenVPN", '/var/log/openvpn.log');
status_log_add("Captive Portal Authentication", '/var/log/portalauth.log');
status_log_add("PPP", '/var/log/ppp.log');
status_log_add("PPPoE Server", '/var/log/poes.log');
status_log_add("DNS", '/var/log/resolver.log');
status_log_add("Routing", '/var/log/routing.log');
status_log_add("Wireless", '/var/log/wireless.log');
status_log_add("PHP Errors", '/tmp/PHP_errors.log', 'all');

status_cmd_define("OS-Message Buffer", "/sbin/dmesg -a");
status_cmd_define("OS-Message Buffer (Boot)", "/bin/cat /var/log/dmesg.boot");

/* OS/Hardware Status */
status_cmd_define("OS-sysctl values", "/sbin/sysctl -aq");
status_cmd_define("OS-Kernel Environment", "/bin/kenv");
status_cmd_define("OS-Kernel Memory Usage", "/usr/local/sbin/kmemusage.sh");
status_cmd_define("OS-Installed Packages", "/usr/local/sbin/pkg-static info");
status_cmd_define("OS-Package Manager Configuration", "/usr/local/sbin/pkg-static -vv");
status_cmd_define("Hardware-PCI Devices", "/usr/sbin/pciconf -lvb");
status_cmd_define("Hardware-USB Devices", "/usr/sbin/usbconfig dump_device_desc");

status_cmd_define("Disk-Filesystem Table", "/bin/cat /etc/fstab");
status_cmd_define("Disk-Swap Information", "/usr/sbin/swapinfo");

if (is_module_loaded("zfs.ko")) {
	status_cmd_define("Disk-ZFS List", "/sbin/zfs list");
	status_cmd_define("Disk-ZFS Properties", "/sbin/zfs get all");
	status_cmd_define("Disk-ZFS Pool List", "/sbin/zpool list");
	status_cmd_define("Disk-ZFS Pool Status", "/sbin/zpool status");
}

status_cmd_define("Disk-GEOM Tree", "/sbin/geom -t");
status_cmd_define("Disk-GEOM Disk List", "/sbin/geom disk list -a");
status_cmd_define("Disk-GEOM Partition Summary", "/sbin/geom part show -p");
status_cmd_define("Disk-GEOM Partition Details", "/sbin/geom part list");
status_cmd_define("Disk-GEOM Label Status", "/sbin/geom label status");
status_cmd_define("Disk-GEOM Label Details", "/sbin/geom label list");
status_cmd_define("Disk-GEOM Mirror Status", "/sbin/gmirror status");

/* Items specific to EFI */
if (get_single_sysctl("machdep.bootmethod") == "UEFI") {
	/* Basic EFI boot list is easier to read but only includes active entries */
	status_cmd_define("EFI-Boot Manager List", "/usr/sbin/efibootmgr");
	/* Verbose EFI boot list has a lot more detail but is more difficult to read */
	status_cmd_define("EFI-Boot Manager List (Verbose)", "/usr/sbin/efibootmgr -v");
}

exec("/bin/date", $dateOutput, $dateStatus);
$currentDate = $dateOutput[0];

$pgtitle = array(g_get('product_label'), "Status");

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
	'<i class="fa-solid fa-download icon-embed-btn"></i>' .
	gettext("Download") .
	'</button>'); ?>

</form>

<?php print_info_box(status_get_firewall_info(), 'info', false);

/* Call any registeredd package plugins which define status output to include
 * See https://redmine.pfsense.org/issues/14777 and
 *     https://redmine.pfsense.org/issues/1458
 */
$pluginparams = array();
$pluginparams['type'] = 'statusoutput';
pkg_call_plugins('plugin_statusoutput', $pluginparams);

if ($show_output) {
	status_cmd_list();
} else {
	print_info_box(gettext("Status output suppressed. Download archive to view."), 'info', false);
}

endif;

if ($console) {
	status_get_firewall_info();
}

status_cmd_run_all();

if (!empty($errors)) {
	$errorheader = gettext('Errors') . ": " . count($errors) . "\n";
	$errors[] = gettext("NOTE: Some errors are normal if a feature is not enabled or is inaccessible by the current user.\n");
	$errortext = $errorheader . implode('', $errors);
	file_put_contents("{$output_path}/_errors.txt", $errortext);
	if ($console) {
		echo $errortext;
	} else {
		print_info_box($errorheader . "<br/>" . implode('<br/>', $errors), 'warning', false);
	}
}

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
