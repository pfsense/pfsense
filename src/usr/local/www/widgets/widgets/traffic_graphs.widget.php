<?php
/*
 * traffic_graphs.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2007 Scott Dale
 * Copyright (c) 2004-2005 T. Lechat <dev@lechat.org>
 * Copyright (c) 2004-2005 Jonathan Watt <jwatt@jwatt.org>.
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
require_once("ipsec.inc");
require_once("functions.inc");

$ifdescrs = get_configured_interface_with_descr();

if (ipsec_enabled()) {
	$ifdescrs['enc0'] = "IPsec";
}

if ($_POST) {

	if (!is_array($user_settings["widgets"]["traffic_graphs"])) {
		$user_settings["widgets"]["traffic_graphs"] = array();
	}

	if (isset($_POST["refreshinterval"]) && is_numeric($_POST["refreshinterval"]) && ($_POST["refreshinterval"] >= 1) && ($_POST["refreshinterval"] <= 10)) {
		$user_settings["widgets"]["traffic_graphs"]["refreshinterval"] = $_POST["refreshinterval"];
	}

	if (isset($_POST["invert"]) && in_array($_POST["invert"], array("true", "false"))) {
		$user_settings["widgets"]["traffic_graphs"]["invert"] = $_POST["invert"];
	}

	if (isset($_POST["backgroundupdate"]) && in_array($_POST["backgroundupdate"], array("true", "false"))) {
		$user_settings["widgets"]["traffic_graphs"]["backgroundupdate"] = $_POST["backgroundupdate"];
	}

	if (isset($_POST["smoothfactor"]) && is_numeric($_POST["smoothfactor"]) && ($_POST["smoothfactor"] >= 0) && ($_POST["smoothfactor"] <= 5)) {
		$user_settings["widgets"]["traffic_graphs"]["smoothfactor"] = $_POST["smoothfactor"];
	}

	if (isset($_POST["size"]) && in_array($_POST["size"], array("8", "1"))) {
		$user_settings["widgets"]["traffic_graphs"]["size"] = $_POST["size"];
	}

	$validNames = array();

	foreach ($ifdescrs as $ifdescr => $ifname) {
		array_push($validNames, $ifdescr);
	}

	if (is_array($_POST['show'])) {
		$user_settings["widgets"]["traffic_graphs"]["filter"] = implode(',', array_diff($validNames, $_POST['show']));
	} else {
		$user_settings["widgets"]["traffic_graphs"]["filter"] = implode(',', $validNames);
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Updated traffic graphs widget settings via dashboard."));
	header("Location: /");
	exit(0);
}

if (isset($user_settings['widgets']['traffic_graphs']['refreshinterval'])) {
	$tg_refreshinterval = (int)$user_settings['widgets']['traffic_graphs']['refreshinterval'];
} else {
	$tg_refreshinterval = 1;
}

if (isset($user_settings['widgets']['traffic_graphs']['size'])) {
	$tg_size = (int)$user_settings['widgets']['traffic_graphs']['size'];
} else {
	$tg_size = 1;
}

if (isset($user_settings['widgets']['traffic_graphs']['invert'])) {
	$tg_invert = (boolean)$user_settings['widgets']['traffic_graphs']['invert'];
} else {
	$tg_invert = true;
}

if (isset($user_settings['widgets']['traffic_graphs']['backgroundupdate'])) {
	$tg_backgroundupdate = (boolean)$user_settings['widgets']['traffic_graphs']['backgroundupdate'];
} else {
	$tg_backgroundupdate = true;
}

if (isset($user_settings['widgets']['traffic_graphs']['smoothfactor'])) {
	$tg_smoothfactor = (int)$user_settings['widgets']['traffic_graphs']['smoothfactor'];
} else {
	$tg_smoothfactor = 0;
}

$skip_tg_items = explode(",", $user_settings['widgets']['traffic_graphs']['filter']);
$tg_displayed = false;
$tg_displayed_ifs_array = [];
$tg_displayed_realifsarray = [];
?>
	<script src="/vendor/d3/d3.min.js?v=<?=filemtime('/usr/local/www/vendor/d3/d3.min.js')?>"></script>
	<script src="/vendor/nvd3/nv.d3.js?v=<?=filemtime('/usr/local/www/vendor/nvd3/nv.d3.js')?>"></script>
	<script src="/vendor/visibility/visibility-1.2.3.min.js?v=<?=filemtime('/usr/local/www/vendor/visibility/visibility-1.2.3.min.js')?>"></script>

	<link href="/vendor/nvd3/nv.d3.css" media="screen, projection" rel="stylesheet" type="text/css">

	<div id="traffic-chart-error" class="alert alert-danger" style="display: none;"></div>
<?php
	foreach ($ifdescrs as $ifdescr => $ifname) {
		if (in_array($ifdescr, $skip_tg_items)) {
			continue;
		}

		$ifinfo = get_interface_info($ifdescr);

		if ($ifinfo['status'] == "down") {
			// Do not try to display the traffic graph of a down interface,
			// even though it is selected for display.
			continue;
		}

		$tg_displayed = true;
		$tg_displayed_ifs_array[] = $ifdescr;
		$tg_displayed_realifsarray[] = get_real_interface($ifdescr);
		echo '<div id="traffic-chart-' . $ifdescr . '" class="d3-chart traffic-widget-chart">';
		echo '	<svg></svg>';
		echo '</div>';
	}

	if (!$tg_displayed) {
		echo '<div id="traffic-chartnone" class="d3-chart traffic-widget-chart">';
		echo gettext('All traffic graphs are hidden.');
		echo '</div>';
	}
?>

<!-- close the body we're wrapped in and add a configuration-panel -->
</div>

<div id="widget-<?=$widgetname?>_panel-footer" class="panel-footer collapse">

	<form action="/widgets/widgets/traffic_graphs.widget.php" method="post" class="form-horizontal">
		<div class="form-group">
			<label for="traffic-graph-interval" class="col-sm-3 control-label"><?=gettext('Refresh Interval')?></label>
			<div class="col-sm-9">
				<input type="number" id="refreshinterval" name="refreshinterval" value="<?=htmlspecialchars($tg_refreshinterval)?>" min="1" max="10" class="form-control" />
			</div>
		</div>

		<div class="form-group">
			<label for="invert" class="col-sm-3 control-label"><?=gettext('Inverse')?></label>
			<div class="col-sm-9">
				<select class="form-control" id="invert" name="invert">
				<?php
					if ($tg_invert === true) {
						echo '<option value="true" selected>On</option>';
						echo '<option value="false">Off</option>';
					} else {
						echo '<option value="true">On</option>';
						echo '<option value="false" selected>Off</option>';
					}
				?>
				</select>
			</div>
		</div>

		<div class="form-group">
			<label for="size" class="col-sm-3 control-label"><?=gettext('Unit Size')?></label>
			<div class="col-sm-9">
				<select class="form-control" id="size" name="size">
				<?php
					if ($tg_size === 8) {
						echo '<option value="8" selected>Bits</option>';
						echo '<option value="1">Bytes</option>';
					} else {
						echo '<option value="8">Bits</option>';
						echo '<option value="1" selected>Bytes</option>';
					}
				?>
				</select>
			</div>
		</div>

		<div class="form-group">
			<label for="backgroundupdate" class="col-sm-3 control-label"><?=gettext('Background updates')?></label>
			<div class="col-sm-9">
				<select class="form-control" id="backgroundupdate" name="backgroundupdate">
				<?php
					if ($tg_backgroundupdate === true) {
						echo '<option value="true" selected>Keep graphs updated on inactive tab. (increases cpu usage)</option>';
						echo '<option value="false">Clear graphs when not visible.</option>';
					} else {
						echo '<option value="true">Keep graphs updated on inactive tab. (increases cpu usage)</option>';
						echo '<option value="false" selected>Clear graphs when not visible.</option>';
					}
				?>
				</select>
			</div>
		</div>

		<div class="form-group">
			<label for="smoothfactor" class="col-sm-3 control-label"><?=gettext('Graph Smoothing')?></label>
			<div class="col-sm-9">
				<input type='range' id="smoothfactor" name='smoothfactor' class='form-control' min='0' max='5'value="<?= htmlspecialchars($tg_smoothfactor) ?>"/>
			</div>
		</div>

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
					$idx = 0;

					foreach ($ifdescrs as $ifdescr => $ifname):
	?>
							<tr>
								<td><?=$ifname?></td>
								<td class="col-sm-2"><input id="show[]" name ="show[]" value="<?=$ifdescr?>" type="checkbox" <?=(!in_array($ifdescr, $skip_tg_items) ? 'checked':'')?>></td>
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
				<button id="showalltgitems" type="button" class="btn btn-info"><i class="fa fa-undo icon-embed-btn"></i><?=gettext('All')?></button>
			</div>
		</div>
	</form>

<script type="text/javascript">
//<![CDATA[
// Used by /js/traffic-graphs.js to display description from name
var graph_interfacenames = <?php
	foreach ($ifdescrs as $ifname => $ifdescr) {
		$iflist[$ifname] = $ifdescr;
	}
	echo json_encode($iflist);
?>;

events.push(function() {

	var InterfaceString = "<?=implode("|", $tg_displayed_ifs_array)?>";
	var RealInterfaceString = "<?=implode("|", $tg_displayed_realifsarray)?>";
	window.graph_backgroundupdate = <?=json_encode($tg_backgroundupdate)?>;

	window.interval = <?=json_encode($tg_refreshinterval)?>;
	window.invert = JSON.parse(<?=json_encode($tg_invert)?>);
	window.size = <?=json_encode($tg_size)?>;
	window.smoothing = <?=json_encode($tg_smoothfactor)?>;
	window.interfaces = InterfaceString.split("|").filter(function(entry) { return entry.trim() != ''; });
	window.realinterfaces = RealInterfaceString.split("|").filter(function(entry) { return entry.trim() != ''; });

	graph_init();
	graph_visibilitycheck();

	set_widget_checkbox_events("#widget-<?=$widgetname?>_panel-footer [id^=show]", "showalltgitems");
});
//]]>
</script>

<script src="/js/traffic-graphs.js?v=<?=filemtime('/usr/local/www/js/traffic-graphs.js')?>"></script>
