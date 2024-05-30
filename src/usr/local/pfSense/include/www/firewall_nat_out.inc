<?php
/*
 * firewall_nat_out.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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
	global $FilterIflist, $GatewaysList;

	config_init_path('nat/outbound/rule');

	if (config_get_path('nat/outbound/mode') === null) {
		config_set_path('nat/outbound/mode', "automatic");
	}
	
	$mode = config_get_path('nat/outbound/mode');

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
				foreach (config_get_path('nat/outbound/rule', []) as $rule) {
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
					config_set_path('nat/outbound/rule/', $natent);
				}
			}
		}
		$default_rules_msg = gettext("Default rules for each interface have been created.");
		unset($FilterIflist, $GatewaysList);
	}

	config_set_path('nat/outbound/mode', $post['mode']);

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
	global $after;
	$nat_tgttype_flags = [SPECIALNET_NETAL, SPECIALNET_IFADDR, SPECIALNET_VIPS];
	$nat_srctype_flags = [SPECIALNET_ANY, SPECIALNET_SELF, SPECIALNET_NETAL, SPECIALNET_IFNET, SPECIALNET_GROUP];
	$nat_dsttype_flags = [SPECIALNET_ANY, SPECIALNET_NETAL, SPECIALNET_IFNET, SPECIALNET_GROUP];

	config_init_path('nat/outbound/rule');

	if (isset($post['after'])) {
		$after = $post['after'];
	}

	if ($post['source_type'] != 'network' && get_specialnet($post['source_type'], $nat_srctype_flags)) {
		$post['source'] = $post['source_type'];
		$post['source_subnet'] = '';
	}
	if ($post['destination_type'] != 'network' && get_specialnet($post['destination_type'], $nat_dsttype_flags)) {
		$post['destination'] = $post['destination_type'];
		$post['destination_subnet'] = '';
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
	$reqdfields = explode(" ", "interface protocol");
	$reqdfieldsn = array(gettext("Interface"), gettext("Protocol"));
	if (!get_specialnet($post['source_type'], $nat_srctype_flags)) {
		$reqdfields[] = "source";
		$reqdfieldsn[] = gettext("Source");
		$reqdfields[] = "source_subnet";
		$reqdfieldsn[] = gettext("Source bit count");
	}
	if (!get_specialnet($post['destination_type'], $nat_dsttype_flags)) {
		$reqdfields[] = "destination";
		$reqdfieldsn[] = gettext("Destination");
		$reqdfields[] = "destination_subnet";
		$reqdfieldsn[] = gettext("Destination bit count");
	}
	if (!$json) {
		do_input_validation($post, $reqdfields, $reqdfieldsn, $input_errors);
	}

	$protocol_uses_ports = (strtolower($post['protocol']) == 'any' || array_key_exists(strtolower($post['protocol']), get_ipprotocols('portsonly'))) ? true : false;

	if ($post['source']) {
		$post['source'] = trim($post['source']);
	}
	if ($post['destination']) {
		$post['destination'] = trim($post['destination']);
	}
	if ($post['target']) {
		$post['target'] = trim($post['target']);
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

	if ($protocol_uses_ports && $post['sourceport'] <> "" && !is_port_or_range_or_alias($post['sourceport'])) {
		$input_errors[] = gettext("A valid port or port alias must be supplied for the source port entry.");
	}

	if ($protocol_uses_ports && $post['dstport'] <> "" && !is_port_or_range_or_alias($post['dstport'])) {
		$input_errors[] = gettext("A valid port or port alias must be supplied for the destination port entry.");
	}

	if ($protocol_uses_ports && $post['natport'] <> "" && !is_port_or_range_or_alias($post['natport']) && !isset($post['nonat'])) {
		$input_errors[] = gettext("A valid port must be supplied for the NAT port entry.");
	}

	if ($post['source_type'] == 'network') {
		if ($post['source'] && !is_ipaddroralias($post['source'])) {
			$input_errors[] = gettext("A valid source must be specified.");
		}
	}

	if ($post['source_subnet'] && !is_numericint($post['source_subnet'])) {
		$input_errors[] = gettext("A valid source bit count must be specified.");
	}

	if ($post['destination_type'] == 'network') {
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

	if (!isset($post['nonat']) && !get_specialnet($post['target_type'], $nat_tgttype_flags)) {
		$input_errors[] = gettext("A valid target type must be specified.");
	}

	if ($post['target_type'] == 'network') {
		if (!is_ipaddr($post['target']) && !is_alias($post['target'])) {
			$input_errors[] = gettext("A valid target IP address or alias must be specified when using the Network type.");
		}

		if (!is_numericint($post['target_subnet'])) {
			$input_errors[] = gettext("A valid target bit count must be specified when using the Network type.");
		}
	}

	/* Verify Pool Options */
	$poolopts = "";
	$source_hash_key = "";
	if ($post['poolopts']) {
		if (is_subnet($post['target_type']) || ($post['target_type'] == "network")) {
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

	// if the source/destination is not an IP address, it's an alias or specialnet
	if ($post['source_type'] == 'network' && is_ipaddr($post['source'])) {
		$osn = gen_subnet($post['source'], $post['source_subnet']) . "/" . $post['source_subnet'];
	} else {
		$osn = $post['source'];
	}
	if ($post['destination_type'] == 'network' && is_ipaddr($post['destination'])) {
		$ext = gen_subnet($post['destination'], $post['destination_subnet']) . "/" . $post['destination_subnet'];
	} else {
		$ext = $post['destination'];
	}

	if (!$input_errors) {
		$natent = array();
		$natent['source']['network'] = $osn;
		$natent['sourceport'] = ($protocol_uses_ports) ? $post['sourceport'] : "";
		$natent['descr'] = $post['descr'];
		if (isset($post['nonat'])) {
			$natent['target'] = '';
			$natent['target_subnet'] = '';
		} elseif ($post['target_type'] == 'network') {
			$natent['target'] = $post['target'];
			$natent['target_subnet'] = (!isset($post['nonat'])) ? $post['target_subnet'] : "";
		} else {
			$natent['target'] = $post['target_type'];
			$natent['target_subnet'] = '';
		}
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
			$natent['destination']['network'] = $ext;
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

		if (is_array(config_get_path("nat/outbound/rule/{$id}/created"))) {
			$natent['created'] = config_get_path("nat/outbound/rule/{$id}/created");
		}

		$natent['updated'] = make_config_revision_entry();

		// Allow extending of the firewall edit page and include custom input validation
		pfSense_handle_custom_code("/usr/local/pkg/firewall_aon/pre_write_config");

		if (isset($id) && config_get_path("nat/outbound/rule/{$id}")) {
			config_set_path("nat/outbound/rule/{$id}", $natent);
		} else {
			$natent['created'] = make_config_revision_entry();
			if (is_numeric($after)) {
				$a_out = config_get_path('nat/outbound/rule');
				array_splice($a_out, $after+1, 0, array($natent));
				config_set_path('nat/outbound/rule', $a_out);
			} else {
				config_set_path('nat/outbound/rule/', $natent);
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
	$nat_tgttype_flags = [SPECIALNET_NETAL, SPECIALNET_IFADDR, SPECIALNET_VIPS];
	$nat_srctype_flags = [SPECIALNET_ANY, SPECIALNET_SELF, SPECIALNET_NETAL, SPECIALNET_IFNET, SPECIALNET_GROUP];
	$nat_dsttype_flags = [SPECIALNET_ANY, SPECIALNET_NETAL, SPECIALNET_IFNET, SPECIALNET_GROUP];

	config_init_path('nat/outbound/rule');
	$a_out = config_get_path('nat/outbound/rule');

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

		$pconfig['source_type'] = 'any';
		$pconfig['source'] = '';
		$pconfig['source_subnet'] = '';
		if (!empty($a_out[$id]['source']['network'])) {
			if (get_specialnet($a_out[$id]['source']['network'], $nat_srctype_flags)) {
				$pconfig['source_type'] = $a_out[$id]['source']['network'];
			} else {
				$pconfig['source_type'] = 'network';
				list($pconfig['source'], $pconfig['source_subnet']) = explode('/', $a_out[$id]['source']['network']);
			}
		}
		$pconfig['sourceport'] = $a_out[$id]['sourceport'];

		$pconfig['destination_type'] = 'any';
		$pconfig['destination'] = '';
		$pconfig['destination_subnet'] = '';
		if (!empty($a_out[$id]['destination']['network'])) {
			if (get_specialnet($a_out[$id]['destination']['network'], $nat_dsttype_flags)) {
				$pconfig['destination_type'] = $a_out[$id]['destination']['network'];
			} else {
				$pconfig['destination_type'] = 'network';
				list($pconfig['destination'], $pconfig['destination_subnet']) = explode('/', $a_out[$id]['destination']['network']);
			}
		}
		$pconfig['dstport'] = $a_out[$id]['dstport'];
		$pconfig['destination_not'] = (isset($a_out[$id]['destination']['not'])) ? 1 : 0;

		$pconfig['natport'] = $a_out[$id]['natport'];
		$pconfig['poolopts'] = $a_out[$id]['poolopts'];
		$pconfig['source_hash_key'] = $a_out[$id]['source_hash_key'];
		$pconfig['interface'] = $a_out[$id]['interface'];
	
		if (!$pconfig['interface']) {
			$pconfig['interface'] = "wan";
		}
		$pconfig['target'] = '';
		$pconfig['target_subnet'] = '';
		if (empty($a_out[$id]['target'])) {
			$pconfig['target_type'] = $pconfig['interface'] . 'ip';
		} elseif (empty($a_out[$id]['target_subnet']) && get_specialnet($a_out[$id]['target'], $nat_tgttype_flags)) {
			$pconfig['target_type'] = $a_out[$id]['target'];
		} else {
			$pconfig['target_type'] = 'network';
			$pconfig['target'] = $a_out[$id]['target'];
			$pconfig['target_subnet'] = $a_out[$id]['target_subnet'];
		}
		$pconfig['descr'] = $a_out[$id]['descr'];
		$pconfig['nonat'] = $a_out[$id]['nonat'];
		$pconfig['disabled'] = isset($a_out[$id]['disabled']);
		$pconfig['staticnatport'] = isset($a_out[$id]['staticnatport']);
		$pconfig['nosync'] = isset($a_out[$id]['nosync']);
	} else {
		$pconfig['source_type'] = 'any';
		$pconfig['source'] = '';
		$pconfig['source_subnet'] = '';
		$pconfig['destination_type'] = 'any';
		$pconfig['destination'] = '';
		$pconfig['destination_subnet'] = '';
		$pconfig['interface'] = 'wan';
		$pconfig['target_type'] = $pconfig['interface'] . 'ip';
		$pconfig['target'] = '';
	}

	return $json ? json_encode($pconfig):$pconfig;
}

// Toggle enabled/disabled status of an Outbound rule
function toggleoutNATrule($post, $json = false) {
	config_init_path('nat/outbound/rule');
	$a_out = config_get_path('nat/outbound/rule');

	if (isset($a_out[$post['id']]['disabled'])) {
		unset($a_out[$post['id']]['disabled']);
		$wc_msg = gettext('Firewall: NAT: Outbound - enabled a NAT Outbound rule.');
	} else {
		$a_out[$post['id']]['disabled'] = true;
		$wc_msg = gettext('Firewall: NAT: Outbound - disabled a NAT Outbound rule.');
	}
	config_set_path('nat/outbound/rule', $a_out);

	if (write_config($wc_msg) && !$json) {
		mark_subsystem_dirty('natconf');
	}

	if (!$json) {
		header("Location: firewall_nat_out.php");
		exit;
	} else {
		return isset($a_out[$post['id']]['disabled']) ? "disabled":"enabled";
	}
}

// Toggle multiple Outbound rules
function toggleMultipleoutNATrules($post, $json = false) {
	if (empty($post) ||
	    !is_array($post) ||
	    !isset($post['rule']) ||
	    !is_array($post['rule'])) {
		/* Nothing to do.
		 * See https://redmine.pfsense.org/issues/12694 */
		return;
	}

	config_init_path('nat/outbound/rule');
	$a_out = config_get_path('nat/outbound/rule');

	foreach ($post['rule'] as $rulei) {
		if (isset($a_out[$rulei]['disabled'])) {
			unset($a_out[$rulei]['disabled']);
		} else {
			$a_out[$rulei]['disabled'] = true;
		}
	}
	config_set_path('nat/outbound/rule', $a_out);

	if (write_config(gettext("Firewall: NAT: Outbound - toggle enable/disable for selected outbound mappings.")) && !$json) {
		mark_subsystem_dirty('natconf');
	}

	if (!$json) {
		header("Location: firewall_nat_out.php");
		exit;
	}
}

// Delete multiple Outbound rules
function deleteMultipleoutNATrules($post, $json = false) {
	if (empty($post) ||
	    !is_array($post) ||
	    !isset($post['rule']) ||
	    !is_array($post['rule'])) {
		/* Nothing to do.
		 * See https://redmine.pfsense.org/issues/12694 */
		return;
	}

	config_init_path('nat/outbound/rule');
	$a_out = config_get_path('nat/outbound/rule');

	foreach ($post['rule'] as $rulei) {
		unset($a_out[$rulei]);
	}
	config_set_path('nat/outbound/rule', $a_out);

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
	config_init_path('nat/outbound/rule');
	$a_out = config_get_path('nat/outbound/rule');

	unset($a_out[$post['id']]);
	config_set_path('nat/outbound/rule', $a_out);
	if (write_config(gettext("Firewall: NAT: Outbound - deleted NPt mapping.")) && !$json) {
		mark_subsystem_dirty('natconf');
	}

	if(!$json) {
		header("Location: firewall_nat_out.php");
		exit;
	}
}

// Re-order the NPtNAT rules per the array of indices passed in $post
function outNATrulesreorder($post, $json = false) {
	if (is_array($post['rule']) && !empty($post['rule'])) {
		config_init_path('nat/outbound/rule');
		$a_out = config_get_path('nat/outbound/rule');
		$a_out_new = array();

		// if a rule is not in POST[rule], it has been deleted by the user
		foreach ($post['rule'] as $id) {
			$a_out_new[] = $a_out[$id];
		}

		$a_out = $a_out_new;
		config_set_path('nat/outbound/rule', $a_out);

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
