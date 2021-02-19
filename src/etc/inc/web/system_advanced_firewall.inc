<?php
/*
 * system_advanced_firewall.inc
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

?>
