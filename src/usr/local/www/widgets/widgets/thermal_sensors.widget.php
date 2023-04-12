<?php
/*
 * thermal_sensors.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2023 Rubicon Communications, LLC (Netgate)
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
require_once("system.inc");


//=========================================================================
//called by showThermalSensorsData() (jQuery Ajax call) in thermal_sensors.js
if (isset($_REQUEST["getThermalSensorsData"])) {

	$specplatform = system_identify_specific_platform();
	if ($specplatform['name'] == '5100') {
		$_gb = exec("/sbin/sysctl -q dev.cpu | /usr/bin/grep temperature | /usr/bin/sort", $dfout);
	} else {
		$_gb = exec("/sbin/sysctl -q hw.acpi.thermal dev.cpu dev.t5nex dev.armada_thermal dev.cordbuc dev.pchtherm | /usr/bin/grep 'temperature:'", $dfout);
	}
	$dfout_filtered = array_filter($dfout, function($v) {
		return strpos($v, ' -') === false;
	});

	print(join("|", $dfout_filtered));
	exit;
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
	if (isset($_POST["thermal_sensors_widget_show_fahrenheit"])) {
		// convert back to celsius
		$_POST["thermal_sensors_widget_zone_warning_threshold"] = floor(($_POST["thermal_sensors_widget_zone_warning_threshold"] - 32) / 1.8);
		$_POST["thermal_sensors_widget_zone_critical_threshold"] = floor(($_POST["thermal_sensors_widget_zone_critical_threshold"] - 32) / 1.8);
		$_POST["thermal_sensors_widget_core_warning_threshold"] = floor(($_POST["thermal_sensors_widget_core_warning_threshold"] - 32) / 1.8);
		$_POST["thermal_sensors_widget_core_critical_threshold"] = floor(($_POST["thermal_sensors_widget_core_critical_threshold"] - 32) / 1.8);
		$_POST["thermal_sensors_widget_pch_warning_threshold"] = floor(($_POST["thermal_sensors_widget_pch_warning_threshold"] - 32) / 1.8);
		$_POST["thermal_sensors_widget_pch_critical_threshold"] = floor(($_POST["thermal_sensors_widget_pch_critical_threshold"] - 32) / 1.8);
	}

	set_customwidgettitle($user_settings);
	saveThresholdSettings($user_settings, $_POST, "thermal_sensors_widget_zone_warning_threshold", "thermal_sensors_widget_zone_critical_threshold");
	saveThresholdSettings($user_settings, $_POST, "thermal_sensors_widget_core_warning_threshold", "thermal_sensors_widget_core_critical_threshold");
	saveThresholdSettings($user_settings, $_POST, "thermal_sensors_widget_pch_warning_threshold", "thermal_sensors_widget_pch_critical_threshold");

	//handle checkboxes separately
	saveGraphDisplaySettings($user_settings, $_POST, "thermal_sensors_widget_show_raw_output");
	saveGraphDisplaySettings($user_settings, $_POST, "thermal_sensors_widget_show_full_sensor_name");
	saveGraphDisplaySettings($user_settings, $_POST, "thermal_sensors_widget_pulsate_warning");
	saveGraphDisplaySettings($user_settings, $_POST, "thermal_sensors_widget_pulsate_critical");
	saveGraphDisplaySettings($user_settings, $_POST, "thermal_sensors_widget_show_fahrenheit");

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
$thermal_sensors_widget_pchWarningTempThreshold = getThresholdValueFromConfig($user_settings, "thermal_sensors_widget_pch_warning_threshold", DEFAULT_WARNING_THRESHOLD, $widgetkey);
$thermal_sensors_widget_pchCriticalTempThreshold = getThresholdValueFromConfig($user_settings, "thermal_sensors_widget_pch_critical_threshold", DEFAULT_CRITICAL_THRESHOLD, $widgetkey);

//get display settings from config (apply defaults if missing)
$thermal_sensors_widget_showRawOutput = getBoolValueFromConfig($user_settings, "thermal_sensors_widget_show_raw_output", false, $widgetkey);
$thermal_sensors_widget_showFullSensorName = getBoolValueFromConfig($user_settings, "thermal_sensors_widget_show_full_sensor_name", false, $widgetkey);
$thermal_sensors_widget_pulsateWarning = getBoolValueFromConfig($user_settings, "thermal_sensors_widget_pulsate_warning", true, $widgetkey);
$thermal_sensors_widget_pulsateCritical = getBoolValueFromConfig($user_settings, "thermal_sensors_widget_pulsate_critical", true, $widgetkey);
$thermal_sensors_widget_showFahrenheit = getBoolValueFromConfig($user_settings, "thermal_sensors_widget_show_fahrenheit", false, $widgetkey);

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
			pchWarningTempThreshold:<?= $thermal_sensors_widget_pchWarningTempThreshold; ?>,
			pchCriticalTempThreshold:<?= $thermal_sensors_widget_pchCriticalTempThreshold; ?>,
			showRawOutput:<?= $thermal_sensors_widget_showRawOutput ? "true" : "false"; ?>,
			showFullSensorName:<?= $thermal_sensors_widget_showFullSensorName ? "true" : "false"; ?>,
			pulsateWarning:<?= $thermal_sensors_widget_pulsateWarning ? "true" : "false"; ?>,
			pulsateCritical:<?= $thermal_sensors_widget_pulsateCritical ? "true" : "false"; ?>,
			showFahrenheit:<?= $thermal_sensors_widget_showFahrenheit ? "true" : "false"; ?>

		};

		$("#thermal_sensors_widget_show_fahrenheit").on("change", function(e) {
			if (this.checked) {
				$(".thermal_sensors_widget_unit").html('<?=gettext("&deg;F")?>');
				$(".thermal_sensors_widget_range").html('<?=gettext("(1&ndash;212)")?>');
				$("#thermal_sensors_widget_zone_warning_threshold").val(function(){return getFahrenheitValue(this.value);});
				$("#thermal_sensors_widget_zone_critical_threshold").val(function(){return getFahrenheitValue(this.value);});
				$("#thermal_sensors_widget_core_warning_threshold").val(function(){return getFahrenheitValue(this.value);});
				$("#thermal_sensors_widget_core_critical_threshold").val(function(){return getFahrenheitValue(this.value);});
				$("#thermal_sensors_widget_pch_warning_threshold").val(function(){return getFahrenheitValue(this.value);});
				$("#thermal_sensors_widget_pch_critical_threshold").val(function(){return getFahrenheitValue(this.value);});
			} else {
				$(".thermal_sensors_widget_unit").html('<?=gettext("&deg;C")?>');
				$(".thermal_sensors_widget_range").html('<?=gettext("(1&ndash;100)")?>');
				$("#thermal_sensors_widget_zone_warning_threshold").val(function(){return getCelsiusValue(this.value);});
				$("#thermal_sensors_widget_zone_critical_threshold").val(function(){return getCelsiusValue(this.value);});
				$("#thermal_sensors_widget_core_warning_threshold").val(function(){return getCelsiusValue(this.value);});
				$("#thermal_sensors_widget_core_critical_threshold").val(function(){return getCelsiusValue(this.value);});
				$("#thermal_sensors_widget_pch_warning_threshold").val(function(){return getCelsiusValue(this.value);});
				$("#thermal_sensors_widget_pch_critical_threshold").val(function(){return getCelsiusValue(this.value);});
			}
		});
		// --------------------- Centralized widget refresh system ------------------------------

		// Callback function called by refresh system when data is retrieved
		function ts_callback(s) {
			var thermalSensorsData = s || "";
			buildThermalSensorsData(thermalSensorsData, "<?=htmlspecialchars($widgetkey)?>", tsParams, true);
			firstTime = false;
		}

		// POST data to send via AJAX
		var postdata = {
			ajax: "ajax",
			getThermalSensorsData : "1"
		 };

		// Create an object defining the widget refresh AJAX call
		var tsObject = new Object();
		tsObject.name = "thermal-sensors";
		tsObject.url = "/widgets/widgets/thermal_sensors.widget.php";
		tsObject.callback = ts_callback;
		tsObject.parms = postdata;
		tsObject.freq = 5;

		// Register the AJAX object
		register_ajax(tsObject);

		// ---------------------------------------------------------------------------------------------------
	});
//]]>
</script>
<?php
// update displayed values
if ($thermal_sensors_widget_showFahrenheit) {
	$thermal_sensors_widget_zoneWarningTempThreshold = ceil($thermal_sensors_widget_zoneWarningTempThreshold * 1.8 + 32);
	$thermal_sensors_widget_zoneCriticalTempThreshold = ceil($thermal_sensors_widget_zoneCriticalTempThreshold * 1.8 + 32);
	$thermal_sensors_widget_coreWarningTempThreshold = ceil($thermal_sensors_widget_coreWarningTempThreshold * 1.8 + 32);
	$thermal_sensors_widget_coreCriticalTempThreshold = ceil($thermal_sensors_widget_coreCriticalTempThreshold * 1.8 + 32);
	$thermal_sensors_widget_pchWarningTempThreshold = ceil($thermal_sensors_widget_pchWarningTempThreshold * 1.8 + 32);
	$thermal_sensors_widget_pchCriticalTempThreshold = ceil($thermal_sensors_widget_pchCriticalTempThreshold * 1.8 + 32);
}
?>
<div style="padding: 5px">
	<div id="thermalSensorsContainer-<?=htmlspecialchars($widgetkey)?>" class="listr">
		<?=gettext('(Updating...)')?><br /><br />
	</div>
</div>
</div>
<input type="hidden" id="thermal_sensors-config" name="thermal_sensors-config" value="" />

<div id="<?=$widget_panel_footer_id?>" class="widgetconfigdiv panel-footer collapse" >
	<form action="/widgets/widgets/thermal_sensors.widget.php" method="post" class="form-horizontal">
		<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>">
		<?=gen_customwidgettitle_div($widgetconfig['title']); ?>
		<div class="form-group">
			<label class="col-sm-6 control-label">
				<?=sprintf(gettext('Thresholds in <span class="thermal_sensors_widget_unit">%s</span><br/><span class="thermal_sensors_widget_range">%s</span>:'), $thermal_sensors_widget_showFahrenheit ? gettext("&deg;F") : gettext("&deg;C"), $thermal_sensors_widget_showFahrenheit ? gettext("(1&ndash;212)") : gettext("(1&ndash;100)"))?>
			</label>
		</div>

		<div class="form-group">
			<label class="col-sm-4 control-label"><?=gettext('Zone Warning')?></label>
			<div class="col-sm-4">
				<input type="text" name="thermal_sensors_widget_zone_warning_threshold" id="thermal_sensors_widget_zone_warning_threshold" value="<?= $thermal_sensors_widget_zoneWarningTempThreshold; ?>" class="form-control" />
			</div>
		</div>

		<div class="form-group">
			<label class="col-sm-4 control-label"><?=gettext('Zone Critical')?></label>
			<div class="col-sm-4">
				<input type="text" name="thermal_sensors_widget_zone_critical_threshold" id="thermal_sensors_widget_zone_critical_threshold" value="<?= $thermal_sensors_widget_zoneCriticalTempThreshold; ?>" class="form-control" />
			</div>
		</div>

		<div class="form-group">
			<label class="col-sm-4 control-label"><?=gettext('Core Warning')?></label>
			<div class="col-sm-4">
				<input type="text" name="thermal_sensors_widget_core_warning_threshold" id="thermal_sensors_widget_core_warning_threshold" value="<?= $thermal_sensors_widget_coreWarningTempThreshold; ?>" class="form-control" />
			</div>
		</div>

		<div class="form-group">
			<label class="col-sm-4 control-label"><?=gettext('Core Critical')?></label>
			<div class="col-sm-4">
				<input type="text" name="thermal_sensors_widget_core_critical_threshold" id="thermal_sensors_widget_core_critical_threshold" value="<?= $thermal_sensors_widget_coreCriticalTempThreshold; ?>" class="form-control" />
			</div>
		</div>

		<div class="form-group">
			<label class="col-sm-4 control-label"><?=gettext('PCH Warning')?></label>
			<div class="col-sm-4">
				<input type="text" name="thermal_sensors_widget_pch_warning_threshold" id="thermal_sensors_widget_pch_warning_threshold" value="<?= $thermal_sensors_widget_pchWarningTempThreshold; ?>" class="form-control" />
			</div>
		</div>

		<div class="form-group">
			<label class="col-sm-4 control-label"><?=gettext('PCH Critical')?></label>
			<div class="col-sm-4">
				<input type="text" name="thermal_sensors_widget_pch_critical_threshold" id="thermal_sensors_widget_pch_critical_threshold" value="<?= $thermal_sensors_widget_pchCriticalTempThreshold; ?>" class="form-control" />
			</div>
		</div>

		<div class="form-group">
			<label class="col-sm-6 control-label"><?=gettext('Display settings:')?></label>
		</div>

		<div class="form-group">
			<label for="thermal_sensors_widget_show_raw_output" class="col-sm-4 control-label"><?=gettext('Show raw output')?></label>
			<div class="col-sm-6 checkbox">
				<label>
					<input type="checkbox" name="thermal_sensors_widget_show_raw_output" id="thermal_sensors_widget_show_raw_output" value="<?= $thermal_sensors_widget_showRawOutput; ?>" <?= ($thermal_sensors_widget_showRawOutput) ? " checked" : ""; ?>/>
					<?=gettext('(no graph)')?>
				</label>
			</div>
		</div>

		<div class="form-group">
			<label for="thermal_sensors_widget_show_full_sensor_name" class="col-sm-4 control-label"><?=gettext('Show full sensor name')?></label>
			<div class="col-sm-6 checkbox">
				<label>
					<input type="checkbox" name="thermal_sensors_widget_show_full_sensor_name" id="thermal_sensors_widget_show_full_sensor_name" value="<?= $thermal_sensors_widget_showFullSensorName; ?>" <?= ($thermal_sensors_widget_showFullSensorName) ? " checked" : ""; ?>/>
				</label>
			</div>
		</div>

		<div class="form-group">
			<label for="thermal_sensors_widget_show_fahrenheit" class="col-sm-4 control-label"><?=gettext('Show temperature in Fahrenheit')?></label>
			<div class="col-sm-6 checkbox">
				<label>
					<input type="checkbox" name="thermal_sensors_widget_show_fahrenheit" id="thermal_sensors_widget_show_fahrenheit" value="<?= $thermal_sensors_widget_showFahrenheit; ?>" <?= ($thermal_sensors_widget_showFahrenheit) ? " checked" : ""; ?>/>
				</label>
			</div>
		</div>

		<div class="form-group">
			<div class="col-sm-offset-4 col-sm-6">
				<button type="submit" class="btn btn-primary"><i class="fa fa-save icon-embed-btn"></i><?=gettext('Save')?></button>
			</div>
		</div>

		<div class="form-group">
			<span><?=gettext('* A proper Thermal Sensor / Module can be configured under')?> <br />
			&nbsp;&nbsp;&nbsp;<a href="system_advanced_misc.php"><?=gettext('System')?> &gt; <?=gettext('Advanced')?> &gt; <?=gettext('Miscellaneous')?> : <?=gettext('Thermal Sensors')?> <?=gettext('section')?></a>.</span>
		</div>
	</form>
