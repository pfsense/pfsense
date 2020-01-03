<?php
/*
 * interfaces_groups.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-interfaces-groups
##|*NAME=Interfaces: Groups
##|*DESCR=Create interface groups
##|*MATCH=interfaces_groups.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");

init_config_arr(array('ifgroups', 'ifgroupentry'));
$a_ifgroups = &$config['ifgroups']['ifgroupentry'];

if ($_POST['act'] == "del") {
	if ($a_ifgroups[$_POST['id']]) {
		$members = explode(" ", $a_ifgroups[$_POST['id']]['members']);
		foreach ($members as $ifs) {
			$realif = get_real_interface($ifs);
			if ($realif) {
				mwexec("/sbin/ifconfig {$realif} -group " . $a_ifgroups[$_POST['id']]['ifname']);
			}
		}
		unset($a_ifgroups[$_POST['id']]);
		write_config();
		header("Location: interfaces_groups.php");
		exit;
	}
}

$pgtitle = array(gettext("Interfaces"), gettext("Interface Groups"));
$shortcut_section = "interfaces";

include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Interface Assignments"), false, "interfaces_assign.php");
$tab_array[] = array(gettext("Interface Groups"), true, "interfaces_groups.php");
$tab_array[] = array(gettext("Wireless"), false, "interfaces_wireless.php");
$tab_array[] = array(gettext("VLANs"), false, "interfaces_vlan.php");
$tab_array[] = array(gettext("QinQs"), false, "interfaces_qinq.php");
$tab_array[] = array(gettext("PPPs"), false, "interfaces_ppps.php");
$tab_array[] = array(gettext("GREs"), false, "interfaces_gre.php");
$tab_array[] = array(gettext("GIFs"), false, "interfaces_gif.php");
$tab_array[] = array(gettext("Bridges"), false, "interfaces_bridge.php");
$tab_array[] = array(gettext("LAGGs"), false, "interfaces_lagg.php");
display_top_tabs($tab_array);
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Interface Groups')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr>
						<th><?=gettext('Name');?></th>
						<th><?=gettext('Members');?></th>
						<th><?=gettext('Description');?></th>
						<th><?=gettext('Actions');?></th>
					</tr>
				</thead>
				<tbody>
<?php foreach ($a_ifgroups as $i => $ifgroupentry): ?>
					<tr>
						<td>
							<?=htmlspecialchars($ifgroupentry['ifname']); ?>
						</td>
						<td>
<?php
		$members_arr = explode(" ", $ifgroupentry['members']);
		$iflist = get_configured_interface_with_descr(true);
		$memberses_arr = array();
		foreach ($members_arr as $memb) {
			$memberses_arr[] = $iflist[$memb] ? $iflist[$memb] : $memb;
		}

		unset($iflist);
		$memberses = implode(", ", $memberses_arr);
		echo $memberses;
		if (count($members_arr) >= 10) {
			echo '&hellip;';
		}
?>
						</td>
						<td>
							<?=htmlspecialchars($ifgroupentry['descr']);?>
						</td>
						<td>
							<a class="fa fa-pencil"	title="<?=gettext('Edit group')?>"	href="interfaces_groups_edit.php?id=<?=$i; ?>"></a>
							<a class="fa fa-trash"	title="<?=gettext('Delete group')?>"	href="interfaces_groups.php?act=del&amp;id=<?=$i; ?>" usepost></a>
						</td>
					</tr>
<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<nav class="action-buttons">
	<a class="btn btn-success btn-sm" href="interfaces_groups_edit.php" role="button">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add");?>
	</a>
</nav>

<div class="infoblock">
	<?php print_info_box(sprintf(gettext('Interface Groups allow setting up rules for multiple interfaces without duplicating the rules.%s' .
					   'If members are removed from an interface group, the group rules are no longer applicable to that interface.'), '<br />'), 'info', false); ?>

</div>
<?php

include("foot.inc");
