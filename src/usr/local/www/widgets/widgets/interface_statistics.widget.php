<?php
/*
 * interface_statistics.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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
	if (!is_array($ifdescrs)) {
		$ifdescrs = array();
	}
	$skipinterfaces = explode(",", $user_settings['widgets']['interface_statistics']['iffilter']);

	print("<thead>");
	print(	"<tr>");
	print(		"<th></th>");

	foreach ($ifdescrs as $ifdescr => $ifname) {
		if (!in_array($ifdescr, $skipinterfaces)) {
			print(		"<th>" . $ifname . "</th>");
		}
	}

	print(		"</tr>");
	print(	"</thead>");
	print(	"<tbody>");

	foreach ($rows as $key => $name) {
		print("<tr>");
		print(	"<td><b>" . $name . "</b></td>");

		foreach ($ifdescrs as $ifdescr => $ifname) {
			if (in_array($ifdescr, $skipinterfaces)) {
				continue;
			}

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
} else if ($_POST) {

	$ifdescrs = get_configured_interface_with_descr();
	if (!is_array($ifdescrs)) {
		$ifdescrs = array();
	}
	$validNames = array();

	foreach ($ifdescrs as $ifdescr => $ifname) {
		array_push($validNames, $ifdescr);
	}

	if (is_array($_POST['show'])) {
		$user_settings['widgets']['interface_statistics']['iffilter'] = implode(',', array_diff($validNames, $_POST['show']));
	} else {
		$user_settings['widgets']['interface_statistics']['iffilter'] = "";
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Saved Interface Statistics Filter via Dashboard."));
	header("Location: /index.php");
}

$widgetperiod = isset($config['widgets']['period']) ? $config['widgets']['period'] * 1000 : 10000;

?>
<table id="iftbl" class="table table-striped table-hover">
	<tr><td><?=gettext("Retrieving interface data")?></td></tr>
</table>

<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="widget-<?=$widgetname?>_panel-footer" class="panel-footer collapse">

<form action="/widgets/widgets/interface_statistics.widget.php" method="post" class="form-horizontal">
    <div class="panel panel-default col-sm-10">
		<div class="panel-body">
			<div class="table responsive">
				<table class="table table-striped table-hover table-condensed">
					<thead>
						<tr>
							<th><?=gettext("Interface")?></th>
							<th><?=gettext("Show")?></th>
						</tr>
					</thead>
					<tbody>
<?php
				$skipinterfaces = explode(",", $user_settings['widgets']['interface_statistics']['iffilter']);
				$idx = 0;

				foreach ($ifdescrs as $ifdescr => $ifname):
?>
						<tr>
							<td><?=$ifname?></td>
							<td class="col-sm-2"><input id="show[]" name ="show[]" value="<?=$ifdescr?>" type="checkbox" <?=(!in_array($ifdescr, $skipinterfaces) ? 'checked':'')?>></td>
						</tr>
<?php
				endforeach;
?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-6">
			<button type="submit" class="btn btn-primary"><i class="fa fa-save icon-embed-btn"></i><?=gettext('Save')?></button>
			<button id="showallinterfacesforstats" type="button" class="btn btn-info"><i class="fa fa-undo icon-embed-btn"></i><?=gettext('All')?></button>
		</div>
	</div>
</form>

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
			setTimeout(get_if_stats, "<?=$widgetperiod?>");
		});
	}

	events.push(function(){
		$("#showallinterfacesforstats").click(function() {
			$("[id^=show]").each(function() {
				$(this).prop("checked", true);
			});
		});

		// Start polling for updates some small random number of seconds from now (so that all the widgets don't
		// hit the server at exactly the same time)
		setTimeout(get_if_stats, Math.floor((Math.random() * 10000) + 1000));
	});
//]]>
</script>
