<?php
/*
	system_advanced_misc.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2008 Shrew Soft Inc
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
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
##|*IDENT=page-system-advanced-misc
##|*NAME=System: Advanced: Miscellaneous
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
$pconfig['use_mfs_tmpvar'] = isset($config['system']['use_mfs_tmpvar']);
$pconfig['use_mfs_tmp_size'] = $config['system']['use_mfs_tmp_size'];
$pconfig['use_mfs_var_size'] = $config['system']['use_mfs_var_size'];
$pconfig['do_not_send_host_uuid'] = isset($config['system']['do_not_send_host_uuid']);

$pconfig['powerd_ac_mode'] = "hadp";
if (!empty($config['system']['powerd_ac_mode'])) {
	$pconfig['powerd_ac_mode'] = $config['system']['powerd_ac_mode'];
}

$pconfig['powerd_battery_mode'] = "hadp";
if (!empty($config['system']['powerd_battery_mode'])) {
	$pconfig['powerd_battery_mode'] = $config['system']['powerd_battery_mode'];
}

$pconfig['powerd_normal_mode'] = "hadp";
if (!empty($config['system']['powerd_normal_mode'])) {
	$pconfig['powerd_normal_mode'] = $config['system']['powerd_normal_mode'];
}

$crypto_modules = array(
	'glxsb' => gettext("AMD Geode LX Security Block"),
	'aesni' => gettext("AES-NI CPU-based Acceleration"));

$thermal_hardware_modules = array(
	'coretemp' => gettext("Intel Core* CPU on-die thermal sensor"),
	'amdtemp' => gettext("AMD K8, K10 and K11 CPU on-die thermal sensor"));

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	ob_flush();
	flush();

	if (!empty($_POST['crypto_hardware']) && !array_key_exists($_POST['crypto_hardware'], $crypto_modules)) {
		$input_errors[] = gettext("Please select a valid Cryptographic Accelerator.");
	}

	if (!empty($_POST['thermal_hardware']) && !array_key_exists($_POST['thermal_hardware'], $thermal_hardware_modules)) {
		$input_errors[] = gettext("Please select a valid Thermal Hardware Sensor.");
	}

	if (!empty($_POST['use_mfs_tmp_size']) && (!is_numeric($_POST['use_mfs_tmp_size']) || ($_POST['use_mfs_tmp_size'] < 40))) {
		$input_errors[] = gettext("/tmp Size must be numeric and should not be less than 40MB.");
	}

	if (!empty($_POST['use_mfs_var_size']) && (!is_numeric($_POST['use_mfs_var_size']) || ($_POST['use_mfs_var_size'] < 60))) {
		$input_errors[] = gettext("/var Size must be numeric and should not be less than 60MB.");
	}

	if (!empty($_POST['proxyport']) && !is_port($_POST['proxyport'])) {
		$input_errors[] = gettext("Proxy port must be a valid port number, 1-65535.");
	}

	if (!empty($_POST['proxyurl']) && !is_fqdn($_POST['proxyurl']) && !is_ipaddr($_POST['proxyurl'])) {
		$input_errors[] = gettext("Proxy URL must be a valid IP address or FQDN.");
	}

	if (!empty($_POST['proxyuser']) && preg_match("/[^a-zA-Z0-9\.\-_@]/", $_POST['proxyuser'])) {
		$input_errors[] = gettext("The proxy username contains invalid characters.");
	}

	if($_POST['proxypass'] != $_POST['proxypass_confirm']) {
		$input_errors[] = gettext("Proxy password and confirmation must match.");
	}

	if (!$input_errors) {

		if ($_POST['harddiskstandby'] <> "") {
			$config['system']['harddiskstandby'] = $_POST['harddiskstandby'];
			system_set_harddisk_standby();
		} else {
			unset($config['system']['harddiskstandby']);
		}

		if ($_POST['proxyurl'] <> "") {
			$config['system']['proxyurl'] = $_POST['proxyurl'];
		} else {
			unset($config['system']['proxyurl']);
		}

		if ($_POST['proxyport'] <> "") {
			$config['system']['proxyport'] = $_POST['proxyport'];
		} else {
			unset($config['system']['proxyport']);
		}

		if ($_POST['proxyuser'] <> "") {
			$config['system']['proxyuser'] = $_POST['proxyuser'];
		} else {
			unset($config['system']['proxyuser']);
		}

		if ($_POST['proxypass'] <> "") {
			if ($_POST['proxypass'] != DMYPWD) {
				$config['system']['proxypass'] = $_POST['proxypass'];
			}
		} else {
			unset($config['system']['proxypass']);
		}

		$need_relayd_restart = false;
		if ($_POST['lb_use_sticky'] == "yes") {
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

		if ($_POST['gw_switch_default'] == "yes") {
			$config['system']['gw_switch_default'] = true;
		} else {
			unset($config['system']['gw_switch_default']);
		}

		if ($_POST['pkg_nochecksig'] == "yes") {
			$config['system']['pkg_nochecksig'] = true;
		} elseif (isset($config['system']['pkg_nochecksig'])) {
			unset($config['system']['pkg_nochecksig']);
		}

		if ($_POST['do_not_send_host_uuid'] == "yes") {
			$config['system']['do_not_send_host_uuid'] = true;
		} else {
			unset($config['system']['do_not_send_host_uuid']);
		}

		if ($_POST['powerd_enable'] == "yes") {
			$config['system']['powerd_enable'] = true;
		} else {
			unset($config['system']['powerd_enable']);
		}

		$config['system']['powerd_ac_mode'] = $_POST['ac-power'];
		$config['system']['powerd_battery_mode'] = $_POST['battery-power'];
		$config['system']['powerd_normal_mode'] = $_POST['unknown-power'];

		if ($_POST['crypto_hardware']) {
			$config['system']['crypto_hardware'] = $_POST['crypto_hardware'];
		} else {
			unset($config['system']['crypto_hardware']);
		}

		if ($_POST['thermal_hardware']) {
			$config['system']['thermal_hardware'] = $_POST['thermal_hardware'];
		} else {
			unset($config['system']['thermal_hardware']);
		}

		if ($_POST['schedule_states'] == "yes") {
			$config['system']['schedule_states'] = true;
		} else {
			unset($config['system']['schedule_states']);
		}

		if ($_POST['kill_states'] == "yes") {
			$config['system']['kill_states'] = true;
		} else {
			unset($config['system']['kill_states']);
		}

		if ($_POST['skip_rules_gw_down'] == "yes") {
			$config['system']['skip_rules_gw_down'] = true;
		} else {
			unset($config['system']['skip_rules_gw_down']);
		}

		if ($_POST['use_mfs_tmpvar'] == "yes") {
			$config['system']['use_mfs_tmpvar'] = true;
		} else {
			unset($config['system']['use_mfs_tmpvar']);
		}

		$config['system']['use_mfs_tmp_size'] = $_POST['use_mfs_tmp_size'];
		$config['system']['use_mfs_var_size'] = $_POST['use_mfs_var_size'];

		if (isset($_POST['rrdbackup'])) {
			$config['system']['rrdbackup'] = $_POST['rrdbackup'];
			install_cron_job("/etc/rc.backup_rrd.sh", ($config['system']['rrdbackup'] > 0), $minute="0", "*/{$config['system']['rrdbackup']}");
		}
		if (isset($_POST['dhcpbackup'])) {
			$config['system']['dhcpbackup'] = $_POST['dhcpbackup'];
			install_cron_job("/etc/rc.backup_dhcpleases.sh", ($config['system']['dhcpbackup'] > 0), $minute="0", "*/{$config['system']['dhcpbackup']}");
		}

		write_config();

		$retval = 0;
		system_resolvconf_generate(true);
		$retval = filter_configure();
		if (stristr($retval, "error") <> true) {
			$savemsg = get_std_save_message(gettext($retval));
		} else {
			$savemsg = gettext($retval);
		}

		activate_powerd();
		load_crypto();
		load_thermal_hardware();
		if ($need_relayd_restart) {
			relayd_configure();
		}
	}
}

$pgtitle = array(gettext("System"), gettext("Advanced"), gettext("Miscellaneous"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
$tab_array[] = array(gettext("Admin Access"), false, "system_advanced_admin.php");
$tab_array[] = array(gettext("Firewall / NAT"), false, "system_advanced_firewall.php");
$tab_array[] = array(gettext("Networking"), false, "system_advanced_network.php");
$tab_array[] = array(gettext("Miscellaneous"), true, "system_advanced_misc.php");
$tab_array[] = array(gettext("System Tunables"), false, "system_advanced_sysctl.php");
$tab_array[] = array(gettext("Notifications"), false, "system_advanced_notifications.php");
display_top_tabs($tab_array);

$form = new Form;
$section = new Form_Section('Proxy support');

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
	$pconfig['proxyuser']
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
))->setHelp('Successive connections will be redirected to the servers in a '.
	'round-robin manner with connections from the same source being sent to the '.
	'same web server. This "sticky connection" will exist as long as there are '.
	'states that refer to this connection. Once the states expire, so will the '.
	'sticky connection. Further connections from that host will be redirected '.
	'to the next web server in the round robin. Changing this option will '.
	'restart the Load Balancing service.');

$group->add(new Form_Input(
	'srctrack',
	'Source tracking timeout',
	'number',
	$pconfig['srctrack'],
	['placeholder' => 0]
))->setHelp('Set the source tracking timeout for sticky connections. By default '.
	'this is 0, so source tracking is removed as soon as the state expires. '.
	'Setting this timeout higher will cause the source/destination relationship '.
	'to persist for longer periods of time.');

$section->add($group);

$section->addInput(new Form_Checkbox(
	'gw_switch_default',
	'Default gateway switching',
	'Enable default gateway switching',
	$pconfig['gw_switch_default']
))->setHelp('If the default gateway goes down, switch the default gateway to '.
	'another available one. This is not enabled by default, as it\'s unnecessary in '.
	'most all scenarios, which instead use gateway groups.');

$form->add($section);
$section = new Form_Section('Power savings');

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

$modes = array(
	'hadp' => 'Hiadaptive',
	'adp' => 'Adaptive',
	'min' => 'Minimum',
	'max' => 'Maximum',
);

$section->addInput(new Form_Select(
	'powerd_ac_mode',
	'AC Power',
	$pconfig['powerd_ac_mode'],
	$modes
));

$section->addInput(new Form_Select(
	'powerd_battery_mode',
	'Battery Power',
	$pconfig['powerd_battery_mode'],
	$modes
));

$section->addInput(new Form_Select(
	'powerd_normal_mode',
	'Unknown Power',
	$pconfig['powerd_normal_mode'],
	$modes
));

$form->add($section);
$section = new Form_Section('Cryptographic & Thermal Hardware');

$section->addInput(new Form_Select(
	'crypto_hardware',
	'Cryptographic Hardware',
	$pconfig['crypto_hardware'],
	['' => gettext('None')] + $crypto_modules
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
	'thermal_hardware',
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
	'kill_states',
	'State Killing on Gateway Failure',
	'Flush all states when a gateway goes down',
	$pconfig['kill_states']
))->setHelp('The monitoring process will flush all states when a gateway goes down '.
	'if this box is not checked. Check this box to disable this behavior.');

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
	($pconfig['use_mfs_tmpvar'] || $g['platform'] != $g['product_name'])
))->setHelp('Set this if you wish to use /tmp and /var as RAM disks (memory file '.
	'system disks) on a full install rather than use the hard disk. Setting this will '.
	'cause the data in /tmp and /var to be lost at reboot, including log data. RRD '.
	'and DHCP Leases will be retained.');

$section->addInput(new Form_Input(
	'use_mfs_tmp_size',
	'/tmp RAM Disk Size',
	'number',
	$pconfig['use_mfs_tmp_size'],
	['placeholder' => 40]
))->setHelp('Set the size, in MB, for the /tmp '.
	'RAM disk. Leave blank for 40MB. Do not set lower than 40.');

$section->addInput(new Form_Input(
	'use_mfs_var_size',
	'/var RAM Disk Size',
	'number',
	$pconfig['use_mfs_var_size'],
	['placeholder' => 60]
))->setHelp('Set the size, in MB, for the /var '.
	'RAM disk. Leave blank for 60MB. Do not set lower than 60.');

$section->addInput(new Form_Input(
	'rrdbackup',
	'Periodic RRD Backup',
	'number',
	$config['system']['rrdbackup'],
	['min' => 1, 'max' => 24, 'placeholder' => 'frequency between 1 and 24 hours']
))->setHelp('This will periodically backup the RRD data so '.
	'it can be restored automatically on the next boot. Keep in mind that the more '.
	'frequent the backup, the more writes will happen to your media.');

$section->addInput(new Form_Input(
	'dhcpbackup',
	'Periodic DHCP Leases Backup',
	'number',
	$config['system']['dhcpbackup'],
	['min' => 1, 'max' => 24, 'placeholder' => 'frequency between 1 and 24 hours']
))->setHelp('This will periodically backup the DHCP leases so '.
	'it can be restored automatically on the next boot. Keep in mind that the more '.
	'frequent the backup, the more writes will happen to your media.');

$form->add($section);

if ($g['platform'] == "pfSense") {
	$section = new Form_Section('Hardware settings');

	$opts = array(0.5,  1, 2,  3,  4,  5,  7.5,  10,  15,  20,  30,  60);
	$vals = array(  6, 12, 24, 36, 48, 60,  90, 120, 180, 240, 241, 242);

	$section->addInput(new Form_Select(
		'harddiskstandby',
		'Hard disk standby time',
		$pconfig['harddiskstandby'],
		['' => gettext("Always on")] + array_combine($opts, $vals)
	))->setHelp("Puts the hard disk into standby mode when the selected number of minutes has elapsed since the last access." . "<br />" .
				"<strong> Do not set this for CF cards.</strong>");

	$form->add($section);
}

$section = new Form_Section('Installation Feedback');

$section->addInput(new Form_Checkbox(
	'do_not_send_host_uuid',
	'Host UUID',
	'Do NOT send HOST UUID with user agent',
	$pconfig['do_not_send_host_uuid']
))->setHelp('Enable this option to not send HOST UUID to pfSense as part of User-Agent header.');

$form->add($section);

print $form;

include("foot.inc");
