<?php

$nocsrf = true;

require_once("guiconfig.inc");
require_once("openvpn.inc");

/* Handle AJAX */
if($_GET['action']) {
	if($_GET['action'] == "kill") {
		$port = $_GET['port'];
		$remipp = $_GET['remipp'];
		if (!empty($port) and !empty($remipp)) {
			$retval = kill_client($port, $remipp);
			echo htmlentities("|{$port}|{$remipp}|{$retval}|");
		} else {
			echo gettext("invalid input");
		}
		exit;
	}
}


function kill_client($port, $remipp) {
	global $g;

	//$tcpsrv = "tcp://127.0.0.1:{$port}";
	$tcpsrv = "unix://{$g['varetc_path']}/openvpn/{$port}.sock";
	$errval;
	$errstr;

	/* open a tcp connection to the management port of each server */
	$fp = @stream_socket_client($tcpsrv, $errval, $errstr, 1);
	$killed = -1;
	if ($fp) {
		stream_set_timeout($fp, 1);
		fputs($fp, "kill {$remipp}\n");
		while (!feof($fp)) {
			$line = fgets($fp, 1024);

			$info = stream_get_meta_data($fp);
			if ($info['timed_out'])
				break;

			/* parse header list line */
			if (strpos($line, "INFO:") !== false)
				continue;
			if (strpos($line, "SUCCESS") !== false) {
				$killed = 0;
			}
			break;
		}
		fclose($fp);
	}
	return $killed;
}

$servers = openvpn_get_active_servers();
$sk_servers = openvpn_get_active_servers("p2p");
$clients = openvpn_get_active_clients();
?>

<br/>
<script type="text/javascript">
	function killClient(mport, remipp) {
		var busy = function(index,icon) {
			jQuery(icon).bind("onclick","");
			jQuery(icon).attr('src',jQuery(icon).attr('src').replace("\.gif", "_d.gif"));
			jQuery(icon).css("cursor","wait");
		}

		jQuery('img[name="i:' + mport + ":" + remipp + '"]').each(busy);

		jQuery.ajax(
			"<?=$_SERVER['SCRIPT_NAME'];?>" +
				"?action=kill&port=" + mport + "&remipp=" + remipp,
			{ type: "get", complete: killComplete }
		);
	}

	function killComplete(req) {
		var values = req.responseText.split("|");
		if(values[3] != "0") {
			alert('<?=gettext("An error occurred.");?>' + ' (' + values[3] + ')');
			return;
		}

		jQuery('tr[name="r:' + values[1] + ":" + values[2] + '"]').each(
			function(index,row) { jQuery(row).fadeOut(1000); }
		);
	}
</script>

<?php foreach ($servers as $server): ?>

<table style="padding-top:0px; padding-bottom:0px; padding-left:0px; padding-right:0px" width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td colspan="6" class="listtopic">
			<?=$server['name'];?> Client connections
		</td>
	</tr>
	<tr>
		<td>
			<table style="padding-top:0px; padding-bottom:0px; padding-left:0px; padding-right:0px" class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0" sortableMultirow="2">
			<tr>
				<td class="listhdrr">Name/Time</td>
				<td class="listhdrr">Real/Virtual IP</td>
			</tr>
			<?php $rowIndex = 0;
			foreach ($server['conns'] as $conn): 
			$evenRowClass = $rowIndex % 2 ? " listMReven" : " listMRodd";
			$rowIndex++;			
			?>
			<tr name='<?php echo "r:{$server['mgmt']}:{$conn['remote_host']}"; ?>' class="<?=$evenRowClass?>">
				<td class="listMRlr">
					<?=$conn['common_name'];?>
				</td>
				<td class="listMRr">
					<?=$conn['remote_host'];?>
				</td>
				<td class='listMR' rowspan="2">
					<img src='/themes/<?php echo $g['theme']; ?>/images/icons/icon_x.gif' height='17' width='17' border='0'
					   onclick="killClient('<?php echo $server['mgmt']; ?>', '<?php echo $conn['remote_host']; ?>');" style='cursor:pointer;'
					   name='<?php echo "i:{$server['mgmt']}:{$conn['remote_host']}"; ?>'
					   title='Kill client connection from <?php echo $conn['remote_host']; ?>' alt='' />
				</td>
			</tr>
			<tr name='<?php echo "r:{$server['mgmt']}:{$conn['remote_host']}"; ?>' class="<?=$evenRowClass?>">
				<td class="listMRlr">
					<?=$conn['connect_time'];?>
				</td>
				<td class="listMRr">
					<?=$conn['virtual_addr'];?>
				</td>
			</tr>

			<?php endforeach; ?>
			<tfoot>
			<tr>
				<td colspan="6" class="list" height="12"></td>
			</tr>
			</tfoot>
		</table>
		</td>
	</tr>
</table>

<?php endforeach; ?>
<?php if (!empty($sk_servers)) { ?>
<table style="padding-top:0px; padding-bottom:0px; padding-left:0px; padding-right:0px" width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td colspan="6" class="listtopic">
			Peer to Peer Server Instance Statistics
		</td>
	</tr>
	<tr>
		<table style="padding-top:0px; padding-bottom:0px; padding-left:0px; padding-right:0px" class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr>
			<td class="listhdrr">Name/Time</td>
			<td class="listhdrr">Remote/Virtual IP</td>
		</tr>

<?php foreach ($sk_servers as $sk_server): ?>
		<tr name='<?php echo "r:{$sk_server['port']}:{$sk_server['remote_host']}"; ?>'>
			<td class="listlr">
				<?=$sk_server['name'];?>
			</td>
			<td class="listr">
				<?=$sk_server['remote_host'];?>
			</td>
			<td rowspan="2" align="center">
			<?php
			if ($sk_server['status'] == "up") {
				/* tunnel is up */
				$iconfn = "interface_up";
			} else {
				/* tunnel is down */
				$iconfn = "interface_down";
			}
			echo "<img src ='/themes/{$g['theme']}/images/icons/icon_{$iconfn}.gif'>";
			?>
			</td>
		</tr>
		<tr name='<?php echo "r:{$sk_server['port']}:{$sk_server['remote_host']}"; ?>'>
			<td class="listlr">
				<?=$sk_server['connect_time'];?>
			</td>
			<td class="listr">
				<?=$sk_server['virtual_addr'];?>
			</td>
		</tr>
<?php endforeach; ?>
		</table>
	</tr>
</table>

<?php
} ?>
<?php if (!empty($clients)) { ?>
<table style="padding-top:0px; padding-bottom:0px; padding-left:0px; padding-right:0px" width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td colspan="6" class="listtopic">
			Client Instance Statistics
		</td>
	</tr>
	<tr>
		<table style="padding-top:0px; padding-bottom:0px; padding-left:0px; padding-right:0px" class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr>
			<td class="listhdrr">Name/Time</td>
			<td class="listhdrr">Remote/Virtual IP</td>
		</tr>

<?php foreach ($clients as $client): ?>
		<tr name='<?php echo "r:{$client['port']}:{$client['remote_host']}"; ?>'>
			<td class="listlr">
				<?=$client['name'];?>
			</td>
			<td class="listr">
				<?=$client['remote_host'];?>
			</td>
			<td rowspan="2" align="center">
			<?php
			if ($client['status'] == "up") {
				/* tunnel is up */
				$iconfn = "interface_up";
			} else {
				/* tunnel is down */
				$iconfn = "interface_down";
			}
			echo "<img src ='/themes/{$g['theme']}/images/icons/icon_{$iconfn}.gif'>";
			?>
			</td>
		</tr>
		<tr name='<?php echo "r:{$client['port']}:{$client['remote_host']}"; ?>'>
			<td class="listlr">
				<?=$client['connect_time'];?>
			</td>
			<td class="listr">
				<?=$client['virtual_addr'];?>
			</td>
		</tr>
<?php endforeach; ?>
		</table>
	</tr>
</table>

<?php
}

if ($DisplayNote) {
	echo "<br/><b>NOTE:</b> You need to bind each OpenVPN client to enable its management daemon: use 'Local port' setting in the OpenVPN client screen";
}

if ((empty($clients)) && (empty($servers)) && (empty($sk_servers))) {
	echo "No OpenVPN instance defined";
}
?>
