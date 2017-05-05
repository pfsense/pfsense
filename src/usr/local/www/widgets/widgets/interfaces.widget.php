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
// Update once per minute by default, instead of every 10 seconds
$widgetperiod = isset($config['widgets']['period']) ? $config['widgets']['period'] * 1000 * 6 : 60000;

if ($_POST['widgetkey']) {

	$validNames = array();

	foreach ($ifdescrs as $ifdescr => $ifname) {
		array_push($validNames, $ifdescr);
	}

	if (is_array($_POST['show'])) {
		$user_settings['widgets'][$_POST['widgetkey']]['iffilter'] = implode(',', array_diff($validNames, $_POST['show']));
	} else {
		$user_settings['widgets'][$_POST['widgetkey']]['iffilter'] = implode(',', $validNames);
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Saved Interfaces Filter via Dashboard."));
	header("Location: /index.php");
}

// When this widget is included in the dashboard, $widgetkey is already defined before the widget is included.
// When the ajax call is made to refresh the interfaces table, 'widgetkey' comes in $_REQUEST.
if ($_REQUEST['widgetkey']) {
	$widgetkey = $_REQUEST['widgetkey'];
}

?>

<div class="table-responsive" id="ifaces_status_<?=$widgetkey?>">
	<table class="table table-striped table-hover table-condensed">
		<tbody>

<?php
$skipinterfaces = explode(",", $user_settings['widgets'][$widgetkey]['iffilter']);
$widgetkey_nodash = str_replace("-", "", $widgetkey);
$interface_is_displayed = false;

foreach ($ifdescrs as $ifdescr => $ifname):
	if (in_array($ifdescr, $skipinterfaces)) {
		continue;
	}

	$interface_is_displayed = true;
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
			<?php if ($ifinfo['pppoelink'] == "up" || $ifinfo['pptplink'] == "up" || $ifinfo['l2tplink'] == "up"):?>
				<?=sprintf(gettext("Uptime: %s"), htmlspecialchars($ifinfo['ppp_uptime']));?>
			<?php else: ?>
				<?=htmlspecialchars($ifinfo['media']);?>
			<?php endif; ?>
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
if (!$interface_is_displayed):
?>
	<tr>
		<td class="text-center">
			<?=gettext('All interfaces are hidden.');?>
		</td>
	</tr>

<?php
endif;
?>
		</tbody>
	</table>
</div>
<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="<?=$widget_panel_footer_id?>" class="panel-footer collapse">

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
			<button id="<?=$widget_showallnone_id?>" type="button" class="btn btn-info"><i class="fa fa-undo icon-embed-btn"></i><?=gettext('All')?></button>
		</div>
	</div>
</form>

<?php

/* for AJAX response, we only need the panels */
if ($_REQUEST['widgetkey']) {
	exit;
}
?>

<script type="text/javascript">
//<![CDATA[
function getstatus_ifaces_<?=$widgetkey_nodash?>() {
	$.ajax({
		type: 'get',
		url: '/widgets/widgets/interfaces.widget.php',
		dataType: 'html',
		data: { widgetkey: "<?=$widgetkey?>" },
		dataFilter: function(raw){
			// We reload the entire widget, strip this block of javascript from it
			return raw.replace(/<script>([\s\S]*)<\/script>/gi, '');
		},
		success: function(data){
			$('#ifaces_status_<?=$widgetkey?>').html(data);
		},
		error: function(){
			$('#ifaces_status_<?=$widgetkey?>').html("<div class=\"alert alert-danger\"><?=gettext('Unable to retrieve status'); ?></div>");
		}
	});
}

	events.push(function(){
		set_widget_checkbox_events("#<?=$widget_panel_footer_id?> [id^=show]", "<?=$widget_showallnone_id?>");
		setInterval('getstatus_ifaces_<?=$widgetkey_nodash?>()', "<?=$widgetperiod?>");
	});
//]]>
</script>
