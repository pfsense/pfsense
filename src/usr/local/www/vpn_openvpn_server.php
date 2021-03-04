<?php
/*
 * vpn_openvpn_server.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-openvpn-server
##|*NAME=OpenVPN: Servers
##|*DESCR=Allow access to the 'OpenVPN: Servers' page.
##|*MATCH=vpn_openvpn_server.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("openvpn.inc");
require_once("pfsense-utils.inc");
require_once("pkg-utils.inc");

global $openvpn_topologies, $openvpn_tls_modes, $openvpn_exit_notify_server;

init_config_arr(array('openvpn', 'openvpn-server'));
$a_server = &$config['openvpn']['openvpn-server'];

init_config_arr(array('ca'));
$a_ca = &$config['ca'];

init_config_arr(array('cert'));
$a_cert = &$config['cert'];

init_config_arr(array('crl'));
$a_crl = &$config['crl'];

foreach ($a_crl as $cid => $acrl) {
	if (!isset($acrl['refid'])) {
		unset ($a_crl[$cid]);
	}
}

if (isset($_REQUEST['id']) && is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

if (isset($_REQUEST['act'])) {
	$act = $_REQUEST['act'];
}

$user_entry = getUserEntry($_SESSION['Username']);
$user_can_edit_advanced = (isAdminUID($_SESSION['Username']) || userHasPrivilege($user_entry, "page-openvpn-server-advanced") || userHasPrivilege($user_entry, "page-all"));

if (isset($id) && $a_server[$id]) {
	$vpnid = $a_server[$id]['vpnid'];
} else {
	$vpnid = 0;
}

if ($_POST['act'] == "del") {

	if (!isset($a_server[$id])) {
		pfSenseHeader("vpn_openvpn_server.php");
		exit;
	}

	if (empty($a_server[$id])) {
		$wc_msg = gettext('Deleted empty OpenVPN server');
	} elseif (!$user_can_edit_advanced && !empty($a_server[$id]['custom_options'])) {
		$input_errors[] = gettext("This user does not have sufficient privileges to delete an instance with Advanced options set.");
	} else {
		openvpn_delete('server', $a_server[$id]);
		$wc_msg = sprintf(gettext('Deleted OpenVPN server from %1$s:%2$s %3$s'), convert_friendly_interface_to_friendly_descr($a_server[$id]['interface']), $a_server[$id]['local_port'], $a_server[$id]['description']);
	}
	if (!empty($wc_msg)) {
		unset($a_server[$id]);
		write_config($wc_msg);
		$savemsg = gettext("Server successfully deleted.");
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
	$pconfig['dh_length'] = 2048;
	$pconfig['dev_mode'] = "tun";
	$pconfig['interface'] = "wan";
	$pconfig['local_port'] = openvpn_port_next('UDP');
	$pconfig['cert_depth'] = 1;
	$pconfig['create_gw'] = "both"; // v4only, v6only, or both (default: both)
	$pconfig['verbosity_level'] = 1; // Default verbosity is 1
	$pconfig['digest'] = "SHA256";
	$pconfig['allow_compression'] = "no";
	$pconfig['compression'] = "";
}

if (($act == "edit") || ($act == "dup")) {

	if (isset($id) && $a_server[$id]) {
		$pconfig['disable'] = isset($a_server[$id]['disable']);
		$pconfig['mode'] = $a_server[$id]['mode'];
		$pconfig['protocol'] = $a_server[$id]['protocol'];
		$pconfig['authmode'] = $a_server[$id]['authmode'];
		if (isset($a_server[$id]['data_ciphers'])) {
			$pconfig['data_ciphers'] = $a_server[$id]['data_ciphers'];
		} else {
			$pconfig['data_ciphers'] = 'AES-256-GCM,AES-128-GCM,CHACHA20-POLY1305';
		}
		if (isset($a_server[$id]['ncp_enable'])) {
			$pconfig['ncp_enable'] = $a_server[$id]['ncp_enable'];
		} else {
			$pconfig['ncp_enable'] = "enabled";
		}
		$pconfig['dev_mode'] = $a_server[$id]['dev_mode'];
		$pconfig['interface'] = $a_server[$id]['interface'];

		if (!empty($a_server[$id]['ipaddr'])) {
			$pconfig['interface'] = $pconfig['interface'] . '|' . $a_server[$id]['ipaddr'];
		}

		$pconfig['local_port'] = $a_server[$id]['local_port'];
		$pconfig['description'] = $a_server[$id]['description'];
		$pconfig['custom_options'] = $a_server[$id]['custom_options'];

		if ($pconfig['mode'] != "p2p_shared_key") {
			if ($a_server[$id]['tls']) {
				$pconfig['tlsauth_enable'] = "yes";
				$pconfig['tls'] = base64_decode($a_server[$id]['tls']);
				$pconfig['tls_type'] = $a_server[$id]['tls_type'];
				$pconfig['tlsauth_keydir'] = $a_server[$id]['tlsauth_keydir'];
			}

			$pconfig['caref'] = $a_server[$id]['caref'];
			$pconfig['crlref'] = $a_server[$id]['crlref'];
			if (isset($a_server[$id]['ocspcheck'])) {
				$pconfig['ocspcheck'] = "yes";
			}
			$pconfig['ocspurl'] = $a_server[$id]['ocspurl'];
			$pconfig['certref'] = $a_server[$id]['certref'];
			$pconfig['dh_length'] = $a_server[$id]['dh_length'];
			$pconfig['ecdh_curve'] = $a_server[$id]['ecdh_curve'];
			if (isset($a_server[$id]['cert_depth'])) {
				$pconfig['cert_depth'] = $a_server[$id]['cert_depth'];
			} else {
				$pconfig['cert_depth'] = 1;
			}
			if ($pconfig['mode'] == "server_tls_user") {
				$pconfig['strictusercn'] = $a_server[$id]['strictusercn'];
			}
		} else {
			$pconfig['shared_key'] = base64_decode($a_server[$id]['shared_key']);
		}
		$pconfig['data_ciphers_fallback'] = $a_server[$id]['data_ciphers_fallback'];
		$pconfig['digest'] = !empty($a_server[$id]['digest']) ? $a_server[$id]['digest'] : "SHA256";
		$pconfig['engine'] = $a_server[$id]['engine'];

		$pconfig['tunnel_network'] = $a_server[$id]['tunnel_network'];
		$pconfig['tunnel_networkv6'] = $a_server[$id]['tunnel_networkv6'];

		$pconfig['remote_network'] = $a_server[$id]['remote_network'];
		$pconfig['remote_networkv6'] = $a_server[$id]['remote_networkv6'];
		$pconfig['gwredir'] = $a_server[$id]['gwredir'];
		$pconfig['gwredir6'] = $a_server[$id]['gwredir6'];
		$pconfig['local_network'] = $a_server[$id]['local_network'];
		$pconfig['local_networkv6'] = $a_server[$id]['local_networkv6'];
		$pconfig['maxclients'] = $a_server[$id]['maxclients'];
		$pconfig['allow_compression'] = $a_server[$id]['allow_compression'];
		$pconfig['compression'] = $a_server[$id]['compression'];
		$pconfig['compression_push'] = $a_server[$id]['compression_push'];
		$pconfig['passtos'] = $a_server[$id]['passtos'];
		$pconfig['client2client'] = $a_server[$id]['client2client'];

		$pconfig['dynamic_ip'] = $a_server[$id]['dynamic_ip'];
		$pconfig['topology'] = $a_server[$id]['topology'];

		$pconfig['serverbridge_dhcp'] = $a_server[$id]['serverbridge_dhcp'];
		$pconfig['serverbridge_interface'] = $a_server[$id]['serverbridge_interface'];
		$pconfig['serverbridge_routegateway'] = $a_server[$id]['serverbridge_routegateway'];
		$pconfig['serverbridge_dhcp_start'] = $a_server[$id]['serverbridge_dhcp_start'];
		$pconfig['serverbridge_dhcp_end'] = $a_server[$id]['serverbridge_dhcp_end'];

		$pconfig['dns_domain'] = $a_server[$id]['dns_domain'];
		if ($pconfig['dns_domain']) {
			$pconfig['dns_domain_enable'] = true;
		}

		$pconfig['dns_server1'] = $a_server[$id]['dns_server1'];
		$pconfig['dns_server2'] = $a_server[$id]['dns_server2'];
		$pconfig['dns_server3'] = $a_server[$id]['dns_server3'];
		$pconfig['dns_server4'] = $a_server[$id]['dns_server4'];

		if ($pconfig['dns_server1'] ||
		    $pconfig['dns_server2'] ||
		    $pconfig['dns_server3'] ||
		    $pconfig['dns_server4']) {
			$pconfig['dns_server_enable'] = true;
		}

		$pconfig['ntp_server1'] = $a_server[$id]['ntp_server1'];
		$pconfig['ntp_server2'] = $a_server[$id]['ntp_server2'];

		if ($pconfig['ntp_server1'] ||
		    $pconfig['ntp_server2']) {
			$pconfig['ntp_server_enable'] = true;
		}

		$pconfig['netbios_enable'] = $a_server[$id]['netbios_enable'];
		$pconfig['netbios_ntype'] = $a_server[$id]['netbios_ntype'];
		$pconfig['netbios_scope'] = $a_server[$id]['netbios_scope'];

		$pconfig['wins_server1'] = $a_server[$id]['wins_server1'];
		$pconfig['wins_server2'] = $a_server[$id]['wins_server2'];

		if ($pconfig['wins_server1'] ||
		    $pconfig['wins_server2']) {
			$pconfig['wins_server_enable'] = true;
		}

		$pconfig['nbdd_server1'] = $a_server[$id]['nbdd_server1'];
		if ($pconfig['nbdd_server1']) {
			$pconfig['nbdd_server_enable'] = true;
		}

		// just in case the modes switch
		$pconfig['autokey_enable'] = "yes";
		$pconfig['autotls_enable'] = "yes";

		$pconfig['duplicate_cn'] = isset($a_server[$id]['duplicate_cn']);

		if (isset($a_server[$id]['create_gw'])) {
			$pconfig['create_gw'] = $a_server[$id]['create_gw'];
		} else {
			$pconfig['create_gw'] = "both"; // v4only, v6only, or both (default: both)
		}

		if (isset($a_server[$id]['verbosity_level'])) {
			$pconfig['verbosity_level'] = $a_server[$id]['verbosity_level'];
		} else {
			$pconfig['verbosity_level'] = 1; // Default verbosity is 1
		}

		$pconfig['push_blockoutsidedns'] = $a_server[$id]['push_blockoutsidedns'];
		$pconfig['username_as_common_name']  = ($a_server[$id]['username_as_common_name'] != 'disabled');
		$pconfig['udp_fast_io'] = $a_server[$id]['udp_fast_io'];
		$pconfig['exit_notify'] = $a_server[$id]['exit_notify'];
		$pconfig['sndrcvbuf'] = $a_server[$id]['sndrcvbuf'];
		$pconfig['push_register_dns'] = $a_server[$id]['push_register_dns'];

		$pconfig['ping_method'] = $a_server[$id]['ping_method'];
		$pconfig['keepalive_interval'] = $a_server[$id]['keepalive_interval'];
		$pconfig['keepalive_timeout'] = $a_server[$id]['keepalive_timeout'];
		$pconfig['ping_seconds'] = $a_server[$id]['ping_seconds'];
		$pconfig['ping_push'] = empty($a_server[$id]['ping_push']) ? '' : 'yes';
		$pconfig['ping_action'] = $a_server[$id]['ping_action'];
		$pconfig['ping_action_seconds'] = $a_server[$id]['ping_action_seconds'];
		$pconfig['ping_action_push'] = empty($a_server[$id]['ping_action_push']) ? '' : 'yes';
		$pconfig['inactive_seconds'] = $a_server[$id]['inactive_seconds'] ?: 0;
	}
}

if ($act == "dup") {
	$act = "new";
	$pconfig['local_port'] = openvpn_port_next('UDP');
	$vpnid = 0;
	unset($id);
}

if ($_POST['save']) {
	unset($input_errors);
	$pconfig = $_POST;

	if (isset($id) && $a_server[$id]) {
		$vpnid = $a_server[$id]['vpnid'];
	} else {
		$vpnid = 0;
	}

	$pconfig['ncp_enable'] = ($pconfig['ncp_enable'] == 'yes') ? 'enabled' : 'disabled';

	if (isset($pconfig['custom_options']) &&
	    ($pconfig['custom_options'] != $a_server[$id]['custom_options']) &&
	    !$user_can_edit_advanced) {
		$input_errors[] = gettext("This user does not have sufficient privileges to edit Advanced options on this instance.");
	}
	if (!$user_can_edit_advanced && !empty($a_server[$id]['custom_options'])) {
		$pconfig['custom_options'] = $a_server[$id]['custom_options'];
	}

	$cipher_validation_list = array_keys(openvpn_get_cipherlist());
	if (!in_array($pconfig['data_ciphers_fallback'], $cipher_validation_list)) {
		$input_errors[] = gettext("The selected Fallback Data Encryption Algorithm is not valid.");
	}

	/* Maximum option line length = 256, see https://redmine.pfsense.org/issues/11559 */
	if (!empty($pconfig['data_ciphers']) && (strlen("data-ciphers " . implode(",", $pconfig['data_ciphers'])) > 254)) {
		$input_errors[] = gettext("Too many Data Encryption Algorithms have been selected.");
	}

	list($iv_iface, $iv_ip) = explode ("|", $pconfig['interface']);
	if (is_ipaddrv4($iv_ip) && (stristr($pconfig['protocol'], "6") !== false)) {
		$input_errors[] = gettext("Protocol and IP address families do not match. An IPv6 protocol and an IPv4 IP address cannot be selected.");
	} elseif (is_ipaddrv6($iv_ip) && (stristr($pconfig['protocol'], "6") === false)) {
		$input_errors[] = gettext("Protocol and IP address families do not match. An IPv4 protocol and an IPv6 IP address cannot be selected.");
	} elseif ((stristr($pconfig['protocol'], "6") === false) && !get_interface_ip($iv_iface) && ($pconfig['interface'] != "any")) {
		// If an underlying interface to be used by this server uses DHCP, then it may not have received an IP address yet.
		// So in that case we do not report a problem.
		if (!interface_has_dhcp($iv_iface, 4)) {
			$input_errors[] = gettext("An IPv4 protocol was selected, but the selected interface has no IPv4 address.");
		}
	} elseif ((stristr($pconfig['protocol'], "6") !== false) && !get_interface_ipv6($iv_iface) && ($pconfig['interface'] != "any")) {
		// If an underlying interface to be used by this server uses DHCP6, then it may not have received an IP address yet.
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

	if (empty($pconfig['authmode']) && (($pconfig['mode'] == "server_user") || ($pconfig['mode'] == "server_tls_user"))) {
		$input_errors[] = gettext("A Backend for Authentication must be selected if the server mode requires User Auth.");
	}

	/* input validation */
	if ($result = openvpn_validate_port($pconfig['local_port'], 'Local port', 1)) {
		$input_errors[] = $result;
	}

	/* Maximum option line length = 256, see https://redmine.pfsense.org/issues/11104 */
	if (!empty($pconfig['authmode']) && is_port($pconfig['local_port'])) {
		$strictusercn = "false";
		if ($pconfig['strictusercn']) {
			$strictusercn = "true";
		}
		$authstring = openvpn_authscript_string(implode(',', $pconfig['authmode']),
			    $strictusercn, $vpnid, $pconfig['local_port']);
		if (strlen($authstring) > 254) {
			$input_errors[] = gettext("Too many Authentication Backends have been selected or their names are too long.");
		}
	}

	if ($result = openvpn_validate_cidr($pconfig['tunnel_network'], 'IPv4 Tunnel Network', false, "ipv4")) {
		$input_errors[] = $result;
	}

	if (!empty($pconfig['tunnel_network']) &&
	    (!isset($a_server[$id]) ||
	    ($a_server[$id]['tunnel_network'] != $pconfig['tunnel_network'])) &&
	    openvpn_is_tunnel_network_in_use($pconfig['tunnel_network'])) {
		$input_errors[] = gettext("The submitted IPv4 Tunnel Network is already in use.");
	}

	if ($result = openvpn_validate_cidr($pconfig['tunnel_networkv6'], 'IPv6 Tunnel Network', false, "ipv6")) {
		$input_errors[] = $result;
	}

	if (!empty($pconfig['tunnel_networkv6']) &&
	    (!isset($a_server[$id]) ||
	    ($a_server[$id]['tunnel_networkv6'] != $pconfig['tunnel_networkv6'])) &&
	    openvpn_is_tunnel_network_in_use($pconfig['tunnel_networkv6'])) {
		$input_errors[] = gettext("The submitted IPv6 Tunnel Network is already in use.");
	}

	if ($result = openvpn_validate_cidr($pconfig['remote_network'], 'IPv4 Remote Network', true, "ipv4")) {
		$input_errors[] = $result;
	}

	if ($result = openvpn_validate_cidr($pconfig['remote_networkv6'], 'IPv6 Remote Network', true, "ipv6")) {
		$input_errors[] = $result;
	}

	if ($result = openvpn_validate_cidr($pconfig['local_network'], 'IPv4 Local Network', true, "ipv4")) {
		$input_errors[] = $result;
	}

	if ($result = openvpn_validate_cidr($pconfig['local_networkv6'], 'IPv6 Local Network', true, "ipv6")) {
		$input_errors[] = $result;
	}

	$portused = openvpn_port_used($pconfig['protocol'], $pconfig['interface'], $pconfig['local_port'], $vpnid);
	if (($portused != $vpnid) && ($portused != 0)) {
		$input_errors[] = gettext("The specified 'Local port' is in use. Please select another value");
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

	if ($pconfig['dns_server_enable']) {
		if (!empty($pconfig['dns_server1']) && !is_ipaddr(trim($pconfig['dns_server1']))) {
			$input_errors[] = gettext("The field 'DNS Server #1' must contain a valid IPv4 or IPv6 address");
		}
		if (!empty($pconfig['dns_server2']) && !is_ipaddr(trim($pconfig['dns_server2']))) {
			$input_errors[] = gettext("The field 'DNS Server #2' must contain a valid IPv4 or IPv6 address");
		}
		if (!empty($pconfig['dns_server3']) && !is_ipaddr(trim($pconfig['dns_server3']))) {
			$input_errors[] = gettext("The field 'DNS Server #3' must contain a valid IPv4 or IPv6 address");
		}
		if (!empty($pconfig['dns_server4']) && !is_ipaddr(trim($pconfig['dns_server4']))) {
			$input_errors[] = gettext("The field 'DNS Server #4' must contain a valid IPv4 or IPv6 address");
		}
	}

	if ($pconfig['ntp_server_enable']) {
		if (!empty($pconfig['ntp_server1']) && !is_ipaddr(trim($pconfig['ntp_server1']))) {
			$input_errors[] = gettext("The field 'NTP Server #1' must contain a valid IP address");
		}
		if (!empty($pconfig['ntp_server2']) && !is_ipaddr(trim($pconfig['ntp_server2']))) {
			$input_errors[] = gettext("The field 'NTP Server #2' must contain a valid IP address");
		}
		if (!empty($pconfig['ntp_server3']) && !is_ipaddr(trim($pconfig['ntp_server3']))) {
			$input_errors[] = gettext("The field 'NTP Server #3' must contain a valid IP address");
		}
		if (!empty($pconfig['ntp_server4']) && !is_ipaddr(trim($pconfig['ntp_server4']))) {
			$input_errors[] = gettext("The field 'NTP Server #4' must contain a valid IP address");
		}
	}

	if ($pconfig['netbios_enable']) {
		if ($pconfig['wins_server_enable']) {
			if (!empty($pconfig['wins_server1']) && !is_ipaddr(trim($pconfig['wins_server1']))) {
				$input_errors[] = gettext("The field 'WINS Server #1' must contain a valid IP address");
			}
			if (!empty($pconfig['wins_server2']) && !is_ipaddr(trim($pconfig['wins_server2']))) {
				$input_errors[] = gettext("The field 'WINS Server #2' must contain a valid IP address");
			}
		}
		if ($pconfig['nbdd_server_enable']) {
			if (!empty($pconfig['nbdd_server1']) && !is_ipaddr(trim($pconfig['nbdd_server1']))) {
				$input_errors[] = gettext("The field 'NetBIOS Data Distribution Server #1' must contain a valid IP address");
			}
		}
	}

	if ($pconfig['maxclients'] && !is_numericint($pconfig['maxclients'])) {
		$input_errors[] = gettext("The field 'Concurrent connections' must be numeric.");
	}

	if (!array_key_exists($pconfig['topology'], $openvpn_topologies)) {
		$input_errors[] = gettext("The field 'Topology' contains an invalid selection");
	}

	/* If we are not in shared key mode, then we need the CA/Cert. */
	if ($pconfig['mode'] != "p2p_shared_key") {
		if (empty(trim($pconfig['certref']))) {
			$input_errors[] = gettext("The selected certificate is not valid");
		}

		if (!empty($pconfig['dh_length']) && !in_array($pconfig['dh_length'], array_keys($openvpn_dh_lengths))) {
			$input_errors[] = gettext("The specified DH Parameter length is invalid or " .
				"the DH file does not exist.");
		}

		if (!empty($pconfig['ecdh_curve']) && !openvpn_validate_curve($pconfig['ecdh_curve'])) {
			$input_errors[] = gettext("The specified ECDH Curve is invalid.");
		}
		$reqdfields = explode(" ", "caref certref");
		$reqdfieldsn = array(gettext("Certificate Authority"), gettext("Certificate"));

		if (($pconfig['ncp_enable'] != "disabled") && !empty($pconfig['data_ciphers']) && is_array($pconfig['data_ciphers'])) {
			foreach ($pconfig['data_ciphers'] as $dc) {
				if (!in_array(trim($dc), $cipher_validation_list)) {
					$input_errors[] = gettext("One or more of the selected Data Encryption Algorithms is not valid.");
				}
			}
		}
	} elseif (!$pconfig['autokey_enable']) {
		/* We only need the shared key filled in if we are in shared key mode and autokey is not selected. */
		$reqdfields = array('shared_key');
		$reqdfieldsn = array(gettext('Shared key'));
	}

	if (($pconfig['mode'] == "p2p_shared_key") && strstr($pconfig['data_ciphers_fallback'], "GCM")) {
		$input_errors[] = gettext("GCM Encryption Algorithms cannot be used with Shared Key mode.");
	}

	if ($pconfig['dev_mode'] == "tap") {
		if ($pconfig['serverbridge_dhcp'] && $pconfig['tunnel_network']) {
			$input_errors[] = gettext("Using a tunnel network and server bridge settings together is not allowed.");
		}
		if (($pconfig['serverbridge_dhcp'] && $pconfig['serverbridge_routegateway']) &&
		    ((empty($pconfig['serverbridge_interface'])) || (strcmp($pconfig['serverbridge_interface'], "none") == 0))) {
			$input_errors[] = gettext("Bridge Route Gateway requires a valid Bridge Interface.");
		}
		if (($pconfig['serverbridge_dhcp_start'] && !$pconfig['serverbridge_dhcp_end']) ||
		    (!$pconfig['serverbridge_dhcp_start'] && $pconfig['serverbridge_dhcp_end'])) {
			$input_errors[] = gettext("Server Bridge DHCP Start and End must both be empty, or defined.");
		}
		if (($pconfig['serverbridge_dhcp_start'] && !is_ipaddrv4($pconfig['serverbridge_dhcp_start']))) {
			$input_errors[] = gettext("Server Bridge DHCP Start must be an IPv4 address.");
		}
		if (($pconfig['serverbridge_dhcp_end'] && !is_ipaddrv4($pconfig['serverbridge_dhcp_end']))) {
			$input_errors[] = gettext("Server Bridge DHCP End must be an IPv4 address.");
		}
		if (ip_greater_than($pconfig['serverbridge_dhcp_start'], $pconfig['serverbridge_dhcp_end'])) {
			$input_errors[] = gettext("The Server Bridge DHCP range is invalid (start higher than end).");
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
		if (!array_key_exists($pconfig['exit_notify'], $openvpn_exit_notify_server)) {
			$input_errors[] = gettext("The Exit Notify value is invalid.");
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
	if (!empty($pconfig['ocspurl']) && !is_URL($pconfig['ocspurl'])) {
		$input_errors[] = gettext("OCSP URL must be a valid URL address.");
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!$input_errors) {

		$server = array();

		if (isset($id) && $a_server[$id] &&
		    $pconfig['dev_mode'] <> $a_server[$id]['dev_mode']) {
			/*
			 * delete old interface so a new TUN or TAP interface
			 * can be created.
			 */
			openvpn_delete('server', $a_server[$id]);
		}

		if ($vpnid) {
			$server['vpnid'] = $vpnid;
		} else {
			$server['vpnid'] = openvpn_vpnid_next();
		}

		if ($_POST['disable'] == "yes") {
			$server['disable'] = true;
		}
		$server['mode'] = $pconfig['mode'];
		if (!empty($pconfig['authmode']) && (($pconfig['mode'] == "server_user") || ($pconfig['mode'] == "server_tls_user"))) {
			$server['authmode'] = implode(",", $pconfig['authmode']);
		}
		$server['protocol'] = $pconfig['protocol'];
		$server['dev_mode'] = $pconfig['dev_mode'];
		list($server['interface'], $server['ipaddr']) = explode ("|", $pconfig['interface']);
		$server['local_port'] = $pconfig['local_port'];
		$server['description'] = $pconfig['description'];
		$server['custom_options'] = str_replace("\r\n", "\n", $pconfig['custom_options']);

		if ($tls_mode) {
			if ($pconfig['tlsauth_enable']) {
				if ($pconfig['autotls_enable']) {
					$pconfig['tls'] = openvpn_create_key();
				}
				$server['tls'] = base64_encode($pconfig['tls']);
				$server['tls_type'] = $pconfig['tls_type'];
				$server['tlsauth_keydir'] = $pconfig['tlsauth_keydir'];
			}
			$server['caref'] = $pconfig['caref'];
			$server['crlref'] = $pconfig['crlref'];
			if ($pconfig['ocspcheck']) {
				$server['ocspcheck'] = "yes";
			}
			$server['ocspurl'] = $pconfig['ocspurl'];
			$server['certref'] = $pconfig['certref'];
			$server['dh_length'] = $pconfig['dh_length'];
			$server['ecdh_curve'] = $pconfig['ecdh_curve'];
			$server['cert_depth'] = $pconfig['cert_depth'];
			if ($pconfig['mode'] == "server_tls_user") {
				$server['strictusercn'] = $pconfig['strictusercn'];
			}
		} else {
			$server['shared_key'] = base64_encode($pconfig['shared_key']);
		}

		$server['data_ciphers_fallback'] = $pconfig['data_ciphers_fallback'];
		$server['digest'] = $pconfig['digest'];
		$server['engine'] = $pconfig['engine'];

		$server['tunnel_network'] = trim($pconfig['tunnel_network']);
		$server['tunnel_networkv6'] = trim($pconfig['tunnel_networkv6']);
		$server['remote_network'] = $pconfig['remote_network'];
		$server['remote_networkv6'] = $pconfig['remote_networkv6'];
		$server['gwredir'] = $pconfig['gwredir'];
		$server['gwredir6'] = $pconfig['gwredir6'];
		$server['local_network'] = $pconfig['local_network'];
		$server['local_networkv6'] = $pconfig['local_networkv6'];
		$server['maxclients'] = $pconfig['maxclients'];
		$server['allow_compression'] = $pconfig['allow_compression'];
		$server['compression'] = $pconfig['compression'];
		$server['compression_push'] = $pconfig['compression_push'];
		$server['passtos'] = $pconfig['passtos'];
		$server['client2client'] = $pconfig['client2client'];

		$server['dynamic_ip'] = $pconfig['dynamic_ip'];
		$server['topology'] = $pconfig['topology'];

		$server['serverbridge_dhcp'] = $pconfig['serverbridge_dhcp'];
		$server['serverbridge_interface'] = $pconfig['serverbridge_interface'];
		$server['serverbridge_routegateway'] = $pconfig['serverbridge_routegateway'];
		$server['serverbridge_dhcp_start'] = $pconfig['serverbridge_dhcp_start'];
		$server['serverbridge_dhcp_end'] = $pconfig['serverbridge_dhcp_end'];

		if ($pconfig['dns_domain_enable']) {
			$server['dns_domain'] = $pconfig['dns_domain'];
		}

		if ($pconfig['dns_server_enable']) {
			$server['dns_server1'] = $pconfig['dns_server1'];
			$server['dns_server2'] = $pconfig['dns_server2'];
			$server['dns_server3'] = $pconfig['dns_server3'];
			$server['dns_server4'] = $pconfig['dns_server4'];
		}

		if ($pconfig['push_blockoutsidedns']) {
			$server['push_blockoutsidedns'] = $pconfig['push_blockoutsidedns'];
		}

		$server['username_as_common_name'] = ($pconfig['username_as_common_name'] == 'yes') ? "enabled" : "disabled";

		if ($pconfig['udp_fast_io']) {
			$server['udp_fast_io'] = $pconfig['udp_fast_io'];
		}
		if ($pconfig['exit_notify']) {
			$server['exit_notify'] = $pconfig['exit_notify'];
		}
		$server['sndrcvbuf'] = $pconfig['sndrcvbuf'];
		if ($pconfig['push_register_dns']) {
			$server['push_register_dns'] = $pconfig['push_register_dns'];
		}

		if ($pconfig['ntp_server_enable']) {
			$server['ntp_server1'] = $pconfig['ntp_server1'];
			$server['ntp_server2'] = $pconfig['ntp_server2'];
		}

		$server['netbios_enable'] = $pconfig['netbios_enable'];
		$server['netbios_ntype'] = $pconfig['netbios_ntype'];
		$server['netbios_scope'] = $pconfig['netbios_scope'];

		$server['create_gw'] = $pconfig['create_gw'];
		$server['verbosity_level'] = $pconfig['verbosity_level'];

		if ($pconfig['netbios_enable']) {

			if ($pconfig['wins_server_enable']) {
				$server['wins_server1'] = $pconfig['wins_server1'];
				$server['wins_server2'] = $pconfig['wins_server2'];
			}

			if ($pconfig['dns_server_enable']) {
				$server['nbdd_server1'] = $pconfig['nbdd_server1'];
			}
		}

		if ($_POST['duplicate_cn'] == "yes") {
			$server['duplicate_cn'] = true;
		}

		if (!empty($pconfig['data_ciphers'])) {
			$server['data_ciphers'] = implode(",", $pconfig['data_ciphers']);
		}

		$server['ncp_enable'] = $pconfig['ncp_enable'];

		$server['ping_method'] = $pconfig['ping_method'];
		$server['keepalive_interval'] = $pconfig['keepalive_interval'];
		$server['keepalive_timeout'] = $pconfig['keepalive_timeout'];
		$server['ping_seconds'] = $pconfig['ping_seconds'];
		$server['ping_push'] = $pconfig['ping_push'];
		$server['ping_action'] = $pconfig['ping_action'];
		$server['ping_action_seconds'] = $pconfig['ping_action_seconds'];
		$server['ping_action_push'] = $pconfig['ping_action_push'];
		$server['inactive_seconds'] = $pconfig['inactive_seconds'];

		if (isset($id) && $a_server[$id]) {
			$a_server[$id] = $server;
			$wc_msg = sprintf(gettext('Updated OpenVPN server on %1$s:%2$s %3$s'), convert_friendly_interface_to_friendly_descr($server['interface']), $server['local_port'], $server['description']);
		} else {
			$a_server[] = $server;
			$wc_msg = sprintf(gettext('Added OpenVPN server on %1$s:%2$s %3$s'), convert_friendly_interface_to_friendly_descr($server['interface']), $server['local_port'], $server['description']);
		}

		write_config($wc_msg);
		openvpn_resync('server', $server);
		openvpn_resync_csc_all();

		header("Location: vpn_openvpn_server.php");
		exit;
	}

	if (!empty($pconfig['data_ciphers'])) {
		$pconfig['data_ciphers'] = implode(",", $pconfig['data_ciphers']);
	}

	if (!empty($pconfig['authmode'])) {
		$pconfig['authmode'] = implode(",", $pconfig['authmode']);
	}
}

$pgtitle = array(gettext("VPN"), gettext("OpenVPN"), gettext("Servers"));
$pglinks = array("", "vpn_openvpn_server.php", "vpn_openvpn_server.php");

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
$tab_array[] = array(gettext("Servers"), true, "vpn_openvpn_server.php");
$tab_array[] = array(gettext("Clients"), false, "vpn_openvpn_client.php");
$tab_array[] = array(gettext("Client Specific Overrides"), false, "vpn_openvpn_csc.php");
$tab_array[] = array(gettext("Wizards"), false, "wizard.php?xml=openvpn_wizard.xml");
add_package_tabs("OpenVPN", $tab_array);
display_top_tabs($tab_array);

$form = new Form();

if ($act=="new" || $act=="edit"):


	$section = new Form_Section('General Information');

	$section->addInput(new Form_Checkbox(
		'disable',
		'Disabled',
		'Disable this server',
		$pconfig['disable']
	))->setHelp('Set this option to disable this server without removing it from the list.');

	$section->addInput(new Form_Select(
		'mode',
		'*Server mode',
		$pconfig['mode'],
		openvpn_build_mode_list()
		));

	$options = array();
	$authmodes = array();
	$authmodes = explode(",", $pconfig['authmode']);

	$auth_servers = auth_get_authserver_list();

	foreach (explode(",", $pconfig['data_ciphers']) as $cipher) {
		$data_ciphers_list[$cipher] = $cipher;
	}

	// If no authmodes set then default to selecting the first entry in auth_servers
	if (empty($authmodes[0]) && !empty(key($auth_servers))) {
		$authmodes[0] = key($auth_servers);
	}

	foreach ($auth_servers as $auth_server_key => $auth_server) {
		$options[$auth_server_key] = $auth_server['name'];
	}

	$section->addInput(new Form_Select(
		'authmode',
		'*Backend for authentication',
		$authmodes,
		$options,
		true
		))->addClass('authmode');

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
		))->setHelp("The interface or Virtual IP address where OpenVPN will receive client connections.");

	$section->addInput(new Form_Input(
		'local_port',
		'*Local port',
		'number',
		$pconfig['local_port'],
		['min' => '0']
	))->setHelp("The port used by OpenVPN to receive client connections.");

	$section->addInput(new Form_Input(
		'description',
		'Description',
		'text',
		$pconfig['description']
	))->setHelp('A description may be entered here for administrative reference (not parsed).');

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
	    'This key is used to sign control channel packets with an HMAC signature for authentication when establishing the tunnel. ',
		'<br/>');

	$section->addInput(new Form_Select(
		'tls_type',
		'*TLS Key Usage Mode',
		empty($pconfig['tls_type']) ? 'auth':$pconfig['tls_type'],
		$openvpn_tls_modes
		))->setHelp('In Authentication mode the TLS key is used only as HMAC authentication for the control channel, protecting the peers from unauthorized connections. %1$s' .
		    'Encryption and Authentication mode also encrypts control channel communication, providing more privacy and traffic control channel obfuscation.',
			'<br/>');

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
			sprintf('No Certificate Revocation Lists defined. One may be created here: %s', '<a href="system_camanager.php">System &gt; Cert. Manager</a>')
		));
	}

	$section->addInput(new Form_Checkbox(
		'ocspcheck',
		'OCSP Check',
		'Check client certificates with OCSP',
		$pconfig['ocspcheck']
	));

	$section->addInput(new Form_Input(
		'ocspurl',
		'OCSP URL',
		'url',
		$pconfig['ocspurl']
	));

	$certhelp = '<span id="certtype"></span>';
	if (count($a_cert)) {
		if (!empty(trim($pconfig['certref']))) {
			$thiscert = lookup_cert($pconfig['certref']);
			$purpose = cert_get_purpose($thiscert['crt'], true);
			if ($purpose['server'] != "Yes") {
				$certhelp = '<span id="certtype" class="text-danger">' . gettext("Warning: The selected server certificate was not created as an SSL/TLS Server certificate and may not work as expected") . ' </span>';
			}
		}
	} else {
		$certhelp = sprintf(gettext('No Certificates defined. One may be created here: %1$s%2$s%3$s'), '<span id="certtype">', '<a href="system_camanager.php">' . gettext("System &gt; Cert. Manager") . '</a>', '</span>');
	}

	$cl = openvpn_build_cert_list(false, true);

	//Save the number of server certs for use at run-time
	$servercerts = count($cl['server']);

	$section->addInput(new Form_Select(
		'certref',
		'*Server certificate',
		$pconfig['certref'],
		$cl['server'] + $cl['non-server']
		))->setHelp($certhelp);

	$section->addInput(new Form_Select(
		'dh_length',
		'*DH Parameter Length',
		$pconfig['dh_length'],
		$openvpn_dh_lengths
		))->setHelp('Diffie-Hellman (DH) parameter set used for key exchange.%1$s%2$s%3$s',
		    '<div class="infoblock">',
		    sprint_info_box(gettext('Only DH parameter sets which exist in /etc/ are shown.') .
		        '<br/>' .
		        gettext('Generating new or stronger DH parameters is CPU-intensive and must be performed manually.') . ' ' .
		        sprintf(gettext('Consult %1$sthe doc wiki article on DH Parameters%2$sfor information on generating new or stronger parameter sets.'),
					'<a href="https://docs.netgate.com/pfsense/en/latest/vpn/openvpn/configure.html#dh-parameters-length">',
					'</a> '),
				'info', false),
		    '</div>');

	$section->addInput(new Form_Select(
		'ecdh_curve',
		'ECDH Curve',
		$pconfig['ecdh_curve'],
		openvpn_get_curvelist()
		))->setHelp('The Elliptic Curve to use for key exchange. %1$s' .
		    'The curve from the server certificate is used by default when the server uses an ECDSA certificate. ' .
		    'Otherwise, secp384r1 is used as a fallback.',
			'<br/>');

	if (!$pconfig['shared_key']) {
		$section->addInput(new Form_Checkbox(
			'autokey_enable',
			'Shared key',
			'Automatically generate a shared key',
			$pconfig['autokey_enable']
		));
	}

	$section->addInput(new Form_Textarea(
		'shared_key',
		'*Shared Key',
		$pconfig['shared_key']
	))->setHelp('Paste the shared key here');

	$section->addInput(new Form_Checkbox(
		'ncp_enable',
		'Data Encryption Negotiation',
		'Enable Data Encryption Negotiation',
		($pconfig['ncp_enable'] == "enabled")
	))->setHelp('This option allows OpenVPN clients and servers to negotiate a compatible set of acceptable cryptographic ' .
			'data encryption algorithms from those selected in the Data Encryption Algorithms list below. ' .
			'Disabling this feature is deprecated.');

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
		$pconfig['data_ciphers_fallback'],
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
		    'The server and all clients must have the same setting. While SHA1 is the default for OpenVPN, this algorithm is insecure. ',
			'<br />');

	$section->addInput(new Form_Select(
		'engine',
		'Hardware Crypto',
		$pconfig['engine'],
		openvpn_get_engines()
		));

	$section->addInput(new Form_Select(
		'cert_depth',
		'*Certificate Depth',
		$pconfig['cert_depth'],
		["" => gettext("Do Not Check")] + $openvpn_cert_depths
		))->setHelp('When a certificate-based client logs in, do not accept certificates below this depth. ' .
					'Useful for denying certificates made with intermediate CAs generated from the same CA as the server.');

	$section->addInput(new Form_Checkbox(
		'strictusercn',
		'Strict User-CN Matching',
		'Enforce match',
		$pconfig['strictusercn']
	))->setHelp('When authenticating users, enforce a match between the common name of the client certificate and the username given at login.');

	$form->add($section);

	$section = new Form_Section('Tunnel Settings');

	$section->addInput(new Form_Input(
		'tunnel_network',
		'IPv4 Tunnel Network',
		'text',
		$pconfig['tunnel_network']
	))->setHelp('This is the IPv4 virtual network used for private communications between this server and client ' .
				'hosts expressed using CIDR notation (e.g. 10.0.8.0/24). The first usable address in the network will be assigned to ' .
				'the server virtual interface. The remaining usable addresses will be assigned ' .
				'to connecting clients.');

	$section->addInput(new Form_Input(
		'tunnel_networkv6',
		'IPv6 Tunnel Network',
		'text',
		$pconfig['tunnel_networkv6']
	))->setHelp('This is the IPv6 virtual network used for private ' .
				'communications between this server and client hosts expressed using CIDR notation (e.g. fe80::/64). ' .
				'The ::1 address in the network will be assigned to the server virtual interface. The remaining ' .
				'addresses will be assigned to connecting clients.');

	$section->addInput(new Form_Checkbox(
		'serverbridge_dhcp',
		'Bridge DHCP',
		'Allow clients on the bridge to obtain DHCP.',
		$pconfig['serverbridge_dhcp']
	));

	$section->addInput(new Form_Select(
		'serverbridge_interface',
		'Bridge Interface',
		$pconfig['serverbridge_interface'],
		openvpn_build_bridge_list()
		))->setHelp('The interface to which this TAP instance will be bridged. This is not done automatically. This interface must be assigned ' .
						'and the bridge created separately. This setting controls which existing IP address and subnet ' .
						'mask are used by OpenVPN for the bridge. Setting this to "none" will cause the Server Bridge DHCP settings below to be ignored.');

	$section->addInput(new Form_Checkbox(
		'serverbridge_routegateway',
		'Bridge Route Gateway',
		'Push the Bridge Interface IPv4 address to connecting clients as a route gateway',
		$pconfig['serverbridge_routegateway']
	))->setHelp('When omitting the <b>IPv4 Tunnel Network</b> for a bridge, connecting clients cannot automatically determine a server-side gateway for <b>IPv4 Local Network(s)</b> ' .
						'or <b>Redirect IPv4 Gateway</b> traffic. When enabled, this option sends the IPv4 address of the selected <b>Bridge Interface</b> to clients ' .
						'which they can then use as a gateway for routing traffic outside of the bridged subnet. OpenVPN does not currently support this mechanism for IPv6.');

	$section->addInput(new Form_Input(
		'serverbridge_dhcp_start',
		'Server Bridge DHCP Start',
		'text',
		$pconfig['serverbridge_dhcp_start']
	))->setHelp('When using TAP mode as a multi-point server, a DHCP range may optionally be supplied to use on the ' .
				'interface to which this TAP instance is bridged. If these settings are left blank, DHCP will be passed ' .
				'through to the LAN, and the interface setting above will be ignored.');

	$section->addInput(new Form_Input(
		'serverbridge_dhcp_end',
		'Server Bridge DHCP End',
		'text',
		$pconfig['serverbridge_dhcp_end']
	));

	$section->addInput(new Form_Checkbox(
		'gwredir',
		'Redirect IPv4 Gateway',
		'Force all client-generated IPv4 traffic through the tunnel.',
		$pconfig['gwredir']
	));
	$section->addInput(new Form_Checkbox(
		'gwredir6',
		'Redirect IPv6 Gateway',
		'Force all client-generated IPv6 traffic through the tunnel.',
		$pconfig['gwredir6']
	));

	$section->addInput(new Form_Input(
		'local_network',
		'IPv4 Local network(s)',
		'text',
		$pconfig['local_network']
	))->setHelp('IPv4 networks that will be accessible from the remote endpoint. ' .
				'Expressed as a comma-separated list of one or more CIDR ranges. ' .
				'This may be left blank if not adding a route to the local network through this tunnel on the remote machine. ' .
				'This is generally set to the LAN network.');

	$section->addInput(new Form_Input(
		'local_networkv6',
		'IPv6 Local network(s)',
		'text',
		$pconfig['local_networkv6']
	))->setHelp('IPv6 networks that will be accessible from the remote endpoint. ' .
				'Expressed as a comma-separated list of one or more IP/PREFIX. This may be left blank if not adding a ' .
				'route to the local network through this tunnel on the remote machine. This is generally set to the LAN network.');

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
		'maxclients',
		'Concurrent connections',
		'number',
		$pconfig['maxclients']
	))->setHelp('Specify the maximum number of clients allowed to concurrently connect to this server.');

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

	$section->addInput(new Form_Checkbox(
		'compression_push',
		'Push Compression',
		'Push the selected Compression setting to connecting clients.',
		$pconfig['compression_push']
	));

	$section->addInput(new Form_Checkbox(
		'passtos',
		'Type-of-Service',
		'Set the TOS IP header value of tunnel packets to match the encapsulated packet value.',
		$pconfig['passtos']
	));

	$section->addInput(new Form_Checkbox(
		'client2client',
		'Inter-client communication',
		'Allow communication between clients connected to this server',
		$pconfig['client2client']
	));

	$section->addInput(new Form_Checkbox(
		'duplicate_cn',
		'Duplicate Connection',
		'Allow multiple concurrent connections from the same user',
		$pconfig['duplicate_cn']
	))->setHelp('When set, the same user may connect multiple times. ' .
			'When unset, a new connection from a user will disconnect the previous session. %1$s%1$s' .
			'Users are identified by their username or certificate properties, depending on the VPN configuration. ' .
			'This practice is discouraged security reasons, but may be necessary in some environments.', '<br />');

	$form->add($section);

	$section = new Form_Section('Client Settings');
	$section->addClass('advanced');

	$section->addInput(new Form_Checkbox(
		'dynamic_ip',
		'Dynamic IP',
		'Allow connected clients to retain their connections if their IP address changes.',
		$pconfig['dynamic_ip']
	));

	$section->addInput(new Form_Select(
		'topology',
		'Topology',
		$pconfig['topology'],
		$openvpn_topologies
	))->setHelp('Specifies the method used to supply a virtual adapter IP address to clients when using TUN mode on IPv4.%1$s' .
				'Some clients may require this be set to "subnet" even for IPv6, such as OpenVPN Connect (iOS/Android). ' .
				'Older versions of OpenVPN (before 2.0.9) or clients such as Yealink phones may require "net30".', '<br />');

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
	    'ping-restart = timeout*2%1$s' .
	    'push ping = interval%1$s' .
	    'push ping-restart = timeout%1$s',
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

	$section->addInput(new Form_Checkbox(
		'ping_push',
		'Push ping to client',
		'Push ping to VPN client',
		$pconfig['ping_push']
	));

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

	$section->addInput(new Form_Checkbox(
		'ping_action_push',
		'Push to client',
		'Push ping-restart/ping-exit to VPN client',
		$pconfig['ping_action_push']
	));

	$form->add($section);

	$section = new Form_Section("Advanced Client Settings");
	$section->addClass("clientadv");

	$section->addInput(new Form_Checkbox(
		'dns_domain_enable',
		'DNS Default Domain',
		'Provide a default domain name to clients',
		$pconfig['dns_domain_enable']
	));

	$section->addInput(new Form_Input(
		'dns_domain',
		'DNS Default Domain',
		'text',
		$pconfig['dns_domain']
	));

	$section->addInput(new Form_Checkbox(
		'dns_server_enable',
		'DNS Server enable',
		'Provide a DNS server list to clients. Addresses may be IPv4 or IPv6.',
		$pconfig['dns_server_enable']
	));

	$section->addInput(new Form_Input(
		'dns_server1',
		'DNS Server 1',
		'text',
		$pconfig['dns_server1']
	));

	$section->addInput(new Form_Input(
		'dns_server2',
		'DNS Server 2',
		'text',
		$pconfig['dns_server2']
	));

	$section->addInput(new Form_Input(
		'dns_server3',
		'DNS Server 3',
		'text',
		$pconfig['dns_server3']
	));

	$section->addInput(new Form_Input(
		'dns_server4',
		'DNS Server 4',
		'text',
		$pconfig['dns_server4']
	));

	$section->addInput(new Form_Checkbox(
		'push_blockoutsidedns',
		'Block Outside DNS',
		'Make Windows 10 Clients Block access to DNS servers except across OpenVPN while connected, forcing clients to use only VPN DNS servers.',
		$pconfig['push_blockoutsidedns']
	))->setHelp('Requires Windows 10 and OpenVPN 2.3.9 or later. Only Windows 10 is prone to DNS leakage in this way, other clients will ignore the option as they are not affected.');

	$section->addInput(new Form_Checkbox(
		'push_register_dns',
		'Force DNS cache update',
		'Run "net stop dnscache", "net start dnscache", "ipconfig /flushdns" and "ipconfig /registerdns" on connection initiation.',
		$pconfig['push_register_dns']
	))->setHelp('This is known to kick Windows into recognizing pushed DNS servers.');

	$section->addInput(new Form_Checkbox(
		'ntp_server_enable',
		'NTP Server enable',
		'Provide an NTP server list to clients',
		$pconfig['ntp_server_enable']
	));

	$section->addInput(new Form_Input(
		'ntp_server1',
		'NTP Server 1',
		'text',
		$pconfig['ntp_server1']
	));

	$section->addInput(new Form_Input(
		'ntp_server2',
		'NTP Server 2',
		'text',
		$pconfig['ntp_server2']
	));

	$section->addInput(new Form_Checkbox(
		'netbios_enable',
		'NetBIOS enable',
		'Enable NetBIOS over TCP/IP',
		$pconfig['netbios_enable']
	))->setHelp('If this option is not set, all NetBIOS-over-TCP/IP options (including WINS) will be disabled.');

	$section->addInput(new Form_Select(
		'netbios_ntype',
		'Node Type',
		$pconfig['netbios_ntype'],
		$netbios_nodetypes
		))->setHelp('Possible options: b-node (broadcasts), p-node (point-to-point name queries to a WINS server), ' .
					'm-node (broadcast then query name server), and h-node (query name server, then broadcast)');

	$section->addInput(new Form_Input(
		'netbios_scope',
		'Scope ID',
		'text',
		$pconfig['netbios_scope']
	))->setHelp('A NetBIOS Scope ID provides an extended naming service for NetBIOS over TCP/IP. The NetBIOS ' .
				'scope ID isolates NetBIOS traffic on a single network to only those nodes with the same ' .
				'NetBIOS scope ID');

	$section->addInput(new Form_Checkbox(
		'wins_server_enable',
		'WINS server enable',
		'Provide a WINS server list to clients',
		$pconfig['wins_server_enable']
	));

	$section->addInput(new Form_Input(
		'wins_server1',
		'WINS Server 1',
		'text',
		$pconfig['wins_server1']
	));

	$section->addInput(new Form_Input(
		'wins_server2',
		'WINS Server 2',
		'text',
		$pconfig['wins_server2']
	));

	$form->add($section);

	$section = new Form_Section('Advanced Configuration');

	$custops = new Form_Textarea(
		'custom_options',
		'Custom options',
		$pconfig['custom_options']
	);
	if (!$user_can_edit_advanced) {
		$custops->setDisabled();
	}
	$section->addInput($custops)->setHelp('Enter any additional options to add to the OpenVPN server configuration here, separated by semicolon.%1$s' .
				'EXAMPLE: push "route 10.0.0.0 255.255.255.0"', '<br />');

	$section->addInput(new Form_Checkbox(
		'username_as_common_name',
		'Username as Common Name',
		'Use the authenticated client username instead of the certificate common name (CN).',
		$pconfig['username_as_common_name']
	))->setHelp('When a user authenticates, if this option is enabled then the username of the client will be used ' .
			'in place of the certificate common name for purposes such as determining Client Specific Overrides.');

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
		$openvpn_exit_notify_server
	))->setHelp('Send an explicit exit notification to connected clients/peers when restarting ' .
		'or shutting down, so they may immediately disconnect rather than waiting for a timeout. ' .
		'In SSL/TLS Server modes, clients may be directed to reconnect or use the next server. ' .
		'In Peer-to-Peer Shared Key or with a /30 Tunnel Network, this value controls how ' .
		'many times this instance will attempt to send the exit notification.');

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

	$group->setHelp('If you assign a virtual interface to this OpenVPN server, ' .
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

	if (isset($id) && $a_server[$id]) {
		$form->addGlobal(new Form_Input(
			'id',
			null,
			'hidden',
			$id
		));
	}

	$form->add($section);
	print($form);

else:
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('OpenVPN Servers')?></h2></div>
		<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap table-rowdblclickedit" data-sortable>
			<thead>
				<tr>
					<th><?=gettext("Interface")?></th>
					<th data-sortable-type="alpha"><?=gettext("Protocol / Port")?></th>
					<th><?=gettext("Tunnel Network")?></th>
					<th><?=gettext("Mode / Crypto")?></th>
					<th><?=gettext("Description")?></th>
					<th><?=gettext("Actions")?></th>
				</tr>
			</thead>

			<tbody>
<?php
	$i = 0;
	foreach ($a_server as $server):
		$dc = openvpn_build_data_cipher_list($server['data_ciphers'], $server['data_ciphers_fallback'], ($server['ncp_enable'] != "disabled"));
		$dca = explode(',', $dc);
		if (count($dca) > 5) {
			$dca = array_slice($dca, 0, 5);
			$dca[] = '[...]';
		}
		$dc = implode(', ', $dca);
?>
				<tr <?=isset($server['disable']) ? 'class="disabled"':''?>>
					<td>
						<?=htmlspecialchars(convert_openvpn_interface_to_friendly_descr($server['interface']))?>
					</td>
					<td data-value="<?=htmlspecialchars($server['local_port']) . '-' . htmlspecialchars($server['protocol'])?>">
						<?=htmlspecialchars($server['protocol'])?> / <?=htmlspecialchars($server['local_port'])?>
						<br/>(<?= htmlspecialchars(strtoupper(empty($server['dev_mode']) ? 'TUN' : $server['dev_mode'])) ?>)
					</td>
					<td>
					<?php if (!empty($server['tunnel_network'])): ?>
						<?=htmlspecialchars($server['tunnel_network'])?><br />
					<?php endif; ?>
						<?=htmlspecialchars($server['tunnel_networkv6'])?>
					</td>
					<td>
						<strong><?= gettext('Mode') ?>:</strong> <?= htmlspecialchars($openvpn_server_modes[$server['mode']]) ?>
						<br/>
						<strong><?= gettext('Data Ciphers') ?>:</strong> <?= htmlspecialchars($dc) ?>
						<br/>
						<strong><?= gettext('Digest') ?>:</strong> <?= htmlspecialchars($server['digest']) ?>
					<?php if (!empty($server['dh_length'])): ?>
						<br/>
						<strong><?= gettext('D-H Params') ?>:</strong>
						<?php if (is_numeric($server['dh_length'])): ?>
							<?= htmlspecialchars($server['dh_length']) ?> <?= gettext('bits') ?>
						<?php elseif ($server['dh_length'] == "none"): ?>
							<?= gettext("Disabled, ECDH Only") ?>
						<?php endif; ?>
					<?php endif; ?>
					</td>
					<td>
						<?= htmlspecialchars($server['description']) ?>
					</td>
					<td>
						<a class="fa fa-pencil"	title="<?=gettext('Edit Server')?>"	href="vpn_openvpn_server.php?act=edit&amp;id=<?=$i?>"></a>
						<a class="fa fa-clone"	title="<?=gettext("Copy Server")?>"	href="vpn_openvpn_server.php?act=dup&amp;id=<?=$i?>" usepost></a>
						<a class="fa fa-trash"	title="<?=gettext('Delete Server')?>"	href="vpn_openvpn_server.php?act=del&amp;id=<?=$i?>" usepost></a>
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
	<a href="vpn_openvpn_server.php?act=new" class="btn btn-sm btn-success btn-sm">
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

	function advanced_change(hide, mode) {
		if (!hide) {
			hideClass('advanced', false);
			hideClass("clientadv", false);
		} else if (mode == "p2p_tls") {
			hideClass('advanced', false);
			hideClass("clientadv", true);
		} else {
			hideClass('advanced', true);
			hideClass("clientadv", true);
		}
	}

	function mode_change() {
		value = $('#mode').val();

		hideCheckbox('autotls_enable', false);
		hideCheckbox('tlsauth_enable', false);
		hideInput('caref', false);
		hideInput('crlref', false);
		hideCheckbox('ocspcheck', false);
		hideLabel('Peer Certificate Revocation list', false);

		switch (value) {
			case "p2p_tls":
			case "server_tls":
			case "server_user":
				hideInput('tls', false);
				hideInput('certref', false);
				hideInput('dh_length', false);
				hideInput('ecdh_curve', false);
				hideInput('cert_depth', false);
				hideCheckbox('strictusercn', true);
				hideCheckbox('autokey_enable', true);
				hideInput('shared_key', false);
				hideInput('topology', false);
				hideCheckbox('compression_push', false);
				hideCheckbox('duplicate_cn', false);
			break;
			case "server_tls_user":
				hideInput('tls', false);
				hideInput('certref', false);
				hideInput('dh_length', false);
				hideInput('ecdh_curve', false);
				hideInput('cert_depth', false);
				hideCheckbox('strictusercn', false);
				hideCheckbox('autokey_enable', true);
				hideInput('shared_key', true);
				hideInput('topology', false);
				hideCheckbox('compression_push', false);
				hideCheckbox('duplicate_cn', false);
			break;
			case "p2p_shared_key":
				hideInput('tls', true);
				hideInput('caref', true);
				hideInput('crlref', true);
				hideLabel('Peer Certificate Revocation list', true);
				hideLabel('Peer Certificate Authority', true);
				hideInput('certref', true);
				hideCheckbox('tlsauth_enable', true);
				hideInput('dh_length', true);
				hideInput('ecdh_curve', true);
				hideInput('cert_depth', true);
				hideCheckbox('strictusercn', true);
				hideCheckbox('autokey_enable', true);
				hideInput('shared_key', false);
				hideInput('topology', true);
				hideCheckbox('compression_push', true);
				hideCheckbox('duplicate_cn', true);
				hideCheckbox('ocspcheck', true);
			break;
		}

		switch (value) {
			case "p2p_shared_key":
				advanced_change(true, value);
				hideInput('remote_network', false);
				hideInput('remote_networkv6', false);
				hideCheckbox('gwredir', true);
				hideCheckbox('gwredir6', true);
				hideInput('local_network', true);
				hideInput('local_networkv6', true);
				hideMultiClass('authmode', true);
				hideCheckbox('client2client', true);
				hideCheckbox('autokey_enable', false);
				hideCheckbox('username_as_common_name', true);
			break;
			case "p2p_tls":
				advanced_change(true, value);
				hideInput('remote_network', false);
				hideInput('remote_networkv6', false);
				hideCheckbox('gwredir', false);
				hideCheckbox('gwredir6', false);
				hideInput('local_network', false);
				hideInput('local_networkv6', false);
				hideMultiClass('authmode', true);
				hideCheckbox('client2client', false);
				hideCheckbox('username_as_common_name', true);
			break;
			case "server_user":
			case "server_tls_user":
				advanced_change(false, value);
				hideInput('remote_network', true);
				hideInput('remote_networkv6', true);
				hideCheckbox('gwredir', false);
				hideCheckbox('gwredir6', false);
				hideInput('local_network', false);
				hideInput('local_networkv6', false);
				hideMultiClass('authmode', false);
				hideCheckbox('client2client', false);
				hideCheckbox('autokey_enable', true);
				hideCheckbox('username_as_common_name', false);
			break;
			case "server_tls":
				hideMultiClass('authmode', true);
				advanced_change(false, value);
				hideCheckbox('autokey_enable', true);
			default:
				hideInput('custom_options', false);
				hideInput('verbosity_level', false);
				hideInput('remote_network', true);
				hideInput('remote_networkv6', true);
				hideCheckbox('gwredir', false);
				hideCheckbox('gwredir6', false);
				hideInput('local_network', false);
				hideInput('local_networkv6', false);
				hideCheckbox('client2client', false);
				hideCheckbox('username_as_common_name', true);
			break;
		}

		gwredir_change();
		gwredir6_change();
		tlsauth_change();
		autokey_change();
	}

	function protocol_change() {
		if ($('#protocol').val() != undefined) {
			hideInput('interface', (($('#protocol').val().toLowerCase() == 'udp') || ($('#protocol').val().toLowerCase() == 'tcp')));
			var notudp = !($('#protocol').val().substring(0, 3).toLowerCase() == 'udp');
			hideCheckbox('udp_fast_io', notudp);
			hideInput('exit_notify', notudp);
		}
	}

	// Process "Enable authentication of TLS packets" checkbox
	function tlsauth_change() {
		autotls_change();
	}

	// Process "Automatically generate a shared TLS authentication key" checkbox
	// Hide 'autotls_enable' AND 'tls' if mode == p2p_shared_key
	// Otherwise hide 'tls' based on state of 'autotls_enable'
	function autotls_change() {
		if (($('#mode').val() == 'p2p_shared_key') || (!$('#tlsauth_enable').prop('checked'))) {
			hideInput('tls', true);
			hideInput('tls_type', true);
			hideInput('tlsauth_keydir', true);
			hideInput('autotls_enable', true);
		} else {
			hideInput('autotls_enable', false);
			hideInput('tls', $('#autotls_enable').prop('checked') || !$('#tlsauth_enable').prop('checked'));
			hideInput('tls_type', $('#autotls_enable').prop('checked') || !$('#tlsauth_enable').prop('checked'));
			hideInput('tlsauth_keydir', $('#autotls_enable').prop('checked') || !$('#tlsauth_enable').prop('checked'));
		}
	}

	function autokey_change() {
		var hide  = $('#autokey_enable').prop('checked')

		if ($('#mode').val() != 'p2p_shared_key') {
			hideCheckbox('autokey_enable', true);
			hideInput('shared_key', true);
		} else {
			hideInput('shared_key', hide);
			hideCheckbox('autokey_enable', false);
		}


	}

	function gwredir_change() {
		var hide = $('#gwredir').prop('checked')

		hideInput('local_network', hide);
//		hideInput('remote_network', hide);
	}

	function gwredir6_change() {
		var hide = $('#gwredir6').prop('checked')

		hideInput('local_networkv6', hide);
//		hideInput('remote_networkv6', hide);
	}

	function dns_domain_change() {
		var hide  = ! $('#dns_domain_enable').prop('checked')

		hideInput('dns_domain', hide);
	}

	function dns_server_change() {
		var hide  = ! $('#dns_server_enable').prop('checked')

		hideInput('dns_server1', hide);
		hideInput('dns_server2', hide);
		hideInput('dns_server3', hide);
		hideInput('dns_server4', hide);
	}

	function wins_server_change() {
		var hide  = ! $('#wins_server_enable').prop('checked')

		hideInput('wins_server1', hide);
		hideInput('wins_server2', hide);
	}


	function ntp_server_change() {
		var hide  = ! $('#ntp_server_enable').prop('checked')

		hideInput('ntp_server1', hide);
		hideInput('ntp_server2', hide);
	}

	function netbios_change() {
		var hide  = ! $('#netbios_enable').prop('checked')

		hideInput('netbios_ntype', hide);
		hideInput('netbios_scope', hide);
		hideCheckbox('wins_server_enable', hide);
		wins_server_change();
	}

	function tuntap_change() {

		mvalue = $('#mode').val();

		switch (mvalue) {
			case "p2p_shared_key":
				sharedkey = true;
				p2p = true;
				break;
			case "p2p_tls":
				sharedkey = false;
				p2p = true;
				break;
			default:
				sharedkey = false;
				p2p = false;
				break;
		}

		value = $('#dev_mode').val();

		switch (value) {
			case "tun":
				hideInput('tunnel_network', false);
				hideCheckbox('serverbridge_dhcp', true);
				hideInput('serverbridge_interface', true);
				hideCheckbox('serverbridge_routegateway', true);
				hideInput('serverbridge_dhcp_start', true);
				hideInput('serverbridge_dhcp_end', true);
				if (sharedkey) {
					hideInput('local_network', true);
					hideInput('local_networkv6', true);
					hideInput('topology', true);
				} else {
					// For tunnel mode that is not shared key,
					// the display status of local network fields depends on
					// the state of the gwredir checkbox.
					gwredir_change();
					gwredir6_change();
					hideInput('topology', false);
				}
				break;

			case "tap":
				hideInput('tunnel_network', false);

				if (!p2p) {
					hideCheckbox('serverbridge_dhcp', false);
					disableInput('serverbridge_dhcp', false);
					hideInput('serverbridge_interface', false);
					hideCheckbox('serverbridge_routegateway', false);
					hideInput('serverbridge_dhcp_start', false);
					hideInput('serverbridge_dhcp_end', false);
					hideInput('topology', true);

					if ($('#serverbridge_dhcp').prop('checked')) {
						disableInput('serverbridge_interface', false);
						hideCheckbox('serverbridge_routegateway', false);
						disableInput('serverbridge_dhcp_start', false);
						disableInput('serverbridge_dhcp_end', false);
					} else {
						disableInput('serverbridge_interface', true);
						hideCheckbox('serverbridge_routegateway', true);
						disableInput('serverbridge_dhcp_start', true);
						disableInput('serverbridge_dhcp_end', true);
					}
				} else {
					hideInput('topology', true);
					disableInput('serverbridge_dhcp', true);
					disableInput('serverbridge_interface', true);
					hideCheckbox('serverbridge_routegateway', true);
					disableInput('serverbridge_dhcp_start', true);
					disableInput('serverbridge_dhcp_end', true);
				}

				break;
		}
	}

	function ping_method_change() {
		pvalue = $('#ping_method').val();

		keepalive = (pvalue == 'keepalive');

		hideInput('keepalive_interval', !keepalive);
		hideInput('keepalive_timeout', !keepalive);
		hideInput('ping_seconds', keepalive);
		hideCheckbox('ping_push', keepalive);
		hideInput('ping_action', keepalive);
		hideInput('ping_action_seconds', keepalive);
		hideCheckbox('ping_action_push', keepalive);
	}

	function ocspcheck_change() {
		var hide  = ! $('#ocspcheck').prop('checked')

		hideInput('ocspurl', hide);
	}

	function allow_compression_change() {
		var hide  = ($('#allow_compression').val() == 'no')
		hideInput('compression', hide);
		hideCheckbox('compression_push', hide);
	}

	// ---------- Monitor elements for change and call the appropriate display functions ------------------------------

	// NTP
	$('#ntp_server_enable').click(function () {
		ntp_server_change();
	});

	// Netbios
	$('#netbios_enable').click(function () {
		netbios_change();
	});

	 // Wins server port
	$('#wins_server_enable').click(function () {
		wins_server_change();
	});

	 // DNS server port
	$('#dns_server_enable').click(function () {
		dns_server_change();
	});

	 // DNS server port
	$('#dns_domain_enable').click(function () {
		dns_domain_change();
	});

	 // Gateway redirect
	$('#gwredir').click(function () {
		gwredir_change();
	});

	 // Gateway redirect IPv6
	$('#gwredir6').click(function () {
		gwredir6_change();
	});

	 // Auto TLSkey generation
	$('#autotls_enable').click(function () {
		autotls_change();
	});

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
		tuntap_change();
	});

	// Protocol
	$('#protocol').change(function () {
		protocol_change();
	});

	 // Tun/tap mode
	$('#dev_mode, #serverbridge_dhcp').change(function () {
		tuntap_change();
	});

	// ping
	$('#ping_method').change(function () {
		ping_method_change();
	});

	// OCSP
	$('#ocspcheck').click(function () {
		ocspcheck_change();
	});

	// Certref
	$('#certref').on('change', function() {
		var errmsg = "";

		if ($(this).find(":selected").index() >= "<?=$servercerts?>") {
			var errmsg = '<span class="text-danger">' + "<?=gettext('Warning: The selected server certificate was not created as an SSL/TLS Server certificate and may not work as expected')?>" + '</span>';
		}

		$('#certtype').html(errmsg);
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
		$('[id="data_ciphers[]"] option').prop("selected", true);
	});

	// ---------- Set initial page display state ----------------------------------------------------------------------
	mode_change();
	protocol_change();
	autokey_change();
	tlsauth_change();
	gwredir_change();
	gwredir6_change();
	dns_domain_change();
	dns_server_change();
	wins_server_change();
	ntp_server_change();
	netbios_change();
	tuntap_change();
	ping_method_change();
	ocspcheck_change();
	allow_compression_change();
});
//]]>
</script>
<?php

include("foot.inc");
