<?php
/*
	gateways.widget.php
	Copyright 2008 Seth Mos
	Part of pfSense widgets (https://www.pfsense.org)
	originally based on m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("/usr/local/www/widgets/include/gateways.inc");

if ($_POST) {
	if (!is_array($config["widgets"]["gateways_widget"])) {
		$config["widgets"]["gateways_widget"] = array();
	}
	if (isset($_POST["display_type"])) {
		$config["widgets"]["gateways_widget"]["display_type"] = $_POST["display_type"];
	}
	write_config("Updated gateways widget settings via dashboard.");
	header("Location: /");
	exit(0);
}

if (isset($config["widgets"]["gateways_widget"]["display_type"])) {
	$display_type = $config["widgets"]["gateways_widget"]["display_type"];
} else {
	$display_type = "gw_ip";
}

$a_gateways = return_gateways_array();
$gateways_status = array();
$gateways_status = return_gateways_status(true);

?>
<table class="table table-striped table-hover">
<thead>
<tr>
	<th>Name</td>
	<th>RTT</td>
	<th>Loss</td>
	<th>Status</td>
</tr>
</thead>
<tbody>
<?php foreach ($a_gateways as $gname => $gateway): ?>
	<tr>
		<td>
<?php
	$if_gw = '';
	if (is_ipaddr($gateway['gateway']))
		$if_gw = $gateway['gateway'];
	else {
		if($gateway['ipprotocol'] == "inet")
			$if_gw = get_interface_gateway($gateway['friendlyiface']);
		if($gateway['ipprotocol'] == "inet6")
			$if_gw = get_interface_gateway_v6($gateway['friendlyiface']);
	}
?>
			<?=htmlspecialchars($gateway['name'])?><br />
			<i><?=($if_gw == '' ? '~' : htmlspecialchars($if_gw));?></i>
		</td>
<?php
	if ($gateways_status[$gname]) {
		if (stristr($gateways_status[$gname]['status'], "force_down")) {
			$online = "Offline (forced)";
			$bgcolor = "#F08080";  // lightcoral
		} elseif (stristr($gateways_status[$gname]['status'], "down")) {
			$online = "Offline";
			$bgcolor = "#F08080";  // lightcoral
		} elseif (stristr($gateways_status[$gname]['status'], "loss")) {
			$online = "Packetloss";
			$bgcolor = "#F0E68C";  // khaki
		} elseif (stristr($gateways_status[$gname]['status'], "delay")) {
			$online = "Latency";
			$bgcolor = "#F0E68C";  // khaki
		} elseif ($gateways_status[$gname]['status'] == "none") {
			$online = "Online";
			$bgcolor = "#90EE90";  // lightgreen
		} elseif ($gateways_status[$gname]['status'] == "") {
			$online = "Pending";
			$bgcolor = "#D3D3D3";  // lightgray
		}
	} else {
		$online = gettext("Unknown");
		$bgcolor = "#ADD8E6";  // lightblue
	}
?>
		<td><?=($gateways_status[$gname] ? htmlspecialchars($gateways_status[$gname]['delay']) : gettext("Pending"))?></td>
		<td><?=($gateways_status[$gname] ? htmlspecialchars($gateways_status[$gname]['loss']) : gettext("Pending"))?></td>
		<td style="background-color: <?=$bgcolor?>"><?=$online?></td>
	</tr>
<?php endforeach; ?>
</tbody>
</table>