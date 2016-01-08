<?php
/*
	vpn_ipsec.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *  Some or all of this file is based on the m0n0wall project which is
 *  Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-vpn-ipsec
##|*NAME=VPN: IPsec
##|*DESCR=Allow access to the 'VPN: IPsec' page.
##|*MATCH=vpn_ipsec.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("ipsec.inc");
require_once("vpn.inc");

if (!is_array($config['ipsec']['phase1'])) {
	$config['ipsec']['phase1'] = array();
}

if (!is_array($config['ipsec']['phase2'])) {
	$config['ipsec']['phase2'] = array();
}

$a_phase1 = &$config['ipsec']['phase1'];
$a_phase2 = &$config['ipsec']['phase2'];

if ($_POST) {

	if ($_POST['apply']) {
		$retval = vpn_ipsec_configure();
		/* reload the filter in the background */
		filter_configure();
		$savemsg = get_std_save_message($retval);
		if ($retval >= 0) {
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
			if (write_config()) {
				mark_subsystem_dirty('ipsec');
			}
		}
	} else if (isset($_POST['delp2'])) {
		/* delete selected p2 entries */
		if (is_array($_POST['p2entry']) && count($_POST['p2entry'])) {
			foreach ($_POST['p2entry'] as $p2entrydel) {
				unset($a_phase2[$p2entrydel]);
			}
			if (write_config()) {
				mark_subsystem_dirty('ipsec');
			}
		}
	} else {
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
				$a_phase1[$togglebtn]['disabled'] = true;
			}
		} else if (isset($togglebtnp2)) {
			if (isset($a_phase2[$togglebtnp2]['disabled'])) {
				unset($a_phase2[$togglebtnp2]['disabled']);
			} else {
				$a_phase2[$togglebtnp2]['disabled'] = true;
			}
		} else if (isset($delbtn)) {
			/* remove static route if interface is not WAN */
			if ($a_phase1[$delbtn]['interface'] <> "wan") {
				mwexec("/sbin/route delete -host {$a_phase1[$delbtn]['remote-gateway']}");
			}

			/* remove all phase2 entries that match the ikeid */
			$ikeid = $a_phase1[$delbtn]['ikeid'];
			foreach ($a_phase2 as $p2index => $ph2tmp) {
				if ($ph2tmp['ikeid'] == $ikeid) {
					unset($a_phase2[$p2index]);
				}
			}
			unset($a_phase1[$delbtn]);

		} else if (isset($delbtnp2)) {
			unset($a_phase2[$delbtnp2]);

		} else {
			$save = 0;
		}

		if ($save === 1) {
			if (write_config()) {
				mark_subsystem_dirty('ipsec');
			}
		}
	}
}

$pgtitle = array(gettext("VPN"), gettext("IPsec"), gettext("Tunnels"));
$shortcut_section = "ipsec";

include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Tunnels"), true, "vpn_ipsec.php");
$tab_array[] = array(gettext("Mobile Clients"), false, "vpn_ipsec_mobile.php");
$tab_array[] = array(gettext("Pre-Shared Keys"), false, "vpn_ipsec_keys.php");
$tab_array[] = array(gettext("Advanced Settings"), false, "vpn_ipsec_settings.php");
display_top_tabs($tab_array);
?>

<script type="text/javascript" src="/javascript/row_toggle.js"></script>

<?php
	if ($savemsg) {
		print_info_box($savemsg, 'success');
	}

	if (is_subsystem_dirty('ipsec')) {
		print_info_box_np(gettext("The IPsec tunnel configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));
	}
?>

<form name="mainform" method="post">
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('IPsec tunnels')?></h2></div>
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
						<th><?=gettext("P1 Description")?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody class="p1-entries">
<?php $i = 0; foreach ($a_phase1 as $ph1ent): ?>
<?php
	$iconfn = "pass";

	$entryStatus = (isset($ph1ent['disabled']) ? 'disabled' : 'enabled');

	if ($entryStatus == 'disabled') {
		$iconfn .= "_d";
	}
?>
					<tr id="fr<?=$i?>" onclick="fr_toggle(<?=$i?>)" id="frd<?=$i?>" ondblclick="document.location='vpn_ipsec_phase1.php?p1index=<?=$i?>'" class="<?= $entryStatus ?>">
						<td>
							<input type="checkbox" id="frc<?=$i?>" onclick="fr_toggle(<?=$i?>)" name="p1entry[]" value="<?=$i?>"  />
							<a	class="fa fa-anchor" id="Xmove_<?=$i?>" title="<?=gettext("Move checked entries to here")?>"></a>
						</td>
						<td>
							<button value="toggle_<?=$i?>" name="toggle_<?=$i?>" title="<?=gettext("click to toggle enabled/disabled status")?>" class="btn btn-xs btn-default" type="submit"><?= ($entryStatus == 'disabled' ? 'enable' : 'disable') ?></button>
						</td>
						<td id="frd<?=$i?>">
<?php
			if (empty($ph1ent['iketype']) || $ph1ent['iketype'] == "ikev1") {
				echo "V1";
			} else {
				echo "V2";
			}
?>
						</td>
						<td>
<?php
			if ($ph1ent['interface']) {
				$iflabels = get_configured_interface_with_descr();

				$carplist = get_configured_carp_interface_list();
				foreach ($carplist as $cif => $carpip) {
					$iflabels[$cif] = $carpip." (".get_vip_descr($carpip).")";
				}

				$aliaslist = get_configured_ip_aliases_list();
				foreach ($aliaslist as $aliasip => $aliasif) {
					$iflabels[$aliasip] = $aliasip." (".get_vip_descr($aliasip).")";
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
				$if = htmlspecialchars($iflabels[$ph1ent['interface']]);
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
					if (empty($ph1ent['iketype']) || $ph1ent['iketype'] == "ikev1") {
						echo "{$ph1ent['mode']}";
					}
					?>
					<?=$spane?>
				</td>
				<td id="frd<?=$i?>">
					<?=$p1_ealgos[$ph1ent['encryption-algorithm']['name']]['name']?>
<?php
			if ($ph1ent['encryption-algorithm']['keylen']) {
				if ($ph1ent['encryption-algorithm']['keylen'] == "auto") {
					echo " (" . gettext("auto") . ")";
				} else {
					echo " ({$ph1ent['encryption-algorithm']['keylen']} " . gettext("bits") . ")";
				}
			}
?>
						</td>
						<td>
							<?=$p1_halgos[$ph1ent['hash-algorithm']]?>
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
			if (isset($_POST["tdph2-{$i}-visible"])) {
				$tdph2_visible = htmlspecialchars($_POST["tdph2-{$i}-visible"]);
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
								<input type="button" onclick="show_phase2('tdph2-<?=$i?>','shph2but-<?=$i?>')" value="+" /> - <?php printf(gettext("Show %s Phase-2 entries"), $phase2count); ?>
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
												<button value="togglep2_<?=$ph2index?>" name="togglep2_<?=$ph2index?>" title="<?=gettext("click to toggle enabled/disabled status")?>" class="btn btn-xs btn-default" type="submit"><?= ($entryStatus == 'disabled'? 'enable' : 'disable') ?></button>
											</td>
											<td id="<?=$fr_d?>" onclick="fr_toggle('<?=$j?>', '<?=$fr_prefix?>')">
												<?=$ph2ent['mode']?>
											</td>
<?php if (($ph2ent['mode'] == "tunnel") or ($ph2ent['mode'] == "tunnel6")): ?>
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
												<a	class="fa fa-trash no-confirm" id="Xdelp2_<?=$i?>" title="<?=gettext('Delete phase2 entry'); ?>"></a>
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
		<a href="vpn_ipsec_phase1.php" class="btn btn-success btn-sm">
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
	<?=print_info_box('<strong>' . gettext("Note:") . '</strong><br />' .
	gettext("You can check your IPsec status at ") . '<a href="status_ipsec.php">' . gettext("Status:IPsec") . '</a>.<br />' .
	gettext("IPsec Debug Mode can be enabled at ") . '<a href="vpn_ipsec_settings.php">' .gettext("VPN:IPsec:Advanced Settings") . '</a>.<br />' .
	gettext("IPsec can be set to prefer older SAs at ") . '<a href="vpn_ipsec_settings.php">' . gettext("VPN:IPsec:Advanced Settings") . '</a>', 'info')?>
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
		$('#' + event.target.id.slice(1)).click();
	});

	$('[id^=Xdel_]').click(function (event) {
		if (confirm("<?=gettext('Are you sure you wish to delete this P1 entry?')?>")) {
			$('#' + event.target.id.slice(1)).click();
		}
	});

	$('[id^=Xdelp2_]').click(function (event) {
		if (confirm("<?=gettext('Are you sure you wish to delete this P2 entry?')?>")) {
			$('#' + event.target.id.slice(1)).click();
		}
	});
});
//]]>
</script>

<?php
include("foot.inc");
