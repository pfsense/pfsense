<?php
/*
 * openvpn.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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
require_once("openvpn.inc");

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
	printPanel();
	exit;
}

// Output the widget panel from this function so that it can be called from the AJAX handler as well as
// when first rendering the page
function printPanel() {

	$servers = openvpn_get_active_servers();
	$sk_servers = openvpn_get_active_servers("p2p");
	$clients = openvpn_get_active_clients();

	$opstring = "";

	foreach ($servers as $server):

	$opstring .= "<div class=\"widget panel panel-default\">";
	$opstring .=	"<div class=\"panel-heading\"><h2 class=\"panel-title\">" . htmlspecialchars($server['name']) . "</h2></div>";
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

	if (!empty($sk_servers)):

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

	if (!empty($clients)):
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
		print(gettext("No OpenVPN instances defined"));
	}
}

$widgetperiod = isset($config['widgets']['period']) ? $config['widgets']['period'] * 1000 : 10000;

?>

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
			alert('<?=gettext("An error occurred.");?>' + ' (' + values[3] + ')');
			return;
		}

		$('tr[name="r:' + values[1] + ":" + values[2] + '"]').each(
			function(index,row) { $(row).fadeOut(1000); }
		);
	}

	// Refresh the panel
	function get_update() {
		var ajaxRequest;

		ajaxRequest = $.ajax({
				url: "/widgets/widgets/openvpn.widget.php",
				type: "post",
				data: { ajax: "ajax"}
			});

		// Deal with the results of the above ajax call
		ajaxRequest.done(function (response, textStatus, jqXHR) {
			$('#mainpanel').html(response);

			// and do it again
			setTimeout(get_update, "<?=$widgetperiod?>");
		});
	}

	events.push(function(){
		// Start polling for updates some small random number of seconds from now (so that all the widgets don't
		// hit the server at exactly the same time)
		setTimeout(get_update, Math.floor((Math.random() * 10000) + 1000));
	});
//]]>
</script>
<div id="mainpanel" class="content">

<?php
	printPanel();
?>
</div>

