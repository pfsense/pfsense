#!/usr/local/bin/php
<?php
/* $Id$ */
/*
	status_queues.php
        Part of the pfSense project
	Copyright (C) 2004, 2005 Scott Ullrich
	Copyright (C) 2009 Ermal Luçi
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
	pfSense_MODULE:	shaper
*/

##|+PRIV
##|*IDENT=page-status-trafficshaper-queues
##|*NAME=Status: Traffic shaper: Queues page
##|*DESCR=Allow access to the 'Status: Traffic shaper: Queues' page.
##|*MATCH=status_queues.php*
##|-PRIV

header("Last-Modified: " . gmdate( "D, j M Y H:i:s" ) . " GMT" );
header("Expires: " . gmdate( "D, j M Y H:i:s", time() ) . " GMT" );
header("Cache-Control: no-store, no-cache, must-revalidate" ); // HTTP/1.1
header("Cache-Control: post-check=0, pre-check=0", FALSE );
header("Pragma: no-cache"); // HTTP/1.0

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
	$error = "Something wrong happened during comunication with stat gathering";
} else {
	$stats = "";
	while(!feof($fd))
		$stats .= fread($fd, 4096);
	fclose($fd);
	@file_put_contents("{$g['tmp_path']}/qstats", $stats);
	$altqstats = @parse_xml_config("{$g['tmp_path']}/qstats", array("altqstats"));
	if ($altqstats == -1)
		$error = "No queue statistics could be read.";
}
if ($_REQUEST['getactivity']) {	
	$statistics = array();
	$bigger_stat = 0;
	$stat_type = $_REQUEST['stats'];
	/* build the queue stats. */
	foreach($altqstats['queue'] as $q) {
		statsQueues($q);
	}
	/* calculate the bigger amount of packets or bandwidth being moved through all queues. */
	if ($stat_type == "0")
	{
		foreach($statistics as $q) {
			if ($bigger_stat < $q->pps)
				$bigger_stat = $q->pps;
		}
	}
	else
	{
		foreach($statistics as $q) {
			if ($bigger_stat < $q->bandwidth)
				$bigger_stat = $q->bandwidth;
		}
	}	
	$finscript = "";
	foreach($statistics as $q) {
		if ($stat_type == "0")
			$packet_s = round(150 * (1 - $q->pps / $bigger_stat), 0);
		else
			$packet_s = round(150 * (1 - $q->bandwidth / $bigger_stat), 0);
		if ($packet_s < 0) {$packet_s = 0;}
		$finscript .= "jQuery('#queue{$q->queuename}widthb').width('{$packet_s}');";
		$finscript .= "jQuery('#queue{$q->queuename}widtha').width('" . (150 - $packet_s) . "');";
		$finscript .= "jQuery('#queue{$q->queuename}pps').val('" . number_format($q->pps,1) . "');";
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
$pgtitle = array(gettext("Status"),gettext("Traffic shaper"),gettext("Queues"));
$shortcut_section = "trafficshaper";
include("head.inc");
?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php
if(!is_array($config['shaper']['queue']) || count($config['shaper']['queue']) < 1) {
	echo gettext("Traffic shaping is not configured.");
	include("fend.inc");
	echo '</body></html>';
	exit;}
?>
<?php if (!$error): ?>
<form action="status_queues.php" method="post">
<script type="text/javascript">
	/*<![CDATA[*/
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
	jQuery(document).ready(function(){
		setTimeout('getqueueactivity()', 150);
	});
	/*]]>*/
</script>
<?php endif; ?>
<table width="100%" border="1" cellpadding="0" cellspacing="0">
<?php if ($error): ?>
	<tr><td><?php echo $error; ?></td></tr>
<?php else: ?>
	<tr>
		<td class="listhdr"><?=gettext("Queue"); ?></td>
		<td class="listhdr">
			<?=gettext("Statistics"); ?>
			<select id="selStatistic">
				<option value="0">PPS</option>
				<option value="1">Bandwidth</option>
			</select>
		</td>
		<td class="listhdr" width="1%"><?=gettext("PPS"); ?></td>
		<td class="listhdr" width="1%"><?=gettext("Bandwidth"); ?></td>
		<td class="listhdr" width="1%"><?=gettext("Borrows"); ?></td>
		<td class="listhdr" width="1%"><?=gettext("Suspends"); ?></td>
		<td class="listhdr" width="1%"><?=gettext("Drops"); ?></td>
		<td class="listhdr" width="1%"><?=gettext("Length"); ?></td>		
	</tr>
	<?php 
	$if_queue_list = get_configured_interface_list_by_realif(false, true);
	processQueues($altqstats, 0, "")?>
<?php endif; ?>
</table>
<p>
	<strong><span class="red"><?=gettext("Note"); ?>:</span></strong><br/>
	<?=gettext("Queue graphs take 5 seconds to sample data"); ?>.<br/>
	<?=gettext("You can configure the Traffic Shaper"); ?> <a href="/firewall_shaper_wizards.php"><?=gettext("here"); ?></a>.
</p>
<script type="text/javascript">
	function StatsShowHide(classname) {
    var firstrow = jQuery("." + classname).first();
    if (firstrow.is(':visible')) {
        jQuery("." + classname).hide();}
    else {
        jQuery("." + classname).show();}
	}
</script>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
<?php 
function processQueues($altqstats, $level, $parent_name){
	global $g;
	global $if_queue_list;
	$gray_value = 190 + $level * 10;
	if ($gray_value > 250) $gray_value = 255;
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
			echo "<tr><td colspan=\"8\" style=\"padding: 2px;\"><b>Interface ". htmlspecialchars(convert_real_interface_to_friendly_descr($q['interface'])) . "</b></td></tr>";
			$prev_if = $q['interface'];
		}
		?>
		<tr class="<?php echo $parent_name?>">
			<td bgcolor="#<?php echo $row_background?>" style="padding-left: <?php echo $level * 20?>px;">
				<font color="#000000">
					<?
					if (strstr($q['name'], "root_"))
						echo "<a href=\"firewall_shaper.php?interface={$if_name}&amp;queue={$if_name}&amp;action=show\">Root queue</a>";
					else
						echo "<a href=\"firewall_shaper.php?interface={$if_name}&amp;queue={$q['name']}&amp;action=show\">" . htmlspecialchars($q['name']) . "</a>";
					?>
				</font>
			</td>
			<?php
			$cpuUsage = 0;
			echo "<td nowrap=\"nowrap\" width=\"1%\" bgcolor=\"#{$row_background}\">";
			echo "<img src='./themes/".$g['theme']."/images/misc/bar_left.gif' height='10' width='4' border='0' align='middle' alt='' />";
			echo "<img src='./themes/".$g['theme']."/images/misc/bar_blue.gif' height='10' name='queue{$q['name']}{$q['interface']}widtha' id='queue{$q['name']}{$q['interface']}widtha' width='" . $cpuUsage . "' border='0' align='middle' alt='" . htmlspecialchars($q['name']) . "' />";
			echo "<img src='./themes/".$g['theme']."/images/misc/bar_gray.gif' height='10' name='queue{$q['name']}{$q['interface']}widthb' id='queue{$q['name']}{$q['interface']}widthb' width='" . (150 - $cpuUsage) . "' border='0' align='middle' alt='" . htmlspecialchars($q['name']) . "' />";
			echo "<img src='./themes/".$g['theme']."/images/misc/bar_right.gif' height='10' width='5' border='0' align='middle' alt='' /> ";
			if (is_array($q['queue'])) {
				echo "<a href=\"#\" onclick=\"StatsShowHide('queuerow{$q['name']}{$q['interface']}');return false\">+/-</a> ";
			}
			echo " </td>";
			echo "<td width=\"1%\" bgcolor=\"#{$row_background}\"><input style='border: 0px solid white; background-color:#{$row_background}; color:#000000;width:70px;text-align:right;' size='10' name='queue{$q['name']}{$q['interface']}pps' id='queue{$q['name']}{$q['interface']}pps' value='(" . gettext("Loading") . ")' align='left' /></td>";
			echo "<td width=\"1%\" bgcolor=\"#{$row_background}\"><input style='border: 0px solid white; background-color:#{$row_background}; color:#000000;width:80px;text-align:right;' size='10' name='queue{$q['name']}{$q['interface']}bps' id='queue{$q['name']}{$q['interface']}bps' value='' align='right' /></td>";
			echo "<td width=\"1%\" bgcolor=\"#{$row_background}\"><input style='border: 0px solid white; background-color:#{$row_background}; color:#000000;width:70px;text-align:right;' size='10' name='queue{$q['name']}{$q['interface']}borrows' id='queue{$q['name']}{$q['interface']}borrows' value='' align='right' /></td>";
			echo "<td width=\"1%\" bgcolor=\"#{$row_background}\"><input style='border: 0px solid white; background-color:#{$row_background}; color:#000000;width:70px;text-align:right;' size='10' name='queue{$q['name']}{$q['interface']}suspends' id='queue{$q['name']}{$q['interface']}suspends' value='' align='right' /></td>";
			echo "<td width=\"1%\" bgcolor=\"#{$row_background}\"><input style='border: 0px solid white; background-color:#{$row_background}; color:#000000;width:70px;text-align:right;' size='10' name='queue{$q['name']}{$q['interface']}drops' id='queue{$q['name']}{$q['interface']}drops' value='' align='right' /></td>";
			echo "<td width=\"1%\" bgcolor=\"#{$row_background}\"><input style='border: 0px solid white; background-color:#{$row_background}; color:#000000;width:70px;text-align:right;' size='10' name='queue{$q['name']}{$q['interface']}length' id='queue{$q['name']}{$q['interface']}length' value='' align='right' /></td>";			
			?>
		</tr>
		<?php
		if (is_array($q['queue']))
			processQueues($q, $level + 1, $parent_name);
	};
}
function statsQueues($xml){
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
		foreach($xml['queue'] as $q) {
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
