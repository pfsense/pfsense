<?php
/*
 * diag_routes.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2006 Fernando Lamos
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
##|*IDENT=page-diagnostics-routingtables
##|*NAME=Diagnostics: Routing tables
##|*DESCR=Allow access to the 'Diagnostics: Routing tables' page.
##|*MATCH=diag_routes.php*
##|-PRIV

require_once('guiconfig.inc');

$limit = '100';
$filter = '';

if (isset($_REQUEST['isAjax'])) {
	$netstat = "/usr/bin/netstat -rnW";
	if (isset($_REQUEST['IPv6'])) {
		$netstat .= " -f inet6";
		echo "IPv6\n";
	} else {
		$netstat .= " -f inet";
		echo "IPv4\n";
	}

	if (!empty($_REQUEST['filter'])) {
		$netstat .= " | /usr/bin/sed -e " . escapeshellarg("1,3d; 5,\$ { /" . htmlspecialchars($_REQUEST['filter']) . "/!d; };");
	} else {
		$netstat .= " | /usr/bin/sed -e '1,3d'";
	}

	if (is_numeric($_REQUEST['limit']) && $_REQUEST['limit'] > 0) {
		$_REQUEST['limit']++;  // Account for the header line
		$netstat .= " | /usr/bin/head -n {$_REQUEST['limit']}";
	}

	if (isset($_REQUEST['resolve'])) {
		$netstat_output_array = explode("\n", shell_exec($netstat));
		$output_text = "";
		foreach ($netstat_output_array as $netstat_line) {
			$netstat_columns_array = explode(" ", $netstat_line);
			$output_line = "";
			foreach ($netstat_columns_array as $netstat_column) {
				// An address can be like:
				// address%dev/CIDR     ff01::%em0/32
				// address%dev          fe80::a00:1234:5678:9abc%em0
				// address/CIDR         2001:470:12:abcd::/64       192.168.1.0/24
				// or just an address   2001:470:12:abcd:1:2:3:4    192.168.1.1
				// Separate the bit before and after any slash.
				$slash_parts = explode("/", $netstat_column);
				// Then separate the bit before and after any percent sign.
				$percent_parts = explode("%", $slash_parts[0]);
				if (is_ipaddr($percent_parts[0])) {
					// Try and reverse resolve the first part, which looks like an IP Address
					$output_line .= gethostbyaddr($percent_parts[0]);
					if (strlen($percent_parts[1]) > 0) {
						// Put back the percent bit.
						$output_line .= "%" . $percent_parts[1];
					}
					if (strlen($slash_parts[1]) > 0) {
						// Put back the slash bit.
						$output_line .= "/" . $slash_parts[1];
					}
				} else {
					$output_line .= $netstat_column;
				}
				$output_line .= " ";
			}
			$output_text .= trim($output_line) . "\n";
		}
	} else {
		$output_text = shell_exec($netstat);
	}

	echo htmlspecialchars_decode($output_text);
	exit;
}

$pgtitle = array(gettext("Diagnostics"), gettext("Routes"));
$shortcut_section = "routing";

include('head.inc');

$form = new Form(false);
$form->addGlobal(new Form_Input(
	'isAjax',
	null,
	'hidden',
	1
));
$section = new Form_Section('Routing Table Display Options');

$section->addInput(new Form_Checkbox(
	'resolve',
	'Resolve names',
	'Enable',
	$resolve
))->setHelp('Enabling name resolution may cause the query to take longer.'.
	' It can be stopped at any time by clicking the Stop button in the browser.');

$validLimits = array('10', '50', '100', '200', '500', '1000', 'all');
$section->addInput(new Form_Select(
	'limit',
	'Rows to display',
	$limit,
	array_combine($validLimits, $validLimits)
));

$section->addInput(new Form_Input(
	'filter',
	'Filter',
	'text',
	$host
))->setHelp('Use a regular expression to filter the tables.');

$form->add($section);

$form->addGlobal(new Form_Button(
	'Submit',
	'Update',
	null,
	'fa-refresh'
))->addClass('btn-primary');

print $form;
?>
<script type="text/javascript">
//<![CDATA[
function update_routes(section) {
	$.ajax(
		'/diag_routes.php',
		{
			type: 'post',
			data: $(document.forms[0]).serialize() +'&'+ section +'=true',
			success: update_routes_callback,
	});
}

function update_routes_callback(html) {
	// First line contains section
	var responseTextArr = html.split("\n");
	var section = responseTextArr.shift();
	var tbody = '';
	var field = '';
	var tr_class = '';
	var thead = '<tr>';

	for (var i = 0; i < responseTextArr.length; i++) {

		if (responseTextArr[i] == "") {
			continue;
		}

		if (i == 0) {
			var tmp = '';
		} else {
			var tmp = '<tr>';
		}

		var j = 0;
		var entry = responseTextArr[i].split(" ");
		for (var k = 0; k < entry.length; k++) {
			if (entry[k] == "") {
				continue;
			}
			if (i == 0) {
				tmp += '<th>' + entry[k] + '<\/th>';
			} else {
				tmp += '<td>' + entry[k] + '<\/td>';
			}
			j++;
		}

		if (i == 0) {
			thead += tmp;
		} else {
			tmp += '<td><\/td>'
			tbody += tmp;
		}
	}

	$('#' + section + ' > thead').html(thead);
	$('#' + section + ' > tbody').html(tbody);
}

function update_all_routes() {
	update_routes("IPv4");
	update_routes("IPv6");
}

events.push(function() {
	setInterval('update_all_routes()', 5000);
	update_all_routes();

	$(document.forms[0]).on('submit', function(e) {
		update_all_routes();

		e.preventDefault();
	});
});
//]]>
</script>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("IPv4 Routes")?></h2></div>
	<div class="panel panel-body">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" id="IPv4">
		<thead>
			<tr>
				<th><!-- filled by xhr --></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><?=gettext("Gathering data, please wait...")?></td>
			</tr>
		</tbody>
		</table>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("IPv6 Routes")?></h2></div>
	<div class="panel panel-body">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" id="IPv6">
		<thead>
			<tr>
				<th><!-- filled by xhr --></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><?=gettext("Gathering data, please wait...")?></td>
			</tr>
		</tbody>
		</table>
	</div>
</div>

<?php include("foot.inc");
