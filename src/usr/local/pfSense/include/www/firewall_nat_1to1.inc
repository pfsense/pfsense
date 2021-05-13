<?php
/*
 * firewall_nat_1to1.inc
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

// Functions to support firewall_nat_1to1.php and firewall_nat_1to1_edit.php

require_once("config.gui.inc");
require_once("interfaces.inc");
require_once("util.inc");
require_once("pfsense-utils.inc");
require_once("ipsec.inc");
require_once("filter.inc");

// Toggle enabled/disabled status of a 1 to 1 rule
function toggle1to1NATrule($post, $json = false) {
	global $config;

	init_config_arr(array('nat', 'onetoone'));
	$a_1to1 = &$config['nat']['onetoone'];

	if (isset($a_1to1[$_POST['id']]['disabled'])) {
		unset($a_1to1[$_POST['id']]['disabled']);
		$wc_msg = gettext('Firewall: NAT: 1:1 - enabled a NAT 1:1 rule.');
	} else {
		$a_1to1[$_POST['id']]['disabled'] = true;
		$wc_msg = gettext('Firewall: NAT: 1:1 - disabled a NAT 1:1 rule.');
	}

	if (write_config($wc_msg) && !$json) {
		mark_subsystem_dirty('natconf');
	}

	if (!$json) {
		header("Location: firewall_nat_1to1.php");
		exit;
	}
}

// Delete multiple 1 to 1 rules
function deleteMultiple1to1NATrules($post, $json = false) {
	global $config;

	init_config_arr(array('nat', 'onetoone'));
	$a_1to1 = &$config['nat']['onetoone'];

	foreach ($_POST['rule'] as $rulei) {
		unset($a_1to1[$rulei]);
	}

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
	global $config;

	init_config_arr(array('nat', 'onetoone'));
	$a_1to1 = &$config['nat']['onetoone'];

	unset($a_1to1[$post['id']]);
	if (write_config(gettext("Firewall: NAT: 1:1 - deleted NAT 1:1 mapping.")) && !$json) {
		mark_subsystem_dirty('natconf');
	}

	if(!$json) {
		header("Location: firewall_nat_1to1.php");
		exit;
	}
}

// Re-order the 1 to 1 NAT rules per the array of iindicies passed in $post
function reorder1to1NATrules($post, $json = false) {
	global $config;

	if (is_array($post['rule']) && !empty($post['rule'])) {
		init_config_arr(array('nat', 'onetoone'));
		$a_1to1 = &$config['nat']['onetoone'];
		$a_1to1_new = array();

		// if a rule is not in POST[rule], it has been deleted by the user
		foreach ($post['rule'] as $id) {
			$a_1to1_new[] = $a_1to1[$id];
		}

		$a_1to1 = $a_1to1_new;

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
?>