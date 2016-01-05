<?php
/*
	firewall_nat_out_edit.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *  Some or all of this file is based on the m0n0wall project which is
 *  Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
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

##|+PRIV
##|*IDENT=page-firewall-nat-outbound-edit
##|*NAME=Firewall: NAT: Outbound: Edit
##|*DESCR=Allow access to the 'Firewall: NAT: Outbound: Edit' page.
##|*MATCH=firewall_nat_out_edit.php*
##|-PRIV

require("guiconfig.inc");
require_once("ipsec.inc");
require_once("filter.inc");
require("shaper.inc");

if (!is_array($config['nat']['outbound'])) {
	$config['nat']['outbound'] = array();
}

if (!is_array($config['nat']['outbound']['rule'])) {
	$config['nat']['outbound']['rule'] = array();
}

$a_out = &$config['nat']['outbound']['rule'];

if (!is_array($config['aliases']['alias'])) {
	$config['aliases']['alias'] = array();
}
$a_aliases = &$config['aliases']['alias'];

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (is_numericint($_GET['after']) || $_GET['after'] == "-1") {
	$after = $_GET['after'];
}
if (isset($_POST['after']) && (is_numericint($_POST['after']) || $_POST['after'] == "-1")) {
	$after = $_POST['after'];
}

if (isset($_GET['dup']) && is_numericint($_GET['dup'])) {
	$id = $_GET['dup'];
	$after = $_GET['dup'];
}

if (isset($id) && $a_out[$id]) {
	if (isset($a_out[$id]['created']) && is_array($a_out[$id]['created'])) {
		$pconfig['created'] = $a_out[$id]['created'];
	}

	if (isset($a_out[$id]['updated']) && is_array($a_out[$id]['updated'])) {
		$pconfig['updated'] = $a_out[$id]['updated'];
	}

	$pconfig['protocol'] = $a_out[$id]['protocol'];
	list($pconfig['source'], $pconfig['source_subnet']) = explode('/', $a_out[$id]['source']['network']);
	if (!is_numeric($pconfig['source_subnet'])) {
		$pconfig['source_subnet'] = 32;
	}
	$pconfig['sourceport'] = $a_out[$id]['sourceport'];
	address_to_pconfig($a_out[$id]['destination'], $pconfig['destination'],
		$pconfig['destination_subnet'], $pconfig['destination_not'],
		$none, $none);

	$pconfig['dstport'] = $a_out[$id]['dstport'];
	$pconfig['natport'] = $a_out[$id]['natport'];
	$pconfig['target'] = $a_out[$id]['target'];
	$pconfig['targetip'] = $a_out[$id]['targetip'];
	$pconfig['targetip_subnet'] = $a_out[$id]['targetip_subnet'];
	$pconfig['poolopts'] = $a_out[$id]['poolopts'];
	$pconfig['interface'] = $a_out[$id]['interface'];

	if (!$pconfig['interface']) {
		$pconfig['interface'] = "wan";
	}

	$pconfig['descr'] = $a_out[$id]['descr'];
	$pconfig['nonat'] = $a_out[$id]['nonat'];
	$pconfig['disabled'] = isset($a_out[$id]['disabled']);
	$pconfig['staticnatport'] = isset($a_out[$id]['staticnatport']);
	$pconfig['nosync'] = isset($a_out[$id]['nosync']);
} else {
	$pconfig['source_subnet'] = 24;
	$pconfig['destination'] = "any";
	$pconfig['destination_subnet'] = 24;
	$pconfig['interface'] = "wan";
}

if (isset($_GET['dup']) && is_numericint($_GET['dup'])) {
	unset($id);
}

if ($_POST) {
	if ($_POST['destination_type'] == "any") {
		$_POST['destination'] = "any";
		$_POST['destination_subnet'] = 24;
	}

	if ($_POST['source_type'] == "any") {
		$_POST['source'] = "any";
		$_POST['source_subnet'] = 24;
	} elseif ($_POST['source_type'] == "(self)") {
		$_POST['source'] = "(self)";
		$_POST['source_subnet'] = 24;
	}

	unset($input_errors);
	$pconfig = $_POST;
	/*  run through $_POST items encoding HTML entitles so that the user
	 *  cannot think he is slick and perform a XSS attack on the unwilling
	 */
	foreach ($_POST as $key => $value) {
		$temp = str_replace(">", "", $value);
		$newpost = htmlentities($temp);
		if ($newpost <> $temp) {
			$input_errors[] = sprintf(gettext("Invalid characters detected (%s).  Please remove invalid characters and save again."), $temp);
		}
	}

	/* input validation */
	$reqdfields = explode(" ", "interface protocol source source_subnet destination destination_subnet");
	$reqdfieldsn = array(gettext("Interface"), gettext("Protocol"), gettext("Source"), gettext("Source bit count"), gettext("Destination"), gettext("Destination bit count"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	$protocol_uses_ports = in_array($_POST['protocol'], explode(" ", "any tcp udp tcp/udp"));

	if ($_POST['source']) {
		$_POST['source'] = trim($_POST['source']);
	}
	if ($_POST['destination']) {
		$_POST['destination'] = trim($_POST['destination']);
	}
	if ($_POST['targetip']) {
		$_POST['targetip'] = trim($_POST['targetip']);
	}
	if ($_POST['sourceport']) {
		$_POST['sourceport'] = trim($_POST['sourceport']);
	}
	if ($_POST['dstport']) {
		$_POST['dstport'] = trim($_POST['dstport']);
	}
	if ($_POST['natport']) {
		$_POST['natport'] = trim($_POST['natport']);
	}

	if ($protocol_uses_ports && $_POST['sourceport'] <> "" && !(is_portoralias($_POST['sourceport']) || is_portrange($_POST['sourceport']))) {
		$input_errors[] = gettext("You must supply either a valid port or port alias for the source port entry.");
	}

	if ($protocol_uses_ports && $_POST['dstport'] <> "" && !(is_portoralias($_POST['dstport']) || is_portrange($_POST['dstport']))) {
		$input_errors[] = gettext("You must supply either a valid port or port alias for the destination port entry.");
	}

	if ($protocol_uses_ports && $_POST['natport'] <> "" && !(is_portoralias($_POST['natport']) || is_portrange($_POST['natport'])) && !isset($_POST['nonat'])) {
		$input_errors[] = gettext("You must supply a valid port for the NAT port entry.");
	}

	if (($_POST['source_type'] != "any") && ($_POST['source_type'] != "(self)")) {
		if ($_POST['source'] && !is_ipaddroralias($_POST['source']) && $_POST['source'] != "any") {
			$input_errors[] = gettext("A valid source must be specified.");
		}
	}

	if ($_POST['source_subnet'] && !is_numericint($_POST['source_subnet'])) {
		$input_errors[] = gettext("A valid source bit count must be specified.");
	}

	if ($_POST['destination_type'] != "any") {
		if ($_POST['destination'] && !is_ipaddroralias($_POST['destination'])) {
			$input_errors[] = gettext("A valid destination must be specified.");
		}
	}

	if ($_POST['destination_subnet'] && !is_numericint($_POST['destination_subnet'])) {
		$input_errors[] = gettext("A valid destination bit count must be specified.");
	}

	if ($_POST['destination_type'] == "any") {
		if ($_POST['destination_not']) {
			$input_errors[] = gettext("Negating destination address of \"any\" is invalid.");
		}
	}

	if ($_POST['target'] && !is_ipaddr($_POST['target']) && !is_subnet($_POST['target']) && !is_alias($_POST['target']) && !isset($_POST['nonat']) && !($_POST['target'] == "other-subnet")) {
		$input_errors[] = gettext("A valid target IP address must be specified.");
	}

	if ($_POST['target'] == "other-subnet") {
		if (!is_ipaddr($_POST['targetip'])) {
			$input_errors[] = gettext("A valid target IP must be specified when using the 'Other Subnet' type.");
		}

		if (!is_numericint($_POST['targetip_subnet'])) {
			$input_errors[] = gettext("A valid target bit count must be specified when using the 'Other Subnet' type.");
		}
	}

	/* Verify Pool Options */
	$poolopts = "";
	if ($_POST['poolopts']) {
		if (is_subnet($_POST['target']) || ($_POST['target'] == "other-subnet")) {
			$poolopts = $_POST['poolopts'];
		} elseif (is_alias($_POST['target'])) {
			if (substr($_POST['poolopts'], 0, 11) == "round-robin") {
				$poolopts = $_POST['poolopts'];
			} else {
				$input_errors[] = gettext("Only Round Robin pool options may be chosen when selecting an alias.");
			}
		}
	}

	/* if user has selected any as source, set it here */
	if ($_POST['source_type'] == "any") {
		$osn = "any";
	} else if ($_POST['source_type'] == "(self)") {
		$osn = "(self)";
	} else if (is_alias($_POST['source'])) {
		$osn = $_POST['source'];
	} else {
		$osn = gen_subnet($_POST['source'], $_POST['source_subnet']) . "/" . $_POST['source_subnet'];
	}

	/* check for existing entries */
	if ($_POST['destination_type'] == "any") {
		$ext = "any";
	} else if (is_alias($_POST['destination'])) {
		$ext = $_POST['destination'];
	} else {
		$ext = gen_subnet($_POST['destination'], $_POST['destination_subnet']) . "/" . $_POST['destination_subnet'];
	}

	foreach ($a_out as $natent) {
		if (isset($id) && ($a_out[$id]) && ($a_out[$id] === $natent)) {
			continue;
		}

		if (!$natent['interface']) {
			$natent['interface'] = "wan";
		}
	}

	if (!$input_errors) {
		$natent = array();
		$natent['source']['network'] = $osn;
		$natent['sourceport'] = ($protocol_uses_ports) ? $_POST['sourceport'] : "";
		$natent['descr'] = $_POST['descr'];
		$natent['target'] = (!isset($_POST['nonat'])) ? $_POST['target'] : "";
		$natent['targetip'] = (!isset($_POST['nonat'])) ? $_POST['targetip'] : "";
		$natent['targetip_subnet'] = (!isset($_POST['nonat'])) ? $_POST['targetip_subnet'] : "";
		$natent['interface'] = $_POST['interface'];
		$natent['poolopts'] = $poolopts;

		/* static-port */
		if (isset($_POST['staticnatport']) && $protocol_uses_ports && !isset($_POST['nonat'])) {
			$natent['staticnatport'] = true;
		} else {
			unset($natent['staticnatport']);
		}

		if (isset($_POST['disabled'])) {
			$natent['disabled'] = true;
		} else {
			unset($natent['disabled']);
		}

		/* if user has selected not nat, set it here */
		if (isset($_POST['nonat'])) {
			$natent['nonat'] = true;
		} else {
			unset($natent['nonat']);
		}

		if ($_POST['protocol'] && $_POST['protocol'] != "any") {
			$natent['protocol'] = $_POST['protocol'];
		} else {
			unset($natent['protocol']);
		}

		if ($ext == "any") {
			$natent['destination']['any'] = true;
		} else {
			$natent['destination']['address'] = $ext;
		}
		if ($_POST['natport'] != "" && $protocol_uses_ports && !isset($_POST['nonat'])) {
				$natent['natport'] = $_POST['natport'];
		} else {
			unset($natent['natport']);
		}
		if ($_POST['dstport'] != "" && $protocol_uses_ports) {
			$natent['dstport'] = $_POST['dstport'];
		} else {
			unset($natent['dstport']);
		}

		if ($_POST['nosync'] == "yes") {
			$natent['nosync'] = true;
		} else {
			unset($natent['nosync']);
		}

		if (isset($_POST['destination_not']) && $ext != "any") {
			$natent['destination']['not'] = true;
		}

		if (isset($a_out[$id]['created']) && is_array($a_out[$id]['created'])) {
			$natent['created'] = $a_out[$id]['created'];
		}

		$natent['updated'] = make_config_revision_entry();

		// Allow extending of the firewall edit page and include custom input validation
		pfSense_handle_custom_code("/usr/local/pkg/firewall_aon/pre_write_config");

		if (isset($id) && $a_out[$id]) {
			$a_out[$id] = $natent;
		} else {
			$natent['created'] = make_config_revision_entry();
			if (is_numeric($after)) {
				array_splice($a_out, $after+1, 0, array($natent));
			} else {
				$a_out[] = $natent;
			}
		}

		if (write_config()) {
			mark_subsystem_dirty('natconf');
		}
		header("Location: firewall_nat_out.php");
		exit;
	}
}

$pgtitle = array(gettext("Firewall"), gettext("NAT"), gettext("Outbound"), gettext("Edit"));
include("head.inc");

function build_target_list() {
	global $config, $sn, $a_aliases;
	$list = array();

	$list[""] = 'Interface Address';

	if (is_array($config['virtualip']['vip'])) {
		foreach ($config['virtualip']['vip'] as $sn) {
			if (isset($sn['noexpand'])) {
				continue;
			}

			if ($sn['mode'] == "proxyarp" && $sn['type'] == "network") {
				$start = ip2long32(gen_subnet($sn['subnet'], $sn['subnet_bits']));
				$end = ip2long32(gen_subnet_max($sn['subnet'], $sn['subnet_bits']));
				$len = $end - $start;
				$list[$sn['subnet'] . '/' . $sn['subnet_bits']] = 'Subnet: ' . $sn['subnet'] . '/' . $sn['subnet_bits'] . ' (' . $sn['descr'] . ')';

				for ($i = 0; $i <= $len; $i++) {
					$snip = long2ip32($start+$i);

					$list[$snip] = $snip . ' (' . $sn['descr'] . ')';
				}
			} else {
				$list[$sn['subnet']] = $sn['subnet'] . ' (' . $sn['descr'] . ')';
			}
		}
	}

	foreach ($a_aliases as $alias) {
		if ($alias['type'] != "host") {
			continue;
		}

		$list[$alias['name']] = 'Host Alias: ' . $alias['name'] . ' (' . $alias['descr'] . ')';
	}

	$list['other-subnet'] = 'Other Subnet (Enter Below)';

	return($list);
}

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form(new Form_Button(
	'Submit',
	gettext("Save")
));

$section = new Form_Section('Edit Advanced Outbound NAT entry');

$section->addInput(new Form_Checkbox(
	'disabled',
	'Disabled',
	'Disable this rule',
	$pconfig['disabled']
));

$section->addInput(new Form_Checkbox(
	'nonat',
	'Do not NAT',
	'Enabling this option will disable NAT for traffic matching this rule and stop processing Outbound NAT rules',
	$pconfig['nonat']
))->setHelp('In most cases this option is not required');

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
if ($config['openvpn']["openvpn-server"] || $config['openvpn']["openvpn-client"]) {
	$interfaces["openvpn"] = "OpenVPN";
}

$section->addInput(new Form_Select(
	'interface',
	'Interface',
	$pconfig['interface'],
	$interfaces
))->setHelp('Choose which interface this rule applies to. In most cases "WAN" is specified.');

$protocols = "any TCP UDP TCP/UDP ICMP ESP AH GRE IPV6 IGMP carp pfsync";

$section->addInput(new Form_Select(
	'protocol',
	'Protocol',
	$pconfig['protocol'],
	array_combine(explode(" ", strtolower($protocols)), explode(" ", $protocols))
))->setHelp('Choose which protocol this rule should match. In most cases "any" is specified.');

$group = new Form_Group('Source');

$group->add(new Form_Select(
	'source_type',
	null,
	(($pconfig['source'] == "any") || ($pconfig['source'] == "(self)")) ? $pconfig['source'] : "network",
	array('any' => 'Any', '(self)' => 'This Firewall (self)', 'network' => 'Network')
))->setHelp('Type')->setWidth('3');

$group->add(new Form_IpAddress(
	'source',
	null,
	$pconfig['source']
))->addMask('source_subnet', $pconfig['source_subnet'])->setHelp('Source network for the outbound NAT mapping.')->setPattern('[a-zA-Z0-9\_\.\:]+');

$group->add(new Form_Input(
	'sourceport',
	null,
	'text',
	$pconfig['sourceport']
))->setHelp('Port')->setWidth('2');

$section->add($group);

$group = new Form_Group('Destination');

$group->add(new Form_Select(
	'destination_type',
	null,
	$pconfig['destination'] == "any" ? "any":"network",
	array('any' => 'Any', 'network' => 'Network')
))->setHelp('Type')->setWidth('3');

$group->add(new Form_IpAddress(
	'destination',
	null,
	$pconfig['destination'] == "any" ? "":$pconfig['destination']
))->addMask('destination_subnet', $pconfig['destination_subnet'])->setHelp('Destination network for the outbound NAT mapping.')->setPattern('[a-zA-Z0-9\_\.\:]+');

$group->add(new Form_Input(
	'dstport',
	null,
	'text',
	$pconfig['dstport']
))->setHelp('Port')->setWidth('2');

$section->add($group);

$section->addInput(new Form_Checkbox(
	'destination_not',
	null,
	'Not',
	$pconfig['destination_not']
))->setHelp('Invert the sense of the destination match');

$form->add($section);

$section = new Form_Section('Translation');
$section->addClass('translation');

$section->addInput(new Form_Select(
	'target',
	'Address',
	$pconfig['target'],
	build_target_list()
));

$section->addInput(new Form_IpAddress(
	'targetip',
	'Other subnet',
	$pconfig['targetip']
))->addMask('targetip_subnet', $pconfig['targetip_subnet'])->addClass('othersubnet')->setHelp(
		'Packets matching this rule will be mapped to the IP address given here.' . '<br />' .
		'If you want this rule to apply to another IP address rather than the IP address of the interface chosen above, ' .
		'select it here (you will need to define ' .
		'<a href="firewall_virtual_ip.php">' . gettext("Virtual IP") . '</a> ' .
		'addresses on the interface first)');

$section->addInput(new Form_Select(
	'poolopts',
	'Pool options',
	$pconfig['poolopts'],
	array(
		'' => 'Default',
		'round-robin' => 'Round Robin',
		'round-robin sticky-address' => 'Round Robin with Sticky Address',
		'random' => 'Random',
		'random sticky-address' => 'Random with Sticky Address',
		'source-hash' => 'Source hash',
		'bitmask' => 'Bit mask'
	)
))->setHelp('Only Round Robin types work with Host Aliases. Any type can be used with a Subnet.' . '<br />' .
			'</span><ul class="help-block">' .
				'<li>' . 'Round Robin: Loops through the translation addresses.' . '</li>' .
				'<li>' . 'Random: Selects an address from the translation address pool at random.' . '</li>' .
				'<li>' . 'Source Hash: Uses a hash of the source address to determine the translation address, ensuring that the redirection address is always the same for a given source.' . '</li>' .
				'<li>' . 'Bitmask: Applies the subnet mask and keeps the last portion identical; 10.0.1.50 -&gt; x.x.x.50.' . '</li>' .
				'<li>' . 'Sticky Address: The Sticky Address option can be used with the Random and Round Robin pool types to ensure that a particular source address is always mapped to the same translation address.' . '</li>' .
			'</ul><span class="help-block">');

$group = new Form_Group('Port');
$group->addClass('natportgrp');

$group->add(new Form_Input(
	'natport',
	null,
	'number',
	$pconfig['natport'],
	['min' => '1', 'max' => '65536']
))->setHelp('Enter the source port for the outbound NAT mapping.');

$group->add(new Form_Checkbox(
	'staticnatport',
	null,
	'Static port',
	$pconfig['staticnatport']
));

$section->add($group);
$form->add($section);

$section = new Form_Section('Misc');

$section->addInput(new Form_Checkbox(
	'nosync',
	'No XMLRPC Sync',
	null,
	$pconfig['nosync'],
	'yes'
))->setHelp('Prevents the rule on Master from automatically syncing to other CARP members. ' .
			'This does NOT prevent the rule from being overwritten on Slave.');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('You may enter a description here for your reference (not parsed).');

if (isset($id) && $a_out[$id]) {
	$section->addInput(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$section->addInput(new Form_Input(
	'after',
	null,
	'hidden',
	$after
));

$form->add($section);

$has_created_time = (isset($a_out[$id]['created']) && is_array($a_out[$id]['created']));
$has_updated_time = (isset($a_out[$id]['updated']) && is_array($a_out[$id]['updated']));

if ($has_created_time || $has_updated_time) {
	$section = new Form_Section('Rule Information');

	if ($has_created_time) {
		$section->addInput(new Form_StaticText(
			'Created',
			date(gettext("n/j/y H:i:s"), $a_out[$id]['created']['time']) . gettext(" by ") . $a_out[$id]['created']['username']
		));
	}

	if ($has_updated_time) {
		$section->addInput(new Form_StaticText(
			'Updated',
			date(gettext("n/j/y H:i:s"), $a_out[$id]['updated']['time']) . gettext(" by ") . $a_out[$id]['updated']['username']
		));
	}

	$form->add($section);
}

print($form);

?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	var portsenabled = 1;

	function staticportchange() {
		if ($('#staticnatport').prop('checked'))	 {
			$('#natport').val("");
			disableInput('natport' , true);
		} else {
			disableInput('natport' , false);
		}
	}

	function sourcesel_change() {
		if ($('#source_type').find(":selected").val() == "network") {
			disableInput('source', false);
			disableInput('source_subnet', false);
		} else {
			$('#source').val("");
			disableInput('source', true);
			$('#source_subnet').val("24");
			disableInput('source_subnet', true);
		}
	}

	function typesel_change() {
		if ($('#destination_type').find(":selected").val() == "network") {
			disableInput('destination', false);
			disableInput('destination_subnet', false);
		} else {
			$('#destination').val("");
			disableInput('destination', true);
			$('#destination_subnet').val("24");
			disableInput('destination_subnet', true);
		}
	}

	function nonat_change() {
		hideClass('translation', $('#nonat').prop('checked'));
	}

	function proto_change() {
		if (($('#protocol').find(":selected").index() >= 0) && ($('#protocol').find(":selected").index() <= 3)) {
			hideGroupInput('sourceport', false);
			hideGroupInput('dstport', false);
			hideClass('natportgrp', false);
		} else {
			hideGroupInput('sourceport', true);
			hideGroupInput('dstport', true);
			hideClass('natportgrp', true);
		}
	}

	function poolopts_change() {
		if ($('#target option:selected').text().trim().substring(0,4) == "Host") {
			hideInput('poolopts', false);
			hideGroupClass('othersubnet', true);
		} else if ($('#target option:selected').text().trim().substring(0,6) == "Subnet") {
			hideInput('poolopts', false);
			hideGroupClass('othersubnet', true);
		} else if ($('#target option:selected').text().trim().substring(0,5) == "Other") {
			hideInput('poolopts', false);
			hideGroupClass('othersubnet', false);
		} else {
			$('#poolopts').prop('selectedIndex',0);
			hideInput('poolopts', true);
			hideGroupClass('othersubnet', true);
			$('#targetip').val('');
			$('#targetip_subnet').val('0');
		}
	}

	// When controls are clicked . .
	$('#staticnatport').click(function () {
		staticportchange();
	});

	$('#source_type').on('change', function() {
		sourcesel_change();
	});

	$('#destination_type').on('change', function() {
		typesel_change();
	});

	$('#nonat').on('change', function() {
		nonat_change();
	});

	$('#protocol').on('change', function() {
		proto_change();
	});

	$('#target').on('change', function() {
		poolopts_change();
	});

	// Set initial states
	staticportchange();
	sourcesel_change();
	typesel_change();
	nonat_change();
	proto_change();
	poolopts_change();

    // --------- Autocomplete -----------------------------------------------------------------------------------------
    var addressarray = <?= json_encode(get_alias_list(array("host", "network", "openvpn", "urltable"))) ?>;
    var customarray = <?= json_encode(get_alias_list(array("port", "url_ports", "urltable_ports"))) ?>;

    $('#destination, #source').autocomplete({
        source: addressarray
    });

    $('#dstbeginport_cust, #sourceport, #destination, #dstport').autocomplete({
        source: customarray
    });
});
//]]>
</script>

<?php include("foot.inc");
