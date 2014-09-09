<?php
/****h* pfSense/service-utils
 * NAME
 *   service-utils.inc - Service facility
 * DESCRIPTION
 *   This file contains various functions used by the pfSense service facility.
 * HISTORY
 *   $Id$
 ******
 *
 * Copyright (C) 2005-2006 Colin Smith (ethethlay@gmail.com)
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * RISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 */

/*
	pfSense_BUILDER_BINARIES:	/bin/pgrep /bin/sh /usr/bin/killall
	pfSense_MODULE:	utils
*/
require_once("globals.inc");
require_once("captiveportal.inc");
require_once("openvpn.inc");
require_once("ipsec.inc");
require_once("vpn.inc");
require_once("vslb.inc");
require_once("gwlb.inc");

define("RCFILEPREFIX", "/usr/local/etc/rc.d/");
function write_rcfile($params) {
	global $g;

	safe_mkdir(RCFILEPREFIX);
	$rcfile_fullname = RCFILEPREFIX . $params['file'];
	if (!file_exists($rcfile_fullname) && !is_link($rcfile_fullname) && !touch($rcfile_fullname))
		return false;

	if (!is_writable($rcfile_fullname) || empty($params['start']))
		return false;

	$towrite = "#!/bin/sh\n";
	$towrite .= "# This file was automatically generated\n# by the {$g['product_name']} service handler.\n\n";

	/* write our rc functions */
	$towrite .= "rc_start() {\n";
	$towrite .= "\t{$params['start']}\n";
	$towrite .= "}\n\n";
	if(!empty($params['stop'])) {
		$tokill =& $params['stop'];
	} else if(!empty($params['executable'])) {
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

function start_service($name) {
	global $config;

	if (empty($name))
		return;

	if (is_array($config['installedpackages']) && is_array($config['installedpackages']['service'])) {
		foreach($config['installedpackages']['service'] as $service) {
			if(strtolower($service['name']) == strtolower($name)) {
				if($service['rcfile']) {
					$prefix = RCFILEPREFIX;
					if (!empty($service['prefix'])) {
						$prefix =& $service['prefix'];
					}
					if(file_exists("{$prefix}{$service['rcfile']}") || is_link("{$prefix}{$service['rcfile']}")) {
						mwexec_bg("{$prefix}{$service['rcfile']} start");
					}
				}
				if (!empty($service['startcmd']))
					eval($service['startcmd']);
				break;
			}
		}
	}
}

function stop_service($name) {
	global $config;

	if (empty($name))
		return;

	if (is_array($config['installedpackages']) && is_array($config['installedpackages']['service'])) {
		foreach($config['installedpackages']['service'] as $service) {
			if(strtolower($service['name']) == strtolower($name)) {
				if($service['rcfile']) {
					$prefix = RCFILEPREFIX;
					if(!empty($service['prefix'])) {
						$prefix =& $service['prefix'];
					}
					if(file_exists("{$prefix}{$service['rcfile']}") || is_link("{$prefix}{$service['rcfile']}")) {
						mwexec("{$prefix}{$service['rcfile']} stop");
					}
					return;
				}
				if (!empty($service['stopcmd']))
					eval($service['stopcmd']);

				break;
			}
		}
	}
}

function restart_service($name) {
	global $config;

	if (empty($name))
		return;

	stop_service($name);
	start_service($name);

	if (is_array($config['installedpackages']) && is_array($config['installedpackages']['service'])) {
		foreach($config['installedpackages']['service'] as $service) {
			if(strtolower($service['name']) == strtolower($name)) {
				if($service['restartcmd']) {
					eval($service['restartcmd']);
				}
				break;
			}
		}
	}
}

function is_pid_running($pidfile) {
	if (!file_exists($pidfile))
		return false;

	return (isvalidpid($pidfile));
}

function is_dhcp_running($interface) {
	$status = find_dhclient_process($interface);
	if($status != 0)
		return true;
	return false;
}

function restart_service_if_running($service) {
	global $config;
	if(is_service_running($service))
		restart_service($service);
	return;
}

function is_service_enabled($service_name) {
	global $config;
	if ($service_name == "")
		return false;
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

	if(is_array($config['installedpackages']['service'])) {
		foreach($config['installedpackages']['service'] as $aservice) {
			if(strtolower($service) == strtolower($aservice['name'])) {
				if ($aservice['custom_php_service_status_command'] <> "") {
					eval("\$rc={$aservice['custom_php_service_status_command']};");
					return $rc;
				}
				if(empty($aservice['executable']))
					return false;
				if (is_process_running($aservice['executable']))
					return true;

				return false;
			}
		}
	}

	if (is_process_running($service))
		return true;

	return false;
}

function get_services() {
	global $config;
	if (is_array($config['installedpackages']['service']))
		$services = $config['installedpackages']['service'];
	else
		$services = array();

	/*    Add services that are in the base.
	 *
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
		$pconfig['description'] = gettext("Unbound DNS Forwarder");
		$services[] = $pconfig;
	}

	$pconfig = array();
	$pconfig['name'] = "ntpd";
	$pconfig['description'] = gettext("NTP clock sync");
	$services[] = $pconfig;

	if (is_array($config['captiveportal'])) {
		foreach ($config['captiveportal'] as $zone => $setting) {
			if (isset($setting['enable'])) {
				$pconfig = array();
				$pconfig['name'] = "captiveportal";
				$pconfig['zone'] = $zone;
				$pconfig['description'] = gettext("Captive Portal") . ": ".htmlspecialchars($setting['zone']);
				$services[] = $pconfig;
			}
		}
	}

	$iflist = array();
	$ifdescrs = get_configured_interface_list();
	foreach ($ifdescrs as $if) {
		$oc = $config['interfaces'][$if];
		if ($oc['if'] && (!link_interface_to_bridge($if)))
			$iflist[$if] = $if;
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
		$pconfig['name'] = "apinger";
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

	if (isset($config['installedpackages']['routed']) && $config['installedpackages']['routed']['config'][0]['enable']) {
		$pconfig = array();
		$pconfig['name'] = "routed";
		$pconfig['description'] = gettext("RIP Daemon");
		$services[] = $pconfig;
	}

	if (isset($config['ipsec']['enable'])) {
		$pconfig = array();
		$pconfig['name'] = "ipsec";
		$pconfig['description'] = gettext("IPsec VPN");
		$services[] = $pconfig;
	}

	if (isset($config['system']['enablesshd'])) {
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
					$pconfig['description'] = gettext("OpenVPN") . " ".$mode.": ".htmlspecialchars($setting['description']);
					$services[] = $pconfig;
				}
			}
		}
	}

	if (count($config['load_balancer']['virtual_server']) && count($config['load_balancer']['lbpool'])) {
		$pconfig = array();
		$pconfig['name'] = "relayd";
		$pconfig['description'] = gettext("Server load balancing daemon");
		$services[] = $pconfig;
	}
	return $services;
}

function find_service_by_name($name) {
	$services = get_services();
	foreach ($services as $service)
		if ($service["name"] == $name)
			return $service;
	return array();
}

function find_service_by_openvpn_vpnid($vpnid) {
	$services = get_services();
	foreach ($services as $service)
		if (($service["name"] == "openvpn") && isset($service["vpnid"]) && ($service["vpnid"] == $vpnid))
			return $service;
	return array();
}

function find_service_by_cp_zone($zone) {
	$services = get_services();
	foreach ($services as $service)
		if (($service["name"] == "captiveportal") && isset($service["zone"]) && ($service["zone"] == $zone))
			return $service;
	return array();
}

function service_name_compare($a, $b) {
	if (strtolower($a['name']) == strtolower($b['name']))
		return 0;
	return (strtolower($a['name']) < strtolower($b['name'])) ? -1 : 1;
}

function get_pkg_descr($package_name) {
	global $config;
	if (is_array($config['installedpackages']['package'])) {
		foreach($config['installedpackages']['package'] as $pkg) {
			if($pkg['name'] == $package_name)
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
			$running = is_pid_running("{$g['varrun_path']}/lighty-{$service['zone']}-CaptivePortal.pid");
			if (isset($config['captiveportal'][$service['zone']]['httpslogin']))
				$running = $running && is_pid_running("{$g['varrun_path']}/lighty-{$service['zone']}-CaptivePortal-SSL.pid");
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

function get_service_status_icon($service, $withtext = true, $smallicon = false) {
	global $g;
	$output = "";
	if(get_service_status($service)) {
		$statustext = gettext("Running");
		$output .= "<img style=\"vertical-align:middle\" title=\"" . sprintf(gettext("%s Service is"),$service["name"]) . " {$statustext}\" src=\"/themes/" . $g["theme"] . "/images/icons/";
		$output .= ($smallicon) ? "icon_pass.gif" : "icon_service_running.gif";
		$output .= "\" alt=\"status\" />&nbsp;";
		if ($withtext)
			$output .= "&nbsp;" . $statustext;
	} else {
		$service_enabled = is_service_enabled($service['name']);
		$statustext = ($service_enabled) ? gettext("Stopped") : gettext("Disabled");
		$output .= "<img style=\"vertical-align:middle\" title=\"" . sprintf(gettext("%s Service is"),$service["name"]) . " {$statustext}\" src=\"/themes/" . $g["theme"] . "/images/icons/";
		$output .= ($smallicon) ? "icon_block.gif" : "icon_service_stopped.gif";
		$output .= "\" alt=\"status\" />&nbsp;";
		if ($withtext)
			$output .= "&nbsp;<font color=\"white\">{$statustext}</font>";
	}
	return $output;
}

function get_service_control_links($service, $addname = false) {
	global $g;
	$output = "";
	$stitle = ($addname) ? $service['name'] . " " : "";
	if(get_service_status($service)) {
		switch ($service['name']) {
			case "openvpn":
				$output .= "<a href='status_services.php?mode=restartservice&amp;service={$service['name']}&amp;vpnmode={$service['mode']}&amp;id={$service['vpnid']}'>";
				break;
			case "captiveportal":
				$output .= "<a href='status_services.php?mode=restartservice&amp;service={$service['name']}&amp;zone={$service['zone']}'>";
				break;
			default:
				$output .= "<a href='status_services.php?mode=restartservice&amp;service={$service['name']}'>";
		}
		$output .= "<img style=\"vertical-align:middle\" title='" . sprintf(gettext("Restart %sService"),$stitle) . "' border='0' src='./themes/".$g['theme']."/images/icons/icon_service_restart.gif' alt='restart' /></a>\n";
		switch ($service['name']) {
			case "openvpn":
				$output .= "<a href='status_services.php?mode=stopservice&amp;service={$service['name']}&amp;vpnmode={$service['mode']}&amp;id={$service['vpnid']}'>";
				break;
			case "captiveportal":
				$output .= "<a href='status_services.php?mode=stopservice&amp;service={$service['name']}&amp;zone={$service['zone']}'>";
				break;
			default:
				$output .= "<a href='status_services.php?mode=stopservice&amp;service={$service['name']}'>";
		}
		$output .= "<img style=\"vertical-align:middle\" title='" . sprintf(gettext("Stop %sService"),$stitle) . "' border='0' src='./themes/".$g['theme']."/images/icons/icon_service_stop.gif' alt='stop' />";
		$output .= "</a>";
	} else {
		$service_enabled = is_service_enabled($service['name']);
		switch ($service['name']) {
			case "openvpn":
				$output .= "<a href='status_services.php?mode=startservice&amp;service={$service['name']}&amp;vpnmode={$service['mode']}&amp;id={$service['vpnid']}'>";
				break;
			case "captiveportal":
				$output .= "<a href='status_services.php?mode=startservice&amp;service={$service['name']}&amp;zone={$service['zone']}'>";
				break;
			default:
				if ($service_enabled)
					$output .= "<a href='status_services.php?mode=startservice&amp;service={$service['name']}'>";
		}
		if ($service_enabled)
			$output .= "<img style=\"vertical-align:middle\" title='" . sprintf(gettext("Start %sService"),$stitle) . "' border='0' src='./themes/".$g['theme']."/images/icons/icon_service_start.gif' alt='start' /></a>\n";
	}
	return $output;
}

function service_control_start($name, $extras) {
	global $g;
	switch($name) {
		case 'radvd':
			services_radvd_configure();
			break;
		case 'captiveportal':
			$zone = htmlspecialchars($extras['zone']);
			captiveportal_init_webgui_zonename($zone);
			break;
		case 'ntpd':
		case 'openntpd':
			system_ntp_configure();
			break;
		case 'apinger':
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
			vpn_ipsec_force_reload();
			break;
		case 'sshd':
			send_event("service restart sshd");
			break;
		case 'openvpn':
			$vpnmode = isset($extras['vpnmode']) ? htmlspecialchars($extras['vpnmode']) : htmlspecialchars($extras['mode']);
			if (($vpnmode == "server") || ($vpnmode == "client")) {
				$id = isset($extras['vpnid']) ? htmlspecialchars($extras['vpnid']) : htmlspecialchars($extras['id']);
				$configfile = "{$g['varetc_path']}/openvpn/{$vpnmode}{$id}.conf";
				if (file_exists($configfile))
					openvpn_restart_by_vpnid($vpnmode, $id);
			}
			break;
		case 'relayd':
			relayd_configure();
			break;
		default:
			start_service($name);
			break;
	}
	return sprintf(gettext("%s has been started."),htmlspecialchars($name));
}
function service_control_stop($name, $extras) {
	global $g;
	switch($name) {
		case 'radvd':
			killbypid("{$g['varrun_path']}/radvd.pid");
			break;
		case 'captiveportal':
			$zone = htmlspecialchars($extras['zone']);
			killbypid("{$g['varrun_path']}/lighty-{$zone}-CaptivePortal.pid");
			killbypid("{$g['varrun_path']}/lighty-{$zone}-CaptivePortal-SSL.pid");
			break;
		case 'ntpd':
			killbyname("ntpd");
			break;
		case 'openntpd':
			killbyname("openntpd");
			break;
		case 'apinger':
			killbypid("{$g['varrun_path']}/apinger.pid");
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
			exec("/usr/local/sbin/ipsec stop");
			break;
		case 'openvpn':
			$vpnmode = htmlspecialchars($extras['vpnmode']);
			if (($vpnmode == "server") or ($vpnmode == "client")) {
				$id = htmlspecialchars($extras['id']);
				$pidfile = "{$g['varrun_path']}/openvpn_{$vpnmode}{$id}.pid";
				killbypid($pidfile);
			}
			break;
		case 'relayd':
			mwexec('pkill relayd');
			break;
		default:
			stop_service($name);
			break;
	}
	return sprintf(gettext("%s has been stopped."), htmlspecialchars($name));
}

function service_control_restart($name, $extras) {
	global $g;
	switch($name) {
		case 'radvd':
			services_radvd_configure();
			break;
		case 'captiveportal':
			$zone = htmlspecialchars($extras['zone']);
			killbypid("{$g['varrun_path']}/lighty-{$zone}-CaptivePortal.pid");
			killbypid("{$g['varrun_path']}/lighty-{$zone}-CaptivePortal-SSL.pid");
			captiveportal_init_webgui_zonename($zone);
			break;
		case 'ntpd':
		case 'openntpd':
			system_ntp_configure();
			break;
		case 'apinger':
			killbypid("{$g['varrun_path']}/apinger.pid");
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
			vpn_ipsec_force_reload();
			break;
		case 'sshd':
			send_event("service restart sshd");
			break;
		case 'openvpn':
			$vpnmode = htmlspecialchars($extras['vpnmode']);
			if ($vpnmode == "server" || $vpnmode == "client") {
				$id = htmlspecialchars($extras['id']);
				$configfile = "{$g['varetc_path']}/openvpn/{$vpnmode}{$id}.conf";
				if (file_exists($configfile))
					openvpn_restart_by_vpnid($vpnmode, $id);
			}
			break;
		case 'relayd':
			relayd_configure(true);
			break;
		default:
			restart_service($name);
			break;
	}
	return sprintf(gettext("%s has been restarted."),htmlspecialchars($name));
}

?>
