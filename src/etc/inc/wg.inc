<?php
/*
 * syslog.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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

require_once("globals.inc");
require_once('config.inc');

// Read the Wireguard configuration from config.xml and create a number of *.conf files for wg-quick to read
function wg_create_config_files($clean = true) {
	global $config, $g;

	if ($config["wireguard"]["tunnel"]) {
		$wg_tunnels = $config["wireguard"]["tunnel"];
		$cfgpath = $g["wg_conf_path"];

		if (!file_exists($cfgpath)) {
			mkdir($cfgpath, 0644);
		}
	}

	if ($clean) {
		delete_wg_configs();
	}

	$idx = 0;
	foreach ($wg_tunnels as $tunnel) {
		make_wg_conf($tunnel, $idx);
		$idx++;
	}
}

function make_wg_conf($tunnel, $idx) {
	global $g;

	$txt = "# This Wireguard config file has been created automatically. Do not edit!\n";

	$txt .= "# Description: {$tunnel['descr']}\n\n";

	// Process Interfaces section
	$txt .= "[Interface]\n";

	if (!empty($tunnel["interface"]["privatekey"])) {
		$txt .= "PrivateKey = {$tunnel["interface"]["privatekey"]}\n";
	}

	if (!empty($tunnel["interface"]["address"])) {
		$txt .= "Address = {$tunnel["interface"]["address"]}\n";
	}

	if (!empty($tunnel["interface"]["listenport"])) {
		$txt .= "ListenPort = {$tunnel["interface"]["listenport"]}\n";
	}

	$txt .= "\n";

	// Process peers section
	if (isset($tunnel["peers"]) && count($tunnel["peers"]) > 0) {
		foreach ($tunnel["peers"] as $peer) {
			$txt .= "# Peer: {$peer['descr']}\n";
			$txt .= "[Peer]\n";

			if (!empty($peer["publickey"])) {
				$txt .= "PublicKey = {$peer['publickey']}\n";
			}

			if (!empty($peer["address"])) {
				$txt .= "Address = {$peer['address']}\n";
			}

			if (!empty($peer["listenport"])) {
				$txt .= "ListenPort = {$peer['listenport']}\n";
			}

			if (!empty($peer["endpoint"])) {
				$txt .= "EndPoint = {$peer['endpoint']}\n";
			}

			if (!empty($peer["allowedips"])) {
				$txt .= "AllowedIPs = {$peer['allowedips']}\n";
			}

			if (!empty($peer["persistentkeepalive"])) {
				$txt .= "PersistentKeepalive = {$peer['persistentkeepalive']}\n";
			}

			if (!empty($peer["presharedkey"])) {
				$txt .= "PresharedKey = {$peer['presharedkey']}\n";
			}

			$txt .= "\n";
		}
	}

	file_put_contents($g["wg_conf_path"] . "/wg" . $idx . ".conf", $txt);
}

function delete_wg_configs() {

}

/*
Sample Wireguard config.xml entry

	<wireguard>
		<tunnel>
			<descr>Home office</descr>
			<interface>
				<privatekey>yAnz5TF+lXXJte14tji3zlMNq+hd2rYUIgJBgB3fBmk=</privatekey>
				<address>10.0.0.1/24, fdc9:281f:04d7:9ee9::1/64</address>
				<listenport>51820</listenport>
			</interface>
			<peers>
				<publickey>xTIBA5rboUvnH4htodjb6e697QjLERt1NAB4mZqp8Dg=</publickey>
				<endpoint>192.88.8.88:51820</endpoint>
				<allowedips>10.192.122.1/32, 10.192.124.1/24</allowedips>
				<descr>Alice</descr>
			</peers>
			<peers>
				<publickey>xTIBA5rboUvnH4htodjb6e697QjLERt1NAB4mZqp8Dg=</publickey>
				<endpoint>192.88.8.88:51820</endpoint>
				<allowedips>10.192.122.2/32, 10.192.124.2/24</allowedips>
				<descr>Bob</descr>
			</peers>
			<peers>
				<publickey>xTIBA5rboUvnH4htodjb6e697QjLERt1NAB4mZqp8Dg=</publickey>
				<endpoint>192.88.8.88:51820</endpoint>
				<allowedips>10.192.122.3/32, 10.192.124.3/24</allowedips>
				<descr>Charlie</descr>
			</peers>
		</tunnel>
		<tunnel>
			<descr>Branch office</descr>
			<interface>
				<privatekey>yAnz5TF+lXXJte14tji3zlMNq+hd2rYUIgJBgB3fBmk=</privatekey>
				<address>10.0.0.1/24, fdc9:281f:04d7:9ee9::1/64</address>
				<listenport>51820</listenport>
			</interface>
			<peers>
				<publickey>xTIBA5rboUvnH4htodjb6e697QjLERt1NAB4mZqp8Dg=</publickey>
				<endpoint>192.88.8.88:51820</endpoint>
				<allowedips>10.192.122.1/32, 10.192.124.1/24</allowedips>
				<persistentkeepalive>25</persistentkeepalive>
				<descr>Alice</descr>
			</peers>
		</tunnel>
	</wireguard>
*/
