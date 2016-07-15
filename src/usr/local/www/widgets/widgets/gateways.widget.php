<?php
/*
 * gateways.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008 Seth Mos
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("/usr/local/www/widgets/include/gateways.inc");

// Compose the table contents and pass it back to the ajax caller
if ($_REQUEST && $_REQUEST['ajax']) {
	print(compose_table_body_contents());
	exit;
}

if ($_POST) {
	if (!is_array($config["widgets"]["gateways_widget"])) {
		$config["widgets"]["gateways_widget"] = array();
	}
	if (isset($_POST["display_type"])) {
		$config["widgets"]["gateways_widget"]["display_type"] = $_POST["display_type"];
	}
	write_config(gettext("Updated gateways widget settings via dashboard."));
	header("Location: /");
	exit(0);
}
?>

<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
		<thead>
			<tr>
				<th><?=gettext("Name")?></th>
				<th>RTT</th>
				<th>RTTsd</th>
				<th><?=gettext("Loss")?></th>
				<th><?=gettext("Status")?></th>
			</tr>
		</thead>
		<tbody id="gwtblbody">
<?php
		print(compose_table_body_contents());
?>
		</tbody>
	</table>
</div>
<!-- close the body we're wrapped in and add a configuration-panel -->
</div>

<div id="widget-<?=$widgetname?>_panel-footer" class="panel-footer collapse">
<input type="hidden" id="gateways-config" name="gateways-config" value="" />

<div id="gateways-settings" class="widgetconfigdiv" >
	<form action="/widgets/widgets/gateways.widget.php" method="post" name="gateways_widget_iform" id="gateways_widget_iform">
		Display:
			<?php
				$display_type_gw_ip = "checked";
				$display_type_monitor_ip = "";
				$display_type_both_ip = "";
				if (isset($config["widgets"]["gateways_widget"]["display_type"])) {
					$selected_radio = $config["widgets"]["gateways_widget"]["display_type"];
					if ($selected_radio == "gw_ip") {
						$display_type_gw_ip = "checked";
						$display_type_monitor_ip = "";
						$display_type_both_ip = "";
					} else if ($selected_radio == "monitor_ip") {
						$display_type_gw_ip = "";
						$display_type_monitor_ip = "checked";
						$display_type_both_ip = "";
					} else if ($selected_radio == "both_ip") {
						$display_type_gw_ip = "";
						$display_type_monitor_ip = "";
						$display_type_both_ip = "checked";
					}
				}
			?>

		<div class="radio">
			<label><input name="display_type" type="radio" id="display_type_gw_ip" value="gw_ip" <?=$display_type_gw_ip;?> onchange="updateGatewayDisplays();" /> <?=gettext('Gateway IP')?></label>
		</div>
		<div class="radio">
			<label><input name="display_type" type="radio" id="display_type_monitor_ip" value="monitor_ip" <?=$display_type_monitor_ip;?> onchange="updateGatewayDisplays();" /><?=gettext('Monitor IP')?></label>
		</div>
		<div class="radio">
			<label><input name="display_type" type="radio" id="display_type_both_ip" value="both_ip" <?=$display_type_both_ip;?> onchange="updateGatewayDisplays();" /><?=gettext('Both')?></label>
		</div>
		<br />
		<button id="submit_settings" name="submit_settings" type="submit" onclick="return updatePref();" class="btn btn-primary btn-sm" value="<?=gettext('Save Settings')?>">
			<i class="fa fa-save icon-embed-btn"></i>
			<?=gettext('Save Settings')?>
		</button>

	</form>
</div>

<script type="text/javascript">
//<![CDATA[

	function get_gw_stats() {
		var ajaxRequest;

		ajaxRequest = $.ajax({
				url: "/widgets/widgets/gateways.widget.php",
				type: "post",
				data: { ajax: "ajax"}
			});

		// Deal with the results of the above ajax call
		ajaxRequest.done(function (response, textStatus, jqXHR) {
			$('#gwtblbody').html(response);
			// and do it again
			setTimeout(get_gw_stats, 5000);
		});
	}

	events.push(function(){
		get_gw_stats();
	});
//]]>
</script>

<?php
function compose_table_body_contents() {
	global $config;

	$rtnstr = '';

	$a_gateways = return_gateways_array();
	$gateways_status = array();
	$gateways_status = return_gateways_status(true);

	if (isset($config["widgets"]["gateways_widget"]["display_type"])) {
		$display_type = $config["widgets"]["gateways_widget"]["display_type"];
	} else {
		$display_type = "gw_ip";
	}

	foreach ($a_gateways as $gname => $gateway) {
		$rtnstr .= "<tr>\n";
		$rtnstr .= 	"<td>\n";
		$rtnstr .= htmlspecialchars($gateway['name']) . "<br />";
		$rtnstr .= '<div id="gateway' . $counter . '" style="display:inline"><b>';

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

		$rtnstr .= $if_gw . $monitor_address_disp;
		unset ($if_gw);
		unset ($monitor_address);
		unset ($monitor_address_disp);
		$counter++;

		$rtnstr .= 		"</b>";
		$rtnstr .= 		"</div>\n";
		$rtnstr .= 	"</td>\n";

		if ($gateways_status[$gname]) {
			if (stristr($gateways_status[$gname]['status'], "force_down")) {
				$online = gettext("Offline (forced)");
				$bgcolor = "danger";  // lightcoral
			} elseif (stristr($gateways_status[$gname]['status'], "down")) {
				$online = gettext("Offline");
				$bgcolor = "danger";  // lightcoral
			} elseif (stristr($gateways_status[$gname]['status'], "loss")) {
				$online = gettext("Packetloss");
				$bgcolor = "warning";  // khaki
			} elseif (stristr($gateways_status[$gname]['status'], "delay")) {
				$online = gettext("Latency");
				$bgcolor = "warning";  // khaki
			} elseif ($gateways_status[$gname]['status'] == "none") {
				if ($gateways_status[$gname]['monitorip'] == "none") {
					$online = gettext("Online <br/>(unmonitored)");
				} else {
					$online = gettext("Online");
				}
				$bgcolor = "success";  // lightgreen
			} elseif ($gateways_status[$gname]['status'] == "") {
				$online = gettext("Pending");
				$bgcolor = "info";  // lightgray
			}
		} else {
			$online = gettext("Unknown");
			$bgcolor = "info";  // lightblue
		}

		$rtnstr .= 	"<td>" . ($gateways_status[$gname] ? htmlspecialchars($gateways_status[$gname]['delay']) : gettext("Pending")) . "</td>\n";
		$rtnstr .= 	"<td>" . ($gateways_status[$gname] ? htmlspecialchars($gateways_status[$gname]['stddev']) : gettext("Pending")) . "</td>\n";
		$rtnstr .= 	"<td>" . ($gateways_status[$gname] ? htmlspecialchars($gateways_status[$gname]['loss']) : gettext("Pending")) . "</td>\n";
		$rtnstr .= '<td class="bg-' . $bgcolor . '">' . $online . "</td>\n";
		$rtnstr .= "</tr>\n";
	}
	return($rtnstr);
}
?>
