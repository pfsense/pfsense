<?php 
/*
	status_ovpenvpn.php

    Copyright (C) 2008 Shrew Soft Inc.
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

##|+PRIV
##|*IDENT=page-status-openvpn
##|*NAME=Status: OpenVPN page
##|*DESCR=Allow access to the 'Status: OpenVPN' page.
##|*MATCH=status_openvpn.php*
##|-PRIV


$pgtitle = array("Status", "OpenVPN");
require("guiconfig.inc");

$servers = array();

if (is_array($config['openvpn']['openvpn-server'])) {
	foreach ($config['openvpn']['openvpn-server'] as & $settings) {

		$prot = $settings['protocol'];
		$port = $settings['local_port'];

		$server = array();
		if ($settings['description'])
			$server['name'] = "{$settings['description']} {$prot}:{$port}";
		else
			$server['name'] = "Server {$prot}:{$port}";
		$server['conns'] = array();

		$tcpsrv = "tcp://127.0.0.1:{$port}";
		$errval;
		$errstr;

		/* open a tcp connection to the management port of each server */
		$fp = stream_socket_client($tcpsrv, $errval, $errstr, 1);
		if ($fp) {

			/* send our status request */
			fputs($fp, "status 2\n");

			/* recv all response lines */
			$buff = "";
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
		}

		$servers[] = $server;
	}
}

include("head.inc");
include("fbegin.inc");

echo $buff;

?>
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
				<tr>
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

<?php include("fend.inc"); ?>
