<?php
/* $Id$ */
/*
	diag_logs_settings.php
	Copyright (C) 2004-2009 Scott Ullrich
	All rights reserved.

	originially part of m0n0wall (http://m0n0.ch/wall)
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
	pfSense_MODULE:	system
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
$pconfig['logbogons'] = !isset($config['syslog']['nologbogons']);
$pconfig['logprivatenets'] = !isset($config['syslog']['nologprivatenets']);
$pconfig['loglighttpd'] = !isset($config['syslog']['nologlighttpd']);
$pconfig['rawfilter'] = isset($config['syslog']['rawfilter']);
$pconfig['filterdescriptions'] = $config['syslog']['filterdescriptions'];
$pconfig['disablelocallogging'] = isset($config['syslog']['disablelocallogging']);

if (!$pconfig['nentries'])
	$pconfig['nentries'] = 50;

function is_valid_syslog_server($target) {
	return (is_ipaddr($target)
		|| is_ipaddrwithport($target)
		|| is_hostname($target)
		|| is_hostnamewithport($target));
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['enable'] && !is_valid_syslog_server($_POST['remoteserver'])) {
		$input_errors[] = gettext("A valid IP address/hosname or IP/hostname:port must be specified for remote syslog server #1.");
	}
	if ($_POST['enable'] && $_POST['remoteserver2'] && !is_valid_syslog_server($_POST['remoteserver2'])) {
		$input_errors[] = gettext("A valid IP address/hosname or IP/hostname:port must be specified for remote syslog server #2.");
	}
	if ($_POST['enable'] && $_POST['remoteserver3'] && !is_valid_syslog_server($_POST['remoteserver3'])) {
		$input_errors[] = gettext("A valid IP address/hosname or IP/hostname:port must be specified for remote syslog server #3.");
	}

	if (($_POST['nentries'] < 5) || ($_POST['nentries'] > 2000)) {
		$input_errors[] = gettext("Number of log entries to show must be between 5 and 2000.");
	}

	if (!$input_errors) {
		$config['syslog']['reverse'] = $_POST['reverse'] ? true : false;
		$config['syslog']['nentries'] = (int)$_POST['nentries'];
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
		$oldnologbogons = isset($config['syslog']['nologbogons']);
		$oldnologprivatenets = isset($config['syslog']['nologprivatenets']);
		$oldnologlighttpd = isset($config['syslog']['nologlighttpd']);
		$config['syslog']['nologdefaultblock'] = $_POST['logdefaultblock'] ? false : true;
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
include("head.inc");

?>


<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<script type="text/JavaScript">
<!--
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
// -->
</script>

<?php include("fbegin.inc"); ?>
<form action="diag_logs_settings.php" method="post" name="iform" id="iform">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td>
<?php
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
?>
</td></tr>
<tr>
	<td>
	<div id="mainarea">
	<table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?=gettext("General Logging Options");?></td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vtable">Forward/Reverse Display</td>
			<td width="78%" class="vtable"> <input name="reverse" type="checkbox" id="reverse" value="yes" <?php if ($pconfig['reverse']) echo "checked=\"checked\""; ?> />
			<strong><?=gettext("Show log entries in reverse order (newest entries on top)");?></strong></td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vtable">GUI Log Entries to Display</td>
			<td width="78%" class="vtable">
			<input name="nentries" id="nentries" type="text" class="formfld unknown" size="4" value="<?=htmlspecialchars($pconfig['nentries']);?>" /><br/>
			<?=gettext("Hint: This is only the number of log entries displayed in the GUI. It does not affect how many entries are contained in the actual log files.") ?></td>
		</tr>
		<tr>
			<td valign="top" class="vtable">Log Firewall Default Blocks</td>
			<td class="vtable">
				<input name="logdefaultblock" type="checkbox" id="logdefaultblock" value="yes" <?php if ($pconfig['logdefaultblock']) echo "checked=\"checked\""; ?> />
				<strong><?=gettext("Log packets blocked by the default rule");?></strong><br/>
				<?=gettext("Hint: packets that are blocked by the implicit default block rule will not be logged if you uncheck this option. Per-rule logging options are still respected.");?>
				<br/>
				<input name="logbogons" type="checkbox" id="logbogons" value="yes" <?php if ($pconfig['logbogons']) echo "checked=\"checked\""; ?> />
				<strong><?=gettext("Log packets blocked by 'Block Bogon Networks' rules");?></strong><br/>
				<br/>
				<input name="logprivatenets" type="checkbox" id="logprivatenets" value="yes" <?php if ($pconfig['logprivatenets']) echo "checked=\"checked\""; ?> />
				<strong><?=gettext("Log packets blocked by 'Block Private Networks' rules");?></strong><br/>
			</td>
		</tr>
		<tr>
			<td valign="top" class="vtable">Web Server Log</td>
			<td class="vtable"> <input name="loglighttpd" type="checkbox" id="loglighttpd" value="yes" <?php if ($pconfig['loglighttpd']) echo "checked=\"checked\""; ?> />
			<strong><?=gettext("Log errors from the web server process.");?></strong><br/>
			<?=gettext("Hint: If this is checked, errors from the lighttpd web server process for the GUI or Captive Portal will appear in the main system log.");?></td>
		</tr>
		<tr>
			<td valign="top" class="vtable">Raw Logs</td>
			<td class="vtable"> <input name="rawfilter" type="checkbox" id="rawfilter" value="yes" <?php if ($pconfig['rawfilter']) echo "checked=\"checked\""; ?> />
			<strong><?=gettext("Show raw filter logs");?></strong><br/>
			<?=gettext("Hint: If this is checked, filter logs are shown as generated by the packet filter, without any formatting. This will reveal more detailed information, but it is more difficult to read.");?></td>
		</tr>
		<tr>
			<td valign="top" class="vtable">Filter descriptions</td>
			<td class="vtable">
				<select name="filterdescriptions" id="filterdescriptions" >
				  <option value="0"<?=!isset($pconfig['filterdescriptions'])?" selected=\"selected\"":""?>>Dont load descriptions</option>
				  <option value="1"<?=($pconfig['filterdescriptions'])==="1"?" selected=\"selected\"":""?>>Display as column</option>
				  <option value="2"<?=($pconfig['filterdescriptions'])==="2"?" selected=\"selected\"":""?>>Display as second row</option>
				</select>
				<strong><?=gettext("Show the applied rule description below or in the firewall log rows.");?></strong>
				<br/>
				<?=gettext("Displaying rule descriptions for all lines in the log might affect performance with large rule sets.");?>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vtable">Local Logging</td>
			<td width="78%" class="vtable"> <input name="disablelocallogging" type="checkbox" id="disablelocallogging" value="yes" <?php if ($pconfig['disablelocallogging']) echo "checked=\"checked\""; ?> onclick="enable_change(false)" />
			<?php if ($g['platform'] == "pfSense"): ?>
			<strong><?=gettext("Disable writing log files to the local disk");?></strong></td>
			<?php else: ?>
			<strong><?=gettext("Disable writing log files to the local RAM disk");?></strong></td>
			<?php endif; ?>
		</tr>
		<tr>
			<td colspan="2" valign="top">&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?=gettext("Remote Logging Options");?></td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Source Address"); ?></td>
			<td width="78%" class="vtable">
				<select name="sourceip" class="formselect">
					<option value="">Default (any)</option>
				<?php $sourceips = get_possible_traffic_source_addresses(false);
					foreach ($sourceips as $sip):
						$selected = "";
						if (!link_interface_to_bridge($sip['value']) && ($sip['value'] == $pconfig['sourceip']))
							$selected = 'selected="selected"';
				?>
					<option value="<?=$sip['value'];?>" <?=$selected;?>>
						<?=htmlspecialchars($sip['name']);?>
					</option>
					<?php endforeach; ?>
				</select>
				<br/>
				<?= gettext("This option will allow the logging daemon to bind to a single IP address, rather than all IP addresses."); ?>
				<?= gettext("If you pick a single IP, remote syslog severs must all be of that IP type. If you wish to mix IPv4 and IPv6 remote syslog servers, you must bind to all interfaces."); ?>
				<br/><br/>
				<?= gettext("NOTE: If an IP address cannot be located on the chosen interface, the daemon will bind to all addresses."); ?>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("IP Protocol"); ?></td>
			<td width="78%" class="vtable">
				<select name="ipproto" class="formselect">
					<option value="ipv4" <?php if ($ipproto == "ipv4") echo 'selected="selected"' ?>>IPv4</option>
					<option value="ipv6" <?php if ($ipproto == "ipv6") echo 'selected="selected"' ?>>IPv6</option>
				</select>
				<br/>
				<?= gettext("This option is only used when a non-default address is chosen as the source above. This option only expresses a preference; If an IP address of the selected type is not found on the chosen interface, the other type will be tried."); ?>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Enable Remote Logging");?></td>
			<td width="78%" class="vtable"> <input name="enable" type="checkbox" id="enable" value="yes" <?php if ($pconfig['enable']) echo "checked=\"checked\""; ?> onclick="enable_change(false)" />
				<strong><?=gettext("Send log messages to remote syslog server");?></strong></td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Remote Syslog Servers");?></td>
			<td width="78%" class="vtable">
				<table>
					<tr>
						<td><?=gettext("Server") . " 1";?></td>
						<td><input name="remoteserver" id="remoteserver" type="text" class="formfld host" size="20" value="<?=htmlspecialchars($pconfig['remoteserver']);?>" /></td>
					</tr>
					<tr>
						<td><?=gettext("Server") . " 2";?></td>
						<td><input name="remoteserver2" id="remoteserver2" type="text" class="formfld host" size="20" value="<?=htmlspecialchars($pconfig['remoteserver2']);?>" /></td>
					</tr>
					<tr>
						<td><?=gettext("Server") . " 3";?></td>
						<td><input name="remoteserver3" id="remoteserver3" type="text" class="formfld host" size="20" value="<?=htmlspecialchars($pconfig['remoteserver3']);?>" /></td>
					</tr>
					<tr>
						<td>&nbsp;</td>
						<td><?=gettext("IP addresses of remote syslog servers, or an IP:port.");?></td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Remote Syslog Contents");?></td>
			<td width="78%" class="vtable">
				<input name="logall" id="logall" type="checkbox" value="yes" <?php if ($pconfig['logall']) echo "checked=\"checked\""; ?> onclick="check_everything();" />
				<?=gettext("Everything");?><br/><br/>
				<input name="system" id="system" type="checkbox" value="yes" onclick="enable_change(false)" <?php if ($pconfig['system']) echo "checked=\"checked\""; ?> />
				<?=gettext("System events");?><br/>
				<input name="filter" id="filter" type="checkbox" value="yes" <?php if ($pconfig['filter']) echo "checked=\"checked\""; ?> />
				<?=gettext("Firewall events");?><br/>
				<input name="dhcp" id="dhcp" type="checkbox" value="yes" <?php if ($pconfig['dhcp']) echo "checked=\"checked\""; ?> />
				<?=gettext("DHCP service events");?><br/>
				<input name="portalauth" id="portalauth" type="checkbox" value="yes" <?php if ($pconfig['portalauth']) echo "checked=\"checked\""; ?> />
				<?=gettext("Portal Auth events");?><br/>
				<input name="vpn" id="vpn" type="checkbox" value="yes" <?php if ($pconfig['vpn']) echo "checked=\"checked\""; ?> />
				<?=gettext("VPN (PPTP, IPsec, OpenVPN) events");?><br/>
				<input name="apinger" id="apinger" type="checkbox" value="yes" <?php if ($pconfig['apinger']) echo "checked=\"checked\""; ?> />
				<?=gettext("Gateway Monitor events");?><br/>
				<input name="relayd" id="relayd" type="checkbox" value="yes" <?php if ($pconfig['relayd']) echo "checked=\"checked\""; ?> />
				<?=gettext("Server Load Balancer events");?><br/>
				<input name="hostapd" id="hostapd" type="checkbox" value="yes" <?php if ($pconfig['hostapd']) echo "checked=\"checked\""; ?> />
				<?=gettext("Wireless events");?><br/>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%"> <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" onclick="enable_change(true)" />
			</td>
		</tr>
		<tr>
			<td width="22%" height="53" valign="top">&nbsp;</td>
			<td width="78%"><strong><span class="red"><?=gettext("Note:")?></span></strong><br/>
			<?=gettext("syslog sends UDP datagrams to port 514 on the specified " .
			"remote syslog server, unless another port is specified. Be sure to set syslogd on the " .
			"remote server to accept syslog messages from");?> <?=$g['product_name']?>.
			</td>
		</tr>
	</table>
	</div>
</td></tr>
</table>
</form>
<script type="text/JavaScript">
<!--
enable_change(false);
//-->
</script>
<?php include("fend.inc"); ?>
</body>
</html>
