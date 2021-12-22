<?php
/*
 * firewall_virtual_ip_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Bill Marquette <bill.marquette@gmail.com>
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
##|*IDENT=page-firewall-virtualipaddress-edit
##|*NAME=Firewall: Virtual IP Address: Edit
##|*DESCR=Allow access to the 'Firewall: Virtual IP Address: Edit' page.
##|*MATCH=firewall_virtual_ip_edit.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("firewall_virtual_ip.inc");

init_config_arr(array('virtualip', 'vip'));
$a_vip = &$config['virtualip']['vip'];

if (isset($_REQUEST['id']) && is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

function return_first_two_octets($ip) {
	$ip_split = explode(".", $ip);
	return $ip_split[0] . "." . $ip_split[1];
}

if (isset($id) && $a_vip[$id]) {
	$pconfig['mode'] = $a_vip[$id]['mode'];
	$pconfig['vhid'] = $a_vip[$id]['vhid'];
	$pconfig['advskew'] = $a_vip[$id]['advskew'];
	$pconfig['advbase'] = $a_vip[$id]['advbase'];
	$pconfig['password'] = $a_vip[$id]['password'];
	$pconfig['subnet'] = $a_vip[$id]['subnet'];
	$pconfig['subnet_bits'] = $a_vip[$id]['subnet_bits'];
	$pconfig['noexpand'] = $a_vip[$id]['noexpand'];
	$pconfig['descr'] = $a_vip[$id]['descr'];
	$pconfig['type'] = $a_vip[$id]['type'];
	$pconfig['interface'] = $a_vip[$id]['interface'];
	$pconfig['uniqid'] = $a_vip[$id]['uniqid'];
} else {
	$lastvhid = find_last_used_vhid();
	$lastvhid++;
	$pconfig['vhid'] = $lastvhid;
	$pconfig['uniqid'] = uniqid();
}

if ($_POST['save']) {
	$_POST['id'] = $id;
	$rv = saveVIP($_POST);
	$input_errors = $rv['input_errors'];
	$a_vip = $rv['a_vip'];
}

$ipaliashelp = gettext('The mask must be the network\'s subnet mask. It does not specify a CIDR range.');
$proxyarphelp = gettext('Enter a CIDR block of proxy ARP addresses.');

$pgtitle = array(gettext("Firewall"), gettext("Virtual IPs"), gettext("Edit"));
$pglinks = array("", "firewall_virtual_ip.php", "@self");
include("head.inc");

function build_if_list() {
	$list = array();

	$interfaces = get_configured_interface_with_descr(true);
	$carplist = get_configured_vip_list('all', VIP_CARP);

	foreach ($carplist as $vipname => $address) {
		$interfaces[$vipname] = $address;
		$interfaces[$vipname] .= " (";
		if (get_vip_descr($address)) {
			$interfaces[$vipname] .= get_vip_descr($address);
		} else {
			$vip = get_configured_vip($vipname);
			$interfaces[$vipname] .= "vhid: {$vip['vhid']}";
		}
		$interfaces[$vipname] .= ")";
	}

	$interfaces['lo0'] = 'Localhost';

	return($interfaces);
}

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('Edit Virtual IP');

$group = new Form_Group('*Type');

$group->add(new Form_Checkbox(
	'mode',
	null,
	'IP Alias',
	($pconfig['mode'] == "ipalias"),
	'ipalias'
))->displayAsRadio();

$group->add(new Form_Checkbox(
	'mode',
	null,
	'CARP',
	($pconfig['mode'] == "carp"),
	'carp'
))->displayAsRadio();

$group->add(new Form_Checkbox(
	'mode',
	null,
	'Proxy ARP',
	($pconfig['mode'] == "proxyarp"),
	'proxyarp'
))->displayAsRadio();

$group->add(new Form_Checkbox(
	'mode',
	null,
	'Other',
	($pconfig['mode'] == "other"),
	'other'
))->displayAsRadio();

$section->add($group);

$section->addInput(new Form_Select(
	'interface',
	'*Interface',
	$pconfig['interface'],
	build_if_list()
));

$section->addInput(new Form_Select(
	'type',
	'Address type',
	(!isset($pconfig['subnet'])) ? 'single':'network',
	array(
		'single' => gettext('Single address'),
		'network' => gettext('Network')
	)
))->addClass('typesel');

$section->addInput(new Form_IpAddress(
	'subnet',
	'*Address(es)',
	$pconfig['subnet']
))->addMask('subnet_bits', $pconfig['subnet_bits'])->setHelp('%s', '<span id="address_note"></span>');

$section->addInput(new Form_Checkbox(
	'noexpand',
	'Expansion',
	'Disable expansion of this entry into IPs on NAT lists (e.g. 192.168.1.0/24 expands to 256 entries.) ',
	isset($pconfig['noexpand'])
));

$section->addPassword(new Form_Input(
	'password',
	'Virtual IP Password',
	'password',
	$pconfig['password']
))->setHelp('Enter the VHID group password.');

$section->addInput(new Form_Select(
	'vhid',
	'VHID Group',
	$pconfig['vhid'],
	array_combine(range(1, 255, 1), range(1, 255, 1))
))->setHelp('Enter the VHID group that the machines will share.');

$group = new Form_Group('Advertising frequency');
$group->add(new Form_Select(
	'advbase',
	'Base',
	$pconfig['advbase'],
	array_combine(range(1, 254, 1), range(1, 254, 1))
))->setHelp('Base');

$group->add(new Form_Select(
	'advskew',
	'Skew',
	$pconfig['advskew'],
	array_combine(range(0, 254, 1), range(0, 254, 1))
))->setHelp('Skew');

$group->setHelp('The frequency that this machine will advertise. 0 means usually master. Otherwise the lowest combination of both values in the cluster determines the master.');

$section->add($group);

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

if (isset($id) && $a_vip[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->addGlobal(new Form_Input(
	'uniqid',
	null,
	'hidden',
	$pconfig['uniqid']
));

$form->add($section);

print($form);
?>

<div class="infoblock">
	<?php
	print_info_box(sprintf(gettext('Proxy ARP and Other type Virtual IP ' .
	    'addresses cannot be used for binding by services on the ' .
	    'firewall (IPsec, OpenVPN, etc.). Use an IP Alias or CARP type ' .
	    'VIP for these roles.%1$s' .
	    'For more information, visit the %2$s book section on %3$s'),
		'<br />', $g['product_label'],
		'<a href="https://docs.netgate.com/pfsense/en/latest/firewall/virtual-ip-addresses.html">Virtual IP Addresses</a>.'),
	    'info', false);
	?>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Hides the <div> in which the specified checkbox lives so that the checkbox, its label and help text are hidden
	function hideCheckbox(id, hide) {
		if (hide) {
			$('#' + id).parent().parent().parent('div').addClass('hidden');
		} else {
			$('#' + id).parent().parent().parent('div').removeClass('hidden');
		}
	}

	// Disables the specified input element
	function disableInput(id, disable) {
		$('#' + id).prop("disabled", disable);
	}

	// Hide/show input elements depending on the 'mode' radio button setting
	function check_mode() {
		var mode = $("input[name=mode]:checked").val();

		disableInput('vhid', true);
		disableInput('advbase', true);
		disableInput('advskew', true);
		disableInput('subnet_bits', true);
		disableInput('type', true);
		disableInput('password', true);
		disableInput('password_confirm', true);
		hideCheckbox('noexpand', true);
		setRequired('password', false);
		setRequired('vhid', false);
		setRequired('advbase', false);

		// Make sure the type is selected before allowing address to be selected.
		if(mode == undefined){
			disableInput('subnet', true);
		}else{
			disableInput('subnet', false);
		}

		if (mode == 'ipalias') {
			$('#address_note').html("<?=$ipaliashelp?>");
			$('#type').val('single');
			setRequired('type', false);
			disableInput('subnet_bits', false);

		} else if (mode == 'carp') {
			$('#address_note').html("<?=$ipaliashelp?>");
			disableInput('vhid', false);
			disableInput('advbase', false);
			disableInput('advskew', false);
			disableInput('password', false);
			disableInput('password_confirm', false);
			disableInput('subnet_bits', false);
			$('#type').val('single');
			setRequired('type', false);
			setRequired('password', true);
			setRequired('vhid', true);
			setRequired('advbase', true);
		} else if (mode == 'proxyarp') {
			$('#address_note').html("<?=$proxyarphelp?>");
			disableInput('type', false);
			setRequired('type', true);
			disableInput('subnet_bits', ($('#type').val() == 'single'));
			hideCheckbox('noexpand', false);
		} else {
			$('#address_note').html('');
			disableInput('type', false);
			setRequired('type', true);
			disableInput('subnet_bits', ($('#type').val() == 'single'));
			hideCheckbox('noexpand', false);
		}
	}

	// ---------- Click checkbox handlers ---------------------------------------------------------

	// When radio buttons are clicked . .
	$('input:radio[name=mode]').click(function() {
		check_mode();
	});

	// On clicking the address type selector
	$('#type').on('change', function() {
		check_mode();
		hideCheckbox('noexpand', (this.value == 'single'));
	});

	// ---------- On initial page load ------------------------------------------------------------

	check_mode();
});
//]]>
</script>

<?php

include("foot.inc");
