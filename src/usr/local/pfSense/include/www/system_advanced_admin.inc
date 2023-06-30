<?php
/*
 * system_advanced_admin.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2014-2023 Rubicon Communications, LLC (Netgate)
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

// Functions included by system_advanced_admin.php ============================

// Retrieve an array of advanced admin settings
function getAdvancedAdminConfig($json = false) {
	$pconfig = array();

	$pconfig['webguiproto'] = config_get_path('system/webgui/protocol');
	$pconfig['webguiport'] = config_get_path('system/webgui/port');
	$pconfig['max_procs'] = config_get_path('system/webgui/max_procs', 2);
	$pconfig['ssl-certref'] = config_get_path('system/webgui/ssl-certref');
	$pconfig['disablehttpredirect'] = config_path_enabled('system/webgui', 'disablehttpredirect');
	$pconfig['disablehsts'] = config_path_enabled('system/webgui', 'disablehsts');
	$pconfig['ocsp-staple'] = config_path_enabled('system/webgui', 'ocsp-staple');
	$pconfig['disableconsolemenu'] = config_path_enabled('system', 'disableconsolemenu');
	$pconfig['noantilockout'] = config_path_enabled('system/webgui', 'noantilockout');
	$pconfig['nodnsrebindcheck'] = config_path_enabled('system/webgui', 'nodnsrebindcheck');
	$pconfig['nohttpreferercheck'] = config_path_enabled('system/webgui', 'nohttpreferercheck');
	$pconfig['pagenamefirst'] = config_path_enabled('system/webgui', 'pagenamefirst');
	$pconfig['loginautocomplete'] = config_path_enabled('system/webgui', 'loginautocomplete');
	$pconfig['althostnames'] = config_get_path('system/webgui/althostnames');
	$pconfig['enableserial'] = config_get_path('system/enableserial');
	$pconfig['serialspeed'] = config_get_path('system/serialspeed');
	$pconfig['primaryconsole'] = config_get_path('system/primaryconsole');
	$pconfig['enablesshd'] = config_get_path('system/ssh/enable');
	$pconfig['sshport'] = config_get_path('system/ssh/port');
	$pconfig['sshdkeyonly'] = config_get_path('system/ssh/sshdkeyonly');
	$pconfig['sshdagentforwarding'] = config_path_enabled('system/ssh', 'sshdagentforwarding');
	$pconfig['quietlogin'] = config_path_enabled('system/webgui', 'quietlogin');
	$pconfig['roaming'] = (config_get_path('system/webgui/roaming', 'enabled') == 'enabled') ? true : false;
	$pconfig['sshguard_threshold'] = config_get_path('system/sshguard_threshold', '');
	$pconfig['sshguard_blocktime'] = config_get_path('system/sshguard_blocktime', '');
	$pconfig['sshguard_detection_time'] = config_get_path('system/sshguard_detection_time', '');
	$pconfig['sshguard_whitelist'] = config_get_path('system/sshguard_whitelist', '');
	$pconfig['interfaces_lan'] = config_get_path('interfaces/lan');
	$a_cert = config_get_path('cert', []);
	$pconfig['certsavailable'] = (is_array($a_cert) && count($a_cert));
	if (!$pconfig['webguiproto'] || !$pconfig['certsavailable']) {
		$pconfig['webguiproto'] = 'http';
	}
	if ($json) {
		// When outputting in JSON, make the certs an array of objects each containing the id and description
		$l = cert_build_list('cert', 'HTTPS');
		$j = array();

		foreach ( $l as $k =>$v) {
			$j[] = array('id' => $k, 'descr' => $v);
		}

		$pconfig['certlist'] = $j;
	} else {
		$pconfig['certlist'] = cert_build_list('cert', 'HTTPS');
	}

	return $json ? json_encode($pconfig) : $pconfig;
}

function doAdvancedAdminPOST($post, $json = false) {
	$valid_webguiproto = array('http', 'https');
	$input_errors = array();
	$pconfig = $post;

	/* input validation */

	if ($post['webguiproto'] && !in_array($pconfig['webguiproto'], $valid_webguiproto)) {
		$input_errors[] = gettext("A valid webConfigurator protocol must be specified");
	}

	if ($post['webguiport']) {
		if (!is_port($post['webguiport'])) {
			$input_errors[] = gettext("A valid webConfigurator port number must be specified");
		}
	}

	if ($post['max_procs']) {
		if (!is_numericint($post['max_procs']) || ($post['max_procs'] < 1) || ($post['max_procs'] > 500)) {
			$input_errors[] = gettext("Max Processes must be a number 1 or greater");
		}
	}

	if ($post['althostnames']) {
		$althosts = explode(" ", $post['althostnames']);
		foreach ($althosts as $ah) {
			if (!is_ipaddr($ah) && !is_hostname($ah)) {
				$input_errors[] = sprintf(gettext("Alternate hostname %s is not a valid hostname."), htmlspecialchars($ah));
			}
		}
	}

	if ($post['sshport']) {
		if (!is_port($post['sshport'])) {
			$input_errors[] = gettext("A valid port number must be specified");
		}
	}

	if ($json) {
		$pconfig['sshguard_whitelist'] = $post['sshguard_whitelist'];
	} else {
		$whitelist_addresses = array();
		for ($i = 0; isset($post['address' . $i]); $i++) {
			/* Ignore blank fields */
			if (empty($post['address' . $i])) {
				continue;
			}

			$whitelist_address = $post['address' . $i] . '/' .
			    $post['address_subnet'. $i];

			if (!is_subnet($whitelist_address)) {
				$input_errors[] = sprintf(gettext(
				    "Invalid subnet '%s' added to Login Protection pass list"),
				    $whitelist_address);
				break;
			}
			$whitelist_addresses[] = $whitelist_address;
		}


		$pconfig['sshguard_whitelist'] = implode(' ', $whitelist_addresses);
	}

	if (!$input_errors) {
		if ($post['webguiproto']) {
			config_set_path('system/webgui/protocol', $post['webguiproto']);
			$restart_webgui = true;
		}

		if ($post['webguiport']) {
			config_set_path('system/webgui/port', $post['webguiport']);
			$restart_webgui = true;
		}

		if ($post['ssl-certref']) {
			config_set_path('system/webgui/ssl-certref', $post['ssl-certref']);
			$restart_webgui = true;
		}

		if ($post['max_procs']) {
			config_set_path('system/webgui/max_procs', $post['max_procs']);
			$restart_webgui = true;
		}

		// Restart the webgui only if this actually changed
		if ($post['webgui-redirect'] == "yes") {
			if (config_get_path('system/webgui/disablehttpredirect') != true) {
				$restart_webgui = true;
			}

			config_set_path('system/webgui/disablehttpredirect', true);
		} else {
			if (config_get_path('system/webgui/disablehttpredirect')) {
				$restart_webgui = true;
			}

			config_del_path('system/webgui/disablehttpredirect');
		}

		if ($post['webgui-hsts'] == "yes") {
			if (config_get_path('system/webgui/disablehsts') != true) {
				$restart_webgui = true;
			}

			config_set_path('system/webgui/disablehsts', true);
		} else {
			if (config_get_path('system/webgui/disablehsts')) {
				$restart_webgui = true;
			}

			config_del_path('system/webgui/disablehsts');
		}

		if ($post['ocsp-staple'] == "yes") {
			if (config_get_path('system/webgui/ocsp-staple') != true) {
				$restart_webgui = true;
			}

			config_set_path('system/webgui/ocsp-staple', true);
		} else {
			if (config_get_path('system/webgui/ocsp-staple')) {
				$restart_webgui = true;
			}

			config_del_path('system/webgui/ocsp-staple');
		}
		
		if ($post['webgui-login-messages'] == "yes") {
			config_set_path('system/webgui/quietlogin', true);
		} else {
			config_del_path('system/webgui/quietlogin');
		}

		if ($post['roaming'] == "yes") {
			config_set_path('system/webgui/roaming', 'enabled');
		} else {
			config_set_path('system/webgui/roaming', 'disabled');
		}

		if ($post['disableconsolemenu'] == "yes") {
			config_set_path('system/disableconsolemenu', true);
		} else {
			config_del_path('system/disableconsolemenu');
		}

		if ($post['noantilockout'] == "yes") {
			config_set_path('system/webgui/noantilockout', true);
		} else {
			config_del_path('system/webgui/noantilockout');
		}

		if ($post['enableserial'] == "yes" || g_get('enableserial_force')) {
			config_set_path('system/enableserial', true);
		} else {
			config_del_path('system/enableserial');
		}

		if (is_numericint($post['serialspeed'])) {
			config_set_path('system/serialspeed', $post['serialspeed']);
		} else {
			config_del_path('system/serialspeed');
		}

		if ($post['primaryconsole']) {
			config_set_path('system/primaryconsole', $post['primaryconsole']);
		} else {
			config_del_path('system/primaryconsole');
		}

		if ($post['nodnsrebindcheck'] == "yes") {
			config_set_path('system/webgui/nodnsrebindcheck', true);
		} else {
			config_del_path('system/webgui/nodnsrebindcheck');
		}

		if ($post['nohttpreferercheck'] == "yes") {
			config_set_path('system/webgui/nohttpreferercheck', true);
		} else {
			config_del_path('system/webgui/nohttpreferercheck');
		}

		if ($post['pagenamefirst'] == "yes") {
			config_set_path('system/webgui/pagenamefirst', true);
		} else {
			config_del_path('system/webgui/pagenamefirst');
		}

		if ($post['loginautocomplete'] == "yes") {
			config_set_path('system/webgui/loginautocomplete', true);
		} else {
			config_del_path('system/webgui/loginautocomplete');
		}

		if ($post['althostnames']) {
			config_set_path('system/webgui/althostnames', $post['althostnames']);
		} else {
			config_del_path('system/webgui/althostnames');
		}

		$sshd_enabled = config_path_enabled('system/ssh');
		if ($post['enablesshd']) {
			config_set_path('system/ssh/enable', 'enabled');
		} else {
			config_del_path('system/ssh/enable');
		}

		$sshd_keyonly = config_get_path('system/ssh/sshdkeyonly');
		if ($post['sshdkeyonly'] == "enabled") {
			config_set_path('system/ssh/sshdkeyonly', 'enabled');
		} else if ($post['sshdkeyonly'] == "both") {
			config_set_path('system/ssh/sshdkeyonly', 'both');
		} else {
			config_del_path('system/ssh/sshdkeyonly');
		}

		$sshd_agentforwarding = config_path_enabled('system/ssh', 'sshdagentforwarding');
		if ($post['sshdagentforwarding']) {
			config_set_path('system/ssh/sshdagentforwarding', 'enabled');
		} else {
			config_del_path('system/ssh/sshdagentforwarding');
		}

		$sshd_port = config_get_path('system/ssh/port');
		if ($post['sshport']) {
			config_set_path('system/ssh/port', $post['sshport']);
		} else {
			config_del_path('system/ssh/port');
		}

		if (($sshd_enabled != config_path_enabled('system/ssh')) ||
		    ($sshd_keyonly != config_get_path('system/ssh/sshdkeyonly')) ||
		    ($sshd_agentforwarding != config_path_enabled('system/ssh', 'sshdagentforwarding')) ||
		    ($sshd_port != config_get_path('system/ssh/port'))) {
			$restart_sshd = true;
		}


		if ($restart_webgui && !$json) {
			global $_SERVER;
			$http_host_port = explode("]", $_SERVER['HTTP_HOST']);
			/* IPv6 address check */
			if (strstr($_SERVER['HTTP_HOST'], "]")) {
				if (count($http_host_port) > 1) {
					array_pop($http_host_port);
					$host = str_replace(array("[", "]"), "", implode(":", $http_host_port));
					$host = "[{$host}]";
				} else {
					$host = str_replace(array("[", "]"), "", implode(":", $http_host_port));
					$host = "[{$host}]";
				}
			} else {
				list($host) = explode(":", $_SERVER['HTTP_HOST']);
			}
			$prot = config_get_path('system/webgui/protocol', 'http');
			$port = config_get_path('system/webgui/port');
			if ($port) {
				$url = "{$prot}://{$host}:{$port}/system_advanced_admin.php";
			} else {
				$url = "{$prot}://{$host}/system_advanced_admin.php";
			}
		}

		$restart_sshguard = false;
		if (config_get_path('system/sshguard_threshold') != $pconfig['sshguard_threshold']) {
			config_set_path('system/sshguard_threshold', $pconfig['sshguard_threshold']);
			$restart_sshguard = true;
		}
		if (config_get_path('system/sshguard_blocktime') != $pconfig['sshguard_blocktime']) {
			config_set_path('system/sshguard_blocktime', $pconfig['sshguard_blocktime']);
			$restart_sshguard = true;
		}
		if (config_get_path('system/sshguard_detection_time') != $pconfig['sshguard_detection_time']) {
			config_set_path('system/sshguard_detection_time', $pconfig['sshguard_detection_time']);
			$restart_sshguard = true;
		}
		if (config_get_path('system/sshguard_whitelist') != $pconfig['sshguard_whitelist']) {
			config_set_path('system/sshguard_whitelist', $pconfig['sshguard_whitelist']);
			$restart_sshguard = true;
		}

		write_config("Admin Access Advanced Settings saved");

		$changes_applied = true;
		$retval = 0;
		$retval |= filter_configure();
		if ($restart_sshguard) {
			$retval |= system_syslogd_start(true);
		}

		if ($restart_webgui && !$json) {
			$extra_save_msg = sprintf("<br />" . gettext("One moment...redirecting to %s in 20 seconds."), $url);
		}

		console_configure();
		// Restart DNS in case dns rebinding toggled
		if (config_path_enabled('dnsmasq', 'enable')) {
			services_dnsmasq_configure();
		} elseif (config_path_enabled('unbound', 'enable')) {
			services_unbound_configure();
		}
	}

	if ($restart_webgui && !$json) {
		echo "<meta http-equiv=\"refresh\" content=\"20;url={$url}\" />";
	}

	if ($restart_sshd && $json) {
		restart_SSHD();
	}

	if ($restart_webgui && $json) {
		restart_GUI();
	}

	$rv = array();

	$rv['pconfig'] = $pconfig;
	$rv['input_errors'] = $input_errors;
	$rv['extra'] = $extra_save_msg;
	$rv['restartui'] = $restart_webgui;
	$rv['restartsshd'] = $restart_sshd;
	$rv['changesapplied'] = $changes_applied;
	$rv['retval'] = $retval;

	return $json ? json_encode($rv) : $rv;
}

function restart_GUI() {
	ob_flush();
	flush();
	log_error(gettext("webConfigurator configuration has changed. Restarting webConfigurator."));
	send_event("service restart webgui");
}

function restart_SSHD() {
	killbyname("sshd");
	log_error(gettext("secure shell configuration has changed. Stopping sshd."));

	if (config_path_enabled('system/ssh', 'enable')) {
		log_error(gettext("secure shell configuration has changed. Restarting sshd."));
		send_event("service restart sshd");
	}
}

?>
