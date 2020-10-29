<?php
/*
 * system_usermanager_settings.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2007 Bill Marquette <bill.marquette@gmail.com>
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
##|*IDENT=page-system-usermanager-settings
##|*NAME=System: User Manager: Settings
##|*DESCR=Allow access to the 'System: User Manager: Settings' page.
##|*WARN=standard-warning-root
##|*MATCH=system_usermanager_settings.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("auth.inc");

// Test LDAP settings in response to an AJAX request from this page.
if ($_REQUEST['ajax']) {

	$authserver = $_REQUEST['authserver'];
	$authcfg = auth_get_authserver($authserver);
	$auth_server_host = $authcfg['host'];

	if (!$authcfg) {
		printf(gettext('%1$sError: Could not find settings for %2$s%3$s'), '<span class="text-danger">', htmlspecialchars($authserver), "</span>");
		exit;
	} elseif ($authcfg['type'] != 'ldap') {
		printf(gettext('%1$sError: cannot test settings for %2$s because testing is supported only for LDAP based backends.%3$s'), '<span class="text-danger">', htmlspecialchars($authserver), "</span>");
		exit;
	} else {
		print("<pre>");

		print('<table class="table table-hover table-condensed">');

		print("<tr><td>" . sprintf(gettext('Attempting connection to %1$s%2$s%3$s'), "<td><center>", htmlspecialchars($auth_server_host), "</center></td>"));
		if (ldap_test_connection($authcfg)) {
			print("<td><span class=\"text-center text-success\">" . gettext("OK") . "</span></td></tr>");

			print("<tr><td>" . sprintf(gettext('Attempting bind to %1$s%2$s%3$s'), "<td><center>", htmlspecialchars($auth_server_host), "</center></td>"));
			if (ldap_test_bind($authcfg)) {
				print('<td><span class="text-center text-success">' . gettext("OK") . "</span></td></tr>");

				print("<tr><td>" . sprintf(gettext('Attempting to fetch Organizational Units from %1$s%2$s%3$s'), "<td><center>", htmlspecialchars($auth_server_host), "</center></td>"));
				$ous = ldap_get_user_ous(true, $authcfg);

				if (count($ous)>1) {
					print('<td><span class="text-center text-success">' . gettext("OK") . "</span></td></tr>");
					print('<tr ><td colspan="3">');

					if (is_array($ous)) {
						print("<b>" . gettext("Organization units found") . "</b>");
						print('<table class="table-hover">');
						foreach ($ous as $ou) {
							print("<tr><td>" . $ou . "</td></tr>");
						}

					print("</td></tr>");
					print("</table>");
					}
				} else {
					print("<td><span class=\"text-alert\">" . gettext("failed") . "</span></td></tr>");
				}

				print("</table><p/>");

			} else {
				print('<td><span class="text-alert">' . gettext("failed") . "</span></td></tr>");
				print("</table><p/>");
			}
		} else {
			print('<td><span class="text-alert">' . gettext("failed") . "</span></td></tr>");
			print("</table><p/>");
		}

		print("</pre>");
		exit;
	}
}

$pconfig['session_timeout'] = $config['system']['webgui']['session_timeout'];

if (isset($config['system']['webgui']['authmode'])) {
	$pconfig['authmode'] = $config['system']['webgui']['authmode'];
} else {
	$pconfig['authmode'] = "Local Database";
}

$pconfig['shellauth'] = isset($config['system']['webgui']['shellauth']) ? true : false;

$pconfig['backend'] = $config['system']['webgui']['backend'];

$pconfig['auth_refresh_time'] = $config['system']['webgui']['auth_refresh_time'];

// Page title for main admin
$pgtitle = array(gettext("System"), gettext("User Manager"), gettext("Settings"));
$pglinks = array("", "system_usermanager.php", "@self");

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

	if (isset($_POST['auth_refresh_time'])) {
		$timeout = intval($_POST['auth_refresh_time']);
		if (!is_numeric($timeout) || $timeout < 0 || $timeout > 3600 ) {
			$input_errors[] = gettext("Authentication refresh time must be an integer between 0 and 3600 (inclusive).");
		}
	}

	$authcfg = auth_get_authserver($_POST['authmode']);
	if (($authcfg['type'] == 'radius') && !isset($authcfg['radius_auth_port'])) {
		$input_errors[] = gettext("RADIUS Authentication Server must provide Authentication service.");
	}

	if (($authcfg['type'] == 'ldap') && empty($authcfg['ldap_pam_groupdn']) &&
	    isset($_POST['shellauth'])) {
		$input_errors[] = gettext("Shell Authentication Group DN must be specified for LDAP server if " . 
			"Shell Authentication is used.");
	}

	if (($_POST['authmode'] == "Local Database") && $_POST['savetest']) {
		$savemsg = gettext("Settings have been saved, but the test was not performed because it is not supported for local databases.");
	}

	if (!$input_errors) {
		if ($_POST['authmode'] != "Local Database") {
			$authsrv = auth_get_authserver($_POST['authmode']);
			if ($_POST['savetest']) {
				if ($authsrv['type'] == "ldap") {
					$save_and_test = true;
				} else {
					$savemsg = gettext("Settings have been saved, but the test was not performed because it is supported only for LDAP based backends.");
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

		if (isset($_POST['shellauth'])) {
			$config['system']['webgui']['shellauth'] = true;
		} else {
			unset($config['system']['webgui']['shellauth']);
		}

		if (isset($_POST['auth_refresh_time']) && $_POST['auth_refresh_time'] != "") {
			$config['system']['webgui']['auth_refresh_time'] = intval($_POST['auth_refresh_time']);
		} else {
			unset($config['system']['webgui']['auth_refresh_time']);
		}

		write_config();
		set_pam_auth();
	}
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
if (!isAllowedPage("system_usermanager.php")) {
       $tab_array[] = array(gettext("User Password"), false, "system_usermanager_passwordmg.php");
} else {
       $tab_array[] = array(gettext("Users"), false, "system_usermanager.php");
}
$tab_array[] = array(gettext("Groups"), false, "system_groupmanager.php");
$tab_array[] = array(gettext("Settings"), true, "system_usermanager_settings.php");
$tab_array[] = array(gettext("Authentication Servers"), false, "system_authservers.php");
display_top_tabs($tab_array);

/* Default to pfsense backend type if none is defined */
if (!$pconfig['backend']) {
	$pconfig['backend'] = "pfsense";
}

$form = new Form;

$section = new Form_Section('Settings');

$section->addInput(new Form_Input(
	'session_timeout',
	'Session timeout',
	'number',
	$pconfig['session_timeout'],
	['min' => 0]
))->setHelp('Time in minutes to expire idle management sessions. The default is 4 '.
	'hours (240 minutes). Enter 0 to never expire sessions. NOTE: This is a security '.
	'risk!');

$auth_servers = array();
foreach (auth_get_authserver_list() as $idx_authserver => $auth_server) {
	$auth_servers[ $idx_authserver ] = $auth_server['name'];
}

$section->addInput(new Form_Select(
	'authmode',
	'*Authentication Server',
	$pconfig['authmode'],
	$auth_servers
));

$section->addInput(new Form_Checkbox(
	'shellauth',
	'Shell Authentication',
	'Use Authentication Server for Shell Authentication',
	$pconfig['shellauth'],
))->setHelp('If RADIUS or LDAP server is selected it is used for console and SSH authentication. ' .
	    'Otherwise, the Local Database is used.%1$s To allow logins with RADIUS credentials, ' .
	    'equivalent local users with the expected privileges must be created first.%1$s ' .
	    'To allow logins with LDAP credentials, ' .
	    'Shell Authentication Group DN must be specified on the LDAP server configuration page.', '<br/>'); 

$section->addInput(new Form_Input(
	'auth_refresh_time',
	'Auth Refresh Time',
	'number',
	$pconfig['auth_refresh_time'],
	['min' => 0, 'max' => 3600]
))->setHelp('Time in seconds to cache authentication results. The default is 30 seconds, maximum 3600 (one hour). '.
	'Shorter times result in more frequent queries to authentication servers.');

$form->addGlobal(new Form_Button(
	'savetest',
	'Save & Test',
	null,
	'fa-wrench'
))->addClass('btn-info');

$form->add($section);

$modal = new Modal("LDAP settings", "testresults", true);

$modal->addInput(new Form_StaticText(
	'Test results',
	'<span id="ldaptestop">Testing pfSense LDAP settings... One moment please...' . $g['product_name'] . '</span>'
));

$form->add($modal);

print $form;

// If the user clicked "Save & Test" show the modal and populate it with the test results via AJAX
if ($save_and_test) {
?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {

	function test_LDAP() {
		var ajaxRequest;
		var authserver = $('#authmode').val();

		ajaxRequest = $.ajax(
			{
				url: "/system_usermanager_settings.php",
				type: "post",
				data: {
					ajax: "ajax",
					authserver: authserver
				}
			}
		);

		// Deal with the results of the above ajax call
		ajaxRequest.done(function (response, textStatus, jqXHR) {
			$('#ldaptestop').html(response);
		});
	}

	$('#testresults').modal('show');

	test_LDAP();
});
</script>
<?php

}

include("foot.inc");
