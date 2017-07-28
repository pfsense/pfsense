<?php
/*
 * status_queues.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-status-trafficshaper-queues
##|*NAME=Status: Traffic Shaper: Queues
##|*DESCR=Allow access to the 'Status: Traffic Shaper: Queues' page.
##|*MATCH=status_queues.php*
##|-PRIV
/*
header("Last-Modified: " . gmdate("D, j M Y H:i:s") . " GMT");
header("Expires: " . gmdate("D, j M Y H:i:s", time()) . " GMT");
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache"); // HTTP/1.0
*/

if (isset($_REQUEST['getactivity'])) {
	require_once('auth_check.inc');
	require_once('globals.inc');
	if (file_exists("/cf/conf/use_xmlreader")) {
		require_once("xmlreader.inc");
	} else {
		require_once("xmlparse.inc");
	}
	require_once("util.inc");
} else {
	require_once("guiconfig.inc");
}
class QueueStats {
	public $queuename;
	public $queuelength;
	public $pps;
	public $bandwidth;
	public $borrows;
	public $suspends;
	public $drops;
}
if (is_numericint($_REQUEST['refreshrate'])) {
	$refreshrate = $_REQUEST['refreshrate'];
} else {
	$refreshrate = 1000;
}
if (isset($_REQUEST['reinitialize'])) {
	// when switching to a different update frequency the old accumulated average is nolonger valid
	killbypid("{$g['varrun_path']}/qstats.pid");
	usleep(50000);
}

if (!file_exists("{$g['varrun_path']}/qstats.pid") || !isvalidpid("{$g['varrun_path']}/qstats.pid")) {
	/* Start in the background so we don't hang up the GUI */
	mwexec_bg("/usr/local/sbin/qstats -p {$g['varrun_path']}/qstats.pid");
	/* Give it a moment to start up */
	usleep(500000);
	/* Make sure that 'prev_bytes' are known from previous request and read/dump the output */
	$fd = @fsockopen("unix://{$g['varrun_path']}/qstats");
	if (!$fd) {
		$error = gettext("Something wrong happened during communication with stat gathering.");
	} else {
		while (!feof($fd)) {
			fread($fd, 4096);
		}
		fclose($fd);
		usleep($refreshrate * 1000); // make sure the stats retrieved are the proper time appart
	}
}
$fd = @fsockopen("unix://{$g['varrun_path']}/qstats");
if (!$fd) {
	$error = gettext("Something wrong happened during communication with stat gathering.");
} else {
	$stats = "";
	while (!feof($fd)) {
		$stats .= fread($fd, 4096);
	}
	fclose($fd);
	global $listtags;
	$listtags['queue'] = true;
	$altqstats = parse_xml_config_raw($stats, array("altqstats"), true);	
	if ($altqstats == -1) {
		$error = gettext("No queue statistics could be read.");
	}
}

if ($_REQUEST['getactivity']) {
	$ratemultiplier = 5000 / $refreshrate;
	$statistics = array();
	$bigger_stat = 0;
	$stat_type = $_REQUEST['stats'];
	/* build the queue stats. */
	if (is_array($altqstats['queue'])) {
		foreach ($altqstats['queue'] as $q) {
			statsQueues($q);
		}
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
		if ($stat_type == "0" && $bigger_stat != "0") {
			$packet_s = round(100 * ($q->pps / $bigger_stat), 0);
		} else if ($bigger_stat != "0") {
			$packet_s = round(100 * ($q->bandwidth / $bigger_stat), 0);
		} else {
			$packet_s = 0;
		}
		$finscript .= "$('#queue{$q->queuename}width').css('width','" . $packet_s * $ratemultiplier. "%');";
		$finscript .= "$('#queue{$q->queuename}pps').val('" . number_format($q->pps * $ratemultiplier, 1) . "');";
		$finscript .= "$('#queue{$q->queuename}bps').val('" . format_bits($q->bandwidth * $ratemultiplier) . "');";
		$finscript .= "$('#queue{$q->queuename}borrows').val('{$q->borrows}');";
		$finscript .= "$('#queue{$q->queuename}suspends').val('{$q->suspends}');";
		$finscript .= "$('#queue{$q->queuename}drops').val('{$q->drops}');";
		$finscript .= "$('#queue{$q->queuename}length').val('{$q->queuelength}');";
	}
	unset($statistics, $altqstats);
	header("Content-type: text/javascript");
	echo $finscript;
	exit;
}
$pgtitle = array(gettext("Status"), gettext("Queues"));
$shortcut_section = "trafficshaper";
include("head.inc");

if (!is_array($config['shaper']['queue']) || count($config['shaper']['queue']) < 1) {
	print_info_box(gettext("Traffic shaping is not configured."));
	include("foot.inc");
	exit;
}

if (!$error): ?>
<form action="status_queues.php" method="post">
<script type="text/javascript">
//<![CDATA[
var refreshrate = <?=$refreshrate?>;
var reinitialize = false;
events.push(function() {
	$('#updatespeed').on('change', function() {
		refreshrate = $("#updatespeed").val();
		reinitialize = true;
	});

	function getqueueactivity() {
		var url = "/status_queues.php";
		var pars = "getactivity=yes&stats=" + $("#selStatistic").val() + "&refreshrate=" + refreshrate;
		if (reinitialize) {
			pars = pars + "&reinitialize=true";
			reinitialize = false;
		}
		$.ajax(
			url,
			{
				type: 'post',
				data: pars,
				complete: activitycallback
			});
	}

	function activitycallback(transport) {
		setTimeout(getqueueactivity, refreshrate);
	}

	$(document).ready(function() {
		setTimeout(getqueueactivity, 150);
	});
});
//]]>
</script>
<?php endif;

if ($error):
	print_info_box($error);
else: ?>
	<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Settings"); ?></h2></div>
		<div class="panel-body table-responsive">
			<label class="col-sm-2 control-label">
				<span>Refresh rate</span>
			</label>
			<div class="col-sm-10">
				<select id="updatespeed" class="form-control">
					<option value="500">0.5 <?=gettext("seconds");?></option>
					<option value="1000" selected>1 <?=gettext("seconds");?></option>
					<option value="2000">2 <?=gettext("seconds");?></option>
					<option value="5000">5 <?=gettext("seconds");?></option>
				</select>
			</div>
		</div>
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Status Queues"); ?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th><?=gettext("Queue"); ?></th>
						<th><?=gettext("Statistics"); ?>
							<select id="selStatistic" class="form-control">
								<option value="0"><?=gettext("PPS");?></option>
								<option value="1"><?=gettext("Bandwidth");?></option>
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
	$if_queue_list = get_configured_interface_list_by_realif(true);
	processQueues($altqstats, 0, "");
?>
<?php endif; ?>
				</tbody>
			</table>
			<br />
			<div class="infoblock blockopen">
<?php
	print_info_box(gettext("Queue graphs show bandwidth/pps numbers added to the previous value * 7 devided by 8. "
			. "As such it takes 18 updates to show 90% accurate numbers."), 'info', false);
?>
			</div>
		</div>
	</div>
<br/>

<script type="text/javascript">
//<![CDATA[
	function StatsShowHide(classname) {
		var firstrow = $("." + classname).first();
		if (firstrow.is(':visible')) {
			$("." + classname).hide();
		} else {
			$("." + classname).show();
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

	$parent_name = $parent_name . " queuerow" . $altqstats['name'] . $altqstats['interface'];
	$prev_if = $altqstats['interface'];
	if (!is_array($altqstats)) {
		print("<tr class={$parent_name}><td>");
		print("No Queue data available");
		print("</td></tr>");
		return;
	}
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
		<tr class="<?=$parent_name;?>">
			<td class="<?=$row_class?>" style="padding-left:<?=$level * 20?>px;">
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
		print('<td>');
		print('<div class="progress" style="height: 7px;width: 170px;">');
		print('		<div class="progress-bar" role="progressbar" id="queue' . $q['name'] . $q['interface'] . 'width" aria-valuenow="70" aria-valuemin="0" aria-valuemax="100" style="width: ' . $cpuUsage*100 . '%;"></div>');
		print('	  </div>');
		print('</td>');
		print('<td><input readonly style="border:0;width:70px;text-align:right;" name="queue' . $q['name'] . $q['interface'] . 'pps"      id="queue' . $q['name'] . $q['interface'] . 'pps"      value="(' . gettext("Loading") . ')" /></td>');
		print('<td><input readonly style="border:0;width:80px;text-align:right;" name="queue' . $q['name'] . $q['interface'] . 'bps"      id="queue' . $q['name'] . $q['interface'] . 'bps"      value="" /></td>');
		print('<td><input readonly style="border:0;width:70px;text-align:right;" name="queue' . $q['name'] . $q['interface'] . 'borrows"  id="queue' . $q['name'] . $q['interface'] . 'borrows"  value="" /></td>');
		print('<td><input readonly style="border:0;width:70px;text-align:right;" name="queue' . $q['name'] . $q['interface'] . 'suspends" id="queue' . $q['name'] . $q['interface'] . 'suspends" value="" /></td>');
		print('<td><input readonly style="border:0;width:70px;text-align:right;" name="queue' . $q['name'] . $q['interface'] . 'drops"    id="queue' . $q['name'] . $q['interface'] . 'drops"    value="" /></td>');
		print('<td><input readonly style="border:0;width:70px;text-align:right;" name="queue' . $q['name'] . $q['interface'] . 'length"   id="queue' . $q['name'] . $q['interface'] . 'length"   value="" /></td>');
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
