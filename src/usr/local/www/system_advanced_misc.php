<?php
/*
 * system_advanced_misc.php
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
##|*IDENT=page-system-advanced-misc
##|*NAME=System: Advanced: Miscellaneous
##|*DESCR=Allow access to the 'System: Advanced: Miscellaneous' page.
##|*MATCH=system_advanced_misc.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("vpn.inc");
include_once("system_advanced_misc.inc");

$powerd_modes = array(
	'hadp' => gettext('Hiadaptive'),
	'adp' => gettext('Adaptive'),
	'min' => gettext('Minimum'),
	'max' => gettext('Maximum'),
);

$mds_modes = array(
	'' => gettext('Default'),
	0 => gettext('Mitigation disabled'),
	1 => gettext('VERW instruction (microcode) mitigation enabled'),
	2 => gettext('Software sequence mitigation enabled (not recommended)'),
	3 => gettext('Automatic VERW or Software selection'),
);


$pconfig = getSystemAdvancedMisc();

$crypto_modules = array(
	'aesni' => gettext("AES-NI CPU-based Acceleration"),
	'cryptodev' => gettext("BSD Crypto Device (cryptodev)"),
	'aesni_cryptodev' => gettext("AES-NI and BSD Crypto Device (aesni, cryptodev)"),
);

$thermal_hardware_modules = array(
	'coretemp' => gettext("Intel Core* CPU on-die thermal sensor"),
	'amdtemp' => gettext("AMD K8, K10 and K11 CPU on-die thermal sensor")
);

$rebootneeded = false;

if ($_POST) {
	ob_flush();
	flush();

	$rv = saveSystemAdvancedMisc($_POST);

	$pconfig = $rv['post'];
	$input_errors = $rv['input_errors'];
	$retval = $rv['retval'];
	$changes_applied = $rv['changes_applied'];
	$rebootneeded = $rv['reboot'];
} else {
	$pconfig = getSystemAdvancedMisc();
}

$pgtitle = array(gettext("System"), gettext("Advanced"), gettext("Miscellaneous"));
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
$tab_array[] = array(gettext("Networking"), false, "system_advanced_network.php");
$tab_array[] = array(gettext("Miscellaneous"), true, "system_advanced_misc.php");
$tab_array[] = array(gettext("System Tunables"), false, "system_advanced_sysctl.php");
$tab_array[] = array(gettext("Notifications"), false, "system_advanced_notifications.php");
display_top_tabs($tab_array);

$form = new Form;
$section = new Form_Section('Proxy Support');

$section->addInput(new Form_Input(
	'proxyurl',
	'Proxy URL',
	'text',
	$pconfig['proxyurl']
))->setHelp('Hostname or IP address of proxy server this system will '.
	'use for its outbound Internet access.');

$section->addInput(new Form_Input(
	'proxyport',
	'Proxy Port',
	'text',
	$pconfig['proxyport']
))->setHelp('Port where proxy server is listening.');

$section->addInput(new Form_Input(
	'proxyuser',
	'Proxy Username',
	'text',
	$pconfig['proxyuser'],
	['autocomplete' => 'new-password']
))->setHelp('Username for authentication to proxy server. Optional, '.
	'leave blank to not use authentication.');

$section->addPassword(new Form_Input(
	'proxypass',
	'Proxy Password',
	'password',
	$pconfig['proxypass']
))->setHelp('Password for authentication to proxy server.');

$form->add($section);
$section = new Form_Section('Load Balancing');

$group = new Form_Group('Load Balancing');

$group->add(new Form_Checkbox(
	'lb_use_sticky',
	'Use sticky connections',
	'Use sticky connections',
	$pconfig['lb_use_sticky']
))->setHelp('Successive connections will be redirected via gateways in a '.
	'round-robin manner with connections from the same source being sent via the '.
	'same gateway. This "sticky connection" will exist as long as there are '.
	'states that refer to this connection. Once the states expire, so will the '.
	'sticky connection. Further connections from that host will be redirected '.
	'via the next gateway in the round robin.');

$group->add(new Form_Input(
	'srctrack',
	'Source tracking timeout',
	'number',
	$pconfig['srctrack'],
	["placeholder" => "0"]
))->setHelp('Set the source tracking timeout for sticky connections in seconds. By default '.
	'this is 0, so source tracking is removed as soon as the state expires. '.
	'Setting this timeout higher will cause the source/destination relationship '.
	'to persist for longer periods of time.');

$section->add($group);

$form->add($section);
$section = new Form_Section('Power Savings');

$section->addInput(new Form_Checkbox(
	'powerd_enable',
	'PowerD',
	'Enable PowerD',
	$pconfig['powerd_enable']
))->setHelp('The powerd utility monitors '.
	'the system state and sets various power control options accordingly.  It offers '.
	'four modes (maximum, minimum, adaptive and hiadaptive) that can be individually '.
	'selected while on AC power or batteries. The modes maximum, minimum, adaptive '.
	'and hiadaptive may be abbreviated max, min, adp, hadp.	 Maximum mode chooses the '.
	'highest performance values.  Minimum mode selects the lowest performance values '.
	'to get the most power savings. Adaptive mode attempts to strike a balance by '.
	'degrading performance when the system appears idle and increasing it when the '.
	'system is busy.  It offers a good balance between a small performance loss for '.
	'greatly increased power savings.  Hiadaptive mode is alike adaptive mode, but '.
	'tuned for systems where performance and interactivity are more important than '.
	'power consumption.	 It raises frequency faster, drops slower and keeps twice '.
	'lower CPU load.');

$section->addInput(new Form_Select(
	'powerd_ac_mode',
	'AC Power',
	$pconfig['powerd_ac_mode'],
	$powerd_modes
));

$section->addInput(new Form_Select(
	'powerd_battery_mode',
	'Battery Power',
	$pconfig['powerd_battery_mode'],
	$powerd_modes
));

$section->addInput(new Form_Select(
	'powerd_normal_mode',
	'Unknown Power',
	$pconfig['powerd_normal_mode'],
	$powerd_modes
));

$form->add($section);
$section = new Form_Section('Cryptographic & Thermal Hardware');

$section->addInput(new Form_Select(
	'crypto_hardware',
	'Cryptographic Hardware',
	$pconfig['crypto_hardware'],
	['' => gettext('None')] + $crypto_modules
))->setHelp('A cryptographic accelerator module will use hardware support to speed up some cryptographic '.
	'functions on systems which have the chip. '.
	'Loading the BSD Crypto Device module will allow access to acceleration devices using drivers '.
	'built into the kernel, such as Hifn or ubsec chipsets. '.
	'If the firewall does not contain a crypto chip, this option will have no effect. '.
	'To unload the selected module, set this option to "none" and then reboot.');

$section->addInput(new Form_Select(
	'thermal_hardware',
	'Thermal Sensors',
	$pconfig['thermal_hardware'],
	array('' => 'None/ACPI') + $thermal_hardware_modules
))->setHelp('With a '.
	'supported CPU, selecting a thermal sensor will load the appropriate driver to '.
	'read its temperature. Setting this to "None" will attempt to read the '.
	'temperature from an ACPI-compliant motherboard sensor instead, if one is '.
	'present. If there is not a supported thermal sensor chip in the system, this '.
	'option will have no effect. To unload the selected module, set this option to '.
	'"none" and then reboot.');

$form->add($section);

$pti = get_single_sysctl('vm.pmap.pti');
if (strlen($pti) > 0) {
	$section = new Form_Section('Kernel Page Table Isolation');
	$section->addInput(new Form_Checkbox(
		'pti_disabled',
		'Kernel PTI',
		'Forcefully disable the kernel PTI',
		$pconfig['pti_disabled']
	))->setHelp('Meltdown workaround. If disabled the kernel memory can be accessed by unprivileged users on affected CPUs. ' .
		    'This option forces the workaround off, and requires a reboot to activate. %1$s%1$s' .
		    'PTI is active by default only on affected CPUs, if PTI is disabled by default then this option will have no effect. %1$s' .
		    'Current PTI status: %2$s', "<br/>", ($pti == "1") ? "Enabled" : "Disabled");
	$form->add($section);
}

$mds = get_single_sysctl('hw.mds_disable_state');
if (strlen($mds) > 0) {
	$section = new Form_Section('Microarchitectural Data Sampling Mitigation');
	$section->addInput(new Form_Select(
		'mds_disable',
		'MDS Mode',
		$pconfig['mds_disable'],
		$mds_modes
	))->setHelp('Microarchitectural Data Sampling mitigation. If disabled the kernel memory can be accessed by unprivileged users on affected CPUs. ' .
		    'This option controls which method of MDS mitigation is used, if any. %1$s%1$s' .
		    'Current MDS status: %2$s', "<br/>", ucwords(htmlspecialchars($mds)));
	$form->add($section);
}

$section = new Form_Section('Schedules');

$section->addInput(new Form_Checkbox(
	'schedule_states',
	'Schedule States',
	'Do not kill connections when schedule expires',
	$pconfig['schedule_states']
))->setHelp('By default, when a schedule expires, connections permitted by that '.
	'schedule are killed. This option overrides that behavior by not clearing states '.
	'for existing connections.');

$form->add($section);
$section = new Form_Section('Gateway Monitoring');

$section->addInput(new Form_Checkbox(
	'gw_down_kill_states',
	'State Killing on Gateway Failure',
	'Flush all states when a gateway goes down',
	$pconfig['gw_down_kill_states']
))->setHelp('The monitoring process will flush all states when a gateway goes down '.
	'if this box is checked.');

$section->addInput(new Form_Checkbox(
	'skip_rules_gw_down',
	'Skip rules when gateway is down',
	'Do not create rules when gateway is down',
	$pconfig['skip_rules_gw_down']
))->setHelp('By default, when a rule has a gateway specified and this gateway is '.
	'down, the rule is created omitting the gateway. This option overrides that '.
	'behavior by omitting the entire rule instead.');

$form->add($section);
$section = new Form_Section('RAM Disk Settings (Reboot to Apply Changes)');

$section->addInput(new Form_Checkbox(
	'use_mfs_tmpvar',
	'Use RAM Disks',
	'Use memory file system for /tmp and /var',
	$pconfig['use_mfs_tmpvar']
))->setHelp('Set this to use /tmp and /var as RAM disks (memory file '.
	'system disks) on a full install rather than use the hard disk. Setting this will '.
	'cause the data in /tmp and /var to be lost. RRD, '.
	'DHCP leases and log directory will be retained. Changing this setting will cause the firewall to reboot after clicking "Save".');

$group = new Form_Group('RAM Disk Size');

$group->add(new Form_Input(
	'use_mfs_tmp_size',
	'/tmp RAM Disk Size',
	'number',
	$pconfig['use_mfs_tmp_size'],
	['placeholder' => 40]
))->setHelp('/tmp RAM Disk<br />Do not set lower than 40.');

$group->add(new Form_Input(
	'use_mfs_var_size',
	'/var RAM Disk Size',
	'number',
	$pconfig['use_mfs_var_size'],
	['placeholder' => 60]
))->setHelp('/var RAM Disk<br />Do not set lower than 60.');

$group->setHelp('Sets the size, in MiB, for the RAM disks. ' .
	'Ensure each RAM disk is large enough to contain the current contents of the directories in question. %s' .
	'Maximum total size of all RAM disks cannot exceed available kernel memory: %s',
	'<br/>', format_bytes( $pconfig['available_kernel_memory']));

$section->add($group);

$group = new Form_Group('Periodic RAM Disk Data Backups');

$group->add(new Form_Input(
	'rrdbackup',
	'Periodic RRD Backup',
	'number',
	$config['system']['rrdbackup'],
	['min' => 0, 'max' => 24, 'placeholder' => '1 to 24 hours']
))->setHelp('RRD Data');

$group->add(new Form_Input(
	'dhcpbackup',
	'Periodic DHCP Leases Backup',
	'number',
	$config['system']['dhcpbackup'],
	['min' => 0, 'max' => 24, 'placeholder' => '1 to 24 hours']
))->setHelp('DHCP Leases');

$group->add(new Form_Input(
	'logsbackup',
	'Periodic Logs Backup',
	'number',
	$config['system']['logsbackup'],
	['min' => 0, 'max' => 24, 'placeholder' => '1 to 24 hours']
))->setHelp('Log Directory');

$group->setHelp('Sets the interval, in hours, to periodically backup these portions of RAM disk data so '.
	'they can be restored automatically on the next boot. Keep in mind that the more '.
	'frequent the backup, the more writes will happen to the media.');

$section->add($group);

$form->add($section);

$section = new Form_Section('Hardware Settings');

$opts = array(0.5,  1, 2,  3,  4,  5,  7.5,  10,  15,  20,  30,  60);
$vals = array(  6, 12, 24, 36, 48, 60,  90, 120, 180, 240, 241, 242);

$section->addInput(new Form_Select(
	'harddiskstandby',
	'Hard disk standby time',
	$pconfig['harddiskstandby'],
	['' => gettext("Always on")] + array_combine($opts, $vals)
))->setHelp('Puts the hard disk into standby mode when the selected number of minutes has elapsed since the last access.%1$s' .
			'%2$sDo not set this for CF cards.%3$s', '<br />', '<strong>', '</strong>');

$form->add($section);

$section = new Form_Section('Installation Feedback');

$section->addInput(new Form_Checkbox(
	'do_not_send_uniqueid',
	'Netgate Device ID',
	'Do NOT send Netgate Device ID with user agent',
	$pconfig['do_not_send_uniqueid']
))->setHelp('Enable this option to not send Netgate Device ID as part of User-Agent header.');

$form->add($section);

print $form;

$ramdisk_msg = gettext('The \"Use Ramdisk\" setting has been changed. This requires the firewall\nto reboot.\n\nReboot now ?');
$use_mfs_tmpvar_changed = $rebootneeded;
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// Has the Use ramdisk checkbox changed state?
	if (<?=(int)$use_mfs_tmpvar_changed?> && confirm("<?=$ramdisk_msg?>")) {
		postSubmit({override : 'yes'}, 'diag_reboot.php')
	}

	// source track timeout field is disabled if sticky connections not enabled
	$('#lb_use_sticky').click(function () {
		disableInput('srctrack', !$(this).prop("checked"));
	});

	disableInput('srctrack', !$('#lb_use_sticky').prop("checked"));

});
//]]>
</script>

<?php
include("foot.inc");
