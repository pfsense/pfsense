<?php
/*
 * system_advanced_notifications.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-system-advanced-notifications
##|*NAME=System: Advanced: Notifications
##|*DESCR=Allow access to the 'System: Advanced: Notifications' page.
##|*MATCH=system_advanced_notifications.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("notices.inc");
require_once("pfsense-utils.inc");
require_once("system_advanced_notifications.inc");

global $smtp_authentication_mechanisms;
global $pushover_sounds;

$pconfig = getAdvancedNotifications();

if ($_POST) {
	$rv = saveAdvancedNotifications($_POST);

	$pconfig = $rv['pconfig'];
	$test_result = $rv['test_result'];
	$test_class = $rv['test_class'];
	$input_errors = $rv['input_errors'];
}

$pgtitle = array(gettext("System"), gettext("Advanced"), gettext("Notifications"));
$pglinks = array("", "system_advanced_admin.php", "@self");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($test_result) {
	print_info_box($test_result, $test_class);
}

$tab_array = array();
$tab_array[] = array(gettext("Admin Access"), false, "system_advanced_admin.php");
$tab_array[] = array(htmlspecialchars(gettext("Firewall & NAT")), false, "system_advanced_firewall.php");
$tab_array[] = array(gettext("Networking"), false, "system_advanced_network.php");
$tab_array[] = array(gettext("Miscellaneous"), false, "system_advanced_misc.php");
$tab_array[] = array(gettext("System Tunables"), false, "system_advanced_sysctl.php");
$tab_array[] = array(gettext("Notifications"), true, "system_advanced_notifications.php");
display_top_tabs($tab_array);

$form = new Form;

$section = new Form_Section('General Settings');

$section->addInput(new Form_Checkbox(
	'cert_enable_notify',
	'Certificate Expiration',
	'Enable daily notifications of expired and soon-to-expire certificates',
	$pconfig['cert_enable_notify']
))->setHelp('When enabled, the firewall will check CA and Certificate expiration ' .
	'times daily and file notices when expired or soon-to-expire ' .
	'entries are detected.');
$section->addInput(new Form_Checkbox(
    'revoked_cert_ignore_notify',
    'Ignore for Revoked Certificate',
    'Ignore notifications for revoked certificates',
    $pconfig['revoked_cert_ignore_notify']
))->setHelp('When enabled, the firewall will NOT check expiring for revoked (at least once) certificates');
$section->addInput(new Form_Input(
	'certexpiredays',
	'Certificate Expiration Threshold',
	'number',
	$pconfig['certexpiredays']
))->setAttribute('placeholder', $g['default_cert_expiredays'])
  ->setHelp('The number of days at which a certificate lifetime is considered to ' .
	'be expiring soon and worthy of notification. Default is %d days.', $g['default_cert_expiredays']);

$form->add($section);


$section = new Form_Section('E-Mail');

$section->addInput(new Form_Checkbox(
	'disable_smtp',
	'Disable SMTP',
	'Disable SMTP Notifications',
	$pconfig['disable_smtp']
))->setHelp('Check this option to disable SMTP notifications but preserve the '.
	'settings below. Some other mechanisms, such as packages, may need these settings '.
	'in place to function.');

$section->addInput(new Form_Input(
	'smtpipaddress',
	'E-Mail server',
	'text',
	$pconfig['smtpipaddress']
))->setHelp('This is the FQDN or IP address of the SMTP E-Mail server to '.
	'which notifications will be sent.');

$section->addInput(new Form_Input(
	'smtpport',
	'SMTP Port of E-Mail server',
	'number',
	$pconfig['smtpport']
))->setHelp('This is the port of the SMTP E-Mail server, typically 25, 587 '.
	'(submission) or 465 (smtps).');

$section->addInput(new Form_Input(
	'smtptimeout',
	'Connection timeout to E-Mail server',
	'number',
	$pconfig['smtptimeout']
))->setHelp('This is how many seconds it will wait for the SMTP server to connect. Default is 20s.');

$group = new Form_Group('Secure SMTP Connection');
$group->add(new Form_Checkbox(
	'smtpssl',
	'Enable SSL/TLS',
	'Enable SMTP over SSL/TLS',
	$pconfig['smtpssl']
));

$section->add($group);

$section->addInput(new Form_Checkbox(
	'sslvalidate',
	'Validate SSL/TLS',
	'Validate the SSL/TLS certificate presented by the server',
	$pconfig['sslvalidate']
))->setHelp('When disabled, the server certificate will not be validated. ' .
	'Encryption will still be used if available, but the identity of the server will not be confirmed.');

$section->addInput(new Form_Input(
	'smtpfromaddress',
	'From e-mail address',
	'text',
	$pconfig['smtpfromaddress']
))->setHelp('This is the e-mail address that will appear in the from field.');

$section->addInput(new Form_Input(
	'smtpnotifyemailaddress',
	'Notification E-Mail address',
	'text',
	$pconfig['smtpnotifyemailaddress']
))->setHelp('Enter the e-mail address to send email notifications to.');

// This name prevents the browser from auto-filling the field. We change it on submit
$section->addInput(new Form_Input(
	'smtpusername',
	'Notification E-Mail auth username (optional)',
	'text',
	$pconfig['smtpusername'],
	['autocomplete' => 'new-password']
))->setHelp('Enter the e-mail address username for SMTP authentication.');

$section->addPassword(new Form_Input(
	'smtppassword',
	'Notification E-Mail auth password',
	'password',
	$pconfig['smtppassword'],
	['autocomplete' => 'new-password']
))->setHelp('Enter the e-mail account password for SMTP authentication.');

$section->addInput(new Form_Select(
	'smtpauthmech',
	'Notification E-Mail auth mechanism',
	$pconfig['smtpauthmech'],
	$smtp_authentication_mechanisms
))->setHelp('Select the authentication mechanism used by the SMTP server. Most work with PLAIN, some servers like Exchange or Office365 might require LOGIN. ');

$section->addInput(new Form_Button(
	'test-smtp',
	'Test SMTP Settings',
	null,
	'fa-envelope'
))->addClass('btn-info')->setHelp('A test notification will be sent even if the service is '.
	'marked as disabled.  The last SAVED values will be used, not necessarily the values entered here.');

$form->add($section);

$section = new Form_Section('Sounds');

$section->addInput(new Form_Checkbox(
	'disablebeep',
	'Startup/Shutdown Sound',
	'Disable the startup/shutdown beep',
	$pconfig['disablebeep']
))->setHelp('When this is checked, startup and shutdown sounds will no longer '.
	'play.');

$form->add($section);

$section = new Form_Section('Telegram');

$section->addInput(new Form_Checkbox(
	'enable_telegram',
	'Enable Telegram',
	'Enable Telegram Notifications',
	$pconfig['enable_telegram']
	))->setHelp('Check this option to enable Telegram notifications. <br>You will need a Telegram Bot and its associated API key. <a href="https://core.telegram.org/bots#creating-a-new-bot" target="_blank">Instructions here.</a>');

$section->addInput(new Form_Input(
	'api',
	'API Key',
	'text',
	$pconfig['api'],
	['placeholder' => '123456789:ABCDEabcde_FGHIJfghijKLMNOklmnoPQRST']
))->setHelp('Enter the Bot API key required to authenticate with the Telegram API server.');

$section->addInput(new Form_Input(
	'chatid',
	'Chat ID',
	'text',
	$pconfig['chatid'],
	['placeholder' => '123456789']

))->setHelp('Enter the chat ID number (private) or channel @username (public) that will be used to send the notifications to.');

$section->addInput(new Form_Button(
	'test-telegram',
	'Test Telegram Settings',
	null,
	'fa-send'
))->addClass('btn-info')->setHelp('A test notification will be sent even if the service is '.
	'not enabled.  The last SAVED values will be used, not necessarily the values displayed here.');

	$form->add($section);

	$section = new Form_Section('Pushover');

	$section->addInput(new Form_Checkbox(
		'enable_pushover',
		'Enable Pushover',
		'Enable Pushover Notifications',
		$pconfig['enable_pushover']
		))->setHelp('Check this option to enable Pushover notifications. <br>An API key will need to be created and entered along with the Pushover account user key. <a href="https://pushover.net/api#registration" target="_blank">API documentation.</a>');

	$section->addInput(new Form_Input(
		'pushoverapikey',
		'API Key',
		'text',
		$pconfig['pushoverapikey'],
		['placeholder' => 'azGDORePK8gMaC0QOYAMyEEuzJnyUi']
	))->setHelp('Enter the API key required to authenticate with the Pushover API server. <a href="https://pushover.net/apps/build" target="_blank">Create API key here.</a>');

	$section->addInput(new Form_Input(
		'pushoveruserkey',
		'User Key',
		'text',
		$pconfig['pushoveruserkey'],
		['placeholder' => 'uQiRzpo4DXghDmr9QzzfQu27cmVRsG']

	))->setHelp('Enter user key of the Pushover account');

	$section->addInput(new Form_Select(
		'pushoversound',
		'Notification Sound',
		$pconfig['pushoversound'],
		$pushover_sounds
	))->setHelp('Select notification sound. <a href="https://pushover.net/api#sounds" target="_blank">Notification Sound documentation with audio.</a>');

	$section->addInput(new Form_Select(
		'pushoverpriority',
		'Message Priority',
		$pconfig['pushoverpriority'],
		array(
			"0" => "Normal Priority",
			"-2" => "Lowest Priority - No sound or notification",
			"-1" => "Low Priority - No sound or vibration",
			"1" => "High Priority - Always play sound and vibrate",
			"2" => "Emergency Priority - Repeats notification until acknowledged",
		)
	))->setHelp('Select message priority. <a href="https://pushover.net/api#priority" target="_blank">Message Priority documentation.</a>');

	$section->addInput(new Form_Input(
		'pushoverretry',
		'Emergency Priority Notification Retry Interval',
		'number',
		$pconfig['pushoverretry']
	))->setHelp('This specifies how often (in seconds) the Pushover servers will send the same notification for Emergency Priority notifications until the notification is acknowledged. <br>This parameter must have a value of at least 30 seconds between retries. Default is 1 minute.');

	$section->addInput(new Form_Input(
		'pushoverexpire',
		'Emergency Priority Notification Expiration',
		'number',
		$pconfig['pushoverexpire']
	))->setHelp('This specifies how many seconds the notifications will continue to be retried (every retry seconds) for Emergency Priority notifications until the notification is acknowledged. <br>This parameter must have a maximum value of at most 10800 seconds (3 hours). Default is 5 minutes.');

	$section->addInput(new Form_Button(
		'test-pushover',
		'Test Pushover Settings',
		null,
		'fa-send'
	))->addClass('btn-info')->setHelp('A test notification will be sent even if the service is '.
		'not enabled.  The last SAVED values will be used, not necessarily the values displayed here.');

$form->add($section);
print($form);

include("foot.inc");
