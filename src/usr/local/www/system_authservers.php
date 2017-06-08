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

$auth_servers_list = auth_get_authserver_list();

/*
 * FIXME (1): Auth server name validation to tighten.  This is enough for this code page and for the moment.
 *	Temp fix ensures we can treat auth server names as lacking spaces and "&", and still unique.
 *
 * FIXME (2): auth_get_authserver_list() needs not to add "Local Database" as the key, it breaks assumptions.
 *
 * FIXME (3): whatever replaces "LOCAL DATABASE" isn't a valid server name
 *
 * This is crude but allows us to test the PR more easily without adding too much temp code. Once resolved:
 * 		Replace and clean up all occurances of $config_MOD by $config
 *		Remove the 8 lines following this comment, which overrides the line above (defining $auth_servers_list[]) and which defines $config_MOD[]
 */

$config_MOD['system']['authserver'] = $auth_servers_list = array();
foreach ($config['system']['authserver'] as $svridx => $svrdata) {
	$config_MOD['system']['authserver'][htmlsafechars($svridx)] = $svrdata;
	$config_MOD['system']['authserver'][htmlsafechars($svridx)]['original_key'] = $svridx;
}
foreach (auth_get_authserver_list() as $svridx => $svrdata) {
	$auth_servers_list[htmlsafechars($svridx)] = $svrdata;
	$auth_servers_list[htmlsafechars($svridx)]['original_key'] = $svridx;
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

if (isset($_REQUEST['id'])) {
	// Used quite a bit, keeps rest of code simple
	$id = $_REQUEST['id'];
//	FIXME: DELETE THE NEXT LINE AFTER BUG FIXED, AND REPLACE BY $id ELSEWHERE, AS IT IS TEMPORARY ONLY
	$id_actually_in_config = $auth_servers_list[$id]['original_key'];
}
	
if ($_REQUEST['del']) {
	if (!isset($id) || !in_array($id, $config_MOD['system']['authserver'])) {
		$input_errors[] = gettext('Can not delete Authentication Server: server ID is missing, invalid or unrecognised.');
	} else {
		/* Remove server from main list. */
//	FIXME: PROPER CODE, USE AFTER BUG FIXED
//		unset($config['system']['authserver'][$id]);
//	FIXME: DELETE THE NEXT LINE AFTER BUG FIXED, AS IT IS TEMPORARY ONLY
		unset($config['system']['authserver'][$id_actually_in_config];
		
		/* Remove server from temp list used on this page. */
		unset($auth_servers_list[$id]);

		$savemsg = sprintf(gettext("Authentication Server %s deleted."), htmlspecialchars($id));
		write_config($savemsg);
	}
} elseif ($_REQUEST['test']) {
	// Test auth settings. Also see similar code at system_admin_advanced.php
	$authcfg = $config_MOD['system']['authserver'][$id];
	if (!$authcfg) {
		$savemsg = sprintf(gettext("%sError: Could not find settings for server '%s'%s"), '<span class="text-danger">', htmlspecialchars($id), "</span>");
	} elseif ($authcfg['type'] != 'ldap') {
		$savemsg = sprintf(gettext("%sError: %s is not an LDAP server, unable to test connection%s"), '<span class="text-danger">', htmlspecialchars($id), "</span>");
	} else {
		//auth server is defined and is LDAP, carry on
		$savemsg = sprintf(gettext('Server connection test results') . ":<br/>\n";
		$savemsg .= sprintf(gettext('Attempting connection to %s (%s:%s) ... '),  htmlspecialchars($id), htmlspecialchars($authcfg['host']), htmlspecialchars($authcfg['port']), );
		if (ldap_test_connection($authcfg)) {
			// connection OK
			$savemsg .= '<span class="text-center text-success">' . gettext("OK") . '</span><br/>';
			$savemsg .= sprintf(gettext('Attempting bind to %s (%s:%s) ... '),  htmlspecialchars($id), htmlspecialchars($authcfg['host']), htmlspecialchars($authcfg['port']), );
			if (ldap_test_bind($authcfg)) {
				// bind OK
				$savemsg .= '<span class="text-center text-success">' . gettext("OK") . '</span><br/>';
				$savemsg .= sprintf(gettext('Attempting to fetch Organizational Units from %s ... '),  htmlspecialchars($id));
				$ous = ldap_get_user_ous(true, $authcfg);
				if (count($ous)>1) {
					// OUs OK
					$savemsg .= '<span class="text-center text-success">' . gettext("OK") . '</span><br/>';
					if (is_array($ous)) {
						$savemsg .=  "<b>" . gettext("Organization units found") . ":</b>&nbsp; ";
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
			<?php foreach ($auth_servers_list as $serveridx => $servercfg): ?>
					<tr>
						<td><?=htmlspecialchars($servercfg['name'])?></td>
						<td><?=htmlspecialchars($auth_server_types[$servercfg['type']])?></td>
						<td><?=htmlspecialchars($servercfg['host'])?></td>
						<td>
						<?php if ($auth_server_types[$servercfg['type']] == 'ldap'): ?>
							// We can test any LDAP server
							<a class="fa fa-wrench"  title="<?=gettext("Test Connection")?>" href="system_authservers.php?act=test&amp;id=<?=$serveridx?>"></a>
						<php elseif ($servercfg['type'] != 'Local Database'): ?>
							// We can edit anything except local database
							<a class="fa fa-pencil" title="<?=gettext("Edit server"); ?>" href="system_authservers_edit.php?id=<?=$serveridx?>"></a>
						<php if (count($auth_servers_list) >= 2): ?>
							// We can delete anything except local database *if* there's at least one other server defined (which there should be)
							<a class="fa fa-trash"  title="<?=gettext("Delete server")?>" href="system_authservers.php?act=del&amp;id=<?=$serveridx?>"></a>
						<?php endif; ?>
						<?php endif; ?>
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
