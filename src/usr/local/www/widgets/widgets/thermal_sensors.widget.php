<?php
/*
 * thermal_sensors.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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

//default constants
const DEFAULT_WARNING_THRESHOLD = 60; //60 C
const DEFAULT_CRITICAL_THRESHOLD = 70; //70 C
const MIN_THRESHOLD_VALUE = 1; //deg C
const MAX_THRESHOLD_VALUE = 100; //deg C

if (!function_exists('saveThresholdSettings')) {
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
			$configArray[WIDGETS_CONFIG_SECTION_KEY][$postArray['widgetkey']][$warningValueKey] = $warningValue;
			$configArray[WIDGETS_CONFIG_SECTION_KEY][$postArray['widgetkey']][$criticalValueKey] = $criticalValue;
		}
	}
}

if (!function_exists('saveGraphDisplaySettings')) {
	function saveGraphDisplaySettings(&$configArray, &$postArray, $valueKey) {
		$configArray[WIDGETS_CONFIG_SECTION_KEY][$postArray['widgetkey']][$valueKey] = isset($postArray[$valueKey]) ? 1 : 0;
	}
}

if (!function_exists('getThresholdValueFromConfig')) {
	function getThresholdValueFromConfig(&$configArray, $valueKey, $defaultValue, $widgetKey) {

		$thresholdValue = $defaultValue;

		if (isset($configArray[WIDGETS_CONFIG_SECTION_KEY][$widgetKey][$valueKey])) {
			$thresholdValue = (int) $configArray[WIDGETS_CONFIG_SECTION_KEY][$widgetKey][$valueKey];
		}

		if ($thresholdValue < MIN_THRESHOLD_VALUE || $thresholdValue > MAX_THRESHOLD_VALUE) {
			//set to default if not in allowed range
			$thresholdValue = $defaultValue;
		}
		return $thresholdValue;
	}
}

if (!function_exists('getBoolValueFromConfig')) {
	function getBoolValueFromConfig(&$configArray, $valueKey, $defaultValue, $widgetKey) {

		$boolValue = false;

		if (isset($configArray[WIDGETS_CONFIG_SECTION_KEY][$widgetKey][$valueKey])) {
			$boolValue = (bool) $configArray[WIDGETS_CONFIG_SECTION_KEY][$widgetKey][$valueKey];
		} else {
			//set to default if not in allowed range
			$boolValue = $defaultValue;
		}
		return $boolValue;
	}
}

//NOTE: keys used in $_POST and $config and $user_settings should match text and checkbox inputs' IDs/names in HTML code section
//=========================================================================
//save widget config settings on POST
if ($_POST['widgetkey']) {
	saveThresholdSettings($user_settings, $_POST, "thermal_sensors_widget_zone_warning_threshold", "thermal_sensors_widget_zone_critical_threshold");
	saveThresholdSettings($user_settings, $_POST, "thermal_sensors_widget_core_warning_threshold", "thermal_sensors_widget_core_critical_threshold");

	//handle checkboxes separately
	saveGraphDisplaySettings($user_settings, $_POST, "thermal_sensors_widget_show_raw_output");
	saveGraphDisplaySettings($user_settings, $_POST, "thermal_sensors_widget_show_full_sensor_name");
	saveGraphDisplaySettings($user_settings, $_POST, "thermal_sensors_widget_pulsate_warning");
	saveGraphDisplaySettings($user_settings, $_POST, "thermal_sensors_widget_pulsate_critical");

	//write settings to config file
	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Saved thermal_sensors_widget settings via Dashboard."));
	header("Location: ../../index.php");
}

$widgetkey_nodash = str_replace("-", "", $widgetkey);

//=========================================================================
//get Threshold settings from config (apply defaults if missing)
$thermal_sensors_widget_zoneWarningTempThreshold = getThresholdValueFromConfig($user_settings, "thermal_sensors_widget_zone_warning_threshold", DEFAULT_WARNING_THRESHOLD, $widgetkey);
$thermal_sensors_widget_zoneCriticalTempThreshold = getThresholdValueFromConfig($user_settings, "thermal_sensors_widget_zone_critical_threshold", DEFAULT_CRITICAL_THRESHOLD, $widgetkey);
$thermal_sensors_widget_coreWarningTempThreshold = getThresholdValueFromConfig($user_settings, "thermal_sensors_widget_core_warning_threshold", DEFAULT_WARNING_THRESHOLD, $widgetkey);
$thermal_sensors_widget_coreCriticalTempThreshold = getThresholdValueFromConfig($user_settings, "thermal_sensors_widget_core_critical_threshold", DEFAULT_CRITICAL_THRESHOLD, $widgetkey);

//get display settings from config (apply defaults if missing)
$thermal_sensors_widget_showRawOutput = getBoolValueFromConfig($user_settings, "thermal_sensors_widget_show_raw_output", false, $widgetkey);
$thermal_sensors_widget_showFullSensorName = getBoolValueFromConfig($user_settings, "thermal_sensors_widget_show_full_sensor_name", false, $widgetkey);
$thermal_sensors_widget_pulsateWarning = getBoolValueFromConfig($user_settings, "thermal_sensors_widget_pulsate_warning", true, $widgetkey);
$thermal_sensors_widget_pulsateCritical = getBoolValueFromConfig($user_settings, "thermal_sensors_widget_pulsate_critical", true, $widgetkey);

//=========================================================================
?>

<script type="text/javascript">
//<![CDATA[
	//start showing temp data
	//NOTE: the refresh interval will be reset to a proper value in showThermalSensorsData() (thermal_sensors.js).
	events.push(function(){
		var tsParams = {
			zoneWarningTempThreshold:<?= $thermal_sensors_widget_zoneWarningTempThreshold; ?>,
			zoneCriticalTempThreshold:<?= $thermal_sensors_widget_zoneCriticalTempThreshold; ?>,
			coreWarningTempThreshold:<?= $thermal_sensors_widget_coreWarningTempThreshold; ?>,
			coreCriticalTempThreshold:<?= $thermal_sensors_widget_coreCriticalTempThreshold; ?>,
			showRawOutput:<?= $thermal_sensors_widget_showRawOutput ? "true" : "false"; ?>,
			showFullSensorName:<?= $thermal_sensors_widget_showFullSensorName ? "true" : "false"; ?>,
			pulsateWarning:<?= $thermal_sensors_widget_pulsateWarning ? "true" : "false"; ?>,
			pulsateCritical:<?= $thermal_sensors_widget_pulsateCritical ? "true" : "false"; ?>
		};
		// showThermalSensorsData("<?=$widgetkey?>", true);
		setTimeout(function(){showThermalSensorsData("<?=$widgetkey?>", tsParams, true);}, Math.floor((Math.random() * 10000) + 1000));
	});
//]]>
</script>
<div style="padding: 5px">
	<div id="thermalSensorsContainer-<?=$widgetkey?>" class="listr">
		<?=gettext('(Updating...)')?><br /><br />
	</div>
</div>
</div>
<input type="hidden" id="thermal_sensors-config" name="thermal_sensors-config" value="" />

<div id="<?=$widget_panel_footer_id?>" class="widgetconfigdiv panel-footer collapse" >
	<form action="/widgets/widgets/thermal_sensors.widget.php" method="post" id="iform_thermal_sensors_settings" name="iform_thermal_sensors_settings">
	<input type="hidden" name="widgetkey" value="<?=$widgetkey; ?>">
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

