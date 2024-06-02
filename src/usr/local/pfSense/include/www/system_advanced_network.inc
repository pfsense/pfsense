<?php
/*
 * system_advanced_network.inc
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

require_once("config.gui.inc");
require_once("util.inc");
require_once("pfsense-utils.inc");
require_once("filter.inc");
require_once("syslog.inc");

function getAdvancedNetwork($json = false) {
	$pconfig = array();
	$pconfig['ipv6nat_enable'] = config_path_enabled('diag/ipv6nat');
	$pconfig['ipv6nat_ipaddr'] = config_path_enabled('diag/ipv6nat', 'ipaddr') ? config_get_path('diag/ipv6nat/ipaddr') : null;
	$pconfig['ipv6allow'] = config_path_enabled('system', 'ipv6allow');
	$pconfig['ipv6dontcreatelocaldns'] = config_path_enabled('system', 'ipv6dontcreatelocaldns');
	$pconfig['radvddebug'] = config_path_enabled('system', 'radvddebug');
	$pconfig['ignoreiscwarning'] = config_path_enabled('system', 'ignoreiscwarning');
	$pconfig['dhcp6debug'] = config_path_enabled('system', 'dhcp6debug');
	$pconfig['dhcp6norelease'] = config_path_enabled('system', 'dhcp6norelease');
	$pconfig['global-v6duid'] = config_path_enabled('system', 'global-v6duid') ? config_get_path('system/global-v6duid') : null;
	$pconfig['prefer_ipv4'] = config_path_enabled('system', 'prefer_ipv4');
	$pconfig['sharednet'] = config_path_enabled('system', 'sharednet') ? config_get_path('system/sharednet') : null;
	$pconfig['disablechecksumoffloading'] = config_path_enabled('system', 'disablechecksumoffloading');
	$pconfig['ip_change_kill_states'] = config_path_enabled('system', 'ip_change_kill_states') ? config_get_path('system/ip_change_kill_states') : null;
	$pconfig['hnaltqenable'] = config_path_enabled('system', 'hn_altq_enable');
	$pconfig['disablechecksumoffloading'] = config_path_enabled('system', 'disablechecksumoffloading');
	$pconfig['disablesegmentationoffloading'] = config_path_enabled('system', 'disablesegmentationoffloading');
	$pconfig['disablelargereceiveoffloading'] = config_path_enabled('system', 'disablelargereceiveoffloading');
	$pconfig['duid'] = get_duid_from_file();

	/* not kea means isc */
	$dhcpbackend = config_get_path('dhcpbackend', 'isc');
	$pconfig['dhcpbackend'] = (in_array($dhcpbackend, ['isc', 'kea']) ? $dhcpbackend : 'isc');

	return $json ? json_encode($pconfig) : $pconfig;
}

function saveAdvancedNetworking($post, $json = false) {
	global $g;

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
	    ((config_get_path('system/maximumtableentries') === null) ||
	     config_get_path('system/maximumtableentries') <
	     g_get('minimumtableentries_bogonsv6'))) {
		$input_errors[] = sprintf(gettext(
		    "In order enable IPv6 and block bogon networks the Firewall Maximum Table Entries value in System / Advanced / Firewall must be increased at least to %s."),
		    g_get('minimumtableentries_bogonsv6'));
	}

	if (!$json) {
		ob_flush();
		flush();
	}

	if (!$input_errors) {
		if ($post['ipv6nat_enable'] == "yes") {
			config_init_path('diag/ipv6nat');
			config_set_path('diag/ipv6nat/enable', true);
			config_set_path('diag/ipv6nat/ipaddr', $post['ipv6nat_ipaddr']);
		} else {
			if (is_array(config_get_path('diag')) &&
			    is_array(config_get_path('diag/ipv6nat'))) {
				config_del_path('diag/ipv6nat/enable');
				config_del_path('diag/ipv6nat/ipaddr');
			}
		}

		$dhcpd_restart = (!dhcp_is_backend($post['dhcpbackend']));
		config_set_path('dhcpbackend', $post['dhcpbackend']);

		if ($post['ipv6allow'] == "yes") {
			config_set_path('system/ipv6allow', true);
		} else {
			config_del_path('system/ipv6allow');
		}

		if ($post['ipv6dontcreatelocaldns'] == "yes") {
			config_set_path('system/ipv6dontcreatelocaldns', true);
		} else {
			config_del_path('system/ipv6dontcreatelocaldns');
		}

		$syslogrestart = (isset($post['radvddebug']) xor config_path_enabled('system', 'radvddebug')) ? true : false;
		if (isset($post['radvddebug'])) {
			config_set_path('system/radvddebug', true);
		} else {
			config_del_path('system/radvddebug');
		}

		if ($post['ignoreiscwarning'] === 'yes') {
			config_set_path('system/ignoreiscwarning', true);
		} else {
			config_del_path('system/ignoreiscwarning');
		}

		if ($post['dhcp6debug'] == "yes") {
			config_set_path('system/dhcp6debug', true);
		} else {
			config_del_path('system/dhcp6debug');
		}

		if ($post['dhcp6norelease'] == "yes") {
			config_set_path('system/dhcp6norelease', true);
		} else {
			config_del_path('system/dhcp6norelease');
		}

		if ((isset($post['dhcp6debug']) xor config_path_enabled('system', 'dhcp6debug')) ||
		    (isset($post['dhcp6norelease']) xor config_path_enabled('system', 'dhcp6norelease'))) {
			$dhcp6restart = true;
		}

		if ($post['prefer_ipv4'] == "yes") {
			config_set_path('system/prefer_ipv4', true);
		} else {
			config_del_path('system/prefer_ipv4');
		}

		if (!empty($post['global-v6duid'])) {
			config_set_path('system/global-v6duid', $post['global-v6duid']);
		} else {
			config_del_path('system/global-v6duid');
		}

		if ($post['sharednet'] == "yes") {
			config_set_path('system/sharednet', true);
			system_disable_arp_wrong_if();
		} else {
			config_del_path('system/sharednet');
			system_enable_arp_wrong_if();
		}

		if ((isset($post['disablechecksumoffloading']) xor config_path_enabled('system', 'disablechecksumoffloading')) ||
		    (isset($post['disablesegmentationoffloading']) xor config_path_enabled('system', 'disablesegmentationoffloading')) ||
		    (isset($post['disablelargereceiveoffloading']) xor config_path_enabled('system', 'disablelargereceiveoffloading'))) {
			$show_reboot_msg = true;
		}

		if ($post['disablechecksumoffloading'] == "yes") {
			config_set_path('system/disablechecksumoffloading', true);
		} else {
			config_del_path('system/disablechecksumoffloading');
		}

		if ($post['disablesegmentationoffloading'] == "yes") {
			config_set_path('system/disablesegmentationoffloading', true);
		} else {
			config_del_path('system/disablesegmentationoffloading');
		}

		if ($post['disablelargereceiveoffloading'] == "yes") {
			config_set_path('system/disablelargereceiveoffloading', true);
		} else {
			config_del_path('system/disablelargereceiveoffloading');
		}

		if ($post['hnaltqenable'] == "yes") {
			config_set_path('system/hn_altq_enable', true);
		} else {
			config_del_path('system/hn_altq_enable');
		}

		if ($post['ip_change_kill_states'] == "yes") {
			config_set_path('system/ip_change_kill_states', true);
		} else {
			config_del_path('system/ip_change_kill_states');
		}

		setup_microcode();

		// Write out configuration (config.xml)
		write_config("Networking Advanced Settings saved");

		// Set preferred protocol
		prefer_ipv4_or_ipv6();

		$pconfig['duid'] = get_duid_from_file();
		$pconfig['disablechecksumoffloading'] = config_path_enabled('system', 'disablechecksumoffloading');
		$pconfig['disablesegmentationoffloading'] = config_path_enabled('system', 'disablesegmentationoffloading');
		$pconfig['disablelargereceiveoffloading'] = config_path_enabled('system', 'disablelargereceiveoffloading');
		$pconfig['hnaltqenable'] = config_path_enabled('system', 'hn_altq_enable');

		$changes_applied = true;
		$retval = 0;
		$retval |= filter_configure();

		if ($syslogrestart) {
			system_syslogd_start();
		}

		if ($dhcp6restart) {
			foreach (config_get_path('interfaces', []) as $ifname => $ifcfg) {
				if (isset($ifcfg['enable']) && ($ifcfg['ipaddrv6'] == 'dhcp6')) {
					interface_dhcpv6_configure($ifname, $ifcfg);
				}
			}
		}

		if ($dhcpd_restart) {
			services_dhcpd_configure();
		}

		$rv['input_errors'] = $input_errors;
		$rv['retval'] = $retval;
		$rv['changes_applied'] = $changes_applied;
		$rv['show_reboot_msg'] = $show_reboot_msg;
		$rv['pconfig'] = $post;

		return $json ? json_encode($rv) : $rv;
	}
}
