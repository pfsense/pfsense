<?php
/*
 * interfaces_qinq_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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
$pglinks = array("", "interfaces_qinq.php", "@self");
$shortcut_section = "interfaces";

require_once("guiconfig.inc");

init_config_arr(array('qinqs', 'qinqentry'));
$a_qinqs = &$config['qinqs']['qinqentry'];

$portlist = get_interface_list();
$lagglist = get_lagg_interface_list();
$portlist = array_merge($portlist, $lagglist);
foreach ($lagglist as $laggif => $lagg) {
	/* LAGG members cannot be assigned */
	$laggmembers = explode(',', $lagg['members']);
	foreach ($laggmembers as $lagm) {
		if (isset($portlist[$lagm])) {
			unset($portlist[$lagm]);
		}
	}
}

/* Do not allow WireGuard interfaces to be used for QinQ
 * https://redmine.pfsense.org/issues/11277 */
init_config_arr(array('wireguard', 'tunnel'));
foreach ($config['wireguard']['tunnel'] as $tunnel) {
	if (isset($portlist[$tunnel['name']])) {
		unset($portlist[$tunnel['name']]);
	}
}

if (count($portlist) < 1) {
	header("Location: interfaces_qinq.php");
	exit;
}

if (isset($_REQUEST['id']) && is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

if (isset($id) && $a_qinqs[$id]) {
	$pconfig['if'] = $a_qinqs[$id]['if'];
	$pconfig['tag'] = $a_qinqs[$id]['tag'];
	$pconfig['members'] = $a_qinqs[$id]['members'];
	$pconfig['descr'] = html_entity_decode($a_qinqs[$id]['descr']);
	$pconfig['autogroup'] = isset($a_qinqs[$id]['autogroup']);
	$pconfig['autoadjustmtu'] = isset($a_qinqs[$id]['autoadjustmtu']);
}

if ($_POST['save']) {
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

	$tag_min = 1;
	$tag_max = 4094;
	$tag_format_error = false;
	$members = "";

	// Read the POSTed member array into a space separated list translating any ranges
	// into their included values
	$membercounter = 0;
	$membername = "member{$membercounter}";
	$valid_members = array();

	while (isset($_POST[$membername])) {
		if (is_intrange($_POST[$membername], $tag_min, $tag_max)) {
			$sep = (strpos($_POST[$membername], ":") === false) ? "-" : ":";
			$member = explode($sep, $_POST[$membername]);
			for ($i = intval($member[0]); $i <= intval($member[1]); $i++) {
				$valid_members[] = $i;
			}
		} elseif (is_numericint($_POST[$membername]) && ($_POST[$membername] >= $tag_min) && ($_POST[$membername] <= $tag_max)) {
			$valid_members[] = intval($_POST[$membername]);
		} elseif ($_POST[$membername] != "") {
			$tag_format_error = true;
		} // else ignore empty rows

		// Remember the POSTed values so they can be redisplayed if there were errors.
		$posted_members .= ($membercounter == 0 ? '':' ') . $_POST[$membername];

		$membercounter++;
		$membername = "member{$membercounter}";
	}

	if ($tag_format_error) {
		$input_errors[] = sprintf(gettext('Tags can contain only numbers or a range  (in format #-#) from %1$s to %2$s.'), $tag_min, $tag_max);
	}

	// Just use the unique valid members. There could have been overlap in the ranges or repeat of numbers entered.
	$members = implode(" ", array_unique($valid_members));

	if ($members == "") {
		$input_errors[] = gettext("At least one tag must be entered.");
	}

	$nmembers = explode(" ", $members);
	if (isset($id) && $a_qinqs[$id]) {
		$omembers = explode(" ", $a_qinqs[$id]['members']);
		$delmembers = array_diff($omembers, $nmembers);
		foreach ($delmembers as $tag) {
			if (qinq_inuse($a_qinqs[$id], $tag)) {
				$input_errors[] = gettext("This QinQ tag cannot be deleted because it is still being used as an interface.");
				break;
			}
		}
	}

	if (!$input_errors) {
		$qinqentry['members'] = $members;
		$qinqentry['descr'] = $_POST['descr'];
		$qinqentry['vlanif'] = vlan_interface($_POST);
		$nmembers = explode(" ", $members);

		if (isset($id) && $a_qinqs[$id]) {
			$omembers = explode(" ", $a_qinqs[$id]['members']);
			$delmembers = array_diff($omembers, $nmembers);
			$addmembers = array_diff($nmembers, $omembers);

			if ((count($delmembers) > 0) || (count($addmembers) > 0)) {
				foreach ($delmembers as $tag) {
					$ngif = str_replace(".", "_", $qinqentry['vlanif']);
					exec("/usr/sbin/ngctl shutdown {$ngif}h{$tag}: > /dev/null 2>&1");
					exec("/usr/sbin/ngctl msg {$ngif}qinq: delfilter \\\"{$ngif}{$tag}\\\" > /dev/null 2>&1");
				}

				$qinqcmdbuf = "";
				foreach ($addmembers as $member) {
					$qinq = array();
					$qinq['if'] = $qinqentry['vlanif'];
					$qinq['tag'] = $member;
					$macaddr = get_interface_mac($qinqentry['vlanif']);
					interface_qinq2_configure($qinq, $qinqcmdbuf, $macaddr);
				}

				if (strlen($qinqcmdbuf) > 0) {
					$fd = fopen("{$g['tmp_path']}/netgraphcmd", "w");
					if ($fd) {
						fwrite($fd, $qinqcmdbuf);
						fclose($fd);
						mwexec("/usr/sbin/ngctl -f {$g['tmp_path']}/netgraphcmd > /dev/null 2>&1");
					}
				}
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
				$additions .= qinq_interface($qinqentry, $qtag) . " ";
			}
			$additions .= "{$qinqentry['vlanif']}";
			if ($found == true) {
				$config['ifgroups']['ifgroupentry'][$gid]['members'] .= " {$additions}";
			} else {
				$gentry = array();
				$gentry['ifname'] = "QinQ";
				$gentry['members'] = "{$additions}";
				$gentry['descr'] = gettext("QinQ VLANs group");
				init_config_arr(array('ifgroups', 'ifgroupentry'));
				$config['ifgroups']['ifgroupentry'][] = $gentry;
			}
		}

		write_config("QinQ interface added");

		header("Location: interfaces_qinq.php");
		exit;
	} else {
		$pconfig['descr'] = $_POST['descr'];
		$pconfig['tag'] = $_POST['tag'];
		$pconfig['members'] = $posted_members;
	}
}

function build_parent_list() {
	global $portlist;

	$list = array();

	foreach ($portlist as $ifn => $ifinfo) {
		$list[$ifn] = $ifn . ' (' . $ifinfo['mac'] . ')';
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
	'*Parent interface',
	$pconfig['if'],
	build_parent_list()
))->setHelp('Only QinQ capable interfaces will be shown.');

$section->addInput(new Form_Input(
	'tag',
	'*First level tag',
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
	'Click "Add Tag" as many times as needed to add new inputs.'
));

if (isset($id) && $a_qinqs[$id]) {
	$form->addGlobal(new Form_Input(
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

	$group = new Form_Group($counter == 0 ? '*Tag(s)':'');
	$group->addClass('repeatable');

	$group->add(new Form_Input(
		'member' . $counter,
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

?>

<script type="text/javascript">
//<![CDATA[

events.push(function() {

	// Suppress "Delete row" button if there are fewer than two rows
	checkLastRow();

});
//]]>
</script>

<?php
include("foot.inc");
