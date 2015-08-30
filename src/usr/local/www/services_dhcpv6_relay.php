<?php
/*
	services_dhcpv6_relay.php

	Copyright (C) 2003-2004 Justin Ellison <justin@techadvise.com>.
	Copyright (C) 2010 	Ermal Luçi
	Copyright (C) 2010 	Seth Mos
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
	pfSense_MODULE:	dhcpv6relay
*/

##|+PRIV
##|*IDENT=page-services-dhcpv6relay
##|*NAME=Services: DHCPv6 Relay page
##|*DESCR=Allow access to the 'Services: DHCPv6 Relay' page.
##|*MATCH=services_dhcpv6_relay.php*
##|-PRIV

require("guiconfig.inc");
require_once('classes/Form.class.php');

function filterDestinationServers(array $destinationServers)
{
	return array_unique(
		array_filter($destinationServers)
	);
}

$pconfig['enable'] = isset($config['dhcrelay6']['enable']);
if (empty($config['dhcrelay6']['interface'])) {
	$pconfig['interface'] = array();
} else {
	$pconfig['interface'] = explode(",", $config['dhcrelay6']['interface']);
}

$pconfig['server'] = filterDestinationServers(
	explode(',', $config['dhcrelay6']['server'])
);

$pconfig['agentoption'] = isset($config['dhcrelay6']['agentoption']);

$iflist = array_intersect_key(
	get_configured_interface_with_descr(),
	array_flip(
		array_filter(
			array_keys(get_configured_interface_with_descr()),
			function($if) {
				return is_ipaddrv6(get_interface_ipv6($if));
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
	foreach ($config['dhcpdv6'] as $dhcp) {
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
			foreach ($_POST['server'] as $srv) {
				if (!is_ipaddrv6($srv))
					$input_errors[] = gettext("A valid Destination Server IPv6 address  must be specified.");
			}
		}
	}

	if (!$input_errors) {
		$config['dhcrelay6']['enable'] = $_POST['enable'] ? true : false;
		$config['dhcrelay6']['interface'] = implode(",", $_POST['interface']);
		$config['dhcrelay6']['agentoption'] = $_POST['agentoption'] ? true : false;
		$config['dhcrelay6']['server'] = $_POST['server'];

		write_config();

		$retval = 0;
		$retval = services_dhcrelay6_configure();
		$savemsg = get_std_save_message($retval);
	}
}

$closehead = false;
$pgtitle = array(gettext("Services"), gettext("DHCPv6 Relay"));
$shortcut_section = "dhcp6";
include("head.inc");

if ($dhcpd_enabled)
{
	echo '<div class="alert alert-danger">DHCPv6 Server is currently enabled. Cannot enable the DHCPv6 Relay service while the DHCPv6 Server is enabled on any interface.</div>';
	include("foot.inc");
	exit;
}

if ($input_errors)
	print_input_errors($input_errors);

if ($savemsg)
	print_info_box($savemsg);

$form = new Form;

$section = new Form_Section('DHCPv6 Relay configuration');

$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	'Enable DHCPv6 relay on interface',
	$pconfig['enable']
))->toggles('.form-group:not(:first-child)');

$section->addInput(new Form_Select(
	'interface',
	'Interface(s)',
	$pconfig['interface'],
	$iflist,
	true
))->setHelp('Interfaces without an IPv6 address will not be shown.');


$section->addInput(new Form_Checkbox(
	'agentoption',
	'',
	'Append circuit ID and agent ID to requests',
	'yes',
	$pconfig['agentoption']
))->setHelp(
	'If this is checked, the DHCPv6 relay will append the circuit ID (%s interface number) and the agent ID to the DHCPv6 request.',
	[$g['product_name']]
);

function createDestinationServerInputGroup($value = null)
{
	$group = new Form_Group('Destination server');
	$group->enableDuplication();

	$group->add(new Form_IpAddress(
		'server',
		'Destination server',
		$value
	))->setHelp(
		'This is the IPv6 address of the server to which DHCPv6 requests are relayed.'
	)->setIsRepeated();

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
