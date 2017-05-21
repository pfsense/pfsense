<?php
/*
 * diag_routes.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2006 Fernando Lamos
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *
 * 3. All advertising materials mentioning features or use of this software
 *    must display the following acknowledgment:
 *    "This product includes software developed by the pfSense Project
 *    for use in the pfSense® software distribution. (http://www.pfsense.org/).
 *
 * 4. The names "pfSense" and "pfSense Project" must not be used to
 *    endorse or promote products derived from this software without
 *    prior written permission. For written permission, please contact
 *    coreteam@pfsense.org.
 *
 * 5. Products derived from this software may not be called "pfSense"
 *    nor may "pfSense" appear in their names without prior written
 *    permission of the Electric Sheep Fencing, LLC.
 *
 * 6. Redistributions of any form whatsoever must retain the following
 *    acknowledgment:
 *
 * "This product includes software developed by the pfSense Project
 * for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 * THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 * EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 * ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 * STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 * OF THE POSSIBILITY OF SUCH DAMAGE.
 */

##|+PRIV
##|*IDENT=page-diagnostics-routingtables
##|*NAME=Diagnostics: Routing tables
##|*DESCR=Allow access to the 'Diagnostics: Routing tables' page.
##|*MATCH=diag_routes.php*
##|-PRIV

$limit = '100';
$filter = '';

if (isset($_REQUEST['isAjax'])) {
	require_once('auth_check.inc');
	
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
require_once('guiconfig.inc');

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
