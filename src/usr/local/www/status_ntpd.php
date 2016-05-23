<?php
/*
	status_ntpd.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2013 Dagorlad
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
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

##|+PRIV
##|*IDENT=page-status-ntp
##|*NAME=Status: NTP
##|*DESCR=Allow access to the 'Status: NTP' page.
##|*MATCH=status_ntpd.php*
##|-PRIV

require_once("guiconfig.inc");

if (!isset($config['ntpd']['noquery'])) {
	if (isset($config['system']['ipv6allow'])) {
		$inet_version = "";
	} else {
		$inet_version = " -4";
	}

	exec("/usr/local/sbin/ntpq -pn $inet_version | /usr/bin/tail +3", $ntpq_output);

	$ntpq_servers = array();
	foreach ($ntpq_output as $line) {
		$server = array();

		switch (substr($line, 0, 1)) {
			case " ":
				$server['status'] = gettext("Unreach/Pending");
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
				$gps_lat_min = substr($gps_vars[3], 2) / 60.0;
				$gps_lon_deg = substr($gps_vars[5], 0, 3);
				$gps_lon_min = substr($gps_vars[5], 3) / 60.0;
				$gps_lat = $gps_lat_deg + $gps_lat_min;
				$gps_lat = $gps_lat * (($gps_vars[4] == "N") ? 1 : -1);
				$gps_lon = $gps_lon_deg + $gps_lon_min;
				$gps_lon = $gps_lon * (($gps_vars[6] == "E") ? 1 : -1);
			} elseif (substr($tmp, 0, 6) == '$GPGGA') {
				$gps_vars = explode(",", $tmp);
				$gps_ok = $gps_vars[6];
				$gps_lat_deg = substr($gps_vars[2], 0, 2);
				$gps_lat_min = substr($gps_vars[2], 2) / 60.0;
				$gps_lon_deg = substr($gps_vars[4], 0, 3);
				$gps_lon_min = substr($gps_vars[4], 3) / 60.0;
				$gps_lat = $gps_lat_deg + $gps_lat_min;
				$gps_lat = $gps_lat * (($gps_vars[3] == "N") ? 1 : -1);
				$gps_lon = $gps_lon_deg + $gps_lon_min;
				$gps_lon = $gps_lon * (($gps_vars[5] == "E") ? 1 : -1);
				$gps_alt = $gps_vars[9];
				$gps_alt_unit = $gps_vars[10];
				$gps_sat = $gps_vars[7];
			} elseif (substr($tmp, 0, 6) == '$GPGLL') {
				$gps_vars = explode(",", $tmp);
				$gps_ok = ($gps_vars[6] == "A");
				$gps_lat_deg = substr($gps_vars[1], 0, 2);
				$gps_lat_min = substr($gps_vars[1], 2) / 60.0;
				$gps_lon_deg = substr($gps_vars[3], 0, 3);
				$gps_lon_min = substr($gps_vars[3], 3) / 60.0;
				$gps_lat = $gps_lat_deg + $gps_lat_min;
				$gps_lat = $gps_lat * (($gps_vars[2] == "N") ? 1 : -1);
				$gps_lon = $gps_lon_deg + $gps_lon_min;
				$gps_lon = $gps_lon * (($gps_vars[4] == "E") ? 1 : -1);
			}
		}
	}
}

if (isset($config['ntpd']['gps']['type']) && ($config['ntpd']['gps']['type'] == 'SureGPS') && (isset($gps_ok))) {
	//GSV message is only enabled by init commands in services_ntpd_gps.php for SureGPS board
	$gpsport = fopen("/dev/gps0", "r+");
	while ($gpsport) {
		$buffer = fgets($gpsport);
		if (substr($buffer, 0, 6) == '$GPGSV') {
			//echo $buffer."\n";
			$gpgsv = explode(',', $buffer);
			$gps_satview = $gpgsv[3];
			break;
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
	global $config, $ntpq_servers;

	if (isset($config['ntpd']['noquery'])):

		print("<tr>\n");
		print('<td class="warning" colspan="11">');
		printf(gettext("Statistics unavailable because ntpq and ntpdc queries are disabled in the %sNTP service settings%s"), '<a href="services_ntpd.php">', '</a>');
		print("</td>\n");
		print("</tr>\n");
	elseif (count($ntpq_servers) == 0):
		print("<tr>\n");
		print('<td class="warning" colspan="11">');
		printf(gettext("No peers found, %sis the ntp service running?%s"), '<a href="status_services.php">', '</a>');
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
			print("</tr>\n");
			$i++;
		endforeach;
	endif;
}

function print_gps() {
	global 	$gps_lat, $gps_lon, $gps_lat_deg, $gps_lon_deg, $gps_lat_min, $gps_lon_min, $gps_vars,
			$gps_alt, $gps_alt_unit, $gps_sat, $gps_satview, $gps_goo_lnk;

	print("<tr>\n");
	print("<td>\n");
	printf("%.5f", $gps_lat);
	print(" (");
	printf("%d%s", $gps_lat_deg, "&deg;");
	printf("%.5f", $gps_lat_min*60);
	print($gps_vars[4]);
	print(")");
	print("</td>\n");
	print("<td>\n");
	printf("%.5f", $gps_lon);
	print(" (");
	printf("%d%s", $gps_lon_deg, "&deg;");
	printf("%.5f", $gps_lon_min*60);
	print($gps_vars[6]);
	print(")");
	print("</td>\n");

	if (isset($gps_alt)) {
		print("<td>\n");
		print($gps_alt . ' ' . $gps_alt_unit);
		print("</td>\n");
	}

	if (isset($gps_sat) || isset($gps_satview)) {
		print('<td class="text-center">');

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
	print('<td colspan="' . $gps_goo_lnk . '"><a target="_gmaps" href="http://maps.google.com/?q=' . $gps_lat . ',' . $gps_lon . '">' . gettext("Google Maps Link") . '</a></td>');
	print("</tr>\n");
}

$pgtitle = array(gettext("Status"), gettext("NTP"));
$shortcut_section = "ntp";

include("head.inc");
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Network Time Protocol Status");?></h2></div>
	<div class="panel-body">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><?=gettext("Status")?></th>
					<th><?=gettext("Server")?></th>
					<th><?=gettext("Ref ID")?></th>
					<th><?=gettext("Stratum")?></th>
					<th><?=gettext("Type")?></th>
					<th><?=gettext("When")?></th>
					<th><?=gettext("Poll")?></th>
					<th><?=gettext("Reach")?></th>
					<th><?=gettext("Delay")?></th>
					<th><?=gettext("Offset")?></th>
					<th><?=gettext("Jitter")?></th>
				</tr>
			</thead>
			<tbody id="ntpbody">
				<?=print_status()?>
			</tbody>
		</table>
	</div>
</div>


<?php

$showgps = 0;

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
