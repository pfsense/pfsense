<?php
/*
 * openvpn.attributes.php
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

global $username, $dev, $untrusted_port;

if (empty($dev)) {
	$dev = "openvpn";
}

function cisco_to_cidr($addr) {
	if (!is_ipaddr($addr)) {
		throw new Exception('Invalid IP Addr');
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

function parse_cisco_acl_rule($rule, $devname, $dir, $proto) {
	$rule_orig = $rule;
	$rule = explode(" ", $rule);
	$tmprule = "";
	$index = 0;

	if ($rule[$index] == "permit") {
		$startrule = "pass {$dir} quick on {$devname} ";
	} else if ($rule[$index] == "deny") {
		$startrule = "block {$dir} quick on {$devname} ";
	} else {
		return;
	}

	$index++;

	switch ($rule[$index]) {
		case "ip":
			break;
		case "icmp":
			$icmp = ($proto == "inet") ? "icmp" : "ipv6-icmp";
			$tmprule .= "proto {$icmp} ";
			break;
		case "tcp":
		case "udp":
			$tmprule .= "proto {$rule[$index]} ";
			break;
		default:
			syslog(LOG_WARNING, "Error parsing rule {$rule_orig}: Invalid protocol.");
			return;
	}
	$index++;

	/* Source */
	if (trim($rule[$index]) == "host") {
		$index++;
		if ((is_ipaddrv4(trim($rule[$index])) && ($proto == "inet")) ||
		    (is_ipaddrv6(trim($rule[$index])) && ($proto == "inet6"))) {
			$tmprule .= "from {$rule[$index]} ";
			$index++;
		} else {
			syslog(LOG_WARNING, "Error parsing rule {$rule_orig}: Invalid source host '{$rule[$index]}'.");
			return;
		}
	} elseif (is_subnetv6(trim($rule[$index])) && ($proto == "inet6")) {
		$tmprule .= "from {$rule[$index]} ";
		$index++;
	} elseif (trim($rule[$index]) == "any") {
		$tmprule .= "from any ";
		$index++;
	} else {
		$network = $rule[$index];
		$netmask = $rule[++$index];

		if (is_ipaddrv4($network) && ($proto == "inet")) {
			try {
				$netmask = cisco_to_cidr($netmask);
			} catch(Exception $e) {
				syslog(LOG_WARNING, "Error parsing rule {$rule_orig}: Invalid source netmask '$netmask'.");
				return;
			}
			$tmprule .= "from {$network}/{$netmask}";
		} else {
			syslog(LOG_WARNING, "Error parsing rule {$rule_orig}: Invalid source network '$network'.");
			return;
		}

		$index++;
	}

	/* Source Operator */
	if (in_array(trim($rule[$index]), array("lt", "gt", "eq", "neq"))) {
		switch(trim($rule[$index])) {
			case "lt":
				$operator = "<";
				break;
			case "gt":
				$operator = ">";
				break;
			case "eq":
				$operator = "=";
				break;
			case "neq":
				$operator = "!=";
				break;
		}

		$port = $rule[++$index];
		if (is_port($port)) {
			$tmprule .= "port {$operator} {$port} ";
		} else {
			syslog(LOG_WARNING, "Error parsing rule {$rule_orig}: Invalid source port: '$port' not a numeric value between 0 and 65535.");
			return;
		}
		$index++;
	} else if (trim($rule[$index]) == "range") {
		$port = array($rule[++$index], $rule[++$index]);
		if (is_port($port[0]) && is_port($port[1])) {
			$port[0]--;
			$port[1]++;
			$tmprule .= "port {$port[0]} >< {$port[1]} ";
		} else {
			syslog(LOG_WARNING, "Error parsing rule {$rule_orig}: Invalid source ports: '$port[0]' & '$port[1]' one or both are not a numeric value between 0 and 65535.");
			return;
		}
		$index++;
	}

	/* Destination */
	if (trim($rule[$index]) == "host") {
		$index++;
		if ((is_ipaddrv4(trim($rule[$index])) && ($proto == "inet")) ||
		    (is_ipaddrv6(trim($rule[$index])) && ($proto == "inet6"))) {
			$tmprule .= "to {$rule[$index]} ";
			$index++;
		} else {
			syslog(LOG_WARNING, "Error parsing rule {$rule_orig}: Invalid destination host '{$rule[$index]}'.");
			return;
		}
	} elseif (is_subnetv6(trim($rule[$index])) && ($proto == "inet6")) {
		$tmprule .= "to {$rule[$index]} ";
		$index++;
	} elseif (trim($rule[$index]) == "any") {
		$tmprule .= "to any ";
		$index++;
	} else {
		$network = $rule[$index];
		$netmask = $rule[++$index];

		if (is_ipaddrv4($network) && ($proto == "inet")) {
			try {
				$netmask = cisco_to_cidr($netmask);
			} catch(Exception $e) {
				syslog(LOG_WARNING, "Error parsing rule {$rule_orig}: Invalid destination network '$network'.");
				return;
			}
			$tmprule .= "to {$network}/{$netmask}";
		} else {
			syslog(LOG_WARNING, "Error parsing rule {$rule_orig}: Invalid destination network '$network'.");
			return;
		}

		$index++;
	}

	/* Destination Operator */
	if (in_array(trim($rule[$index]), array("lt", "gt", "eq", "neq"))) {
		switch(trim($rule[$index])) {
			case "lt":
				$operator = "<";
				break;
			case "gt":
				$operator = ">";
				break;
			case "eq":
				$operator = "=";
				break;
			case "neq":
				$operator = "!=";
				break;
		}

		$port = $rule[++$index];
		if (is_port($port)) {
			$tmprule .= "port {$operator} {$port} ";
		} else {
			syslog(LOG_WARNING, "Error parsing rule {$rule_orig}: Invalid destination port: '$port' not a numeric value between 0 and 65535.");
			return;
		}
		$index++;
	} else if (trim($rule[$index]) == "range") {
		$port = array($rule[++$index], $rule[++$index]);
		if (is_port($port[0]) && is_port($port[1])) {
			$port[0]--;
			$port[1]++;
			$tmprule .= "port {$port[0]} >< {$port[1]} ";
		} else {
			syslog(LOG_WARNING, "Error parsing rule {$rule_orig}: Invalid destination ports: '$port[0]' '$port[1]' one or both are not a numeric value between 0 and 65535.");
			return;
		}
		$index++;
	}

	$tmprule = $startrule . $proto . " " . $tmprule;
	return $tmprule;
}

function parse_cisco_acl($attribs) {
	global $dev, $attributes;
	if (!is_array($attribs)) {
		return "";
	}
	$finalrules = "";
	if (is_array($attribs['ciscoavpair'])) {
		$inrules = array('inet' => array(), 'inet6' => array());
		$outrules = array('inet' => array(), 'inet6' => array());
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

			if (strstr($rule[0], "ipv6")) {
				$proto = "inet6";
			} else {
				$proto = "inet";
			}

			$tmprule = parse_cisco_acl_rule($rule[1], $dev, $dir, $proto);

			if ($dir == "in") {
				$inrules[$proto][$rindex] = $tmprule;
			} else if ($dir == "out") {
				$outrules[$proto][$rindex] = $tmprule;
			}
		}


		$state = "";
		foreach (array('inet', 'inet6') as $ip) {
			if (!empty($outrules[$ip])) {
				$state = "no state";
			}
			ksort($inrules[$ip], SORT_NUMERIC);
			foreach ($inrules[$ip] as $inrule) {
				$finalrules .= "{$inrule} {$state}\n";
			}
			if (!empty($outrules[$ip])) {
				ksort($outrules[$ip], SORT_NUMERIC);
				foreach ($outrules[$ip] as $outrule) {
					$finalrules .= "{$outrule} {$state}\n";
				}
			}
		}
	}
	return $finalrules;
}

$rules = parse_cisco_acl($attributes);
if (!empty($rules)) {
	$filename = "{$g['tmp_path']}/ovpn_{$dev}_{$username}_{$untrusted_port}.rules";
	@file_put_contents($filename, $rules);
}

?>
