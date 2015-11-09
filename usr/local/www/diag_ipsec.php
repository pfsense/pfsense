<?php
/* $Id$ */
/*
	diag_ipsec.php
 * ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved. 
 *  portions Copyright (C) 2008 Shrew Soft Inc <mgrooms@shrew.net>.
 * 
 *  Parts of this code originally based on vpn_ipsec_sad.php from m0n0wall,
 *  Copyright (C) 2003-2004 Manuel Kasper (BSD 2 clause)
 *
 *  Redistribution and use in source and binary forms, with or without modification, 
 *  are permitted provided that the following conditions are met: 
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution. 
 *
 *  3. All advertising materials mentioning features or use of this software 
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense® software distribution. (http://www.pfsense.org/). 
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
  *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
 */

/*
	pfSense_MODULE:	ipsec
*/

##|+PRIV
##|*IDENT=page-status-ipsec
##|*NAME=Status: IPsec page
##|*DESCR=Allow access to the 'Status: IPsec' page.
##|*MATCH=diag_ipsec.php*
##|-PRIV


global $g;

$pgtitle = array(gettext("Status"),gettext("IPsec"));
$shortcut_section = "ipsec";

require("guiconfig.inc");
include("head.inc");
require("ipsec.inc");

if ($_GET['act'] == 'connect') {
	if (ctype_digit($_GET['ikeid'])) {
		$ph1ent = ipsec_get_phase1($_GET['ikeid']);
		if (!empty($ph1ent)) {
			if (empty($ph1ent['iketype']) || $ph1ent['iketype'] == 'ikev1') {
				$ph2entries = ipsec_get_number_of_phase2($_GET['ikeid']);
				for ($i = 0; $i < $ph2entries; $i++) {
					$connid = escapeshellarg("con{$_GET['ikeid']}00{$i}");
					mwexec("/usr/local/sbin/ipsec down {$connid}");
					mwexec("/usr/local/sbin/ipsec up {$connid}");
				}
			} else {
				mwexec("/usr/local/sbin/ipsec down con" . escapeshellarg($_GET['ikeid']));
				mwexec("/usr/local/sbin/ipsec up con" . escapeshellarg($_GET['ikeid']));
			}
		}
	}
} else if ($_GET['act'] == 'ikedisconnect') {
	if (ctype_digit($_GET['ikeid'])) {
		if (!empty($_GET['ikesaid']) && ctype_digit($_GET['ikesaid']))
			mwexec("/usr/local/sbin/ipsec down con" . escapeshellarg($_GET['ikeid']) . "[" . escapeshellarg($_GET['ikesaid']) . "]");
		else
			mwexec("/usr/local/sbin/ipsec down con" . escapeshellarg($_GET['ikeid']));
	}
} else if ($_GET['act'] == 'childdisconnect') {
	if (ctype_digit($_GET['ikeid'])) {
		if (!empty($_GET['ikesaid']) && ctype_digit($_GET['ikesaid']))
			mwexec("/usr/local/sbin/ipsec down con" . escapeshellarg($_GET['ikeid']) . "{" . escapeshellarg($_GET['ikesaid']) . "}");
	}
}

if (!is_array($config['ipsec']['phase1']))
    $config['ipsec']['phase1'] = array();

$a_phase1 = &$config['ipsec']['phase1'];

$status = ipsec_list_sa();

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" onload="<?php echo $jsevents["body"]["onload"]; ?>">
<?php include("fbegin.inc"); ?>
<div id="inputerrors"></div>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="status ipsec">
	<tr>
		<td>
<?php
			$tab_array = array();
			$tab_array[0] = array(gettext("Overview"), true, "diag_ipsec.php");
			$tab_array[1] = array(gettext("Leases"), false, "diag_ipsec_leases.php");
			$tab_array[2] = array(gettext("SAD"), false, "diag_ipsec_sad.php");
			$tab_array[3] = array(gettext("SPD"), false, "diag_ipsec_spd.php");
			$tab_array[4] = array(gettext("Logs"), false, "diag_logs_ipsec.php");
			display_top_tabs($tab_array);
?>
		</td>
	</tr>
	<tr>
		<td>
		<div id="mainarea">
		<table width="100%" border="0" cellpadding="6" cellspacing="0" class="tabcont sortable" summary="status">
		<thead>
			<tr>
				<th class="listhdrr nowrap"><?php echo gettext("Description");?></th>
				<th class="listhdrr nowrap"><?php echo gettext("Local ID");?></th>
				<th class="listhdrr nowrap"><?php echo gettext("Local IP");?></th>
				<th class="listhdrr nowrap"><?php echo gettext("Remote ID");?></th>
				<th class="listhdrr nowrap"><?php echo gettext("Remote IP");?></th>
				<th class="listhdrr nowrap"><?php echo gettext("Role");?></th>
				<th class="listhdrr nowrap"><?php echo gettext("Reauth");?></th>
				<th class="listhdrr nowrap"><?php echo gettext("Algo");?></th>
				<th class="listhdrr nowrap"><?php echo gettext("Status");?></th>
				<th class="list nowrap"></th>
			</tr>
		</thead>
		<tbody>
<?php
	$ipsecconnected = array();
	if (is_array($status)) {
		foreach ($status as $ikeid => $ikesa) {
			$con_id = substr($ikeid, 3);
			if ($ikesa['version'] == 1) {
				$ph1idx = substr($con_id, 0, strrpos(substr($con_id, 0, -1), '00'));
				$ipsecconnected[$ph1idx] = $ph1idx;
			} else {
				$ipsecconnected[$con_id] = $ph1idx = $con_id;
			}

			if ($ikesa['state'] == "ESTABLISHED") {
				$icon = "pass";
			} elseif (!isset($config['ipsec']['enable'])) {
				$icon = "block";
			} else {
				$icon = "reject";
			}
?>
			<tr>
				<td class="listlr">
<?php
					echo htmlspecialchars(ipsec_get_descr($ph1idx));
?>
				</td>
				<td class="listr">
<?php
				if (!empty($ikesa['local-id'])) {
					if ($ikesa['local-id'] == '%any') {
						echo gettext('Any identifier');
					} else {
						echo htmlspecialchars($ikesa['local-id']);
					}
				} else {
					echo gettext("Unknown");
				}
					
?>
				</td>
				<td class="listr">
<?php
				if (!empty($ikesa['local-host'])) {
					echo htmlspecialchars($ikesa['local-host']);
				} else {
					echo gettext("Unknown");
				}
				/*
				 * XXX: local-nat-t was defined by pfSense
				 * When strongswan team accepted the change, they changed it to
				 * nat-local. Keep both for a while and remove local-nat-t in
				 * the future
				 */
				if (isset($ikesa['local-nat-t']) || isset($ikesa['nat-local'])) {
					echo " NAT-T";
				}

?>
				</td>
				<td class="listr">
<?php
				$identity = "";
				if (!empty($ikesa['remote-id'])) {
					if ($ikesa['remote-id'] == '%any') {
						$identity = 'Any identifier';
					} else {
						$identity = htmlspecialchars($ikesa['remote-id']);
					}
				}
				if (!empty($ikesa['remote-xauth-id'])) {
					echo htmlspecialchars($ikesa['remote-xauth-id']);
					echo "<br/>{$identity}";
				} elseif (!empty($ikesa['remote-eap-id'])) {
					echo htmlspecialchars($ikesa['remote-eap-id']);
					echo "<br/>{$identity}";
				} else {
					if (empty($identity))
						echo gettext("Unknown");
					else
						echo $identity;
				}

?>
				</td>
				<td class="listr">
<?php
				if (!empty($ikesa['remote-host'])) {
					echo htmlspecialchars($ikesa['remote-host']);
				} else {
					echo gettext("Unknown");
				}
				/*
				 * XXX: remote-nat-t was defined by pfSense
				 * When strongswan team accepted the change, they changed it to
				 * nat-remote. Keep both for a while and remove remote-nat-t in
				 * the future
				 */
				if (isset($ikesa['remote-nat-t']) || isset($ikesa['nat-remote'])) {
					echo " NAT-T";
				}
?>
				</td>
				<td class="listr">
					IKEv<?php echo htmlspecialchars($ikesa['version']);?>
					<br/>
<?php
				if ($ikesa['initiator'] == 'yes') {
					echo 'initiator';
				} else {
					echo 'responder';
				}
?>
				</td>
				<td class="listr">
<?php
					echo htmlspecialchars($ikesa['reauth-time']) . " " . gettext("seconds");
?>
				</td>
				<td class="listr">
<?php
					echo htmlspecialchars($ikesa['encr-alg']);
					echo "<br/>";
					echo htmlspecialchars($ikesa['integ-alg']);
					echo "<br/>";
					echo htmlspecialchars($ikesa['prf-alg']);
					echo "<br/>";
					echo htmlspecialchars($ikesa['dh-group']);
?>
				</td>
				<td class="listr">
					<center>
						<img src ="/themes/<?php echo $g['theme']; ?>/images/icons/icon_<?php echo $icon; ?>.gif" title="<?php echo $ikesa['status']; ?>" alt=""/>
						<br/><?php echo htmlspecialchars($ikesa['state']);?>
						<br/><?php echo htmlspecialchars($ikesa['established']) . " " . gettext("seconds ago");?>
					</center>
				</td>
				<td >
<?php
				if ($icon != "pass") {
?>
					<center>
						<a href="diag_ipsec.php?act=connect&amp;ikeid=<?php echo $con_id; ?>">
						<img src ="/themes/<?php echo $g['theme']; ?>/images/icons/icon_service_start.gif" alt=<?php echo gettext("Connect VPN");?> title=<?php echo gettext("Connect VPN");?> border="0"/>
						</a>
					</center>
<?php
				} else {
?>
					<center>
						<a href="diag_ipsec.php?act=ikedisconnect&amp;ikeid=<?php echo $con_id; ?>">
						<img src ="/themes/<?php echo $g['theme']; ?>/images/icons/icon_service_stop.gif" alt=<?php echo gettext("Disconnect VPN");?> title=<?php echo gettext("Disconnect VPN");?> border="0"/>
						</a>
						<a href="diag_ipsec.php?act=ikedisconnect&amp;ikeid=<?php echo $con_id; ?>&amp;ikesaid=<?php echo $ikesa['uniqueid']; ?>">
						<img src ="/themes/<?php echo $g['theme']; ?>/images/icons/icon_x.gif" alt=<?php echo gettext("Disconnect VPN Connection");?> title=<?php echo gettext("Disconnect VPN Connection");?> border="0"/>
						</a>
					</center>
<?php
				}
?>
				</td>
				<td valign="middle" class="list nowrap">
					<table border="0" cellspacing="0" cellpadding="1" summary="">
					</table>
				</td>
			</tr>
<?php
			if (is_array($ikesa['child-sas'])) {
?>
			<tr>
				<td class="listrborder" colspan="9">
				<div id="btnchildsa-<?=$ikeid;?>">
					<input  type="button" onclick="show_childsa('childsa-<?=$ikeid;?>','btnchildsa-<?=$ikeid;?>');" value="+" /> - Show child SA entries
				</div>
				<table class="tabcont" width="100%" height="100%" border="0" cellspacing="0" cellpadding="0" id="childsa-<?=$ikeid;?>" style="display:none" summary="">
					<thead>
						<tr>
							<th class="listhdrr nowrap"><?php echo gettext("Local subnets");?></th>
							<th class="listhdrr nowrap"><?php echo gettext("Local SPI(s)");?></th>
							<th class="listhdrr nowrap"><?php echo gettext("Remote subnets");?></th>
							<th class="listhdrr nowrap"><?php echo gettext("Times");?></th>
							<th class="listhdrr nowrap"><?php echo gettext("Algo");?></th>
							<th class="listhdrr nowrap"><?php echo gettext("Stats");?></th>
						</tr>
					</thead>
					<tbody>
<?php
				foreach ($ikesa['child-sas'] as $childid => $childsa) {
?>
						<tr valign="top">
							<td class="listlr nowrap">
<?php
						if (is_array($childsa['local-ts'])) {
							foreach ($childsa['local-ts'] as $lnets) {
								echo htmlspecialchars(ipsec_fixup_network($lnets)) . "<br />";
							}
						} else {
							echo gettext("Unknown");
						}
?>
							</td>
							<td class="listr nowrap">
<?php
						if (isset($childsa['spi-in'])) {
							echo gettext("Local: ") . htmlspecialchars($childsa['spi-in']);
						}
						if (isset($childsa['spi-out'])) {
							echo "<br/>" . gettext("Remote: ") . htmlspecialchars($childsa['spi-out']);
						}
?>
							</td>
							<td class="listr nowrap">
<?php
						if (is_array($childsa['remote-ts'])) {
							foreach ($childsa['remote-ts'] as $rnets) {
								echo htmlspecialchars(ipsec_fixup_network($rnets)) . "<br />";
							}
						} else {
							echo gettext("Unknown");
						}
?>
							</td>
							<td class="listr nowrap">
<?php
							echo gettext("Rekey: ") . htmlspecialchars($childsa['rekey-time']) . gettext(" seconds");
							echo "<br/>" . gettext("Life: ") . htmlspecialchars($childsa['life-time']) . gettext(" seconds");
							echo "<br/>" . gettext("Install: ") .htmlspecialchars($childsa['install-time']) . gettext(" seconds");

?>
							</td>
							<td class="listr nowrap">
<?php
							echo htmlspecialchars($childsa['encr-alg']);
							echo "<br/>";
							echo htmlspecialchars($childsa['integ-alg']);
							echo "<br/>";
							if (!empty($childsa['prf-alg'])) {
								echo htmlspecialchars($childsa['prf-alg']);
								echo "<br/>";
							}
							if (!empty($childsa['dh-group'])) {
								echo htmlspecialchars($childsa['dh-group']);
								echo "<br/>";
							}
							if (!empty($childsa['esn'])) {
								echo htmlspecialchars($childsa['esn']);
								echo "<br/>";
							}
							echo gettext("IPComp: ");
							if (!empty($childsa['cpi-in']) || !empty($childsa['cpi-out'])) {
								echo htmlspecialchars($childsa['cpi-in']) . " " . htmlspecialchars($childsa['cpi-out']);
							} else {
								echo gettext("none");
							}
?>
							</td>
							<td class="listr nowrap">
<?php
							echo gettext("Bytes-In: ") . htmlspecialchars($childsa['bytes-in']);
							echo "<br/>";
							echo gettext("Packets-In: ") . htmlspecialchars($childsa['packets-in']);
							echo "<br/>";
							echo gettext("Bytes-Out: ") . htmlspecialchars($childsa['bytes-out']);
							echo "<br/>";
							echo gettext("Packets-Out: ") . htmlspecialchars($childsa['packets-out']);
?>
							</td>
							<td>
								<center>
									<a href="diag_ipsec.php?act=childdisconnect&amp;ikeid=<?php echo $con_id; ?>&amp;ikesaid=<?php echo $childsa['reqid']; ?>">
									<img src ="/themes/<?php echo $g['theme']; ?>/images/icons/icon_x.gif" alt=<?php echo gettext("Disconnect Child SA");?> title=<?php echo gettext("Disconnect Child SA");?> border="0"/>
									</a>
								</center>
							</td>
							<td class="list nowrap">
								&nbsp;
							</td>
						</tr>
<?php
					}
?>
						<tr style="display:none;"><td></td></tr>
					</tbody>
				</table>
				</td>
			</tr>
<?php
			}

			unset($con_id);
		}
	}

	$rgmap = array();
	foreach ($a_phase1 as $ph1ent) {
		if (isset($ph1ent['disabled'])) {
			continue;
		}
		$rgmap[$ph1ent['remote-gateway']] = $ph1ent['remote-gateway'];
		if ($ipsecconnected[$ph1ent['ikeid']]) {
			continue;
		}
?>
			<tr>
				<td class="listlr">
<?php
					echo htmlspecialchars($ph1ent['descr']);
?>
				</td>
				<td class="listr">
<?php
				list ($myid_type, $myid_data) = ipsec_find_id($ph1ent, "local");
				if (empty($myid_data))
					echo gettext("Unknown");
				else
					echo htmlspecialchars($myid_data);
?>
				</td>
				<td class="listr">
<?php
				$ph1src = ipsec_get_phase1_src($ph1ent);
				if (empty($ph1src))
					echo gettext("Unknown");
				else
					echo htmlspecialchars($ph1src);
?>
				</td>
				<td class="listr">
<?php
				list ($peerid_type, $peerid_data) = ipsec_find_id($ph1ent, "peer", $rgmap);
				if (empty($peerid_data))
					echo gettext("Unknown");
				else
					echo htmlspecialchars($peerid_data);
?>
				</td>
				<td class="listr">
<?php
				$ph1src = ipsec_get_phase1_dst($ph1ent);
				if (empty($ph1src))
					echo gettext("Unknown");
				else
					echo htmlspecialchars($ph1src);
?>
				</td>
				<td class="listr" >
				</td>
				<td class="listr" >
				</td>
				<td class="listr" >
				</td>
<?php
			if (isset($ph1ent['mobile'])) {
?>
				<td class="listr">
					<center>
						<img src ="/themes/<?php echo $g['theme']; ?>/images/icons/icon_pass.gif" title=<?php echo gettext("Awaiting connections");?> alt=""/>
						<br/><?php echo gettext("Awaiting connections");?>
					</center>
				</td>
				<td valign="middle" class="list nowrap">
					<table border="0" cellspacing="0" cellpadding="1" summary="">
					</table>
				</td>
<?php
			} else {
?>
				<td class="listr">
					<center>
						<img src ="/themes/<?php echo $g['theme']; ?>/images/icons/icon_reject.gif" title=<?php echo gettext("Disconnected");?> alt=""/>
						<br/><?php echo gettext("Disconnected");?>
					</center>
				</td>
				<td >
					<center>
						<a href="diag_ipsec.php?act=connect&amp;ikeid=<?php echo $ph1ent['ikeid']; ?>">
						<img src ="/themes/<?php echo $g['theme']; ?>/images/icons/icon_service_start.gif" alt=<?php echo gettext("Connect VPN");?> title=<?php echo gettext("Connect VPN");?> border="0"/>
						</a>
					</center>
				</td>
<?php
			}
?>
				<td valign="middle" class="list nowrap">
					<table border="0" cellspacing="0" cellpadding="1" summary="">
					</table>
				</td>
			</tr>
<?php
	}
	unset($ipsecconnected, $phase1, $rgmap);
?>
			<tr style="display:none;"><td></td></tr>
		</tbody>
		</table>
	</div>
	</td>
	</tr>
</table>

<p class="vexpl">
	<span class="red">
		<strong><?php echo gettext("Note:");?><br /></strong>
	</span>
	<?php echo gettext("You can configure IPsec");?>
	<a href="vpn_ipsec.php">here</a>.
</p>
<?php unset($status); include("fend.inc"); ?>
<script type="text/javascript">
//<![CDATA[
function show_childsa(id, buttonid) {
	document.getElementById(buttonid).innerHTML='';
	aodiv = document.getElementById(id);
	aodiv.style.display = "block";
}
//]]>
</script>
</body>
</html>
