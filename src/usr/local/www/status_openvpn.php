<?php
/*
	status_openvpn.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2008 Shrew Soft Inc.
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-status-openvpn
##|*NAME=Status: OpenVPN
##|*DESCR=Allow access to the 'Status: OpenVPN' page.
##|*MATCH=status_openvpn.php*
##|-PRIV

$pgtitle = array(gettext("Status"), gettext("OpenVPN"));
$shortcut_section = "openvpn";

require("guiconfig.inc");
require_once("openvpn.inc");
require_once("shortcuts.inc");
require_once("service-utils.inc");

/* Handle AJAX */
if ($_GET['action']) {
	if ($_GET['action'] == "kill") {
		$port  = $_GET['port'];
		$remipp  = $_GET['remipp'];
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
			alert('<?=gettext("An error occurred.");?>' + ' (' + values[3] + ')');
			return;
		}

		$('tr[name="r:' + values[1] + ":" + values[2] + '"]').each(
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
		<div class="panel-heading"><h2 class="panel-title"><?=htmlspecialchars($server['name']);?> <?=gettext('Client Connections')?></h2></div>
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
						<td><?=$conn['common_name'];?></td>
						<td><?=$conn['remote_host'];?></td>
						<td><?=$conn['virtual_addr'];?></td>
						<td><?=$conn['connect_time'];?></td>
						<td><?=format_bytes($conn['bytes_sent']);?></td>
						<td><?=format_bytes($conn['bytes_recv']);?></td>
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
									<td>
										<?php $ssvc = find_service_by_openvpn_vpnid($server['vpnid']); ?>
										<?= get_service_status_icon($ssvc, true, true); ?>
										<?= get_service_control_links($ssvc); ?>
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
						<td><?=$sk_server['virtual_addr'];?></td>
						<td><?=$sk_server['remote_host'];?></td>
						<td><?=format_bytes($sk_server['bytes_sent']);?></td>
						<td><?=format_bytes($sk_server['bytes_recv']);?></td>
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
						<td><?=$client['virtual_addr'];?></td>
						<td><?=$client['remote_host'];?></td>
						<td><?=format_bytes($client['bytes_sent']);?></td>
						<td><?=format_bytes($client['bytes_recv']);?></td>
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
