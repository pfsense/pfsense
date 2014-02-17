<?php
/* $Id$ */
/*
	diag_ipsec_sad.php
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
##|*IDENT=page-status-ipsec-sad
##|*NAME=Status: IPsec: SAD page
##|*DESCR=Allow access to the 'Status: IPsec: SAD' page.
##|*MATCH=diag_ipsec_sad.php*
##|-PRIV

require("guiconfig.inc");
require("ipsec.inc");

$pgtitle = array(gettext("Status"),gettext("IPsec"),gettext("SAD"));
$shortcut_section = "ipsec";
include("head.inc");

$sad = ipsec_dump_sad();

/* delete any SA? */
if ($_GET['act'] == "del") {
	$fd = @popen("/usr/local/sbin/setkey -c > /dev/null 2>&1", "w");
	if ($fd) {
		fwrite($fd, "delete {$_GET['src']} {$_GET['dst']} {$_GET['proto']} {$_GET['spi']} ;\n");
		pclose($fd);
		sleep(1);
	}
}

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
	<?php include("fbegin.inc"); ?>
	<table width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr>
			<td>
				<?php
					$tab_array = array();
					$tab_array[0] = array(gettext("Overview"), false, "diag_ipsec.php");
					$tab_array[1] = array(gettext("SAD"), true, "diag_ipsec_sad.php");
					$tab_array[2] = array(gettext("SPD"), false, "diag_ipsec_spd.php");
					$tab_array[3] = array(gettext("Logs"), false, "diag_logs_ipsec.php");
					display_top_tabs($tab_array);
				?>
			</td>
		</tr>
		<tr>
			<td>
				<div id="mainarea">
					<table class="tabcont sortable" width="100%" border="0" cellpadding="6" cellspacing="0">
						<?php if (count($sad)): ?>
						<tr>
							<td nowrap="nowrap" class="listhdrr"><?=gettext("Source");?></td>
							<td nowrap="nowrap" class="listhdrr"><?=gettext("Destination");?></td>
							<td nowrap="nowrap" class="listhdrr"><?=gettext("Protocol");?></td>
							<td nowrap="nowrap" class="listhdrr"><?=gettext("SPI");?></td>
							<td nowrap="nowrap" class="listhdrr"><?=gettext("Enc. alg.");?></td>
							<td nowrap="nowrap" class="listhdr"><?=gettext("Auth. alg.");?></td>
							<td nowrap="nowrap" class="listhdr"><?=gettext("Data");?></td>
							<td nowrap="nowrap" class="list"></td>
						</tr>
						<?php foreach ($sad as $sa): ?>
						<tr>
							<td class="listlr"><?=htmlspecialchars($sa['src']);?></td>
							<td class="listr"><?=htmlspecialchars($sa['dst']);?></td>
							<td class="listr"><?=htmlspecialchars(strtoupper($sa['proto']));?></td>
							<td class="listr"><?=htmlspecialchars($sa['spi']);?></td>
							<td class="listr"><?=htmlspecialchars($sa['ealgo']);?></td>
							<td class="listr"><?=htmlspecialchars($sa['aalgo']);?></td>
							<td class="listr"><?=htmlspecialchars($sa['data']);?></td>
							<td class="list" nowrap="nowrap">
								<?php
									$args = "src=" . rawurlencode($sa['src']);
									$args .= "&dst=" . rawurlencode($sa['dst']);
									$args .= "&proto=" . rawurlencode($sa['proto']);
									$args .= "&spi=" . rawurlencode("0x" . $sa['spi']);
								?>
								<a href="diag_ipsec_sad.php?act=del&<?=$args;?>" onclick="return confirm('<?=gettext("Do you really want to delete this security association?"); ?>')">
									<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" alt=""/>
								</a>
							</td>
						</tr>
						<?php endforeach; ?>
						<?php else: ?>
						<tr>
							<td>
								<p><strong><?=gettext("No IPsec security associations.");?></strong></p>
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
<span class="red"><strong><?=gettext("Note:");?><br /></strong></span>
<?=gettext("You can configure your IPsec");?> <a href="vpn_ipsec.php"><?=gettext("here.");?></a>
</span>

<?php include("fend.inc"); ?>
</body>
</html>
