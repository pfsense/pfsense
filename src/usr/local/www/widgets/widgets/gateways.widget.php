<?php
/*
	gateways.widget.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *  Copyright (c)  2008 Seth Mos
 *
 *  Some or all of this file is based on the m0n0wall project which is
 *  Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
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

<table class="table table-striped table-hover">
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
		<input name="display_type" class="radio" type="radio" id="display_type_gw_ip" value="gw_ip" <?=$display_type_gw_ip;?> onchange="updateGatewayDisplays();" /> <span><?=gettext('Gateway IP')?></span>
		<input name="display_type" class="radio" type="radio" id="display_type_monitor_ip" value="monitor_ip" <?=$display_type_monitor_ip;?> onchange="updateGatewayDisplays();" /> <span><?=gettext('Monitor IP')?></span>
		<input name="display_type" class="radio" type="radio" id="display_type_both_ip" value="both_ip" <?=$display_type_both_ip;?> onchange="updateGatewayDisplays();" /> <span><?=gettext('Both')?></span>
		<br /><br />
		<input id="submit_settings" name="submit_settings" type="submit" onclick="return updatePref();" class="formbtn" value="<?=gettext('Save Settings')?>" />
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
				$online = gettext("Online");
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
