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

if (!is_array($config['nat']['onetoone']))
	$config['nat']['onetoone'] = array();

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

if (isset($_POST['del_x'])) {
	/* delete selected rules */
	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		foreach ($_POST['rule'] as $rulei) {
			unset($a_1to1[$rulei]);
		}
		if (write_config())
			mark_subsystem_dirty('natconf');
		header("Location: firewall_nat_1to1.php");
		exit;
	}

} else if ($_GET['act'] == "toggle") {
	if ($a_1to1[$_GET['id']]) {
		if(isset($a_1to1[$_GET['id']]['disabled']))
			unset($a_1to1[$_GET['id']]['disabled']);
		else
			$a_1to1[$_GET['id']]['disabled'] = true;
		if (write_config("Firewall: NAT: Outbound, enable/disable NAT rule"))
			mark_subsystem_dirty('natconf');
		header("Location: firewall_nat_1to1.php");
		exit;
	}
} else {
	/* yuck - IE won't send value attributes for image buttons, while Mozilla does - so we use .x/.y to find move button clicks instead... */
	unset($movebtn);
	foreach ($_POST as $pn => $pd) {
		if (preg_match("/move_(\d+)_x/", $pn, $matches)) {
			$movebtn = $matches[1];
			break;
		}
	}
	/* move selected rules before this rule */
	if (isset($movebtn) && is_array($_POST['rule']) && count($_POST['rule'])) {
		$a_1to1_new = array();

		/* copy all rules < $movebtn and not selected */
		for ($i = 0; $i < $movebtn; $i++) {
			if (!in_array($i, $_POST['rule']))
				$a_1to1_new[] = $a_1to1[$i];
		}

		/* copy all selected rules */
		for ($i = 0; $i < count($a_1to1); $i++) {
			if ($i == $movebtn)
				continue;
			if (in_array($i, $_POST['rule']))
				$a_1to1_new[] = $a_1to1[$i];
		}

		/* copy $movebtn rule */
		if ($movebtn < count($a_1to1))
			$a_1to1_new[] = $a_1to1[$movebtn];

		/* copy all rules > $movebtn and not selected */
		for ($i = $movebtn+1; $i < count($a_1to1); $i++) {
			if (!in_array($i, $_POST['rule']))
				$a_1to1_new[] = $a_1to1[$i];
		}
		if (count($a_1to1_new) > 0)
			$a_1to1 = $a_1to1_new;

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
<script type="text/javascript" src="/javascript/row_toggle.js"></script>
<?php
if ($savemsg)
	print_info_box($savemsg);
if (is_subsystem_dirty('natconf'))
	print_info_box_np(gettext("The NAT configuration has been changed.") .
		"<br />" .
		gettext("You must apply the changes in order for them to take effect."));
?>
<br />
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="firewall nat 1to1">
	<tr><td>
<?php
		$tab_array = array();
		$tab_array[] = array(gettext("Port Forward"), false, "firewall_nat.php");
		$tab_array[] = array(gettext("1:1"), true, "firewall_nat_1to1.php");
		$tab_array[] = array(gettext("Outbound"), false, "firewall_nat_out.php");
		$tab_array[] = array(gettext("NPt"), false, "firewall_nat_npt.php");
		display_top_tabs($tab_array);
?>
	</td></tr>
	<tr><td>
		<div id="mainarea">
		<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="main area">
			<tr id="frheader">
				<td width="3%" class="list">&nbsp;</td>
				<td width="3%" class="list">&nbsp;</td>
				<td width="10%" class="listhdrr"><?=gettext("Interface"); ?></td>
				<td width="15%" class="listhdrr"><?=gettext("External IP"); ?></td>
				<td width="15%" class="listhdrr"><?=gettext("Internal IP"); ?></td>
				<td width="15%" class="listhdrr"><?=gettext("Destination IP"); ?></td>
				<td width="29%" class="listhdr"><?=gettext("Description"); ?></td>
				<td width="10%" class="list">
					<table border="0" cellspacing="0" cellpadding="1" summary="edit">
						<tr>
							<td width="17"></td>
							<td valign="middle">
								<a href="firewall_nat_1to1_edit.php">
									<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="<?=gettext("add rule"); ?>" alt="add" />
								</a>
							</td>
						</tr>
					</table>
				</td>
			</tr>
<?php
		$textse = "</span>";
		$i = 0;
		foreach ($a_1to1 as $natent):
			if (isset($natent['disabled'])) {
				$textss = "<span class=\"gray\">";
				$iconfn = "pass_d";
			} else {
				$textss = "<span>";
				$iconfn = "pass";
			}
?>
			<tr valign="top" id="fr<?=$i;?>">
				<td class="listt">
					<input type="checkbox" id="frc<?=$i;?>" name="rule[]" value="<?=$i;?>" onclick="fr_bgcolor('<?=$i;?>')" style="margin: 0; padding: 0; width: 15px; height: 15px;" />
				</td>
				<td class="listt" align="center">
					<a href="?act=toggle&amp;id=<?=$i;?>">
						<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_<?=$iconfn;?>.gif" width="11" height="11" border="0"
							title="<?=gettext("click to toggle enabled/disabled status");?>" alt="icon" />
					</a>
				</td>
				<td class="listlr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='firewall_nat_1to1_edit.php?id=<?=$i;?>';">
<?php
					echo $textss;
					if (!$natent['interface'])
						echo htmlspecialchars(convert_friendly_interface_to_friendly_descr("wan"));
					else
						echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface']));
					echo $textse;
?>
				</td>
				<td class="listr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='firewall_nat_1to1_edit.php?id=<?=$i;?>';">
<?php
					$source_net = pprint_address($natent['source']);
					$source_cidr = strstr($source_net, '/');
					echo $textss . $natent['external'] . $source_cidr . $textse;
?>
				</td>
				<td class="listr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='firewall_nat_1to1_edit.php?id=<?=$i;?>';">
<?php
					echo $textss . $source_net . $textse;
?>
				</td>
				<td class="listr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='firewall_nat_1to1_edit.php?id=<?=$i;?>';">
<?php
					echo $textss . pprint_address($natent['destination']) . $textse;
?>
				</td>
				<td class="listbg" onclick="fr_toggle(<?=$i;?>)" ondblclick="document.location='firewall_nat_1to1_edit.php?id=<?=$i;?>';">
<?php
					echo $textss . htmlspecialchars($natent['descr']) . '&nbsp;' . $textse;
?>
				</td>
				<td class="list nowrap" valign="middle">
					<table border="0" cellspacing="0" cellpadding="1" summary="move">
						<tr>
							<td>
								<input onmouseover="fr_insline(<?=$i;?>, true)" onmouseout="fr_insline(<?=$i;?>, false)" name="move_<?=$i;?>"
									src="/themes/<?= $g['theme']; ?>/images/icons/icon_left.gif"
									title="<?=gettext("move selected rules before this rule");?>"
									type="image" style="height:17;width:17;border:0" />
							</td>
							<td>
								<a href="firewall_nat_1to1_edit.php?id=<?=$i;?>">
									<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" title="<?=gettext("edit rule");?>" alt="edit" />
								</a>
							</td>
						</tr>
						<tr>
							<td align="center" valign="middle">
								<a href="firewall_nat_1to1.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this rule?");?>')">
									<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" title="<?=gettext("delete rule");?>" alt="delete" />
								</a>
							</td>
							<td>
								<a href="firewall_nat_1to1_edit.php?dup=<?=$i;?>">
									<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("add a new rule based on this one");?>" width="17" height="17" border="0" alt="duplicate" />
								</a>
							</td>
						</tr>
					</table>
				</td>
			</tr>
<?php
			$i++;
		endforeach;
?>
			<tr>
				<td class="list" colspan="7"></td>
				<td class="list nowrap" valign="middle">
					<table border="0" cellspacing="0" cellpadding="1" summary="edit">
						<tr>
							<td>
<?php
							if ($i == 0):
?>
								<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_left_d.gif" width="17" height="17"
									title="<?=gettext("move selected mappings to end");?>" border="0" alt="move" />
<?php
							else:
?>
								<input name="move_<?=$i;?>" type="image" src="/themes/<?= $g['theme']; ?>/images/icons/icon_left.gif"
									style="width:17;height:17;border:0" title="<?=gettext("move selected mappings to end");?>" />
<?php
							endif;
?>
							</td>
							<td>
								<a href="firewall_nat_1to1_edit.php">
									<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"
									title="<?=gettext("add new mapping");?>" alt="add" />
								</a>
							</td>
						</tr>
						<tr>
							<td>
<?php
							if ($i == 0):
?>
								<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x_d.gif" width="17" height="17"
									title="<?=gettext("delete selected rules");?>" border="0" alt="delete" />
<?php
							else:
?>
								<input name="del" type="image" src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif"
									style="width:17;height:17" title="<?=gettext("delete selected mappings");?>"
									onclick="return confirm('<?=gettext("Do you really want to delete the selected mappings?");?>')" />
<?php
							endif;
?>
							</td>
						</tr>
					</table>
				</td>
			</tr>
			<tr>
				<td colspan="7">
					<p><span class="vexpl">
						<span class="red"><strong><?=gettext("Note:"); ?><br /></strong></span>
						<?=gettext("Depending on the way your WAN connection is setup, you may also need a"); ?>
						<a href="firewall_virtual_ip.php"><?=gettext("Virtual IP."); ?></a><br />
						<?=gettext("If you add a 1:1 NAT entry for any of the interface IPs on this system, " .
							"it will make this system inaccessible on that IP address. i.e. if " .
							"you use your WAN IP address, any services on this system (IPsec, OpenVPN server, etc.) " .
							"using the WAN IP address will no longer function."); ?>
					</span></p>
				</td>
			</tr>
		</table>
		</div>
	</td></tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
