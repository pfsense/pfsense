<?php
/*
 * load_balancer_pool.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2019 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-loadbalancer-pool
##|*NAME=Load Balancer: Pool
##|*DESCR=Allow access to the 'Load Balancer: Pool' page.
##|*MATCH=load_balancer_pool.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

init_config_arr(array('load_balancer', 'lbpool'));
$a_pool = &$config['load_balancer']['lbpool'];

$pconfig = $_POST;

if ($_POST['apply']) {
	$retval = 0;
	$retval |= filter_configure();
	$retval |= relayd_configure();

	clear_subsystem_dirty('loadbalancer');
}

if ($_POST['act'] == "del") {
	if (array_key_exists($_POST['id'], $a_pool)) {
		/* make sure no virtual servers reference this entry */
		if (is_array($config['load_balancer']['virtual_server'])) {
			foreach ($config['load_balancer']['virtual_server'] as $vs) {
				if ($vs['poolname'] == $a_pool[$_POST['id']]['name']) {
					$input_errors[] = gettext("This entry cannot be deleted because it is still referenced by at least one virtual server.");
					break;
				}
			}
		}

		if (!$input_errors) {
			unset($a_pool[$_POST['id']]);
			write_config();
			mark_subsystem_dirty('loadbalancer');
			header("Location: load_balancer_pool.php");
			exit;
		}
	}
}

/* Index monitor_type array for easy hyperlinking */
$mondex = array();
for ($i = 0; isset($config['load_balancer']['monitor_type'][$i]); $i++) {
	$mondex[$config['load_balancer']['monitor_type'][$i]['name']] = $i;
}

for ($i = 0; isset($config['load_balancer']['lbpool'][$i]); $i++) {
	$a_pool[$i]['monitor'] = "<a href=\"/load_balancer_monitor_edit.php?id={$mondex[$a_pool[$i]['monitor']]}\">" . htmlspecialchars($a_pool[$i]['monitor']) . "</a>";
}

$pgtitle = array(gettext("Services"), gettext("Load Balancer"), gettext("Pools"));
$pglinks = array("", "@self", "@self");
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
$tab_array[] = array(gettext("Pools"), true, "load_balancer_pool.php");
$tab_array[] = array(gettext("Virtual Servers"), false, "load_balancer_virtual_server.php");
$tab_array[] = array(gettext("Monitors"), false, "load_balancer_monitor.php");
$tab_array[] = array(gettext("Settings"), false, "load_balancer_setting.php");
display_top_tabs($tab_array);

?>
<form action="load_balancer_pool.php" method="post">
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Pool')?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr>
						<th><?=gettext('Name')?></th>
						<th><?=gettext('Mode')?></th>
						<th><?=gettext('Servers')?></th>
						<th><?=gettext('Port')?></th>
						<th><?=gettext('Monitor')?></th>
						<th><?=gettext('Description')?></th>
						<th><?=gettext('Actions')?></th>
					</tr>
				</thead>
				<tbody>
<?php

$idx = 0;
foreach ($a_pool as $pool) {
?>
					<tr>
						<td>
							<?=$pool['name']?>
						</td>
						<td>
							<?=htmlspecialchars($pool['mode'])?>
						</td>
						<td>
<?php
	$numsvrs = count($pool['servers']) - 1;

	foreach ($pool['servers'] as $server => $ip) {
		print($ip);
		print(($server < $numsvrs) ? '<br />':'');
	}

?>
						</td>
						<td>
							<?=$pool['port']?>
						</td>
						<td>
							<?=$pool['monitor']?>
						</td>
						<td>
							<?=htmlspecialchars($pool['descr'])?>
						</td>
						<td>
							<a class="fa fa-pencil"	title="<?=gettext('Edit pool')?>"	href="load_balancer_pool_edit.php?id=<?=$idx?>"></a>
							<a class="fa fa-clone"	title="<?=gettext('Copy pool')?>"	href="load_balancer_pool_edit.php?act=dup&amp;id=<?=$idx?>"></a>
							<a class="fa fa-trash"	title="<?=gettext('Delete pool')?>"	href="load_balancer_pool.php?act=del&amp;id=<?=$idx?>" usepost></a>
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
	<a href="load_balancer_pool_edit.php" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext('Add')?>
	</a>
</nav>

<?php include("foot.inc");
