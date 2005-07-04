#!/usr/local/bin/php
<?php
/* $Id$ */
/*
	services_captiveportal_mac.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2004 Dinesh Nair <dinesh@alphaque.com>
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

if (!is_array($config['captiveportal']['passthrumac']))
	$config['captiveportal']['passthrumac'] = array();

passthrumacs_sort();
$a_passthrumacs = &$config['captiveportal']['passthrumac'] ;

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;
		if (!file_exists($d_sysrebootreqd_path)) {
			$retval = captiveportal_passthrumac_configure();
		}
		$savemsg = get_std_save_message($retval);
		if ($retval == 0) {
			if (file_exists($d_passthrumacsdirty_path)) {
				config_lock();
				unlink($d_passthrumacsdirty_path);
				config_unlock();
			}
		}
	}
}

if ($_GET['act'] == "del") {
	if ($a_passthrumacs[$_GET['id']]) {
		unset($a_passthrumacs[$_GET['id']]);
		write_config();
		touch($d_passthrumacsdirty_path);
		header("Location: services_captiveportal_mac.php");
		exit;
	}
}

$pgtitle = "Services: Captive Portal: MACs";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<form action="services_captiveportal_mac.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_passthrumacsdirty_path)): ?><p>
<?php print_info_box_np("The captive portal MAC address configuration has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
<?php
	$tab_array = array();
	$tab_array[0] = array("Captive portal", false, "services_captiveportal.php");
	$tab_array[1] = array("Pass-through MAC", true, "services_captiveportal_mac.php");
	$tab_array[2] = array("Allowed IP addresses", false, "services_captiveportal_ip.php");
	$tab_array[3] = array("Users", false, "services_captiveportal_users.php");
	display_top_tabs($tab_array);
?>  
  </td></tr>
  <tr>
  <td>
<div id="mainarea">
  <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
	  <td width="30%" class="listhdrr">MAC address</td>
	  <td width="60%" class="listhdr">Description</td>
	  <td width="10%" class="list"></td>
	</tr>
  <?php $i = 0; foreach ($a_passthrumacs as $mac): ?>
	<tr>
	  <td class="listlr" ondblclick="document.location='services_captiveportal_mac_edit.php?id=<?=$i;?>';">
		<?=strtolower($mac['mac']);?>
	  </td>
	  <td class="listbg" ondblclick="document.location='services_captiveportal_mac_edit.php?id=<?=$i;?>';">
		<font color="white"><?=htmlspecialchars($mac['descr']);?>&nbsp;</font>
	  </td>
	  <td valign="middle" nowrap class="list">
            <table border="0" cellspacing="0" cellpadding="1">
              <tr>
                <td valign="middle"><a href="services_captiveportal_mac_edit.php?id=<?=$i;?>"><img src="e.gif" width="17" height="17" border="0"></a></td>
		<td valign="middle"><a href="services_captiveportal_mac.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this host?')"><img src="x.gif" width="17" height="17" border="0"></a></td>
              </tr>
            </table>
          </td>
	</tr>
  <?php $i++; endforeach; ?>
	<tr>
	  <td class="list" colspan="2">&nbsp;</td>
	  <td class="list">
            <table border="0" cellspacing="0" cellpadding="1">
              <tr>
                <td valign="middle"><a href="services_captiveportal_mac_edit.php"><img src="plus.gif" width="17" height="17" border="0"></a></td>
              </tr>
            </table>
          </td>
	</tr>
	<tr>
	<td colspan="2" class="list"><span class="vexpl"><span class="red"><strong>
	Note:<br>
	</strong></span>
	Adding MAC addresses as pass-through MACs  allows them access through the captive portal automatically without being taken to the portal page. Pass-through MACs will however still be disconnected after the captive portal timeout period.</span></td>
	<td class="list">&nbsp;</td>
	</tr>
  </table>
</div>
  </td>
  </tr>
  </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
