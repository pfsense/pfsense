<?php
/*
 * firewall_nat_nat64_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2014-2020 Nick Whaley
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
##|*IDENT=page-firewall-nat-nat64-edit
##|*NAME=Firewall: NAT: NAT64: Edit
##|*DESCR=Allow access to the 'Firewall: NAT: NAT64: Edit' page.
##|*MATCH=firewall_nat_nat64_edit.php
##|-PRIV

require_once("guiconfig.inc");
require_once("filter.inc");
require_once("shaper.inc");

init_config_arr(array('nat', 'nat64', 'rule'));
unset($a_nat64);
$a_nat64 = &$config['nat']['nat64']['rule'];

//defaults
$pconfig['prefix4mask'] = 24;
$pconfig['prefix6'] = '64:ff9b::';

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

if (isset($id) && $a_nat64[$id]) {
	$pconfig['disabled'] = isset($a_nat64[$id]['disabled']);
	$pconfig['allowrfc1918'] = isset($a_nat64[$id]['allowrfc1918']);

	$dummyvar = 0;
	address_to_pconfig($a_nat64[$id]['prefix4'], $pconfig['prefix4'], $pconfig['prefix4mask'],
		$dummyvar, $dummyvar, $dummyvar);
	address_to_pconfig($a_nat64[$id]['prefix6'], $pconfig['prefix6'], $dummyvar,
		$dummyvar, $dummyvar, $dummyvar);

	$pconfig['descr'] = $a_nat64[$id]['descr'];
}

if (isset($_REQUEST['dup'])) {
	unset($id);
}

if ($_POST['save']) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields[] = "prefix4";
	$reqdfieldsn[] = gettext("IPv4 prefix");
	$reqdfields[] = "prefix6";
	$reqdfieldsn[] = gettext("IPv6 prefix");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!is_ipaddrv4(trim($_POST['prefix4']))) {
		$input_errors[] = gettext("The specified IPv4 prefix is not valid.");
	}
	if (!is_ipaddrv6(trim($_POST['prefix6']))) {
		$input_errors[] = gettext("The specified IPv6 prefix is not valid.");
	}

	if (!$input_errors) {
		$natent = array();

		$natent['disabled'] = isset($_POST['disabled']) ? true:false;
		$natent['allowrfc1918'] = isset($_POST['allowrfc1918']) ? true:false;
		$natent['descr'] = $_POST['descr'];

		if ($_POST['prefix4']) {
			$_POST['prefix4'] = trim($_POST['prefix4']);
		}
		if ($_POST['prefix6']) {
			$_POST['prefix6'] = trim($_POST['prefix6']);
		}

		pconfig_to_address($natent['prefix4'], $_POST['prefix4'], $_POST['prefix4mask']);

		pconfig_to_address($natent['prefix6'], $_POST['prefix6'], 96);

		if (isset($id) && $a_nat64[$id]) {
			$a_nat64[$id] = $natent;
		} else {
			if (is_numeric($after)) {
				array_splice($a_nat64, $after+1, 0, array($natent));
			} else {
				$a_nat64[] = $natent;
			}
		}

		if (write_config(gettext("Firewall: NAT: NAT64 - saved/edited NAT64 mapping."))) {
			mark_subsystem_dirty('nat64');
		}
		
		header("Location: firewall_nat_nat64.php");
		exit;
	}
}

$pgtitle = array(gettext("Firewall"), gettext("NAT"), gettext("NAT64"), gettext("Edit"));
$pglinks = array("", "firewall_nat.php", "firewall_nat_nat64.php", "@self");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('Edit NAT64 Entry');

$section->addInput(new Form_Checkbox(
	'disabled',
	'Disabled',
	'Disable this rule',
	$pconfig['disabled']
));

$section->addInput(new Form_IpAddress(
	'prefix4',
	'*IPv4 Addresses',
	$pconfig['prefix4'],
	'V4'
))->addMask('prefix4mask', $pconfig['prefix4mask'], 32, 0)->setHelp('A range of IPv4 addresses to use dynamically as a source address when an IPv6 packet is translated to IPv4.');

$section->addInput(new Form_IpAddress(
	'prefix6',
	'*IPv6 Addresses',
	$pconfig['prefix6'],
	'V6'
))->addMask('prefix6mask', 96)->setHelp('A 96-bit masked IPv6 prefix to use to prepend to/from a IPv4 address. ' .
	'Often is the well-known prefix 64:ff9b::/96');
	
$section->addInput(new Form_Checkbox(
	'allowrfc1918',
	'Allow RFC1918',
	'Allow private RFC1918 addresses',
	$pconfig['allowrfc1918']
))->setHelp('Turn on processing private IPv4 addresses. '.
'By default IPv6 packets with destinations mapped to private address ranges defined by RFC1918 are not processed. ');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

if (isset($id) && $a_nat64[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->add($section);
print($form);

?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {
	disableInput('prefix6mask', true);
});
//]]>
</script>
<?php

include("foot.inc");
