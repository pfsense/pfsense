<?php
/*
 * firewall_nat_out_edit.php
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
##|*IDENT=page-firewall-nat-outbound-edit
##|*NAME=Firewall: NAT: Outbound: Edit
##|*DESCR=Allow access to the 'Firewall: NAT: Outbound: Edit' page.
##|*MATCH=firewall_nat_out_edit.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("ipsec.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("firewall_nat_out.inc");

init_config_arr(array('nat', 'outbound', 'rule'));
$a_out = &$config['nat']['outbound']['rule'];

if (isset($_REQUEST['id']) && is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

if (isset($_REQUEST['after']) && (is_numericint($_REQUEST['after']) || $_REQUEST['after'] == "-1")) {
	$after = $_REQUEST['after'];
}

if ($_POST['save']) {
	$rv = saveoutNATrule($_POST, $id);

	$input_errors = $rv['input_errors'];
	$pconfig = $rv['pconfig'];

	if (!$input_errors) {
		header("Location: firewall_nat_out.php");
		exit;
	}
} else {
	if (isset($_REQUEST['dup']) && is_numericint($_REQUEST['dup'])) {
		$id = $_REQUEST['dup'];
		$after = $_REQUEST['dup'];
	}

	$pconfig = getoutNATrule($id);
}

if (isset($_REQUEST['dup']) && is_numericint($_REQUEST['dup'])) {
	unset($id);
}

$pgtitle = array(gettext("Firewall"), gettext("NAT"), gettext("Outbound"), gettext("Edit"));
$pglinks = array("", "firewall_nat.php", "firewall_nat_out.php", "@self");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('Edit Advanced Outbound NAT Entry');

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
	isset($pconfig['nonat'])
))->setHelp('In most cases this option is not required.');

$section->addInput(new Form_Select(
	'interface',
	'*Interface',
	$pconfig['interface'],
	create_interface_list()
))->setHelp('The interface on which traffic is matched as it exits the firewall. In most cases this is "WAN" or another externally-connected interface.');

$section->addInput(new Form_Select(
	'ipprotocol',
	'*Address Family',
	$pconfig['ipprotocol'],
	array(
		'inet' => 'IPv4',
		'inet6' => 'IPv6',
		'' => 'IPv4+IPv6',
	)
))->setHelp('Select the Internet Protocol version this rule applies to.');

$protocols = "any TCP UDP TCP/UDP ICMP ESP AH GRE IPV6 IGMP carp pfsync";

$section->addInput(new Form_Select(
	'protocol',
	'*Protocol',
	$pconfig['protocol'],
	array_combine(explode(" ", strtolower($protocols)), explode(" ", $protocols))
))->setHelp('Choose which protocol this rule should match. In most cases "any" is specified.');

$group = new Form_Group('*Source');

$group->add(new Form_Select(
	'source_type',
	null,
	(($pconfig['source'] == "any") || ($pconfig['source'] == "(self)")) ? $pconfig['source'] : "network",
	array('any' => gettext('Any'), '(self)' => gettext('This Firewall (self)'), 'network' => gettext('Network'))
))->setHelp('Type')->setWidth('3');

$group->add(new Form_IpAddress(
	'source',
	null,
	$pconfig['source'],
	'ALIASV4V6'
))->addMask('source_subnet', $pconfig['source_subnet'])->setHelp('Source network for the outbound NAT mapping.');

$group->add(new Form_Input(
	'sourceport',
	null,
	'text',
	$pconfig['sourceport']
))->setHelp('Port or Range')->setWidth('2');

$section->add($group);

$group = new Form_Group('*Destination');

$group->add(new Form_Select(
	'destination_type',
	null,
	$pconfig['destination'] == "any" ? "any":"network",
	array('any' => gettext('Any'), 'network' => gettext('Network'))
))->setHelp('Type')->setWidth('3');

$group->add(new Form_IpAddress(
	'destination',
	null,
	$pconfig['destination'] == "any" ? "":$pconfig['destination'],
	'ALIASV4V6'
))->addMask('destination_subnet', $pconfig['destination_subnet'])->setHelp('Destination network for the outbound NAT mapping.');

$group->add(new Form_Input(
	'dstport',
	null,
	'text',
	$pconfig['dstport']
))->setHelp('Port or Range')->setWidth('2');

$section->add($group);

$section->addInput(new Form_Checkbox(
	'destination_not',
	null,
	'Not',
	$pconfig['destination_not']
))->setHelp('Invert the sense of the destination match.');

$form->add($section);

$section = new Form_Section('Translation');
$section->addClass('translation');

$section->addInput(new Form_Select(
	'target',
	'*Address',
	$pconfig['target'],
	build_target_list()
))->setHelp('Connections matching this rule will be mapped to the specified %1$sAddress%2$s.%3$s' .
		'The %1$sAddress%2$s can be an Interface, a Host-type Alias, or a %4$sVirtual IP%5$s address.',
		'<b>', '</b>', '<br />', '<a href="firewall_virtual_ip.php">', '</a>');

$section->addInput(new Form_IpAddress(
	'targetip',
	'Other subnet',
	$pconfig['targetip']
))->addMask('targetip_subnet', $pconfig['targetip_subnet'])->setHelp(
		'This subnet must be routed to the firewall or each address in the subnet must be defined in one or more %1$sVirtual IP%2$s addresses.',
		'<a href="firewall_virtual_ip.php">', '</a>');

$section->addInput(new Form_Select(
	'poolopts',
	'Pool options',
	$pconfig['poolopts'],
	array(
		'' => gettext('Default'),
		'round-robin' => gettext('Round Robin'),
		'round-robin sticky-address' => gettext('Round Robin with Sticky Address'),
		'random' => gettext('Random'),
		'random sticky-address' => gettext('Random with Sticky Address'),
		'source-hash' => gettext('Source hash'),
		'bitmask' => gettext('Bit mask')
	)
))->setHelp('%s',
			gettext('Only Round Robin types work with Host Aliases. Any type can be used with a Subnet.') .
			'<br /></span><ul class="help-block">' .
				'<li>' . gettext('Round Robin: Loops through the translation addresses.') . '</li>' .
				'<li>' . gettext('Random: Selects an address from the translation address pool at random.') . '</li>' .
				'<li>' . gettext('Source Hash: Uses a hash of the source address to determine the translation address, ensuring that the redirection address is always the same for a given source.') . '</li>' .
				'<li>' . gettext('Bitmask: Applies the subnet mask and keeps the last portion identical; 10.0.1.50 -&gt; x.x.x.50.') . '</li>' .
				'<li>' . gettext('Sticky Address: The Sticky Address option can be used with the Random and Round Robin pool types to ensure that a particular source address is always mapped to the same translation address.') . '</li>' .
			'</ul><span class="help-block">');

$section->addInput(new Form_Input(
	'source_hash_key',
	'Source Hash Key',
	'text',
	$pconfig['source_hash_key']
))->setHelp('The key that is fed to the hashing algorithm in hex format, preceeded by "0x", or any string. A non-hex string is hashed using md5 to a hexadecimal key. Defaults to a randomly generated value.')->setWidth(10);

$group = new Form_Group('Port or Range');
$group->addClass('natportgrp');

$group->add(new Form_Input(
	'natport',
	null,
	'text',
	$pconfig['natport']
))->setHelp('Enter the external source %1$sPort or Range%2$s used for remapping '.
		'the original source port on connections matching the rule. %3$s'.
		'Port ranges are a low port and high port number separated by ":".%4$s'.
		'Leave blank when %1$sStatic Port%2$s is checked.', '<b>', '</b>', '<br/><br/>', '<br/>');

$group->add(new Form_Checkbox(
	'staticnatport',
	null,
	'Static Port',
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
))->setHelp('A description may be entered here for administrative reference (not parsed).');

if (isset($id) && $a_out[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->addGlobal(new Form_Input(
	'after',
	null,
	'hidden',
	$after
));

$form->add($section);

gen_created_updated_fields($form, $a_out[$id]['created'], $a_out[$id]['updated']);

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
			$('#destination, #source').autocomplete({
				source: addressarray
			});
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
		if ($('#target option:selected').val().substring(0,1) == "H") {
			hideInput('poolopts', false);
			hideInput('source_hash_key', true);
			hideIpAddress('targetip', true);
		} else if ($('#target option:selected').val().substring(0,1) == "S") {
			hideInput('poolopts', false);
			hideInput('source_hash_key', true);
			hideIpAddress('targetip', true);
		} else if ($('#target option:selected').val().substring(0,1) == "O") {
			hideInput('poolopts', false);
			hideIpAddress('targetip', false);
			if ($('#poolopts option:selected').val() == "source-hash") {
				hideInput('source_hash_key', false);
			} else {
				hideInput('source_hash_key', true);
			}
		} else {
			$('#poolopts').prop('selectedIndex',0);
			hideInput('poolopts', true);
			hideInput('source_hash_key', true);
			hideIpAddress('targetip', true);
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

	$('#poolopts').on('change', function() {
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
    var addressarray = <?= json_encode(get_alias_list(array("host", "network", "urltable"))) ?>;
    var customarray = <?= json_encode(get_alias_list(array("port", "url_ports", "urltable_ports"))) ?>;

    $('#destination, #source').autocomplete({
        source: addressarray
    });

    $('#sourceport, #dstport').autocomplete({
        source: customarray
    });
});
//]]>
</script>

<?php include("foot.inc");
