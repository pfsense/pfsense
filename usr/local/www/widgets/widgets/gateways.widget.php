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

$counter = 1;

?>

<input type="hidden" id="gateways-config" name="gateways-config" value="" />

<div id="gateways-settings" class="widgetconfigdiv" style="display:none;">
<form action="/widgets/widgets/gateways.widget.php" method="post" name="gateways_widget_iform" id="gateways_widget_iform">
	Display:
		<?php 
			$display_type_gw_ip="checked=\"checked\"";
			$display_type_monitor_ip="";
			$display_type_both_ip="";
			if (isset($config["widgets"]["gateways_widget"]["display_type"])) {
				$selected_radio = $config["widgets"]["gateways_widget"]["display_type"];
				if ($selected_radio == "gw_ip") {
					$display_type_gw_ip = "checked=\"checked\"";
					$display_type_monitor_ip="";
					$display_type_both_ip="";
				} else if ($selected_radio == "monitor_ip") {
					$display_type_gw_ip = "";
					$display_type_monitor_ip="checked=\"checked\"";
					$display_type_both_ip="";
				} else if ($selected_radio == "both_ip") {
					$display_type_gw_ip = "";
					$display_type_monitor_ip="";
					$display_type_both_ip="checked=\"checked\"";
				}
			}
		?>
	<input name="display_type" class="radio" type="radio" id="display_type_gw_ip" value="gw_ip" <?php echo $display_type_gw_ip; ?> onchange="updateGatewayDisplays();" /> <span>Gateway IP</span>
	<input name="display_type" class="radio" type="radio" id="display_type_monitor_ip" value="monitor_ip" <?php echo $display_type_monitor_ip; ?> onchange="updateGatewayDisplays();" /> <span>Monitor IP</span>
	<input name="display_type" class="radio" type="radio" id="display_type_both_ip" value="both_ip" <?php echo $display_type_both_ip; ?> onchange="updateGatewayDisplays();" /> <span>Both</span>
	<br /><br />
	<input id="submit_settings" name="submit_settings" type="submit" onclick="return updatePref();" class="formbtn" value="Save Settings" />
</form>
</div>

<script type="text/javascript">
//<![CDATA[
	d = document;
	selectIntLink = "gateways-configure";
	textlink = d.getElementById(selectIntLink);
	textlink.style.display = "inline";
//]]>
</script>

<table bgcolor="#990000" width="100%" border="0" cellspacing="0" cellpadding="0" summary="gateway status">
	<tr>
		<td class="listhdrr" id="gatewayname" align="center">Name</td>
		<td class="listhdrr" align="center">RTT</td>
		<td class="listhdrr" align="center">Loss</td>
		<td class="listhdrr" align="center">Status</td>
	</tr>
	<?php foreach ($a_gateways as $gname => $gateway) { ?>
	<tr>
	<td class="listhdrr" id="gateway<?php echo $counter; ?>" rowspan="2" align="center">
		<strong>
		<?php echo htmlspecialchars($gateway['name']); ?>
		</strong>
		<?php $counter++; ?>
	</td>
	<td colspan="3" class="listr ellipsis" align="center">
				<div id="gateway<?php echo $counter; ?>" style="display:inline"><b>
					<?php
						$monitor_address = "";
						$monitor_address_disp = "";
						if ($display_type == "monitor_ip" || $display_type == "both_ip") {
							$monitor_address = $gateway['monitor'];
							if ($monitor_address != "" && $display_type == "both_ip") {
								$monitor_address_disp = " (" . $monitor_address . ")";
							} else {
								$monitor_address_disp = $monitor_address;
							}
						}
						$if_gw = '';
						// If the user asked to display Gateway IP or both IPs, or asked for just monitor IP but the monitor IP is blank
						// then find the gateway IP (which is also the monitor IP if the monitor IP was not explicitly set).
						if ($display_type == "gw_ip" || $display_type == "both_ip" || ($display_type == "monitor_ip" && $monitor_address == "")) {
							if (is_ipaddr($gateway['gateway'])) {
								$if_gw = htmlspecialchars($gateway['gateway']);
							} else {
								if ($gateway['ipprotocol'] == "inet") {
									$if_gw = htmlspecialchars(get_interface_gateway($gateway['friendlyiface']));
								}
								if ($gateway['ipprotocol'] == "inet6") {
									$if_gw = htmlspecialchars(get_interface_gateway_v6($gateway['friendlyiface']));
								}
							}
							if ($if_gw == "") {
								$if_gw = "~";
							}
						}
						if ($monitor_address == $if_gw) {
							$monitor_address_disp = "";
						}
						echo $if_gw . $monitor_address_disp;
						unset ($if_gw);
						unset ($monitor_address);
						unset ($monitor_address_disp);
						$counter++;
					?>
				</b></div>
			</td>
	</tr>
	<tr>
			<td class="listr ellipsis" align="center" id="gateway<?php echo $counter; ?>">
			<?php
				if ($gateways_status[$gname]) {
					echo htmlspecialchars($gateways_status[$gname]['delay']);
				} else {
					echo gettext("Pending");
				}
			?>
			<?php $counter++; ?>
			</td>
			<td class="listr ellipsis" align="center" id="gateway<?php echo $counter; ?>">
			<?php
				if ($gateways_status[$gname]) {
					echo htmlspecialchars($gateways_status[$gname]['loss']);
				} else {
					echo gettext("Pending");
				}
			?>
			<?php $counter++; ?>
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
				echo "<td class=\"listr ellipsis\" align=\"center\" id=\"gateway$counter\">$online</td>\n";
				?>
				<td style="display:none;">
				<script type="text/javascript">
				//<![CDATA[
					jQuery('#gateway<?php echo $counter;?>').css('background-color',"<?php echo $bgcolor;?>");
				//]]>
				</script>
				</td>
				<?php
				$counter++;
			?>
	</tr>
	<?php } // foreach ?>
</table>
