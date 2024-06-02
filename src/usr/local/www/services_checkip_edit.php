<?php
/*
 * services_checkip_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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

config_init_path('checkipservices/checkipservice');

if (is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

$this_checkip_config = isset($id) ? config_get_path("checkipservices/checkipservice/{$id}") : null;
if ($this_checkip_config) {
	$pconfig['enable'] = isset($this_checkip_config['enable']);
	$pconfig['name'] = $this_checkip_config['name'];
	$pconfig['url'] = $this_checkip_config['url'];
	$pconfig['username'] = $this_checkip_config['username'];
	$pconfig['password'] = $this_checkip_config['password'];
	$pconfig['verifysslpeer'] = isset($this_checkip_config['verifysslpeer']);
	$pconfig['curl_proxy'] = isset($this_checkip_config['curl_proxy']);
	$pconfig['descr'] = $this_checkip_config['descr'];
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
			$checkip['password'] = $this_checkip_config['password'];;
		}

		$checkip['verifysslpeer'] = $_POST['verifysslpeer'] ? true : false;
		$checkip['curl_proxy'] = $_POST['curl_proxy'] ? true : false;
		$checkip['descr'] = $_POST['descr'];

		if ($this_checkip_config) {
			config_set_path("checkipservices/checkipservice/{$id}", $checkip);
		} else {
			config_set_path('checkipservices/checkipservice/', $checkip);
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
	'Verify SSL/TLS Peer',
	'Verify SSL/TLS Peer',
	$pconfig['verifysslpeer']
));

if (!empty(config_get_path('system/proxyurl'))) {
	$section->addInput(new Form_Checkbox(
		'curl_proxy',
		'Use Proxy',
		'Use Proxy for Check IP server connection',
		$pconfig['curl_proxy'],
	))->setHelp('Use proxy configured under System > Advanced, on the Miscellaneous tab.');
}

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

if ($this_checkip_config) {
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
