<?php
/*
 * openvpn.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *
 * 3. All advertising materials mentioning features or use of this software
 *    must display the following acknowledgment:
 *    "This product includes software developed by the pfSense Project
 *    for use in the pfSense® software distribution. (http://www.pfsense.org/).
 *
 * 4. The names "pfSense" and "pfSense Project" must not be used to
 *    endorse or promote products derived from this software without
 *    prior written permission. For written permission, please contact
 *    coreteam@pfsense.org.
 *
 * 5. Products derived from this software may not be called "pfSense"
 *    nor may "pfSense" appear in their names without prior written
 *    permission of the Electric Sheep Fencing, LLC.
 *
 * 6. Redistributions of any form whatsoever must retain the following
 *    acknowledgment:
 *
 * "This product includes software developed by the pfSense Project
 * for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 * THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 * EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 * ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 * STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 * OF THE POSSIBILITY OF SUCH DAMAGE.
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
	$opstring .=					"<th>" . gettext('Nome/Time') . "</th>";
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

	$opstring .=				"<tr name=\"r:" . client['port'] . ":" . $client['remote_host'] . "\">";
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

	// REfreh the panel
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
			setTimeout(get_update, 5000);
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
