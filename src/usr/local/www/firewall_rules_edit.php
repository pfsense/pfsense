<?php
/*
 * firewall_rules_edit.php
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
##|*IDENT=page-firewall-rules-edit
##|*NAME=Firewall: Rules: Edit
##|*DESCR=Allow access to the 'Firewall: Rules: Edit' page.
##|*MATCH=firewall_rules_edit.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("ipsec.inc");
require_once("filter.inc");
require_once("shaper.inc");

/* build icmptypes valid for IPv4, IPv6 and IPv<any> */
$icmptypes4 = array('any' => gettext('any'));
$icmptypes6 = array('any' => gettext('any'));
$icmptypes46 = array('any' => gettext('any'));

// ICMP descriptions may be translated, so require escaping to handle single quotes (in particular)
foreach ($icmptypes as $k => $v) {
	$description = addslashes($v['descrip']);

	if ($v['valid4']) {
		$icmptypes4[$k] = $description;
		if ($v['valid6']) {
			$icmptypes6[$k] = $description;
			$icmptypes46[$k] = $description;
		}
	} else {
		$icmptypes6[$k] = $description;
	}
}

$icmplookup = array(
	'inet' => array('name' => 'IPv4', 'icmptypes' => $icmptypes4, 'helpmsg' => gettext('For ICMP rules on IPv4, one or more of these ICMP subtypes may be specified.')),
	'inet6' => array('name' => 'IPv6', 'icmptypes' => $icmptypes6, 'helpmsg' => gettext('For ICMP rules on IPv6, one or more of these ICMP subtypes may be specified.')),
	'inet46' => array('name' => 'IPv4+6', 'icmptypes' => $icmptypes46, 'helpmsg' => sprintf(gettext('For ICMP rules on IPv4+IPv6, one or more of these ICMP subtypes may be specified. (Other ICMP subtypes are only valid under IPv4 %1$sor%2$s IPv6, not both)'), '<i>', '</i>'))
);

$statetype_values = array(
	'keep state' => gettext('Keep'),
	'sloppy state' => gettext('Sloppy'),
	'synproxy state' => gettext('Synproxy'),
	'none' => gettext('None'),
);

$vlanprio = array(
	"" => "none",
	"bk" => "Background (BK, 0)",
	"be" => "Best Effort (BE, 1)",
	"ee" => "Excellent Effort (EE, 2)",
	"ca" => "Critical Applications (CA, 3)",
	"vi" => "Video (VI, 4)",
	"vo" => "Voice (VO, 5)",
	"ic" => "Internetwork Control (IC, 6)",
	"nc" => "Network Control (NC, 7)");

if (isset($_POST['referer'])) {
	$referer = $_POST['referer'];
} else {
	$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/firewall_rules.php');
}

function is_posnumericint($arg) {
	// Integer > 0? (Note that to be safe we do not allow any leading zero - "01", "007")
	return (is_numericint($arg) && $arg[0] != '0');
}

function is_aoadv_used($rule_config) {
	// Note that the user could set "tag" or "tagged" to the string "0", which is valid but empty().
	// And if the user enters "0" in other fields, we want to present an error message, and keep the Advanced Options section open.
	if (($rule_config['os'] != "") ||
	    ($rule_config['dscp'] != "") ||
	    (isset($rule_config['allowopts'])) ||
	    (isset($rule_config['disablereplyto'])) ||
	    ($rule_config['tag'] != "") ||
	    ($rule_config['tagged'] != "") ||
	    ($rule_config['max'] != "") ||
	    ($rule_config['max-src-nodes'] != "") ||
	    ($rule_config['max-src-conn'] != "") ||
	    ($rule_config['max-src-states'] != "") ||
	    ($rule_config['max-src-conn-rate'] != "") ||
	    ($rule_config['max-src-conn-rates'] != "") ||
	    ($rule_config['statetimeout'] != "") ||
	    ($rule_config['tcpflags1'] != "") ||
	    ($rule_config['tcpflags2'] != "") ||
	    ($rule_config['tcpflags_any']) ||
	    ($rule_config['nopfsync']) ||
	    (($rule_config['statetype'] != "") && ($rule_config['statetype'] != "keep state")) ||
	    ($rule_config['nosync']) ||
	    ($rule_config['vlanprio'] != "") ||
	    ($rule_config['vlanprioset'] != "") ||
	    ($rule_config['sched'] != "") ||
	    ($rule_config['gateway'] != "") ||
	    ($rule_config['dnpipe'] != "") ||
	    ($rule_config['pdnpipe'] != "") ||
	    ($rule_config['ackqueue'] != "") ||
	    ($rule_config['defaultqueue'] != "")) {
		return true;
	}

	return false;
}

$ostypes = array();
exec('/sbin/pfctl -s osfp | /usr/bin/tr \'\t\' \' \'', $ostypes);

if (count($ostypes) > 2) {
	// Remove header rows from pfctl output
	array_shift($ostypes);
	array_shift($ostypes);
} else {
	// Fall back to a default list
	$ostypes = array(
		"AIX",
		"Linux",
		"FreeBSD",
		"NetBSD",
		"OpenBSD",
		"Solaris",
		"MacOS",
		"Windows",
		"Novell",
		"NMAP"
	);
}

$specialsrcdst = explode(" ", "any (self) pptp pppoe l2tp openvpn");
$ifdisp = get_configured_interface_with_descr();

foreach ($ifdisp as $kif => $kdescr) {
	$specialsrcdst[] = "{$kif}";
	$specialsrcdst[] = "{$kif}ip";
}

init_config_arr(array('filter', 'rule'));
filter_rules_sort();
$a_filter = &$config['filter']['rule'];

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

if (isset($id) && $a_filter[$id]) {
	$pconfig['interface'] = $a_filter[$id]['interface'];

	if (isset($a_filter[$id]['id'])) {
		$pconfig['ruleid'] = $a_filter[$id]['id'];
	}

	if (isset($a_filter[$id]['created']) && is_array($a_filter[$id]['created'])) {
		$pconfig['created'] = $a_filter[$id]['created'];
	}

	if (isset($a_filter[$id]['updated']) && is_array($a_filter[$id]['updated'])) {
		$pconfig['updated'] = $a_filter[$id]['updated'];
	}

	if (!isset($a_filter[$id]['type'])) {
		$pconfig['type'] = "pass";
	} else {
		$pconfig['type'] = $a_filter[$id]['type'];
	}

	if (isset($a_filter[$id]['floating']) || $if == "FloatingRules") {
		$pconfig['floating'] = $a_filter[$id]['floating'];
		if (isset($a_filter[$id]['interface']) && $a_filter[$id]['interface'] <> "") {
			$pconfig['interface'] = explode(",", $a_filter[$id]['interface']);
		}
	}

	if (isset($a_filter['floating'])) {
		$pconfig['floating'] = "yes";
	}

	if (isset($a_filter[$id]['direction'])) {
		$pconfig['direction'] = $a_filter[$id]['direction'];
	}

	if (isset($a_filter[$id]['ipprotocol'])) {
		$pconfig['ipprotocol'] = $a_filter[$id]['ipprotocol'];
	} else {
		$pconfig['ipprotocol'] = 'inet';
	}

	if (isset($a_filter[$id]['protocol'])) {
		$pconfig['proto'] = $a_filter[$id]['protocol'];
	} else {
		$pconfig['proto'] = "any";
	}

	if ($a_filter[$id]['protocol'] == "icmp") {
		$pconfig['icmptype'] = $a_filter[$id]['icmptype'];
	}

	address_to_pconfig($a_filter[$id]['source'], $pconfig['src'],
		$pconfig['srcmask'], $pconfig['srcnot'],
		$pconfig['srcbeginport'], $pconfig['srcendport']);

	if ($a_filter[$id]['os'] != "") {
		$pconfig['os'] = $a_filter[$id]['os'];
	}

	address_to_pconfig($a_filter[$id]['destination'], $pconfig['dst'],
		$pconfig['dstmask'], $pconfig['dstnot'],
		$pconfig['dstbeginport'], $pconfig['dstendport']);

	if ($a_filter[$id]['dscp'] <> "") {
		$pconfig['dscp'] = $a_filter[$id]['dscp'];
	}

	$pconfig['disabled'] = isset($a_filter[$id]['disabled']);
	$pconfig['log'] = isset($a_filter[$id]['log']);
	$pconfig['descr'] = $a_filter[$id]['descr'];

	if (isset($a_filter[$id]['tcpflags_any'])) {
		$pconfig['tcpflags_any'] = true;
	} else {
		if (isset($a_filter[$id]['tcpflags1']) && $a_filter[$id]['tcpflags1'] <> "") {
			$pconfig['tcpflags1'] = $a_filter[$id]['tcpflags1'];
		}
		if (isset($a_filter[$id]['tcpflags2']) && $a_filter[$id]['tcpflags2'] <> "") {
			$pconfig['tcpflags2'] = $a_filter[$id]['tcpflags2'];
		}
	}

	if (isset($a_filter[$id]['tag']) && $a_filter[$id]['tag'] <> "") {
		$pconfig['tag'] = $a_filter[$id]['tag'];
	}
	if (isset($a_filter[$id]['tagged']) && $a_filter[$id]['tagged'] <> "") {
		$pconfig['tagged'] = $a_filter[$id]['tagged'];
	}
	if (isset($a_filter[$id]['quick']) && $a_filter[$id]['quick']) {
		$pconfig['quick'] = $a_filter[$id]['quick'];
	}
	if (isset($a_filter[$id]['allowopts'])) {
		$pconfig['allowopts'] = true;
	}
	if (isset($a_filter[$id]['disablereplyto'])) {
		$pconfig['disablereplyto'] = true;
	}

	/* advanced */
	$pconfig['max'] = $a_filter[$id]['max'];
	$pconfig['max-src-nodes'] = $a_filter[$id]['max-src-nodes'];
	$pconfig['max-src-conn'] = $a_filter[$id]['max-src-conn'];
	$pconfig['max-src-states'] = $a_filter[$id]['max-src-states'];
	$pconfig['statetype'] = $a_filter[$id]['statetype'];
	$pconfig['statetimeout'] = $a_filter[$id]['statetimeout'];
	$pconfig['nopfsync'] = isset($a_filter[$id]['nopfsync']);

	/* advanced - nosync */
	$pconfig['nosync'] = isset($a_filter[$id]['nosync']);

	/* advanced - new connection per second banning*/
	$pconfig['max-src-conn-rate'] = $a_filter[$id]['max-src-conn-rate'];
	$pconfig['max-src-conn-rates'] = $a_filter[$id]['max-src-conn-rates'];

	/* Multi-WAN next-hop support */
	$pconfig['gateway'] = $a_filter[$id]['gateway'];

	/* Shaper support */
	$pconfig['defaultqueue'] = (($a_filter[$id]['ackqueue'] == "none") ? '' : $a_filter[$id]['defaultqueue']);
	$pconfig['ackqueue'] = (($a_filter[$id]['ackqueue'] == "none") ? '' : $a_filter[$id]['ackqueue']);
	$pconfig['dnpipe'] = (($a_filter[$id]['dnpipe'] == "none") ? '' : $a_filter[$id]['dnpipe']);
	$pconfig['pdnpipe'] = (($a_filter[$id]['pdnpipe'] == "none") ? '' : $a_filter[$id]['pdnpipe']);

	//schedule support
	$pconfig['sched'] = (($a_filter[$id]['sched'] == "none") ? '' : $a_filter[$id]['sched']);
	$pconfig['vlanprio'] = (($a_filter[$id]['vlanprio'] == "none") ? '' : $a_filter[$id]['vlanprio']);
	$pconfig['vlanprioset'] = (($a_filter[$id]['vlanprioset'] == "none") ? '' : $a_filter[$id]['vlanprioset']);
	if (!isset($_REQUEST['dup']) || !is_numericint($_REQUEST['dup'])) {
		$pconfig['associated-rule-id'] = $a_filter[$id]['associated-rule-id'];
	}

	$pconfig['tracker'] = $a_filter[$id]['tracker'];

} else {
	/* defaults */
	if ($_REQUEST['if']) {
		$pconfig['interface'] = $_REQUEST['if'];
	}
	$pconfig['ipprotocol'] = "inet"; // other things depend on this, set a sensible default
	$pconfig['type'] = "pass";
	$pconfig['proto'] = "tcp"; // for new blank rules, default=tcp, also ensures ports fields are visible
	$pconfig['src'] = "any";
	$pconfig['dst'] = "any";
}
/* Allow the FloatingRules to work */
$if = $pconfig['interface'];

if (isset($_REQUEST['dup']) && is_numericint($_REQUEST['dup'])) {
	unset($id);
}

read_altq_config(); /* XXX: */
$qlist =& get_unique_queue_list();

$list = array('' => 'none');
if (!is_array($qlist)) {
	$qlist = array();
}

foreach ($qlist as $q => $qkey) {
	if (isset($ifdisp[$q])) {
		$list[$q] = $ifdisp[$q];
	} else {
		$list[$q] = $q;
	}
}

read_dummynet_config(); /* XXX: */
$dnqlist =& get_unique_dnqueue_list();
$a_gatewaygroups = return_gateway_groups_array();

if ($_POST['save']) {

	unset($input_errors);

	if (!array_key_exists($_POST['ipprotocol'], $icmplookup)) {
		$input_errors[] = gettext("The IP protocol is not recognized.");
		unset($_POST['ipprotocol']);
	}

	// add validation + input error for $_POST['interface']

	$valid = ($_POST['interface'] == "FloatingRules" || isset($_POST['floating'])) ? ['pass','block','reject', 'match'] : ['pass','block','reject'];
	if (!(is_string($_POST['type'])  && in_array($_POST['type'], $valid))) {
		$input_errors[] = gettext("A valid rule type is not selected.");
		unset($_POST['type']);
	}

	if (isset($_POST['tracker']) && !is_numericint($_POST['tracker'])) {
		unset($_POST['tracker']);	// silently unset hidden input if invalid
	}

	if (isset($a_filter[$id]['associated-rule-id'])) {
		$_POST['proto'] = $pconfig['proto'];
		if ($pconfig['proto'] == "icmp") {
			$_POST['icmptype'] = $pconfig['icmptype'];
		}
	}

	if (isset($_POST['ipprotocol']) && $_POST['gateway'] <> '') {
		if (is_array($config['gateways']['gateway_group'])) {
			foreach ($config['gateways']['gateway_group'] as $gw_group) {
				if ($gw_group['name'] == $_POST['gateway'] && $_POST['ipprotocol'] != $a_gatewaygroups[$_POST['gateway']]['ipprotocol']) {
					if ($_POST['ipprotocol'] == "inet46") {
						$input_errors[] = gettext("Gateways can not be assigned in a rule that applies to both IPv4 and IPv6.");
					} elseif ($_POST['ipprotocol'] == "inet6") {
						$input_errors[] = gettext("An IPv4 gateway group can not be assigned in IPv6 rules.");
					} elseif ($_POST['ipprotocol'] == "inet") {
						$input_errors[] = gettext("An IPv6 gateway group can not be assigned in IPv4 rules.");
					}
				}
			}
		}
		if ($iptype = is_ipaddr(lookup_gateway_ip_by_name($_POST['gateway']))) {
			// this also implies that  $_POST['gateway'] was set and not empty
			if ($_POST['ipprotocol'] == "inet46") {
				$input_errors[] = gettext("Gateways can not be assigned in a rule that applies to both IPv4 and IPv6.");
			}
			if (($_POST['ipprotocol'] == "inet6") && ($iptype != 6)) {
				$input_errors[] = gettext("An IPv4 gateway can not be assigned in IPv6 rules.");
			}
			if (($_POST['ipprotocol'] == "inet") && ($iptype != 4)) {
				$input_errors[] = gettext("An IPv6 gateway can not be assigned in IPv4 rules.");
			}
		}
	}

	if (($_POST['proto'] != "tcp") && ($_POST['proto'] != "udp") && ($_POST['proto'] != "tcp/udp")) {
		$_POST['srcbeginport'] = 0;
		$_POST['srcendport'] = 0;
		$_POST['dstbeginport'] = 0;
		$_POST['dstendport'] = 0;
	} else {
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
			$_POST['dstbeginport'] = 0;
			$_POST['dstendport'] = 0;
		} else {
			if (!$_POST['dstendport']) {
				$_POST['dstendport'] = $_POST['dstbeginport'];
			}
		}
		if ($_POST['dstendport'] == "any") {
			$_POST['dstendport'] = $_POST['dstbeginport'];
		}
	}

	if (is_specialnet($_POST['srctype'])) {
		$_POST['src'] = $_POST['srctype'];
		$_POST['srcmask'] = 0;
	} else if ($_POST['srctype'] == "single") {
		if (is_ipaddrv6($_POST['src'])) {
			$_POST['srcmask'] = 128;
		} else {
			$_POST['srcmask'] = 32;
		}
	}
	if (is_specialnet($_POST['dsttype'])) {
		$_POST['dst'] = $_POST['dsttype'];
		$_POST['dstmask'] = 0;
	} else if ($_POST['dsttype'] == "single") {
		if (is_ipaddrv6($_POST['dst'])) {
			$_POST['dstmask'] = 128;
		} else {
			$_POST['dstmask'] = 32;
		}
	}

	$pconfig = $_POST;

	if (!isset($pconfig['ipprotocol'])) {
		// other things depend on this, so ensure a valid value if none provided
		$pconfig['ipprotocol'] = "inet";
	}

	if (($_POST['proto'] == "icmp") && count($_POST['icmptype'])) {
		$pconfig['icmptype'] = implode(',', $_POST['icmptype']);
	} else {
		unset($pconfig['icmptype']);
	}

	/* input validation */
	$reqdfields = explode(" ", "type proto");
	if (isset($a_filter[$id]['associated-rule-id']) === false) {
		$reqdfields[] = "src";
		$reqdfields[] = "dst";
	}
	$reqdfieldsn = array(gettext("Type"), gettext("Protocol"));
	if (isset($a_filter[$id]['associated-rule-id']) === false) {
		$reqdfieldsn[] = gettext("Source");
		$reqdfieldsn[] = gettext("Destination");
	}

	if ($_POST['statetype'] == "synproxy state") {
		if ($_POST['proto'] != "tcp") {
			$input_errors[] = sprintf(gettext("%s is only valid with protocol TCP."), $_POST['statetype']);
		}
		if ($_POST['gateway'] != "") {
			$input_errors[] = sprintf(gettext("%s is only valid if the gateway is set to 'default'."), $_POST['statetype']);
		}
	}

	if (isset($a_filter[$id]['associated-rule-id']) === false &&
	    (!(is_specialnet($_POST['srctype']) || ($_POST['srctype'] == "single")))) {
		$reqdfields[] = "srcmask";
		$reqdfieldsn[] = gettext("Source bit count");
	}
	if (isset($a_filter[$id]['associated-rule-id']) === false &&
	    (!(is_specialnet($_POST['dsttype']) || ($_POST['dsttype'] == "single")))) {
		$reqdfields[] = "dstmask";
		$reqdfieldsn[] = gettext("Destination bit count");
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
	if (!$_POST['srcbeginport_cust'] && $_POST['srcendport_cust']) {
		if (is_alias($_POST['srcendport_cust'])) {
			$input_errors[] = 'If a port alias is put in the Source port range to: field the same port alias must be put in the from: field';
		}
	}
	if ($_POST['srcbeginport_cust'] && $_POST['srcendport_cust']) {
		if (is_alias($_POST['srcbeginport_cust']) && is_alias($_POST['srcendport_cust']) && $_POST['srcbeginport_cust'] != $_POST['srcendport_cust']) {
			$input_errors[] = 'The same port alias must be used in Source port range from: and to: fields';
		}
		if ((is_alias($_POST['srcbeginport_cust']) && (!is_alias($_POST['srcendport_cust']) && $_POST['srcendport_cust'] != '')) ||
		    ((!is_alias($_POST['srcbeginport_cust']) && $_POST['srcbeginport_cust'] != '') && is_alias($_POST['srcendport_cust']))) {
			$input_errors[] = 'Numbers and port aliases cannot be specified at the same time in Source port range from: and to: field';
		}
	}
	if (!$_POST['dstbeginport_cust'] && $_POST['dstendport_cust']) {
		if (is_alias($_POST['dstendport_cust'])) {
			$input_errors[] = 'If a port alias is put in the Destination port range to: field the same port alias must be put in the from: field';
		}
	}
	if ($_POST['dstbeginport_cust'] && $_POST['dstendport_cust']) {
		if (is_alias($_POST['dstbeginport_cust']) && is_alias($_POST['dstendport_cust']) && $_POST['dstbeginport_cust'] != $_POST['dstendport_cust']) {
			$input_errors[] = 'The same port alias must be used in Destination port range from: and to: fields';
		}
		if ((is_alias($_POST['dstbeginport_cust']) && (!is_alias($_POST['dstendport_cust']) && $_POST['dstendport_cust'] != '')) ||
		    ((!is_alias($_POST['dstbeginport_cust']) && $_POST['dstbeginport_cust'] != '') && is_alias($_POST['dstendport_cust']))) {
			$input_errors[] = 'Numbers and port aliases cannot be specified at the same time in Destination port range from: and to: field';
		}
	}

	if ($_POST['src']) {
		$_POST['src'] = addrtolower(trim($_POST['src']));
	}
	if ($_POST['dst']) {
		$_POST['dst'] = addrtolower(trim($_POST['dst']));
	}

	/* if user enters an alias and selects "network" then disallow. */
	if ($_POST['srctype'] == "network") {
		if (is_alias($_POST['src'])) {
			$input_errors[] = gettext("Alias entries must be a single host or alias.");
		}
	}
	if ($_POST['dsttype'] == "network") {
		if (is_alias($_POST['dst'])) {
			$input_errors[] = gettext("Alias entries must be a single host or alias.");
		}
	}

	if (!is_specialnet($_POST['srctype'])) {
		if (($_POST['src'] && !is_ipaddroralias($_POST['src']))) {
			$input_errors[] = sprintf(gettext("%s is not a valid source IP address or alias."), $_POST['src']);
		}
		if (($_POST['srcmask'] && !is_numericint($_POST['srcmask']))) {
			$input_errors[] = gettext("A valid source bit count must be specified.");
		}
	}
	if (!is_specialnet($_POST['dsttype'])) {
		if (($_POST['dst'] && !is_ipaddroralias($_POST['dst']))) {
			$input_errors[] = sprintf(gettext("%s is not a valid destination IP address or alias."), $_POST['dst']);
		}
		if (($_POST['dstmask'] && !is_numericint($_POST['dstmask']))) {
			$input_errors[] = gettext("A valid destination bit count must be specified.");
		}
	}
	if ((is_ipaddr($_POST['src']) && is_ipaddr($_POST['dst']))) {
		if (!validate_address_family($_POST['src'], $_POST['dst'])) {
			$input_errors[] = gettext("The source and destination IP addresses must have the same family (IPv4 / IPv6).");
		}
	}
	if ((is_ipaddrv6($_POST['src']) || is_ipaddrv6($_POST['dst'])) && ($_POST['ipprotocol'] == "inet")) {
		$input_errors[] = gettext("IPv6 addresses cannot be used in IPv4 rules (except within an alias).");
	}
	if ((is_ipaddrv4($_POST['src']) || is_ipaddrv4($_POST['dst'])) && ($_POST['ipprotocol'] == "inet6")) {
		$input_errors[] = gettext("IPv4 addresses can not be used in IPv6 rules (except within an alias).");
	}

	if ((is_ipaddr($_POST['src']) || is_ipaddr($_POST['dst'])) && ($_POST['ipprotocol'] == "inet46")) {
		$input_errors[] = gettext("IPv4 and IPv6 addresses can not be used in rules that apply to both IPv4 and IPv6 (except within an alias).");
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
	if ($_POST['os']) {
		if ($_POST['proto'] != "tcp") {
			$input_errors[] = gettext("OS detection is only valid with protocol TCP.");
		}
		if (!in_array($_POST['os'], $ostypes)) {
			$input_errors[] = gettext("Invalid OS detection selection. Please select a valid OS.");
		}
	}

	if ($_POST['proto'] == "icmp") {
		$t = $_POST['icmptype'];
		if (isset($t) && !is_array($t)) {
			// shouldn't happen but avoids making assumptions for data-sanitising
			$input_errors[] = gettext("ICMP types expected to be a list if present, but is not.");
		} elseif (!isset($t) || count($t) == 0) {
			// not specified or none selected
			unset($_POST['icmptype']);
		} elseif (isset($_POST['ipprotocol'])) {
			// check data; if ipprotocol invalid then safe to skip this (we can't determine valid icmptypes, but input error already raised for ipprotocol)
			$bad_types = array();
			if ((count($t) == 1 && !isset($t['any'])) || count($t) > 1) {
				// Only need to check valid if just one selected != "any", or >1 selected
				$p = $_POST['ipprotocol'];
				foreach ($t as $type) {
					if (($p == 'inet' && !array_key_exists($type, $icmptypes4)) ||
					    ($p == 'inet6' && !array_key_exists($type, $icmptypes6)) ||
					    ($p == 'inet46' && !array_key_exists($type, $icmptypes46))) {
							$bad_types[] = $type;
					}
				}
			}
			if (count($bad_types) > 0) {
				$input_errors[] = sprintf(gettext("Invalid ICMP subtype: %s can not be used with %s."), implode(';', $bad_types),  $t['name']);
			}
		}
	} else {
		unset($_POST['icmptype']); // field not applicable, might hold junk from old hidden selections. Unset it.
	}

	if ($_POST['ackqueue'] != "") {
		if ($_POST['defaultqueue'] == "") {
			$input_errors[] = gettext("A queue must be selected when an acknowledge queue is also selected.");
		} else if ($_POST['ackqueue'] == $_POST['defaultqueue']) {
			$input_errors[] = gettext("Acknowledge queue and Queue cannot be the same.");
		}
	}
	if (isset($_POST['floating']) && $_POST['pdnpipe'] != "" && (empty($_POST['direction']) || $_POST['direction'] == "any")) {
		$input_errors[] = gettext("Limiters can not be used in Floating rules without choosing a direction.");
	}
	if (isset($_POST['floating']) && $_POST['gateway'] != "" && (empty($_POST['direction']) || $_POST['direction'] == "any")) {
		$input_errors[] = gettext("Gateways can not be used in Floating rules without choosing a direction.");
	}
	if ($_POST['pdnpipe'] && $_POST['pdnpipe'] != "") {
		if ($_POST['dnpipe'] == "") {
			$input_errors[] = gettext("A queue must be selected for the In direction before selecting one for Out too.");
		} else if ($_POST['pdnpipe'] == $_POST['dnpipe']) {
			$input_errors[] = gettext("In and Out Queue cannot be the same.");
		} else if ($dnqlist[$_POST['pdnpipe']][0] == "?" && $dnqlist[$_POST['dnpipe']][0] <> "?") {
			$input_errors[] = gettext("A queue and a virtual interface cannot be selected for IN and Out. Both must be from the same type.");
		} else if ($dnqlist[$_POST['dnpipe']][0] == "?" && $dnqlist[$_POST['pdnpipe']][0] <> "?") {
			$input_errors[] = gettext("A queue and a virtual interface cannot be selected for IN and Out. Both must be from the same type.");
		}
		if ($_POST['direction'] == "out" && empty($_POST['gateway'])) {
			$input_errors[] = gettext("Please select a gateway, normally the interface selected gateway, so the limiters work correctly");
		}
	}
	if (!empty($_POST['ruleid']) && !is_numericint($_POST['ruleid'])) {
		$input_errors[] = gettext('ID must be an integer');
	}

	if (!in_array($_POST['proto'], array("tcp", "tcp/udp"))) {
		if (!empty($_POST['max-src-conn'])) {
			$input_errors[] = gettext("The maximum number of established connections per host (advanced option) can only be specified for TCP protocol.");
		}
		if (!empty($_POST['max-src-conn-rate']) || !empty($_POST['max-src-conn-rates'])) {
			$input_errors[] = gettext("The maximum new connections per host / per second(s) (advanced option) can only be specified for TCP protocol.");
		}
		if (!empty($_POST['statetimeout'])) {
			$input_errors[] = gettext("The state timeout (advanced option) can only be specified for TCP protocol.");
		}
	}

	if ($_POST['type'] <> "pass") {
		if (!empty($_POST['max'])) {
			$input_errors[] = gettext("The maximum state entries (advanced option) can only be specified for Pass type rules.");
		}
		if (!empty($_POST['max-src-nodes'])) {
			$input_errors[] = gettext("The maximum number of unique source hosts (advanced option) can only be specified for Pass type rules.");
		}
		if (!empty($_POST['max-src-conn'])) {
			$input_errors[] = gettext("The maximum number of established connections per host (advanced option) can only be specified for Pass type rules.");
		}
		if (!empty($_POST['max-src-states'])) {
			$input_errors[] = gettext("The maximum state entries per host (advanced option) can only be specified for Pass type rules.");
		}
		if (!empty($_POST['max-src-conn-rate']) || !empty($_POST['max-src-conn-rates'])) {
			$input_errors[] = gettext("The maximum new connections per host / per second(s) (advanced option) can only be specified for Pass type rules.");
		}
		if (!empty($_POST['statetimeout'])) {
			$input_errors[] = gettext("The state timeout (advanced option) can only be specified for Pass type rules.");
		}
	}

	if ($_POST['statetype'] == "none") {
		if (!empty($_POST['max'])) {
			$input_errors[] = gettext("The maximum state entries (advanced option) cannot be specified if statetype is none.");
		}
		if (!empty($_POST['max-src-nodes'])) {
			$input_errors[] = gettext("The maximum number of unique source hosts (advanced option) cannot be specified if statetype is none.");
		}
		if (!empty($_POST['max-src-conn'])) {
			$input_errors[] = gettext("The maximum number of established connections per host (advanced option) cannot be specified if statetype is none.");
		}
		if (!empty($_POST['max-src-states'])) {
			$input_errors[] = gettext("The maximum state entries per host (advanced option) cannot be specified if statetype is none.");
		}
		if (!empty($_POST['max-src-conn-rate']) || !empty($_POST['max-src-conn-rates'])) {
			$input_errors[] = gettext("The maximum new connections per host / per second(s) (advanced option) cannot be specified if statetype is none.");
		}
		if (!empty($_POST['statetimeout'])) {
			$input_errors[] = gettext("The state timeout (advanced option) cannot be specified if statetype is none.");
		}
	}

	if (($_POST['max'] != "") && !is_posnumericint($_POST['max'])) {
		$input_errors[] = gettext("Maximum state entries (advanced option) must be a positive integer");
	}

	if (($_POST['max-src-nodes'] != "") && !is_posnumericint($_POST['max-src-nodes'])) {
		$input_errors[] = gettext("Maximum number of unique source hosts (advanced option) must be a positive integer");
	}

	if (($_POST['max-src-conn'] != "") && !is_posnumericint($_POST['max-src-conn'])) {
		$input_errors[] = gettext("Maximum number of established connections per host (advanced option) must be a positive integer");
	}

	if (($_POST['max-src-states'] != "") && !is_posnumericint($_POST['max-src-states'])) {
		$input_errors[] = gettext("Maximum state entries per host (advanced option) must be a positive integer");
	}

	if (($_POST['max-src-conn-rate'] != "") && !is_posnumericint($_POST['max-src-conn-rate'])) {
		$input_errors[] = gettext("Maximum new connections per host / per second(s) (advanced option) must be a positive integer");
	}

	if (($_POST['statetimeout'] != "") && !is_posnumericint($_POST['statetimeout'])) {
		$input_errors[] = gettext("State timeout (advanced option) must be a positive integer");
	}

	if ((($_POST['max-src-conn-rate'] <> "" and $_POST['max-src-conn-rates'] == "")) ||
	    (($_POST['max-src-conn-rate'] == "" and $_POST['max-src-conn-rates'] <> ""))) {
		$input_errors[] = gettext("Both maximum new connections per host and the interval (per second(s)) must be specified");
	}

	if (!$_POST['tcpflags_any']) {
		$settcpflags = array();
		$outoftcpflags = array();
		foreach ($tcpflags as $tcpflag) {
			if ($_POST['tcpflags1_' . $tcpflag] == "on") {
				$settcpflags[] = $tcpflag;
			}
			if ($_POST['tcpflags2_' . $tcpflag] == "on") {
				$outoftcpflags[] = $tcpflag;
			}
		}
		if (empty($outoftcpflags) && !empty($settcpflags)) {
			$input_errors[] = gettext("If TCP flags that should be set is specified, then out of which flags should be specified as well.");
		}
	}

	if ($_POST['dscp'] && !in_array($_POST['dscp'], $firewall_rules_dscp_types)) {
		$input_errors[] = gettext("Invalid DSCP value.");
	}
	if ($_POST['tag'] && !is_validaliasname($_POST['tag'])) {
		$input_errors[] = gettext("Invalid tag value.");
	}
	if ($_POST['tagged'] && !is_validaliasname($_POST['tagged'])) {
		$input_errors[] = gettext("Invalid tagged value.");
	}
	if ($_POST['statetype'] && !array_key_exists($_POST['statetype'], $statetype_values)) {
		$input_errors[] = gettext("Invalid State Type.");
	}
	if ($_POST['vlanprio'] && !array_key_exists($_POST['vlanprio'], $vlanprio)) {
		$input_errors[] = gettext("Invalid VLAN Prio.");
	}
	if ($_POST['vlanprioset'] && !array_key_exists($_POST['vlanprioset'], $vlanprio)) {
		$input_errors[] = gettext("Invalid VLAN Prio Set.");
	}

	if ($_POST['ackqueue'] && !array_key_exists($_POST['ackqueue'], $list)) {
		$input_errors[] = gettext("Invalid ACK Queue.");
	}
	if ($_POST['defaultqueue'] && !array_key_exists($_POST['defaultqueue'], $list)) {
		$input_errors[] = gettext("Invalid Default Queue.");
	}

	if ($_POST['dnpipe'] && !array_key_exists($_POST['dnpipe'], $dnqlist)) {
		$input_errors[] = gettext("Invalid In Pipe.");
	}
	if ($_POST['pdnpipe'] && !array_key_exists($_POST['pdnpipe'], $dnqlist)) {
		$input_errors[] = gettext("Invalid Out Pipe.");
	}

	// Allow extending of the firewall edit page and include custom input validation
	pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/input_validation");

	if (!$input_errors) {
		$filterent = array();
		$filterent['id'] = $_POST['ruleid']>0?$_POST['ruleid']:'';

		$filterent['tracker'] = empty($_POST['tracker']) ? (int)microtime(true) : $_POST['tracker'];

		$filterent['type'] = $_POST['type'];

		if (isset($_POST['interface'])) {
			$filterent['interface'] = $_POST['interface'];
		} // FIXME: can $_POST['interface'] be unset at this point, if so then what?

		$filterent['ipprotocol'] = $_POST['ipprotocol'];

		if ($_POST['tcpflags_any']) {
			$filterent['tcpflags_any'] = true;
		} else {
			$settcpflags = array();
			$outoftcpflags = array();
			foreach ($tcpflags as $tcpflag) {
				if ($_POST['tcpflags1_' . $tcpflag] == "on") {
					$settcpflags[] = $tcpflag;
				}
				if ($_POST['tcpflags2_' . $tcpflag] == "on") {
					$outoftcpflags[] = $tcpflag;
				}
			}
			if (!empty($outoftcpflags)) {
				$filterent['tcpflags2'] = join(",", $outoftcpflags);
				if (!empty($settcpflags)) {
					$filterent['tcpflags1'] = join(",", $settcpflags);
				}
			}
		}

		if (isset($_POST['tag'])) {
			$filterent['tag'] = $_POST['tag'];
		}
		if (isset($_POST['tagged'])) {
			$filterent['tagged'] = $_POST['tagged'];
		}
		if ($if == "FloatingRules" || isset($_POST['floating'])) {
			$filterent['direction'] = $_POST['direction'];
			if (isset($_POST['quick']) && $_POST['quick'] <> "") {
				$filterent['quick'] = $_POST['quick'];
			}
			$filterent['floating'] = "yes";
			if (isset($_POST['interface']) && count($_POST['interface']) > 0) {
				$filterent['interface'] = implode(",", $_POST['interface']);
			}
		}

		/* Advanced options */
		if ($_POST['allowopts'] == "yes") {
			$filterent['allowopts'] = true;
		} else {
			unset($filterent['allowopts']);
		}
		if ($_POST['disablereplyto'] == "yes") {
			$filterent['disablereplyto'] = true;
		} else {
			unset($filterent['disablereplyto']);
		}
		$filterent['max'] = $_POST['max'];
		$filterent['max-src-nodes'] = $_POST['max-src-nodes'];
		$filterent['max-src-conn'] = $_POST['max-src-conn'];
		$filterent['max-src-states'] = $_POST['max-src-states'];
		$filterent['statetimeout'] = $_POST['statetimeout'];
		$filterent['statetype'] = $_POST['statetype'];
		$filterent['os'] = $_POST['os'];
		if ($_POST['nopfsync'] <> "") {
			$filterent['nopfsync'] = true;
		} else {
			unset($filterent['nopfsync']);
		}

		/* Nosync directive - do not xmlrpc sync this item */
		if ($_POST['nosync'] <> "") {
			$filterent['nosync'] = true;
		} else {
			unset($filterent['nosync']);
		}

		/* unless both values are provided, unset the values - ticket #650 */
		if ($_POST['max-src-conn-rate'] <> "" and $_POST['max-src-conn-rates'] <> "") {
			$filterent['max-src-conn-rate'] = $_POST['max-src-conn-rate'];
			$filterent['max-src-conn-rates'] = $_POST['max-src-conn-rates'];
		} else {
			unset($filterent['max-src-conn-rate']);
			unset($filterent['max-src-conn-rates']);
		}

		if ($_POST['proto'] != "any") {
			$filterent['protocol'] = $_POST['proto'];
		} else {
			unset($filterent['protocol']);
		}

		// Convert array of selected ICMP types to comma-separated string, for backwards compatibility (previously only allowed one type per rule)
		if ($_POST['proto'] == "icmp" && is_array($_POST['icmptype']) && !isset($_POST['icmptype']['any']) && count($_POST['icmptype']) > 0) {
			//if any of these conditions not met, rule would apply to all icmptypes, so we would unset
			$filterent['icmptype'] = implode(',', $_POST['icmptype']);
		} else {
			unset($filterent['icmptype']);
		}

		pconfig_to_address($filterent['source'], $_POST['src'],
			$_POST['srcmask'], $_POST['srcnot'],
			$_POST['srcbeginport'], $_POST['srcendport']);

		pconfig_to_address($filterent['destination'], $_POST['dst'],
			$_POST['dstmask'], $_POST['dstnot'],
			$_POST['dstbeginport'], $_POST['dstendport']);

		if ($_POST['disabled']) {
			$filterent['disabled'] = true;
		} else {
			unset($filterent['disabled']);
		}

		if ($_POST['dscp']) {
			$filterent['dscp'] = $_POST['dscp'];
		}

		if ($_POST['log']) {
			$filterent['log'] = true;
		} else {
			unset($filterent['log']);
		}

		$filterent['descr'] = trim($_POST['descr']);

		if ($_POST['gateway'] != "") {
			$filterent['gateway'] = $_POST['gateway'];
		}

		if ($_POST['defaultqueue'] != "") {
			$filterent['defaultqueue'] = $_POST['defaultqueue'];
			if ($_POST['ackqueue'] != "") {
				$filterent['ackqueue'] = $_POST['ackqueue'];
			}
		}

		if ($_POST['dnpipe'] != "") {
			$filterent['dnpipe'] = $_POST['dnpipe'];
			if ($_POST['pdnpipe'] != "") {
				$filterent['pdnpipe'] = $_POST['pdnpipe'];
			}
		}

		if ($_POST['sched'] != "") {
			$filterent['sched'] = $_POST['sched'];
		}

		if ($_POST['vlanprio'] != "") {
			$filterent['vlanprio'] = $_POST['vlanprio'];
		}
		if ($_POST['vlanprioset'] != "") {
			$filterent['vlanprioset'] = $_POST['vlanprioset'];
		}

		// If we have an associated nat rule, make sure the source and destination doesn't change
		if (isset($a_filter[$id]['associated-rule-id'])) {
			$filterent['interface'] = $a_filter[$id]['interface'];
			if (isset($a_filter[$id]['protocol'])) {
				$filterent['protocol'] = $a_filter[$id]['protocol'];
			} else if (isset($filterent['protocol'])) {
				unset($filterent['protocol']);
			}
			if ($a_filter[$id]['protocol'] == "icmp" && $a_filter[$id]['icmptype']) {
				$filterent['icmptype'] = $a_filter[$id]['icmptype'];
			} else if (isset($filterent['icmptype'])) {
				unset($filterent['icmptype']);
			}

			$filterent['source'] = $a_filter[$id]['source'];
			$filterent['destination'] = $a_filter[$id]['destination'];
			$filterent['associated-rule-id'] = $a_filter[$id]['associated-rule-id'];
		}

		if (isset($a_filter[$id]['created']) && is_array($a_filter[$id]['created'])) {
			$filterent['created'] = $a_filter[$id]['created'];
		}

		$filterent['updated'] = make_config_revision_entry();

		// Allow extending of the firewall edit page and include custom input validation
		pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/pre_write_config");

		if (isset($id) && $a_filter[$id]) {
			$tmpif = $filterent['interface'];
			if (($tmpif == $if) || (isset($pconfig['floating']))) {
				$a_filter[$id] = $filterent;
			} else {							// rule moved to different interface
				// Update the separators of previous interface.
				init_config_arr(array('filter', 'separator', strtolower($if)));
				$a_separators = &$config['filter']['separator'][strtolower($if)];
				$ridx = ifridx($if, $id);		// get rule index within interface
				$mvnrows = -1;
				move_separators($a_separators, $ridx, $mvnrows);

				$a_filter[$id] = $filterent;	// save edited rule to new interface

				// Update the separators of new interface.
				init_config_arr(array('filter', 'separator', strtolower($tmpif)));
				$a_separators = &$config['filter']['separator'][strtolower($tmpif)];
				$ridx = ifridx($tmpif, $id);	// get rule index within interface
				if ($ridx == 0) {				// rule was placed at the top
					$ridx = -1;					// move all separators
				}
				$mvnrows = +1;
				move_separators($a_separators, $ridx, $mvnrows);
			}

		} else {
			$filterent['created'] = make_config_revision_entry();
			if (is_numeric($after)) {
				array_splice($a_filter, $after+1, 0, array($filterent));

				// For copy/dup the $if var is taken from the rule submission.
				// In the case of floating rules that could be anything.  But never "FloatingRules" that is needed.
				if (isset($pconfig['floating'])) {
					$tmpif = 'FloatingRules';
				} else if (isset($filterent['interface'])) {
					$tmpif = $filterent['interface'];
					if ($tmpif != $if) {					// rule copied to different interface
						$ridx = ifridx($tmpif, $after+1);	// get rule index within interface
						if ($ridx == 0) {					// rule was placed at the top
							$after = -1;					// move all separators
						}
					}
				} else {
					$tmpif = $if;
				}

				// Update the separators
				init_config_arr(array('filter', 'separator', strtolower($tmpif)));
				$a_separators = &$config['filter']['separator'][strtolower($tmpif)];
				$ridx = ifridx($tmpif, $after);	// get rule index within interface
				$mvnrows = +1;
				move_separators($a_separators, $ridx, $mvnrows);
			} else {
				$a_filter[] = $filterent;
			}
		}

		filter_rules_sort();

		if (write_config(gettext("Firewall: Rules - saved/edited a firewall rule."))) {
			mark_subsystem_dirty('filter');
		}

		if (isset($_POST['floating'])) {
			header("Location: firewall_rules.php?if=FloatingRules");
		} else {
			header("Location: firewall_rules.php?if=" . htmlspecialchars($_POST['interface']));
		}
		exit;
	}
}

function build_flag_table() {
	global $pconfig, $tcpflags;

	$flagtable = '<table class="table table-condensed table-flags" style="width: auto;">';

	$setflags = explode(",", $pconfig['tcpflags1']);
	$outofflags = explode(",", $pconfig['tcpflags2']);
	$header = "<td></td>";
	$tcpflags1 = "<td>" . gettext("set") . "</td>";
	$tcpflags2 = "<td>" . gettext("out of") . "</td>";

	foreach ($tcpflags as $tcpflag) {
		$header .= "<td><strong>" . strtoupper($tcpflag) . "</strong></td>\n";
		$tcpflags1 .= "<td> <input type='checkbox' name='tcpflags1_{$tcpflag}' value='on' ";

		if (array_search($tcpflag, $setflags) !== false) {
			$tcpflags1 .= "checked";
		}

		$tcpflags1 .= " /></td>\n";
		$tcpflags2 .= "<td> <input type='checkbox' name='tcpflags2_{$tcpflag}' value='on' ";

		if (array_search($tcpflag, $outofflags) !== false) {
			$tcpflags2 .= "checked";
		}

		$tcpflags2 .= " /></td>\n";
	}

	$flagtable .= "<tr id='tcpheader'>{$header}</tr>\n";
	$flagtable .=  "<tr id='tcpflags1'>{$tcpflags1}</tr>\n";
	$flagtable .=  "<tr id='tcpflags2'>{$tcpflags2}</tr>\n";
	$flagtable .=  "</table>";

	$flagtable .= '<input type="checkbox" name="tcpflags_any" id="tcpflags_any" value="on"';
	$flagtable .= ($pconfig['tcpflags_any'] ? 'checked':'') . '/>';
	$flagtable .= '<strong>' . gettext(" Any flags.") . '</strong>';

	return($flagtable);
}

$pgtitle = array(gettext("Firewall"), gettext("Rules"));
$pglinks = array("");

if ($if == "FloatingRules" || isset($pconfig['floating'])) {
	$pglinks[] = "firewall_rules.php?if=FloatingRules";
	$pgtitle[] = gettext('Floating');
	$pglinks[] = "firewall_rules.php?if=FloatingRules";
} elseif (!empty($if)) {
	$pglinks = array("", "firewall_rules.php?if=" . $if);
} else {
	$pglinks = array("", "firewall_rules.php");
}

$pgtitle[] = gettext("Edit");
$pglinks[] = "@self";
$shortcut_section = "firewall";

$page_filename = "firewall_rules_edit.php";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form;
$section = new Form_Section('Edit Firewall Rule');

if (isset($id)) {
	$form->addGlobal(new Form_Input(
		'id',
		'ID',
		'hidden',
		$id
	));
}

if (isset($a_filter[$id])) {
	$form->addGlobal(new Form_Input(
		'tracker',
		'Tracker',
		'hidden',
		$pconfig['tracker']
	));
}

$form->addGlobal(new Form_Input(
	'after',
	'After',
	'hidden',
	$after
));

$form->addGlobal(new Form_Input(
	'ruleid',
	'Ruleid',
	'hidden',
	$pconfig['ruleid']
));

// Allow extending of the firewall edit page and include custom input validation
pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/htmlphpearly");

$values = array(
	'pass' => gettext('Pass'),
	'block' => gettext('Block'),
	'reject' => gettext('Reject'),
);

if ($if == "FloatingRules" || isset($pconfig['floating'])) {
	$values['match'] = gettext('Match');
}

$section->addInput(new Form_Select(
	'type',
	'*Action',
	$pconfig['type'],
	$values
))->setHelp('Choose what to do with packets that match the criteria specified '.
	'below.%sHint: the difference between block and reject is that with '.
	'reject, a packet (TCP RST or ICMP port unreachable for UDP) is returned '.
	'to the sender, whereas with block the packet is dropped silently. In '.
	'either case, the original packet is discarded.', '<br/>');

$section->addInput(new Form_Checkbox(
	'disabled',
	'Disabled',
	'Disable this rule',
	$pconfig['disabled']
))->setHelp('Set this option to disable this rule without removing it from the '.
	'list.');

if ($if == "FloatingRules" || isset($pconfig['floating'])) {
	$section->addInput(new Form_Checkbox(
		'quick',
		'Quick',
		'Apply the action immediately on match.',
		$pconfig['quick']
	))->setHelp('Set this option to apply this action to traffic that '.
		'matches this rule immediately.');
}

$edit_disabled = isset($pconfig['associated-rule-id']);

if ($edit_disabled) {
	$extra = '';
	foreach ($config['nat']['rule'] as $index => $nat_rule) {
		if ($nat_rule['associated-rule-id'] === $pconfig['associated-rule-id']) {
			$extra = '<br/><a href="firewall_nat_edit.php?id='. $index .'">'. gettext('View the NAT rule') .'</a>';
		}
	}

	$section->addInput(new Form_StaticText(
		'Associated filter rule',
		'<span class="help-block">' .
		'This is associated with a NAT rule.<br/>' .
		'Editing the interface, protocol, source, or destination of associated filter rules is not permitted.'.
		$extra .
		'</span>'
		));

	$form->addGlobal(new Form_Input(
		'associated-rule-id',
		null,
		'hidden',
		$pconfig['associated-rule-id']
	));

	if (!empty($pconfig['interface'])) {
		$form->addGlobal(new Form_Input(
			'interface',
			null,
			'hidden',
			$pconfig['interface']
		));
	}
}

if ($if == "FloatingRules" || isset($pconfig['floating'])) {
	$section->addInput($input = new Form_Select(
		'interface',
		'*Interface',
		$pconfig['interface'],
		filter_get_interface_list(),
		true
	))->setHelp('Choose the interface(s) for this rule.');
} else {
	$section->addInput($input = new Form_Select(
		'interface',
		'*Interface',
		$pconfig['interface'],
		filter_get_interface_list()
	))->setHelp('Choose the interface from which packets must come to match this rule.');
}

if ($if == "FloatingRules" || isset($pconfig['floating'])) {
	$section->addInput(new Form_Select(
		'direction',
		'*Direction',
		$pconfig['direction'],
		array(
			'any' => gettext('any'),
			'in' => gettext('in'),
			'out' => gettext('out'),
		)
	));

	$form->addGlobal(new Form_Input(
		'floating',
		'Floating',
		'hidden',
		'floating'
	));
}

$section->addInput(new Form_Select(
	'ipprotocol',
	'*Address Family',
	$pconfig['ipprotocol'],
	array(
		'inet' => 'IPv4',
		'inet6' => 'IPv6',
		'inet46' => 'IPv4+IPv6',
	)
))->setHelp('Select the Internet Protocol version this rule applies to.');

$section->addInput(new Form_Select(
	'proto',
	'*Protocol',
	$pconfig['proto'],
	array(
		'any' => gettext('Any'),
		'tcp' => 'TCP',
		'udp' => 'UDP',
		'tcp/udp' => 'TCP/UDP',
		'icmp' => 'ICMP',
		'esp' => 'ESP',
		'ah' => 'AH',
		'gre' => 'GRE',
		'ipv6' => 'IPV6',
		'igmp' => 'IGMP',
		'pim' => 'PIM',
		'ospf' => 'OSPF',
		'sctp' => 'SCTP',
		'carp' => 'CARP',
		'pfsync' => 'PFSYNC',
	)
))->setHelp('Choose which IP protocol this rule should match.');

$group = new Form_Group("ICMP Subtypes");
$group->add(new Form_Select(
	'icmptype',
	'ICMP subtypes',
	((isset($pconfig['icmptype']) && strlen($pconfig['icmptype']) > 0) ? explode(',', $pconfig['icmptype']) : 'any'),
	isset($icmplookup[$pconfig['ipprotocol']]) ? $icmplookup[$pconfig['ipprotocol']]['icmptypes'] : array('any' => gettext('any')),
	true
))->setHelp('%s', '<div id="icmptype_help">' . (isset($icmplookup[$pconfig['ipprotocol']]) ? $icmplookup[$pconfig['ipprotocol']]['helpmsg'] : '') . '</div>');
$group->addClass('icmptype_section');

$section->add($group);

$form->add($section);

// Source and destination share a lot of logic. Loop over the two
// ToDo: Unfortunately they seem to differ more than they share. This needs to be unrolled
foreach (['src' => gettext('Source'), 'dst' => gettext('Destination')] as $type => $name) {
	$section = new Form_Section($name);

	$group = new Form_Group('*' . $name);
	$group->add(new Form_Checkbox(
		$type .'not',
		$name .' not',
		'Invert match.',
		$pconfig[$type.'not']
	))->setWidth(2);

	// The rule type dropdown on the GUI can be one of the special names like
	// "any" "LANnet" "LAN address"... or "Single host or alias" or "Network"
	if ($pconfig[$type.'type']) {
		// The rule type came from the $_POST array, after input errors, so keep it.
		$ruleType = $pconfig[$type.'type'];
	} elseif (is_specialnet($pconfig[$type])) {
		// It is one of the special names, let it through as-is.
		$ruleType = $pconfig[$type];
	} elseif ((is_ipaddrv6($pconfig[$type]) && $pconfig[$type.'mask'] == 128) ||
	    (is_ipaddrv4($pconfig[$type]) && $pconfig[$type.'mask'] == 32) ||
	    (is_alias($pconfig[$type]))) {
		// It is a single-host IP address or an alias
		$ruleType = 'single';
	} else {
		// Everything else must be a network
		$ruleType = 'network';
	}

	$ruleValues = array(
		'any' => gettext('any'),
		'single' => gettext('Single host or alias'),
		'network' => gettext('Network'),
	);

	if ($type == 'dst') {
		$ruleValues['(self)'] = gettext("This firewall (self)");
	}

	if (isset($a_filter[$id]['floating']) || $if == "FloatingRules") {
		$ruleValues['(self)'] = gettext('This Firewall (self)');
	}
	if (have_ruleint_access("pppoe")) {
		$ruleValues['pppoe'] = gettext('PPPoE clients');
	}
	if (have_ruleint_access("l2tp")) {
		$ruleValues['l2tp'] = gettext('L2TP clients');
	}

	foreach ($ifdisp as $ifent => $ifdesc) {
		if (!have_ruleint_access($ifent)) {
			continue;
		}

		$ruleValues[$ifent] = $ifdesc.' net';
		$ruleValues[$ifent.'ip'] = $ifdesc.' address';
	}

	$group->add(new Form_Select(
		$type . 'type',
		$name .' Type',
		$ruleType,
		$ruleValues
	));

	$group->add(new Form_IpAddress(
		$type,
		$name .' Address',
		$pconfig[$type],
		'ALIASV4V6'
	))->addMask($type .'mask', $pconfig[$type.'mask']);

	$section->add($group);

	if ($type == 'src') {
		$section->addInput(new Form_Button(
			'btnsrctoggle',
			'',
			null,
			'fa-cog'
		))->setAttribute('type','button')->addClass('btn-info btn-sm')->setHelp(
			'The %1$sSource Port Range%2$s for a connection is typically random '.
			'and almost never equal to the destination port. '.
			'In most cases this setting must remain at its default value, %1$sany%2$s.', '<b>', '</b>');
	}

	$portValues = ['' => gettext('(other)'), 'any' => gettext('any')];
	foreach ($wkports as $port => $portName) {
		$portValues[$port] = $portName.' ('. $port .')';
	}

	$group = new Form_Group($type == 'src' ? gettext('Source Port Range') : gettext('Destination Port Range'));

	$group->addClass($type . 'portrange');

	$group->add(new Form_Select(
		$type .'beginport',
		$name .' port begin',
		$pconfig[$type .'beginport'],
		$portValues
	))->setHelp('From');

	$group->add(new Form_Input(
		$type .'beginport_cust',
		null,//$name .' port begin custom',
		'text',
		(isset($portValues[ $pconfig[$type .'beginport'] ]) ? null : $pconfig[$type .'beginport'])
	))->setHelp('Custom');

	$group->add(new Form_Select(
		$type .'endport',
		$name .' port end',
		$pconfig[$type .'endport'],
		$portValues
	))->setHelp('To');

	$group->add(new Form_Input(
		$type .'endport_cust',
		null,//$name .' port end custom',
		'text',
		(isset($portValues[ $pconfig[$type .'endport'] ]) ? null : $pconfig[$type .'endport'])
	))->setHelp('Custom');

	$group->setHelp('Specify the %s port or port range for this rule. The "To" field may be left empty if only filtering a single port.', strtolower($name));

	$group->addClass(($type == 'src') ? 'srcprtr':'dstprtr');
	$section->add($group);
	$form->add($section);
}

$section = new Form_Section('Extra Options');
$section->addInput(new Form_Checkbox(
	'log',
	'Log',
	'Log packets that are handled by this rule',
	$pconfig['log']
))->setHelp('Hint: the firewall has limited local log space. Don\'t turn on logging '.
	'for everything. If doing a lot of logging, consider using a remote '.
	'syslog server (see the %1$sStatus: System Logs: Settings%2$s page).', '<a href="status_logs_settings.php">', '</a>');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference. ' .
	'A maximum of %s characters will be used in the ruleset and displayed in the firewall log.',
	user_rule_descr_maxlen());

$btnadv = new Form_Button(
	'btnadvopts',
	'Display Advanced',
	null,
	'fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	'Advanced Options',
	$btnadv
));

$form->add($section);

$section = new Form_Section('Advanced Options');
$section->addClass('advanced-options');

$section->addInput(new Form_Select(
	'os',
	'Source OS',
	(empty($pconfig['os']) ? '':$pconfig['os']),
	['' => gettext('Any')] + array_combine($ostypes, $ostypes)
))->setHelp('Note: this only works for TCP rules. General OS choice matches all subtypes.');

$section->addInput(new Form_Select(
	'dscp',
	'Diffserv Code Point',
	$pconfig['dscp'],
	["" => ''] + array_combine($firewall_rules_dscp_types, $firewall_rules_dscp_types)
));

$section->addInput(new Form_Checkbox(
	'allowopts',
	'Allow IP options',
	'Allow packets with IP options to pass. Otherwise they are blocked by '.
	'default. This is usually only seen with multicast traffic.',
	$pconfig['allowopts']
));

$section->addInput(new Form_Checkbox(
	'disablereplyto',
	'Disable reply-to',
	'Disable auto generated reply-to for this rule.',
	$pconfig['disablereplyto']
));

$section->addInput(new Form_Input(
	'tag',
	'Tag',
	'text',
	$pconfig['tag']
))->setHelp('A packet matching this rule can be marked and this mark used to match '.
	'on other NAT/filter rules. It is called %1$sPolicy filtering%2$s.', '<b>', '</b>');

$section->addInput(new Form_Input(
	'tagged',
	'Tagged',
	'text',
	$pconfig['tagged']
))->setHelp('A packet can be matched on a mark placed before on another rule.');

$section->addInput(new Form_Input(
	'max',
	'Max. states',
	'number',
	$pconfig['max']
))->setHelp('Maximum state entries this rule can create.');

$section->addInput(new Form_Input(
	'max-src-nodes',
	'Max. src nodes',
	'number',
	$pconfig['max-src-nodes']
))->setHelp('Maximum number of unique source hosts.');

$section->addInput(new Form_Input(
	'max-src-conn',
	'Max. connections',
	'number',
	$pconfig['max-src-conn']
))->setHelp('Maximum number of established connections per host (TCP only).');

$section->addInput(new Form_Input(
	'max-src-states',
	'Max. src. states',
	'number',
	$pconfig['max-src-states']
))->setHelp('Maximum state entries per host.');

$section->addInput(new Form_Input(
	'max-src-conn-rate',
	'Max. src. conn. Rate',
	'number',
	$pconfig['max-src-conn-rate']
))->setHelp('Maximum new connections per host (TCP only).');

$section->addInput(new Form_Input(
	'max-src-conn-rates',
	'Max. src. conn. Rates',
	'number',
	$pconfig['max-src-conn-rates'],
	['min' => 1, 'max' => 255]
))->setHelp('/ per how many second(s) (TCP only)');

$section->addInput(new Form_Input(
	'statetimeout',
	'State timeout',
	'number',
	$pconfig['statetimeout'],
	['min' => 1]
))->setHelp('State Timeout in seconds (TCP only)');

$section->addInput(new Form_StaticText(
	'TCP Flags',
	build_flag_table()
))->setHelp('Use this to choose TCP flags that must be set or cleared for this rule to match.');

$section->addInput(new Form_Checkbox(
	'nopfsync',
	'No pfSync',
	'Prevent states created by this rule to be sync\'ed over pfsync.',
	$pconfig['nopfsync']
));

$section->addInput(new Form_Select(
	'statetype',
	'State type',
	(isset($pconfig['statetype'])) ? $pconfig['statetype'] : "keep state",
	$statetype_values
))->setHelp('Select which type of state tracking mechanism to use.  If in doubt, use keep state.%1$s',
			'<br /><span></span>');

$section->addInput(new Form_Checkbox(
	'nosync',
	'No XMLRPC Sync',
	'Prevent the rule on Master from automatically syncing to other CARP members',
	$pconfig['nosync']
))->setHelp('This does NOT prevent the rule from being overwritten on Slave.');

$section->addInput(new Form_Select(
	'vlanprio',
	'VLAN Prio',
	$pconfig['vlanprio'],
	$vlanprio
))->setHelp('Choose 802.1p priority to match on.');

$section->addInput(new Form_Select(
	'vlanprioset',
	'VLAN Prio Set',
	$pconfig['vlanprioset'],
	$vlanprio
))->setHelp('Choose 802.1p priority to apply.');

$schedules = array();
foreach ((array)$config['schedules']['schedule'] as $schedule) {
	if ($schedule['name'] != "") {
		$schedules[] = $schedule['name'];
	}
}

$section->addInput(new Form_Select(
	'sched',
	'Schedule',
	$pconfig['sched'],
	['' => gettext('none')] + array_combine($schedules, $schedules)
))->setHelp('Leave as \'none\' to leave the rule enabled all the time.');

// Build the gateway lists in JSON so the selector can be populated in JS
$gwjson = '[{"name":"", "gateway":"Default", "family":"inet46"}';

foreach (return_gateways_array() as $gwname => $gw) {
	$gwjson = $gwjson . "," .'{"name":' . json_encode($gwname) . ', "gateway":' .
	json_encode($gw['name'] . (empty($gw['gateway'])? '' : ' - '. $gw['gateway']) . (empty($gw['descr'])? '' : ' - '. $gw['descr'])) . ',"family":' .
	json_encode($gw['ipprotocol']) . '}';
}

foreach ((array)$a_gatewaygroups as $gwg_name => $gwg_data) {
	$gwjson = $gwjson . "," .'{"name":' . json_encode($gwg_name) . ', "gateway":' .
	json_encode($gwg_data['name'] . $gwg_name . (empty($gwg_data['descr'])? '' : ' - '. $gwg_data['descr'])) . ',"family":' .
	json_encode($gwg_data['ipprotocol']) . '}';
	$firstgw = false;
}

$gwjson .= ']';
$gwselected = $pconfig['gateway'];

// print($gwjson);

// Gateway selector is populated by JavaScript updateGWselect() function
$section->addInput(new Form_Select(
	'gateway',
	'Gateway',
	'',
	[]
))->setHelp('Leave as \'default\' to use the system routing table. Or choose a '.
	'gateway to utilize policy based routing. %sGateway selection is not valid for "IPV4+IPV6" address family.', '<br />');

$group = new Form_Group('In / Out pipe');

$group->add(new Form_Select(
	'dnpipe',
	'DNpipe',
	(isset($pconfig['dnpipe'])) ? $pconfig['dnpipe']:"",
	array('' => gettext('none')) + array_combine(array_keys($dnqlist), array_keys($dnqlist))
));

$group->add(new Form_Select(
	'pdnpipe',
	'PDNpipe',
	(isset($pconfig['pdnpipe'])) ? $pconfig['pdnpipe']:"",
	array('' => gettext('none')) + array_combine(array_keys($dnqlist), array_keys($dnqlist))
));

$section->add($group)->setHelp('Choose the Out queue/Virtual interface only if '.
	'In is also selected. The Out selection is applied to traffic leaving '.
	'the interface where the rule is created, the In selection is applied to traffic coming '.
	'into the chosen interface.%1$sIf creating a floating rule, if the '.
	'direction is In then the same rules apply, if the direction is Out the '.
	'selections are reversed, Out is for incoming and In is for outgoing.',
	'<br />'
);

$group = new Form_Group('Ackqueue / Queue');

$group->add(new Form_Select(
	'ackqueue',
	'Ackqueue',
	$pconfig['ackqueue'],
	$list
));

$group->add(new Form_Select(
	'defaultqueue',
	'Default Queue',
	$pconfig['defaultqueue'],
	$list
));

$section->add($group)->setHelp('Choose the Acknowledge Queue only if there is a '.
	'selected Queue.'
);

$form->add($section);

gen_created_updated_fields($form, $a_filter[$id]['created'], $a_filter[$id]['updated'], $a_filter[$id]['tracker']);

echo $form;
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	var portsenabled = 1;
	var editenabled = 1;
	var srcportsvisible = false;

	// Show advanced additional opts options ======================================================
	var showadvopts = false;

	// Remove focus on page load
	document.activeElement.blur()

	function show_advopts(ispageload) {
		var text;
		// On page load decide the initial state based on the data.
		if (ispageload) {
			showadvopts = <?php if (is_aoadv_used($pconfig)) {echo 'true';} else {echo 'false';} ?>;
		} else {
			// It was a click, swap the state.
			showadvopts = !showadvopts;
		}

		hideClass('advanced-options', !showadvopts);
		if ($('#tcpflags_any').prop('checked')) {
			$('.table-flags').addClass('hidden');
		}

		if (showadvopts) {
			text = "<?=gettext('Hide Advanced');?>";
		} else {
			text = "<?=gettext('Display Advanced');?>";
		}
		$('#btnadvopts').html('<i class="fa fa-cog"></i> ' + text);
	}

	$('#btnadvopts').click(function(event) {
		show_advopts();
	});

	function ext_change() {

		if (($('#srcbeginport').find(":selected").index() == 0) && portsenabled && editenabled) {
			disableInput('srcbeginport_cust', false);
		} else {
			if (editenabled) {
				$('#srcbeginport_cust').val("");
			}

			disableInput('srcbeginport_cust', true);
		}

		if (($('#srcendport').find(":selected").index() == 0) && portsenabled && editenabled) {
			disableInput('srcendport_cust', false);
		} else {
			if (editenabled) {
				$('#srcendport_cust').val("");
			}

			disableInput('srcendport_cust', true);
		}

		if (($('#dstbeginport').find(":selected").index() == 0) && portsenabled && editenabled) {
			disableInput('dstbeginport_cust', false);
		} else {
			if (editenabled) {
				$('#dstbeginport_cust').val("");
			}

			disableInput('dstbeginport_cust', true);
		}

		if (($('#dstendport').find(":selected").index() == 0) && portsenabled && editenabled) {
			disableInput('dstendport_cust', false);
		} else {
			if (editenabled) {
				$('#dstendport_cust').val("");
			}

			disableInput('dstendport_cust', true);
		}

		if (!portsenabled) {
			disableInput('srcbeginport', true);
			disableInput('srcendport', true);
			disableInput('dstbeginport', true);
			disableInput('dstendport', true);
		} else {
			if (editenabled) {
				disableInput('srcbeginport', false);
				disableInput('srcendport', false);
				disableInput('dstbeginport', false);
				disableInput('dstendport', false);
			}
		}
	}

	function show_source_port_range() {
		hideClass('srcprtr', !srcportsvisible);

		if (srcportsvisible) {
			text = "<?=gettext('Hide Advanced');?>";
		} else {
			text = "<?=gettext('Display Advanced');?>";
		}

		$('#btnsrctoggle').html('<i class="fa fa-cog"></i> ' + text);
	}

	function typesel_change() {
		src_typesel_change();
		dst_typesel_change();
	}

	function src_typesel_change() {
		if (editenabled) {
			switch ($('#srctype').find(":selected").index()) {
				case 1: // single
					disableInput('src', false);
					$('#srcmask').val("");
					disableInput('srcmask', true);
					break;
				case 2: // network
					disableInput('src', false);
					disableInput('srcmask', false);
					break;
				default:
					$('#src').val("");
					disableInput('src', true);
					$('#srcmask').val("");
					disableInput('srcmask', true);
					break;
			}
		}
	}

	function dst_typesel_change() {
		if (editenabled) {
			switch ($('#dsttype').find(":selected").index()) {
				case 1: // single
					disableInput('dst', false);
					$('#dstmask').val("");
					disableInput('dstmask', true);
					break;
				case 2: // network
					disableInput('dst', false);
					disableInput('dstmask', false);
					break;
				default:
					$('#dst').val("");
					disableInput('dst', true);
					$('#dstmask').val("");
					disableInput('dstmask', true);
					break;
			}
		}
	}

	// Populate the "gateway" selector from a JSON array composed in the PHP
	function updateGWselect() {
		var selected = "<?=$gwselected?>";
		var protocol = $('#ipprotocol').val();
		var json = JSON.parse(<?=json_encode($gwjson)?>);

		// Remove all of the existing optns
		$('#gateway').find('option').remove();

		// Add new ones as appropriate for the address family
		json.forEach(function(gwobj) {
			if (((gwobj.family == protocol) || (gwobj.family == "inet46")) && (protocol != "inet46")) {
				$('#gateway').append($('<option>', {
				    text: gwobj.gateway,
				    value: gwobj.name
				}));
			}
		});

		// Add "selected" attribute as needed
		$('#gateway').val(selected);

		// Gateway selection is not permitted for "IPV4+IPV6"
		$('#gateway').prop("disabled", protocol == "inet46");

	}

	function proto_change() {
		var is_tcpudp = (jQuery.inArray($('#proto :selected').val(), ['tcp','udp', 'tcp/udp']) != -1);
		portsenabled = (is_tcpudp ? 1 : 0);
		hideClass('tcpflags', !is_tcpudp);

		// Disable OS if the proto is not TCP.
		disableInput('os', ($('#proto :selected').val() != 'tcp'));

		// Hide ICMP types if not icmp rule
		hideClass('icmptype_section', $('#proto').val() != 'icmp');
		// Update ICMP help msg to match current IP protocol
		$('#icmptype_help').html(icmphelp[$('#ipprotocol').val()]);
		// Update ICMP types available for current IP protocol, copying over any still-valid selections
		var listid = "#icmptype\\[\\]"; // for ease of use
		var current_sel = ($(listid).val() || ['any']); // Ensures we get correct array when none selected
		var new_options = icmptypes[$('#ipprotocol').val()];
		var new_html = '';
		//remove and re-create the select element (otherwise the options can disappear in Safari)
		$(listid).remove();
		var select = $("<select></select>").attr("id", "icmptype[]").attr("name", "icmptype[]").addClass("form-control").attr("multiple", "multiple");
		$('div.icmptype_section > div.col-sm-10').prepend(select);

		for (var key in new_options) {
			new_html += '<option value="' + key + (jQuery.inArray(key, current_sel) != -1 ? '" selected="selected">' : '">') + new_options[key] + '</option>\n';
		}

		$(listid).html(new_html);

		ext_change();

		if (is_tcpudp) {
			hideClass('dstprtr', false);
			hideInput('btnsrctoggle', false);
			if ((($('#srcbeginport').val() == "any") || ($('#srcbeginport').val() == "")) &&
			    (($('#srcendport').val() == "any") || ($('#srcendport').val() == ""))) {
				srcportsvisible = false;
			} else {
				srcportsvisible = true;
			}
		} else {
			hideClass('dstprtr', true);
			hideInput('btnsrctoggle', true);
			srcportsvisible = false;
		}

		show_source_port_range();

		updateGWselect();
	}

	function icmptype_change() {
		var listid = "#icmptype\\[\\]"; // for ease of use
		var current_sel = ($(listid).val() || ['any']); // Ensures we get correct array when none selected
		if (jQuery.inArray('any', current_sel) != -1) {
			// "any" negates all selections
			$(listid).find('option').not('[value="any"]').removeAttr('selected');
		}
		if ($(listid + ' option:selected').length == 0) {
			// no selection = select "any"
			$(listid + ' option[value="any"]').prop('selected', true);
		}
	}

	function src_rep_change() {
		$('#srcendport').prop("selectedIndex", $('#srcbeginport').find(":selected").index());
	}

	function dst_rep_change() {
		$('#dstendport').prop("selectedIndex", $('#dstbeginport').find(":selected").index());
	}

	// On initial page load

<?php
	// Generate icmptype data used in form JS
	$out1 = "var icmptypes = [];\n";
	$out2 = "var icmphelp = [];\n";
	foreach ($icmplookup as $k => $v) {
		$a = array();
		foreach ($v['icmptypes'] as $icmp_k => $icmp_v) {
			$a[] = sprintf("'%s':'%s'", $icmp_k, $icmp_v);
		}
		$out1 .= "icmptypes['{$k}'] = {\n\t" . implode(",\n\t", $a) . "\n};\n";
		$out2 .= "icmphelp['{$k}'] = '" . str_replace("'", '&apos;', $v['helpmsg']) . "';\n";
	}
	echo $out1;
	echo $out2;
?>

	proto_change();

	ext_change();

	typesel_change();

	show_advopts(true);
	hideClass('srcportrange', true);

	<?php if ((!empty($pconfig['srcbeginport']) && $pconfig['srcbeginport'] != "any") || (!empty($pconfig['srcendport']) && $pconfig['srcendport'] != "any")): ?>
		srcportsvisible = true;
		show_source_port_range();
	<?php endif; ?>

	// on click . .
	$('#srcbeginport').on('change', function() {
		src_rep_change();
		ext_change();
	});

	$('#btnsrctoggle').click(function() {
		srcportsvisible = !srcportsvisible;
		show_source_port_range();
	});

	$('#srcendport').on('change', function() {
		ext_change();
	});

	$('#save').on('click', function() {
		disableInput('save');
	});

	$('#dstbeginport').on('change', function() {
		dst_rep_change();
		ext_change();
	});

	$('#dstendport').on('change', function() {
		ext_change();
	});

	$('#srctype').on('change', function() {
		src_typesel_change();
	});

	$('#dsttype').on('change', function() {
		dst_typesel_change();
	});

	$('#ipprotocol').on('change', function() {
		proto_change();
	});

	$('#proto').on('change', function() {
		proto_change();
	});

	$('#icmptype\\[\\]').on('change', function() {
			icmptype_change();
	});

	$('#tcpflags_any').click(function () {
		if (this.checked) {
			$('.table-flags').addClass('hidden');
		} else {
			$('.table-flags').removeClass('hidden');
		}
	});

	// Change help text based on the selector value
	function setOptText(target, val) {
		var dispstr = '<span class="text-success">';

		if (val == 'keep state') {
			dispstr += 'Keep: works with all IP protocols';
		} else if (val == 'sloppy state') {
			dispstr += 'Sloppy: works with all IP protocols';
		} else if (val == 'synproxy state') {
			dispstr += 'Synproxy: proxies incoming TCP connections to help protect servers from spoofed TCP SYN floods, at the cost of performance (no SACK or window scaling)';
		} else if (val == 'none') {
			dispstr += 'None: Do not use state mechanisms to keep track';
		}

		dispstr += '</span>';
		setHelpText(target, dispstr);
	}

	// When editing "associated" rules, everything except the enable, action, address family and description
	// fields are disabled
	function disable_most(disable) {
		var elementsToDisable = [
			'interface', 'proto', 'icmptype\\[\\]', 'srcnot', 'srctype', 'src', 'srcmask', 'srcbebinport', 'srcbeginport_cust', 'srcendport',
			'srcendport_cust', 'dstnot', 'dsttype', 'dst', 'dstmask', 'dstbeginport', 'dstbeginport_cust', 'dstendport', 'dstendport_cust'];

		for (var idx=0, len = elementsToDisable.length; idx<len; idx++) {
			disableInput(elementsToDisable[idx], disable);
		}
	}

	// ---------- Click checkbox handlers ---------------------------------------------------------

	$('#statetype').on('change', function() {
		setOptText('statetype', this.value);
	});

	// ---------- On initial page load ------------------------------------------------------------

	setOptText('statetype', $('#statetype').val())
<?php if ($edit_disabled) {
?>
	disable_most(true);
<?php
}
?>

	// ---------- Autocomplete --------------------------------------------------------------------

	var addressarray = <?= json_encode(get_alias_list(array("host", "network", "url", "urltable"))) ?>;
	var customarray = <?= json_encode(get_alias_list(array("port", "url_ports", "urltable_ports"))) ?>;

	$('#src, #dst').autocomplete({
		source: addressarray
	});

	$('#dstbeginport_cust, #dstendport_cust, #srcbeginport_cust, #srcendport_cust').autocomplete({
		source: customarray
	});
});
//]]>
</script>

<?php
include("foot.inc");
