<?php
/*
 * diag_routes.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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

$limit = '100';
$filter = '';

if (isset($_POST['isAjax'])) {
	require_once('auth_check.inc');

	$netstat = "/usr/bin/netstat -rW";
	if (isset($_POST['IPv6'])) {
		$netstat .= " -f inet6";
		echo "IPv6\n";
	} else {
		$netstat .= " -f inet";
		echo "IPv4\n";

	}
	if (!isset($_POST['resolve'])) {
		$netstat .= " -n";
	}

	$netstat .= " | /usr/bin/tail -n +5";

	if (!empty($_POST['filter'])) {
		$netstat .= " | /usr/bin/egrep " . escapeshellarg($_POST['filter']);
	}

	if (is_numeric($_POST['limit']) && $_POST['limit'] > 0) {
		$netstat .= " | /usr/bin/head -n " . escapeshellarg($_POST['limit']);
	}

	echo htmlspecialchars_decode(shell_exec($netstat));

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
	null
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

	for (var i = 0; i < responseTextArr.length; i++) {

		if (responseTextArr[i] == "") {
			continue;
		}

		var tmp = '<tr>';

		var j = 0;
		var entry = responseTextArr[i].split(" ");
		for (var k = 0; k < entry.length; k++) {
			if (entry[k] == "") {
				continue;
			}
			tmp += '<td>' + entry[k] + '<\/td>';
			j++;
		}

		tmp += '<td><\/td>'
		tbody += tmp;
	}

	$('#' + section + ' > tbody').html(tbody);
}

function update_all_routes() {
	update_routes("IPv4");
	update_routes("IPv6");
}

events.push(function() {
	setInterval('update_all_routes()', 15000);
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
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" id="IPv4" data-sortable>
		<thead>
			<tr>
				<th><?= gettext('Destination') ?></th>
				<th><?= gettext('Gateway') ?></th>
				<th><?= gettext('Flags') ?></th>
				<th><?= gettext('Uses') ?></th>
				<th><?= gettext('MTU') ?></th>
				<th><?= gettext('Interface') ?></th>
				<th><?= gettext('Expire') ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td colspan="7"><?=gettext("Gathering data, please wait...")?></td>
			</tr>
		</tbody>
		</table>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("IPv6 Routes")?></h2></div>
	<div class="panel panel-body">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" id="IPv6" data-sortable>
		<thead>
			<tr>
				<th><?= gettext('Destination') ?></th>
				<th><?= gettext('Gateway') ?></th>
				<th><?= gettext('Flags') ?></th>
				<th><?= gettext('Uses') ?></th>
				<th><?= gettext('MTU') ?></th>
				<th><?= gettext('Interface') ?></th>
				<th><?= gettext('Expire') ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td colspan="7"><?=gettext("Gathering data, please wait...")?></td>
			</tr>
		</tbody>
		</table>
	</div>
</div>

<?php include("foot.inc");
