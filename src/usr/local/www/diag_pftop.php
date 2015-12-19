<?php
/*
	diag_pftop.php
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
##|*IDENT=page-diagnostics-system-pftop
##|*NAME=Diagnostics: pfTop
##|*DESCR=Allows access to the 'Diagnostics: pfTop' page
##|*MATCH=diag_pftop.php*
##|-PRIV

require("guiconfig.inc");

$pgtitle = array(gettext("Diagnostics"), gettext("pfTop"));

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
<?php
?>
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
