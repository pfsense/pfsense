<?php
/*
 * interface_statistics.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("/usr/local/www/widgets/include/interface_statistics.inc");

$ifdescrs = get_configured_interface_with_descr();
$ifstats = array(
	'inpkts' => gettext('Packets In'),
	'outpkts' => gettext('Packets Out'),
	'inbytes' => gettext('Bytes In'),
	'outbytes' => gettext('Bytes Out'),
	'inerrs' => gettext('Errors In'),
	'outerrs' => gettext('Errors Out'),
	'collisions' => gettext('Collisions'),
);

// Compose the table contents and pass it back to the ajax caller
if ($_REQUEST && $_REQUEST['ajax']) {

	$skipinterfaces = explode(",", $user_settings['widgets'][$_REQUEST['widgetkey']]['iffilter']);
	$skipifstats = explode(",", $user_settings['widgets'][$_REQUEST['widgetkey']]['ifstatsfilter']);
	$an_interface_is_selected = false; // decide if at least 1 interface is selected for display
	$an_interface_is_displayed = false; // decide if at least 1 interface is displayed (i.e. not down)
	$an_ifstat_is_displayed = false;

	if (isset($user_settings["widgets"][$_REQUEST['widgetkey']]["orientation_type"])) {
		$orientation_type = $user_settings["widgets"][$_REQUEST['widgetkey']]["orientation_type"];
	} else {
		$orientation_type = "if_columns";
	}

	$ifstats_arr = array();

	// Construct an array of only the selected stats items
	foreach ($ifstats as $key => $name) {
		if (!in_array($key, $skipifstats)) {
			$ifstats_arr[$key] = $name;
			$an_ifstat_is_displayed = true;
		}
	}

	$ifinfo_arr = array();

	// Gather the stats info for the required interfaces
	foreach ($ifdescrs as $ifdescr => $ifname) {
		if (in_array($ifdescr, $skipinterfaces)) {
			continue;
		}

		$ifinfo = get_interface_info($ifdescr);
		$an_interface_is_selected = true;

		if ($ifinfo_arr[$ifdescr]['status'] != "down") {
			$ifinfo['inbytes'] = format_bytes($ifinfo['inbytes']);
			$ifinfo['outbytes'] = format_bytes($ifinfo['outbytes']);
			$ifinfo['name'] = $ifname;
			$ifinfo_arr[$ifdescr] = $ifinfo;
			$an_interface_is_displayed = true;
		}
	}

	print("<thead>");
	print("<tr>");
	print("<th></th>");

	if ($orientation_type == "if_columns") {
		// Put interface names as column headings
		foreach ($ifinfo_arr as $ifdescr => $ifinfo) {
			print("<th>" . $ifinfo['name'] . "</th>");
		}

		if (!$an_interface_is_selected) {
			print("<th>" . gettext('All interfaces are hidden.') . "</th>");
		} else if (!$an_interface_is_displayed) {
			print("<th>" . gettext('All selected interfaces are down.') . "</th>");
		}
	} else {
		// Put stats item names as column headings
		foreach ($ifstats_arr as $key => $name) {
			print("<th>" . $name . "</th>");
		}

		if (!$an_ifstat_is_displayed) {
			print("<th>" . gettext('All statistics are hidden.') . "</th>");
		}
	}

	print("</tr>");
	print("</thead>");
	print("<tbody>");

	if ($orientation_type == "if_columns") {
		//Construct the table with stats as rows and interfaces as columns
		foreach ($ifstats_arr as $key => $name) {
			print("<tr>");
			print("<td><b>" . $name . "</b></td>");

			foreach ($ifinfo_arr as $ifdescr => $ifinfo) {
				print("<td>" . (isset($ifinfo[$key]) ? htmlspecialchars($ifinfo[$key]) : 'n/a') . "</td>");
			}

			print("</tr>");
		}

		if (!$an_ifstat_is_displayed) {
			print("<tr><td><b>" . gettext('All statistics are hidden.') . "</b></td></tr>");
		}
	} else {
		//Construct the table with interfaces as rows and stats as columns
		foreach ($ifinfo_arr as $ifdescr => $ifinfo) {
			print("<tr>");
			print("<td><b>" . $ifinfo['name'] . "</b></td>");

			foreach ($ifstats_arr as $key => $name) {
				print("<td>" . (isset($ifinfo[$key]) ? htmlspecialchars($ifinfo[$key]) : 'n/a') . "</td>");
			}

			print("</tr>");
		}

		if (!$an_interface_is_selected) {
			print("<tr><td><b>" . gettext('All interfaces are hidden.') . "</b></td></tr>");
		} else if (!$an_interface_is_displayed) {
			print("<tr><td><b>" . gettext('All selected interfaces are down.') . "</b></td></tr>");
		}
	}

	print(	"</tbody>");
	exit;
} else if ($_POST['widgetkey']) {
	set_customwidgettitle($user_settings);

	if (isset($_POST['orientation_type'])) {
		$user_settings['widgets'][$_POST['widgetkey']]['orientation_type'] = $_POST['orientation_type'];
	}

	$validNames = array();

	foreach ($ifdescrs as $ifdescr => $ifname) {
		array_push($validNames, $ifdescr);
	}

	if (is_array($_POST['show'])) {
		$user_settings['widgets'][$_POST['widgetkey']]['iffilter'] = implode(',', array_diff($validNames, $_POST['show']));
	} else {
		$user_settings['widgets'][$_POST['widgetkey']]['iffilter'] = implode(',', $validNames);
	}

	$validStats = array();

	foreach ($ifstats as $statkey => $statname) {
		array_push($validStats, $statkey);
	}

	if (is_array($_POST['showifstat'])) {
		$user_settings['widgets'][$_POST['widgetkey']]['ifstatsfilter'] = implode(',', array_diff($validStats, $_POST['showifstat']));
	} else {
		$user_settings['widgets'][$_POST['widgetkey']]['ifstatsfilter'] = implode(',', $validStats);
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Saved Interface Statistics Filter via Dashboard."));
	header("Location: /index.php");
}

$widgetperiod = isset($config['widgets']['period']) ? $config['widgets']['period'] * 1000 : 10000;
$widgetkey_nodash = str_replace("-", "", $widgetkey);

?>
<table id="<?=htmlspecialchars($widgetkey)?>-iftbl" class="table table-striped table-hover">
	<tr><td><?=gettext("Retrieving interface data")?></td></tr>
</table>

<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="<?=$widget_panel_footer_id?>" class="panel-footer collapse">

<form action="/widgets/widgets/interface_statistics.widget.php" method="post" class="form-horizontal">
	<?=gen_customwidgettitle_div($widgetconfig['title']); ?>
	<div class="form-group">
		<label class="col-sm-3 control-label"><?=gettext('Orientation')?></label>
		<?php
			$orientation_type_if_columns = "checked";
			$orientation_type_if_rows = "";
			if (isset($user_settings["widgets"][$widgetkey]["orientation_type"])) {
				$selected_radio = $user_settings["widgets"][$widgetkey]["orientation_type"];
				if ($selected_radio == "if_columns") {
					$orientation_type_if_columns = "checked";
					$orientation_type_if_rows = "";
				} else if ($selected_radio == "if_rows") {
					$orientation_type_if_columns = "";
					$orientation_type_if_rows = "checked";
				}
			}
?>
		<div class="col-sm-6">
			<div class="radio">
				<label><input name="orientation_type" type="radio" id="orientation_type_if_columns" value="if_columns" <?=$orientation_type_if_columns;?> /> <?=gettext('Each interface in a column')?></label>
			</div>
			<div class="radio">
				<label><input name="orientation_type" type="radio" id="orientation_type_if_rows" value="if_rows" <?=$orientation_type_if_rows;?> /><?=gettext('Each interface in a row')?></label>
			</div>
		</div>
	</div>

    <div class="panel panel-default col-sm-10">
		<div class="panel-body">
			<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>">
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
				$skipinterfaces = explode(",", $user_settings['widgets'][$widgetkey]['iffilter']);
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
			<div class="table responsive">
				<table class="table table-striped table-hover table-condensed">
					<thead>
						<tr>
							<th><?=gettext("Stats Item")?></th>
							<th><?=gettext("Show")?></th>
						</tr>
					</thead>
					<tbody>
<?php
				$skipifstats = explode(",", $user_settings['widgets'][$widgetkey]['ifstatsfilter']);
				$idx = 0;

				foreach ($ifstats as $statkey => $statname):
?>
						<tr>
							<td><?=$statname?></td>
							<td class="col-sm-2"><input id="showifstat[]" name ="showifstat[]" value="<?=$statkey?>" type="checkbox" <?=(!in_array($statkey, $skipifstats) ? 'checked':'')?>></td>
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
			<button id="<?=$widget_showallnone_id?>" type="button" class="btn btn-info"><i class="fa fa-undo icon-embed-btn"></i><?=gettext('All')?></button>
		</div>
	</div>
</form>

<script type="text/javascript">
//<![CDATA[
/*
	function get_if_stats_<?=htmlspecialchars($widgetkey_nodash)?>() {
		var ajaxRequest;

		ajaxRequest = $.ajax({
				url: "/widgets/widgets/interface_statistics.widget.php",
				type: "post",
				data: { ajax: "ajax", widgetkey: <?=json_encode($widgetkey)?>}
			});

		// Deal with the results of the above ajax call
		ajaxRequest.done(function (response, textStatus, jqXHR) {
			$(<?=json_encode('#' . $widgetkey . '-iftbl')?>).html(response);

			// and do it again
			setTimeout(get_if_stats_<?=htmlspecialchars($widgetkey_nodash)?>, "<?=$widgetperiod?>");
		});
	}
*/
	events.push(function() {
		// --------------------- Centralized widget refresh system ------------------------------

		// Callback function called by refresh system when data is retrieved
		function interface_statistics_callback(s) {
			$(<?=json_encode('#' . $widgetkey . '-iftbl')?>).html(s);
		}

		// POST data to send via AJAX
		var postdata = {
			ajax : "ajax",
			widgetkey : <?=json_encode($widgetkey)?>
		 };

		// Create an object defining the widget refresh AJAX call
		var ifstatObject = new Object();
		ifstatObject.name = "IFstats";
		ifstatObject.url = "/widgets/widgets/interface_statistics.widget.php";
		ifstatObject.callback = interface_statistics_callback;
		ifstatObject.parms = postdata;
		ifstatObject.freq = 1;

		// Register the AJAX object
		register_ajax(ifstatObject);

		// ---------------------------------------------------------------------------------------------------
		// Note: This manages all settings checkboxes with id starting with "show"
		// (i.e. both the interface and stats item selection groups)
		// using a single All/None button
		set_widget_checkbox_events("#<?=$widget_panel_footer_id?> [id^=show]", "<?=$widget_showallnone_id?>");

	});
//]]>
</script>
