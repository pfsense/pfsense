<?php
/*
 * gwlb.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008 Bill Marquette, Seth Mos
 * Copyright (c) 2008-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
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

require_once("config.inc");
require_once("rrd.inc");
require_once("ipsec.inc");

/* Returns an array of default values used for dpinger */
function return_dpinger_defaults() {
	return array(
		"latencylow" => "200",
		"latencyhigh" => "500",
		"losslow" => "10",
		"losshigh" => "20",
		"interval" => "500",
		"loss_interval" => "2000",
		"time_period" => "60000",
		"alert_interval" => "1000",
		"data_payload" => "0");
}

function running_dpinger_processes() {
	global $g;

	$pidfiles = glob("{$g['varrun_path']}/dpinger_*.pid");

	$result = array();
	if ($pidfiles === FALSE) {
		return $result;
	}

	foreach ($pidfiles as $pidfile) {
		if (preg_match('/^dpinger_(.+)~([^~]+)~([^~]+)\.pid$/',
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
			killbypid($process['pidfile'], 3);
		} else {
			@unlink($process['pidfile']);
		}
	}
}

function start_dpinger($gateway) {
	global $g;

	if (!isset($gateway['gwifip'])) {
		return (false);
	}

	$dpinger_defaults = return_dpinger_defaults();

	$prefix = "{$g['varrun_path']}/dpinger_{$gateway['name']}~" .
	    "{$gateway['gwifip']}~{$gateway['monitor']}";
	# dpinger socket path should not be longer then uaddr.sun_path
	if (strlen($prefix) > 95) {
		$prefix = "{$g['varrun_path']}/dpinger_{$gateway['name']}~" .
		    substr(md5($gateway['gwifip']),0,8) . "~" .
		    $gateway['monitor'];
	}
	$pidfile = $prefix . ".pid";
	$socket = $prefix . ".sock";
	$alarm_cmd = "{$g['etc_path']}/rc.gateway_alarm";

	$params  = "-S ";			/* Log warnings via syslog */
	$params .= "-r 0 ";			/* Disable unused reporting thread */
	$params .= "-i {$gateway['name']} ";	/* Identifier */
	$params .= "-B {$gateway['gwifip']} ";	/* Bind src address */
	$params .= "-p {$pidfile} ";		/* PID filename */
	$params .= "-u {$socket} ";		/* Status Socket */
	if (!$gateway['action_disable']) {
		$params .= "-C \"{$alarm_cmd}\" ";	/* Command to run on alarm */
	}

	$params .= "-d " .
	    (isset($gateway['data_payload']) && is_numeric($gateway['data_payload'])
	    ? $gateway['data_payload']
	    : $dpinger_defaults['data_payload']
	    ) . " ";

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

	/* Do not try to bind IPv6 where interface is in tentative state */
	if (is_ipaddrv6($gateway['gwifip'])) {
		$err = interface_wait_tentative(get_real_interface(
		    $gateway['interface']));
		if ($err == false) {
			log_error(gettext("Timeout waiting for IPv6 address in tentative state.  dpinger will not run."));
			return (false);
		}
	}

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
		log_error(gettext("No gateways to monitor. dpinger will not run."));
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
				log_error(sprintf(gettext('Removing static route for monitor %1$s and adding a new route through %2$s'), $gateway['monitor'], $gateway['gateway']));
				$route_to = "-host {$gateway['monitor']}";
				if (interface_isppp_type($gateway['friendlyiface'])) {
					route_add_or_change("{$route_to} -iface {$gateway['interface']}");
				} else {
					route_add_or_change("{$route_to} {$gateway['gateway']}");
				}

				pfSense_kill_states("0.0.0.0/0", $gateway['monitor'], $gateway['interface'], "icmp");
			}
		} else if ($gateway['ipprotocol'] == "inet6") { // This is an IPv6 gateway...
			if (is_linklocal($gateway['gateway']) &&
			    get_ll_scope($gateway['gateway']) == '') {
				$gateway['gateway'] .= '%' . $gateway['interface'];
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
				log_error(sprintf(gettext('Removing static route for monitor %1$s and adding a new route through %2$s'), $gateway['monitor'], $gateway['gateway']));
				$route_to = "-host -inet6 {$gateway['monitor']}";
				if (interface_isppp_type($gateway['friendlyiface'])) {
					route_add_or_change("{$route_to} -iface {$gateway['interface']}");
				} else {
					route_add_or_change("{$route_to} {$gateway['gateway']}");
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
			log_error(sprintf(gettext("Error starting gateway monitor for %s"), $gateway['name']));
		}
	}

	return;
}

function get_dpinger_status($gwname, $detailed = false) {
	global $g;

	$running_processes = running_dpinger_processes();

	if (!isset($running_processes[$gwname])) {
		log_error(sprintf(gettext('dpinger: No dpinger session running for gateway %s'), $gwname));
		return false;
	}

	$proc = $running_processes[$gwname];
	unset($running_processes);

	$timeoutcounter = 0;
	while (true) {
		if (!file_exists($proc['socket'])) {
			log_error("dpinger: status socket {$proc['socket']} not found");
			return false;
		}
		$fp = @stream_socket_client("unix://{$proc['socket']}", $errno, $errstr, 10);
		if (!$fp) {
			log_error(sprintf(gettext('dpinger: cannot connect to status socket %1$s - %2$s (%3$s)'), $proc['socket'], $errstr, $errno));
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

		// dpinger returns '<gwname> 0 0 0' when queried directly after it starts.
		// while a latency of 0 and a loss of 0 would be perfect, in a real world it doesnt happen.
		// or does it, anyone? if so we must 'detect' the initialization period differently..
		$ready = $r['latency_stddev'] != '0' || $r['loss'] != '0';

		if ($ready) {
			break;
		} else {
			$timeoutcounter++;
			if ($timeoutcounter > 300) {
				log_error(sprintf(gettext('dpinger: timeout while retrieving status for gateway %s'), $gwname));
				return false;
			}
			usleep(10000);
		}
	}

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

		if ($r['latency_avg'] > $settings['latencyhigh']) {
			if ($detailed) {
				$r['status'] = "highdelay";
			} else {
				$r['status'] = "down";
			}
		} else if ($r['loss'] > $settings['losshigh']) {
			if ($detailed) {
				$r['status'] = "highloss";
			} else {
				$r['status'] = "down";
			}
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
		// If action is disabled for this gateway, then we want a detailed status.
		// That reports "highdelay" or "highloss" rather than just "down".
		// Because reporting the gateway down would be misleading (gateway action is disabled)
		$detailed = $gateways_arr[$gwname]['action_disable'];
		$dpinger_status = get_dpinger_status($gwname, $detailed);
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
		$status[$target]['stddev'] = empty($dpinger_status['latency_stddev']) ? "0ms" : $dpinger_status['latency_stddev'] . "ms";
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
			$status[$target]['delay'] = "";
			$status[$target]['loss'] = "";
			$status[$target]['status'] = "none";
		}

		$status[$target]['monitor_disable'] = true;
	}
	return($status);
}

function return_gateways_status_text($byname = false, $brief = false) {
	$gwstat = return_gateways_status($byname);
	$output = "";
	$widths = array();
	$col_sep = 2;
	if ($brief) {
		$collist = array('status' => "Status");
	} else {
		$collist = array('monitorip' => "Monitor",
				'srcip' => "Source",
				'delay' => "Delay",
				'stddev' => "StdDev",
				'loss' => "Loss",
				'status' => "Status");
	}
	foreach ($gwstat as $gw) {
		foreach ($gw as $gwdidx => $gwdata) {
			if (strlen($gwdata) > $widths[$gwdidx]) {
				$widths[$gwdidx] = strlen($gwdata);
			}
		}
	}

	$output .= str_pad("Name", $widths['name'] + $col_sep, " ", STR_PAD_RIGHT);
	foreach ($collist as $hdrcol => $hdrdesc) {
		if (strlen($hdrdesc) > $widths[$hdrcol]) {
			$widths[$hdrcol] = strlen($hdrdesc);
		}
		$output .= str_pad($hdrdesc, $widths[$hdrcol] + $col_sep, " ", (substr($hdrcol, -2, 2) == "ip") ? STR_PAD_RIGHT : STR_PAD_LEFT);
	}
	$output .= "\n";

	foreach ($gwstat as $idx => $gw) {
		$output .= str_pad($gw['name'], $widths['name'] + $col_sep, " ", STR_PAD_RIGHT);
		foreach (array_keys($collist) as $col) {
			$output .= str_pad($gw[$col], $widths[$col] + $col_sep, " ", (substr($col, -2, 2) == "ip") ? STR_PAD_RIGHT : STR_PAD_LEFT);
		}
		$output .= "\n";
	}

	return $output;
}

function compare_gateway_order_configured($a, $b) {
	/* XXX WAN always has precedence */
	if ($a['friendlyiface'] == "wan") {
		return -1;
	} elseif ($b['friendlyiface'] == "wan") {
		return 1;
	}

	if ($a['attribute'] === $b['attribute']) {
		if ($a['attribute'] === 'system') {
			$res = (($a['name'] < $b['name'])) ? -1 : 1;
			return $res;
		}
		return 0;
	}
	if ($a['attribute'] === 'system' || $b['attribute'] === 'system') {
		$res = (($b['attribute'] === 'system')) ? -1 : 1;
		return $res;
	}
	$res = ($a['attribute'] < $b['attribute']) ? -1 : 1;
	return $res;
}

function order_gateways_as_configured($gateways_arr) {
	uasort($gateways_arr, 'compare_gateway_order_configured');
	return $gateways_arr;
}

/* Return all configured gateways on the system
   $disabled = true - include gateways that are disabled
   $localhost = true - include "Null" entries for localhost IP addresses
   $inactive = true - include gateways on inactive interfaces
   $integer_index = true - index the returned array by integers 0,1,2,... instead of by GW name
*/
function return_gateways_array($disabled = false, $localhost = false, $inactive = false, $integer_index = false) {
	global $config, $g;

	$gateways_arr = array();
	$gateways_arr_temp = array();
	$cgw4 = getcurrentdefaultgatewayip('inet');
	$cgw6 = getcurrentdefaultgatewayip('inet6');
	$found_defaultv4 = 0;
	$found_defaultv6 = 0;

	// Ensure the interface cache is up to date first
	$interfaces = get_interface_arr(true);

	$i = -1;
	/* Process/add all the configured gateways. */
	if (is_array($config['gateways']) && is_array($config['gateways']['gateway_item'])) {
		foreach ($config['gateways']['gateway_item'] as $gateway) {
			if (!is_array($gateway) || empty($gateway)) {
				continue;
			}
			
			/* Increment it here to do not skip items */
			$i++;
			if (isset($gateway['defaultgw'])) {
				unset($gateway['defaultgw']);
			}

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

			if (isset($gateway['action_disable'])) {
				$gateway['action_disable'] = true;
			}

			$gateway['friendlyiface'] = $gateway['interface'];

			/* special treatment for tunnel interfaces */
			if ($gateway['ipprotocol'] == "inet6") {
				$gateway['interface'] = get_real_interface($gateway['interface'], "inet6", false, false);
			} else {
				$gateway['interface'] = get_real_interface($gateway['interface'], "inet", false, false);
			}

			if ($gateway['ipprotocol'] == "inet" &&
					($gateway['gateway'] == $cgw4)) {
				$gateway['isdefaultgw'] = true;
				$found_defaultv4 = 1;
			} else if ($gateway['ipprotocol'] == "inet6" &&
					($gateway['gateway'] == $cgw6)) {
				$gateway['isdefaultgw'] = true;
				$found_defaultv6 = 1;
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

	//Sort the array by GW name before moving on.
	ksort($gateways_arr, SORT_STRING | SORT_FLAG_CASE);

	/* Loop through all interfaces with a gateway and add it to a array */
	if ($disabled == false) {
		$iflist = get_configured_interface_with_descr();
	} else {
		$iflist = get_configured_interface_with_descr(true);
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

		$ctype = "";
		switch ($ifcfg['ipaddr']) {
			case "dhcp":
			case "pppoe":
			case "l2tp":
			case "pptp":
			case "ppp":
				$ctype = strtoupper($ifcfg['ipaddr']);
				break;
			default:
				$tunnelif = substr($ifcfg['if'], 0, 3);
				if (substr($ifcfg['if'], 0, 4) == "ovpn") {
					switch (substr($ifcfg['if'], 4, 1)) {
						case "c":
							$ovpntype = "openvpn-client";
							break;
						case "s":
							$ovpntype = "openvpn-server";
							break;
						default:
							// unknown ovpn type
							continue 3;
					}
					$ovpnid = substr($ifcfg['if'], 5);
					if (is_array($config['openvpn'][$ovpntype])) {
						foreach ($config['openvpn'][$ovpntype] as & $ovpnconf) {
							if ($ovpnconf['vpnid'] == $ovpnid) {
								// skip IPv6-only interfaces
								if ($ovpnconf['create_gw'] == "v6only") {
									continue 3;
								}
								// skip tap interfaces
								if ($ovpnconf['dev_mode'] == "tap") {
									continue 3;
								}
							}
						}
					}
					$ctype = "VPNv4";
				} elseif (substr($ifcfg['if'], 0, 5) == "ipsec") {
					$ikeid = substr($ifcfg['if'], 5);
					if (is_array($config['ipsec']) && is_array($config['ipsec']['phase1']) && is_array($config['ipsec']['phase2'])) {
						foreach ($config['ipsec']['phase1'] as $ph1ent) {
							if ($ph1ent['disabled']) {
								continue;
							}
							$vtisubnet_spec = ipsec_vti($ph1ent, true);
							// Skip non-VTI tunnels
							if (!$vtisubnet_spec || !is_array($vtisubnet_spec)) {
								continue;
							}
							if (!isset($ph1ent['mobile']) && ($keyexchange == 'ikev1' || isset($ph1ent['splitconn']))) {
								foreach ($vtisubnet_spec as $idx => $vtisub) {
									if ($ifcfg['if'] == "ipsec{$ph1ent['ikeid']}00{$idx}") {
										// If this specific VTI remote is v4, then we can make a v4 gw
										if (is_ipaddrv4($vtisub['right'])) {
											$ctype = "VTIv4";
										}
									}
								}
							} else {
								if ($ifcfg['if'] == "ipsec{$ph1ent['ikeid']}000") {
									// If any of the VTI remotes are v4, then we can make a v4 gw
									foreach ($vtisubnet_spec as $vtisub) {
										if (is_ipaddrv4($vtisub['right'])) {
											$ctype = "VTIv4";
										}
									}
								}
							}
						}
						if (empty($ctype)) {
							continue 2;
						}
					}
				} elseif ($tunnelif == "gif" || $tunnelif == "gre") {
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
			$gateway['isdefaultgw'] = true;
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
					switch (substr($ifcfg['if'], 4, 1)) {
						case "c":
							$ovpntype = "openvpn-client";
							break;
						case "s":
							$ovpntype = "openvpn-server";
							break;
						default:
							// unknown ovpn type
							continue 3;
					}
					$ovpnid = substr($ifcfg['if'], 5);
					if (is_array($config['openvpn'][$ovpntype])) {
						foreach ($config['openvpn'][$ovpntype] as & $ovpnconf) {
							if ($ovpnconf['vpnid'] == $ovpnid) {
								// skip IPv4-only interfaces
								if ($ovpnconf['create_gw'] == "v4only") {
									continue 3;
								}
								// skip tap interfaces
								if ($ovpnconf['dev_mode'] == "tap") {
									continue 3;
								}
							}
						}
					}
					$ctype = "VPNv6";
				} elseif (substr($ifcfg['if'], 0, 5) == "ipsec") {
					$ikeid = substr($ifcfg['if'], 5);
					if (is_array($config['ipsec']) && is_array($config['ipsec']['phase1']) && is_array($config['ipsec']['phase2'])) {
						foreach ($config['ipsec']['phase1'] as $ph1ent) {
							if ($ph1ent['disabled']) {
								continue;
							}
							$vtisubnet_spec = ipsec_vti($ph1ent, true);
							// Skip non-VTI tunnels
							if (!$vtisubnet_spec || !is_array($vtisubnet_spec)) {
								continue;
							}
							if (!isset($ph1ent['mobile']) && ($keyexchange == 'ikev1' || isset($ph1ent['splitconn']))) {
								foreach ($vtisubnet_spec as $idx => $vtisub) {
									if ($ifcfg['if'] == "ipsec{$ph1ent['ikeid']}00{$idx}") {
										// If this specific VTI remote is v6, then we can make a v6 gw
										if (is_ipaddrv6($vtisub['right'])) {
											$ctype = "VTIv6";
										}
									}
								}
							} else {
								if ($ifcfg['if'] == "ipsec{$ph1ent['ikeid']}000") {
									// If any of the VTI remotes are v6, then we can make a v6 gw
									foreach ($vtisubnet_spec as $vtisub) {
										if (is_ipaddrv6($vtisub['right'])) {
											$ctype = "VTIv6";
										}
									}
								}
							}
						}
						if (empty($ctype)) {
							continue 2;
						}
					}
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
			$gateway['isdefaultgw'] = true;
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
					$gateway['isdefaultgw'] = true;
					$found_defaultv4 = 1;
				}
			}
			else if (($gateway['friendlyiface'] == "wan") && ($found_defaultv6 == 0) && ($gateway['ipprotocol'] == "inet6")) {
				if (file_exists("{$g['tmp_path']}/{$gateway['interface']}_defaultgwv6")) {
					$gateway['isdefaultgw'] = true;
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
		$gwlo4['attribute'] = "system";
		$gwlo6 = array();
		$gwlo6['name'] = "Null6";
		$gwlo6['interface'] = "lo0";
		$gwlo6['ipprotocol'] = "inet6";
		$gwlo6['gateway'] = "::1";
		$gwlo6['attribute'] = "system";
		$gateways_arr['Null4'] = $gwlo4;
		$gateways_arr['Null6'] = $gwlo6;
	}

	if ($integer_index) {
		$gateways_arr = array_values($gateways_arr);
	}

	if ($found_defaultv4 != 1 && is_ipaddr($cgw4)) {
		foreach($gateways_arr as &$gw) {
			if ($gw['gateway'] == $cgw4) {
				$gw['isdefaultgw'] = true;
			}
		}
	}
	if ($found_defaultv6 != 1 && is_ipaddr($cgw6)) {
		foreach($gateways_arr as &$gw) {
			if ($gw['gateway'] == $cgw6) {
				$gw['isdefaultgw'] = true;
			}
		}
	}
	return order_gateways_as_configured($gateways_arr);
}

function fixup_default_gateway($ipprotocol, $gateways_status, $gateways_arr) {
	global $config, $g;
	/*
	 * NOTE: The code below is meant to replace the default gateway when it goes down.
	 *	This facilitates services running on pfSense itself and are not handled by a PBR to continue working.
	 */
	$set_dfltgwname = '';

	if ($ipprotocol == 'inet') {
		$gwdefault = $config['gateways']['defaultgw4'];
	} else {
		$gwdefault = $config['gateways']['defaultgw6'];
	}
	if ($gwdefault == "-") {
		// 'none', dont set the default gateway, useful if routes are managed by frr/bgp/ospf or similar
		return;
	}
	if (isset($gateways_arr[$gwdefault])) {
		// the configured gateway is a regular one. (not a gwgroup) use it as is..
		$set_dfltgwname = $gwdefault;
	} elseif (empty($gwdefault)) {
		// 'automatic' mode, pick the first one thats 'up' or 'unmonitored' which is always considered up
		$gateways_arr = order_gateways_as_configured($gateways_arr);
		$fallback = "";
		foreach($gateways_arr as $gwname => $gwsttng) {
			if ($gwsttng['ipprotocol'] != $ipprotocol) {
				continue;
			}
			
			if ((isset($gwsttng['monitor_disable']) || isset($gwsttng['action_disable']) || $gateways_status[$gwname]['status'] == "none")) {
				$set_dfltgwname = $gwname;
				break;
			}
			if (empty($fallback) && $gwsttng['interface'] != 'lo0') {
				$fallback = $gwname;
			}
		}
		if (empty($set_dfltgwname)) {
			log_error(sprintf("Gateway, none 'available' for %s, use the first one configured. '%s'", $ipprotocol, $fallback));
			$set_dfltgwname = $fallback;
		}
	} else {
		// a gwgroup is selected
		// find the best available gateway given options available..
		$gwg_members = array();
		$viplist = get_configured_vip_list();
		if (is_array($config['gateways']['gateway_group'])) {
			foreach ($config['gateways']['gateway_group'] as $group) {
				if ($group['name'] == $gwdefault) {
					// finds the gw members of the best available tier for this group.
					$gwg_members = get_gwgroup_members_inner($group, $gateways_status, $gateways_arr, $viplist);
				}
			}
		}

		if (count($gwg_members) > 0) {
			$currentdefaultgwip = getcurrentdefaultgatewayip($ipprotocol);
			$found_current = false;
			foreach($gwg_members as $gwgroupitem) {
				if (!empty($currentdefaultgwip) && ($gwgroupitem['gwip'] == $currentdefaultgwip)) {
					$set_dfltgwname = $gwgroupitem['gw'];
					$found_current = true;
					if (isset($config['system']['gw-debug'])) {
						log_error("Keep current gateway, its already part of the group members.");
					}
					break;
				}
			}
			if (!$found_current) {
				$set_dfltgwname = $gwg_members[0]['gw'];
				log_error(sprintf("Gateway, switch to: %s", $set_dfltgwname));
			}
		} else {
			log_error("Gateway, NONE AVAILABLE");
		}
	}
	if (!empty($set_dfltgwname) && isset($gateways_arr[$set_dfltgwname])) {
		setdefaultgateway($gateways_arr[$set_dfltgwname]);
	}
}

function getcurrentdefaultgatewayip($ipprotocol) {
	return trim(exec("/sbin/route -n get -{$ipprotocol} default 2>/dev/null | /usr/bin/awk '/gateway:/ {print $2}'"), " \n");
}

function setdefaultgateway($gw) {
	global $g, $config;
	if (isset($config['system']['route-debug'])) {
		file_put_contents("/dev/console", "\n[".getmypid()."] SET DEF GW: {$gw['name']}");
	}
	$ipprotocol = $gw['ipprotocol'];
	if ($gw['gateway'] == "dynamic") {
		if ($ipprotocol == 'inet') {
			$gw['gateway'] = get_interface_gateway($gw['friendlyiface']);
		} else {
			$gw['gateway'] = get_interface_gateway_v6($$gw['friendlyiface']);
		}
	}
	if ($ipprotocol == 'inet6' && !is_ipaddrv6($gw['gateway'])) {
		return;
	}
	if ($ipprotocol == 'inet' && !is_ipaddrv4($gw['gateway'])) {
		return;
	}
	if ($ipprotocol == 'inet6') {
		if (is_linklocal($gw['gateway']) && get_ll_scope($gw['gateway']) == '') {
			$gw['gateway'] .= "%" . $gw['interface'];
		}
	}
	$currentdefaultgwip = getcurrentdefaultgatewayip($ipprotocol);
	if ($currentdefaultgwip != $gw['gateway']) {
		log_error("Default gateway setting {$gw['descr']} as default.");

		if ($ipprotocol == 'inet') {
			array_map('unlink', glob("{$g['tmp_path']}/*_defaultgw", GLOB_BRACE));
		} else {
			array_map('unlink', glob("{$g['tmp_path']}/*_defaultgwv6", GLOB_BRACE));
		}
		$defaultif = get_real_interface($gw['interface']);
		if ($defaultif) {
			@file_put_contents("{$g['tmp_path']}/{$defaultif}_defaultgw", $gw['gateway']);
		}

		if (isset($gw["nonlocalgateway"])) {
			if (is_ipaddr($gw['gateway']) && !empty($gw['interface'])) {
				route_add_or_change("-{$ipprotocol} {$gw['gateway']} -iface {$gw['interface']}");
			}
		}
		if (isset($config['system']['route-debug'])) {
			file_put_contents("/dev/console", "\n[".getmypid()."] SET DEF GW: {$gw['name']} ({$gw['gateway']})");
		}
		route_add_or_change("-{$ipprotocol} default {$gw['gateway']}");
		return true;
	}
}

function get_gwgroup_members_inner($group, $gateways_status, $gateways_arr, $viplist){
	$result = array();
	/* create array with group gateways members separated by tier */
	$tiers = array();
	$backupplan = array();
	$gwvip_arr = array();
	foreach ($group['item'] as $item) {
		list($gwname, $tier, $vipname) = explode("|", $item);

		if (is_ipaddr($viplist[$vipname])) {
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
				$msg = sprintf(gettext('MONITOR: %1$s is down, omitting from routing group %2$s'), $gwname, $group['name']);
				$gwdown = true;
			} else if (stristr($status['status'], "loss") && strstr($group['trigger'], "loss")) {
				/* packet loss */
				$msg = sprintf(gettext('MONITOR: %1$s has packet loss, omitting from routing group %2$s'), $gwname, $group['name']);
				$gwdown = true;
			} else if (stristr($status['status'], "delay") && strstr($group['trigger'] , "latency")) {
				/* high latency */
				$msg = sprintf(gettext('MONITOR: %1$s has high latency, omitting from routing group %2$s'), $gwname, $group['name']);
				$gwdown = true;
			}
			$statuschanged = false;
			$pluginparams = array();
			$pluginparams['type'] = 'gateway';
			$pluginparams['name'] = $gwname;
			if ($gwdown == true) {
				if (!file_exists("/tmp/.down.{$gwname}")) {
					@touch("/tmp/.down.{$gwname}");
					$msg .= "\n".implode("|", $status);
					$pluginparams['event'] = 'gateway.down';
					$statuschanged = true;
				}
			} else {
				/* Online add member */
				if (!is_array($tiers[$tier])) {
					$tiers[$tier] = array();
				}
				$tiers[$tier][] = $gwname;
				if (unlink_if_exists("/tmp/.down.{$gwname}")) {
					$msg = sprintf(getmypid () . gettext('MONITOR: %1$s is available now, adding to routing group %2$s'), $gwname, $group['name']);
					$msg .= "\n".implode("|", $status);
					$pluginparams['event'] = 'gateway.up';
					$statuschanged = true;
				}
			}
			if ($statuschanged) {
				log_error($msg);
				notify_via_smtp($msg);
				if (isset($gateways_arr[$gwname]['interface'])) {
					$pluginparams['interface'] = $gateways_arr[$gwname]['interface'];
				}
				pkg_call_plugins('plugin_gateway', $pluginparams);
			}
		} else if (isset($gateways_arr[$gwname]['monitor_disable']) || isset($gateways_arr[$gwname]['action_disable'])) {
			$tiers[$tier][] = $gwname;
		}
	}
	$tiers_count = count($tiers);
	if ($tiers_count == 0) {
		/* Oh dear, we have no members! Engage Plan B */
		if (isset($config['system']['gw-debug']) && (!platform_booting())) {
			$msg = sprintf(gettext('Gateways status could not be determined, considering all as up/active. (Group: %s)'), $group['name']);
			log_error($msg);
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
					$result['ipprotocol'] = $gateway['ipprotocol'];
					if (is_ipaddr($gatewayip)) {
						$groupmember = array();
						$groupmember['gw'] = $member;
						$groupmember['int'] = $int;
						$groupmember['gwip'] = $gatewayip;
						$groupmember['weight'] = isset($gateway['weight']) ? $gateway['weight'] : 1;
						if (is_array($gwvip_arr[$group['name']]) && !empty($gwvip_arr[$group['name']][$member])) {
							$groupmember['vip'] = $gwvip_arr[$group['name']][$member];
						}
						$result[] = $groupmember;
					}
				}
			}
		}
		/* we should have the 1st available tier now, exit stage left */
		if (count($result) > 0) {
			break;
		} else {
			log_error(sprintf(gettext('GATEWAYS: Group %1$s did not have any gateways up on tier %2$s!'), $group['name'], $tieridx));
		}
	}
	// Add description field last to not influence the count() above
	$result['descr'] = $group['descr'];
	return $result;
}

function get_gwgroup_members($groupname) {
	global $config;
	$gateways_status = return_gateways_status(true);
	$gateways_arr = return_gateways_array();
	$viplist = get_configured_vip_list();
	foreach ($config['gateways']['gateway_group'] as $group) {
		if ($group['name'] == $groupname) {
			return get_gwgroup_members_inner($group, $gateways_status, $gateways_arr, $viplist);
		}
	}
	return array();
}

/*
 * Return an array with all gateway groups with name as key
 * All gateway groups will be processed before returning the array.
 */
function return_gateway_groups_array($fixup = false) {
	global $config;

	/* fetch the current gateways status */
	$gateways_status = return_gateways_status(true);
	$gateways_arr = return_gateways_array();
	$gateway_groups_array = array();
	if ($fixup == true) {
		$gw4 = lookup_gateway_or_group_by_name($config['gateways']['defaultgw4']);
		$gw6 = lookup_gateway_or_group_by_name($config['gateways']['defaultgw6']);
		if ($gw4 && $gw4['type'] == 'gatewaygroup') {
			fixup_default_gateway("inet", $gateways_status, $gateways_arr);
		}
		if ($gw6 && $gw6['type'] == 'gatewaygroup') {
			fixup_default_gateway("inet6", $gateways_status, $gateways_arr);
		}
	}
	if (is_array($config['gateways']['gateway_group'])) {
		$viplist = get_configured_vip_list();
		foreach ($config['gateways']['gateway_group'] as $group) {
			$gateway_groups_array[$group['name']] = get_gwgroup_members_inner($group, $gateways_status, $gateways_arr, $viplist);
		}
	}

	return ($gateway_groups_array);
}

/* Update DHCP WAN Interface ip address in gateway group item */
function dhclient_update_gateway_groups_defaultroute($interface = "wan") {
	global $config;

	if (is_array($config['gateways']['gateway_item'])) {
		foreach ($config['gateways']['gateway_item'] as & $gw) {
			if ($gw['interface'] != $interface) {
				continue;
			}

			$current_gw = get_interface_gateway($interface);
			if ($gw['gateway'] <> $current_gw) {
				$gw['gateway'] = $current_gw;
				$changed = true;
			}
		}
	}

	if ($changed && $current_gw) {
		write_config(sprintf(gettext(
		    'Updating gateway group gateway for %1$s - new gateway is %2$s'),
		    $interface, $current_gw));
	}
}

function lookup_gateway_or_group_by_name($gwname) {
	global $config;

	$gateways_arr = return_gateways_array();
	foreach ($gateways_arr as $gw) {
		if ($gw['name'] == $gwname) {
			$gw['type'] = 'gateway';
			return $gw;
		}
	}

	if (is_array($config['gateways']['gateway_group'])) {
		foreach ($config['gateways']['gateway_group'] as $gwg) {
			if ($gwg['name'] == $gwname) {
				$gwg['type'] = 'gatewaygroup';
				return $gwg;
			}
		}
	}

	return false;
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

function get_root_interface($interface) {
	if (substr($interface, 0, 4) == '_vip') {
		$interface = get_configured_vip_interface($interface);
		if (substr($interface, 0, 4) == '_vip') {
			$interface = get_configured_vip_interface($interface);
		}
	}
	return $interface;
}

function get_interface_gateway($interface, &$dynamic = false) {
	global $config, $g;
	
	$interface = get_root_interface($interface);

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

	$interface = get_root_interface($interface);

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
function interface_gateway_group_member($interface, $gwgroup_name = "") {
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
				if ($interface == $gateways_arr[$gwname]['interface'] &&
				    (empty($gwgroup_name) || $gwgroup_name == $group['name'])) {
					unset($gateways_arr);
					return true;
				}
			}
		}
	}
	unset($gateways_arr);

	return false;
}

function gateway_is_gwgroup_member($name, $detail=false) {
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
				list($gwname, $tier, $vipname) = explode("|", $item);
				if ($name == $gwname) {
					if ($detail) {
						$newitem = array();
						$newitem['name'] = $group['name'];
						$newitem['tier'] = $tier;
						$newitem['vipname'] = $vipname;
						$members[] = $newitem;
					} else {
						$members[] = $group['name'];
					}
				}
			}
		}
	}

	return $members;
}
/*
  Check the proposed gateway settings to see if they are valid.
  $gateway_settings - the proposed array of proposed gateway settings
  $id - the index of the gateway proposed to be modified (otherwise "" if adding a new gateway)
  $parent_ip - the IP (v4 or v6) address about to be set on the corresponding interface (if any)
  $parent_sn - the subnet about to be set on the corresponding interface (if any)
  (Note: the above 2 parameters allow gateway parameters to be validated concurrently with saving
   an interface, before the new interface parameters are actually saved in the config.)
  Return completed $input_errors array if there is any problem.
  Otherwise return an empty $input_errors array
*/
function validate_gateway($gateway_settings, $id = "", $parent_ip = "", $parent_sn = "") {
	global $config;

	$a_gateways = return_gateways_array(true, false, true, true);
	$input_errors = array();

	/* input validation */
	$reqdfields = explode(" ", "name interface");
	$reqdfieldsn = array(gettext("Name"), gettext("Interface"));

	do_input_validation($gateway_settings, $reqdfields, $reqdfieldsn, $input_errors);

	if (!isset($gateway_settings['name'])) {
		$input_errors[] = "A valid gateway name must be specified.";
	}
	if (!is_validaliasname($gateway_settings['name'])) {
		$input_errors[] = invalidaliasnamemsg($gateway_settings['name'], gettext("gateway"));
	} else if (isset($gateway_settings['disabled'])) {
		// We have a valid gateway name that the user wants to mark as disabled.
		// Check if the gateway name is used in any gateway group.
		if (is_array($config['gateways']['gateway_group'])) {
			foreach ($config['gateways']['gateway_group'] as $group) {
				foreach ($group['item'] as $item) {
					$items = explode("|", $item);
					if ($items[0] == $gateway_settings['name']) {
						$input_errors[] = sprintf(gettext('Gateway "%1$s" cannot be disabled because it is in use on Gateway Group "%2$s"'), $gateway_settings['name'], $group['name']);
					}
				}
			}
		}

		// Check if the gateway name is used in any enabled Static Route.
		if (is_array($config['staticroutes']['route'])) {
			foreach ($config['staticroutes']['route'] as $route) {
				if ($route['gateway'] == $gateway_settings['name']) {
					if (!isset($route['disabled'])) {
						// There is a static route that uses this gateway and is enabled (not disabled).
						$input_errors[] = sprintf(gettext('Gateway "%1$s" cannot be disabled because it is in use on Static Route "%2$s"'), $gateway_settings['name'], $route['network']);
					}
				}
			}
		}
	}
	/* skip system gateways which have been automatically added */
	if (($gateway_settings['gateway'] && (!is_ipaddr($gateway_settings['gateway'])) && ($gateway_settings['attribute'] !== "system")) && ($gateway_settings['gateway'] != "dynamic")) {
		$input_errors[] = gettext("A valid gateway IP address must be specified.");
	}

	if ($gateway_settings['gateway'] && is_ipaddr($gateway_settings['gateway'])) {
		if (is_ipaddrv4($gateway_settings['gateway'])) {
			if ($parent_ip == '') {
				$parent_ip = get_interface_ip($gateway_settings['interface']);
				$parent_sn = get_interface_subnet($gateway_settings['interface']);
			}
			if (empty($parent_ip) || empty($parent_sn)) {
				$input_errors[] = gettext("Cannot add IPv4 Gateway Address because no IPv4 address could be found on the interface.");
			} elseif (!isset($gateway_settings["nonlocalgateway"])) {
				$subnets = array(gen_subnet($parent_ip, $parent_sn) . "/" . $parent_sn);
				$vips = link_interface_to_vips($gateway_settings['interface']);
				if (is_array($vips)) {
					foreach ($vips as $vip) {
						if (!is_ipaddrv4($vip['subnet'])) {
							continue;
						}
						$subnets[] = gen_subnet($vip['subnet'], $vip['subnet_bits']) . "/" . $vip['subnet_bits'];
					}
				}

				$found = false;
				foreach ($subnets as $subnet) {
					if (ip_in_subnet($gateway_settings['gateway'], $subnet)) {
						$found = true;
						break;
					}
				}

				if ($found === false) {
					$input_errors[] = sprintf(gettext("The gateway address %s does not lie within one of the chosen interface's subnets."), $gateway_settings['gateway']);
				}
			}
		} else if (is_ipaddrv6($gateway_settings['gateway'])) {
			/* do not do a subnet match on a link local address, it's valid */
			if (!is_linklocal($gateway_settings['gateway'])) {
				if ($parent_ip == '') {
					$parent_ip = get_interface_ipv6($gateway_settings['interface']);
					$parent_sn = get_interface_subnetv6($gateway_settings['interface']);
				}
				if (empty($parent_ip) || empty($parent_sn)) {
					$input_errors[] = gettext("Cannot add IPv6 Gateway Address because no IPv6 address could be found on the interface.");
				} elseif (!isset($gateway_settings["nonlocalgateway"])) {
					$subnets = array(gen_subnetv6($parent_ip, $parent_sn) . "/" . $parent_sn);
					$vips = link_interface_to_vips($gateway_settings['interface']);
					if (is_array($vips)) {
						foreach ($vips as $vip) {
							if (!is_ipaddrv6($vip['subnet'])) {
								continue;
							}
							$subnets[] = gen_subnetv6($vip['subnet'], $vip['subnet_bits']) . "/" . $vip['subnet_bits'];
						}
					}

					$found = false;
					foreach ($subnets as $subnet) {
						if (ip_in_subnet($gateway_settings['gateway'], $subnet)) {
							$found = true;
							break;
						}
					}

					if ($found === false) {
						$input_errors[] = sprintf(gettext("The gateway address %s does not lie within one of the chosen interface's subnets."), $gateway_settings['gateway']);
					}
				}
			}
		}

		if (!empty($config['interfaces'][$gateway_settings['interface']]['ipaddr'])) {
			if (is_ipaddr($config['interfaces'][$gateway_settings['interface']]['ipaddr']) && (empty($gateway_settings['gateway']) || $gateway_settings['gateway'] == "dynamic")) {
				$input_errors[] = gettext("Dynamic gateway values cannot be specified for interfaces with a static IPv4 configuration.");
			}
		}
		if (!empty($config['interfaces'][$gateway_settings['interface']]['ipaddrv6'])) {
			if (is_ipaddr($config['interfaces'][$gateway_settings['interface']]['ipaddrv6']) && (empty($gateway_settings['gateway']) || $gateway_settings['gateway'] == "dynamic")) {
				$input_errors[] = gettext("Dynamic gateway values cannot be specified for interfaces with a static IPv6 configuration.");
			}
		}
	}
	if (($gateway_settings['monitor'] != "") && ($gateway_settings['monitor'] != "dynamic")) {
		validateipaddr($gateway_settings['monitor'], IPV4V6, "Monitor IP", $input_errors, false);
	}
	if (isset($gateway_settings['data_payload']) && is_numeric($gateway_settings['data_payload']) && $gateway_settings['data_payload'] < 0) {
		$input_errors[] = gettext("A valid data payload must be specified.");
	}
	/* only allow correct IPv4 and IPv6 gateway addresses */
	if (($gateway_settings['gateway'] <> "") && is_ipaddr($gateway_settings['gateway']) && $gateway_settings['gateway'] != "dynamic") {
		if (is_ipaddrv6($gateway_settings['gateway']) && ($gateway_settings['ipprotocol'] == "inet")) {
			$input_errors[] = sprintf(gettext("The IPv6 gateway address '%s' can not be used as a IPv4 gateway."), $gateway_settings['gateway']);
		}
		if (is_ipaddrv4($gateway_settings['gateway']) && ($gateway_settings['ipprotocol'] == "inet6")) {
			$input_errors[] = sprintf(gettext("The IPv4 gateway address '%s' can not be used as a IPv6 gateway."), $gateway_settings['gateway']);
		}
	}
	/* only allow correct IPv4 and IPv6 monitor addresses */
	if (($gateway_settings['monitor'] <> "") && is_ipaddr($gateway_settings['monitor']) && $gateway_settings['monitor'] != "dynamic") {
		if (is_ipaddrv6($gateway_settings['monitor']) && ($gateway_settings['ipprotocol'] == "inet")) {
			$input_errors[] = sprintf(gettext("The IPv6 monitor address '%s' can not be used on a IPv4 gateway."), $gateway_settings['monitor']);
		}
		if (is_ipaddrv4($gateway_settings['monitor']) && ($gateway_settings['ipprotocol'] == "inet6")) {
			$input_errors[] = sprintf(gettext("The IPv4 monitor address '%s' can not be used on a IPv6 gateway."), $gateway_settings['monitor']);
		}
	}

	if (isset($gateway_settings['name'])) {
		/* check for overlaps */
		foreach ($a_gateways as $gateway) {
			if (isset($id) && ($a_gateways[$id]) && ($a_gateways[$id] === $gateway)) {
				if ($gateway['name'] != $gateway_settings['name']) {
					$input_errors[] = gettext("Changing name on a gateway is not allowed.");
				}
				continue;
			}
			if ($gateway_settings['name'] <> "") {
				if (($gateway['name'] <> "") && ($gateway_settings['name'] == $gateway['name']) && ($gateway['attribute'] !== "system")) {
					$input_errors[] = sprintf(gettext('The gateway name "%s" already exists.'), $gateway_settings['name']);
					break;
				}
			}
			if (is_ipaddr($gateway_settings['gateway'])) {
				if (($gateway['gateway'] <> "") && ($gateway_settings['gateway'] == $gateway['gateway']) && ($gateway['attribute'] !== "system")) {
					$input_errors[] = sprintf(gettext('The gateway IP address "%s" already exists.'), $gateway_settings['gateway']);
					break;
				}
			}
			if (is_ipaddr($gateway_settings['monitor'])) {
				if (($gateway['monitor'] <> "") && ($gateway_settings['monitor'] == $gateway['monitor']) && ($gateway['attribute'] !== "system")) {
					$input_errors[] = sprintf(gettext('The monitor IP address "%s" is already in use. A different monitor IP must be chosen.'), $gateway_settings['monitor']);
					break;
				}
			}
		}
	}

	/* input validation of dpinger advanced parameters */

	$dpinger_default = return_dpinger_defaults();
	$latencylow = $dpinger_default['latencylow'];
	if ($gateway_settings['latencylow']) {
		if (!is_numeric($gateway_settings['latencylow'])) {
			$input_errors[] = gettext("The low latency threshold needs to be a numeric value.");
		} else if ($gateway_settings['latencylow'] < 1) {
			$input_errors[] = gettext("The low latency threshold needs to be positive.");
		} else {
			$latencylow = $gateway_settings['latencylow'];
		}
	}

	$latencyhigh = $dpinger_default['latencyhigh'];
	if ($gateway_settings['latencyhigh']) {
		if (!is_numeric($gateway_settings['latencyhigh'])) {
			$input_errors[] = gettext("The high latency threshold needs to be a numeric value.");
		} else if ($gateway_settings['latencyhigh'] < 1) {
			$input_errors[] = gettext("The high latency threshold needs to be positive.");
		} else {
			$latencyhigh = $gateway_settings['latencyhigh'];
		}
	}

	$losslow = $dpinger_default['losslow'];
	if ($gateway_settings['losslow']) {
		if (!is_numeric($gateway_settings['losslow'])) {
			$input_errors[] = gettext("The low Packet Loss threshold needs to be a numeric value.");
		} else if ($gateway_settings['losslow'] < 1) {
			$input_errors[] = gettext("The low Packet Loss threshold needs to be positive.");
		} else if ($gateway_settings['losslow'] >= 100) {
			$input_errors[] = gettext("The low Packet Loss threshold needs to be less than 100.");
		} else {
			$losslow = $gateway_settings['losslow'];
		}
	}

	$losshigh = $dpinger_default['losshigh'];
	if ($gateway_settings['losshigh']) {
		if (!is_numeric($gateway_settings['losshigh'])) {
			$input_errors[] = gettext("The high Packet Loss threshold needs to be a numeric value.");
		} else if ($gateway_settings['losshigh'] < 1) {
			$input_errors[] = gettext("The high Packet Loss threshold needs to be positive.");
		} else if ($gateway_settings['losshigh'] > 100) {
			$input_errors[] = gettext("The high Packet Loss threshold needs to be 100 or less.");
		} else {
			$losshigh = $gateway_settings['losshigh'];
		}
	}

	$time_period = $dpinger_default['time_period'];
	if ($gateway_settings['time_period']) {
		if (!is_numeric($gateway_settings['time_period'])) {
			$input_errors[] = gettext("The time period over which results are averaged needs to be a numeric value.");
		} else if ($gateway_settings['time_period'] < 1) {
			$input_errors[] = gettext("The time period over which results are averaged needs to be positive.");
		} else {
			$time_period = $gateway_settings['time_period'];
		}
	}

	$interval = $dpinger_default['interval'];
	if ($gateway_settings['interval']) {
		if (!is_numeric($gateway_settings['interval'])) {
			$input_errors[] = gettext("The probe interval needs to be a numeric value.");
		} else if ($gateway_settings['interval'] < 1) {
			$input_errors[] = gettext("The probe interval needs to be positive.");
		} else {
			$interval = $gateway_settings['interval'];
		}
	}

	$loss_interval = $dpinger_default['loss_interval'];
	if ($gateway_settings['loss_interval']) {
		if (!is_numeric($gateway_settings['loss_interval'])) {
			$input_errors[] = gettext("The loss interval needs to be a numeric value.");
		} else if ($gateway_settings['loss_interval'] < 1) {
			$input_errors[] = gettext("The loss interval setting needs to be positive.");
		} else {
			$loss_interval = $gateway_settings['loss_interval'];
		}
	}

	$alert_interval = $dpinger_default['alert_interval'];
	if ($gateway_settings['alert_interval']) {
		if (!is_numeric($gateway_settings['alert_interval'])) {
			$input_errors[] = gettext("The alert interval needs to be a numeric value.");
		} else if ($gateway_settings['alert_interval'] < 1) {
			$input_errors[] = gettext("The alert interval setting needs to be positive.");
		} else {
			$alert_interval = $gateway_settings['alert_interval'];
		}
	}

	if ($latencylow >= $latencyhigh) {
		$input_errors[] = gettext(
		    "The high latency threshold needs to be greater than the low latency threshold");
	}

	if ($losslow >= $losshigh) {
		$input_errors[] = gettext(
		    "The high packet loss threshold needs to be higher than the low packet loss threshold");
	}

	// If the loss interval is less than latencyhigh, then high latency could never be recorded
	// because those high latency packets would be considered as lost. So do not allow that.
	if ($latencyhigh > $loss_interval) {
		$input_errors[] = gettext("The loss interval needs to be greater than or equal to the high latency threshold.");
	}

	// Ensure that the time period is greater than 2 times the probe interval plus the loss interval.
	if (($interval * 2 + $loss_interval) >= $time_period) {
		$input_errors[] = gettext("The time period needs to be greater than twice the probe interval plus the loss interval.");
	}

	// There is no point recalculating the average latency and loss more often than the probe interval.
	// So the alert interval needs to be >= probe interval.
	if ($interval > $alert_interval) {
		$input_errors[] = gettext("The alert interval needs to be greater than or equal to the probe interval.");
	}

	return $input_errors;
}

// Save gateway settings.
// $gateway_settings - the array of gateway setting parameters
// $realid - the index of the gateway to be modified (otherwise "" if adding a new gateway)

// This function is responsible to:
//   Setup the gateway parameter structure from the gateway settings input parameter
//   Save the structure into the config
//   Remove any run-time settings from gateway parameters that are changed (e.g. remove routes to addresses that are changing)

// A subsequent "apply" step will implement the added/changed gateway.

function save_gateway($gateway_settings, $realid = "") {
	global $config;

	init_config_arr(array('gateways', 'gateway_item'));
	$a_gateway_item = &$config['gateways']['gateway_item'];
	$reloadif = "";
	$gateway = array();

	if (empty($gateway_settings['interface'])) {
		$gateway['interface'] = $gateway_settings['friendlyiface'];
	} else {
		$gateway['interface'] = $gateway_settings['interface'];
	}
	if (is_ipaddr($gateway_settings['gateway'])) {
		$gateway['gateway'] = $gateway_settings['gateway'];
	} else {
		$gateway['gateway'] = "dynamic";
	}
	$gateway['name'] = $gateway_settings['name'];
	$gateway['weight'] = $gateway_settings['weight'];
	$gateway['ipprotocol'] = $gateway_settings['ipprotocol'];
	if ($gateway_settings['interval']) {
		$gateway['interval'] = $gateway_settings['interval'];
	}

	if ($gateway_settings['time_period']) {
		$gateway['time_period'] = $gateway_settings['time_period'];
	}
	if ($gateway_settings['alert_interval']) {
		$gateway['alert_interval'] = $gateway_settings['alert_interval'];
	}

	$gateway['descr'] = $gateway_settings['descr'];
	if ($gateway_settings['monitor_disable'] == "yes") {
		$gateway['monitor_disable'] = true;
	}
	if ($gateway_settings['action_disable'] == "yes") {
		$gateway['action_disable'] = true;
	}
	if ($gateway_settings['nonlocalgateway'] == "yes") {
		$gateway['nonlocalgateway'] = true;
	}
	if ($gateway_settings['force_down'] == "yes") {
		$gateway['force_down'] = true;
	}
	if (is_ipaddr($gateway_settings['monitor'])) {
		$gateway['monitor'] = $gateway_settings['monitor'];
	}
	if (isset($gateway_settings['data_payload']) && $gateway_settings['data_payload'] > 0) {
		$gateway['data_payload'] = $gateway_settings['data_payload'];
	}

	/* NOTE: If gateway ip is changed need to cleanup the old static interface route */
	if ($gateway_settings['monitor'] != "dynamic" && !empty($a_gateway_item[$realid]) && is_ipaddr($a_gateway_item[$realid]['gateway']) &&
		$gateway['gateway'] != $a_gateway_item[$realid]['gateway'] &&
		isset($a_gateway_item[$realid]["nonlocalgateway"])) {
		$realif = get_real_interface($a_gateway_item[$realid]['interface']);
		$inet = (!is_ipaddrv4($a_gateway_item[$realid]['gateway']) ? "-inet6" : "-inet");
		$rgateway = $a_gateway_item[$realid]['gateway'];
		$cmd = "/sbin/route delete $inet " . escapeshellarg($a_gateway_item[$realid]['gateway']) . " -iface " . escapeshellarg($realif) . " " . escapeshellarg($rgateway);
		mwexec($cmd);
	}

	/* NOTE: If monitor ip is changed need to cleanup the old static route */
	if ($gateway_settings['monitor'] != "dynamic" && !empty($a_gateway_item[$realid]) && is_ipaddr($a_gateway_item[$realid]['monitor']) &&
		$gateway_settings['monitor'] != $a_gateway_item[$realid]['monitor'] && $gateway['gateway'] != $a_gateway_item[$realid]['monitor']) {
		$rgateway = $a_gateway_item[$realid]['gateway'];
		if (is_ipaddrv4($a_gateway_item[$realid]['monitor'])) {
			mwexec("/sbin/route delete " . escapeshellarg($a_gateway_item[$realid]['monitor']) . " " . escapeshellarg($rgateway));
		} else {
			mwexec("/sbin/route delete -inet6 " . escapeshellarg($a_gateway_item[$realid]['monitor']) . " " . escapeshellarg($rgateway));
		}
	}

	if ($gateway_settings['defaultgw'] == "yes" || $gateway_settings['defaultgw'] == "on") {
		// a new default gateway is being saved.
		$i = 0;
		/* remove the default gateway bits for all gateways with the same address family */
		if (is_array($a_gateway_item)) {
			foreach ($a_gateway_item as $gw) {
				if ($gateway['ipprotocol'] == $gw['ipprotocol']) {
					if ($gw['interface'] != $gateway_settings['interface'] &&
						($gw['name'] == $config['gateways']['defaultgw4'] || $gw['name'] == $config['gateways']['defaultgw6'])) {
						// remember the old default gateway interface to call with a "interface reconfigure" event.
						$reloadif = $gw['interface'];
					}
				}
				$i++;
			}
		}
		if ($gateway['ipprotocol'] == "inet") {
			$config['gateways']['defaultgw4'] = $gateway['name'];
		} elseif ($gateway['ipprotocol'] == "inet6") {
			$config['gateways']['defaultgw6'] = $gateway['name'];
		}
	}

	if ($gateway_settings['latencylow']) {
		$gateway['latencylow'] = $gateway_settings['latencylow'];
	}
	if ($gateway_settings['latencyhigh']) {
		$gateway['latencyhigh'] = $gateway_settings['latencyhigh'];
	}
	if ($gateway_settings['losslow']) {
		$gateway['losslow'] = $gateway_settings['losslow'];
	}
	if ($gateway_settings['losshigh']) {
		$gateway['losshigh'] = $gateway_settings['losshigh'];
	}
	if ($gateway_settings['loss_interval']) {
		$gateway['loss_interval'] = $gateway_settings['loss_interval'];
	}
	/* when saving the manual gateway we use the attribute which has the corresponding id */
	if (isset($realid) && $a_gateway_item[$realid]) {
		$preserve_disabled = isset($a_gateway_item[$realid]['disabled']);
		$a_gateway_item[$realid] = $gateway;
		if ($preserve_disabled) {
			$a_gateway_item[$realid]['disabled'] = true;
		}
	} else {
		$a_gateway_item[] = $gateway;
	}
	gateway_set_enabled($gateway_settings['name'], !isset($gateway_settings['disabled']));

	mark_subsystem_dirty('staticroutes');

	write_config();

	if (!empty($reloadif)) {
		send_event("interface reconfigure {$reloadif}");
	}
}

function gateway_set_enabled($name, $enabled) {
	global $config;
	if (is_array($config['gateways']['gateway_item'])) {
		foreach($config['gateways']['gateway_item'] as &$item) {
			if ($item['name'] == $name) {
				$gateway = &$item;
			}
		}
	}
	if (!isset($gateway)) {
		return;
	}
	if ($enabled) {
		unset($gateway['disabled']);
	} else {
		/* Check if the gateway was enabled but changed to disabled. */
		if (!isset($gateway['disabled'])) {
			/*  If the disabled gateway was the default route, remove the default route */
			if (is_ipaddr($gateway['gateway'])) {
				$inet = (!is_ipaddrv4($gateway['gateway']) ? 'inet6' : 'inet');
				if ($inet == 'inet') {
					$cgw = getcurrentdefaultgatewayip('inet');
				} else {
					$cgw = getcurrentdefaultgatewayip('inet6');
				}
				if ($gateway['gateway'] == $cgw) {
					mwexec("/sbin/route delete -{$inet} default " . escapeshellarg($cgw));
				}
			}
			$gateway['disabled'] = true;
		}
	}
}

function gateway_or_gwgroup_exists($name) {
	global $config;
	if (is_array($config['gateways']['gateway_item'])) {
		foreach($config['gateways']['gateway_item'] as $item) {
			if ($item['name'] == $name) {
				return true;
			}
		}
	}
	if (is_array($config['gateways']['gateway_group'])) {
		foreach($config['gateways']['gateway_group'] as $item) {
			if ($item['name'] == $name) {
				return true;
			}
		}
	}
	return false;
}

?>
