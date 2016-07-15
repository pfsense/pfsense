<?php
/*
 * thermal_sensors.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
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
	write_config(gettext("Saved thermal_sensors_widget settings via Dashboard."));
	header("Location: ../../index.php");
}

function saveThresholdSettings(&$configArray, &$postArray, $warningValueKey, $criticalValueKey) {
	$warningValue = 0;
	$criticalValue = 0;

	if (isset($postArray[$warningValueKey]) && is_numeric($postArray[$warningValueKey])) {
		$warningValue = (int) $postArray[$warningValueKey];
	}

	if (isset($postArray[$criticalValueKey]) && is_numeric($postArray[$criticalValueKey])) {
		$criticalValue = (int) $postArray[$criticalValueKey];
	}

	if (($warningValue >= MIN_THRESHOLD_VALUE && $warningValue <= MAX_THRESHOLD_VALUE) &&
		($criticalValue >= MIN_THRESHOLD_VALUE && $criticalValue <= MAX_THRESHOLD_VALUE) &&
		($warningValue < $criticalValue)) {
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
	events.push(function(){
		showThermalSensorsData();
	});
//]]>
</script>
<div style="padding: 5px">
	<div id="thermalSensorsContainer" class="listr">
		<?=gettext('(Updating...)')?><br /><br />
	</div>
</div>
</div>
<input type="hidden" id="thermal_sensors-config" name="thermal_sensors-config" value="" />

<div id="widget-<?=$widgetname?>_panel-footer" class="widgetconfigdiv panel-footer collapse" >
	<form action="/widgets/widgets/thermal_sensors.widget.php" method="post" id="iform_thermal_sensors_settings" name="iform_thermal_sensors_settings">
	<table>
		<tr>
			<td class="text-left" colspan="2">
				<strong><?=gettext('Thresholds in')?> &deg;C <?=gettext('(1 to 100):')?></strong>
			</td>
			<td class="text-right" colspan="1">
				<strong><?=gettext('Display settings:')?></strong>
			</td>
		</tr>
		<tr>
			<td class="text-right">
				<?=gettext('Zone Warning:')?>
			</td>
			<td>
				<input type="text" maxlength="3" size="3" class="formfld unknown"
					name="thermal_sensors_widget_zone_warning_threshold"
					id="thermal_sensors_widget_zone_warning_threshold"
					value="<?= $thermal_sensors_widget_zoneWarningTempThreshold; ?>" />
			</td>
			<td class="text-right">
				<label for="thermal_sensors_widget_show_raw_output"><?=gettext('Show raw output (no graph):')?> </label>
				<input type="checkbox"
					id="thermal_sensors_widget_show_raw_output"
					name="thermal_sensors_widget_show_raw_output"
					value="<?= $thermal_sensors_widget_showRawOutput; ?>" <?= ($thermal_sensors_widget_showRawOutput) ? " checked" : ""; ?> />
			</td>
		</tr>
		<tr>
			<td class="text-right">
				<?=gettext('Zone Critical:')?>
			</td>
			<td>
				<input type="text" maxlength="3" size="3" class="formfld unknown"
					name="thermal_sensors_widget_zone_critical_threshold"
					id="thermal_sensors_widget_zone_critical_threshold"
					value="<?= $thermal_sensors_widget_zoneCriticalTempThreshold; ?>" />
			</td>
			<td class="text-right">
				<label for="thermal_sensors_widget_show_full_sensor_name"><?=gettext('Show full sensor name:')?> </label>
				<input type="checkbox"
					id="thermal_sensors_widget_show_full_sensor_name"
					name="thermal_sensors_widget_show_full_sensor_name"
					value="<?= $thermal_sensors_widget_showFullSensorName; ?>" <?= ($thermal_sensors_widget_showFullSensorName) ? " checked" : ""; ?> />
			</td>
		</tr>
		<tr>
			<td class="text-right">
				<?=gettext('Core Warning:')?>
			</td>
			<td>
				<input type="text" maxlength="3" size="3" class="formfld unknown"
					name="thermal_sensors_widget_core_warning_threshold"
					id="thermal_sensors_widget_core_warning_threshold"
					value="<?= $thermal_sensors_widget_coreWarningTempThreshold ?>" />
			</td>
			<td class="text-right">

			</td>
		</tr>
		<tr>
			<td class="text-right">
				<?=gettext('Core Critical:')?>
			</td>
			<td>
				<input type="text" maxlength="3" size="3" class="formfld unknown"
					name="thermal_sensors_widget_core_critical_threshold"
					id="thermal_sensors_widget_core_critical_threshold"
					value="<?= $thermal_sensors_widget_coreCriticalTempThreshold ?>" />
			</td>
			<td class="text-right">

			</td>
		</tr>
		<tr>
			<td class="text-right" colspan="3">
				<button type="submit" id="thermal_sensors_widget_submit" name="thermal_sensors_widget_submit" class="btn btn-primary btn-sm" value="Save">
					<i class="fa fa-save icon-embed-btn"></i>
					<?=gettext("Save")?>
				</button>
			</td>
		</tr>
		<tr>
			<td class="text-left" colspan="3">
				<span><?=gettext('* A proper Thermal Sensor / Module can be configured under')?> <br />
				&nbsp;&nbsp;&nbsp;<a href="system_advanced_misc.php"><?=gettext('System')?> &gt; <?=gettext('Advanced')?> &gt; <?=gettext('Miscellaneous')?> : <?=gettext('Thermal Sensors')?> <?=gettext('section')?></a>.</span>
			</td>
		</tr>
	</table>
</form>

