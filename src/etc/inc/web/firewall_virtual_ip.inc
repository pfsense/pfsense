<?php
/*
 * firewall_virtual_ip.inc.inc
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

require_once("config.gui.inc");
require_once("util.inc");
require_once("interfaces.inc");
require_once("pfsense-utils.inc");

// Functions included by firewall_virtual_ip.php =============================
// Apply changes
function applyVIP() {
	$rv = array();

	$check_carp = false;
	if (file_exists("{$g['tmp_path']}/.firewall_virtual_ip.apply")) {
		$toapplylist = unserialize(file_get_contents("{$g['tmp_path']}/.firewall_virtual_ip.apply"));
		foreach ($toapplylist as $vid => $ovip) {
			if (!empty($ovip)) {
				interface_vip_bring_down($ovip);
			}
			if ($a_vip[$vid]) {
				switch ($a_vip[$vid]['mode']) {
					case "ipalias":
						interface_ipalias_configure($a_vip[$vid]);
						break;
					case "proxyarp":
						interface_proxyarp_configure($a_vip[$vid]['interface']);
						break;
					case "carp":
						$check_carp = true;
						interface_carp_configure($a_vip[$vid]);
						break;
					default:
						break;
				}
				/* restart choparp on VIP change, see #7379 */
				if ($a_vip[$vid]['mode'] != 'proxyarp') {
					foreach ($a_vip as $avip) { 
						if (($avip['interface'] == $a_vip[$vid]['interface']) &&
						    ($avip['mode'] == 'proxyarp')) {
							interface_proxyarp_configure($a_vip[$vid]['interface']);
							break;
						}
					}
				}
			}
		}

		@unlink("{$g['tmp_path']}/.firewall_virtual_ip.apply");
	}
	/* Before changing check #4633 */
	if ($check_carp === true && !get_carp_status()) {
		set_single_sysctl("net.inet.carp.allow", "1");
	}

	$retval = 0;
	$retval |= filter_configure();

	clear_subsystem_dirty('vip');

	$rv['retval'] = $retval;
	return $rv;
}

// Delete a VIP
function deleteVIP($id, $json = false) {
	global $config;

	$rv = array();
	$input_errors = array();

	init_config_arr(array('virtualip', 'vip'));
	$a_vip = &$config['virtualip']['vip'];

	if ($a_vip[$id]) {
		/* make sure no inbound NAT mappings reference this entry */
		if (is_array($config['nat']['rule'])) {
			foreach ($config['nat']['rule'] as $rule) {
				if ($rule['destination']['address'] != "") {
					if ($rule['destination']['address'] == $a_vip[$id]['subnet']) {
						$input_errors[] = gettext("This entry cannot be deleted because it is still referenced by at least one NAT mapping.");
						break;
					}
				}
			}
		}

		/* make sure no OpenVPN server or client references this entry */
		$openvpn_types_a = array("openvpn-server" => gettext("server"), "openvpn-client" => gettext("client"));
		foreach ($openvpn_types_a as $openvpn_type => $openvpn_type_text) {
			if (is_array($config['openvpn'][$openvpn_type])) {
				foreach ($config['openvpn'][$openvpn_type] as $openvpn) {
					if ($openvpn['ipaddr'] <> "") {
						if ($openvpn['ipaddr'] == $a_vip[$id]['subnet']) {
							if (strlen($openvpn['description'])) {
								$openvpn_desc = $openvpn['description'];
							} else {
								$openvpn_desc = $openvpn['ipaddr'] . ":" . $openvpn['local_port'];
							}
							$input_errors[] = sprintf(gettext('This entry cannot be deleted because it is still referenced by OpenVPN %1$s %2$s.'), $openvpn_type_text, $openvpn_desc);
							break;
						}
					}
				}
			}
		}

		if (is_ipaddrv6($a_vip[$id]['subnet'])) {
			$is_ipv6 = true;
			$subnet = gen_subnetv6($a_vip[$id]['subnet'], $a_vip[$id]['subnet_bits']);
			$if_subnet_bits = get_interface_subnetv6($a_vip[$id]['interface']);
			$if_subnet = gen_subnetv6(get_interface_ipv6($a_vip[$id]['interface']), $if_subnet_bits);
		} else {
			$is_ipv6 = false;
			$subnet = gen_subnet($a_vip[$id]['subnet'], $a_vip[$id]['subnet_bits']);
			$if_subnet_bits = get_interface_subnet($a_vip[$id]['interface']);
			$if_subnet = gen_subnet(get_interface_ip($a_vip[$id]['interface']), $if_subnet_bits);
		}

		$subnet .= "/" . $a_vip[$id]['subnet_bits'];
		$if_subnet .= "/" . $if_subnet_bits;

		/* Determine if this VIP is in the same subnet as any gateway
		 * which can only be reached by VIPs */
		$viponlygws = array();
		if (is_array($config['gateways']['gateway_item'])) {
			foreach ($config['gateways']['gateway_item'] as $gateway) {
				if ($a_vip[$id]['interface'] != $gateway['interface']) {
					continue;
				}
				if ($is_ipv6 && $gateway['ipprotocol'] == 'inet') {
					continue;
				}
				if (!$is_ipv6 && $gateway['ipprotocol'] == 'inet6') {
					continue;
				}
				if (ip_in_subnet($gateway['gateway'], $if_subnet)) {
					continue;
				}
				if (ip_in_subnet($gateway['gateway'], $subnet)) {
					$viponlygws[] = $gateway;
				}
			}
		}

		/*
		 * If gateways for this subnet are only reachable via VIPs,
		 * make sure this is not the last VIP through which that gateway
		 * can be reached. See https://redmine.pfsense.org/issues/4438
		 */
		foreach ($viponlygws as $vgw) {
			$numrefs = 0;
			foreach ($a_vip as $refvip) {
				if (($refvip['interface'] != $vgw['interface']) ||
				    (is_ipaddrv4($refvip['subnet']) && ($vgw['ipprotocol'] == 'inet6')) ||
				    (is_ipaddrv6($refvip['subnet']) && ($vgw['ipprotocol'] == 'inet'))) {
					continue;
				}
				if (ip_in_subnet($vgw['gateway'],
				    gen_subnet($refvip['subnet'], $refvip['subnet_bits']) . '/' . $refvip['subnet_bits'])) {
					$numrefs++;
				}
			}
			if ($numrefs <= 1) {
				$input_errors[] = sprintf(gettext("This entry cannot be deleted because it is required to reach Gateway: %s."), $vgw['name']);
				break;
			}
		}

		if ($a_vip[$id]['mode'] == "ipalias") {
			$subnet = gen_subnet($a_vip[$id]['subnet'], $a_vip[$id]['subnet_bits']) . "/" . $a_vip[$id]['subnet_bits'];
			$found_if = false;
			$found_carp = false;
			$found_other_alias = false;

			if ($subnet == $if_subnet) {
				$found_if = true;
			}

			$vipiface = $a_vip[$id]['interface'];

			foreach ($a_vip as $vip_id => $vip) {
				if ($vip_id == $id) {
					continue;
				}

				if ($vip['interface'] == $vipiface && ip_in_subnet($vip['subnet'], $subnet)) {
					if ($vip['mode'] == "carp") {
						$found_carp = true;
					} else if ($vip['mode'] == "ipalias") {
						$found_other_alias = true;
					}
				}
			}

			if ($found_carp === true && $found_other_alias === false && $found_if === false) {
				$input_errors[] = sprintf(gettext("This entry cannot be deleted because it is still referenced by a CARP IP with the description %s."), $vip['descr']);
			}
		} else if ($a_vip[$id]['mode'] == "carp") {
			$vipiface = "{$a_vip[$id]['interface']}_vip{$a_vip[$id]['vhid']}";
			foreach ($a_vip as $vip) {
				if ($vipiface == $vip['interface'] && $vip['mode'] == "ipalias") {
					$input_errors[] = sprintf(gettext("This entry cannot be deleted because it is still referenced by an IP alias entry with the description %s."), $vip['descr']);
				}
			}
		}

		if (!$input_errors) {
			if (!$json) {
				phpsession_begin();
				$user = getUserEntry($_SESSION['Username']);

				if (is_array($user) && userHasPrivilege($user, "user-config-readonly")) {
					header("Location: firewall_virtual_ip.php");
					phpsession_end();
					exit;
				}
				phpsession_end();
			}


			// Special case since every proxyarp vip is handled by the same daemon.
			if ($a_vip[$id]['mode'] == "proxyarp") {
				$viface = $a_vip[$id]['interface'];
				unset($a_vip[$id]);
				interface_proxyarp_configure($viface);
			} else {
				interface_vip_bring_down($a_vip[$id]);
				unset($a_vip[$id]);
			}
			if (count($config['virtualip']['vip']) == 0) {
				unset($config['virtualip']['vip']);
			}

			write_config(gettext("Deleted a virtual IP."));

			if (!$json) {
				header("Location: firewall_virtual_ip.php");
				exit;
			}
		} else {
			$input_errors[] = sprintf(gettext("Virtual IP # %s does not exist."), $id);
		}
	}

	$rv['input_errors'] = $input_errors;

	return $json ? json_encode($rv) : $rv;
}
?>
