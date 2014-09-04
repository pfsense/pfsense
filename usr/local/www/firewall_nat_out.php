<?php
/* $Id$ */
/*
	firewall_nat_out.php
	Copyright (C) 2004 Scott Ullrich
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
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
##|*IDENT=page-firewall-nat-outbound
##|*NAME=Firewall: NAT: Outbound page
##|*DESCR=Allow access to the 'Firewall: NAT: Outbound' page.
##|*MATCH=firewall_nat_out.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

global $FilterIflist;
global $GatewaysList;

if (!is_array($config['nat']['outbound']))
	$config['nat']['outbound'] = array();

if (!is_array($config['nat']['outbound']['rule']))
	$config['nat']['outbound']['rule'] = array();

$a_out = &$config['nat']['outbound']['rule'];

if (!isset($config['nat']['outbound']['mode']))
	$config['nat']['outbound']['mode'] = "automatic";

$mode = $config['nat']['outbound']['mode'];

if ($_POST['apply']) {
	write_config();

	$retval = 0;
	$retval |= filter_configure();

	if(stristr($retval, "error") <> true)
	        $savemsg = get_std_save_message($retval);
	else
		$savemsg = $retval;

	if ($retval == 0) {
		clear_subsystem_dirty('natconf');
		clear_subsystem_dirty('filter');
	}
}

if (isset($_POST['save']) && $_POST['save'] == "Save") {
	/* mutually exclusive settings - if user wants advanced NAT, we don't generate automatic rules */
	if ($_POST['mode'] == "advanced" && ($mode == "automatic" || $mode == "hybrid")) {
		/*
		 *    user has enabled advanced outbound NAT and doesn't have rules
		 *    lets automatically create entries
		 *    for all of the interfaces to make life easier on the pip-o-chap
		 */
		if(empty($FilterIflist))
			filter_generate_optcfg_array();
		if(empty($GatewaysList))
			filter_generate_gateways();
		$tonathosts = filter_nat_rules_automatic_tonathosts(true);
		$automatic_rules = filter_nat_rules_outbound_automatic("");

		foreach ($tonathosts as $tonathost) {
			foreach ($automatic_rules as $natent) {
				$natent['source']['network'] = $tonathost['subnet'];
				$natent['descr'] .= sprintf(gettext(' - %1$s to %2$s'),
					$tonathost['descr'],
					convert_real_interface_to_friendly_descr($natent['interface']));
				$natent['created'] = make_config_revision_entry(null, gettext("Manual Outbound NAT Switch"));

				/* Try to detect already auto created rules and avoid duplicate them */
				$found = false;
				foreach ($a_out as $rule) {
					if ($rule['interface'] == $natent['interface'] &&
					    $rule['source']['network'] == $natent['source']['network'] &&
					    $rule['dstport'] == $natent['dstport'] &&
					    $rule['target'] == $natent['target'] &&
					    $rule['descr'] == $natent['descr']) {
						$found = true;
						break;
					}
				}

				if ($found === false)
					$a_out[] = $natent;
			}
		}
		$savemsg = gettext("Default rules for each interface have been created.");
		unset($FilterIflist, $GatewaysList);
	}

	$config['nat']['outbound']['mode'] = $_POST['mode'];

	if (write_config())
		mark_subsystem_dirty('natconf');
	header("Location: firewall_nat_out.php");
	exit;
}

if ($_GET['act'] == "del") {
	if ($a_out[$_GET['id']]) {
		unset($a_out[$_GET['id']]);
		if (write_config())
			mark_subsystem_dirty('natconf');
		header("Location: firewall_nat_out.php");
		exit;
	}
}

if (isset($_POST['del_x'])) {
	/* delete selected rules */
	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		foreach ($_POST['rule'] as $rulei) {
			unset($a_out[$rulei]);
		}
		if (write_config())
			mark_subsystem_dirty('natconf');
		header("Location: firewall_nat_out.php");
		exit;
	}

} else if ($_GET['act'] == "toggle") {
	if ($a_out[$_GET['id']]) {
		if(isset($a_out[$_GET['id']]['disabled']))
			unset($a_out[$_GET['id']]['disabled']);
		else
			$a_out[$_GET['id']]['disabled'] = true;
		if (write_config("Firewall: NAT: Outbound, enable/disable NAT rule"))
			mark_subsystem_dirty('natconf');
		header("Location: firewall_nat_out.php");
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
		$a_out_new = array();

		/* copy all rules < $movebtn and not selected */
		for ($i = 0; $i < $movebtn; $i++) {
			if (!in_array($i, $_POST['rule']))
				$a_out_new[] = $a_out[$i];
		}

		/* copy all selected rules */
		for ($i = 0; $i < count($a_out); $i++) {
			if ($i == $movebtn)
				continue;
			if (in_array($i, $_POST['rule']))
				$a_out_new[] = $a_out[$i];
		}

		/* copy $movebtn rule */
		if ($movebtn < count($a_out))
			$a_out_new[] = $a_out[$movebtn];

		/* copy all rules > $movebtn and not selected */
		for ($i = $movebtn+1; $i < count($a_out); $i++) {
			if (!in_array($i, $_POST['rule']))
				$a_out_new[] = $a_out[$i];
		}
		if (count($a_out_new) > 0)
			$a_out = $a_out_new;

		if (write_config())
			mark_subsystem_dirty('natconf');
		header("Location: firewall_nat_out.php");
		exit;
	}
}

$pgtitle = array(gettext("Firewall"),gettext("NAT"),gettext("Outbound"));
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="firewall_nat_out.php" method="post" name="iform">
<script type="text/javascript" src="/javascript/row_toggle.js"></script>
<?php
if ($savemsg)
	print_info_box($savemsg);
if (is_subsystem_dirty('natconf'))
	print_info_box_np(gettext("The NAT configuration has been changed.")."<br />".gettext("You must apply the changes in order for them to take effect."));
?>
<br />
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="firewall nat outbound">
	<tr><td>
<?php
		$tab_array = array();
		$tab_array[] = array(gettext("Port Forward"), false, "firewall_nat.php");
		$tab_array[] = array(gettext("1:1"), false, "firewall_nat_1to1.php");
		$tab_array[] = array(gettext("Outbound"), true, "firewall_nat_out.php");
		$tab_array[] = array(gettext("NPt"), false, "firewall_nat_npt.php");
		display_top_tabs($tab_array);
?>
	</td></tr>
	<tr>
		<td>
			<div id="mainarea">
			<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="main area">
				<tr>
					<td rowspan="3" align="right" valign="middle"><b><?=gettext("Mode:"); ?></b></td>
					<td>
						<input name="mode" type="radio" id="automatic" value="automatic" <?php if ($mode == "automatic") echo "checked=\"checked\"";?> />
					</td>
					<td>
						<strong>
							<?=gettext("Automatic outbound NAT rule generation"); ?><br />
							<?=gettext("(IPsec passthrough included)");?>
						</strong>
					</td>
					<td>
						<input name="mode" type="radio" id="hybrid" value="hybrid" <?php if ($mode == "hybrid") echo "checked=\"checked\"";?> />
					</td>
					<td>
						<strong>
							<?=gettext("Hybrid Outbound NAT rule generation"); ?><br />
							<?=gettext("(Automatic Outbound NAT + rules below)");?>
						</strong>
					</td>
					<td rowspan="3" valign="middle" align="left">
						<input name="save" type="submit" class="formbtn" value="<?=gettext("Save");?>" />
					</td>
				</tr>
				<tr>
					<td colspan="4">
						&nbsp;
					</td>
				</tr>
				<tr>
					<td>
						<input name="mode" type="radio" id="advanced" value="advanced" <?php if ($mode == "advanced") echo "checked=\"checked\"";?> />
					</td>
					<td>
						<strong>
							<?=gettext("Manual Outbound NAT rule generation"); ?><br />
							<?=gettext("(AON - Advanced Outbound NAT)");?>
						</strong>
					</td>
					<td>
						<input name="mode" type="radio" id="disabled" value="disabled" <?php if ($mode == "disabled") echo "checked=\"checked\"";?> />
					</td>
					<td>
						<strong>
							<?=gettext("Disable Outbound NAT rule generation"); ?><br />
							<?=gettext("(No Outbound NAT rules)");?>
						</strong>
					</td>
				</tr>
				<tr>
					<td colspan="6">
						&nbsp;
					</td>
				</tr>
			</table>
			<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="mappings">
				<tr><td colspan="5"><b>&nbsp;<?=gettext("Mappings:"); ?></b></td></tr>
				<tr><td>&nbsp;</td></tr>
				<tr id="frheader">
					<td width="3%" class="list">&nbsp;</td>
					<td width="3%" class="list">&nbsp;</td>
					<td width="10%" class="listhdrr"><?=gettext("Interface");?></td>
					<td width="15%" class="listhdrr"><?=gettext("Source");?></td>
					<td width="10%" class="listhdrr"><?=gettext("Source Port");?></td>
					<td width="15%" class="listhdrr"><?=gettext("Destination");?></td>
					<td width="10%" class="listhdrr"><?=gettext("Destination Port");?></td>
					<td width="15%" class="listhdrr"><?=gettext("NAT Address");?></td>
					<td width="10%" class="listhdrr"><?=gettext("NAT Port");?></td>
					<td width="10%" class="listhdrr"><?=gettext("Static Port");?></td>
					<td width="25%" class="listhdr"><?=gettext("Description");?></td>
					<td width="5%" class="list">
						<table border="0" cellspacing="0" cellpadding="1" summary="add">
							<tr>
								<td width="17"></td>
								<td>
									<a href="firewall_nat_out_edit.php?after=-1">
										<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="<?=gettext("add new mapping");?>" alt="add" />
									</a>
								</td>
							</tr>
						</table>
					</td>
				</tr>
<?php
			$i = 0;
			foreach ($a_out as $natent):
				$iconfn = "pass";
				$textss = $textse = "";
				if ($mode == "disabled" || $mode == "automatic" || isset($natent['disabled'])) {
					$textss = "<span class=\"gray\">";
					$textse = "</span>";
					$iconfn .= "_d";
				}

				//build Alias popup box
				$alias_src_span_begin = "";
				$alias_src_port_span_begin = "";
				$alias_dst_span_begin = "";
				$alias_dst_port_span_begin = "";

				$alias_popup = rule_popup($natent['source']['network'],pprint_port($natent['sourceport']),$natent['destination']['address'],pprint_port($natent['dstport']));

				$alias_src_span_begin = $alias_popup["src"];
				$alias_src_port_span_begin = $alias_popup["srcport"];
				$alias_dst_span_begin = $alias_popup["dst"];
				$alias_dst_port_span_begin = $alias_popup["dstport"];

				$alias_src_span_end = $alias_popup["src_end"];
				$alias_src_port_span_end = $alias_popup["srcport_end"];
				$alias_dst_span_end = $alias_popup["dst_end"];
				$alias_dst_port_span_end = $alias_popup["dstport_end"];
?>
				<tr valign="top" id="fr<?=$i;?>">
					<td class="listt">
						<input type="checkbox" id="frc<?=$i;?>" name="rule[]" value="<?=$i;?>" onclick="fr_bgcolor('<?=$i;?>')" style="margin: 0; padding: 0; width: 15px; height: 15px;" />
					</td>
					<td class="listt" align="center">
<?php
					if ($mode == "disabled" || $mode == "automatic"):
?>
						<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_<?=$iconfn;?>.gif" width="11" height="11" border="0"
							title="<?=gettext("This rule is being ignored");?>" alt="icon" />
<?php
					else:
?>
						<a href="?act=toggle&amp;id=<?=$i;?>">
							<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_<?=$iconfn;?>.gif" width="11" height="11" border="0"
								title="<?=gettext("click to toggle enabled/disabled status");?>" alt="icon" />
						</a>
<?php
						endif;
?>
					</td>
					<td class="listlr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$i;?>';">
						<?php echo $textss . htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface'])) . $textse; ?>
						&nbsp;
					</td>
					<td class="listr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$i;?>';">
						<?PHP $natent['source']['network'] = ($natent['source']['network'] == "(self)") ? "This Firewall" : $natent['source']['network']; ?>
						<?php echo $textss . $alias_src_span_begin . $natent['source']['network'] . $alias_src_span_end . $textse;?>
					</td>
					<td class="listr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$i;?>';">
<?php
						echo $textss;
						echo ($natent['protocol']) ? $natent['protocol'] . '/' : "" ;
						if (!$natent['sourceport'])
							echo "*";
						else
							echo $alias_src_port_span_begin . $natent['sourceport'] . $alias_src_port_span_end;
						echo $textse;
?>
					</td>
					<td class="listr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$i;?>';">
<?php
						echo $textss;
						if (isset($natent['destination']['any']))
							echo "*";
						else {
							if (isset($natent['destination']['not']))
								echo "!&nbsp;";
							echo $alias_dst_span_begin . $natent['destination']['address'] . $alias_dst_span_end;
						}
						echo $textse;
?>
					</td>
					<td class="listr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$i;?>';">
<?php
						echo $textss;
						echo ($natent['protocol']) ? $natent['protocol'] . '/' : "" ;
						if (!$natent['dstport'])
							echo "*";
						else
							echo $alias_dst_port_span_begin . $natent['dstport'] . $alias_dst_port_span_end;
						echo $textse;
?>
					</td>
					<td class="listr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$i;?>';">
<?php
						echo $textss;
						if (isset($natent['nonat']))
							echo '<I>NO NAT</I>';
						elseif (!$natent['target'])
							echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface'])) . " address";
						elseif ($natent['target'] == "other-subnet")
							echo $natent['targetip'] . '/' . $natent['targetip_subnet'];
						else
							echo $natent['target'];
						echo $textse;
?>
					</td>
					<td class="listr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$i;?>';">
<?php
						echo $textss;
						if (!$natent['natport'])
							echo "*";
						else
							echo $natent['natport'];
						echo $textse;
?>
					</td>
					<td class="listr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$i;?>';" align="center">
<?php
						echo $textss;
						if(isset($natent['staticnatport']))
							echo gettext("YES");
						else
							echo gettext("NO");
						echo $textse;
?>
					</td>
					<td class="listbg" onclick="fr_toggle(<?=$i;?>)" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$i;?>';">
						<?=htmlspecialchars($natent['descr']);?>&nbsp;
					</td>
					<td class="list nowrap" valign="middle">
						<table border="0" cellspacing="0" cellpadding="1" summary="move">
							<tr>
								<td><input onmouseover="fr_insline(<?=$i;?>, true)" onmouseout="fr_insline(<?=$i;?>, false)" name="move_<?=$i;?>" src="/themes/<?= $g['theme']; ?>/images/icons/icon_left.gif" title="<?=gettext("move selected rules before this rule");?>" type="image" style="height:17;width:17;border:0" /></td>
								<td>
									<a href="firewall_nat_out_edit.php?id=<?=$i;?>">
										<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" title="<?=gettext("edit mapping");?>" alt="edit" />
									</a>
								</td>
							</tr>
							<tr>
								<td align="center" valign="middle">
									<a href="firewall_nat_out.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this rule?");?>')">
										<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" title="<?=gettext("delete rule");?>" alt="delete" />
									</a>
								</td>
								<td>
									<a href="firewall_nat_out_edit.php?dup=<?=$i;?>">
										<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("add a new NAT based on this one");?>" width="17" height="17" border="0" alt="duplicate" />
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
				<tr valign="top" id="fr<?=$i;?>">
					<td class="list" colspan="11"></td>
					<td class="list nowrap" valign="middle">
						<table border="0" cellspacing="0" cellpadding="1" summary="edit">
							<tr>
								<td>
<?php
								if ($i == 0):
?>
									<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_left_d.gif" width="17" height="17" title="<?=gettext("move selected mappings to end");?>" border="0" alt="move" />
<?php
								else:
?>
									<input onmouseover="fr_insline(<?=$i;?>, true)" onmouseout="fr_insline(<?=$i;?>, false)" name="move_<?=$i;?>" type="image" src="/themes/<?= $g['theme']; ?>/images/icons/icon_left.gif" style="width:17;height:17;border:0" title="<?=gettext("move selected mappings to end");?>" />
<?php
								endif;
?>
								</td>
								<td>
									<a href="firewall_nat_out_edit.php">
										<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="<?=gettext("add new mapping");?>" alt="add" />
									</a>
								</td>
							</tr>
							<tr>
								<td>
<?php
								if ($i == 0):
?>
									<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x_d.gif" width="17" height="17" title="<?=gettext("delete selected rules");?>" border="0" alt="delete" />
<?php
								else:
?>
									<input name="del" type="image" src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" style="width:17;height:17" title="<?=gettext("delete selected mappings");?>" onclick="return confirm('<?=gettext("Do you really want to delete the selected mappings?");?>')" />
<?php
								endif;
?>
								</td>
							</tr>
						</table>
					</td>
				</tr>
<?php
			if ($mode == "automatic" || $mode == "hybrid"):
				if(empty($FilterIflist))
					filter_generate_optcfg_array();
				if(empty($GatewaysList))
					filter_generate_gateways();
				$automatic_rules = filter_nat_rules_outbound_automatic(implode(" ", filter_nat_rules_automatic_tonathosts()));
				unset($FilterIflist, $GatewaysList);
?>
				<tr><td colspan="5"><b>&nbsp;<?=gettext("Automatic rules:"); ?></b></td></tr>
				<tr><td>&nbsp;</td></tr>
				<tr id="frheader">
					<td width="3%" class="list">&nbsp;</td>
					<td width="3%" class="list">&nbsp;</td>
					<td width="10%" class="listhdrr"><?=gettext("Interface");?></td>
					<td width="15%" class="listhdrr"><?=gettext("Source");?></td>
					<td width="10%" class="listhdrr"><?=gettext("Source Port");?></td>
					<td width="15%" class="listhdrr"><?=gettext("Destination");?></td>
					<td width="10%" class="listhdrr"><?=gettext("Destination Port");?></td>
					<td width="15%" class="listhdrr"><?=gettext("NAT Address");?></td>
					<td width="10%" class="listhdrr"><?=gettext("NAT Port");?></td>
					<td width="10%" class="listhdrr"><?=gettext("Static Port");?></td>
					<td width="25%" class="listhdr"><?=gettext("Description");?></td>
					<td width="5%" class="list">&nbsp;</td>
				</tr>
<?php
				foreach ($automatic_rules as $natent):
?>
					<tr valign="top">
						<td class="list">&nbsp;</td>
						<td class="listt" align="center">
							<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_pass.gif" width="11" height="11" border="0" title="<?=gettext("automatic outbound nat");?>" alt="icon" />
						</td>
						<td class="listlr" style="background-color: #E0E0E0">
							<?php echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface'])); ?>
							&nbsp;
						</td>
						<td class="listr" style="background-color: #E0E0E0">
							<?=$natent['source']['network'];?>
						</td>
						<td class="listr" style="background-color: #E0E0E0">
<?php
							echo ($natent['protocol']) ? $natent['protocol'] . '/' : "" ;
							if (!$natent['sourceport'])
								echo "*";
							else
								echo $natent['sourceport'];
?>
						</td>
						<td class="listr" style="background-color: #E0E0E0">
<?php
							if (isset($natent['destination']['any']))
								echo "*";
							else {
								if (isset($natent['destination']['not']))
									echo "!&nbsp;";
								echo $natent['destination']['address'];
							}
?>
						</td>
						<td class="listr" style="background-color: #E0E0E0">
<?php
							echo ($natent['protocol']) ? $natent['protocol'] . '/' : "" ;
							if (!$natent['dstport'])
								echo "*";
							else
								echo $natent['dstport'];
?>
						</td>
						<td class="listr" style="background-color: #E0E0E0">
<?php
							if (isset($natent['nonat']))
								echo '<I>NO NAT</I>';
							elseif (!$natent['target'])
								echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface'])) . " address";
							elseif ($natent['target'] == "other-subnet")
								echo $natent['targetip'] . '/' . $natent['targetip_subnet'];
							else
								echo $natent['target'];
?>
						</td>
						<td class="listr" style="background-color: #E0E0E0">
<?php
							if (!$natent['natport'])
								echo "*";
							else
								echo $natent['natport'];
?>
						</td>
						<td class="listr" style="background-color: #E0E0E0">
<?php
							if(isset($natent['staticnatport']))
								echo gettext("YES");
							else
								echo gettext("NO");
?>
						</td>
						<td class="listbg">
							<?=htmlspecialchars($natent['descr']);?>&nbsp;
						</td>
						<td class="list">&nbsp;</td>
					</tr>
<?php
				endforeach;
			endif;
?>
				<tr>
					<td colspan="12">
						<p><span class="vexpl">
							<span class="red"><strong><?=gettext("Note:"); ?><br /></strong></span>
							<?=gettext("If automatic outbound NAT selected, a mapping is automatically created " .
								"for each interface's subnet (except WAN-type connections) and the rules " .
								"on \"Mappings\" section of this page are ignored.<br /><br /> " .
								"If manual outbound NAT is selected, outbound NAT rules will not be " .
								"automatically generated and only the mappings you specify on this page " .
								"will be used. <br /><br /> " .
								"If hybrid outbound NAT is selected, mappings you specify on this page will " .
								"be used, followed by the automatically generated ones. <br /><br />" .
								"If disable outbound NAT is selected, no rules will be used. <br /><br />" .
								"If a target address other than a WAN-type interface's IP address is used, " .
								"then depending on the way the WAN connection is setup, a "); ?>
								<a href="firewall_virtual_ip.php"><?=gettext("Virtual IP"); ?></a>
								<?= gettext(" may also be required.") ?>
						</span></p>
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
