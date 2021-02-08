<?php
/*
 * system_advanced.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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

// Functions included by system_advanced_sysctl.php ===========================

// Return a list of system tunables
function getTunables($json = false) {
	$st = system_get_sysctls();

	if (!$json) {
		return $st;
	}

	$tunables = [];

	foreach ($st as $t) {
	   array_push($tunables, $t);
	}

	return(json_encode($tunables));
}

// Delete a system tunable
function deleteTunable($id, $complete = false) {
	global $config;

	init_config_arr(array('sysctl', 'item'));
	$a_tunable = &$config['sysctl']['item'];

	if ($a_tunable[$id]) {
		unset($a_tunable[$id]);
		write_config("Custom System Tunables deleted");
		if ($complete) {
			system_setup_sysctl();
		} else {
			mark_subsystem_dirty('sysctl');
			pfSenseHeader("system_advanced_sysctl.php");
		}
		return true;
	}

	return false;
}

// Update tunable if it exists, otherwise create a new one
function saveTunable($post, $id, $json = false) {
	global $config;

	init_config_arr(array('sysctl', 'item'));
	$a_tunable = &$config['sysctl']['item'];

	$rv = array();

	$rv['pconfig'] = $post;

	$tunableent = array();

	if (!$post['tunable'] || !isset($post['value'])) {
		$input_errors[] = gettext("Both a name and a value must be specified.");
	} else if (preg_match("/[^a-zA-Z0-9.\-_%\/]/", $post['value'])) {
		$input_errors[] = gettext("The value may only contain alphanumeric characters, -, _, %, and /.");
	} else {
		$tunableent['tunable'] = htmlspecialchars($post['tunable']);
		$tunableent['value'] = htmlspecialchars($post['value']);
		$tunableent['descr'] = strip_tags($post['descr']);

		if (isset($id) && isset($a_tunable[$id])) {
			$a_tunable[$id] = $tunableent;
		} else {
			$a_tunable[] = $tunableent;
		}

		if (!$json) {
			mark_subsystem_dirty('sysctl');
		}

		write_config("System Tunables Advanced Settings saved");
		if ($json) {
			system_setup_sysctl();
		}
	}

	$rv['input_errors'] = $input_errors;

	return $json ? json_encode($rv) : $rv;
}

// Functions included by system_advanced_firewall.php =========================
function getSystemAdvancedFirewall($json = false) {
	global $config;

	$pconfig = array();

	$pconfig['adaptiveend'] = $config['system']['adaptiveend'];
	$pconfig['adaptivestart'] = $config['system']['adaptivestart'];
	$pconfig['aliasesresolveinterval'] = $config['system']['aliasesresolveinterval'];
	$pconfig['bogonsinterval'] = $config['system']['bogons']['interval'];
	$pconfig['bypassstaticroutes'] = isset($config['filter']['bypassstaticroutes']);
	$pconfig['checkaliasesurlcert'] = isset($config['system']['checkaliasesurlcert']);
	$pconfig['disablefilter'] = $config['system']['disablefilter'];
	$pconfig['disablenatreflection'] = $config['system']['disablenatreflection'];
	$pconfig['disablenegate'] = isset($config['system']['disablenegate']);
	$pconfig['disablereplyto'] = isset($config['system']['disablereplyto']);
	$pconfig['disablescrub'] = isset($config['system']['disablescrub']);
	$pconfig['disablevpnrules'] = isset($config['system']['disablevpnrules']);
	$pconfig['enablebinatreflection'] = $config['system']['enablebinatreflection'];
	$pconfig['enablenatreflectionhelper'] = $config['system']['enablenatreflectionhelper'];
	$pconfig['enablenatreflectionpurenat'] = $config['system']['enablenatreflectionpurenat'];
	$pconfig['icmperrortimeout'] = $config['system']['icmperrortimeout'];
	$pconfig['icmpfirsttimeout'] = $config['system']['icmpfirsttimeout'];
	$pconfig['maximumfrags'] = $config['system']['maximumfrags'];
	$pconfig['maximumstates'] = $config['system']['maximumstates'];
	$pconfig['maximumtableentries'] = $config['system']['maximumtableentries'];
	$pconfig['no_apipa_block'] = isset($config['system']['no_apipa_block']);
	$pconfig['optimization'] = $config['system']['optimization'];
	$pconfig['otherfirsttimeout'] = $config['system']['otherfirsttimeout'];
	$pconfig['othermultipletimeout'] = $config['system']['othermultipletimeout'];
	$pconfig['othersingletimeout'] = $config['system']['othersingletimeout'];
	$pconfig['reflectiontimeout'] = $config['system']['reflectiontimeout'];
	$pconfig['scrubnodf'] = $config['system']['scrubnodf'];
	$pconfig['scrubrnid'] = $config['system']['scrubrnid'];
	$pconfig['tcpclosedtimeout'] = $config['system']['tcpclosedtimeout'];
	$pconfig['tcpclosingtimeout'] = $config['system']['tcpclosingtimeout'];
	$pconfig['tcpestablishedtimeout'] = $config['system']['tcpestablishedtimeout'];
	$pconfig['tcpfinwaittimeout'] = $config['system']['tcpfinwaittimeout'];
	$pconfig['tcpfirsttimeout'] = $config['system']['tcpfirsttimeout'];
	$pconfig['tcpopeningtimeout'] = $config['system']['tcpopeningtimeout'];
	$pconfig['tcptsdifftimeout'] = $config['system']['tcptsdifftimeout'];
	$pconfig['tftpinterface'] = explode(",", $config['system']['tftpinterface']);
	$pconfig['udpfirsttimeout'] = $config['system']['udpfirsttimeout'];
	$pconfig['udpmultipletimeout'] = $config['system']['udpmultipletimeout'];
	$pconfig['udpsingletimeout'] = $config['system']['udpsingletimeout'];

	return $json ? json_encode($pconfig) : $pconfig;
}

function saveSystemAdvancedFirewall($post, $json = false) {
	global $g, $config;

	$rv = array();

	/* input validation */
	if ((isset($post['adaptivestart']) && !isset($post['adaptiveend'])) || (!isset($post['adaptivestart']) && isset($post['adaptiveend']))) {
		$input_errors[] = gettext("The Firewall Adaptive values must be set together.");
	}
	if (isset($post['adaptivestart']) && (strlen($post['adaptivestart']) > 0) && !is_numericint($post['adaptivestart'])) {
		$input_errors[] = gettext("The Firewall Adaptive Start value must be an integer.");
	}
	if (isset($post['adaptive-end']) && (strlen($post['adaptive-end']) > 0) && !is_numericint($post['adaptive-end'])) {
		$input_errors[] = gettext("The Firewall Adaptive End value must be an integer.");
	}
	if ($post['firewall-maximum-states'] && !is_numericint($post['firewall-maximum-states'])) {
		$input_errors[] = gettext("The Firewall Maximum States value must be an integer.");
	}
	if ($post['aliases-hostnames-resolve-interval'] && !is_numericint($post['aliases-hostnames-resolve-interval'])) {
		$input_errors[] = gettext("The Aliases Hostname Resolve Interval value must be an integer.");
	}
	if ($post['firewall-maximum-table-entries'] && !is_numericint($post['firewall-maximum-table-entries'])) {
		$input_errors[] = gettext("The Firewall Maximum Table Entries value must be an integer.");
	}
	if ($post['maximumfrags'] && !is_numericint($post['maximumfrags'])) {
		$input_errors[] = gettext("The Firewall Maximum Fragment Entries value must be an integer.");
	}
	if ($post['tcpidletimeout'] && !is_numericint($post['tcpidletimeout'])) {
		$input_errors[] = gettext("The TCP idle timeout must be an integer.");
	}
	if ($post['reflectiontimeout'] && !is_numericint($post['reflectiontimeout'])) {
		$input_errors[] = gettext("The Reflection timeout must be an integer.");
	}
	if ($post['tcpfirsttimeout'] && !is_numericint($post['tcpfirsttimeout'])) {
		$input_errors[] = gettext("The TCP first timeout value must be an integer.");
	}
	if ($post['tcpopeningtimeout'] && !is_numericint($post['tcpopeningtimeout'])) {
		$input_errors[] = gettext("The TCP opening timeout value must be an integer.");
	}
	if ($post['tcpestablishedtimeout'] && !is_numericint($post['tcpestablishedtimeout'])) {
		$input_errors[] = gettext("The TCP established timeout value must be an integer.");
	}
	if ($post['tcpclosingtimeout'] && !is_numericint($post['tcpclosingtimeout'])) {
		$input_errors[] = gettext("The TCP closing timeout value must be an integer.");
	}
	if ($post['tcpfinwaittimeout'] && !is_numericint($post['tcpfinwaittimeout'])) {
		$input_errors[] = gettext("The TCP FIN wait timeout value must be an integer.");
	}
	if ($post['tcpclosedtimeout'] && !is_numericint($post['tcpclosedtimeout'])) {
		$input_errors[] = gettext("The TCP closed timeout value must be an integer.");
	}
	if ($post['tcptsdifftimeout'] && !is_numericint($post['tcptsdifftimeout'])) {
		$input_errors[] = gettext("The TCP tsdiff timeout value must be an integer.");
	}
	if ($post['udpfirsttimeout'] && !is_numericint($post['udpfirsttimeout'])) {
		$input_errors[] = gettext("The UDP first timeout value must be an integer.");
	}
	if ($post['udpsingletimeout'] && !is_numericint($post['udpsingletimeout'])) {
		$input_errors[] = gettext("The UDP single timeout value must be an integer.");
	}
	if ($post['udpmultipletimeout'] && !is_numericint($post['udpmultipletimeout'])) {
		$input_errors[] = gettext("The UDP multiple timeout value must be an integer.");
	}
	if ($post['icmpfirsttimeout'] && !is_numericint($post['icmpfirsttimeout'])) {
		$input_errors[] = gettext("The ICMP first timeout value must be an integer.");
	}
	if ($post['icmperrortimeout'] && !is_numericint($post['icmperrortimeout'])) {
		$input_errors[] = gettext("The ICMP error timeout value must be an integer.");
	}
	if ($post['otherfirsttimeout'] && !is_numericint($post['otherfirsttimeout'])) {
		$input_errors[] = gettext("The Other first timeout value must be an integer.");
	}
	if ($post['othersingletimeout'] && !is_numericint($post['othersingletimeout'])) {
		$input_errors[] = gettext("The Other single timeout value must be an integer.");
	}
	if ($post['othermultipletimeout'] && !is_numericint($post['othermultipletimeout'])) {
		$input_errors[] = gettext("The Other multiple timeout value must be an integer.");
	}

	if ($post['maximumtableentries']) {
		$maximumtableentries = $post['maximumtableentries'];
	} else {
		$maximumtableentries = pfsense_default_table_entries_size();
	}
	if (!is_numericint($maximumtableentries)) {
		$input_errors[] = gettext("The Firewall Maximum Table Entries value must be an integer.");
	} else if (is_bogonsv6_used() &&
	    $maximumtableentries < $g['minimumtableentries_bogonsv6']) {
		$input_errors[] = sprintf(gettext(
		    "The Firewall Maximum Table Entries value must be greater than %s when block bogons is enabled."),
		    $g['minimumtableentries_bogonsv6']);
	}

	if (!$json) {
		ob_flush();
		flush();
	}

	if (!$input_errors) {

		if ($post['disablefilter'] == "yes") {
			$config['system']['disablefilter'] = "enabled";
		} else {
			unset($config['system']['disablefilter']);
		}

		if ($post['disablevpnrules'] == "yes") {
			$config['system']['disablevpnrules'] = true;
		} else {
			unset($config['system']['disablevpnrules']);
		}
		if ($post['rfc959workaround'] == "yes") {
			$config['system']['rfc959workaround'] = "enabled";
		} else {
			unset($config['system']['rfc959workaround']);
		}

		if ($post['scrubnodf'] == "yes") {
			$config['system']['scrubnodf'] = "enabled";
		} else {
			unset($config['system']['scrubnodf']);
		}

		if ($post['scrubrnid'] == "yes") {
			$config['system']['scrubrnid'] = "enabled";
		} else {
			unset($config['system']['scrubrnid']);
		}

		if (is_numericint($post['adaptiveend'])) {
			$config['system']['adaptiveend'] = $post['adaptiveend'];
		} else {
			unset($config['system']['adaptiveend']);
		}
		if (is_numericint($post['adaptivestart'])) {
			$config['system']['adaptivestart'] = $post['adaptivestart'];
		} else {
			unset($config['system']['adaptivestart']);
		}

		if ($post['checkaliasesurlcert'] == "yes") {
			$config['system']['checkaliasesurlcert'] = true;
		} else {
			unset($config['system']['checkaliasesurlcert']);
		}

		$config['system']['optimization'] = $post['optimization'];
		$config['system']['maximumstates'] = $post['maximumstates'];
		$config['system']['aliasesresolveinterval'] = $post['aliasesresolveinterval'];
		$config['system']['maximumtableentries'] = $post['maximumtableentries'];
		$config['system']['maximumfrags'] = $post['maximumfrags'];

		if (!empty($post['tcpfirsttimeout'])) {
			$config['system']['tcpfirsttimeout'] = $post['tcpfirsttimeout'];
		} else {
			unset($config['system']['tcpfirsttimeout']);
		}
		if (!empty($post['tcpopeningtimeout'])) {
			$config['system']['tcpopeningtimeout'] = $post['tcpopeningtimeout'];
		} else {
			unset($config['system']['tcpopeningtimeout']);
		}
		if (!empty($post['tcpestablishedtimeout'])) {
			$config['system']['tcpestablishedtimeout'] = $post['tcpestablishedtimeout'];
		} else {
			unset($config['system']['tcpestablishedtimeout']);
		}
		if (!empty($post['tcpclosingtimeout'])) {
			$config['system']['tcpclosingtimeout'] = $post['tcpclosingtimeout'];
		} else {
			unset($config['system']['tcpclosingtimeout']);
		}
		if (!empty($post['tcpfinwaittimeout'])) {
			$config['system']['tcpfinwaittimeout'] = $post['tcpfinwaittimeout'];
		} else {
			unset($config['system']['tcpfinwaittimeout']);
		}
		if (!empty($post['tcpclosedtimeout'])) {
			$config['system']['tcpclosedtimeout'] = $post['tcpclosedtimeout'];
		} else {
			unset($config['system']['tcpclosedtimeout']);
		}
		if (!empty($post['tcptsdifftimeout'])) {
			$config['system']['tcptsdifftimeout'] = $post['tcptsdifftimeout'];
		} else {
			unset($config['system']['tcptsdifftimeout']);
		}
		if (!empty($post['udpfirsttimeout'])) {
			$config['system']['udpfirsttimeout'] = $post['udpfirsttimeout'];
		} else {
			unset($config['system']['udpfirsttimeout']);
		}
		if (!empty($post['udpsingletimeout'])) {
			$config['system']['udpsingletimeout'] = $post['udpsingletimeout'];
		} else {
			unset($config['system']['udpsingletimeout']);
		}
		if (!empty($post['udpmultipletimeout'])) {
			$config['system']['udpmultipletimeout'] = $post['udpmultipletimeout'];
		} else {
			unset($config['system']['udpmultipletimeout']);
		}
		if (!empty($post['icmpfirsttimeout'])) {
			$config['system']['icmpfirsttimeout'] = $post['icmpfirsttimeout'];
		} else {
			unset($config['system']['icmpfirsttimeout']);
		}
		if (!empty($post['icmperrortimeout'])) {
			$config['system']['icmperrortimeout'] = $post['icmperrortimeout'];
		} else {
			unset($config['system']['icmperrortimeout']);
		}
		if (!empty($post['otherfirsttimeout'])) {
			$config['system']['otherfirsttimeout'] = $post['otherfirsttimeout'];
		} else {
			unset($config['system']['otherfirsttimeout']);
		}
		if (!empty($post['othersingletimeout'])) {
			$config['system']['othersingletimeout'] = $post['othersingletimeout'];
		} else {
			unset($config['system']['othersingletimeout']);
		}
		if (!empty($post['othermultipletimeout'])) {
			$config['system']['othermultipletimeout'] = $post['othermultipletimeout'];
		} else {
			unset($config['system']['othermultipletimeout']);
		}

		if ($post['natreflection'] == "proxy") {
			unset($config['system']['disablenatreflection']);
			unset($config['system']['enablenatreflectionpurenat']);
		} else if ($post['natreflection'] == "purenat") {
			unset($config['system']['disablenatreflection']);
			$config['system']['enablenatreflectionpurenat'] = "yes";
		} else {
			$config['system']['disablenatreflection'] = "yes";
			unset($config['system']['enablenatreflectionpurenat']);
		}

		if ($post['enablebinatreflection'] == "yes") {
			$config['system']['enablebinatreflection'] = "yes";
		} else {
			unset($config['system']['enablebinatreflection']);
		}

		if ($post['disablereplyto'] == "yes") {
			$config['system']['disablereplyto'] = $post['disablereplyto'];
		} else {
			unset($config['system']['disablereplyto']);
		}

		if ($post['disablenegate'] == "yes") {
			$config['system']['disablenegate'] = $post['disablenegate'];
		} else {
			unset($config['system']['disablenegate']);
		}

		if ($post['no_apipa_block'] == "yes") {
			$config['system']['no_apipa_block'] = "enabled";
		} else {
			unset($config['system']['no_apipa_block']);
		}

		if ($post['enablenatreflectionhelper'] == "yes") {
			$config['system']['enablenatreflectionhelper'] = "yes";
		} else {
			unset($config['system']['enablenatreflectionhelper']);
		}

		$config['system']['reflectiontimeout'] = $post['reflectiontimeout'];

		if ($post['bypassstaticroutes'] == "yes") {
			$config['filter']['bypassstaticroutes'] = $post['bypassstaticroutes'];
		} elseif (isset($config['filter']['bypassstaticroutes'])) {
			unset($config['filter']['bypassstaticroutes']);
		}

		if ($post['disablescrub'] == "yes") {
			$config['system']['disablescrub'] = $post['disablescrub'];
		} else {
			unset($config['system']['disablescrub']);
		}

		if ($post['tftpinterface']) {
			$config['system']['tftpinterface'] = implode(",", $post['tftpinterface']);
		} else {
			unset($config['system']['tftpinterface']);
		}

		if ($post['bogonsinterval'] != $config['system']['bogons']['interval']) {
			switch ($post['bogonsinterval']) {
				case 'daily':
					install_cron_job("/usr/bin/nice -n20 /etc/rc.update_bogons.sh", true, "1", "3", "*", "*", "*", "root", false);
					break;
				case 'weekly':
					install_cron_job("/usr/bin/nice -n20 /etc/rc.update_bogons.sh", true, "1", "3", "*", "*", "0", "root", false);
					break;
				case 'monthly':
					// fall through
				default:
					install_cron_job("/usr/bin/nice -n20 /etc/rc.update_bogons.sh", true, "1", "3", "1", "*", "*", "root", false);
			}
			$config['system']['bogons']['interval'] = $post['bogonsinterval'];
		}

		write_config(gettext("Changed Advanced Firewall/NAT settings."));

		// Kill filterdns when value changes, filter_configure() will restart it
		if (($old_aliasesresolveinterval != $config['system']['aliasesresolveinterval']) &&
		    isvalidpid("{$g['varrun_path']}/filterdns.pid")) {
			killbypid("{$g['varrun_path']}/filterdns.pid");
		}

		/* Update net.pf.request_maxcount when necessary
		 * See https://redmine.pfsense.org/issues/10861 */
		if ($old_maximumtableentries !=
		    $config['system']['maximumtableentries']) {
			system_setup_sysctl();
		}

		$changes_applied = true;
		$retval = 0;
		$retval |= filter_configure();
	}

	$rv['input_errors'] = $input_errors;
	$rv['retval'] = $retval;
	$rv['changes_applied'] = $changes_applied;

	return $json ? json_encode($rv) : $rv;
}

// Functions included by system_advanced_misc.php =============================
function getSystemAdvancedMisc($json = false) {
	global $config;

	$pconfig = array();
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
	$pconfig['use_mfs_tmpvar'] = isset($config['system']['use_mfs_tmpvar']);
	$pconfig['use_mfs_tmp_size'] = $config['system']['use_mfs_tmp_size'];
	$pconfig['use_mfs_var_size'] = $config['system']['use_mfs_var_size'];
	$pconfig['do_not_send_uniqueid'] = isset($config['system']['do_not_send_uniqueid']);
	$pconfig['available_kernel_memory'] = get_single_sysctl("vm.kmem_map_free");
	$pconfig['pti'] = get_single_sysctl('vm.pmap.pti');
	$pconfig['mds']= get_single_sysctl('hw.mds_disable_state');

	/* Adjust available kernel memory to account for existing RAM disks
	 * https://redmine.pfsense.org/issues/10420 */
	if (isset($config['system']['use_mfs_tmpvar'])) {
		/* Get current RAM disk sizes */
		$pconfig['available_kernel_memory'] += ((int) trim(exec("/bin/df -k /tmp /var | /usr/bin/awk '/\/dev\/md/ {sum += \$2 * 1024} END {print sum}'")));
	}

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
		$rv['reboot'] = ($tmpvar_enabled != $tmpvar_set) ||
		   (file_exists('/conf/ram_disks_failed') && $post['use_mfs_tmpvar'] == "yes");

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
	$post['available_kernel_memory'] = get_single_sysctl("vm.kmem_map_free");
	$post['pti'] = get_single_sysctl('vm.pmap.pti');
	$post['mds']= get_single_sysctl('hw.mds_disable_state');

	if (!$json) {
		$rv['post'] = $post;		
		$rv['retval'] = $retval;
		$rv['changes_applied'] = $changes_applied;
	}

	return $json ? json_encode($rv) : $rv;
}
?>