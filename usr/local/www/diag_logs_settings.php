#!/usr/local/bin/php
<?php 
/*
	diag_logs_settings.php
	part of m0n0wall (http://m0n0.ch/wall)
	
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

require("guiconfig.inc");

$pconfig['reverse'] = isset($config['syslog']['reverse']);
$pconfig['nentries'] = $config['syslog']['nentries'];
$pconfig['remoteserver'] = $config['syslog']['remoteserver'];
$pconfig['filter'] = isset($config['syslog']['filter']);
$pconfig['dhcp'] = isset($config['syslog']['dhcp']);
$pconfig['vpn'] = isset($config['syslog']['vpn']);
$pconfig['system'] = isset($config['syslog']['system']);
$pconfig['enable'] = isset($config['syslog']['enable']);
$pconfig['logdefaultblock'] = !isset($config['syslog']['nologdefaultblock']);
$pconfig['rawfilter'] = isset($config['syslog']['rawfilter']);

if (!$pconfig['nentries'])
	$pconfig['nentries'] = 50;

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['enable'] && !is_ipaddr($_POST['remoteserver'])) {
		$input_errors[] = "A valid IP address must be specified.";
	}
	if (($_POST['nentries'] < 5) || ($_POST['nentries'] > 1000)) {
		$input_errors[] = "Number of log entries to show must be between 5 and 1000.";
	}

	if (!$input_errors) {
		$config['syslog']['reverse'] = $_POST['reverse'] ? true : false;
		$config['syslog']['nentries'] = (int)$_POST['nentries'];
		$config['syslog']['remoteserver'] = $_POST['remoteserver'];
		$config['syslog']['filter'] = $_POST['filter'] ? true : false;
		$config['syslog']['dhcp'] = $_POST['dhcp'] ? true : false;
		$config['syslog']['vpn'] = $_POST['vpn'] ? true : false;
		$config['syslog']['system'] = $_POST['system'] ? true : false;
		$config['syslog']['enable'] = $_POST['enable'] ? true : false;
		$oldnologdefaultblock = isset($config['syslog']['nologdefaultblock']);
		$config['syslog']['nologdefaultblock'] = $_POST['logdefaultblock'] ? false : true;
		$config['syslog']['rawfilter'] = $_POST['rawfilter'] ? true : false;
		
		write_config();
		
		$retval = 0;
		if (!file_exists($d_sysrebootreqd_path)) {
			config_lock();
			$retval = system_syslogd_start();
			if ($oldnologdefaultblock !== isset($config['syslog']['nologdefaultblock']))
				$retval |= filter_configure();
			config_unlock();
		}
		$savemsg = get_std_save_message($retval);	
	}
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Diagnostics: System logs");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
<script language="JavaScript">
<!--
function enable_change(enable_over) {
	if (document.iform.enable.checked || enable_over) {
		document.iform.remoteserver.disabled = 0;
		document.iform.filter.disabled = 0;
		document.iform.dhcp.disabled = 0;
		document.iform.vpn.disabled = 0;
		document.iform.system.disabled = 0;
	} else {
		document.iform.remoteserver.disabled = 1;
		document.iform.filter.disabled = 1;
		document.iform.dhcp.disabled = 1;
		document.iform.vpn.disabled = 1;
		document.iform.system.disabled = 1;
	}
}
// -->
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Diagnostics: System logs</p>
<form action="diag_logs_settings.php" method="post" name="iform" id="iform">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
  <ul id="tabnav">
    <li class="tabinact"><a href="diag_logs.php">System</a></li>
    <li class="tabinact"><a href="diag_logs_filter.php">Firewall</a></li>
    <li class="tabinact"><a href="diag_logs_dhcp.php">DHCP</a></li>
    <li class="tabinact"><a href="diag_logs_vpn.php">PPTP VPN</a></li>
    <li class="tabact">Settings</li>
  </ul>
  </td></tr>
  <tr> 
    <td class="tabcont">
	  <table width="100%" border="0" cellpadding="6" cellspacing="0">
                      <tr> 
                        <td width="22%" valign="top" class="vtable">&nbsp;</td>
                        <td width="78%" class="vtable"> <input name="reverse" type="checkbox" id="reverse" value="yes" <?php if ($pconfig['reverse']) echo "checked"; ?>>
                          <strong>Show log entries in reverse order (newest entries 
                          on top)</strong></td>
                      </tr>
                      <tr> 
                        <td width="22%" valign="top" class="vtable">&nbsp;</td>
                        <td width="78%" class="vtable">Number of log entries to 
                          show: 
                          <input name="nentries" id="nentries" type="text" class="formfld" size="4" value="<?=htmlspecialchars($pconfig['nentries']);?>"></td>
                      </tr>
                      <tr> 
                        <td valign="top" class="vtable">&nbsp;</td>
                        <td class="vtable"> <input name="logdefaultblock" type="checkbox" id="logdefaultblock" value="yes" <?php if ($pconfig['logdefaultblock']) echo "checked"; ?>>
                          <strong>Log packets blocked by the default rule</strong><br>
                          Hint: packets that are blocked by the 
                          implicit default block rule will not be logged anymore 
                          if you uncheck this option. Per-rule logging options are not affected.</td>
                      </tr>
                      <tr> 
                        <td valign="top" class="vtable">&nbsp;</td>
                        <td class="vtable"> <input name="rawfilter" type="checkbox" id="rawfilter" value="yes" <?php if ($pconfig['rawfilter']) echo "checked"; ?>>
                          <strong>Show raw filter logs</strong><br>
                          Hint: If this is checked, filter logs are shown as generated by the packet filter, without any formatting. This will reveal more detailed information. </td>
                      </tr>
                      <tr> 
                        <td width="22%" valign="top" class="vtable">&nbsp;</td>
                        <td width="78%" class="vtable"> <input name="enable" type="checkbox" id="enable" value="yes" <?php if ($pconfig['enable']) echo "checked"; ?> onClick="enable_change(false)">
                          <strong>Enable syslog'ing to remote syslog server</strong></td>
                      </tr>
                      <tr> 
                        <td width="22%" valign="top" class="vncell">Remote syslog 
                          server</td>
                        <td width="78%" class="vtable"> <input name="remoteserver" id="remoteserver" type="text" class="formfld" size="20" value="<?=htmlspecialchars($pconfig['remoteserver']);?>"> 
                          <br>
                          IP address of remote syslog server<br> <br> <input name="system" id="system" type="checkbox" value="yes" onclick="enable_change(false)" <?php if ($pconfig['system']) echo "checked"; ?>>
                          system events <br> <input name="filter" id="filter" type="checkbox" value="yes" <?php if ($pconfig['filter']) echo "checked"; ?>>
                          firewall events<br> <input name="dhcp" id="dhcp" type="checkbox" value="yes" <?php if ($pconfig['dhcp']) echo "checked"; ?>>
                          DHCP service events<br> <input name="vpn" id="vpn" type="checkbox" value="yes" <?php if ($pconfig['vpn']) echo "checked"; ?>>
                          PPTP VPN events</td>
                      </tr>
                      <tr> 
                        <td width="22%" valign="top">&nbsp;</td>
                        <td width="78%"> <input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)"> 
                        </td>
                      </tr>
                      <tr> 
                        <td width="22%" height="53" valign="top">&nbsp;</td>
                        <td width="78%"><strong><span class="red">Note:</span></strong><br>
                          syslog sends UDP datagrams to port 514 on the specified 
                          remote syslog server. Be sure to set syslogd on the 
                          remote server to accept syslog messages from m0n0wall. 
                        </td>
                      </tr>
                    </table>
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
