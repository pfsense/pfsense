<?php
/*
 * system_advanced_firewall.inc
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
require_once("shaper.inc");
require_once("system.inc");
require_once("util.inc");
require_once("pfsense-utils.inc");

$state_policy_options = [
	'if-bound' => gettext('Interface Bound States'),
	'floating' => gettext('Floating States'),
];


// Functions included by system_advanced_firewall.php =========================
function getSystemAdvancedFirewall($json = false) {
	$pconfig = array();

	$pconfig['adaptiveend'] = config_get_path('system/adaptiveend');
	$pconfig['adaptivestart'] = config_get_path('system/adaptivestart');
	$pconfig['aliasesresolveinterval'] = config_get_path('system/aliasesresolveinterval');
	$pconfig['bogonsinterval'] = config_get_path('system/bogons/interval');
	$pconfig['bypassstaticroutes'] = config_path_enabled('filter', 'bypassstaticroutes');
	$pconfig['checkaliasesurlcert'] = config_path_enabled('system', 'checkaliasesurlcert');
	$pconfig['statepolicy'] = config_get_path('system/statepolicy');
	$pconfig['disable_auto_floating_states'] = config_get_path('system/disable_auto_floating_states');
	$pconfig['disablefilter'] = config_get_path('system/disablefilter');
	$pconfig['disablenatreflection'] = config_get_path('system/disablenatreflection');
	$pconfig['disablenegate'] = config_path_enabled('system', 'disablenegate');
	$pconfig['disablereplyto'] = config_path_enabled('system', 'disablereplyto');
	$pconfig['disablescrub'] = config_path_enabled('system', 'disablescrub');
	$pconfig['disablevpnrules'] = config_path_enabled('system', 'disablevpnrules');
	$pconfig['enablebinatreflection'] = config_get_path('system/enablebinatreflection');
	$pconfig['enablenatreflectionhelper'] = config_get_path('system/enablenatreflectionhelper');
	$pconfig['enablenatreflectionpurenat'] = config_get_path('system/enablenatreflectionpurenat');
	$pconfig['icmperrortimeout'] = config_get_path('system/icmperrortimeout');
	$pconfig['icmpfirsttimeout'] = config_get_path('system/icmpfirsttimeout');
	$pconfig['maximumfrags'] = config_get_path('system/maximumfrags');
	$pconfig['maximumstates'] = config_get_path('system/maximumstates');
	$pconfig['maximumtableentries'] = config_get_path('system/maximumtableentries');
	$pconfig['no_apipa_block'] = config_path_enabled('system', 'no_apipa_block');
	$pconfig['optimization'] = config_get_path('system/optimization');
	$pconfig['otherfirsttimeout'] = config_get_path('system/otherfirsttimeout');
	$pconfig['othermultipletimeout'] = config_get_path('system/othermultipletimeout');
	$pconfig['othersingletimeout'] = config_get_path('system/othersingletimeout');
	$pconfig['reflectiontimeout'] = config_get_path('system/reflectiontimeout');
	$pconfig['scrubnodf'] = config_get_path('system/scrubnodf');
	$pconfig['scrubrnid'] = config_get_path('system/scrubrnid');
	$pconfig['tcpclosedtimeout'] = config_get_path('system/tcpclosedtimeout');
	$pconfig['tcpclosingtimeout'] = config_get_path('system/tcpclosingtimeout');
	$pconfig['tcpestablishedtimeout'] = config_get_path('system/tcpestablishedtimeout');
	$pconfig['tcpfinwaittimeout'] = config_get_path('system/tcpfinwaittimeout');
	$pconfig['tcpfirsttimeout'] = config_get_path('system/tcpfirsttimeout');
	$pconfig['tcpopeningtimeout'] = config_get_path('system/tcpopeningtimeout');
	$pconfig['tcptsdifftimeout'] = config_get_path('system/tcptsdifftimeout');
	$pconfig['sctpfirsttimeout'] = config_get_path('system/sctpfirsttimeout');
	$pconfig['sctpopeningtimeout'] = config_get_path('system/sctpopeningtimeout');
	$pconfig['sctpestablishedtimeout'] = config_get_path('system/sctpestablishedtimeout');
	$pconfig['sctpclosingtimeout'] = config_get_path('system/sctpclosingtimeout');
	$pconfig['sctpclosedtimeout'] = config_get_path('system/sctpclosedtimeout');
	$pconfig['tftpinterface'] = explode(",", config_get_path('system/tftpinterface'));
	$pconfig['udpfirsttimeout'] = config_get_path('system/udpfirsttimeout');
	$pconfig['udpmultipletimeout'] = config_get_path('system/udpmultipletimeout');
	$pconfig['udpsingletimeout'] = config_get_path('system/udpsingletimeout');
	$pconfig['vpn_scrubnodf'] = config_path_enabled('system', 'vpn_scrubnodf');
	$pconfig['vpn_fragment_reassemble'] = config_path_enabled('system', 'vpn_fragment_reassemble');
	$pconfig['maxmss_enable'] = config_path_enabled('system', 'maxmss_enable');
	$pconfig['maxmss'] = config_get_path('system/maxmss');

	return $json ? json_encode($pconfig) : $pconfig;
}

function saveSystemAdvancedFirewall($post, $json = false) {
	global $g, $state_policy_options;

	$rv = array();

	/* input validation */

	if (!empty($post['statepolicy']) &&
	    !array_key_exists($post['statepolicy'], $state_policy_options)) {
		$input_errors[] = gettext("Invalid Firewall State Policy value.");
	}

	if ((isset($post['adaptivestart']) && !isset($post['adaptiveend'])) || (!isset($post['adaptivestart']) && isset($post['adaptiveend']))) {
		$input_errors[] = gettext("The Firewall Adaptive values must be set together.");
	}
	if (isset($post['adaptivestart']) && (strlen($post['adaptivestart']) > 0) && !is_numericint($post['adaptivestart'])) {
		$input_errors[] = gettext("The Firewall Adaptive Start value must be an integer.");
	}
	if (isset($post['adaptiveend']) && (strlen($post['adaptiveend']) > 0) && !is_numericint($post['adaptiveend'])) {
		$input_errors[] = gettext("The Firewall Adaptive End value must be an integer.");
	}
	if ($post['maximumstates'] && !is_numericint($post['maximumstates'])) {
		$input_errors[] = gettext("The Firewall Maximum States value must be an integer.");
	}
	if ($post['aliasesresolveinterval'] && !is_numericint($post['aliasesresolveinterval'])) {
		$input_errors[] = gettext("The Aliases Hostname Resolve Interval value must be an integer.");
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
	if ($post['sctpfirsttimeout'] && !is_numericint($post['sctpfirsttimeout'])) {
		$input_errors[] = gettext("The SCTP First timeout value must be an integer.");
	}
	if ($post['sctpopeningtimeout'] && !is_numericint($post['sctpopeningtimeout'])) {
		$input_errors[] = gettext("The SCTP Opening timeout value must be an integer.");
	}
	if ($post['sctpestablishedtimeout'] && !is_numericint($post['sctpestablishedtimeout'])) {
		$input_errors[] = gettext("The SCTP Established timeout value must be an integer.");
	}
	if ($post['sctpclosingtimeout'] && !is_numericint($post['sctpclosingtimeout'])) {
		$input_errors[] = gettext("The SCTP Closing timeout value must be an integer.");
	}
	if ($post['sctpclosedtimeout'] && !is_numericint($post['sctpclosedtimeout'])) {
		$input_errors[] = gettext("The SCTP Closed timeout value must be an integer.");
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
		$maximumtableentries = pfsense_current_table_entries_size();
	}
	if (!is_numericint($maximumtableentries)) {
		$input_errors[] = gettext("The Firewall Maximum Table Entries value must be an integer.");
	} else if (is_bogonsv6_used() &&
	    $maximumtableentries < g_get('minimumtableentries_bogonsv6')) {
		$input_errors[] = sprintf(gettext(
		    "The Firewall Maximum Table Entries value must be greater than %s when block bogons is enabled."),
		    g_get('minimumtableentries_bogonsv6'));
	}

	if ($post['maxmss']) {
		if (!is_numericint($post['maxmss'])) {
			$input_errors[] = gettext("An integer must be specified for Maximum MSS.");
		}
		if (($post['maxmss'] < 576) || ($post['maxmss'] > 65535)) {
			$input_errors[] = gettext("An integer between 576 and 65535 must be specified for Maximum MSS");
		}
	}

	if (!$json) {
		ob_flush();
		flush();
	}

	if (!$input_errors) {

		if ($post['disablefilter'] == "yes") {
			config_set_path('system/disablefilter', "enabled");
		} else {
			config_del_path('system/disablefilter');
		}

		config_set_path('system/statepolicy', $post['statepolicy']);
		if (isset($post['disable_auto_floating_states'])) {
			config_set_path('system/disable_auto_floating_states', true);
		} else {
			config_del_path('system/disable_auto_floating_states');
		}

		if ($post['disablevpnrules'] == "yes") {
			config_set_path('system/disablevpnrules', true);
		} else {
			config_del_path('system/disablevpnrules');
		}
		if ($post['rfc959workaround'] == "yes") {
			config_set_path('system/rfc959workaround', "enabled");
		} else {
			config_del_path('system/rfc959workaround');
		}

		if ($post['scrubnodf'] == "yes") {
			config_set_path('system/scrubnodf', "enabled");
		} else {
			config_del_path('system/scrubnodf');
		}

		if ($post['scrubrnid'] == "yes") {
			config_set_path('system/scrubrnid', "enabled");
		} else {
			config_del_path('system/scrubrnid');
		}

		if (is_numericint($post['adaptiveend'])) {
			config_set_path('system/adaptiveend', $post['adaptiveend']);
		} else {
			config_del_path('system/adaptiveend');
		}
		if (is_numericint($post['adaptivestart'])) {
			config_set_path('system/adaptivestart', $post['adaptivestart']);
		} else {
			config_del_path('system/adaptivestart');
		}

		if ($post['checkaliasesurlcert'] == "yes") {
			config_set_path('system/checkaliasesurlcert', true);
		} else {
			config_del_path('system/checkaliasesurlcert');
		}

		$setup_sysctl = ($post['maximumtableentries'] != config_get_path('system/maximumtableentries'));
		$restart_filterdns = ($post['aliasesresolveinterval'] != config_get_path('system/aliasesresolveinterval'));

		config_set_path('system/optimization', $post['optimization']);
		$maxstates_changed = (config_get_path('system/maximumstates') != $post['maximumstates']) ? true : false;
		config_set_path('system/maximumstates', $post['maximumstates']);
		config_set_path('system/aliasesresolveinterval', $post['aliasesresolveinterval']);
		config_set_path('system/maximumtableentries', $post['maximumtableentries']);
		config_set_path('system/maximumfrags', $post['maximumfrags']);

		if ($post['maxmss_enable'] == "yes") {
			config_set_path('system/maxmss_enable', true);
			config_set_path('system/maxmss', $post['maxmss']);
		} else {
				config_del_path('system/maxmss_enable');
				config_del_path('system/maxmss');
		}

		if ($post['vpn_scrubnodf'] == "yes") {
			config_set_path('system/vpn_scrubnodf', true);
		} else {
			config_del_path('system/vpn_scrubnodf');
		}

		if ($post['vpn_fragment_reassemble'] == "yes") {
			config_set_path('system/vpn_fragment_reassemble', true);
		} else {
			config_del_path('system/vpn_fragment_reassemble');
		}

		if (!empty($post['tcpfirsttimeout'])) {
			config_set_path('system/tcpfirsttimeout', $post['tcpfirsttimeout']);
		} else {
			config_del_path('system/tcpfirsttimeout');
		}
		if (!empty($post['tcpopeningtimeout'])) {
			config_set_path('system/tcpopeningtimeout', $post['tcpopeningtimeout']);
		} else {
			config_del_path('system/tcpopeningtimeout');
		}
		if (!empty($post['tcpestablishedtimeout'])) {
			config_set_path('system/tcpestablishedtimeout', $post['tcpestablishedtimeout']);
		} else {
			config_del_path('system/tcpestablishedtimeout');
		}
		if (!empty($post['tcpclosingtimeout'])) {
			config_set_path('system/tcpclosingtimeout', $post['tcpclosingtimeout']);
		} else {
			config_del_path('system/tcpclosingtimeout');
		}
		if (!empty($post['tcpfinwaittimeout'])) {
			config_set_path('system/tcpfinwaittimeout', $post['tcpfinwaittimeout']);
		} else {
			config_del_path('system/tcpfinwaittimeout');
		}
		if (!empty($post['tcpclosedtimeout'])) {
			config_set_path('system/tcpclosedtimeout', $post['tcpclosedtimeout']);
		} else {
			config_del_path('system/tcpclosedtimeout');
		}
		if (!empty($post['tcptsdifftimeout'])) {
			config_set_path('system/tcptsdifftimeout', $post['tcptsdifftimeout']);
		} else {
			config_del_path('system/tcptsdifftimeout');
		}
		if (!empty($post['sctpfirsttimeout'])) {
			config_set_path('system/sctpfirsttimeout', $post['sctpfirsttimeout']);
		} else {
			config_del_path('system/sctpfirsttimeout');
		}
		if (!empty($post['sctpopeningtimeout'])) {
			config_set_path('system/sctpopeningtimeout', $post['sctpopeningtimeout']);
		} else {
			config_del_path('system/sctpopeningtimeout');
		}
		if (!empty($post['sctpestablishedtimeout'])) {
			config_set_path('system/sctpestablishedtimeout', $post['sctpestablishedtimeout']);
		} else {
			config_del_path('system/sctpestablishedtimeout');
		}
		if (!empty($post['sctpclosingtimeout'])) {
			config_set_path('system/sctpclosingtimeout', $post['sctpclosingtimeout']);
		} else {
			config_del_path('system/sctpclosingtimeout');
		}
		if (!empty($post['sctpclosedtimeout'])) {
			config_set_path('system/sctpclosedtimeout', $post['sctpclosedtimeout']);
		} else {
			config_del_path('system/sctpclosedtimeout');
		}
		if (!empty($post['udpfirsttimeout'])) {
			config_set_path('system/udpfirsttimeout', $post['udpfirsttimeout']);
		} else {
			config_del_path('system/udpfirsttimeout');
		}
		if (!empty($post['udpsingletimeout'])) {
			config_set_path('system/udpsingletimeout', $post['udpsingletimeout']);
		} else {
			config_del_path('system/udpsingletimeout');
		}
		if (!empty($post['udpmultipletimeout'])) {
			config_set_path('system/udpmultipletimeout', $post['udpmultipletimeout']);
		} else {
			config_del_path('system/udpmultipletimeout');
		}
		if (!empty($post['icmpfirsttimeout'])) {
			config_set_path('system/icmpfirsttimeout', $post['icmpfirsttimeout']);
		} else {
			config_del_path('system/icmpfirsttimeout');
		}
		if (!empty($post['icmperrortimeout'])) {
			config_set_path('system/icmperrortimeout', $post['icmperrortimeout']);
		} else {
			config_del_path('system/icmperrortimeout');
		}
		if (!empty($post['otherfirsttimeout'])) {
			config_set_path('system/otherfirsttimeout', $post['otherfirsttimeout']);
		} else {
			config_del_path('system/otherfirsttimeout');
		}
		if (!empty($post['othersingletimeout'])) {
			config_set_path('system/othersingletimeout', $post['othersingletimeout']);
		} else {
			config_del_path('system/othersingletimeout');
		}
		if (!empty($post['othermultipletimeout'])) {
			config_set_path('system/othermultipletimeout', $post['othermultipletimeout']);
		} else {
			config_del_path('system/othermultipletimeout');
		}

		if ($post['natreflection'] == "proxy") {
			config_del_path('system/disablenatreflection');
			config_del_path('system/enablenatreflectionpurenat');
		} else if ($post['natreflection'] == "purenat") {
			config_del_path('system/disablenatreflection');
			config_set_path('system/enablenatreflectionpurenat', "yes");
		} else {
			config_set_path('system/disablenatreflection', "yes");
			config_del_path('system/enablenatreflectionpurenat');
		}

		if ($post['enablebinatreflection'] == "yes") {
			config_set_path('system/enablebinatreflection', "yes");
		} else {
			config_del_path('system/enablebinatreflection');
		}

		if ($post['disablereplyto'] == "yes") {
			config_set_path('system/disablereplyto', $post['disablereplyto']);
		} else {
			config_del_path('system/disablereplyto');
		}

		if ($post['disablenegate'] == "yes") {
			config_set_path('system/disablenegate', $post['disablenegate']);
		} else {
			config_del_path('system/disablenegate');
		}

		if ($post['no_apipa_block'] == "yes") {
			config_set_path('system/no_apipa_block', "enabled");
		} else {
			config_del_path('system/no_apipa_block');
		}

		if ($post['enablenatreflectionhelper'] == "yes") {
			config_set_path('system/enablenatreflectionhelper', "yes");
		} else {
			config_del_path('system/enablenatreflectionhelper');
		}

		config_set_path('system/reflectiontimeout', $post['reflectiontimeout']);

		if ($post['bypassstaticroutes'] == "yes") {
			config_set_path('filter/bypassstaticroutes', $post['bypassstaticroutes']);
		} elseif (config_path_enabled('filter', 'bypassstaticroutes')) {
			config_del_path('filter/bypassstaticroutes');
		}

		if ($post['disablescrub'] == "yes") {
			config_set_path('system/disablescrub', $post['disablescrub']);
		} else {
			config_del_path('system/disablescrub');
		}

		if ($post['tftpinterface']) {
			config_set_path('system/tftpinterface', implode(",", $post['tftpinterface']));
		} else {
			config_del_path('system/tftpinterface');
		}

		if ($post['bogonsinterval'] != config_get_path('system/bogons/interval')) {
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
			config_set_path('system/bogons/interval', $post['bogonsinterval']);
		}

		if ($maxstates_changed) {
			setup_loader_settings();
		}

		write_config(gettext("Changed Advanced Firewall/NAT settings."));

		// Kill filterdns when value changes, filter_configure() will restart it
		if ($restart_filterdns && isvalidpid("{$g['varrun_path']}/filterdns.pid")) {
			killbypid("{$g['varrun_path']}/filterdns.pid");
		}

		/* Update net.pf.request_maxcount when necessary
		 * See https://redmine.pfsense.org/issues/10861 */
		if ($setup_sysctl) {
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

?>
