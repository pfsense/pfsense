/*
 * thermal_sensors.js
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

warningTemp = 9999;
criticalTemp = 100;
ajaxBusy = false;

//should be called from "thermal_sensors.widget.php"
function showThermalSensorsData() {
	if(!ajaxBusy) {
		ajaxBusy = true;
		//get data from thermal_sensors.widget.php
		url = "/widgets/widgets/thermal_sensors.widget.php?getThermalSensorsData=1"
				//IE fix to disable cache when using http:// , just append timespan
				+ new Date().getTime();

		$.ajax(url, {
			type: 'get',
			success: function(data) {
				var thermalSensorsData = data || "";
				buildThermalSensorsData(thermalSensorsData);
			},
			error: function(jqXHR, status, error) {
				warningTemp = 9999;
				buildThermalSensorsDataRaw('<span class="alert-danger">Temperature data could not be read.</span>');
			}
		});

		ajaxBusy = false;
	}
	//call itself in 11 seconds
	window.setTimeout(showThermalSensorsData, 11000);
}

function buildThermalSensorsData(thermalSensorsData) {
	//NOTE: variable thermal_sensors_widget_showRawOutput is declared/set in "thermal_sensors.widget.php"
	if (thermal_sensors_widget_showRawOutput) {
		buildThermalSensorsDataRaw(thermalSensorsData);
	} else {
		if(warningTemp == 9999) {
			buildThermalSensorsDataGraph(thermalSensorsData);
		}

		updateThermalSensorsDataGraph(thermalSensorsData);
	}
}

function buildThermalSensorsDataRaw(thermalSensorsData) {

	var thermalSensorsContent = "";

	if (thermalSensorsData && thermalSensorsData != "") {
		thermalSensorsContent = thermalSensorsData.replace(/\|/g, "<br />");
		//rawData = thermalSensorsData.split("|").join("<br />");
	}

	loadThermalSensorsContainer(thermalSensorsContent);
}

function loadThermalSensorsContainer (thermalSensorsContent) {

	if (thermalSensorsContent && thermalSensorsContent != "") {
		//load generated graph (or raw data) into thermalSensorsContainer (thermalSensorsContainer DIV defined in "thermal_sensors.widget.php")
		$('#thermalSensorsContainer').html(thermalSensorsContent);
	} else {
		$('#thermalSensorsContainer').html("No Thermal Sensors data available.");
	}
}

function buildThermalSensorsDataGraph(thermalSensorsData) {

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
			warningTemp = thermal_sensors_widget_coreWarningTempThreshold;
			criticalTemp = thermal_sensors_widget_coreCriticalTempThreshold;
		} else { //assuming sensor is for a zone, check Zone Threshold config settings
			warningTemp = thermal_sensors_widget_zoneWarningTempThreshold;
			criticalTemp = thermal_sensors_widget_zoneCriticalTempThreshold;
		}

		//NOTE: variable thermal_sensors_widget_showFullSensorName is declared/set in "thermal_sensors.widget.php"
		if (!thermal_sensors_widget_showFullSensorName) {
			sensorName = getSensorFriendlyName(sensorName);
		}

		//build temperature item/row for a sensor

		var thermalSensorRow =	'<div class="progress">' +
									'<div id="temperaturebarL' + i + '" class="progress-bar progress-bar-success progress-bar-striped" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="1" style="width: 1%"></div>' +
									'<div id="temperaturebarM' + i + '" class="progress-bar progress-bar-warning progress-bar-striped" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="width: 0%"></div>' +
									'<div id="temperaturebarH' + i + '" class="progress-bar progress-bar-danger progress-bar-striped" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="width: 0%"></div>' +
								'</div>' +
								'<span><b>' + sensorName + ': </b></span>' + '<span id="temperaturemsg' + i + '">' + thermalSensorValue + ' &deg;C</span>';


		thermalSensorsHTMLContent = thermalSensorsHTMLContent + thermalSensorRow;

	}

	//load generated graph into thermalSensorsContainer (DIV defined in "thermal_sensors.widget.php")
	loadThermalSensorsContainer(thermalSensorsHTMLContent);


}

function updateThermalSensorsDataGraph(thermalSensorsData) {
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
			warningTemp = thermal_sensors_widget_coreWarningTempThreshold;
			criticalTemp = thermal_sensors_widget_coreCriticalTempThreshold;
		} else { //assuming sensor is for a zone, check Zone Threshold config settings
			warningTemp = thermal_sensors_widget_zoneWarningTempThreshold;
			criticalTemp = thermal_sensors_widget_zoneCriticalTempThreshold;
		}

		//NOTE: variable thermal_sensors_widget_showFullSensorName is declared/set in "thermal_sensors.widget.php"
		if (!thermal_sensors_widget_showFullSensorName) {
			sensorName = getSensorFriendlyName(sensorName);
		}

	setTempProgress(i, thermalSensorValue);
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

// Update the progress indicator
// transition = true allows the bar to move at default speed, false = instantaneous
function setTempProgress(bar, percent) {
	var barTempL, barTempM, barTempH;

	if(percent <= warningTemp) {
		barTempL = percent;
		barTempM = 0;
		barTempH = 0;
	} else if(percent <= criticalTemp) {
		barTempL = warningTemp;
		barTempM = percent - warningTemp;
		barTempH = 0;
	} else {
		barTempL = warningTemp;
		barTempM = criticalTemp - warningTemp;
		barTempH = percent - criticalTemp;
	}


	$('#' + 'temperaturebarL' + bar).css('width', barTempL + '%').attr('aria-valuenow', barTempL);
	$('#' + 'temperaturebarM' + bar).css('width', barTempM + '%').attr('aria-valuenow', barTempM);
	$('#' + 'temperaturebarH' + bar).css('width', barTempH + '%').attr('aria-valuenow', barTempH);

	$('#' + 'temperaturemsg' + bar).html(percent + ' &deg;C');
}
