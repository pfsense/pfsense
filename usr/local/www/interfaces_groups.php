<?php
/*
	Copyright (C) 2009 Ermal Luçi
	Copyright (C) 2004 Scott Ullrich
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
	pfSense_BUILDER_BINARIES:	/sbin/ifconfig
	pfSense_MODULE:	interfaces
*/

##|+PRIV
##|*IDENT=page-interfaces-groups
##|*NAME=Interfaces: Groups page
##|*DESCR=Create interface groups
##|*MATCH=interfaces_groups.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");

if (!is_array($config['ifgroups']['ifgroupentry']))
	$config['ifgroups']['ifgroupentry'] = array();

$a_ifgroups = &$config['ifgroups']['ifgroupentry'];

if ($_GET['act'] == "del") {
	if ($a_ifgroups[$_GET['id']]) {
		$members = explode(" ", $a_ifgroups[$_GET['id']]['members']);
		foreach ($members as $ifs) {
			$realif = get_real_interface($ifs);
			if ($realif)
				mwexec("/sbin/ifconfig  {$realif} -group " . $a_ifgroups[$_GET['id']]['ifname']);
		}
		unset($a_ifgroups[$_GET['id']]);
		write_config();
		header("Location: interfaces_groups.php");
		exit;
	}
}

$pgtitle = array(gettext("Interfaces"),gettext("Groups"));
$shortcut_section = "interfaces";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>

<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="interfaces groups">
  <tr><td>
<?php
	$tab_array = array();
	$tab_array[0] = array(gettext("Interface assignments"), false, "interfaces_assign.php");
	$tab_array[1] = array(gettext("Interface Groups"), true, "interfaces_groups.php");
	$tab_array[2] = array(gettext("Wireless"), false, "interfaces_wireless.php");
	$tab_array[3] = array(gettext("VLANs"), false, "interfaces_vlan.php");
	$tab_array[4] = array(gettext("QinQs"), false, "interfaces_qinq.php");
	$tab_array[5] = array(gettext("PPPs"), false, "interfaces_ppps.php");
	$tab_array[6] = array(gettext("GRE"), false, "interfaces_gre.php");
	$tab_array[7] = array(gettext("GIF"), false, "interfaces_gif.php");
	$tab_array[8] = array(gettext("Bridges"), false, "interfaces_bridge.php");
	$tab_array[9] = array(gettext("LAGG"), false, "interfaces_lagg.php");
	display_top_tabs($tab_array);
?>
  </td></tr>
<tr>
    <td>
        <div id="mainarea">
        <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="main area">

<tr>
  <td width="15%" class="listhdrr"><?=gettext("Name");?></td>
  <td width="35%" class="listhdrr"><?=gettext("Members");?></td>
  <td width="25%" class="listhdr"><?=gettext("Description");?></td>
  <td width="5%" class="list"></td>
</tr>
	  <?php if (count ($a_ifgroups)):
		$i = 0; foreach ($a_ifgroups as $ifgroupentry): ?>
<tr>
  <td class="listlr" ondblclick="document.location='interfaces_groups_edit.php?id=<?=$i;?>';">
	<a href="/firewall_rules.php?if=<?=htmlspecialchars($ifgroupentry['ifname']);?>"><?=htmlspecialchars($ifgroupentry['ifname']);?></a>
  </td>
  <td class="listr" ondblclick="document.location='interfaces_groups_edit.php?id=<?=$i;?>';">
      <?php
	$members_arr = explode(" ", $ifgroupentry['members']);
	$iflist = get_configured_interface_with_descr(false, true);
	$memberses_arr = array();
	foreach ($members_arr as $memb)
		$memberses_arr[] = $iflist[$memb] ? $iflist[$memb] : $memb;
	unset($iflist);
	$memberses = implode(", ", $memberses_arr);
	echo $memberses;
	if(count($members_arr) < 10) {
		echo " ";
	} else {
		echo "...";
	}
    ?>
  </td>
  <td class="listbg" ondblclick="document.location='interfaces_groups_edit.php?id=<?=$i;?>';">
    <?=htmlspecialchars($ifgroupentry['descr']);?>&nbsp;
  </td>
  <td valign="middle" class="list nowrap">
    <table border="0" cellspacing="0" cellpadding="1" summary="icons">
      <tr>
        <td valign="middle"><a href="interfaces_groups_edit.php?id=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" title="<?=gettext("edit group");?>" alt="edit" /></a></td>
        <td><a href="interfaces_groups.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this group? All elements that still use it will become invalid (e.g. filter rules)!");?>')"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" title="<?=gettext("delete ifgroupentry");?>" alt="delete" /></a></td>
      </tr>
    </table>
  </td>
</tr>
	  <?php $i++; endforeach; endif;?>
<tr>
  <td class="list" colspan="3"></td>
  <td class="list">
    <table border="0" cellspacing="0" cellpadding="1" summary="add">
      <tr>
	<td valign="middle" width="17">&nbsp;</td>
        <td valign="middle"><a href="interfaces_groups_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="<?=gettext("add a new group");?>" alt="add" /></a></td>
      </tr>
    </table>
  </td>
</tr>
<tr>
  <td class="tabcont" colspan="3">
	<p><span class="vexpl"><span class="red"><strong><?=gettext("Note:");?><br/></strong></span><?=gettext("Interface Groups allow you to create rules that apply to multiple interfaces without duplicating the rules. If you remove members from an interface group, the group rules no longer apply to that interface.");?></span></p>
	</td>
</tr>
	</table>
	</div>
 </td>
</tr>
</table>
<?php include("fend.inc"); ?>
</body>
</html>
