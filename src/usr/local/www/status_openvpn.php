<?php
/*
 * status_openvpn.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008 Shrew Soft Inc.
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
##|*IDENT=page-status-openvpn
##|*NAME=Status: OpenVPN
##|*DESCR=Allow access to the 'Status: OpenVPN' page.
##|*MATCH=status_openvpn.php*
##|-PRIV

$pgtitle = array(gettext("Status"), gettext("OpenVPN"));
$shortcut_section = "openvpn";

require_once("guiconfig.inc");
require_once("openvpn.inc");
require_once("shortcuts.inc");
require_once("service-utils.inc");

/* Handle AJAX */
if ($_REQUEST['action']) {
	if ($_REQUEST['action'] == "kill") {
		$port  = $_REQUEST['port'];
		$remipp  = $_REQUEST['remipp'];
		if (!empty($port) and !empty($remipp)) {
			$retval = openvpn_kill_client($port, $remipp);
			echo htmlentities("|{$port}|{$remipp}|{$retval}|");
		} else {
			echo gettext("invalid input");
		}
		exit;
	}
}

$servers = openvpn_get_active_servers();
$sk_servers = openvpn_get_active_servers("p2p");
$clients = openvpn_get_active_clients();

include("head.inc"); ?>

<form action="status_openvpn.php" method="get" name="iform">
<script type="text/javascript">
//<![CDATA[
	function killClient(mport, remipp) {
		var busy = function(index,icon) {
			$(icon).bind("onclick","");
			$(icon).attr('src',$(icon).attr('src').replace("\.gif", "_d.gif"));
			$(icon).css("cursor","wait");
		}

		$('img[name="i:' + mport + ":" + remipp + '"]').each(busy);

		$.ajax(
			"<?=$_SERVER['SCRIPT_NAME'];?>" +
				"?action=kill&port=" + mport + "&remipp=" + remipp,
			{ type: "get", complete: killComplete }
		);
	}

	function killComplete(req) {
		var values = req.responseText.split("|");
		if (values[3] != "0") {
	//		alert('<?=gettext("An error occurred.");?>' + ' (' + values[3] + ')');
			return;
		}

		$('tr[id="r:' + values[1] + ":" + values[2] + '"]').each(
			function(index,row) { $(row).fadeOut(1000); }
		);
	}
//]]>
</script>

<?php
	$i = 0;
	foreach ($servers as $server):
?>

<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=htmlspecialchars($server['name']);?> <?=gettext('Client Connections') . ": " . ($server['conns'][0]['common_name'] != '[error]' ? sizeof($server['conns']) : '0');?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th><?=gettext("Common Name")?></th>
						<th><?=gettext("Real Address")?></th>
						<th><?=gettext("Virtual Address"); ?></th>
						<th><?=gettext("Connected Since"); ?></th>
						<th><?=gettext("Bytes Sent")?></th>
						<th><?=gettext("Bytes Received")?></th>
						<th><!-- Icons --></th>
					</tr>
				</thead>
				<tbody>

					<?php
							foreach ($server['conns'] as $conn):
					?>
					<tr id="<?php echo "r:{$server['mgmt']}:{$conn['remote_host']}"; ?>">
						<td>
							<?=$conn['common_name'];?>
					<?php if (!empty($conn['common_name']) && !empty($conn['user_name']) && ($conn['user_name'] != "UNDEF")): ?>
							<br />
					<?php endif; ?>
					<?php if (!empty($conn['user_name']) && ($conn['user_name'] != "UNDEF")): ?>
							<?=$conn['user_name'];?>
					<?php endif; ?>
						</td>
						<td><?=$conn['remote_host'];?></td>
						<td>
							<?=$conn['virtual_addr'];?>
					<?php if (!empty($conn['virtual_addr']) && !empty($conn['virtual_addr6'])): ?>
							<br />
					<?php endif; ?>
							<?=$conn['virtual_addr6'];?>
						</td>
						<td><?=$conn['connect_time'];?></td>
						<td data-value="<?=trim($conn['bytes_sent'])?>"><?=format_bytes($conn['bytes_sent']);?></td>
						<td data-value="<?=trim($conn['bytes_recv'])?>"><?=format_bytes($conn['bytes_recv']);?></td>
						<td>
							<a
							   onclick="killClient('<?=$server['mgmt'];?>', '<?=$conn['remote_host'];?>');" style="cursor:pointer;"
							   id="<?php echo "i:{$server['mgmt']}:{$conn['remote_host']}"; ?>"
							   title="<?php echo sprintf(gettext("Kill client connection from %s"), $conn['remote_host']); ?>">
							<i class="fa fa-times"></i>
							</a>
						</td>
					</tr>
					<?php
							endforeach;
					?>
				</tbody>
				<tfoot>
					<tr>
						<td colspan="2">
							<table>
								<tr>
										<?php $ssvc = find_service_by_openvpn_vpnid($server['vpnid']); ?>
									<td>
										<?= gettext("Status") . ": " . get_service_status_icon($ssvc, false, true, false, "service_state"); ?>
									</td>
									<td>
										<?= gettext("Actions") . ": " . get_service_control_links($ssvc); ?>
									</td>
								</tr>
							</table>
						</td>
						<td colspan="5">
						</td>
					</tr>
				</tfoot>
			</table>
		</div>
</div>
<?php
		if (is_array($server['routes']) && count($server['routes'])):
?>
<div id="shroutebut-<?= $i ?>">
	<button type="button" class="btn btn-info" onClick="show_routes('tabroute-<?= $i ?>','shroutebut-<?= $i ?>')" value="<?php echo gettext("Show Routing Table"); ?>">
		<i class="fa fa-plus-circle icon-embed-btn"></i>
		<?php echo gettext("Show Routing Table"); ?>
	</button>
	- <?= gettext("Display OpenVPN's internal routing table for this server.") ?>
	<br /><br />
</div>
<div class="panel panel-default" id="tabroute-<?=$i?>" style="display: none;">
		<div class="panel-heading"><h2 class="panel-title"><?=htmlspecialchars($server['name']);?> <?=gettext("Routing Table"); ?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th><?=gettext("Common Name"); ?></th>
						<th><?=gettext("Real Address"); ?></th>
						<th><?=gettext("Target Network"); ?></th>
						<th><?=gettext("Last Used"); ?></th>
					</tr>
				</thead>
				<tbody>

<?php
			foreach ($server['routes'] as $conn):
?>
					<tr id="<?php echo "r:{$server['mgmt']}:{$conn['remote_host']}"; ?>">
						<td><?=$conn['common_name'];?></td>
						<td><?=$conn['remote_host'];?></td>
						<td><?=$conn['virtual_addr'];?></td>
						<td><?=$conn['last_time'];?></td>
					</tr>
<?php
			endforeach;
?>
				</tbody>
				<tfoot>
					<tr>
						<td colspan="4"><?= gettext("An IP address followed by C indicates a host currently connected through the VPN.") ?></td>
					</tr>
				</tfoot>
			</table>
		</div>
</div>
<?php
		endif;
?>
<br />
<?php
		$i++;
	endforeach;
?>
<br />

<?php
	if (!empty($sk_servers)) {
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Peer to Peer Server Instance Statistics"); ?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th><?=gettext("Name"); ?></th>
						<th><?=gettext("Status"); ?></th>
						<th><?=gettext("Connected Since"); ?></th>
						<th><?=gettext("Virtual Address"); ?></th>
						<th><?=gettext("Remote Host"); ?></th>
						<th><?=gettext("Bytes Sent"); ?></th>
						<th><?=gettext("Bytes Received"); ?></th>
						<th><?=gettext("Service"); ?></th>
					</tr>
				</thead>
				<tbody>

<?php
		foreach ($sk_servers as $sk_server):
?>
					<tr id="<?php echo "r:{$sk_server['port']}:{$sk_server['vpnid']}"; ?>">
						<td><?=htmlspecialchars($sk_server['name']);?></td>
						<td><?=$sk_server['status'];?></td>
						<td><?=$sk_server['connect_time'];?></td>
						<td>
							<?=$sk_server['virtual_addr'];?>
					<?php if (!empty($sk_server['virtual_addr']) && !empty($sk_server['virtual_addr6'])): ?>
							<br />
					<?php endif; ?>
							<?=$sk_server['virtual_addr6'];?>
						</td>
						<td><?=$sk_server['remote_host'];?></td>
						<td data-value="<?=trim($sk_server['bytes_sent'])?>"><?=format_bytes($sk_server['bytes_sent']);?></td>
						<td data-value="<?=trim($sk_server['bytes_recv'])?>"><?=format_bytes($sk_server['bytes_recv']);?></td>
						<td>
							<table>
								<tr>
									<td>
										<?php $ssvc = find_service_by_openvpn_vpnid($sk_server['vpnid']); ?>
										<?= get_service_status_icon($ssvc, false, true); ?>
										<?= get_service_control_links($ssvc, true); ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
<?php
		endforeach;
?>
				</tbody>
			</table>
		</div>
</div>

<?php
	}
?>
<br />
<?php
	if (!empty($clients)) {
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Client Instance Statistics"); ?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th><?=gettext("Name"); ?></th>
						<th><?=gettext("Status"); ?></th>
						<th><?=gettext("Connected Since"); ?></th>
						<th><?=gettext("Local Address"); ?></th>
						<th><?=gettext("Virtual Address"); ?></th>
						<th><?=gettext("Remote Host"); ?></th>
						<th><?=gettext("Bytes Sent"); ?></th>
						<th><?=gettext("Bytes Received"); ?></th>
						<th><?=gettext("Service"); ?></th>
					</tr>
				</thead>
				<tbody>

<?php
		foreach ($clients as $client):
?>
					<tr id="<?php echo "r:{$client['port']}:{$client['vpnid']}"; ?>">
						<td><?=htmlspecialchars($client['name']);?></td>
						<td><?=$client['status'];?></td>
						<td><?=$client['connect_time'];?></td>
						<td>
					<?php if (empty($client['local_host']) && empty($client['local_port'])): ?>
							(pending)
					<?php else: ?>
							<?=$client['local_host'];?>:<?=$client['local_port'];?>
					<?php endif; ?>
						</td>
						<td>
							<?=$client['virtual_addr'];?>
					<?php if (!empty($client['virtual_addr']) && !empty($client['virtual_addr6'])): ?>
							<br />
					<?php endif; ?>
							<?=$client['virtual_addr6'];?>
						</td>
						<td>
					<?php if (empty($client['remote_host']) && empty($client['remote_port'])): ?>
							(pending)
					<?php else: ?>
							<?=$client['remote_host'];?>:<?=$client['remote_port'];?>
					<?php endif; ?>
						</td>
						<td data-value="<?=trim($client['bytes_sent'])?>"><?=format_bytes($client['bytes_sent']);?></td>
						<td data-value="<?=trim($client['bytes_recv'])?>"><?=format_bytes($client['bytes_recv']);?></td>
						<td>
							<table>
								<tr>
									<td>
										<?php $ssvc = find_service_by_openvpn_vpnid($client['vpnid']); ?>
										<?= get_service_status_icon($ssvc, false, true); ?>
										<?= get_service_control_links($ssvc, true); ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
<?php
		endforeach;
?>
				</tbody>
			</table>
		</div>
	</div>

<?php
}

if ($DisplayNote) {
 	print_info_box(gettext("If there are custom options that override the management features of OpenVPN on a client or server, they will cause that OpenVPN instance to not work correctly with this status page."));
}

if ((empty($clients)) && (empty($servers)) && (empty($sk_servers))) {
	print_info_box(gettext("No OpenVPN instances defined."));
}
?>
</form>

<script type="text/javascript">
//<![CDATA[

function show_routes(id, buttonid) {
	document.getElementById(buttonid).innerHTML='';
	aodiv = document.getElementById(id);
	aodiv.style.display = "block";
}

//]]>
</script>

<?php include("foot.inc"); ?>
