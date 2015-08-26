<?php
/* $Id$ */
/*
	system_advanced_notifications.php
	part of pfSense
	Copyright (C) 2009 Scott Ullrich <sullrich@gmail.com>
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP

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
	pfSense_MODULE:	system
*/

##|+PRIV
##|*IDENT=page-system-advanced-notifications
##|*NAME=System: Advanced: Notifications page
##|*DESCR=Allow access to the 'System: Advanced: Notifications' page.
##|*MATCH=system_advanced_notifications.php*
##|-PRIV

require("guiconfig.inc");
require_once("notices.inc");

// Growl
$pconfig['disable_growl'] = isset($config['notifications']['growl']['disable']);
if ($config['notifications']['growl']['password']) {
	$pconfig['password'] = $config['notifications']['growl']['password'];
}
if ($config['notifications']['growl']['ipaddress']) {
	$pconfig['ipaddress'] = $config['notifications']['growl']['ipaddress'];
}

if ($config['notifications']['growl']['notification_name']) {
	$pconfig['notification_name'] = $config['notifications']['growl']['notification_name'];
} else {
  $pconfig['notification_name'] = "{$g['product_name']} growl alert";
}

if ($config['notifications']['growl']['name']) {
	$pconfig['name'] = $config['notifications']['growl']['name'];
} else {
  $pconfig['name'] = 'PHP-Growl';
}


// SMTP
$pconfig['disable_smtp'] = isset($config['notifications']['smtp']['disable']);
if ($config['notifications']['smtp']['ipaddress']) {
	$pconfig['smtpipaddress'] = $config['notifications']['smtp']['ipaddress'];
}
if ($config['notifications']['smtp']['port']) {
	$pconfig['smtpport'] = $config['notifications']['smtp']['port'];
}
if (isset($config['notifications']['smtp']['ssl'])) {
	$pconfig['smtpssl'] = true;
}
if (isset($config['notifications']['smtp']['tls'])) {
	$pconfig['smtptls'] = true;
}
if ($config['notifications']['smtp']['notifyemailaddress']) {
	$pconfig['smtpnotifyemailaddress'] = $config['notifications']['smtp']['notifyemailaddress'];
}
if ($config['notifications']['smtp']['username']) {
	$pconfig['smtpusername'] = $config['notifications']['smtp']['username'];
}
if ($config['notifications']['smtp']['password']) {
	$pconfig['smtppassword'] = $config['notifications']['smtp']['password'];
}
if ($config['notifications']['smtp']['authentication_mechanism']) {
	$pconfig['smtpauthmech'] = $config['notifications']['smtp']['authentication_mechanism'];
}
if ($config['notifications']['smtp']['fromaddress']) {
	$pconfig['smtpfromaddress'] = $config['notifications']['smtp']['fromaddress'];
}

// System Sounds
$pconfig['disablebeep'] = isset($config['system']['disablebeep']);

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	if (isset($_POST['save'])) {

		// Growl
		$config['notifications']['growl']['ipaddress'] = $_POST['ip-address'];
		$config['notifications']['growl']['password'] = $_POST['password'];
		$config['notifications']['growl']['name'] = $_POST['registration-name'];
		$config['notifications']['growl']['notification_name'] = $_POST['notification-name'];

		if ($_POST['disable_growl'] == "yes") {
			$config['notifications']['growl']['disable'] = true;
		} else {
			unset($config['notifications']['growl']['disable']);
		}

		// SMTP
		$config['notifications']['smtp']['ipaddress'] = $_POST['smtpipaddress'];
		$config['notifications']['smtp']['port'] = $_POST['smtpport'];
		if (isset($_POST['smtpssl'])) {
			$config['notifications']['smtp']['ssl'] = true;
		} else {
			unset($config['notifications']['smtp']['ssl']);
		}
		
		if (isset($_POST['smtptls'])) {
			$config['notifications']['smtp']['tls'] = true;
		} else {
			unset($config['notifications']['smtp']['tls']);
		}
		
		$config['notifications']['smtp']['notifyemailaddress'] = $_POST['smtpnotifyemailaddress'];
		$config['notifications']['smtp']['username'] = $_POST['smtpusername'];
		$config['notifications']['smtp']['password'] = $_POST['smtppassword'];
		$config['notifications']['smtp']['authentication_mechanism'] = $_POST['smtpauthmech'];
		$config['notifications']['smtp']['fromaddress'] = $_POST['smtpfromaddress'];

		if ($_POST['disable_smtp'] == "yes") {
			$config['notifications']['smtp']['disable'] = true;
		} else {
			unset($config['notifications']['smtp']['disable']);
		}

		// System Sounds
		if ($_POST['disablebeep'] == "yes") {
			$config['system']['disablebeep'] = true;
		} else {
			unset($config['system']['disablebeep']);
		}

		write_config();
		pfSenseHeader("system_advanced_notifications.php");
		return;

	}

	if (isset($_POST['test-growl'])) {
		// Send test message via growl
		if ($config['notifications']['growl']['ipaddress'] &&
		    $config['notifications']['growl']['password'] = $_POST['password']) {
			unlink_if_exists($g['vardb_path'] . "/growlnotices_lastmsg.txt");
			register_via_growl();
			notify_via_growl(sprintf(gettext("This is a test message from %s.  It is safe to ignore this message."), $g['product_name']), true);
		}
	}

	if (isset($_POST['test-smtp'])) {
		// Send test message via smtp
		if (file_exists("/var/db/notices_lastmsg.txt")) {
			unlink("/var/db/notices_lastmsg.txt");
		}
		$savemsg = notify_via_smtp(sprintf(gettext("This is a test message from %s.  It is safe to ignore this message."), $g['product_name']), true);
	}
}

$pgtitle = array(gettext("System"), gettext("Advanced: Notifications"));
include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);

$tab_array = array();
$tab_array[] = array(gettext("Admin Access"), false, "system_advanced_admin.php");
$tab_array[] = array(gettext("Firewall / NAT"), false, "system_advanced_firewall.php");
$tab_array[] = array(gettext("Networking"), false, "system_advanced_network.php");
$tab_array[] = array(gettext("Miscellaneous"), false, "system_advanced_misc.php");
$tab_array[] = array(gettext("System Tunables"), false, "system_advanced_sysctl.php");
$tab_array[] = array(gettext("Notifications"), true, "system_advanced_notifications.php");
display_top_tabs($tab_array);

?><div id="container"><?php

require('classes/Form.class.php');
$form = new Form;
$section = new Form_Section('Growl');

$section->addInput(new Form_Checkbox(
	'disable-growl',
	'Disable Growl',
	'Disable Growl Notifications',
	$pconfig['disable_growl']
))->setHelp('Check this option to disable growl notifications but preserve the '.
	'settings below.');

$section->addInput(new Form_Input(
	'registration-name',
	'Registration Name',
	'text',
	$pconfig['name'],
	['placeholder' => 'PHP-Growl']
))->setHelp('Enter the name to register with the Growl server.');

$section->addInput(new Form_Input(
	'notification-name',
	'Notification Name',
	'text',
	$pconfig['notification_name'],
	['placeholder' => $g["product_name"].' growl alert']

))->setHelp('Enter a name for the Growl notifications');

$section->addInput(new Form_Input(
	'ip-address',
	'IP Address',
	'text',
	$pconfig['ipaddress']
))->setHelp('This is the IP address that you would like to send growl '.
	'notifications to.');

$section->addInput(new Form_Input(
	'password',
	'Password',
	'text',
	$pconfig['password']
))->setHelp('Enter the password of the remote growl notification device.');

$section->addInput(new Form_Input(
	'test-growl',
	'Test Growl',
	'submit',
	'Test Growl settings'
))->setHelp('A test notification will be sent even if the service is '.
	'marked as disabled.');

$form->add($section);
$section = new Form_Section('E-Mail');

$section->addInput(new Form_Checkbox(
	'disable-smtp',
	'Disable SMTP',
	'Disable SMTP Notifications',
	$pconfig['disable_smtp']
))->setHelp('Check this option to disable SMTP notifications but preserve the '.
	'settings below. Some other mechanisms, such as packages, may need these settings '.
	'in place to function.');

$section->addInput(new Form_Input(
	'e-mail-server',
	'E-Mail server',
	'text',
	$pconfig['smtpipaddress']
))->setHelp('This is the FQDN or IP address of the SMTP E-Mail server to '.
	'which notifications will be sent.');

$section->addInput(new Form_Input(
	'smtp-port-of-e-mail-server',
	'SMTP Port of E-Mail server',
	'number',
	$pconfig['smtpport']
))->setHelp('This is the port of the SMTP E-Mail server, typically 25, 587 '.
	'(submission) or 465 (smtps)');

$group = new Form_Group('Secure SMTP Connection');
$group->add(new Form_Checkbox(
	'enable-ssl-tls',
	'Enable SSL/TLS',
	'Enable SMTP over SSL/TLS',
	isset($pconfig['smtpssl'])
));

$group->add(new Form_Checkbox(
	'secure-starttls',
	'Secure STARTTLS',
	'Enable STARTTLS',
	isset($pconfig['smtptls'])
));

$section->add($group);

$section->addInput(new Form_Input(
	'from-e-mail-address',
	'From e-mail address',
	'text',
	$pconfig['smtpfromaddress']
))->setHelp('This is the e-mail address that will appear in the from field.');

$section->addInput(new Form_Input(
	'notification-e-mail-address',
	'Notification E-Mail address',
	'text',
	$pconfig['smtpnotifyemailaddress']
))->setHelp('Enter the e-mail address that you would like email '.
	'notifications sent to.');

$section->addInput(new Form_Input(
	'notification-e-mail-auth-username-optional-',
	'Notification E-Mail auth username (optional)',
	'text',
	$pconfig['smtpusername']
))->setHelp('Enter the e-mail address username for SMTP authentication.');

$section->addInput(new Form_Input(
	'notification-e-mail-auth-password',
	'Notification E-Mail auth password',
	'password',
	$pconfig['smtppassword']
))->setHelp('Enter the e-mail address password for SMTP authentication.');

$section->addInput(new Form_Input(
	'test-smtp',
	'Test SMTP',
	'submit',
	'Test SMTP settings'
))->setHelp('A test notification will be sent even if the service is '.
	'marked as disabled.');

$section->addInput(new Form_Checkbox(
	'startup-shutdown-sound',
	'Startup/Shutdown Sound',
	'Disable the startup/shutdown beep',
	$pconfig['disablebeep']
))->setHelp('When this is checked, startup and shutdown sounds will no longer '.
	'play.');

$form->add($section);
print $form;

include("foot.inc");