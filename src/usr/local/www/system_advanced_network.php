<?php
/*
 * system_advanced_network.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-system-advanced-network
##|*NAME=System: Advanced: Networking
##|*DESCR=Allow access to the 'System: Advanced: Networking' page.
##|*MATCH=system_advanced_network.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");


$pconfig['ipv6nat_enable'] = isset($config['diag']['ipv6nat']['enable']);
$pconfig['ipv6nat_ipaddr'] = $config['diag']['ipv6nat']['ipaddr'];
$pconfig['ipv6allow'] = isset($config['system']['ipv6allow']);
$pconfig['ipv6dontcreatelocaldns'] = isset($config['system']['ipv6dontcreatelocaldns']);
$pconfig['global-v6duid'] = $config['system']['global-v6duid'];
$pconfig['prefer_ipv4'] = isset($config['system']['prefer_ipv4']);
$pconfig['sharednet'] = $config['system']['sharednet'];
$pconfig['disablechecksumoffloading'] = isset($config['system']['disablechecksumoffloading']);
$pconfig['disablesegmentationoffloading'] = isset($config['system']['disablesegmentationoffloading']);
$pconfig['disablelargereceiveoffloading'] = isset($config['system']['disablelargereceiveoffloading']);
$pconfig['ip_change_kill_states'] = isset($config['system']['ip_change_kill_states']);

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	if ($_POST['ipv6nat_enable'] && !is_ipaddr($_POST['ipv6nat_ipaddr'])) {
		$input_errors[] = gettext("An IP address to NAT IPv6 packets must be specified.");
	}

	if (!empty($_POST['global-v6duid'])) {
		$_POST['global-v6duid'] = format_duid($_POST['global-v6duid']);
		$pconfig['global-v6duid'] = $_POST['global-v6duid'];
		if (!is_duid($_POST['global-v6duid'])) {
			$input_errors[] = gettext("A valid DUID must be specified");
		}
	}

	ob_flush();
	flush();
	if (!$input_errors) {

		if ($_POST['ipv6nat_enable'] == "yes") {
			$config['diag']['ipv6nat']['enable'] = true;
			$config['diag']['ipv6nat']['ipaddr'] = $_POST['ipv6nat_ipaddr'];
		} else {
			if ($config['diag']) {
				if ($config['diag']['ipv6nat']) {
					unset($config['diag']['ipv6nat']['enable']);
					unset($config['diag']['ipv6nat']['ipaddr']);
				}
			}
		}

		if ($_POST['ipv6allow'] == "yes") {
			$config['system']['ipv6allow'] = true;
		} else {
			unset($config['system']['ipv6allow']);
		}

		if ($_POST['ipv6dontcreatelocaldns'] == "yes") {
			$config['system']['ipv6dontcreatelocaldns'] = true;
		} else {
			unset($config['system']['ipv6dontcreatelocaldns']);
		}

		if ($_POST['prefer_ipv4'] == "yes") {
			$config['system']['prefer_ipv4'] = true;
		} else {
			unset($config['system']['prefer_ipv4']);
		}

		if (!empty($_POST['global-v6duid'])) {
			$config['system']['global-v6duid'] = $_POST['global-v6duid'];
		} else {
			unset($config['system']['global-v6duid']);
		}

		if ($_POST['sharednet'] == "yes") {
			$config['system']['sharednet'] = true;
			system_disable_arp_wrong_if();
		} else {
			unset($config['system']['sharednet']);
			system_enable_arp_wrong_if();
		}

		if ($_POST['disablechecksumoffloading'] == "yes") {
			$config['system']['disablechecksumoffloading'] = true;
		} else {
			unset($config['system']['disablechecksumoffloading']);
		}

		if ($_POST['disablesegmentationoffloading'] == "yes") {
			$config['system']['disablesegmentationoffloading'] = true;
		} else {
			unset($config['system']['disablesegmentationoffloading']);
		}

		if ($_POST['disablelargereceiveoffloading'] == "yes") {
			$config['system']['disablelargereceiveoffloading'] = true;
		} else {
			unset($config['system']['disablelargereceiveoffloading']);
		}

		if ($_POST['ip_change_kill_states'] == "yes") {
			$config['system']['ip_change_kill_states'] = true;
		} else {
			unset($config['system']['ip_change_kill_states']);
		}

		setup_microcode();

		// Write out configuration (config.xml)
		write_config();

		// Set preferred protocol
		prefer_ipv4_or_ipv6();

		$changes_applied = true;
		$retval = 0;
		$retval |= filter_configure();
	}
}

$pgtitle = array(gettext("System"), gettext("Advanced"), gettext("Networking"));
$pglinks = array("", "system_advanced_admin.php", "@self");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($changes_applied) {
	print_apply_result_box($retval);
}

$tab_array = array();
$tab_array[] = array(gettext("Admin Access"), false, "system_advanced_admin.php");
$tab_array[] = array(htmlspecialchars(gettext("Firewall & NAT")), false, "system_advanced_firewall.php");
$tab_array[] = array(gettext("Networking"), true, "system_advanced_network.php");
$tab_array[] = array(gettext("Miscellaneous"), false, "system_advanced_misc.php");
$tab_array[] = array(gettext("System Tunables"), false, "system_advanced_sysctl.php");
$tab_array[] = array(gettext("Notifications"), false, "system_advanced_notifications.php");
$duid = get_duid_from_file();
display_top_tabs($tab_array);

$form = new Form;
$section = new Form_Section('IPv6 Options');

$section->addInput(new Form_Checkbox(
	'ipv6allow',
	'Allow IPv6',
	'All IPv6 traffic will be blocked by the firewall unless this box is checked',
	$pconfig['ipv6allow']
))->setHelp('NOTE: This does not disable any IPv6 features on the firewall, it only '.
	'blocks traffic.');

$section->addInput(new Form_Checkbox(
	'ipv6nat_enable',
	'IPv6 over IPv4 Tunneling',
	'Enable IPv6 over IPv4 tunneling',
	$pconfig['ipv6nat_enable']
))->setHelp('These options create an RFC 2893 compatible mechanism for IPv4 NAT encapsulation of IPv6 packets, ' .
	'that can be used to tunnel IPv6 packets over IPv4 routing infrastructures. ' .
	'IPv6 firewall rules are %1$salso required%2$s, to control and pass encapsulated traffic.', '<a href="firewall_rules.php">', '</a>');

$section->addInput(new Form_Input(
	'ipv6nat_ipaddr',
	'IPv4 address of Tunnel Peer',
	'text',
	$pconfig['ipv6nat_ipaddr']
));

$section->addInput(new Form_Checkbox(
	'prefer_ipv4',
	'Prefer IPv4 over IPv6',
	'Prefer to use IPv4 even if IPv6 is available',
	$pconfig['prefer_ipv4']
))->setHelp('By default, if IPv6 is configured and a hostname resolves IPv6 and IPv4 addresses, '.
	'IPv6 will be used. If this option is selected, IPv4 will be preferred over IPv6.');

$section->addInput(new Form_Checkbox(
	'ipv6dontcreatelocaldns',
	'IPv6 DNS entry',
	'Do not generate local IPv6 DNS entries for LAN interfaces',
	$pconfig['ipv6dontcreatelocaldns']
))->setHelp('If a LAN interface\'s IPv6 configuration is set to Track, and the tracked interface loses connectivity, '.
    'it can cause connections to this firewall that were established via hostname to fail. This can happen '.
	'unintentionally when accessing the firewall by hostname, since by default both IPv4 and IPv6 entries are added '.
	'to the system\'s DNS. Enabling this option prevents those IPv6 records from being created.');

$group = new Form_Group('DHCP6 DUID');

$group->add(new Form_Input(
	'global-v6duid',
	'DHCP6 DUID',
	'text',
	$pconfig['global-v6duid'],
	['placeholder' => $duid]
	));

$btncopyduid = new Form_Button(
	'btncopyduid',
	'Copy DUID',
	null,
	'fa-clone'
	);

$btncopyduid->setAttribute('type','button')->removeClass('btn-primary')->addClass('btn-success btn-sm');
$group->add($btncopyduid);

$group->setHelp('This is the DHCPv6 Unique Identifier (DUID) used by the firewall when requesting an IPv6 address. %1$s' .
		'By default, the firewall automatically creates a dynamic DUID which is not saved in the firewall configuration. '.
		'To ensure the same DUID is retained by the firewall at all times, enter a DUID in this field. ' .
		'The new DUID will take effect after a reboot or when the WAN interface(s) are reconfigured by the firewall.%1$s' .
		'If the firewall is configured to use a RAM disk for /var, the best practice is to store a DUID here otherwise the DUID will change on each reboot.%1$s%1$s' .
		'You may use the Copy DUID button to copy the system detected DUID shown in the placeholder. ', '<br />');

$section->add($group);

$form->add($section);
$section = new Form_Section('Network Interfaces');

$section->addInput(new Form_Checkbox(
	'disablechecksumoffloading',
	'Hardware Checksum Offloading',
	'Disable hardware checksum offload',
	isset($config['system']['disablechecksumoffloading'])
))->setHelp('Checking this option will disable hardware checksum offloading.%1$s'.
	'Checksum offloading is broken in some hardware, particularly some Realtek cards. '.
	'Rarely, drivers may have problems with checksum offloading and some specific '.
	'NICs. This will take effect after a machine reboot or re-configure of each '.
	'interface.', '<br/>');

$section->addInput(new Form_Checkbox(
	'disablesegmentationoffloading',
	'Hardware TCP Segmentation Offloading',
	'Disable hardware TCP segmentation offload',
	isset($config['system']['disablesegmentationoffloading'])
))->setHelp('Checking this option will disable hardware TCP segmentation '.
	'offloading (TSO, TSO4, TSO6). This offloading is broken in some hardware '.
	'drivers, and may impact performance with some specific NICs. This will take '.
	'effect after a machine reboot or re-configure of each interface.');

$section->addInput(new Form_Checkbox(
	'disablelargereceiveoffloading',
	'Hardware Large Receive Offloading',
	'Disable hardware large receive offload',
	isset($config['system']['disablelargereceiveoffloading'])
))->setHelp('Checking this option will disable hardware large receive offloading '.
	'(LRO). This offloading is broken in some hardware drivers, and may impact '.
	'performance with some specific NICs. This will take effect after a machine reboot '.
	'or re-configure of each interface.');

$section->addInput(new Form_Checkbox(
	'sharednet',
	'ARP Handling',
	'Suppress ARP messages',
	isset($pconfig['sharednet'])
))->setHelp('This option will suppress ARP log messages when multiple interfaces '.
	'reside on the same broadcast domain.');

$section->addInput(new Form_Checkbox(
	'ip_change_kill_states',
	'Reset All States',
	'Reset all states if WAN IP Address changes',
	isset($pconfig['ip_change_kill_states'])
))->setHelp('This option resets all states when a WAN IP Address changes instead of only '.
    'states associated with the previous IP Address.');

if (get_freebsd_version() == 8) {
	$section->addInput(new Form_Checkbox(
		'flowtable',
		'Enable flowtable support',
		$pconfig['flowtable']
	))->setHelp('Enables infrastructure for caching flows as a means of accelerating '.
		'L3 and L2 lookups as well as providing stateful load balancing when used with '.
		'RADIX_MPATH.');
}

$form->add($section);
print $form;
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Show/hide IPv4 address of Tunnel Peer input field
	function showHideIpv6nat() {
		hideInput('ipv6nat_ipaddr', !$('#ipv6nat_enable').prop('checked'));
	}

	// On click, copy the placeholder DUID to the input field
	$('#btncopyduid').click(function() {
		$('#global-v6duid').val('<?=$duid?>');
	});

	// On clicking IPv6 over IPv4 Tunneling checkbox
	$('#ipv6nat_enable').click(function () {
		showHideIpv6nat();
	});

	// On page load
	showHideIpv6nat();


});
//]]>
</script>

<?php include("foot.inc");
