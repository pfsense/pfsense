<?php
/*
 * firewall_nat_npt_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2011 Seth Mos <seth.mos@dds.nl>
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
##|*IDENT=page-firewall-nat-npt-edit
##|*NAME=Firewall: NAT: NPt: Edit
##|*DESCR=Allow access to the 'Firewall: NAT: NPt: Edit' page.
##|*MATCH=firewall_nat_npt_edit.php*
##|-PRIV

require_once("ipsec.inc");

require_once("guiconfig.inc");
require_once("interfaces.inc");
require_once("filter.inc");
require_once("shaper.inc");

$ifdisp = get_configured_interface_with_descr();

foreach ($ifdisp as $kif => $kdescr) {
	$specialsrcdst[] = "{$kif}";
	$specialsrcdst[] = "{$kif}ip";
}

init_config_arr(array('nat', 'npt'));
$a_npt = &$config['nat']['npt'];

if (isset($_REQUEST['id']) && is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

if (isset($_REQUEST['after'])) {
	$after = $_REQUEST['after'];
}

if (isset($_REQUEST['dup'])) {
	$id = $_REQUEST['dup'];
	$after = $_REQUEST['dup'];
}

if (isset($id) && $a_npt[$id]) {
	$pconfig['disabled'] = isset($a_npt[$id]['disabled']);

	address_to_pconfig($a_npt[$id]['source'], $pconfig['src'],
		$pconfig['srcmask'], $pconfig['srcnot'],
		$pconfig['srcbeginport'], $pconfig['srcendport']);

	address_to_pconfig($a_npt[$id]['destination'], $pconfig['dst'],
		$pconfig['dstmask'], $pconfig['dstnot'],
		$pconfig['dstbeginport'], $pconfig['dstendport']);

	$pconfig['interface'] = $a_npt[$id]['interface'];
	if (!$pconfig['interface']) {
		$pconfig['interface'] = "wan";
	}

	$pconfig['descr'] = $a_npt[$id]['descr'];
} else {
	$pconfig['interface'] = "wan";
}

if (isset($_REQUEST['dup'])) {
	unset($id);
}

if ($_POST['save']) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "interface");
	$reqdfieldsn = array(gettext("Interface"));
	$reqdfields[] = "src";
	$reqdfieldsn[] = gettext("Source prefix");
	$reqdfields[] = "dst";
	$reqdfieldsn[] = gettext("Destination prefix");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!is_ipaddrv6(trim($_POST['src']))) {
		$input_errors[] = gettext("The specified source address is not a valid IPv6 prefix");
	}
	if (!is_ipaddrv6(trim($_POST['dst']))) {
		$input_errors[] = gettext("The specified destination address is not a valid IPv6 prefix");
	}
	if (check_subnetsv6_overlap(get_interface_ipv6($_POST['interface']), 128, trim($_POST['dst']), $_POST['dstmask']) &&
	    !$_POST['dstnot']) {
		$input_errors[] = gettext("The specified destination address and interface IPv6 address cannot overlap");
	}

	if (!$input_errors) {
		$natent = array();

		$natent['disabled'] = isset($_POST['disabled']) ? true:false;
		$natent['descr'] = $_POST['descr'];
		$natent['interface'] = $_POST['interface'];

		if ($_POST['src']) {
			$_POST['src'] = trim($_POST['src']);
		}
		if ($_POST['dst']) {
			$_POST['dst'] = trim($_POST['dst']);
		}

		pconfig_to_address($natent['source'], $_POST['src'], $_POST['srcmask'], $_POST['srcnot']);

		pconfig_to_address($natent['destination'], $_POST['dst'], $_POST['dstmask'], $_POST['dstnot']);

		if (isset($id) && $a_npt[$id]) {
			$a_npt[$id] = $natent;
		} else {
			if (is_numeric($after)) {
				array_splice($a_npt, $after+1, 0, array($natent));
			} else {
				$a_npt[] = $natent;
			}
		}

		if (write_config(gettext("Firewall: NAT: NPt - saved/edited NPt mapping."))) {
			mark_subsystem_dirty('natconf');
		}

		header("Location: firewall_nat_npt.php");
		exit;
	}
}

$pgtitle = array(gettext("Firewall"), gettext("NAT"), gettext("NPt"), gettext("Edit"));
$pglinks = array("", "firewall_nat.php", "firewall_nat_npt.php", "@self");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('Edit NAT NPt Entry');

$section->addInput(new Form_Checkbox(
	'disabled',
	'Disabled',
	'Disable this rule',
	$pconfig['disabled']
));

$section->addInput(new Form_Select(
	'interface',
	'*Interface',
	$pconfig['interface'],
	create_interface_list()
))->setHelp('Choose which interface this rule applies to.%s' .
			'Hint: Typically the "WAN" is used here.', '<br />');

$section->addInput(new Form_Checkbox(
	'srcnot',
	'Internal IPv6 prefix',
	'Not',
	$pconfig['srcnot']
))->setHelp('Use this option to invert the sense of the match. ');

$section->addInput(new Form_IpAddress(
	'src',
	'*Address',
	$pconfig['src'],
	'V6'
))->addMask('srcmask', $pconfig['srcmask'])->setHelp('Internal (LAN) ULA IPv6 Prefix for the Network Prefix translation. ' .
													 'The prefix size specified for the internal IPv6 prefix will be applied to the external prefix.');

$section->addInput(new Form_Checkbox(
	'dstnot',
	'Destination IPv6 prefix',
	'Not',
	$pconfig['dstnot']
))->setHelp('Use this option to invert the sense of the match. ');

$section->addInput(new Form_IpAddress(
	'dst',
	'*Address',
	$pconfig['dst'],
	'V6'
))->addMask('dstmask', $pconfig['dstmask'])->setHelp('Global Unicast routable IPv6 prefix');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

if (isset($id) && $a_npt[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->add($section);
print($form);

include("foot.inc");
