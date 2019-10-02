<?php
/*
 * openvpn.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
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
require_once("openvpn.inc");

// Output the widget panel from this function so that it can be called from the AJAX handler as well as
// when first rendering the page
if (!function_exists('printPanel')) {
	function printPanel($widgetkey) {
		global $user_settings;

		$servers = openvpn_get_active_servers();
		$sk_servers = openvpn_get_active_servers("p2p");
		$clients = openvpn_get_active_clients();
		$skipovpns = explode(",", $user_settings['widgets'][$widgetkey]['filter']);

		$opstring = "";
		$got_ovpn_server = false;

		foreach ($servers as $server):
			if (in_array($server['vpnid'], $skipovpns)) {
				continue;
			}

			$got_ovpn_server = true;

		$opstring .= "<div class=\"widget panel panel-default\">";
		$opstring .= "<div class=\"panel-heading\"><h2 class=\"panel-title\">" .
		    htmlspecialchars($server['name']);
		if ($server['conns'][0]['common_name'] != '[error]') {
			$opstring .= ' (' . sizeof($server['conns']) . ')';
		}
		$opstring .=    "</h2></div>";
		$opstring .=	"<div class=\"table-responsive\">";
		$opstring .=		"<table class=\"table table-striped table-hover table-condensed sortable-theme-bootstrap\" data-sortable>";
		$opstring .=			"<thead>";
		$opstring .=				"<tr>";
		$opstring .=					"<th>" . gettext('Name/Time') . "</th>";
		$opstring .=					"<th>" . gettext('Real/Virtual IP') . "</th>";
		$opstring .=					"<th></th>";
		$opstring .=				"</tr>";
		$opstring .=			"</thead>";
		$opstring .=			"<tbody>";

					$rowIndex = 0;
					foreach ($server['conns'] as $conn):
						$evenRowClass = $rowIndex % 2 ? " listMReven" : " listMRodd";
						$rowIndex++;

		$opstring .=				"<tr name=\"" . "r:" . $server['mgmt'] . ":" . $conn['remote_host'] . "\" class=\"" . $evenRowClass . "\">";
		$opstring .=					"<td>";
		$opstring .=						$conn['common_name'];
		$opstring .=					"</td>";
		$opstring .=					"<td>";
		$opstring .=						$conn['remote_host'];
		$opstring .=					"</td>";
		$opstring .=					"<td>";
		$opstring .=						"<i class=\"fa fa-times-circle\" ";
		$opstring .=							"onclick=\"killClient('" . $server['mgmt'] . "', '" . $conn['remote_host'] . "');\" ";
		$opstring .=							"style=\"cursor:pointer;\" ";
		$opstring .=							"name=\"" . "i:" . $server['mgmt'] . ":" . $conn['remote_host'] . "\" ";
		$opstring .=							"title=\"" . sprintf(gettext('Kill client connection from %s'), $conn['remote_host']) . "\">";
		$opstring .=						"</i>";
		$opstring .=					"</td>";
		$opstring .=				"</tr>";
		$opstring .=				"<tr name=\"" . "r:" . $server['mgmt'] . ":" . $conn['remote_host'] . "\" class=\"" . $evenRowClass . "\">";
		$opstring .=					"<td>";
		$opstring .=						$conn['connect_time'];
		$opstring .=					"</td>";
		$opstring .=					"<td>";
		$opstring .=						$conn['virtual_addr'];
		if (!empty($conn['virtual_addr']) && !empty($conn['virtual_addr6'])) {
			$opstring .=						"<br />";
		}
		$opstring .=						$conn['virtual_addr6'];
		$opstring .=					"</td>";
		$opstring .=					"<td></td>";
		$opstring .=				"</tr>";

					endforeach;

		$opstring .=			"</tbody>";
		$opstring .=		"</table>";
		$opstring .=	"</div>";
		$opstring .= "</div>";

		endforeach;

		print($opstring);

		$got_sk_server = false;

		if (!empty($sk_servers)):
			foreach ($sk_servers as $sk_server):
				if (!in_array($sk_server['vpnid'], $skipovpns)) {
					$got_sk_server = true;
					break;
				}
			endforeach;
		endif;

		if ($got_sk_server):

		$opstring = "";
		$opstring .= "<div class=\"widget panel panel-default\">";
		$opstring .=	"<div class=\"panel-heading\"><h2 class=\"panel-title\">" . gettext("Peer to Peer Server Instance Statistics") . "</h2></div>";
		$opstring .=	"<div class=\"table-responsive\">";
		$opstring .=		"<table class=\"table table-striped table-hover table-condensed sortable-theme-bootstrap\" data-sortable>";
		$opstring .=			"<thead>";
		$opstring .=				"<tr>";
		$opstring .=					"<th>" . gettext('Name/Time') . "</th>";
		$opstring .=					"<th>" . gettext('Remote/Virtual IP') . "</th>";
		$opstring .=					"<th></th>";
		$opstring .=				"</tr>";
		$opstring .=			"</thead>";
		$opstring .=			"<tbody>";

					foreach ($sk_servers as $sk_server):
						if (in_array($sk_server['vpnid'], $skipovpns)) {
							continue;
						}

		$opstring .=				"<tr name=\"r:" . $sk_server['port'] . ":" . $sk_server['remote_host'] . "\">";
		$opstring .=					"<td>";
		$opstring .=						$sk_server['name'];
		$opstring .=					"</td>";
		$opstring .=					"<td>";
		$opstring .=						$sk_server['remote_host'];
		$opstring .=					"</td>";
		$opstring .=					"<td>";

						if ($sk_server['status'] == "up") {
							/* tunnel is up */
		$opstring .=						"<i class=\"fa fa-arrow-up text-success\"></i>";
						} else {
							/* tunnel is down */
		$opstring .=						"<i class=\"fa fa-arrow-down text-danger\"></i>";
						}

		$opstring .=					"</td>";
		$opstring .=				"</tr>";
		$opstring .=				"<tr name=\"r:" . $sk_server['port'] . ":" . $sk_server['remote_host'] . "\">";
		$opstring .=					"<td>";
		$opstring .=						$sk_server['connect_time'];
		$opstring .=					"</td>";
		$opstring .=					"<td>";
		$opstring .=						$sk_server['virtual_addr'];
		if (!empty($sk_server['virtual_addr']) && !empty($sk_server['virtual_addr6'])) {
			$opstring .=						"<br />";
		}
		$opstring .=						$sk_server['virtual_addr6'];
		$opstring .=					"</td>";
		$opstring .=					"<td></td>";
		$opstring .=				"</tr>";

					endforeach;

		$opstring .=			"</tbody>";
		$opstring .=		"</table>";
		$opstring .=	"</div>";
		$opstring .= "</div>";

		print($opstring);

		endif;

		$got_ovpn_client = false;

		if (!empty($clients)):
			foreach ($clients as $client):
				if (!in_array($client['vpnid'], $skipovpns)) {
					$got_ovpn_client = true;
					break;
				}
			endforeach;
		endif;

		if ($got_ovpn_client):

		$opstring = "";

		$opstring .= "<div class=\"widget panel panel-default\">";
		$opstring .=	"<div class=\"panel-heading\"><h2 class=\"panel-title\">" . gettext("Client Instance Statistics") . "</h2></div>";
		$opstring .=	"<div class=\"table-responsive\">";
		$opstring .=		"<table class=\"table table-striped table-hover table-condensed sortable-theme-bootstrap\" data-sortable>";
		$opstring .=			"<thead>";
		$opstring .=				"<tr>";
		$opstring .=					"<th>" . gettext('Name/Time') . "</th>";
		$opstring .=					"<th>" . gettext('Remote/Virtual IP') . "</th>";
		$opstring .=					"<th></th>";
		$opstring .=				"</tr>";
		$opstring .=			"</thead>";
		$opstring .=			"<tbody>";

					foreach ($clients as $client):
						if (in_array($client['vpnid'], $skipovpns)) {
							continue;
						}

		$opstring .=				"<tr name=\"r:" . $client['port'] . ":" . $client['remote_host'] . "\">";
		$opstring .=					"<td>";
		$opstring .=						$client['name'];
		$opstring .=					"</td>";
		$opstring .=					"<td>";
		$opstring .=						$client['remote_host'];
		$opstring .=					"</td>";
		$opstring .=					"<td>";

						if ($client['status'] == "up") {
							/* tunnel is up */
		$opstring .=						"<i class=\"fa fa-arrow-up text-success\"></i>";
						} else {
							/* tunnel is down */
		$opstring .=						"<i class=\"fa fa-arrow-down text-danger\"></i>";
						}

		$opstring .=					"</td>";
		$opstring .=				"</tr>";
		$opstring .=				"<tr name=\"r:" . $client['port'] . ":" . $client['remote_host'] . "\">";
		$opstring .=					"<td>";
		$opstring .=						$client['connect_time'];
		$opstring .=					"</td>";
		$opstring .=					"<td>";
		$opstring .=						$client['virtual_addr'];
		if (!empty($client['virtual_addr']) && !empty($client['virtual_addr6'])) {
			$opstring .=						"<br />";
		}
		$opstring .=						$client['virtual_addr6'];
		$opstring .=					"</td>";
		$opstring .=					"<td></td>";
		$opstring .=				"</tr>";

					endforeach;

		$opstring .=			"</tbody>";
		$opstring .=		"</table>";
		$opstring .=	"</div>";
		$opstring .= "</div>";

		print($opstring);

		endif;

		if ((empty($clients)) && (empty($servers)) && (empty($sk_servers))) {
			$none_to_display_text = gettext("No OpenVPN instances defined");
		} else if (!$got_ovpn_server && !$got_sk_server && !$got_ovpn_client) {
			$none_to_display_text = gettext("All OpenVPN instances are hidden");
		} else {
			$none_to_display_text = "";
		}

		if (strlen($none_to_display_text) > 0) {
			print('<table class="table"><tbody><td class="text-center">' . $none_to_display_text . '</td></tbody></table>');
		}
	}
}

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

// Compose the table contents and pass it back to the ajax caller
if ($_REQUEST && $_REQUEST['ajax']) {
	printPanel($_REQUEST['widgetkey']);
	exit;
} else if ($_POST['widgetkey']) {
	set_customwidgettitle($user_settings);

	$validNames = array();
	$servers = openvpn_get_active_servers();
	$sk_servers = openvpn_get_active_servers("p2p");
	$clients = openvpn_get_active_clients();

	foreach ($servers as $server) {
		array_push($validNames, $server['vpnid']);
	}

	foreach ($sk_servers as $sk_server) {
		array_push($validNames, $sk_server['vpnid']);
	}

	foreach ($clients as $client) {
		array_push($validNames, $client['vpnid']);
	}

	if (is_array($_POST['show'])) {
		$user_settings['widgets'][$_POST['widgetkey']]['filter'] = implode(',', array_diff($validNames, $_POST['show']));
	} else {
		$user_settings['widgets'][$_POST['widgetkey']]['filter'] = implode(',', $validNames);
	}

	save_widget_settings($_SESSION['Username'], $user_settings["widgets"], gettext("Saved OpenVPN Filter via Dashboard."));
	header("Location: /index.php");
}

$widgetperiod = isset($config['widgets']['period']) ? $config['widgets']['period'] * 1000 : 10000;
$widgetkey_nodash = str_replace("-", "", $widgetkey);

?>

<div id="<?=htmlspecialchars($widgetkey)?>-openvpn-mainpanel" class="content">

<?php
	printPanel($widgetkey);
?>
</div>
<!-- close the body we're wrapped in and add a configuration-panel -->
</div><div id="<?=$widget_panel_footer_id?>" class="panel-footer collapse">

<form action="/widgets/widgets/openvpn.widget.php" method="post" class="form-horizontal">
	<?=gen_customwidgettitle_div($widgetconfig['title']); ?>
    <div class="panel panel-default col-sm-10">
		<div class="panel-body">
			<input type="hidden" name="widgetkey" value="<?=htmlspecialchars($widgetkey); ?>">
			<div class="table responsive">
				<table class="table table-striped table-hover table-condensed">
					<thead>
						<tr>
							<th><?=gettext("Name")?></th>
							<th><?=gettext("Show")?></th>
						</tr>
					</thead>
					<tbody>
<?php
				$servers = openvpn_get_active_servers();
				$sk_servers = openvpn_get_active_servers("p2p");
				$clients = openvpn_get_active_clients();
				$skipovpns = explode(",", $user_settings['widgets'][$widgetkey]['filter']);
				foreach ($servers as $server):
?>
						<tr>
							<td><?=htmlspecialchars($server['name'])?></td>
							<td class="col-sm-2"><input id="show[]" name ="show[]" value="<?=$server['vpnid']?>" type="checkbox" <?=(!in_array($server['vpnid'], $skipovpns) ? 'checked':'')?>></td>
						</tr>
<?php
				endforeach;
				foreach ($sk_servers as $sk_server):
?>
						<tr>
							<td><?=htmlspecialchars($sk_server['name'])?></td>
							<td class="col-sm-2"><input id="show[]" name ="show[]" value="<?=$sk_server['vpnid']?>" type="checkbox" <?=(!in_array($sk_server['vpnid'], $skipovpns) ? 'checked':'')?>></td>
						</tr>
<?php
				endforeach;
				foreach ($clients as $client):
?>
						<tr>
							<td><?=htmlspecialchars($client['name'])?></td>
							<td class="col-sm-2"><input id="show[]" name ="show[]" value="<?=$client['vpnid']?>" type="checkbox" <?=(!in_array($client['vpnid'], $skipovpns) ? 'checked':'')?>></td>
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

<script type="text/javascript">
//<![CDATA[
	function killClient(mport, remipp) {

		$.ajax(
			"widgets/widgets/openvpn.widget.php" +
				"?action=kill&port=" + mport + "&remipp=" + remipp,
			{ type: "get", complete: killComplete }
		);
	}

	function killComplete(req) {
		var values = req.responseText.split("|");
		if (values[3] != "0") {
	//		alert('<?=gettext("An error occurred.");?>' + ' (' + values[3] + ')');
			return;
		}

		$('tr[name="r:' + values[1] + ":" + values[2] + '"]').each(
			function(index,row) { $(row).fadeOut(1000); }
		);
	}

	events.push(function(){
		set_widget_checkbox_events("#<?=$widget_panel_footer_id?> [id^=show]", "<?=$widget_showallnone_id?>");

		// --------------------- Centralized widget refresh system ------------------------------

		// Callback function called by refresh system when data is retrieved
		function openvpn_callback(s) {
			$(<?=json_encode('#' . $widgetkey . '-openvpn-mainpanel')?>).html(s);
		}

		// POST data to send via AJAX
		var postdata = {
			ajax: "ajax",
			widgetkey: <?=json_encode($widgetkey)?>
		 };

		// Create an object defining the widget refresh AJAX call
		var openvpnObject = new Object();
		openvpnObject.name = "OpenVPN";
		openvpnObject.url = "/widgets/widgets/openvpn.widget.php";
		openvpnObject.callback = openvpn_callback;
		openvpnObject.parms = postdata;
		openvpnObject.freq = 4;

		// Register the AJAX object
		register_ajax(openvpnObject);

		// ---------------------------------------------------------------------------------------------------
	});
//]]>
</script>
