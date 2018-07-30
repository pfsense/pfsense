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
##|*IDENT=page-switch-vlans-edit
##|*NAME=Switch: VLANs Edit
##|*DESCR=Allow access to the 'Switch: VLANs Edit' page.
##|*MATCH=switch_vlans_edit.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("switch.inc");

if (!is_array($config['switches'])) {
        $config['switches'] = array();
}

if (!is_array($config['switches']['switch'])) {
        $config['switches']['switch'] = array();
}

$a_switches = &$config['switches']['switch'];

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

function find_vgroup_id_by_vlan($vgroups = NULL, $vlan = -1) {

	if ($vgroups == NULL || !is_array($vgroups) ||
	    $vlan == -1 || !vlan_valid_tag($vlan)) {
		return (-1);
	}

	foreach($vgroups as $vgid => $vgroup) {
		if (!isset($vgroup['vlanid']) || $vgroup['vlanid'] != $vlan) {
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

$pgtitle = array(gettext("Interfaces"), gettext("Switch"), gettext("VLANs"), gettext("Edit"));

$pgtitle = array(gettext("Interfaces"), gettext("Switch"), gettext("VLANs"), gettext("Edit"));
$pglinks = array("", "", "switch_vlans.php", "@self");
$shortcut_section = "vlans";
include("head.inc");

/* Check swdevice */
if (!isset($_REQUEST['swdevice'])) {
	header("Location: switch_vlans.php");
	exit;
}

$swdevice = $_REQUEST['swdevice'];
$swinfo = pfSense_etherswitch_getinfo($swdevice);

if ($swinfo == NULL) {
	header("Location: switch_vlans.php");
	exit;
}

$swid = -1;

foreach($a_switches as $switchid => $switch) {
	if ($switch['device'] != $swdevice) {
		continue;
	}
	$swid = $switchid;
	break;
}

if ($swid == -1) {
	header("Location: switch_vlans.php");
	exit;
}

if (!is_array($a_switches[$swid]['vlangroups'])) {
        $a_switches[$swid]['vlangroups'] = array();
}

if (!is_array($a_switches[$swid]['vlangroups']['vlangroup'])) {
        $a_switches[$swid]['vlangroups']['vlangroup'] = array();
}

$a_vgroups = &$a_switches[$swid]['vlangroups']['vlangroup'];

unset ($input_errors);

if ($_POST) {

	$members = "";

	// Read the POSTed member array into a space separated list translating any ranges
	// into their included values
	$membercounter = 0;
	$membername = "member{$membercounter}";
	$valid_members = array();
	$posted_tagged = array();
	$posted_members = array();

	while (isset($_POST[$membername])) {
		if (!switch_port_is_enabled($swinfo, intval($_POST[$membername]))) {
			$input_errors[] = "Invalid port: {$_POST[$membername]}";
		}

		if (isset($_POST["tagged{$membercounter}"])) {
			if (empty($input_errors)) {
				$valid_members[] = intval($_POST[$membername]). "t";
			}
			$posted_tagged[] = intval($_POST[$membername]);
		} else {
			if (empty($input_errors)) {
				$valid_members[] = intval($_POST[$membername]);
			}
		}

		// Remember the POSTed values so they can be redisplayed if there were errors.
		$posted_members[] = intval($_POST[$membername]);

		$membercounter++;
		$membername = "member{$membercounter}";
	}

	if (isset($_POST['vgroupid']) && is_numeric($_POST['vgroupid'])) {
		if (!is_numeric($_POST['vgroupid']) || $_POST['vgroupid'] < 0 ||
		    $_POST['vgroupid'] >= $swinfo['nvlangroups']) {
			$input_errors[] = "Invalid vlangroup: {$_POST['vgroupid']}";
		}
		$vgroupid = $_POST['vgroupid'];
	} else {
		$vgroupid = -1;
	}
	if (isset($swinfo['vlan_mode']) && $swinfo['vlan_mode'] == "DOT1Q") {
		if (!vlan_valid_tag($_POST['vlanid'])) {
			$input_errors[] = "Invalid VLAN tag: {$_POST['vlanid']}";
		}
		if ($_POST['oldvid'] == 1 && $_POST['vlanid'] != $_POST['oldvid']) {
			$input_errors[] = "VLAN tag 1 cannot change.  Restoring original value.";
			$_POST['vlanid'] = 1;
		}
	} else {
		if (isset($_POST['oldvid']) && $_POST['vlanid'] != $_POST['oldvid']) {
			$input_errors[] = "PORT cannot change.  Restoring original value.";
			$_POST['vlanid'] = $_POST['vgroupid'];
		}
	}
	$vgid = find_vgroup_id($a_vgroups, $vgroupid);
	$duplicate = find_vgroup_id_by_vlan($a_vgroups, $_POST['vlanid']);
	if ($vgid == -1 && $duplicate != -1 ||
	    $vgid != -1 && $duplicate != -1 && $vgid != $duplicate) {
		$input_errors[] = "VLAN tag is already in use: {$_POST['vlanid']}";
	}

	if (empty($input_errors)) {

		$vgmembers = array();
		foreach($posted_members as $m) {
			$vgmembers[$m] = array();
			if (in_array($m, $posted_tagged)) {
				$vgmembers[$m]['tagged'] = 1;
			}
		}
		$err = pfSense_etherswitch_setvlangroup($swdevice, $vgroupid, $_POST['vlanid'], $vgmembers);
		if ($err == false) {
		/* XXX */
		}
		if ($vgroupid == -1) {
			$vgroupid = $err;
		}
		$vgroup = array();
		$vgroup['vgroup'] = $vgroupid;
		$vgroup['vlanid'] = $_POST['vlanid'];
		$vgroup['descr'] = $_POST['descr'];
		$vgroup['members'] = implode(" ", $valid_members);
		if ($vgid != -1 && isset($a_vgroups[$vgid]) && is_array($a_vgroups[$vgid])) {
			$a_vgroups[$vgid] = $vgroup;
		} else {
			$a_vgroups[] = $vgroup;
		}
		write_config();

		header("Location: switch_vlans.php?swdevice=". htmlspecialchars($swdevice));
		exit;
	} else {
		$pconfig['vgroupid'] = $_GET['vgroupid'];
		$pconfig['vlanid'] = $_POST['vlanid'];
		$pconfig['descr'] = $_POST['descr'];
		$pconfig['tagged'] = $posted_tagged;
		$pconfig['members'] = $posted_members;
	}
} else {

	if (!isset($_GET['act'])) {
		header("Location: switch_vlans.php?swdevice=". htmlspecialchars($swdevice));
		exit;
	}
	switch ($_GET['act']) {
		case "edit" :
			$pconfig['vgroupid'] = $_GET['vgroupid'];

			$vgroup = pfSense_etherswitch_getvlangroup($swdevice, $pconfig['vgroupid']);
			if ($vgroup == NULL) {
				header("Location: switch_vlans.php?swdevice=". htmlspecialchars($swdevice));
				exit;
			}

			$pconfig['vlanid'] = $vgroup['vid'];
			$descr = get_config_descr($swid, $vgroup['vlangroup']);
			if ($descr != NULL) {
				$pconfig['descr'] = $descr;
			} else {
				$pconfig['descr'] = "";
				$vlans_system = switch_get_system_vlans(false);
				foreach ($vlans_system as $svlan) {
					if ($svlan['vid'] != $vgroup['vid']) {
						continue;
					}

					$pconfig['descr'] = "Default System VLAN";
					break;
				}
			}

			$pconfig['tagged'] = array();
			$pconfig['members'] = array();
			foreach ($vgroup['members'] as $member => $val) {
				$tagpos = strpos($member, "t");
				if ($tagpos != false) {
					$member = substr($member, 0, $tagpos);
					$pconfig['tagged'][] = $member;
				}
				$pconfig['members'][] = $member;
			}

		break;

		case "new" :
			$pconfig['vgroupid'] = "";
			$pconfig['vlanid'] = "";
			$pconfig['descr'] = "";
			$pconfig['members'] = array('');
			$pconfig['tagged'] = array();
		break;

		default:
			header("Location: switch_vlans.php?swdevice=". htmlspecialchars($swdevice));
			exit;
	}
}

$form = new Form();

$section = new Form_Section("Vlan properties");

if (isset($swinfo['vlan_mode']) && $swinfo['vlan_mode'] == "DOT1Q") {
	$vidtag = "VLAN tag";
	$vidhelp = "Enter a VLAN ID number (that is not already in use.)";
} else {
	$vidtag = "PORT";
	$vidhelp = "";
}

$section->addInput(new Form_Input(
	'vlanid',
	$vidtag,
	'number',
	$pconfig['vlanid']
))->setHelp($vidhelp);

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp("A description may be entered here for administrative reference (not parsed).");

$counter = 0;
foreach ($pconfig['members'] as $member) {

	$group = new Form_Group($counter == 0 ? '*Member(s)':'');
	$group->addClass('repeatable');

	$group->add(new Form_Input(
		'member' . $counter,
		null,
		'text',
		$member
	))->setWidth(6); // Width must be <= 8 to make room for the duplication buttons

	if (isset($swinfo['vlan_mode']) && $swinfo['vlan_mode'] == "DOT1Q") {
		if (in_array($member, $pconfig['tagged'])) {
			$checked = 'yes';
		} else {
			$checked = '';
		}

		$group->add(new Form_Checkbox(
			'tagged' . $counter,
			null,
			'tagged',
			$checked,
			'yes'
		));
	}

	$group->add(new Form_Button(
		'deleterow' . $counter,
		'Delete',
		null,
		'fa-trash'
	))->addClass('btn-warning');

	$section->add($group);
	$counter++;
}

$form->addGlobal(new Form_Button(
	'addrow',
	'Add member',
	null,
	'fa-plus'
))->addClass('btn-success addbtn');

if (isset($pconfig['vgroupid'])) {
	$section->addInput(new Form_Input(
		'vgroupid',
		null,
		'hidden',
		$pconfig['vgroupid']
	));
}

$section->addInput(new Form_Input(
	'oldvid',
	null,
	'hidden',
	$pconfig['vlanid']
));

$form->add($section);

if ($input_errors) {
	print_input_errors($input_errors);
}

print($form);

?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {

	function updateMembers(mem) {
		var found = false;

		// If the member exists, remove it
		$('[id="members[]"] option').each(function() {
		    if($(this).val() == mem) {
		    	$(this).remove();
				found = true;
		    }
		});

		// If not, add it
	    if (!found) {
			$('[id="members[]"]').append(new Option('Port ' + mem , mem));
	    }

	    // Sort alphabetically
		var options = $('[id="members[]"] option');

		options.detach().sort(function(a,b) {
		    var at = $(a).text();
		    var bt = $(b).text();
		    return (at > bt)?1:((at < bt)?-1:0);
		});

		options.appendTo('[id="members[]"]');

		// Unselect all options
		$('[id="availports[]"] option:selected').removeAttr("selected");


	}

	// On click, update the members list
	$('[id="availports[]"]').click(function () {
		updateMembers($(this).val());
	});

	// When the form is posted, all options in members must be selected if htey are to submit
	$("#save").prop('type' ,'button');

	$('#save').click(function() {
		$('[id="members[]"] option').prop('selected', true);
		$('form').submit();
	});


});
//]]>
</script>
<?php
include("foot.inc");
