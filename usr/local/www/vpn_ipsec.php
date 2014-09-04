<?php
/*
	vpn_ipsec.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
	Copyright (C) 2008 Shrew Soft Inc
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
##|*IDENT=page-vpn-ipsec
##|*NAME=VPN: IPsec page
##|*DESCR=Allow access to the 'VPN: IPsec' page.
##|*MATCH=vpn_ipsec.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("ipsec.inc");
require_once("vpn.inc");

if (!is_array($config['ipsec']['phase1']))
	$config['ipsec']['phase1'] = array();

if (!is_array($config['ipsec']['phase2']))
	$config['ipsec']['phase2'] = array();

$a_phase1 = &$config['ipsec']['phase1'];
$a_phase2 = &$config['ipsec']['phase2'];

$pconfig['enable'] = isset($config['ipsec']['enable']);

if ($_POST) {
	if ($_POST['apply']) {
		$retval = 0;
		$retval = vpn_ipsec_configure();
		/* reload the filter in the background */
		filter_configure();
		$savemsg = get_std_save_message($retval);
		if ($retval >= 0) {
			if (is_subsystem_dirty('ipsec'))
				clear_subsystem_dirty('ipsec');
		}
	} else if ($_POST['submit']) {
		$pconfig = $_POST;

		$config['ipsec']['enable'] = $_POST['enable'] ? true : false;

		write_config();

		$retval = vpn_ipsec_configure();
	} else if (isset($_POST['del_x'])) {
		/* delete selected p1 entries */
		if (is_array($_POST['p1entry']) && count($_POST['p1entry'])) {
			foreach ($_POST['p1entry'] as $p1entrydel) {
				unset($a_phase1[$p1entrydel]);
			}
			if (write_config())
				mark_subsystem_dirty('ipsec');
		}
	} else if (isset($_POST['delp2_x'])) {
		/* delete selected p2 entries */
		if (is_array($_POST['p2entry']) && count($_POST['p2entry'])) {
			foreach ($_POST['p2entry'] as $p2entrydel) {
				unset($a_phase2[$p2entrydel]);
			}
			if (write_config())
				mark_subsystem_dirty('ipsec');
		}
	} else {
		/* yuck - IE won't send value attributes for image buttons, while Mozilla does - so we use .x/.y to find move button clicks instead... */
		unset($delbtn, $delbtnp2, $movebtn, $movebtnp2, $togglebtn, $togglebtnp2);
		foreach ($_POST as $pn => $pd) {
			if (preg_match("/del_(\d+)_x/", $pn, $matches)) {
				$delbtn = $matches[1];
			} else if (preg_match("/delp2_(\d+)_x/", $pn, $matches)) {
				$delbtnp2 = $matches[1];
			} else if (preg_match("/move_(\d+)_x/", $pn, $matches)) {
				$movebtn = $matches[1];
			} else if (preg_match("/movep2_(\d+)_x/", $pn, $matches)) {
				$movebtnp2 = $matches[1];
			} else if (preg_match("/toggle_(\d+)_x/", $pn, $matches)) {
				$togglebtn = $matches[1];
			} else if (preg_match("/togglep2_(\d+)_x/", $pn, $matches)) {
				$togglebtnp2 = $matches[1];
			}
		}

		$save = 1;

		/* move selected p1 entries before this */
		if (isset($movebtn) && is_array($_POST['p1entry']) && count($_POST['p1entry'])) {
			$a_phase1_new = array();

			/* copy all p1 entries < $movebtn and not selected */
			for ($i = 0; $i < $movebtn; $i++) {
				if (!in_array($i, $_POST['p1entry']))
					$a_phase1_new[] = $a_phase1[$i];
			}

			/* copy all selected p1 entries */
			for ($i = 0; $i < count($a_phase1); $i++) {
				if ($i == $movebtn)
					continue;
				if (in_array($i, $_POST['p1entry']))
					$a_phase1_new[] = $a_phase1[$i];
			}

			/* copy $movebtn p1 entry */
			if ($movebtn < count($a_phase1))
				$a_phase1_new[] = $a_phase1[$movebtn];

			/* copy all p1 entries > $movebtn and not selected */
			for ($i = $movebtn+1; $i < count($a_phase1); $i++) {
				if (!in_array($i, $_POST['p1entry']))
					$a_phase1_new[] = $a_phase1[$i];
			}
			if (count($a_phase1_new) > 0)
				$a_phase1 = $a_phase1_new;

		} else if (isset($movebtnp2) && is_array($_POST['p2entry']) && count($_POST['p2entry'])) {
			/* move selected p2 entries before this */
			$a_phase2_new = array();

			/* copy all p2 entries < $movebtnp2 and not selected */
			for ($i = 0; $i < $movebtnp2; $i++) {
				if (!in_array($i, $_POST['p2entry']))
					$a_phase2_new[] = $a_phase2[$i];
			}

			/* copy all selected p2 entries */
			for ($i = 0; $i < count($a_phase2); $i++) {
				if ($i == $movebtnp2)
					continue;
				if (in_array($i, $_POST['p2entry']))
					$a_phase2_new[] = $a_phase2[$i];
			}

			/* copy $movebtnp2 p2 entry */
			if ($movebtnp2 < count($a_phase2))
				$a_phase2_new[] = $a_phase2[$movebtnp2];

			/* copy all p2 entries > $movebtnp2 and not selected */
			for ($i = $movebtnp2+1; $i < count($a_phase2); $i++) {
				if (!in_array($i, $_POST['p2entry']))
					$a_phase2_new[] = $a_phase2[$i];
			}
			if (count($a_phase2_new) > 0)
				$a_phase2 = $a_phase2_new;

		} else if (isset($togglebtn)) {
			if (isset($a_phase1[$togglebtn]['disabled']))
				unset($a_phase1[$togglebtn]['disabled']);
			else
				$a_phase1[$togglebtn]['disabled'] = true;

		} else if (isset($togglebtnp2)) {
			if (isset($a_phase2[$togglebtnp2]['disabled']))
				unset($a_phase2[$togglebtnp2]['disabled']);
			else
				$a_phase2[$togglebtnp2]['disabled'] = true;

		} else if (isset($delbtn)) {
			/* remove static route if interface is not WAN */
			if ($a_phase1[$delbtn]['interface'] <> "wan")
				mwexec("/sbin/route delete -host {$a_phase1[$delbtn]['remote-gateway']}");

			/* remove all phase2 entries that match the ikeid */
			$ikeid = $a_phase1[$delbtn]['ikeid'];
			foreach ($a_phase2 as $p2index => $ph2tmp)
				if ($ph2tmp['ikeid'] == $ikeid) {
					unset($a_phase2[$p2index]);
				}

			unset($a_phase1[$delbtn]);

		} else if (isset($delbtnp2)) {
			unset($a_phase2[$delbtnp2]);

		} else
			$save = 0;

		if ($save === 1) {
			if (write_config())
				mark_subsystem_dirty('ipsec');
		}
	}
}

$pgtitle = array(gettext("VPN"),gettext("IPsec"));
$shortcut_section = "ipsec";

include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="vpn_ipsec.php" method="post">
<script type="text/javascript" src="/javascript/row_toggle.js"></script>
<?php
	if ($savemsg)
		print_info_box($savemsg);
	if ($pconfig['enable'] && is_subsystem_dirty('ipsec'))
		print_info_box_np(gettext("The IPsec tunnel configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="vpn ipsec">
	<tr>
		<td class="tabnavtbl">
<?php
			$tab_array = array();
			$tab_array[0] = array(gettext("Tunnels"), true, "vpn_ipsec.php");
			$tab_array[1] = array(gettext("Mobile clients"), false, "vpn_ipsec_mobile.php");
			$tab_array[2] = array(gettext("Pre-Shared Keys"), false, "vpn_ipsec_keys.php");
			$tab_array[3] = array(gettext("Advanced Settings"), false, "vpn_ipsec_settings.php");
			display_top_tabs($tab_array);
?>
		</td>
	</tr>
	<tr>
		<td>
			<div id="mainarea">
				<table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
					<tr>
						<td class="vtable">
							<table border="0" cellspacing="2" cellpadding="0" summary="enable">
								<tr>
									<td>
										<input name="enable" type="checkbox" id="enable" value="yes" <?php if ($pconfig['enable']) echo "checked=\"checked\"";?> />
									</td>
									<td>
										<strong><?=gettext("Enable IPsec"); ?></strong>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td>
							<input name="submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" />
						</td>
					</tr>
				</table>
				<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="phase-1 entries">
					<tr id="frheader">
						<td class="list">&nbsp;</td>
						<td class="list">&nbsp;</td>
						<td class="listhdrr"><?=gettext("IKE"); ?></td>
						<td class="listhdrr"><?=gettext("Remote Gateway"); ?></td>
						<td class="listhdrr"><?=gettext("Mode"); ?></td>
						<td class="listhdrr"><?=gettext("P1 Protocol"); ?></td>
						<td class="listhdrr"><?=gettext("P1 Transforms"); ?></td>
						<td class="listhdrr"><?=gettext("P1 Description"); ?></td>
						<td class="list">
						</td>
					</tr>
<?php
				$i = 0;
				foreach ($a_phase1 as $ph1ent):
					$iconfn = "pass";
					$spans = $spane = "";
					if (isset($ph1ent['disabled'])) {
						$spans = "<span class=\"gray\">";
						$spane = "</span>";
						$iconfn .= "_d";
					}
?>
					<tr valign="top" id="fr<?=$i;?>" ondblclick="document.location='vpn_ipsec_phase1.php?p1index=<?=$i;?>'">
						<td class="listt" align="center" valign="middle">
							<input type="checkbox" id="frc<?=$i;?>" name="p1entry[]" value="<?=$i;?>" onclick="fr_bgcolor('<?=$i;?>')" style="margin: 0; padding: 0; width: 15px; height: 15px;" />
						</td>
						<td class="listt" align="center" valign="middle">
							<input name="toggle_<?=$i;?>" src="/themes/<?= $g['theme']; ?>/images/icons/icon_<?=$iconfn?>.gif"
								title="<?=gettext("click to toggle enabled/disabled status");?>"
								type="image" style="height:11;width:11;border:0" />
						</td>
						<td class="listlr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>">
							<?=$spans;?>
<?php
							if (empty($ph1ent['iketype']) || $ph1ent['iketype'] == "ikev1")
								echo "V1";
							else
								echo "V2";
?>
							<?=$spane;?>
						</td>
						<td class="listr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>">
							<?=$spans;?>
<?php
							if ($ph1ent['interface']) {
								$iflabels = get_configured_interface_with_descr();

								$carplist = get_configured_carp_interface_list();
								foreach ($carplist as $cif => $carpip)
									$iflabels[$cif] = $carpip." (".get_vip_descr($carpip).")";

								$aliaslist = get_configured_ip_aliases_list();
								foreach ($aliaslist as $aliasip => $aliasif)
									$iflabels[$aliasip] = $aliasip." (".get_vip_descr($aliasip).")";

								$grouplist = return_gateway_groups_array();
								foreach ($grouplist as $name => $group) {
									if($group[0]['vip'] <> "")
										$vipif = $group[0]['vip'];
									else
										$vipif = $group[0]['int'];
									$iflabels[$name] = "GW Group {$name}";
								}
								$if = htmlspecialchars($iflabels[$ph1ent['interface']]);
							}
							else
								$if = "WAN";

							if (!isset($ph1ent['mobile']))
								echo $if."<br />".$ph1ent['remote-gateway'];
							else
								echo $if."<br /><strong>" . gettext("Mobile Client") . "</strong>";
?>
							<?=$spane;?>
						</td>
						<td class="listr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>">
							<?=$spans;?>
							<?=$ph1ent['mode'];?>
							<?=$spane;?>
						</td>
						<td class="listr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>">
							<?=$spans;?>
							<?=$p1_ealgos[$ph1ent['encryption-algorithm']['name']]['name'];?>
<?php
							if ($ph1ent['encryption-algorithm']['keylen']) {
								if ($ph1ent['encryption-algorithm']['keylen']=="auto")
									echo " (" . gettext("auto") . ")";
								else
									echo " ({$ph1ent['encryption-algorithm']['keylen']} " . gettext("bits") . ")";
							}
?>
							<?=$spane;?>
						</td>
						<td class="listr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>">
							<?=$spans;?>
							<?=$p1_halgos[$ph1ent['hash-algorithm']];?>
							<?=$spane;?>
						</td>
						<td class="listbg" onclick="fr_toggle(<?=$i;?>)">
							<?=$spans;?>
							<?=htmlspecialchars($ph1ent['descr']);?>&nbsp;
							<?=$spane;?>
						</td>
						<td valign="middle" class="list nowrap">
							<table border="0" cellspacing="0" cellpadding="1" summary="icons">
								<tr>
									<td>
										<input onmouseover="fr_insline(<?=$i;?>, true)" onmouseout="fr_insline(<?=$i;?>, false)"
											name="move_<?=$i;?>" src="/themes/<?= $g['theme']; ?>/images/icons/icon_left.gif"
											title="<?=gettext("move selected entries before this");?>"
											type="image" style="height:17;width:17;border:0" />
									</td>
									<td>
										<a href="vpn_ipsec_phase1.php?p1index=<?=$i;?>">
											<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" title="<?=gettext("edit phase1 entry"); ?>" width="17" height="17" border="0" alt="edit" />
										</a>
									</td>
								</tr>
								<tr>
									<td>
										<input name="del_<?=$i;?>" src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif"
											title="<?=gettext("delete phase1 entry");?>"
											type="image" style="height:17;width:17;border:0"
											onclick="return confirm('<?=gettext("Do you really want to delete this phase1 and all associated phase2 entries?"); ?>')" />
									</td>
									<td>
<?php
							if (!isset($ph1ent['mobile'])):
?>
										<a href="vpn_ipsec_phase1.php?dup=<?=$i;?>">
											<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("copy phase1 entry"); ?>" width="17" height="17" border="0" alt="add" />
										</a>
<?php
							endif;
?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class="listt">&nbsp;</td>
						<td class="listt">&nbsp;</td>
						<td class="listrborder" colspan="6">
<?php
							if (isset($_POST["tdph2-{$i}-visible"]))
								$tdph2_visible = htmlspecialchars($_POST["tdph2-{$i}-visible"]);
							else
								$tdph2_visible = 0;
?>
							<input type="hidden" name="tdph2-<?=$i;?>-visible" id="tdph2-<?=$i;?>-visible" value="<?=$tdph2_visible?>" />
							<div id="shph2but-<?=$i?>" <?php echo ($tdph2_visible == '1' ? 'style="display:none"' : '');?>>
<?php
							$phase2count=0;
							foreach ($a_phase2 as $ph2ent) {
								if ($ph2ent['ikeid'] != $ph1ent['ikeid'])
									continue;
								$phase2count++;
							}
							$fr_prefix = "frp2{$i}";
							$fr_header = $fr_prefix . "header";
?>
								<input type="button" onclick="show_phase2('tdph2-<?=$i?>','shph2but-<?=$i?>')" value="+" /> - <?php printf(gettext("Show %s Phase-2 entries"), $phase2count); ?>
							</div>
							<div id="tdph2-<?=$i?>" <?php echo ($tdph2_visible != '1' ? 'style="display:none"' : '');?>>
							<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0" summary="phase-2 entries">
							<tr id="<?=$fr_header;?>">
									<td>&nbsp;</td>
									<td>&nbsp;</td>
									<td class="listhdrr"><?=gettext("Mode"); ?></td>
									<td class="listhdrr"><?=gettext("Local Subnet"); ?></td>
									<td class="listhdrr"><?=gettext("Remote Subnet"); ?></td>
									<td class="listhdrr"><?=gettext("P2 Protocol"); ?></td>
									<td class="listhdrr"><?=gettext("P2 Transforms"); ?></td>
									<td class="listhdrr"><?=gettext("P2 Auth Methods"); ?></td>
									<td class ="list">&nbsp;</td>
								</tr>
<?php
								$j = 0;
								foreach ($a_phase2 as $ph2index => $ph2ent):
									if ($ph2ent['ikeid'] != $ph1ent['ikeid'])
										continue;

									$fr_c = $fr_prefix . "c" . $j;
									$fr_d = $fr_prefix . "d" . $j;

									$iconfn = "pass";
									$spans = $spane = "";
									if (isset($ph2ent['disabled'])) {
										$spans = "<span class=\"gray\">";
										$spane = "</span>";
										$iconfn .= "_d";
									}
?>
								<tr valign="top" id="<?=$fr_prefix . $j;?>" ondblclick="document.location='vpn_ipsec_phase2.php?p2index=<?=$ph2ent['uniqid'];?>'">
									<td class="listt" align="center" valign="middle">
									<input type="checkbox" id="<?=$fr_c;?>" name="p2entry[]" value="<?=$ph2index;?>" onclick="fr_bgcolor('<?=$j;?>', '<?=$fr_prefix;?>')" style="margin: 0; padding: 0; width: 15px; height: 15px;" />
									</td>
									<td class="listt" align="center" valign="middle">
										<input name="togglep2_<?=$ph2index;?>" src="/themes/<?= $g['theme']; ?>/images/icons/icon_<?=$iconfn?>.gif"
											title="<?=gettext("click to toggle enabled/disabled status");?>"
											type="image" style="height:11;width:11;border:0" />
									</td>
									<td class="listlr nowrap" id="<?=$fr_d;?>" onclick="fr_toggle('<?=$j;?>', '<?=$fr_prefix;?>')">
										<?=$spans;?>
										<?=$ph2ent['mode'];?>
										<?=$spane;?>
									</td>
<?php
									if(($ph2ent['mode'] == "tunnel") or ($ph2ent['mode'] == "tunnel6")):
?>
										<td class="listr nowrap" id="<?=$fr_d;?>" onclick="fr_toggle('<?=$j;?>', '<?=$fr_prefix;?>')">
											<?=$spans;?>
												<?=ipsec_idinfo_to_text($ph2ent['localid']); ?>
											<?=$spane;?>
										</td>
										<td class="listr nowrap" id="<?=$fr_d;?>" onclick="fr_toggle('<?=$j;?>', '<?=$fr_prefix;?>')">
											<?=$spans;?>
												<?=ipsec_idinfo_to_text($ph2ent['remoteid']); ?>
											<?=$spane;?>
										</td>
<?php
									else:
										echo "<td class=\"listr nowrap\">&nbsp;</td><td class=\"listr nowrap\">&nbsp;</td>";
									endif;
?>
									<td class="listr nowrap" id="<?=$fr_d;?>" onclick="fr_toggle('<?=$j;?>', '<?=$fr_prefix;?>')">
										<?=$spans;?>
										<?php echo $p2_protos[$ph2ent['protocol']]; ?>
										<?=$spane;?>
									</td>
									<td class="listr" id="<?=$fr_d;?>" onclick="fr_toggle('<?=$j;?>', '<?=$fr_prefix;?>')">
										<?=$spans;?>
<?php
										foreach ($ph2ent['encryption-algorithm-option'] as $k => $ph2ea) {
											if ($k)
												echo ", ";
											echo $p2_ealgos[$ph2ea['name']]['name'];
											if ($ph2ea['keylen']) {
												if ($ph2ea['keylen']=="auto")
													echo " (" . gettext("auto") . ")";
												else
													echo " ({$ph2ea['keylen']} " . gettext("bits") . ")";
											}
										}
?>
										<?=$spane;?>
									</td>
									<td class="listr nowrap" id="<?=$fr_d;?>" onclick="fr_toggle('<?=$j;?>', '<?=$fr_prefix;?>')">
										<?=$spans;?>
<?php
										if (!empty($ph2ent['hash-algorithm-option']) && is_array($ph2ent['hash-algorithm-option'])) {
											foreach ($ph2ent['hash-algorithm-option'] as $k => $ph2ha) {
												if ($k)
													echo ", ";
												echo $p2_halgos[$ph2ha];
											}
										}
?>
										<?=$spane;?>
									</td>
									<td class="list nowrap" valign="middle">
										<input onmouseover="fr_insline(<?=$j;?>, true, '<?=$fr_prefix;?>')" onmouseout="fr_insline(<?=$j;?>, false, '<?=$fr_prefix;?>')"
											name="movep2_<?=$j;?>" src="/themes/<?= $g['theme']; ?>/images/icons/icon_left.gif"
											title="<?=gettext("move selected entries before this");?>"
											type="image" style="height:17;width:17;border:0" />
										<a href="vpn_ipsec_phase2.php?p2index=<?=$ph2ent['uniqid'];?>">
											<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" title="<?=gettext("edit phase2 entry"); ?>" width="17" height="17" border="0" alt="edit" />
										</a>
										<input name="delp2_<?=$ph2index;?>" src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif"
											title="<?=gettext("delete phase2 entry");?>"
											type="image" style="height:17;width:17;border:0"
											onclick="return confirm('<?=gettext("Do you really want to delete this phase2 entry?"); ?>')" />
										<a href="vpn_ipsec_phase2.php?dup=<?=$ph2ent['uniqid'];?>">
											<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("add a new Phase 2 based on this one"); ?>" width="17" height="17" border="0" alt="add" />
										</a>
									</td>
								</tr>
<?php
									$j++;
								endforeach;
?>
								<tr valign="top" id="<?=$fr_prefix . $j;?>">
									<td class="list" colspan="8"></td>
									<td class="list nowrap" valign="middle">
<?php
									if ($j == 0):
?>
										<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_left_d.gif" width="17" height="17" title="<?=gettext("move selected phase2 entries to end");?>" border="0" alt="move" />
<?php
									else:
?>
										<input onmouseover="fr_insline(<?=$j;?>, true, '<?=$fr_prefix;?>')" onmouseout="fr_insline(<?=$j;?>, false, '<?=$fr_prefix;?>')" name="movep2_<?=$j;?>" type="image" src="/themes/<?= $g['theme']; ?>/images/icons/icon_left.gif" style="width:17;height:17;border:0" title="<?=gettext("move selected phase2 entries to end");?>" />
<?php
									endif;
?>
										<a href="vpn_ipsec_phase2.php?ikeid=<?=$ph1ent['ikeid'];?><?php if (isset($ph1ent['mobile'])) echo "&amp;mobile=true";?>">
											<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("add phase2 entry"); ?>" width="17" height="17" border="0" alt="add" />
										</a>
<?php
									if ($j == 0):
?>
										<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x_d.gif" width="17" height="17" title="<?=gettext("delete selected phase2 entries");?>" border="0" alt="delete" />
<?php
									else:
?>
										<input name="delp2" type="image" src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" style="width:17;height:17" title="<?=gettext("delete selected phase2 entries");?>" onclick="return confirm('<?=gettext("Do you really want to delete the selected phase2 entries?");?>')" />
<?php
									endif;
?>
									</td>
								</tr>
							</table>
							</div>
						</td>
					</tr>
<?php
					$i++;
				endforeach;  // $a_phase1 as $ph1ent
?>
					<tr valign="top" id="fr<?=$i;?>">
						<td class="list" colspan="8"></td>
						<td class="list nowrap" valign="middle">
							<table border="0" cellspacing="0" cellpadding="1" summary="edit">
								<tr>
									<td>
<?php
									if ($i == 0):
?>
										<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_left_d.gif" width="17" height="17" title="<?=gettext("move selected phase1 entries to end");?>" border="0" alt="move" />
<?php
									else:
?>
										<input onmouseover="fr_insline(<?=$i;?>, true)" onmouseout="fr_insline(<?=$i;?>, false)" name="move_<?=$i;?>" type="image" src="/themes/<?= $g['theme']; ?>/images/icons/icon_left.gif" style="width:17;height:17;border:0" title="<?=gettext("move selected phase1 entries to end");?>" />
<?php
									endif;
?>
									</td>
									<td>
										<a href="vpn_ipsec_phase1.php">
											<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="<?=gettext("add new phase1");?>" alt="add" />
										</a>
									</td>
								</tr>
								<tr>
									<td>
<?php
									if ($i == 0):
?>
										<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x_d.gif" width="17" height="17" title="<?=gettext("delete selected phase1 entries");?>" border="0" alt="delete" />
<?php
									else:
?>
										<input name="del" type="image" src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" style="width:17;height:17" title="<?=gettext("delete selected phase1 entries");?>" onclick="return confirm('<?=gettext("Do you really want to delete the selected phase1 entries?");?>')" />
<?php
									endif;
?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td colspan="8">
							<p>
								<span class="vexpl">
									<span class="red">
										<strong><?=gettext("Note"); ?>:<br /></strong>
									</span>
									<?=gettext("You can check your IPsec status at"); ?> <a href="diag_ipsec.php"><?=gettext("Status:IPsec"); ?></a>.<br />
									<?=gettext("IPsec Debug Mode can be enabled at"); ?> <a href="vpn_ipsec_settings.php"><?=gettext("VPN:IPsec:Advanced Settings"); ?></a>.<br />
									<?=gettext("IPsec can be set to prefer older SAs at"); ?> <a href="vpn_ipsec_settings.php"><?=gettext("VPN:IPsec:Advanced Settings"); ?></a>.
								</span>
							</p>
						</td>
					</tr>
				</table>
			</div>
		</td>
	</tr>
</table>
</form>
<?php include("fend.inc"); ?>
<script type="text/javascript">
//<![CDATA[
function show_phase2(id, buttonid) {
	document.getElementById(buttonid).innerHTML='';
	document.getElementById(id).style.display = "block";
	var visible = id + '-visible';
	document.getElementById(visible).value = "1";
}
//]]>
</script>
</body>
</html>
