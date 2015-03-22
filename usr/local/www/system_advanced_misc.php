<?php
/* $Id$ */
/*
	system_advanced_misc.php
	part of pfSense
	Copyright (C) 2005-2007 Scott Ullrich
	Copyright (C) 2008 Shrew Soft Inc
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
	pfSense_MODULE:	system
*/

##|+PRIV
##|*IDENT=page-system-advanced-misc
##|*NAME=System: Advanced: Miscellaneous page
##|*DESCR=Allow access to the 'System: Advanced: Miscellaneous' page.
##|*MATCH=system_advanced_misc.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("vpn.inc");
require_once("vslb.inc");

$pconfig['proxyurl'] = $config['system']['proxyurl'];
$pconfig['proxyport'] = $config['system']['proxyport'];
$pconfig['proxyuser'] = $config['system']['proxyuser'];
$pconfig['proxypass'] = $config['system']['proxypass'];
$pconfig['harddiskstandby'] = $config['system']['harddiskstandby'];
$pconfig['lb_use_sticky'] = isset($config['system']['lb_use_sticky']);
$pconfig['srctrack'] = $config['system']['srctrack'];
$pconfig['gw_switch_default'] = isset($config['system']['gw_switch_default']);
$pconfig['powerd_enable'] = isset($config['system']['powerd_enable']);
$pconfig['crypto_hardware'] = $config['system']['crypto_hardware'];
$pconfig['thermal_hardware'] = $config['system']['thermal_hardware'];
$pconfig['schedule_states'] = isset($config['system']['schedule_states']);
$pconfig['kill_states'] = isset($config['system']['kill_states']);
$pconfig['skip_rules_gw_down'] = isset($config['system']['skip_rules_gw_down']);
$pconfig['apinger_debug'] = isset($config['system']['apinger_debug']);
$pconfig['use_mfs_tmpvar'] = isset($config['system']['use_mfs_tmpvar']);
$pconfig['use_mfs_tmp_size'] = $config['system']['use_mfs_tmp_size'];
$pconfig['use_mfs_var_size'] = $config['system']['use_mfs_var_size'];
$pconfig['pkg_nochecksig'] = isset($config['system']['pkg_nochecksig']);

$pconfig['powerd_ac_mode'] = "hadp";
if (!empty($config['system']['powerd_ac_mode']))
	$pconfig['powerd_ac_mode'] = $config['system']['powerd_ac_mode'];

$pconfig['powerd_battery_mode'] = "hadp";
if (!empty($config['system']['powerd_battery_mode']))
	$pconfig['powerd_battery_mode'] = $config['system']['powerd_battery_mode'];

$pconfig['powerd_normal_mode'] = "hadp";
if (!empty($config['system']['powerd_normal_mode']))
	$pconfig['powerd_normal_mode'] = $config['system']['powerd_normal_mode'];

$crypto_modules = array('glxsb' => gettext("AMD Geode LX Security Block"),
			'aesni' => gettext("AES-NI CPU-based Acceleration"));

$thermal_hardware_modules = array(	'coretemp' => gettext("Intel Core* CPU on-die thermal sensor"),
					'amdtemp' => gettext("AMD K8, K10 and K11 CPU on-die thermal sensor"));

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	ob_flush();
	flush();

	if (!empty($_POST['cryptographic-hardware']) && !array_key_exists($_POST['cryptographic-hardware'], $crypto_modules))
		$input_errors[] = gettext("Please select a valid Cryptographic Accelerator.");

	if (!empty($_POST['thermal-sensors']) && !array_key_exists($_POST['thermal-sensors'], $thermal_hardware_modules))
		$input_errors[] = gettext("Please select a valid Thermal Hardware Sensor.");

	if (!empty($_POST['-tmp-ram-disk-size']) && (!is_numeric($_POST['-tmp-ram-disk-size']) || ($_POST['-tmp-ram-disk-size'] < 40)))
		$input_errors[] = gettext("/tmp Size must be numeric and should not be less than 40MB.");

	if (!empty($_POST['-var-ram-disk-size']) && (!is_numeric($_POST['-var-ram-disk-size']) || ($_POST['-var-ram-disk-size'] < 60)))
		$input_errors[] = gettext("/var Size must be numeric and should not be less than 60MB.");

	if (!$input_errors) {

		if($_POST['proxy-url'] != "")
			$config['system']['proxyurl'] = $_POST['proxy-url'];
		else
			unset($config['system']['proxyurl']);

		if($_POST['proxy-port'] != "")
			$config['system']['proxyport'] = $_POST['proxy-port'];
		else
			unset($config['system']['proxyport']);

		if($_POST['proxy-username'] != "")
			$config['system']['proxyuser'] = $_POST['proxy-username'];
		else
			unset($config['system']['proxyuser']);

		if($_POST['proxy-password'] != "")
			$config['system']['proxypass'] = $_POST['proxy-password'];
		else
			unset($config['system']['proxypass']);

		$need_relayd_restart = false;
		if($_POST['use-sticky-connections'] == "yes") {
			if (!isset($config['system']['lb_use_sticky'])) {
				$config['system']['lb_use_sticky'] = true;
				$need_relayd_restart = true;
			}
			if ($config['system']['srctrack'] != $_POST['source-tracking-timeout']) {
				$config['system']['srctrack'] = $_POST['source-tracking-timeout'];
				$need_relayd_restart = true;
			}
		} else {
			if (isset($config['system']['lb_use_sticky'])) {
				unset($config['system']['lb_use_sticky']);
				$need_relayd_restart = true;
			}
		}

		if($_POST['default-gateway-switching'] == "yes")
			$config['system']['gw_switch_default'] = true;
		else
			unset($config['system']['gw_switch_default']);

		if($_POST['package-signature'] == "yes")
			$config['system']['pkg_nochecksig'] = true;
		elseif (isset($config['system']['pkg_nochecksig']))
			unset($config['system']['pkg_nochecksig']);

		if($_POST['powerd'] == "yes")
			$config['system']['powerd_enable'] = true;
		else
			unset($config['system']['powerd_enable']);

		$config['system']['powerd_ac_mode'] = $_POST['ac-power'];
		$config['system']['powerd_battery_mode'] = $_POST['battery-power'];
		$config['system']['powerd_normal_mode'] = $_POST['unknown-power'];

		if($_POST['cryptographic-hardware'])
			$config['system']['crypto_hardware'] = $_POST['cryptographic-hardware'];
		else
			unset($config['system']['crypto_hardware']);

		if($_POST['thermal-sensors'])
			$config['system']['thermal_hardware'] = $_POST['thermal-sensors'];
		else
			unset($config['system']['thermal_hardware']);

		if($_POST['schedule-states'] == "yes")
			$config['system']['schedule_states'] = true;
		else
			unset($config['system']['schedule_states']);

		if($_POST['state-killing-on-gateway-failure'] == "yes")
			$config['system']['kill_states'] = true;
		else
			unset($config['system']['kill_states']);

		if($_POST['skip-rules-when-gateway-is-down'] == "yes")
			$config['system']['skip_rules_gw_down'] = true;
		else
			unset($config['system']['skip_rules_gw_down']);

		$need_apinger_restart = false;
		if($_POST['gateway-monitoring-logging'] == "yes") {
			if (!isset($config['system']['apinger_debug']))
				$need_apinger_restart = true;
			$config['system']['apinger_debug'] = true;
		} else {
			if (isset($config['system']['apinger_debug']))
				$need_apinger_restart = true;
			unset($config['system']['apinger_debug']);
		}

		if($_POST['use-ram-disks'] == "yes")
			$config['system']['use_mfs_tmpvar'] = true;
		else
			unset($config['system']['use_mfs_tmpvar']);

		$config['system']['use_mfs_tmp_size'] = $_POST['-tmp-ram-disk-size'];
		$config['system']['use_mfs_var_size'] = $_POST['-var-ram-disk-size'];

		if (isset($_POST['periodic-rrd-backup'])) {
			$config['system']['rrdbackup'] = $_POST['periodic-rrd-backup'];
			install_cron_job("/etc/rc.backup_rrd.sh", ($config['system']['rrdbackup'] > 0), $minute="0", "*/{$config['system']['rrdbackup']}");
		}
		if (isset($_POST['periodic-dhcp-leases-backup'])) {
			$config['system']['dhcpbackup'] = $_POST['periodic-dhcp-leases-backup'];
			install_cron_job("/etc/rc.backup_dhcpleases.sh", ($config['system']['dhcpbackup'] > 0), $minute="0", "*/{$config['system']['dhcpbackup']}");
		}

		write_config();

		$retval = 0;
		system_resolvconf_generate(true);
		$retval = filter_configure();
		if(stristr($retval, "error") <> true)
			$savemsg = get_std_save_message(gettext($retval));
		else
			$savemsg = gettext($retval);

		activate_powerd();
		load_crypto();
		load_thermal_hardware();
		if ($need_relayd_restart)
			relayd_configure();
		if ($need_apinger_restart)
			setup_gateways_monitor();
	}
}

$pgtitle = array(gettext("System"),gettext("Advanced: Miscellaneous"));
include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);

$tab_array = array();
$tab_array[] = array(gettext("Admin Access"), false, "system_advanced_admin.php");
$tab_array[] = array(gettext("Firewall / NAT"), false, "system_advanced_firewall.php");
$tab_array[] = array(gettext("Networking"), false, "system_advanced_network.php");
$tab_array[] = array(gettext("Miscellaneous"), true, "system_advanced_misc.php");
$tab_array[] = array(gettext("System Tunables"), false, "system_advanced_sysctl.php");
$tab_array[] = array(gettext("Notifications"), false, "system_advanced_notifications.php");
display_top_tabs($tab_array);

?><div id="container"><?php

require('classes/Form.class.php');
$form = new Form;
$section = new Form_Section('Proxy support');

$section->addInput(new Form_Input(
	'proxy-url',
	'Proxy URL',
	'text',
	$pconfig['proxyurl']
))->setHelp('Hostname or IP address of proxy server this system will '.
	'use for its outbound Internet access.');

$section->addInput(new Form_Input(
	'proxy-port',
	'Proxy Port',
	'text',
	$pconfig['proxyport']
))->setHelp('Port where proxy server is listening.');

$section->addInput(new Form_Input(
	'proxy-username',
	'Proxy Username',
	'text',
	$pconfig['proxyuser']
))->setHelp('Username for authentication to proxy server. Optional, '.
	'leave blank to not use authentication.');

$section->addInput(new Form_Input(
	'proxy-password',
	'Proxy Password',
	'text',
	$pconfig['proxypass']
))->setHelp('Password for authentication to proxy server.');

$form->add($section);
$section = new Form_Section('Load Balancing');

$group = new Form_Group('Load Balancing');

$group->add(new Form_Checkbox(
	'use-sticky-connections',
	'Use sticky connections',
	'Use sticky connections',
	$pconfig['lb_use_sticky']
))->setHelp('Successive connections will be redirected to the servers in a '.
	'round-robin manner with connections from the same source being sent to the '.
	'same web server. This "sticky connection" will exist as long as there are '.
	'states that refer to this connection. Once the states expire, so will the '.
	'sticky connection. Further connections from that host will be redirected '.
	'to the next web server in the round robin. Changing this option will '.
	'restart the Load Balancing service.');

$group->add(new Form_Input(
	'source-tracking-timeout',
	'Source tracking timeout',
	'number',
	$pconfig['srctrack'],
	['placeholder' => 1400]
))->setHelp('Set the source tracking timeout for sticky connections. By default '.
	'this is 0, so source tracking is removed as soon as the state expires. '.
	'Setting this timeout higher will cause the source/destination relationship '.
	'to persist for longer periods of time.');

$section->add($group);

$section->addInput(new Form_Checkbox(
	'default-gateway-switching',
	'Default gateway switching',
	'Enable default gateway switching',
	$pconfig['gw_switch_default']
))->setHelp('If the default gateway goes down, switch the default gateway to '.
	'another available one. This is not enabled by default, as it"s unnecessary in '.
	'most all scenarios, which instead use gateway groups.');

$form->add($section);
$section = new Form_Section('Power savings');

$section->addInput(new Form_Checkbox(
	'powerd',
	'PowerD',
	'Enable PowerD',
	$pconfig['powerd_enable']
))->setHelp('The powerd utility monitors '.
	'the system state and sets various power control options accordingly.  It offers '.
	'four modes (maximum, minimum, adaptive and hiadaptive) that can be individually '.
	'selected while on AC power or batteries. The modes maximum, minimum, adaptive '.
	'and hiadaptive may be abbreviated max, min, adp, hadp.  Maximum mode chooses the '.
	'highest performance values.  Minimum mode selects the lowest performance values '.
	'to get the most power savings. Adaptive mode attempts to strike a balance by '.
	'degrading performance when the system appears idle and increasing it when the '.
	'system is busy.  It offers a good balance between a small performance loss for '.
	'greatly increased power savings.  Hiadaptive mode is alike adaptive mode, but '.
	'tuned for systems where performance and interactivity are more important than '.
	'power consumption.  It raises frequency faster, drops slower and keeps twice '.
	'lower CPU load.');

$modes = array(
	'hadp' => 'Hiadaptive',
	'adp' => 'Adaptive',
	'min' => 'Minimum',
	'max' => 'Maximum',
);

$section->addInput(new Form_Select(
	'ac-power',
	'AC Power',
	$pconfig['powerd_ac_mode'],
	$modes
));

$section->addInput(new Form_Select(
	'battery-power',
	'Battery Power',
	$pconfig['powerd_battery_mode'],
	$modes
));

$section->addInput(new Form_Select(
	'unknown-power',
	'Unknown Power',
	$pconfig['powerd_normal_mode'],
	$modes
));

$form->add($section);
$section = new Form_Section('Cryptographic & Thermal Hardware');

$section->addInput(new Form_Select(
	'cryptographic-hardware',
	'Cryptographic Hardware',
	$pconfig['crypto_hardware'],
	$crypto_modules
))->setHelp('A cryptographic '.
	'accelerator module will use hardware support to speed up some cryptographic '.
	'functions on systems which have the chip. Do not enable this option if you have '.
	'a Hifn cryptographic acceleration card, as this will take precedence and the '.
	'Hifn card will not be used. Acceleration should be automatic for IPsec when '.
	'using a cipher supported by your chip, such as AES-128. OpenVPN should be set '.
	'for AES-128-CBC and have cryptodev enabled for hardware acceleration.If you do '.
	'not have a crypto chip in your system, this option will have no effect. To '.
	'unload the selected module, set this option to "none" and then reboot.');

$section->addInput(new Form_Select(
	'thermal-sensors',
	'Thermal Sensors',
	$pconfig['thermal_hardware'],
	array('' => 'None/ACPI') + $thermal_hardware_modules
))->setHelp('If you have a '.
	'supported CPU, selecting a themal sensor will load the appropriate driver to '.
	'read its temperature. Setting this to "None" will attempt to read the '.
	'temperature from an ACPI-compliant motherboard sensor instead, if one is '.
	'present.If you do not have a supported thermal sensor chip in your system, this '.
	'option will have no effect. To unload the selected module, set this option to '.
	'"none" and then reboot.');

$form->add($section);
$section = new Form_Section('Schedules');

$section->addInput(new Form_Checkbox(
	'schedule-states',
	'Schedule States',
	'Do not kill connections when schedule expires',
	$pconfig['schedule_states']
))->setHelp('By default, when a schedule expires, connections permitted by that '.
	'schedule are killed. This option overrides that behavior by not clearing states '.
	'for existing connections.');

$form->add($section);
$section = new Form_Section('Gateway Monitoring');

$section->addInput(new Form_Checkbox(
	'state-killing-on-gateway-failure',
	'State Killing on Gateway Failure',
	'Flush states for a gateway that goes down',
	$pconfig['kill_states']
))->setHelp('The monitoring process will flush states for a gateway that goes down '.
	'if this box is not checked. Check this box to disable this behavior.');

$section->addInput(new Form_Checkbox(
	'skip-rules-when-gateway-is-down',
	'Skip rules when gateway is down',
	'Do not create rules when gateway is down',
	$pconfig['skip_rules_gw_down']
))->setHelp('By default, when a rule has a gateway specified and this gateway is '.
	'down, the rule is created omitting the gateway. This option overrides that '.
	'behavior by omitting the entire rule instead.');

$section->addInput(new Form_Checkbox(
	'gateway-monitoring-logging',
	'Gateway monitoring logging',
	'Enable debug logging',
	$pconfig['apinger_debug']
))->setHelp('Enable this setting to log debug information from the gateway '.
	'monitoring process to the system logs.');

$form->add($section);
$section = new Form_Section('RAM Disk Settings (Reboot to Apply Changes)');

$section->addInput(new Form_Checkbox(
	'use-ram-disks',
	'Use RAM Disks',
	'Use memory file system for /tmp and /var',
	($pconfig['use_mfs_tmpvar'] || $g['platform'] != "pfSense")
))->setHelp('Set this if you wish to use /tmp and /var as RAM disks (memory file '.
	'system disks) on a full install rather than use the hard disk. Setting this will '.
	'cause the data in /tmp and /var to be lost at reboot, including log data. RRD '.
	'and DHCP Leases will be retained.');

$section->addInput(new Form_Input(
	'-tmp-ram-disk-size',
	'/tmp RAM Disk Size',
	'number',
	$pconfig['use_mfs_tmp_size'],
	['placeholder' => 40]
))->setHelp('Set the size, in MB, for the /tmp '.
	'RAM disk. Leave blank for 40MB. Do not set lower than 40.');

$section->addInput(new Form_Input(
	'-var-ram-disk-size',
	'/var RAM Disk Size',
	'number',
	$pconfig['use_mfs_var_size'],
	['placeholder' => 60]
))->setHelp('Set the size, in MB, for the /var '.
	'RAM disk. Leave blank for 60MB. Do not set lower than 60.');

$section->addInput(new Form_Input(
	'periodic-rrd-backup',
	'Periodic RRD Backup',
	'number',
	$config['system']['rrdbackup'],
	['min' => 1, 'max' => 24, 'placeholder' => 'frequency between 1 and 24 hours']
))->setHelp('This will periodically backup the RRD data so '.
	'it can be restored automatically on the next boot. Keep in mind that the more '.
	'frequent the backup, the more writes will happen to your media.');

$section->addInput(new Form_Input(
	'periodic-dhcp-leases-backup',
	'Periodic DHCP Leases Backup',
	'number',
	$config['system']['rrdbackup'],
	['min' => 1, 'max' => 24, 'placeholder' => 'frequency between 1 and 24 hours']
))->setHelp('This will periodically backup the DHCP leases so '.
	'it can be restored automatically on the next boot. Keep in mind that the more '.
	'frequent the backup, the more writes will happen to your media.');

$form->add($section);
$section = new Form_Section('Package settings');

$section->addInput(new Form_Checkbox(
	'package-signature',
	'Package signature',
	'Disable check package signature',
	$pconfig['pkg_nochecksig']
))->setHelp('Enable this option to allow pfSense to install any package without '.
	'checking its signature.');

$form->add($section);
print $form;

include("foot.inc");