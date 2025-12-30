<?php
/*
 * diag_dump_states.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Colin Smith
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

require_once("interfaces.inc");
require_once("config.gui.inc");
require_once('/etc/inc/interfaces_fast.inc');

function get_ip($addr) {
	$parts = explode(":", $addr);
	if (count($parts) == 2) {
		return (trim($parts[0]));
	} else {
		/* IPv6 */
		$parts = explode("[", $addr);
		if (count($parts) == 2) {
			return (trim($parts[0]));
		}
	}

	return ("");
}

// Process web request and return an array of filtered states
function process_state_req($post, $request, $json = false) {
	$arr = array();
	/* RuleId filter. */
	if (isset($post['ruleid']) && strlen($post['ruleid']) > 0) {
		$ids = explode(",", $post['ruleid']);
		for ($i = 0; $i < count($ids); $i++) {
			$arr[] = array("ruleid" => intval($ids[$i]));
		}
	}

	/* Interface filter. */
	$interface_selected = null;
	if (isset($post['interface']) && $post['interface'] != "all") {
		$interface_selected = get_real_interface($post['interface']);
		$arr[] = array("interface" => $interface_selected);
	}

	if (isset($post['filter']) && strlen($post['filter']) > 0) {
		$arr[] = array("filter" => $post['filter']);
	}

	if (isset($post['filter']) || isset($post['ruleid']) ||
	    !config_path_enabled('system/webgui', 'requirestatefilter')) {
		if (count($arr) > 0) {
			$res = pfSense_get_pf_states($arr);
		} else {
			$res = pfSense_get_pf_states();
		}
	} else {
		$res = NULL;
	}

	$statedisp = array();

	if (is_array($res) && count($res)) {
		$interface_names = convert_real_interface_to_friendly_interface_name_fast();
		$interface_descriptions = get_configured_interface_with_descr(true);
		$interfaces = [];
		foreach ($interface_names as $port => $name) {
			$interfaces[$port] = $interface_descriptions[$name];
		}

		foreach ($res as $state) {
			if (isset($interface_selected) && ($state['if'] !== $interface_selected)) {
				continue;
			}
			$statedisp[] = [
				'interface' => $interfaces[$state['if']] ?? $state['if'],
				'proto' => $state['proto'],
				'display' => $state['src'] . (!empty($state['src-orig']) ? " ({$state['src-orig']})" : '') . ' -> ' . $state['dst'] . (!empty($state['dst-orig']) ? " ({$state['dst-orig']})" : ''),
				'srcip' => get_ip($state['src']),
				'dstip' => get_ip($state['dst']),
				'state' => $state['state'],
				'packets' => format_number($state['packets in']) . ' / ' . format_number($state['packets out']),
				'bytes' => format_bytes($state['bytes in']) . ' / ' . format_bytes($state['bytes out'])
			];
		}
	}

	return $json ? json_encode($statedisp) : $statedisp;
}
?>