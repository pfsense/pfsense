<?php
/*
 * shortcuts.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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

// On the page, add in like so:
//   $shortcut_section = "ipsec";

$shortcuts = array();

/* Load and process custom shortcuts. */
function get_shortcut_files($directory) {
	$dir_array = array();
	if (!is_dir($directory)) {
		return;
	}
	if ($dh = opendir($directory)) {
		while (($file = readdir($dh)) !== false) {
			$canadd = 0;
			if ($file == ".") {
				$canadd = 1;
			}
			if ($file == "..") {
				$canadd = 1;
			}
			if ($canadd == 0) {
				array_push($dir_array, $file);
			}
		}
		closedir($dh);
	}
	if (!is_array($dir_array)) {
		return;
	}
	return $dir_array;
}

function get_shortcut_by_service_name($servicename) {
	global $shortcuts;
	foreach ($shortcuts as $name => $shortcut) {
		if (!empty($shortcut['service']) && ($shortcut['service'] == $servicename)) {
			return $name;
		}
	}
	return null;
}

function get_shortcut_main_link($shortcut_section, $addspace = true, $service = array()) {
	global $g, $shortcuts;
	if (empty($shortcut_section)) {
		return "";
	}
	$space = ($addspace) ? "&nbsp;" : "" ;
	switch ($shortcut_section) {
		case "openvpn":
			if (!empty($service['mode']) && is_numeric($service['id'])) {
				$link = "vpn_openvpn_{$service['mode']}.php?act=edit&amp;id={$service['id']}";
			} else {
				$link = $shortcuts[$shortcut_section]['main'];
			}
			break;
		case "captiveportal":
			if (!empty($service['zone'])) {
				$link = "services_captiveportal.php?zone={$service['zone']}";
			} else {
				$link = $shortcuts[$shortcut_section]['main'];
			}
			break;
		default:
			$link = $shortcuts[$shortcut_section]['main'];
			break;
	}
	if (!empty($link) && ($_SERVER['REQUEST_URI'] != "/{$link}")) {
		return "{$space}<a href=\"{$link}\" title=\"" . gettext("Related settings") . "\"><i class=\"fa fa-sliders\"></i></a>";
	}
	return "";
}

function get_shortcut_status_link($shortcut_section, $addspace = true, $service = array()) {
	global $g, $shortcuts, $cpzone;
	if (empty($shortcut_section)) {
		return "";
	}
	$space = ($addspace) ? "&nbsp;" : "" ;
	if (!empty($cpzone)) {
		$zone = $cpzone;
	} elseif (!empty($service['zone'])) {
		$zone = $service['zone'];
	}
	switch ($shortcut_section) {
		case "captiveportal":
			if (!empty($zone)) {
				$link = "status_captiveportal.php?zone={$zone}";
			} else {
				$link = $shortcuts[$shortcut_section]['status'];
			}
			break;
		case "captiveportal-vouchers":
			if (!empty($zone)) {
				$link = "status_captiveportal_vouchers.php?zone={$zone}";
			} else {
				$link = $shortcuts[$shortcut_section]['status'];
			}
			break;
		default:
			$link = $shortcuts[$shortcut_section]['status'];
			break;
	}
	if (!empty($link)) {
		return "{$space}<a href=\"{$link}\" title=\"" . gettext("Related status") . "\"><i class=\"fa fa-bar-chart\"></i></a>";
	}
	return "";
}

function get_shortcut_log_link($shortcut_section, $addspace = true) {
	global $g, $shortcuts;
	$space = ($addspace) ? "&nbsp;" : "" ;
	if (!empty($shortcut_section) && !empty($shortcuts[$shortcut_section]['log'])) {
		return "{$space}<a href=\"{$shortcuts[$shortcut_section]['log']}\" title=\"" . gettext("Related log entries") . "\"><i class=\"fa fa-list-alt\"></i></a>";
	}
	return "";
}

// Load shortcuts
$dir_array = get_shortcut_files("/usr/local/www/shortcuts");
foreach ($dir_array as $file) {
	if (!is_dir("/usr/local/www/shortcuts/{$file}") && stristr($file, ".inc")) {
		include_once("/usr/local/www/shortcuts/{$file}");
	}
}
if (is_dir("/usr/local/pkg/shortcuts")) {
	$dir_array = get_shortcut_files("/usr/local/pkg/shortcuts");
	foreach ($dir_array as $file) {
		if (!is_dir("/usr/local/pkg/shortcuts/{$file}") && stristr($file, ".inc")) {
			include_once("/usr/local/pkg/shortcuts/{$file}");
		}
	}
}

$shortcuts['captiveportal'] = array();
$shortcuts['captiveportal']['main'] = "services_captiveportal_zones.php";
$shortcuts['captiveportal']['log'] = "status_logs.php?logfile=portalauth";
$shortcuts['captiveportal']['status'] = "status_captiveportal.php";
$shortcuts['captiveportal']['service'] = "captiveportal";

$shortcuts['captiveportal-vouchers'] = array();
$shortcuts['captiveportal-vouchers']['log'] = "status_logs.php?logfile=auth";
$shortcuts['captiveportal-vouchers']['status'] = "status_captiveportal_vouchers.php";
$shortcuts['captiveportal-vouchers']['service'] = "captiveportal";

$shortcuts['dhcp'] = array();
$shortcuts['dhcp']['main'] = "services_dhcp.php";
$shortcuts['dhcp']['log'] = "status_logs.php?logfile=dhcpd";
$shortcuts['dhcp']['status'] = "status_dhcp_leases.php";
$shortcuts['dhcp']['service'] = "dhcpd";

$shortcuts['dhcp6'] = array();
$shortcuts['dhcp6']['main'] = "services_dhcpv6.php";
$shortcuts['dhcp6']['log'] = "status_logs.php?logfile=dhcpd";
$shortcuts['dhcp6']['status'] = "status_dhcpv6_leases.php";


$shortcuts['ipsec'] = array();
$shortcuts['ipsec']['main'] = "vpn_ipsec.php";
$shortcuts['ipsec']['log'] = "status_logs.php?logfile=ipsec";
$shortcuts['ipsec']['status'] = "status_ipsec.php";
$shortcuts['ipsec']['service'] = "ipsec";

$shortcuts['openvpn'] = array();
$shortcuts['openvpn']['main'] = "vpn_openvpn_server.php";
$shortcuts['openvpn']['log'] = "status_logs.php?logfile=openvpn";
$shortcuts['openvpn']['status'] = "status_openvpn.php";
$shortcuts['openvpn']['service'] = "openvpn";

$shortcuts['firewall'] = array();
$shortcuts['firewall']['main'] = "firewall_rules.php";
$shortcuts['firewall']['log'] = "status_logs_filter.php";
$shortcuts['firewall']['status'] = "status_filter_reload.php";

$shortcuts['routing'] = array();
$shortcuts['routing']['main'] = "system_routes.php";
$shortcuts['routing']['log'] = "status_logs.php?logfile=routing";
$shortcuts['routing']['status'] = "diag_routes.php";

$shortcuts['gateways'] = array();
$shortcuts['gateways']['main'] = "system_gateways.php";
$shortcuts['gateways']['log'] = "status_logs.php?logfile=gateways";
$shortcuts['gateways']['status'] = "status_gateways.php";
$shortcuts['gateways']['service'] = "dpinger";

$shortcuts['gateway-groups'] = array();
$shortcuts['gateway-groups']['main'] = "system_gateway_groups.php";
$shortcuts['gateway-groups']['log'] = "status_logs.php?logfile=gateways";
$shortcuts['gateway-groups']['status'] = "status_gateway_groups.php";

$shortcuts['interfaces'] = array();
$shortcuts['interfaces']['main'] = "interfaces_assign.php";
$shortcuts['interfaces']['status'] = "status_interfaces.php";

$shortcuts['trafficshaper'] = array();
$shortcuts['trafficshaper']['main'] = "firewall_shaper.php";
$shortcuts['trafficshaper']['status'] = "status_queues.php";

$shortcuts['trafficshaper-limiters'] = array();
$shortcuts['trafficshaper-limiters']['main'] = "firewall_shaper_vinterface.php";
$shortcuts['trafficshaper-limiters']['status'] = "diag_limiter_info.php";

$shortcuts['forwarder'] = array();
$shortcuts['forwarder']['main'] = "services_dnsmasq.php";
$shortcuts['forwarder']['log'] = "status_logs.php?logfile=resolver";
$shortcuts['forwarder']['service'] = "dnsmasq";

$shortcuts['resolver'] = array();
$shortcuts['resolver']['main'] = "services_unbound.php";
$shortcuts['resolver']['log'] = "status_logs.php?logfile=resolver";
$shortcuts['resolver']['service'] = "unbound";
$shortcuts['resolver']['status'] = "status_unbound.php";

$shortcuts['wireless'] = array();
$shortcuts['wireless']['main'] = "interfaces_wireless.php";
$shortcuts['wireless']['log'] = "status_logs.php?logfile=wireless";
$shortcuts['wireless']['status'] = "status_wireless.php";

$shortcuts['ntp'] = array();
$shortcuts['ntp']['main'] = "services_ntpd.php";
$shortcuts['ntp']['log'] = "status_logs.php?logfile=ntpd";
$shortcuts['ntp']['status'] = "status_ntpd.php";
$shortcuts['ntp']['service'] = "ntpd";

$shortcuts['pppoes'] = array();
$shortcuts['pppoes']['main'] = "services_pppoe.php";
$shortcuts['pppoes']['log'] = "status_logs_vpn.php?vpntype=poes";

$shortcuts['l2tps'] = array();
$shortcuts['l2tps']['main'] = "vpn_l2tp.php";
$shortcuts['l2tps']['log'] = "status_logs_vpn.php?logfile=l2tps&vpntype=l2tp";

$shortcuts['carp'] = array();
$shortcuts['carp']['main'] = "system_hasync.php";
$shortcuts['carp']['status'] = "status_carp.php";

$shortcuts['snmp'] = array();
$shortcuts['snmp']['main'] = "services_snmp.php";
$shortcuts['snmp']['service'] = "bsnmpd";

$shortcuts['syslogd'] = array();
$shortcuts['syslogd']['main'] = "status_logs_settings.php";
$shortcuts['syslogd']['log'] = "status_logs.php";
$shortcuts['syslogd']['service'] = "syslogd";

$shortcuts['authentication'] = array();
$shortcuts['authentication']['main'] = "system_authservers.php";
// $shortcuts['authentication']['status'] = "diag_authentication.php";

$shortcuts['aliases'] = array();
$shortcuts['aliases']['main'] = "firewall_aliases.php";
$shortcuts['aliases']['status'] = "diag_tables.php";

?>
