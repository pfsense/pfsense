<?php
/*
 * diag_authentication.php
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
##|*IDENT=page-diagnostics-authentication
##|*NAME=Diagnostics: Authentication
##|*DESCR=Allow access to the 'Diagnostics: Authentication' page.
##|*MATCH=diag_authentication.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("auth.inc");

if ($_POST) {
	$pconfig = $_POST;
	unset($input_errors);

	$authcfg = auth_get_authserver($_POST['authmode']);
	if (!$authcfg) {
		$input_errors[] =  sprintf(gettext('%s is not a valid authentication server'), $_POST['authmode']);
	}

	if (empty($_POST['username'])) {
		$input_errors[] = gettext("A username and password must be specified.");
	}

	if (!$input_errors) {
		$attributes = array();
		if (authenticate_user($_POST['username'], $_POST['password'], $authcfg, $attributes)) {
			$savemsg = sprintf(gettext('User %s authenticated successfully.'), $_POST['username']);
			$groups = getUserGroups($_POST['username'], $authcfg, $attributes);
			$savemsg .= "&nbsp;" . gettext("This user is a member of groups") . ": <br /><br />";
			$savemsg .= "<ul>";
			foreach ($groups as $group) {
				$savemsg .= "<li>" . "{$group} " . "</li>";
			}
			$savemsg .= "</ul>";

		} else {
			$input_errors[] = gettext("Authentication failed.");
		}
	}
} else {
	if (isset($config['system']['webgui']['authmode'])) {
		$pconfig['authmode'] = $config['system']['webgui']['authmode'];
	} else {
		$pconfig['authmode'] = "Local Database";
	}
}

$pgtitle = array(gettext("Diagnostics"), gettext("Authentication"));
$shortcut_section = "authentication";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success', false);
}

$form = new Form(false);

$section = new Form_Section('Authentication Test');

foreach (auth_get_authserver_list() as $key => $auth_server) {
	$serverlist[$key] = $auth_server['name'];
}

$section->addInput(new Form_Select(
	'authmode',
	'*Authentication Server',
	$pconfig['authmode'],
	$serverlist
))->setHelp('Select the authentication server to test against.');

$section->addInput(new Form_Input(
	'username',
	'*Username',
	'text',
	$pconfig['username'],
	['placeholder' => 'Username', 'autocomplete' => 'new-password']
));

$section->addInput(new Form_Input(
	'password',
	'*Password',
	'password',
	$pconfig['password'],
	['placeholder' => 'Password', 'autocomplete' => 'new-password']
));

$form->add($section);

$form->addGlobal(new Form_Button(
	'Submit',
	'Test',
	null,
	'fa-wrench'
))->addClass('btn-primary');

print $form;

include("foot.inc");
