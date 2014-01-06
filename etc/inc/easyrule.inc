<?php
/*
	easyrule.inc.php

	Copyright (C) 2009-2010 Jim Pingle (jpingle@gmail.com)
	Originally Sponsored By Anathematic @ pfSense Forums
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	notice, this list of conditions and the following disclaimer in the
	documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
/*
	pfSense_BUILDER_BINARIES:
	pfSense_MODULE:	filter
*/

$blockaliasname = 'EasyRuleBlockHosts';
$protocols_with_ports = array('tcp', 'udp');
require_once("functions.inc");
require_once("util.inc");
require_once("config.inc");

function easyrule_find_rule_interface($int) {
	global $config;
	/* Borrowed from firewall_rules.php */
	$iflist = get_configured_interface_with_descr(false, true);

	if ($config['pptpd']['mode'] == "server")
		$iflist['pptp'] = "PPTP VPN";

	if ($config['pppoe']['mode'] == "server")
		$iflist['pppoe'] = "PPPoE VPN";

	if ($config['l2tp']['mode'] == "server")
                $iflist['l2tp'] = "L2TP VPN";

	/* add ipsec interfaces */
	if (isset($config['ipsec']['enable']) || isset($config['ipsec']['client']['enable'])){
		$iflist["enc0"] = "IPSEC";
	}

	if (isset($iflist[$int]))
		return $int;

	foreach ($iflist as $if => $ifd) {
		if (strtolower($int) == strtolower($ifd))
			return $if;
	}

	if (substr($int, 0, 4) == "ovpn")
		return "openvpn";

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
		if (!is_array($rule) || !is_array($rule['source']))
			continue;
		$checkproto = isset($rule['ipprotocol']) ? $rule['ipprotocol'] : "inet";
		if ($rule['source']['address'] == $blockaliasname . strtoupper($int) && ($rule['interface'] == $int) && ($checkproto == $ipproto))
			return true;
	}
	return false;
}

function easyrule_block_rule_create($int = 'wan', $ipproto = "inet") {
	global $blockaliasname, $config;
	/* If the alias doesn't exist, exit.
	 * Can't create an empty alias, and we don't know a host */
	if (easyrule_block_alias_getid($int) === false)
		return false;

	/* If the rule already exists, no need to do it again */
	if (easyrule_block_rule_exists($int, $ipproto))
		return true;

	/* No rules, start a new array */
	if (!is_array($config['filter']['rule'])) {
		$config['filter']['rule'] = array();
	}

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
	$filterent['created'] = make_config_revision_entry(null, gettext("Easy Rule"));

	array_splice($a_filter, 0, 0, array($filterent));

	return true;
}

function easyrule_block_alias_getid($int = 'wan') {
	global $blockaliasname, $config;
	if (!is_array($config['aliases']))
		return false;

	/* Hunt down an alias with the name we want, return its id */
	foreach ($config['aliases']['alias'] as $aliasid => $alias)
		if ($alias['name'] == $blockaliasname . strtoupper($int))
			return $aliasid;

	return false;
}

function easyrule_block_alias_add($host, $int = 'wan') {
	global $blockaliasname, $config;
	/* If the host isn't a valid IP address, bail */
	$host = trim($host, "[]");
	if (!is_ipaddr($host) && !is_subnet($host))
		return false;

	/* If there are no aliases, start an array */
	if (!is_array($config['aliases']['alias']))
		$config['aliases']['alias'] = array();

	$a_aliases = &$config['aliases']['alias'];

	/* Try to get the ID if the alias already exists */
	$id = easyrule_block_alias_getid($int);
	if ($id === false)
	  unset($id);

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
		/* Make sure this IP isn't already in the list. */
		if (in_array($host.'/'.$mask, explode(" ", $a_aliases[$id]['address'])))
			return true;
		/* Since the alias already exists, just add to it. */
		$alias['name']    = $a_aliases[$id]['name'];
		$alias['type']    = $a_aliases[$id]['type'];
		$alias['descr']   = $a_aliases[$id]['descr'];

		$alias['address'] = $a_aliases[$id]['address'] . ' ' . $host . '/' . $mask;
		$alias['detail']  = $a_aliases[$id]['detail'] . gettext('Entry added') . ' ' . date('r') . '||';
	} else {
		/* Create a new alias with all the proper information */
	 	$alias['name']    = $blockaliasname . strtoupper($int);
	 	$alias['type']    = 'network';
		$alias['descr']   = gettext("Hosts blocked from Firewall Log view");

		$alias['address'] = $host . '/' . $mask;
		$alias['detail']  = gettext('Entry added') . ' ' . date('r') . '||';
	}

	/* Replace the old alias if needed, otherwise tack it on the end */
	if (isset($id) && $a_aliases[$id])
		$a_aliases[$id] = $alias;
	else
		$a_aliases[] = $alias;

	// Sort list
	$a_aliases = msort($a_aliases, "name");

	return true;
}

function easyrule_block_host_add($host, $int = 'wan', $ipproto = "inet") {
	global $retval;
	/* Bail if the supplied host is not a valid IP address */
	$host = trim($host, "[]");
	if (!is_ipaddr($host) && !is_subnet($host))
		return false;

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
		write_config();
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

	/* No rules, start a new array */
	if (!is_array($config['filter']['rule'])) {
		$config['filter']['rule'] = array();
	}

	filter_rules_sort();
	$a_filter = &$config['filter']['rule'];

	/* Make up a new rule */
	$filterent = array();
	$filterent['type'] = 'pass';
	$filterent['interface'] = $int;
	$filterent['ipprotocol'] = $ipproto;
	$filterent['descr'] = gettext("Easy Rule: Passed from Firewall Log View");

	if ($proto != "any")
		$filterent['protocol'] = $proto;
	else
		unset($filterent['protocol']);

	/* Default to only allow echo requests, since that's what most people want and
	 *  it should be a safe choice. */
	if ($proto == "icmp")
		$filterent['icmptype'] = 'echoreq';

	if ((strtolower($proto) == "icmp6") || (strtolower($proto) == "icmpv6"))
		$filterent['protocol'] = "icmp";

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

	$filterent['created'] = make_config_revision_entry(null, gettext("Easy Rule"));
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
