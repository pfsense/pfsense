<?php
/*
 * firewall_nat_npt.inc
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

// Functions to support firewall_nat_npt.php and firewall_nat_npt_edit.php

require_once("config.gui.inc");
require_once("interfaces.inc");
require_once("util.inc");
require_once("pfsense-utils.inc");
require_once("ipsec.inc");
require_once("filter.inc");

/**
 * Add NPT rules.
 * @param array $rules_list A list of config rules.
 * @param int $index A config rule index used to place the new rule(s).
 * 		A zero or greater value adds the rule(s) after that index.
 * 		A value of -1 adds the rule(s) at the top.
 * 		A value of -2 or lower adds the rule(s) at the end.
 * @param bool $replace If a rule is found where the new rule(s) would
 * 		be added that existing rule will be replaced with the new rule(s).
 */
function add_npt_rules(array $rules_list, int $index = -2, bool $replace = false, array &$changes = []) {
	$index = get_anynat_rule_relative_index('npt', $index);
	if (!isset($index)) {
		return;
	}
	$separators = [];
	$nat_rules = get_anynat_rules_map('npt');
	$changed_rules = false;
	foreach (get_anynat_rules_map('npt', $rules_list) as $new_category_name => $new_category) {
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
					);
					$changed_rules = true;
				}
			}
		}
	}

	if ($changed_rules) {
		set_anynat_rules_map('npt', $nat_rules);
		$changes['npt_rules'] = true;
	}
}

/**
 * Remove NPT rules.
 * @param int|array $search_index One or more indices of config rules.
 */
function remove_npt_rules(int|array $search_index, array &$changes = []) {
	if (!is_array($search_index)) {
		$search_index = [$search_index];
	}
	if (empty($search_index)) {
		return;
	}

	$separators = [];
	$associated_id_list = [];
	$rules_map = get_anynat_rules_map('npt');
	$changed_rules = false;
	foreach (get_anynat_rules_index('npt', $search_index) as $category_name => $category) {
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
		set_anynat_rules_map('npt', $rules_map);
		$changes['npt_rules'] = true;
		foreach ($associated_id_list as $id) {
			delete_id($id, false, $changes);
		}
	}
}

// Save NPt rule
function savenptNATrule($post, $id, $json = false) {
	if (isset($post['after'])) {
		$after = $post['after'];
	}

	unset($input_errors);
	$pconfig = $post;

	// input validation
	$reqdfields = explode(" ", "interface");
	$reqdfieldsn = array(gettext("Interface"));
	$reqdfields[] = "src";
	$reqdfieldsn[] = gettext("Source prefix");
	if (!get_specialnet($post['dsttype'])) {
		$reqdfields[] = "dst";
		$reqdfieldsn[] = gettext("Destination prefix");
	}

	if (!$json) {
		do_input_validation($post, $reqdfields, $reqdfieldsn, $input_errors);
	}

	if (!is_ipaddrv6(trim($post['src']))) {
		$input_errors[] = gettext("The specified source address is not a valid IPv6 prefix");
	}
	if (!is_ipaddrv6(trim($post['dst'])) && !get_specialnet(trim($post['dsttype']))) {
		$input_errors[] = gettext("The specified destination address is not a valid IPv6 prefix");
	}
	if (check_subnetsv6_overlap(get_interface_ipv6($post['interface']), 128, trim($post['dst']), $post['dstmask']) &&
	    !$post['dstnot']) {
		$input_errors[] = gettext("The specified destination address and interface IPv6 address cannot overlap");
	}
	$dstmask = $post['dstmask'];
	if (get_specialnet($post['dsttype'])) {
		/* Find actual destination mask from track source */
		$track6ip = get_interface_track6ip($post['dsttype']);
		$dstmask = $track6ip[1];
	}
	if (!empty($dstmask) && ($post['srcmask'] != $dstmask)) {
		$input_errors[] = gettext("The specified source prefix size must be equal to the destination prefix size.");
	}

	if (!$input_errors) {
		$natent = array();

		$natent['disabled'] = isset($post['disabled']) ? true:false;
		$natent['descr'] = $post['descr'];
		$natent['interface'] = $post['interface'];

		if ($post['src']) {
			$post['src'] = trim($post['src']);
		}
		if (get_specialnet($post['dsttype'])) {
			$post['dst'] = $post['dsttype'];
		} elseif ($post['dst']) {
			$post['dst'] = trim($post['dst']);
		}

		pconfig_to_address($natent['source'], $post['src'], $post['srcmask'], $post['srcnot']);

		pconfig_to_address($natent['destination'], $post['dst'], $post['dstmask'], $post['dstnot'], 0, 0, true);

		$a_npt = get_anynat_rules_list('npt');
		if (isset($id) && $a_npt[$id]) {
			if (isset($a_npt[$id]['labels'])) {
				// Keep rule labels.
				$natent['labels'] = $a_npt[$id]['labels'];
			}
			if (isset($a_npt[$id]['force_category'])) {
				// Keep filter category override.
				$natent['force_category'] = $a_npt[$id]['force_category'];
			}
			add_npt_rules([$natent], $id, true);
		} else {
			if (is_numeric($after)) {
				add_npt_rules([$natent], $after);
			} else {
				add_npt_rules([$natent]);
			}
		}

		if (write_config(gettext("Firewall: NAT: NPt - saved/edited NPt mapping.")) && !$json) {
			mark_subsystem_dirty('natconf');
		}
	}

	$rv = array();
	$rv['input_errors'] = $input_errors;
	$rv['pconfig'] = $pconfig;
	return $json ? json_encode($rv) : $rv;
}

// Retrieve the specified NPt rule
function getnptNATRule($id, $json = false) {
	$a_npt = get_anynat_rules_list('npt');

	$pconfig = array();

	if (isset($id) && $a_npt[$id]) {
		$pconfig['disabled'] = isset($a_npt[$id]['disabled']);

		address_to_pconfig($a_npt[$id]['source'], $pconfig['src'],
			$pconfig['srcmask'], $pconfig['srcnot'],
			$pconfig['srcbeginport'], $pconfig['srcendport']);

		address_to_pconfig($a_npt[$id]['destination'], $pconfig['dst'],
			$pconfig['dstmask'], $pconfig['dstnot'],
			$pconfig['dstbeginport'], $pconfig['dstendport']);

		$dst_arr = explode("/", $pconfig['dst']);
		if (count($dst_arr) > 1) {
			$pconfig['dst'] = $dst_arr[0];
			$pconfig['dstmask'] = $dst_arr[1];
		}

		$pconfig['interface'] = $a_npt[$id]['interface'];
		if (!$pconfig['interface']) {
			$pconfig['interface'] = "wan";
		}

		$pconfig['descr'] = $a_npt[$id]['descr'];
	} else {
		$pconfig['interface'] = "wan";
	}

	return $json ? json_encode($pconfig):$pconfig;
}

// Toggle enabled/disabled status of an NPt rule
function togglenptNATrule($post, $json = false) {
	$a_npt = get_anynat_rules_list('npt');

	if (isset($a_npt[$post['id']]['disabled'])) {
		unset($a_npt[$post['id']]['disabled']);
		$wc_msg = gettext('Firewall: NAT: NPt - enabled a NAT NPt rule.');
	} else {
		$a_npt[$post['id']]['disabled'] = true;
		$wc_msg = gettext('Firewall: NAT: NPt - disabled a NAT NPt rule.');
	}
	set_anynat_rules_list('npt', $a_npt);

	if (write_config($wc_msg) && !$json) {
		mark_subsystem_dirty('natconf');
	}

	if (!$json) {
		header("Location: firewall_nat_npt.php");
		exit;
	}
}

// Toggle multiple NPt rules
function toggleMultiplenptNATrules($post, $json = false) {
	$a_npt = get_anynat_rules_list('npt');

	foreach ($post['rule'] as $rulei) {
		if (isset($a_npt[$rulei]['disabled'])) {
			unset($a_npt[$rulei]['disabled']);
		} else {
			$a_npt[$rulei]['disabled'] = true;
		}
	}
	set_anynat_rules_list('npt', $a_npt);

	if (write_config(gettext("Firewall: NAT: NPt - toggle enable/disable for selected NPt mappings.")) && !$json) {
		mark_subsystem_dirty('natconf');
	}

	if (!$json) {
		header("Location: firewall_nat_npt.php");
		exit;
	}
}

// Delete multiple NPt rules
function deleteMultiplenptNATrules($post, $json = false) {
	remove_npt_rules($post['rule']);

	if (write_config(gettext("Firewall: NAT: NPt - deleted selected NPt mappings.")) && !$json) {
		mark_subsystem_dirty('natconf');
	}

	if (!$json) {
		header("Location: firewall_nat_npt.php");
		exit;
	}
}

// Delete NPt rule
function deletenptNATrule($post, $json = false) {
	remove_npt_rules($post['id']);
	if (write_config(gettext("Firewall: NAT: NPt - deleted NPt mapping.")) && !$json) {
		mark_subsystem_dirty('natconf');
	}

	if(!$json) {
		header("Location: firewall_nat_npt.php");
		exit;
	}
}

// Re-order the NPtNAT rules per the array of indices passed in $post
function reordernptNATrules($post, $json = false) {
	if (is_array($post['rule']) && !empty($post['rule'])) {
		set_anynat_rules_order('npt', $post['rule']);

		if (write_config(gettext("Firewall: NAT: NPt - reordered NPt mappings.")) && !$json) {
			mark_subsystem_dirty('natconf');
		}

		if (!$json) {
			header("Location: firewall_nat_npt.php");
			exit;
		}
	}
}

function applynptNATrules() {
	$retval = 0;
	$retval |= filter_configure();

	if ($retval == 0) {
		clear_subsystem_dirty('natconf');
		clear_subsystem_dirty('filter');
	}

	return $retval;
}

?>
