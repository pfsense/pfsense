<?php
/*
 * service-utils.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2005-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005-2006 Colin Smith (ethethlay@gmail.com)
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

require_once("captiveportal.inc");
require_once("globals.inc");
require_once("gwlb.inc");
require_once("ipsec.inc");
require_once("openvpn.inc");
require_once("system.inc");
require_once("util.inc");
require_once("vpn.inc");

define("RCFILEPREFIX", "/usr/local/etc/rc.d/");
function write_rcfile($params) {
	global $g;

	safe_mkdir(RCFILEPREFIX);
	$rcfile_fullname = RCFILEPREFIX . $params['file'];
	if (!file_exists($rcfile_fullname) && !is_link($rcfile_fullname) && !touch($rcfile_fullname)) {
		return false;
	}

	if (!is_writable($rcfile_fullname) || empty($params['start'])) {
		return false;
	}

	$towrite = "#!/bin/sh\n";
	$towrite .= "# This file was automatically generated\n# by the {$g['product_label']} service handler.\n\n";

	/* write our rc functions */
	$towrite .= "rc_start() {\n";
	$towrite .= "\t{$params['start']}\n";
	$towrite .= "}\n\n";
	if (!empty($params['stop'])) {
		$tokill = &$params['stop'];
	} else if (!empty($params['executable'])) {
		/* just nuke the executable */
		$tokill = "/usr/bin/killall " . escapeshellarg($params['executable']);
	} else {
		/* make an educated guess (bad) */
		$tokill = explode(' ', $params['start']);
		$tokill = array_pop(explode('/', array_shift($tokill)));
	}
	$towrite .= "rc_stop() {\n";
	$towrite .= "\t{$tokill}\n";
	$towrite .= "}\n\n";
	if (!empty($params['restart'])) {
		$torestart = &$params['restart'];
	} else {
		$torestart = "\trc_stop\n";
		$torestart .= "\trc_start\n";
	}
	$towrite .= "rc_restart() {\n";
	$towrite .= "{$torestart}\n";
	$towrite .= "}\n\n";

	/* begin rcfile logic */
	$towrite .= "case \$1 in\n\tstart)\n\t\trc_start\n\t\t;;\n\tstop)\n\t\trc_stop\n\t\t;;\n\trestart)\n\t\trc_restart\n\t\t;;\nesac\n\n";

	@file_put_contents($rcfile_fullname, $towrite);
	unset($towrite);
	@chmod("{$rcfile_fullname}", 0755);

	return;
}

function start_service($name, $after_sync = false) {
	if (empty($name)) {
		return;
	}

	foreach (config_get_path('installedpackages/service', []) as $service) {
		if (empty($service)) {
			continue;
		}
		if (isset($service['name']) && (strtolower($service['name']) == strtolower($name))) {
			/* Avoid starting twice if this is called just after a
			 * package sync which starts the service itself. */
			if ($after_sync && isset($service['starts_on_sync'])) {
				break;
			}
			if ($service['rcfile']) {
				$prefix = RCFILEPREFIX;
				if (!empty($service['prefix'])) {
					$prefix = &$service['prefix'];
				}
				if (file_exists("{$prefix}{$service['rcfile']}") || is_link("{$prefix}{$service['rcfile']}")) {
					mwexec_bg("{$prefix}{$service['rcfile']} start");
				}
			}
			if (!empty($service['startcmd'])) {
				eval($service['startcmd']);
			}
			break;
		}
	}
}

function stop_service($name) {
	if (empty($name)) {
		return;
	}

	foreach (config_get_path('installedpackages/service', []) as $service) {
		if (empty($service)) {
			continue;
		}
		if (strtolower($service['name']) == strtolower($name)) {
			if ($service['rcfile']) {
				$prefix = RCFILEPREFIX;
				if (!empty($service['prefix'])) {
					$prefix = &$service['prefix'];
				}
				if (file_exists("{$prefix}{$service['rcfile']}") || is_link("{$prefix}{$service['rcfile']}")) {
					mwexec("{$prefix}{$service['rcfile']} stop");
				}
				return;
			}
			if (!empty($service['stopcmd'])) {
				eval($service['stopcmd']);
			} elseif (!empty($service['executable'])) {
				mwexec("/usr/bin/killall " . escapeshellarg($service['executable']));
			}

			break;
		}
	}
}

function restart_service($name) {
	if (empty($name)) {
		return;
	}

	if (is_service_running($name)) {
		stop_service($name);
	}
	start_service($name);

	foreach (config_get_path('installedpackages/service', []) as $service) {
		if (empty($service)) {
			continue;
		}
		if (strtolower($service['name']) == strtolower($name)) {
			if ($service['restartcmd']) {
				eval($service['restartcmd']);
			}
			break;
		}
	}
}

function is_pid_running($pidfile) {
	if (!file_exists($pidfile)) {
		return false;
	}

	return (isvalidpid($pidfile));
}

function is_dhcp_running($interface) {
	$status = find_dhclient_process($interface);
	if ($status != 0) {
		return true;
	}
	return false;
}

function restart_service_if_running($service) {
	if (is_service_running($service)) {
		restart_service($service);
	}
	return;
}

function is_service_enabled($service_name) {
	switch ($service_name) {
		case 'bsnmpd':
			return config_path_enabled('snmpd');
			break;
		case 'dhcrelay':
			return config_path_enabled('dhcrelay');
			break;
		case 'dhcrelay6':
			return config_path_enabled('dhcrelay6');
			break;
		case 'dhcpd':
			return (dhcp_is_backend('isc') && is_dhcp_server_enabled());
			break;
		case 'kea-dhcp4':
			return (dhcp_is_backend('kea') && is_dhcp_server_enabled());
			break;
		case 'kea-dhcp6':
			return (dhcp_is_backend('kea') && is_dhcpv6_server_enabled());
			break;
		case 'dnsmasq':
			return config_path_enabled('dnsmasq');
			break;
		case 'dpinger':
			/* TODO: Should loop through gateways and check to make
			 *       sure they don't all have monitoring disabled. */
			return !empty(get_gateways());
			break;
		case 'igmpproxy':
			return (config_path_enabled('igmpproxy') && !empty(config_get_path('igmpproxy/igmpentry', [])));
			break;
		case 'ipsec':
			return ipsec_enabled();
			break;
		case 'miniupnpd':
			return (config_get_path('installedpackages/miniupnpd/config/0/enable') == 'on');
			break;
		case 'ntpd':
			return (config_get_path('ntpd/enable') != 'disabled');
			break;
		case 'pcscd':
			return config_path_enabled('ipsec', 'pkcs11support');
			break;
		case 'radvd':
			return is_radvd_enabled();
			break;
		case 'sshd':
			return config_path_enabled('system/ssh');
			break;
		case 'syslogd':
			$local_enabled = !config_path_enabled('syslog', 'disablelocallogging');
			$remote_enabled = !empty(system_syslogd_get_remote_servers(config_get_path('syslog', [])));
			return ($local_enabled || $remote_enabled);
			break;
		case 'unbound':
			return config_path_enabled('unbound');
			break;
		default:
			/* Do nothing since we can't determine for certain. */
	}
	/* TODO: The service name isn't likely to match the config tag,
	 *       needs better logic or pkg plugin to probe */
	$pkg_enabled = config_get_path("installedpackages/{$service_name}/config/0/enable", null);
	if (($pkg_enabled !== null) &&
	    (empty($pkg_enabled) || ($pkg_enabled === 'off'))) {
		/* Only return false for cases where the config area exists and
		 * appears to be disabled. */
		return false;
	}

	/* Unknown service, for compatibility reasons, return true. */
	return true;
}

function is_service_running($service, $ps = "") {
	foreach (config_get_path('installedpackages/service', []) as $aservice) {
		if (empty($aservice)) {
			continue;
		}
		if (isset($aservice['name']) && (strtolower($service) == strtolower($aservice['name']))) {
			if ($aservice['custom_php_service_status_command'] <> "") {
				eval("\$rc={$aservice['custom_php_service_status_command']};");
				return $rc;
			}
			if (empty($aservice['executable'])) {
				return false;
			}
			if (is_process_running($aservice['executable'])) {
				return true;
			}
			return false;
		}
	}
	if (is_process_running($service)) {
		return true;
	}
	return false;
}

function get_services() {
	$services = config_get_path('installedpackages/service', []);

	/* Clean up any empty services */
	foreach ($services as $k => &$s) {
		if (empty($s)) {
			config_del_path('installedpackages/services/' . $k);
			unset($s);
		}
	}

	/*
	 * Add services that are in the base.
	 */
	if (is_radvd_enabled()) {
		$pconfig = array();
		$pconfig['name'] = "radvd";
		$pconfig['description'] = gettext("Router Advertisement Daemon");
		$pconfig['enabled'] = true;
		$pconfig['status'] = get_service_status($pconfig);
		$services[] = $pconfig;
	}

	if (config_path_enabled('dnsmasq')) {
		$pconfig = array();
		$pconfig['name'] = "dnsmasq";
		$pconfig['description'] = gettext("DNS Forwarder");
		$pconfig['enabled'] = true;
		$pconfig['status'] = get_service_status($pconfig);
		$services[] = $pconfig;
	}

	if (config_path_enabled('unbound')) {
		$pconfig = array();
		$pconfig['name'] = "unbound";
		$pconfig['description'] = gettext("DNS Resolver");
		$pconfig['enabled'] = true;
		$pconfig['status'] = get_service_status($pconfig);
		$services[] = $pconfig;
	}

	if (config_path_enabled('ipsec', 'pkcs11support')) {
		$pconfig = array();
		$pconfig['name'] = "pcscd";
		$pconfig['description'] = gettext("PC/SC Smart Card Daemon");
		$pconfig['enabled'] = true;
		$pconfig['status'] = get_service_status($pconfig);
		$services[] = $pconfig;
	}

	if (config_get_path('ntpd/enable') != 'disabled') {
		$pconfig = array();
		$pconfig['name'] = "ntpd";
		$pconfig['description'] = gettext("NTP clock sync");
		$pconfig['enabled'] = true;
		$pconfig['status'] = get_service_status($pconfig);
		$services[] = $pconfig;
	}

	$pconfig = array();
	$pconfig['name'] = "syslogd";
	$pconfig['description'] = gettext("System Logger Daemon");
	$pconfig['enabled'] = is_service_enabled("syslogd");
	$pconfig['status'] = get_service_status($pconfig);
	$services[] = $pconfig;

	foreach (config_get_path('captiveportal', []) as $zone => $setting) {
		if (empty($setting)) {
			continue;
		}
		if (isset($setting['enable'])) {
			$pconfig = array();
			$pconfig['name'] = "captiveportal";
			$pconfig['zone'] = $zone;
			$pconfig['enabled'] = true;
			$pconfig['description'] = gettext("Captive Portal") . ": " . htmlspecialchars($zone . ($setting['descr'] ? " ({$setting['descr']})" : ''));
			$services[] = $pconfig;
		}
	}

	$iflist = array();
	$ifdescrs = get_configured_interface_list();
	foreach ($ifdescrs as $if) {
		if (config_get_path("interfaces/{$if}/if") &&
		    !link_interface_to_bridge($if)) {
			$iflist[$if] = $if;
		}
	}

	if (config_path_enabled('dhcrelay')) {
		$pconfig = array();
		$pconfig['name'] = "dhcrelay";
		$pconfig['description'] = gettext('ISC DHCP Relay');
		$pconfig['enabled'] = true;
		$pconfig['status'] = get_service_status($pconfig);
		$services[] = $pconfig;
	}

	if (config_path_enabled('dhcrelay6')) {
		$pconfig = array();
		$pconfig['name'] = "dhcrelay6";
		$pconfig['description'] = gettext('ISC DHCPv6 Relay');
		$pconfig['enabled'] = true;
		$pconfig['status'] = get_service_status($pconfig);
		$services[] = $pconfig;
	}

	if (dhcp_is_backend('isc') && is_dhcp_server_enabled()) {
		$pconfig = array();
		$pconfig['name'] = "dhcpd";
		$pconfig['description'] = gettext('ISC DHCP Server');
		$pconfig['enabled'] = true;
		$pconfig['status'] = get_service_status($pconfig);
		$services[] = $pconfig;
	}

	if (dhcp_is_backend('kea') && is_dhcp_server_enabled()) {
		$pconfig = array();
		$pconfig['name'] = 'kea-dhcp4';
		$pconfig['description'] = gettext('Kea DHCP Server');
		$pconfig['enabled'] = true;
		$pconfig['status'] = get_service_status($pconfig);
		$services[] = $pconfig;
	}

	if (dhcp_is_backend('kea') && is_dhcpv6_server_enabled()) {
		$pconfig = array();
		$pconfig['name'] = 'kea-dhcp6';
		$pconfig['description'] = gettext('Kea DHCPv6 Server');
		$pconfig['enabled'] = true;
		$pconfig['status'] = get_service_status($pconfig);
		$services[] = $pconfig;
	}

	if (!empty(get_gateways())) {
		$pconfig = array();
		$pconfig['name'] = "dpinger";
		$pconfig['description'] = gettext("Gateway Monitoring Daemon");
		$pconfig['enabled'] = is_service_enabled("dpinger");
		$pconfig['status'] = get_service_status($pconfig);
		$services[] = $pconfig;
	}

	if (config_path_enabled('snmpd')) {
		$pconfig = array();
		$pconfig['name'] = "bsnmpd";
		$pconfig['description'] = gettext("SNMP Service");
		$pconfig['enabled'] = true;
		$pconfig['status'] = get_service_status($pconfig);
		$services[] = $pconfig;
	}

	if (!empty(config_get_path('igmpproxy/igmpentry', []))) {
		$pconfig = array();
		$pconfig['name'] = "igmpproxy";
		$pconfig['description'] = gettext("IGMP proxy");
		$pconfig['enabled'] = is_service_enabled("igmpproxy");
		$pconfig['status'] = get_service_status($pconfig);
		$services[] = $pconfig;
	}

	if (config_get_path('installedpackages/miniupnpd/config/0/enable') == 'on') {
		$pconfig = array();
		$pconfig['name'] = "miniupnpd";
		$pconfig['description'] = gettext("UPnP IGD & PCP Service");
		$pconfig['enabled'] = true;
		$pconfig['status'] = get_service_status($pconfig);
		$services[] = $pconfig;
	}

	if (ipsec_enabled()) {
		$pconfig = array();
		$pconfig['name'] = "ipsec";
		$pconfig['description'] = gettext("IPsec VPN");
		$pconfig['enabled'] = true;
		$pconfig['status'] = get_service_status($pconfig);
		$services[] = $pconfig;
	}

	if (config_path_enabled('system/ssh')) {
		$pconfig = array();
		$pconfig['name'] = "sshd";
		$pconfig['description'] = gettext("Secure Shell Daemon");
		$pconfig['enabled'] = true;
		$pconfig['status'] = get_service_status($pconfig);
		$services[] = $pconfig;
	}

	foreach (array('server', 'client') as $mode) {
		foreach (config_get_path("openvpn/openvpn-{$mode}", []) as $id => $setting) {
			if (!isset($setting['disable'])) {
				$pconfig = array();
				$pconfig['name'] = "openvpn";
				$pconfig['mode'] = $mode;
				$pconfig['id'] = $id;
				$pconfig['vpnid'] = $setting['vpnid'];
				$pconfig['description'] = gettext("OpenVPN") . " " . $mode . ": " . htmlspecialchars($setting['description']);
				$pconfig['enabled'] = true;
				$pconfig['status'] = get_service_status($pconfig);
				$services[] = $pconfig;
			}
		}
	}

	return $services;
}

function find_service_by_name($name) {
	$services = get_services();
	foreach ($services as $service) {
		if (isset($service["name"]) && ($service["name"] == $name)) {
			return $service;
		}
	}
	return array();
}

function find_service_by_openvpn_vpnid($vpnid) {
	$services = get_services();
	foreach ($services as $service) {
		if (isset($service["name"]) && ($service["name"] == "openvpn") && isset($service["vpnid"]) && ($service["vpnid"] == $vpnid)) {
			return $service;
		}
	}
	return array();
}

function find_service_by_cp_zone($zone) {
	$services = get_services();
	foreach ($services as $service) {
		if (isset($service["name"]) && ($service["name"] == "captiveportal") && isset($service["zone"]) && ($service["zone"] == $zone)) {
			return $service;
		}
	}
	return array();
}

function service_description_compare($a, $b) {
	if (strtolower($a['description']) == strtolower($b['description'])) {
		return 0;
	}
	return (strtolower($a['description']) < strtolower($b['description'])) ? -1 : 1;
}

function service_name_compare($a, $b) {
	if (!isset($a['name']) || !isset($b['name'])) {
		return -1;
	}
	/* If the names are equal, fall back to sorting by description */
	if (strtolower($a['name']) == strtolower($b['name'])) {
		return service_description_compare($a, $b);
	}
	return (strtolower($a['name']) < strtolower($b['name'])) ? -1 : 1;
}

function service_dispname_compare($a, $b) {
	/* If two items have an instance suffix, perform an integer comparison to avoid awkward sorting */
	if ((strpos($a['dispname'], '_') > 0) && (strpos($b['dispname'], '_') > 0)) {
		list($adn1, $adn2) = explode('_', $a['dispname'], 2);
		list($bdn1, $bdn2) = explode('_', $b['dispname'], 2);
		if (($adn1 == $bdn1) && is_numeric($adn2) && is_numeric($bdn2)) {
			if ($adn2 == $bdn2) {
				return 0;
			}
			return ((int)$adn2 < (int)$bdn2) ? -1 : 1;
		}
	}
	/* If the display names are equal, compare the internal name */
	if (strtolower($a['dispname']) == strtolower($b['dispname'])) {
		return service_name_compare($a, $b);
	}
	return (strtolower($a['dispname']) < strtolower($b['dispname'])) ? -1 : 1;
}

function get_pkg_descr($package_name) {
	foreach (config_get_path('installedpackages/package', []) as $pkg) {
		if ($pkg['name'] == $package_name) {
			return $pkg['descr'];
		}
	}
	return gettext("Not available.");
}

function get_service_status($service) {
	global $g;
	switch ($service['name']) {
		case "openvpn":
			$running = is_pid_running("{$g['varrun_path']}/openvpn_{$service['mode']}{$service['vpnid']}.pid");
			break;
		case "captiveportal":
			$running = is_pid_running("{$g['varrun_path']}/nginx-{$service['zone']}-CaptivePortal.pid");
			if (config_path_enabled("captiveportal/{$service['zone']}", 'httpslogin')) {
				$running = $running && is_pid_running("{$g['varrun_path']}/nginx-{$service['zone']}-CaptivePortal-SSL.pid");
			}
			break;
		case "vhosts-http":
			$running = is_pid_running("{$g['varrun_path']}/vhosts-http.pid");
			break;
		case "dhcrelay6":
			$running = is_pid_running("{$g['varrun_path']}/dhcrelay6.pid");
			break;
		case 'ipsec':
			$running = (is_pid_running("{$g['varrun_path']}/charon.pid") || is_process_running('charon'));
			break;
		case 'kea-dhcp4':
			$running = is_pid_running('/var/run/kea/kea-dhcp4.kea-dhcp4.pid');
			break;
		case 'kea-dhcp6':
			$running = is_pid_running('/var/run/kea/kea-dhcp6.kea-dhcp6.pid');
			break;
		default:
			$running = is_service_running($service['name']);
	}
	return $running;
}

function get_service_status_icon($service, $withtext = true, $smallicon = false, $withthumbs = false, $title = "service_state") {
	$output = "";

	if (get_service_status($service)) {
		$statustext = gettext("Running");
		$text_class = "text-success";
		$fa_class = "fa-solid fa-check-circle";
		$fa_class_thumbs = "fa-regular fa-thumbs-up";
		$Thumbs_UpDown = "Thumbs up";
	} else {
		if (is_service_enabled($service['name'])) {
			$statustext = gettext("Stopped");
			$text_class = "text-danger";
			$fa_class = "fa-solid fa-times-circle";
		} else {
			$statustext = gettext("Disabled");
			$text_class = "text-warning";
			$fa_class = "fa-solid fa-ban";
		}
		$fa_class_thumbs = "fa-regular fa-thumbs-down";
		$Thumbs_UpDown = "Thumbs down";
	}
	$fa_size = ($smallicon) ? "fa-1x" : "fa-lg";

	if ($title == "state") {
		$title = $statustext;
	} elseif ($title == "service_state") {
		$title = sprintf(gettext('%1$s Service is %2$s'), $service["name"], $statustext);
	} elseif ($title == "description_state") {
		$title = sprintf(gettext('%1$s Service is %2$s'), $service["description"], $statustext);
	} elseif ($title == "description_service_state") {
		$title = sprintf(gettext('%1$s, %2$s Service is %3$s'), $service["description"], $service["name"], $statustext);
	}

	$spacer = ($withthumbs || $withtext) ? " " : "";

	$output = "<i class=\"{$text_class} {$fa_class} {$fa_size}\" title=\"{$title}\"><span style=\"display: none\">{$statustext}</span></i>{$spacer}";

	$spacer = ($withtext) ? " " : "";
	if ($withthumbs) {
		$output .= "<i class=\"{$text_class} {$fa_class_thumbs} {$fa_size}\" title=\"{$Thumbs_UpDown}\"></i>{$spacer}";
	}

	if ($withtext) {
		$output .= "<span class=\"" . $text_class . "\">" . $statustext . "</span>";
	}

	return $output;
}

function get_service_control_links($service, $addname = false) {
	global $g;
	$output = "";
	$stitle = ($addname) ? $service['name'] . " " : "";

	switch ($service['name']) {
		case "openvpn":
			$link = '<a title="%s" href="#" id="openvpn-%s-' . $service['mode'] . '-' . $service['vpnid'] . '" >';
		break;
		case "captiveportal":
			$link = '<a title="%s" href="#" id="captiveportal-%s-' . $service['zone'] . '">';
		break;
		default:
			$link = '<a title="%s" href="#" id="%s-' . $service['name'] . '">';
	}

	if (get_service_status($service)) {
		switch ($service['name']) {
			case "openvpn":
				$output .= '<a href="#" id="openvpn-restartservice-' . $service['mode'] . '-' . $service['vpnid'] . '" >';
				break;
			case "captiveportal":
				$output .= '<a href="#" id="captiveportal-restartservice-' . $service['zone'] . '">';
				break;
			default:
				$output .= '<a href="#" id="restartservice-' . $service['name'] . '" >';
		}

		$output .= "<i class=\"fa-solid fa-arrow-rotate-right\" title=\"" . sprintf(gettext("Restart %sService"), $stitle) . "\"></i></a>\n";

		switch ($service['name']) {
			case "openvpn":
				$output .= '<a href="#" id="openvpn-stopservice-' . $service['mode'] . '-' . $service['vpnid'] . '" >';
				break;
			case "captiveportal":
				$output .= '<a href="#" id="captiveportal-stopservice-' . $service['zone'] . '">';
				break;
			default:
				$output .= '<a href="#" id="stopservice-' . $service['name'] . '">';
		}

		$output .= "<i class=\"fa-regular fa-circle-stop\" title=\"" . sprintf(gettext("Stop %sService"), $stitle) . "\"></i></a>";

	} else {
		$service_enabled = is_service_enabled($service['name']);

		if ($service['name'] == 'openvpn' || $service['name'] == 'captiveportal' || $service_enabled) {
			$output .= sprintf($link, sprintf(gettext("Start %sService"), $stitle), 'startservice');
			$output .= '<i class="fa-solid fa-play-circle"></i></a> ';
		}
	}

	return $output;
}

function service_control_start($name, $extras) {
	global $g;
	switch ($name) {
		case 'radvd':
			services_radvd_configure();
			break;
		case 'captiveportal':
			$zone = htmlspecialchars($extras['zone']);
			captiveportal_init_webgui_zonename($zone);
			break;
		case 'ntpd':
			system_ntp_configure();
			break;
		case 'dpinger':
			setup_gateways_monitor();
			break;
		case 'bsnmpd':
			services_snmpd_configure();
			break;
		case 'dhcrelay':
			services_dhcrelay_configure();
			break;
		case 'dhcrelay6':
			services_dhcrelay6_configure();
			break;
		case 'dnsmasq':
			services_dnsmasq_configure();
			break;
		case 'unbound':
			services_unbound_configure();
			break;
		case 'kea-dhcp4':
		case 'kea-dhcp6':
		case 'dhcpd':
			services_dhcpd_configure();
			break;
		case 'igmpproxy':
			services_igmpproxy_configure();
			break;
		case 'miniupnpd':
			upnp_action('start');
			break;
		case 'ipsec':
			ipsec_force_reload();
			break;
		case 'sshd':
			send_event("service restart sshd");
			break;
		case 'pcscd':
			ipsec_force_reload();
			break;
		case 'openvpn':
			$vpnmode = isset($extras['vpnmode']) ? htmlspecialchars($extras['vpnmode']) : htmlspecialchars($extras['mode']);
			if (($vpnmode == "server") || ($vpnmode == "client")) {
				$id = isset($extras['vpnid']) ? htmlspecialchars($extras['vpnid']) : htmlspecialchars($extras['id']);
				$configfile = "{$g['openvpn_base']}/{$vpnmode}{$id}/config.ovpn";
				if (file_exists($configfile)) {
					openvpn_restart_by_vpnid($vpnmode, $id);
				}
			}
			break;
		case 'syslogd':
			system_syslogd_start();
			break;
		default:
			start_service($name);
			break;
	}
	return sprintf(gettext("%s has been started."), htmlspecialchars($name));
}
function service_control_stop($name, $extras) {
	global $g;
	switch ($name) {
		case 'radvd':
			killbypid("{$g['varrun_path']}/radvd.pid");
			break;
		case 'captiveportal':
			$zone = htmlspecialchars($extras['zone']);
			killbypid("{$g['varrun_path']}/nginx-{$zone}-CaptivePortal.pid");
			killbypid("{$g['varrun_path']}/nginx-{$zone}-CaptivePortal-SSL.pid");
			break;
		case 'ntpd':
			killbyname("ntpd");
			break;
		case 'openntpd':
			killbyname("openntpd");
			break;
		case 'dpinger':
			stop_dpinger();
			break;
		case 'bsnmpd':
			killbypid("{$g['varrun_path']}/snmpd.pid");
			break;
		case 'choparp':
			killbyname("choparp");
			break;
		case 'kea-dhcp4':
			killbypid('/var/run/kea/kea-dhcp4.kea-dhcp4.pid');
			break;
		case 'kea-dhcp6':
			killbypid('/var/run/kea/kea-dhcp6.kea-dhcp6.pid');
			break;
		case 'dhcpd':
			killbyname("dhcpd");
			break;
		case 'dhcrelay':
			killbypid("{$g['varrun_path']}/dhcrelay.pid");
			break;
		case 'dhcrelay6':
			killbypid("{$g['varrun_path']}/dhcrelay6.pid");
			break;
		case 'dnsmasq':
			killbypid("{$g['varrun_path']}/dnsmasq.pid");
			break;
		case 'unbound':
			killbypid("{$g['varrun_path']}/unbound.pid");
			break;
		case 'igmpproxy':
			killbyname("igmpproxy");
			break;
		case 'miniupnpd':
			upnp_action('stop');
			break;
		case 'sshd':
			killbyname("sshd");
			break;
		case 'pcscd':
		case 'ipsec':
			exec("/usr/local/sbin/strongswanrc stop");
			if (isvalidproc("pcscd")) {
				killbyname("pcscd");
			}
			break;
		case 'openvpn':
			$vpnmode = htmlspecialchars($extras['vpnmode']);
			if (($vpnmode == "server") or ($vpnmode == "client")) {
				$id = htmlspecialchars($extras['id']);
				$pidfile = "{$g['varrun_path']}/openvpn_{$vpnmode}{$id}.pid";
				killbypid($pidfile);
				openvpn_delete_tmp($vpnmode, $id);
			}
			break;
		case 'syslogd':
			if (isvalidpid("{$g['varrun_path']}/syslog.pid")) {
				sigkillbypid("{$g['varrun_path']}/syslog.pid", "TERM");
				usleep(100000);
			}
			if (isvalidpid("{$g['varrun_path']}/syslog.pid")) {
				sigkillbypid("{$g['varrun_path']}/syslog.pid", "KILL");
				usleep(100000);
			}
			/* Make sure sshguard stops as well */
			system_sshguard_stop();
			break;
		default:
			stop_service($name);
			break;
	}
	return sprintf(gettext("%s has been stopped."), htmlspecialchars($name));
}

function service_control_restart($name, $extras) {
	global $g;
	switch ($name) {
		case 'radvd':
			services_radvd_configure();
			break;
		case 'captiveportal':
			$zone = htmlspecialchars($extras['zone']);
			killbypid("{$g['varrun_path']}/nginx-{$zone}-CaptivePortal.pid");
			killbypid("{$g['varrun_path']}/nginx-{$zone}-CaptivePortal-SSL.pid");
			/* see https://redmine.pfsense.org/issues/12651 */
			sleep(1);
			captiveportal_init_webgui_zonename($zone);
			break;
		case 'ntpd':
		case 'openntpd':
			system_ntp_configure();
			break;
		case 'dpinger':
			setup_gateways_monitor();
			break;
		case 'bsnmpd':
			services_snmpd_configure();
			break;
		case 'dhcrelay':
			services_dhcrelay_configure();
			break;
		case 'dhcrelay6':
			services_dhcrelay6_configure();
			break;
		case 'dnsmasq':
			services_dnsmasq_configure();
			break;
		case 'unbound':
			services_unbound_configure();
			break;
		case 'kea-dhcp4':
		case 'kea-dhcp6':
		case 'dhcpd':
			services_dhcpd_configure();
			break;
		case 'igmpproxy':
			services_igmpproxy_configure();
			break;
		case 'miniupnpd':
			upnp_action('restart');
			break;
		case 'ipsec':
			ipsec_force_reload();
			break;
		case 'sshd':
			send_event("service restart sshd");
			break;
		case 'pcscd':
			exec("/usr/local/sbin/strongswanrc stop");
			if (isvalidproc("pcscd")) {
				killbyname("pcscd");
			}
			ipsec_force_reload();
			break;
		case 'openvpn':
			$vpnmode = htmlspecialchars($extras['vpnmode']);
			if (($vpnmode == "server") || ($vpnmode == "client")) {
				$id = htmlspecialchars($extras['id']);
				$configfile = "{$g['openvpn_base']}/{$vpnmode}{$id}/config.ovpn";
				if (file_exists($configfile)) {
					openvpn_restart_by_vpnid($vpnmode, $id);
				}
			}
			break;
		case 'syslogd':
			system_syslogd_start();
			break;
		default:
			restart_service($name);
			break;
	}
	return sprintf(gettext("%s has been restarted."), htmlspecialchars($name));
}
