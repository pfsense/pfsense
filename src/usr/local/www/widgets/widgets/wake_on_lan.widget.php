<?php
/*
 * wake_on_lan.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2017 Rubicon Communications, LLC (Netgate)
 * Copyright (c)  2010 Yehuda Katz
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *
 * 3. All advertising materials mentioning features or use of this software
 *    must display the following acknowledgment:
 *    "This product includes software developed by the pfSense Project
 *    for use in the pfSense® software distribution. (http://www.pfsense.org/).
 *
 * 4. The names "pfSense" and "pfSense Project" must not be used to
 *    endorse or promote products derived from this software without
 *    prior written permission. For written permission, please contact
 *    coreteam@pfsense.org.
 *
 * 5. Products derived from this software may not be called "pfSense"
 *    nor may "pfSense" appear in their names without prior written
 *    permission of the Electric Sheep Fencing, LLC.
 *
 * 6. Redistributions of any form whatsoever must retain the following
 *    acknowledgment:
 *
 * "This product includes software developed by the pfSense Project
 * for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 * THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 * EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 * ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 * STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 * OF THE POSSIBILITY OF SUCH DAMAGE.
 */

$nocsrf = true;

require_once("guiconfig.inc");
require_once("/usr/local/www/widgets/include/wake_on_lan.inc");

if (is_array($config['wol']['wolentry'])) {
	$wolcomputers = $config['wol']['wolentry'];
} else {
	$wolcomputers = array();
}

// Constructs a unique key that will identify a WoL entry in the filter list.
function get_wolent_key($wolent) {
	return ($wolent['interface'] . "|" . $wolent['mac']);
}

if ($_POST) {

	$validNames = array();

	if (is_array($config['wol']['wolentry'])) {
		foreach ($config['wol']['wolentry'] as $wolent) {
			array_push($validNames, get_wolent_key($wolent));
		}
	}

	if (is_array($_POST['show'])) {
		$user_settings['widgets']['wol']['filter'] = implode(',', array_diff($validNames, $_POST['show']));
	} else {
		$user_settings['widgets']['wol']['filter'] = implode(',', $validNames);
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
$skipwols = explode(",", $user_settings['widgets']['wol']['filter']);

if (count($wolcomputers) > 0):
	$wol_entry_is_displayed = false;

	foreach ($wolcomputers as $wolent):
		if (in_array(get_wolent_key($wolent), $skipwols)) {
			continue;
		}

		$wol_entry_is_displayed = true;
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
</div><div id="widget-<?=$widgetname?>_panel-footer" class="panel-footer collapse">

<form action="/widgets/widgets/wake_on_lan.widget.php" method="post" class="form-horizontal">
    <div class="panel panel-default col-sm-10">
		<div class="panel-body">
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
				$skipwols = explode(",", $user_settings['widgets']['wol']['filter']);
				$idx = 0;

				foreach ($wolcomputers as $wolent):
?>
						<tr>
							<td><?=$wolent['descr']?></td>
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
			<button id="showallwols" type="button" class="btn btn-info"><i class="fa fa-undo icon-embed-btn"></i><?=gettext('All')?></button>
		</div>
	</div>
</form>

<script>
//<![CDATA[
	events.push(function(){
		set_widget_checkbox_events("#widget-<?=$widgetname?>_panel-footer [id^=show]", "showallwols");
	});
//]]>
</script>
