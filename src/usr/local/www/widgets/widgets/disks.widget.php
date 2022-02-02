<?php
/*
 * disks.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021-2022 Rubicon Communications, LLC (Netgate)
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

// Composer autoloader
require_once('vendor/autoload.php');

// pfSense includes
require_once('guiconfig.inc');

// Widget includes
require_once('/usr/local/www/widgets/include/disks.inc');

global $disks_widget_defaults;

$widgetkey = (isset($_POST['widgetkey'])) ? $_POST['widgetkey'] : $widgetkey;

// Now overide any defaults with user settings
$widget_config = array_replace($disks_widget_defaults, (array) $user_settings['widgets'][$widgetkey]);

// Randomly invalidate the cache, 25% chance.
disks_cache_invalidate(false, 0.25);

// Are we handling an ajax refresh?
if (isset($_POST['ajax'])) {
	print(disks_compose_widget_table($widget_config));

	// We are done here...
	exit();
}

// Are we saving the configurable settings?
if (isset($_POST['save'])) {
	// Process settings post
	disks_do_widget_settings_post($_POST, $user_settings);

	// Redirect back to home...
	header('Location: /');

	// We are done here...
	exit();
}

?>

	<div class="table-responsive">
		<table id="<?=htmlspecialchars($widgetkey)?>-table" class="table table-striped table-hover table-condensed">
			<?=disks_compose_widget_table($widget_config)?>
		</table>
	</div>
</div>

<div id="widget-<?=htmlspecialchars($widgetkey)?>_panel-footer" class="panel-footer collapse">

	<form action="/widgets/widgets/<?=$widgetconfig['basename']?>.widget.php" method="post" class="form-horizontal">
		<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey)?>" />
		<input type="hidden" name="save" value="save" />
		<div class="panel panel-default col-sm-12">
			<div class="panel-body">
				<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>">
				<div class="table responsive">
					<table class="table table-striped table-hover table-condensed">
						<thead>
							<tr>
								<th><?=htmlspecialchars(gettext("Mount"))?></th>
								<th>
									<span><i class="fa fa-thumb-tack" style="vertical-align:middle;"></i></span>
									<?=htmlspecialchars(gettext("Pin"))?>
								</th>
							</tr>
						</thead>
						<tbody>
<?php
					$disk_filter = explode(",", $user_settings['widgets'][$widgetkey]['disk_filter']);

					foreach (disks_get_nonroot_filesystems() as $fs):
?>
							<tr>
								<td><?=htmlspecialchars($fs->getPath())?></td>
								<td class="col-sm-2"><input id="<?=htmlspecialchars($widgetkey)?>_disk_filter[]" name ="<?=htmlspecialchars($widgetkey)?>_disk_filter[]" value="<?=$fs->getPath()?>" type="checkbox" <?=(in_array($fs->getPath(), $disk_filter) ? 'checked' : null)?>></td>
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
			<label for="<?=htmlspecialchars($widgetkey)?>_autoshow_threshold" class="col-sm-4 control-label"><?=htmlspecialchars(gettext('Auto Show Threshold'))?></label>
			<div class="col-sm-8">
				<input type="number" id="<?=htmlspecialchars($widgetkey)?>_autoshow_threshold" name="<?=htmlspecialchars($widgetkey)?>_autoshow_threshold" value="<?=htmlspecialchars($widget_config['autoshow_threshold'])?>" placeholder="<?=htmlspecialchars($disks_widget_defaults['autoshow_threshold'])?>" min="0" max="100" class="form-control" />
				<span class="help-block">
					<?=htmlspecialchars(gettext('Automatically show mounts when utilization exceeds the specified threshold (%).'))?>
					<br />
					<span class="text-danger">Note:</span>
					<?=sprintf(gettext('The default is %s%% (0%% to disable).'), htmlspecialchars($disks_widget_defaults['autoshow_threshold']))?>
				</span>
			</div>
		</div>

		<nav class="action-buttons">
			<button type="submit" class="btn btn-primary">
				<i class="fa fa-save icon-embed-btn"></i>
				<?=htmlspecialchars(gettext('Save'))?>
			</button>
			<button id="<?=$widget_showallnone_id?>" type="button" class="btn btn-info">
				<i class="fa fa-undo icon-embed-btn"></i>
				<?=htmlspecialchars(gettext('All'))?>
			</button>
		</nav>
	</form>

	<script type="text/javascript">
	//<![CDATA[
	events.push(function() {
		let cookieName = <?=json_encode("treegrid-{$widgetkey}")?>;

		// Callback function called by refresh system when data is retrieved
		function disks_callback(s) {
			var tree = $(<?=json_encode("#{$widgetkey}-table")?>);

			tree.removeData();

			tree.html(s);

			initTreegrid(true);
		}

		// POST data to send via AJAX
		var postdata = {
			ajax: "ajax",
			widgetkey: <?=json_encode($widgetkey)?>
		};

		// Create an object defining the widget refresh AJAX call
		var disksObject = new Object();
		disksObject.name = "disks";
		disksObject.url = "/widgets/widgets/disks.widget.php";
		disksObject.callback = disks_callback;
		disksObject.parms = postdata;
		disksObject.freq = 1;

		// Register the AJAX object
		register_ajax(disksObject);

		function initTreegrid(isAjax) {
			var tree = $(<?=json_encode("#{$widgetkey}-table")?>);

			if (!isAjax) {
				$.removeCookie(cookieName);
				
				tree.removeData();
			}

			tree.treegrid({
				expanderExpandedClass: 'fa fa fa-chevron-down',
				expanderCollapsedClass: 'fa fa fa-chevron-right',
				initialState: 'collapsed',
				saveStateName: cookieName,
				saveState: true
			});
		}

		initTreegrid(false);

		set_widget_checkbox_events("#<?=$widget_panel_footer_id?> [id^=<?=htmlspecialchars($widgetkey)?>_disk_filter]", "<?=$widget_showallnone_id?>");
	});
	//]]>
	</script>
