<?php
/*
	openvpn.inc part of pfSense

	Copyright (C) 2008 Scott Ullrich <sullrich@gmail.com>
	All rights reserved.

	Copyright (C) 2006  Fernando Lemos
	All rights reserved.

	This file was rewritten from scratch by Fernando Lemos but
	*MIGHT* contain code previously written by:

	Copyright (C) 2005 Peter Allgeyer <allgeyer_AT_web.de>
	All rights reserved.

	Copyright (C) 2004 Peter Curran (peter@closeconsultants.com).
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notices,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notices, this list of conditions and the following disclaimer in the
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

	DISABLE_PHP_LINT_CHECKING

	pfSense_BUILDER_BINARIES:	/usr/local/sbin/openvpn	/usr/bin/openssl	/sbin/ifconfig
	pfSense_MODULE:	openvpn

*/
require_once('config.inc');
require_once("certs.inc");
require_once('pfsense-utils.inc');
require_once("auth.inc");

global $openvpn_prots;
$openvpn_prots = array("UDP", "UDP6", "TCP", "TCP6");

global $openvpn_dev_mode;
$openvpn_dev_mode = array("tun", "tap");

global $openvpn_verbosity_level;
$openvpn_verbosity_level = array(
	0 =>	"none",
	1 =>	"default",
	2 =>	"2",
	3 =>	"3 (recommended)",
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
	1024, 2048, 4096 );

global $openvpn_cert_depths;
$openvpn_cert_depths = array(
	1 => "One (Client+Server)",
	2 => "Two (Client+Intermediate+Server)",
	3 => "Three (Client+2xIntermediate+Server)",
	4 => "Four (Client+3xIntermediate+Server)",
	5 => "Five (Client+4xIntermediate+Server)"
);

global $openvpn_server_modes;
$openvpn_server_modes = array(
	'p2p_tls' => gettext("Peer to Peer ( SSL/TLS )"),
	'p2p_shared_key' => gettext("Peer to Peer ( Shared Key )"),
	'server_tls' => gettext("Remote Access ( SSL/TLS )"),
	'server_user' => gettext("Remote Access ( User Auth )"),
	'server_tls_user' => gettext("Remote Access ( SSL/TLS + User Auth )"));

global $openvpn_client_modes;
$openvpn_client_modes = array(
	'p2p_tls' => gettext("Peer to Peer ( SSL/TLS )"),
	'p2p_shared_key' => gettext("Peer to Peer ( Shared Key )") );

global $openvpn_compression_modes;
$openvpn_compression_modes = array(
	'' =>	gettext("No Preference"),
	'no' =>		gettext("Disabled - No Compression"),
	'adaptive' =>	gettext("Enabled with Adaptive Compression"),
	'yes' =>	gettext("Enabled without Adaptive Compression"));

function openvpn_create_key() {

	$fp = popen("/usr/local/sbin/openvpn --genkey --secret /dev/stdout 2>/dev/null", "r");
	if (!$fp)
		return false;

	$rslt = stream_get_contents($fp);
	pclose($fp);

	return $rslt;
}

function openvpn_create_dhparams($bits) {

	$fp = popen("/usr/bin/openssl dhparam {$bits} 2>/dev/null", "r");
	if (!$fp)
		return false;

	$rslt = stream_get_contents($fp);
	pclose($fp);

	return $rslt;
}

function openvpn_vpnid_used($vpnid) {
	global $config;

	if (is_array($config['openvpn']['openvpn-server']))
		foreach ($config['openvpn']['openvpn-server'] as & $settings)
			if ($vpnid == $settings['vpnid'])
				return true;

	if (is_array($config['openvpn']['openvpn-client']))
		foreach ($config['openvpn']['openvpn-client'] as & $settings)
			if ($vpnid == $settings['vpnid'])
				return true;

	return false;
}

function openvpn_vpnid_next() {

	$vpnid = 1;
	while(openvpn_vpnid_used($vpnid))
		$vpnid++;

	return $vpnid;
}

function openvpn_port_used($prot, $interface, $port, $curvpnid = 0) {
	global $config;

	if (is_array($config['openvpn']['openvpn-server'])) {
		foreach ($config['openvpn']['openvpn-server'] as & $settings) {
			if (isset($settings['disable']))
				continue;

			if ($curvpnid != 0 && $curvpnid == $settings['vpnid'])
				continue;

			if ($port == $settings['local_port'] && $prot == $settings['protocol'] &&
				($interface == $settings['interface'] || $interface == "any" || $settings['interface'] == "any"))
				return $settings['vpnid'];
		}
	}

	if (is_array($config['openvpn']['openvpn-client'])) {
		foreach ($config['openvpn']['openvpn-client'] as & $settings) {
			if (isset($settings['disable']))
				continue;

			if ($curvpnid != 0 && $curvpnid == $settings['vpnid'])
				continue;

			if ($port == $settings['local_port'] && $prot == $settings['protocol'] &&
				($interface == $settings['interface'] || $interface == "any" || $settings['interface'] == "any"))
				return $settings['vpnid'];
		}
	}

	return 0;
}

function openvpn_port_next($prot, $interface = "wan") {

	$port = 1194;
	while(openvpn_port_used($prot, $interface, $port))
		$port++;
	while(openvpn_port_used($prot, "any", $port))
		$port++;

	return $port;
}

function openvpn_get_cipherlist() {

	$ciphers = array();
	$cipher_out = shell_exec('/usr/local/sbin/openvpn --show-ciphers | /usr/bin/grep "default key" | /usr/bin/awk \'{print $1, "(" $2 "-" $3 ")";}\'');
	$cipher_lines = explode("\n", trim($cipher_out));
	sort($cipher_lines);
	foreach ($cipher_lines as $line) {
		$words = explode(' ', $line);
		$ciphers[$words[0]] = "{$words[0]} {$words[1]}";
	}
	$ciphers["none"] = gettext("None (No Encryption)");
	return $ciphers;
}

function openvpn_get_digestlist() {

	$digests = array();
	$digest_out = shell_exec('/usr/local/sbin/openvpn --show-digests | /usr/bin/grep "digest size" | /usr/bin/awk \'{print $1, "(" $2 "-" $3 ")";}\'');
	$digest_lines = explode("\n", trim($digest_out));
	sort($digest_lines);
	foreach ($digest_lines as $line) {
		$words = explode(' ', $line);
		$digests[$words[0]] = "{$words[0]} {$words[1]}";
	}
	$digests["none"] = gettext("None (No Authentication)");
	return $digests;
}

function openvpn_get_engines() {
	$openssl_engines = array('none' => 'No Hardware Crypto Acceleration');
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
			if (strpos($dt, "unavailable") !== FALSE)
				$keep = false;
			if (strpos($dt, "available") !== FALSE)
				continue;
			if (strpos($dt, "[") !== FALSE)
				$ciphers = trim($dt, "[]");
		}
		if (!empty($ciphers))
			$ciphers = " - " . $ciphers;
		if (strlen($ciphers) > 60)
			$ciphers = substr($ciphers, 0, 60) . " ... ";
		if ($keep)
			$openssl_engines[$linematch[1]] = $linematch[2] . $ciphers;
	}
	return $openssl_engines;
}

function openvpn_validate_engine($engine) {
	$engines = openvpn_get_engines();
	return array_key_exists($engine, $engines);
}

function openvpn_validate_host($value, $name) {
	$value = trim($value);
	if (empty($value) || (!is_domain($value) && !is_ipaddr($value)))
		return sprintf(gettext("The field '%s' must contain a valid IP address or domain name."), $name);
	return false;
}

function openvpn_validate_port($value, $name) {
	$value = trim($value);
	if (empty($value) || !is_numeric($value) || $value < 0 || ($value > 65535))
		return sprintf(gettext("The field '%s' must contain a valid port, ranging from 0 to 65535."), $name);
	return false;
}

function openvpn_validate_cidr($value, $name, $multiple = false, $ipproto = "ipv4") {
	$value = trim($value);
	$error = false;
	if (empty($value))
		return false;
	$networks = explode(',', $value);

	if (!$multiple && (count($networks) > 1))
		return sprintf(gettext("The field '%s' must contain a single valid %s CIDR range."), $name, $ipproto);

	foreach ($networks as $network) {
		if ($ipproto == "ipv4")
			$error = !openvpn_validate_cidr_ipv4($network);
		else
			$error = !openvpn_validate_cidr_ipv6($network);
		if ($error)
			break;
	}

	if ($error)
		return sprintf(gettext("The field '%s' must contain only valid %s CIDR range(s) separated by commas."), $name, $ipproto);
	else
		return false;
}

function openvpn_validate_cidr_ipv4($value) {
	$value = trim($value);
	if (!empty($value)) {
		list($ip, $mask) = explode('/', $value);
		if (!is_ipaddrv4($ip) or !is_numeric($mask) or ($mask > 32) or ($mask < 0))
			return false;
	}
	return true;
}

function openvpn_validate_cidr_ipv6($value) {
	$value = trim($value);
	if (!empty($value)) {
		list($ipv6, $prefix) = explode('/', $value);
		if (empty($prefix))
			$prefix = "128";
		if (!is_ipaddrv6($ipv6) or !is_numeric($prefix) or ($prefix > 128) or ($prefix < 0))
			return false;
	}
	return true;
}

function openvpn_add_dhcpopts(& $settings, & $conf) {

	if (!empty($settings['dns_domain']))
		$conf .= "push \"dhcp-option DOMAIN {$settings['dns_domain']}\"\n";

	if (!empty($settings['dns_server1']))
		$conf .= "push \"dhcp-option DNS {$settings['dns_server1']}\"\n";
	if (!empty($settings['dns_server2']))
		$conf .= "push \"dhcp-option DNS {$settings['dns_server2']}\"\n";
	if (!empty($settings['dns_server3']))
		$conf .= "push \"dhcp-option DNS {$settings['dns_server3']}\"\n";
	if (!empty($settings['dns_server4']))
		$conf .= "push \"dhcp-option DNS {$settings['dns_server4']}\"\n";

	if (!empty($settings['push_register_dns']))
		$conf .= "push \"register-dns\"\n";

	if (!empty($settings['ntp_server1']))
		$conf .= "push \"dhcp-option NTP {$settings['ntp_server1']}\"\n";
	if (!empty($settings['ntp_server2']))
		$conf .= "push \"dhcp-option NTP {$settings['ntp_server2']}\"\n";

	if ($settings['netbios_enable']) {

		if (!empty($settings['dhcp_nbttype']) && ($settings['dhcp_nbttype'] != 0))
			$conf .= "push \"dhcp-option NBT {$settings['dhcp_nbttype']}\"\n";
		if (!empty($settings['dhcp_nbtscope']))
			$conf .= "push \"dhcp-option NBS {$settings['dhcp_nbtscope']}\"\n";

		if (!empty($settings['wins_server1']))
			$conf .= "push \"dhcp-option WINS {$settings['wins_server1']}\"\n";
		if (!empty($settings['wins_server2']))
			$conf .= "push \"dhcp-option WINS {$settings['wins_server2']}\"\n";

		if (!empty($settings['nbdd_server1']))
			$conf .= "push \"dhcp-option NBDD {$settings['nbdd_server1']}\"\n";
	}

	if ($settings['gwredir'])
		$conf .= "push \"redirect-gateway def1\"\n";
}

function openvpn_add_custom(& $settings, & $conf) {

	if ($settings['custom_options']) {

		$options = explode(';', $settings['custom_options']);

		if (is_array($options)) {
			foreach ($options as $option)
				$conf .= "$option\n";
		} else
			$conf .= "{$settings['custom_options']}\n";
	}
}

function openvpn_add_keyfile(& $data, & $conf, $mode_id, $directive, $opt = "") {
	global $g;

	$fpath = $g['varetc_path']."/openvpn/{$mode_id}.{$directive}";
	openvpn_create_dirs();
	file_put_contents($fpath, base64_decode($data));
	//chown($fpath, 'nobody');
	//chgrp($fpath, 'nobody');
	@chmod($fpath, 0600);

	$conf .= "{$directive} {$fpath} {$opt}\n";
}

function openvpn_reconfigure($mode, $settings) {
	global $g, $config;

	if (empty($settings))
		return;
	if (isset($settings['disable']))
		return;
	openvpn_create_dirs();
	/*
	 * NOTE: Deleting tap devices causes spontaneous reboots. Instead,
	 * we use a vpnid number which is allocated for a particular client
	 * or server configuration. ( see openvpn_vpnid_next() )
	 */

	$vpnid = $settings['vpnid'];
	$mode_id = $mode.$vpnid;

	if (isset($settings['dev_mode']))
		$tunname = "{$settings['dev_mode']}{$vpnid}";
	else {	/* defaults to tun */
		$tunname = "tun{$vpnid}";
		$settings['dev_mode'] = "tun";
	}

	if ($mode == "server")
		$devname = "ovpns{$vpnid}";
	else
		$devname = "ovpnc{$vpnid}";

	/* is our device already configured */
	if (!does_interface_exist($devname)) {

		/* create the tap device if required */
		if (!file_exists("/dev/{$tunname}"))
			exec("/sbin/ifconfig " . escapeshellarg($tunname) . " create");

		/* rename the device */
		mwexec("/sbin/ifconfig " . escapeshellarg($tunname) . " name " . escapeshellarg($devname));

		/* add the device to the openvpn group */
		mwexec("/sbin/ifconfig " . escapeshellarg($devname) . " group openvpn");
	}

	$pfile = $g['varrun_path'] . "/openvpn_{$mode_id}.pid";
	$proto = strtolower($settings['protocol']);
	if (substr($settings['protocol'], 0, 3) == "TCP")
			$proto = "{$proto}-{$mode}";
	$dev_mode = $settings['dev_mode'];
	$cipher = $settings['crypto'];
	// OpenVPN defaults to SHA1, so use it when unset to maintain compatibility.
	$digest = !empty($settings['digest']) ? $settings['digest'] : "SHA1";

	$interface = get_failover_interface($settings['interface']);
	$ipaddr = $settings['ipaddr'];
	$ipaddrv6 = $settings['ipaddrv6'];

	// If a specific ip address (VIP) is requested, use it.
	// Otherwise, if a specific interface is requested, use it
	// If "any" interface was selected, local directive will be ommited.
	if (is_ipaddrv4($ipaddr)) {
		$iface_ip=$ipaddr;
	} else {
		if ((!empty($interface)) && (strcmp($interface, "any"))) {
			$iface_ip=get_interface_ip($interface);
		}
	}
	if (is_ipaddrv6($ipaddrv6)) {
		$iface_ipv6=$ipaddrv6;
	} else {
		if ((!empty($interface)) && (strcmp($interface, "any"))) {
			$iface_ipv6=get_interface_ipv6($interface);
		}
	}


	$conf = "dev {$devname}\n";
	if (isset($settings['verbosity_level'])) {
		$conf .= "verb {$settings['verbosity_level']}\n";
	}

	$conf .= "dev-type {$settings['dev_mode']}\n";
	switch($settings['dev_mode']) {
		case "tun":
			if (!$settings['no_tun_ipv6']) {
				$conf .= "tun-ipv6\n";
			}
			break;
	}
	$conf .= "dev-node /dev/{$tunname}\n";
	$conf .= "writepid {$pfile}\n";
	$conf .= "#user nobody\n";
	$conf .= "#group nobody\n";
	$conf .= "script-security 3\n";
	$conf .= "daemon\n";
	$conf .= "keepalive 10 60\n";
	$conf .= "ping-timer-rem\n";
	$conf .= "persist-tun\n";
	$conf .= "persist-key\n";
	$conf .= "proto {$proto}\n";
	$conf .= "cipher {$cipher}\n";
	$conf .= "auth {$digest}\n";
	$conf .= "up /usr/local/sbin/ovpn-linkup\n";
	$conf .= "down /usr/local/sbin/ovpn-linkdown\n";
	if (file_exists("/usr/local/sbin/openvpn.attributes.sh")) {
		switch($settings['mode']) {
			case 'server_user':
			case 'server_tls_user':
				$conf .= "client-connect /usr/local/sbin/openvpn.attributes.sh\n";
				$conf .= "client-disconnect /usr/local/sbin/openvpn.attributes.sh\n";
				break;
		}
	}

	/* Determine the local IP to use - and make sure it matches with the selected protocol. */
	if (is_ipaddrv4($iface_ip) && (stristr($settings['protocol'], "6") === false)) {
		$conf .= "local {$iface_ip}\n";
	} elseif (is_ipaddrv6($iface_ipv6) && (stristr($settings['protocol'], "6") !== false)) {
		$conf .= "local {$iface_ipv6}\n";
	}

	if (openvpn_validate_engine($settings['engine']) && ($settings['engine'] != "none"))
		$conf .= "engine {$settings['engine']}\n";

	// server specific settings
	if ($mode == 'server') {

		list($ip, $cidr) = explode('/', $settings['tunnel_network']);
		list($ipv6, $prefix) = explode('/', $settings['tunnel_networkv6']);
		$mask = gen_subnet_mask($cidr);

		// configure tls modes
		switch($settings['mode']) {
			case 'p2p_tls':
			case 'server_tls':
			case 'server_user':
			case 'server_tls_user':
				$conf .= "tls-server\n";
				break;
		}

		// configure p2p/server modes
		switch($settings['mode']) {
			case 'p2p_tls':
				// If the CIDR is less than a /30, OpenVPN will complain if you try to
				//  use the server directive. It works for a single client without it.
				//  See ticket #1417
				if (!empty($ip) && !empty($mask) && ($cidr < 30)) {
					$conf .= "server {$ip} {$mask}\n";
					$conf .= "client-config-dir {$g['varetc_path']}/openvpn-csc\n";
					if(is_ipaddr($ipv6))
						$conf .= "server-ipv6 {$ipv6}/{$prefix}\n";
				}
			case 'p2p_shared_key':
				if (!empty($ip) && !empty($mask)) {
					list($ip1, $ip2) = openvpn_get_interface_ip($ip, $mask);
					if ($settings['dev_mode'] == 'tun')
						$conf .= "ifconfig {$ip1} {$ip2}\n";
					else
						$conf .= "ifconfig {$ip1} {$mask}\n";
				}
				if (!empty($ipv6) && !empty($prefix)) {
					list($ipv6_1, $ipv6_2) = openvpn_get_interface_ipv6($ipv6, $prefix);
					if ($settings['dev_mode'] == 'tun')
						$conf .= "ifconfig-ipv6 {$ipv6_1} {$ipv6_2}\n";
					else
						$conf .= "ifconfig-ipv6 {$ipv6_1} {$prefix}\n";
				}
				break;
			case 'server_tls':
			case 'server_user':
			case 'server_tls_user':
				if (!empty($ip) && !empty($mask)) {
					$conf .= "server {$ip} {$mask}\n";
					if(is_ipaddr($ipv6))
						$conf .= "server-ipv6 {$ipv6}/{$prefix}\n";
					$conf .= "client-config-dir {$g['varetc_path']}/openvpn-csc\n";
				} else {
					if ($settings['serverbridge_dhcp']) {
						if ((!empty($settings['serverbridge_interface'])) && (strcmp($settings['serverbridge_interface'], "none"))) {
							$biface_ip=get_interface_ip($settings['serverbridge_interface']);
							$biface_sm=gen_subnet_mask(get_interface_subnet($settings['serverbridge_interface']));
							if (is_ipaddrv4($biface_ip) && is_ipaddrv4($settings['serverbridge_dhcp_start']) && is_ipaddrv4($settings['serverbridge_dhcp_end'])) {
								$conf .= "server-bridge {$biface_ip} {$biface_sm} {$settings['serverbridge_dhcp_start']} {$settings['serverbridge_dhcp_end']}\n";
								$conf .= "client-config-dir {$g['varetc_path']}/openvpn-csc\n";
							} else {
								$conf .= "mode server\n";
							}
						} else {
							$conf .= "mode server\n";
						}
					}
				}
				break;
		}

		// configure user auth modes
		switch($settings['mode']) {
			case 'server_user':
				$conf .= "client-cert-not-required\n";
			case 'server_tls_user':
				/* username-as-common-name is not compatible with server-bridge */
				if (stristr($conf, "server-bridge") === false)
					$conf .= "username-as-common-name\n";
				if (!empty($settings['authmode'])) {
					$strictusercn = "false";
					if ($settings['strictusercn'])
						$strictusercn = "true";
					$conf .= "auth-user-pass-verify \"/usr/local/sbin/ovpn_auth_verify user '{$settings['authmode']}' {$strictusercn} {$mode_id}\" via-env\n";
				}
				break;
		}
		if (!isset($settings['cert_depth']) && (strstr($settings['mode'], 'tls')))
			$settings['cert_depth'] = 1;
		if (is_numeric($settings['cert_depth'])) {
			if (($mode == 'client') && empty($settings['certref']))
				$cert = "";
			else {
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
		$conf .= "management {$g['varetc_path']}/openvpn/{$mode_id}.sock unix\n";
		//$conf .= "management 127.0.0.1 {$settings['local_port']}\n";

		if ($settings['maxclients'])
			$conf .= "max-clients {$settings['maxclients']}\n";

		// Can we push routes
		if ($settings['local_network']) {
			$conf .= openvpn_gen_routes($settings['local_network'], "ipv4", true);
		}
		if ($settings['local_networkv6']) {
			$conf .= openvpn_gen_routes($settings['local_networkv6'], "ipv6", true);
		}

		switch($settings['mode']) {
			case 'server_tls':
			case 'server_user':
			case 'server_tls_user':
				// Configure client dhcp options
				openvpn_add_dhcpopts($settings, $conf);
				if ($settings['client2client'])
					$conf .= "client-to-client\n";
				break;
		}
		if (isset($settings['duplicate_cn']))
			$conf .= "duplicate-cn\n";
	}

	// client specific settings

	if ($mode == 'client') {

		// configure p2p mode
		switch($settings['mode']) {
			case 'p2p_tls':
				$conf .= "tls-client\n";
			case 'shared_key':
				$conf .= "client\n";
				break;
		}

		// If there is no bind option at all (ip and/or port), add "nobind" directive
		//  Otherwise, use the local port if defined, failing that, use lport 0 to
		//  ensure a random source port.
		if ((empty($iface_ip)) && (!$settings['local_port']))
			$conf .= "nobind\n";
		elseif ($settings['local_port'])
			$conf .= "lport {$settings['local_port']}\n";
		else
			$conf .= "lport 0\n";

		// Use unix socket to overcome the problem on any type of server
		$conf .= "management {$g['varetc_path']}/openvpn/{$mode_id}.sock unix\n";

		// The remote server
		$conf .= "remote {$settings['server_addr']} {$settings['server_port']}\n";

		if (!empty($settings['use_shaper']))
			$conf .= "shaper {$settings['use_shaper']}\n";

		if (!empty($settings['tunnel_network'])) {
			list($ip, $mask) = explode('/', $settings['tunnel_network']);
			$mask = gen_subnet_mask($mask);
			list($ip1, $ip2) = openvpn_get_interface_ip($ip, $mask);
			if ($settings['dev_mode'] == 'tun')
				$conf .= "ifconfig {$ip2} {$ip1}\n";
			else
				$conf .= "ifconfig {$ip2} {$mask}\n";
		}

		if (!empty($settings['tunnel_networkv6'])) {
			list($ipv6, $prefix) = explode('/', $settings['tunnel_networkv6']);
			list($ipv6_1, $ipv6_2) = openvpn_get_interface_ipv6($ipv6, $prefix);
			if ($settings['dev_mode'] == 'tun')
				$conf .= "ifconfig-ipv6 {$ipv6_2} {$ipv6_1}\n";
			else
				$conf .= "ifconfig-ipv6 {$ipv6_2} {$prefix}\n";
		}

		if ($settings['auth_user'] && $settings['auth_pass']) {
			$up_file = "{$g['varetc_path']}/openvpn/{$mode_id}.up";
			$conf .= "auth-user-pass {$up_file}\n";
			$userpass = "{$settings['auth_user']}\n";
			$userpass .= "{$settings['auth_pass']}\n";
			file_put_contents($up_file, $userpass);
		}

		if ($settings['proxy_addr']) {
			$conf .= "http-proxy {$settings['proxy_addr']} {$settings['proxy_port']}";
			if ($settings['proxy_authtype'] != "none") {
				$conf .= " {$g['varetc_path']}/openvpn/{$mode_id}.pas {$settings['proxy_authtype']}";
				$proxypas = "{$settings['proxy_user']}\n";
				$proxypas .= "{$settings['proxy_passwd']}\n";
				file_put_contents("{$g['varetc_path']}/openvpn/{$mode_id}.pas", $proxypas);
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
	switch($settings['mode']) {
		case 'p2p_shared_key':
			openvpn_add_keyfile($settings['shared_key'], $conf, $mode_id, "secret");
			break;
		case 'p2p_tls':
		case 'server_tls':
		case 'server_tls_user':
		case 'server_user':
			$ca = lookup_ca($settings['caref']);
			openvpn_add_keyfile($ca['crt'], $conf, $mode_id, "ca");

			if (!empty($settings['certref'])) {
				$cert = lookup_cert($settings['certref']);
				openvpn_add_keyfile($cert['crt'], $conf, $mode_id, "cert");
				openvpn_add_keyfile($cert['prv'], $conf, $mode_id, "key");
			}
			if ($mode == 'server')
				$conf .= "dh {$g['etc_path']}/dh-parameters.{$settings['dh_length']}\n";
			if (!empty($settings['crlref'])) {
				$crl = lookup_crl($settings['crlref']);
				crl_update($crl);
				openvpn_add_keyfile($crl['text'], $conf, $mode_id, "crl-verify");
			}
			if ($settings['tls']) {
				if ($mode == "server")
					$tlsopt = 0;
				else
					$tlsopt = 1;
				openvpn_add_keyfile($settings['tls'], $conf, $mode_id, "tls-auth", $tlsopt);
			}
			break;
	}

	if (!empty($settings['compression']))
		$conf .= "comp-lzo {$settings['compression']}\n";

	if ($settings['passtos'])
		$conf .= "passtos\n";

	if ($settings['resolve_retry'])
		$conf .= "resolv-retry infinite\n";

	if ($settings['dynamic_ip']) {
		$conf .= "persist-remote-ip\n";
		$conf .= "float\n";
	}

	if ($settings['topology_subnet']) {
		$conf .= "topology subnet\n";
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

	openvpn_add_custom($settings, $conf);

	openvpn_create_dirs();
	$fpath = "{$g['varetc_path']}/openvpn/{$mode_id}.conf";
	file_put_contents($fpath, $conf);
	unset($conf);
	$fpath = "{$g['varetc_path']}/openvpn/{$mode_id}.interface";
	file_put_contents($fpath, $interface);
	//chown($fpath, 'nobody');
	//chgrp($fpath, 'nobody');
	@chmod("{$g['varetc_path']}/openvpn/{$mode_id}.conf", 0600);
	@chmod("{$g['varetc_path']}/openvpn/{$mode_id}.interface", 0600);
	@chmod("{$g['varetc_path']}/openvpn/{$mode_id}.key", 0600);
	@chmod("{$g['varetc_path']}/openvpn/{$mode_id}.tls-auth", 0600);
	@chmod("{$g['varetc_path']}/openvpn/{$mode_id}.conf", 0600);
}

function openvpn_restart($mode, $settings) {
	global $g, $config;

	$vpnid = $settings['vpnid'];
	$mode_id = $mode.$vpnid;

	/* kill the process if running */
	$pfile = $g['varrun_path']."/openvpn_{$mode_id}.pid";
	if (file_exists($pfile)) {

		/* read the pid file */
		$pid = rtrim(file_get_contents($pfile));
		unlink($pfile);

		/* send a term signal to the process */
		posix_kill($pid, SIGTERM);

		/* wait until the process exits */
		while(posix_kill($pid, 0))
			usleep(250000);
	}

	if (isset($settings['disable']))
		return;

	/* Do not start a client if we are a CARP backup on this vip! */
	if (($mode == "client") && (strstr($settings['interface'], "_vip") && get_carp_interface_status($settings['interface']) == "BACKUP"))
		return;

	/* Check if client is bound to a gateway group */
	$a_groups = return_gateway_groups_array();
	if (is_array($a_groups[$settings['interface']])) {
	/* the interface is a gateway group. If a vip is defined and its a CARP backup then do not start */
		if (($a_groups[$settings['interface']][0]['vip'] <> "") && (get_carp_interface_status($a_groups[$settings['interface']][0]['vip']) == "BACKUP"))
			return;
	}

	/* start the new process */
	$fpath = $g['varetc_path']."/openvpn/{$mode_id}.conf";
	openvpn_clear_route($mode, $settings);
	mwexec_bg("/usr/local/sbin/openvpn --config " . escapeshellarg($fpath));

	if (!$g['booting'])
		send_event("filter reload");
}

function openvpn_delete($mode, & $settings) {
	global $g, $config;

	$vpnid = $settings['vpnid'];
	$mode_id = $mode.$vpnid;

	if (isset($settings['dev_mode']))
		$tunname = "{$settings['dev_mode']}{$vpnid}";
	else {  /* defaults to tun */
		$tunname = "tun{$vpnid}";
	}

	if ($mode == "server")
		$devname = "ovpns{$vpnid}";
	else
		$devname = "ovpnc{$vpnid}";

	/* kill the process if running */
	$pfile = "{$g['varrun_path']}/openvpn_{$mode_id}.pid";
	if (file_exists($pfile)) {

		/* read the pid file */
		$pid = trim(file_get_contents($pfile));
		unlink($pfile);

		/* send a term signal to the process */
		posix_kill($pid, SIGTERM);
	}

	/* remove the device from the openvpn group */
	mwexec("/sbin/ifconfig " . escapeshellarg($devname) . " -group openvpn");

	/* restore the original adapter name */
	mwexec("/sbin/ifconfig " . escapeshellarg($devname) . " name " . escapeshellarg($tunname));

	/* remove the configuration files */
	@array_map('unlink', glob("{$g['varetc_path']}/openvpn/{$mode_id}.*"));
}

function openvpn_cleanup_csc($common_name) {
	global $g, $config;
	if (empty($common_name))
		return;
	$fpath = "{$g['varetc_path']}/openvpn-csc/" . basename($common_name);
	if (is_file($fpath))
		unlink_if_exists($fpath);
	return;
}

function openvpn_resync_csc(& $settings) {
	global $g, $config;

	$fpath = $g['varetc_path']."/openvpn-csc/".$settings['common_name'];

	if (isset($settings['disable'])) {
		unlink_if_exists($fpath);
		return;
	}
	openvpn_create_dirs();

	$conf = '';
	if ($settings['block'])
		$conf .= "disable\n";

	if ($settings['push_reset'])
		$conf .= "push-reset\n";

	if (!empty($settings['tunnel_network'])) {
		list($ip, $mask) = explode('/', $settings['tunnel_network']);
		$baselong = ip2long32($ip) & gen_subnet_mask_long($mask);
		$serverip = long2ip32($baselong + 1);
		$clientip = long2ip32($baselong + 2);
		/* Because this is being pushed, the order from the client's point of view. */
		if ($settings['dev_mode'] != 'tap')
			$conf .= "ifconfig-push {$clientip} {$serverip}\n";
		else
			$conf .= "ifconfig-push {$clientip} {$mask}\n";
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

	if ($settings['gwredir'])
		$conf .= "push \"redirect-gateway def1\"\n";

	openvpn_add_custom($settings, $conf);

	file_put_contents($fpath, $conf);
	chown($fpath, 'nobody');
	chgrp($fpath, 'nobody');
}

function openvpn_delete_csc(& $settings) {
	global $g, $config;

	$fpath = $g['varetc_path']."/openvpn-csc/".$settings['common_name'];
	unlink_if_exists($fpath);
}

// Resync the configuration and restart the VPN
function openvpn_resync($mode, $settings) {
	openvpn_reconfigure($mode, $settings);
	openvpn_restart($mode, $settings);
}

// Resync and restart all VPNs
function openvpn_resync_all($interface = "") {
	global $g, $config;

	if ($g['platform'] == 'jail')
		return;
	openvpn_create_dirs();

	if (!is_array($config['openvpn']))
		$config['openvpn'] = array();

/*
	if (!$config['openvpn']['dh-parameters']) {
		echo "Configuring OpenVPN Parameters ...\n";
		$dh_parameters = openvpn_create_dhparams(1024);
		$dh_parameters = base64_encode($dh_parameters);
		$config['openvpn']['dh-parameters'] = $dh_parameters;
		write_config("OpenVPN DH parameters");
	}

	$path_ovdh = $g['varetc_path']."/openvpn/dh-parameters";
	if (!file_exists($path_ovdh)) {
		$dh_parameters = $config['openvpn']['dh-parameters'];
		$dh_parameters = base64_decode($dh_parameters);
		file_put_contents($path_ovdh, $dh_parameters);
	}
*/
	if ($interface <> "")
		log_error("Resyncing OpenVPN instances for interface " . convert_friendly_interface_to_friendly_descr($interface) . ".");
	else
		log_error("Resyncing OpenVPN instances.");

	if (is_array($config['openvpn']['openvpn-server'])) {
		foreach ($config['openvpn']['openvpn-server'] as & $settings) {
			if ($interface <> "" && $interface != $settings['interface'])
				continue;
			openvpn_resync('server', $settings);
		}
	}

	if (is_array($config['openvpn']['openvpn-client'])) {
		foreach ($config['openvpn']['openvpn-client'] as & $settings) {
			if ($interface <> "" && $interface != $settings['interface'])
				continue;
			openvpn_resync('client', $settings);
		}
	}

	if (is_array($config['openvpn']['openvpn-csc']))
		foreach ($config['openvpn']['openvpn-csc'] as & $settings)
			openvpn_resync_csc($settings);

}

// Resync and restart all VPNs using a gateway group.
function openvpn_resync_gwgroup($gwgroupname = "") {
	global $g, $config;

	if ($gwgroupname <> "") {
		if (is_array($config['openvpn']['openvpn-server'])) {
			foreach ($config['openvpn']['openvpn-server'] as & $settings) {
				if ($gwgroupname == $settings['interface']) {
					log_error("Resyncing OpenVPN for gateway group " . $gwgroupname . " server " . $settings["description"] . ".");
					openvpn_resync('server', $settings);
				}
			}
		}

		if (is_array($config['openvpn']['openvpn-client'])) {
			foreach ($config['openvpn']['openvpn-client'] as & $settings) {
				if ($gwgroupname == $settings['interface']) {
					log_error("Resyncing OpenVPN for gateway group " . $gwgroupname . " client " . $settings["description"] . ".");
					openvpn_resync('client', $settings);
				}
			}
		}

		// Note: no need to resysnc Client Specific (csc) here, as changes to the OpenVPN real interface do not effect these.

	} else
		log_error("openvpn_resync_gwgroup called with null gwgroup parameter.");
}

function openvpn_get_active_servers($type="multipoint") {
	global $config, $g;

	$servers = array();
	if (is_array($config['openvpn']['openvpn-server'])) {
		foreach ($config['openvpn']['openvpn-server'] as & $settings) {
			if (empty($settings) || isset($settings['disable']))
				continue;

			$prot = $settings['protocol'];
			$port = $settings['local_port'];

			$server = array();
			$server['port'] = ($settings['local_port']) ? $settings['local_port'] : 1194;
			$server['mode'] = $settings['mode'];
			if ($settings['description'])
				$server['name'] = "{$settings['description']} {$prot}:{$port}";
			else
				$server['name'] = "Server {$prot}:{$port}";
			$server['conns'] = array();
			$server['vpnid'] = $settings['vpnid'];
			$server['mgmt'] = "server{$server['vpnid']}";
			$socket = "unix://{$g['varetc_path']}/openvpn/{$server['mgmt']}.sock";
			list($tn, $sm) = explode('/', $settings['tunnel_network']);

			if ((($server['mode'] == "p2p_shared_key") || ($sm >= 30) ) && ($type == "p2p"))
				$servers[] = openvpn_get_client_status($server, $socket);
			elseif (($server['mode'] != "p2p_shared_key") && ($type == "multipoint") && ($sm < 30))
				$servers[] = openvpn_get_server_status($server, $socket);

		}
	}
	return $servers;
}

function openvpn_get_server_status($server, $socket) {
	$errval;
	$errstr;
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
			if ($info['timed_out'])
				break;

			/* parse header list line */
			if (strstr($line, "HEADER"))
				continue;

			/* parse end of output line */
			if (strstr($line, "END") || strstr($line, "ERROR"))
				break;

			/* parse client list line */
			if (strstr($line, "CLIENT_LIST")) {
				$list = explode(",", $line);
				$conn = array();
				$conn['common_name'] = $list[1];
				$conn['remote_host'] = $list[2];
				$conn['virtual_addr'] = $list[3];
				$conn['bytes_recv'] = $list[4];
				$conn['bytes_sent'] = $list[5];
				$conn['connect_time'] = $list[6];
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
		$conn['remote_host'] = "Unable to contact daemon";
		$conn['virtual_addr'] = "Service not running?";
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

			if (empty($settings) || isset($settings['disable']))
				continue;

			$prot = $settings['protocol'];
			$port = ($settings['local_port']) ? ":{$settings['local_port']}" : "";

			$client = array();
			$client['port'] = $settings['local_port'];
			if ($settings['description'])
				$client['name'] = "{$settings['description']} {$prot}{$port}";
			else
				$client['name'] = "Client {$prot}{$port}";

			$client['vpnid'] = $settings['vpnid'];
			$client['mgmt'] = "client{$client['vpnid']}";
			$socket = "unix://{$g['varetc_path']}/openvpn/{$client['mgmt']}.sock";
			$client['status']="down";

			$clients[] = openvpn_get_client_status($client, $socket);
		}
	}
	return $clients;
}

function openvpn_get_client_status($client, $socket) {
	$errval;
	$errstr;
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
			if ($info['timed_out'])
				break;

			/* Get the client state */
			if (strstr($line,"CONNECTED")) {
				$client['status']="up";
				$list = explode(",", $line);

				$client['connect_time']  = date("D M j G:i:s Y", $list[0]);
				$client['virtual_addr']  = $list[3];
				$client['remote_host'] = $list[4];
			}
			if (strstr($line,"CONNECTING")) {
				$client['status']="connecting";
			}
			if (strstr($line,"ASSIGN_IP")) {
				$client['status']="waiting";
				$list = explode(",", $line);

				$client['connect_time']  = date("D M j G:i:s Y", $list[0]);
				$client['virtual_addr']  = $list[3];
			}
			if (strstr($line,"RECONNECTING")) {
				$client['status']="reconnecting";
				$list = explode(",", $line);

				$client['connect_time']  = date("D M j G:i:s Y", $list[0]);
				$client['status'] .= "; " . $list[2];
			}
			/* parse end of output line */
			if (strstr($line, "END") || strstr($line, "ERROR"))
				break;
		}

		/* If up, get read/write stats */
		if (strcmp($client['status'], "up") == 0) {
			fputs($fp, "status 2\n");
			/* recv all response lines */
			while (!feof($fp)) {
				/* read the next line */
				$line = fgets($fp, 1024);

				$info = stream_get_meta_data($fp);
				if ($info['timed_out'])
					break;

				if (strstr($line,"TCP/UDP read bytes")) {
					$list = explode(",", $line);
					$client['bytes_recv'] = $list[1];
				}

				if (strstr($line,"TCP/UDP write bytes")) {
					$list = explode(",", $line);
					$client['bytes_sent'] = $list[1];
				}

				/* parse end of output line */
				if (strstr($line, "END"))
					break;
			}
		}

		fclose($fp);

	} else {
		$DisplayNote=true;
		$client['remote_host'] = "Unable to contact daemon";
		$client['virtual_addr'] = "Service not running?";
		$client['bytes_recv'] = 0;
		$client['bytes_sent'] = 0;
		$client['connect_time'] = 0;
	}
	return $client;
}

function openvpn_refresh_crls() {
	global $g, $config;

	openvpn_create_dirs();

	if (is_array($config['openvpn']['openvpn-server'])) {
		foreach ($config['openvpn']['openvpn-server'] as $settings) {
			if (empty($settings))
				continue;
			if (isset($settings['disable']))
				continue;
			// Write the settings for the keys
			switch($settings['mode']) {
				case 'p2p_tls':
				case 'server_tls':
				case 'server_tls_user':
				case 'server_user':
					if (!empty($settings['crlref'])) {
						$crl = lookup_crl($settings['crlref']);
						crl_update($crl);
						$fpath = $g['varetc_path']."/openvpn/server{$settings['vpnid']}.crl-verify";
						file_put_contents($fpath, base64_decode($crl['text']));
						@chmod($fpath, 0644);
					}
					break;
			}
		}
	}
}

function openvpn_create_dirs() {
	global $g;
	if (!is_dir("{$g['varetc_path']}/openvpn"))
		safe_mkdir("{$g['varetc_path']}/openvpn", 0750);
	if (!is_dir("{$g['varetc_path']}/openvpn-csc"))
		safe_mkdir("{$g['varetc_path']}/openvpn-csc", 0750);
}

function openvpn_get_interface_ip($ip, $mask) {
	$baselong = ip2long32($ip) & ip2long($mask);
	$ip1 = long2ip32($baselong + 1);
	$ip2 = long2ip32($baselong + 2);
	return array($ip1, $ip2);
}

function openvpn_get_interface_ipv6($ipv6, $prefix) {
	$basev6 = gen_subnetv6($ipv6, $prefix);
	// Is there a better way to do this math?
	$ipv6_arr = explode(':', $basev6);
	$last = hexdec(array_pop($ipv6_arr));
	$ipv6_1 = Net_IPv6::compress(Net_IPv6::uncompress(implode(':', $ipv6_arr) . ':' . dechex($last + 1)));
	$ipv6_2 = Net_IPv6::compress(Net_IPv6::uncompress(implode(':', $ipv6_arr) . ':' . dechex($last + 2)));
	return array($ipv6_1, $ipv6_2);
}

function openvpn_clear_route($mode, $settings) {
	if (empty($settings['tunnel_network']))
		return;
	list($ip, $cidr) = explode('/', $settings['tunnel_network']);
	$mask = gen_subnet_mask($cidr);
	$clear_route = false;

	switch($settings['mode']) {
		case 'shared_key':
			$clear_route = true;
			break;
		case 'p2p_tls':
		case 'p2p_shared_key':
			if ($cidr == 30)
				$clear_route = true;
			break;
	}

	if ($clear_route && !empty($ip) && !empty($mask)) {
		list($ip1, $ip2) = openvpn_get_interface_ip($ip, $mask);
		$ip_to_clear = ($mode == "server") ? $ip1 : $ip2;
		/* XXX: Family for route? */
		mwexec("/sbin/route -q delete {$ip_to_clear}");
	}
}

function openvpn_gen_routes($value, $ipproto = "ipv4", $push = false, $iroute = false) {
	$routes = "";
	if (empty($value))
		return "";
	$networks = explode(',', $value);

	foreach ($networks as $network) {
		if ($ipproto == "ipv4")
			$route = openvpn_gen_route_ipv4($network, $iroute);
		else
			$route = openvpn_gen_route_ipv6($network, $iroute);

		if ($push)
			$routes .= "push \"{$route}\"\n";
		else
			$routes .= "{$route}\n";
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
	if (empty($prefix))
		$prefix = "128";
	return "{$i}route-ipv6 ${ipv6}/${prefix}";
}

function openvpn_get_settings($mode, $vpnid) {
	global $config;

	if (is_array($config['openvpn']['openvpn-server'])) {
		foreach ($config['openvpn']['openvpn-server'] as $settings) {
			if (isset($settings['disable']))
				continue;

			if ($vpnid != 0 && $vpnid == $settings['vpnid'])
				return $settings;
		}
	}

	if (is_array($config['openvpn']['openvpn-client'])) {
		foreach ($config['openvpn']['openvpn-client'] as $settings) {
			if (isset($settings['disable']))
				continue;

			if ($vpnid != 0 && $vpnid == $settings['vpnid'])
				return $settings;
		}
	}

	return array();
}

function openvpn_restart_by_vpnid($mode, $vpnid) {
	$settings = openvpn_get_settings($mode, $vpnid);
	openvpn_restart($mode, $settings);
}

?>
