<?php
/*
	openvpn.inc
	
	Copyright (C) 2004 Peter Curran (peter@closeconsultants.com).
	Copyright (C) 2005 Peter Allgeyer (allgeyer@web.de).
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
		ovpn_server_crl_add();
		ovpn_server_ccd_add();
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
	//else {
	//	$fd = fopen($g['vardb_path'] ."/ovpn_tap_link", 'r+');
	//	$link_count = fread($fd, filesize($g['vardb_path'] ."/ovpn_tap_link"));
	//	$link_count ++;
	//}
	//fwrite($fd, $link_count);
	//fclose($fd);
	return true;
}

function ovpn_unlink_tap() {
	/* Remove a reference to the tap KLM.  If ref count = 0, unload it */
	global $g;
	
	if (!is_file($g['vardb_path'] ."/ovpn_tap_link"))
		return false;  //no file, no links so why are we called?
		
	$fd = fopen($g['vardb_path'] ."/ovpn_tap_link", 'r+');
	$link_count = fread($fd, filesize($g['vardb_path'] ."/ovpn_tap_link"));
	$link_count--;
	fwrite($fd, $link_count);
	fclose($fd);
		
	if ($link_count == 0)
		mwexec("/sbin/kldunload if_tap");
	return true;
}

/*****************************/	
/*  Server related functions */
/*****************************/

/* Configure the server */
function ovpn_config_server($reconfigure) {
	global $config, $g, $d_ovpnsrvdirty_path;

	if(!is_array($config['ovpn']['server']['tunnel']))
		return;
	foreach ($config['ovpn']['server']['tunnel'] as $id => $server) {
		/* get tunnel interface */
		$tun = $server['tun_iface'];
			
		if (isset($server['enable'])) {

			if ($g['booting']) {
				echo "Starting OpenVPN server $id... ";

				/* define configuration options */
				ovpn_srv_config_generate($id);

				/* Start the openvpn daemon */
				mwexec("/usr/local/sbin/openvpn {$g['varetc_path']}/ovpn_srv_{$tun}.conf");

				/* Send the boot message */
				echo "done\n";

				/* next server */
				continue;
			}

			/* send SIGUSR1 to running openvpn daemon */
			if ( $reconfigure == "true" && isset($server['dynip'])) {
				sigkillbypid($g['varrun_path']."/ovpn_srv_{$tun}.pid", "SIGUSR1");
				continue;
			}

			/* read dirtyfile */
			if (is_readable($d_ovpnsrvdirty_path))
				$lines = file($d_ovpnsrvdirty_path);

			/* reconfigure server */
			if (is_array($lines) && in_array($tun, $lines)) {

				/* kill running server */
				ovpn_server_kill($tun);

				/* remove old certs & keys */
				ovpn_server_certs_del($tun);

				/* define configuration options */
				ovpn_srv_config_generate($id);
			}

			/* Start the openvpn daemon */
			if (!is_readable("{$g['varrun_path']}/ovpn_srv_{$tun}.pid"))
				mwexec("/usr/local/sbin/openvpn {$g['varetc_path']}/ovpn_srv_{$tun}.conf");

			/* next server */
			continue;
		}

		/* server disabled */
		if (!$g['booting']) {
			/* kill running server */
			ovpn_server_kill($tun);

			/* Remove old certs & keys */
			ovpn_server_certs_del($tun);

			/* stop any processes, unload the tap module */
			//if ($server['type'] == "tap")
			//	ovpn_unlink_tap();
		}
	}
	return 0;
}

/* Kill off a running server process */
function ovpn_server_certs_del($tun) {
	global $g;
	
	/* Remove old certs & keys */
	unlink_if_exists("{$g['vardb_path']}/ovpn_ca_cert_{$tun}.pem");
	unlink_if_exists("{$g['vardb_path']}/ovpn_srv_cert_{$tun}.pem");
	unlink_if_exists("{$g['vardb_path']}/ovpn_srv_key_{$tun}.pem");
	unlink_if_exists("{$g['vardb_path']}/ovpn_dh_{$tun}.pem");
	unlink_if_exists("{$g['vardb_path']}/ovpn_srv_psk_{$tun}.pem");
	unlink_if_exists("{$g['varetc_path']}/ovpn_srv_up_{$tun}.sh");
	unlink_if_exists("{$g['varetc_path']}/ovpn_srv_{$tun}.conf");

	return 0;
}

/* Kill off a running server process */
function ovpn_server_kill($tun) {
	global $g;

	/* kill running server */
	killbypid("{$g['varrun_path']}/ovpn_srv_{$tun}.pid");
}

/* Generate the config for a OpenVPN server */
function ovpn_srv_config_generate($id) {
	global $config, $g;
	$server = $config['ovpn']['server']['tunnel'][$id];

	/* get tunnel interface */
	$tun = $server['tun_iface'];

	/* get optional interface name */
	$iface = ovpn_get_opt_interface($tun);

	/* Copy the TLS-Server certs & keys to disk */
	if ($server['authentication_method'] != "pre_shared_key" ) {

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
	}

	if ($server['authentication_method'] == "pre_shared_key" || isset($server['tlsauth'])) {
		touch ("{$g['vardb_path']}/ovpn_srv_psk_{$tun}.pem");
		chmod ("{$g['vardb_path']}/ovpn_srv_psk_{$tun}.pem", 0600);
		$fd = fopen("{$g['vardb_path']}/ovpn_srv_psk_{$tun}.pem", "w");
		if ($fd) {
			fwrite($fd, base64_decode($server['pre-shared-key'])."\n");
			fclose($fd);	
		}
	}

	$fd = fopen("{$g['varetc_path']}/ovpn_srv_{$tun}.conf", "w");
	if (!$fd) {
		printf("Error: cannot open ovpn_srv_{$tun}.conf in ovpn_srv_config_generate($id).\n");
		return 1;
	}

	/* First the generic stuff:
		- We are a server
		- We will run without privilege
	*/
	$ovpn_config = "";
	$ovpn_config .= <<<EOD
daemon
user nobody
group nobody
verb {$server['verb']}
persist-tun
persist-key
status /var/log/openvpn_{$tun}.log 60
writepid {$g['varrun_path']}/ovpn_srv_{$tun}.pid
dev {$server['tun_iface']}
port {$server['port']}
cipher {$server['crypto']}

EOD;

	/* Set protocol being used (p = udp (default), tcp-server) */
	if ($server['proto'] == "tcp")
		$ovpn_config .= "proto tcp-server\n";

	/* Interface binding - 1 or all */
	if ($server['bind_iface'] != 'all')
		if ($ipaddr = ovpn_get_ip($server['bind_iface']))
			$ovpn_config .= "local {$ipaddr}\n";

	/* are we using dynamic ip addresses? */
	if (isset($server['dynip']))
		$ovpn_config .= "persist-remote-ip\n";

	/* Client to client routing (off by default) */
	if (isset($server['cli2cli']))
		$ovpn_config .= "client-to-client\n";

	/* Limit server to a maximum of n concurrent clients. */
	if (!empty($server['maxcli']))
		$ovpn_config .= "max-clients {$server['maxcli']}\n";

	/* Authentication method */
	if ($server['authentication_method'] != "pre_shared_key") {

		$ovpn_config .= <<<EOD
client-config-dir {$g['vardb_path']}/ccd
ca {$g['vardb_path']}/ovpn_ca_cert_{$tun}.pem
cert {$g['vardb_path']}/ovpn_srv_cert_{$tun}.pem
key {$g['vardb_path']}/ovpn_srv_key_{$tun}.pem
dh {$g['vardb_path']}/ovpn_dh_{$tun}.pem

EOD;

		/* CRL list */
		if (isset($server['crlname']) &&
		    is_readable("{$g['vardb_path']}/{$server['crlname']}.crl.pem")) {
			$ovpn_config .= "crl-verify {$g['vardb_path']}/{$server['crlname']}.crl.pem\n";
		}

		/* TLS auth */
		if (isset($server['tlsauth']))
			$ovpn_config .= "tls-auth {$g['vardb_path']}/ovpn_srv_psk_{$tun}.pem 0\n";

		/* bridging enabled? */
		if ($server['bridge'] && $server['type'] == "tap") {
			if ($server['method'] == "ovpn") {
				$netmask = gen_subnet_mask($config['interfaces'][$server['bridge']]['subnet']);
				$ovpn_config .= "server-bridge {$server['gateway']} {$netmask} {$server['range_from']} {$server['range_to']}\n";
			} else {
				$ovpn_config .= <<<EOD
mode server
tls-server

EOD;
			}

			$lastdigits = substr($tun, 3) + 2;
			$ovpn_srv_up = "/sbin/ifconfig " . $tun . " 127.0.0." . $lastdigits . "/32\n";

			$fdo = fopen("{$g['varetc_path']}/ovpn_srv_up_{$tun}.sh", "w");
			if ($fdo) {
				fwrite($fdo, $ovpn_srv_up);
				fclose($fdo);	
				chmod ("{$g['varetc_path']}/ovpn_srv_up_{$tun}.sh", 0755);
				$ovpn_config .= "up /var/etc/ovpn_srv_up_{$tun}.sh\n";
			}

		} else {
			/* Not bridged, can be tun or tap, doesn't matter */
			$netmask = gen_subnet_mask($server['prefix']);

			if ($server['method'] == "ovpn") {
				/* new --server macro simplifies config */
				$ovpn_config .= "server {$server['ipblock']} {$netmask}\n";
			} else {
				$ovpn_config .= <<<EOD
mode server
tls-server
ifconfig {$server['ipblock']} {$netmask}

EOD;
			}
		} /* end bridging */

		/* Duplicate CNs */
		if (isset($server['dupcn']))
			$ovpn_config .= "duplicate-cn\n";

	} else {
		/* 'authentication_method' == "pre_shared_key" */
		$network = gen_subnet($server['lipaddr'], $server['netmask']);
		$netmask = gen_subnet_mask($server['netmask']);

		$ovpn_config .= "secret {$g['vardb_path']}/ovpn_srv_psk_{$tun}.pem 0\n";

		if (strstr($server['type'], "tun")) {
			$ovpn_config .= "ifconfig {$server['lipaddr']} {$server['ripaddr']}\n";
			$ovpn_config .= "route {$network} {$netmask}\n";
		} else {
			$ovpn_config .= "ifconfig {$server['lipaddr']} {$netmask}\n";
		}

		if (isset($server['client-to-client']))
			$ovpn_config .= "push \"route {$network} {$netmask}\"\n";
		else
			$ovpn_config .= "push \"route {$server['lipaddr']}\"\n";

	} /* end authentication_method */

	$push_options = "";
	
	/* Client push - redirect gateway */
	if (isset($server['psh_options']['redir']))  {
		if (isset($server['psh_options']['redir_loc']))
			$push_config .= "push \"redirect-gateway local\"\n";
		else
			$push_config .= "push \"redirect-gateway\"\n";
		if ($server['method'] != "ovpn")
			$push_config .= "push \"route-gateway {$server['ipblock']}\"\n";
	}

	/* Client push - route delay */
	if (isset($server['psh_options']['rte_delay']))
		$push_config .= "push \"route-delay {$server['psh_options']['rte_delay_int']}\"\n";

	/* Client push - ping (note we set both server and client) */
	if (isset ($server['psh_options']['ping'])){
		$conflict = true;
		$interval = $server['psh_options']['ping_int'];
		$ovpn_config .= "ping {$server['psh_options']['ping_int']}\n ";
		$push_config .= "push \"ping {$server['psh_options']['ping_int']}\"\n";
	}

	/* Client push - ping-restart (note server uses 2 x client interval) */
	if (isset ($server['psh_options']['pingrst'])){
		$conflict = true;
		$interval = $server['psh_options']['pingrst_int'];
		$ovpn_config .= "ping-restart " . ($interval * 2) . "\n";
		$push_config .= "push \"ping-restart $interval\"\n";
	}

	/* Client push - ping-exit (set on client) */
	if (isset ($server['psh_options']['pingexit'])){
		$conflict = true;
		$ovpn_config .= "ping-exit {$server['psh_options']['pingexit_int']}\n";
		$push_config .= "push \"ping-exit {$server['psh_options']['pingexit_int']}\"\n";
	}

	/* Client push - inactive (set on client) */
	if (isset ($server['psh_options']['inact'])){
		$ovpn_config .= "inactive {$server['psh_options']['inact_int']}\n";
		$push_config .= "push \"inactive {$server['psh_options']['inact_int']}\"\n";
	}

	if (isset($push_config))
		$ovpn_config .= $push_config;

	if (!isset($conflict))
		$ovpn_config .= "keepalive 10 60\n";

	/* Expert mode paramters */
	if (isset($server['expertmode_enabled']) && is_array($server['expertmode'])) {
		$ovpn_config .= ";begin expertmode\n";
		foreach ($server['expertmode']['option'] as $option) {
			$ovpn_config .= "{$option}\n";
		}
		$ovpn_config .= ";end expertmode\n";
	}

	fwrite($fd, $ovpn_config);
	fclose($fd);

	//trigger_error("OVPN: $ovpn_config", E_USER_NOTICE);
}

/* Define an OVPN Server tunnel interface in the interfaces array and assign a name */
function ovpn_server_iface(){
	global $config, $g;

	unset($filter_configure);
	unset($bridge_configure);
	
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
				} else {

					/* No existing entry, this is first unused */
					$config['interfaces'][$ifname] = array();

					/* add new filter rules */
					$filter_configure = true;
					break;
				}
				$i++;
			}
			$config['interfaces'][$ifname]['descr'] = strtoupper($server['tun_iface']);
			$config['interfaces'][$ifname]['if'] = $server['tun_iface'];
			if ($server['method'] == "ovpn")
				$config['interfaces'][$ifname]['ipaddr'] = long2ip( ip2long($server['ipblock']) + 1);
			else
				$config['interfaces'][$ifname]['ipaddr'] = $server['ipblock'];
			if (isset($server['bridge'])) {
				$config['interfaces'][$ifname]['bridge'] = $server['bridge'];
				$bridge_configure = true;
			} else if (isset($config['interfaces'][$ifname]['bridge'])) {
				/* bridge config removed */
				unset ($config['interfaces'][$ifname]['bridge']);
				$bridge_configure = true;
			}
			$config['interfaces'][$ifname]['subnet'] = $server['prefix'];
			$config['interfaces'][$ifname]['enable'] = isset($server['enable']) ? true : false;
			$config['interfaces'][$ifname]['ovpn'] = "server_{$tun}";

			write_config();
		}
	}

	/* do we have to reconfigure filter rules? */
	if (isset($bridge_configure))
		interfaces_optional_configure();
	else if (isset($filter_configure))
		filter_configure();

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

	/* reconfigure filter rules */
	interfaces_optional_configure();
}

/* Add client config file */
function ovpn_server_ccd_add() {
	global $config, $g;

	if (is_array($config['ovpn']['server']['ccd'])) {
		foreach ($config['ovpn']['server']['ccd'] as $id => $server) {
			/* define configuration options */
			ovpn_server_ccd_generate($id);
		}
	}
}


/* Construct client config file */
function ovpn_server_ccd_generate($id) {
	global $config, $g;
	$ovpnccd = $config['ovpn']['server']['ccd'][$id];

	$cn = $ovpnccd['cn'];
	$ccd_config = "";
        $push_options = "";
        
	/* Push reset */
	if (!isset($ovpnccd['disable']) && isset($ovpnccd['psh_reset']))  {
		$ccd_config .= "push-reset\n";

		/* Client push - redirect gateway */
		if (isset($ovpnccd['psh_options']['redir']))  {
			if (isset($ovpnccd['psh_options']['redir_loc']))
				$push_config .= "push \"redirect-gateway local\"\n";
			else
				$push_config .= "push \"redirect-gateway\"\n";
		}

		/* Client push - route delay */
		if (isset($ovpnccd['psh_options']['rte_delay']))
			$push_config .= "push \"route-delay {$ovpnccd['psh_options']['rte_delay_int']}\"\n";

		/* Client push - ping (note we set both server and client) */
		if (isset ($ovpnccd['psh_options']['ping'])){
			$ccd_config .= "ping {$server['psh_options']['ping_int']}\n ";
			$push_config .= "push \"ping {$ovpnccd['psh_options']['ping_int']}\"\n";
		}

		/* Client push - ping-restart (note server uses 2 x client interval) */
		if (isset ($ovpnccd['psh_options']['pingrst'])){
			$interval = $ovpnccd['psh_options']['pingrst_int'];
			$ccd_config .= "ping-restart " . ($interval * 2) . "\n";
			$push_config .= "push \"ping-restart $interval\"\n";
		}

		/* Client push - ping-exit (set on client) */
		if (isset ($ovpnccd['psh_options']['pingexit'])){
			$ccd_config .= "ping-exit {$ovpnccd['psh_options']['pingexit_int']}\n";
			$push_config .= "push \"ping-exit {$ovpnccd['psh_options']['pingexit_int']}\"\n";
		}

		/* Client push - inactive (set on client) */
		if (isset ($ovpnccd['psh_options']['inact'])){
			$ccd_config .= "inactive {$ovpnccd['psh_options']['inact_int']}\n";
			$push_config .= "push \"inactive {$ovpnccd['psh_options']['inact_int']}\"\n";
		}

		if (isset($push_config))
			$ccd_config .= $push_config;
	}

        if (!isset($ovpnccd['disable']) && is_array($ovpnccd['options'])) {
                foreach ($ovpnccd['options']['option'] as $option) {
                        $ccd_config .= "{$option}\n";
                }
        }

	/* Disable client from connecting */
	if (isset($ovpnccd['disable']))
		$ccd_config = "disable\n";

	unlink_if_exists("{$g['vardb_path']}/ccd/{$cn}");

	if (isset($ccd_config) && isset($ovpnccd['enable'])) {
		$fd = fopen("{$g['vardb_path']}/ccd/{$cn}", "w");
		if ($fd) {
			fwrite($fd, $ccd_config."\n");
			fclose($fd);    
		}
	}
}

/* Delete client config file */
function ovpn_server_ccd_del($cn) {
	global $g;
	
	unlink_if_exists("{$g['vardb_path']}/ccd/{$cn}");
	return 0;
}

/* Add CRL file */
function ovpn_server_crl_add() {
	global $config, $g, $d_ovpncrldirty_path;

	if (is_array($config['ovpn']['server']['crl'])) {
		foreach ($config['ovpn']['server']['crl'] as $id => $crlent) {
			/* get crl file name */
			$name = $crlent['crlname'];

			if (isset($crlent['enable'])) {
			
				/* add file */
				ovpn_server_crl_generate($id);

				if ($g['booting']) {
					/* next crl file */
					continue;
				}
				
				/* read dirtyfile */
				if (is_readable($d_ovpncrldirty_path))
					$lines = file($d_ovpncrldirty_path);

				/* reconfigure crl file */
				if (is_array($lines) && in_array($name, $lines)) {

					/* restart running openvpn daemon */
					foreach ($config['ovpn']['server']['tunnel'] as $id => $server) {
						$tun = $server['tun_iface'];

						if ($server['enable'] &&
						    isset($server['crlname']) && $server['crlname'] == $name)
							/* kill running server */
							ovpn_server_kill($tun);

							/* Start the openvpn daemon */
							mwexec("/usr/local/sbin/openvpn {$g['varetc_path']}/ovpn_srv_{$tun}.conf");
					}

				}
			
				/* next crl file */
				continue;
			}
			
			/* crl file disabled: remove file */
			ovpn_server_crl_del($name);
		}
	}
	return 0;
}

/* Write CRL to file */
function ovpn_server_crl_generate($id) {
	global $config, $g;

	$ovpncrl = $config['ovpn']['server']['crl'][$id];

	$fd = fopen("{$g['vardb_path']}/{$ovpncrl['crlname']}.crl.pem", "w");
	if ($fd) {
		fwrite($fd, base64_decode($ovpncrl['crl_list'])."\n");
		fclose($fd);
	}
}

/* Delete CRL file */
function ovpn_server_crl_del($name) {
	global $config, $g;

	/* have to wipe out the crl from the server config */
	foreach ($config['ovpn']['server']['tunnel'] as $id => $server) {
		if (isset($server['crlname']) && $server['crlname'] == $name) {

			/* get tunnel interface */
			$tun = $server['tun_iface'];

			/* remove crl file entry */
			unset($config['ovpn']['server']['tunnel'][$id]['crlname']);
			write_config();
			
			/* kill running server */
			ovpn_server_kill($tun);

			/* remove old certs & keys */
			ovpn_server_certs_del($tun);
			
			/* reconfigure daemon */
			ovpn_srv_config_generate($id);

			/* Start the openvpn daemon */
			mwexec("/usr/local/sbin/openvpn {$g['varetc_path']}/ovpn_srv_{$tun}.conf");
		}
	}

	unlink_if_exists("{$g['vardb_path']}/{$name}.crl.pem");
	return 0;
}


/* Get a list of crl files */
function ovpn_get_crl_list() {
	global $config;

	$crl_list = array();

	if (is_array($config['ovpn']['server']['crl'])) {
		foreach ($config['ovpn']['server']['crl'] as $crlent) {
			if (isset($crlent['enable']))
				$crl_list[] = $crlent['crlname'];
		}
	}
	return $crl_list;
}

/* append interface to $_ovpnsrvdirty_path */
function ovpn_srv_dirty($tun) {
	global $d_ovpnsrvdirty_path;
	
	$fd = fopen($d_ovpnsrvdirty_path, 'a');
	if ($fd) {
		fwrite($fd, $tun);
		fclose($fd);
	}
}

/* append file name to $_ovpncrldirty_path */
function ovpn_crl_dirty($name) {
	global $d_ovpncrldirty_path;
	
	$fd = fopen($d_ovpncrldirty_path, 'a');
	if ($fd) {
		fwrite($fd, $name);
		fclose($fd);
	}
}


/****************************/
/* Client related functions */
/****************************/

function ovpn_config_client() {
	/* Boot time configuration */
	global $config, $g, $d_ovpnclidirty_path;;
	
	foreach ($config['ovpn']['client']['tunnel'] as $id => $client) {

		/* get tunnel interface */
		$tun = $client['if'];

		if (isset($client['enable'])) {

			if ($g['booting']) {
				echo "Starting OpenVPN client $id... ";
		
				/* define configuration options */
				ovpn_cli_config_generate($id);

				/* Start openvpn for this client */
				mwexec("/usr/local/sbin/openvpn {$g['varetc_path']}/ovpn_cli_{$tun}.conf");
		
				/* Send the boot message */
				echo "done\n";

				/* next client */
				continue;
			}

			/* read dirtyfile */
			if (is_readable($d_ovpnclidirty_path))
				$lines = file($d_ovpnclidirty_path);

			/* reconfigure client */
			if (is_array($lines) && in_array($tun, $lines)) {

				/* kill running client */
				ovpn_client_kill($tun);

				/* remove old certs & keys */
				ovpn_client_certs_del($tun);

				/* define configuration options */
				ovpn_cli_config_generate($id);
			}

			/* Start the openvpn daemon */
			if (!is_readable("{$g['varrun_path']}/ovpn_cli_{$tun}.pid"))
				mwexec("/usr/local/sbin/openvpn {$g['varetc_path']}/ovpn_cli_{$tun}.conf");

			/* next client */
			continue;
		}

		/* client disabled */
		if (!$g['booting']) {
			/* kill running client */
			ovpn_client_kill($tun);
			
			/* remove old certs & keys */
			ovpn_client_certs_del($tun);

			/* stop any processes, unload the tap module */
			//if ($client['type'] == "tap")
			//	ovpn_unlink_tap();
		}
	}
	return 0;
}

/* Kill off a running client process */
function ovpn_client_certs_del($tun) {
	global $g;
	
	/* Remove old certs & keys */
	unlink_if_exists("{$g['vardb_path']}/ovpn_cli_ca_cert_{$tun}.pem");
	unlink_if_exists("{$g['vardb_path']}/ovpn_cli_cert_{$tun}.pem");
	unlink_if_exists("{$g['vardb_path']}/ovpn_cli_key_{$tun}.pem");
	unlink_if_exists("{$g['vardb_path']}/ovpn_cli_psk_{$tun}.pem");
	unlink_if_exists("{$g['varetc_path']}/ovpn_cli_up_{$tun}.pem");
	unlink_if_exists("{$g['varetc_path']}/ovpn_cli_{$tun}.conf");

	return 0;
}

/* Kill off a running client process */
function ovpn_client_kill($tun) {
	global $g;

	/* kill running client */
	killbypid("{$g['varrun_path']}/ovpn_cli_{$tun}.pid");
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

	/* Copy the TLS-Client certs & keys to disk */
	if ($client['authentication_method'] != "pre_shared_key" ) {

		$fd = fopen("{$g['vardb_path']}/ovpn_cli_ca_cert_{$tun}.pem", "w");
		if ($fd) {
			fwrite($fd, base64_decode($client['ca_cert'])."\n");
			fclose($fd);	
		}

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
	}

	if ($client['authentication_method'] == "pre_shared_key" || isset($client['tlsauth'])) {
		touch ("{$g['vardb_path']}/ovpn_cli_psk_{$tun}.pem");
		chmod ("{$g['vardb_path']}/ovpn_cli_psk_{$tun}.pem", 0600);
		$fd = fopen("{$g['vardb_path']}/ovpn_cli_psk_{$tun}.pem", "w");
		if ($fd) {
			fwrite($fd, base64_decode($client['pre-shared-key'])."\n");
			fclose($fd);	
		}
	}

	$fd = fopen("{$g['varetc_path']}/ovpn_cli_{$tun}.conf", "w");
	if (!$fd) {
		printf("Error: cannot open ovpn_cli_{$tun}.conf in ovpn_cli_config_generate($id).\n");
		return 1;
	}

	/* Client support in 2.0 is very simple */
	$ovpn_config = "";
	$ovpn_config .= <<<EOD
daemon
verb 1
status /var/log/openvpn_{$tun}.log 60
writepid {$g['varrun_path']}/ovpn_cli_{$tun}.pid
dev {$client['if']}
lport {$client['cport']}
remote {$client['saddr']} {$client['sport']}
cipher {$client['crypto']}

EOD;

	/* Version 1.0 compatibility; http://openvpn.net/compat.html */
	if ($client['ver'] != "2") {
		$ovpn_config .= <<<EOD
key-method 1
tun-mtu 1500
tun-mtu-extra 32
mssfix 1450

EOD;
	}

	/* Set protocol being used (p = udp (default), tcp-client) */
	if ($client['proto'] == "tcp")
		$ovpn_config .= "proto tcp-client\n";

	/* TLS-Client params */
	if ($client['authentication_method'] != "pre_shared_key") {
		$ovpn_config .= <<<EOD
ca {$g['vardb_path']}/ovpn_cli_ca_cert_{$tun}.pem
cert {$g['vardb_path']}/ovpn_cli_cert_{$tun}.pem
key {$g['vardb_path']}/ovpn_cli_key_{$tun}.pem

EOD;

		if (isset($client['pull']))
			$ovpn_config .= "client\n";
		else
			$ovpn_config .= "tls-client\n";

		/* TLS auth */
		if (isset($client['ns_cert_type']))
			$ovpn_config .= "ns-cert-type server\n";

		/* TLS auth */
		if (isset($client['tlsauth']))
			$ovpn_config .= "tls-auth {$g['vardb_path']}/ovpn_cli_psk_{$tun}.pem 1\n";

		/* bridging enabled? */
		if ($client['bridge'] && $client['type'] == "tap") {
			$lastdigits = substr($tun, 3) + 2;
			$ovpn_cli_up = "/sbin/ifconfig " . $tun . " 127.0.0." . $lastdigits . "/32\n";

			$fdo = fopen("{$g['varetc_path']}/ovpn_cli_up_{$tun}.sh", "w");
			if ($fdo) {
				fwrite($fdo, $ovpn_cli_up);
				fclose($fdo);   
				chmod ("{$g['varetc_path']}/ovpn_cli_up_{$tun}.sh", 0755);
				$ovpn_config .= "up /var/etc/ovpn_cli_up_{$tun}.sh\n";
			}
		}

	} else {
		/* 'authentication_method' == "pre_shared_key" */
		$ovpn_config .= "secret {$g['vardb_path']}/ovpn_cli_psk_{$tun}.pem 0\n";

		$network = gen_subnet($client['lipaddr'], $client['netmask']);
		$netmask = gen_subnet_mask($client['netmask']);

		if (strstr($client['type'], "tap"))
			$ovpn_config .= "ifconfig {$client['lipaddr']} {$netmask}\n";
		else
			$ovpn_config .= "ifconfig {$client['lipaddr']} {$client['ripaddr']}\n";

	} /* end authentication_method */

	/* Expert mode paramters */
	if (isset($client['expertmode_enabled']) && is_array($client['expertmode'])) {
		$ovpn_config .= ";begin expertmode\n";
		foreach ($client['expertmode']['option'] as $option) {
			$ovpn_config .= "{$option}\n";
		}
		$ovpn_config .= ";end expertmode\n";
	}

	fwrite($fd, $ovpn_config);
	fclose($fd);

	/* trigger_error("OVPN: $ovpn_config", E_USER_NOTICE); */
}

/* Define an OVPN tunnel interface in the interfaces array for each client */
function ovpn_client_iface(){
	global $config;
		
	unset($filter_configure);
	unset($bridge_configure);

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
				} else {

					/* No existing entry, this is first unused */
					$config['interfaces'][$ifname] = array();

					/* add new filter rules */
					$filter_configure = true;
					break;
				}
				$i++;
			}
			$config['interfaces'][$ifname]['descr'] = strtoupper($client['if']);
			$config['interfaces'][$ifname]['if'] = $client['if'];
			$config['interfaces'][$ifname]['ipaddr'] = "0.0.0.0";
			$config['interfaces'][$ifname]['subnet'] = "0";
			if (isset($client['bridge'])) {
				$config['interfaces'][$ifname]['bridge'] = $client['bridge'];
				$bridge_configure = true;
			} else if (isset($config['interfaces'][$ifname]['bridge'])) {
				/* bridge config removed */
				unset ($config['interfaces'][$ifname]['bridge']);
				$bridge_configure = true;
			}
			$config['interfaces'][$ifname]['enable'] = isset($client['enable']) ? true : false;
			$config['interfaces'][$ifname]['ovpn'] = "client_{$tun}";
			write_config();
		}
	}

	/* do we have to reconfigure filter rules? */
	if (isset($bridge_configure))
		interfaces_optional_configure();
	else if (isset($filter_configure))
		filter_configure();

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

	/* reconfigure filter rules */
	interfaces_optional_configure();
}

/* append interface to ovpndirty_path */
function ovpn_cli_dirty($tun) {
	global $d_ovpnclidirty_path;
	
	$fd = fopen($d_ovpnclidirty_path, 'a');
	if ($fd) {
		fwrite($fd, $tun);
		fclose($fd);
	}
}


/******************/
/* Misc functions */
/******************/

/* find the first available device of type $type */
function getnxt_if($type) {
	global $config;

	/* initialize variables */
	$iface_list	= array();
	$max		= ($type == 'tun') ? 17 : 4;

	/* construct list of valid interfaces */
	for ($i = 0; $i < $max ; $i++)
		array_push($iface_list, $type . $i);
	
	/* delete interface in use from the list */
	if ($a_server = $config['ovpn']['server']['tunnel']) {
		foreach ($a_server as $server) {
			$entry = array();
			array_push($entry, $server['tun_iface']);
			$iface_list = array_diff($iface_list, $entry);
		}
	}

	/* same for list of client tunnels  */
	if ($a_client = $config['ovpn']['client']['tunnel']) {
		foreach ($a_client as $client) {
			$entry = array();
			array_push($entry, $client['if']);
			$iface_list = array_diff($iface_list, $entry);
		}
	}
			
	/* return first element of list, if list of interfaces isn't empty */
	if (count($iface_list))
		return array_shift($iface_list);
	else
		return false;
}

/* find the next best available port */
function getnxt_port() {

	/* construct list of valid ports */
	$port_list = free_port_list();

        /* return first element of list, if list of ports isn't empty */
        if (count($port_list))
                return array_shift($port_list);
        else
                return false;
}

/* construct list of free ports */
function free_port_list() {
	global $config;

	/* initialize variables */
	$port_list	= array();
	$first_port 	= 1194;
	$max		= $first_port + 21;

	for ($i = $first_port; $i < $max; $i++)
		array_push($port_list, $i);
        
        /* delete port in use from the list */
        if ($a_server = $config['ovpn']['server']['tunnel']) {
                foreach ($a_server as $server) {
                        $entry = array();
                        array_push($entry, $server['port']);
                        $port_list = array_diff($port_list, $entry);
                }
        }

        /* same for list of client tunnels  */
        if ($a_client = $config['ovpn']['client']['tunnel']) {
                foreach ($a_client as $client) {
                        $entry = array();
                        array_push($entry, $client['cport']);
                        $port_list = array_diff($port_list, $entry);
                }
        }

	return $port_list;
}

/* construct list of used ports */
function used_port_list() {
	global $config;

	/* initialize variables */
	$port_list	= array();

        /* add used ports to the list */
        if ($a_server = $config['ovpn']['server']['tunnel']) {
                foreach ($a_server as $server) {
                        if (isset($server['enable']))
				array_push($port_list, $server['port']);
                }
        }

        /* same for list of client tunnels  */
        if ($a_client = $config['ovpn']['client']['tunnel']) {
                foreach ($a_client as $client) {
                        if (isset($client['enable']))
                        	array_push($port_list, $client['cport']);
                }
        }

	return $port_list;
}

/* construct list of bindings used for a specified port */
function used_bind_list($port) {
	global $config;

	/* initialize variables */
	$bind_list	= array();

        /* add used bindings to the list */
        if ($a_server = $config['ovpn']['server']['tunnel']) {
                foreach ($a_server as $server) {
                        if (isset($server['enable']) && $server['port'] == $port)
				array_push($bind_list, $server['bind_iface']);
                }
        }

	/* client daemon always binds to 0.0.0.0 */
	if ($a_client = $config['ovpn']['client']['tunnel']) {
		foreach ($a_client as $client) {
			if (isset($client['enable']) && $client['cport'] == $port)
				array_push($bind_list, "all");
		}
	}

	/* return list of bindings */
	return $bind_list;
}

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
	if ($a_server = $config['ovpn']['server']['tunnel']) {
		foreach ($a_server as $server) {
			if ($server['port'] == $port) {
				return true;
			}
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

/* called by interfaces_opt.php */
function ovpn_ccd_sort() {
	global $g, $config;

	function ccdcmp($a, $b) {
		return strcmp($a['cn'][0], $b['cn'][0]);
	}

	usort($config['ovpn']['server']['ccd'], "ccdcmp");

}

/* called by interfaces_opt.php */
function ovpn_config_post() {
	global $_POST, $optcfg, $pconfig;
	
	unset($input_errors);

	/* bridge check */
	if ($_POST['bridge'] && strstr($optcfg['if'], "tun"))
		$input_errors[] = "Bridging a tun interface isn't possible.";

	if (($_POST['enable'] && !isset($optcfg['enable'])) || (!$_POST['enable'] && isset($optcfg['enable'])))
		$input_errors[] = "Enabling or disabling a tunneling interface isn't supported on this page.";

	if ($_POST['ipaddr'] != $optcfg['ipaddr']) 
		$input_errors[] = "Changing the IP address of a tunneling interfaces isn't supported on this page.";

	if ($_POST['subnet'] != $optcfg['subnet']) 
		$input_errors[] = "Changing the subnet mask of a tunneling interfaces isn't supported on this page.";

	if ($input_errors) {
		$pconfig['ipaddr'] = $optcfg['ipaddr'];
		$pconfig['subnet'] = $optcfg['subnet'];
		$pconfig['bridge'] = $optcfg['bridge'];
		$pconfig['enable'] = isset($optcfg['enable']);
	}

	return $input_errors;
}

function check_bridging($bridge) {
	global $config;
	unset($input_errors);

	/* double bridging? */
	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) { 
		if ($i != $index) {
			if ($config['interfaces']['opt' . $i]['bridge'] == $bridge) {
				$input_errors = "Optional interface {$i} " . 
				  "({$config['interfaces']['opt' . $i]['descr']}) is already bridged to " .
				  "the specified interface.";
			} else if ($config['interfaces']['opt' . $i]['bridge'] == "opt{$index}") {
				$input_errors = "Optional interface {$i} " . 
				  "({$config['interfaces']['opt' . $i]['descr']}) is already bridged to " .
				  "this interface.";
			}
		}
	}

	if ($config['interfaces'][$bridge]['bridge'])
		$input_errors = "The specified interface is already bridged to another interface.";

	return $input_errors;
}

/*
function is_specialnet($net) {
	$specialsrcdst = explode(" ", "lan");
	        
	if (in_array($net, $specialsrcdst))
		return true;
	else
		return false;
}
*/

	
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
