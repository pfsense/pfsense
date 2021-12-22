<?php
/*
 * status_wireless.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-diagnostics-wirelessstatus
##|*NAME=Status: Wireless
##|*DESCR=Allow access to the 'Status: Wireless' page.
##|*MATCH=status_wireless.php*
##|-PRIV

require_once("guiconfig.inc");

$pgtitle = array(gettext("Status"), gettext("Wireless"));
$shortcut_section = "wireless";

include("head.inc");

$if = $_REQUEST['if'];

$ciflist = get_configured_interface_with_descr();
if (empty($if)) {
	/* Find the first interface that is wireless */
	foreach ($ciflist as $interface => $ifdescr) {
		if (is_interface_wireless(get_real_interface($interface))) {
			$if = $interface;
			break;
		}
	}
}

$tab_array = array();

foreach ($ciflist as $interface => $ifdescr) {
	if (is_interface_wireless(get_real_interface($interface))) {
		$enabled = false;
		if ($if == $interface) {
			$enabled = true;
		}

		$tab_array[] = array(gettext("Status") . " ({$ifdescr})", $enabled, "status_wireless.php?if={$interface}");
	}
}

$rwlif = get_real_interface($if);

if ($_POST['rescanwifi'] != "") {
	mwexec_bg("/sbin/ifconfig {$rwlif} scan 2>&1");
	$savemsg = gettext("Rescan has been initiated in the background. Refresh this page in 10 seconds to see the results.");
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

display_top_tabs($tab_array);
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Nearby Access Points or Ad-Hoc Peers")?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th>SSID</th>
						<th>BSSID</th>
						<th>CHAN</th>
						<th>RATE</th>
						<th>RSSI</th>
						<th>INT</th>
						<th>CAPS</th>
					</tr>
				</thead>
				<tbody>
<?php
	exec("/sbin/ifconfig {$rwlif} list scan 2>&1", $states, $ret);
	/* Skip Header */
	array_shift($states);

	$counter = 0;
	foreach ($states as $state) {
		/* Split by Mac address for the SSID Field */
		$split = preg_split("/([0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f])/i", $state);
		preg_match("/([0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f])/i", $state, $bssid);
		$ssid = htmlspecialchars($split[0]);
		$bssid = $bssid[0];
		/* Split the rest by using spaces for this line using the 2nd part */
		$split = preg_split("/[ ]+/i", $split[1]);
		$channel = $split[1];
		$rate = $split[2];
		$rssi = $split[3];
		$int = $split[4];
		$caps = "$split[5] $split[6] $split[7] $split[8] $split[9] $split[10] $split[11] ";
?>
					<tr>
						<td>
							<?=$ssid?>
						</td>
						<td>
							<?=$bssid?>
						</td>
						<td>
							<?=$channel?>
						</td>
						<td>
							<?=$rate?>
						</td>
						<td>
							<?=$rssi?>
						</td>
						<td>
							<?=$int?>
						</td>
						<td>
							<?=$caps?>
						</td>
					</tr>
<?php
	} // e-o-foreach
?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Associated or Ad-Hoc Peers")?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th>ADDR</th>
						<th>AID</th>
						<th>CHAN</th>
						<th>RATE</th>
						<th>RSSI</th>
						<th>IDLE</th>
						<th>TXSEQ</th>
						<th>RXSEQ</th>
						<th>CAPS</th>
						<th>ERP</th>
					</tr>
				</thead>
				<tbody>

<?php
	$states = array();
	exec("/sbin/ifconfig {$rwlif} list sta 2>&1", $states, $ret);
	array_shift($states);

	$counter=0;

	foreach ($states as $state) {
		$split = preg_split("/[ ]+/i", $state);
?>
					<tr>
<?php
		/* Split the rest by using spaces for this line using the 2nd part */
		for ($idx=0; $idx<10; $idx++) {
?>
						<td>
							<?=$split[$idx]?>
						</td>
<?php
		}
?>
					</tr>
<?php
	}
?>
				</tbody>
			</table>
		</div>
	</div>
</div>


<form action="status_wireless.php" method="post">
	<nav class="action-buttons">
		<input type="hidden" name="if" id="if" value="<?=htmlspecialchars($if)?>" />
		<button type="submit" class="btn btn-success" name="rescanwifi" id="rescanwifi" value="Rescan">
			<i class="fa fa-refresh icon-embed-btn"></i>
			<?=gettext("Rescan")?>
		</button>
	</nav>
</form>
<div class="infoblock">
<?php
print_info_box(sprintf(gettext('%1$sFlags:%2$s A = authorized, E = Extended Rate (802.11g), P = Power saving mode.%3$s' .
			   '%1$sCapabilities:%2$s E = ESS (infrastructure mode), I = IBSS (ad-hoc mode), P = privacy (WEP/TKIP/AES), ' .
			   'S = Short preamble, s = Short slot time.'), '<b>', '</b>', '<br />'), 'info', false);
?>
</div>
<?php
include("foot.inc");
