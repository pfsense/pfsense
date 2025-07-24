<?php
/*
 * globals.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
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
define('IPV4', 4);
define('IPV6', 6);
define('IPV4V6', 2);
define('ALIAS', 1);

// Interface Name Size
define('IF_NAMESIZE', 15);	/* 16 minus the terminating NULL */

// AddPassword method defines
define('DMYPWD', '********');

// Captive Portal aliases prefix
define('CPPREFIX', 'cpzoneid_');

$version_file = '/etc/version';
$version_patch_file = $version_file . 'patch';

$product_version = rtrim(file_get_contents($version_file));
$present_year = date('Y');

global $g;
$g = [
	'acbbackuppath' => '/cf/conf/acb/',
	'event_address' => 'unix:///var/run/check_reload_status',
	'factory_shipped_username' => 'admin',
	'factory_shipped_password' => 'pfsense',
	'upload_path' => '/root',
	'dhcpd_chroot_path' => '/var/dhcpd',
	'unbound_chroot_path' => '/var/unbound',
	'var_path' => '/var',
	'varrun_path' => '/var/run',
	'varetc_path' => '/var/etc',
	'vardb_path' => '/var/db',
	'varlog_path' => '/var/log',
	'etc_path' => '/etc',
	'tmp_path' => '/tmp',
	'tmp_path_user_code' => '/tmp/user_code',
	'conf_path' => '/conf',
	'conf_default_path' => '/conf.default',
	'cf_path' => '/cf',
	'cf_conf_path' => '/cf/conf',
	'www_path' => '/usr/local/www',
	'xml_rootobj' => 'pfsense',
	'admin_group' => 'admins',
	'product_name' => 'pfSense',
	'product_label' => 'pfSense',
	'product_label_html' => 'Netgate pfSense<sup>&#174;</sup>',
	'product_version' => $product_version,
	'product_copyright_years' => '2004 - ' . $present_year,
	'disablehelpmenu' => false,
	'disablehelpicon' => false,
	'disablecrashreporter' => false,
	'debug' => false,
	'latest_config' => '24.0',
	'minimum_ram_warning' => '101',
	'minimum_ram_warning_text' => '128 MB',
	'wan_interface_name' => 'wan',
	'captiveportal_path' => '/usr/local/captiveportal',
	'captiveportal_element_path' => '/var/db/cpelements',
	'captiveportal_element_sizelimit' => 1048576,
	'captiveportal_rules_interval' => 50,
	'services_dhcp_server_enable' => true,
	'wireless_regex' => '/^(ath|athp|bwi|bwn|ipw|iwi|iwlwifi|iwm|iwn|malo|mwl|ral|rsu|rtwn|rum|run|uath|upgt|ural|urtw|urtwn|wi|wpi|wtap|zyd)[0-9]+/',
	'help_base_url' => '/help.php',
	'pkg_prefix' => 'pfSense-pkg-',
	'default_timezone' => 'Etc/UTC',
	'language' => 'en_US',
	'default_config_backup_count' => 30,
	'default_cert_expiredays' => 27,
	'default_log_entries' => 500,
	'default_log_size' => 512000,
	'minimumtableentries_bogonsv6' => 400000,
	'alternativemetaports' => ['vmware', 'php72', 'php73', 'php74'],
	'backuppath' => [
		'captiveportal' => '/var/db/captiveportal*.db',
		'dhcpd' => '{/var/dhcpd/var/db/dhcpd.leases,/var/lib/kea/dhcp4.leases}',
		'dhcpdv6' => '{/var/dhcpd/var/db/dhcpd6.leases,/var/lib/kea/dhcp6.leases}',
		'voucher' => '/var/db/voucher_*.db'
	],
	'cp_prefix' => 'cpzoneid',
	'booting' => false,
	'rrddbpath' => '/var/db/rrd',
	'rrdtool' => '/usr/bin/nice -n20 /usr/local/bin/rrdtool'
];

/* IP TOS flags */
global $iptos;
$iptos = ['lowdelay', 'throughput', 'reliability'];

/* TCP flags */
global $tcpflags;
$tcpflags = ['syn', 'ack', 'fin', 'rst', 'psh', 'urg', 'ece', 'cwr'];

if (file_exists($version_patch_file)) {
	$g['product_version_patch'] = rtrim(file_get_contents($version_patch_file));
} else {
	$g['product_version_patch'] = '0';
}

$g['product_version_string'] = g_get('product_version');
if (is_numeric(g_get('product_version_patch')) && g_get('product_version_patch') != '0') {
	$g['product_version_string'] .= '-p'. g_get('product_version_patch');
}

$flavor_file = g_get('etc_path') . '/default-config-flavor';
if (file_exists($flavor_file)) {
	$flavor_array = file($flavor_file);
	$g['default-config-flavor'] = chop($flavor_array[0]);
} else {
	$g['default-config-flavor'] = '';
}

$g['openvpn_base'] = g_get('varetc_path') . '/openvpn';

$g['pkg_repos_path'] = '/usr/local/etc/' . $g['product_name'] . '/pkg/repos';

/* Default sysctls */
global $sysctls;
$sysctls = [
	'net.inet.ip.portrange.first' => '1024',
	'net.inet.tcp.blackhole' => '2',
	'net.inet.udp.blackhole' => '1',
	'net.inet.ip.random_id' => '1',
	'net.inet.tcp.drop_synfin' => '1',
	'net.inet.ip.redirect' => '1',
	'net.inet6.ip6.redirect' => '1',
	'net.inet6.ip6.use_tempaddr' => '0',
	'net.inet6.ip6.prefer_tempaddr' => '0',
	'net.inet.tcp.syncookies' => '1',
	'net.inet.tcp.recvspace' => '65228',
	'net.inet.tcp.sendspace' => '65228',
	'net.inet.tcp.delayed_ack' => '0',
	'net.inet.udp.maxdgram' => '57344',
	'net.link.bridge.pfil_onlyip' => '0',
	'net.link.bridge.pfil_member' => '1',
	'net.link.bridge.pfil_bridge' => '0',
	'net.link.tap.user_open' => '1',
	'net.link.vlan.mtag_pcp' => '1',
	'kern.randompid' => '347',
	'net.inet.ip.intr_queue_maxlen' => '1000',
	'hw.syscons.kbd_reboot' => '0',
	'net.inet.tcp.log_debug' => '0',
	'net.inet.tcp.tso' => '1',
	'net.inet.icmp.icmplim' => '0',
	'vfs.read_max' => '32',
	'kern.ipc.maxsockbuf' => '4262144',
	'net.inet.ip.process_options' => 0,
	'kern.random.harvest.mask' => '351',
	'net.route.netisr_maxqlen' => 1024,
	'net.inet.udp.checksum' => 1,
	'net.inet.icmp.reply_from_interface' => 1,
	'net.inet6.ip6.rfc6204w3' => 1,
	'net.key.preferred_oldsa' => '0',
	'net.inet.carp.senderr_demotion_factor' => 0, /* Do not demote CARP for interface send errors */
	'net.pfsync.carp_demotion_factor' => 0, /* Do not demote CARP for pfsync errors */
	'net.raw.recvspace' => 65536,
	'net.raw.sendspace' => 65536,
	'net.inet.raw.recvspace' => 131072,
	'net.inet.raw.maxdgram' => 131072,
	'kern.corefile' => '/root/%N.core' /* Write all core files to /root/ so they do not consume space on other slices */
];

$machine_type = php_uname('m');
if (($machine_type == 'arm') || ($machine_type == 'arm64')) {
	$sysctls['kern.shutdown.secure_halt'] = 1;
}

/* Read all XML files in following dir and load menu entries */
$g['ext_menu_path'] = '/usr/local/share/'. g_get('product_name') . '/menu';

/* Cache file used to store pfSense version */
$g['version_cache_file'] = g_get('varrun_path') . '/' . g_get('product_name') . '_version';
$g['version_cache_refresh'] = 7200; /* 2h */

if (file_exists(g_get('cf_conf_path') . '/enableserial_force')) {
	$g['enableserial_force'] = true;
}

global $config_parsed;
$config_parsed = false;

/* Factory default check IP service. */
global $factory_default_checkipservice;
$factory_default_checkipservice = [
	'enable' => true,
	'name' => 'Default',
	'url' => 'http://checkip.dyndns.org',
	'descr' => 'Default Check IP Service',
];

global $dyndns_split_domain_types;
$dyndns_split_domain_types = [
	'cloudflare', 'cloudflare-v6',
	'cloudns',
	"digitalocean", "digitalocean-v6",
	"gandi-livedns", "gandi-livedns-v6",
	'godaddy', 'godaddy-v6',
	'gratisdns',
	'linode', 'linode-v6',
	'luadns', 'luadns-v6',
	"mythicbeasts", "mythicbeasts-v6",
	'namecheap',
	"name.com", "name.com-v6",
	"onecom", "onecom-v6",
	'porkbun', 'porkbun-v6',
	"yandex", "yandex-v6",
];

// pf tokens from FreeBSD source /sbin/pfctl/parse.y
global $pf_reserved_keywords;
$pf_reserved_keywords = [
	// Original tokens
	'all', 'allow-opts', 'altq', 'anchor', 'antispoof', 'any', 'bandwidth', 'binat', 'binat-anchor', 'bitmask',
	'block', 'block-policy', 'buckets', 'cbq', 'code', 'codelq', 'crop', 'debug', 'divert-reply', 'divert-to',
	'dnpipe', 'dnqueue', 'drop', 'drop-ovl', 'dup-to', 'ether', 'fail-policy', 'fairq', 'fastroute', 'file',
	'fingerprints', 'flags', 'floating', 'flush', 'for', 'fragment', 'from', 'global', 'group', 'hfsc', 'hogs',
	'hostid', 'icmp-type', 'icmp6-type', 'if-bound', 'in', 'include', 'inet', 'inet6', 'interval', 'keep',
	'keepcounters', 'l3', 'label', 'limit', 'linkshare', 'load', 'log', 'loginterface', 'map-e-portset', 'match',
	'max', 'max-mss', 'max-src-conn', 'max-src-conn-rate', 'max-src-nodes', 'max-src-states', 'min-ttl', 'modulate',
	'nat', 'nat-anchor', 'no', 'no-df', 'no-route', 'no-sync', 'on', 'optimization', 'os', 'out', 'overload',
	'pass', 'port', 'prio', 'priority', 'priq', 'probability', 'proto', 'qlimit', 'queue', 'quick', 'random',
	'random-id', 'rdr', 'rdr-anchor', 'realtime', 'reassemble', 'reply-to', 'require-order', 'return', 'return-icmp',
	'return-icmp6', 'return-rst', 'ridentifier', 'round-robin', 'route', 'route-to', 'rtable', 'rule',
	'ruleset-optimization', 'scrub', 'set', 'set-tos', 'skip', 'sloppy', 'source-hash', 'source-track', 'state',
	'state-defaults', 'state-policy', 'static-port', 'sticky-address', 'syncookies', 'synproxy', 'table', 'tag',
	'tagged', 'target', 'tbrsize', 'timeout', 'to', 'tos', 'ttl', 'upperlimit', 'urpf-failed', 'user',
	// Original tokens in fuzzy format
	'allowopts', 'binatanchor', 'blockpolicy', 'divertreply', 'divertto', 'duptoicmptype', 'icmp6type', 'ifboundmaxmss',
	'maxsrcconn', 'maxsrcconnrate', 'maxsrcnodes', 'maxsrcstates', 'minttl', 'natanchornodf', 'noroute', 'nosync',
	'randomidrdranchor', 'replyto', 'requireorderreturnicmp', 'returnicmp6', 'returnrstroundrobinrouteto',
	'ruleset_optimization', 'settos', 'sourcehash', 'sourcetrackstatedefaults', 'statepolicy', 'staticport',
	'stickyaddress', 'urpffailed', 'dropovl', 'failpolicy', 'mapeportset',
	// Custom tokens
	'arrow', 'codel', 'error', 'filename', 'fragcrop', 'fragdrop', 'IPsec', 'L2TP', 'maximummin', 'OpenVPN',
	'pppoe', 'pptp'
];

/**
 * User-accessible read-only aliases reserved for system use.
 * 
 * Entries must have the name as the key. Each entry must have a name,
 * type, and description. Possible values are:
 * - name: Same value as the key; used for compatibility with other functions.
 * - url: A URL string for URL* types. Alternatively it may be a file path.
 *        If a file path is given, the alias is assumed to be processed
 *        separately such as with rc.update_bogons.sh.
 * - type: The alias type.
 * - descr: A string value.
 * - address: A string of space-separated values; may be defined
 *            dynamically e.g. during filter ruleset generation.
 * - detail: An optional string value.
 * 
 * @global array $reserved_table_names
 */
global $reserved_table_names;
$reserved_table_names = [
	'bogons' => [
		'name' => 'bogons',
		'type' => 'urltable',
		'url' => '/etc/bogons',
		'descr' => 'IPv4 bogons.',
		'address' => '',
		'detail' => ''
	],
	'bogonsv6' => [
		'name' => 'bogonsv6',
		'type' => 'urltable',
		'url' => '/etc/bogonsv6',
		'descr' => 'IPv6 bogons.',
		'address' => '',
		'detail' => ''
	],
	'sshguard' => [
		'name' => 'sshguard',
		'type' => 'host',
		'descr' => 'Hosts blocked by SSH login protection.',
		'address' => '',
		'detail' => ''
	],
	'snort2c' => [
		'name' => 'snort2c',
		'type' => 'host',
		'descr' => 'Hosts blocked by IDS.',
		'address' => '',
		'detail' => ''
	],
	'virusprot' => [
		'name' => 'virusprot',
		'type' => 'host',
		'descr' => 'Hosts blocked by rule restrictions.',
		'address' => '',
		'detail' => ''
	],
	'vpn_networks' => [
		'name' => 'vpn_networks',
		'type' => 'network',
		'descr' => 'Networks for IPsec, OpenVPN, and PPPoE servers.',
		'address' => '',
		'detail' => ''
	],
	'negate_networks' => [
		'name' => 'negate_networks',
		'type' => 'network',
		'descr' => 'Networks to exclude from policy routing rules with any destination.',
		'address' => '',
		'detail' => ''
	],
	'tonatsubnets' => [
		'name' => 'tonatsubnets',
		'type' => 'network',
		'descr' => 'Networks handled by automatic outbound NAT.',
		'address' => '',
		'detail' => ''
	],
];

function add_reserved_table(array $table, array $values) {
	global $reserved_table_names;
	if (!array_key_exists('name', $table)) {
		return;
	}
	$table['address'] = implode(' ', array_keys($values));
	$table['detail'] = implode('||', array_values($values));
	$reserved_table_names[$table['name']] = $table;
}

add_reserved_table(
	[
		'name' => '_loopback4_',
		'type' => 'network',
		'descr' => 'IPv4 loopback network.',
	],
	[
		'127.0.0.0/8' => 'Loopback', // RFC1122
	],
);
add_reserved_table(
	[
		'name' => '_loopback6_',
		'type' => 'network',
		'descr' => 'IPv6 loopback network.',
	],
	[
		'::1/128' => 'Loopback Address', // RFC4291
	],
);
add_reserved_table(
	[
		'name' => '_loopback46_',
		'type' => 'network',
		'descr' => 'IPv4 and IPv6 loopback networks.',
	],
	[
		'_loopback4_' => 'IPv4 Loopback',
		'_loopback6_' => 'IPv6 Loopback',
	],
);

add_reserved_table(
	[
		'name' => '_linklocal4_',
		'type' => 'network',
		'descr' => 'IPv4 link-local networks.',
	],
	[
		'169.254.0.0/16' => 'Link Local', // RFC3927
	],
);
add_reserved_table(
	[
		'name' => '_linklocal6_',
		'type' => 'network',
		'descr' => 'IPv6 link-local networks.',
	],
	[
		'fe80::/10' => 'Linked-Scoped Unicast', // RFC4291
	],
);
add_reserved_table(
	[
		'name' => '_linklocal46_',
		'type' => 'network',
		'descr' => 'IPv4 and IPv6 link-local networks.',
	],
	[
		'_linklocal4_' => 'IPv4 link-local networks',
		'_linklocal6_' => 'IPv6 link-local networks',
	],
);

add_reserved_table(
	[
		'name' => '_private4_',
		'type' => 'network',
		'descr' => 'IPv4 private networks.',
	],
	[
		'10.0.0.0/8' => 'Private-Use', // RFC1918
		'172.16.0.0/12' => 'Private-Use', // RFC1918
		'192.168.0.0/16' => 'Private-Use', // RFC1918

	],
);
add_reserved_table(
	[
		'name' => '_private6_',
		'type' => 'network',
		'descr' => 'IPv6 private networks.',
	],
	[
		'fc00::/7' => 'Unique-Local', // RFC4193
	],
);
add_reserved_table(
	[
		'name' => '_private46_',
		'type' => 'network',
		'descr' => 'IPv4 and IPv6 private networks.',
	],
	[
		'_private4_' => 'IPv4 private networks',
		'_private6_' => 'IPv6 private networks',
	],
);

add_reserved_table(
	[
		'name' => '_multicast4_',
		'type' => 'network',
		'descr' => 'IPv4 multicast networks.',
	],
	[
		'224.0.0.0/4' => 'Multicast', // RFC5771
	],
);
add_reserved_table(
	[
		'name' => '_multicast6_',
		'type' => 'network',
		'descr' => 'IPv6 multicast networks.',
	],
	[
		'ff00::/8' => 'Multicast', // RFC4291
	],
);
add_reserved_table(
	[
		'name' => '_multicast46_',
		'type' => 'network',
		'descr' => 'IPv4 and IPv6 multicast networks.',
	],
	[
		'_multicast4_' => 'IPv4 multicast networks',
		'_multicast6_' => 'IPv6 multicast networks',
	],
);

add_reserved_table(
	[
		'name' => '_reserved4_',
		'type' => 'network',
		'descr' => 'IPv4 networks reserved for local use.',
	],
	[
		'0.0.0.0/8' => 'This host on this network', // RFC1122
		'10.0.0.0/8' => 'Private-Use', // RFC1918
		'100.64.0.0/10' => 'Shared Address Space', // RFC6598
		'127.0.0.0/8' => 'Loopback', // RFC1122
		'169.254.0.0/16' => 'Link Local', // RFC3927
		'172.16.0.0/12' => 'Private-Use', // RFC1918
		'192.0.0.0/24' => 'IETF Protocol Assignments', // RFC6890
		'192.0.2.0/24' => 'Documentation (TEST-NET-1)', // RFC5737
		'192.88.99.0/24' => '6to4 Relay Anycast', // RFC3068
		'192.168.0.0/16' => 'Private-Use', // RFC1918
		'198.18.0.0/15' => 'Benchmarking', //RFC2544
		'198.51.100.0/24' => 'Documentation (TEST-NET-2)', // RFC5737
		'203.0.113.0/24' => 'Documentation (TEST-NET-3)', // RFC5737
		'224.0.0.0/4' => 'Multicast', // RFC5771
		'240.0.0.0/4' => 'Reserved', // RFC1112
		'255.255.255.255/32' => 'Limited Broadcast', // RFC0919
	]
);
add_reserved_table(
	[
		'name' => '_reserved6_',
		'type' => 'network',
		'descr' => 'IPv6 networks reserved for local use.',
	],
	[
		'::1/128' => 'Loopback Address', // RFC4291
		'::/128' => 'Unspecified Address', // RFC4291
		'::ffff:0:0/96' => 'IPv4-mapped Address', // RFC4291
		'64:ff9b::/96' => 'IPv4-IPv6 Translation', // RFC6052
		'64:ff9b:1::/48' => 'Local-Use IPv4/IPv6 Translation', // RFC8215
		'100::/64' => 'Discard-Only Address Block', // RFC6666
		'2001::/23' => 'IETF Protocol Assignments', // RFC2928
		'2001:2::/48' => 'Benchmarking', // RFC5180
		'2001:db8::/32' => 'Documentation', // RFC3849
		'2002::/16' => '6to4', // RFC3056
		'3fff::/20' => 'Documentation', // RFC9637
		'5f00::/16' => 'Segment Routing (SRv6) SIDs', // RFC9602
		'fc00::/7' => 'Unique-Local', // RFC4193
		'fe80::/10' => 'Linked-Scoped Unicast', // RFC4291
		'ff00::/8' => 'Multicast', // RFC4291
	]
);
add_reserved_table(
	[
		'name' => '_reserved46_',
		'type' => 'network',
		'descr' => 'IPv4 and IPv6 networks reserved for local use.',
	],
	[
		'_reserved4_' => 'IPv4 networks reserved for local use',
		'_reserved6_' => 'IPv6 networks reserved for local use',
	],
);

/* VLAN Prio values. */
global $vlanprio_values;
$vlanprio_values = [
	'bk' => 0,
	'be' => 1,
	'ee' => 2,
	'ca' => 3,
	'vi' => 4,
	'vo' => 5,
	'ic' => 6,
	'nc' => 7,
];

global $vlanprio;
$vlanprio = [
	'bk' => 'Background (BK, 0)',
	'be' => 'Best Effort (BE, 1)',
	'ee' => 'Excellent Effort (EE, 2)',
	'ca' => 'Critical Applications (CA, 3)',
	'vi' => 'Video (VI, 4)',
	'vo' => 'Voice (VO, 5)',
	'ic' => 'Internetwork Control (IC, 6)',
	'nc' => 'Network Control (NC, 7)',
];

global $system_log_files;
$system_log_files = [
	'system', 'filter', 'dhcpd', 'vpn', 'poes', 'l2tps', 'openvpn',
	'portalauth', 'ipsec', 'ppp', 'wireless', 'nginx', 'ntpd', 'gateways',
	'resolver', 'routing', 'auth'
];

global $system_log_non_syslog_files;
$system_log_non_syslog_files = ['dmesg.boot', 'utx.log', 'userlog'];

global $system_log_compression_types;
$system_log_compression_types = [
	'bzip2' => [
			'flag' => 'J',
			'cat' => '/usr/bin/bzcat -qf',
			'ext' => 'bz2',
	],
	'gzip' => [
			'flag' => 'Z',
			'cat' => '/usr/bin/zcat -qf',
			'ext' => 'gz',
	],
	'xz' => [
			'flag' => 'X',
			'cat' => '/usr/bin/xzcat -qf',
			'ext' => 'xz',
	],
	'zstd' => [
			'flag' => 'Y',
			'cat' => '/usr/bin/zstdcat -qqf',
			'ext' => 'zst',
	],
	'none' => [
			'flag' => '',
			'cat' => '/bin/cat',
			'ext' => '',
	],
];

global $ddnsdomainkeyalgorithms;
$ddnsdomainkeyalgorithms = [
	'hmac-md5' => 'HMAC-MD5 (legacy default)',
	'hmac-sha1' => 'HMAC-SHA1',
	'hmac-sha224' => 'HMAC-SHA224',
	'hmac-sha256' => 'HMAC-SHA256 (current bind9 default)',
	'hmac-sha384' => 'HMAC-SHA384',
	'hmac-sha512' => 'HMAC-SHA512 (most secure)',
];

global $ipsec_filtermodes;
$ipsec_filtermodes = [
	'enc' => 'Filter IPsec Tunnel, Transport, and VTI on IPsec tab (enc0)',
	'if_ipsec' => 'Filter IPsec VTI and Transport on assigned interfaces, block all tunnel mode traffic',
];

global $ipsec_filter_sysctl;
$ipsec_filter_sysctl = [
	'enc' => [
		'net.inet.ipsec.filtertunnel'   => '0x0000',
		'net.inet6.ipsec6.filtertunnel' => '0x0000',
		'net.enc.out.ipsec_bpf_mask'    => '0x0001',
		'net.enc.out.ipsec_filter_mask' => '0x0001',
		'net.enc.in.ipsec_bpf_mask'     => '0x0002',
		'net.enc.in.ipsec_filter_mask'  => '0x0002',
	],
	'if_ipsec' => [
		'net.inet.ipsec.filtertunnel'   => '0x0001',
		'net.inet6.ipsec6.filtertunnel' => '0x0001',
		'net.enc.out.ipsec_bpf_mask'    => '0x0000',
		'net.enc.out.ipsec_filter_mask' => '0x0000',
		'net.enc.in.ipsec_bpf_mask'     => '0x0000',
		'net.enc.in.ipsec_filter_mask'  => '0x0000',
	],
];

global $vpn_and_ppp_ifs;
$vpn_and_ppp_ifs = ['l2tp', 'pppoe', 'enc0', 'openvpn'];

global $ssh_keys;
$ssh_keys = [
	['type' => 'rsa', 'suffix' => 'rsa_'],
	['type' => 'ed25519', 'suffix' => 'ed25519_'],
];

global $sshConfigDir;
$sshConfigDir = '/etc/ssh';

global $lagg_hash_list;
$lagg_hash_list = [
	'l2,l3,l4' => 'Layer 2/3/4 (default)',
	'l2' => 'Layer 2 (MAC Address)',
	'l3' => 'Layer 3 (IP Address)',
	'l4' => 'Layer 4 (Port Number)',
	'l2,l3' => 'Layer 2/3 (MAC + IP)',
	'l3,l4' => 'Layer 3/4 (IP + Port)',
	'l2,l4' => 'Layer 2/4 (MAC + Port)',
];

/**
 * Check if the global $g variable contains a $key
 *
 * @param string $key The key
 * @param bool $isset Also perform isset check
 *
 * @return bool
 */
function g_has(string $key, bool $isset = false) : bool
{
	global $g;
	return (array_key_exists($key, $g) && (!$isset || isset($g[$key])));
}

/**
 * Get the global $g variable value by $key
 *
 * @param string $key The key
 * @param mixed $default The value to return on a key miss
 *
 * @return mixed
 */
function g_get(string $key, mixed $default = null) : mixed
{
	global $g;
	return (g_has($key, true) ? $g[$key] : $default);
}

/**
 * Set the global $g variable value by $key
 *
 * @param string $key The key
 * @param mixed $value The value
 * @param bool $force Force set (can replace) the value
 *
 * @return mixed
 */
function g_set(string $key, mixed $value, bool $force = false) : mixed
{
	global $g;
	if ($force || !g_has($key, true)) {
		$g[$key] = $value;
	}
	return (g_get($key));
}

/**
 * Unset the global $g variable value by $key
 *
 * @param string $key The key
 *
 * @return void
 */
function g_unset(string $key) : void
{
	global $g;
	if (g_has($key)) {
		unset($g[$key]);
	}
}

/**
 * Determine if the system is currently booting
 *
 * @return bool
 */
function is_platform_booting() : bool
{
	return (g_get('booting', false) || file_exists(g_get('varrun_path') . '/booting'));
}

/**
 * Determine if PHP is executing in cli context
 *
 * @return bool
 */
function is_cli_sapi() : bool
{
	if (defined('STDIN')) {
		return (true);
	}

	if (PHP_SAPI === 'cli') {
		return (true);
	}

	if (array_key_exists('SHELL', $_ENV)) {
		return (true);
	}

	return (false);
}

/**
 * Determine if the system is booting
 *
 * @deprecated Prefer the use of is_platform_booting
 *
 * @param bool $only_on_console Require cli execution context
 *
 * @return bool
 */
function platform_booting(bool $only_on_console = false) : bool
{
	return (is_platform_booting() && (!$only_on_console || is_cli_sapi()));
}

/**
 * Check if a file can be included
 *
 * @param string $filename
 *
 * @return bool
 */
function can_include(string $filename) : bool
{
	// short-circuit on absolute paths before checking relative include paths
	return (file_exists($filename) || (bool) stream_resolve_include_path($filename));
}

/**
 * Get a list of known protocols
 *
 * @param string $type Filter the returned list based on the type
 *
 * @return array
 */
function get_ipprotocols(?string $type = ''):array {
	$exclude = [];
	$ipprotocols = [
		'any' => gettext('Any'),
		'tcp' => 'TCP',
		'udp' => 'UDP',
		'tcp/udp' => 'TCP/UDP',
		'icmp' => 'ICMP',
		'esp' => 'ESP',
		'ah' => 'AH',
		'gre' => 'GRE',
		'etherip' => 'EoIP',
		'ipv6' => 'IPV6',
		'igmp' => 'IGMP',
		'pim' => 'PIM',
		'ospf' => 'OSPF',
		'sctp' => 'SCTP',
		'carp' => 'CARP',
		'pfsync' => 'PFSYNC'
	];

	switch ($type) {
		case 'portsonly':
			$ipprotocols = [
				'tcp' => 'TCP',
				'udp' => 'UDP',
				'tcp/udp' => 'TCP/UDP',
				'sctp' => 'SCTP'
			];
			break;
		case 'outboundnat':
			$exclude = ['EoIP', 'PIM', 'OSPF'];
			break;
		case 'portforward':
			$exclude = ['EoIP', 'CARP', 'PFSYNC'];
			break;
		default:
			break;
	}

	return array_diff($ipprotocols, $exclude);
}

// source pfSense Plus specific globals last
if (can_include('globals.plus.inc')) {
	include_once('globals.plus.inc');
}
