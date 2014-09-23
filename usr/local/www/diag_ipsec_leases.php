<?php
/* $Id$ */
/*
	diag_ipsec_leases.php
	Copyright (C) 2014 Ermal LUÇi
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
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/ipsec
	pfSense_MODULE:	ipsec
*/

##|+PRIV
##|*IDENT=page-status-ipsec-leases
##|*NAME=Status: IPsec: Leases page
##|*DESCR=Allow access to the 'Status: IPsec: Leases' page.
##|*MATCH=diag_ipsec_leases.php*
##|-PRIV

require("guiconfig.inc");
require("ipsec.inc");

$pgtitle = array(gettext("Status"),gettext("IPsec"),gettext("Leases"));
$shortcut_section = "ipsec";
include("head.inc");

$mobile = ipsec_dump_mobile();

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
	<?php include("fbegin.inc"); ?>
	<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="status ipsec sad">
		<tr>
			<td>
				<?php
					$tab_array = array();
					$tab_array[0] = array(gettext("Overview"), false, "diag_ipsec.php");
					$tab_array[1] = array(gettext("Leases"), true, "diag_ipsec_leases.php");
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
				<?php if (isset($mobile['pool']) && is_array($mobile['pool'])): ?>
					<?php foreach($mobile['pool'] as $pool): ?>
						<table class="tabcont sortable" width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
							<tr>
								<td colspan="4" valign="top" class="listtopic">
								<?php
									echo gettext("Pool: ") . $pool['name'];
									echo ' ' . gettext("usage: ") . $pool['usage'];
									echo ' ' . gettext("online: ") . $pool['online'];
								?>
								</td>
							</tr>
							<?php if (is_array($pool['lease']) && count($pool['lease']) > 0): ?>
							<tr>
								<td class="listhdrr nowrap"><?=gettext("ID");?></td>
								<td class="listhdrr nowrap"><?=gettext("Host");?></td>
								<td class="listhdrr nowrap"><?=gettext("Status");?></td>
								<td class="list nowrap"></td>
							</tr>
							<?php foreach ($pool['lease'] as $lease): ?>
							<tr>
								<td class="listlr"><?=htmlspecialchars($lease['id']);?></td>
								<td class="listr"><?=htmlspecialchars($lease['host']);?></td>
								<td class="listr"><?=htmlspecialchars($lease['status']);?></td>
								<td class="list nowrap">
								</td>
							</tr>
							<?php endforeach;
							else: ?>
							<tr>
								<td>
									<p><strong><?=gettext("No leases from this pool yet.");?></strong></p>
								</td>
							</tr>
							<?php endif; ?>
						</table>
					<?php endforeach; ?>
				<?php else: ?>
					<table class="tabcont sortable" width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
						<tr>
							<td>
								<p><strong><?=gettext("No IPsec pools.");?></strong></p>
							</td>
						</tr>
					</table>
				<?php endif; ?>
				</div>
			</td>
		</tr>
	</table>

<p class="vexpl">
<span class="red"><strong><?=gettext("Note:");?><br /></strong></span>
<?=gettext("You can configure your IPsec");?> <a href="vpn_ipsec.php"><?=gettext("here.");?></a>
</p>

<?php include("fend.inc"); ?>
</body>
</html>
