<?php

/* $Id$ */
/*
	$RCSfile$
	Copyright (C) 2006  Fernando Lemos
	All rights reserved.

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
*/

require_once('config.inc');
require_once('pfsense-utils.inc');
require_once('util.inc');

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


function openvpn_validate_port($value, $name) {
	$value = trim($value);
	if (!empty($value) && !(is_numeric($value) && ($value > 0) && ($value < 65535)))
		return "The field '$name' must contain a valid port, ranging from 0 to 65535.";
	return false;
}


function openvpn_validate_cidr($value, $name) {
	$value = trim($value);
	if (!empty($value)) {
		list($ip, $mask) = explode('/', $value);
		if (!is_ipaddr($ip) or !is_numeric($mask) or ($mask > 32) or ($mask < 0))
			return "The field '$name' must contain a valid CIDR range.";
	}
	return false;
}


// Do the input validation
function openvpn_validate_input($mode, $post, $input_errors) {
	$Mode = ucfirst($mode);

	if ($mode == 'server') {
		if ($result = openvpn_validate_port($post['local_port'], 'Local port'))
			$input_errors[] = $result;

		if ($result = openvpn_validate_cidr($post['addresspool'], 'Address pool'))
			$input_errors[] = $result;

		if ($result = openvpn_validate_cidr($post['local_network'], 'Local network'))
			$input_errors[] = $result;

// DHCP-Options logic-check
	if (!empty($post['dhcp_dns'])) {
    $servers = explode(';', $post['dhcp_dns']);
      foreach ($servers as $server) if (!is_ipaddr($server)) 
        {$input_errors[] = 'The field \'DHCP-Opt.: DNS-Server\' must contain a valid IP address and no whitespaces.';
        break;}}
	if (!empty($post['dhcp_wins'])) {
    $servers = explode(';', $post['dhcp_wins']);
      foreach ($servers as $server) if (!is_ipaddr($server)) 
        {$input_errors[] = 'The field \'DHCP-Opt.: WINS-Server\' must contain a valid IP address and no whitespaces.';
        break;}}
	if (!empty($post['dhcp_nbdd'])) {
    $servers = explode(';', $post['dhcp_nbdd']);
      foreach ($servers as $server) if (!is_ipaddr($server)) 
        {$input_errors[] = 'The field \'DHCP-Opt.: NBDD-Server\' must contain a valid IP address and no whitespaces.';
        break;}}
	if (!empty($post['dhcp_ntp'])) {
    $servers = explode(';', $post['dhcp_ntp']);
      foreach ($servers as $server) if (!is_ipaddr($server)) 
        {$input_errors[] = 'The field \'DHCP-Opt.: NTP-Server\' must contain a valid IP address and no whitespaces.';
        break;}}

	}

	else { // Client mode
		if ($result = openvpn_validate_port($post['serverport'], 'Server port'))
			$input_errors[] = $result;

		$server_addr = trim($post['serveraddr']);
		if (!empty($value) && !(is_domain($server_addr) || is_ipaddr($server_addr)))
			$input_errors[] = 'The field \'Server address\' must contain a valid IP address or domain name.';

		if ($result = openvpn_validate_cidr($post['interface_ip'], 'Interface IP'))
			$input_errors[] = $result;

		if ($post['auth_method'] == 'shared_key') {
			if (empty($post['interface_ip']))
				$input_errors[] = 'The field \'Interface IP\' is required.';
		}
		if (isset($post['proxy_hostname']) && $post['proxy_hostname'] != "") {
			if (!is_domain($post['proxy_hostname']) || is_ipaddr($post['proxy_hostname']))
				$input_errors[] = 'The field \'Proxy Host\' must contain a valid IP address or domain name.';
			if (!is_port($post['proxy_port']))
				$input_errors[] = 'The field \'Proxy port\' must contain a valid port number.';
			if ($post['protocol'] != "TCP")
				$input_errors[] = 'The protocol must be TCP to use a HTTP proxy server.';
		}
		if (isset($post['use_shaper']) && $post['use_shaper'] != "") {
			if (!is_numeric($post['use_shaper']))
				$input_errors[] = 'The field \'Limit outgoing bandwidth\' must be numeric.';
		}

	}

	if ($result = openvpn_validate_cidr($post['remote_network'], 'Remote network'))
		$input_errors[] = $result;

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


function openvpn_validate_input_csc($post, $input_errors) {
	if ($result = openvpn_validate_cidr($post['ifconfig_push'], 'Interface IP'))
		$input_errors[] = $result;
}


// Rewrite the settings
function openvpn_reconfigure($mode, $id) {
	global $g, $config;

	$settings = $config['installedpackages']["openvpn$mode"]['config'][$id];
	if ($settings['disable']) return;

	$lport = 1194 + $id;

	// Set the keys up
	// Note that the keys' extension is also the directive that goes to the config file
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

	$pidfile = $g['varrun_path'] . "/openvpn_{$mode}{$id}.pid";
	$proto = ($settings['protocol'] == 'UDP' ? 'udp' : "tcp-{$mode}");
	$cipher = $settings['crypto'];
	$openvpn_conf = <<<EOD
writepid $pidfile
#user nobody
#group nobody
daemon
keepalive 10 60
ping-timer-rem
persist-tun
persist-key
dev tun
proto $proto
cipher $cipher
up /etc/rc.filter_configure
down /etc/rc.filter_configure

EOD;

	// Mode-specific stuff
	if ($mode == 'server') {
		list($ip, $mask) = explode('/', $settings['addresspool']);
		$mask = gen_subnet_mask($mask);

		// Using a shared key or not dynamically assigning IPs to the clients
		if (($settings['auth_method'] == 'shared_key') || ($settings['nopool'] == 'on')) {
			if ($settings['auth_method'] == 'pki') $openvpn_conf .= "tls-server\n";

			$baselong = ip2long($ip) & ip2long($mask);
			$ip1 = long2ip($baselong + 1);
			$ip2 = long2ip($baselong + 2);
			$openvpn_conf .= "ifconfig $ip1 $ip2\n";
		}
		// Using a PKI
		else if ($settings['auth_method'] == 'pki') {
			if ($settings['client2client']) $openvpn_conf .= "client-to-client\n";
			$openvpn_conf .= "server $ip $mask\n";
			$csc_dir = "{$g['varetc_path']}/openvpn_csc";
			$openvpn_conf .= "client-config-dir $csc_dir\n";
		}

		// We can push routes
		if (!empty($settings['local_network'])) {
			list($ip, $mask) = explode('/', $settings['local_network']);
			$mask = gen_subnet_mask($mask);
			$openvpn_conf .= "push \"route $ip $mask\"\n";
		}

		// The port we'll listen at
		$openvpn_conf .= "lport {$settings['local_port']}\n";

		// DHCP-Options
	  if (!empty($settings['dhcp_domainname'])) $openvpn_conf .= "push \"dhcp-option DOMAIN {$settings['dhcp_domainname']}\"\n";
	      	
    if (!empty($settings['dhcp_dns'])) {
	   	$servers = explode(';', $settings['dhcp_dns']);
   	  if (is_array($servers)) {
			 foreach ($servers as $server) $openvpn_conf .= "push \"dhcp-option DNS {$server}\"\n";
  	  }
		  else {
			$openvpn_conf .= "push \"dhcp-option DNS {$settings['dhcp_dns']}\"\n";
		  }
    }
  
    if (!empty($settings['dhcp_wins'])) {
	   	$servers = explode(';', $settings['dhcp_wins']);
   	  if (is_array($servers)) {
			 foreach ($servers as $server) $openvpn_conf .= "push \"dhcp-option WINS {$server}\"\n";
  	  }
		  else {
			$openvpn_conf .= "push \"dhcp-option WINS {$settings['dhcp_wins']}\"\n";
		  }
    }	

    if (!empty($settings['dhcp_nbdd'])) {
	   	$servers = explode(';', $settings['dhcp_nbdd']);
   	  if (is_array($servers)) {
			 foreach ($servers as $server) $openvpn_conf .= "push \"dhcp-option NBDD {$server}\"\n";
  	  }
		  else {
			$openvpn_conf .= "push \"dhcp-option NBDD {$settings['dhcp_nbdd']}\"\n";
		  }
    }	

    if (!empty($settings['dhcp_ntp'])) {
	   	$servers = explode(';', $settings['dhcp_ntp']);
   	  if (is_array($servers)) {
			 foreach ($servers as $server) $openvpn_conf .= "push \"dhcp-option NTP {$server}\"\n";
  	  }
		  else {
			$openvpn_conf .= "push \"dhcp-option NTP {$settings['dhcp_ntp']}\"\n";
		  }
    }	

  	if (!empty($settings['dhcp_nbttype']) && $settings['dhcp_nbttype'] !=0) $openvpn_conf .= "push \"dhcp-option NBT {$settings['dhcp_nbttype']}\"\n";
    if (!empty($settings['dhcp_nbtscope'])) $openvpn_conf .= "push \"dhcp-option NBS {$settings['dhcp_nbtscope']}\"\n";
   	if ($settings['dhcp_nbtdisable']) $openvpn_conf .= "push \"dhcp-option DISABLE-NBT\"\n";

	}

	else { // $mode == client
		// The remote server
		$openvpn_conf .= "remote {$settings['serveraddr']} {$settings['serverport']}\n";

		if ($settings['auth_method'] == 'pki') $openvpn_conf .= "client\n";

		if ($settings['use_dynamicport']) $openvpn_conf .= "nobind\n";
			else
			// The port we'll listen at
			$openvpn_conf .= "lport {$lport}\n";

		if (!empty($settings['use_shaper'])) $openvpn_conf .= "shaper {$settings['use_shaper']}\n";

		if (!empty($settings['interface_ip'])) {
			// Configure the IPs according to the address pool
			list($ip, $mask) = explode('/', $settings['interface_ip']);
			$mask = gen_subnet_mask($mask);
			$baselong = ip2long($ip) & ip2long($mask);
			$ip1 = long2ip($baselong + 1);
			$ip2 = long2ip($baselong + 2);
			$openvpn_conf .= "ifconfig $ip2 $ip1\n";
		}
		if (isset($settings['proxy_hostname']) && $settings['proxy_hostname'] != "") {
			/* ;http-proxy-retry # retry on connection failures */
			$openvpn_conf .= "http-proxy {$settings['proxy_hostname']} {$settings['proxy_port']}\n";
		}
	}

	// Add the routes if they're set
	if (!empty($settings['remote_network'])) {
		list($ip, $mask) = explode('/', $settings['remote_network']);
		$mask = gen_subnet_mask($mask);
		$openvpn_conf .= "route $ip $mask\n";
	}

	// Write the settings for the keys
	foreach ($keys as $key)
		$openvpn_conf .= $key['directive'] . ' ' . $base_file . $key['ext'] . "\n";

	if ($settings['use_lzo']) $openvpn_conf .= "comp-lzo\n";

	if ($settings['dynamic_ip']) {
		$openvpn_conf .= "persist-remote-ip\n";
		$openvpn_conf .= "float\n";
	}

	if (!empty($settings['custom_options'])) {
		$options = explode(';', $settings['custom_options']);
		if (is_array($options)) {
			foreach ($options as $option)
				$openvpn_conf .= "$option\n";
		}
		else {
			$openvpn_conf .= "{$settings['custom_options']}\n";
		}
	}

	file_put_contents($g['varetc_path'] . "/openvpn_{$mode}{$id}.conf", $openvpn_conf);
}


function openvpn_resync_csc($id) {
	global $g, $config;

	$settings = $config['installedpackages']['openvpncsc']['config'][$id];

	if ($settings['disable'] == 'on') {
		$filename = "{$g['varetc_path']}/openvpn_csc/{$settings['commonname']}";
		unlink_if_exists($filename);
		return;
	}

	$conf = '';
	if ($settings['block'] == 'on') $conf .= "disable\n";
	if ($settings['push_reset'] == 'on') $conf .= "push-reset\n";
	if (!empty($settings['ifconfig_push'])) {
		list($ip, $mask) = explode('/', $settings['ifconfig_push']);
		$baselong = ip2long($ip) & gen_subnet_mask_long($mask);
		$conf .= 'ifconfig-push ' . long2ip($baselong + 1) . ' ' . long2ip($baselong + 2) . "\n";
	}
	if (!empty($settings['custom_options'])) {
		$options = explode(';', $settings['custom_options']);
		if (is_array($options)) {
			foreach ($options as $option)
				$conf .= "$option\n";
		}
		else {
			$conf .= "{$settings['custom_options']}\n";
		}
	}

	$filename = "{$g['varetc_path']}/openvpn_csc/{$settings['commonname']}";
	file_put_contents($filename, $conf);
	chown($filename, 'nobody');
	chgrp($filename, 'nogroup');
}


function openvpn_restart($mode, $id) {
	global $g, $config;

	$pidfile = $g['varrun_path'] . "/openvpn_{$mode}{$id}.pid";
	killbypid($pidfile);
	sleep(2);

	$settings = $config['installedpackages']["openvpn$mode"]['config'][$id];
	if ($settings['disable']) return;

	$configfile = $g['varetc_path'] . "/openvpn_{$mode}{$id}.conf";
	mwexec_bg("nohup openvpn --config $configfile");
	touch("{$g['tmp_path']}/filter_dirty");
}


// Resync the configuration and restart the VPN
function openvpn_resync($mode, $id) {
	openvpn_reconfigure($mode, $id);
	openvpn_restart($mode, $id);
}

function openvpn_create_cscdir() {
	global $g;

	$csc_dir = "{$g['varetc_path']}/openvpn_csc";
	if (is_dir($csc_dir))
		rmdir_recursive($csc_dir);
	make_dirs($csc_dir);
	chown($csc_dir, 'nobody');
	chgrp($csc_dir, 'nobody');
}

// Resync and restart all VPNs
function openvpn_resync_all() {
	global $config;

	$started = false;

	foreach (array('server', 'client') as $mode) {
		if (is_array($config['installedpackages']["openvpn$mode"]['config'])) {
			foreach ($config['installedpackages']["openvpn$mode"]['config'] as $id => $settings)
				openvpn_resync($mode, $id);
			$started = true;
		}
	}

	openvpn_create_cscdir();
	if (is_array($config['installedpackages']['openvpncsc']['config'])) {
		foreach ($config['installedpackages']['openvpncsc']['config'] as $id => $csc)
			openvpn_resync_csc($id);
		$started = true;
	}
	
	/* give speedy machines time to settle */
	if($started) {
		sleep(5);
		/* reload the filter policy */
		filter_configure();
	}
	
}


function openvpn_print_javascript($mode) {
	$javascript = <<<EOD
<script language="JavaScript">
<!--
function onAuthMethodChanged() {
	var method = document.iform.auth_method;
	var endis = (method.options[method.selectedIndex].value == 'shared_key');

	document.iform.shared_key.disabled = !endis;
	document.iform.ca_cert.disabled = endis;
	document.iform.{$mode}_cert.disabled = endis;
	document.iform.{$mode}_key.disabled = endis;

EOD;
	if ($mode == 'server') {
		$javascript .= <<<EOD
	document.iform.dh_params.disabled = endis;
	document.iform.crl.disabled = endis;
	document.iform.nopool.disabled = endis;
	document.iform.local_network.disabled = endis;
	document.iform.client2client.disabled = endis;

EOD;
	}

	else { // Client mode
		$javascript .= "\tdocument.iform.remote_network.disabled = !endis;\n";
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