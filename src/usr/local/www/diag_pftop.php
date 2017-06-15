<?php
/*
 * diag_pftop.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-diagnostics-system-pftop
##|*NAME=Diagnostics: pfTop
##|*DESCR=Allows access to the 'Diagnostics: pfTop' page
##|*MATCH=diag_pftop.php*
##|-PRIV

require_once("guiconfig.inc");

$pgtitle = array(gettext("Diagnostics"), gettext("pfTop"));

$sorttypes = array('age', 'bytes', 'dest', 'dport', 'exp', 'none', 'pkt', 'sport', 'src');
$viewtypes = array('default', 'label', 'long', 'queue', 'rules', 'size', 'speed', 'state', 'time');
$viewall = array('queue', 'label', 'rules');
$numstates = array('50', '100', '200', '500', '1000', 'all');

if ($_REQUEST['getactivity']) {
	if ($_REQUEST['sorttype'] && in_array($_REQUEST['sorttype'], $sorttypes) &&
	    $_REQUEST['viewtype'] && in_array($_REQUEST['viewtype'], $viewtypes) &&
	    $_REQUEST['states'] && in_array($_REQUEST['states'], $numstates)) {
		$viewtype = escapeshellarg($_REQUEST['viewtype']);
		if (in_array($_REQUEST['viewtype'], $viewall)) {
			$sorttype = "";
			$numstate = "-a";
		} else {
			$sorttype = "-o " . escapeshellarg($_REQUEST['sorttype']);
			$numstate = ($_REQUEST['states'] == "all" ? "-a" : escapeshellarg($_REQUEST['states']));
		}
	} else {
		$sorttype = "bytes";
		$viewtype = "default";
		$numstate = "100";
	}

	$text = `pftop -b {$sorttype} -w 135 -v {$viewtype} {$numstate}`;
	echo trim($text);
	exit;
}

include("head.inc");

if ($_REQUEST['sorttype'] && in_array($_REQUEST['sorttype'], $sorttypes) &&
    $_REQUEST['viewtype'] && in_array($_REQUEST['viewtype'], $viewtypes) &&
    $_REQUEST['states'] && in_array($_REQUEST['states'], $numstates)) {
	$viewtype = escapeshellarg($_REQUEST['viewtype']);
	if (in_array($_REQUEST['viewtype'], $viewall)) {
		$sorttype = "";
		$numstate = "-a";
	} else {
		$sorttype = "-o " . escapeshellarg($_REQUEST['sorttype']);
		$numstate = ($_REQUEST['states'] == "all" ? "-a" : escapeshellarg($_REQUEST['states']));
	}
} else {
	$sorttype = "bytes";
	$viewtype = "default";
	$numstate = "100";
}

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form(false);
$form->addGlobal(new Form_Input(
	'getactivity',
	null,
	'hidden',
	'yes'
));
$section = new Form_Section('pfTop Configuration');

$validViews = array(
	'default' => gettext('default'), 
	'label' => gettext('label'), 
	'long' => gettext('long'),
	'queue' => gettext('queue'), 
	'rules' => gettext('rules'), 
	'size' => gettext('size'),
	'speed' => gettext('speed'), 
	'state' => gettext('state'), 
	'time' => gettext('time'),
);
$section->addInput(new Form_Select(
	'viewtype',
	'View',
	$viewtype,
	$validViews
));

$section->addInput(new Form_Select(
	'sorttype',
	'Sort by',
	$sorttype,
	array(
		'none' => gettext('None'),
		'age' => gettext('Age'),
		'bytes' => gettext('Bytes'),
		'dest' => gettext('Destination Address'),
		'dport' => gettext('Destination Port'),
		'exp' => gettext('Expiry'),
		'pkt' => gettext('Packet'),
		'sport' => gettext('Source Port'),
		'src' => gettext('Source Address'),
	)
));

$validStates = array(50, 100, 200, 500, 100, 'all');
$section->addInput(new Form_Select(
	'states',
	'Maximum # of States',
	$numstate,
	array_combine($validStates, $validStates)
));

$form->add($section);
print $form;
?>

<script type="text/javascript">
//<![CDATA[
	function getpftopactivity() {
		$.ajax(
			'/diag_pftop.php',
			{
				method: 'post',
				data: $(document.forms[0]).serialize(),
				dataType: "html",
				success: function (data) {
					$('#xhrOutput').html(data);
				},
			}
		);
	}

	events.push(function() {
		setInterval('getpftopactivity()', 2500);
		getpftopactivity();
	});
//]]>
</script>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Output')?></h2></div>
	<div class="panel panel-body">
		<pre id="xhrOutput"><?=gettext("Gathering pfTOP activity, please wait...")?></pre>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	$('#viewtype').on('change', function() {
		if (['queue', 'label', 'rules'].indexOf($(this).val()) > -1) {
			$("#sorttype, #sorttypediv, #statesdiv, #states").parents('.form-group').hide();
		} else {
			$("#sorttype, #sorttypediv, #statesdiv, #states").parents('.form-group').show();
		}
	});
});
//]]>
</script>
<?php include("foot.inc");
