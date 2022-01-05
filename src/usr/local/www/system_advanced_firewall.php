<?php
/*
 * system_advanced_firewall.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-system-advanced-firewall
##|*NAME=System: Advanced: Firewall & NAT
##|*DESCR=Allow access to the 'System: Advanced: Firewall & NAT' page.
##|*MATCH=system_advanced_firewall.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("system_advanced_firewall.inc");

// Retrieve furewall settings
$pconfig = getSystemAdvancedFirewall();
$old_maximumtableentries = $pconfig['maximumtableentries'];
$old_aliasesresolveinterval = $pconfig['aliasesresolveinterval'];

$pftimeouts = get_pf_timeouts();

// Validate/save new settings
if ($_POST) {
	$pconfig = $_POST;

	$rv = saveSystemAdvancedFirewall($_POST);

	$input_errors = $rv['input_errors'];
	$retval = $rv['retval'];
	$changes_applied = $rv['changes_applied'];
}

$pgtitle = array(gettext("System"), gettext("Advanced"), htmlspecialchars(gettext("Firewall & NAT")));
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
$tab_array[] = array(htmlspecialchars(gettext("Firewall & NAT")), true, "system_advanced_firewall.php");
$tab_array[] = array(gettext("Networking"), false, "system_advanced_network.php");
$tab_array[] = array(gettext("Miscellaneous"), false, "system_advanced_misc.php");
$tab_array[] = array(gettext("System Tunables"), false, "system_advanced_sysctl.php");
$tab_array[] = array(gettext("Notifications"), false, "system_advanced_notifications.php");
display_top_tabs($tab_array);

?><div id="container"><?php

$form = new Form;
$section = new Form_Section('Packet Processing');

$section->addInput(new Form_Checkbox(
	'scrubnodf',
	'IP Do-Not-Fragment compatibility',
	'Clear invalid DF bits instead of dropping the packets',
	isset($config['system']['scrubnodf'])
))->setHelp('This allows for communications with hosts that generate fragmented '.
	'packets with the don\'t fragment (DF) bit set. Linux NFS is known to do this. '.
	'This will cause the filter to not drop such packets but instead clear the don\'t '.
	'fragment bit.');

$section->addInput(new Form_Checkbox(
	'scrubrnid',
	'IP Random id generation',
	'Insert a stronger ID into IP header of packets passing through the filter.',
	isset($config['system']['scrubrnid'])
))->setHelp('Replaces the IP identification field of packets with random values to '.
	'compensate for operating systems that use predictable values. This option only '.
	'applies to packets that are not fragmented after the optional packet '.
	'reassembly.');

$section->addInput($input = new Form_Select(
	'optimization',
	'Firewall Optimization Options',
	$config['system']['optimization'],
	array(
		'normal' => 'Normal',
		'high-latency' => gettext('High-latency'),
		'aggressive' => gettext('Aggressive'),
		'conservative' => gettext('Conservative'),
	)
))->setHelp('Select the type of state table optimization to use');

$section->addInput(new Form_Checkbox(
	'disablescrub',
	'Disable Firewall Scrub',
	'Disables the PF scrubbing option which can sometimes interfere with NFS traffic.',
	isset($config['system']['disablescrub'])
));

$group = new Form_Group('Firewall Adaptive Timeouts');

$group->add(new Form_Input(
	'adaptivestart',
	'Adaptive start',
	'number',
	$pconfig['adaptivestart'],
	['min' => 0, 'placeholder' => $pftimeouts['ADAPTIVE']['Start']['value']]
))->setHelp('When the number of state entries exceeds this value, adaptive '.
	'scaling begins.  All timeout values are scaled linearly with factor '.
	'(adaptive.end - number of states) / (adaptive.end - adaptive.start). '.
	'Defaults to 60% of the Firewall Maximum States value');

$group->add(new Form_Input(
	'adaptiveend',
	'Adaptive end',
	'number',
	$pconfig['adaptiveend'],
	['min' => 0, 'placeholder' => $pftimeouts['ADAPTIVE']['End']['value']]
))->setHelp('When reaching this number of state entries, all timeout values '.
	'become zero, effectively purging all state entries immediately.  This '.
	'value is used to define the scale factor, it should not actually be '.
	'reached (set a lower state limit, see below). '.
	'Defaults to 120% of the Firewall Maximum States value');

$group->setHelp('Timeouts for states can be scaled adaptively as the number of '.
	'state table entries grows. Leave blank to use default values, set to '.
	'0 to disable Adaptive Timeouts.');

$section->add($group);

$section->addInput(new Form_Input(
	'maximumstates',
	'Firewall Maximum States',
	'number',
	$pconfig['maximumstates'],
	['min' => 1, 'placeholder' => pfsense_default_state_size()]
))->setHelp('Maximum number of connections to hold in the firewall state table. %1$s'.
	'Note: Leave this blank for the default. On this system the default '.
	'size is: %2$d', '<br/>', pfsense_default_state_size());

$section->addInput(new Form_Input(
	'maximumtableentries',
	'Firewall Maximum Table Entries',
	'text',
	$pconfig['maximumtableentries'],
	['placeholder' => pfsense_default_table_entries_size()]
))->setHelp('Maximum number of table entries for systems such as aliases, '.
	'sshguard, snort, etc, combined.%1$sNote: Leave this blank for the '.
	'default. On this system the default size is: %2$d',
	'<br/>',
	pfsense_default_table_entries_size());

$section->addInput(new Form_Input(
	'maximumfrags',
	'Firewall Maximum Fragment Entries',
	'text',
	$pconfig['maximumfrags'],
	['placeholder' => 5000]
))->setHelp('Maximum number of packet fragments to hold for reassembly by scrub rules. Leave this blank for the default (5000)');

$form->add($section);

$section = new Form_Section('VPN Packet Processing');

$section->addInput(new Form_StaticText(
	'',
	gettext('These setting will affect IPsec, OpenVPN and PPPoE Server network traffic')
));

$section->addInput(new Form_Checkbox(
	'vpn_scrubnodf',
	'IP Do-Not-Fragment compatibility',
	'Clear invalid DF bits instead of dropping the packets',
	$pconfig['vpn_scrubnodf']
))->setHelp('This allows for communications with hosts that generate fragmented '.
	'packets with the don\'t fragment (DF) bit set. Linux NFS is known to do this. '.
	'This will cause the filter to not drop such packets but instead clear the don\'t '.
	'fragment bit.');

$section->addInput(new Form_Checkbox(
	'vpn_fragment_reassemble',
	'IP Fragment Reassemble',
	'Reassemble IP Fragments until they form a complete packet',
	$pconfig['vpn_fragment_reassemble']
))->setHelp('Reassemble IP Fragments for normalization. In this case, fragments are buffered until ' .
	    'they form a complete packet, and only the completed packet is passed on to the filter. ' .
	    'The advantage is that filter rules have to deal only with complete packets, and can ignore ' .
	    'fragments. The drawback of caching fragments is the additional memory cost.');

$section->addInput(new Form_Checkbox(
	'maxmss_enable',
	'Enable Maximum MSS',
	'Enable MSS clamping on VPN traffic',
	$pconfig['maxmss_enable']
))->toggles('.toggle-maxmss', 'collapse');

$group = new Form_Group('Maximum MSS');
$group->addClass('toggle-maxmss collapse');

if (!empty($pconfig['maxmss_enable'])) {
	$group->addClass('in');
}

$group->add(new Form_Input(
	'maxmss',
	'Maximum MSS',
	'text',
	$pconfig['maxmss'],
	['placeholder' => 1400]
))->setHelp(
	'Enable MSS clamping on TCP flows over VPN. ' .
	'This helps overcome problems with PMTUD on IPsec VPN links. The default value is 1400 bytes.');

$section->add($group);
$form->add($section);

$section = new Form_Section('Advanced Options');

$section->addInput(new Form_Checkbox(
	'disablefilter',
	'Disable Firewall',
	'Disable all packet filtering.',
	isset($config['system']['disablefilter'])
))->setHelp('Note: This converts %1$s into a routing only platform!%2$s'.
	'Note: This will also turn off NAT! To only disable NAT, '.
	'and not firewall rules, visit the %3$sOutbound NAT%4$s page.', $g["product_label"], '<br/>', '<a href="firewall_nat_out.php">', '</a>');

$section->addInput(new Form_Checkbox(
	'bypassstaticroutes',
	'Static route filtering',
	'Bypass firewall rules for traffic on the same interface',
	$pconfig['bypassstaticroutes']
))->setHelp('This option only applies if one or more static routes have been defined. '.
	'If it is enabled, traffic that enters and leaves through the same '.
	'interface will not be checked by the firewall. This may be desirable in some '.
	'situations where multiple subnets are connected to the same interface.');

$section->addInput(new Form_Checkbox(
	'disablevpnrules',
	'Disable Auto-added VPN rules',
	'Disable all auto-added VPN rules.',
	isset($config['system']['disablevpnrules'])
))->setHelp('Note: This disables automatically added rules for IPsec.');

$section->addInput(new Form_Checkbox(
	'disablereplyto',
	'Disable reply-to',
	'Disable reply-to on WAN rules',
	$pconfig['disablereplyto']
))->setHelp('With Multi-WAN it is generally desired to ensure traffic leaves the same '.
	'interface it arrives on, hence reply-to is added automatically by default. When '.
	'using bridging, this behavior must be disabled if the WAN gateway IP is '.
	'different from the gateway IP of the hosts behind the bridged interface.');

$section->addInput(new Form_Checkbox(
	'disablenegate',
	'Disable Negate rules',
	'Disable Negate rule on policy routing rules',
	$pconfig['disablenegate']
))->setHelp('With Multi-WAN it is generally desired to ensure traffic reaches directly '.
	'connected networks and VPN networks when using policy routing. This can be disabled '.
	'for special purposes but it requires manually creating rules for these networks.');

$section->addInput(new Form_Checkbox(
	'no_apipa_block',
	'Allow APIPA',
	'Allow APIPA traffic',
	$pconfig['no_apipa_block']
))->setHelp('Normally this traffic is dropped by the firewall, as APIPA traffic cannot be routed, but some providers may utilize APIPA space for interconnect interfaces.');

$section->addInput(new Form_Input(
	'aliasesresolveinterval',
	'Aliases Hostnames Resolve Interval',
	'text',
	$pconfig['aliasesresolveinterval'],
	['placeholder' => '300']
))->setHelp('Interval, in seconds, that will be used to resolve hostnames '.
	'configured on aliases. %1$sNote:	 Leave this blank for the default '.
	'(300s).', '<br/>');

$section->addInput(new Form_Checkbox(
	'checkaliasesurlcert',
	'Check certificate of aliases URLs',
	'Verify HTTPS certificates when downloading alias URLs',
	$pconfig['checkaliasesurlcert']
))->setHelp('Make sure the certificate is valid for all HTTPS addresses on '.
	'aliases. If it\'s not valid or is revoked, do not download it.');

$form->add($section);

$section = new Form_Section('Bogon Networks');

$section->addInput(new Form_Select(
	'bogonsinterval',
	'Update Frequency',
	empty($pconfig['bogonsinterval']) ? 'monthly' : $pconfig['bogonsinterval'],
	array(
		'monthly' => gettext('Monthly'),
		'weekly' => gettext('Weekly'),
		'daily' => gettext('Daily'),
	)
))->setHelp('The frequency of updating the lists of IP addresses that are '.
	'reserved (but not RFC 1918) or not yet assigned by IANA.');

$form->add($section);

if (count($config['interfaces']) > 1) {
	$section = new Form_Section('Network Address Translation');

	if (isset($config['system']['disablenatreflection'])) {
		$value = 'disable';
	} elseif (!isset($config['system']['enablenatreflectionpurenat'])) {
		$value = 'proxy';
	} else {
		$value = 'purenat';
	}

	$section->addInput(new Form_Select(
		'natreflection',
		'NAT Reflection mode for port forwards',
		$value,
		array(
			'disable' => gettext('disabled'),
			'proxy' => gettext('NAT + Proxy'),
			'purenat' => gettext('Pure NAT'),
		)
	))->setHelp('%1$sThe Pure NAT mode uses a set of NAT rules to direct '.
		'packets to the target of the port forward. It has better scalability, '.
		'but it must be possible to accurately determine the interface and '.
		'gateway IP used for communication with the target at the time the '.
		'rules are loaded. There are no inherent limits to the number of ports '.
		'other than the limits of the protocols.  All protocols available for '.
		'port forwards are supported.%2$sThe NAT + Proxy mode uses a '.
		'helper program to send packets to the target of the port forward. '.
		'It is useful in setups where the interface and/or gateway IP used '.
		'for communication with the target cannot be accurately determined at '.
		'the time the rules are loaded. Reflection rules are not created for '.
		'ranges larger than 500 ports and will not be used for more than 1000 '.
		'ports total between all port forwards. This feature does not support IPv6. ' .
	        'Only TCP and UDP protocols are supported.%3$sIndividual rules may ' .
		'be configured to override this system setting on a per-rule basis.',
		'</span><ul class="help-block"><li>', '</li><li>', '</li></ul><span class="help-block">');

	$section->addInput(new Form_Input(
		'reflectiontimeout',
		'Reflection Timeout',
		'number',
		$config['system']['reflectiontimeout'],
		['min' => 1, 'placeholder' => '2000']
	))->setHelp('Enter value for Reflection timeout in seconds.%1$sNote: Only '.
		'applies to Reflection on port forwards in NAT + proxy mode.', '<br/>');

	$section->addInput(new Form_Checkbox(
		'enablebinatreflection',
		'Enable NAT Reflection for 1:1 NAT',
		'Automatic creation of additional NAT redirect rules from within the internal networks.',
		isset($config['system']['enablebinatreflection'])
	))->setHelp('Note: Reflection on 1:1 mappings is only for the inbound component of '.
		'the 1:1 mappings. This functions the same as the pure NAT mode for port '.
		'forwards. For more details, refer to the pure NAT mode description '.
		'above. Individual rules may be configured to override this system setting on a '.
		'per-rule basis.');

	$section->addInput(new Form_Checkbox(
		'enablenatreflectionhelper',
		'Enable automatic outbound NAT for Reflection',
		'Automatic create outbound NAT rules that direct traffic back out to the same subnet it originated from.',
		isset($config['system']['enablenatreflectionhelper'])
	))->setHelp('Required for full functionality of the pure NAT mode of NAT '.
		'Reflection for port forwards or NAT Reflection for 1:1 NAT. Note: This only works '.
		'for assigned interfaces.  Other interfaces require manually creating the '.
		'outbound NAT rules that direct the reply packets back through the router.');

	$section->addInput(new Form_Select(
		'tftpinterface',
		'TFTP Proxy',
		$pconfig['tftpinterface'],
		get_configured_interface_with_descr(),
		true
	))->setHelp('Choose the interfaces on which to enable TFTP proxy helper.');

	$form->add($section);
}

$section = new Form_Section('State Timeouts (seconds - blank for default)');

foreach ($pftimeouts as $proto => $tm) {
	foreach ($tm as $type => $item) {
		$section->addInput(new Form_Input(
			$item['keyname'],
			$item['name'],
			'number',
			$config['system'][$item['keyname']],
			['placeholder' => $item['value']]
		));
	}
	if ($item['keyname'] == 'othermultipletimeout') {
		break;
	}
}

$form->add($section);

print $form;

?></div>

<div class="infoblock">
	<?php print_info_box(gettext('You need to reload this page after changing the Firewall Optimization Options to see the actual timeout values.'), 'info', false); ?>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// Change help text based on the selector value
	function setOptText(val) {
		var htext = '<span class="text-success">';

		if (val == 'normal') {
			htext += '<?=gettext("The default optimization algorithm");?>';
		} else if (val == 'high-latency') {
			htext += '<?=gettext("Used for eg. satellite links. Expires idle connections later than default");?>';
		} else if (val == 'aggressive') {
			htext += '<?=gettext("Expires idle connections quicker. More efficient use of CPU and memory but can drop legitimate idle connections");?>';
		} else if (val == 'conservative') {
			htext += '<?=gettext("Tries to avoid dropping any legitimate idle connections at the expense of increased memory usage and CPU utilization");?>';
		}

		htext += '</span>';
		setHelpText('optimization', htext);
	}

	// ---------- Click checkbox handlers ---------------------------------------------------------

	$('#optimization').on('change', function() {
		setOptText(this.value);
	});

	// ---------- On initial page load ------------------------------------------------------------

	setOptText($('#optimization').val())

});
//]]>
</script>
<?php
include("foot.inc");
?>
