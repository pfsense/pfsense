<?php
/*
	Copyright (C) 2010 Ermal Luci
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
/*
	pfSense_MODULE:	pppoe
*/

##|+PRIV
##|*IDENT=page-services-pppoeserver
##|*NAME=Services: PPPoE Server page
##|*DESCR=Allow access to the 'Services: PPPoE Server' page.
##|*MATCH=vpn_pppoe.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("filter.inc");
require_once("vpn.inc");

if (!is_array($config['pppoes']['pppoe']))
	$config['pppoes']['pppoe'] = array();

$a_pppoes = &$config['pppoes']['pppoe'];

if ($_POST) {
        $pconfig = $_POST;

        if ($_POST['apply']) {
                if (file_exists("{$g['tmp_path']}/.vpn_pppoe.apply")) {
                        $toapplylist = unserialize(file_get_contents("{$g['tmp_path']}/.vpn_pppoe.apply"));
                        foreach ($toapplylist as $pppoeid) {
				if (!is_numeric($pppoeid))
					continue;
				if (is_array($config['pppoes']['pppoe'])) {
					foreach ($config['pppoes']['pppoe'] as $pppoe) {
						if ($pppoe['pppoeid'] == $pppoeid) {
							vpn_pppoe_configure($pppoe);
							break;
						}
					}
				}
                        }
                        @unlink("{$g['tmp_path']}/.vpn_pppoe.apply");
                }
                $retval = 0;
                $retval |= filter_configure();
                $savemsg = get_std_save_message($retval);
                clear_subsystem_dirty('vpnpppoe');
        }
}

if ($_GET['act'] == "del") {
	if ($a_pppoes[$_GET['id']]) {
		if ("{$g['varrun_path']}/pppoe" . $a_pppoes[$_GET['id']]['pppoeid'] . "-vpn.pid")
			killbypid("{$g['varrun_path']}/pppoe" . $a_pppoes[$_GET['id']]['pppoeid'] . "-vpn.pid");
		if (is_dir("{$g['varetc_path']}/pppoe" . $a_pppoes[$_GET['id']]['pppoeid']))
			mwexec("/bin/rm -r {$g['varetc_path']}/pppoe" . $a_pppoes[$_GET['id']]['pppoeid']);
		unset($a_pppoes[$_GET['id']]);
		write_config();
		header("Location: vpn_pppoe.php");
		exit;
	}
}

$pgtitle = array(gettext("VPN"),gettext("PPPoE"));
$shortcut_section = "pppoes";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="vpn_pppoe.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('vpnpppoe')): ?><p>
<?php print_info_box_np(gettext("The PPPoE entry list has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));?>
<?php endif; ?>

<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
<tr>
  <td width="15%" class="listhdrr"><?=gettext("Interface");?></td>
  <td width="10%" class="listhdrr"><?=gettext("Local IP");?></td>
  <td width="25%" class="listhdrr"><?=gettext("Number of users");?></td>
  <td width="25%" class="listhdr"><?=gettext("Description");?></td>
  <td width="5%" class="list">
    <table border="0" cellspacing="0" cellpadding="1">
      <tr>
	<td valign="middle" width="17">&nbsp;</td>
        <td valign="middle"><a href="vpn_pppoe_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="<?=gettext("add a new pppoe instance");?>"></a></td>
      </tr>
    </table>
  </td>
</tr>
	  <?php $i = 0; foreach ($a_pppoes as $pppoe): ?>
<tr>
  <td class="listlr" ondblclick="document.location='vpn_pppoe_edit.php?id=<?=$i;?>';">
    <?=htmlspecialchars(strtoupper($pppoe['interface']));?>
  </td>
  <td class="listlr" ondblclick="document.location='vpn_pppoe_edit.php?id=<?=$i;?>';">
    <?=htmlspecialchars($pppoe['localip']);?>
  </td>
  <td class="listr" ondblclick="document.location='vpn_pppoe_edit.php?id=<?=$i;?>';">
      <?=htmlspecialchars($pppoe['n_pppoe_units']);?>
  </td>
  <td class="listbg" ondblclick="document.location='vpn_pppoe_edit.php?id=<?=$i;?>';">
    <?=htmlspecialchars($pppoe['descr']);?>&nbsp;
  </td>
  <td valign="middle" nowrap class="list">
    <table border="0" cellspacing="0" cellpadding="1">
      <tr>
        <td valign="middle"><a href="vpn_pppoe_edit.php?id=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" title="<?=gettext("edit pppoe instance"); ?>"></a></td>
        <td><a href="vpn_pppoe.php?act=del&id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this entry? All elements that still use it will become invalid (e.g. filter rules)!");?>')"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" title="<?=gettext("delete pppoe instance");?>"></a></td>
      </tr>
    </table>
  </td>
</tr>
	  <?php $i++; endforeach; ?>
<tr>
  <td class="list" colspan="4"></td>
  <td class="list">
    <table border="0" cellspacing="0" cellpadding="1">
      <tr>
	<td valign="middle" width="17">&nbsp;</td>
        <td valign="middle"><a href="vpn_pppoe_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="<?=gettext("add a new pppoe instance");?>"></a></td>
        </td>
      </tr>
    </table>
  </td>
</tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
