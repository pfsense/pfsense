<?php
/* $Id$ */
/*
	system_usermanager_settings.php

	part of pfSense (https://www.pfsense.org/)

	Copyright (C) 2007 Scott Ullrich <sullrich@gmail.com>
	Copyright (C) 2007 Bill Marquette <bill.marquette@gmail.com>
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
/*
	pfSense_MODULE:	auth
*/

##|+PRIV
##|*IDENT=page-system-usermanager-settings
##|*NAME=System: User Manager: settings page
##|*DESCR=Allow access to the 'System: User Manager: settings' page.
##|*MATCH=system_usermanager_settings.php*
##|-PRIV

require("guiconfig.inc");

$pconfig['session_timeout'] = &$config['system']['webgui']['session_timeout'];
$pconfig['authmode'] = &$config['system']['webgui']['authmode'];
$pconfig['backend'] = &$config['system']['webgui']['backend'];

// Page title for main admin
$pgtitle = array(gettext("System"), gettext("User manager settings"));

$save_and_test = false;
if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	if (isset($_POST['session_timeout'])) {
		$timeout = intval($_POST['session_timeout']);
		if ($timeout != "" && (!is_numeric($timeout) || $timeout <= 0)) {
			$input_errors[] = gettext("Session timeout must be an integer value.");
		}
	}

	if (!$input_errors) {
		if ($_POST['authmode'] != "local") {
			$authsrv = auth_get_authserver($_POST['authmode']);
			if ($_POST['savetest']) {
				if ($authsrv['type'] == "ldap") {
					$save_and_test = true;
				} else {
					$savemsg = gettext("The test was not performed because it is supported only for ldap based backends.");
				}
			}
		}


		if (isset($_POST['session_timeout']) && $_POST['session_timeout'] != "") {
			$config['system']['webgui']['session_timeout'] = intval($_POST['session_timeout']);
		} else {
			unset($config['system']['webgui']['session_timeout']);
		}

		if ($_POST['authmode']) {
			$config['system']['webgui']['authmode'] = $_POST['authmode'];
		} else {
			unset($config['system']['webgui']['authmode']);
		}

		write_config();

	}
}

include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);

if($save_and_test) {
	echo "<script>\n";
	echo "myRef = window.open('system_usermanager_settings_test.php?authserver=".$pconfig['authmode']."','mywin','left=20,top=20,width=700,height=550,toolbar=1,resizable=0');\n";
	echo "if (myRef==null || typeof(myRef)=='undefined') alert('" . gettext("Popup blocker detected.  Action aborted.") ."');\n";
	echo "</script>\n";
}

$tab_array = array();
$tab_array[] = array(gettext("Users"), false, "system_usermanager.php");
$tab_array[] = array(gettext("Groups"), false, "system_groupmanager.php");
$tab_array[] = array(gettext("Settings"), true, "system_usermanager_settings.php");
$tab_array[] = array(gettext("Servers"), false, "system_authservers.php");
display_top_tabs($tab_array);

/* Default to pfsense backend type if none is defined */
if(!$pconfig['backend'])
	$pconfig['backend'] = "pfsense";

require_once('classes/Form.class.php');
$form = new Form;

$section = new Form_Section('Settings');

$section->addInput(new Form_Input(
	'session_timeout',
	'Session timeout',
	'number',
	$pconfig['session_timeout']
))->setHelp('Time in minutes to expire idle management sessions. The default is 4 '.
	'hours (240 minutes).Enter 0 to never expire sessions. NOTE: This is a security '.
	'risk!');

$auth_servers = array();
foreach (auth_get_authserver_list() as $auth_server)
	$auth_servers[ $auth_server['name'] ] = $auth_server['name'];

$section->addInput(new Form_Select(
	'authmode',
	'Authentication Server',
	$pconfig['authmode'],
	$auth_servers
));

$form->addGlobal(new Form_Button(
	'savetest',
	'Save & Test'
))->removeClass('btn-primary')->addClass('btn-default');

$form->add($section);
print $form;

include("fend.inc");