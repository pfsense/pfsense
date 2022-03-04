<?php
/*
 * system_advanced_misc.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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
require_once("shaper.inc");
require_once("system.inc");
require_once("util.inc");
require_once("pfsense-utils.inc");

// Functions included by system_advanced_misc.php =============================
function getSystemAdvancedMisc($json = false) {
	global $config;

	$pconfig = array();

	$pconfig['available_kernel_memory'] = (int) get_single_sysctl("vm.stats.vm.v_free_count") * (int) get_single_sysctl("vm.stats.vm.v_page_size");

	/* Adjust available kernel memory to account for existing RAM disks
	 * https://redmine.pfsense.org/issues/10420 */
	if (isset($config['system']['use_mfs_tmpvar'])) {
		$pconfig['available_kernel_memory'] += (int) trim(exec("/bin/df -k /tmp /var | /usr/bin/awk '/\/dev\/md/ {sum += \$2 * 1024} END {print sum}'"));
	}

	$pconfig['proxyurl'] = $config['system']['proxyurl'];
	$pconfig['proxyport'] = $config['system']['proxyport'];
	$pconfig['proxyuser'] = $config['system']['proxyuser'];
	$pconfig['proxypass'] = $config['system']['proxypass'];
	$pconfig['harddiskstandby'] = $config['system']['harddiskstandby'];
	$pconfig['lb_use_sticky'] = isset($config['system']['lb_use_sticky']);
	$pconfig['srctrack'] = $config['system']['srctrack'];
	$pconfig['powerd_enable'] = isset($config['system']['powerd_enable']);
	$pconfig['crypto_hardware'] = $config['system']['crypto_hardware'];
	$pconfig['thermal_hardware'] = $config['system']['thermal_hardware'];
	$pconfig['pti_disabled'] = isset($config['system']['pti_disabled']);
	$pconfig['mds_disable'] = $config['system']['mds_disable'];
	$pconfig['schedule_states'] = isset($config['system']['schedule_states']);
	$pconfig['gw_down_kill_states'] = isset($config['system']['gw_down_kill_states']);
	$pconfig['skip_rules_gw_down'] = isset($config['system']['skip_rules_gw_down']);
	$pconfig['dpinger_dont_add_static_routes'] = isset($config['system']['dpinger_dont_add_static_routes']);
	$pconfig['use_mfs_tmpvar'] = isset($config['system']['use_mfs_tmpvar']);
	$pconfig['use_mfs_tmp_size'] = $config['system']['use_mfs_tmp_size'];
	$pconfig['use_mfs_var_size'] = $config['system']['use_mfs_var_size'];
	$pconfig['do_not_send_uniqueid'] = isset($config['system']['do_not_send_uniqueid']);
	$pconfig['pti'] = get_single_sysctl('vm.pmap.pti');
	$pconfig['mds']= get_single_sysctl('hw.mds_disable_state');

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

	return $json ? json_encode($pconfig) : $pconfig;
}

function saveSystemAdvancedMisc($post, $json = false) {
	global $config;

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

	$post['available_kernel_memory'] = (int) get_single_sysctl("vm.stats.vm.v_free_count") * (int) get_single_sysctl("vm.stats.vm.v_page_size");;

	/* Adjust available kernel memory to account for existing RAM disks
	 * https://redmine.pfsense.org/issues/10420 */
	if (isset($config['system']['use_mfs_tmpvar'])) {
		$post['available_kernel_memory'] += (int) trim(exec("/bin/df -k /tmp /var | /usr/bin/awk '/\/dev\/md/ {sum += \$2 * 1024} END {print sum}'"));
	}

	if (!empty($post['crypto_hardware']) && !array_key_exists($post['crypto_hardware'], $crypto_modules)) {
		$input_errors[] = gettext("Please select a valid Cryptographic Accelerator.");
	}

	if (!empty($post['thermal_hardware']) && !array_key_exists($post['thermal_hardware'], $thermal_hardware_modules)) {
		$input_errors[] = gettext("Please select a valid Thermal Hardware Sensor.");
	}

	if (!empty($post['use_mfs_tmp_size']) && (!is_numeric($post['use_mfs_tmp_size']) || ($post['use_mfs_tmp_size'] < 40))) {
		$input_errors[] = gettext("/tmp Size must be numeric and should not be less than 40MiB.");
	}

	if (!empty($post['use_mfs_var_size']) && (!is_numeric($post['use_mfs_var_size']) || ($post['use_mfs_var_size'] < 60))) {
		$input_errors[] = gettext("/var Size must be numeric and should not be less than 60MiB.");
	}

	if (is_numericint($post['use_mfs_tmp_size']) && is_numericint($post['use_mfs_var_size']) &&
	    ((($post['use_mfs_tmp_size'] + $post['use_mfs_var_size']) * 1024 * 1024) > $post['available_kernel_memory'])) {
		$input_errors[] = gettext("Combined size of /tmp and /var RAM disks would exceed available kernel memory.");
	}

	if (!empty($post['proxyport']) && !is_port($post['proxyport'])) {
		$input_errors[] = gettext("Proxy port must be a valid port number, 1-65535.");
	}

	if (!empty($post['proxyurl']) && !is_fqdn($post['proxyurl']) && !is_ipaddr($post['proxyurl'])) {
		$input_errors[] = gettext("Proxy URL must be a valid IP address or FQDN.");
	}

	if (!empty($post['proxyuser']) && preg_match("/[^a-zA-Z0-9\.\-_@]/", $post['proxyuser'])) {
		$input_errors[] = gettext("The proxy username contains invalid characters.");
	}

	if ($post['proxypass'] != $post['proxypass_confirm']) {
		$input_errors[] = gettext("Proxy password and confirmation must match.");
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

	if (!$input_errors) {

		if ($post['harddiskstandby'] <> "") {
			$config['system']['harddiskstandby'] = $post['harddiskstandby'];
			system_set_harddisk_standby();
		} else {
			unset($config['system']['harddiskstandby']);
		}

		if ($post['proxyurl'] <> "") {
			$config['system']['proxyurl'] = $post['proxyurl'];
		} else {
			unset($config['system']['proxyurl']);
		}

		if ($post['proxyport'] <> "") {
			$config['system']['proxyport'] = $post['proxyport'];
		} else {
			unset($config['system']['proxyport']);
		}

		if ($post['proxyuser'] <> "") {
			$config['system']['proxyuser'] = $post['proxyuser'];
		} else {
			unset($config['system']['proxyuser']);
		}

		if ($post['proxypass'] <> "") {
			if ($post['proxypass'] != DMYPWD) {
				$config['system']['proxypass'] = $post['proxypass'];
			}
		} else {
			unset($config['system']['proxypass']);
		}

		if ($post['lb_use_sticky'] == "yes") {
			if (!isset($config['system']['lb_use_sticky'])) {
				$config['system']['lb_use_sticky'] = true;
			}
			if ($config['system']['srctrack'] != $post['srctrack']) {
				$config['system']['srctrack'] = $post['srctrack'];
			}
		} else {
			if (isset($config['system']['lb_use_sticky'])) {
				unset($config['system']['lb_use_sticky']);
			}
		}

		if ($post['pkg_nochecksig'] == "yes") {
			$config['system']['pkg_nochecksig'] = true;
		} elseif (isset($config['system']['pkg_nochecksig'])) {
			unset($config['system']['pkg_nochecksig']);
		}

		if ($post['do_not_send_uniqueid'] == "yes") {
			$config['system']['do_not_send_uniqueid'] = true;
		} else {
			unset($config['system']['do_not_send_uniqueid']);
		}

		if ($post['powerd_enable'] == "yes") {
			$config['system']['powerd_enable'] = true;
		} else {
			unset($config['system']['powerd_enable']);
		}

		$config['system']['powerd_ac_mode'] = $post['powerd_ac_mode'];
		$config['system']['powerd_battery_mode'] = $post['powerd_battery_mode'];
		$config['system']['powerd_normal_mode'] = $post['powerd_normal_mode'];

		if ($post['crypto_hardware']) {
			$config['system']['crypto_hardware'] = $post['crypto_hardware'];
		} else {
			unset($config['system']['crypto_hardware']);
		}

		if ($post['thermal_hardware']) {
			$config['system']['thermal_hardware'] = $post['thermal_hardware'];
		} else {
			unset($config['system']['thermal_hardware']);
		}

		$old_pti_state = isset($config['system']['pti_disabled']);
		if ($post['pti_disabled'] == "yes") {
			$config['system']['pti_disabled'] = true;
		} else {
			unset($config['system']['pti_disabled']);
		}

		if (isset($post['mds_disable']) && (strlen($post['mds_disable']) > 0)) {
			$config['system']['mds_disable'] = $post['mds_disable'];
		} else {
			unset($config['system']['mds_disable']);
		}

		if ($post['schedule_states'] == "yes") {
			$config['system']['schedule_states'] = true;
		} else {
			unset($config['system']['schedule_states']);
		}

		if ($post['gw_down_kill_states'] == "yes") {
			$config['system']['gw_down_kill_states'] = true;
		} else {
			unset($config['system']['gw_down_kill_states']);
		}

		if ($post['skip_rules_gw_down'] == "yes") {
			$config['system']['skip_rules_gw_down'] = true;
		} else {
			unset($config['system']['skip_rules_gw_down']);
		}

		$tmpvar_set = (isset($config['system']['use_mfs_tmpvar']) ? true:false);

		if ($post['use_mfs_tmpvar'] == "yes") {
			$config['system']['use_mfs_tmpvar'] = true;
			$tmpvar_enabled = true;
		} else {
			unset($config['system']['use_mfs_tmpvar']);
			$tmpvar_enabled = false;
		}

		// If the "use ramdisk" setting has changed, or if it is being enabled while an
		// error is present, indicate that a reboot will be needed.
		if (($tmpvar_enabled != $tmpvar_set) ||
		    (file_exists('/conf/ram_disks_failed') && $post['use_mfs_tmpvar'] == "yes") ||
		    (($post['use_mfs_tmpvar'] == "yes") &&
		    (($config['system']['use_mfs_tmp_size'] != $post['use_mfs_tmp_size']) ||
		    ($config['system']['use_mfs_var_size'] != $post['use_mfs_var_size'])))) {
			$rv['reboot'] = true;
		}

		$config['system']['use_mfs_tmp_size'] = $post['use_mfs_tmp_size'];
		$config['system']['use_mfs_var_size'] = $post['use_mfs_var_size'];

		if (isset($post['rrdbackup'])) {
			if (($post['rrdbackup'] > 0) && ($post['rrdbackup'] <= 24)) {
				$config['system']['rrdbackup'] = intval($post['rrdbackup']);
			} else {
				unset($config['system']['rrdbackup']);
			}
		}

		if (isset($post['dhcpbackup'])) {
			if (($post['dhcpbackup'] > 0) && ($post['dhcpbackup'] <= 24)) {
				$config['system']['dhcpbackup'] = intval($post['dhcpbackup']);
			} else {
				unset($config['system']['dhcpbackup']);
			}
		}

		if (isset($post['logsbackup'])) {
			if (($post['logsbackup'] > 0) && ($post['logsbackup'] <= 24)) {
				$config['system']['logsbackup'] = intval($post['logsbackup']);
			} else {
				unset($config['system']['logsbackup']);
			}
		}

		if (isset($post['captiveportalbackup'])) {
			if (($post['captiveportalbackup'] > 0) && ($post['captiveportalbackup'] <= 24)) {
				$config['system']['captiveportalbackup'] = intval($post['captiveportalbackup']);
			} else {
				unset($config['system']['captiveportalbackup']);
			}
		}

		// Add/Remove RAM disk periodic backup cron jobs according to settings and installation type.
		// Remove the cron jobs on full install if not using RAM disk.
		// Add the cron jobs on all others if the periodic backup option is set.  Otherwise the cron job is removed.
		if (!isset($config['system']['use_mfs_tmpvar'])) {
			/* See #7146 for detail on why the extra parameters are needed for the time being. */
			install_cron_job("/etc/rc.backup_rrd.sh", false, null, null, null, null, null, null, false);
			install_cron_job("/etc/rc.backup_dhcpleases.sh", false, null, null, null, null, null, null, false);
			install_cron_job("/etc/rc.backup_logs.sh", false, null, null, null, null, null, null, false);
		} else {
			/* See #7146 for detail on why the extra parameters are needed for the time being. */
			install_cron_job("/etc/rc.backup_rrd.sh", ($config['system']['rrdbackup'] > 0), $minute="0", "*/{$config['system']['rrdbackup']}", '*', '*', '*', 'root', false);
			install_cron_job("/etc/rc.backup_dhcpleases.sh", ($config['system']['dhcpbackup'] > 0), $minute="0", "*/{$config['system']['dhcpbackup']}", '*', '*', '*', 'root', false);
			install_cron_job("/etc/rc.backup_logs.sh", ($config['system']['logsbackup'] > 0), $minute="0", "*/{$config['system']['logsbackup']}", '*', '*', '*', 'root', false);
			install_cron_job("/etc/rc.backup_captiveportal.sh", ($config['system']['captiveportalbackup'] > 0), $minute="0", "*/{$config['system']['captiveportalbackup']}", '*', '*', '*', 'root', false);
		}

		write_config("Miscellaneous Advanced Settings saved");

		$changes_applied = true;
		$retval = 0;
		system_resolvconf_generate(true);
		$retval |= filter_configure();

		if ($old_pti_state != isset($config['system']['pti_disabled'])) {
			setup_loader_settings();
		}

		if (isset($config['system']['mds_disable']) &&
		    (strlen($config['system']['mds_disable']) > 0)) {
			set_single_sysctl("hw.mds_disable" , (int)$config['system']['mds_disable']);
		}

		activate_powerd();
		load_crypto();
		load_thermal_hardware();
	}

	// Compose the structure to retuen
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
