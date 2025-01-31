<?php
/*
 * status_upnp.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2010 Seth Mos <seth.mos@dds.nl>
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

##|+PRIV
##|*IDENT=page-status-upnpstatus
##|*NAME=Status: UPnP IGD & PCP
##|*DESCR=Allow access to the 'Status: UPnP IGD & PCP' page.
##|*MATCH=status_upnp.php*
##|-PRIV

require_once("guiconfig.inc");

if ($_POST) {
	if ($_POST['delete-all']) {
		upnp_action('restart');
		$savemsg = gettext("Port maps have been deleted and the service restarted.");
	}
}

$rdr_entries = array();
exec("/sbin/pfctl -a miniupnpd -s nat -P", $rdr_entries, $pf_ret);

$pgtitle = array(gettext("Status"), gettext("UPnP IGD &amp; PCP"));
$shortcut_section = "upnp";

include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (!config_get_path('installedpackages/miniupnpd/config/0/iface_array') ||
    !config_path_enabled('installedpackages/miniupnpd/config/0')) {

	print_info_box(sprintf(gettext('Service is currently disabled. It can be enabled here: %1$s%2$s%3$s.'), '<a href="pkg_edit.php?xml=miniupnpd.xml">', gettext('Services &gt; UPnP IGD &amp; PCP'), '</a>'), 'danger');
	include("foot.inc");
	exit;
}

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=htmlentities(gettext("Active UPnP IGD & PCP/NAT-PMP Port Maps"))?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th><?=gettext("Ext Interface")?></th>
						<th><?=gettext("Ext Port")?></th>
						<th><?=gettext("Int IP")?></th>
						<th><?=gettext("Int Port")?></th>
						<th><?=gettext("Protocol")?></th>
						<th><?=gettext("Source IP")?></th>
						<th><?=gettext("Source Port")?></th>
						<th><?=gettext("Description")?></th>
					</tr>
				</thead>
				<tbody>
<?php
$i = 0;

foreach ($rdr_entries as $rdr_entry) {
	/* rdr log quick on igb2 inet proto tcp from any to any port = xxxxx keep state label "xxxxx" rtable 0 -> xxx.xxx.xxx.xxx port xxxxx */
	/* rdr log quick on igb2 inet proto udp from any to xxx.xxx.xxx.xxx port = xxxxxx keep state label "xxxxx" rtable 0 -> xxx.xxx.xxx.xxx port xxxxx */
	if (preg_match("/on (?P<iface>.*) inet proto (?P<proto>.*) from (?P<srcaddr>.*) (port (?P<srcport>.*) )?to (?P<extaddr>.*) port = (?P<extport>.*) keep state (label \"(?P<descr>.*)\" )?rtable [0-9] -> (?P<intaddr>.*) port (?P<intport>.*)/", $rdr_entry, $matches)) {
?>
					<tr>
						<td>
							<?= htmlspecialchars(convert_real_interface_to_friendly_descr($matches['iface'])) ?>
						</td>
						<td>
							<?= htmlspecialchars($matches['extport']) ?>
						</td>
						<td>
							<?= htmlspecialchars($matches['intaddr']) ?>
						</td>
						<td>
							<?= htmlspecialchars($matches['intport']) ?>
						</td>
						<td>
							<?= htmlspecialchars(strtoupper($matches['proto'])) ?>
						</td>
						<td>
							<?= htmlspecialchars($matches['srcaddr']) ?>
						</td>
						<td>
							<?= htmlspecialchars($matches['srcport'] ?: "any") ?>
						</td>
						<td>
							<?= htmlspecialchars($matches['descr']) ?>
						</td>
					</tr>
<?php
	}
	$i++;
}
?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<div>
	<form action="status_upnp.php" method="post">
		<nav class="action-buttons">
			<button class="btn btn-danger btn-sm" type="submit" name="delete-all" value="delete-all">
				<i class="fa-solid fa-trash-can icon-embed-btn"></i>
				<?=gettext("Delete all port maps")?>
			</button>
		</nav>
	</form>
</div>

<?php
include("foot.inc");
