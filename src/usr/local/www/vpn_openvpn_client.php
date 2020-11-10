<?php
/*
 * vpn_openvpn_client.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008 Shrew Soft Inc.
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

##|+PRIV
##|*IDENT=page-openvpn-client
##|*NAME=OpenVPN: Clients
##|*DESCR=Allow access to the 'OpenVPN: Clients' page.
##|*MATCH=vpn_openvpn_client.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("openvpn.inc");
require_once("pfsense-utils.inc");
require_once("pkg-utils.inc");

global $openvpn_topologies, $openvpn_tls_modes;

init_config_arr(array('openvpn', 'openvpn-client'));
$a_client = &$config['openvpn']['openvpn-client'];

init_config_arr(array('ca'));
$a_ca = &$config['ca'];

init_config_arr(array('cert'));
$a_cert = &$config['cert'];

init_config_arr(array('crl'));
$a_crl = &$config['crl'];

if (isset($_REQUEST['id']) && is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

$act = $_REQUEST['act'];

if (isset($id) && $a_client[$id]) {
	$vpnid = $a_client[$id]['vpnid'];
} else {
	$vpnid = 0;
}

$user_entry = getUserEntry($_SESSION['Username']);
$user_can_edit_advanced = (isAdminUID($_SESSION['Username']) || userHasPrivilege($user_entry, "page-openvpn-client-advanced") || userHasPrivilege($user_entry, "page-all"));

if ($_POST['act'] == "del") {

	if (!isset($a_client[$id])) {
		pfSenseHeader("vpn_openvpn_client.php");
		exit;
	}

	if (empty($a_client[$id])) {
		$wc_msg = gettext('Deleted empty OpenVPN client');
	} elseif (!$user_can_edit_advanced && !empty($a_client[$id]['custom_options'])) {
		$input_errors[] = gettext("This user does not have sufficient privileges to delete an instance with Advanced options set.");
	} else {
		openvpn_delete('client', $a_client[$id]);
		$wc_msg = sprintf(gettext('Deleted OpenVPN client to server %1$s:%2$s %3$s'), $a_client[$id]['server_addr'], $a_client[$id]['server_port'], $a_client[$id]['description']);
	}
	if (!empty($wc_msg)) {
		unset($a_client[$id]);
		write_config($wc_msg);
		$savemsg = gettext("Client successfully deleted.");
	}
}

if ($act == "new") {
	$pconfig['ncp_enable'] = "enabled";
	$pconfig['data_ciphers'] = 'AES-256-GCM,AES-128-GCM,CHACHA20-POLY1305';
	$pconfig['data_ciphers_fallback'] = 'AES-256-CBC';
	$pconfig['autokey_enable'] = "yes";
	$pconfig['tlsauth_enable'] = "yes";
	$pconfig['tlsauth_keydir'] = "default";
	$pconfig['autotls_enable'] = "yes";
	$pconfig['interface'] = "wan";
	$pconfig['server_port'] = 1194;
	$pconfig['verbosity_level'] = 1; // Default verbosity is 1
	$pconfig['digest'] = "SHA256";
	$pconfig['allow_compression'] = "no";
	$pconfig['compression'] = "";
}

global $simplefields;
$simplefields = array('auth_user', 'auth_pass', 'proxy_user', 'proxy_passwd');

if (($act == "edit") || ($act == "dup")) {
	if (isset($id) && $a_client[$id]) {
		foreach ($simplefields as $stat) {
			$pconfig[$stat] = $a_client[$id][$stat];
		}

		$pconfig['disable'] = isset($a_client[$id]['disable']);
		$pconfig['mode'] = $a_client[$id]['mode'];
		$pconfig['protocol'] = $a_client[$id]['protocol'];
		$pconfig['interface'] = $a_client[$id]['interface'];
		if (!empty($a_client[$id]['ipaddr'])) {
			$pconfig['interface'] = $pconfig['interface'] . '|' . $a_client[$id]['ipaddr'];
		}
		$pconfig['local_port'] = $a_client[$id]['local_port'];
		$pconfig['server_addr'] = $a_client[$id]['server_addr'];
		$pconfig['server_port'] = $a_client[$id]['server_port'];
		$pconfig['proxy_addr'] = $a_client[$id]['proxy_addr'];
		$pconfig['proxy_port'] = $a_client[$id]['proxy_port'];
		$pconfig['proxy_authtype'] = $a_client[$id]['proxy_authtype'];
		$pconfig['description'] = $a_client[$id]['description'];
		$pconfig['custom_options'] = $a_client[$id]['custom_options'];
		$pconfig['ns_cert_type'] = $a_client[$id]['ns_cert_type'];
		if (isset($a_client[$id]['data_ciphers'])) {
			$pconfig['data_ciphers'] = $a_client[$id]['data_ciphers'];
		} else {
			$pconfig['data_ciphers'] = 'AES-256-GCM,AES-128-GCM,CHACHA20-POLY1305';
		}
		if (isset($a_client[$id]['ncp_enable'])) {
			$pconfig['ncp_enable'] = $a_client[$id]['ncp_enable'];
		} else {
			$pconfig['ncp_enable'] = "enabled";
		}
		$pconfig['dev_mode'] = $a_client[$id]['dev_mode'];

		if ($pconfig['mode'] != "p2p_shared_key") {
			$pconfig['caref'] = $a_client[$id]['caref'];
			$pconfig['certref'] = $a_client[$id]['certref'];
			$pconfig['crlref'] = $a_client[$id]['crlref'];
			if ($a_client[$id]['tls']) {
				$pconfig['tlsauth_enable'] = "yes";
				$pconfig['tls'] = base64_decode($a_client[$id]['tls']);
				$pconfig['tls_type'] = $a_client[$id]['tls_type'];
			}
		} else {
			$pconfig['shared_key'] = base64_decode($a_client[$id]['shared_key']);
		}
		$pconfig['tlsauth_keydir'] = $a_client[$id]['tlsauth_keydir'];
		$pconfig['data_ciphers_fallback'] = $a_client[$id]['data_ciphers_fallback'];
		$pconfig['digest'] = !empty($a_client[$id]['digest']) ? $a_client[$id]['digest'] : "SHA256";
		$pconfig['engine'] = $a_client[$id]['engine'];

		$pconfig['tunnel_network'] = $a_client[$id]['tunnel_network'];
		$pconfig['tunnel_networkv6'] = $a_client[$id]['tunnel_networkv6'];
		$pconfig['remote_network'] = $a_client[$id]['remote_network'];
		$pconfig['remote_networkv6'] = $a_client[$id]['remote_networkv6'];
		$pconfig['use_shaper'] = $a_client[$id]['use_shaper'];
		$pconfig['allow_compression'] = $a_client[$id]['allow_compression'];
		$pconfig['compression'] = $a_client[$id]['compression'];
		$pconfig['auth-retry-none'] = $a_client[$id]['auth-retry-none'];
		$pconfig['passtos'] = $a_client[$id]['passtos'];
		$pconfig['udp_fast_io'] = $a_client[$id]['udp_fast_io'];
		$pconfig['exit_notify'] = $a_client[$id]['exit_notify'];
		$pconfig['sndrcvbuf'] = $a_client[$id]['sndrcvbuf'];
		$pconfig['topology'] = $a_client[$id]['topology'];

		// just in case the modes switch
		$pconfig['autokey_enable'] = "yes";
		$pconfig['autotls_enable'] = "yes";

		$pconfig['route_no_pull'] = $a_client[$id]['route_no_pull'];
		$pconfig['route_no_exec'] = $a_client[$id]['route_no_exec'];
		if (isset($a_client[$id]['create_gw'])) {
			$pconfig['create_gw'] = $a_client[$id]['create_gw'];
		} else {
			$pconfig['create_gw'] = "both"; // v4only, v6only, or both (default: both)
		}
		if (isset($a_client[$id]['verbosity_level'])) {
			$pconfig['verbosity_level'] = $a_client[$id]['verbosity_level'];
		} else {
			$pconfig['verbosity_level'] = 1; // Default verbosity is 1
		}

		$pconfig['ping_method'] = $a_client[$id]['ping_method'];
		$pconfig['keepalive_interval'] = $a_client[$id]['keepalive_interval'];
		$pconfig['keepalive_timeout'] = $a_client[$id]['keepalive_timeout'];
		$pconfig['ping_seconds'] = $a_client[$id]['ping_seconds'];
		$pconfig['ping_action'] = $a_client[$id]['ping_action'];
		$pconfig['ping_action_seconds'] = $a_client[$id]['ping_action_seconds'];
		$pconfig['inactive_seconds'] = $a_client[$id]['inactive_seconds'] ?: 0;
	}
}

if ($act == "dup") {
	$act = "new";
	$vpnid = 0;
	$parentid = $id;
	unset($id);
}

if ($_POST['save']) {

	unset($input_errors);
	$pconfig = $_POST;

	if (isset($id) && $a_client[$id]) {
		$vpnid = $a_client[$id]['vpnid'];
	} else {
		$vpnid = 0;
	}

	if (isset($pconfig['custom_options']) &&
	    ($pconfig['custom_options'] != $a_client[$id]['custom_options']) &&
	    !$user_can_edit_advanced) {
		$input_errors[] = gettext("This user does not have sufficient privileges to edit Advanced options on this instance.");
	}
	if (!$user_can_edit_advanced && !empty($a_client[$id]['custom_options'])) {
		$pconfig['custom_options'] = $a_client[$id]['custom_options'];
	}

	$cipher_validation_list = array_keys(openvpn_get_cipherlist());
	if (!in_array($pconfig['data_ciphers_fallback'], $cipher_validation_list)) {
		$input_errors[] = gettext("The selected Fallback Data Encryption Algorithm is not valid.");
	}

	list($iv_iface, $iv_ip) = explode ("|", $pconfig['interface']);
	if (is_ipaddrv4($iv_ip) && (stristr($pconfig['protocol'], "6") !== false)) {
		$input_errors[] = gettext("Protocol and IP address families do not match. An IPv6 protocol and an IPv4 IP address cannot be selected.");
	} elseif (is_ipaddrv6($iv_ip) && (stristr($pconfig['protocol'], "6") === false)) {
		$input_errors[] = gettext("Protocol and IP address families do not match. An IPv4 protocol and an IPv6 IP address cannot be selected.");
	} elseif ((stristr($pconfig['protocol'], "6") === false) && !get_interface_ip($iv_iface) && ($pconfig['interface'] != "any")) {
		// If an underlying interface to be used by this client uses DHCP, then it may not have received an IP address yet.
		// So in that case we do not report a problem.
		if (!interface_has_dhcp($iv_iface, 4)) {
			$input_errors[] = gettext("An IPv4 protocol was selected, but the selected interface has no IPv4 address.");
		}
	} elseif ((stristr($pconfig['protocol'], "6") !== false) && !get_interface_ipv6($iv_iface) && ($pconfig['interface'] != "any")) {
		// If an underlying interface to be used by this client uses DHCP6, then it may not have received an IP address yet.
		// So in that case we do not report a problem.
		if (!interface_has_dhcp($iv_iface, 6)) {
			$input_errors[] = gettext("An IPv6 protocol was selected, but the selected interface has no IPv6 address.");
		}
	}

	if ($pconfig['mode'] != "p2p_shared_key") {
		$tls_mode = true;
	} else {
		$tls_mode = false;
	}

	/* input validation */
	if ($pconfig['local_port']) {

		if ($result = openvpn_validate_port($pconfig['local_port'], 'Local port')) {
			$input_errors[] = $result;
		}

		$portused = openvpn_port_used($pconfig['protocol'], $pconfig['interface'], $pconfig['local_port'], $vpnid);
		if (($portused != $vpnid) && ($portused != 0)) {
			$input_errors[] = gettext("The specified 'Local port' is in use. Please select another value");
		}
	}

	if ($result = openvpn_validate_host($pconfig['server_addr'], 'Server host or address')) {
		$input_errors[] = $result;
	}

	if ($result = openvpn_validate_port($pconfig['server_port'], 'Server port')) {
		$input_errors[] = $result;
	}

	if (!array_key_exists($pconfig['topology'], $openvpn_topologies)) {
		$input_errors[] = gettext("The field 'Topology' contains an invalid selection");
	}

	if ($pconfig['proxy_addr']) {

		if ($result = openvpn_validate_host($pconfig['proxy_addr'], 'Proxy host or address')) {
			$input_errors[] = $result;
		}

		if ($result = openvpn_validate_port($pconfig['proxy_port'], 'Proxy port')) {
			$input_errors[] = $result;
		}

		if ($pconfig['proxy_authtype'] != "none") {
			if (empty($pconfig['proxy_user']) || empty($pconfig['proxy_passwd'])) {
				$input_errors[] = gettext("User name and password are required for proxy with authentication.");
			}
		}
	}

	if ($pconfig['tunnel_network']) {
		if ($result = openvpn_validate_cidr($pconfig['tunnel_network'], 'IPv4 Tunnel Network', false, "ipv4")) {
			$input_errors[] = $result;
		}
		if ((!isset($a_client[$id]) ||
		    ($a_client[$id]['tunnel_network'] != $pconfig['tunnel_network'])) &&
		    openvpn_is_tunnel_network_in_use($pconfig['tunnel_network'])) {
			$input_errors[] = gettext("The submitted IPv4 Tunnel Network is already in use.");
		}
	}

	if ($pconfig['tunnel_networkv6']) {
		if ($result = openvpn_validate_cidr($pconfig['tunnel_networkv6'], 'IPv6 Tunnel Network', false, "ipv6")) {
			$input_errors[] = $result;
		}
		if ((!isset($a_client[$id]) ||
		    ($a_client[$id]['tunnel_networkv6'] != $pconfig['tunnel_networkv6'])) &&
		    openvpn_is_tunnel_network_in_use($pconfig['tunnel_networkv6'])) {
			$input_errors[] = gettext("The submitted IPv6 Tunnel Network is already in use.");
		}
	}

	if ($result = openvpn_validate_cidr($pconfig['remote_network'], 'IPv4 Remote Network', true, "ipv4")) {
		$input_errors[] = $result;
	}

	if ($result = openvpn_validate_cidr($pconfig['remote_networkv6'], 'IPv6 Remote Network', true, "ipv6")) {
		$input_errors[] = $result;
	}

	if (!empty($pconfig['use_shaper']) && (!is_numeric($pconfig['use_shaper']) || ($pconfig['use_shaper'] <= 0))) {
		$input_errors[] = gettext("The bandwidth limit must be a positive numeric value.");
	}

	if ($pconfig['autokey_enable']) {
		$pconfig['shared_key'] = openvpn_create_key();
	}

	if (!$tls_mode && !$pconfig['autokey_enable']) {
		if (!strstr($pconfig['shared_key'], "-----BEGIN OpenVPN Static key V1-----") ||
		    !strstr($pconfig['shared_key'], "-----END OpenVPN Static key V1-----")) {
			$input_errors[] = gettext("The field 'Shared Key' does not appear to be valid");
		}
	}

	if ($tls_mode && $pconfig['tlsauth_enable'] && !$pconfig['autotls_enable']) {
		if (!strstr($pconfig['tls'], "-----BEGIN OpenVPN Static key V1-----") ||
		    !strstr($pconfig['tls'], "-----END OpenVPN Static key V1-----")) {
			$input_errors[] = gettext("The field 'TLS Key' does not appear to be valid");
		}
		if (!in_array($pconfig['tls_type'], array_keys($openvpn_tls_modes))) {
			$input_errors[] = gettext("The field 'TLS Key Usage Mode' is not valid");
		}
	}

	if (($pconfig['mode'] == "p2p_shared_key") && strstr($pconfig['data_ciphers_fallback'], "GCM")) {
		$input_errors[] = gettext("GCM Encryption Algorithms cannot be used with Shared Key mode.");
	}

	/* If we are not in shared key mode, then we need the CA/Cert. */
	if ($pconfig['mode'] != "p2p_shared_key") {
		if (($pconfig['ncp_enable'] != "disabled") && !empty($pconfig['data_ciphers']) && is_array($pconfig['data_ciphers'])) {
			foreach ($pconfig['data_ciphers'] as $dc) {
				if (!in_array(trim($dc), $cipher_validation_list)) {
					$input_errors[] = gettext("One or more of the selected Data Encryption Algorithms is not valid.");
				}
			}
		}
		$reqdfields = explode(" ", "caref");
		$reqdfieldsn = array(gettext("Certificate Authority"));
	} elseif (!$pconfig['autokey_enable']) {
		/* We only need the shared key filled in if we are in shared key mode and autokey is not selected. */
		$reqdfields = array('shared_key');
		$reqdfieldsn = array(gettext('Shared key'));
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($pconfig['mode'] != "p2p_shared_key") {
		if (empty($pconfig['certref']) && empty($pconfig['auth_user']) && empty($pconfig['auth_pass'])) {
			$input_errors[] = gettext("If no Client Certificate is selected, a username and/or password must be entered.");
		}
	}

	/* UDP Fast I/O and Exit Notify are not compatible with TCP, so toss the
	 * option out when submitted since it can't be set this way
	 * legitimately. This also avoids having to perform any more trickery on
	 * the stored option to not preserve the value when changing modes. */
	if (strtolower(substr($pconfig['protocol'], 0, 3)) != "udp") {
		if ($pconfig['udp_fast_io']) {
			unset($pconfig['udp_fast_io']);
		}
		if ($pconfig['exit_notify']) {
			unset($pconfig['exit_notify']);
		}
	} else {
		if (!array_key_exists($pconfig['exit_notify'], $openvpn_exit_notify_client)) {
			$input_errors[] = gettext("The Exit Notify value is invalid.");
		}
	}

	if ($pconfig['udp_fast_io'] && (!empty($pconfig['use_shaper']))) {
		/* Only warn if the user is set to UDP, otherwise it isn't relevant (See above) */
		if (strtolower(substr($pconfig['protocol'], 0, 3)) == "udp") {
			$input_errors[] = gettext("Limit Outgoing Bandwidth is not compatible with UDP Fast I/O.");
		} else {
			unset($pconfig['udp_fast_io']);
		}
	}

	if (!empty($pconfig['sndrcvbuf']) && !array_key_exists($pconfig['sndrcvbuf'], openvpn_get_buffer_values())) {
		$input_errors[] = gettext("The supplied Send/Receive Buffer size is invalid.");
	}

	if (!empty($pconfig['ping_method']) && !array_key_exists($pconfig['ping_method'], $openvpn_ping_method)) {
		$input_errors[] = gettext("The supplied Ping Method is invalid.");
	}
	if (!empty($pconfig['ping_action']) && !array_key_exists($pconfig['ping_action'], $openvpn_ping_action)) {
		$input_errors[] = gettext("The supplied Ping Action is invalid.");
	}
	if (!empty($pconfig['keepalive_interval']) && !is_numericint($pconfig['keepalive_interval'])) {
		$input_errors[] = gettext("The supplied Keepalive Interval value is invalid.");
	}
	if (!empty($pconfig['keepalive_timeout']) && !is_numericint($pconfig['keepalive_timeout'])) {
		$input_errors[] = gettext("The supplied Keepalive Timeout value is invalid.");
	}
	if (!empty($pconfig['ping_seconds']) && !is_numericint($pconfig['ping_seconds'])) {
		$input_errors[] = gettext("The supplied Ping Seconds value is invalid.");
	}
	if (!empty($pconfig['ping_action_seconds']) && !is_numericint($pconfig['ping_action_seconds'])) {
		$input_errors[] = gettext("The supplied Ping Restart or Exit Seconds value is invalid.");
	}
	if (!empty($pconfig['inactive_seconds']) && !is_numericint($pconfig['inactive_seconds'])) {
		$input_errors[] = gettext("The supplied Inactive Seconds value is invalid.");
	}

	if (!$input_errors) {

		$client = array();

		if (isset($id) && $a_client[$id] &&
		    $pconfig['dev_mode'] <> $a_client[$id]['dev_mode']) {
			/*
			 * delete old interface so a new TUN or TAP interface
			 * can be created.
			 */
			openvpn_delete('client', $a_client[$id]);
		}

		foreach ($simplefields as $stat) {
			if ($_POST[$stat] == DMYPWD) {
				if (is_numeric($_POST['parentid'])) {
					$orig_id = $_POST['parentid'];
				} else {
					$orig_id = $id;
				}
				$client[$stat] = $a_client[$orig_id][$stat];
			} else {
				update_if_changed($stat, $client[$stat], $_POST[$stat]);
			}
		}

		if ($vpnid) {
			$client['vpnid'] = $vpnid;
		} else {
			$client['vpnid'] = openvpn_vpnid_next();
		}

		if ($_POST['disable'] == "yes") {
			$client['disable'] = true;
		}
		$client['protocol'] = $pconfig['protocol'];
		$client['dev_mode'] = $pconfig['dev_mode'];
		list($client['interface'], $client['ipaddr']) = explode ("|", $pconfig['interface']);
		$client['local_port'] = $pconfig['local_port'];
		$client['server_addr'] = $pconfig['server_addr'];
		$client['server_port'] = $pconfig['server_port'];
		$client['proxy_addr'] = $pconfig['proxy_addr'];
		$client['proxy_port'] = $pconfig['proxy_port'];
		$client['proxy_authtype'] = $pconfig['proxy_authtype'];
		$client['description'] = $pconfig['description'];
		$client['mode'] = $pconfig['mode'];
		$client['topology'] = $pconfig['topology'];
		$client['custom_options'] = str_replace("\r\n", "\n", $pconfig['custom_options']);

		if ($tls_mode) {
			$client['caref'] = $pconfig['caref'];
			$client['certref'] = $pconfig['certref'];
			$client['crlref'] = $pconfig['crlref'];
			if ($pconfig['tlsauth_enable']) {
				if ($pconfig['autotls_enable']) {
					$pconfig['tls'] = openvpn_create_key();
				}
				$client['tls'] = base64_encode($pconfig['tls']);
				$client['tls_type'] = $pconfig['tls_type'];
				$client['tlsauth_keydir'] = $pconfig['tlsauth_keydir'];
			}
		} else {
			$client['shared_key'] = base64_encode($pconfig['shared_key']);
		}
		$client['data_ciphers_fallback'] = $pconfig['data_ciphers_fallback'];
		$client['digest'] = $pconfig['digest'];
		$client['engine'] = $pconfig['engine'];

		$client['tunnel_network'] = $pconfig['tunnel_network'];
		$client['tunnel_networkv6'] = $pconfig['tunnel_networkv6'];
		$client['remote_network'] = $pconfig['remote_network'];
		$client['remote_networkv6'] = $pconfig['remote_networkv6'];
		$client['use_shaper'] = $pconfig['use_shaper'];
		$client['allow_compression'] = $pconfig['allow_compression'];
		$client['compression'] = $pconfig['compression'];
		$client['auth-retry-none'] = $pconfig['auth-retry-none'];
		$client['passtos'] = $pconfig['passtos'];
		$client['udp_fast_io'] = $pconfig['udp_fast_io'];
		$client['exit_notify'] = $pconfig['exit_notify'];
		$client['sndrcvbuf'] = $pconfig['sndrcvbuf'];

		$client['route_no_pull'] = $pconfig['route_no_pull'];
		$client['route_no_exec'] = $pconfig['route_no_exec'];
		$client['verbosity_level'] = $pconfig['verbosity_level'];
		$client['create_gw'] = $pconfig['create_gw'];

		if (!empty($pconfig['data_ciphers'])) {
			$client['data_ciphers'] = implode(",", $pconfig['data_ciphers']);
		}

		$client['ncp_enable'] = $pconfig['ncp_enable'] ? "enabled":"disabled";

		$client['ping_method'] = $pconfig['ping_method'];
		$client['keepalive_interval'] = $pconfig['keepalive_interval'];
		$client['keepalive_timeout'] = $pconfig['keepalive_timeout'];
		$client['ping_seconds'] = $pconfig['ping_seconds'];
		$client['ping_action'] = $pconfig['ping_action'];
		$client['ping_action_seconds'] = $pconfig['ping_action_seconds'];
		$client['inactive_seconds'] = $pconfig['inactive_seconds'];

		if (isset($id) && $a_client[$id]) {
			$a_client[$id] = $client;
			$wc_msg = sprintf(gettext('Updated OpenVPN client to server %1$s:%2$s %3$s'), $client['server_addr'], $client['server_port'], $client['description']);
		} else {
			$a_client[] = $client;
			$wc_msg = sprintf(gettext('Added OpenVPN client to server %1$s:%2$s %3$s'), $client['server_addr'], $client['server_port'], $client['description']);
		}

		write_config($wc_msg);
		openvpn_resync('client', $client);

		header("Location: vpn_openvpn_client.php");
		exit;
	}

	if (!empty($pconfig['data_ciphers'])) {
		$pconfig['data_ciphers'] = implode(",", $pconfig['data_ciphers']);
	}
}

$pgtitle = array(gettext("VPN"), gettext("OpenVPN"), gettext("Clients"));
$pglinks = array("", "vpn_openvpn_server.php", "vpn_openvpn_client.php");

if ($act=="new" || $act=="edit") {
	$pgtitle[] = gettext('Edit');
	$pglinks[] = "@self";
}
$shortcut_section = "openvpn";

include("head.inc");

if (!$savemsg) {
	$savemsg = "";
}

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
$tab_array[] = array(gettext("Servers"), false, "vpn_openvpn_server.php");
$tab_array[] = array(gettext("Clients"), true, "vpn_openvpn_client.php");
$tab_array[] = array(gettext("Client Specific Overrides"), false, "vpn_openvpn_csc.php");
$tab_array[] = array(gettext("Wizards"), false, "wizard.php?xml=openvpn_wizard.xml");
add_package_tabs("OpenVPN", $tab_array);
display_top_tabs($tab_array);

if ($act=="new" || $act=="edit"):
	$form = new Form();

	$section = new Form_Section('General Information');

	$section->addInput(new Form_Checkbox(
		'disable',
		'Disabled',
		'Disable this client',
		$pconfig['disable']
	))->setHelp('Set this option to disable this client without removing it from the list.');

	$section->addInput(new Form_Select(
		'mode',
		'*Server mode',
		$pconfig['mode'],
		$openvpn_client_modes
		));

	$section->addInput(new Form_Select(
		'protocol',
		'*Protocol',
		$pconfig['protocol'],
		$openvpn_prots
		));

	$section->addInput(new Form_Select(
		'dev_mode',
		'*Device mode',
		empty($pconfig['dev_mode']) ? 'tun':$pconfig['dev_mode'],
		$openvpn_dev_mode
		))->setHelp('"tun" mode carries IPv4 and IPv6 (OSI layer 3) and is the most common and compatible mode across all platforms.%1$s' .
		    '"tap" mode is capable of carrying 802.3 (OSI Layer 2.)', '<br/>');

	$section->addInput(new Form_Select(
		'interface',
		'*Interface',
		$pconfig['interface'],
		openvpn_build_if_list()
		))->setHelp("The interface used by the firewall to originate this OpenVPN client connection");

	$section->addInput(new Form_Input(
		'local_port',
		'Local port',
		'number',
		$pconfig['local_port'],
		['min' => '0']
	))->setHelp('Set this option to bind to a specific port. Leave this blank or enter 0 for a random dynamic port.');

	$section->addInput(new Form_Input(
		'server_addr',
		'*Server host or address',
		'text',
		$pconfig['server_addr']
	))->setHelp("The IP address or hostname of the OpenVPN server.");

	$section->addInput(new Form_Input(
		'server_port',
		'*Server port',
		'number',
		$pconfig['server_port']
	))->setHelp("The port used by the server to receive client connections.");

	$section->addInput(new Form_Input(
		'proxy_addr',
		'Proxy host or address',
		'text',
		$pconfig['proxy_addr']
	))->setHelp('The address for an HTTP Proxy this client can use to connect to a remote server.%1$s' .
	    'TCP must be used for the client and server protocol.', '<br/>');

	$section->addInput(new Form_Input(
		'proxy_port',
		'Proxy port',
		'number',
		$pconfig['proxy_port']
	));

	$section->addInput(new Form_Select(
		'proxy_authtype',
		'Proxy Authentication',
		$pconfig['proxy_authtype'],
		array('none' => gettext('none'), 'basic' => gettext('basic'), 'ntlm' => gettext('ntlm'))
		))->setHelp("The type of authentication used by the proxy server.");

	$section->addInput(new Form_Input(
		'proxy_user',
		'Username',
		'text',
		$pconfig['proxy_user'],
		['autocomplete' => 'new-password']
	));

	$section->addPassword(new Form_Input(
		'proxy_passwd',
		'Password',
		'password',
		$pconfig['proxy_passwd'],
	), false);

	$section->addInput(new Form_Input(
		'description',
		'Description',
		'text',
		$pconfig['description']
	))->setHelp('A description may be entered here for administrative reference (not parsed).');

	$form->add($section);
	$section = new Form_Section('User Authentication Settings');
	$section->addClass('authentication');

	$section->addInput(new Form_Input(
		'auth_user',
		'Username',
		'text',
		$pconfig['auth_user'],
		['autocomplete' => 'new-password']
	))->setHelp('Leave empty when no user name is needed');

	$section->addPassword(new Form_Input(
		'auth_pass',
		'Password',
		'password',
		$pconfig['auth_pass']
	), false)->setHelp('Leave empty when no password is needed');

	$section->addInput(new Form_Checkbox(
		'auth-retry-none',
		'Authentication Retry',
		'Do not retry connection when authentication fails',
		$pconfig['auth-retry-none']
	))->setHelp('When enabled, the OpenVPN process will exit if it receives an authentication failure message. ' .
		    'The default behavior is to retry.%1$s%2$s%3$s', '<div class="infoblock">',
		    sprint_info_box(gettext('WARNING: If the remote server requires both a username and a password, ' .
		    'but only one is filled in, the system will hang on reboot prompting for OpenVPN Client credentials unless ' .
		    'Authentication Retry is checked.'), 'info', false), '</div>');

	$form->add($section);

	$section = new Form_Section('Cryptographic Settings');

	$section->addInput(new Form_Checkbox(
		'tlsauth_enable',
		'TLS Configuration',
		'Use a TLS Key',
		$pconfig['tlsauth_enable']
	))->setHelp("A TLS key enhances security of an OpenVPN connection by requiring both parties to have a common key before a peer can perform a TLS handshake. " .
	    "This layer of HMAC authentication allows control channel packets without the proper key to be dropped, protecting the peers from attack or unauthorized connections." .
	    "The TLS Key does not have any effect on tunnel data.");

	if (!$pconfig['tls']) {
		$section->addInput(new Form_Checkbox(
			'autotls_enable',
			null,
			'Automatically generate a TLS Key.',
			$pconfig['autotls_enable']
		));
	}

	$section->addInput(new Form_Textarea(
		'tls',
		'*TLS Key',
		$pconfig['tls']
	))->setHelp('Paste the TLS key here.%1$s' .
	    'This key is used to sign control channel packets with an HMAC signature for authentication when establishing the tunnel. ', '<br/>');

	$section->addInput(new Form_Select(
		'tls_type',
		'*TLS Key Usage Mode',
		empty($pconfig['tls_type']) ? 'auth':$pconfig['tls_type'],
		$openvpn_tls_modes
		))->setHelp('In Authentication mode the TLS key is used only as HMAC authentication for the control channel, protecting the peers from unauthorized connections. %1$s' .
		    'Encryption and Authentication mode also encrypts control channel communication, providing more privacy and traffic control channel obfuscation.', '<br/>');

	if (strlen($pconfig['tlsauth_keydir']) == 0) {
		$pconfig['tlsauth_keydir'] = "default";
	}
	$section->addInput(new Form_Select(
		'tlsauth_keydir',
		'*TLS keydir direction',
		$pconfig['tlsauth_keydir'],
		openvpn_get_keydirlist()
	))->setHelp('The TLS Key Direction must be set to complementary values on the client and server. ' .
			'For example, if the server is set to 0, the client must be set to 1. ' .
			'Both may be set to omit the direction, in which case the TLS Key will be used bidirectionally.');

	if (count($a_ca)) {
		$section->addInput(new Form_Select(
			'caref',
			'*Peer Certificate Authority',
			$pconfig['caref'],
			cert_build_list('ca', 'OpenVPN')
		));
	} else {
		$section->addInput(new Form_StaticText(
			'*Peer Certificate Authority',
			sprintf('No Certificate Authorities defined. One may be created here: %s', '<a href="system_camanager.php">System &gt; Cert. Manager</a>')
		));
	}

	if (count($a_crl)) {
		$section->addInput(new Form_Select(
			'crlref',
			'Peer Certificate Revocation list',
			$pconfig['crlref'],
			openvpn_build_crl_list()
		));
	} else {
		$section->addInput(new Form_StaticText(
			'Peer Certificate Revocation list',
			sprintf('No Certificate Revocation Lists defined. One may be created here: %s', '<a href="system_crlmanager.php">System &gt; Cert. Manager &gt; Certificate Revocation</a>')
		));
	}

	$section->addInput(new Form_Checkbox(
		'autokey_enable',
		'Auto generate',
		'Automatically generate a shared key',
		$pconfig['autokey_enable'] && empty($pconfig['shared_key'])
	));

	$section->addInput(new Form_Textarea(
		'shared_key',
		'*Shared Key',
		$pconfig['shared_key']
	))->setHelp('Paste the shared key here');

	$cl = openvpn_build_cert_list(true);

	$section->addInput(new Form_Select(
		'certref',
		'Client Certificate',
		$pconfig['certref'],
		$cl['server']
		));

	$section->addInput(new Form_Checkbox(
		'ncp_enable',
		'Data Encryption Negotiation',
		'Enable Data Encryption Negotiation',
		($pconfig['ncp_enable'] == "enabled")
	))->setHelp('This option allows OpenVPN clients and servers to negotiate a compatible set of acceptable cryptographic ' .
			'data encryption algorithms from those selected in the Data Encryption Algorithms list below. ' .
			'Disabling this feature is deprecated.');

	foreach (explode(",", $pconfig['data_ciphers']) as $cipher) {
		$data_ciphers_list[$cipher] = $cipher;
	}
	$group = new Form_Group('Data Encryption Algorithms');

	$group->add(new Form_Select(
		'availciphers',
		null,
		array(),
		openvpn_get_cipherlist(),
		true
	))->setAttribute('size', '10')
	  ->setHelp('Available Data Encryption Algorithms%1$sClick to add or remove an algorithm from the list', '<br />');

	$group->add(new Form_Select(
		'data_ciphers',
		null,
		array(),
		$data_ciphers_list,
		true
	))->setReadonly()
	  ->setAttribute('size', '10')
	  ->setHelp('Allowed Data Encryption Algorithms. Click an algorithm name to remove it from the list');

	$group->setHelp('The order of the selected Data Encryption Algorithms is respected by OpenVPN.%1$s%2$s%3$s',
					'<div class="infoblock">',
					sprint_info_box(
						gettext('For backward compatibility, when an older peer connects that does not support dynamic negotiation, OpenVPN will use the Fallback Data Encryption Algorithm ' .
							'requested by the peer so long as it is selected in this list or chosen as the Fallback Data Encryption Algorithm.'), 'info', false),
					'</div>');

	$section->add($group);

	$section->addInput(new Form_Select(
		'data_ciphers_fallback',
		'Fallback Data Encryption Algorithm',
		$pconfig['crypto'],
		openvpn_get_cipherlist()
		))->setHelp('The Fallback Data Encryption Algorithm used for data channel packets when communicating with ' .
				'clients that do not support data encryption algorithm negotiation. ' .
				'This algorithm is automatically included in the Data Encryption Algorithms list.');

	$section->addInput(new Form_Select(
		'digest',
		'*Auth digest algorithm',
		$pconfig['digest'],
		openvpn_get_digestlist()
		))->setHelp('The algorithm used to authenticate data channel packets, and control channel packets if a TLS Key is present.%1$s' .
		    'When an AEAD Encryption Algorithm mode is used, such as AES-GCM, this digest is used for the control channel only, not the data channel.%1$s' .
		    'Set this to the same value as the server. While SHA1 is the default for OpenVPN, this algorithm is insecure. ', '<br />');

	$section->addInput(new Form_Select(
		'engine',
		'Hardware Crypto',
		$pconfig['engine'],
		openvpn_get_engines()
		));

	$form->add($section);

	$section = new Form_Section('Tunnel Settings');

	$section->addInput(new Form_Input(
		'tunnel_network',
		'IPv4 Tunnel Network',
		'text',
		$pconfig['tunnel_network']
	))->setHelp('This is the IPv4 virtual network used for private communications between this client and the server ' .
				'expressed using CIDR notation (e.g. 10.0.8.0/24). The second usable address in the network will be assigned to ' .
				'the client virtual interface. Leave blank if the server is capable of providing addresses to clients.');

	$section->addInput(new Form_Input(
		'tunnel_networkv6',
		'IPv6 Tunnel Network',
		'text',
		$pconfig['tunnel_networkv6']
	))->setHelp('This is the IPv6 virtual network used for private ' .
				'communications between this client and the server expressed using CIDR notation (e.g. fe80::/64). ' .
				'When set static using this field, the ::2 address in the network will be assigned to the client virtual interface. ' .
				'Leave blank if the server is capable of providing addresses to clients.');

	$section->addInput(new Form_Input(
		'remote_network',
		'IPv4 Remote network(s)',
		'text',
		$pconfig['remote_network']
	))->setHelp('IPv4 networks that will be routed through the tunnel, so that a site-to-site VPN can be established without manually ' .
				'changing the routing tables. Expressed as a comma-separated list of one or more CIDR ranges. ' .
				'If this is a site-to-site VPN, enter the remote LAN/s here. May be left blank for non site-to-site VPN.');

	$section->addInput(new Form_Input(
		'remote_networkv6',
		'IPv6 Remote network(s)',
		'text',
		$pconfig['remote_networkv6']
	))->setHelp('These are the IPv6 networks that will be routed through the tunnel, so that a site-to-site VPN can be established without manually ' .
				'changing the routing tables. Expressed as a comma-separated list of one or more IP/PREFIX. ' .
				'If this is a site-to-site VPN, enter the remote LAN/s here. May be left blank for non site-to-site VPN.');

	$section->addInput(new Form_Input(
		'use_shaper',
		'Limit outgoing bandwidth',
		'number',
		$pconfig['use_shaper'],
		['min' => 100, 'max' => 100000000, 'placeholder' => 'Between 100 and 100,000,000 bytes/sec']
	))->setHelp('Maximum outgoing bandwidth for this tunnel. Leave empty for no limit. The input value has to be something between 100 bytes/sec and 100 Mbytes/sec (entered as bytes per second). ' .
				'Not compatible with UDP Fast I/O.');

	$section->addInput(new Form_Select(
		'allow_compression',
		'Allow Compression',
		$pconfig['allow_compression'],
		$openvpn_allow_compression
		))->setHelp('Allow compression to be used with this VPN instance. %1$s' .
				'Compression can potentially increase throughput but may allow an attacker to extract secrets if they can control ' .
				'compressed plaintext traversing the VPN (e.g. HTTP). ' .
				'Before enabling compression, consult information about the VORACLE, CRIME, TIME, and BREACH attacks against TLS ' .
				'to decide if the use case for this specific VPN is vulnerable to attack. %1$s%1$s' .
				'Asymmetric compression allows an easier transition when connecting with older peers. %1$s',
				'<br/>');

	$section->addInput(new Form_Select(
		'compression',
		'Compression',
		$pconfig['compression'],
		$openvpn_compression_modes
		))->setHelp('Deprecated. Compress tunnel packets using the LZO algorithm. %1$s' .
				'Compression can potentially dangerous and insecure. See the note on the Allow Compression option above. %1$s%1$s' .
				'Adaptive compression will dynamically disable compression for a period of time if OpenVPN detects that the data in the ' .
				'packets is not being compressed efficiently.',
				'<br/>');

	$section->addInput(new Form_Select(
		'topology',
		'Topology',
		$pconfig['topology'],
		$openvpn_topologies
	))->setHelp('Specifies the method used to configure a virtual adapter IP address.');

	$section->addInput(new Form_Checkbox(
		'passtos',
		'Type-of-Service',
		'Set the TOS IP header value of tunnel packets to match the encapsulated packet value.',
		$pconfig['passtos']
	));

	$section->addInput(new Form_Checkbox(
		'route_no_pull',
		'Don\'t pull routes',
		'Bars the server from adding routes to the client\'s routing table',
		$pconfig['route_no_pull']
	))->setHelp('This option still allows the server to set the TCP/IP properties of the client\'s TUN/TAP interface. ');

	$section->addInput(new Form_Checkbox(
		'route_no_exec',
		'Don\'t add/remove routes',
		'Don\'t add or remove routes automatically',
		$pconfig['route_no_exec']
	))->setHelp('Do not execute operating system commands to install routes. Instead, pass routes to --route-up script using environmental variables.');

	$form->add($section);

	$section = new Form_Section("Ping settings");

	$section->addInput(new Form_Input(
		'inactive_seconds',
		'Inactive',
		'number',
		$pconfig['inactive_seconds'] ?: 0,
		['min' => '0']
	    ))->setHelp('Causes OpenVPN to exit after n seconds of ' .
	    'inactivity on the TUN/TAP device.%1$s' .
	    'The time length of inactivity is measured since the last ' .
	    'incoming or outgoing tunnel packet.%1$s' .
	    '0 disables this feature.%1$s', '<br />');

	$section->addInput(new Form_Select(
		'ping_method',
		'Ping method',
		$pconfig['ping_method'],
		$openvpn_ping_method
	))->setHelp('keepalive helper uses interval and timeout parameters ' .
	    'to define ping and ping-restart values as follows:%1$s' .
	    'ping = interval%1$s' .
	    'ping-restart = timeout%1$s',
	    '<br />');

	$section->addInput(new Form_Input(
		'keepalive_interval',
		'Interval',
		'number',
		$pconfig['keepalive_interval']
		    ?: $openvpn_default_keepalive_interval,
		['min' => '0']
	));

	$section->addInput(new Form_Input(
		'keepalive_timeout',
		'Timeout',
		'number',
		$pconfig['keepalive_timeout']
		    ?: $openvpn_default_keepalive_timeout,
		['min' => '0']
	));

	$section->addInput(new Form_Input(
		'ping_seconds',
		'Ping',
		'number',
		$pconfig['ping_seconds'] ?: $openvpn_default_keepalive_interval,
		['min' => '0']
	))->setHelp('Ping remote over the TCP/UDP control channel if no ' .
	    'packets have been sent for at least n seconds.%1$s',
	    '<br />');

	$section->addInput(new Form_Select(
		'ping_action',
		'Ping restart or exit',
		$pconfig['ping_action'],
		$openvpn_ping_action
	))->setHelp('Exit or restart OpenVPN after timeout from remote%1$s',
	    '<br />');

	$section->addInput(new Form_Input(
		'ping_action_seconds',
		'Ping restart or exit seconds',
		'number',
		$pconfig['ping_action_seconds']
		    ?: $openvpn_default_keepalive_timeout,
		['min' => '0']
	));

	$form->add($section);

	$section = new Form_Section('Advanced Configuration');
	$section->addClass('advanced');

	$custops = new Form_Textarea(
		'custom_options',
		'Custom options',
		$pconfig['custom_options']
	);
	if (!$user_can_edit_advanced) {
		$custops->setDisabled();
	}
	$section->addInput($custops)->setHelp('Enter any additional options to add to the OpenVPN client configuration here, separated by semicolon.');

	$section->addInput(new Form_Checkbox(
		'udp_fast_io',
		'UDP Fast I/O',
		'Use fast I/O operations with UDP writes to tun/tap. Experimental.',
		$pconfig['udp_fast_io']
	))->setHelp('Optimizes the packet write event loop, improving CPU efficiency by 5% to 10%. ' .
		'Not compatible with all platforms, and not compatible with OpenVPN bandwidth limiting.');

	$section->addInput(new Form_Select(
		'exit_notify',
		'Exit Notify',
		$pconfig['exit_notify'],
		$openvpn_exit_notify_client
	))->setHelp('Send an explicit exit notification to connected servers/peers when restarting ' .
		'or shutting down, so they may immediately disconnect rather than waiting for a timeout. ' .
		'This value controls how many times this instance will attempt to send the exit notification.');

	$section->addInput(new Form_Select(
		'sndrcvbuf',
		'Send/Receive Buffer',
		$pconfig['sndrcvbuf'],
		openvpn_get_buffer_values()
		))->setHelp('Configure a Send and Receive Buffer size for OpenVPN. ' .
				'The default buffer size can be too small in many cases, depending on hardware and network uplink speeds. ' .
				'Finding the best buffer size can take some experimentation. To test the best value for a site, start at ' .
				'512KiB and test higher and lower values.');

	$group = new Form_Group('Gateway creation');
	$group->add(new Form_Checkbox(
		'create_gw',
		null,
		'Both',
		($pconfig['create_gw'] == "both"),
		'both'
	))->displayAsRadio();

	$group->add(new Form_Checkbox(
		'create_gw',
		null,
		'IPv4 only',
		($pconfig['create_gw'] == "v4only"),
		'v4only'
	))->displayAsRadio();

	$group->add(new Form_Checkbox(
		'create_gw',
		null,
		'IPv6 only',
		($pconfig['create_gw'] == "v6only"),
		'v6only'
	))->displayAsRadio();

	$group->setHelp('If you assign a virtual interface to this OpenVPN client, ' .
		'this setting controls which gateway types will be created. The default ' .
		'setting is \'both\'.');

	$section->add($group);

	$section->addInput(new Form_Select(
		'verbosity_level',
		'Verbosity level',
		$pconfig['verbosity_level'],
		$openvpn_verbosity_level
		))->setHelp('Each level shows all info from the previous levels. Level 3 is recommended for a good summary of what\'s happening without being swamped by output.%1$s%1$s' .
					'None: Only fatal errors%1$s' .
					'Default through 4: Normal usage range%1$s' .
					'5: Output R and W characters to the console for each packet read and write. Uppercase is used for TCP/UDP packets and lowercase is used for TUN/TAP packets.%1$s' .
					'6-11: Debug info range', '<br />');

	$form->addGlobal(new Form_Input(
		'act',
		null,
		'hidden',
		$act
	));

	if (isset($id) && $a_client[$id]) {
		$form->addGlobal(new Form_Input(
			'id',
			null,
			'hidden',
			$id
		));
	}

	if (isset($parentid)) {
		$form->addGlobal(new Form_Input(
			'parentid',
			null,
			'hidden',
			$parentid
		));
	}

	$form->add($section);
	print($form);
else:
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('OpenVPN Clients')?></h2></div>
		<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap table-rowdblclickedit" data-sortable>
			<thead>
				<tr>
					<th><?=gettext("Interface")?></th>
					<th data-sortable-type="alpha"><?=gettext("Protocol")?></th>
					<th><?=gettext("Server")?></th>
					<th><?=gettext("Mode / Crypto")?></th>
					<th><?=gettext("Description")?></th>
					<th><?=gettext("Actions")?></th>
				</tr>
			</thead>

			<tbody>
<?php
	$i = 0;
	foreach ($a_client as $client):
		$server = "{$client['server_addr']}:{$client['server_port']}";
		$dc = openvpn_build_data_cipher_list($client['data_ciphers'], $client['data_ciphers_fallback'], ($client['ncp_enable'] != "disabled"));
		$dca = explode(',', $dc);
		if (count($dca) > 5) {
			$dca = array_slice($dca, 0, 5);
			$dca[] = '[...]';
		}
		$dc = implode(', ', $dca);
?>
				<tr <?=isset($client['disable']) ? 'class="disabled"':''?>>
					<td>
						<?=convert_openvpn_interface_to_friendly_descr($client['interface'])?>
					</td>
					<td>
						<?=htmlspecialchars($client['protocol'])?>
						<br/>(<?= htmlspecialchars(strtoupper(empty($client['dev_mode']) ? 'TUN' : $client['dev_mode'])) ?>)
					</td>
					<td>
						<?=htmlspecialchars($server)?>
					</td>
					<td>
						<strong><?= gettext('Mode') ?>:</strong> <?= htmlspecialchars($openvpn_client_modes[$client['mode']]) ?>
						<br/>
						<strong><?= gettext('Data Ciphers') ?>:</strong> <?= htmlspecialchars($dc) ?>
						<br/>
						<strong><?= gettext('Digest') ?>:</strong> <?= htmlspecialchars($client['digest']) ?>
					<?php if (!empty($client['dh_length'])): ?>
						<br/>
						<strong><?= gettext('D-H Params') ?>:</strong>
						<?php if (is_numeric($client['dh_length'])): ?>
							<?= htmlspecialchars($client['dh_length']) ?> <?= gettext('bits') ?>
						<?php elseif ($client['dh_length'] == "none"): ?>
							<?= gettext("Disabled, ECDH Only") ?>
						<?php endif; ?>
					<?php endif; ?>
					</td>
					<td>
						<?=htmlspecialchars($client['description'])?>
					</td>
					<td>
						<a class="fa fa-pencil"	title="<?=gettext('Edit Client')?>"	href="vpn_openvpn_client.php?act=edit&amp;id=<?=$i?>"></a>
						<a class="fa fa-clone"	title="<?=gettext("Copy Client")?>"	href="vpn_openvpn_client.php?act=dup&amp;id=<?=$i?>" usepost></a>
						<a class="fa fa-trash"	title="<?=gettext('Delete Client')?>"	href="vpn_openvpn_client.php?act=del&amp;id=<?=$i?>" usepost></a>
					</td>
				</tr>
<?php
		$i++;
	endforeach;
?>
			</tbody>
		</table>
	</div>
</div>

<nav class="action-buttons">
	<a href="vpn_openvpn_client.php?act=new" class="btn btn-sm btn-success">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>

<?php
endif;

// Note:
// The following *_change() functions were converted from Javascript/DOM to JQuery but otherwise
// mostly left unchanged. The logic on this form is complex and this works!
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	function mode_change() {
		switch ($('#mode').val()) {
			case "p2p_tls":
				hideCheckbox('tlsauth_enable', false);
				hideInput('caref', false);
				hideInput('certref', false);
				hideClass('authentication', false);
				hideCheckbox('autokey_enable', true);
				hideInput('shared_key', true);
				hideLabel('Peer Certificate Revocation list', false);
				hideInput('crlref', false);
				hideInput('topology', false);
				hideCheckbox('route_no_pull', false);
				break;
			case "p2p_shared_key":
				hideCheckbox('tlsauth_enable', true);
				hideInput('caref', true);
				hideInput('certref', true);
				hideClass('authentication', true);
				hideCheckbox('autokey_enable', false);
				hideInput('shared_key', false);
				hideLabel('Peer Certificate Revocation list', true);
				hideInput('crlref', true);
				hideInput('topology', true);
				hideCheckbox('route_no_pull', true);
				break;
		}

		tlsauth_change();
		autokey_change();
		dev_mode_change();

	}

	function dev_mode_change() {
		hideInput('topology',  ($('#dev_mode').val() == 'tap') || $('#mode').val() == "p2p_shared_key");
	}

	function protocol_change() {
		hideInput('interface', (($('#protocol').val().toLowerCase() == 'udp') || ($('#protocol').val().toLowerCase() == 'tcp')));
		var notudp = !($('#protocol').val().substring(0, 3).toLowerCase() == 'udp');
		hideCheckbox('udp_fast_io', notudp);
		hideInput('exit_notify', notudp);
	}

	// Process "Automatically generate a shared key" checkbox
	function autokey_change() {
		hideInput('shared_key', $('#autokey_enable').prop('checked'));
	}

	function useproxy_changed() {
		hideInput('proxy_user', ($('#proxy_authtype').val() == 'none'));
		hideInput('proxy_passwd', ($('#proxy_authtype').val() == 'none'));
	}

	// Process "Enable authentication of TLS packets" checkbox
	function tlsauth_change() {
		hideCheckbox('autotls_enable', !($('#tlsauth_enable').prop('checked'))  || ($('#mode').val() == 'p2p_shared_key'));
		autotls_change();
	}

	// Process "Automatically generate a shared TLS authentication key" checkbox
	function autotls_change() {
		hideInput('tls', $('#autotls_enable').prop('checked') || !$('#tlsauth_enable').prop('checked') || ($('#mode').val() == 'p2p_shared_key'));
		hideInput('tls_type', $('#autotls_enable').prop('checked') || !$('#tlsauth_enable').prop('checked') || ($('#mode').val() == 'p2p_shared_key'));
	}

	function ping_method_change() {
		pvalue = $('#ping_method').val();

		keepalive = (pvalue == 'keepalive');

		hideInput('keepalive_interval', !keepalive);
		hideInput('keepalive_timeout', !keepalive);
		hideInput('ping_seconds', keepalive);
		hideInput('ping_action', keepalive);
		hideInput('ping_action_seconds', keepalive);
	}

	function allow_compression_change() {
		var hide  = ($('#allow_compression').val() == 'no')
		hideInput('compression', hide);
	}

	// ---------- Monitor elements for change and call the appropriate display functions ------------------------------

	 // TLS Authorization
	$('#tlsauth_enable').click(function () {
		tlsauth_change();
	});

	 // Auto key
	$('#autokey_enable').click(function () {
		autokey_change();
	});

	 // Mode
	$('#mode').change(function () {
		mode_change();
	});

	// Protocol
	$('#protocol').change(function () {
		protocol_change();
	});

	 // Use proxy
	$('#proxy_authtype').change(function () {
		useproxy_changed();
	});

	 // Tun/tap
	$('#dev_mode').change(function () {
		dev_mode_change();
	});

	// ping
	$('#ping_method').change(function () {
		ping_method_change();
	});

	 // Auto TLS
	$('#autotls_enable').click(function () {
		autotls_change();
	});

	// Compression Settings
	$('#allow_compression').change(function () {
		allow_compression_change();
	});

	function updateCipher(mem) {
		var found = false;

		// If the cipher exists, remove it
		$('[id="data_ciphers[]"] option').each(function() {
			if($(this).val().toString() == mem) {
				$(this).remove();
				found = true;
			}
		});

		// If not, add it
		if (!found) {
			$('[id="data_ciphers[]"]').append(new Option(mem , mem));
		}
	}

	function updateCiphers(mem) {
		mem.toString().split(",").forEach(updateCipher);

		// Unselect all options
		$('[id="availciphers[]"] option:selected').removeAttr("selected");
	}

	// On click, update the ciphers list
	$('[id="availciphers[]"]').click(function () {
		updateCiphers($(this).val());
	});

	// On click, remove the cipher from the list
	$('[id="data_ciphers[]"]').click(function () {
		if ($(this).val() != null) {
			updateCiphers($(this).val());
		}
	});

	// Make sure the "Available ciphers" selector is not submitted with the form,
	// and select all of the chosen ciphers so that they are submitted
	$('form').submit(function() {
		$("#availciphers" ).prop( "disabled", true);
		$('[id="data_ciphers[]"] option').attr("selected", "selected");
	});

	// ---------- Set initial page display state ----------------------------------------------------------------------
	mode_change();
	protocol_change();
	autokey_change();
	tlsauth_change();
	useproxy_changed();
	ping_method_change();
	allow_compression_change();
});
//]]>
</script>

<?php include("foot.inc");
