/*
	$Id: ups_status.js
	
	File location: 
		\usr\local\www\widgets\javascript\
	Used by:
		\usr\local\www\widgets\widgets\ups_status.widget.php

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

//should be called from "ups_status.widget.php"
function showUPSData() {

	//get data from ups_status.widget.php
	url = "/widgets/widgets/ups_status.widget.php?getUPSData=1" 
			//IE fix to disable cache when using http:// , just append timespan
			+ new Date().getTime();

	jQuery.ajax(url, {
		type: 'get',
		success: function(data) {
			var upsData = data || "";
			updateUPSWidgetContent(upsData);
		},
		error: function(jqXHR, status, error){
			updateUPSWidgetContent("Error getting data from [ups_status.widget.php]");
		}
	});
	
	//call itself in 11 seconds
	window.setTimeout(showUPSData, 11000);
}

function updateUPSWidgetContent(upsData) {

	upsdata_array = upsData.split(":");

	if(upsdata_array.length > 1) {
		jQuery("#ups_monitoring").html(upsdata_array[0]);
		jQuery("#ups_model").html(upsdata_array[1]);
		jQuery("#ups__status").html(upsdata_array[2]);
		jQuery("#ups_batmeter_graph").css('width', upsdata_array[3]);
		jQuery("#ups_batmeter").html(upsdata_array[3]);
		jQuery("#ups_runtime").html(upsdata_array[4]);
		// Change title to "Battery Voltage" or "Battery Temp"
		if(upsdata_array[5].indexOf("V")) {
			jQuery("#ups_celltitle_VT").html("Battery Voltage");
		} else if(upsdata_array[5].indexOf("C")) {
			jQuery("#ups_celltitle_VT").html("Battery Temp");
		}
		jQuery("#ups_bvoltage").html(upsdata_array[5]);
		jQuery("#ups_loadmeter_graph").css('width', upsdata_array[6]);
		jQuery("#ups_loadmeter").html(upsdata_array[6]);
		jQuery("#ups_inputv").html(upsdata_array[7]);
		jQuery("#ups_outputv").html(upsdata_array[8]);
		jQuery("#ups_widget").css('opacity', '1');
		jQuery("#ups_error_description").html("");
	} else {
		// print error description ($condition variable from ups_status.widget.php)
		jQuery("#ups_widget").css('opacity', '0.2');
		jQuery("#ups_error_description").html("ERROR: " + upsdata_array[0]);
	}
}
