<?php
/*
 * interfaces_groups_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
$pglinks = array("", "interfaces_groups.php", "@self");
$shortcut_section = "interfaces";

init_config_arr(array('ifgroups', 'ifgroupentry'));
$a_ifgroups = &$config['ifgroups']['ifgroupentry'];
$id = $_REQUEST['id'];

if (isset($id) && $a_ifgroups[$id]) {
	$pconfig['ifname'] = $a_ifgroups[$id]['ifname'];
	$pconfig['members'] = $a_ifgroups[$id]['members'];
	$pconfig['descr'] = html_entity_decode($a_ifgroups[$id]['descr']);
}

$interface_list = get_configured_interface_with_descr(true);
$interface_list_disabled = get_configured_interface_with_descr(true);
$ifname_allowed_chars_text = gettext("Only letters (A-Z), digits (0-9) and '_' are allowed.");
$ifname_no_digit_text = gettext("The group name cannot end with a digit.");

/* hide VTI interfaces, see https://redmine.pfsense.org/issues/11134 */
foreach ($interface_list as $if => $ifdescr) {
	if (substr(get_real_interface($if), 0, 5) == "ipsec") {
		unset($interface_list[$if]);
	}
}

if ($_POST['save']) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "ifname");
	$reqdfieldsn = array(gettext("Group Name"));
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!$input_errors) {
		foreach ($a_ifgroups as $groupid => $groupentry) {
			if ((!isset($id) || ($groupid != $id)) && ($groupentry['ifname'] == $_POST['ifname'])) {
				$input_errors[] = gettext("Group name already exists!");
			}
		}

		if (strlen($_POST['ifname']) > 15) {
			$input_errors[] = gettext("Group name cannot have more than 15 characters.");
		}

		if (preg_match("/([^a-zA-Z0-9_])+/", $_POST['ifname'])) {
			$input_errors[] = $ifname_allowed_chars_text . " " . gettext("Please choose another group name.");
		}

		if (preg_match("/[0-9]$/", $_POST['ifname'])) {
			$input_errors[] = $ifname_no_digit_text;
		}

		/*
		 * Packages (e.g. tinc) create interface groups, reserve this
		 * namespace pkg_ for them
		 */
		if (substr($_POST['ifname'], 0, 4) == 'pkg_') {
			$input_errors[] = gettext("Group name cannot start with pkg_");
		}

		foreach ($interface_list_disabled as $gif => $gdescr) {
			if ((strcasecmp($gdescr, $_POST['ifname']) == 0) || (strcasecmp($gif, $_POST['ifname']) == 0)) {
				$input_errors[] = "The specified group name is already used by an interface. Please choose another name.";
			}
		}

		/* Is the description already used as an alias name? */
		if (is_array($config['aliases']['alias'])) {
			foreach ($config['aliases']['alias'] as $alias) {
				if ($alias['name'] == $_POST['ifname']) {
					$input_errors[] = gettext("An alias with this name already exists.");
				}
			}
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

		write_config("Interface Group added");
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
	'*Group Name',
	'text',
	$pconfig['ifname'],
	['placeholder' => 'Group Name', 'maxlength' => "15"]
))->setWidth(6)->setHelp($ifname_allowed_chars_text . " " . $ifname_no_digit_text);

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
	'Multi-WAN typically relies. %1$sMore Information%2$s',
	'<a href="https://docs.netgate.com/pfsense/en/latest/interfaces/groups.html">', '</a>');

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
