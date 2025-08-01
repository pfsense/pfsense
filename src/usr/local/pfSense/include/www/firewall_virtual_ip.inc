<?php
/*
 * firewall_virtual_ip.inc.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
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
function find_last_used_vhid() {
	$vhid = 0;
	foreach (config_get_path('virtualip/vip', []) as $vip) {
		if ($vip['vhid'] > $vhid) {
			$vhid = $vip['vhid'];
		}
	}

	return $vhid;
}

// Get VIP configuration
function getVIPs($json = false) {
	$vips = array();

	foreach (config_get_path('virtualip/vip', []) as $vip) {
		$vips[] = $vip;
	}

	$rv = array();
	$rv['virtualips'] = $vips;

	return $json ? json_encode($rv) : $rv;
}

// Save virtual IP changes
function saveVIP($post, $json = false) {
	global $g;

	$a_vip = config_get_path('virtualip/vip', []);
	$rv = array();
	$id = is_numericint($post['id']) ? $post['id'] : null;

	$input_errors = array();

	/* input validation */
	$reqdfields = explode(" ", "mode");
	$reqdfieldsn = array(gettext("Type"));

	if (!$json) {
		do_input_validation($post, $reqdfields, $reqdfieldsn, $input_errors);
	}

	if ($post['subnet']) {
		$post['subnet'] = trim($post['subnet']);
	}

	if (is_pseudo_interface(convert_friendly_interface_to_real_interface_name($post['interface']))) {
		if ($post['mode'] == 'carp') {
			$input_errors[] = gettext("The interface chosen for the VIP does not support CARP mode.");
		} elseif ($post['mode'] == 'proxyarp') {
			$input_errors[] = gettext("The interface chosen for the VIP does not support Proxy ARP mode.");
		}
	}

	if ($post['subnet']) {
		if (!is_ipaddr($post['subnet'])) {
			$input_errors[] = gettext("A valid IP address must be specified.");
		} else {
			if (isset($id) && isset($a_vip[$id])) {
				$ignore_if = $a_vip[$id]['interface'];
				$ignore_mode = $a_vip[$id]['mode'];
				if (isset($a_vip[$id]['uniqid']))
					$ignore_uniqid = $a_vip[$id]['uniqid'];
			} else {
				$ignore_if = $post['interface'];
				$ignore_mode = $post['mode'];
			}

			if (!isset($ignore_uniqid))
				$ignore_uniqid = $post['uniqid'];

			if ($ignore_mode == 'carp' || $ignore_mode == 'ipalias')
				$ignore_if = "_vip{$ignore_uniqid}";

			if (is_ipaddr_configured($post['subnet'], $ignore_if)) {
				$input_errors[] = gettext("This IP address is being used by another interface or VIP.");
			}

			unset($ignore_if, $ignore_mode);
		}
	}

	$natiflist = get_configured_interface_with_descr();
	foreach (array_keys($natiflist) as $natif) {
		if ($post['interface'] == $natif &&
			(empty(config_get_path("interfaces/{$natif}/ipaddr")) &&
			 empty(config_get_path("interfaces/{$natif}/ipaddrv6")))) {
			$input_errors[] = gettext("The interface chosen for the VIP has no IPv4 or IPv6 address configured so it cannot be used as a parent for the VIP.");
		}
	}

	/* ipalias and carp should not use network or broadcast address */
	if ($post['mode'] == "ipalias" || $post['mode'] == "carp") {
		if (is_ipaddrv4($post['subnet']) && $post['subnet_bits'] != "32" && $post['subnet_bits'] != "31") {
			$network_addr = gen_subnet($post['subnet'], $post['subnet_bits']);
			$broadcast_addr = gen_subnet_max($post['subnet'], $post['subnet_bits']);
		}

		if (isset($network_addr) && $post['subnet'] == $network_addr) {
			$input_errors[] = gettext("The network address cannot be used for this VIP");
		} else if (isset($broadcast_addr) && $post['subnet'] == $broadcast_addr) {
			$input_errors[] = gettext("The broadcast address cannot be used for this VIP");
		}
	}

	/* make sure new ip is within the subnet of a valid ip
	 * on one of our interfaces (wan, lan optX)
	 */
	switch ($post['mode']) {
		case 'carp':
			/* verify against reusage of vhids */
			$idtracker = 0;
			foreach (config_get_path('virtualip/vip', []) as $vip) {
				if ($vip['vhid'] == $post['vhid'] && $vip['interface'] == $post['interface'] && $idtracker != $id) {
					$input_errors[] = sprintf(gettext('VHID %1$s is already in use on interface %2$s. Pick a unique number on this interface.'), $post['vhid'], convert_friendly_interface_to_friendly_descr($post['interface']));
				}
				$idtracker++;
			}

			if (empty($post['password'])) {
				$input_errors[] = gettext("A CARP password that is shared between the two VHID members must be specified.");
			}

			if ($post['password'] != $post['password_confirm']) {
				$input_errors[] = gettext("Password and confirm password must match");
			}

			if ($post['interface'] == 'lo0') {
				$input_errors[] = gettext("For this type of vip localhost is not allowed.");
			} else if (strstr($post['interface'], '_vip')) {
				$input_errors[] = gettext("A CARP parent interface can only be used with IP Alias type Virtual IPs.");
			}

			break;
		case 'ipalias':
			/* verify IP alias on CARP has proper address family */
			if (strstr($post['interface'], '_vip')) {
				$vipif = get_configured_vip($post['interface']);
				if (is_ipaddrv4($post['subnet']) && is_ipaddrv6($vipif['subnet'])) {
					$input_errors[] = gettext("An IPv4 Virtual IP cannot have an IPv6 CARP parent.");
				}
				if (is_ipaddrv6($post['subnet']) && is_ipaddrv4($vipif['subnet'])) {
					$input_errors[] = gettext("An IPv6 Virtual IP cannot have an IPv4 CARP parent.");
				}
			}
			break;
		default:
			if ($post['interface'] == 'lo0') {
				$input_errors[] = gettext("For this type of vip localhost is not allowed.");
			} else if (strstr($post['interface'], '_vip')) {
				$input_errors[] = gettext("A CARP parent interface can only be used with IP Alias type Virtual IPs.");
			}

			break;
	}

	if ($post['mode'] == 'proxyarp' && is_v6($post['subnet'])) {
		$input_errors[] = gettext("Only IPv4 addresses are valid for Proxy ARP VIPs.");
	}

	if (!$input_errors) {
		$vipent = array();

		$vipent['mode'] = $post['mode'];
		$vipent['interface'] = $post['interface'];

		/* ProxyARP & Other specific fields */
		if (($post['mode'] === "proxyarp") || ($post['mode'] === "other")) {
			$vipent['noexpand'] = isset($post['noexpand']);
		}

		/* CARP specific fields */
		if ($post['mode'] === "carp") {
			$vipent['vhid'] = $post['vhid'];
			$vipent['advskew'] = $post['advskew'];
			$vipent['advbase'] = $post['advbase'];

			if ($post['password'] != DMYPWD) {
				$vipent['password'] = $post['password'];
			} else {
				$vipent['password'] = $a_vip[$id]['password'];
			}
		}

		/* IPalias and CARP should have a uniqid */
		if ($post['mode'] === "carp" || $post['mode'] === "ipalias") {
			if (empty($post['uniqid'])) {
				// if we changed a 'parp' or 'other' alias to 'carp'/'ipalias' it needs a uniqid
				$vipent['uniqid'] = uniqid();
			} else {
				$vipent['uniqid'] = $post['uniqid'];
			}
		}

		/* Common fields */
		$vipent['descr'] = $post['descr'];
		if (isset($post['type'])) {
			$vipent['type'] = $post['type'];
		} else {
			$vipent['type'] = "single";
		}

		if ($vipent['type'] == "single" || $vipent['type'] == "network") {
			if (!isset($post['subnet_bits'])) {
				$vipent['subnet_bits'] = "32";
			} else {
				$vipent['subnet_bits'] = $post['subnet_bits'];
			}

			$vipent['subnet'] = $post['subnet'];
		}

		if (!isset($id)) {
			$id = count($a_vip);
		}

		if (file_exists("{$g['tmp_path']}/.firewall_virtual_ip.apply")) {
			$toapplylist = unserialize_data(file_get_contents("{$g['tmp_path']}/.firewall_virtual_ip.apply"), []);
		} else {
			$toapplylist = array();
		}

		$toapplylist[$id] = $a_vip[$id];

		if (!empty($a_vip[$id])) {
			/* modify all virtual IP rules with this address */
			$rdr_rules = get_anynat_rules_list('rdr');
			foreach ($rdr_rules as &$rule) {
				if (array_get_path($rule, 'destination/address') == $a_vip[$id]['subnet']) {
					array_set_path($rule, 'destination/address', $vipent['subnet']);
				}
			}
			unset($rule);
			set_anynat_rules_list('rdr', $rdr_rules);

			$nat_rules = get_anynat_rules_list('nat');
			foreach ($nat_rules as &$rule) {
				if (array_get_path($rule, 'target') == $a_vip[$id]['subnet']) {
					array_set_path($rule, 'target', $vipent['subnet']);
				}
			}
			unset($rule);
			set_anynat_rules_list('nat', $nat_rules);
		}

		config_set_path("virtualip/vip/{$id}", $vipent);
		if (write_config(gettext("Saved/edited a virtual IP."))) {
			mark_subsystem_dirty('vip');
			file_put_contents("{$g['tmp_path']}/.firewall_virtual_ip.apply", serialize($toapplylist));
		}

		if (!$json) {
			header("Location: firewall_virtual_ip.php");
			exit;
		}
	}

	$rv['input_errors'] = $input_errors;
	$rv['a_vip'] = $a_vip;
	$rv['id'] = $id;

	return $json? json_encode($rv) : $rv;
}

// Apply changes
function applyVIP() {
	global $g;

	$a_vip = config_get_path('virtualip/vip', []);
	$rv = array();

	$check_carp = false;
	if (file_exists("{$g['tmp_path']}/.firewall_virtual_ip.apply")) {
		$toapplylist = unserialize_data(file_get_contents("{$g['tmp_path']}/.firewall_virtual_ip.apply"), []);
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
						if (does_vip_exist($a_vip[$vid]) && isset($ovip['vhid']) &&
						    ($ovip['vhid'] ^ $a_vip[$vid]['vhid'])) {
							$ipalias_reload = true;
						} else {
							$ipalias_reload = false;
						}
						interface_carp_configure($a_vip[$vid], false, $ipalias_reload);
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
	if ($check_carp === true) {
		enable_carp();
	}


	filter_configure();
	clear_subsystem_dirty('vip');

	/* NB: retval here is historical */
	$rv['retval'] = 0;
	return $rv;
}

// Delete a VIP
function deleteVIP($id, $json = false) {
	$rv = array();
	$input_errors = array();

	$a_vip = config_get_path('virtualip/vip', []);

	if ($a_vip[$id]) {
		/* make sure no inbound NAT mappings reference this entry */
		foreach (get_anynat_rules_list('rdr') as $rule) {
			if ($rule['destination']['address'] != "") {
				if ($rule['destination']['address'] == $a_vip[$id]['subnet']) {
					$input_errors[] = gettext("This entry cannot be deleted because it is still referenced by at least one NAT mapping.");
					break;
				}
			}
		}
		/* make sure no outbound NAT mappings reference this entry */
		foreach (get_anynat_rules_list('nat') as $rule) {
			if ($rule['target'] != "") {
				if ($rule['target'] == $a_vip[$id]['subnet']) {
					$input_errors[] = gettext("This entry cannot be deleted because it is still referenced by at least one Outbound NAT mapping.");
					break;
				}
			}
		}


		/* make sure no OpenVPN server or client references this entry */
		$openvpn_types_a = array("openvpn-server" => gettext("server"), "openvpn-client" => gettext("client"));
		foreach ($openvpn_types_a as $openvpn_type => $openvpn_type_text) {
			foreach (config_get_path("openvpn/{$openvpn_type}", []) as $openvpn) {
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

		foreach (config_get_path('ipsec/phase1', []) as $ph1ent) {
			if (!isset($ph1ent['disabled'])) {
				foreach (explode(',', ipsec_get_phase1_src($ph1ent)) as $ifip) {
					if ($ifip == $a_vip[$id]['subnet']) {
						if (strlen($ph1ent['descr'])) {
							$ipsec_desc = $ph1ent['descr'];
						} elseif (isset($ph1ent['mobile'])) {
							$ipsec_desc = "Mobile on " . convert_friendly_interface_to_friendly_descr($ph1ent['interface']);
						} else {
							$ipsec_desc = "on " . convert_friendly_interface_to_friendly_descr($ph1ent['interface']) .
							    " to " . $ph1ent['remote-gateway'];
						}
						$input_errors[] = sprintf(gettext('This entry cannot be deleted because it is still referenced by IPsec %1$s.'), $ipsec_desc);
						break 2;
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
		foreach (config_get_path('gateways/gateway_item', []) as $gateway) {
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
			$found_carp = null;
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
						$found_carp = $vip_id;
					} else if ($vip['mode'] == "ipalias") {
						$found_other_alias = true;
					}
				}
			}

			if (isset($a_vip[$found_carp]) && $found_other_alias === false && $found_if === false) {
				$input_errors[] = sprintf(gettext("This entry cannot be deleted because it is still referenced by a CARP IP with the description %s."), $a_vip[$found_carp]['descr']);
			}
		} else if ($a_vip[$id]['mode'] == "carp") {
			$vipiface = "_vip{$a_vip[$id]['uniqid']}";
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
				$user = $user['item'];

				if (is_array($user) && userHasPrivilege($user, "user-config-readonly")) {
					header("Location: firewall_virtual_ip.php");
					phpsession_end();
					exit;
				}
				phpsession_end();
			}

			config_del_path("virtualip/vip/{$id}");
			if (count($a_vip) == 0) {
				config_del_path("virtualip/vip");
			}
			write_config(gettext("Deleted a virtual IP."));

			// Special case since every proxyarp vip is handled by the same daemon.
			if ($a_vip[$id]['mode'] == "proxyarp") {
				$viface = $a_vip[$id]['interface'];
				interface_proxyarp_configure($viface);
			} else {
				interface_vip_bring_down($a_vip[$id]);
			}

			/* Reload filter since removed address may trigger changes in rules
			 * https://redmine.pfsense.org/issues/13908 */
			filter_configure();

			if (!$json) {
				header("Location: firewall_virtual_ip.php");
				exit;
			}
		}
	} else {
		$input_errors[] = sprintf(gettext("Virtual IP # %s does not exist."), $id);
	}

	$rv['input_errors'] = $input_errors;

	return $json ? json_encode($rv) : $rv;
}
?>
