#!/usr/local/bin/php
<?php 
/*
	services_snmp.php
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

if (!is_array($config['snmpd'])) {
	$config['snmpd'] = array();
	$config['snmpd']['rocommunity'] = "public";
}

$pconfig['syslocation'] = $config['snmpd']['syslocation'];
$pconfig['syscontact'] = $config['snmpd']['syscontact'];
$pconfig['rocommunity'] = $config['snmpd']['rocommunity'];
$pconfig['enable'] = isset($config['snmpd']['enable']);

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['enable']) {
		$reqdfields = explode(" ", "rocommunity");
		$reqdfieldsn = explode(",", "Community");
		
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	}

	if (!$input_errors) {
		$config['snmpd']['syslocation'] = $_POST['syslocation'];	
		$config['snmpd']['syscontact'] = $_POST['syscontact'];
		$config['snmpd']['rocommunity'] = $_POST['rocommunity'];
		$config['snmpd']['enable'] = $_POST['enable'] ? true : false;
			
		write_config();
		
		$retval = 0;
		if (!file_exists($d_sysrebootreqd_path)) {
			config_lock();
			$retval = services_snmpd_configure();
			config_unlock();
		}
		$savemsg = get_std_save_message($retval);
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Services: SNMP");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
<script language="JavaScript">
<!--
function enable_change(enable_change) {
	if (document.iform.enable.checked || enable_change) {
		document.iform.syslocation.disabled = 0;
		document.iform.syscontact.disabled = 0;
		document.iform.rocommunity.disabled = 0;
	} else {
		document.iform.syslocation.disabled = 1;
		document.iform.syscontact.disabled = 1;
		document.iform.rocommunity.disabled = 1;
	}
}
//-->
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Services: SNMP</p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
            <form action="services_snmp.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr> 
                  <td width="22%" valign="top" class="vtable">&nbsp;</td>
                  <td width="78%" class="vtable">
<input name="enable" type="checkbox" value="yes" <?php if ($pconfig['enable']) echo "checked"; ?> onClick="enable_change(false)">
                    <strong>Enable SNMP agent</strong></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">System location</td>
                  <td width="78%" class="vtable"> 
                    <input name="syslocation" type="text" class="formfld" id="syslocation" size="40" value="<?=htmlspecialchars($pconfig['syslocation']);?>"> 
                  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">System contact</td>
                  <td width="78%" class="vtable"> 
                    <input name="syscontact" type="text" class="formfld" id="syscontact" size="40" value="<?=htmlspecialchars($pconfig['syscontact']);?>"> 
                  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Community</td>
                  <td width="78%" class="vtable"> 
                    <input name="rocommunity" type="text" class="formfld" id="rocommunity" size="40" value="<?=htmlspecialchars($pconfig['rocommunity']);?>"> 
                    <br>
                    In most cases, &quot;public&quot; is used here</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="Save" onClick="enable_change(true)"> 
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
