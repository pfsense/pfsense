<?php
/*
 * interfaces_bridge.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-interfaces-bridge
##|*NAME=Interfaces: Bridge
##|*DESCR=Allow access to the 'Interfaces: Bridge' page.
##|*MATCH=interfaces_bridge.php*
##|-PRIV

require_once("guiconfig.inc");

init_config_arr(array('bridges', 'bridged'));
$a_bridges = &$config['bridges']['bridged'];

function bridge_inuse($num) {
	global $config, $a_bridges;

	$iflist = get_configured_interface_list(true);

	foreach ($iflist as $if) {
		if ($config['interfaces'][$if]['if'] == $a_bridges[$num]['bridgeif']) {
			return true;
		}
	}

	return false;
}

if ($_POST['act'] == "del") {
	if (!isset($_POST['id'])) {
		$input_errors[] = gettext("Wrong parameters supplied");
	} else if (empty($a_bridges[$_POST['id']])) {
		$input_errors[] = gettext("Wrong index supplied");
	/* check if still in use */
	} else if (bridge_inuse($_POST['id'])) {
		$input_errors[] = gettext("This bridge cannot be deleted because it is assigned as an interface.");
	} else {
		if (!does_interface_exist($a_bridges[$_POST['id']]['bridgeif'])) {
			log_error("Bridge interface does not exist, skipping ifconfig destroy.");
		} else {
			pfSense_interface_destroy($a_bridges[$_POST['id']]['bridgeif']);
		}

		unset($a_bridges[$_POST['id']]);

		write_config("Bridge deleted");

		header("Location: interfaces_bridge.php");
		exit;
	}
}

$pgtitle = array(gettext("Interfaces"), gettext("Bridges"));
$shortcut_section = "interfaces";
include("head.inc");
if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array(gettext("Interface Assignments"), false, "interfaces_assign.php");
$tab_array[] = array(gettext("Interface Groups"), false, "interfaces_groups.php");
$tab_array[] = array(gettext("Wireless"), false, "interfaces_wireless.php");
$tab_array[] = array(gettext("VLANs"), false, "interfaces_vlan.php");
$tab_array[] = array(gettext("QinQs"), false, "interfaces_qinq.php");
$tab_array[] = array(gettext("PPPs"), false, "interfaces_ppps.php");
$tab_array[] = array(gettext("GREs"), false, "interfaces_gre.php");
$tab_array[] = array(gettext("GIFs"), false, "interfaces_gif.php");
$tab_array[] = array(gettext("Bridges"), true, "interfaces_bridge.php");
$tab_array[] = array(gettext("LAGGs"), false, "interfaces_lagg.php");
display_top_tabs($tab_array);
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Bridge Interfaces')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr>
						<th><?=gettext("Interface"); ?></th>
						<th><?=gettext("Members"); ?></th>
						<th><?=gettext("Description"); ?></th>
						<th><?=gettext("Actions"); ?></th>
					</tr>
				</thead>
				<tbody>
<?php

$i = 0;
$ifdescrs = get_configured_interface_with_descr();

foreach ($a_bridges as $bridge) {
?>
					<tr>
						<td>
							<?=htmlspecialchars(strtoupper($bridge['bridgeif']))?>
						</td>
						<td>
<?php
	$members = explode(',', $bridge['members']);
	$j = 0;
	foreach ($members as $member) {
		if (isset($ifdescrs[$member])) {
			echo $ifdescrs[$member];
			$j++;
		}
		if ($j > 0 && $j < count($members)) {
			echo ", ";
		}
	}
?>
						</td>
						<td>
							<?=htmlspecialchars($bridge['descr'])?>
						</td>
						<td>
							<a class="fa fa-pencil"	title="<?=gettext('Edit interface bridge')?>"	href="interfaces_bridge_edit.php?id=<?=$i?>"></a>
							<a class="fa fa-trash"	title="<?=gettext('Delete interface bridge')?>"	href="interfaces_bridge.php?act=del&amp;id=<?=$i?>" usepost></a>
						</td>
					</tr>
<?php
	$i++;
}
?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<nav class="action-buttons">
	<a href="interfaces_bridge_edit.php" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>

<?php include("foot.inc");
