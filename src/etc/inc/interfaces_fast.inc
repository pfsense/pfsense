<?php
/*
 * interfaces_fast.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate) (pfSense)
 * Copyright (c) 2017 Peter Schofield (parts of this file)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *
 * 3. All advertising materials mentioning features or use of this software
 *    must display the following acknowledgment:
 *    "This product includes software developed by the pfSense Project
 *    for use in the pfSense® software distribution. (http://www.pfsense.org/).
 *
 * 4. The names "pfSense" and "pfSense Project" must not be used to
 *    endorse or promote products derived from this software without
 *    prior written permission. For written permission, please contact
 *    coreteam@pfsense.org.
 *
 * 5. Products derived from this software may not be called "pfSense"
 *    nor may "pfSense" appear in their names without prior written
 *    permission of the Electric Sheep Fencing, LLC.
 *
 * 6. Redistributions of any form whatsoever must retain the following
 *    acknowledgment:
 *
 * "This product includes software developed by the pfSense Project
 * for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 * THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 * EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 * ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 * STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 * OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *
 * This file contains rewrites of several functions, from both interfaces.inc
 * and interfaces_assign.php. The general purpose of these rewrites is not
 * necessarily to be faster in and of themselves, though they may be, but to
 * replace functions called multiple times with a function that's called only
 * once. This results in a significant speedup because there are far fewer
 * function calls, fewer loops and suchlike. It does, however, increase memory
 * usage somewhat, but it shouldnt be significant in the grand scheme of things.
 *
 *
 * Functions in this file may not require/use parameters when called, however 
 * in most cases they will accept parameters as per their original forms, for 
 * consistency.
 
 */
require_once('interfaces.inc');
/*
* does_interface_exist_fast
Returns an array of interrfaces which exist on the system.
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
 Doesn't actually use any parameters, they're just accepted for consistency with the function it replaces
 */
function convert_real_interface_to_friendly_interface_name_fast($interfaces = array(), $checkparent = false) {
	global $config;

	$out = array();
	/* XXX: For speed reasons reference directly the interface array */
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
	global $ovpn_descrs;
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
		} elseif ($portinfo['islagg']) {
			$descr = strtoupper($portinfo['laggif']);
			if ($portinfo['descr']) {
				$descr .= " (" . $portinfo['descr'] . ")";
			}
		} elseif ($portinfo['isqinq']) {
			$descr = $portinfo['descr'];
		} elseif (substr($portname, 0, 4) == 'ovpn') {
			$descr = $portname . " (" . $ovpn_descrs[substr($portname, 5)] . ")";
		} else {
			$descr = $portname . " (" . $portinfo['mac'] . ")";
		}
		$out[$portname] = htmlspecialchars($descr);
	}
	return $out;
}
?>