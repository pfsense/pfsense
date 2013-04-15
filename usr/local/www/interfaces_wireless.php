<?php
/* $Id$ */
/*
	interfaces_wireless.php

	Copyright (C) 2010 Erik Fonnesbeck
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
	pfSense_MODULE:	interfaces_assign
*/

##|+PRIV
##|*IDENT=page-interfaces-wireless
##|*NAME=Interfaces: Wireless page
##|*DESCR=Allow access to the 'Interfaces: Wireless' page.
##|*MATCH=interfaces_wireless.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['wireless']))
	$config['wireless'] = array();
if (!is_array($config['wireless']['clone']))
	$config['wireless']['clone'] = array();

$a_clones = &$config['wireless']['clone'];

function clone_inuse($num) {
	global $config, $a_clones;

	$iflist = get_configured_interface_list(false, true);
	foreach ($iflist as $if) {
		if ($config['interfaces'][$if]['if'] == $a_clones[$num]['cloneif'])
			return true;
	}

	return false;
}

if ($_GET['act'] == "del") {
	/* check if still in use */
	if (clone_inuse($_GET['id'])) {
		$input_errors[] = gettext("This wireless clone cannot be deleted because it is assigned as an interface.");
	} else {
		mwexec("/sbin/ifconfig " . $a_clones[$_GET['id']]['cloneif'] . " destroy");
		unset($a_clones[$_GET['id']]);

		write_config();

		header("Location: interfaces_wireless.php");
		exit;
	}
}


$pgtitle = array(gettext("Interfaces"),gettext("Wireless"));
$shortcut_section = "wireless";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="interfaces wireless">
  <tr><td>
<?php
	$tab_array = array();
	$tab_array[0] = array(gettext("Interface assignments"), false, "interfaces_assign.php");
	$tab_array[1] = array(gettext("Interface Groups"), false, "interfaces_groups.php");
	$tab_array[2] = array(gettext("Wireless"), true, "interfaces_wireless.php");
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
	<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="main table">
                <tr>
                  <td width="20%" class="listhdrr"><?=gettext("Interface");?></td>
                  <td width="20%" class="listhdrr"><?=gettext("Mode");?></td>
                  <td width="50%" class="listhdr"><?=gettext("Description");?></td>
                  <td width="10%" class="list"></td>
				</tr>
			  <?php $i = 0;
					foreach ($a_clones as $clone): ?>
                <tr ondblclick="document.location='interfaces_wireless_edit.php?id=<?=$i;?>'">
                  <td class="listlr">
					<?=htmlspecialchars($clone['cloneif']);?>
                  </td>
                  <td class="listr">
					<?= $wlan_modes[$clone['mode']]; ?>
                  </td>
                  <td class="listbg">
                    <?=htmlspecialchars($clone['descr']);?>&nbsp;
                  </td>
                  <td valign="middle" class="list nowrap"> <a href="interfaces_wireless_edit.php?id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" alt="edit" ></a>
                     &nbsp;<a href="interfaces_wireless.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this wireless clone?");?>')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" alt="delete" /></a></td>
				</tr>
			  <?php $i++; endforeach; ?>
                <tr>
                  <td class="list" colspan="3">&nbsp;</td>
                  <td class="list"> <a href="interfaces_wireless_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="edit" /></a></td>
			</tr>
				<tr>
				<td colspan="3" class="list"><p class="vexpl"><span class="red"><strong>
				  <?=gettext("Note");?>:<br/>
				  </strong></span>
				  <?=gettext("Here you can configure clones of wireless interfaces, which can be assigned as separate independent interfaces. Only available on wireless chipsets that support this, with limitations on the number that can be created in each mode.");?></p>
				  </td>
				<td class="list">&nbsp;</td>
				</tr>
              </table>
	      </div>
	</td>
	</tr>
</table>
<?php include("fend.inc"); ?>
</body>
</html>
