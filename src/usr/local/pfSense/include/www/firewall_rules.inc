<?php
/*
 * firewall_rules.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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

 // Functions to support firewall_rules.php

require_once("config.gui.inc");
require_once("interfaces.inc");
require_once("util.inc");
require_once("pfsense-utils.inc");
require_once("ipsec.inc");
require_once("filter.inc");
require_once("itemid.inc");

$list = array('' => 'none');
if (!is_array($qlist)) {
	$qlist = array();
}

foreach ($qlist as $q => $qkey) {
	if (isset($ifdisp[$q])) {
		$list[$q] = $ifdisp[$q];
	} else {
		$list[$q] = $q;
	}
}

if (isset($_REQUEST['after']) && (is_numericint($_REQUEST['after']) || $_REQUEST['after'] == "-1")) {
	$after = $_REQUEST['after'];
}

if (isset($_REQUEST['dup']) && is_numericint($_REQUEST['dup'])) {
	$id = $_REQUEST['dup'];
	$after = $_REQUEST['dup'];
}

function is_posnumericint($arg) {
	// Integer > 0? (Note that to be safe we do not allow any leading zero - "01", "007")
	return (is_numericint($arg) && $arg[0] != '0');
}

function is_aoadv_used($rule_config) {
	// Note that the user could set "tag" or "tagged" to the string "0", which is valid but empty().
	// And if the user enters "0" in other fields, we want to present an error message, and keep the Advanced Options section open.
	if (($rule_config['os'] != "") ||
	    ($rule_config['dscp'] != "") ||
	    (isset($rule_config['allowopts'])) ||
	    (isset($rule_config['disablereplyto'])) ||
	    ($rule_config['tag'] != "") ||
	    ($rule_config['tagged'] != "") ||
	    (isset($rule_config['nottagged'])) ||
	    ($rule_config['max'] != "") ||
	    ($rule_config['max-src-nodes'] != "") ||
	    ($rule_config['max-src-conn'] != "") ||
	    ($rule_config['max-src-states'] != "") ||
	    ($rule_config['max-src-conn-rate'] != "") ||
	    ($rule_config['max-src-conn-rates'] != "") ||
	    ($rule_config['statetimeout'] != "") ||
	    ($rule_config['tcpflags1'] != "") ||
	    ($rule_config['tcpflags2'] != "") ||
	    ($rule_config['tcpflags_any']) ||
	    ($rule_config['nopfsync']) ||
	    (($rule_config['statetype'] != "") && ($rule_config['statetype'] != "keep state")) ||
	    ($rule_config['nosync']) ||
	    ($rule_config['vlanprio'] != "") ||
	    ($rule_config['vlanprioset'] != "") ||
	    ($rule_config['sched'] != "") ||
	    ($rule_config['gateway'] != "") ||
	    ($rule_config['dnpipe'] != "") ||
	    ($rule_config['pdnpipe'] != "") ||
	    ($rule_config['ackqueue'] != "") ||
	    ($rule_config['defaultqueue'] != "")) {
		return true;
	}

	return false;
}

function saveFilterRule($post, $id, $json = false) {
  global $config, $icmptypes, $tcpflags, $firewall_rules_dscp_types, $list, $after;
  init_config_arr(array('filter', 'rule'));
  filter_rules_sort();
  $a_filter = &$config['filter']['rule'];
  $if = $post['interface'];

  if (isset($a_filter[$id]['protocol'])) {
		$pconfig['proto'] = $a_filter[$id]['protocol'];
	} else {
		$pconfig['proto'] = "any";
	}

	if ($a_filter[$id]['protocol'] == "icmp") {
		$pconfig['icmptype'] = $a_filter[$id]['icmptype'];
	}

    
  $dnqlist =& get_unique_dnqueue_list();
  $a_gatewaygroups = return_gateway_groups_array();
  $a_filter = &$config['filter']['rule'];
  

  /* build icmptypes valid for IPv4, IPv6 and IPv<any> */
  $icmptypes4 = array('any' => gettext('any'));
  $icmptypes6 = array('any' => gettext('any'));
  $icmptypes46 = array('any' => gettext('any'));

  // ICMP descriptions may be translated, so require escaping to handle single quotes (in particular)
  foreach ($icmptypes as $k => $v) {
    $description = addslashes($v['descrip']);

    if ($v['valid4']) {
      $icmptypes4[$k] = $description;
      if ($v['valid6']) {
        $icmptypes6[$k] = $description;
        $icmptypes46[$k] = $description;
      }
    } else {
      $icmptypes6[$k] = $description;
    }
  }

  $icmplookup = array(
    'inet' => array('name' => 'IPv4', 'icmptypes' => $icmptypes4, 'helpmsg' => gettext('For ICMP rules on IPv4, one or more of these ICMP subtypes may be specified.')),
    'inet6' => array('name' => 'IPv6', 'icmptypes' => $icmptypes6, 'helpmsg' => gettext('For ICMP rules on IPv6, one or more of these ICMP subtypes may be specified.')),
    'inet46' => array('name' => 'IPv4+6', 'icmptypes' => $icmptypes46, 'helpmsg' => sprintf(gettext('For ICMP rules on IPv4+IPv6, one or more of these ICMP subtypes may be specified. (Other ICMP subtypes are only valid under IPv4 %1$sor%2$s IPv6, not both)'), '<i>', '</i>'))
  );

  $statetype_values = array(
    'keep state' => gettext('Keep'),
    'sloppy state' => gettext('Sloppy'),
    'synproxy state' => gettext('Synproxy'),
    'none' => gettext('None'),
  );

  $vlanprio = array(
    "" => "none",
    "bk" => "Background (BK, 0)",
    "be" => "Best Effort (BE, 1)",
    "ee" => "Excellent Effort (EE, 2)",
    "ca" => "Critical Applications (CA, 3)",
    "vi" => "Video (VI, 4)",
    "vo" => "Voice (VO, 5)",
    "ic" => "Internetwork Control (IC, 6)",
    "nc" => "Network Control (NC, 7)");

  $ostypes = array();
  exec('/sbin/pfctl -s osfp | /usr/bin/tr \'\t\' \' \'', $ostypes);

  if (count($ostypes) > 2) {
    // Remove header rows from pfctl output
    array_shift($ostypes);
    array_shift($ostypes);
  } else {
    // Fall back to a default list
    $ostypes = array(
      "AIX",
      "Linux",
      "FreeBSD",
      "NetBSD",
      "OpenBSD",
      "Solaris",
      "MacOS",
      "Windows",
      "Novell",
      "NMAP"
    );
  }

  $input_errors = array();

  if (!array_key_exists($post['ipprotocol'], $icmplookup)) {
		$input_errors[] = gettext("The IP protocol is not recognized.");
		unset($post['ipprotocol']);
	}
  $valid = ($post['interface'] == "FloatingRules" || isset($post['floating'])) ? ['pass','block','reject', 'match'] : ['pass','block','reject'];
	if (!(is_string($post['type'])  && in_array($post['type'], $valid))) {
		$input_errors[] = gettext("A valid rule type is not selected.");
		unset($post['type']);
	}

	if (isset($post['tracker']) && !is_numericint($post['tracker'])) {
		unset($post['tracker']);	// silently unset hidden input if invalid
	}

	if (isset($a_filter[$id]['associated-rule-id'])) {
		$post['proto'] = $pconfig['proto'];
		if ($pconfig['proto'] == "icmp") {
			$post['icmptype'] = $pconfig['icmptype'];
		}
	}

	if (isset($post['ipprotocol']) && $post['gateway'] <> '') {
		if (is_array($config['gateways']['gateway_group'])) {
			foreach ($config['gateways']['gateway_group'] as $gw_group) {
				if ($gw_group['name'] == $post['gateway'] && $post['ipprotocol'] != $a_gatewaygroups[$post['gateway']]['ipprotocol']) {
					if ($post['ipprotocol'] == "inet46") {
						$input_errors[] = gettext("Gateways can not be assigned in a rule that applies to both IPv4 and IPv6.");
					} elseif ($post['ipprotocol'] == "inet6") {
						$input_errors[] = gettext("An IPv4 gateway group can not be assigned in IPv6 rules.");
					} elseif ($post['ipprotocol'] == "inet") {
						$input_errors[] = gettext("An IPv6 gateway group can not be assigned in IPv4 rules.");
					}
				}
			}
		}
		if ($iptype = is_ipaddr(lookup_gateway_ip_by_name($post['gateway']))) {
			// this also implies that  $post['gateway'] was set and not empty
			if ($post['ipprotocol'] == "inet46") {
				$input_errors[] = gettext("Gateways can not be assigned in a rule that applies to both IPv4 and IPv6.");
			}
			if (($post['ipprotocol'] == "inet6") && ($iptype != 6)) {
				$input_errors[] = gettext("An IPv4 gateway can not be assigned in IPv6 rules.");
			}
			if (($post['ipprotocol'] == "inet") && ($iptype != 4)) {
				$input_errors[] = gettext("An IPv6 gateway can not be assigned in IPv4 rules.");
			}
		}
	}

	if (strpos($post['descr'], "\\") !== false) {
		$input_errors[] = gettext("The '\' character is not allowed in the Description field.");
	}

	if (($post['proto'] != "tcp") && ($post['proto'] != "udp") && ($post['proto'] != "tcp/udp")) {
		$post['srcbeginport'] = 0;
		$post['srcendport'] = 0;
		$post['dstbeginport'] = 0;
		$post['dstendport'] = 0;
	} else {
		if ($post['srcbeginport_cust'] && !$post['srcbeginport']) {
			$post['srcbeginport'] = trim($post['srcbeginport_cust']);
		}
		if ($post['srcendport_cust'] && !$post['srcendport']) {
			$post['srcendport'] = trim($post['srcendport_cust']);
		}
		if ($post['srcbeginport'] == "any") {
			$post['srcbeginport'] = 0;
			$post['srcendport'] = 0;
		} else {
			if (!$post['srcendport']) {
				$post['srcendport'] = $post['srcbeginport'];
			}
		}
		if ($post['srcendport'] == "any") {
			$post['srcendport'] = $post['srcbeginport'];
		}

		if ($post['dstbeginport_cust'] && !$post['dstbeginport']) {
			$post['dstbeginport'] = trim($post['dstbeginport_cust']);
		}
		if ($post['dstendport_cust'] && !$post['dstendport']) {
			$post['dstendport'] = trim($post['dstendport_cust']);
		}

		if ($post['dstbeginport'] == "any") {
			$post['dstbeginport'] = 0;
			$post['dstendport'] = 0;
		} else {
			if (!$post['dstendport']) {
				$post['dstendport'] = $post['dstbeginport'];
			}
		}
		if ($post['dstendport'] == "any") {
			$post['dstendport'] = $post['dstbeginport'];
		}
	}

	if (is_specialnet($post['srctype'])) {
		$post['src'] = $post['srctype'];
		$post['srcmask'] = 0;
	} else if ($post['srctype'] == "single") {
		if (is_ipaddrv6($post['src'])) {
			$post['srcmask'] = 128;
		} else {
			$post['srcmask'] = 32;
		}
	}
	if (is_specialnet($post['dsttype'])) {
		$post['dst'] = $post['dsttype'];
		$post['dstmask'] = 0;
	} else if ($post['dsttype'] == "single") {
		if (is_ipaddrv6($post['dst'])) {
			$post['dstmask'] = 128;
		} else {
			$post['dstmask'] = 32;
		}
	}

	$pconfig = $post;

	if (!isset($pconfig['ipprotocol'])) {
		// other things depend on this, so ensure a valid value if none provided
		$pconfig['ipprotocol'] = "inet";
	}

	if (($post['proto'] == "icmp") && count($post['icmptype'])) {
		$pconfig['icmptype'] = implode(',', $post['icmptype']);
	} else {
		unset($pconfig['icmptype']);
	}

	/* input validation */
	$reqdfields = explode(" ", "type proto");
	if (isset($a_filter[$id]['associated-rule-id']) === false) {
		$reqdfields[] = "src";
		$reqdfields[] = "dst";
	}
	$reqdfieldsn = array(gettext("Type"), gettext("Protocol"));
	if (isset($a_filter[$id]['associated-rule-id']) === false) {
		$reqdfieldsn[] = gettext("Source");
		$reqdfieldsn[] = gettext("Destination");
	}

	if ($post['statetype'] == "synproxy state") {
		if ($post['proto'] != "tcp") {
			$input_errors[] = sprintf(gettext("%s is only valid with protocol TCP."), $post['statetype']);
		}
		if ($post['gateway'] != "") {
			$input_errors[] = sprintf(gettext("%s is only valid if the gateway is set to 'default'."), $post['statetype']);
		}
	}

	if (isset($a_filter[$id]['associated-rule-id']) === false &&
	    (!(is_specialnet($post['srctype']) || ($post['srctype'] == "single")))) {
		$reqdfields[] = "srcmask";
		$reqdfieldsn[] = gettext("Source bit count");
	}
	if (isset($a_filter[$id]['associated-rule-id']) === false &&
	    (!(is_specialnet($post['dsttype']) || ($post['dsttype'] == "single")))) {
		$reqdfields[] = "dstmask";
		$reqdfieldsn[] = gettext("Destination bit count");
	}

	do_input_validation($post, $reqdfields, $reqdfieldsn, $input_errors);

	if ((isset($post['srcnot']) && ($post['srctype'] == 'any')) ||
	    (isset($post['dstnot']) && ($post['dsttype'] == 'any'))) {
		$input_errors[] = gettext("Invert match cannot be selected with 'any'.");
	}

	if (isset($post['nottagged']) && empty($post['tagged'])) {
		$input_errors[] = gettext("Invert tagged match cannot be selected without any tags.");
	}

	if (!$post['srcbeginport']) {
		$post['srcbeginport'] = 0;
		$post['srcendport'] = 0;
	}
	if (!$post['dstbeginport']) {
		$post['dstbeginport'] = 0;
		$post['dstendport'] = 0;
	}

	if ($post['srcbeginport'] && !is_port_or_alias($post['srcbeginport'])) {
		$input_errors[] = sprintf(gettext("%s is not a valid start source port. It must be a port alias or integer between 1 and 65535."), $post['srcbeginport']);
	}
	if ($post['srcendport'] && !is_port_or_alias($post['srcendport'])) {
			$input_errors[] = sprintf(gettext("%s is not a valid end source port. It must be a port alias or integer between 1 and 65535."), $post['srcendport']);
	}
	if ($post['dstbeginport'] && !is_port_or_alias($post['dstbeginport'])) {
			$input_errors[] = sprintf(gettext("%s is not a valid start destination port. It must be a port alias or integer between 1 and 65535."), $post['dstbeginport']);
	}
	if ($post['dstendport'] && !is_port_or_alias($post['dstendport'])) {
			$input_errors[] = sprintf(gettext("%s is not a valid end destination port. It must be a port alias or integer between 1 and 65535."), $post['dstendport']);
	}
	if (!$post['srcbeginport_cust'] && $post['srcendport_cust']) {
		if (is_alias($post['srcendport_cust'])) {
			$input_errors[] = 'If a port alias is put in the Source port range to: field the same port alias must be put in the from: field';
		}
	}
	if ($post['srcbeginport_cust'] && $post['srcendport_cust']) {
		if (is_alias($post['srcbeginport_cust']) && is_alias($post['srcendport_cust']) && $post['srcbeginport_cust'] != $post['srcendport_cust']) {
			$input_errors[] = 'The same port alias must be used in Source port range from: and to: fields';
		}
		if ((is_alias($post['srcbeginport_cust']) && (!is_alias($post['srcendport_cust']) && $post['srcendport_cust'] != '')) ||
		    ((!is_alias($post['srcbeginport_cust']) && $post['srcbeginport_cust'] != '') && is_alias($post['srcendport_cust']))) {
			$input_errors[] = 'Numbers and port aliases cannot be specified at the same time in Source port range from: and to: field';
		}
	}
	if (!$post['dstbeginport_cust'] && $post['dstendport_cust']) {
		if (is_alias($post['dstendport_cust'])) {
			$input_errors[] = 'If a port alias is put in the Destination port range to: field the same port alias must be put in the from: field';
		}
	}
	if ($post['dstbeginport_cust'] && $post['dstendport_cust']) {
		if (is_alias($post['dstbeginport_cust']) && is_alias($post['dstendport_cust']) && $post['dstbeginport_cust'] != $post['dstendport_cust']) {
			$input_errors[] = 'The same port alias must be used in Destination port range from: and to: fields';
		}
		if ((is_alias($post['dstbeginport_cust']) && (!is_alias($post['dstendport_cust']) && $post['dstendport_cust'] != '')) ||
		    ((!is_alias($post['dstbeginport_cust']) && $post['dstbeginport_cust'] != '') && is_alias($post['dstendport_cust']))) {
			$input_errors[] = 'Numbers and port aliases cannot be specified at the same time in Destination port range from: and to: field';
		}
	}

	if ($post['src']) {
		$post['src'] = addrtolower(trim($post['src']));
	}
	if ($post['dst']) {
		$post['dst'] = addrtolower(trim($post['dst']));
	}

	/* if user enters an alias and selects "network" then disallow. */
	if ($post['srctype'] == "network") {
		if (is_alias($post['src'])) {
			$input_errors[] = gettext("Alias entries must be a single host or alias.");
		}
	}
	if ($post['dsttype'] == "network") {
		if (is_alias($post['dst'])) {
			$input_errors[] = gettext("Alias entries must be a single host or alias.");
		}
	}

	if (!is_specialnet($post['srctype'])) {
		if (($post['src'] && !is_ipaddroralias($post['src']))) {
			$input_errors[] = sprintf(gettext("%s is not a valid source IP address or alias."), $post['src']);
		}
		if (($post['srcmask'] && !is_numericint($post['srcmask']))) {
			$input_errors[] = gettext("A valid source bit count must be specified.");
		}
	}
	if (!is_specialnet($post['dsttype'])) {
		if (($post['dst'] && !is_ipaddroralias($post['dst']))) {
			$input_errors[] = sprintf(gettext("%s is not a valid destination IP address or alias."), $post['dst']);
		}
		if (($post['dstmask'] && !is_numericint($post['dstmask']))) {
			$input_errors[] = gettext("A valid destination bit count must be specified.");
		}
	}
	if ((is_ipaddr($post['src']) && is_ipaddr($post['dst']))) {
		if (!validate_address_family($post['src'], $post['dst'])) {
			$input_errors[] = gettext("The source and destination IP addresses must have the same family (IPv4 / IPv6).");
		}
	}
	if ((is_ipaddrv6($post['src']) || is_ipaddrv6($post['dst'])) && ($post['ipprotocol'] == "inet")) {
		$input_errors[] = gettext("IPv6 addresses cannot be used in IPv4 rules (except within an alias).");
	}
	if ((is_ipaddrv4($post['src']) || is_ipaddrv4($post['dst'])) && ($post['ipprotocol'] == "inet6")) {
		$input_errors[] = gettext("IPv4 addresses can not be used in IPv6 rules (except within an alias).");
	}

	if ((is_ipaddr($post['src']) || is_ipaddr($post['dst'])) && ($post['ipprotocol'] == "inet46")) {
		$input_errors[] = gettext("IPv4 and IPv6 addresses can not be used in rules that apply to both IPv4 and IPv6 (except within an alias).");
	}

	if ($post['srcbeginport'] > $post['srcendport']) {
		/* swap */
		$tmp = $post['srcendport'];
		$post['srcendport'] = $post['srcbeginport'];
		$post['srcbeginport'] = $tmp;
	}
	if ($post['dstbeginport'] > $post['dstendport']) {
		/* swap */
		$tmp = $post['dstendport'];
		$post['dstendport'] = $post['dstbeginport'];
		$post['dstbeginport'] = $tmp;
	}
	if ($post['os']) {
		if ($post['proto'] != "tcp") {
			$input_errors[] = gettext("OS detection is only valid with protocol TCP.");
		}
		if (!in_array($post['os'], $ostypes)) {
			$input_errors[] = gettext("Invalid OS detection selection. Please select a valid OS.");
		}
	}

	if ($post['proto'] == "icmp") {
		$t = $post['icmptype'];
		if (isset($t) && !is_array($t)) {
			// shouldn't happen but avoids making assumptions for data-sanitising
			$input_errors[] = gettext("ICMP types expected to be a list if present, but is not.");
		} elseif (!isset($t) || count($t) == 0) {
			// not specified or none selected
			unset($post['icmptype']);
		} elseif (isset($post['ipprotocol'])) {
			// check data; if ipprotocol invalid then safe to skip this (we can't determine valid icmptypes, but input error already raised for ipprotocol)
			$bad_types = array();
			if ((count($t) == 1 && !isset($t['any'])) || count($t) > 1) {
				// Only need to check valid if just one selected != "any", or >1 selected
				$p = $post['ipprotocol'];
				foreach ($t as $type) {
					if (($p == 'inet' && !array_key_exists($type, $icmptypes4)) ||
					    ($p == 'inet6' && !array_key_exists($type, $icmptypes6)) ||
					    ($p == 'inet46' && !array_key_exists($type, $icmptypes46))) {
							$bad_types[] = $type;
					}
				}
			}
			if (count($bad_types) > 0) {
				$input_errors[] = sprintf(gettext("Invalid ICMP subtype: %s can not be used with %s."), implode(';', $bad_types),  $t['name']);
			}
		}
	} else {
		unset($post['icmptype']); // field not applicable, might hold junk from old hidden selections. Unset it.
	}

	if ($post['ackqueue'] != "") {
		if ($post['defaultqueue'] == "") {
			$input_errors[] = gettext("A queue must be selected when an acknowledge queue is also selected.");
		} else if ($post['ackqueue'] == $post['defaultqueue']) {
			$input_errors[] = gettext("Acknowledge queue and Queue cannot be the same.");
		}
	}
	if (isset($post['floating']) && $post['pdnpipe'] != "" && (empty($post['direction']) || $post['direction'] == "any")) {
		$input_errors[] = gettext("Limiters can not be used in Floating rules without choosing a direction.");
	}
	if (isset($post['floating']) && $post['gateway'] != "" && (empty($post['direction']) || $post['direction'] == "any")) {
		$input_errors[] = gettext("Gateways can not be used in Floating rules without choosing a direction.");
	}
	if ($post['pdnpipe'] && $post['pdnpipe'] != "") {
		if ($post['dnpipe'] == "") {
			$input_errors[] = gettext("A queue must be selected for the In direction before selecting one for Out too.");
		} else if ($post['pdnpipe'] == $post['dnpipe']) {
			$input_errors[] = gettext("In and Out Queue cannot be the same.");
		} else if ($dnqlist[$post['pdnpipe']][0] == "?" && $dnqlist[$post['dnpipe']][0] <> "?") {
			$input_errors[] = gettext("A queue and a virtual interface cannot be selected for IN and Out. Both must be from the same type.");
		} else if ($dnqlist[$post['dnpipe']][0] == "?" && $dnqlist[$post['pdnpipe']][0] <> "?") {
			$input_errors[] = gettext("A queue and a virtual interface cannot be selected for IN and Out. Both must be from the same type.");
		}
		if ($post['direction'] == "out" && empty($post['gateway'])) {
			$input_errors[] = gettext("Please select a gateway, normally the interface selected gateway, so the limiters work correctly");
		}
	}
	if (!empty($post['ruleid']) && !is_numericint($post['ruleid'])) {
		$input_errors[] = gettext('ID must be an integer');
	}

	if (!in_array($post['proto'], array("tcp", "tcp/udp"))) {
		if (!empty($post['max-src-conn'])) {
			$input_errors[] = gettext("The maximum number of established connections per host (advanced option) can only be specified for TCP protocol.");
		}
		if (!empty($post['max-src-conn-rate']) || !empty($post['max-src-conn-rates'])) {
			$input_errors[] = gettext("The maximum new connections per host / per second(s) (advanced option) can only be specified for TCP protocol.");
		}
	}

	if ($post['type'] <> "pass") {
		if (!empty($post['max'])) {
			$input_errors[] = gettext("The maximum state entries (advanced option) can only be specified for Pass type rules.");
		}
		if (!empty($post['max-src-nodes'])) {
			$input_errors[] = gettext("The maximum number of unique source hosts (advanced option) can only be specified for Pass type rules.");
		}
		if (!empty($post['max-src-conn'])) {
			$input_errors[] = gettext("The maximum number of established connections per host (advanced option) can only be specified for Pass type rules.");
		}
		if (!empty($post['max-src-states'])) {
			$input_errors[] = gettext("The maximum state entries per host (advanced option) can only be specified for Pass type rules.");
		}
		if (!empty($post['max-src-conn-rate']) || !empty($post['max-src-conn-rates'])) {
			$input_errors[] = gettext("The maximum new connections per host / per second(s) (advanced option) can only be specified for Pass type rules.");
		}
		if (!empty($post['statetimeout'])) {
			$input_errors[] = gettext("The state timeout (advanced option) can only be specified for Pass type rules.");
		}
	}

	if ($post['statetype'] == "none") {
		if (!empty($post['max'])) {
			$input_errors[] = gettext("The maximum state entries (advanced option) cannot be specified if statetype is none.");
		}
		if (!empty($post['max-src-nodes'])) {
			$input_errors[] = gettext("The maximum number of unique source hosts (advanced option) cannot be specified if statetype is none.");
		}
		if (!empty($post['max-src-conn'])) {
			$input_errors[] = gettext("The maximum number of established connections per host (advanced option) cannot be specified if statetype is none.");
		}
		if (!empty($post['max-src-states'])) {
			$input_errors[] = gettext("The maximum state entries per host (advanced option) cannot be specified if statetype is none.");
		}
		if (!empty($post['max-src-conn-rate']) || !empty($post['max-src-conn-rates'])) {
			$input_errors[] = gettext("The maximum new connections per host / per second(s) (advanced option) cannot be specified if statetype is none.");
		}
		if (!empty($post['statetimeout'])) {
			$input_errors[] = gettext("The state timeout (advanced option) cannot be specified if statetype is none.");
		}
	}

	if (($post['max'] != "") && !is_posnumericint($post['max'])) {
		$input_errors[] = gettext("Maximum state entries (advanced option) must be a positive integer");
	}

	if (($post['max-src-nodes'] != "") && !is_posnumericint($post['max-src-nodes'])) {
		$input_errors[] = gettext("Maximum number of unique source hosts (advanced option) must be a positive integer");
	}

	if (($post['max-src-conn'] != "") && !is_posnumericint($post['max-src-conn'])) {
		$input_errors[] = gettext("Maximum number of established connections per host (advanced option) must be a positive integer");
	}

	if (($post['max-src-states'] != "") && !is_posnumericint($post['max-src-states'])) {
		$input_errors[] = gettext("Maximum state entries per host (advanced option) must be a positive integer");
	}

	if (($post['max-src-conn-rate'] != "") && !is_posnumericint($post['max-src-conn-rate'])) {
		$input_errors[] = gettext("Maximum new connections per host / per second(s) (advanced option) must be a positive integer");
	}

	if (($post['statetimeout'] != "") && !is_posnumericint($post['statetimeout'])) {
		$input_errors[] = gettext("State timeout (advanced option) must be a positive integer");
	}

	if ((($post['max-src-conn-rate'] <> "" and $post['max-src-conn-rates'] == "")) ||
	    (($post['max-src-conn-rate'] == "" and $post['max-src-conn-rates'] <> ""))) {
		$input_errors[] = gettext("Both maximum new connections per host and the interval (per second(s)) must be specified");
	}

	if (!$post['tcpflags_any']) {
		$settcpflags = array();
		$outoftcpflags = array();
		foreach ($tcpflags as $tcpflag) {
			if ($post['tcpflags1_' . $tcpflag] == "on") {
				$settcpflags[] = $tcpflag;
			}
			if ($post['tcpflags2_' . $tcpflag] == "on") {
				$outoftcpflags[] = $tcpflag;
			}
		}
		if (empty($outoftcpflags) && !empty($settcpflags)) {
			$input_errors[] = gettext("If TCP flags that should be set is specified, then out of which flags should be specified as well.");
		} else {
			/* https://redmine.pfsense.org/issues/11762 */
			foreach ($settcpflags as $tcpflag) {
				if (!in_array($tcpflag, $outoftcpflags)) {
					$input_errors[] = sprintf(gettext("The TCP flag %s must be checked in 'out of' list."), strtoupper($tcpflag));
				}
			}
		}
	}

	if ($post['dscp'] && !in_array($post['dscp'], $firewall_rules_dscp_types)) {
		$input_errors[] = gettext("Invalid DSCP value.");
	}
	if ($post['tag'] && !is_validaliasname($post['tag'])) {
		$input_errors[] = gettext("Invalid tag value.");
	}
	if ($post['tagged'] && !is_validaliasname($post['tagged'])) {
		$input_errors[] = gettext("Invalid tagged value.");
	}
	if ($post['statetype'] && !array_key_exists($post['statetype'], $statetype_values)) {
		$input_errors[] = gettext("Invalid State Type.");
	}
	if ($post['vlanprio'] && !array_key_exists($post['vlanprio'], $vlanprio)) {
		$input_errors[] = gettext("Invalid VLAN Prio.");
	}
	if ($post['vlanprioset'] && !array_key_exists($post['vlanprioset'], $vlanprio)) {
		$input_errors[] = gettext("Invalid VLAN Prio Set.");
	}

	if ($post['ackqueue'] && !array_key_exists($post['ackqueue'], $list)) {
		$input_errors[] = gettext("Invalid ACK Queue.");
	}
	if ($post['defaultqueue'] && !array_key_exists($post['defaultqueue'], $list)) {
		$input_errors[] = gettext("Invalid Default Queue.");
	}

	if ($post['dnpipe'] && !array_key_exists($post['dnpipe'], $dnqlist)) {
		$input_errors[] = gettext("Invalid In Pipe.");
	}
	if ($post['pdnpipe'] && !array_key_exists($post['pdnpipe'], $dnqlist)) {
		$input_errors[] = gettext("Invalid Out Pipe.");
	}

	// Allow extending of the firewall edit page and include custom input validation
	pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/input_validation");

	if (!$input_errors) {
		$filterent = array();
		$filterent['id'] = $post['ruleid']>0?$post['ruleid']:'';

		$filterent['tracker'] = empty($post['tracker']) ? (int)microtime(true) : $post['tracker'];

		$filterent['type'] = $post['type'];

		if (isset($post['interface'])) {
			$filterent['interface'] = $post['interface'];
		} // FIXME: can $post['interface'] be unset at this point, if so then what?

		$filterent['ipprotocol'] = $post['ipprotocol'];

		if ($post['tcpflags_any']) {
			$filterent['tcpflags_any'] = true;
		} else {
			$settcpflags = array();
			$outoftcpflags = array();
			foreach ($tcpflags as $tcpflag) {
				if ($post['tcpflags1_' . $tcpflag] == "on") {
					$settcpflags[] = $tcpflag;
				}
				if ($post['tcpflags2_' . $tcpflag] == "on") {
					$outoftcpflags[] = $tcpflag;
				}
			}
			if (!empty($outoftcpflags)) {
				$filterent['tcpflags2'] = join(",", $outoftcpflags);
				if (!empty($settcpflags)) {
					$filterent['tcpflags1'] = join(",", $settcpflags);
				}
			}
		}

		if (isset($post['tag'])) {
			$filterent['tag'] = $post['tag'];
		}
		if (isset($post['tagged'])) {
			$filterent['tagged'] = $post['tagged'];
		}
		if ($if == "FloatingRules" || isset($post['floating'])) {
			$filterent['direction'] = $post['direction'];
			if (isset($post['quick']) && $post['quick'] <> "") {
				$filterent['quick'] = $post['quick'];
			}
			$filterent['floating'] = "yes";
			if (isset($post['interface']) && count($post['interface']) > 0) {
				$filterent['interface'] = implode(",", $post['interface']);
			}
		}

		/* Advanced options */
		if ($post['allowopts'] == "yes") {
			$filterent['allowopts'] = true;
		} else {
			unset($filterent['allowopts']);
		}
		if ($post['disablereplyto'] == "yes") {
			$filterent['disablereplyto'] = true;
		} else {
			unset($filterent['disablereplyto']);
		}
		if ($post['nottagged'] == "yes") {
			$filterent['nottagged'] = true;
		} else {
			unset($filterent['nottagged']);
		}
		$filterent['max'] = $post['max'];
		$filterent['max-src-nodes'] = $post['max-src-nodes'];
		$filterent['max-src-conn'] = $post['max-src-conn'];
		$filterent['max-src-states'] = $post['max-src-states'];
		$filterent['statetimeout'] = $post['statetimeout'];
		$filterent['statetype'] = $post['statetype'];
		$filterent['os'] = $post['os'];
		if ($post['nopfsync'] <> "") {
			$filterent['nopfsync'] = true;
		} else {
			unset($filterent['nopfsync']);
		}

		/* Nosync directive - do not xmlrpc sync this item */
		if ($post['nosync'] <> "") {
			$filterent['nosync'] = true;
		} else {
			unset($filterent['nosync']);
		}

		/* unless both values are provided, unset the values - ticket #650 */
		if ($post['max-src-conn-rate'] <> "" and $post['max-src-conn-rates'] <> "") {
			$filterent['max-src-conn-rate'] = $post['max-src-conn-rate'];
			$filterent['max-src-conn-rates'] = $post['max-src-conn-rates'];
		} else {
			unset($filterent['max-src-conn-rate']);
			unset($filterent['max-src-conn-rates']);
		}

		if ($post['proto'] != "any") {
			$filterent['protocol'] = $post['proto'];
		} else {
			unset($filterent['protocol']);
		}

		// Convert array of selected ICMP types to comma-separated string, for backwards compatibility (previously only allowed one type per rule)
		if ($post['proto'] == "icmp" && is_array($post['icmptype']) && !isset($post['icmptype']['any']) && count($post['icmptype']) > 0) {
			//if any of these conditions not met, rule would apply to all icmptypes, so we would unset
			$filterent['icmptype'] = implode(',', $post['icmptype']);
		} else {
			unset($filterent['icmptype']);
		}

		pconfig_to_address($filterent['source'], $post['src'],
			$post['srcmask'], $post['srcnot'],
			$post['srcbeginport'], $post['srcendport']);

		pconfig_to_address($filterent['destination'], $post['dst'],
			$post['dstmask'], $post['dstnot'],
			$post['dstbeginport'], $post['dstendport']);

		if ($post['disabled']) {
			$filterent['disabled'] = true;
		} else {
			unset($filterent['disabled']);
		}

		if ($post['dscp']) {
			$filterent['dscp'] = $post['dscp'];
		}

		if ($post['log']) {
			$filterent['log'] = true;
		} else {
			unset($filterent['log']);
		}

		$filterent['descr'] = trim($post['descr']);

		if ($post['gateway'] != "") {
			$filterent['gateway'] = $post['gateway'];
		}

		if ($post['defaultqueue'] != "") {
			$filterent['defaultqueue'] = $post['defaultqueue'];
			if ($post['ackqueue'] != "") {
				$filterent['ackqueue'] = $post['ackqueue'];
			}
		}

		if ($post['dnpipe'] != "") {
			$filterent['dnpipe'] = $post['dnpipe'];
			if ($post['pdnpipe'] != "") {
				$filterent['pdnpipe'] = $post['pdnpipe'];
			}
		}

		if ($post['sched'] != "") {
			$filterent['sched'] = $post['sched'];
		}

		if ($post['vlanprio'] != "") {
			$filterent['vlanprio'] = $post['vlanprio'];
		}
		if ($post['vlanprioset'] != "") {
			$filterent['vlanprioset'] = $post['vlanprioset'];
		}

		// If we have an associated nat rule, make sure the source and destination doesn't change
		if (isset($a_filter[$id]['associated-rule-id'])) {
			$filterent['interface'] = $a_filter[$id]['interface'];
			if (isset($a_filter[$id]['protocol'])) {
				$filterent['protocol'] = $a_filter[$id]['protocol'];
			} else if (isset($filterent['protocol'])) {
				unset($filterent['protocol']);
			}
			if ($a_filter[$id]['protocol'] == "icmp" && $a_filter[$id]['icmptype']) {
				$filterent['icmptype'] = $a_filter[$id]['icmptype'];
			} else if (isset($filterent['icmptype'])) {
				unset($filterent['icmptype']);
			}

			$filterent['source'] = $a_filter[$id]['source'];
			$filterent['destination'] = $a_filter[$id]['destination'];
			$filterent['associated-rule-id'] = $a_filter[$id]['associated-rule-id'];
		}

		if (isset($a_filter[$id]['created']) && is_array($a_filter[$id]['created'])) {
			$filterent['created'] = $a_filter[$id]['created'];
		}

		$filterent['updated'] = make_config_revision_entry();

		// Allow extending of the firewall edit page and include custom input validation
		pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/pre_write_config");

		if (isset($id) && $a_filter[$id]) {
			$tmpif = $filterent['interface'];
			if (($tmpif == $if) || (isset($pconfig['floating']))) {
				$a_filter[$id] = $filterent;
			} else {							// rule moved to different interface
				// Update the separators of previous interface.
				init_config_arr(array('filter', 'separator', strtolower($if)));
				$a_separators = &$config['filter']['separator'][strtolower($if)];
				$ridx = ifridx($if, $id);		// get rule index within interface
				$mvnrows = -1;
				move_separators($a_separators, $ridx, $mvnrows);

				$a_filter[$id] = $filterent;	// save edited rule to new interface

				// Update the separators of new interface.
				init_config_arr(array('filter', 'separator', strtolower($tmpif)));
				$a_separators = &$config['filter']['separator'][strtolower($tmpif)];
				$ridx = ifridx($tmpif, $id);	// get rule index within interface
				if ($ridx == 0) {				// rule was placed at the top
					$ridx = -1;					// move all separators
				}
				$mvnrows = +1;
				move_separators($a_separators, $ridx, $mvnrows);
			}

		} else {
			$filterent['created'] = make_config_revision_entry();
			if (is_numeric($after)) {
				array_splice($a_filter, $after+1, 0, array($filterent));

				// For copy/dup the $if var is taken from the rule submission.
				// In the case of floating rules that could be anything.  But never "FloatingRules" that is needed.
				if (isset($pconfig['floating'])) {
					$tmpif = 'FloatingRules';
				} else if (isset($filterent['interface'])) {
					$tmpif = $filterent['interface'];
					if ($tmpif != $if) {					// rule copied to different interface
						$ridx = ifridx($tmpif, $after+1);	// get rule index within interface
						if ($ridx == 0) {					// rule was placed at the top
							$after = -1;					// move all separators
						}
					}
				} else {
					$tmpif = $if;
				}

				// Update the separators
				init_config_arr(array('filter', 'separator', strtolower($tmpif)));
				$a_separators = &$config['filter']['separator'][strtolower($tmpif)];
				$ridx = ifridx($tmpif, $after);	// get rule index within interface
				$mvnrows = +1;
				move_separators($a_separators, $ridx, $mvnrows);
			} else {
				$a_filter[] = $filterent;
			}
		}

		filter_rules_sort();

		if (write_config(gettext("Firewall: Rules - saved/edited a firewall rule."))) {
			if(!$json) {
        mark_subsystem_dirty('filter');
      } else {
        filter_configure();
      }
		}
  }

  $rv = array();
  $rv['input_errors'] = $input_errors;
  $rv['pconfig'] = $pconfig;

  return $json ? json_encode($rv) : $rv;
}

function toggleFilterRule($post, $if, $json = false) {
  global $config;
  $a_filter = &$config['filter']['rule'];
  if ($a_filter[$post['id']]) {
		if (isset($a_filter[$post['id']]['disabled'])) {
			unset($a_filter[$post['id']]['disabled']);
			$wc_msg = gettext('Firewall: Rules - enabled a firewall rule.');
		} else {
			$a_filter[$post['id']]['disabled'] = true;
			$wc_msg = gettext('Firewall: Rules - disabled a firewall rule.');
		}
		if (write_config($wc_msg)) {
			mark_subsystem_dirty('filter');
		}
    if (!$json) {
		  header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
		  exit;
    }
	}
}

function deleteMultipleFilterRules($post, $if, $json = false) {
  global $config;
  $a_filter = &$config['filter']['rule'];

  if (is_array($post['rule']) && count($post['rule'])) {
		init_config_arr(array('filter', 'separator', strtolower($if)));
		$a_separators = &$config['filter']['separator'][strtolower($if)];

		$first_idx = 0;		
		$num_deleted = 0;
		foreach ($post['rule'] as $rulei) {
			delete_nat_association($a_filter[$rulei]['associated-rule-id']);
			unset($a_filter[$rulei]);

			// Capture first changed filter index for later separator shifting
			if (!$first_idx) $first_idx = ifridx($if, $rulei);
			$num_deleted++;
		}

		if ($num_deleted) {
			move_separators($a_separators, $first_idx, -$num_deleted);
			if (write_config(gettext("Firewall: Rules - deleted selected firewall rules."))) {
				mark_subsystem_dirty('filter');
			}
		}

    if (!$json) {
		  header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
		  exit;
    }
	}
}

function deleteFilterRule($post, $if, $json = false) {
  global $config;
  $a_filter = &$config['filter']['rule'];
  
    
  if ($a_filter[$post['id']]) {
		if (!empty($a_filter[$post['id']]['associated-rule-id'])) {
			delete_nat_association($a_filter[$post['id']]['associated-rule-id']);
		}
		unset($a_filter[$post['id']]);

		// Update the separators
		init_config_arr(array('filter', 'separator', strtolower($if)));
		$a_separators = &$config['filter']['separator'][strtolower($if)];
		$ridx = ifridx($if, $post['id']);	// get rule index within interface
		$mvnrows = -1;
		move_separators($a_separators, $ridx, $mvnrows);

		if (write_config(gettext("Firewall: Rules - deleted a firewall rule."))) {
      if ($json) {
        filter_configure();
      } else {
        mark_subsystem_dirty('filter');
      }
		}

    if (!$json) {
      header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
      exit;
    }
	}
}

function applyFilterRules() {
  $retval = 0;
	$retval |= filter_configure();

  if ($retval == 0) {
    clear_subsystem_dirty('filter');
  }

  return $retval;
}

function reorderFilterRules($post, $if, $json = false) {
  global $config;
  $a_filter = &$config['filter']['rule'];
  $updated = false;
	$dirty = false;


	/* update rule order, POST[rule] is an array of ordered IDs */
	if (is_array($post['rule']) && !empty($post['rule'])) {
		$a_filter_new = array();

		// Include the rules of other interfaces listed in config before this (the selected) interface.
		foreach ($a_filter as $filteri_before => $filterent) {
			if (($filterent['interface'] == $if && !isset($filterent['floating'])) || (isset($filterent['floating']) && "FloatingRules" == $if)) {
				break;
			} else {
				$a_filter_new[] = $filterent;
			}
		}

		// Include the rules of this (the selected) interface.
		// If a rule is not in POST[rule], it has been deleted by the user
		foreach ($post['rule'] as $id) {
			$a_filter_new[] = $a_filter[$id];
		}

		// Include the rules of other interfaces listed in config after this (the selected) interface.
		foreach ($a_filter as $filteri_after => $filterent) {
			if ($filteri_before > $filteri_after) {
				continue;
			}
			if (($filterent['interface'] == $if && !isset($filterent['floating'])) || (isset($filterent['floating']) && "FloatingRules" == $if)) {
				continue;
			} else {
				$a_filter_new[] = $filterent;
			}
		}

		if ($a_filter !== $a_filter_new) {
			$a_filter = $a_filter_new;
			$dirty = true;
		}
	}

	$a_separators = &$config['filter']['separator'][strtolower($if)];

	/* update separator order, POST[separator] is an array of ordered IDs */
	if (is_array($post['separator']) && !empty($post['separator'])) {
		$new_separator = array();
		$idx = 0;

		foreach ($post['separator'] as $separator) {
			$new_separator['sep' . $idx++] = $separator;
		}

		if ($a_separators !== $new_separator) {
			$a_separators = $new_separator;
			$updated = true;
		}
	} else if (!empty($a_separators)) {
		$a_separators = "";
		$updated = true;
	}

	if ($updated || $dirty) {
		if (write_config(gettext("Firewall: Rules - reordered firewall rules."))) {
			if ($dirty) {
				mark_subsystem_dirty('filter');
			}
		}
	}
  if(!$json) {
	  header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
	  exit;
  }
}
  