<?php
/*
 * vpn_ipsec.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-vpn-ipsec
##|*NAME=VPN: IPsec
##|*DESCR=Allow access to the 'VPN: IPsec' page.
##|*MATCH=vpn_ipsec.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("ipsec.inc");
require_once("vpn.inc");

init_config_arr(array('ipsec', 'phase1'));
init_config_arr(array('ipsec', 'phase2'));
$a_phase1 = &$config['ipsec']['phase1'];
$a_phase2 = &$config['ipsec']['phase2'];


if ($_POST['apply']) {
	$ipsec_dynamic_hosts = ipsec_configure();
	/* reload the filter in the background */
	$retval = 0;
	$retval |= filter_configure();
	if ($ipsec_dynamic_hosts >= 0) {
		if (is_subsystem_dirty('ipsec')) {
			clear_subsystem_dirty('ipsec');
		}
	}
} else if (isset($_POST['del'])) {
	/* delete selected p1 entries */
	if (is_array($_POST['p1entry']) && count($_POST['p1entry'])) {
		foreach ($_POST['p1entry'] as $p1entrydel) {
			unset($a_phase1[$p1entrydel]);
		}
		if (write_config(gettext("Deleted selected IPsec Phase 1 entries."))) {
			mark_subsystem_dirty('ipsec');
		}
	}
} else if (isset($_POST['delp2'])) {
	/* delete selected p2 entries */
	if (is_array($_POST['p2entry']) && count($_POST['p2entry'])) {
		foreach ($_POST['p2entry'] as $p2entrydel) {
			if (is_interface_ipsec_vti_assigned($a_phase2[$p2entrydel]) && ($a_phase2[$p2entrydel]['mode'] == 'vti')) {
				$input_errors[] = gettext("Cannot delete a VTI Phase 2 while the interface is assigned. Remove the interface assignment before deleting this P2.");
			} else {
				unset($a_phase2[$p2entrydel]);
			}
		}
		if (write_config(gettext("Deleted selected IPsec Phase 2 entries."))) {
			mark_subsystem_dirty('ipsec');
		}
	}
} else  {
	/* yuck - IE won't send value attributes for image buttons, while Mozilla does - so we use .x/.y to find move button clicks instead... */

	// TODO: this. is. nasty.
	unset($delbtn, $delbtnp2, $movebtn, $movebtnp2, $togglebtn, $togglebtnp2);
	foreach ($_POST as $pn => $pd) {
		if (preg_match("/del_(\d+)/", $pn, $matches)) {
			$delbtn = $matches[1];
		} else if (preg_match("/delp2_(\d+)/", $pn, $matches)) {
			$delbtnp2 = $matches[1];
		} else if (preg_match("/move_(\d+)/", $pn, $matches)) {
			$movebtn = $matches[1];
		} else if (preg_match("/movep2_(\d+)/", $pn, $matches)) {
			$movebtnp2 = $matches[1];
		} else if (preg_match("/toggle_(\d+)/", $pn, $matches)) {
			$togglebtn = $matches[1];
		} else if (preg_match("/togglep2_(\d+)/", $pn, $matches)) {
			$togglebtnp2 = $matches[1];
		}
	}

	$save = 1;

	/* move selected p1 entries before this */
	if (isset($movebtn) && is_array($_POST['p1entry']) && count($_POST['p1entry'])) {
		$a_phase1_new = array();

		/* copy all p1 entries < $movebtn and not selected */
		for ($i = 0; $i < $movebtn; $i++) {
			if (!in_array($i, $_POST['p1entry'])) {
				$a_phase1_new[] = $a_phase1[$i];
			}
		}

		/* copy all selected p1 entries */
		for ($i = 0; $i < count($a_phase1); $i++) {
			if ($i == $movebtn) {
				continue;
			}
			if (in_array($i, $_POST['p1entry'])) {
				$a_phase1_new[] = $a_phase1[$i];
			}
		}

		/* copy $movebtn p1 entry */
		if ($movebtn < count($a_phase1)) {
			$a_phase1_new[] = $a_phase1[$movebtn];
		}

		/* copy all p1 entries > $movebtn and not selected */
		for ($i = $movebtn+1; $i < count($a_phase1); $i++) {
			if (!in_array($i, $_POST['p1entry'])) {
				$a_phase1_new[] = $a_phase1[$i];
			}
		}
		if (count($a_phase1_new) > 0) {
			$a_phase1 = $a_phase1_new;
		}

	} else if (isset($movebtnp2) && is_array($_POST['p2entry']) && count($_POST['p2entry'])) {
		/* move selected p2 entries before this */
		$a_phase2_new = array();

		/* copy all p2 entries < $movebtnp2 and not selected */
		for ($i = 0; $i < $movebtnp2; $i++) {
			if (!in_array($i, $_POST['p2entry'])) {
				$a_phase2_new[] = $a_phase2[$i];
			}
		}

		/* copy all selected p2 entries */
		for ($i = 0; $i < count($a_phase2); $i++) {
			if ($i == $movebtnp2) {
				continue;
			}
			if (in_array($i, $_POST['p2entry'])) {
				$a_phase2_new[] = $a_phase2[$i];
			}
		}

		/* copy $movebtnp2 p2 entry */
		if ($movebtnp2 < count($a_phase2)) {
			$a_phase2_new[] = $a_phase2[$movebtnp2];
		}

		/* copy all p2 entries > $movebtnp2 and not selected */
		for ($i = $movebtnp2+1; $i < count($a_phase2); $i++) {
			if (!in_array($i, $_POST['p2entry'])) {
				$a_phase2_new[] = $a_phase2[$i];
			}
		}
		if (count($a_phase2_new) > 0) {
			$a_phase2 = $a_phase2_new;
		}

	} else if (isset($togglebtn)) {
		if (isset($a_phase1[$togglebtn]['disabled'])) {
			unset($a_phase1[$togglebtn]['disabled']);
		} else {
			if (ipsec_vti($a_phase1[$togglebtn])) {
				$input_errors[] = gettext("Cannot disable a Phase 1 with a child Phase 2 while the interface is assigned. Remove the interface assignment before disabling this P2.");
			} else {
				$a_phase1[$togglebtn]['disabled'] = true;
			}
		}
	} else if (isset($togglebtnp2)) {
		if (isset($a_phase2[$togglebtnp2]['disabled'])) {
			unset($a_phase2[$togglebtnp2]['disabled']);
		} else {
			if (is_interface_ipsec_vti_assigned($a_phase2[$togglebtnp2]) && ($a_phase2[$togglebtnp2]['mode'] == 'vti')) {
				$input_errors[] = gettext("Cannot disable a VTI Phase 2 while the interface is assigned. Remove the interface assignment before disabling this P2.");
			} else {
				$a_phase2[$togglebtnp2]['disabled'] = true;
			}
		}
	} else if (isset($delbtn)) {
		/* remove static route if interface is not WAN */
		if ($a_phase1[$delbtn]['interface'] <> "wan") {
			$rgateway = exec("/sbin/route -n get {$a_phase1[$delbtn]['remote-gateway']} | /usr/bin/awk '/gateway:/ {print $2;}'");
			mwexec("/sbin/route delete -host {$a_phase1[$delbtn]['remote-gateway']} " . escapeshellarg($rgateway));
		}

		/* remove all phase2 entries that match the ikeid */
		$ikeid = $a_phase1[$delbtn]['ikeid'];
		$p1_has_vti = false;
		$delp2ids = array();
		foreach ($a_phase2 as $p2index => $ph2tmp) {
			if ($ph2tmp['ikeid'] == $ikeid) {
				if (is_interface_ipsec_vti_assigned($ph2tmp)) {
					$p1_has_vti = true;
				} else {
					$delp2ids[] = $p2index;
				}
			}
		}

		if ($p1_has_vti) {
			$input_errors[] = gettext("Cannot delete a Phase 1 which contains an active VTI Phase 2 with an interface assigned. Remove the interface assignment before deleting this P1.");
		} else {
			foreach ($delp2ids as $dp2idx) {
				unset($a_phase2[$dp2idx]);
			}
			unset($a_phase1[$delbtn]);
		}

	} else if (isset($delbtnp2)) {
		if (is_interface_ipsec_vti_assigned($a_phase2[$delbtnp2]) && ($a_phase2[$delbtnp2]['mode'] == 'vti')) {
			$input_errors[] = gettext("Cannot delete a VTI Phase 2 while the interface is assigned. Remove the interface assignment before deleting this P2.");
		} else {
			unset($a_phase2[$delbtnp2]);
		}
	} else {
		$save = 0;
	}

	if ($save === 1) {
		if (write_config(gettext("Saved configuration changes for IPsec tunnels."))) {
			mark_subsystem_dirty('ipsec');
		}
	}
}


$pgtitle = array(gettext("VPN"), gettext("IPsec"), gettext("Tunnels"));
$pglinks = array("", "@self", "@self");
$shortcut_section = "ipsec";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array(gettext("Tunnels"), true, "vpn_ipsec.php");
$tab_array[] = array(gettext("Mobile Clients"), false, "vpn_ipsec_mobile.php");
$tab_array[] = array(gettext("Pre-Shared Keys"), false, "vpn_ipsec_keys.php");
$tab_array[] = array(gettext("Advanced Settings"), false, "vpn_ipsec_settings.php");
display_top_tabs($tab_array);

if ($_POST['apply']) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('ipsec')) {
	print_apply_box(gettext("The IPsec tunnel configuration has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
}
?>

<form name="mainform" method="post">
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('IPsec Tunnels')?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th>&nbsp;</th>
						<th>&nbsp;</th>
						<th><?=gettext("IKE")?></th>
						<th><?=gettext("Remote Gateway")?></th>
						<th><?=gettext("Mode")?></th>
						<th><?=gettext("P1 Protocol")?></th>
						<th><?=gettext("P1 Transforms")?></th>
						<th><?=gettext("P1 DH-Group")?></th>
						<th><?=gettext("P1 Description")?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody class="p1-entries">
<?php
$iflabels = get_configured_interface_with_descr(false, true);
$viplist = get_configured_vip_list();
foreach ($viplist as $vip => $address) {
	$iflabels[$vip] = $address;
	if (get_vip_descr($address)) {
		$iflabels[$vip] .= " (". get_vip_descr($address) .")";
	}
}
$grouplist = return_gateway_groups_array();
foreach ($grouplist as $name => $group) {
	if ($group[0]['vip'] != "") {
		$vipif = $group[0]['vip'];
	} else {
		$vipif = $group[0]['int'];
	}
	$iflabels[$name] = "GW Group {$name}";
}

$i = 0; foreach ($a_phase1 as $ph1ent):

	$iconfn = "pass";

	$entryStatus = (isset($ph1ent['disabled']) ? 'disabled' : 'enabled');

	if ($entryStatus == 'disabled') {
		$iconfn .= "_d";
	}
?>
					<tr id="fr<?=$i?>" onclick="fr_toggle(<?=$i?>)" id="frd<?=$i?>" ondblclick="document.location='vpn_ipsec_phase1.php?p1index=<?=$i?>'" class="<?= $entryStatus ?>">
						<td>
							<input type="checkbox" id="frc<?=$i?>" onclick="fr_toggle(<?=$i?>)" name="p1entry[]" value="<?=$i?>"  />
							<a	class="fa fa-anchor icon-pointer" id="Xmove_<?=$i?>" title="<?=gettext("Move checked entries to here")?>"></a>
						</td>
						<td>
							<button value="toggle_<?=$i?>" name="toggle_<?=$i?>" title="<?=gettext("click to toggle enabled/disabled status")?>" class="btn btn-xs btn-<?= ($entryStatus == 'disabled' ? 'success' : 'warning') ?>" type="submit"><?= ($entryStatus == 'disabled' ? 'Enable' : 'Disable') ?></button>
						</td>
						<td id="frd<?=$i?>">
<?php
			if (empty($ph1ent['iketype']) || $ph1ent['iketype'] == "ikev1") {
				echo "V1";
			} elseif ($ph1ent['iketype'] == "ikev2") {
				echo "V2";
			} elseif ($ph1ent['iketype'] == "auto") {
				echo "Auto";
			}
?>
						</td>
						<td>
<?php
			if ($ph1ent['interface']) {
				if (isset($iflabels[$ph1ent['interface']])) {
					$if = htmlspecialchars($iflabels[$ph1ent['interface']]);
				} else {
					$if = sprintf("Interface not found: '%s'", $ph1ent['interface']);
				}
			} else {
				$if = "WAN";
			}

			if (!isset($ph1ent['mobile'])) {
				echo $if."<br />".$ph1ent['remote-gateway'];
			} else {
				echo $if."<br /><strong>" . gettext("Mobile Client") . "</strong>";
			}
?>
						</td>
						<td id="frd<?=$i?>">
					<?=$spans?>
					<?php
					if (empty($ph1ent['iketype']) || $ph1ent['iketype'] == "ikev1" || $ph1ent['iketype'] == "auto") {
						echo "{$ph1ent['mode']}";
					}
					?>
					<?=$spane?>
				</td>
				<td id="frd<?=$i?>">
<?php
				$first = true;
				if (is_array($ph1ent['encryption']['item'])) {
					foreach($ph1ent['encryption']['item'] as $p1algo) {
						if (!$first) {
							echo "<br/>";
						}
						echo $p1_ealgos[$p1algo['encryption-algorithm']['name']]['name'];
						if ($p1algo['encryption-algorithm']['keylen']) {
							echo " ({$p1algo['encryption-algorithm']['keylen']} " . gettext("bits") . ")";
						}
						$first = false;
					}
				}
?>
						</td>
						<td>
<?php			$first = true;
				if (is_array($ph1ent['encryption']['item'])) {
					foreach($ph1ent['encryption']['item'] as $p1algo) {
						if (!$first) {
							echo "<br/>";
						}
						echo $p1_halgos[$p1algo['hash-algorithm']];
						$first = false;
					}
				}
				?>
						</td>
						<td>
<?php			$first = true;
				if (is_array($ph1ent['encryption']['item'])) {
					foreach($ph1ent['encryption']['item'] as $p1algo) {
						if (!$first) {
							echo "<br/>";
						}
						echo str_replace(" ","&nbsp;",$p1_dhgroups[$p1algo['dhgroup']]);
						$first = false;
					}
				}
				?>
						</td>
						<td>
							<?=htmlspecialchars($ph1ent['descr'])?>
						</td>
						<td style="cursor: pointer;">
<!--							<a	class="fa fa-anchor" id="Xmove_<?=$i?>" title="<?=gettext("Move checked entries to here")?>"></a> -->
							<button style="display: none;" class="btn btn-default btn-xs" type="submit" id="move_<?=$i?>" name="move_<?=$i?>" value="move_<?=$i?>"><?=gettext("Move checked entries to here")?></button>
							<a class="fa fa-pencil" href="vpn_ipsec_phase1.php?p1index=<?=$i?>" title="<?=gettext("Edit phase1 entry"); ?>"></a>
<?php if (!isset($ph1ent['mobile'])): ?>
							<a class="fa fa-clone" href="vpn_ipsec_phase1.php?dup=<?=$i?>" title="<?=gettext("Copy phase1 entry"); ?>"></a>
<?php endif; ?>
							<a	class="fa fa-trash no-confirm" id="Xdel_<?=$i?>" title="<?=gettext('Delete phase1 entry'); ?>"></a>
							<button style="display: none;" class="btn btn-xs btn-warning" type="submit" id="del_<?=$i?>" name="del_<?=$i?>" value="del_<?=$i?>" title="<?=gettext('Delete phase1 entry'); ?>">delete</button>

						</td>
					</tr>
					<tr class="<?= $entryStatus ?>">
						<td colspan="2"></td>
						<td colspan="7" class="contains-table">
<?php
			if (isset($_REQUEST["tdph2-{$i}-visible"])) {
				$tdph2_visible = htmlspecialchars($_REQUEST["tdph2-{$i}-visible"]);
			} else {
				$tdph2_visible = 0;
			}
?>
							<input type="hidden" name="tdph2-<?=$i?>-visible" id="tdph2-<?=$i?>-visible" value="<?=$tdph2_visible?>" />
							<div id="shph2but-<?=$i?>" <?=($tdph2_visible == '1' ? 'style="display:none"' : '')?>>
<?php
				$phase2count=0;

				foreach ($a_phase2 as $ph2ent) {
					if ($ph2ent['ikeid'] != $ph1ent['ikeid']) {
						continue;
					}
					$phase2count++;
				}
				$fr_prefix = "frp2{$i}";
				$fr_header = $fr_prefix . "header";
?>
								<button class="btn btn-info" type="button" onclick="show_phase2('tdph2-<?=$i?>','shph2but-<?=$i?>')" value="+"><i class="fa fa-plus-circle"></i> <?php printf(gettext("Show Phase 2 Entries (%s)"), $phase2count); ?></button>
							</div>
							<div id="tdph2-<?=$i?>" <?=($tdph2_visible != '1' ? 'style="display:none"' : '')?>>
								<table class="table table-striped table-hover">
									<thead>
										<tr>
											<th>&nbsp;</th>
											<th>&nbsp;</th>
											<th><?=gettext("Mode"); ?></th>
											<th><?=gettext("Local Subnet"); ?></th>
											<th><?=gettext("Remote Subnet"); ?></th>
											<th><?=gettext("P2 Protocol"); ?></th>
											<th><?=gettext("P2 Transforms"); ?></th>
											<th><?=gettext("P2 Auth Methods"); ?></th>
											<th><?=gettext("P2 actions")?></th>
										</tr>
									</thead>
									<tbody class="p2-entries">
<?php $j = 0; foreach ($a_phase2 as $ph2index => $ph2ent): ?>
<?php
						if ($ph2ent['ikeid'] != $ph1ent['ikeid']) {
							continue;
						}

						$fr_c = $fr_prefix . "c" . $j;
						$fr_d = $fr_prefix . "d" . $j;

						$iconfn = "pass";
						$entryStatus = (isset($ph2ent['disabled']) || isset($ph1ent['disabled']) ? 'disabled' : 'enabled');

						if ($entryStatus == 'disabled') {
							$iconfn .= "_d";
						}
?>
										<tr id="<?=$fr_prefix . $j?>" ondblclick="document.location='vpn_ipsec_phase2.php?p2index=<?=$ph2ent['uniqid']?>'" class="<?= $entryStatus ?>">
											<td>
												<input type="checkbox" id="<?=$fr_c?>" name="p2entry[]" value="<?=$ph2index?>" onclick="fr_bgcolor('<?=$j?>', '<?=$fr_prefix?>')" />
												<button class="fa fa-anchor button-icon" type="submit" name="movep2_<?=$j?>" value="movep2_<?=$j?>" title="<?=gettext("Move checked P2s here")?>"></button>
											</td>
											<td>
												<button value="togglep2_<?=$ph2index?>" name="togglep2_<?=$ph2index?>" title="<?=gettext("click to toggle enabled/disabled status")?>" class="btn btn-xs btn-<?= ($entryStatus == 'disabled'? 'success' : 'warning') ?>" type="submit"><?= ($entryStatus == 'disabled'? 'Enable' : 'Disable') ?></button>
											</td>
											<td id="<?=$fr_d?>" onclick="fr_toggle('<?=$j?>', '<?=$fr_prefix?>')">
												<?=$ph2ent['mode']?>
											</td>
<?php if (($ph2ent['mode'] == "tunnel") or ($ph2ent['mode'] == "tunnel6") or ($ph2ent['mode'] == "vti")): ?>
											<td id="<?=$fr_d?>" onclick="fr_toggle('<?=$j?>', '<?=$fr_prefix?>')">
												<?=ipsec_idinfo_to_text($ph2ent['localid']); ?>
											</td>
											<td id="<?=$fr_d?>" onclick="fr_toggle('<?=$j?>', '<?=$fr_prefix?>')">
												<?=ipsec_idinfo_to_text($ph2ent['remoteid']); ?>
											</td>
		<?php else: ?>
											<td colspan="2"></td>
<?php endif; ?>
											<td id="<?=$fr_d?>" onclick="fr_toggle('<?=$j?>', '<?=$fr_prefix?>')">
												<?=$p2_protos[$ph2ent['protocol']]; ?>
											</td>
											<td id="<?=$fr_d?>" onclick="fr_toggle('<?=$j?>', '<?=$fr_prefix?>')">
<?php
								foreach ($ph2ent['encryption-algorithm-option'] as $k => $ph2ea) {
									if ($k) {
										echo ", ";
									}
									echo $p2_ealgos[$ph2ea['name']]['name'];
									if ($ph2ea['keylen']) {
										if ($ph2ea['keylen'] == "auto") {
											echo " (" . gettext("auto") . ")";
										} else {
											echo " ({$ph2ea['keylen']} " . gettext("bits") . ")";
										}
									}
								}
?>
											</td>
											<td id="<?=$fr_d?>" onclick="fr_toggle('<?=$j?>', '<?=$fr_prefix?>')">
<?php
								if (!empty($ph2ent['hash-algorithm-option']) && is_array($ph2ent['hash-algorithm-option'])) {
									foreach ($ph2ent['hash-algorithm-option'] as $k => $ph2ha) {
										if ($k) {
											echo ", ";
										}
										echo $p2_halgos[$ph2ha];
									}
								}
?>
											</td>
											<td style="cursor: pointer;">
<!--												<button class="fa fa-anchor button-icon" type="submit" name="movep2_<?=$j?>" value="movep2_<?=$j?>" title="<?=gettext("Move checked P2s here")?>"></button> -->
												<a class="fa fa-pencil" href="vpn_ipsec_phase2.php?p2index=<?=$ph2ent['uniqid']?>" title="<?=gettext("Edit phase2 entry"); ?>"></a>
												<a class="fa fa-clone" href="vpn_ipsec_phase2.php?dup=<?=$ph2ent['uniqid']?>" title="<?=gettext("Add a new Phase 2 based on this one"); ?>"></a>
												<a	class="fa fa-trash no-confirm" id="Xdelp2_<?=$ph2index?>" title="<?=gettext('Delete phase2 entry'); ?>"></a>
												<button style="display: none;" class="btn btn-xs btn-warning" type="submit" id="delp2_<?=$ph2index?>" name="delp2_<?=$ph2index?>" value="delp2_<?=$ph2index?>" title="<?=gettext('delete phase2 entry'); ?>">delete</button>
											</td>
										</tr>
<?php $j++; endforeach; ?>
										<tr>
											<td></td>
											<td>
												<a class="btn btn-xs btn-success" href="vpn_ipsec_phase2.php?ikeid=<?=$ph1ent['ikeid']?><?php if (isset($ph1ent['mobile'])) echo "&amp;mobile=true"?>">
													<i class="fa fa-plus icon-embed-btn"></i>
													<?=gettext("Add P2")?>
												</a>
											</td>
											<td colspan="7"></td>
										</tr>
									</tbody>
								</table>
							</div>
						</td>
					</tr>
<?php
					$i++;
				endforeach;	 // $a_phase1 as $ph1ent
?>
				</tbody>
			</table>
		</div>
	</div>

	<nav class="action-buttons">
<?php
/*
	if ($i !== 0): ?>
	<input type="submit" name="move_<?=$i?>" class="btn btn-default" value="<?=gettext("move selected phase1 entries to end")?>" />
<?php endif;
*/
?>
		<a href="vpn_ipsec_phase1.php" class="btn btn-success btn-sm"  usepost>
			<i class="fa fa-plus icon-embed-btn"></i>
			<?=gettext("Add P1")?>
		</a>
<?php if ($i !== 0): ?>
		<button type="submit" name="del" class="btn btn-danger btn-sm" value="<?=gettext("Delete selected P1s")?>">
			<i class="fa fa-trash icon-embed-btn"></i>
			<?=gettext("Delete P1s")?>
		</button>
<?php endif; ?>
	</nav>
</form>

<div class="infoblock">
	<?php print_info_box(sprintf(gettext('The IPsec status can be checked at %1$s%2$s%3$s.'), '<a href="status_ipsec.php">', gettext("Status:IPsec"), '</a>') . '<br />' .
	sprintf(gettext('IPsec debug mode can be enabled at %1$s%2$s%3$s.'), '<a href="vpn_ipsec_settings.php">', gettext("VPN:IPsec:Advanced Settings"), '</a>') . '<br />' .
	sprintf(gettext('IPsec can be set to prefer older SAs at %1$s%2$s%3$s.'), '<a href="vpn_ipsec_settings.php">', gettext("VPN:IPsec:Advanced Settings"), '</a>'), 'info', false); ?>
</div>

<script type="text/javascript">
//<![CDATA[
function show_phase2(id, buttonid) {
	document.getElementById(buttonid).innerHTML='';
	document.getElementById(id).style.display = "block";
	var visible = id + '-visible';
	document.getElementById(visible).value = "1";
}

events.push(function() {
	$('[id^=Xmove_]').click(function (event) {
		// ToDo: We POST shift="yes" if the user has the shift key depressed, but that is not yet used
		// by the $_POST code. It is intended to allow the user to choose to move stuff to the row before or
		// after the clicked anchor icon
		if (event.shiftKey) {
			$('form').append('<input type="hidden" id="shift" name="shift" value="yes" />');
		}

		$('#' + event.target.id.slice(1)).click();
	});

	$('[id^=Xdel_]').click(function (event) {
		if (confirm("<?=gettext('Confirmation required to delete this P1 entry.')?>")) {
			$('#' + event.target.id.slice(1)).click();
		}
	});

	$('[id^=Xdelp2_]').click(function (event) {
		if (confirm("<?=gettext('Confirmation required to delete this P2 entry.')?>")) {
			$('#' + event.target.id.slice(1)).click();
		}
	});
});
//]]>
</script>

<?php
include("foot.inc");
