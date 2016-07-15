<?php
/*
 * openvpn.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
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
require_once("openvpn.inc");

/* Handle AJAX */
if ($_GET['action']) {
	if ($_GET['action'] == "kill") {
		$port = $_GET['port'];
		$remipp = $_GET['remipp'];
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
?>

<script type="text/javascript">
//<![CDATA[
	function killClient(mport, remipp) {

		$.ajax(
			"widgets/widgets/openvpn.widget.php" +
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
<div class="content">
<?php foreach ($servers as $server): ?>

<div class="widget panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=htmlspecialchars($server['name']);?></h2></div>
	<div class="table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><?=gettext('Name/Time')?></th>
					<th><?=gettext('Real/Virtual IP')?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
<?php
			$rowIndex = 0;
			foreach ($server['conns'] as $conn):
				$evenRowClass = $rowIndex % 2 ? " listMReven" : " listMRodd";
				$rowIndex++;
?>
				<tr name="<?php echo "r:{$server['mgmt']}:{$conn['remote_host']}"; ?>" class="<?=$evenRowClass?>">
					<td>
						<?=$conn['common_name'];?>
					</td>
					<td>
						<?=$conn['remote_host'];?>
					</td>
					<td>
						<i class="fa fa-times-circle"
							onclick="killClient('<?=$server['mgmt']; ?>', '<?=$conn['remote_host']; ?>');"
							style="cursor:pointer;"
							name="<?php echo "i:{$server['mgmt']}:{$conn['remote_host']}"; ?>"
							title=<?=sprintf(gettext('Kill client connection from %s'), $conn['remote_host']);?>>
						</i>
					</td>
				</tr>
				<tr name="<?php echo "r:{$server['mgmt']}:{$conn['remote_host']}"; ?>" class="<?=$evenRowClass?>">
					<td>
						<?=$conn['connect_time'];?>
					</td>
					<td>
						<?=$conn['virtual_addr'];?>
					</td>
					<td></td>
				</tr>
<?php
			endforeach;
?>
			</tbody>
		</table>
	</div>
</div>

<?php
endforeach;

if (!empty($sk_servers)):
?>
<div class="widget panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Peer to Peer Server Instance Statistics");?></h2></div>
	<div class="table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><?=gettext('Name/Time')?></th>
					<th><?=gettext('Remote/Virtual IP')?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
<?php
			foreach ($sk_servers as $sk_server):
?>
				<tr name='<?php echo "r:{$sk_server['port']}:{$sk_server['remote_host']}"; ?>'>
					<td>
						<?=$sk_server['name'];?>
					</td>
					<td>
						<?=$sk_server['remote_host'];?>
					</td>
					<td>
<?php
				if ($sk_server['status'] == "up") {
					/* tunnel is up */
					echo '<i class="fa fa-arrow-up text-success"></i>';
				} else {
					/* tunnel is down */
					echo '<i class="fa fa-arrow-down text-danger"></i>';
				}
?>
					</td>
				</tr>
				<tr name="<?php echo "r:{$sk_server['port']}:{$sk_server['remote_host']}"; ?>">
					<td>
						<?=$sk_server['connect_time'];?>
					</td>
					<td>
						<?=$sk_server['virtual_addr'];?>
					</td>
					<td></td>
				</tr>
<?php
			endforeach;
?>
			</tbody>
		</table>
	</div>
</div>

<?php
endif;

if (!empty($clients)):
?>
<div class="widget panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Client Instance Statistics");?></h2></div>
	<div class="table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><?=gettext('Name/Time')?></th>
					<th><?=gettext('Remote/Virtual IP')?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
<?php
			foreach ($clients as $client):
?>
				<tr name="<?php echo "r:{$client['port']}:{$client['remote_host']}"; ?>">
					<td>
						<?=$client['name'];?>
					</td>
					<td>
					<?=$client['remote_host'];?>
					</td>
					<td>
<?php
				if ($client['status'] == "up") {
					/* tunnel is up */
					echo '<i class="fa fa-arrow-up text-success"></i>';
				} else {
					/* tunnel is down */
					echo '<i class="fa fa-arrow-down text-danger"></i>';
				}
?>
					</td>
				</tr>
				<tr name="<?php echo "r:{$client['port']}:{$client['remote_host']}"; ?>">
					<td>
						<?=$client['connect_time'];?>
					</td>
					<td>
						<?=$client['virtual_addr'];?>
					</td>
					<td></td>
				</tr>
<?php
			endforeach;
?>
			</tbody>
		</table>
	</div>
</div>

<?php
endif;

if ((empty($clients)) && (empty($servers)) && (empty($sk_servers))) {
	echo gettext("No OpenVPN instances defined");
}
?>
</div>
