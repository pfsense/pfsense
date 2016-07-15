<?php
/*
 * interface_statistics.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
 * Copyright (c) 2007 Scott Dale
 * Copyright (c) 2004-2005 T. Lechat <dev@lechat.org>
 * Copyright (c) 2004-2005 Jonathan Watt <jwatt@jwatt.org>
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("/usr/local/www/widgets/include/interface_statistics.inc");

// Compose the table contents and pass it back to the ajax caller
if ($_REQUEST && $_REQUEST['ajax']) {

	$rows = array(
		'inpkts' => gettext('Packets In'),
		'outpkts' => gettext('Packets Out'),
		'inbytes' => gettext('Bytes In'),
		'outbytes' => gettext('Bytes Out'),
		'inerrs' => gettext('Errors In'),
		'outerrs' => gettext('Errors Out'),
		'collisions' => gettext('Collisions'),
	);

	$ifdescrs = get_configured_interface_with_descr();

	print("<thead>");
	print(	"<tr>");
	print(		"<th></th>");

	foreach ($ifdescrs as $ifname) {
		print(		"<th>" . $ifname . "</th>");
	}

	print(		"</tr>");
	print(	"</thead>");
	print(	"<tbody>");

	foreach ($rows as $key => $name) {
		print("<tr>");
		print(	"<td><b>" . $name . "</b></td>");

		foreach ($ifdescrs as $ifdescr => $ifname) {
			$ifinfo = get_interface_info($ifdescr);

			if ($ifinfo['status'] == "down") {
				continue;
			}

			$ifinfo['inbytes'] = format_bytes($ifinfo['inbytes']);
			$ifinfo['outbytes'] = format_bytes($ifinfo['outbytes']);

			print("<td>" . (isset($ifinfo[$key]) ? htmlspecialchars($ifinfo[$key]) : 'n/a') . "</td>");
		}

		print(		"</td>");
		print(	"</tr>");
	}
	print(	"</tbody>");
	exit;
}

?>
<table id="iftbl" class="table table-striped table-hover">
	<tr><td><?=gettext("Retrieving interface data")?></td></tr>
</table>

<script type="text/javascript">
//<![CDATA[

	function get_if_stats() {
		var ajaxRequest;

		ajaxRequest = $.ajax({
				url: "/widgets/widgets/interface_statistics.widget.php",
				type: "post",
				data: { ajax: "ajax"}
			});

		// Deal with the results of the above ajax call
		ajaxRequest.done(function (response, textStatus, jqXHR) {
			$('#iftbl').html(response);

			// and do it again
			setTimeout(get_if_stats, 5000);
		});
	}

	events.push(function(){
		get_if_stats();
	});
//]]>
</script>
