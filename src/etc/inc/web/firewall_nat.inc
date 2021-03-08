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
require_once("wg.inc");
require_once("filter.inc");
require_once("itemid.inc");

$specialsrcdst = explode(" ", "any pptp pppoe l2tp openvpn");
$ifdisp = get_configured_interface_with_descr();
foreach ($ifdisp as $kif => $kdescr) {
	$specialsrcdst[] = "{$kif}";
	$specialsrcdst[] = "{$kif}ip";
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

function MVC_is_specialnet($net) {
	global $specialsrcdst;

	if (!$net) {
		return false;
	}
	if (in_array($net, $specialsrcdst)) {
		return true;
	} else {
		return false;
	}
}

function MVC_build_dsttype_list() {
	global $config, $ifdisp;

	$list = array('any' => gettext('Any'), 'single' => gettext('Single host or alias'), 'network' => gettext('Network'));
	$list['pppoe'] = gettext('PPPoE clients');
	$list['l2tp'] = gettext('L2TP clients');

	foreach ($ifdisp as $if => $ifdesc) {
		$list[$if] = $ifdesc;
		$list[$if . 'ip'] = $ifdesc . ' address';
	}

	$list['(self)'] = gettext("This firewall (self)");

	if (is_array($config['virtualip']['vip'])) {
		foreach ($config['virtualip']['vip'] as $sn) {
			if (($sn['mode'] == "proxyarp" || $sn['mode'] == "other") && $sn['type'] == "network") {
				$list[$sn['subnet'] . '/' . $sn['subnet_bits']] = 'Subnet: ' . $sn['subnet'] . '/' . $sn['subnet_bits'] . ' (' . $sn['descr'] . ')';
				if (isset($sn['noexpand'])) {
					continue;
				}
				$start = ip2long32(gen_subnet($sn['subnet'], $sn['subnet_bits']));
				$end = ip2long32(gen_subnet_max($sn['subnet'], $sn['subnet_bits']));
				$len = $end - $start;
				for ($i = 0; $i <= $len; $i++) {
					$snip = long2ip32($start+$i);

					$list[$snip] = $snip . ' (' . $sn['descr'] . ')';
				}
			} else {
				$list[$sn['subnet']] = $sn['subnet'] . ' (' . $sn['descr'] . ')';
			}
		}
	}

	$rv = array();

	foreach ($list as $ifent => $ifname) {
		$rv[] = array("text" => $ifname, "value" => $ifent);
	}

	return(json_encode($rv));
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

	if (is_wg_enabled()) {
		$iflist['wireguard'] = 'WireGuard';
	}

	return($iflist);
}

?>