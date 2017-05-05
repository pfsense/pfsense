<?php
/*
 * status_logs_settings.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-diagnostics-logs-settings
##|*NAME=Status: Logs: Settings
##|*DESCR=Allow access to the 'Status: Logs: Settings' page.
##|*MATCH=status_logs_settings.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

$pconfig['reverse'] = isset($config['syslog']['reverse']);
$pconfig['nentries'] = $config['syslog']['nentries'];
$pconfig['remoteserver'] = $config['syslog']['remoteserver'];
$pconfig['remoteserver2'] = $config['syslog']['remoteserver2'];
$pconfig['remoteserver3'] = $config['syslog']['remoteserver3'];
$pconfig['sourceip'] = $config['syslog']['sourceip'];
$pconfig['ipproto'] = $config['syslog']['ipproto'];
$pconfig['filter'] = isset($config['syslog']['filter']);
$pconfig['dhcp'] = isset($config['syslog']['dhcp']);
$pconfig['portalauth'] = isset($config['syslog']['portalauth']);
$pconfig['vpn'] = isset($config['syslog']['vpn']);
$pconfig['dpinger'] = isset($config['syslog']['dpinger']);
$pconfig['relayd'] = isset($config['syslog']['relayd']);
$pconfig['hostapd'] = isset($config['syslog']['hostapd']);
$pconfig['logall'] = isset($config['syslog']['logall']);
$pconfig['system'] = isset($config['syslog']['system']);
$pconfig['resolver'] = isset($config['syslog']['resolver']);
$pconfig['ppp'] = isset($config['syslog']['ppp']);
$pconfig['routing'] = isset($config['syslog']['routing']);
$pconfig['ntpd'] = isset($config['syslog']['ntpd']);
$pconfig['enable'] = isset($config['syslog']['enable']);
$pconfig['logdefaultblock'] = !isset($config['syslog']['nologdefaultblock']);
$pconfig['logdefaultpass'] = isset($config['syslog']['nologdefaultpass']);
$pconfig['logbogons'] = !isset($config['syslog']['nologbogons']);
$pconfig['logprivatenets'] = !isset($config['syslog']['nologprivatenets']);
$pconfig['lognginx'] = !isset($config['syslog']['nolognginx']);
$pconfig['rawfilter'] = isset($config['syslog']['rawfilter']);
$pconfig['filterdescriptions'] = $config['syslog']['filterdescriptions'];
$pconfig['disablelocallogging'] = isset($config['syslog']['disablelocallogging']);
$pconfig['logfilesize'] = $config['syslog']['logfilesize'];
$pconfig['igmpxverbose'] = isset($config['syslog']['igmpxverbose']);

if (!$pconfig['nentries']) {
	$pconfig['nentries'] = 50;
}

function is_valid_syslog_server($target) {
	return (is_ipaddr($target)
		|| is_ipaddrwithport($target)
		|| is_hostname($target)
		|| is_hostnamewithport($target));
}

if ($_POST['resetlogs'] == gettext("Reset Log Files")) {
	clear_all_log_files(true);
	$reset_msg = gettext("The log files have been reset.");
} elseif ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['enable'] && !is_valid_syslog_server($_POST['remoteserver'])) {
		$input_errors[] = gettext("A valid IP address/hostname or IP/hostname:port must be specified for remote syslog server #1.");
	}
	if ($_POST['enable'] && $_POST['remoteserver2'] && !is_valid_syslog_server($_POST['remoteserver2'])) {
		$input_errors[] = gettext("A valid IP address/hostname or IP/hostname:port must be specified for remote syslog server #2.");
	}
	if ($_POST['enable'] && $_POST['remoteserver3'] && !is_valid_syslog_server($_POST['remoteserver3'])) {
		$input_errors[] = gettext("A valid IP address/hostname or IP/hostname:port must be specified for remote syslog server #3.");
	}

	if (($_POST['nentries'] < 5) || ($_POST['nentries'] > 2000)) {
		$input_errors[] = gettext("Number of log entries to show must be between 5 and 2000.");
	}

	if (isset($_POST['logfilesize']) && (strlen($_POST['logfilesize']) > 0)) {
		if (!is_numeric($_POST['logfilesize']) || ($_POST['logfilesize'] < 100000)) {
			$input_errors[] = gettext("Log file size must be numeric and greater than or equal to 100000.");
		}
	}
	if (!$input_errors) {
		$config['syslog']['reverse'] = $_POST['reverse'] ? true : false;
		$config['syslog']['nentries'] = (int)$_POST['nentries'];
		$pconfig['nentries'] = $config['syslog']['nentries'];
		if (isset($_POST['logfilesize']) && (strlen($_POST['logfilesize']) > 0)) {
			$config['syslog']['logfilesize'] = (int)$_POST['logfilesize'];
			$pconfig['logfilesize'] = $config['syslog']['logfilesize'];
		} else {
			unset($config['syslog']['logfilesize']);
		}
		$config['syslog']['remoteserver'] = $_POST['remoteserver'];
		$config['syslog']['remoteserver2'] = $_POST['remoteserver2'];
		$config['syslog']['remoteserver3'] = $_POST['remoteserver3'];
		$config['syslog']['sourceip'] = $_POST['sourceip'];
		$config['syslog']['ipproto'] = $_POST['ipproto'];
		$config['syslog']['filter'] = $_POST['filter'] ? true : false;
		$config['syslog']['dhcp'] = $_POST['dhcp'] ? true : false;
		$config['syslog']['portalauth'] = $_POST['portalauth'] ? true : false;
		$config['syslog']['vpn'] = $_POST['vpn'] ? true : false;
		$config['syslog']['dpinger'] = $_POST['dpinger'] ? true : false;
		$config['syslog']['relayd'] = $_POST['relayd'] ? true : false;
		$config['syslog']['hostapd'] = $_POST['hostapd'] ? true : false;
		$config['syslog']['logall'] = $_POST['logall'] ? true : false;
		$config['syslog']['system'] = $_POST['system'] ? true : false;
		$config['syslog']['resolver'] = $_POST['resolver'] ? true : false;
		$config['syslog']['ppp'] = $_POST['ppp'] ? true : false;
		$config['syslog']['routing'] = $_POST['routing'] ? true : false;
		$config['syslog']['ntpd'] = $_POST['ntpd'] ? true : false;
		$config['syslog']['disablelocallogging'] = $_POST['disablelocallogging'] ? true : false;
		$config['syslog']['enable'] = $_POST['enable'] ? true : false;
		$oldnologdefaultblock = isset($config['syslog']['nologdefaultblock']);
		$oldnologdefaultpass = isset($config['syslog']['nologdefaultpass']);
		$oldnologbogons = isset($config['syslog']['nologbogons']);
		$oldnologprivatenets = isset($config['syslog']['nologprivatenets']);
		$oldnolognginx = isset($config['syslog']['nolognginx']);
		$config['syslog']['nologdefaultblock'] = $_POST['logdefaultblock'] ? false : true;
		$config['syslog']['nologdefaultpass'] = $_POST['logdefaultpass'] ? true : false;
		$config['syslog']['nologbogons'] = $_POST['logbogons'] ? false : true;
		$config['syslog']['nologprivatenets'] = $_POST['logprivatenets'] ? false : true;
		$config['syslog']['nolognginx'] = $_POST['lognginx'] ? false : true;
		$config['syslog']['rawfilter'] = $_POST['rawfilter'] ? true : false;
		$config['syslog']['igmpxverbose'] = $_POST['igmpxverbose'] ? true : false;

		if (is_numeric($_POST['filterdescriptions']) && $_POST['filterdescriptions'] > 0) {
			$config['syslog']['filterdescriptions'] = $_POST['filterdescriptions'];
		} else {
			unset($config['syslog']['filterdescriptions']);
		}

		if ($config['syslog']['enable'] == false) {
			unset($config['syslog']['remoteserver']);
			unset($config['syslog']['remoteserver2']);
			unset($config['syslog']['remoteserver3']);
		}

		write_config(gettext("Changed system logging options."));

		$changes_applied = true;
		$retval = 0;
		system_syslogd_start();
		if (($oldnologdefaultblock !== isset($config['syslog']['nologdefaultblock'])) ||
		    ($oldnologdefaultpass !== isset($config['syslog']['nologdefaultpass'])) ||
		    ($oldnologbogons !== isset($config['syslog']['nologbogons'])) ||
		    ($oldnologprivatenets !== isset($config['syslog']['nologprivatenets']))) {
			$retval |= filter_configure();
		}

		if ($oldnolognginx !== isset($config['syslog']['nolognginx'])) {
			ob_flush();
			flush();
			log_error(gettext("webConfigurator configuration has changed. Restarting webConfigurator."));
			send_event("service restart webgui");
			$extra_save_msg = gettext("WebGUI process is restarting.");
		}

		filter_pflog_start(true);
	}
}

$pgtitle = array(gettext("Status"), gettext("System Logs"), gettext("Settings"));
$pglinks = array("", "status_logs.php", "@self");
include("head.inc");

$logfilesizeHelp =	gettext("Logs are held in constant-size circular log files. This field controls how large each log file is, and thus how many entries may exist inside the log. By default this is approximately 500KB per log file, and there are nearly 20 such log files.") .
					'<br /><br />' .
					gettext("NOTE: Log sizes are changed the next time a log file is cleared or deleted. To immediately increase the size of the log files, first save the options to set the size, then clear all logs using the \"Reset Log Files\" option farther down this page. ") .
					gettext("Be aware that increasing this value increases every log file size, so disk usage will increase significantly.") . '<br /><br />' .
					gettext("Disk space currently used by log files is: ") . exec("/usr/bin/du -sh /var/log | /usr/bin/awk '{print $1;}'") .
					gettext(" Remaining disk space for log files: ") . exec("/bin/df -h /var/log | /usr/bin/awk '{print $4;}'");

$remoteloghelp =	gettext("This option will allow the logging daemon to bind to a single IP address, rather than all IP addresses.") . " " .
					gettext("If a single IP is picked, remote syslog servers must all be of that IP type. To mix IPv4 and IPv6 remote syslog servers, bind to all interfaces.") .
					"<br /><br />" .
					gettext("NOTE: If an IP address cannot be located on the chosen interface, the daemon will bind to all addresses.");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($reset_msg) {
	print_info_box($reset_msg, 'success');
}

if ($changes_applied) {
	print_apply_result_box($retval, $extra_save_msg);
}

$tab_array = array();
$tab_array[] = array(gettext("System"), false, "status_logs.php");
$tab_array[] = array(gettext("Firewall"), false, "status_logs_filter.php");
$tab_array[] = array(gettext("DHCP"), false, "status_logs.php?logfile=dhcpd");
$tab_array[] = array(gettext("Captive Portal Auth"), false, "status_logs.php?logfile=portalauth");
$tab_array[] = array(gettext("IPsec"), false, "status_logs.php?logfile=ipsec");
$tab_array[] = array(gettext("PPP"), false, "status_logs.php?logfile=ppp");
$tab_array[] = array(gettext("VPN"), false, "status_logs_vpn.php");
$tab_array[] = array(gettext("Load Balancer"), false, "status_logs.php?logfile=relayd");
$tab_array[] = array(gettext("OpenVPN"), false, "status_logs.php?logfile=openvpn");
$tab_array[] = array(gettext("NTP"), false, "status_logs.php?logfile=ntpd");
$tab_array[] = array(gettext("Settings"), true, "status_logs_settings.php");
display_top_tabs($tab_array);

$form = new Form();

$section = new Form_Section('General Logging Options');

$section->addInput(new Form_Checkbox(
	'reverse',
	'Forward/Reverse Display',
	'Show log entries in reverse order (newest entries on top)',
	$pconfig['reverse']
));

$section->addInput(new Form_Input(
	'nentries',
	'GUI Log Entries',
	'text',
	$pconfig['nentries'],
	['placeholder' => '']
))->setHelp('This is only the number of log entries displayed in the GUI. It does not affect how many entries are contained in the actual log files.');

$section->addInput(new Form_Input(
	'logfilesize',
	'Log file size (Bytes)',
	'text',
	$pconfig['logfilesize'],
	['placeholder' => 'Bytes']
))->setHelp($logfilesizeHelp);

$section->addInput(new Form_Checkbox(
	'logdefaultblock',
	'Log firewall default blocks',
	'Log packets matched from the default block rules in the ruleset',
	$pconfig['logdefaultblock']
))->setHelp('Log packets that are %1$sblocked%2$s by the implicit default block rule. - Per-rule logging options are still respected.', '<strong>', '</strong>');

$section->addInput(new Form_Checkbox(
	'logdefaultpass',
	null,
	'Log packets matched from the default pass rules put in the ruleset',
	$pconfig['logdefaultpass']
))->setHelp('Log packets that are %1$sallowed%2$s by the implicit default pass rule. - Per-rule logging options are still respected. ', '<strong>', '</strong>');

$section->addInput(new Form_Checkbox(
	'logbogons',
	null,
	'Log packets blocked by \'Block Bogon Networks\' rules',
	$pconfig['logbogons']
));

$section->addInput(new Form_Checkbox(
	'logprivatenets',
	null,
	'Log packets blocked by \'Block Private Networks\' rules',
	$pconfig['logprivatenets']
));

$section->addInput(new Form_Checkbox(
	'lognginx',
	'Web Server Log',
	'Log errors from the web server process',
	$pconfig['lognginx']
))->setHelp('If this is checked, errors from the web server process for the GUI or Captive Portal will appear in the main system log.');

$section->addInput(new Form_Checkbox(
	'rawfilter',
	'Raw Logs',
	'Show raw filter logs',
	$pconfig['rawfilter']
))->setHelp('If this is checked, filter logs are shown as generated by the packet filter, without any formatting. This will reveal more detailed information, but it is more difficult to read.');

$section->addINput(new Form_Checkbox(
	'igmpxverbose',
	'IGMP Proxy',
	'Enable verbose logging (Default is terse logging)',
	$pconfig['igmpxverbose']
));

$section->addInput(new Form_Select(
	'filterdescriptions',
	'Where to show rule descriptions',
	!isset($pconfig['filterdescriptions']) ? '0':$pconfig['filterdescriptions'],
	array(
		'0' => gettext('Dont load descriptions'),
		'1' => gettext('Display as column'),
		'2' => gettext('Display as second row')
	)
))->setHelp('Show the applied rule description below or in the firewall log rows.%1$s' .
			'Displaying rule descriptions for all lines in the log might affect performance with large rule sets.',
			'<br />');

$section->addInput(new Form_Checkbox(
	'disablelocallogging',
	'Local Logging',
	"Disable writing log files to the local disk",
	$pconfig['disablelocallogging']
));

$section->addInput(new Form_Button(
	'resetlogs',
	'Reset Log Files',
	null,
	'fa-trash'
))->addClass('btn-danger btn-sm')->setHelp('Clears all local log files and reinitializes them as empty logs. This also restarts the DHCP daemon. Use the Save button first if any setting changes have been made.');

$form->add($section);
$section = new Form_Section('Remote Logging Options');
$section->addClass('toggle-remote');

$section->addInput(new Form_Checkbox(
	'enable',
	'Enable Remote Logging',
	'Send log messages to remote syslog server',
	$pconfig['enable']
));

$section->addInput(new Form_Select(
	'sourceip',
	'Source Address',
	link_interface_to_bridge($pconfig['sourceip']) ? null : $pconfig['sourceip'],
	["" => gettext("Default (any)")] + get_possible_traffic_source_addresses(false)
))->setHelp($remoteloghelp);

$section->addInput(new Form_Select(
	'ipproto',
	'IP Protocol',
	$ipproto,
	array('ipv4' => 'IPv4', 'ipv6' => 'IPv6')
))->setHelp('This option is only used when a non-default address is chosen as the source above. ' .
			'This option only expresses a preference; If an IP address of the selected type is not found on the chosen interface, the other type will be tried.');

// Group collapses/appears based on 'enable' checkbox above
$group = new Form_Group('Remote log servers');
$group->addClass('remotelogging');

$group->add(new Form_Input(
	'remoteserver',
	'Server 1',
	'text',
	$pconfig['remoteserver'],
	['placeholder' => 'IP[:port]']
));

$group->add(new Form_Input(
	'remoteserver2',
	'Server 2',
	'text',
	$pconfig['remoteserver2'],
	['placeholder' => 'IP[:port]']
));

$group->add(new Form_Input(
	'remoteserver3',
	'Server 3',
	'text',
	$pconfig['remoteserver3'],
	['placeholder' => 'IP[:port]']
));

$section->add($group);

$group = new Form_MultiCheckboxGroup('Remote Syslog Contents');
$group->addClass('remotelogging');

$group->add(new Form_MultiCheckbox(
	'logall',
	null,
	'Everything',
	$pconfig['logall']
));

$group->add(new Form_MultiCheckbox(
	'system',
	null,
	'System Events',
	$pconfig['system']
));

$group->add(new Form_MultiCheckbox(
	'filter',
	null,
	'Firewall Events',
	$pconfig['filter']
));

$group->add(new Form_MultiCheckbox(
	'resolver',
	null,
	'DNS Events (Resolver/unbound, Forwarder/dnsmasq, filterdns)',
	$pconfig['resolver']
));

$group->add(new Form_MultiCheckbox(
	'dhcp',
	null,
	'DHCP Events (DHCP Daemon, DHCP Relay, DHCP Client)',
	$pconfig['dhcp']
));

$group->add(new Form_MultiCheckbox(
	'ppp',
	null,
	'PPP Events (PPPoE WAN Client, L2TP WAN Client, PPTP WAN Client)',
	$pconfig['ppp']
));

$group->add(new Form_MultiCheckbox(
	'portalauth',
	null,
	'Captive Portal Events',
	$pconfig['portalauth']
));

$group->add(new Form_MultiCheckbox(
	'vpn',
	null,
	'VPN Events (IPsec, OpenVPN, L2TP, PPPoE Server)',
	$pconfig['vpn']
));

$group->add(new Form_MultiCheckbox(
	'dpinger',
	null,
	'Gateway Monitor Events',
	$pconfig['dpinger']
));

$group->add(new Form_MultiCheckbox(
	'routing',
	null,
	'Routing Daemon Events (RADVD, UPnP, RIP, OSPF, BGP)',
	$pconfig['routing']
));

$group->add(new Form_MultiCheckbox(
	'relayd',
	null,
	'Server Load Balancer Events (relayd)',
	$pconfig['relayd']
));

$group->add(new Form_MultiCheckbox(
	'ntpd',
	null,
	'Network Time Protocol Events (NTP Daemon, NTP Client)',
	$pconfig['ntpd']
));

$group->add(new Form_MultiCheckbox(
	'hostapd',
	null,
	'Wireless Events (hostapd)',
	$pconfig['hostapd']
));

$group->setHelp('Syslog sends UDP datagrams to port 514 on the specified remote '.
	'syslog server, unless another port is specified. Be sure to set syslogd on '.
	'the remote server to accept syslog messages from pfSense.');

$section->add($group);

$form->add($section);

print $form;
?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// We don't want to see the automatically generated "Toggle all" button
	$('[name=btntoggleall]').hide();

	// ---------- Click checkbox handlers ---------------------------------------------------------

	$('#logall').click(function () {
		disableEverything();
	});

	 $('#enable').click(function () {
		hideClass('remotelogging', !this.checked);
		hideSelect('sourceip', !this.checked);
		hideSelect('ipproto', !this.checked);
	});

	function disableEverything() {
		var hide = $('#logall').prop('checked');

		disableInput('system', hide);
		disableInput('filter', hide);
		disableInput('dhcp', hide);
		disableInput('portalauth', hide);
		disableInput('vpn', hide);
		disableInput('dpinger', hide);
		disableInput('relayd', hide);
		disableInput('hostapd', hide);
		disableInput('resolver', hide);
		disableInput('ppp', hide);
		disableInput('routing', hide);
		disableInput('ntpd', hide);
	}

	// ---------- On initial page load ------------------------------------------------------------

	hideClass('remotelogging', !$('#enable').prop('checked'));
	hideSelect('sourceip', !$('#enable').prop('checked'));
	hideSelect('ipproto', !$('#enable').prop('checked'));
});
//]]>
</script>

<?php

include("foot.inc");
