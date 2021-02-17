<?php
/*
 * system_advanced.inc
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

require_once("config.gui.inc");
require_once("util.inc");
require_once("pfsense-utils.inc");
require_once("filter.inc");


function getAdvancedNetwork($json = false) {
	global $config;

	$pconfig = array();
	$pconfig['ipv6nat_enable'] = isset($config['diag']['ipv6nat']['enable']);
	$pconfig['ipv6nat_ipaddr'] = isset($config['diag']['ipv6nat']['ipaddr']) ? $config['diag']['ipv6nat']['ipaddr'] : null;
	$pconfig['ipv6allow'] = isset($config['system']['ipv6allow']);
	$pconfig['ipv6dontcreatelocaldns'] = isset($config['system']['ipv6dontcreatelocaldns']);
	$pconfig['global-v6duid'] = isset($config['system']['global-v6duid']) ? $config['system']['global-v6duid'] : null;
	$pconfig['prefer_ipv4'] = isset($config['system']['prefer_ipv4']);
	$pconfig['sharednet'] = isset($config['system']['sharednet']) ? $config['system']['sharednet'] : null;
	$pconfig['disablechecksumoffloading'] = isset($config['system']['disablechecksumoffloading']);
	$pconfig['ip_change_kill_states'] = isset($config['system']['ip_change_kill_states']) ? $config['system']['ip_change_kill_states'] : null;
	$pconfig['hnaltqenable'] = isset($config['system']['hn_altq_enable']);
	$pconfig['disablechecksumoffloading'] = isset($config['system']['disablechecksumoffloading']);
	$pconfig['disablesegmentationoffloading'] = isset($config['system']['disablesegmentationoffloading']);
	$pconfig['disablelargereceiveoffloading'] = isset($config['system']['disablelargereceiveoffloading']);
	$pconfig['duid'] = get_duid_from_file();

	return $json ? json_encode($pconfig) : $pconfig;
}

function saveAdvancedNetworking($post, $json = false) {
	global $g, $config;

	$input_errors = array();
	$rv = array();
	$show_reboot_msg = false;

	if ($post['ipv6nat_enable'] && !is_ipaddr($post['ipv6nat_ipaddr'])) {
		$input_errors[] = gettext("An IP address to NAT IPv6 packets must be specified.");
	}

	switch ($post['ipv6duidtype']) {
	case 1:
		if (!empty($post['ipv6duidllt_time']) && !empty($post['ipv6duidllt_ll'])) {
			$post['global-v6duid'] = format_duid(1, $post['ipv6duidllt_time'], $post['ipv6duidllt_ll']);
		}
		break;
	case 2:
		if (!empty($post['ipv6duiden_en']) && !empty($post['ipv6duiden_id'])) {
			$post['global-v6duid'] = format_duid(2, $post['ipv6duiden_en'], $post['ipv6duiden_id']);
		}
		break;
	case 3:
		if (!empty($post['ipv6duidll'])) {
			$post['global-v6duid'] = format_duid(3, $post['ipv6duidll']);
		}
		break;
	case 4:
		if (!empty($post['ipv6duiduuid'])) {
			$post['global-v6duid'] = format_duid(4, $post['ipv6duiduuid']);
		}
		break;
	}

	if (!empty($post['global-v6duid'])) {
		$post['global-v6duid'] = format_duid(0, $post['global-v6duid']);
		if (!is_duid($post['global-v6duid'])) {
			$input_errors[] = gettext("A valid DUID must be specified.");
		}
	}

	if ($post['ipv6allow'] == "yes" && is_bogonsv6_used(true) &&
	    (!isset($config['system']['maximumtableentries']) ||
	     $config['system']['maximumtableentries'] <
	     $g['minimumtableentries_bogonsv6'])) {
		$input_errors[] = sprintf(gettext(
		    "In order enable IPv6 and block bogon networks the Firewall Maximum Table Entries value in System / Advanced / Firewall must be increased at least to %s."),
		    $g['minimumtableentries_bogonsv6']);
	}

	if (!$json) {
		ob_flush();
		flush();
	}

	if (!$input_errors) {
		if ($post['ipv6nat_enable'] == "yes") {
			init_config_arr(array('diag', 'ipv6nat'));
			$config['diag']['ipv6nat']['enable'] = true;
			$config['diag']['ipv6nat']['ipaddr'] = $post['ipv6nat_ipaddr'];
		} else {
			if (is_array($config['diag']) &&
			    is_array($config['diag']['ipv6nat'])) {
				unset($config['diag']['ipv6nat']['enable']);
				unset($config['diag']['ipv6nat']['ipaddr']);
			}
		}

		if ($post['ipv6allow'] == "yes") {
			$config['system']['ipv6allow'] = true;
		} else {
			unset($config['system']['ipv6allow']);
		}

		if ($post['ipv6dontcreatelocaldns'] == "yes") {
			$config['system']['ipv6dontcreatelocaldns'] = true;
		} else {
			unset($config['system']['ipv6dontcreatelocaldns']);
		}

		if ($post['prefer_ipv4'] == "yes") {
			$config['system']['prefer_ipv4'] = true;
		} else {
			unset($config['system']['prefer_ipv4']);
		}

		if (!empty($post['global-v6duid'])) {
			$config['system']['global-v6duid'] = $post['global-v6duid'];
		} else {
			unset($config['system']['global-v6duid']);
		}

		if ($post['sharednet'] == "yes") {
			$config['system']['sharednet'] = true;
			system_disable_arp_wrong_if();
		} else {
			unset($config['system']['sharednet']);
			system_enable_arp_wrong_if();
		}

		if ((isset($post['disablechecksumoffloading']) xor isset($config['system']['disablechecksumoffloading'])) ||
		    (isset($post['disablesegmentationoffloading']) xor isset($config['system']['disablesegmentationoffloading'])) ||
		    (isset($post['disablelargereceiveoffloading']) xor isset($config['system']['disablelargereceiveoffloading']))) {
			$show_reboot_msg = true;
		}

		if ($post['disablechecksumoffloading'] == "yes") {
			$config['system']['disablechecksumoffloading'] = true;
		} else {
			unset($config['system']['disablechecksumoffloading']);
		}

		if ($post['disablesegmentationoffloading'] == "yes") {
			$config['system']['disablesegmentationoffloading'] = true;
		} else {
			unset($config['system']['disablesegmentationoffloading']);
		}

		if ($post['disablelargereceiveoffloading'] == "yes") {
			$config['system']['disablelargereceiveoffloading'] = true;
		} else {
			unset($config['system']['disablelargereceiveoffloading']);
		}

		if ($post['hnaltqenable'] == "yes") {
			$config['system']['hn_altq_enable'] = true;
		} else {
			unset($config['system']['hn_altq_enable']);
		}

		if ($post['ip_change_kill_states'] == "yes") {
			$config['system']['ip_change_kill_states'] = true;
		} else {
			unset($config['system']['ip_change_kill_states']);
		}

		setup_microcode();

		// Write out configuration (config.xml)
		write_config("Networking Advanced Settings saved");

		// Set preferred protocol
		prefer_ipv4_or_ipv6();

		$pconfig['duid'] = get_duid_from_file();
		$pconfig['disablechecksumoffloading'] = isset($config['system']['disablechecksumoffloading']);
		$pconfig['disablesegmentationoffloading'] = isset($config['system']['disablesegmentationoffloading']);
		$pconfig['disablelargereceiveoffloading'] = isset($config['system']['disablelargereceiveoffloading']);
		$pconfig['hnaltqenable'] = isset($config['system']['hn_altq_enable']);

		$changes_applied = true;
		$retval = 0;
		$retval |= filter_configure();

		$rv['input_errors'] = $input_errors;
		$rv['retval'] = $retval;
		$rv['changes_applied'] = $changes_applied;
		$rv['show_reboot_msg'] = $show_reboot_msg;
		$rv['pconfig'] = $post;

		return $json ? json_encode($rv) : $rv;
	}
}
?>