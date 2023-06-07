<?php
/*
 * status_logs_settings.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2023 Rubicon Communications, LLC (Netgate)
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
require_once("status_logs_common.inc");

global $g, $system_log_compression_types, $syslog_formats;

$pconfig['reverse'] = config_path_enabled('syslog', 'reverse');
$pconfig['nentries'] = config_get_path('syslog/nentries');
$pconfig['remoteserver'] = config_get_path('syslog/remoteserver');
$pconfig['remoteserver2'] = config_get_path('syslog/remoteserver2');
$pconfig['remoteserver3'] = config_get_path('syslog/remoteserver3');
$pconfig['sourceip'] = config_get_path('syslog/sourceip');
$pconfig['ipproto'] = config_get_path('syslog/ipproto');
$pconfig['filter'] = config_path_enabled('syslog', 'filter');
$pconfig['dhcp'] = config_path_enabled('syslog', 'dhcp');
$pconfig['auth'] = config_path_enabled('syslog', 'auth');
$pconfig['portalauth'] = config_path_enabled('syslog', 'portalauth');
$pconfig['vpn'] = config_path_enabled('syslog', 'vpn');
$pconfig['dpinger'] = config_path_enabled('syslog', 'dpinger');
$pconfig['hostapd'] = config_path_enabled('syslog', 'hostapd');
$pconfig['logall'] = config_path_enabled('syslog', 'logall');
$pconfig['system'] = config_path_enabled('syslog', 'system');
$pconfig['resolver'] = config_path_enabled('syslog', 'resolver');
$pconfig['ppp'] = config_path_enabled('syslog', 'ppp');
$pconfig['routing'] = config_path_enabled('syslog', 'routing');
$pconfig['ntpd'] = config_path_enabled('syslog', 'ntpd');
$pconfig['enable'] = config_path_enabled('syslog', 'enable');
$pconfig['logdefaultblock'] = !config_path_enabled('syslog', 'nologdefaultblock');
$pconfig['logdefaultpass'] = config_path_enabled('syslog', 'nologdefaultpass');
$pconfig['logbogons'] = !config_path_enabled('syslog', 'nologbogons');
$pconfig['logprivatenets'] = !config_path_enabled('syslog', 'nologprivatenets');
$pconfig['lognginx'] = !config_path_enabled('syslog', 'nolognginx');
$pconfig['rawfilter'] = config_path_enabled('syslog', 'rawfilter');
$pconfig['filterdescriptions'] = config_get_path('syslog/filterdescriptions');
$pconfig['disablelocallogging'] = config_path_enabled('syslog', 'disablelocallogging');
$pconfig['logconfigchanges'] = (config_get_path('syslog/logconfigchanges') != "disabled");
$pconfig['logfilesize'] = config_get_path('syslog/logfilesize');
$pconfig['logcompressiontype'] = system_log_get_compression();
$pconfig['rotatecount'] = config_get_path('syslog/rotatecount');
$pconfig['format'] = config_get_path('syslog/format');

if (!$pconfig['nentries']) {
	$pconfig['nentries'] = g_get('default_log_entries');
}

function is_valid_syslog_server($target) {
	$parts = explode(":", $target);
	if (is_numericint($parts[0])) {
		/* OS interprets numeric value as decimal IP address, see https://redmine.pfsense.org/issues/12000 */
		return false;
	}
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

	if (($_POST['nentries'] < 5) || ($_POST['nentries'] > 200000)) {
		$input_errors[] = gettext("Number of log entries to show must be between 5 and 200000.");
	}

	if (isset($_POST['logfilesize']) && (strlen($_POST['logfilesize']) > 0)) {
		if (!is_numeric($_POST['logfilesize']) || ($_POST['logfilesize'] < 100000)) {
			$input_errors[] = gettext("Log file size must be numeric and greater than or equal to 100000.");
		} elseif ($_POST['logfilesize'] >= (2**32)/2) {
			$input_errors[] = gettext("Log file size is too large. Set a smaller value.");
		}
	}
	if (isset($_POST['rotatecount']) && (strlen($_POST['rotatecount']) > 0)) {
		if (!is_numericint($_POST['rotatecount']) ||
		    ($_POST['rotatecount'] < 0) ||
		    ($_POST['rotatecount'] > 99)) {
			$input_errors[] = gettext("Log Retention Count must be an integer from 0 to 99.");
		}
	}

	if (!array_key_exists($_POST['format'], $syslog_formats)) {
		$input_errors[] = gettext("Invalid Log Message Format.");
	}

	if (!array_key_exists($_POST['logcompressiontype'], $system_log_compression_types)) {
		$input_errors[] = gettext("Invalid log compression type.");
	}

	if (!$input_errors) {
		init_config_arr(array('syslog'));
		config_set_path('syslog/reverse', $_POST['reverse'] ? true : false);
		config_set_path('syslog/nentries', (int)$_POST['nentries']);
		$pconfig['nentries'] = config_get_path('syslog/nentries');
		if (isset($_POST['logfilesize']) && (strlen($_POST['logfilesize']) > 0)) {
			config_set_path('syslog/logfilesize', (int)$_POST['logfilesize']);
			$pconfig['logfilesize'] = config_get_path('syslog/logfilesize');
		} else {
			config_del_path('syslog/logfilesize');
		}

		if (isset($_POST['logcompressiontype'])) {
			/* If the non-default compression type changed and the
			 * old type was not 'none', then remove the old log files. */

			if ((!config_path_enabled('syslog', 'logcompressiontype') && ($_POST['logcompressiontype'] != 'bzip2')) ||
			    (config_path_enabled('syslog', 'logcompressiontype') &&
			    (config_get_path('syslog/logcompressiontype') != 'none') &&
			    (config_get_path('syslog/logcompressiontype') != $_POST['logcompressiontype']))) {
				/* Clear old rotated log files */
				foreach (system_syslogd_get_all_logfilenames() as $lfile) {
					unlink_if_exists("{$g['varlog_path']}/{$lfile}.log.*");
				}
			}
			config_set_path('syslog/logcompressiontype', $_POST['logcompressiontype']);
		}

		config_set_path('syslog/format', $_POST['format']);
		config_set_path('syslog/rotatecount', $_POST['rotatecount']);
		config_set_path('syslog/remoteserver', $_POST['remoteserver']);
		config_set_path('syslog/remoteserver2', $_POST['remoteserver2']);
		config_set_path('syslog/remoteserver3', $_POST['remoteserver3']);
		config_set_path('syslog/sourceip', $_POST['sourceip']);
		config_set_path('syslog/ipproto', $_POST['ipproto']);
		config_set_path('syslog/filter', $_POST['filter'] ? true : false);
		config_set_path('syslog/dhcp', $_POST['dhcp'] ? true : false);
		config_set_path('syslog/auth', $_POST['auth'] ? true : false);
		config_set_path('syslog/portalauth', $_POST['portalauth'] ? true : false);
		config_set_path('syslog/vpn', $_POST['vpn'] ? true : false);
		config_set_path('syslog/dpinger', $_POST['dpinger'] ? true : false);
		config_set_path('syslog/hostapd', $_POST['hostapd'] ? true : false);
		config_set_path('syslog/logall', $_POST['logall'] ? true : false);
		config_set_path('syslog/system', $_POST['system'] ? true : false);
		config_set_path('syslog/resolver', $_POST['resolver'] ? true : false);
		config_set_path('syslog/ppp', $_POST['ppp'] ? true : false);
		config_set_path('syslog/routing', $_POST['routing'] ? true : false);
		config_set_path('syslog/ntpd', $_POST['ntpd'] ? true : false);
		config_set_path('syslog/disablelocallogging', $_POST['disablelocallogging'] ? true : false);
		config_set_path('syslog/logconfigchanges', $_POST['logconfigchanges'] ? "enabled" : "disabled");
		config_set_path('syslog/enable', $_POST['enable'] ? true : false);
		$oldnologdefaultblock = config_path_enabled('syslog', 'nologdefaultblock');
		$oldnologdefaultpass = config_path_enabled('syslog', 'nologdefaultpass');
		$oldnologbogons = config_path_enabled('syslog', 'nologbogons');
		$oldnologprivatenets = config_path_enabled('syslog', 'nologprivatenets');
		$oldnolognginx = config_path_enabled('syslog', 'nolognginx');
		config_set_path('syslog/nologdefaultblock', $_POST['logdefaultblock'] ? false : true);
		config_set_path('syslog/nologdefaultpass', $_POST['logdefaultpass'] ? true : false);
		config_set_path('syslog/nologbogons', $_POST['logbogons'] ? false : true);
		config_set_path('syslog/nologprivatenets', $_POST['logprivatenets'] ? false : true);
		config_set_path('syslog/nolognginx', $_POST['lognginx'] ? false : true);
		config_set_path('syslog/rawfilter', $_POST['rawfilter'] ? true : false);

		if (is_numeric($_POST['filterdescriptions']) && $_POST['filterdescriptions'] > 0) {
			config_set_path('syslog/filterdescriptions', $_POST['filterdescriptions']);
		} else {
			config_del_path('syslog/filterdescriptions');
		}

		if (!config_path_enabled('syslog')) {
			config_del_path('syslog/remoteserver');
			config_del_path('syslog/remoteserver2');
			config_del_path('syslog/remoteserver3');
		}

		write_config(gettext("Changed system logging options."));

		$changes_applied = true;
		$retval = 0;
		system_syslogd_start();
		if (($oldnologdefaultblock !== config_path_enabled('syslog', 'nologdefaultblock')) ||
		    ($oldnologdefaultpass !== config_path_enabled('syslog', 'nologdefaultpass')) ||
		    ($oldnologbogons !== config_path_enabled('syslog', 'nologbogons')) ||
		    ($oldnologprivatenets !== config_path_enabled('syslog', 'nologprivatenets'))) {
			$retval |= filter_configure();
		}

		if ($oldnolognginx !== config_path_enabled('syslog', 'nolognginx')) {
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

$current_log_size = config_get_path('syslog/logfilesize', g_get('default_log_size'));
$current_rotate_count = is_numericint($syslogcfg['rotatecount']) ? $syslogcfg['rotatecount'] : 7;

$logfilesizeHelp =	sprintf(gettext("This field controls the size at which logs will be rotated. By default this is %s per log file, and there are nearly 20 such log files. " .
					"Rotated log files consume additional disk space, which varies depending on compression and retention count."), format_bytes(g_get('default_log_size'))) .
					'<br /><br />' .
					gettext("NOTE: Increasing this value allows every log file to grow to the specified size, so disk usage may increase significantly.") . '<br />' .
					gettext("Logs from packages may consume additional space which is not accounted for in these settings. Check package-specific settings.") . ' ' .
					gettext("Log file sizes are checked once per minute to determine if rotation is necessary, so a very rapidly growing log file may exceed this value.") . ' ' .
					'<br /><br />' .
					gettext("Disk space currently used by log files:") . ' ' . exec("/usr/bin/du -sh /var/log | /usr/bin/awk '{print $1;}'") .
					'<br />' .
					gettext("Worst case disk usage for base system logs based on current global settings:") . ' ' . format_bytes(count($system_log_files) * $current_log_size * $current_rotate_count) .
					'<br />' .
					gettext("Remaining disk space for log files:") . ' ' . exec("/bin/df -h /var/log | /usr/bin/awk '{print $4;}'");

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

tab_array_logs_common();

$form = new Form();

$section = new Form_Section('General Logging Options');

$section->addInput(new Form_Select(
	'format',
	'Log Message Format',
	!isset($pconfig['format']) ? 'rfc3164' : $pconfig['format'],
	$syslog_formats
))->setHelp('The format of syslog messages written to disk locally and sent to ' .
	'remote syslog servers (if enabled).%s' .
	'Changing this value will only affect new log messages.', '<br />');

$section->addInput(new Form_Checkbox(
	'reverse',
	'Forward/Reverse Display',
	'Show log entries in reverse order (newest entries on top)',
	$pconfig['reverse']
));

$section->addInput(new Form_Input(
	'nentries',
	'GUI Log Entries',
	'number',
	$pconfig['nentries'],
	['min' => 5, 'max' => 200000, 'placeholder' => config_get_path('syslog/nentries', g_get('default_log_entries'))]
))->setHelp('This is only the number of log entries displayed in the GUI. It does not affect how many entries are contained in the actual log files.');

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
))->setHelp('WARNING: This will also disable Login Protection!');

$section->addInput(new Form_Checkbox(
	'logconfigchanges',
	'Log Configuration Changes',
	"Generate log entries when making changes to the configuration.",
	$pconfig['logconfigchanges']
));

$section->addInput(new Form_Button(
	'resetlogs',
	'Reset Log Files',
	null,
	'fa-trash'
))->addClass('btn-danger btn-sm')->setHelp('Clears all local log files and reinitializes them as empty logs. This also restarts the DHCP daemon. Use the Save button first if any setting changes have been made.');

$form->add($section);
$section = new Form_Section('Log Rotation Options');

$section->addInput(new Form_Input(
	'logfilesize',
	'Log Rotation Size (Bytes)',
	'number',
	$pconfig['logfilesize'],
	['min' => 100000, 'placeholder' => config_get_path('syslog/logfilesize', g_get('default_log_size'))]
))->setHelp($logfilesizeHelp);

$section->addInput(new Form_Select(
	'logcompressiontype',
	'Log Compression',
	$pconfig['logcompressiontype'],
	array_combine(array_keys($system_log_compression_types), array_keys($system_log_compression_types))
))->setHelp('The type of compression to use when rotating log files. ' .
	'Compressing rotated log files saves disk space, but can incur a performance penalty. ' .
	'Compressed logs remain available for display and searching in the GUI.%1$s%1$s' .
	'Compression should be disabled when using large log files and/or slower hardware.%1$s' .
	'Disabled by default on new ZFS installations as ZFS already performs compression.%1$s' .
	' WARNING: Changing this value will remove previously rotated compressed log files!', '<br />');

$section->addInput(new Form_Input(
	'rotatecount',
	'Log Retention Count',
	'number',
	$pconfig['rotatecount'],
	['min' => 0, 'max' => 99, 'placeholder' => '7']
))->setHelp('The number of log files to keep before the oldest copy is removed on rotation.');

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
	$pconfig['ipproto'],
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
	'auth',
	null,
	'General Authentication Events',
	$pconfig['auth']
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
	'the remote server to accept syslog messages from %s.', g_get('product_label'));

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
		disableInput('auth', hide);
		disableInput('portalauth', hide);
		disableInput('vpn', hide);
		disableInput('dpinger', hide);
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
