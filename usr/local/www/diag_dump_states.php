<?php
/*
	diag_dump_states.php
	Copyright (C) 2005-2009 Scott Ullrich
	Copyright (C) 2005 Colin Smith
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
	pfSense_BUILDER_BINARIES:	/sbin/pfctl
	pfSense_MODULE:	filter
*/

##|+PRIV
##|*IDENT=page-diagnostics-showstates
##|*NAME=Diagnostics: Show States page
##|*DESCR=Allow access to the 'Diagnostics: Show States' page.
##|*MATCH=diag_dump_states.php*
##|-PRIV

require_once("guiconfig.inc");

/* handle AJAX operations */
if($_GET['action']) {
	if($_GET['action'] == "remove") {
		if (is_ipaddr($_GET['srcip']) and is_ipaddr($_GET['dstip'])) {
			$retval = mwexec("/sbin/pfctl -k " . escapeshellarg($_GET['srcip']) . " -k " . escapeshellarg($_GET['dstip']));
			echo htmlentities("|{$_GET['srcip']}|{$_GET['dstip']}|{$retval}|");
		} else {
			echo gettext("invalid input");
		}
		exit;
	}
}

if ($_GET['filter'] && ($_GET['killfilter'] == "Kill")) {
	if (is_ipaddr($_GET['filter'])) {
		$tokill = escapeshellarg($_GET['filter'] . "/32");
	} elseif (is_subnet($_GET['filter'])) {
		$tokill = escapeshellarg($_GET['filter']);
	}
	$retval = mwexec("/sbin/pfctl -k {$tokill} -k 0/0");
	$retval = mwexec("/sbin/pfctl -k 0.0.0.0/0 -k {$tokill}");
}

$pgtitle = array(gettext("Diagnostics"),gettext("Show States"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" onload="<?=$jsevents["body"]["onload"];?>">
<?php include("fbegin.inc"); ?>
<form action="diag_dump_states.php" method="get" name="iform">

<script type="text/javascript">
	function removeState(srcip, dstip) {
		var busy = function(index,icon) {
			jQuery(icon).bind("onclick","");
			jQuery(icon).attr('src',jQuery(icon).attr('src').replace("\.gif", "_d.gif"));
			jQuery(icon).css("cursor","wait");
		}

		jQuery('img[name="i:' + srcip + ":" + dstip + '"]').each(busy);

		jQuery.ajax(
			"<?=$_SERVER['SCRIPT_NAME'];?>" +
				"?action=remove&srcip=" + srcip + "&dstip=" + dstip,
			{ type: "get", complete: removeComplete }
		);
	}

	function removeComplete(req) {
		var values = req.responseText.split("|");
		if(values[3] != "0") {
			alert('<?=gettext("An error occurred.");?>');
			return;
		}

		jQuery('tr[name="r:' + values[1] + ":" + values[2] + '"]').each(
			function(index,row) { jQuery(row).fadeOut(1000); }
		);
	}
</script>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td>
		<?php
			$tab_array = array();
			$tab_array[] = array(gettext("States"), true, "diag_dump_states.php");
			if (isset($config['system']['lb_use_sticky']))
				$tab_array[] = array(gettext("Source Tracking"), false, "diag_dump_states_sources.php");
			$tab_array[] = array(gettext("Reset States"), false, "diag_resetstate.php");
			display_top_tabs($tab_array);
		?>
		</td>
	</tr>
	<tr>
		<td>
			<div id="mainarea">

<!-- Start of tab content -->

<?php
	$current_statecount=`pfctl -si | grep "current entries" | awk '{ print $3 }'`;
?>

<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0">
	<tr>
		<td>
			<form action="<?=$_SERVER['SCRIPT_NAME'];?>" method="get">
			<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0">
				<tr>
					<td>
						<?=gettext("Current total state count");?>: <?= $current_statecount ?>
					</td>
					<td style="font-weight:bold;" align="right">
						<?=gettext("Filter expression:");?>
						<input type="text" name="filter" class="formfld search" value="<?=htmlspecialchars($_GET['filter']);?>" size="30" />
						<input type="submit" class="formbtn" value="<?=gettext("Filter");?>" />
					<?php if (is_ipaddr($_GET['filter']) || is_subnet($_GET['filter'])): ?>
						<input type="submit" class="formbtn" name="killfilter" value="<?=gettext("Kill");?>" />
					<?php endif; ?>
					</td>
				</tr>
			</table>
			</form>
		</td>
	</tr>
	<tr>
		<td>
			<table class="tabcont sortable" width="100%" border="0" cellspacing="0" cellpadding="0">
				<thead>
				<tr>
					<th class="listhdrr" width="10%"><?=gettext("Proto");?></th>
					<th class="listhdrr" width="65"><?=gettext("Source -> Router -> Destination");?></th>
					<th class="listhdr" width="24%"><?=gettext("State");?></th>
					<th class="list sort_ignore" width="1%"></th>
				</tr>
				</thead>
				<tbody>
<?php
$row = 0;
/* get our states */
$grepline = ($_GET['filter']) ? "| grep " . escapeshellarg(htmlspecialchars($_GET['filter'])) : "";
$fd = popen("/sbin/pfctl -s state {$grepline}", "r" );
while ($line = chop(fgets($fd))) {
	if($row >= 10000)
		break;

	$line_split = preg_split("/\s+/", $line);
	$type  = array_shift($line_split);
	$proto = array_shift($line_split);
	$state = array_pop($line_split);
	$info  = implode(" ", $line_split);

	/* break up info and extract $srcip and $dstip */
	$ends = preg_split("/\<?-\>?/", $info);
	$parts = explode(":", $ends[0]);
	$srcip = trim($parts[0]);
	$parts = explode(":", $ends[count($ends) - 1]);
	$dstip = trim($parts[0]);

?>
	<tr valign="top" name="r:<?= $srcip ?>:<?= $dstip ?>">
			<td class="listlr"><?= $proto ?></td>
			<td class="listr"><?= $info ?></td>
			<td class="listr"><?= $state ?></td>
			<td class="list">
			<img src="/themes/<?= $g['theme'] ?>/images/icons/icon_x.gif" height="17" width="17" border="0"
				onclick="removeState('<?= $srcip ?>', '<?= $dstip ?>');" style="cursor:pointer;"
				name="i:<?= $srcip ?>:<?= $dstip ?>"
				title="<?= gettext('Remove all state entries from') ?> <?= $srcip ?> <?= gettext('to') ?> <?= $dstip ?>" alt="" />
			</td>
	</tr>
<?php
	$row++;
	ob_flush();
}

if ($row == 0): ?>
	<tr>
		<td class="list" colspan="4" align="center" valign="top">
		<?= gettext("No states were found.") ?>
		</td>
	</tr>
<?php endif;
pclose($fd);
?>
			</tbody>
			</table>
		</td>
	</tr>
	<tr>
		<td class="list" colspan="4" align="center" valign="top">
		<?php if (!empty($_GET['filter'])): ?>
			<?=gettext("States matching current filter")?>: <?= $row ?>
		<?php endif; ?>
		</td>
	</tr>
</table>

<!-- End of tab content -->

		</div>
	</td>
  </tr>
</table>

<?php require("fend.inc"); ?>
</body>
</html>
