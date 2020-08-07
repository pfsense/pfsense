<?php
/*
 * firewall_virtual_ip_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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

init_config_arr(array('virtualip', 'vip'));
$a_vip = &$config['virtualip']['vip'];

if (isset($_REQUEST['id']) && is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

function return_first_two_octets($ip) {
	$ip_split = explode(".", $ip);
	return $ip_split[0] . "." . $ip_split[1];
}

function find_last_used_vhid() {
	global $config, $g;

	$vhid = 0;
	foreach ($config['virtualip']['vip'] as $vip) {
		if ($vip['vhid'] > $vhid) {
			$vhid = $vip['vhid'];
		}
	}

	return $vhid;
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
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "mode");
	$reqdfieldsn = array(gettext("Type"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($_POST['subnet']) {
		$_POST['subnet'] = trim($_POST['subnet']);
	}

	if (is_pseudo_interface(convert_friendly_interface_to_real_interface_name($_POST['interface']))) {
		if ($_POST['mode'] == 'ipalias') {
			$input_errors[] = gettext("The interface chosen for the VIP does not support IP Alias mode.");
		} elseif ($_POST['mode'] == 'proxyarp') {
			$input_errors[] = gettext("The interface chosen for the VIP does not support Proxy ARP mode.");
		}
	}

	if ($_POST['subnet']) {
		if (!is_ipaddr($_POST['subnet'])) {
			$input_errors[] = gettext("A valid IP address must be specified.");
		} else {
			if (isset($id) && isset($a_vip[$id])) {
				$ignore_if = $a_vip[$id]['interface'];
				$ignore_mode = $a_vip[$id]['mode'];
				if (isset($a_vip[$id]['uniqid']))
					$ignore_uniqid = $a_vip[$id]['uniqid'];
			} else {
				$ignore_if = $_POST['interface'];
				$ignore_mode = $_POST['mode'];
			}

			if (!isset($ignore_uniqid))
				$ignore_uniqid = $_POST['uniqid'];

			if ($ignore_mode == 'carp' || $ignore_mode == 'ipalias')
				$ignore_if = "_vip{$ignore_uniqid}";

			if (is_ipaddr_configured($_POST['subnet'], $ignore_if)) {
				$input_errors[] = gettext("This IP address is being used by another interface or VIP.");
			}

			unset($ignore_if, $ignore_mode);
		}
	}

	$natiflist = get_configured_interface_with_descr();
	foreach ($natiflist as $natif => $natdescr) {
		if ($_POST['interface'] == $natif && (empty($config['interfaces'][$natif]['ipaddr']) && empty($config['interfaces'][$natif]['ipaddrv6']))) {
			$input_errors[] = gettext("The interface chosen for the VIP has no IPv4 or IPv6 address configured so it cannot be used as a parent for the VIP.");
		}
	}

	/* ipalias and carp should not use network or broadcast address */
	if ($_POST['mode'] == "ipalias" || $_POST['mode'] == "carp") {
		if (is_ipaddrv4($_POST['subnet']) && $_POST['subnet_bits'] != "32" && $_POST['subnet_bits'] != "31") {
			$network_addr = gen_subnet($_POST['subnet'], $_POST['subnet_bits']);
			$broadcast_addr = gen_subnet_max($_POST['subnet'], $_POST['subnet_bits']);
		} else if (is_ipaddrv6($_POST['subnet']) && $_POST['subnet_bits'] != "128") {
			$network_addr = gen_subnetv6($_POST['subnet'], $_POST['subnet_bits']);
			$broadcast_addr = gen_subnetv6_max($_POST['subnet'], $_POST['subnet_bits']);
		}

		if (isset($network_addr) && $_POST['subnet'] == $network_addr) {
			$input_errors[] = gettext("The network address cannot be used for this VIP");
		} else if (isset($broadcast_addr) && $_POST['subnet'] == $broadcast_addr) {
			$input_errors[] = gettext("The broadcast address cannot be used for this VIP");
		}
	}

	/* make sure new ip is within the subnet of a valid ip
	 * on one of our interfaces (wan, lan optX)
	 */
	switch ($_POST['mode']) {
		case 'carp':
			/* verify against reusage of vhids */
			$idtracker = 0;
			foreach ($config['virtualip']['vip'] as $vip) {
				if ($vip['vhid'] == $_POST['vhid'] && $vip['interface'] == $_POST['interface'] && $idtracker != $id) {
					$input_errors[] = sprintf(gettext("VHID %1$s is already in use on interface %2$s. Pick a unique number on this interface."), $_POST['vhid'], convert_friendly_interface_to_friendly_descr($_POST['interface']));
				}
				$idtracker++;
			}

			if (empty($_POST['password'])) {
				$input_errors[] = gettext("A CARP password that is shared between the two VHID members must be specified.");
			}

			if ($_POST['password'] != $_POST['password_confirm']) {
				$input_errors[] = gettext("Password and confirm password must match");
			}

			if ($_POST['interface'] == 'lo0') {
				$input_errors[] = gettext("For this type of vip localhost is not allowed.");
			} else if (strstr($_POST['interface'], '_vip')) {
				$input_errors[] = gettext("A CARP parent interface can only be used with IP Alias type Virtual IPs.");
			}

			break;
		case 'ipalias':
			/* verify IP alias on CARP has proper address family */
			if (strstr($_POST['interface'], '_vip')) {
				$vipif = get_configured_vip($_POST['interface']);
				if (is_ipaddrv4($_POST['subnet']) && is_ipaddrv6($vipif['subnet'])) {
					$input_errors[] = gettext("An IPv4 Virtual IP cannot have an IPv6 CARP parent.");
				}
				if (is_ipaddrv6($_POST['subnet']) && is_ipaddrv4($vipif['subnet'])) {
					$input_errors[] = gettext("An IPv6 Virtual IP cannot have an IPv4 CARP parent.");
				}
			}
			break;
		default:
			if ($_POST['interface'] == 'lo0') {
				$input_errors[] = gettext("For this type of vip localhost is not allowed.");
			} else if (strstr($_POST['interface'], '_vip')) {
				$input_errors[] = gettext("A CARP parent interface can only be used with IP Alias type Virtual IPs.");
			}

			break;
	}

	if (!$input_errors) {
		$vipent = array();

		$vipent['mode'] = $_POST['mode'];
		$vipent['interface'] = $_POST['interface'];

		/* ProxyARP & Other specific fields */
		if (($_POST['mode'] === "proxyarp") || ($_POST['mode'] === "other")) {
			$vipent['noexpand'] = isset($_POST['noexpand']);
		}

		/* CARP specific fields */
		if ($_POST['mode'] === "carp") {
			$vipent['vhid'] = $_POST['vhid'];
			$vipent['advskew'] = $_POST['advskew'];
			$vipent['advbase'] = $_POST['advbase'];

			if ($_POST['password'] != DMYPWD) {
				$vipent['password'] = $_POST['password'];
			} else {
				$vipent['password'] = $a_vip[$id]['password'];
			}
		}

		/* IPalias and CARP should have a uniqid */
		if ($_POST['mode'] === "carp" || $_POST['mode'] === "ipalias") {
			if (empty($_POST['uniqid'])) {
				// if we changed a 'parp' or 'other' alias to 'carp'/'ipalias' it needs a uniqid
				$vipent['uniqid'] = uniqid();
			} else {
				$vipent['uniqid'] = $_POST['uniqid'];
			}
		}

		/* Common fields */
		$vipent['descr'] = $_POST['descr'];
		if (isset($_POST['type'])) {
			$vipent['type'] = $_POST['type'];
		} else {
			$vipent['type'] = "single";
		}

		if ($vipent['type'] == "single" || $vipent['type'] == "network") {
			if (!isset($_POST['subnet_bits'])) {
				$vipent['subnet_bits'] = "32";
			} else {
				$vipent['subnet_bits'] = $_POST['subnet_bits'];
			}

			$vipent['subnet'] = $_POST['subnet'];
		}

		if (!isset($id)) {
			$id = count($a_vip);
		}
		if (file_exists("{$g['tmp_path']}/.firewall_virtual_ip.apply")) {
			$toapplylist = unserialize(file_get_contents("{$g['tmp_path']}/.firewall_virtual_ip.apply"));
		} else {
			$toapplylist = array();
		}

		$toapplylist[$id] = $a_vip[$id];

		if (!empty($a_vip[$id])) {
			/* modify all virtual IP rules with this address */
			for ($i = 0; isset($config['nat']['rule'][$i]); $i++) {
				if ($config['nat']['rule'][$i]['destination']['address'] == $a_vip[$id]['subnet']) {
					$config['nat']['rule'][$i]['destination']['address'] = $vipent['subnet'];
				}
			}
		}

		$a_vip[$id] = $vipent;

		if (write_config(gettext("Saved/edited a virtual IP."))) {
			mark_subsystem_dirty('vip');
			file_put_contents("{$g['tmp_path']}/.firewall_virtual_ip.apply", serialize($toapplylist));
		}

		header("Location: firewall_virtual_ip.php");
		exit;
	}
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
	<?php print_info_box(gettext("Proxy ARP and Other type Virtual IP addresses cannot be used for binding by services on the firewall (IPsec, OpenVPN, etc.). Use an IP Alias or CARP type VIP for these roles.") . '<br />' .
			   sprintf(gettext("For more information, visit the pfSense book section on %s"), '<a href="https://docs.netgate.com/pfsense/en/latest/book/firewall/virtual-ip-addresses.html">Virtual IP Addresses</a>.'), 'info', false); ?>
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
