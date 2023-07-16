<?php
/*
 * vpn_ipsec_keys_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2023 Rubicon Communications, LLC (Netgate)
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

init_config_arr(array('ipsec', 'mobilekey'));

if (is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

if (isset($id) && config_get_path('ipsec/mobilekey/' . $id)) {
	$pconfig['ident'] = config_get_path('ipsec/mobilekey/' . $id . '/ident');
	$pconfig['type'] = config_get_path('ipsec/mobilekey/' . $id . '/type');
	$pconfig['psk'] = config_get_path('ipsec/mobilekey/' . $id . '/pre-shared-key');
	$pconfig['ident_type'] = config_get_path('ipsec/mobilekey/' . $id . '/ident_type');
	$pconfig['pool_address'] = config_get_path('ipsec/mobilekey/' . $id . '/pool_address');
	$pconfig['pool_netbits'] = config_get_path('ipsec/mobilekey/' . $id . '/pool_netbits');
	$pconfig['dns_address'] = config_get_path('ipsec/mobilekey/' . $id . '/dns_address');
}

if ($_POST['save']) {
	$userids = array();
	foreach (config_get_path('system/user', []) as $uid => $user) {
		$userids[$user['name']] = $uid;
	}

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "ident psk");
	$reqdfieldsn = array(gettext("Identifier"), gettext("Pre-Shared Key"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (preg_match("/[^a-zA-Z0-9@\.\-]/", $_POST['ident'])) {
		$input_errors[] = gettext("The identifier contains invalid characters.");
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

	if (!$input_errors && !(isset($id) && config_get_path('ipsec/mobilekey/' . $id))) {
		/* make sure there are no dupes */
		foreach (config_get_path('ipsec/mobilekey', []) as $secretent) {
			if ($secretent['ident'] == $_POST['ident']) {
				$input_errors[] = gettext("Another entry with the same identifier already exists.");
				break;
			}
		}
	}

	if (!$input_errors) {

		if (isset($id) && config_get_path('ipsec/mobilekey/' . $id)) {
			$secretent = config_get_path('ipsec/mobilekey/' . $id);
		}

		$secretent['ident'] = $_POST['ident'];
		$secretent['type'] = $_POST['type'];
		$secretent['pre-shared-key'] = $_POST['psk'];
		$secretent['ident_type'] = $_POST['ident_type'];
		$secretent['pool_address'] = $_POST['pool_address'];
		$secretent['pool_netbits'] = $_POST['pool_netbits'];
		$secretent['dns_address'] = $_POST['dns_address'];
		$text = "";

		if (isset($id) && config_get_path('ipsec/mobilekey/' . $id)) {
			config_set_path('ipsec/mobilekey/' . $id, $secretent);
			$text = gettext("Edited IPsec Pre-Shared Keys");
		} else {
			$mks = config_get_path('ipsec/mobilekey', []);
			$mks[] = $secretent;
			config_set_path('ipsec/mobilekey', $mks);
			$text = gettext("Added IPsec Pre-Shared Keys");
		}

		write_config($text);
		mark_subsystem_dirty('ipsec');

		header("Location: vpn_ipsec_keys.php");
		exit;
	}
}

function build_ipsecid_list() {
	global $ipsec_identifier_list;

	$list = array();

	foreach ($ipsec_identifier_list as $id_type => $id_params) {
		$list[$id_type] = htmlspecialchars($id_params['desc']);
	}

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
))->setHelp('This can be either an IP address, fully qualified domain name or an e-mail address.');

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
	build_ipsecid_list()
))->setWidth(4)->setHelp('Optional: specify identifier type for EAP authentication');

$group = new Form_Group('Virtual Address Pool');
$group->addClass('virtualip');
$group->add(new Form_IpAddress(
	'pool_address',
	'Virtual Address Pool',
	$pconfig['pool_address']
))->setWidth(4)->setHelp('Optional. If used, must be IPv4 address. If left blank, "Virtual Address Pool" of "Mobile Clients" will be used.')->addMask('pool_netbits', $pconfig['pool_netbits'], 32, 0);
$section->add($group);

$section->addInput(new Form_IpAddress(
	'dns_address',
	'DNS Server',
	$pconfig['dns_address']
))->setWidth(4)->setHelp('Optional. If used, must be IPv4 address. Individual DNS server only for this user. If left blank, "DNS Servers" of "Mobile Clients" will be used.');

if (isset($id) && config_get_path('ipsec/mobilekey/' . $id)) {
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
<script type="text/javascript">
//<![CDATA[
events.push(function() {
	function change_type() {
		hide = $('#type').val() != 'EAP';
		hideInput('ident_type', hide);
		hideClass('virtualip', hide);
		hideInput('dns_address', hide);
	}

	$('#type').change(function () {
		change_type();
	});

	// ---------- On initial page load ------------------------------------------------------------

	change_type();
});
//]]>
</script>
<?php
include("foot.inc");
