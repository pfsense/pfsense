<?php
/*
 * firewall_nat_1to1.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
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

// Functions to support firewall_nat_1to1.php and firewall_nat_1to1_edit.php

require_once("config.gui.inc");
require_once("interfaces.inc");
require_once("util.inc");
require_once("pfsense-utils.inc");
require_once("ipsec.inc");
require_once("filter.inc");

/**
 * Add 1-to-1 NAT rules.
 * @param array $rules_list A list of config rules.
 * @param int $index A config rule index used to place the new rule(s).
 * 		A zero or greater value adds the rule(s) after that index.
 * 		A value of -1 adds the rule(s) at the top.
 * 		A value of -2 or lower adds the rule(s) at the end.
 * @param bool $replace If a rule is found where the new rule(s) would
 * 		be added that existing rule will be replaced with the new rule(s).
 */
function add_binat_rules(array $rules_list, int $index = -2, bool $replace = false, array &$changes = []) {
	$index = get_anynat_rule_relative_index('binat', $index);
	if (!isset($index)) {
		return;
	}
	$separators = [];
	$nat_rules = get_anynat_rules_map('binat');
	$changed_rules = false;
	foreach (get_anynat_rules_map('binat', $rules_list) as $new_category_name => $new_category) {
		foreach ($new_category as $new_group_name => $new_group) {
			foreach ($new_group as $new_subcategory_name => $new_rules) {
				// Get existing rules to modify.
				$rules = array_get_path(
					$nat_rules,
					"{$new_category_name}/{$new_group_name}/{$new_subcategory_name}",
					[]
				);

				// Insert the rules.
				$result = insert_config_rules($rules, $separators, $new_rules, $index, $replace);
				if ($result['rules']) {
					array_set_path(
						$nat_rules,
						"{$new_category_name}/{$new_group_name}/{$new_subcategory_name}",
						$rules
					);;
					$changed_rules = true;
				}
			}
		}
	}

	if ($changed_rules) {
		set_anynat_rules_map('binat', $nat_rules);
		$changes['binat_rules'] = true;
	}
}

/**
 * Remove 1-to-1 NAT rules.
 * @param int|array $search_index One or more indices of config rules.
 */
function remove_binat_rules(int|array $search_index, array &$changes = []) {
	if (!is_array($search_index)) {
		$search_index = [$search_index];
	}
	if (empty($search_index)) {
		return;
	}

	$separators = [];
	$associated_id_list = [];
	$rules_map = get_anynat_rules_map('binat');
	$changed_rules = false;
	foreach (get_anynat_rules_index('binat', $search_index) as $category_name => $category) {
		foreach ($category as $group_name => $group) {
			foreach ($group as $subcategory_name => $rules_index) {
				// Get existing rules to modify.
				$rules = &$rules_map[$category_name][$group_name][$subcategory_name];

				// Get related info before rules are removed.
				$relative_index_list = [];
				foreach (array_reverse($rules_index['matched_rules']) as $index) {
					$relative_index_list[] = $index['relative_index'];
					if (!empty($rules[$index['relative_index']]['associated-rule-id'])) {
						// Remove the association in NAT rules.
						$associated_id_list[] = $rules[$index['relative_index']]['associated-rule-id'];
					}
				}

				// Remove the rules.
				$result = remove_config_rules($rules, $separators, $relative_index_list);
				if ($result['rules']) {
					$changed_rules = true;
				}
				unset($rules);
			}
		}
	}

	if ($changed_rules) {
		set_anynat_rules_map('binat', $rules_map);
		$changes['binat_rules'] = true;
		foreach ($associated_id_list as $id) {
			delete_id($id, false, $changes);
		}
	}
}

$ifdisp = get_configured_interface_with_descr();

function save1to1NATrule($post, $id, $json = false) {
	global $vpn_and_ppp_ifs;
	$binat_exttype_flags = [SPECIALNET_IFADDR];
	$binat_srctype_flags = [SPECIALNET_ANY, SPECIALNET_CLIENTS, SPECIALNET_IFADDR, SPECIALNET_IFSUB];
	$binat_dsttype_flags = [SPECIALNET_ANY, SPECIALNET_CLIENTS, SPECIALNET_IFADDR, SPECIALNET_IFSUB, SPECIALNET_VIPS];

	if (isset($post['after'])) {
		$after = $post['after'];
	}

	/*	run through $post items encoding HTML entities so that the user
	 *	cannot think he is slick and perform a XSS attack on the unwilling
	 */
	foreach ($post as $key => $value) {
		if ($key == 'descr') {
			continue;
		}

		$temp = str_replace(">", "", $value);
		$newpost = htmlentities($temp);

		if ($newpost != $temp) {
			$input_errors[] = sprintf(gettext("Invalid characters detected (%s).  Please remove invalid characters and save again."), $temp);
		}
	}

	/* input validation */
	if (isset($post['nobinat']) || ($post['exttype'] != "single")) {
		$reqdfields = explode(" ", "interface");
		$reqdfieldsn = array(gettext("Interface"));
	} else {
		$reqdfields = explode(" ", "interface external");
		$reqdfieldsn = array(gettext("Interface"), gettext("External subnet"));
	}

	if ($post['srctype'] == "single" || $post['srctype'] == "network") {
		$reqdfields[] = "src";
		$reqdfieldsn[] = gettext("Source address");
	}

	if ($post['dsttype'] == "single" || $post['dsttype'] == "network") {
		$reqdfields[] = "dst";
		$reqdfieldsn[] = gettext("Destination address");
	}

	if (!$json) {
		do_input_validation($post, $reqdfields, $reqdfieldsn, $input_errors);
	}

	if ($post['external']) {
		$post['external'] = trim($post['external']);
	}
	if ($post['src']) {
		$post['src'] = trim($post['src']);
	}
	if ($post['dst']) {
		$post['dst'] = trim($post['dst']);
	}

	if (get_specialnet($post['srctype'], $binat_srctype_flags)) {
		$post['src'] = $post['srctype'];
		$post['srcmask'] = 0;
	} else if ($post['srctype'] == "single") {
		$post['srcmask'] = (is_ipaddrv4($post['src'])) ? 32 : 128;
	}

	if (get_specialnet($post['dsttype'], $binat_dsttype_flags)) {
		$post['dst'] = $post['dsttype'];
		$post['dstmask'] = 0;
		// handle VIPs
		if (is_ipaddrv6($post['dsttype'])) {
			$post['dstmask'] = 128;
		} elseif (is_ipaddrv4($post['dsttype'])) {
			$post['dstmask'] = 32;
		}
	} else if ($post['dsttype'] == "single") {
		$post['dstmask'] = (is_ipaddrv6($post['dst'])) ? 128 : 32;
	} else if (is_ipaddr($post['dsttype'])) {
		$post['dst'] = $post['dsttype'];
		$post['dsttype'] = "single";
		if ($post['ipprotocol'] == 'inet') {
			$post['dstmask'] = 32;
		} else {
			$post['dstmask'] = 128;
		}
	}

	$pconfig = $post;

	$extipaddrtype = false;
	$srcipaddrtype = false;
	$dstipaddrtype = false;

	if (((($post['ipprotocol'] == 'inet') && !is_interface_ipaddr($post['interface'])) ||
	    (($post['ipprotocol'] == 'inet6') && !is_interface_ipaddrv6($post['interface']))) && 
	    !get_specialnet_type($post['interface'], $post['ipprotocol']) &&
	    !is_interface_group($post['interface']) && !in_array($post['interface'], $vpn_and_ppp_ifs)) {
		$input_errors[] = gettext("The interface does not have an address from the specified address family.");
	}

	if ($post['external'] && !get_specialnet($post['exttype'], $binat_exttype_flags) &&
	    ((($post['ipprotocol'] == 'inet') && (is_ipaddrv4($post['external']))) ||
	    (($post['ipprotocol'] == 'inet6') && (is_ipaddrv6($post['external']))))) {
		$extipaddrtype = validateipaddr($post['external'], IPV4V6, "External subnet IP", $input_errors, false);
	} elseif (get_specialnet($post['exttype'], $binat_exttype_flags)) {
		$extipaddrtype = get_specialnet_type($post['exttype'], $post['ipprotocol']);
	}

	if (!$extipaddrtype) {
		$input_errors[] = gettext("The external subnet IP is not from the specified address family.");
	}

	/* For dst, if user enters an alias and selects "network" then disallow. */
	if ($post['dsttype'] == "network" && is_alias($post['dst'])) {
		$input_errors[] = gettext("Alias entries must specify a single host or alias.");
	}

	if ($post['src'] && $post['srcmask'] && !is_numericint($post['srcmask'])) {
		$input_errors[] = gettext("A valid internal bit count must be specified.");
	}

	if ($post['src'] && !get_specialnet($post['srctype'], $binat_srctype_flags) &&
	    ((($post['ipprotocol'] == 'inet') && (is_ipaddrv4($post['src']))) ||
	    (($post['ipprotocol'] == 'inet6') && (is_ipaddrv6($post['src']))))) {
		$srcipaddrtype = validateipaddr($post['src'], IPV4V6, "Internal IP", $input_errors, false);
	} elseif (get_specialnet($post['srctype'], $binat_srctype_flags)) {
		$srcipaddrtype = get_specialnet_type($post['srctype'], $post['ipprotocol']);
	}

	if (($post['src'] != 'any') && !$srcipaddrtype) {
		$input_errors[] = gettext("The internal IP is not from the specified address family.");
	}

	if ($post['dst'] && $post['dstmask'] && !is_numericint($post['dstmask'])) {
		$input_errors[] = gettext("A valid destination bit count must be specified.");
	}

	if ($post['dst'] && !get_specialnet($post['dsttype'], $binat_dsttype_flags) && (is_alias($post['dst']) ||
	    (($post['ipprotocol'] == 'inet') && (is_ipaddrv4($post['dst']))) ||
	    (($post['ipprotocol'] == 'inet6') && (is_ipaddrv6($post['dst']))))) {
		$dstipaddrtype = validateipaddr($post['dst'], IPV4V6, "Destination address", $input_errors, true);
	} elseif (get_specialnet($post['dsttype'], $binat_dsttype_flags)) {
		$dstipaddrtype = get_specialnet_type($post['dsttype'], $post['ipprotocol']);
	}

	if (($post['dst'] != 'any') && !$dstipaddrtype) {
		$input_errors[] = gettext("The destination address is not from the specified address family.");
	}

	if (get_specialnet($post['exttype'], $binat_exttype_flags)) {
		$post['external'] = $post['exttype'];
		$pconfig['external'] = $post['exttype'];
	}

	if (!$input_errors) {
		$natent = array();

		$natent['nobinat'] = isset($post['nobinat']) ? true:false;
		$natent['disabled'] = isset($post['disabled']) ? true:false;
		$natent['external'] = $post['external'];
		$natent['descr'] = $post['descr'];
		$natent['interface'] = $post['interface'];
		$natent['ipprotocol'] = $post['ipprotocol'];

		pconfig_to_address($natent['source'], $post['src'],
			$post['srcmask'], $post['srcnot'], 0, 0, false, $binat_srctype_flags);

		pconfig_to_address($natent['destination'], $post['dst'],
			$post['dstmask'], $post['dstnot'], 0, 0, false, array_diff($binat_dsttype_flags, [SPECIALNET_VIPS]));

		if ($post['natreflection'] == "enable" || $post['natreflection'] == "disable") {
			$natent['natreflection'] = $post['natreflection'];
		} else {
			unset($natent['natreflection']);
		}

		$a_1to1 = get_anynat_rules_list('binat');
		if (isset($id) && $a_1to1[$id]) {
			if (isset($a_1to1[$id]['labels'])) {
				// Keep rule labels.
				$natent['labels'] = $a_1to1[$id]['labels'];
			}
			if (isset($a_1to1[$id]['force_category'])) {
				// Keep filter category override.
				$natent['force_category'] = $a_1to1[$id]['force_category'];
			}
			add_binat_rules([$natent], $id, true);
		} else {
			if (is_numeric($after)) {
				add_binat_rules([$natent], $after);
			} else {
				add_binat_rules([$natent]);
			}
		}

		if (write_config(gettext("Firewall: NAT: 1:1 - saved/edited NAT 1:1 mapping.")) && !$json) {
			mark_subsystem_dirty('natconf');
		}
	}

	$rv = array();
	$rv['input_errors'] = $input_errors;
	$rv['pconfig'] = $pconfig;

	return $json ? json_encode($rv) : $rv;
}

function get1to1NATRule($id, $json = false) {
	$a_1to1 = get_anynat_rules_list('binat');

	$pconfig = array();

	if (isset($id) && $a_1to1[$id]) {
		$pconfig['nobinat'] = isset($a_1to1[$id]['nobinat']);
		$pconfig['disabled'] = isset($a_1to1[$id]['disabled']);

		address_to_pconfig($a_1to1[$id]['source'], $pconfig['src'],
			$pconfig['srcmask'], $pconfig['srcnot'],
			$pconfig['srcbeginport'], $pconfig['srcendport']);

		address_to_pconfig($a_1to1[$id]['destination'], $pconfig['dst'],
			$pconfig['dstmask'], $pconfig['dstnot'],
			$pconfig['dstbeginport'], $pconfig['dstendport']);

		$pconfig['interface'] = $a_1to1[$id]['interface'];
		$pconfig['ipprotocol'] = $a_1to1[$id]['ipprotocol'];
		if (!$pconfig['interface']) {
			$pconfig['interface'] = "wan";
		}

		$pconfig['external'] = $a_1to1[$id]['external'];
		$pconfig['descr'] = $a_1to1[$id]['descr'];
		$pconfig['natreflection'] = $a_1to1[$id]['natreflection'];
	} else {
		$pconfig['interface'] = "wan";
	}

	return $json ? json_encode($pconfig):$pconfig;
}

// Toggle enabled/disabled status of a 1 to 1 rule
function toggle1to1NATrule($post, $json = false) {
	$a_1to1 = get_anynat_rules_list('binat');

	if (isset($a_1to1[$post['id']]['disabled'])) {
		unset($a_1to1[$post['id']]['disabled']);
		$wc_msg = gettext('Firewall: NAT: 1:1 - enabled a NAT 1:1 rule.');
	} else {
		$a_1to1[$post['id']]['disabled'] = true;
		$wc_msg = gettext('Firewall: NAT: 1:1 - disabled a NAT 1:1 rule.');
	}
	set_anynat_rules_list('binat', $a_1to1);

	if (write_config($wc_msg) && !$json) {
		mark_subsystem_dirty('natconf');
	}

	if (!$json) {
		header("Location: firewall_nat_1to1.php");
		exit;
	} else {
		return isset($a_1to1[$post['id']]['disabled']) ? "disabled":"enabled";
	}
}

// Toggle enabled/disabled status for multiple 1 to 1 rules
function toggleMultiple1to1NATrules($post, $json = false) {
	$a_1to1 = get_anynat_rules_list('binat');

	foreach ($post['rule'] as $rulei) {
		if (isset($a_1to1[$rulei]['disabled'])) {
			unset($a_1to1[$rulei]['disabled']);
		} else {
			$a_1to1[$rulei]['disabled'] = true;
		}
	}
	set_anynat_rules_list('binat', $a_1to1);

	if (write_config(gettext("Firewall: NAT: 1:1 - toggle enable/disable for selected NAT 1:1 mappings.")) && !$json) {
		mark_subsystem_dirty('natconf');
	}

	if (!$json) {
		header("Location: firewall_nat_1to1.php");
		exit;
	}
}

// Delete multiple 1 to 1 rules
function deleteMultiple1to1NATrules($post, $json = false) {
	remove_binat_rules($post['rule']);

	if (write_config(gettext("Firewall: NAT: 1:1 - deleted selected NAT 1:1 mappings.")) && !$json) {
		mark_subsystem_dirty('natconf');
	}

	if (!$json) {
		header("Location: firewall_nat_1to1.php");
		exit;
	}
}

// Delete 1 to 1 rule
function delete1to1NATrule($post, $json = false) {
	remove_binat_rules($post['id']);
	if (write_config(gettext("Firewall: NAT: 1:1 - deleted NAT 1:1 mapping.")) && !$json) {
		mark_subsystem_dirty('natconf');
	}

	if(!$json) {
		header("Location: firewall_nat_1to1.php");
		exit;
	}
}

// Re-order the 1 to 1 NAT rules per the array of indices passed in $post
function reorder1to1NATrules($post, $json = false) {
	if (is_array($post['rule']) && !empty($post['rule'])) {
		set_anynat_rules_order('binat', $post['rule']);

		if (write_config(gettext("Firewall: NAT: 1:1 - reordered NAT 1:1 mappings.")) && !$json) {
			mark_subsystem_dirty('natconf');
		}

		if (!$json) {
			header("Location: firewall_nat_1to1.php");
			exit;
		}
	}
}

function apply1to1NATrules() {
	$retval = 0;
	$retval |= filter_configure();

	if ($retval == 0) {
		clear_subsystem_dirty('natconf');
		clear_subsystem_dirty('filter');
	}

	return $retval;
}

function build_srctype_list() {
	return get_specialnet('', [SPECIALNET_CHECKPERM, SPECIALNET_ANY, SPECIALNET_COMPAT_ADDR,
	                      SPECIALNET_NET, SPECIALNET_CLIENTS, SPECIALNET_IFADDR, SPECIALNET_IFSUB]);
}

function srctype_selected() {
	global $pconfig;

	$binat_srctype_flags = [SPECIALNET_ANY, SPECIALNET_COMPAT_ADDR, SPECIALNET_NET, SPECIALNET_CLIENTS,
	    SPECIALNET_IFADDR, SPECIALNET_IFSUB];

	if ($pconfig['srctype']) {
		// The rule type came from the $post array, after input errors, so keep it.
		return $pconfig['srctype'];
	}

	$sel = get_specialnet($pconfig['src'], $binat_srctype_flags);

	if (!$sel) {
		if ((($pconfig['srcmask'] == 32) && (is_ipaddrv4($pconfig['src']))) ||
		    (($pconfig['srcmask'] == 128) && (is_ipaddrv6($pconfig['src']))) ||
		    (!isset($pconfig['srcmask']))) {
			return('single');
		}

		return('network');
	}

	return($pconfig['src']);
}

function build_dsttype_list() {
	return get_specialnet('', [SPECIALNET_CHECKPERM, SPECIALNET_ANY,
	                       SPECIALNET_COMPAT_ADDRAL, SPECIALNET_NET, SPECIALNET_CLIENTS,
	                       SPECIALNET_IFADDR, SPECIALNET_IFSUB, SPECIALNET_VIPS]);
}

function dsttype_selected() {
	global $pconfig;

	$binat_dsttype_flags = [SPECIALNET_ANY, SPECIALNET_COMPAT_ADDRAL, SPECIALNET_NET, SPECIALNET_CLIENTS,
	    SPECIALNET_IFADDR, SPECIALNET_IFSUB, SPECIALNET_VIPS];

	if ($pconfig['dsttype']) {
		// The rule type came from the $post array, after input errors, so keep it.
		return $pconfig['dsttype'];
	}

	$sel = get_specialnet($pconfig['dst'], $binat_dsttype_flags);

	if (empty($pconfig['dst']) || ($pconfig['dst'] == "any")) {
		return('any');
	}

	if (!$sel) {
		if ((($pconfig['dstmask'] == 32) && (is_ipaddrv4($pconfig['dst']))) ||
		    (($pconfig['dstmask'] == 128) && (is_ipaddrv6($pconfig['dst']))) ||
		    (!isset($pconfig['dstmask'])) || is_alias($pconfig['dst'])) {
			return('single');
		}

		return('network');
	}

	return($pconfig['dst']);
}

function build_exttype_list() {
	return get_specialnet('', [SPECIALNET_CHECKPERM, SPECIALNET_COMPAT_ADDR, SPECIALNET_IFADDR]);
}

function exttype_selected() {
	global $pconfig;
	$binat_exttype_flags = [SPECIALNET_COMPAT_ADDR, SPECIALNET_IFADDR];

	if ($pconfig['exttype']) {
		// The rule type came from the $post array, after input errors, so keep it.
		return $pconfig['exttype'];
	}

	$sel = get_specialnet($pconfig['external'], $binat_exttype_flags);

	if (!$sel) {
		return('single');
	}

	return($pconfig['external']);
}

function get_specialnet_type($type, $ipprotocol='inet') {
	if (get_specialnet($type, [SPECIALNET_VIPS])) {
		if (is_ipaddrv6($type)) {
			return '6';
		} elseif (is_ipaddrv4($type)) {
			return '4';
		} else {
			return false;
		}
	}

	foreach (get_configured_interface_with_descr() as $kif => $kdescr) {
		if (($type == "{$kif}ip") || ($type == $kif)) {
			if (($ipprotocol == 'inet') && get_interface_ip($kif)) {
				return '4';
			} elseif (($ipprotocol == 'inet6') && get_interface_ipv6($kif)) {
				return '6';
			}
		}
	}

	return false;
}
?>
