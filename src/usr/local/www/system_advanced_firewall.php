<?php
/*
 * system_advanced_firewall.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008 Shrew Soft Inc
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

##|+PRIV
##|*IDENT=page-system-advanced-firewall
##|*NAME=System: Advanced: Firewall & NAT
##|*DESCR=Allow access to the 'System: Advanced: Firewall & NAT' page.
##|*MATCH=system_advanced_firewall.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("pfsense-utils.inc");

$pconfig['disablefilter'] = $config['system']['disablefilter'];
$pconfig['scrubnodf'] = $config['system']['scrubnodf'];
$pconfig['scrubrnid'] = $config['system']['scrubrnid'];
$pconfig['optimization'] = $config['filter']['optimization'];
$pconfig['adaptivestart'] = $config['system']['adaptivestart'];
$pconfig['adaptiveend'] = $config['system']['adaptiveend'];
$pconfig['maximumstates'] = $config['system']['maximumstates'];
$pconfig['aliasesresolveinterval'] = $config['system']['aliasesresolveinterval'];
$old_aliasesresolveinterval = $config['system']['aliasesresolveinterval'];
$pconfig['checkaliasesurlcert'] = isset($config['system']['checkaliasesurlcert']);
$pconfig['maximumtableentries'] = $config['system']['maximumtableentries'];
$old_maximumtableentries = $config['system']['maximumtableentries'];
$pconfig['maximumfrags'] = $config['system']['maximumfrags'];
$pconfig['disablereplyto'] = isset($config['system']['disablereplyto']);
$pconfig['disablenegate'] = isset($config['system']['disablenegate']);
$pconfig['no_apipa_block'] = isset($config['system']['no_apipa_block']);
$pconfig['bogonsinterval'] = $config['system']['bogons']['interval'];
$pconfig['disablenatreflection'] = $config['system']['disablenatreflection'];
$pconfig['enablebinatreflection'] = $config['system']['enablebinatreflection'];
$pconfig['reflectiontimeout'] = $config['system']['reflectiontimeout'];
$pconfig['bypassstaticroutes'] = isset($config['filter']['bypassstaticroutes']);
$pconfig['disablescrub'] = isset($config['system']['disablescrub']);
$pconfig['tftpinterface'] = explode(",", $config['system']['tftpinterface']);
$pconfig['disablevpnrules'] = isset($config['system']['disablevpnrules']);
$pconfig['tcpfirsttimeout'] = $config['system']['tcpfirsttimeout'];
$pconfig['tcpopeningtimeout'] = $config['system']['tcpopeningtimeout'];
$pconfig['tcpestablishedtimeout'] = $config['system']['tcpestablishedtimeout'];
$pconfig['tcpclosingtimeout'] = $config['system']['tcpclosingtimeout'];
$pconfig['tcpfinwaittimeout'] = $config['system']['tcpfinwaittimeout'];
$pconfig['tcpclosedtimeout'] = $config['system']['tcpclosedtimeout'];
$pconfig['tcptsdifftimeout'] = $config['system']['tcptsdifftimeout'];
$pconfig['udpfirsttimeout'] = $config['system']['udpfirsttimeout'];
$pconfig['udpsingletimeout'] = $config['system']['udpsingletimeout'];
$pconfig['udpmultipletimeout'] = $config['system']['udpmultipletimeout'];
$pconfig['icmpfirsttimeout'] = $config['system']['icmpfirsttimeout'];
$pconfig['icmperrortimeout'] = $config['system']['icmperrortimeout'];
$pconfig['otherfirsttimeout'] = $config['system']['otherfirsttimeout'];
$pconfig['othersingletimeout'] = $config['system']['othersingletimeout'];
$pconfig['othermultipletimeout'] = $config['system']['othermultipletimeout'];

$show_reboot_msg = false;
$reboot_msg = gettext('The \"Firewall Maximum Table Entries\" setting has ' .
    'been changed to a value bigger than system can support without a ' .
    'reboot.\n\nReboot now ?');

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ((isset($_POST['adaptivestart']) && !isset($_POST['adaptiveend'])) || (!isset($_POST['adaptivestart']) && isset($_POST['adaptiveend']))) {
		$input_errors[] = gettext("The Firewall Adaptive values must be set together.");
	}
	if (isset($_POST['adaptivestart']) && (strlen($_POST['adaptivestart']) > 0) && !is_numericint($_POST['adaptivestart'])) {
		$input_errors[] = gettext("The Firewall Adaptive Start value must be an integer.");
	}
	if (isset($_POST['adaptive-end']) && (strlen($_POST['adaptive-end']) > 0) && !is_numericint($_POST['adaptive-end'])) {
		$input_errors[] = gettext("The Firewall Adaptive End value must be an integer.");
	}
	if ($_POST['firewall-maximum-states'] && !is_numericint($_POST['firewall-maximum-states'])) {
		$input_errors[] = gettext("The Firewall Maximum States value must be an integer.");
	}
	if ($_POST['aliases-hostnames-resolve-interval'] && !is_numericint($_POST['aliases-hostnames-resolve-interval'])) {
		$input_errors[] = gettext("The Aliases Hostname Resolve Interval value must be an integer.");
	}
	if ($_POST['firewall-maximum-table-entries'] && !is_numericint($_POST['firewall-maximum-table-entries'])) {
		$input_errors[] = gettext("The Firewall Maximum Table Entries value must be an integer.");
	}
	if ($_POST['maximumfrags'] && !is_numericint($_POST['maximumfrags'])) {
		$input_errors[] = gettext("The Firewall Maximum Fragment Entries value must be an integer.");
	}
	if ($_POST['tcpidletimeout'] && !is_numericint($_POST['tcpidletimeout'])) {
		$input_errors[] = gettext("The TCP idle timeout must be an integer.");
	}
	if ($_POST['reflectiontimeout'] && !is_numericint($_POST['reflectiontimeout'])) {
		$input_errors[] = gettext("The Reflection timeout must be an integer.");
	}
	if ($_POST['tcpfirsttimeout'] && !is_numericint($_POST['tcpfirsttimeout'])) {
		$input_errors[] = gettext("The TCP first timeout value must be an integer.");
	}
	if ($_POST['tcpopeningtimeout'] && !is_numericint($_POST['tcpopeningtimeout'])) {
		$input_errors[] = gettext("The TCP opening timeout value must be an integer.");
	}
	if ($_POST['tcpestablishedtimeout'] && !is_numericint($_POST['tcpestablishedtimeout'])) {
		$input_errors[] = gettext("The TCP established timeout value must be an integer.");
	}
	if ($_POST['tcpclosingtimeout'] && !is_numericint($_POST['tcpclosingtimeout'])) {
		$input_errors[] = gettext("The TCP closing timeout value must be an integer.");
	}
	if ($_POST['tcpfinwaittimeout'] && !is_numericint($_POST['tcpfinwaittimeout'])) {
		$input_errors[] = gettext("The TCP FIN wait timeout value must be an integer.");
	}
	if ($_POST['tcpclosedtimeout'] && !is_numericint($_POST['tcpclosedtimeout'])) {
		$input_errors[] = gettext("The TCP closed timeout value must be an integer.");
	}
	if ($_POST['tcptsdifftimeout'] && !is_numericint($_POST['tcptsdifftimeout'])) {
		$input_errors[] = gettext("The TCP tsdiff timeout value must be an integer.");
	}
	if ($_POST['udpfirsttimeout'] && !is_numericint($_POST['udpfirsttimeout'])) {
		$input_errors[] = gettext("The UDP first timeout value must be an integer.");
	}
	if ($_POST['udpsingletimeout'] && !is_numericint($_POST['udpsingletimeout'])) {
		$input_errors[] = gettext("The UDP single timeout value must be an integer.");
	}
	if ($_POST['udpmultipletimeout'] && !is_numericint($_POST['udpmultipletimeout'])) {
		$input_errors[] = gettext("The UDP multiple timeout value must be an integer.");
	}
	if ($_POST['icmpfirsttimeout'] && !is_numericint($_POST['icmpfirsttimeout'])) {
		$input_errors[] = gettext("The ICMP first timeout value must be an integer.");
	}
	if ($_POST['icmperrortimeout'] && !is_numericint($_POST['icmperrortimeout'])) {
		$input_errors[] = gettext("The ICMP error timeout value must be an integer.");
	}
	if ($_POST['otherfirsttimeout'] && !is_numericint($_POST['otherfirsttimeout'])) {
		$input_errors[] = gettext("The Other first timeout value must be an integer.");
	}
	if ($_POST['othersingletimeout'] && !is_numericint($_POST['othersingletimeout'])) {
		$input_errors[] = gettext("The Other single timeout value must be an integer.");
	}
	if ($_POST['othermultipletimeout'] && !is_numericint($_POST['othermultipletimeout'])) {
		$input_errors[] = gettext("The Other multiple timeout value must be an integer.");
	}

	if ($_POST['maximumtableentries']) {
		$maximumtableentries = $_POST['maximumtableentries'];
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

	ob_flush();
	flush();

	if (!$input_errors) {

		if ($_POST['disablefilter'] == "yes") {
			$config['system']['disablefilter'] = "enabled";
		} else {
			unset($config['system']['disablefilter']);
		}

		if ($_POST['disablevpnrules'] == "yes") {
			$config['system']['disablevpnrules'] = true;
		} else {
			unset($config['system']['disablevpnrules']);
		}
		if ($_POST['rfc959workaround'] == "yes") {
			$config['system']['rfc959workaround'] = "enabled";
		} else {
			unset($config['system']['rfc959workaround']);
		}

		if ($_POST['scrubnodf'] == "yes") {
			$config['system']['scrubnodf'] = "enabled";
		} else {
			unset($config['system']['scrubnodf']);
		}

		if ($_POST['scrubrnid'] == "yes") {
			$config['system']['scrubrnid'] = "enabled";
		} else {
			unset($config['system']['scrubrnid']);
		}

		if (is_numericint($_POST['adaptiveend'])) {
			$config['system']['adaptiveend'] = $_POST['adaptiveend'];
		} else {
			unset($config['system']['adaptiveend']);
		}
		if (is_numericint($_POST['adaptivestart'])) {
			$config['system']['adaptivestart'] = $_POST['adaptivestart'];
		} else {
			unset($config['system']['adaptivestart']);
		}

		if ($_POST['checkaliasesurlcert'] == "yes") {
			$config['system']['checkaliasesurlcert'] = true;
		} else {
			unset($config['system']['checkaliasesurlcert']);
		}

		$config['system']['optimization'] = $_POST['optimization'];
		$config['system']['maximumstates'] = $_POST['maximumstates'];
		$config['system']['aliasesresolveinterval'] = $_POST['aliasesresolveinterval'];
		$config['system']['maximumtableentries'] = $_POST['maximumtableentries'];
		$config['system']['maximumfrags'] = $_POST['maximumfrags'];

		if (!empty($_POST['tcpfirsttimeout'])) {
			$config['system']['tcpfirsttimeout'] = $_POST['tcpfirsttimeout'];
		} else {
			unset($config['system']['tcpfirsttimeout']);
		}
		if (!empty($_POST['tcpopeningtimeout'])) {
			$config['system']['tcpopeningtimeout'] = $_POST['tcpopeningtimeout'];
		} else {
			unset($config['system']['tcpopeningtimeout']);
		}
		if (!empty($_POST['tcpestablishedtimeout'])) {
			$config['system']['tcpestablishedtimeout'] = $_POST['tcpestablishedtimeout'];
		} else {
			unset($config['system']['tcpestablishedtimeout']);
		}
		if (!empty($_POST['tcpclosingtimeout'])) {
			$config['system']['tcpclosingtimeout'] = $_POST['tcpclosingtimeout'];
		} else {
			unset($config['system']['tcpclosingtimeout']);
		}
		if (!empty($_POST['tcpfinwaittimeout'])) {
			$config['system']['tcpfinwaittimeout'] = $_POST['tcpfinwaittimeout'];
		} else {
			unset($config['system']['tcpfinwaittimeout']);
		}
		if (!empty($_POST['tcpclosedtimeout'])) {
			$config['system']['tcpclosedtimeout'] = $_POST['tcpclosedtimeout'];
		} else {
			unset($config['system']['tcpclosedtimeout']);
		}
		if (!empty($_POST['tcptsdifftimeout'])) {
			$config['system']['tcptsdifftimeout'] = $_POST['tcptsdifftimeout'];
		} else {
			unset($config['system']['tcptsdifftimeout']);
		}
		if (!empty($_POST['udpfirsttimeout'])) {
			$config['system']['udpfirsttimeout'] = $_POST['udpfirsttimeout'];
		} else {
			unset($config['system']['udpfirsttimeout']);
		}
		if (!empty($_POST['udpsingletimeout'])) {
			$config['system']['udpsingletimeout'] = $_POST['udpsingletimeout'];
		} else {
			unset($config['system']['udpsingletimeout']);
		}
		if (!empty($_POST['udpmultipletimeout'])) {
			$config['system']['udpmultipletimeout'] = $_POST['udpmultipletimeout'];
		} else {
			unset($config['system']['udpmultipletimeout']);
		}
		if (!empty($_POST['icmpfirsttimeout'])) {
			$config['system']['icmpfirsttimeout'] = $_POST['icmpfirsttimeout'];
		} else {
			unset($config['system']['icmpfirsttimeout']);
		}
		if (!empty($_POST['icmperrortimeout'])) {
			$config['system']['icmperrortimeout'] = $_POST['icmperrortimeout'];
		} else {
			unset($config['system']['icmperrortimeout']);
		}
		if (!empty($_POST['otherfirsttimeout'])) {
			$config['system']['otherfirsttimeout'] = $_POST['otherfirsttimeout'];
		} else {
			unset($config['system']['otherfirsttimeout']);
		}
		if (!empty($_POST['othersingletimeout'])) {
			$config['system']['othersingletimeout'] = $_POST['othersingletimeout'];
		} else {
			unset($config['system']['othersingletimeout']);
		}
		if (!empty($_POST['othermultipletimeout'])) {
			$config['system']['othermultipletimeout'] = $_POST['othermultipletimeout'];
		} else {
			unset($config['system']['othermultipletimeout']);
		}

		if ($_POST['natreflection'] == "proxy") {
			unset($config['system']['disablenatreflection']);
			unset($config['system']['enablenatreflectionpurenat']);
		} else if ($_POST['natreflection'] == "purenat") {
			unset($config['system']['disablenatreflection']);
			$config['system']['enablenatreflectionpurenat'] = "yes";
		} else {
			$config['system']['disablenatreflection'] = "yes";
			unset($config['system']['enablenatreflectionpurenat']);
		}

		if ($_POST['enablebinatreflection'] == "yes") {
			$config['system']['enablebinatreflection'] = "yes";
		} else {
			unset($config['system']['enablebinatreflection']);
		}

		if ($_POST['disablereplyto'] == "yes") {
			$config['system']['disablereplyto'] = $_POST['disablereplyto'];
		} else {
			unset($config['system']['disablereplyto']);
		}

		if ($_POST['disablenegate'] == "yes") {
			$config['system']['disablenegate'] = $_POST['disablenegate'];
		} else {
			unset($config['system']['disablenegate']);
		}

		if ($_POST['no_apipa_block'] == "yes") {
			$config['system']['no_apipa_block'] = "enabled";
		} else {
			unset($config['system']['no_apipa_block']);
		}

		if ($_POST['enablenatreflectionhelper'] == "yes") {
			$config['system']['enablenatreflectionhelper'] = "yes";
		} else {
			unset($config['system']['enablenatreflectionhelper']);
		}

		$config['system']['reflectiontimeout'] = $_POST['reflection-timeout'];

		if ($_POST['bypassstaticroutes'] == "yes") {
			$config['filter']['bypassstaticroutes'] = $_POST['bypassstaticroutes'];
		} elseif (isset($config['filter']['bypassstaticroutes'])) {
			unset($config['filter']['bypassstaticroutes']);
		}

		if ($_POST['disablescrub'] == "yes") {
			$config['system']['disablescrub'] = $_POST['disablescrub'];
		} else {
			unset($config['system']['disablescrub']);
		}

		if ($_POST['tftpinterface']) {
			$config['system']['tftpinterface'] = implode(",", $_POST['tftpinterface']);
		} else {
			unset($config['system']['tftpinterface']);
		}

		if ($_POST['bogonsinterval'] != $config['system']['bogons']['interval']) {
			switch ($_POST['bogonsinterval']) {
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
			$config['system']['bogons']['interval'] = $_POST['bogonsinterval'];
		}

		write_config(gettext("Changed Advanced Firewall/NAT settings."));

		// Kill filterdns when value changes, filter_configure() will restart it
		if (($old_aliasesresolveinterval != $config['system']['aliasesresolveinterval']) &&
		    isvalidpid("{$g['varrun_path']}/filterdns.pid")) {
			killbypid("{$g['varrun_path']}/filterdns.pid");
		}

		/* Update loader.conf when necessary */
		if ($old_maximumtableentries !=
		    $config['system']['maximumtableentries']) {
			setup_loader_settings();
			$cur_maximumtableentries = get_single_sysctl(
			    'net.pf.request_maxcount');


			if ($config['system']['maximumtableentries'] >
			    $cur_maximumtableentries) {
				$show_reboot_msg = true;
			}
		}

		$changes_applied = true;
		$retval = 0;
		$retval |= filter_configure();
	}
}

$pgtitle = array(gettext("System"), gettext("Advanced"), htmlspecialchars(gettext("Firewall & NAT")));
$pglinks = array("", "system_advanced_admin.php", "@self");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($changes_applied) {
	print_apply_result_box($retval);
}

$tab_array = array();
$tab_array[] = array(gettext("Admin Access"), false, "system_advanced_admin.php");
$tab_array[] = array(htmlspecialchars(gettext("Firewall & NAT")), true, "system_advanced_firewall.php");
$tab_array[] = array(gettext("Networking"), false, "system_advanced_network.php");
$tab_array[] = array(gettext("Miscellaneous"), false, "system_advanced_misc.php");
$tab_array[] = array(gettext("System Tunables"), false, "system_advanced_sysctl.php");
$tab_array[] = array(gettext("Notifications"), false, "system_advanced_notifications.php");
display_top_tabs($tab_array);

?><div id="container"><?php

$form = new Form;
$section = new Form_Section('Firewall Advanced');

$section->addInput(new Form_Checkbox(
	'scrubnodf',
	'IP Do-Not-Fragment compatibility',
	'Clear invalid DF bits instead of dropping the packets',
	isset($config['system']['scrubnodf'])
))->setHelp('This allows for communications with hosts that generate fragmented '.
	'packets with the don\'t fragment (DF) bit set. Linux NFS is known to do this. '.
	'This will cause the filter to not drop such packets but instead clear the don\'t '.
	'fragment bit.');

$section->addInput(new Form_Checkbox(
	'scrubrnid',
	'IP Random id generation',
	'Insert a stronger ID into IP header of packets passing through the filter.',
	isset($config['system']['scrubrnid'])
))->setHelp('Replaces the IP identification field of packets with random values to '.
	'compensate for operating systems that use predictable values. This option only '.
	'applies to packets that are not fragmented after the optional packet '.
	'reassembly.');

$section->addInput($input = new Form_Select(
	'optimization',
	'Firewall Optimization Options',
	$config['system']['optimization'],
	array(
		'normal' => 'Normal',
		'high-latency' => gettext('High-latency'),
		'aggressive' => gettext('Aggressive'),
		'conservative' => gettext('Conservative'),
	)
))->setHelp('Select the type of state table optimization to use');

$section->addInput(new Form_Checkbox(
	'disablefilter',
	'Disable Firewall',
	'Disable all packet filtering.',
	isset($config['system']['disablefilter'])
))->setHelp('Note: This converts %1$s into a routing only platform!%2$s'.
	'Note: This will also turn off NAT! To only disable NAT, '.
	'and not firewall rules, visit the %3$sOutbound NAT%4$s page.', $g["product_name"], '<br/>', '<a href="firewall_nat_out.php">', '</a>');

$section->addInput(new Form_Checkbox(
	'disablescrub',
	'Disable Firewall Scrub',
	'Disables the PF scrubbing option which can sometimes interfere with NFS traffic.',
	isset($config['system']['disablescrub'])
));

$group = new Form_Group('Firewall Adaptive Timeouts');

$group->add(new Form_Input(
	'adaptivestart',
	'Adaptive start',
	'number',
	$pconfig['adaptivestart'],
	['min' => 0]
))->setHelp('When the number of state entries exceeds this value, adaptive '.
	'scaling begins.  All timeout values are scaled linearly with factor '.
	'(adaptive.end - number of states) / (adaptive.end - adaptive.start). '.
	'Defaults to 60% of the Firewall Maximum States value');

$group->add(new Form_Input(
	'adaptiveend',
	'Adaptive end',
	'number',
	$pconfig['adaptiveend'],
	['min' => 0]
))->setHelp('When reaching this number of state entries, all timeout values '.
	'become zero, effectively purging all state entries immediately.  This '.
	'value is used to define the scale factor, it should not actually be '.
	'reached (set a lower state limit, see below). '.
	'Defaults to 120% of the Firewall Maximum States value');

$group->setHelp('Timeouts for states can be scaled adaptively as the number of '.
	'state table entries grows. Leave blank to use default values, set to '.
	'0 to disable Adaptive Timeouts.');

$section->add($group);

$section->addInput(new Form_Input(
	'maximumstates',
	'Firewall Maximum States',
	'number',
	$pconfig['maximumstates'],
	['min' => 1, 'placeholder' => pfsense_default_state_size()]
))->setHelp('Maximum number of connections to hold in the firewall state table. %1$s'.
	'Note: Leave this blank for the default. On this system the default '.
	'size is: %2$d', '<br/>', pfsense_default_state_size());

$section->addInput(new Form_Input(
	'maximumtableentries',
	'Firewall Maximum Table Entries',
	'text',
	$pconfig['maximumtableentries'],
	['placeholder' => pfsense_default_table_entries_size()]
))->setHelp('Maximum number of table entries for systems such as aliases, '.
	'sshguard, snort, etc, combined.%1$sNote: Leave this blank for the '.
	'default. On this system the default size is: %2$d',
	'<br/>',
	pfsense_default_table_entries_size());

$section->addInput(new Form_Input(
	'maximumfrags',
	'Firewall Maximum Fragment Entries',
	'text',
	$pconfig['maximumfrags'],
	['placeholder' => 5000]
))->setHelp('Maximum number of packet fragments to hold for reassembly by scrub rules. Leave this blank for the default (5000)');

$section->addInput(new Form_Checkbox(
	'bypassstaticroutes',
	'Static route filtering',
	'Bypass firewall rules for traffic on the same interface',
	$pconfig['bypassstaticroutes']
))->setHelp('This option only applies if one or more static routes have been defined. '.
	'If it is enabled, traffic that enters and leaves through the same '.
	'interface will not be checked by the firewall. This may be desirable in some '.
	'situations where multiple subnets are connected to the same interface.');

$section->addInput(new Form_Checkbox(
	'disablevpnrules',
	'Disable Auto-added VPN rules',
	'Disable all auto-added VPN rules.',
	isset($config['system']['disablevpnrules'])
))->setHelp('Note: This disables automatically added rules for IPsec.');

$section->addInput(new Form_Checkbox(
	'disablereplyto',
	'Disable reply-to',
	'Disable reply-to on WAN rules',
	$pconfig['disablereplyto']
))->setHelp('With Multi-WAN it is generally desired to ensure traffic leaves the same '.
	'interface it arrives on, hence reply-to is added automatically by default. When '.
	'using bridging, this behavior must be disabled if the WAN gateway IP is '.
	'different from the gateway IP of the hosts behind the bridged interface.');

$section->addInput(new Form_Checkbox(
	'disablenegate',
	'Disable Negate rules',
	'Disable Negate rule on policy routing rules',
	$pconfig['disablenegate']
))->setHelp('With Multi-WAN it is generally desired to ensure traffic reaches directly '.
	'connected networks and VPN networks when using policy routing. This can be disabled '.
	'for special purposes but it requires manually creating rules for these networks.');

$section->addInput(new Form_Checkbox(
	'no_apipa_block',
	'Allow APIPA',
	'Allow APIPA traffic',
	$pconfig['no_apipa_block']
))->setHelp('Normally this traffic is dropped by the firewall, as APIPA traffic cannot be routed, but some providers may utilize APIPA space for interconnect interfaces.');

$section->addInput(new Form_Input(
	'aliasesresolveinterval',
	'Aliases Hostnames Resolve Interval',
	'text',
	$pconfig['aliasesresolveinterval'],
	['placeholder' => '300']
))->setHelp('Interval, in seconds, that will be used to resolve hostnames '.
	'configured on aliases. %1$sNote:	 Leave this blank for the default '.
	'(300s).', '<br/>');

$section->addInput(new Form_Checkbox(
	'checkaliasesurlcert',
	'Check certificate of aliases URLs',
	'Verify HTTPS certificates when downloading alias URLs',
	$pconfig['checkaliasesurlcert']
))->setHelp('Make sure the certificate is valid for all HTTPS addresses on '.
	'aliases. If it\'s not valid or is revoked, do not download it.');

$form->add($section);
$section = new Form_Section('Bogon Networks');

$section->addInput(new Form_Select(
	'bogonsinterval',
	'Update Frequency',
	empty($pconfig['bogonsinterval']) ? 'monthly' : $pconfig['bogonsinterval'],
	array(
		'monthly' => gettext('Monthly'),
		'weekly' => gettext('Weekly'),
		'daily' => gettext('Daily'),
	)
))->setHelp('The frequency of updating the lists of IP addresses that are '.
	'reserved (but not RFC 1918) or not yet assigned by IANA.');

$form->add($section);

if (count($config['interfaces']) > 1) {
	$section = new Form_Section('Network Address Translation');

	if (isset($config['system']['disablenatreflection'])) {
		$value = 'disable';
	} elseif (!isset($config['system']['enablenatreflectionpurenat'])) {
		$value = 'proxy';
	} else {
		$value = 'purenat';
	}

	$section->addInput(new Form_Select(
		'natreflection',
		'NAT Reflection mode for port forwards',
		$value,
		array(
			'disable' => gettext('disabled'),
			'proxy' => gettext('NAT + proxy'),
			'purenat' => gettext('Pure NAT'),
		)
	))->setHelp('%1$sThe pure NAT mode uses a set of NAT rules to direct '.
		'packets to the target of the port forward. It has better scalability, '.
		'but it must be possible to accurately determine the interface and '.
		'gateway IP used for communication with the target at the time the '.
		'rules are loaded. There are no inherent limits to the number of ports '.
		'other than the limits of the protocols.  All protocols available for '.
		'port forwards are supported.%2$sThe NAT + proxy mode uses a '.
		'helper program to send packets to the target of the port forward. '.
		'It is useful in setups where the interface and/or gateway IP used '.
		'for communication with the target cannot be accurately determined at '.
		'the time the rules are loaded. Reflection rules are not created for '.
		'ranges larger than 500 ports and will not be used for more than 1000 '.
		'ports total between all port forwards. Only TCP and UDP protocols are '.
		'supported.%3$sIndividual rules may be configured to override '.
		'this system setting on a per-rule basis.',
		'</span><ul class="help-block"><li>', '</li><li>', '</li></ul><span class="help-block">');

	$section->addInput(new Form_Input(
		'reflectiontimeout',
		'Reflection Timeout',
		'number',
		$config['system']['reflectiontimeout'],
		['min' => 1]
	))->setHelp('Enter value for Reflection timeout in seconds.%1$sNote: Only '.
		'applies to Reflection on port forwards in NAT + proxy mode.', '<br/>');

	$section->addInput(new Form_Checkbox(
		'enablebinatreflection',
		'Enable NAT Reflection for 1:1 NAT',
		'Automatic creation of additional NAT redirect rules from within the internal networks.',
		isset($config['system']['enablebinatreflection'])
	))->setHelp('Note: Reflection on 1:1 mappings is only for the inbound component of '.
		'the 1:1 mappings. This functions the same as the pure NAT mode for port '.
		'forwards. For more details, refer to the pure NAT mode description '.
		'above. Individual rules may be configured to override this system setting on a '.
		'per-rule basis.');

	$section->addInput(new Form_Checkbox(
		'enablenatreflectionhelper',
		'Enable automatic outbound NAT for Reflection',
		'Automatic create outbound NAT rules that direct traffic back out to the same subnet it originated from.',
		isset($config['system']['enablenatreflectionhelper'])
	))->setHelp('Required for full functionality of the pure NAT mode of NAT '.
		'Reflection for port forwards or NAT Reflection for 1:1 NAT. Note: This only works '.
		'for assigned interfaces.  Other interfaces require manually creating the '.
		'outbound NAT rules that direct the reply packets back through the router.');

	$section->addInput(new Form_Select(
		'tftpinterface',
		'TFTP Proxy',
		$pconfig['tftpinterface'],
		get_configured_interface_with_descr(),
		true
	))->setHelp('Choose the interfaces on which to enable TFTP proxy helper.');

	$form->add($section);
}

$section = new Form_Section('State Timeouts (seconds - blank for default)');

$pftimeouts = get_pf_timeouts();
foreach ($pftimeouts as $proto => $tm) {
	foreach ($tm as $type => $item) {
		$section->addInput(new Form_Input(
			$item['keyname'],
			$item['name'],
			'number',
			$config['system'][$keyname],
			['placeholder' => $item['value']]
		));
	}
}

$form->add($section);

print $form;

?></div>

<div class="infoblock">
	<?php print_info_box(gettext('You need to reload this page after changing the Firewall Optimization Options to see the actual timeout values.'), 'info', false); ?>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// Change help text based on the selector value
	function setOptText(val) {
		var htext = '<span class="text-success">';

		if (val == 'normal') {
			htext += '<?=gettext("The default optimization algorithm");?>';
		} else if (val == 'high-latency') {
			htext += '<?=gettext("Used for eg. satellite links. Expires idle connections later than default");?>';
		} else if (val == 'aggressive') {
			htext += '<?=gettext("Expires idle connections quicker. More efficient use of CPU and memory but can drop legitimate idle connections");?>';
		} else if (val == 'conservative') {
			htext += '<?=gettext("Tries to avoid dropping any legitimate idle connections at the expense of increased memory usage and CPU utilization");?>';
		}

		htext += '</span>';
		setHelpText('optimization', htext);
	}

	// ---------- Click checkbox handlers ---------------------------------------------------------

	$('#optimization').on('change', function() {
		setOptText(this.value);
	});

	// ---------- On initial page load ------------------------------------------------------------

	setOptText($('#optimization').val())

	if (<?=(int)$show_reboot_msg?> && confirm("<?=$reboot_msg?>")) {
		postSubmit({override : 'yes'}, 'diag_reboot.php')
	}
});
//]]>
</script>
<?php
include("foot.inc");
?>
