<?php
/*
 * switch_ports.php
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
##|*IDENT=page-switch-ports
##|*NAME=Switch: Ports
##|*DESCR=Allow access to the 'Switch: Ports' page.
##|*MATCH=switch_ports.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("switch.inc");

$pgtitle = array(gettext("Interfaces"), gettext("Switch"), gettext("Ports"));
$shortcut_section = "ports";
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("System"), false, "switch_system.php");
$tab_array[] = array(gettext("Ports"), true, "switch_ports.php");
$tab_array[] = array(gettext("VLANs"), false, "switch_vlans.php");
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

// List the available switches
// ToDo: Check this is the correct way to get teh switch information
$swdevices = array();
$swdevices = glob("/dev/etherswitch*");

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

// If the selector was changed, the selected value becomes the default
if($_POST['swdevice']) {
	$swdevice = $_POST['swdevice'];
} else {
	$swdevice = $swdevices[0];
}

/* Just in case... */
pfSense_etherswitch_close();

if (pfSense_etherswitch_open($swdevice) == false) {
	$input_errors[] = "Cannot open the switch device\n";
}

$swinfo = pfSense_etherswitch_getinfo();

if ($swinfo == NULL) {
	pfSense_etherswitch_close();
	$input_errors[] = "Cannot get switch device information\n";
}

if ($input_errors) {
	print_input_errors($input_errors);
} else {
	// Don't draw the table if there were hardware errors
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=$ufwname . gettext('Switch Ports')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr>
						<th><?=gettext("Port #"); ?></th>
						<th><?=gettext("Port name"); ?></th>
						<th><?=gettext("Port VID"); ?></th>
						<th><?=gettext("Flags"); ?></th>
						<th><?=gettext("Media"); ?></th>
						<th><?=gettext("Status"); ?></th>
					</tr>
				</thead>
				<tbody>
<?php


	for ($i = 0; $i < $swinfo['nports']; $i++) {
		$port = pfSense_etherswitch_getport($i);
		if ($port == NULL) {
			continue;
		}
?>
					<tr>
						<td>
<?
		print(htmlspecialchars($port['port']));

?>
						</td>
						<td>
<?php
		$host = false;

		foreach ($port['flags'] as $flag => $val) {
			if ($flag == "HOST") {
				$host = true;
				break;
			}
		}

		if ($host == true) {
			echo " host";
		} else {
			$swport = switch_map_port($port['port']);
			if ($swport != NULL) {
				echo "$swport";
			}
		}
?>
						</td>
						<td>
							<?= htmlspecialchars($port['pvid'])?>
						</td>
						<td>
<?
		$comma = false;
		foreach ($port['flags'] as $flag => $val) {
			if ($comma)
				echo ",";
			echo "$flag";
			$comma = true;
		}
?>
						</td>
						<td>
<?
		if (isset($port['media']['current'])) {
			echo htmlspecialchars($port['media']['current']);
			if (isset($port['media']['active'])) {
				echo " (". htmlspecialchars($port['media']['active']) .")";
			}
		}

		print('</td>');

		switch (strtolower(htmlspecialchars($port['status']))) {
			case 'no carrier':
				print('<td class="text-danger">');
				break;
			case 'active':
				print('<td class="text-success">');
				break;
			default:
				print('<td>');
				break;
		}

		print(ucwords(htmlspecialchars($port['status'])));
?>
						</td>
					</tr>
<?
		}
	}

	pfSense_etherswitch_close();

?>
				</tbody>
			</table>
		</div>
	</div>
</div>

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
