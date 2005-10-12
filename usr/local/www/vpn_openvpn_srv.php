#!/usr/local/bin/php
<?php 
/*
	vpn_openvpn_srv.php

	Copyright (C) 2004 Peter Curran (peter@closeconsultants.com).
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

$ovpnsrv = &$config['ovpn']['server']['tunnel'];

$id = $_GET['id'];
if (isset($_POST['id']))
        $id = $_POST['id'];


if ($_POST['apply']) {
		$retval = 0;
		if (file_exists($d_sysrebootreqd_path)) {
			/* Rewrite interface definitions */
			$retval = ovpn_server_iface();
		} else {
			ovpn_lock();
			$retval = ovpn_server_iface();
			$retval = ovpn_config_server(false);
			ovpn_unlock();
		}
		if (file_exists($d_ovpnsrvdirty_path))
			unlink($d_ovpnsrvdirty_path);
		$savemsg = get_std_save_message($retval);	
}

if ($_GET['act'] == "del") {
	if ($ovpnsrv[$id]) {
		$ovpnent = $ovpnsrv[$id];
		unset($ovpnsrv[$id]);
									      
		/* Kill running processes */
		ovpn_server_kill($ovpnent['tun_iface']);

		/* Remove old certs & keys */
		ovpn_server_certs_del($ovpnent['tun_iface']);

		/* Remove interface from list of optional interfaces */
		ovpn_server_iface_del($ovpnent['tun_iface']);

		write_config();
		//touch($d_sysrebootreqd_path);
		header("Location: vpn_openvpn_srv.php");
		exit;
	}
}

$pgtitle = "VPN: OpenVPN";
include("head.inc");

?>

<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if (file_exists($d_sysrebootreqd_path) && !file_exists($d_ovpnsrvdirty_path)) print_info_box(get_std_save_message(0)); ?>
<form action="vpn_openvpn_srv.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<?php if (file_exists($d_ovpnsrvdirty_path)): ?><p>
<?php print_info_box_np("The OpenVPN server configuration has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<input name="apply" type="submit" class="formbtn" id="apply" value="Apply changes"></p>
<?php endif; ?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
  <ul id="tabnav">	        
	<li class="tabact">Server</li>
	<li class="tabinact"><a href="vpn_openvpn_cli.php">Client</a></li>
	<li class="tabinact"><a href="vpn_openvpn_ccd.php">Client-specific Configuration</a></li>
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
	  <td width="5%" class="listhdrr">Interface</td>
	  <td width="5%" class="listhdrr">Protocol</td>
	  <td width="5%" class="listhdrr">Socket</td>
	  <td width="25%" class="listhdrr">IP Block</td>
	  <td width="15%" class="listhdrr">Crypto</td>
	  <td width="35%" class="listhdr">Description</td>
	  <td width="10%" class="list"></td>
	</tr>
	
	<?php $i = 0; foreach ($ovpnsrv as $server):
					if (!isset($server['enable'])) {
						$spans = "<span class=\"gray\">";
						$spane = "</span>";
					} else {
						$spans = $spane = "";
					}
					
		if ($server['bind_iface'] == 'all')
			$ipaddr = "0.0.0.0";
		else
			$ipaddr = ovpn_get_ip($server['bind_iface']);
	?>
	
	<tr>
	  <td class="listlr"><?=$spans;?>
		<?php	if ($interface = ovpn_get_opt_interface($server['tun_iface']))
				$iface = $config['interfaces'][$interface]['descr'];
			else $iface = strtoupper($server['tun_iface']);?>
		<?= $iface;?>
	  <?=$spane;?></td>
	  <td class="listr"><?=$spans;?>
		<?= strtoupper($server['proto']);?>	
	  <?=$spane;?></td>
	  <td class="listr"><?=$spans;?>
		<?= $ipaddr.":".$server['port'];?>
	  <?=$spane;?></td>
	  <td nowrap class="listr"><?=$spans;?>
		<?php	if ($server['authentication_method'] == "pre_shared_key") {
				if ($server['type'] == "tun") {
					$ipblock = $server['lipaddr'] . " / " . $server['ripaddr'];
				} else {
					$ipblock = $server['lipaddr'] . "/" . $server['netmask'];
				}
			} else if (!$server['bridge'])
				$ipblock = $server['ipblock'] . "/" . $server['prefix'];
			else if ($server['range_from'])
				$ipblock = $server['range_from'] . " - " . $server['range_to'];
			else
				$ipblock = "--";?>
		<?= $ipblock;?>	
	  <?=$spane;?></td>
	  <td class="listr"><?=$spans;?>
		<?= $server['crypto'];?>	
	  <?=$spane;?></td>
	   <td class="listbg"><?=$spans;?>
	  	<?= htmlspecialchars($server['descr']);?>&nbsp;
	  <?=$spane;?></td>
	  <td valign="middle" nowrap class="list"> <a href="vpn_openvpn_srv_edit.php?id=<?=$i;?>"><img src="e.gif" title="edit server configuration" width="17" height="17" border="0"></a>
		 &nbsp;<a href="vpn_openvpn_srv.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this server configuration?')"><img src="x.gif" title="delete server configuration" width="17" height="17" border="0"></a></td>
	</tr>
  	<?php $i++; endforeach; ?>
	<tr> 
	  <td class="list" colspan="6">&nbsp;</td>
	  <td class="list"> <a href="vpn_openvpn_srv_edit.php"><img src="plus.gif" title="add server configuration" width="17" height="17" border="0"></a></td>
	</tr>
    </table>
  </td>
</tr>
</table>
</form>
<?php include("fend.inc"); ?>
