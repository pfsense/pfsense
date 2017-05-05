<?php
/*
 * firewall_nat_1to1_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/firewall_nat_1to1.php');

function get_must_be_both_text() {
	return(" " . gettext("They must be either both IPv4 or both IPv6 addresses."));
}

$specialsrcdst = explode(" ", "any pptp pppoe l2tp openvpn");
$ifdisp = get_configured_interface_with_descr();

foreach ($ifdisp as $kif => $kdescr) {
	$specialsrcdst[] = "{$kif}";
	$specialsrcdst[] = "{$kif}ip";
}

if (!is_array($config['nat']['onetoone'])) {
	$config['nat']['onetoone'] = array();
}

$a_1to1 = &$config['nat']['onetoone'];

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

if (isset($id) && $a_1to1[$id]) {
	$pconfig['nobinat'] = isset($a_1to1[$id]['nobinat']);
	$pconfig['disabled'] = isset($a_1to1[$id]['disabled']);

	address_to_pconfig($a_1to1[$id]['source'], $pconfig['src'],
		$pconfig['srcmask'], $pconfig['srcnot'],
		$pconfig['srcbeginport'], $pconfig['srcendport']);

	address_to_pconfig($a_1to1[$id]['destination'], $pconfig['dst'],
		$pconfig['dstmask'], $pconfig['dstnot'],
		$pconfig['dstbeginport'], $pconfig['dstendport']);

	$pconfig['interface'] = $a_1to1[$id]['interface'];
	if (!$pconfig['interface']) {
		$pconfig['interface'] = "wan";
	}

	$pconfig['external'] = $a_1to1[$id]['external'];
	$pconfig['descr'] = $a_1to1[$id]['descr'];
	$pconfig['natreflection'] = $a_1to1[$id]['natreflection'];
} else {
	$pconfig['interface'] = "wan";
}

if (isset($_REQUEST['dup'])) {
	unset($id);
}

if ($_POST['save']) {

	unset($input_errors);
	/*	run through $_POST items encoding HTML entities so that the user
	 *	cannot think he is slick and perform a XSS attack on the unwilling
	 */
	foreach ($_POST as $key => $value) {
		if ($key == 'descr') {
			continue;
		}

		$temp = str_replace(">", "", $value);
		$newpost = htmlentities($temp);

		if ($newpost != $temp) {
			$input_errors[] = sprintf(gettext("Invalid characters detected (%s).  Please remove invalid characters and save again."), $temp);
		}
	}

	/* input validation */
	if (isset($_POST['nobinat'])) {
		$reqdfields = explode(" ", "interface");
		$reqdfieldsn = array(gettext("Interface"));
	} else {
		$reqdfields = explode(" ", "interface external");
		$reqdfieldsn = array(gettext("Interface"), gettext("External subnet"));
	}

	if ($_POST['srctype'] == "single" || $_POST['srctype'] == "network") {
		$reqdfields[] = "src";
		$reqdfieldsn[] = gettext("Source address");
	}

	if ($_POST['dsttype'] == "single" || $_POST['dsttype'] == "network") {
		$reqdfields[] = "dst";
		$reqdfieldsn[] = gettext("Destination address");
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($_POST['external']) {
		$_POST['external'] = trim($_POST['external']);
	}
	if ($_POST['src']) {
		$_POST['src'] = trim($_POST['src']);
	}
	if ($_POST['dst']) {
		$_POST['dst'] = trim($_POST['dst']);
	}

	if (is_specialnet($_POST['srctype'])) {
		$_POST['src'] = $_POST['srctype'];
		$_POST['srcmask'] = 0;
	} else if ($_POST['srctype'] == "single") {
		$_POST['srcmask'] = 32;
	}

	if (is_specialnet($_POST['dsttype'])) {
		$_POST['dst'] = $_POST['dsttype'];
		$_POST['dstmask'] = 0;
	} else if ($_POST['dsttype'] == "single") {
		$_POST['dstmask'] = 32;
	} else if (is_ipaddr($_POST['dsttype'])) {
		$_POST['dst'] = $_POST['dsttype'];
		$_POST['dstmask'] = 32;
		$_POST['dsttype'] = "single";
	}

	$pconfig = $_POST;

	$extipaddrtype = false;
	$srcipaddrtype = false;
	$dstipaddrtype = false;

	/* For external, user can enter only ip's */
	if ($_POST['external']) {
		$extipaddrtype = validateipaddr($_POST['external'], IPV4V6, "External subnet IP", $input_errors, false);
	}

	/* For dst, if user enters an alias and selects "network" then disallow. */
	if ($_POST['dsttype'] == "network" && is_alias($_POST['dst'])) {
		$input_errors[] = gettext("Alias entries must specify a single host or alias.");
	}

	/* For src, user can enter only ips or networks */
	if (!is_specialnet($_POST['srctype'])) {
		if ($_POST['src']) {
			$srcipaddrtype = validateipaddr($_POST['src'], IPV4V6, "Internal address", $input_errors, false);
			if ($srcipaddrtype) {
				// It is a valid IP address of some address family.
				// Check that the address family matches the other IP addresses entered.
				if ($extipaddrtype && ($srcipaddrtype != $extipaddrtype)) {
					$input_errors[] = sprintf(
						gettext('The external IP address (%1$s) and internal IP address (%2$s) are of different address families.') .
							get_must_be_both_text(),
						$_POST['external'],
						$_POST['src']);
				}
			}
		}

		if (($_POST['srcmask'] && !is_numericint($_POST['srcmask']))) {
			$input_errors[] = gettext("A valid internal bit count must be specified.");
		}
	}

	/* For dst, user can enter ips, networks or aliases */
	if (!is_specialnet($_POST['dsttype'])) {
		if ($_POST['dst']) {
			$dstipaddrtype = validateipaddr($_POST['dst'], IPV4V6, "Destination address", $input_errors, true);
			if ($dstipaddrtype == ALIAS) {
				// It is an alias.
				// pf does not report "error loading rules" if the address family of items in the alias does not match the external/internal address family.
				// So that is up to the user to make sensible, we do not try and verify it here.
			} elseif ($dstipaddrtype) {
				// It is a valid IP address of some address family.
				// Check that the address family matches the other IP addresses entered.
				if ($extipaddrtype && ($dstipaddrtype != $extipaddrtype)) {
					$input_errors[] = sprintf(
						gettext('The external IP address (%1$s) and destination IP address (%2$s) are of different address families.') .
							get_must_be_both_text(),
						$_POST['external'],
						$_POST['dst']);
				}
				if ($srcipaddrtype && ($dstipaddrtype != $srcipaddrtype)) {
					$input_errors[] = sprintf(
						gettext('The internal IP address (%1$s) and destination IP address (%2$s) are of different address families.') .
							get_must_be_both_text(),
						$_POST['src'],
						$_POST['dst']);
				}
			}
		}

		if (($_POST['dstmask'] && !is_numericint($_POST['dstmask']))) {
			$input_errors[] = gettext("A valid destination bit count must be specified.");
		}
	}

	/* check for overlaps with other 1:1 */
	foreach ($a_1to1 as $natent) {
		if (isset($id) && ($a_1to1[$id]) && ($a_1to1[$id] === $natent)) {
			continue;
		}

		if (check_subnets_overlap($_POST['internal'], $_POST['subnet'], $natent['internal'], $natent['subnet'])) {
			//$input_errors[] = "Another 1:1 rule overlaps with the specified internal subnet.";
			//break;
		}
	}

	if (!$input_errors) {
		$natent = array();

		$natent['nobinat'] = isset($_POST['nobinat']) ? true:false;
		$natent['disabled'] = isset($_POST['disabled']) ? true:false;
		$natent['external'] = $_POST['external'];
		$natent['descr'] = $_POST['descr'];
		$natent['interface'] = $_POST['interface'];

		pconfig_to_address($natent['source'], $_POST['src'],
			$_POST['srcmask'], $_POST['srcnot']);

		pconfig_to_address($natent['destination'], $_POST['dst'],
			$_POST['dstmask'], $_POST['dstnot']);

		if ($_POST['natreflection'] == "enable" || $_POST['natreflection'] == "disable") {
			$natent['natreflection'] = $_POST['natreflection'];
		} else {
			unset($natent['natreflection']);
		}

		if (isset($id) && $a_1to1[$id]) {
			$a_1to1[$id] = $natent;
		} else {
			if (is_numeric($after)) {
				array_splice($a_1to1, $after+1, 0, array($natent));
			} else {
				$a_1to1[] = $natent;
			}
		}

		if (write_config(gettext("Firewall: NAT: 1:1 - saved/edited NAT 1:1 mapping."))) {
			mark_subsystem_dirty('natconf');
		}
		header("Location: firewall_nat_1to1.php");
		exit;
	}
}

$pgtitle = array(gettext("Firewall"), gettext("NAT"), gettext("1:1"), gettext("Edit"));
$pglinks = array("", "firewall_nat.php", "firewall_nat_1to1.php", "@self");
include("head.inc");

function build_srctype_list() {
	global $pconfig, $ifdisp;

	$list = array('any' => gettext('Any'), 'single' => gettext('Single host'), 'network' => gettext('Network'));

	$sel = is_specialnet($pconfig['src']);

	if (have_ruleint_access("pppoe")) {
		$list['pppoe'] = gettext('PPPoE clients');
	}

	if (have_ruleint_access("l2tp")) {
		$list['l2tp'] = gettext('L2TP clients');
	}

	foreach ($ifdisp as $ifent => $ifdesc) {
		if (have_ruleint_access($ifent)) {
			$list[$ifent] = $ifdesc . ' net';
			$list[$ifent . 'ip'] = $ifdesc . ' address';
		}
	}

	return($list);
}

function srctype_selected() {
	global $pconfig;

	if ($pconfig['srctype']) {
		// The rule type came from the $_POST array, after input errors, so keep it.
		return $pconfig['srctype'];
	}

	$sel = is_specialnet($pconfig['src']);

	if (!$sel) {
		if (($pconfig['srcmask'] == 32) || (!isset($pconfig['srcmask']))) {
			return('single');
		}

		return('network');
	}

	return($pconfig['src']);
}

function build_dsttype_list() {
	global $pconfig, $config, $ifdisp;

	$sel = is_specialnet($pconfig['dst']);
	$list = array('any' => gettext('Any'), 'single' => gettext('Single host or alias'), 'network' => gettext('Network'));

	if (have_ruleint_access("pppoe")) {
		$list['pppoe'] = gettext('PPPoE clients');
	}

	if (have_ruleint_access("l2tp")) {
		$list['l2tp'] = gettext('L2TP clients');
	}

	foreach ($ifdisp as $if => $ifdesc) {
		if (have_ruleint_access($if)) {
			$list[$if] = $ifdesc;
			$list[$if . 'ip'] = $ifdesc . ' address';
		}
	}

	if (is_array($config['virtualip']['vip'])) {
		foreach ($config['virtualip']['vip'] as $sn) {
			if (($sn['mode'] == "proxyarp" || $sn['mode'] == "other") && $sn['type'] == "network") {
				$list[$sn['subnet'] . '/' . $sn['subnet_bits']] = 'Subnet: ' . $sn['subnet'] . '/' . $sn['subnet_bits'] . ' (' . $sn['descr'] . ')';
				if (isset($sn['noexpand'])) {
					continue;
				}
				$start = ip2long32(gen_subnet($sn['subnet'], $sn['subnet_bits']));
				$end = ip2long32(gen_subnet_max($sn['subnet'], $sn['subnet_bits']));
				$len = $end - $start;
				for ($i = 0; $i <= $len; $i++) {
					$snip = long2ip32($start+$i);

					$list[$snip] = $snip . ' (' . $sn['descr'] . ')';
				}
			} else {
				$list[$sn['subnet']] = $sn['subnet'] . ' (' . $sn['descr'] . ')';
			}
		}
	}

	return($list);
}

function dsttype_selected() {
	global $pconfig;

	if ($pconfig['dsttype']) {
		// The rule type came from the $_POST array, after input errors, so keep it.
		return $pconfig['dsttype'];
	}

	$sel = is_specialnet($pconfig['dst']);

	if (empty($pconfig['dst']) || $pconfig['dst'] == "any") {
		return('any');
	}

	if (!$sel) {
		if ($pconfig['dstmask'] == 32) {
			return('single');
		}

		return('network');
	}

	return($pconfig['dst']);
}

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

$iflist = get_configured_interface_with_descr(true);

foreach ($iflist as $if => $ifdesc) {
	if (have_ruleint_access($if)) {
		$interfaces[$if] = $ifdesc;
	}
}

if ($config['l2tp']['mode'] == "server") {
	if (have_ruleint_access("l2tp")) {
		$interfaces['l2tp'] = gettext("L2TP VPN");
	}
}

if (is_pppoe_server_enabled() && have_ruleint_access("pppoe")) {
	$interfaces['pppoe'] = gettext("PPPoE Server");
}

/* add ipsec interfaces */
if (ipsec_enabled() && have_ruleint_access("enc0")) {
	$interfaces["enc0"] = gettext("IPsec");
}

/* add openvpn/tun interfaces */
if	($config['openvpn']["openvpn-server"] || $config['openvpn']["openvpn-client"]) {
	$interfaces["openvpn"] = gettext("OpenVPN");
}

$section->addInput(new Form_Select(
	'interface',
	'*Interface',
	$pconfig['interface'],
	$interfaces
))->setHelp('Choose which interface this rule applies to. In most cases "WAN" is specified.');

$section->addInput(new Form_IpAddress(
	'external',
	'*External subnet IP',
	$pconfig['external']
))->setHelp('Enter the external (usually on a WAN) subnet\'s starting address for the 1:1 mapping. ' .
			'The subnet mask from the internal address below will be applied to this IP address.');

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
	}

	// ---------- Click checkbox handlers ---------------------------------------------------------

	$('#srctype').change(function () {
		typesel_change();
	});

	$('#dsttype').change(function () {
		typesel_change();
	});

	// ---------- On initial page load ------------------------------------------------------------

	typesel_change();

	// ---------- Autocomplete --------------------------------------------------------------------

	var addressarray = <?= json_encode(get_alias_list(array("host", "network", "openvpn", "urltable"))) ?>;

	$('#dst').autocomplete({
		source: addressarray
	});
});
//]]>
</script>

<?php include("foot.inc");
