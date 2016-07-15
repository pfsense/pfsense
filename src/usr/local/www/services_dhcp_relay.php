<?php
/*
 * services_dhcp_relay.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
 * Copyright (c) 2003-2004 Justin Ellison <justin@techadvise.com>
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
##|*IDENT=page-services-dhcprelay
##|*NAME=Services: DHCP Relay
##|*DESCR=Allow access to the 'Services: DHCP Relay' page.
##|*MATCH=services_dhcp_relay.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("filter.inc");
$pconfig['enable'] = isset($config['dhcrelay']['enable']);

if (empty($config['dhcrelay']['interface'])) {
	$pconfig['interface'] = array();
} else {
	$pconfig['interface'] = explode(",", $config['dhcrelay']['interface']);
}

$pconfig['agentoption'] = isset($config['dhcrelay']['agentoption']);

$iflist = array_intersect_key(
	get_configured_interface_with_descr(),
	array_flip(
		array_filter(
			array_keys(get_configured_interface_with_descr()),
			function($if) {
				return is_ipaddr(get_interface_ip($if));
			}
		)
	)
);

/*   set the enabled flag which will tell us if DHCP server is enabled
 *   on any interface.   We will use this to disable dhcp-relay since
 *   the two are not compatible with each other.
 */
$dhcpd_enabled = false;
if (is_array($config['dhcpd'])) {
	foreach ($config['dhcpd'] as $dhcpif => $dhcp) {
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
				if (!is_ipaddr($srv[0])) {
					$input_errors[] = gettext("A valid Destination Server IP address must be specified.");
				}

				if (!empty($srv[0])) { // Filter out any empties
					if (!empty($svrlist)) {
						$svrlist .= ',';
					}

					$svrlist .= $srv[0];
				}
			}
		}
	}

	// Now $svrlist is a comma separated list of servers ready to save to the config system
	$pconfig['server'] = $svrlist;

	if (!$input_errors) {
		$config['dhcrelay']['enable'] = $_POST['enable'] ? true : false;
		$config['dhcrelay']['interface'] = implode(",", $_POST['interface']);
		$config['dhcrelay']['agentoption'] = $_POST['agentoption'] ? true : false;
		$config['dhcrelay']['server'] = $svrlist;

		write_config();

		$retval = 0;
		$retval = services_dhcrelay_configure();
		$savemsg = get_std_save_message($retval);
		filter_configure();
	}
}

$pconfig['server'] = $config['dhcrelay']['server'];

$pgtitle = array(gettext("Services"), gettext("DHCP Relay"));
$shortcut_section = "dhcp";
include("head.inc");

if ($dhcpd_enabled) {
	print_info_box(gettext("DHCP Server is currently enabled. Cannot enable the DHCP Relay service while the DHCP Server is enabled on any interface."), 'danger', false);
	include("foot.inc");
	exit;
}

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$form = new Form;

$section = new Form_Section('DHCP Relay Configuration');

$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	'Enable DHCP relay on interface',
	$pconfig['enable']
))->toggles('.form-group:not(:first-child)');

$section->addInput(new Form_Select(
	'interface',
	'Interface(s)',
	$pconfig['interface'],
	$iflist,
	true
))->setHelp('Interfaces without an IP address will not be shown.');

$section->addInput(new Form_Checkbox(
	'agentoption',
	'',
	'Append circuit ID and agent ID to requests',
	$pconfig['agentoption']
))->setHelp(
	'If this is checked, the DHCP relay will append the circuit ID (%s interface number) and the agent ID to the DHCP request.',
	[$g['product_name']]
);

//Small function to prevent duplicate code
function createDestinationServerInputGroup($value = null) {
	$group = new Form_Group('Destination server');

	$group->add(new Form_IpAddress(
		'server',
		'Destination server',
		$value
	))->setWidth(4)
	  ->setHelp('This is the IP address of the server to which DHCP requests are relayed.')
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
