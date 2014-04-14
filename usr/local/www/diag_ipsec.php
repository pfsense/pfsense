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

if ($_GET['act'] == "connect") {
	if (is_ipaddrv4($_GET['remoteid']) && is_ipaddrv4($_GET['source'])) {
		exec("/sbin/ping -S " . escapeshellarg($_GET['source']) . " -c 1 " . escapeshellarg($_GET['remoteid']));
	}
	else if (is_ipaddrv6($_GET['remoteid']) && is_ipaddrv6($_GET['source'])) {
		exec("/sbin/ping6 -S " . escapeshellarg($_GET['source']) . " -c 1 " . escapeshellarg($_GET['remoteid']));
	}
}

if ($_GET['act'] == "disconnect") {
	if (!empty($_GET['user'])) {
		ipsec_disconnect_mobile($_GET['user']);
		sleep(1);
		$savemsg = gettext("Disconnected user") . " " . $_GET['user'];
	}
}

if (!is_array($config['ipsec']['phase2']))
    $config['ipsec']['phase2'] = array();

$a_phase2 = &$config['ipsec']['phase2'];

$status = ipsec_smp_dump_status();

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" onload="<?php echo $jsevents["body"]["onload"]; ?>">
<?php include("fbegin.inc"); ?>
<div id="inputerrors"></div>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td>
			<?php
				$tab_array = array();
				$tab_array[0] = array(gettext("Overview"), true, "diag_ipsec.php");
				$tab_array[1] = array(gettext("SAD"), false, "diag_ipsec_sad.php");
				$tab_array[2] = array(gettext("SPD"), false, "diag_ipsec_spd.php");
				$tab_array[3] = array(gettext("Logs"), false, "diag_logs_ipsec.php");
				display_top_tabs($tab_array);
			?>
		</td>
	</tr>
	<tr>
	<td>
	<div id="mainarea">
		<table width="100%" border="0" cellpadding="6" cellspacing="0" class="tabcont sortable">
		<thead>
			<tr>
				<th nowrap class="listhdrr"><?php echo gettext("Description");?></th>
				<th nowrap class="listhdrr"><?php echo gettext("Local ID");?></th>
				<th nowrap class="listhdrr"><?php echo gettext("Local IP");?></th>
				<th nowrap class="listhdrr"><?php echo gettext("Remote ID");?></th>
				<th nowrap class="listhdrr"><?php echo gettext("Remote IP");?></a></th>
				<th nowrap class="listhdrr"><?php echo gettext("Role");?></a></th>
				<th nowrap class="listhdrr"><?php echo gettext("Status");?></a></th>
		</tr>
		</thead>
		<tbody>
<?php
	if (is_array($status['query']) && is_array($status['query']['ikesalist']) && is_array($status['query']['ikesalist']['ikesa'])) {
		foreach ($status['query']['ikesalist']['ikesa'] as $ikeid => $ikesa) {
?>
			<tr>
				<td class="listlr">
					<?php echo htmlspecialchars($ikesa['peerconfig']);?>
				</td>
				<td class="listr">
			<?php   if (!is_array($ikesa['local']))
					echo "Unknown";
				else {
					if (!empty($ikesa['local']['identification']))
						echo htmlspecialchars($ikesa['local']['identification']) . '<br />' . htmlspecialchars($ikesa['local']['spi']);
					else
						echo 'Unknown';
				}
			?>
				</td>
				<td class="listr">
			<?php   if (!is_array($ikesa['local']))
					echo "Unknown";
				else {
					if (!empty($ikesa['local']['address']))
						echo htmlspecialchars($ikesa['local']['address']) . ':' . htmlspecialchars($ikesa['local']['port']);
					else
						echo 'Unknown';
					if ($ikesa['local']['nat'])
						echo " NAT-T";
				}
			?>
				</td>
				<td class="listr">
			<?php   if (!is_array($ikesa['remote']))
					echo "Unknown";
				else {
					if (!empty($ikesa['remote']['identification']))
						echo htmlspecialchars($ikesa['remote']['identification']) . '<br />' . htmlspecialchars($ikesa['remote']['spi']);
					else
						echo 'Unknown';
				}
			?>
				</td>
				<td class="listr">
			<?php   if (!is_array($ikesa['remote']))
					echo "Unknown";
				else {
					if (!empty($ikesa['remote']['address']))
						echo htmlspecialchars($ikesa['remote']['address']) . ':' . htmlspecialchars($ikesa['remote']['port']);
					else
						echo 'Unknown';
					if ($ikesa['remote']['nat'])
						echo " NAT-T";
				}
			?>
				</td>
				<td class="listr">
					<?php echo htmlspecialchars($ikesa['role']);?>
				</td>
				<td class="listr">
					<?php echo htmlspecialchars($ikesa['status']);?>
				</td>
				<td class="listbg">
					<?php ?> &nbsp;
				</td>
				<td valign="middle" nowrap class="list">
					<table border="0" cellspacing="0" cellpadding="1">
					</table>
				</td>
			</tr>
			<?php if (is_array($ikesa['childsalist'])): ?>
			<tr>
				<td class="listrborder" colspan="7">
				<div id="btnchildsa-<?=$ikeid;?>">
					<input  type="button" onClick="show_childsa('childsa-<?=$ikeid;?>','btnchildsa-<?=$ikeid;?>');" value="+" /> - Show child SA entries</a>
				</div>
				<table class="tabcont" width="100%" height="100%" border="0" cellspacing="0" cellpadding="0" id="childsa-<?=$ikeid;?>" style="display:none">
				<thead>
					<tr>
						<th nowrap class="listhdrr"><?php echo gettext("Local subnets");?></th>
						<th nowrap class="listhdrr"><?php echo gettext("Local SPI");?></th>
						<th nowrap class="listhdrr"><?php echo gettext("Remote SPI");?></th>
						<th nowrap class="listhdrr"><?php echo gettext("Remote subnets");?></th>
					</tr>
				</thead>
				<tbody>
				<?php
					if (is_array($ikesa['childsalist']['childsa'])) {
						foreach ($ikesa['childsalist']['childsa'] as $childsa) {
				?>
					<tr valign="top">
						<td nowrap class="listlr">
				<?php	if (is_array($childsa['local']) && is_array($childsa['local']['networks']) && is_array($childsa['local']['networks']['network'])) {
						foreach ($childsa['local']['networks']['network'] as $lnets) {
							echo htmlspecialchars($lnets) . "<br />";	
						}
					} else
						echo "Unknown";
				?>
						</td>
						<td nowrap class="listr">
				<?php	if (is_array($childsa['local']))
						echo htmlspecialchars($childsa['local']['spi']);
				?>
						</td>
						<td nowrap class="listr">
				<?php	if (is_array($childsa['remote']))
						echo htmlspecialchars($childsa['remote']['spi']);
				?>
						</td>
						<td nowrap class="listlr">
				<?php	if (is_array($childsa['remote']) && is_array($childsa['remote']['networks']) && is_array($childsa['remote']['networks']['network'])) {
						foreach ($childsa['remote']['networks']['network'] as $rnets) {
							echo htmlspecialchars($rnets) . "<br />";	
						}
					} else
						echo "Unknown";
				?>
						</td>
						<td nowrap class="list">
							&nbsp;
						</td>
					</tr>
				<?php } } ?>
				</tbody>
				</table>
				</td>
			</tr>
			<?php endif; 
		}
	}
?>
		</tbody>
		</table>
	</div>
	</td>
	</tr>
</table>
</div>

<span class="vexpl">
	<span class="red">
		<strong><?php echo gettext("Note:");?><br /></strong>
	</span>
	<?php echo gettext("You can configure IPsec");?>
	<a href="vpn_ipsec.php">here</a>.
</span>
<?php unset($status); include("fend.inc"); ?>
<script type="text/javascript">
function show_childsa(id, buttonid) {
	document.getElementById(buttonid).innerHTML='';
	aodiv = document.getElementById(id);
	aodiv.style.display = "block";
}
</script>
</body>
</html>
