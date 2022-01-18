<?php
/*
 * system_register.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
##|*IDENT=page-system-register
##|*NAME=System: Register
##|*DESCR=Allow access to the 'System: Register' page.
##|*MATCH=system_register.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("system_register.inc");

include("head.inc");

if  (strpos($g['product_label'], 'Plus') !== false) {
	header("Location: /");
}

// Possible helpblock messages
$pgtitle = array(gettext("System"), gettext("Register"));

$pghelp = sprintf(gettext('Thank you for choosing %1s%2s'), $g['product_label_html'], '<br /><br />');

$pghelp_notrequired = gettext("Your device does not require registration, we recognize it already." .
   " You may have already registered, or it may be a pre-registered Netgate appliance.");

$pghelp_offline = gettext("The registration system is not currently available. Please check your network connection and try again.");

$pghelp_ok = gettext('pfSense<sup>&reg;</sup> Community Edition software may be upgraded to pfSense<sup>&reg;</sup> Plus software by purchasing an upgrade from the Netgate store here: ' .
'<a href="https://redirects.netgate.com/upgrade">Upgrade</a>' .
'<br />Once your upgrade purchase is complete, you will receive an activation token by email.' .
'Please paste that token into the field below, then click “Register”.' .
'<br /><br>Registration ensures you have access to the services included with pfSense<sup>&reg;</sup> Plus software.' .
'<br />If you do not receive the activation token email within a few minutes of placing the order, please contact <a href="https://go.netgate.com">Netgate TAC</a>.');

$pghelp_exists = sprintf(gettext("This device has already been registered. " .
   "If you believe that to be incorrect, please contact Netgate TAC support for assistance by visiting %1s."),
   '<a href="go.netgate.com", target="_blank">go.netgate.com</a>');

$pghelp_notready = sprintf(gettext("The registration service is not available yet. Please watch %1s, %2s, %3s,  for updates."),
   '<a href="https://www.netgate.com/blog/" target="_blank">our blog</a>',
   '<a href="https://twitter.com/NetgateUSA" target="_blank">Twitter</a>',
   '<a href="https://www.reddit.com/r/Netgate/" target="_blank">Reddit</a>'
);

$pghelp_success = gettext("Your firewall has been successfully registered. On your next visit to the System/Update page, select pfSense<sup>&reg;</sup> Plus software from the list of repositories.");

$pghelp_notfound = sprintf(gettext("This activation token is not valid. " .
   "Please contact Netgate TAC support for assistance by visiting %1s"), '<a href="https://go.netgate.com" target="_blank">go.netgate.com</a>');

$disabled = "disabled";
$activation_token = "";
$alert_color = 'info';

// On registration (POST), call the activate_plus() function to register the token and NDI with the server.
// Otherwise call check_plus() to determine if this device is suitable for registration.
// Choose from one of the above messages on return
if ($_POST && $_REQUEST['Submit'] == 'Register') {
	$activation_token = $_REQUEST['activation_token'];

	if ( !empty($activation_token)) {
		unset($input_errors);

		$activation_token = str_replace(' ', '', trim($activation_token));
		$activation_token = str_replace("\n", '', $activation_token);
		$activation_token = str_replace("\r", '', $activation_token);

		if (preg_match('/(^[A-Za-z0-9-_]*\.[A-Za-z0-9-_]*\.[A-Za-z0-9-_]*$)/', $activation_token)) {
			makehelp(activate_plus("register", $activation_token));
		} else {
			$input_errors[] = $activation_token . ' ' . gettext('is not a valid activation token!');
		}
	} else {
		$input_errors[] = gettext('An activation token must be provided.');
	}
} else {
	makeHelp(activate_plus("check", ""));
}



if ($input_errors) {
	print_input_errors($input_errors);
	$disabled = "";
}

print('<div class="infoblock blockopen">');
print_info_box($pghelp,$alert_color, false);
print('</div>');

$form = new Form(false);

$section = new Form_Section('Register ' . $g['product_label']);

$section->addInput(new Form_Textarea(
	'activation_token',
	'*Activation token',
	$activation_token
))->setAttribute($disabled, true)
  ->setAttribute('rows', '10')
  ->removeClass('form-control')
  ->addClass('row-fluid col-sm-8');

$form->add($section);

$form->addGlobal(new Form_Button(
	'Submit',
	'Register',
	null,
	'fa-registered'
))->addClass('btn-primary')->setAttribute($disabled, true);

print $form;

// Display the appropriate message, color and input activation based on the web service response
function makeHelp($res) {
	global $pghelp, $disabled, $pghelp_ok, $pghelp_offline, $alert_color;
	global $pghelp_notrequired, $pghelp_notready, $pghelp_success, $pghelp_notfound;

	switch ($res) {
		case "OK" :
			$pghelp .= $pghelp_ok;
			$disabled = "";
		break;

		case "FAIL" :
			$pghelp .= $pghelp_offline;
			$alert_color = 'danger';
		break;

		case "EXISTS" :
			$pghelp .= $pghelp_notrequired;
			$alert_color = 'warning';
		break;

		case "NOTREADY" :
			$pghelp .= $pghelp_notready;
			$alert_color = 'warning';
		break;

		case "REGOK" :
			$pghelp .= $pghelp_success;
			$alert_color = 'success';
		break;

		case "NOTFOUND" :
			$pghelp .= $pghelp_notfound;
			$disabled = false;
			$alert_color = 'danger';
		break;
	}
}

include("foot.inc");
?>
