#!/usr/local/bin/php
<?php
/*
	vpn_pptp.php
	part of m0n0wall (http://m0n0.ch/wall)
	
	Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
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

$pgtitle = array("VPN", "PPTP");
require("guiconfig.inc");

if (!is_array($config['pptpd']['radius'])) {
	$config['pptpd']['radius'] = array();
}
$pptpcfg = &$config['pptpd'];

$pconfig['remoteip'] = $pptpcfg['remoteip'];
$pconfig['localip'] = $pptpcfg['localip'];
$pconfig['redir'] = $pptpcfg['redir'];
$pconfig['mode'] = $pptpcfg['mode'];
$pconfig['req128'] = isset($pptpcfg['req128']);
$pconfig['radiusenable'] = isset($pptpcfg['radius']['enable']);
$pconfig['radacct_enable'] = isset($pptpcfg['radius']['accounting']);
$pconfig['radiusserver'] = $pptpcfg['radius']['server'];
$pconfig['radiussecret'] = $pptpcfg['radius']['secret'];

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['mode'] == "server") {
		$reqdfields = explode(" ", "localip remoteip");
		$reqdfieldsn = explode(",", "Server address,Remote start address");
		
		if ($_POST['radiusenable']) {
			$reqdfields = array_merge($reqdfields, explode(" ", "radiusserver radiussecret"));
			$reqdfieldsn = array_merge($reqdfieldsn, 
				explode(",", "RADIUS server address,RADIUS shared secret"));
		}
		
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
		
		if (($_POST['localip'] && !is_ipaddr($_POST['localip']))) {
			$input_errors[] = "A valid server address must be specified.";
		}
		if (($_POST['subnet'] && !is_ipaddr($_POST['remoteip']))) {
			$input_errors[] = "A valid remote start address must be specified.";
		}
		if (($_POST['radiusserver'] && !is_ipaddr($_POST['radiusserver']))) {
			$input_errors[] = "A valid RADIUS server address must be specified.";
		}
		
		if (!$input_errors) {	
			$_POST['remoteip'] = $pconfig['remoteip'] = gen_subnet($_POST['remoteip'], $g['pptp_subnet']);
			$subnet_start = ip2long($_POST['remoteip']);
			$subnet_end = ip2long($_POST['remoteip']) + $g['n_pptp_units'] - 1;
						
			if ((ip2long($_POST['localip']) >= $subnet_start) && 
			    (ip2long($_POST['localip']) <= $subnet_end)) {
				$input_errors[] = "The specified server address lies in the remote subnet.";	
			}
			if ($_POST['localip'] == $config['interfaces']['lan']['ipaddr']) {
				$input_errors[] = "The specified server address is equal to the LAN interface address.";	
			}
		}
	} else if ($_POST['mode'] == "redir") {
		$reqdfields = explode(" ", "redir");
		$reqdfieldsn = explode(",", "PPTP redirection target address");
		
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
		
		if (($_POST['redir'] && !is_ipaddr($_POST['redir']))) {
			$input_errors[] = "A valid target address must be specified.";
		}
	}

	if (!$input_errors) {
		$pptpcfg['remoteip'] = $_POST['remoteip'];
		$pptpcfg['redir'] = $_POST['redir'];
		$pptpcfg['localip'] = $_POST['localip'];
		$pptpcfg['mode'] = $_POST['mode'];
		$pptpcfg['req128'] = $_POST['req128'] ? true : false;
		$pptpcfg['radius']['enable'] = $_POST['radiusenable'] ? true : false;
		$pptpcfg['radius']['accounting'] = $_POST['radacct_enable'] ? true : false;
		$pptpcfg['radius']['server'] = $_POST['radiusserver'];
		$pptpcfg['radius']['secret'] = $_POST['radiussecret'];
			
		write_config();
		
		$retval = 0;
		if (!file_exists($d_sysrebootreqd_path)) {
			config_lock();
			$retval = vpn_pptpd_configure();
			config_unlock();
		}
		$savemsg = get_std_save_message($retval);
	}
}
?>
<?php include("fbegin.inc"); ?>
<script language="JavaScript">
<!--
function get_radio_value(obj)
{
	for (i = 0; i < obj.length; i++) {
		if (obj[i].checked)
			return obj[i].value;
	}
	return null;
}

function enable_change(enable_over) {
	if ((get_radio_value(document.iform.mode) == "server") || enable_over) {
		document.iform.remoteip.disabled = 0;
		document.iform.localip.disabled = 0;
		document.iform.req128.disabled = 0;
		document.iform.radiusenable.disabled = 0;
		
		if (document.iform.radiusenable.checked || enable_over) {
			document.iform.radacct_enable.disabled = 0;
			document.iform.radiusserver.disabled = 0;
			document.iform.radiussecret.disabled = 0;
		} else {
			document.iform.radacct_enable.disabled = 1;
			document.iform.radiusserver.disabled = 1;
			document.iform.radiussecret.disabled = 1;
		}
	} else {
		document.iform.remoteip.disabled = 1;
		document.iform.localip.disabled = 1;
		document.iform.req128.disabled = 1;
		document.iform.radiusenable.disabled = 1;
		document.iform.radacct_enable.disabled = 1;
		document.iform.radiusserver.disabled = 1;
		document.iform.radiussecret.disabled = 1;
	}
	if ((get_radio_value(document.iform.mode) == "redir") || enable_over) {
		document.iform.redir.disabled = 0;
	} else {
		document.iform.redir.disabled = 1;
	}
}
//-->
</script>
<form action="vpn_pptp.php" method="post" name="iform" id="iform">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
  <ul id="tabnav">
    <li class="tabact">Configuration</li>
    <li class="tabinact"><a href="vpn_pptp_users.php">Users</a></li>
  </ul>
  </td></tr>
  <tr> 
    <td class="tabcont">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr> 
                  <td width="22%" valign="top" class="vtable">&nbsp;</td>
                  <td width="78%" class="vtable"> 
                    <input name="mode" type="radio" onclick="enable_change(false)" value="off"
				  	<?php if (($pconfig['mode'] != "server") && ($pconfig['mode'] != "redir")) echo "checked";?>>
                    Off</td>
                <tr> 
                  <td width="22%" valign="top" class="vtable">&nbsp;</td>
                  <td width="78%" class="vtable">
<input type="radio" name="mode" value="redir" onclick="enable_change(false)" <?php if ($pconfig['mode'] == "redir") echo "checked"; ?>>
                    Redirect incoming PPTP connections to:</td>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">PPTP redirection</td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="redir" type="text" class="formfld" id="redir" size="20" value="<?=htmlspecialchars($pconfig['redir']);?>"> 
                    <br>
                    Enter the IP address of a host which will accept incoming 
                    PPTP connections.</td>
                <tr> 
                  <td width="22%" valign="top" class="vtable">&nbsp;</td>
                  <td width="78%" class="vtable">
<input type="radio" name="mode" value="server" onclick="enable_change(false)" <?php if ($pconfig['mode'] == "server") echo "checked"; ?>>
                    Enable PPTP server</td>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Max. concurrent 
                    connections</td>
                  <td width="78%" class="vtable"> 
                    <?=$g['n_pptp_units'];?>
                  </td>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Server address</td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="localip" type="text" class="formfld" id="localip" size="20" value="<?=htmlspecialchars($pconfig['localip']);?>"> 
                    <br>
                    Enter the IP address the PPTP server should use on its side 
                    for all clients.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Remote address 
                    range</td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="remoteip" type="text" class="formfld" id="remoteip" size="20" value="<?=htmlspecialchars($pconfig['remoteip']);?>">
                    / 
                    <?=$g['pptp_subnet'];?>
                    <br>
                    Specify the starting address for the client IP address subnet.<br>
                    The PPTP server will assign 
                    <?=$g['n_pptp_units'];?>
                    addresses, starting at the address entered above, to clients.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">RADIUS</td>
                  <td width="78%" class="vtable"> 
                      <input name="radiusenable" type="checkbox" id="radiusenable" onclick="enable_change(false)" value="yes" <?php if ($pconfig['radiusenable']) echo "checked"; ?>>
                      <strong>Use a RADIUS server for authentication<br>
                      </strong>When set, all users will be authenticated using 
                      the RADIUS server specified below. The local user database 
                      will not be used.<br>
                      <br>
                      <input name="radacct_enable" type="checkbox" id="radacct_enable" onclick="enable_change(false)" value="yes" <?php if ($pconfig['radacct_enable']) echo "checked"; ?>>
                      <strong>Enable RADIUS accounting <br>
                      </strong>Sends accounting packets to the RADIUS server.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">RADIUS server </td>
                  <td width="78%" class="vtable">
                      <input name="radiusserver" type="text" class="formfld" id="radiusserver" size="20" value="<?=htmlspecialchars($pconfig['radiusserver']);?>">
                      <br>
                      Enter the IP address of the RADIUS server.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">RADIUS shared secret</td>
                  <td width="78%" valign="top" class="vtable">
                      <input name="radiussecret" type="password" class="formfld" id="radiussecret" size="20" value="<?=htmlspecialchars($pconfig['radiussecret']);?>">
                      <br>
                      Enter the shared secret that will be used to authenticate 
                      to the RADIUS server.</td>
                </tr>
                <tr> 
                  <td height="16" colspan="2" valign="top"></td>
                </tr>
                <tr> 
                  <td width="22%" valign="middle">&nbsp;</td>
                  <td width="78%" class="vtable"> 
                    <input name="req128" type="checkbox" id="req128" value="yes" <?php if ($pconfig['req128']) echo "checked"; ?>> 
                    <strong>Require 128-bit encryption</strong><br>
                    When set, 128-bit encryption will be accepted. Otherwise, 
                    40-bit and 56-bit encryption will be accepted, too. Note that 
                    encryption will always be forced on PPTP connections (i.e. 
                    unencrypted connections will not be accepted).</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)"> 
                  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"><span class="vexpl"><span class="red"><strong>Note:<br>
                    </strong></span>don't forget to add a firewall rule to permit 
                    traffic from PPTP clients!</span></td>
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
