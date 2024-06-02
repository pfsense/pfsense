<?php
/*
 * interfaces_wireless.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2010 Erik Fonnesbeck
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
##|*IDENT=page-interfaces-wireless
##|*NAME=Interfaces: Wireless
##|*DESCR=Allow access to the 'Interfaces: Wireless' page.
##|*MATCH=interfaces_wireless.php*
##|-PRIV

require_once("guiconfig.inc");

config_init_path('wireless/clone');

function clone_inuse($num) {
	$a_clones = config_get_path('wireless/clone');
	$iflist = get_configured_interface_list(true);
	$if_config = config_get_path('interfaces');
	foreach ($iflist as $if) {
		if ($if_config[$if]['if'] == $a_clones[$num]['cloneif']) {
			return true;
		}
	}

	return false;
}

if ($_POST['act'] == "del") {
	/* check if still in use */
	if (clone_inuse($_POST['id'])) {
		$input_errors[] = gettext("This wireless clone cannot be deleted because it is assigned as an interface.");
	} else {
		pfSense_interface_destroy(config_get_path("wireless/clone/{$_POST['id']}/cloneif"));
		config_del_path("wireless/clone/{$_POST['id']}");

		write_config("Wireless interface deleted");

		header("Location: interfaces_wireless.php");
		exit;
	}
}


$pgtitle = array(gettext("Interfaces"), gettext("Wireless"));
$shortcut_section = "wireless";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array(gettext("Interface Assignments"), false, "interfaces_assign.php");
$tab_array[] = array(gettext("Interface Groups"), false, "interfaces_groups.php");
$tab_array[] = array(gettext("Wireless"), true, "interfaces_wireless.php");
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
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Wireless Interfaces')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr>
						<th><?=gettext("Interface"); ?></th>
						<th><?=gettext("Mode"); ?></th>
						<th><?=gettext("Description"); ?></th>
						<th><?=gettext("Actions"); ?></th>
					</tr>
				</thead>
				<tbody>
<?php

$i = 0;

foreach (config_get_path('wireless/clone', []) as $clone) {
?>
					<tr>
						<td>
							<?=htmlspecialchars($clone['cloneif'])?>
						</td>
						<td>
							<?= $wlan_modes[$clone['mode']]; ?>
						</td>
						<td>
							<?=htmlspecialchars($clone['descr'])?>
						</td>
						<td>
							<a class="fa-solid fa-pencil"	title="<?=gettext('Edit WiFi interface')?>"	href="interfaces_wireless_edit.php?id=<?=$i?>"></a>
							<a class="fa-solid fa-trash-can"	title="<?=gettext('Delete WiFi interface')?>"	href="interfaces_wireless.php?act=del&amp;id=<?=$i?>" usepost></a>
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
	<a href="interfaces_wireless_edit.php" class="btn btn-success btn-sm">
		<i class="fa-solid fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?></a>
</nav>
<?php
include("foot.inc");
