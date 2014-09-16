<?php
/*
	vpn_ipsec_phase1.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2008 Shrew Soft Inc
	Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
	Copyright (C) 2014 Ermal LUÇI
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

##|+PRIV
##|*IDENT=page-vpn-ipsec-editphase1
##|*NAME=VPN: IPsec: Edit Phase 1 page
##|*DESCR=Allow access to the 'VPN: IPsec: Edit Phase 1' page.
##|*MATCH=vpn_ipsec_phase1.php*
##|-PRIV

require("functions.inc");
require("guiconfig.inc");
require_once("ipsec.inc");
require_once("vpn.inc");

if (!is_array($config['ipsec']['phase1']))
	$config['ipsec']['phase1'] = array();

if (!is_array($config['ipsec']['phase2']))
	$config['ipsec']['phase2'] = array();

$a_phase1 = &$config['ipsec']['phase1'];
$a_phase2 = &$config['ipsec']['phase2'];

if (is_numericint($_GET['p1index']))
	$p1index = $_GET['p1index'];
if (isset($_POST['p1index']) && is_numericint($_POST['p1index']))
	$p1index = $_POST['p1index'];

if (isset($_GET['dup']) && is_numericint($_GET['dup']))
	$p1index = $_GET['dup'];

if (isset($p1index) && $a_phase1[$p1index]) {
	// don't copy the ikeid on dup
	if (!isset($_GET['dup']) || !is_numericint($_GET['dup']))
		$pconfig['ikeid'] = $a_phase1[$p1index]['ikeid'];

	$old_ph1ent = $a_phase1[$p1index];

	$pconfig['disabled'] = isset($a_phase1[$p1index]['disabled']);

	if ($a_phase1[$p1index]['interface'])
		$pconfig['interface'] = $a_phase1[$p1index]['interface'];
	else
		$pconfig['interface'] = "wan";

	list($pconfig['remotenet'],$pconfig['remotebits']) = explode("/", $a_phase1[$p1index]['remote-subnet']);

	if (isset($a_phase1[$p1index]['mobile']))
		$pconfig['mobile'] = 'true';
	else
		$pconfig['remotegw'] = $a_phase1[$p1index]['remote-gateway'];

	if (empty($a_phase1[$p1index]['iketype']))
		$pconfig['iketype'] = "ikev1";
	else
		$pconfig['iketype'] = $a_phase1[$p1index]['iketype'];
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

	if (!isset($a_phase1[$p1index]['reauth_enable']))
		$pconfig['reauth_enable'] = true;
	if (!isset($a_phase1[$p1index]['rekey_enable']))
		$pconfig['rekey_enable'] = true;

	if ($a_phase1[$p1index]['dpd_delay'] &&	$a_phase1[$p1index]['dpd_maxfail']) {
		$pconfig['dpd_enable'] = true;
		$pconfig['dpd_delay'] = $a_phase1[$p1index]['dpd_delay'];
		$pconfig['dpd_maxfail'] = $a_phase1[$p1index]['dpd_maxfail'];
	}
} else {
	/* defaults */
	$pconfig['interface'] = "wan";
	if($config['interfaces']['lan'])
		$pconfig['localnet'] = "lan";
	$pconfig['mode'] = "aggressive";
	$pconfig['protocol'] = "inet";
	$pconfig['myid_type'] = "myaddress";
	$pconfig['peerid_type'] = "peeraddress";
	$pconfig['authentication_method'] = "pre_shared_key";
	$pconfig['ealgo'] = array( name => "3des" );
	$pconfig['halgo'] = "sha1";
	$pconfig['dhgroup'] = "2";
	$pconfig['lifetime'] = "28800";
	$pconfig['nat_traversal'] = "on";
	$pconfig['dpd_enable'] = true;
	$pconfig['iketype'] = "ikev1";

	/* mobile client */
	if($_GET['mobile'])
		$pconfig['mobile']=true;
}

if (isset($_GET['dup']) && is_numericint($_GET['dup']))
	unset($p1index);

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */

	$method = $pconfig['authentication_method'];
	// Unset ca and cert if not required to avaoid storing in config
	if ($method == "pre_shared_key" || $method == "xauth_psk_server"){
		unset($pconfig['caref']);
		unset($pconfig['certref']);
	}

	// Only require PSK here for normal PSK tunnels (not mobile) or xauth.
	// For RSA methods, require the CA/Cert.
	switch ($method) {
		case "eap-tls":
			if ($pconfig['iketype'] != 'ikev2')
				$input_errors[] = gettext("EAP-TLS can only be used with IKEv2 type VPNs.");
			break;
		case "pre_shared_key":
			// If this is a mobile PSK tunnel the user PSKs go on
			//    the PSK tab, not here, so skip the check.
			if ($pconfig['mobile'])
				break;
		case "xauth_psk_server":
			$reqdfields = explode(" ", "pskey");
			$reqdfieldsn = array(gettext("Pre-Shared Key"));
			break;
		case "hybrid_rsa_server":
		case "xauth_rsa_server":
		case "rsasig":
			$reqdfields = explode(" ", "caref certref");
			$reqdfieldsn = array(gettext("Certificate Authority"),gettext("Certificate"));
			break;
	}
	if (!$pconfig['mobile']) {
		$reqdfields[] = "remotegw";
		$reqdfieldsn[] = gettext("Remote gateway");
	}

	do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

	if (($pconfig['lifetime'] && !is_numeric($pconfig['lifetime'])))
		$input_errors[] = gettext("The P1 lifetime must be an integer.");

	if ($pconfig['remotegw']) {
		if (!is_ipaddr($pconfig['remotegw']) && !is_domain($pconfig['remotegw']))
			$input_errors[] = gettext("A valid remote gateway address or host name must be specified.");
		elseif (is_ipaddrv4($pconfig['remotegw']) && ($pconfig['protocol'] != "inet"))
			$input_errors[] = gettext("A valid remote gateway IPv4 address must be specified or you need to change protocol to IPv6");
		elseif (is_ipaddrv6($pconfig['remotegw']) && ($pconfig['protocol'] != "inet6"))
			$input_errors[] = gettext("A valid remote gateway IPv6 address must be specified or you need to change protocol to IPv4");
	}

	if (($pconfig['remotegw'] && is_ipaddr($pconfig['remotegw']) && !isset($pconfig['disabled']) )) {
		$t = 0;
		foreach ($a_phase1 as $ph1tmp) {
			if ($p1index <> $t) {
				$tremotegw = $pconfig['remotegw'];
				if (($ph1tmp['remote-gateway'] == $tremotegw) && !isset($ph1tmp['disabled'])) {
					$input_errors[] = sprintf(gettext('The remote gateway "%1$s" is already used by phase1 "%2$s".'), $tremotegw, $ph1tmp['descr']);
				}
			}
			$t++;
		}
	}

	if (is_array($a_phase2) && (count($a_phase2))) {
		foreach ($a_phase2 as $phase2) {
			if($phase2['ikeid'] == $pconfig['ikeid']) {
				if (($pconfig['protocol'] == "inet") && ($phase2['mode'] == "tunnel6")) {
					$input_errors[] = gettext("There is a Phase 2 using IPv6, you cannot use IPv4.");
					break;
				}
				if (($pconfig['protocol'] == "inet6") && ($phase2['mode'] == "tunnel")) {
					$input_errors[] = gettext("There is a Phase 2 using IPv4, you cannot use IPv6.");
					break;
				}
			}
		}
	}

	/* My identity */

	if ($pconfig['myid_type'] == "myaddress")
		$pconfig['myid_data'] = "";

	if ($pconfig['myid_type'] == "address" and $pconfig['myid_data'] == "")
		$input_errors[] = gettext("Please enter an address for 'My Identifier'");

	if ($pconfig['myid_type'] == "keyid tag" and $pconfig['myid_data'] == "")
		$input_errors[] = gettext("Please enter a keyid tag for 'My Identifier'");

	if ($pconfig['myid_type'] == "fqdn" and $pconfig['myid_data'] == "")
		$input_errors[] = gettext("Please enter a fully qualified domain name for 'My Identifier'");

	if ($pconfig['myid_type'] == "user_fqdn" and $pconfig['myid_data'] == "")
		$input_errors[] = gettext("Please enter a user and fully qualified domain name for 'My Identifier'");

	if ($pconfig['myid_type'] == "dyn_dns" and $pconfig['myid_data'] == "")
		$input_errors[] = gettext("Please enter a dynamic domain name for 'My Identifier'");

	if ((($pconfig['myid_type'] == "address") && !is_ipaddr($pconfig['myid_data'])))
		$input_errors[] = gettext("A valid IP address for 'My identifier' must be specified.");

	if ((($pconfig['myid_type'] == "fqdn") && !is_domain($pconfig['myid_data'])))
		$input_errors[] = gettext("A valid domain name for 'My identifier' must be specified.");

	if ($pconfig['myid_type'] == "fqdn")
		if (is_domain($pconfig['myid_data']) == false)
			$input_errors[] = gettext("A valid FQDN for 'My identifier' must be specified.");

	if ($pconfig['myid_type'] == "user_fqdn") {
		$user_fqdn = explode("@",$pconfig['myid_data']);
		if (is_domain($user_fqdn[1]) == false)
			$input_errors[] = gettext("A valid User FQDN in the form of user@my.domain.com for 'My identifier' must be specified.");
	}

	if ($pconfig['myid_type'] == "dyn_dns")
		if (is_domain($pconfig['myid_data']) == false)
			$input_errors[] = gettext("A valid Dynamic DNS address for 'My identifier' must be specified.");

	/* Peer identity */

	if ($pconfig['myid_type'] == "peeraddress")
		$pconfig['peerid_data'] = "";

	// Only enforce peer ID if we are not dealing with a pure-psk mobile config.
	if (!(($pconfig['authentication_method'] == "pre_shared_key") && ($pconfig['mobile']))) {
		if ($pconfig['peerid_type'] == "address" and $pconfig['peerid_data'] == "")
			$input_errors[] = gettext("Please enter an address for 'Peer Identifier'");

		if ($pconfig['peerid_type'] == "keyid tag" and $pconfig['peerid_data'] == "")
			$input_errors[] = gettext("Please enter a keyid tag for 'Peer Identifier'");

		if ($pconfig['peerid_type'] == "fqdn" and $pconfig['peerid_data'] == "")
			$input_errors[] = gettext("Please enter a fully qualified domain name for 'Peer Identifier'");

		if ($pconfig['peerid_type'] == "user_fqdn" and $pconfig['peerid_data'] == "")
			$input_errors[] = gettext("Please enter a user and fully qualified domain name for 'Peer Identifier'");

		if ((($pconfig['peerid_type'] == "address") && !is_ipaddr($pconfig['peerid_data'])))
			$input_errors[] = gettext("A valid IP address for 'Peer identifier' must be specified.");

		if ((($pconfig['peerid_type'] == "fqdn") && !is_domain($pconfig['peerid_data'])))
			$input_errors[] = gettext("A valid domain name for 'Peer identifier' must be specified.");

		if ($pconfig['peerid_type'] == "fqdn")
			if (is_domain($pconfig['peerid_data']) == false)
				$input_errors[] = gettext("A valid FQDN for 'Peer identifier' must be specified.");

		if ($pconfig['peerid_type'] == "user_fqdn") {
			$user_fqdn = explode("@",$pconfig['peerid_data']);
			if (is_domain($user_fqdn[1]) == false)
				$input_errors[] = gettext("A valid User FQDN in the form of user@my.domain.com for 'Peer identifier' must be specified.");
		}
	}

	if ($pconfig['dpd_enable']) {
		if (!is_numeric($pconfig['dpd_delay']))
			$input_errors[] = gettext("A numeric value must be specified for DPD delay.");

		if (!is_numeric($pconfig['dpd_maxfail']))
			$input_errors[] = gettext("A numeric value must be specified for DPD retries.");
	}

	if (!empty($pconfig['iketype']) && $pconfig['iketype'] != "ikev1" && $pconfig['iketype'] != "ikev2")
		$input_errors[] = gettext("Valid arguments for IKE type is v1 or v2");

	/* build our encryption algorithms array */
	$pconfig['ealgo'] = array();
	$pconfig['ealgo']['name'] = $_POST['ealgo'];
	if($pconfig['ealgo_keylen'])
		$pconfig['ealgo']['keylen'] = $_POST['ealgo_keylen'];

	if (!$input_errors) {
		$ph1ent['ikeid'] = $pconfig['ikeid'];
		$ph1ent['iketype'] = $pconfig['iketype'];
		$ph1ent['disabled'] = $pconfig['disabled'] ? true : false;
		$ph1ent['interface'] = $pconfig['interface'];
		/* if the remote gateway changed and the interface is not WAN then remove route */
		/* the vpn_ipsec_configure() handles adding the route */
		if ($pconfig['interface'] <> "wan") {
			if($old_ph1ent['remote-gateway'] <> $pconfig['remotegw']) {
				mwexec("/sbin/route delete -host {$old_ph1ent['remote-gateway']}");
			}
		}

		if ($pconfig['mobile'])
			$ph1ent['mobile'] = true;
		else
			$ph1ent['remote-gateway'] = $pconfig['remotegw'];

		$ph1ent['mode'] = $pconfig['mode'];
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

		if (isset($pconfig['reauth_enable']))
			$ph1ent['reauth_enable'] = true;
		if (isset($pconfig['rekey_enable']))
			$ph1ent['rekey_enable'] = true;

		if (isset($pconfig['dpd_enable'])) {
			$ph1ent['dpd_delay'] = $pconfig['dpd_delay'];
			$ph1ent['dpd_maxfail'] = $pconfig['dpd_maxfail'];
		}

		/* generate unique phase1 ikeid */
		if ($ph1ent['ikeid'] == 0)
			$ph1ent['ikeid'] = ipsec_ikeid_next();

		if (isset($p1index) && $a_phase1[$p1index])
			$a_phase1[$p1index] = $ph1ent;
		else
			$a_phase1[] = $ph1ent;

		write_config();
		mark_subsystem_dirty('ipsec');

		header("Location: vpn_ipsec.php");
		exit;
	}
}

if ($pconfig['mobile'])
	$pgtitle = array(gettext("VPN"),gettext("IPsec"),gettext("Edit Phase 1"), gettext("Mobile Client"));
else
	$pgtitle = array(gettext("VPN"),gettext("IPsec"),gettext("Edit Phase 1"));
$shortcut_section = "ipsec";


include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
//<![CDATA[

function myidsel_change() {
	index = document.iform.myid_type.selectedIndex;
	value = document.iform.myid_type.options[index].value;
	if (value == 'myaddress')
			document.getElementById('myid_data').style.visibility = 'hidden';
	else
			document.getElementById('myid_data').style.visibility = 'visible';
}

function peeridsel_change() {
	index = document.iform.peerid_type.selectedIndex;
	value = document.iform.peerid_type.options[index].value;
	if (value == 'peeraddress')
			document.getElementById('peerid_data').style.visibility = 'hidden';
	else
			document.getElementById('peerid_data').style.visibility = 'visible';
}

function methodsel_change() {
	index = document.iform.authentication_method.selectedIndex;
	value = document.iform.authentication_method.options[index].value;

	switch (value) {
	case 'eap-tls':
		document.getElementById('opt_psk').style.display = 'none';
		document.getElementById('opt_peerid').style.display = '';
		document.getElementById('opt_cert').style.display = '';
		document.getElementById('opt_ca').style.display = '';
		document.getElementById('opt_cert').disabled = false;
		document.getElementById('opt_ca').disabled = false;
		break;
	case 'hybrid_rsa_server':
		document.getElementById('opt_psk').style.display = 'none';
		document.getElementById('opt_peerid').style.display = '';
		document.getElementById('opt_cert').style.display = '';
		document.getElementById('opt_ca').style.display = '';
		document.getElementById('opt_cert').disabled = false;
		document.getElementById('opt_ca').disabled = false;
		break;
	case 'xauth_rsa_server':
	case 'rsasig':
		document.getElementById('opt_psk').style.display = 'none';
		document.getElementById('opt_peerid').style.display = '';
		document.getElementById('opt_cert').style.display = '';
		document.getElementById('opt_ca').style.display = '';
		document.getElementById('opt_cert').disabled = false;
		document.getElementById('opt_ca').disabled = false;
		break;
<?php if ($pconfig['mobile']) { ?>
	case 'pre_shared_key':
		document.getElementById('opt_psk').style.display = 'none';
		document.getElementById('opt_peerid').style.display = 'none';
		document.getElementById('opt_cert').style.display = 'none';
		document.getElementById('opt_ca').style.display = 'none';
		document.getElementById('opt_cert').disabled = true;
		document.getElementById('opt_ca').disabled = true;
		break;
<?php } ?>
	default: /* psk modes*/
		document.getElementById('opt_psk').style.display = '';
		document.getElementById('opt_peerid').style.display = '';
		document.getElementById('opt_cert').style.display = 'none';
		document.getElementById('opt_ca').style.display = 'none';
		document.getElementById('opt_cert').disabled = true;
		document.getElementById('opt_ca').disabled = true;
		break;
	}
}

/* PHP generated java script for variable length keys */
function ealgosel_change(bits) {
	switch (document.iform.ealgo.selectedIndex) {
<?php
$i = 0;
foreach ($p1_ealgos as $algo => $algodata) {
	if (is_array($algodata['keysel'])) {
		echo "		case {$i}:\n";
		echo "			document.iform.ealgo_keylen.style.visibility = 'visible';\n";
		echo "			document.iform.ealgo_keylen.options.length = 0;\n";
	//      echo "			document.iform.ealgo_keylen.options[document.iform.ealgo_keylen.options.length] = new Option( 'auto', 'auto' );\n";

		$key_hi = $algodata['keysel']['hi'];
		$key_lo = $algodata['keysel']['lo'];
		$key_step = $algodata['keysel']['step'];

		for ($keylen = $key_hi; $keylen >= $key_lo; $keylen -= $key_step)
			echo "			document.iform.ealgo_keylen.options[document.iform.ealgo_keylen.options.length] = new Option( '{$keylen} bits', '{$keylen}' );\n";
		echo "			break;\n";
	} else {
		echo "		case {$i}:\n";
		echo "			document.iform.ealgo_keylen.style.visibility = 'hidden';\n";
		echo "			document.iform.ealgo_keylen.options.length = 0;\n";
		echo "			break;\n";
	}
	$i++;
}
?>
	}

	if( bits )
		document.iform.ealgo_keylen.value = bits;
}

function dpdchkbox_change() {
	if( document.iform.dpd_enable.checked )
		document.getElementById('opt_dpd').style.display = '';
	else
		document.getElementById('opt_dpd').style.display = 'none';

	if (!document.iform.dpd_delay.value)
		document.iform.dpd_delay.value = "10";

	if (!document.iform.dpd_maxfail.value)
		document.iform.dpd_maxfail.value = "5";
}

//]]>
</script>

<form action="vpn_ipsec_phase1.php" method="post" name="iform" id="iform">

<?php
	if ($input_errors)
		print_input_errors($input_errors);
?>

<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="vpn ipsec phase-1">
	<tr class="tabnavtbl">
		<td id="tabnav">
			<?php
				$tab_array = array();
				$tab_array[0] = array(gettext("Tunnels"), true, "vpn_ipsec.php");
				$tab_array[1] = array(gettext("Mobile clients"), false, "vpn_ipsec_mobile.php");
				$tab_array[2] = array(gettext("Pre-Shared Keys"), false, "vpn_ipsec_keys.php");
				$tab_array[3] = array(gettext("Advanced Settings"), false, "vpn_ipsec_settings.php");
				display_top_tabs($tab_array);
			?>
		</td>
	</tr>
	<tr>
		<td id="mainarea">
			<div class="tabcont">
				<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?=gettext("General information"); ?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Disabled"); ?></td>
						<td width="78%" class="vtable">
							<input name="disabled" type="checkbox" id="disabled" value="yes" <?php if ($pconfig['disabled']) echo "checked=\"checked\""; ?> />
							<strong><?=gettext("Disable this phase1 entry"); ?></strong><br />
							<span class="vexpl">
								<?=gettext("Set this option to disable this phase1 without " .
								"removing it from the list"); ?>.
							</span>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Key Exchange version"); ?></td>
						<td width="78%" class="vtable">
							<select name="iketype" class="formselect">
							<?php
								$keyexchange = array("ikev1" => "V1", "ikev2" => "V2");
								foreach ($keyexchange as $kidx => $name):
							?>
								<option value="<?=$kidx;?>" <?php if ($kidx == $pconfig['iketype']) echo "selected=\"selected\""; ?>>
									<?=htmlspecialchars($name);?>
								</option>
							<?php endforeach; ?>
							</select> <br /> <span class="vexpl"><?=gettext("Select the KeyExchange Protocol version to be used. Usually known as IKEv1 or IKEv2."); ?>.</span>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Internet Protocol"); ?></td>
						<td width="78%" class="vtable">
							<select name="protocol" class="formselect">
							<?php
								$protocols = array("inet" => "IPv4", "inet6" => "IPv6");
								foreach ($protocols as $protocol => $name):
							?>
								<option value="<?=$protocol;?>" <?php if ($protocol == $pconfig['protocol']) echo "selected=\"selected\""; ?>>
									<?=htmlspecialchars($name);?>
								</option>
							<?php endforeach; ?>
							</select> <br /> <span class="vexpl"><?=gettext("Select the Internet Protocol family from this dropdown"); ?>.</span>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Interface"); ?></td>
						<td width="78%" class="vtable">
							<select name="interface" class="formselect">
							<?php
								$interfaces = get_configured_interface_with_descr();

								$carplist = get_configured_carp_interface_list();
								foreach ($carplist as $cif => $carpip)
									$interfaces[$cif] = $carpip." (".get_vip_descr($carpip).")";

								$aliaslist = get_configured_ip_aliases_list();
								foreach ($aliaslist as $aliasip => $aliasif)
									$interfaces[$aliasip] = $aliasip." (".get_vip_descr($aliasip).")";

								$grouplist = return_gateway_groups_array();
								foreach ($grouplist as $name => $group) {
									if($group[0]['vip'] <> "")
										$vipif = $group[0]['vip'];
									else
										$vipif = $group[0]['int'];
									$interfaces[$name] = "GW Group {$name}";
								}


								foreach ($interfaces as $iface => $ifacename):
							?>
								<option value="<?=$iface;?>" <?php if ($iface == $pconfig['interface']) echo "selected=\"selected\""; ?>>
									<?=htmlspecialchars($ifacename);?>
								</option>
							<?php endforeach; ?>
							</select>
							<br />
							<span class="vexpl"><?=gettext("Select the interface for the local endpoint of this phase1 entry"); ?>.</span>
						</td>
					</tr>

					<?php if (!$pconfig['mobile']): ?>

					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Remote gateway"); ?></td>
						<td width="78%" class="vtable">
							<?=$mandfldhtml;?><input name="remotegw" type="text" class="formfld unknown" id="remotegw" size="28" value="<?=htmlspecialchars($pconfig['remotegw']);?>" />
							<br />
							<?=gettext("Enter the public IP address or host name of the remote gateway"); ?>
						</td>
					</tr>

					<?php endif; ?>

					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
						<td width="78%" class="vtable">
							<input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>" />
							<br />
							<span class="vexpl">
								<?=gettext("You may enter a description here " .
								"for your reference (not parsed)"); ?>.
							</span>
						</td>
					</tr>
					<tr>
						<td colspan="2" class="list" height="12"></td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="listtopic">
							<?=gettext("Phase 1 proposal (Authentication)"); ?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Authentication method"); ?></td>
						<td width="78%" class="vtable">
							<select name="authentication_method" class="formselect" onchange="methodsel_change()">
							<?php
								foreach ($p1_authentication_methods as $method_type => $method_params):
									if (!$pconfig['mobile'] && $method_params['mobile'])
										continue;
							?>
								<option value="<?=$method_type;?>" <?php if ($method_type == $pconfig['authentication_method']) echo "selected=\"selected\""; ?>>
									<?=htmlspecialchars($method_params['name']);?>
								</option>
							<?php endforeach; ?>
							</select>
							<br />
							<span class="vexpl">
								<?=gettext("Must match the setting chosen on the remote side"); ?>.
							</span>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Negotiation mode"); ?></td>
						<td width="78%" class="vtable">
							<select name="mode" class="formselect">
							<?php
								$modes = array("main" => "Main", "aggressive" => "Aggressive");
								foreach ($modes as $mode => $mdescr):
							?>
								<option value="<?=$mode;?>" <?php if ($mode == $pconfig['mode']) echo "selected=\"selected\""; ?>>
									<?=htmlspecialchars($mdescr);?>
								</option>
							<?php endforeach; ?>
							</select> <br /> <span class="vexpl"><?=gettext("Aggressive is more flexible, but less secure"); ?>.</span>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("My identifier"); ?></td>
						<td width="78%" class="vtable">
							<select name="myid_type" class="formselect" onchange="myidsel_change()">
							<?php foreach ($my_identifier_list as $id_type => $id_params): ?>
								<option value="<?=$id_type;?>" <?php if ($id_type == $pconfig['myid_type']) echo "selected=\"selected\""; ?>>
									<?=htmlspecialchars($id_params['desc']);?>
								</option>
							<?php endforeach; ?>
							</select>
							<input name="myid_data" type="text" class="formfld unknown" id="myid_data" size="30" value="<?=htmlspecialchars($pconfig['myid_data']);?>" />
						</td>
					</tr>
					<tr id="opt_peerid">
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Peer identifier"); ?></td>
						<td width="78%" class="vtable">
							<select name="peerid_type" class="formselect" onchange="peeridsel_change()">
							<?php
								foreach ($peer_identifier_list as $id_type => $id_params):
									if ($pconfig['mobile'] && !$id_params['mobile'])
										continue;
							?>
							<option value="<?=$id_type;?>" <?php if ($id_type == $pconfig['peerid_type']) echo "selected=\"selected\""; ?>>
								<?=htmlspecialchars($id_params['desc']);?>
							</option>
							<?php endforeach; ?>
							</select>
							<input name="peerid_data" type="text" class="formfld unknown" id="peerid_data" size="30" value="<?=htmlspecialchars($pconfig['peerid_data']);?>" />
						<?php if ($pconfig['mobile']) { ?>
							<br /><br /><?=gettext("NOTE: This is known as the \"group\" setting on some VPN client implementations"); ?>.
						<?php } ?>
						</td>
					</tr>
					<tr id="opt_psk">
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Pre-Shared Key"); ?></td>
						<td width="78%" class="vtable">
							<?=$mandfldhtml;?>
							<input name="pskey" type="text" class="formfld unknown" id="pskey" size="40" value="<?=htmlspecialchars($pconfig['pskey']);?>" />
							<span class="vexpl">
							<br />
								<?=gettext("Input your Pre-Shared Key string"); ?>.
							</span>
						</td>
					</tr>
					<tr id="opt_cert">
						<td width="22%" valign="top" class="vncellreq"><?=gettext("My Certificate"); ?></td>
						<td width="78%" class="vtable">
							<select name="certref" class="formselect">
							<?php
								foreach ($config['cert'] as $cert):
									$selected = "";
									if ($pconfig['certref'] == $cert['refid'])
										$selected = "selected=\"selected\"";
							?>
								<option value="<?=$cert['refid'];?>" <?=$selected;?>><?=$cert['descr'];?></option>
							<?php endforeach; ?>
							</select>
							<br />
							<span class="vexpl">
								<?=gettext("Select a certificate previously configured in the Certificate Manager"); ?>.
							</span>
						</td>
					</tr>
					<tr id="opt_ca">
						<td width="22%" valign="top" class="vncellreq"><?=gettext("My Certificate Authority"); ?></td>
						<td width="78%" class="vtable">
							<select name="caref" class="formselect">
							<?php
								foreach ($config['ca'] as $ca):
									$selected = "";
									if ($pconfig['caref'] == $ca['refid'])
										$selected = "selected=\"selected\"";
							?>
								<option value="<?=$ca['refid'];?>" <?=$selected;?>><?=$ca['descr'];?></option>
							<?php endforeach; ?>
							</select>
							<br />
							<span class="vexpl">
								<?=gettext("Select a certificate authority previously configured in the Certificate Manager"); ?>.
							</span>
						</td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="listtopic">
							<?=gettext("Phase 1 proposal (Algorithms)"); ?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Encryption algorithm"); ?></td>
						<td width="78%" class="vtable">
							<select name="ealgo" class="formselect" onchange="ealgosel_change()">
							<?php
								foreach ($p1_ealgos as $algo => $algodata):
									$selected = "";
									if ($algo == $pconfig['ealgo']['name'])
										$selected = " selected=\"selected\"";
							?>
								<option value="<?=$algo;?>"<?=$selected?>>
									<?=htmlspecialchars($algodata['name']);?>
								</option>
							<?php endforeach; ?>
							</select>
							<select name="ealgo_keylen" width="30" class="formselect">
							</select>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Hash algorithm"); ?></td>
						<td width="78%" class="vtable">
							<select name="halgo" class="formselect">
							<?php foreach ($p1_halgos as $algo => $algoname): ?>
								<option value="<?=$algo;?>" <?php if ($algo == $pconfig['halgo']) echo "selected=\"selected\""; ?>>
									<?=htmlspecialchars($algoname);?>
								</option>
							<?php endforeach; ?>
							</select>
							<br />
							<span class="vexpl">
								<?=gettext("Must match the setting chosen on the remote side"); ?>.
							</span>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("DH key group"); ?></td>
						<td width="78%" class="vtable">
							<select name="dhgroup" class="formselect">
							<?php foreach ($p1_dhgroups as $keygroup => $keygroupname): ?>
								<option value="<?=$keygroup;?>" <?php if ($keygroup == $pconfig['dhgroup']) echo "selected=\"selected\""; ?>>
									<?=htmlspecialchars($keygroupname);?>
								</option>
							<?php endforeach; ?>
							</select>
							<br />
							<span class="vexpl">
								<?=gettext("Must match the setting chosen on the remote side"); ?>.
							</span>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Lifetime"); ?></td>
						<td width="78%" class="vtable">
							<input name="lifetime" type="text" class="formfld unknown" id="lifetime" size="20" value="<?=htmlspecialchars($pconfig['lifetime']);?>" />
							<?=gettext("seconds"); ?>
						</td>
					</tr>
					<tr>
						<td colspan="2" class="list" height="12"></td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?=gettext("Advanced Options"); ?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Disable Rekey");?></td>
						<td width="78%" class="vtable">
							<input name="rekey_enable" type="checkbox" id="rekey_enable" value="yes" <?php if (isset($pconfig['rekey_enable'])) echo "checked=\"checked\""; ?> />
							<?=gettext("Whether a connection should be renegotiated when it is about to expire."); ?><br />
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Disable Reauth");?></td>
						<td width="78%" class="vtable">
							<input name="reauth_enable" type="checkbox" id="reauth_enable" value="yes" <?php if (isset($pconfig['reauth_enable'])) echo "checked=\"checked\""; ?> />
							<?=gettext("Whether rekeying of an IKE_SA should also reauthenticate the peer. In IKEv1, reauthentication is always done."); ?><br />
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("NAT Traversal"); ?></td>
						<td width="78%" class="vtable">
							<select name="nat_traversal" class="formselect">
								<option value="off" <?php if ($pconfig['nat_traversal'] == "off") echo "selected=\"selected\""; ?>><?=gettext("Disable"); ?></option>
								<option value="on" <?php if ($pconfig['nat_traversal'] == "on") echo "selected=\"selected\""; ?>><?=gettext("Enable"); ?></option>
								<option value="force" <?php if ($pconfig['nat_traversal'] == "force") echo "selected=\"selected\""; ?>><?=gettext("Force"); ?></option>
							</select>
							<br />
							<span class="vexpl">
								<?=gettext("Set this option to enable the use of NAT-T (i.e. the encapsulation of ESP in UDP packets) if needed, " .
								"which can help with clients that are behind restrictive firewalls"); ?>.
							</span>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Dead Peer Detection"); ?></td>
						<td width="78%" class="vtable">
							<input name="dpd_enable" type="checkbox" id="dpd_enable" value="yes" <?php if (isset($pconfig['dpd_enable'])) echo "checked=\"checked\""; ?> onclick="dpdchkbox_change()" />
							<?=gettext("Enable DPD"); ?><br />
							<div id="opt_dpd">
								<br />
								<input name="dpd_delay" type="text" class="formfld unknown" id="dpd_delay" size="5" value="<?=htmlspecialchars($pconfig['dpd_delay']);?>" />
								<?=gettext("seconds"); ?><br />
								<span class="vexpl">
									<?=gettext("Delay between requesting peer acknowledgement"); ?>.
								</span><br />
								<br />
								<input name="dpd_maxfail" type="text" class="formfld unknown" id="dpd_maxfail" size="5" value="<?=htmlspecialchars($pconfig['dpd_maxfail']);?>" />
								<?=gettext("retries"); ?><br />
								<span class="vexpl">
									<?=gettext("Number of consecutive failures allowed before disconnect"); ?>.
								</span>
								<br />
							</div>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top">&nbsp;</td>
						<td width="78%">
							<?php if (isset($p1index) && $a_phase1[$p1index]): ?>
							<input name="p1index" type="hidden" value="<?=htmlspecialchars($p1index);?>" />
							<?php endif; ?>
							<?php if ($pconfig['mobile']): ?>
							<input name="mobile" type="hidden" value="true" />
							<?php endif; ?>
							<input name="ikeid" type="hidden" value="<?=htmlspecialchars($pconfig['ikeid']);?>" />
							<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" />
						</td>
					</tr>
				</table>
			</div>
		</td>
	</tr>
</table>
</form>

<script type="text/javascript">
//<![CDATA[
<?php
	/* determine if we should init the key length */
	$keyset = '';
	if (isset($pconfig['ealgo']['keylen']))
		if (is_numeric($pconfig['ealgo']['keylen']))
			$keyset = $pconfig['ealgo']['keylen'];
?>
myidsel_change();
peeridsel_change();
methodsel_change();
ealgosel_change(<?=$keyset;?>);
dpdchkbox_change();
//]]>
</script>
<?php include("fend.inc"); ?>
</body>
</html>
