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

$pgtitle = array("Status", "OpenVPN");
require("guiconfig.inc");
require_once("vpn.inc");

/* Handle AJAX */
if($_GET['action']) {
	if($_GET['action'] == "kill") {
		$port  = $_GET['port'];
		$remipp  = $_GET['remipp'];
		if (!empty($port) and !empty($remipp)) {
			$retval = kill_client($port, $remipp);
			echo htmlentities("|{$port}|{$remipp}|{$retval}|");
		} else {
			echo "invalid input";
		}
		exit;
	}
}


function kill_client($port, $remipp) {
	$tcpsrv = "tcp://127.0.0.1:{$port}";
	$errval;
	$errstr;

	/* open a tcp connection to the management port of each server */
	$fp = @stream_socket_client($tcpsrv, $errval, $errstr, 1);
	$killed = -1;
	if ($fp) {
		fputs($fp, "kill {$remipp}\n");
		while (!feof($fp)) {
			$line = fgets($fp, 1024);
			/* parse header list line */
			if (strpos($line, "INFO:"))
				continue;
			if (strpos($line, "UCCESS")) {
				$killed = 0;
			}
			break;
		}
		fclose($fp);
	}
	return $killed;
}

$servers = array();
$clients = array();

if (is_array($config['openvpn']['openvpn-server'])) {
	foreach ($config['openvpn']['openvpn-server'] as & $settings) {

		$prot = $settings['protocol'];
		$port = $settings['local_port'];

		$server = array();
		$server['port'] = $settings['local_port'];
		if ($settings['description'])
			$server['name'] = "{$settings['description']} {$prot}:{$port}";
		else
			$server['name'] = "Server {$prot}:{$port}";
		$server['conns'] = array();

		$tcpsrv = "tcp://127.0.0.1:{$port}";
		$errval;
		$errstr;

		/* open a tcp connection to the management port of each server */
		$fp = @stream_socket_client($tcpsrv, $errval, $errstr, 1);
		if ($fp) {

			/* send our status request */
			fputs($fp, "status 2\n");

			/* recv all response lines */
			while (!feof($fp)) {

				/* read the next line */
				$line = fgets($fp, 1024);

				/* parse header list line */
				if (strstr($line, "HEADER"))
					continue;

				/* parse end of output line */
				if (strstr($line, "END"))
					break;

				/* parse client list line */
				if (strstr($line, "CLIENT_LIST")) {
					$list = explode(",", $line);
					$conn = array();
					$conn['common_name'] = $list[1];
					$conn['remote_host'] = $list[2];
					$conn['virtual_addr'] = $list[3];
					$conn['bytes_recv'] = $list[4];
					$conn['bytes_sent'] = $list[5];
					$conn['connect_time'] = $list[6];
					$server['conns'][] = $conn;
				}
			}

			/* cleanup */
			fclose($fp);
		} else {
			$conn = array();
			$conn['common_name'] = "[error]";
			$conn['remote_host'] = "Management Daemon Unreachable";
			$conn['virtual_addr'] = "";
			$conn['bytes_recv'] = 0;
			$conn['bytes_sent'] = 0;
			$conn['connect_time'] = 0;
			$server['conns'][] = $conn;
		}

		$servers[] = $server;
	}
}


if (is_array($config['openvpn']['openvpn-client'])) {
	foreach ($config['openvpn']['openvpn-client'] as & $settings) {

		$prot = $settings['protocol'];
		$port = $settings['local_port'];

		$client = array();
		$client['port'] = $settings['local_port'];
		if ($settings['description'])
			$client['name'] = "{$settings['description']} {$prot}:{$port}";
		else
			$client['name'] = "Client {$prot}:{$port}";

		$tcpcli = "tcp://127.0.0.1:{$port}";
		$errval;
		$errstr;

		$client['status']="down";

		/* open a tcp connection to the management port of each cli */
		$fp = @stream_socket_client($tcpcli, $errval, $errstr, 1);
		if ($fp) {

			/* send our status request */
			fputs($fp, "state 1\n");

			/* recv all response lines */
			while (!feof($fp)) {
				/* read the next line */
				$line = fgets($fp, 1024);

				/* Get the client state */
				if (strstr($line,"CONNECTED")) {
					$client['status']="up";
					$list = explode(",", $line);

					$client['connect_time']  = date("D M j G:i:s Y", $list[0]);
					$client['virtual_addr']  = $list[3];
					$client['remote_host'] = $list[4];
				}
				/* parse end of output line */
				if (strstr($line, "END"))
					break;
			}

			/* If up, get read/write stats */
			if (strcmp($client['status'], "up") == 0) {
				fputs($fp, "status 2\n");
				/* recv all response lines */
				while (!feof($fp)) {
					/* read the next line */
					$line = fgets($fp, 1024);

					if (strstr($line,"TCP/UDP read bytes")) {
						$list = explode(",", $line);
						$client['bytes_recv'] = $list[1];
					}

					if (strstr($line,"TCP/UDP write bytes")) {
						$list = explode(",", $line);
						$client['bytes_sent'] = $list[1];
					}

					/* parse end of output line */
					if (strstr($line, "END"))
						break;
				}
			}

			fclose($fp);

		} else {
			$DisplayNote=true;
			$client['remote_host'] = "No Management Daemon";
			$client['virtual_addr'] = "See Note Below";
			$client['bytes_recv'] = 0;
			$client['bytes_sent'] = 0;
			$client['connect_time'] = 0;
		}

		$clients[] = $client;
	}
}
include("head.inc"); ?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" onload="<?=$jsevents["body"]["onload"];?>">
<script src="/javascript/sorttable.js" type="text/javascript"></script>
<?php include("fbegin.inc"); ?>
<form action="status_openvpn.php" method="get" name="iform">
<script type="text/javascript">
	function killClient(mport, remipp) {
		var busy = function(icon) {
			icon.onclick      = "";
			icon.src          = icon.src.replace("\.gif", "_d.gif");
			icon.style.cursor = "wait";
		}

		$A(document.getElementsByName("i:" + mport + ":" + remipp)).each(busy);

		new Ajax.Request(
			"<?=$_SERVER['SCRIPT_NAME'];?>" +
				"?action=kill&port=" + mport + "&remipp=" + remipp,
			{ method: "get", onComplete: killComplete }
		);
	}

	function killComplete(req) {
		var values = req.responseText.split("|");
		if(values[3] != "0") {
			alert('<?=gettext("An error occurred.");?>' + ' (' + values[3] + ')');
			return;
		}

		$A(document.getElementsByName("r:" + values[1] + ":" + values[2])).each(
			function(row) { Effect.Fade(row, { duration: 1.0 }); }
		);
	}
</script>

<?php foreach ($servers as $server): ?>

<table style="padding-top:0px; padding-bottom:0px; padding-left:0px; padding-right:0px" width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td colspan="6" class="listtopic">
			Client connections for <?=$server['name'];?>
		</td>
	</tr>
	<tr>
		<td>
			<table style="padding-top:0px; padding-bottom:0px; padding-left:0px; padding-right:0px" class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
			<tr>
				<td class="listhdrr">Common Name</td>
				<td class="listhdrr">Real Address</td>
				<td class="listhdrr">Virtual Address</td>
				<td class="listhdrr">Connected Since</td>
				<td class="listhdrr">Bytes Sent</td>
				<td class="listhdrr">Bytes Received</td>
			</tr>

			<?php foreach ($server['conns'] as $conn): ?>
			<tr name='<?php echo "r:{$server['port']}:{$conn['remote_host']}"; ?>'>
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
					   onclick="killClient('<?php echo $server['port']; ?>', '<?php echo $conn['remote_host']; ?>');" style='cursor:pointer;'
					   name='<?php echo "i:{$server['port']}:{$conn['remote_host']}"; ?>'
					   title='Kill client connection from <?php echo $conn['remote_host']; ?>' alt='' />
				</td>
			</tr>

			<?php endforeach; ?>
			<tr>
				<td colspan="6" class="list" height="12"></td>
			</tr>

		</table>
		</td>
	</tr>
</table>

<?php endforeach; ?>
<br>


<?php if (!empty($clients)) { ?>
<table style="padding-top:0px; padding-bottom:0px; padding-left:0px; padding-right:0px" width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td colspan="6" class="listtopic">
			OpenVPN client instances statistics
		</td>
	</tr>
	<tr>
		<table style="padding-top:0px; padding-bottom:0px; padding-left:0px; padding-right:0px" class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr>
			<td class="listhdrr">Name</td>
			<td class="listhdrr">Status</td>
			<td class="listhdrr">Connected Since</td>
			<td class="listhdrr">Virtual Addr</td>
			<td class="listhdrr">Remote Host</td>
			<td class="listhdrr">Bytes Sent</td>
			<td class="listhdrr">Bytes Received</td>
		</tr>

<?php foreach ($clients as $client): ?>
		<tr name='<?php echo "r:{$client['port']}:{$conn['remote_host']}"; ?>'>
			<td class="listlr">
				<?=$client['name'];?>
			</td>
			<td class="listlr">
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

if ((empty($clients)) && (empty($servers))) {
	echo "No OpenVPN instance defined";
}
?>


<?php include("fend.inc"); ?>
