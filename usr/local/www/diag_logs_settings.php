<?php
/* $Id$ */
/*
	diag_logs_settings.php
	Copyright (C) 2004-2009 Scott Ullrich
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/

/*
	pfSense_MODULE: system
*/

##|+PRIV
##|*IDENT=page-diagnostics-logs-settings
##|*NAME=Diagnostics: Logs: Settings page
##|*DESCR=Allow access to the 'Diagnostics: Logs: Settings' page.
##|*MATCH=diag_logs_settings.php*
##|-PRIV

require("guiconfig.inc");
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
$pconfig['apinger'] = isset($config['syslog']['apinger']);
$pconfig['relayd'] = isset($config['syslog']['relayd']);
$pconfig['hostapd'] = isset($config['syslog']['hostapd']);
$pconfig['logall'] = isset($config['syslog']['logall']);
$pconfig['system'] = isset($config['syslog']['system']);
$pconfig['enable'] = isset($config['syslog']['enable']);
$pconfig['logdefaultblock'] = !isset($config['syslog']['nologdefaultblock']);
$pconfig['logdefaultpass'] = isset($config['syslog']['nologdefaultpass']);
$pconfig['logbogons'] = !isset($config['syslog']['nologbogons']);
$pconfig['logprivatenets'] = !isset($config['syslog']['nologprivatenets']);
$pconfig['loglighttpd'] = !isset($config['syslog']['nologlighttpd']);
$pconfig['rawfilter'] = isset($config['syslog']['rawfilter']);
$pconfig['filterdescriptions'] = $config['syslog']['filterdescriptions'];
$pconfig['disablelocallogging'] = isset($config['syslog']['disablelocallogging']);
$pconfig['logfilesize'] = $config['syslog']['logfilesize'];

if (!$pconfig['nentries'])
	$pconfig['nentries'] = 50;

function is_valid_syslog_server($target) {
	return (is_ipaddr($target)
		|| is_ipaddrwithport($target)
		|| is_hostname($target)
		|| is_hostnamewithport($target));
}

if ($_POST['resetlogs'] == gettext("Reset Log Files")) {
	clear_all_log_files();
	$savemsg .= gettext("The log files have been reset.");
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
		$config['syslog']['apinger'] = $_POST['apinger'] ? true : false;
		$config['syslog']['relayd'] = $_POST['relayd'] ? true : false;
		$config['syslog']['hostapd'] = $_POST['hostapd'] ? true : false;
		$config['syslog']['logall'] = $_POST['logall'] ? true : false;
		$config['syslog']['system'] = $_POST['system'] ? true : false;
		$config['syslog']['disablelocallogging'] = $_POST['disablelocallogging'] ? true : false;
		$config['syslog']['enable'] = $_POST['enable'] ? true : false;
		$oldnologdefaultblock = isset($config['syslog']['nologdefaultblock']);
		$oldnologdefaultpass = isset($config['syslog']['nologdefaultpass']);
		$oldnologbogons = isset($config['syslog']['nologbogons']);
		$oldnologprivatenets = isset($config['syslog']['nologprivatenets']);
		$oldnologlighttpd = isset($config['syslog']['nologlighttpd']);
		$config['syslog']['nologdefaultblock'] = $_POST['logdefaultblock'] ? false : true;
		$config['syslog']['nologdefaultpass'] = $_POST['logdefaultpass'] ? true : false;
		$config['syslog']['nologbogons'] = $_POST['logbogons'] ? false : true;
		$config['syslog']['nologprivatenets'] = $_POST['logprivatenets'] ? false : true;
		$config['syslog']['nologlighttpd'] = $_POST['loglighttpd'] ? false : true;
		$config['syslog']['rawfilter'] = $_POST['rawfilter'] ? true : false;
		if (is_numeric($_POST['filterdescriptions']) && $_POST['filterdescriptions'] > 0)
			$config['syslog']['filterdescriptions'] = $_POST['filterdescriptions'];
		else
			unset($config['syslog']['filterdescriptions']);
		if($config['syslog']['enable'] == false) {
			unset($config['syslog']['remoteserver']);
			unset($config['syslog']['remoteserver2']);
			unset($config['syslog']['remoteserver3']);
		}

		write_config();

		$retval = 0;
		$retval = system_syslogd_start();
		if (($oldnologdefaultblock !== isset($config['syslog']['nologdefaultblock']))
			|| ($oldnologdefaultpass !== isset($config['syslog']['nologdefaultpass']))
			|| ($oldnologbogons !== isset($config['syslog']['nologbogons']))
			|| ($oldnologprivatenets !== isset($config['syslog']['nologprivatenets'])))
			$retval |= filter_configure();

		$savemsg = get_std_save_message($retval);

		if ($oldnologlighttpd !== isset($config['syslog']['nologlighttpd'])) {
			ob_flush();
			flush();
			log_error(gettext("webConfigurator configuration has changed. Restarting webConfigurator."));
			send_event("service restart webgui");
			$savemsg .= "<br />" . gettext("WebGUI process is restarting.");
		}

		filter_pflog_start(true);
	}
}

$pgtitle = array(gettext("Status"), gettext("System logs"), gettext("Settings"));
$closehead = false;
include("head.inc");
?>

<script type="text/javascript">
//<![CDATA[
function enable_change(enable_over) {
	if (document.iform.enable.checked || enable_over) {
		document.iform.remoteserver.disabled = 0;
		document.iform.remoteserver2.disabled = 0;
		document.iform.remoteserver3.disabled = 0;
		document.iform.filter.disabled = 0;
		document.iform.dhcp.disabled = 0;
		document.iform.portalauth.disabled = 0;
		document.iform.vpn.disabled = 0;
		document.iform.apinger.disabled = 0;
		document.iform.relayd.disabled = 0;
		document.iform.hostapd.disabled = 0;
		document.iform.system.disabled = 0;
		document.iform.logall.disabled = 0;
		check_everything();
	} else {
		document.iform.remoteserver.disabled = 1;
		document.iform.remoteserver2.disabled = 1;
		document.iform.remoteserver3.disabled = 1;
		document.iform.filter.disabled = 1;
		document.iform.dhcp.disabled = 1;
		document.iform.portalauth.disabled = 1;
		document.iform.vpn.disabled = 1;
		document.iform.apinger.disabled = 1;
		document.iform.relayd.disabled = 1;
		document.iform.hostapd.disabled = 1;
		document.iform.system.disabled = 1;
		document.iform.logall.disabled = 1;
	}
}
function check_everything() {
	if (document.iform.logall.checked) {
		document.iform.filter.disabled = 1;
		document.iform.filter.checked = false;
		document.iform.dhcp.disabled = 1;
		document.iform.dhcp.checked = false;
		document.iform.portalauth.disabled = 1;
		document.iform.portalauth.checked = false;
		document.iform.vpn.disabled = 1;
		document.iform.vpn.checked = false;
		document.iform.apinger.disabled = 1;
		document.iform.apinger.checked = false;
		document.iform.relayd.disabled = 1;
		document.iform.relayd.checked = false;
		document.iform.hostapd.disabled = 1;
		document.iform.hostapd.checked = false;
		document.iform.system.disabled = 1;
		document.iform.system.checked = false;
	} else {
		document.iform.filter.disabled = 0;
		document.iform.dhcp.disabled = 0;
		document.iform.portalauth.disabled = 0;
		document.iform.vpn.disabled = 0;
		document.iform.apinger.disabled = 0;
		document.iform.relayd.disabled = 0;
		document.iform.hostapd.disabled = 0;
		document.iform.system.disabled = 0;
	}
}
//]]>
</script>

<?php

$logfilesizeHelp =	gettext("Logs are held in constant-size circular log files. This field controls how large each log file is, and thus how many entries may exist inside the log. By default this is approximately 500KB per log file, and there are nearly 20 such log files.") .
					'<br /><br />' .
					gettext("NOTE: Log sizes are changed the next time a log file is cleared or deleted. To immediately increase the size of the log files, you must first save the options to set the size, then clear all logs using the \"Reset Log Files\" option farther down this page. ") .
					gettext("Be aware that increasing this value increases every log file size, so disk usage will increase significantly.") . '<br /><br />' .
					gettext("Disk space currently used by log files is: ") . exec("/usr/bin/du -sh /var/log | /usr/bin/awk '{print $1;}'") .
					gettext(" Remaining disk space for log files: ") . exec("/bin/df -h /var/log | /usr/bin/awk '{print $4;}'");

$remoteloghelp =	gettext("This option will allow the logging daemon to bind to a single IP address, rather than all IP addresses.") .
					gettext("If you pick a single IP, remote syslog severs must all be of that IP type. If you wish to mix IPv4 and IPv6 remote syslog servers, you must bind to all interfaces.") .
					"<br /><br />" .
					gettext("NOTE: If an IP address cannot be located on the chosen interface, the daemon will bind to all addresses.");
if ($input_errors)
	print_input_errors($input_errors);
else if($savemsg)
	print('<div class="alert alert-success" role="alert">'.$savemsg.'</div>');

$tab_array = array();
$tab_array[] = array(gettext("System"), false, "diag_logs.php");
$tab_array[] = array(gettext("Firewall"), false, "diag_logs_filter.php");
$tab_array[] = array(gettext("DHCP"), false, "diag_logs_dhcp.php");
$tab_array[] = array(gettext("Portal Auth"), false, "diag_logs_auth.php");
$tab_array[] = array(gettext("IPsec"), false, "diag_logs_ipsec.php");
$tab_array[] = array(gettext("PPP"), false, "diag_logs_ppp.php");
$tab_array[] = array(gettext("VPN"), false, "diag_logs_vpn.php");
$tab_array[] = array(gettext("Load Balancer"), false, "diag_logs_relayd.php");
$tab_array[] = array(gettext("OpenVPN"), false, "diag_logs_openvpn.php");
$tab_array[] = array(gettext("NTP"), false, "diag_logs_ntpd.php");
$tab_array[] = array(gettext("Settings"), true, "diag_logs_settings.php");
display_top_tabs($tab_array);

require('classes/Form.class.php');

$form = new Form(new Form_Button(
	'Submit',
	gettext("Save")
));

// General logging section ------------------------------------------------
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
))->setHelp(gettext('Packets that are blocked by the implicit default block rule will not be logged if you uncheck this option. Per-rule logging options are still respected.'));

$section->addInput(new Form_Checkbox(
	'logdefaultpass',
	null,
	'Log packets matched from the default pass rules put in the ruleset',
	$pconfig['logdefaultpass']
))->setHelp(gettext('Packets that are allowed by the implicit default pass rule will be logged if you check this option. Per-rule logging options are still respected. '));

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
	'loglighttpd',
	'Web Server Log',
	'Log errors from the web server process',
	$pconfig['loglighttpd']
))->setHelp(gettext('If this is checked, errors from the lighttpd web server process for the GUI or Captive Portal will appear in the main system log'));

$section->addInput(new Form_Checkbox(
	'rawfilter',
	'Raw Logs',
	'Show raw filter logs)',
	$pconfig['rawfilter']
))->setHelp(gettext('If this is checked, filter logs are shown as generated by the packet filter, without any formatting. This will reveal more detailed information, but it is more difficult to read'));

$section->addInput(new Form_Select(
	'filterdescriptions',
	'Where to show rule descriptions',
	!isset($pconfig['filterdescriptions']) ? '0':$pconfig['filterdescriptions'],
	array(
		'0' => 'Dont load descriptions',
		'1' => 'Display as column',
		'2' => 'Display as second row'
	)
))->setHelp('Show the applied rule description below or in the firewall log rows' . '<br />' .
			'Displaying rule descriptions for all lines in the log might affect performance with large rule sets');

$section->addInput(new Form_Checkbox(
	'disablelocallogging',
	'Local Logging',
	$g['platform'] == 'pfSense' ? gettext("Disable writing log files to the local disk") : gettext("Disable writing log files to the local RAM disk"),
	$pconfig['disablelocallogging']
));

$resetlogsbtn = new Form_Button(
    'resetlogs', 
    'Reset Log Files'
);

$resetlogsbtn->removeClass("btn-primary")->addClass("btn-danger btn-xs");

$section->addInput(new Form_StaticText(
	'Reset Logs',
	 $resetlogsbtn
))->setHelp('Clears all local log files and reinitializes them as empty logs. This also restarts the DHCP daemon. Use the Save button first if you have made any setting changes.');


// Remote logging section ------------------------------------------------
$section2 = new Form_Section('Remote Logging Options');

$sourceips = get_possible_traffic_source_addresses(false);

$selected = "";

foreach ($sourceips as $sa) {
	$rllist[$sa['value']] = $sa['name'];

	if (!link_interface_to_bridge($sa['value']) && ($sa['value'] == $pconfig['sourceip']))
		$selected = $sa['value'];
}

$section2->addInput(new Form_Select(
	'sourceip',
	'Source Address',
	$selected,
	$rllist
))->setHelp($remoteloghelp);

$section2->addInput(new Form_Select(
	'ipproto',
	'IP Protocol',
	$ipproto,
	array('ipv4' => 'IPv4', 'ipv6' => 'IPv6')
))->setHelp(gettext("This option is only used when a non-default address is chosen as the source above. This option only expresses a preference; If an IP address of the selected type is not found on the chosen interface, the other type will be tried."));

$section2->addInput(new Form_Checkbox(
	'enable',
	'Enable Remote Logging',
	'Send log messages to remote syslog server',
	$pconfig['enable']
));

$section2->addInput(new Form_Input(
	'remoteserver',
	'Server 1',
	'text',
	$pconfig['remoteserver'],
	['placeholder' => 'IP[:port]']
));

$section2->addInput(new Form_Input(
	'remoteserver2',
	'Server 2',
	'text',
	$pconfig['remoteserver2'],
	['placeholder' => 'IP[:port]']
));

$section2->addInput(new Form_Input(
	'remoteserver3',
	'Server 3',
	'text',
	$pconfig['remoteserver3'],
	['placeholder' => 'IP[:port]']
))->setHelp('IP addresses or IP:Port of remote syslog servers');

$section2->addInput(new Form_Checkbox(
	'logall',
	'Remote Syslog Contents',
	'Everything!',
	$pconfig['logall']
));

$section2->addInput(new Form_Checkbox(
	'system',
	'',
	'System Events',
	$pconfig['system']
));

$section2->addInput(new Form_Checkbox(
	'filter',
	'',
	'Firewall Events',
	$pconfig['filter']
));

$section2->addInput(new Form_Checkbox(
	'dhcp',
	'',
	'DHCP service events',
	$pconfig['dhcp']
));

$section2->addInput(new Form_Checkbox(
	'portalauth',
	'',
	'Portal Auth events',
	$pconfig['portalauth']
));

$section2->addInput(new Form_Checkbox(
	'vpn',
	'',
	'VPN (PPTP, IPsec, OpenVPN) events',
	$pconfig['vpn']
));

$section2->addInput(new Form_Checkbox(
	'apinger',
	'',
	'Gateway Monitor events',
	$pconfig['apinger']
));

$section2->addInput(new Form_Checkbox(
	'relayd',
	'',
	'Server Load Balancer events',
	$pconfig['relayd']
));

$section2->addInput(new Form_Checkbox(
	'hostapd',
	'',
	'Wireless events',
	$pconfig['hostapd']
));

$section2->addInput(new Form_StaticText(
	'Note',
	'syslog sends UDP datagrams to port 514 on the specified remote syslog server, unless another port is specified. Be sure to set syslogd on the remote server to accept syslog messages frompfSense.'
));

$form->add($section);
$form->add($section2);
print $form;
?>

<script type="text/javascript">
//<![CDATA[
enable_change(false);
//]]>
</script>

<?php include("foot.inc"); ?>
<script>
//<![CDATA[
setRemoteServers();

$("#enable").click( function(){
	setRemoteServers();
});

function setRemoteServers() {
	if(enable.checked == 1) {
		$("#remoteserver" ).prop('disabled', false);
		$("#remoteserver2").prop('disabled', false);
		$("#remoteserver3").prop('disabled', false);
	} else	{
		$("#remoteserver" ).prop('disabled', true);
		$("#remoteserver2").prop('disabled', true);
		$("#remoteserver3").prop('disabled', true);
	}
}
//]]>
</script>
