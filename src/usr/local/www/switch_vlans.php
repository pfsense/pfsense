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

if (!is_array($config['switches']['switch'])) {
	$config['switches']['switch'] = array();
}

$a_switches = &$config['switches']['switch'];

$pgtitle = array(gettext("Interfaces"), gettext("Switch"), gettext("VLANs"));
$shortcut_section = "vlans";
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("System"), false, "switch_system.php");
$tab_array[] = array(gettext("Ports"), false, "switch_ports.php");
$tab_array[] = array(gettext("VLANs"), true, "switch_vlans.php");
display_top_tabs($tab_array);

// Probably uFW specific.
function find_pfSense_vlan($vlantag) {
	global $config;

	if (!isset($config["vlans"]["vlan"]) || !is_array($config["vlans"]["vlan"])) {
		return (NULL);
	}

	for ($i = 0; $i < count($config["vlans"]["vlan"]); $i++) {
		$vlan = $config["vlans"]["vlan"][$i];
		if ($vlan["tag"] != $vlantag) {
			continue;
		}

		return (array("id" => $i, "vlan" => $vlan));
	}

	return (NULL);
}

function find_vgroup_id($vgroups = NULL, $vlangroup = -1) {

	if ($vgroups == NULL || !is_array($vgroups) || $vlangroup == -1) {
		return (-1);
	}

	foreach($vgroups as $vgid => $vgroup) {
		if (!isset($vgroup['vgroup']) || $vgroup['vgroup'] != $vlangroup) {
			continue;
		}
		return ($vgid);
	}

	return (-1);
}

function get_config_descr($swid = -1, $vlangroup = -1) {
	global $config;

	if ($swid == -1 || $vlangroup == -1) {
		return (NULL);
	}
	$switch = $config['switches']['switch'][$swid];
	if (!isset($switch) || !is_array($switch)) {
		return (NULL);
	}
	$vgroups = $switch['vlangroups']['vlangroup'];
	if (!isset($vgroups) || !is_array($vgroups)) {
		return (NULL);
	}
	$vgid = find_vgroup_id($vgroups, $vlangroup);
	if ($vgid == -1) {
		return (NULL);
	}
	$vgroup = $vgroups[$vgid];

	if (!isset($vgroup['descr']) || strlen($vgroup['descr']) == 0) {
		return (NULL);
	}

	return ($vgroup['descr']);
}

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

// List the available switches
$swdevices = switch_get_devices();
$vlans_system = switch_get_system_vlans(false);
$swtitle = switch_get_title();

// If there is more than one switch, draw a selector to allow the user to choose which one to look at
if (count($swdevices) > 1) {
	$form = new Form(false);

	$section = new Form_Section('Select switch');

	$section->addInput(new Form_Select(
		'swdevice',
		'Switch',
		$_POST['swdevice'],
		get_switches($swdevices)
	));

	$form->add($section);

	print($form);

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


$swinfo = pfSense_etherswitch_getinfo($swdevice);
if ($swinfo == NULL) {
	$input_errors[] = "Cannot get switch device information\n";
}

$swid = -1;
foreach ($a_switches as $sid => $switch) {
	if ($switch['device'] != $swdevice) {
		continue;
	}
	$pconfig['vlanmode'] = $switch['vlanmode'];
	$swid = $sid;
}

$platform = system_identify_specific_platform();

// Delete a VLAN
if ($_POST['act'] == "del") {
	$vid = intval($_POST['vid']);

	$a_vgroups = &$a_switches[$swid]['vlangroups']['vlangroup'];
	if (is_array($a_vgroups) && vlan_valid_tag($vid)) {
		foreach($a_vgroups as $vgid => $vgroup) {
			if (!isset($vgroup['vgroup']) ||
			    !isset($vgroup['vlanid']) || $vgroup['vlanid'] != $vid) {
				continue;
			}
			pfSense_etherswitch_setvlangroup($swdevice, $vgroup['vgroup'], 0, array());
			unset($a_vgroups[$vgid]);
			write_config();
			break;
		}
	}

	header("Location: switch_vlans.php?swdevice=". htmlspecialchars($swdevice));
	exit;

} elseif (isset($_POST['save'])) {
	/* uFW is always run in DOT1Q mode. */
	if ($platform['name'] == 'uFW' || isset($_POST['dot1q'])) {
		$vlanmode = "DOT1Q";
	} else {
		$vlanmode = "PORT";
	}
	$pconfig['vlanmode'] = $vlanmode;

	$found = false;
	$update = false;
	foreach ($config['switches'] as $cswitch) {
		if ($cswitch['device'] != $swdevice) {
			continue;
		}
		$found = true;
		if ($cswitch['vlanmode'] != $vlanmode) {
			$update = true;
		}
	}

	if ($update || !$found) {

		$switch = array();
		$switch['device'] = $swdevice;
		$switch['vlanmode'] = $vlanmode;
		$found = false;
		foreach ($a_switches as $id => $cswitch) {
			if ($cswitch['device'] != $swdevice) {
				continue;
			}
			$a_switches[$id]['vlanmode'] = $vlanmode;
			$found = true;
		}
		if (!$found) {
			$a_switches[] = $switch;
		}

		/* Reset existing settings. */
		if ($found == true && is_array($a_switches[$id]['vlangroups']['vlangroup'])) {
			foreach($a_switches[$id]['vlangroups']['vlangroup'] as $vgid => $vgroup) {
				unset($a_switches[$id]['vlangroups']['vlangroup'][$vgid]);
			}
		}
		if ($found == true && is_array($a_switches[$id]['swports']['swport'])) {
			foreach($a_switches[$id]['swports']['swport'] as $pid => $swport) {
				unset($a_switches[$id]['swports']['swport'][$pid]);
			}
		}

		write_config();

		/* Set switch mode. */
		switch_set_vlan_mode($swdevice, $vlanmode);

		header("Location: switch_vlans.php?swdevice=". htmlspecialchars($swdevice));
		exit;
	}
}

if ($input_errors) {
	print_input_errors($input_errors);
} else {
	// Don't draw the table if there were hardware errors
	$form = new Form();

	if (isset($swinfo['vlan_mode']) && $swinfo['vlan_mode'] == "PORT") {
		$section = new Form_Section(gettext($swtitle) ." ". gettext('Switch Port based VLANs'));
	} else {
		$section = new Form_Section(gettext($swtitle) ." ". gettext('Switch 802.1Q VLANs'));
	}

	if ($platform['name'] != 'uFW') {
		$section->addInput(new Form_Checkbox(
			'dot1q',
			'Enable',
			'Enable 802.1q VLAN mode',
			($pconfig['vlanmode'] == 'DOT1Q' ? 'yes' : ''),
			'yes'
		))->setHelp('If enabled, packets with unknown VLAN tags will be dropped.');
	}

	$switch_table = ' 
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr>
						<th>'. gettext("VLAN group") ."</th>\n";
	if (isset($swinfo['vlan_mode']) && $swinfo['vlan_mode'] == "DOT1Q") {
		$switch_table .= '<th>'. gettext("VLAN tag") ."</th>\n";
	} else {
		$switch_table .= '<th>'. gettext("Port") ."</th>\n";
	}
	$switch_table .= "
						<th>". gettext("Members") ."</th>
						<th>". gettext("Description") ."</th>
						<th>". gettext("Action") ."</th>
					</tr>
				</thead>
				<tbody>\n";

	for ($i = 0; $i < $swinfo['nvlangroups']; $i++) {
		$vgroup = pfSense_etherswitch_getvlangroup($swdevice, $i);
		if ($vgroup == NULL) {
			continue;
		}
		$switch_table .= "
					<tr>
						<td>". htmlspecialchars($vgroup['vlangroup']) ."
						</td>
						<td>". htmlspecialchars($vgroup['vid']) ."
						</td>
						<td>\n";
		$comma = false;
		foreach ($vgroup['members'] as $member => $val) {
			if ($comma) {
				$switch_table .= ",";
			}
			$switch_table .= "$member";
			$comma = true;
		}
		$switch_table .= "
						</td>
						<td>\n";
		$sys = false;
		foreach ($vlans_system as $svlan) {
			if ($svlan['vid'] != $vgroup['vid']) {
				continue;
			}

			$switch_table .= "Default System VLAN";
			$sys = true;

			break;
		}
		if (!$sys && $platform['name'] == 'uFW') {
			$vlan = find_pfSense_vlan($vgroup['vid']);
			if ($vlan != NULL && is_array($vlan)) {
				$switch_table .= htmlspecialchars($vlan['vlan']['descr']);
			}
		} else {
			$descr = get_config_descr($swid, $vgroup['vlangroup']);
			if ($descr != NULL) {
				$switch_table .= htmlspecialchars($descr);
			}
		}
		$switch_table .= "
						</td>
						<td>\n";

		/* Allow regular VLAN editing on SG-1000. */
		if (!$sys && $platform['name'] == 'uFW' && isset($vlan) && $vlan != NULL && is_array($vlan)) {
			$switch_table .= '<a class="fa fa-pencil" title="'. gettext("Edit") .'" href="interfaces_vlan_edit.php?id='. htmlspecialchars($vlan['id']) .'"></a>';
			$switch_table .= '<a class="fa fa-trash no-confirm"  title="'. gettext('Delete VLAN') .'"     role="button" id="vlandel-'. $vlan['id'] .'"></a>';
		} elseif ($platform['name'] != 'uFW') {
			$switch_table .= '<a class="fa fa-pencil" title="'. gettext("Edit") .'" ';
			$switch_table .= 'href="switch_vlans_edit.php?act=edit&vgroupid='. htmlspecialchars($vgroup['vlangroup']);
			$switch_table .= '&swdevice='. $swdevice .'"></a>';
			if (isset($swinfo['vlan_mode']) && $swinfo['vlan_mode'] == "DOT1Q" && !$sys) {
				$switch_table .= '<a class="fa fa-trash no-confirm"  title="'. gettext('Delete VLAN tag') .'"     role="button" id="del-'. $vgroup['vid'] .'"></a>';
			}
		}
		$switch_table .= "
						</td>
					</tr>\n";
	}

	$switch_table .= "
				</tbody>
			</table>
		</div>\n";

	$section->addInput(new Form_StaticText(
		"VLAN(s) table",
		$switch_table
	));

if ($platform['name'] != 'uFW' && isset($swinfo['vlan_mode']) && $swinfo['vlan_mode'] == "DOT1Q") {
	$form->addGlobal(new Form_Button(
		'addtag',
		'Add Tag',
		"switch_vlans_edit.php?swdevice=". htmlspecialchars($swdevice) ."&act=new",
		'fa-plus'
	))->addClass('btn-success addbtn');
}

$form->add($section);
print($form);

} // e-o-if($input_errors) else . .

?>

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
        $delmsg = gettext("Are you sure you want to delete this VLAN tag?");
?>

<form name="vlan_edit_form" action="interfaces_vlan.php" method="post">
        <input id="vlanact" type="hidden" name="act" value="" />
        <input id="vlanid" type="hidden" name="id" value="" />
</form>
<form name="switch_vlans_form" action="switch_vlans.php" method="post">
        <input id="act" type="hidden" name="act" value="" />
        <input id="vid" type="hidden" name="vid" value="" />
</form>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// Select 'delete button' clicks, extract the id, set the hidden input values and submit
	$('[id^=vlandel-]').click(function(event) {
		if (confirm("<?=$delmsg?>")) {
			$('#vlanact').val('del');
			$('#vlanid').val(this.id.replace("vlandel-", ""));
			$('form[name="vlan_edit_form"]').submit();
		}
	});

	$('[id^=del-]').click(function(event) {
		if (confirm("<?=$delmsg?>")) {
			$('#act').val('del');
			$('#vid').val(this.id.replace("del-", ""));
			$('form[name="switch_vlans_form"]').submit();
		}
	});

});
//]]>
</script>

<?php
include("foot.inc");
