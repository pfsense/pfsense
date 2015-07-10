<?php
/* $Id$ */
/*
	diag_pf_info.php
	Copyright (C) 2010 Scott Ullrich
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
	pfSense_BUILDER_BINARIES:	/usr/bin/top
	pfSense_MODULE: system
*/

##|+PRIV
##|*IDENT=page-diagnostics-pf-info
##|*NAME=Diagnostics: pfInfo
##|*DESCR=Allows access to the 'Diagnostics: pfInfo' page
##|*MATCH=diag_pf_info.php*
##|-PRIV

require("guiconfig.inc");

$pgtitle = gettext("Diagnostics: pfInfo");

if (stristr($_POST['Submit'], gettext("No"))) {
	header("Location: index.php");
	exit;
}

if($_REQUEST['getactivity']) {
	$text = `/sbin/pfctl -vvsi`;
	$text .= "<p/>";
	$text .= `/sbin/pfctl -vvsm`;
	$text .= "<p/>";
	$text .= `/sbin/pfctl -vvst`;
	$text .= "<p/>";
	$text .= `/sbin/pfctl -vvsI`;
	echo $text;
	exit;
}

include("head.inc");

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
$section = new Form_Section('Auto update page');

$section->addInput(new Form_Checkbox(
	'refresh',
	'Refresh',
	'Automatically refresh the output below',
	true
));

$form->add($section);
print $form;

?>
<script>
	function getpfinfo() {
		if (!$('#refresh').is(':checked'))
			return;

		$.ajax(
			'/diag_pf_info.php',
			{
				type: 'post',
				data: $(document.forms[0]).serialize(),
				success: function (data) {
					$('#xhrOutput').html(data);
				},
		});
	}

	events.push(function(){
		setInterval('getpfinfo()', 2500);
		getpfinfo();
	});
</script>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Output')?></h2></div>
	<div class="panel panel-body">
		<pre id="xhrOutput"><?=gettext("Gathering PF information, please wait...")?></pre>
	</div>
</div>

<?php include("foot.inc");