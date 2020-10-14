<?php

/*
 * vpn_ipsec_phase1.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008 Shrew Soft Inc
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
##|*IDENT=page-vpn-ipsec-editphase1
##|*NAME=VPN: IPsec: Edit Phase 1
##|*DESCR=Allow access to the 'VPN: IPsec: Edit Phase 1' page.
##|*MATCH=vpn_ipsec_phase1.php*
##|-PRIV

require_once("functions.inc");
require_once("guiconfig.inc");
require_once("ipsec.inc");
require_once("vpn.inc");
require_once("filter.inc");

if ($_REQUEST['generatekey']) {
	$keyoutput = "";
	$keystatus = "";
	exec("/bin/dd status=none if=/dev/random bs=4096 count=1 | /usr/bin/openssl sha224 | /usr/bin/cut -f2 -d' '", $keyoutput, $keystatus);
	print json_encode(['pskey' => $keyoutput[0]]);
	exit;
}

init_config_arr(array('ipsec', 'phase1'));
init_config_arr(array('ipsec', 'phase2'));
init_config_arr(array('ipsec', 'vtimaps', 'item'));
$a_phase1 = &$config['ipsec']['phase1'];
$a_phase2 = &$config['ipsec']['phase2'];
$a_vtimaps = &$config['ipsec']['vtimaps']['item'];

if (is_numericint($_REQUEST['p1index'])) {
	$p1index = $_REQUEST['p1index'];
}

if (is_numericint($_REQUEST['dup'])) {
	$p1index = $_REQUEST['dup'];
}

if (isset($p1index) && $a_phase1[$p1index]) {
	// don't copy the ikeid on dup
	if (!isset($_REQUEST['dup']) || !is_numericint($_REQUEST['dup'])) {
		$pconfig['ikeid'] = $a_phase1[$p1index]['ikeid'];
	}

	$old_ph1ent = $a_phase1[$p1index];

	$pconfig['disabled'] = isset($a_phase1[$p1index]['disabled']);

	if ($a_phase1[$p1index]['interface']) {
		$pconfig['interface'] = $a_phase1[$p1index]['interface'];
	} else {
		$pconfig['interface'] = "wan";
	}

	list($pconfig['remotenet'], $pconfig['remotebits']) = explode("/", $a_phase1[$p1index]['remote-subnet']);

	if (isset($a_phase1[$p1index]['mobile'])) {
		$pconfig['mobile'] = 'true';
	} else {
		$pconfig['remotegw'] = $a_phase1[$p1index]['remote-gateway'];
		$pconfig['ikeport'] = $a_phase1[$p1index]['ikeport'];
		$pconfig['nattport'] = $a_phase1[$p1index]['nattport'];
	}

	if (empty($a_phase1[$p1index]['iketype'])) {
		$pconfig['iketype'] = "ikev1";
	} else {
		$pconfig['iketype'] = $a_phase1[$p1index]['iketype'];
	}
	$pconfig['mode'] = $a_phase1[$p1index]['mode'];
	$pconfig['protocol'] = $a_phase1[$p1index]['protocol'];
	$pconfig['myid_type'] = $a_phase1[$p1index]['myid_type'];
	$pconfig['myid_data'] = $a_phase1[$p1index]['myid_data'];
	$pconfig['peerid_type'] = $a_phase1[$p1index]['peerid_type'];
	$pconfig['peerid_data'] = $a_phase1[$p1index]['peerid_data'];
	$pconfig['encryption'] = $a_phase1[$p1index]['encryption'];
	$pconfig['rekey_time'] = $a_phase1[$p1index]['rekey_time'];
	$pconfig['reauth_time'] = $a_phase1[$p1index]['reauth_time'];
	$pconfig['over_time'] = $a_phase1[$p1index]['over_time'];
	$pconfig['authentication_method'] = $a_phase1[$p1index]['authentication_method'];

	if (($pconfig['authentication_method'] == "pre_shared_key") ||
	    ($pconfig['authentication_method'] == "xauth_psk_server")) {
		$pconfig['pskey'] = $a_phase1[$p1index]['pre-shared-key'];
	} else {
		$pconfig['pkcs11certref'] = $a_phase1[$p1index]['pkcs11certref'];
		$pconfig['pkcs11pin'] = $a_phase1[$p1index]['pkcs11pin'];
		$pconfig['certref'] = $a_phase1[$p1index]['certref'];
		$pconfig['caref'] = $a_phase1[$p1index]['caref'];
	}

	$pconfig['descr'] = $a_phase1[$p1index]['descr'];
	$pconfig['nat_traversal'] = $a_phase1[$p1index]['nat_traversal'];
	$pconfig['mobike'] = $a_phase1[$p1index]['mobike'];

	if (isset($a_phase1[$p1index]['gw_duplicates'])) {
		$pconfig['gw_duplicates'] = true;
	}

	$pconfig['closeaction'] = $a_phase1[$p1index]['closeaction'];

	if (isset($a_phase1[$p1index]['responderonly'])) {
		$pconfig['responderonly'] = true;
	}

	if ($a_phase1[$p1index]['dpd_delay'] && $a_phase1[$p1index]['dpd_maxfail']) {
		$pconfig['dpd_enable'] = true;
		$pconfig['dpd_delay'] = $a_phase1[$p1index]['dpd_delay'];
		$pconfig['dpd_maxfail'] = $a_phase1[$p1index]['dpd_maxfail'];
	}

	if (isset($a_phase1[$p1index]['prfselect_enable'])) {
		$pconfig['prfselect_enable'] = 'yes';
	}

	if (isset($a_phase1[$p1index]['splitconn'])) {
		$pconfig['splitconn'] = true;
	}

	if (isset($a_phase1[$p1index]['tfc_enable'])) {
		$pconfig['tfc_enable'] = true;
	}

	if (isset($a_phase1[$p1index]['tfc_bytes'])) {
		$pconfig['tfc_bytes'] = $a_phase1[$p1index]['tfc_bytes'];
	}
} else {
	/* defaults */
	$pconfig['interface'] = "wan";
	if ($config['interfaces']['lan']) {
		$pconfig['localnet'] = "lan";
	}
	$pconfig['mode'] = "main";
	$pconfig['protocol'] = "inet";
	$pconfig['myid_type'] = "myaddress";
	$pconfig['peerid_type'] = "peeraddress";
	$pconfig['authentication_method'] = "pre_shared_key";
	$pconfig['reauth_time'] = "28800";
	$pconfig['nat_traversal'] = 'on';
	$pconfig['mobike'] = 'off';
	$pconfig['prfselect_enable'] = false;
	$pconfig['dpd_enable'] = true;
	$pconfig['iketype'] = "ikev1";

	/* mobile client */
	if ($_REQUEST['mobile']) {
		$pconfig['mobile'] = true;
		$pconfig['mode'] = "aggressive";
	}
}
// default value for new P1 and failsafe to always have at least 1 encryption item for the Form_ListItem
if (!is_array($pconfig['encryption']['item']) || count($pconfig['encryption']['item']) == 0) {
	$item = array();
	$item['encryption-algorithm'] = array('name' => "aes", 'keylen' => 128);
	$item['hash-algorithm'] = "sha256";
	$item['prf-algorithm'] = "sha256";
	$item['dhgroup'] = "14";
	$pconfig['encryption']['item'][] = $item;
}

if (isset($_REQUEST['dup']) && is_numericint($_REQUEST['dup'])) {
	unset($p1index);
}

if ($_POST['save']) {
	unset($input_errors);
	$pconfig = $_POST;

	for($i = 0; $i < 100; $i++) {
		if (isset($_POST['ealgo_algo'.$i])) {
			$item = array();
			$item['encryption-algorithm']['name'] = $_POST['ealgo_algo'.$i];
			$item['encryption-algorithm']['keylen'] = $_POST['ealgo_keylen'.$i];
			$item['hash-algorithm'] = $_POST['halgo'.$i];
			$item['prf-algorithm'] = $_POST['prfalgo'.$i];
			$item['dhgroup'] = $_POST['dhgroup'.$i];
			$pconfig['encryption']['item'][] = $item;
		}
	}

	/* input validation */

	$method = $pconfig['authentication_method'];

	// Unset ca and cert if not required to avoid storing in config
	if ($method == "pre_shared_key" || $method == "xauth_psk_server") {
		unset($pconfig['certref']);
		unset($pconfig['pkcs11certref']);
	}

	if (!in_array($method, array('cert', 'eap-tls', 'xauth_cert_server', 'pkcs11'))) {
		unset($pconfig['caref']);
	}

	// Only require PSK here for normal PSK tunnels (not mobile) or xauth.
	// For certificate methods, require the CA/Cert.
	switch ($method) {
		case 'eap-mschapv2':
			if ($pconfig['iketype'] != 'ikev2') {
				$input_errors[] = gettext("EAP-MSChapv2 can only be used with IKEv2 type VPNs.");
			}
			break;
		case "eap-tls":
			if ($pconfig['iketype'] != 'ikev2') {
				$input_errors[] = gettext("EAP-TLS can only be used with IKEv2 type VPNs.");
			}
			break;
		case "eap-radius":
			if ($pconfig['iketype'] != 'ikev2') {
				$input_errors[] = gettext("EAP-RADIUS can only be used with IKEv2 type VPNs.");
			}
			break;
		case "pre_shared_key":
			// If this is a mobile PSK tunnel the user PSKs go on
			//	  the PSK tab, not here, so skip the check.
			if ($pconfig['mobile']) {
				break;
			}
		case "xauth_psk_server":
			$reqdfields = explode(" ", "pskey");
			$reqdfieldsn = array(gettext("Pre-Shared Key"));
			$validate_pskey = true;
			break;
		case "xauth_cert_server":
		case "cert":
			$reqdfields = explode(" ", "caref certref");
			$reqdfieldsn = array(gettext("Certificate Authority"), gettext("Certificate"));
			break;
		case "pkcs11":
			$reqdfields = explode(" ", "caref pkcs11certref pkcs11pin");
			$reqdfieldsn = array(gettext("Certificate Authority"), gettext("Token Certificate"), gettext("Token PIN"));
			break;
		default:
			/* Other types do not use this validation mechanism. */
	}

	if (!$pconfig['mobile']) {
		$reqdfields[] = "remotegw";
		$reqdfieldsn[] = gettext("Remote gateway");
	}

	do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

	if (isset($validate_pskey) && isset($pconfig['pskey']) && !preg_match('/^[[:ascii:]]*$/', $pconfig['pskey'])) {
		unset($validate_pskey);
		$input_errors[] = gettext("Pre-Shared Key contains invalid characters.");
	}

	if (!empty($pconfig['rekey_time']) && !is_numericint($pconfig['rekey_time'])) {
		$input_errors[] = gettext("Rekey Time must be an integer.");
	}
	if (!empty($pconfig['reauth_time']) && !is_numericint($pconfig['reauth_time'])) {
		$input_errors[] = gettext("Reauth Time must be an integer.");
	}
	if (!empty($pconfig['over_time']) && !is_numericint($pconfig['over_time'])) {
		$input_errors[] = gettext("Over Time must be an integer.");
	}

	if (!empty($pconfig['closeaction']) && !array_key_exists($pconfig['closeaction'], $ipsec_closeactions)) {
		$input_errors[] = gettext("Invalid Child SA Close Action.");
	}

	if ($pconfig['remotegw']) {
		if (!is_ipaddr($pconfig['remotegw']) && !is_domain($pconfig['remotegw'])) {
			$input_errors[] = gettext("A valid remote gateway address or host name must be specified.");
		} elseif (is_ipaddrv4($pconfig['remotegw']) && ($pconfig['protocol'] == "inet6")) {
			$input_errors[] = gettext("A valid remote gateway IPv4 address must be specified or protocol needs to be changed to IPv6");
		} elseif (is_ipaddrv6($pconfig['remotegw']) && ($pconfig['protocol'] == "inet")) {
			$input_errors[] = gettext("A valid remote gateway IPv6 address must be specified or protocol needs to be changed to IPv4");
		}
	}

	if ($_POST['ikeport']) {
		if (!is_port($pconfig['ikeport'])) {
			$input_errors[] = gettext("The IKE port number is invalid.");
		}
	} else {
		unset($pconfig['ikeport']);
	}

	if ($_POST['nattport']) {
		if (!is_port($pconfig['nattport'])) {
			$input_errors[] = gettext("The NAT-T port number is invalid.");
		}
	} else {
		unset($pconfig['nattport']);
	}

	if (isset($pconfig['ikeport']) && isset($pconfig['nattport']) && $pconfig['ikeport'] == $pconfig['nattport']) {
		$input_errors[] = gettext("IKE and NAT-T port numbers must be different.");
	}

	if ($pconfig['remotegw'] && is_ipaddr($pconfig['remotegw']) && !isset($pconfig['disabled'])) {
		$t = 0;
		foreach ($a_phase1 as $ph1tmp) {
			if ($p1index != $t) {
				$tremotegw = $pconfig['remotegw'];
				if (($ph1tmp['remote-gateway'] == $tremotegw) && ($ph1tmp['remote-gateway'] != '0.0.0.0') &&
				    ($ph1tmp['remote-gateway'] != '::') && !isset($ph1tmp['disabled']) &&
				    (!isset($pconfig['gw_duplicates']) || !isset($ph1tmp['gw_duplicates']))) {
					$input_errors[] = sprintf(gettext('The remote gateway "%1$s" is already used by phase1 "%2$s".'), $tremotegw, $ph1tmp['descr']);
				}
			}
			$t++;
		}
	}

	if (($pconfig['remotegw'] == '0.0.0.0') || ($pconfig['remotegw'] == '::')) {
		if (!isset($pconfig['responderonly'])) {
			$input_errors[] = gettext('The remote gateway "0.0.0.0" or "::" address can only be used with "Responder Only".');
		}
		if ($pconfig['peerid_type'] == "peeraddress") {
			$input_errors[] = gettext('The remote gateway "0.0.0.0" or "::" address can not be used with IP address peer identifier.');
		}

	}

	if (($pconfig['iketype'] == "ikev1") && is_array($a_phase2) && (count($a_phase2))) {
		foreach ($a_phase2 as $phase2) {
			if ($phase2['ikeid'] == $pconfig['ikeid']) {
				if (($pconfig['protocol'] == "inet") && ($phase2['mode'] == "tunnel6")) {
					$input_errors[] = gettext("There is a Phase 2 using IPv6, cannot use IPv4.");
					break;
				}
				if (($pconfig['protocol'] == "inet6") && ($phase2['mode'] == "tunnel")) {
					$input_errors[] = gettext("There is a Phase 2 using IPv4, cannot use IPv6.");
					break;
				}
			}
		}
	}

	/* My identity */

	if ($pconfig['myid_type'] == "myaddress") {
		$pconfig['myid_data'] = "";
	}

	if ($pconfig['myid_type'] == "address" and $pconfig['myid_data'] == "") {
		$input_errors[] = gettext("Please enter an address for 'My Identifier'");
	}

	if ($pconfig['myid_type'] == "keyid tag" and $pconfig['myid_data'] == "") {
		$input_errors[] = gettext("Please enter a keyid tag for 'My Identifier'");
	}

	if ($pconfig['myid_type'] == "fqdn" and $pconfig['myid_data'] == "") {
		$input_errors[] = gettext("Please enter a fully qualified domain name for 'My Identifier'");
	}

	if ($pconfig['myid_type'] == "user_fqdn" and $pconfig['myid_data'] == "") {
		$input_errors[] = gettext("Please enter a user and fully qualified domain name for 'My Identifier'");
	}

	if ($pconfig['myid_type'] == "dyn_dns" and $pconfig['myid_data'] == "") {
		$input_errors[] = gettext("Please enter a dynamic domain name for 'My Identifier'");
	}

	if (($pconfig['myid_type'] == "address") && !is_ipaddr($pconfig['myid_data'])) {
		$input_errors[] = gettext("A valid IP address for 'My identifier' must be specified.");
	}

	if (($pconfig['myid_type'] == "fqdn") && !is_domain($pconfig['myid_data'])) {
		$input_errors[] = gettext("A valid domain name for 'My identifier' must be specified.");
	}

	if ($pconfig['myid_type'] == "fqdn") {
		if (is_domain($pconfig['myid_data']) == false) {
			$input_errors[] = gettext("A valid FQDN for 'My identifier' must be specified.");
		}
	}

	if ($pconfig['myid_type'] == "user_fqdn") {
		$user_fqdn = explode("@", $pconfig['myid_data']);
		if (is_domain($user_fqdn[1]) == false) {
			$input_errors[] = gettext("A valid User FQDN in the form of user@my.domain.com for 'My identifier' must be specified.");
		}
	}

	if ($pconfig['myid_type'] == "dyn_dns") {
		if (is_domain($pconfig['myid_data']) == false) {
			$input_errors[] = gettext("A valid Dynamic DNS address for 'My identifier' must be specified.");
		}
	}

	/* Peer identity */

	if ($pconfig['myid_type'] == "peeraddress") {
		$pconfig['peerid_data'] = "";
	}

	// Only enforce peer ID if we are not dealing with a pure-psk mobile config.
	if (!(($pconfig['authentication_method'] == "pre_shared_key") && ($pconfig['mobile']))) {
		if ($pconfig['peerid_type'] == "address" and $pconfig['peerid_data'] == "") {
			$input_errors[] = gettext("Please enter an address for 'Peer Identifier'");
		}

		if ($pconfig['peerid_type'] == "keyid tag" and $pconfig['peerid_data'] == "") {
			$input_errors[] = gettext("Please enter a keyid tag for 'Peer Identifier'");
		}

		if ($pconfig['peerid_type'] == "fqdn" and $pconfig['peerid_data'] == "") {
			$input_errors[] = gettext("Please enter a fully qualified domain name for 'Peer Identifier'");
		}

		if ($pconfig['peerid_type'] == "user_fqdn" and $pconfig['peerid_data'] == "") {
			$input_errors[] = gettext("Please enter a user and fully qualified domain name for 'Peer Identifier'");
		}

		if ((($pconfig['peerid_type'] == "address") && !is_ipaddr($pconfig['peerid_data']))) {
			$input_errors[] = gettext("A valid IP address for 'Peer identifier' must be specified.");
		}

		if ((($pconfig['peerid_type'] == "fqdn") && !is_domain($pconfig['peerid_data']))) {
			$input_errors[] = gettext("A valid domain name for 'Peer identifier' must be specified.");
		}

		if ($pconfig['peerid_type'] == "fqdn") {
			if (is_domain($pconfig['peerid_data']) == false) {
				$input_errors[] = gettext("A valid FQDN for 'Peer identifier' must be specified.");
			}
		}

		if ($pconfig['peerid_type'] == "user_fqdn") {
			$user_fqdn = explode("@", $pconfig['peerid_data']);
			if (is_domain($user_fqdn[1]) == false) {
				$input_errors[] = gettext("A valid User FQDN in the form of user@my.domain.com for 'Peer identifier' must be specified.");
			}
		}
	}

	if ($pconfig['dpd_enable']) {
		if (!is_numericint($pconfig['dpd_delay'])) {
			$input_errors[] = gettext("A numeric value must be specified for DPD delay.");
		}

		if (!is_numericint($pconfig['dpd_maxfail'])) {
			$input_errors[] = gettext("A numeric value must be specified for DPD retries.");
		}
	}

	if ($pconfig['tfc_bytes'] && !is_numericint($pconfig['tfc_bytes'])) {
		$input_errors[] = gettext("A numeric value must be specified for TFC bytes.");
	}

	if (!empty($pconfig['iketype']) && $pconfig['iketype'] != "ikev1" && $pconfig['iketype'] != "ikev2" && $pconfig['iketype'] != "auto") {
		$input_errors[] = gettext("Valid arguments for IKE type are v1, v2 or auto");
	}

	foreach($pconfig['encryption']['item'] as $p1algo) {
		if (preg_match("/aes\d+gcm/", $p1algo['encryption-algorithm']['name']) && $_POST['iketype'] != "ikev2") {
			$input_errors[] = gettext("Encryption Algorithm AES-GCM can only be used with IKEv2");
		}
	}
	/* auth backend for mobile eap-radius VPNs should be a RADIUS server */
	if (($pconfig['authentication_method'] == 'eap-radius') && $pconfig['mobile']) {
		if (!empty($config['ipsec']['client']['user_source'])) {
			$auth_server_list  = explode(',', $config['ipsec']['client']['user_source']);
			foreach ($auth_server_list as $auth_server_name) {
				$auth_server       = auth_get_authserver($auth_server_name);
				if (!is_array($auth_server) || ($auth_server['type'] != 'radius')) {
					$input_errors[] = gettext("A valid RADIUS server must be selected for user authentication on the Mobile Clients tab in order to set EAP-RADIUS as the authentication method.");
				}
			}
		}
	}
	if (is_array($old_ph1ent) && ipsec_vti($old_ph1ent, false, false) && $pconfig['disabled']) {
		$input_errors[] = gettext("Cannot disable a Phase 1 with a child Phase 2 while the interface is assigned. Remove the interface assignment before disabling this P2.");
	}

	if (!empty($pconfig['certref'])) {
		$errchkcert =& lookup_cert($pconfig['certref']);
		if (is_array($errchkcert) && !cert_check_pkey_compatibility($errchkcert['prv'], 'IPsec')) {
			$input_errors[] = gettext("The selected ECDSA certificate does not use a curve compatible with IKEv2");
		}
	}

	if (!$input_errors) {
		$ph1ent['ikeid'] = $pconfig['ikeid'];
		$ph1ent['iketype'] = $pconfig['iketype'];
		if ($pconfig['iketype'] == 'ikev2') {
			unset($ph1ent['mode']);
		} else {
			$ph1ent['mode'] = $pconfig['mode'];
		}

		// re-create vtimaps after IKE version switching
		$vtisubnet_spec = ipsec_vti($ph1ent, true);
		if ((($pconfig['iketype'] != $a_phase1[$p1index]['iketype']) ||
		    (isset($pconfig['splitconn']) != isset($a_phase1[$p1index]['splitconn']))) &&
		    ($vtisubnet_spec || is_array($vtisubnet_spec))) {
			foreach ($a_vtimaps as $id => $vtimap) {
				if ($vtimap['reqid'] == $ph1ent['ikeid']) {
					unset($a_vtimaps[$id]);
				}
			}
			if (($pconfig['iketype'] == 'ikev1') ||
			    isset($pconfig['splitconn'])) {
				foreach ($vtisubnet_spec as $idx => $vtisub) {
					$a_vtimaps[] = ipsec_create_vtimap(
					    $ph1ent['ikeid'], $idx);
				}
			} else {
				$a_vtimaps[] = ipsec_create_vtimap(
				    $ph1ent['ikeid'], 0);
			}
		}

		$ph1ent['disabled'] = $pconfig['disabled'] ? true : false;
		$ph1ent['interface'] = $pconfig['interface'];
		/* if the remote gateway changed and the interface is not WAN then remove route */
		/* the ipsec_configure() handles adding the route */
		if ($pconfig['interface'] <> "wan") {
			if ($old_ph1ent['remote-gateway'] <> $pconfig['remotegw']) {
				route_del($old_ph1ent['remote-gateway']);
			}
		}

		if ($pconfig['mobile']) {
			$ph1ent['mobile'] = true;
		} else {
			$ph1ent['remote-gateway'] = $pconfig['remotegw'];
			if ( !empty($pconfig['ikeport']) ) {
				$ph1ent['ikeport'] = $pconfig['ikeport'];
			} else {
				unset($ph1ent['ikeport']);
			}
			if ( !empty($pconfig['nattport']) ) {
				$ph1ent['nattport'] = $pconfig['nattport'];
			} else {
				unset($ph1ent['nattport']);
			}
		}

		$ph1ent['protocol'] = $pconfig['protocol'];

		$ph1ent['myid_type'] = $pconfig['myid_type'];
		$ph1ent['myid_data'] = $pconfig['myid_data'];
		$ph1ent['peerid_type'] = $pconfig['peerid_type'];
		$ph1ent['peerid_data'] = $pconfig['peerid_data'];

		$ph1ent['encryption'] = $pconfig['encryption'];
		$ph1ent['rekey_time'] = $pconfig['rekey_time'];
		$ph1ent['reauth_time'] = $pconfig['reauth_time'];
		$ph1ent['over_time'] = $pconfig['over_time'];
		$ph1ent['pre-shared-key'] = $pconfig['pskey'];
		$ph1ent['private-key'] = base64_encode($pconfig['privatekey']);
		$ph1ent['certref'] = $pconfig['certref'];
		$ph1ent['pkcs11certref'] = $pconfig['pkcs11certref'];
		$ph1ent['pkcs11pin'] = $pconfig['pkcs11pin'];
		$ph1ent['caref'] = $pconfig['caref'];
		$ph1ent['authentication_method'] = $pconfig['authentication_method'];
		$ph1ent['descr'] = $pconfig['descr'];
		$ph1ent['nat_traversal'] = $pconfig['nat_traversal'];
		$ph1ent['mobike'] = $pconfig['mobike'];

		if ( isset($pconfig['gw_duplicates'])) {
			$ph1ent['gw_duplicates'] = true;
		} else {
			unset($ph1ent['gw_duplicates']);
		}

		$ph1ent['closeaction'] = $pconfig['closeaction'];

		if (isset($pconfig['prfselect_enable'])) {
			$ph1ent['prfselect_enable'] = 'yes';
		} else {
			unset($ph1ent['prfselect_enable']);
		}

		if (isset($pconfig['responderonly'])) {
			$ph1ent['responderonly'] = true;
		} else {
			unset($ph1ent['responderonly']);
		}

		if (isset($pconfig['dpd_enable'])) {
			$ph1ent['dpd_delay'] = $pconfig['dpd_delay'];
			$ph1ent['dpd_maxfail'] = $pconfig['dpd_maxfail'];
		}

		if (isset($pconfig['splitconn'])) {
			$ph1ent['splitconn'] = true;
		} else {
			unset($ph1ent['splitconn']);
		}

		if (isset($pconfig['tfc_enable'])) {
			$ph1ent['tfc_enable'] = true;
		}

		if (isset($pconfig['tfc_bytes'])) {
			$ph1ent['tfc_bytes'] = $pconfig['tfc_bytes'];
		}

		/* generate unique phase1 ikeid */
		if ($ph1ent['ikeid'] == 0) {
			$ph1ent['ikeid'] = ipsec_ikeid_next();
		}

		if (isset($p1index) && $a_phase1[$p1index]) {
			$a_phase1[$p1index] = $ph1ent;
		} else {
			$a_phase1[] = $ph1ent;
		}

		write_config(gettext("Saved IPsec tunnel Phase 1 configuration."));
		mark_subsystem_dirty('ipsec');

		header("Location: vpn_ipsec.php");
		exit;
	}
}

function build_interface_list() {
	$interfaces = get_configured_interface_with_descr();

	$viplist = get_configured_vip_list();
	foreach ($viplist as $vip => $address) {
		$interfaces[$vip] = $address;
		if (get_vip_descr($address)) {
			$interfaces[$vip] .= " (". get_vip_descr($address) .")";
		}
	}

	$grouplist = return_gateway_groups_array();

	foreach ($grouplist as $name => $group) {
		if ($group[0]['vip'] != "") {
			$vipif = $group[0]['vip'];
		} else {
			$vipif = $group[0]['int'];
		}

		$interfaces[$name] = sprintf(gettext("GW Group %s"), $name);
	}

	return($interfaces);

}

function build_auth_method_list() {
	global $p1_authentication_methods, $pconfig;

	$list = array();

	foreach ($p1_authentication_methods as $method_type => $method_params) {
		if (!$pconfig['mobile'] && $method_params['mobile']) {
			continue;
		}

		$list[$method_type] = htmlspecialchars($method_params['name']);
	}

	return($list);
}

function build_myid_list() {
	global $my_identifier_list;

	$list = array();

	foreach ($my_identifier_list as $id_type => $id_params) {
		$list[$id_type] = htmlspecialchars($id_params['desc']);
	}

	return($list);
}

function build_peerid_list() {
	global $peer_identifier_list;

	$list = array();

	foreach ($peer_identifier_list as $id_type => $id_params) {
		$list[$id_type] = htmlspecialchars($id_params['desc']);
	}

	return($list);
}

function build_pkcs11cert_list() {
	global $config;

	$list = array();
	$p11_cn = array();
	$p11_id = array();
	$output = shell_exec('/usr/local/bin/pkcs15-tool -c');

	preg_match_all('/X\.509\ Certificate\ \[(.*)\]/', $output, $p11_cn);
	preg_match_all('/ID\s+: (.*)/', $output, $p11_id);

	if (is_array($p11_id)) {
		for ($i = 0; $i < count($p11_id[1]); $i++) {
			$list[$p11_id[1][$i]] = "{$p11_cn[1][$i]} " . "({$p11_id[1][$i]})";
		}
	}
	return($list);
}

function build_eal_list() {
	global $p1_ealgos;

	$list = array();

	if (is_array($p1_ealgos)) {
		foreach ($p1_ealgos as $algo => $algodata) {
			$list[$algo] = htmlspecialchars($algodata['name']);
		}
	}

	return($list);
}

if ($pconfig['mobile']) {
	$pgtitle = array(gettext("VPN"), gettext("IPsec"), gettext("Mobile Clients"), gettext("Edit Phase 1"));
	$pglinks = array("", "vpn_ipsec.php", "vpn_ipsec_mobile.php", "@self");
} else {
	$pgtitle = array(gettext("VPN"), gettext("IPsec"), gettext("Tunnels"), gettext("Edit Phase 1"));
	$pglinks = array("", "vpn_ipsec.php", "vpn_ipsec.php", "@self");
}

$shortcut_section = "ipsec";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array(gettext("Tunnels"), true, "vpn_ipsec.php");
$tab_array[] = array(gettext("Mobile Clients"), false, "vpn_ipsec_mobile.php");
$tab_array[] = array(gettext("Pre-Shared Keys"), false, "vpn_ipsec_keys.php");
$tab_array[] = array(gettext("Advanced Settings"), false, "vpn_ipsec_settings.php");
display_top_tabs($tab_array);

$form = new Form();

$section = new Form_Section('General Information');

$section->addInput(new Form_Checkbox(
	'disabled',
	'Disabled',
	'Set this option to disable this phase1 without removing it from the list. ',
	$pconfig['disabled']
));

$section->addInput(new Form_Select(
	'iketype',
	'*Key Exchange version',
	$pconfig['iketype'],
	array("ikev1" => "IKEv1", "ikev2" => "IKEv2", "auto" => gettext("Auto"))
))->setHelp('Select the Internet Key Exchange protocol version to be used. Auto uses IKEv2 when initiator, and accepts either IKEv1 or IKEv2 as responder.');

$section->addInput(new Form_Select(
	'protocol',
	'*Internet Protocol',
	$pconfig['protocol'],
	array("inet" => "IPv4", "inet6" => "IPv6", "both" => "Both (Dual Stack)")
))->setHelp('Select the Internet Protocol family.');

$section->addInput(new Form_Select(
	'interface',
	'*Interface',
	$pconfig['interface'],
	build_interface_list()
))->setHelp('Select the interface for the local endpoint of this phase1 entry.');

if (!$pconfig['mobile']) {
	$group = new Form_Group('*Remote Gateway');

	$group->add(new Form_Input(
		'remotegw',
		'Remote Gateway',
		'text',
		$pconfig['remotegw']
	))->setHelp('Enter the public IP address or host name of the remote gateway.%1$s%2$s%3$s',
	    '<div class="infoblock">',
	    sprint_info_box(gettext('Use \'0.0.0.0\' to allow connections from any IPv4 address or \'::\' ' . 
	    'to allow connections from any IPv6 address.' . '<br/>' . 'Responder Only must be set and ' . 
	    'Peer IP Address cannot be used for Remote Identifier.'), 'info', false),
	    '</div>');
	$group->add(new Form_Input(
	    'ikeport',
	    'Remote IKE Port',
	    'number',
	    $pconfig['ikeport'],
	    ['min' => 1, 'max' => 65535]
	))->setHelp('UDP port for IKE on the remote gateway. Leave empty for default automatic behavior (500/4500).');
	$group->add(new Form_Input(
	    'nattport',
	    'Remote NAT-T Port',
	    'number',
	    $pconfig['nattport'],
	    ['min' => 1, 'max' => 65535]
	))->setHelp('UDP port for NAT-T on the remote gateway.%1$s%2$s%3$s',
	    '<div class="infoblock">',
	    sprint_info_box(gettext('If the IKE port is empty and NAT-T contains a value, the tunnel will use only NAT-T.'),
	    'info', false),
	    '</div>');
	$section->add($group);
}

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

$form->add($section);

$section = new Form_Section('Phase 1 Proposal (Authentication)');

$section->addInput(new Form_Select(
	'authentication_method',
	'*Authentication Method',
	$pconfig['authentication_method'],
	build_auth_method_list()
))->setHelp('Must match the setting chosen on the remote side.');

$section->addInput(new Form_Select(
	'mode',
	'*Negotiation mode',
	$pconfig['mode'],
	array("main" => gettext("Main"), "aggressive" => gettext("Aggressive"))
))->setHelp('Aggressive is more flexible, but less secure.');

$group = new Form_Group('*My identifier');

$group->add(new Form_Select(
	'myid_type',
	null,
	$pconfig['myid_type'],
	build_myid_list()
));

$group->add(new Form_Input(
	'myid_data',
	null,
	'text',
	$pconfig['myid_data']
));

$section->add($group);

$group = new Form_Group('*Peer identifier');
$group->addClass('peeridgroup');

$group->add(new Form_Select(
	'peerid_type',
	null,
	$pconfig['peerid_type'],
	build_peerid_list()
));

$group->add(new Form_Input(
	'peerid_data',
	null,
	'text',
	$pconfig['peerid_data']
));

if ($pconfig['mobile']) {
	$group->setHelp('This is known as the "group" setting on some VPN client implementations');
}

$section->add($group);

$section->addInput(new Form_Input(
	'pskey',
	'*Pre-Shared Key',
	'text',
	$pconfig['pskey']
))->setHelp('Enter the Pre-Shared Key string. This key must match on both peers. %1$sThis key should be long and random to protect the tunnel and its contents. A weak Pre-Shared Key can lead to a tunnel compromise.%1$s', '<br/>');

$section->addInput(new Form_Select(
	'certref',
	'*My Certificate',
	$pconfig['certref'],
	cert_build_list('cert', 'IPsec')
))->setHelp('Select a certificate previously configured in the Certificate Manager.');

$section->addInput(new Form_Select(
	'pkcs11certref',
	'*PKCS#11 Certificate',
	$pconfig['pkcs11certref'],
	build_pkcs11cert_list()
))->setHelp('Select a Certificate from an attached PKCS#11 token device');

$section->addInput(new Form_Input(
	'pkcs11pin',
	'*PKCS#11 PIN',
	'text',
	$pconfig['pkcs11pin']
))->setHelp('Enter PKCS#11 token PIN number');

$section->addInput(new Form_Select(
	'caref',
	'*Peer Certificate Authority',
	$pconfig['caref'],
	cert_build_list('ca', 'IPsec')
))->setHelp('Select a certificate authority previously configured in the Certificate Manager.');

$form->add($section);

$rowcount = count($pconfig['encryption']['item']);
$section = new Form_Section('Phase 1 Proposal (Encryption Algorithm)');
foreach($pconfig['encryption']['item'] as $key => $p1enc) {
	$lastrow = ($counter == $rowcount - 1);
	$group = new Form_Group($counter == 0 ? '*Encryption Algorithm' : '');
	$group->addClass("repeatable");

	$group->add(new Form_Select(
		'ealgo_algo'.$key,
		null,
		$p1enc['encryption-algorithm']['name'],
		build_eal_list()
	))->setHelp($lastrow ? 'Algorithm' : '')->setWidth(2);

	$group->add(new Form_Select(
		'ealgo_keylen'.$key,
		null,
		$p1enc['encryption-algorithm']['keylen'],
		array()
	))->setHelp($lastrow ? 'Key length' : '')->setWidth(2);

	$group->add(new Form_Select(
		'halgo'.$key,
		'*Hash Algorithm',
		$p1enc['hash-algorithm'],
		$p1_halgos
	))->setHelp($lastrow ? 'Hash' : '')->setWidth(2);

	$group->add(new Form_Select(
		'dhgroup'.$key,
		'*DH Group',
		$p1enc['dhgroup'],
		$p1_dhgroups
	))->setHelp($lastrow ? 'DH Group' : '')->setWidth(2);

	$group->add(new Form_Button(
		'deleterow' . $counter,
		'Delete',
		null,
		'fa-trash'
	))->addClass('btn-warning')->setWidth(2);

	$group->add(new Form_StaticText(
		null,
		null
	))->setWidth(6);

	$group->add(new Form_Select(
		'prfalgo'.$key,
		'*PRF Algorithm',
		$p1enc['prf-algorithm'],
		$p1_halgos
	))->setHelp($lastrow ? 'PRF' : '')->setWidth(2);

	$section->add($group);
	$counter += 1;
}
$section->addInput(new Form_StaticText('', ''))->setHelp('Note: Blowfish, 3DES, CAST128, MD5, SHA1, and DH groups 1, 2, 5, 22, 23, and 24 provide weak security and should be avoided.');

$btnaddopt = new Form_Button(
	'algoaddrow',
	'Add Algorithm',
	null,
	'fa-plus'
);
$btnaddopt->removeClass('btn-primary')->addClass('btn-success btn-sm');
$section->addInput($btnaddopt);

$form->add($section);

$section = new Form_Section('Expiration and Replacement');

$section->addInput(new Form_Input(
	'rekey_time',
	'Rekey Time',
	'number',
	$pconfig['rekey_time'],
	['min' => 0]
))->setHelp('Time, in seconds, before an IKE SA establishes new keys. This works without interruption. ' .
		'Only supported by IKEv2, and is recommended for use with IKEv2. ' .
		'Leave blank or enter a value of 0 to disable.');

$section->addInput(new Form_Input(
	'reauth_time',
	'Reauth Time',
	'number',
	$pconfig['reauth_time'],
	['min' => 0]
))->setHelp('Time, in seconds, before an IKE SA is torn down and recreated from scratch, including authentication. ' .
		'This can be disruptive unless both sides support make-before-break and overlapping IKE SA entries. ' .
		'Supported by IKEv1 and IKEv2. Leave blank or enter a value of 0 to disable.');

$section->addInput(new Form_Input(
	'over_time',
	'Over Time',
	'number',
	$pconfig['over_time'],
	['min' => 0]
))->setHelp('Hard IKE SA life time, in seconds, after which the IKE SA will be expired. ' .
		'This time is relative to reauthentication and rekey time. ' .
		'If left empty, defaults to 10% of whichever timer is higher (reauth or rekey)');

$form->add($section);

$section = new Form_Section('Advanced Options');

$section->addInput(new Form_Checkbox(
	'responderonly',
	'Responder Only',
	'Enable this option to never initiate this connection from this side, only respond to incoming requests.',
	$pconfig['responderonly']
));

$section->addInput(new Form_Select(
	'closeaction',
	'Child SA Close Action',
	$pconfig['closeaction'],
	$ipsec_closeactions
))->setHelp('Set this option to control the behavior when the remote peer unexpectedly closes a child SA (P2)');

$section->addInput(new Form_Select(
	'nat_traversal',
	'NAT Traversal',
	$pconfig['nat_traversal'],
	array('on' => gettext('Auto'), 'force' => gettext('Force'))
))->setHelp('Set this option to enable the use of NAT-T (i.e. the encapsulation of ESP in UDP packets) if needed, ' .
			'which can help with clients that are behind restrictive firewalls.');

$section->addInput(new Form_Select(
	'mobike',
	'MOBIKE',
	$pconfig['mobike'],
	array('on' => gettext('Enable'), 'off' => gettext('Disable'))
))->setHelp('Set this option to control the use of MOBIKE');

$section->addInput(new Form_Checkbox(
	'gw_duplicates',
	'Gateway duplicates',
	'Enable this to allow multiple phase 1 configurations with the same endpoint. ' .
		'When enabled, pfSense does not manage routing to the remote gateway and traffic will follow the default route ' .
		'without regard for the chosen interface. Static routes can override this behavior.',
	$pconfig['gw_duplicates']
));

$section->addInput(new Form_Checkbox(
	'splitconn',
	'Split connections',
	'Enable this to split connection entries with multiple phase 2 configurations. Required for remote endpoints that support only a single traffic selector per child SA.',
	$pconfig['splitconn']
));

$section->addInput(new Form_Checkbox(
	'prfselect_enable',
	'PRF Selection',
	'Enable manual Pseudo-Random Function (PRF) selection',
	$pconfig['prfselect_enable']
))->setHelp('Manual PRF selection is typically not required, but can be useful in combination with AEAD Encryption Algorithms such as AES-GCM');

/* FreeBSD doesn't yet have TFC support. this is ready to go once it does
https://redmine.pfsense.org/issues/4688

$section->addInput(new Form_Checkbox(
	'tfc_enable',
	'Traffic Flow Confidentiality',
	'Enable TFC',
	$pconfig['tfc_enable']
))->setHelp('Enable Traffic Flow Confidentiality');

$section->addInput(new Form_Input(
	'tfc_bytes',
	'TFC Bytes',
	'Bytes TFC',
	$pconfig['tfc_bytes']
))->setHelp('Enter the number of bytes to pad ESP data to, or leave blank to fill to MTU size');

*/

$section->addInput(new Form_Checkbox(
	'dpd_enable',
	'Dead Peer Detection',
	'Enable DPD',
	$pconfig['dpd_enable']
));

$section->addInput(new Form_Input(
	'dpd_delay',
	'Delay',
	'number',
	$pconfig['dpd_delay']
))->setHelp('Delay between requesting peer acknowledgement.');

$section->addInput(new Form_Input(
	'dpd_maxfail',
	'Max failures',
	'number',
	$pconfig['dpd_maxfail']
))->setHelp('Number of consecutive failures allowed before disconnect. ');

if (isset($p1index) && $a_phase1[$p1index]) {
	$form->addGlobal(new Form_Input(
		'p1index',
		null,
		'hidden',
		$p1index
	));
}

if ($pconfig['mobile']) {
	$form->addGlobal(new Form_Input(
		'mobile',
		null,
		'hidden',
		'true'
	));
}

$form->addGlobal(new Form_Input(
	'ikeid',
	null,
	'hidden',
	$pconfig['ikeid']
));

$form->add($section);

print($form);

?>


<form action="vpn_ipsec_phase1.php" method="post" name="iform" id="iform">

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	$('[id^=algoaddrow]').prop('type','button');

	$('[id^=algoaddrow]').click(function() {
		add_row();

		var lastRepeatableGroup = $('.repeatable:last');
		$(lastRepeatableGroup).find('[id^=ealgo_algo]select').change(function () {
			id = getStringInt(this.id);
			ealgosel_change(id, '');
		});
		$(lastRepeatableGroup).find('[id^=ealgo_algo]select').change();
	});

	function myidsel_change() {
		hideGroupInput('myid_data', ($('#myid_type').val() == 'myaddress'));
	}

	function iketype_change() {

		if ($('#iketype').val() == 'ikev2') {
			hideInput('mode', true);
			hideInput('mobike', false);
			//hideCheckbox('tfc_enable', false);
			hideInput('rekey_time', false);
			hideCheckbox('splitconn', false);
			hideCheckbox('prfselect_enable', false);
		} else {
			hideInput('mode', false);
			hideInput('mobike', true);
			//hideCheckbox('tfc_enable', true);
			//hideInput('tfc_bytes', true);
			hideInput('rekey_time', !($('#iketype').val() == 'auto'));
			hideCheckbox('splitconn', true);
			hideCheckbox('prfselect_enable', true);
		}
	}

	function peeridsel_change() {
		hideGroupInput('peerid_data', ($('#peerid_type').val() == 'peeraddress') || ($('#peerid_type').val() == 'any'));
	}

	function methodsel_change() {

		switch ($('#authentication_method').val()) {
			case 'eap-mschapv2':
			case 'eap-radius':
			case 'hybrid_cert_server':
				hideInput('pskey', true);
				hideClass('peeridgroup', false);
				hideInput('certref', false);
				hideInput('caref', true);
				hideInput('pkcs11certref', true);
				hideInput('pkcs11pin', true);
				disableInput('certref', false);
				disableInput('caref', true);
				disableInput('pkcs11certref', true);
				disableInput('pkcs11pin', true);
				break;
			case 'eap-tls':
			case 'xauth_cert_server':
			case 'cert':
				hideInput('pskey', true);
				hideClass('peeridgroup', false);
				hideInput('certref', false);
				hideInput('caref', false);
				hideInput('pkcs11certref', true);
				hideInput('pkcs11pin', true);
				disableInput('certref', false);
				disableInput('caref', false);
				disableInput('pkcs11certref', true);
				disableInput('pkcs11pin', true);
				break;
			case 'pkcs11':
				hideInput('pskey', true);
				hideClass('peeridgroup', false);
				hideInput('certref', true);
				hideInput('caref', false);
				hideInput('pkcs11certref', false);
				hideInput('pkcs11pin', false);
				disableInput('certref', true);
				disableInput('caref', false);
				disableInput('pkcs11certref', false);
				disableInput('pkcs11pin', false);
				break;

<?php if ($pconfig['mobile']) { ?>
				case 'pre_shared_key':
					hideInput('pskey', true);
					hideClass('peeridgroup', true);
					hideInput('certref', true);
					hideInput('caref', true);
					hideInput('pkcs11certref', true);
					hideInput('pkcs11pin', true);
					disableInput('certref', true);
					disableInput('caref', true);
					disableInput('pkcs11certref', true);
					disableInput('pkcs11pin', true);
					break;
<?php } ?>
			default: /* psk modes*/
				hideInput('pskey', false);
				hideClass('peeridgroup', false);
				hideInput('certref', true);
				hideInput('caref', true);
				hideInput('pkcs11certref', true);
				hideInput('pkcs11pin', true);
				disableInput('certref', true);
				disableInput('caref', true);
				disableInput('pkcs11certref', true);
				disableInput('pkcs11pin', true);
				break;
		}
	}

	/* PHP generates javascript case statements for variable length keys */
	function ealgosel_change(id, bits) {

		$("select[name='ealgo_keylen"+id+"']").find('option').remove().end();

		switch ($('#ealgo_algo'+id).find(":selected").index().toString()) {
<?php
	$i = 0;
	foreach ($p1_ealgos as $algo => $algodata) {
		if (is_array($algodata['keysel'])) {
?>
			case '<?=$i?>':
				invisibleGroupInput('ealgo_keylen'+id, false);
<?php
			$key_hi = $algodata['keysel']['hi'];
			$key_lo = $algodata['keysel']['lo'];
			$key_step = $algodata['keysel']['step'];

			for ($keylen = $key_hi; $keylen >= $key_lo; $keylen -= $key_step) {
?>
				$("select[name='ealgo_keylen"+id+"']").append($('<option value="<?=$keylen?>"><?=$keylen?> bits</option>'));
<?php
			}
?>
			break;
<?php
		} else {
?>
			case '<?=$i?>':
				invisibleGroupInput('ealgo_keylen'+id, true);
			break;
<?php
		}
		$i++;
	}
?>
		}

		if (bits) {
			$('#ealgo_keylen'+id).val(bits);
		}
	}

	function prfselectchkbox_change() {
		hide = !$('#prfselect_enable').prop('checked');
		var i;
		for (i = 0; i < 50; i++) {
			hideGroupInput('prfalgo' + i , hide);
		}
	}

	function dpdchkbox_change() {
		hide = !$('#dpd_enable').prop('checked');

		hideInput('dpd_delay', hide);
		hideInput('dpd_maxfail', hide);

		if (!$('#dpd_delay').val()) {
			$('#dpd_delay').val('10')
		}

		if (!$('#dpd_maxfail').val()) {
			$('#dpd_maxfail').val('5')
		}
	}

	//function tfcchkbox_change() {
	//	hide = !$('#tfc_enable').prop('checked');
	//
	//	hideInput('tfc_bytes', hide);
	//}

	// ---------- Monitor elements for change and call the appropriate display functions ----------

	 // Enable PRF
	$('#prfselect_enable').click(function () {
		prfselectchkbox_change();
	});

	 // Enable DPD
	$('#dpd_enable').click(function () {
		dpdchkbox_change();
	});

	 // TFC
	//$('#tfc_enable').click(function () {
	//	tfcchkbox_change();
	//});

	 // Peer identifier
	$('#peerid_type').change(function () {
		peeridsel_change();
	});

	 // My identifier
	$('#myid_type').change(function () {
		myidsel_change();
	});

	 // ike type
	$('#iketype').change(function () {
		iketype_change();
	});

	 // authentication method
	$('#authentication_method').change(function () {
		methodsel_change();
	});

	 // algorithm
	$('[id^=ealgo_algo]select').change(function () {
		id = getStringInt(this.id);
		ealgosel_change(id, 0);
	});

	// On initial page load
	myidsel_change();
	peeridsel_change();
	iketype_change();
	methodsel_change();
	dpdchkbox_change();
	prfselectchkbox_change();
<?php
foreach($pconfig['encryption']['item'] as $key => $p1enc) {
	$keylen = $p1enc['encryption-algorithm']['keylen'];
	if (!is_numericint($keylen)) {
		$keylen = "''";
	}
	echo "ealgosel_change({$key}, {$keylen});";
}
?>

	// ---------- On initial page load ------------------------------------------------------------

	var generateButton = $('<a class="btn btn-xs btn-warning"><i class="fa fa-refresh icon-embed-btn"></i><?=gettext("Generate new Pre-Shared Key");?></a>');
	generateButton.on('click', function() {
		$.ajax({
			type: 'get',
			url: 'vpn_ipsec_phase1.php?generatekey=true',
			dataType: 'json',
			success: function(data) {
				$('#pskey').val(data.pskey.replace(/\\n/g, '\n'));
			}
		});
	});
	generateButton.appendTo($('#pskey + .help-block')[0]);
});
//]]>
</script>
</form>
<?php

include("foot.inc");
