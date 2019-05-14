<?php
/*
 * status_upnp.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2019 Rubicon Communications, LLC (Netgate)
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
##|*NAME=Status: UPnP Status
##|*DESCR=Allow access to the 'Status: UPnP Status' page.
##|*MATCH=status_upnp.php*
##|-PRIV

require_once("guiconfig.inc");

if ($_POST) {
	if ($_POST['clear']) {
		upnp_action('restart');
		$savemsg = gettext("Rules have been cleared and the daemon restarted.");
	}
}

$rdr_entries = array();
exec("/sbin/pfctl -aminiupnpd -sn", $rdr_entries, $pf_ret);

$pgtitle = array(gettext("Status"), gettext("UPnP &amp; NAT-PMP"));
$shortcut_section = "upnp";

include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (!$config['installedpackages'] ||
    !$config['installedpackages']['miniupnpd']['config'][0]['iface_array'] ||
    !$config['installedpackages']['miniupnpd']['config'][0]['enable']) {

	print_info_box(sprintf(gettext('UPnP is currently disabled. It can be enabled here: %1$s%2$s%3$s.'), '<a href="pkg_edit.php?xml=miniupnpd.xml">', gettext('Services &gt; UPnP &amp; NAT-PMP'), '</a>'), 'danger');
	include("foot.inc");
	exit;
}

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("UPnP &amp; NAT-PMP Rules")?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th><?=gettext("Port")?></th>
						<th><?=gettext("Protocol")?></th>
						<th><?=gettext("Internal IP")?></th>
						<th><?=gettext("Int. Port")?></th>
						<th><?=gettext("Description")?></th>
					</tr>
				</thead>
				<tbody>
<?php
$i = 0;

foreach ($rdr_entries as $rdr_entry) {
	if (preg_match("/on (.*) inet proto (.*) from any to any port = (.*) keep state label \"(.*)\" rtable [0-9] -> (.*) port (.*)/", $rdr_entry, $matches)) {
	$rdr_proto = $matches[2];
	$rdr_port = $matches[3];
	$rdr_label =$matches[4];
	$rdr_ip = $matches[5];
	$rdr_iport = $matches[6];

?>
					<tr>
						<td>
							<?=$rdr_port?>
						</td>
						<td>
							<?=$rdr_proto?>
						</td>
						<td>
							<?=$rdr_ip?>
						</td>
						<td>
							<?=$rdr_iport?>
						</td>
						<td>
							<?=$rdr_label?>
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
			<button class="btn btn-danger btn-sm" type="submit" name="clear" id="clear" value="<?=gettext("Clear all sessions")?>">
				<i class="fa fa-trash icon-embed-btn"></i>
				<?=gettext("Clear all sessions")?>
			</button>
		</nav>
	</form>
</div>

<?php
include("foot.inc");
