<?php
/*
 * load_balancer_monitor.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005-2008 Bill Marquette <bill.marquette@gmail.com>
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
##|*IDENT=page-services-loadbalancer-monitor
##|*NAME=Services: Load Balancer: Monitors
##|*DESCR=Allow access to the 'Services: Load Balancer: Monitors' page.
##|*MATCH=load_balancer_monitor.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("filter.inc");

init_config_arr(array('load_balancer', 'monitor_type'));
$a_monitor = &$config['load_balancer']['monitor_type'];

$pconfig = $_POST;

if ($_POST['apply']) {
	$retval = 0;
	$retval |= filter_configure();
	$retval |= relayd_configure();

	clear_subsystem_dirty('loadbalancer');
}


if ($_POST['act'] == "del") {
	if (array_key_exists($_POST['id'], $a_monitor)) {
		/* make sure no pools reference this entry */
		if (is_array($config['load_balancer']['lbpool'])) {
			foreach ($config['load_balancer']['lbpool'] as $pool) {
				if ($pool['monitor'] == $a_monitor[$_POST['id']]['name']) {
					$input_errors[] = gettext("This entry cannot be deleted because it is still referenced by at least one pool.");
					break;
				}
			}
		}

		if (!$input_errors) {
			unset($a_monitor[$_POST['id']]);
			write_config();
			mark_subsystem_dirty('loadbalancer');
			header("Location: load_balancer_monitor.php");
			exit;
		}
	}
}

$pgtitle = array(gettext("Services"), gettext("Load Balancer"), gettext("Monitors"));
$pglinks = array("", "load_balancer_pool.php", "@self");
$shortcut_section = "relayd";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($_POST['apply']) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('loadbalancer')) {
	print_apply_box(gettext("The load balancer configuration has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
}

/* active tabs */
$tab_array = array();
$tab_array[] = array(gettext("Pools"), false, "load_balancer_pool.php");
$tab_array[] = array(gettext("Virtual Servers"), false, "load_balancer_virtual_server.php");
$tab_array[] = array(gettext("Monitors"), true, "load_balancer_monitor.php");
$tab_array[] = array(gettext("Settings"), false, "load_balancer_setting.php");
display_top_tabs($tab_array);
?>

<form action="load_balancer_monitor.php" method="post">
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Monitor')?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr>
						<th><?=gettext('Name')?></th>
						<th><?=gettext('Type')?></th>
						<th><?=gettext('Description')?></th>
						<th><?=gettext('Actions')?></th>
					</tr>
				</thead>
				<tbody>
<?php
$idx = 0;
foreach ($a_monitor as $monitor) {
?>
					<tr>
						<td>
							<?=htmlspecialchars($monitor['name'])?>
						</td>
						<td>
							<?=htmlspecialchars($monitor['type'])?>
						</td>
						<td>
							<?=htmlspecialchars($monitor['descr'])?>
						</td>
						<td>
							<a class="fa fa-pencil"	title="<?=gettext('Edit monitor')?>"	href="load_balancer_monitor_edit.php?id=<?=$idx?>"></a>
							<a class="fa fa-clone"	title="<?=gettext('Copy monitor')?>"	href="load_balancer_monitor_edit.php?act=dup&amp;id=<?=$idx?>"></a>
							<a class="fa fa-trash"	title="<?=gettext('Delete monitor')?>"	href="load_balancer_monitor.php?act=del&amp;id=<?=$idx?>" usepost></a>
						</td>
					</tr>
<?php
	$idx++;
}
?>
				</tbody>
			</table>
		</div>
	</div>
</form>

<nav class="action-buttons">
	<a href="load_balancer_monitor_edit.php" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext('Add')?>
	</a>
</nav>

<?php

include("foot.inc");
