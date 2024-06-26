<?php
/*
 * status_ntpd.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013 Dagorlad
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
##|*IDENT=page-status-ntp
##|*NAME=Status: NTP
##|*DESCR=Allow access to the 'Status: NTP' page.
##|*MATCH=status_ntpd.php*
##|-PRIV

require_once('config.inc');
require_once('config.lib.inc');
require_once('guiconfig.inc');

$allow_query = !config_path_enabled('ntpd','noquery');
foreach (config_get_path('ntpd/restrictions/row', []) as $v) {
	if (ip_in_subnet('127.0.0.1', "{$v['acl_network']}/{$v['mask']}") || 
		ip_in_subnet('::1', "{$v['acl_network']}/{$v['mask']}")) {
		$allow_query = !isset($v['noquery']);
	}
}

if ($allow_query && (config_get_path('ntpd/enable') != 'disabled')) {
	if (config_path_enabled('system','ipv6allow')) {
		$inet_version = "";
	} else {
		$inet_version = " -4";
	}

	exec('/usr/local/sbin/ntpq -pnw' . $inet_version . ' | /usr/bin/tail +3 | /usr/bin/awk -v RS= \'{gsub(/\n[[:space:]][[:space:]]+/," ")}1\'', $ntpq_output);
	exec('/usr/local/sbin/ntpq -c associations' . $inet_version . ' | /usr/bin/tail +3 | /usr/bin/awk -v RS= \'{gsub(/\n[[:space:]][[:space:]]\n+/," ")}1\'', $ntpq_associations_output);

	$ntpq_servers = array();
	$ntpq_server_responses = array();

	foreach ($ntpq_associations_output as $i => $line) {
		$associations_response = array();
		$peerinfo = preg_split("/[\s\t]+/", $line);
		$server['ind'] = $peerinfo[1];
		$associations_response['assid'] = $peerinfo[2];
		$associations_response['status_word'] = $peerinfo[3];
		$associations_response['conf'] = $peerinfo[4];
		$associations_response['reach'] = $peerinfo[5];
		$associations_response['auth'] = $peerinfo[6];
		$associations_response['condition'] = $peerinfo[7];
		$associations_response['last_event'] = $peerinfo[8];
		$associations_response['cnt'] = $peerinfo[9];
		$ntpq_server_responses[$i] = $associations_response;
	}

	foreach ($ntpq_output as $i => $line) {
		$server = array();
		$status_char = substr($line, 0, 1);
		$line = substr($line, 1);
		$peerinfo = preg_split("/[\s\t]+/", $line);

		$server['server'] = $peerinfo[0];
		$server['refid'] = $peerinfo[1];
		$server['stratum'] = $peerinfo[2];
		$server['type'] = $peerinfo[3];
		$server['when'] = $peerinfo[4];
		$server['poll'] = $peerinfo[5];
		$server['reach'] = $peerinfo[6];
		$server['delay'] = $peerinfo[7];
		$server['offset'] = $peerinfo[8];
		$server['jitter'] = $peerinfo[9];

		$server['ind'] = $ntpq_server_responses[$i]['ind'];
		$server['assid'] = $ntpq_server_responses[$i]['assid'];
		$server['status_word'] = $ntpq_server_responses[$i]['status_word'];
		$server['conf'] = $ntpq_server_responses[$i]['conf'];
		$server['auth'] = $ntpq_server_responses[$i]['auth'];
		$server['condition'] = $ntpq_server_responses[$i]['condition'];
		$server['last_event'] = $ntpq_server_responses[$i]['last_event'];
		$server['cnt'] = $ntpq_server_responses[$i]['cnt'];

		switch ($status_char) {
			case " ":
				if ($server['refid'] == ".POOL.") {
					$server['status'] = gettext("Pool Placeholder");
				} else {
					$server['status'] = gettext("Unreach/Pending");
				}
				break;
			case "*":
				$server['status'] = gettext("Active Peer");
				break;
			case "+":
				$server['status'] = gettext("Candidate");
				break;
			case "o":
				$server['status'] = gettext("PPS Peer");
				break;
			case "#":
				$server['status'] = gettext("Selected");
				break;
			case ".":
				$server['status'] = gettext("Excess Peer");
				break;
			case "x":
				$server['status'] = gettext("False Ticker");
				break;
			case "-":
				$server['status'] = gettext("Outlier");
				break;
		}

		$ntpq_servers[] = $server;
	}

	exec("/usr/local/sbin/ntpq -c clockvar $inet_version", $ntpq_clockvar_output);
	foreach ($ntpq_clockvar_output as $line) {
		if (substr($line, 0, 9) == "timecode=") {
			$tmp = explode('"', $line);
			$tmp = $tmp[1];
			if (substr($tmp, 0, 6) == '$GPRMC') {
				$gps_vars = explode(",", $tmp);
				$gps_ok = ($gps_vars[2] == "A");
				$gps_lat_deg = substr($gps_vars[3], 0, 2);
				$gps_lat_min = substr($gps_vars[3], 2);
				$gps_lon_deg = substr($gps_vars[5], 0, 3);
				$gps_lon_min = substr($gps_vars[5], 3);
				$gps_lat = (float) $gps_lat_deg + (float) $gps_lat_min / 60.0;
				$gps_lat = $gps_lat * (($gps_vars[4] == "N") ? 1 : -1);
				$gps_lon = (float) $gps_lon_deg + (float) $gps_lon_min / 60.0;
				$gps_lon = $gps_lon * (($gps_vars[6] == "E") ? 1 : -1);
				$gps_lat_dir = $gps_vars[4];
				$gps_lon_dir = $gps_vars[6];
			} elseif (substr($tmp, 0, 6) == '$GPGGA') {
				$gps_vars = explode(",", $tmp);
				$gps_ok = $gps_vars[6];
				$gps_lat_deg = substr($gps_vars[2], 0, 2);
				$gps_lat_min = substr($gps_vars[2], 2);
				$gps_lon_deg = substr($gps_vars[4], 0, 3);
				$gps_lon_min = substr($gps_vars[4], 3);
				$gps_lat = (float) $gps_lat_deg + (float) $gps_lat_min / 60.0;
				$gps_lat = $gps_lat * (($gps_vars[3] == "N") ? 1 : -1);
				$gps_lon = (float) $gps_lon_deg + (float) $gps_lon_min / 60.0;
				$gps_lon = $gps_lon * (($gps_vars[5] == "E") ? 1 : -1);
				$gps_alt = $gps_vars[9];
				$gps_alt_unit = $gps_vars[10];
				$gps_sat = (int)$gps_vars[7];
				$gps_lat_dir = $gps_vars[3];
				$gps_lon_dir = $gps_vars[5];
			} elseif (substr($tmp, 0, 6) == '$GPGLL') {
				$gps_vars = preg_split('/[,\*]+/', $tmp);
				$gps_ok = ($gps_vars[6] == "A");
				$gps_lat_deg = substr($gps_vars[1], 0, 2);
				$gps_lat_min = substr($gps_vars[1], 2);
				$gps_lon_deg = substr($gps_vars[3], 0, 3);
				$gps_lon_min = substr($gps_vars[3], 3);
				$gps_lat = (float) $gps_lat_deg + (float) $gps_lat_min / 60.0;
				$gps_lat = $gps_lat * (($gps_vars[2] == "N") ? 1 : -1);
				$gps_lon = (float) $gps_lon_deg + (float) $gps_lon_min / 60.0;
				$gps_lon = $gps_lon * (($gps_vars[4] == "E") ? 1 : -1);
				$gps_lat_dir = $gps_vars[2];
				$gps_lon_dir = $gps_vars[4];
			} elseif (substr($tmp, 0, 6) == '$PGRMF') {
				$gps_vars = preg_split('/[,\*]+/', $tmp);
				$gps_ok = $gps_vars[11];
				$gps_lat_deg = substr($gps_vars[6], 0, 2);
				$gps_lat_min = substr($gps_vars[6], 2);
				$gps_lon_deg = substr($gps_vars[8], 0, 3);
				$gps_lon_min = substr($gps_vars[8], 3);
				$gps_lat = (float) $gps_lat_deg + (float) $gps_lat_min / 60.0;
				$gps_lat = $gps_lat * (($gps_vars[7] == "N") ? 1 : -1);
				$gps_lon = (float) $gps_lon_deg + (float) $gps_lon_min / 60.0;
				$gps_lon = $gps_lon * (($gps_vars[9] == "E") ? 1 : -1);
				$gps_lat_dir = $gps_vars[7];
				$gps_lon_dir = $gps_vars[9];
			}
		}
	}
}

global $showgps;
$showgps = 0;

global $gps_goo_lnk;
$gps_goo_lnk = 1;

// GPS satellite information (if available)
if (($gps_ok) && ($gps_lat) && ($gps_lon)) {
	$gps_goo_lnk = 2;
	$showgps = 1;
}

if (isset($gps_ok) && config_path_enabled('ntpd/gps','extstatus')) {
	$lookfor['GPGSV'] = config_path_enabled('ntpd/gps/nmeaset','gpgsv');
	$lookfor['GPGGA'] = !isset($gps_sat) && config_path_enabled('ntpd/gps/nmeaset','gpgga');
	$gpsport = fopen('/dev/gps0', 'r+');
	while ($gpsport && ($lookfor['GPGSV'] || $lookfor['GPGGA'])) {
		$buffer = fgets($gpsport);
		if ($lookfor['GPGSV'] && substr($buffer, 0, 6) == '$GPGSV') {
			$gpgsv = explode(',', $buffer);
			$gps_satview = (int)$gpgsv[3];
			$lookfor['GPGSV'] = false;
		} elseif ($lookfor['GPGGA'] && substr($buffer, 0, 6) == '$GPGGA') {
			$gpgga = explode(',', $buffer);
			$gps_sat = (int)$gpgga[7];
			$gps_alt = $gpgga[9];
			$gps_alt_unit = $gpgga[10];
			$lookfor['GPGGA'] = false;
		}
	}
}

// Responding to an AJAX call, we return the GPS data or the status data depending on $_REQUEST['dogps']
if ($_REQUEST['ajax']) {

	if ($_REQUEST['dogps'] == "yes") {
		print_gps();
	} else {
		print_status();
	}

	exit;
}

function print_status() {
	global $ntpq_servers, $allow_query;

	if (config_get_path('ntpd/enable') == 'disabled'):
		print("<tr>\n");
		print('<td class="warning" colspan="11">');
		printf(gettext('NTP Server is disabled'));
		print("</td>\n");
		print("</tr>\n");
	elseif (!$allow_query):
		print("<tr>\n");
		print('<td class="warning" colspan="11">');
		printf(gettext('Statistics unavailable because ntpq and ntpdc queries are disabled in the %1$sNTP service settings%2$s'), '<a href="services_ntpd.php">', '</a>');
		print("</td>\n");
		print("</tr>\n");
	elseif (count($ntpq_servers) == 0):
		print("<tr>\n");
		print('<td class="warning" colspan="11">');
		printf(gettext('No peers found, %1$sis the ntp service running?%2$s'), '<a href="status_services.php">', '</a>');
		print("</td>\n");
		print("</tr>\n");
	else:

		$i = 0;
		foreach ($ntpq_servers as $server):
			print("<tr>\n");
			print("<td>" . $server['status'] . "</td>\n");
			print("<td>" . $server['server'] . "</td>\n");
			print("<td>" . $server['refid'] . "</td>\n");
			print("<td>" . $server['stratum'] . "</td>\n");
			print("<td>" . $server['type'] . "</td>\n");
			print("<td>" . $server['when'] . "</td>\n");
			print("<td>" . $server['poll'] . "</td>\n");
			print("<td>" . $server['reach'] . "</td>\n");
			print("<td>" . $server['delay'] . "</td>\n");
			print("<td>" . $server['offset'] . "</td>\n");
			print("<td>" . $server['jitter'] . "</td>\n");
			print("<td>" . $server['assid'] . "</td>\n");
			print("<td>" . $server['status_word'] . "</td>\n");
			print("<td>" . $server['auth'] . "</td>\n");
			print("</tr>\n");
			$i++;
		endforeach;
	endif;
}

function print_gps() {
	global 	$gps_lat, $gps_lon, $gps_lat_deg, $gps_lon_deg, $gps_lat_min, $gps_lon_min, $gps_lat_dir, $gps_lon_dir,
			$gps_alt, $gps_alt_unit, $gps_sat, $gps_satview, $gps_goo_lnk;

	print("<tr>\n");
	print("<td>\n");
	printf("%.5f", $gps_lat);
	print(" (");
	printf("%d%s", $gps_lat_deg, "&deg;");
	printf("%.5f", $gps_lat_min);
	print($gps_lat_dir);
	print(")");
	print("</td>\n");
	print("<td>\n");
	printf("%.5f", $gps_lon);
	print(" (");
	printf("%d%s", $gps_lon_deg, "&deg;");
	printf("%.5f", $gps_lon_min);
	print($gps_lon_dir);
	print(")");
	print("</td>\n");

	if (isset($gps_alt)) {
		print("<td>\n");
		print($gps_alt . ' ' . $gps_alt_unit);
		print("</td>\n");
	}

	if (isset($gps_sat) || isset($gps_satview)) {
		print('<td>');

		if (isset($gps_satview)) {
			print(gettext('in view ') . intval($gps_satview));
		}

		if (isset($gps_sat) && isset($gps_satview)) {
			print(', ');
		}
		if (isset($gps_sat)) {
			print(gettext('in use ') . $gps_sat);
		}

		print("</td>\n");
	}

	print("</tr>\n");
	print("<tr>\n");
	print('<td colspan="' . $gps_goo_lnk . '"><a target="_gmaps" href="https://maps.google.com/?q=' . $gps_lat . ',' . $gps_lon . '">' . gettext("Google Maps Link") . '</a></td>');
	print("</tr>\n");
}

$pgtitle = array(gettext("Status"), gettext("NTP"));
$shortcut_section = "ntp";

include("head.inc");
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Network Time Protocol Status");?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><?=gettext("Status")?></th>
					<th><?=gettext("Server")?></th>
					<th><?=gettext("Ref ID")?></th>
					<th><?=gettext("Stratum")?></th>
					<th><?=gettext("Type")?></th>
					<th><?=gettext("When")?></th>
					<th><?=gettext("Poll (s)")?></th>
					<th><?=gettext("Reach")?></th>
					<th><?=gettext("Delay (ms)")?></th>
					<th><?=gettext("Offset (ms)")?></th>
					<th><?=gettext("Jitter (ms)")?></th>
					<th><?=gettext("AssocID")?></th>
					<th><?=gettext("Status Word")?></th>
					<th><?=gettext("Auth")?></th>
				</tr>
			</thead>
			<tbody id="ntpbody">
				<?=print_status()?>
			</tbody>
		</table>
	</div>
</div>


<?php

// GPS satellite information (if available)
if (($gps_ok) && ($gps_lat) && ($gps_lon)):
	$gps_goo_lnk = 2;
	$showgps = 1;
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("GPS Information");?></h2></div>
	<div class="panel-body">
		<table class="table table-striped table-hover table-condensed">
			<thead>
				<tr>
					<th><?=gettext("Clock Latitude")?></th>
					<th><?=gettext("Clock Longitude")?></th>
<?php
	if (isset($gps_alt)) {
?>
					<th><?=gettext("Clock Altitude")?></th>
<?php
		$gps_goo_lnk++;
	}

	if (isset($gps_sat) || isset($gps_satview)) {
?>
					<th><?=gettext("Satellites")?></th>
<?php
		$gps_goo_lnk++;
	}
?>
				</tr>
			</thead>

			<tbody id="gpsbody">
				<?=print_gps()?>
			</tbody>
		</table>
	</div>
</div>

<?php
endif;
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	ajax_lock = false;		// Mutex so we don't make a call until the previous call is finished
	do_gps = "no";

	// Fetch the tbody contents from the server
	function update_tables() {

		if (ajax_lock) {
			return;
		}

		ajax_lock = true;

		ajaxRequest = $.ajax(
			{
				url: "/status_ntpd.php",
				type: "post",
				data: {
					ajax: 	"ajax",
					dogps:  do_gps
				}
			}
		);

		// Deal with the results of the above ajax call
		ajaxRequest.done(function (response, textStatus, jqXHR) {
			if (do_gps == "yes") {
				$('#gpsbody').html(response);
			} else {
				$('#ntpbody').html(response);
			}

			ajax_lock = false;

			// Alternate updating the status table and the gps table (if enabled)
			if ((do_gps == "yes") || ("<?=$showgps?>" != 1)) {
				do_gps = "no";
			} else {
				do_gps = "yes";
			}

			// and do it again
			setTimeout(update_tables, 5000);
		});


	}

	// Populate the tbody on page load
	update_tables();
});
//]]>
</script>

<?php
include("foot.inc");
?>
