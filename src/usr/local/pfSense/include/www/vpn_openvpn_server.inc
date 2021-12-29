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
            $rv['input_errors'] = gettext("No server exists with specified ID");
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
 ?>