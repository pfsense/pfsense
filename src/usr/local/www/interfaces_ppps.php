<?php
/*
 * interfaces_ppps.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
##|*IDENT=page-interfaces-ppps
##|*NAME=Interfaces: PPPs
##|*DESCR=Allow access to the 'Interfaces: PPPs' page.
##|*MATCH=interfaces_ppps.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");

function ppp_inuse($num) {
	global $config, $g;

	$iflist = get_configured_interface_list(true);
	if (!is_array($config['ppps']['ppp'])) {
		return false;
	}

	foreach ($iflist as $if) {
		if ($config['interfaces'][$if]['if'] == $config['ppps']['ppp'][$num]['if']) {
			return true;
		}
	}

	return false;
}

if ($_POST['act'] == "del") {
	/* check if still in use */
	if (ppp_inuse($_POST['id'])) {
		$input_errors[] = gettext("This point-to-point link cannot be deleted because it is still being used as an interface.");
	} elseif (is_array($config['ppps']['ppp']) && is_array($config['ppps']['ppp'][$_POST['id']])) {

		unset($config['ppps']['ppp'][$_POST['id']]['pppoe-reset-type']);
		handle_pppoe_reset($config['ppps']['ppp'][$_POST['id']]);
		unset($config['ppps']['ppp'][$_POST['id']]);
		write_config();
		header("Location: interfaces_ppps.php");
		exit;
	}
}

if (!is_array($config['ppps'])) {
	$config['ppps'] = array();
}

if (!is_array($config['ppps']['ppp'])) {
	$config['ppps']['ppp'] = array();
}

$a_ppps = $config['ppps']['ppp'];

$pgtitle = array(gettext("Interfaces"), gettext("PPPs"));
$shortcut_section = "interfaces";
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Interface Assignments"), false, "interfaces_assign.php");
$tab_array[] = array(gettext("Interface Groups"), false, "interfaces_groups.php");
$tab_array[] = array(gettext("Wireless"), false, "interfaces_wireless.php");
$tab_array[] = array(gettext("VLANs"), false, "interfaces_vlan.php");
$tab_array[] = array(gettext("QinQs"), false, "interfaces_qinq.php");
$tab_array[] = array(gettext("PPPs"), true, "interfaces_ppps.php");
$tab_array[] = array(gettext("GREs"), false, "interfaces_gre.php");
$tab_array[] = array(gettext("GIFs"), false, "interfaces_gif.php");
$tab_array[] = array(gettext("VXLANs"), false, "interfaces_vxlan.php");
$tab_array[] = array(gettext("Bridges"), false, "interfaces_bridge.php");
$tab_array[] = array(gettext("LAGGs"), false, "interfaces_lagg.php");
display_top_tabs($tab_array);
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('PPP Interfaces')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr>
						<th><?=gettext("Interface"); ?></th>
						<th><?=gettext("Interface(s)/Port(s)"); ?></th>
						<th><?=gettext("Description"); ?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody>
<?php

$i = 0;


if (is_array($a_ppps)) {
	foreach ($a_ppps as $id => $ppp) {
?>
					<tr>
						<td>
							<?=htmlspecialchars($ppp['if'])?>
						</td>
						<td>
<?php
	$portlist = explode(",", $ppp['ports']);
	foreach ($portlist as $portid => $port) {
		if ($port != get_real_interface($port) && $ppp['type'] != "ppp") {
			$portlist[$portid] = convert_friendly_interface_to_friendly_descr($port);
		}
	}
							echo htmlspecialchars(implode(",", $portlist));
?>
						</td>
						<td>
							<?=htmlspecialchars($ppp['descr'])?>
						</td>
						<td>
							<a class="fa fa-pencil"	title="<?=gettext('Edit PPP interface')?>"	href="interfaces_ppps_edit.php?id=<?=$i?>"></a>
							<a class="fa fa-trash"	title="<?=gettext('Delete PPP interface')?>"	href="interfaces_ppps.php?act=del&amp;id=<?=$i?>" usepost></a>
						</td>
					</tr>
<?php
	$i++;
	}
}
?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<nav class="action-buttons">
	<a href="interfaces_ppps_edit.php" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>

<?php
include("foot.inc");
