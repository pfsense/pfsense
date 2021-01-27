<?php
/*
 * vpn_ipsec_mobile.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008 Shrew Soft Inc
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
##|*IDENT=page-vpn-ipsec-mobile
##|*NAME=VPN: IPsec: Mobile
##|*DESCR=Allow access to the 'VPN: IPsec: Mobile' page.
##|*MATCH=vpn_ipsec_mobile.php*
##|-PRIV

require_once("functions.inc");
require_once("guiconfig.inc");
require_once("ipsec.inc");
require_once("vpn.inc");
require_once("filter.inc");

init_config_arr(array('ipsec', 'phase1'));
$a_phase1 = &$config['ipsec']['phase1'];

init_config_arr(array('ipsec', 'client'));
$a_client = &$config['ipsec']['client'];

init_config_arr(array('system', 'group'));
$a_group = &$config['system']['group'];

$auth_groups = array();
foreach ($config['system']['group'] as $group) {
	if (isset($group['priv'])) {
		foreach ($group['priv'] as $priv) {
			if (($priv == 'page-all') || ($priv == 'user-ipsec-xauth-dialin')) {
				if (!empty($group['description'])) {
					$auth_groups[$group['name']] = $group['description'] . " (" . $group['name'] . ")";
				} else {
					$auth_groups[$group['name']] = $group['name'];
				}
				continue 2;
			}
		}
	}
}

if (count($a_client)) {

	$pconfig['enable'] = $a_client['enable'];
	$pconfig['radiusaccounting'] = ($a_client['radiusaccounting'] == 'enabled');
	$pconfig['radius_groups'] = $a_client['radius_groups'];

	$pconfig['user_source'] = $a_client['user_source'];
	$pconfig['group_source'] = $a_client['group_source'];
	$pconfig['auth_groups'] = $a_client['auth_groups'];

	$pconfig['pool_address'] = $a_client['pool_address'];
	$pconfig['pool_netbits'] = $a_client['pool_netbits'];
	$pconfig['pool_address_v6'] = $a_client['pool_address_v6'];
	$pconfig['pool_netbits_v6'] = $a_client['pool_netbits_v6'];
	$pconfig['net_list'] = $a_client['net_list'];
	$pconfig['save_passwd'] = $a_client['save_passwd'];
	$pconfig['dns_domain'] = $a_client['dns_domain'];
	$pconfig['dns_split'] = $a_client['dns_split'];
	$pconfig['dns_server1'] = $a_client['dns_server1'];
	$pconfig['dns_server2'] = $a_client['dns_server2'];
	$pconfig['dns_server3'] = $a_client['dns_server3'];
	$pconfig['dns_server4'] = $a_client['dns_server4'];
	$pconfig['wins_server1'] = $a_client['wins_server1'];
	$pconfig['wins_server2'] = $a_client['wins_server2'];
	$pconfig['pfs_group'] = $a_client['pfs_group'];
	$pconfig['login_banner'] = $a_client['login_banner'];
	$pconfig['radius_ip_priority_enable'] = $a_client['radius_ip_priority_enable'];
	
	if (isset($pconfig['enable'])) {
		$pconfig['enable'] = true;
	}

	if ($pconfig['group_source'] == 'enabled') {
		$pconfig['group_source'] = true;
		$pconfig['auth_groups'] = $a_client['auth_groups'];
	}

	if ($pconfig['pool_address'] && $pconfig['pool_netbits']) {
		$pconfig['pool_enable'] = true;
	} else {
		$pconfig['pool_netbits'] = 24;
	}

	if (isset($pconfig['radius_ip_priority_enable'])) {
		$pconfig['radius_ip_priority_enable'] = true;
	}

	if ($pconfig['pool_address_v6'] && $pconfig['pool_netbits_v6']) {
		$pconfig['pool_enable_v6'] = true;
	} else {
		$pconfig['pool_netbits_v6'] = 120;
	}

	if (isset($pconfig['net_list'])) {
		$pconfig['net_list_enable'] = true;
	}

	if (isset($pconfig['save_passwd'])) {
		$pconfig['save_passwd_enable'] = true;
	}

	if ($pconfig['dns_domain']) {
		$pconfig['dns_domain_enable'] = true;
	}

	if ($pconfig['dns_split']) {
		$pconfig['dns_split_enable'] = true;
	}

	if ($pconfig['dns_server1'] || $pconfig['dns_server2'] || $pconfig['dns_server3'] || $pconfig['dns_server4']) {
		$pconfig['dns_server_enable'] = true;
	}

	if ($pconfig['wins_server1'] || $pconfig['wins_server2']) {
		$pconfig['wins_server_enable'] = true;
	}

	if (isset($pconfig['pfs_group'])) {
		$pconfig['pfs_group_enable'] = true;
	}

	if ($pconfig['login_banner']) {
		$pconfig['login_banner_enable'] = true;
	}
}

if ($_REQUEST['create']) {
	header("Location: vpn_ipsec_phase1.php?mobile=true");
}

if ($_POST['apply']) {
	$retval = 0;
	/* NOTE: #4353 Always restart ipsec when mobile clients settings change */
	$ipsec_dynamic_hosts = ipsec_configure(true);
	if ($ipsec_dynamic_hosts >= 0) {
		if (is_subsystem_dirty('ipsec')) {
			clear_subsystem_dirty('ipsec');
		}
	}
}

if ($_POST['save']) {

	unset($input_errors);
	$pconfig = $_POST;

	foreach ($a_phase1 as $ph1ent) {
		if (isset($ph1ent['mobile'])) {
			$mobileph1 = $ph1ent;
		}
	}
	/* input consolidation */

	/* input validation */

	$reqdfields = explode(" ", "user_source");
	$reqdfieldsn = array(gettext("User Authentication Source"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($pconfig['pool_enable']) {
		if (!is_ipaddr($pconfig['pool_address'])) {
			$input_errors[] = gettext("A valid IP address for 'Virtual Address Pool Network' must be specified.");
		}
	}
	if ($pconfig['pool_enable_v6']) {
		if (!is_ipaddrv6($pconfig['pool_address_v6'])) {
			$input_errors[] = gettext("A valid IPv6 address for 'Virtual IPv6 Address Pool Network' must be specified.");
		}
	}
	if ($pconfig['dns_domain_enable']) {
		if (!is_domain($pconfig['dns_domain'])) {
			$input_errors[] = gettext("A valid value for 'DNS Default Domain' must be specified.");
		}
	}
	if ($pconfig['dns_split_enable']) {
		if (!empty($pconfig['dns_split'])) {
			/* Replace multiple spaces by single */
			$pconfig['dns_split'] = preg_replace('/\s+/', ' ', trim($pconfig['dns_split']));
			$domain_array = explode(' ', $pconfig['dns_split']);
			foreach ($domain_array as $curdomain) {
				if (!is_domain($curdomain)) {
					$input_errors[] = gettext("A valid split DNS domain list must be specified.");
					break;
				}
			}
		}
	}

	if ($pconfig['dns_server_enable']) {
		if (!$pconfig['dns_server1'] && !$pconfig['dns_server2'] &&
		    !$pconfig['dns_server3'] && !$pconfig['dns_server4']) {
			$input_errors[] = gettext("At least one DNS server must be specified to enable the DNS Server option.");
		}
		if ($pconfig['dns_server1'] && !is_ipaddr($pconfig['dns_server1'])) {
			$input_errors[] = gettext("A valid IP address for 'DNS Server #1' must be specified.");
		}
		if ($pconfig['dns_server2'] && !is_ipaddr($pconfig['dns_server2'])) {
			$input_errors[] = gettext("A valid IP address for 'DNS Server #2' must be specified.");
		}
		if ($pconfig['dns_server3'] && !is_ipaddr($pconfig['dns_server3'])) {
			$input_errors[] = gettext("A valid IP address for 'DNS Server #3' must be specified.");
		}
		if ($pconfig['dns_server4'] && !is_ipaddr($pconfig['dns_server4'])) {
			$input_errors[] = gettext("A valid IP address for 'DNS Server #4' must be specified.");
		}
	}

	if ($pconfig['wins_server_enable']) {
		if (!$pconfig['wins_server1'] && !$pconfig['wins_server2']) {
			$input_errors[] = gettext("At least one WINS server must be specified to enable the DNS Server option.");
		}
		if ($pconfig['wins_server1'] && !is_ipaddr($pconfig['wins_server1'])) {
			$input_errors[] = gettext("A valid IP address for 'WINS Server #1' must be specified.");
		}
		if ($pconfig['wins_server2'] && !is_ipaddr($pconfig['wins_server2'])) {
			$input_errors[] = gettext("A valid IP address for 'WINS Server #2' must be specified.");
		}
	}

	if ($pconfig['login_banner_enable']) {
		if (!strlen($pconfig['login_banner'])) {
			$input_errors[] = gettext("A valid value for 'Login Banner' must be specified.");
		}
	}

	if ($pconfig['user_source']) {
		if (isset($mobileph1) && $mobileph1['authentication_method'] == 'eap-radius') {
			foreach ($pconfig['user_source'] as $auth_server_name) {
				$auth_server       = auth_get_authserver($auth_server_name);
				if (!is_array($auth_server) || ($auth_server['type'] != 'radius')) {
					$input_errors[] = gettext("Only valid RADIUS servers may be selected as a user source when using EAP-RADIUS for authentication on the Mobile IPsec VPN.");
					$pconfig['user_source'] = implode(',', $pconfig['user_source']);
				}
			}
		}
	}

	if ($pconfig['radius_ip_priority_enable']) {
		if (!(isset($mobileph1) && ($mobileph1['authentication_method'] == 'eap-radius'))) {
			$input_errors[] = gettext("RADIUS IP may only take prioriy when using EAP-RADIUS for authentication on the Mobile IPsec VPN.");
			$pconfig['user_source'] = implode(',', $pconfig['user_source']);
		}
	}

	if (!$input_errors) {
		$client = array();

		if ($pconfig['enable']) {
			$client['enable'] = true;
		}

		$client['radiusaccounting'] = ($pconfig['radiusaccounting'] == 'yes') ? "enabled" : "disabled";

		if (!empty($pconfig['user_source'])) {
			$client['user_source'] = implode(",", $pconfig['user_source']);
			$client['user_source'] = htmlentities($client['user_source'],ENT_COMPAT,'UTF-8');
		}

		$client['group_source'] = ($pconfig['group_source'] == 'yes') ? "enabled" : "disabled";

		if (($pconfig['group_source'] == 'yes') && !empty($pconfig['auth_groups'])) {
			$client['auth_groups'] = implode(",", $pconfig['auth_groups']);
		} else {
			unset($client['group_source']);
			unset($client['auth_groups']);
		}

		if ($pconfig['pool_enable']) {
			$client['pool_address'] = $pconfig['pool_address'];
			$client['pool_netbits'] = $pconfig['pool_netbits'];
		}

		if ($pconfig['radius_ip_priority_enable']) {
			$client['radius_ip_priority_enable'] = true;
		}

		if ($pconfig['pool_enable_v6']) {
			$client['pool_address_v6'] = $pconfig['pool_address_v6'];
			$client['pool_netbits_v6'] = $pconfig['pool_netbits_v6'];
		}

		if ($pconfig['net_list_enable']) {
			$client['net_list'] = true;
		}

		if ($pconfig['save_passwd_enable']) {
			$client['save_passwd'] = true;
		}

		if ($pconfig['dns_domain_enable']) {
			$client['dns_domain'] = $pconfig['dns_domain'];
		}

		if ($pconfig['dns_split_enable']) {
			$client['dns_split'] = $pconfig['dns_split'];
		}

		if ($pconfig['dns_server_enable']) {
			$client['dns_server1'] = $pconfig['dns_server1'];
			$client['dns_server2'] = $pconfig['dns_server2'];
			$client['dns_server3'] = $pconfig['dns_server3'];
			$client['dns_server4'] = $pconfig['dns_server4'];
		}

		if ($pconfig['wins_server_enable']) {
			$client['wins_server1'] = $pconfig['wins_server1'];
			$client['wins_server2'] = $pconfig['wins_server2'];
		}

		if ($pconfig['pfs_group_enable']) {
			$client['pfs_group'] = $pconfig['pfs_group'];
		}

		if ($pconfig['login_banner_enable']) {
			$client['login_banner'] = $pconfig['login_banner'];
		}
		$a_client = $client;

		write_config(gettext("Saved IPsec Mobile Clients configuration."));
		mark_subsystem_dirty('ipsec');

		header("Location: vpn_ipsec_mobile.php");
		exit;
	}
}

$pgtitle = array(gettext("VPN"), gettext("IPsec"), gettext("Mobile Clients"));
$pglinks = array("", "vpn_ipsec.php", "@self");
$shortcut_section = "ipsec";

include("head.inc");
?>

	<script type="text/javascript">
		//<![CDATA[

		function pool_change() {

			if (document.iform.pool_enable.checked) {
				document.iform.pool_address.disabled = 0;
				document.iform.pool_netbits.disabled = 0;
			} else {
				document.iform.pool_address.disabled = 1;
				document.iform.pool_netbits.disabled = 1;
			}
		}

		function pool_change_v6() {

			if (document.iform.pool_enable_v6.checked) {
				document.iform.pool_address_v6.disabled = 0;
				document.iform.pool_netbits_v6.disabled = 0;
			} else {
				document.iform.pool_address_v6.disabled = 1;
				document.iform.pool_netbits_v6.disabled = 1;
			}
		}

		function dns_domain_change() {

			if (document.iform.dns_domain_enable.checked) {
				document.iform.dns_domain.disabled = 0;
			} else {
				document.iform.dns_domain.disabled = 1;
			}
		}

		function dns_split_change() {

			if (document.iform.dns_split_enable.checked) {
				document.iform.dns_split.disabled = 0;
			} else {
				document.iform.dns_split.disabled = 1;
			}
		}

		function dns_server_change() {

			if (document.iform.dns_server_enable.checked) {
				document.iform.dns_server1.disabled = 0;
				document.iform.dns_server2.disabled = 0;
				document.iform.dns_server3.disabled = 0;
				document.iform.dns_server4.disabled = 0;
			} else {
				document.iform.dns_server1.disabled = 1;
				document.iform.dns_server2.disabled = 1;
				document.iform.dns_server3.disabled = 1;
				document.iform.dns_server4.disabled = 1;
			}
		}

		function wins_server_change() {

			if (document.iform.wins_server_enable.checked) {
				document.iform.wins_server1.disabled = 0;
				document.iform.wins_server2.disabled = 0;
			} else {
				document.iform.wins_server1.disabled = 1;
				document.iform.wins_server2.disabled = 1;
			}
		}

		function pfs_group_change() {

			if (document.iform.pfs_group_enable.checked) {
				document.iform.pfs_group.disabled = 0;
			} else {
				document.iform.pfs_group.disabled = 1;
			}
		}

		function login_banner_change() {

			if (document.iform.login_banner_enable.checked) {
				document.iform.login_banner.disabled = 0;
			} else {
				document.iform.login_banner.disabled = 1;
			}
		}

		//]]>
	</script>

<?php
if ($_POST['apply']) {
	print_apply_result_box($retval);
}
if (is_subsystem_dirty('ipsec')) {
	print_apply_box(gettext("The IPsec tunnel configuration has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
}
foreach ($a_phase1 as $ph1ent) {
	if (isset($ph1ent['mobile'])) {
		$ph1found = true;
	}
}
if ($pconfig['enable'] && !$ph1found) {
	print_info_box(gettext("Support for IPsec Mobile Clients is enabled but a Phase 1 definition was not found") . ".<br />" . gettext("Please click Create to define one."), "warning", "create", gettext("Create Phase 1"), 'fa-plus', 'success');
}

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[0] = array(gettext("Tunnels"), false, "vpn_ipsec.php");
$tab_array[1] = array(gettext("Mobile Clients"), true, "vpn_ipsec_mobile.php");
$tab_array[2] = array(gettext("Pre-Shared Keys"), false, "vpn_ipsec_keys.php");
$tab_array[3] = array(gettext("Advanced Settings"), false, "vpn_ipsec_settings.php");
display_top_tabs($tab_array);

$form = new Form;

$section = new Form_Section('Enable IPsec Mobile Client Support');
$section->addInput(new Form_Checkbox(
	'enable',
	'IKE Extensions',
	'Enable IPsec Mobile Client Support',
	$pconfig['enable']
));

$form->add($section);

$section = new Form_Section('Extended Authentication (Xauth)');

$authServers = array();

foreach (auth_get_authserver_list() as $key => $authServer) {
	$authServers[$key] = $authServer['name']; // Value == name
}

$section->addInput(new Form_Select(
	'user_source',
	'*User Authentication',
	is_array($pconfig['user_source']) ? $pconfig['user_source'] : explode(",", $pconfig['user_source']),
	$authServers,
	true
))->setHelp('Source');

$section->addInput(new Form_Checkbox(
	'group_source',
	'Group Authentication',
	'Group Authentication',
	$pconfig['group_source'],
))->setHelp('Authenticate members of groups which have either "User - VPN: IPsec with Dialin" or "WebCfg - All pages" privileges.')
  ->toggles('.toggle-group_source');

$group = new Form_Group('Authentication Groups');
$group->addClass('toggle-group_source collapse');

if (!empty($pconfig['group_source'])) {
	$group->addClass('in');
}

$group->add(new Form_Select(
	'auth_groups',
	'Groups',
	is_array($pconfig['auth_groups']) ? $pconfig['auth_groups'] : explode(",", $pconfig['auth_groups']),
	$auth_groups,
	true
))->setHelp('Multiple group selection is allowed.');

$section->add($group);

$section->addInput(new Form_Checkbox(
	'radiusaccounting',
	'RADIUS Accounting',
	'Enable RADIUS Accounting',
	$pconfig['radiusaccounting']
))->setHelp('When enabled, the IPsec daemon will attempt to send RADIUS accounting ' .
		'data for all tunnels, not only connections associated with mobile IPsec. ' .
		'Do not enable this option unless the selected RADIUS servers are online and ' .
		'capable of receiving RADIUS accounting data. If RADIUS accounting data is ' .
		'enabled and fails to send, tunnels will be disconnected.');

$form->add($section);

$section = new Form_Section('Client Configuration (mode-cfg)');

$section->addInput(new Form_Checkbox(
	'pool_enable',
	'Virtual Address Pool',
	'Provide a virtual IP address to clients',
	$pconfig['pool_enable']
))->toggles('.toggle-pool_enable');

// TODO: Refactor this manual setup
$group = new Form_Group('');
$group->addClass('toggle-pool_enable collapse');

if (!empty($pconfig['pool_enable'])) {
	$group->addClass('in');
}

$group->add(new Form_Input(
	'pool_address',
	'Network',
	'text',
	htmlspecialchars($pconfig['pool_address'])
))->setWidth(4)->setHelp('Network configuration for Virtual Address Pool');

$netBits = array();

for ($i = 32; $i >= 0; $i--) {
	$netBits[$i] = $i;
}

$group->add(new Form_Select(
	'pool_netbits',
	'',
	$pconfig['pool_netbits'],
	$netBits
))->setWidth(2);

$section->add($group);

$section->addInput(new Form_Checkbox(
	'pool_enable_v6',
	'Virtual IPv6 Address Pool',
	'Provide a virtual IPv6 address to clients',
	$pconfig['pool_enable_v6']
))->toggles('.toggle-pool_enable_v6');

// TODO: Refactor this manual setup
$group = new Form_Group('');
$group->addClass('toggle-pool_enable_v6 collapse');

if (!empty($pconfig['pool_enable_v6'])) {
	$group->addClass('in');
}

$group->add(new Form_Input(
	'pool_address_v6',
	'IPv6 Network',
	'text',
	htmlspecialchars($pconfig['pool_address_v6'])
))->setWidth(4)->setHelp('Network configuration for Virtual IPv6 Address Pool');

$netBits = array();

for ($i = 128; $i >= 0; $i--) {
	$netBitsv6[$i] = $i;
}

$group->add(new Form_Select(
	'pool_netbits_v6',
	'',
	$pconfig['pool_netbits_v6'],
	$netBitsv6
))->setWidth(2);

$section->add($group);

$section->addInput(new Form_Checkbox(
	'radius_ip_priority_enable',
	'RADIUS IP address priority',
	'IPv4/IPv6 address pool is used if address is not supplied by RADIUS server',
	$pconfig['radius_ip_priority_enable']
));

$section->addInput(new Form_Checkbox(
	'net_list_enable',
	'Network List',
	'Provide a list of accessible networks to clients',
	$pconfig['net_list_enable']
));

$section->addInput(new Form_Checkbox(
	'save_passwd_enable',
	'Save Xauth Password',
	'Allow clients to save Xauth passwords (Cisco VPN client only).',
	$pconfig['save_passwd_enable']
))->setHelp('NOTE: With iPhone clients, this does not work when deployed via the iPhone configuration utility, only by manual entry.');

$section->addInput(new Form_Checkbox(
	'dns_domain_enable',
	'DNS Default Domain',
	'Provide a default domain name to clients',
	$pconfig['dns_domain_enable']
))->toggles('.toggle-dns_domain');

$group = new Form_Group('');
$group->addClass('toggle-dns_domain collapse');

if (!empty($pconfig['dns_domain_enable'])) {
	$group->addClass('in');
}

$group->add(new Form_Input(
	'dns_domain',
	'',
	'text',
	htmlspecialchars($pconfig['dns_domain'])
))->setHelp('Specify domain as DNS Default Domain');

$section->add($group);

$section->addInput(new Form_Checkbox(
	'dns_split_enable',
	'Split DNS',
	'Provide a list of split DNS domain names to clients. Enter a space separated list.',
	$pconfig['dns_split_enable']
))->toggles('.toggle-dns_split');

$group = new Form_Group('');
$group->addClass('toggle-dns_split collapse');

if (!empty($pconfig['dns_split_enable'])) {
	$group->addClass('in');
}

$group->add(new Form_Input(
	'dns_split',
	'',
	'text',
	htmlspecialchars($pconfig['dns_split'])
))->setHelp('NOTE: If left blank, and a default domain is set, it will be used for this value.');

$section->add($group);

$section->addInput(new Form_Checkbox(
	'dns_server_enable',
	'DNS Servers',
	'Provide a DNS server list to clients',
	$pconfig['dns_server_enable']
))->toggles('.toggle-dns_server_enable');

for ($i = 1; $i <= 4; $i++) {
	$group = new Form_Group('Server #' . $i);
	$group->addClass('toggle-dns_server_enable collapse');

	if (!empty($pconfig['dns_server_enable'])) {
		$group->addClass('in');
	}

	$group->add(new Form_Input(
		'dns_server' . $i,
		'Server #' . $i,
		'text',
		htmlspecialchars($pconfig['dns_server' . $i])
	));

	$section->add($group);
}

$section->addInput(new Form_Checkbox(
	'wins_server_enable',
	'WINS Servers',
	'Provide a WINS server list to clients',
	$pconfig['wins_server_enable']
))->toggles('.toggle-wins_server_enable');

for ($i = 1; $i <= 2; $i++) {
	$group = new Form_Group('Server #' . $i);
	$group->addClass('toggle-wins_server_enable collapse');

	if (!empty($pconfig['wins_server_enable'])) {
		$group->addClass('in');
	}

	$group->add(new Form_Input(
		'wins_server' . $i,
		'Server #' . $i,
		'text',
		htmlspecialchars($pconfig['wins_server' . $i]),
		array('size' => 20)
	));

	$section->add($group);
}

$section->addInput(new Form_Checkbox(
	'pfs_group_enable',
	'Phase2 PFS Group',
	'Provide the Phase2 PFS group to clients ( overrides all mobile phase2 settings )',
	$pconfig['pfs_group_enable']
))->toggles('.toggle-pfs_group');

$group = new Form_Group('Group');
$group->addClass('toggle-pfs_group collapse');

if (!empty($pconfig['pfs_group_enable'])) {
	$group->addClass('in');
}

$group->add(new Form_Select(
	'pfs_group',
	'Group',
	$pconfig['pfs_group'],
	$p2_pfskeygroups
))->setHelp('Note: Groups 1, 2, 5, 22, 23, and 24 provide weak security and should be avoided.');

$section->add($group);

$section->addInput(new Form_Checkbox(
	'login_banner_enable',
	'Login Banner',
	'Provide a login banner to clients',
	$pconfig['login_banner_enable']
))->toggles('.toggle-login_banner');

$group = new Form_Group('');
$group->addClass('toggle-login_banner collapse');

if (!empty($pconfig['login_banner_enable'])) {
	$group->addClass('in');
}

// TODO: should be a textarea
$group->add(new Form_Input(
	'login_banner',
	'',
	'text',
	htmlspecialchars($pconfig['login_banner'])
));

$section->add($group);

$form->add($section);

print $form;

include("foot.inc");
