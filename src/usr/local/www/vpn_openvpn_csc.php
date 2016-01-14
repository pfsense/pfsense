<?php
/*
	vpn_openvpn_csc.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *  Copyright (c)  2008 Shrew Soft Inc.
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
##|*IDENT=page-openvpn-csc
##|*NAME=OpenVPN: Client Specific Override
##|*DESCR=Allow access to the 'OpenVPN: Client Specific Override' page.
##|*MATCH=vpn_openvpn_csc.php*
##|-PRIV

require("guiconfig.inc");
require_once("openvpn.inc");
require_once("pkg-utils.inc");

global $openvpn_tls_server_modes;

$pgtitle = array(gettext("VPN"), gettext("OpenVPN"), gettext("Client Specific Overrides"));
$shortcut_section = "openvpn";

if (!is_array($config['openvpn']['openvpn-csc'])) {
	$config['openvpn']['openvpn-csc'] = array();
}

$a_csc = &$config['openvpn']['openvpn-csc'];

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

$act = $_GET['act'];
if (isset($_POST['act'])) {
	$act = $_POST['act'];
}

if ($_GET['act'] == "del") {
	if (!$a_csc[$id]) {
		pfSenseHeader("vpn_openvpn_csc.php");
		exit;
	}

	openvpn_delete_csc($a_csc[$id]);
	unset($a_csc[$id]);
	write_config();
	$savemsg = gettext("Client Specific Override successfully deleted")."<br />";
}

if ($_GET['act'] == "edit") {

	if (isset($id) && $a_csc[$id]) {
		$pconfig['server_list'] = explode(",", $a_csc[$id]['server_list']);
		$pconfig['custom_options'] = $a_csc[$id]['custom_options'];
		$pconfig['disable'] = isset($a_csc[$id]['disable']);
		$pconfig['common_name'] = $a_csc[$id]['common_name'];
		$pconfig['block'] = $a_csc[$id]['block'];
		$pconfig['description'] = $a_csc[$id]['description'];

		$pconfig['tunnel_network'] = $a_csc[$id]['tunnel_network'];
		$pconfig['local_network'] = $a_csc[$id]['local_network'];
		$pconfig['local_networkv6'] = $a_csc[$id]['local_networkv6'];
		$pconfig['remote_network'] = $a_csc[$id]['remote_network'];
		$pconfig['remote_networkv6'] = $a_csc[$id]['remote_networkv6'];
		$pconfig['gwredir'] = $a_csc[$id]['gwredir'];

		$pconfig['push_reset'] = $a_csc[$id]['push_reset'];

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

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($result = openvpn_validate_cidr($pconfig['tunnel_network'], 'Tunnel network')) {
		$input_errors[] = $result;
	}

	if ($result = openvpn_validate_cidr($pconfig['local_network'], 'IPv4 Local Network', true, "ipv4")) {
		$input_errors[] = $result;
	}

	if ($result = openvpn_validate_cidr($pconfig['local_networkv6'], 'IPv6 Local Network', true, "ipv6")) {
		$input_errors[] = $result;
	}

	if ($result = openvpn_validate_cidr($pconfig['remote_network'], 'IPv4 Remote Network', true, "ipv4")) {
		$input_errors[] = $result;
	}

	if ($result = openvpn_validate_cidr($pconfig['remote_networkv6'], 'IPv6 Remote Network', true, "ipv6")) {
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
	}

	$reqdfields[] = 'common_name';
	$reqdfieldsn[] = 'Common name';

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!$input_errors) {
		$csc = array();

		$csc['server_list'] = implode(",", $pconfig['server_list']);
		$csc['custom_options'] = $pconfig['custom_options'];
		if ($_POST['disable'] == "yes") {
			$csc['disable'] = true;
		}
		$csc['common_name'] = $pconfig['common_name'];
		$csc['block'] = $pconfig['block'];
		$csc['description'] = $pconfig['description'];
		$csc['tunnel_network'] = $pconfig['tunnel_network'];
		$csc['local_network'] = $pconfig['local_network'];
		$csc['local_networkv6'] = $pconfig['local_networkv6'];
		$csc['remote_network'] = $pconfig['remote_network'];
		$csc['remote_networkv6'] = $pconfig['remote_networkv6'];
		$csc['gwredir'] = $pconfig['gwredir'];
		$csc['push_reset'] = $pconfig['push_reset'];

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

		if (isset($id) && $a_csc[$id]) {
			$old_csc = $a_csc[$id];
			$a_csc[$id] = $csc;
		} else {
			$a_csc[] = $csc;
		}

		if (!empty($old_csc['common_name'])) {
			openvpn_delete_csc($old_csc);
		}
		openvpn_resync_csc($csc);
		write_config();

		header("Location: vpn_openvpn_csc.php");
		exit;
	}
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
$tab_array[] = array(gettext("Server"), false, "vpn_openvpn_server.php");
$tab_array[] = array(gettext("Client"), false, "vpn_openvpn_client.php");
$tab_array[] = array(gettext("Client Specific Overrides"), true, "vpn_openvpn_csc.php");
$tab_array[] = array(gettext("Wizards"), false, "wizard.php?xml=openvpn_wizard.xml");
add_package_tabs("OpenVPN", $tab_array);
display_top_tabs($tab_array);

if ($act == "new" || $act == "edit"):
	$form = new Form();

	$section = new Form_Section('General Information');

	$serveroptionlist = array();
	if (is_array($config['openvpn']['openvpn-server'])) {
		foreach ($config['openvpn']['openvpn-server'] as $serversettings) {
			if (in_array($serversettings['mode'], $openvpn_tls_server_modes)) {
				$serveroptionlist[$serversettings['vpnid']] = "OpenVPN Server {$serversettings['vpnid']}: {$serversettings['description']}";
			}
		}
	}

	$section->addInput(new Form_Select(
		'server_list',
		'Server List',
		$pconfig['server_list'],
		$serveroptionlist,
		true
		))->setHelp('Select the servers for which the override will apply. Selecting no servers will also apply the override to all servers.');


	$section->addInput(new Form_Checkbox(
		'disable',
		'Disable',
		'Disable this override',
		$pconfig['disable']
	))->setHelp('Set this option to disable this client-specific override without removing it from the list.');

	$section->addInput(new Form_Input(
		'common_name',
		'Common name',
		'text',
		$pconfig['common_name']
	))->setHelp('Enter the client\'s X.509 common name.');

	$section->addInput(new Form_Input(
		'description',
		'Description',
		'text',
		$pconfig['description']
	))->setHelp('You may enter a description here for your reference (not parsed). ');

	$section->addInput(new Form_Checkbox(
		'block',
		'Connection blocking',
		'Block this client connection based on its common name. ',
		$pconfig['block']
	))->setHelp('Don\'t use this option to permanently disable a client due to a compromised key or password. Use a CRL (certificate revocation list) instead. ');

	$form->add($section);

	$section = new Form_Section('Tunnel settings');

	$section->addInput(new Form_Input(
		'tunnel_network',
		'Tunnel Network',
		'text',
		$pconfig['tunnel_network']
	))->setHelp('This is the virtual network used for private communications between this client and the server expressed using CIDR (eg. 10.0.8.0/24). ' .
				'The first network address is assumed to be the server address and the second network address will be assigned to the client virtual interface. ');

	$section->addInput(new Form_Input(
		'local_network',
		'IPv4 Local Network/s',
		'text',
		$pconfig['local_network']
	))->setHelp('These are the IPv4 networks that will be accessible from this particular client. Expressed as a comma-separated list of one or more CIDR ranges. ' . '<br />' .
				'NOTE: You do not need to specify networks here if they have already been defined on the main server configuration.');

	$section->addInput(new Form_Input(
		'local_networkv6',
		'IPv6 Local Network/s',
		'text',
		$pconfig['local_networkv6']
	))->setHelp('These are the IPv4 networks that will be accessible from this particular client. Expressed as a comma-separated list of one or more IP/PREFIX networks.' . '<br />' .
				'NOTE: You do not need to specify networks here if they have already been defined on the main server configuration.');

	$section->addInput(new Form_Input(
		'remote_network',
		'IPv4 Remote Network/s',
		'text',
		$pconfig['remote_network']
	))->setHelp('These are the IPv4 networks that will be routed to this client specifically using iroute, so that a site-to-site VPN can be established. ' .
				'Expressed as a comma-separated list of one or more CIDR ranges. You may leave this blank if there are no client-side networks to be routed.' . '<br />' .
				'NOTE: Remember to add these subnets to the IPv4 Remote Networks list on the corresponding OpenVPN server settings.');

	$section->addInput(new Form_Input(
		'remote_networkv6',
		'IPv6 Remote Network/s',
		'text',
		$pconfig['remote_networkv6']
	))->setHelp('These are the IPv4 networks that will be routed to this client specifically using iroute, so that a site-to-site VPN can be established. ' .
				'Expressed as a comma-separated list of one or more IP/PREFIX networks. You may leave this blank if there are no client-side networks to be routed.' . '<br />' .
				'NOTE: Remember to add these subnets to the IPv6 Remote Networks list on the corresponding OpenVPN server settings.');

	$section->addInput(new Form_Checkbox(
		'gwredir',
		'Redirect Gateway',
		'Force all client generated traffic through the tunnel.',
		$pconfig['gwredir']
	));

	$form->add($section);

	$section = new Form_Section('Client settings');

	// Default domain name
	$section->addInput(new Form_Checkbox(
		'push_reset',
		'Server Definitions',
		'Prevent this client from receiving any server-defined client settings. ',
		$pconfig['push_reset']
	));

	$section->addInput(new Form_Checkbox(
		'dns_domain_enable',
		'DNS Default Domain',
		'Provide a default domain name to clients',
		$pconfig['dns_domain_enable']
	))->toggles('.dnsdomain');

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
	))->toggles('.dnsservers');

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
	))->toggles('.ntpservers');

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

	// NTP servers - For this section we need to use Javascript hiding since there
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

	$section->addInput(new Form_Textarea(
		'custom_options',
		'Advanced',
		$pconfig['custom_options']
	))->setHelp('Enter any additional options you would like to add for this client specific override, separated by a semicolon. ' . '<br />' .
				'EXAMPLE: push "route 10.0.0.0 255.255.255.0"; ');

	// The hidden fields
	$section->addInput(new Form_Input(
		'act',
		null,
		'hidden',
		$act
	));

	if (isset($id) && $a_csc[$id]) {
		$section->addInput(new Form_Input(
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

	// Hide/show that section, but have to also respect the wins_server_enable checkbox
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
});
//]]>
</script>

<?php
else :  // Not an 'add' or an 'edit'. Just the table of Override CSCs
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('CSC Overrides')?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed">
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
						<a class="fa fa-trash"	title="<?=gettext('Delete CSC Override')?>"	href="vpn_openvpn_csc.php?act=del&amp;id=<?=$i?>"></a>
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
