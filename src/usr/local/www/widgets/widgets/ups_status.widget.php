<?php
/*
	$Id: ups_status.widget.php

	Copyright (C) 2015 SunStroke <andrey.b.nikitin@gmail.com>

	File location:
		\usr\local\www\widgets\widgets\
	Depends on:
		\usr\local\www\widgets\javascript\ups_status.js
		\usr\local\www\widgets\include\ups_status.inc

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

require_once("guiconfig.inc");
require_once("/usr/local/www/widgets/include/ups_status.inc");

//=========================================================================
if (!isset($config['installedpackages']['nut']['config'][0])) {
	//return error (no nut installed)
	echo "ERROR: No NUT package installed";
	return;
} else {
	//called by showUPSData() (jQuery Ajax call) in ups_status.js
	if (isset($_GET["getUPSData"])) {
	    //get UPS data and return it in ajax response
	    echo getUPSData();
	    return;
	}
}

function getUPSData() {

	global $config;
	$data = "";
	$cmd = "";
	$nut_config = $config['installedpackages']['nut']['config'][0];

	if ($nut_config['monitor'] == "local") {
		// "Monitoring" field - upsdata_array[0]
		$data = gettext("Local UPS");
		$cmd = "upsc {$nut_config['name']}@localhost";
	} elseif ($nut_config['monitor'] == "remote") {
		// "Monitoring" field - upsdata_array[0]
		$data = gettext("Remote UPS");
		$cmd = "upsc {$nut_config['remotename']}@{$nut_config['remoteaddr']}";
	} elseif ($nut_config['monitor'] == "snmp") {
		// "Monitoring" field - upsdata_array[0]
		$data = gettext("SNMP UPS");
		$cmd = "upsc {$nut_config['snmpname']}@localhost";
	}

	if (is_process_running('upsmon')) {
		$handle = popen($cmd, 'r');
		if ($handle) {
			$read = fread($handle, 4096);
			pclose($handle);
			$lines = explode("\n", $read);
			if (count($lines) == 1) {
				$condition = gettext("Data stale!");
			} else {
				$ups = array();
				foreach ($lines as $line) {
					$line = explode(':', $line);
					$ups[$line[0]] = trim($line[1]);
				}
			}
		}
	} else {
		$condition = gettext("NUT enabled but service not running!");
		if ($nut_config['monitor'] == "snmp") {
			$condition .= gettext("\nSNMP UPS may be unreachable.");
		}
	}
	if (isset($condition)) {
		// Return error description
		return $condition;
	}
	// "Model" field - upsdata_array[1]
	$data .= ":" . (($ups['ups.model'] != "") ? $ups['ups.model'] : gettext("n/a"));
	// "Status" field - upsdata_array[2]
	$status = explode(" ", $ups['ups.status']);
	foreach($status as $condition) {
		if($disp_status) $disp_status .= ", ";
		switch ($condition) {
			case "WAIT":
				$disp_status .= gettext("Waiting");
				break;
			case "OFF":
				$disp_status .= gettext("Off Line");
				break;
			case "OL":
				$disp_status .= gettext("On Line");
				break;
			case "OB":
				$disp_status .= gettext("On Battery");
				break;
			case "TRIM":
				$disp_status .= gettext("SmartTrim");
				break;
			case "BOOST":
				$disp_status .= gettext("SmartBoost");
				break;
			case "OVER":
				$disp_status .= gettext("Overload");
				break;
			case "LB":
				$disp_status .= gettext("Battery Low");
				break;
			case "RB":
				$disp_status .= gettext("Replace Battery");
				break;
			case "CAL":
				$disp_status .= gettext("Calibration");
				break;
			case "CHRG":
				$disp_status .= gettext("Charging");
				break;
			default:
				$disp_status .= $condition;
				break;
		}
	}
	$data .= ":" . $disp_status;
	// "Battery Charge" bars and field - upsdata_array[3]
	$data .= ":" . $ups['battery.charge'] . "%";
	// "Time Remaning" field - upsdata_array[4]
	$secs = $ups['battery.runtime'];
	if ($secs < 0 || $secs == "") {
		$data .= ":" . gettext("n/a");
	} else {
		$m = (int)($secs / 60); 
		$h = (int)($m / 60) % 24; 
		$m = $m % 60;
		$s = $secs % 60;
		$data .= ":" . $h."h " . $m."m " . $s."s";
	}
	// "Battery Voltage or Battery Temp" field - upsdata_array[5]
	if($ups['battery.voltage'] > 0) {
		$data .= ":" . $ups['battery.voltage'] . "&nbsp;V";
	} elseif ($ups['ups.temperature'] > 0) {
		$data .= ":" . $ups['ups.temperature'] . "&#38;#176;C";
	} else {
		$data .= ":" . "";
	}
	// "Load" bars and field - upsdata_array[6]
	$data .= ":" . $ups['ups.load'] . "%";
	// "Input Voltage" field - upsdata_array[7]
	$data .= ":" . $ups['input.voltage'] . "&nbsp;V";
	// "Output Voltage" field - upsdata_array[8]
	$data .= ":" . $ups['output.voltage'] . "&nbsp;V";

	return $data;

}
//=========================================================================

?>

<script type="text/javascript">
//<![CDATA[
	//start showing ups data
	//NOTE: the refresh interval will be reset to a proper value in showUPSData() (ups_status.js).
	jQuery(document).ready(function() {
		showUPSData();
	});
//]]>
</script>

<div id="UPSWidgetContainer" class="listr">
	<table id="ups_widget" bgcolor="#990000" width="100%" border="0" cellspacing="0" cellpadding="0" summary="UPS status">
		<tr>
			<td class="widgetsubheader" align="center"><strong><?php echo gettext("Monitoring"); ?></strong></td>
			<td class="widgetsubheader" align="center"><strong><?php echo gettext("Model"); ?></strong></td>
			<td class="widgetsubheader" align="center"><strong><?php echo gettext("Status"); ?></strong></td>
		</tr>
		<tr>
			<td class="listlr" align="center" id="ups_monitoring"></td>
			<td class="listr" align="center" id="ups_model"></td>
			<td class="listr" align="center" id="ups__status"></td>
		</tr>
		<tr>
			<td class="widgetsubheader" align="center"><?php echo gettext("Battery Charge"); ?></td>
			<td class="widgetsubheader" align="center"><?php echo gettext("Time Remain"); ?></td>
			<td class="widgetsubheader" align="center" id="ups_celltitle_VT"></td>
		</tr>
		<tr>
			<td class="listlr" align="center" id="ups_charge">
				<div class="ui-progressbar ui-widget ui-widget-content ui-corner-all" role="progressbar"><div id="ups_batmeter_graph" class="ui-progressbar-value ui-widget-header ui-corner-left"></div></div>
				<span id="ups_batmeter"></span>
			</td>
			<td class="listr" align="center" id="ups_runtime"></td>
			<td class="listr" align="center" id="ups_bvoltage"></td>
		</tr>
		<tr>
			<td class="widgetsubheader" align="center"><?php echo gettext("Load"); ?></td>
			<td class="widgetsubheader" align="center"><?php echo gettext("Input Voltage"); ?></td>
			<td class="widgetsubheader" align="center"><?php echo gettext("Output Voltage"); ?></td>
		</tr>
		<tr>
			<td class="listlr" align="center" id="ups_load">
				<div class="ui-progressbar ui-widget ui-widget-content ui-corner-all" role="progressbar"><div id="ups_loadmeter_graph" class="ui-progressbar-value ui-widget-header ui-corner-left"></div></div>
				<span id="ups_loadmeter"></span>
			</td>
			<td class="listr" align="center" id="ups_inputv"></td>
			<td class="listr" align="center" id="ups_outputv"></td>
		</tr>
	</table>
	<span id="ups_error_description"></span>
</div>
