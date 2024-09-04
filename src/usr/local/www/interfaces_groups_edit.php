<?php
/*
 * interfaces_groups_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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

config_init_path('ifgroups/ifgroupentry');
$id = is_numericint($_REQUEST['id']) ? $_REQUEST['id'] : null;

$this_ifgroup_config = isset($id) ? config_get_path("ifgroups/ifgroupentry/{$id}") : null;
if ($this_ifgroup_config) {
	$pconfig['ifname'] = $this_ifgroup_config['ifname'];
	$pconfig['members'] = $this_ifgroup_config['members'];
	$pconfig['descr'] = html_entity_decode($this_ifgroup_config['descr']);
}

$interface_list = get_configured_interface_with_descr(true);
$interface_list_disabled = get_configured_interface_with_descr(true);
$ifname_allowed_chars_text = gettext("Only letters (A-Z), digits (0-9) and '_' are allowed.");
$ifname_no_digit_text = gettext("The group name cannot start or end with a digit.");

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
		/* Reserved name? Allow the reserved interface-network suffix since it is based on the interface name. */
		if ((get_pf_reserved($_POST['ifname'], false) && !str_ends_with($_POST['ifname'], '__NETWORK')) ||
		    (get_pf_reserved(strtoupper($_POST['ifname']), false) && !str_ends_with(strtoupper($_POST['ifname']), '__NETWORK'))) {
			$input_errors[] = sprintf(gettext("Cannot use a reserved keyword as an interface name: %s"), $_POST['ifname']);
		}

		foreach (config_get_path('ifgroups/ifgroupentry', []) as $groupid => $groupentry) {
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

		if (preg_match("/(^[0-9])|([0-9]$)/", $_POST['ifname'])) {
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
		foreach(config_get_path('aliases/alias', []) as $alias) {
			if ($alias['name'] == $_POST['ifname']) {
				$input_errors[] = gettext("An alias with this name already exists.");
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
		if ($this_ifgroup_config && $_POST['ifname'] != $this_ifgroup_config['ifname']) {
			$filter_rule_config = config_get_path('filter/rule');
			if (is_array($filter_rule_config)) {
				foreach ($filter_rule_config as &$rule) {
					if (isset($rule['floating'])) {
						$rule_ifs = array_filter(explode(",", $rule['interface']));
						$rule_changed = false;
						foreach ($rule_ifs as $rule_if_id => $rule_if) {
							if ($rule_if == $this_ifgroup_config['ifname']) {
								$rule_ifs[$rule_if_id] = $_POST['ifname'];
								$rule_changed = true;
							}
						}
						if ($rule_changed) {
							$rule['interface'] = implode(",", $rule_ifs);
						}
					} else {
						if ($rule['interface'] == $this_ifgroup_config['ifname']) {
							$rule['interface'] = $_POST['ifname'];
						}
					}
				}
				unset($rule);
				config_set_path('filter/rule', $filter_rule_config);
			}
			$nat_rule_config = config_get_path('nat/rule');
			if (is_array($nat_rule_config)) {
				foreach ($nat_rule_config as $ridx => &$rule) {
					if ($rule['interface'] == $this_ifgroup_config['ifname']) {
						$rule['interface'] = $_POST['ifname'];
					}
				}
				unset($rule);
				config_set_path('nat/rule', $nat_rule_config);
			}
			$omembers = explode(" ", $this_ifgroup_config['members']);
			if (count($omembers) > 0) {
				foreach ($omembers as $ifs) {
					$realif = get_real_interface($ifs);
					if ($realif) {
						mwexec("/sbin/ifconfig {$realif} -group " . $this_ifgroup_config['ifname']);
					}
				}
			}
			$ifgroupentry['ifname'] = $_POST['ifname'];
			$this_ifgroup_config = $ifgroupentry;

		// Edit old group
		} else if ($this_ifgroup_config) {
			$omembers = explode(" ", $this_ifgroup_config['members']);
			$nmembers = explode(" ", $members);
			$delmembers = array_diff($omembers, $nmembers);
			if (count($delmembers) > 0) {
				foreach ($delmembers as $ifs) {
					$realif = get_real_interface($ifs);
					if ($realif) {
						mwexec("/sbin/ifconfig {$realif} -group " . $this_ifgroup_config['ifname']);
					}
				}
			}
			$ifgroupentry['ifname'] = $_POST['ifname'];
			config_set_path("ifgroups/ifgroupentry/{$id}", $ifgroupentry);

		// Create new group
		} else {
			$ifgroupentry['ifname'] = $_POST['ifname'];
			config_set_path('ifgroups/ifgroupentry/', $ifgroupentry);
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

if ($this_ifgroup_config) {
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
