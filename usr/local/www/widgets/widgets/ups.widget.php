<?php
/*
	$Id$
	Copyright 2008 Seth Mos
	Part of pfSense widgets (www.pfsense.com)
	originally based on m0n0wall (http://m0n0.ch/wall)
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
	v. 2.01
*/
$nocsrf = true;
require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
if (isset($config['installedpackages']['nut']['config'][0])) {
	$nut_config = $config['installedpackages']['nut']['config'][0];
} else {
	// Draw dummy table with "Not installed" error message and return early
?>
	<table bgcolor="#990000" width="100%" border="0" cellspacing="0" cellpadding="0" summary="UPS status">
		<tr>
			<td class="widgetsubheader" align="center"><b>Monitoring</b></td>
			<td class="widgetsubheader" align="center"><b>Model</b></td>
			<td class="widgetsubheader" align="center"><b>Status</b></td>
		</tr>
		<tr>
			<td class="listlr" align="center" id="monitoring"></td>
			<td class="listr" align="center" id="model">No NUT installed!</td>
			<td class="listr" align="center" id="status">ERROR</td>
		</tr>
	</table>
<?php
	return;
} ?>
<table bgcolor="#990000" width="100%" border="0" cellspacing="0" cellpadding="0" summary="UPS status">
	<tr>
		<td class="widgetsubheader" align="center"><b>Monitoring</b></td>
		<td class="widgetsubheader" align="center"><b>Model</b></td>
		<td class="widgetsubheader" align="center"><b>Status</b></td>
	</tr>
	<tr>
		<td class="listlr" align="center" id="monitoring">
		<?php
			if ($nut_config['monitor'] == "local") {
				echo "Local UPS";
				$cmd = "upsc {$nut_config['name']}@localhost";
			} elseif ($nut_config['monitor'] == "remote") {
				echo "Remote UPS";
				$cmd = "upsc {$nut_config['remotename']}@{$nut_config['remoteaddr']}";
			} elseif ($nut_config['monitor'] == "snmp") {
				echo "SNMP UPS";
				$cmd = "upsc {$nut_config['snmpname']}@localhost";
			}
		?>
		</td>
		<td class="listr" align="center" id="model">
		<?php
			$running = ((int)exec("pgrep upsmon | wc -l") > 0) ? true : false;
			if ($running) {
				$handle = popen($cmd, 'r');
				if ($handle) {
					$read = fread($handle, 4096);
					pclose($handle);
					$lines = explode("\n", $read);
					if (count($lines) == 1) {
						$condition = "ERROR:Data stale!";
					} else {
						$ups = array();
						foreach ($lines as $line) {
							$line = explode(':', $line);
							$ups[$line[0]] = trim($line[1]);
						}
					}
				}
			} else {
				if ($nut_config['monitor'] == "snmp") {
					$condition = "NUT enabled but service not running!\nSNMP UPS may be unreachable.";
				} else {
					$condition = "NUT enabled but service not running!";
				}
			}
			if (isset($condition)) {
				echo $condition;
		?>
				</td><td class="listr" align="center" id="status">ERROR</td></tr></table>
				<!-- Close table if error and return. Table row - | "monitor type" | "error description" | "ERROR" | -->
		<?php
				return;
			} else {
				echo ($ups['ups.model'] != "") ? $ups['ups.model'] : "n/a";
			}
		?>
		</td>
		<td class="listr" align="center" id="status">
		<?php
			$status = explode(" ", $ups['ups.status']);
			foreach ($status as $condition) {
				if ($disp_status) {
					$disp_status .= ", ";
				}
				switch ($condition) {
					case "WAIT":
						$disp_status .= "Waiting";
						break;
					case "OFF":
						$disp_status .= "Off Line";
						break;
					case "OL":
						$disp_status .= "On Line";
						break;
					case "OB":
						$disp_status .= "On Battery";
						break;
					case "TRIM":
						$disp_status .= "SmartTrim";
						break;
					case "BOOST":
						$disp_status .= "SmartBoost";
						break;
					case "OVER":
						$disp_status .= "Overload";
						break;
					case "LB":
						$disp_status .= "Battery Low";
						break;
					case "RB":
						$disp_status .= "Replace Battery";
						break;
					case "CAL":
						$disp_status .= "Calibration";
						break;
					case "CHRG":
						$disp_status .= "Charging";
						break;
					default:
						$disp_status .= $condition;
						break;
				}
			}
			echo $disp_status;
			//  Battery Temp or Battery Voltage
			if ($ups['battery.voltage'] > 0) {
				$cell23 = array("Battery Voltage" , $ups['battery.voltage']."&nbsp;V");
			} elseif ($ups['ups.temperature'] > 0) {
				$cell23 = array("Battery Temp" , $ups['ups.temperature']."&#38;#176;C");
			} else {
				$cell23 = array("" , "");
			}
		?>
		</td>
	</tr>
	<tr>
		<td class="widgetsubheader" align="center">Battery Charge</td>
		<td class="widgetsubheader" align="center">Time Remain</td>
		<td class="widgetsubheader" align="center"><?php echo $cell23[0]; ?></td>
	</tr>
	<tr>
		<td class="listlr" align="center" id="charge">
			<img src="./themes/<?php echo $g['theme']; ?>/images/misc/bar_left.gif" height="15" width="4" border="0" align="middle" alt="left bar" /><img src="./themes/<?php echo $g['theme']; ?>/images/misc/bar_blue.gif" height="15" name="batwidtha" id="batwidtha" width="<?php echo round($ups['battery.charge']); ?>" border="0" align="middle" alt="red bar" /><img src="./themes/<?php echo $g['theme']; ?>/images/misc/bar_gray.gif" height="15" name="batwidthb" id="batwidthb" width="<?php echo (100 - $ups['battery.charge']); ?>" border="0" align="middle" alt="gray bar" /><img src="./themes/<?php echo $g['theme']; ?>/images/misc/bar_right.gif" height="15" width="5" border="0" align="middle" alt="right bar" /><br/>
			<span id="batmeter"><?php echo $ups['battery.charge']."%"; ?></span>
		</td>
		<td class="listr" align="center" id="runtime">
		<?php
			$secs = $ups['battery.runtime'];
			if ($secs < 0 || $secs == "") {
				echo "n/a";
			} else {
				$m = (int)($secs / 60); 
				$h = (int)($m / 60) % 24; 
				$m = $m % 60;
				$s = $secs % 60;
				echo $h."h " . $m."m " . $s."s";
			}
		?>
		</td>
		<td class="listr" align="center" id="bvoltage"><?php echo $cell23[1]; ?></td>
	</tr>
	<tr>
		<td class="widgetsubheader" align="center">Load</td>
		<td class="widgetsubheader" align="center">Input Voltage</td>
		<td class="widgetsubheader" align="center">Output Voltage</td>
	</tr>
	<tr>
		<td class="listlr" align="center" id="Load">
			<img src="./themes/<?php echo $g['theme']; ?>/images/misc/bar_left.gif" height="15" width="4" border="0" align="middle" alt="left bar" /><img src="./themes/<?php echo $g['theme']; ?>/images/misc/bar_blue.gif" height="15" name="loadwidtha" id="loadwidtha" width="<?php echo round($ups['ups.load']); ?>" border="0" align="middle" alt="red bar" /><img src="./themes/<?php echo $g['theme']; ?>/images/misc/bar_gray.gif" height="15" name="loadwidthb" id="loadwidthb" width="<?php echo (100 - $ups['ups.load']); ?>" border="0" align="middle" alt="gray bar" /><img src="./themes/<?php echo $g['theme']; ?>/images/misc/bar_right.gif" height="15" width="5" border="0" align="middle" alt="right bar" /><br/>
			<span id="loadmeter"><?php echo $ups['ups.load'].'%'; ?></span>
		</td>
		<td class="listr" align="center" id="InputV"><?php echo $ups['input.voltage']."&nbsp;V"; ?></td>
		<td class="listr" align="center" id="OutputV"><?php echo $ups['output.voltage']."&nbsp;V"; ?></td>
	</tr>
</table>
