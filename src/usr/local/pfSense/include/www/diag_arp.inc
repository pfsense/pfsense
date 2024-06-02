<?php
/*
 * diag_arp.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
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

require_once("config.gui.inc");

function prepare_ARP_table($json = false) {
	// Get DHCP lease hostnames by MAC address
	$leases = system_get_dhcpleases(false);
	$namesbymac = [];
	foreach ($leases['lease'] as $value) {
		if ((empty($value['hostname']) && $value['hostname'] != '0') ||
		    ($value["act"] == "expired")) {
			continue;
		}
		$namesbymac[$value['mac']] = $value['hostname'];
	}

	// Load MAC-Manufacturer table
	$mac_man = load_mac_manufacturer_table();

	$arp_table = system_get_arp_table();

	$i = 0;

	// if list
	$ifdescrs = get_configured_interface_with_descr();

	$hwif = $pfif = [];
	foreach ($ifdescrs as $key => $interface) {
		$thisif = convert_friendly_interface_to_real_interface_name($key);
		if (!empty($thisif)) {
			$hwif[$thisif] = $interface;
			$pfif[$thisif] = $key;
		}
	}

	// Resolve hostnames and replace Z_ with "".  The intention
	// is to sort the list by hostnames, alpha and then the non
	// resolvable addresses will appear last in the list.
	$dnsavailable = get_dnsavailable(AF_INET);
	foreach ($arp_table as &$entry) {
		// Add dnsresolve
		$dns="";
		if (!empty($entry['mac-address']) && isset($namesbymac[$entry['mac-address']])) {
			$dns = $namesbymac[$entry['mac-address']];
		} elseif (!empty($entry['ip-address']) && $dnsavailable) {
			$dns = resolve_address($entry['ip-address']);
		}

		if (trim($dns)) {
			$entry['dnsresolve'] = "$dns";
		} else {
			$entry['dnsresolve'] = "Z_ ";
		}

		// Fix MAC address
		if (empty($entry['mac-address'])) {
			$mac = '(' . gettext("Incomplete") .')';
			$entry['mac-address'] = $mac;
		} else {
			$mac = trim($entry['mac-address']);
			$entry['mac-address'] = $mac;

			$mac_hi = strtoupper($mac[0] . $mac[1] .
			    $mac[3] . $mac[4] . $mac[6] .
			    $mac[7]);

			if (isset($mac_man[$mac_hi])) {
				$entry['mac-address'] .= ' <small>('.
				    $mac_man[$mac_hi] .
				    ')</small>';
			}
		}

		// Fix expiration
		$status = '';
		if (!empty($entry['expires'])) {
			$status = sprintf(gettext(
			    "Expires in %d seconds"),
			    $entry['expires']);
		} else if (!empty($entry['permanent'])) {
			$status = gettext("Permanent");
		}

		$entry['expires'] = $status;

		// Fix dnsresolve
		$entry['dnsresolve'] = trim(str_replace("Z_ ", "", $entry['dnsresolve']));

		/* Use the interface friendly name only if it is assigned */
		$entry['assigned'] = false;
		if (array_key_exists($entry['interface'], $pfif)) {
			$entry['if'] = $pfif[$entry['interface']];
			$entry['interface'] = $hwif[$entry['interface']];
			$entry['assigned'] = true;
		}
	}

	// Sort the data alpha first
	$arp_table = msort($arp_table, "dnsresolve");

	if ($json) {
		$jstr = json_encode($arp_table);
		$jstr = str_replace("mac-address", "mac", $jstr);
		$jstr = str_replace("ip-address", "ip", $jstr);

		return $jstr;
	} else {
		return $arp_table;
	}

}
