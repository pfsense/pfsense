#!/usr/local/bin/php
<?php 
/*
	vpn_openvpn_crl.php

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
if (!is_array($config['ovpn']['server']['crl']))
	$config['ovpn']['server']['crl'] =  array();

$ovpncrl = &$config['ovpn']['server']['crl'];

$id = $_GET['id'];
if (isset($_POST['id']))
        $id = $_POST['id'];


if ($_POST['apply']) {
	$retval = 0;
	$retval = ovpn_server_crl_add();

	/* remove dirty flag */
	unlink_if_exists($d_ovpncrldirty_path);

	$savemsg = get_std_save_message($retval);	
}

if ($_GET['act'] == "del") {
	if ($ovpncrl[$id]) {
		$ovpnent = $ovpncrl[$id];

		unset($ovpncrl[$id]);
		write_config();

		/* Remove crl file */
		ovpn_server_crl_del($ovpnent['crlname']);

		/* we should send a SIGUSR1 to openvpn daemon */
		touch($d_ovpncrldirty_path);

		header("Location: vpn_openvpn_crl.php");
		exit;
	}
}

$pgtitle = "VPN: OpenVPN";
include("head.inc");

?>

<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if (file_exists($d_sysrebootreqd_path) && !file_exists($d_ovpncrldirty_path)) print_info_box(get_std_save_message(0)); ?>
<form action="vpn_openvpn_crl.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<?php if (file_exists($d_ovpncrldirty_path)): ?><p>
<?php print_info_box_np("OpenVPN CRL files have been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<input name="apply" type="submit" class="formbtn" id="apply" value="Apply changes"></p>
<?php endif; ?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
  <ul id="tabnav">	        
	<li class="tabinact"><a href="vpn_openvpn_srv.php">Server</a></li>
	<li class="tabinact"><a href="vpn_openvpn_cli.php">Client</a></li>
	<li class="tabinact"><a href="vpn_openvpn_ccd.php">Client-specific Configuration</a></li>
	<li class="tabact">CRL</li>
  </ul>
  </td></tr>
  <tr>
  <td class="tabcont">
  <strong><span class="red">WARNING: This feature is experimental and modifies your optional interface configuration.
  Backup your configuration before using OpenVPN, and restore it before upgrading.<br>
  &nbsp;<br>
  </span></strong>
    <table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
	  <td width="40%" class="listhdrr">CRL name</td>
	  <td width="50%" class="listhdr">Description</td>
	  <td width="10%" class="list"></td>
	</tr>
	<?php $i = 0; foreach ($ovpncrl as $crl):

		if (!isset($crl['enable'])) {
			$spans = "<span class=\"gray\">";
			$spane = "</span>";
		} else {
			$spans = $spane = "";
		}
	?>
	
	<tr>
	  <td class="listlr"><?=$spans;?>
		<?= $crl['crlname'];?>
	  <?=$spane;?></td>
	  <td class="listbg"><?=$spans;?>
		<?= htmlspecialchars($crl['descr']);?>&nbsp;
	  <?=$spane;?></td>
	  <td valign="middle" nowrap class="list"><a href="vpn_openvpn_crl_edit.php?id=<?=$i;?>"><img src="e.gif" title="edit CRL file" width="17" height="17" border="0"></a>
		&nbsp;<a href="vpn_openvpn_crl.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this CRL file?')"><img src="x.gif" title="delete CRL file" width="17" height="17" border="0"></a></td>
	</tr>
  	<?php $i++; endforeach; ?>
	<tr> 
	  <td class="list" colspan="2">&nbsp;</td>
	  <td class="list"><a href="vpn_openvpn_crl_edit.php"><img src="plus.gif" title="add CRL file" width="17" height="17" border="0"></a></td>
	</tr>
    </table><br>
    <span class="vexpl">
      <span class="red"><strong>Note:</strong></span><br>
	A  CRL  (certificate  revocation list) is used when a particular
	key is compromised but when the overall PKI is still intact.<br>
	<br>
	Suppose you had a PKI consisting of a CA, root certificate,  and
	a number of client certificates.  Suppose a laptop computer
	containing a client key and certificate was stolen.  By adding  the
	stolen certificate to the CRL file, you could reject any connection
	which attempts to use it, while preserving the overall
	integrity of the PKI.<br>
	<br>
	The  only  time when it would be necessary to rebuild the entire
	PKI from scratch would be if the root certificate key itself was
	compromised.
    </span>
  </td>
</tr>
</table>
</form>
<?php include("fend.inc"); ?>
