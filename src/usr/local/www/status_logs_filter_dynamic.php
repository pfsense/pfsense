<?php
/*
 * status_logs_filter_dynamic.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

##|+PRIV
##|*IDENT=page-diagnostics-logs-firewall-dynamic
##|*NAME=Status: System Logs: Firewall (Dynamic View)
##|*DESCR=Allow access to the 'Status: System Logs: Firewall (Dynamic View)' page
##|*MATCH=status_logs_filter_dynamic.php*
##|-PRIV


/* AJAX related routines */
require_once("guiconfig.inc");
require_once("syslog.inc");
handle_ajax();


require_once("status_logs_common.inc");

/*
Build a list of allowed log files so we can reject others to prevent the page
from acting on unauthorized files.
*/
$allowed_logs = array(
	"filter" => array("name" => "Firewall",
		    "shortcut" => "filter"),
);

// The logs to display are specified in a REQUEST argument. Default to 'system' logs
if (!$_REQUEST['logfile']) {
	$logfile = 'filter';
	$view = 'normal';
} else {
	$logfile = $_REQUEST['logfile'];
	$view = $_REQUEST['view'];
	if (!array_key_exists($logfile, $allowed_logs)) {
		/* Do not let someone attempt to load an unauthorized log. */
		$logfile = 'filter';
		$view = 'normal';
	}
}

if ($view == 'normal')  { $view_title = gettext("Normal View"); }
if ($view == 'dynamic') { $view_title = gettext("Dynamic View"); }
if ($view == 'summary') { $view_title = gettext("Summary View"); }


// Log Filter Submit - Firewall
log_filter_form_firewall_submit();


// Manage Log Section - Code
manage_log_code();


// Status Logs Common - Code
status_logs_common_code();


$pgtitle = array(gettext("Status"), gettext("System Logs"), gettext($allowed_logs[$logfile]["name"]), $view_title);
$pglinks = array("", "status_logs.php", "status_logs_filter.php", "@self");
include("head.inc");

if ($changes_applied) {
	print_apply_result_box($retval, $extra_save_msg);
	$manage_log_active = false;
}

// Tab Array
tab_array_logs_common();


// Manage Log - Section/Form
if ($system_logs_manage_log_form_hidden) {
	manage_log_section();
}


// Force the formatted mode filter and form.  Raw mode is not applicable in the dynamic view.
$rawfilter = false;


// Log Filter Submit - Firewall
filter_form_firewall();


// Now the forms are complete we can draw the log table and its controls
system_log_filter();
?>

<script type="text/javascript">
//<![CDATA[
	lastsawtime = '<?=time(); ?>;';
	var lines = Array();
	var timer;
	var updateDelay = 25500;
	var isBusy = false;
	var isPaused = false;
	var nentries = <?=$nentries; ?>;

<?php
	# Build query string.
	if ($filterlogentries_submit) {	# Formatted mode.
		$filter_query_string = "type=formatted&filter=" . urlencode(json_encode($filterfieldsarray ));
	}
	if ($filtersubmit) {	# Raw mode.
		$filter_query_string = "type=raw&filter=" . urlencode(json_encode($filtertext )) . "&interfacefilter=" . $interfacefilter;
	}


	# First get the "General Logging Options" (global) chronological order setting.  Then apply specific log override if set.
	$reverse = isset($config['syslog']['reverse']);
	$specific_log = basename($logfile, '.log') . '_settings';
	if ($config['syslog'][$specific_log]['cronorder'] == 'forward') $reverse = false;
	if ($config['syslog'][$specific_log]['cronorder'] == 'reverse') $reverse = true;
?>
	var filter_query_string = "<?=$filter_query_string . '&logfile=' . $logfile_path . '&nentries=' . $nentries?>";

	var isReverse = "<?=$reverse?>";

	/* Called by the AJAX updater */
	function format_log_line(row) {
		if (row[8] == '6') {
			srcIP = '[' + row[3] + ']';
			dstIP = '[' + row[5] + ']';
		} else {
			srcIP = row[3];
			dstIP = row[5];
		}

		if (row[4] == '') {
			srcPort = '';
		} else {
			srcPort = ':' + row[4];
		}
		if (row[6] == '') {
			dstPort = '';
		} else {
			dstPort = ':' + row[6];
		}

		var line =
			'<td>' + row[0] + '</td>' +
			'<td>' + row[1] + '</td>' +
			'<td>' + row[2] + '</td>' +
			'<td>' + srcIP + srcPort + '</td>' +
			'<td>' + dstIP + dstPort + '</td>' +
			'<td>' + row[7] + '</td>';

		return line;
	}

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
		};
		http_request.open('REQUEST', url, true);
		http_request.send(null);
	};
}

function outputrule(req) {
	alert(req.content);
}

function fetch_new_rules() {
	if (isPaused) {
		return;
	}
	if (isBusy) {
		return;
	}
	isBusy = true;
	getURL('status_logs_filter_dynamic.php?' + filter_query_string + '&lastsawtime=' + lastsawtime, fetch_new_rules_callback);
}

function fetch_new_rules_callback(callback_data) {
	if (isPaused) {
		return;
	}

	var data_split;
	var new_data_to_add = Array();
	var data = callback_data.content;

	data_split = data.split("\n");

	for (var x=0; x<data_split.length-1; x++) {
		/* loop through rows */
		row_split = data_split[x].split("||");
		lastsawtime = row_split[9];

		var tmp = format_log_line(row_split);

		if (!(tmp)) {
			continue;
		}

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
	if ((isPaused) || (data.length < 1)) {
		return;
	}

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

	var rows = $('#filter-log-entries>tr');

	// Number of rows to move by
	var move = rows.length + data.length - nentries;

	if (move < 0) {
		move = 0;
	}

	if (($("#count").text() == 0) && (data.length < nentries)) {
		move += rows.length;
	}

	var tr_classes = 'text-nowrap';

	if (isReverse == false) {
		for (var i = move; i < rows.length; i++) {
			$(rows[i - move]).html($(rows[i]).html());
		}

		var tbody = $('#filter-log-entries');

		for (var i = 0; i < data.length; i++) {
			var rowIndex = rows.length - move + i;
			if (rowIndex < rows.length) {
				$(rows[rowIndex]).html(data[i]);
				$(rows[rowIndex]).className = tr_classes;
			} else {
				$(tbody).append('<tr class="' + tr_classes + '">' + data[i] + '</tr>');
			}
		}
	} else {
		for (var i = rows.length - 1; i >= move; i--) {
			$(rows[i]).html($(rows[i - move]).html());
		}

		var tbody = $('#filter-log-entries');

		for (var i = 0; i < data.length; i++) {
			var rowIndex = move - 1 - i;
			if (rowIndex >= 0) {
				$(rows[rowIndex]).html(data[i]);
				$(rows[rowIndex]).className = tr_classes;
			} else {
				$(tbody).prepend('<tr class="' + tr_classes + '">' + data[i] + '</tr>');
			}
		}
	}

	var rowCount = $('#filter-log-entries>tr').length;
	$("#count").html(rowCount);

	$('.fa').tooltip();
}

function toggle_pause() {
	if (isPaused) {
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

function toggleListDescriptions() {
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

//]]>
</script>


<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">
<?php
	// Force the raw mode table panel title so that JQuery can update it dynamically.
	$rawfilter = true;

	print(system_log_table_panel_title());
?>
<?=" " . gettext('Pause') . " "?><input type="checkbox" onclick="javascript:toggle_pause();" />
		</h2>
	</div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr class="text-nowrap">
						<th><?=gettext("Action")?></th>
						<th><?=gettext("Time")?></th>
						<th><?=gettext("Interface")?></th>
						<th><?=gettext("Source")?></th>
						<th><?=gettext("Destination")?></th>
						<th><?=gettext("Protocol")?></th>
					</tr>
				</thead>
				<tbody id="filter-log-entries">
<?php
				$rowIndex = 0;
				$tcpcnt = 0;

				foreach ($filterlog as $filterent) {
					$rowIndex++;
					if ($filterent['version'] == '6') {
						$srcIP = "[" . htmlspecialchars($filterent['srcip']) . "]";
						$dstIP = "[" . htmlspecialchars($filterent['dstip']) . "]";
					} else {
						$srcIP = htmlspecialchars($filterent['srcip']);
						$dstIP = htmlspecialchars($filterent['dstip']);
					}

					if ($filterent['srcport']) {
						$srcPort = ":" . htmlspecialchars($filterent['srcport']);
					} else {
						$srcPort = "";
					}

					if ($filterent['dstport']) {
						$dstPort = ":" . htmlspecialchars($filterent['dstport']);
					} else {
						$dstPort = "";
					}
?>
					<tr class="text-nowrap">
						<td>
<?php
							if ($filterent['act'] == "block") {
								$icon_act = "fa-times text-danger";
							} else {
								$icon_act = "fa-check text-success";
							}
?>
							<i class="fa <?=$icon_act;?> icon-pointer" title="<?php echo $filterent['act'] .'/'. $filterent['tracker'];?>" onclick="javascript:getURL('status_logs_filter.php?getrulenum=<?="{$filterent['rulenum']},{$filterent['tracker']},{$filterent['act']}"; ?>', outputrule);"></i>
						</td>
						<td><?=htmlspecialchars($filterent['time'])?></td>
						<td><?=htmlspecialchars($filterent['interface'])?></td>
						<td><?=$srcIP . $srcPort?></td>
						<td><?=$dstIP . $dstPort?></td>
<?php
						if ($filterent['proto'] == "TCP") {
							$filterent['proto'] .= ":{$filterent['tcpflags']}";
							$tcpcnt++;
						}
?>
						<td><?=htmlspecialchars($filterent['proto'])?></td>
					</tr>
<?php
				} // e-o-foreach ()

	if (count($filterlog) == 0) {
		print '<tr class="text-nowrap"><td colspan=6>';
		print_info_box(gettext('No logs to display.'));
		print '</td></tr>';
	}
?>
				</tbody>
			</table>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	$("#count").html(<?=count($filterlog);?>);
});
//]]>
</script>

		</div>
	</div>
</div>

<?php
if ($tcpcnt > 0) {
?>
<div class="infoblock">
<?php
	print_info_box('<a href="https://docs.netgate.com/pfsense/en/latest/firewall/configure.html#tcp-flags">' .
					gettext("TCP Flags") . '</a>: F - FIN, S - SYN, A or . - ACK, R - RST, P - PSH, U - URG, E - ECE, C - CWR.', 'info', false);
?>
</div>
<?php
}

# Manage Log - Section/Form
if (!$system_logs_manage_log_form_hidden) {
	manage_log_section();
}
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	$(document).ready(function() {
	    $('.fa').tooltip();
	});
});
//]]>
</script>

<?php include("foot.inc");
