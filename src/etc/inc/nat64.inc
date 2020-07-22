<?php
/*
 * nat64.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2020 Nick Whaley
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2006 Manuel Kasper <mk@neon1.net>.
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

require_once("globals.inc");
require_once("functions.inc");
require_once("util.inc");
require_once("notices.inc");

function nat64_load_modules() {
	mute_kernel_msgs();
	
	if (!is_module_loaded("ipfw.ko"))
		mwexec("/sbin/kldload ipfw");
	if (!is_module_loaded("ipfw_nat64.ko"))
		mwexec("/sbin/kldload ipfw_nat64");
	set_sysctl(array(
		//having ipfw enabled in pfil conflicts with capture portal.
		"net.inet.ip.pfil.inbound" => "pf",
		"net.inet6.ip6.pfil.inbound" => "pf",
		"net.inet.ip.pfil.outbound" => "pf",
		"net.inet6.ip6.pfil.outbound" => "pf",
	    "net.link.ether.ipfw" => "1",
	    "net.inet.ip.fw.one_pass" => "1",
	    "net.inet.ip.fw.tables_max" => "65534"
	));

	unmute_kernel_msgs();
}

function filter_generate_nat64_rules() {
	global $config, $g;

	init_config_arr(array('nat', 'nat64', 'rule'));
	$a_nat64 = &$config['nat']['nat64']['rule'];

	if (count($a_nat64) > $g['nat64_rules_interval'])
		nat64_syslog("Out of space for nat64 rules. Some may be ignored.");

	$modulesLoaded = false;
	$anyRule = false;
	for($i = 0; $i < $g['nat64_rules_interval']; $i++) {
		nat64_syslog('ID = '.$i);
		
		$existingRule = array();
		exec('/sbin/ipfw nat64lsn NAT64_'.$i.' show', $existingRule);
		nat64_syslog('command: /sbin/ipfw nat64lsn NAT64_'.$i.' show', $existingRule);
		$existingRule = $existingRule[0];
		//normalizes whitespace
		$existingRule = implode(' ', array_filter(explode(' ', $existingRule)));
		nat64_syslog('existingRule = '.$existingRule);
		
		$newRule = ''; $newRuleOptions = ''; $ip4 = ''; $ip6 = '';
		if ($i < count($a_nat64) && !isset($a_nat64[$i]['disabled'])) {
			$ip4 = $a_nat64[$i]['prefix4']['address'];
			$ip6 = $a_nat64[$i]['prefix6']['address'];
			$allowrfc = isset($a_nat64[$i]['allowrfc1918']) ? ' allow_private' : '';
			$max_ports = isset($a_nat64[$i]['max_ports']) && $a_nat64[$i]['max_ports'] != 2048 ? ' max_ports '.$a_nat64[$i]['max_ports'] : '';
			$host_del_age = isset($a_nat64[$i]['host_del_age']) && $a_nat64[$i]['max_ports'] != 3600 ? ' host_del_age '.$a_nat64[$i]['host_del_age'] : '';
			$pg_del_age = isset($a_nat64[$i]['pg_del_age']) && $a_nat64[$i]['max_ports'] != 900 ? ' pg_del_age '.$a_nat64[$i]['pg_del_age'] : '';
			$tcp_syn_age = isset($a_nat64[$i]['tcp_syn_age']) && $a_nat64[$i]['max_ports'] != 10 ? ' tcp_syn_age '.$a_nat64[$i]['tcp_syn_age'] : '';
			$tcp_close_age = isset($a_nat64[$i]['tcp_close_age']) && $a_nat64[$i]['max_ports'] != 180 ? ' tcp_close_age '.$a_nat64[$i]['tcp_close_age'] : '';
			$tcp_est_age = isset($a_nat64[$i]['tcp_est_age']) && $a_nat64[$i]['max_ports'] != 7200 ? ' tcp_est_age '.$a_nat64[$i]['tcp_est_age'] : '';
			$udp_age = isset($a_nat64[$i]['udp_age']) && $a_nat64[$i]['max_ports'] != 120 ? ' udp_age '.$a_nat64[$i]['udp_age'] : '';
			$icmp_age = isset($a_nat64[$i]['icmp_age']) && $a_nat64[$i]['max_ports'] != 60 ? ' icmp_age '.$a_nat64[$i]['icmp_age'] : '';
			
			$newRuleOptions = 
				' prefix4 '.$ip4.' prefix6 '.$ip6.
				$max_ports.$host_del_age.$pg_del_age.$tcp_syn_age.$tcp_close_age.$tcp_est_age.$udp_age.$icmp_age.
				$allowrfc;
			$newRule = 'nat64lsn NAT64_'.$i.$newRuleOptions;
		}
		nat64_syslog('newRule = '.$newRule);
		
		if ($existingRule != $newRule) {
			if (!empty($existingRule)) {
				mwexec('/sbin/ipfw -q delete '.($g['nat64_rules_start'] + $i));
				nat64_syslog('command: /sbin/ipfw -q delete '.($g['nat64_rules_start'] + $i));
				mwexec('/sbin/ipfw -q nat64lsn NAT64_'.$i.' destroy');
				nat64_syslog('command: /sbin/ipfw -q nat64lsn NAT64_'.$i.' destroy');
				nat64_syslog('removed old NAT64_'.$i);
			}
		
			if (!empty($newRule)) {
				if (!$modulesLoaded)
					nat64_load_modules();
				$modulesLoaded = true;

				mwexec('/sbin/ipfw -q nat64lsn NAT64_'.$i.' create'.$newRuleOptions);
				nat64_syslog('command: /sbin/ipfw -q nat64lsn NAT64_'.$i.' create'.$newRuleOptions);
				mwexec('/sbin/ipfw -q '.($g['nat64_rules_start'] + $i).' add nat64lsn NAT64_'.$i.' ip from any to '.$ip4);
				nat64_syslog('command: /sbin/ipfw -q '.($g['nat64_rules_start'] + $i).' add nat64lsn NAT64_'.$i.' ip from any to '.$ip4);
				mwexec('/sbin/ipfw -q '.($g['nat64_rules_start'] + $i).' add nat64lsn NAT64_'.$i.' ip6 from any to '.$ip6);
				nat64_syslog('command: /sbin/ipfw -q '.($g['nat64_rules_start'] + $i).' add nat64lsn NAT64_'.$i.' ip6 from any to '.$ip6);
				nat64_syslog('added new NAT64_'.$i);
			}
		}
				
		if (!empty($newRule)) $anyRule = true;
		if (empty($existingRule) && $i >= count($a_nat64)) break;
	}
	
	if (!$anyRule) {
		//MUST check if captive port still using ipfw
		if (empty($config['captiveportal'])) {
			set_single_sysctl("net.link.ether.ipfw", "0");
		} else {
			$cpactive = false;
			if (is_array($config['captiveportal'])) {
				foreach ($config['captiveportal'] as $cpkey => $cp) {
					if (isset($cp['enable'])) {
						$cpactive = true;
						break;
					}
				}
			}
			if ($cpactive === false) {
				set_single_sysctl("net.link.ether.ipfw", "0");
			}
		}
	}
}

/* log simple messages to syslog */
function nat64_syslog($message) {
	$message = trim($message);
	openlog("lognat64", LOG_PID, LOG_LOCAL4);
	// Log it
	syslog(LOG_INFO, $message);
	closelog();
}
