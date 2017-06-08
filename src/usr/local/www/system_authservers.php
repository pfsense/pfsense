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

if (!is_array($config['system']['authserver'])) {
	$config['system']['authserver'] = array();
}

$a_servers = auth_get_authserver_list();
foreach ($a_servers as $servers) {
	$a_server[] = $servers;
}

/* 
		// FIXME:   Nothing actually seems to use $config['ca'] in this code any more.
			    Commenting this out for now - may need to remove when reviewed.

		if (!is_array($config['ca'])) {
			$config['ca'] = array();
		}
		$a_ca =& $config['ca'];
*/


/* 
		// FIXME:   Nothing actually seems to use $config['system']['webgui']['backend']
			    Commenting this out for now - may need to remove when reviewed.

		$pconfig['backend'] = &$config['system']['webgui']['backend'];
		// Default to pfsense backend type if none is defined
		if (!$pconfig['backend']) {
			$pconfig['backend'] = "pfsense";
		}
*/

unset($input_errors);

if ($_REQUEST['del']) {
	$id = $_REQUEST['id'];
	$serverdeleted = $a_server[$id]['name'];
	if (!isset($_REQUEST['id']) || !is_numericint($id) || !$a_server[$id] || $serverdeleted == 'Local Database') {
		$input_errors[] = gettext('Can not delete Authentication Server: server ID is missing, invalid or unrecognised.');
	} elseif (count($a_server) <= 1) {
		// Shouldn't be possible as "local database" should always exist and isn't deletable here, but "just in case"
		$input_errors[] = gettext('You must define at least one Authentication Server before deleting this server, as one server must be defined at all times.');
	} else {
		/* Remove server from main list. */
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
} elseif ($_REQUEST['test']) {
	// Test auth settings. Also see similar code at system_admin_advanced.php

// FIXME:  Should use $id here, or (more likely) the server should be present in $_REQUEST['authserver']
//		at the moment this won't pick up the arg (selected svr) from the calling code.
	
	if (isset($config['system']['authserver'][0]['host'])) {
		$auth_server = $config['system']['authserver'][0]['host'];
		$selected_authserver = $_POST['authserver'];
		$authcfg = $auth_servers_list($selected_authserver);
	}
	if (!$authcfg) {
		$savemsg = sprintf(gettext("%sError: Could not find settings for %s%s"), '<span class="text-danger">', htmlspecialchars($selected_authserver), "</span>");
	} elseif ($authcfg['type'] != 'ldap') {
		$savemsg = sprintf(gettext("%sError: %s is not an LDAP server, unable to test connection%s"), '<span class="text-danger">', htmlspecialchars($selected_authserver), "</span>");
	} else {
		//auth server is defined and is LDAP, carry on
		$savemsg = sprintf(gettext('Server Connection test results') . ":<br/>\n";
		$savemsg .= sprintf(gettext('Attempting connection to %s ... '),  htmlspecialchars($auth_server));
		if (ldap_test_connection($authcfg)) {
			// connection OK
			$savemsg .= '<span class="text-center text-success">' . gettext("OK") . '</span><br/>';
			$savemsg .= sprintf(gettext('Attempting bind to %s ... '), htmlspecialchars($auth_server));
			if (ldap_test_bind($authcfg)) {
				// bind OK
				$savemsg .= '<span class="text-center text-success">' . gettext("OK") . '</span><br/>';
				$savemsg .= sprintf(gettext('Attempting to fetch Organizational Units from %s ... '),  htmlspecialchars($auth_server));
				$ous = ldap_get_user_ous(true, $authcfg);
				if (count($ous)>1) {
					// OUs OK
					$savemsg .= '<span class="text-center text-success">' . gettext("OK") . '</span><br/>';
					if (is_array($ous)) {
						$savemsg .=  "<b>" . gettext("Organization units found") . "</b><br/>";
						// format as bulleted inline list, so it doesn't sprawl over dozens of lines in the WebUI
						$savemsg .=  implode("&nbsp;&nbsp;&nbsp;\n",
								array_map(
									function($ou) { return '<span style="white-space: nowrap">&bullet;&nbsp;' . htmlspecialchars($ou) . '</span>'; }, 
									$ous
							     ));
					}
				} else {
					// fetch OUs failed
					$savemsg .= '<span class="text-alert">' . gettext("failed - no Organizational Units found") . '</span>';
				}
			} else {
				// bind failed
				$savemsg .= '<span class="text-alert">' . gettext("failed") . '</span>';
			}
		} else {
			// connection failed
			$savemsg .= '<span class="text-alert">' . gettext("failed") . '</span>';
		}
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
						<?php 
						if ($i < (count($a_server) - 1)):
						     if ($auth_server_types[$server['type']] == 'ldap') {
						?>
							<a class="fa fa-wrench"  title="<?=gettext("Test Connection")?>" href="system_authservers.php?act=test&amp;id=<?=$i?>"></a>
						<?php endif;?>
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
	<a href="system_authservers_edit.php" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>
<?php
include("foot.inc");
