#!/usr/local/bin/php
<?php 
/*
	interfaces_lan.php
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

$lancfg = &$config['interfaces']['lan'];
$optcfg = &$config['interfaces']['lan'];
$pconfig['ipaddr'] = $config['interfaces']['lan']['ipaddr'];
$pconfig['subnet'] = $config['interfaces']['lan']['subnet'];

/* Wireless interface? */
if (isset($optcfg['wireless'])) {
	require("interfaces_wlan.inc");
	wireless_config_init();
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "ipaddr subnet");
	$reqdfieldsn = explode(",", "IP address,Subnet bit count");
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
	if (($_POST['ipaddr'] && !is_ipaddr($_POST['ipaddr']))) {
		$input_errors[] = "A valid IP address must be specified.";
	}
	if (($_POST['subnet'] && !is_numeric($_POST['subnet']))) {
		$input_errors[] = "A valid subnet bit count must be specified.";
	}
	
	/* Wireless interface? */
	if (isset($optcfg['wireless'])) {
		$wi_input_errors = wireless_config_post();
		if ($wi_input_errors) {
			$input_errors = array_merge($input_errors, $wi_input_errors);
		}
	}

	if (!$input_errors) {
		$config['interfaces']['lan']['ipaddr'] = $_POST['ipaddr'];
		$config['interfaces']['lan']['subnet'] = $_POST['subnet'];
		
		$dhcpd_was_enabled = 0;
		if (isset($config['dhcpd']['enable'])) {
			unset($config['dhcpd']['enable']);
			$dhcpd_was_enabled = 1;
		}
			
		write_config();
		touch($d_sysrebootreqd_path);
		
		$savemsg = get_std_save_message(0);
		
		if ($dhcpd_was_enabled)
			$savemsg .= "<br>Note that the DHCP server has been disabled.<br>Please review its configuration " .
				"and enable it again prior to rebooting.";
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Interfaces: LAN");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
<script language="JavaScript">
<!--
function gen_bits(ipaddr) {
    if (ipaddr.search(/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/) != -1) {
        var adr = ipaddr.split(/\./);
        if (adr[0] > 255 || adr[1] > 255 || adr[2] > 255 || adr[3] > 255)
            return "";
        if (adr[0] == 0 && adr[1] == 0 && adr[2] == 0 && adr[3] == 0)
            return "";
		
		if (adr[0] <= 127)
			return "8";
		else if (adr[0] <= 191)
			return "16";
		else
			return "24";
    }
    else
        return "";
}
function ipaddr_change() {
	document.iform.subnet.value = gen_bits(document.iform.ipaddr.value);
}
// -->
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Interfaces: LAN</p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
            <form action="interfaces_lan.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">IP address</td>
                  <td width="78%" class="vtable"> 
                    <input name="ipaddr" type="text" class="formfld" id="hostname" size="20" value="<?=htmlspecialchars($pconfig['ipaddr']);?>" onchange="ipaddr_change()">
                    / 
                    <select name="subnet" class="formfld" id="subnet">
                      <?php for ($i = 31; $i > 0; $i--): ?>
                      <option value="<?=$i;?>" <?php if ($i == $pconfig['subnet']) echo "selected"; ?>>
                      <?=$i;?>
                      </option>
                      <?php endfor; ?>
                    </select></td>
                </tr>
				<?php /* Wireless interface? */
				if (isset($optcfg['wireless']))
					wireless_config_print();
				?>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="Save"> 
                  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"><span class="vexpl"><span class="red"><strong>Warning:<br>
                    </strong></span>after you click &quot;Save&quot;, you must 
                    reboot your firewall for changes to take effect. You may also 
                    have to do one or more of the following steps before you can 
                    access your firewall again: 
                    <ul>
                      <li>change the IP address of your computer</li>
                      <li>renew its DHCP lease</li>
                      <li>access the webGUI with the new IP address</li>
                    </ul>
                    </span></td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
