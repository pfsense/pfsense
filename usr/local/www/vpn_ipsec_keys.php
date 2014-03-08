<?php
/*
	vpn_ipsec_keys.php
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

##|+PRIV
##|*IDENT=page-vpn-ipsec-listkeys
##|*NAME=VPN: IPsec: Pre-Shared Keys List
##|*DESCR=Allow access to the 'VPN: IPsec: Pre-Shared Keys List' page.
##|*MATCH=vpn_ipsec_keys.php*
##|-PRIV

require("functions.inc");
require("guiconfig.inc");
require_once("ipsec.inc");
require_once("vpn.inc");

if (!is_array($config['ipsec']['mobilekey'])) {
	$config['ipsec']['mobilekey'] = array();
}
ipsec_mobilekey_sort();
$a_secret = &$config['ipsec']['mobilekey'];

$userkeys = array();
foreach ($config['system']['user'] as $id => $user) {
	if (!empty($user['ipsecpsk'])) {
		$k = array();
		$k["ident"] = $user['name'];
		$k["pre-shared-key"] = $user['ipsecpsk'];
		$k["id"] = $id;
		$userkeys[] = $k;
	}
}


if ($_GET['act'] == "del") {
	if ($a_secret[$_GET['id']]) {
		unset($a_secret[$_GET['id']]);
		write_config(gettext("Deleted IPsec Pre-Shared Key"));
		mark_subsystem_dirty('ipsec');
		header("Location: vpn_ipsec_keys.php");
		exit;
	}
}

$pgtitle = gettext("VPN: IPsec: Keys");
$shortcut_section = "ipsec";

include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="vpn_ipsec.php" method="post">
<?php 
if ($savemsg)
	print_info_box($savemsg);
if (is_subsystem_dirty('ipsec'))
	print_info_box_np(gettext("The IPsec tunnel configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));

?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
<?php
	$tab_array = array();
	$tab_array[0] = array(gettext("Tunnels"), false, "vpn_ipsec.php");
	$tab_array[1] = array(gettext("Mobile clients"), false, "vpn_ipsec_mobile.php");
	$tab_array[2] = array(gettext("Pre-Shared Keys"), true, "vpn_ipsec_keys.php");
	display_top_tabs($tab_array);
?>
  </td></tr>
  <tr> 
    <td>
	<div id="mainarea">
              <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr> 
                  <td class="listhdrr"><?=gettext("Identifier"); ?></td>
                  <td class="listhdr"><?=gettext("Pre-Shared Key"); ?></td>
                  <td class="list">
			<table border="0" cellspacing="0" cellpadding="1">
			    <tr>
			        <td width="20" height="17"></td>
				<td><a href="vpn_ipsec_keys_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("add key"); ?>" alt="" width="17" height="17" border="0"/></a></td>
			    </tr>
			</table>
		  </td>
		</tr>
			  <?php $i = 0; foreach ($userkeys as $secretent): ?>
		<tr>
		<td class="listlr gray">
			<?=htmlspecialchars($secretent['ident']);?>
		</td>
		<td class="listr gray">
			<?=htmlspecialchars($secretent['pre-shared-key']);?>
		</td>
		<td class="list" nowrap="nowrap"><a href="system_usermanager.php?act=edit&id=<?=$secretent['id'];?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" title="<?=gettext("edit key"); ?>" alt="" width="17" height="17" border="0"/></a>
		&nbsp;</td>
				</tr>
			  <?php $i++; endforeach; ?>

			  <?php $i = 0; foreach ($a_secret as $secretent): ?>
                <tr> 
                  <td class="listlr">
                    <?=htmlspecialchars($secretent['ident']);?>
                  </td>
                  <td class="listr">
                    <?=htmlspecialchars($secretent['pre-shared-key']);?>
                  </td>
                  <td class="list" nowrap="nowrap"> <a href="vpn_ipsec_keys_edit.php?id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" title="<?=gettext("edit key"); ?>" alt="" width="17" height="17" border="0"/></a>
                     &nbsp;<a href="vpn_ipsec_keys.php?act=del&id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this Pre-Shared Key?"); ?>')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" title="<?=gettext("delete key"); ?>" alt="" width="17" height="17" border="0"/></a></td>
				</tr>
			  <?php $i++; endforeach; ?>
                <tr> 
                  <td class="list" colspan="2"></td>
                  <td class="list">
			<table border="0" cellspacing="0" cellpadding="1">
			    <tr>
			        <td width="20" height="17"></td>
				<td><a href="vpn_ipsec_keys_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("add key"); ?>" alt="" width="17" height="17" border="0"/></a></td>
			    </tr>
			</table>
		  </td>
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
