<?php
/*
	status_logs_filter_dynamic.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *  Some or all of this file is based on the m0n0wall project which is
 *  Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *  Redistribution and use in source and binary forms, with or without modification,
 *  are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution.
 *
 *  3. All advertising materials mentioning features or use of this software
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-diagnostics-logs-firewall-dynamic
##|*NAME=Status: System Logs: Firewall (Dynamic View)
##|*DESCR=Allow access to the 'Status: System Logs: Firewall (Dynamic View)' page
##|*MATCH=status_logs_filter_dynamic.php*
##|-PRIV


/* AJAX related routines */
require_once("guiconfig.inc");
require_once("filter_log.inc");
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

// The logs to display are specified in a GET argument. Default to 'system' logs
if (!$_GET['logfile']) {
	$logfile = 'filter';
	$view = 'normal';
} else {
	$logfile = $_GET['logfile'];
	$view = $_GET['view'];
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


$pgtitle = array(gettext("Status"), gettext("System logs"), gettext($allowed_logs[$logfile]["name"]), $view_title);
include("head.inc");

if (!$input_errors && $savemsg) {
	print_info_box($savemsg, 'success');
	$manage_log_active = false;
}


// Tab Array
tab_array_logs_common();


// Log Filter Submit - Firewall
filter_form_firewall();


// Now the forms are complete we can draw the log table and its controls
if ($filterlogentries_submit) {
	$filterlog = conv_log_filter($logfile_path, $nentries, $nentries + 100, $filterfieldsarray);
} else {
	$filterlog = conv_log_filter($logfile_path, $nentries, $nentries + 100, $filtertext, $interfacefilter);
}
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

	if ($reverse) {
		echo "var isReverse = true;\n";
	} else {
		echo "var isReverse = false;\n";
	}
?>
	var filter_query_string = "<?=$filter_query_string . '&logfile=' . $logfile_path . '&nentries=' . $nentries?>";

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
		http_request.open('GET', url, true);
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
	if (isPaused) {
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

	var rows = jQuery('#filter-log-entries>tr');

	// Number of rows to move by
	var move = rows.length + data.length - nentries;

	if (move < 0) {
		move = 0;
	}

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
			<?=gettext('Last ') . $nentries . gettext(' records. ') . gettext('Pause ')?><input type="checkbox" onclick="javascript:toggle_pause();" />
		</h2>
	</div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr class="text-nowrap">
						<th><?=gettext("Act")?></th>
						<th><?=gettext("Time")?></th>
						<th><?=gettext("IF")?></th>
						<th><?=gettext("Source")?></th>
						<th><?=gettext("Destination")?></th>
						<th><?=gettext("Proto")?></th>
					</tr>
				</thead>
				<tbody id="filter-log-entries">
<?php
				$rowIndex = 0;
				$tcpcnt = 0;

				foreach ($filterlog as $filterent) {
					$evenRowClass = $rowIndex % 2 ? " listMReven" : " listMRodd";
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
							<i class="fa <?php echo $icon_act;?> icon-pointer" title="<?php echo $filterent['act'] .'/'. $filterent['tracker'];?>" onclick="javascript:getURL('status_logs_filter.php?getrulenum=<?="{$filterent['rulenum']},{$filterent['tracker']},{$filterent['act']}"; ?>', outputrule);"></i>
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
				} // e-o-foreach()
?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?php
if ($tcpcnt > 0) {
?>
<div class="infoblock">
<?php
	print_info_box('<a href="https://doc.pfsense.org/index.php/What_are_TCP_Flags%3F">' .
					gettext("TCP Flags") . '</a>: F - FIN, S - SYN, A or . - ACK, R - RST, P - PSH, U - URG, E - ECE, C - CWR');
?>
</div>
<?php
}
?>

<?php
# Manage Log - Section/Form
manage_log_section();
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
