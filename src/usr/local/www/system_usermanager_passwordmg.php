<?php
/*
 * system_usermanager_passwordmg.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
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

$pgtitle = array(gettext("System"), gettext("User Password"));

if (isset($_POST['save'])) {
	unset($input_errors);
	/* input validation */

	$reqdfields = explode(" ", "passwordfld1");
	$reqdfieldsn = array(gettext("Password"));
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($_POST['passwordfld1'] != $_POST['passwordfld2']) {
		$input_errors[] = gettext("The passwords do not match.");
	}
	if (!isset($config['system']['user'][$userindex[$_SESSION['Username']]]) ||
	    !is_array($config['system']['user'][$userindex[$_SESSION['Username']]])) {
		$input_errors[] = gettext("Could not locate this user.");
	}

	if (!$input_errors) {
		phpsession_begin();
		// all values are okay --> saving changes
		$userent =& $config['system']['user'][$userindex[$_SESSION['Username']]];
		local_user_set_password($userent, $_POST['passwordfld1']);
		local_user_set($userent);
		$savemsg = sprintf(gettext("Password changed for user: %s"), $userent['name']);
		unset($userent);
		phpsession_end(true);

		write_config($savemsg);
		syslog($logging_level, "{$logging_prefix}: {$savemsg}");
	}
}

phpsession_begin();

/* determine if user is not local to system */
$islocal = false;
foreach ($config['system']['user'] as $user) {
	if ($user['name'] == $_SESSION['Username']) {
		$islocal = true;
	}
}

phpsession_end(true);

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
$tab_array[] = array(gettext("User Password"), true, "system_usermanager_passwordmg.php");
$tab_array[] = array(gettext("Groups"), false, "system_groupmanager.php");
$tab_array[] = array(gettext("Settings"), false, "system_usermanager_settings.php");
$tab_array[] = array(gettext("Authentication Servers"), false, "system_authservers.php");
display_top_tabs($tab_array);

if ($islocal == false) {
	echo gettext("The password cannot be changed for a non-local user.");
	include("foot.inc");
	exit;
}

$form = new Form();

$section = new Form_Section('Update Password');

$section->addInput(new Form_Input(
	'passwordfld1',
	'*Password',
	'password',
	null,
	['autocomplete' => 'new-password']
));

$section->addInput(new Form_Input(
	'passwordfld2',
	'*Confirmation',
	'password',
	null,
	['autocomplete' => 'new-password']
))->setHelp('Select a new password');

$form->add($section);
print($form);

include("foot.inc");
