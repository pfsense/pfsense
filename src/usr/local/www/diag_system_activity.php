<?php
/*
 * diag_system_activity.php
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
##|*IDENT=page-diagnostics-system-activity
##|*NAME=Diagnostics: System Activity
##|*DESCR=Allows access to the 'Diagnostics: System Activity' page
##|*MATCH=diag_system_activity.php*
##|-PRIV

require_once("guiconfig.inc");

$pgtitle = array(gettext("Diagnostics"), gettext("System Activity"));

if ($_REQUEST['getactivity']) {
	$text = `/usr/bin/top -aHS | /usr/bin/cut -c1-105`;
	echo $text;
	exit;
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {
	ajaxbusy = false;

	function getcpuactivity() {

		if (ajaxbusy) {
			return;
		}

		ajaxbusy = true;

		ajaxRequest = $.ajax(
			{
				url: '/diag_system_activity.php',
				type: 'post',
				data: {
					getactivity: 'yes'
				},
				dataType: "html",
			}
		);

		// Deal with the results of the above ajax call
		ajaxRequest.done(function (response, textStatus, jqXHR) {
			$('#xhrOutput').html(response);
			ajaxbusy = false;
		});

		setTimeout(getcpuactivity, 2500);
	};

	getcpuactivity();

});
//]]>
</script>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('CPU Activity')?></h2></div>
	<div class="panel panel-body">
		<pre id="xhrOutput"><?=gettext("Gathering CPU activity, please wait...")?></pre>
	</div>
</div>

<?php include("foot.inc");
