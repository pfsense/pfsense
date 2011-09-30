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
$pconfig['filter'] = isset($config['syslog']['filter']);
$pconfig['dhcp'] = isset($config['syslog']['dhcp']);
$pconfig['portalauth'] = isset($config['syslog']['portalauth']);
$pconfig['vpn'] = isset($config['syslog']['vpn']);
$pconfig['logall'] = isset($config['syslog']['logall']);
$pconfig['system'] = isset($config['syslog']['system']);
$pconfig['enable'] = isset($config['syslog']['enable']);
$pconfig['logdefaultblock'] = !isset($config['syslog']['nologdefaultblock']);
$pconfig['rawfilter'] = isset($config['syslog']['rawfilter']);
$pconfig['disablelocallogging'] = isset($config['syslog']['disablelocallogging']);

if (!$pconfig['nentries'])
	$pconfig['nentries'] = 50;

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['enable'] && !is_ipaddr($_POST['remoteserver'])) {
		$input_errors[] = gettext("A valid IP address must be specified for remote syslog server #1.");
	}
	if ($_POST['enable'] && $_POST['remoteserver2'] && !is_ipaddr($_POST['remoteserver2'])) {
		$input_errors[] = gettext("A valid IP address must be specified for remote syslog server #2.");
	}
	if ($_POST['enable'] && $_POST['remoteserver3'] && !is_ipaddr($_POST['remoteserver3'])) {
		$input_errors[] = gettext("A valid IP address must be specified for remote syslog server #3.");
	}
	if ($_POST['enable'] && !is_ipaddr($_POST['remoteserver'])) {
		$input_errors[] = gettext("A valid IP address must be specified.");
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
		$config['syslog']['filter'] = $_POST['filter'] ? true : false;
		$config['syslog']['dhcp'] = $_POST['dhcp'] ? true : false;
		$config['syslog']['portalauth'] = $_POST['portalauth'] ? true : false;
		$config['syslog']['vpn'] = $_POST['vpn'] ? true : false;
                $config['syslog']['logall'] = $_POST['logall'] ? true : false;
		$config['syslog']['system'] = $_POST['system'] ? true : false;
		$config['syslog']['disablelocallogging'] = $_POST['disablelocallogging'] ? true : false;
		$config['syslog']['enable'] = $_POST['enable'] ? true : false;
		$oldnologdefaultblock = isset($config['syslog']['nologdefaultblock']);
		$config['syslog']['nologdefaultblock'] = $_POST['logdefaultblock'] ? false : true;
		$config['syslog']['rawfilter'] = $_POST['rawfilter'] ? true : false;
		if($config['syslog']['enable'] == false) {
			unset($config['syslog']['remoteserver']);
			unset($config['syslog']['remoteserver2']);
			unset($config['syslog']['remoteserver3']);
		}

		write_config();

		$retval = 0;
		$retval = system_syslogd_start();
		if ($oldnologdefaultblock !== isset($config['syslog']['nologdefaultblock']))
			$retval |= filter_configure();

		$savemsg = get_std_save_message($retval);
	}
}

$pgtitle = array(gettext("Status"), gettext("System logs"), gettext("Settings"));
include("head.inc");

?>


<script language="JavaScript">
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
		document.iform.system.disabled = 0;
		document.iform.logall.disabled = 0;
	} else {
		document.iform.remoteserver.disabled = 1;
		document.iform.remoteserver2.disabled = 1;
		document.iform.remoteserver3.disabled = 1;
		document.iform.filter.disabled = 1;
		document.iform.dhcp.disabled = 1;
		document.iform.portalauth.disabled = 1;
		document.iform.vpn.disabled = 1;
		document.iform.system.disabled = 1;
		document.iform.logall.disabled = 1;
	}
}
// -->
</script>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
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
	$tab_array[] = array(gettext("OpenNTPD"), false, "diag_logs_ntpd.php");
	$tab_array[] = array(gettext("Wireless"), false, "diag_logs_wireless.php");
	$tab_array[] = array(gettext("Settings"), true, "diag_logs_settings.php");
	display_top_tabs($tab_array);
?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
	  <table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
                      <tr>
                        <td width="22%" valign="top" class="vtable">&nbsp;</td>
                        <td width="78%" class="vtable"> <input name="reverse" type="checkbox" id="reverse" value="yes" <?php if ($pconfig['reverse']) echo "checked"; ?>>
			<strong><?=gettext("Show log entries in reverse order (newest entries on top)");?></strong></td>
                      </tr>
                      <tr>
                        <td width="22%" valign="top" class="vtable">&nbsp;</td>
			<td width="78%" class="vtable"><?=gettext("Number of log entries to show:")?>
                          <input name="nentries" id="nentries" type="text" class="formfld unknown" size="4" value="<?=htmlspecialchars($pconfig['nentries']);?>"></td>
                      </tr>
                      <tr>
                        <td valign="top" class="vtable">&nbsp;</td>
                        <td class="vtable"> <input name="logdefaultblock" type="checkbox" id="logdefaultblock" value="yes" <?php if ($pconfig['logdefaultblock']) echo "checked"; ?>>
			<strong><?=gettext("Log packets blocked by the default rule");?></strong><br>
			  <?=gettext("Hint: packets that are blocked by the " .
                          "implicit default block rule will not be logged anymore " .
			  "if you uncheck this option. Per-rule logging options are not affected.");?></td>
                      </tr>
                      <tr>
                        <td valign="top" class="vtable">&nbsp;</td>
                        <td class="vtable"> <input name="rawfilter" type="checkbox" id="rawfilter" value="yes" <?php if ($pconfig['rawfilter']) echo "checked"; ?>>
			<strong><?=gettext("Show raw filter logs");?></strong><br>
			  <?=gettext("Hint: If this is checked, filter logs are shown as generated by the packet filter, without any formatting. This will reveal more detailed information.");?></td>
                      </tr>
                      <tr>
                        <td width="22%" valign="top" class="vtable">&nbsp;</td>
                        <td width="78%" class="vtable"> <input name="disablelocallogging" type="checkbox" id="disablelocallogging" value="yes" <?php if ($pconfig['disablelocallogging']) echo "checked"; ?> onClick="enable_change(false)">
			  <strong><?=gettext("Disable writing log files to the local RAM disk");?></strong></td>
                       </tr>
                      <tr>
                        <td width="22%" valign="top" class="vtable">&nbsp;</td>
                        <td width="78%" class="vtable"> <input name="enable" type="checkbox" id="enable" value="yes" <?php if ($pconfig['enable']) echo "checked"; ?> onClick="enable_change(false)">
			  <strong><?=gettext("Enable syslog'ing to remote syslog server");?></strong></td>
                      </tr>
                      <tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Remote syslog servers");?></td>
                        <td width="78%" class="vtable"> 
							<table>
								<tr>
									<td>
										<?=gettext("Server") . " 1";?>
									</td>
									<td>
										<input name="remoteserver" id="remoteserver" type="text" class="formfld host" size="20" value="<?=htmlspecialchars($pconfig['remoteserver']);?>">
									</td>
								</tr>
								<tr>
									<td>
										<?=gettext("Server") . " 2";?>
									</td>
									<td>
										<input name="remoteserver2" id="remoteserver2" type="text" class="formfld host" size="20" value="<?=htmlspecialchars($pconfig['remoteserver2']);?>">
									</td>
								</tr>
								<tr>
									<td>
										<?=gettext("Server") . " 3";?>
									</td>
									<td>
										<input name="remoteserver3" id="remoteserver3" type="text" class="formfld host" size="20" value="<?=htmlspecialchars($pconfig['remoteserver3']);?>">
									</td>
								</tr>
								<tr>
									<td>
										&nbsp;
									</td>
									<td>
										<?=gettext("IP addresses of remote syslog servers");?>
									</td>
							</table>
					 	  <input name="system" id="system" type="checkbox" value="yes" onclick="enable_change(false)" <?php if ($pconfig['system']) echo "checked"; ?>>
						  <?=gettext("system events");?><br> <input name="filter" id="filter" type="checkbox" value="yes" <?php if ($pconfig['filter']) echo "checked"; ?>>
						  <?=gettext("firewall events");?><br> <input name="dhcp" id="dhcp" type="checkbox" value="yes" <?php if ($pconfig['dhcp']) echo "checked"; ?>>
						  <?=gettext("DHCP service events");?><br> <input name="portalauth" id="portalauth" type="checkbox" value="yes" <?php if ($pconfig['portalauth']) echo "checked"; ?>>
						  <?=gettext("Portal Auth");?><br> <input name="vpn" id="vpn" type="checkbox" value="yes" <?php if ($pconfig['vpn']) echo "checked"; ?>>
						  <?=gettext("PPTP VPN events");?>
                          <br> <input name="logall" id="logall" type="checkbox" value="yes" <?php if ($pconfig['logall']) echo "checked"; ?>>
						  <?=gettext("Everything");?>
                        </td>
                      </tr>
                      <tr>
                        <td width="22%" valign="top">&nbsp;</td>
                        <td width="78%"> <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" onclick="enable_change(true)">
                        </td>
                      </tr>
                      <tr>
                        <td width="22%" height="53" valign="top">&nbsp;</td>
						<td width="78%"><strong><span class="red"><?=gettext("Note:")?></span></strong><br>
                          <?=gettext("syslog sends UDP datagrams to port 514 on the specified " .
                          "remote syslog server. Be sure to set syslogd on the " .
						  "remote server to accept syslog messages from");?> <?=$g['product_name']?>.
                        </td>
                      </tr>
                    </table>
	</div>
    </td>
  </tr>
</table>
</form>
<script language="JavaScript">
<!--
enable_change(false);
//-->
</script>
<?php include("fend.inc"); ?>
</body>
</html>
