<?php
/*
 * system_advanced_network.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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

$show_reboot_msg = false;
$reboot_msg = gettext('Changing the Hardware Checksum setting requires a system reboot.') . '\n\n' . gettext('Reboot now?');

$pconfig['ipv6nat_enable'] = isset($config['diag']['ipv6nat']['enable']);
$pconfig['ipv6nat_ipaddr'] = isset($config['diag']['ipv6nat']['ipaddr']) ? $config['diag']['ipv6nat']['ipaddr'] : null;
$pconfig['ipv6allow'] = isset($config['system']['ipv6allow']);
$pconfig['ipv6dontcreatelocaldns'] = isset($config['system']['ipv6dontcreatelocaldns']);
$pconfig['global-v6duid'] = isset($config['system']['global-v6duid']) ? $config['system']['global-v6duid'] : null;
$pconfig['prefer_ipv4'] = isset($config['system']['prefer_ipv4']);
$pconfig['sharednet'] = isset($config['system']['sharednet']) ? $config['system']['sharednet'] : null;
$pconfig['disablechecksumoffloading'] = isset($config['system']['disablechecksumoffloading']);
$pconfig['disablesegmentationoffloading'] = isset($config['system']['disablesegmentationoffloading']);
$pconfig['disablelargereceiveoffloading'] = isset($config['system']['disablelargereceiveoffloading']);
$pconfig['ip_change_kill_states'] = isset($config['system']['ip_change_kill_states']) ? $config['system']['ip_change_kill_states'] : null;
$pconfig['hnaltqenable'] = isset($config['system']['hn_altq_enable']);

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	if ($_POST['ipv6nat_enable'] && !is_ipaddr($_POST['ipv6nat_ipaddr'])) {
		$input_errors[] = gettext("An IP address to NAT IPv6 packets must be specified.");
	}

	switch ($_POST['ipv6duidtype']) {
	case 1:
		if (!empty($_POST['ipv6duidllt_time']) && !empty($_POST['ipv6duidllt_ll'])) {
			$_POST['global-v6duid'] = format_duid(1, $_POST['ipv6duidllt_time'], $_POST['ipv6duidllt_ll']);
		}
		break;
	case 2:
		if (!empty($_POST['ipv6duiden_en']) && !empty($_POST['ipv6duiden_id'])) {
			$_POST['global-v6duid'] = format_duid(2, $_POST['ipv6duiden_en'], $_POST['ipv6duiden_id']);
		}
		break;
	case 3:
		if (!empty($_POST['ipv6duidll'])) {
			$_POST['global-v6duid'] = format_duid(3, $_POST['ipv6duidll']);
		}
		break;
	case 4:
		if (!empty($_POST['ipv6duiduuid'])) {
			$_POST['global-v6duid'] = format_duid(4, $_POST['ipv6duiduuid']);
		}
		break;
	}

	if (!empty($_POST['global-v6duid'])) {
		$_POST['global-v6duid'] = format_duid(0, $_POST['global-v6duid']);
		if (is_duid($_POST['global-v6duid'])) {
			$pconfig['global-v6duid'] = $_POST['global-v6duid'];
		} else {
			$input_errors[] = gettext("A valid DUID must be specified.");
		}
	}

	if ($_POST['ipv6allow'] == "yes" && is_bogonsv6_used(true) &&
	    (!isset($config['system']['maximumtableentries']) ||
	     $config['system']['maximumtableentries'] <
	     $g['minimumtableentries_bogonsv6'])) {
		$input_errors[] = sprintf(gettext(
		    "In order enable IPv6 and block bogon networks the Firewall Maximum Table Entries value in System / Advanced / Firewall must be increased at least to %s."),
		    $g['minimumtableentries_bogonsv6']);
	}

	ob_flush();
	flush();
	if (!$input_errors) {

		if ($_POST['ipv6nat_enable'] == "yes") {
			init_config_arr(array('diag', 'ipv6nat'));
			$config['diag']['ipv6nat']['enable'] = true;
			$config['diag']['ipv6nat']['ipaddr'] = $_POST['ipv6nat_ipaddr'];
		} else {
			if (is_array($config['diag']) &&
			    is_array($config['diag']['ipv6nat'])) {
				unset($config['diag']['ipv6nat']['enable']);
				unset($config['diag']['ipv6nat']['ipaddr']);
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

		if ((isset($_POST['disablechecksumoffloading']) xor isset($config['system']['disablechecksumoffloading'])) ||
		    (isset($_POST['disablesegmentationoffloading']) xor isset($config['system']['disablesegmentationoffloading'])) ||
		    (isset($_POST['disablelargereceiveoffloading']) xor isset($config['system']['disablelargereceiveoffloading']))) {
			$show_reboot_msg = true;
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

		if ($_POST['hnaltqenable'] == "yes") {
			$config['system']['hn_altq_enable'] = true;
		} else {
			unset($config['system']['hn_altq_enable']);
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

$section->addInput(new Form_Select(
	'ipv6duidtype',
	'DHCP6 DUID',
	'$ipv6duidtype',
	array('0' => gettext('Raw DUID: As stored in DUID file or seen in firewall logs'),
		'1' => gettext('DUID-LLT: Based on Link-layer Address Plus Time'),
		'2' => gettext('DUID-EN: Assigned by Vendor based on Enterprise Number'),
		'3' => gettext('DUID-LL: Based on Link-layer Address'),
		'4' => gettext('DUID-UUID: Based on Universally Unique Identifier')
	)
))->setHelp('A DHCPv6 Unique Identifier (DUID) is used by the firewall when requesting an IPv6 address.%1$s%1$s' .
		'By default, the firewall automatically creates a dynamic DUID-LLT which is not saved in the firewall configuration. '.
		'To ensure that the same DUID is retained by the firewall at all times, enter a DUID in this section. ' .
		'The new DUID will take effect after a reboot or when the WAN interface(s) are reconfigured by the firewall.%1$s%1$s' .
		'If the firewall is configured to use a RAM disk for /var, the best practice is to store a DUID here; otherwise, the DUID will change on each reboot.', '<br />');

$group = new Form_Group('Raw DUID');

$group->add(new Form_Textarea(
	'global-v6duid',
	'DHCP6 DUID',
	$pconfig['global-v6duid']
	));

$btncopyduid = new Form_Button(
	'btncopyduid',
	'Copy DUID',
	null,
	'fa-clone'
	);

$btncopyduid->setAttribute('type','button')->removeClass('btn-primary')->addClass('btn-success btn-sm');
$group->add($btncopyduid);

$group->setHelp('You may use the Copy DUID button to copy the system detected DUID shown in the placeholder.');

$section->add($group);

$group = new Form_Group('DUID-LLT');

$group->add(new Form_Input(
	'ipv6duidllt_time',
	'DUID-LLT',
	'text',
	$ipv6duidllt_time
))->setHelp('Time (seconds) since midnight, Jan 1, 2000 UTC');

$group->add(new Form_Input(
	'ipv6duidllt_ll',
	'Link-layer address',
	'text',
	$ipv6duidllt_ll,
	[ 'placeholder' => 'xx:xx:xx:xx:xx:xx' ]
))->setHelp('Link-layer address');

$section->add($group);

$group = new Form_Group('DUID-EN');

$group->add(new Form_Input(
	'ipv6duiden_en',
	'DUID-EN',
	'number',
	$ipv6duiden_en,
	[ 'placeholder' => 'Enterprise Number' ]
))->setHelp('IANA Private Enterprise Number');

$group->add(new Form_Textarea(
	'ipv6duiden_id',
	'Identifier',
	$ipv6duiden_id
))->setHelp('Identifier (variable length)');

$section->add($group);

$section->addInput(new Form_Input(
	'ipv6duidll',
	'DUID-LL',
	'text',
	$ipv6duidll,
	[ 'placeholder' => 'xx:xx:xx:xx:xx:xx' ]
))->setHelp('Link-layer address');

$section->addInput(new Form_Input(
	'ipv6duiduuid',
	'DUID-UUID',
	'text',
	$ipv6duiduuid,
	[ 'placeholder' => '00000000-0000-0000-0000-000000000000' ]
))->setHelp('Universally Unique Identifier');

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
	'hnaltqenable',
	'hn ALTQ support',
	'Enable the ALTQ support for hn NICs.',
	isset($config['system']['hn_altq_enable'])
))->setHelp('Checking this option will enable the ALTQ support for hn NICs. '.
	'The ALTQ support disables the multiqueue API and may reduce the system '.
	'capability to handle traffic. This will take effect after a machine reboot.');

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

	// Set placeholder on raw DUID and DUID-EN Identifier Textareas, time for DUID-LLT
	// Parse DUID if set in config and set corresponding DUID type and input values on page
	function setIpv6duid() {
		$('#global-v6duid').attr('placeholder', '<?=$duid?>');
		$('#ipv6duidllt_time').val((Date.now() / 1000 | 0) - 946684800);
		$('#ipv6duiden_id').attr('placeholder', 'xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx');
		<?php if (isset($pconfig['global-v6duid'])): ?>
		var duid = '<?=$pconfig['global-v6duid']?>';
		var duidtype = parseInt(duid.substr(6, 5).replace(':', ''), 16);
		switch (duidtype) {
		case 1:
			$('#ipv6duidllt_time').val(parseInt(duid.substr(18, 11).replace(/:/g, ''), 16));
			$('#ipv6duidllt_ll').val(duid.substr(-17));
			break;
		case 2:
			$('#ipv6duiden_en').val(parseInt(duid.substr(12, 11).replace(/:/g, ''), 16));
			$('#ipv6duiden_id').val(duid.substr(24));
			break;
		case 3:
			$('#ipv6duidll').val(duid.substr(-17));
			break;
		case 4:
			var uuid = duid.substr(-47).replace(/:/g, '');
			$('#ipv6duiduuid').val(uuid.substr(0, 8) + '-' + uuid.substr(8, 4) + '-' + uuid.substr(12, 4) + '-' + uuid.substr(16, 4) + '-' + uuid.substr(20));
			break;
		default:
		}
		if (0 < duidtype && duidtype < 5)
			$('#ipv6duidtype').val(duidtype);
		<?php endif; ?>
	}

	// Show/hide DUID type subsections
	function showHideIpv6duid() {
		hideInput('global-v6duid', $('#ipv6duidtype').prop('value') != '0');
		hideInput('ipv6duidllt_time', $('#ipv6duidtype').prop('value') != '1');
		hideInput('ipv6duiden_en', $('#ipv6duidtype').prop('value') != '2');
		hideInput('ipv6duidll', $('#ipv6duidtype').prop('value') != '3');
		hideInput('ipv6duiduuid', $('#ipv6duidtype').prop('value') != '4');
	}

	// On changing selection for DUID type
	$('#ipv6duidtype').change(function() {
		showHideIpv6duid();
	});

	// On click, copy the placeholder DUID to the input field
	$('#btncopyduid').click(function() {
		if ('<?=$duid?>' != '--:--:--:--:--:--:--:--:--:--:--:--:--:--:--:--')
			$('#global-v6duid').val('<?=$duid?>');
	});

	// On clicking IPv6 over IPv4 Tunneling checkbox
	$('#ipv6nat_enable').click(function () {
		showHideIpv6nat();
	});

	// On page load
	showHideIpv6nat();
	setIpv6duid();
	showHideIpv6duid();

	if (<?=(int)$show_reboot_msg?> && confirm("<?=$reboot_msg?>")) {
		postSubmit({override : 'yes'}, 'diag_reboot.php')
	}

});
//]]>
</script>

<?php include("foot.inc");
