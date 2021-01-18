<?php
/*
 * wg.inc
 *
 * part of pfSense (https://www.pfsense.org)
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

require_once("globals.inc");
require_once('config.inc');

$wgbinpath = '/usr/local/bin/wg';

// Gnerate a preshared key
function gerneratePSK() {
	global $wgbinpath;

	return exec("{$wgbinpath} genpsk");
}

// Return the next available wWreguard port
function next_wg_port() {
	global $config;

	init_config_arr(array('wireguard', 'tunnel'));
	$tunnels = &$config['wireguard']['tunnel'];

	for ($idx=51820; $idx<65535; $idx++) {
		// Check to see if the port is already in use
		$found = false;
		foreach ($tunnels as $tunnel) {
			if ($tunnel['interface']['listenport'] == $idx) {
				$found = true;
				break;
			}
		}

		// If not, it can be used
		if (!$found) {
			return $idx;
		}
	}

	return 51820;
}

// Validate the user's input and return error messages if not acceptable
function wg_validate_post($pconfig) {
	$input_errors = array();

	// Check the addresses
	$addrs = explode(",", $pconfig['interface']['address']);

	foreach ($addrs as $addr) {
		$addr = trim($addr);

		if (!is_subnet($addr)) {
			$input_errors[] = sprintf(gettext(
			    '%1$s is not a valid CIDR address'), $addr);
		}

		$iflist = get_configured_interface_list_by_realif();

		$skip = '';
		if (!empty($iflist[$pconfig['name']])) {
			$skip = $iflist[$pconfig['name']];
		}

		$a = explode("/", $addr);
		$conflicts = where_is_ipaddr_configured($a[0], $skip, true,
		    true, $a[1]);

		if (!empty($conflicts)) {
			foreach ($conflicts as $conflict) {
				$input_errors[] = sprintf(gettext(
				    '%1$s is already configured on this ' .
				    'firewall: %2$s (%3$s)'), $addr,
					strtoupper($conflict['if']),
					$conflict['ip_or_subnet']);
			}
		}
	}

	// Check listen port
	$lport = $pconfig['interface']['listenport'];
	if (empty($lport) || $lport > 65535 || $lport < 512 ) {
		$input_errors[] = gettext("Invalid interface listen port.");
	}

	// Check keys
	if (empty($pconfig['interface']['privatekey']) ||
	    empty($pconfig['interface']['publickey'])) {
		$input_errors[] = gettext("Both a public and private key " .
		    "must be specified");
	}

	// Now the peers
	if (isset($pconfig['peers']['wgpeer'])) {
		$idx = 0;
		foreach ($pconfig['peers']['wgpeer'] as $peer) {
			$input_errors = array_merge($input_errors,
			    wg_validate_peer($idx, $peer));
			$idx++;
		}
	}

	return $input_errors;
}

// Valildate a peer
function wg_validate_peer($idx, $peer) {
	$input_errors = array();

	// Check remote port
	$rport = $peer['port'];
	if (!empty($rport) && ($rport > 65535 || $rport < 512 )) {
		$input_errors[] = sprintf(gettext(
		    'Peer %1$s: Invalid remote port. (%2$s)'), $idx, $rport);
	}

	// Check key
	if (empty($peer['publickey'])) {
		$input_errors[] = sprintf(gettext(
		    'Peer %1$s: A public key must be specified'), $idx);
	}

	// Endpoint
	$ep = trim($peer['endpoint']);
	if (!empty($ep) && !is_hostname($ep) && !is_ipaddr($ep)) {
		$input_errors[] = sprintf(gettext(
		    'Peer %1$s: Endpoint must be a valid IPv4 or IPv6 ' .
		    'adress or hostname.'), $idx);
	}

	// Allowed IPs
	if (!empty($peer['allowedips'])) {
		foreach (explode(",", $peer['allowedips']) as $ip) {
			if (!is_subnet(trim($ip))) {
				$input_errors[] = sprintf(gettext(
				    'Peer %1$s: Address %2$s is not a valid ' .
				    'IPv4 or IPv6 CIDR subnet aadress.'),
					$idx, $ip);
			}
		}
	}

	return $input_errors;
}

// Setup all Wireguard tunnels
function wg_configure() {
	global $config;

	if (!is_array($config["wireguard"]["tunnel"])) {
		return;
	}

	if (platform_booting()) {
		echo gettext("Configuring Wireguard Tunnels... ");
	}

	wg_create_config_files();

	foreach ($config["wireguard"]["tunnel"] as $tunnel) {
		if (isset($tunnel['enabled']) && $tunnel['enabled'] == 'yes') {
			wg_configure_if($tunnel['name']);
		}
	}

	if (platform_booting()) {
		echo gettext("done.") . "\n";
	}
}

// Setup Wireguard tunnel
function wg_configure_if($wg_ifname) {
	global $config, $g;

	if (!is_array($config["wireguard"]["tunnel"])) {
		return;
	}

	unset($tunnel);
	foreach ($config["wireguard"]["tunnel"] as $tun) {
		if (isset($tun['enabled']) && $tun['enabled'] == 'yes' &&
		    $tun['name'] == $wg_ifname) {
			$tunnel = $tun;
			break;
		}
	}

	if (!is_array($tunnel)) {
		return;
	}

	echo $tunnel['name'] . " ";
	wg_destroy_if($tunnel['name']);
	//$if = pfSense_interface_create($tunnel['name']);
	$conf_path = $g["wg_conf_path"] . "/" . $tunnel['name'] . ".conf";
	/* XXX - workaround while pfSense-module is fixed */
	mwexec("/sbin/ifconfig " . escapeshellarg($tunnel['name']) . " create");
	mwexec("/usr/local/bin/wg setconf " . escapeshellarg($tunnel['name']) .
	    " " . escapeshellarg($conf_path));
	$ip4_first = true;
	$ip6_first = true;
	foreach (explode(",", $tunnel['interface']['address']) as $addr) {
		if (strstr($addr, "/") == false) {
			continue;
		}
		list($ip, $mask) = explode("/", trim($addr));
		if (is_ipaddrv4($ip)) {
			mwexec("/sbin/ifconfig " .
			    escapeshellarg($tunnel['name']) . " inet " .
			    escapeshellarg($ip) . " netmask " .
			    escapeshellarg(gen_subnet_mask($mask)) .
			    ($ip4_first ? "" : " alias"));
			$ip4_first = false;
		} elseif (is_ipaddrv6($ip)) {
			mwexec("/sbin/ifconfig " .
			    escapeshellarg($tunnel['name']) . " inet6 " .
			    escapeshellarg($ip) . " netmask " .
			    escapeshellarg(gen_subnet_mask($mask)) .
			    ($ip6_first ? "" : " alias"));
			$ip6_first = false;
		}
	}

	system_routing_configure(
	    convert_real_interface_to_friendly_interface_name($wg_ifname));
}

// Remove Wireguard tunnel
function wg_destroy_if($wg_ifname) {
	return (pfSense_interface_destroy($wg_ifname));
}

// Generate private key
function genKeyPair($json = false) {
	global $wgbinpath;

	$privkey = exec("{$wgbinpath} genkey");
	$pubkey = genPubKey($privkey);

	$res = array('privkey' => $privkey, 'pubkey' => $pubkey);
	return $json ? json_encode($res) : $res;
}

// Compose the public key from a provided private key
function genPubKey($privkey) {
	global $wgbinpath;

	return (exec("echo {$privkey} | {$wgbinpath} pubkey"));
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
	global $config, $g;

	if ($config['wireguard']['tunnel'][$tunidx]) {
		unset($ifname);
		unset($conf_path);
		if (isset($config['wireguard']['tunnel'][$tunidx]['name'])) {
			$ifname =
			    $config['wireguard']['tunnel'][$tunidx]['name'];
		}
		if (isset($ifname)) {
			$conf_path = $g["wg_conf_path"] . "/" . $ifname .
			    ".conf";
		}
		// Delete the tunnel configuration entry
		unset($config['wireguard']['tunnel'][$tunidx]);
		write_config("Wireguard tunnel {$index} updated.");

		// Delete the wg?.conf file
		if (isset($conf_path) && is_file($conf_path)) {
			unlink($conf_path);
		}

		// Destroy the deleted tunnel
		wg_destroy_if($ifname);
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
	$pconfig['interface']['publickey'] = $post['publickey'];

	if (isset($post['endpoint0'])) {
		// Peers section
		$pconfig['peers'] = array();
		$pconfig['peers']['wgpeer'] = array();

		$idx = 0;

		for (;;) {
			if (!$post['publickeyp'.$idx] &&
			    !$post['endpoint'.$idx] &&
			    !$post['allowedips'.$idx] &&
			    !$post['descr'.$idx] &&
			    !$post['persistentkeepalive'.$idx]) {
				break;
			}

			$peer = array();
			$peer['publickey'] = $post['publickeyp' . $idx];
			$peer['endpoint'] = $post['endpoint' . $idx];
			$peer['allowedips'] = $post['allowedips' . $idx];
			$peer['descr'] = $post['descp' . $idx];
			$peer['persistentkeepalive'] =
			    $post['persistentkeepalive' . $idx];
			$peer['presharedkey'] = $post['presharedkey' . $idx];
			$peer['port'] = $post['port' . $idx];
			$peer['peerwgaddr'] = $post['peerwgaddr' . $idx];
			$peer['peernwks'] = $post['peernwks' . $idx];

			$pconfig['peers']['wgpeer'][] = $peer;
			$idx++;
		}
	} else {
		unset($pconfig['peers']);
	}

	$input_errors = wg_validate_post($pconfig);

	if (!$input_errors) {
		$config['wireguard']['tunnel'][$index] = $pconfig;
		write_config("Wireguard tunnel {$index} updated.");
	}

	return(array('input_errors' => $input_errors, 'pconfig' => $pconfig));
}

/*
 * Read the Wireguard configurations from config.xml and create a number of
 * *.conf files for wg-quick to read
 */
function wg_create_config_files($clean = true) {
	global $config, $g;

	if ($config["wireguard"]["tunnel"]) {
		$wg_tunnels = $config["wireguard"]["tunnel"];
		$cfgpath = $g["wg_conf_path"];

		if (!file_exists($cfgpath)) {
			mkdir($cfgpath, 0700, true);
		} else {
			chmod($cfgpath, 0700);
		}

		if ($clean) {
			delete_wg_configs();
		}

		foreach ($wg_tunnels as $tunnel) {
			if (!empty($tunnel['enabled']) &&
			    $tunnel['enabled'] == 'yes') {
				make_wg_conf($tunnel);
			}
		}
	}
}

// Write each file
function make_wg_conf($tunnel) {
	global $g;

	$txt = "# This Wireguard config file has been created automatically. " .
	    "Do not edit!\n";

	$txt .= "# Description: {$tunnel['descr']}\n\n";

	// Process Interfaces section
	$txt .= "[Interface]\n";

	if (!empty($tunnel["interface"]["privatekey"])) {
		$txt .= "PrivateKey = {$tunnel["interface"]["privatekey"]}\n";
	}

	if (!empty($tunnel["interface"]["listenport"])) {
		$txt .= "ListenPort = {$tunnel["interface"]["listenport"]}\n";
	}

	$txt .= "\n";

	// Process peers section
	if (is_array($tunnel['peers']) && is_array($tunnel['peers']['wgpeer']) &&
	    count($tunnel['peers']['wgpeer']) > 0) {
		foreach ($tunnel['peers']['wgpeer'] as $peer) {
			$txt .= "# Peer: {$peer['descr']}\n";
			$txt .= "[Peer]\n";

			if (!empty($peer["publickey"])) {
				$txt .= "PublicKey = {$peer['publickey']}\n";
			}

			if (!empty($peer["endpoint"]) &&
			    !empty($peer["port"])) {
				$txt .= "EndPoint = " .
				    "{$peer['endpoint']}:{$peer['port']}\n";
			}

			if (!empty($peer["allowedips"])) {
				$txt .= "AllowedIPs = {$peer['allowedips']}\n";
			}

			if (!empty($peer["persistentkeepalive"])) {
				$txt .= "PersistentKeepalive = " .
				    "{$peer['persistentkeepalive']}\n";
			}

			if (!empty($peer["presharedkey"])) {
				$txt .= "PresharedKey = " .
				    "{$peer['presharedkey']}\n";
			}

			$txt .= "\n";
		}
	}

	file_put_contents($g["wg_conf_path"] . "/" . $tunnel['name'] . ".conf",
	    $txt);
	chmod($g["wg_conf_path"] . "/" . $tunnel['name'] . ".conf", 0600);
}

// Remove all wg config files from the conf directory
function delete_wg_configs() {
	global $g;

	unlink_if_exists($g["wg_conf_path"] . "/*.conf");
}

/* Check if at least one tunnel is enabled */
function is_wg_enabled() {
	global $config;

	init_config_arr(array('wireguard', 'tunnel'));

	foreach ($config['wireguard']['tunnel'] as $tunnel) {
		if (empty($tunnel['enabled'])) {
			continue;
		}
		return true;
	}

	return false;
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
				<peer>
					<publickey>xTIBA5rboUvnH4htodjb6e697QjLERt1NAB4mZqp8Dg=</publickey>
					<endpoint>192.88.8.88:51820</endpoint>
					<allowedips>10.192.122.1/32, 10.192.124.1/24</allowedips>
					<descr>Alice</descr>
				</peer>
				<peer>
					<publickey>xTIBA5rboUvnH4htodjb6e697QjLERt1NAB4mZqp8Dg=</publickey>
					<endpoint>192.88.8.88:51820</endpoint>
					<allowedips>10.192.122.2/32, 10.192.124.2/24</allowedips>
					<descr>Bob</descr>
				</peer>
				<peer>
					<publickey>xTIBA5rboUvnH4htodjb6e697QjLERt1NAB4mZqp8Dg=</publickey>
					<endpoint>192.88.8.88:51820</endpoint>
					<allowedips>10.192.122.3/32, 10.192.124.3/24</allowedips>
					<descr>Charlie</descr>
				</peer>
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
				<peer>
					<publickey>xTIBA5rboUvnH4htodjb6e697QjLERt1NAB4mZqp8Dg=</publickey>
					<endpoint>192.88.8.88:51820</endpoint>
					<allowedips>10.192.122.1/32, 10.192.124.1/24</allowedips>
					<persistentkeepalive>25</persistentkeepalive>
					<descr>Sam</descr>
				</peer>
			</peers>
		</tunnel>
	</wireguard>
*/
