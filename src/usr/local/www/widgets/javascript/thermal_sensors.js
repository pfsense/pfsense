/*
 * thermal_sensors.js
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

var warningTemp = 9999;
var criticalTemp = 100;
var widgetUnit = 'C';
ajaxBusy = false;

function buildThermalSensorsData(thermalSensorsData, widgetKey, tsParams, firstTime) {
	if (tsParams.showRawOutput) {
		buildThermalSensorsDataRaw(thermalSensorsData, widgetKey);
	} else {
		if (firstTime) {
			buildThermalSensorsDataGraph(thermalSensorsData, tsParams, widgetKey);
		}

		updateThermalSensorsDataGraph(thermalSensorsData, tsParams, widgetKey);
	}
}

function buildThermalSensorsDataRaw(thermalSensorsData, widgetKey) {

	var thermalSensorsContent = "";

	if (thermalSensorsData && thermalSensorsData != "") {
		thermalSensorsContent = thermalSensorsData.replace(/\|/g, "<br />");
		//rawData = thermalSensorsData.split("|").join("<br />");
	}

	loadThermalSensorsContainer(thermalSensorsContent, widgetKey);
}

function loadThermalSensorsContainer (thermalSensorsContent, widgetKey) {

	if (thermalSensorsContent && thermalSensorsContent != "") {
		//load generated graph (or raw data) into thermalSensorsContainer (thermalSensorsContainer DIV defined in "thermal_sensors.widget.php")
		$('#thermalSensorsContainer-' + widgetKey).html(thermalSensorsContent);
	} else {
		$('#thermalSensorsContainer-' + widgetKey).html("No Thermal Sensors data available.");
	}
}

function buildThermalSensorsDataGraph(thermalSensorsData, tsParams, widgetKey) {

	var thermalSensorsArray = new Array();

	if (thermalSensorsData && thermalSensorsData != "") {
		thermalSensorsArray = thermalSensorsData.split("|");
	}

	var thermalSensorsHTMLContent = "";

	//generate graph for each temperature sensor and append to thermalSensorsHTMLContent string
	for (var i = 0; i < thermalSensorsArray.length; i++) {

		var sensorDataArray = thermalSensorsArray[i].split(":");
		var sensorName = sensorDataArray[0].trim();
		var thermalSensorValue = getThermalSensorValue(sensorDataArray[1]);

		//set thresholds
		if (sensorName.indexOf("cpu") > -1) { //check CPU Threshold config settings
			warningTemp = tsParams.coreWarningTempThreshold;
			criticalTemp = tsParams.coreCriticalTempThreshold;
		} else { //assuming sensor is for a zone, check Zone Threshold config settings
			warningTemp = tsParams.zoneWarningTempThreshold;
			criticalTemp = tsParams.zoneCriticalTempThreshold;
		}

		if (!tsParams.showFullSensorName) {
			sensorName = getSensorFriendlyName(sensorName);
		}

		if (tsParams.showFahrenheit) {
			widgetUnit = 'F';
			thermalSensorValue = getFahrenheitValue(thermalSensorValue);
		}

		//build temperature item/row for a sensor

		var thermalSensorRow =	'<div class="progress">' +
						'<div id="temperaturebarL' + i + widgetKey + '" class="progress-bar progress-bar-success progress-bar-striped" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="1" style="width: 1%"></div>' +
						'<div id="temperaturebarM' + i + widgetKey + '" class="progress-bar progress-bar-warning progress-bar-striped" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="width: 0%"></div>' +
						'<div id="temperaturebarH' + i + widgetKey + '" class="progress-bar progress-bar-danger progress-bar-striped" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="width: 0%"></div>' +
					'</div>' +
					'<span><b>' + sensorName + ': </b></span>' + '<span id="temperaturemsg' + i + widgetKey + '">' + thermalSensorValue + '</span> &deg;' + widgetUnit;


		thermalSensorsHTMLContent = thermalSensorsHTMLContent + thermalSensorRow;

	}

	//load generated graph into thermalSensorsContainer (DIV defined in "thermal_sensors.widget.php")
	loadThermalSensorsContainer(thermalSensorsHTMLContent, widgetKey);


}

function updateThermalSensorsDataGraph(thermalSensorsData, tsParams, widgetKey) {
	var thermalSensorsArray = new Array();

	if (thermalSensorsData && thermalSensorsData != "") {
		thermalSensorsArray = thermalSensorsData.split("|");
	}

	//generate graph for each temperature sensor and append to thermalSensorsHTMLContent string
	for (var i = 0; i < thermalSensorsArray.length; i++) {

		var sensorDataArray = thermalSensorsArray[i].split(":");
		var sensorName = sensorDataArray[0].trim();
		var thermalSensorValue = getThermalSensorValue(sensorDataArray[1]);


		//set thresholds
		if (sensorName.indexOf("cpu") > -1) { //check CPU Threshold config settings
			warningTemp = tsParams.coreWarningTempThreshold;
			criticalTemp = tsParams.coreCriticalTempThreshold;
		} else { //assuming sensor is for a zone, check Zone Threshold config settings
			warningTemp = tsParams.zoneWarningTempThreshold;
			criticalTemp = tsParams.zoneCriticalTempThreshold;
		}

		if (!tsParams.showFullSensorName) {
			sensorName = getSensorFriendlyName(sensorName);
		}

		setTempProgress(i, thermalSensorValue, widgetKey);
	}
}

function getSensorFriendlyName(sensorFullName) {
	var rzone = /^hw\.acpi\.thermal\.tz([0-9]+)\.temperature$/;
	var rcore = /^dev\.cpu\.([0-9]+)\.temperature$/;

	if (rzone.test(sensorFullName)) {
		return "Zone " + rzone.exec(sensorFullName)[1];
	}

	if (rcore.test(sensorFullName)) {
		return "Core " + rcore.exec(sensorFullName)[1];
	}

	return sensorFullName;
}

function getThermalSensorValue(stringValue) {
	return (+parseFloat(stringValue) || 0).toFixed(1);
}

function getFahrenheitValue(cels) {
        return Math.ceil((cels * 1.8) + 32);
}

function getCelsiusValue(fahr) {
        return Math.floor((fahr - 32) / 1.8);
}

// Update the progress indicator
// transition = true allows the bar to move at default speed, false = instantaneous
function setTempProgress(bar, percent, widgetKey) {
	var barTempL, barTempM, barTempH;

	if (percent <= warningTemp) {
		barTempL = percent;
		barTempM = 0;
		barTempH = 0;
	} else if (percent <= criticalTemp) {
		barTempL = warningTemp;
		barTempM = percent - warningTemp;
		barTempH = 0;
	} else {
		barTempL = warningTemp;
		barTempM = criticalTemp - warningTemp;
		barTempH = percent - criticalTemp;
	}

	$('#' + 'temperaturebarL' + bar + widgetKey).css('width', barTempL + '%').attr('aria-valuenow', barTempL);
	$('#' + 'temperaturebarM' + bar + widgetKey).css('width', barTempM + '%').attr('aria-valuenow', barTempM);
	$('#' + 'temperaturebarH' + bar + widgetKey).css('width', barTempH + '%').attr('aria-valuenow', barTempH);

	$('#' + 'temperaturemsg' + bar + widgetKey).html(widgetUnit === 'F' ? getFahrenheitValue(percent) : percent);
}
