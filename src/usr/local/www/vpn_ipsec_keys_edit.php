<?php
/*
 * vpn_ipsec_keys_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-vpn-ipsec-editkeys
##|*NAME=VPN: IPsec: Edit Pre-Shared Keys
##|*DESCR=Allow access to the 'VPN: IPsec: Edit Pre-Shared Keys' page.
##|*MATCH=vpn_ipsec_keys_edit.php*
##|-PRIV

require_once("functions.inc");
require_once("guiconfig.inc");
require_once("ipsec.inc");
require_once("vpn.inc");
require_once("certs.inc");

init_config_arr(array('ipsec', 'mobilekey'));
ipsec_mobilekey_sort();
$a_secret = &$config['ipsec']['mobilekey'];

init_config_arr(array('ipsec', 'phase1'));
$a_phase1 = &$config['ipsec']['phase1'];

if (is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

if (isset($id) && $a_secret[$id]) {
	$pconfig['phase1_ikeid'] = $a_secret[$id]['ikeid'];
	$pconfig['ident'] = $a_secret[$id]['ident'];
	$pconfig['type'] = $a_secret[$id]['type'];
	$pconfig['psk'] = $a_secret[$id]['pre-shared-key'];
	$pconfig['ident_type'] = $a_secret[$id]['ident_type'];
	$pconfig['pool_address'] = $a_secret[$id]['pool_address'];
	$pconfig['pool_netbits'] = $a_secret[$id]['pool_netbits'];
	$pconfig['dns_address'] = $a_secret[$id]['dns_address'];
}

if ($_POST['save']) {
	$userids = array();
	foreach ($config['system']['user'] as $uid => $user) {
		$userids[$user['name']] = $uid;
	}

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "ident psk");
	$reqdfieldsn = array(gettext("Identifier"), gettext("Pre-Shared Key"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($_POST['type'] != "RSA") {
		if (preg_match("/[^a-zA-Z0-9@\.\-]/", $_POST['ident'])) {
			$input_errors[] = gettext("The identifier contains invalid characters.");
		}
	}

	if (array_key_exists($_POST['ident'], $userids)) {
		$input_errors[] = gettext("A user with this name already exists. Add the key to the user instead.");
	}
	unset($userids);

	if (isset($_POST['psk']) && !preg_match('/^[[:ascii:]]*$/', $_POST['psk'])) {
		$input_errors[] = gettext("Pre-Shared Key contains invalid characters.");
	}

	if (isset($_POST['pool_address']) && strlen($_POST['pool_address']) > 1 && !is_ipaddr($_POST['pool_address'])) {
		$input_errors[] = gettext("A valid IP address for 'Virtual Address Pool' must be specified.");
	}

	if (isset($_POST['dns_address']) && strlen($_POST['dns_address']) > 1 && !is_ipaddr($_POST['dns_address'])) {
		$input_errors[] = gettext("A valid IP address for 'DNS Server' must be specified.");
	}

	if (!$input_errors && !(isset($id) && $a_secret[$id])) {
		/* make sure there are no dupes */
		foreach ($a_secret as $secretent) {
			if ($secretent['ident'] == $_POST['ident'] && $secretent['ikeid'] == $_POST['phase1_ikeid']) {
				$input_errors[] = gettext("Another entry with the same identifier already exists.");
				break;
			}
		}
	}

	if (!$input_errors) {

		if (isset($id) && $a_secret[$id]) {
			$secretent = $a_secret[$id];
		}

		$secretent['ikeid'] = $_POST['phase1_ikeid'];
		$secretent['ident'] = ($_POST['type'] == "RSA") ? cert_escape_x509_chars($_POST['ident']) : $_POST['ident'];
		$secretent['type'] = $_POST['type'];

		switch ($secretent['type']) {
			case "RSA":
				unset($secretent['pre-shared-key']);
				break;
			default:
				$secretent['pre-shared-key'] = $_POST['psk'];
				break;
		}

		$secretent['ident_type'] = $_POST['ident_type'];
		$secretent['pool_address'] = $_POST['pool_address'];
		$secretent['pool_netbits'] = $_POST['pool_netbits'];
		$secretent['dns_address'] = $_POST['dns_address'];
		$text = "";

		if (isset($id) && $a_secret[$id]) {
			$a_secret[$id] = $secretent;
			$text = gettext("Edited IPsec Pre-Shared Keys");
		} else {
			$a_secret[] = $secretent;
			$text = gettext("Added IPsec Pre-Shared Keys");
		}

		write_config($text);
		mark_subsystem_dirty('ipsec');

		header("Location: vpn_ipsec_keys.php");
		exit;
	}
}

function build_ipsec_identifier_list() {
	global $ipsec_identifier_list;

	$list = array();

	foreach ($ipsec_identifier_list as $identifier_type => $identifier_params) {
		$list[$identifier_type] = htmlspecialchars($identifier_params['desc']);
	}

	return($list);
}

function build_ipsec_phase1_list() {
	global $a_phase1;

	$list = array();

	function phase1_descr_cmp($a, $b) {
		return strcmp($a, $b);
	}

	foreach ($a_phase1 as $ph1ent) {
		if (!isset($ph1ent['mobile'])) {
			continue;
		}
		$list[$ph1ent['ikeid']] = htmlspecialchars($ph1ent['descr']);

		if (isset($ph1ent['disabled'])) {
			$list[$ph1ent['ikeid']] .= " (disabled)";
		}
	}

	uasort($list, "phase1_descr_cmp");

	return($list);
}

$pgtitle = array(gettext("VPN"), gettext("IPsec"), gettext("Pre-Shared Keys"), gettext("Edit"));
$pglinks = array("", "vpn_ipsec.php", "vpn_ipsec_keys.php", "@self");
$shortcut_section = "ipsec";

include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

$form = new Form;

$section = new Form_Section('Edit Pre-Shared-Secret');

$section->addInput(new Form_Input(
	'ident',
	'*Identifier',
	'text',
	$pconfig['ident']
))->setHelp('This can be either an IP address, fully qualified domain name or an e-mail address.<br />Hint: If Secret type is RSA, this Identifier has to be the Common Name of the selected User certificate.');

$section->addInput(new Form_Select(
	'phase1_ikeid',
	'*Linked Phase 1',
	$pconfig['phase1_ikeid'],
	build_ipsec_phase1_list()
))->setWidth(4)->setHelp('Required: Link User to Phase 1 of IPsec Tunnel.<br/>Sorted by Phase 1 description.');

$section->addInput(new Form_Select(
	'type',
	'*Secret type',
	$pconfig['type'],
	$ipsec_preshared_key_type
))->setWidth(2);

$section->addInput(new Form_Input(
	'psk',
	'*Pre-Shared Key',
	'text',
	$pconfig['psk']
));

$section->addInput(new Form_Select(
	'ident_type',
	'Identifier type',
	$pconfig['ident_type'],
	build_ipsec_identifier_list()
))->setWidth(4)->setHelp('Optional: specify identifier type for strongswan');

$section->addInput(new Form_IpAddress(
	'pool_address',
	'Virtual Address Pool',
	$pconfig['pool_address']
))->setWidth(4)->setHelp('Optional. If used, must be IPv4 address. If left blank, "Virtual Address Pool" of "Mobile Clients" will be used.')->addMask('pool_netbits', $pconfig['pool_netbits'], 32, 0);

$section->addInput(new Form_IpAddress(
	'dns_address',
	'DNS Server',
	$pconfig['dns_address']
))->setWidth(4)->setHelp('Optional. If used, must be IPv4 address. Individual DNS server only for this user. If left blank, "DNS Servers" of "Mobile Clients" will be used.');

if (isset($id) && $a_secret[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		false,
		'hidden',
		$id
	));
}

$form->add($section);

print $form;
?>
<div class="infoblock blockopen">
<?php
print_info_box(gettext("PSK for any user can be set by using an identifier of any."), 'info', false);
?>
</div>
<form action="vpn_ipsec_phase1.php" method="post" name="iform" id="iform">

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	function type_change() {
		value = $('#type').val();

		switch (value) {
			case "RSA":
				hideInput('psk', true);
				break;
			default:
				hideInput('psk', false);
				break;
		}
	}

	$('#type').change(function () {
		type_change();
	});

	var generateButton = $('<a class="btn btn-xs btn-warning"><i class="fa fa-refresh icon-embed-btn"></i><?=gettext("Generate new Pre-Shared Key");?></a>');
	generateButton.on('click', function() {
		$.ajax({
			type: 'get',
			url: 'vpn_ipsec_phase1.php?generatekey=true',
			dataType: 'json',
			success: function(data) {
				$('#psk').val(data.pskey.replace(/\\n/g, '\n'));
			}
		});
	});

	type_change();
	$('#psk').parent().append(generateButton);
});
//]]>
</script>
</form>
<?php
include("foot.inc");
