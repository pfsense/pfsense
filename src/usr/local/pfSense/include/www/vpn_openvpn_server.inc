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
    $rv['wc_msg'] = $wc_msg;

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
 ?>