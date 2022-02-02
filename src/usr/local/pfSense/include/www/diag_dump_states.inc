<?php
/*
 * diag_dump_states.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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
	global $config;

	$arr = array();
	/* RuleId filter. */
	if (isset($request['ruleid'])) {
		$ids = explode(",", $request['ruleid']);
		for ($i = 0; $i < count($ids); $i++) {
			$arr[] = array("ruleid" => intval($ids[$i]));
		}
	}

	/* Interface filter. */
	if (isset($post['interface']) && $post['interface'] != "all") {
		$arr[] = array("interface" => get_real_interface($post['interface']));
	}

	if (isset($post['filter']) && strlen($post['filter']) > 0) {
		$arr[] = array("filter" => $post['filter']);
	}

	if (isset($post['filter']) || isset($request['ruleid']) ||
	    !isset($config['system']['webgui']['requirestatefilter'])) {
		if (count($arr) > 0) {
			$res = pfSense_get_pf_states($arr);
		} else {
			$res = pfSense_get_pf_states();
		}
	} else {
		$res = NULL;
	}

	$states = 0;
	if ($res != NULL && is_array($res)) {
		$states = count($res);
	}

	$statedisp = array();

	for ($i = 0; $i < $states; $i++):
		$info = $res[$i]['src'];
		$srcip = get_ip($res[$i]['src']);
		$dstip = get_ip($res[$i]['dst']);
		if ($res[$i]['src-orig']) {
			$info .= " (" . $res[$i]['src-orig'] . ")";
		}
		$info .= " -> ";
		$info .= $res[$i]['dst'];
		if ($res[$i]['dst-orig']) {
			$info .= " (" . $res[$i]['dst-orig'] . ")";
			$killdstip = get_ip($res[$i]['dst-orig']);
		} else {
			$killdstip = $dstip;
		}

		$statedisp[$i]['interface'] = convert_real_interface_to_friendly_descr($res[$i]['if']);
		$statedisp[$i]['proto'] = $res[$i]['proto'];
		$statedisp[$i]['display'] = $info;
		$statedisp[$i]['srcip'] = $srcip;
		$statedisp[$i]['dstip'] =  $dstip;
		$statedisp[$i]['state'] = $res[$i]['state'];
		$statedisp[$i]['packets'] = format_number($res[$i]['packets in']) . ' / ' . format_number($res[$i]['packets out']);
		$statedisp[$i]['bytes'] = format_bytes($res[$i]['bytes in']) . ' / ' . format_bytes($res[$i]['bytes out']);

	endfor;

	return $json ? json_encode($statedisp) : $statedisp;
}
?>