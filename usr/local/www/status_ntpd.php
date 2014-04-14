<?php
/* $Id$ */
/*
	status_ntpd.php
	part of pfSense (https://www.pfsense.org/)

	Copyright (C) 2013 Dagorlad
	Copyright (C) 2012 Jim Pingle
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
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/ntpd	/usr/local/sbin/ntpq
	pfSense_MODULE:	ntpd
*/

##|+PRIV
##|*IDENT=page-status-ntp
##|*NAME=Status: NTP page
##|*DESCR=Allow access to the 'Status: NTP' page.
##|*MATCH=status_ntpd.php*
##|-PRIV

require_once("guiconfig.inc");

if(!isset($config['ntpd']['noquery'])) {

	exec("/usr/local/sbin/ntpq -pn | /usr/bin/tail +3", $ntpq_output);

	$ntpq_servers = array();
	foreach ($ntpq_output as $line) {
		$server = array();

		switch (substr($line, 0, 1)) {
			case " ":
				$server['status'] = "Unreach/Pending";
				break;
			case "*":
				$server['status'] = "Active Peer";
				break;
			case "+":
				$server['status'] = "Candidate";
				break;
			case "o":
				$server['status'] = "PPS Peer";
				break;
			case "#":
				$server['status'] = "Selected";
				break;
			case ".":
				$server['status'] = "Excess Peer";
				break;
			case "x":
				$server['status'] = "False Ticker";
				break;
			case "-":
				$server['status'] = "Outlier";
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

	exec("/usr/local/sbin/ntpq -c clockvar", $ntpq_clockvar_output);
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
			}
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

$pgtitle = array(gettext("Status"),gettext("NTP"));
$shortcut_section = "ntp";
include("head.inc");
?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td><div id="mainarea">
	<table class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr><td class="listtopic">Network Time Protocol Status</td></tr>
	</table>
	<table class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
	<thead>
	<tr>
		<th class="listhdrr"><?=gettext("Status"); ?></th>
		<th class="listhdrr"><?=gettext("Server"); ?></th>
		<th class="listhdrr"><?=gettext("Ref ID"); ?></th>
		<th class="listhdrr"><?=gettext("Stratum"); ?></th>
		<th class="listhdrr"><?=gettext("Type"); ?></th>
		<th class="listhdrr"><?=gettext("When"); ?></th>
		<th class="listhdrr"><?=gettext("Poll"); ?></th>
		<th class="listhdrr"><?=gettext("Reach"); ?></th>
		<th class="listhdrr"><?=gettext("Delay"); ?></th>
		<th class="listhdrr"><?=gettext("Offset"); ?></th>
		<th class="listhdr"><?=gettext("Jitter"); ?></th>
	</tr>
	</thead>
	<tbody>
	<?php if (isset($config['ntpd']['noquery'])): ?>
	<tr><td class="listlr" colspan="11" align="center">
		Statistics unavailable because ntpq and ntpdc queries are disabled in the <a href="services_ntpd.php">NTP service settings</a>.
	</td></tr>
	<?php elseif (count($ntpq_servers) == 0): ?>
	<tr><td class="listlr" colspan="11" align="center">
		No peers found, <a href="status_services.php">is the ntp service running?</a>.
	</td></tr>
	<?php else: ?>
	<?php $i = 0; foreach ($ntpq_servers as $server): ?>
	<tr>
	<td class="listlr" nowrap>
		<?=$server['status'];?>
	</td>
	<td class="listlr">
		<?=$server['server'];?>
	</td>
	<td class="listlr">
		<?=$server['refid'];?>
	</td>
	<td class="listlr">
		<?=$server['stratum'];?>
	</td>
	<td class="listlr">
		<?=$server['type'];?>
	</td>
	<td class="listlr">
		<?=$server['when'];?>
	</td>
	<td class="listlr">
		<?=$server['poll'];?>
	</td>
	<td class="listlr">
		<?=$server['reach'];?>
	</td>
	<td class="listlr">
		<?=$server['delay'];?>
	</td>
	<td class="listlr">
		<?=$server['offset'];?>
	</td>
	<td class="listlr">
		<?=$server['jitter'];?>
	</td>
	</tr>
<?php $i++; endforeach; endif; ?>
	</tbody>
	</table>
<?php if (($gps_ok) && ($gps_lat) && ($gps_lon)): ?>
	<?php $gps_goo_lnk = 2; ?>
	<table class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
	<thead>
	<tr>
		<th class="listhdrr"><?=gettext("Clock Latitude"); ?></th>
		<th class="listhdrr"><?=gettext("Clock Longitude"); ?></th>
		<?php if (isset($gps_alt)) { echo '<th class="listhdrr">'.gettext("Clock Altitude").'</th>'; $gps_goo_lnk++;}?>
		<?php if (isset($gps_sat) || isset($gps_satview)) { echo '<th class="listhdrr">'.gettext("Satellites").'</th>'; $gps_goo_lnk++;}?>
	</tr>
	</thead>
	<tbody>
		<tr>
			<td class="listlr" align="center"><?php echo sprintf("%.5f", $gps_lat); ?> (<?php echo sprintf("%d", $gps_lat_deg); ?>&deg; <?php echo sprintf("%.5f", $gps_lat_min*60); ?><?php echo $gps_vars[4]; ?>)</td>
			<td class="listlr" align="center"><?php echo sprintf("%.5f", $gps_lon); ?> (<?php echo sprintf("%d", $gps_lon_deg); ?>&deg; <?php echo sprintf("%.5f", $gps_lon_min*60); ?><?php echo $gps_vars[6]; ?>)</td>
			<?php if (isset($gps_alt)) { echo '<td class="listlr" align="center">'.$gps_alt.' '.$gps_alt_unit.'</td>';}?>
			<td class="listr" align="center">
			<?php 
			if (isset($gps_satview)) {echo 'in view ' . intval($gps_satview);}
			if (isset($gps_sat) && isset($gps_satview)) {echo ', ';}
			if (isset($gps_sat)) {echo 'in use ' . $gps_sat;} 
			?>
			</td>
		</tr>
		<tr>
			<td class="listlr" colspan="<?php echo $gps_goo_lnk; ?>" align="center"><a target="_gmaps" href="http://maps.google.com/?q=<?php echo $gps_lat; ?>,<?php echo $gps_lon; ?>">Google Maps Link</a></td>
		</tr>
	</tbody>
	</table>
<?php endif; ?>
</div></td></tr>
</table>
<?php include("fend.inc"); ?>
</body>
</html>
