<?php
/*
 * gateways.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008 Seth Mos
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("/usr/local/www/widgets/include/gateways.inc");

if (!function_exists('compose_table_body_contents')) {
	function compose_table_body_contents($widgetkey) {
		global $user_settings;

		$rtnstr = '';

		$a_gateways = return_gateways_array();
		$gateways_status = array();
		$gateways_status = return_gateways_status(true);

		if (isset($user_settings["widgets"][$widgetkey]["display_type"])) {
			$display_type = $user_settings["widgets"][$widgetkey]["display_type"];
		} else {
			$display_type = "gw_ip";
		}

		$hiddengateways = explode(",", $user_settings["widgets"][$widgetkey]["gatewaysfilter"]);
		$gw_displayed = false;

		foreach ($a_gateways as $gname => $gateway) {
			if (in_array($gname, $hiddengateways)) {
				continue;
			}
			if (isset($gateway['inactive'])) {
				$title = gettext("Gateway inactive, interface is missing");
				$icon = 'fa-times-circle-o';
			} elseif (isset($gateway['disabled'])) {
				$icon = 'fa-ban';
				$title = gettext("Gateway disabled");
			} else {
				$icon = 'fa-check-circle-o';
				$title = gettext("Gateway enabled");
			}
			if (isset($gateway['isdefaultgw'])) {
				$gtitle = gettext("Default gateway");
			}

			$gw_displayed = true;
			$rtnstr .= "<tr>\n";
			$rtnstr .= 	"<td title='{$title}'><i class='fa {$icon}'></i></td>\n";
			$rtnstr .= 	"<td title='{$gtitle}'>\n";
			$rtnstr .= htmlspecialchars($gateway['name']);
			if (isset($gateway['isdefaultgw'])) {
				$rtnstr .= ' <i class="fa fa-globe"></i>';
			}
			$rtnstr .= "<br />";
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
				if (stristr($gateways_status[$gname]['status'], "online")) {
					switch ($gateways_status[$gname]['substatus']) {
						case "highloss":
							$online = gettext("Danger, Packetloss");
							$bgcolor = "danger";
							break;
						case "highdelay":
							$online = gettext("Danger, Latency");
							$bgcolor = "danger";
							break;
						case "loss":
							$online = gettext("Warning, Packetloss");
							$bgcolor = "warning";
							break;
						case "delay":
							$online = gettext("Warning, Latency");
							$bgcolor = "warning";
							break;
						default:
							if ($status['monitor_disable'] || ($status['monitorip'] == "none")) {
								$online = gettext("Online <br/>(unmonitored)");
							} else {
								$online = gettext("Online");
							}
							$bgcolor = "success";
					}
				} elseif (stristr($gateways_status[$gname]['status'], "down")) {
					$bgcolor = "danger";
					switch ($gateways_status[$gname]['substatus']) {
						case "force_down":
							$online = gettext("Offline (forced)");
							break;
						case "highloss":
							$online = gettext("Offline, Packetloss");
							break;
						case "highdelay":
							$online = gettext("Offline, Latency");
							break;
						default:
							$online = gettext("Offline");
					}
				} else {
					$online = gettext("Pending");
					$bgcolor = "info";  // lightgray
				}
			} else {
				$online = gettext("Unknown");
				$bgcolor = "info";  // lightblue
			}

			$rtnstr .= 	"<td>" . ($gateways_status[$gname] ? ($gateways_status[$gname]['delay'] ? htmlspecialchars(number_format((float)rtrim($gateways_status[$gname]['delay'], "ms"), 1)) . "ms" : '') : gettext("Pending")) . "</td>\n";
			$rtnstr .= 	"<td>" . ($gateways_status[$gname] ? ($gateways_status[$gname]['stddev'] ? htmlspecialchars(number_format((float)rtrim($gateways_status[$gname]['stddev'], "ms"), 1)) . "ms" : '') : gettext("Pending")) . "</td>\n";
			$rtnstr .= 	"<td>" . ($gateways_status[$gname] ? htmlspecialchars($gateways_status[$gname]['loss']) : gettext("Pending")) . "</td>\n";
			$rtnstr .= '<td class="bg-' . $bgcolor . '">' . $online . "</td>\n";
			$rtnstr .= "</tr>\n";
		}

		if (!$gw_displayed) {
			$rtnstr .= '<tr>';
			$rtnstr .= 	'<td colspan="6" class="text-center">';
			if (count($a_gateways)) {
				$rtnstr .= gettext('All gateways are hidden.');
			} else {
				$rtnstr .= gettext('No gateways found.');
			}
			$rtnstr .= '</td>';
			$rtnstr .= '</tr>';
		}
		return($rtnstr);
	}
}

// Compose the table contents and pass it back to the ajax caller
if ($_REQUEST && $_REQUEST['ajax']) {
	print(compose_table_body_contents($_REQUEST['widgetkey']));
	exit;
}

if ($_POST['widgetkey']) {
	set_customwidgettitle($user_settings);

	if (!is_array($user_settings["widgets"][$_POST['widgetkey']])) {
		$user_settings["widgets"][$_POST['widgetkey']] = array();
	}

	if (isset($_POST["display_type"])) {
		$user_settings["widgets"][$_POST['widgetkey']]["display_type"] = $_POST["display_type"];
	}

	$validNames = array();
	$a_gateways = return_gateways_array();

	foreach ($a_gateways as $gname => $gateway) {
		array_push($validNames, $gname);
	}

	if (is_array($_POST['show'])) {
		$user_settings["widgets"][$_POST['widgetkey']]["gatewaysfilter"] = implode(',', array_diff($validNames, $_POST['show']));
	} else {
		$user_settings["widgets"][$_POST['widgetkey']]["gatewaysfilter"] = implode(',', $validNames);
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Updated gateways widget settings via dashboard."));
	header("Location: /");
	exit(0);
}

$widgetperiod = isset($config['widgets']['period']) ? $config['widgets']['period'] * 1000 : 10000;
$widgetkey_nodash = str_replace("-", "", $widgetkey);

?>

<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
		<thead>
			<tr>
				<th></th>
				<th><?=gettext("Name")?></th>
				<th>RTT</th>
				<th>RTTsd</th>
				<th><?=gettext("Loss")?></th>
				<th><?=gettext("Status")?></th>
			</tr>
		</thead>
		<tbody id="<?=htmlspecialchars($widgetkey)?>-gwtblbody">
<?php
		print(compose_table_body_contents($widgetkey));
?>
		</tbody>
	</table>
</div>
<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="<?=$widget_panel_footer_id?>" class="panel-footer collapse">
<form action="/widgets/widgets/gateways.widget.php" method="post" class="form-horizontal">
	<?=gen_customwidgettitle_div($widgetconfig['title']); ?>
	<div class="form-group">
		<label class="col-sm-4 control-label"><?=gettext('Display')?></label>
<?php
			$display_type_gw_ip = "checked";
			$display_type_monitor_ip = "";
			$display_type_both_ip = "";
			if (isset($user_settings["widgets"][$widgetkey]["display_type"])) {
				$selected_radio = $user_settings["widgets"][$widgetkey]["display_type"];
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
		<div class="col-sm-6">
			<div class="radio">
				<label><input name="display_type" type="radio" id="display_type_gw_ip" value="gw_ip" <?=$display_type_gw_ip;?> /> <?=gettext('Gateway IP')?></label>
			</div>
			<div class="radio">
				<label><input name="display_type" type="radio" id="display_type_monitor_ip" value="monitor_ip" <?=$display_type_monitor_ip;?> /><?=gettext('Monitor IP')?></label>
			</div>
			<div class="radio">
				<label><input name="display_type" type="radio" id="display_type_both_ip" value="both_ip" <?=$display_type_both_ip;?> /><?=gettext('Both')?></label>
			</div>
		</div>
	</div>

	<br />

    <div class="panel panel-default col-sm-10">
		<div class="panel-body">
			<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>">
			<div class="table responsive">
				<table class="table table-striped table-hover table-condensed">
					<thead>
						<tr>
							<th><?=gettext("Gateway")?></th>
							<th><?=gettext("Show")?></th>
						</tr>
					</thead>
					<tbody>
<?php
				$a_gateways = return_gateways_array();
				$hiddengateways = explode(",", $user_settings["widgets"][$widgetkey]["gatewaysfilter"]);
				$idx = 0;

				foreach ($a_gateways as $gname => $gateway):
?>
						<tr>
							<td><?=$gname?></td>
							<td class="col-sm-2"><input id="show[]" name ="show[]" value="<?=$gname?>" type="checkbox" <?=(!in_array($gname, $hiddengateways) ? 'checked':'')?>></td>
						</tr>
<?php
				endforeach;
?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-6">
			<button type="submit" class="btn btn-primary"><i class="fa fa-save icon-embed-btn"></i><?=gettext('Save')?></button>
			<button id="<?=$widget_showallnone_id?>" type="button" class="btn btn-info"><i class="fa fa-undo icon-embed-btn"></i><?=gettext('All')?></button>
		</div>
	</div>
</form>

<script>
//<![CDATA[

events.push(function(){
	// --------------------- Centralized widget refresh system ------------------------------

	// Callback function called by refresh system when data is retrieved
	function gateways_callback(s) {
		$(<?= json_encode('#' . $widgetkey . '-gwtblbody')?>).html(s);
	}

	// POST data to send via AJAX
	var postdata = {
		ajax: "ajax",
		widgetkey : <?=json_encode($widgetkey)?>
	 };

	// Create an object defining the widget refresh AJAX call
	var gatewaysObject = new Object();
	gatewaysObject.name = "Gateways";
	gatewaysObject.url = "/widgets/widgets/gateways.widget.php";
	gatewaysObject.callback = gateways_callback;
	gatewaysObject.parms = postdata;
	gatewaysObject.freq = 1;

	// Register the AJAX object
	register_ajax(gatewaysObject);

	// ---------------------------------------------------------------------------------------------------
});

//]]>
</script>
