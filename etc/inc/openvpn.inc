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
*/

require_once('config.inc');
require_once('pfsense-utils.inc');
require_once('util.inc');

// Shutdown running process if needed
function openvpn_delete($mode, $id) {
	global $g, $config;
	
	$settings = $config['installedpackages']['openvpn$mode']['config'][$id];	
	$mode = $settings['mode'];
	$ps = $g['varetc_path'] . "/openvpn_{$mode}{$id}.conf";
	$ps_id = `ps awux | grep $ps | awk '{ print \$2 }'`;
	killbypid($ps_id);
}

// Return the list of ciphers OpenVPN supports
function openvpn_get_ciphers($pkg) {
	global $config;

	foreach ($pkg['fields']['field'] as $i => $field) {
		if ($field['fieldname'] == 'crypto') {
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
		if ($field['fieldname'] == 'cipher') {
		        if (is_array($config['openvpn']['keys'])) {
                		if (count($config['openvpn']['keys']) > 0) {
					$option_array = &$pkg['fields']['field'][$i]['options']['option'];
                        		foreach ($config['openvpn']['keys'] as $cipher => $type) {
						if ($type['shared.key'])
							$option_array[] = array('value' => $cipher, 'name' => $cipher);
                       			}
                		}
        		}
		}
                if ($field['fieldname'] == 'cipherpki') {
                        if (is_array($config['openvpn']['keys'])) {
                                if (count($config['openvpn']['keys']) > 0) {
                                        $option_array = &$pkg['fields']['field'][$i]['options']['option'];
                                        foreach ($config['openvpn']['keys'] as $cipher => $type) {
                                                if ($type['auth_method'] == 'pki')
                                                        $option_array[] = array('value' => $cipher, 'name' => $type['descr']);
                                        }
                                }
                        }
                }

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
			
/*  check for port in use - update of existing entries not possible because $_GET['act'] is not passed from pkg_edit.php :-( mfuchs		
    $portinuse = shell_exec('sockstat | grep '.$post['local_port'].' | grep '.strtolower($post['protocol']));
    if (!empty($portinuse))
    $input_errors[] = 'The port '.$post['local_port'].'/'.strtolower($post['protocol']).' is already in use.';
*/

	if (!empty($post['dhcp_dns'])) {
    $servers = explode(';', $post['dhcp_dns']);
      foreach ($servers as $server) if (!is_ipaddr($server))
        {$input_errors[] = 'The field \'DHCP Option: DNS Server\' must contain a valid IP address and no whitespaces.';
        break;}}
	if (!empty($post['dhcp_wins'])) {
    $servers = explode(';', $post['dhcp_wins']);
      foreach ($servers as $server) if (!is_ipaddr($server))
        {$input_errors[] = 'The field \'DHCP Option: WINS Server\' must contain a valid IP address and no whitespaces.';
        break;}}
	if (!empty($post['dhcp_nbdd'])) {
    $servers = explode(';', $post['dhcp_nbdd']);
      foreach ($servers as $server) if (!is_ipaddr($server))
        {$input_errors[] = 'The field \'DHCP Option: NBDD Server\' must contain a valid IP address and no whitespaces.';
        break;}}
	if (!empty($post['dhcp_ntp'])) {
    $servers = explode(';', $post['dhcp_ntp']);
      foreach ($servers as $server) if (!is_ipaddr($server))
        {$input_errors[] = 'The field \'DHCP Option: NTP Server\' must contain a valid IP address and no whitespaces.';
        break;}}
	if (isset($post['maxclients']) && $post['maxclients'] != "") {
		if (!is_numeric($post['maxclients']))
			$input_errors[] = 'The field \'Maximum clients\' must be numeric.';
		}

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

/* This are no more needed comment them from now and remove later */
/*
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
*/
if ($mode != "server") {
	$value = trim($post['shared_key']);
	$items = array();

	if ($_POST['auth_method'] == 'shared_key') {
		$items[] = array(	'field' => 'shared.key',
					'string' => 'OpenVPN Static key V1',
					'name' => 'Shared key');
	}
	else {
		$items[] = array(	'field' => 'ca.crt',
					'string' => 'CERTIFICATE',
					'name' => 'CA certificate');
		$items[] = array(	'field' => "{$mode}.crt",
					'string' => 'CERTIFICATE',
					'name' => "$Mode certificate");
		$items[] = array(	'field' => "{$mode}.key",
					'string' => 'RSA PRIVATE KEY',
					'name' => "$Mode key");
	  $items[] = array(	'field' => 'tls',
		      'string' => 'OpenVPN Static key V1',
		      'name' => 'TLS');
		if ($mode == 'server') {
			$items[] = array(	'field' => 'dh_param.dhs',
						'string' => 'DH PARAMETERS',
						'name' => 'DH parameters');
			$items[] = array(	'field' => 'crl.crl',
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
}


function openvpn_validate_input_csc($post, $input_errors) {
	if ($result = openvpn_validate_cidr($post['ifconfig_push'], 'Interface IP'))
		$input_errors[] = $result;

	if ($post['push_reset'] != 'on') {
    if (!empty($post['dhcp_domainname']))
        $input_errors[] = 'It makes no sense to unselect push reset and configure DHCP options';
    elseif (!empty($post['dhcp_dns']))
        $input_errors[] = 'It makes no sense to unselect push reset and configure DHCP options';
    elseif (!empty($post['dhcp_wins']))
        $input_errors[] = 'It makes no sense to unselect push reset and configure DHCP options';
    elseif (!empty($post['dhcp_nbdd']))
        $input_errors[] = 'It makes no sense to unselect push reset and configure DHCP options';
    elseif (!empty($post['dhcp_ntp']))
        $input_errors[] = 'It makes no sense to unselect push reset and configure DHCP options';
    elseif ($post['dhcp_nbttype'])
        $input_errors[] = 'It makes no sense to unselect push reset and configure DHCP options';
    elseif (!empty($post['dhcp_nbtscope']))
        $input_errors[] = 'It makes no sense to unselect push reset and configure DHCP options';
    elseif ($post['dhcp_nbtdisable'])
        $input_errors[] = 'It makes no sense to unselect push reset and configure DHCP options';

  } else {

	if (!empty($post['dhcp_dns'])) {
    $servers = explode(';', $post['dhcp_dns']);
      foreach ($servers as $server) if (!is_ipaddr($server))
        {$input_errors[] = 'The field \'DHCP Option: DNS Server\' must contain a valid IP address and no whitespaces.';
        break;}}
	if (!empty($post['dhcp_wins'])) {
    $servers = explode(';', $post['dhcp_wins']);
      foreach ($servers as $server) if (!is_ipaddr($server))
        {$input_errors[] = 'The field \'DHCP Option: WINS Server\' must contain a valid IP address and no whitespaces.';
        break;}}
	if (!empty($post['dhcp_nbdd'])) {
    $servers = explode(';', $post['dhcp_nbdd']);
      foreach ($servers as $server) if (!is_ipaddr($server))
        {$input_errors[] = 'The field \'DHCP Option: NBDD Server\' must contain a valid IP address and no whitespaces.';
        break;}}
	if (!empty($post['dhcp_ntp'])) {
    $servers = explode(';', $post['dhcp_ntp']);
      foreach ($servers as $server) if (!is_ipaddr($server))
        {$input_errors[] = 'The field \'DHCP Option: NTP Server\' must contain a valid IP address and no whitespaces.';
        break;}}

}}

// Create server PKI certificate if it is not present on system 
function openvpn_server_create_cert($mode, $id) {
	if($mode == "client") 
		return;
	global $g, $config;
	$settings = $config['installedpackages']["openvpn$mode"]['config'][$id];
	$interface = $settings['interface'];
	if(!$interface) 
		$interface = "WAN";
	$serveruniq =  $interface . $settings['local_port'] . $settings['protocol'];
	log_error("Creating server certificate for {$settings['description']}.");
	$caname = $settings['cipherpki'];	
	foreach($config['openvpn']['keys'] as $ca => $ca2) {
		if($ca == $caname)
			$cakeysize = $ca2['keysize'];
	}	
	$ovpncapath = $g['varetc_path']."/openvpn/certificates";	
	$easyrsapath = $g['easyrsapath'];
	config_lock();
	$fd = fopen($ovpncapath . "/RUNME_2ND", "w");
	fwrite($fd, "#!/bin/tcsh\n");
	fwrite($fd, "cd $ovpncapath \n");
	fwrite($fd, "source $ovpncapath/$caname/vars \n");
	fwrite($fd, "$easyrsapath/pkitool --batch --server {$serveruniq} \n");
	fwrite($fd, "openssl dhparam -out $ovpncapath/$caname/dh_params.dh $cakeysize \n");
	fclose($fd);
	system("/bin/chmod a+rx $ovpncapath/RUNME_2ND");
	mwexec("/bin/tcsh $ovpncapath/RUNME_2ND");
	$config['installedpackages']["openvpn$mode"]['config'][$id]['server.key'] = file_get_contents("$ovpncapath/$caname/server.key");
	$config['installedpackages']["openvpn$mode"]['config'][$id]['server.crt'] = file_get_contents("$ovpncapath/$caname/server.crt");
	$config['installedpackages']["openvpn$mode"]['config'][$id]['dh_params.dh'] = file_get_contents("$ovpncapath/$caname/dh_params.dh");
	config_unlock();
	write_config();	
	log_error("Server certificate for {$settings['description']} created.");
}

// Rewrite the settings
function openvpn_reconfigure($mode, $id) {
	global $g, $config;

	$settings = $config['installedpackages']["openvpn$mode"]['config'][$id];
	
	if (empty($settings))
		return;
	if ($settings['disable']) 
		return;

	/* create cert if needed */
	if(!$settings['server.key'] and $mode == "server") 
		openvpn_server_create_cert($mode, $id);

	$lport = 1194 + $id;

	/*
	 * NOTE: if you change the name of the interfaces here than
	 * be sure to change it even on the openvpn command parameters at
	 * openvpn_restart() function.
	 */

        if ($mode == "client") 
                $ovpndevice = "ovpnc{$id}";
        else
                $ovpndevice = "ovpn{$id}";

	if (!$g['booting'])
		mwexec("/sbin/ifconfig {$ovpndevice} destroy");

	$tunname = exec("/sbin/ifconfig tun create");
	mwexec("/sbin/ifconfig {$tunname} name {$ovpndevice}");
	mwexec("/sbin/ifconfig {$ovpndevice} group openvpn");

	$pidfile = $g['varrun_path'] . "/openvpn_{$mode}{$id}.pid";
	$proto = ($settings['protocol'] == 'UDP' ? 'udp' : "tcp-{$mode}");
	$cipher = $settings['crypto'];
	$openvpn_conf .= <<<EOD
dev {$ovpndevice}
dev-type tun
dev-node /dev/{$tunname}
writepid $pidfile
#user nobody
#group nobody
daemon
keepalive 10 60
ping-timer-rem
persist-tun
persist-key
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

        if ($settings['bind_to_iface'] == 'on') {
            $iface = $settings['interface'];
            $iface = convert_friendly_interface_to_real_interface_name($iface);
            $line = trim(shell_exec("ifconfig $iface | grep inet | grep -v inet6"));
            list($dummy, $ip, $dummy2, $dummy3) = explode(' ', $line);

            $openvpn_conf .= "local {$ip}\n";
        }

		// The port we'll listen at
		$openvpn_conf .= "lport {$settings['local_port']}\n";

		// DHCP-Options
	  	if (!empty($settings['dhcp_domainname'])) 
			$openvpn_conf .= "push \"dhcp-option DOMAIN {$settings['dhcp_domainname']}\"\n";

    	if (!empty($settings['dhcp_dns'])) {
	   		$servers = explode(';', $settings['dhcp_dns']);
   	  		if (is_array($servers)) {
			 	foreach ($servers as $server) $openvpn_conf .= "push \"dhcp-option DNS {$server}\"\n";
  	  		} else {
				$openvpn_conf .= "push \"dhcp-option DNS {$settings['dhcp_dns']}\"\n";
			}
    	}

		if (!empty($settings['dhcp_wins'])) {
	   		$servers = explode(';', $settings['dhcp_wins']);
   	  		if (is_array($servers)) {
				foreach ($servers as $server) $openvpn_conf .= "push \"dhcp-option WINS {$server}\"\n";
  	  		} else {
				$openvpn_conf .= "push \"dhcp-option WINS {$settings['dhcp_wins']}\"\n";
			}
    	}

    	if (!empty($settings['dhcp_nbdd'])) {
	   		$servers = explode(';', $settings['dhcp_nbdd']);
   	  		if (is_array($servers)) {
			 	foreach ($servers as $server) $openvpn_conf .= "push \"dhcp-option NBDD {$server}\"\n";
  	  		} else {
				$openvpn_conf .= "push \"dhcp-option NBDD {$settings['dhcp_nbdd']}\"\n";
		  	}
    	}

    	if (!empty($settings['dhcp_ntp'])) {
	   		$servers = explode(';', $settings['dhcp_ntp']);
   	  		if (is_array($servers)) {
				foreach ($servers as $server) $openvpn_conf .= "push \"dhcp-option NTP {$server}\"\n";
  	  		} else {
				$openvpn_conf .= "push \"dhcp-option NTP {$settings['dhcp_ntp']}\"\n";
			}
    	}

  		if (!empty($settings['dhcp_nbttype']) && $settings['dhcp_nbttype'] !=0)
 			$openvpn_conf .= "push \"dhcp-option NBT {$settings['dhcp_nbttype']}\"\n";
    	if (!empty($settings['dhcp_nbtscope'])) 
			$openvpn_conf .= "push \"dhcp-option NBS {$settings['dhcp_nbtscope']}\"\n";
		if ($settings['dhcp_nbtdisable'])
		 	$openvpn_conf .= "push \"dhcp-option DISABLE-NBT\"\n";

    	if (!empty($settings['tls'])) 
			$openvpn_conf .= "tls-auth {$g['varetc_path']}/openvpn_server{$id}.tls 0\n";
		if (!empty($settings['maxclients'])) 
			$openvpn_conf .= "max-clients {$settings['maxclients']}\n";
		if ($settings['gwredir']) 
			$openvpn_conf .= "push \"redirect-gateway def1\"\n";
	} else { // $mode == client
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

    if (!empty($settings['tls'])) $openvpn_conf .= "tls-auth {$g['varetc_path']}/openvpn_client{$id}.tls 1\n";

	}

	// Add the routes if they're set
	if (!empty($settings['remote_network'])) {
		list($ip, $mask) = explode('/', $settings['remote_network']);
		$mask = gen_subnet_mask($mask);
		$openvpn_conf .= "route $ip $mask\n";
	}

        // Write the settings for the keys
        // Set the keys up
        $base_file = $g['varetc_path'] . "/openvpn/certificates/";
        $keys = array();
        if ($settings['auth_method'] == 'shared_key')
                $keys[] = array('field' => 'shared.key', 'ext'  => 'secret', 'directive' => 'secret');
        else {
				$interface = $settings['interface'];
				if(!$interface) 
					$interface = "WAN";				
				$serveruniq =  $interface . $settings['local_port'] . $settings['protocol'];
                $keys[] = array('field' => 'ca.crt', 'directive' => 'ca');
                $keys[] = array('field' => "{$serveruniq}.crt", 'directive' => 'cert');
                $keys[] = array('field' => "{$serveruniq}.key", 'directive' => 'key');
                if ($mode == 'server')
                        $keys[] = array('field' => 'dh_params.dh', 'directive' => 'dh');
                if ($settings['crl'])
                        $keys[] = array('field' => 'crl.crl', 'directive' => 'crl-verify');
        }

        foreach ($keys as $key) {
                if ($mode == "server") {
			if ($settings['auth_method'] == 'pki' && isset($settings['cipherpki']) && 
				$settings['cipherpki'] != "none") 
                        	$openvpn_conf .= $key['directive'] . " " . $base_file . $settings['cipherpki'] . 
							"/".$key['field']."\n";
			else if ($settings['auth_method'] == 'pki' && isset($settings['cipherpki']) && 
				$settings['cipherpki'] != "none") 
                        	$openvpn_conf .= $key['directive'] . " " . $base_file . $settings['cipherpki'] . 
							"/".$key['field']."\n";
                } else {
                        $filename = $g['varetc_path']."/openvpn_{$mode}{$id}." . $key['field'];
                        file_put_contents($filename, base64_decode($settings[$key['field']]));
                        chown($filename, 'nobody');
                        chgrp($filename, 'nobody');
                        $openvpn_conf .= $key['directive'] . " $filename \n";
                }
        }

	if ($settings['use_lzo']) $openvpn_conf .= "comp-lzo\n";

	if ($settings['passtos']) $openvpn_conf .= "passtos\n";
	
	if ($settings['infiniteresolvretry']) $openvpn_conf .= "resolv-retry infinite\n";
	
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

// DHCP-Options
	  if (!empty($settings['dhcp_domainname'])) $conf .= "push \"dhcp-option DOMAIN {$settings['dhcp_domainname']}\"\n";

    if (!empty($settings['dhcp_dns'])) {
	   	$servers = explode(';', $settings['dhcp_dns']);
   	  if (is_array($servers)) {
			 foreach ($servers as $server) $conf .= "push \"dhcp-option DNS {$server}\"\n";
  	  }
		  else {
			$conf .= "push \"dhcp-option DNS {$settings['dhcp_dns']}\"\n";
		  }
    }

    if (!empty($settings['dhcp_wins'])) {
	   	$servers = explode(';', $settings['dhcp_wins']);
   	  if (is_array($servers)) {
			 foreach ($servers as $server) $conf .= "push \"dhcp-option WINS {$server}\"\n";
  	  }
		  else {
			$conf .= "push \"dhcp-option WINS {$settings['dhcp_wins']}\"\n";
		  }
    }

    if (!empty($settings['dhcp_nbdd'])) {
	   	$servers = explode(';', $settings['dhcp_nbdd']);
   	  if (is_array($servers)) {
			 foreach ($servers as $server) $conf .= "push \"dhcp-option NBDD {$server}\"\n";
  	  }
		  else {
			$conf .= "push \"dhcp-option NBDD {$settings['dhcp_nbdd']}\"\n";
		  }
    }

    if (!empty($settings['dhcp_ntp'])) {
	   	$servers = explode(';', $settings['dhcp_ntp']);
   	  if (is_array($servers)) {
			 foreach ($servers as $server) $conf .= "push \"dhcp-option NTP {$server}\"\n";
  	  }
		  else {
			$conf .= "push \"dhcp-option NTP {$settings['dhcp_ntp']}\"\n";
		  }
    }

  	if (!empty($settings['dhcp_nbttype']) && $settings['dhcp_nbttype'] !=0) $conf .= "push \"dhcp-option NBT {$settings['dhcp_nbttype']}\"\n";
    if (!empty($settings['dhcp_nbtscope'])) $conf .= "push \"dhcp-option NBS {$settings['dhcp_nbtscope']}\"\n";
   	if ($settings['dhcp_nbtdisable']) $conf .= "push \"dhcp-option DISABLE-NBT\"\n";
    if ($settings['gwredir']) $conf .= "push \"redirect-gateway def1\"\n";

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
	mwexec_bg("nohup openvpn --config $configfile");// --dev-type tun --dev-node /dev/tun{$id}");
	touch("{$g['tmp_path']}/filter_dirty");
}

//Make ciphers ready for openvpn
function openvpn_restore_all_ciphers() {
        global $config, $g;

        $ovpncapath = $g['varetc_path']."/openvpn/certificates";

        if (is_array($config['openvpn']['keys']) && count($config['openvpn']['keys'])) {
                if (!is_dir($g['varetc_path']."/openvpn"))
                        safe_mkdir($g['varetc_path']."/openvpn");
                if (!is_dir($ovpncapath))
                        safe_mkdir($ovpncapath);

                /* XXX: hardcoded path; worth making it a global?! */
                mwexec("cp -r /usr/local/share/openvpn/certificates ".$g['varetc_path']."/openvpn/");
                if (!is_dir($ovpncapath)) {
                        log_error("Failed to create environment for creating certificates. ");
                } else {

                        foreach ($config['openvpn']['keys'] as $caname => $ciphers) {
                                if (!is_dir("$ovpncapath/$caname"))
                                        safe_mkdir("$ovpncapath/$caname");

                                $cfg = "";
                                /* NOTE: vars; Do we need them restored?! */
                                $cfg .= "setenv KEY_SIZE " .$ciphers['keysize'] ."\n";
                                $cfg .= "setenv KEY_EXPIRE ".$ciphers['keyexpire'] ."\n";
                                $cfg .= "setenv CA_EXPIRE " .$ciphers['caexpire'] . "\n";
                                $cfg .= "setenv KEY_COUNTRY " .$ciphers['keycountry'] ."\n";
                                $cfg .= "setenv KEY_RPOVINCE " .$ciphers['keyprovince'] . "\n";
                                $cfg .= "setenv KEY_CITY " .$ciphers['keycity'] . "\n";
                                $cfg .= "setenv KEY_ORG " .$ciphers['keyorg'] . "\n";
                                $cfg .= "setenv KEY_EMAIL " .$ciphers['keyemail'] . "\n";
                                file_put_contents("$ovpncapath/$caname/vars", $cfg);
                                /* put ciphers back in their files */
                                foreach ($ciphers as $filename => $value) {
                                        file_put_contents("$ovpncapath/$caname/$filename", $value);
                                }
                        }
                }
        }
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
	$ovpncapath = $g['varetc_path']."/openvpn/certificates";

 	openvpn_restore_all_ciphers();

	foreach (array('server', 'client') as $mode) {
		if ($config['installedpackages']["openvpn$mode"]) {
			$cfgp =& $config['installedpackages']["openvpn$mode"];
			if (is_array($cfgp['config']) && count($cfgp['config'])) {
				foreach ($cfgp['config'] as $id => $settings)
					openvpn_resync($mode, $id);
			}
		}
	}

	openvpn_create_cscdir();
	if ($config['installedpackages']['openvpncsc']) {
		$cfgp =& $config['installedpackages']['openvpncsc'];
		if (is_array($cfgp['config']) && count($cfgp['config'])) {
			foreach ($cfgp['config'] as $id => $csc)
				openvpn_resync_csc($id);
		}
	}
	
	/* give speedy machines time to settle */
	sleep(5);

	/* reload the filter policy */
	filter_configure();

}

function openvpn_print_javascript($mode) {
	$javascript = <<<EOD
<script language="JavaScript">
//<!--
function onAuthMethodChanged() {
	var method = document.iform.auth_method;
	var endis = (method.options[method.selectedIndex].value == 'shared_key');

	if ('$mode' == 'server') {
	document.iform.nopool.disabled = endis;
	document.iform.local_network.disabled = endis;
	document.iform.client2client.disabled = endis;
	document.iform.maxclients.disabled = endis;
	document.iform.cipher.disabled = !endis;
	document.iform.cipherpki.disabled = endis;
	}
	else { // Client mode
		document.iform.remote_network.disabled = !endis;;
 	        document.iform['shared.key'].disabled = !endis;
    	   	document.iform['ca.crt'].disabled = endis;
        	document.iform["{$mode}.crt"].disabled = endis;
        	document.iform["{$mode}.key"].disabled = endis;
        	document.iform.tls.disabled = endis;
	}
}
//-->
</script>

EOD;
	print($javascript);
}


function openvpn_print_javascript2() {
	$javascript = <<<EOD
<script language="JavaScript">
//<!--
	onAuthMethodChanged();
//-->
</script>

EOD;
	print($javascript);
}

?>
