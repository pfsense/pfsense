<?php
/*
 * switch_ports.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2018 Rubicon Communications, LLC (Netgate)
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

// Build an array with which to populate the switch device selector
function get_switches($devicelist) {

	$switches = array();

	foreach ($devicelist as $swdev) {

		$swinfo = pfSense_etherswitch_getinfo($swdev);
		if ($swinfo == NULL) {
			continue;
		}
		if ($swdevice == NULL)
			$swdevice = $swdev;

		$switches[$swdev] = $swinfo['name'];
	}

	return($switches);
}

function lagg_port($laggs = NULL, $port = NULL) {

	if ($laggs == NULL || $port == NULL) {
		return "-";
	}

	foreach ($laggs as $lagg) {
		if ($lagg['members'][$port] == 1) {
			return ($lagg['laggroup']);
		}
	}

	return "-";
}

// List the available switches
$swdevices = switch_get_devices();
$swtitle = switch_get_title();

// If the selector was changed, the selected value becomes the default
if($_POST['swdevice']) {
	$swdevice = $_POST['swdevice'];
} else {
	$swdevice = $swdevices[0];
}

$swinfo = pfSense_etherswitch_getinfo($swdevice);
if ($swinfo == NULL) {
	$input_errors[] = "Cannot get switch device information\n";
}

$swid = -1;
if (isset($config['switches']['switch']) && is_array($config['switches']['switch'])) {
	foreach($config['switches']['switch'] as $sid => $switch) {
		if (!isset($switch['device']) || $switch['device'] != $swdevice) {
			continue;
		}
		$swid = $sid;
		break;
	}
}

if ($_REQUEST['ajax'] === "ajax" && $_REQUEST['data']) {

	// Ensure there is some sort of switch configuration to work with
	if ($sw != -1) {
		$a_switch = &$config['switches']['switch'][$swid];
		/* Reset the swports array/nodes. */
		unset($a_switch['swports']);
		$a_switch['swports']['swport'] = array();
		if (isset($swinfo['switch_caps']['LAGG'])) {
			unset($a_switch['laggroups']);
			$a_switch['laggroups']['laggroup'] = array();
		}

		// Decode the JSON array
		$ja = json_decode($_REQUEST['data'], true);

		// Extract the port and VID from each item in the list
		foreach ($ja['data'] as $vid ) {
			$port = $vid['port'];
			$pvid = $vid['vid'];

			if (isset($swinfo['vlan_mode']) && $swinfo['vlan_mode'] == "DOT1Q" &&
			    !vlan_valid_tag($pvid) ) {
				$input_errors[] = sprintf(gettext("%d is not a valid VID for port %s"), $pvid, $port);
			} else {
				$swporto = array();
				$swporto['port'] = htmlspecialchars($port);
				if (vlan_valid_tag($pvid)) {
					$swporto['pvid'] = htmlspecialchars($pvid);
				}
				$swporto['state'] = "forwarding";
				$a_switch['swports']['swport'][] = $swporto;
			}
		}

		$laggs = array();
		foreach ($ja['data'] as $lagg) {
			if (!isset($lagg['lagg']) || !is_numeric($lagg['lagg'])) {
				continue;
			}
			$lid = htmlspecialchars($lagg['lagg']);
			if (!is_numeric($lid) || $lid > $swinfo['nlaggroups']) {
				$input_errors[] = sprintf(gettext("%d is not a valid LAGG group (max: %d)"), $lid, $swinfo['nlaggroups']);
				continue;
			}
			if (!isset($lagg['port']) || !is_numeric($lagg['port'])) {
				continue;
			}
			$port = htmlspecialchars($lagg['port']);
			if (!is_numeric($port) || $port > $swinfo['nports']) {
				$input_errors[] = sprintf(gettext("%d is not a valid port (max: %d)"), $port, $swinfo['nports']);
				continue;
			}
			$laggs[$lid]['lagg'] = $lid;
			$laggs[$lid]['members'] .= " ". $port;
		}

		if (! $input_errors && isset($swinfo['switch_caps']['LAGG'])) {
			$swlagg = array();
			foreach ($laggs as $lagg) {
				$swlagg['lgroup'] = $lagg['lagg'];
				$swlagg['members'] = trim($lagg['members']);
				$a_switch['laggroups']['laggroup'][] = $swlagg;
			}
		}

		if (! $input_errors) {
			write_config("Updating switch port settings");
			if (isset($swinfo['vlan_mode']) && $swinfo['vlan_mode'] == "DOT1Q") {
				foreach ($ja['data'] as $vid) {
					pfSense_etherswitch_setport($swdevice, $vid['port'], $vid['vid']);
				}
			}

			if (isset($swinfo['switch_caps']['LAGG'])) {
				for ($i = 0; $i < $swinfo['nlaggroups']; $i++) {
					$laggroup = pfSense_etherswitch_getlaggroup($swdevice, $i);
					if ($laggroup == NULL) {
						continue;
					}
					mwexec("/sbin/etherswitchcfg laggroup{$i} members none");
				}
				foreach ($laggs as $lagg) {
					$laggmembers = implode(",", explode(" ", trim($lagg['members'])));
					mwexec("/sbin/etherswitchcfg laggroup{$lagg['lagg']} members $laggmembers");
				}
			}

			$savemsg = gettext("Port settings updated.");
		}
	} else {
		$input_errors[] = sprintf(gettext("There is no switch configuration to modify!"));
	}
}

/* Load lagg settings _after_ editing. */
$g_laggs = array();
for ($i = 0; $i < $swinfo['nlaggroups']; $i++) {
	$laggroup = pfSense_etherswitch_getlaggroup($swdevice, $i);
	if ($laggroup == NULL) {
		continue;
	}
	$g_laggs[] = $laggroup;
}

$pgtitle = array(gettext("Interfaces"), gettext("Switch"), gettext("Ports"));
$shortcut_section = "ports";
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("System"), false, "switch_system.php");
$tab_array[] = array(gettext("Ports"), true, "switch_ports.php");
$tab_array[] = array(gettext("VLANs"), false, "switch_vlans.php");
display_top_tabs($tab_array);

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

}

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?= gettext($swtitle) ." ". gettext('Switch Ports')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table id="vlanporttable" class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr style="cursor:default;">
						<th><?=gettext("Port #"); ?></th>
						<th><?=gettext("Port name"); ?></th>
<?
	if (isset($swinfo['vlan_mode']) && $swinfo['vlan_mode'] == "DOT1Q") {
?>
						<th><?=gettext("Port VID"); ?></th>
<?
	}
	if (isset($swinfo['switch_caps']['LAGG'])) {
?>
						<th><?=gettext("LAGG"); ?></th>
<?
	}
?>
						<th><?=gettext("Flags"); ?></th>
<?
	if (isset($swinfo['switch_caps']) && $swinfo['switch_caps']['PSTATE'] == 1) {
?>
						<th><?=gettext("State"); ?></th>
<?
	}
?>
						<th><?=gettext("Media"); ?></th>
						<th><?=gettext("Status"); ?></th>
					</tr>
				</thead>
				<tbody id="vlanporttablebody">
<?php
if (! $input_errors) {
	for ($i = 0; $i < $swinfo['nports']; $i++) {
		if (!switch_port_is_enabled($swinfo, $i)) {
			continue;
		}
		$port = pfSense_etherswitch_getport($swdevice, $i);
		if ($port == NULL) {
			continue;
		}
?>
					<tr style="cursor:default;">
						<td>
<?
		print(htmlspecialchars($port['port']));

?>
						</td>
						<td>
<?php
		$swport = switch_map_port($port['port']);
		if ($swport != NULL) {
			echo "$swport";
		} else {
			print(htmlspecialchars($port['port']));
		}
?>
						</td>
<?
		if ($swinfo['vlan_mode'] == "DOT1Q") {
?>
			<td title="<?=gettext("Click to edit")?>" class="editable icon-pointer"><?= htmlspecialchars($port['pvid'])?></td>
<?
		}
		if (isset($swinfo['switch_caps']['LAGG'])) {
?>
			<td title="<?=gettext("Click to edit")?>" class="editable icon-pointer"><?= htmlspecialchars(lagg_port($g_laggs, $port['port']))?></td>
<?
		}
?>
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
<?
	if (isset($swinfo['switch_caps']) && $swinfo['switch_caps']['PSTATE'] == 1) {
?>
						<td>
<?
		$comma = false;
		foreach ($port['state'] as $state => $val) {
			if ($comma)
				echo ",";
			echo "$state";
			$comma = true;
		}
?>
						</td>
<?
	}
?>
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
?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<nav class="action-buttons">
<?php
	if (isset($swinfo['switch_caps']['LAGG'])) { ?>
	<span class="pull-left text-info"><?=gettext("Click a Port VID or a LAGG to edit")?></span>
<?php
	} else if ($swinfo['vlan_mode'] == "DOT1Q") { ?>
	<span class="pull-left text-info"><?=gettext("Click a Port VID to edit")?></span>
<?php } ?>
	<button name="submit" id="submit" type="submit" class="btn btn-primary btn-sm" value="<?= gettext("Save"); ?>" >
		<i class="fa fa-save icon-embed-btn"></i>
		<?=gettext("Save")?>
	</button>
</nav>

<div class="infoblock <?=$swinfo['vlan_mode'] == "DOT1Q" ? "":"blockopen" ?>">
<?php
	print_info_box(sprintf(gettext('%1$sVLAN IDs are displayed only if 802.1q VLAN mode is enabled on the "VLANs" tab. %2$s' .
		'The Port VIDs may be edited by clicking on the cell in the table above, then clicking "Save"'), '<b>', '</b><br />'), 'info', false);
?>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	$('#vlanporttable').editableTableWidget();	// Make the table cells editable (but only on <td>s with class editable)

	// Create a JSON array of VIDs by port, add it to a form and transmit
	$('#submit').on('click', function () {
		var form = $('<form></form>').attr("method", 'post');
		var json = '{"data":[';
		var entry = 0;

		$('#vlanporttablebody tr').each(function() {
			if (entry > 0) {
				json += ",\n";
			}

			json += '{"port":"' + $(this).find('td:eq(0)').text().trim() + '"';
		<?
			if ($swinfo['vlan_mode'] == "DOT1Q") {
		?>
				json += ', "vid":"' + $(this).find('td:eq(2)').text().trim() + '"';
		<?
			}
		?>
		<?
			if (isset($swinfo['switch_caps']['LAGG'])) {
				// If vlan_mode is not DOT1Q the vid column gets removed so
				// this will adjust the lagg column to get the right data
				$lagg_column = (($swinfo['vlan_mode'] == "DOT1Q") ? 3 : 2);
		?>
				json += ', "lagg":"' +
				$(this).find('td:eq(' + <? echo $lagg_column ?> + ')').text().trim() + '"';
		<?
			}
		?>
 			json += '}';
			entry++;
		});

		json += ']}';

		// Compose a form containing the PVIDs and submit it
		$('<input>').attr({
				type: 'hidden',
				name: 'data',
				value: json
			}).appendTo(form);

		$('<input>').attr({
				type: 'hidden',
				name: 'swdevice',
				value: '<?= $swdevice ?>'
			}).appendTo(form);

		$('<input>').attr({
				type: 'hidden',
				name: 'ajax',
				value: 'ajax'
			}).appendTo(form);

		$('<input>').attr({
				type: 'hidden',
				name: '__csrf_magic',
				value: csrfMagicToken
			}).appendTo(form);

		$('body').append(form);
		$('form').submit();
	});
});
//]]>
</script>

<?php
include("foot.inc");