
lastsawtime = '<?php echo time(); ?>;';
var lines = Array();
var timer;
var updateDelay = 30000;
var isBusy = false;
var isPaused = true;

<?php
	if(isset($config['syslog']['reverse']))
		echo "	var isReverse = true;\n";
	else
		echo "	var isReverse = false;\n";
?>

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
		var line = '';
		line = '<div class="log-entry">';
		line += '  <span class="log-action-mini" nowrap>&nbsp;' + row_split[0] + '&nbsp;</span>';
		line += '  <span class="log-interface-mini" nowrap>' + row_split[2] + '</span>';
		line += '  <span class="log-source-mini" nowrap>' + row_split[3] + '</span>';
		line += '  <span class="log-destination-mini" nowrap>' + row_split[4] + '</span>';
		line += '  <span class="log-protocol-mini" nowrap>' + row_split[5] + '</span>';
		line += '</tr></div>';
		lastsawtime = row_split[6];
		new_data_to_add[new_data_to_add.length] = line;
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
	var rulestable = document.getElementById('log');
	var rows = rulestable.getElementsByTagName('div');
	var showanim = 1;
	if (isIE) {
		showanim = 0;
	}
	//alert(data.length);
	for(var x=0; x<data.length; x++) {
		var numrows = rows.length;
		var appearatrow;
		/*    if reverse logging is enabled we need to show the
		 *    records in a reverse order with new items appearing
         *    on the top
         */
		//if(isReverse == false) {
		//	for (var i = 2; i < numrows; i++) {
		//		nextrecord = i + 1;
		//		if(nextrecord < numrows)
		//			rows[i].innerHTML = rows[nextrecord].innerHTML;
		//	}
		//	appearatrow = numrows - 1;
		//} else {
			for (var i = numrows; i > 0; i--) {
				nextrecord = i + 1;
				if(nextrecord < numrows)
					rows[nextrecord].innerHTML = rows[i].innerHTML;
			}
			appearatrow = 1;
		//}
		var item = document.getElementById('firstrow');
		if(x == data.length-1) {
			/* nothing */
			showanim = false;
		} else {
			showanim = false;
		}
		if (showanim) {
			rows[appearatrow].style.display = 'none';
			rows[appearatrow].innerHTML = data[x];
			new Effect.Appear(rows[appearatrow]);
		} else {
			rows[appearatrow].innerHTML = data[x];
		}
	}
	/* rechedule AJAX interval */
	timer = setInterval('fetch_new_rules()', updateDelay);
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
lastsawtime = '<?php echo time(); ?>;';
timer = setInterval('fetch_new_rules()', updateDelay);
