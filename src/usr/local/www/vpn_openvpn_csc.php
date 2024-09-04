<?php
/*
 * vpn_openvpn_csc.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008 Shrew Soft Inc.
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
##|*IDENT=page-openvpn-csc
##|*NAME=OpenVPN: Client Specific Override
##|*DESCR=Allow access to the 'OpenVPN: Client Specific Override' page.
##|*MATCH=vpn_openvpn_csc.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("openvpn.inc");
require_once("pfsense-utils.inc");
require_once("pkg-utils.inc");

global $openvpn_tls_server_modes, $openvpn_ping_action;

config_init_path('openvpn/openvpn-csc');
config_init_path('openvpn/openvpn-server');

$serveroptionlist = array();
foreach (config_get_path('openvpn/openvpn-server', []) as $serversettings) {
	if (in_array($serversettings['mode'], $openvpn_tls_server_modes)) {
		$serveroptionlist[$serversettings['vpnid']] = sprintf(gettext("OpenVPN Server %d: %s"), $serversettings['vpnid'], $serversettings['description']);
	}
}

if (isset($_REQUEST['id']) && is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

if (isset($_REQUEST['act'])) {
	$act = $_REQUEST['act'];
}

$user_entry = getUserEntry($_SESSION['Username']);
$user_entry = $user_entry['item'];
$user_can_edit_advanced = (isAdminUID($_SESSION['Username']) || userHasPrivilege($user_entry, "page-openvpn-csc-advanced") || userHasPrivilege($user_entry, "page-all"));

$this_csc_config = isset($id) ? config_get_path("openvpn/openvpn-csc/{$id}") : null;

if ($_POST['act'] == "del") {
	if (!$this_csc_config) {
		pfSenseHeader("vpn_openvpn_csc.php");
		exit;
	}

	if (!$user_can_edit_advanced && !empty($this_csc_config['custom_options'])) {
		$input_errors[] = gettext("This user does not have sufficient privileges to delete an instance with Advanced options set.");
	} else {
		$wc_msg = sprintf(gettext('Deleted OpenVPN client specific override %1$s %2$s'), $this_csc_config['common_name'], $this_csc_config['description']);
		openvpn_delete_csc($this_csc_config);
		config_del_path("openvpn/openvpn-csc/{$id}");
		write_config($wc_msg);
		$savemsg = gettext("Client specific override successfully deleted.");
		services_unbound_configure(false);
	}
}

if (($act == "edit") || ($act == "dup")) {
	if ($this_csc_config) {
		$pconfig['keep_minimal'] = isset($this_csc_config['keep_minimal']);
		// Handle the "Reset Options" list
		if (!empty($this_csc_config['remove_options'])) {
			$pconfig['override_options'] = 'remove_specified';
			$pconfig['remove_options'] = explode(',', $this_csc_config['remove_options']);
		} elseif (isset($this_csc_config['push_reset'])) {
			$pconfig['override_options'] = 'push_reset';
		}

		$pconfig['server_list'] = array_filter(explode(",", $this_csc_config['server_list']));
		$pconfig['custom_options'] = $this_csc_config['custom_options'];
		$pconfig['disable'] = isset($this_csc_config['disable']);
		$pconfig['common_name'] = $this_csc_config['common_name'];
		$pconfig['block'] = $this_csc_config['block'];
		$pconfig['description'] = $this_csc_config['description'];

		$pconfig['tunnel_network'] = $this_csc_config['tunnel_network'];
		$pconfig['tunnel_networkv6'] = $this_csc_config['tunnel_networkv6'];
		$pconfig['local_network'] = $this_csc_config['local_network'];
		$pconfig['local_networkv6'] = $this_csc_config['local_networkv6'];
		$pconfig['gateway'] = $this_csc_config['gateway'];
		$pconfig['gateway6'] = $this_csc_config['gateway6'];
		$pconfig['remote_network'] = $this_csc_config['remote_network'];
		$pconfig['remote_networkv6'] = $this_csc_config['remote_networkv6'];
		$pconfig['gwredir'] = $this_csc_config['gwredir'];
		$pconfig['gwredir6'] = $this_csc_config['gwredir6'];

		$pconfig['inactive_seconds'] = $this_csc_config['inactive_seconds'];
		$pconfig['ping_seconds'] = $this_csc_config['ping_seconds'];
		$pconfig['ping_action'] = $this_csc_config['ping_action'];
		$pconfig['ping_action_seconds'] = $this_csc_config['ping_action_seconds'];

		$pconfig['dns_domain'] = $this_csc_config['dns_domain'];
		if ($pconfig['dns_domain']) {
			$pconfig['dns_domain_enable'] = true;
		}

		$pconfig['dns_server1'] = $this_csc_config['dns_server1'];
		$pconfig['dns_server2'] = $this_csc_config['dns_server2'];
		$pconfig['dns_server3'] = $this_csc_config['dns_server3'];
		$pconfig['dns_server4'] = $this_csc_config['dns_server4'];

		if ($pconfig['dns_server1'] ||
		    $pconfig['dns_server2'] ||
		    $pconfig['dns_server3'] ||
		    $pconfig['dns_server4']) {
			$pconfig['dns_server_enable'] = true;
		}

		$pconfig['push_blockoutsidedns'] = $this_csc_config['push_blockoutsidedns'];
		$pconfig['push_register_dns'] = $this_csc_config['push_register_dns'];

		$pconfig['ntp_server1'] = $this_csc_config['ntp_server1'];
		$pconfig['ntp_server2'] = $this_csc_config['ntp_server2'];

		if ($pconfig['ntp_server1'] ||
		    $pconfig['ntp_server2']) {
			$pconfig['ntp_server_enable'] = true;
		}

		$pconfig['netbios_enable'] = $this_csc_config['netbios_enable'];
		$pconfig['netbios_ntype'] = $this_csc_config['netbios_ntype'];
		$pconfig['netbios_scope'] = $this_csc_config['netbios_scope'];

		$pconfig['wins_server1'] = $this_csc_config['wins_server1'];
		$pconfig['wins_server2'] = $this_csc_config['wins_server2'];

		if ($pconfig['wins_server1'] ||
		    $pconfig['wins_server2']) {
			$pconfig['wins_server_enable'] = true;
		}

		$pconfig['nbdd_server1'] = $this_csc_config['nbdd_server1'];
		$pconfig['nbdd_server2'] = $this_csc_config['nbdd_server2'];

		if ($pconfig['nbdd_server1'] || $pconfig['nbdd_server2']) {
			$pconfig['nbdd_server_enable'] = true;
		}
	}
}

if ($act == "dup") {
	$act = "new";
	unset($id);
}

if ($_POST['save']) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if (isset($pconfig['custom_options']) &&
	    ($pconfig['custom_options'] != $this_csc_config['custom_options']) &&
	    !$user_can_edit_advanced) {
		$input_errors[] = gettext("This user does not have sufficient privileges to edit Advanced options on this instance.");
	}
	if (!$user_can_edit_advanced && !empty($this_csc_config['custom_options'])) {
		// Restore the "custom options" field
		$pconfig['custom_options'] = $this_csc_config['custom_options'];
	}

	if (!empty($pconfig['server_list'])) {
		if (is_array($pconfig['server_list'])) {
			foreach ($pconfig['server_list'] as $server) {
				if (!array_key_exists(trim($server), $serveroptionlist)) {
					$input_errors[] = gettext("The server list contains an invalid entry.");
				}
			}
		} else {
			$input_errors[] = gettext("The server list is invalid");
		}
	}

	if (!empty($pconfig['tunnel_network']) && !openvpn_validate_tunnel_network($pconfig['tunnel_network'], 'ipv4')) {
		$input_errors[] = gettext("The field 'IPv4 Tunnel Network' must contain a valid IPv4 subnet with CIDR mask or an alias with a single IPv4 subnet with CIDR mask.");
	}

	if (!empty($pconfig['tunnel_networkv6']) && !openvpn_validate_tunnel_network($pconfig['tunnel_networkv6'], 'ipv6')) {
		$input_errors[] = gettext("The field 'IPv6 Tunnel Network' must contain a valid IPv6 prefix or an alias with a single IPv6 prefix.");
	}

	if (empty($pconfig['gwredir']) && ($result = openvpn_validate_cidr($pconfig['local_network'], 'IPv4 Local Network', true, "ipv4", true))) {
		$input_errors[] = $result;
	}

	if (empty($pconfig['gwredir6']) && ($result = openvpn_validate_cidr($pconfig['local_networkv6'], 'IPv6 Local Network', true, "ipv6", true))) {
		$input_errors[] = $result;
	}

	if (!empty($pconfig['gateway']) && !is_ipaddrv4($pconfig['gateway'])) {
		$input_errors[] = gettext("The specified IPv4 gateway address is invalid.");
	}

	if (!empty($pconfig['gateway6']) && !is_ipaddrv6($pconfig['gateway6'])) {
		$input_errors[] = gettext("The specified IPv6 gateway address is invalid.");
	}

	if ($result = openvpn_validate_cidr($pconfig['remote_network'], 'IPv4 Remote Network', true, "ipv4", true)) {
		$input_errors[] = $result;
	}

	if ($result = openvpn_validate_cidr($pconfig['remote_networkv6'], 'IPv6 Remote Network', true, "ipv6", true)) {
		$input_errors[] = $result;
	}

	if (!empty($pconfig['inactive_seconds']) && !is_numericint($pconfig['inactive_seconds'])) {
		$input_errors[] = gettext('The supplied "Inactivity Timeout" value is invalid.');
	}

	if (!empty($pconfig['ping_seconds']) && !is_numericint($pconfig['ping_seconds'])) {
		$input_errors[] = gettext('The supplied "Ping Interval" value is invalid.');
	}
	if (!empty($pconfig['ping_action']) && ($pconfig['ping_action'] != 'default')) {
		if (!isset($openvpn_ping_action[$pconfig['ping_action']])) {
			$input_errors[] = gettext('The field "Ping Action" contains an invalid selection.');
		}
		if (!is_numericint($pconfig['ping_action_seconds'])) {
			$input_errors[] = gettext('The supplied "Ping Action" timeout value is invalid.');
		}
	}

	if ($pconfig['dns_server_enable']) {
		if (!empty($pconfig['dns_server1']) && !is_ipaddr(trim($pconfig['dns_server1']))) {
			$input_errors[] = gettext("The field 'DNS Server #1' must contain a valid IP address");
		}
		if (!empty($pconfig['dns_server2']) && !is_ipaddr(trim($pconfig['dns_server2']))) {
			$input_errors[] = gettext("The field 'DNS Server #2' must contain a valid IP address");
		}
		if (!empty($pconfig['dns_server3']) && !is_ipaddr(trim($pconfig['dns_server3']))) {
			$input_errors[] = gettext("The field 'DNS Server #3' must contain a valid IP address");
		}
		if (!empty($pconfig['dns_server4']) && !is_ipaddr(trim($pconfig['dns_server4']))) {
			$input_errors[] = gettext("The field 'DNS Server #4' must contain a valid IP address");
		}
	}

	if ($pconfig['ntp_server_enable']) {
		if (!empty($pconfig['ntp_server1']) && !is_ipaddr(trim($pconfig['ntp_server1']))) {
			$input_errors[] = gettext("The field 'NTP Server #1' must contain a valid IP address");
		}
		if (!empty($pconfig['ntp_server2']) && !is_ipaddr(trim($pconfig['ntp_server2']))) {
			$input_errors[] = gettext("The field 'NTP Server #2' must contain a valid IP address");
		}
		if (!empty($pconfig['ntp_server3']) && !is_ipaddr(trim($pconfig['ntp_server3']))) {
			$input_errors[] = gettext("The field 'NTP Server #3' must contain a valid IP address");
		}
		if (!empty($pconfig['ntp_server4']) && !is_ipaddr(trim($pconfig['ntp_server4']))) {
			$input_errors[] = gettext("The field 'NTP Server #4' must contain a valid IP address");
		}
	}

	if ($pconfig['netbios_enable']) {
		if ($pconfig['wins_server_enable']) {
			if (!empty($pconfig['wins_server1']) && !is_ipaddr(trim($pconfig['wins_server1']))) {
				$input_errors[] = gettext("The field 'WINS Server #1' must contain a valid IP address");
			}
			if (!empty($pconfig['wins_server2']) && !is_ipaddr(trim($pconfig['wins_server2']))) {
				$input_errors[] = gettext("The field 'WINS Server #2' must contain a valid IP address");
			}
		}
		if ($pconfig['nbdd_server_enable']) {
			if (!empty($pconfig['nbdd_server1']) && !is_ipaddr(trim($pconfig['nbdd_server1']))) {
				$input_errors[] = gettext("The field 'NetBIOS Data Distribution Server #1' must contain a valid IP address");
			}
			if (!empty($pconfig['nbdd_server2']) && !is_ipaddr(trim($pconfig['nbdd_server2']))) {
				$input_errors[] = gettext("The field 'NetBIOS Data Distribution Server #2' must contain a valid IP address");
			}
		}

		if (!empty($pconfig['netbios_ntype']) &&
		    !array_key_exists($pconfig['netbios_ntype'], $netbios_nodetypes)) {
			$input_errors[] = gettext("The selected NetBIOS Node Type is not valid.");
		}
	}

	$reqdfields[] = 'common_name';
	$reqdfieldsn[] = 'Common name';

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!$input_errors) {
		$csc = array();

		// Handle "Reset Server Options" and "Reset Options"
		if ($pconfig['override_options'] == 'push_reset') {
			$csc['push_reset'] = true;
		} elseif (($pconfig['override_options'] == 'remove_specified') && !empty($pconfig['remove_options'])) {
			$csc['remove_options'] = implode(',', $pconfig['remove_options']);
		}
		if (isset($pconfig['keep_minimal']) && (isset($csc['push_reset']) || isset($csc['remove_options']))) {
			$csc['keep_minimal'] = true;
		}

		if (is_array($pconfig['server_list'])) {
			$csc['server_list'] = implode(",", $pconfig['server_list']);
		} else {
			$csc['server_list'] = "";
		}
		$csc['custom_options'] = $pconfig['custom_options'];
		if ($_POST['disable'] == "yes") {
			$csc['disable'] = true;
		}
		$csc['common_name'] = $pconfig['common_name'];
		$csc['block'] = $pconfig['block'];
		$csc['description'] = $pconfig['description'];
		$csc['tunnel_network'] = $pconfig['tunnel_network'];
		$csc['tunnel_networkv6'] = $pconfig['tunnel_networkv6'];

		$csc['gateway'] = $pconfig['gateway'];
		$csc['gateway6'] = $pconfig['gateway6'];
		// Don't push routes if redirecting all traffic.
		if (!empty($pconfig['gwredir'])) {
			$csc['gwredir'] = $pconfig['gwredir'];
		} else {
			$csc['local_network'] = $pconfig['local_network'];
		}
		if (!empty($pconfig['gwredir6'])) {
			$csc['gwredir6'] = $pconfig['gwredir6'];
		} else {
			$csc['local_networkv6'] = $pconfig['local_networkv6'];
		}

		$csc['remote_network'] = $pconfig['remote_network'];
		$csc['remote_networkv6'] = $pconfig['remote_networkv6'];

		if (is_numericint($pconfig['inactive_seconds'])) {
			$csc['inactive_seconds'] = $pconfig['inactive_seconds'];
		}
		if (is_numericint($pconfig['ping_seconds'])) {
			$csc['ping_seconds'] = $pconfig['ping_seconds'];
		}
		if (!empty($pconfig['ping_action']) && ($pconfig['ping_action'] != 'default')) {
			$csc['ping_action'] = $pconfig['ping_action'];
			$csc['ping_action_seconds'] = $pconfig['ping_action_seconds'];
		}

		if ($pconfig['dns_domain_enable']) {
			$csc['dns_domain'] = $pconfig['dns_domain'];
		}

		if ($pconfig['dns_server_enable']) {
			$csc['dns_server1'] = $pconfig['dns_server1'];
			$csc['dns_server2'] = $pconfig['dns_server2'];
			$csc['dns_server3'] = $pconfig['dns_server3'];
			$csc['dns_server4'] = $pconfig['dns_server4'];
		}

		$csc['push_blockoutsidedns'] = $pconfig['push_blockoutsidedns'];
		$csc['push_register_dns'] = $pconfig['push_register_dns'];

		if ($pconfig['ntp_server_enable']) {
			$csc['ntp_server1'] = $pconfig['ntp_server1'];
			$csc['ntp_server2'] = $pconfig['ntp_server2'];
		}

		$csc['netbios_enable'] = $pconfig['netbios_enable'];

		if ($pconfig['netbios_enable']) {
			$csc['netbios_ntype'] = $pconfig['netbios_ntype'];
			$csc['netbios_scope'] = $pconfig['netbios_scope'];

			if ($pconfig['wins_server_enable']) {
				$csc['wins_server1'] = $pconfig['wins_server1'];
				$csc['wins_server2'] = $pconfig['wins_server2'];
			}

			if ($pconfig['nbdd_server_enable']) {
				$csc['nbdd_server1'] = $pconfig['nbdd_server1'];
				$csc['nbdd_server2'] = $pconfig['nbdd_server2'];
			}
		}

		if (($act == 'new') || (!empty($csc['disable']) ^ !empty($this_csc_config['disable'])) ||
		    ($csc['tunnel_network'] != $this_csc_config['tunnel_network']) ||
		    ($csc['tunnel_networkv6'] != $this_csc_config['tunnel_networkv6'])) {
			$csc['unbound_restart'] = true;
		}

		if ($this_csc_config) {
			$old_csc = $this_csc_config;
			config_set_path("openvpn/openvpn-csc/{$id}", $csc);
			$wc_msg = sprintf(gettext('Updated OpenVPN client specific override %1$s %2$s'), $csc['common_name'], $csc['description']);
		} else {
			config_set_path('openvpn/openvpn-csc/', $csc);
			$wc_msg = sprintf(gettext('Added OpenVPN client specific override %1$s %2$s'), $csc['common_name'], $csc['description']);
		}

		if (!empty($old_csc['common_name'])) {
			openvpn_delete_csc($old_csc);
		}
		openvpn_resync_csc($csc);
		write_config($wc_msg);
		services_unbound_configure(false);

		header("Location: vpn_openvpn_csc.php");
		exit;
	}
}

$pgtitle = array(gettext("VPN"), gettext("OpenVPN"), gettext("Client Specific Overrides"));
$pglinks = array("", "vpn_openvpn_server.php", "vpn_openvpn_csc.php");

if ($act=="new" || $act=="edit") {
	$pgtitle[] = gettext('Edit');
	$pglinks[] = "@self";
}
$shortcut_section = "openvpn";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
$tab_array[] = array(gettext("Servers"), false, "vpn_openvpn_server.php");
$tab_array[] = array(gettext("Clients"), false, "vpn_openvpn_client.php");
$tab_array[] = array(gettext("Client Specific Overrides"), true, "vpn_openvpn_csc.php");
$tab_array[] = array(gettext("Wizards"), false, "wizard.php?xml=openvpn_wizard.xml");
add_package_tabs("OpenVPN", $tab_array);
display_top_tabs($tab_array);

if ($act == "new" || $act == "edit"):
	$form = new Form();

	$section = new Form_Section('General Information');

	$section->addInput(new Form_Input(
		'description',
		'Description',
		'text',
		$pconfig['description']
	))->setHelp('A description of this override for administrative reference.');

	$section->addInput(new Form_Checkbox(
		'disable',
		'Disable',
		'Disable this override',
		$pconfig['disable']
	))->setHelp('Set this option to disable this client-specific override without removing it from the list.');

	$form->add($section);

	$section = new Form_Section('Override Configuration');

	$section->addInput(new Form_Input(
		'common_name',
		'*Common Name',
		'text',
		$pconfig['common_name']
	))->setHelp('Enter the X.509 common name for the client certificate, or the username for VPNs utilizing password authentication. This match is case sensitive. Enter "DEFAULT" to override default client behavior.');

	$section->addInput(new Form_Checkbox(
		'block',
		'Connection blocking',
		'Block this client connection based on its common name.',
		$pconfig['block']
	))->setHelp('Prevents the client from connecting to this server. Do not use this option to permanently disable a client due to a compromised key or password. Use a CRL (certificate revocation list) instead.');

	$section->addInput(new Form_Select(
		'server_list',
		'Server List',
		$pconfig['server_list'],
		$serveroptionlist,
		true
		))->setHelp('Select the servers that will utilize this override. When no servers are selected, the override will apply to all servers.');


	$section->addInput(new Form_Select(
		'override_options',
		'Reset Server Options',
		($pconfig['override_options'] ?? 'default'),
		[
			'default' => 'Keep all server options (default)',
			'push_reset' => 'Reset all options',
			'remove_specified' => 'Remove specified options'
		]
	))->setHelp('Prevent this client from receiving server-defined client settings. Other client-specific options on this page will supersede these options.');

	$section->addInput(new Form_Select(
		'remove_options',
		'Remove Options',
		$pconfig['remove_options'],
		[
			'remove_route' => 'Local Routes & Gateways',
			'remove_iroute' => 'Remote Routes',
			'remove_redirect_gateway' => 'Redirect Gateways',
			'remove_inactive' => 'Inactivity Timeout',
			'remove_ping' => 'Client Ping',
			'remove_ping_action' => 'Ping Action',
			'remove_dnsdomain' => 'DNS Domains',
			'remove_dnsservers' => 'DNS Servers',
			'remove_blockoutsidedns' => 'Block Outside DNS',
			'remove_ntpservers' => 'NTP Options',
			'remove_netbios_ntype' => 'NetBIOS Type',
			'remove_netbios_scope' => 'NetBIOS Scope',
			'remove_wins' => 'WINS Options'
		],
		true
	))->addClass('remove_options')->setHelp('A "push-remove" option will be sent to the client for the selected options, removing the respective server-defined option.');

	$section->addInput(new Form_Checkbox(
		'keep_minimal',
		'Keep minimal options',
		'Automatically determine the client topology and gateway',
		$pconfig['keep_minimal']
	))->setHelp('If checked, generate the required client configuration when server options are reset or removed.');

	$form->add($section);

	$section = new Form_Section('Tunnel Settings');

	$section->addInput(new Form_Input(
		'tunnel_network',
		'IPv4 Tunnel Network',
		'text',
		$pconfig['tunnel_network']
	))->setHelp('The virtual IPv4 network or network type alias with a single entry used for private communications between this client and the server expressed using CIDR (e.g. 10.0.8.5/24). %1$s' .
		    'With subnet topology, enter the client IP address and the subnet mask must match the IPv4 Tunnel Network on the server. %1$s' .
		    'With net30 topology, the first network address of the /30 is assumed to be the server address and the second network address will be assigned to the client.',
			'<br />');

	$section->addInput(new Form_Input(
		'tunnel_networkv6',
		'IPv6 Tunnel Network',
		'text',
		$pconfig['tunnel_networkv6']
	))->setHelp('The virtual IPv6 network or network type alias with a single entry used for private communications between this client and the server expressed using prefix (e.g. 2001:db9:1:1::100/64). %1$s' .
		    'Enter the client IPv6 address and prefix. The prefix must match the IPv6 Tunnel Network prefix on the server. ',
			'<br />');

	$section->addInput(new Form_Input(
		'gateway',
		'IPv4 Gateway',
		'text',
		$pconfig['gateway']
	))->setHelp('This is the IPv4 Gateway to push to the client. Normally it is left blank and determined automatically.');

	$section->addInput(new Form_Input(
		'gateway6',
		'IPv6 Gateway',
		'text',
		$pconfig['gateway6']
	))->setHelp('This is the IPv6 Gateway to push to the client. Normally it is left blank and determined automatically.');

	$section->addInput(new Form_Checkbox(
		'gwredir',
		'Redirect IPv4 Gateway',
		'Force all client generated IPv4 traffic through the tunnel.',
		$pconfig['gwredir']
	));

	$section->addInput(new Form_Checkbox(
		'gwredir6',
		'Redirect IPv6 Gateway',
		'Force all client-generated IPv6 traffic through the tunnel.',
		$pconfig['gwredir6']
	));

	$section->addInput(new Form_Input(
		'local_network',
		'IPv4 Local Network/s',
		'text',
		$pconfig['local_network']
	))->setHelp('These are the IPv4 server-side networks that will be accessible from this particular client. Expressed as a comma-separated list of one or more CIDR ranges or host/network type aliases. %1$s' .
		    'NOTE: Networks do not need to be specified here if they have already been defined on the main server configuration.',
			'<br />');

	$section->addInput(new Form_Input(
		'local_networkv6',
		'IPv6 Local Network/s',
		'text',
		$pconfig['local_networkv6']
	))->setHelp('These are the IPv6 server-side networks that will be accessible from this particular client. Expressed as a comma-separated list of one or more IP/PREFIX networks.%1$s' .
		    'NOTE: Networks do not need to be specified here if they have already been defined on the main server configuration.',
			'<br />');

	$section->addInput(new Form_Input(
		'remote_network',
		'IPv4 Remote Network/s',
		'text',
		$pconfig['remote_network']
	))->setHelp('These are the IPv4 client-side networks that will be routed to this client specifically using iroute, so that a site-to-site VPN can be established. ' .
		    'Expressed as a comma-separated list of one or more CIDR ranges. May be left blank if there are no client-side networks to be routed.%1$s' .
		    'NOTE: Remember to add these subnets to the IPv4 Remote Networks list on the corresponding OpenVPN server settings.',
			'<br />');

	$section->addInput(new Form_Input(
		'remote_networkv6',
		'IPv6 Remote Network/s',
		'text',
		$pconfig['remote_networkv6']
	))->setHelp('These are the IPv6 client-side networks that will be routed to this client specifically using iroute, so that a site-to-site VPN can be established. ' .
		    'Expressed as a comma-separated list of one or more IP/PREFIX networks. May be left blank if there are no client-side networks to be routed.%1$s' .
		    'NOTE: Remember to add these subnets to the IPv6 Remote Networks list on the corresponding OpenVPN server settings.',
			'<br />');

	$form->add($section);

	$section = new Form_Section('Other Client Settings');

	$section->addInput(new Form_Input(
		'inactive_seconds',
		'Inactivity Timeout',
		'number',
		$pconfig['inactive_seconds'],
		['min' => '0']
	))->setHelp('Set connection inactivity timeout')->setWidth(3);

	$section->addInput(new Form_Input(
		'ping_seconds',
		'Ping Interval',
		'number',
		$pconfig['ping_seconds'],
		['min' => '0']
	))->setHelp('Set peer ping interval')->setWidth(3);

	$group = new Form_Group('Ping Action');
	$group->add(new Form_Select(
		'ping_action',
		null,
		$pconfig['ping_action'] ?? 'default',
		array_merge([
			'default' => 'Don\'t override option (default)'
		], $openvpn_ping_action)
	))->setHelp('Exit or restart OpenVPN client after server timeout')->setWidth(4);
	$group->add(new Form_Input(
		'ping_action_seconds',
		'timeout seconds',
		'number',
		$pconfig['ping_action_seconds'],
		['min' => '0']
	))->setWidth(2)->addClass('ping_action_seconds');
	$section->add($group);

	$section->addInput(new Form_Checkbox(
		'dns_domain_enable',
		'DNS Default Domain',
		'Provide a default domain name to clients',
		$pconfig['dns_domain_enable']
	));

	$group = new Form_Group('DNS Domain');
	$group->addClass('dnsdomain');

	$group->add(new Form_Input(
		'dns_domain',
		'DNS Domain',
		'text',
		$pconfig['dns_domain']
	));

	$section->add($group);

	// DNS servers
	$section->addInput(new Form_Checkbox(
		'dns_server_enable',
		'DNS Servers',
		'Provide a DNS server list to clients',
		$pconfig['dns_server_enable']
	));

	$group = new Form_Group(null);
	$group->addClass('dnsservers');

	$group->add(new Form_Input(
		'dns_server1',
		null,
		'text',
		$pconfig['dns_server1']
	))->setHelp('Server 1');

	$group->add(new Form_Input(
		'dns_server2',
		null,
		'text',
		$pconfig['dns_server2']
	))->setHelp('Server 2');

	$group->add(new Form_Input(
		'dns_server3',
		null,
		'text',
		$pconfig['dns_server3']
	))->setHelp('Server 3');

	$group->add(new Form_Input(
		'dns_server4',
		null,
		'text',
		$pconfig['dns_server4']
	))->setHelp('Server 4');

	$section->add($group);

	$section->addInput(new Form_Checkbox(
		'push_blockoutsidedns',
		'Block Outside DNS',
		'Make Windows 10 Clients Block access to DNS servers except across OpenVPN while connected, forcing clients to use only VPN DNS servers.',
		$pconfig['push_blockoutsidedns']
	))->setHelp('Requires Windows 10 and OpenVPN 2.3.9 or later. Only Windows 10 is prone to DNS leakage in this way, other clients will ignore the option as they are not affected.');

	$section->addInput(new Form_Checkbox(
		'push_register_dns',
		'Force DNS cache update',
		'Run "net stop dnscache", "net start dnscache", "ipconfig /flushdns" and "ipconfig /registerdns" on connection initiation.',
		$pconfig['push_register_dns']
	))->setHelp('This is known to kick Windows into recognizing pushed DNS servers.');

	// NTP servers
	$section->addInput(new Form_Checkbox(
		'ntp_server_enable',
		'NTP Servers',
		'Provide an NTP server list to clients',
		$pconfig['ntp_server_enable']
	));

	$group = new Form_Group(null);
	$group->addClass('ntpservers');

	$group->add(new Form_Input(
		'ntp_server1',
		null,
		'text',
		$pconfig['ntp_server1']
	))->setHelp('Server 1');

	$group->add(new Form_Input(
		'ntp_server2',
		null,
		'text',
		$pconfig['ntp_server2']
	))->setHelp('Server 2');

	$section->add($group);

	// NetBIOS - For this section we need to use JavaScript hiding since there
	// are nested toggles
	$section->addInput(new Form_Checkbox(
		'netbios_enable',
		'NetBIOS Options',
		'Enable NetBIOS over TCP/IP',
		$pconfig['netbios_enable']
	))->setHelp('If this option is not set, all NetBIOS-over-TCP/IP options (including WINS) will be disabled. ');

	$section->addInput(new Form_Select(
		'netbios_ntype',
		'Node Type',
		$pconfig['netbios_ntype'],
		$netbios_nodetypes
	))->setHelp('Possible options: b-node (broadcasts), p-node (point-to-point name queries to a WINS server), m-node (broadcast then query name server), ' .
				'and h-node (query name server, then broadcast). ');

	$section->addInput(new Form_Input(
		'netbios_scope',
		null,
		'text',
		$pconfig['netbios_scope']
	))->setHelp('A NetBIOS Scope ID provides an extended naming service for NetBIOS over TCP/IP. ' .
				'The NetBIOS scope ID isolates NetBIOS traffic on a single network to only those nodes with the same NetBIOS scope ID. ');

	$section->addInput(new Form_Checkbox(
		'wins_server_enable',
		'WINS servers',
		'Provide a WINS server list to clients',
		$pconfig['wins_server_enable']
	));

	$group = new Form_Group(null);

	$group->add(new Form_Input(
		'wins_server1',
		null,
		'text',
		$pconfig['wins_server1']
	))->setHelp('Server 1');

	$group->add(new Form_Input(
		'wins_server2',
		null,
		'text',
		$pconfig['wins_server2']
	))->setHelp('Server 2');

	$group->addClass('winsservers');

	$section->add($group);

	$section->addInput(new Form_Checkbox(
		'nbdd_server_enable',
		'NBDD servers',
		'Provide a NetBIOS over TCP/IP Datagram Distribution Servers list to clients',
		$pconfig['nbdd_server_enable']
	));

	$group = new Form_Group(null);

	$group->add(new Form_Input(
		'nbdd_server1',
		null,
		'text',
		$pconfig['nbdd_server1']
	))->setHelp('Server 1');

	$group->add(new Form_Input(
		'nbdd_server2',
		null,
		'text',
		$pconfig['nbdd_server2']
	))->setHelp('Server 2');

	$group->addClass('nbddservers');

	$section->add($group);

	$custops = new Form_Textarea(
		'custom_options',
		'Advanced',
		$pconfig['custom_options']
	);
	if (!$user_can_edit_advanced) {
		$custops->setDisabled();
	}
	$section->addInput($custops)->setHelp('Enter any additional options to add for this client specific override, separated by a semicolon. %1$s' .
				'EXAMPLE: push "route 10.0.0.0 255.255.255.0"; ',
				'<br />');

	// The hidden fields
	$form->addGlobal(new Form_Input(
		'act',
		null,
		'hidden',
		$act
	));

	if ($this_csc_config) {
		$form->addGlobal(new Form_Input(
			'id',
			null,
			'hidden',
			$id
		));
	}

	$form->add($section);
	print($form);

?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	function gwredir_change() {
		hideInput('local_network', ($('#gwredir').prop('checked')));
	}

	function gwredir6_change() {
		hideInput('local_networkv6', ($('#gwredir6').prop('checked')));
	}

	function ping_action_change() {
		hideClass('ping_action_seconds', ($('#ping_action').find('option:selected').val() == 'default'));
	}

	function dnsdomain_change() {
		if ($('#dns_domain_enable').prop('checked')) {
			hideClass('dnsdomain', false);
		} else {
			hideClass('dnsdomain', true);
		}
	}

	function dnsservers_change() {
		if ($('#dns_server_enable').prop('checked')) {
			hideClass('dnsservers', false);
		} else {
			hideClass('dnsservers', true);
		}
	}

	function ntpservers_change() {
		if ($('#ntp_server_enable').prop('checked')) {
			hideClass('ntpservers', false);
		} else {
			hideClass('ntpservers', true);
		}
	}

	// Hide/show that section, but have to also respect the wins_server_enable and nbdd_server_enable checkboxes
	function setNetbios() {
		if ($('#netbios_enable').prop('checked')) {
			hideInput('netbios_ntype', false);
			hideInput('netbios_scope', false);
			hideCheckbox('wins_server_enable', false);
			setWins();
			hideCheckbox('nbdd_server_enable', false);
			setNbdds();
		} else {
			hideInput('netbios_ntype', true);
			hideInput('netbios_scope', true);
			hideCheckbox('wins_server_enable', true);
			hideClass('winsservers', true);
			hideCheckbox('nbdd_server_enable', true);
			hideClass('nbddservers', true);
		}
	}

	function setWins() {
		hideClass('winsservers', ! $('#wins_server_enable').prop('checked'));
	}

	function setNbdds() {
		hideClass('nbddservers', ! $('#nbdd_server_enable').prop('checked'));
	}

	function remove_options_change() {
		hideCheckbox('keep_minimal', ($('#override_options').find('option:selected').val() == 'default'));
		hideMultiClass('remove_options', ($('#override_options').find('option:selected').val() != 'remove_specified'));
	}

	// ---------- Click checkbox handlers ---------------------------------------------------------

	 // On clicking Gateway redirect
	$('#gwredir').click(function () {
		gwredir_change();
	});

	 // On clicking Gateway redirect IPv6
	$('#gwredir6').click(function () {
		gwredir6_change();
	});

	 // On clicking Ping Action
	$('#ping_action').click(function () {
		ping_action_change();
	});

	 // On clicking DNS Default Domain
	$('#dns_domain_enable').click(function () {
		dnsdomain_change();
	});

	 // On clicking DNS Servers
	$('#dns_server_enable').click(function () {
		dnsservers_change();
	});

	 // On clicking NTP Servers
	$('#ntp_server_enable').click(function () {
		ntpservers_change();
	});

	// On clicking the netbios_enable checkbox
	$('#netbios_enable').click(function () {
		setNetbios();
	});

	// On clicking the wins_server_enable checkbox
	$('#wins_server_enable').click(function () {
		setWins();
	});

	// On clicking the nbdd_server_enable checkbox
	$('#nbdd_server_enable').click(function () {
		setNbdds();
	});

	$('#override_options').on('change', function() {
		remove_options_change();
	});
	// ---------- On initial page load ------------------------------------------------------------

	remove_options_change();
	gwredir_change();
	gwredir6_change();
	ping_action_change();
	setNetbios();
	dnsdomain_change();
	dnsservers_change();
	ntpservers_change();

});
//]]>
</script>

<?php
else :  // Not an 'add' or an 'edit'. Just the table of Override CSCs
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('CSC Overrides')?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap table-rowdblclickedit" data-sortable>
			<thead>
				<tr>
					<th><?=gettext("Disabled")?></th>
					<th><?=gettext("Common Name")?></th>
					<th><?=gettext("Description")?></th>
					<th><?=gettext("Actions")?></th>
				</tr>
			</thead>
			<tbody>
<?php
	$i = 0;
	foreach (config_get_path('openvpn/openvpn-csc', []) as $csc):
		$disabled = isset($csc['disable']) ? "Yes":"No";
?>
				<tr>
					<td class="listlr">
						<?=$disabled?>
					</td>
					<td class="listr">
						<?=htmlspecialchars($csc['common_name'])?>
					</td>
					<td class="listbg">
						<?=htmlspecialchars($csc['description'])?>
					</td>
					<td>
						<a class="fa-solid fa-pencil"	title="<?=gettext('Edit CSC Override')?>"	href="vpn_openvpn_csc.php?act=edit&amp;id=<?=$i?>"></a>
						<a class="fa-regular fa-clone"	title="<?=gettext("Copy CSC Override")?>"	href="vpn_openvpn_csc.php?act=dup&amp;id=<?=$i?>" usepost></a>
						<a class="fa-solid fa-trash-can"	title="<?=gettext('Delete CSC Override')?>"	href="vpn_openvpn_csc.php?act=del&amp;id=<?=$i?>" usepost></a>
					</td>
				</tr>
<?php
	   $i++;
	endforeach;
?>
			</tbody>
		</table>
	</div>
</div>

<nav class="action-buttons">
	<a href="vpn_openvpn_csc.php?act=new" class="btn btn-success btn-sm">
		<i class="fa-solid fa-plus icon-embed-btn"></i>
		<?=gettext('Add')?>
	</a>
</nav>

<?php
endif;
include("foot.inc");
