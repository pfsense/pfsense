<?php
/*
 * support.widget.php
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

/*
	This widget transmits the device ID to Netgate's REST API and retrieves the support	information.
	The connection is made using HTTPS/TLS. No other data is transmitted. If the widget
	is not enabled, then no transmission is made
*/

$nocsrf = true;
$supportfile = "/var/db/support.json";
$idfile = "/var/db/uniqueid";
$FQDN = "https://ews.netgate.com/support";
$refreshinterval = (24 * 3600);	// 24 hours

// Write a dummy support file containg an error message
function nosupportdata() {
	global $supportfile;

	file_put_contents($supportfile, sprintf(gettext("%sSupport information could not be retrieved%s"),
		"{\"summary\":\"<div class=\\\"alert alert-danger\\\">", "</div>\",\"htmltext\":\"\"}"));

	// Make the file {refreshinterval} old so that the widget tries again on the next page load
	touch($supportfile, (time() - $refreshinterval));
}

// Poll the Netgate server to obtain the JSON/HTML formatted support information
// and write it to the JSON file
function updateSupport() {
	global $g, $supportfile, $idfile, $FQDN;

	$success = false;

	if (file_exists($idfile)) {
		if (function_exists('curl_version')) {
			$post = ['uid' => file_get_contents($idfile), 'language' => '0'];
			$url = $FQDN;

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_VERBOSE, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_USERAGENT, $g['product_name'] . '/' . $g['product_version']);
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
			$response = curl_exec($ch);
			$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if ($status == 200) {
				file_put_contents($supportfile, $response);
				$success = true;
			}
		}
	}

	if (!$success) {
		nosupportdata();
	}
}

// If the widget is called with act=refresh, delete the JSON file and reload the page, thereby forcing the
// widget to get a fresh copy of the support information
if ($_REQUEST['act'] == "refresh") {
    unlink($supportfile);
    header("Location: /");
	exit;
}

// Retrieve the support data from Netgate.com if the supprt data file does not exist,
// or if it is more than a day old
if (!file_exists($supportfile) || ( time()-filemtime($supportfile) > $refreshinterval)) {
	updateSupport();
}

$str = file_get_contents($supportfile);
$json = json_decode($str, true);

print("<div>");
print($json['summary']);

if (strlen($json['htmltext']) > 0) {
	print('<div class="panel-body" style="padding-left:15px; padding-right:15px;">');
	print('<hr style="margin-top:0px">');
	print($json['htmltext']);
	print('</div>');
}

// Print a low-key refresh link
print('<div style="text-align:right;padding-right:15px;"><a href="/widgets/widgets/netgate_services_and_support.widget.php?act=refresh" usepost>Refresh</a></div>');

print("</div>");

?>
