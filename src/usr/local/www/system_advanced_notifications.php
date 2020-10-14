<?php
/*
 * system_advanced_notifications.php
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
##|*IDENT=page-system-advanced-notifications
##|*NAME=System: Advanced: Notifications
##|*DESCR=Allow access to the 'System: Advanced: Notifications' page.
##|*MATCH=system_advanced_notifications.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("notices.inc");
require_once("pfsense-utils.inc");

global $smtp_authentication_mechanisms;
global $pushover_sounds;
$pconfig = array();
init_config_arr(array('notifications', 'certexpire'));
init_config_arr(array('notifications', 'smtp'));
init_config_arr(array('notifications', 'telegram'));
init_config_arr(array('notifications', 'pushover'));

// General Settings
$pconfig['cert_enable_notify'] = ($config['notifications']['certexpire']['enable'] != "disabled");
if ($config['notifications']['certexpire']['expiredays']) {
	$pconfig['certexpiredays'] = $config['notifications']['certexpire']['expiredays'];
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
$pconfig['sslvalidate'] = ($config['notifications']['smtp']['sslvalidate'] != "disabled");
if (!empty($config['notifications']['smtp']['timeout'])) {
	$pconfig['smtptimeout'] = $config['notifications']['smtp']['timeout'];
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

// Telegram
$pconfig['enable_telegram'] = isset($config['notifications']['telegram']['enabled']);
if ($config['notifications']['telegram']['api']) {
	$pconfig['api'] = $config['notifications']['telegram']['api'];
}
if ($config['notifications']['telegram']['chatid']) {
	$pconfig['chatid'] = $config['notifications']['telegram']['chatid'];
}
// Pushover
$pconfig['enable_pushover'] = isset($config['notifications']['pushover']['enabled']);
if ($config['notifications']['pushover']['apikey']) {
	$pconfig['pushoverapikey'] = $config['notifications']['pushover']['apikey'];
}
if ($config['notifications']['pushover']['userkey']) {
	$pconfig['pushoveruserkey'] = $config['notifications']['pushover']['userkey'];
}
if ($config['notifications']['pushover']['sound']) {
	$pconfig['pushoversound'] = $config['notifications']['pushover']['sound'];
}
if ($config['notifications']['pushover']['priority']) {
	$pconfig['pushoverpriority'] = $config['notifications']['pushover']['priority'];
}
$pconfig['pushoverretry'] = ($config['notifications']['pushover']['retry']) ? $config['notifications']['pushover']['retry'] : 60;
$pconfig['pushoverexpire'] = ($config['notifications']['pushover']['expire']) ? $config['notifications']['pushover']['expire'] : 300;
if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	$testsmtp = isset($_POST['test-smtp']);
	$testtelegram = isset($_POST['test-telegram']);
	$testpushover = isset($_POST['test-pushover']);
	if (isset($_POST['save']) || $testsmtp || $testtelegram || $testpushover) {

		// General Settings
		$config['notifications']['certexpire']['enable'] = ($_POST['cert_enable_notify'] == "yes") ? "enabled" : "disabled";
		if (empty($_POST['certexpiredays']) ||
		    (is_numericint($_POST['certexpiredays']) && ($_POST['certexpiredays'] > 0))) {
			$config['notifications']['certexpire']['expiredays'] = $_POST['certexpiredays'];
		} else {
			$input_errors[] = gettext("Certificate Expiration Threshold must be a positive integer");
		}

		// SMTP
		if (empty($_POST['smtpipaddress']) && (($_POST['disable_smtp'] != "yes") || $testsmtp)) {
			$input_errors[] = gettext("Please enter valid E-Mail server address.");
		} else {
			$config['notifications']['smtp']['ipaddress'] = $_POST['smtpipaddress'];
		}

		if (!is_port($_POST['smtpport']) && ($_POST['disable_smtp'] != "yes")) {
			$input_errors[] = gettext("Please enter valid SMTP port of E-Mail server address.");
		} else {
			$config['notifications']['smtp']['port'] = $_POST['smtpport'];
		}

		if (isset($_POST['smtpssl'])) {
			$config['notifications']['smtp']['ssl'] = true;
		} else {
			unset($config['notifications']['smtp']['ssl']);
		}

		if (isset($_POST['sslvalidate'])) {
			$config['notifications']['smtp']['sslvalidate'] = "enabled";
		} else {
			$config['notifications']['smtp']['sslvalidate'] = "disabled";
		}

		if (!empty($_POST['smtptimeout']) && !is_numeric($_POST['smtptimeout'])) {
			$input_errors[] = gettext("Please enter valid connection timeout.");
		} else {
			$config['notifications']['smtp']['timeout'] = $_POST['smtptimeout'];
		}

		if (empty($_POST['smtpnotifyemailaddress']) && (($_POST['disable_smtp'] != "yes") || $testsmtp)) {
			$input_errors[] = gettext("Please enter valid notification E-Mail address.");
		} else {
			$config['notifications']['smtp']['notifyemailaddress'] = $_POST['smtpnotifyemailaddress'];
		}

		$config['notifications']['smtp']['username'] = $_POST['smtpusername'];

		if (strcmp($_POST['smtppassword'], DMYPWD)!= 0) {
			if ($_POST['smtppassword'] == $_POST['smtppassword_confirm']) {
				$config['notifications']['smtp']['password'] = $_POST['smtppassword'];
			} else {
				if ($_POST['disable_smtp'] != "yes") {
					// Bug #7129 - do not nag people about passwords mismatch when SMTP notifications are disabled
					$input_errors[] = gettext("SMTP passwords must match");
				}
			}
		}

		if (!array_key_exists($_POST['smtpauthmech'], $smtp_authentication_mechanisms)) {
			$input_errors[] = gettext("Please select valid authentication mechanism.");
		} else {
			$config['notifications']['smtp']['authentication_mechanism'] = $_POST['smtpauthmech'];
		}

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
		// Telegram
		$config['notifications']['telegram']['enabled'] = ($_POST['enable_telegram'] == "yes") ? true : false;
		$config['notifications']['telegram']['api'] = $_POST['api'];
		$config['notifications']['telegram']['chatid'] = $_POST['chatid'];

		if (preg_replace("/[^a-zA-Z0-9_:\-]/", "", $config['notifications']['telegram']['api']) !== $config['notifications']['telegram']['api']) {
			$input_errors[] = gettext("The only special characters permitted in the Telegram API string are _, - and :");
		}
		if (preg_replace("/[^a-zA-Z0-9@_\-]/", "", $config['notifications']['telegram']['chatid']) !== $config['notifications']['telegram']['chatid']) {
			$input_errors[] = gettext("The Chat ID can only contain @, _ or - as special characters");
		}

		// Pushover
		$config['notifications']['pushover']['enabled'] = ($_POST['enable_pushover'] == "yes") ? true : false;
		$config['notifications']['pushover']['apikey'] = $_POST['pushoverapikey'];
		$config['notifications']['pushover']['userkey'] = $_POST['pushoveruserkey'];

		if (preg_replace("/[^A-Za-z0-9]/", "", $config['notifications']['pushover']['apikey']) !== $config['notifications']['pushover']['apikey']) {
			$input_errors[] = gettext("API keys are case-sensitive, 30 characters long, and can only use the character set A-Z,a-z,0-9");
		}
		if (preg_replace("/[^A-Za-z0-9]/", "", $config['notifications']['pushover']['userkey']) !== $config['notifications']['pushover']['userkey']) {
			$input_errors[] = gettext("User keys are case-sensitive, 30 characters long, and can only use the character set A-Z,a-z,0-9");
		}
		if (!array_key_exists($_POST['pushoversound'], $pushover_sounds)) {
			$input_errors[] = gettext("Please select a valid Pushover notification sound.");
		} else {
			$config['notifications']['pushover']['sound'] = $_POST['pushoversound'];
		}
		if (!array_key_exists($_POST['pushoverpriority'], array_fill_keys(range(-2,2),''))) {
			$input_errors[] = gettext("Please select a valid Pushover message priority.");
		} else {
			$config['notifications']['pushover']['priority'] = $_POST['pushoverpriority'];
		}
		if (!empty($_POST['pushoverretry']) && !is_numeric($_POST['pushoverretry'])) {
			$input_errors[] = gettext("Please enter valid notification retry interval in seconds.");
		} elseif (!empty($_POST['pushoverretry']) && ($_POST['pushoverretry'] < 30)) {
			$input_errors[] = gettext("Please enter valid notification retry interval with a minimum value of 30.");
		} else {
			$config['notifications']['pushover']['retry'] = $_POST['pushoverretry'];
		}
		if (!empty($_POST['pushoverexpire']) && !is_numeric($_POST['pushoverexpire'])) {
			$input_errors[] = gettext("Please enter valid notification expiration time in seconds.");
		} elseif (!empty($_POST['pushoverexpire']) && ($_POST['pushoverretry'] > 10800)) {
			$input_errors[] = gettext("Please enter valid notification expiration time with maximum value of 10800 (3 hours).");
		} else {
			$config['notifications']['pushover']['expire'] = $_POST['pushoverexpire'];
		}

		if (!$input_errors && !$testsmtp && !$testtelegram && !$testpushover) {
			write_config();

			pfSenseHeader("system_advanced_notifications.php");
			return;
		}

	}

	if ($testsmtp) {
		// Send test message via smtp
		if (file_exists("/var/db/notices_lastmsg.txt")) {
			unlink("/var/db/notices_lastmsg.txt");
		}
		$test_result = notify_via_smtp(sprintf(gettext("This is a test message from %s. It is safe to ignore this message."), $g['product_name']), true);
		if (empty($test_result)) {
			$test_result = gettext("SMTP testing e-mail successfully sent");
			$test_class = 'success';
		} else {
			$test_class = 'danger';
		}
	}
	if ($testtelegram) {
		// Send test message via telegram
		$test_result = notify_via_telegram(sprintf(gettext("This is a Telegram test message from %s. It is safe to ignore this message."), $g['product_name']), true);
		if (empty($test_result)) {
			$test_result = gettext("Telegram testing message successfully sent");
			$test_class = 'success';
		} else {
			$test_class = 'danger';
		}
	}
	if ($testpushover) {
		// Send test message via pushover
		$test_result = notify_via_pushover(sprintf(gettext("This is a Pushover test message from %s. It is safe to ignore this message."), $g['product_name']), true);
		if (empty($test_result)) {
			$test_result = gettext("Pushover testing message successfully sent");
			$test_class = 'success';
		} else {
			$test_class = 'danger';
		}
	}
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
$section->addInput(new Form_Input(
	'certexpiredays',
	'Certificate Expiration Threshold',
	'number',
	$pconfig['certexpiredays']
))->setAttribute('placeholder', $g['default_cert_expiredays'])
  ->setHelp('The number of days at which a certificate lifetime is considered to ' .
	'be expiring soon and worthy of notification. Default is 30 days.');

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
	isset($pconfig['smtpssl'])
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
