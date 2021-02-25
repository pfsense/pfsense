<?php
/*
 * firewall_nat.inc
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
require_once("interfaces.inc");
require_once("util.inc");
require_once("pfsense-utils.inc");
require_once("ipsec.inc");
require_once("wg.inc");

$specialsrcdst = explode(" ", "any pptp pppoe l2tp openvpn");
$ifdisp = get_configured_interface_with_descr();
foreach ($ifdisp as $kif => $kdescr) {
	$specialsrcdst[] = "{$kif}";
	$specialsrcdst[] = "{$kif}ip";
}

function MVC_is_specialnet($net) {
	global $specialsrcdst;

	if (!$net) {
		return false;
	}
	if (in_array($net, $specialsrcdst)) {
		return true;
	} else {
		return false;
	}
}

function MVC_build_dsttype_list() {
	global $config, $ifdisp;

	$list = array('any' => gettext('Any'), 'single' => gettext('Single host or alias'), 'network' => gettext('Network'));
	$list['pppoe'] = gettext('PPPoE clients');
	$list['l2tp'] = gettext('L2TP clients');

	foreach ($ifdisp as $if => $ifdesc) {
		$list[$if] = $ifdesc;
		$list[$if . 'ip'] = $ifdesc . ' address';
	}

	$list['(self)'] = gettext("This firewall (self)");

	if (is_array($config['virtualip']['vip'])) {
		foreach ($config['virtualip']['vip'] as $sn) {
			if (($sn['mode'] == "proxyarp" || $sn['mode'] == "other") && $sn['type'] == "network") {
				$list[$sn['subnet'] . '/' . $sn['subnet_bits']] = 'Subnet: ' . $sn['subnet'] . '/' . $sn['subnet_bits'] . ' (' . $sn['descr'] . ')';
				if (isset($sn['noexpand'])) {
					continue;
				}
				$start = ip2long32(gen_subnet($sn['subnet'], $sn['subnet_bits']));
				$end = ip2long32(gen_subnet_max($sn['subnet'], $sn['subnet_bits']));
				$len = $end - $start;
				for ($i = 0; $i <= $len; $i++) {
					$snip = long2ip32($start+$i);

					$list[$snip] = $snip . ' (' . $sn['descr'] . ')';
				}
			} else {
				$list[$sn['subnet']] = $sn['subnet'] . ' (' . $sn['descr'] . ')';
			}
		}
	}

	$rv = array();

	foreach ($list as $ifent => $ifname) {
		$rv[] = array("text" => $ifname, "value" => $ifent);
	}

	return(json_encode($rv));
}

function MVC_filter_get_interface_list() {
	$iflist = MVC_create_interface_list();
	$filter_ifs = array();

	foreach ($iflist as $ifent => $ifname) {
		$filter_ifs[] = array("text" => $ifname, "value" => $ifent);
	}

	return json_encode($filter_ifs);
}

function MVC_create_interface_list() {
	global $config;

	$iflist = array();

	// add group interfaces
	if (isset($config['ifgroups']['ifgroupentry']) && is_array($config['ifgroups']['ifgroupentry'])) {
		foreach ($config['ifgroups']['ifgroupentry'] as $ifgen) {
			$iflist[$ifgen['ifname']] = $ifgen['ifname'];
		}
	}

	foreach (get_configured_interface_with_descr() as $ifent => $ifdesc) {
		$iflist[$ifent] = $ifdesc;
	}

	if ($config['l2tp']['mode'] == "server") {
		$iflist['l2tp'] = gettext('L2TP VPN');
	}

	if (is_pppoe_server_enabled()) {
		$iflist['pppoe'] = gettext("PPPoE Server");
	}

	// add ipsec interfaces
	if (ipsec_enabled()) {
		$iflist["enc0"] = gettext("IPsec");
	}

	// add openvpn/tun interfaces
	if ($config['openvpn']["openvpn-server"] || $config['openvpn']["openvpn-client"]) {
		$iflist["openvpn"] = gettext("OpenVPN");
	}

	if (is_wg_enabled()) {
		$iflist['wireguard'] = 'WireGuard';
	}

	return($iflist);
}

?>