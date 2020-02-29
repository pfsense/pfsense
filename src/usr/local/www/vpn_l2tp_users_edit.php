<?php
/*
 * vpn_l2tp_users_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-vpn-vpnl2tp-users-edit
##|*NAME=VPN: L2TP: Users: Edit
##|*DESCR=Allow access to the 'VPN: L2TP: Users: Edit' page.
##|*MATCH=vpn_l2tp_users_edit.php*
##|-PRIV

$pgtitle = array(gettext("VPN"), gettext("L2TP"), gettext("Users"), gettext("Edit"));
$pglinks = array("", "vpn_l2tp.php", "vpn_l2tp_users.php", "@self");
$shortcut_section = "l2tps";

function l2tpusercmp($a, $b) {
	return strcasecmp($a['name'], $b['name']);
}

function l2tp_users_sort() {
	global $config;

	if (!is_array($config['l2tp']['user'])) {
		return;
	}

	usort($config['l2tp']['user'], "l2tpusercmp");
}

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("vpn.inc");

init_config_arr(array('l2tp', 'user'));
$a_secret = &$config['l2tp']['user'];

if (isset($_REQUEST['id']) && is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

if (isset($id) && $a_secret[$id]) {
	$pconfig['usernamefld'] = $a_secret[$id]['name'];
	$pconfig['ip'] = $a_secret[$id]['ip'];
	$pconfig['passwordfld'] = $a_secret[$id]['passwordfld'];
	$pwd_required = "";
} else {
	$pwd_required = "*";
}

if ($_POST['save']) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if (isset($id) && ($a_secret[$id])) {
		$reqdfields = explode(" ", "usernamefld");
		$reqdfieldsn = array(gettext("Username"));
	} else {
		$reqdfields = explode(" ", "usernamefld passwordfld");
		$reqdfieldsn = array(gettext("Username"), gettext("Password"));
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (preg_match("/[^a-zA-Z0-9\.\@\-_]/", $_POST['usernamefld'])) {
		$input_errors[] = gettext("The username contains invalid characters.");
	}
	if (preg_match("/^!/", trim($_POST['passwordfld']))) {
		$input_errors[] = gettext("The password cannot start with '!'.");
	}
	if (($_POST['passwordfld']) && ($_POST['passwordfld'] != $_POST['passwordfld_confirm'])) {
		$input_errors[] = gettext("The passwords do not match.");
	}
	if (($_POST['ip'] && !is_ipaddr($_POST['ip']))) {
		$input_errors[] = gettext("The IP address entered is not valid.");
	}

	if (!$input_errors && !(isset($id) && $a_secret[$id])) {
		/* make sure there are no dupes */
		foreach ($a_secret as $secretent) {
			if ($secretent['name'] == $_POST['usernamefld']) {
				$input_errors[] = gettext("Another entry with the same username already exists.");
				break;
			}
		}
	}

	if (!$input_errors) {

		if (isset($id) && $a_secret[$id]) {
			$secretent = $a_secret[$id];
		}

		$secretent['name'] = $_POST['usernamefld'];
		$secretent['ip'] = $_POST['ip'];

		if ($_POST['passwordfld'] && ($_POST['passwordfld'] != DMYPWD)) {
			$secretent['password'] = $_POST['passwordfld'];
		}

		if (isset($id) && $a_secret[$id]) {
			$a_secret[$id] = $secretent;
		} else {
			$a_secret[] = $secretent;
		}
		l2tp_users_sort();

		write_config(gettext("Configured a L2TP VPN user."));

		$retval = vpn_l2tp_configure();

		pfSenseHeader("vpn_l2tp_users.php");

		exit;
	}
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section("User");

$section->addInput(new Form_Input(
	'usernamefld',
	'*Username',
	'text',
	$pconfig['usernamefld'],
	['autocomplete' => 'new-password']
));

$pwd = new Form_Input(
	'passwordfld',
	$pwd_required . 'Password',
	'text',
	$pconfig['passwordfld']
);

if (isset($id) && $a_secret[$id]) {
	$pwd->setHelp('To change the users password, enter it here.');
}

$section->addPassword($pwd);

$section->addInput(new Form_IpAddress(
	'ip',
	'IP Address',
	$pconfig['ip']
))->setHelp('To assign the user a specific IP address, enter it here.');

$form->add($section);

if (isset($id) && $a_secret[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

print($form);

include("foot.inc");
