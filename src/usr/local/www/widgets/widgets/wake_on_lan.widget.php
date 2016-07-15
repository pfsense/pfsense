<?php
/*
 * wake_on_lan.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("/usr/local/www/widgets/include/wake_on_lan.inc");

if (is_array($config['wol']['wolentry'])) {
	$wolcomputers = $config['wol']['wolentry'];
} else {
	$wolcomputers = array();
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
if (count($wolcomputers) > 0):
	foreach ($wolcomputers as $wolent):
		$is_active = exec("/usr/sbin/arp -an |/usr/bin/grep {$wolent['mac']}| /usr/bin/wc -l|/usr/bin/awk '{print $1;}'");
		$status = exec("/usr/sbin/arp -an | /usr/bin/awk '$4 == \"{$wolent['mac']}\" { print $7 }'");
		?>
		<tr>
			<td>
				<?= $wolent['descr'] ?><br />
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
				<a href="services_wol.php?mac=<?= $wolent['mac'] ?>&amp;if=<?= $wolent['interface']?>">
				<i class="fa fa-power-off" data-toggle="tooltip" title="<?= gettext("Wake up!") ?>"></i>
				</a>
			</td>
		</tr>
<?php	endforeach;
else: ?>
	<tr><td colspan="4" class="text-center"><?= gettext("No saved WoL addresses") ?></td></tr>
<?php
endif;
?>
	</tbody>
</table>
<p class="text-center"><a href="status_dhcp_leases.php" class="navlink"><?=gettext('DHCP Leases Status')?></a></p>
</div>
