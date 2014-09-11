<?php
/* $Id$ */
/*
	diag_ipsec.php
	Copyright (C) 2004-2009 Scott Ullrich
	Copyright (C) 2008 Shrew Soft Inc <mgrooms@shrew.net>.
	All rights reserved.

	Parts of this code was originally based on vpn_ipsec_sad.php
	Copyright (C) 2003-2004 Manuel Kasper

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
		mwexec("/usr/local/sbin/ipsec down con" . escapeshellarg($_GET['ikeid']));
		mwexec("/usr/local/sbin/ipsec up con" . escapeshellarg($_GET['ikeid']));
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

$status = ipsec_smp_dump_status();

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
				<td class="list nowrap"></td>
		</tr>
		</thead>
		<tbody>
<?php
	$ipsecconnected = array();
	if (is_array($status['query']) && is_array($status['query']['ikesalist']) && is_array($status['query']['ikesalist']['ikesa'])) {
		foreach ($status['query']['ikesalist']['ikesa'] as $ikeid => $ikesa) {
			$con_id = substr($ikesa['peerconfig'], 3);
			$ipsecconnected[$con_id] = $con_id;

			if (ipsec_phase1_status($status['query']['ikesalist']['ikesa'], $ikesa['id'])) {
				$icon = "pass";
			} elseif(!isset($config['ipsec']['enable'])) {
				$icon = "block";
			} else {
				$icon = "reject";
			}
?>
			<tr>
				<td class="listlr">
					<?php echo htmlspecialchars(ipsec_get_descr($con_id));?>
				</td>
				<td class="listr">
			<?php   if (!is_array($ikesa['local']))
					echo "Unknown";
				else {
					if (!empty($ikesa['local']['identification'])) {
						if ($ikesa['local']['identification'] == '%any')
							echo 'Any identifier';
						else
							echo htmlspecialchars($ikesa['local']['identification']);
					} else
						echo 'Unknown';
				}
			?>
				</td>
				<td class="listr">
			<?php   if (!is_array($ikesa['local']))
					echo "Unknown";
				else {
					if (!empty($ikesa['local']['address']))
						echo htmlspecialchars($ikesa['local']['address']) . '<br/>Port:' . htmlspecialchars($ikesa['local']['port']);
					else
						echo 'Unknown';
					if ($ikesa['local']['nat'] != 'false')
						echo " NAT-T";
				}
			?>
				</td>
				<td class="listr">
			<?php   if (!is_array($ikesa['remote']))
					echo "Unknown";
				else {
					$identity = "";
					if (!empty($ikesa['remote']['identification'])) {
						if ($ikesa['remote']['identification'] == '%any')
							$identity = 'Any identifier';
						else
							$identity = htmlspecialchars($ikesa['remote']['identification']);
					}

					if (is_array($ikesa['remote']['auth']) && !empty($ikesa['remote']['auth'][0]['identity'])) {
						echo htmlspecialchars($ikesa['remote']['auth'][0]['identity']);
						echo "<br/>{$identity}";
					} else {
						if (empty($identity))
							echo "Unknown";
						else
							echo $identity;
					}
				}
			?>
				</td>
				<td class="listr">
			<?php   if (!is_array($ikesa['remote']))
					echo "Unknown";
				else {
					if (!empty($ikesa['remote']['address']))
						echo htmlspecialchars($ikesa['remote']['address']) . '<br/>Port:' . htmlspecialchars($ikesa['remote']['port']);
					else
						echo 'Unknown';
					if ($ikesa['remote']['nat'] != 'false')
						echo " NAT-T";
				}
			?>
				</td>
				<td class="listr">
					IKEv<?php echo htmlspecialchars($ikesa['version']);?>
					<br/>
					<?php echo htmlspecialchars($ikesa['role']);?>
					
				</td>
				<td class="listr">
					<?php echo htmlspecialchars($ikesa['reauth']);?>
				</td>
				<td class="listr">
					<?php
						echo htmlspecialchars($ikesa['encalg']);
						echo "<br/>";
						echo htmlspecialchars($ikesa['intalg']);
						echo "<br/>";
						echo htmlspecialchars($ikesa['prfalg']);
						echo "<br/>";
						echo htmlspecialchars($ikesa['dhgroup']);
					?>
				</td>
				<td class="listr">
					<center>
						<img src ="/themes/<?php echo $g['theme']; ?>/images/icons/icon_<?php echo $icon; ?>.gif" title="<?php echo $ikesa['status']; ?>" alt=""/>
						<br/><?php echo htmlspecialchars($ikesa['status']);?>
						<br/><?php echo htmlspecialchars($ikesa['established']);?>
					</center>
				</td>
				<td >
				<?php if ($icon != "pass"): ?>
					<center>
						<a href="diag_ipsec.php?act=connect&amp;ikeid=<?php echo $con_id; ?>">
						<img src ="/themes/<?php echo $g['theme']; ?>/images/icons/icon_service_start.gif" alt="Connect VPN" title="Connect VPN" border="0"/>
						</a>
					</center>
				<?php else: ?>
					<center>
						<a href="diag_ipsec.php?act=ikedisconnect&amp;ikeid=<?php echo $con_id; ?>">
						<img src ="/themes/<?php echo $g['theme']; ?>/images/icons/icon_service_stop.gif" alt="Disconnect VPN" title="Disconnect VPN" border="0"/>
						</a>
						<a href="diag_ipsec.php?act=ikedisconnect&amp;ikeid=<?php echo $con_id; ?>&amp;ikesaid=<?php echo $ikesa['id']; ?>">
						<img src ="/themes/<?php echo $g['theme']; ?>/images/icons/icon_x.gif" alt="Disconnect VPN Connection" title="Disconnect VPN Connection" border="0"/>
						</a>
					</center>
				<?php endif; ?>
				</td>
				<td valign="middle" class="list nowrap">
					<table border="0" cellspacing="0" cellpadding="1" summary="">
					</table>
				</td>
			</tr>
			<?php if (is_array($ikesa['childsalist'])): ?>
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
					if (is_array($ikesa['childsalist']['childsa'])) {
						foreach ($ikesa['childsalist']['childsa'] as $childsa) {
				?>
					<tr valign="top">
						<td class="listlr nowrap">
				<?php	if (is_array($childsa['local']) && is_array($childsa['local']['networks']) && is_array($childsa['local']['networks']['network'])) {
						foreach ($childsa['local']['networks']['network'] as $lnets) {
							echo htmlspecialchars(ipsec_fixup_network($lnets)) . "<br />";
						}
					} else
						echo "Unknown";
				?>
						</td>
						<td class="listr nowrap">
				<?php	if (is_array($childsa['local']))
						echo "Local: " . htmlspecialchars($childsa['local']['spi']);
				?>
				<?php	if (is_array($childsa['remote']))
						echo "<br/>Remote: " . htmlspecialchars($childsa['remote']['spi']);
				?>
						</td>
						<td class="listr nowrap">
				<?php	if (is_array($childsa['remote']) && is_array($childsa['remote']['networks']) && is_array($childsa['remote']['networks']['network'])) {
						foreach ($childsa['remote']['networks']['network'] as $rnets) {
							echo htmlspecialchars(ipsec_fixup_network($rnets)) . "<br />";
						}
					} else
						echo "Unknown";
				?>
						</td>
						<td class="listr nowrap">
							<?php
								echo "Rekey: " . htmlspecialchars($childsa['rekey']);
								echo "<br/>Life: " . htmlspecialchars($childsa['lifetime']);
								echo "<br/>Install: " .htmlspecialchars($childsa['installtime']);

							?>
						</td>
						<td class="listr nowrap">
						<?php
							echo htmlspecialchars($childsa['encalg']);
							echo "<br/>";
							echo htmlspecialchars($childsa['intalg']);
							echo "<br/>";
							if (!empty($childsa['prfalg'])) {
								echo htmlspecialchars($childsa['prfalg']);
								echo "<br/>";
							}
							if (!empty($childsa['dhgroup'])) {
								echo htmlspecialchars($childsa['dhgroup']);
								echo "<br/>";
							}
							if (!empty($childsa['esn'])) {
								echo htmlspecialchars($childsa['esn']);
								echo "<br/>";
							}
							echo "IPComp: " . htmlspecialchars($childsa['ipcomp']);
						?>
						</td>
						<td class="listr nowrap">
						<?php
							echo "Bytes-In: " . htmlspecialchars($childsa['bytesin']) . "<br/>Packets-In: " . htmlspecialchars($childsa['packetsin']);;
							echo "<br/>";
							echo "Bytes-Out: " . htmlspecialchars($childsa['bytesout']) . "<br/>Packets-Out: " . htmlspecialchars($childsa['packetsout']);;
						?>
						</td>
						<td>
							<center>
								<a href="diag_ipsec.php?act=childdisconnect&amp;ikeid=<?php echo $con_id; ?>&amp;ikesaid=<?php echo $childsa['reqid']; ?>">
								<img src ="/themes/<?php echo $g['theme']; ?>/images/icons/icon_x.gif" alt="Disconnect Child SA" title="Disconnect Child SA" border="0"/>
								</a>
							</center>
						</td>
						<td class="list nowrap">
							&nbsp;
						</td>
					</tr>
				<?php } } ?>
					<tr style="display:none;"><td></td></tr>
				</tbody>
				</table>
				</td>
			</tr>
			<?php endif; 

			unset($con_id);
		}
	}

	$rgmap = array();
	foreach ($a_phase1 as $ph1ent):
		$rgmap[$ph1ent['remote-gateway']] = $ph1ent['remote-gateway'];
		if ($ipsecconnected[$ph1ent['ikeid']])
			continue;
?>
		<tr>
			<td class="listlr">
				<?php echo htmlspecialchars($ph1ent['descr']);?>
			</td>
			<td class="listr">
		<?php
			list ($myid_type, $myid_data) = ipsec_find_id($ph1ent, "local");
			if (empty($myid_data))
				echo "Unknown";
			else
				echo htmlspecialchars($myid_data);
		?>
			</td>
			<td class="listr">
		<?php
			$ph1src = ipsec_get_phase1_src($ph1ent);
			if (empty($ph1src))
				echo "Unknown";
			else
				echo htmlspecialchars($ph1src);
		?>
			</td>
			<td class="listr">
		<?php
			list ($peerid_type, $peerid_data) = ipsec_find_id($ph1ent, "peer", $rgmap);
			if (empty($peerid_data))
				echo "Unknown";
			else
				echo htmlspecialchars($peerid_data);
		?>
			</td>
			<td class="listr">
		<?php
			$ph1src = ipsec_get_phase1_dst($ph1ent);
			if (empty($ph1src))
				echo "Unknown";
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
			<td class="listr">
				<center>
					<img src ="/themes/<?php echo $g['theme']; ?>/images/icons/icon_reject.gif" title="Disconnected" alt=""/>
					<br/>Disconnected
				</center>
			</td>
			<td >
				<center>
					<a href="diag_ipsec.php?act=connect&amp;ikeid=<?php echo $ph1ent['ikeid']; ?>">
					<img src ="/themes/<?php echo $g['theme']; ?>/images/icons/icon_service_start.gif" alt="Connect VPN" title="Connect VPN" border="0"/>
					</a>
				</center>
			</td>
			<td valign="middle" class="list nowrap">
				<table border="0" cellspacing="0" cellpadding="1" summary="">
				</table>
			</td>
		</tr>
<?php
	endforeach;
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
