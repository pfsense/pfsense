<?php
/*
 * diag_limiter_info.php
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
##|*IDENT=page-diagnostics-limiter-info
##|*NAME=Diagnostics: Limiter Info
##|*DESCR=Allows access to the 'Diagnostics: Limiter Info' page
##|*MATCH=diag_limiter_info.php*
##|-PRIV

require_once("guiconfig.inc");

$pgtitle = array(gettext("Diagnostics"), gettext("Limiter Info"));
$shortcut_section = "trafficshaper-limiters";

if ($_REQUEST['getactivity']) {
	$text = `/sbin/ipfw pipe show`;
	if ($text == "") {
		$text = gettext("No limiters were found on this system.");
	}
	echo gettext("Limiters:") . "\n";
	echo $text;
	$text = `/sbin/ipfw sched show`;
	if ($text != "") {
		echo "\n\n" . gettext("Schedulers") . ":\n";
		echo $text;
	}
	$text = `/sbin/ipfw queue show`;
	if ($text != "") {
		echo "\n\n" . gettext("Queues") . ":\n";
		echo $text;
	}
	exit;
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

?>
<script type="text/javascript">
//<![CDATA[
	function getlimiteractivity() {
		$.ajax(
			'/diag_limiter_info.php',
			{
				type: 'post',
				data: {
					getactivity: 'yes'
				},
				success: function (data) {
					$('#xhrOutput').html(data);
				},
		});
	}

	events.push(function() {
		setInterval('getlimiteractivity()', 2500);
		getlimiteractivity();
	});
//]]>
</script>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Limiter Information")?></h2></div>
	<div class="panel-body">
		<pre id="xhrOutput"><?=gettext("Gathering Limiter information, please wait...")?></pre>
	</div>
</div>

<?php include("foot.inc");
