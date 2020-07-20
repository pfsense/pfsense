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
		"net.inet.ip.pfil.inbound" => "pf,ipfw",
		"net.inet6.ip6.pfil.inbound" => "pf,ipfw",
		"net.inet.ip.pfil.outbound" => "pf,ipfw",
		"net.inet6.ip6.pfil.outbound" => "pf,ipfw",
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

	$modulesLoaded = FALSE;
	$log = array();
	for($i = 0; $i < $g['nat64_rules_interval']; $i++) {
		$log[] = 'ID = '.$i;
		
		$existingRule = array();
		exec($log[] = '/sbin/ipfw nat64lsn NAT64_'.$i.' show', $existingRule);
		$existingRule = $existingRule[0];
		$log[] = 'existingRule = '.$existingRule;
		
		$newRule = ''; $ip4 = ''; $ip6 = ''; $allowrfc = '';
		if ($i < count($a_nat64) && !isset($a_nat64[$i]['disabled'])) {
			$ip4 = $a_nat64[$i]['prefix4']['address'];
			$ip6 = $a_nat64[$i]['prefix6']['address'];
			$allowrfc = isset($a_nat64[$i]['allowrfc1918']) ? ' allow_private' : '';
			$newRule = 'nat64lsn NAT64_'.$i.' prefix4 '.$ip4.' prefix6 '.$ip6.$allowrfc;
		}
		$log[] = 'newRule = '.$newRule;
		
		if ($existingRule != $newRule) {
			if (!empty($existingRule)) {
				mwexec($log[] = '/sbin/ipfw -q delete '.($g['nat64_rules_start'] + $i));
				mwexec($log[] = '/sbin/ipfw -q nat64lsn NAT64_'.$i.' destroy');
				$log[] = 'removed old NAT64_'.$i;
			}
		
			if (!empty($newRule)) {
				if (!$modulesLoaded)
					nat64_load_modules();
				$modulesLoaded = TRUE;

				mwexec($log[] = '/sbin/ipfw -q nat64lsn NAT64_'.$i.' create prefix4 '.$ip4.' prefix6 '.$ip6.$allowrfc);
				mwexec($log[] = '/sbin/ipfw -q '.($g['nat64_rules_start'] + $i).' add nat64lsn NAT64_'.$i.' ip from any to '.$ip4);
				mwexec($log[] = '/sbin/ipfw -q '.($g['nat64_rules_start'] + $i).' add nat64lsn NAT64_'.$i.' ip6 from any to '.$ip6);
				$log[] = 'added new NAT64_'.$i;
			}
		}
		
		if (empty($existingRule) && $i >= count($a_nat64)) break;
	}
	file_put_contents("{$g['tmp_path']}/rules.nat64", implode("\n", $log)."\n");
}
function filter_remove_nat64_rules() {
	mwexec('/sbin/ipfw -q delete '.$g['nat64_rules_start'].'-'.($g['nat64_rules_start'] + $g['nat64_rules_interval']), true);
}

/* log simple messages to syslog */
function nat64_syslog($message) {
	$message = trim($message);
	openlog("lognat64", LOG_PID, LOG_LOCAL4);
	// Log it
	syslog(LOG_INFO, $message);
	closelog();
}
