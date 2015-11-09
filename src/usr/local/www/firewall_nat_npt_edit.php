<?php
/*
	firewall_nat_npt_edit.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *  Copyright (c)  2011 Seth Mos <seth.mos@dds.nl>
 *
 *  Redistribution and use in source and binary forms, with or without modification,
 *  are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution.
 *
 *  3. All advertising materials mentioning features or use of this software
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
 */
/*
	pfSense_MODULE: nat
*/

##|+PRIV
##|*IDENT=page-firewall-nat-npt-edit
##|*NAME=Firewall: NAT: NPt: Edit page
##|*DESCR=Allow access to the 'Firewall: NAT: NPt: Edit' page.
##|*MATCH=firewall_nat_npt_edit.php*
##|-PRIV

function natnptcmp($a, $b) {
	return ipcmp($a['external'], $b['external']);
}

function nat_npt_rules_sort() {
	global $g, $config;

	if (!is_array($config['nat']['npt'])) {
			return;
	}

	usort($config['nat']['npt'], "natnptcmp");
}

require("guiconfig.inc");
require_once("interfaces.inc");
require_once("filter.inc");
require("shaper.inc");

$ifdisp = get_configured_interface_with_descr();

foreach ($ifdisp as $kif => $kdescr) {
	$specialsrcdst[] = "{$kif}";
	$specialsrcdst[] = "{$kif}ip";
}

if (!is_array($config['nat']['npt']))
	$config['nat']['npt'] = array();

$a_npt = &$config['nat']['npt'];

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
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

	$pconfig['external'] = $a_npt[$id]['external'];
	$pconfig['descr'] = $a_npt[$id]['descr'];
} else {
	$pconfig['interface'] = "wan";
}


if ($_POST) {

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
			$a_npt[] = $natent;
		}
		nat_npt_rules_sort();

		if (write_config()) {
			mark_subsystem_dirty('natconf');
		}

		header("Location: firewall_nat_npt.php");
		exit;
	}
}

function build_if_list() {
	global $ifdisp;

	foreach ($ifdisp as $if => $ifdesc) {
		if (have_ruleint_access($if))
			$interfaces[$if] = $ifdesc;
	}

	if ($config['l2tp']['mode'] == "server")
		if (have_ruleint_access("l2tp"))
			$interfaces['l2tp'] = "L2TP VPN";

	if ($config['pppoe']['mode'] == "server")
		if (have_ruleint_access("pppoe"))
			$interfaces['pppoe'] = "PPPoE Server";

	/* add ipsec interfaces */
	if (isset($config['ipsec']['enable']) || isset($config['ipsec']['mobileclients']['enable'])) {
		if (have_ruleint_access("enc0"))
			$interfaces["enc0"] = "IPsec";
	}

	/* add openvpn/tun interfaces */
	if ($config['openvpn']["openvpn-server"] || $config['openvpn']["openvpn-client"])
		$interfaces["openvpn"] = "OpenVPN";

	return($interfaces);
}

$pgtitle = array(gettext("Firewall"), gettext("NAT"), gettext("NPt"), gettext("Edit"));
include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

require_once('classes/Form.class.php');

$form = new Form();

$section = new Form_Section('Edit NAT NPt entry');

$section->addInput(new Form_Checkbox(
	'disabled',
	'Disabled',
	'Disable this rule',
	$pconfig['disabled']
));

$section->addInput(new Form_Select(
	'interface',
	'Interface',
	$pconfig['interface'],
	build_if_list()
))->setHelp('Choose which interface this rule applies to.' . '<br />' .
			'Hint: in most cases, you\'ll want to use "WAN" here.');

$section->addInput(new Form_Checkbox(
	'srcnot',
	'Internal IPv6 prefix',
	'Not',
	$pconfig['srcnot']
))->setHelp('Use this option to invert the sense of the match. ');

$section->addInput(new Form_IpAddress(
	'src',
	'Address',
	$pconfig['src']
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
	'Address',
	$pconfig['dst']
))->addMask('dstmask', $pconfig['dstmask'])->setHelp('Global Unicast routable IPv6 prefix');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('You may enter a description here for your reference (not parsed).');

if (isset($id) && $a_npt[$id]) {
	$section->addInput(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->add($section);
print($form);

include("foot.inc");
