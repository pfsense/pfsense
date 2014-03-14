<?php
/*
	vpn_pptp_users.php
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
##|*IDENT=page-vpn-vpnpptp-users
##|*NAME=VPN: VPN PPTP: Users page
##|*DESCR=Allow access to the 'VPN: VPN PPTP: Users' page.
##|*MATCH=vpn_pptp_users.php*
##|-PRIV

require("guiconfig.inc");
require_once("vpn.inc");

if (!is_array($config['pptpd']['user'])) {
	$config['pptpd']['user'] = array();
}
$a_secret = &$config['pptpd']['user'];

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;
		$retval = vpn_setup();
		$savemsg = get_std_save_message($retval);
		if ($retval == 0) {
			if (is_subsystem_dirty('pptpusers'))
				clear_subsystem_dirty('pptpusers');
		}
	}
}

if ($_GET['act'] == "del") {
	if ($a_secret[$_GET['id']]) {
		unset($a_secret[$_GET['id']]);
		write_config();
		mark_subsystem_dirty('pptpusers');
		header("Location: vpn_pptp_users.php");
		exit;
	}
}

$pgtitle = array(gettext("VPN"),gettext("VPN PPTP"),gettext("Users"));
$shortcut_section = "pptps";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="vpn_pptp_users.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (isset($config['pptpd']['radius']['enable']))
	print_info_box(gettext("Warning: RADIUS is enabled. The local user database will not be used.")); ?>
<?php if (is_subsystem_dirty('pptpusers')): ?><p>
<?php print_info_box_np(gettext("The PPTP user list has been modified").".<br />".gettext("You must apply the changes in order for them to take effect").".<br /><b>".gettext("Warning: this will terminate all current PPTP sessions")."!</b>");?><br />
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
<?php
	$tab_array = array();
	$tab_array[0] = array(gettext("Configuration"), false, "vpn_pptp.php");
	$tab_array[1] = array(gettext("Users"), true, "vpn_pptp_users.php");
	display_top_tabs($tab_array);
?>    </td></tr>
  <tr> 
	<td>
        <div id="mainarea">
              <table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr> 
                  <td class="listhdrr"><?=gettext("Username");?></td>
                  <td class="listhdr"><?=gettext("IP address");?></td>
                  <td class="list">
			<table border="0" cellspacing="0" cellpadding="1">
			   <tr>
				<td width="17"></td>
				<td><a href="vpn_pptp_users_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("add user");?>" width="17" height="17" border="0"></a></td>
			   </tr>
			</table>
		  </td>
		</tr>
			  <?php $i = 0; foreach ($a_secret as $secretent): ?>
                <tr> 
                  <td class="listlr">
                    <?=htmlspecialchars($secretent['name']);?>
                  </td>
                  <td class="listr">
                    <?=htmlspecialchars($secretent['ip']);?>&nbsp;
                  </td>
                  <td class="list" nowrap> <a href="vpn_pptp_users_edit.php?id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" title="<?=gettext("edit user");?>" width="17" height="17" border="0"></a>
                     &nbsp;<a href="vpn_pptp_users.php?act=del&id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this user?");?>')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" title="<?=gettext("delete user");?>" width="17" height="17" border="0"></a></td>
				</tr>
			  <?php $i++; endforeach; ?>
                <tr> 
                  <td class="list" colspan="2"></td>
                  <td class="list">
			<table border="0" cellspacing="0" cellpadding="1">
			   <tr>
				<td width="17"></td>
				<td><a href="vpn_pptp_users_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("add user");?>" width="17" height="17" border="0"></a></td>
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
