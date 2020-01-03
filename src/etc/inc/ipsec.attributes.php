<?php
/*
 * ipsec.attributes.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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

if (empty($common_name)) {
	$common_name = getenv("common_name");
	if (empty($common_name)) {
		$common_name = getenv("username");
	}
}

function cisco_to_cidr($addr) {
	if (!is_ipaddr($addr)) {
		return 0;
	}
	$mask = decbin(~ip2long($addr));
	$mask = substr($mask, -32);
	$k = 0;
	for ($i = 0; $i <= 32; $i++) {
		$k += intval($mask[$i]);
	}
	return $k;
}

function cisco_extract_index($prule) {

	$index = explode("#", $prule);
	if (is_numeric($index[1])) {
		return intval($index[1]);
	} else {
		syslog(LOG_WARNING, "Error parsing rule {$prule}: Could not extract index");
	}
	return -1;;
}

function parse_cisco_acl($attribs) {
	global $attributes;
	if (!is_array($attribs)) {
		return "";
	}

	$devname = "enc0";
	$finalrules = "";
	if (is_array($attribs['ciscoavpair'])) {
		$inrules = array();
		$outrules = array();
		foreach ($attribs['ciscoavpair'] as $avrules) {
			$rule = explode("=", $avrules);
			$dir = "";
			if (strstr($rule[0], "inacl")) {
				$dir = "in";
			} else if (strstr($rule[0], "outacl")) {
				$dir = "out";
			} else if (strstr($rule[0], "dns-servers")) {
				$attributes['dns-servers'] = explode(" ", $rule[1]);
				continue;
			} else if (strstr($rule[0], "route")) {
				if (!is_array($attributes['routes'])) {
					$attributes['routes'] = array();
				}
				$attributes['routes'][] = $rule[1];
				continue;
			}
			$rindex = cisco_extract_index($rule[0]);
			if ($rindex < 0) {
				continue;
			}

			$rule = $rule[1];
			$rule = explode(" ", $rule);
			$tmprule = "";
			$index = 0;
			$isblock = false;
			if ($rule[$index] == "permit") {
				$tmprule = "pass {$dir} quick on {$devname} ";
			} else if ($rule[$index] == "deny") {
				//continue;
				$isblock = true;
				$tmprule = "block {$dir} quick on {$devname} ";
			} else {
				continue;
			}

			$index++;

			switch ($rule[$index]) {
				case "tcp":
				case "udp":
					$tmprule .= "proto {$rule[$index]} ";
					break;
			}

			$index++;
			/* Source */
			if (trim($rule[$index]) == "host") {
				$index++;
				$tmprule .= "from {$rule[$index]} ";
				$index++;
				if ($isblock == true) {
					$isblock = false;
				}
			} else if (trim($rule[$index]) == "any") {
				$tmprule .= "from any ";
				$index++;
			} else {
				$tmprule .= "from {$rule[$index]} ";
				$index++;
				$netmask = cisco_to_cidr($rule[$index]);
				$tmprule .= "/{$netmask} ";
				$index++;
				if ($isblock == true) {
					$isblock = false;
				}
			}
			/* Destination */
			if (trim($rule[$index]) == "host") {
				$index++;
				$tmprule .= "to {$rule[$index]} ";
				$index++;
				if ($isblock == true) {
					$isblock = false;
				}
			} else if (trim($rule[$index]) == "any") {
				$index++;
				$tmprule .= "to any";
			} else {
				$tmprule .= "to {$rule[$index]}";
				$index++;
				$netmask = cisco_to_cidr($rule[$index]);
				$tmprule .= "/{$netmask} ";
				$index++;
				if ($isblock == true) {
					$isblock = false;
				}
			}

			if ($isblock == true) {
				continue;
			}

			if ($dir == "in") {
				$inrules[$rindex] = $tmprule;
			} else if ($dir == "out") {
				$outrules[$rindex] = $tmprule;
			}
		}


		$state = "";
		if (!empty($outrules)) {
			$state = "no state";
		}
		ksort($inrules, SORT_NUMERIC);
		foreach ($inrules as $inrule) {
			$finalrules .= "{$inrule} {$state}\n";
		}
		if (!empty($outrules)) {
			ksort($outrules, SORT_NUMERIC);
			foreach ($outrules as $outrule) {
				$finalrules .= "{$outrule} {$state}\n";
			}
		}
	}
	return $finalrules;
}

$rules = parse_cisco_acl($attributes);
if (!empty($rules)) {
	$pid = posix_getpid();
	@file_put_contents("/tmp/ipsec_{$pid}{$common_name}.rules", $rules);
	mwexec("/sbin/pfctl -a " . escapeshellarg("ipsec/{$common_name}") . " -f {$g['tmp_path']}/ipsec_{$pid}" . escapeshellarg($common_name) . ".rules");
	@unlink("{$g['tmp_path']}/ipsec_{$pid}{$common_name}.rules");
}

?>
