<?php
/*
 * firewall_nat_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

##|+PRIV
##|*IDENT=page-firewall-nat-portforward-edit
##|*NAME=Firewall: NAT: Port Forward: Edit
##|*DESCR=Allow access to the 'Firewall: NAT: Port Forward: Edit' page.
##|*MATCH=firewall_nat_edit.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("itemid.inc");
require_once("ipsec.inc");
require_once("filter.inc");
require_once("shaper.inc");

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/firewall_nat.php');

$specialsrcdst = explode(" ", "any (self) pptp pppoe l2tp openvpn");
$ifdisp = get_configured_interface_with_descr();

foreach ($ifdisp as $kif => $kdescr) {
	$specialsrcdst[] = "{$kif}";
	$specialsrcdst[] = "{$kif}ip";
}

init_config_arr(array('filter', 'rule'));
init_config_arr(array('nat', 'separator'));
init_config_arr(array('nat', 'rule'));
$a_nat = &$config['nat']['rule'];
$a_separators = &$config['nat']['separator'];

if (isset($_REQUEST['id']) && is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

if (isset($_REQUEST['after']) && (is_numericint($_REQUEST['after']) || $_REQUEST['after'] == "-1")) {
	$after = $_REQUEST['after'];
}

if (isset($_REQUEST['dup']) && is_numericint($_REQUEST['dup'])) {
	$id = $_REQUEST['dup'];
	$after = $_REQUEST['dup'];
}

if (isset($id) && $a_nat[$id]) {
	if (isset($a_nat[$id]['created']) && is_array($a_nat[$id]['created'])) {
		$pconfig['created'] = $a_nat[$id]['created'];
	}

	if (isset($a_nat[$id]['updated']) && is_array($a_nat[$id]['updated'])) {
		$pconfig['updated'] = $a_nat[$id]['updated'];
	}

	$pconfig['disabled'] = isset($a_nat[$id]['disabled']);
	$pconfig['nordr'] = isset($a_nat[$id]['nordr']);

	address_to_pconfig($a_nat[$id]['source'], $pconfig['src'],
		$pconfig['srcmask'], $pconfig['srcnot'],
		$pconfig['srcbeginport'], $pconfig['srcendport']);

	address_to_pconfig($a_nat[$id]['destination'], $pconfig['dst'],
		$pconfig['dstmask'], $pconfig['dstnot'],
		$pconfig['dstbeginport'], $pconfig['dstendport']);

	if (($pconfig['dstbeginport'] == 1) && ($pconfig['dstendport'] == 65535)) {
		$pconfig['dstbeginport'] = "any";
		$pconfig['dstendport'] = "any";
	}

	$pconfig['ipprotocol'] = $a_nat[$id]['ipprotocol'];
	$pconfig['proto'] = $a_nat[$id]['protocol'];
	$pconfig['localip'] = $a_nat[$id]['target'];
	$pconfig['localbeginport'] = $a_nat[$id]['local-port'];
	$pconfig['descr'] = $a_nat[$id]['descr'];
	$pconfig['interface'] = $a_nat[$id]['interface'];
	$pconfig['associated-rule-id'] = $a_nat[$id]['associated-rule-id'];
	$pconfig['nosync'] = isset($a_nat[$id]['nosync']);
	$pconfig['natreflection'] = $a_nat[$id]['natreflection'];

	if (!$pconfig['interface']) {
		$pconfig['interface'] = "wan";
	}
} else {
	$pconfig['interface'] = "wan";
	$pconfig['src'] = "any";
	$pconfig['srcbeginport'] = "any";
	$pconfig['srcendport'] = "any";
}

if (isset($_REQUEST['dup']) && is_numericint($_REQUEST['dup'])) {
	unset($id);
}

/*	run through $_POST items encoding HTML entities so that the user
 *	cannot think he is slick and perform a XSS attack on the unwilling
 */
unset($input_errors);

foreach ($_POST as $key => $value) {
	if ($key == 'descr') {
		continue;
	}

	$temp = $value;
	$newpost = htmlentities($temp);

	if ($newpost != $temp) {
		$input_errors[] = sprintf(gettext("Invalid characters detected %s. Please remove invalid characters and save again."), $temp);
	}
}

if ($_POST['save']) {
	if (strtoupper($_POST['proto']) == "TCP" || strtoupper($_POST['proto']) == "UDP" || strtoupper($_POST['proto']) == "TCP/UDP") {
		if ($_POST['srcbeginport_cust'] && !$_POST['srcbeginport']) {
			$_POST['srcbeginport'] = trim($_POST['srcbeginport_cust']);
		}
		if ($_POST['srcendport_cust'] && !$_POST['srcendport']) {
			$_POST['srcendport'] = trim($_POST['srcendport_cust']);
		}

		if ($_POST['srcbeginport'] == "any") {
			$_POST['srcbeginport'] = 0;
			$_POST['srcendport'] = 0;
		} else {
			if (!$_POST['srcendport']) {
				$_POST['srcendport'] = $_POST['srcbeginport'];
			}
		}
		if ($_POST['srcendport'] == "any") {
			$_POST['srcendport'] = $_POST['srcbeginport'];
		}

		if ($_POST['dstbeginport_cust'] && !$_POST['dstbeginport']) {
			$_POST['dstbeginport'] = trim($_POST['dstbeginport_cust']);
		}
		if ($_POST['dstendport_cust'] && !$_POST['dstendport']) {
			$_POST['dstendport'] = trim($_POST['dstendport_cust']);
		}

		if ($_POST['dstbeginport'] == "any") {
			$_POST['dstbeginport'] = "1";
			$_POST['dstendport'] = "65535";
			$_POST['localbeginport'] = "1";
		} else {
			if (!$_POST['dstendport']) {
				$_POST['dstendport'] = $_POST['dstbeginport'];
			}
		}
		if ($_POST['dstendport'] == "any") {
			$_POST['dstendport'] = $_POST['dstbeginport'];
		}

		if ($_POST['localbeginport_cust'] && !$_POST['localbeginport']) {
			$_POST['localbeginport'] = trim($_POST['localbeginport_cust']);
		}

		/* Make beginning port end port if not defined and endport is */
		if (!$_POST['srcbeginport'] && $_POST['srcendport']) {
			$_POST['srcbeginport'] = $_POST['srcendport'];
		}
		if (!$_POST['dstbeginport'] && $_POST['dstendport']) {
			$_POST['dstbeginport'] = $_POST['dstendport'];
		}
	} else {
		$_POST['srcbeginport'] = 0;
		$_POST['srcendport'] = 0;
		$_POST['dstbeginport'] = 0;
		$_POST['dstendport'] = 0;
	}

	if (is_specialnet($_POST['srctype'])) {
		$_POST['src'] = $_POST['srctype'];
		$_POST['srcmask'] = 0;
	} elseif ($_POST['srctype'] == "single") {
		if (is_ipaddrv6($_POST['src'])) {
			$_POST['srcmask'] = 128;
		} else {
			$_POST['srcmask'] = 32;
		}
	}

	if (is_specialnet($_POST['dsttype'])) {
		$_POST['dst'] = $_POST['dsttype'];
		$_POST['dstmask'] = 0;
	} elseif ($_POST['dsttype'] == "single") {
		if (is_ipaddrv6($_POST['dst'])) {
			$_POST['dstmask'] = 128;
		} else {
			$_POST['dstmask'] = 32;
		}
	} elseif (is_ipaddr($_POST['dsttype'])) {
		$_POST['dst'] = $_POST['dsttype'];
		$_POST['dsttype'] = "single";
		if (is_ipaddrv6($_POST['dst'])) {
			$_POST['dstmask'] = 128;
		} else {
			$_POST['dstmask'] = 32;
		}
	}

	if (is_specialnet($_POST['localtype'])) {
		$_POST['localip'] = $_POST['localtype'];
	}

	$pconfig = $_POST;

	/* input validation */
	if (strtoupper($_POST['proto']) == "TCP" or strtoupper($_POST['proto']) == "UDP" or strtoupper($_POST['proto']) == "TCP/UDP") {
		$reqdfields = explode(" ", "interface proto dstbeginport dstendport");
		$reqdfieldsn = array(gettext("Interface"), gettext("Protocol"), gettext("Destination port from"), gettext("Destination port to"));
	} else {
		$reqdfields = explode(" ", "interface proto");
		$reqdfieldsn = array(gettext("Interface"), gettext("Protocol"));
	}

	if ($_POST['srctype'] == "single" || $_POST['srctype'] == "network") {
		$reqdfields[] = "src";
		$reqdfieldsn[] = gettext("Source address");
	}

	if ($_POST['dsttype'] == "single" || $_POST['dsttype'] == "network") {
		$reqdfields[] = "dst";
		$reqdfieldsn[] = gettext("Destination address");
	}

	if (!isset($_POST['nordr'])) {
		$reqdfields[] = "localip";
		$reqdfieldsn[] = gettext("Redirect target IP");
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!$_POST['srcbeginport']) {
		$_POST['srcbeginport'] = 0;
		$_POST['srcendport'] = 0;
	}

	if (!$_POST['dstbeginport']) {
		$_POST['dstbeginport'] = 0;
		$_POST['dstendport'] = 0;
	}

	if ($_POST['src']) {
		$_POST['src'] = trim($_POST['src']);
	}
	if ($_POST['dst']) {
		$_POST['dst'] = trim($_POST['dst']);
	}
	if ($_POST['localip']) {
		$_POST['localip'] = trim($_POST['localip']);
	}

	if (!array_key_exists($_POST['interface'], create_interface_list())) {
		$input_errors[] = gettext("The submitted interface does not exist.");
	}

	if (!isset($_POST['nordr']) && $_POST['localip'] &&
	    !is_ipaddroralias($_POST['localip']) && !is_specialnet($_POST['localtype'])) {
		$input_errors[] = sprintf(gettext("\"%s\" is not a valid redirect target IP address or host alias."), $_POST['localip']);
	}

	if ($_POST['localip']) {
		if (is_specialnet($_POST['localtype'])) {
			foreach ($ifdisp as $kif => $kdescr) {
				if ($_POST['localtype'] == "{$kif}ip") {
					if (($_POST['ipprotocol'] == 'inet') && !get_interface_ip($kif)) {
						$input_errors[] = sprintf(gettext("Redirect interface must have IPv4 address."));
						break;
					} elseif (($_POST['ipprotocol'] == 'inet6') && !get_interface_ipv6($kif)) {
						$input_errors[] = sprintf(gettext("Redirect interface must have IPv6 address."));
						break;
					}
				}
			}
		} elseif (($_POST['ipprotocol'] == 'inet') && is_ipaddrv6($_POST['localip'])) {
			$input_errors[] = sprintf(gettext("Redirect target IP must be IPv4."));
		} elseif (($_POST['ipprotocol'] == 'inet6') && is_ipaddrv4($_POST['localip'])) {
			$input_errors[] = sprintf(gettext("Redirect target IP must be IPv6."));
		}
	}

	if ($_POST['srcbeginport'] && !is_port_or_alias($_POST['srcbeginport'])) {
		$input_errors[] = sprintf(gettext("%s is not a valid start source port. It must be a port alias or integer between 1 and 65535."), $_POST['srcbeginport']);
	}
	if ($_POST['srcendport'] && !is_port_or_alias($_POST['srcendport'])) {
		$input_errors[] = sprintf(gettext("%s is not a valid end source port. It must be a port alias or integer between 1 and 65535."), $_POST['srcendport']);
	}
	if ($_POST['dstbeginport'] && !is_port_or_alias($_POST['dstbeginport'])) {
		$input_errors[] = sprintf(gettext("%s is not a valid start destination port. It must be a port alias or integer between 1 and 65535."), $_POST['dstbeginport']);
	}
	if ($_POST['dstendport'] && !is_port_or_alias($_POST['dstendport'])) {
		$input_errors[] = sprintf(gettext("%s is not a valid end destination port. It must be a port alias or integer between 1 and 65535."), $_POST['dstendport']);
	}

	if ((strtoupper($_POST['proto']) == "TCP" || strtoupper($_POST['proto']) == "UDP" || strtoupper($_POST['proto']) == "TCP/UDP") && (!isset($_POST['nordr']) && !is_port_or_alias($_POST['localbeginport']))) {
		$input_errors[] = sprintf(gettext("%s is not a valid redirect target port. It must be a port alias or integer between 1 and 65535."), $_POST['localbeginport']);
	}

	/* if user enters an alias and selects "network" then disallow. */
	if (($_POST['srctype'] == "network" && is_alias($_POST['src'])) ||
	    ($_POST['dsttype'] == "network" && is_alias($_POST['dst']))) {
		$input_errors[] = gettext("Alias entries must specify a single host or alias.");
	}

	if (!is_specialnet($_POST['srctype'])) {
		if (($_POST['src'] && !is_ipaddroralias($_POST['src']))) {
			$input_errors[] = sprintf(gettext("%s is not a valid source IP address or alias."), $_POST['src']);
		}
		if ($_POST['src']) {
			if (($_POST['ipprotocol'] == 'inet') && is_ipaddrv6($_POST['src'])) {
				$input_errors[] = sprintf(gettext("Source must be IPv4."));
			} elseif (($_POST['ipprotocol'] == 'inet6') && is_ipaddrv4($_POST['src'])) {
				$input_errors[] = sprintf(gettext("Source must be IPv6."));
			}
		}
		if (is_ipaddr($_POST['src']) && !is_subnet($_POST['src'] . '/' . $_POST['srcmask'])) {
			$input_errors[] = gettext("A valid source bit count must be specified.");
		}
	}

	if (!is_specialnet($_POST['dsttype'])) {
		if (($_POST['dst'] && !is_ipaddroralias($_POST['dst']))) {
			$input_errors[] = sprintf(gettext("%s is not a valid destination IP address or alias."), $_POST['dst']);
		}
		if ($_POST['dst']) {
			if (($_POST['ipprotocol'] == 'inet') && is_ipaddrv6($_POST['dst'])) {
				$input_errors[] = sprintf(gettext("Destination must be IPv4."));
			} elseif (($_POST['ipprotocol'] == 'inet6') && is_ipaddrv4($_POST['dst'])) {
				$input_errors[] = sprintf(gettext("Destination must be IPv6."));
			}
		}
		if (is_ipaddr($_POST['dst']) && !is_subnet($_POST['dst'] . '/' . $_POST['dstmask'])) {
			$input_errors[] = gettext("A valid destination bit count must be specified.");
		}
	}

	if ($_POST['srcbeginport'] > $_POST['srcendport']) {
		/* swap */
		$tmp = $_POST['srcendport'];
		$_POST['srcendport'] = $_POST['srcbeginport'];
		$_POST['srcbeginport'] = $tmp;
	}

	if ($_POST['dstbeginport'] > $_POST['dstendport']) {
		/* swap */
		$tmp = $_POST['dstendport'];
		$_POST['dstendport'] = $_POST['dstbeginport'];
		$_POST['dstbeginport'] = $tmp;
	}

	if (!$input_errors) {
		if (!isset($_POST['nordr']) && ((int) $_POST['dstendport'] - (int) $_POST['dstbeginport'] + (int) $_POST['localbeginport']) > 65535) {
			$input_errors[] = gettext("The target port range must be an integer between 1 and 65535.");
		}
	}

	/* check for overlaps */
	foreach ($a_nat as $natent) {
		if (isset($id) && ($a_nat[$id]) && ($a_nat[$id] === $natent)) {
			continue;
		}
		if ($natent['interface'] != $_POST['interface']) {
			continue;
		}
		if ($natent['destination']['address'] != $_POST['dst']) {
			continue;
		}
		if (($natent['proto'] != $_POST['proto']) && ($natent['proto'] != "tcp/udp") && ($_POST['proto'] != "tcp/udp")) {
			continue;
		}

		list($begp, $endp) = explode("-", $natent['destination']['port']);
		if (!$endp) {
			$endp = $begp;
		}

		if (!((($_POST['dstbeginport'] < $begp) && ($_POST['dstendport'] < $begp)) ||
		      (($_POST['dstbeginport'] > $endp) && ($_POST['dstendport'] > $endp)))) {
			$input_errors[] = gettext("The destination port range overlaps with an existing entry.");
			break;
		}
	}

	if (!$input_errors) {

		$natent = array();

		if (isset($_POST['disabled'])) {
			$natent['disabled'] = true;
		}

		if (isset($_POST['nordr'])) {
			$natent['nordr'] = true;
		}

		if ($natent['nordr']) {
			$_POST['associated-rule-id'] = '';
			$_POST['filter-rule-association'] = '';
		}

		pconfig_to_address($natent['source'], $_POST['src'],
			$_POST['srcmask'], $_POST['srcnot'],
			$_POST['srcbeginport'], $_POST['srcendport']);

		pconfig_to_address($natent['destination'], $_POST['dst'],
			$_POST['dstmask'], $_POST['dstnot'],
			$_POST['dstbeginport'], $_POST['dstendport']);

		$natent['ipprotocol'] = $_POST['ipprotocol'];
		$natent['protocol'] = $_POST['proto'];

		if (!isset($natent['nordr'])) {
			$natent['target'] = $_POST['localip'];
			$natent['local-port'] = $_POST['localbeginport'];
		}

		$natent['interface'] = $_POST['interface'];
		$natent['descr'] = $_POST['descr'];
		$natent['associated-rule-id'] = $_POST['associated-rule-id'];

		if ($_POST['filter-rule-association'] == "pass") {
			$natent['associated-rule-id'] = "pass";
		}

		if ($_POST['nosync'] == "yes") {
			$natent['nosync'] = true;
		} else {
			unset($natent['nosync']);
		}

		if ($_POST['natreflection'] == "enable" || $_POST['natreflection'] == "purenat" || $_POST['natreflection'] == "disable") {
			$natent['natreflection'] = $_POST['natreflection'];
		} else {
			unset($natent['natreflection']);
		}

		// If we used to have an associated filter rule, but no-longer should have one
		if (!empty($a_nat[$id]) && (empty($natent['associated-rule-id']) || $natent['associated-rule-id'] != $a_nat[$id]['associated-rule-id'])) {
			// Delete the previous rule
			delete_id($a_nat[$id]['associated-rule-id'], $config['filter']['rule']);
			mark_subsystem_dirty('filter');
		}

		$need_filter_rule = false;
		// Updating a rule with a filter rule associated
		if (!empty($natent['associated-rule-id'])) {
			$need_filter_rule = true;
		}
		// Create a rule or if we want to create a new one
		if ($natent['associated-rule-id'] == 'new') {
			$need_filter_rule = true;
			unset($natent['associated-rule-id']);
			$_POST['filter-rule-association']='add-associated';
		}
		// If creating a new rule, where we want to add the filter rule, associated or not
		else if (isset($_POST['filter-rule-association']) &&
		    ($_POST['filter-rule-association'] == 'add-associated' ||
		     $_POST['filter-rule-association'] == 'add-unassociated')) {
			$need_filter_rule = true;
		}

		if ($need_filter_rule == true) {
			/* auto-generate a matching firewall rule */
			$filterent = array();
			unset($filterentid);

			// If a rule already exists, load it
			if (!empty($natent['associated-rule-id'])) {
				$filterentid = get_id($natent['associated-rule-id'], $config['filter']['rule']);
				if ($filterentid === false) {
					$filterent['associated-rule-id'] = $natent['associated-rule-id'];
				} else {
					$filterent =& $config['filter']['rule'][$filterentid];
				}
			}

			pconfig_to_address($filterent['source'], $_POST['src'],
				$_POST['srcmask'], $_POST['srcnot'],
				$_POST['srcbeginport'], $_POST['srcendport']);

			// Update interface, protocol and destination
			$filterent['interface'] = $_POST['interface'];
			$filterent['ipprotocol'] = $_POST['ipprotocol'];
			$filterent['protocol'] = $_POST['proto'];
			if (is_specialnet($_POST['localtype'])) {
				$filterent['destination']['network'] = $_POST['localtype'];
			} else {
				$filterent['destination']['address'] = $_POST['localip'];
			}

			if (isset($_POST['disabled'])) {
				$filterent['disabled'] = true;
			}

			$dstpfrom = $_POST['localbeginport'];
			$dstpto = (int) $dstpfrom + (int) $_POST['dstendport'] - (int) $_POST['dstbeginport'];

			if ($dstpfrom == $dstpto) {
				$filterent['destination']['port'] = $dstpfrom;
			} else {
				$filterent['destination']['port'] = $dstpfrom . "-" . $dstpto;
			}

			/*
			 * Our firewall filter description may be no longer than
			 * 63 characters, so don't let it be.
			 */
			$filterent['descr'] = substr("NAT " . $_POST['descr'], 0, 62);

			// If this is a new rule, create an ID and add the rule
			if ($_POST['filter-rule-association'] == 'add-associated') {
				$filterent['associated-rule-id'] = $natent['associated-rule-id'] = get_unique_id();
				$filterent['tracker'] = (int)microtime(true);
				$filterent['created'] = make_config_revision_entry(null, gettext("NAT Port Forward"));
				$config['filter']['rule'][] = $filterent;
			}

			mark_subsystem_dirty('filter');
		}

		if (isset($a_nat[$id]['created']) && is_array($a_nat[$id]['created'])) {
			$natent['created'] = $a_nat[$id]['created'];
		}

		$natent['updated'] = make_config_revision_entry();

		// Allow extending of the firewall edit page and include custom input validation
		pfSense_handle_custom_code("/usr/local/pkg/firewall_nat/pre_write_config");

		// Update the NAT entry now
		if (isset($id) && $a_nat[$id]) {

			if (isset($natent['associated-rule-id']) &&
			    (isset($a_nat[$id]['disabled']) !== isset($natent['disabled']))) {
				// Check for filter rule associations
				toggle_id($natent['associated-rule-id'],
				    $config['filter']['rule'],
				    !isset($natent['disabled']));
				mark_subsystem_dirty('filter');
			}
			$a_nat[$id] = $natent;
		} else {
			$natent['created'] = make_config_revision_entry();
			if (is_numeric($after)) {
				array_splice($a_nat, $after+1, 0, array($natent));

				// Update the separators
				$ridx = $after;
				$mvnrows = +1;
				move_separators($a_separators, $ridx, $mvnrows);
			} else {
				$a_nat[] = $natent;
			}
		}

		if (write_config(gettext("Firewall: NAT: Port Forward - saved/edited a port forward rule."))) {
			mark_subsystem_dirty('natconf');
		}

		header("Location: firewall_nat.php");
		exit;
	}
}

function build_srctype_list() {
	global $pconfig, $ifdisp, $config;

	$list = array('any' => gettext('Any'), 'single' => gettext('Single host or alias'), 'network' => gettext('Network'));

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
	global $pconfig, $config;

	$selected = "";
	if (array_key_exists($pconfig['src'], build_srctype_list())) {
		$selected = $pconfig['src'];
	} else {
		if ((($pconfig['srcmask'] == 32) && ($pconfig['ipprotocol'] == 'inet')) ||
		    (($pconfig['srcmask'] == 128) && ($pconfig['ipprotocol'] == 'inet6')) || 
		    is_alias($pconfig['src'])) {
			$selected = 'single';
		} else {
			$selected = 'network';
		}
	}

	return($selected);
}

function build_dsttype_list() {
	global $pconfig, $config, $ifdisp;

	$list = array('any' => gettext('Any'), 'single' => gettext('Single host or alias'), 'network' => gettext('Network'), '(self)' => gettext('This Firewall (self)'));

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

	//Temporary array so we can sort IPs
	$templist = array();
	if (is_array($config['virtualip']['vip'])) {
		foreach ($config['virtualip']['vip'] as $sn) {
			if ((($sn['mode'] == "proxyarp") || ($sn['mode'] == "other")) &&
			    ($sn['type'] == "network") && is_subnetv4($sn['subnet'])) {
				$templist[$sn['subnet'] . '/' . $sn['subnet_bits']] = 'Subnet: ' . $sn['subnet'] . '/' . $sn['subnet_bits'] . ' (' . $sn['descr'] . ')';
				if (isset($sn['noexpand'])) {
					continue;
				}
				$start = ip2long32(gen_subnet($sn['subnet'], $sn['subnet_bits']));
				$end = ip2long32(gen_subnet_max($sn['subnet'], $sn['subnet_bits']));
				$len = $end - $start;

				for ($i = 0; $i <= $len; $i++) {
					$snip = long2ip32($start+$i);

					$templist[$snip] = $snip . ' (' . $sn['descr'] . ')';
				}
			} else {
				$templist[$sn['subnet']] = $sn['subnet'] . ' (' . $sn['descr'] . ')';
			}
		}
	}

	//Sort temp IP array and append onto main array
	asort($templist);
	$list = array_merge($list, $templist);
	unset($templist);

	return($list);
}

function dsttype_selected() {
	global $pconfig, $config;

	$selected = "";
	if (array_key_exists($pconfig['dst'], build_dsttype_list())) {
		$selected = $pconfig['dst'];
	} else {
		if ((($pconfig['dstmask'] == 32) && ($pconfig['ipprotocol'] == 'inet')) ||
		    (($pconfig['dstmask'] == 128) && ($pconfig['ipprotocol'] == 'inet6')) ||
		    is_alias($pconfig['dst'])) {
			$selected = 'single';
		} else {
			$selected = 'network';
		}
	}

	return($selected);
}

function build_localtype_list() {
	global $pconfig, $ifdisp;

	$list = array('single' => gettext('Single host'));

	foreach ($ifdisp as $ifent => $ifdesc) {
		if (have_ruleint_access($ifent)) {
			$list[$ifent . 'ip'] = $ifdesc . ' address';
		}
	}

	return($list);
}

function localtype_selected() {
	global $pconfig;

	if ($pconfig['localtype']) {
		// The rule type came from the $_POST array, after input errors, so keep it.
		return $pconfig['localtype'];
	}

	$sel = is_specialnet($pconfig['localip']);

	if (!$sel) {
		return('single');
	}

	return($pconfig['localip']);
}

$pgtitle = array(gettext("Firewall"), gettext("NAT"), gettext("Port Forward"), gettext("Edit"));
$pglinks = array("", "firewall_nat.php", "firewall_nat.php", "@self");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('Edit Redirect Entry');

$section->addInput(new Form_Checkbox(
	'disabled',
	'Disabled',
	'Disable this rule',
	$pconfig['disabled']
));

$section->addInput(new Form_Checkbox(
	'nordr',
	'No RDR (NOT)',
	'Disable redirection for traffic matching this rule',
	$pconfig['nordr']
))->setHelp('This option is rarely needed. Don\'t use this without thorough knowledge of the implications.');

$section->addInput(new Form_Select(
	'interface',
	'*Interface',
	$pconfig['interface'],
	filter_get_interface_list()
))->setHelp('Choose which interface this rule applies to. In most cases "WAN" is specified.');

$protocols = "TCP UDP TCP/UDP ICMP ESP AH GRE IPV6 IGMP PIM OSPF";

$section->addInput(new Form_Select(
	'ipprotocol',
	'*Address Family',
	$pconfig['ipprotocol'],
	array(
		'inet' => 'IPv4',
		'inet6' => 'IPv6'
	)
))->setHelp('Select the Internet Protocol version this rule applies to.');

$section->addInput(new Form_Select(
	'proto',
	'*Protocol',
	$pconfig['proto'],
	array_combine(explode(" ", strtolower($protocols)), explode(" ", $protocols))
))->setHelp('Choose which protocol this rule should match. In most cases "TCP" is specified.');

$btnsrcadv = new Form_Button(
	'btnsrcadv',
	'Display Advanced',
	null,
	'fa-cog'
);

$btnsrcadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	'Source',
	$btnsrcadv
));

$group = new Form_Group('Source');
$group->addClass('srcadv');

$group->add(new Form_Checkbox(
	'srcnot',
	'Source not',
	'Invert match.',
	$pconfig['srcnot']
))->setWidth(2);

$group->add(new Form_Select(
	'srctype',
	null,
	srctype_selected(),
	build_srctype_list()
))->setHelp('Type');

$group->add(new Form_IpAddress(
	'src',
	null,
	is_specialnet($pconfig['src']) ? '': $pconfig['src'],
	'ALIASV4V6'
))->addMask('srcmask', $pconfig['srcmask'])->setHelp('Address/mask');

$section->add($group);

$portlist = array("" => gettext('Other'), 'any' => gettext('Any'));

foreach ($wkports as $wkport => $wkportdesc) {
	$portlist[$wkport] = $wkportdesc;
}

$group = new Form_Group('Source port range');
$group->addClass('srcportrange');

$group->add(new Form_Select(
	'srcbeginport',
	null,
	$pconfig['srcbeginport'],
	$portlist
))->setHelp('From port');

$group->add(new Form_Input(
	'srcbeginport_cust',
	null,
	'text',
	$pconfig['srcbeginport']
))->setPattern('[a-zA-Z0-9_]+')->setHelp('Custom');

$group->add(new Form_Select(
	'srcendport',
	null,
	$pconfig['srcendport'],
	$portlist
))->setHelp('To port');

$group->add(new Form_Input(
	'srcendport_cust',
	null,
	'text',
	$pconfig['srcendport']
))->setPattern('[a-zA-Z0-9_]+')->setHelp('Custom');

$group->setHelp('Specify the source port or port range for this rule. This is usually random and almost never ' .
				'equal to the destination port range (and should usually be \'any\'). The \'to\' field ' .
				'may be left empty if only filtering a single port.');

$section->add($group);

$group = new Form_Group('*Destination');

$group->add(new Form_Checkbox(
	'dstnot',
	'Destination not',
	'Invert match.',
	$pconfig['dstnot']
))->setWidth(2);

$group->add(new Form_Select(
	'dsttype',
	null,
	dsttype_selected(),
	build_dsttype_list()
))->setHelp('Type');

$group->add(new Form_IpAddress(
	'dst',
	null,
	is_specialnet($pconfig['dst']) ? '': $pconfig['dst'],
	'ALIASV4V6'
))->addMask('dstmask', $pconfig['dstmask'], 31)->setHelp('Address/mask');

$section->add($group);

$group = new Form_Group('*Destination port range');
$group->addClass('dstportrange');

$group->add(new Form_Select(
	'dstbeginport',
	null,
	$pconfig['dstbeginport'],
	$portlist
))->setHelp('From port');

$group->add(new Form_Input(
	'dstbeginport_cust',
	null,
	'text',
	$pconfig['dstbeginport']
))->setPattern('[a-zA-Z0-9_]+')->setHelp('Custom');

$group->add(new Form_Select(
	'dstendport',
	null,
	$pconfig['dstendport'],
	$portlist
))->setHelp('To port');

$group->add(new Form_Input(
	'dstendport_cust',
	null,
	'text',
	$pconfig['dstendport']
))->setPattern('[a-zA-Z0-9_]+')->setHelp('Custom');

$group->setHelp('Specify the port or port range for the destination of the packet for this mapping. ' .
				'The \'to\' field may be left empty if only mapping a single port. ');

$section->add($group);

$group = new Form_Group('*Redirect target IP');

$group->add(new Form_StaticText(
	null,
	null
))->setWidth(2);

$group->add(new Form_Select(
	'localtype',
	null,
	localtype_selected(),
	build_localtype_list()
))->setHelp('Type');

$group->add(new Form_IpAddress(
	'localip',
	null,
	$pconfig['localip'],
	'ALIASV4V6'
))->setHelp('Address');

$group->setHelp('Enter the internal IP address of the server on which to map the ports. e.g.: 192.168.1.12 for IPv4%1$s ' .
	    'In case of IPv6 addresses, in must be from the same "scope",%1$s i.e. it is not possible to redirect from ' .
	    'link-local addresses scope (fe80:*) to local scope (::1)', '<br />');

$section->add($group);

$group = new Form_Group('*Redirect target port');
$group->addClass('lclportrange');

$group->add(new Form_Select(
	'localbeginport',
	null,
	$pconfig['localbeginport'],
	array('' => 'Other') + $wkports
))->setHelp('Port');

$group->setHelp('Specify the port on the machine with the IP address entered above. In case of a port range, specify the ' .
				'beginning port of the range (the end port will be calculated automatically).%s' .
				'This is usually identical to the "From port" above.', '<br />');

$group->add(new Form_Input(
	'localbeginport_cust',
	null,
	'text',
	$pconfig['localbeginport']
))->setPattern('[a-zA-Z0-9_]+')->setHelp('Custom');

$section->add($group);

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');


$section->addInput(new Form_Checkbox(
	'nosync',
	'No XMLRPC Sync',
	'Do not automatically sync to other CARP members',
	$pconfig['nosync']
))->setHelp('This prevents the rule on Master from automatically syncing to other CARP members. ' .
			'This does NOT prevent the rule from being overwritten on Slave.');

$section->addInput(new Form_Select(
	'natreflection',
	'NAT reflection',
	$pconfig['natreflection'],
	array(
		'default' => gettext('Use system default'),
		'enable'  => gettext('Enable (NAT + Proxy)'),
		'purenat' => gettext('Enable (Pure NAT)'),
		'disable' => gettext('Disable')
	)
));

if (isset($id) && $a_nat[$id] && (!isset($_POST['dup']) || !is_numericint($_POST['dup']))) {

	$hlpstr = '';
	$rulelist = array('' => gettext('None'), 'pass' => gettext('Pass'));

	if (is_array($config['filter']['rule'])) {
		filter_rules_sort();

		foreach ($config['filter']['rule'] as $filter_id => $filter_rule) {
			if (isset($filter_rule['associated-rule-id'])) {
				$rulelist[$filter_rule['associated-rule-id']] = sprintf(gettext('Rule %s'), $filter_rule['descr']);

				if ($filter_rule['associated-rule-id'] == $pconfig['associated-rule-id']) {
					$hlpstr = '<a href="firewall_rules_edit.php?id=' . $filter_id . '">' . gettext("View the filter rule") . '</a><br />';
				}
			}
		}
	}

	if (isset($pconfig['associated-rule-id'])) {
		$rulelist['new'] = gettext('Create new associated filter rule');
	}

	$section->addInput(new Form_Select(
		'associated-rule-id',
		'Filter rule association',
		$pconfig['associated-rule-id'],
		$rulelist
	))->setHelp($hlpstr);
} else {
	$section->addInput(new Form_Select(
		'filter-rule-association',
		'Filter rule association',
		'add-associated',
		array(
			'' => 'None',
			'add-associated'  => gettext('Add associated filter rule'),
			'add-unassociated' => gettext('Add unassociated filter rule'),
			'pass' => gettext('Pass')
		)
	))->setHelp('The "pass" selection does not work properly with Multi-WAN. It will only work on an interface containing the default gateway.');
}

$form->add($section);

gen_created_updated_fields($form, $a_nat[$id]['created'], $a_nat[$id]['updated']);

if (isset($id) && $a_nat[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->addGlobal(new Form_Input(
	'after',
	null,
	'hidden',
	$after
));

print($form);
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	var portsenabled = true;
	var srcenabled = <?= ($pconfig['srcnot'] || ($pconfig['src'] != "any") || ($pconfig['srcbeginport'] != "any") || ($pconfig['srcendport'] != "any"))? 1:0 ?>;
	var iface_old = '';

	// ---------- jQuery functions, lovingly converted from the original javascript------------------------------------------
	function ext_change() {

		if (($('#srcbeginport').find(":selected").index() == 0) && portsenabled) {
			disableInput('srcbeginport_cust', false);
		} else {
			$('#srcbeginport_cust').val('');
			disableInput('srcbeginport_cust', true);
		}

		if (($('#srcendport').find(":selected").index() == 0) && portsenabled) {
			disableInput('srcendport_cust', false);
		} else {
			$('#srcendport_cust').val('');
			disableInput('srcendport_cust', true);
		}

		if (($('#dstbeginport').find(":selected").index() == 0) && portsenabled) {
			disableInput('dstbeginport_cust', false);
			disableInput('localbeginport', false);
		} else if (($('#dstbeginport').find(":selected").index() == 1) && portsenabled) {
			$('#dstbeginport_cust').val('');
			disableInput('dstbeginport_cust', true);
			disableInput('localbeginport', true);
			disableInput('localbeginport_cust', true);
		} else {
			$('#dstbeginport_cust').val('');
			disableInput('dstbeginport_cust', true);
			disableInput('localbeginport', false);
			disableInput('localbeginport_cust', false);
		}

		if (($('#dstendport').find(":selected").index() == 0) && portsenabled) {
			disableInput('dstendport_cust', false);
		} else {
			$('#dstendport_cust').val('');
			disableInput('dstendport_cust', true);
		}

		if (($('#localbeginport').find(":selected").index() == 0) &&
		    ($('#dstbeginport').find(":selected").index() != 1) && portsenabled) {
			disableInput('localbeginport_cust', false);
		} else {
			$('#localbeginport_cust').val('');
			disableInput('localbeginport_cust', true);
		}

		if (!portsenabled) {
			disableInput('srcbeginport', true);
			disableInput('srcendport', true);
			disableInput('dstbeginport', true);
			disableInput('dstendport', true);
			disableInput('localbeginport_cust', true);
		} else {
			disableInput('srcbeginport', false);
			disableInput('srcendport', false);
//			disableInput('localbeginport_cust', false);
			disableInput('dstbeginport', false);
			disableInput('dstendport', false);
		}
	}

	function nordr_change() {
		if ($('#nordr').prop('checked')) {
			hideInput('localip', true);
			hideClass('lclportrange', true);
			hideInput('associated-rule-id', true);
		} else {
			hideInput('localip', false);
			hideClass('lclportrange', !portsenabled);
			hideInput('associated-rule-id', false);
		}
	}

	var customarray	 = <?= json_encode(get_alias_list(array("port", "url_ports", "urltable_ports"))) ?>;

	function check_for_aliases() {
		//	if External port range is an alias, then disallow
		//	entry of Local port
		for (i = 0; i < customarray.length; i++) {
			if (($('#dstbeginport_cust').val() == customarray[i]) || ($('#dstendport_cust').val() == customarray[i])) {
				$('#dstendport_cust').val(customarray[i]);
				$('#localbeginport_cust').val(customarray[i]);
				disableInput('dstendport_cust', false);
				disableInput('localbeginport', false);
				disableInput('localbeginport_cust', false);
			}
		}
	}

	function proto_change() {
		if ($('#proto').find(":selected").index() >= 0 && $('#proto').find(":selected").index() <= 2) {
			portsenabled = true;
		} else {
			portsenabled = false;
		}

		if (portsenabled) {
			hideClass('srcportrange', !srcenabled);
			hideClass('dstportrange', false);
			hideClass('lclportrange', false);
		} else {
			hideClass('srcportrange', true);
			hideClass('dstportrange', true);
			hideClass('lclportrange', true);
			$('#dstbeginport_cust').val('');
			$('#dstendport_cust').val('');
			$('#localbeginport_cust').val('');
		}
	}

	function typesel_change() {
		switch ($('#srctype').find(":selected").index()) {
			case 1: // single
				disableInput('src', false);
				$('#srcmask').val('');
				disableInput('srcmask', true);
				break;
			case 2: // network
				disableInput('src', false);
				disableInput('srcmask', false);
				break;
			default:
				$('#src').val('');
				disableInput('src', true);
				$('#srcmask').val('');
				disableInput('srcmask', true);
				break;
		}

		switch ($('#dsttype').find(":selected").index()) {
			case 1: // single
				disableInput('dst', false);
				$('#dstmask').val('');
				disableInput('dstmask', true);;
				break;
			case 2: // network /
				disableInput('dst', false);
				disableInput('dstmask', false);
				break;
			default:
				$('#dst').val('');
				disableInput('dst', true);
				$('#dstmask').val('');
				disableInput('dstmask', true);
				break;
		}

		switch ($('#localtype').find(":selected").index()) {
			case 0: // single
				disableInput('localip', false);
				break;
			default:
				$('#localip').val('');
				disableInput('localip', true);
				break;
		}
	}

	function src_rep_change() {
		$('#srcendport').prop("selectedIndex", $('#srcbeginport').find(":selected").index());
	}

	function dst_rep_change() {
		$('#dstendport').prop("selectedIndex", $('#dstbeginport').find(":selected").index());
	}

	function dst_change(iface, old_iface, old_dst) {
		if ((old_dst == "") || (old_iface.concat("ip") == old_dst)) {
			$('#dsttype').val(iface + "ip");
		}
	}

	function hideSource(hide) {
		var text;
		hideClass('srcadv', hide);
		hideClass('srcportrange', hide || !portsenabled);
		if (hide) {
			text = "<?=gettext('Display Advanced');?>";
		} else {
			text = "<?=gettext('Hide Advanced');?>";
		}
		$('#btnsrcadv').html('<i class="fa fa-cog"></i> ' + text);
	}

	// ---------- "onclick" functions ---------------------------------------------------------------------------------
	$('#srcbeginport').on('change', function() {
		src_rep_change();
		ext_change();
	});

	$('#srcendport').on('change', function() {
		ext_change();
	});

	$('#dstbeginport').on('change', function() {
		dst_rep_change();
		ext_change();
	});

	$('#dstendport').on('change', function() {
		ext_change();
	});

	$('#localbeginport').on('change', function() {
		ext_change();
		check_for_aliases();
	});

	$('#proto').on('change', function() {
		proto_change();
		check_for_aliases()
	});

	$('#nordr').click(function () {
		nordr_change();
	});

	$('#interface').click(function () {
		dst_change($('#interface').val(), iface_old, $('#dsttype').val());
		iface_old = $('#interface').val();
		typesel_change();
	});

	$('#srctype').change(function () {
		typesel_change();
	});

	$('#dsttype').change(function () {
		typesel_change();
	});

	$('#localtype').change(function () {
		typesel_change();
	});

	$("#btnsrcadv").click(function() {
		srcenabled = !srcenabled;
		hideSource(!srcenabled);
	});
	// ---------- On initial page load --------------------------------------------------------------------------------

	hideSource(!srcenabled);
	ext_change();
<?php
if (!$_POST) {
?>
	dst_change($('#interface').val(),'<?=htmlspecialchars(addslashes($pconfig['interface']))?>','<?=htmlspecialchars($pconfig['dst'])?>');
<?php
}
?>
	iface_old = $('#interface').val();
	typesel_change();
	proto_change();
	nordr_change();

	// --------- Autocomplete -----------------------------------------------------------------------------------------
	var addressarray = <?= json_encode(get_alias_list(array("host", "network", "urltable"))) ?>;
	var customarray = <?= json_encode(get_alias_list(array("port", "url_ports", "urltable_ports"))) ?>;

	$('#localip, #src, #dst').autocomplete({
		source: addressarray
	});

	$('#dstbeginport_cust, #dstendport_cust, #srcbeginport_cust, #srcendport_cust, #localbeginport_cust').autocomplete({
		source: customarray
	});

});
//]]>
</script>

<?php include("foot.inc");
