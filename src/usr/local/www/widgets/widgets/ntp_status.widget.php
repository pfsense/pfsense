<?php
/*
 * ntp_status.widget.php
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

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("/usr/local/www/widgets/include/ntp_status.inc");

// For this widget the update period is 6 x larger than most others. It typically defaults
// to once per 60 seconds, not once per 10 seconds
$widgetperiod = isset($config['widgets']['period']) ? $config['widgets']['period'] * 1000 * 6 : 60000;

if ($_REQUEST['updateme'] && (!is_array($config['ntpd']) ||
    !isset($config['ntpd']['enable']) || ($config['ntpd']['enable'] == 'enabled'))) {
//this block displays only on ajax refresh
	if (isset($config['system']['ipv6allow'])) {
		$inet_version = "";
	} else {
		$inet_version = " -4";
	}

	exec('/usr/local/sbin/ntpq -pnw ' . $inet_version . ' | /usr/bin/tail +3 | /usr/bin/awk -v RS= \'{gsub(/\n[[:space:]][[:space:]]+/," ")}1\'', $ntpq_output);
	$ntpq_counter = 0;
	$stratum_text = gettext("stratum");
	foreach ($ntpq_output as $line) {
		if (substr($line, 0, 1) == "*") {
			//Active NTP Peer
			$line = substr($line, 1);
			$peerinfo = preg_split("/[\s\t]+/", $line);
			if ($peerinfo[2] == "1") {
				$syncsource = $peerinfo[0] . " (" . $stratum_text . " " . $peerinfo[2] . ", " . $peerinfo[1] . ")";
			} else {
				$syncsource = $peerinfo[0] . " (" . $stratum_text . " " . $peerinfo[2] . ")";
			}
			$ntpq_counter++;
		} elseif (substr($line, 0, 1) == "o") {
			//Local PPS Peer
			$line = substr($line, 1);
			$peerinfo = preg_split("/[\s\t]+/", $line);
			$syncsource = $peerinfo[1] . " (" . $stratum_text . " " . $peerinfo[2] . ", PPS)";
			$ntpq_counter++;
		}
	}

	exec("/usr/local/sbin/ntpq -c clockvar $inet_version", $ntpq_clockvar_output);
	foreach ($ntpq_clockvar_output as $line) {
		if (substr($line, 0, 9) == "timecode=") {
			$tmp = explode('"', $line);
			$tmp = $tmp[1];
			if (substr($tmp, 0, 6) == '$GPRMC') {
				$gps_vars = explode(",", $tmp);
				$gps_ok	= ($gps_vars[2] == "A");
				$gps_lat_deg = substr($gps_vars[3], 0, 2);
				$gps_lat_min = substr($gps_vars[3], 2);
				$gps_lon_deg = substr($gps_vars[5], 0, 3);
				$gps_lon_min = substr($gps_vars[5], 3);
				$gps_lat = (float) $gps_lat_deg + $gps_lat_min / 60.0;
				$gps_lat = $gps_lat * (($gps_vars[4] == "N") ? 1 : -1);
				$gps_lon = (float) $gps_lon_deg + $gps_lon_min / 60.0;
				$gps_lon = $gps_lon * (($gps_vars[6] == "E") ? 1 : -1);
				$gps_lat_dir = $gps_vars[4];
				$gps_lon_dir = $gps_vars[6];
			} elseif (substr($tmp, 0, 6) == '$GPGGA') {
				$gps_vars = explode(",", $tmp);
				$gps_ok	= $gps_vars[6];
				$gps_lat_deg = substr($gps_vars[2], 0, 2);
				$gps_lat_min = substr($gps_vars[2], 2);
				$gps_lon_deg = substr($gps_vars[4], 0, 3);
				$gps_lon_min = substr($gps_vars[4], 3);
				$gps_lat = (float) $gps_lat_deg + $gps_lat_min / 60.0;
				$gps_lat = $gps_lat * (($gps_vars[3] == "N") ? 1 : -1);
				$gps_lon = (float) $gps_lon_deg + $gps_lon_min / 60.0;
				$gps_lon = $gps_lon * (($gps_vars[5] == "E") ? 1 : -1);
				$gps_alt = $gps_vars[9];
				$gps_alt_unit = $gps_vars[10];
				$gps_sat = (int)$gps_vars[7];
				$gps_lat_dir = $gps_vars[3];
				$gps_lon_dir = $gps_vars[5];
			} elseif (substr($tmp, 0, 6) == '$GPGLL') {
				$gps_vars = preg_split('/[,\*]+/', $tmp);
				$gps_ok	= ($gps_vars[6] == "A");
				$gps_lat_deg = substr($gps_vars[1], 0, 2);
				$gps_lat_min = substr($gps_vars[1], 2);
				$gps_lon_deg = substr($gps_vars[3], 0, 3);
				$gps_lon_min = substr($gps_vars[3], 3);
				$gps_lat = (float) $gps_lat_deg + $gps_lat_min / 60.0;
				$gps_lat = $gps_lat * (($gps_vars[2] == "N") ? 1 : -1);
				$gps_lon = (float) $gps_lon_deg + $gps_lon_min / 60.0;
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
				$gps_lat = (float) $gps_lat_deg + $gps_lat_min / 60.0;
				$gps_lat = $gps_lat * (($gps_vars[7] == "N") ? 1 : -1);
				$gps_lon = (float) $gps_lon_deg + $gps_lon_min / 60.0;
				$gps_lon = $gps_lon * (($gps_vars[9] == "E") ? 1 : -1);
				$gps_lat_dir = $gps_vars[7];
				$gps_lon_dir = $gps_vars[9];
			}
		}
	}

	if (isset($gps_ok) && isset($config['ntpd']['gps']['extstatus']) && ($config['ntpd']['gps']['nmeaset']['gpgsv'] || $config['ntpd']['gps']['nmeaset']['gpgga'])) {
		$lookfor['GPGSV'] = $config['ntpd']['gps']['nmeaset']['gpgsv'];
		$lookfor['GPGGA'] = !isset($gps_sat) && $config['ntpd']['gps']['nmeaset']['gpgga'];
		$gpsport = fopen('/dev/gps0', 'r+');
		while ($gpsport && ($lookfor['GPGSV'] || $lookfor['GPGGA'])) {
			$buffer = fgets($gpsport);
			if ($lookfor['GPGSV'] && substr($buffer, 0, 6) == '$GPGSV') {
				$gpgsv = explode(',', $buffer);
				$gps_satview = (int)$gpgsv[3];
				$lookfor['GPGSV'] = 0;
			} elseif ($lookfor['GPGGA'] && substr($buffer, 0, 6) == '$GPGGA') {
				$gpgga = explode(',', $buffer);
				$gps_sat = (int)$gpgga[7];
				$gps_alt = $gpgga[9];
				$gps_alt_unit = $gpgga[10];
				$lookfor['GPGGA'] = 0;
			}
		}
	}
?>

<table id="ntp_status_widget" class="table table-striped table-hover">
<?php if (!is_array($config['ntpd']) || !isset($config['ntpd']['enable']) || ($config['ntpd']['enable'] == 'enabled')): ?>
	<tr>
		<th><?=gettext('Server Time')?></th>
		<td id="ClockTime">
			<!-- will be replaced by javascript -->
			<span id="ntpStatusClock"></span>
		</td>
	</tr>
	<tr>
		<th><?=gettext('Sync Source')?></th>
		<td>
		<?php if ($ntpq_counter == 0): ?>
			<i><?=gettext('No active peers available')?></i>
		<?php else: ?>
			<?=$syncsource;?>
		<?php endif; ?>
		</td>
	</tr>
	<?php if (($gps_ok) && ($gps_lat) && ($gps_lon)): ?>
		<tr>
			<th><?=gettext('Clock location')?></th>
			<td>
				<a target="_gmaps" href="https://maps.google.com/?q=<?=$gps_lat;?>,<?=$gps_lon;?>">
				<?php
				echo sprintf("%.5f", $gps_lat) . " " . $gps_lat_dir . ", " . sprintf("%.5f", $gps_lon) . " " . $gps_lon_dir; ?>
				</a>
				<?php if (isset($gps_alt)) {echo " (" . $gps_alt . " " . $gps_alt_unit . " alt.)";} ?>
			</td>
		</tr>
		<?php if (isset($gps_sat) || isset($gps_satview)): ?>
			<tr>
				<th><?=gettext('Satellites')?></th>
				<td>
				<?php
				if (isset($gps_satview)) {echo gettext('in view') . ' ' . intval($gps_satview);}
				if (isset($gps_sat) && isset($gps_satview)) {echo ', ';}
				if (isset($gps_sat)) {echo gettext('in use') . ' ' . $gps_sat;}
				?>
				</td>
			</tr>
		<?php endif; ?>
	<?php endif; ?>
<?php else: ?>
	<tr>
		<td class="text-danger"><?=gettext('NTP Server is disabled')?></td>
	</tr>
<?php endif; ?>
</table>

<?php
	exit;
}
?>
<?php if ($widget_first_instance): ?>
<script type="text/javascript">
//<![CDATA[
// Have to convert the date to UTC time to match the PHP clock not the local client clock.
function convertDateToUTC(date,offset) {
	var hours_offset = offset/3600;
	var minute_offset = (offset % 3600)/60;
	var d = new Date(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate(), date.getUTCHours() + hours_offset, date.getUTCMinutes() + minute_offset, date.getUTCSeconds())
	return d;
}

var ntp_d = convertDateToUTC(new Date('<?=date_format(date_create(), 'c')?>'), '<?=date('Z')?>');
var tz = '<?=date('T');?>';
setInterval(function() {
	ntp_d.setSeconds(ntp_d.getSeconds() + 1);
	var thisSecond = ntp_d.getSeconds();
	var thisMinute = ntp_d.getMinutes();
	var thisHour = ntp_d.getHours();

	// Add leading zeros to minutes and seconds as required
	thisMinute = thisMinute < 10 ? "0" + thisMinute : thisMinute;
	thisSecond = thisSecond < 10 ? "0" + thisSecond : thisSecond;

	$('[id="ntpStatusClock"]').html(thisHour +':' + thisMinute + ':' + thisSecond + ' ' + tz);
}, 1000);
//]]>
</script>
<?php endif; ?>
<table id="ntpstatus" class="table table-striped table-hover">
	<tbody>
		<tr>
		<?php if (!is_array($config['ntpd']) || !isset($config['ntpd']['enable']) || ($config['ntpd']['enable'] == 'enabled')): ?>
			<td><?=gettext('Updating...')?></td>
		<?php else: ?>
			<td class="text-danger"><?=gettext('NTP Server is disabled')?></td>
		<?php endif; ?>
		</tr>
	</tbody>
</table>
<?php if ($widget_first_instance): ?>
<script type="text/javascript">
//<![CDATA[

events.push(function(){
	// --------------------- Centralized widget refresh system ------------------------------

	// Callback function called by refresh system when data is retrieved
	function ntp_callback(s) {
		$('[id="ntpstatus"]').prop('innerHTML', s);
	}

	// POST data to send via AJAX
	var postdata = {
		ajax: "ajax",
	 	updateme : "yes"
	 };

	// Create an object defining the widget refresh AJAX call
	var ntpObject = new Object();
	ntpObject.name = "NTP";
	ntpObject.url = "/widgets/widgets/ntp_status.widget.php";
	ntpObject.callback = ntp_callback;
	ntpObject.parms = postdata;
	ntpObject.freq = 4;

	// Register the AJAX object
	register_ajax(ntpObject);

	// ---------------------------------------------------------------------------------------------------
});

//]]>
</script>
<?php endif; ?>
