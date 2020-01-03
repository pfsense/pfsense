<?php
/*
 * smart_status.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2012 mkirbst @ pfSense Forum
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
require_once("/usr/local/www/widgets/include/smart_status.inc");
$specplatform = system_identify_specific_platform();

$devs = array();
## Get all adX, daX, and adaX (IDE, SCSI, and AHCI) devices currently installed
if ($specplatform['name'] != "Hyper-V") {
	$devs = get_smart_drive_list();
}

if ($_POST['widgetkey']) {
	set_customwidgettitle($user_settings);

	$validNames = array();

	foreach ($devs as $dev) {
		array_push($validNames, $dev);
	}

	if (is_array($_POST['show'])) {
		$user_settings['widgets'][$_POST['widgetkey']]['filter'] = implode(',', array_diff($validNames, $_POST['show']));
	} else {
		$user_settings['widgets'][$_POST['widgetkey']]['filter'] = implode(',', $validNames);
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Saved SMART Status Filter via Dashboard."));
	header("Location: /index.php");
}

?>

<div class="table-responsive">
<table class="table table-hover table-striped table-condensed">
	<thead>
		<tr>
			<th></th>
			<th><?=gettext("Drive")?></th>
			<th><?=gettext("Ident")?></th>
			<th><?=gettext("S.M.A.R.T. Status")?></th>
		</tr>
	</thead>
	<tbody>
<?php
$skipsmart = explode(",", $user_settings['widgets'][$widgetkey]['filter']);
$smartdrive_is_displayed = false;

if (count($devs) > 0)  {
	foreach ($devs as $dev)  { ## for each found drive do
		if (in_array($dev, $skipsmart)) {
			continue;
		}

		$smartdrive_is_displayed = true;
		$dev_ident = exec("diskinfo -v /dev/$dev | grep ident   | awk '{print $1}'"); ## get identifier from drive
		$dev_state = trim(exec("smartctl -H /dev/$dev | awk -F: '/^SMART overall-health self-assessment test result/ {print $2;exit}
/^SMART Health Status/ {print $2;exit}'")); ## get SMART state from drive
		switch ($dev_state) {
			case "PASSED":
			case "OK":
				$color = "text-success";
				$icon = "fa-check";
				break;
			case "":
				$dev_state = gettext("Unknown");
				$color = "text-info";
				$icon = "fa-times-circle";
				break;
			default:
				$color = "text-alert";
				$icon = "fa-question-circle";
				break;
		}
?>
		<tr>
			<td><i class="fa <?=$icon?> <?=$color?>"></i></td>
			<td><?=$dev?></td>
			<td><?=$dev_ident?></td>
			<td><?=ucfirst($dev_state)?></td>
		</tr>
<?php
	}

	if (!$smartdrive_is_displayed) {
?>
		<tr>
			<td colspan="4" class="text-center">
				<?=gettext('All SMART drives are hidden.');?>
			</td>
		</tr>
<?php
	}
}
?>
	</tbody>
</table>
</div>
<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="<?=$widget_panel_footer_id?>" class="panel-footer collapse">

<form action="/widgets/widgets/smart_status.widget.php" method="post" class="form-horizontal">
	<?=gen_customwidgettitle_div($widgetconfig['title']); ?>
    <div class="panel panel-default col-sm-10">
		<div class="panel-body">
			<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>">
			<div class="table responsive">
				<table class="table table-striped table-hover table-condensed">
					<thead>
						<tr>
							<th><?=gettext("Drive")?></th>
							<th><?=gettext("Show")?></th>
						</tr>
					</thead>
					<tbody>
<?php
				foreach ($devs as $dev):
?>
						<tr>
							<td><?=htmlspecialchars($dev)?></td>
							<td class="col-sm-2"><input id="show[]" name ="show[]" value="<?=$dev?>" type="checkbox" <?=(!in_array($dev, $skipsmart) ? 'checked':'')?>></td>
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
	events.push(function(){
		set_widget_checkbox_events("#<?=$widget_panel_footer_id?> [id^=show]", "<?=$widget_showallnone_id?>");
	});
//]]>
</script>
