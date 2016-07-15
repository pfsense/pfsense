<?php
/*
 * interfaces_qinq_edit.php
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
##|*IDENT=page-interfaces-qinq-edit
##|*NAME=Interfaces: QinQ: Edit
##|*DESCR=Allow access to 'Interfaces: QinQ: Edit' page
##|*MATCH=interfaces_qinq_edit.php*
##|-PRIV

$pgtitle = array(gettext("Interfaces"), gettext("QinQs"), gettext("Edit"));
$shortcut_section = "interfaces";

require_once("guiconfig.inc");

if (!is_array($config['qinqs']['qinqentry'])) {
	$config['qinqs']['qinqentry'] = array();
}

$a_qinqs = &$config['qinqs']['qinqentry'];

$portlist = get_interface_list();

/* add LAGG interfaces */
if (is_array($config['laggs']['lagg']) && count($config['laggs']['lagg'])) {
	foreach ($config['laggs']['lagg'] as $lagg) {
		$portlist[$lagg['laggif']] = $lagg;
	}
}

if (count($portlist) < 1) {
	header("Location: interfaces_qinq.php");
	exit;
}

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && $a_qinqs[$id]) {
	$pconfig['if'] = $a_qinqs[$id]['if'];
	$pconfig['tag'] = $a_qinqs[$id]['tag'];
	$pconfig['members'] = $a_qinqs[$id]['members'];
	$pconfig['descr'] = html_entity_decode($a_qinqs[$id]['descr']);
	$pconfig['autogroup'] = isset($a_qinqs[$id]['autogroup']);
	$pconfig['autoadjustmtu'] = isset($a_qinqs[$id]['autoadjustmtu']);
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	if (empty($_POST['tag'])) {
		$input_errors[] = gettext("First level tag cannot be empty.");
	}
	if (isset($id) && $a_qinqs[$id]['tag'] != $_POST['tag']) {
		$input_errors[] = gettext("Modifying the first level tag of an existing entry is not allowed.");
	}
	if (isset($id) && $a_qinqs[$id]['if'] != $_POST['if']) {
		$input_errors[] = gettext("Modifying the interface of an existing entry is not allowed.");
	}
	if (!isset($id)) {
		foreach ($a_qinqs as $qinqentry) {
			if ($qinqentry['tag'] == $_POST['tag'] && $qinqentry['if'] == $_POST['if']) {
				$input_errors[] = gettext("QinQ level already exists for this interface, edit it!");
			}
		}
		if (is_array($config['vlans']['vlan'])) {
			foreach ($config['vlans']['vlan'] as $vlan) {
				if ($vlan['tag'] == $_POST['tag'] && $vlan['if'] == $_POST['if']) {
					$input_errors[] = gettext("A normal VLAN exists with this tag please remove it to use this tag for QinQ first level.");
				}
			}
		}
	}

	$qinqentry = array();
	$qinqentry['if'] = $_POST['if'];
	$qinqentry['tag'] = $_POST['tag'];

	if ($_POST['autogroup'] == "yes") {
		$qinqentry['autogroup'] = true;
	}

	$members = "";
	$isfirst = 0;

	// Read the POSTed member array into a space separated list translating any ranges
	// into their included values
	foreach ($_POST['members'] as $memb) {
		// Might be a range
		$member = explode("-", $memb);

		if (count($member) > 1) {
			if (preg_match("/([^0-9])+/", $member[0], $match)  || preg_match("/([^0-9])+/", $member[1], $match)) {
				$input_errors[] = gettext("Tags can contain only numbers or a range in format #-#.");
			}

			for ($i = $member[0]; $i <= $member[1]; $i++) {
				$members .= ($isfirst == 0 ? '':' ') . $i;
				$isfirst++;
			}
		} else { // Just a single number
			if (preg_match("/([^0-9])+/", $memb, $match)) {
				$input_errors[] = gettext("Tags can contain only numbers or a range in format #-#.");
			} else {
				$members .= ($isfirst == 0 ? '':' ') . $memb;
				$isfirst++;
			}
		}
	}

	if (!$input_errors) {
		$qinqentry['members'] = $members;
		$qinqentry['descr'] = $_POST['descr'];
		$qinqentry['vlanif'] = "{$_POST['if']}_{$_POST['tag']}";
		$nmembers = explode(" ", $members);

		if (isset($id) && $a_qinqs[$id]) {
			$omembers = explode(" ", $a_qinqs[$id]['members']);
			$delmembers = array_diff($omembers, $nmembers);
			$addmembers = array_diff($nmembers, $omembers);

			if ((count($delmembers) > 0) || (count($addmembers) > 0)) {
				$fd = fopen("{$g['tmp_path']}/netgraphcmd", "w");
				foreach ($delmembers as $tag) {
					fwrite($fd, "shutdown {$qinqentry['vlanif']}h{$tag}:\n");
					fwrite($fd, "msg {$qinqentry['vlanif']}qinq: delfilter \\\"{$qinqentry['vlanif']}{$tag}\\\"\n");
				}

				foreach ($addmembers as $member) {
					$qinq = array();
					$qinq['if'] = $qinqentry['vlanif'];
					$qinq['tag'] = $member;
					$macaddr = get_interface_mac($qinqentry['vlanif']);
					interface_qinq2_configure($qinq, $fd, $macaddr);
				}

				fclose($fd);
				mwexec("/usr/sbin/ngctl -f {$g['tmp_path']}/netgraphcmd");
			}
			$a_qinqs[$id] = $qinqentry;
		} else {
			interface_qinq_configure($qinqentry);
			$a_qinqs[] = $qinqentry;
		}
		if ($_POST['autogroup'] == "yes") {
			if (!is_array($config['ifgroups']['ifgroupentry'])) {
				$config['ifgroups']['ifgroupentry'] = array();
			}
			foreach ($config['ifgroups']['ifgroupentry'] as $gid => $group) {
				if ($group['ifname'] == "QinQ") {
					$found = true;
					break;
				}
			}
			$additions = "";
			foreach ($nmembers as $qtag) {
				$additions .= "{$qinqentry['vlanif']}_{$qtag} ";
			}
			$additions .= "{$qinqentry['vlanif']}";
			if ($found == true) {
				$config['ifgroups']['ifgroupentry'][$gid]['members'] .= " {$additions}";
			} else {
				$gentry = array();
				$gentry['ifname'] = "QinQ";
				$gentry['members'] = "{$additions}";
				$gentry['descr'] = gettext("QinQ VLANs group");
				$config['ifgroups']['ifgroupentry'][] = $gentry;
			}
		}

		write_config();

		header("Location: interfaces_qinq.php");
		exit;
	} else {
		$pconfig['descr'] = $_POST['descr'];
		$pconfig['tag'] = $_POST['tag'];
		$pconfig['members'] = $members;
	}
}

function build_parent_list() {
	global $portlist;

	$list = array();

	foreach ($portlist as $ifn => $ifinfo) {
		if (is_jumbo_capable($ifn)) {
			$list[$ifn] = $ifn . ' (' . $ifinfo['mac'] . ')';
		}
	}

	return($list);
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('QinQ Configuration');

$section->addInput(new Form_Select(
	'if',
	'Parent interface',
	$pconfig['if'],
	build_parent_list()
))->setHelp('Only QinQ capable interfaces will be shown.');

$section->addInput(new Form_Input(
	'tag',
	'First level tag',
	'number',
	$pconfig['tag'],
	['max' => '4094', 'min' => '1']
))->setHelp('This is the first level VLAN tag. On top of this are stacked the member VLANs defined below.');

$section->addInput(new Form_Checkbox(
	'autogroup',
	'Option(s)',
	'Adds interface to QinQ interface groups',
	$pconfig['autogroup']
))->setHelp('Allows rules to be written more easily.');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

$section->addInput(new Form_StaticText(
	'Member(s)',
	'Ranges can be specified in the inputs below. Enter a range (2-3) or individual numbers.' . '<br />' .
	'Click "Duplicate" as many times as needed to add new inputs.'
));

if (isset($id) && $a_qinqs[$id]) {
	$section->addInput(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$counter = 0;
$members = $pconfig['members'];

// List each of the member tags from the space-separated list
if ($members != "") {
	$item = explode(" ", $members);
} else {
	$item = array('');
}

foreach ($item as $ww) {
	$member = $item[$counter];

	$group = new Form_Group($counter == 0 ? 'Tag(s)':'');
	$group->addClass('repeatable');

	$group->add(new Form_Input(
		'members[]',
		null,
		'text',
		$ww
	))->setWidth(6); // Width must be <= 8 to make room for the duplication buttons

	$group->add(new Form_Button(
		'deleterow' . $counter,
		'Delete',
		null,
		'fa-trash'
	))->addClass('btn-warning');

	$counter++;

	$section->add($group);
}

$form->addGlobal(new Form_Button(
	'addrow',
	'Add Tag',
	null,
	'fa-plus'
))->addClass('btn-success addbtn');

$form->add($section);

print($form);

include("foot.inc");
