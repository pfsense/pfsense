
if (typeof getURL == 'undefined') {
	getURL = function(url, callback) {
		if (!url)
			throw 'No URL for getURL';
		try {
			if (typeof callback.operationComplete == 'function')
				callback = callback.operationComplete;
		} catch (e) {}
			if (typeof callback != 'function')
				throw 'No callback function for getURL';
		var http_request = null;
		if (typeof XMLHttpRequest != 'undefined') {
		    http_request = new XMLHttpRequest();
		}
		else if (typeof ActiveXObject != 'undefined') {
			try {
				http_request = new ActiveXObject('Msxml2.XMLHTTP');
			} catch (e) {
				try {
					http_request = new ActiveXObject('Microsoft.XMLHTTP');
				} catch (e) {}
			}
		}
		if (!http_request)
			throw 'Both getURL and XMLHttpRequest are undefined';
		http_request.onreadystatechange = function() {
			if (http_request.readyState == 4) {
				callback( { success : true,
				  content : http_request.responseText,
				  contentType : http_request.getResponseHeader("Content-Type") } );
			}
		}
		http_request.open('GET', url, true);
		http_request.send(null);
	}
}

function outputrule(req) {
	alert(req.content);
}
function fetch_new_rules() {
	if(isPaused)
		return;
	if(isBusy)
		return;
	isBusy = true;
	getURL('diag_logs_filter_dynamic.php?lastsawtime=' + lastsawtime, fetch_new_rules_callback);
}
function fetch_new_rules_callback(callback_data) {
	if(isPaused)
		return;

	var data_split;
	var new_data_to_add = Array();
	var data = callback_data.content;

	data_split = data.split("\n");

	for(var x=0; x<data_split.length-1; x++) {
		/* loop through rows */
		row_split = data_split[x].split("||");
		lastsawtime = row_split[6];
		new_data_to_add[new_data_to_add.length] = format_log_line(row_split);
	}
	update_div_rows(new_data_to_add);
	isBusy = false;
}

function update_div_rows(data) {
	if(isPaused)
		return;

	var isIE = navigator.appName.indexOf('Microsoft') != -1;
	var isSafari = navigator.userAgent.indexOf('Safari') != -1;
	var isOpera = navigator.userAgent.indexOf('Opera') != -1;
	var showanim = 1;
	if (isIE) {
		showanim = 0;
	}
	
	var startat = data.length - nentries;
	if (startat < 0) {
		startat = 0;
	}
	data = data.slice(startat, data.length);

	var rows = jQuery('.log-entry-mini');
	if (jQuery(rows).length == 0) {
		rows = jQuery('.log-entry');
	}

	for(var x=0; x<data.length; x++) {
		/*    if reverse logging is enabled we need to show the
		 *    records in a reverse order with new items appearing
		 *    on the top
		 */
		if(isReverse == false) {
			for (var i = 1; i < jQuery(rows).length; i++) {
				jQuery(rows[i-1]).html(jQuery(rows[i]).html());
			}
		} else {
			for (var i = jQuery(rows).length - 1; i > 0; i--) {
				jQuery(rows[i]).html(jQuery(rows[i-1]).html());
			}
		}
		jQuery('#firstrow').html(data[x]);
	}
}

function toggle_pause() {
	if(isPaused) {
		isPaused = false;
		fetch_new_rules();
	} else {
		isPaused = true;
	}
}
/* start local AJAX engine */
if (typeof updateDelay != 'undefined') {
	timer = setInterval('fetch_new_rules()', updateDelay);
}
