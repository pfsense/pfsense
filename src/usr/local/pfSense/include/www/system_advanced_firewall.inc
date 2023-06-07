<?php
/*
 * system_advanced_firewall.inc
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


// Functions included by system_advanced_firewall.php =========================
function getSystemAdvancedFirewall($json = false) {
	global $config;

	$pconfig = array();

	$pconfig['adaptiveend'] = config_get_path('system/adaptiveend');
	$pconfig['adaptivestart'] = config_get_path('system/adaptivestart');
	$pconfig['aliasesresolveinterval'] = config_get_path('system/aliasesresolveinterval');
	$pconfig['bogonsinterval'] = config_get_path('system/bogons/interval');
	$pconfig['bypassstaticroutes'] = isset($config['filter']['bypassstaticroutes']);
	$pconfig['checkaliasesurlcert'] = isset($config['system']['checkaliasesurlcert']);
	$pconfig['disablefilter'] = config_get_path('system/disablefilter');
	$pconfig['disablenatreflection'] = config_get_path('system/disablenatreflection');
	$pconfig['disablenegate'] = isset($config['system']['disablenegate']);
	$pconfig['disablereplyto'] = isset($config['system']['disablereplyto']);
	$pconfig['disablescrub'] = isset($config['system']['disablescrub']);
	$pconfig['disablevpnrules'] = isset($config['system']['disablevpnrules']);
	$pconfig['enablebinatreflection'] = config_get_path('system/enablebinatreflection');
	$pconfig['enablenatreflectionhelper'] = config_get_path('system/enablenatreflectionhelper');
	$pconfig['enablenatreflectionpurenat'] = config_get_path('system/enablenatreflectionpurenat');
	$pconfig['icmperrortimeout'] = config_get_path('system/icmperrortimeout');
	$pconfig['icmpfirsttimeout'] = config_get_path('system/icmpfirsttimeout');
	$pconfig['maximumfrags'] = config_get_path('system/maximumfrags');
	$pconfig['maximumstates'] = config_get_path('system/maximumstates');
	$pconfig['maximumtableentries'] = config_get_path('system/maximumtableentries');
	$pconfig['no_apipa_block'] = isset($config['system']['no_apipa_block']);
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
	$pconfig['tftpinterface'] = explode(",", $config['system']['tftpinterface']);
	$pconfig['udpfirsttimeout'] = config_get_path('system/udpfirsttimeout');
	$pconfig['udpmultipletimeout'] = config_get_path('system/udpmultipletimeout');
	$pconfig['udpsingletimeout'] = config_get_path('system/udpsingletimeout');
	$pconfig['vpn_scrubnodf'] = isset($config['system']['vpn_scrubnodf']);
	$pconfig['vpn_fragment_reassemble'] = isset($config['system']['vpn_fragment_reassemble']);
	$pconfig['maxmss_enable'] = isset($config['system']['maxmss_enable']);
	$pconfig['maxmss'] = config_get_path('system/maxmss');

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
			$config['system']['disablefilter'] = "enabled";
		} else {
			config_del_path('system/disablefilter');
		}

		if ($post['disablevpnrules'] == "yes") {
			$config['system']['disablevpnrules'] = true;
		} else {
			config_del_path('system/disablevpnrules');
		}
		if ($post['rfc959workaround'] == "yes") {
			$config['system']['rfc959workaround'] = "enabled";
		} else {
			config_del_path('system/rfc959workaround');
		}

		if ($post['scrubnodf'] == "yes") {
			$config['system']['scrubnodf'] = "enabled";
		} else {
			config_del_path('system/scrubnodf');
		}

		if ($post['scrubrnid'] == "yes") {
			$config['system']['scrubrnid'] = "enabled";
		} else {
			config_del_path('system/scrubrnid');
		}

		if (is_numericint($post['adaptiveend'])) {
			$config['system']['adaptiveend'] = $post['adaptiveend'];
		} else {
			config_del_path('system/adaptiveend');
		}
		if (is_numericint($post['adaptivestart'])) {
			$config['system']['adaptivestart'] = $post['adaptivestart'];
		} else {
			config_del_path('system/adaptivestart');
		}

		if ($post['checkaliasesurlcert'] == "yes") {
			$config['system']['checkaliasesurlcert'] = true;
		} else {
			config_del_path('system/checkaliasesurlcert');
		}

		$config['system']['optimization'] = $post['optimization'];
		$config['system']['maximumstates'] = $post['maximumstates'];
		$config['system']['aliasesresolveinterval'] = $post['aliasesresolveinterval'];
		$config['system']['maximumtableentries'] = $post['maximumtableentries'];
		$config['system']['maximumfrags'] = $post['maximumfrags'];

		if ($post['maxmss_enable'] == "yes") {
			$config['system']['maxmss_enable'] = true;
			$config['system']['maxmss'] = $post['maxmss'];
		} else {
			if (isset($config['system']['maxmss_enable'])) {
				config_del_path('system/maxmss_enable');
			}
			if (isset($config['system']['maxmss'])) {
				config_del_path('system/maxmss');
			}
		}

		if ($post['vpn_scrubnodf'] == "yes") {
			$config['system']['vpn_scrubnodf'] = true;
		} else {
			config_del_path('system/vpn_scrubnodf');
		}

		if ($post['vpn_fragment_reassemble'] == "yes") {
			$config['system']['vpn_fragment_reassemble'] = true;
		} else {
			config_del_path('system/vpn_fragment_reassemble');
		}

		if (!empty($post['tcpfirsttimeout'])) {
			$config['system']['tcpfirsttimeout'] = $post['tcpfirsttimeout'];
		} else {
			config_del_path('system/tcpfirsttimeout');
		}
		if (!empty($post['tcpopeningtimeout'])) {
			$config['system']['tcpopeningtimeout'] = $post['tcpopeningtimeout'];
		} else {
			config_del_path('system/tcpopeningtimeout');
		}
		if (!empty($post['tcpestablishedtimeout'])) {
			$config['system']['tcpestablishedtimeout'] = $post['tcpestablishedtimeout'];
		} else {
			config_del_path('system/tcpestablishedtimeout');
		}
		if (!empty($post['tcpclosingtimeout'])) {
			$config['system']['tcpclosingtimeout'] = $post['tcpclosingtimeout'];
		} else {
			config_del_path('system/tcpclosingtimeout');
		}
		if (!empty($post['tcpfinwaittimeout'])) {
			$config['system']['tcpfinwaittimeout'] = $post['tcpfinwaittimeout'];
		} else {
			config_del_path('system/tcpfinwaittimeout');
		}
		if (!empty($post['tcpclosedtimeout'])) {
			$config['system']['tcpclosedtimeout'] = $post['tcpclosedtimeout'];
		} else {
			config_del_path('system/tcpclosedtimeout');
		}
		if (!empty($post['tcptsdifftimeout'])) {
			$config['system']['tcptsdifftimeout'] = $post['tcptsdifftimeout'];
		} else {
			config_del_path('system/tcptsdifftimeout');
		}
		if (!empty($post['udpfirsttimeout'])) {
			$config['system']['udpfirsttimeout'] = $post['udpfirsttimeout'];
		} else {
			config_del_path('system/udpfirsttimeout');
		}
		if (!empty($post['udpsingletimeout'])) {
			$config['system']['udpsingletimeout'] = $post['udpsingletimeout'];
		} else {
			config_del_path('system/udpsingletimeout');
		}
		if (!empty($post['udpmultipletimeout'])) {
			$config['system']['udpmultipletimeout'] = $post['udpmultipletimeout'];
		} else {
			config_del_path('system/udpmultipletimeout');
		}
		if (!empty($post['icmpfirsttimeout'])) {
			$config['system']['icmpfirsttimeout'] = $post['icmpfirsttimeout'];
		} else {
			config_del_path('system/icmpfirsttimeout');
		}
		if (!empty($post['icmperrortimeout'])) {
			$config['system']['icmperrortimeout'] = $post['icmperrortimeout'];
		} else {
			config_del_path('system/icmperrortimeout');
		}
		if (!empty($post['otherfirsttimeout'])) {
			$config['system']['otherfirsttimeout'] = $post['otherfirsttimeout'];
		} else {
			config_del_path('system/otherfirsttimeout');
		}
		if (!empty($post['othersingletimeout'])) {
			$config['system']['othersingletimeout'] = $post['othersingletimeout'];
		} else {
			config_del_path('system/othersingletimeout');
		}
		if (!empty($post['othermultipletimeout'])) {
			$config['system']['othermultipletimeout'] = $post['othermultipletimeout'];
		} else {
			config_del_path('system/othermultipletimeout');
		}

		if ($post['natreflection'] == "proxy") {
			config_del_path('system/disablenatreflection');
			config_del_path('system/enablenatreflectionpurenat');
		} else if ($post['natreflection'] == "purenat") {
			config_del_path('system/disablenatreflection');
			$config['system']['enablenatreflectionpurenat'] = "yes";
		} else {
			$config['system']['disablenatreflection'] = "yes";
			config_del_path('system/enablenatreflectionpurenat');
		}

		if ($post['enablebinatreflection'] == "yes") {
			$config['system']['enablebinatreflection'] = "yes";
		} else {
			config_del_path('system/enablebinatreflection');
		}

		if ($post['disablereplyto'] == "yes") {
			$config['system']['disablereplyto'] = $post['disablereplyto'];
		} else {
			config_del_path('system/disablereplyto');
		}

		if ($post['disablenegate'] == "yes") {
			$config['system']['disablenegate'] = $post['disablenegate'];
		} else {
			config_del_path('system/disablenegate');
		}

		if ($post['no_apipa_block'] == "yes") {
			$config['system']['no_apipa_block'] = "enabled";
		} else {
			config_del_path('system/no_apipa_block');
		}

		if ($post['enablenatreflectionhelper'] == "yes") {
			$config['system']['enablenatreflectionhelper'] = "yes";
		} else {
			config_del_path('system/enablenatreflectionhelper');
		}

		$config['system']['reflectiontimeout'] = $post['reflectiontimeout'];

		if ($post['bypassstaticroutes'] == "yes") {
			$config['filter']['bypassstaticroutes'] = $post['bypassstaticroutes'];
		} elseif (isset($config['filter']['bypassstaticroutes'])) {
			config_del_path('filter/bypassstaticroutes');
		}

		if ($post['disablescrub'] == "yes") {
			$config['system']['disablescrub'] = $post['disablescrub'];
		} else {
			config_del_path('system/disablescrub');
		}

		if ($post['tftpinterface']) {
			$config['system']['tftpinterface'] = implode(",", $post['tftpinterface']);
		} else {
			config_del_path('system/tftpinterface');
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

?>
