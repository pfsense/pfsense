<?php
/*
	openvpn.inc
	
	Copyright (C) 2004 Peter Curran (peter@closeconsultants.com).
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
	
/* include all configuration functions */
require_once("globals.inc");
require_once("config.inc");
require_once("functions.inc");

function ovpn_configure() {
	global $config;
	if (is_array($config['ovpn']['server']))
		ovpn_config_server();
	if (is_array($config['ovpn']['client']))
		ovpn_config_client();
	return;
}

function ovpn_link_tap() {
	/* Add a reference to the tap KLM.  If ref count = 1, load it */
	global $g;
	
	if (!is_file($g['vardb_path'] ."/ovpn_tap_link")){
		$link_count = 1;
		mwexec("/sbin/kldload if_tap");
		$fd = fopen($g['vardb_path'] ."/ovpn_tap_link", 'w');
	}
	else {
		$fd = fopen($g['vardb_path'] ."/ovpn_tap_link", 'r+');
		$link_count = fread($fd);
		$link_count ++;
	}
	fwrite($fd, $link_count);
	fclose($fd);
	return true;
}

function ovpn_unlink_tap() {
	/* Remove a reference to the tap KLM.  If ref count = 0, unload it */
	global $g;
	
	if (!is_file($g['vardb_path'] ."/ovpn_tap_link"))
		return false;  //no file, no links so why are we called?
		
	$fd = fopen($g['vardb_path'] ."/ovpn_tap_link", 'r+');
	$link_count = fread($fd);
	$link_count --;
	fwrite($fd, $link_count);
	fclose($fd);
		
	if ($link_count == 0)
		mwexec("/sbin/kldunload if_tap");
	return true;
}

/*****************************/	
/*  Server-related functions */

/* Configure the server */
function ovpn_config_server() {
	global $config, $g;
	
	if (isset($config['ovpn']['server']['enable'])) {
	
		if ($g['booting'])
			echo "Starting OpenVPN server... ";
		
		/* kill any running openvpn daemon */
		killbypid($g['varrun_path']."/ovpn_srv.pid");
		
		/* Remove old certs & keys */
		unlink_if_exists("{$g['vardb_path']}/ovpn_ca_cert.pem");
		unlink_if_exists("{$g['vardb_path']}/ovpn_srv_cert.pem");
		unlink_if_exists("{$g['vardb_path']}/ovpn_srv_key.pem");
		unlink_if_exists("{$g['vardb_path']}/ovpn_dh.pem");
		
		/* Copy the TLS-Server certs & keys to disk */
		$fd = @fopen("{$g['vardb_path']}/ovpn_ca_cert.pem", "w");
		if ($fd) {
			fwrite($fd, base64_decode($config['ovpn']['server']['ca_cert'])."\n");
			fclose($fd);	
		}
		$fd = @fopen("{$g['vardb_path']}/ovpn_srv_cert.pem", "w");
		if ($fd) {
			fwrite($fd, base64_decode($config['ovpn']['server']['srv_cert'])."\n");
			fclose($fd);	
		}
		$fd = @fopen("{$g['vardb_path']}/ovpn_srv_key.pem", "w");
		if ($fd) {
			fwrite($fd, base64_decode($config['ovpn']['server']['srv_key'])."\n");
			fclose($fd);	
		}
		$fd = @fopen("{$g['vardb_path']}/ovpn_dh.pem", "w");
		if ($fd) {
			fwrite($fd, base64_decode($config['ovpn']['server']['dh_param'])."\n");
			fclose($fd);	
		}
		
		/* Start the openvpn daemon */
		mwexec("/usr/local/sbin/openvpn " . ovpn_srv_config_generate());
		
		if ($g['booting'])
			/* Send the boot message */
			echo "done\n";
	}
	else {
		if (!$g['booting']){
			/* stop any processes, unload the tap module */
			/* Remove old certs & keys */
			unlink_if_exists("{$g['vardb_path']}/ovpn_ca_cert.pem");
			unlink_if_exists("{$g['vardb_path']}/ovpn_srv_cert.pem");
			unlink_if_exists("{$g['vardb_path']}/ovpn_srv_key.pem");
			unlink_if_exists("{$g['vardb_path']}/ovpn_dh.pem");
			killbypid("{$g['varrun_path']}/ovpn_srv.pid");
			if ($config['ovpn']['server']['tun_iface'] == 'tap0')
				ovpn_unlink_tap();
		}
	}
	return 0;
}

/* Generate the config for a OpenVPN server */
function ovpn_srv_config_generate() {
	global $config, $g;
	$server = $config['ovpn']['server'];
	
	/* First the generic stuff:
		- We are a server
		- We are a TLS Server (for authentication)
		- We will run without privilege
	*/
	$ovpn_config = "--daemon --user nobody --group nobody --verb {$server['verb']} ";
	
	/* pid file */
	$ovpn_config .= "--writepid {$g['varrun_path']}/ovpn_srv.pid ";
	
	/* interface */
	$ovpn_config .= "--dev {$server['tun_iface']} ";
	
	/* port */
	$ovpn_config .= "--port {$server['port']} ";
	
	/* Interface binding - 1 or all */
	if ($server['bind_iface'] != 'all') {
		if ($ipaddr = ovpn_get_ip($server['bind_iface']))
			$ovpn_config .= "--local $ipaddr ";
		else
			return "Interface bridged";
		
	}
		
	/* Client to client routing (off by default) */
	if (isset($server['cli2cli']))
		$ovpn_config .= "--client-to-client ";
	
	/* Set maximum simultaneous clients */
	$ovpn_config .= "--max-clients {$server['maxcli']} ";
	 
	/* New --server macro simplifies config */
	$mask = ovpn_calc_mask($server['prefix']);
	$ovpn_config .= "--server {$server['ipblock']} {$mask} ";
	
	/* TLS-Server params */
	$ovpn_config .= "--ca {$g['vardb_path']}/ovpn_ca_cert.pem ";
	$ovpn_config .= "--cert {$g['vardb_path']}/ovpn_srv_cert.pem ";
	$ovpn_config .= "--key {$g['vardb_path']}/ovpn_srv_key.pem ";
	$ovpn_config .= "--dh {$g['vardb_path']}/ovpn_dh.pem ";
	
	/* Data channel encryption cipher*/
	$ovpn_config .= "--cipher {$server['crypto']} ";
	
	/* Duplicate CNs */
	if (isset($server['dupcn']))
		$ovpn_config .= "--duplicate-cn ";
		
	/* Client push - redirect gateway */
	if (isset($server['psh_options']['redir'])){
		if (isset($server['psh_options']['redir_loc']))
			$ovpn_config .= "--push \"redirect-gateway 'local'\" ";
		else
			$ovpn_config .= "--push \"redirect-gateway\" ";
	}
			
	/* Client push - route delay */
	if (isset($server['psh_options']['rte_delay']))
		$ovpn_config .= "--push \"route-delay {$server['psh_options']['rte_delay']}\" ";
		
	/* Client push - ping (note we set both server and client) */
	if (isset ($server['psh_options']['ping'])){
		$ovpn_config .= "--ping {$server['psh_options']['ping']} ";
		$ovpn_config .= "--push \"ping {$server['psh_options']['ping']}\" ";
	}
	
	/* Client push - ping-restart (note server uses 2 x client interval) */
	if (isset ($server['psh_options']['pingrst'])){
		$interval = $server['psh_options']['pingrst'];
		$ovpn_config .= "--ping-restart " . ($interval * 2) . " ";
		$ovpn_config .= "--push \"ping-restart $interval\" ";
	}
	
	/* Client push - ping-exit (set on client) */
	if (isset ($server['psh_options']['pingexit'])){
		$ovpn_config .= "--ping-exit {$server['psh_options']['pingexit']} ";
		$ovpn_config .= "--push \"ping-exit {$server['psh_options']['pingexit']}\" ";
	}
	
	/* Client push - inactive (set on client) */
	if (isset ($server['psh_options']['inact'])){
		$ovpn_config .= "--inactive {$server['psh_options']['pingexit']} ";
		$ovpn_config .= "--push \"inactive {$server['psh_options']['inact']}\" ";
	}
	
	//trigger_error("OVPN: $ovpn_config", E_USER_NOTICE);
	return $ovpn_config;
}

/* Define an OVPN Server tunnel interface in the interfaces array and assign a name */
function ovpn_server_iface(){
	global $config, $g;
	
	$i = 1;
	while (true) {
		$ifname = 'opt' . $i;
		if (is_array($config['interfaces'][$ifname])) {
			if ((isset($config['interfaces'][$ifname]['ovpn']))
			     && ($config['interfaces'][$ifname]['ovpn'] == 'server'))
				/* Already an interface defined - overwrite */
				break;
		}
		else {
			/* No existing entry, this is first unused */
			$config['interfaces'][$ifname] = array();
			break;
		}
		$i++;
	}
	$config['interfaces'][$ifname]['descr'] = "OVPN server";
	$config['interfaces'][$ifname]['if'] = $config['ovpn']['server']['tun_iface'];
	$config['interfaces'][$ifname]['ipaddr'] = long2ip( ip2long($config['ovpn']['server']['ipblock']) + 1);
	$config['interfaces'][$ifname]['subnet'] = $config['ovpn']['server']['prefix'];
	$config['interfaces'][$ifname]['enable'] = isset($config['ovpn']['server']['enable']) ? true : false;
	$config['interfaces'][$ifname]['ovpn'] = 'server';
			
	write_config();
	
	return "OpenVPN server interface defined";
}

/********************************************************/
/* Client related functions */
function ovpn_config_client() {
	/* Boot time configuration */
	global $config, $g;
	
	foreach ($config['ovpn']['client']['tunnel'] as $id => $client) {
		if (isset($client['enable'])) {
	
			if ($g['booting'])
				echo "Starting OpenVPN client $id... ";
		
			/* kill any running openvpn daemon */
			killbypid("{$g['varrun_path']}/ovpn_client{$id}.pid");
		
			/* Remove old certs & keys */
			unlink_if_exists("{$g['vardb_path']}/ovpn_ca_cert_{$id}.pem");
			unlink_if_exists("{$g['vardb_path']}/ovpn_cli_cert_{$id}.pem");
			unlink_if_exists("{$g['vardb_path']}/ovpn_cli_key_{$id}.pem");
		
			/* Copy the TLS-Client certs & keys to disk */
			/*$fd = @fopen("{$g['vardb_path']}/ovpn_ca_cert_{$id}.pem", "w");*/
			$fd = fopen("{$g['vardb_path']}/ovpn_ca_cert_{$id}.pem", "w");
			if ($fd) {
				fwrite($fd, base64_decode($client['ca_cert'])."\n");
				fclose($fd);	
			}
			else
				trigger_error("OVPN: No open for CA", E_USER_NOTICE);
			$fd = fopen($g['vardb_path']."/ovpn_cli_cert_".$id.".pem", "w");
			if ($fd) {
				fwrite($fd, base64_decode($client['cli_cert'])."\n");
				fclose($fd);	
			}
			$fd = fopen($g['vardb_path']."/ovpn_cli_key_".$id.".pem", "w");
			if ($fd) {
				fwrite($fd, base64_decode($client['cli_key'])."\n");
				fclose($fd);	
			}
				
			/* Start openvpn for this client */
			mwexec("/usr/local/sbin/openvpn " . ovpn_cli_config_generate($id));
		
			if ($g['booting'])
				/* Send the boot message */
				echo "done\n";
		}
		else {
			if (!$g['booting']){
				/* stop any processes, unload the tap module */
				/* Remove old certs & keys */
				unlink_if_exists("{$g['vardb_path']}/ovpn_ca_cert_{$id}.pem");
				unlink_if_exists("{$g['vardb_path']}/ovpn_cli_cert_{$id}.pem");
				unlink_if_exists("{$g['vardb_path']}/ovpn_cli_key_{$id}.pem");
				killbypid("{$g['varrun_path']}/ovpn_client{$id}.pid");
				if ($client['type'] == "tap")
					ovpn_unlink_tap();
			}
		}
	}
	return 0;
	
}

/* Kill off a running client process */
function ovpn_client_kill($id) {
	global $g;
	
	killbypid("{$g['varrun_path']}/ovpn_client{$id}.pid");
	return 0;
}

function ovpn_cli_config_generate($id) {
	/* configure the named client */
	global $config, $g;
	$client = $config['ovpn']['client']['tunnel'];
	
	/* Client support in 2.0 is very simple */
	
	$ovpn_config = "--client --daemon --verb 1 ";
	
	/* pid file */
	$ovpn_config .= "--writepid {$g['varrun_path']}/ovpn_client{$id}.pid ";
	
	/* interface */
	$ovpn_config .= "--dev {$client[$id]['if']} ";
	
	/* protocol */
	$ovpn_config .= "--proto {$client[$id]['proto']} ";
	
	/* port */
	$ovpn_config .= "--lport {$client[$id]['cport']} ";
	
	/* server location */
	$ovpn_config .= "--remote {$client[$id]['saddr']} {$client[$id]['sport']} ";
	
	/* TLS-Server params */
	$ovpn_config .= "--ca {$g['vardb_path']}/ovpn_ca_cert_{$id}.pem ";
	$ovpn_config .= "--cert {$g['vardb_path']}/ovpn_cli_cert_{$id}.pem ";
	$ovpn_config .= "--key {$g['vardb_path']}/ovpn_cli_key_{$id}.pem ";
		
	/* Data channel encryption cipher*/
	$ovpn_config .= "--cipher {$client[$id]['crypto']} ";
	
	//trigger_error("OVPN: $ovpn_config", E_USER_NOTICE);
	return $ovpn_config;
}

/* Define an OVPN tunnel interface in the interfaces array for each client */
function ovpn_client_iface(){
	global $config;
		
	foreach ($config['ovpn']['client']['tunnel'] as $id => $client) {
		if (isset($client['enable'])) {
			$i = 1;
			while (true) {
				$ifname = 'opt' . $i;
				if (is_array($config['interfaces'][$ifname])) {
					if ((isset($config['interfaces'][$ifname]['ovpn']))
			     		     && ($config['interfaces'][$ifname]['ovpn'] == "client{$id}"))
						/* Already an interface defined - overwrite */
						break;
				}
				else {
					/* No existing entry, this is first unused */
					$config['interfaces'][$ifname] = array();
					break;
				}
				$i++;
			}
			if (isset($client['descr']))
				$config['interfaces'][$ifname]['descr'] = $client['descr'];
			else
				$config['interfaces'][$ifname]['descr'] = "OVPN client-{$id}";
			$config['interfaces'][$ifname]['if'] = $client['if'];
			$config['interfaces'][$ifname]['ipaddr'] = "0.0.0.0";
			$config['interfaces'][$ifname]['subnet'] = "0";
			$config['interfaces'][$ifname]['enable'] = isset($client['enable']) ? true : false;
			$config['interfaces'][$ifname]['ovpn'] = "client{$id}";
			write_config();
		}
	}
	return "OpenVPN client interfaces defined";
}

/* Delete a client interface definition */
function ovpn_client_iface_del($id) {
	global $config;
	
	$i = 1;
	while (true) {
		$ifname = 'opt' . $i;
		if (is_array($config['interfaces'][$ifname])) {
			if ((isset($config['interfaces'][$ifname]['ovpn']))
			     && ($config['interfaces'][$ifname]['ovpn'] == "client{$id}"))
			     unset($config['interfaces'][$ifname]);
		}
	}
}

/******************/
/* Misc functions */

/* Calculate the last address in a range given the start and /prefix */
function ovpn_calc_end($start, $prefix){

	$first = ip2long($start);
	$last = pow(2,(32 - $prefix)) - 1 + $first;
	return long2ip($last);
}

/* Calculate a mask given a /prefix */
function ovpn_calc_mask($prefix){

	return long2ip(ip2long("255.255.255.255") - (pow( 2, (32 - $prefix)) - 1));
}

/* Read in a file from the $_FILES array */
function ovpn_get_file($file){
	global $g;
	
	if (!is_uploaded_file($_FILES[$file]['tmp_name'])){
		trigger_error("Bad file upload".$_FILES[$file]['error'], E_USER_NOTICE);
		return NULL;
	}
	$contents = file_get_contents($_FILES[$file]['tmp_name']);
	return $contents;
}


/* Get the IP address of a specified interface */
function ovpn_get_ip($iface){
	global $config;
	
	if ($iface == 'wan')
		return get_current_wan_address();
		
	if ($config['interfaces'][$iface]['bridge'])
		/* No bridging (yet) */
		return false;
	return $config['interfaces'][$iface]['ipaddr'];
}
	
/* Get a list of the cipher options supported by OpenVPN */
function ovpn_get_cipher_list(){
	
/*	exec("/usr/local/sbin/openvpn --show-ciphers", $raw);
	print_r ($raw);
	
	$ciphers = preg_grep('/ bit default key /', $raw);
	
	for($i = 0; $i <count($ciphers); $i++){
		$tmp = explode(' ',$ciphers[$i]);
		$cipher_list["$tmp[0]"] = "{$tmp[0]} ({$tmp[1]} {$tmp[2]})";
	}
*/
	$cipher_list = array('DES-CBC' => 'DES-CBC (64 bit)',
			     'RC2-CBC' => 'RC2-CBC (128 bit)',
			     'DES-EDE-CBC' => 'DES-EDE-CBC (128 bit)',
			     'DES-EDE3-CBC' => 'DES-EDE3-CBC (192 bit)',
			     'DESX-CBC' => 'DESX-CBC (192 bit)',
			     'BF-CBC' => 'BF-CBC (128 bit)',
			     'RC2-40-CBC' => 'RC2-40-CBC (40 bit)',
			     'CAST5-CBC' => 'CAST5-CBC (128 bit)',
			     'RC5-CBC' => 'RC5-CBC (128 bit)',
			     'RC2-64-CBC' => 'RC2-64-CBC (64 bit)',
			     'AES-128-CBC' => 'AES-128-CBC (128 bit)',
			     'AES-192-CBC' => 'AES-192-CBC (192 bit)',
			     'AES-256-CBC' => 'AES-256-CBC (256 bit)');
	return $cipher_list;
}
		
	
/* Build a list of the current real interfaces */
function ovpn_real_interface_list(){
	global $config;
	
	$interfaces = array('all' => 'ALL',
			    'lan' => 'LAN',
			    'wan' => 'WAN');
	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
		if (isset($config['interfaces']['opt' . $i]['ovpn']))
			/* Hide our own interface */
			break;
		if (isset($config['interfaces']['opt' . $i]['enable']))
			$interfaces['opt' . $i] = $config['interfaces']['opt' . $i]['descr'];
	}
	return $interfaces;
}

	
/* lock openvpn information, decide that the lock file is stale after
   10 seconds */
function ovpn_lock() {
	
	global $g;
	
	$lockfile = "{$g['varrun_path']}/ovpn.lock";
	
	$n = 0;
	while ($n < 10) {
		/* open the lock file in append mode to avoid race condition */
		if ($fd = @fopen($lockfile, "x")) {
			/* succeeded */
			fclose($fd);
			return;
		} else {
			/* file locked, wait and try again */
			sleep(1);
			$n++;
		}
	}
}

/* unlock configuration file */
function ovpn_unlock() {
	
	global $g;
	
	$lockfile = "{$g['varrun_path']}/ovpn.lock";
	
	if (file_exists($lockfile))
		unlink($lockfile);
}

?>
