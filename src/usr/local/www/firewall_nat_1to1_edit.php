<?php
/*
	firewall_nat_1to1_edit.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
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

##|+PRIV
##|*IDENT=page-firewall-nat-1-1-edit
##|*NAME=Firewall: NAT: 1:1: Edit
##|*DESCR=Allow access to the 'Firewall: NAT: 1:1: Edit' page.
##|*MATCH=firewall_nat_1to1_edit.php*
##|-PRIV

require("guiconfig.inc");
require_once("interfaces.inc");
require_once("filter.inc");
require_once("ipsec.inc");
require("shaper.inc");

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/firewall_nat_1to1.php');

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

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

$after = $_GET['after'];
if (isset($_POST['after'])) {
	$after = $_POST['after'];
}

if (isset($_GET['dup'])) {
	$id = $_GET['dup'];
	$after = $_GET['dup'];
}

if (isset($id) && $a_1to1[$id]) {
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

if (isset($_GET['dup'])) {
	unset($id);
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;
	/*	run through $_POST items encoding HTML entities so that the user
	 *	cannot think he is slick and perform a XSS attack on the unwilling
	 */
	foreach ($_POST as $key => $value) {
		$temp = str_replace(">", "", $value);
		$newpost = htmlentities($temp);

		if ($newpost != $temp) {
			$input_errors[] = sprintf(gettext("Invalid characters detected (%s).  Please remove invalid characters and save again."), $temp);
		}
	}

	/* input validation */
	$reqdfields = explode(" ", "interface external");
	$reqdfieldsn = array(gettext("Interface"), gettext("External subnet"));

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

	/* For external, user can enter only ip's */
	if (($_POST['external'] && !is_ipaddr($_POST['external']))) {
		$input_errors[] = gettext("A valid external subnet must be specified.");
	}

	/* For dst, if user enters an alias and selects "network" then disallow. */
	if ($_POST['dsttype'] == "network" && is_alias($_POST['dst'])) {
		$input_errors[] = gettext("You must specify single host or alias for alias entries.");
	}

	/* For src, user can enter only ip's or networks */
	if (!is_specialnet($_POST['srctype'])) {
		if (($_POST['src'] && !is_ipaddr($_POST['src']))) {
			$input_errors[] = sprintf(gettext("%s is not a valid internal IP address."), $_POST['src']);
		}

		if (($_POST['srcmask'] && !is_numericint($_POST['srcmask']))) {
			$input_errors[] = gettext("A valid internal bit count must be specified.");
		}
	}

	/* For dst, user can enter ip's, networks or aliases */
	if (!is_specialnet($_POST['dsttype'])) {
		if (($_POST['dst'] && !is_ipaddroralias($_POST['dst']))) {
			$input_errors[] = sprintf(gettext("%s is not a valid destination IP address or alias."), $_POST['dst']);
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

		if (write_config()) {
			mark_subsystem_dirty('natconf');
		}
		header("Location: firewall_nat_1to1.php");
		exit;
	}
}

$pgtitle = array(gettext("Firewall"), gettext("NAT"), gettext("1:1"), gettext("Edit"));
include("head.inc");

function build_srctype_list() {
	global $pconfig, $ifdisp;

	$list = array('any' => 'Any', 'single' => 'Single host or alias', 'network' => 'Network');

	$sel = is_specialnet($pconfig['src']);

	if (have_ruleint_access("pppoe")) {
		$list['pppoe'] = 'PPPoE clients';
	}

	if (have_ruleint_access("l2tp")) {
		$list['l2tp'] = 'L2TP clients';
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
	$list = array('any' => 'Any', 'single' => 'Single host or alias', 'network' => 'Network', '(self)' => 'This Firewall (self)');

	if (have_ruleint_access("pppoe")) {
		$list['pppoe'] = 'PPPoE clients';
	}

	if (have_ruleint_access("l2tp")) {
		$list['l2tp'] = 'L2TP clients';
	}

	foreach ($ifdisp as $if => $ifdesc) {
		if (have_ruleint_access($if)) {
			$list[$if] = $ifdesc;
			$list[$if . 'ip'] = $ifdesc . ' address';
		}
	}

	if (is_array($config['virtualip']['vip'])) {
		foreach ($config['virtualip']['vip'] as $sn) {
			if (isset($sn['noexpand'])) {
				continue;
			}

			if ($sn['mode'] == "proxyarp" && $sn['type'] == "network") {
				$start = ip2long32(gen_subnet($sn['subnet'], $sn['subnet_bits']));
				$end = ip2long32(gen_subnet_max($sn['subnet'], $sn['subnet_bits']));
				$len = $end - $start;

				for ($i = 0; $i <= $len; $i++) {
					$snip = long2ip32($start+$i);

					$list[$snip] = $snip . ' (' . $sn['descr'] . ')';
				}

				$list[$sn['subnet']] = $sn['subnet'] . ' (' . $sn['descr'] . ')';
			}
		}
	}

	return($list);
}

function dsttype_selected() {
	global $pconfig;

	$sel = is_specialnet($pconfig['dst']);

	if (empty($pconfig['dst'] || $pconfig['dst'] == "any")) {
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

$form = new Form(new Form_Button(
	'Submit',
	gettext("Save")
));

$section = new Form_Section('Edit NAT 1 to 1 entry');

$section->addInput(new Form_Checkbox(
	'nordr',
	'No RDR (NOT)',
	'Disable redirection for traffic matching this rule',
	$pconfig['nordr']
))->setHelp('This option is rarely needed, don\'t use this unless you know what you\'re doing.');

$iflist = get_configured_interface_with_descr(false, true);

foreach ($iflist as $if => $ifdesc) {
	if (have_ruleint_access($if)) {
		$interfaces[$if] = $ifdesc;
	}
}

if ($config['l2tp']['mode'] == "server") {
	if (have_ruleint_access("l2tp")) {
		$interfaces['l2tp'] = "L2TP VPN";
	}
}

if (is_pppoe_server_enabled() && have_ruleint_access("pppoe")) {
	$interfaces['pppoe'] = "PPPoE Server";
}

/* add ipsec interfaces */
if (ipsec_enabled() && have_ruleint_access("enc0")) {
	$interfaces["enc0"] = "IPsec";
}

/* add openvpn/tun interfaces */
if	($config['openvpn']["openvpn-server"] || $config['openvpn']["openvpn-client"]) {
	$interfaces["openvpn"] = "OpenVPN";
}

$section->addInput(new Form_Select(
	'interface',
	'Interface',
	$pconfig['interface'],
	$interfaces
))->setHelp('Choose which interface this rule applies to. In most cases "WAN" is specified.');

$section->addInput(new Form_IpAddress(
	'external',
	'External subnet IP',
	$pconfig['external']
))->setHelp('Enter the external (usually on a WAN) subnet\'s starting address for the 1:1 mapping. ' .
			'The subnet mask from the internal address below will be applied to this IP address.');

$group = new Form_Group('Internal IP');

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
))->addMask('srcmask', $pconfig['srcmask'], 31)->setHelp('Address/mask')->setPattern('[a-zA-Z0-9\.\:\_]+');

$group->setHelp('Enter the internal (LAN) subnet for the 1:1 mapping. ' .
				'The subnet size specified for the internal subnet will be applied to the external subnet.');

$section->add($group);

$group = new Form_Group('Destination');

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
	is_specialnet($pconfig['dst']) ? '': $pconfig['dst']
))->addMask('dstmask', $pconfig['dstmask'], 31)->setHelp('Address/mask')->setPattern('[a-zA-Z0-9\.\:\_]+');

$group->setHelp('The 1:1 mapping will only be used for connections to or from the specified destination. Hint: this is usually "Any".');

$section->add($group);

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('You may enter a description here for your reference (not parsed).');

$section->addInput(new Form_Select(
	'natreflection',
	'NAT reflection',
	$pconfig['natreflection'],
	array(
		'default' => 'Use system default',
		'enable'  => 'Enable',
		'disable' => 'Disable'
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

	$('#srctype').click(function () {
		typesel_change();
	});

	$('#dsttype').click(function () {
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
