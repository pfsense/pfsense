<?php
/*
	ntp_status.widget.php
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP

	Copyright 2007 Scott Dale
	Part of pfSense widgets (https://www.pfsense.org)
	originally based on m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2004-2005 T. Lechat <dev@lechat.org>, Manuel Kasper <mk@neon1.net>
	and Jonathan Watt <jwatt@jwatt.org>.
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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");

require_once("/usr/local/www/widgets/include/ntp_status.inc");

if (isset($config['system']['ipv6allow']))
	$inet_version = "";
else
	$inet_version = " -4";

exec("/usr/local/sbin/ntpq -pn $inet_version | /usr/bin/tail +3", $ntpq_output);
$ntpq_counter = 0;
foreach ($ntpq_output as $line) {
	if (substr($line, 0, 1) == "*") {
		//Active NTP Peer
		$line = substr($line, 1);
		$peerinfo = preg_split("/[\s\t]+/", $line);
		if ($peerinfo[2] == "1") {
			$syncsource = $peerinfo[0] . " (stratum " . $peerinfo[2] . ", " . $peerinfo[1] . ")";
		} else {
			$syncsource = $peerinfo[0] . " (stratum " . $peerinfo[2] . ")";
		}
		$ntpq_counter++;
	} elseif (substr($line, 0, 1) == "o") {
		//Local PPS Peer
		$line = substr($line, 1);
		$peerinfo = preg_split("/[\s\t]+/", $line);
		$syncsource = $peerinfo[1] . " (stratum " . $peerinfo[2] . ", PPS)";
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
			$gps_ok  = ($gps_vars[2] == "A");
			$gps_lat_deg = substr($gps_vars[3], 0, 2);
			$gps_lat_min = substr($gps_vars[3], 2) / 60.0;
			$gps_lon_deg = substr($gps_vars[5], 0, 3);
			$gps_lon_min = substr($gps_vars[5], 3) / 60.0;
			$gps_lat = $gps_lat_deg + $gps_lat_min;
			$gps_lat = $gps_lat * (($gps_vars[4] == "N") ? 1 : -1);
			$gps_lon = $gps_lon_deg + $gps_lon_min;
			$gps_lon = $gps_lon * (($gps_vars[6] == "E") ? 1 : -1);
			$gps_la = $gps_vars[4];
			$gps_lo = $gps_vars[6];
		}elseif (substr($tmp, 0, 6) == '$GPGGA') {
			$gps_vars = explode(",", $tmp);
			$gps_ok  = $gps_vars[6];
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
			$gps_la = $gps_vars[3];
			$gps_lo = $gps_vars[5];
		}elseif (substr($tmp, 0, 6) == '$GPGLL') {
			$gps_vars = explode(",", $tmp);
			$gps_ok  = ($gps_vars[6] == "A");
			$gps_lat_deg = substr($gps_vars[1], 0, 2);
			$gps_lat_min = substr($gps_vars[1], 2) / 60.0;
			$gps_lon_deg = substr($gps_vars[3], 0, 3);
			$gps_lon_min = substr($gps_vars[3], 3) / 60.0;
			$gps_lat = $gps_lat_deg + $gps_lat_min;
			$gps_lat = $gps_lat * (($gps_vars[2] == "N") ? 1 : -1);
			$gps_lon = $gps_lon_deg + $gps_lon_min;
			$gps_lon = $gps_lon * (($gps_vars[4] == "E") ? 1 : -1);
			$gps_la = $gps_vars[2];
			$gps_lo = $gps_vars[4];
		}
	}
}

if (isset($config['ntpd']['gps']['type']) && ($config['ntpd']['gps']['type'] == 'SureGPS') && (isset($gps_ok))) {
	//GSV message is only enabled by init commands in services_ntpd_gps.php for SureGPS board
	$gpsport = fopen("/dev/gps0", "r+");
	while($gpsport){
		$buffer = fgets($gpsport);
		if(substr($buffer, 0, 6)=='$GPGSV'){
			//echo $buffer."\n";
			$gpgsv = explode(',',$buffer);
			$gps_satview = $gpgsv[3];
			break;
		}
	}
}
?>

<table class="table" id="ntp_status_widget">
	<tr>
		<th>Server Time</th>
		<td id="ntpStatusClock">
			<script>var ntpServerTime = new Date('<?=date_format(date_create(), 'c')?>');</script>
			<!-- display initial value before javascript takes over -->
			<?=gmdate('D j Y H:i:s \G\M\T O (T)');?>
		</td>
	</tr>
	<tr>
		<th>Sync Source</th>
		<td>
		<?php if ($ntpq_counter == 0): ?>
			<i>No active peers available</i>
		<?php else: ?>
			<?php echo $syncsource; ?>
		<?php endif; ?>
		</td>
	</tr>
	<?php if (($gps_ok) && ($gps_lat) && ($gps_lon)): ?>
		<tr>
			<th>Clock location</th>
			<td>
				<a target="_gmaps" href="http://maps.google.com/?q=<?php echo $gps_lat; ?>,<?php echo $gps_lon; ?>">
				<?php
				echo sprintf("%.5f", $gps_lat) . " " . $gps_la . ", " . sprintf("%.5f", $gps_lon) . " " . $gps_lo; ?>
				</a>
				<?php if (isset($gps_alt)) {echo " (" . $gps_alt . " " . $gps_alt_unit . " alt.)";} ?>
			</td>
		</tr>
		<?php if (isset($gps_sat) || isset($gps_satview)): ?>
			<tr>
				<th>Satellites</th>
				<td>
				<?php
				if (isset($gps_satview)) {echo 'in view ' . intval($gps_satview);}
				if (isset($gps_sat) && isset($gps_satview)) {echo ', ';}
				if (isset($gps_sat)) {echo 'in use ' . $gps_sat;}
				?>
				</td>
			</tr>
		<?php endif; ?>
	<?php endif; ?>
</table>