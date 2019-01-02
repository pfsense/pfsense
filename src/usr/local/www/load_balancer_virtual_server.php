<?php
/*
 * load_balancer_virtual_server.php
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
##|*IDENT=page-services-loadbalancer-virtualservers
##|*NAME=Services: Load Balancer: Virtual Servers
##|*DESCR=Allow access to the 'Services: Load Balancer: Virtual Servers' page.
##|*MATCH=load_balancer_virtual_server.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("vslb.inc");

init_config_arr(array('load_balancer', 'virtual_server'));
$a_vs = &$config['load_balancer']['virtual_server'];

$pconfig = $_POST;

if ($_POST['apply']) {
	$retval = 0;
	$retval |= filter_configure();
	$retval |= relayd_configure();
	/* Wipe out old relayd anchors no longer in use. */
	cleanup_lb_marked();
	clear_subsystem_dirty('loadbalancer');
}

if ($_POST['act'] == "del") {
	if (array_key_exists($_POST['id'], $a_vs)) {

		if (!$input_errors) {
			cleanup_lb_mark_anchor($a_vs[$_POST['id']]['name']);
			unset($a_vs[$_POST['id']]);
			write_config();
			mark_subsystem_dirty('loadbalancer');
			header("Location: load_balancer_virtual_server.php");
			exit;
		}
	}
}

/* Index lbpool array for easy hyperlinking */
$poodex = array();
for ($i = 0; isset($config['load_balancer']['lbpool'][$i]); $i++) {
	$poodex[$config['load_balancer']['lbpool'][$i]['name']] = $i;
}
for ($i = 0; isset($config['load_balancer']['virtual_server'][$i]); $i++) {
	if ($a_vs[$i]) {
		$a_vs[$i]['mode'] = htmlspecialchars($a_vs[$i]['mode']);
		$a_vs[$i]['relay_protocol'] = htmlspecialchars($a_vs[$i]['relay_protocol']);
		$a_vs[$i]['poolname'] = "<a href=\"/load_balancer_pool_edit.php?id={$poodex[$a_vs[$i]['poolname']]}\">" . htmlspecialchars($a_vs[$i]['poolname']) . "</a>";
		if ($a_vs[$i]['sitedown'] != '') {
			$a_vs[$i]['sitedown'] = "<a href=\"/load_balancer_pool_edit.php?id={$poodex[$a_vs[$i]['sitedown']]}\">" . htmlspecialchars($a_vs[$i]['sitedown']) . "</a>";
		} else {
			$a_vs[$i]['sitedown'] = 'none';
		}
	}
}

// Return the index of any alias matching the specified name and type
function alias_idx($name, $type) {
	global $config;

	if (empty($config['aliases']['alias'])) {
		return(-1);
	}

	$idx = 0;
	foreach ($config['aliases']['alias'] as $alias) {
		if (($alias['name'] == $name) && ($alias['type'] == $type)) {
			return($idx);
		}

		$idx++;
	}

	return(-1);
}

$pgtitle = array(gettext("Services"), gettext("Load Balancer"), gettext("Virtual Servers"));
$pglinks = array("", "load_balancer_pool.php", "@self");
$shortcut_section = "relayd-virtualservers";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($_POST['apply']) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('loadbalancer')) {
	print_apply_box(gettext("The virtual server configuration has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
}

/* active tabs */
$tab_array = array();
$tab_array[] = array(gettext("Pools"), false, "load_balancer_pool.php");
$tab_array[] = array(gettext("Virtual Servers"), true, "load_balancer_virtual_server.php");
$tab_array[] = array(gettext("Monitors"), false, "load_balancer_monitor.php");
$tab_array[] = array(gettext("Settings"), false, "load_balancer_setting.php");
display_top_tabs($tab_array);
?>

<form action="load_balancer_virtual_server.php" method="post">
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Virtual Servers')?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover table-rowdblclickedit">
				<thead>
					<tr>
						<th><?=gettext('Name')?></th>
						<th><?=gettext('Protocol')?></th>
						<th><?=gettext('IP Address'); ?></th>
						<th><?=gettext('Port'); ?></th>
						<th><?=gettext('Pool'); ?></th>
						<th><?=gettext('Fallback pool'); ?></th>
						<th><?=gettext('Description'); ?></th>
						<th><?=gettext('Actions'); ?></th>
					</tr>
				</thead>
				<tbody>
<?php
if (!empty($a_vs)) {
	$i = 0;
	foreach ($a_vs as $a_v) {

?>
					<tr>
						<td><?=htmlspecialchars($a_v['name'])?></td>
						<td><?=htmlspecialchars($a_v['relay_protocol'])?></td>
<?php

		$aidx = alias_idx($a_v['ipaddr'], "host");

		if ($aidx >= 0) {
			print("<td>\n");
			print('<a href="/firewall_aliases_edit.php?id=' . $aidx . '" data-toggle="popover" data-trigger="hover focus" title="Alias details" data-content="' . alias_info_popup($aidx) . '" data-html="true">');
			print(htmlspecialchars($a_v['ipaddr']) . '</a></td>');
		} else {
			print('<td>' . htmlspecialchars($a_v['ipaddr']) . '</td>');
		}

?>
						<td><?=htmlspecialchars($a_v['port'])?></td>
						<td><?=$a_v['poolname']?></td>
						<td><?=$a_v['sitedown']?></td>
						<td><?=htmlspecialchars($a_v['descr'])?></td>
						<td>
							<a class="fa fa-pencil"	title="<?=gettext('Edit virtual server')?>"	href="load_balancer_virtual_server_edit.php?id=<?=$i?>"></a>
							<a class="fa fa-clone"	title="<?=gettext('Copy virtual server')?>"	href="load_balancer_virtual_server_edit.php?act=dup&amp;id=<?=$i?>"></a>
							<a class="fa fa-trash"	title="<?=gettext('Delete virtual server')?>"	href="load_balancer_virtual_server.php?act=del&amp;id=<?=$i?>" usepost></a>
						</td>
					</tr>
<?php
		$i++;
	}
} else {
?>						<tr>
							<td	 colspan="8"> <?php
								print_info_box(gettext('No virtual servers have been configured.'));
?>							</td>
						</tr> <?php
}
?>
				</tbody>
			</table>
		</div>
	</div>
</form>

<nav class="action-buttons">
	<a href="load_balancer_virtual_server_edit.php" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>

<?php

include("foot.inc");
