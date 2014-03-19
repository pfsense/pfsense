<?php
/*
	status_ovpenvpn.php

    Copyright (C) 2010 Jim Pingle
    Copyright (C) 2008 Shrew Soft Inc.

    AJAX bits borrowed from diag_dump_states.php
    Copyright (C) 2005 Scott Ullrich, Colin Smith

    All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
/* DISABLE_PHP_LINT_CHECKING */
/*
	pfSense_MODULE:	openvpn
*/

##|+PRIV
##|*IDENT=page-status-openvpn
##|*NAME=Status: OpenVPN page
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
if($_GET['action']) {
	if($_GET['action'] == "kill") {
		$port  = $_GET['port'];
		$remipp  = $_GET['remipp'];
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

include("head.inc"); ?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" onload="<?=$jsevents["body"]["onload"];?>">
<?php include("fbegin.inc"); ?>
<form action="status_openvpn.php" method="get" name="iform">
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
<?php $i = 0; ?>
<?php foreach ($servers as $server): ?>

<table style="padding-top:0px; padding-bottom:0px; padding-left:0px; padding-right:0px" width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td colspan="6" class="listtopic">
			<?=$server['name'];?> <?=gettext("Client connections"); ?>
		</td>
	</tr>
	<tr>
		<td>
			<table style="padding-top:0px; padding-bottom:0px; padding-left:0px; padding-right:0px" class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
			<tr>
				<td class="listhdrr"><?=gettext("Common Name"); ?></td>
				<td class="listhdrr"><?=gettext("Real Address"); ?></td>
				<td class="listhdrr"><?=gettext("Virtual Address"); ?></td>
				<td class="listhdrr"><?=gettext("Connected Since"); ?></td>
				<td class="listhdrr"><?=gettext("Bytes Sent"); ?></td>
				<td class="listhdrr"><?=gettext("Bytes Received"); ?></td>
			</tr>

			<?php foreach ($server['conns'] as $conn): ?>
			<tr name='<?php echo "r:{$server['mgmt']}:{$conn['remote_host']}"; ?>'>
				<td class="listlr">
					<?=$conn['common_name'];?>
				</td>
				<td class="listr">
					<?=$conn['remote_host'];?>
				</td>
				<td class="listr">
					<?=$conn['virtual_addr'];?>
				</td>
				<td class="listr">
					<?=$conn['connect_time'];?>
				</td>
				<td class="listr">
					<?=$conn['bytes_sent'];?>
				</td>
				<td class="listr">
					<?=$conn['bytes_recv'];?>
				</td>
				<td class='list'>
					<img src='/themes/<?php echo $g['theme']; ?>/images/icons/icon_x.gif' height='17' width='17' border='0'
					   onclick="killClient('<?php echo $server['mgmt']; ?>', '<?php echo $conn['remote_host']; ?>');" style='cursor:pointer;'
					   name='<?php echo "i:{$server['mgmt']}:{$conn['remote_host']}"; ?>'
					   title='<?php echo gettext("Kill client connection from") . ' ' . $conn['remote_host']; ?>' alt='' />
				</td>
			</tr>

			<?php endforeach; ?>
			<tfoot>
			<tr>
				<td colspan="2" class="list" height="12">
				<table>
				<tr>
				<?php $ssvc = find_service_by_openvpn_vpnid($server['vpnid']); ?>
				<?= get_service_status_icon($ssvc, true, true); ?>
				<td><?= get_service_control_links($ssvc, true); ?></td>
				</tr>
				</table>
				</td>
				<td colspan="4" class="list" height="12">&nbsp;</td>
			</tr>
			</tfoot>
		</table>
		</td>
	</tr>
</table>
<?php if (is_array($server['routes']) && count($server['routes'])): ?>
<div id="shroutebut-<?= $i ?>">
<input type="button" onClick="show_routes('tabroute-<?= $i ?>','shroutebut-<?= $i ?>')" value="<?php echo gettext("Show Routing Table"); ?>" /> - <?= gettext("Display OpenVPN's internal routing table for this server.") ?></a>
<br /><br />
</div>
<table style="display: none; padding-top:0px; padding-bottom:0px; padding-left:0px; padding-right:0px" width="100%" border="0" cellpadding="0" cellspacing="0" id="tabroute-<?= $i ?>">
	<tr>
		<td colspan="6" class="listtopic">
			<?=$server['name'];?> <?=gettext("Routing Table"); ?>
		</td>
	</tr>
	<tr>
		<td>
			<table style="padding-top:0px; padding-bottom:0px; padding-left:0px; padding-right:0px" class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
			<tr>
				<td class="listhdrr"><?=gettext("Common Name"); ?></td>
				<td class="listhdrr"><?=gettext("Real Address"); ?></td>
				<td class="listhdrr"><?=gettext("Target Network"); ?></td>
				<td class="listhdrr"><?=gettext("Last Used"); ?></td>
			</tr>

			<?php foreach ($server['routes'] as $conn): ?>
			<tr name='<?php echo "r:{$server['mgmt']}:{$conn['remote_host']}"; ?>'>
				<td class="listlr">
					<?=$conn['common_name'];?>
				</td>
				<td class="listr">
					<?=$conn['remote_host'];?>
				</td>
				<td class="listr">
					<?=$conn['virtual_addr'];?>
				</td>
				<td class="listr">
					<?=$conn['last_time'];?>
				</td>
			</tr>

			<?php endforeach; ?>
			<tfoot>
			<tr>
				<td colspan="6" class="list" height="12"><?= gettext("An IP address followed by C indicates a host currently connected through the VPN.") ?></td>
			</tr>
			</tfoot>
		</table>
		</td>
	</tr>
</table>
<?php endif; ?>
<br />
<?php $i++; ?>
<?php endforeach; ?>
<br />

<?php if (!empty($sk_servers)) { ?>
<table style="padding-top:0px; padding-bottom:0px; padding-left:0px; padding-right:0px" width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td colspan="6" class="listtopic">
			<?=gettext("Peer to Peer Server Instance Statistics"); ?>
		</td>
	</tr>
	<tr>
		<td>
			<table style="padding-top:0px; padding-bottom:0px; padding-left:0px; padding-right:0px" class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
			<tr>
				<td class="listhdrr"><?=gettext("Name"); ?></td>
				<td class="listhdrr"><?=gettext("Status"); ?></td>
				<td class="listhdrr"><?=gettext("Connected Since"); ?></td>
				<td class="listhdrr"><?=gettext("Virtual Addr"); ?></td>
				<td class="listhdrr"><?=gettext("Remote Host"); ?></td>
				<td class="listhdrr"><?=gettext("Bytes Sent"); ?></td>
				<td class="listhdrr"><?=gettext("Bytes Rcvd"); ?></td>
				<td class="listhdrr"><?=gettext("Service"); ?></td>
			</tr>

<?php foreach ($sk_servers as $sk_server): ?>
			<tr name='<?php echo "r:{$sk_server['port']}:{$sk_server['vpnid']}"; ?>'>
				<td class="listlr">
					<?=$sk_server['name'];?>
				</td>
				<td class="listr">
					<?=$sk_server['status'];?>
				</td>
				<td class="listr">
					<?=$sk_server['connect_time'];?>
				</td>
				<td class="listr">
					<?=$sk_server['virtual_addr'];?>
				</td>
				<td class="listr">
					<?=$sk_server['remote_host'];?>
				</td>
				<td class="listr">
					<?=$sk_server['bytes_sent'];?>
				</td>
				<td class="listr">
					<?=$sk_server['bytes_recv'];?>
				</td>
				<td class="listr">
				<table>
				<tr>
				<?php $ssvc = find_service_by_openvpn_vpnid($sk_server['vpnid']); ?>
				<?= get_service_status_icon($ssvc, false, true); ?>
				<td><?= get_service_control_links($ssvc, true); ?></td>
				</tr>
				</table>
				</td>
			</tr>
<?php endforeach; ?>
			</table>
		</td>
	</tr>
</table>

<?php
} ?>
<br />
<?php if (!empty($clients)) { ?>
<table style="padding-top:0px; padding-bottom:0px; padding-left:0px; padding-right:0px" width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td colspan="6" class="listtopic">
			<?=gettext("Client Instance Statistics"); ?>
		</td>
	</tr>
	<tr>
		<td>
			<table style="padding-top:0px; padding-bottom:0px; padding-left:0px; padding-right:0px" class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
			<tr>
				<td class="listhdrr"><?=gettext("Name"); ?></td>
				<td class="listhdrr"><?=gettext("Status"); ?></td>
				<td class="listhdrr"><?=gettext("Connected Since"); ?></td>
				<td class="listhdrr"><?=gettext("Virtual Addr"); ?></td>
				<td class="listhdrr"><?=gettext("Remote Host"); ?></td>
				<td class="listhdrr"><?=gettext("Bytes Sent"); ?></td>
				<td class="listhdrr"><?=gettext("Bytes Rcvd"); ?></td>
				<td class="listhdrr"><?=gettext("Service"); ?></td>
			</tr>

<?php foreach ($clients as $client): ?>
			<tr name='<?php echo "r:{$client['port']}:{$client['vpnid']}"; ?>'>
				<td class="listlr">
					<?=$client['name'];?>
				</td>
				<td class="listr">
					<?=$client['status'];?>
				</td>
				<td class="listr">
					<?=$client['connect_time'];?>
				</td>
				<td class="listr">
					<?=$client['virtual_addr'];?>
				</td>
				<td class="listr">
					<?=$client['remote_host'];?>
				</td>
				<td class="listr">
					<?=$client['bytes_sent'];?>
				</td>
				<td class="listr">
					<?=$client['bytes_recv'];?>
				</td>
				<td class="listr" height="12">
				<table>
				<tr>
				<?php $ssvc = find_service_by_openvpn_vpnid($client['vpnid']); ?>
				<?= get_service_status_icon($ssvc, false, true); ?>
				<td><?= get_service_control_links($ssvc, true); ?></td>
				</tr>
				</table>
				</td>
			</tr>
<?php endforeach; ?>
			</table>
		</td>
	</tr>
</table>

<?php 
}

if ($DisplayNote) {
	echo "<br /><b>" . gettext("NOTE") . ":</b> " . gettext("If you have custom options that override the management features of OpenVPN on a client or server, they will cause that OpenVPN instance to not work correctly with this status page.");
}

if ((empty($clients)) && (empty($servers)) && (empty($sk_servers))) {
	echo gettext("No OpenVPN instance defined");
}
?>


<?php include("fend.inc"); ?>
<script type="text/javascript">
function show_routes(id, buttonid) {
	document.getElementById(buttonid).innerHTML='';
	aodiv = document.getElementById(id);
	aodiv.style.display = "block";
}
</script>
