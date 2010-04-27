<?php
/*
	interfaces_lan.php
	part of pfSense(http://pfsense.org)

	Originally written by Adam Lebsack <adam at holonyx dot com>
	Changes by Chris Buechler <cmb at pfsense dot org> 
	
	Copyright (C) 2004-2008 BSD Perimeter LLC.
	Copyright (C) 2004-2009 Scott Ullrich
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
	pfSense_BUILDER_BINARIES:	/bin/kill	/sbin/ifconfig	
	pfSense_MODULE:	interfaces
*/

##|+PRIV
##|*IDENT=page-interfaces-ppp
##|*NAME=Interfaces: PPP page
##|*DESCR=Allow access to the 'Interfaces: PPP' page.
##|*MATCH=interfaces_ppp.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['ppps']['ppp']))
	$config['ppps']['ppp'] = array();

$a_ppps = &$config['ppps']['ppp'] ;

function ppp_inuse($num) {
	global $config, $g, $a_ppps;
	$iflist = get_configured_interface_list(false, true);
	foreach ($iflist as $if) {
		if ($config['interfaces'][$if]['if'] == $a_ppps[$num]['port'])
			return true;
	}
	return false;
}

if ($_GET['act'] == "del") {
	/* check if still in use */
	if (ppp_inuse($_GET['id'])) {
		$input_errors[] = "This PPP interface cannot be deleted because it is still being used as an interface.";
	} else {
		unset($a_ppps[$_GET['id']]);
		write_config();
		header("Location: interfaces_ppp.php");
		exit;
	}
}

$pgtitle = "Interfaces: PPP";
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
	$tab_array[5] = array("PPP", true, "interfaces_ppp.php");
	$tab_array[6] = array("MLPPP", false, "interfaces_mlppp.php");
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
                  <td width="20%" class="listhdrr">Serial Port</td>
                  <td width="40%" class="listhdr">Description</td>
                  <td width="10%" class="list"></td>
				</tr>
			  <?php $i = 0; foreach ($a_ppps as $id => $ppp): ?>
                <tr  ondblclick="document.location='interfaces_ppp_edit.php?id=<?=$i;?>'">
                  <td class="listr">
					<?=htmlspecialchars($ppp['port']);?>
                  </td>
                  <td class="listbg">
                    <?=htmlspecialchars($ppp['descr']);?>&nbsp;
                  </td>
                  <td valign="middle" nowrap class="list"> <a href="interfaces_ppp_edit.php?id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0"></a>
                     &nbsp;<a href="interfaces_ppp.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this PPP interface?')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0"></a></td>
				</tr>
			  <?php $i++; endforeach; ?>
                <tr>
                  <td class="list" colspan="2">&nbsp;</td>
                  <td class="list"> <a href="interfaces_ppp_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
				</tr>
              </table>
	      </div>
	</td>
	</tr>
</table>
<?php include("fend.inc"); ?>
</body>
</html>
