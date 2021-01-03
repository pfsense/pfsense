<?php
/*
 * wake_on_lan.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c)  2010 Yehuda Katz
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
require_once("system.inc");
require_once("/usr/local/www/widgets/include/wake_on_lan.inc");

if (isset($config['wol']['wolentry']) && is_array($config['wol']['wolentry'])) {
	$wolcomputers = $config['wol']['wolentry'];
} else {
	$wolcomputers = array();
}

// Constructs a unique key that will identify a WoL entry in the filter list.
if (!function_exists('get_wolent_key')) {
	function get_wolent_key($wolent) {
		return ($wolent['interface'] . "|" . $wolent['mac']);
	}
}

if ($_POST['widgetkey']) {
	set_customwidgettitle($user_settings);

	$validNames = array();

	foreach ($config['wol']['wolentry'] as $wolent) {
		array_push($validNames, get_wolent_key($wolent));
	}

	if (is_array($_POST['show'])) {
		$user_settings['widgets'][$_POST['widgetkey']]['filter'] = implode(',', array_diff($validNames, $_POST['show']));
	} else {
		$user_settings['widgets'][$_POST['widgetkey']]['filter'] = implode(',', $validNames);
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Saved Wake on LAN Filter via Dashboard."));
	header("Location: /index.php");
}

?>
<div class="table-responsive">
<table class="table table-hover table-striped table-condensed">
	<thead>
		<tr>
			<th class="widgetsubheader"><?=gettext("Device")?></th>
			<th class="widgetsubheader"><?=gettext("Interface")?></th>
			<th class="widgetsubheader"><?=gettext("Status")?></th>
			<th class="widgetsubheader"><?=gettext("Wake")?></th>
		</tr>
	</thead>
	<tbody>
<?php
$skipwols = explode(",", $user_settings['widgets'][$widgetkey]['filter']);

if (count($wolcomputers) > 0):
	$wol_entry_is_displayed = false;

	$arp_table = system_get_arp_table();
	foreach ($wolcomputers as $wolent):
		if (in_array(get_wolent_key($wolent), $skipwols)) {
			continue;
		}

		$wol_entry_is_displayed = true;

		$status = '';
		foreach ($arp_table as $entry) {
			if (empty($entry['mac-address']) ||
			    $entry['mac-address'] != $wolent['mac']) {
				continue;
			}

			if (!empty($entry['expires'])) {
				$status = 'expires';
			} else if (!empty($entry['permanent'])) {
				$status = 'permanent';
			}
			break;
		}
		?>
		<tr>
			<td>
				<?= htmlspecialchars($wolent['descr']) ?><br />
				<?= $wolent['mac'] ?>
			</td>
			<td>
				<?= convert_friendly_interface_to_friendly_descr($wolent['interface']) ?>
			</td>
			<td>
		<?php if ($status == 'expires'): ?>
				<i class="fa fa-arrow-up text-success" data-toggle="tooltip" title="<?= gettext("Online") ?>"></i>
		<?php elseif ($status == 'permanent'): ?>
				<i class="fa fa-arrow-up text-success" data-toggle="tooltip" title="<?= gettext("Static ARP") ?>"></i>
		<?php else: ?>
				<i class="fa fa-arrow-down text-danger" data-toggle="tooltip" title="<?= gettext("Offline") ?>"></i>
		<?php endif; ?>
			</td>
			<td>
				<a href="services_wol.php?mac=<?= $wolent['mac'] ?>&amp;if=<?= $wolent['interface']?>" usepost>
				<i class="fa fa-power-off" data-toggle="tooltip" title="<?= gettext("Wake up!") ?>"></i>
				</a>
			</td>
		</tr>
<?php
	endforeach;
	if (!$wol_entry_is_displayed):
?>
		<tr><td colspan="4" class="text-center"><?=gettext("All WoL entries are hidden.")?></td></tr>
<?php
	endif;
else:
?>
	<tr><td colspan="4" class="text-center"><?= gettext("No saved WoL addresses") ?></td></tr>
<?php
endif;
?>
	</tbody>
</table>
<?php
$dhcpd_enabled = false;
if (is_array($config['dhcpd'])) {
	foreach ($config['dhcpd'] as $dhcpif => $dhcp) {
		if (isset($dhcp['enable']) && isset($config['interfaces'][$dhcpif]['enable'])) {
			$dhcpd_enabled = true;
			break;
		}
	}
}
?>
<?php if ($dhcpd_enabled): ?>
	<p class="text-center"><a href="status_dhcp_leases.php" class="navlink"><?=gettext('DHCP Leases Status')?></a></p>
<?php endif; ?>
</div>
<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="<?=$widget_panel_footer_id?>" class="panel-footer collapse">

<form action="/widgets/widgets/wake_on_lan.widget.php" method="post" class="form-horizontal">
	<?=gen_customwidgettitle_div($widgetconfig['title']); ?>
    <div class="panel panel-default col-sm-10">
		<div class="panel-body">
			<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>">
			<div class="table responsive">
				<table class="table table-striped table-hover table-condensed">
					<thead>
						<tr>
							<th><?=gettext("Description")?></th>
							<th><?=gettext("Interface")?></th>
							<th><?=gettext("MAC")?></th>
							<th><?=gettext("Show")?></th>
						</tr>
					</thead>
					<tbody>
<?php
				$skipwols = explode(",", $user_settings['widgets'][$widgetkey]['filter']);
				$idx = 0;

				foreach ($wolcomputers as $wolent):
?>
						<tr>
							<td><?=htmlspecialchars($wolent['descr'])?></td>
							<td><?=convert_friendly_interface_to_friendly_descr($wolent['interface'])?></td>
							<td><?=$wolent['mac']?></td>
							<td class="col-sm-2"><input id="show[]" name ="show[]" value="<?=get_wolent_key($wolent)?>" type="checkbox" <?=(!in_array(get_wolent_key($wolent), $skipwols) ? 'checked':'')?>></td>
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

<script>
//<![CDATA[
	events.push(function(){
		set_widget_checkbox_events("#<?=$widget_panel_footer_id?> [id^=show]", "<?=$widget_showallnone_id?>");
	});
//]]>
</script>
