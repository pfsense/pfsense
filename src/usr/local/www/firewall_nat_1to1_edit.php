<?php
/*
 * firewall_nat_1to1_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-firewall-nat-1-1-edit
##|*NAME=Firewall: NAT: 1:1: Edit
##|*DESCR=Allow access to the 'Firewall: NAT: 1:1: Edit' page.
##|*MATCH=firewall_nat_1to1_edit.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("interfaces.inc");
require_once("filter.inc");
require_once("ipsec.inc");
require_once("shaper.inc");
require_once("firewall_nat_1to1.inc");

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/firewall_nat_1to1.php');

function get_must_be_both_text() {
	return(" " . gettext("They must be either both IPv4 or both IPv6 addresses."));
}

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

$pconfig = get1to1NATrule($id);

if (isset($_REQUEST['dup'])) {
	unset($id);
}

if ($_POST['save']) {
	$rv = save1to1NATrule($_POST, $id);
	$input_errors = $rv['input_errors'];
	$pconfig = $rv['pconfig'];

	if (!$input_errors) {
		header("Location: firewall_nat_1to1.php");
		exit;
	}
}

$pgtitle = array(gettext("Firewall"), gettext("NAT"), gettext("1:1"), gettext("Edit"));
$pglinks = array("", "firewall_nat.php", "firewall_nat_1to1.php", "@self");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('Edit NAT 1:1 Entry');

$section->addInput(new Form_Checkbox(
	'disabled',
	'Disabled',
	'Disable this rule',
	$pconfig['disabled']
))->setHelp('When disabled, the rule will not have any effect.');

$section->addInput(new Form_Checkbox(
	'nobinat',
	'No BINAT (NOT)',
	'Do not perform binat for the specified address',
	$pconfig['nobinat']
))->setHelp('Excludes the address from a later, more general, rule.');

/* add openvpn/tun interfaces */
if	($config['openvpn']["openvpn-server"] || $config['openvpn']["openvpn-client"]) {
	$interfaces["openvpn"] = gettext("OpenVPN");
}

$section->addInput(new Form_Select(
	'interface',
	'*Interface',
	$pconfig['interface'],
	filter_get_interface_list()
))->setHelp('Choose which interface this rule applies to. In most cases "WAN" is specified.');

$section->addInput(new Form_Select(
	'ipprotocol',
	'*Address Family',
	$pconfig['ipprotocol'],
	array(
		'inet' => 'IPv4',
		'inet6' => 'IPv6'
	)
))->setHelp('Select the Internet Protocol version this rule applies to.');

$group = new Form_Group('*External subnet IP');

$group->add(new Form_StaticText(
	null,
	null
))->setWidth(3);

$group->add(new Form_Select(
	'exttype',
	null,
	exttype_selected(),
	build_exttype_list()
))->setHelp('Type')->setWidth(3);

$group->add(new Form_IpAddress(
	'external',
	null,
	is_specialnet($pconfig['external']) ? '': $pconfig['external']
))->setHelp('Address')->setWidth(3);

$group->setHelp('Enter the external (usually on a WAN) subnet\'s starting address or interface for the 1:1 mapping.');

$section->add($group);

$group = new Form_Group('*Internal IP');

$group->add(new Form_Checkbox(
	'srcnot',
	null,
	'Not',
	$pconfig['srcnot']
))->setHelp('Invert the sense of the match.');

$group->add(new Form_Select(
	'srctype',
	null,
	srctype_selected(),
	build_srctype_list()
))->setHelp('Type');

$group->add(new Form_IpAddress(
	'src',
	null,
	is_specialnet($pconfig['src']) ? '': $pconfig['src']
))->addMask('srcmask', $pconfig['srcmask'], 31)->setHelp('Address/mask');

$group->setHelp('Enter the internal (LAN) subnet for the 1:1 mapping. ' .
				'The subnet size specified for the internal subnet will be applied to the external subnet.');

$section->add($group);

$group = new Form_Group('*Destination');

$group->add(new Form_Checkbox(
	'dstnot',
	null,
	'Not',
	$pconfig['dstnot']
))->setHelp('Invert the sense of the match.');

$group->add(new Form_Select(
	'dsttype',
	null,
	dsttype_selected(),
	build_dsttype_list()
))->setHelp('Type');

$group->add(new Form_IpAddress(
	'dst',
	null,
	is_specialnet($pconfig['dst']) ? '': $pconfig['dst'],
	'ALIASV4V6'
))->addMask('dstmask', $pconfig['dstmask'], 31)->setHelp('Address/mask');

$group->setHelp('The 1:1 mapping will only be used for connections to or from the specified destination. Hint: this is usually "Any".');

$section->add($group);

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

$section->addInput(new Form_Select(
	'natreflection',
	'NAT reflection',
	$pconfig['natreflection'],
	array(
		'default' => gettext('Use system default'),
		'enable'  => gettext('Enable'),
		'disable' => gettext('Disable')
	)
));

$form->add($section);

print($form);
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Disables the specified input element
	function disableInput(id, disable) {
		$('#' + id).prop("disabled", disable);
	}

	function typesel_change() {
		switch ($('#srctype').find(":selected").index()) {
			case 1: // single
				disableInput('src', false);
				$('#srcmask').val('');
				disableInput('srcmask', true);
				break;
			case 2: // network
				disableInput('src', false);
				disableInput('srcmask', false);
				break;
			default:
				$('#src').val('');
				disableInput('src', true);
				$('#srcmask').val('');
				disableInput('srcmask', true);
				break;
		}

		switch ($('#dsttype').find(":selected").index()) {
			case 1: // single
				disableInput('dst', false);
				$('#dstmask').val('');
				disableInput('dstmask', true);;
				break;
			case 2: // network /
				disableInput('dst', false);
				disableInput('dstmask', false);
				break;
			default:
				$('#dst').val('');
				disableInput('dst', true);
				$('#dstmask').val('');
				disableInput('dstmask', true);
				break;
		}

		switch ($('#exttype').find(":selected").index()) {
			case 0: // single
				disableInput('external', false);
				break;
			default:
				$('#external').val('');
				disableInput('external', true);
				break;
		}
	}

	// ---------- Click checkbox handlers ---------------------------------------------------------

	$('#srctype').change(function () {
		typesel_change();
	});

	$('#dsttype').change(function () {
		typesel_change();
	});

	$('#exttype').change(function () {
		typesel_change();
	});

	// ---------- On initial page load ------------------------------------------------------------

	typesel_change();

	// ---------- Autocomplete --------------------------------------------------------------------

	var addressarray = <?= json_encode(get_alias_list(array("host", "network", "urltable"))) ?>;

	$('#dst').autocomplete({
		source: addressarray
	});
});
//]]>
</script>

<?php include("foot.inc");
