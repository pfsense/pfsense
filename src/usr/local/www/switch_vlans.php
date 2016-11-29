<?php
/*
 * switch_vlans.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-switch-vlans
##|*NAME=Switch: VLANs
##|*DESCR=Allow access to the 'Switch: VLANs' page.
##|*MATCH=switch_vlans.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("switch.inc");

$pgtitle = array(gettext("Interfaces"), gettext("Switch"), gettext("VLANs"));
$shortcut_section = "vlans";
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("System"), false, "switch_system.php");
$tab_array[] = array(gettext("Ports"), false, "switch_ports.php");
$tab_array[] = array(gettext("VLANs"), true, "switch_vlans.php");
display_top_tabs($tab_array);


// Build an array with which to populate the switch device selector
function get_switches($devicelist) {

	$switches = array();

	foreach ($devicelist as $swdev) {
		/* Just in case... */
		pfSense_etherswitch_close();

		if (pfSense_etherswitch_open($swdev) == false) {
			continue;
		}

		$swinfo = pfSense_etherswitch_getinfo();

		if ($swinfo == NULL) {
			pfSense_etherswitch_close();
			continue;
		}
		if ($swdevice == NULL)
			$swdevice = $swdev;

		$switches[$swdev] = $swinfo['name'];

		pfSense_etherswitch_close();
	}

	return($switches);
}

// Delete a VLAN
if ($_GET['act'] == "del") {
	$vid = $_GET['vid'];
	$device = $_GET['swdevice'];

	print("Deleting VID: " . $vid . " from device: " . $device . "<br />");

	// ToDo: Add some code to delete the VLAN
}

// List the available switches
// ToDo: Check this is the correct way to get teh switch information
$swdevices = array();
$swdevices = glob("/dev/etherswitch*");
$vlans_system = switch_get_system_vlans();

// If there is more than one switch, draw a selector to allow the user to choose which one to look at
if (count($swdevices) > 1) {
	$form = new Form(false);

	$section = new Form_Section('Dynamic DNS Client');

	$section->addInput(new Form_Select(
		'swdevice',
		'Switch',
		$_POST['swdevice'],
		get_switches($swdevices)
	));

	$form->add($section);

	print($form);

} else {
	// If running on a Netgate micro-firewall, display that in the panel title
	if (system_identify_specific_platform()['name'] == "uFW") {
		$ufwname = "uFW ";
	}
}

if ($_GET['swdevice']) {
	$_POST['swdevice'] = $_GET['swdevice'];
}

// If the selector was changed, the selected value becomes the default
if($_POST['swdevice']) {
	$swdevice = $_POST['swdevice'];
} else {
	$swdevice = $swdevices[0];
}


pfSense_etherswitch_close();

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Switch VLANs')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr>
						<th><?=gettext("VLAN group"); ?></th>
						<th><?=gettext("VLAN ID"); ?></th>
						<th><?=gettext("Members"); ?></th>
						<th><?=gettext("Description"); ?></th>
						<th><?=gettext("Action"); ?></th>
					</tr>
				</thead>
				<tbody>
<?php

/* Just in case... */
pfSense_etherswitch_close();

if (pfSense_etherswitch_open($swdevice) == false) {
	continue;
}

$swinfo = pfSense_etherswitch_getinfo();
if ($swinfo == NULL) {
	pfSense_etherswitch_close();
	continue;
}

for ($i = 0; $i < $swinfo['nvlangroups']; $i++) {
	$vgroup = pfSense_etherswitch_getvlangroup($i);
	if ($vgroup == NULL) {
		continue;
	}
?>
					<tr>
						<td>
							<?= htmlspecialchars($vgroup['vlangroup']); ?>
						</td>
						<td>
							<?= htmlspecialchars($vgroup['vid']); ?>
						</td>
						<td>
<?
	$comma = false;

	foreach ($vgroup['members'] as $member => $val) {
		if ($comma) {
			echo ",";
		}

		echo "$member";
		$comma = true;
	}
?>
						</td>
						<td>
<?
	foreach ($vlans_system as $svlan) {
		if ($svlan['vid'] != $vgroup['vid']) {
			$sys = true;
			continue;
		}

		echo "Default System VLAN";
		$sys = false;

		break;
	}
?>
						</td>
						<td>
<?php
		if (!$sys) { ?>
							<a class="fa fa-pencil" title="<?=gettext("Edit"); ?>" href="switch_vlans_edit.php?vid=<?=htmlspecialchars($vgroup['vid'])?>&amp;act=edit&amp;swdevice=<?=$swdevice?>"></a>
							<a class="fa fa-trash"	title="<?=gettext("Delete")?>" href="?act=del&amp;vid=<?=htmlspecialchars($vgroup['vid'])?>&amp;swdevice=<?=$swdevice?>"></a>
<?php 	} ?>
						</td>
					</tr>
<?
	}

	pfSense_etherswitch_close();

?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<nav class="action-buttons">
	<a href="switch_vlans_edit.php?swdevice=<?=$swdevice?>&amp;act=new" role="button" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add");?>
	</a>
</nav>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Automatically submit the form when the selector is changed
	$('#swdevice').on('change', function () {
		$('form').submit();
	});
});
//]]>
</script>

<?php
include("foot.inc");
