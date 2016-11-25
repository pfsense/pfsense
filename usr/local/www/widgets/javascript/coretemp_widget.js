/*
    $Id: coretemp_widget.js
  Descr:	
		js functions to get and show coretemp data in coretemp_widget.widget.php
		works with:
		32bit: http://files.pfsense.org/jimp/ko-8.1/i386/coretemp.ko
		(?) 64bit: http://files.pfsense.org/jimp/ko-8.1/amd64/coretemp.ko  (?)

	File location: 
		\usr\local\www\widgets\javascript\
	Used by:
		\usr\local\www\widgets\widgets\coretemp_widget.widget.php
	Depends on:
		\usr\local\www\widgets\include\coretemp_widget.php
		\usr\local\www\widgets\include\coretemp_widget.inc

	TODO: 	to convert to jQuery, search for the following TODOs in 3 places below
			//TODO: jQuery way for pfSense v2.1 and up
			(remove Prototype / Scriptaculous code)


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

//should be called from "coretemp_widget.widget.php" on dom:loaded
function showCoreTempData() {

	url = '/widgets/include/coretemp_widget.php'

	//TODO: jQuery way for pfSense v2.1 and up
	//jQuery.ajax(url, {
	//	type: 'get',
	//	success: function(data) {
	//		coreTempData = data || "";
	//		updateCoreTempData(coreTempData);
	//	}
	//});

	//Prototype way for pfSense up to v2.0.2 (inclusive)
	new Ajax.Request(url, {
		method: 'get',
		onSuccess: function(transport) {
			coreTempData = transport.responseText || "";
			updateCoreTempData(coreTempData);
		}
	});
	
	//call itself in 11 seconds
	setTimeout('showCoreTempData()', 11000);
}

function updateCoreTempData(coreTempData) {
	//NOTE: variable coretemp_widget_showRawOutput is declared/set in "coretemp_widget.widget.php"
	if (coretemp_widget_showRawOutput) {
		updateCoreTempDataRaw(coreTempData);
	}
	else {
		updateCoreTempDataGraph(coreTempData);
	}
}

function updateCoreTempDataRaw(coreTempData) {
	
	var coreTempContent = "no temperature data available";
	
	if (coreTempData && coreTempData != "") {
		coreTempContent = coreTempData.replace(/\|/g, "<br />");
		//rawData = coreTempData.split("|").join("<br />");
	}
	
	loadCoretempContainer(coreTempContent);
}

function loadCoretempContainer (coreTempContent) {

	//load generated graph (or raw data) into coretempContainer (DIV defined in "coretemp_widget.widget.php")

	//TODO: jQuery way for pfSense v2.1 and up
	//var coretempContainer = jQuery('#coretempContainer');
	//coretempContainer.empty();
	//coretempContainer.append(coreTempContent);

	//Prototype way for pfSense up to v2.0.2 (inclusive)
	var coretempContainer = $('coretempContainer');
	coretempContainer.update(coreTempContent);
}

function updateCoreTempDataGraph(coreTempData) {

	var normalColor = "LimeGreen";
	var normalColorShadowTop = "Lime";
	var normalColorShadowBottom = "Green";
	
	var warningColor = "Orange";
	var warningColorShadowBottom = "Chocolate";
	
	var criticalColor = "Red";
	var criticalColorShadowBottom = "DarkRed";
	
	var barBgColor = normalColor; //green/normal as default
	var barBgColorShadowTop = normalColorShadowTop; //green/normal as default
	var barBgColorShadowBottom = normalColorShadowBottom; //green/normal as default
		
	var coreTempArray = new Array();
	
	if (coreTempData && coreTempData != ""){
		coreTempArray = coreTempData.split("|");
	}

	var coreTempHTMLContent = "";
	var itemsToPulsate = new Array();
	
	for (var i = 0; i < coreTempArray.length; i++) {
	
		var coreTemp = coreTempArray[i].split(":");
		var coreTempSensor = coreTemp[0].trim();
		var coreTempValue = getCoreTempValue(coreTemp[1]);

		var pulsateTimes = 0;
		var pulsateDuration = 0;
		
		var warningTempThresholdPosition = 0;
		var criticalTempThresholdPosition = 0;
		
		//NOTE: the following variables are declared/set in "coretemp_widget.widget.php": 
		//		coretemp_widget_coreWarningTempThreshold, coretemp_widget_coreCriticalTempThreshold, 
		//		coretemp_widget_zoneWarningTempThreshold, coretemp_widget_zoneCriticalTempThreshold
		//		coretemp_widget_pulsateWarning, coretemp_widget_pulsateCritical
		
		//set graph color and pulsate parameters
		if (coreTempSensor.indexOf("cpu") > -1) { //check CPU Threshold config settings
			
			warningTempThresholdPosition = coretemp_widget_coreWarningTempThreshold;
			criticalTempThresholdPosition = coretemp_widget_coreCriticalTempThreshold;
			
			if (coreTempValue < coretemp_widget_coreWarningTempThreshold) {
				barBgColor = normalColor;
				barBgColorShadowTop = normalColorShadowTop;
				barBgColorShadowBottom = normalColorShadowBottom;
				pulsateTimes = 0;
				pulsateDuration = 0;
			} else if (coreTempValue >= coretemp_widget_coreWarningTempThreshold && coreTempValue < coretemp_widget_coreCriticalTempThreshold) {
				barBgColor = warningColor;
				barBgColorShadowTop = warningColor;
				barBgColorShadowBottom = warningColorShadowBottom;
				pulsateTimes = coretemp_widget_pulsateWarning ? 6 : 0;
				pulsateDuration = coretemp_widget_pulsateWarning ? 3000 : 0;
			} else { // coreTempValue > coretemp_widget_coreCriticalTempThreshold
				barBgColor = criticalColor;
				barBgColorShadowTop = criticalColor;
				barBgColorShadowBottom = criticalColorShadowBottom;
				pulsateTimes = coretemp_widget_pulsateCritical ? 12 : 0;
				pulsateDuration = coretemp_widget_pulsateCritical ? 6000 : 0;
			}
		} else { //assuming sensor is for a zone, check Zone Threshold config settings
			
			warningTempThresholdPosition = coretemp_widget_zoneWarningTempThreshold;
			criticalTempThresholdPosition = coretemp_widget_zoneCriticalTempThreshold;

			if (coreTempValue < coretemp_widget_zoneWarningTempThreshold) {
				barBgColor = normalColor;
				barBgColorShadowTop = normalColorShadowTop;
				barBgColorShadowBottom = normalColorShadowBottom;
				pulsateTimes = 0;
				pulsateDuration = 0;
			} else if (coreTempValue >= coretemp_widget_zoneWarningTempThreshold && coreTempValue < coretemp_widget_zoneCriticalTempThreshold) {
				barBgColor = warningColor;
				barBgColorShadowTop = warningColor;
				barBgColorShadowBottom = warningColorShadowBottom;
				pulsateTimes = coretemp_widget_pulsateWarning ? 6 : 0;
				pulsateDuration = coretemp_widget_pulsateWarning ? 3000 : 0;
			} else { // coreTempValue > coretemp_widget_zoneCriticalTempThreshold
				barBgColor = criticalColor;
				barBgColorShadowTop = criticalColor;
				barBgColorShadowBottom = criticalColorShadowBottom;
				pulsateTimes = coretemp_widget_pulsateCritical ? 12 : 0;
				pulsateDuration = coretemp_widget_pulsateCritical ? 6000 : 0;
			}
		}

		//NOTE: variable coretemp_widget_showFullSensorName is declared/set in "coretemp_widget.widget.php"
		if (!coretemp_widget_showFullSensorName) {
			coreTempSensor = getSensorFriendlyName(coreTempSensor);
		}

		var coreTempText = coreTempValue; // + " \u00B0" + "C";//add degree sign and C char

		//build temperature item/row for a sensor
		//NOTE: additional styles are set in 'coretemp_widget.widget.php'
		var coreTempRow = 	"<div class='coreTempRow' id='coreTempRow" + i + "' >" + 
							//sensor name and temperature value
							"	<div class='coreTempTextShell'><div class='coreTempText' id='coreTempText" + i + "'>" + coreTempSensor + ": </div><div class='coreTempValue' id='coreTempValue" + i + "'>" + coreTempText + " &deg;C</div></div>" + 
							//temperature bar
							"	<div class='coreTempBarShell' id='coreTempBarShell" + i + "' >" + 
							"		<div class='coreTempBar' id='coreTempBar" + i + "' style='background-color: " + barBgColor + "; border-top-color: " + barBgColorShadowTop + "; border-bottom-color: " + barBgColorShadowBottom + "; width:" + coreTempValue + "%;' ></div>" + 
							//threshold targets (warning and critical)
							"		<div class='coreTempWarnThresh' id='coreTempWarnThresh" + i + "' style='left:" + warningTempThresholdPosition  + "%;' ></div>" + 
							"		<div class='coreTempCritThresh' id='coreTempCritThresh" + i + "' style='left:" + criticalTempThresholdPosition + "%;' ></div>" + 
							//temperature scale (max 100 C)
							"		<div class='coretemp_widget_scale000'></div>" + 
							"		<div class='coretemp_widget_scale010'></div>" + 
							"		<div class='coretemp_widget_scale020'></div>" + 
							"		<div class='coretemp_widget_scale030'></div>" + 
							"		<div class='coretemp_widget_scale040'></div>" + 
							"		<div class='coretemp_widget_scale050'></div>" + 
							"		<div class='coretemp_widget_scale060'></div>" + 
							"		<div class='coretemp_widget_scale070'></div>" + 
							"		<div class='coretemp_widget_scale080'></div>" + 
							"		<div class='coretemp_widget_scale090'></div>" + 
							"		<div class='coretemp_widget_scale100'></div>" + 
							"		<div class='coretemp_widget_mark100'>100&deg;</div>" + 
							"	</div>" + 
							"</div>";
						
		//collect parameters for warning/critical items we need to pulsate
		if (pulsateTimes > 0) {
			var params = i + "|" + barBgColor + "|" + pulsateTimes + "|" + pulsateDuration;
			itemsToPulsate.push(params);
		}
		
		//append HTML item
		coreTempHTMLContent = coreTempHTMLContent + coreTempRow;
	}
	
	if (coreTempHTMLContent == "") {
		//nothing returned from coretemp
		coreTempHTMLContent = "no temperature data available";
	}
	
	//load generated graph into coretempContainer (DIV defined in "coretemp_widget.widget.php")
	loadCoretempContainer(coreTempHTMLContent);
	
	if (itemsToPulsate.length > 0) {
		//pulsate/flash warning/critical items we collected
		pulsateCoretempItems(itemsToPulsate);
	}
}

function pulsateCoretempItems(itemsToPulsate) {
	
	//pulsate/flash warning/critical items we collected
	for (var i = 0; i < itemsToPulsate.length; i++) {
	
		var pulsateParams = itemsToPulsate[i].split("|");
		var rowNum = parseInt(pulsateParams[0]);
		var textColor = pulsateParams[1];
		var pulsateTimes = parseInt(pulsateParams[2]);
		var pulsateDuration = parseInt(pulsateParams[3]);

		//TODO: jQuery way for pfSense v2.1 and up
		//var divCoreTempValue = jQuery("#coreTempValue" + rowNum); //get temp value by id
		//divCoreTempValue.effect("pulsate", { times: pulsateTimes, easing: 'easeInExpo' }, pulsateDuration);
		
		//var divCoreTempBar = jQuery("#coreTempBar" + rowNum); //get temp bar by id
		//divCoreTempBar.effect("pulsate", { times: pulsateTimes, easing: 'easeInExpo' }, pulsateDuration);
		
		//divCoreTempValue.css( { color: textColor } );
		

		//Prototype / Scriptaculous way for pfSense up to v2.0.2 (inclusive)
		var divCoreTempValue = "coreTempValue" + rowNum; //get temp value by id
		Effect.Pulsate(divCoreTempValue, { pulses: pulsateTimes, duration: (pulsateDuration / 1000), from: 0.1 } );
		
		var divCoreTempBar = "coreTempBar" + rowNum; //get temp bar by id
		Effect.Pulsate(divCoreTempBar, { pulses: pulsateTimes, duration: (pulsateDuration / 1000), from: 0.1 } );
		
		//$(divCoreTempValue).setStyle( { color: textColor } );
	}
}

function getSensorFriendlyName(sensorFullName){

	var friendlyName = "";
	
	switch (sensorFullName) {
		case "hw.acpi.thermal.tz0.temperature":
			friendlyName = "Zone 0";
			break;
		case "hw.acpi.thermal.tz1.temperature":
			friendlyName = "Zone 1";
			break;
		case "dev.cpu.0.temperature":
			friendlyName = "Core 0";
			break;
		case "dev.cpu.1.temperature":
			friendlyName = "Core 1";
			break;
		case "dev.cpu.2.temperature":
			friendlyName = "Core 2";
			break;
		case "dev.cpu.3.temperature":
			friendlyName = "Core 3";
			break;
		default:
			friendlyName = sensorFullName;
	}

	return friendlyName;
}

function getCoreTempValue(stringValue){
	//get temerature value (w/o "C" at the end)
	return parseFloat(stringValue).toFixed(1);
}
