<?php
/*
	Copyright (C) 2007 Marcel Wiget <mwiget@mac.com>.
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
	pfSense_MODULE:	captiveportal
*/

##|+PRIV
##|*IDENT=page-status-captiveportal-voucher-rolls
##|*NAME=Status: Captive portal Voucher Rolls page
##|*DESCR=Allow access to the 'Status: Captive portal Voucher Rolls' page.
##|*MATCH=status_captiveportal_voucher_rolls.php*
##|-PRIV

require("guiconfig.inc");
require("functions.inc");
require_once("filter.inc");
require("shaper.inc");
require("captiveportal.inc");
require_once("voucher.inc");

$cpzone = $_GET['zone'];
if (isset($_POST['zone']))
	$cpzone = $_POST['zone'];

if (empty($cpzone)) {
	header("Location: services_captiveportal_zones.php");
	exit;
}

if (!is_array($config['captiveportal']))
	$config['captiveportal'] = array();
$a_cp =& $config['captiveportal'];
$pgtitle = array(gettext("Status"), gettext("Captive portal"), gettext("Voucher Rolls"), $a_cp[$cpzone]['zone']);
$shortcut_section = "captiveportal-vouchers";

if (!is_array($config['voucher'][$cpzone]['roll'])) {
	$config['voucher'][$cpzone]['roll'] = array();
}
$a_roll = &$config['voucher'][$cpzone]['roll'];

include("head.inc");
include("fbegin.inc");
?>

<form action="status_captiveportal_voucher_rolls.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="tab pane">
	<tr>
		<td class="tabnavtbl">
		<?php
			$tab_array = array();
			$tab_array[] = array(gettext("Active Users"), false, "status_captiveportal.php?zone={$cpzone}");
			$tab_array[] = array(gettext("Active Vouchers"), false, "status_captiveportal_vouchers.php?zone={$cpzone}");
			$tab_array[] = array(gettext("Voucher Rolls"), true, "status_captiveportal_voucher_rolls.php?zone={$cpzone}");
			$tab_array[] = array(gettext("Test Vouchers"), false, "status_captiveportal_test.php?zone={$cpzone}");
			$tab_array[] = array(gettext("Expire Vouchers"), false, "status_captiveportal_expire.php?zone={$cpzone}");
			display_top_tabs($tab_array);
		?>
		</td>
	</tr>
	<tr>
		<td class="tabcont">
			<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="content pane">
				<tr>
					<td class="listhdrr"><?=gettext("Roll#"); ?></td>
					<td class="listhdrr"><?=gettext("Minutes/Ticket"); ?></td>
					<td class="listhdrr"><?=gettext("# of Tickets"); ?></td>
					<td class="listhdrr"><?=gettext("Comment"); ?></td>
					<td class="listhdrr"><?=gettext("used"); ?></td>
					<td class="listhdrr"><?=gettext("active"); ?></td>
					<td class="listhdr"><?=gettext("ready"); ?></td>
				</tr>
				<?php
					$voucherlck = lock("vouche{$cpzone}r");
					$i = 0; foreach($a_roll as $rollent):
					$used = voucher_used_count($rollent['number']);
					$active = count(voucher_read_active_db($rollent['number']),$rollent['minutes']);
					$ready = $rollent['count'] - $used;
					/* used also count active vouchers, remove them */
					$used = $used - $active;
				?>
				<tr>
					<td class="listlr">
						<?=htmlspecialchars($rollent['number']); ?>&nbsp;
					</td>
					<td class="listr">
						<?=htmlspecialchars($rollent['minutes']);?>&nbsp;
					</td>
					<td class="listr">
						<?=htmlspecialchars($rollent['count']);?>&nbsp;
					</td>
					<td class="listr">
						<?=htmlspecialchars($rollent['comment']); ?>&nbsp;
					</td>
					<td class="listr">
						<?=htmlspecialchars($used); ?>&nbsp;
					</td>
					<td class="listr">
						<?=htmlspecialchars($active); ?>&nbsp;
					</td>
					<td class="listr">
						<?=htmlspecialchars($ready); ?>&nbsp;
					</td>
				</tr>
				<?php $i++; endforeach; unlock($voucherlck); ?>
			</table>
		</td>
	</tr>
</table>
</form>
<?php include("fend.inc"); ?>
