<?php
/*
 * interfaces_fast.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2017 Peter Schofield (parts of this file)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2004 Manuel Kasper <mk@neon1.net>.
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
 *
 *
 * This file contains rewrites of several functions, from both interfaces.inc
 * and interfaces_assign.php. The general purpose of these rewrites is not
 * necessarily to be faster in and of themselves, though they may be, but to
 * replace functions called multiple times with a function that's called only
 * once. This results in a significant speedup because there are far fewer
 * function calls, fewer loops and suchlike. It does, however, increase memory
 * usage somewhat, but it shouldn't be significant in the grand scheme of things.
 *
 *
 * Functions in this file may not require/use parameters when called, however
 * in most cases they will accept parameters as per their original forms, for
 * consistency.

 */
require_once('interfaces.inc');
/*
* does_interface_exist_fast
Returns an array of interfaces which exist on the system.
The $interface parameter is not used, but is accepted for
consistency with the function it replaces.
*/
function does_interface_exist_fast($interface='', $flush = true) {
	global $config;

	$ints = get_interface_arr($flush);
	return array_flip($ints);
}

/*
 * convert_real_interface_to_friendly_interface_name_fast($interface): convert fxp0 -> wan, etc.
 Returns an array of interfaces and friendly names.
 */
function convert_real_interface_to_friendly_interface_name_fast() {
	global $config;

	$out = array();
	/* XXX: For speed reasons reference directly the interface array */
	init_config_arr(array('interfaces'));
	$ifdescrs = &$config['interfaces'];
	$iffriendlynames = array_keys($ifdescrs);
	$out = array_flip(get_real_interface_fast($iffriendlynames));
	return $out;
}

/*
 * get_real_interface_fast($interfaces, ...)
 * Exactly the same as it's namesake, except it takes an array of interfaces and returns an array
 *
 */

function get_real_interface_fast($interfaces = array(), $family = "all", $realv6iface = false, $flush = true) {
	global $config, $g;

	$existing_ifs = does_interface_exist_fast();

	$out = array();
	foreach ($interfaces as $interface) {
		$wanif = NULL;

		switch ($interface) {
			case "l2tp":
				$wanif = "l2tp";
				break;
			case "pptp":
				$wanif = "pptp";
				break;
			case "pppoe":
				$wanif = "pppoe";
				break;
			case "openvpn":
				$wanif = "openvpn";
				break;
			case "IPsec":
			case "ipsec":
			case "enc0":
				$wanif = "enc0";
				break;
			case "ppp":
				$wanif = "ppp";
				break;
			default:
				if (substr($interface, 0, 4) == '_vip') {
					$wanif = get_configured_vip_interface($interface);
					if (!empty($wanif)) {
						$wanif = get_real_interface($wanif);
					}
					break;
				} else if (substr($interface, 0, 5) == '_lloc') {
					$interface = substr($interface, 5);
				} else if (strstr($interface, "_vlan") || isset($existing_ifs[$interface])) {
					/*
					 * If a real interface was already passed simply
					 * pass the real interface back.  This encourages
					 * the usage of this function in more cases so that
					 * we can combine logic for more flexibility.
					 */
					$wanif = $interface;
					break;
				}

				if (empty($config['interfaces'][$interface])) {
					break;
				}

				$cfg = &$config['interfaces'][$interface];

				if ($family == "inet6") {
					switch ($cfg['ipaddrv6']) {
						case "6rd":
						case "6to4":
							$wanif = "{$interface}_stf";
							break;
						case 'pppoe':
						case 'ppp':
						case 'l2tp':
						case 'pptp':
							if (is_array($cfg['wireless']) || preg_match($g['wireless_regex'], $cfg['if'])) {
								$wanif = interface_get_wireless_clone($cfg['if']);
							} else {
								$wanif = $cfg['if'];
							}
							break;
						default:
							switch ($cfg['ipaddr']) {
								case 'pppoe':
								case 'ppp':
								case 'l2tp':
								case 'pptp':
									if (isset($cfg['dhcp6usev4iface']) && $realv6iface === false) {
										$wanif = $cfg['if'];
									} else {
										$parents = get_parent_interface($interface);
										if (!empty($parents[0])) {
											$wanif = $parents[0];
										} else {
											$wanif = $cfg['if'];
										}
									}
									break;
								default:
									if (is_array($cfg['wireless']) || preg_match($g['wireless_regex'], $cfg['if'])) {
										$wanif = interface_get_wireless_clone($cfg['if']);
									} else {
										$wanif = $cfg['if'];
									}
									break;
							}
							break;
					}
				} else {
					// Wireless cloned NIC support (FreeBSD 8+)
					// interface name format: $parentnic_wlanparentnic#
					// example: ath0_wlan0
					if (is_array($cfg['wireless']) || preg_match($g['wireless_regex'], $cfg['if'])) {
						$wanif = interface_get_wireless_clone($cfg['if']);
					} else {
						$wanif = $cfg['if'];
					}
				}
				break;
		}
		$out[$interface] = $wanif;
	}

	return $out;
}
/*
 * interface_assign_description_fast($portlist, $friendlyifnames)
 *
 * This function replaces the function defined in interfaces_assign.php
 *
 * I created this version of the function because in interfaces_assign.php
 * the interface_assign_description() function is used twice, in both cases
 * being called for every iteration through the array of interfaces, and
 * was seemingly dragging the performance of the HTML generation code down
 * when faced with a large number of VLAN interfaces.
 *
 * Although this function internally recreates the loop that its namesake was
 * called in; the fact it's only called once rather than once per interface * 2
 * has resulted in a significant speed gain with a large number of optional
 * interfaces configured.
 *
 * $portlist is the same $portlist as defined in interfaces_assign.php, call this
 * function after all the optional interfaces are added to $portlist.
 *
 * $friendlyifnames is a global variable of my own making, created by calling
 * convert_real_interface_to_friendly_interface_name_fast() on the keys of $portlist.
 *
 * Return value of this function is an associative array of interface descriptions
 * indexed by the unique name of the interface.
 *
 */
function interface_assign_description_fast($portlist, $friendlyifnames) {
	global $ovpn_descrs, $ipsec_descrs;
	$out = array();
	$gettext = gettext('on');
	foreach($portlist as $portname => $portinfo) {
		if ($portinfo['isvlan']) {
			$descr = sprintf('VLAN %1$s '.$gettext.' %2$s', $portinfo['tag'], $portinfo['if']);
			$iface = $friendlyifnames[$portinfo['if']];
			if (isset($iface) && strlen($iface) > 0) {
				$descr .= " - $iface";
			}
			if ($portinfo['descr']) {
				$descr .= " (" . $portinfo['descr'] . ")";
			}
		} elseif ($portinfo['iswlclone']) {
			$descr = $portinfo['cloneif'];
			if ($portinfo['descr']) {
				$descr .= " (" . $portinfo['descr'] . ")";
			}
		} elseif ($portinfo['isppp']) {
			$descr = $portinfo['descr'];
		} elseif ($portinfo['isbridge']) {
			$descr = strtoupper($portinfo['bridgeif']);
			if ($portinfo['descr']) {
				$descr .= " (" . $portinfo['descr'] . ")";
			}
		} elseif ($portinfo['isgre']) {
			$descr = "GRE {$portinfo['remote-addr']}";
			if ($portinfo['descr']) {
				$descr .= " (" . $portinfo['descr'] . ")";
			}
		} elseif ($portinfo['isgif']) {
			$descr = "GIF {$portinfo['remote-addr']}";
			if ($portinfo['descr']) {
				$descr .= " (" . $portinfo['descr'] . ")";
			}
		} elseif ($portinfo['isvxlan']) {
			$descr = "VXLAN {$portinfo['remote-addr']}";
			if ($portinfo['descr']) {
				$descr .= " (" . $portinfo['descr'] . ")";
			}
		} elseif ($portinfo['islagg']) {
			$descr = strtoupper($portinfo['laggif']);
			if ($portinfo['descr']) {
				$descr .= " (" . $portinfo['descr'] . ")";
			}
		} elseif ($portinfo['isqinq']) {
			$descr = $portinfo['descr'];
		} elseif (substr($portname, 0, 4) == 'ovpn') {
			$descr = $portname . " (" . $ovpn_descrs[substr($portname, 5)] . ")";
		} elseif (substr($portname, 0, 5) == 'ipsec') {
			$descr = $portname . " (" . $ipsec_descrs[$portname] . ")";
		} else {
			$descr = $portname . " (" . $portinfo['mac'] . ")";
		}
		$out[$portname] = htmlspecialchars($descr);
	}
	return $out;
}
?>
