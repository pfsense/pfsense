<?php
/*
 * switch_laggs.php
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
##|*IDENT=page-switch-laggs
##|*NAME=Switch: LAGGs
##|*DESCR=Allow access to the 'Switch: LAGGs' page.
##|*MATCH=switch_laggs.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("switch.inc");

if (!is_array($config['switches']['switch'])) {
	$config['switches']['switch'] = array();
}

$a_switches = &$config['switches']['switch'];

$pgtitle = array(gettext("Interfaces"), gettext("Switch"), gettext("LAGGs"));
$shortcut_section = "laggs";
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("System"), false, "switch_system.php");
$tab_array[] = array(gettext("Ports"), false, "switch_ports.php");
$tab_array[] = array(gettext("VLANs"), false, "switch_vlans.php");
$tab_array[] = array(gettext("LAGGs"), true, "switch_laggs.php");
display_top_tabs($tab_array);

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
unset($input_errors);

if ($input_errors) {
	print_input_errors($input_errors);
} else {
	// Don't draw the table if there were hardware errors
	$form = new Form(false);

	$section = new Form_Section(gettext($swtitle) ." ". gettext('Switch LAGGs'));

	$switch_table = ' 
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr>
						<th>'. gettext("LAGG group") ."</th>
						<th>". gettext("Members") ."</th>
						<th>". gettext("Description") ."</th>
						<th>". gettext("Action") ."</th>
					</tr>
				</thead>
				<tbody>\n";

	for ($i = 0; $i < $swinfo['nlaggroups']; $i++) {
		$laggroup = pfSense_etherswitch_getlaggroup($swdevice, $i);
		if ($laggroup == NULL) {
			continue;
		}
		$switch_table .= "
					<tr>
						<td>". htmlspecialchars($laggroup['laggroup']) ."
						</td>
						<td>\n";
		$comma = false;
		foreach ($laggroup['members'] as $member => $val) {
			if ($comma) {
				$switch_table .= ",";
			}
			$switch_table .= "$member";
			$comma = true;
		}
		$switch_table .= "
						</td>
						<td>\n";
		$descr = get_config_descr($swid, $laggroup['descr']);
		if ($descr != NULL) {
			$switch_table .= htmlspecialchars($descr);
		}
		$switch_table .= "
						</td>
 						<td>
 						</td>\n";
/*
 *						<td>\n";
 *
 *		$switch_table .= '<a class="fa fa-pencil" title="'. gettext("Edit") .'" ';
 *		$switch_table .= 'href="switch_laggs_edit.php?act=edit&laggroupid='. htmlspecialchars($laggroup['laggroup']);
 *		$switch_table .= '&swdevice='. $swdevice .'"></a>';
 *		$switch_table .= '<a class="fa fa-trash no-confirm"  title="'. gettext('Delete LAGG group') .'"     role="button" id="del-'. $laggroup['laggid'] .'"></a>';
 *		$switch_table .= "
 *						</td>
 */
"
					</tr>\n";
	}

	$switch_table .= "
				</tbody>
			</table>
		</div>\n";

	$section->addInput(new Form_StaticText(
		"LAGG(s) table",
		$switch_table
	));

/*
 * $form->addGlobal(new Form_Button(
 * 	'addtag',
 * 	'Add LAGG',
 * 	"switch_laggs_edit.php?swdevice=". htmlspecialchars($swdevice) ."&act=new",
 * 	'fa-plus'
 * ))->addClass('btn-success addbtn');
 */

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
include("foot.inc");
