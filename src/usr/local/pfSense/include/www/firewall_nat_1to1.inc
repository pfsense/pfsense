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

function save1to1NATrule($post, $id, $json = false) {
	global $config, $vpn_and_ppp_ifs;

	init_config_arr(array('nat', 'onetoone'));
	$a_1to1 = &$config['nat']['onetoone'];

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

	do_input_validation($post, $reqdfields, $reqdfieldsn, $input_errors);

	if ($post['external']) {
		$post['external'] = trim($post['external']);
	}
	if ($post['src']) {
		$post['src'] = trim($post['src']);
	}
	if ($post['dst']) {
		$post['dst'] = trim($post['dst']);
	}

	if (is_specialnet($post['srctype'])) {
		$post['src'] = $post['srctype'];
		$post['srcmask'] = 0;
	} else if ($post['srctype'] == "single") {
		$post['srcmask'] = (is_ipaddrv4($post['src'])) ? 32 : 128;
	}

	if (is_specialnet($post['dsttype'])) {
		$post['dst'] = $post['dsttype'];
		$post['dstmask'] = 0;
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
		!is_interface_group($post['interface']) && !in_array($post['interface'], $vpn_and_ppp_ifs)) {
		$input_errors[] = gettext("The interface does not have an address from the specified address family.");
	}

	if ($post['external'] && !is_specialnet($post['exttype']) &&
	    ((($post['ipprotocol'] == 'inet') && (is_ipaddrv4($post['external']))) ||
	    (($post['ipprotocol'] == 'inet6') && (is_ipaddrv6($post['external']))))) {
		$extipaddrtype = validateipaddr($post['external'], IPV4V6, "External subnet IP", $input_errors, false);
	} elseif (is_specialnet($post['exttype'])) {
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

	if ($post['src'] && !is_specialnet($post['srctype']) &&
	    ((($post['ipprotocol'] == 'inet') && (is_ipaddrv4($post['src']))) ||
	    (($post['ipprotocol'] == 'inet6') && (is_ipaddrv6($post['src']))))) {
		$srcipaddrtype = validateipaddr($post['src'], IPV4V6, "Internal IP", $input_errors, false);
	} elseif (is_specialnet($post['srctype'])) {
		$srcipaddrtype = get_specialnet_type($post['srctype'], $post['ipprotocol']);
	}

	if (($post['src'] != 'any') && !$srcipaddrtype) {
		$input_errors[] = gettext("The internal IP is not from the specified address family.");
	}

	if ($post['dst'] && $post['dstmask'] && !is_numericint($post['dstmask'])) {
		$input_errors[] = gettext("A valid destination bit count must be specified.");
	}

	if ($post['dst'] && !is_specialnet($post['dsttype']) && (is_alias($post['dst']) ||
	    (($post['ipprotocol'] == 'inet') && (is_ipaddrv4($post['dst']))) ||
	    (($post['ipprotocol'] == 'inet6') && (is_ipaddrv6($post['dst']))))) {
		$dstipaddrtype = validateipaddr($post['dst'], IPV4V6, "Destination address", $input_errors, true);
	} elseif (is_specialnet($post['dsttype'])) {
		$dstipaddrtype = get_specialnet_type($post['dsttype'], $post['ipprotocol']);
	}

	if (($post['dst'] != 'any') && !$dstipaddrtype) {
		$input_errors[] = gettext("The destination address is not from the specified address family.");
	}

	/* check for overlaps with other 1:1 */
	foreach ($a_1to1 as $natent) {
		if (isset($id) && ($a_1to1[$id]) && ($a_1to1[$id] === $natent)) {
			continue;
		}

		if (check_subnets_overlap($post['internal'], $post['subnet'], $natent['internal'], $natent['subnet'])) {
			//$input_errors[] = "Another 1:1 rule overlaps with the specified internal subnet.";
			//break;
		}
	}

	if (is_specialnet($post['exttype'])) {
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
			$post['srcmask'], $post['srcnot']);

		pconfig_to_address($natent['destination'], $post['dst'],
			$post['dstmask'], $post['dstnot']);

		if ($post['natreflection'] == "enable" || $post['natreflection'] == "disable") {
			$natent['natreflection'] = $post['natreflection'];
		} else {
			unset($natent['natreflection']);
		}

		if (isset($id) && $a_1to1[$id]) {
			$a_1to1[$id] = $natent;
		} else {
			if (is_numeric($after)) {
				array_splice($a_1to1, $after+1, 0, array($natent));
			} else {
				$a_1to1[] = $natent;
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
	global $config;

	init_config_arr(array('nat', 'onetoone'));
	$a_1to1 = &$config['nat']['onetoone'];

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
	global $config;

	init_config_arr(array('nat', 'onetoone'));
	$a_1to1 = &$config['nat']['onetoone'];

	if (isset($a_1to1[$post['id']]['disabled'])) {
		unset($a_1to1[$post['id']]['disabled']);
		$wc_msg = gettext('Firewall: NAT: 1:1 - enabled a NAT 1:1 rule.');
	} else {
		$a_1to1[$post['id']]['disabled'] = true;
		$wc_msg = gettext('Firewall: NAT: 1:1 - disabled a NAT 1:1 rule.');
	}

	if (write_config($wc_msg) && !$json) {
		mark_subsystem_dirty('natconf');
	}

	if (!$json) {
		header("Location: firewall_nat_1to1.php");
		exit;
	} else {
		$a_nat = &$config['nat']['onetoone'];
		return isset($a_nat[$post['id']]['disabled']) ? "disabled":"enabled";
	}
}

// Delete multiple 1 to 1 rules
function deleteMultiple1to1NATrules($post, $json = false) {
	global $config;

	init_config_arr(array('nat', 'onetoone'));
	$a_1to1 = &$config['nat']['onetoone'];

	foreach ($post['rule'] as $rulei) {
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

function build_srctype_list() {
	global $pconfig, $ifdisp;

	$list = array('any' => gettext('Any'), 'single' => gettext('Single host'), 'network' => gettext('Network'));

	$sel = is_specialnet($pconfig['src']);

	if (have_ruleint_access("pppoe")) {
		$list['pppoe'] = gettext('PPPoE clients');
	}

	if (have_ruleint_access("l2tp")) {
		$list['l2tp'] = gettext('L2TP clients');
	}

	foreach ($ifdisp as $ifent => $ifdesc) {
		if (have_ruleint_access($ifent)) {
			$list[$ifent] = $ifdesc . ' net';
			$list[$ifent . 'ip'] = $ifdesc . ' address';
		}
	}

	return($list);
}

function srctype_selected() {
	global $pconfig;

	if ($pconfig['srctype']) {
		// The rule type came from the $post array, after input errors, so keep it.
		return $pconfig['srctype'];
	}

	$sel = is_specialnet($pconfig['src']);

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
	global $pconfig, $config, $ifdisp;

	$sel = is_specialnet($pconfig['dst']);
	$list = array('any' => gettext('Any'), 'single' => gettext('Single host or alias'), 'network' => gettext('Network'));

	if (have_ruleint_access("pppoe")) {
		$list['pppoe'] = gettext('PPPoE clients');
	}

	if (have_ruleint_access("l2tp")) {
		$list['l2tp'] = gettext('L2TP clients');
	}

	foreach ($ifdisp as $if => $ifdesc) {
		if (have_ruleint_access($if)) {
			$list[$if] = $ifdesc;
			$list[$if . 'ip'] = $ifdesc . ' address';
		}
	}

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

	return($list);
}

function dsttype_selected() {
	global $pconfig;

	if ($pconfig['dsttype']) {
		// The rule type came from the $post array, after input errors, so keep it.
		return $pconfig['dsttype'];
	}

	$sel = is_specialnet($pconfig['dst']);

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
	global $pconfig, $ifdisp;

	$list = array('single' => gettext('Single host'));

	foreach ($ifdisp as $ifent => $ifdesc) {
		if (have_ruleint_access($ifent)) {
			$list[$ifent . 'ip'] = $ifdesc . ' address';
		}
	}

	return($list);
}

function exttype_selected() {
	global $pconfig;

	if ($pconfig['exttype']) {
		// The rule type came from the $post array, after input errors, so keep it.
		return $pconfig['exttype'];
	}

	$sel = is_specialnet($pconfig['external']);

	if (!$sel) {
		return('single');
	}

	return($pconfig['external']);
}

function get_specialnet_type($type, $ipprotocol='inet') {

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
