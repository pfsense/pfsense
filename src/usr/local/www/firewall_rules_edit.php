<?php
/*
	firewall_rules_edit.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-firewall-rules-edit
##|*NAME=Firewall: Rules: Edit
##|*DESCR=Allow access to the 'Firewall: Rules: Edit' page.
##|*MATCH=firewall_rules_edit.php*
##|-PRIV

require("guiconfig.inc");
require_once("ipsec.inc");
require_once("filter.inc");
require("shaper.inc");

if (isset($_POST['referer'])) {
	$referer = $_POST['referer'];
} else {
	$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/firewall_rules.php');
}

function is_posnumericint($arg) {
	// Note that to be safe we do not allow any leading zero - "01", "007"
	return (is_numericint($arg) && $arg[0] != '0' && $arg > 0);
}

function is_aoadv_used($rule_config) {
	// Note that the user could set "tag" or "tagged" to the string "0", which is valid but empty().
	// And if the user enters "0" in other fields, we want to present an error message, and keep the Advanced Options section open.
	if ((isset($rule_config['allowopts'])) ||
	    (isset($rule_config['disablereplyto'])) ||
	    ($rule_config['tag'] != "") ||
	    ($rule_config['tagged'] != "") ||
	    ($rule_config['max'] != "") ||
	    ($rule_config['max-src-nodes'] != "") ||
	    ($rule_config['max-src-conn'] != "") ||
	    ($rule_config['max-src-states'] != "") ||
	    ($rule_config['max-src-conn-rate'] != "") ||
	    ($rule_config['max-src-conn-rates'] != "") ||
	    ($rule_config['statetimeout'] != "")) {
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

if (!is_array($config['filter']['rule'])) {
	$config['filter']['rule'] = array();
}
filter_rules_sort();
$a_filter = &$config['filter']['rule'];

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (is_numericint($_GET['after']) || $_GET['after'] == "-1") {
	$after = $_GET['after'];
}
if (isset($_POST['after']) && (is_numericint($_POST['after']) || $_POST['after'] == "-1")) {
	$after = $_POST['after'];
}

if (isset($_GET['dup']) && is_numericint($_GET['dup'])) {
	$id = $_GET['dup'];
	$after = $_GET['dup'];
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
			$pconfig['interface'] = $a_filter[$id]['interface'];
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
	if (!isset($_GET['dup']) || !is_numericint($_GET['dup'])) {
		$pconfig['associated-rule-id'] = $a_filter[$id]['associated-rule-id'];
	}

	$pconfig['tracker'] = $a_filter[$id]['tracker'];

} else {
	/* defaults */
	if ($_GET['if']) {
		$pconfig['interface'] = $_GET['if'];
	}
	$pconfig['type'] = "pass";
	$pconfig['src'] = "any";
	$pconfig['dst'] = "any";
}
/* Allow the FloatingRules to work */
$if = $pconfig['interface'];

if (isset($_GET['dup']) && is_numericint($_GET['dup'])) {
	unset($id);
}

read_altq_config(); /* XXX: */
$qlist =& get_unique_queue_list();
read_dummynet_config(); /* XXX: */
$dnqlist =& get_unique_dnqueue_list();
$a_gatewaygroups = return_gateway_groups_array();

if ($_POST) {
	unset($input_errors);

	if (isset($a_filter[$id]['associated-rule-id'])) {
		$_POST['proto'] = $pconfig['proto'];
		if ($pconfig['proto'] == "icmp") {
			$_POST['icmptype'] = $pconfig['icmptype'];
		}
	}

	if (($_POST['ipprotocol'] <> "") && ($_POST['gateway'] <> "")) {
		if (is_array($config['gateways']['gateway_group'])) {
			foreach ($config['gateways']['gateway_group'] as $gw_group) {
				if ($gw_group['name'] == $_POST['gateway']) {
					$family = $a_gatewaygroups[$_POST['gateway']]['ipprotocol'];
					if ($_POST['ipprotocol'] == $family) {
						continue;
					}
					if (($_POST['ipprotocol'] == "inet46") && ($_POST['ipprotocol'] != $family)) {
						$input_errors[] = gettext("You can not assign a gateway to a rule that applies to IPv4 and IPv6");
					}
					if (($_POST['ipprotocol'] == "inet6") && ($_POST['ipprotocol'] != $family)) {
						$input_errors[] = gettext("You can not assign an IPv4 gateway group on IPv6 Address Family rule");
					}
					if (($_POST['ipprotocol'] == "inet") && ($_POST['ipprotocol'] != $family)) {
						$input_errors[] = gettext("You can not assign an IPv6 gateway group on IPv4 Address Family rule");
					}
				}
			}
		}
	}
	if (($_POST['ipprotocol'] <> "") && ($_POST['gateway'] <> "") && (is_ipaddr(lookup_gateway_ip_by_name($_POST['gateway'])))) {
		if (($_POST['ipprotocol'] == "inet46") && ($_POST['gateway'] <> "")) {
			$input_errors[] = gettext("You can not assign a gateway to a rule that applies to IPv4 and IPv6");
		}
		if (($_POST['ipprotocol'] == "inet6") && (!is_ipaddrv6(lookup_gateway_ip_by_name($_POST['gateway'])))) {
			$input_errors[] = gettext("You can not assign an IPv4 Gateway to an IPv6 Filter rule");
		}
		if (($_POST['ipprotocol'] == "inet") && (!is_ipaddrv4(lookup_gateway_ip_by_name($_POST['gateway'])))) {
			$input_errors[] = gettext("You can not assign an IPv6 Gateway to an IPv4 Filter rule");
		}
	}
	if (($_POST['proto'] == "icmp") && ($_POST['icmptype'] <> "")) {
		if ($_POST['ipprotocol'] == "inet46") {
			$input_errors[] = gettext("You can not assign a ICMP type to a rule that applies to IPv4 and IPv6");
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

	/* input validation */
	$reqdfields = explode(" ", "type proto");
	if (isset($a_filter[$id]['associated-rule-id']) === false) {
		$reqdfields[] = "src";
		$reqdfields[] = "dst";
	}
	$reqdfieldsn = explode(",", "Type,Protocol");
	if (isset($a_filter[$id]['associated-rule-id']) === false) {
		$reqdfieldsn[] = "Source";
		$reqdfieldsn[] = "Destination";
	}

	if ($_POST['statetype'] == "modulate state" or $_POST['statetype'] == "synproxy state") {
		if ($_POST['proto'] != "tcp") {
			$input_errors[] = sprintf(gettext("%s is only valid with protocol TCP."), $_POST['statetype']);
		}
		if (($_POST['statetype'] == "synproxy state") && ($_POST['gateway'] != "")) {
			$input_errors[] = sprintf(gettext("%s is only valid if the gateway is set to 'default'."), $_POST['statetype']);
		}
	}

	if (isset($a_filter[$id]['associated-rule-id']) === false &&
	    (!(is_specialnet($_POST['srctype']) || ($_POST['srctype'] == "single")))) {
		$reqdfields[] = "srcmask";
		$reqdfieldsn[] = "Source bit count";
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

	if ($_POST['srcbeginport'] && !is_portoralias($_POST['srcbeginport'])) {
		$input_errors[] = sprintf(gettext("%s is not a valid start source port. It must be a port alias or integer between 1 and 65535."), $_POST['srcbeginposrt']);
	}
	if ($_POST['srcendport'] && !is_portoralias($_POST['srcendport'])) {
			$input_errors[] = sprintf(gettext("%s is not a valid end source port. It must be a port alias or integer between 1 and 65535."), $_POST['srcendport']);
	}
	if ($_POST['dstbeginport'] && !is_portoralias($_POST['dstbeginport'])) {
			$input_errors[] = sprintf(gettext("%s is not a valid start destination port. It must be a port alias or integer between 1 and 65535."), $_POST['dstbeginport']);
	}
	if ($_POST['dstendport'] && !is_portoralias($_POST['dstendport'])) {
			$input_errors[] = sprintf(gettext("%s is not a valid end destination port. It must be a port alias or integer between 1 and 65535."), $_POST['dstendport']);
	}
	if (!$_POST['srcbeginport_cust'] && $_POST['srcendport_cust']) {
		if (is_alias($_POST['srcendport_cust'])) {
			$input_errors[] = 'If you put port alias in Source port range to: field you must put the same port alias in from: field';
		}
	}
	if ($_POST['srcbeginport_cust'] && $_POST['srcendport_cust']) {
		if (is_alias($_POST['srcendport_cust']) && is_alias($_POST['srcendport_cust']) && $_POST['srcbeginport_cust'] != $_POST['srcendport_cust']) {
			$input_errors[] = 'The same port alias must be used in Source port range from: and to: fields';
		}
		if ((is_alias($_POST['srcbeginport_cust']) && (!is_alias($_POST['srcendport_cust']) && $_POST['srcendport_cust'] != '')) ||
		    ((!is_alias($_POST['srcbeginport_cust']) && $_POST['srcbeginport_cust'] != '') && is_alias($_POST['srcendport_cust']))) {
			$input_errors[] = 'You cannot specify numbers and port aliases at the same time in Source port range from: and to: field';
		}
	}
	if (!$_POST['dstbeginport_cust'] && $_POST['dstendport_cust']) {
		if (is_alias($_POST['dstendport_cust'])) {
			$input_errors[] = 'If you put port alias in Destination port range to: field you must put the same port alias in from: field';
		}
	}
	if ($_POST['dstbeginport_cust'] && $_POST['dstendport_cust']) {
		if (is_alias($_POST['dstendport_cust']) && is_alias($_POST['dstendport_cust']) && $_POST['dstbeginport_cust'] != $_POST['dstendport_cust']) {
			$input_errors[] = 'The same port alias must be used in Destination port range from: and to: fields';
		}
		if ((is_alias($_POST['dstbeginport_cust']) && (!is_alias($_POST['dstendport_cust']) && $_POST['dstendport_cust'] != '')) ||
		    ((!is_alias($_POST['dstbeginport_cust']) && $_POST['dstbeginport_cust'] != '') && is_alias($_POST['dstendport_cust']))) {
			$input_errors[] = 'You cannot specify numbers and port aliases at the same time in Destination port range from: and to: field';
		}
	}

	if ($_POST['src']) {
		$_POST['src'] = trim($_POST['src']);
	}
	if ($_POST['dst']) {
		$_POST['dst'] = trim($_POST['dst']);
	}

	/* if user enters an alias and selects "network" then disallow. */
	if ($_POST['srctype'] == "network") {
		if (is_alias($_POST['src'])) {
			$input_errors[] = gettext("You must specify single host or alias for alias entries.");
		}
	}
	if ($_POST['dsttype'] == "network") {
		if (is_alias($_POST['dst'])) {
			$input_errors[] = gettext("You must specify single host or alias for alias entries.");
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
			$input_errors[] = sprintf(gettext("The Source IP address %s Address Family differs from the destination %s."), $_POST['src'], $_POST['dst']);
		}
		if ((is_ipaddrv6($_POST['src']) || is_ipaddrv6($_POST['dst'])) && ($_POST['ipprotocol'] == "inet")) {
			$input_errors[] = gettext("You can not use IPv6 addresses in IPv4 rules.");
		}
		if ((is_ipaddrv4($_POST['src']) || is_ipaddrv4($_POST['dst'])) && ($_POST['ipprotocol'] == "inet6")) {
			$input_errors[] = gettext("You can not use IPv4 addresses in IPv6 rules.");
		}
	}

	if ((is_ipaddr($_POST['src']) || is_ipaddr($_POST['dst'])) && ($_POST['ipprotocol'] == "inet46")) {
		$input_errors[] = gettext("You can not use a IPv4 or IPv6 address in combined IPv4 + IPv6 rules.");
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

	if ($_POST['ackqueue'] != "") {
		if ($_POST['defaultqueue'] == "") {
			$input_errors[] = gettext("You have to select a queue when you select an acknowledge queue too.");
		} else if ($_POST['ackqueue'] == $_POST['defaultqueue']) {
			$input_errors[] = gettext("Acknowledge queue and Queue cannot be the same.");
		}
	}
	if (isset($_POST['floating']) && $_POST['pdnpipe'] != "" && (empty($_POST['direction']) || $_POST['direction'] == "any")) {
		$input_errors[] = gettext("You can not use limiters in Floating rules without choosing a direction.");
	}
	if (isset($_POST['floating']) && $_POST['gateway'] != "" && (empty($_POST['direction']) || $_POST['direction'] == "any")) {
		$input_errors[] = gettext("You can not use gateways in Floating rules without choosing a direction.");
	}
	if ($_POST['pdnpipe'] && $_POST['pdnpipe'] != "") {
		if ($_POST['dnpipe'] == "") {
			$input_errors[] = gettext("You must select a queue for the In direction before selecting one for Out too.");
		} else if ($_POST['pdnpipe'] == $_POST['dnpipe']) {
			$input_errors[] = gettext("In and Out Queue cannot be the same.");
		} else if ($dnqlist[$_POST['pdnpipe']][0] == "?" && $dnqlist[$_POST['dnpipe']][0] <> "?") {
			$input_errors[] = gettext("You cannot select one queue and one virtual interface for IN and Out. Both must be from the same type.");
		} else if ($dnqlist[$_POST['dnpipe']][0] == "?" && $dnqlist[$_POST['pdnpipe']][0] <> "?") {
			$input_errors[] = gettext("You cannot select one queue and one virtual interface for IN and Out. Both must be from the same type.");
		}
		if ($_POST['direction'] == "out" && empty($_POST['gateway'])) {
			$input_errors[] = gettext("Please select a gateway, normally the interface selected gateway, so the limiters work correctly");
		}
	}
	if (!empty($_POST['ruleid']) && !ctype_digit($_POST['ruleid'])) {
		$input_errors[] = gettext('ID must be an integer');
	}

	if (!in_array($_POST['proto'], array("tcp", "tcp/udp"))) {
		if (!empty($_POST['max-src-conn'])) {
			$input_errors[] = gettext("You can only specify the maximum number of established connections per host (advanced option) for TCP protocol.");
		}
		if (!empty($_POST['max-src-conn-rate']) || !empty($_POST['max-src-conn-rates'])) {
			$input_errors[] = gettext("You can only specify the maximum new connections per host / per second(s) (advanced option) for TCP protocol.");
		}
		if (!empty($_POST['statetimeout'])) {
			$input_errors[] = gettext("You can only specify the state timeout (advanced option) for TCP protocol.");
		}
	}

	if ($_POST['type'] <> "pass") {
		if (!empty($_POST['max'])) {
			$input_errors[] = gettext("You can only specify the maximum state entries (advanced option) for Pass type rules.");
		}
		if (!empty($_POST['max-src-nodes'])) {
			$input_errors[] = gettext("You can only specify the maximum number of unique source hosts (advanced option) for Pass type rules.");
		}
		if (!empty($_POST['max-src-conn'])) {
			$input_errors[] = gettext("You can only specify the maximum number of established connections per host (advanced option) for Pass type rules.");
		}
		if (!empty($_POST['max-src-states'])) {
			$input_errors[] = gettext("You can only specify the maximum state entries per host (advanced option) for Pass type rules.");
		}
		if (!empty($_POST['max-src-conn-rate']) || !empty($_POST['max-src-conn-rates'])) {
			$input_errors[] = gettext("You can only specify the maximum new connections per host / per second(s) (advanced option) for Pass type rules.");
		}
		if (!empty($_POST['statetimeout'])) {
			$input_errors[] = gettext("You can only specify the state timeout (advanced option) for Pass type rules.");
		}
	}

	if ($_POST['statetype'] == "none") {
		if (!empty($_POST['max'])) {
			$input_errors[] = gettext("You cannot specify the maximum state entries (advanced option) if statetype is none and no L7 container is selected.");
		}
		if (!empty($_POST['max-src-nodes'])) {
			$input_errors[] = gettext("You cannot specify the maximum number of unique source hosts (advanced option) if statetype is none and no L7 container is selected.");
		}
		if (!empty($_POST['max-src-conn'])) {
			$input_errors[] = gettext("You cannot specify the maximum number of established connections per host (advanced option) if statetype is none and no L7 container is selected.");
		}
		if (!empty($_POST['max-src-states'])) {
			$input_errors[] = gettext("You cannot specify the maximum state entries per host (advanced option) if statetype is none and no L7 container is selected.");
		}
		if (!empty($_POST['max-src-conn-rate']) || !empty($_POST['max-src-conn-rates'])) {
			$input_errors[] = gettext("You cannot specify the maximum new connections per host / per second(s) (advanced option) if statetype is none and no L7 container is selected.");
		}
		if (!empty($_POST['statetimeout'])) {
			$input_errors[] = gettext("You cannot specify the state timeout (advanced option) if statetype is none and no L7 container is selected.");
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
			$input_errors[] = gettext("If you specify TCP flags that should be set you should specify out of which flags as well.");
		}
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
		}

		if (isset($_POST['ipprotocol'])) {
			$filterent['ipprotocol'] = $_POST['ipprotocol'];
		}

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

		if ($_POST['proto'] == "icmp") {
			if ($filterent['ipprotocol'] == 'inet6' && $_POST['icmp6type']) {
				$filterent['icmptype'] = $_POST['icmp6type'];
			} else if ($filterent['ipprotocol'] != 'inet6' && $_POST['icmptype']) {
				$filterent['icmptype'] = $_POST['icmptype'];
			} else {
				unset($filterent['icmptype']);
			}
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
		strncpy($filterent['descr'], $_POST['descr'], 52);

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
			$a_filter[$id] = $filterent;
		} else {
			$filterent['created'] = make_config_revision_entry();
			if (is_numeric($after)) {
				array_splice($a_filter, $after+1, 0, array($filterent));
			} else {
				$a_filter[] = $filterent;
			}
		}

		filter_rules_sort();

		if (write_config()) {
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
	$tcpflags1 = "<td>set</td>";
	$tcpflags2 = "<td>out of</td>";

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
	$flagtable .= $pconfig['tcpflags_any'] ? 'checked':'' . '/>';
	$flagtable .= '<strong>' . gettext(" Any flags.") . '</strong>';

	return($flagtable);
}

function build_if_list() {
	global $config;

	$iflist = array();

	// add group interfaces
	if (is_array($config['ifgroups']['ifgroupentry'])) {
		foreach ($config['ifgroups']['ifgroupentry'] as $ifgen) {
			if (have_ruleint_access($ifgen['ifname'])) {
				$iflist[$ifgen['ifname']] = $ifgen['ifname'];
			}
		}
	}

	foreach (get_configured_interface_with_descr() as $ifent => $ifdesc) {
		if (have_ruleint_access($ifent)) {
			$iflist[$ifent] = $ifdesc;
		}
	}

	if ($config['l2tp']['mode'] == "server" && have_ruleint_access("l2tp")) {
		$iflist['l2tp'] = 'L2TP VPN';
	}

	if (is_pppoe_server_enabled() && have_ruleint_access("pppoe")) {
		$iflist['pppoe'] = "PPPoE Server";
	}

	// add ipsec interfaces
	if (ipsec_enabled() && have_ruleint_access("enc0")) {
		$iflist["enc0"] = "IPsec";
	}

	// add openvpn/tun interfaces
	if ($config['openvpn']["openvpn-server"] || $config['openvpn']["openvpn-client"]) {
		$iflist["openvpn"] = "OpenVPN";
	}

	return($iflist);
}

$pgtitle = array(gettext("Firewall"), gettext("Rules"), gettext("Edit"));
$shortcut_section = "firewall";

$page_filename = "firewall_rules_edit.php";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form;
$section = new Form_Section('Edit Firewall rule');

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
	'pass' => 'Pass',
	'block' => 'Block',
	'reject' => 'Reject',
);

if ($if == "FloatingRules" || isset($pconfig['floating'])) {
	$values['match'] = 'Match';
}

$section->addInput(new Form_Select(
	'type',
	'Action',
	$pconfig['type'],
	$values
))->setHelp('Choose what to do with packets that match the criteria specified '.
	'below.<br/>Hint: the difference between block and reject is that with '.
	'reject, a packet (TCP RST or ICMP port unreachable for UDP) is returned '.
	'to the sender, whereas with block the packet is dropped silently. In '.
	'either case, the original packet is discarded.');

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
	))->setHelp('Set this option if you need to apply this action to traffic that '.
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
		'You cannot edit the interface, protocol, source, or destination of associated filter rules.'.
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
		'Interface',
		explode(",", $pconfig['interface']),
		build_if_list(),
		true
	))->setHelp('Choose the interface(s) for this rule.');
} else {
	$section->addInput($input = new Form_Select(
		'interface',
		'Interface',
		$pconfig['interface'],
		build_if_list()
	))->setHelp('Choose the interface from which packets must come to match this rule.');
}

if ($if == "FloatingRules" || isset($pconfig['floating'])) {
	$section->addInput(new Form_Select(
		'direction',
		'Direction',
		$pconfig['direction'],
		array(
			'any' => 'any',
			'in' => 'in',
			'out' => 'out',
		)
	));

	$section->addInput(new Form_Input(
		'floating',
		'Floating',
		'hidden',
		'floating'
	));
}

$section->addInput(new Form_Select(
	'ipprotocol',
	'Address Family',
	$pconfig['ipprotocol'],
	array(
		'inet' => 'IPv4',
		'inet6' => 'IPv6',
		'inet46' => 'IPv4+IPv6',
	)
))->setHelp('Select the Internet Protocol version this rule applies to');

$section->addInput(new Form_Select(
	'proto',
	'Protocol',
	$pconfig['proto'],
	array(
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
		'any' => 'any',
		'carp' => 'CARP',
		'pfsync' => 'PFSYNC',
	)
))->setHelp('Choose which IP protocol this rule should match.');

$section->addInput(new Form_Select(
	'icmptype',
	'ICMP type',
	$pconfig['icmptype'],
	$icmptypes
))->setHelp('If you selected ICMP for the protocol above, you may specify an ICMP type here.');

$section->addInput(new Form_Select(
	'icmp6type',
	'ICMPv6 type',
	$pconfig['icmptype'],
	$icmp6types
))->setHelp('If you selected ICMP for the protocol above, you may specify an ICMP type here.');

$form->add($section);

// Source and destination share a lot of logic. Loop over the two
// ToDo: Unfortunately they seem to differ more than they share. This needs to be unrolled
foreach (['src' => 'Source', 'dst' => 'Destination'] as $type => $name) {
	$section = new Form_Section($name);

	$group = new Form_Group($name);
	$group->add(new Form_Checkbox(
		$type .'not',
		$name .' not',
		'Invert match.',
		$pconfig[$type.'not']
	))->setWidth(2);

	// The rule type dropdown on the GUI can be one of the special names like
	// "any" "LANnet" "LAN address"... or "Single host or alias" or "Network"
	if (is_specialnet($pconfig[$type])) {
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
		'any' => 'any',
		'single' => 'Single host or alias',
		'network' => 'Network',
	);

	if($type == 'dst') {
		$ruleValues['(self)'] = "This firewall (self)";
	}

	if (isset($a_filter[$id]['floating']) || $if == "FloatingRules") {
		$ruleValues['(self)'] = 'This Firewall (self)';
	}
	if (have_ruleint_access("pppoe")) {
		$ruleValues['pppoe'] = 'PPPoE clients';
	}
	if (have_ruleint_access("l2tp")) {
		$ruleValues['l2tp'] = 'L2TP clients';
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
		$pconfig[$type]
	))->addMask($type .'mask', $pconfig[$type.'mask'])->setPattern('[a-zA-Z0-9\_\.\:]+');

	$section->add($group);

	if ($type == 'src') {
		$section->addInput(new Form_Button(
			'btnsrcadv',
			'Show advanced'
		))->removeClass('btn-primary');
	}

	$portValues = ['' => '(other)', 'any' => 'any'];

	foreach ($wkports as $port => $portName) {
		$portValues[$port] = $portName.' ('. $port .')';
	}

	$group = new Form_Group($name .' port range');

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


	if ($type == 'src')
		$group->setHelp('Specify the source port or port range for this rule. This is '.
			'usually random and almost never equal to the destination port range (and '.
			'should usually be <b>any</b>). You can leave the "To" field '.
			'empty if you only want to filter a single port.');
	else
		$group->setHelp('Specify the destination port or port range for this rule. ' .
			'You can leave the "To" field empty if you only want to filter a '.
			'single port.');

	$group->addClass(($type == 'src') ? 'srcprtr':'dstprtr');
	$section->add($group);
	$form->add($section);
}

$section = new Form_Section('Extra options');
$section->addInput(new Form_Checkbox(
	'log',
	'Log',
	'Log packets that are handled by this rule',
	$pconfig['log']
))->setHelp('Hint: the firewall has limited local log space. Don\'t turn on logging '.
	'for everything. If you want to do a lot of logging, consider using a remote '.
	'syslog server (see the <a href="status_logs_settings.php">Status: System logs: '.
	'Settings</a> page).');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('You may enter a description here for your reference.');

$adv_open = is_aoadv_used($pconfig);

$btnadvanced = new Form_Button(
	'toggle-advanced',
	'Advanced options'
);

$btnadvanced->removeClass('btn-primary')->addClass('btn-default');

if (!$adv_open) {
	$section->addInput(new Form_StaticText(
		null,
		$btnadvanced
	));
}

$form->add($section);

$section = new Form_Section('Advanced options');
$section->addClass('advanced-options');

$section->addInput(new Form_Select(
	'os',
	'Source OS',
	(empty($pconfig['os']) ? '':$pconfig['os']),
	['' => 'Any'] + array_combine($ostypes, $ostypes)
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
))->setHelp('You can mark a packet matching this rule and use this mark to match '.
	'on other NAT/filter rules. It is called <b>Policy filtering</b>.');

$section->addInput(new Form_Input(
	'tagged',
	'Tagged',
	'text',
	$pconfig['tagged']
))->setHelp('You can match packet on a mark placed before on another rule.');

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
))->setHelp('Maximum new connections per host (TCP only)');

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
	['min' => 1, 'max' => 3600]
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
	(isset($pconfig['statetype'])) ? "keep state":$pconfig['statetype'],
	array(
		'keep state' => 'Keep',
		'sloppy state' => 'Sloppy',
		'synproxy state' => 'Synproxy',
		'none' => 'None',
	)
))->setHelp('Select which type of state tracking mechanism you would like to use.  If in doubt, use keep state' . '<br />' .
			'<span></span>');

$section->addInput(new Form_Checkbox(
	'nosync',
	'No XMLRPC Sync',
	'Prevent the rule on Master from automatically syncing to other CARP members',
	$pconfig['nosync']
))->setHelp('This does NOT prevent the rule from being overwritten on Slave.');

$vlanprio = array("" => "none", "be" => "BE", "bk" => "BK", "ee" => "EE", "ca" => "CA", "vi" => "VI", "vo" => "VO", "ic" => "IC", "nc" => "NC");

$section->addInput(new Form_Select(
	'vlanprio',
	'VLAN Prio',
	$pconfig['vlanprio'],
	$vlanprio
))->setHelp('Choose 802.1p priority to match on');

$section->addInput(new Form_Select(
	'vlanprioset',
	'VLAN Prio Set',
	$pconfig['vlanprioset'],
	$vlanprio
))->setHelp('Choose 802.1p priority to apply');

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
	['' => 'none'] + array_combine($schedules, $schedules)
))->setHelp('Leave as \'none\' to leave the rule enabled all the time');

$gateways = array("" => 'default');
foreach (return_gateways_array() as $gwname => $gw) {
	if (($pconfig['ipprotocol'] == "inet46")) {
		continue;
	}
	if (($pconfig['ipprotocol'] == "inet6") && !(($gw['ipprotocol'] == "inet6") || (is_ipaddrv6($gw['gateway'])))) {
		continue;
	}
	if (($pconfig['ipprotocol'] == "inet") && !(($gw['ipprotocol'] == "inet") || (is_ipaddrv4($gw['gateway'])))) {
		continue;
	}
	if ($gw == "") {
		continue;
	}

	$gateways[ $gwname ] = $gw['name'] . (empty($gw['gateway'])? '' : ' - '. $gateway_addr_str);
}

foreach ((array)$a_gatewaygroups as $gwg_name => $gwg_data) {
	if ((empty($pconfig['ipprotocol'])) || ($pconfig['ipprotocol'] == $gwg_data['ipprotocol'])) {
		$gateways[ $gwg_name ] = $gwg_name;
	}
}

$section->addInput(new Form_Select(
	'gateway',
	'Gateway',
	$pconfig['gateway'],
	$gateways
))->setHelp('Leave as \'default\' to use the system routing table. Or choose a '.
	'gateway to utilize policy based routing.');

$group = new Form_Group('In / Out pipe');

$group->add(new Form_Select(
	'dnpipe',
	'DNpipe',
	(isset($pconfig['dnpipe'])) ? $pconfig['dnpipe']:"",
	array('' => 'none') + array_combine(array_keys($dnqlist), array_keys($dnqlist))
));

$group->add(new Form_Select(
	'pdnpipe',
	'PDNpipe',
	(isset($pconfig['pdnpipe'])) ? $pconfig['pdnpipe']:"",
	array('' => 'none') + array_combine(array_keys($dnqlist), array_keys($dnqlist))
));

$section->add($group)->setHelp('Choose the Out queue/Virtual interface only if '.
	'you have also selected In. The Out selection is applied to traffic leaving '.
	'the interface where the rule is created, the In selection is applied to traffic coming '.
	'into the chosen interface.<br />If you are creating a floating rule, if the '.
	'direction is In then the same rules apply, if the direction is Out the '.
	'selections are reversed, Out is for incoming and In is for outgoing.'
);

$group = new Form_Group('Ackqueue / Queue');

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

$section->add($group)->setHelp('Choose the Acknowledge Queue only if you have '.
	'selected Queue.'
);

$has_created_time = (isset($a_filter[$id]['created']) && is_array($a_filter[$id]['created']));
$has_updated_time = (isset($a_filter[$id]['updated']) && is_array($a_filter[$id]['updated']));


if ($has_created_time || $has_updated_time) {
	$form->add($section);
	$section = new Form_Section('Rule Information');

	if ($has_created_time) {
		$section->addInput(new Form_StaticText(
			'Created',
			date('n/j/y H:i:s', $a_filter[$id]['created']['time']) . gettext(' by ') .'<b>'. $a_filter[$id]['created']['username'] .'</b>'
		));
	}

	if ($has_updated_time) {
		$section->addInput(new Form_StaticText(
			'Updated',
			date('n/j/y H:i:s', $a_filter[$id]['updated']['time']) . gettext(' by ') .'<b>'. $a_filter[$id]['updated']['username'] .'</b>'
		));
	}
}

$form->add($section);
echo $form;
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	var portsenabled = 1;
	var editenabled = 1;
	var optionsvisible = 0;
	var srcportsvisible = 0;

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
	}

	function typesel_change() {
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

	function proto_change() {
		if ($('#proto').find(":selected").index() < 3) {
			portsenabled = 1;
			hideClass('tcpflags', false);
		} else {
			portsenabled = 0;
			hideClass('tcpflags', true);
		}

		// Disable OS if the proto is not TCP.
		if ($('#proto').find(":selected").index() < 1) {
			disableInput('os', false);
		} else {
			disableInput('os', true);
		}

		if ($('#proto').find(":selected").index() == 3) {
			disableInput('icmptype', false);
			disableInput('icmp6type', false);
		} else {
			disableInput('icmptype', true);
			disableInput('icmp6type', true);
		}

		ext_change();

		if ($('#proto').find(":selected").index() == 3 || $('#proto').find(":selected").index() == 4) {
			if ($('#ipprotocol').find(":selected").index() == 0) { // IPv4
				hideInput('icmptype', false);
				hideInput('icmp6type', true);
			} else if ($('#ipprotocol').find(":selected").index() == 1) { // IPv6
				hideInput('icmptype', true);
				hideInput('icmp6type', false);
			} else { // IPv4 + IPv6
				hideInput('icmptype', true);
				hideInput('icmp6type', true);
			}
		} else {
			hideInput('icmptype', true);
			hideInput('icmp6type', true);
		}

		if ($('#proto').find(":selected").index() <= 2) {
			hideClass('dstprtr', false);
			hideClass('srcprtr', !srcportsvisible);
			$("#btnsrcadv").prop('value', srcportsvisible ? 'Hide advanced':'Show advanced');
		} else {
			hideClass('srcprtr', true);
			hideClass('dstprtr', true);
			srcportsvisible = 0;
			$("#btnsrcadv").prop('value', srcportsvisible ? 'Hide advanced':'Show advanced');
		}
	}

	function src_rep_change() {
		$('#srcendport').prop("selectedIndex", $('#srcbeginport').find(":selected").index());
	}

	function dst_rep_change() {
		$('#dstendport').prop("selectedIndex", $('#dstbeginport').find(":selected").index());
	}

	// On initial page load
	proto_change();

	ext_change();

	typesel_change();

	hideClass('advanced-options',  ! "<?=$adv_open?>");
	hideClass('srcportrange', true);

	<?php if ((!empty($pconfig['srcbeginport']) && $pconfig['srcbeginport'] != "any") || (!empty($pconfig['srcendport']) && $pconfig['srcendport'] != "any")): ?>
		srcportsvisible = true;
		show_source_port_range();
		hideInput('btnsrcadv', true);
	<?php endif; ?>

	// Make it a regular button, not a submit
	$('#toggle-advanced').prop('type','button');
	$("#btnsrcadv").prop('type','button');

	// on click . .
	$('#srcbeginport').on('change', function() {
		src_rep_change();
		ext_change();
	});

	$('#btnsrcadv').click(function() {
		srcportsvisible = !srcportsvisible;
		show_source_port_range();
		$("#btnsrcadv").prop('value', srcportsvisible ? 'Hide advanced':'Show advanced');
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

	$('#srctype').on('change', function() {
		typesel_change();
	});

	$('#dsttype').on('change', function() {
		typesel_change();
	});

	$('#proto').on('change', function() {
		proto_change();
	});

	$('#ipprotocol').on('change', function() {
		proto_change();
	});

	$('#toggle-advanced').click(function() {
		optionsvisible = 1;
		hideClass('advanced-options', false);
		if ($('#tcpflags_any').prop('checked')) {
			$('.table-flags').addClass('hidden');
		}
	});

	$('#tcpflags_any').click(function () {
		if (this.checked) {
			$('.table-flags').addClass('hidden');
		} else {
			$('.table-flags').removeClass('hidden');
		}
	});

	// Change help text based on the selector value
	function setHelpText(id, text) {
		$('#' + id).parent().parent('div').find('span').find('span').html(text);
	}

	function setOptText(target, val) {
		var dispstr = '<span class="text-success">';

		if (val == 'keep state') {
			dispstr += 'Keep: works with all IP protocols';
		} else if (val == 'sloppy state') {
			dispstr += 'Sloppy: works with all IP protocols';
		} else if (val == 'synproxy state') {
			dispstr += 'Synproxy: proxies incoming TCP connections to help protect servers from spoofed TCP SYN floods. This option includes the functionality of keep state and modulate state combined';
		} else if (val == 'none') {
			dispstr += 'None: Do not use state mechanisms to keep track.  This is only useful if you\'re doing advanced queueing in certain situations';
		}

		dispstr += '</span>';
		setHelpText(target, dispstr);
	}

	// ---------- Click checkbox handlers ---------------------------------------------------------

	$('#statetype').on('change', function() {
		setOptText('statetype', this.value);
	});

	// ---------- On initial page load ------------------------------------------------------------

	setOptText('statetype', $('#statetype').val())

	// ---------- Autocomplete --------------------------------------------------------------------

	var addressarray = <?= json_encode(get_alias_list(array("host", "network", "openvpn", "urltable"))) ?>;
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
