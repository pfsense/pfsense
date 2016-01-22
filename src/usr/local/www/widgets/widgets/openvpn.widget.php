<?php
/*
	openvpn.widget.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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

		jQuery.ajax(
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

		jQuery('tr[name="r:' + values[1] + ":" + values[2] + '"]').each(
			function(index,row) { jQuery(row).fadeOut(1000); }
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
			<?php $rowIndex = 0;
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
						<i class="fa fa-times-circle" onclick="killClient('<?=$server['mgmt']; ?>', '<?=$conn['remote_host']; ?>');" style="cursor:pointer;"
							name="<?php echo "i:{$server['mgmt']}:{$conn['remote_host']}"; ?>"
							title=<?=sprintf(gettext('Kill client connection from %s'), $conn['remote_host']);?>'>
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
		<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<?php endforeach; ?>
<?php if (!empty($sk_servers)) { ?>
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
<?php foreach ($sk_servers as $sk_server): ?>
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
						echo '<i class="fa fa-arrow-up"></i>';
					} else {
						/* tunnel is down */
						echo '<i class="fa fa-arrow-down"></i>';
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
<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<?php
} ?>
<?php if (!empty($clients)) { ?>
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
	<?php foreach ($clients as $client): ?>
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
					echo '<i class="fa fa-arrow-up"></i>';
				} else {
					/* tunnel is down */
					echo '<i class="fa fa-arrow-down"></i>';
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
	<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<?php
}

if ($DisplayNote) {
	echo "<br /><b>". gettext("NOTE") . ":</b> ". gettext("You need to bind each OpenVPN client to enable its management daemon: use 'Local port' setting in the OpenVPN client screen");
}

if ((empty($clients)) && (empty($servers)) && (empty($sk_servers))) {
	echo gettext("No OpenVPN instances defined");
}
?>
</div>
