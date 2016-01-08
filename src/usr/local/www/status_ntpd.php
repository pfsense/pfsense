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

$pgtitle = array(gettext("Status"), gettext("NTP"));
$shortcut_section = "ntp";

include("head.inc");
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title">Network Time Protocol Status</h2></div>
	<div class="panel-body">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><?=gettext("Status"); ?></th>
					<th><?=gettext("Server"); ?></th>
					<th><?=gettext("Ref ID"); ?></th>
					<th><?=gettext("Stratum"); ?></th>
					<th><?=gettext("Type"); ?></th>
					<th><?=gettext("When"); ?></th>
					<th><?=gettext("Poll"); ?></th>
					<th><?=gettext("Reach"); ?></th>
					<th><?=gettext("Delay"); ?></th>
					<th><?=gettext("Offset"); ?></th>
					<th><?=gettext("Jitter"); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if (isset($config['ntpd']['noquery'])): ?>
				<tr>
					<td class="warning" colspan="11">
						Statistics unavailable because ntpq and ntpdc queries are disabled in the <a href="services_ntpd.php">NTP service settings</a>.
					</td>
				</tr>
				<?php elseif (count($ntpq_servers) == 0): ?>
				<tr>
					<td class="warning" colspan="11">
						No peers found, <a href="status_services.php">is the ntp service running?</a>
					</td>
				</tr>
				<?php else:

					$i = 0;
					foreach ($ntpq_servers as $server): ?>
						<tr>
							<td><?=$server['status']?></td>
							<td><?=$server['server']?></td>
							<td><?=$server['refid']?></td>
							<td><?=$server['stratum']?></td>
							<td><?=$server['type']?></td>
							<td><?=$server['when']?></td>
							<td><?=$server['poll']?></td>
							<td><?=$server['reach']?></td>
							<td><?=$server['delay']?></td>
							<td><?=$server['offset']?></td>
							<td><?=$server['jitter']?></td>
						</tr> <?php
					   $i++;
				   endforeach;
			   endif;
?>
			</tbody>
		</table>
	</div>
</div>


<?php

// GPS satellite information (if available)
if (($gps_ok) && ($gps_lat) && ($gps_lon)):
	$gps_goo_lnk = 2; ?>

	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title">GPS information</h2></div>
		<div class="panel-body">
			<table class="table table-striped table-hover table-condensed">
				<thead>
				<tr>
					<th>
						 <?=gettext("Clock Latitude"); ?>
					</th>
					<th>
						<?=gettext("Clock Longitude"); ?>
					</th>
					<?php if (isset($gps_alt)) { ?>
						 <th>
							 <?=gettext("Clock Altitude")?>
						 </th>
						 <?php $gps_goo_lnk++;
					 }

					 if (isset($gps_sat) || isset($gps_satview)) { ?>
						<th>
							<?=gettext("Satellites")?>
						</th> <?php
						$gps_goo_lnk++;
					 }?>
					</tr>
				</thead>

				<tbody>
					<tr>
						<td>
							<?=printf("%.5f", $gps_lat); ?> (<?=printf("%d", $gps_lat_deg); ?>&deg; <?=printf("%.5f", $gps_lat_min*60); ?><?=$gps_vars[4]; ?>)
						</td>
						<td>
							<?=printf("%.5f", $gps_lon); ?> (<?=printf("%d", $gps_lon_deg); ?>&deg; <?=printf("%.5f", $gps_lon_min*60); ?><?=$gps_vars[6]; ?>)
						</td>

						<?php if (isset($gps_alt)) { ?>
							<td>
								<?=$gps_alt . ' ' . $gps_alt_unit?>
							</td>
							}

						if (isset($gps_sat) || isset($gps_satview)) { ?>
							<td class="text-center"> <?php
								if (isset($gps_satview)) {
									print('in view ' . intval($gps_satview));
								}

							if (isset($gps_sat) && isset($gps_satview)) {
								print(', ');
							}
							if (isset($gps_sat)) {
								print('in use ' . $gps_sat);
							} ?>
							</td> <?php
						}
						?>
					</tr>
					<tr>
						<td colspan="<?=$gps_goo_lnk; ?>"><a target="_gmaps" href="http://maps.google.com/?q=<?=$gps_lat; ?>,<?=$gps_lon; ?>">Google Maps Link</a></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

<?php	endif;

include("foot.inc"); ?>
