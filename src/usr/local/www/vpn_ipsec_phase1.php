<?php
/*
 * vpn_ipsec_phase1.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
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
$a_phase1 = &$config['ipsec']['phase1'];
$a_phase2 = &$config['ipsec']['phase2'];

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
	$pconfig['lifetime'] = $a_phase1[$p1index]['lifetime'];
	$pconfig['authentication_method'] = $a_phase1[$p1index]['authentication_method'];

	if (($pconfig['authentication_method'] == "pre_shared_key") ||
	    ($pconfig['authentication_method'] == "xauth_psk_server")) {
		$pconfig['pskey'] = $a_phase1[$p1index]['pre-shared-key'];
	} else {
		$pconfig['certref'] = $a_phase1[$p1index]['certref'];
		$pconfig['caref'] = $a_phase1[$p1index]['caref'];
	}

	$pconfig['descr'] = $a_phase1[$p1index]['descr'];
	$pconfig['nat_traversal'] = $a_phase1[$p1index]['nat_traversal'];
	$pconfig['mobike'] = $a_phase1[$p1index]['mobike'];
	$pconfig['closeaction'] = $a_phase1[$p1index]['closeaction'];

	if (isset($a_phase1[$p1index]['reauth_enable'])) {
		$pconfig['reauth_enable'] = true;
	}

	if (isset($a_phase1[$p1index]['rekey_enable'])) {
		$pconfig['rekey_enable'] = true;
	}

	if ($a_phase1[$p1index]['margintime']) {
		$pconfig['margintime'] = $a_phase1[$p1index]['margintime'];
	}

	if (isset($a_phase1[$p1index]['responderonly'])) {
		$pconfig['responderonly'] = true;
	}

	if ($a_phase1[$p1index]['dpd_delay'] && $a_phase1[$p1index]['dpd_maxfail']) {
		$pconfig['dpd_enable'] = true;
		$pconfig['dpd_delay'] = $a_phase1[$p1index]['dpd_delay'];
		$pconfig['dpd_maxfail'] = $a_phase1[$p1index]['dpd_maxfail'];
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
	$pconfig['lifetime'] = "28800";
	$pconfig['nat_traversal'] = 'on';
	$pconfig['mobike'] = 'off';
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
			$item['dhgroup'] = $_POST['dhgroup'.$i];
			$pconfig['encryption']['item'][] = $item;
		}
	}

	/* input validation */

	$method = $pconfig['authentication_method'];

	// Unset ca and cert if not required to avoid storing in config
	if ($method == "pre_shared_key" || $method == "xauth_psk_server") {
		unset($pconfig['certref']);
	}

	if ($method != "cert" && $method != "xauth_cert_server" && $method != "eap-tls") {
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

	if (($pconfig['lifetime'] && !is_numericint($pconfig['lifetime']))) {
		$input_errors[] = gettext("The P1 lifetime must be an integer.");
	}

	if (!empty($pconfig['closeaction']) && !array_key_exists($pconfig['closeaction'], $ipsec_closeactions)) {
		$input_errors[] = gettext("Invalid Child SA Close Action.");
	}

	if (!isset($pconfig['rekey_enable']) && $pconfig['margintime']) {
		if(!is_numericint($pconfig['margintime'])){
			 $input_errors[] = gettext("The margintime must be an integer.");
		} else if(intval($pconfig['margintime']) >= intval($pconfig['lifetime'])){
			 $input_errors[] = gettext("The margintime must be smaller than the P1 lifetime.");
		}
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

	if ($pconfig['remotegw'] && is_ipaddr($pconfig['remotegw']) && !isset($pconfig['disabled'])) {
		$t = 0;
		foreach ($a_phase1 as $ph1tmp) {
			if ($p1index != $t) {
				$tremotegw = $pconfig['remotegw'];
				if (($ph1tmp['remote-gateway'] == $tremotegw) && !isset($ph1tmp['disabled'])) {
					$input_errors[] = sprintf(gettext('The remote gateway "%1$s" is already used by phase1 "%2$s".'), $tremotegw, $ph1tmp['descr']);
				}
			}
			$t++;
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
	if (is_array($old_ph1ent) && ipsec_vti($old_ph1ent) && $pconfig['disabled']) {
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
		$ph1ent['disabled'] = $pconfig['disabled'] ? true : false;
		$ph1ent['interface'] = $pconfig['interface'];
		/* if the remote gateway changed and the interface is not WAN then remove route */
		/* the ipsec_configure() handles adding the route */
		if ($pconfig['interface'] <> "wan") {
			if ($old_ph1ent['remote-gateway'] <> $pconfig['remotegw']) {
				mwexec("/sbin/route delete -host {$old_ph1ent['remote-gateway']}");
			}
		}

		if ($pconfig['mobile']) {
			$ph1ent['mobile'] = true;
		} else {
			$ph1ent['remote-gateway'] = $pconfig['remotegw'];
		}

		$ph1ent['protocol'] = $pconfig['protocol'];

		$ph1ent['myid_type'] = $pconfig['myid_type'];
		$ph1ent['myid_data'] = $pconfig['myid_data'];
		$ph1ent['peerid_type'] = $pconfig['peerid_type'];
		$ph1ent['peerid_data'] = $pconfig['peerid_data'];

		$ph1ent['encryption'] = $pconfig['encryption'];
		$ph1ent['lifetime'] = $pconfig['lifetime'];
		$ph1ent['pre-shared-key'] = $pconfig['pskey'];
		$ph1ent['private-key'] = base64_encode($pconfig['privatekey']);
		$ph1ent['certref'] = $pconfig['certref'];
		$ph1ent['caref'] = $pconfig['caref'];
		$ph1ent['authentication_method'] = $pconfig['authentication_method'];
		$ph1ent['descr'] = $pconfig['descr'];
		$ph1ent['nat_traversal'] = $pconfig['nat_traversal'];
		$ph1ent['mobike'] = $pconfig['mobike'];
		$ph1ent['closeaction'] = $pconfig['closeaction'];

		if (isset($pconfig['reauth_enable'])) {
			$ph1ent['reauth_enable'] = true;
		} else {
			unset($ph1ent['reauth_enable']);
		}

		if (isset($pconfig['rekey_enable'])) {
			$ph1ent['rekey_enable'] = true;
		} else {
			unset($ph1ent['rekey_enable']);
		}

		if (!isset($pconfig['rekey_enable'])) {
			$ph1ent['margintime'] = $pconfig['margintime'];
		} else {
			unset($ph1ent['margintime']);
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
	$section->addInput(new Form_Input(
		'remotegw',
		'*Remote Gateway',
		'text',
		$pconfig['remotegw']
	))->setHelp('Enter the public IP address or host name of the remote gateway.');
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
	))->setHelp($lastrow ? 'Algorithm' : '');

	$group->add(new Form_Select(
		'ealgo_keylen'.$key,
		null,
		$p1enc['encryption-algorithm']['keylen'],
		array()
	))->setHelp($lastrow ? 'Key length' : '');

	$group->add(new Form_Select(
		'halgo'.$key,
		'*Hash Algorithm',
		$p1enc['hash-algorithm'],
		$p1_halgos
	))->setHelp($lastrow ? 'Hash' : '');

	$group->add(new Form_Select(
		'dhgroup'.$key,
		'*DH Group',
		$p1enc['dhgroup'],
		$p1_dhgroups
	))->setHelp($lastrow ? 'DH Group' : '');

	$group->add(new Form_Button(
		'deleterow' . $counter,
		'Delete',
		null,
		'fa-trash'
	))->addClass('btn-warning');

	$section->add($group);
	$counter += 1;
}
$section->addInput(new Form_StaticText('', ''))->setHelp('Note: Blowfish, 3DES, CAST128, MD5, SHA1, and DH groups 1, 2, 22, 23, and 24 provide weak security and should be avoided.');
$form->add($section);

$btnaddopt = new Form_Button(
	'algoaddrow',
	'Add Algorithm',
	null,
	'fa-plus'
);
$btnaddopt->removeClass('btn-primary')->addClass('btn-success btn-sm');
$section->addInput($btnaddopt);

$section = new Form_Section('NOTITLE');
$section->addInput(new Form_Input(
	'lifetime',
	'*Lifetime (Seconds)',
	'number',
	$pconfig['lifetime']
));

$form->add($section);

$section = new Form_Section('Advanced Options');

$section->addInput(new Form_Checkbox(
	'rekey_enable',
	'Disable rekey',
	'Disables renegotiation when a connection is about to expire.',
	$pconfig['rekey_enable']
));

$section->addInput(new Form_Input(
	'margintime',
	'Margintime (Seconds)',
	'number',
	$pconfig['margintime']
))->setHelp('How long before connection expiry or keying-channel expiry should attempt to negotiate a replacement begin.');

$section->addInput(new Form_Checkbox(
	'reauth_enable',
	'Disable Reauth',
	'Whether rekeying of an IKE_SA should also reauthenticate the peer. In IKEv1, reauthentication is always done.',
	$pconfig['reauth_enable']
));

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
	'splitconn',
	'Split connections',
	'Enable this to split connection entries with multiple phase 2 configurations. Required for remote endpoints that support only a single traffic selector per child SA.',
	$pconfig['splitconn']
));

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
			hideCheckbox('reauth_enable', false);
			hideCheckbox('splitconn', false);
		} else {
			hideInput('mode', false);
			hideInput('mobike', true);
			//hideCheckbox('tfc_enable', true);
			//hideInput('tfc_bytes', true);
			hideCheckbox('reauth_enable', true);
			hideCheckbox('splitconn', true);
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
				disableInput('certref', false);
				disableInput('caref', true);
				break;
			case 'eap-tls':
			case 'xauth_cert_server':
			case 'cert':
				hideInput('pskey', true);
				hideClass('peeridgroup', false);
				hideInput('certref', false);
				hideInput('caref', false);
				disableInput('certref', false);
				disableInput('caref', false);
				break;

<?php if ($pconfig['mobile']) { ?>
				case 'pre_shared_key':
					hideInput('pskey', true);
					hideClass('peeridgroup', true);
					hideInput('certref', true);
					hideInput('caref', true);
					disableInput('certref', true);
					disableInput('caref', true);
					break;
<?php } ?>
			default: /* psk modes*/
				hideInput('pskey', false);
				hideClass('peeridgroup', false);
				hideInput('certref', true);
				hideInput('caref', true);
				disableInput('certref', true);
				disableInput('caref', true);
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

	function rekeychkbox_change() {
		hide = $('#rekey_enable').prop('checked');

		hideInput('margintime', hide);
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

	 // Enable Rekey
	$('#rekey_enable').click(function () {
		rekeychkbox_change();
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
	rekeychkbox_change();
	dpdchkbox_change();
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
