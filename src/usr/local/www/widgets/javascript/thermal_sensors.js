/*
	thermal_sensors.js
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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

		jQuery.ajax(url, {
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
		jQuery('#thermalSensorsContainer').html(thermalSensorsContent);
	} else {
		jQuery('#thermalSensorsContainer').html("No Thermal Sensors data available.");
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