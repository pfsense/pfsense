#!/usr/local/bin/php
<?php 
/*
	services_dyndns.php
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

$pconfig['username'] = $config['dyndns']['username'];
$pconfig['password'] = $config['dyndns']['password'];
$pconfig['host'] = $config['dyndns']['host'];
$pconfig['mx'] = $config['dyndns']['mx'];
$pconfig['type'] = $config['dyndns']['type'];
$pconfig['enable'] = isset($config['dyndns']['enable']);
$pconfig['wildcard'] = isset($config['dyndns']['wildcard']);

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['enable']) {
		$reqdfields = explode(" ", "host username password type");
		$reqdfieldsn = explode(",", "Hostname,Username,Password,Service type");
		
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	}
	
	if (($_POST['host'] && !is_domain($_POST['host']))) {
		$input_errors[] = "The host name contains invalid characters.";
	}
	if (($_POST['mx'] && !is_domain($_POST['mx']))) {
		$input_errors[] = "The MX contains invalid characters.";
	}
	if (($_POST['username'] && !is_dyndns_username($_POST['username']))) {
		$input_errors[] = "The username contains invalid characters.";
	}

	if (!$input_errors) {
		$config['dyndns']['type'] = $_POST['type'];	
		$config['dyndns']['username'] = $_POST['username'];
		$config['dyndns']['password'] = $_POST['password'];
		$config['dyndns']['host'] = $_POST['host'];
		$config['dyndns']['mx'] = $_POST['mx'];
		$config['dyndns']['wildcard'] = $_POST['wildcard'] ? true : false;
		$config['dyndns']['enable'] = $_POST['enable'] ? true : false;
			
		write_config();
		
		$retval = 0;
		if (!file_exists($d_sysrebootreqd_path)) {
			/* nuke the cache file */
			config_lock();
			services_dyndns_reset();
			$retval = services_dyndns_configure();
			config_unlock();
		}
		$savemsg = get_std_save_message($retval);
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Services: Dynamic DNS client");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
<script language="JavaScript">
<!--
function enable_change(enable_change) {
	if (document.iform.enable.checked || enable_change) {
		document.iform.host.disabled = 0;
		document.iform.mx.disabled = 0;
		document.iform.type.disabled = 0;
		document.iform.wildcard.disabled = 0;
		document.iform.username.disabled = 0;
		document.iform.password.disabled = 0;
	} else {
		document.iform.host.disabled = 1;
		document.iform.mx.disabled = 1;
		document.iform.type.disabled = 1;
		document.iform.wildcard.disabled = 1;
		document.iform.username.disabled = 1;
		document.iform.password.disabled = 1;
	}
}
//-->
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Services: Dynamic DNS client</p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
            <form action="services_dyndns.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr> 
                  <td width="22%" valign="top" class="vtable">&nbsp;</td>
                  <td width="78%" class="vtable">
<input name="enable" type="checkbox" value="yes" <?php if ($pconfig['enable']) echo "checked"; ?> onClick="enable_change(false)">
                    <strong>Enable Dynamic DNS client</strong></td>
				</tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Service type</td>
                  <td width="78%" class="vtable">
<select name="type" class="formfld" id="type">
                      <?php $types = explode(",", "DynDNS,DHS,ODS,DyNS,HN.ORG,ZoneEdit,GNUDip,DynDNS (static),DynDNS (custom),easyDNS,EZ-IP,TZO");
					        $vals = explode(" ", "dyndns dhs ods dyns hn zoneedit gnudip dyndns-static dyndns-custom easydns ezip tzo");
					  $j = 0; for ($j = 0; $j < count($vals); $j++): ?>
                      <option value="<?=$vals[$j];?>" <?php if ($vals[$j] == $pconfig['type']) echo "selected";?>> 
                      <?=htmlspecialchars($types[$j]);?>
                      </option>
                      <?php endfor; ?>
                    </select></td>
				</tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Hostname</td>
                  <td width="78%" class="vtable"> 
                    <input name="host" type="text" class="formfld" id="host" size="30" value="<?=htmlspecialchars($pconfig['host']);?>"> 
                  </td>
				</tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">MX</td>
                  <td width="78%" class="vtable"> 
                    <input name="mx" type="text" class="formfld" id="mx" size="30" value="<?=htmlspecialchars($pconfig['mx']);?>"> 
                    <br>
                    Set this option only if you need a special MX record. Not 
                    all services support this.</td>
				</tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Wildcards</td>
                  <td width="78%" class="vtable"> 
                    <input name="wildcard" type="checkbox" id="wildcard" value="yes" <?php if ($pconfig['wildcard'] == "yes") echo "checked"; ?>>
                    Enable Wildcard</td>
				</tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Username</td>
                  <td width="78%" class="vtable"> 
                    <input name="username" type="text" class="formfld" id="username" size="20" value="<?=htmlspecialchars($pconfig['username']);?>"> 
                  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Password</td>
                  <td width="78%" class="vtable"> 
                    <input name="password" type="password" class="formfld" id="password" size="20" value="<?=htmlspecialchars($pconfig['password']);?>"> 
                  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="Save" onClick="enable_change(true)"> 
                  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"><span class="vexpl"><span class="red"><strong>Note:<br>
                    </strong></span>You must configure a DNS server in <a href="system.php">System: 
                    General setup</a> or allow the DNS server list to be overridden 
                    by DHCP/PPP on WAN for the DynDNS client to work.</span></td>
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
