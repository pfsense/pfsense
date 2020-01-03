<?php
/*
 * services_checkip_edit.php
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
##|*IDENT=page-services-checkipedit
##|*NAME=Services: Check IP Service: Edit
##|*DESCR=Allow access to the 'Services: Check IP Service: Edit' page.
##|*MATCH=services_checkip_edit.php*
##|-PRIV

require_once("guiconfig.inc");

init_config_arr(array('checkipservices', 'checkipservice'));
$a_checkip = &$config['checkipservices']['checkipservice'];

if (is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && isset($a_checkip[$id])) {
	$pconfig['enable'] = isset($a_checkip[$id]['enable']);
	$pconfig['name'] = $a_checkip[$id]['name'];
	$pconfig['url'] = $a_checkip[$id]['url'];
	$pconfig['username'] = $a_checkip[$id]['username'];
	$pconfig['password'] = $a_checkip[$id]['password'];
	$pconfig['verifysslpeer'] = isset($a_checkip[$id]['verifysslpeer']);
	$pconfig['descr'] = $a_checkip[$id]['descr'];
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = array();
	$reqdfieldsn = array();
	$reqdfields = array_merge($reqdfields, explode(" ", "name url"));
	$reqdfieldsn = array_merge($reqdfieldsn, array(gettext("Name"), gettext("URL")));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (($_POST['name'] && !is_validaliasname($_POST['name']))) {
		$input_errors[] = gettext("The Check IP Service name contains invalid characters.");
	}
	if (($_POST['url'] && !is_URL($_POST['url']))) {
		$input_errors[] = gettext("The Check IP Service URL is not valid.");
	}
	if ($_POST['passwordfld'] != $_POST['passwordfld_confirm']) {
		$input_errors[] = gettext("Password and confirmed password must match.");
	}

	if (!$input_errors) {
		$checkip = array();
		$checkip['enable'] = $_POST['enable'] ? true : false;
		$checkip['name'] = $_POST['name'];
		$checkip['url'] = $_POST['url'];
		$checkip['username'] = $_POST['username'];

		if ($_POST['passwordfld'] != DMYPWD) {
			$checkip['password'] = $_POST['passwordfld'];
		} else {
			$checkip['password'] = $a_checkip[$id]['password'];;
		}

		$checkip['verifysslpeer'] = $_POST['verifysslpeer'] ? true : false;
		$checkip['descr'] = $_POST['descr'];

		if (isset($id) && $a_checkip[$id]) {
			$a_checkip[$id] = $checkip;
		} else {
			$a_checkip[] = $checkip;
		}

		write_config(gettext("New/Edited Check IP Services entry was posted."));

		header("Location: services_checkip.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("Dynamic DNS"), gettext("Check IP Services"), gettext("Edit"));
$pglinks = array("", "services_dyndns.php", "services_checkip.php", "@self");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form;

$section = new Form_Section('Check IP Service');

$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	null,
	$pconfig['enable']
));

$section->addInput(new Form_Input(
	'name',
	'*Name',
	'text',
	$pconfig['name']
))->setHelp('The name of the service may only consist of the characters "a-z, A-Z, 0-9 and _".');

$section->addInput(new Form_Input(
	'url',
	'*URL',
	'text',
	$pconfig['url']
));

$section->addInput(new Form_Input(
	'username',
	'User name',
	'text',
	$pconfig['username'],
	['autocomplete' => 'new-password']
));

$section->addPassword(new Form_Input(
	'passwordfld',
	'Password',
	'password',
	$pconfig['password']
));

$section->addInput(new Form_Checkbox(
	'verifysslpeer',
	'Verify SSL Peer',
	'Verify SSL Peer',
	$pconfig['verifysslpeer']
));

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

if (isset($id) && $a_checkip[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->add($section);
print($form);

include("foot.inc");
