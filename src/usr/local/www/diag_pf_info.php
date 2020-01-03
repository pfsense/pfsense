<?php
/*
 * diag_pf_info.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-diagnostics-pf-info
##|*NAME=Diagnostics: pfInfo
##|*DESCR=Allows access to the 'Diagnostics: pfInfo' page
##|*MATCH=diag_pf_info.php*
##|-PRIV

require_once("guiconfig.inc");

$pgtitle = array(gettext("Diagnostics"), gettext("pfInfo"));

if (stristr($_POST['Submit'], gettext("No"))) {
	header("Location: index.php");
	exit;
}

if ($_REQUEST['getactivity']) {
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
$section = new Form_Section('Auto Update Page');

$section->addInput(new Form_Checkbox(
	'refresh',
	'Refresh',
	'Automatically refresh the output below',
	true
));

$form->add($section);
print $form;

?>
<script type="text/javascript">
//<![CDATA[
	function getpfinfo() {
		if (!$('#refresh').is(':checked')) {
			return;
		}

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

	events.push(function() {
		setInterval('getpfinfo()', 2500);
		getpfinfo();
	});
//]]>
</script>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Output')?></h2></div>
	<div class="panel panel-body">
		<pre id="xhrOutput"><?=gettext("Gathering PF information, please wait...")?></pre>
	</div>
</div>

<?php include("foot.inc");
