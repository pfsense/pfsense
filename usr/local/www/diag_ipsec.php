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

require("guiconfig.inc");
include("head.inc");
require("ipsec.inc");

if ($_GET['act'] == "connect") {
	if (is_ipaddr($_GET['remoteid']) && is_ipaddr($_GET['source'])) {
		exec("/sbin/ping -S " . escapeshellarg($_GET['source']) . " -c 1 " . escapeshellarg($_GET['remoteid']));
	}
}

if (!is_array($config['ipsec']['phase2']))
    $config['ipsec']['phase2'] = array();

$a_phase2 = &$config['ipsec']['phase2'];

$spd = ipsec_dump_spd();
$sad = ipsec_dump_sad();

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" onload="<?= $jsevents["body"]["onload"] ?>">
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
					<tr>
						<td nowrap class="listhdrr"><?=gettext("Local IP");?></td>
						<td nowrap class="listhdrr"><?=gettext("Remote IP");?></a></td>
						<td nowrap class="listhdrr"><?=gettext("Local Network");?></td>
						<td nowrap class="listhdrr"><?=gettext("Remote Network");?></a></td>
						<td nowrap class="listhdrr"><?=gettext("Description");?></a></td>
						<td nowrap class="listhdrr"><?=gettext("Status");?></td>
					</tr>
					<?php
						foreach ($a_phase2 as $ph2ent) {
							ipsec_lookup_phase1($ph2ent,$ph1ent);
							if (!isset($ph2ent['disabled']) && !isset($ph1ent['disabled'])) {
								if(ipsec_phase2_status($spd,$sad,$ph1ent,$ph2ent)) {
									$icon = "pass";
									$status = "Active";
								} elseif(!isset($config['ipsec']['enable'])) {
									$icon = "block";
									$status = "Disabled";
								} else {
									$icon = "reject";
									$status = "Error";
								}
					?>
					<tr>
						<td class="listlr">
							<?=htmlspecialchars(ipsec_get_phase1_src($ph1ent));?>
						</td>
						<td class="listr">
							<?=htmlspecialchars($ph1ent['remote-gateway']);?>
						</td>
						<td class="listr">
							<?php echo ipsec_idinfo_to_text($ph2ent['localid']); ?>
						</td>
						<td class="listr">
							<?php echo ipsec_idinfo_to_text($ph2ent['remoteid']); ?>
						</td>
						<td class="listr"><?=htmlspecialchars($ph2ent['descr']);?></td>
						<td class="listr">
							<center>
								<img src ="/themes/<?=$g['theme']?>/images/icons/icon_<?=$icon?>.gif" title="<?=$status?>">
							</center>
						</td>
						<td class="list">
							<?php
							$source = "";
							$ip_interface = null;
							$ip_alias = null;
							if ($ph2ent['localid']['type'] == 'lan') {
								$source = get_interface_ip('lan');
							} else if ($ph2ent['localid']['type'] == 'network') {
								$ip_interface = find_ip_interface($ph2ent['localid']['address'], $ph2ent['localid']['netbits']);
								if (!$ip_interface) {
									$ip_alias = find_virtual_ip_alias($ph2ent['localid']['address'], $ph2ent['localid']['netbits']);
								}
							} else {
								$ip_interface = find_ip_interface($ph2ent['localid']['address']);
								if (!$ip_interface) {
									$ip_alias = find_virtual_ip_alias($ph2ent['localid']['address']);
								}
							}
							if ($ip_interface) {
								$source = get_interface_ip($ip_interface);
							} else if ($ip_alias) {
								$source = $ip_alias['subnet'];
							}
							?>
							<?php if (($ph2ent['remoteid']['type'] != "mobile") && ($icon != "pass") && ($source != "")): ?>
							<center>
								<a href="diag_ipsec.php?act=connect&remoteid=<?= $ph2ent['remoteid']['address'] ?>&source=<?= $source ?>">
								<img src ="/themes/<?=$g['theme']?>/images/icons/icon_service_start.gif" alt="Connect VPN" title="Connect VPN" border="0">
								</a>
							</center>
							<?php else: ?>
								&nbsp;
							<?php endif; ?>
						</td>
					</tr>
					<?php
							}
						}
					?>
				</table>
			</div>
		</td>
	</tr>
</table>

<p/>

<span class="vexpl">
	<span class="red">
		<strong><?=gettext("Note:");?><br /></strong>
	</span>
	<?=gettext("You can configure your IPsec");?> 
	<a href="vpn_ipsec.php">here</a>.
</span>

<?php include("fend.inc"); ?>
</body>
</html>
