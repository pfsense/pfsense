/*
	$Id: thermal_sensors.js
	Description:	
		Javascript functions to get and show thermal sensors data in thermal_sensors.widget.php.
		NOTE: depends on proper cofing in System >> Advanced >> Miscellaneous tab >> Thermal Sensors section.
	File location: 
		\usr\local\www\widgets\javascript\
	Used by:
		\usr\local\www\widgets\widgets\thermal_sensors.widget.php

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



//should be called from "thermal_sensors.widget.php"
function showThermalSensorsData() {

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
		error: function(jqXHR, status, error){
			buildThermalSensorsDataRaw(
				"Error getting data from [thermal_sensors.widget.php] - |" + 
				"status: [" + (status || "") + "]|" + 
				"error: [" + (error || "") + "]");
		}
	});
	
	//call itself in 11 seconds
	window.setTimeout(showThermalSensorsData, 11000);
}

function buildThermalSensorsData(thermalSensorsData) {
	//NOTE: variable thermal_sensors_widget_showRawOutput is declared/set in "thermal_sensors.widget.php"
	if (thermal_sensors_widget_showRawOutput) {
		buildThermalSensorsDataRaw(thermalSensorsData);
	}
	else {
		buildThermalSensorsDataGraph(thermalSensorsData);
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
		jQuery('#thermalSensorsContainer').html("No Thermal Sensors data available.<br /><br />");
		jQuery('<div/>').html(
				"<span>* You can configure a proper Thermal Sensor / Module under <br />" + 
				"&nbsp;&nbsp;&nbsp;<a href='system_advanced_misc.php'>System &gt; Advanced &gt; Miscellaneous : Thermal Sensors section</a>.</span>"
				).appendTo('#thermalSensorsContainer');
	}
}

function buildThermalSensorsDataGraph(thermalSensorsData) {

	//local constants
	var normalColor = "LimeGreen";
	var normalColorShadowTop = "Lime";
	var normalColorShadowBottom = "Green";
	
	var warningColor = "Orange";
	var warningColorShadowBottom = "Chocolate";
	
	var criticalColor = "Red";
	var criticalColorShadowBottom = "DarkRed";
	
	//local variables
	var barBgColor = normalColor; //green/normal as default
	var barBgColorShadowTop = normalColorShadowTop; //green/normal as default
	var barBgColorShadowBottom = normalColorShadowBottom; //green/normal as default
		
	var thermalSensorsArray = new Array();
	
	if (thermalSensorsData && thermalSensorsData != ""){
		thermalSensorsArray = thermalSensorsData.split("|");
	}

	var thermalSensorsHTMLContent = "";
	var itemsToPulsate = new Array();
	
	//generate graph for each temperature sensor and append to thermalSensorsHTMLContent string
	for (var i = 0; i < thermalSensorsArray.length; i++) {
	
		var sensorDataArray = thermalSensorsArray[i].split(":");
		var sensorName = sensorDataArray[0].trim();
		var thermalSensorValue = getThermalSensorValue(sensorDataArray[1]);

		var pulsateTimes = 0;
		var pulsateDuration = 0;
		
		var warningTempThresholdPosition = 0;
		var criticalTempThresholdPosition = 0;
		
		//NOTE: the following variables are declared/set in "thermal_sensors.widget.php": 
		//		thermal_sensors_widget_coreWarningTempThreshold, thermal_sensors_widget_coreCriticalTempThreshold, 
		//		thermal_sensors_widget_zoneWarningTempThreshold, thermal_sensors_widget_zoneCriticalTempThreshold
		//		thermal_sensors_widget_pulsateWarning, thermal_sensors_widget_pulsateCritical
		
		//set graph color and pulsate parameters
		if (sensorName.indexOf("cpu") > -1) { //check CPU Threshold config settings
			
			warningTempThresholdPosition = thermal_sensors_widget_coreWarningTempThreshold;
			criticalTempThresholdPosition = thermal_sensors_widget_coreCriticalTempThreshold;
			
			if (thermalSensorValue < thermal_sensors_widget_coreWarningTempThreshold) {
				barBgColor = normalColor;
				barBgColorShadowTop = normalColorShadowTop;
				barBgColorShadowBottom = normalColorShadowBottom;
				pulsateTimes = 0;
				pulsateDuration = 0;
			} else if (thermalSensorValue >= thermal_sensors_widget_coreWarningTempThreshold && thermalSensorValue < thermal_sensors_widget_coreCriticalTempThreshold) {
				barBgColor = warningColor;
				barBgColorShadowTop = warningColor;
				barBgColorShadowBottom = warningColorShadowBottom;
				pulsateTimes = thermal_sensors_widget_pulsateWarning ? 4 : 0;
				pulsateDuration = thermal_sensors_widget_pulsateWarning ? 900 : 0;
			} else { // thermalSensorValue > thermal_sensors_widget_coreCriticalTempThreshold
				barBgColor = criticalColor;
				barBgColorShadowTop = criticalColor;
				barBgColorShadowBottom = criticalColorShadowBottom;
				pulsateTimes = thermal_sensors_widget_pulsateCritical ? 7 : 0;
				pulsateDuration = thermal_sensors_widget_pulsateCritical ? 900 : 0;
			}
		} else { //assuming sensor is for a zone, check Zone Threshold config settings
			
			warningTempThresholdPosition = thermal_sensors_widget_zoneWarningTempThreshold;
			criticalTempThresholdPosition = thermal_sensors_widget_zoneCriticalTempThreshold;

			if (thermalSensorValue < thermal_sensors_widget_zoneWarningTempThreshold) {
			
				barBgColor = normalColor;
				barBgColorShadowTop = normalColorShadowTop;
				barBgColorShadowBottom = normalColorShadowBottom;
				pulsateTimes = 0;
				pulsateDuration = 0;

			} else if (thermalSensorValue >= thermal_sensors_widget_zoneWarningTempThreshold 
						&& thermalSensorValue < thermal_sensors_widget_zoneCriticalTempThreshold) {
						
				barBgColor = warningColor;
				barBgColorShadowTop = warningColor;
				barBgColorShadowBottom = warningColorShadowBottom;
				pulsateTimes = thermal_sensors_widget_pulsateWarning ? 4 : 0;
				pulsateDuration = thermal_sensors_widget_pulsateWarning ? 900 : 0;

			} else { // thermalSensorValue > thermal_sensors_widget_zoneCriticalTempThreshold
			
				barBgColor = criticalColor;
				barBgColorShadowTop = criticalColor;
				barBgColorShadowBottom = criticalColorShadowBottom;
				pulsateTimes = thermal_sensors_widget_pulsateCritical ? 7 : 0;
				pulsateDuration = thermal_sensors_widget_pulsateCritical ? 900 : 0;
			}
		}

		//NOTE: variable thermal_sensors_widget_showFullSensorName is declared/set in "thermal_sensors.widget.php"
		if (!thermal_sensors_widget_showFullSensorName) {
			sensorName = getSensorFriendlyName(sensorName);
		}

		//build temperature item/row for a sensor
		//NOTE: additional styles are set in 'thermal_sensors.widget.php'
		var thermalSensorRow = 	"<div class='thermalSensorRow' id='thermalSensorRow" + i + "' >" + 
							//sensor name and temperature value
							"	<div class='thermalSensorTextShell'><div class='thermalSensorText' id='thermalSensorText" + i + "'>" + sensorName + ": </div><div class='thermalSensorValue' id='thermalSensorValue" + i + "'>" + thermalSensorValue + " &deg;C</div></div>" + 
							//temperature bar
							"	<div class='thermalSensorBarShell' id='thermalSensorBarShell" + i + "' >" + 
							"		<div class='thermalSensorBar' id='thermalSensorBar" + i + "' style='background-color: " + barBgColor + "; border-top-color: " + barBgColorShadowTop + "; border-bottom-color: " + barBgColorShadowBottom + "; width:" + thermalSensorValue + "%;' ></div>" + 
							//threshold targets (warning and critical)
							"		<div class='thermalSensorWarnThresh' id='thermalSensorWarnThresh" + i + "' style='left:" + warningTempThresholdPosition  + "%;' ></div>" + 
							"		<div class='thermalSensorCritThresh' id='thermalSensorCritThresh" + i + "' style='left:" + criticalTempThresholdPosition + "%;' ></div>" + 
							//temperature scale (max 100 C)
							"		<div class='thermal_sensors_widget_scale000'></div>" + 
							"		<div class='thermal_sensors_widget_scale010'></div>" + 
							"		<div class='thermal_sensors_widget_scale020'></div>" + 
							"		<div class='thermal_sensors_widget_scale030'></div>" + 
							"		<div class='thermal_sensors_widget_scale040'></div>" + 
							"		<div class='thermal_sensors_widget_scale050'></div>" + 
							"		<div class='thermal_sensors_widget_scale060'></div>" + 
							"		<div class='thermal_sensors_widget_scale070'></div>" + 
							"		<div class='thermal_sensors_widget_scale080'></div>" + 
							"		<div class='thermal_sensors_widget_scale090'></div>" + 
							"		<div class='thermal_sensors_widget_scale100'></div>" + 
							"		<div class='thermal_sensors_widget_mark100'>100&deg;</div>" + 
							"	</div>" + 
							"</div>";
						
		//collect parameters for warning/critical items we need to pulsate
		if (pulsateTimes > 0) {
			var params = i + "|" + barBgColor + "|" + pulsateTimes + "|" + pulsateDuration;
			itemsToPulsate.push(params);
		}
		
		//append HTML item
		thermalSensorsHTMLContent = thermalSensorsHTMLContent + thermalSensorRow;
	}
	
	//load generated graph into thermalSensorsContainer (DIV defined in "thermal_sensors.widget.php")
	loadThermalSensorsContainer(thermalSensorsHTMLContent);
	
	if (itemsToPulsate.length > 0) {
		//pulsate/flash warning/critical items we collected
		pulsateThermalSensorsItems(itemsToPulsate);
	}
}

function pulsateThermalSensorsItems(itemsToPulsate) {
	
	//pulsate/flash warning/critical items we collected
	for (var i = 0; i < itemsToPulsate.length; i++) {
	
		var pulsateParams = itemsToPulsate[i].split("|");
		var rowNum = parseInt(pulsateParams[0]);
		//var textColor = pulsateParams[1];
		var pulsateTimes = parseInt(pulsateParams[2]);
		var pulsateDuration = parseInt(pulsateParams[3]);

		//pulsate temp Value
		var divThermalSensorValue = jQuery("#thermalSensorValue" + rowNum); //get temp value by id
		divThermalSensorValue.effect("pulsate", { 
				 times: pulsateTimes
				,easing: 'linear' //'easeInExpo' 
		}, pulsateDuration);
		////set Temp Value color
		//divThermalSensorValue.css( { color: textColor } );		
		
		//pulsate temp Bar
		var divThermalSensorBar = jQuery("#thermalSensorBar" + rowNum); //get temp bar by id
		divThermalSensorBar.effect("pulsate", { 
				 times: pulsateTimes
				,easing: 'linear' //'easeInExpo' 
		}, pulsateDuration);
		
	}
}

function getSensorFriendlyName(sensorFullName){
	var rzone = /^hw\.acpi\.thermal\.tz([0-9]+)\.temperature$/;
	var rcore = /^dev\.cpu\.([0-9]+)\.temperature$/;

	if (rzone.test(sensorFullName))
		return "Zone " + rzone.exec(sensorFullName)[1];

	if (rcore.test(sensorFullName))
		return "Core " + rcore.exec(sensorFullName)[1];

	return sensorFullName;
}

function getThermalSensorValue(stringValue){
	return (+parseFloat(stringValue) || 0).toFixed(1);
}
