<?php
/* $Id$ */
/*
	diag_ipsec_spd.php
	Copyright (C) 2004-2009 Scott Ullrich
	All rights reserved.

	originially part of m0n0wall (http://m0n0.ch/wall)
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
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/setkey
	pfSense_MODULE:	ipsec
*/

##|+PRIV
##|*IDENT=page-status-ipsec-spd
##|*NAME=Status: IPsec: SPD page
##|*DESCR=Allow access to the 'Status: IPsec: SPD' page.
##|*MATCH=diag_ipsec_spd.php*
##|-PRIV

require("guiconfig.inc");
require("ipsec.inc");

$pgtitle = array(gettext("Status"),gettext("IPsec"),gettext("SPD"));
$shortcut_section = "ipsec";
include("head.inc");

/* delete any SP? */
if ($_GET['act'] == "del") {
	$fd = @popen("/usr/local/sbin/setkey -c > /dev/null 2>&1", "w");
	if ($fd) {
		fwrite($fd, "spddelete {$_GET['srcid']} {$_GET['dstid']} any -P {$_GET['dir']} ;\n");
		pclose($fd);
		sleep(1);
	}
}

$spd = ipsec_dump_spd();
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
	<?php include("fbegin.inc"); ?>
	<table width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr>
			<td>
				<?php
					$tab_array = array();
					$tab_array[0] = array(gettext("Overview"), false, "diag_ipsec.php");
					$tab_array[1] = array(gettext("SAD"), false, "diag_ipsec_sad.php");
					$tab_array[2] = array(gettext("SPD"), true, "diag_ipsec_spd.php");
					$tab_array[3] = array(gettext("Logs"), false, "diag_logs_ipsec.php");
					display_top_tabs($tab_array);
				?>
			</td>
		</tr>
		<tr>
			<td>
				<div id="mainarea" style="background:#eeeeee">
					<table class="tabcont sortable" width="100%" border="0" cellpadding="6" cellspacing="0">
						<?php if (count($spd)): ?>
						<tr>
							<td nowrap="nowrap" class="listhdrr"><?= gettext("Source"); ?></td>
							<td nowrap="nowrap" class="listhdrr"><?= gettext("Destination"); ?></td>
							<td nowrap="nowrap" class="listhdrr"><?= gettext("Direction"); ?></td>
							<td nowrap="nowrap" class="listhdrr"><?= gettext("Protocol"); ?></td>
							<td nowrap="nowrap" class="listhdrr"><?= gettext("Tunnel endpoints"); ?></td>
							<td nowrap="nowrap" class="list"></td>
						</tr>
						<?php foreach ($spd as $sp): ?>
						<tr>
							<td class="listlr" valign="top"><?=htmlspecialchars($sp['srcid']);?></td>
							<td class="listr" valign="top"><?=htmlspecialchars($sp['dstid']);?></td>
							<td class="listr" valign="top">
								<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_<?=$sp['dir'];?>.gif" width="11" height="11" style="margin-top: 2px" alt=""/>
							</td>
							<td class="listr" valign="top"><?=htmlspecialchars(strtoupper($sp['proto']));?></td>
							<td class="listr" valign="top"><?=htmlspecialchars($sp['src']);?> -> <?=htmlspecialchars($sp['dst']);?></td>
							<td class="list" nowrap="nowrap">
								<?php
									$args = "srcid=".rawurlencode($sp['srcid']);
									$args .= "&dstid=".rawurlencode($sp['dstid']);
									$args .= "&dir=".rawurlencode($sp['dir']);
								?>
								<a href="diag_ipsec_spd.php?act=del&<?=$args;?>" onclick="return confirm('<?= gettext("Do you really want to delete this security policy?"); ?>')">
									<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" alt=""/>
								</a>
							</td>
						</tr>
						<?php endforeach; ?>
					</table>
					<br />
					<table class="tabcont" border="0" cellspacing="0" cellpadding="6">
						<tr>
							<td width="16"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_in.gif" width="11" height="11" alt=""/></td>
							<td><?= gettext("incoming (as seen by firewall)"); ?></td>
						</tr>
						<tr>
							<td colspan="5" height="4"></td>
						</tr>
						<tr>
							<td><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_out.gif" width="11" height="11" alt=""/></td>
							<td><?= gettext("outgoing (as seen by firewall)"); ?></td>
						</tr>
						<?php else: ?>
						<tr>
							<td>
								<p><strong><?= gettext("No IPsec security policies."); ?></strong></p>
							</td>
						</tr>
						<?php endif; ?>
					</table>
				</div>
			</td>
		</tr>
	</table>

<p/>

<span class="vexpl">
<span class="red"><strong><?= gettext("Note:"); ?><br /></strong></span>
<?= gettext("You can configure your IPsec"); ?> <a href="vpn_ipsec.php"><?= gettext("here."); ?></a>
</span>

<?php include("fend.inc"); ?>
</body>
</html>

