<?php
/*
 * firewall_nat.inc
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

// Functions to support firewall_nat.php and firewall_nat_edit.php

require_once("config.gui.inc");
require_once("interfaces.inc");
require_once("util.inc");
require_once("pfsense-utils.inc");
require_once("ipsec.inc");
require_once("filter.inc");
require_once("itemid.inc");

$specialsrcdst = explode(" ", "any pptp pppoe l2tp openvpn");
$ifdisp = get_configured_interface_with_descr();
foreach ($ifdisp as $kif => $kdescr) {
	$specialsrcdst[] = "{$kif}";
	$specialsrcdst[] = "{$kif}ip";
}

function build_localtype_list($json = false) {
	global $pconfig, $ifdisp;

	$list = array('single' => gettext('Single host'));

	foreach ($ifdisp as $ifent => $ifdesc) {
		if ($json || have_ruleint_access($ifent)) {
			$list[$ifent . 'ip'] = $ifdesc . ' address';
		}
	}

	if ($json) {
		$rv = array();

		foreach ($list as $ifent => $ifname) {
			$rv[] = array("text" => $ifname, "value" => $ifent);
		}

		return(json_encode($rv));
	} else {
		return($list);
	}
}

function build_dsttype_list($json = false) {
	global $config, $ifdisp;

	$list = array('any' => gettext('Any'), 'single' => gettext('Single host or alias'), 'network' => gettext('Network'), '(self)' => gettext('This Firewall (self)'));

	if ($json || have_ruleint_access("pppoe")) {
		$list['pppoe'] = gettext('PPPoE clients');
	}

	if ($json || have_ruleint_access("l2tp")) {
		$list['l2tp'] = gettext('L2TP clients');
	}

	foreach ($ifdisp as $ifent => $ifdesc) {
		if ($json || have_ruleint_access($ifent)) {
			$list[$ifent] = $ifdesc . ' net';
			$list[$ifent . 'ip'] = $ifdesc . ' address';
		}
	}

	//Temporary array so we can sort IPs
	$templist = array();
	if (is_array($config['virtualip']['vip'])) {
		foreach ($config['virtualip']['vip'] as $sn) {
			if ((($sn['mode'] == "proxyarp") || ($sn['mode'] == "other")) &&
			    ($sn['type'] == "network") && is_subnetv4($sn['subnet'] . "/" . $sn['subnet_bits'])) {
				$templist[$sn['subnet'] . '/' . $sn['subnet_bits']] = 'Subnet: ' . $sn['subnet'] . '/' . $sn['subnet_bits'] . ' (' . $sn['descr'] . ')';
				if (isset($sn['noexpand'])) {
					continue;
				}
				$start = ip2long32(gen_subnet($sn['subnet'], $sn['subnet_bits']));
				$end = ip2long32(gen_subnet_max($sn['subnet'], $sn['subnet_bits']));
				$len = $end - $start;

				for ($i = 0; $i <= $len; $i++) {
					$snip = long2ip32($start+$i);

					$templist[$snip] = $snip . ' (' . $sn['descr'] . ')';
				}
			} else {
				$templist[$sn['subnet']] = $sn['subnet'] . ' (' . $sn['descr'] . ')';
			}
		}
	}

	//Sort temp IP array and append onto main array
	asort($templist);
	$list = array_merge($list, $templist);
	unset($templist);

	if ($json) {
		$rv = array();

		foreach ($list as $ifent => $ifname) {
			$rv[] = array("text" => $ifname, "value" => $ifent);
		}

		return(json_encode($rv));
	} else {
		return($list);
	}
}

function build_srctype_list($json = false) {
	global $ifdisp, $config;

	$list = array('any' => gettext('Any'), 'single' => gettext('Single host or alias'), 'network' => gettext('Network'));

	if ($json || have_ruleint_access("pppoe")) {
		$list['pppoe'] = gettext('PPPoE clients');
	}

	if ($json || have_ruleint_access("l2tp")) {
		$list['l2tp'] = gettext('L2TP clients');
	}

	foreach ($ifdisp as $ifent => $ifdesc) {
		if ($json || have_ruleint_access($ifent)) {
			$list[$ifent] = $ifdesc . ' net';
			$list[$ifent . 'ip'] = $ifdesc . ' address';
		}
	}

	if ($json) {
		$rv = array();

		foreach ($list as $ifent => $ifname) {
			$rv[] = array("text" => $ifname, "value" => $ifent);
		}

		return(json_encode($rv));
	} else {
		return($list);
	}
}

function saveNATrule($post, $id, $json = false) {
	global $config, $ifdisp;

	init_config_arr(array('filter', 'rule'));
	init_config_arr(array('nat', 'separator'));
	init_config_arr(array('nat', 'rule'));
	$a_nat = &$config['nat']['rule'];
	$a_separators = &$config['nat']['separator'];

	$input_errors = array();

	if (isset($post['after'])) {
		$after = $post['after'];
	}

	if (strtoupper($post['proto']) == "TCP" || strtoupper($post['proto']) == "UDP" || strtoupper($post['proto']) == "TCP/UDP") {
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
			$post['dstbeginport'] = "1";
			$post['dstendport'] = "65535";
			$post['localbeginport'] = "1";
		} else {
			if (!$post['dstendport']) {
				$post['dstendport'] = $post['dstbeginport'];
			}
		}
		if ($post['dstendport'] == "any") {
			$post['dstendport'] = $post['dstbeginport'];
		}

		if ($post['localbeginport_cust'] && !$post['localbeginport']) {
			$post['localbeginport'] = trim($post['localbeginport_cust']);
		}

		/* Make beginning port end port if not defined and endport is */
		if (!$post['srcbeginport'] && $post['srcendport']) {
			$post['srcbeginport'] = $post['srcendport'];
		}
		if (!$post['dstbeginport'] && $post['dstendport']) {
			$post['dstbeginport'] = $post['dstendport'];
		}
	} else {
		$post['srcbeginport'] = 0;
		$post['srcendport'] = 0;
		$post['dstbeginport'] = 0;
		$post['dstendport'] = 0;
	}

	if (is_specialnet($post['srctype'])) {
		$post['src'] = $post['srctype'];
		$post['srcmask'] = 0;
	} elseif ($post['srctype'] == "single") {
		if (is_ipaddrv6($post['src'])) {
			$post['srcmask'] = 128;
		} else {
			$post['srcmask'] = 32;
		}
	}

	if (is_specialnet($post['dsttype'])) {
		$post['dst'] = $post['dsttype'];
		$post['dstmask'] = 0;
	} elseif ($post['dsttype'] == "single") {
		if (is_ipaddrv6($post['dst'])) {
			$post['dstmask'] = 128;
		} else {
			$post['dstmask'] = 32;
		}
	} elseif (is_ipaddr($post['dsttype'])) {
		$post['dst'] = $post['dsttype'];
		$post['dsttype'] = "single";
		if (is_ipaddrv6($post['dst'])) {
			$post['dstmask'] = 128;
		} else {
			$post['dstmask'] = 32;
		}
	}

	if (is_specialnet($post['localtype'])) {
		$post['localip'] = $post['localtype'];
	}

	$pconfig = $post;

	/* input validation */
	if (strtoupper($post['proto']) == "TCP" or strtoupper($post['proto']) == "UDP" or strtoupper($post['proto']) == "TCP/UDP") {
		$reqdfields = explode(" ", "interface proto dstbeginport dstendport");
		$reqdfieldsn = array(gettext("Interface"), gettext("Protocol"), gettext("Destination port from"), gettext("Destination port to"));
	} else {
		$reqdfields = explode(" ", "interface proto");
		$reqdfieldsn = array(gettext("Interface"), gettext("Protocol"));
	}

	if ($post['srctype'] == "single" || $post['srctype'] == "network") {
		$reqdfields[] = "src";
		$reqdfieldsn[] = gettext("Source address");
	}

	if ($post['dsttype'] == "single" || $post['dsttype'] == "network") {
		$reqdfields[] = "dst";
		$reqdfieldsn[] = gettext("Destination address");
	}

	if (!isset($post['nordr'])) {
		$reqdfields[] = "localip";
		$reqdfieldsn[] = gettext("Redirect target IP");
	}

	if (!$json) {
		do_input_validation($post, $reqdfields, $reqdfieldsn, $input_errors);
	}

	if (!$post['srcbeginport']) {
		$post['srcbeginport'] = 0;
		$post['srcendport'] = 0;
	}

	if (!$post['dstbeginport']) {
		$post['dstbeginport'] = 0;
		$post['dstendport'] = 0;
	}

	if ($post['src']) {
		$post['src'] = trim($post['src']);
	}

	if ($post['dst']) {
		$post['dst'] = trim($post['dst']);
	}

	if ($post['localip']) {
		$post['localip'] = trim($post['localip']);
	}

	if (!array_key_exists($post['interface'], create_interface_list($json))) {
		$input_errors[] = gettext("The submitted interface does not exist.");
	}

	if (!isset($post['nordr']) && $post['localip'] &&
	    !is_ipaddroralias($post['localip']) && !is_specialnet($post['localtype'])) {
		$input_errors[] = sprintf(gettext("\"%s\" is not a valid redirect target IP address or host alias."), $post['localip']);
	}

	if ($post['localip']) {
		if (is_specialnet($post['localtype'])) {
			foreach ($ifdisp as $kif => $kdescr) {
				if ($post['localtype'] == "{$kif}ip") {
					if (($post['ipprotocol'] == 'inet') && !get_interface_ip($kif)) {
						$input_errors[] = sprintf(gettext("Redirect interface must have IPv4 address."));
						break;
					} elseif (($post['ipprotocol'] == 'inet6') && !get_interface_ipv6($kif)) {
						$input_errors[] = sprintf(gettext("Redirect interface must have IPv6 address."));
						break;
					}
				}
			}
		} elseif (($post['ipprotocol'] == 'inet') && is_ipaddrv6($post['localip'])) {
			$input_errors[] = sprintf(gettext("Redirect target IP must be IPv4."));
		} elseif (($post['ipprotocol'] == 'inet6') && is_ipaddrv4($post['localip'])) {
			$input_errors[] = sprintf(gettext("Redirect target IP must be IPv6."));
		}
	}

	if ($post['srcbeginport'] && !is_port_or_alias($post['srcbeginport'])) {
		$input_errors[] = sprintf(gettext("%s is not a valid start source port. It must be a port alias or integer between 1 and 65535."), $post['srcbeginport']);
	}
	if ($post['srcendport'] && !is_port_or_alias($post['srcendport'])) {
		$input_errors[] = sprintf(gettext("%s is not a valid end source port. It must be a port alias or integer between 1 and 65535."), $post['srcendport']);
	}
	if ($post['srcbeginport'] && $post['srcendport']) {
		if (is_port($post['srcbeginport']) && is_alias($post['srcendport']) ||
		    (is_alias($post['srcbeginport']) && is_port($post['srcendport']))) { 
			$input_errors[] = gettext("Source port range From/To values must a port number or alias, but not both.");
		} elseif (is_alias($post['srcbeginport']) && is_alias($post['srcendport']) &&
		    ($post['srcbeginport'] != $post['srcendport'])) {
			$input_errors[] = gettext("The 'From' alias of the Source port range must be equal to the 'To' alias.");
		}
	}
	if ($post['dstbeginport'] && !is_port_or_alias($post['dstbeginport'])) {
		$input_errors[] = sprintf(gettext("%s is not a valid start destination port. It must be a port alias or integer between 1 and 65535."), $post['dstbeginport']);
	}
	if ($post['dstendport'] && !is_port_or_alias($post['dstendport'])) {
		$input_errors[] = sprintf(gettext("%s is not a valid end destination port. It must be a port alias or integer between 1 and 65535."), $post['dstendport']);
	}
	if ($post['dstbeginport'] && $post['dstendport']) {
		if (is_port($post['dstbeginport']) && is_alias($post['dstendport']) ||
		    (is_alias($post['dstbeginport']) && is_port($post['dstendport']))) { 
			$input_errors[] = gettext("Destination port range From/To values must a port number or alias, but not both.");
		} elseif (is_alias($post['dstbeginport']) && is_alias($post['dstendport']) &&
		    ($post['dstbeginport'] != $post['dstendport'])) {
			$input_errors[] = gettext("The 'From' alias of the Destination port range must be equal to the 'To' alias.");
		}
	}

	if ((strtoupper($post['proto']) == "TCP" || strtoupper($post['proto']) == "UDP" || strtoupper($post['proto']) == "TCP/UDP") && (!isset($post['nordr']) && !is_port_or_alias($post['localbeginport']))) {
		$input_errors[] = sprintf(gettext("Redirect target port %s is not valid. It must be a port alias or integer between 1 and 65535."), $post['localbeginport']);
	}

	/* if user enters an alias and selects "network" then disallow. */
	if (($post['srctype'] == "network" && is_alias($post['src'])) ||
	    ($post['dsttype'] == "network" && is_alias($post['dst']))) {
		$input_errors[] = gettext("Alias entries must specify a single host or alias.");
	}

	if (!is_specialnet($post['srctype'])) {
		if (($post['src'] && !is_ipaddroralias($post['src']))) {
			$input_errors[] = sprintf(gettext("%s is not a valid source IP address or alias."), $post['src']);
		}
		if ($post['src']) {
			if (($post['ipprotocol'] == 'inet') && is_ipaddrv6($post['src'])) {
				$input_errors[] = sprintf(gettext("Source must be IPv4."));
			} elseif (($post['ipprotocol'] == 'inet6') && is_ipaddrv4($post['src'])) {
				$input_errors[] = sprintf(gettext("Source must be IPv6."));
			}
		}
		if (is_ipaddr($post['src']) && !is_subnet($post['src'] . '/' . $post['srcmask'])) {
			$input_errors[] = gettext("A valid source bit count must be specified.");
		}
	}

	if (!is_specialnet($post['dsttype'])) {
		if (($post['dst'] && !is_ipaddroralias($post['dst']))) {
			$input_errors[] = sprintf(gettext("%s is not a valid destination IP address or alias."), $post['dst']);
		}
		if ($post['dst']) {
			if (($post['ipprotocol'] == 'inet') && is_ipaddrv6($post['dst'])) {
				$input_errors[] = sprintf(gettext("Destination must be IPv4."));
			} elseif (($post['ipprotocol'] == 'inet6') && is_ipaddrv4($post['dst'])) {
				$input_errors[] = sprintf(gettext("Destination must be IPv6."));
			}
		}
		if (is_ipaddr($post['dst']) && !is_subnet($post['dst'] . '/' . $post['dstmask'])) {
			$input_errors[] = gettext("A valid destination bit count must be specified.");
		}
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

	if (!$input_errors) {
		if (!isset($post['nordr']) && ((int) $post['dstendport'] - (int) $post['dstbeginport'] + (int) $post['localbeginport']) > 65535) {
			$input_errors[] = gettext("The target port range must be an integer between 1 and 65535.");
		}
	}

	/* check for overlaps */
	foreach ($a_nat as $natent) {
		if (isset($id) && ($a_nat[$id]) && ($a_nat[$id] === $natent)) {
			continue;
		}
		if ($natent['interface'] != $post['interface']) {
			continue;
		}
 		if ((($post['srctype'] == "network") && ($natent['source']['address'] != ($post['src'] . '/' . $post['srcmask']))) ||
		    (($post['srctype'] == "single") && ($natent['source']['address'] != $post['src'])) ||
		    ((is_specialnet($post['srctype'])) && ($natent['source']['network'] != $post['src']))) {
			continue;
		}
		if ((($post['dsttype'] == "network") && ($natent['destination']['address'] != ($post['dst'] . '/' . $post['dstmask']))) ||
		    (($post['dsttype'] == "single") && ($natent['destination']['address'] != $post['dst'])) ||
		    ((is_specialnet($post['dsttype'])) && ($natent['destination']['network'] != $post['dst']))) {
			continue;
		}
		if (($natent['protocol'] != $post['proto']) &&
		    (strtoupper($natent['protocol']) != "TCP/UDP") && (strtoupper($post['proto']) != "TCP/UDP")) {
			continue;
		}

		list($begp, $endp) = explode("-", $natent['destination']['port']);
		if (!$endp) {
			$endp = $begp;
		}

		if (!((($post['dstbeginport'] < $begp) && ($post['dstendport'] < $begp)) ||
		      (($post['dstbeginport'] > $endp) && ($post['dstendport'] > $endp)))) {
			$input_errors[] = gettext("The destination port range overlaps with an existing entry.");
			break;
		}
	}

	if (!$input_errors) {

		$natent = array();

		if (isset($post['disabled'])) {
			$natent['disabled'] = true;
		}

		if (isset($post['nordr'])) {
			$natent['nordr'] = true;
		}

		if ($natent['nordr']) {
			$post['associated-rule-id'] = '';
			$post['filter-rule-association'] = '';
		}

		pconfig_to_address($natent['source'], $post['src'],
			$post['srcmask'], $post['srcnot'],
			$post['srcbeginport'], $post['srcendport']);

		pconfig_to_address($natent['destination'], $post['dst'],
			$post['dstmask'], $post['dstnot'],
			$post['dstbeginport'], $post['dstendport']);

		$natent['ipprotocol'] = $post['ipprotocol'];
		$natent['protocol'] = $post['proto'];

		if (!isset($natent['nordr'])) {
			$natent['target'] = $post['localip'];
			$natent['local-port'] = $post['localbeginport'];
		}

		$natent['interface'] = $post['interface'];
		$natent['descr'] = $post['descr'];
		$natent['associated-rule-id'] = $post['associated-rule-id'];

		if ($post['filter-rule-association'] == "pass") {
			$natent['associated-rule-id'] = "pass";
		}

		if ($post['nosync'] == "yes") {
			$natent['nosync'] = true;
		} else {
			unset($natent['nosync']);
		}

		if ($post['natreflection'] == "enable" || $post['natreflection'] == "purenat" || $post['natreflection'] == "disable") {
			$natent['natreflection'] = $post['natreflection'];
		} else {
			unset($natent['natreflection']);
		}

		// If we used to have an associated filter rule, but no-longer should have one
		if (!empty($a_nat[$id]) && (empty($natent['associated-rule-id']) || $natent['associated-rule-id'] != $a_nat[$id]['associated-rule-id'])) {
			// Delete the previous rule
			delete_id($a_nat[$id]['associated-rule-id'], $config['filter']['rule']);
			if (!$json) {
				mark_subsystem_dirty('filter');
			}
		}

		$need_filter_rule = false;
		// Updating a rule with a filter rule associated
		if (!empty($natent['associated-rule-id'])) {
			$need_filter_rule = true;
		}
		// Create a rule or if we want to create a new one
		if ($natent['associated-rule-id'] == 'new') {
			$need_filter_rule = true;
			unset($natent['associated-rule-id']);
			$post['filter-rule-association']='add-associated';
		}
		// If creating a new rule, where we want to add the filter rule, associated or not
		else if (isset($post['filter-rule-association']) &&
		    ($post['filter-rule-association'] == 'add-associated' ||
		     $post['filter-rule-association'] == 'add-unassociated')) {
			$need_filter_rule = true;
		}

		if ($need_filter_rule == true) {
			/* auto-generate a matching firewall rule */
			$filterent = array();
			unset($filterentid);

			// If a rule already exists, load it
			if (!empty($natent['associated-rule-id'])) {
				$filterentid = get_id($natent['associated-rule-id'], $config['filter']['rule']);
				if ($filterentid === false) {
					$filterent['associated-rule-id'] = $natent['associated-rule-id'];
				} else {
					$filterent =& $config['filter']['rule'][$filterentid];
				}
			}

			pconfig_to_address($filterent['source'], $post['src'],
				$post['srcmask'], $post['srcnot'],
				$post['srcbeginport'], $post['srcendport']);

			// Update interface, protocol and destination
			$filterent['interface'] = $post['interface'];
			$filterent['ipprotocol'] = $post['ipprotocol'];
			$filterent['protocol'] = $post['proto'];
			if (is_specialnet($post['localtype'])) {
				$filterent['destination']['network'] = $post['localtype'];
			} else {
				$filterent['destination']['address'] = $post['localip'];
			}

			if (isset($post['disabled'])) {
				$filterent['disabled'] = true;
			}

			$dstpfrom = $post['localbeginport'];
			$dstpto = (int) $dstpfrom + (int) $post['dstendport'] - (int) $post['dstbeginport'];

			if ($dstpfrom == $dstpto) {
				$filterent['destination']['port'] = $dstpfrom;
			} else {
				$filterent['destination']['port'] = $dstpfrom . "-" . $dstpto;
			}

			/*
			 * Our firewall filter description may be no longer than
			 * 63 characters, so don't let it be.
			 */
			$filterent['descr'] = substr("NAT " . $post['descr'], 0, 62);

			// If this is a new rule, create an ID and add the rule
			if ($post['filter-rule-association'] == 'add-associated') {
				$filterent['associated-rule-id'] = $natent['associated-rule-id'] = get_unique_id();
				$filterent['tracker'] = (int)microtime(true);
				$filterent['created'] = make_config_revision_entry(null, gettext("NAT Port Forward"));
				$config['filter']['rule'][] = $filterent;
			}

			if (!$json) {
				mark_subsystem_dirty('filter');
			}
		}

		if (isset($a_nat[$id]['created']) && is_array($a_nat[$id]['created'])) {
			$natent['created'] = $a_nat[$id]['created'];
		}

		$natent['updated'] = make_config_revision_entry();

		if (!$json) {
			// Allow extending of the firewall edit page and include custom input validation
			pfSense_handle_custom_code("/usr/local/pkg/firewall_nat/pre_write_config");
		}

		// Update the NAT entry now
		if (isset($id) && $a_nat[$id]) {
			if (isset($natent['associated-rule-id']) &&
			    (isset($a_nat[$id]['disabled']) !== isset($natent['disabled']))) {
				// Check for filter rule associations
				toggle_id($natent['associated-rule-id'],
				    $config['filter']['rule'],
				    !isset($natent['disabled']));
				
				if (!$json) {
					mark_subsystem_dirty('filter');
				}
			}
			$a_nat[$id] = $natent;
		} else {
			$natent['created'] = make_config_revision_entry();
			if (is_numeric($after)) {
				array_splice($a_nat, $after+1, 0, array($natent));

				// Update the separators
				$ridx = $after;
				$mvnrows = +1;
				move_separators($a_separators, $ridx, $mvnrows);
			} else {
				$a_nat[] = $natent;
			}
		}

		if (write_config(gettext("Firewall: NAT: Port Forward - saved/edited a port forward rule."))) {
			if (!$json) {
				mark_subsystem_dirty('natconf');
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

function toggleNATrule($post, $json = false) {
	global $config;

	init_config_arr(array('nat', 'rule'));
	$a_nat = &$config['nat']['rule'];
	init_config_arr(array('nat', 'separator'));
	$a_separators = &$config['nat']['separator'];

	if (isset($a_nat[$post['id']]['disabled'])) {
		unset($a_nat[$post['id']]['disabled']);
		$rule_status = true;
	} else {
		$a_nat[$post['id']]['disabled'] = true;
		$rule_status = false;
	}

	// Check for filter rule associations
	if (isset($a_nat[$post['id']]['associated-rule-id'])) {
		toggle_id($a_nat[$post['id']]['associated-rule-id'],
		    $config['filter']['rule'], $rule_status);
		unset($rule_status);

		if(!$json) {
			mark_subsystem_dirty('filter');
		}
	}

	if (write_config(gettext("Firewall: NAT: Port forward, enable/disable NAT rule"))) {
		if (!$json) {
			mark_subsystem_dirty('natconf');
		}
	}

	if(!$json) {
		header("Location: firewall_nat.php");
		exit;
	} else {
		$a_nat = &$config['nat']['rule'];
		return isset($a_nat[$post['id']]['disabled']) ? "disabled":"enabled";
	}
}

function deleteMultipleNATrules($post, $json = false) {
	global $config;

	init_config_arr(array('nat', 'rule'));
	$a_nat = &$config['nat']['rule'];
	init_config_arr(array('nat', 'separator'));
	$a_separators = &$config['nat']['separator'];

	$first_idx = 0;
	$num_deleted = 0;

	foreach ($post['rule'] as $rulei) {
		// Check for filter rule associations
		if (isset($a_nat[$rulei]['associated-rule-id'])) {
			delete_id($a_nat[$rulei]['associated-rule-id'], $config['filter']['rule']);
			if (!$json) {
				mark_subsystem_dirty('filter');
			}
		}

		unset($a_nat[$rulei]);

		// Capture first changed filter index for later separator shifting
		if (!$first_idx) {
			$first_idx = $rulei;
		}

		$num_deleted++;
	}

	if ($num_deleted) {
		move_separators($a_separators, $first_idx, -$num_deleted);
		if (write_config("NAT: Rule deleted")) {
			if ($json) {
				filter_configure();
			} else {
				mark_subsystem_dirty('natconf');
			}
		}
	}

	if(!$json) {
		header("Location: firewall_nat.php");
		exit;
	}
}

function deleteNATrule($post, $json = false) {
	global $config;

	init_config_arr(array('nat', 'rule'));
	$a_nat = &$config['nat']['rule'];
	init_config_arr(array('nat', 'separator'));
	$a_separators = &$config['nat']['separator'];

	if (isset($a_nat[$post['id']]['associated-rule-id'])) {
		delete_id($a_nat[$post['id']]['associated-rule-id'], $config['filter']['rule']);
		$want_dirty_filter = true;
	}

	unset($a_nat[$post['id']]);

	// Update the separators
	$ridx = $post['id'];
	$mvnrows = -1;
	move_separators($a_separators, $ridx, $mvnrows);

	if (write_config("NAT: Rule deleted")) {
		if ($json) {
			filter_configure();
		} else {
			mark_subsystem_dirty('natconf');
			if ($want_dirty_filter) {
				mark_subsystem_dirty('filter');
			}
		}
	}

	if(!$json) {
		header("Location: firewall_nat.php");
		exit;
	}
}

function applyNATrules() {
	$retval = 0;

	$retval |= filter_configure();

	pfSense_handle_custom_code("/usr/local/pkg/firewall_nat/apply");

	if ($retval == 0) {
		clear_subsystem_dirty('natconf');
		clear_subsystem_dirty('filter');
	}

	return $retval;
}

// Re-order the NAT rules per the array of iindicies passed in $post
function reorderNATrules($post, $json = false) {
	global $config;

	$updated = false;
	$dirty = false;

	init_config_arr(array('nat', 'separator'));
	init_config_arr(array('nat', 'rule'));
	$a_nat = &$config['nat']['rule'];
	$a_separators = &$config['nat']['separator'];

	/* update rule order, POST[rule] is an array of ordered IDs */
	if (is_array($post['rule']) && !empty($post['rule'])) {
		$a_nat_new = array();

		// if a rule is not in POST[rule], it has been deleted by the user
		foreach ($post['rule'] as $id) {
			$a_nat_new[] = $a_nat[$id];
		}

		if ($a_nat !== $a_nat_new) {
			$a_nat = $a_nat_new;
			$dirty = true;
		}
	}

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
		if (write_config("NAT: Rule order changed")) {
			if ($json) {
				filter_configure();
			} else if ($dirty) {
				mark_subsystem_dirty('natconf');
			}
		}
	}

	if(!$json) {
		header("Location: firewall_nat.php");
		exit;
	}
}

function MVC_filter_get_interface_list() {
	$iflist = MVC_create_interface_list();
	$filter_ifs = array();

	foreach ($iflist as $ifent => $ifname) {
		$filter_ifs[] = array("text" => $ifname, "value" => $ifent);
	}

	return json_encode($filter_ifs);
}

function MVC_create_interface_list() {
	global $config;

	$iflist = array();

	// add group interfaces
	if (isset($config['ifgroups']['ifgroupentry']) && is_array($config['ifgroups']['ifgroupentry'])) {
		foreach ($config['ifgroups']['ifgroupentry'] as $ifgen) {
			$iflist[$ifgen['ifname']] = $ifgen['ifname'];
		}
	}

	foreach (get_configured_interface_with_descr() as $ifent => $ifdesc) {
		$iflist[$ifent] = $ifdesc;
	}

	if ($config['l2tp']['mode'] == "server") {
		$iflist['l2tp'] = gettext('L2TP VPN');
	}

	if (is_pppoe_server_enabled()) {
		$iflist['pppoe'] = gettext("PPPoE Server");
	}

	// add ipsec interfaces
	if (ipsec_enabled()) {
		$iflist["enc0"] = gettext("IPsec");
	}

	// add openvpn/tun interfaces
	if ($config['openvpn']["openvpn-server"] || $config['openvpn']["openvpn-client"]) {
		$iflist["openvpn"] = gettext("OpenVPN");
	}

	return($iflist);
}

function getNATRule($id, $json = false) {
	global $config;

	init_config_arr(array('nat', 'rule'));
	$a_nat = &$config['nat']['rule'];

	if (isset($id) && $a_nat[$id]) {
		if (isset($a_nat[$id]['created']) && is_array($a_nat[$id]['created'])) {
			$pconfig['created'] = $a_nat[$id]['created'];
		}

		if (isset($a_nat[$id]['updated']) && is_array($a_nat[$id]['updated'])) {
			$pconfig['updated'] = $a_nat[$id]['updated'];
		}

		$pconfig['disabled'] = isset($a_nat[$id]['disabled']);
		$pconfig['nordr'] = isset($a_nat[$id]['nordr']);

		address_to_pconfig($a_nat[$id]['source'], $pconfig['src'],
			$pconfig['srcmask'], $pconfig['srcnot'],
			$pconfig['srcbeginport'], $pconfig['srcendport']);

		address_to_pconfig($a_nat[$id]['destination'], $pconfig['dst'],
			$pconfig['dstmask'], $pconfig['dstnot'],
			$pconfig['dstbeginport'], $pconfig['dstendport']);

		if (($pconfig['dstbeginport'] == 1) && ($pconfig['dstendport'] == 65535)) {
			$pconfig['dstbeginport'] = "any";
			$pconfig['dstendport'] = "any";
		}

		$pconfig['ipprotocol'] = $a_nat[$id]['ipprotocol'];
		$pconfig['proto'] = $a_nat[$id]['protocol'];
		$pconfig['localip'] = $a_nat[$id]['target'];
		$pconfig['localbeginport'] = $a_nat[$id]['local-port'];
		$pconfig['descr'] = $a_nat[$id]['descr'];
		$pconfig['interface'] = $a_nat[$id]['interface'];
		$pconfig['associated-rule-id'] = $a_nat[$id]['associated-rule-id'];
		$pconfig['nosync'] = isset($a_nat[$id]['nosync']);
		$pconfig['natreflection'] = $a_nat[$id]['natreflection'];

		if (!$pconfig['interface']) {
			$pconfig['interface'] = "wan";
		}
	} else {
		$pconfig['interface'] = "wan";
		$pconfig['src'] = "any";
		$pconfig['srcbeginport'] = "any";
		$pconfig['srcendport'] = "any";
	}

	return $json ? json_encode($pconfig):$pconfig;
}
?>
