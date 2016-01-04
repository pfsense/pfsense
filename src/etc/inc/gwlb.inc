<?php
/*
	gwlb.inc
	Copyright (C) 2008 Bill Marquette, Seth Mos
	Copyright (C) 2010 Ermal Luçi
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	notice, this list of conditions and the following disclaimer in the
	documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
 */

require_once("config.inc");
require_once("rrd.inc");

/* Returns an array of default values used for dpinger */
function return_dpinger_defaults() {
	return array(
		"latencylow" => "200",
		"latencyhigh" => "500",
		"losslow" => "10",
		"losshigh" => "20",
		"interval" => "250",
		"loss_interval" => "1250",
		"time_period" => "30000",
		"alert_interval" => "1000");
}

function running_dpinger_processes() {
	global $g;

	$pidfiles = glob("{$g['varrun_path']}/dpinger_*.pid");

	$result = array();
	if ($pidfiles === FALSE) {
		return $result;
	}

	foreach ($pidfiles as $pidfile) {
		if (preg_match('/^dpinger_(.+)_([^_]+)_([^_]+)\.pid$/',
		    basename($pidfile), $matches)) {
			$socket_file = preg_replace('/\.pid$/', '.sock',
			    $pidfile);
			$result[$matches[1]] = array(
			    'srcip'    => $matches[2],
			    'targetip' => $matches[3],
			    'pidfile'  => $pidfile,
			    'socket'   => $socket_file
			);
			unset($gwinfo);
		}
	}

	return $result;
}

/*
 * Stop one or more dpinger process
 * default parameter $gwname is '*' that will kill all running sessions
 * If a gateway name is passed, only this one will be killed
 */
function stop_dpinger($gwname = '') {
	global $g;

	$running_processes = running_dpinger_processes();

	foreach ($running_processes as $running_gwname => $process) {
		if ($gwname != '' && $running_gwname != $gwname) {
			continue;
		}

		if (isvalidpid($process['pidfile'])) {
			killbypid($process['pidfile']);
		} else {
			@unlink($process['pidfile']);
		}
	}
}

function start_dpinger($gateway) {
	global $g;

	if (!isset($gateway['gwifip'])) {
		return;
	}

	$dpinger_defaults = return_dpinger_defaults();

	$pidfile = "{$g['varrun_path']}/dpinger_{$gateway['name']}_" .
	    "{$gateway['gwifip']}_{$gateway['monitor']}.pid";
	$socket = "{$g['varrun_path']}/dpinger_{$gateway['name']}_" .
	    "{$gateway['gwifip']}_{$gateway['monitor']}.sock";
	$alarm_cmd = "{$g['etc_path']}/rc.gateway_alarm";

	$params  = "-S ";			/* Log warnings via syslog */
	$params .= "-r 0 ";			/* Disable unused reporting thread */
	$params .= "-i {$gateway['name']} ";	/* Identifier */
	$params .= "-B {$gateway['gwifip']} ";	/* Bind src address */
	$params .= "-p {$pidfile} ";		/* PID filename */
	$params .= "-u {$socket} ";		/* Status Socket */
	$params .= "-C \"{$alarm_cmd}\" ";	/* Command to run on alarm */

	$params .= "-s " .
	    (isset($gateway['interval']) && is_numeric($gateway['interval'])
	    ? $gateway['interval']
	    : $dpinger_defaults['interval']
	    ) . " ";

	$params .= "-l " .
	    (isset($gateway['loss_interval']) && is_numeric($gateway['loss_interval'])
	    ?  $gateway['loss_interval']
	    : $dpinger_defaults['loss_interval']
	    ) . " ";

	$params .= "-t " .
	    (isset($gateway['time_period']) && is_numeric($gateway['time_period'])
	    ?  $gateway['time_period']
	    : $dpinger_defaults['time_period']
	    ) . " ";

	$params .= "-A " .
	    (isset($gateway['alert_interval']) && is_numeric($gateway['alert_interval'])
	    ?  $gateway['alert_interval']
	    : $dpinger_defaults['alert_interval']
	    ) . " ";

	$params .= "-D " .
	    (isset($gateway['latencyhigh']) && is_numeric($gateway['latencyhigh'])
	    ?  $gateway['latencyhigh']
	    : $dpinger_defaults['latencyhigh']
	    ) . " ";

	$params .= "-L " .
	    (isset($gateway['losshigh']) && is_numeric($gateway['losshigh'])
	    ?  $gateway['losshigh']
	    : $dpinger_defaults['losshigh']
	    ) . " ";

	/* Make sure we don't end up with 2 process for the same GW */
	stop_dpinger($gateway['name']);

	/* Redirect stdout to /dev/null to avoid exec() to wait for dpinger */
	return mwexec("/usr/local/bin/dpinger {$params} {$gateway['monitor']} >/dev/null");
}

/*
 * Starts dpinger processes and adds appropriate static routes for monitor IPs
 */
function setup_gateways_monitor() {
	global $config, $g;

	$gateways_arr = return_gateways_array();
	if (!is_array($gateways_arr)) {
		log_error("No gateways to monitor. dpinger will not run.");
		stop_dpinger();
		return;
	}

	$monitor_ips = array();
	foreach ($gateways_arr as $gwname => $gateway) {
		/* Do not monitor if such was requested */
		if (isset($gateway['monitor_disable'])) {
			continue;
		}
		if (empty($gateway['monitor']) || !is_ipaddr($gateway['monitor'])) {
			if (is_ipaddr($gateway['gateway'])) {
				$gateways_arr[$gwname]['monitor'] = $gateway['gateway'];
			} else { /* No chance to get an ip to monitor skip target. */
				continue;
			}
		}

		/* if the monitor address is already used before, skip */
		if (in_array($gateway['monitor'], $monitor_ips)) {
			continue;
		}

		/* Interface ip is needed since dpinger will bind a socket to it.
		 * However the config GUI should already have checked this and when
		 * PPPoE is used the IP address is set to "dynamic". So using is_ipaddrv4
		 * or is_ipaddrv6 to identify packet type would be wrong, especially as
		 * further checks (that can cope with the "dynamic" case) are present inside
		 * the if block. So using $gateway['ipprotocol'] is the better option.
		 */
		if ($gateway['ipprotocol'] == "inet") { // This is an IPv4 gateway...
			$gwifip = find_interface_ip($gateway['interface'], true);
			if (!is_ipaddrv4($gwifip)) {
				continue; //Skip this target
			}

			if ($gwifip == "0.0.0.0") {
				continue; //Skip this target - the gateway is still waiting for DHCP
			}

			/*
			 * If the gateway is the same as the monitor we do not add a
			 * route as this will break the routing table.
			 * Add static routes for each gateway with their monitor IP
			 * not strictly necessary but is a added level of protection.
			 */
			if (is_ipaddrv4($gateway['gateway']) && $gateway['monitor'] != $gateway['gateway']) {
				log_error("Removing static route for monitor {$gateway['monitor']} and adding a new route through {$gateway['gateway']}");
				if (interface_isppp_type($gateway['friendlyiface'])) {
					mwexec("/sbin/route change -host " . escapeshellarg($gateway['monitor']) .
						" -iface " . escapeshellarg($gateway['interface']), true);
				} else {
					mwexec("/sbin/route change -host " . escapeshellarg($gateway['monitor']) .
						" " . escapeshellarg($gateway['gateway']), true);
				}

				pfSense_kill_states("0.0.0.0/0", $gateway['monitor'], $gateway['interface'], "icmp");
			}
		} else if ($gateway['ipprotocol'] == "inet6") { // This is an IPv6 gateway...
			if (is_linklocal($gateway['gateway']) &&
			    get_ll_scope($gateway['gateway']) == '') {
				$gateways_arr[$gwname]['gateway'] .= '%' . $gateway['interface'];
			}

			if (is_linklocal($gateway['monitor'])) {
				if (get_ll_scope($gateway['monitor']) == '') {
					$gateways_arr[$gwname]['monitor'] .= '%' . $gateway['interface'];
				}

				$gwifip = find_interface_ipv6_ll($gateway['interface'], true);

				if (get_ll_scope($gwifip) == '') {
					$gwifip .= '%' . $gateway['interface'];
				}
			} else {
				$gwifip = find_interface_ipv6($gateway['interface'], true);
			}

			if (!is_ipaddrv6($gwifip)) {
				continue; //Skip this target
			}

			/*
			 * If the gateway is the same as the monitor we do not add a
			 * route as this will break the routing table.
			 * Add static routes for each gateway with their monitor IP
			 * not strictly necessary but is a added level of protection.
			 */
			if ($gateway['gateway'] != $gateway['monitor']) {
				log_error("Removing static route for monitor {$gateway['monitor']} and adding a new route through {$gateway['gateway']}");
				if (interface_isppp_type($gateway['friendlyiface'])) {
					mwexec("/sbin/route change -host -inet6 " . escapeshellarg($gateway['monitor']) .
						" -iface " . escapeshellarg($gateway['interface']), true);
				} else {
					mwexec("/sbin/route change -host -inet6 " . escapeshellarg($gateway['monitor']) .
						" " . escapeshellarg($gateway['gateway']), true);
				}

				pfSense_kill_states("::0.0.0.0/0", $gateway['monitor'], $gateway['interface'], "icmpv6");
			}
		} else {
			continue;
		}

		$monitor_ips[] = $gateway['monitor'];
		$gateways_arr[$gwname]['enable_dpinger'] = true;
		$gateways_arr[$gwname]['gwifip'] = $gwifip;
	}

	stop_dpinger();

	/* Start new processes */
	foreach ($gateways_arr as $gateway) {
		if (!isset($gateway['enable_dpinger'])) {
			continue;
		}

		if (start_dpinger($gateway) != 0) {
			log_error("Error starting gateway monitor for " .
			    $gateway['name']);
		}
	}

	return;
}

function get_dpinger_status($gwname) {
	global $g;

	$running_processes = running_dpinger_processes();

	if (!isset($running_processes[$gwname])) {
		log_error("dpinger: No dpinger session running for gateway {$gwname}");
		return false;
	}

	$proc = $running_processes[$gwname];
	unset($running_processes);

	if (!file_exists($proc['socket'])) {
		log_error("dpinger: status socket {$proc['socket']} not found");
		return false;
	}

	$fp = stream_socket_client("unix://{$proc['socket']}", $errno, $errstr, 10);
	if (!$fp) {
		log_error("dpinger: cannot connect to status socket {$proc['socket']} - $errstr ($errno)");
		return false;
	}

	$status = '';
	while (!feof($fp)) {
		$status .= fgets($fp, 1024);
	}
	fclose($fp);

	$r = array();
	list(
	    $r['gwname'],
	    $r['latency_avg'],
	    $r['latency_stddev'],
	    $r['loss']
	) = explode(' ', preg_replace('/\n/', '', $status));

	$r['srcip'] = $proc['srcip'];
	$r['targetip'] = $proc['targetip'];

	$gateways_arr = return_gateways_array();
	unset($gw);
	if (isset($gateways_arr[$gwname])) {
		$gw = $gateways_arr[$gwname];
	}

	$r['latency_avg'] = round($r['latency_avg']/1000, 3);
	$r['latency_stddev'] = round($r['latency_stddev']/1000, 3);

	$r['status'] = "none";
	if (isset($gw) && isset($gw['force_down'])) {
		$r['status'] = "force_down";
	} else if (isset($gw)) {
		$settings = return_dpinger_defaults();

		$keys = array(
		    'latencylow',
		    'latencyhigh',
		    'losslow',
		    'losshigh'
		);

		/* Replace default values by user-defined */
		foreach ($keys as $key) {
			if (isset($gw[$key]) && is_numeric($gw[$key])) {
				$settings[$key] = $gw[$key];
			}
		}

		if ($r['latency_avg'] > $settings['latencyhigh'] ||
		    $r['loss'] > $settings['losshigh']) {
			$r['status'] = "down";
		} else if ($r['latency_avg'] > $settings['latencylow']) {
			$r['status'] = "delay";
		} else if ($r['loss'] > $settings['losslow']) {
			$r['status'] = "loss";
		}
	}

	return $r;
}

/* return the status of the dpinger targets as an array */
function return_gateways_status($byname = false) {
	global $config, $g;

	$dpinger_gws = running_dpinger_processes();
	$status = array();

	$gateways_arr = return_gateways_array();

	foreach ($dpinger_gws as $gwname => $gwdata) {
		$dpinger_status = get_dpinger_status($gwname);
		if ($dpinger_status === false) {
			continue;
		}

		if ($byname == false) {
			$target = $dpinger_status['targetip'];
		} else {
			$target = $gwname;
		}

		$status[$target] = array();
		$status[$target]['monitorip'] = $dpinger_status['targetip'];
		$status[$target]['srcip'] = $dpinger_status['srcip'];
		$status[$target]['name'] = $gwname;
		$status[$target]['delay'] = empty($dpinger_status['latency_avg']) ? "0ms" : $dpinger_status['latency_avg'] . "ms";
		$status[$target]['loss'] = empty($dpinger_status['loss']) ? "0.0%" : round($dpinger_status['loss'], 1) . "%";
		$status[$target]['status'] = $dpinger_status['status'];
	}

	/* tack on any gateways that have monitoring disabled
	 * or are down, which could cause gateway groups to fail */
	$gateways_arr = return_gateways_array();
	foreach ($gateways_arr as $gwitem) {
		if (!isset($gwitem['monitor_disable'])) {
			continue;
		}
		if (!is_ipaddr($gwitem['monitor'])) {
			$realif = $gwitem['interface'];
			$tgtip = get_interface_gateway($realif);
			if (!is_ipaddr($tgtip)) {
				$tgtip = "none";
			}
			$srcip = find_interface_ip($realif);
		} else {
			$tgtip = $gwitem['monitor'];
			$srcip = find_interface_ip($realif);
		}
		if ($byname == true) {
			$target = $gwitem['name'];
		} else {
			$target = $tgtip;
		}

		/* failsafe for down interfaces */
		if ($target == "none") {
			$target = $gwitem['name'];
			$status[$target]['name'] = $gwitem['name'];
			$status[$target]['delay'] = "0.0ms";
			$status[$target]['loss'] = "100.0%";
			$status[$target]['status'] = "down";
		} else {
			$status[$target]['monitorip'] = $tgtip;
			$status[$target]['srcip'] = $srcip;
			$status[$target]['name'] = $gwitem['name'];
			$status[$target]['delay'] = "0.0ms";
			$status[$target]['loss'] = "0.0%";
			$status[$target]['status'] = "none";
		}
	}
	return($status);
}

/* Return all configured gateways on the system */
function return_gateways_array($disabled = false, $localhost = false, $inactive = false) {
	global $config, $g;

	$gateways_arr = array();
	$gateways_arr_temp = array();

	$found_defaultv4 = 0;
	$found_defaultv6 = 0;

	// Ensure the interface cache is up to date first
	$interfaces = get_interface_arr(true);
	$interfaces_v4 = array();
	$interfaces_v6 = array();

	$i = -1;
	/* Process/add all the configured gateways. */
	if (is_array($config['gateways']['gateway_item'])) {
		foreach ($config['gateways']['gateway_item'] as $gateway) {
			/* Increment it here to do not skip items */
			$i++;

			if (empty($config['interfaces'][$gateway['interface']])) {
				if ($inactive === false) {
					continue;
				} else {
					$gateway['inactive'] = true;
				}
			}
			$wancfg = $config['interfaces'][$gateway['interface']];

			/* skip disabled interfaces */
			if ($disabled === false && (!isset($wancfg['enable']))) {
				continue;
			}

			/* if the gateway is dynamic and we can find the IPv4, Great! */
			if (empty($gateway['gateway']) || $gateway['gateway'] == "dynamic") {
				if ($gateway['ipprotocol'] == "inet") {
					/* we know which interfaces is dynamic, this should be made a function */
					$gateway['gateway'] = get_interface_gateway($gateway['interface']);
					/* no IP address found, set to dynamic */
					if (!is_ipaddrv4($gateway['gateway'])) {
						$gateway['gateway'] = "dynamic";
					}
					$gateway['dynamic'] = true;
				}

				/* if the gateway is dynamic and we can find the IPv6, Great! */
				else if ($gateway['ipprotocol'] == "inet6") {
					/* we know which interfaces is dynamic, this should be made a function, and for v6 too */
					$gateway['gateway'] = get_interface_gateway_v6($gateway['interface']);
					/* no IPv6 address found, set to dynamic */
					if (!is_ipaddrv6($gateway['gateway'])) {
						$gateway['gateway'] = "dynamic";
					}
					$gateway['dynamic'] = true;
				}
			} else {
				/* getting this detection right is hard at this point because we still don't
				 * store the address family in the gateway item */
				if (is_ipaddrv4($gateway['gateway'])) {
					$gateway['ipprotocol'] = "inet";
				} else if (is_ipaddrv6($gateway['gateway'])) {
					$gateway['ipprotocol'] = "inet6";
				}
			}

			if (isset($gateway['monitor_disable'])) {
				$gateway['monitor_disable'] = true;
			} else if (empty($gateway['monitor'])) {
				$gateway['monitor'] = $gateway['gateway'];
			}

			$gateway['friendlyiface'] = $gateway['interface'];

			/* special treatment for tunnel interfaces */
			if ($gateway['ipprotocol'] == "inet6") {
				$gateway['interface'] = get_real_interface($gateway['interface'], "inet6", false, false);
				$interfaces_v6[$gateway['friendlyiface']] = $gateway['friendlyiface'];
			} else {
				$gateway['interface'] = get_real_interface($gateway['interface'], "all", false, false);
				$interfaces_v4[$gateway['friendlyiface']] = $gateway['friendlyiface'];
			}

			/* entry has a default flag, use it */
			if (isset($gateway['defaultgw'])) {
				if ($gateway['ipprotocol'] == "inet") {
					$gateway['defaultgw'] = true;
					$found_defaultv4 = 1;
				} else if ($gateway['ipprotocol'] == "inet6") {
					$gateway['defaultgw'] = true;
					$found_defaultv6 = 1;
				}
			}
			/* include the gateway index as the attribute */
			$gateway['attribute'] = $i;

			/* Remember all the gateway names, even ones to be skipped because they are disabled. */
			/* Then we can easily know and match them later when attempting to add dynamic gateways to the list. */
			$gateways_arr_temp[$gateway['name']] = $gateway;

			/* skip disabled gateways if the caller has not asked for them to be returned. */
			if (!($disabled === false && isset($gateway['disabled']))) {
				$gateways_arr[$gateway['name']] = $gateway;
			}
		}
	}
	unset($gateway);

	/* Loop through all interfaces with a gateway and add it to a array */
	if ($disabled == false) {
		$iflist = get_configured_interface_with_descr();
	} else {
		$iflist = get_configured_interface_with_descr(false, true);
	}

	/* Process/add dynamic v4 gateways. */
	foreach ($iflist as $ifname => $friendly) {
		if (!interface_has_gateway($ifname)) {
			continue;
		}

		if (empty($config['interfaces'][$ifname])) {
			continue;
		}

		$ifcfg = &$config['interfaces'][$ifname];
		if (!isset($ifcfg['enable'])) {
			continue;
		}

		if (!empty($ifcfg['ipaddr']) && is_ipaddrv4($ifcfg['ipaddr'])) {
			continue;
		}

		if (isset($interfaces_v4[$ifname])) {
			continue;
		}

		$ctype = "";
		switch ($ifcfg['ipaddr']) {
			case "dhcp":
			case "pppoe":
			case "pptp":
			case "ppp":
				$ctype = strtoupper($ifcfg['ipaddr']);
				break;
			default:
				$tunnelif = substr($ifcfg['if'], 0, 3);
				if (substr($ifcfg['if'], 0, 4) == "ovpn") {
					// if current iface is an ovpn server endpoint then check its type, skip tap only
					if (substr($ifcfg['if'], 4, 1) == 's') {
						$ovpnid = substr($ifcfg['if'], 5);
						if (is_array($config['openvpn']['openvpn-server'])) {
							foreach ($config['openvpn']['openvpn-server'] as & $ovpnserverconf) {
								if ($ovpnserverconf['vpnid'] == $ovpnid) {
									if ($ovpnserverconf['dev_mode'] == "tap") {
										continue 3;
									}
								}
							}
						}
					}
					$ctype = "VPNv4";
				} else if ($tunnelif == "gif" || $tunnelif == "gre") {
					$ctype = "TUNNELv4";
				}
				break;
		}
		$ctype = "_". strtoupper($ctype);

		$gateway = array();
		$gateway['dynamic'] = false;
		$gateway['ipprotocol'] = "inet";
		$gateway['gateway'] = get_interface_gateway($ifname, $gateway['dynamic']);
		$gateway['interface'] = get_real_interface($ifname);
		$gateway['friendlyiface'] = $ifname;
		$gateway['name'] = "{$friendly}{$ctype}";
		$gateway['attribute'] = "system";

		if (($gateway['dynamic'] === "default") && ($found_defaultv4 == 0)) {
			$gateway['defaultgw'] = true;
			$gateway['dynamic'] = true;
			$found_defaultv4 = 1;
		}
		/* Loopback dummy for dynamic interfaces without a IP */
		if (!is_ipaddrv4($gateway['gateway']) && $gateway['dynamic'] == true) {
			$gateway['gateway'] = "dynamic";
		}

		/* automatically skip known static and dynamic gateways that were previously processed */
		foreach ($gateways_arr_temp as $gateway_item) {
			if ((($ifname == $gateway_item['friendlyiface'] && $friendly == $gateway_item['name'])&& ($gateway['ipprotocol'] == $gateway_item['ipprotocol'])) ||
			    (($ifname == $gateway_item['friendlyiface'] && $gateway_item['dynamic'] == true) && ($gateway['ipprotocol'] == $gateway_item['ipprotocol']))) {
				continue 2;
			}
		}

		if (is_ipaddrv4($gateway['gateway'])) {
			$gateway['monitor'] = $gateway['gateway'];
		}

		$gateway['descr'] = "Interface {$friendly}{$ctype} Gateway";
		$gateways_arr[$gateway['name']] = $gateway;
	}
	unset($gateway);

	/* Process/add dynamic v6 gateways. */
	foreach ($iflist as $ifname => $friendly) {
		/* If the user has disabled IPv6, they probably don't want any IPv6 gateways. */
		if (!isset($config['system']['ipv6allow'])) {
			break;
		}

		if (!interface_has_gatewayv6($ifname)) {
			continue;
		}

		if (empty($config['interfaces'][$ifname])) {
			continue;
		}

		$ifcfg = &$config['interfaces'][$ifname];
		if (!isset($ifcfg['enable'])) {
			continue;
		}

		if (!empty($ifcfg['ipaddrv6']) && is_ipaddrv6($ifcfg['ipaddrv6'])) {
			continue;
		}

		if (isset($interfaces_v6[$ifname])) {
			continue;
		}

		$ctype = "";
		switch ($ifcfg['ipaddrv6']) {
			case "slaac":
			case "dhcp6":
			case "6to4":
			case "6rd":
				$ctype = strtoupper($ifcfg['ipaddrv6']);
				break;
			default:
				$tunnelif = substr($ifcfg['if'], 0, 3);
				if (substr($ifcfg['if'], 0, 4) == "ovpn") {
					// if current iface is an ovpn server endpoint then check its type, skip tap only
					if (substr($ifcfg['if'], 4, 1) == 's') {
						$ovpnid = substr($ifcfg['if'], 5);
						if (is_array($config['openvpn']['openvpn-server'])) {
							foreach ($config['openvpn']['openvpn-server'] as & $ovpnserverconf) {
								if ($ovpnserverconf['vpnid'] == $ovpnid) {
									if ($ovpnserverconf['dev_mode'] == "tap") {
										continue 3;
									}
								}
							}
						}
					}
					$ctype = "VPNv6";
				} else if ($tunnelif == "gif" || $tunnelif == "gre") {
					$ctype = "TUNNELv6";
				}
				break;
		}
		$ctype = "_". strtoupper($ctype);

		$gateway = array();
		$gateway['dynamic'] = false;
		$gateway['ipprotocol'] = "inet6";
		$gateway['gateway'] = get_interface_gateway_v6($ifname, $gateway['dynamic']);
		$gateway['interface'] = get_real_interface($ifname, "inet6");
		switch ($ifcfg['ipaddrv6']) {
			case "6rd":
			case "6to4":
				$gateway['dynamic'] = "default";
				break;
		}
		$gateway['friendlyiface'] = $ifname;
		$gateway['name'] = "{$friendly}{$ctype}";
		$gateway['attribute'] = "system";

		if (($gateway['dynamic'] === "default") && ($found_defaultv6 == 0)) {
			$gateway['defaultgw'] = true;
			$gateway['dynamic'] = true;
			$found_defaultv6 = 1;
		}

		/* Loopback dummy for dynamic interfaces without a IP */
		if (!is_ipaddrv6($gateway['gateway']) && $gateway['dynamic'] == true) {
			$gateway['gateway'] = "dynamic";
		}

		/* automatically skip known static and dynamic gateways that were previously processed */
		foreach ($gateways_arr_temp as $gateway_item) {
			if ((($ifname == $gateway_item['friendlyiface'] && $friendly == $gateway_item['name']) && ($gateway['ipprotocol'] == $gateway_item['ipprotocol'])) ||
			    (($ifname == $gateway_item['friendlyiface'] && $gateway_item['dynamic'] == true) && ($gateway['ipprotocol'] == $gateway_item['ipprotocol']))) {
				continue 2;
			}
		}

		if (is_ipaddrv6($gateway['gateway'])) {
			$gateway['monitor'] = $gateway['gateway'];
		}

		$gateway['descr'] = "Interface {$friendly}{$ctype} Gateway";
		$gateways_arr[$gateway['name']] = $gateway;
	}
	unset($gateway);

	/* FIXME: Should this be enabled.
	 * Some interface like wan might be default but have no info recorded
	 * the config. */
	/* this is a fallback if all else fails and we want to get packets out @smos */
	if ($found_defaultv4 == 0 || $found_defaultv6 == 0) {
		foreach ($gateways_arr as &$gateway) {
			if (($gateway['friendlyiface'] == "wan") && ($found_defaultv4 == 0) && (!isset($gateway['ipprotocol']) || ($gateway['ipprotocol'] == "inet"))) {
				if (file_exists("{$g['tmp_path']}/{$gateway['interface']}_defaultgw")) {
					$gateway['defaultgw'] = true;
					$found_defaultv4 = 1;
				}
			}
			else if (($gateway['friendlyiface'] == "wan") && ($found_defaultv6 == 0) && ($gateway['ipprotocol'] == "inet6")) {
				if (file_exists("{$g['tmp_path']}/{$gateway['interface']}_defaultgwv6")) {
					$gateway['defaultgw'] = true;
					$found_defaultv6 = 1;
				}
			}
		}
	}

	if ($localhost === true) {
		/* attach localhost for Null routes */
		$gwlo4 = array();
		$gwlo4['name'] = "Null4";
		$gwlo4['interface'] = "lo0";
		$gwlo4['ipprotocol'] = "inet";
		$gwlo4['gateway'] = "127.0.0.1";
		$gwlo6 = array();
		$gwlo6['name'] = "Null6";
		$gwlo6['interface'] = "lo0";
		$gwlo6['ipprotocol'] = "inet6";
		$gwlo6['gateway'] = "::1";
		$gateways_arr['Null4'] = $gwlo4;
		$gateways_arr['Null6'] = $gwlo6;
	}
	return($gateways_arr);
}

function fixup_default_gateway($ipprotocol, $gateways_status, $gateways_arr) {
	global $config, $g;
	/*
	 * NOTE: The code below is meant to replace the default gateway when it goes down.
	 *	This facilitates services running on pfSense itself and are not handled by a PBR to continue working.
	 */
	$upgw = '';
	$dfltgwname = '';
	$dfltgwdown = false;
	$dfltgwfound = false;
	foreach ($gateways_arr as $gwname => $gwsttng) {
		if (($gwsttng['ipprotocol'] == $ipprotocol) && isset($gwsttng['defaultgw'])) {
			$dfltgwfound = true;
			$dfltgwname = $gwname;
			if (!isset($gwsttng['monitor_disable']) && $gateways_status[$gwname]['status'] != "none") {
				$dfltgwdown = true;
			}
		}
		/* Keep a record of the last up gateway */
		/* XXX: Blacklist lan for now since it might cause issues to those who have a gateway set for it */
		if (empty($upgw) && ($gwsttng['ipprotocol'] == $ipprotocol) && (isset($gwsttng['monitor_disable']) || $gateways_status[$gwname]['status'] == "none") && $gwsttng[$gwname]['friendlyiface'] != "lan") {
			$upgw = $gwname;
		}
		if ($dfltgwdown == true && !empty($upgw)) {
			break;
		}
	}
	if ($dfltgwfound == false) {
		$gwname = convert_friendly_interface_to_friendly_descr("wan");
		if (!empty($gateways_status[$gwname]) && stristr($gateways_status[$gwname]['status'], "down")) {
			$dfltgwdown = true;
		}
	}
	if ($dfltgwdown == true && !empty($upgw)) {
		if ($gateways_arr[$upgw]['gateway'] == "dynamic") {
			$gateways_arr[$upgw]['gateway'] = get_interface_gateway($gateways_arr[$upgw]['friendlyiface']);
		}
		if (is_ipaddr($gateways_arr[$upgw]['gateway'])) {
			log_error("Default gateway down setting {$upgw} as default!");
			if (is_ipaddrv6($gateways_arr[$upgw]['gateway'])) {
				$inetfamily = "-inet6";
			} else {
				$inetfamily = "-inet";
			}
			mwexec("/sbin/route change {$inetfamily} default {$gateways_arr[$upgw]['gateway']}");
		}
	} else if (!empty($dfltgwname)) {
		$defaultgw = trim(exec("/sbin/route -n get -{$ipprotocol} default | /usr/bin/awk '/gateway:/ {print $2}'"), " \n");
		if ($ipprotocol == 'inet6' && !is_ipaddrv6($gateways_arr[$dfltgwname]['gateway'])) {
			return;
		}
		if ($ipprotocol == 'inet' && !is_ipaddrv4($gateways_arr[$dfltgwname]['gateway'])) {
			return;
		}
		if ($defaultgw != $gateways_arr[$dfltgwname]['gateway']) {
			mwexec("/sbin/route change -{$ipprotocol} default {$gateways_arr[$dfltgwname]['gateway']}");
		}
	}
}

/*
 * Return an array with all gateway groups with name as key
 * All gateway groups will be processed before returning the array.
 */
function return_gateway_groups_array() {
	global $config, $g;

	/* fetch the current gateways status */
	$gateways_status = return_gateways_status(true);
	$gateways_arr = return_gateways_array();
	$gateway_groups_array = array();

	if (isset($config['system']['gw_switch_default'])) {
		fixup_default_gateway("inet", $gateways_status, $gateways_arr);
		fixup_default_gateway("inet6", $gateways_status, $gateways_arr);
	}
	if (is_array($config['gateways']['gateway_group'])) {
		$carplist = get_configured_carp_interface_list();
		foreach ($config['gateways']['gateway_group'] as $group) {
			/* create array with group gateways members separated by tier */
			$tiers = array();
			$backupplan = array();
			$gwvip_arr = array();
			foreach ($group['item'] as $item) {
				list($gwname, $tier, $vipname) = explode("|", $item);

				if (is_ipaddr($carplist[$vipname])) {
					if (!is_array($gwvip_arr[$group['name']])) {
						$gwvip_arr[$group['name']] = array();
					}
					$gwvip_arr[$group['name']][$gwname] = $vipname;
				}

				/* Do it here rather than reiterating again the group in case no member is up. */
				if (!is_array($backupplan[$tier])) {
					$backupplan[$tier] = array();
				}
				$backupplan[$tier][] = $gwname;

				/* check if the gateway is available before adding it to the array */
				if (is_array($gateways_status[$gwname])) {
					$status = $gateways_status[$gwname];
					$gwdown = false;
					if (stristr($status['status'], "down")) {
						$msg = sprintf(gettext("MONITOR: %s is down, omitting from routing group {$group['name']}"), $gwname);
						$gwdown = true;
					} else if (stristr($status['status'], "loss") && strstr($group['trigger'], "loss")) {
						/* packet loss */
						$msg = sprintf(gettext("MONITOR: %s has packet loss, omitting from routing group {$group['name']}"), $gwname);
						$gwdown = true;
					} else if (stristr($status['status'], "delay") && strstr($group['trigger'] , "latency")) {
						/* high latency */
						$msg = sprintf(gettext("MONITOR: %s has high latency, omitting from routing group {$group['name']}"), $gwname);
						$gwdown = true;
					}
					if ($gwdown == true) {
						log_error($msg);
						notify_via_growl($msg);
						notify_via_smtp($msg);
					} else {
						/* Online add member */
						if (!is_array($tiers[$tier])) {
							$tiers[$tier] = array();
						}
						$tiers[$tier][] = $gwname;
					}
				} else if (isset($gateways_arr[$gwname]['monitor_disable'])) {
					$tiers[$tier][] = $gwname;
				}
			}
			$tiers_count = count($tiers);
			if ($tiers_count == 0) {
				/* Oh dear, we have no members! Engage Plan B */
				if (!platform_booting()) {
					$msg = gettext("Gateways status could not be determined, considering all as up/active. (Group: {$group['name']})");
					log_error($msg);
					notify_via_growl($msg);
					//notify_via_smtp($msg);
				}
				$tiers = $backupplan;
			}
			/* sort the tiers array by the tier key */
			ksort($tiers);

			/* we do not really foreach the tiers as we stop after the first tier */
			foreach ($tiers as $tieridx => $tier) {
				/* process all gateways in this tier */
				foreach ($tier as $member) {
					/* determine interface gateway */
					if (isset($gateways_arr[$member])) {
						$gateway = $gateways_arr[$member];
						$int = $gateway['interface'];
						$gatewayip = "";
						if (is_ipaddr($gateway['gateway'])) {
							$gatewayip = $gateway['gateway'];
						} else if (!empty($int)) {
							$gatewayip = get_interface_gateway($gateway['friendlyiface']);
						}

						if (!empty($int)) {
							$gateway_groups_array[$group['name']]['ipprotocol'] = $gateway['ipprotocol'];
							if (is_ipaddr($gatewayip)) {
								$groupmember = array();
								$groupmember['int'] = $int;
								$groupmember['gwip'] = $gatewayip;
								$groupmember['weight'] = isset($gateway['weight']) ? $gateway['weight'] : 1;
								if (is_array($gwvip_arr[$group['name']])&& !empty($gwvip_arr[$group['name']][$member])) {
									$groupmember['vip'] = $gwvip_arr[$group['name']][$member];
								}
								$gateway_groups_array[$group['name']][] = $groupmember;
							}
						}
					}
				}
				/* we should have the 1st available tier now, exit stage left */
				if (count($gateway_groups_array[$group['name']]) > 0) {
					break;
				} else {
					log_error("GATEWAYS: Group {$group['name']} did not have any gateways up on tier {$tieridx}!");
				}
			}
		}
	}

	return ($gateway_groups_array);
}

/* Update DHCP WAN Interface ip address in gateway group item */
function dhclient_update_gateway_groups_defaultroute($interface = "wan") {
	global $config, $g;
	foreach ($config['gateways']['gateway_item'] as & $gw) {
		if ($gw['interface'] == $interface) {
			$current_gw = get_interface_gateway($interface);
			if ($gw['gateway'] <> $current_gw) {
				$gw['gateway'] = $current_gw;
				$changed = true;
			}
		}
	}
	if ($changed && $current_gw) {
		write_config(sprintf(gettext('Updating gateway group gateway for %1$s - new gateway is %2$s'), $interfac, $current_gw));
	}
}

function lookup_gateway_ip_by_name($name, $disabled = false) {

	$gateways_arr = return_gateways_array($disabled, true);
	foreach ($gateways_arr as $gname => $gw) {
		if ($gw['name'] === $name || $gname === $name) {
			return $gw['gateway'];
		}
	}

	return false;
}

function lookup_gateway_monitor_ip_by_name($name) {

	$gateways_arr = return_gateways_array(false, true);
	if (!empty($gateways_arr[$name])) {
		$gateway = $gateways_arr[$name];
		if (!is_ipaddr($gateway['monitor'])) {
			return $gateway['gateway'];
		}

		return $gateway['monitor'];
	}

	return (false);
}

function lookup_gateway_interface_by_name($name) {

	$gateways_arr = return_gateways_array(false, true);
	if (!empty($gateways_arr[$name])) {
		$interfacegw = $gateways_arr[$name]['friendlyiface'];
		return ($interfacegw);
	}

	return (false);
}

function get_interface_gateway($interface, &$dynamic = false) {
	global $config, $g;

	if (substr($interface, 0, 4) == '_vip') {
		$interface = get_configured_carp_interface_list($interface, 'inet', 'iface');
	}

	$gw = NULL;
	$gwcfg = $config['interfaces'][$interface];
	if (!empty($gwcfg['gateway']) && is_array($config['gateways']['gateway_item'])) {
		foreach ($config['gateways']['gateway_item'] as $gateway) {
			if (($gateway['name'] == $gwcfg['gateway']) && (is_ipaddrv4($gateway['gateway']))) {
				$gw = $gateway['gateway'];
				break;
			}
		}
	}

	// for dynamic interfaces we handle them through the $interface_router file.
	if (($gw == NULL || !is_ipaddrv4($gw)) && !is_ipaddrv4($gwcfg['ipaddr'])) {
		$realif = get_real_interface($interface);
		if (file_exists("{$g['tmp_path']}/{$realif}_router")) {
			$gw = trim(file_get_contents("{$g['tmp_path']}/{$realif}_router"), " \n");
			$dynamic = true;
		}
		if (file_exists("{$g['tmp_path']}/{$realif}_defaultgw")) {
			$dynamic = "default";
		}

	}

	/* return gateway */
	return ($gw);
}

function get_interface_gateway_v6($interface, &$dynamic = false) {
	global $config, $g;

	if (substr($interface, 0, 4) == '_vip') {
		$interface = get_configured_carp_interface_list($interface, 'inet6', 'iface');
	}

	$gw = NULL;
	$gwcfg = $config['interfaces'][$interface];
	if (!empty($gwcfg['gatewayv6']) && is_array($config['gateways']['gateway_item'])) {
		foreach ($config['gateways']['gateway_item'] as $gateway) {
			if (($gateway['name'] == $gwcfg['gatewayv6']) && (is_ipaddrv6($gateway['gateway']))) {
				$gw = $gateway['gateway'];
				break;
			}
		}
	}

	// for dynamic interfaces we handle them through the $interface_router file.
	if (($gw == NULL || !is_ipaddrv6($gw)) && !is_ipaddrv6($gwcfg['ipaddrv6'])) {
		$realif = get_real_interface($interface);
		if (file_exists("{$g['tmp_path']}/{$realif}_routerv6")) {
			$gw = trim(file_get_contents("{$g['tmp_path']}/{$realif}_routerv6"), " \n");
			$dynamic = true;
		}
		if (file_exists("{$g['tmp_path']}/{$realif}_defaultgwv6")) {
			$dynamic = "default";
		}
	}
	/* return gateway */
	return ($gw);
}

/* Check a IP address against a gateway IP or name
 * to verify it's address family */
function validate_address_family($ipaddr, $gwname, $disabled = false) {
	$v4ip = false;
	$v6ip = false;
	$v4gw = false;
	$v6gw = false;

	if (is_ipaddrv4($ipaddr)) {
		$v4ip = true;
	}
	if (is_ipaddrv6($ipaddr)) {
		$v6ip = true;
	}
	if (is_ipaddrv4($gwname)) {
		$v4gw = true;
	}
	if (is_ipaddrv6($gwname)) {
		$v6gw = true;
	}

	if ($v4ip && $v4gw) {
		return true;
	}
	if ($v6ip && $v6gw) {
		return true;
	}

	/* still no match, carry on, lookup gateways */
	if (is_ipaddrv4(lookup_gateway_ip_by_name($gwname, $disabled))) {
		$v4gw = true;
	}
	if (is_ipaddrv6(lookup_gateway_ip_by_name($gwname, $disabled))) {
		$v6gw = true;
	}

	$gw_array = return_gateways_array();
	if (is_array($gw_array[$gwname])) {
		switch ($gw_array[$gwname]['ipprotocol']) {
			case "inet":
				$v4gw = true;
				break;
			case "inet6":
				$v6gw = true;
				break;
		}
	}

	if ($v4ip && $v4gw) {
		return true;
	}
	if ($v6ip && $v6gw) {
		return true;
	}

	return false;
}

/* check if a interface is part of a gateway group */
function interface_gateway_group_member($interface) {
	global $config;

	if (is_array($config['gateways']['gateway_group'])) {
		$groups = $config['gateways']['gateway_group'];
	} else {
		return false;
	}

	$gateways_arr = return_gateways_array(false, true);
	foreach ($groups as $group) {
		if (is_array($group['item'])) {
			foreach ($group['item'] as $item) {
				$elements = explode("|", $item);
				$gwname = $elements[0];
				if ($interface == $gateways_arr[$gwname]['interface']) {
					unset($gateways_arr);
					return true;
				}
			}
		}
	}
	unset($gateways_arr);

	return false;
}

function gateway_is_gwgroup_member($name) {
	global $config;

	if (is_array($config['gateways']['gateway_group'])) {
		$groups = $config['gateways']['gateway_group'];
	} else {
		return false;
	}

	$members = array();
	foreach ($groups as $group) {
		if (is_array($group['item'])) {
			foreach ($group['item'] as $item) {
				$elements = explode("|", $item);
				$gwname = $elements[0];
				if ($name == $elements[0]) {
					$members[] = $group['name'];
				}
			}
		}
	}

	return $members;
}
?>
