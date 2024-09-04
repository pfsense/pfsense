<?php
/*
 * wizard_utils.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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
require_once("globals.inc");
require_once("pfsense-utils.inc");

// Save all of the parameters changed by the setup wizard
function save_setupwizard_config(
	$hostname,
	$domain,
	$timezone,
	$timeservers,
	$spoofmac,
	$mtu,
	$mss,
	$bogons,
	$rfc1918,
	$lanip,
	$dnsoverride,
	$primarydns,
	$secondarydns,
	$dhcphostname,
	$wangateway,
	$wanip,
	$wantype,
	$pppUsername,
	$pppPassword,
	$pppOnDemand,
	$pppIdleTimeout,
	$pppoeServiceName,
	$ppppoeLocalIP) {

	global $g;

	// General ====================================================================================================================
	config_set_path('system/hostname', $hostname);
	config_set_path('system/domain', $domain);

	// Time =======================================================================================================================
	config_set_path('system/timezone', $timezone);
	config_set_path('system/timeservers', $timeservers);

	// WAN ========================================================================================================================
	$if_wan_config = config_get_path('interfaces/wan');
	setorforget($if_wan_config['spoofmac'], $spoofmac);
	setorforget($if_wan_config['mtu'], $mtu);
	setorforget($if_wan_config['mss'], $mss);
	$if_wan_config['dhcphostname'] = $dhcphostname;
	config_set_path('interfaces/wan', $if_wan_config);

	if ($bogons == "on") {
		config_set_path('interfaces/wan/blockbogons', true);
	} else {
		config_del_path('interfaces/wan/blockbogons');
	}

	if ($rfc1918 == "on") {
		config_set_path('interfaces/wan/blockpriv', true);
	} else {
		config_del_path('interfaces/wan/blockpriv');
	}

	if (strlen($wangateway) > 0) {
		config_init_path('gateways/gateway_item');
		$found = false;
		$defaultgw_found = false;
		$gwlist = config_get_path('gateways/gateway_item', []);
		$ifgw = '';
		foreach ($gwlist as & $gw) {
			if ($gw['interface'] != "wan")
				continue;
			if (isset($gw['defaultgw']))
				$defaultgw_found = true;
			if ($gw['name'] == 'WANGW' || $gw['gateway'] == $wangateway) {
				$found = true;
				$gw['gateway'] = $wangateway;
				$ifgw = $gw['name'];
			}
		}
		config_set_path('interfaces/wan/gateway', $ifgw);
		config_set_path('gateways/gateway_item', $gwlist);
		if (!$found) {
			$newgw = array();
			$newgw['interface'] = "wan";
			$newgw['gateway'] = $wangateway;
			$newgw['name'] = "WANGW";
			$newgw['weight'] = 1;
			$newgw['descr'] = "WAN Gateway";
			$newgw['defaultgw'] = !$defaultgw_found;
			config_set_path('gateways/gateway_item/', $newgw);
			config_set_path('interfaces/wan/gateway', "WANGW");
		}
	}

	$pppfg = array();
	$type = $wantype;

	config_init_path('ppps/ppp');
	if (count(config_get_path('ppps/ppp', []))) {
		foreach (config_get_path('ppps/ppp', []) as $pppid => $ppp) {
			if ($ppp['ptpid'] == "0") {
				if ((substr(config_get_path('interfaces/wan/if'),0,5) == "pppoe") || (substr(config_get_path('interfaces/wan/if'),0,4) == "pptp")) {
					$oldif = explode(",", $ppp['ports']);
					if (!empty($oldif[0])) {
						config_set_path('interfaces/wan/if', $oldif[0]);
					}
				}
				if ($type == "pppoe" || $type == "pptp")
					config_del_path("ppps/ppp/{$pppid}");
			}
		}
	}

	if ($type == "pppoe" || $type == "pptp") {
		if ($type == "pptp") {
			$pppfg['username'] = $pppUsername;
			$pppfg['ondemand'] = $pppOnDemand;
			$pppfg['idletimeout'] = $pppIdleTimeout;
		}

		$pppfg['password'] = base64_encode($pppPassword);
		$tmp = array();
		$tmp['ptpid'] = "0";
		$tmp['type'] = $type;
		$tmp['if'] = $type . "0";
		$tmp['ports'] = config_get_path('interfaces/wan/if');
		config_set_path('ppps/ppp/', array_merge($tmp, $pppfg));
		unset($tmp);
		config_set_path('interfaces/wan/if', $type."0");
	}

	if(strpos($wanip, "/") !== false){
		$ip = explode("/", $wanip);
		config_set_path('interfaces/wan/ipaddr', $ip[0]);
		config_set_path('interfaces/wan/subnet', $ip[1]);		
	} else {
		config_set_path('interfaces/wan/ipaddr', $wanip);
	}

	// LAN ========================================================================================================================
	$lp = explode("/", $lanip);
	$addr = $lp[0];
	$mask = $lp[1];

	config_set_path('interfaces/lan/ipaddr', $addr);
	config_set_path('interfaces/lan/subnet', $mask);

	config_init_path('dhcpd/lan/range/from');
	config_init_path('dhcpd/lan/range/to');

	if (!ip_in_subnet(config_get_path('dhcpd/lan/range/from'), "{$addr}/{$mask}") ||
	    !ip_in_subnet(config_get_path('dhcpd/lan/range/to'), "{$addr}/{$mask}")) {

		$ipaddresses_before = ip_range_size_v4($lowestip, $addr);
		$ipaddresses_after = ip_range_size_v4($addr, $highestip);
		if ($ipaddresses_after >= $ipaddresses_before) {
			// The LAN IP is in the 1st half of the subnet, so put DHCP in the 2nd half.
			if ($ipaddresses_after > 30) {
				// There is reasonable space in the subnet, use a smaller chunk of the space for DHCP
				// This case will work out like the old defaults if the user has specified the ".1" address.
				// The range will be something like ".10" to ".245"
				config_set_path('dhcpd/lan/range/from', ip_after($addr, 9));
				config_set_path('dhcpd/lan/range/to', ip_before($highestip, 10));
			} else {
				// There is not much space in the subnet, so allocate everything above the LAN IP to DHCP.
				config_set_path('dhcpd/lan/range/from', ip_after($addr));
				config_set_path('dhcpd/lan/range/to', ip_before($highestip));
			}
		} else {
			// The LAN IP is in the 2nd half of the subnet, so put DHCP in the 1st half.
			if ($ipaddresses_before > 30) {
				// There is reasonable space in the subnet, use a smaller chunk of the space for DHCP
				config_set_path('dhcpd/lan/range/from', ip_after($lowestip, 10));
				config_set_path('dhcpd/lan/range/to', ip_before($addr, 9));
			} else {
				// There is not much space in the subnet, so allocate everything below the LAN IP to DHCP.
				config_set_path('dhcpd/lan/range/from', ip_after($lowestip));
				config_set_path('dhcpd/lan/range/to', ip_before($addr));
			}
		}
	}

	// DNS ========================================================================================================================
	if ($dnsoverride == "on") {
		config_set_path('system/dnsallowoverride', "on");
	} else {
		config_del_path('system/dnsallowoverride');
	}

	config_set_path('system/dnsserver', array($primarydns, $secondarydns));

	write_config("Configuration updated by setup wizard");
	reload_all();
	mwexec_bg("/etc/rc.update_bogons.sh now");
	touch("{$g['cf_conf_path']}/copynotice_display");
}

function setorforget(&$node, $value) {
   if(strlen($value) > 0) {
      $node = $value;
   } else {
      unset($node);
   }
}

?>
