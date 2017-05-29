<?php
/*
 * system_authservers.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008 Shrew Soft Inc
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
##|*IDENT=page-system-authservers
##|*NAME=System: Authentication Servers
##|*DESCR=Allow access to the 'System: Authentication Servers' page.
##|*WARN=standard-warning-root
##|*MATCH=system_authservers.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("auth.inc");
require_once("pfsense-utils.inc");

// Test LDAP settings in response to an AJAX request from this page.
if ($_REQUEST['ajax'] && $_REQUEST['act'] == 'test_ldap') {

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

if (!is_array($config['system']['authserver'])) {
	$config['system']['authserver'] = array();
}

$a_servers = auth_get_authserver_list();
foreach ($a_servers as $servers) {
	$a_server[] = $servers;
}

if (!is_array($config['ca'])) {
	$config['ca'] = array();
}
$a_ca =& $config['ca'];

if (isset($config['system']['webgui']['authmode'])) {
	$pconfig['authmode'] = &$config['system']['webgui']['authmode'];
} else {
	$pconfig['authmode'] = "Local Database";
}

$pconfig['backend'] = &$config['system']['webgui']['backend'];

/* Default to pfsense backend type if none is defined */
if (!$pconfig['backend']) {
	$pconfig['backend'] = "pfsense";
}

$save_and_test = false;

unset($input_errors);

if ($_REQUEST['del']) {
	$id = $_REQUEST['id'];
	if (!isset($_REQUEST['id']) || !is_numericint($id) || !$a_server[$id]) {
		$input_errors[] = gettext('Can not delete Authentication Server: server ID is missing, invalid or unrecognised.');
	} else {
		/* Remove server from main list. */
		$serverdeleted = $a_server[$id]['name'];
		foreach ($config['system']['authserver'] as $k => $as) {
			if ($config['system']['authserver'][$k]['name'] == $serverdeleted) {
				unset($config['system']['authserver'][$k]);
			}
		}

		/* Remove server from temp list used later on this page. */
		unset($a_server[$id]);

		$savemsg = sprintf(gettext("Authentication Server %s deleted."), htmlspecialchars($serverdeleted));
		write_config($savemsg);
	}
} elseif ($_REQUEST) {
	$pconfig = $_REQUEST;

	if (($_REQUEST['authmode'] == "Local Database") && $_REQUEST['savetest']) {
		$savemsg = gettext("Settings have been saved, but the test was not performed because it is not supported for local databases.");
	}

	if (!$input_errors) {
		if ($_REQUEST['authmode'] != "Local Database") {
			$authsrv = auth_get_authserver($_REQUEST['authmode']);
			if ($_REQUEST['savetest']) {
				if ($authsrv['type'] == "ldap") {
					$save_and_test = true;
				} else {
					$savemsg = gettext("Settings have been saved, but the test was not performed because it is supported only for LDAP based backends.");
				}
			}
		}

		if ($_REQUEST['authmode']) {
			$config['system']['webgui']['authmode'] = $_REQUEST['authmode'];
		} else {
			unset($config['system']['webgui']['authmode']);
		}

		write_config();
	}
}

$pgtitle = array(gettext("System"), gettext("User Manager"), gettext("Authentication Servers"));
$pglinks = array("", "system_usermanager.php", "@self");

$shortcut_section = "authentication";
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
$tab_array[] = array(gettext("Authentication Servers"), true, "system_authservers.php");
display_top_tabs($tab_array);


$form = new Form;

$section = new Form_Section('Login Authentication for this router');

$auth_servers = array();
foreach (auth_get_authserver_list() as $idx_authserver => $auth_server) {
	$auth_servers[ $idx_authserver ] = $auth_server['name'];
}

$section->addInput(new Form_Select(
	'authmode',
	'',
	$pconfig['authmode'],
	$auth_servers
))->sethelp('Select the server used for authenticating WebConfigurator login attempts on this router.<br/><br/>Note: By default, the same server will be used to authenticate Console logins if password protection is enabled, and other remote logins such as SSH and Telnet if they are enabled.');

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

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Authentication Servers')?></h2></div>
	<div class="panel-body">

		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap table-rowdblclickedit" data-sortable>
				<thead>
					<tr>
						<th><?=gettext("Server Name")?></th>
						<th><?=gettext("Type")?></th>
						<th><?=gettext("Host Name")?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody>
			<?php foreach ($a_server as $i => $server): ?>
					<tr>
						<td><?=htmlspecialchars($server['name'])?></td>
						<td><?=htmlspecialchars($auth_server_types[$server['type']])?></td>
						<td><?=htmlspecialchars($server['host'])?></td>
						<td>
						<?php if ($i < (count($a_server) - 1)): ?>
							<a class="fa fa-pencil" title="<?=gettext("Edit server"); ?>" href="system_authservers_edit.php?id=<?=$i?>"></a>
							<a class="fa fa-trash"  title="<?=gettext("Delete server")?>" href="system_authservers.php?act=del&amp;id=<?=$i?>"></a>
						<?php endif?>
						</td>
					</tr>
			<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<nav class="action-buttons">
	<a href="system_authservers_edit.php?act=new" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>

<?php
// If the user clicked "Save & Test" show the modal and populate it with the test results via AJAX
if ($save_and_test):
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	function test_LDAP() {
		var ajaxRequest;
		var authserver = $('#authmode').val();

		ajaxRequest = $.ajax(
			{
				url: "/system_authservers.php",
				type: "post",
				data: {
					ajax: "ajax",
					act: 'test_ldap',
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
endif;

include("foot.inc");
