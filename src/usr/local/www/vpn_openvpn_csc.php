<?php
/*
 * vpn_openvpn_csc.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2023 Rubicon Communications, LLC (Netgate)
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

global $openvpn_tls_server_modes;

init_config_arr(array('openvpn', 'openvpn-csc'));
$a_csc = &$config['openvpn']['openvpn-csc'];
init_config_arr(array('openvpn', 'openvpn-server'));

$serveroptionlist = array();
foreach ($config['openvpn']['openvpn-server'] as $serversettings) {
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
$user_can_edit_advanced = (isAdminUID($_SESSION['Username']) || userHasPrivilege($user_entry, "page-openvpn-csc-advanced") || userHasPrivilege($user_entry, "page-all"));

if ($_POST['act'] == "del") {
	if (!$a_csc[$id]) {
		pfSenseHeader("vpn_openvpn_csc.php");
		exit;
	}

	if (!$user_can_edit_advanced && !empty($a_csc[$id]['custom_options'])) {
		$input_errors[] = gettext("This user does not have sufficient privileges to delete an instance with Advanced options set.");
	} else {
		$wc_msg = sprintf(gettext('Deleted OpenVPN client specific override %1$s %2$s'), $a_csc[$id]['common_name'], $a_csc[$id]['description']);
		openvpn_delete_csc($a_csc[$id]);
		unset($a_csc[$id]);
		write_config($wc_msg);
		$savemsg = gettext("Client specific override successfully deleted.");
		services_unbound_configure(false);
	}
}

if (($act == "edit") || ($act == "dup")) {
	if (isset($id) && $a_csc[$id]) {
		$pconfig['server_list'] = explode(",", $a_csc[$id]['server_list']);
		$pconfig['custom_options'] = $a_csc[$id]['custom_options'];
		$pconfig['disable'] = isset($a_csc[$id]['disable']);
		$pconfig['common_name'] = $a_csc[$id]['common_name'];
		$pconfig['block'] = $a_csc[$id]['block'];
		$pconfig['description'] = $a_csc[$id]['description'];

		$pconfig['tunnel_network'] = $a_csc[$id]['tunnel_network'];
		$pconfig['tunnel_networkv6'] = $a_csc[$id]['tunnel_networkv6'];
		$pconfig['local_network'] = $a_csc[$id]['local_network'];
		$pconfig['local_networkv6'] = $a_csc[$id]['local_networkv6'];
		$pconfig['remote_network'] = $a_csc[$id]['remote_network'];
		$pconfig['remote_networkv6'] = $a_csc[$id]['remote_networkv6'];
		$pconfig['gwredir'] = $a_csc[$id]['gwredir'];

		$pconfig['push_reset'] = $a_csc[$id]['push_reset'];
		$pconfig['remove_route'] = $a_csc[$id]['remove_route'];

		$pconfig['dns_domain'] = $a_csc[$id]['dns_domain'];
		if ($pconfig['dns_domain']) {
			$pconfig['dns_domain_enable'] = true;
		}

		$pconfig['dns_server1'] = $a_csc[$id]['dns_server1'];
		$pconfig['dns_server2'] = $a_csc[$id]['dns_server2'];
		$pconfig['dns_server3'] = $a_csc[$id]['dns_server3'];
		$pconfig['dns_server4'] = $a_csc[$id]['dns_server4'];

		if ($pconfig['dns_server1'] ||
		    $pconfig['dns_server2'] ||
		    $pconfig['dns_server3'] ||
		    $pconfig['dns_server4']) {
			$pconfig['dns_server_enable'] = true;
		}

		$pconfig['ntp_server1'] = $a_csc[$id]['ntp_server1'];
		$pconfig['ntp_server2'] = $a_csc[$id]['ntp_server2'];

		if ($pconfig['ntp_server1'] ||
		    $pconfig['ntp_server2']) {
			$pconfig['ntp_server_enable'] = true;
		}

		$pconfig['netbios_enable'] = $a_csc[$id]['netbios_enable'];
		$pconfig['netbios_ntype'] = $a_csc[$id]['netbios_ntype'];
		$pconfig['netbios_scope'] = $a_csc[$id]['netbios_scope'];

		$pconfig['wins_server1'] = $a_csc[$id]['wins_server1'];
		$pconfig['wins_server2'] = $a_csc[$id]['wins_server2'];

		if ($pconfig['wins_server1'] ||
		    $pconfig['wins_server2']) {
			$pconfig['wins_server_enable'] = true;
		}

		$pconfig['nbdd_server1'] = $a_csc[$id]['nbdd_server1'];
		if ($pconfig['nbdd_server1']) {
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
	    ($pconfig['custom_options'] != $a_csc[$id]['custom_options']) &&
	    !$user_can_edit_advanced) {
		$input_errors[] = gettext("This user does not have sufficient privileges to edit Advanced options on this instance.");
	}
	if (!$user_can_edit_advanced && !empty($a_csc[$id]['custom_options'])) {
		$pconfig['custom_options'] = $a_csc[$id]['custom_options'];
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

	if ($result = openvpn_validate_cidr($pconfig['local_network'], 'IPv4 Local Network', true, "ipv4", true)) {
		$input_errors[] = $result;
	}

	if ($result = openvpn_validate_cidr($pconfig['local_networkv6'], 'IPv6 Local Network', true, "ipv6", true)) {
		$input_errors[] = $result;
	}

	if ($result = openvpn_validate_cidr($pconfig['remote_network'], 'IPv4 Remote Network', true, "ipv4", true)) {
		$input_errors[] = $result;
	}

	if ($result = openvpn_validate_cidr($pconfig['remote_networkv6'], 'IPv6 Remote Network', true, "ipv6", true)) {
		$input_errors[] = $result;
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
		$csc['local_network'] = $pconfig['local_network'];
		$csc['local_networkv6'] = $pconfig['local_networkv6'];
		$csc['remote_network'] = $pconfig['remote_network'];
		$csc['remote_networkv6'] = $pconfig['remote_networkv6'];
		$csc['gwredir'] = $pconfig['gwredir'];
		$csc['push_reset'] = $pconfig['push_reset'];
		$csc['remove_route'] = $pconfig['remove_route'];

		if ($pconfig['dns_domain_enable']) {
			$csc['dns_domain'] = $pconfig['dns_domain'];
		}

		if ($pconfig['dns_server_enable']) {
			$csc['dns_server1'] = $pconfig['dns_server1'];
			$csc['dns_server2'] = $pconfig['dns_server2'];
			$csc['dns_server3'] = $pconfig['dns_server3'];
			$csc['dns_server4'] = $pconfig['dns_server4'];
		}

		if ($pconfig['ntp_server_enable']) {
			$csc['ntp_server1'] = $pconfig['ntp_server1'];
			$csc['ntp_server2'] = $pconfig['ntp_server2'];
		}

		$csc['netbios_enable'] = $pconfig['netbios_enable'];
		$csc['netbios_ntype'] = $pconfig['netbios_ntype'];
		$csc['netbios_scope'] = $pconfig['netbios_scope'];

		if ($pconfig['netbios_enable']) {
			if ($pconfig['wins_server_enable']) {
				$csc['wins_server1'] = $pconfig['wins_server1'];
				$csc['wins_server2'] = $pconfig['wins_server2'];
			}

			if ($pconfig['dns_server_enable']) {
				$csc['nbdd_server1'] = $pconfig['nbdd_server1'];
			}
		}

		if (($act == 'new') || (!empty($csc['disable']) ^ !empty($a_csc[$id]['disable'])) ||
		    ($csc['tunnel_network'] != $a_csc[$id]['tunnel_network']) ||
		    ($csc['tunnel_networkv6'] != $a_csc[$id]['tunnel_networkv6'])) {
			$csc['unbound_restart'] = true;
		}

		if (isset($id) && $a_csc[$id]) {
			$old_csc = $a_csc[$id];
			$a_csc[$id] = $csc;
			$wc_msg = sprintf(gettext('Updated OpenVPN client specific override %1$s %2$s'), $csc['common_name'], $csc['description']);
		} else {
			$a_csc[] = $csc;
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

	$section->addInput(new Form_Checkbox(
		'gwredir',
		'Redirect Gateway',
		'Force all client generated traffic through the tunnel.',
		$pconfig['gwredir']
	));

	$form->add($section);

	$section = new Form_Section('Client Settings');

	$section->addInput(new Form_Checkbox(
		'push_reset',
		'Server Definitions',
		'Prevent this client from receiving any server-defined client settings. ',
		$pconfig['push_reset']
	));

	/* as "push-reset" can break subnet topology, 
	 * "push-remove route" removes only IPv4/IPv6 routes, see #9702 */
	$section->addInput(new Form_Checkbox(
		'remove_route',
		'Remove Server Routes',
		'Prevent this client from receiving any server-defined routes without removing any other options. ',
		$pconfig['remove_route']
	));

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

	if (isset($id) && $a_csc[$id]) {
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
		} else {
			hideInput('netbios_ntype', true);
			hideInput('netbios_scope', true);
			hideCheckbox('wins_server_enable', true);
			hideClass('winsservers', true);
		}
	}

	function setWins() {
		hideClass('winsservers', ! $('#wins_server_enable').prop('checked'));
	}

	// ---------- Click checkbox handlers ---------------------------------------------------------

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

	// ---------- On initial page load ------------------------------------------------------------

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
	foreach ($a_csc as $csc):
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
						<a class="fa fa-pencil"	title="<?=gettext('Edit CSC Override')?>"	href="vpn_openvpn_csc.php?act=edit&amp;id=<?=$i?>"></a>
						<a class="fa fa-clone"	title="<?=gettext("Copy CSC Override")?>"	href="vpn_openvpn_csc.php?act=dup&amp;id=<?=$i?>" usepost></a>
						<a class="fa fa-trash"	title="<?=gettext('Delete CSC Override')?>"	href="vpn_openvpn_csc.php?act=del&amp;id=<?=$i?>" usepost></a>
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
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext('Add')?>
	</a>
</nav>

<?php
endif;
include("foot.inc");
