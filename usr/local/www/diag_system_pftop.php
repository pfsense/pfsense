<?php
/* $Id$ */
/*
	diag_system_pftop.php
	Copyright (C) 2008-2009 Scott Ullrich
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/

/*
	pfSense_MODULE:	filter
*/

##|+PRIV
##|*IDENT=page-diagnostics-system-pftop
##|*NAME=Diagnostics: pfTop
##|*DESCR=Allows access to the 'Diagnostics: pfTop' page
##|*MATCH=diag_system_pftop.php*
##|-PRIV

require("guiconfig.inc");

$pgtitle = gettext("Diagnostics: pfTop");

$sorttypes = array('age', 'bytes', 'dest', 'dport', 'exp', 'none', 'peak', 'pkt', 'rate', 'size', 'sport', 'src');
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

	$text = `pftop -b {$sorttype} -v {$viewtype} {$numstate}`;
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

if ($input_errors)
	print_input_errors($input_errors);

require('classes/Form.class.php');
$form = new Form(false);
$form->addGlobal(new Form_Input(
	'getactivity',
	null,
	'hidden',
	'yes'
));
$section = new Form_Section('pfTop Configuration');

$validViews = array(
	'default', 'label', 'long',
	'queue', 'rules', 'size',
	'speed', 'state', 'time',
);
$section->addInput(new Form_Select(
	'viewtype',
	'View',
	$viewtype,
	array_combine($validViews, $validViews)
));

$section->addInput(new Form_Select(
	'sorttype',
	'Sort by',
	$sorttype,
	array(
		'none' => 'None',
		'age' => 'Age',
		'bytes' => 'Bytes',
		'dest' => 'Destination Address',
		'dport' => 'Destination Port',
		'exp' => 'Expiry',
		'peak' => 'Peak',
		'pkt' => 'Packet',
		'rate' => 'Rate',
		'size' => 'Size',
		'sport' => 'Source Port',
		'src' => 'Source Address',
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

<script>
	function getpftopactivity() {
		$.ajax(
			'/diag_system_pftop.php',
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

	events.push(function(){
		setInterval('getpftopactivity()', 2500);
		getpftopactivity();
	});
</script>
<?php
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Output')?></h2></div>
	<div class="panel panel-body">
		<pre id="xhrOutput"><?=gettext("Gathering pfTOP activity, please wait...")?></pre>
	</div>
</div>

<script>
events.push(function(){
	$('#viewtype').on('change', function(){
		if (['queue', 'label', 'rules'].indexOf($(this).val()) > -1)
			$("#sorttype, #sorttypediv, #statesdiv, #states").parents('.form-group').hide();
		else
			$("#sorttype, #sorttypediv, #statesdiv, #states").parents('.form-group').show();
	});
});
</script>
<?php include("foot.inc");