<?php
/*
 * services_dhcpv6_relay.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Justin Ellison <justin@techadvise.com>
 * Copyright (c) 2010 Seth Mos
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
##|*IDENT=page-services-dhcpv6relay
##|*NAME=Services: DHCPv6 Relay
##|*DESCR=Allow access to the 'Services: DHCPv6 Relay' page.
##|*MATCH=services_dhcpv6_relay.php*
##|-PRIV

require_once("guiconfig.inc");

$pconfig['enable'] = isset($config['dhcrelay6']['enable']);

if (empty($config['dhcrelay6']['interface'])) {
	$pconfig['interface'] = array();
} else {
	$pconfig['interface'] = explode(",", $config['dhcrelay6']['interface']);
}

$pconfig['agentoption'] = isset($config['dhcrelay6']['agentoption']);
$pconfig['server'] = $config['dhcrelay6']['server'];

$iflist = array_intersect_key(
	get_configured_interface_with_descr(),
	array_flip(
		array_filter(
			array_keys(get_configured_interface_with_descr()),
			function($if) {
				return (get_interface_ipv6($if) &&
				    !is_pseudo_interface(convert_friendly_interface_to_real_interface_name($if)));
			}
		)
	)
);

/*   set the enabled flag which will tell us if DHCP server is enabled
 *   on any interface.   We will use this to disable dhcp-relay since
 *   the two are not compatible with each other.
 */
$dhcpd_enabled = false;
if (is_array($config['dhcpdv6'])) {
	foreach ($config['dhcpdv6'] as $dhcpif => $dhcp) {
		if (isset($dhcp['enable']) && isset($config['interfaces'][$dhcpif]['enable'])) {
			$dhcpd_enabled = true;
			break;
		}
	}
}

if ($_POST) {

	unset($input_errors);

	$pconfig = $_POST;

	/* input validation */
	if ($_POST['enable']) {
		$reqdfields = explode(" ", "server interface");
		$reqdfieldsn = array(gettext("Destination Server"), gettext("Interface"));

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

		$svrlist = '';

		if ($_POST['server']) {
			foreach ($_POST['server'] as $checksrv => $srv) {
				if (!empty($srv[0])) { // Filter out any empties
					if (!is_ipaddrv6($srv[0])) {
						$input_errors[] = sprintf(gettext("Destination Server IP address %s is not a valid IPv6 address."), $srv[0]);
					}

					if (!empty($svrlist)) {
						$svrlist .= ',';
					}

					$svrlist .= $srv[0];
				}
			}

			// Check that the user input something in one of the Destination Server fields
			if (empty($svrlist)) {
				$input_errors[] = gettext("At least one Destination Server IP address must be specified.");
			}
		}
	}

	// Now $svrlist is a comma separated list of servers ready to save to the config system
	$pconfig['server'] = $svrlist;

	if (!$input_errors) {
		init_config_arr(array('dhcrelay6'));
		$config['dhcrelay6']['enable'] = $_POST['enable'] ? true : false;
		$config['dhcrelay6']['interface'] = implode(",", $_POST['interface']);
		$config['dhcrelay6']['agentoption'] = $_POST['agentoption'] ? true : false;
		$config['dhcrelay6']['server'] = $svrlist;

		write_config("DHCPv6 Relay settings saved");

		$changes_applied = true;
		$retval = 0;
		$retval |= services_dhcrelay6_configure();
	}
}

$pgtitle = array(gettext("Services"), gettext("DHCPv6 Relay"));
$shortcut_section = "dhcp6";
include("head.inc");

if ($dhcpd_enabled) {
	print_info_box(gettext("DHCPv6 Server is currently enabled. Cannot enable the DHCPv6 Relay service while the DHCPv6 Server is enabled on any interface."), 'danger', false);
	include("foot.inc");
	exit;
}

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($changes_applied) {
	print_apply_result_box($retval);
}

$form = new Form;

$section = new Form_Section('DHCPv6 Relay Configuration');

$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	'Enable DHCPv6 relay on interface',
	$pconfig['enable']
))->toggles('.form-group:not(:first-child)');

$section->addInput(new Form_Select(
	'interface',
	'*Interface(s)',
	$pconfig['interface'],
	$iflist,
	true
))->setHelp('Interfaces without an IPv6 address will not be shown.');


$section->addInput(new Form_Checkbox(
	'agentoption',
	'',
	'Append circuit ID and agent ID to requests',
	$pconfig['agentoption']
))->setHelp(
	'If this is checked, the DHCPv6 relay will append the circuit ID (%s interface number) and the agent ID to the DHCPv6 request.',
	$g['product_label']
);

function createDestinationServerInputGroup($value = null) {
	$group = new Form_Group('*Destination server');

	$group->add(new Form_IpAddress(
		'server',
		'Destination server',
		$value,
		'V6'
	))->setWidth(4)
	  ->setHelp('This is the IPv6 address of the server to which DHCPv6 requests are relayed.')
	  ->setIsRepeated();

	$group->enableDuplication(null, true); // Buttons are in-line with the input

	return $group;
}

if (!isset($pconfig['server'])) {
	$section->add(createDestinationServerInputGroup());
} else {
	foreach (explode(',', $pconfig['server']) as $server) {
		$section->add(createDestinationServerInputGroup($server));
	}
}

$form->add($section);
print $form;

include("foot.inc");
