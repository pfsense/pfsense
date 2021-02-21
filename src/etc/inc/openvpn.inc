<?php
/*
 * openvpn.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006 Fernando Lemos
 * Copyright (c) 2006-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * This file was rewritten from scratch by Fernando Lemos but
 * *MIGHT* contain code previously written by:
 *
 * Copyright (c) 2005 Peter Allgeyer <allgeyer_AT_web.de>
 * Copyright (c) 2004 Peter Curran (peter@closeconsultants.com).
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

require_once('config.inc');
require_once("certs.inc");
require_once('pfsense-utils.inc');
require_once("auth.inc");

global $openvpn_prots;
$openvpn_prots = array(
	"UDP4" => "UDP on IPv4 only",
	"UDP6" => "UDP on IPv6 only",
	"TCP4" => "TCP on IPv4 only",
	"TCP6" => "TCP on IPv6 only",
	"UDP" => "UDP IPv4 and IPv6 on all interfaces (multihome)",
	"TCP" => "TCP IPv4 and IPv6 on all interfaces (multihome)"
);

global $openvpn_dev_mode;
$openvpn_dev_mode = array(
	"tun" => "tun - Layer 3 Tunnel Mode",
	"tap" => "tap - Layer 2 Tap Mode"
);

global $openvpn_verbosity_level;
$openvpn_verbosity_level = array(
	0 =>	gettext("none"),
	1 =>	gettext("default"),
	2 =>	"2",
	3 =>	gettext("3 (recommended)"),
	4 =>	"4",
	5 => 	"5",
	6 => 	"6",
	7 => 	"7",
	8 => 	"8",
	9 => 	"9",
	10 => 	"10",
	11 => 	"11"
);

/*
 * The User Auth mode below is disabled because
 * OpenVPN erroneously requires that we provide
 * a CA configuration parameter. In this mode,
 * clients don't send a certificate so there is
 * no need for a CA. If we require that admins
 * provide one in the pfSense UI due to a bogus
 * requirement imposed by OpenVPN, it could be
 * considered very confusing ( I know I was ).
 *
 * -mgrooms
 */

global $openvpn_dh_lengths;
$openvpn_dh_lengths = array(
	1024 => "1024 bit",
	2048 => "2048 bit",
	3072 => "3072 bit",
	4096 => "4096 bit",
	6144 => "6144 bit",
	7680 => "7680 bit",
	8192 => "8192 bit",
	15360 => "15360 bit",
	16384 => "16384 bit",
	"none" => "ECDH Only"
);
foreach ($openvpn_dh_lengths as $idx => $dhlen) {
	if (is_numeric($idx) && !file_exists("/etc/dh-parameters.{$idx}")) {
		unset($openvpn_dh_lengths[$idx]);
	}
}

global $openvpn_cert_depths;
$openvpn_cert_depths = array(
	1 => gettext("One (Client+Server)"),
	2 => gettext("Two (Client+Intermediate+Server)"),
	3 => gettext("Three (Client+2xIntermediate+Server)"),
	4 => gettext("Four (Client+3xIntermediate+Server)"),
	5 => gettext("Five (Client+4xIntermediate+Server)")
);

global $openvpn_server_modes;
$openvpn_server_modes = array(
	'p2p_tls' => gettext("Peer to Peer ( SSL/TLS )"),
	'p2p_shared_key' => gettext("Peer to Peer ( Shared Key )"),
	'server_tls' => gettext("Remote Access ( SSL/TLS )"),
	'server_user' => gettext("Remote Access ( User Auth )"),
	'server_tls_user' => gettext("Remote Access ( SSL/TLS + User Auth )"));

global $openvpn_tls_server_modes;
$openvpn_tls_server_modes = array('p2p_tls', 'server_tls', 'server_user', 'server_tls_user');

global $openvpn_client_modes;
$openvpn_client_modes = array(
	'p2p_tls' => gettext("Peer to Peer ( SSL/TLS )"),
	'p2p_shared_key' => gettext("Peer to Peer ( Shared Key )"));

global $openvpn_allow_compression;
$openvpn_allow_compression = array(
	'asym' => gettext("Decompress incoming, do not compress outgoing (Asymmetric)"),
	'no'   => gettext("Refuse any non-stub compression (Most secure)"),
	'yes'  => gettext("Compress packets (WARNING: Potentially dangerous!)"),
);

global $openvpn_compression_modes;
$openvpn_compression_modes = array(
	'' => gettext("Disable Compression [Omit Preference]"),
	'none' => gettext("Disable Compression, retain compression packet framing [compress]"),
	'stub' => gettext("Enable Compression (stub) [compress stub]"),
	'stub-v2' => gettext("Enable Compression (stub v2) [compress stub-v2]"),
	'lz4' => gettext("LZ4 Compression [compress lz4]"),
	'lz4-v2' => gettext("LZ4 Compression v2 [compress lz4-v2]"),
	'lzo' => gettext("LZO Compression [compress lzo, equivalent to comp-lzo yes for compatibility]"),
	'noadapt' => gettext("Omit Preference, + Disable Adaptive LZO Compression [Legacy style, comp-noadapt]"),
	'adaptive' => gettext("Adaptive LZO Compression [Legacy style, comp-lzo adaptive]"),
	'yes' => gettext("LZO Compression [Legacy style, comp-lzo yes]"),
	'no' => gettext("No LZO Compression [Legacy style, comp-lzo no]"),
);

global $openvpn_topologies;
$openvpn_topologies = array(
	'subnet' => gettext("Subnet -- One IP address per client in a common subnet"),
	'net30' => gettext("net30 -- Isolated /30 network per client")
);

global $openvpn_tls_modes;
$openvpn_tls_modes = array(
	'auth' => gettext("TLS Authentication"),
	'crypt' => gettext("TLS Encryption and Authentication")
);

global $openvpn_ping_method;
$openvpn_ping_method = array(
	'keepalive' => gettext("keepalive -- Use keepalive helper to define ping configuration"),
	'ping' => gettext("ping -- Define ping/ping-exit/ping-restart manually")
);

global $openvpn_default_keepalive_interval;
$openvpn_default_keepalive_interval = 10;

global $openvpn_default_keepalive_timeout;
$openvpn_default_keepalive_timeout = 60;

global $openvpn_ping_action;
$openvpn_ping_action = array(
	'ping_restart' => gettext("ping-restart -- Restart OpenVPN after timeout"),
	'ping_exit' => gettext("ping-exit -- Exit OpenVPN after timeout")
);

global $openvpn_exit_notify_server;
$openvpn_exit_notify_server = array(
	'none' => gettext("Disabled"),
	'1' => gettext("Reconnect to this server / Retry once"),
	'2' => gettext("Reconnect to next server / Retry twice"),
);

global $openvpn_exit_notify_client;
$openvpn_exit_notify_client = array(
	'none' => gettext("Disabled"),
);
for ($i=1; $i<=5; $i++) {
	$openvpn_exit_notify_client[$i] = sprintf(gettext("Retry %dx"), $i);
}

function openvpn_build_mode_list() {
	global $openvpn_server_modes;

	$list = array();

	foreach ($openvpn_server_modes as $name => $desc) {
		$list[$name] = $desc;
	}

	return($list);
}

global $openvpn_interfacenames;
function convert_openvpn_interface_to_friendly_descr($if) {
	global $openvpn_interfacenames;
	if (!is_array($openvpn_interfacenames)) {
		$openvpn_interfacenames = openvpn_build_if_list();
	}
	foreach($openvpn_interfacenames as $key => $value) {
		list($item_if, $item_ip) = explode("|", $key);
		if ($if == $item_if) {
			echo "$value";
		}
	}
}

function openvpn_build_if_list() {
	$list = array();

	$interfaces = get_configured_interface_with_descr();
	$viplist = get_configured_vip_list();
	foreach ($viplist as $vip => $address) {
		$interfaces[$vip.'|'.$address] = $address;
		if (get_vip_descr($address)) {
			$interfaces[$vip.'|'.$address] .= " (";
			$interfaces[$vip.'|'.$address] .= get_vip_descr($address);
			$interfaces[$vip.'|'.$address] .= ")";
		}
	}

	$grouplist = return_gateway_groups_array();
	foreach ($grouplist as $name => $group) {
		if ($group[0]['vip'] != "") {
			$vipif = $group[0]['vip'];
		} else {
			$vipif = $group[0]['int'];
		}

		$interfaces[$name] = "GW Group {$name}";
	}

	$interfaces['lo0'] = "Localhost";
	$interfaces['any'] = "any";

	foreach ($interfaces as $iface => $ifacename) {
	   $list[$iface] = $ifacename;
	}

	return($list);
}

function openvpn_build_crl_list() {
	global $a_crl;

	$list = array('' => 'None');

	foreach ($a_crl as $crl) {
		$caname = "";
		$ca = lookup_ca($crl['caref']);

		if ($ca) {
			$caname = " (CA: {$ca['descr']})";
		}

		$list[$crl['refid']] = $crl['descr'] . $caname;
	}

	return($list);
}

function openvpn_build_cert_list($include_none = false, $prioritize_server_certs = false) {
	global $a_cert;
	$openvpn_compatible_certs = cert_build_list('cert', 'OpenVPN');

	if ($include_none) {
		$list = array('' => gettext('None (Username and/or Password required)'));
	} else {
		$list = array();
	}

	$non_server_list = array();

	if ($prioritize_server_certs) {
		$list[' '] = gettext("===== Server Certificates =====");
		$non_server_list['  '] = gettext("===== Non-Server Certificates =====");
	}

	foreach ($a_cert as $cert) {
		if (!array_key_exists($cert['refid'], $openvpn_compatible_certs)) {
			continue;
		}
		$properties = array();
		$propstr = "";
		$ca = lookup_ca($cert['caref']);
		$purpose = cert_get_purpose($cert['crt'], true);

		if ($purpose['server'] == "Yes") {
			$properties[] = gettext("Server: Yes");
		} elseif ($prioritize_server_certs) {
			$properties[] = gettext("Server: NO");
		}
		if ($ca) {
			$properties[] = sprintf(gettext("CA: %s"), $ca['descr']);
		}
		if (cert_in_use($cert['refid'])) {
			$properties[] = gettext("In Use");
		}
		if (is_cert_revoked($cert)) {
			$properties[] = gettext("Revoked");
		}

		if (!empty($properties)) {
			$propstr = " (" . implode(", ", $properties) . ")";
		}

		if ($prioritize_server_certs) {
			if ($purpose['server'] == "Yes") {
				$list[$cert['refid']] = $cert['descr'] . $propstr;
			} else {
				$non_server_list[$cert['refid']] = $cert['descr'] . $propstr;
			}
		} else {
			$list[$cert['refid']] = $cert['descr'] . $propstr;
		}
	}

	return(array('server' => $list, 'non-server' => $non_server_list));
}

function openvpn_build_bridge_list() {
	$list = array();

	$serverbridge_interface['none'] = "none";
	$serverbridge_interface = array_merge($serverbridge_interface, get_configured_interface_with_descr());
	$viplist = get_configured_vip_list();

	foreach ($viplist as $vip => $address) {
		$serverbridge_interface[$vip.'|'.$address] = $address;
		if (get_vip_descr($address)) {
			$serverbridge_interface[$vip.'|'.$address] .= " (". get_vip_descr($address) .")";
		}
	}

	foreach ($serverbridge_interface as $iface => $ifacename) {
		$list[$iface] = htmlspecialchars($ifacename);
	}

	return($list);
}

function openvpn_create_key() {

	$fp = popen("/usr/local/sbin/openvpn --genkey secret /dev/stdout 2>/dev/null", "r");
	if (!$fp) {
		return false;
	}

	$rslt = stream_get_contents($fp);
	pclose($fp);

	return $rslt;
}

function openvpn_create_dhparams($bits) {

	$fp = popen("/usr/bin/openssl dhparam {$bits} 2>/dev/null", "r");
	if (!$fp) {
		return false;
	}

	$rslt = stream_get_contents($fp);
	pclose($fp);

	return $rslt;
}

function openvpn_vpnid_used($vpnid) {
	global $config;

	if (is_array($config['openvpn']['openvpn-server'])) {
		foreach ($config['openvpn']['openvpn-server'] as & $settings) {
			if ($vpnid == $settings['vpnid']) {
				return true;
			}
		}
	}

	if (is_array($config['openvpn']['openvpn-client'])) {
		foreach ($config['openvpn']['openvpn-client'] as & $settings) {
			if ($vpnid == $settings['vpnid']) {
				return true;
			}
		}
	}

	return false;
}

function openvpn_vpnid_next() {

	$vpnid = 1;
	while (openvpn_vpnid_used($vpnid)) {
		$vpnid++;
	}

	return $vpnid;
}

function openvpn_port_used($prot, $interface, $port, $curvpnid = 0) {
	global $config;

	$ovpn_settings = array();
	if (is_array($config['openvpn']['openvpn-server'])) {
		$ovpn_settings = $config['openvpn']['openvpn-server'];
	}
	if (is_array($config['openvpn']['openvpn-client'])) {
		$ovpn_settings = array_merge($ovpn_settings,
		    $config['openvpn']['openvpn-client']);
	}

	foreach ($ovpn_settings as $settings) {
		if (isset($settings['disable'])) {
			continue;
		}

		if ($curvpnid != 0 && $curvpnid == $settings['vpnid']) {
			continue;
		}

		/* (TCP|UDP)(4|6) does not conflict unless interface is any */
		if (($interface != "any" && $settings['interface'] != "any") &&
		    (strlen($prot) == 4) &&
		    (strlen($settings['protocol']) == 4) &&
		    substr($prot,0,3) == substr($settings['protocol'],0,3) &&
		    substr($prot,3,1) != substr($settings['protocol'],3,1)) {
			continue;
		}

		if ($port == $settings['local_port'] &&
		    substr($prot,0,3) == substr($settings['protocol'],0,3) &&
		    ($interface == $settings['interface'] ||
		    $interface == "any" || $settings['interface'] == "any")) {
			return $settings['vpnid'];
		}
	}

	return 0;
}

function openvpn_port_next($prot, $interface = "wan") {

	$port = 1194;
	while (openvpn_port_used($prot, $interface, $port)) {
		$port++;
	}
	while (openvpn_port_used($prot, "any", $port)) {
		$port++;
	}

	return $port;
}

function openvpn_get_cipherlist() {

	$ciphers = array();
	$cipher_out = shell_exec('/usr/local/sbin/openvpn --show-ciphers | /usr/bin/grep \'(.*key\' | sed \'s/, TLS client\/server mode only//\'');
	$cipher_lines = explode("\n", trim($cipher_out));
	sort($cipher_lines);
	foreach ($cipher_lines as $line) {
		$words = explode(' ', $line, 2);
		$ciphers[$words[0]] = "{$words[0]} {$words[1]}";
	}
	$ciphers["none"] = gettext("None (No Encryption)");
	return $ciphers;
}

function openvpn_get_curvelist() {
	global $cert_curve_compatible;
	$curves = array();
	$curves["none"] = gettext("Use Default");
	foreach ($cert_curve_compatible['OpenVPN'] as $curve) {
		$curves[$curve] = $curve;
	}
	return $curves;
}

function openvpn_validate_curve($curve) {
	$curves = openvpn_get_curvelist();
	return array_key_exists($curve, $curves);
}

/* Obtain the list of digest algorithms supported by openssl and their alternate names */
function openvpn_get_openssldigestmappings() {
	$digests = array();
	$digest_out = shell_exec('/usr/bin/openssl list-message-digest-algorithms | /usr/bin/grep "=>"');
	$digest_lines = explode("\n", trim($digest_out));
	sort($digest_lines);
	foreach ($digest_lines as $line) {
		$words = explode(' => ', $line, 2);
		$digests[$words[0]] = $words[1];
	}
	return $digests;
}

/* Obtain the list of digest algorithms supported by openvpn */
function openvpn_get_digestlist() {
	/* Grab the list from OpenSSL to check for duplicates or aliases */
	$openssl_digest_mappings = openvpn_get_openssldigestmappings();
	$digests = array();
	$digest_out = shell_exec('/usr/local/sbin/openvpn --show-digests | /usr/bin/grep "digest size" | /usr/bin/awk \'{print $1, "(" $2 "-" $3 ")";}\'');
	$digest_lines = explode("\n", trim($digest_out));
	sort($digest_lines);
	foreach ($digest_lines as $line) {
		$words = explode(' ', $line);
		/* Only add the entry if it is NOT also listed as being an alias/mapping by OpenSSL */
		if (!array_key_exists($words[0], $openssl_digest_mappings)) {
			$digests[$words[0]] = "{$words[0]} {$words[1]}";
		}
	}
	$digests["none"] = gettext("None (No Authentication)");
	return $digests;
}

/* Check to see if a digest name is an alias and if so, find the actual digest
 * algorithm instead. Useful for upgrade code that has to translate aliased
 * algorithms to their actual names.
 */
function openvpn_remap_digest($digest) {
	$openssl_digest_mappings = openvpn_get_openssldigestmappings();
	if (array_key_exists($digest, $openssl_digest_mappings)) {
		/* Some mappings point to other mappings, keep going until we find the actual digest algorithm */
		if (array_key_exists($openssl_digest_mappings[$digest], $openssl_digest_mappings)) {
			return openvpn_remap_digest($openssl_digest_mappings[$digest]);
		} else {
			return $openssl_digest_mappings[$digest];
		}
	}
	return $digest;
}

function openvpn_get_keydirlist() {
	$keydirs = array(
		'default'  => gettext('Use default direction'),
		'0' => gettext('Direction 0'),
		'1' => gettext('Direction 1'),
		'2' => gettext('Both directions'),
	);
	return $keydirs;
}

function openvpn_get_engines() {
	$openssl_engines = array('none' => gettext('No Hardware Crypto Acceleration'));
	exec("/usr/bin/openssl engine -t -c", $openssl_engine_output);
	$openssl_engine_output = implode("\n", $openssl_engine_output);
	$openssl_engine_output = preg_replace("/\\n\\s+/", "|", $openssl_engine_output);
	$openssl_engine_output = explode("\n", $openssl_engine_output);

	foreach ($openssl_engine_output as $oeo) {
		$keep = true;
		$details = explode("|", $oeo);
		$engine = array_shift($details);
		$linematch = array();
		preg_match("/\((.*)\)\s(.*)/", $engine, $linematch);
		foreach ($details as $dt) {
			if (strpos($dt, "unavailable") !== FALSE) {
				$keep = false;
			}
			if (strpos($dt, "available") !== FALSE) {
				continue;
			}
			if (strpos($dt, "[") !== FALSE) {
				$ciphers = trim($dt, "[]");
			}
		}
		if (!empty($ciphers)) {
			$ciphers = " - " . $ciphers;
		}
		if (strlen($ciphers) > 60) {
			$ciphers = substr($ciphers, 0, 60) . " ... ";
		}
		if ($keep) {
			$openssl_engines[$linematch[1]] = $linematch[2] . $ciphers;
		}
	}
	return $openssl_engines;
}

function openvpn_get_buffer_values() {
	$sendbuf_max = get_single_sysctl('net.inet.tcp.sendbuf_max');
	$recvbuf_max = get_single_sysctl('net.inet.tcp.recvbuf_max');
	/* Usually these two are equal, but if they are not, take whichever one is lower. */
	$buffer_max = ($sendbuf_max <= $recvbuf_max) ? $sendbuf_max : $recvbuf_max;
	$buffer_values = array('' => gettext('Default'));
	for ($bs = 32; $bs >= 1; $bs /= 2) {
		$buffer_values[$buffer_max/$bs] = format_bytes($buffer_max/$bs);
	}
	return $buffer_values;
}

function openvpn_validate_engine($engine) {
	$engines = openvpn_get_engines();
	return array_key_exists($engine, $engines);
}

function openvpn_validate_host($value, $name) {
	$value = trim($value);
	if (empty($value) || (!is_domain($value) && !is_ipaddr($value))) {
		return sprintf(gettext("The field '%s' must contain a valid IP address or domain name."), $name);
	}
	return false;
}

function openvpn_validate_port($value, $name, $first_port = 0) {
	$value = trim($value);
	if (!is_numeric($first_port)) {
		$first_port = 0;
	}
	if (empty($value) || !is_numeric($value) || $value < $first_port || ($value > 65535)) {
		return sprintf(gettext("The field '%s' must contain a valid port, ranging from %s to 65535."), $name, $first_port);
	}
	return false;
}

function openvpn_validate_cidr($value, $name, $multiple = false, $ipproto = "ipv4") {
	$value = trim($value);
	$error = false;
	if (empty($value)) {
		return false;
	}
	$networks = explode(',', $value);

	if (!$multiple && (count($networks) > 1)) {
		return sprintf(gettext("The field '%1\$s' must contain a single valid %2\$s CIDR range."), $name, $ipproto);
	}

	foreach ($networks as $network) {
		if ($ipproto == "ipv4") {
			$error = !openvpn_validate_cidr_ipv4($network);
		} else {
			$error = !openvpn_validate_cidr_ipv6($network);
		}
		if ($error) {
			break;
		}
	}

	if ($error) {
		return sprintf(gettext("The field '%1\$s' must contain only valid %2\$s CIDR range(s) separated by commas."), $name, $ipproto);
	} else {
		return false;
	}
}

function openvpn_validate_cidr_ipv4($value) {
	$value = trim($value);
	if (!empty($value)) {
		list($ip, $mask) = explode('/', $value);
		if (!is_ipaddrv4($ip) or !is_numeric($mask) or ($mask > 32) or ($mask < 0)) {
			return false;
		}
	}
	return true;
}

function openvpn_validate_cidr_ipv6($value) {
	$value = trim($value);
	if (!empty($value)) {
		list($ipv6, $prefix) = explode('/', $value);
		if (empty($prefix)) {
			$prefix = "128";
		}
		if (!is_ipaddrv6($ipv6) or !is_numeric($prefix) or ($prefix > 128) or ($prefix < 0)) {
			return false;
		}
	}
	return true;
}

function openvpn_add_dhcpopts(& $settings, & $conf) {

	if (!empty($settings['dns_domain'])) {
		$conf .= "push \"dhcp-option DOMAIN {$settings['dns_domain']}\"\n";
	}

	if (!empty($settings['dns_server1'])) {
		$dnstype = (is_ipaddrv6($settings['dns_server1'])) ? "DNS6" : "DNS";
		$conf .= "push \"dhcp-option {$dnstype} {$settings['dns_server1']}\"\n";
	}
	if (!empty($settings['dns_server2'])) {
		$dnstype = (is_ipaddrv6($settings['dns_server2'])) ? "DNS6" : "DNS";
		$conf .= "push \"dhcp-option {$dnstype} {$settings['dns_server2']}\"\n";
	}
	if (!empty($settings['dns_server3'])) {
		$dnstype = (is_ipaddrv6($settings['dns_server3'])) ? "DNS6" : "DNS";
		$conf .= "push \"dhcp-option {$dnstype} {$settings['dns_server3']}\"\n";
	}
	if (!empty($settings['dns_server4'])) {
		$dnstype = (is_ipaddrv6($settings['dns_server4'])) ? "DNS6" : "DNS";
		$conf .= "push \"dhcp-option {$dnstype} {$settings['dns_server4']}\"\n";
	}

	if (!empty($settings['push_blockoutsidedns'])) {
		$conf .= "push \"block-outside-dns\"\n";
	}
	if (!empty($settings['push_register_dns'])) {
		$conf .= "push \"register-dns\"\n";
	}

	if (!empty($settings['ntp_server1'])) {
		$conf .= "push \"dhcp-option NTP {$settings['ntp_server1']}\"\n";
	}
	if (!empty($settings['ntp_server2'])) {
		$conf .= "push \"dhcp-option NTP {$settings['ntp_server2']}\"\n";
	}

	if ($settings['netbios_enable']) {

		if (!empty($settings['dhcp_nbttype']) && ($settings['dhcp_nbttype'] != 0)) {
			$conf .= "push \"dhcp-option NBT {$settings['dhcp_nbttype']}\"\n";
		}
		if (!empty($settings['dhcp_nbtscope'])) {
			$conf .= "push \"dhcp-option NBS {$settings['dhcp_nbtscope']}\"\n";
		}

		if (!empty($settings['wins_server1'])) {
			$conf .= "push \"dhcp-option WINS {$settings['wins_server1']}\"\n";
		}
		if (!empty($settings['wins_server2'])) {
			$conf .= "push \"dhcp-option WINS {$settings['wins_server2']}\"\n";
		}

		if (!empty($settings['nbdd_server1'])) {
			$conf .= "push \"dhcp-option NBDD {$settings['nbdd_server1']}\"\n";
		}
	}

	if ($settings['gwredir']) {
		$conf .= "push \"redirect-gateway def1\"\n";
	}
	if ($settings['gwredir6']) {
		$conf .= "push \"redirect-gateway ipv6\"\n";
	}
}

function openvpn_add_custom(& $settings, & $conf) {

	if ($settings['custom_options']) {

		$options = explode(';', $settings['custom_options']);

		if (is_array($options)) {
			foreach ($options as $option) {
				$conf .= "$option\n";
			}
		} else {
			$conf .= "{$settings['custom_options']}\n";
		}
	}
}

function openvpn_add_keyfile(& $data, & $conf, $mode_id, $directive, $opt = "") {
	global $g;

	$fpath = "{$g['openvpn_base']}/{$mode_id}/{$directive}";
	openvpn_create_dirs();
	file_put_contents($fpath, base64_decode($data));
	//chown($fpath, 'nobody');
	//chgrp($fpath, 'nobody');
	@chmod($fpath, 0600);

	$conf .= "{$directive} {$fpath} {$opt}\n";
}

function openvpn_reconfigure($mode, $settings) {
	global $g, $config, $openvpn_tls_server_modes, $openvpn_dh_lengths, $openvpn_default_keepalive_interval, $openvpn_default_keepalive_timeout;

	if (empty($settings)) {
		return;
	}
	if (isset($settings['disable'])) {
		return;
	}
	openvpn_create_dirs();
	/*
	 * NOTE: Deleting tap devices causes spontaneous reboots. Instead,
	 * we use a vpnid number which is allocated for a particular client
	 * or server configuration. ( see openvpn_vpnid_next() )
	 */

	$vpnid = $settings['vpnid'];
	$mode_id = $mode.$vpnid;

	if (isset($settings['dev_mode'])) {
		$tunname = "{$settings['dev_mode']}{$vpnid}";
	} else {
		/* defaults to tun */
		$tunname = "tun{$vpnid}";
		$settings['dev_mode'] = "tun";
	}

	if ($mode == "server") {
		$devname = "ovpns{$vpnid}";
	} else {
		$devname = "ovpnc{$vpnid}";
	}

	/* is our device already configured */
	if (!does_interface_exist($devname)) {

		/* create the tap device if required */
		if (!file_exists("/dev/{$tunname}")) {
			exec("/sbin/ifconfig " . escapeshellarg($tunname) . " create");
		}

		/* rename the device */
		mwexec("/sbin/ifconfig " . escapeshellarg($tunname) . " name " . escapeshellarg($devname));

		/* add the device to the openvpn group and make sure it's UP*/
		mwexec("/sbin/ifconfig " . escapeshellarg($devname) . " group openvpn up");

		$ifname = convert_real_interface_to_friendly_interface_name($devname);
		$grouptmp = link_interface_to_group($ifname);
		if (!empty($grouptmp)) {
			array_walk($grouptmp, 'interface_group_add_member');
		}
		unset($grouptmp, $ifname);
	}

	$pfile = $g['varrun_path'] . "/openvpn_{$mode_id}.pid";
	$proto = strtolower($settings['protocol']);
	if (substr($settings['protocol'], 0, 3) == "TCP") {
			$proto = "{$proto}-{$mode}";
	}
	$dev_mode = $settings['dev_mode'];
	$fbcipher = $settings['data_ciphers_fallback'];
	// OpenVPN defaults to SHA1, so use it when unset to maintain compatibility.
	$digest = !empty($settings['digest']) ? $settings['digest'] : "SHA1";

	$interface = get_failover_interface($settings['interface']);
	// The IP address in the settings can be an IPv4 or IPv6 address associated with the interface
	$ipaddr = $settings['ipaddr'];

	// If a specific ip address (VIP) is requested, use it.
	// Otherwise, if a specific interface is requested, use it
	// If "any" interface was selected, local directive will be omitted.
	if (is_ipaddrv4($ipaddr)) {
		$iface_ip = $ipaddr;
	} elseif (!empty($interface) && strcmp($interface, "any")) {
		$iface_ip=get_interface_ip($interface);
	}
	if (is_ipaddrv6($ipaddr)) {
		$iface_ipv6 = $ipaddr;
	} elseif (!empty($interface) && strcmp($interface, "any")) {
		$iface_ipv6=get_interface_ipv6($interface);
	}

	$conf = "dev {$devname}\n";
	if (isset($settings['verbosity_level'])) {
		$conf .= "verb {$settings['verbosity_level']}\n";
	}

	$conf .= "dev-type {$settings['dev_mode']}\n";
	$conf .= "dev-node /dev/{$tunname}\n";
	$conf .= "writepid {$pfile}\n";
	$conf .= "#user nobody\n";
	$conf .= "#group nobody\n";
	$conf .= "script-security 3\n";
	$conf .= "daemon\n";

	if (!empty($settings['inactive_seconds'])) {
		$conf .= "inactive {$settings['inactive_seconds']}\n";
	}

	if (!empty($settings['ping_method']) &&
	    $settings['ping_method'] == 'ping') {
		$conf .= "ping {$settings['ping_seconds']}\n";

		if (!empty($settings['ping_push'])) {
			$conf .= "push \"ping {$settings['ping_seconds']}\"\n";
		}

		$action = str_replace("_", "-", $settings['ping_action']);
		$conf .= "{$action} {$settings['ping_action_seconds']}\n";

		if (!empty($settings['ping_action_push'])) {
			$conf .= "push \"{$action} " .
			    "{$settings['ping_action_seconds']}\"\n";
		}
	} else {
		$conf .= "keepalive " .
		    ($settings['keepalive_interval']
			?: $openvpn_default_keepalive_interval) . " " .
		    ($settings['keepalive_timeout']
			?: $openvpn_default_keepalive_timeout) . "\n";
	}

	$conf .= "ping-timer-rem\n";
	$conf .= "persist-tun\n";
	$conf .= "persist-key\n";
	$conf .= "proto {$proto}\n";
	$conf .= "auth {$digest}\n";
	if ($settings['dns_add']) {
		$conf .= "up /usr/local/sbin/ovpn-dnslinkup\n";
	} else {
		$conf .= "up /usr/local/sbin/ovpn-linkup\n";
	}
	$conf .= "down /usr/local/sbin/ovpn-linkdown\n";
	if (file_exists("/usr/local/sbin/openvpn.attributes.sh")) {
		switch ($settings['mode']) {
			case 'server_user':
			case 'server_tls_user':
				$conf .= "client-connect /usr/local/sbin/openvpn.attributes.sh\n";
				$conf .= "client-disconnect /usr/local/sbin/openvpn.attributes.sh\n";
				break;
		}
	}
	if ($settings['dev_mode'] === 'tun' && isset($config['unbound']['enable']) && isset($config['unbound']['regovpnclients'])) {
		if (($settings['mode'] == 'server_tls') || ($settings['mode'] == 'server_tls_user') ||
		    (($settings['mode'] == 'server_user') && ($settings['username_as_common_name'] == 'enabled'))) {
			$domain = !empty($settings['dns_domain']) ? $settings['dns_domain'] : $config['system']['domain'];
			if (is_domain($domain)) {
				$conf .= "learn-address \"/usr/local/sbin/openvpn.learn-address.sh $domain\"\n";
			}
		}
	}

	/*
	 * When binding specific address, wait cases where interface is in
	 * tentative state otherwise it will fail
	 */
	$wait_tentative = false;

	/* Determine the local IP to use - and make sure it matches with the selected protocol. */
	switch ($settings['protocol']) {
		case 'UDP':
		case 'TCP':
			$conf .= "multihome\n";
			break;
		case 'UDP4':
		case 'TCP4':
			if (is_ipaddrv4($iface_ip)) {
				$conf .= "local {$iface_ip}\n";
			}
			if ($settings['interface'] == "any") {
				$conf .= "multihome\n";
			}
			break;
		case 'UDP6':
		case 'TCP6':
			if (is_ipaddrv6($iface_ipv6)) {
				$conf .= "local {$iface_ipv6}\n";
				$wait_tentative = true;
			}
			if ($settings['interface'] == "any") {
				$conf .= "multihome\n";
			}
			break;
		default:
	}

	if (openvpn_validate_engine($settings['engine']) && ($settings['engine'] != "none")) {
		$conf .= "engine {$settings['engine']}\n";
	}

	// server specific settings
	if ($mode == 'server') {

		list($ip, $cidr) = explode('/', trim($settings['tunnel_network']));
		list($ipv6, $prefix) = explode('/', trim($settings['tunnel_networkv6']));
		$mask = gen_subnet_mask($cidr);

		// configure tls modes
		switch ($settings['mode']) {
			case 'p2p_tls':
			case 'server_tls':
			case 'server_user':
			case 'server_tls_user':
				$conf .= "tls-server\n";
				break;
		}

		// configure p2p/server modes
		switch ($settings['mode']) {
			case 'p2p_tls':
				// If the CIDR is less than a /30, OpenVPN will complain if you try to
				//  use the server directive. It works for a single client without it.
				//  See ticket #1417
				if ((!empty($ip) && !empty($mask)) ||
				    (!empty($ipv6) && !empty($prefix))) {
					$add_ccd = false;
					if (is_ipaddr($ip) && ($cidr < 30)) {
						$add_ccd = true;
						$conf .= "server {$ip} {$mask}\n";
					}
					if (is_ipaddr($ipv6)) {
						$add_ccd = true;
						$conf .= "server-ipv6 {$ipv6}/{$prefix}\n";
					}
					if ($add_ccd) {
						$conf .= "client-config-dir {$g['openvpn_base']}/server{$vpnid}/csc\n";
					}
				}
			case 'p2p_shared_key':
				if (!empty($ip) && !empty($mask)) {
					list($ip1, $ip2) = openvpn_get_interface_ip($ip, $cidr);
					if ($settings['dev_mode'] == 'tun') {
						$conf .= "ifconfig {$ip1} {$ip2}\n";
					} else {
						$conf .= "ifconfig {$ip1} {$mask}\n";
					}
				}
				if (!empty($ipv6) && !empty($prefix)) {
					list($ipv6_1, $ipv6_2) = openvpn_get_interface_ipv6($ipv6, $prefix);
					if ($settings['dev_mode'] == 'tun') {
						$conf .= "ifconfig-ipv6 {$ipv6_1} {$ipv6_2}\n";
					} else {
						$conf .= "ifconfig-ipv6 {$ipv6_1} {$prefix}\n";
					}
				}
				break;
			case 'server_tls':
			case 'server_user':
			case 'server_tls_user':
				if ((!empty($ip) && !empty($mask)) ||
				    (!empty($ipv6) && !empty($prefix))) {
					if (is_ipaddr($ip)) {
						$conf .= "server {$ip} {$mask}\n";
					}
					if (is_ipaddr($ipv6)) {
						$conf .= "server-ipv6 {$ipv6}/{$prefix}\n";
					}
					$conf .= "client-config-dir {$g['openvpn_base']}/server{$vpnid}/csc\n";
				} else {
					if ($settings['serverbridge_dhcp']) {
						if ((!empty($settings['serverbridge_interface'])) && (strcmp($settings['serverbridge_interface'], "none"))) {
							$biface_ip=get_interface_ip($settings['serverbridge_interface']);
							$biface_sm=gen_subnet_mask(get_interface_subnet($settings['serverbridge_interface']));
							if (is_ipaddrv4($biface_ip) && is_ipaddrv4($settings['serverbridge_dhcp_start']) && is_ipaddrv4($settings['serverbridge_dhcp_end'])) {
								$conf .= "server-bridge {$biface_ip} {$biface_sm} {$settings['serverbridge_dhcp_start']} {$settings['serverbridge_dhcp_end']}\n";
								$conf .= "client-config-dir {$g['openvpn_base']}/server{$vpnid}/csc\n";
							} else {
								$conf .= "mode server\n";
							}
							if (!empty($settings['serverbridge_routegateway']) && is_ipaddrv4($biface_ip)) {
								$conf .= "push \"route-gateway {$biface_ip}\"\n";
							}
							/* OpenVPN doesn't support this yet, clients do not recognize the option fully.
							$biface_ip6=get_interface_ipv6($settings['serverbridge_interface']);
							if (!empty($settings['serverbridge_routegateway']) && is_ipaddrv6($biface_ip6)) {
								$conf .= "push \"route-ipv6-gateway {$biface_ip6}\"\n";
							}
							*/
						} else {
							$conf .= "mode server\n";
						}
					}
				}
				break;
		}

		// configure user auth modes
		switch ($settings['mode']) {
			case 'server_user':
				$conf .= "verify-client-cert none\n";
			case 'server_tls_user':
				/* username-as-common-name is not compatible with server-bridge */
				if ((stristr($conf, "server-bridge") === false) &&
				    ($settings['username_as_common_name'] != 'disabled')) {
					$conf .= "username-as-common-name\n";
				}
				if (!empty($settings['authmode'])) {
					$strictusercn = "false";
					if ($settings['strictusercn']) {
						$strictusercn = "true";
					}
					$conf .= "plugin /usr/local/lib/openvpn/plugins/openvpn-plugin-auth-script.so /usr/local/sbin/ovpn_auth_verify_async user " . base64_encode($settings['authmode']) . " {$strictusercn} {$mode_id} {$settings['local_port']}\n";
				}
				break;
		}
		if (!isset($settings['cert_depth']) && (strstr($settings['mode'], 'tls'))) {
			$settings['cert_depth'] = 1;
		}
		if (is_numeric($settings['cert_depth'])) {
			if (($mode == 'client') && empty($settings['certref'])) {
				$cert = "";
			} else {
				$cert = lookup_cert($settings['certref']);
				/* XXX: Seems not used at all! */
				$servercn = urlencode(cert_get_cn($cert['crt']));
				$conf .= "tls-verify \"/usr/local/sbin/ovpn_auth_verify tls '{$servercn}' {$settings['cert_depth']}\"\n";
			}
		}

		// The local port to listen on
		$conf .= "lport {$settings['local_port']}\n";

		// The management port to listen on
		// Use unix socket to overcome the problem on any type of server
		$conf .= "management {$g['openvpn_base']}/{$mode_id}/sock unix\n";
		//$conf .= "management 127.0.0.1 {$settings['local_port']}\n";

		if ($settings['maxclients']) {
			$conf .= "max-clients {$settings['maxclients']}\n";
		}

		// Can we push routes, and should we?
		if ($settings['local_network'] && !$settings['gwredir']) {
			$conf .= openvpn_gen_routes($settings['local_network'], "ipv4", true);
		}
		if ($settings['local_networkv6'] && !$settings['gwredir6']) {
			$conf .= openvpn_gen_routes($settings['local_networkv6'], "ipv6", true);
		}

		switch ($settings['mode']) {
			case 'server_tls':
			case 'server_user':
			case 'server_tls_user':
				// Configure client dhcp options
				openvpn_add_dhcpopts($settings, $conf);
				if ($settings['client2client']) {
					$conf .= "client-to-client\n";
				}
				break;
		}
		if ($settings['mode'] != 'p2p_shared_key' &&
		    isset($settings['duplicate_cn'])) {
			$conf .= "duplicate-cn\n";
		}
	}

	// client specific settings

	if ($mode == 'client') {

		// configure p2p mode
		switch ($settings['mode']) {
			case 'p2p_tls':
				$conf .= "tls-client\n";
			case 'shared_key':
				$conf .= "client\n";
				break;
		}

		// If there is no bind option at all (ip and/or port), add "nobind" directive
		//  Otherwise, use the local port if defined, failing that, use lport 0 to
		//  ensure a random source port.
		if ((empty($iface_ip)) && empty($iface_ipv6) && (!$settings['local_port'])) {
			$conf .= "nobind\n";
		} elseif ($settings['local_port']) {
			$conf .= "lport {$settings['local_port']}\n";
		} else {
			$conf .= "lport 0\n";
		}

		// Use unix socket to overcome the problem on any type of server
		$conf .= "management {$g['openvpn_base']}/{$mode_id}/sock unix\n";

		// The remote server
		$remoteproto = strtolower($settings['protocol']);
		if (substr($remoteproto, 0, 3) == "tcp") {
			$remoteproto .= "-{$mode}";
		}
		$conf .= "remote {$settings['server_addr']} {$settings['server_port']} {$remoteproto}\n";

		if (!empty($settings['use_shaper'])) {
			$conf .= "shaper {$settings['use_shaper']}\n";
		}

		if (!empty($settings['tunnel_network'])) {
			list($ip, $cidr) = explode('/', trim($settings['tunnel_network']));
			$mask = gen_subnet_mask($cidr);
			list($ip1, $ip2) = openvpn_get_interface_ip($ip, $cidr);
			if ($settings['dev_mode'] == 'tun') {
				$conf .= "ifconfig {$ip2} {$ip1}\n";
			} else {
				$conf .= "ifconfig {$ip2} {$mask}\n";
			}
		}

		if (!empty($settings['tunnel_networkv6'])) {
			list($ipv6, $prefix) = explode('/', trim($settings['tunnel_networkv6']));
			list($ipv6_1, $ipv6_2) = openvpn_get_interface_ipv6($ipv6, $prefix);
			if ($settings['dev_mode'] == 'tun') {
				$conf .= "ifconfig-ipv6 {$ipv6_2} {$ipv6_1}\n";
			} else {
				$conf .= "ifconfig-ipv6 {$ipv6_2} {$prefix}\n";
			}
		}

		if (($settings['auth_user'] || $settings['auth_pass']) && $settings['mode'] == "p2p_tls") {
			$up_file = "{$g['openvpn_base']}/{$mode_id}/up";
			$conf .= "auth-user-pass {$up_file}\n";
			if (!$settings['auth-retry-none']) {
				$conf .= "auth-retry nointeract\n";
			}
			if ($settings['auth_user']) {
				$userpass = "{$settings['auth_user']}\n";
			} else {
				$userpass = "";
			}
			if ($settings['auth_pass']) {
				$userpass .= "{$settings['auth_pass']}\n";
			}
			// If only auth_pass is given, then it acts like a user name and we put a blank line where pass would normally go.
			if (!($settings['auth_user'] && $settings['auth_pass'])) {
				$userpass .= "\n";
			}
			file_put_contents($up_file, $userpass);
		}

		if ($settings['proxy_addr']) {
			$conf .= "http-proxy {$settings['proxy_addr']} {$settings['proxy_port']}";
			if ($settings['proxy_authtype'] != "none") {
				$conf .= " {$g['openvpn_base']}/{$mode_id}/proxy_auth {$settings['proxy_authtype']}";
				$proxypas = "{$settings['proxy_user']}\n";
				$proxypas .= "{$settings['proxy_passwd']}\n";
				file_put_contents("{$g['openvpn_base']}/{$mode_id}/proxy_auth", $proxypas);
			}
			$conf .= " \n";
		}
	}

	// Add a remote network route if set, and only for p2p modes.
	if ((substr($settings['mode'], 0, 3) == "p2p") && (openvpn_validate_cidr($settings['remote_network'], "", true, "ipv4") === FALSE)) {
		$conf .= openvpn_gen_routes($settings['remote_network'], "ipv4", false);
	}
	// Add a remote network route if set, and only for p2p modes.
	if ((substr($settings['mode'], 0, 3) == "p2p") && (openvpn_validate_cidr($settings['remote_networkv6'], "", true, "ipv6") === FALSE)) {
		$conf .= openvpn_gen_routes($settings['remote_networkv6'], "ipv6", false);
	}

	// Write the settings for the keys
	switch ($settings['mode']) {
		case 'p2p_shared_key':
			openvpn_add_keyfile($settings['shared_key'], $conf, $mode_id, "secret");
			break;
		case 'p2p_tls':
		case 'server_tls':
		case 'server_tls_user':
		case 'server_user':
			// ca_chain_array() expects parameter to be passed by reference.
			// avoid passing the whole settings array, as param names or
			// types might change in future releases.
			$param = array('caref' => $settings['caref']);
			$cas = ca_chain_array($param);
			$capath = "{$g['openvpn_base']}/{$mode_id}/ca";
			/* Cleanup old CA/CRL references */
			@unlink_if_exists("{$capath}.*0");
			/* Find the CRL listed in the settings */
			if (!empty($settings['crlref'])) {
				$crl = lookup_crl($settings['crlref']);
				crl_update($crl);
			}
			/* For each CA in the chain, setup the CApath */
			foreach ($cas as $ca) {
				ca_setup_capath($ca, $capath, $crl);
			}
			$conf .= "capath {$capath}\n";
			unset($cas, $param);

			if (!empty($settings['certref'])) {
				$cert = lookup_cert($settings['certref']);
				openvpn_add_keyfile($cert['crt'], $conf, $mode_id, "cert");
				openvpn_add_keyfile($cert['prv'], $conf, $mode_id, "key");
			}
			if ($mode == 'server') {
				if (is_numeric($settings['dh_length'])) {
					if (!in_array($settings['dh_length'], array_keys($openvpn_dh_lengths))) {
						/* The user selected a DH parameter length that does not have a corresponding file. */
						log_error(gettext("Failed to construct OpenVPN server configuration. The selected DH Parameter length cannot be used."));
						return;
					}
					$dh_file = "{$g['etc_path']}/dh-parameters.{$settings['dh_length']}";
				} else {
					$dh_file = $settings['dh_length'];
				}
				$conf .= "dh {$dh_file}\n";
				if (!empty($settings['ecdh_curve']) && ($settings['ecdh_curve'] != "none") && openvpn_validate_curve($settings['ecdh_curve'])) {
					$conf .= "ecdh-curve {$settings['ecdh_curve']}\n";
				}
			}
			if ($settings['tls']) {
				if ($settings['tls_type'] == "crypt") {
					$tls_directive = "tls-crypt";
					$tlsopt = "";
				} else {
					$tls_directive = "tls-auth";
					if ($mode == "server") {
						switch($settings['tlsauth_keydir']){
							case '1':
								$tlsopt = 1;
								break;
							case '2':
								$tlsopt = '';
								break;
							default:
								$tlsopt = 0;
								break;
						}
					} else {
						switch($settings['tlsauth_keydir']){
							case '0':
								$tlsopt = 0;
								break;
							case '2':
								$tlsopt = '';
								break;
							default:
								$tlsopt = 1;
								break;
						}
					}
				}
				openvpn_add_keyfile($settings['tls'], $conf, $mode_id, $tls_directive, $tlsopt);
			}
			break;
	}

	/* Data encryption cipher support.
	 * If it is not set, assume enabled since that is OpenVPN's default.
	 * Note that diabling this is now deprecated and will be removed in a future version of OpenVPN */
	if ($settings['ncp_enable'] == "disabled") {
		$conf .= "ncp-disable\n";
		$conf .= "cipher {$fbcipher}\n";
	} else {
		$conf .= "data-ciphers " . str_replace(',', ':', openvpn_build_data_cipher_list($settings['data_ciphers'], $fbcipher)) . "\n";
		$conf .= "data-ciphers-fallback {$fbcipher}\n";
	}

	if (!empty($settings['allow_compression'])) {
		$conf .= "allow-compression {$settings['allow_compression']}\n";
	}

	$compression = "";
	switch ($settings['compression']) {
		case 'none':
			$settings['compression'] = '';
		case 'lz4':
		case 'lz4-v2':
		case 'lzo':
		case 'stub':
		case 'stub-v2':
			$compression .= "compress {$settings['compression']}";
			break;
		case 'noadapt':
			$compression .= "comp-noadapt";
			break;
		case 'adaptive':
		case 'yes':
		case 'no':
			$compression .= "comp-lzo {$settings['compression']}";
			break;
		default:
			/* Add nothing to the configuration */
			break;
	}

	if (($settings['allow_compression'] != 'no') &&
	    !empty($compression)) {
		$conf .= "{$compression}\n";
		if ($settings['compression_push']) {
			$conf .= "push \"{$compression}\"\n";
		}
	}

	if ($settings['passtos']) {
		$conf .= "passtos\n";
	}

	if ($mode == 'client') {
		$conf .= "resolv-retry infinite\n";
	}

	if ($settings['dynamic_ip']) {
		$conf .= "persist-remote-ip\n";
		$conf .= "float\n";
	}

	// If the server is not a TLS server or it has a tunnel network CIDR less than a /30, skip this.
	if (in_array($settings['mode'], $openvpn_tls_server_modes) && (!empty($ip) && !empty($mask) && ($cidr < 30)) && $settings['dev_mode'] != "tap") {
		if (empty($settings['topology'])) {
			$settings['topology'] = "subnet";
		}
		$conf .= "topology {$settings['topology']}\n";
	}

	// New client features
	if ($mode == "client") {
		// Dont pull routes checkbox
		if ($settings['route_no_pull']) {
			$conf .= "route-nopull\n";
		}

		// Dont add/remove routes checkbox
		if ($settings['route_no_exec']) {
			$conf .= "route-noexec\n";
		}
	}

	/* UDP Fast I/O. Only compatible with UDP and also not compatible with OpenVPN's "shaper" directive. */
	if ($settings['udp_fast_io']
	    && (strtolower(substr($settings['protocol'], 0, 3)) == "udp")
	    && (empty($settings['use_shaper']))) {
		$conf .= "fast-io\n";
	}

	/* Exit Notify. Only compatible with UDP. */
	if (!empty($settings['exit_notify']) &&
	    is_numericint($settings['exit_notify']) &&
	    (strtolower(substr($settings['protocol'], 0, 3)) == "udp")) {
		$conf .= "explicit-exit-notify {$settings['exit_notify']}\n";
	}

	/* Send and Receive Buffer Settings */
	if (is_numericint($settings['sndrcvbuf'])
	    && ($settings['sndrcvbuf'] > 0)
	    && ($settings['sndrcvbuf'] <= get_single_sysctl('net.inet.tcp.sendbuf_max'))
	    && ($settings['sndrcvbuf'] <= get_single_sysctl('net.inet.tcp.recvbuf_max'))) {
		$conf .= "sndbuf {$settings['sndrcvbuf']}\n";
		$conf .= "rcvbuf {$settings['sndrcvbuf']}\n";
	}

	openvpn_add_custom($settings, $conf);

	openvpn_create_dirs();
	$fpath = "{$g['openvpn_base']}/{$mode_id}/config.ovpn";
	file_put_contents($fpath, $conf);
	unset($conf);
	$fpath = "{$g['openvpn_base']}/{$mode_id}/interface";
	file_put_contents($fpath, $interface);
	//chown($fpath, 'nobody');
	//chgrp($fpath, 'nobody');
	@chmod("{$g['openvpn_base']}/{$mode_id}/config.ovpn", 0600);
	@chmod("{$g['openvpn_base']}/{$mode_id}/interface", 0600);
	@chmod("{$g['openvpn_base']}/{$mode_id}/key", 0600);
	@chmod("{$g['openvpn_base']}/{$mode_id}/tls-auth", 0600);
	@chmod("{$g['openvpn_base']}/{$mode_id}/conf", 0600);

	if ($wait_tentative) {
		interface_wait_tentative($interface);
	}
}

function openvpn_restart($mode, $settings) {
	global $g, $config;

	$vpnid = $settings['vpnid'];
	$mode_id = $mode.$vpnid;
	$lockhandle = lock("openvpnservice{$mode_id}", LOCK_EX);
	openvpn_reconfigure($mode, $settings);
	/* kill the process if running */
	$pfile = $g['varrun_path']."/openvpn_{$mode_id}.pid";
	if (file_exists($pfile)) {

		/* read the pid file */
		$pid = rtrim(file_get_contents($pfile));
		unlink($pfile);
		syslog(LOG_INFO, "OpenVPN terminate old pid: {$pid}");

		/* send a term signal to the process */
		posix_kill($pid, SIGTERM);

		/* wait until the process exits, or timeout and kill it */
		$i = 0;
		while (posix_kill($pid, 0)) {
			usleep(250000);
			if ($i > 10) {
				log_error(sprintf(gettext('OpenVPN ID %1$s PID %2$s still running, killing.'), $mode_id, $pid));
				posix_kill($pid, SIGKILL);
				usleep(500000);
			}
			$i++;
		}
	}

	if (isset($settings['disable'])) {
		unlock($lockhandle);
		return;
	}

	/* Do not start an instance if we are not CARP master on this vip! */
	if (strstr($settings['interface'], "_vip") && !in_array(get_carp_interface_status($settings['interface']), array("MASTER", ""))) {
		unlock($lockhandle);
		return;
	}

	/* Check if client is bound to a gateway group */
	$a_groups = return_gateway_groups_array(true);
	if (is_array($a_groups[$settings['interface']])) {
		/* the interface is a gateway group. If a vip is defined and its a CARP backup then do not start */
		if (($a_groups[$settings['interface']][0]['vip'] <> "") && (!in_array(get_carp_interface_status($a_groups[$settings['interface']][0]['vip']), array("MASTER", "")))) {
			unlock($lockhandle);
			return;
		}
	}

	/* start the new process */
	$fpath = "{$g['openvpn_base']}/{$mode_id}/config.ovpn";
	openvpn_clear_route($mode, $settings);
	$res = mwexec("/usr/local/sbin/openvpn --config " . escapeshellarg($fpath));
	if ($res == 0) {
		$i = 0;
		$pid = "--";
		while ($i < 3000) {
			if (isvalidpid($pfile)) {
				$pid = rtrim(file_get_contents($pfile));
				break;
			}
			usleep(1000);
			$i++;
		}
		syslog(LOG_INFO, "OpenVPN PID written: {$pid}");
	} else {
		syslog(LOG_ERR, "OpenVPN failed to start");
	}
	if (!platform_booting()) {
		send_event("filter reload");
	}
	unlock($lockhandle);
}

function openvpn_delete($mode, $settings) {
	global $g, $config;

	$vpnid = $settings['vpnid'];
	$mode_id = $mode.$vpnid;

	if ($mode == "server") {
		$devname = "ovpns{$vpnid}";
	} else {
		$devname = "ovpnc{$vpnid}";
	}

	/* kill the process if running */
	$pfile = "{$g['varrun_path']}/openvpn_{$mode_id}.pid";
	if (file_exists($pfile)) {

		/* read the pid file */
		$pid = trim(file_get_contents($pfile));
		unlink($pfile);

		/* send a term signal to the process */
		posix_kill($pid, SIGTERM);
	}

	/* destroy the device */
	pfSense_interface_destroy($devname);

	/* Invalidate cache */
	get_interface_arr(true);

	/* remove the configuration files */
	unlink_if_exists("{$g['openvpn_base']}/{$mode_id}/*/*");
	unlink_if_exists("{$g['openvpn_base']}/{$mode_id}/*");
}

function openvpn_resync_csc(& $settings) {
	global $g, $config, $openvpn_tls_server_modes;
	if (isset($settings['disable'])) {
		openvpn_delete_csc($settings);
		return;
	}
	openvpn_create_dirs();

	if (empty($settings['server_list'])) {
		$csc_server_list = array();
	} else {
		$csc_server_list = explode(",", $settings['server_list']);
	}

	$conf = '';
	if ($settings['block']) {
		$conf .= "disable\n";
	}

	if ($settings['push_reset']) {
		$conf .= "push-reset\n";
	}

	if ($settings['remove_route']) {
		$conf .= "push-remove route\n";
	}

	if ($settings['local_network']) {
		$conf .= openvpn_gen_routes($settings['local_network'], "ipv4", true);
	}
	if ($settings['local_networkv6']) {
		$conf .= openvpn_gen_routes($settings['local_networkv6'], "ipv6", true);
	}

	// Add a remote network iroute if set
	if (openvpn_validate_cidr($settings['remote_network'], "", true, "ipv4") === FALSE) {
		$conf .= openvpn_gen_routes($settings['remote_network'], "ipv4", false, true);
	}
	// Add a remote network iroute if set
	if (openvpn_validate_cidr($settings['remote_networkv6'], "", true, "ipv6") === FALSE) {
		$conf .= openvpn_gen_routes($settings['remote_networkv6'], "ipv6", false, true);
	}

	openvpn_add_dhcpopts($settings, $conf);

	openvpn_add_custom($settings, $conf);
	/* Loop through servers, find which ones can use this CSC */
	if (is_array($config['openvpn']['openvpn-server'])) {
		foreach ($config['openvpn']['openvpn-server'] as $serversettings) {
			if (isset($serversettings['disable'])) {
				continue;
			}
			if (in_array($serversettings['mode'], $openvpn_tls_server_modes)) {
				if ($serversettings['vpnid'] && (empty($csc_server_list) || in_array($serversettings['vpnid'], $csc_server_list))) {
					$csc_path = "{$g['openvpn_base']}/server{$serversettings['vpnid']}/csc/" . basename($settings['common_name']);
					$csc_conf = $conf;

					if (!empty($serversettings['tunnel_network']) && !empty($settings['tunnel_network'])) {
						list($ip, $mask) = explode('/', trim($settings['tunnel_network']));
						if (($serversettings['dev_mode'] == 'tap') || ($serversettings['topology'] == "subnet")) {
							$csc_conf .= "ifconfig-push {$ip} " . gen_subnet_mask($mask) . "\n";
						} else {
							/* Because this is being pushed, the order from the client's point of view. */
							$baselong = gen_subnetv4($ip, $mask);
							$serverip = ip_after($baselong, 1);
							$clientip = ip_after($baselong, 2);
							$csc_conf .= "ifconfig-push {$clientip} {$serverip}\n";
						}
					}

					if (!empty($serversettings['tunnel_networkv6']) && !empty($settings['tunnel_networkv6'])) {
						list($ipv6, $prefix) = explode('/', trim($serversettings['tunnel_networkv6']));
						list($ipv6_1, $ipv6_2) = openvpn_get_interface_ipv6($ipv6, $prefix);
						$csc_conf .= "ifconfig-ipv6-push {$settings['tunnel_networkv6']} {$ipv6_1}\n";
					}

					file_put_contents($csc_path, $csc_conf);
					chown($csc_path, 'nobody');
					chgrp($csc_path, 'nobody');
				}
			}
		}
	}
}

function openvpn_resync_csc_all() {
	global $config;
	if (is_array($config['openvpn']['openvpn-csc'])) {
		foreach ($config['openvpn']['openvpn-csc'] as & $settings) {
			openvpn_resync_csc($settings);
		}
	}
}

function openvpn_delete_csc(& $settings) {
	global $g, $config, $openvpn_tls_server_modes;
	if (empty($settings['server_list'])) {
		$csc_server_list = array();
	} else {
		$csc_server_list = explode(",", $settings['server_list']);
	}

	/* Loop through servers, find which ones used this CSC */
	if (is_array($config['openvpn']['openvpn-server'])) {
		foreach ($config['openvpn']['openvpn-server'] as $serversettings) {
			if (isset($serversettings['disable'])) {
				continue;
			}
			if (in_array($serversettings['mode'], $openvpn_tls_server_modes)) {
				if ($serversettings['vpnid'] && (empty($csc_server_list) || in_array($serversettings['vpnid'], $csc_server_list))) {
					$csc_path = "{$g['openvpn_base']}/server{$serversettings['vpnid']}/csc/" . basename($settings['common_name']);
					unlink_if_exists($csc_path);
				}
			}
		}
	}
}

// Resync the configuration and restart the VPN
function openvpn_resync($mode, $settings) {
	openvpn_restart($mode, $settings);
}

// Resync and restart all VPNs
function openvpn_resync_all($interface = "") {
	global $g, $config;

	openvpn_create_dirs();

	if (!is_array($config['openvpn'])) {
		$config['openvpn'] = array();
	}

	if ($interface <> "") {
		log_error(sprintf(gettext("Resyncing OpenVPN instances for interface %s."), convert_friendly_interface_to_friendly_descr($interface)));
	} else {
		log_error(gettext("Resyncing OpenVPN instances."));
	}

	// Check if OpenVPN clients and servers require a resync.
	foreach (array("server", "client") as $type) {
		if (!is_array($config['openvpn']["openvpn-{$type}"])) {
			continue;
		}
		foreach ($config['openvpn']["openvpn-{$type}"] as & $settings) {
			$fpath = "{$g['openvpn_base']}/{$type}{$settings['vpnid']}/interface";
			$gw_group_change = FALSE;
			$ip_change = ($interface === "") ||
			    ($interface === $settings['interface']);

			if (!$ip_change && file_exists($fpath)) {
				$gw_group_change =
				    (trim(file_get_contents($fpath), " \t\n") !=
				    get_failover_interface(
					$settings['interface']));
			}

			if ($ip_change || $gw_group_change) {
				openvpn_resync($type, $settings);
			}
		}
	}

	openvpn_resync_csc_all();

}

// Resync and restart all VPNs using a gateway group.
function openvpn_resync_gwgroup($gwgroupname = "") {
	global $g, $config;

	if ($gwgroupname <> "") {
		if (is_array($config['openvpn']['openvpn-server'])) {
			foreach ($config['openvpn']['openvpn-server'] as & $settings) {
				if ($gwgroupname == $settings['interface']) {
					log_error(sprintf(gettext('Resyncing OpenVPN for gateway group %1$s server %2$s.'), $gwgroupname, $settings["description"]));
					openvpn_resync('server', $settings);
				}
			}
		}

		if (is_array($config['openvpn']['openvpn-client'])) {
			foreach ($config['openvpn']['openvpn-client'] as & $settings) {
				if ($gwgroupname == $settings['interface']) {
					log_error(sprintf(gettext('Resyncing OpenVPN for gateway group %1$s client %2$s.'), $gwgroupname, $settings["description"]));
					openvpn_resync('client', $settings);
				}
			}
		}

		// Note: no need to resysnc Client Specific (csc) here, as changes to the OpenVPN real interface do not effect these.

	} else {
		log_error(gettext("openvpn_resync_gwgroup called with null gwgroup parameter."));
	}
}

function openvpn_get_active_servers($type="multipoint") {
	global $config, $g;

	$servers = array();
	if (is_array($config['openvpn']['openvpn-server'])) {
		foreach ($config['openvpn']['openvpn-server'] as & $settings) {
			if (empty($settings) || isset($settings['disable'])) {
				continue;
			}

			$prot = $settings['protocol'];
			$port = $settings['local_port'];

			$server = array();
			$server['port'] = ($settings['local_port']) ? $settings['local_port'] : 1194;
			$server['mode'] = $settings['mode'];
			if ($settings['description']) {
				$server['name'] = "{$settings['description']} {$prot}:{$port}";
			} else {
				$server['name'] = "Server {$prot}:{$port}";
			}
			$server['conns'] = array();
			$server['vpnid'] = $settings['vpnid'];
			$server['mgmt'] = "server{$server['vpnid']}";
			$socket = "unix://{$g['openvpn_base']}/{$server['mgmt']}/sock";
			list($tn, $sm) = explode('/', trim($settings['tunnel_network']));

			if ((($server['mode'] == "p2p_shared_key") || ($sm >= 30)) && ($type == "p2p")) {
				$servers[] = openvpn_get_client_status($server, $socket);
			} elseif (($server['mode'] != "p2p_shared_key") && ($type == "multipoint") && ($sm < 30)) {
				$servers[] = openvpn_get_server_status($server, $socket);
			}
		}
	}
	return $servers;
}

function openvpn_get_server_status($server, $socket) {
	$errval = null;
	$errstr = null;
	$fp = @stream_socket_client($socket, $errval, $errstr, 1);
	if ($fp) {
		stream_set_timeout($fp, 1);

		/* send our status request */
		fputs($fp, "status 2\n");

		/* recv all response lines */
		while (!feof($fp)) {

			/* read the next line */
			$line = fgets($fp, 1024);

			$info = stream_get_meta_data($fp);
			if ($info['timed_out']) {
				break;
			}

			/* parse header list line */
			if (strstr($line, "HEADER")) {
				continue;
			}

			/* parse end of output line */
			if (strstr($line, "END") || strstr($line, "ERROR")) {
				break;
			}

			/* parse client list line */
			if (strstr($line, "CLIENT_LIST")) {
				$list = explode(",", $line);
				$conn = array();
				$conn['common_name'] = $list[1];
				$conn['remote_host'] = $list[2];
				$conn['virtual_addr'] = $list[3];
				$conn['virtual_addr6'] = $list[4];
				$conn['bytes_recv'] = $list[5];
				$conn['bytes_sent'] = $list[6];
				$conn['connect_time'] = $list[7];
				$conn['connect_time_unix'] = $list[8];
				$conn['user_name'] = $list[9];
				$conn['client_id'] = $list[10];
				$conn['peer_id'] = $list[11];
				$conn['cipher'] = $list[12];
				$server['conns'][] = $conn;
			}
			/* parse routing table lines */
			if (strstr($line, "ROUTING_TABLE")) {
				$list = explode(",", $line);
				$conn = array();
				$conn['virtual_addr'] = $list[1];
				$conn['common_name'] = $list[2];
				$conn['remote_host'] = $list[3];
				$conn['last_time'] = $list[4];
				$server['routes'][] = $conn;
			}
		}

		/* cleanup */
		fclose($fp);
	} else {
		$conn = array();
		$conn['common_name'] = "[error]";
		$conn['remote_host'] = gettext("Unable to contact daemon");
		$conn['virtual_addr'] = gettext("Service not running?");
		$conn['bytes_recv'] = 0;
		$conn['bytes_sent'] = 0;
		$conn['connect_time'] = 0;
		$server['conns'][] = $conn;
	}
	return $server;
}

function openvpn_get_active_clients() {
	global $config, $g;

	$clients = array();
	if (is_array($config['openvpn']['openvpn-client'])) {
		foreach ($config['openvpn']['openvpn-client'] as & $settings) {

			if (empty($settings) || isset($settings['disable'])) {
				continue;
			}

			$prot = $settings['protocol'];
			$port = ($settings['local_port']) ? ":{$settings['local_port']}" : "";

			$client = array();
			$client['port'] = $settings['local_port'];
			if ($settings['description']) {
				$client['name'] = "{$settings['description']} {$prot}{$port}";
			} else {
				$client['name'] = "Client {$prot}{$port}";
			}

			$client['vpnid'] = $settings['vpnid'];
			$client['mgmt'] = "client{$client['vpnid']}";
			$socket = "unix://{$g['openvpn_base']}/{$client['mgmt']}/sock";
			$client['status']="down";

			$clients[] = openvpn_get_client_status($client, $socket);
		}
	}
	return $clients;
}

function openvpn_get_client_status($client, $socket) {
	$errval = null;
	$errstr = null;
	$fp = @stream_socket_client($socket, $errval, $errstr, 1);
	if ($fp) {
		stream_set_timeout($fp, 1);
		/* send our status request */
		fputs($fp, "state 1\n");

		/* recv all response lines */
		while (!feof($fp)) {
			/* read the next line */
			$line = fgets($fp, 1024);

			$info = stream_get_meta_data($fp);
			if ($info['timed_out']) {
				break;
			}

			/* Get the client state */
			if (strstr($line, "CONNECTED")) {
				$client['status'] = "up";
				$list = explode(",", $line);

				$client['connect_time'] = date("D M j G:i:s Y", $list[0]);
				$client['virtual_addr'] = $list[3];
				$client['remote_host'] = $list[4];
				$client['remote_port'] = $list[5];
				$client['local_host'] = $list[6];
				$client['local_port'] = $list[7];
				$client['virtual_addr6'] = $list[8];
			}
			if (strstr($line, "CONNECTING")) {
				$client['status'] = "connecting";
			}
			if (strstr($line, "ASSIGN_IP")) {
				$client['status'] = "waiting";
				$list = explode(",", $line);

				$client['connect_time'] = date("D M j G:i:s Y", $list[0]);
				$client['virtual_addr'] = $list[3];
			}
			if (strstr($line, "RECONNECTING")) {
				$client['status'] = "reconnecting";
				$list = explode(",", $line);

				$client['connect_time'] = date("D M j G:i:s Y", $list[0]);
				$client['status'] .= "; " . $list[2];
			}
			/* parse end of output line */
			if (strstr($line, "END") || strstr($line, "ERROR")) {
				break;
			}
		}

		/* If up, get read/write stats */
		if (strcmp($client['status'], "up") == 0) {
			fputs($fp, "status 2\n");
			/* recv all response lines */
			while (!feof($fp)) {
				/* read the next line */
				$line = fgets($fp, 1024);

				$info = stream_get_meta_data($fp);
				if ($info['timed_out']) {
					break;
				}

				if (strstr($line, "TCP/UDP read bytes")) {
					$list = explode(",", $line);
					$client['bytes_recv'] = $list[1];
				}

				if (strstr($line, "TCP/UDP write bytes")) {
					$list = explode(",", $line);
					$client['bytes_sent'] = $list[1];
				}

				/* parse end of output line */
				if (strstr($line, "END")) {
					break;
				}
			}
		}

		fclose($fp);

	} else {
		$client['remote_host'] = gettext("Unable to contact daemon");
		$client['virtual_addr'] = gettext("Service not running?");
		$client['bytes_recv'] = 0;
		$client['bytes_sent'] = 0;
		$client['connect_time'] = 0;
	}
	return $client;
}

function openvpn_kill_client($port, $remipp) {
	global $g;

	//$tcpsrv = "tcp://127.0.0.1:{$port}";
	$tcpsrv = "unix://{$g['openvpn_base']}/{$port}/sock";
	$errval = null;
	$errstr = null;

	/* open a tcp connection to the management port of each server */
	$fp = @stream_socket_client($tcpsrv, $errval, $errstr, 1);
	$killed = -1;
	if ($fp) {
		stream_set_timeout($fp, 1);
		fputs($fp, "kill {$remipp}\n");
		while (!feof($fp)) {
			$line = fgets($fp, 1024);

			$info = stream_get_meta_data($fp);
			if ($info['timed_out']) {
				break;
			}

			/* parse header list line */
			if (strpos($line, "INFO:") !== false) {
				continue;
			}
			if (strpos($line, "SUCCESS") !== false) {
				$killed = 0;
			}
			break;
		}
		fclose($fp);
	}
	return $killed;
}

function openvpn_refresh_crls() {
	global $g, $config;

	openvpn_create_dirs();

	if (is_array($config['openvpn']['openvpn-server'])) {
		foreach ($config['openvpn']['openvpn-server'] as $settings) {
			if (empty($settings)) {
				continue;
			}
			if (isset($settings['disable'])) {
				continue;
			}
			// Write the settings for the keys
			switch ($settings['mode']) {
				case 'p2p_tls':
				case 'server_tls':
				case 'server_tls_user':
				case 'server_user':
					$param = array('caref' => $settings['caref']);
					$cas = ca_chain_array($param);
					$capath = "{$g['openvpn_base']}/server{$settings['vpnid']}/ca";
					if (!empty($settings['crlref'])) {
						$crl = lookup_crl($settings['crlref']);
						crl_update($crl);
					}
					foreach ($cas as $ca) {
						ca_setup_capath($ca, $capath, $crl, true);
					}
					unset($cas, $param, $capath, $crl);
					break;
			}
		}
	}
}

function openvpn_create_dirs() {
	global $g, $config, $openvpn_tls_server_modes;
	if (!is_dir($g['openvpn_base'])) {
		safe_mkdir($g['openvpn_base'], 0750);
	}

	init_config_arr(array('openvpn', 'openvpn-server'));
	init_config_arr(array('openvpn', 'openvpn-client'));
	foreach(array('server', 'client') as $mode) {
		foreach ($config['openvpn']["openvpn-{$mode}"] as $settings) {
			$target = "{$g['openvpn_base']}/{$mode}{$settings['vpnid']}";
			@unlink_if_exists($csctarget);
			@safe_mkdir($target);
			if (in_array($settings['mode'], $openvpn_tls_server_modes)) {
				if ($settings['vpnid']) {
					$csctarget = "{$target}/csc/";
					@unlink_if_exists($csctarget);
					@safe_mkdir($csctarget);
				}
			}
		}
	}
}

function openvpn_get_interface_ip($ip, $cidr) {
	$subnet = gen_subnetv4($ip, $cidr);
	if ($cidr == 31) {
		$ip1 = $subnet;
	} else {
		$ip1 = ip_after($subnet);
	}
	$ip2 = ip_after($ip1);
	return array($ip1, $ip2);
}

function openvpn_get_interface_ipv6($ipv6, $prefix) {
	$basev6 = gen_subnetv6($ipv6, $prefix);
	// Is there a better way to do this math?
	$ipv6_arr = explode(':', $basev6);
	$last = hexdec(array_pop($ipv6_arr));
	if ($prefix == 127) {
		$last--;
	}
	$ipv6_1 = text_to_compressed_ip6(implode(':', $ipv6_arr) . ':' . dechex($last + 1));
	$ipv6_2 = text_to_compressed_ip6(implode(':', $ipv6_arr) . ':' . dechex($last + 2));
	return array($ipv6_1, $ipv6_2);
}

function openvpn_clear_route($mode, $settings) {
	if (empty($settings['tunnel_network'])) {
		return;
	}
	list($ip, $cidr) = explode('/', trim($settings['tunnel_network']));
	$mask = gen_subnet_mask($cidr);
	$clear_route = false;

	switch ($settings['mode']) {
		case 'shared_key':
			$clear_route = true;
			break;
		case 'p2p_tls':
		case 'p2p_shared_key':
			if ($cidr >= 30) {
				$clear_route = true;
			}
			break;
	}

	if ($clear_route && !empty($ip) && !empty($mask)) {
		list($ip1, $ip2) = openvpn_get_interface_ip($ip, $cidr);
		$ip_to_clear = ($mode == "server") ? $ip1 : $ip2;
		/* XXX: Family for route? */
		mwexec("/sbin/route -q delete {$ip_to_clear}");
	}
}

function openvpn_gen_routes($value, $ipproto = "ipv4", $push = false, $iroute = false) {
	$routes = "";
	if (empty($value)) {
		return "";
	}
	$networks = explode(',', $value);

	foreach ($networks as $network) {
		if ($ipproto == "ipv4") {
			$route = openvpn_gen_route_ipv4($network, $iroute);
		} else {
			$route = openvpn_gen_route_ipv6($network, $iroute);
		}

		if ($push) {
			$routes .= "push \"{$route}\"\n";
		} else {
			$routes .= "{$route}\n";
		}
	}
	return $routes;
}

function openvpn_gen_route_ipv4($network, $iroute = false) {
	$i = ($iroute) ? "i" : "";
	list($ip, $mask) = explode('/', trim($network));
	$mask = gen_subnet_mask($mask);
	return "{$i}route $ip $mask";
}

function openvpn_gen_route_ipv6($network, $iroute = false) {
	$i = ($iroute) ? "i" : "";
	list($ipv6, $prefix) = explode('/', trim($network));
	if (empty($prefix) && !is_numeric($prefix)) {
		$prefix = "128";
	}
	return "{$i}route-ipv6 ${ipv6}/${prefix}";
}

function openvpn_get_settings($mode, $vpnid) {
	global $config;

	if (is_array($config['openvpn']['openvpn-server'])) {
		foreach ($config['openvpn']['openvpn-server'] as $settings) {
			if (isset($settings['disable'])) {
				continue;
			}

			if ($vpnid != 0 && $vpnid == $settings['vpnid']) {
				return $settings;
			}
		}
	}

	if (is_array($config['openvpn']['openvpn-client'])) {
		foreach ($config['openvpn']['openvpn-client'] as $settings) {
			if (isset($settings['disable'])) {
				continue;
			}

			if ($vpnid != 0 && $vpnid == $settings['vpnid']) {
				return $settings;
			}
		}
	}

	return array();
}

function openvpn_restart_by_vpnid($mode, $vpnid) {
	$settings = openvpn_get_settings($mode, $vpnid);
	openvpn_restart($mode, $settings);
}

/****f* certs/openvpn_is_tunnel_network_in_use
 * NAME
 *   openvpn_is_tunnel_network_in_use
 *     Check if the supplied network is in use as an OpenVPN server or client
 *     tunnel network
 * INPUTS
 *   $net : The IPv4 or IPv6 network to check
 * RESULT
 *   boolean value: true if the network is in use, false otherwise.
 ******/

function openvpn_is_tunnel_network_in_use($net) {
	global $config;
	init_config_arr(array('openvpn', 'openvpn-server'));
	init_config_arr(array('openvpn', 'openvpn-client'));
	/* Determine whether to check IPv4 or IPv6 tunnel networks */
	$tocheck = 'tunnel_network';
	if (is_v6($net)) {
		$tocheck .= "v6";
	}
	/* Check all OpenVPN clients and servers for this tunnel network */
	foreach(array('server', 'client') as $mode) {
		foreach ($config['openvpn']["openvpn-{$mode}"] as $ovpn) {
			if (!empty($ovpn[$tocheck]) &&
			    ($ovpn[$tocheck] == $net)) {
				return true;
			}
		}
	}
	return false;
}

function openvpn_build_data_cipher_list($data_ciphers = 'AES-256-GCM,AES-128-GCM,CHACHA20-POLY1305', $fallback_cipher = 'AES-256-GBC', $ncp_enabled = true) {
	/* If the data_ciphers list is empty, populate it with the fallback cipher. */
	if (empty($data_ciphers) || !$ncp_enabled) {
		$data_ciphers = $fallback_cipher;
	}
	/* Add the fallback cipher to the data ciphers list if it isn't already present */
	if (!in_array($fallback_cipher, explode(',', $data_ciphers))) {
		$data_ciphers .= ',' . $fallback_cipher;
	}
	return $data_ciphers;
}

?>
