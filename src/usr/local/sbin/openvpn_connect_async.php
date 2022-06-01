<?php
/*
# openvpn_connect_async.php
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2021-2022 Rubicon Communications, LLC (Netgate)
# All rights reserved.
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
# http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.
*/

// Returns a string with all active sessions delimited by '|'.

require_once("openvpn.inc");

$result = openvpn_get_active_servers();
$output = null;

foreach ($result as $server) {
	$vpnid = $server["vpnid"];
	foreach ($server["conns"] as $client) {
		if ( !(empty($client["remote_host"])) ) {
			$port = substr($client["remote_host"], strpos($client["remote_host"], ":") + 1);
			/* Handle RADIUS backend */
			if (escapeshellarg($client["common_name"]) != "'UNDEF'") {
				$name = escapeshellarg($client["common_name"]);
			} else {
				$name = escapeshellarg($client["user_name"]);
			}
			$name = isset($name) ? trim($name) : NULL;
			$port = isset($port) ? trim($port) : NULL;
			if ( !empty($name) && !empty($port) ) {
				$output .= "ovpns{$vpnid}_{$name}_{$port}|";
			}
		}
	}
}

echo (isset($output)) ? $output : '';

?>
