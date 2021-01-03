<?php
/*
 * diag_states_summary.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Colin Smith
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
##|*IDENT=page-diagnostics-statessummary
##|*NAME=Diagnostics: States Summary
##|*DESCR=Allow access to the 'Diagnostics: States Summary' page.
##|*MATCH=diag_states_summary.php*
##|-PRIV

exec("/sbin/pfctl -s state", $states);

$srcipinfo = array();
$dstipinfo = array();
$allipinfo = array();
$pairipinfo = array();

function addipinfo(&$iparr, $ip, $proto, $srcport, $dstport) {
	$iparr[$ip]['seen']++;
	$iparr[$ip]['protos'][$proto]['seen']++;
	if (!empty($srcport)) {
		$iparr[$ip]['protos'][$proto]['srcports'][$srcport]++;
	}
	if (!empty($dstport)) {
		$iparr[$ip]['protos'][$proto]['dstports'][$dstport]++;
	}
}

$row = 0;
if (count($states) > 0) {
	foreach ($states as $line) {
		$line_split = preg_split("/\s+/", $line);
		$iface = array_shift($line_split);
		$proto = array_shift($line_split);
		$state = array_pop($line_split);
		$info = implode(" ", $line_split);

		/* Handle NAT cases
		   Replaces an external IP + NAT by the internal IP */
		if (strpos($info, ') ->') !== FALSE) {
			/* Outbound NAT */
			$info = preg_replace('/(\S+) \((\S+)\)/U', "$2", $info);
		} elseif (strpos($info, ') <-') !== FALSE) {
			/* Inbound NAT/Port Forward */
			$info = preg_replace('/(\S+) \((\S+)\)/U', "$1", $info);
		}

		/* break up info and extract $srcip and $dstip */
		$ends = preg_split("/\<?-\>?/", $info);

		if (strpos($info, '->') === FALSE) {
			$srcinfo = $ends[count($ends) - 1];
			$dstinfo = $ends[0];
		} else {
			$srcinfo = $ends[0];
			$dstinfo = $ends[count($ends) - 1];
		}

		/* Handle IPv6 */
		$parts = explode(":", $srcinfo);
		$partcount = count($parts);
		if ($partcount <= 2) {
			$srcip = trim($parts[0]);
			$srcport = trim($parts[1]);
		} else {
			preg_match("/([0-9a-f:]+)(\[([0-9]+)\])?/i", $srcinfo, $matches);
			$srcip = $matches[1];
			$srcport = trim($matches[3]);
		}

		$parts = explode(":", $dstinfo);
		$partcount = count($parts);
		if ($partcount <= 2) {
			$dstip = trim($parts[0]);
			$dstport = trim($parts[1]);
		} else {
			preg_match("/([0-9a-f:]+)(\[([0-9]+)\])?/i", $dstinfo, $matches);
			$dstip = $matches[1];
			$dstport = trim($matches[3]);
		}

		addipinfo($srcipinfo, $srcip, $proto, $srcport, $dstport);
		addipinfo($dstipinfo, $dstip, $proto, $srcport, $dstport);
		addipinfo($pairipinfo, "{$srcip} -> {$dstip}", $proto, $srcport, $dstport);

		addipinfo($allipinfo, $srcip, $proto, $srcport, $dstport);
		addipinfo($allipinfo, $dstip, $proto, $srcport, $dstport);
	}
}

function sort_by_ip($a, $b) {
	return ip2ulong($a) < ip2ulong($b) ? -1 : 1;
}

function build_port_info($portarr, $proto) {
	if (!$portarr) {
		return '';
	}
	$ports = array();
	asort($portarr);
	foreach (array_reverse($portarr, TRUE) as $port => $count) {
		$service = getservbyport($port, strtolower($proto));
		$port = "{$proto}/{$port}";
		if ($service) {
			$port = "{$port} ({$service})";
		}
		$ports[] = "{$port}: {$count}";
	}
	return implode(', ', $ports);
}

function print_summary_table($label, $iparr, $sort = TRUE) {
	if ($sort) {
		uksort($iparr, "sort_by_ip");
	}

?>
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title"><?=$label?></h2>
		</div>
		<div class="panel-body">
			<div class="table-responsive">
				<table class="table table-hover table-condensed table-striped">
					<thead>
						<tr>
							<th></th><th></th><th></th>
							<th colspan="3" class="text-center colspanth"><?=gettext("Protocol counts")?></th>
						</tr>

						<tr>
							<th ><?=gettext("IP");?></th>
							<th class="text-center"># <?=gettext("States");?></th>
							<th ><?=gettext("Protocol");?></th>
							<th class="text-center"># <?=gettext("States");?></th>
							<th class="text-center"><?=gettext("Source Ports");?></th>
							<th class="text-center"><?=gettext("Dest. Ports");?></th>
						</tr>
					</thead>
					<tbody>
<?php foreach ($iparr as $ip => $ipinfo):
	$protocolCount = count($ipinfo['protos']);
	$rowSpan = '';
	$i = 0;

	if ($protocolCount > 1) {
		$rowSpan = ' rowspan="' . $protocolCount . '"';
	}
?>
						<tr>
							<td<?= $rowSpan ?>><?=$ip;?></td>
							<td<?= $rowSpan ?> class="text-center"><?=$ipinfo['seen'];?></td>

<?php foreach ($ipinfo['protos'] as $proto => $protoinfo): ?>
<?php if ($protocolCount > 1 && $i > 0): ?>
							</tr><tr>
<?php endif;

	$srscnt = is_array($protoinfo['srcports']) ? count($protoinfo['srcports']) : 0;
	$dstcnt = is_array($protoinfo['dstports']) ? count($protoinfo['dstports']) : 0;

?>
							<td><?=$proto;?></td>
							<td class="text-center" ><?=$protoinfo['seen'];?></td>
							<td class="text-center" ><span title="<?=build_port_info($protoinfo['srcports'], $proto);?>"><?=$srccnt?></span></td>
							<td class="text-center" ><span title="<?=build_port_info($protoinfo['dstports'], $proto);?>"><?=$dstcnt?></span></td>
<?php $i++; endforeach; ?>
						</tr>
<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
<?php
}

$pgtitle = array(gettext("Diagnostics"), gettext("States Summary"));
require_once("guiconfig.inc");
include("head.inc");

print_summary_table(gettext("By Source IP"), $srcipinfo);
print_summary_table(gettext("By Destination IP"), $dstipinfo);
print_summary_table(gettext("Total per IP"), $allipinfo);
print_summary_table(gettext("By IP Pair"), $pairipinfo, FALSE);

include("foot.inc");
