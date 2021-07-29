<?php
/*
 * services_router_advertisements.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2010 Seth Mos <seth.mos@dds.nl>
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
##|*IDENT=page-services-router-advertisements
##|*NAME=Services: Router Advertisements
##|*DESCR=Allow access to the 'Services: Router Advertisements' page.
##|*MATCH=services_router_advertisements.php*
##|-PRIV

require_once("guiconfig.inc");

if (!$g['services_dhcp_server_enable']) {
	header("Location: /");
	exit;
}

$if = $_REQUEST['if'];

if (!$_REQUEST['if']) {
	$info_msg = gettext("The DHCPv6 Server can only be enabled on interfaces configured with static, non unique local IP addresses.") . "<br />" .
	    gettext("Only interfaces configured with a static IP will be shown.");
}

$iflist = get_configured_interface_with_descr();

/* set the starting interface */
if (!$if || !isset($iflist[$if])) {
	foreach ($iflist as $ifent => $ifname) {
		$oc = $config['interfaces'][$ifent];
		$valid_if_ipaddrv6 = (bool) ($oc['ipaddrv6'] == 'track6' ||
		    (is_ipaddrv6($oc['ipaddrv6']) &&
		    !is_linklocal($oc['ipaddrv6'])));

		if ((!is_array($config['dhcpdv6'][$ifent]) ||
		    !isset($config['dhcpdv6'][$ifent]['enable'])) &&
		    !$valid_if_ipaddrv6) {
			continue;
		}
		$if = $ifent;
		break;
	}
}

if (is_array($config['dhcpdv6'][$if])) {
	/* RA specific */
	$pconfig['ramode'] = $config['dhcpdv6'][$if]['ramode'];
	$pconfig['rapriority'] = $config['dhcpdv6'][$if]['rapriority'];
	$pconfig['rainterface'] = $config['dhcpdv6'][$if]['rainterface'];
	if ($pconfig['rapriority'] == "") {
		$pconfig['rapriority'] = "medium";
	}

	$pconfig['ravalidlifetime'] = $config['dhcpdv6'][$if]['ravalidlifetime'];
	$pconfig['rapreferredlifetime'] = $config['dhcpdv6'][$if]['rapreferredlifetime'];
	$pconfig['raminrtradvinterval'] = $config['dhcpdv6'][$if]['raminrtradvinterval'];
	$pconfig['ramaxrtradvinterval'] = $config['dhcpdv6'][$if]['ramaxrtradvinterval'];
	$pconfig['raadvdefaultlifetime'] = $config['dhcpdv6'][$if]['raadvdefaultlifetime'];

	$pconfig['radomainsearchlist'] = $config['dhcpdv6'][$if]['radomainsearchlist'];
	list($pconfig['radns1'], $pconfig['radns2'], $pconfig['radns3']) = $config['dhcpdv6'][$if]['radnsserver'];
	$pconfig['radvd-dns'] = ($config['dhcpdv6'][$if]['radvd-dns'] != 'disabled') ? true : false;
	$pconfig['rasamednsasdhcp6'] = isset($config['dhcpdv6'][$if]['rasamednsasdhcp6']);

	$pconfig['subnets'] = $config['dhcpdv6'][$if]['subnets']['item'];
}
if (!is_array($pconfig['subnets'])) {
	$pconfig['subnets'] = array();
}

$advertise_modes = array(
	"disabled" => 	gettext("Disabled"),
	"router" => 	gettext("Router Only - RA Flags [none], Prefix Flags [router]"),
	"unmanaged" => 	gettext("Unmanaged - RA Flags [none], Prefix Flags [onlink, auto, router]"),
	"managed" => 	gettext("Managed - RA Flags [managed, other stateful], Prefix Flags [onlink, router]"),
	"assist" => 	gettext("Assisted - RA Flags [managed, other stateful], Prefix Flags [onlink, auto, router]"),
	"stateless_dhcp" => gettext("Stateless DHCP - RA Flags [other stateful], Prefix Flags [onlink, auto, router]"));
$priority_modes = array(
	"low" => 	gettext("Low"),
	"medium" => gettext("Normal"),
	"high" => 	gettext("High"));

$subnets_help = '<span class="help-block">' .
	gettext("Subnets are specified in CIDR format.  " .
		"Select the CIDR mask that pertains to each entry.	" .
		"/128 specifies a single IPv6 host; /64 specifies a normal IPv6 network; etc.  " .
		"If no subnets are specified here, the Router Advertisement (RA) Daemon will advertise to the subnet to which the router's interface is assigned.") .
	'</span>';

// THe use of <div class="infoblock"> here causes the text to be hidden until the user clicks the "info" icon
$ramode_help = gettext('Select the Operating Mode for the Router Advertisement (RA) Daemon.') .
	'<div class="infoblock">' .
	'<dl class="dl-horizontal responsive">' .
	'<dt>' . gettext('Disabled') . 		 '</dt><dd>' . gettext('RADVD will not be enabled on this interface.') . '</dd>' .
	'<dt>' . gettext('Router Only') . 	 '</dt><dd>' . gettext('Will advertise this router.') . '</dd>' .
	'<dt>' . gettext('Unmanaged') . 	 '</dt><dd>' . gettext('Will advertise this router with stateless autoconfig.') . '</dd>' .
	'<dt>' . gettext('Managed') . 		 '</dt><dd>' . gettext('Will advertise this router with all configuration through a DHCPv6 server.') . '</dd>' .
	'<dt>' . gettext('Assisted') . 		 '</dt><dd>' . gettext('Will advertise this router with configuration through a DHCPv6 server and/or stateless autoconfig.') . '</dd>' .
	'<dt>' . gettext('Stateless DHCP') . '</dt><dd>' . gettext('Will advertise this router with stateless autoconfig and other configuration information available via DHCPv6.') . '</dd>' .
	'</dl>' .
	sprintf(gettext('It is not required to activate DHCPv6 server on %s ' .
	    'when set to "Managed", "Assisted" or "Stateless DHCP", it can ' .
	    'be another host on the network.'), $g['product_label']) .
	'</div>';

if ($_POST['save']) {
	unset($input_errors);

	$pconfig = $_POST;

	/* input validation */

	$pconfig['subnets'] = array();
	for ($x = 0; $x < 5000; $x += 1) {
		$address = trim($_POST['subnet_address' . $x]);
		if ($address === "") {
			continue;
		}

		$bits = trim($_POST['subnet_bits' . $x]);
		if ($bits === "") {
			$bits = "128";
		}

		if (is_alias($address)) {
			$pconfig['subnets'][] = $address;
		} else {
			$pconfig['subnets'][] = $address . "/" . $bits;
			if (!is_ipaddrv6($address)) {
				$input_errors[] = sprintf(gettext('An invalid subnet or alias was specified. [%1$s/%2$s]'), $address, $bits);
			}
		}
	}

	if (($_POST['radns1'] && !is_ipaddrv6($_POST['radns1'])) || ($_POST['radns2'] && !is_ipaddrv6($_POST['radns2'])) || ($_POST['radns3'] && !is_ipaddrv6($_POST['radns3']))) {
		$input_errors[] = gettext("A valid IPv6 address must be specified for each of the DNS servers.");
	}
	if ($_POST['radomainsearchlist']) {
		$domain_array=preg_split("/[ ;]+/", $_POST['radomainsearchlist']);
		foreach ($domain_array as $curdomain) {
			if (!is_domain($curdomain)) {
				$input_errors[] = gettext("A valid domain search list must be specified.");
				break;
			}
		}
	}

	if ($_POST['ravalidlifetime'] && ($_POST['ravalidlifetime'] < 7200)) {
		$input_errors[] = gettext("A valid lifetime below 2 hours will be ignored by clients (RFC 4862 Section 5.5.3 point e)");
	}
	if ($_POST['ravalidlifetime'] && !is_numericint($_POST['ravalidlifetime'])) {
		$input_errors[] = gettext("Valid lifetime must be an integer.");
	}
	if ($_POST['raminrtradvinterval']) {
		if (!is_numericint($_POST['raminrtradvinterval'])) {
			$input_errors[] = gettext("Minimum advertisement interval must be an integer.");
		}
		if ($_POST['raminrtradvinterval'] < 3) {
			$input_errors[] = gettext("Minimum advertisement interval must be no less than 3.");
		}
		if ($_POST['ramaxrtradvinterval'] && $_POST['raminrtradvinterval'] > (0.75 * $_POST['ramaxrtradvinterval'])) {
			$input_errors[] = gettext("Minimum advertisement interval must be no greater than 0.75 * Maximum advertisement interval");
		}
	}
	if ($_POST['ramaxrtradvinterval']) {
		if (!is_numericint($_POST['ramaxrtradvinterval'])) {
			$input_errors[] = gettext("Maximum advertisement interval must be an integer.");
		}
		if ($_POST['ramaxrtradvinterval'] < 4 || $_POST['ramaxrtradvinterval'] > 1800) {
			$input_errors[] = gettext("Maximum advertisement interval must be no less than 4 and no greater than 1800.");
		}
	}
	if ($_POST['raadvdefaultlifetime'] && (($_POST['raadvdefaultlifetime'] < 1) || ($_POST['raadvdefaultlifetime'] > 9000))) {
		$input_errors[] = gettext("Router lifetime must be an integer between 1 and 9000.");
	}
	if (($_POST['ravalidlifetime'] && $_POST['rapreferredlifetime'] &&
	    ($_POST['ravalidlifetime'] < $_POST['rapreferredlifetime'])) ||
	    ($_POST['ravalidlifetime'] && empty($_POST['rapreferredlifetime']) &&
	    ($_POST['ravalidlifetime'] < 14400)) || (empty($_POST['ravalidlifetime']) &&
	    $_POST['rapreferredlifetime'] && ($_POST['rapreferredlifetime'] > 86400))) { 
		$input_errors[] = gettext("Default valid lifetime must be greater than Default preferred lifetime.");
	}

	if (!$input_errors) {
		if (!is_array($config['dhcpdv6'])) {
			$config['dhcpdv6'] = array();
		}

		if (!is_array($config['dhcpdv6'][$if])) {
			$config['dhcpdv6'][$if] = array();
		}

		$config['dhcpdv6'][$if]['ramode'] = $_POST['ramode'];
		$config['dhcpdv6'][$if]['rapriority'] = $_POST['rapriority'];
		$config['dhcpdv6'][$if]['rainterface'] = $_POST['rainterface'];

		$config['dhcpdv6'][$if]['ravalidlifetime'] = $_POST['ravalidlifetime'];
		$config['dhcpdv6'][$if]['rapreferredlifetime'] = $_POST['rapreferredlifetime'];
		$config['dhcpdv6'][$if]['raminrtradvinterval'] = $_POST['raminrtradvinterval'];
		$config['dhcpdv6'][$if]['ramaxrtradvinterval'] = $_POST['ramaxrtradvinterval'];
		$config['dhcpdv6'][$if]['raadvdefaultlifetime'] = $_POST['raadvdefaultlifetime'];

		$config['dhcpdv6'][$if]['radomainsearchlist'] = $_POST['radomainsearchlist'];
		unset($config['dhcpdv6'][$if]['radnsserver']);
		if ($_POST['radns1']) {
			$config['dhcpdv6'][$if]['radnsserver'][] = $_POST['radns1'];
		}
		if ($_POST['radns2']) {
			$config['dhcpdv6'][$if]['radnsserver'][] = $_POST['radns2'];
		}
		if ($_POST['radns3']) {
			$config['dhcpdv6'][$if]['radnsserver'][] = $_POST['radns3'];
		}

		$config['dhcpdv6'][$if]['radvd-dns'] = ($_POST['radvd-dns']) ? "enabled" : "disabled";
		$config['dhcpdv6'][$if]['rasamednsasdhcp6'] = ($_POST['rasamednsasdhcp6']) ? true : false;

		if (count($pconfig['subnets'])) {
			$config['dhcpdv6'][$if]['subnets']['item'] = $pconfig['subnets'];
		} else {
			unset($config['dhcpdv6'][$if]['subnets']);
		}

		write_config("Router Advertisements settings saved");
		$changes_applied = true;
		$retval = 0;
		$retval |= services_radvd_configure();
	}
}

$pgtitle = array(gettext("Services"), htmlspecialchars(gettext("DHCPv6 Server & RA")));
$pglinks = array("", "services_dhcpv6.php");

if (!empty($if) && isset($iflist[$if])) {
	$pgtitle[] = $iflist[$if];
	$pglinks[] = "services_dhcpv6.php?if=" . $if;
}
$pgtitle[] = gettext("Router Advertisements");
$pglinks[] = "@self";
$shortcut_section = "radvd";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($changes_applied) {
	print_apply_result_box($retval);
}

if ($info_msg) {
	print_info_box($info_msg, 'success');
}

/* active tabs */
$tab_array = array();
$tabscounter = 0;
$i = 0;
foreach ($iflist as $ifent => $ifname) {
	$oc = $config['interfaces'][$ifent];
	// We need interfaces configured with a static IPv6 address or track6 for PD.
	if (!is_ipaddrv6($oc['ipaddrv6']) && $oc['ipaddrv6'] != "track6") {
		continue;
	}

	if ($ifent == $if) {
		$active = true;
	} else {
		$active = false;
	}

	$tab_array[] = array($ifname, $active, "services_router_advertisements.php?if={$ifent}");
	$tabscounter++;
}

if ($tabscounter == 0) {
	include("foot.inc");
	exit;
}

display_top_tabs($tab_array);

$tab_array = array();
$tab_array[] = array(gettext("DHCPv6 Server"),		 false, "services_dhcpv6.php?if={$if}");
$tab_array[] = array(gettext("Router Advertisements"), true,  "services_router_advertisements.php?if={$if}");
display_top_tabs($tab_array, false, 'nav nav-tabs');

$form = new Form();

$section = new Form_Section('Advertisements');

$section->addInput(new Form_Select(
	'ramode',
	'*Router mode',
	$pconfig['ramode'],
	$advertise_modes
))->setHelp($ramode_help);

$section->addInput(new Form_Select(
	'rapriority',
	'*Router priority',
	$pconfig['rapriority'],
	$priority_modes
))->setHelp('Select the Priority for the Router Advertisement (RA) Daemon.');

$carplist = get_configured_vip_list("inet6", VIP_CARP);

$carplistif = array();

if (count($carplist) > 0) {
	foreach ($carplist as $ifname => $vip) {
		if (get_configured_vip_interface($ifname) == $if) {
			$carplistif[$ifname] = $vip;
		}
	}
}

if (count($carplistif) > 0) {
	$iflist = array();

	$iflist['interface'] = convert_friendly_interface_to_friendly_descr($if);
	foreach ($carplistif as $ifname => $vip) {
		$iflist[$ifname] = get_vip_descr($vip) . " - " . $vip;
	}

	$section->addInput(new Form_Select(
		'rainterface',
		'RA Interface',
		$pconfig['rainterface'],
		$iflist
	))->setHelp('Select the Interface for the Router Advertisement (RA) Daemon.');
}

$section->addInput(new Form_Input(
	'ravalidlifetime',
	'Default valid lifetime',
	'number',
	$pconfig['ravalidlifetime'],
	['min' => 1, 'max' => 655350, 'placeholder' => 86400]
))->setHelp('The length of time in seconds (relative to the time the packet is sent) that the prefix is valid for the purpose of on-link determination.%1$s' .
'The default is 86400 seconds.', '<br />');

$section->addInput(new Form_Input(
	'rapreferredlifetime',
	'Default preferred lifetime',
	'text',
	$pconfig['rapreferredlifetime'],
	['placeholder' => 14400]
))->setHelp('Seconds. The length of time in seconds (relative to the time the packet is sent) that addresses generated from the prefix via stateless address autoconfiguration remain preferred.%1$s' .
			'The default is 14400 seconds.', '<br />');

$section->addInput(new Form_Input(
	'raminrtradvinterval',
	'Minimum RA interval',
	'number',
	$pconfig['raminrtradvinterval'],
	['min' => 3, 'max' => 1350, 'placeholder' => 5]
))->setHelp('The minimum time allowed between sending unsolicited multicast router advertisements in seconds.%1$s' .
'The default is 5 seconds.', '<br />');

$section->addInput(new Form_Input(
	'ramaxrtradvinterval',
	'Maximum RA interval',
	'number',
	$pconfig['ramaxrtradvinterval'],
	['min' => 4, 'max' => 1800, 'placeholder' => 20]
))->setHelp('The maximum time allowed between sending unsolicited multicast router advertisements in seconds.%1$s' .
'The default is 20 seconds.', '<br />');

$section->addInput(new Form_Input(
	'raadvdefaultlifetime',
	'Router lifetime',
	'number',
	$pconfig['raadvdefaultlifetime'],
	['min' => 1, 'max' => 9000]
))->setHelp('The lifetime associated with the default router in seconds.%1$s' .
'The default is 3 * Maximum RA interval seconds.', '<br />');

$section->addInput(new Form_StaticText(
	'RA Subnets',
	$subnets_help
));

if (empty($pconfig['subnets'])) {
	$pconfig['subnets'] = array('0' => '/128');
}

$counter = 0;
$numrows = count($pconfig['subnets']) - 1;

foreach ($pconfig['subnets'] as $subnet) {
	$address_name = "subnet_address" . $counter;
	$bits_name = "subnet_bits" . $counter;
	list($address, $subnet) = explode("/", $subnet);

	$group = new Form_Group($counter == 0 ? 'Subnets':'');

	$group->add(new Form_IpAddress(
		$address_name,
		null,
		$address,
		'V6'
	))->addMask($bits_name, $subnet);

	$group->add(new Form_Button(
		'deleterow' . $counter,
		'Delete',
		null,
		'fa-trash'
	))->removeClass('btn-primary')->addClass('btn-warning');

	$group->addClass('repeatable');

	$section->add($group);

	$counter++;
}

$section->addInput(new Form_Button(
	'addrow',
	'Add',
	null,
	'fa-plus'
))->addClass('btn-success');

$form->add($section);

$section = new Form_Section('DNS Configuration');

for ($idx=1; $idx<=3; $idx++) {
	$section->addInput(new Form_IpAddress(
		'radns' . $idx,
		'Server ' . $idx,
		$pconfig['radns' . $idx],
		'ALIASV6'
	))->setHelp(($idx < 3) ? '':'Leave blank to use the system default DNS servers - this interface\'s IP if DNS Forwarder or Resolver is enabled, otherwise the servers configured on the General page');
}

$section->addInput(new Form_Input(
	'radomainsearchlist',
	'Domain search list',
	'text',
	$pconfig['radomainsearchlist']
))->setHelp('The RA server can optionally provide a domain search list. Use the semicolon character as separator.');

$section->addInput(new Form_Checkbox(
	'radvd-dns',
	null,
	'Provide DNS configuration via radvd',
	$pconfig['radvd-dns']
))->setHelp('Unchecking this box disables the RDNSS/DNSSL options in /var/etc/radvd.conf. ' .
			'Use with caution, as the resulting behavior may violate some RFCs.');

$section->addInput(new Form_Checkbox(
	'rasamednsasdhcp6',
	'Settings',
	'Use same settings as DHCPv6 server',
	$pconfig['rasamednsasdhcp6']
));

$form->addGlobal(new Form_Input(
	'if',
	null,
	'hidden',
	$if
));


$form->add($section);
print($form);
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// Suppress "Delete row" button if there are fewer than two rows
	checkLastRow();

	// --------- Autocomplete -----------------------------------------------------------------------------------------
	var addressarray = <?= json_encode(get_alias_list(array("host", "network", "urltable"))) ?>;

	$('#radns1, #radns2, #radns3').autocomplete({
		source: addressarray
	});

});
//]]>
</script>

<?php include("foot.inc");
