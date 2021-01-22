<?php
/*
 * easyrule.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2009-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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

	if ($config['pppoe']['mode'] == "server") {
		$iflist['pppoe'] = "PPPoE Server";
	}

	if ($config['l2tp']['mode'] == "server") {
		$iflist['l2tp'] = "L2TP VPN";
	}

	/* add ipsec interfaces */
	if (ipsec_enabled()) {
		$iflist["enc0"] = "IPSEC";
	}

	if (isset($iflist[$int])) {
		return $int;
	}

	foreach ($iflist as $if => $ifd) {
		if (strtolower($int) == strtolower($ifd)) {
			return $if;
		}
	}

	if (substr($int, 0, 4) == "ovpn") {
		return "openvpn";
	}
	if (substr($int, 0, 5) == "ipsec") {
		return "ipsec";
	}
	if (substr($int, 0, 2) == "wg") {
		return "wireguard";
	}

	return false;
}

function easyrule_block_rule_exists($int = 'wan', $ipproto = "inet") {
	global $blockaliasname, $config;
	/* No rules, we we know it doesn't exist */
	if (!is_array($config['filter']['rule'])) {
		return false;
	}

	/* Search through the rules for one referencing our alias */
	foreach ($config['filter']['rule'] as $rule) {
		if (!is_array($rule) || !is_array($rule['source'])) {
			continue;
		}
		$checkproto = isset($rule['ipprotocol']) ? $rule['ipprotocol'] : "inet";
		if ($rule['source']['address'] == $blockaliasname . strtoupper($int) && ($rule['interface'] == $int) && ($checkproto == $ipproto)) {
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
		return false;
	}

	/* If the rule already exists, no need to do it again */
	if (easyrule_block_rule_exists($int, $ipproto)) {
		return true;
	}

	init_config_arr(array('filter', 'rule'));
	filter_rules_sort();
	$a_filter = &$config['filter']['rule'];

	/* Make up a new rule */
	$filterent = array();
	$filterent['type'] = 'block';
	$filterent['interface'] = $int;
	$filterent['ipprotocol'] = $ipproto;
	$filterent['source']['address'] = $blockaliasname . strtoupper($int);
	$filterent['destination']['any'] = '';
	$filterent['descr'] = gettext("Easy Rule: Blocked from Firewall Log View");
	$filterent['created'] = make_config_revision_entry(null, "Easy Rule");
	$filterent['tracker'] = (int)microtime(true);

	// Refer to firewall_rules_edit.php separators updating code.
	// Using same code, variables, and techniques here.
	$after = -1;	// Place rule at top and move all separators.
	array_splice($a_filter, $after+1, 0, array($filterent));

	$tmpif = $int;

	// Update the separators
	init_config_arr(array('filter', 'separator', strtolower($tmpif)));
	$a_separators = &$config['filter']['separator'][strtolower($tmpif)];
	$ridx = ifridx($tmpif, $after);	// get rule index within interface
	$mvnrows = +1;
	move_separators($a_separators, $ridx, $mvnrows);

	return true;
}

function easyrule_block_alias_getid($int = 'wan') {
	global $blockaliasname, $config;
	if (!is_array($config['aliases'])) {
		return false;
	}

	/* Hunt down an alias with the name we want, return its id */
	foreach ($config['aliases']['alias'] as $aliasid => $alias) {
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
		return false;
	}

	init_config_arr(array('aliases', 'alias'));
	$a_aliases = &$config['aliases']['alias'];

	/* Try to get the ID if the alias already exists */
	$id = easyrule_block_alias_getid($int);
	if ($id === false) {
	  unset($id);
	}

	$alias = array();

	if (is_subnet($host)) {
		list($host, $mask) = explode("/", $host);
	} elseif (is_specialnet($host)) {
		$mask = 0;
	} elseif (is_ipaddrv6($host)) {
		$mask = 128;
	} else {
		$mask = 32;
	}

	if (isset($id) && $a_aliases[$id]) {

		// Catch case when the list is empty
		if (empty($a_aliases[$id]['address'])) {
			$a_address = array();
			$a_detail = array();
		} else {
			$a_address = explode(" ", $a_aliases[$id]['address']);

			/* Make sure this IP isn't already in the list. */
			if (in_array($host.'/'.$mask, $a_address)) {
				return true;
			}
			$a_detail = explode("||", $a_aliases[$id]['detail']);
		}

		/* Since the alias already exists, just add to it. */
		$alias['name']    = $a_aliases[$id]['name'];
		$alias['type']    = $a_aliases[$id]['type'];
		$alias['descr']   = $a_aliases[$id]['descr'];

		$a_address[] = $host.'/'.$mask;
		$a_detail[] = gettext('Entry added') . ' ' . date('r');

		$alias['address'] = join(" ", $a_address);
		$alias['detail']  = join("||", $a_detail);

	} else {
		/* Create a new alias with all the proper information */
		$alias['name']    = $blockaliasname . strtoupper($int);
		$alias['type']    = 'network';
		$alias['descr']   = gettext("Hosts blocked from Firewall Log view");

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

	return true;
}

function easyrule_block_host_add($host, $int = 'wan', $ipproto = "inet") {
	global $retval;
	/* Bail if the supplied host is not a valid IP address */
	$host = trim($host, "[]");
	if (!is_ipaddr($host) && !is_subnet($host)) {
		return false;
	}

	/* Flag whether or not we need to reload the filter */
	$dirty = false;

	/* Attempt to add this host to the alias */
	if (easyrule_block_alias_add($host, $int)) {
		$dirty = true;
	} else {
		/* Couldn't add the alias, or adding the host failed. */
		return false;
	}

	/* Attempt to add the firewall rule if it doesn't exist.
	 * Failing to add the rule isn't necessarily an error, it may
	 * have been modified by the user in some way. Adding to the
	 * Alias is what's important.
	 */
	if (!easyrule_block_rule_exists($int, $ipproto)) {
		if (easyrule_block_rule_create($int, $ipproto)) {
			$dirty = true;
		} else {
			return false;
		}
	}

	/* If needed, write the config and reload the filter */
	if ($dirty) {
		write_config(sprintf(gettext("Blocked host %s via easy rule"), $host));
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
	$a_filter = &$config['filter']['rule'];

	/* Make up a new rule */
	$filterent = array();
	$filterent['type'] = 'pass';
	$filterent['interface'] = $int;
	$filterent['ipprotocol'] = $ipproto;
	$filterent['descr'] = gettext("Easy Rule: Passed from Firewall Log View");

	if ($proto != "any") {
		$filterent['protocol'] = $proto;
	} else {
		unset($filterent['protocol']);
	}

	/* Default to only allow echo requests, since that's what most people want and
	 *  it should be a safe choice. */
	if ($proto == "icmp") {
		$filterent['icmptype'] = 'echoreq';
	}

	if ((strtolower($proto) == "icmp6") || (strtolower($proto) == "icmpv6")) {
		$filterent['protocol'] = "icmp";
	}

	if (is_subnet($srchost)) {
		list($srchost, $srcmask) = explode("/", $srchost);
	} elseif (is_specialnet($srchost)) {
		$srcmask = 0;
	} elseif (is_ipaddrv6($srchost)) {
		$srcmask = 128;
	} else {
		$srcmask = 32;
	}

	if (is_subnet($dsthost)) {
		list($dsthost, $dstmask) = explode("/", $dsthost);
	} elseif (is_specialnet($dsthost)) {
		$dstmask = 0;
	} elseif (is_ipaddrv6($dsthost)) {
		$dstmask = 128;
	} else {
		$dstmask = 32;
	}

	pconfig_to_address($filterent['source'], $srchost, $srcmask);
	pconfig_to_address($filterent['destination'], $dsthost, $dstmask, '', $dstport, $dstport);

	$filterent['created'] = make_config_revision_entry(null, "Easy Rule");
	$filterent['tracker'] = (int)microtime(true);
	$a_filter[] = $filterent;

	write_config($filterent['descr']);
	$retval = filter_configure();
	if (!empty($_SERVER['DOCUMENT_ROOT'])) {
		header("Location: firewall_rules.php?if={$int}");
		exit;
	} else {
		return true;
	}
}

function easyrule_parse_block($int, $src, $ipproto = "inet") {
	if (!empty($src) && !empty($int)) {
		$src = trim($src, "[]");
		if (!is_ipaddr($src) && !is_subnet($src)) {
			return gettext("Tried to block invalid IP:") . ' ' . htmlspecialchars($src);
		}
		$int = easyrule_find_rule_interface($int);
		if ($int === false) {
			return gettext("Invalid interface for block rule:") . ' ' . htmlspecialchars($int);
		}
		if (easyrule_block_host_add($src, $int, $ipproto)) {
			return gettext("Host added successfully");
		} else {
			return gettext("Failed to create block rule, alias, or add host.");
		}
	} else {
		return gettext("Tried to block but had no host IP or interface");
	}
	return gettext("Unknown block error.");
}

function easyrule_parse_unblock($int, $host, $ipproto = "inet") {
	global $blockaliasname, $config;

	if (!empty($host) && !empty($int)) {
		$host = trim($host, "[]");
		if (!is_ipaddr($host) && !is_subnet($host)) {
			return gettext("Tried to unblock invalid IP:") . ' ' . htmlspecialchars($host);
		}
		$real_int = easyrule_find_rule_interface($int);
		if ($real_int === false) {
			return gettext("Invalid interface for block rule:") . ' ' . htmlspecialchars($int);
		}

		/* Try to get the ID - will fail if there are no rules/alias on this interface */
		$id = easyrule_block_alias_getid($real_int);
		if ($id === false || !$config['aliases']['alias'][$id]) {
			return gettext("No block rules set on interface:") . ' ' . htmlspecialchars($int);
		}

		init_config_arr(array('aliases', 'alias', $id));
		$alias = &$config['aliases']['alias'][$id];

		if (is_subnet($host)) {
			list($host, $mask) = explode("/", $host);
		} elseif (is_specialnet($host)) {
			$mask = 0;
		} elseif (is_ipaddrv6($host)) {
			$mask = 128;
		} else {
			$mask = 32;
		}

		// Create the expected string representation
		$unblock = $host.'/'.$mask;

		$a_address = explode(" ", $config['aliases']['alias'][$id]['address']);
		$a_detail = explode("||", $config['aliases']['alias'][$id]['detail']);

		if (($key = array_search($unblock, $a_address)) !== false) {
			unset($a_address[$key]);
			unset($a_detail[$key]);
			// Write back the result to the config array
			$config['aliases']['alias'][$id]['address'] = join(" ", $a_address);
			$config['aliases']['alias'][$id]['detail'] = join("||", $a_detail);

			// Update config
			write_config(sprintf(gettext("Unblocked host %s via easy rule"), $host));
			$retval = filter_configure();
			if (!empty($_SERVER['DOCUMENT_ROOT'])) {
				header("Location: firewall_aliases.php");
				exit;
			} else {
				return gettext("Host unblocked successfully");
			}
		} else {
			return gettext("Host is not on block list: " . $host);
		}
	}

	return gettext("Tried to unblock but had no host IP or interface");

}

function easyrule_parse_getblock($int = 'wan', $sep = "\n") {
	global $blockaliasname, $config;

	$real_int = easyrule_find_rule_interface($int);
	if ($real_int === false) {
		return gettext("Invalid interface for block rule:") . ' ' . htmlspecialchars($int);
	}

	/* Try to get the ID - will fail if there are no rules/alias on this interface */
	$id = easyrule_block_alias_getid($real_int);

	if ($id === false || !$config['aliases']['alias'][$id] || empty($config['aliases']['alias'][$id]['address'])) {
		return gettext("No block rules set on interface:") . ' ' . htmlspecialchars($int);
	}
	return join($sep, explode(" ", $config['aliases']['alias'][$id]['address']));

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
		if (getprotobyname($proto) == -1) {
			return gettext("Invalid protocol for pass rule:") . ' ' . htmlspecialchars($proto);
		}
		if (!is_ipaddr($src) && !is_subnet($src) && !is_ipaddroralias($src) && !is_specialnet($src)) {
			return gettext("Tried to pass invalid source IP:") . ' ' . htmlspecialchars($src);
		}
		if (!is_ipaddr($dst) && !is_subnet($dst) && !is_ipaddroralias($dst) && !is_specialnet($dst)) {
			return gettext("Tried to pass invalid destination IP:") . ' ' . htmlspecialchars($dst);
		}
		if (in_array($proto, $protocols_with_ports)) {
			if (empty($dstport)) {
				return gettext("Missing destination port:") . ' ' . htmlspecialchars($dstport);
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
