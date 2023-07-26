<?php
/*
 * easyrule.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2009-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2023 Rubicon Communications, LLC (Netgate)
 * Originally Sponsored By Anathematic @ pfSense Forums
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

$blockaliasname = 'EasyRuleBlockHosts';
$protocols_with_ports = array('tcp', 'udp');
require_once("functions.inc");
require_once("util.inc");
require_once("ipsec.inc");
require_once("config.inc");

function easyrule_find_rule_interface($int) {
	global $config;
	/* Borrowed from firewall_rules.php */
	$iflist = get_configured_interface_with_descr(true);

	/* Add interface groups */
	foreach (config_get_path('ifgroups/ifgroupentry', []) as $ifgen) {
		$iflist[$ifgen['ifname']] = $ifgen['ifname'];
	}

	if (is_pppoe_server_enabled()) {
		$iflist['pppoe'] = gettext("PPPoE Server");
	}

	if (config_get_path('l2tp/mode') == "server") {
		$iflist['l2tp'] = gettext("L2TP VPN");
	}

	/* Add IPsec tunnel interface */
	if (ipsec_enabled()) {
		$iflist["enc0"] = gettext("IPsec");
	}

	if (count(config_get_path('openvpn/openvpn-server', [])) ||
	    count(config_get_path('openvpn/openvpn-client', []))) {
		$iflist["openvpn"] = gettext("OpenVPN");
	}

	/* Check if the given name matches a known assigned interface id or
	 * common group name */
	if (array_key_exists($int, $iflist)) {
		return $int;
	}

	/* Check if the user passed an interface description name instead of the
	 * internal name. */
	foreach ($iflist as $if => $ifd) {
		if (strtolower($int) == strtolower($ifd)) {
			return $if;
		}
	}

	/* Check for unassigned OpenVPN or IPsec and return the associated
	 * group name. */
	if (substr($int, 0, 4) == "ovpn") {
		return "openvpn";
	}
	if (substr($int, 0, 5) == "ipsec") {
		return "ipsec";
	}

	/* If the user passed a real interface name, attempt to map it to an
	 * assigned interface */
	$iff = convert_real_interface_to_friendly_interface_name($int);
	if (($iff !== NULL) && ($iff != $int)) {
		return $iff;
	}

	return false;
}

function easyrule_block_rule_exists($int = 'wan', $ipproto = "inet") {
	global $blockaliasname, $config;
	/* No rules, we we know it doesn't exist */
	if (empty(config_get_path('filter/rule', []))) {
		return false;
	}

	/* Search through the rules for one referencing our alias */
	foreach (config_get_path('filter/rule', []) as $rule) {
		if (!is_array($rule) || !is_array($rule['source'])) {
			continue;
		}
		$checkproto = isset($rule['ipprotocol']) ? $rule['ipprotocol'] : "inet";
		if ((array_get_path($rule, 'source/address') == $blockaliasname . strtoupper($int)) &&
		    ($rule['interface'] == $int) &&
		    ($checkproto == $ipproto)) {
			return true;
		}
	}
	return false;
}

function easyrule_block_rule_create($int = 'wan', $ipproto = "inet") {
	global $blockaliasname, $config;
	/* If the alias doesn't exist, exit.
	 * Can't create an empty alias, and we don't know a host */
	if (easyrule_block_alias_getid($int) === false) {
		return "noalias";
	}

	/* If the rule already exists, no need to do it again */
	if (easyrule_block_rule_exists($int, $ipproto)) {
		return true;
	}

	filter_rules_sort();
	$a_filter = config_get_path('filter/rule', []);

	/* Make up a new rule */
	$filterent = array();
	$filterent['type'] = 'block';
	$filterent['interface'] = $int;
	$filterent['ipprotocol'] = $ipproto;
	$filterent['source'] = [];
	$filterent['source']['address'] = $blockaliasname . strtoupper($int);
	$filterent['destination'] = [];
	$filterent['destination']['any'] = '';
	$filterent['descr'] = gettext("Blocked via EasyRule");
	$filterent['created'] = make_config_revision_entry(null, "EasyRule");
	$filterent['tracker'] = (int)microtime(true);

	// Refer to firewall_rules_edit.php separators updating code.
	// Using same code, variables, and techniques here.
	$after = -1;	// Place rule at top and move all separators.
	array_splice($a_filter, $after+1, 0, array($filterent));
	config_set_path('filter/rule', $a_filter);

	$tmpif = $int;

	// Update the separators
	$a_separators = config_get_path('filter/separator/' . strtolower($tmpif), []);
	$ridx = abs(ifridx($tmpif, $after));	// get rule index within interface
	$mvnrows = +1;
	move_separators($a_separators, $ridx, $mvnrows);
	config_set_path('filter/separator/' . strtolower($tmpif), $a_separators);

	return true;
}

function easyrule_block_alias_getid($int = 'wan') {
	global $blockaliasname;

	/* Hunt down an alias with the name we want, return its id */
	foreach (config_get_path('aliases/alias', []) as $aliasid => $alias) {
		if ($alias['name'] == $blockaliasname . strtoupper($int)) {
			return $aliasid;
		}
	}

	return false;
}

function easyrule_block_alias_add($host, $int = 'wan') {
	global $blockaliasname, $config;
	/* If the host isn't a valid IP address, bail */
	$host = trim($host, "[]");
	if (!is_ipaddr($host) && !is_subnet($host)) {
		return "invalid";
	}

	$a_aliases = config_get_path('aliases/alias', []);

	/* Try to get the ID if the alias already exists */
	$id = easyrule_block_alias_getid($int);
	if ($id === false) {
		unset($id);
	}

	$alias = array();

	if (is_subnet($host)) {
		list($host, $mask) = explode("/", $host);
	} elseif (get_specialnet($host, [SPECIALNET_ANY, SPECIALNET_SELF, SPECIALNET_CLIENTS])) {
		$mask = 0;
	} elseif (is_ipaddrv6($host)) {
		$mask = 128;
	} else {
		$mask = 32;
	}

	if (isset($id) &&
	    array_key_exists($id, $a_aliases) &&
	    is_array($a_aliases[$id])) {

		// Catch case when the list is empty
		if (empty(array_get_path($a_aliases, "{$id}/address", ""))) {
			$a_address = array();
			$a_detail = array();
		} else {
			$a_address = explode(" ", array_get_path($a_aliases, "{$id}/address", ""));

			/* Make sure this IP address isn't already in the list. */
			if (in_array($host.'/'.$mask, $a_address)) {
				return "exists";
			}
			$a_detail = explode("||", array_get_path($a_aliases, "{$id}/detail"));
		}

		/* Since the alias already exists, just add to it. */
		$alias['name']    = array_get_path($a_aliases, "{$id}/name");
		$alias['type']    = array_get_path($a_aliases, "{$id}/type");
		$alias['descr']   = array_get_path($a_aliases, "{$id}/descr");

		$a_address[] = $host.'/'.$mask;
		$a_detail[] = gettext('Entry added') . ' ' . date('r');

		$alias['address'] = join(" ", $a_address);
		$alias['detail']  = join("||", $a_detail);

	} else {
		/* Create a new alias with all the proper information */
		$alias['name']    = $blockaliasname . strtoupper($int);
		$alias['type']    = 'network';
		$alias['descr']   = gettext("Blocked via EasyRule");

		$alias['address'] = $host . '/' . $mask;
		$alias['detail']  = gettext('Entry added') . ' ' . date('r') . '||';
	}

	/* Replace the old alias if needed, otherwise tack it on the end */
	if (isset($id) && $a_aliases[$id]) {
		$a_aliases[$id] = $alias;
	} else {
		$a_aliases[] = $alias;
	}

	// Sort list
	$a_aliases = msort($a_aliases, "name");

	config_set_path('aliases/alias', $a_aliases);
	return true;
}

function easyrule_block_host_add($host, $int = 'wan') {
	global $retval;
	/* Bail if the supplied host is not a valid IP address */
	$host = trim($host, "[]");
	if (!is_ipaddr($host) && !is_subnet($host)) {
		return "invalid";
	}

	if (is_v6($host)) {
		$ipproto = 'inet6';
	} else {
		$ipproto = 'inet';
	}

	/* Flag whether or not we need to reload the filter */
	$dirty = false;

	/* Attempt to add this host to the alias */
	$alias_add_result = easyrule_block_alias_add($host, $int);
	if ($alias_add_result === true) {
		$dirty = true;
	} else {
		/* Couldn't add the alias, or adding the host failed. */
		return $alias_add_result;
	}

	/* Attempt to add the firewall rule if it doesn't exist.
	 * Failing to add the rule isn't necessarily an error, it may
	 * have been modified by the user in some way. Adding to the
	 * Alias is what's important.
	 */
	if (!easyrule_block_rule_exists($int, $ipproto)) {
		$rule_create_result = easyrule_block_rule_create($int, $ipproto);
		if ($rule_create_result === true) {
			$dirty = true;
		} else {
			return $rule_create_result;
		}
	}

	/* If needed, write the config and reload the filter */
	if ($dirty) {
		write_config(sprintf(gettext("Blocked %s via EasyRule"), $host));
		$retval = filter_configure();
		if (!empty($_SERVER['DOCUMENT_ROOT'])) {
			header("Location: firewall_aliases.php");
			exit;
		} else {
			return true;
		}
	} else {
		return false;
	}
}

function easyrule_pass_rule_add($int, $proto, $srchost, $dsthost, $dstport, $ipproto) {
	global $config;

	init_config_arr(array('filter', 'rule'));
	filter_rules_sort();
	$a_filter = config_get_path('filter/rule', []);

	/* Make up a new rule */
	$filterent = array();
	$filterent['type'] = 'pass';
	$filterent['interface'] = $int;
	$filterent['ipprotocol'] = $ipproto;
	$filterent['descr'] = gettext("Passed via EasyRule");

	if ($proto != "any") {
		$filterent['protocol'] = $proto;
	} else {
		unset($filterent['protocol']);
	}

	if ((strtolower($proto) == "icmp6") || (strtolower($proto) == "icmpv6")) {
		$filterent['protocol'] = "icmp";
	}

	/* Default to only allow echo requests, since that's what most people want and
	 *  it should be a safe choice. */
	if ($proto == "icmp") {
		$filterent['icmptype'] = 'echoreq';
	}

	if (is_subnet($srchost)) {
		list($srchost, $srcmask) = explode("/", $srchost);
	} elseif (get_specialnet($srchost, [SPECIALNET_ANY, SPECIALNET_SELF, SPECIALNET_CLIENTS])) {
		$srcmask = 0;
	} elseif (is_ipaddrv6($srchost)) {
		$srcmask = 128;
	} else {
		$srcmask = 32;
	}

	if (is_subnet($dsthost)) {
		list($dsthost, $dstmask) = explode("/", $dsthost);
	} elseif (get_specialnet($dsthost, [SPECIALNET_ANY, SPECIALNET_SELF, SPECIALNET_CLIENTS])) {
		$dstmask = 0;
	} elseif (is_ipaddrv6($dsthost)) {
		$dstmask = 128;
	} else {
		$dstmask = 32;
	}

	pconfig_to_address($filterent['source'], $srchost, $srcmask);
	pconfig_to_address($filterent['destination'], $dsthost, $dstmask, '', $dstport, $dstport);

	$filterent['created'] = make_config_revision_entry(null, "EasyRule");
	$filterent['tracker'] = (int)microtime(true);
	$a_filter[] = $filterent;
	config_set_path('filter/rule', $a_filter);

	write_config($filterent['descr']);
	$retval = filter_configure();
	if (!empty($_SERVER['DOCUMENT_ROOT'])) {
		header("Location: firewall_rules.php?if={$int}");
		exit;
	} else {
		return true;
	}
}

function easyrule_parse_block($int, $src) {
	if (!empty($src) && !empty($int)) {
		$src = trim($src, "[]");
		if (!is_ipaddr($src) && !is_subnet($src)) {
			return gettext("Tried to block invalid address:") . ' ' . htmlspecialchars($src);
		}
		$int = easyrule_find_rule_interface($int);
		if ($int === false) {
			return gettext("Invalid interface for block rule.");
		}
		switch ((string)easyrule_block_host_add($src, $int)) {
			case "exists":
				return gettext("Block entry already exists.");
				break;
			case "invalid":
				return gettext("Invalid address.");
				break;
			case "1":
				return gettext("Block added successfully");
				break;
			case "":
			default:
				return gettext("Failed to create block rule, alias, or add entry.");
				break;
		}
	} else {
		return gettext("Tried to block but had no address or interface");
	}
	return gettext("Unknown block error.");
}

function easyrule_parse_unblock($int, $host) {
	global $blockaliasname, $config;

	if (!empty($host) && !empty($int)) {
		$host = trim($host, "[]");
		if (!is_ipaddr($host) && !is_subnet($host)) {
			return gettext("Tried to unblock invalid address:") . ' ' . htmlspecialchars($host);
		}
		$real_int = easyrule_find_rule_interface($int);
		if ($real_int === false) {
			return gettext("Invalid interface for block rule:") . ' ' . htmlspecialchars($int);
		}

		/* Try to get the ID - will fail if there are no rules/alias on this interface */
		$id = easyrule_block_alias_getid($real_int);
		if ($id === false ||
		    empty(config_get_path("aliases/alias/{$id}", [])) ||
		    empty(config_get_path("aliases/alias/{$id}")['address'])) {
			return gettext("No entries are blocked on interface:") . ' ' . htmlspecialchars($int);
		}

		$alias = config_get_path("aliases/alias/{$id}", []);

		if (is_subnet($host)) {
			list($host, $mask) = explode("/", $host);
		} elseif (get_specialnet($host, [SPECIALNET_ANY, SPECIALNET_SELF, SPECIALNET_CLIENTS])) {
			$mask = 0;
		} elseif (is_ipaddrv6($host)) {
			$mask = 128;
		} else {
			$mask = 32;
		}

		// Create the expected string representation
		$unblock = $host.'/'.$mask;

		$a_address = explode(" ", $alias['address']);
		$a_detail = explode("||", $alias['detail']);

		if (($key = array_search($unblock, $a_address)) !== false) {
			unset($a_address[$key]);
			unset($a_detail[$key]);
			// Write back the result to the config array
			$alias['address'] = join(" ", $a_address);
			$alias['detail'] = join("||", $a_detail);
			config_set_path("aliases/alias/{$id}", $alias);

			// Update config
			write_config(sprintf(gettext("Unblocked %s via EasyRule"), $host));
			$retval = filter_configure();
			if (!empty($_SERVER['DOCUMENT_ROOT'])) {
				header("Location: firewall_aliases.php");
				exit;
			} else {
				return gettext("Entry unblocked successfully");
			}
		} else {
			return gettext("Entry is not on block list: " . $host);
		}
	}

	return gettext("Tried to unblock but had no address or interface");

}

function easyrule_parse_getblock($int = 'wan', $sep = "\n") {
	global $blockaliasname, $config;

	$real_int = easyrule_find_rule_interface($int);
	if ($real_int === false) {
		return gettext("Invalid interface for block rule:") . ' ' . htmlspecialchars($int);
	}

	/* Try to get the ID - will fail if there are no rules/alias on this interface */
	$id = easyrule_block_alias_getid($real_int);

	if ($id === false ||
	    empty(config_get_path("aliases/alias/{$id}", [])) ||
	    empty(config_get_path("aliases/alias/{$id}")['address'])) {
		return gettext("No entries are blocked on interface:") . ' ' . htmlspecialchars($int);
	}
	return join($sep, explode(" ", config_get_path("aliases/alias/{$id}")['address']));
}

function easyrule_parse_pass($int, $proto, $src, $dst, $dstport = 0, $ipproto = "inet") {
	/* Check for valid int, srchost, dsthost, dstport, and proto */
	global $protocols_with_ports;
	$src = trim($src, "[]");
	$dst = trim($dst, "[]");

	if (!empty($int) && !empty($proto) && !empty($src) && !empty($dst)) {
		$int = easyrule_find_rule_interface($int);
		if ($int === false) {
			return gettext("Invalid interface for pass rule:") . ' ' . htmlspecialchars($int);
		}
		if ((strtolower($proto) == "icmp6") || (strtolower($proto) == "icmpv6")) {
			$proto = "icmp";
		}
		if (($proto != 'any') &&
		    (getprotobyname($proto) === false) &&
		    (!is_numericint($proto) || (getprotobynumber($proto) === false))) {
			return gettext("Invalid protocol for pass rule:") . ' ' . htmlspecialchars($proto);
		}
		if (!is_ipaddr($src) && !is_subnet($src) && !is_ipaddroralias($src) && !get_specialnet($src, [SPECIALNET_ANY, SPECIALNET_SELF, SPECIALNET_CLIENTS])) {
			return gettext("Tried to pass invalid source IP address:") . ' ' . htmlspecialchars($src);
		}
		if (!is_ipaddr($dst) && !is_subnet($dst) && !is_ipaddroralias($dst) && !get_specialnet($dst, [SPECIALNET_ANY, SPECIALNET_SELF, SPECIALNET_CLIENTS])) {
			return gettext("Tried to pass invalid destination IP address:") . ' ' . htmlspecialchars($dst);
		}
		if ((is_v6($src) && is_v4($dst)) || (is_v4($src) && is_v6($dst))) {
			return gettext("The source IP address family has to match the family of the destination IP address.");
		}
		if (is_v6($src)) {
			$ipproto = 'inet6';
		} else {
			$ipproto = 'inet';
		}
		/* If the protocol is by number, change it to a name */
		if (($proto != 'any') &&
		    (getprotobyname($proto) === false)) {
			$proto = getprotobynumber($proto);
		}
		if (in_array($proto, $protocols_with_ports)) {
			if (empty($dstport)) {
				return gettext("Missing destination port.");
			}
			if (!is_port($dstport) && ($dstport != "any")) {
				return gettext("Tried to pass invalid destination port:") . ' ' . htmlspecialchars($dstport);
			}
		} else {
			$dstport = 0;
		}
		/* Should have valid input... */
		if (easyrule_pass_rule_add($int, $proto, $src, $dst, $dstport, $ipproto)) {
			return gettext("Successfully added pass rule!");
		} else {
			return gettext("Failed to add pass rule.");
		}
	} else {
		return gettext("Missing parameters for pass rule.");
	}
	return gettext("Unknown pass error.");
}

?>
