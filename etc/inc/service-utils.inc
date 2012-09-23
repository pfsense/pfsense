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

define("RCFILEPREFIX", "/usr/local/etc/rc.d/");
function write_rcfile($params) {
	global $g;

	$rcfile_fullname = RCFILEPREFIX . $params['file'];
	if (!file_exists($rcfile_fullname) && !touch($rcfile_fullname))
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
		$tokill = "/usr/bin/killall {$params['executable']}";
	} else {
		/* make an educated guess (bad) */
		$tokill = array_pop(explode('/', array_shift(explode(' ', $params['start']))));
	}
	$towrite .= "rc_stop() {\n";
	$towrite .= "\t{$tokill}\n";
	$towrite .= "}\n\n";

	/* begin rcfile logic */
	$towrite .= "case \$1 in\n\tstart)\n\t\trc_start\n\t\t;;\n\tstop)\n\t\trc_stop\n\t\t;;\n\trestart)\n\t\trc_stop\n\t\trc_start\n\t\t;;\nesac\n\n";

	file_put_contents($rcfile_fullname, $towrite);
	@chmod("{$rcfile_fullname}", 0755);

	return;
}

function start_service($name) {
	global $config;

	if (empty($name))
		return;

	/* make sure service is stopped before starting */
	stop_service($name);
	sleep(2);

	$rcfile_fullname = RCFILEPREFIX . $name . '.sh';
	if(file_exists($rcfile_fullname)) {
		mwexec_bg("/bin/sh {$rcfile_fullname} start");
		return;
	}
	if($config['installedpackages']['service']) {
		foreach($config['installedpackages']['service'] as $service) {
			if(strtolower($service['name']) == strtolower($name)) {
				if($service['rcfile']) {
					$prefix = RCFILEPREFIX;
					if (!empty($service['prefix'])) {
						$prefix =& $service['prefix'];
					}
					if(file_exists("{$prefix}{$service['rcfile']}")) {
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

	if ($config['installedpackages']['service']) {
		foreach($config['installedpackages']['service'] as $service) {
			if(strtolower($service['name']) == strtolower($name)) {
				if($service['rcfile']) {
					$prefix = RCFILEPREFIX;
					if(!empty($service['prefix'])) {
						$prefix =& $service['prefix'];
					}
					if(file_exists("{$prefix}{$service['rcfile']}")) {
						mwexec("{$prefix}{$service['rcfile']} stop");
					}
					return;
				}
				if (!empty($service['stopcmd']))
					eval($service['stopcmd']);

				if(!($service['rcfile'] or $service['stopcmd'])) {
					if(is_process_running("{$service['executable']}"))
						mwexec("/usr/bin/killall {$service['executable']}");
					return;
				}
				break;
			}
		}
	}
	/* finally if we get here lets simply kill the service name */
	if(is_process_running("{$name}"))
		mwexec("/usr/bin/killall {$name}");
}

function restart_service($name) {
	global $config;

	if (empty($name))
		return;

	stop_service($name);
	start_service($name);

	if($config['installedpackages']['service']) {
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
	$running = shell_exec("/bin/pgrep -F {$pidfile} 2>/dev/null");

	return (!empty($running));
}

function is_dhcp_running($interface) {
	$status = find_dhclient_process($interface);
	if($status <> "")
		return true;
	return false;
}

function restart_service_if_running($service) {
	global $config;
	if(is_service_running($service))
		restart_service($service);
	return;
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
	if(is_radvd_enabled()) {
		$pconfig = array();
		$pconfig['name'] = "radvd";
		$pconfig['description'] = gettext("Router Advertisement Daemon");
		$services[] = $pconfig;
	}

	if(isset($config['dnsmasq']['enable'])) {
		$pconfig = array();
		$pconfig['name'] = "dnsmasq";
		$pconfig['description'] = gettext("DNS Forwarder");
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
	$show_dhcprelay = false;
	foreach($iflist as $if) {
		if(isset($config['dhcrelay'][$if]['enable']))
			$show_dhcprelay = true;
	}

	if($show_dhcprelay == true) {
		$pconfig = array();
		$pconfig['name'] = "dhcrelay";
		$pconfig['description'] = gettext("DHCP Relay");
		$services[] = $pconfig;
	}

	if(is_dhcp_server_enabled()) {
		$pconfig = array();
		$pconfig['name'] = "dhcpd";
		$pconfig['description'] = gettext("DHCP Service");
		$services[] = $pconfig;
	}

	if(isset($config['snmpd']['enable'])) {
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
		$pconfig['name'] = "racoon";
		$pconfig['description'] = gettext("IPsec VPN");
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
		$output .= '<td class="listr"><center>' . "\n";
		$output .= "<img style=\"vertical-align:middle\" title=\"" . sprintf(gettext("%s Service is"),$service["name"]) . " {$statustext}\" src=\"/themes/" . $g["theme"] . "/images/icons/";
		$output .= ($smallicon) ? "icon_pass.gif" : "icon_service_running.gif";
		$output .= "\">";
		if ($withtext)
			$output .= "&nbsp;&nbsp;" . $statustext;
		$output .= "</td>\n";
	} else {
		$statustext = gettext("Stopped");
		$output .= '<td class="listbg"><center>' . "\n";
		$output .= "<img style=\"vertical-align:middle\" title=\"" . sprintf(gettext("%s Service is"),$service["name"]) . " {$statustext}\" src=\"/themes/" . $g["theme"] . "/images/icons/";
		$output .= ($smallicon) ? "icon_block.gif" : "icon_service_stopped.gif";
		$output .= "\">";
		if ($withtext)
			$output .= "&nbsp;&nbsp;" . "<font color=\"white\">{$statustext}</font>";
		$output .= "</td>\n";
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
				$output .= "<a href='status_services.php?mode=restartservice&service={$service['name']}&vpnmode={$service['mode']}&id={$service['vpnid']}'>";
				break;
			case "captiveportal":
				$output .= "<a href='status_services.php?mode=restartservice&service={$service['name']}&zone={$service['zone']}'>";
				break;
			default:
				$output .= "<a href='status_services.php?mode=restartservice&service={$service['name']}'>";
		}
		$output .= "<img style=\"vertical-align:middle\" title='" . sprintf(gettext("Restart %sService"),$stitle) . "' border='0' src='./themes/".$g['theme']."/images/icons/icon_service_restart.gif'></a>\n";
		switch ($service['name']) {
			case "openvpn":
				$output .= "<a href='status_services.php?mode=stopservice&service={$service['name']}&vpnmode={$service['mode']}&id={$service['vpnid']}'>";
				break;
			case "captiveportal":
				$output .= "<a href='status_services.php?mode=stopservice&service={$service['name']}&zone={$service['zone']}'>";
				break;
			default:
				$output .= "<a href='status_services.php?mode=stopservice&service={$service['name']}'>";
		}
		$output .= "<img style=\"vertical-align:middle\" title='" . sprintf(gettext("Stop %sService"),$stitle) . "' border='0' src='./themes/".$g['theme']."/images/icons/icon_service_stop.gif'>";
		$output .= "</a>";
	} else {
		switch ($service['name']) {
			case "openvpn":
				$output .= "<a href='status_services.php?mode=startservice&service={$service['name']}&vpnmode={$service['mode']}&id={$service['vpnid']}'>";
				break;
			case "captiveportal":
				$output .= "<a href='status_services.php?mode=startservice&service={$service['name']}&zone={$service['zone']}'>";
				break;
			default:
				$output .= "<a href='status_services.php?mode=startservice&service={$service['name']}'>";
		}
		$output .= "<img style=\"vertical-align:middle\" title='" . sprintf(gettext("Start %sService"),$stitle) . "' border='0' src='./themes/".$g['theme']."/images/icons/icon_service_start.gif'></a>\n";
	}
	return $output;
}
?>