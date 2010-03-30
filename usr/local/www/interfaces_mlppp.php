<?php
/* $Id$ */
/*
	interfaces_mlppp.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
##|*IDENT=page-interfaces-mlppp
##|*NAME=Interfaces: mlppp page
##|*DESCR=Allow access to the 'Interfaces: mlppp' page.
##|*MATCH=interfaces_mlppp.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['mlppps']['mlppp']))
	$config['mlppps']['mlppp'] = array();

$a_mlppps = &$config['mlppps']['mlppp'] ;

function mlppp_inuse($num) {
	global $config, $a_mlppps;

	$iflist = get_configured_interface_list(false, true);
	foreach ($iflist as $if) {
		if ($config['interfaces'][$if]['if'] == $a_mlppps[$num]['mlpppif'])
			return true;
	}

	return false;
}

if ($_GET['act'] == "del") {
	/* check if still in use */
	if (mlppp_inuse($_GET['id'])) {
		$input_errors[] = "This interface cannot be deleted because it is still being used as an interface.";
	} else {
		mwexec("/sbin/ifconfig " . $a_mlppps[$_GET['id']]['mlpppif'] . " destroy");
		unset($a_mlppps[$_GET['id']]);

		write_config();

		header("Location: interfaces_mlppp.php");
		exit;
	}
}


$pgtitle = array("Interfaces","MLPPP");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
<?php
	$tab_array = array();
	$tab_array[0] = array("Interface assignments", false, "interfaces_assign.php");
	$tab_array[1] = array("Interface Groups", false, "interfaces_groups.php");
	$tab_array[2] = array("Wireless", false, "interfaces_wireless.php");
	$tab_array[3] = array("VLANs", false, "interfaces_vlan.php");
	$tab_array[4] = array("QinQs", false, "interfaces_qinq.php");
	$tab_array[5] = array("PPP", false, "interfaces_ppp.php");
	$tab_array[6] = array("MLPPP", true, "interfaces_mlppp.php");
	$tab_array[7] = array("GRE", false, "interfaces_gre.php");
	$tab_array[8] = array("GIF", false, "interfaces_gif.php");
	$tab_array[9] = array("Bridges", false, "interfaces_bridge.php");
	$tab_array[10] = array("LAGG", false, "interfaces_lagg.php");
	display_top_tabs($tab_array);
?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
	<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                  <td width="20%" class="listhdrr">Interface</td>
                  <td width="20%" class="listhdrr">mlppp tag</td>
                  <td width="50%" class="listhdr">Description</td>
                  <td width="10%" class="list"></td>
				</tr>
			  <?php $i = 0; foreach ($a_mlppps as $mlppp): ?>
                <tr ondblclick="document.location='interfaces_mlppp_edit.php?id=<?=$i;?>'">
                  <td class="listlr">
					<?=htmlspecialchars($mlppp['if']);?>
                  </td>
                  <td class="listr">
					<?=htmlspecialchars($mlppp['tag']);?>
                  </td>
                  <td class="listbg">
                    <?=htmlspecialchars($mlppp['descr']);?>&nbsp;
                  </td>
                  <td valign="middle" nowrap class="list"> <a href="interfaces_mlppp_edit.php?id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0"></a>
                     &nbsp;<a href="interfaces_mlppp.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this mlppp?')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0"></a></td>
				</tr>
			  <?php $i++; endforeach; ?>
                <tr>
                  <td class="list" colspan="3">&nbsp;</td>
                  <td class="list"> <a href="interfaces_mlppp_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
				</tr>
				<tr>
				<td colspan="3" class="list"><p class="vexpl"><span class="red"><strong>
				  Note:<br>
				  </strong></span>
				  Not all drivers/NICs support 802.1Q mlppp tagging properly. On cards that do not explicitly support it, mlppp tagging will still work, but the reduced MTU may cause problems. See the <?=$g['product_name']?> handbook for information on supported cards. </p>
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
