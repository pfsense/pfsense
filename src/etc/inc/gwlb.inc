<?php
/*
 * gwlb.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008 Bill Marquette, Seth Mos
 * Copyright (c) 2008-2013 BSD Perimeter
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

require_once("config.inc");
require_once("rrd.inc");
require_once("ipsec.inc");
require_once("interfaces.inc");
require_once("util.inc");

global $gateway_state_kill_modes;
$gateway_state_kill_modes = array(
	'' => gettext("Use global behavior (default)"),
	'none' => gettext("Do not kill states on gateway failure"),
	'down' => gettext("Kill states using this gateway when it is down"),
);

// Initialize the gateways cache variables
define('GW_CACHE_DISABLED', 1); // include disabled gateways and gateways on disabled interfaces
define('GW_CACHE_LOCALHOST', 2); // include "Null" entries for localhost IP addresses
define('GW_CACHE_INACTIVE', 4); // include gateways on inactive interfaces
define('GW_CACHE_INDEXED', 8); // index the returned array by integers instead of the name
define('GW_CACHE_ALL', GW_CACHE_DISABLED | GW_CACHE_LOCALHOST | GW_CACHE_INACTIVE); // all possible gateways
if (!array_key_exists('GatewaysCache', $GLOBALS)) {
	$GLOBALS['GatewaysCache'] = null;
}

/**
 * Caches the gateway list and filters the result. Changes to gateways should
 * call refresh_gateways(), e.g. when adding or removing dynamic gateways.
 * 
 * @param array $flags Additional gateways to include.
 * 
 * @return array filtered list of gateways
 */
function get_gateways(int $flags = 0): array {
	if (!is_array($GLOBALS['GatewaysCache'])) {
		refresh_gateways(); // create the gateway list cache
	}
	$gateways = $GLOBALS['GatewaysCache'];
	$indexed = boolval($flags & GW_CACHE_INDEXED);
	if ($indexed) {
		$remove_disabled = false;
		$remove_localhost = true;
		$remove_inactive = false;
	} elseif (boolval(($flags & GW_CACHE_ALL) == GW_CACHE_ALL)) {
		$remove_disabled = false;
		$remove_localhost = false;
		$remove_inactive = false;
	} else {
		$remove_disabled = !boolval($flags & GW_CACHE_DISABLED);
		$remove_localhost = !boolval($flags & GW_CACHE_LOCALHOST);
		$remove_inactive = !boolval($flags & GW_CACHE_INACTIVE);
	}

	if ($remove_localhost) {
		unset($gateways['Null4']);
		unset($gateways['Null6']);
	}

	if ($remove_disabled || $remove_inactive) {
		foreach ($gateways as $idx => &$gw) {
			if (($idx == 'Null4') || ($idx == 'Null6')) {
				continue;
			}
			if ($remove_disabled && (isset($gw['disabled']) || (isset($gw['friendlyiface']) && !config_path_enabled("interfaces/{$gw['friendlyiface']}")))) {
				unset($gateways[$idx]);
			}
			if ($remove_inactive && isset($gw['inactive'])) {
				unset($gateways[$idx]);
			}
		}
	}

	if ($indexed) {
		$gateways = array_values($gateways);
	}

	if ($remove_disabled || $remove_localhost || $remove_inactive || $indexed) {
		$gateways = order_gateways_as_configured($gateways);
	}

	return $gateways;
}

/**
 * Generates a list of all gateways and adds them to the config if missing.
 */
function refresh_gateways(): void {
	// Generate all gateways and cache the result
	$GLOBALS['GatewaysCache'] = return_gateways_array(true, true, true);

	// Get gateways that exist in the config
	$saved_gateways = array_column(config_get_path('gateways/gateway_item', []), 'name', 'name');

	// Exclude null gateways from being saved to config
	foreach (array_diff_key($GLOBALS["GatewaysCache"], array_fill_keys(['Null4', 'Null6'], null)) as $gateway) {
		// Save any of the generated gateways that are not found in config
		if (!array_key_exists($gateway['name'], $saved_gateways)) {
			// Build the gateway elements and save the gateway to config
			config_set_path('gateways/gateway_item',
				array_merge(config_get_path('gateways/gateway_item', []), [[
					'interface' => $gateway['friendlyiface'],
					'gateway' => $gateway['dynamic'] ? 'dynamic' : $gateway['gateway'],
					'name' => $gateway['name'],
					'weight' => $gateway['weight'] ?? 1,
					'ipprotocol' => $gateway['ipprotocol'],
					'descr' => $gateway['descr'],
					'gw_down_kill_states' => $gateway['gw_down_kill_states'] ?? ''
				]])
			);
			if (!isset($write_config)) {
				$write_config = true;
			}
		}
	}
	if ($write_config) {
		write_config(gettext('Synced configured gateways'), true, true);
	}
}

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
		"data_payload" => "1");
}

function running_dpinger_processes() {
	$pidfiles = glob(g_get('varrun_path', '') . '/dpinger_*.pid');

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
	if (!isset($gateway['gwifip'])) {
		return (false);
	}

	$dpinger_defaults = return_dpinger_defaults();

	$prefix = g_get('varrun_path', '') . "/dpinger_{$gateway['name']}~" .
	    "{$gateway['gwifip']}~{$gateway['monitor']}";
	# dpinger socket path should not be longer then uaddr.sun_path
	if (strlen($prefix) > 95) {
		$prefix = g_get('varrun_path', '') . "/dpinger_{$gateway['name']}~" .
		    substr(md5($gateway['gwifip']),0,8) . "~" .
		    $gateway['monitor'];
	}
	$pidfile = $prefix . ".pid";
	$socket = $prefix . ".sock";
	$alarm_cmd = g_get('etc_path', '') . '/rc.gateway_alarm';

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
	refresh_gateways(); // dynamic gateways may have changed so refresh the cache
	$gateways_arr = get_gateways();
	if (!is_array($gateways_arr)) {
		log_error(gettext("No gateways to monitor. dpinger will not run."));
		stop_dpinger();
		return;
	}
	if (is_platform_booting()) {
		echo "Setting up gateway monitors...";
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
			if (!config_path_enabled('system', 'dpinger_dont_add_static_routes') &&
					!isset($gateway['dpinger_dont_add_static_route'])) {
				if (is_ipaddrv4($gateway['gateway']) && $gateway['monitor'] != $gateway['gateway']) {
					log_error(sprintf(gettext('Removing static route for monitor %1$s and adding a new route through %2$s'), $gateway['monitor'], $gateway['gateway']));
					if (interface_isppp_type($gateway['friendlyiface'])) {
						route_add_or_change($gateway['monitor'],
						    '', $gateway['interface']);
						system_staticroutes_configure($gateway['friendlyiface']);
					} else {
						route_add_or_change($gateway['monitor'],
						    $gateway['gateway']);
					}

					pfSense_kill_states("0.0.0.0/0", $gateway['monitor'], $gateway['interface'], "icmp");
				}
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

			if (!config_path_enabled('system', 'dpinger_dont_add_static_routes') &&
					!isset($gateway['dpinger_dont_add_static_route'])) {
				if ($gateway['gateway'] != $gateways_arr[$gwname]['monitor']) {
					log_error(sprintf(gettext('Removing static route for monitor %1$s and adding a new route through %2$s'), $gateway['monitor'], $gateway['gateway']));
					if (interface_isppp_type($gateway['friendlyiface'])) {
						route_add_or_change($gateway['monitor'],
						    '', $gateway['interface']);
						system_staticroutes_configure($gateway['friendlyiface']);
					} else {
						route_add_or_change($gateway['monitor'],
						    $gateway['gateway']);
					}

					pfSense_kill_states("::0.0.0.0/0", $gateway['monitor'], $gateway['interface'], "icmpv6");
				}
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
	if (is_platform_booting()) {
		echo "done.\n";
	}

	return;
}

/**
 * Looks for the highest priority failover tier that is currently online in a
 * failover gateway group. A failover tier is any tier number greater than the
 * lowest configured tier number. A failover gateway group must consist of at
 * least two different tiers.
 * 
 * @param string $gateway_group Name of an existing failover gateway group
 * 
 * @return int The tier number - returns 0 if no matches are found
 * @return false Not a valid failover gateway group
 */
function get_highest_failover_tier_online(?string $gateway_group): int|false {
	if (empty($gateway_group)) {
		return false;
	}

	foreach (config_get_path('gateways/gateway_group', []) as $group) {
		// check if this is the gateway group being queried
		if ($group['name'] != $gateway_group) {
			continue;
		}

		// make sure there are expected values and at least two gateways in the gateway group
		if (!is_array($group['item']) || empty($group['item']) || (count($group['item']) < 2)) {
			continue;
		}
		// build a list of gateways and their tiers
		$group_gateways = [];
		foreach ($group['item'] as $item) {
			$gateway_item = explode("|", $item);
			// at least a gateway name and tier is expected
			if (count($gateway_item) < 2) {
				continue;
			}

			$group_gateways[$gateway_item[0]] = intval($gateway_item[1]);
		}

		// make sure it's a failover group by checking for different tiers
		$group_tiers = array_unique(array_values($group_gateways));
		if (count($group_tiers) < 2) {
			break;
		}

		if (!isset($gateways_states)) {
			// get a list of all relevant gateway states
			$gateways_states = return_gateways_status(true);
		}

		$highest_tier = min($group_tiers);
		// remove offline gateways
		foreach (array_keys($group_gateways) as $gateway_name) {
			if ($gateways_states[$gateway_name]['status'] != 'online') {
				unset($group_gateways[$gateway_name]);
			}
		}

		// no online gateways
		if (empty($group_gateways)) {
			return 0;
		}

		// highest online is highest tier
		$highest_online_tier = min(array_values($group_gateways));
		if ($highest_online_tier == $highest_tier) {
			return 0;
		}

		// return the highest-priority failover tier of online gateways
		return $highest_online_tier;
	}

	return false;
}

/**
 * Loops through each gateway group and kills states for lower-priority gateways.
 * Supports the same gateway at different tiers in different gateway groups. A
 * failover gateway group name may be provided to restrict the state removal.
 * 
 * @param string $gateway_name Gateway group name to remove failover states for
 */
function remove_failover_states(?string $gateway_name = null): void {
	// get the global default for gateway groups
	$keep_failover_states = config_path_enabled('system', 'keep_failover_states');

	// if killing states from the firewall itself, check for default failover gateway groups
	$remove_states_default = config_get_path('system/remove_failover_states_default');
	if ($remove_states_default) {
		$default_gateways = [];

		$default_gateway4 = config_get_path('gateways/defaultgw4', '');
		if (!empty($default_gateway4) && ($default_gateway4 != '-')) {
			$default_gateways[$default_gateway4] = null;
		}
		$default_gateway6 = config_get_path('gateways/defaultgw6', '');
		if (!empty($default_gateway6) && ($default_gateway6 != '-')) {
			$default_gateways[$default_gateway6] = null;
		}
	}

	foreach (config_get_path('gateways/gateway_group', []) as $group) {
		// skip if states don't need to be killed for this gateway group
		if ((!empty($gateway_name) && ($group['name'] != $gateway_name)) ||
		    ($keep_failover_states && ($group['keep_failover_states'] != 'kill'))) {
			continue;
		}

		// make sure there are expected values and at least two gateways in the gateway group
		if (!is_array($group['item']) || empty($group['item']) || (count($group['item']) < 2)) {
			continue;
		}
		// build a list gateway interfaces and tiers
		$gateway_interfaces = [];
		foreach ($group['item'] as $item) {
			$gateway_item = explode("|", $item);
			// at least a gateway name and tier is expected
			if (count($gateway_item) < 2) {
				continue;
			}

			if (!isset($gateways_states)) {
				// get a list of all relevant gateway states
				$gateways_states = return_gateways_status(true);
			}

			// only process online gateways
			if ($gateways_states[$gateway_item[0]]['status'] != 'online') {
				continue;
			}

			if (!isset($all_gateways)) {
				// get a list of all relevant gateways
				$all_gateways = get_gateways();
			}

			// get the gateway's interface and tier
			$gateway_interfaces[$all_gateways[$gateway_item[0]]['interface']] = intval($gateway_item[1]);
		}

		// make sure there are at least two online gateways (interfaces) in this gateway group
		if (empty($gateway_interfaces) || (count($gateway_interfaces) < 2)) {
			continue;
		}

		// build a list of interfaces, used to kill states from the firewall itself
		if ($remove_states_default && array_key_exists($group['name'], $default_gateways)) {
			// get the interfaces of the default gateway group
			$default_gateways[$group['name']] = $gateway_interfaces;

			// for states from the firewall itself (when the default gateway is set to a failover gateway group)
			foreach (array_keys($gateway_interfaces, min(array_values($gateway_interfaces))) as $gateway_interface) {
				// exclude the interface of the highest priority gateway(s)
				unset($default_gateways[$group['name']][$gateway_interface]);
			}

			// all states are to be killed for this gateway group, skip killing only policy routing states
			continue;
		}

		// build a list of tiers to kill policy routing states for
		$tiers_to_kill = array_unique(array_values($gateway_interfaces));
		// avoid killing states for the higher-tier gateway(s)
		$tiers_to_kill = array_diff($tiers_to_kill, [min($tiers_to_kill)]);
		foreach ($tiers_to_kill as $tier) {
			mwexec_bg('/sbin/pfctl -M -k label -k ' . escapeshellarg("gw{$tier}:{$group['name']}") . ' -q');
		}
	}

	// kill all states for the default failover gateway groups
	if (!$remove_states_default) {
		return;
	}
	foreach ($default_gateways as $gateway_name => $gateway_interfaces) {
		if (empty($gateway_interfaces)) {
			continue;
		}

		/* filtering for the address family allows states that have been created by user
		   rules with a gateway of a different address family on the same interface to remain */
		if ($remove_states_default == 'addrfamily') {
			$host_prefix = ($gateway_name == $default_gateway4) ? '-k 0.0.0.0/0' : '-k ::/0';
		} elseif ($remove_states_default == 'all') {
			$host_prefix = '-Fs';
		} else {
			continue;
		}
		foreach (array_keys($gateway_interfaces) as $gateway_interface) {
			mwexec_bg('/sbin/pfctl -M -i ' . escapeshellarg($gateway_interface) . " {$host_prefix} -q");
		}
	}
}

function get_dpinger_status($gwname, $action_disable = false) {
	$running_processes = running_dpinger_processes();

	if (!isset($running_processes[$gwname])) {
		log_error(sprintf(gettext(
		    'dpinger: No dpinger session running for gateway %s'),
		    $gwname));
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

	$gateways_arr = get_gateways();

	unset($gw);
	if (isset($gateways_arr[$gwname])) {
		$gw = $gateways_arr[$gwname];
	}

	$r['latency_avg'] = round($r['latency_avg']/1000, 3);
	$r['latency_stddev'] = round($r['latency_stddev']/1000, 3);

	$r['status'] = "online";
	$r['substatus'] = "none";
	if (isset($gw) && isset($gw['force_down'])) {
		$r['status'] = "down";
		$r['substatus'] = "force_down";
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
			if (!$action_disable) {
				$r['status'] = "down";
			}
			$r['substatus'] = "highdelay";
		} else if ($r['loss'] > $settings['losshigh']) {
			if (!$action_disable) {
				$r['status'] = "down";
			}
			$r['substatus'] = "highloss";
		} else if ($r['latency_avg'] > $settings['latencylow']) {
			$r['substatus'] = "delay";
		} else if ($r['loss'] > $settings['losslow']) {
			$r['substatus'] = "loss";
		}
	}

	return $r;
}

/* return the status of the dpinger targets as an array */
function return_gateways_status($byname = false) {
	$dpinger_gws = running_dpinger_processes();
	$status = array();

	$gateways_arr = get_gateways();

	foreach ($dpinger_gws as $gwname => $gwdata) {
		/*
		 * If action is disabled for this gateway, then we want a
		 * detailed status.  That reports "highdelay" or "highloss"
		 * rather than just "down".  Because reporting the gateway
		 * down would be misleading (gateway action is disabled)
		 */
		$action_disable = $gateways_arr[$gwname]['action_disable'];
		$dpinger_status = get_dpinger_status($gwname, $action_disable);
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
		$status[$target]['delay'] =
		    empty($dpinger_status['latency_avg'])
		    ? "0ms"
		    : $dpinger_status['latency_avg'] . "ms";
		$status[$target]['stddev'] =
		    empty($dpinger_status['latency_stddev'])
		    ? "0ms"
		    : $dpinger_status['latency_stddev'] . "ms";
		$status[$target]['loss'] = empty($dpinger_status['loss'])
		    ? "0.0%"
		    : round($dpinger_status['loss'], 1) . "%";
		$status[$target]['status'] = $dpinger_status['status'];
		$status[$target]['substatus'] = $dpinger_status['substatus'];
	}

	/* tack on any gateways that have monitoring disabled
	 * or are down, which could cause gateway groups to fail */
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
			$status[$target]['substatus'] = "down";
		} else {
			$status[$target]['monitorip'] = $tgtip;
			$status[$target]['srcip'] = $srcip;
			$status[$target]['name'] = $gwitem['name'];
			$status[$target]['delay'] = "";
			$status[$target]['loss'] = "";
			$status[$target]['status'] = "online";
			$status[$target]['substatus'] = "none";
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
				'status' => "Status",
				'substatus' => "Substatus");
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
*/
function return_gateways_array($disabled = false, $localhost = false, $inactive = false) {
	$gateways_arr = array();
	$gateways_arr_temp = array();
	$cgw4 = route_get_default('inet');
	$cgw6 = route_get_default('inet6');
	$found_defaultv4 = 0;
	$found_defaultv6 = 0;

	// Ensure the interface cache is up to date first
	$interfaces = get_interface_arr(true);

	$i = -1;
	/* Process/add all the configured gateways. */
	foreach (config_get_path('gateways/gateway_item', []) as $gateway) {
		if (!is_array($gateway) || empty($gateway)) {
			continue;
		}

		/* Increment it here to do not skip items */
		$i++;
		if (isset($gateway['defaultgw'])) {
			unset($gateway['defaultgw']);
		}

		if (empty(config_get_path("interfaces/{$gateway['interface']}"))) {
			if ($inactive === false) {
				continue;
			} else {
				$gateway['inactive'] = true;
			}
		}
		$wancfg = config_get_path("interfaces/{$gateway['interface']}");

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
		$gateway['friendlyifdescr'] = convert_friendly_interface_to_friendly_descr($gateway['interface']);

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

		if (empty(config_get_path("interfaces/{$ifname}"))) {
			continue;
		}

		$ifcfg = config_get_path("interfaces/{$ifname}");
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
					$found = false;
					foreach (config_get_path("openvpn/{$ovpntype}", []) as $ovpnconf) {
						if (empty($ovpnconf)) {
							continue;
						}
						if ($ovpnconf['vpnid'] == $ovpnid) {
							$found = true;
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
					if (!$found) {
						// skip unexpected interfaces, e.g. QinQ such as ovpns6.600.601
						continue 2;
					}
					$ctype = "VPNv4";
				} elseif (substr($ifcfg['if'], 0, 5) == "ipsec") {
					$ikeid = substr($ifcfg['if'], 5);
					if (!empty(config_get_path('ipsec/phase1', [])) &&
					    !empty(config_get_path('ipsec/phase2', []))) {
						foreach (config_get_path('ipsec/phase1', []) as $ph1ent) {
							if (empty($ph1ent) || $ph1ent['disabled']) {
								continue;
							}
							$vtisubnet_spec = ipsec_vti($ph1ent, true);
							// Skip non-VTI tunnels
							if (!$vtisubnet_spec || !is_array($vtisubnet_spec)) {
								continue;
							}
							if (!isset($ph1ent['mobile']) && ($ph1ent['iketype'] == 'ikev1' || isset($ph1ent['splitconn']))) {
								foreach ($vtisubnet_spec as $idx => $vtisub) {
									if ($ifcfg['if'] == ipsec_get_ifname($ph1ent, $vtisub['reqid'])) {
										// If this specific VTI remote is v4, then we can make a v4 gw
										if (is_ipaddrv4($vtisub['right'])) {
											$ctype = "VTIv4";
										}
									}
								}
							} else {
								if ($ifcfg['if'] == ipsec_get_ifname($ph1ent)) {
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
		$gateway['friendlyifdescr'] = convert_friendly_interface_to_friendly_descr($ifname);
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
		if (!config_path_enabled('system', 'ipv6allow')) {
			break;
		}

		if (!interface_has_gatewayv6($ifname)) {
			continue;
		}

		if (empty(config_get_path("interfaces/{$ifname}"))) {
			continue;
		}

		$ifcfg = config_get_path("interfaces/{$ifname}");
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
					$found = false;
					foreach (config_get_path("openvpn/{$ovpntype}", []) as $ovpnconf) {
						if (empty($ovpnconf)) {
							continue;
						}
						if ($ovpnconf['vpnid'] == $ovpnid) {
							$found = true;
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
					if (!$found) {
						// skip unexpected interfaces, e.g. QinQ such as ovpns6.600.601
						continue 2;
					}
					$ctype = "VPNv6";
				} elseif (substr($ifcfg['if'], 0, 5) == "ipsec") {
					$ikeid = substr($ifcfg['if'], 5);
					if (!empty(config_get_path('ipsec/phase1', [])) &&
					    !empty(config_get_path('ipsec/phase2', []))) {
						foreach (config_get_path('ipsec/phase1', []) as $ph1ent) {
							if (empty($ph1ent) || $ph1ent['disabled']) {
								continue;
							}
							$vtisubnet_spec = ipsec_vti($ph1ent, true);
							// Skip non-VTI tunnels
							if (!$vtisubnet_spec || !is_array($vtisubnet_spec)) {
								continue;
							}
							if (!isset($ph1ent['mobile']) && ($ph1ent['iketype'] == 'ikev1' || isset($ph1ent['splitconn']))) {
								foreach ($vtisubnet_spec as $idx => $vtisub) {
									if ($ifcfg['if'] == ipsec_get_ifname($ph1ent, $vtisub['reqid'])) {
										// If this specific VTI remote is v6, then we can make a v6 gw
										if (is_ipaddrv6($vtisub['right'])) {
											$ctype = "VTIv6";
										}
									}
								}
							} else {
								if ($ifcfg['if'] == ipsec_get_ifname($ph1ent)) {
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
		$gateway['friendlyifdescr'] = convert_friendly_interface_to_friendly_descr($ifname);
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
				if (file_exists(g_get('tmp_path', '') . "/{$gateway['interface']}_defaultgw")) {
					$gateway['isdefaultgw'] = true;
					$found_defaultv4 = 1;
				}
			}
			else if (($gateway['friendlyiface'] == "wan") && ($found_defaultv6 == 0) && ($gateway['ipprotocol'] == "inet6")) {
				if (file_exists(g_get('tmp_path', '') . "/{$gateway['interface']}_defaultgwv6")) {
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

	$gways = order_gateways_as_configured($gateways_arr);

	// Add the tier names here so that system_gateways.php doesn't need to
	foreach ($gways as $idx => $gway) {
		$gways[$idx]['tiername'] = gateway_getgwtiername($gways, $idx);
	}

	return $gways;
}

function fixup_default_gateway($ipprotocol, $gateways_status, $gateways_arr) {
	/*
	 * NOTE: The code below is meant to replace the default gateway when it goes down.
	 *	This facilitates services running on pfSense itself and are not handled by a PBR to continue working.
	 */
	$set_dfltgwname = '';

	if ($ipprotocol == 'inet') {
		$gwdefault = config_get_path('gateways/defaultgw4', "");
	} else {
		$gwdefault = config_get_path('gateways/defaultgw6', "");
	}
	/* do not delete dynamic (frr/bgp/ospf) route
	 * see https://redmine.pfsense.org/issues/12536 */
	if ($gwdefault == "-") {
		if (!is_dynamic_route('default', $ipprotocol)) {
			route_del('default', $ipprotocol);
		}
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
			if (($gwsttng['ipprotocol'] != $ipprotocol) || isset($gwsttng['force_down'])) {
				continue;
			}

			if (isset($gwsttng['monitor_disable']) || isset($gwsttng['action_disable']) ||
			    ($gateways_status[$gwname]['status'] == "online")) {
				$set_dfltgwname = $gwname;
				break;
			}
			if (empty($fallback) && $gwsttng['interface'] != 'lo0') {
				$fallback = $gwname;
			}
		}
		if (empty($set_dfltgwname) && !empty($fallback)) {
			log_error(sprintf("Gateway, none 'available' for %s, use the first one configured. '%s'", $ipprotocol, $fallback));
			$set_dfltgwname = $fallback;
		} else {
			log_error("Gateway, NONE AVAILABLE");
		}
	} else {
		// a gwgroup is selected
		// find the best available gateway given options available..
		$gwg_members = array();
		$viplist = get_configured_vip_list();
		foreach (config_get_path('gateways/gateway_group', []) as $group) {
			if ($group['name'] == $gwdefault) {
				// finds the gw members of the best available tier for this group.
				$gwg_members = get_gwgroup_members_inner($group, $gateways_status, $gateways_arr, $viplist);
			}
		}

		if (count($gwg_members) > 0) {
			$currentdefaultgwip = route_get_default($ipprotocol);
			$found_current = false;
			foreach($gwg_members as $gwgroupitem) {
				if (!empty($currentdefaultgwip) &&
				    is_array($gwgroupitem) &&
				    ($gwgroupitem['gwip'] == $currentdefaultgwip)) {
					$set_dfltgwname = $gwgroupitem['gw'];
					$found_current = true;
					if (config_path_enabled('system', 'gw-debug')) {
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
	} elseif (empty($set_dfltgwname)) {
		route_del('default', $ipprotocol);
	}
}

function setdefaultgateway($gw) {
	if (config_path_enabled('system', 'route-debug')) {
		file_put_contents("/dev/console", "\n[".getmypid()."] SET DEF GW: {$gw['name']}");
	}
	$ipprotocol = $gw['ipprotocol'];
	if ($gw['gateway'] == "dynamic") {
		if ($ipprotocol == 'inet') {
			$gw['gateway'] = get_interface_gateway($gw['friendlyiface']);
		} else {
			$gw['gateway'] = get_interface_gateway_v6($gw['friendlyiface']);
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
	$currentdefaultgwip = route_get_default($ipprotocol);
	if ($currentdefaultgwip != $gw['gateway']) {
		log_error("Default gateway setting {$gw['descr']} as default.");

		if ($ipprotocol == 'inet') {
			$inet = '';
		} else {
			$inet = 'v6';
		}
		unlink_if_exists(g_get('tmp_path', '') . "/*_defaultgw{$inet}");
		$defaultif = get_real_interface($gw['interface']);
		if ($defaultif) {
			@file_put_contents(g_get('tmp_path', '') . "/{$defaultif}_defaultgw{$inet}", $gw['gateway']);
		}

		if (isset($gw["nonlocalgateway"])) {
			if (is_ipaddr($gw['gateway']) && !empty($gw['interface'])) {
				route_add_or_change($gw['gateway'], '',
				    $gw['interface']);
			}
		}
		if (config_path_enabled('system', 'route-debug')) {
			file_put_contents("/dev/console", "\n[".getmypid()."] SET DEF GW: {$gw['name']} ({$gw['gateway']})");
		}
		route_add_or_change("default", $gw['gateway'], '', '',
		    $ipprotocol);
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
				$gwdown = true;
				switch ($status['substatus']) {
					case "highloss":
						$msg = sprintf(gettext('MONITOR: %1$s has packet loss, omitting from routing group %2$s'), $gwname, $group['name']);
						break;
					case "highdelay":
						$msg = sprintf(gettext('MONITOR: %1$s has high latency, omitting from routing group %2$s'), $gwname, $group['name']);
						break;
					default:
						$msg = sprintf(gettext('MONITOR: %1$s is down, omitting from routing group %2$s'), $gwname, $group['name']);
				}
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
					$msg = sprintf(gettext('MONITOR: %1$s is available now, adding to routing group %2$s'), $gwname, $group['name']);
					$msg .= "\n".implode("|", $status);
					$pluginparams['event'] = 'gateway.up';
					$statuschanged = true;
				}
			}
			if ($statuschanged) {
				log_error($msg);
				notify_all_remote($msg);
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
		if (config_path_enabled('system', 'gw-debug') && (!is_platform_booting())) {
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
				} elseif (!empty($int)) {
					if ($gateway['ipprotocol'] == 'inet') {
						$gatewayip = get_interface_gateway($gateway['friendlyiface']);
					} else {
						$gatewayip = get_interface_gateway_v6($gateway['friendlyiface']);
					}
				}

				if (!empty($int)) {
					$result['ipprotocol'] = $gateway['ipprotocol'];
					if (is_ipaddr($gatewayip)) {
						$groupmember = array();
						$groupmember['gw'] = $member;
						$groupmember['int'] = $int;
						$groupmember['gwip'] = $gatewayip;
						/* set correct linklocal gateway address,
						 * see https://redmine.pfsense.org/issues/12721 */
						if (is_ipaddrv6($gatewayip) && is_linklocal($gatewayip) && empty(get_ll_scope($gatewayip))) {
							$groupmember['gwip'] .= '%' . $int;
						}
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
	$gateways_status = return_gateways_status(true);
	$gateways_arr = get_gateways();
	$viplist = get_configured_vip_list();
	foreach (config_get_path('gateways/gateway_group', []) as $group) {
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
	/* fetch the current gateways status */
	$gateways_status = return_gateways_status(true);
	$gateways_arr = get_gateways();
	$gateway_groups_array = array();
	if ($fixup == true) {
		$gw4 = lookup_gateway_or_group_by_name(config_get_path('gateways/defaultgw4', ""));
		$gw6 = lookup_gateway_or_group_by_name(config_get_path('gateways/defaultgw6', ""));
		if ($gw4 && $gw4['type'] == 'gatewaygroup') {
			fixup_default_gateway("inet", $gateways_status, $gateways_arr);
		}
		if ($gw6 && $gw6['type'] == 'gatewaygroup') {
			fixup_default_gateway("inet6", $gateways_status, $gateways_arr);
		}
	}
	config_init_path('gateways/gateway_group');
	if (!empty(config_get_path('gateways/gateway_group'))) {
		$viplist = get_configured_vip_list();
		foreach (config_get_path('gateways/gateway_group', []) as $group) {
			$gateway_groups_array[$group['name']] = get_gwgroup_members_inner($group, $gateways_status, $gateways_arr, $viplist);
		}
	}

	return ($gateway_groups_array);
}

/* Update DHCP WAN Interface ip address in gateway group item */
function dhclient_update_gateway_groups_defaultroute($interface = "wan") {
	$gwlist = config_get_path('gateways/gateway_item', []);
	foreach ($gwlist as & $gw) {
		if ($gw['interface'] != $interface) {
			continue;
		}

		$current_gw = get_interface_gateway($interface);
		if ($gw['gateway'] <> $current_gw) {
			$gw['gateway'] = $current_gw;
			$changed = true;
		}
	}
	config_set_path('gateways/gateway_item', $gwlist);

	if ($changed && $current_gw) {
		write_config(sprintf(gettext(
		    'Updating gateway group gateway for %1$s - new gateway is %2$s'),
		    $interface, $current_gw));
	}
}

function lookup_gateway_or_group_by_name($gwname) {
	$gateways_arr = get_gateways();

	foreach ($gateways_arr as $gw) {
		if ($gw['name'] == $gwname) {
			$gw['type'] = 'gateway';
			return $gw;
		}
	}

	config_init_path('gateways/gateway_group');
	foreach (config_get_path('gateways/gateway_group', []) as $gwg) {
		if ($gwg['name'] == $gwname) {
			$gwg['type'] = 'gatewaygroup';
			return $gwg;
		}
	}

	return false;
}

function lookup_gateway_ip_by_name($name, $disabled = false) {

	$gateways_arr = get_gateways(($disabled ? GW_CACHE_DISABLED : 0) | GW_CACHE_LOCALHOST);
	foreach ($gateways_arr as $gname => $gw) {
		if ($gw['name'] === $name || $gname === $name) {
			return $gw['gateway'];
		}
	}

	return false;
}

function lookup_gateway_monitor_ip_by_name($name) {

	$gateways_arr = get_gateways(GW_CACHE_LOCALHOST);
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

	$gateways_arr = get_gateways(GW_CACHE_LOCALHOST);
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
	$interface = get_root_interface($interface);

	$gw = NULL;
	$gwcfg = config_get_path("interfaces/{$interface}");
	if (!empty($gwcfg['gateway'])) {
		foreach (config_get_path('gateways/gateway_item', []) as $gateway) {
			if (($gateway['name'] == $gwcfg['gateway']) && (is_ipaddrv4($gateway['gateway']))) {
				$gw = $gateway['gateway'];
				break;
			}
		}
	}

	// for dynamic interfaces we handle them through the $interface_router file.
	if (($gw == NULL || !is_ipaddrv4($gw)) && !is_ipaddrv4($gwcfg['ipaddr'])) {
		$realif = get_real_interface($interface);
		if (file_exists(g_get('tmp_path', '') . "/{$realif}_router")) {
			$gw = trim(@file_get_contents(g_get('tmp_path', '') . "/{$realif}_router"), " \n");
			$dynamic = true;
		}
		if (file_exists(g_get('tmp_path', '') . "/{$realif}_defaultgw")) {
			$dynamic = "default";
		}

	}

	/* return gateway */
	return ($gw);
}

function get_interface_gateway_last($interface, $family = 'inet') {
	$interface = get_root_interface($interface);
	$realif = get_real_interface($interface);
	$suffix = ($family == 'inet6') ? 'v6' : '';
	if (file_exists(g_get('tmp_path', '') . "/{$realif}_router{$suffix}.last")) {
		return trim(@file_get_contents(g_get('tmp_path', '') . "/{$realif}_router{$suffix}.last"), " \n");
	}
	return '';
}

function get_interface_gateway_v6($interface, &$dynamic = false) {
	$interface = get_root_interface($interface);

	$gw = NULL;
	$gwcfg = config_get_path("interfaces/{$interface}");
	if (!empty($gwcfg['gatewayv6'])) {
		foreach (config_get_path('gateways/gateway_item', []) as $gateway) {
			if (($gateway['name'] == $gwcfg['gatewayv6']) && (is_ipaddrv6($gateway['gateway']))) {
				$gw = $gateway['gateway'];
				break;
			}
		}
	}

	// for dynamic interfaces we handle them through the $interface_router file.
	if (($gw == NULL || !is_ipaddrv6($gw)) && !is_ipaddrv6($gwcfg['ipaddrv6'])) {
		$realif = get_real_interface($interface);
		if (file_exists(g_get('tmp_path', '') . "/{$realif}_routerv6")) {
			$gw = trim(file_get_contents(g_get('tmp_path', '') . "/{$realif}_routerv6"), " \n");
			$dynamic = true;
		}
		if (file_exists(g_get('tmp_path', '') . "/{$realif}_defaultgwv6")) {
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

	/* Check gateways including disabled gateways and gateways for disabled
	 * interfaces.
	 * https://redmine.pfsense.org/issues/8846
	 */
	$gw_array = get_gateways(GW_CACHE_DISABLED | GW_CACHE_INACTIVE);
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
	if (empty(config_get_path('gateways/gateway_group'))) {
		return false;
	}

	$gateways_arr = get_gateways(GW_CACHE_LOCALHOST);
	foreach (config_get_path('gateways/gateway_group', []) as $group) {
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
	$members = array();
	foreach (config_get_path('gateways/gateway_group', []) as $group) {
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

	if (empty($members)) {
		return false;
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
function validate_gateway($gateway_settings, $id = "", $parent_ip = "", $parent_sn = "", $json = false) {
	if ($json) {
		$gateway_settings = json_decode($gateway_settings, true);
	}
	global $gateway_state_kill_modes;

	refresh_gateways(); // make sure we're validating against a current gateway list
	$a_gateways = get_gateways(GW_CACHE_INDEXED);
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
		foreach (config_get_path('gateways/gateway_group', []) as $group) {
			foreach ($group['item'] as $item) {
				$items = explode("|", $item);
				if ($items[0] == $gateway_settings['name']) {
					$input_errors[] = sprintf(gettext('Gateway "%1$s" cannot be disabled because it is in use on Gateway Group "%2$s"'), $gateway_settings['name'], $group['name']);
				}
			}
		}

		// Check if the gateway name is used in any enabled Static Route.
		foreach (config_get_path('staticroutes/route', []) as $route) {
			if ($route['gateway'] == $gateway_settings['name']) {
				if (!isset($route['disabled'])) {
					// There is a static route that uses this gateway and is enabled (not disabled).
					$input_errors[] = sprintf(gettext('Gateway "%1$s" cannot be disabled because it is in use on Static Route "%2$s"'), $gateway_settings['name'], $route['network']);
				}
			}
		}

		// Check if the gateway name is used by any DNS Server
		for ($dnsgw_counter = 1; !is_null($dnsgw = config_get_path("system/dns{$dnsgw_counter}gw")); $dnsgw_counter++) {
			if ($gateway_settings['name'] == $dnsgw) {
				// The user wants to disable this gateway, but there is a static route to the DNS server that refers to the gateway.
				$input_errors[] = sprintf(gettext('Gateway "%1$s" cannot be disabled because it is in use by DNS Server "%2$s"'), $gateway_settings['name'], $dnsgw);
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

		if (!empty(config_get_path("interfaces/{$gateway_settings['interface']}/ipaddr"))) {
			if (is_ipaddr(config_get_path("interfaces/{$gateway_settings['interface']}/ipaddr")) && (empty($gateway_settings['gateway']) || $gateway_settings['gateway'] == "dynamic")) {
				$input_errors[] = gettext("Dynamic gateway values cannot be specified for interfaces with a static IPv4 configuration.");
			}
		}
		if (!empty(config_get_path("interfaces/{$gateway_settings['interface']}/ipaddrv6"))) {
			if (is_ipaddr(config_get_path("interfaces/{$gateway_settings['interface']}/ipaddrv6")) && (empty($gateway_settings['gateway']) || $gateway_settings['gateway'] == "dynamic")) {
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
	if (!empty($gateway_settings['skip_rules_gw_down']) && !array_key_exists($gateway_settings['skip_rules_gw_down'], $gateway_state_kill_modes)) {
		$input_errors[] = gettext("Please select a valid State Killing on Gateway Failure mode.");
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

	if ($json) {
		return json_encode($input_errors);
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

function save_gateway($gateway_settings, $realid = "", $json = false) {
	if ($json) {
		$gateway_settings = json_decode($gateway_settings, true);
	}

	config_init_path('gateways/gateway_item');
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
	if ($gateway_settings['dpinger_dont_add_static_route'] == "yes") {
		$gateway['dpinger_dont_add_static_route'] = true;
	}
	if ($gateway_settings['force_down'] == "yes") {
		$gateway['force_down'] = true;
	}
	$gateway['gw_down_kill_states'] = $gateway_settings['gw_down_kill_states'];
	if (is_ipaddr($gateway_settings['monitor'])) {
		$gateway['monitor'] = $gateway_settings['monitor'];
	}
	if (isset($gateway_settings['data_payload']) && is_numeric($gateway_settings['data_payload']) && $gateway_settings['data_payload'] >= 0) {
		$gateway['data_payload'] = $gateway_settings['data_payload'];
	}

	$a_gateway_item = config_get_path("gateways/gateway_item/{$realid}");
	/* NOTE: If gateway ip is changed need to cleanup the old static interface route */
	if ($gateway_settings['monitor'] != "dynamic" &&
	    !empty($a_gateway_item) &&
	    is_ipaddr($a_gateway_item['gateway']) &&
	    $gateway['gateway'] != $a_gateway_item['gateway'] &&
	    isset($a_gateway_item["nonlocalgateway"])) {
		route_del($a_gateway_item['gateway']);
	}

	/* NOTE: If monitor ip is changed need to cleanup the old static route */
	if ($gateway_settings['monitor'] != "dynamic" &&
	    !empty($a_gateway_item) &&
	    is_ipaddr($a_gateway_item['monitor']) &&
	    $gateway_settings['monitor'] != $a_gateway_item['monitor'] &&
	    $gateway['gateway'] != $a_gateway_item['monitor']) {
		route_del($a_gateway_item['monitor']);
	}

	if ($gateway_settings['defaultgw'] == "yes" || $gateway_settings['defaultgw'] == "on") {
		// a new default gateway is being saved.
		$i = 0;
		/* remove the default gateway bits for all gateways with the same address family */
		foreach (config_get_path('gateways/gateway_item', []) as $gw) {
			if ($gateway['ipprotocol'] == $gw['ipprotocol']) {
				if ($gw['interface'] != $gateway_settings['interface'] &&
					($gw['name'] == config_get_path('gateways/defaultgw4', "") ||
					 $gw['name'] == config_get_path('gateways/defaultgw6', ""))) {
					// remember the old default gateway interface to call with a "interface reconfigure" event.
					$reloadif = $gw['interface'];
				}
			}
			$i++;
		}
		if ($gateway['ipprotocol'] == "inet") {
			config_set_path('gateways/defaultgw4', $gateway['name']);
		} elseif ($gateway['ipprotocol'] == "inet6") {
			config_set_path('gateways/defaultgw6', $gateway['name']);
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

	$a_gateway_item = config_get_path("gateways/gateway_item/{$realid}");
	/* reload IPsec and OpenVPN on gateway IP, 'Mark Gateway as Down', or 'Disabled' option change
	 * see https://redmine.pfsense.org/issues/13076 */
	if (!empty($a_gateway_item) &&
	    (isset($gateway['disabled']) ^ isset($a_gateway_item['disabled'])) ||
	    (!isset($a_gateway_item['disabled']) &&
	    ((($gateway['monitor'] != "dynamic") &&
	    is_ipaddr($a_gateway_item['gateway']) &&
	    ($gateway['gateway'] != $a_gateway_item['gateway'])) ||
	    (isset($gateway['force_down']) ^ isset($a_gateway_item['force_down']))))) {
		$reloadvpn = true;
	}

	/* when saving the manual gateway we use the attribute which has the corresponding id */
	if (isset($realid) && $a_gateway_item) {
		$preserve_disabled = isset($a_gateway_item['disabled']);
		$a_gateway_item = $gateway;
		if ($preserve_disabled) {
			$a_gateway_item['disabled'] = true;
		}
		config_set_path("gateways/gateway_item/{$realid}", $a_gateway_item);
	} else {
		config_set_path("gateways/gateway_item/", $gateway);
	}
	gateway_set_enabled($gateway_settings['name'], !isset($gateway_settings['disabled']));

	mark_subsystem_dirty('staticroutes');

	write_config("Gateway settings changed");

	if ($reloadvpn) {
		send_event("service reload ipsec " . escapeshellarg($gateway['name']));
		send_event("service reload openvpn " . escapeshellarg($gateway['name']));
	}

	if (!empty($reloadif)) {
		send_event("interface reconfigure {$reloadif}");
	}
}

function gateway_set_enabled($name, $enabled) {
	$gwlist = config_get_path('gateways/gateway_item', []);
	if (is_array($gwlist)) {
		foreach($gwlist as $key => $item) {
			if ($item['name'] == $name) {
				$gateway = &$gwlist[$key];
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
					$cgw = route_get_default('inet');
				} else {
					$cgw = route_get_default('inet6');
				}
				if ($gateway['gateway'] == $cgw) {
					route_del("default", $inet);
				}
			}
			$gateway['disabled'] = true;
		}
	}
	unset($gateway);
	config_set_path('gateways/gateway_item', $gwlist);
}

function gateway_or_gwgroup_exists($name) {
	foreach(config_get_path('gateways/gateway_item', []) as $item) {
		if ($item['name'] == $name) {
			return true;
		}
	}
	foreach(config_get_path('gateways/gateway_group', []) as $item) {
		if ($item['name'] == $name) {
			return true;
		}
	}
	return false;
}

// These two replacement functions avoid the need to call return_gateways_array() multiple times and
// allow system_gateways.php to get everything it needs in a single function call
function gateway_getgwtiername($gways, $idx) {
	$result = "";
	$gwname = $gways[$idx]['name'];

	$gw = get_gateway_or_group_by_name($gwname, $gways);

	config_init_path('gateways');
	$gw4 = config_get_path('gateways/defaultgw4', "");
	$gw6 = config_get_path('gateways/defaultgw6', "");
	if ($gw4 == $gwname || $gw6 == $gwname) {
		$result = "Default";
	} else {
		if ($gw['ipprotocol'] == 'inet') {
			$defgw = get_gateway_or_group_by_name($gw4, $gways);
		} else {
			$defgw = get_gateway_or_group_by_name($gw6, $gways);
		}

		if ($defgw['type'] == "gatewaygroup") {
			$detail = gateway_is_gwgroup_member($gwname, true);
			foreach($detail as $gwitem) {
				if ($gwitem['name'] == $defgw['name']) {
					if (isset($gwitem['tier'])) {
						$result = "Tier " . $gwitem['tier'];
						break;
					}
				}
			}
		}
    }

	if (!empty($result)) {
		if ($gw['ipprotocol'] == "inet") {
			$result .= " (IPv4)";
		} elseif ($gw['ipprotocol'] == "inet6") {
			$result .= " (IPv6)";
		}
	}

	return $result;
}

function get_gateway_or_group_by_name($gwname, $gateways_arr) {
	foreach ($gateways_arr as $gw) {
		if ($gw['name'] == $gwname) {
			$gw['type'] = 'gateway';
			return $gw;
		}
	}

	config_init_path('gateways/gateway_group');
	foreach (config_get_path('gateways/gateway_group', []) as $gwg) {
		if ($gwg['name'] == $gwname) {
			$gwg['type'] = 'gatewaygroup';
			return $gwg;
		}
	}

	return false;
}

// Compose a list of available gateways but without the need to call return_gateways_array() multiple times
// Previously that function could be called eight times per gateway!
function available_default_gateways() {
	$gways = get_gateways(GW_CACHE_DISABLED | GW_CACHE_INACTIVE);

	$items4 = array();
	$items6 = array();
	$items4[''] = "Automatic";
	$items6[''] = "Automatic";
	foreach($gways as $gw) {
		$gwn = $gw['name'];
		if ($gw['ipprotocol'] == "inet6") {
			$items6[$gwn] = $gwn;
		} else {
			$items4[$gwn] = $gwn;
		}
	}

	$groups = return_gateway_groups_array(false);
	foreach ($groups as $key => $group) {
		$gwn = $group['descr'];
		if ($group['ipprotocol'] == "inet6") {
			$items6[$key] = "$key ($gwn)";
		} else {
			$items4[$key] = "$key ($gwn)";
		}
	}
	$items4['-'] = "None";
	$items6['-'] = "None";

	$defaults = array();
	$defaults['v4'] = $items4;
	$defaults['v6'] = $items6;
	$defaults['defaultgw4'] = config_get_path('gateways/defaultgw4', "");
	$defaults['defaultgw6'] = config_get_path('gateways/defaultgw6', "");

	return $defaults;
}

function get_interface_gateway_name($friendlyiface, $family = 'inet') {
	/* Try to locate gateway directly (e.g. static) */
	if ($family == 'inet') {
		$gw = config_get_path("interfaces/{$friendlyiface}/gateway", '');
	} else {
		$gw = config_get_path("interfaces/{$friendlyiface}/gatewayv6", '');
	}

	/* If the gateway is empty that means it's dynamic so we have to hunt it down */
	if (empty($gw)) {
		/* Check each gateway entry for a dynamic gateway matching
		 * this interface and the requested address family.
		 */
		foreach (get_gateways() as $gwname => $gwcfg) {
			if (empty($gwcfg) ||
			    !is_array($gwcfg) ||
			    ($gwcfg['dynamic'] == false) ||
			    ($gwcfg['friendlyiface'] != $friendlyiface) ||
			    ($gwcfg['ipprotocol'] != $family)) {
				continue;
			}
			$gw = $gwname;
			break;
		}
	}
	return $gw;
}

?>
