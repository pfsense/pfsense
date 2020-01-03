<?php
/*
 * globals.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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

// Global defines

// Automatic panel collapse
define('COLLAPSIBLE', 0x08);
define('SEC_CLOSED', 0x04);
define('SEC_OPEN', 0x00);

// IP address types
define("IPV4", 4);
define("IPV6", 6);
define("IPV4V6", 2);
define("ALIAS", 1);

// Interface Name Size
define("IF_NAMESIZE", 15);	/* 16 minus the terminating NULL */

// AddPassword method defines
define('DMYPWD', "********");

global $g;
$g = array(
	"event_address" => "unix:///var/run/check_reload_status",
	"factory_shipped_username" => "admin",
	"factory_shipped_password" => "pfsense",
	"upload_path" => "/root",
	"dhcpd_chroot_path" => "/var/dhcpd",
	"unbound_chroot_path" => "/var/unbound",
	"var_path" => "/var",
	"varrun_path" => "/var/run",
	"varetc_path" => "/var/etc",
	"vardb_path" => "/var/db",
	"varlog_path" => "/var/log",
	"etc_path" => "/etc",
	"tmp_path" => "/tmp",
	"tmp_path_user_code" => "/tmp/user_code",
	"conf_path" => "/conf",
	"conf_default_path" => "/conf.default",
	"cf_path" => "/cf",
	"cf_conf_path" => "/cf/conf",
	"www_path" => "/usr/local/www",
	"xml_rootobj" => "pfsense",
	"admin_group" => "admins",
	"product_name" => "pfSense",
	"product_version" => trim(file_get_contents("/etc/version"), " \n"),
	"product_copyright_years" => "2004 - ".date("Y"),
	"disablehelpmenu" => false,
	"disablehelpicon" => false,
	"disablecrashreporter" => false,
	"debug" => false,
	"latest_config" => "20.0",
	"minimum_ram_warning" => "101",
	"minimum_ram_warning_text" => "128 MB",
	"wan_interface_name" => "wan",
	"captiveportal_path" => "/usr/local/captiveportal",
	"captiveportal_element_path" => "/var/db/cpelements",
	"captiveportal_element_sizelimit" => 1048576,
	"captiveportal_rules_interval" => 50,
	"services_dhcp_server_enable" => true,
	"wireless_regex" => "/^(ath|athp|bwi|bwn|ipw|iwi|iwm|iwn|malo|mwl|ral|rsu|rum|run|uath|upgt|ural|urtw|urtwn|wi|wpi|wtap|zyd)[0-9]+/",
	"help_base_url" => "/help.php",
	"pkg_prefix" => "pfSense-pkg-",
	"default_timezone" => "Etc/UTC",
	"language" => "en_US",
	"default_config_backup_count" => 30,
	"default_cert_expiredays" => 27,
	"default_log_entries" => 500,
	"default_log_size" => 512000,
	"minimumtableentries_bogonsv6" => 400000,
	"alternativemetaports" => array("vmware", "php72", "php73")
);

/* IP TOS flags */
$iptos = array("lowdelay", "throughput", "reliability");

/* TCP flags */
$tcpflags = array("syn", "ack", "fin", "rst", "psh", "urg", "ece", "cwr");

if (file_exists("/etc/version.patch")) {
	$g["product_version_patch"] = trim(file_get_contents("/etc/version.patch"), " \n");
} else {
	$g["product_version_patch"] = "0";
}

$g['product_version_string'] = $g['product_version'];
if (is_numeric($g["product_version_patch"]) && $g["product_version_patch"] != "0") {
	$g['product_version_string'] .= "-p{$g['product_version_patch']}";
}

/* XXX: Backward compatible */
$g['platform'] = $g['product_name'];

if (file_exists("{$g['etc_path']}/default-config-flavor")) {
	$flavor_array = file("{$g['etc_path']}/default-config-flavor");
	$g['default-config-flavor'] = chop($flavor_array[0]);
} else {
	$g['default-config-flavor'] = '';
}

$g['openvpn_base'] = "{$g['varetc_path']}/openvpn";

/* Default sysctls */
$sysctls = array("net.inet.ip.portrange.first" => "1024",
	"net.inet.tcp.blackhole" => "2",
	"net.inet.udp.blackhole" => "1",
	"net.inet.ip.random_id" => "1",
	"net.inet.tcp.drop_synfin" => "1",
	"net.inet.ip.redirect" => "1",
	"net.inet6.ip6.redirect" => "1",
	"net.inet6.ip6.use_tempaddr" => "0",
	"net.inet6.ip6.prefer_tempaddr" => "0",
	"net.inet.tcp.syncookies" => "1",
	"net.inet.tcp.recvspace" => "65228",
	"net.inet.tcp.sendspace" => "65228",
	"net.inet.tcp.delayed_ack" => "0",
	"net.inet.udp.maxdgram" => "57344",
	"net.link.bridge.pfil_onlyip" => "0",
	"net.link.bridge.pfil_member" => "1",
	"net.link.bridge.pfil_bridge" => "0",
	"net.link.tap.user_open" => "1",
	"net.link.vlan.mtag_pcp" => "1",
	"kern.randompid" => "347",
	"net.inet.ip.intr_queue_maxlen" => "1000",
	"hw.syscons.kbd_reboot" => "0",
	"net.inet.tcp.log_debug" => "0",
	"net.inet.tcp.tso" => "1",
	"net.inet.icmp.icmplim" => "0",
	"vfs.read_max" => "32",
	"kern.ipc.maxsockbuf" => "4262144",
	"net.inet.ip.process_options" => 0,
	"kern.random.harvest.mask" => "351",
	"net.route.netisr_maxqlen" => 1024,
	"net.inet.udp.checksum" => 1,
	"net.inet.icmp.reply_from_interface" => 1,
	"net.inet6.ip6.rfc6204w3" => 1,
	"net.enc.out.ipsec_bpf_mask" => "0x0001",
	"net.enc.out.ipsec_filter_mask" => "0x0001",
	"net.enc.in.ipsec_bpf_mask" => "0x0002",
	"net.enc.in.ipsec_filter_mask" => "0x0002",
	"net.key.preferred_oldsa" => "0",
	"net.inet.carp.senderr_demotion_factor" => 0, /* Do not demote CARP for interface send errors */
	"net.pfsync.carp_demotion_factor" => 0, /* Do not demote CARP for pfsync errors */
	"net.raw.recvspace" => 65536,
	"net.raw.sendspace" => 65536,
	"net.inet.raw.recvspace" => 131072,
	"net.inet.raw.maxdgram" => 131072,
	"kern.corefile" => "/root/%N.core" /* Write all core files to /root/ so they do not consume space on other slices */
);

$machine_type = php_uname('m');
if (($machine_type == 'arm') || ($machine_type == 'arm64')) {
	$sysctls['kern.shutdown.secure_halt'] = 1;
}

/* Include override values for the above if needed. If the file doesn't exist, don't try to load it. */
if (file_exists("/etc/inc/globals_override.inc")) {
	@include_once("globals_override.inc");
}

/* Read all XML files in following dir and load menu entries */
$g["ext_menu_path"] = "/usr/local/share/{$g['product_name']}/menu";

/* Cache file used to store pfSense version */
$g["version_cache_file"] = "{$g['varrun_path']}/{$g['product_name']}_version";
$g['version_cache_refresh'] = 2 * 60 * 60; /* 2h */

function platform_booting($on_console = false) {
	global $g;

	if ($g['booting'] || file_exists("{$g['varrun_path']}/booting")) {
		if ($on_console == false || php_sapi_name() != 'fpm-fcgi') {
			return true;
		}
	}

	return false;
}

if (file_exists("{$g['cf_conf_path']}/enableserial_force")) {
	$g['enableserial_force'] = true;
}

$config_parsed = false;

/* Factory default check IP service. */
$factory_default_checkipservice = array(
	"enable" => true,
	"name" => 'Default',
	"url" => 'http://checkip.dyndns.org',
//	"username" => '',
//	"password" => '',
//	"verifysslpeer" => true,
	"descr" => 'Default Check IP Service'
);

$dyndns_split_domain_types = array("namecheap", "cloudflare", "cloudflare-v6", "gratisdns", "cloudns", "godaddy", "godaddy-v6", "linode", "linode-v6");

/* pf tokens from FreeBSD source sbin/pfctl/parse.y (plus our custom entries at the end)*/
global $pf_reserved_keywords;
$pf_reserved_keywords = array(
	"pass", "block", "scrub", "return", "in", "os", "out", "log", "quick", "on", "from", "to", "flags", "returnrst",
	"returnicmp", "returnicmp6", "proto", "inet", "inet6", "all", "any", "icmptype", "icmp6type", "code", "keep",
	"modulate", "state", "port", "rdr", "nat", "binat", "arrow", "nodf", "minttl", "error", "allowopts", "fastroute",
	"filename", "routeto", "dupto", "replyto", "no", "label", "noroute", "urpffailed", "fragment", "user", "group",
	"maxmss", "maximum", "ttl", "tos", "drop", "table", "reassemble", "fragdrop", "fragcrop", "anchor", "natanchor",
	"rdranchor", "binatanchor", "set", "optimization", "timeout", "limit", "loginterface", "blockpolicy", "randomid",
	"requireorder", "synproxy", "fingerprints", "nosync", "debug", "skip", "hostid", "antispoof", "for", "include",
	"bitmask", "random", "sourcehash", "roundrobin", "staticport", "probability", "altq", "cbq", "codel", "priq",
	"hfsc", "fairq", "bandwidth", "tbrsize", "linkshare", "realtime", "upperlimit", "queue", "priority", "qlimit",
	"hogs", "buckets", "rtable", "target", "interval", "load", "ruleset_optimization", "prio", "stickyaddress",
	"maxsrcstates", "maxsrcnodes", "sourcetrack", "global", "rule", "maxsrcconn", "maxsrcconnrate", "overload",
	"flush", "sloppy", "tagged", "tag", "ifbound", "floating", "statepolicy", "statedefaults", "route", "settos",
	"divertto", "divertreply", "max", "min", "pptp", "pppoe", "L2TP", "OpenVPN", "IPsec");

/* Reserved table names to avoid collision */
global $reserved_table_names;
$reserved_table_names = array(
	"bogons",
	"bogonsv6",
	"negate_networks",
	"snort2c",
	"sshguard",
	"tonatsubnets",
	"virusprot",
	"vpn_networks",
);

/* VLAN Prio values. */
$vlanprio_values = array(
	"bk" => 0,
	"be" => 1,
	"ee" => 2,
	"ca" => 3,
	"vi" => 4,
	"vo" => 5,
	"ic" => 6,
	"nc" => 7
);

$vlanprio = array(
	"bk" => "Background (BK, 0)",
	"be" => "Best Effort (BE, 1)",
	"ee" => "Excellent Effort (EE, 2)",
	"ca" => "Critical Applications (CA, 3)",
	"vi" => "Video (VI, 4)",
	"vo" => "Voice (VO, 5)",
	"ic" => "Internetwork Control (IC, 6)",
	"nc" => "Network Control (NC, 7)"
);

global $system_log_files;
$system_log_files = array(
	"system", "filter", "dhcpd", "vpn", "poes", "l2tps", "openvpn",
	"portalauth", "ipsec", "ppp", "wireless", "nginx", "ntpd", "gateways",
	"resolver", "routing", "auth");

global $system_log_non_syslog_files;
$system_log_non_syslog_files = array('dmesg.boot', 'utx.log', 'userlog');

global $system_log_compression_types;
$system_log_compression_types = array(
	'bzip2' => array(
			'flag' => 'J',
			'cat' => '/usr/bin/bzcat -qf',
			'ext' => 'bz2',
		),
	'gzip' => array(
			'flag' => 'Z',
			'cat' => '/usr/bin/zcat -qf',
			'ext' => 'gz',
		),
	'xz' => array(
			'flag' => 'X',
			'cat' => '/usr/bin/xzcat -qf',
			'ext' => 'xz',
		),
	'zstd' => array(
			'flag' => 'Y',
			'cat' => '/usr/bin/zstdcat -qqf',
			'ext' => 'zst',
		),
	'none' => array(
			'flag' => '',
			'cat' => '/usr/bin/cat',
			'ext' => '',
		),
);

?>
