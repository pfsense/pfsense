<?php
/*
	vpn_ipsec_keys_edit.php
	part of m0n0wall (http://m0n0.ch/wall)
	part of pfSense

	Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
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
##|*IDENT=page-vpn-ipsec-editkeys
##|*NAME=VPN: IPsec: Edit Pre-Shared Keys
##|*DESCR=Allow access to the 'VPN: IPsec: Edit Pre-Shared Keys' page.
##|*MATCH=vpn_ipsec_keys_edit.php*
##|-PRIV

require("functions.inc");
require("guiconfig.inc");
require_once("ipsec.inc");
require_once("vpn.inc");

if (!is_array($config['ipsec']['mobilekey'])) {
	$config['ipsec']['mobilekey'] = array();
}
ipsec_mobilekey_sort();
$a_secret = &$config['ipsec']['mobilekey'];

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && $a_secret[$id]) {
	$pconfig['ident'] = $a_secret[$id]['ident'];
	$pconfig['type'] = $a_secret[$id]['type'];
	$pconfig['psk'] = $a_secret[$id]['pre-shared-key'];
}

if ($_POST) {
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
			$text = gettext("Edited");
		} else {
			$a_secret[] = $secretent;
			$text = gettext("Added");
		}

		write_config("{$text} IPsec Pre-Shared Keys");
		mark_subsystem_dirty('ipsec');

		header("Location: vpn_ipsec_keys.php");
		exit;
	}
}

$pgtitle = gettext("VPN: IPsec: Edit Pre-Shared Key");
$shortcut_section = "ipsec";

include("head.inc");

?>

<?php if ($input_errors) print_input_errors($input_errors); ?>

<?php

require_once('classes/Form.class.php');
$form = new Form;

$section = new Form_Section('Edit pre-shared-secret');

$section->addInput(new Form_Input(
	'ident',
	'Identifier',
	'text',
	$pconfig['ident']
))->setHelp('This can be either an IP address, fully qualified domain name or an e-mail address');

$section->addInput(new Form_Select(
	'type',
	'Secret type',
	$pconfig['type'],
	$ipsec_preshared_key_type
))->setWidth(2);

$section->addInput(new Form_Input(
	'psk',
	'Pre-Shared Key',
	'text',
	$pconfig['psk']
));

if (isset($id) && $a_secret[$id])
{
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

<div class="alert alert-info">
	<strong><?=gettext("Note"); ?>:</strong><br />
	<?=gettext("PSK for any user can be set by using an identifier of any")?>
</div>

<?php include("foot.inc"); ?>