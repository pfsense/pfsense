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

// TO CHECK - IF THERE'S ONLY ONE AUTH SERVER, SHOULD THE USER BE PREVENTED FROM DELETING?
if ($_REQUEST['del']) {
	$id = $_REQUEST['id'];
	$serverdeleted = $a_server[$id]['name'];
	if (!isset($_REQUEST['id']) || !is_numericint($id) || !$a_server[$id] || $serverdeleted == 'Local Database') {
		$input_errors[] = gettext('Can not delete Authentication Server: server ID is missing, invalid or unrecognised.');
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
	<a href="system_authservers_edit.php" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>
<?php
include("foot.inc");
