<?php
/*
 * firewall_nat_out.inc
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

// Functions to support firewall_nat_out.php and firewall_nat_out_edit.php

require_once("config.gui.inc");
require_once("interfaces.inc");
require_once("util.inc");
require_once("pfsense-utils.inc");
require_once("ipsec.inc");
require_once("filter.inc");

// Save Outbound mode
function saveNAToutMode($post, $json = false) {
	global $config, $FilterIflist, $GatewaysList;

	init_config_arr(array('nat', 'outbound', 'rule'));
	$a_out = &$config['nat']['outbound']['rule'];

	if (!isset($config['nat']['outbound']['mode'])) {
		$config['nat']['outbound']['mode'] = "automatic";
	}
	
	$mode = $config['nat']['outbound']['mode'];

	/* mutually exclusive settings - if user wants advanced NAT, we don't generate automatic rules */
	if ($post['mode'] == "advanced" && ($mode == "automatic" || $mode == "hybrid")) {
		/*
		 *	user has enabled advanced outbound NAT and doesn't have rules
		 *	lets automatically create entries
		 *	for all of the interfaces to make life easier on the pip-o-chap
		 */
		if (empty($FilterIflist)) {
			filter_generate_optcfg_array();
		}

		if (empty($GatewaysList)) {
			filter_generate_gateways();
		}

		$tonathosts = filter_nat_rules_automatic_tonathosts(true);
		$automatic_rules = filter_nat_rules_outbound_automatic("");

		foreach ($tonathosts as $tonathost) {
			foreach ($automatic_rules as $natent) {
				$natent['source']['network'] = $tonathost['subnet'];
				$natent['descr'] .= sprintf(gettext(' - %1$s to %2$s'),
					$tonathost['descr'],
					convert_real_interface_to_friendly_descr($natent['interface']));
				$natent['created'] = make_config_revision_entry(null, gettext("Manual Outbound NAT Switch"));

				/* Try to detect already auto created rules and avoid duplicating them */
				$found = false;
				foreach ($a_out as $rule) {
					if ($rule['interface'] == $natent['interface'] &&
					    $rule['source']['network'] == $natent['source']['network'] &&
					    $rule['dstport'] == $natent['dstport'] &&
					    $rule['target'] == $natent['target'] &&
					    $rule['descr'] == $natent['descr']) {
						$found = true;
						break;
					}
				}

				if ($found === false) {
					$a_out[] = $natent;
				}
			}
		}
		$default_rules_msg = gettext("Default rules for each interface have been created.");
		unset($FilterIflist, $GatewaysList);
	}

	$config['nat']['outbound']['mode'] = $post['mode'];

	if (write_config(gettext("Firewall: NAT: Outbound - saved outbound NAT settings."))) {
		mark_subsystem_dirty('natconf');
	}

	if (!$json) {
		header("Location: firewall_nat_out.php");
		exit;
	}
}
// Save Outbound rule
function saveoutNATrule($post, $id, $json = false) {
	global $config;

	init_config_arr(array('nat', 'outbound', 'rule'));
	$a_out = &$config['nat']['outbound']['rule'];

	if (isset($post['after'])) {
		$after = $post['after'];
	}

	if ($post['destination_type'] == "any") {
		$post['destination'] = "any";
		$post['destination_subnet'] = 24;
	}

	if ($post['source_type'] == "any") {
		$post['source'] = "any";
		$post['source_subnet'] = 24;
	} elseif ($post['source_type'] == "(self)") {
		$post['source'] = "(self)";
		$post['source_subnet'] = 24;
	}

	unset($input_errors);
	$pconfig = $post;

	/*  run through $post items encoding HTML entitles so that the user
	 *  cannot think he is slick and perform a XSS attack on the unwilling
	 */
	foreach ($post as $key => $value) {
		if ($key == 'descr') {
			continue;
		}

		$temp = str_replace(">", "", $value);
		$newpost = htmlentities($temp);
		if ($newpost <> $temp) {
			$input_errors[] = sprintf(gettext("Invalid characters detected (%s).  Please remove invalid characters and save again."), $temp);
		}
	}

	/* input validation */
	$reqdfields = explode(" ", "interface protocol source source_subnet destination destination_subnet");
	$reqdfieldsn = array(gettext("Interface"), gettext("Protocol"), gettext("Source"), gettext("Source bit count"), gettext("Destination"), gettext("Destination bit count"));

	do_input_validation($post, $reqdfields, $reqdfieldsn, $input_errors);

	$protocol_uses_ports = in_array($post['protocol'], explode(" ", "any tcp udp tcp/udp"));

	if ($post['source']) {
		$post['source'] = trim($post['source']);
	}
	if ($post['destination']) {
		$post['destination'] = trim($post['destination']);
	}
	if ($post['targetip']) {
		$post['targetip'] = trim($post['targetip']);
	}
	if ($post['sourceport']) {
		$post['sourceport'] = trim($post['sourceport']);
	}
	if ($post['dstport']) {
		$post['dstport'] = trim($post['dstport']);
	}
	if ($post['natport']) {
		$post['natport'] = trim($post['natport']);
	}

	if (strlen($post['target']) > 0) {
		// Strip the target code 1-char code from the front before validating and saving.
		$post['target'] = substr($post['target'], 1);
	}

	if ($protocol_uses_ports && $post['sourceport'] <> "" && !is_port_or_range_or_alias($post['sourceport'])) {
		$input_errors[] = gettext("A valid port or port alias must be supplied for the source port entry.");
	}

	if ($protocol_uses_ports && $post['dstport'] <> "" && !is_port_or_range_or_alias($post['dstport'])) {
		$input_errors[] = gettext("A valid port or port alias must be supplied for the destination port entry.");
	}

	if ($protocol_uses_ports && $post['natport'] <> "" && !is_port_or_range_or_alias($post['natport']) && !isset($post['nonat'])) {
		$input_errors[] = gettext("A valid port must be supplied for the NAT port entry.");
	}

	if (($post['source_type'] != "any") && ($post['source_type'] != "(self)")) {
		if ($post['source'] && !is_ipaddroralias($post['source']) && $post['source'] != "any") {
			$input_errors[] = gettext("A valid source must be specified.");
		}
	}

	if ($post['source_subnet'] && !is_numericint($post['source_subnet'])) {
		$input_errors[] = gettext("A valid source bit count must be specified.");
	}

	if ($post['destination_type'] != "any") {
		if ($post['destination'] && !is_ipaddroralias($post['destination'])) {
			$input_errors[] = gettext("A valid destination must be specified.");
		}
	}

	if ($post['destination_subnet'] && !is_numericint($post['destination_subnet'])) {
		$input_errors[] = gettext("A valid destination bit count must be specified.");
	}

	if ($post['destination_type'] == "any") {
		if ($post['destination_not']) {
			$input_errors[] = gettext("Negating destination address of \"any\" is invalid.");
		}
	}

	if ($post['target'] && !is_ipaddr($post['target']) && !is_subnet($post['target']) && !is_alias($post['target']) && !isset($post['nonat']) && !($post['target'] == "other-subnet")) {
		$input_errors[] = gettext("A valid target IP address must be specified.");
	}

	if ($post['target'] == "other-subnet") {
		if (!is_ipaddr($post['targetip'])) {
			$input_errors[] = gettext("A valid target IP must be specified when using the 'Other Subnet' type.");
		}

		if (!is_numericint($post['targetip_subnet'])) {
			$input_errors[] = gettext("A valid target bit count must be specified when using the 'Other Subnet' type.");
		}
	}

	/* Verify Pool Options */
	$poolopts = "";
	$source_hash_key = "";
	if ($post['poolopts']) {
		if (is_subnet($post['target']) || ($post['target'] == "other-subnet")) {
			$poolopts = $post['poolopts'];
		} elseif (is_alias($post['target'])) {
			if (substr($post['poolopts'], 0, 11) == "round-robin") {
				$poolopts = $post['poolopts'];
			} else {
				$input_errors[] = gettext("Only Round Robin pool options may be chosen when selecting an alias.");
			}
		}
		/* If specified, verify valid source-hash key or generate a valid key using md5 */
		if ($post['source_hash_key']) {
			if (substr($post['source_hash_key'],0,2) == "0x") {
				if (ctype_xdigit(substr($post['source_hash_key'],2)) && strlen($post['source_hash_key']) == 34) {
					$source_hash_key = $post['source_hash_key'];
				} else {
					$input_errors[] = gettext("Incorrect format for source-hash key, \"0x\" must be followed by exactly 32 hexadecimal characters.");
				}
			} else {
				$source_hash_key = "0x".md5($post['source_hash_key']);
			}
		}
	}

	/* if user has selected any as source, set it here */
	if ($post['source_type'] == "any") {
		$osn = "any";
	} else if ($post['source_type'] == "(self)") {
		$osn = "(self)";
	} else if (is_alias($post['source'])) {
		$osn = $post['source'];
	} else {
		$osn = gen_subnet($post['source'], $post['source_subnet']) . "/" . $post['source_subnet'];
	}

	/* check for existing entries */
	if ($post['destination_type'] == "any") {
		$ext = "any";
	} else if (is_alias($post['destination'])) {
		$ext = $post['destination'];
	} else {
		$ext = gen_subnet($post['destination'], $post['destination_subnet']) . "/" . $post['destination_subnet'];
	}

	foreach ($a_out as $natent) {
		if (isset($id) && ($a_out[$id]) && ($a_out[$id] === $natent)) {
			continue;
		}

		if (!$natent['interface']) {
			$natent['interface'] = "wan";
		}
	}

	if (!$input_errors) {
		$natent = array();
		$natent['source']['network'] = $osn;
		$natent['sourceport'] = ($protocol_uses_ports) ? $post['sourceport'] : "";
		$natent['descr'] = $post['descr'];
		$natent['target'] = (!isset($post['nonat'])) ? $post['target'] : "";
		$natent['targetip'] = (!isset($post['nonat'])) ? $post['targetip'] : "";
		$natent['targetip_subnet'] = (!isset($post['nonat'])) ? $post['targetip_subnet'] : "";
		$natent['interface'] = $post['interface'];
		$natent['poolopts'] = $poolopts;
		$natent['source_hash_key'] = $source_hash_key;

		/* static-port */
		if (isset($post['staticnatport']) && $protocol_uses_ports && !isset($post['nonat'])) {
			$natent['staticnatport'] = true;
		} else {
			unset($natent['staticnatport']);
		}

		if (isset($post['disabled'])) {
			$natent['disabled'] = true;
		} else {
			unset($natent['disabled']);
		}

		/* if user has selected not nat, set it here */
		if (isset($post['nonat'])) {
			$natent['nonat'] = true;
		} else {
			unset($natent['nonat']);
		}

		if ($post['ipprotocol'] && $post['ipprotocol'] != "inet46") {
			$natent['ipprotocol'] = $post['ipprotocol'];
		} else {
			unset($natent['ipprotocol']);
		}
		
		if ($post['protocol'] && $post['protocol'] != "any") {
			$natent['protocol'] = $post['protocol'];
		} else {
			unset($natent['protocol']);
		}

		if ($ext == "any") {
			$natent['destination']['any'] = true;
		} else {
			$natent['destination']['address'] = $ext;
		}
		if ($post['natport'] != "" && $protocol_uses_ports && !isset($post['nonat'])) {
				$natent['natport'] = $post['natport'];
		} else {
			unset($natent['natport']);
		}
		if ($post['dstport'] != "" && $protocol_uses_ports) {
			$natent['dstport'] = $post['dstport'];
		} else {
			unset($natent['dstport']);
		}

		if ($post['nosync'] == "yes") {
			$natent['nosync'] = true;
		} else {
			unset($natent['nosync']);
		}

		if (isset($post['destination_not']) && $ext != "any") {
			$natent['destination']['not'] = true;
		}

		if (isset($a_out[$id]['created']) && is_array($a_out[$id]['created'])) {
			$natent['created'] = $a_out[$id]['created'];
		}

		$natent['updated'] = make_config_revision_entry();

		// Allow extending of the firewall edit page and include custom input validation
		pfSense_handle_custom_code("/usr/local/pkg/firewall_aon/pre_write_config");

		if (isset($id) && $a_out[$id]) {
			$a_out[$id] = $natent;
		} else {
			$natent['created'] = make_config_revision_entry();
			if (is_numeric($after)) {
				array_splice($a_out, $after+1, 0, array($natent));
			} else {
				$a_out[] = $natent;
			}
		}

		if (write_config(gettext("Firewall: NAT: Outbound - saved/edited outbound NAT mapping.")) && !$json) {
			mark_subsystem_dirty('natconf');
		}
	}

	$rv = array();
	$rv['input_errors'] = $input_errors;
	$rv['pconfig'] = $pconfig;

	return $json ? json_encode($rv) : $rv;
}

// Retrieve the specified Outbound rule
function getoutNATrule($id, $json = false) {
	global $config;

	init_config_arr(array('nat', 'outbound', 'rule'));
	$a_out = &$config['nat']['outbound']['rule'];

	$pconfig = array();

	if (isset($id) && $a_out[$id]) {
		if (isset($a_out[$id]['created']) && is_array($a_out[$id]['created'])) {
			$pconfig['created'] = $a_out[$id]['created'];
		}
	
		if (isset($a_out[$id]['updated']) && is_array($a_out[$id]['updated'])) {
			$pconfig['updated'] = $a_out[$id]['updated'];
		}
	
		$pconfig['ipprotocol'] = $a_out[$id]['ipprotocol'];
		$pconfig['protocol'] = $a_out[$id]['protocol'];
		list($pconfig['source'], $pconfig['source_subnet']) = explode('/', $a_out[$id]['source']['network']);
		if (!is_numeric($pconfig['source_subnet'])) {
			$pconfig['source_subnet'] = 32;
		}
		$pconfig['sourceport'] = $a_out[$id]['sourceport'];
		address_to_pconfig($a_out[$id]['destination'], $pconfig['destination'],
			$pconfig['destination_subnet'], $pconfig['destination_not'],
			$none, $none);
	
		$pconfig['dstport'] = $a_out[$id]['dstport'];
		$pconfig['natport'] = $a_out[$id]['natport'];
		$pconfig['target'] = $a_out[$id]['target'];
		if (strlen($pconfig['target']) > 0) {
			// Deduce the target type and add to the front of the target string.
			if (is_subnet($pconfig['target'])) {
				$target_type = "S";
			} elseif (is_ipaddr($pconfig['target'])) {
				$target_type = "I";
			} elseif (is_alias($pconfig['target'])) {
				$target_type = "H";
			} else {
				$target_type = "O";
			}
			$pconfig['target'] = $target_type . $pconfig['target'];
		}
	
		$pconfig['targetip'] = $a_out[$id]['targetip'];
		$pconfig['targetip_subnet'] = $a_out[$id]['targetip_subnet'];
		$pconfig['poolopts'] = $a_out[$id]['poolopts'];
		$pconfig['source_hash_key'] = $a_out[$id]['source_hash_key'];
		$pconfig['interface'] = $a_out[$id]['interface'];
	
		if (!$pconfig['interface']) {
			$pconfig['interface'] = "wan";
		}
	
		$pconfig['descr'] = $a_out[$id]['descr'];
		$pconfig['nonat'] = $a_out[$id]['nonat'];
		$pconfig['disabled'] = isset($a_out[$id]['disabled']);
		$pconfig['staticnatport'] = isset($a_out[$id]['staticnatport']);
		$pconfig['nosync'] = isset($a_out[$id]['nosync']);
	} else {
		$pconfig['source_subnet'] = 24;
		$pconfig['destination'] = "any";
		$pconfig['destination_subnet'] = 24;
		$pconfig['interface'] = "wan";
	}

	return $json ? json_encode($pconfig):$pconfig;
}

// Toggle enabled/disabled status of an Outbound rule
function toggleoutNATrule($post, $json = false) {
	global $config;

	init_config_arr(array('nat', 'outbound', 'rule'));
	$a_out = &$config['nat']['outbound']['rule'];

	if (isset($a_out[$post['id']]['disabled'])) {
		unset($a_out[$post['id']]['disabled']);
		$wc_msg = gettext('Firewall: NAT: Outbound - enabled a NAT Outbound rule.');
	} else {
		$a_out[$post['id']]['disabled'] = true;
		$wc_msg = gettext('Firewall: NAT: Outbound - disabled a NAT Outbound rule.');
	}

	if (write_config($wc_msg) && !$json) {
		mark_subsystem_dirty('natconf');
	}

	if (!$json) {
		header("Location: firewall_nat_out.php");
		exit;
	} else {
		$a_out = &$config['nat']['outbound'];
		return isset($a_out['rule'][$post['id']]['disabled']) ? "disabled":"enabled";
	}
}

// Delete multiple Outbound rules
function deleteMultipleoutNATrules($post, $json = false) {
	global $config;

	init_config_arr(array('nat', 'outbound', 'rule'));
	$a_out = &$config['nat']['outbound']['rule'];

	foreach ($post['rule'] as $rulei) {
		unset($a_out[$rulei]);
	}

	if (write_config(gettext("Firewall: NAT: Outbound - deleted selected outbound mappings.")) && !$json) {
		mark_subsystem_dirty('natconf');
	}

	if (!$json) {
		header("Location: firewall_nat_out.php");
		exit;
	}
}

// Delete outbound rule
function deleteoutNATrule($post, $json = false) {
	global $config;

	init_config_arr(array('nat', 'outbound', 'rule'));
	$a_out = &$config['nat']['outbound']['rule'];

	unset($a_out[$post['id']]);
	if (write_config(gettext("Firewall: NAT: Outbound - deleted NPt mapping.")) && !$json) {
		mark_subsystem_dirty('natconf');
	}

	if(!$json) {
		header("Location: firewall_nat_out.php");
		exit;
	}
}

// Re-order the NPtNAT rules per the array of iindicies passed in $post
function outNATrulesreorder($post, $json = false) {
	global $config;

	if (is_array($post['rule']) && !empty($post['rule'])) {
		init_config_arr(array('nat', 'outbound', 'rule'));
		$a_out = &$config['nat']['outbound']['rule'];
		$a_out_new = array();

		// if a rule is not in POST[rule], it has been deleted by the user
		foreach ($post['rule'] as $id) {
			$a_out_new[] = $a_out[$id];
		}

		$a_out = $a_out_new;

		if (write_config(gettext("Firewall: NAT: Outbound - reordered outbound mappings.")) && !$json) {
			mark_subsystem_dirty('natconf');
		}

		if (!$json) {
			header("Location: firewall_nat_out.php");
			exit;
		}
	}
}

function applyoutNATrules() {
	$retval = 0;
	$retval |= filter_configure();

	if ($retval == 0) {
		clear_subsystem_dirty('natconf');
		clear_subsystem_dirty('filter');
	}

	return $retval;
}

function build_target_list() {
	global $config;

	init_config_arr(array('aliases', 'alias'));
	$a_aliases = &$config['aliases']['alias'];

	$list = array();
	// Target list entries are made to start with the following characters:
	// "" (blank) - the interface address of the selected interface
	// S - a subnet
	// I - an ordinary IP address
	// H - a host alias
	// O - other subnet
	// The prefix letter makes it easy for the JavaScript to distinguish
	// the type of entry based on the first letter of the value.
	// The prefix letter is removed before saving in the config,
	// and added back when reading from the config.

	$list[""] = gettext('Interface Address');

	//Temporary array so we can sort IPs
	$templist = array();
	if (is_array($config['virtualip']['vip'])) {
		foreach ($config['virtualip']['vip'] as $sn) {
			if (($sn['mode'] == "proxyarp" || $sn['mode'] == "other") && $sn['type'] == "network") {
				$templist['S' . $sn['subnet'] . '/' . $sn['subnet_bits']] = gettext('Subnet: ') . $sn['subnet'] . '/' . $sn['subnet_bits'] . ' (' . $sn['descr'] . ')';
				if (isset($sn['noexpand'])) {
					continue;
				}
				$start = ip2long32(gen_subnet($sn['subnet'], $sn['subnet_bits']));
				$end = ip2long32(gen_subnet_max($sn['subnet'], $sn['subnet_bits']));
				$len = $end - $start;
				for ($i = 0; $i <= $len; $i++) {
					$snip = long2ip32($start+$i);

					$templist['I' . $snip] = $snip . ' (' . $sn['descr'] . ')';
				}
			} else {
				$templist['I' . $sn['subnet']] = $sn['subnet'] . ' (' . $sn['descr'] . ')';
			}
		}
	}
	asort($templist);
	//Append sorted IP array onto main array
	$list = array_merge($list, $templist);
	unset($templist);

	foreach ($a_aliases as $alias) {
		if ($alias['type'] != "host") {
			continue;
		}

		$list['H' . $alias['name']] = gettext('Host Alias: ') . $alias['name'] . ' (' . $alias['descr'] . ')';
	}

	$list['Oother-subnet'] = gettext('Other Subnet (Enter Below)');

	return($list);
}

function getAutoRules($json = false) {
	global $FilterIflist;
	global $GatewaysList;

	if (empty($FilterIflist)) {
		filter_generate_optcfg_array();
	}

	if (empty($GatewaysList)) {
		filter_generate_gateways();
	}

	$automatic_rules = filter_nat_rules_outbound_automatic(implode(" ", filter_nat_rules_automatic_tonathosts()));

	return $json ? json_encode($automatic_rules) : $automatic_rules;
}
?>
