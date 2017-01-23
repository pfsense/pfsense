<?php
/*
 * interfaces_groups_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *
 * 3. All advertising materials mentioning features or use of this software
 *    must display the following acknowledgment:
 *    "This product includes software developed by the pfSense Project
 *    for use in the pfSense® software distribution. (http://www.pfsense.org/).
 *
 * 4. The names "pfSense" and "pfSense Project" must not be used to
 *    endorse or promote products derived from this software without
 *    prior written permission. For written permission, please contact
 *    coreteam@pfsense.org.
 *
 * 5. Products derived from this software may not be called "pfSense"
 *    nor may "pfSense" appear in their names without prior written
 *    permission of the Electric Sheep Fencing, LLC.
 *
 * 6. Redistributions of any form whatsoever must retain the following
 *    acknowledgment:
 *
 * "This product includes software developed by the pfSense Project
 * for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 * THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 * EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 * ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 * STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 * OF THE POSSIBILITY OF SUCH DAMAGE.
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

	if (strlen($_POST['ifname']) > 16) {
		$input_errors[] = gettext("Group name cannot have more than 16 characters.");
	}

	if (preg_match("/([^a-zA-Z0-9-_])+/", $_POST['ifname'])) {
		$input_errors[] = gettext("Only letters (A-Z), digits (0-9), '-' and '_' are allowed as the group name.");
	}

	if (preg_match("/[0-9]$/", $_POST['ifname'])) {
		$input_errors[] = gettext("Group name cannot end with digit.");
	}

	/*
	 * Packages (e.g. tinc) creates interface groups, reserve this
	 * namespace pkg- for them
	 */
	if (substr($_POST['ifname'], 0, 4) == 'pkg-') {
		$input_errors[] = gettext("Group name cannot start with pkg-");
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
	['placeholder' => 'Group Name', 'maxlength' => "16"]
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
