<?php
/*
 * service-utils.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2005-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
	$towrite .= "# This file was automatically generated\n# by the {$g['product_name']} service handler.\n\n";

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
		$tokill = array_pop(explode('/', array_shift(explode(' ', $params['start']))));
	}
	$towrite .= "rc_stop() {\n";
	$towrite .= "\t{$tokill}\n";
	$towrite .= "}\n\n";

	/* begin rcfile logic */
	$towrite .= "case \$1 in\n\tstart)\n\t\trc_start\n\t\t;;\n\tstop)\n\t\trc_stop\n\t\t;;\n\trestart)\n\t\trc_stop\n\t\trc_start\n\t\t;;\nesac\n\n";

	@file_put_contents($rcfile_fullname, $towrite);
	unset($towrite);
	@chmod("{$rcfile_fullname}", 0755);

	return;
}

function start_service($name, $after_sync = false) {
	global $config;

	if (empty($name)) {
		return;
	}

	if (is_array($config['installedpackages']) && is_array($config['installedpackages']['service'])) {
		foreach ($config['installedpackages']['service'] as $service) {
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
}

function stop_service($name) {
	global $config;

	if (empty($name)) {
		return;
	}

	if (is_array($config['installedpackages']) && is_array($config['installedpackages']['service'])) {
		foreach ($config['installedpackages']['service'] as $service) {
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
}

function restart_service($name) {
	global $config;

	if (empty($name)) {
		return;
	}

	if (is_service_running($name)) {
		stop_service($name);
	}
	start_service($name);

	if (is_array($config['installedpackages']) && is_array($config['installedpackages']['service'])) {
		foreach ($config['installedpackages']['service'] as $service) {
			if (strtolower($service['name']) == strtolower($name)) {
				if ($service['restartcmd']) {
					eval($service['restartcmd']);
				}
				break;
			}
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
	global $config;
	if (is_service_running($service)) {
		restart_service($service);
	}
	return;
}

function is_service_enabled($service_name) {
	global $config;
	if ($service_name == "") {
		return false;
	}
	if (is_array($config['installedpackages'])) {
		if (isset($config['installedpackages'][$service_name]['config'][0]['enable']) &&
		    ((empty($config['installedpackages'][$service_name]['config'][0]['enable'])) ||
		    ($config['installedpackages'][$service_name]['config'][0]['enable'] === 'off'))) {
			return false;
		}
	}
	return true;
}

function is_service_running($service, $ps = "") {
	global $config;

	if (is_array($config['installedpackages']['service'])) {
		foreach ($config['installedpackages']['service'] as $aservice) {
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
	}

	if (is_process_running($service)) {
		return true;
	}

	return false;
}

function get_services() {
	global $config;
	if (is_array($config['installedpackages']['service'])) {
		$services = $config['installedpackages']['service'];
	} else {
		$services = array();
	}

	/*
	 * Add services that are in the base.
	 */
	if (is_radvd_enabled()) {
		$pconfig = array();
		$pconfig['name'] = "radvd";
		$pconfig['description'] = gettext("Router Advertisement Daemon");
		$services[] = $pconfig;
	}

	if (isset($config['dnsmasq']['enable'])) {
		$pconfig = array();
		$pconfig['name'] = "dnsmasq";
		$pconfig['description'] = gettext("DNS Forwarder");
		$services[] = $pconfig;
	}

	if (isset($config['unbound']['enable'])) {
		$pconfig = array();
		$pconfig['name'] = "unbound";
		$pconfig['description'] = gettext("DNS Resolver");
		$services[] = $pconfig;
	}

	$pconfig = array();
	$pconfig['name'] = "pcscd";
	$pconfig['description'] = gettext("PC/SC Smart Card Daemon");
	$services[] = $pconfig;

	$pconfig = array();
	$pconfig['name'] = "ntpd";
	$pconfig['description'] = gettext("NTP clock sync");
	$services[] = $pconfig;

	$pconfig = array();
	$pconfig['name'] = "syslogd";
	$pconfig['description'] = gettext("System Logger Daemon");
	$services[] = $pconfig;

	if (is_array($config['captiveportal'])) {
		foreach ($config['captiveportal'] as $zone => $setting) {
			if (isset($setting['enable'])) {
				$pconfig = array();
				$pconfig['name'] = "captiveportal";
				$pconfig['zone'] = $zone;
				$pconfig['description'] = gettext("Captive Portal") . ": " . htmlspecialchars($setting['zone']);
				$services[] = $pconfig;
			}
		}
	}

	$iflist = array();
	$ifdescrs = get_configured_interface_list();
	foreach ($ifdescrs as $if) {
		$oc = $config['interfaces'][$if];
		if ($oc['if'] && (!link_interface_to_bridge($if))) {
			$iflist[$if] = $if;
		}
	}

	if (isset($config['dhcrelay']['enable'])) {
		$pconfig = array();
		$pconfig['name'] = "dhcrelay";
		$pconfig['description'] = gettext("DHCP Relay");
		$services[] = $pconfig;
	}

	if (isset($config['dhcrelay6']['enable'])) {
		$pconfig = array();
		$pconfig['name'] = "dhcrelay6";
		$pconfig['description'] = gettext("DHCPv6 Relay");
		$services[] = $pconfig;
	}

	if (is_dhcp_server_enabled()) {
		$pconfig = array();
		$pconfig['name'] = "dhcpd";
		$pconfig['description'] = gettext("DHCP Service");
		$services[] = $pconfig;
	}

	$gateways_arr = return_gateways_array();
	if (is_array($gateways_arr)) {
		$pconfig = array();
		$pconfig['name'] = "dpinger";
		$pconfig['description'] = gettext("Gateway Monitoring Daemon");
		$services[] = $pconfig;
	}

	if (isset($config['snmpd']['enable'])) {
		$pconfig = array();
		$pconfig['name'] = "bsnmpd";
		$pconfig['description'] = gettext("SNMP Service");
		$services[] = $pconfig;
	}

	if (is_array($config['igmpproxy']['igmpentry']) && (count($config['igmpproxy']['igmpentry']) > 0)) {
		$pconfig = array();
		$pconfig['name'] = "igmpproxy";
		$pconfig['description'] = gettext("IGMP proxy");
		$services[] = $pconfig;
	}

	if (isset($config['installedpackages']['miniupnpd']) && $config['installedpackages']['miniupnpd']['config'][0]['enable']) {
		$pconfig = array();
		$pconfig['name'] = "miniupnpd";
		$pconfig['description'] = gettext("UPnP Service");
		$services[] = $pconfig;
	}

	if (ipsec_enabled()) {
		$pconfig = array();
		$pconfig['name'] = "ipsec";
		$pconfig['description'] = gettext("IPsec VPN");
		$services[] = $pconfig;
	}

	if (isset($config['system']['ssh']['enable'])) {
		$pconfig = array();
		$pconfig['name'] = "sshd";
		$pconfig['description'] = gettext("Secure Shell Daemon");
		$services[] = $pconfig;
	}

	foreach (array('server', 'client') as $mode) {
		if (is_array($config['openvpn']["openvpn-{$mode}"])) {
			foreach ($config['openvpn']["openvpn-{$mode}"] as $id => $setting) {
				if (!isset($setting['disable'])) {
					$pconfig = array();
					$pconfig['name'] = "openvpn";
					$pconfig['mode'] = $mode;
					$pconfig['id'] = $id;
					$pconfig['vpnid'] = $setting['vpnid'];
					$pconfig['description'] = gettext("OpenVPN") . " " . $mode . ": " . htmlspecialchars($setting['description']);
					$services[] = $pconfig;
				}
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
	global $config;
	if (is_array($config['installedpackages']['package'])) {
		foreach ($config['installedpackages']['package'] as $pkg) {
			if ($pkg['name'] == $package_name) {
				return $pkg['descr'];
			}
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
			if (isset($config['captiveportal'][$service['zone']]['httpslogin'])) {
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
			$running = is_pid_running("{$g['varrun_path']}/charon.pid");
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
		$fa_class = "fa fa-check-circle";
		$fa_class_thumbs = "fa fa-thumbs-o-up";
		$Thumbs_UpDown = "Thumbs up";
	} else {
		if (is_service_enabled($service['name'])) {
			$statustext = gettext("Stopped");
			$text_class = "text-danger";
			$fa_class = "fa fa-times-circle";
		} else {
			$statustext = gettext("Disabled");
			$text_class = "text-warning";
			$fa_class = "fa fa-ban";
		}
		$fa_class_thumbs = "fa fa-thumbs-o-down";
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

		$output .= "<i class=\"fa fa-repeat\" title=\"" . sprintf(gettext("Restart %sService"), $stitle) . "\"></i></a>\n";

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

		$output .= "<i class=\"fa fa-stop-circle-o\" title=\"" . sprintf(gettext("Stop %sService"), $stitle) . "\"></i></a>";

	} else {
		$service_enabled = is_service_enabled($service['name']);

		if ($service['name'] == 'openvpn' || $service['name'] == 'captiveportal' || $service_enabled) {
			$output .= sprintf($link, sprintf(gettext("Start %sService"), $stitle), 'startservice');
			$output .= '<i class="fa fa-play-circle"></i></a> ';
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
		case 'pcscd':
			mwexec_bg("service pcscd onestart");
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
		case 'ipsec':
			exec("/usr/local/sbin/strongswanrc stop");
			break;
		case 'openvpn':
			$vpnmode = htmlspecialchars($extras['vpnmode']);
			if (($vpnmode == "server") or ($vpnmode == "client")) {
				$id = htmlspecialchars($extras['id']);
				$pidfile = "{$g['varrun_path']}/openvpn_{$vpnmode}{$id}.pid";
				killbypid($pidfile);
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
			sigkillbyname("sshguard", "TERM");
			break;
		case 'pcscd':
			mwexec_bg("service pcscd onestop");
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
		case 'openvpn':
			$vpnmode = htmlspecialchars($extras['vpnmode']);
			if ($vpnmode == "server" || $vpnmode == "client") {
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

?>
