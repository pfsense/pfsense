<?php
require('config.inc');

/* $Id$ */
/*
	openvpn.inc
	part of pfSense (www.pfSense.com)
	(C)2006 Fernando Lemos
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

// Return the list of ciphers OpenVPN supports
function openvpn_get_ciphers($pkg) {
                foreach ($pkg['fields']['field'] as $i => $field) {
                        if ($field['fieldname'] == 'crypto') break;
                }
                $option_array = &$pkg['fields']['field'][$i]['options']['option'];
                $ciphers_out = shell_exec('openvpn --show-ciphers | grep "default key" | awk \'{print $1, "(" $2 "-" $3 ")";}\'');
                $ciphers = explode("\n", trim($ciphers_out));
		sort($ciphers);
                foreach ($ciphers as $cipher) {
                        $value = explode(' ', $cipher);
                        $value = $value[0];
                        $option_array[] = array('value' => $value, 'name' => $cipher);
                }
}


// Do the input validation
function openvpn_validate_input($mode, $post, $input_errors) {
	$Mode = ucfirst($mode);

	$port = trim($post['port']);
	if ($port && (!is_numeric($port) || ($port < 0) || ($port > 65535)))
		$input_errors[] = 'The field \'Port\' should contain a valid port number, between 1 and 65536.';
	if ($mode == 'client') {
		$server_port = trim($post['serverport']);
		if ($server_port && (!is_numeric($server_port) || ($server_port < 0) || ($port > 65535)))
			$input_errors[] = 'The field \'Server port\' should contain a valid port number, between 1 and 65536.';
	}
												
	$reqfields = array('local_ip', 'remote_ip');
	$reqfieldsn = array('Local IP', 'Remote IP');
	foreach($reqfields as $i => $field) {
		$value = trim($post[$field]);
		if ($value and (!is_ipaddr($value)))
			$input_errors[] = "The field '{$reqfieldsn[$i]}' must contain a valid IP address";
	}

	if ($mode == 'client') {
		$server_addr = trim($post['serveraddr']);
		if ($value && !(is_domain($server_addr) || is_ipaddr($server_addr)))
			$input_errors[] = 'The field \'Server address\' must contain a valid IP address or domain name.';
	}

	$value = trim($post['ipblock']);
	if ($value) {
		list($ip, $mask) = explode('/', $value);
		if (!is_ipaddr($ip) or !is_numeric($mask) or ($mask > 32) or ($mask < 0))
			$input_errors[] = "The field 'IP block' must contain a valid CIDR range.";
	}

	if ($_POST['auth_method'] == 'shared_key') {
		$reqfields[] = 'shared_key';
		$reqfieldsn[] = 'Shared key';
	}
	else {
		$req = explode(' ', "ca_cert {$mode}_cert {$mode}_key");
		$reqn = array(	'CA certificate',
				ucfirst($mode) . ' certificate',
				ucfirst($mode) . ' key');
		$reqfields = array_merge($reqfields, $req);
		$reqfieldsn = array_merge($reqfieldsn, $reqn);
		if ($mode == 'server') {
			$reqfields[] = 'dh_params';
			$reqfieldsn[] = 'DH parameters';
		}
	}
	do_input_validation($post, $reqfields, $reqfieldsn, &$input_errors);

	$value = trim($post['shared_key']);
	$items = array();
	if ($_POST['auth_method'] == 'shared_key') {
		$items[] = array(	'field' => 'shared_key',
					'string' => 'OpenVPN Static key V1',
					'name' => 'Shared key');
	}
	else {
		$items[] = array(	'field' => 'ca_cert',
					'string' => 'CERTIFICATE',
					'name' => 'CA certificate');
		$items[] = array(	'field' => "{$mode}_cert",
					'string' => 'CERTIFICATE',
					'name' => "$Mode certificate");
		$items[] = array(	'field' => "{$mode}_key",
					'string' => 'RSA PRIVATE KEY',
					'name' => "$Mode key");
		if ($mode == 'server') {
			$items[] = array(	'field' => 'dh_params',
						'string' => 'DH PARAMETERS',
						'name' => 'DH parameters');
			$items[] = array(	'field' => 'crl',
						'string' => 'X509 CRL',
						'name' => 'CRL');
		}
	}
	foreach ($items as $item) {
		$value = trim($_POST[$item['field']]);
		$string = $item['string'];
		if ($value && (!strstr($value, "-----BEGIN {$string}-----") || !strstr($value, "-----END {$string}-----")))
			$input_errors[] = "The field '{$item['name']}' does not appear to be valid";
	}
}


// Rewrite the settings
function openvpn_reconfigure($mode, $id) {
	global $g, $config;

	$settings = $config['installedpackages']["openvpn$mode"]['config'][$id];
	if ($settings['disable']) return;

	// Set up the keys
	// Note that the keys' extension is the directive that goes to the config file
	$base_file = $g['varetc_path'] . "/openvpn_{$mode}{$id}.";
	$keys = array();
	if ($settings['auth_method'] == 'shared_key')
		$keys[] = array('field' => 'shared_key', 'ext'  => 'secret', 'directive' => 'secret');
	else {
		$keys[] = array('field' => 'ca_cert', 'ext' => 'ca', 'directive' => 'ca');
		$keys[] = array('field' => "{$mode}_cert", 'ext' => 'cert', 'directive' => 'cert');
		$keys[] = array('field' => "{$mode}_key", 'ext' => 'key', 'directive' => 'key');
		if ($mode == 'server')
			$keys[] = array('field' => 'dh_params', 'ext' => 'dh', 'directive' => 'dh');
		if ($settings['crl'])
			$keys[] = array('field' => 'crl', 'ext' => 'crl', 'directive' => 'crl-verify');
	}
	foreach($keys as $key) {
		$filename = $base_file . $key['ext'];
		file_put_contents($filename, base64_decode($settings[$key['field']]));
		chown($filename, 'nobody');
		chgrp($filename, 'nobody');
	}

	$proto = ($settings['protocol'] == 'UDP' ? 'udp' : "tcp-{$mode}");
	$port = $settings['port'];
	$ifconfig = $settings['local_ip'] . ' ' . $settings['remote_ip'];
	list($route_ip, $route_mask) = explode('/', $settings['ipblock']);
	$route_mask = gen_subnet_mask($route_mask);
	$cipher = $settings['crypto'];
	$openvpn_conf = <<<EOD
user nobody
group nobody
daemon
keepalive 10 60
ping-timer-rem
persist-tun
persist-key
dev tun
proto $proto
port $port
ifconfig $ifconfig
route $route_ip $route_mask
cipher $cipher

EOD;
	if ($settings['auth_method'] == 'pki')
		$openvpn_conf .= "tls-$mode\n";

	// Write the settings for the keys
	foreach ($keys as $key)
		$openvpn_conf .= $key['directive'] . ' ' . $base_file . $key['ext'] . "\n";

	if ($mode == 'client') $openvpn_conf .= 'remote ' . $settings['serveraddr'] . ' ' .$settings['serverport'] . "\n";
	if ($settings['use_lzo']) $openvpn_conf .= "comp-lzo\n";
	if ($settings['dynamic_ip']) $openvpn_conf .= "persist-remote-ip\n";

	file_put_contents($g['varetc_path'] . "/openvpn_{$mode}{$id}.conf", $openvpn_conf);
}


function openvpn_restart($mode, $id) {
	global $g, $config;

	$pidfile = $g['varrun_path'] . "/openvpn_{$mode}{$id}.pid";
	killbypid($pidfile);
	sleep(2);

	$settings = $config['installedpackages']["openvpn$mode"]['config'][$id];
	if ($settings['disable']) return;

	$configfile = $g['varetc_path'] . "/openvpn_{$mode}{$id}.conf";
	mwexec("openvpn --config $configfile --writepid $pidfile");
}


// Resync the configuration and restart the VPN 
function openvpn_resync($mode, $id) {
	openvpn_reconfigure($mode, $id);
	openvpn_restart($mode, $id);
}


// Resync and restart all VPNs
function openvpn_resync_all() {
	global $config;

	foreach (array('server', 'client') as $mode) {
		if (is_array($config['installedpackages']["openvpn$mode"]['config'])) {
			foreach ($config['installedpackages']["openvpn$mode"]['config'] as $id => $settings)
				openvpn_resync($mode, $id);
		}
	}
}


function openvpn_print_javascript($mode) {
	$javascript = <<<EOD
<script language="JavaScript">
<!--
function onAuthMethodChanged() {
	var endis = (document.iform.auth_method.options.value == 'shared_key');
	document.iform.shared_key.disabled = !endis;
	document.iform.ca_cert.disabled = endis;
	document.iform.{$mode}_cert.disabled = endis;
	document.iform.{$mode}_key.disabled = endis;

EOD;
	if ($mode == 'server') {
		$javascript .= "\tdocument.iform.dh_params.disabled = endis;\n";
		$javascript .= "\tdocument.iform.crl.disabled = endis;\n";
	}
	$javascript .= <<<EOD
}
//-->
</script>

EOD;
	print($javascript);
}


function openvpn_print_javascript2() {
	$javascript = <<<EOD
<script language="JavaScript">
<!--
	onAuthMethodChanged();
//-->
</script>

EOD;
	print($javascript);
}
?>