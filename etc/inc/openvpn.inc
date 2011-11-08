<?php

/* $Id$ */
/*
	$RCSfile$
	
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

$openvpn_prots = array("UDP", "TCP");

$openvpn_dev_mode = array("tun", "tap");

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

$openvpn_dh_lengths = array(
	1024, 2048, 4096 );

$openvpn_cert_depths = array(
	1 => "One (Client+Server)",
	2 => "Two (Client+Intermediate+Server)",
	3 => "Three (Client+2xIntermediate+Server)",
	4 => "Four (Client+3xIntermediate+Server)",
	5 => "Five (Client+4xIntermediate+Server)"
);

$openvpn_server_modes = array(
	'p2p_tls' => gettext("Peer to Peer ( SSL/TLS )"),
	'p2p_shared_key' => gettext("Peer to Peer ( Shared Key )"),
	'server_tls' => gettext("Remote Access ( SSL/TLS )"),
	'server_user' => gettext("Remote Access ( User Auth )"),
	'server_tls_user' => gettext("Remote Access ( SSL/TLS + User Auth )"));

$openvpn_client_modes = array(
	'p2p_tls' => gettext("Peer to Peer ( SSL/TLS )"),
	'p2p_shared_key' => gettext("Peer to Peer ( Shared Key )") );

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

function openvpn_port_used($prot, $port) {
	global $config;

	if (is_array($config['openvpn']['openvpn-server']))
		foreach ($config['openvpn']['openvpn-server'] as & $settings)
			if ($port == $settings['local_port'] &&
				$prot == $settings['protocol'] && !isset($settings['disable']))
				return $settings['vpnid'];

	if (is_array($config['openvpn']['openvpn-client']))
		foreach ($config['openvpn']['openvpn-client'] as & $settings)
			if ($port == $settings['local_port'] &&
				$prot == $settings['protocol'] && !isset($settings['disable']))
				return $settings['vpnid'];

	return 0;
}

function openvpn_port_next($prot) {

	$port = 1194;
	while(openvpn_port_used($prot, $port))
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

function openvpn_get_engines() {
	$openssl_engines = array('none' => 'No Hardware Crypto Acceleration');
	exec("/usr/bin/openssl engine", $openssl_engine_output);
	foreach ($openssl_engine_output as $oeo) {
		$linematch = array();
		preg_match("/\((.*)\)\s(.*)/", $oeo, $linematch);
		if ($linematch[1] != "dynamic")
			$openssl_engines[$linematch[1]] = $linematch[2];
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

function openvpn_validate_cidr($value, $name) {
	$value = trim($value);
	if (!empty($value)) {
		list($ip, $mask) = explode('/', $value);
		if (!is_ipaddr($ip) or !is_numeric($mask) or ($mask > 32) or ($mask < 0))
			return sprintf(gettext("The field '%s' must contain a valid CIDR range."), $name);
	}
	return false;
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
	if (mwexec("/sbin/ifconfig {$devname}")) {

		/* create the tap device if required */
		if (!file_exists("/dev/{$tunname}"))
			exec("/sbin/ifconfig {$tunname} create");

		/* rename the device */
		mwexec("/sbin/ifconfig {$tunname} name {$devname}");

		/* add the device to the openvpn group */
		mwexec("/sbin/ifconfig {$devname} group openvpn");
	}

	$pfile = $g['varrun_path'] . "/openvpn_{$mode_id}.pid";
	$proto = ($settings['protocol'] == 'UDP' ? 'udp' : "tcp-{$mode}");
	$dev_mode = $settings['dev_mode'];
	$cipher = $settings['crypto'];

	$interface = $settings['interface'];
	$ipaddr = $settings['ipaddr'];
	$ipaddrv6 = $settings['ipaddrv6'];

	// If a specific ip address (VIP) is requested, use it.
	// Otherwise, if a specific interface is requested, use it
	// If "any" interface was selected, local directive will be ommited.
	if (is_ipaddrv4($ipaddr)) {
		$iface_ip=$ipaddr;
	} elseif (is_ipaddrv6($ipaddrv6)) {
		$iface_ipv6=$ipaddrv6;
	} else {
		if ((!empty($interface)) && (strcmp($interface, "any"))) {
			$iface_ip=get_interface_ip($interface);
		}
		if ((!empty($interface)) && (strcmp($interface, "any"))) {
			$iface_ipv6=get_interface_ipv6($interface);
		}
	}

	$conf  = "dev {$devname}\n";
	$conf .= "dev-type {$settings['dev_mode']}\n";
	switch($settings['dev_mode']) {
		case "tun":
			$conf .= "tun-ipv6\n";
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
	$conf .= "up /usr/local/sbin/ovpn-linkup\n";
	$conf .= "down /usr/local/sbin/ovpn-linkdown\n";

	if (is_ipaddrv4($iface_ip)) {
		$conf .= "local {$iface_ip}\n";	
	}
	if (is_ipaddrv6($iface_ipv6)) {
		// $conf .= "local {$iface_ipv6}\n";	
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
				if (($settings['dev_mode'] != "tap") && ($cidr < 30)) {
					$conf .= "server {$ip} {$mask}\n";
					$conf .= "client-config-dir {$g['varetc_path']}/openvpn-csc\n";
				}
			case 'p2p_shared_key':
				if ($settings['dev_mode'] != "tap") {
					$baselong = ip2long32($ip) & ip2long($mask);
					$ip1 = long2ip32($baselong + 1);
					$ip2 = long2ip32($baselong + 2);
					$conf .= "ifconfig $ip1 $ip2\n";
				}
				break;
			case 'server_tls':
			case 'server_user':
			case 'server_tls_user':
				if ($settings['dev_mode'] != "tap") {
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
				$conf .= "username-as-common-name\n";
				if (!empty($settings['authmode'])) {
					$authcfgs = explode(",", $settings['authmode']);
					$sed = "\$authmodes=array(";
					$firstsed = 0;
					foreach ($authcfgs as $authcfg) {
						if ($firstsed > 0)
							$sed .= ",";
						$firstsed = 1;
						$sed .= "\"{$authcfg}\"";
					}
					$sed .= ");\\\n";
					if ($settings['strictusercn'])
						$sed .= "\$strictusercn = true;";
					$sed .= " \$modeid = \"{$mode_id}\";";
					mwexec("/bin/cat /etc/inc/openvpn.auth-user.php | /usr/bin/sed 's/\/\/<template>/{$sed}/g' >  {$g['varetc_path']}/openvpn/{$mode_id}.php");
					mwexec("/bin/chmod a+x {$g['varetc_path']}/openvpn/{$mode_id}.php");
					$conf .= "auth-user-pass-verify {$g['varetc_path']}/openvpn/{$mode_id}.php via-env\n";
				}
				break;
		}
		if (!isset($settings['cert_depth']) && (strstr($settings['mode'], 'tls')))
			$settings['cert_depth'] = 1;
		if (is_numeric($settings['cert_depth'])) {
			$sed = "";
			$cert = lookup_cert($settings['certref']);
			$servercn = cert_get_cn($cert['crt']);
			$sed .= "\$server_cn = \"{$servercn}\";\\\n";
			$sed .= "\$allowed_depth = {$settings['cert_depth']};\\\n";
			mwexec("/bin/cat /etc/inc/openvpn.tls-verify.php | /usr/bin/sed 's/\/\/<template>/{$sed}/g' >  {$g['varetc_path']}/openvpn/{$mode_id}.tls-verify.php");
			mwexec("/bin/chmod a+x {$g['varetc_path']}/openvpn/{$mode_id}.tls-verify.php");
			$conf .= "tls-verify {$g['varetc_path']}/openvpn/{$mode_id}.tls-verify.php\n";
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
			list($ip, $mask) = explode('/', $settings['local_network']);
			$mask = gen_subnet_mask($mask);
			$conf .= "push \"route $ip $mask\"\n";
		}
		if ($settings['local_networkv6']) {
			list($ipv6, $prefix) = explode('/', $settings['local_networkv6']);
			$conf .= "push \"route-ipv6 $ipv6/$prefix\"\n";
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
			$baselong = ip2long32($ip) & ip2long($mask);
			$ip1 = long2ip32($baselong + 1);
			$ip2 = long2ip32($baselong + 2);
			$conf .= "ifconfig $ip2 $ip1\n";
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
	if ((substr($settings['mode'], 0, 3) == "p2p") && is_subnet($settings['remote_network'])) {
		list($ip, $mask) = explode('/', $settings['remote_network']);
		$mask = gen_subnet_mask($mask);
		$conf .= "route $ip $mask\n";
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
			$cert = lookup_cert($settings['certref']);
			openvpn_add_keyfile($cert['crt'], $conf, $mode_id, "cert");
			openvpn_add_keyfile($cert['prv'], $conf, $mode_id, "key");
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

	if ($settings['compression'])
		$conf .= "comp-lzo\n";

	if ($settings['passtos'])
		$conf .= "passtos\n";

	if ($settings['resolve_retry'])
		$conf .= "resolv-retry infinite\n";

	if ($settings['dynamic_ip']) {
		$conf .= "persist-remote-ip\n";
		$conf .= "float\n";
	}

	openvpn_add_custom($settings, $conf);

	$fpath = $g['varetc_path']."/openvpn/{$mode_id}.conf";
	file_put_contents($fpath, $conf);
	//chown($fpath, 'nobody');
	//chgrp($fpath, 'nobody');
	@chmod("{$g['varetc_path']}/openvpn/{$mode_id}.conf", 0600);
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

	/* start the new process */
	$fpath = $g['varetc_path']."/openvpn/{$mode_id}.conf";
	mwexec_bg("/usr/local/sbin/openvpn --config {$fpath}");

	if (!$g['booting'])
		send_event("filter reload");
}

function openvpn_delete($mode, & $settings) {
	global $g, $config;

	$vpnid = $settings['vpnid'];
	$mode_id = $mode.$vpnid;

	$tunname = "tun{$vpnid}";
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
	mwexec("/sbin/ifconfig {$devname} -group openvpn");

	/* restore the original adapter name */
	mwexec("/sbin/ifconfig {$devname} name {$tunname}");

	/* remove the configuration files */
	mwexec("/bin/rm {$g['varetc_path']}/openvpn/{$mode_id}.*");
}

function openvpn_resync_csc(& $settings) {
	global $g, $config;

	$fpath = $g['varetc_path']."/openvpn-csc/".$settings['common_name'];

	if (isset($settings['disable'])) {
		unlink_if_exists($fpath);
		return;
	}

	$conf = '';
	if ($settings['block'])
		$conf .= "disable\n";

	if ($settings['push_reset'])
		$conf .= "push-reset\n";

	if (!empty($settings['tunnel_network'])) {
		list($ip, $mask) = explode('/', $settings['tunnel_network']);
		$baselong = ip2long32($ip) & gen_subnet_mask_long($mask);
		$ip1 = long2ip32($baselong + 1);
		$ip2 = long2ip32($baselong + 2);
		$conf .= "ifconfig-push {$ip1} {$ip2}\n";
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
	// delay our setup until the system
	// has a chance to init our paths
	if (!file_exists($g['varetc_path']."/openvpn") ||
		!file_exists($g['varetc_path']."/openvpn-csc"))
		return;

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
	
			$vpnid = $settings['vpnid'];
			$mode_id = "server{$vpnid}";
			$server['mgmt'] = $mode_id;
			$socket = "unix://{$g['varetc_path']}/openvpn/{$mode_id}.sock";
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
		}

		/* cleanup */
		fclose($fp);
	} else {
		$conn = array();
		$conn['common_name'] = "[error]";
		$conn['remote_host'] = "Management Daemon Unreachable";
		$conn['virtual_addr'] = "";
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
	
			$vpnid = $settings['vpnid'];
			$mode_id = "client{$vpnid}";
			$client['mgmt'] = $mode_id;
			$socket = "unix://{$g['varetc_path']}/openvpn/{$mode_id}.sock";
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
		$client['remote_host'] = "No Management Daemon";
		$client['virtual_addr'] = "See Note Below";
		$client['bytes_recv'] = 0;
		$client['bytes_sent'] = 0;
		$client['connect_time'] = 0;
	}
	return $client;
}

function openvpn_refresh_crls() {
	global $g, $config;

	if (!file_exists($g['varetc_path']."/openvpn"))
		return;

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

?>
