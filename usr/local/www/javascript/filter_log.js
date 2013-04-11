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

		var tmp = format_log_line(row_split);
		if ( !(tmp) ) continue;

		new_data_to_add[new_data_to_add.length] = tmp;
	}
	update_table_rows(new_data_to_add);
	isBusy = false;
}

function in_arrayi(needle, haystack) {
	var i = haystack.length;
	while (i--) {
		if (haystack[i].toLowerCase() === needle.toLowerCase()) {
			return true;
		}
	}
	return false;
}

function update_table_rows(data) {
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

	var rows = jQuery('#filter-log-entries>tr');

	// Number of rows to move by
	var move = rows.length + data.length - nentries;
	if (move < 0)
		move = 0;

	if (isReverse == false) {
		for (var i = move; i < rows.length; i++) {
			jQuery(rows[i - move]).html(jQuery(rows[i]).html());
		}

		var tbody = jQuery('#filter-log-entries');
		for (var i = 0; i < data.length; i++) {
			var rowIndex = rows.length - move + i;
			if (rowIndex < rows.length) {
				jQuery(rows[rowIndex]).html(data[i]);
			} else {
				jQuery(tbody).append('<tr>' + data[i] + '</tr>');
			}
		}
	} else {
		for (var i = rows.length - 1; i >= move; i--) {
			jQuery(rows[i]).html(jQuery(rows[i - move]).html());
		}

		var tbody = jQuery('#filter-log-entries');
		for (var i = 0; i < data.length; i++) {
			var rowIndex = move - 1 - i;
			if (rowIndex >= 0) {
				jQuery(rows[rowIndex]).html(data[i]);
			} else {
				jQuery(tbody).prepend('<tr>' + data[i] + '</tr>');
			}
		}
	}

	// Much easier to go through each of the rows once they've all be added.
	rows = jQuery('#filter-log-entries>tr');
	for (var i = 0; i < rows.length; i++) {
		rows[i].className = i % 2 == 0 ? 'listMRodd' : 'listMReven';
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

function toggleListDescriptions(){
	var ss = document.styleSheets;
	for (var i=0; i<ss.length; i++) {
		var rules = ss[i].cssRules || ss[i].rules;
		for (var j=0; j<rules.length; j++) {
			if (rules[j].selectorText === ".listMRDescriptionL" || rules[j].selectorText === ".listMRDescriptionR") {
				rules[j].style.display = rules[j].style.display === "none" ? "table-cell" : "none";
			}
		}
	}
}
