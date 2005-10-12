#!/usr/local/bin/php
<?php 
/*
	vpn_openvpn_srv_edit.php

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

require("guiconfig.inc");
require_once("openvpn.inc");

if (!is_array($config['ovpn']))
	$config['ovpn'] = array();
if (!is_array($config['ovpn']['server'])){
	$config['ovpn']['server'] = array();
	$config['ovpn']['server']['tunnel'] = array();
}

$ovpnsrv =& $config['ovpn']['server']['tunnel'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $ovpnsrv[$id]) {
	$pconfig = $config['ovpn']['server']['tunnel'][$id];
	if (isset($ovpnsrv[$id]['enable']))
		$pconfig['enable'] = true;
	if (!isset($ovpnsrv[$id]['method']))
		$pconfig['method'] = "ovpn";
	if (is_array($ovpnsrv[$id]['expertmode'])) {
		$pconfig['expertmode_options'] = "";
		foreach ($ovpnsrv[$id]['expertmode']['option'] as $optent) {
			$pconfig['expertmode_options'] .= $optent . "\n"; 
		}
		$pconfig['expertmode_options'] = rtrim($pconfig['expertmode_options']); 
	}

} else {
	/* creating - set defaults */
	$pconfig = array();
	$pconfig['type'] = "tun";
	$pconfig['psh_options'] = array();
	/* Initialise with some sensible defaults */
	$pconfig['authentication_method'] = "rsasig";
	$pconfig['port'] = getnxt_port();
	$pconfig['proto'] = 'udp';
	$pconfig['method'] = 'ovpn';
	$pconfig['maxcli'] = '';
	$pconfig['crypto'] = 'BF-CBC';
	$pconfig['dupcn'] = false;
	$pconfig['verb'] = 1;
	$pconfig['enable'] = true;
}

if ($_POST) {

	unset($input_errors);
	unset($check_ipblock);
	unset($bridge_reset);

	/* input validation */
	$reqdfields = explode(" ", "type bind_iface");
	$reqdfieldsn = explode(",", "Tunnel type,Interface binding");

	if ($_POST['authentication_method'] == "pre_shared_key") {
		$reqdfields  = array_merge($reqdfields, explode(" ", "lipaddr pre-shared-key"));
		$reqdfieldsn = array_merge($reqdfieldsn, explode(",", "Local IP address,Pre-shared secret"));

		if ($_POST['type'] == "tun") {
			/* tun */
			$reqdfields  = array_merge($reqdfields, explode(" ", "ripaddr"));
			$reqdfieldsn = array_merge($reqdfieldsn, explode(",", "Remote IP address"));

			/* subnet or ip address */
			if ($_POST['ripaddr']) {
				if (!is_ipaddr($_POST['ripaddr']))
					$input_errors[] = "A valid static remote IP address must be specified.";
				else if (ip2long($_POST['lipaddr']) == ip2long($_POST['ripaddr']))
					$input_errors[] = "Local IP address and remote IP address are the same.";
			}
			if ($_POST['lipaddr'])
				if (!is_ipaddr($_POST['lipaddr'])) 
					$input_errors[] = "A valid local static IP address must be specified.";

		} else {
			 /* tap */
			if ($_POST['lipaddr']) {
				if (!is_ipaddr($_POST['lipaddr'])) 
					$input_errors[] = "A valid local static IP address must be specified.";
				if (gen_subnet($_POST['lipaddr'], $_POST['netmask']) == $_POST['lipaddr'])
					$input_errors[] = "Local IP address is subnet address.";
				if (gen_subnet_max($_POST['lipaddr'], $_POST['netmask']) == $_POST['lipaddr'])
					$input_errors[] = "Local IP address is broadcast address.";
			}
		}

		if (intval($_POST['maxcli']) > 1)
			$input_errors[] = "Maximum number of simultaneous clients should not be greater than \"1\".";

		/* checked also by javascript */
		if ($_POST['method'] != "static")
			$input_errors[] = "Only static address assignment is supported.";

	} else {
		/* rsa */
		$reqdfields  = array_merge($reqdfields, explode(" ", "ca_cert srv_cert srv_key dh_param"));
		$reqdfieldsn = array_merge($reqdfieldsn, explode(",", "CA certificate,Server certificate,Server key,DH parameters"));

		if ($_POST['type'] == "tap") {
			/* tap*/
			if (!$_POST['bridge']) {
				if ($_POST['method'] == "ovpn") {
					$reqdfields  = array_merge($reqdfields, "ipblock");
					$reqdfieldsn = array_merge($reqdfieldsn, "IP address block");
					
					$check_ipblock = 1;
				} else {
					$input_errors[] = "Only supported address assignment is \"Managed by OpenVPN\".";
				}
			} else {
				if ($_POST['method'] == "ovpn") {
					$reqdfields  = array_merge($reqdfields, explode(" ", "range_from range_to gateway"));
					$reqdfieldsn = array_merge($reqdfieldsn, explode(",", "Range begin,Range end,Gateway"));
					if (intval($_POST['maxcli']) > (ip2long($_POST['range_to']) - ip2long($_POST['range_from']) + 1))
						$input_errors[] = "IP range to small for maximum number of simultaneous clients.";

				} else if ($_POST['method'] != "dhcp") {
					$input_errors[] = "Wrong or emtpy OpenVPN address assignment.";
				}
			}

		} else {
			/* tun*/
			$reqdfields  = array_merge($reqdfields, "ipblock");
			$reqdfieldsn = array_merge($reqdfieldsn, "IP address block");

			/* checked also by javascript */
			if ($_POST['method'] != "ovpn")
				$input_errors[] = "Only supported address assignment is \"Managed by OpenVPN\".";

			$check_ipblock = 1;
		}


		/* valid IP */
		if ($_POST['ipblock'] && $check_ipblock) {
			if (!is_ipaddr($_POST['ipblock'])) {
				$input_errors[] = "A valid IP netblock must be specified.";
			} else {
				$network = ip2long(gen_subnet($_POST['ipblock'], $_POST['prefix']));
				$broadcast = ip2long(gen_subnet_max($_POST['ipblock'], $_POST['prefix']));

				if ($_POST['maxcli']) {
					if ($_POST['type'] == "tap") {
						if (intval($_POST['maxcli']) > ($broadcast - $network - 3))
							$input_errors[] = "Maximum number of simultaneous clients too high";
					} else {
						if (intval($_POST['maxcli']) > floor(($broadcast - $network) / 4))
							$input_errors[] = "Maximum number of simultaneous clients too high";
					}
				}
			}
		}

		/* Sort out the cert+key files */
		if (!empty($_POST['ca_cert']) &&
		   (!strstr($_POST['ca_cert'], "BEGIN CERTIFICATE") ||
		    !strstr($_POST['ca_cert'], "END CERTIFICATE")))
			$input_errors[] = "The CA certificate does not appear to be valid.";

		if (!empty($_POST['srv_cert']) &&
		   (!strstr($_POST['srv_cert'], "BEGIN CERTIFICATE") ||
		    !strstr($_POST['srv_cert'], "END CERTIFICATE")))
			$input_errors[] = "The server certificate does not appear to be valid.";

		if (!empty($_POST['srv_key']) &&
		   (!strstr($_POST['srv_key'], "BEGIN RSA PRIVATE KEY") ||
		    !strstr($_POST['srv_key'], "END RSA PRIVATE KEY")))
			$input_errors[] = "The server key does not appear to be valid.";

		if (!empty($_POST['dh_param']) &&
		   (!strstr($_POST['dh_param'], "BEGIN DH PARAMETERS") ||
		    !strstr($_POST['dh_param'], "END DH PARAMETERS")))
			$input_errors[] = "The DH parameters do not appear to be valid.";

		if (isset($_POST['tlsauth']) && empty($_POST['pre-shared-key']))
			$input_errors[] = "The field 'Pre-shared secret' is required.";
	}
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if (($_POST['range_from'] && !is_ipaddr($_POST['range_from'])))
		$input_errors[] = "A valid range must be specified.";

	if (($_POST['range_to'] && !is_ipaddr($_POST['range_to'])))
		$input_errors[] = "A valid range must be specified.";

	if ($_POST['gateway'] && !is_ipaddr($_POST['gateway']))
		$input_errors[] = "A valid gateway IP address must be specified.";

	/* make sure the range lies within the bridged subnet */
	if ($_POST['bridge']) {
		if ($_POST['method'] == "ovpn") {

			$ipaddr = $config['interfaces'][$_POST['bridge']]['ipaddr'];
			$subnet = $config['interfaces'][$_POST['bridge']]['subnet'];

			$subnet_start = (ip2long($ipaddr) & gen_subnet_mask_long($subnet));
			$subnet_end = (ip2long($ipaddr) | (~gen_subnet_mask_long($subnet)));

			if (!ip_in_subnet($_POST['gateway'], gen_subnet($ipaddr, $subnet) . "/" . $subnet))
				$input_errors[] = "The specified gateway lies outside of the bridged subnet.";

			if ((ip2long($_POST['range_from']) < $subnet_start) || (ip2long($_POST['range_from']) > $subnet_end) ||
			    (ip2long($_POST['range_to']) < $subnet_start) || (ip2long($_POST['range_to']) > $subnet_end)) {
				$input_errors[] = "The specified range lies outside of the bridged subnet.";
			}

			if (ip2long($_POST['range_from']) > ip2long($_POST['range_to']))
				$input_errors[] = "The range is invalid (first element higher than second element).";

			if (!($_POST['type'] == "tap" && $_POST['authentication_method'] == "rsasig"))
				$bridge_reset = 1;
		}
	}

	/* valid Port */
	if (empty($_POST['port']))
		$input_errors[] = "You must provide a server in between 1 and 65535.";
	else if (!is_port($_POST['port']))
		$input_errors[] = "The server port must be an integer between 1 and 65535.";
	
	/* check if dynip is set correctly */
	if ($_POST['dynip'] && $_POST['bind_iface'] != 'all')
		$input_errors[] = "Dynamic IP address can only be set with interface binding set to ALL.";

	if (!empty($_POST['pre-shared-key']))
		if (!strstr($_POST['pre-shared-key'], "BEGIN OpenVPN Static key") ||
		    !strstr($_POST['pre-shared-key'], "END OpenVPN Static key"))
			$input_errors[] = "Pre-shared secret does not appear to be valid.";
				
	if ($_POST['psh_pingrst'] && $_POST['psh_pingexit'])
		$input_errors[] = "Ping-restart and Ping-exit are mutually exclusive and cannot be used together";

	if ($_POST['psh_rtedelay'] && !is_numeric($_POST['psh_rtedelay_int']))
		$input_errors[] = "Route-delay needs a numerical interval setting.";

	if ($_POST['psh_inact'] && !is_numeric($_POST['psh_inact_int']))
		$input_errors[] = "Inactive needs a numerical interval setting.";

	if ($_POST['psh_ping'] && !is_numeric($_POST['psh_ping_int']))
		$input_errors[] = "Ping needs a numerical interval setting.";
			
	if ($_POST['psh_pingexit'] && !is_numeric($_POST['psh_pingexit_int']))
		$input_errors[] = "Ping-exit needs a numerical interval setting.";

	if ($_POST['psh_pingrst'] && !is_numeric($_POST['psh_pingrst_int']))
		$input_errors[] = "Ping-restart needs a numerical interval setting.";

	/* Editing an existing entry? */
	if (isset($id) && $ovpnsrv[$id]) {
		$ovpnent = $ovpnsrv[$id];

		/* bridging changed */
		if ($ovpnent['bridge'] != $_POST['bridge']) {
			/* double bridging? */
			if ($_POST['bridge'] &&
			    $_POST['type'] == "tap" &&
			    $_POST['authentication_method'] == "rsasig")
				$retval = check_bridging($_POST['bridge']);

			if (!empty($retval))
				$input_errors[] = $retval;
			else
				ovpn_srv_dirty($ovpnent['tun_iface']);
		}

		/* port number syntactically valid, so lets check, if it is free */
		if (isset($ovpnent['enable']) &&
		   !isset($_POST['disabled']) &&
		   $ovpnent['port'] != $_POST['port']) {
			/* port number has changed */

			if (in_array($_POST['port'], used_port_list())) {
				/* port in use, check binding */

				/* return interfaces bind to this port */
				$bind_list = used_bind_list($_POST['port']);

				/* check if binding is in use */
				if (($_POST['bind_iface'] == "all") ||
				    in_array("all", $bind_list) ||
				    in_array($_POST['bind_iface'], $bind_list) ) {
					$input_errors[] = "OpenVPN binding already in use by another OpenVPN daemon.";
				}
			}
		}

		/* binding free? */
		if (isset($ovpnent['enable']) &&
		   !isset($_POST['disabled']) &&
		   $ovpnent['bind_iface'] != $_POST['bind_iface']) {
			/* binding has changed, remove existing old entry from list */
			$entry = array();
			array_push($entry, $ovpnent['bind_iface']);
			$bind_list = array_diff(used_bind_list($_POST['port']), $entry);

			if (count($bind_list)) {
				if ($_POST['bind_iface'] == "all")
					$input_errors[] = "Interface binding is already in use.";
				else if (in_array("all", $bind_list) ||
				         in_array($_POST['bind_iface'], $bind_list))
					$input_errors[] = "Interface binding is already in use.";
			}
		}

		/* Test Server type hasn't changed */
		if ($ovpnent['type'] != $_POST['type']) {
			$input_errors[] = "Delete this interface first before changing the type of the tunnel to " . strtoupper($_POST['type']) .".";

		}

		/* Has the enable/disable state changed? */
		if (isset($ovpnent['enable']) && isset($_POST['disabled'])) {
			/* status changed to disabled */
			ovpn_srv_dirty($ovpnent['tun_iface']);
		}

		/* status changed to enable */
		if (!isset($ovpnent['enable']) && !isset($_POST['disabled'])) {

			/* check if port number is free */
			if (in_array($_POST['port'], used_port_list())) {
				/* port in use, check binding */

				/* return interfaces bind to this port */
				$bind_list = used_bind_list($_POST['port']);

				if (($_POST['bind_iface'] == "all") ||
				    in_array("all", $bind_list ) ||
				    in_array($_POST['bind_iface'], $bind_list) ) {
					/* binding in use */
					$input_errors[] = "OpenVPN binding already in use by another OpenVPN daemon.";
				}
			}

			ovpn_srv_dirty($ovpnent['tun_iface']);
		}

	} else {
		/* Creating a new entry */
		$ovpnent = array();

		/* port number syntactically valid, so lets check, if it is free */
		if ($_POST['port']) {
			/* new port number */
			$bind_list = used_bind_list($_POST['port']);

			if (in_array($_POST['port'], used_port_list())) {
				/* port in use, check binding */
				if (($_POST['bind_iface'] == "all") ||
				    in_array("all", $bind_list ) ||
				    in_array($_POST['bind_iface'], $bind_list) ) {
					/* binding in use */
					$input_errors[] = "Port {$_POST['port']} is already used for another interface.";
				}
			}
		}

		if (!($ovpnent['tun_iface'] = getnxt_if($_POST['type'])))
			$input_errors[] = "Run out of devices for a tunnel of type {$_POST['type']}";

		/* double bridging? */
		if ($ovpnent['bridge'] != $_POST['bridge']) {
			/* double bridging? */
			if ($_POST['bridge'] &&
			    $_POST['type'] == "tap" &&
			    $_POST['authentication_method'] == "rsasig")
				$retval = check_bridging($_POST['bridge']);

			if (!empty($retval))
				$input_errors[] = $retval;
			else
				ovpn_srv_dirty($ovpnent['tun_iface']);
		}
	}

	if (!$input_errors) {

		$ovpnent['enable'] = isset($_POST['disabled']) ? false : true;
		$ovpnent['bind_iface'] = $_POST['bind_iface'];
		$ovpnent['port'] = $_POST['port'];
		$ovpnent['proto'] = $_POST['proto'];
		$ovpnent['type'] = $_POST['type'];
		$ovpnent['method'] = $_POST['method'];
		$ovpnent['authentication_method'] = $_POST['authentication_method'];
		
		/* convert IP address block to a correct network IP address */
		$ovpnent['ipblock'] = gen_subnet($_POST['ipblock'], $_POST['prefix']);
		$ovpnent['prefix'] = $_POST['prefix'];
		$ovpnent['lipaddr'] = $_POST['lipaddr'];
		$ovpnent['ripaddr'] = $_POST['ripaddr'];
		$ovpnent['netmask'] = $_POST['netmask'];
		$ovpnent['range_from'] = $_POST['range_from'];
		$ovpnent['range_to'] = $_POST['range_to'];
		$ovpnent['gateway'] = $_POST['gateway'];
		$ovpnent['bridge'] = $_POST['bridge'];

		$ovpnent['descr'] = $_POST['descr'];
		$ovpnent['verb'] = $_POST['verb'];
		$ovpnent['maxcli'] = $_POST['maxcli'];
		$ovpnent['crypto'] = $_POST['crypto'];
		$ovpnent['cli2cli'] = $_POST['cli2cli'] ? true : false;
		$ovpnent['dupcn'] = $_POST['dupcn'] ? true : false;
		$ovpnent['dynip'] = $_POST['dynip'] ? true : false;
		$ovpnent['tlsauth'] = $_POST['tlsauth'] ? true : false;
		$ovpnent['crlname'] = $_POST['crlname'];

		unset($ovpnent['pre-shared-key']);
		if ($_POST['pre-shared-key'])
			$ovpnent['pre-shared-key'] = base64_encode($_POST['pre-shared-key']);	

		$ovpnent['psh_options']['redir'] = $_POST['psh_redir'] ? true : false;
		$ovpnent['psh_options']['redir_loc'] = $_POST['psh_redir_loc'] ? true : false;
		$ovpnent['psh_options']['rtedelay'] = $_POST['psh_rtedelay'] ? true : false;
		$ovpnent['psh_options']['inact'] = $_POST['psh_inact'] ? true : false;
		$ovpnent['psh_options']['ping'] = $_POST['psh_ping'] ? true : false;
		$ovpnent['psh_options']['pingrst'] = $_POST['psh_pingrst'] ? true : false;
		$ovpnent['psh_options']['pingexit'] = $_POST['psh_pingexit'] ? true : false;

		unset($ovpnent['psh_options']['rtedelay_int']);
		unset($ovpnent['psh_options']['inact_int']);
		unset($ovpnent['psh_options']['ping_int']);
		unset($ovpnent['psh_options']['pingrst_int']);
		unset($ovpnent['psh_options']['pingexit_int']);

		if ($_POST['psh_rtedelay_int'])
			$ovpnent['psh_options']['rtedelay_int'] = $_POST['psh_rtedelay_int'];
		if ($_POST['psh_inact_int'])
			$ovpnent['psh_options']['inact_int'] = $_POST['psh_inact_int'];
		if ($_POST['psh_ping_int'])
			$ovpnent['psh_options']['ping_int'] = $_POST['psh_ping_int'];
		if ($_POST['psh_pingrst_int'])
			$ovpnent['psh_options']['pingrst_int'] = $_POST['psh_pingrst_int'];
		if ($_POST['psh_pingexit_int'])
			$ovpnent['psh_options']['pingexit_int'] = $_POST['psh_pingexit_int'];
		
		$ovpnent['ca_cert'] = base64_encode($_POST['ca_cert']);
		$ovpnent['srv_cert'] = base64_encode($_POST['srv_cert']);
		$ovpnent['srv_key'] = base64_encode($_POST['srv_key']);
		$ovpnent['dh_param'] = base64_encode($_POST['dh_param']);	

		/* expertmode params */
		$ovpnent['expertmode_enabled'] = $_POST['expertmode_enabled'] ? true : false;
		
		if (!is_array($options))
			$options = array();
		if (!is_array($ovpnent['expertmode']))
			$ovpnent['expertmode'] = array();

		$options['option'] = array_map('trim', explode("\n", trim($_POST['expertmode_options'])));
		$ovpnent['expertmode'] = $options;

		if (isset($id) && $ovpnsrv[$id])
			$ovpnsrv[$id] = $ovpnent;
		else
			$ovpnsrv[] = $ovpnent;

		write_config();
		ovpn_srv_dirty($ovpnent['tun_iface']);

		header("Location: vpn_openvpn_srv.php");
		exit;
	} else {

		$pconfig = $_POST;

		$pconfig['enable'] = "true";
		if (isset($_POST['disabled']))
			unset($pconfig['enable']);

		$pconfig['pre-shared-key'] = base64_encode($_POST['pre-shared-key']);	
		$pconfig['ca_cert'] = base64_encode($_POST['ca_cert']);
		$pconfig['srv_cert'] = base64_encode($_POST['srv_cert']);
		$pconfig['srv_key'] = base64_encode($_POST['srv_key']);
		$pconfig['dh_param'] = base64_encode($_POST['dh_param']);

		$pconfig['psh_options']['redir'] = $_POST['psh_redir'];
		$pconfig['psh_options']['redir_loc'] = $_POST['psh_redir_loc'];
		$pconfig['psh_options']['rtedelay'] = $_POST['psh_rtedelay'];
		$pconfig['psh_options']['inact'] = $_POST['psh_inact'];
		$pconfig['psh_options']['ping'] = $_POST['psh_ping'];
		$pconfig['psh_options']['pingrst'] = $_POST['psh_pingrst'];
		$pconfig['psh_options']['pingexit'] = $_POST['psh_pingexit'];

		$pconfig['psh_options']['rtedelay_int'] = $_POST['psh_rtedelay_int'];
		$pconfig['psh_options']['inact_int'] = $_POST['psh_inact_int'];
		$pconfig['psh_options']['ping_int'] = $_POST['psh_ping_int'];
		$pconfig['psh_options']['pingrst_int'] = $_POST['psh_pingrst_int'];
		$pconfig['psh_options']['pingexit_int'] = $_POST['psh_pingexit_int'];
	}
}

$pgtitle = "VPN: OpenVPN: Edit Server";
include("head.inc");

?>

<?php include("fbegin.inc"); ?>
<script language="JavaScript">
function enable_change(enable_over) {
	var endis;
	endis = !(!document.iform.disabled.checked || enable_over);
        
        document.iform.proto[0].disabled = endis;
        document.iform.proto[1].disabled = endis;
	document.iform.port.disabled = endis;
        document.iform.bind_iface.disabled = endis;
        document.iform.dynip.disabled = endis;
        document.iform.descr.disabled = endis;
        document.iform.authentication_method.disabled = endis;
        document.iform.ca_cert.disabled = endis;
        document.iform.srv_cert.disabled = endis;
        document.iform.srv_key.disabled = endis;
        document.iform.dh_param.disabled = endis;
        document.iform.crypto.disabled = endis;
        document.iform.tlsauth.disabled = endis;
        document.iform.crlname.disabled = endis;
        document.iform.psk.disabled = endis;
        document.iform.type[0].disabled = endis;
        document.iform.type[1].disabled = endis;
	document.iform.bridge.disabled = endis;
	document.iform.method[0].disabled = endis;
	document.iform.method[1].disabled = endis;
	document.iform.method[2].disabled = endis;
	document.iform.maxcli.disabled = endis;
	document.iform.ipblock.disabled = endis;
	document.iform.prefix.disabled = endis;
	document.iform.range_from.disabled = endis;
	document.iform.range_to.disabled = endis;
	document.iform.gateway.disabled = endis;
	document.iform.lipaddr.disabled = endis;
	document.iform.ripaddr.disabled = endis;
	document.iform.netmask.disabled = endis;
	document.iform.cli2cli.disabled = endis;
	document.iform.dupcn.disabled = endis;
	document.iform.psh_redir.disabled = endis;
	document.iform.psh_redir_loc.disabled = endis;
	document.iform.psh_rtedelay.disabled = endis;
	document.iform.psh_rtedelay_int.disabled = endis;
	document.iform.psh_inact.disabled = endis;
	document.iform.psh_inact_int.disabled = endis;
	document.iform.psh_ping.disabled = endis;
	document.iform.psh_ping_int.disabled = endis;
	document.iform.psh_pingexit.disabled = endis;
	document.iform.psh_pingexit_int.disabled = endis;
	document.iform.psh_pingrst.disabled = endis;
	document.iform.psh_pingrst_int.disabled = endis;
	document.iform.expertmode_enabled.disabled = endis;
	document.iform.expertmode_options.disabled = endis;

	if (!document.iform.disabled.checked) {
		type_change();
		tls_change(enable_over);
		expertmode_change(enable_over);
		methodsel_change(enable_over);
	}
}

function type_change() {
	switch (document.iform.bind_iface.selectedIndex) {
		/* ALL */
		case 0:
			document.iform.dynip.disabled = 0;
			break;
		default:
			document.iform.dynip.disabled = 1;
	}
}

function tls_change(enable_over) {
	var endis;
	endis = !(document.iform.tlsauth.checked || enable_over);
        
        document.iform.psk.disabled = endis;
}


function expertmode_change(enable_over) {
	var endis;
	endis = !(document.iform.expertmode_enabled.checked || enable_over);

	document.iform.expertmode_options.disabled = endis;
}

function methodsel_change(enable_over) {
	var endis;

	switch (document.iform.authentication_method.selectedIndex) {
		case 1: /* rsa */
			if (get_radio_value(document.iform.type) == "tap") {
				/* tap */

				endis = !((document.iform.bridge.selectedIndex == 0) || enable_over);

				if (document.iform.bridge.selectedIndex == 0)
					document.iform.method[0].checked = 1;

				document.iform.method[0].disabled = 0;
				document.iform.method[1].disabled = !endis;
				document.iform.method[2].disabled = 1;
				document.iform.method[2].checked = 0;
				document.iform.bridge.disabled = 0;

				if (get_radio_value(document.iform.method) == "ovpn") {
					document.iform.ipblock.disabled = endis;
					document.iform.prefix.disabled = endis;
					document.iform.range_from.disabled = !endis;
					document.iform.range_to.disabled = !endis;
					document.iform.gateway.disabled = !endis;
				} else if (get_radio_value(document.iform.method) == "dhcp") {
					document.iform.ipblock.disabled = 1;
					document.iform.prefix.disabled = 1;
					document.iform.range_from.disabled = 1;
					document.iform.range_to.disabled = 1;
					document.iform.gateway.disabled = 1;
				}
			} else {
				/* tun */
				document.iform.method[0].disabled = 0;
				document.iform.method[0].checked = 1;
				document.iform.method[1].disabled = 1;
				document.iform.method[2].disabled = 1;
				document.iform.bridge.disabled = 1;
				document.iform.bridge.selectedIndex = 0;
				document.iform.ipblock.disabled = 0;
				document.iform.prefix.disabled = 0;
				document.iform.range_from.disabled = 1;
				document.iform.range_to.disabled = 1;
				document.iform.gateway.disabled = 1;
			}

			document.iform.psk.disabled = 1;
			document.iform.ca_cert.disabled = 0;
			document.iform.srv_cert.disabled = 0;
			document.iform.srv_key.disabled = 0;
			document.iform.dh_param.disabled = 0;
			document.iform.tlsauth.disabled = 0;
			document.iform.crlname.disabled = 0;
			document.iform.maxcli.disabled = 0;
			document.iform.dupcn.disabled = 0;
			document.iform.lipaddr.disabled = 1;
			document.iform.ripaddr.disabled = 1;
			document.iform.netmask.disabled = 1;
			tls_change();
			break;
		default: /* pre-shared */
			if (get_radio_value(document.iform.type) == "tap") {
				/* tap */
				document.iform.ripaddr.disabled = 1;
				document.iform.netmask.disabled = 0;
			} else {
				/* tun */
				document.iform.ripaddr.disabled = 0;
				document.iform.netmask.disabled = 1;
			}

			document.iform.psk.disabled = 0;
			document.iform.ca_cert.disabled = 1;
			document.iform.srv_cert.disabled = 1;
			document.iform.srv_key.disabled = 1;
			document.iform.dh_param.disabled = 1;
			document.iform.tlsauth.disabled = 1;
			document.iform.crlname.disabled = 1;
		
			document.iform.method[0].disabled = 1;
			document.iform.method[1].disabled = 1;
			document.iform.method[2].disabled = 0;
			document.iform.method[2].checked = 1;
			document.iform.bridge.disabled = 1;
			document.iform.bridge.selectedIndex = 0;
			document.iform.ipblock.disabled = 1;
			document.iform.prefix.disabled = 1;
			document.iform.range_from.disabled = 1;
			document.iform.range_to.disabled = 1;
			document.iform.gateway.disabled = 1;
			document.iform.lipaddr.disabled = 0;
			document.iform.maxcli.disabled = 1;
			document.iform.maxcli.value = "";
			document.iform.dupcn.disabled = 1;
			document.iform.dupcn.checked = 0;
			document.iform.cli2cli.disabled = 1;
			document.iform.cli2cli.checked = 0;
			break;
	}

	if (enable_over) {
		document.iform.psk.disabled = 0;
		document.iform.ca_cert.disabled = 0;
		document.iform.srv_cert.disabled = 0;
		document.iform.srv_key.disabled = 0;
		document.iform.dh_param.disabled = 0;
		document.iform.tlsauth.disabled = 0;
		document.iform.crlname.disabled = 0;
		document.iform.bridge.disabled = 0;
		document.iform.ipblock.disabled = 0;
		document.iform.prefix.disabled = 0;
		document.iform.range_from.disabled = 0;
		document.iform.range_to.disabled = 0;
		document.iform.gateway.disabled = 0;
		document.iform.lipaddr.disabled = 0;
		document.iform.ripaddr.disabled = 0;
		document.iform.netmask.disabled = 0;
		document.iform.maxcli.disabled = 0;
		document.iform.method[0].disabled = 0;
		document.iform.method[1].disabled = 0;
		document.iform.method[2].disabled = 0;
	}
}

function get_radio_value(obj) {
	for (i = 0; i < obj.length; i++) {
		if (obj[i].checked)
		return obj[i].value;
	}
	return null;
}

//-->
</script>
<?php if ($input_errors) print_input_errors($input_errors);?>
<form action="vpn_openvpn_srv_edit.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<strong><span class="red">WARNING: This feature is experimental and modifies your optional interface configuration.
  Backup your configuration before using OpenVPN, and restore it before upgrading.<br>&nbsp;<br>
</span></strong>
<table width="100%" border="0" cellpadding="6" cellspacing="0">
  <tr>
    <td width="22%" valign="top" class="vncellreq">Disabled</td>
    <td width="78%" class="vtable">
      <input name="disabled" type="checkbox" value="yes" onclick="enable_change(false)" <?php if (!isset($pconfig['enable'])) echo "checked"; ?>>
      <strong>Disable this server</strong><br>
        <span class="vexpl">Set this option to disable this server without removing it from the list.</span>
    </td>
   </tr>
   
    <tr>
      <td width="22%" valign="top" class="vncell">OpenVPN protocol/port</td>
      <td width="78%" class="vtable">
	<input type="radio" name="proto" class="formfld" value="udp" <?php if ($pconfig['proto'] == 'udp') echo "checked"; ?>>
           UDP&nbsp;
        <input type="radio" name="proto" class="formfld" value="tcp" <?php if ($pconfig['proto'] == 'tcp') echo "checked"; ?>>
           TCP<br><br>
        Port: 
        <input name="port" type="text" class="formfld" size="5" maxlength="5" value="<?= $pconfig['port']; ?>"><br>
        Enter the port number to use for the server (default is 1194).</td>
    </tr>
    
    <tr>
      <td width="22%" valign="top" class="vncellreq">Interface binding</td>
      <td width="78%" class="vtable">
	<select name="bind_iface" class="formfld" onchange="type_change()">
        <?php 
	$interfaces = ovpn_real_interface_list();
	foreach ($interfaces as $key => $iface):
        ?>
	<option value="<?=$key;?>" <?php if ($key == $pconfig['bind_iface']) echo "selected"; ?>> <?= $iface;?>
        </option>
        <?php endforeach;?>
        </select>
        <span class="vexpl"><br>
        Choose an interface for the OpenVPN server to listen on.</span></td>
    </tr>
		
    <tr>
      <td width="22%" valign="top" class="vncell">Dynamic IP address</td>
      <td width="78%" class="vtable">
	<input name="dynip" type="checkbox" value="yes" <?php if (isset($pconfig['dynip'])) echo "checked"; ?>>
	<strong>Dynamic IP address</strong><br>
	Set this option to on, if your IP addresses are being assigned dynamically. Can only be used with interface binding set to ALL.</td>
    </tr>

    <tr> 
      <td width="22%" valign="top" class="vncell">Description</td>
      <td width="78%" class="vtable"> 
        <input name="descr" type="text" class="formfld" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>"> 
        <br> <span class="vexpl">You may enter a description here for your reference (not parsed).</span></td>
    </tr>

    <tr> 
      <td colspan="2" valign="top" height="12"></td>
    </tr>
    <tr>
      <td colspan="2" valign="top" class="listtopic">Cryptographic options</td>
    </tr>
    <tr> 
      <td width="22%" valign="top" class="vncellreq">Authentication method</td>
      <td width="78%" class="vtable"> 
	<select name="authentication_method" class="formfld" onChange="methodsel_change(false)">
	  <?php foreach ($p1_authentication_methods as $method => $methodname): ?>
	    <option value="<?=$method;?>" <?php if ($method == $pconfig['authentication_method']) echo "selected"; ?>>
		<?=htmlspecialchars($methodname);?>
	    </option>
	  <?php endforeach; ?>
	</select> <br> <span class="vexpl">Must match the setting chosen on the remote side.</span></td>
    </tr>
    
    <tr> 
      <td width="22%" valign="top" class="vncellreq">CA certificate</td>
      <td width="78%" class="vtable"> 
      <textarea name="ca_cert" cols="65" rows="4" class="formpre"><?=htmlspecialchars(base64_decode($pconfig['ca_cert']));?></textarea>
      <br>
      Paste a CA certificate in X.509 PEM format here.</td>
    </tr>
		
    <tr> 
      <td width="22%" valign="top" class="vncellreq">Server certificate</td>
      <td width="78%" class="vtable">
        <textarea name="srv_cert" cols="65" rows="4" class="formpre"><?=htmlspecialchars(base64_decode($pconfig['srv_cert']));?></textarea>
        <br>
        Paste a server certificate in X.509 PEM format here.</td>
    </tr>
     
    <tr> 
      <td width="22%" valign="top" class="vncellreq">Server key</td>
      <td width="78%" class="vtable"> 
        <textarea name="srv_key" cols="65" rows="4" class="formpre"><?=htmlspecialchars(base64_decode($pconfig['srv_key']));?></textarea>
        <br>Paste the server RSA private key here.</td>
    </tr>
      
    <tr> 
      <td width="22%" valign="top" class="vncellreq">DH parameters</td>
      <td width="78%" class="vtable"> 
	<textarea name="dh_param" cols="65" rows="4" class="formpre"><?=htmlspecialchars(base64_decode($pconfig['dh_param']));?></textarea>
	<br>          
	  Paste the Diffie-Hellman parameters in PEM format here.</td>
    </tr>
      
    <tr>
      <td width="22%" valign="top" class="vncell">Crypto</td>
      <td width="78%" class="vtable">
	<select name="crypto" class="formfld">
	  <?php $cipher_list = ovpn_get_cipher_list();
		foreach($cipher_list as $key => $value){
	  ?>
		<option value="<?= $key ?>" <?php if ($pconfig['crypto'] == $key) echo "selected"; ?>>
		<?= $value ?>
		</option>
	  <?php
	    }
	  ?>
	  </select>
	  <br>
	Select a data channel encryption cipher.</td>
    </tr>
      
    <tr>
      <td width="22%" valign="top" class="vncell">TLS auth</td>
      <td width="78%" class="vtable">
	<input name="tlsauth" type="checkbox" value="yes" <?php if (isset($pconfig['tlsauth'])) echo "checked";?> onclick="tls_change(false)">
	<strong>TLS auth</strong><br>
	The tls-auth directive adds an additional HMAC signature to all SSL/TLS handshake packets for integrity verification.</td>
    </tr>

    <tr> 
      <td width="22%" valign="top" class="vncellreq">Pre-shared secret</td>
      <td width="78%" class="vtable">
	<textarea name="pre-shared-key" id="psk" cols="65" rows="4" class="formpre"><?=htmlspecialchars(base64_decode($pconfig['pre-shared-key']));?></textarea>
	<br>
	Paste your own pre-shared secret here.</td>
    </tr>

    <tr>
      <td width="22%" valign="top" class="vncell">CRL</td>
      <td width="78%" class="vtable">
	<select name="crlname" class="formfld" id="crlname">
	<option <?php if (!$pconfig['crlname']) echo "selected";?> value="">none</option>
	<?php $crl_list = ovpn_get_crl_list();
	    foreach($crl_list as $crlname): ?>
		<option value="<?=$crlname;?>" <?php if ($crlname == $pconfig['crlname']) echo "selected";?>>
		  <?=htmlspecialchars($crlname);?>
		</option>
	<?php endforeach; ?>
	</select> 
	<br> <span class="vexpl">
	You can choose a CRL (certificate revocation list) file in PEM format here.
	Each peer certificate is checked against this file.</span></td>
    </tr>

    <tr> 
      <td colspan="2" valign="top" height="12"></td>
    </tr>
    <tr>
      <td colspan="2" valign="top" class="listtopic">IP configuration</td>
    </tr>
   <tr>
     <td width="22%" valign="top" class="vncellreq">Tunnel type</td>
     <td width="78%" class="vtable">
       <input type="radio" name="type" class="formfld" value="tun" onclick="methodsel_change(false)" <?php if ($pconfig['type'] == 'tun') echo "checked"; ?>>
          TUN&nbsp;
       <input type="radio" name="type" class="formfld" value="tap" onclick="methodsel_change(false)" <?php if ($pconfig['type'] == 'tap') echo "checked"; ?>>
          TAP
      </td>
    </tr>

    <tr> 
      <td width="22%" valign="top" class="vncellreq">Bridge with</td>
      <td width="78%" class="vtable">
	<select name="bridge" class="formfld" id="bridge" onChange="methodsel_change(false)">
	<option <?php if (!$pconfig['bridge']) echo "selected";?> value="">none</option>
	<?php $opts = array('lan' => "LAN", 'wan' => "WAN");
	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
		if ($i != $index && !($config['interfaces']['opt' . $i]['ovpn']))
			$opts['opt' . $i] = "Optional " . $i . " (" . $config['interfaces']['opt' . $i]['descr'] . ")";
	}
	foreach ($opts as $opt => $optname): ?>
		<option <?php if ($opt == $pconfig['bridge']) echo "selected";?> value="<?=htmlspecialchars($opt);?>"> 
			<?=htmlspecialchars($optname);?>
		</option>
	<?php endforeach; ?>
	</select> <br> <span class="vexpl">Only supported with authentication method set to RSA signature.</span>
      </td>
    </tr>
	 
    <tr> 
      <td width="22%" valign="top" class="vncellreq">OpenVPN address assignment</td>
      <td width="78%" class="vtable"> 
	<table cellpadding="0" cellspacing="0">
	  <tr>
	    <td colspan="2"><input name="method" type="radio" id="method" value="ovpn" onclick="methodsel_change(false)" <?php if($pconfig['method'] == "ovpn" || $pconfig['type'] == "tun") echo "checked"; ?>>
	    Managed by OpenVPN
	    </td>
	  </tr>
	  <tr>
	    <td colspan="2"><input name="method" type="radio" id="method" value="dhcp" onclick="methodsel_change(false)" <?php if($pconfig['method'] == "dhcp") echo "checked"; ?>>
	    Configure manually or by DHCP Server
	    </td>
	  </tr>
	  <tr>
	    <td colspan="2"><input name="method" type="radio" id="method" value="static" onclick="methodsel_change(false)" <?php if($pconfig['method'] == "static") echo "checked"; ?>>
	    Static assignment
	    </td>
	  </tr>
	  <tr>
	    <td>&nbsp;</td>
	    <td>&nbsp;</td>
	  </tr>
	  <tr>
	    <td>Maximum number of simultaneous clients:&nbsp;&nbsp;<br>(leave blank to disable)</td>
	    <td valign="top">
		<input name="maxcli" type="text" class="formfld" size="3" maxlength="3" value="<?=htmlspecialchars($pconfig['maxcli']);?>">
	    </td>
	  </tr>
	</table>
      </td>
    </tr>

    <tr> 
      <td width="22%" valign="top" class="vncell"></td>
      <td width="78%" class="vtable"> 
	When using OpenVPN for address assignment, set aside a pool of subnets to be
	dynamically allocated to connecting  clients, similar to a DHCP server.<br>
	<br>
	For tun-style tunnels, each client will be given a /30 subnet
	(for interoperability  with  Windows  clients).<br>
	For tap-style tunnels, individual addresses will be allocated, and the optional
	netmask parameter will also be pushed to clients.<br>
	<br>

	<table cellpadding="0" cellspacing="0">
	  <tr>
	    <td>IP address block:&nbsp;&nbsp;</td>
	    <td valign="top"><input name="ipblock" type="text" class="formfld" size="20" value="<?=htmlspecialchars($pconfig['ipblock']);?>">
		/ 
		<select name="prefix" class="formfld">
		<?php for ($i = 30; $i > 19; $i--): ?>
		  <option value="<?=$i;?>" <?php if ($i == $pconfig['prefix']) echo "selected"; ?>>
		    <?=$i;?>
		  </option>
		<?php endfor; ?>
		</select>
	    </td>
	  </tr>
	</table>
      </td>
    </tr>

    <tr> 
      <td width="22%" valign="top" class="vncell"></td>
      <td width="78%" class="vtable"> 
	For bridges interfaces OpenVPN will allocate
	an IP range in the bridged subnet to connecting clients.<br><br>
	The gateway and netmask parameters
	can be set to either the IP of the bridge interface, or to
	the IP of the default gateway/router on the bridged subnet.<br>
	<br>

	<table cellpadding="0" cellspacing="0">
	  <tr>
	    <td>Range:&nbsp;&nbsp;</td>
	    <td valign="top"><input name="range_from" type="text" class="formfld" size="20" value="<?=htmlspecialchars($pconfig['range_from']);?>">
		&nbsp;to&nbsp;<input name="range_to" type="text" class="formfld" size="20" value="<?=htmlspecialchars($pconfig['range_to']);?>">
	    </td>
	  </tr>

	  <tr>
	    <td>Gateway:&nbsp;&nbsp;</td>
	    <td valign="top"><input name="gateway" type="text" class="formfld" size="20" value="<?=htmlspecialchars($pconfig['gateway']);?>">
	    </td>
	  </tr>
	</table>
      </td>
    </tr>

    <tr> 
      <td width="22%" valign="top" class="vncell">&nbsp;</td>
      <td width="78%" class="vtable"> 
        When using pre-shared keys, enter the IP address and subnet mask
        of the local and remote VPN endpoint here. For TAP devices, only the
        IP address of the local VPN endpoint is needed. The netmask is the subnet mask
        of the virtual ethernet segment which is being created or connected to.<br>
	<br>
	<table cellpadding="0" cellspacing="0">
	  <tr>
	    <td>Local IP address:&nbsp;&nbsp;</td>
	    <td valign="top"><input name="lipaddr" id="lipaddr" type="text" class="formfld" size="20" value="<?=htmlspecialchars($pconfig['lipaddr']);?>">
		/ 
		<select name="netmask" id="netmask" class="formfld">
		<?php for ($i = 30; $i > 19; $i--): ?>
		  <option value="<?=$i;?>" <?php if ($i == $pconfig['netmask']) echo "selected"; ?>>
		    <?=$i;?>
		  </option>
		<?php endfor; ?>
		</select>
	    </td>
	  </tr>

	  <tr>
	    <td>Remote IP address:&nbsp;&nbsp;</td>
	    <td valign="top"><input name="ripaddr" id="ripaddr" type="text" class="formfld" size="20" value="<?=htmlspecialchars($pconfig['ripaddr']);?>">
	    </td>
	  </tr>
	</table>
      </td>
    </tr>

    <tr> 
      <td colspan="2" valign="top" height="12"></td>
    </tr>
    <tr>
      <td colspan="2" valign="top" class="listtopic">Server Options</td>
    </tr>
    <tr>
      <td width="22%" valign="top" class="vncell">Internal routing mode</td>
      <td width="78%" class="vtable">
	<input name="cli2cli" type="checkbox" value="yes" <?php if (isset($pconfig['cli2cli'])) echo "checked"; ?>>
	<strong>Enable client-to-client routing</strong><br>
	If this option is on, clients are allowed to talk to each other.</td>
    </tr>
      
    <tr>
      <td width="22%" valign="top" class="vncell">Client authentication</td>
      <td width="78%" class="vtable">
	<input name="dupcn" type="checkbox" value="yes" <?php if (isset($pconfig['dupcn'])) echo "checked"; ?>>
        <strong>Permit duplicate client certificates</strong><br>
	If this option is on, clients with duplicate certificates will not be disconnected.</td>
    </tr>
	 
    <tr>
      <td width="22%" valign="top" class="vncell">Client-push options</td>
      <td width="78%" class="vtable">
	    <table border="0" cellspacing="0" cellpadding="0">
	      <tr>
            <td><input type="checkbox" name="psh_redir" value="yes" <?php if (isset($pconfig['psh_options']['redir'])) echo "checked"; ?>>
            Redirect-gateway</td>
            <td>&nbsp;</td>
            <td><input type="checkbox" name="psh_redir_loc" value="yes" <?php if (isset($pconfig['psh_options']['redir_loc'])) echo "checked"; ?>>
              Local</td>
	        </tr>
          <tr>
            <td><input type="checkbox" name="psh_rtedelay" value="yes" <?php if (isset($pconfig['psh_options']['rtedelay'])) echo "checked"; ?>> Route-delay</td>
            <td width="16">&nbsp;</td>
            <td><input type="text" name="psh_rtedelay_int" class="formfld" size="4" value="<?= $pconfig['psh_options']['rtedelay_int']?>"> seconds</td>
          </tr>
          <tr>
            <td><input type="checkbox" name="psh_inact" value="yes" <?php if (isset($pconfig['psh_options']['inact'])) echo "checked"; ?>>
    Inactive</td>
            <td>&nbsp;</td>
            <td><input type="text" name="psh_inact_int" class="formfld" size="4" value="<?= $pconfig['psh_options']['inact_int']?>">
    seconds</td>
          </tr>
          <tr>
            <td><input type="checkbox" name="psh_ping" value="yes" <?php if (isset($pconfig['psh_options']['ping'])) echo "checked"; ?>> Ping</td>
            <td>&nbsp;</td>
            <td>Interval: <input type="text" name="psh_ping_int" class="formfld" size="4" value="<?= $pconfig['psh_options']['ping_int']?>"> seconds</td>
          </tr>
          <tr>
            <td><input type="checkbox" name="psh_pingexit" value="yes" <?php if (isset($pconfig['psh_options']['pingexit'])) echo "checked"; ?>> Ping-exit</td>
            <td>&nbsp;</td>
            <td>Interval: <input type="text" name="psh_pingexit_int" class="formfld" size="4" value="<?= $pconfig['psh_options']['pingexit_int']?>"> seconds</td>
          </tr>
          <tr>
            <td><input type="checkbox" name="psh_pingrst" value="yes" <?php if (isset($pconfig['psh_options']['pingrst'])) echo "checked"; ?>> Ping-restart</td>
            <td>&nbsp;</td>
            <td>Interval: <input type="text" name="psh_pingrst_int" class="formfld" size="4" value="<?= $pconfig['psh_options']['pingrst_int']?>"> seconds</td>
          </tr>
        </table></td>
    </tr>

     <tr>
      <td width="22%" valign="top" class="vncell">Expert mode</td>
      <td width="78%" class="vtable">
        <input name="expertmode_enabled" type="checkbox" value="yes" onclick="expertmode_change(false);" <?php if (isset($pconfig['expertmode_enabled'])) echo "checked"; ?>>
        <strong>Enable expert OpenVPN mode</strong><br>
        If this option is on, you can specify your own extra commands for the OpenVPN server.<br/>
        <textarea name="expertmode_options" id="expertmode_options" cols="65" rows="4" class="formpre"><?=htmlspecialchars($pconfig['expertmode_options']);?></textarea>
	<strong><span class="red">Note:</span></strong><br>
	Commands in expert mode aren't supported.
      </td>
    </tr>

    <tr>
      <td width="22%" valign="top">&nbsp;</td>
      <td width="78%">
        <input name="Submit" type="submit" class="formbtn" value="Save" onclick="methodsel_change(true);tls_change(true);expertmode_change(true);enable_change(true)">
        <input name="verb" type="hidden" value="<?=$pconfig['verb'];?>"> 
        <?php if (isset($id)): ?>
        <input name="id" type="hidden" value="<?=$id;?>"> 
        <?php endif; ?>
      </td>
    </tr>
</table>
</form>
<script language="JavaScript">
<!--
type_change();
tls_change(false);
methodsel_change(false);
expertmode_change(false);
enable_change(false);
//-->
</script>
<?php include("fend.inc");
?>
