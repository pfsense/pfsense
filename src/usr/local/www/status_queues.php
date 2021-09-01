<?php
/*
 * status_queues.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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

require_once("guiconfig.inc");
include_once("shaper.inc");

$stats = get_queue_stats();

$pgtitle = array(gettext("Status"), gettext("Queues"));
$shortcut_section = "trafficshaper";
include("head.inc");

if (!isset($config['shaper']['queue']) || !is_array($config['shaper']['queue']) || count($config['shaper']['queue']) < 1) {
	print_info_box(gettext("Traffic shaping is not configured."));
	include("foot.inc");
	exit;
}

if (!$error): ?>
<form action="status_queues.php" method="post">
<script type="text/javascript">
//<![CDATA[
var refreshrate = 1000;
var queuestathistorylength = 0;
var queuestathistory = [];
var queuestatprevious = [];
var timestampprevious;
var graphstatmax = 0;
events.push(function() {
	$('#updatespeed').on('change', function() {
		refreshrate = $("#updatespeed").val();
	});

	function getqueueactivity() {
		var url = "/getqueuestats.php";
		var pars = "format=json";
		$.ajax(
			url,
			{
				type: 'post',
				data: pars,
				complete: activitycallback
			});
	}

	function escapeStr(str)
	{
		if (str)
			return str.replace(/([ #;?%&,.+*~\':"!^$[\]()=>|\/@])/g,'\\$1');
		return str;
	}

	function activitycallback(transport) {
		setTimeout(getqueueactivity, refreshrate);
		json = transport.responseJSON;
		if (!json) {
			return;
		}
		timestamp = json.timestamp;
		timestampdiff = timestamp - timestampprevious;
		$stattype = $('#selStatistic').val();

		interfacename_stats = [];
		for (interfacename in json.interfacestats) {
			var queueparents = [];
			interface = json.interfacestats[interfacename];
			interfacename_stats[interfacename] = [];
			for (queuename in interface) {
				queue = interface[queuename];
				statqname = queue['name'] + queue['interface'];

				for(childnr in queue['contains']) {
					child = queue['contains'][childnr];
					if (!queueparents[child]) {
						queueparents[child] = [];
					}
					queueparents[child] = queuename;
				}

				if (queuestatprevious[statqname]) {
					interfacename_stats[interfacename][statqname] = [];
					pkts_ps = (queue['pkts'] - queuestatprevious[statqname]['pkts']) / timestampdiff
					bytes_ps = (queue['bytes'] - queuestatprevious[statqname]['bytes']) / timestampdiff
					droppedpkts = parseFloat(queue['droppedpkts']);
					borrows = parseFloat(queue['borrows']);
					suspends = parseFloat(queue['suspends']);
					interfacename_stats[interfacename][statqname]['pkts_ps'] = pkts_ps;
					interfacename_stats[interfacename][statqname]['bytes_ps'] = bytes_ps;
					interfacename_stats[interfacename][statqname]['borrows'] = borrows;
					interfacename_stats[interfacename][statqname]['suspends'] = suspends;
					interfacename_stats[interfacename][statqname]['droppedpkts'] = droppedpkts;
					interfacename_stats[interfacename][statqname]['qlengthitems'] = queue['qlengthitems'];
					interfacename_stats[interfacename][statqname]['qlengthsize'] = queue['qlengthsize'];
					find = queuename;
					while(queueparents[find]) {
						// add diff values also to parent queues
						parentname = queueparents[find];
						parentqueuename = parentname+interfacename;
						if (parentname.indexOf('root_') !== 0) {
							interfacename_stats[interfacename][parentqueuename]['pkts_ps'] += pkts_ps;
							interfacename_stats[interfacename][parentqueuename]['bytes_ps'] += bytes_ps;
						}
						interfacename_stats[interfacename][parentqueuename]['borrows'] += borrows;
						interfacename_stats[interfacename][parentqueuename]['suspends'] += suspends;
						interfacename_stats[interfacename][parentqueuename]['droppedpkts'] += droppedpkts;
						find = parentname;
					}
				}
				queuestatprevious[statqname] = queue;
			}
		}
		// Find max pps/bps needed for any scale bar
		statmax = 0;
		for (interfacename in interfacename_stats) {
			interface = interfacename_stats[interfacename];
			for (queuename in interface) {
				queue = interface[queuename];
				if ($stattype == "0") {
					if (statmax < queue['pkts_ps']) {
						statmax = queue['pkts_ps'];
					}
				} else {
					if (statmax < queue['bytes_ps']) {
						statmax = queue['bytes_ps'];
					}
				}
			}
		}
		// use a slowly sliding max scale value but do make sure its always large enough to accommodate the largest value..
		if (graphstatmax < statmax) {
			// peek value + 10% keeps a little room for it to increase
			graphstatmax = statmax * 1.1;
		} else {
			// in general make largest bar fill +- 2/3 of the scale
			graphstatmax = (graphstatmax * 20 + statmax * 1.5) / 21;
		}
		// set values on the objects
		for (interfacename in interfacename_stats) {
			interface = interfacename_stats[interfacename];
			for (queuename in interface) {
				queue = interface[queuename];
				statqname = escapeStr(queuename);
				if ($stattype == "0") {
					$('#queue'+statqname+'width').css('width', (queue['pkts_ps']*100/graphstatmax).toFixed(0) + '%');
				} else {
					$('#queue'+statqname+'width').css('width', (queue['bytes_ps']*100/graphstatmax).toFixed(0) + '%');
				}
				$('#queue'+statqname+'pps').val(queue['pkts_ps'].toFixed(1));
				$('#queue'+statqname+'bps').val(formatSpeedBits(queue['bytes_ps']));
				$('#queue'+statqname+'borrows').val(queue['borrows']);
				$('#queue'+statqname+'suspends').val(queue['suspends']);
				$('#queue'+statqname+'drops').val(queue['droppedpkts']);
				$('#queue'+statqname+'length').val(queue['qlengthitems']+'/'+queue['qlengthsize']);
			}
		}
		timestampprevious = timestamp;
	}

	$(document).ready(function() {
		setTimeout(getqueueactivity, 150);
	});
});

function formatSpeedBits(speed) {
	// format speed in bits/sec, input: bytes/sec
	if (speed < 125000) {
		return Math.round(speed / 125) + " <?=gettext("Kbps"); ?>";
	}
	if (speed < 125000000) {
		return Math.round(speed / 1250)/100 + " <?=gettext("Mbps"); ?>";
	}
	// else
	return Math.round(speed / 1250000)/100 + " <?=gettext("Gbps"); ?>";  /* wow! */
}
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
	processInterfaceQueues($stats, 0, "");
?>
<?php endif; ?>
				</tbody>
			</table>
			<br />
			<div class="infoblock blockopen">
<?php
	print_info_box(gettext("Queue graphs sample data on a regular interval."), 'info', false);
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

function processInterfaceQueues($altqstats, $parent_name) {
	global $g;
	global $if_queue_list;

	$parent_name = $parent_name . " queuerow" . $altqstats['name'] . convert_real_interface_to_friendly_interface_name($altqstats['interface']);
	$prev_if = $altqstats['interface'];
	if (!is_array($altqstats['interfacestats'])) {
		print("<tr><td>");
		print("No Queue data available");
		print("</td></tr>");
		return;
	}
	foreach ($altqstats['interfacestats'] as $if => $ifq) {
		$parents = array();
		echo "<tr><td colspan=\"8\"><b>Interface " . htmlspecialchars(convert_real_interface_to_friendly_descr($if)) . "</b></td></tr>\n";
		$if_name = "";
		foreach ($if_queue_list as $oif => $real_name) {
			if ($oif == $if) {
				$if_name = $real_name;
				break;
			}
		}
		if ($prev_if != $q['interface']) {
			$prev_if = $q['interface'];
		}
		foreach($ifq as $qkey => $q) {
			$parent_name = $if . " queuerow" . $altqstats['name'] . convert_real_interface_to_friendly_interface_name($altqstats['interface']);
			if (isset($q['contains'])) {
				foreach($q['contains'] as $child) {
					$parents[$child] = $qkey;
				}
			}
			$find = $qkey;
			$level = 0;
			while(isset($parents[$find])) {
				$find = $parents[$find];
				$level++;
				$parent_name = $parent_name . " queuerow" . $find . $q['interface'];
			}
			$qfinterface = convert_real_interface_to_friendly_interface_name($q['interface']);
			$qname = str_replace($q['interface'], $qfinterface, $q['name']);
?>
			<tr class="<?=$parent_name;?>">
				<td class="<?=$row_class?>" style="padding-left:<?=$level * 20?>px;">
					<?php
					if (is_array($q['contains'])) {
						echo "<a href=\"#\" onclick=\"StatsShowHide('queuerow{$qname}{$qfinterface}');return false\">+/-</a>";
					}
					if (strstr($qname, "root_")) {
						echo "<a href=\"firewall_shaper.php?interface={$if_name}&amp;queue={$if_name}&amp;action=show\">Root queue</a>";
					} else {
						echo "<a href=\"firewall_shaper.php?interface={$if_name}&amp;queue={$qname}&amp;action=show\">" . htmlspecialchars($qname) . "</a>";
					}
					?>
				</td>
<?php
			$cpuUsage = 0;
			$stat_prefix = "queue" . $q['name'] . $q['interface'];
			print('<td>');
			print('<div class="progress" style="height: 7px;width: 170px;">');
			print('		<div class="progress-bar" role="progressbar" id="' . $stat_prefix . 'width" aria-valuenow="70" aria-valuemin="0" aria-valuemax="100" style="width: ' . $cpuUsage*100 . '%;"></div>');
			print('	  </div>');
			print('</td>');
			print('<td><input readonly style="border:0;width:70px;text-align:right;" name="' . $stat_prefix . 'pps"      id="' . $stat_prefix . 'pps"      value="(' . gettext("Loading") . ')" /></td>');
			print('<td><input readonly style="border:0;width:80px;text-align:right;" name="' . $stat_prefix . 'bps"      id="' . $stat_prefix . 'bps"      value="" /></td>');
			print('<td><input readonly style="border:0;width:70px;text-align:right;" name="' . $stat_prefix . 'borrows"  id="' . $stat_prefix . 'borrows"  value="" /></td>');
			print('<td><input readonly style="border:0;width:70px;text-align:right;" name="' . $stat_prefix . 'suspends" id="' . $stat_prefix . 'suspends" value="" /></td>');
			print('<td><input readonly style="border:0;width:70px;text-align:right;" name="' . $stat_prefix . 'drops"    id="' . $stat_prefix . 'drops"    value="" /></td>');
			print('<td><input readonly style="border:0;width:70px;text-align:right;" name="' . $stat_prefix . 'length"   id="' . $stat_prefix . 'length"   value="" /></td>');
?>
			</tr>
<?php
			if (is_array($q['queue'])) {
				processInterfaceQueues($q, $level + 1, $parent_name);
			}
		}
	};
}
?>
