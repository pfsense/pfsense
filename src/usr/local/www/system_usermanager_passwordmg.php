<?php
/*
 * system_usermanager_passwordmg.php
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
##|*IDENT=page-system-usermanager-passwordmg
##|*NAME=System: User Password Manager
##|*DESCR=Allow access to the 'System: User Password Manager' page.
##|*MATCH=system_usermanager_passwordmg.php*
##|-PRIV

require_once("auth.inc");
require_once("certs.inc");
require_once("guiconfig.inc");

$logging_level = LOG_WARNING;
$logging_prefix = gettext("Local User Database");

$pgtitle = array(gettext("System"), gettext("User Password Manager"));

$password_extra_help = get_validate_password_hints();

unset($input_errors);
$input_errors = [];

phpsession_begin();
$guiuser = getUserEntry($_SESSION['Username']);
$read_only = (is_array($guiuser) && userHasPrivilege($guiuser, "user-config-readonly"));
/* Determine if the current user authenticated locally */
$islocal = false;
foreach (config_get_path('system/user', []) as $user) {
	if ($user['name'] == $_SESSION['Username']) {
		$islocal = true;
	}
}
phpsession_end(true);

if (isset($_POST['save'])) {
	/* Input validation */
	if (!$islocal) {
		$input_errors[] = gettext("This page cannot change passwords for non-local users.");
	}
	if ($read_only) {
		$input_errors[] = gettext("The current user is read-only and cannot change the configuration.");
	}

	$reqdfields = explode(" ", "passwordfld1");
	$reqdfieldsn = array(gettext("Password"));
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	$user_item_config = [
		'idx' => $userindex[$_SESSION['Username']],
		'item' => isset($userindex[$_SESSION['Username']]) ? config_get_path("system/user/{$userindex[$_SESSION['Username']]}") : null
	];
	$userent = &$user_item_config['item'];
	if ($_POST['passwordfld1'] != $_POST['passwordfld2']) {
		$input_errors[] = gettext("The passwords do not match.");
	}
	if (!is_array($userent)) {
		$input_errors[] = gettext("Could not locate this user.");
	}

	$input_errors = array_merge($input_errors, validate_password($_SESSION['Username'], $_POST['passwordfld1']));

	if (!$input_errors) {
		phpsession_begin();
		// Save changes to the current user
		local_user_set_password($user_item_config, $_POST['passwordfld1']);
		local_user_set($userent);
		$savemsg = sprintf(gettext("Password changed for user: %s"), $userent['name']);
		phpsession_end(true);

		write_config($savemsg);
		syslog($logging_level, "{$logging_prefix}: {$savemsg}");
	}
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if (!$islocal) {
	print_info_box(gettext("This page cannot change passwords for non-local users."), 'danger');
}
if ($read_only) {
	print_info_box(gettext("The current user is read-only and cannot change the configuration."), 'danger');
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
$tab_array[] = array(gettext("Users"), false, "system_usermanager.php");
$tab_array[] = array(gettext("Groups"), false, "system_groupmanager.php");
$tab_array[] = array(gettext("Settings"), false, "system_usermanager_settings.php");
$tab_array[] = array(gettext("Change Password"), true, "system_usermanager_passwordmg.php");
$tab_array[] = array(gettext("Authentication Servers"), false, "system_authservers.php");
display_top_tabs($tab_array);

$form = new Form();

$section = new Form_Section('Change Password');

$section->addInput(new Form_StaticText(
	'',
	'This page changes the password for the current user in the local configuration. ' .
	'This affects all services which utilize the Local Authentication database ' .
	'(User Manager).' .
	'<br/><br/>' .
	'This page cannot change passwords for users from other authentication ' .
	'sources such as LDAP or RADIUS.'
));

/* Only display password change fields for local users. */
if ($islocal &&
    !$read_only) {
	$section->addInput(new Form_StaticText(
		'Database',
		'Local Authentication'
	));

	$section->addInput(new Form_StaticText(
		'Username',
		$_SESSION['Username']
	));

	$section->addInput(new Form_Input(
		'passwordfld1',
		'*Password',
		'password',
		null,
		['autocomplete' => 'new-password']
	))->setHelp('Enter a new password.' .
			'%1$s%1$s' .
			'Hints:%1$s' .
			' %2$s', '<br/>', $password_extra_help);

	$section->addInput(new Form_Input(
		'passwordfld2',
		'*Confirmation',
		'password',
		null,
		['autocomplete' => 'new-password']
	))->setHelp('Type the new password again for confirmation.');
}

$form->add($section);
print($form);

include("foot.inc");
