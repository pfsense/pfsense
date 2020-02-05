<?php
/*
 * copyget.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
	Provides the functionality required to fetch updated copyright information from Netgate
*/

require_once("guiconfig.inc");

$copyrightfile = "{$g['cf_conf_path']}/copyright";
$tmpfile = "{$g['cf_conf_path']}/copyright.tmp";
$idfile = "/var/db/uniqueid";
$FQDN = "https://ews.netgate.com/copyright";
$refreshinterval = (24 * 3600);	// 24 hours

// Poll the Netgate server to obtain the JSON/HTML formatted support information
// and write it to the JSON file
function updatecopyright() {
	global $g, $copyrightfile, $tmpfile, $idfile, $FQDN;

	if (file_exists($idfile)) {
		if (function_exists('curl_version')) {
			$post = ['uid' => file_get_contents($idfile), 'language' => '0', 'edition' => 'community', 'version' => '1'];
			$url = $FQDN;

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_VERBOSE, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_USERAGENT, $g['product_name'] . '/' . $g['product_version']);
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,4);
			$response = curl_exec($ch);
			$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if ($status == 200) {
				// Save to a temporary file
				file_put_contents($tmpfile, $response);
				// If the file contents are not the same as the existing file, create the trigger to display the new copyright
				if (!file_exists($copyrightfile) || !compare_copytext(file_get_contents($copyrightfile), $response)) {
					touch("{$g['cf_conf_path']}/copynotice_display");
				}

				rename($tmpfile, $copyrightfile);
			}
		}
	}
}

// Retrieve the copyright data from Netgate.com if the support data file does not exist,
// or if it is more than a day old
if (!file_exists($copyrightfile) || ( time()-filemtime($copyrightfile) > $refreshinterval)) {
	updateCopyright();
}

// Compare the copyright text contained in the first HTML <DIV>
function compare_copytext ($new, $old) {
	if ((strlen($new) > 0) && (strlen($old) > 0)) {
		$new_divend = strpos($new, "</div>");
		$old_divend = strpos($old, "</div>");

		if ($new_divend && $old_divend) {
			return(md5(substr($new, 0, $new_divend)) === md5(substr($old, 0, $old_divend)));
		}
	}

	return false;
}
