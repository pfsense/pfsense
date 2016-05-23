<?php
/*
	system_usermanager_settings.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2007 Bill Marquette <bill.marquette@gmail.com>
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-system-usermanager-settings
##|*NAME=System: User Manager: Settings
##|*DESCR=Allow access to the 'System: User Manager: Settings' page.
##|*MATCH=system_usermanager_settings.php*
##|-PRIV

require("guiconfig.inc");
require_once("auth.inc");

// Test LDAP settings in response to an AJAX request from this page.
if ($_REQUEST['ajax']) {

	if (isset($config['system']['authserver'][0]['host'])) {
		$auth_server = $config['system']['authserver'][0]['host'];
		$authserver = $_REQUEST['authserver'];
		$authcfg = auth_get_authserver($authserver);
	}

	if (!$authcfg) {
		printf(gettext("%sError: Could not find settings for %s%s"), '<span class="text-danger">', htmlspecialchars($authserver), "</span>");
		exit;
	} else {
		print("<pre>");

		print('<table class="table table-hover table-striped table-condensed">');

		print("<tr><td>" . sprintf(gettext("Attempting connection to %s%s%s"), "<td><center>", htmlspecialchars($auth_server), "</center></td>"));
		if (ldap_test_connection($authcfg)) {
			print("<td><span class=\"text-center text-success\">" . gettext("OK") . "</span></td></tr>");

			print("<tr><td>" . sprintf(gettext("Attempting bind to %s%s%s"), "<td><center>", htmlspecialchars($auth_server), "</center></td>"));
			if (ldap_test_bind($authcfg)) {
				print('<td><span class="text-center text-success">' . gettext("OK") . "</span></td></tr>");

				print("<tr><td>" . sprintf(gettext("Attempting to fetch Organizational Units from %s%s%s"), "<td><center>", htmlspecialchars($auth_server), "</center></td>"));
				$ous = ldap_get_user_ous(true, $authcfg);

				if (count($ous)>1) {
					print('<td><span class="text-center text-success">' . gettext("OK") . "</span></td></tr>");
					print('<tr ><td colspan="3">');

					if (is_array($ous)) {
						print("<b>" . gettext("Organization units found") . "</b>");
						print('<table class="table table-hover">');
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

$pconfig['session_timeout'] = &$config['system']['webgui']['session_timeout'];

if (isset($config['system']['webgui']['authmode'])) {
	$pconfig['authmode'] = &$config['system']['webgui']['authmode'];
} else {
	$pconfig['authmode'] = "Local Database";
}

$pconfig['backend'] = &$config['system']['webgui']['backend'];

// Page title for main admin
$pgtitle = array(gettext("System"), gettext("User Manager"), gettext("Settings"));

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

		write_config();

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
$tab_array[] = array(gettext("Users"), false, "system_usermanager.php");
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
	[min => 0]
))->setHelp('Time in minutes to expire idle management sessions. The default is 4 '.
	'hours (240 minutes). Enter 0 to never expire sessions. NOTE: This is a security '.
	'risk!');

$auth_servers = array();
foreach (auth_get_authserver_list() as $auth_server) {
	$auth_servers[ $auth_server['name'] ] = $auth_server['name'];
}

$section->addInput(new Form_Select(
	'authmode',
	'Authentication Server',
	$pconfig['authmode'],
	$auth_servers
));

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
