<?php
/*
 * vpn_ipsec_keys_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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

if (!is_array($config['ipsec']['mobilekey'])) {
	$config['ipsec']['mobilekey'] = array();
}
ipsec_mobilekey_sort();
$a_secret = &$config['ipsec']['mobilekey'];

if (is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

if (isset($id) && $a_secret[$id]) {
	$pconfig['ident'] = $a_secret[$id]['ident'];
	$pconfig['type'] = $a_secret[$id]['type'];
	$pconfig['psk'] = $a_secret[$id]['pre-shared-key'];
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

	if (!$input_errors && !(isset($id) && $a_secret[$id])) {
		/* make sure there are no dupes */
		foreach ($a_secret as $secretent) {
			if ($secretent['ident'] == $_POST['ident']) {
				$input_errors[] = gettext("Another entry with the same identifier already exists.");
				break;
			}
		}
	}

	if (!$input_errors) {

		if (isset($id) && $a_secret[$id]) {
			$secretent = $a_secret[$id];
		}

		$secretent['ident'] = $_POST['ident'];
		$secretent['type'] = $_POST['type'];
		$secretent['pre-shared-key'] = $_POST['psk'];
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
<?php
include("foot.inc");
