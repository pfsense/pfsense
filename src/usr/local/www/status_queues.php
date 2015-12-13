<?php
/*
	status_queues.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */
/*
	pfSense_BUILDER_BINARIES:	/sbin/pfctl
	pfSense_MODULE: shaper
*/

##|+PRIV
##|*IDENT=page-status-trafficshaper-queues
##|*NAME=Status: Traffic shaper: Queues
##|*DESCR=Allow access to the 'Status: Traffic shaper: Queues' page.
##|*MATCH=status_queues.php*
##|-PRIV
/*
header("Last-Modified: " . gmdate("D, j M Y H:i:s") . " GMT");
header("Expires: " . gmdate("D, j M Y H:i:s", time()) . " GMT");
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache"); // HTTP/1.0
*/

require("guiconfig.inc");
class QueueStats {
	public $queuename;
	public $queuelength;
	public $pps;
	public $bandwidth;
	public $borrows;
	public $suspends;
	public $drops;
}
if (!file_exists("{$g['varrun_path']}/qstats.pid") || !isvalidpid("{$g['varrun_path']}/qstats.pid")) {
	/* Start in the background so we don't hang up the GUI */
	mwexec_bg("/usr/local/sbin/qstats -p {$g['varrun_path']}/qstats.pid");
	/* Give it a moment to start up */
	sleep(1);
}
$fd = @fsockopen("unix://{$g['varrun_path']}/qstats");
if (!$fd) {
	$error = "Something wrong happened during communication with stat gathering";
} else {
	$stats = "";
	while (!feof($fd)) {
		$stats .= fread($fd, 4096);
	}
	fclose($fd);
	@file_put_contents("{$g['tmp_path']}/qstats", $stats);
	$altqstats = @parse_xml_config("{$g['tmp_path']}/qstats", array("altqstats"));
	if ($altqstats == -1) {
		$error = "No queue statistics could be read.";
	}
}
if ($_REQUEST['getactivity']) {
	$statistics = array();
	$bigger_stat = 0;
	$stat_type = $_REQUEST['stats'];
	/* build the queue stats. */
	foreach ($altqstats['queue'] as $q) {
		statsQueues($q);
	}
	/* calculate the bigger amount of packets or bandwidth being moved through all queues. */
	if ($stat_type == "0") {
		foreach ($statistics as $q) {
			if ($bigger_stat < $q->pps) {
				$bigger_stat = $q->pps;
			}
		}
	} else {
		foreach ($statistics as $q) {
			if ($bigger_stat < $q->bandwidth) {
				$bigger_stat = $q->bandwidth;
			}
		}
	}
	$finscript = "";
	foreach ($statistics as $q) {
		if ($stat_type == "0") {
			$packet_s = round(100 * ($q->pps / $bigger_stat), 0);
		} else {
			$packet_s = round(100 * ($q->bandwidth / $bigger_stat), 0);
		}
		if ($packet_s < 0) {
			$packet_s = 0;
		}
		$finscript .= "jQuery('#queue{$q->queuename}width').css('width','{$packet_s}%');";
		$finscript .= "jQuery('#queue{$q->queuename}pps').val('" . number_format($q->pps, 1) . "');";
		$finscript .= "jQuery('#queue{$q->queuename}bps').val('" . format_bits($q->bandwidth) . "');";
		$finscript .= "jQuery('#queue{$q->queuename}borrows').val('{$q->borrows}');";
		$finscript .= "jQuery('#queue{$q->queuename}suspends').val('{$q->suspends}');";
		$finscript .= "jQuery('#queue{$q->queuename}drops').val('{$q->drops}');";
		$finscript .= "jQuery('#queue{$q->queuename}length').val('{$q->queuelength}');";
	}
	unset($statistics, $altqstats);
	header("Content-type: text/javascript");
	echo $finscript;
	exit;
}
$pgtitle = array(gettext("Status"), gettext("Traffic shaper"), gettext("Queues"));
$shortcut_section = "trafficshaper";
include("head.inc");
?>
<script src="/jquery/jquery-1.11.2.min.js"></script>
<?php
if (!is_array($config['shaper']['queue']) || count($config['shaper']['queue']) < 1) {
	print_info_box(gettext("Traffic shaping is not configured."));
	include("foot.inc");
	exit;
}
?>
<?php if (!$error): ?>
<form action="status_queues.php" method="post">
<script type="text/javascript">
//<![CDATA[
	function getqueueactivity() {
		var url = "/status_queues.php";
		var pars = "getactivity=yes&stats=" + jQuery("#selStatistic").val();
		jQuery.ajax(
			url,
			{
				type: 'post',
				data: pars,
				complete: activitycallback
			});
	}
	function activitycallback(transport) {
		setTimeout('getqueueactivity()', 5100);
	}
	jQuery(document).ready(function() {
		setTimeout('getqueueactivity()', 150);
	});
//]]>
</script>
<?php endif;

if ($error):
	print_info_box($error);
else: ?>
	<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Status Queues"); ?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th><?=gettext("Queue"); ?></th>
						<th><?=gettext("Statistics"); ?>
							<select id="selStatistic">
								<option value="0">PPS</option>
								<option value="1">Bandwidth</option>
							</select>
						</th>
						<th><?=gettext("PPS"); ?></th>
						<th><?=gettext("Bandwidth"); ?></th>
						<th><?=gettext("Borrows"); ?></th>
						<th><?=gettext("Suspends"); ?></th>
						<th><?=gettext("Drops"); ?></th>
						<th><?=gettext("Length"); ?></th>
					</tr>
				</thead>
				<tbody>
<?php
	$if_queue_list = get_configured_interface_list_by_realif(false, true);
	processQueues($altqstats, 0, "");
?>
<?php endif; ?>
				</tbody>
			</table>
		<br />
<?php
		print_info_box(gettext("Queue graphs take 5 seconds to sample data"));
?>
		</div>
	</div>
<br/>

<?php



?>

<script type="text/javascript">
//<![CDATA[
	function StatsShowHide(classname) {
		var firstrow = jQuery("." + classname).first();
		if (firstrow.is(':visible')) {
			jQuery("." + classname).hide();
		} else {
			jQuery("." + classname).show();
		}
	}
//]]>
</script>
</form>
<?php

include("foot.inc");

function processQueues($altqstats, $level, $parent_name) {
	global $g;
	global $if_queue_list;
	$gray_value = 190 + $level * 10;
	if ($gray_value > 250) {
		$gray_value = 255;
	}
	$row_background = str_repeat(dechex($gray_value), 3);
	$parent_name = $parent_name . " queuerow" . $altqstats['name'] . $altqstats['interface'];
	$prev_if = $altqstats['interface'];
	foreach ($altqstats['queue'] as $q) {
		$if_name = "";
		foreach ($if_queue_list as $oif => $real_name) {
			if ($oif == $q['interface']) {
				$if_name = $real_name;
				break;
			}
		}
		if ($prev_if != $q['interface']) {
			echo "<tr><td colspan=\"8\"><b>Interface " . htmlspecialchars(convert_real_interface_to_friendly_descr($q['interface'])) . "</b></td></tr>\n";
			$prev_if = $q['interface'];
		}
?>
		<tr class="<?php echo $parent_name?>">
			<td style="background-color:#<?php echo $row_background?>;padding-left:<?php echo $level * 20?>px;">
				<?php
				if (is_array($q['queue'])) {
					echo "<a href=\"#\" onclick=\"StatsShowHide('queuerow{$q['name']}{$q['interface']}');return false\">+/-</a>";
				}
				if (strstr($q['name'], "root_")) {
					echo "<a href=\"firewall_shaper.php?interface={$if_name}&amp;queue={$if_name}&amp;action=show\">Root queue</a>";
				} else {
					echo "<a href=\"firewall_shaper.php?interface={$if_name}&amp;queue={$q['name']}&amp;action=show\">" . htmlspecialchars($q['name']) . "</a>";
				}
				?>
			</td>
<?php
		$cpuUsage = 0;
		echo "<td style=\"background-color:#{$row_background}\" >";
		echo "<div class=\"progress\" style=\"height: 7px;width: 170px;\">
				<div class=\"progress-bar\" role=\"progressbar\" id=\"queue{$q['name']}{$q['interface']}width\" aria-valuenow=\"70\" aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width: " . ($cpuUsage*100) . "%;\"></div>
			  </div>";
		echo "</td>";
		echo "<td style=\"background-color:#{$row_background}\"><input style=\"border:0;width:70px;text-align:right;\" size=\"10\" name=\"queue{$q['name']}{$q['interface']}pps\" id=\"queue{$q['name']}{$q['interface']}pps\" value=\"(" . gettext("Loading") . ")\" /></td>";
		echo "<td style=\"background-color:#{$row_background}\"><input style=\"border:0;width:80px;text-align:right;\" size=\"10\" name=\"queue{$q['name']}{$q['interface']}bps\" id=\"queue{$q['name']}{$q['interface']}bps\" value=\"\" /></td>";
		echo "<td style=\"background-color:#{$row_background}\"><input style=\"border:0;width:70px;text-align:right;\" size=\"10\" name=\"queue{$q['name']}{$q['interface']}borrows\" id=\"queue{$q['name']}{$q['interface']}borrows\" value=\"\" /></td>";
		echo "<td style=\"background-color:#{$row_background}\"><input style=\"border:0;width:70px;text-align:right;\" size=\"10\" name=\"queue{$q['name']}{$q['interface']}suspends\" id=\"queue{$q['name']}{$q['interface']}suspends\" value=\"\" /></td>";
		echo "<td style=\"background-color:#{$row_background}\"><input style=\"border:0;width:70px;text-align:right;\" size=\"10\" name=\"queue{$q['name']}{$q['interface']}drops\" id=\"queue{$q['name']}{$q['interface']}drops\" value=\"\" /></td>";
		echo "<td style=\"background-color:#{$row_background}\"><input style=\"border:0;width:70px;text-align:right;\" size=\"10\" name=\"queue{$q['name']}{$q['interface']}length\" id=\"queue{$q['name']}{$q['interface']}length\" value=\"\" /></td>";
?>
		</tr>
<?php
		if (is_array($q['queue'])) {
			processQueues($q, $level + 1, $parent_name);
		}
	};
}

function statsQueues($xml) {
	global $statistics;

	$current = new QueueStats();
	$child = new QueueStats();
	$current->queuename = $xml['name'] . $xml['interface'];
	$current->queuelength = $xml['qlength'];
	$current->pps = $xml['measured'];
	$current->bandwidth = $xml['measuredspeedint'];
	$current->borrows = intval($xml['borrows']);
	$current->suspends = intval($xml['suspends']);
	$current->drops = intval($xml['droppedpkts']);
	if (is_array($xml['queue'])) {
		foreach ($xml['queue'] as $q) {
			$child = statsQueues($q);
			$current->pps += $child->pps;
			$current->bandwidth += $child->bandwidth;
			$current->borrows += $child->borrows;
			$current->suspends += $child->suspends;
			$current->drops += $child->drops;
		}
	}
	unset($child);
	$statistics[] = $current;
	return $current;
}
function format_bits($bits) {
	if ($bits >= 1000000000) {
		return sprintf("%.2f Gbps", $bits/1000000000);
	} else if ($bits >= 1000000) {
		return sprintf("%.2f Mbps", $bits/1000000);
	} else if ($bits >= 1000) {
		return sprintf("%.2f Kbps", $bits/1000);
	} else {
		return sprintf("%d bps", $bits);
	}
}
?>
