<?php
/*
 * interfaces_groups_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
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
##|*IDENT=page-interfaces-groups-edit
##|*NAME=Interfaces: Groups: Edit
##|*DESCR=Allow access to the 'Interfaces: Groups: Edit' page.
##|*MATCH=interfaces_groups_edit.php*
##|-PRIV


require_once("guiconfig.inc");
require_once("functions.inc");

$pgtitle = array(gettext("Interfaces"), gettext("Interface Groups"), gettext("Edit"));
$shortcut_section = "interfaces";

if (!is_array($config['ifgroups']['ifgroupentry'])) {
	$config['ifgroups']['ifgroupentry'] = array();
}

$a_ifgroups = &$config['ifgroups']['ifgroupentry'];

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && $a_ifgroups[$id]) {
	$pconfig['ifname'] = $a_ifgroups[$id]['ifname'];
	$pconfig['members'] = $a_ifgroups[$id]['members'];
	$pconfig['descr'] = html_entity_decode($a_ifgroups[$id]['descr']);
}

$interface_list = get_configured_interface_with_descr();
$interface_list_disabled = get_configured_interface_with_descr(false, true);

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	if (!isset($id)) {
		foreach ($a_ifgroups as $groupentry) {
			if ($groupentry['ifname'] == $_POST['ifname']) {
				$input_errors[] = gettext("Group name already exists!");
			}
		}
	}

	if (preg_match("/([^a-zA-Z])+/", $_POST['ifname'], $match)) {
		$input_errors[] = gettext("Only letters A-Z are allowed as the group name.");
	}

	foreach ($interface_list as $gif => $gdescr) {
		if ($gdescr == $_POST['ifname'] || $gif == $_POST['ifname']) {
			$input_errors[] = "The specified group name is already used by an interface. Please choose another name.";
		}
	}

	if (isset($_POST['members'])) {
		$members = implode(" ", $_POST['members']);
	} else {
		$members = "";
	}

	if (!$input_errors) {
		$ifgroupentry = array();
		$ifgroupentry['members'] = $members;
		$ifgroupentry['descr'] = $_POST['descr'];

		// Edit group name
		if (isset($id) && $a_ifgroups[$id] && $_POST['ifname'] != $a_ifgroups[$id]['ifname']) {
			if (!empty($config['filter']) && is_array($config['filter']['rule'])) {
				foreach ($config['filter']['rule'] as $ridx => $rule) {
					if (isset($rule['floating'])) {
						$rule_ifs = explode(",", $rule['interface']);
						$rule_changed = false;
						foreach ($rule_ifs as $rule_if_id => $rule_if) {
							if ($rule_if == $a_ifgroups[$id]['ifname']) {
								$rule_ifs[$rule_if_id] = $_POST['ifname'];
								$rule_changed = true;
							}
						}
						if ($rule_changed) {
							$config['filter']['rule'][$ridx]['interface'] = implode(",", $rule_ifs);
						}
					} else {
						if ($rule['interface'] == $a_ifgroups[$id]['ifname']) {
							$config['filter']['rule'][$ridx]['interface'] = $_POST['ifname'];
						}
					}
				}
			}
			if (!empty($config['nat']) && is_array($config['nat']['rule'])) {
				foreach ($config['nat']['rule'] as $ridx => $rule) {
					if ($rule['interface'] == $a_ifgroups[$id]['ifname']) {
						$config['nat']['rule'][$ridx]['interface'] = $_POST['ifname'];
					}
				}
			}
			$omembers = explode(" ", $a_ifgroups[$id]['members']);
			if (count($omembers) > 0) {
				foreach ($omembers as $ifs) {
					$realif = get_real_interface($ifs);
					if ($realif) {
						mwexec("/sbin/ifconfig {$realif} -group " . $a_ifgroups[$id]['ifname']);
					}
				}
			}
			$ifgroupentry['ifname'] = $_POST['ifname'];
			$a_ifgroups[$id] = $ifgroupentry;

		// Edit old group
		} else if (isset($id) && $a_ifgroups[$id]) {
			$omembers = explode(" ", $a_ifgroups[$id]['members']);
			$nmembers = explode(" ", $members);
			$delmembers = array_diff($omembers, $nmembers);
			if (count($delmembers) > 0) {
				foreach ($delmembers as $ifs) {
					$realif = get_real_interface($ifs);
					if ($realif) {
						mwexec("/sbin/ifconfig {$realif} -group " . $a_ifgroups[$id]['ifname']);
					}
				}
			}
			$ifgroupentry['ifname'] = $_POST['ifname'];
			$a_ifgroups[$id] = $ifgroupentry;

		// Create new group
		} else {
			$ifgroupentry['ifname'] = $_POST['ifname'];
			$a_ifgroups[] = $ifgroupentry;
		}

		write_config();
		interface_group_setup($ifgroupentry);

		header("Location: interfaces_groups.php");
		exit;
	} else {
		$pconfig['descr'] = $_POST['descr'];
		$pconfig['members'] = $members;
	}
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

?>
<div id="inputerrors"></div>
<?php
$form = new Form;
$section = new Form_Section('Interface Group Configuration');

$section->addInput(new Form_Input(
	'ifname',
	'Group Name',
	'text',
	$pconfig['ifname'],
	['placeholder' => 'Group Name']
))->setWidth(6)->setHelp('No numbers or spaces are allowed. '.
	'Only characters: a-zA-Z');

$section->addInput(new Form_Input(
	'descr',
	'Group Description',
	'text',
	$pconfig['descr'],
	['placeholder' => 'Group Description']
))->setWidth(6)->setHelp('A group description may be entered '.
	'here for administrative reference (not parsed).');

$section->addInput(new Form_Select(
	'members',
	'Group Members',
	explode(' ', $pconfig['members']),
	$interface_list,
	true
))->setWidth(6)->setHelp('NOTE: Rules for WAN type '.
	'interfaces in groups do not contain the reply-to mechanism upon which '.
	'Multi-WAN typically relies. '.
	'<a href="https://doc.pfsense.org/index.php/ifgroups">More Information</a>');

if (isset($id) && $a_ifgroups[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		'id',
		'hidden',
		$id
	));
}

$form->add($section);
print $form;

unset($interface_list);
unset($interface_list_disabled);
include("foot.inc");
?>
