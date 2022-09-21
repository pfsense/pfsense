<?php
/*
 * services_status.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2007 Sam Wenham
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
require_once("captiveportal.inc");
require_once("service-utils.inc");
require_once("ipsec.inc");
require_once("vpn.inc");
require_once("/usr/local/www/widgets/include/services_status.inc");

$widgetkey = (isset($_POST['widgetkey'])) ? $_POST['widgetkey'] : $widgetkey;
$widget_config = $user_settings['widgets'][$widgetkey];
$services = get_services_array();

// Are we handling an ajax refresh?
if (isset($_POST['ajax'])) {
	print(services_compose_widget_body($widgetkey));
	// We are done here...
	exit();
}

if (isset($_POST['save'])) {
	set_customwidgettitle($user_settings);
	$validNames = array();
	$showArrName = ($widgetkey . '_show');
	$showArr = (isset($_POST[$showArrName])) ? $_POST[$showArrName] : null;
	foreach ($services as $service) {
		array_push($validNames, $service['dispname']);
	}
	if (is_array($showArr)) {
		$user_settings['widgets'][$_POST['widgetkey']]['filter'] = implode(',', array_diff($validNames, $showArr));
	} else {
		$user_settings['widgets'][$_POST['widgetkey']]['filter'] = implode(',', $validNames);
	}
	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Saved Service Status Filter via Dashboard."));
	header('Location: /');
	exit();
}

?>
<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
		<thead>
			<tr>
				<th></th>
				<th><?=gettext('Service')?></th>
				<th><?=gettext('Description')?></th>
				<th><?=gettext('Action')?></th>
			</tr>
		</thead>
		<tbody id="<?=htmlspecialchars($widgetkey)?>">
			<?=services_compose_widget_body($widgetkey)?>
		</tbody>
	</table>
</div>
<!-- close the body we're wrapped in and add a configuration-panel -->
</div>
<div id="widget-<?=htmlspecialchars($widgetkey)?>_panel-footer" class="panel-footer collapse">

<form action="/widgets/widgets/services_status.widget.php" method="post" class="form-horizontal">
	<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey)?>" />
	<input type="hidden" name="save" value="save" />
	<?=gen_customwidgettitle_div($widgetconfig['title']); ?>
    <div class="panel panel-default col-sm-10">
		<div class="panel-body">
			<div class="table responsive">
				<table class="table table-striped table-hover table-condensed">
					<thead>
						<tr>
							<th><?=gettext("Service")?></th>
							<th><?=gettext("Show")?></th>
						</tr>
					</thead>
					<tbody>
<?php
				$skipservices = explode(",", $user_settings['widgets'][$widgetkey]['filter']);

				foreach ($services as $service):
					if (!empty(trim($service['dispname'])) || is_numeric($service['dispname'])) {
?>
						<tr>
							<td><?=$service['dispname']?></td>
							<td class="col-sm-2"><input id="<?=htmlspecialchars($widgetkey)?>_show[]" name="<?=htmlspecialchars($widgetkey)?>_show[]" value="<?=$service['dispname']?>" type="checkbox" <?=(!in_array($service['dispname'], $skipservices) ? 'checked':'')?>></td>
						</tr>
<?php
					}
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

	events.push(function(){

		/// --------------------- Centralized widget refresh system ------------------------------

		// Callback function called by refresh system when data is retrieved
		function services_callback(s) {
			$(<?=json_encode("#{$widgetkey}")?>).html(s);
			// The click handler has to be attached after the div is updated
			addServiceControlClickHandlers(<?=json_encode($widgetkey)?>, false);
		}

		// POST data to send via AJAX
		var postdata = {
			ajax: "ajax",
			widgetkey: <?=json_encode($widgetkey)?>
		};

		// Create an object defining the widget refresh AJAX call
		var servicesObject = new Object();
		servicesObject.name = "services";
		servicesObject.url = "/widgets/widgets/services_status.widget.php";
		servicesObject.callback = services_callback;
		servicesObject.parms = postdata;
		servicesObject.freq = 2;

		// Register the AJAX object
		register_ajax(servicesObject);

		// ---------------------------------------------------------------------------------------------------

		set_widget_checkbox_events("#<?=$widget_panel_footer_id?> [id^=<?=htmlspecialchars($widgetkey)?>_show]", "<?=$widget_showallnone_id?>");
		
	});

	window.onload=function() {
		addServiceControlClickHandlers("<?=htmlspecialchars($widgetkey)?>", false);
	}

//]]>
</script>