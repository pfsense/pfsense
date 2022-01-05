<?php
/*
 * services_wol_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
##|*IDENT=page-services-wakeonlan-edit
##|*NAME=Services: Wake-on-LAN: Edit
##|*DESCR=Allow access to the 'Services: Wake-on-LAN: Edit' page.
##|*MATCH=services_wol_edit.php*
##|-PRIV

function wolcmp($a, $b) {
	return strcmp($a['descr'], $b['descr']);
}

function wol_sort() {
	global $config;

	usort($config['wol']['wolentry'], "wolcmp");
}

require_once("guiconfig.inc");

init_config_arr(array('wol', 'wolentry'));
$a_wol = &$config['wol']['wolentry'];

if (is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}


if (isset($id) && $a_wol[$id]) {
	$pconfig['interface'] = $a_wol[$id]['interface'];
	$pconfig['mac'] = $a_wol[$id]['mac'];
	$pconfig['descr'] = $a_wol[$id]['descr'];
} else {
	$pconfig['interface'] = $_REQUEST['if'];
	$pconfig['mac'] = $_REQUEST['mac'];
	$pconfig['descr'] = $_REQUEST['descr'];
}

if ($_POST['save']) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "interface mac");
	$reqdfieldsn = array(gettext("Interface"), gettext("MAC address"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	/* normalize MAC addresses - lowercase and convert Windows-ized hyphenated MACs to colon delimited */
	$_POST['mac'] = strtolower(str_replace("-", ":", $_POST['mac']));

	if (($_POST['mac'] && !is_macaddr($_POST['mac']))) {
		$input_errors[] = gettext("A valid MAC address must be specified.");
	}

	foreach ($a_wol as $wolidx => $wolentry) {
		if ((!isset($id) || ($wolidx != $id)) && ($wolentry['interface'] == $_POST['interface']) && ($wolentry['mac'] == $_POST['mac'])) {
			$input_errors[] = gettext("This interface and MAC address wake-on-LAN entry already exists.");
			break;
		}
	}

	if (!$input_errors) {
		$wolent = array();
		$wolent['interface'] = $_POST['interface'];
		$wolent['mac'] = $_POST['mac'];
		$wolent['descr'] = $_POST['descr'];

		if (isset($id) && $a_wol[$id]) {
			$a_wol[$id] = $wolent;
		} else {
			$a_wol[] = $wolent;
		}
		wol_sort();

		write_config(gettext("Configured a wake-on-LAN entry."));

		header("Location: services_wol.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("Wake-on-LAN"), gettext("Edit"));
$pglinks = array("", "services_wol.php", "@self");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form;

if (isset($id) && $a_wol[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$section = new Form_Section('Edit WOL Entry');

$section->addInput(new Form_Select(
	'interface',
	'*Interface',
	(link_interface_to_bridge($pconfig['interface']) ? null : $pconfig['interface']),
	get_configured_interface_with_descr()
))->setHelp('Choose which interface this host is connected to.');

$section->addInput(new Form_Input(
	'mac',
	'*MAC address',
	'text',
	$pconfig['mac']
))->setHelp('Enter a MAC address in the following format: xx:xx:xx:xx:xx:xx');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

$form->add($section);
print $form;

include("foot.inc");
