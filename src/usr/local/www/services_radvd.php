<?php
/*
 * services_radvd.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-services-router-advertisement
##|*NAME=Services: Router Advertisement
##|*DESCR=Allow access to the 'Services: Router Advertisement' page.
##|*MATCH=services_radvd.php*
##|-PRIV

require_once('guiconfig.inc');

if (!g_get('services_dhcp_server_enable')) {
	header('Location: /');
	exit;
}

$if = $_REQUEST['if'];
$iflist = get_configured_interface_with_descr();

/* set the starting interface */
if (!$if || !isset($iflist[$if])) {
	foreach ($iflist as $ifent => $ifname) {
		$ifaddr = config_get_path("interfaces/{$ifent}/ipaddrv6", 'none');
		if (!config_path_enabled("dhcpdv6/{$ifent}") &&
		    !(($ifaddr == 'track6') || ($ifaddr == 'none') ||
		    (is_ipaddrv6($ifaddr) &&
		    !is_linklocal($ifaddr)))) {
			continue;
		}
		$if = $ifent;
		break;
	}
}

if (!empty(config_get_path("dhcpdv6/{$if}"))) {
	/* RA specific */
	$pconfig['ramode'] = config_get_path("dhcpdv6/{$if}/ramode");
	$pconfig['rapriority'] = config_get_path("dhcpdv6/{$if}/rapriority");
	$pconfig['rainterface'] = config_get_path("dhcpdv6/{$if}/rainterface");
	if ($pconfig['rapriority'] == "") {
		$pconfig['rapriority'] = "medium";
	}

	$pconfig['ravalidlifetime'] = config_get_path("dhcpdv6/{$if}/ravalidlifetime");
	$pconfig['rapreferredlifetime'] = config_get_path("dhcpdv6/{$if}/rapreferredlifetime");
	$pconfig['raminrtradvinterval'] = config_get_path("dhcpdv6/{$if}/raminrtradvinterval");
	$pconfig['ramaxrtradvinterval'] = config_get_path("dhcpdv6/{$if}/ramaxrtradvinterval");
	$pconfig['raadvdefaultlifetime'] = config_get_path("dhcpdv6/{$if}/raadvdefaultlifetime");

	$pconfig['radomainsearchlist'] = config_get_path("dhcpdv6/{$if}/radomainsearchlist");
	list($pconfig['radns1'], $pconfig['radns2'], $pconfig['radns3']) = config_get_path("dhcpdv6/{$if}/radnsserver");
	$pconfig['radvd-dns'] = (config_get_path("dhcpdv6/{$if}/radvd-dns") != 'disabled') ? true : false;
	$pconfig['rasamednsasdhcp6'] = config_path_enabled("dhcpdv6/{$if}", 'rasamednsasdhcp6');

	$pconfig['subnets'] = config_get_path("dhcpdv6/{$if}/subnets/item");
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



// THe use of <div class="infoblock"> here causes the text to be hidden until the user clicks the "info" icon
$ramode_help = gettext('Select the Operating Mode for the Router Advertisement (RA) Daemon.') .
	'<div class="infoblock">' .
	'<dl class="dl-horizontal responsive">' .
	'<dt>' . gettext('Disabled') . 		 '</dt><dd>' . gettext('RADVD will not be enabled on this interface.') . '</dd>' .
	'<dt>' . gettext('Router Only') . 	 '</dt><dd>' . gettext('Will advertise this router.') . '</dd>' .
	'<dt>' . gettext('Unmanaged') . 	 '</dt><dd>' . gettext('Will advertise this router with Stateless Address Auto-Configuration (SLAAC).') . '</dd>' .
	'<dt>' . gettext('Managed') . 		 '</dt><dd>' . gettext('Will advertise this router with all configuration through a DHCPv6 server.') . '</dd>' .
	'<dt>' . gettext('Assisted') . 		 '</dt><dd>' . gettext('Will advertise this router with configuration through a DHCPv6 server and/or SLAAC.') . '</dd>' .
	'<dt>' . gettext('Stateless DHCP') . '</dt><dd>' . gettext('Will advertise this router with SLAAC and other configuration information available via DHCPv6.') . '</dd>' .
	'</dl>' .
	sprintf(gettext('It is not required to activate DHCPv6 server on %s ' .
	    'when set to "Managed", "Assisted" or "Stateless DHCP", it can ' .
	    'be another host on the network.'), g_get('product_label')) .
	'</div>';

if ($_POST['save']) {
	unset($input_errors);

	$pconfig = $_POST;

	/* input validation */

	if (config_get_path("interfaces/{$if}/ipaddrv6", 'none') == "none" && $_POST['ramode'] != 'disabled') {
		$input_errors[] = gettext("Router Advertisements can only be enabled on interfaces configured with static IPv6 or Track Interface.");
	}

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
	if ($_POST['rapreferredlifetime']) {
		if (!is_numericint($_POST['rapreferredlifetime'])) {
			$input_errors[] = gettext("Default preferred lifetime must be an integer.");
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
		config_init_path("dhcpdv6/{$if}");
		$dhcpd6_config = config_get_path("dhcpdv6/{$if}");

		$dhcpd6_config['ramode'] = $_POST['ramode'];
		$dhcpd6_config['rapriority'] = $_POST['rapriority'];
		$dhcpd6_config['rainterface'] = $_POST['rainterface'];

		$dhcpd6_config['ravalidlifetime'] = $_POST['ravalidlifetime'];
		$dhcpd6_config['rapreferredlifetime'] = $_POST['rapreferredlifetime'];
		$dhcpd6_config['raminrtradvinterval'] = $_POST['raminrtradvinterval'];
		$dhcpd6_config['ramaxrtradvinterval'] = $_POST['ramaxrtradvinterval'];
		$dhcpd6_config['raadvdefaultlifetime'] = $_POST['raadvdefaultlifetime'];

		$dhcpd6_config['radomainsearchlist'] = $_POST['radomainsearchlist'];
		array_del_path($dhcpd6_config, 'radnsserver');
		if ($_POST['radns1']) {
			$dhcpd6_config['radnsserver'][] = $_POST['radns1'];
		}
		if ($_POST['radns2']) {
			$dhcpd6_config['radnsserver'][] = $_POST['radns2'];
		}
		if ($_POST['radns3']) {
			$dhcpd6_config['radnsserver'][] = $_POST['radns3'];
		}

		$dhcpd6_config['radvd-dns'] = ($_POST['radvd-dns']) ? "enabled" : "disabled";
		$dhcpd6_config['rasamednsasdhcp6'] = ($_POST['rasamednsasdhcp6']) ? true : false;

		if (count($pconfig['subnets'])) {
			$dhcpd6_config['subnets']['item'] = $pconfig['subnets'];
		} else {
			array_del_path($dhcpd6_config, 'subnets');
		}

		config_set_path("dhcpdv6/{$if}", $dhcpd6_config);
		write_config("Router Advertisements settings saved");
		$changes_applied = true;
		$retval = 0;
		$retval |= services_radvd_configure();
	}
}

$pgtitle = [gettext('Services'), gettext('Router Advertisement')];
$pglinks = [null, 'services_radvd.php'];

if (!empty($if) && isset($iflist[$if])) {
	$pgtitle[] = $iflist[$if];
	$pglinks[] = 'services_radvd.php?if=' . $if;
}
$shortcut_section = 'radvd';

include('head.inc');

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
	$oc = config_get_path("interfaces/{$ifent}");
	/* We need interfaces configured with a static IPv6 address or track6 for PD.
	   Also show those configured as none to allow disabling the service. See:
	   https://redmine.pfsense.org/issues/14967 */
	if (!is_ipaddrv6($oc['ipaddrv6']) && $oc['ipaddrv6'] != "track6" && array_get_path($oc, 'ipaddrv6', 'none') != 'none') {
		continue;
	}

	if ($ifent == $if) {
		$active = true;
	} else {
		$active = false;
	}

	$tab_array[] = array($ifname, $active, 'services_radvd.php?if='.$ifent);
	$tabscounter++;
}

if ($tabscounter == 0) {
	print_info_box(gettext('Router Advertisements can only be enabled on interfaces configured with static IPv6 or Track Interface.'), 'danger', false);
	include('foot.inc');
	exit;
}

$valid_ra = in_array(config_get_path('dhcpdv6/'.$if.'/ramode', 'disabled'), ['managed', 'assist', 'stateless_dhcp']);
if (config_path_enabled('dhcpdv6/'.$if) && !$valid_ra) {
	print_info_box(sprintf(gettext('DHCPv6 is enabled but not being advertised to clients on %1$s. Router Advertisement must be enabled and Router Mode set to "Managed", "Assisted" or "Stateless DHCP."'), $iflist[$if]), 'danger', false);
}

display_top_tabs($tab_array);

$form = new Form();

$section = new Form_Section(gettext('Router Advertisement'));

$section->addInput(new Form_Select(
	'ramode',
	'*'.gettext('Router Mode'),
	$pconfig['ramode'],
	$advertise_modes
))->setHelp($ramode_help);

$section->addInput(new Form_Select(
	'rapriority',
	'*'.gettext('Router Priority'),
	$pconfig['rapriority'],
	$priority_modes
))->setHelp(gettext('Select the Priority for the RA Daemon.'));

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
		gettext('RA Interface'),
		$pconfig['rainterface'],
		$iflist
	))->setHelp(gettext('Select the Interface for the Router Advertisement (RA) Daemon.'));
}

$section->addInput(new Form_Input(
	'ravalidlifetime',
	gettext('Valid Lifetime'),
	'number',
	$pconfig['ravalidlifetime'],
	['min' => 1, 'max' => 655350, 'placeholder' => 86400]
))->setHelp(gettext('The length of time in seconds (relative to the time the packet is sent) that the prefix is valid for the purpose of on-link determination.%1$s' .
'The default is 86400 seconds.'), '<br />');

$section->addInput(new Form_Input(
	'rapreferredlifetime',
	gettext('Preferred Lifetime'),
	'number',
	$pconfig['rapreferredlifetime'],
	['placeholder' => 14400]
))->setHelp(gettext('The length of time in seconds (relative to the time the packet is sent) that addresses generated from the prefix via SLAAC remain preferred.%1$s' .
			'The default is 14400 seconds.'), '<br />');

$section->addInput(new Form_Input(
	'raminrtradvinterval',
	gettext('Minimum RA Interval'),
	'number',
	$pconfig['raminrtradvinterval'],
	['min' => 3, 'max' => 1350, 'placeholder' => 200]
))->setHelp(gettext('The minimum time allowed between sending unsolicited multicast router advertisements in seconds.%1$s' .
'The default is 200 seconds.'), '<br />');

$section->addInput(new Form_Input(
	'ramaxrtradvinterval',
	gettext('Maximum RA Interval'),
	'number',
	$pconfig['ramaxrtradvinterval'],
	['min' => 4, 'max' => 1800, 'placeholder' => 600]
))->setHelp(gettext('The maximum time allowed between sending unsolicited multicast router advertisements in seconds.%1$s' .
'The default is 600 seconds.'), '<br />');

if (isset($pconfig['raadvdefaultlifetime']) &&
    is_numeric($pconfig['raadvdefaultlifetime'])) {
	$raadvdefaultlifetime = $pconfig['raadvdefaultlifetime'];
} elseif (isset($pconfig['ramaxrtradvinterval']) &&
    is_numeric($pconfig['ramaxrtradvinterval'])) {
	$raadvdefaultlifetime = $pconfig['ramaxrtradvinterval'] * 3;
} else {
	$raadvdefaultlifetime = 1800;
}

$section->addInput(new Form_Input(
	'raadvdefaultlifetime',
	gettext('Router Lifetime'),
	'number',
	$pconfig['raadvdefaultlifetime'],
	['min' => 1, 'max' => 9000, 'placeholder' => $raadvdefaultlifetime]
))->setHelp(gettext('The lifetime associated with the default router in seconds.%1$s' .
'The default is 3 * Maximum RA interval seconds.'), '<br />');


if (empty($pconfig['subnets'])) {
	$pconfig['subnets'] = array('0' => '/128');
}

$counter = 0;
$last = (count($pconfig['subnets']) - 1);
foreach ($pconfig['subnets'] as $subnet) {
	$group = new Form_Group(($counter == 0) ? gettext('RA Subnet(s)') : '');
	$group->addClass('repeatable');

	[$address, $subnet] = explode('/', $subnet);
	$group->add(new Form_IpAddress(
		'subnet_address' . $counter,
		gettext('RA Subnet'),
		$address,
		'V6'
	))->addClass('autotrim')
          ->addMask('subnet_bits' . $counter, $subnet)
	  ->setHelp(($counter === $last) ? gettext('Subnets are specified in CIDR format. Select the CIDR mask that pertains to each entry. If no subnets are specified here, the RA Daemon will advertise to the subnet to which the router\'s interface is assigned.') : '');

	$group->add(new Form_Button(
		'deleterow' . $counter,
		gettext('Delete'),
		null,
		'fa-solid fa-trash-can'
	))->addClass('btn-sm btn-warning');

	$section->add($group);
	$counter++;
}

$group = new Form_Group(null);
$input = new Form_Button(
	'addrow',
	gettext('Add RA Subnet'),
	null,
	'fa-solid fa-plus'
);
$input->addClass('btn-success');
$group->add($input);

$section->add($group);

$form->add($section);

$section = new Form_Section(gettext('DNS Configuration'));

$section->addInput(new Form_Checkbox(
	'radvd-dns',
	gettext('Enable DNS'),
	gettext('Provide DNS Configuration via the RA Daemon'),
	$pconfig['radvd-dns']
))->setHelp(gettext('Unchecking this box disables the RA Daemon RDNSS/DNSSL options. ' .
			'Use with caution, as the resulting behavior may violate some RFCs.'));

$section->addInput(new Form_Checkbox(
	'rasamednsasdhcp6',
	gettext('Mirror DHCPv6'),
	gettext('Mirror DHCPv6 DNS Configuration'),
	$pconfig['rasamednsasdhcp6']
))->setHelp(gettext('When checked, the DNS configuration will be copied from the primary DHCPv6 options automatically.'));

$ifipv6 = get_interface_ipv6($if);

$dns_arrv6 = [];
foreach (config_get_path('system/dnsserver', []) as $dnsserver) {
	if (is_ipaddrv6($dnsserver)) {
		$dns_arrv6[] = $dnsserver;
	}
}

if (config_path_enabled('dnsmasq') ||
    config_path_enabled('unbound')) {
	$dns_arrv6 = [$ifipv6];
}

if (is_numeric($pool) || ($act === 'newpool')) {
	$subnet_dnsservers = config_get_path('dhcpdv6/'.$if.'/dnsserver', []);
	if (!empty($subnet_dnsservers)) {
		$dns_arrv6 = $subnet_dnsservers;
	}
}

for ($idx = 1; $idx <= 4; $idx++) {
	$last = $section->addInput(new Form_IpAddress(
		'radns' . $idx,
		gettext('DNS Server') . ' '. $idx,
		$pconfig['radns' . $idx],
		'ALIASV6'
	))->addClass('autotrim')
	  ->setAttribute('placeholder', $dns_arrv6[$idx - 1] ?? sprintf(gettext('DNS Server %s'), $idx));
}
$last->setHelp(gettext('Leave blank to use the IP address of this firewall interface if DNS Resolver or Forwarder is enabled, the servers configured in General settings or those obtained dynamically.'));

$section->addInput(new Form_Input(
	'radomainsearchlist',
	gettext('Domain Search List'),
	'text',
	$pconfig['radomainsearchlist']
))->addClass('autotrim')
  ->setAttribute('placeholder', 'example.com;sub.example.com')
  ->setHelp(gettext('The RA Daemon can optionally provide a domain search list. Use the semicolon character as separator.'));

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

	$('#radns1, #radns2, #radns3, #radns4').autocomplete({
		source: addressarray
	});

});
//]]>
</script>

<?php
include("foot.inc");
