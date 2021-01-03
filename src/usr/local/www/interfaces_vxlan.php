<?php
/*
 * interfaces_vxlan.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-interfaces-vxlan
##|*NAME=Interfaces: VXLAN
##|*DESCR=Allow access to the 'Interfaces: VXLAN' page.
##|*MATCH=interfaces_vxlan.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");

init_config_arr(array('vxlans', 'vxlan'));
$a_vxlans = &$config['vxlans']['vxlan'] ;

function vxlan_inuse($num) {
	global $config, $a_vxlans;

	$iflist = get_configured_interface_list(true);
	foreach ($iflist as $if) {
		if ($config['interfaces'][$if]['if'] == $a_vxlans[$num]['vxlanif']) {
			return true;
		}
	}

	return false;
}

if ($_POST['act'] == "del") {
	if (!isset($_POST['id'])) {
		$input_errors[] = gettext("Wrong parameters supplied");
	} else if (empty($a_vxlans[$_POST['id']])) {
		$input_errors[] = gettext("Wrong index supplied");
	/* check if still in use */
	} else if (vxlan_inuse($_POST['id'])) {
		$input_errors[] = gettext("This VXLAN tunnel cannot be deleted because it is still being used as an interface.");
	} else {
		pfSense_interface_destroy($a_vxlans[$_POST['id']]['vxlanif']);
		unset($a_vxlans[$_POST['id']]);

		write_config("VXLAN interface deleted");

		header("Location: interfaces_vxlan.php");
		exit;
	}
}

$pgtitle = array(gettext("Interfaces"), gettext("VXLANs"));
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
$tab_array[] = array(gettext("VXLANs"), true, "interfaces_vxlan.php");
$tab_array[] = array(gettext("Bridges"), false, "interfaces_bridge.php");
$tab_array[] = array(gettext("LAGGs"), false, "interfaces_lagg.php");
display_top_tabs($tab_array);
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('VXLAN Interfaces')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr>
						<th><?=gettext("Interface"); ?></th>
						<th><?=gettext("Tunnel to"); ?></th>
						<th><?=gettext("Description"); ?></th>
						<th><?=gettext("Actions"); ?></th>
					</tr>
				</thead>
				<tbody>
<?php foreach ($a_vxlans as $i => $vxlan):
	if (substr($vxlan['if'], 0, 4) == "_vip") {
		$if = convert_real_interface_to_friendly_descr(get_real_interface($vxlan['if']));
	} else {
		$if = $vxlan['if'];
	}
?>
					<tr>
						<td>
							<?=htmlspecialchars(convert_friendly_interface_to_friendly_descr($if))?>
						</td>
						<td>
							<?=htmlspecialchars($vxlan['remote-addr'])?>
						</td>
						<td>
							<?=htmlspecialchars($vxlan['descr'])?>
						</td>
						<td>
							<a class="fa fa-pencil"	title="<?=gettext('Edit VXLAN interface')?>"	href="interfaces_vxlan_edit.php?id=<?=$i?>"></a>
							<a class="fa fa-trash"	title="<?=gettext('Delete VXLAN interface')?>"	href="interfaces_vxlan.php?act=del&amp;id=<?=$i?>" usepost></a>
						</td>
					</tr>
<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<nav class="action-buttons">
	<a href="interfaces_vxlan_edit.php" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>
<?php
include("foot.inc");
