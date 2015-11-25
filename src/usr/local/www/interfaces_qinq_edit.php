<?php
/*
	interfaces_qinq_edit.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */
/*
	pfSense_BUILDER_BINARIES:	/usr/sbin/ngctl
	pfSense_MODULE:	interfaces
*/

##|+PRIV
##|*IDENT=page-interfaces-qinq-edit
##|*NAME=Interfaces: QinQ: Edit
##|*DESCR=Allow access to 'Interfaces: QinQ: Edit' page
##|*MATCH=interfaces_qinq_edit.php*
##|-PRIV

$pgtitle = array(gettext("Interfaces"), gettext("QinQ"), gettext("Edit"));
$shortcut_section = "interfaces";

require("guiconfig.inc");

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
		$input_errors[] = gettext("You are editing an existing entry and modifying the first level tag is not allowed.");
	}
	if (isset($id) && $a_qinqs[$id]['if'] != $_POST['if']) {
		$input_errors[] = gettext("You are editing an existing entry and modifying the interface is not allowed.");
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
			if (preg_match("/([^0-9])+/", $member[0], $match)  || preg_match("/([^0-9])+/", $member[1], $match))
				$input_errors[] = gettext("Tags can contain only numbers or a range in format #-#.");

			for ($i = $member[0]; $i <= $member[1]; $i++) {
				$members .= ($isfirst == 0 ? '':' ') . $i;
				$isfirst++;
			}
		}
		else { // Just a single number
			if (preg_match("/([^0-9])+/", $memb, $match))
				$input_errors[] = gettext("Tags can contain only numbers or a range in format #-#.");
			else {
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
		if (is_jumbo_capable($ifn))
			$list[$ifn] = $ifn . ' (' . $ifinfo['mac'] . ')';
	}

	return($list);
}

include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

$form = new Form(new Form_Button(
	'Submit',
	gettext("Save")
));

$section = new Form_Section('Interface QinQ Edit');

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
))->setHelp('Allows rules to be written more easily');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('You may enter a description here for your reference (not parsed).');

$section->addInput(new Form_StaticText(
	'Member(s)',
	'You can specify ranges in the inputs below. Enter a range (2-3) or individual numbers.' . '<br />' .
	'Click "Duplicate" as many times as needed to add new inputs'
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
if ($members != "")
	$item = explode(" ", $members);
else
	$item = array('');

foreach($item as $ww) {
	$member = $item[$counter];

	$group = new Form_Group($counter == 0 ? 'Tag(s)':'');

	$group->add(new Form_Input(
		'members',
		null,
		'text',
		$ww
	))->setWidth(6); // Width must be <= 8 to make room for the duplication buttons

$counter++;

$group->enableDuplication(null, true); // Buttons are in-line with the input
$section->add($group);
}

$form->add($section);

print($form);

include("foot.inc");
