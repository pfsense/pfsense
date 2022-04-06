<?php
/*
 * vpn_openvpn_server.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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

require_once("config.gui.inc");
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

if (isset($_REQUEST['act'])) {
	$act = $_REQUEST['act'];
}

// Delete an OpenVPN server definition
// Returns an array containing various UI display messages
function deleteOpenVPNServer($id, $json=false) {
    global $a_server, $user_can_edit_advanced;
    
    $input_errors = array();
    $wc_msg = "";
    $savemsg = "";
    $rv = array();

	if (!isset($a_server[$id])) {
		if (!$json) {
            pfSenseHeader("vpn_openvpn_server.php");
		    exit;
        } else {
            $input_errors[] = gettext("No server exists with specified ID");
            $rv['input_errors'] = $input_errors;
            return json_encode($rv, true);
        }
	}

	if (empty($a_server[$id])) {
		$wc_msg = gettext('Deleted empty OpenVPN server');
	} elseif (openvpn_inuse($a_server[$id]['vpnid'], 'server')) {
		$input_errors[] = gettext("Cannot delete an OpenVPN instance while the interface is assigned. Remove the interface assignment first.");
	} elseif (!$json && !$user_can_edit_advanced && !empty($a_server[$id]['custom_options'])) {
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

    $rv['savemsg'] = $savemsg;
    $rv['input_errors'] = $input_errors;

    return $json? json_encode($rv, true) : $rv;
}

// Create/return a new/empty OpenVPNserver definition with all default values
function createNewOpenVPNServer() {
    $pconfig = array();
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
	$pconfig['inactive_seconds'] = 300;
	$pconfig['exit_notify'] = 1;
	$pconfig['remote_cert_tls'] = "yes";

    return $pconfig;
}

function dup_edit_server($id, $json=false) {
    global $a_server, $user_can_edit_advanced;

    $pconfig = array();

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
        $pconfig['remote_cert_tls'] = $a_server[$id]['remote_cert_tls'];
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
      $pconfig['connlimit'] = $a_server[$id]['connlimit'];
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

        return $pconfig;
    }
}

// Save an edited OpenVPN server specification. Return $pconfig, $wc_msg and/or $input_errors
function saveOpenVPNServer($vpnid, $id, $post, $json=false) {
    global $a_server, $user_can_edit_advanced, $openvpn_exit_notify_server;
    global $openvpn_ping_method, $openvpn_ping_action, $openvpn_tls_modes;
    global $openvpn_topologies, $openvpn_dh_lengths;

    $input_errors = array();
	$pconfig = $post;

  if (isset($id) && $a_server[$id]) {
		$vpnid = $a_server[$id]['vpnid'];
	} else {
		$vpnid = 0;
	}

	$pconfig['ncp_enable'] = ($pconfig['ncp_enable'] == 'yes') ? 'enabled' : 'disabled';

	if ($pconfig['disable'] && openvpn_inuse($vpnid, 'server')) {
		$input_errors[] = gettext("Cannot disable an OpenVPN instance while the interface is assigned. Remove the interface assignment first.");
	}

	if (isset($pconfig['custom_options']) &&
	    ($pconfig['custom_options'] != $a_server[$id]['custom_options']) &&
	    !$user_can_edit_advanced) {
		$input_errors[] = gettext("This user does not have sufficient privileges to edit Advanced options on this instance.");
	}
	if (!$user_can_edit_advanced && !empty($a_server[$id]['custom_options'])) {
		$pconfig['custom_options'] = $a_server[$id]['custom_options'];
	}

	if (!empty($pconfig['mode']) &&
	    !array_key_exists($pconfig['mode'], openvpn_build_mode_list())) {
		$input_errors[] = gettext("The selected Server Mode is not valid.");
	}

	if (!empty($pconfig['dev_mode']) &&
	    !array_key_exists($pconfig['dev_mode'], $openvpn_dev_mode)) {
		$input_errors[] = gettext("The selected Device Mode is not valid.");
	}

	if (!empty($pconfig['protocol']) &&
	    !array_key_exists($pconfig['protocol'], $openvpn_prots)) {
		$input_errors[] = gettext("The selected Protocol is not valid.");
	}

	if (!empty($pconfig['interface']) &&
	    !array_key_exists($pconfig['interface'], openvpn_build_if_list())) {
		$input_errors[] = gettext("The selected Interface is not valid.");
	}

	if (!empty($pconfig['ecdh_curve']) &&
	    !array_key_exists($pconfig['ecdh_curve'], openvpn_get_curvelist())) {
		$input_errors[] = gettext("The selected ECDH Curve is not valid.");
	}

	$cipher_validation_list = array_keys(openvpn_get_cipherlist());
	if (!in_array($pconfig['data_ciphers_fallback'], $cipher_validation_list)) {
		$input_errors[] = gettext("The selected Fallback Data Encryption Algorithm is not valid.");
	}

	/* Maximum option line length = 256, see https://redmine.pfsense.org/issues/11559 */
	if (!empty($pconfig['data_ciphers']) && (strlen("data-ciphers " . implode(",", $pconfig['data_ciphers'])) > 254)) {
		$input_errors[] = gettext("Too many Data Encryption Algorithms have been selected.");
	}

	if (!empty($pconfig['data_ciphers']) && is_array($pconfig['data_ciphers'])) {
		foreach ($pconfig['data_ciphers'] as $dc) {
			if (!in_array(trim($dc), $cipher_validation_list)) {
				$input_errors[] = gettext("One or more of the selected Data Encryption Algorithms is not valid.");
			}
		}
	} else {
		/* If data_ciphers is not empty and also not an array, it can't be valid. */
		$input_errors[] = gettext("The Data Encryption Algorithm list is not valid.");
	}

	if (!empty($pconfig['digest']) &&
	    !array_key_exists($pconfig['digest'], openvpn_get_digestlist())) {
		$input_errors[] = gettext("The selected Auth Digest Algorithm is not valid.");
	}

	if (!empty($pconfig['engine']) &&
	    !array_key_exists($pconfig['engine'], openvpn_get_engines())) {
		$input_errors[] = gettext("The selected Hardware Crypto engine is not valid.");
	}

	if (!empty($pconfig['cert_depth']) &&
	    !array_key_exists($pconfig['cert_depth'], $openvpn_cert_depths)) {
		$input_errors[] = gettext("The selected Certificate Depth is not valid.");
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

		if (!empty($pconfig['caref']) &&
		    !array_key_exists($pconfig['caref'], cert_build_list('ca', 'OpenVPN'))) {
			$input_errors[] = gettext("The selected Peer Certificate Authority is not valid.");
		}

		if (!empty($pconfig['crlref']) &&
		    !array_key_exists($pconfig['crlref'], openvpn_build_crl_list())) {
			$input_errors[] = gettext("The selected Peer Certificate Revocation List is not valid.");
		}

		if (!empty($pconfig['certref']) &&
		    !array_key_exists($pconfig['certref'], $certlist['server'] + $certlist['non-server'])) {
			$input_errors[] = gettext("The selected Server Certificate is not valid.");
		}

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

	if (!empty($pconfig['tunnel_network']) && !openvpn_validate_tunnel_network($pconfig['tunnel_network'], 'ipv4')) {
		$input_errors[] = gettext("The field 'IPv4 Tunnel Network' must contain a valid IPv4 subnet with CIDR mask or an alias with a single IPv4 subnet with CIDR mask.");
	}

	if (!empty($pconfig['tunnel_network']) &&
	    (!isset($a_server[$id]) ||
	    ($a_server[$id]['tunnel_network'] != $pconfig['tunnel_network'])) &&
	    openvpn_is_tunnel_network_in_use($pconfig['tunnel_network'])) {
		$input_errors[] = gettext("The submitted IPv4 Tunnel Network is already in use.");
	}

	if (!empty($pconfig['tunnel_networkv6']) && !openvpn_validate_tunnel_network($pconfig['tunnel_networkv6'], 'ipv6')) {
		$input_errors[] = gettext("The field 'IPv6 Tunnel Network' must contain a valid IPv6 prefix or an alias with a single IPv6 prefix.");
	}

	if (!empty($pconfig['tunnel_networkv6']) &&
	    (!isset($a_server[$id]) ||
	    ($a_server[$id]['tunnel_networkv6'] != $pconfig['tunnel_networkv6'])) &&
	    openvpn_is_tunnel_network_in_use($pconfig['tunnel_networkv6'])) {
		$input_errors[] = gettext("The submitted IPv6 Tunnel Network is already in use.");
	}

	if ($result = openvpn_validate_cidr($pconfig['remote_network'], 'IPv4 Remote Network', true, "ipv4", true)) {
		$input_errors[] = $result;
	}

	if ($result = openvpn_validate_cidr($pconfig['remote_networkv6'], 'IPv6 Remote Network', true, "ipv6", true)) {
		$input_errors[] = $result;
	}

	if ($result = openvpn_validate_cidr($pconfig['local_network'], 'IPv4 Local Network', true, "ipv4", true)) {
		$input_errors[] = $result;
	}

	if ($result = openvpn_validate_cidr($pconfig['local_networkv6'], 'IPv6 Local Network', true, "ipv6", true)) {
		$input_errors[] = $result;
	}

	if (!empty($pconfig['allow_compression']) &&
	    !array_key_exists($pconfig['allow_compression'], $openvpn_allow_compression)) {
		$input_errors[] = gettext("The selected Allow Compression value is not valid.");
	}

	if (!empty($pconfig['compression']) &&
	    !array_key_exists($pconfig['compression'], $openvpn_compression_modes)) {
		$input_errors[] = gettext("The selected Compression is not valid.");
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
		if (!empty($pconfig['tlsauth_keydir']) &&
		    !array_key_exists($pconfig['tlsauth_keydir'], openvpn_get_keydirlist())) {
			$input_errors[] = gettext("The selected TLS Key Direction is not valid.");
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

		if (!empty($pconfig['netbios_ntype']) &&
		    !array_key_exists($pconfig['netbios_ntype'], $netbios_nodetypes)) {
			$input_errors[] = gettext("The selected NetBIOS Node Type is not valid.");
		}
	}

	if ($pconfig['maxclients'] && !is_numericint($pconfig['maxclients'])) {
		$input_errors[] = gettext("The field 'Concurrent connections' must be numeric.");
	}

	if ($pconfig['connlimit'] && !is_numericint($pconfig['connlimit'])) {
		$input_errors[] = gettext("The field 'Duplicate Connection Limit' must be numeric.");
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
	} elseif (!$pconfig['autokey_enable']) {
		/* We only need the shared key filled in if we are in shared key mode and autokey is not selected. */
		$reqdfields = array('shared_key');
		$reqdfieldsn = array(gettext('Shared key'));
	}

	if (($pconfig['mode'] == "p2p_shared_key") && strstr($pconfig['data_ciphers_fallback'], "GCM")) {
		$input_errors[] = gettext("GCM Encryption Algorithms cannot be used with Shared Key mode.");
	}

	if ($pconfig['dev_mode'] == "tap") {
		if (!empty($pconfig['serverbridge_interface']) &&
		    !array_key_exists($pconfig['serverbridge_interface'], openvpn_build_bridge_list())) {
			$input_errors[] = gettext("The selected Server Bridge Interface is not valid.");
		} elseif (empty($pconfig['serverbridge_interface']) && empty($pconfig["tunnel_network"]) &&
		    empty($pconfig["tunnel_networkv6"])) {
			$input_errors[] = gettext("TAP server mode requires an IPv4/IPv6 Tunnel Network or Bridge Interface to work.");
		}

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
	if (!empty($pconfig['verbosity_level']) &&
	    !array_key_exists($pconfig['verbosity_level'], $openvpn_verbosity_level)) {
		$input_errors[] = gettext("The selected Verbosity Level is not valid.");
	}

	do_input_validation($post, $reqdfields, $reqdfieldsn, $input_errors);

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

		if ($post['disable'] == "yes") {
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
			$server['remote_cert_tls'] = $pconfig['remote_cert_tls'];
		} else {
			$server['shared_key'] = base64_encode($pconfig['shared_key']);
		}

		$server['data_ciphers_fallback'] = $pconfig['data_ciphers_fallback'];
		$server['digest'] = $pconfig['digest'];
		$server['engine'] = $pconfig['engine'];

		foreach (array('', 'v6') as $ntype) {
			$server["tunnel_network{$ntype}"] = openvpn_tunnel_network_fix($pconfig["tunnel_network{$ntype}"]);
		}
		$server['remote_network'] = $pconfig['remote_network'];
		$server['remote_networkv6'] = $pconfig['remote_networkv6'];
		$server['gwredir'] = $pconfig['gwredir'];
		$server['gwredir6'] = $pconfig['gwredir6'];
		$server['local_network'] = $pconfig['local_network'];
		$server['local_networkv6'] = $pconfig['local_networkv6'];
		$server['maxclients'] = $pconfig['maxclients'];
		$server['connlimit'] = $pconfig['connlimit'];
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

		if ($post['duplicate_cn'] == "yes") {
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

		if (($act == 'new') || ($server['disable'] ^ $a_server[$id]['disable']) ||
		    ($server['tunnel_network'] != $a_server[$id]['tunnel_network']) ||
		    ($server['tunnel_networkv6'] != $a_server[$id]['tunnel_networkv6'])) {
			$server['unbound_restart'] = true;
		}

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
	}

	if (!empty($pconfig['data_ciphers'])) {
		$pconfig['data_ciphers'] = implode(",", $pconfig['data_ciphers']);
	}

	if (!empty($pconfig['authmode'])) {
		$pconfig['authmode'] = implode(",", $pconfig['authmode']);
	}
    $rv = array();
    $rv['pconfig'] = $pconfig;
    $rv['input_errors'] = $input_errors;

    return $json ? json_encode($rv, true) : $rv;
}

 ?>