<?php
/*
 * interfaces_qinq_edit.php
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
##|*IDENT=page-interfaces-qinq-edit
##|*NAME=Interfaces: QinQ: Edit
##|*DESCR=Allow access to 'Interfaces: QinQ: Edit' page
##|*MATCH=interfaces_qinq_edit.php*
##|-PRIV

$pgtitle = array(gettext("Interfaces"), gettext("QinQs"), gettext("Edit"));
$pglinks = array("", "interfaces_qinq.php", "@self");
$shortcut_section = "interfaces";

require_once("guiconfig.inc");

config_init_path('qinqs/qinqentry');

$portlist = get_interface_list();
$lagglist = get_lagg_interface_list();
$portlist = array_merge($portlist, $lagglist);
foreach ($lagglist as $lagg) {
	/* LAGG members cannot be assigned */
	$laggmembers = explode(',', $lagg['members']);
	foreach ($laggmembers as $lagm) {
		if (isset($portlist[$lagm])) {
			unset($portlist[$lagm]);
		}
	}
}

/* Do not allow OpenVPN TUN interfaces to be used for QinQ
 * https://redmine.pfsense.org/issues/11675 */
config_init_path('openvpn/openvpn-server');
config_init_path('openvpn/openvpn-client');
$openvpn_config = config_get_path('openvpn');
foreach ($portlist as $portname => $port) {
	if (strstr($portname, "ovpn")) {
		preg_match('/ovpn([cs])([1-9]+)/', $portname, $m);
		$type = ($m[1] == 'c') ? 'client' : 'server';
		foreach ($openvpn_config['openvpn-'.$type] as $ovpn) {
			if (($ovpn['vpnid'] == $m[2]) && ($ovpn['dev_mode'] == 'tun')) {
				unset($portlist[$portname]);
			}
		}
	}
}

if (count($portlist) < 1) {
	header("Location: interfaces_qinq.php");
	exit;
}

if (isset($_REQUEST['id']) && is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

$this_qinq_config = isset($id) ? config_get_path("qinqs/qinqentry/{$id}") : null;
if ($this_qinq_config) {
	$pconfig['if'] = $this_qinq_config['if'];
	$pconfig['tag'] = $this_qinq_config['tag'];
	$pconfig['members'] = $this_qinq_config['members'];
	$pconfig['descr'] = html_entity_decode($this_qinq_config['descr']);
	$pconfig['autogroup'] = isset($this_qinq_config['autogroup']);
	$pconfig['autoadjustmtu'] = isset($this_qinq_config['autoadjustmtu']);
}

if ($_POST['save']) {
	unset($input_errors);
	$pconfig = $_POST;

	/*
	 * Check user privileges to test if the user is allowed to make changes.
	 * Otherwise users can end up in an inconsistent state where some changes are
	 * performed and others denied. See https://redmine.pfsense.org/issues/15318
	 */
	phpsession_begin();
	$guiuser = getUserEntry($_SESSION['Username']);
	$read_only = (is_array($guiuser) && userHasPrivilege($guiuser, "user-config-readonly"));
	phpsession_end();

	if ($read_only) {
		$input_errors = array(gettext("Insufficient privileges to make the requested change (read only)."));
	}

	if (empty($_POST['tag'])) {
		$input_errors[] = gettext("First level tag cannot be empty.");
	}
	if ($this_qinq_config && $this_qinq_config['tag'] != $_POST['tag']) {
		$input_errors[] = gettext("Modifying the first level tag of an existing entry is not allowed.");
	}
	if ($this_qinq_config && $this_qinq_config['if'] != $_POST['if']) {
		$input_errors[] = gettext("Modifying the interface of an existing entry is not allowed.");
	}
	if (!isset($id)) {
		foreach (config_get_path('qinqs/qinqentry', []) as $qinqentry) {
			if ($qinqentry['tag'] == $_POST['tag'] && $qinqentry['if'] == $_POST['if']) {
				$input_errors[] = gettext("QinQ level already exists for this interface, edit it!");
			}
		}
		foreach (config_get_path('vlans/vlan', []) as $vlan) {
			if ($vlan['tag'] == $_POST['tag'] && $vlan['if'] == $_POST['if']) {
				$input_errors[] = gettext("A normal VLAN exists with this tag please remove it to use this tag for QinQ first level.");
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
	if ($this_qinq_config) {
		$omembers = explode(" ", $this_qinq_config['members']);
		$delmembers = array_diff($omembers, $nmembers);
		foreach ($delmembers as $tag) {
			if (qinq_inuse($this_qinq_config, $tag)) {
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

		if ($this_qinq_config) {
			$omembers = explode(" ", $this_qinq_config['members']);
			$delmembers = array_diff($omembers, $nmembers);
			$addmembers = array_diff($nmembers, $omembers);

			if ((count($delmembers) > 0) || (count($addmembers) > 0)) {
				$parent = $qinqentry['vlanif'];
				foreach ($delmembers as $tag) {
					exec("/sbin/ifconfig {$parent}.{$tag} destroy");
				}

				foreach ($addmembers as $member) {
					$qinq = array();
					$qinq['if'] = $qinqentry['vlanif'];
					$qinq['tag'] = $member;
					$macaddr = get_interface_mac($qinqentry['vlanif']);
					interface_qinq2_configure($qinq, $macaddr);
				}
			}
			config_set_path("qinqs/qinqentry/{$id}", $qinqentry);
		} else {
			interface_qinq_configure($qinqentry);
			config_set_path('qinqs/qinqentry/', $qinqentry);
		}
		if ($_POST['autogroup'] == "yes") {
			$gid = null;
			foreach (config_get_path('ifgroups/ifgroupentry', []) as $idx => $group) {
				if ($group['ifname'] == "QinQ") {
					$gid = $idx;
					break;
				}
			}
			$additions = "";
			foreach ($nmembers as $qtag) {
				$additions .= qinq_interface($qinqentry, $qtag) . " ";
			}
			$additions .= "{$qinqentry['vlanif']}";
			if ($gid !== null) {
				config_set_path("ifgroups/ifgroupentry/{$gid}/members", config_get_path("ifgroups/ifgroupentry/{$gid}/members") . " {$additions}");
			} else {
				$gentry = array();
				$gentry['ifname'] = "QinQ";
				$gentry['members'] = "{$additions}";
				$gentry['descr'] = gettext("QinQ VLANs group");
				config_init_path('ifgroups/ifgroupentry');
				config_set_path('ifgroups/ifgroupentry/', $gentry);
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

if ($this_qinq_config) {
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
		'fa-solid fa-trash-can'
	))->addClass('btn-warning');

	$counter++;

	$section->add($group);
}

$form->addGlobal(new Form_Button(
	'addrow',
	'Add Tag',
	null,
	'fa-solid fa-plus'
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
