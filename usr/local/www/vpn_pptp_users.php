#!/usr/local/bin/php
<?php
/*
	vpn_pptp_users.php
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

if (!is_array($config['pptpd']['user'])) {
	$config['pptpd']['user'] = array();
}
pptpd_users_sort();
$a_secret = &$config['pptpd']['user'];

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;
		if (!file_exists($d_sysrebootreqd_path)) {
			config_lock();
			$retval = vpn_pptpd_configure();
			config_unlock();
		}
		$savemsg = get_std_save_message($retval);
		if ($retval == 0) {
			if (file_exists($d_pptpuserdirty_path))
				unlink($d_pptpuserdirty_path);
		}
	}
}

if ($_GET['act'] == "del") {
	if ($a_secret[$_GET['id']]) {
		unset($a_secret[$_GET['id']]);
		write_config();
		touch($d_pptpuserdirty_path);
		header("Location: vpn_pptp_users.php");
		exit;
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("VPN: PPTP: Users");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">VPN: PPTP: Users</p>
<form action="vpn_pptp_users.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (isset($config['pptpd']['radius']['enable']))
	print_info_box("Warning: RADIUS is enabled. The local user database will not be used."); ?>
<?php if (file_exists($d_pptpuserdirty_path)): ?><p>
<?php print_info_box_np("The PPTP user list has been modified.<br>You must apply the changes in order for them to take effect.<br><b>Warning: this will terminate all current PPTP sessions!</b>");?><br>
<input name="apply" type="submit" class="formbtn" id="apply" value="Apply changes"></p>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
  <ul id="tabnav">
    <li class="tabinact"><a href="vpn_pptp.php">Configuration</a></li>
    <li class="tabact">Users</li>
  </ul>
  </td></tr>
  <tr> 
    <td colspan="3" class="tabcont">
              <table width="80%" border="0" cellpadding="0" cellspacing="0">
                <tr> 
                  <td class="listhdrr">Username</td>
                  <td class="listhdr">IP address</td>
                  <td class="list"></td>
				</tr>
			  <?php $i = 0; foreach ($a_secret as $secretent): ?>
                <tr> 
                  <td class="listlr">
                    <?=htmlspecialchars($secretent['name']);?>
                  </td>
                  <td class="listr">
                    <?=htmlspecialchars($secretent['ip']);?>&nbsp;
                  </td>
                  <td class="list" nowrap> <a href="vpn_pptp_users_edit.php?id=<?=$i;?>"><img src="e.gif" width="17" height="17" border="0"></a>
                     &nbsp;<a href="vpn_pptp_users.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this user?')"><img src="x.gif" width="17" height="17" border="0"></a></td>
				</tr>
			  <?php $i++; endforeach; ?>
                <tr> 
                  <td class="list" colspan="2"></td>
                  <td class="list"> <a href="vpn_pptp_users_edit.php"><img src="plus.gif" width="17" height="17" border="0"></a></td>
				</tr>
              </table>
			</td>
	</tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
