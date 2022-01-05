<?php
/*
 * system_advanced_admin.inc
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

// Functions included by system_advanced_admin.php ============================

// Retrieve an array of advanced admin settings
function getAdvancedAdminConfig($json = false) {
	global $config;

	$pconfig = array();

	$pconfig['webguiproto'] = $config['system']['webgui']['protocol'];
	$pconfig['webguiport'] = $config['system']['webgui']['port'];
	$pconfig['max_procs'] = ($config['system']['webgui']['max_procs']) ? $config['system']['webgui']['max_procs'] : 2;
	$pconfig['ssl-certref'] = $config['system']['webgui']['ssl-certref'];
	$pconfig['disablehttpredirect'] = isset($config['system']['webgui']['disablehttpredirect']);
	$pconfig['disablehsts'] = isset($config['system']['webgui']['disablehsts']);
	$pconfig['ocsp-staple'] = $config['system']['webgui']['ocsp-staple'];
	$pconfig['disableconsolemenu'] = isset($config['system']['disableconsolemenu']);
	$pconfig['noantilockout'] = isset($config['system']['webgui']['noantilockout']);
	$pconfig['nodnsrebindcheck'] = isset($config['system']['webgui']['nodnsrebindcheck']);
	$pconfig['nohttpreferercheck'] = isset($config['system']['webgui']['nohttpreferercheck']);
	$pconfig['pagenamefirst'] = isset($config['system']['webgui']['pagenamefirst']);
	$pconfig['loginautocomplete'] = isset($config['system']['webgui']['loginautocomplete']);
	$pconfig['althostnames'] = $config['system']['webgui']['althostnames'];
	$pconfig['enableserial'] = $config['system']['enableserial'];
	$pconfig['serialspeed'] = $config['system']['serialspeed'];
	$pconfig['primaryconsole'] = $config['system']['primaryconsole'];
	$pconfig['enablesshd'] = $config['system']['ssh']['enable'];
	$pconfig['sshport'] = $config['system']['ssh']['port'];
	$pconfig['sshdkeyonly'] = $config['system']['ssh']['sshdkeyonly'];
	$pconfig['sshdagentforwarding'] = isset($config['system']['ssh']['sshdagentforwarding']);
	$pconfig['quietlogin'] = isset($config['system']['webgui']['quietlogin']);
	$pconfig['sshguard_threshold'] = $config['system']['sshguard_threshold'] ?? '';
	$pconfig['sshguard_blocktime'] = $config['system']['sshguard_blocktime'] ?? '';
	$pconfig['sshguard_detection_time'] = $config['system']['sshguard_detection_time'] ?? '';
	$pconfig['sshguard_whitelist'] = $config['system']['sshguard_whitelist'] ?? '';
	$a_cert = &$config['cert'];
	$pconfig['certsavailable'] = (is_array($a_cert) && count($a_cert));
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
	global $config;

	$valid_webguiproto = array('http', 'https');
	$input_errors = array();
	$pconfig = $post;

	/* input validation */

	if (!in_array($pconfig['webguiproto'], $valid_webguiproto)) {
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
		if (l_update_if_changed("webgui protocol", $config['system']['webgui']['protocol'], $post['webguiproto'])) {
			$restart_webgui = true;
		}

		if (l_update_if_changed("webgui port", $config['system']['webgui']['port'], $post['webguiport'])) {
			$restart_webgui = true;
		}

		if (l_update_if_changed("webgui certificate", $config['system']['webgui']['ssl-certref'], $post['ssl-certref'])) {
			$restart_webgui = true;
		}

		if (l_update_if_changed("webgui max processes", $config['system']['webgui']['max_procs'], $post['max_procs'])) {
			$restart_webgui = true;
		}

		// Restart the webgui only if this actually changed
		if ($post['webgui-redirect'] == "yes") {
			if ($config['system']['webgui']['disablehttpredirect'] != true) {
				$restart_webgui = true;
			}

			$config['system']['webgui']['disablehttpredirect'] = true;
		} else {
			if (isset($config['system']['webgui']['disablehttpredirect'])) {
				$restart_webgui = true;
			}

			unset($config['system']['webgui']['disablehttpredirect']);
		}

		if ($post['webgui-hsts'] == "yes") {
			if ($config['system']['webgui']['disablehsts'] != true) {
				$restart_webgui = true;
			}

			$config['system']['webgui']['disablehsts'] = true;
		} else {
			if (isset($config['system']['webgui']['disablehsts'])) {
				$restart_webgui = true;
			}

			unset($config['system']['webgui']['disablehsts']);
		}

		if ($post['ocsp-staple'] == "yes") {
			if ($config['system']['webgui']['ocsp-staple'] != true) {
				$restart_webgui = true;
			}

			$config['system']['webgui']['ocsp-staple'] = true;
		} else {
			if (isset($config['system']['webgui']['ocsp-staple'])) {
				$restart_webgui = true;
			}

			unset($config['system']['webgui']['ocsp-staple']);
		}
		
		if ($post['webgui-login-messages'] == "yes") {
			$config['system']['webgui']['quietlogin'] = true;
		} else {
			unset($config['system']['webgui']['quietlogin']);
		}

		if ($post['disableconsolemenu'] == "yes") {
			$config['system']['disableconsolemenu'] = true;
		} else {
			unset($config['system']['disableconsolemenu']);
		}

		if ($post['noantilockout'] == "yes") {
			$config['system']['webgui']['noantilockout'] = true;
		} else {
			unset($config['system']['webgui']['noantilockout']);
		}

		if ($post['enableserial'] == "yes" || $g['enableserial_force']) {
			$config['system']['enableserial'] = true;
		} else {
			unset($config['system']['enableserial']);
		}

		if (is_numericint($post['serialspeed'])) {
			$config['system']['serialspeed'] = $post['serialspeed'];
		} else {
			unset($config['system']['serialspeed']);
		}

		if ($post['primaryconsole']) {
			$config['system']['primaryconsole'] = $post['primaryconsole'];
		} else {
			unset($config['system']['primaryconsole']);
		}

		if ($post['nodnsrebindcheck'] == "yes") {
			$config['system']['webgui']['nodnsrebindcheck'] = true;
		} else {
			unset($config['system']['webgui']['nodnsrebindcheck']);
		}

		if ($post['nohttpreferercheck'] == "yes") {
			$config['system']['webgui']['nohttpreferercheck'] = true;
		} else {
			unset($config['system']['webgui']['nohttpreferercheck']);
		}

		if ($post['pagenamefirst'] == "yes") {
			$config['system']['webgui']['pagenamefirst'] = true;
		} else {
			unset($config['system']['webgui']['pagenamefirst']);
		}

		if ($post['loginautocomplete'] == "yes") {
			$config['system']['webgui']['loginautocomplete'] = true;
		} else {
			unset($config['system']['webgui']['loginautocomplete']);
		}

		if ($post['althostnames']) {
			$config['system']['webgui']['althostnames'] = $post['althostnames'];
		} else {
			unset($config['system']['webgui']['althostnames']);
		}

		$sshd_enabled = $config['system']['ssh']['enable'];
		if ($post['enablesshd']) {
			$config['system']['ssh']['enable'] = "enabled";
		} else {
			unset($config['system']['ssh']['enable']);
		}

		$sshd_keyonly = $config['system']['ssh']['sshdkeyonly'];
		if ($post['sshdkeyonly'] == "enabled") {
			$config['system']['ssh']['sshdkeyonly'] = "enabled";
		} else if ($post['sshdkeyonly'] == "both") {
			$config['system']['ssh']['sshdkeyonly'] = "both";
		} else if (isset($config['system']['ssh']['sshdkeyonly'])) {
			unset($config['system']['ssh']['sshdkeyonly']);
		}

		$sshd_agentforwarding = isset($config['system']['ssh']['sshdagentforwarding']);
		if ($post['sshdagentforwarding']) {
			$config['system']['ssh']['sshdagentforwarding'] = 'enabled';
		} else if (isset($config['system']['ssh']['sshdagentforwarding'])) {
			unset($config['system']['ssh']['sshdagentforwarding']);
		}

		$sshd_port = $config['system']['ssh']['port'];
		if ($post['sshport']) {
			$config['system']['ssh']['port'] = $post['sshport'];
		} else if (isset($config['system']['ssh']['port'])) {
			unset($config['system']['ssh']['port']);
		}

		if (($sshd_enabled != $config['system']['ssh']['enable']) ||
		    ($sshd_keyonly != $config['system']['ssh']['sshdkeyonly']) ||
		    ($sshd_agentforwarding != $config['system']['ssh']['sshdagentforwarding']) ||
		    ($sshd_port != $config['system']['ssh']['port'])) {
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
			$prot = in_array($config['system']['webgui']['protocol'], $valid_webguiproto) ? $config['system']['webgui']['protocol'] : 'http' ;
			$port = $config['system']['webgui']['port'];
			if ($port) {
				$url = "{$prot}://{$host}:{$port}/system_advanced_admin.php";
			} else {
				$url = "{$prot}://{$host}/system_advanced_admin.php";
			}
		}

		$restart_sshguard = false;
		if (l_update_if_changed("login protection threshold",
		    $config['system']['sshguard_threshold'],
		    $pconfig['sshguard_threshold'])) {
			$restart_sshguard = true;
		}
		if (l_update_if_changed("login protection blocktime",
		    $config['system']['sshguard_blocktime'],
		    $pconfig['sshguard_blocktime'])) {
			$restart_sshguard = true;
		}
		if (l_update_if_changed("login protection detection_time",
		    $config['system']['sshguard_detection_time'],
		    $pconfig['sshguard_detection_time'])) {
			$restart_sshguard = true;
		}
		if (l_update_if_changed("login protection whitelist",
		    $config['system']['sshguard_whitelist'],
		    $pconfig['sshguard_whitelist'])) {
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
		if (isset($config['dnsmasq']['enable'])) {
			services_dnsmasq_configure();
		} elseif (isset($config['unbound']['enable'])) {
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
	global $config;

	killbyname("sshd");
	log_error(gettext("secure shell configuration has changed. Stopping sshd."));

	if ($config['system']['ssh']['enable']) {
		log_error(gettext("secure shell configuration has changed. Restarting sshd."));
		send_event("service restart sshd");
	}
}

/* update the changedesc and changecount(er) variables */
function l_update_changedesc($update) {
	global $changedesc;
	global $changecount;

	$changedesc .= " {$update}";
	$changecount++;
}

/* Check if variable has changed, update and log if it has
 * returns true if var changed
 * varname = variable name in plain text
 * orig = original value
 * new = new value
 */
function l_update_if_changed($varname, & $orig, $new) {
	if (is_array($orig) && is_array($new)) {
		$a_diff = array_diff($orig, $new);
		foreach ($a_diff as $diff) {
			l_update_changedesc("removed {$varname}: \"{$diff}\"");
		}
		$a_diff = array_diff($new, $orig);
		foreach ($a_diff as $diff) {
			l_update_changedesc("added {$varname}: \"{$diff}\"");
		}
		$orig = $new;
		return true;

	} else {
		if ($orig != $new) {
			l_update_changedesc("{$varname}: \"{$orig}\" -> \"{$new}\"");
			$orig = $new;
			return true;
		}
	}
	return false;
}

?>
