<?php
/*
	firewall_virtual_ip_edit.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2005 Bill Marquette <bill.marquette@gmail.com>
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
##|*IDENT=page-firewall-virtualipaddress-edit
##|*NAME=Firewall: Virtual IP Address: Edit
##|*DESCR=Allow access to the 'Firewall: Virtual IP Address: Edit' page.
##|*MATCH=firewall_virtual_ip_edit.php*
##|-PRIV

require("guiconfig.inc");
require_once("filter.inc");
require("shaper.inc");

if (!is_array($config['virtualip']['vip'])) {
		$config['virtualip']['vip'] = array();
}

$a_vip = &$config['virtualip']['vip'];

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
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
	$pconfig['range'] = $a_vip[$id]['range'];
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

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "mode");
	$reqdfieldsn = array(gettext("Type"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($_POST['subnet']) {
		$_POST['subnet'] = trim($_POST['subnet']);
	}

	if ($_POST['subnet']) {
		if (!is_ipaddr($_POST['subnet'])) {
			$input_errors[] = gettext("A valid IP address must be specified.");
		} else {
			if (isset($id) && isset($a_vip[$id])) {
				$ignore_if = $a_vip[$id]['interface'];
				$ignore_mode = $a_vip[$id]['mode'];
				if (isset($a_vip[$id]['uniqid'])) {
					$ignore_uniqid = $a_vip[$id]['uniqid'];
				}
			} else {
				$ignore_if = $_POST['interface'];
				$ignore_mode = $_POST['mode'];
			}

			if (!isset($ignore_uniqid)) {
				$ignore_uniqid = $_POST['uniqid'];
			}

			if ($ignore_mode == 'carp') {
				$ignore_if = "_vip{$ignore_uniqid}";
			} else {
				$ignore_if .= "_virtualip{$id}";
			}

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
		if (is_ipaddrv4($_POST['subnet']) && $_POST['subnet_bits'] != "32") {
			$network_addr = gen_subnet($_POST['subnet'], $_POST['subnet_bits']);
			$broadcast_addr = gen_subnet_max($_POST['subnet'], $_POST['subnet_bits']);
		} else if (is_ipaddrv6($_POST['subnet']) && $_POST['subnet_bits'] != "128") {
			$network_addr = gen_subnetv6($_POST['subnet'], $_POST['subnet_bits']);
			$broadcast_addr = gen_subnetv6_max($_POST['subnet'], $_POST['subnet_bits']);
		}

		if (isset($network_addr) && $_POST['subnet'] == $network_addr) {
			$input_errors[] = gettext("You cannot use the network address for this VIP");
		} else if (isset($broadcast_addr) && $_POST['subnet'] == $broadcast_addr) {
			$input_errors[] = gettext("You cannot use the broadcast address for this VIP");
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
					$input_errors[] = sprintf(gettext("VHID %s is already in use on interface %s. Pick a unique number on this interface."), $_POST['vhid'], convert_friendly_interface_to_friendly_descr($_POST['interface']));
				}
				$idtracker++;
			}

			if (empty($_POST['password'])) {
				$input_errors[] = gettext("You must specify a CARP password that is shared between the two VHID members.");
			}

			if ($_POST['password'] != $_POST['password_confirm']) {
				$input_errors[] = gettext("Password and confirm password must match");
			}

			if ($_POST['interface'] == 'lo0') {
				$input_errors[] = gettext("For this type of vip localhost is not allowed.");
			} else if (strpos($_POST['interface'], '_vip')) {
				$input_errors[] = gettext("A CARP parent interface can only be used with IP Alias type Virtual IPs.");
			}

			break;
		case 'ipalias':
			if (strstr($_POST['interface'], "_vip")) {
				if (is_ipaddrv4($_POST['subnet'])) {
					$parent_ip = get_interface_ip($_POST['interface']);
					$parent_sn = get_interface_subnet($_POST['interface']);
					$subnet = gen_subnet($parent_ip, $parent_sn);
				} else if (is_ipaddrv6($_POST['subnet'])) {
					$parent_ip = get_interface_ipv6($_POST['interface']);
					$parent_sn = get_interface_subnetv6($_POST['interface']);
					$subnet = gen_subnetv6($parent_ip, $parent_sn);
				}

				if (isset($parent_ip) && !ip_in_subnet($_POST['subnet'], "{$subnet}/{$parent_sn}") &&
				    !ip_in_interface_alias_subnet(link_carp_interface_to_parent($_POST['interface']), $_POST['subnet'])) {
					$cannot_find = $_POST['subnet'] . "/" . $_POST['subnet_bits'] ;
					$input_errors[] = sprintf(gettext("Sorry, we could not locate an interface with a matching subnet for %s.  Please add an IP alias in this subnet on this interface."), $cannot_find);
				}

				unset($parent_ip, $parent_sn, $subnet);
			}

			break;
		default:
			if ($_POST['interface'] == 'lo0') {
				$input_errors[] = gettext("For this type of vip localhost is not allowed.");
			} else if (strpos($_POST['interface'], '_vip')) {
				$input_errors[] = gettext("A CARP parent interface can only be used with IP Alias type Virtual IPs.");
			}

			break;
	}

	if (!$input_errors) {
		$vipent = array();

		$vipent['mode'] = $_POST['mode'];
		$vipent['interface'] = $_POST['interface'];

		/* ProxyARP specific fields */
		if ($_POST['mode'] === "proxyarp") {
			if ($_POST['type'] == "range") {
				$vipent['range']['from'] = $_POST['range_from'];
				$vipent['range']['to'] = $_POST['range_to'];

			}

			$vipent['noexpand'] = isset($_POST['noexpand']);
		}

		/* CARP specific fields */
		if ($_POST['mode'] === "carp") {
			$vipent['vhid'] = $_POST['vhid'];
			$vipent['uniqid'] = $_POST['uniqid'];
			$vipent['advskew'] = $_POST['advskew'];
			$vipent['advbase'] = $_POST['advbase'];

			if ($_POST['password'] != DMYPWD) {
				$vipent['password'] = $_POST['password'];
			} else {
				$vipent['password'] = $a_vip[$id]['password'];
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

		if (write_config()) {
			mark_subsystem_dirty('vip');
			file_put_contents("{$g['tmp_path']}/.firewall_virtual_ip.apply", serialize($toapplylist));
		}

		header("Location: firewall_virtual_ip.php");
		exit;
	}
}

$ipaliashelp = gettext('The mask must be the network\'s subnet mask. It does not specify a CIDR range.');
$proxyarphelp = gettext('Enter a CIDR block of proxy ARP addresses.');

$pgtitle = array(gettext("Firewall"), gettext("Virtual IP Address"), gettext("Edit"));
include("head.inc");

function build_if_list() {
	$list = array();

	$interfaces = get_configured_interface_with_descr(false, true);
	$carplist = get_configured_carp_interface_list();

	foreach ($carplist as $cif => $carpip) {
		$interfaces[$cif] = $carpip . ' (' . get_vip_descr($carpip) . ')';
	}

	$interfaces['lo0'] = 'Localhost';

	return($interfaces);
}

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('Edit Virtual IP');

$group = new Form_Group('Type');

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
	'Interface',
	$pconfig['interface'],
	build_if_list()
));

$section->addInput(new Form_Select(
	'type',
	'Address type',
	((!$pconfig['range'] && $pconfig['subnet_bits'] == 32) || (!isset($pconfig['subnet']))) ? 'single':'network',
	array(
		'single' => 'Single address',
		'network' => 'Network'
	)
))->addClass('typesel');

$section->addInput(new Form_IpAddress(
	'subnet',
	'Address(es)',
	$pconfig['subnet']
))->addMask('subnet_bits', $pconfig['subnet_bits'])->setHelp('<span id="address_note"></span>');

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
))->setHelp('Enter the VHID group that the machines will share');

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
))->setHelp('You may enter a description here for your reference (not parsed).');

if (isset($id) && $a_vip[$id]){
	$section->addInput(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$section->addInput(new Form_Input(
	'uniqid',
	null,
	'hidden',
	$pconfig['uniqid']
));

$form->add($section);

print($form);
?>

<div class="infoblock">
	<?=print_info_box(gettext("Proxy ARP and Other type Virtual IPs cannot be bound to by anything running on the firewall, such as IPsec, OpenVPN, etc.  Use a CARP or IP Alias type address for these types.") . '<br />' .
			   sprintf(gettext("For more information on CARP and the above values, visit the OpenBSD %s"), '<a href="http://www.openbsd.org/faq/pf/carp.html">CARP FAQ</a>.'), 'info')?>
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

		if (mode == 'ipalias') {
			$('#address_note').html("<?=$ipaliashelp?>");
			$('#type').val('single');
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
		} else if (mode == 'proxyarp') {
			$('#address_note').html("<?=$proxyarphelp?>");
			disableInput('type', false);
			disableInput('subnet_bits', ($('#type').val() == 'single'));
		} else {
			$('#address_note').html('');
			disableInput('type', false);
			disableInput('subnet_bits', ($('#type').val() == 'single'));
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
