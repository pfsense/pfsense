<?php
/*
	services_dhcp_relay.php

	Copyright (C) 2003-2004 Justin Ellison <justin@techadvise.com>.
	Copyright (C) 2010 	Ermal Luçi
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
/*
	pfSense_MODULE:	dhcprelay
*/

##|+PRIV
##|*IDENT=page-services-dhcprelay
##|*NAME=Services: DHCP Relay page
##|*DESCR=Allow access to the 'Services: DHCP Relay' page.
##|*MATCH=services_dhcp_relay.php*
##|-PRIV

require("guiconfig.inc");
require('classes/Form.class.php');

function filterDestinationServers(array $destinationServers)
{
	return array_unique(
		array_filter($destinationServers)
	);
}

$pconfig['enable'] = isset($config['dhcrelay']['enable']);

if (empty($config['dhcrelay']['interface'])) {
	$pconfig['interface'] = array();
} else {
	$pconfig['interface'] = explode(",", $config['dhcrelay']['interface']);
}

$pconfig['server'] = filterDestinationServers(
	explode(',', $config['dhcrelay']['server'])
);

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

	if ($_POST['server'])
		$_POST['server'] = filterDestinationServers($_POST['server']);

	$pconfig = $_POST;

	/* input validation */
	if ($_POST['enable']) {
		$reqdfields = explode(" ", "server interface");
		$reqdfieldsn = array(gettext("Destination Server"), gettext("Interface"));

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

		if ($_POST['server']) {
			$checksrv = explode(",", $_POST['server']);
			foreach ($checksrv as $srv) {
				if (!is_ipaddr($srv)) {
					$input_errors[] = gettext("A valid Destination Server IP address must be specified.");
				}
			}
		}
	}

	if (!$input_errors) {
		$config['dhcrelay']['enable'] = $_POST['enable'] ? true : false;
		$config['dhcrelay']['interface'] = implode(",", $_POST['interface']);
		$config['dhcrelay']['agentoption'] = $_POST['agentoption'] ? true : false;
		$config['dhcrelay']['server'] = implode(',', $_POST['server']);

		write_config();

		$retval = 0;
		$retval = services_dhcrelay_configure();
		$savemsg = get_std_save_message($retval);

	}
}

$closehead = false;
$pgtitle = array(gettext("Services"), gettext("DHCP Relay"));
$shortcut_section = "dhcp";
include("head.inc");

if ($dhcpd_enabled) {
	echo '<div class="alert alert-danger">DHCP Server is currently enabled. Cannot enable the DHCP Relay service while the DHCP Server is enabled on any interface.</div>';
	include("foot.inc");
	exit;
}

if ($input_errors)
	print_input_errors($input_errors);

if ($savemsg)
	print_info_box($savemsg);

$form = new Form;

$section = new Form_Section('DHCP Relay configuration');

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
	'yes',
	$pconfig['agentoption']
))->setHelp(
	'If this is checked, the DHCP relay will append the circuit ID (%s interface number) and the agent ID to the DHCP request.',
	[$g['product_name']]
);

//Small function to prevent duplicate code
function createDestinationServerInputGroup($value = null)
{
	$group = new Form_Group('Destination server');

	$group->add(new Form_IpAddress(
		'server',
		'Destination server',
		$value
	))->setWidth(4)->setHelp(
		'This is the IP address of the server to which DHCP requests are relayed.'
	)->setIsRepeated();
	
	$group->enableDuplication(null, true); // Buttons are in-line with the input
	return $group;
}

if (!isset($pconfig['server']) || count($pconfig['server']) < 1)
	$section->add(createDestinationServerInputGroup());
else
	foreach ($pconfig['server'] as $idx => $server)
		$section->add(createDestinationServerInputGroup($server));

$form->add($section);
print $form;

include("foot.inc");