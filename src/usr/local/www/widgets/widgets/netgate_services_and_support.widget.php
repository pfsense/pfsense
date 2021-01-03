<?php
/*
 * netgate_services_and_support.widget.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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

/*
	This widget transmits the Netgate Device ID to Netgate's REST API, and retrieves the support information.
	The connection is made using HTTPS/TLS. No other data is transmitted. If the widget
	is not enabled, then no transmission is made

	If the file containing the support data exists on the file system and is less than 24 hours old
	the file contents are displayed immediately. If not, an AJAX call is made to retrieve fresh information
*/

require_once("guiconfig.inc");

$supportfile = "/var/db/support.json";
$idfile = "/var/db/uniqueid";
$FQDN = "https://ews.netgate.com/support";
$refreshinterval = (24 * 3600);	// 24 hours


if ($_REQUEST['ajax']) {

	// Retrieve the support data from Netgate.com if
	// the support data file does not exist, or
	// if it is more than a day old and the URL seems resolvable
	if (!file_exists($supportfile) ||
	    ((time()-filemtime($supportfile) > $refreshinterval) && is_url_hostname_resolvable($FQDN))) {
		if (file_exists($supportfile)) {
			unlink($supportfile);
		}

		updateSupport();
	}

	if (file_exists($supportfile)) {
		print(file_get_contents($supportfile));
	}

	exit;
}

// If the widget is called with act=refresh, delete the JSON file and reload the page, thereby forcing the
// widget to get a fresh copy of the support information
if ($_REQUEST['act'] == "refresh") {

    if (file_exists($supportfile)) {
		unlink($supportfile);
    }

    header("Location: /");
	exit;
}

// Poll the Netgate server to obtain the JSON/HTML formatted support information
// and write it to the JSON file
function updateSupport() {
	global $g, $supportfile, $idfile, $FQDN;

	if (file_exists($idfile)) {
		if (function_exists('curl_version')) {
			$post = ['uid' => file_get_contents($idfile), 'language' => '0'];
			$url = $FQDN;

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_VERBOSE, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_USERAGENT, $g['product_label'] . '/' . $g['product_version']);
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,4);
			$response = curl_exec($ch);
			$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if ($status == 200) {
				file_put_contents($supportfile, $response);
			}
		}
	}
}


$doajax = "yes";

print("<div>");

if (file_exists($supportfile) && ( time()-filemtime($supportfile) < $refreshinterval)) {
	// Print the support data from the file
	$str = file_get_contents($supportfile);
	$json = json_decode($str, true);
	print($json['summary']);
	print($json['htmltext']);
	$doajax = "no";
} else {
	//Print empty <div>s and request the data by AJAX
	print(sprintf(gettext("%sRetrieving support information %s %s"),
		"<div id=\"summary\" class=\"alert alert-warning\">", "<i class=\"fa fa-cog fa-spin\"></i>", "</div><div id=\"htmltxt\"></div>"));
}

// Print a low-key refresh link
print('<div style="text-align:right;padding-right:15px;"><a href="/widgets/widgets/netgate_services_and_support.widget.php?act=refresh" usepost><i class="fa fa-refresh"></i></a></div>');

print("</div>");

?>

<script type="text/javascript">
//<![CDATA[
	events.push(function(){
		function fetch_spt_data() {

			$.ajax({
				type: 'POST',
				url: "/widgets/widgets/netgate_services_and_support.widget.php",
				data: {
					ajax: "ajax"
				},

				success: function(data){
					if (data.length > 0) {
						try{
							var obj = JSON.parse(data);

							$('#summary').removeClass("alert");
							$('#summary').removeClass("alert-warning");
							$('#summary').html(obj.summary);
							$('#htmltxt').html(obj.htmltext);

						}catch(e){

						}

					}
				},

				error: function(e){
			//		alert("Error: " + e);

				}
			});
		}

		if ("<?=$doajax?>" === "yes") {
			fetch_spt_data();
		}
	});


//]]>
</script>
