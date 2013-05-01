<?php
/* $Id$ */
/*
	firewall_nat_1to1.php
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
	pfSense_MODULE:	nat
*/

##|+PRIV
##|*IDENT=page-firewall-nat-1-1
##|*NAME=Firewall: NAT: 1:1 page
##|*DESCR=Allow access to the 'Firewall: NAT: 1:1' page.
##|*MATCH=firewall_nat_1to1.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

if (!is_array($config['nat']['onetoone'])) {
	$config['nat']['onetoone'] = array();
}
$a_1to1 = &$config['nat']['onetoone'];

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;
		$retval |= filter_configure();
		$savemsg = get_std_save_message($retval);

		if ($retval == 0) {
			clear_subsystem_dirty('natconf');
			clear_subsystem_dirty('filter');
		}
	}
}

if ($_GET['act'] == "del") {
	if ($a_1to1[$_GET['id']]) {
		unset($a_1to1[$_GET['id']]);
		if (write_config())
			mark_subsystem_dirty('natconf');
		header("Location: firewall_nat_1to1.php");
		exit;
	}
}

$pgtitle = array(gettext("Firewall"),gettext("NAT"),gettext("1:1"));
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="firewall_nat_1to1.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('natconf')): ?>
<?php print_info_box_np(gettext("The NAT configuration has been changed") . ".<br/>" . gettext("You must apply the changes in order for them to take effect."));?><br/>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="fireall nat 1to1">  <tr><td>
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("Port Forward"), false, "firewall_nat.php");
	$tab_array[] = array(gettext("1:1"), true, "firewall_nat_1to1.php");
	$tab_array[] = array(gettext("Outbound"), false, "firewall_nat_out.php");
	$tab_array[] = array(gettext("NPt"), false, "firewall_nat_npt.php");
	display_top_tabs($tab_array);
?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
              <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="main area">
                <tr>
		  <td width="10%" class="listhdrr"><?=gettext("Interface"); ?></td>
                  <td width="20%" class="listhdrr"><?=gettext("External IP"); ?></td>
                  <td width="15%" class="listhdrr"><?=gettext("Internal IP"); ?></td>
                  <td width="15%" class="listhdrr"><?=gettext("Destination IP"); ?></td>
                  <td width="30%" class="listhdr"><?=gettext("Description"); ?></td>
                  <td width="10%" class="list">
                    <table border="0" cellspacing="0" cellpadding="1" summary="edit">
                      <tr>
			<td width="17"></td>
                        <td valign="middle"><a href="firewall_nat_1to1_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="<?=gettext("add rule"); ?>" alt="add" /></a></td>
                      </tr>
                    </table>
		  </td>
				</tr>
	  <?php 
			$textse = "</span>";

			$i = 0; foreach ($a_1to1 as $natent):

			if (isset($natent['disabled']))
				$textss = "<span class=\"gray\">";
			else
				$textss = "<span>"; ?>
                <tr>
		  <td class="listlr" ondblclick="document.location='firewall_nat_1to1_edit.php?id=<?=$i;?>';">
                  <?php
			echo $textss;
					if (!$natent['interface'])
						echo htmlspecialchars(convert_friendly_interface_to_friendly_descr("wan"));
					else
						echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface']));
			echo $textse;
				  ?>
                  </td>
                  <td class="listr" ondblclick="document.location='firewall_nat_1to1_edit.php?id=<?=$i;?>';">
                    <?php 
			$source_net = pprint_address($natent['source']);
			$source_cidr = strstr($source_net, '/');
			echo $textss . $natent['external'] . $source_cidr . $textse;
                    ?>
                  </td>
                  <td class="listr" ondblclick="document.location='firewall_nat_1to1_edit.php?id=<?=$i;?>';">
                    <?php 		echo $textss . $source_net . $textse; ?>
                  </td>
                  <td class="listr" ondblclick="document.location='firewall_nat_1to1_edit.php?id=<?=$i;?>';">
                    <?php 		echo $textss . pprint_address($natent['destination']) . $textse; ?>
                  </td>
                  <td class="listbg" ondblclick="document.location='firewall_nat_1to1_edit.php?id=<?=$i;?>';">
			<?=$textss;?>
                    <?=htmlspecialchars($natent['descr']);?>&nbsp;
			<?=$textse;?>
                  </td>
                  <td class="list nowrap">
                    <table border="0" cellspacing="0" cellpadding="1" summary="edit">
                      <tr>
                        <td valign="middle"><a href="firewall_nat_1to1_edit.php?id=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" title="<?=gettext("edit rule"); ?>" alt="edit" /></a></td>
			<td valign="middle"><a href="firewall_nat_1to1.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this mapping?");?>')"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" title="<?=gettext("delete rule"); ?>" alt="delete" /></a></td>
                      </tr>
                    </table>
                  </td>
                </tr>
		<?php $i++; endforeach; ?>
                <tr>
                  <td class="list" colspan="5"></td>
                  <td class="list">
                    <table border="0" cellspacing="0" cellpadding="1" summary="add">
                      <tr>
			<td width="17"></td>
                        <td valign="middle"><a href="firewall_nat_1to1_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="<?=gettext("add rule"); ?>" alt="add" /></a></td>
                      </tr>
                    </table>
                  </td>
                </tr>
		<tr>
		  <td colspan="5">
		        <p><span class="vexpl"><span class="red"><strong><?=gettext("Note:"); ?><br/>
                      </strong></span><?=gettext("Depending on the way your WAN connection is setup, you may also need a"); ?> <a href="firewall_virtual_ip.php"><?=gettext("Virtual IP."); ?></a><br/>
			<?=gettext("If you add a 1:1 NAT entry for any of the interface IPs on this system, it will make this system inaccessible on that IP address. i.e. if " .
			"you use your WAN IP address, any services on this system (IPsec, OpenVPN server, etc.) using the WAN IP address will no longer function."); ?></span></p>
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
