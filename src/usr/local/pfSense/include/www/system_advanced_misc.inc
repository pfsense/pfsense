<?php
/*
 * system_advanced_misc.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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

require_once("config.gui.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("service-utils.inc");
require_once("shaper.inc");
require_once("system.inc");
require_once("util.inc");
require_once("pfsense-utils.inc");

global $hwpstate_status_descr;
$hwpstate_status_descr = [
	'-1' => gettext('Disabled'),
	'0'  => gettext('Unsupported'),
	'1'  => gettext('Active'),
];

global $hwpstate_control_levels;
$hwpstate_control_levels = [
	'0' => gettext('Core Level Control (Recommended)'),
	'1' => gettext('Package Level Control'),
];

global $global_gateway_state_kill_modes;
$global_gateway_state_kill_modes = array(
	'none' => gettext("Do not kill states on gateway failure"),
	'down' => gettext("Kill states for all gateways which are down"),
	'all' => gettext("Flush all states on gateway failure"),
);

// Functions included by system_advanced_misc.php =============================
function ramdisk_current_usage($mnt, $ramonly = true) {
	if (is_array($mnt)) {
		$disks = implode(' ', array_map('escapeshellarg', $mnt));
	} else {
		$disks = escapeshellarg($mnt);
	}
	$filter = ($ramonly) ? '/tmpfs/' : '';
	return (int) trim(exec("/bin/df -k {$disks} | /usr/bin/awk '{$filter} {sum += \$3 * 1024} END {print sum+0}'"));
}
function ramdisk_available_memory($includeswap = true) {
	/* Current free RAM, converted from page count to bytes. */
	$free = (int) get_single_sysctl("vm.stats.vm.v_free_count") * (int) get_single_sysctl("vm.stats.vm.v_page_size");

	/* Adjust available memory to account for existing RAM disk contents
	 * https://redmine.pfsense.org/issues/10420 */
	if (config_path_enabled('system','use_mfs_tmpvar')) {
		$free += ramdisk_current_usage(['/var', '/tmp'], true);
	}

	if ($includeswap) {
		/* Also include total swap space in calculation since tmpfs data can be swapped */
		$free += (int) trim(exec("/usr/sbin/swapinfo -k | /usr/bin/tail -1 | /usr/bin/awk '{print \$2 * 1024}'"));
	}

	return $free;
}

function getSystemAdvancedMisc($json = false) {
	$pconfig = array();

	$pconfig['proxyurl'] = config_get_path('system/proxyurl');
	$pconfig['proxyport'] = config_get_path('system/proxyport');
	$pconfig['proxyuser'] = config_get_path('system/proxyuser');
	$pconfig['proxypass'] = config_get_path('system/proxypass');
	$pconfig['harddiskstandby'] = config_get_path('system/harddiskstandby');
	$pconfig['lb_use_sticky'] = config_path_enabled('system', 'lb_use_sticky');
	$pconfig['srctrack'] = config_get_path('system/srctrack');
	$pconfig['powerd_enable'] = config_path_enabled('system', 'powerd_enable');
	$pconfig['crypto_hardware'] = config_get_path('system/crypto_hardware');
	$pconfig['thermal_hardware'] = config_get_path('system/thermal_hardware');
	$pconfig['pti_disabled'] = config_path_enabled('system', 'pti_disabled');
	$pconfig['mds_disable'] = config_get_path('system/mds_disable');
	$pconfig['schedule_states'] = config_path_enabled('system', 'schedule_states');
	$pconfig['remove_failover_states_default'] = config_get_path('system/remove_failover_states_default');
	$pconfig['keep_failover_states'] = config_path_enabled('system', 'keep_failover_states');
	$pconfig['gw_down_kill_states'] = config_get_path('system/gw_down_kill_states');
	$pconfig['skip_rules_gw_down'] = config_path_enabled('system', 'skip_rules_gw_down');
	$pconfig['dpinger_dont_add_static_routes'] = config_path_enabled('system', 'dpinger_dont_add_static_routes');
	$pconfig['use_mfs_tmpvar'] = config_path_enabled('system', 'use_mfs_tmpvar');
	$pconfig['use_mfs_tmp_size'] = config_get_path('system/use_mfs_tmp_size');
	$pconfig['use_mfs_var_size'] = config_get_path('system/use_mfs_var_size');
	$pconfig['do_not_send_uniqueid'] = config_path_enabled('system', 'do_not_send_uniqueid');
	$pconfig['pti'] = get_single_sysctl('vm.pmap.pti');
	$pconfig['mds']= get_single_sysctl('hw.mds_disable_state');
	$pconfig['php_memory_limit'] = config_get_path('system/php_memory_limit');

	$pconfig['hwpstate'] = (config_get_path('system/hwpstate', 'enabled') == 'enabled');
	$pconfig['hwpstate_control_level'] = config_get_path('system/hwpstate_control_level', get_single_sysctl('machdep.hwpstate_pkg_ctrl'));
	$hwpstate_epp_default = get_single_sysctl('dev.hwpstate_intel.0.epp');
	if (!is_numericint($hwpstate_epp_default) ||
	    ($hwpstate_epp_default < 0) ||
	    ($hwpstate_epp_default > 100)) {
		$hwpstate_epp_default = 50;
	}
	$pconfig['hwpstate_epp'] = config_get_path('system/hwpstate_epp', $hwpstate_epp_default);

	$pconfig['powerd_ac_mode'] = "hadp";
	if (!empty(config_get_path('system/powerd_ac_mode'))) {
		$pconfig['powerd_ac_mode'] = config_get_path('system/powerd_ac_mode');
	}

	$pconfig['powerd_battery_mode'] = "hadp";
	if (!empty(config_get_path('system/powerd_battery_mode'))) {
		$pconfig['powerd_battery_mode'] = config_get_path('system/powerd_battery_mode');
	}

	$pconfig['powerd_normal_mode'] = "hadp";
	if (!empty(config_get_path('system/powerd_normal_mode'))) {
		$pconfig['powerd_normal_mode'] = config_get_path('system/powerd_normal_mode');
	}

	return $json ? json_encode($pconfig) : $pconfig;
}

function getSystemAdvancedMiscCryptoModules() {
	$platform = system_identify_specific_platform();
	switch ($platform['name']) {
		default:
			$crypto_modules = array(
				'aesni' => gettext("AES-NI CPU-based Acceleration"),
				'aesni_cryptodev' => gettext("AES-NI and BSD Crypto Device (aesni, cryptodev)"),
				'cryptodev' => gettext("BSD Crypto Device (cryptodev)"),
				'qat' => gettext("Intel QuickAssist (QAT)"),
			);
	}

	return $crypto_modules;
}

function saveSystemAdvancedMisc($post, $json = false) {
	global $global_gateway_state_kill_modes, $hwpstate_status_descr, $hwpstate_control_levels;

	$rv = array();

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

	$crypto_modules = array(
		'aesni' => gettext("AES-NI CPU-based Acceleration"),
		'cryptodev' => gettext("BSD Crypto Device (cryptodev)"),
		'aesni_cryptodev' => gettext("AES-NI and BSD Crypto Device (aesni, cryptodev)"),
	);

	$thermal_hardware_modules = array(
		'coretemp' => gettext("Intel Core* CPU on-die thermal sensor"),
		'amdtemp' => gettext("AMD K8, K10 and K11 CPU on-die thermal sensor")
	);

	if (!empty($post['crypto_hardware']) && !array_key_exists($post['crypto_hardware'],
	    getSystemAdvancedMiscCryptoModules())) {
		$input_errors[] = gettext("Please select a valid Cryptographic Accelerator.");
	}

	if (!empty($post['thermal_hardware']) && !array_key_exists($post['thermal_hardware'], $thermal_hardware_modules)) {
		$input_errors[] = gettext("Please select a valid Thermal Hardware Sensor.");
	}

	if (!empty($post['gw_down_kill_states']) && !array_key_exists($post['gw_down_kill_states'], $global_gateway_state_kill_modes)) {
		$input_errors[] = gettext("Please select a valid State Killing on Gateway Failure mode.");
	}

	if (!empty($post['use_mfs_tmp_size']) && (!is_numeric($post['use_mfs_tmp_size']) || ($post['use_mfs_tmp_size'] < 40))) {
		$input_errors[] = gettext("/tmp Size must be numeric and should not be less than 40MiB.");
	}

	if (!empty($post['use_mfs_var_size']) && (!is_numeric($post['use_mfs_var_size']) || ($post['use_mfs_var_size'] < 60))) {
		$input_errors[] = gettext("/var Size must be numeric and should not be less than 60MiB.");
	}

	/* Do not check that size would exceed RAM unless RAM disks would be enabled.
	 * https://redmine.pfsense.org/issues/13479 */
	if ($post['use_mfs_tmpvar'] == "yes") {
		if (is_numericint($post['use_mfs_tmp_size']) && is_numericint($post['use_mfs_var_size']) &&
		    ((($post['use_mfs_tmp_size'] + $post['use_mfs_var_size']) * 1024 * 1024) > ramdisk_available_memory())) {
			$input_errors[] = gettext("Combined size of /tmp and /var RAM disks would exceed available memory and swap space.");
		}
	}

	if (!empty($post['proxyport']) && !is_port($post['proxyport'])) {
		$input_errors[] = gettext("Proxy port must be a valid port number, 1-65535.");
	}

	if (!empty($post['proxyurl']) && !is_fqdn($post['proxyurl']) && !is_ipaddr($post['proxyurl'])) {
		$input_errors[] = gettext("Proxy URL must be a valid IP address or FQDN.");
	}

	if (!empty($post['proxyuser']) && empty($post['proxypass'])) {
		$input_errors[] = gettext("A proxy password must be set when specifying a proxy username.");
	}

	if ($post['proxypass'] != $post['proxypass_confirm']) {
		$input_errors[] = gettext("Proxy password and confirmation must match.");
	}

	if (system_has_hwpstate() !== false) {
		if (!in_array($post['hwpstate_control_level'], array_keys($hwpstate_control_levels))) {
			$input_errors[] = gettext("Invalid Speed Shift Control Level.");
		}

		if ((array_key_exists('hwpstate_epp', $post) &&
		    (strlen($post['hwpstate_epp']) > 0)) &&
		    (!is_numericint($post['hwpstate_epp']) ||
		    ($post['hwpstate_epp'] < 0) ||
		    ($post['hwpstate_epp'] > 100))) {
			$input_errors[] = gettext("Invalid Speed Shift Energy/Performance Preference value.");
		}
	}

	if (!in_array($post['powerd_ac_mode'], array_keys($powerd_modes))) {
		$input_errors[] = gettext("Invalid AC Power mode.");
	}

	if (!in_array($post['powerd_battery_mode'], array_keys($powerd_modes))) {
		$input_errors[] = gettext("Invalid Battery Power mode.");
	}

	if (!in_array($post['powerd_normal_mode'], array_keys($powerd_modes))) {
		$input_errors[] = gettext("Invalid Unknown Power mode.");
	}

	if (!in_array($post['mds_disable'], array_keys($mds_modes))) {
		$input_errors[] = gettext("Invalid MDS Mode.");
	}

	// Retrieve limit
	$php_max_ram = get_php_max_memory();

	if (!empty($post['php_memory_limit']) && ((!is_numeric($post['php_memory_limit'])) || ($post['php_memory_limit'] > $php_max_ram)
			|| ($post['php_memory_limit'] < 128))) {
		$input_errors[] = gettext("PHP Memory Limit must be numeric and should be between 128 to " . $php_max_ram);
	}

	if (!$input_errors) {

		if ($post['harddiskstandby'] <> "") {
			config_set_path('system/harddiskstandby', $post['harddiskstandby']);
			system_set_harddisk_standby();
		} else {
			config_del_path('system/harddiskstandby');
		}

		if ($post['proxyurl'] <> "") {
			config_set_path('system/proxyurl', $post['proxyurl']);
		} else {
			config_del_path('system/proxyurl');
		}

		if ($post['proxyport'] <> "") {
			config_set_path('system/proxyport', $post['proxyport']);
		} else {
			config_del_path('system/proxyport');
		}

		if ($post['proxyuser'] <> "") {
			config_set_path('system/proxyuser', $post['proxyuser']);
		} else {
			config_del_path('system/proxyuser');
		}

		if ($post['proxypass'] <> "") {
			if ($post['proxypass'] != DMYPWD) {
				config_set_path('system/proxypass', $post['proxypass']);
			}
		} else {
			config_del_path('system/proxypass');
		}

		if ($post['lb_use_sticky'] == "yes") {
			if (!config_path_enabled('system', 'lb_use_sticky')) {
				config_set_path('system/lb_use_sticky', true);
			}
			if (config_get_path('system/srctrack') != $post['srctrack']) {
				config_set_path('system/srctrack', $post['srctrack']);
			}
		} else {
				config_del_path('system/lb_use_sticky');
		}

		if ($post['pkg_nochecksig'] == "yes") {
			config_set_path('system/pkg_nochecksig', true);
		} elseif (config_path_enabled('system', 'pkg_nochecksig')) {
			config_del_path('system/pkg_nochecksig');
		}

		if ($post['do_not_send_uniqueid'] == "yes") {
			config_set_path('system/do_not_send_uniqueid', true);
		} else {
			config_del_path('system/do_not_send_uniqueid');
		}

		if ($post['powerd_enable'] == "yes") {
			config_set_path('system/powerd_enable', true);
		} else {
			config_del_path('system/powerd_enable');
		}

		if (system_has_hwpstate() !== false) {
			$old_hwpstate = config_get_path('system/hwpstate', 'enabled');
			$new_hwpstate = ($post['hwpstate'] == 'yes') ? 'enabled' : 'disabled';
			config_set_path('system/hwpstate', $new_hwpstate);
			$old_hwpstate_control_level = get_single_sysctl('machdep.hwpstate_pkg_ctrl');
			config_set_path('system/hwpstate_control_level', $post['hwpstate_control_level']);
			config_set_path('system/hwpstate_epp', $post['hwpstate_epp']);

			/* Check if reboot is needed */
			if (($old_hwpstate != $new_hwpstate) ||
			    ($old_hwpstate_control_level != $post['hwpstate_control_level'])) {
				$rv['reboot-hwpstate'] = true;
			}
			system_hwpstate_setepp();
		}

		config_set_path('system/powerd_ac_mode', $post['powerd_ac_mode']);
		config_set_path('system/powerd_battery_mode', $post['powerd_battery_mode']);
		config_set_path('system/powerd_normal_mode', $post['powerd_normal_mode']);

		if ($post['crypto_hardware']) {
			config_set_path('system/crypto_hardware', $post['crypto_hardware']);
		} else {
			config_del_path('system/crypto_hardware');
		}

		if ($post['thermal_hardware']) {
			config_set_path('system/thermal_hardware', $post['thermal_hardware']);
		} else {
			config_del_path('system/thermal_hardware');
		}

		$old_pti_state = config_path_enabled('system', 'pti_disabled');
		if ($post['pti_disabled'] == "yes") {
			config_set_path('system/pti_disabled', true);
		} else {
			config_del_path('system/pti_disabled');
		}

		if (isset($post['mds_disable']) && (strlen($post['mds_disable']) > 0)) {
			config_set_path('system/mds_disable', $post['mds_disable']);
		} else {
			config_del_path('system/mds_disable');
		}

		if ($post['schedule_states'] == "yes") {
			config_set_path('system/schedule_states', true);
		} else {
			config_del_path('system/schedule_states');
		}

		if (!empty($post['remove_failover_states_default'])) {
			config_set_path('system/remove_failover_states_default', $post['remove_failover_states_default']);
		} else {
			config_del_path('system/remove_failover_states_default');
		}

		if ($post['keep_failover_states']) {
			config_set_path('system/keep_failover_states', true);
		} else {
			config_del_path('system/keep_failover_states');
		}

		if ($post['gw_down_kill_states']) {
			config_set_path('system/gw_down_kill_states', $post['gw_down_kill_states']);
		} else {
			config_del_path('system/gw_down_kill_states');
		}

		if ($post['skip_rules_gw_down'] == "yes") {
			config_set_path('system/skip_rules_gw_down', true);
		} else {
			config_del_path('system/skip_rules_gw_down');
		}

		if ($post['dpinger_dont_add_static_routes'] == "yes") {
			config_set_path('system/dpinger_dont_add_static_routes', true);
		} else {
			config_del_path('system/dpinger_dont_add_static_routes');
		}

		$tmpvar_set = (config_path_enabled('system', 'use_mfs_tmpvar') ? true:false);

		if ($post['use_mfs_tmpvar'] == "yes") {
			config_set_path('system/use_mfs_tmpvar', true);
			$tmpvar_enabled = true;
		} else {
			config_del_path('system/use_mfs_tmpvar');
			$tmpvar_enabled = false;
		}

		// If the "use ramdisk" setting has changed, or if it is being enabled while an
		// error is present, indicate that a reboot will be needed.
		if (($tmpvar_enabled != $tmpvar_set) ||
		    (file_exists('/conf/ram_disks_failed') && $post['use_mfs_tmpvar'] == "yes") ||
		    (($post['use_mfs_tmpvar'] == "yes") &&
		    ((config_get_path('system/use_mfs_tmp_size') != $post['use_mfs_tmp_size']) ||
		    (config_get_path('system/use_mfs_var_size') != $post['use_mfs_var_size'])))) {
			$rv['reboot-ramdisks'] = true;
		}

		config_set_path('system/use_mfs_tmp_size', $post['use_mfs_tmp_size']);
		config_set_path('system/use_mfs_var_size', $post['use_mfs_var_size']);

		if (isset($post['rrdbackup'])) {
			if (($post['rrdbackup'] > 0) && ($post['rrdbackup'] <= 24)) {
				config_set_path('system/rrdbackup', intval($post['rrdbackup']));
			} else {
				config_del_path('system/rrdbackup');
			}
		}

		if (isset($post['dhcpbackup'])) {
			if (($post['dhcpbackup'] > 0) && ($post['dhcpbackup'] <= 24)) {
				config_set_path('system/dhcpbackup', intval($post['dhcpbackup']));
			} else {
				config_del_path('system/dhcpbackup');
			}
		}

		if (isset($post['logsbackup'])) {
			if (($post['logsbackup'] > 0) && ($post['logsbackup'] <= 24)) {
				config_set_path('system/logsbackup', intval($post['logsbackup']));
			} else {
				config_del_path('system/logsbackup');
			}
		}

		if (isset($post['captiveportalbackup'])) {
			if (($post['captiveportalbackup'] > 0) && ($post['captiveportalbackup'] <= 24)) {
				config_set_path('system/captiveportalbackup', intval($post['captiveportalbackup']));
			} else {
				config_del_path('system/captiveportalbackup');
			}
		}

		// PHP Memory Limit
		if (!empty($post['php_memory_limit'])) {
			config_set_path('system/php_memory_limit', $post['php_memory_limit']);
		} else {
			config_del_path('system/php_memory_limit');
		}

		// Add/Remove RAM disk periodic backup cron jobs according to settings and installation type.
		// Remove the cron jobs on full install if not using RAM disk.
		// Add the cron jobs on all others if the periodic backup option is set.  Otherwise the cron job is removed.
		if (!config_path_enabled('system', 'use_mfs_tmpvar')) {
			/* See #7146 for detail on why the extra parameters are needed for the time being. */
			install_cron_job("/etc/rc.backup_rrd.sh", false, null, null, null, null, null, null, false);
			install_cron_job("/etc/rc.backup_dhcpleases.sh", false, null, null, null, null, null, null, false);
			install_cron_job("/etc/rc.backup_logs.sh", false, null, null, null, null, null, null, false);
		} else {
			/* See #7146 for detail on why the extra parameters are needed for the time being. */
			install_cron_job("/etc/rc.backup_rrd.sh", (config_get_path('system/rrdbackup') > 0), $minute="0", "*/{config_get_path('system/rrdbackup')}", '*', '*', '*', 'root', false);
			install_cron_job("/etc/rc.backup_dhcpleases.sh", (config_get_path('system/dhcpbackup') > 0), $minute="0", "*/{config_get_path('system/dhcpbackup')}", '*', '*', '*', 'root', false);
			install_cron_job("/etc/rc.backup_logs.sh", (config_get_path('system/logsbackup') > 0), $minute="0", "*/{config_get_path('system/logsbackup')}", '*', '*', '*', 'root', false);
			install_cron_job("/etc/rc.backup_captiveportal.sh", (config_get_path('system/captiveportalbackup') > 0), $minute="0", "*/{config_get_path('system/captiveportalbackup')}", '*', '*', '*', 'root', false);
		}

		write_config("Miscellaneous Advanced Settings saved");

		$changes_applied = true;
		$retval = 0;
		system_resolvconf_generate(true);
		$retval |= filter_configure();

		if (($rv['reboot-hwpstate']) ||
		    ($old_pti_state != config_path_enabled('system', 'pti_disabled'))) {
			setup_loader_settings();
		}

		if (config_path_enabled('system', 'mds_disable') &&
		    (strlen(config_get_path('system/mds_disable')) > 0)) {
			set_single_sysctl("hw.mds_disable" , (int)config_get_path('system/mds_disable'));
		}

		activate_powerd();
		load_crypto();
		load_thermal_hardware();
	}

	// Compose the structure to return
	$rv['input_errors'] = $input_errors;
	$post['pti'] = get_single_sysctl('vm.pmap.pti');
	$post['mds'] = get_single_sysctl('hw.mds_disable_state');

	if (!$json) {
		$rv['post'] = $post;
		$rv['retval'] = $retval;
		$rv['changes_applied'] = $changes_applied;
	}

	return $json ? json_encode($rv) : $rv;
}
?>
