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

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

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
	if ($ovpncli[$id]) {
		$ovpnent = $ovpncli[$id];
		unset($ovpncli[$id]);

		/* Kill running processes */
		/* Remove old certs & keys */
		ovpn_client_kill($ovpnent['if']);

		/* Remove interface from list of optional interfaces */
		ovpn_client_iface_del($ovpnent['if']);

		write_config();
		touch($d_sysrebootreqd_path);
		header("Location: vpn_openvpn_cli.php");
		exit;
	}
}
$pgtitle = "VPN: OpenVPN";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if (file_exists($d_sysrebootreqd_path) && !file_exists($d_ovpnclidirty_path)) print_info_box(get_std_save_message(0)); ?>
<form action="vpn_openvpn_cli.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<?php if (file_exists($d_ovpnclidirty_path)): ?><p>
<?php print_info_box_np("The OpenVPN client configuration has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<input name="apply" type="submit" class="formbtn" id="apply" value="Apply changes"></p>
<?php endif; ?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
<?php
        $tab_array = array();
        $tab_array[] = array("Server", false, "vpn_openvpn.php");
        $tab_array[] = array("Client", true, "vpn_openvpn.php");
        display_top_tabs($tab_array);
?>
  </td></tr>
  <tr>
  <td>
    <div id="mainarea">
    <table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0">
  <tr>
  <td colspan="6">
  <strong><span class="red">WARNING: This feature is experimental and modifies your optional interface configuration.
  Backup your configuration before using OpenVPN, and restore it before upgrading.<br>
&nbsp;  <br>
    </span></strong>
	<tr>
	  <td width="10%" class="listhdrr">Interface</td>
	  <td width="10%" class="listhdrr">Protocol</td>
	  <td width="15%" class="listhdrr">Socket</td>
	  <td width="15%" class="listhdrr">Server address</td>
	  <td width="5%" class="listhdrr" align="middle">Version</td>
	  <td width="35%" class="listhdr">Description</td>
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
		<?= $client['if'];?>	
	  <?=$spane;?></td>
	  <td class="listr"><?=$spans;?>
		<?= strtoupper($client['proto']);?>     
          <?=$spane;?></td>
	  <td class="listr"><?=$spans;?>
		<?= "0.0.0.0:" . $client['port'];?>	
	  <?=$spane;?></td>
	  <td class="listr"><?=$spans;?>
		<?= $client['saddr'].":".$client['sport'];?>
	  <?=$spane;?></td>
	  <td align="middle" class="listr"><?=$spans;?>
	  	<?= $client['ver'];?>
	  <?=$spane;?></td>
	   <td class="listbg"><?=$spans;?>
	  	<?= htmlspecialchars($client['descr']);?>&nbsp;
	  <?=$spane;?></td>
	  <td valign="middle" nowrap class="list"> <a href="vpn_openvpn_cli_edit.php?id=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" title="edit client configuration" width="17" height="17" border="0"></a>
		 &nbsp;<a href="vpn_openvpn_cli.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this client configuration?')"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" title="delete client configuration" width="17" height="17" border="0"></a></td>
	</tr>
  	<?php $i++; endforeach; ?>
	<tr> 
	  <td class="list" colspan="6">&nbsp;</td>
	  <td class="list"> <a href="vpn_openvpn_cli_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="add client configuration" width="17" height="17" border="0"></a></td>
	</tr>
    </table>
    </div>
  </td>
</tr>
</table>
</form>
<?php include("fend.inc"); ?>
