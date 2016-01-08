<?php
/*
	status_logs_filter_summary.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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
##|*IDENT=page-diagnostics-logs-firewall-summary
##|*NAME=Status: System Logs: Firewall Log Summary
##|*DESCR=Allow access to the 'Status: System Logs: Firewall Log Summary' page
##|*MATCH=status_logs_filter_summary.php*
##|-PRIV

require_once("status_logs_common.inc");

$lines = 5000;
$entriesperblock = 5;


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


$filterlog = conv_log_filter($logfile_path, $lines, $lines);
$gotlines = count($filterlog);
$fields = array(
	'act'	   => gettext("Actions"),
	'interface' => gettext("Interfaces"),
	'proto'	 => gettext("Protocols"),
	'srcip'	 => gettext("Source IPs"),
	'dstip'	 => gettext("Destination IPs"),
	'srcport'	=> gettext("Source Ports"),
	'dstport'	=> gettext("Destination Ports"));

$segcolors = array("#2484c1", "#65a620", "#7b6888", "#a05d56", "#961a1a", "#d8d23a", "#e98125", "#d0743c", "#635222", "#6ada6a");
$numcolors = 10;

$summary = array();
foreach (array_keys($fields) as $f) {
	$summary[$f] = array();
}

$totals = array();


foreach ($filterlog as $fe) {
	$specialfields = array('srcport', 'dstport');
	foreach (array_keys($fields) as $field) {
		if (!in_array($field, $specialfields)) {
			$summary[$field][$fe[$field]]++;
		}
	}
	/* Handle some special cases */
	if ($fe['srcport']) {
		$summary['srcport'][$fe['proto'].'/'.$fe['srcport']]++;
	} else {
		$summary['srcport'][$fe['srcport']]++;
	}
	if ($fe['dstport']) {
		$summary['dstport'][$fe['proto'].'/'.$fe['dstport']]++;
	} else {
		$summary['dstport'][$fe['dstport']]++;
	}
}

print("<br />");
$infomsg = sprintf(gettext('This is a summary of the last %1$s lines of the firewall log (Max %2$s).'), $gotlines, $lines);
?>
<div>
	<div class="infoblock_open">
		<?=print_info_box($infomsg, 'info');?>
	</div>
</div>

<script src="d3pie/d3pie.min.js"></script>
<script src="d3pie/d3.min.js"></script>

<?php

$chartnum=0;
foreach (array_keys($fields) as $field) {
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=$fields[$field]?></h2></div>
	<div class="panel-body">
		<div id="pieChart<?=$chartnum?>" class="text-center">
<?php
			pie_block($summary, $field , $entriesperblock, $chartnum);
			stat_block($summary, $field , $entriesperblock);
			$chartnum++;
?>
		</div>
	</div>
</div>
<?php
}
?>

<?php
function cmp($a, $b) {
	if ($a == $b) {
		return 0;
	}
	return ($a < $b) ? 1 : -1;
}

function stat_block($summary, $stat, $num) {
	global $g, $gotlines, $fields;
	uasort($summary[$stat] , 'cmp');
	print('<div class="table-responsive">');
	print('<table class="table table-striped table-hover table-condensed">');
	print('<tr><th>' . $fields[$stat] . '</th>' . '<th>' . gettext("Data points") . '</th><th></th></tr>');
	$k = array_keys($summary[$stat]);
	$total = 0;
	$numentries = 0;
	for ($i = 0; $i < $num; $i++) {
		if ($k[$i]) {
			$total += $summary[$stat][$k[$i]];
			$numentries++;
			$outstr = $k[$i];
			if (is_ipaddr($outstr)) {
				print('<tr><td>' . $outstr . '</td>' . '<td>' . $summary[$stat][$k[$i]] . '</td><td><a href="diag_dns.php?host=' . $outstr . '" class="btn btn-xs btn-success" title="' . gettext("Reverse Resolve with DNS") . '">Lookup</a></td></tr>');

			} elseif (substr_count($outstr, '/') == 1) {
				list($proto, $port) = explode('/', $outstr);
				$service = getservbyport($port, strtolower($proto));
				if ($service) {
					$outstr .= ": {$service}";
				}
			}

			if (!is_ipaddr($outstr)) {
				print('<tr><td>' . $outstr . '</td><td>' . $summary[$stat][$k[$i]] . '</td><td></td></tr>');
			}
		}
	}
	$leftover = $gotlines - $total;
	if ($leftover > 0) {
		print "<tr><td>Other</td><td>{$leftover}</td><td></td>";
	}
	print "</table>";
	print('</div>');
}
?>

<?php
// Create the JSON document for the chart to be displayed
// Todo: Be good to investigate building this with json_encode and friends some time
function pie_block($summary, $stat, $num, $chartnum) {
	global $fields, $segcolors, $gotlines, $numcolors;
?>
<script type="text/javascript">
//<![CDATA[
var pie = new d3pie("pieChart<?=$chartnum?>", {
	"header": {
		"title": {
			"text": "",
			"fontSize": 22,
			"font": "verdana"
		},
		"subtitle": {
			"color": "#999999",
			"fontSize": 12,
			"font": "open sans"
		},
		"titleSubtitlePadding": 12
	},
	"footer": {
		"color": "#999999",
		"fontSize": 10,
		"font": "open sans",
		"location": "bottom-left"
	},
	"size": {
		"canvasHeight": 400,
		"canvasWidth": 590,
		"pieOuterRadius": "88%"
	},
	"data": {
		"sortOrder": "value-desc",
		"content": [
<?php
	uasort($summary[$stat] , 'cmp');
	$k = array_keys($summary[$stat]);
	$total = 0;
	$numentries = 0;

	for ($i = 0; $i < $num; $i++) {
		if ($k[$i]) {
			$total += $summary[$stat][$k[$i]];
			$numentries++;
			if ($i > 0) {
				print(",\r\n");
			}

			print("{");
			print('"label": "' . $k[$i] . '", "value": ');
			print($summary[$stat][$k[$i]]);
			print(', "color": "' . $segcolors[$i % $numcolors] . '"');
			print("}");

		}
	}

	$leftover = $gotlines - $total;

	if ($leftover > 0) {
		print(",\r\n");
		print("{");
		print('"label": "Other", "value": ');
		print($leftover);
		print(', "color": "' . $segcolors[$i % $numcolors] . '"');
		print("}");
	}
?>
		]
	},
	"labels": {
		"outer": {
			"pieDistance": 32
		},
		"inner": {
			"hideWhenLessThanPercentage": 3
		},
		"mainLabel": {
			"fontSize": 11
		},
		"percentage": {
			"color": "#ffffff",
			"decimalPlaces": 0
		},
		"value": {
			"color": "#adadad",
			"fontSize": 11
		},
		"lines": {
			"enabled": true
		},
		"truncation": {
			"enabled": true
		}
	},
	"effects": {
		"pullOutSegmentOnClick": {
			"effect": "linear",
			"speed": 400,
			"size": 8
		}
	},
	"misc": {
		"gradient": {
			"enabled": true,
			"percentage": 100
		}
	},
	"callbacks": {}
});
//]]>
</script>
<?php
}
?>

<?php
include("foot.inc");
?>
