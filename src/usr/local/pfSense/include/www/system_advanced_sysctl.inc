<?php
/*
 * system_advanced_sysctl.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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

require_once("config.gui.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("system.inc");
require_once("util.inc");
require_once("pfsense-utils.inc");

// Functions included by system_advanced_sysctl.php ===========================

// Return a list of system tunables
function getTunables($json = false) {
	$st = system_get_sysctls();

	if (!$json) {
		return $st;
	}

	$tunables = [];

	foreach ($st as $t) {
	   array_push($tunables, $t);
	}

	return(json_encode($tunables));
}

// Delete a system tunable
function deleteTunable($id, $complete = false) {
	global $config;

	init_config_arr(array('sysctl', 'item'));
	$a_tunable = &$config['sysctl']['item'];

	if ($a_tunable[$id]) {
		unset($a_tunable[$id]);
		write_config("Custom System Tunables deleted");
		if ($complete) {
			system_setup_sysctl();
		} else {
			mark_subsystem_dirty('sysctl');
			pfSenseHeader("system_advanced_sysctl.php");
		}
		return true;
	}

	return false;
}

// Update tunable if it exists, otherwise create a new one
function saveTunable($post, $id, $json = false) {
	global $config;

	init_config_arr(array('sysctl', 'item'));
	$a_tunable = &$config['sysctl']['item'];

	$rv = array();

	$rv['pconfig'] = $post;

	$tunableent = array();

	if (!$post['tunable'] || !isset($post['value'])) {
		$input_errors[] = gettext("Both a name and a value must be specified.");
	} else if (preg_match("/[^a-zA-Z0-9.\-_%\/]/", $post['value'])) {
		$input_errors[] = gettext("The value may only contain alphanumeric characters, -, _, %, and /.");
	} else {
		$tunableent['tunable'] = htmlspecialchars($post['tunable']);
		$tunableent['value'] = htmlspecialchars($post['value']);
		$tunableent['descr'] = strip_tags($post['descr']);

		if (isset($id) && isset($a_tunable[$id])) {
			$a_tunable[$id] = $tunableent;
		} else {
			$a_tunable[] = $tunableent;
		}

		if (!$json) {
			mark_subsystem_dirty('sysctl');
		}

		write_config("System Tunables Advanced Settings saved");
		if ($json) {
			system_setup_sysctl();
		}
	}

	$rv['input_errors'] = $input_errors;

	return $json ? json_encode($rv) : $rv;
}

?>