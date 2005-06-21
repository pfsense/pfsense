#!/usr/local/bin/php
<?php 
/*
	vpn_openvpn_cli.php

	Copyright (C) 2004 Peter Curran (peter@closeconsultants.com).
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

$pgtitle = array("VPN", "OpenVPN");
require("guiconfig.inc");
require_once("openvpn.inc");

if (!is_array($config['ovpn']))
	$config['ovpn'] = array();
if (!is_array($config['ovpn']['client'])){
	$config['ovpn']['client'] =  array();
	$config['ovpn']['client']['tunnel'] =  array();
}

$ovpncli =& $config['ovpn']['client']['tunnel'];

if ($_POST['apply']) {
		$retval = 0;
		if (file_exists($d_sysrebootreqd_path)) {
			/* Rewrite interface definitions */
			$retval = ovpn_client_iface();
		}
		else{
			ovpn_lock();
			$retval = ovpn_config_client();
			ovpn_unlock();
		}
		if (file_exists($d_ovpnclidirty_path))
			unlink($d_ovpnclidirty_path);
		$savemsg = get_std_save_message($retval);	
}

if ($_GET['act'] == "del") {
	if ($ovpncli[$_GET['id']]) {
		unset($ovpncli[$_GET['id']]);
		write_config();
		ovpn_client_kill($_GET['id']);
		touch($d_ovpnclidirty_path);
		header("Location: vpn_openvpn_cli.php");
		exit;
	}
}
?>
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if (file_exists($d_sysrebootreqd_path) && !file_exists($d_ovpnclidirty_path)) print_info_box(get_std_save_message(0)); ?>
<form action="vpn_openvpn_cli.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<?php if (file_exists($d_ovpnclidirty_path)): ?><p>
<?php print_info_box_np("The OpenVPN client configuration has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<input name="apply" type="submit" class="formbtn" id="apply" value="Apply changes"></p>
<?php endif; ?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
  <ul id="tabnav">	        
	<li class="tabinact1"><a href="vpn_openvpn.php">Server</a></li>
	<li class="tabact">Client</li>
  </ul>
  </td></tr>
  <tr>
  <td class="tabcont">
  <strong><span class="red">WARNING: This feature is experimental and modifies your optional interface configuration.
  Backup your configuration before using OpenVPN, and restore it before upgrading.<br>
&nbsp;  <br>
    </span></strong>
    <table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
	  <td width="10%" class="listhdrr">Interface</td>
	  <td width="30%" class="listhdrr">Server address</td>
	  <td width="10%" class="listhdrr" align="middle">Version</td>
	  <td width="40%" class="listhdr">Description</td>
	  <td width="10%" class="list"></td>
	</tr>
	
	<?php $i = 0; foreach ($ovpncli as $client):
					if (!isset($client['enable'])) {
						$spans = "<span class=\"gray\">";
						$spane = "</span>";
					} else {
						$spans = $spane = "";
					}
	?>
	
	<tr>
	  <td class="listlr"><?=$spans;?>
		<?= $client['if'].":".$client['cport'];?>	
	  <?=$spane;?></td>
	  <td class="listr"><?=$spans;?>
		<?= $client['saddr'].":".$client['sport'];?>
	  <?=$spane;?></td>
	  <td align="middle" class="listr"><?=$spans;?>
	  	<?= $client['ver'];?>
	  <?=$spane;?></td>
	   <td class="listbg"><?=$spans;?>
	  	<?= $client['descr'];?>
	  <?=$spane;?></td>
	  <td valign="middle" nowrap class="list"> <a href="vpn_openvpn_cli_edit.php?id=<?=$i;?>"><img src="e.gif" title="edit client configuration" width="17" height="17" border="0"></a>
		 &nbsp;<a href="vpn_openvpn_cli.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this client configuration?')"><img src="x.gif" title="delete client configuration" width="17" height="17" border="0"></a></td>
	</tr>
  	<?php $i++; endforeach; ?>
	<tr> 
	  <td class="list" colspan="4">&nbsp;</td>
	  <td class="list"> <a href="vpn_openvpn_cli_edit.php"><img src="plus.gif" title="add client configuration" width="17" height="17" border="0"></a></td>
	</tr>
    </table>
  </td>
</tr>
</table>
</form>
<?php include("fend.inc"); ?>
