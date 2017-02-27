<?php
/*
 * interfaces.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c)  2007 Scott Dale
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
require_once("/usr/local/www/widgets/include/interfaces.inc");

$ifdescrs = get_configured_interface_with_descr();

if ($_POST['widgetkey']) {

	$validNames = array();

	foreach ($ifdescrs as $ifdescr => $ifname) {
		array_push($validNames, $ifdescr);
	}

	if (is_array($_POST['show'])) {
		$user_settings['widgets'][$_POST['widgetkey']]['iffilter'] = implode(',', array_diff($validNames, $_POST['show']));
	} else {
		$user_settings['widgets'][$_POST['widgetkey']]['iffilter'] = "";
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Saved Interfaces Filter via Dashboard."));
	header("Location: /index.php");
}

?>

<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
		<tbody>
<?php
$skipinterfaces = explode(",", $user_settings['widgets'][$widgetkey]['iffilter']);

foreach ($ifdescrs as $ifdescr => $ifname):
	if (in_array($ifdescr, $skipinterfaces)) {
		continue;
	}

	$ifinfo = get_interface_info($ifdescr);
	if ($ifinfo['pppoelink'] || $ifinfo['pptplink'] || $ifinfo['l2tplink']) {
		/* PPP link (non-cell) - looks like a modem */
		$typeicon = 'hdd-o';
	} else if ($ifinfo['ppplink']) {
		/* PPP Link (usually cellular) */
		$typeicon = 'signal';
	} else if (is_interface_wireless($ifdescr)) {
		/* Wi-Fi interface (hostap/client/etc) */
		$typeicon = 'wifi';
	} else {
		/* Wired/other interface. */
		$typeicon = 'sitemap';
	}

	$known_status = true;

	// Choose an icon by interface status
	if ($ifinfo['status'] == "up" || $ifinfo['status'] == "associated") {
		$icon = 'arrow-up text-success';
	} elseif ($ifinfo['status'] == "no carrier") {
		$icon = 'times-circle text-danger';
	} elseif ($ifinfo['status'] == "down") {
		$icon = 'arrow-down text-danger';
	} else {
		$known_status = false;
	}

?>
	<tr>
		<td title="<?=htmlspecialchars($ifinfo['macaddr'])?>">
			<i class="fa fa-<?=$typeicon?>"></i>
			<a href="/interfaces.php?if=<?=$ifdescr?>">
				<?=htmlspecialchars($ifname);?>
			</a>
		</td>
		<td>
			<?php if ($known_status):?>
				<i class="fa fa-<?=$icon?>" title="<?=htmlspecialchars($ifinfo['status'])?>"></i>
			<?php else: ?>
				<?=htmlspecialchars($ifinfo['status'])?>
			<?php endif; ?>
		</td>
		<td>
			<?=htmlspecialchars($ifinfo['media']);?>
		</td>

		<td <?=($ifinfo['dhcplink'] ? ' title="via dhcp"':'')?>>
			<?php if (empty($ifinfo['ipaddr']) && empty($ifinfo['ipaddrv6'])): ?>
				n/a
			<?php else: ?>
				<?=htmlspecialchars($ifinfo['ipaddr'])?>
<?php
				if (($ifinfo['ipaddr'] != "") && ($ifinfo['ipaddrv6'] != "")) {
					print('<br />');
				}
?>
				<?=htmlspecialchars($ifinfo['ipaddrv6'])?>
			<?php endif; ?>
		</td>
	</tr>
<?php
endforeach;
?>
		</tbody>
	</table>
</div>
<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="widget-<?=$widgetkey?>_panel-footer" class="panel-footer collapse">

<form action="/widgets/widgets/interfaces.widget.php" method="post" class="form-horizontal">
    <div class="panel panel-default col-sm-10">
		<div class="panel-body">
			<input type="hidden" name="widgetkey" value="<?=$widgetkey; ?>">
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
		</div>
	</div>

	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-6">
			<button type="submit" class="btn btn-primary"><i class="fa fa-save icon-embed-btn"></i><?=gettext('Save')?></button>
			<button id="showallinterfaces" type="button" class="btn btn-info"><i class="fa fa-undo icon-embed-btn"></i><?=gettext('All')?></button>
		</div>
	</div>
</form>

<script>
//<![CDATA[
	events.push(function(){
		$("#widget-<?=$widgetkey?>_panel-footer #showallinterfaces").click(function() {
			$("#widget-<?=$widgetkey?>_panel-footer [id^=show]").each(function() {
				$(this).prop("checked", true);
			});
		});

	});
//]]>
</script>
