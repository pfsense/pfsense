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

$spd = ipsec_dump_spd();
$sad = ipsec_dump_sad();
$mobile = ipsec_dump_mobile();

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
						<th nowrap="nowrap" class="listhdrr"><?php echo gettext("Local IP");?></th>
						<th nowrap="nowrap" class="listhdrr"><?php echo gettext("Remote IP");?></th>
						<th nowrap="nowrap" class="listhdrr"><?php echo gettext("Local Network");?></th>
						<th nowrap="nowrap" class="listhdrr"><?php echo gettext("Remote Network");?></th>
						<th nowrap="nowrap" class="listhdrr"><?php echo gettext("Description");?></th>
						<th nowrap="nowrap" class="listhdrr"><?php echo gettext("Status");?></th>
					</tr>
					</thead>
					<tbody>
					<?php
						$rowIndex = 0;
						foreach ($a_phase2 as $ph2ent) {
							if ($ph2ent['remoteid']['type'] == "mobile")
								continue;
							ipsec_lookup_phase1($ph2ent,$ph1ent);
							if (!isset($ph2ent['disabled']) && !isset($ph1ent['disabled'])) {
								$rowIndex++;
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
							<?php echo htmlspecialchars(ipsec_get_phase1_src($ph1ent));?>
						</td>
						<td class="listr">
							<?php echo htmlspecialchars($ph1ent['remote-gateway']);?>
						</td>
						<td class="listr">
							<?php echo ipsec_idinfo_to_text($ph2ent['localid']); ?>
						</td>
						<td class="listr">
							<?php echo ipsec_idinfo_to_text($ph2ent['remoteid']); ?>
						</td>
						<td class="listr"><?php echo htmlspecialchars($ph2ent['descr']);?></td>
						<td class="listr">
							<center>
								<img src ="/themes/<?php echo $g['theme']; ?>/images/icons/icon_<?php echo $icon; ?>.gif" title="<?php echo $status; ?>" alt=""/>
							</center>
						</td>
						<td class="list">
							<?php
							$source = "";
							$ip_interface = null;
							$ip_alias = null;
							$localinfo = ipsec_idinfo_to_cidr($ph2ent['localid'], false, $ph2ent['mode']);
							list($localip, $localsub) = explode("/", $localinfo);
							$ip_interface = find_ip_interface($localip, $localsub);
							if (!$ip_interface)
								$ip_alias = find_virtual_ip_alias($localip, $localsub);
							if ($ip_interface) {
								if (is_ipaddrv6($localip))
									$source = get_interface_ipv6($ip_interface);
								else
									$source = get_interface_ip($ip_interface);
							} else if ($ip_alias) {
								$source = $ip_alias['subnet'];
							}
							if (!empty($ph2ent['pinghost']))
								$remoteid = $ph2ent['pinghost'];
							else
								$remoteid = $ph2ent['remoteid']['address'];
							?>
							<?php if (($ph2ent['remoteid']['type'] != "mobile") && ($icon != "pass") && ($source != "")): ?>
							<center>
								<a href="diag_ipsec.php?act=connect&amp;remoteid=<?php echo $remoteid; ?>&amp;source=<?php echo $source; ?>">
								<img src ="/themes/<?php echo $g['theme']; ?>/images/icons/icon_service_start.gif" alt="Connect VPN" title="Connect VPN" border="0"/>
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
						if ($rowIndex == 0) {
					?>
					<tr><td></td></tr>
					<?php
						}
					?>
					</tbody>
				</table>
				<?php if (isset($config['ipsec']['client']['enable'])): ?>
				<table width="100%" border="0" cellpadding="6" cellspacing="0" class="tabcont sortable">
					<thead>
					<tr>
						<th nowrap="nowrap" class="listhdrr"><?php echo gettext("Mobile User");?></th>
						<th nowrap="nowrap" class="listhdrr"><?php echo gettext("Login Time");?></th>
						<th nowrap="nowrap" class="listhdrr"><?php echo gettext("Local");?></th>
						<th nowrap="nowrap" class="listhdrr"><?php echo gettext("Remote");?></th>
						<th nowrap="nowrap" class="list">&nbsp;</th>
					</tr>
					</thead>
					<tbody>
					<?php	foreach ($mobile as $muser): ?>
					<tr>
						<td class="listlr"><?php echo $muser['username']; ?></td>
						<td class="listr" align="center"><?php echo $muser['logintime']; ?></td>
						<td class="listr" align="center"><?php echo $muser['local']; ?></td>
						<td class="listr" align="center"><?php echo $muser['remote']; ?></td>
						<td class="list" align="center"><a href="diag_ipsec.php?act=disconnect&user=<?php echo $muser['username']; ?>"><img src='/themes/<?php echo $g['theme']; ?>/images/icons/icon_x.gif' height='17' width='17' border='0' alt=''/></a></td>
					</tr>
					<?php	endforeach; ?>
					</tbody>
				</table>
				<?php endif; ?>
			</div>
		</td>
	</tr>
</table>

<p/>

<span class="vexpl">
	<span class="red">
		<strong><?php echo gettext("Note:");?><br /></strong>
	</span>
	<?php echo gettext("You can configure IPsec");?>
	<a href="vpn_ipsec.php">here</a>.
</span>

<?php include("fend.inc"); ?>
</body>
</html>
