#!/usr/local/bin/php
<?php 
/*
	system_advanced.php
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

$pconfig['filteringbridge_enable'] = isset($config['bridge']['filteringbridge']);
$pconfig['ipv6nat_enable'] = isset($config['diag']['ipv6nat']['enable']);
$pconfig['ipv6nat_ipaddr'] = $config['diag']['ipv6nat']['ipaddr'];
$pconfig['cert'] = base64_decode($config['system']['webgui']['certificate']);
$pconfig['key'] = base64_decode($config['system']['webgui']['private-key']);
$pconfig['disableconsolemenu'] = isset($config['system']['disableconsolemenu']);
$pconfig['disablefirmwarecheck'] = isset($config['system']['disablefirmwarecheck']);
$pconfig['expanddiags'] = isset($config['system']['webgui']['expanddiags']);
if ($g['platform'] == "generic-pc")
	$pconfig['harddiskstandby'] = $config['system']['harddiskstandby'];
$pconfig['noantilockout'] = isset($config['system']['webgui']['noantilockout']);
$pconfig['tcpidletimeout'] = $config['filter']['tcpidletimeout'];

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['ipv6nat_enable'] && !is_ipaddr($_POST['ipv6nat_ipaddr'])) {
		$input_errors[] = "You must specify an IP address to NAT IPv6 packets.";
	}
	if ($_POST['tcpidletimeout'] && !is_numericint($_POST['tcpidletimeout'])) {
		$input_errors[] = "The TCP idle timeout must be an integer.";
	}
	if (($_POST['cert'] && !$_POST['key']) || ($_POST['key'] && !$_POST['cert'])) {
		$input_errors[] = "Certificate and key must always be specified together.";
	} else if ($_POST['cert'] && $_POST['key']) {
		if (!strstr($_POST['cert'], "BEGIN CERTIFICATE") || !strstr($_POST['cert'], "END CERTIFICATE"))
			$input_errors[] = "This certificate does not appear to be valid.";
		if (!strstr($_POST['key'], "BEGIN RSA PRIVATE KEY") || !strstr($_POST['key'], "END RSA PRIVATE KEY"))
			$input_errors[] = "This key does not appear to be valid.";
	}

	if (!$input_errors) {
		$config['bridge']['filteringbridge'] = $_POST['filteringbridge_enable'] ? true : false;
		$config['diag']['ipv6nat']['enable'] = $_POST['ipv6nat_enable'] ? true : false;
		$config['diag']['ipv6nat']['ipaddr'] = $_POST['ipv6nat_ipaddr'];
		$oldcert = $config['system']['webgui']['certificate'];
		$oldkey = $config['system']['webgui']['private-key'];
		$config['system']['webgui']['certificate'] = base64_encode($_POST['cert']);
		$config['system']['webgui']['private-key'] = base64_encode($_POST['key']);
		$config['system']['disableconsolemenu'] = $_POST['disableconsolemenu'] ? true : false;
		$config['system']['disablefirmwarecheck'] = $_POST['disablefirmwarecheck'] ? true : false;
		$config['system']['webgui']['expanddiags'] = $_POST['expanddiags'] ? true : false;
		if ($g['platform'] == "generic-pc") {
			$oldharddiskstandby = $config['system']['harddiskstandby'];
			$config['system']['harddiskstandby'] = $_POST['harddiskstandby'];
		}
		$config['system']['webgui']['noantilockout'] = $_POST['noantilockout'] ? true : false;
		$config['filter']['tcpidletimeout'] = $_POST['tcpidletimeout'];
			
		write_config();
		
		if (($config['system']['webgui']['certificate'] != $oldcert)
				|| ($config['system']['webgui']['private-key'] != $oldkey)) {
			touch($d_sysrebootreqd_path);
		} else if (($g['platform'] == "generic-pc") && ($config['system']['harddiskstandby'] != $oldharddiskstandby)) {
			if (!$config['system']['harddiskstandby']) {
				// Reboot needed to deactivate standby due to a stupid ATA-protocol
				touch($d_sysrebootreqd_path);
				unset($config['system']['harddiskstandby']);
			} else {
				// No need to set the standby-time if a reboot is needed anyway
				system_set_harddisk_standby();
			}
		}
		
		$retval = 0;
		if (!file_exists($d_sysrebootreqd_path)) {
			config_lock();
			$retval = filter_configure();
			$retval |= interfaces_optional_configure();
			config_unlock();
		}
		$savemsg = get_std_save_message($retval);
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("System: Advanced functions");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
<script language="JavaScript">
<!--
function enable_change(enable_over) {
	if (document.iform.ipv6nat_enable.checked || enable_over) {
		document.iform.ipv6nat_ipaddr.disabled = 0;
	} else {
		document.iform.ipv6nat_ipaddr.disabled = 1;
	}
}
// -->
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
      <p class="pgtitle">System: Advanced functions</p>
            <?php if ($input_errors) print_input_errors($input_errors); ?>
            <?php if ($savemsg) print_info_box($savemsg); ?>
            <p><span class="vexpl"><span class="red"><strong>Note: </strong></span>the 
              options on this page are intended for use by advanced users only, 
              and there's <strong>NO</strong> support for them.</span></p>
            <form action="system_advanced.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr> 
                  <td colspan="2" valign="top" class="listtopic">IPv6 tunneling</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">&nbsp;</td>
                  <td width="78%" class="vtable"> 
                    <input name="ipv6nat_enable" type="checkbox" id="ipv6nat_enable" value="yes" <?php if ($pconfig['ipv6nat_enable']) echo "checked"; ?> onclick="enable_change(false)"> 
                    <strong>NAT encapsulated IPv6 packets (IP protocol 41/RFC2893) 
                    to:</strong><br> <br> <input name="ipv6nat_ipaddr" type="text" class="formfld" id="ipv6nat_ipaddr" size="20" value="<?=htmlspecialchars($pconfig['ipv6nat_ipaddr']);?>"> 
                    &nbsp;(IP address)<span class="vexpl"><br>
                    Don't forget to add a firewall rule to permit IPv6 packets!</span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)"> 
                  </td>
                </tr>
                <tr> 
                  <td colspan="2" class="list" height="12"></td>
                </tr>
				<tr> 
                  <td colspan="2" valign="top" class="listtopic">Filtering bridge</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">&nbsp;</td>
                  <td width="78%" class="vtable"> 
                    <input name="filteringbridge_enable" type="checkbox" id="filteringbridge_enable" value="yes" <?php if ($pconfig['filteringbridge_enable']) echo "checked"; ?>>
                    <strong>Enable filtering bridge</strong><span class="vexpl"><br>
                    This will cause bridged packets to pass through the packet 
                    filter in the same way as routed packets do (by default bridged 
                    packets are always passed). If you enable this option, you'll 
                    have to add filter rules to selectively permit traffic from 
                    bridged interfaces.</span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)"> 
                  </td>
                </tr>
                <tr> 
                  <td colspan="2" class="list" height="12"></td>
                </tr>
                <tr> 
                  <td colspan="2" valign="top" class="listtopic">webGUI SSL certificate/key</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">Certificate</td>
                  <td width="78%" class="vtable"> 
                    <textarea name="cert" cols="65" rows="7" id="cert" class="formpre"><?=htmlspecialchars($pconfig['cert']);?></textarea>
                    <br> 
                    Paste a signed certificate in X.509 PEM format here.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">Key</td>
                  <td width="78%" class="vtable"> 
                    <textarea name="key" cols="65" rows="7" id="key" class="formpre"><?=htmlspecialchars($pconfig['key']);?></textarea>
                    <br> 
                    Paste an RSA private key in PEM format here.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)"> 
                  </td>
                </tr>
                <tr> 
                  <td colspan="2" class="list" height="12"></td>
                </tr>
                <tr> 
                  <td colspan="2" valign="top" class="listtopic">Miscellaneous</td>
                </tr>
				<tr> 
                  <td width="22%" valign="top" class="vncell">Console menu </td>
                  <td width="78%" class="vtable"> 
                    <input name="disableconsolemenu" type="checkbox" id="disableconsolemenu" value="yes" <?php if ($pconfig['disableconsolemenu']) echo "checked"; ?>>
                    <strong>Disable console menu</strong><span class="vexpl"><br>
                    Changes to this option will take effect after a reboot.</span></td>
                </tr>
				<tr>
                  <td valign="top" class="vncell">Firmware version check </td>
                  <td class="vtable">
                    <input name="disablefirmwarecheck" type="checkbox" id="disablefirmwarecheck" value="yes" <?php if ($pconfig['disablefirmwarecheck']) echo "checked"; ?>>
                    <strong>Disable firmware version check</strong><span class="vexpl"><br>
    This will cause m0n0wall not to check for newer firmware versions when the <a href="system_firmware.php">System: Firmware</a> page is viewed.</span></td>
			    </tr>
				<tr>
                  <td valign="top" class="vncell">TCP idle timeout </td>
                  <td class="vtable">                    <span class="vexpl">
                    <input name="tcpidletimeout" type="text" class="formfld" id="tcpidletimeout" size="8" value="<?=htmlspecialchars($pconfig['tcpidletimeout']);?>">
                    seconds<br>
    Idle TCP connections will be removed from the state table after no packets have been received for the specified number of seconds. Don't set this too high or your state table could become full of connections that have been improperly shut down. The default is 2.5 hours.</span></td>
			    </tr>
<?php if ($g['platform'] == "generic-pc"): ?>
				<tr> 
                  <td width="22%" valign="top" class="vncell">Hard disk standby time </td>
                  <td width="78%" class="vtable"> 
                    <select name="harddiskstandby" class="formfld">
					<?php
                        /* Values from ATA-2
                           http://www.t13.org/project/d0948r3-ATA-2.pdf
                           Page 66 */
						$sbvals = explode(" ", "0.5,6 1,12 2,24 3,36 4,48 5,60 7.5,90 10,120 15,180 20,240 30,241 60,242");
					?>
                      <option value="" <?php if(!$pconfig['harddiskstandby']) echo('selected');?>>Always on</option>
					<?php
					foreach ($sbvals as $sbval):
						list($min,$val) = explode(",", $sbval); ?>
                      <option value="<?=$val;?>" <?php if($pconfig['harddiskstandby'] == $val) echo('selected');?>><?=$min;?> minutes</option>
					<?php endforeach; ?>
                    </select>
                    <br>
                    Puts the hard disk into standby mode when the selected amount of time after the last
                    access has elapsed. <em>Do not set this for CF cards.</em></td>
				</tr>
<?php endif; ?>
				<tr> 
                  <td width="22%" valign="top" class="vncell">Navigation</td>
                  <td width="78%" class="vtable"> 
                    <input name="expanddiags" type="checkbox" id="expanddiags" value="yes" <?php if ($pconfig['expanddiags']) echo "checked"; ?>>
                    <strong>Keep diagnostics in navigation expanded </strong></td>
                </tr>
				<tr> 
                  <td width="22%" valign="top" class="vncell">webGUI anti-lockout</td>
                  <td width="78%" class="vtable"> 
                    <input name="noantilockout" type="checkbox" id="noantilockout" value="yes" <?php if ($pconfig['noantilockout']) echo "checked"; ?>>
                    <strong>Disable webGUI anti-lockout rule</strong><br>
					By default, access to the webGUI on the LAN interface is always permitted, regardless of the user-defined filter rule set. Enable this feature to control webGUI access (make sure to have a filter rule in place that allows you in, or you will lock yourself out!).<br>
					Hint: 
					the &quot;set LAN IP address&quot; option in the console menu  resets this setting as well.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)"> 
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
