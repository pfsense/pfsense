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

// Create public and private keys
function wgCreateKeys() {
	global $config;

	// ToDo Inset wireguard magic to create public and private keys
	// Debug
	$pubkey = "xTIBA5rboUvnH4htodjb6e697QjLERt1NAB4mZqp8Dg=";
	$privkey = "yAnz5TF+lXXJte14tji3zlMNq+hd2rYUIgJBgB3fBmk=";

	$config['wireguard']['fwpubkey'] = $pubkey;
	$config['wireguard']['fwprivkey'] = $privkey;

	write_config("Wireguard keys created");
}

// Return the next wireguard interface name that is not currently in use
function nextFreeWGInterfaceName() {
	global $config;

	$tunnels = $config['wireguard']['tunnel'];
	$num = count($tunnels);

	for( $idx=0; $idx<$num; $idx++) {
		if ($idx != intval(substr($tunnels[$idx]['name'], 2))) {
			return $idx;
		}
	}

	return $idx;
}

function deleteTunnel($tunidx) {
	global $config;

	if ($config['wireguard']['tunnel'][$tunidx]) {
		// Delete the config file entry
		unset($config['wireguard']['tunnel'][$tunidx]);
		write_config("Wireguard tunnel {$index} updated.");

		// Delete the wg?.conf file
		$fname = $config['wireguard']['tunnel'][$tunidx]['name'];
		if(is_file($fname)) {
			unlink($fname);
		}

		// ToDo: kill the deleted tunnel
	}
}

// Write new tunnel values to the configuration system
function wg_do_post($post, $json = false) {
	global $config;

	init_config_arr(array('wireguard', 'tunnel'));

	$input_errors = array();

	$index = $post['index'];

	$pconfig = &$config['wireguard']['tunnel'][$index];

	$pconfig['name'] = "wg" . $index;
	$pconfig['enabled'] = empty($post['enabled']) ? 'no':'yes';
	$pconfig['descr'] = $post['descr'];

	// Interface section
	$pconfig['interface']['address'] = $post['address'];
	$pconfig['interface']['listenport'] = $post['listenport'];
	$pconfig['interface']['privatekey'] = $post['privatekey'];
	
	// Peers section
	$pconfig['peers'] = array();

	$idx = 0;

	for (;;) {
		if (!$post['publickeyp' . $idx] && !$post['endpoint' . $idx] && !$post['allowedips' . $idx] && !$post['descr' . $idx] && !$post['persistentkeepalive' . $idx]) {
			break;
		}

		$peer = array();
		$peer['publickey'] =  $post['publickeyp' . $idx];
		$peer['endpoint'] =  $post['endpoint' . $idx];
		$peer['allowedips'] =  $post['allowedips' . $idx];
		$peer['descr'] =  $post['descp' . $idx];
		$peer['persistentkeepalive'] =  $post['persistentkeepalive' . $idx];

		$pconfig['peers'][] = $peer;
		$idx++;
	}

	// print_r($pconfig);
	// $input_errors[] = "What were you thinking?";

	$config['wireguard']['tunnel'][$index] = $pconfig;
	write_config("Wireguard tunnel {$index} updated.");

	return(array('input_errors' => $input_errors, 'pconfig' => $pconfig));
}

// Read the Wireguard configurations from config.xml and create a number of *.conf files for wg-quick to read
function wg_create_config_files($clean = true) {
	global $config, $g;

	if ($config["wireguard"]["tunnel"]) {
		$wg_tunnels = $config["wireguard"]["tunnel"];
		$cfgpath = $g["wg_conf_path"];

		if (!file_exists($cfgpath)) {
			mkdir($cfgpath, 0644);
		}

		if ($clean) {
			delete_wg_configs();
		}

		foreach ($wg_tunnels as $tunnel) {
			if (!empty($tunnel['enabled']) && $tunnel['enabled'] == 'yes') {
				make_wg_conf($tunnel);
			}
		}
	}
}

// Write each file
function make_wg_conf($tunnel) {
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

	file_put_contents($g["wg_conf_path"] . "" . $tunnel['name'] . ".conf", $txt);
}

// Remove all wg config files from the conf directory
function delete_wg_configs() {
	global $g;

	$files = glob($g["wg_conf_path"] . "/*.conf"); // get all confog file names
	foreach($files as $file) {
		if(is_file($file)) {
			unlink($file);
		}
	}
}

/*
Sample Wireguard config.xml entry

	<wireguard>
		<fwpubkey></fwpubkey>
		<fwprivkey></fwprivkey>
		<tunnel>
			<name>wg0</name>
			<descr>Home office</descr>
			<enabled>yes</enabled>
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
			<name>wg1</name>
			<descr>Branch office</descr>
			<enabled>yes</enabled>
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
				<descr>Sam</descr>
			</peers>
		</tunnel>
	</wireguard>
*/
