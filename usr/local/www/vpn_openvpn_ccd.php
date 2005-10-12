#!/usr/local/bin/php
<?php 
/*
	vpn_openvpn_ccd.php

	Copyright (C) 2005 Peter Allgeyer (allgeyer@web.de).
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
require_once("openvpn.inc");

if (!is_array($config['ovpn']))
	$config['ovpn'] = array();
if (!is_array($config['ovpn']['server'])){
	$config['ovpn']['server'] =  array();
	$config['ovpn']['server']['tunnel'] =  array();
}
if (!is_array($config['ovpn']['server']['ccd']))
	$config['ovpn']['server']['ccd'] =  array();

$ovpnccd = &$config['ovpn']['server']['ccd'];

$id = $_GET['id'];
if (isset($_POST['id']))
        $id = $_POST['id'];


if ($_POST['apply']) {
	$retval = 0;

	$retval = ovpn_server_ccd_add();

#
#	/* should we send a SIGUSR1 to openvpn daemon? */
#	foreach ($config['ovpn']['server']['tunnel'] as $id => $server) {
#		/* get tunnel interface */
#		$tun = $server['tun_iface'];
#
#		/* send SIGUSR1 to running openvpn daemon */
#		if (isset($server['enable']))
#			sigkillbypid($g['varrun_path']."/ovpn_srv_{$tun}.pid", "SIGUSR1");
#	}
#

	/* remove dirty flag */
	unlink_if_exists($d_ovpnccddirty_path);

	$savemsg = get_std_save_message($retval);	
}

if ($_GET['act'] == "del") {
	if ($ovpnccd[$id]) {
		$ovpnent = $ovpnccd[$id];

		unset($ovpnccd[$id]);
		write_config();

		/* Remove config files */
		ovpn_server_ccd_del($ovpnent['cn']);

		header("Location: vpn_openvpn_ccd.php");
		exit;
	}

} else if ($_GET['act'] == "toggle") {
	if ($ovpnccd[$_GET['id']]) {
		$ovpnccd[$_GET['id']]['enable'] = !isset($ovpnccd[$_GET['id']]['enable']);
		write_config();
		touch($d_ovpnccddirty_path);
		header("Location: vpn_openvpn_ccd.php");
		exit;
	}
}

$pgtitle = "VPN: OpenVPN";
include("head.inc");

?>

<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if (file_exists($d_sysrebootreqd_path) && !file_exists($d_ovpnccddirty_path)) print_info_box(get_std_save_message(0)); ?>
<form action="vpn_openvpn_ccd.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<?php if (file_exists($d_ovpnccddirty_path)): ?><p>
<?php print_info_box_np("OpenVPN client-specific configuration options have been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<input name="apply" type="submit" class="formbtn" id="apply" value="Apply changes"></p>
<?php endif; ?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
  <ul id="tabnav">	        
	<li class="tabinact"><a href="vpn_openvpn_srv.php">Server</a></li>
	<li class="tabinact"><a href="vpn_openvpn_cli.php">Client</a></li>
	<li class="tabact">Client-specific Configuration</li>
	<li class="tabinact"><a href="vpn_openvpn_crl.php">CRL</a></li>
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
	  <td width="5%" class="list">&nbsp;</td>
	  <td width="38%" class="listhdrr">Common Name</td>
	  <td width="47%" class="listhdr">Description</td>
	  <td width="10%" class="list"></td>
	</tr>
	<?php $i = 0; foreach ($ovpnccd as $ccd):

		if (isset($ccd['disable']))
			$iconfn = "block";
		else
			$iconfn = "pass";

		if (!isset($ccd['enable'])) {
			$spans = "<span class=\"gray\">";
			$spane = "</span>";
			$iconfn .= "_d";
		} else {
			$spans = $spane = "";
		}
	?>
	
	<tr>
	  <td class="listt" align="center">
	  	<a href="?act=toggle&id=<?=$i;?>"><img src="<?=$iconfn;?>.gif"
		width="11" height="11" border="0" title="click to toggle enabled/disabled status"></a>
	  </td>
	  <td class="listlr"><?=$spans;?>
		<?= $ccd['cn'];?>
	  <?=$spane;?></td>
	  <td class="listbg"><?=$spans;?>
		<?= htmlspecialchars($ccd['descr']);?>&nbsp;
	  <?=$spane;?></td>
	  <td valign="middle" nowrap class="list"><a href="vpn_openvpn_ccd_edit.php?id=<?=$i;?>"><img src="e.gif" title="edit client-specific configuration" width="17" height="17" border="0"></a>
		&nbsp;<a href="vpn_openvpn_ccd.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this client-specific configuration?')"><img src="x.gif" title="delete client-specific configuration" width="17" height="17" border="0"></a></td>
	</tr>
  	<?php $i++; endforeach; ?>
	<tr> 
	  <td class="list" colspan="3">&nbsp;</td>
	  <td class="list"><a href="vpn_openvpn_ccd_edit.php"><img src="plus.gif" title="add client-specific configuration" width="17" height="17" border="0"></a></td>
	</tr>
    </table>
    <table border="0" cellspacing="0" cellpadding="0">
      <tr> 
	<td width="16"><img src="pass.gif" width="11" height="11"></td>
	  <td>pass</td>
	  <td width="14"></td>
	  <td width="16"><img src="block.gif" width="11" height="11"></td>
	  <td>block</td>
	</tr>
	<tr>
	  <td colspan="5" height="4"></td>
	</tr>
	<tr> 
	  <td><img src="pass_d.gif" width="11" height="11"></td>
	  <td>pass (disabled)</td>
	  <td></td>
	  <td><img src="block_d.gif" width="11" height="11"></td>
	  <td>block (disabled)</td>
	</tr>
    </table>
  </td>
</tr>
</table>
</form>
<?php include("fend.inc"); ?>
