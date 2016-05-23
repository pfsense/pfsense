<?php
/*
	vpn_ipsec_phase1.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2008 Shrew Soft Inc
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-vpn-ipsec-editphase1
##|*NAME=VPN: IPsec: Edit Phase 1
##|*DESCR=Allow access to the 'VPN: IPsec: Edit Phase 1' page.
##|*MATCH=vpn_ipsec_phase1.php*
##|-PRIV

require("functions.inc");
require("guiconfig.inc");
require_once("ipsec.inc");
require_once("vpn.inc");
require_once("filter.inc");

if (!is_array($config['ipsec']['phase1'])) {
	$config['ipsec']['phase1'] = array();
}

if (!is_array($config['ipsec']['phase2'])) {
	$config['ipsec']['phase2'] = array();
}

$a_phase1 = &$config['ipsec']['phase1'];
$a_phase2 = &$config['ipsec']['phase2'];

if (is_numericint($_GET['p1index'])) {
	$p1index = $_GET['p1index'];
}
if (isset($_POST['p1index']) && is_numericint($_POST['p1index'])) {
	$p1index = $_POST['p1index'];
}

if (isset($_GET['dup']) && is_numericint($_GET['dup'])) {
	$p1index = $_GET['dup'];
}

if (isset($p1index) && $a_phase1[$p1index]) {
	// don't copy the ikeid on dup
	if (!isset($_GET['dup']) || !is_numericint($_GET['dup'])) {
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
	$pconfig['ealgo'] = $a_phase1[$p1index]['encryption-algorithm'];
	$pconfig['halgo'] = $a_phase1[$p1index]['hash-algorithm'];
	$pconfig['dhgroup'] = $a_phase1[$p1index]['dhgroup'];
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

	if (isset($a_phase1[$p1index]['reauth_enable'])) {
		$pconfig['reauth_enable'] = true;
	}
	if (isset($a_phase1[$p1index]['rekey_enable'])) {
		$pconfig['rekey_enable'] = true;
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
	$pconfig['ealgo'] = array(name => "aes");
	$pconfig['halgo'] = "sha1";
	$pconfig['dhgroup'] = "2";
	$pconfig['lifetime'] = "28800";
	$pconfig['nat_traversal'] = 'on';
	$pconfig['mobike'] = 'off';
	$pconfig['dpd_enable'] = true;
	$pconfig['iketype'] = "ikev1";

	/* mobile client */
	if ($_GET['mobile']) {
		$pconfig['mobile'] = true;
		$pconfig['mode'] = "aggressive";
	}
}

if (isset($_GET['dup']) && is_numericint($_GET['dup'])) {
	unset($p1index);
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */

	$method = $pconfig['authentication_method'];
	// Unset ca and cert if not required to avoid storing in config
	if ($method == "pre_shared_key" || $method == "xauth_psk_server") {
		unset($pconfig['certref']);
	}
	if ($method != "rsasig" && $method != "xauth_rsa_server" && $method != "eap-tls") {
		unset($pconfig['caref']);
	}
	// Only require PSK here for normal PSK tunnels (not mobile) or xauth.
	// For RSA methods, require the CA/Cert.
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
		case "hybrid_rsa_server":
		case "xauth_rsa_server":
		case "rsasig":
			$reqdfields = explode(" ", "caref certref");
			$reqdfieldsn = array(gettext("Certificate Authority"), gettext("Certificate"));
			break;
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

	if ($pconfig['remotegw']) {
		if (!is_ipaddr($pconfig['remotegw']) && !is_domain($pconfig['remotegw'])) {
			$input_errors[] = gettext("A valid remote gateway address or host name must be specified.");
		} elseif (is_ipaddrv4($pconfig['remotegw']) && ($pconfig['protocol'] != "inet")) {
			$input_errors[] = gettext("A valid remote gateway IPv4 address must be specified or protocol needs to be changed to IPv6");
		} elseif (is_ipaddrv6($pconfig['remotegw']) && ($pconfig['protocol'] != "inet6")) {
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

	if (!empty($_POST['ealgo']) && isset($config['system']['crypto_hardware'])) {
		if ($config['system']['crypto_hardware'] == "glxsb") {
			if ($_POST['ealgo'] == "aes" && $_POST['ealgo_keylen'] != "128") {
				$input_errors[] = gettext("Only 128 bit AES can be used where the glxsb crypto accelerator is enabled.");
			}
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

	/* build our encryption algorithms array */
	$pconfig['ealgo'] = array();
	$pconfig['ealgo']['name'] = $_POST['ealgo'];
	if ($pconfig['ealgo_keylen']) {
		$pconfig['ealgo']['keylen'] = $_POST['ealgo_keylen'];
	}

	if (!$input_errors) {
		$ph1ent['ikeid'] = $pconfig['ikeid'];
		$ph1ent['iketype'] = $pconfig['iketype'];
		if ($pconfig['iketype'] != 'ikev1') {
			unset($ph1ent['mode']);
		} else {
			$ph1ent['mode'] = $pconfig['mode'];
		}
		$ph1ent['disabled'] = $pconfig['disabled'] ? true : false;
		$ph1ent['interface'] = $pconfig['interface'];
		/* if the remote gateway changed and the interface is not WAN then remove route */
		/* the vpn_ipsec_configure() handles adding the route */
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

		$ph1ent['encryption-algorithm'] = $pconfig['ealgo'];
		$ph1ent['hash-algorithm'] = $pconfig['halgo'];
		$ph1ent['dhgroup'] = $pconfig['dhgroup'];
		$ph1ent['lifetime'] = $pconfig['lifetime'];
		$ph1ent['pre-shared-key'] = $pconfig['pskey'];
		$ph1ent['private-key'] = base64_encode($pconfig['privatekey']);
		$ph1ent['certref'] = $pconfig['certref'];
		$ph1ent['caref'] = $pconfig['caref'];
		$ph1ent['authentication_method'] = $pconfig['authentication_method'];
		$ph1ent['descr'] = $pconfig['descr'];
		$ph1ent['nat_traversal'] = $pconfig['nat_traversal'];
		$ph1ent['mobike'] = $pconfig['mobike'];

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

		write_config();
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

function build_cert_list() {
	global $config;

	$list = array();

	if (is_array($config['cert'])) {
		foreach ($config['cert'] as $cert) {
			$list[$cert['refid']] = $cert['descr'];
		}
	}

	return($list);
}

function build_ca_list() {
	global $config;

	$list = array();

	if (is_array($config['ca'])) {
		foreach ($config['ca'] as $ca) {
			$list[$ca['refid']] = $ca['descr'];
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
} else {
	$pgtitle = array(gettext("VPN"), gettext("IPsec"), gettext("Tunnels"), gettext("Edit Phase 1"));
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
	'Key Exchange version',
	$pconfig['iketype'],
	array("ikev1" => "V1", "ikev2" => "V2", "auto" => gettext("Auto"))
))->setHelp('Select the Internet Key Exchange protocol version to be used. Auto uses IKEv2 when initiator, and accepts either IKEv1 or IKEv2 as responder.');

$section->addInput(new Form_Select(
	'protocol',
	'Internet Protocol',
	$pconfig['protocol'],
	array("inet" => "IPv4", "inet6" => "IPv6")
))->setHelp('Select the Internet Protocol family.');

$section->addInput(new Form_Select(
	'interface',
	'Interface',
	$pconfig['interface'],
	build_interface_list()
))->setHelp('Select the interface for the local endpoint of this phase1 entry.');

if (!$pconfig['mobile']) {
	$section->addInput(new Form_Input(
		'remotegw',
		'Remote Gateway',
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
	'Authentication Method',
	$pconfig['authentication_method'],
	build_auth_method_list()
))->setHelp('Must match the setting chosen on the remote side.');

$section->addInput(new Form_Select(
	'mode',
	'Negotiation mode',
	$pconfig['mode'],
	array("main" => gettext("Main"), "aggressive" => gettext("Aggressive"))
))->setHelp('Aggressive is more flexible, but less secure.');

$group = new Form_Group('My identifier');

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

$group = new Form_Group('Peer identifier');
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
	'Pre-Shared Key',
	'text',
	$pconfig['pskey']
))->setHelp('Enter the Pre-Shared Key string.');

$section->addInput(new Form_Select(
	'certref',
	'My Certificate',
	$pconfig['certref'],
	build_cert_list()
))->setHelp('Select a certificate previously configured in the Certificate Manager.');

$section->addInput(new Form_Select(
	'caref',
	'Peer Certificate Authority',
	$pconfig['caref'],
	build_ca_list()
))->setHelp('Select a certificate authority previously configured in the Certificate Manager.');

$form->add($section);

$section = new Form_Section('Phase 1 Proposal (Algorithms)');

$group = new Form_Group('Encryption Algorithm');

$group->add(new Form_Select(
	'ealgo',
	null,
	$pconfig['ealgo']['name'],
	build_eal_list()
));

$group->add(new Form_Select(
	'ealgo_keylen',
	null,
	$pconfig['ealgo_keylen'],
	array()
));

$section->add($group);

$section->addInput(new Form_Select(
	'halgo',
	'Hash Algorithm',
	$pconfig['halgo'],
	$p1_halgos
))->setHelp('Must match the setting chosen on the remote side.');

$section->addInput(new Form_Select(
	'dhgroup',
	'DH Group',
	$pconfig['dhgroup'],
	$p1_dhgroups
))->setHelp('Must match the setting chosen on the remote side.');

$section->addInput(new Form_Input(
	'lifetime',
	'Lifetime (Seconds)',
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
	$section->addInput(new Form_Input(
		'p1index',
		null,
		'hidden',
		$pconfig['$p1index']
	));
}

if ($pconfig['mobile']) {
	$section->addInput(new Form_Input(
		'mobile',
		null,
		'hidden',
		'true'
	));
}

$section->addInput(new Form_Input(
	'ikeid',
	null,
	'hidden',
	$pconfig['ikeid']
));

$form->add($section);

print($form);

/* determine if we should init the key length */
$keyset = '';
if (isset($pconfig['ealgo']['keylen'])) {
	if (is_numericint($pconfig['ealgo']['keylen'])) {
		$keyset = $pconfig['ealgo']['keylen'];
	}
}
?>


<form action="vpn_ipsec_phase1.php" method="post" name="iform" id="iform">

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	function myidsel_change() {
		hideGroupInput('myid_data', ($('#myid_type').val() == 'myaddress'));
	}

	function iketype_change() {

		if ($('#iketype').val() == 'ikev2') {
			hideInput('mode', true);
			hideInput('mobike', false);
			hideInput('nat_traversal', true);
			//hideCheckbox('tfc_enable', false);
			hideCheckbox('reauth_enable', false);
			hideCheckbox('splitconn', false);
		} else {
			hideInput('mode', false);
			hideInput('mobike', true);
			hideInput('nat_traversal', false);
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
			case 'hybrid_rsa_server':
				hideInput('pskey', true);
				hideClass('peeridgroup', false);
				hideInput('certref', false);
				hideInput('caref', true);
				disableInput('certref', false);
				disableInput('caref', true);
				break;
			case 'eap-tls':
			case 'xauth_rsa_server':
			case 'rsasig':
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
	function ealgosel_change(bits) {

		$("select[name='ealgo_keylen']").find('option').remove().end();

		switch ($('#ealgo').find(":selected").index().toString()) {
<?php
	$i = 0;
	foreach ($p1_ealgos as $algo => $algodata) {
		if (is_array($algodata['keysel'])) {
?>
			case '<?=$i?>':
				hideGroupInput('ealgo_keylen', false);
<?php
			$key_hi = $algodata['keysel']['hi'];
			$key_lo = $algodata['keysel']['lo'];
			$key_step = $algodata['keysel']['step'];

			for ($keylen = $key_hi; $keylen >= $key_lo; $keylen -= $key_step) {
?>
				$("select[name='ealgo_keylen']").append($('<option value="<?=$keylen?>"><?=$keylen?> bits</option>'));
<?php
			}
?>
			break;
<?php
		} else {
?>
			case '<?=$i?>':
				hideGroupInput('ealgo_keylen', true);
			break;
<?php
		}
		$i++;
	}
?>
		}

		if (bits) {
			$('#ealgo_keylen').val(bits);
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

	 // Enable DPD
	$('#dpd_enable').click(function () {
		dpdchkbox_change();
	});

	 // TFC
	//$('#tfc_enable').click(function () {
	//	tfcchkbox_change();
	//});

	 // Peer identifier
	$('#peerid_type').click(function () {
		peeridsel_change();
	});

	 // My identifier
	$('#myid_type').click(function () {
		myidsel_change();
	});

	 // ike type
	$('#iketype').click(function () {
		iketype_change();
	});

	 // authentication method
	$('#authentication_method').click(function () {
		methodsel_change();
	});

	 // authentication method
	$('#ealgo').click(function () {
		ealgosel_change(<?=$keyset?>);
	});

	// On ititial page load
	myidsel_change();
	peeridsel_change();
	iketype_change();
	methodsel_change();
	ealgosel_change(<?=$keyset?>);
	dpdchkbox_change();

	// ---------- On initial page load ------------------------------------------------------------

	hideInput('ikeid', true);
});
//]]>
</script>
</form>
<?php

include("foot.inc");
