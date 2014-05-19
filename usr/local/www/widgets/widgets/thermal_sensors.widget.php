<?php
/*
	$Id: thermal_sensors.widget.php
	Description: Thermal Sensors Widget.
		NOTE: depends on proper cofing in System >> Advanced >> Miscellaneous tab >> Thermal Sensors section.

	File location:
		\usr\local\www\widgets\widgets\
	Depends on:
		\usr\local\www\widgets\javascript\thermal_sensors.js
		\usr\local\www\widgets\include\thermal_sensors.inc

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
require_once("/usr/local/www/widgets/include/thermal_sensors.inc");

//=========================================================================
//called by showThermalSensorsData() (jQuery Ajax call) in thermal_sensors.js
if (isset($_GET["getThermalSensorsData"])) {
    //get Thermal Sensors data and return
    echo getThermalSensorsData();
    return;
}
//=========================================================================


const WIDGETS_CONFIG_SECTION_KEY = "widgets";
const THERMAL_SENSORS_WIDGET_SUBSECTION_KEY = "thermal_sensors_widget";

//default constants
const DEFAULT_WARNING_THRESHOLD = 60; //60 C
const DEFAULT_CRITICAL_THRESHOLD = 70; //70 C
const MIN_THRESHOLD_VALUE = 1; //deg C
const MAX_THRESHOLD_VALUE = 100; //deg C

//NOTE: keys used in $_POST and $config should match text and checkbox inputs' IDs/names in HTML code section
//=========================================================================
//save widget config settings on POST
if ($_POST) {
	saveThresholdSettings($config, $_POST, "thermal_sensors_widget_zone_warning_threshold", "thermal_sensors_widget_zone_critical_threshold");
	saveThresholdSettings($config, $_POST, "thermal_sensors_widget_core_warning_threshold", "thermal_sensors_widget_core_critical_threshold");

	//handle checkboxes separately
	saveGraphDisplaySettings($config, $_POST, "thermal_sensors_widget_show_raw_output");
	saveGraphDisplaySettings($config, $_POST, "thermal_sensors_widget_show_full_sensor_name");
	saveGraphDisplaySettings($config, $_POST, "thermal_sensors_widget_pulsate_warning");
	saveGraphDisplaySettings($config, $_POST, "thermal_sensors_widget_pulsate_critical");

	//write settings to config file
	write_config("Saved thermal_sensors_widget settings via Dashboard.");
	header("Location: ../../index.php");
}

function saveThresholdSettings(&$configArray, &$postArray, $warningValueKey, $criticalValueKey) {
	$warningValue = 0;
	$criticalValue = 0;

	if (isset($postArray[$warningValueKey])) {
		$warningValue = (int) $postArray[$warningValueKey];
	}

	if (isset($postArray[$criticalValueKey])) {
		$criticalValue = (int) $postArray[$criticalValueKey];
	}

	if (
		($warningValue >= MIN_THRESHOLD_VALUE && $warningValue <= MAX_THRESHOLD_VALUE) &&
		($criticalValue >= MIN_THRESHOLD_VALUE && $criticalValue <= MAX_THRESHOLD_VALUE) &&
		($warningValue < $criticalValue)
	) {
		//all validated ok, save to config array
		$configArray[WIDGETS_CONFIG_SECTION_KEY][THERMAL_SENSORS_WIDGET_SUBSECTION_KEY][$warningValueKey] = $warningValue;
		$configArray[WIDGETS_CONFIG_SECTION_KEY][THERMAL_SENSORS_WIDGET_SUBSECTION_KEY][$criticalValueKey] = $criticalValue;
	}
}

function saveGraphDisplaySettings(&$configArray, &$postArray, $valueKey) {
    $configArray[WIDGETS_CONFIG_SECTION_KEY][THERMAL_SENSORS_WIDGET_SUBSECTION_KEY][$valueKey] = isset($postArray[$valueKey]) ? 1 : 0;
}

//=========================================================================
//get Threshold settings from config (apply defaults if missing)
$thermal_sensors_widget_zoneWarningTempThreshold = getThresholdValueFromConfig($config, "thermal_sensors_widget_zone_warning_threshold", DEFAULT_WARNING_THRESHOLD);
$thermal_sensors_widget_zoneCriticalTempThreshold = getThresholdValueFromConfig($config, "thermal_sensors_widget_zone_critical_threshold", DEFAULT_CRITICAL_THRESHOLD);
$thermal_sensors_widget_coreWarningTempThreshold = getThresholdValueFromConfig($config, "thermal_sensors_widget_core_warning_threshold", DEFAULT_WARNING_THRESHOLD);
$thermal_sensors_widget_coreCriticalTempThreshold = getThresholdValueFromConfig($config, "thermal_sensors_widget_core_critical_threshold", DEFAULT_CRITICAL_THRESHOLD);

//get display settings from config (apply defaults if missing)
$thermal_sensors_widget_showRawOutput = getBoolValueFromConfig($config, "thermal_sensors_widget_show_raw_output", false);
$thermal_sensors_widget_showFullSensorName = getBoolValueFromConfig($config, "thermal_sensors_widget_show_full_sensor_name", false);
$thermal_sensors_widget_pulsateWarning = getBoolValueFromConfig($config, "thermal_sensors_widget_pulsate_warning", true);
$thermal_sensors_widget_pulsateCritical = getBoolValueFromConfig($config, "thermal_sensors_widget_pulsate_critical", true);

function getThresholdValueFromConfig(&$configArray, $valueKey, $defaultValue) {

	$thresholdValue = $defaultValue;

	if (isset($configArray[WIDGETS_CONFIG_SECTION_KEY][THERMAL_SENSORS_WIDGET_SUBSECTION_KEY][$valueKey])) {
		$thresholdValue = (int) $configArray[WIDGETS_CONFIG_SECTION_KEY][THERMAL_SENSORS_WIDGET_SUBSECTION_KEY][$valueKey];
	}

	if ($thresholdValue < MIN_THRESHOLD_VALUE || $thresholdValue > MAX_THRESHOLD_VALUE) {
		//set to default if not in allowed range
		$thresholdValue = $defaultValue;
	}
	return $thresholdValue;
}

function getBoolValueFromConfig(&$configArray, $valueKey, $defaultValue) {

	$boolValue = false;

	if (isset($configArray[WIDGETS_CONFIG_SECTION_KEY][THERMAL_SENSORS_WIDGET_SUBSECTION_KEY][$valueKey])) {
		$boolValue = (bool) $configArray[WIDGETS_CONFIG_SECTION_KEY][THERMAL_SENSORS_WIDGET_SUBSECTION_KEY][$valueKey];
	} else {
		//set to default if not in allowed range
		$boolValue = $defaultValue;
	}
	return $boolValue;
}

//=========================================================================
?>

<script type="text/javascript">
//<![CDATA[
	//set Thresholds, to be used in thermal_sensors.js
	var thermal_sensors_widget_zoneWarningTempThreshold = <?= $thermal_sensors_widget_zoneWarningTempThreshold; ?>;
	var thermal_sensors_widget_zoneCriticalTempThreshold = <?= $thermal_sensors_widget_zoneCriticalTempThreshold; ?>;
	var thermal_sensors_widget_coreWarningTempThreshold = <?= $thermal_sensors_widget_coreWarningTempThreshold; ?>;
	var thermal_sensors_widget_coreCriticalTempThreshold = <?= $thermal_sensors_widget_coreCriticalTempThreshold; ?>;

	//set Graph display settings, to be used in thermal_sensors.js
	var thermal_sensors_widget_showRawOutput = <?= $thermal_sensors_widget_showRawOutput ? "true" : "false"; ?>;
	var thermal_sensors_widget_showFullSensorName = <?= $thermal_sensors_widget_showFullSensorName ? "true" : "false"; ?>;
	var thermal_sensors_widget_pulsateWarning = <?= $thermal_sensors_widget_pulsateWarning ? "true" : "false"; ?>;
	var thermal_sensors_widget_pulsateCritical = <?= $thermal_sensors_widget_pulsateCritical ? "true" : "false"; ?>;

	//start showing temp data
	//NOTE: the refresh interval will be reset to a proper value in showThermalSensorsData() (thermal_sensors.js).
	jQuery(document).ready(function() {
		showThermalSensorsData();
	});
//]]>
</script>

<input type="hidden" id="thermal_sensors-config" name="thermal_sensors-config" value="" />
<div id="thermal_sensors-settings" class="widgetconfigdiv" style="display:none;">
	<form action="/widgets/widgets/thermal_sensors.widget.php" method="post" id="iform_thermal_sensors_settings" name="iform_thermal_sensors_settings">
	<table width="100%" border="0" summary="thermal sensors widget">
		<tr>
		<td align="left" colspan="2">
			<span style="font-weight: bold" >Thresholds in &deg;C (1 to 100):</span>
		</td>
		<td align="right" colspan="1">
			<span style="font-weight: bold" >Display settings:</span>
		</td>
		</tr>
		<tr>
		<td align="right">
			Zone Warning:
		</td>
		<td>
			<input type="text" maxlength="3" size="3" class="formfld unknown"
			   name="thermal_sensors_widget_zone_warning_threshold"
			   id="thermal_sensors_widget_zone_warning_threshold"
			   value="<?= $thermal_sensors_widget_zoneWarningTempThreshold; ?>" />
		</td>
		<td align="right">
			<label for="thermal_sensors_widget_show_raw_output">Show raw output (no graph): </label>
			<input type="checkbox"
			   id="thermal_sensors_widget_show_raw_output"
			   name="thermal_sensors_widget_show_raw_output"
			   value="<?= $thermal_sensors_widget_showRawOutput; ?>" <?= ($thermal_sensors_widget_showRawOutput) ? " checked='checked'" : ""; ?> />
		</td>
		</tr>
		<tr>
		<td align="right">
			Zone Critical:
		</td>
		<td>
			<input type="text" maxlength="3" size="3" class="formfld unknown"
			   name="thermal_sensors_widget_zone_critical_threshold"
			   id="thermal_sensors_widget_zone_critical_threshold"
			   value="<?= $thermal_sensors_widget_zoneCriticalTempThreshold; ?>" />
		</td>
		<td align="right">
			<label for="thermal_sensors_widget_show_full_sensor_name">Show full sensor name: </label>
			<input type="checkbox"
			   id="thermal_sensors_widget_show_full_sensor_name"
			   name="thermal_sensors_widget_show_full_sensor_name"
			   value="<?= $thermal_sensors_widget_showFullSensorName; ?>" <?= ($thermal_sensors_widget_showFullSensorName) ? " checked='checked'" : ""; ?> />
		</td>
		</tr>
		<tr>
		<td align="right">
			Core Warning:
		</td>
		<td>
			<input type="text" maxlength="3" size="3" class="formfld unknown"
			   name="thermal_sensors_widget_core_warning_threshold"
			   id="thermal_sensors_widget_core_warning_threshold"
			   value="<?= $thermal_sensors_widget_coreWarningTempThreshold ?>" />
		</td>
		<td align="right">
			<label for="thermal_sensors_widget_pulsate_warning">Pulsate Warning: </label>
			<input type="checkbox"
			   id="thermal_sensors_widget_pulsate_warning"
			   name="thermal_sensors_widget_pulsate_warning"
			   value="<?= $thermal_sensors_widget_pulsateWarning; ?>" <?= ($thermal_sensors_widget_pulsateWarning) ? " checked='checked'" : ""; ?> />
		</td>
		</tr>
		<tr>
		<td align="right">
			Core Critical:
		</td>
		<td>
			<input type="text" maxlength="3" size="3" class="formfld unknown"
			   name="thermal_sensors_widget_core_critical_threshold"
			   id="thermal_sensors_widget_core_critical_threshold"
			   value="<?= $thermal_sensors_widget_coreCriticalTempThreshold ?>" />
		</td>
		<td align="right">
			<label for="thermal_sensors_widget_pulsate_critical">Pulsate Critical: </label>
			<input type="checkbox"
			   id="thermal_sensors_widget_pulsate_critical"
			   name="thermal_sensors_widget_pulsate_critical"
			   value="<?= $thermal_sensors_widget_pulsateCritical; ?>" <?= ($thermal_sensors_widget_pulsateCritical) ? " checked='checked'" : ""; ?> />
		</td>
		</tr>
		<tr>
		<td align="right" colspan="3">
			<input type="submit" id="thermal_sensors_widget_submit" name="thermal_sensors_widget_submit" class="formbtn" value="Save" />
		</td>
		</tr>
		<tr>
		<td align="left" colspan="3">
			<span>* You can configure a proper Thermal Sensor / Module under <br />
			&nbsp;&nbsp;&nbsp;<a href="system_advanced_misc.php">System &gt; Advanced &gt; Miscellaneous : Thermal Sensors section</a>.</span>
		</td>
		</tr>
	</table>
	</form>
</div>

<div style="padding: 5px">
	<div id="thermalSensorsContainer" class="listr">
		(Updating...)<br /><br />
	</div>
</div>

<!-- needed to display the widget settings menu -->
<script type="text/javascript">
//<![CDATA[
	textlink = jQuery("#thermal_sensors-configure");
	textlink.css({display: "inline"});
//]]>
</script>
