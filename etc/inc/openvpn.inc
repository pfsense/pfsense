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

function ovpn_configure($reconfigure) {
	global $config;
	if (is_array($config['ovpn']['server']))
		ovpn_config_server($reconfigure);
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
/*  Server related functions */
/*****************************/

function getnxt_server_if($type) {
	/* find the first available device of type $type */
	global $config;
	$a_server = $config['ovpn']['server']['tunnel'];
	$max = ($type == 'tun') ? 17 : 4;
	for ($i = 0; $i < $max ; $i++) {
		$hit = false;
		foreach ($a_server as $server) {
			if ($server['tun_iface'] == $type . $i) {
				$hit = true;
				break;
			}
		}
		if (!$hit)
			return $type . $i;
	}
	return false;
}

function getnxt_server_port() {
	/* Get first unused port */
	global $config;
	$a_server = $config['ovpn']['server']['tunnel'];
	$port = 1194;
	while (true) {
		$hit = false;
		foreach ($a_server as $server) {
			if ($server['port'] == $port) {
				$hit = true;
				break;
			}
		}
		if (!$hit)
			if (!ovpn_port_inuse_client($port))
				return $port;
		$port++;
	}
	return false; /* should never get here */
}

/* Configure the server */
function ovpn_config_server($reconfigure) {
	global $config, $g;

	foreach ($config['ovpn']['server']['tunnel'] as $id => $server) {
		/* get tunnel interface */
		$tun = $server['tun_iface'];
			
		/* kill any running openvpn daemon */
		killbypid($g['varrun_path']."/ovpn_srv_{$tun}.pid");

		if (isset($server['enable'])) {

			if ($g['booting'])
				echo "Starting OpenVPN server $id... ";

			/* send SIGUSR1 to running openvpn daemon */
			if ( $reconfigure == "true" && isset($server['dynip'])) {
				sigkillbypid($g['varrun_path']."/ovpn_srv_{$tun}.pid", "SIGUSR1");
				continue;
			}

			/* Remove old certs & keys */
			unlink_if_exists("{$g['vardb_path']}/ovpn_ca_cert_{$tun}.pem");
			unlink_if_exists("{$g['vardb_path']}/ovpn_srv_cert_{$tun}.pem");
			unlink_if_exists("{$g['vardb_path']}/ovpn_srv_key_{$tun}.pem");
			unlink_if_exists("{$g['vardb_path']}/ovpn_dh_{$tun}.pem");
			unlink_if_exists("{$g['vardb_path']}/ovpn_srv_psk_{$tun}.pem");
			unlink_if_exists("{$g['varetc_path']}/ovpn_srv_up_{$tun}.pem");
			unlink_if_exists("{$g['varetc_path']}/ovpn_cli_up_{$tun}.pem");

			/* Copy the TLS-Server certs & keys to disk */
			$fd = fopen("{$g['vardb_path']}/ovpn_ca_cert_{$tun}.pem", "w");
			if ($fd) {
				fwrite($fd, base64_decode($server['ca_cert'])."\n");
				fclose($fd);	
			}
			$fd = fopen("{$g['vardb_path']}/ovpn_srv_cert_{$tun}.pem", "w");
			if ($fd) {
				fwrite($fd, base64_decode($server['srv_cert'])."\n");
				fclose($fd);	
			}
			touch ("{$g['vardb_path']}/ovpn_srv_key_{$tun}.pem");
			chmod ("{$g['vardb_path']}/ovpn_srv_key_{$tun}.pem", 0600);
			$fd = fopen("{$g['vardb_path']}/ovpn_srv_key_{$tun}.pem", "w");
			if ($fd) {
				fwrite($fd, base64_decode($server['srv_key'])."\n");
				fclose($fd);	
			}
			$fd = fopen("{$g['vardb_path']}/ovpn_dh_{$tun}.pem", "w");
			if ($fd) {
				fwrite($fd, base64_decode($server['dh_param'])."\n");
				fclose($fd);	
			}

			touch ("{$g['vardb_path']}/ovpn_srv_psk_{$tun}.pem");
			chmod ("{$g['vardb_path']}/ovpn_srv_psk_{$tun}.pem", 0600);
			$fd = fopen("{$g['vardb_path']}/ovpn_srv_psk_{$tun}.pem", "w");
			if ($fd) {
				fwrite($fd, base64_decode($server['pre-shared-key'])."\n");
				fclose($fd);	
			}

			/* Start the openvpn daemon */
			mwexec("/usr/local/sbin/openvpn " . ovpn_srv_config_generate($id));

			if ($g['booting'])
				/* Send the boot message */
				echo "done\n";
		}
		else {
			if (!$g['booting']){
				/* stop any processes, unload the tap module */
				/* Remove old certs & keys */
				ovpn_server_kill($tun);

				if ($server['type'] == "tap")
					ovpn_unlink_tap();
			}
		}
	}
	return 0;
}

/* Kill off a running server process */
function ovpn_server_kill($tun) {
	global $g;
	
	killbypid("{$g['varrun_path']}/ovpn_srv_{$tun}.pid");

	/* Remove old certs & keys */
	unlink_if_exists("{$g['vardb_path']}/ovpn_ca_cert_{$tun}.pem");
	unlink_if_exists("{$g['vardb_path']}/ovpn_srv_cert_{$tun}.pem");
	unlink_if_exists("{$g['vardb_path']}/ovpn_srv_key_{$tun}.pem");
	unlink_if_exists("{$g['vardb_path']}/ovpn_dh_{$tun}.pem");
	unlink_if_exists("{$g['vardb_path']}/ovpn_srv_psk_{$tun}.pem");

	return 0;
}

/* Generate the config for a OpenVPN server */
function ovpn_srv_config_generate($id) {
	global $config, $g;
	$server = $config['ovpn']['server']['tunnel'][$id];

	/* get tunnel interface */
	$tun = $server['tun_iface'];

	/* get optional interface name */
	$iface = ovpn_get_opt_interface($tun);

	/* First the generic stuff:
		- We are a server
		- We are a TLS Server (for authentication)
		- We will run without privilege
	*/
	$ovpn_config = "--daemon --user nobody --group nobody --verb {$server['verb']} --persist-tun --persist-key --status /var/log/openvpn_{$tun}.log 60 ";

	/* pid file */
	$ovpn_config .= "--writepid {$g['varrun_path']}/ovpn_srv_{$tun}.pid ";
	
	/* interface */
	$ovpn_config .= "--dev {$server['tun_iface']} ";
	
	/* port */
	$ovpn_config .= "--port {$server['port']} ";

	/* Set protocol being used (p = udp (default), tcp-server)
	if ($server['proto'] == 'tcp') {
		$ovpn_config .= "--proto tcp-server ";
	}
	
	/* Interface binding - 1 or all */
	if ($server['bind_iface'] != 'all') {
		if ($ipaddr = ovpn_get_ip($server['bind_iface']))
			$ovpn_config .= "--local $ipaddr ";
		else
			return "Interface bridged";
	}

	/* are we using dynamic ip addresses? */
	if (isset($server['dynip']))
		$ovpn_config .= "--persist-remote-ip ";
	
	/* Client to client routing (off by default) */
	if (isset($server['cli2cli']))
		$ovpn_config .= "--client-to-client ";
	
	/* Set maximum simultaneous clients */
	$ovpn_config .= "--max-clients {$server['maxcli']} ";
	 
	/* bridging enabled? */
	if (($ifname = $config['interfaces'][$iface]['bridge']) && $server['type'] == "tap") {
		$gateway = $config['interfaces'][$ifname]['ipaddr'];
		$netmask = gen_subnet_mask($config['interfaces'][$ifname]['subnet']);
		$poolstart = $server['ipblock'];
		$poolend = gen_subnet_max($server['ipblock'], $server['prefix']);

		$ovpn_config .= "--server-bridge $gateway $netmask $poolstart $poolend ";

		$lastdigits = substr($tun, 3) + 2;
		$ovpn_srv_up = "/sbin/ifconfig " . $tun . " 127.0.0." . $lastdigits . "/32\n";

		$fd = fopen("{$g['varetc_path']}/ovpn_srv_up_{$tun}.sh", "w");
		if ($fd) {
			fwrite($fd, $ovpn_srv_up);
			fclose($fd);	
			chmod ("{$g['varetc_path']}/ovpn_srv_up_{$tun}.sh", 0755);
			$ovpn_config .= "--up /var/etc/ovpn_srv_up_{$tun}.sh ";
		}
	} else {
		/* New --server macro simplifies config */
		$netmask = gen_subnet_mask($server['prefix']);

		$ovpn_config .= "--server {$server['ipblock']} {$netmask} ";
	}
	
	/* TLS-Server params */
	$ovpn_config .= "--ca {$g['vardb_path']}/ovpn_ca_cert_{$tun}.pem ";
	$ovpn_config .= "--cert {$g['vardb_path']}/ovpn_srv_cert_{$tun}.pem ";
	$ovpn_config .= "--key {$g['vardb_path']}/ovpn_srv_key_{$tun}.pem ";
	$ovpn_config .= "--dh {$g['vardb_path']}/ovpn_dh_{$tun}.pem ";
	
	/* TLS auth */
	if (isset($server['tlsauth']))
		$ovpn_config .= "--tls-auth {$g['vardb_path']}/ovpn_srv_psk_{$tun}.pem 0 ";
	
	/* Data channel encryption cipher*/
	$ovpn_config .= "--cipher {$server['crypto']} ";
	
	/* Duplicate CNs */
	if (isset($server['dupcn']))
		$ovpn_config .= "--duplicate-cn ";
		
	/* Client push - redirect gateway */
	if (isset($server['psh_options']['redir'])){
		if (isset($server['psh_options']['redir_loc']))
			$ovpn_config .= "--push \"redirect-gateway local\" ";
		else
			$ovpn_config .= "--push \"redirect-gateway\" ";
	}
			
	/* Client push - route delay */
	if (isset($server['psh_options']['rte_delay']))
		$ovpn_config .= "--push \"route-delay {$server['psh_options']['rte_delay_int']}\" ";
		
	/* Client push - ping (note we set both server and client) */
	if (isset ($server['psh_options']['ping'])){
		$conflict = true;
		$interval = $server['psh_options']['ping_int'];
		$ovpn_config .= "--ping {$server['psh_options']['ping_int']} ";
		$ovpn_config .= "--push \"ping {$server['psh_options']['ping_int']}\" ";
	}
	
	/* Client push - ping-restart (note server uses 2 x client interval) */
	if (isset ($server['psh_options']['pingrst'])){
		$conflict = true;
		$interval = $server['psh_options']['pingrst_int'];
		$ovpn_config .= "--ping-restart " . ($interval * 2) . " ";
		$ovpn_config .= "--push \"ping-restart $interval\" ";
	}
	
	/* Client push - ping-exit (set on client) */
	if (isset ($server['psh_options']['pingexit'])){
		$conflict = true;
		$ovpn_config .= "--ping-exit {$server['psh_options']['pingexit_int']} ";
		$ovpn_config .= "--push \"ping-exit {$server['psh_options']['pingexit_int']}\" ";
	}
	
	/* Client push - inactive (set on client) */
	if (isset ($server['psh_options']['inact'])){
		$ovpn_config .= "--inactive {$server['psh_options']['inact_int']} ";
		$ovpn_config .= "--push \"inactive {$server['psh_options']['inact_int']}\" ";
	}
	
	if (!isset($conflict))
		$ovpn_config .= "--keepalive 10 60 ";

	//trigger_error("OVPN: $ovpn_config", E_USER_NOTICE);
	return $ovpn_config;
}

/* Define an OVPN Server tunnel interface in the interfaces array and assign a name */
function ovpn_server_iface(){
	global $config, $g;
	
	foreach ($config['ovpn']['server']['tunnel'] as $id => $server) {
		if (isset($server['enable'])) {

			/* get tunnel interface */
			$tun = $server['tun_iface'];
			
			$i = 1;
			while (true) {
				$ifname = 'opt' . $i;
				if (is_array($config['interfaces'][$ifname])) {
					if ((isset($config['interfaces'][$ifname]['ovpn']))
					     && ($config['interfaces'][$ifname]['ovpn'] == "server_{$tun}"))
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
			$config['interfaces'][$ifname]['descr'] = strtoupper($ifname);
			$config['interfaces'][$ifname]['if'] = $server['tun_iface'];
			$config['interfaces'][$ifname]['ipaddr'] = long2ip( ip2long($server['ipblock']) + 1);
			$config['interfaces'][$ifname]['subnet'] = $server['prefix'];
			$config['interfaces'][$ifname]['enable'] = isset($server['enable']) ? true : false;
			$config['interfaces'][$ifname]['ovpn'] = "server_{$tun}";

			write_config();
		}
	}
	return "OpenVPN server interface defined";
}

/* Delete a server interface definition */
function ovpn_server_iface_del($tun) {
	global $config;

	for ($i = 1; is_array($config['interfaces']['opt' . $i]); $i++) {
		$ifname = 'opt' . $i;
		if ((isset($config['interfaces'][$ifname]['ovpn']))
		     && ($config['interfaces'][$ifname]['if'] == "$tun")) {
			unset($config['interfaces'][$ifname]);
			break;
		}
	}


	/* shift down other OPTn interfaces to get rid of holes */
	$i++;

	/* look at the following OPTn ports */
	while (is_array($config['interfaces']['opt' . $i])) {
		$config['interfaces']['opt' . ($i - 1)] =
			$config['interfaces']['opt' . $i];

		unset($config['interfaces']['opt' . $i]);
		$i++;
	}
}


/****************************/
/* Client related functions */
/****************************/

function getnxt_client_if($type) {
	/* find the first available device of type $type */
	global $config;
	$max = ($type == 'tun') ? 17 : 4;
	for ($i = 0; $i < $max; $i++) {
		$hit = false;
		foreach ($a_client as $client) {
			if ($client['if'] == $type . $i) {
				$hit = true;
				break;
			}
		}
		if (!$hit)
			return $type . $i;
	}
        return false;
}

function getnxt_client_port() {
        /* Get first unused port */
	global $config;
	$a_client = $config['ovpn']['client']['tunnel'];
	$port = 1194;
	while (true) {
		$hit = false;
		foreach ($a_client as $client) {
			if ($client['port'] == $port) {
				$hit = true;
				break;
			}
		}
		if (!$hit)
			if (!ovpn_port_inuse_server($port))
				return $port;
		$port++;
	}
	return false; /* should never get here */
}

/* Port in use */
function ovpn_port_inuse_client($port){
	global $config;
	$a_client = $config['ovpn']['client']['tunnel'];
	foreach ($a_client as $client) {
		if ($client['port'] == $port) {
			return true;
		}
	}
	return false;
}

function ovpn_config_client() {
	/* Boot time configuration */
	global $config, $g;
	
	foreach ($config['ovpn']['client']['tunnel'] as $id => $client) {

		/* get tunnel interface */
		$tun = $client['if'];

		/* kill any running openvpn daemon */
		killbypid($g['varrun_path']."/ovpn_cli_{$tun}.pid");

		if (isset($client['enable'])) {
	
			if ($g['booting'])
				echo "Starting OpenVPN client $id... ";
		
			/* Remove old certs & keys */
			unlink_if_exists("{$g['vardb_path']}/ovpn_cli_ca_cert_{$tun}.pem");
			unlink_if_exists("{$g['vardb_path']}/ovpn_cli_cert_{$tun}.pem");
			unlink_if_exists("{$g['vardb_path']}/ovpn_cli_key_{$tun}.pem");
			unlink_if_exists("{$g['vardb_path']}/ovpn_cli_psk_{$tun}.pem");
			unlink_if_exists("{$g['varetc_path']}/ovpn_cli_up_{$tun}.pem");
		
			/* Copy the TLS-Client certs & keys to disk */
			$fd = fopen("{$g['vardb_path']}/ovpn_cli_ca_cert_{$tun}.pem", "w");
			if ($fd) {
				fwrite($fd, base64_decode($client['ca_cert'])."\n");
				fclose($fd);	
			}
			else
				trigger_error("OVPN: No open for CA", E_USER_NOTICE);
			$fd = fopen("{$g['vardb_path']}/ovpn_cli_cert_{$tun}.pem", "w");
			if ($fd) {
				fwrite($fd, base64_decode($client['cli_cert'])."\n");
				fclose($fd);	
			}
			touch ("{$g['vardb_path']}/ovpn_cli_key_{$tun}.pem");
			chmod ("{$g['vardb_path']}/ovpn_cli_key_{$tun}.pem", 0600);
			$fd = fopen("{$g['vardb_path']}/ovpn_cli_key_{$tun}.pem", "w");
			if ($fd) {
				fwrite($fd, base64_decode($client['cli_key'])."\n");
				fclose($fd);	
			}
			touch ("{$g['vardb_path']}/ovpn_cli_psk_{$tun}.pem");
			chmod ("{$g['vardb_path']}/ovpn_cli_psk_{$tun}.pem", 0600);
			$fd = fopen("{$g['vardb_path']}/ovpn_cli_psk_{$tun}.pem", "w");
			if ($fd) {
				fwrite($fd, base64_decode($client['pre-shared-key'])."\n");
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
				ovpn_client_kill($tun);

				if ($client['type'] == "tap")
					ovpn_unlink_tap();
			}
		}
	}
	return 0;
	
}

/* Kill off a running client process */
function ovpn_client_kill($tun) {
	global $g;
	
	killbypid("{$g['varrun_path']}/ovpn_cli_{$tun}.pid");
	
	/* Remove old certs & keys */
	unlink_if_exists("{$g['vardb_path']}/ovpn_cli_ca_cert_{$tun}.pem");
	unlink_if_exists("{$g['vardb_path']}/ovpn_cli_cert_{$tun}.pem");
	unlink_if_exists("{$g['vardb_path']}/ovpn_cli_key_{$tun}.pem");
	unlink_if_exists("{$g['vardb_path']}/ovpn_cli_psk_{$tun}.pem");

	return 0;
}

/* Generate the config for a OpenVPN client */
function ovpn_cli_config_generate($id) {
	/* configure the named client */
	global $config, $g;
	$client = $config['ovpn']['client']['tunnel'][$id];

	/* get tunnel interface */
	$tun = $client['if'];
	
	/* get optional interface name */
	$iface = ovpn_get_opt_interface($tun);

	/* Client support in 2.0 is very simple */
	$ovpn_config = "--client --daemon --verb 1 --status /var/log/openvpn_{$tun}.log 60 ";
	
	/* pid file */
	$ovpn_config .= "--writepid {$g['varrun_path']}/ovpn_cli_{$tun}.pid ";
	
	/* interface */
	$ovpn_config .= "--dev {$client['if']} ";
	
	/* protocol */
	/* Set protocol being used (p = udp (default), tcp-client)
	if ($client['proto'] == 'tcp') {
		$ovpn_config .= "--proto tcp-client ";
	}
	
	/* port */
	$ovpn_config .= "--lport {$client['port']} ";
	
	/* server location */
	$ovpn_config .= "--remote {$client['saddr']} {$client['sport']} ";
	
	/* bridging enabled? */
	if (($ifname = $config['interfaces'][$iface]['bridge']) && $client['type'] == "tap") {
		$lastdigits = substr($tun, 3) + 2;
		$ovpn_srv_up = "/sbin/ifconfig " . $tun . " 127.0.0." . $lastdigits . "/32\n";

		$fd = fopen("{$g['varetc_path']}/ovpn_cli_up_{$tun}.sh", "w");
		if ($fd) {
			fwrite($fd, $ovpn_cli_up);
			fclose($fd);    
			chmod ("{$g['varetc_path']}/ovpn_cli_up_{$tun}.sh", 0755);
			$ovpn_config .= "--up /var/etc/ovpn_cli_up_{$tun}.sh ";
		}
	}

	/* TLS-Client params */
	$ovpn_config .= "--ca {$g['vardb_path']}/ovpn_cli_ca_cert_{$tun}.pem ";
	$ovpn_config .= "--cert {$g['vardb_path']}/ovpn_cli_cert_{$tun}.pem ";
	$ovpn_config .= "--key {$g['vardb_path']}/ovpn_cli_key_{$tun}.pem ";

	/* TLS auth */
	if (isset($client['tlsauth']))
		$ovpn_config .= "--tls-auth {$g['vardb_path']}/ovpn_cli_psk_{$tun}.pem 1 ";

	/* Data channel encryption cipher*/
	$ovpn_config .= "--cipher {$client['crypto']} ";
	
	//trigger_error("OVPN: $ovpn_config", E_USER_NOTICE);
	return $ovpn_config;
}

/* Define an OVPN tunnel interface in the interfaces array for each client */
function ovpn_client_iface(){
	global $config;
		
	foreach ($config['ovpn']['client']['tunnel'] as $id => $client) {
		if (isset($client['enable'])) {

			/* get tunnel interface */
			$tun = $client['if'];

			$i = 1;
			while (true) {
				$ifname = 'opt' . $i;
				if (is_array($config['interfaces'][$ifname])) {
					if ((isset($config['interfaces'][$ifname]['ovpn']))
			     		     && ($config['interfaces'][$ifname]['ovpn'] == "client_{$tun}"))
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
			$config['interfaces'][$ifname]['descr'] = strtoupper($ifname);
			$config['interfaces'][$ifname]['if'] = $client['if'];
			$config['interfaces'][$ifname]['ipaddr'] = "0.0.0.0";
			$config['interfaces'][$ifname]['subnet'] = "0";
			$config['interfaces'][$ifname]['enable'] = isset($client['enable']) ? true : false;
			$config['interfaces'][$ifname]['ovpn'] = "client_{$tun}";
			write_config();
		}
	}
	return "OpenVPN client interfaces defined";
}

/* Delete a client interface definition */
function ovpn_client_iface_del($tun) {
	global $config;

	for ($i = 1; is_array($config['interfaces']['opt' . $i]); $i++) {
		$ifname = 'opt' . $i;
		if ((isset($config['interfaces'][$ifname]['ovpn']))
		     && ($config['interfaces'][$ifname]['if'] == "$tun")) {
			unset($config['interfaces'][$ifname]);
			break;
		}
	}


	/* shift down other OPTn interfaces to get rid of holes */
	$i++;

	/* look at the following OPTn ports */
	while (is_array($config['interfaces']['opt' . $i])) {
		$config['interfaces']['opt' . ($i - 1)] =
			$config['interfaces']['opt' . $i];

		unset($config['interfaces']['opt' . $i]);
		$i++;
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

/* Port in use */
function ovpn_port_inuse_server($port){
	global $config;
	$a_server = $config['ovpn']['server']['tunnel'];
	foreach ($a_server as $server) {
		if ($server['port'] == $port) {
			return true;
		}
	}
	return false;
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
		
	
/* Get optional interface */
/* needs tunneling interface (tun0, tun1, tap0, ...) */
/* returns optional interface name (opt2, opt3, ...) */
function ovpn_get_opt_interface($tun){
	global $config;

	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
		$ifname = 'opt' . $i;

		if (isset($config['interfaces']['opt' . $i]['ovpn']))
			if ($config['interfaces'][$ifname]['if'] == "$tun")
				 return $ifname;
	}
	/* not found? */
	return false;
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
		if ($fd = fopen($lockfile, "x")) {
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
