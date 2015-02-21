<?php
/* $Id$ */
/*
	firewall_rules_edit.php
	part of pfSense (https://www.pfsense.org)
	Copyright (C) 2005 Scott Ullrich (sullrich@gmail.com)
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
/*
	pfSense_MODULE:	filter
*/

##|+PRIV
##|*IDENT=page-firewall-rules-edit
##|*NAME=Firewall: Rules: Edit page
##|*DESCR=Allow access to the 'Firewall: Rules: Edit' page.
##|*MATCH=firewall_rules_edit.php*
##|-PRIV

require("guiconfig.inc");
require_once("filter.inc");
require("shaper.inc");

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/firewall_rules.php');

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
		($rule_config['statetimeout'] != ""))
		return true;
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

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (is_numericint($_GET['after']) || $_GET['after'] == "-1")
	$after = $_GET['after'];
if (isset($_POST['after']) && (is_numericint($_POST['after']) || $_POST['after'] == "-1"))
	$after = $_POST['after'];

if (isset($_GET['dup']) && is_numericint($_GET['dup'])) {
	$id = $_GET['dup'];
	$after = $_GET['dup'];
}

if (isset($id) && $a_filter[$id]) {
	$pconfig['interface'] = $a_filter[$id]['interface'];

	if (isset($a_filter[$id]['id']))
		$pconfig['ruleid'] = $a_filter[$id]['id'];

	if ( isset($a_filter[$id]['created']) && is_array($a_filter[$id]['created']) )
		$pconfig['created'] = $a_filter[$id]['created'];

	if ( isset($a_filter[$id]['updated']) && is_array($a_filter[$id]['updated']) )
		$pconfig['updated'] = $a_filter[$id]['updated'];

	if (!isset($a_filter[$id]['type']))
		$pconfig['type'] = "pass";
	else
		$pconfig['type'] = $a_filter[$id]['type'];

	if (isset($a_filter[$id]['floating']) || $if == "FloatingRules") {
		$pconfig['floating'] = $a_filter[$id]['floating'];
		if (isset($a_filter[$id]['interface']) && $a_filter[$id]['interface'] != "")
			$pconfig['interface'] = $a_filter[$id]['interface'];
	}

	if (isset($a_filter['floating']))
		$pconfig['floating'] = "yes";

	if (isset($a_filter[$id]['direction']))
		$pconfig['direction'] = $a_filter[$id]['direction'];

	if (isset($a_filter[$id]['ipprotocol']))
		$pconfig['ipprotocol'] = $a_filter[$id]['ipprotocol'];

	if (isset($a_filter[$id]['protocol']))
		$pconfig['proto'] = $a_filter[$id]['protocol'];
	else
		$pconfig['proto'] = "any";

	if ($a_filter[$id]['protocol'] == "icmp")
		$pconfig['icmptype'] = $a_filter[$id]['icmptype'];

	address_to_pconfig($a_filter[$id]['source'], $pconfig['src'],
		$pconfig['srcmask'], $pconfig['srcnot'],
		$pconfig['srcbeginport'], $pconfig['srcendport']);

	if($a_filter[$id]['os'] != "")
		$pconfig['os'] = $a_filter[$id]['os'];

	address_to_pconfig($a_filter[$id]['destination'], $pconfig['dst'],
		$pconfig['dstmask'], $pconfig['dstnot'],
		$pconfig['dstbeginport'], $pconfig['dstendport']);

	if ($a_filter[$id]['dscp'] != "")
		$pconfig['dscp'] = $a_filter[$id]['dscp'];

	$pconfig['disabled'] = isset($a_filter[$id]['disabled']);
	$pconfig['log'] = isset($a_filter[$id]['log']);
	$pconfig['descr'] = $a_filter[$id]['descr'];

	if (isset($a_filter[$id]['tcpflags_any']))
		$pconfig['tcpflags_any'] = true;
	else {
		if (isset($a_filter[$id]['tcpflags1']) && $a_filter[$id]['tcpflags1'] != "")
			$pconfig['tcpflags1'] = $a_filter[$id]['tcpflags1'];
		if (isset($a_filter[$id]['tcpflags2']) && $a_filter[$id]['tcpflags2'] != "")
			$pconfig['tcpflags2'] = $a_filter[$id]['tcpflags2'];
	}

	if (isset($a_filter[$id]['tag']) && $a_filter[$id]['tag'] != "")
		$pconfig['tag'] = $a_filter[$id]['tag'];
	if (isset($a_filter[$id]['tagged']) && $a_filter[$id]['tagged'] != "")
		$pconfig['tagged'] = $a_filter[$id]['tagged'];
	if (isset($a_filter[$id]['quick']) && $a_filter[$id]['quick'])
		$pconfig['quick'] = $a_filter[$id]['quick'];
	if (isset($a_filter[$id]['allowopts']))
		$pconfig['allowopts'] = true;
	if (isset($a_filter[$id]['disablereplyto']))
		$pconfig['disablereplyto'] = true;

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
	$pconfig['l7container'] = (($a_filter[$id]['l7container'] == "none") ? '' : $a_filter[$id]['l7container']);

	//schedule support
	$pconfig['sched'] = (($a_filter[$id]['sched'] == "none") ? '' : $a_filter[$id]['sched']);
	$pconfig['vlanprio'] = (($a_filter[$id]['vlanprio'] == "none") ? '' : $a_filter[$id]['vlanprio']);
	$pconfig['vlanprioset'] = (($a_filter[$id]['vlanprioset'] == "none") ? '' : $a_filter[$id]['vlanprioset']);
	if (!isset($_GET['dup']) || !is_numericint($_GET['dup']))
		$pconfig['associated-rule-id'] = $a_filter[$id]['associated-rule-id'];

	$pconfig['tracker'] = $a_filter[$id]['tracker'];

} else {
	/* defaults */
	if ($_GET['if'])
		$pconfig['interface'] = $_GET['if'];
	$pconfig['type'] = "pass";
	$pconfig['src'] = "any";
	$pconfig['dst'] = "any";
}
/* Allow the FloatingRules to work */
$if = $pconfig['interface'];

if (isset($_GET['dup']) && is_numericint($_GET['dup']))
	unset($id);

read_altq_config(); /* XXX: */
$qlist =& get_unique_queue_list();
read_dummynet_config(); /* XXX: */
$dnqlist =& get_unique_dnqueue_list();
read_layer7_config();
$l7clist =& get_l7_unique_list();
$a_gatewaygroups = return_gateway_groups_array();

if ($_POST) {
	unset($input_errors);

	if( isset($a_filter[$id]['associated-rule-id']) ) {
		$_POST['protocol'] = $pconfig['proto'];
		if ($pconfig['proto'] == "icmp")
			$_POST['icmp-type'] = $pconfig['icmptype'];
	}

	if (($_POST['tcp-ip-version'] != "") && ($_POST['gateway'] != "")) {
		if(is_array($config['gateways']['gateway_group'])) {
			foreach($config['gateways']['gateway_group'] as $gw_group) {
				if($gw_group['name'] == $_POST['gateway']) {
					$family = $a_gatewaygroups[$_POST['gateway']]['ipprotocol'];
					if($_POST['tcp-ip-version'] == $family) {
						continue;
					}
					if(($_POST['tcp-ip-version'] == "inet46") && ($_POST['tcp-ip-version'] != $family)) {
						$input_errors[] = gettext("You can not assign a gateway to a rule that applies to IPv4 and IPv6");
					}
					if(($_POST['tcp-ip-version'] == "inet6") && ($_POST['tcp-ip-version'] != $family)) {
						$input_errors[] = gettext("You can not assign an IPv4 gateway group on IPv6 Address Family rule");
					}
					if(($_POST['tcp-ip-version'] == "inet") && ($_POST['tcp-ip-version'] != $family)) {
						$input_errors[] = gettext("You can not assign an IPv6 gateway group on IPv4 Address Family rule");
					}
				}
			}
		}
	}
	if (($_POST['tcp-ip-version'] != "") && ($_POST['gateway'] != "") && (is_ipaddr(lookup_gateway_ip_by_name($_POST['gateway'])))) {
		if(($_POST['tcp-ip-version'] == "inet46") && ($_POST['gateway'] != "")) {
			$input_errors[] = gettext("You can not assign a gateway to a rule that applies to IPv4 and IPv6");
		}
		if(($_POST['tcp-ip-version'] == "inet6") && (!is_ipaddrv6(lookup_gateway_ip_by_name($_POST['gateway'])))) {
			$input_errors[] = gettext("You can not assign an IPv4 Gateway to an IPv6 Filter rule");
		}
		if(($_POST['tcp-ip-version'] == "inet") && (!is_ipaddrv4(lookup_gateway_ip_by_name($_POST['gateway'])))) {
			$input_errors[] = gettext("You can not assign an IPv6 Gateway to an IPv4 Filter rule");
		}
	}

	if (($_POST['protocol'] != "tcp") && ($_POST['protocol'] != "udp") && ($_POST['protocol'] != "tcp/udp") && ($_POST['protocol'] != "icmp")) {
		if($_POST['tcp-ip-version'] == "inet46")
			$input_errors[] =  gettext("You can not assign a protocol other than ICMP, TCP, UDP or TCP/UDP to a rule that applies to IPv4 and IPv6");
	}
	if (($_POST['protocol'] == "icmp") && ($_POST['icmp-type'] != "")){
		if($_POST['tcp-ip-version'] == "inet46")
			$input_errors[] =  gettext("You can not assign a ICMP type to a rule that applies to IPv4 and IPv6");
	}

	if (($_POST['protocol'] != "tcp") && ($_POST['protocol'] != "udp") && ($_POST['protocol'] != "tcp/udp")) {
		$_POST['source-port-begin'] = 0;
		$_POST['source-port-end'] = 0;
		$_POST['destination-port-begin'] = 0;
		$_POST['destination-port-end'] = 0;
	} else {
		if ($_POST['source-port-begin-custom'] && !$_POST['source-port-begin'])
			$_POST['source-port-begin'] = trim($_POST['source-port-begin-custom']);
		if ($_POST['source-port-end-custom'] && !$_POST['source-port-end'])
			$_POST['source-port-end'] = trim($_POST['source-port-end-custom']);
		if ($_POST['source-port-begin'] == "any") {
			$_POST['source-port-begin'] = 0;
			$_POST['source-port-end'] = 0;
		} else {
			if (!$_POST['source-port-end'])
				$_POST['source-port-end'] = $_POST['source-port-begin'];
		}
		if ($_POST['source-port-end'] == "any")
			$_POST['source-port-end'] = $_POST['source-port-begin'];

		if ($_POST['destination-port-begin-custom'] && !$_POST['destination-port-begin'])
			$_POST['destination-port-begin'] = trim($_POST['destination-port-begin-custom']);
		if ($_POST['destination-port-end-custom'] && !$_POST['destination-port-end'])
			$_POST['destination-port-end'] = trim($_POST['destination-port-end-custom']);

		if ($_POST['destination-port-begin'] == "any") {
			$_POST['destination-port-begin'] = 0;
			$_POST['destination-port-end'] = 0;
		} else {
			if (!$_POST['destination-port-end'])
				$_POST['destination-port-end'] = $_POST['destination-port-begin'];
		}
		if ($_POST['destination-port-end'] == "any")
			$_POST['destination-port-end'] = $_POST['destination-port-begin'];
	}

	if (is_specialnet($_POST['source-type'])) {
		$_POST['source-address'] = $_POST['source-type'];
		$_POST['source-addressmask'] = 0;
	} else if ($_POST['source-type'] == "single") {
		if (is_ipaddrv6($_POST['source-address']))
			$_POST['source-addressmask'] = 128;
		else
			$_POST['source-addressmask'] = 32;
	}
	if (is_specialnet($_POST['destination-type'])) {
		$_POST['destination-address'] = $_POST['destination-type'];
		$_POST['source-addressmask'] = 0;
	}  else if ($_POST['destination-type'] == "single") {
		if (is_ipaddrv6($_POST['destination-address']))
			$_POST['source-addressmask'] = 128;
		else
			$_POST['source-addressmask'] = 32;
	}

	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "type proto");
	if ( isset($a_filter[$id]['associated-rule-id'])===false ) {
		$reqdfields[] = "src";
		$reqdfields[] = "dst";
	}
	$reqdfieldsn = explode(",", "Type,Protocol");
	if ( isset($a_filter[$id]['associated-rule-id'])===false ) {
		$reqdfieldsn[] = "Source";
		$reqdfieldsn[] = "Destination";
	}

	if($_POST['state-type'] == "modulate state" or $_POST['state-type'] == "synproxy state") {
		if( $_POST['protocol'] != "tcp" )
			$input_errors[] = sprintf(gettext("%s is only valid with protocol tcp."),$_POST['state-type']);
		if(($_POST['state-type'] == "synproxy state") && ($_POST['gateway'] != ""))
			$input_errors[] = sprintf(gettext("%s is only valid if the gateway is set to 'default'."),$_POST['state-type']);
	}

	if ( isset($a_filter[$id]['associated-rule-id'])===false &&
	(!(is_specialnet($_POST['source-type']) || ($_POST['source-type'] == "single"))) ) {
		$reqdfields[] = "srcmask";
		$reqdfieldsn[] = "Source bit count";
	}
	if ( isset($a_filter[$id]['associated-rule-id'])===false &&
	(!(is_specialnet($_POST['destination-type']) || ($_POST['destination-type'] == "single"))) ) {
		$reqdfields[] = "dstmask";
		$reqdfieldsn[] = gettext("Destination bit count");
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!$_POST['source-port-begin']) {
		$_POST['source-port-begin'] = 0;
		$_POST['source-port-end'] = 0;
	}
	if (!$_POST['destination-port-begin']) {
		$_POST['destination-port-begin'] = 0;
		$_POST['destination-port-end'] = 0;
	}

	if ($_POST['source-port-begin'] && !is_portoralias($_POST['source-port-begin']))
		$input_errors[] = sprintf(gettext("%s is not a valid start source port. It must be a port alias or integer between 1 and 65535."),$_POST['source-port-begin']);
	if ($_POST['source-port-end'] && !is_portoralias($_POST['source-port-end']))
			$input_errors[] = sprintf(gettext("%s  is not a valid end source port. It must be a port alias or integer between 1 and 65535."),$_POST['source-port-end']);
	if ($_POST['destination-port-begin'] && !is_portoralias($_POST['destination-port-begin']))
			$input_errors[] = sprintf(gettext("%s is not a valid start destination port. It must be a port alias or integer between 1 and 65535."),$_POST['destination-port-begin']);
	if ($_POST['destination-port-end'] && !is_portoralias($_POST['destination-port-end']))
			$input_errors[] = sprintf(gettext("%s is not a valid end destination port. It must be a port alias or integer between 1 and 65535."),$_POST['destination-port-end']);
	if ( !$_POST['source-port-begin-custom'] && $_POST['source-port-end-custom'])
		if (is_alias($_POST['source-port-end-custom']))
			$input_errors[] = 'If you put port alias in Source port range to: field you must put the same port alias in from: field';
	if ( $_POST['source-port-begin-custom'] && $_POST['source-port-end-custom']){
		if (is_alias($_POST['source-port-end-custom']) && is_alias($_POST['source-port-end-custom']) && $_POST['source-port-begin-custom'] != $_POST['destination-port-begin-custom'])
			$input_errors[] = 'The same port alias must be used in Source port range from: and to: fields';
		if ((is_alias($_POST['source-port-begin-custom']) && (!is_alias($_POST['source-port-end-custom']) && $_POST['source-port-end-custom']!='')) ||
			((!is_alias($_POST['source-port-begin-custom']) && $_POST['source-port-begin-custom']!='') && is_alias($_POST['source-port-end-custom'])))
			$input_errors[] = 'You cannot specify numbers and port aliases at the same time in Source port range from: and to: field';
	}
	if ( !$_POST['destination-port-begin-custom'] && $_POST['destination-port-end-custom'])
		if (is_alias($_POST['destination-port-end-custom']))
			$input_errors[] = 'If you put port alias in Destination port range to: field you must put the same port alias in from: field';
	if ( $_POST['destination-port-begin-custom'] && $_POST['destination-port-end-custom']){
		if (is_alias($_POST['destination-port-end-custom']) && is_alias($_POST['destination-port-end-custom']) && $_POST['source-port-begin-custom'] != $_POST['destination-port-end-custom'])
			$input_errors[] = 'The same port alias must be used in Destination port range from: and to: fields';
		if ((is_alias($_POST['destination-port-begin-custom']) && (!is_alias($_POST['destination-port-end-custom']) && $_POST['destination-port-end-custom']!='')) ||
			((!is_alias($_POST['destination-port-begin-custom']) && $_POST['destination-port-begin-custom']!='') && is_alias($_POST['destination-port-end-custom'])))
			$input_errors[] = 'You cannot specify numbers and port aliases at the same time in Destination port range from: and to: field';
	}

	if ($_POST['source-address'])
		$_POST['source-address'] = trim($_POST['source-address']);
	if ($_POST['destination-address'])
		$_POST['destination-address'] = trim($_POST['destination-address']);

	/* if user enters an alias and selects "network" then disallow. */
	if($_POST['source-type'] == "network") {
		if(is_alias($_POST['source-address']))
			$input_errors[] = gettext("You must specify single host or alias for alias entries.");
	}
	if($_POST['destination-type'] == "network") {
		if(is_alias($_POST['destination-address']))
			$input_errors[] = gettext("You must specify single host or alias for alias entries.");
	}

	if (!is_specialnet($_POST['source-type'])) {
		if (($_POST['source-address'] && !is_ipaddroralias($_POST['source-address']))) {
			$input_errors[] = sprintf(gettext("%s is not a valid source IP address or alias."),$_POST['source-address']);
		}
		if (($_POST['source-addressmask'] && !is_numericint($_POST['source-addressmask']))) {
			$input_errors[] = gettext("A valid source bit count must be specified.");
		}
	}
	if (!is_specialnet($_POST['destination-type'])) {
		if (($_POST['destination-address'] && !is_ipaddroralias($_POST['destination-address']))) {
			$input_errors[] = sprintf(gettext("%s is not a valid destination IP address or alias."),$_POST['destination-address']);
		}
		if (($_POST['source-addressmask'] && !is_numericint($_POST['source-addressmask']))) {
			$input_errors[] = gettext("A valid destination bit count must be specified.");
		}
	}
	if((is_ipaddr($_POST['source-address']) && is_ipaddr($_POST['destination-address']))) {
		if(!validate_address_family($_POST['source-address'], $_POST['destination-address']))
			$input_errors[] = sprintf(gettext("The Source IP address %s Address Family differs from the destination %s."), $_POST['source-address'], $_POST['destination-address']);
		if((is_ipaddrv6($_POST['source-address']) || is_ipaddrv6($_POST['destination-address'])) && ($_POST['tcp-ip-version'] == "inet"))
			$input_errors[] = gettext("You can not use IPv6 addresses in IPv4 rules.");
		if((is_ipaddrv4($_POST['source-address']) || is_ipaddrv4($_POST['destination-address'])) && ($_POST['tcp-ip-version'] == "inet6"))
			$input_errors[] = gettext("You can not use IPv4 addresses in IPv6 rules.");
	}

	if((is_ipaddr($_POST['source-address']) || is_ipaddr($_POST['destination-address'])) && ($_POST['tcp-ip-version'] == "inet46"))
		$input_errors[] = gettext("You can not use a IPv4 or IPv6 address in combined IPv4 + IPv6 rules.");

	if ($_POST['source-port-begin'] > $_POST['source-port-end']) {
		/* swap */
		$tmp = $_POST['source-port-end'];
		$_POST['source-port-end'] = $_POST['source-port-begin'];
		$_POST['source-port-begin'] = $tmp;
	}
	if ($_POST['destination-port-begin'] > $_POST['destination-port-end']) {
		/* swap */
		$tmp = $_POST['destination-port-end'];
		$_POST['destination-port-end'] = $_POST['destination-port-begin'];
		$_POST['destination-port-begin'] = $tmp;
	}
	if ($_POST['source-os']) {
		if( $_POST['protocol'] != "tcp" )
			$input_errors[] = gettext("OS detection is only valid with protocol tcp.");
		if (!in_array($_POST['source-os'], $ostypes))
			$input_errors[] = gettext("Invalid OS detection selection. Please select a valid OS.");
	}

	if ($_POST['ackqueue'] != "") {
		if ($_POST['default-queue'] == "" )
			$input_errors[] = gettext("You have to select a queue when you select an acknowledge queue too.");
		else if ($_POST['ackqueue'] == $_POST['default-queue'])
			$input_errors[] = gettext("Acknowledge queue and Queue cannot be the same.");
	}
	if (isset($_POST['floating']) && $_POST['pdnpipe'] != "" && (empty($_POST['tcp-ip-version']) || $_POST['tcp-ip-version'] == "any"))
		$input_errors[] = gettext("You can not use limiters in Floating rules without choosing a direction.");
	if (isset($_POST['floating']) && $_POST['gateway'] != "" && (empty($_POST['tcp-ip-version']) || $_POST['tcp-ip-version'] == "any"))
		$input_errors[] = gettext("You can not use gateways in Floating rules without choosing a direction.");
	if ($_POST['pdnpipe'] && $_POST['pdnpipe'] != "") {
		if ($_POST['dnpipe'] == "" )
			$input_errors[] = gettext("You must select a queue for the In direction before selecting one for Out too.");
		else if ($_POST['pdnpipe'] == $_POST['dnpipe'])
			$input_errors[] = gettext("In and Out Queue cannot be the same.");
		else if ($dnqlist[$_POST['pdnpipe']][0] == "?" && $dnqlist[$_POST['dnpipe']][0] != "?")
			$input_errors[] = gettext("You cannot select one queue and one virtual interface for IN and Out. Both must be from the same type.");
		else if ($dnqlist[$_POST['dnpipe']][0] == "?" && $dnqlist[$_POST['pdnpipe']][0] != "?")
			$input_errors[] = gettext("You cannot select one queue and one virtual interface for IN and Out. Both must be from the same type.");
		if ($_POST['direction'] == "out" && empty($_POST['gateway']))
			$input_errors[] = gettext("Please select a gateway, normally the interface selected gateway, so the limiters work correctly");
	}
	if( !empty($_POST['ruleid']) && !ctype_digit($_POST['ruleid']))
		$input_errors[] = gettext('ID must be an integer');
	if($_POST['layer7'] && $_POST['layer7'] != "") {
		if(!($_POST['protocol'] == "tcp" || $_POST['protocol'] == "udp" || $_POST['protocol'] == "tcp/udp"))
			$input_errors[] = gettext("You can only select a layer7 container for TCP and/or UDP protocols");
		if ($_POST['action'] != "pass")
			$input_errors[] = gettext("You can only select a layer7 container for Pass type rules.");
	}

	if (!in_array($_POST['protocol'], array("tcp","tcp/udp"))) {
		if (!empty($_POST['max-connections']))
			$input_errors[] = gettext("You can only specify the maximum number of established connections per host (advanced option) for TCP protocol.");
		if (!empty($_POST['max-src-conn-rate']) || !empty($_POST['max-src-conn-rates']))
			$input_errors[] = gettext("You can only specify the maximum new connections per host / per second(s) (advanced option) for TCP protocol.");
		if (!empty($_POST['state-timeout']))
			$input_errors[] = gettext("You can only specify the state timeout (advanced option) for TCP protocol.");
	}

	if ($_POST['action'] != "pass") {
		if (!empty($_POST['max-states']))
			$input_errors[] = gettext("You can only specify the maximum state entries (advanced option) for Pass type rules.");
		if (!empty($_POST['max-src-nodes']))
			$input_errors[] = gettext("You can only specify the maximum number of unique source hosts (advanced option) for Pass type rules.");
		if (!empty($_POST['max-connections']))
			$input_errors[] = gettext("You can only specify the maximum number of established connections per host (advanced option) for Pass type rules.");
		if (!empty($_POST['max-src-states']))
			$input_errors[] = gettext("You can only specify the maximum state entries per host (advanced option) for Pass type rules.");
		if (!empty($_POST['max-src-conn-rate']) || !empty($_POST['max-src-conn-rates']))
			$input_errors[] = gettext("You can only specify the maximum new connections per host / per second(s) (advanced option) for Pass type rules.");
		if (!empty($_POST['state-timeout']))
			$input_errors[] = gettext("You can only specify the state timeout (advanced option) for Pass type rules.");
	}

	if (($_POST['state-type'] == "none") && (empty($_POST['layer7']))) {
		if (!empty($_POST['max-states']))
			$input_errors[] = gettext("You cannot specify the maximum state entries (advanced option) if statetype is none and no L7 container is selected.");
		if (!empty($_POST['max-src-nodes']))
			$input_errors[] = gettext("You cannot specify the maximum number of unique source hosts (advanced option) if statetype is none and no L7 container is selected.");
		if (!empty($_POST['max-connections']))
			$input_errors[] = gettext("You cannot specify the maximum number of established connections per host (advanced option) if statetype is none and no L7 container is selected.");
		if (!empty($_POST['max-src-states']))
			$input_errors[] = gettext("You cannot specify the maximum state entries per host (advanced option) if statetype is none and no L7 container is selected.");
		if (!empty($_POST['max-src-conn-rate']) || !empty($_POST['max-src-conn-rates']))
			$input_errors[] = gettext("You cannot specify the maximum new connections per host / per second(s) (advanced option) if statetype is none and no L7 container is selected.");
		if (!empty($_POST['state-timeout']))
			$input_errors[] = gettext("You cannot specify the state timeout (advanced option) if statetype is none and no L7 container is selected.");
	}

	if (($_POST['max-states'] != "") && !is_posnumericint($_POST['max-states']))
		$input_errors[] = gettext("Maximum state entries (advanced option) must be a positive integer");

	if (($_POST['max-src-nodes'] != "") && !is_posnumericint($_POST['max-src-nodes']))
		$input_errors[] = gettext("Maximum number of unique source hosts (advanced option) must be a positive integer");

	if (($_POST['max-connections'] != "") && !is_posnumericint($_POST['max-connections']))
		$input_errors[] = gettext("Maximum number of established connections per host (advanced option) must be a positive integer");

	if (($_POST['max-src-states'] != "") && !is_posnumericint($_POST['max-src-states']))
		$input_errors[] = gettext("Maximum state entries per host (advanced option) must be a positive integer");

	if (($_POST['max-src-conn-rate'] != "") && !is_posnumericint($_POST['max-src-conn-rate']))
		$input_errors[] = gettext("Maximum new connections per host / per second(s) (advanced option) must be a positive integer");

	if (($_POST['state-timeout'] != "") && !is_posnumericint($_POST['state-timeout']))
		$input_errors[] = gettext("State timeout (advanced option) must be a positive integer");

	if ((($_POST['max-src-conn-rate'] != "" and $_POST['max-src-conn-rates'] == "")) ||
		(($_POST['max-src-conn-rate'] == "" and $_POST['max-src-conn-rates'] != "")))
		$input_errors[] = gettext("Both maximum new connections per host and the interval (per second(s)) must be specified");

	if (!$_POST['tcpflags_any']) {
		$settcpflags = array();
		$outoftcpflags = array();
		foreach ($tcpflags as $tcpflag) {
			if ($_POST['tcpflags1_' . $tcpflag] == "on")
				$settcpflags[] = $tcpflag;
			if ($_POST['tcpflags2_' . $tcpflag] == "on")
				$outoftcpflags[] = $tcpflag;
		}
		if (empty($outoftcpflags) && !empty($settcpflags))
			$input_errors[] = gettext("If you specify TCP flags that should be set you should specify out of which flags as well.");
	}

	// Allow extending of the firewall edit page and include custom input validation
	pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/input_validation");

	if (!$input_errors) {
		$filterent = array();
		$filterent['id'] = $_POST['ruleid']>0?$_POST['ruleid']:'';

		$filterent['tracker'] = empty($_POST['tracker']) ? (int)microtime(true) : $_POST['tracker'];

		$filterent['type'] = $_POST['action'];
		if (isset($_POST['interface'] ))
			$filterent['interface'] = $_POST['interface'];

		if (isset($_POST['tcp-ip-version'] ))
			$filterent['ipprotocol'] = $_POST['tcp-ip-version'];

		if ($_POST['tcpflags_any']) {
			$filterent['tcpflags_any'] = true;
		} else {
			$settcpflags = array();
			$outoftcpflags = array();
			foreach ($tcpflags as $tcpflag) {
				if ($_POST['tcpflags1_' . $tcpflag] == "on")
					$settcpflags[] = $tcpflag;
				if ($_POST['tcpflags2_' . $tcpflag] == "on")
					$outoftcpflags[] = $tcpflag;
			}
			if (!empty($outoftcpflags)) {
				$filterent['tcpflags2'] = join(",", $outoftcpflags);
				if (!empty($settcpflags))
					$filterent['tcpflags1'] = join(",", $settcpflags);
			}
		}

		if (isset($_POST['tag']))
			$filterent['tag'] = $_POST['tag'];
		if (isset($_POST['tagged']))
			$filterent['tagged'] = $_POST['tagged'];
		if ($if == "FloatingRules" || isset($_POST['floating'])) {
			$filterent['direction'] = $_POST['tcp-ip-version'];
			if (isset($_POST['quick']) && $_POST['quick'] != "")
				$filterent['quick'] = $_POST['quick'];
			$filterent['floating'] = "yes";
			if (isset($_POST['interface']) && count($_POST['interface']) > 0)  {
				$filterent['interface'] = implode(",", $_POST['interface']);
			}
		}

		/* Advanced options */
		if ($_POST['allow-ip-options'] == "yes")
			$filterent['allowopts'] = true;
		else
			unset($filterent['allowopts']);
		if ($_POST['disable-reply-to'] == "yes")
			$filterent['disablereplyto'] = true;
		else
			unset($filterent['disablereplyto']);
		$filterent['max'] = $_POST['max-states'];
		$filterent['max-src-nodes'] = $_POST['max-src-nodes'];
		$filterent['max-src-conn'] = $_POST['max-connections'];
		$filterent['max-src-states'] = $_POST['max-src-states'];
		$filterent['statetimeout'] = $_POST['state-timeout'];
		$filterent['statetype'] = $_POST['state-type'];
		$filterent['os'] = $_POST['source-os'];
		if($_POST['no-pfsync'] != "")
			$filterent['nopfsync'] = true;
		else
			unset($filterent['nopfsync']);

		/* Nosync directive - do not xmlrpc sync this item */
		if($_POST['no-xmlrpc-sync'] != "")
			$filterent['nosync'] = true;
		else
			unset($filterent['nosync']);

		/* unless both values are provided, unset the values - ticket #650 */
		if($_POST['max-src-conn-rate'] != "" and $_POST['max-src-conn-rates'] != "") {
			$filterent['max-src-conn-rate'] = $_POST['max-src-conn-rate'];
			$filterent['max-src-conn-rates'] = $_POST['max-src-conn-rates'];
		} else {
			unset($filterent['max-src-conn-rate']);
			unset($filterent['max-src-conn-rates']);
		}

		if ($_POST['protocol'] != "any")
			$filterent['protocol'] = $_POST['protocol'];
		else
			unset($filterent['protocol']);

		if ($_POST['protocol'] == "icmp") {
			if ($filterent['ipprotocol'] == 'inet6' && $_POST['icmpv6-type'])
				$filterent['icmptype'] = $_POST['icmpv6-type'];
			else if ($filterent['ipprotocol'] != 'inet6' && $_POST['icmp-type'])
				$filterent['icmptype'] = $_POST['icmp-type'];
			else
				unset($filterent['icmptype']);
		} else
			unset($filterent['icmptype']);

		pconfig_to_address($filterent['source'], $_POST['source-address'],
			$_POST['source-addressmask'], $_POST['source-not'],
			$_POST['source-port-begin'], $_POST['source-port-end']);

		pconfig_to_address($filterent['destination'], $_POST['destination-address'],
			$_POST['source-addressmask'], $_POST['destination-not'],
			$_POST['destination-port-begin'], $_POST['destination-port-end']);

		if ($_POST['disabled'])
			$filterent['disabled'] = true;
		else
			unset($filterent['disabled']);

		if ($_POST['diffserv-code-point'])
			$filterent['dscp'] = $_POST['diffserv-code-point'];

		if ($_POST['log'])
			$filterent['log'] = true;
		else
			unset($filterent['log']);
		strncpy($filterent['descr'], $_POST['description'], 52);

		if ($_POST['gateway'] != "") {
			$filterent['gateway'] = $_POST['gateway'];
		}

		if ($_POST['default-queue'] != "") {
			$filterent['defaultqueue'] = $_POST['default-queue'];
			if ($_POST['ackqueue'] != "")
				$filterent['ackqueue'] = $_POST['ackqueue'];
		}

		if ($_POST['dnpipe'] != "") {
			$filterent['dnpipe'] = $_POST['dnpipe'];
			if ($_POST['pdnpipe'] != "")
				$filterent['pdnpipe'] = $_POST['pdnpipe'];
		}

		if ($_POST['layer7'] != "") {
			$filterent['l7container'] = $_POST['layer7'];
		}

		if ($_POST['schedule'] != "") {
			$filterent['sched'] = $_POST['schedule'];
		}

		if ($_POST['vlan-prio'] != "") {
			$filterent['vlanprio'] = $_POST['vlan-prio'];
		}
		if ($_POST['vlan-prio-set'] != "") {
			$filterent['vlanprioset'] = $_POST['vlan-prio-set'];
		}

		// If we have an associated nat rule, make sure the source and destination doesn't change
		if( isset($a_filter[$id]['associated-rule-id']) ) {
			$filterent['interface'] = $a_filter[$id]['interface'];
			if (isset($a_filter[$id]['protocol']))
				$filterent['protocol'] = $a_filter[$id]['protocol'];
			else if (isset($filterent['protocol']))
				unset($filterent['protocol']);
			if ($a_filter[$id]['protocol'] == "icmp" && $a_filter[$id]['icmptype'])
				$filterent['icmptype'] = $a_filter[$id]['icmptype'];
			else if (isset($filterent['icmptype']))
				unset($filterent['icmptype']);

			$filterent['source'] = $a_filter[$id]['source'];
			$filterent['destination'] = $a_filter[$id]['destination'];
			$filterent['associated-rule-id'] = $a_filter[$id]['associated-rule-id'];
		}

		if ( isset($a_filter[$id]['created']) && is_array($a_filter[$id]['created']) )
			$filterent['created'] = $a_filter[$id]['created'];

		$filterent['updated'] = make_config_revision_entry();

		// Allow extending of the firewall edit page and include custom input validation
		pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/pre_write_config");

		if (isset($id) && $a_filter[$id])
			$a_filter[$id] = $filterent;
		else {
			$filterent['created'] = make_config_revision_entry();
			if (is_numeric($after))
				array_splice($a_filter, $after+1, 0, array($filterent));
			else
				$a_filter[] = $filterent;
		}

		filter_rules_sort();

		if (write_config())
			mark_subsystem_dirty('filter');

		if (isset($_POST['floating']))
			header("Location: firewall_rules.php?if=FloatingRules");
		else
			header("Location: firewall_rules.php?if=" . htmlspecialchars($_POST['interface']));
		exit;
	}
}

$pgtitle = array(gettext("Firewall"),gettext("Rules"),gettext("Edit"));
$shortcut_section = "firewall";

$closehead = false;

$page_filename = "firewall_rules_edit.php";
include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

require('classes/Form.class.php');
$form = new Form;
$section = new Form_Section('Edit Firewall rule');

if (isset($id))
{
	$section->addInput(new Form_Input(
		'ID',
		'hidden',
		$id
	));
}

if (isset($a_filter[$id]))
{
	$section->addInput(new Form_Input(
		'Tracker',
		'hidden',
		$pconfig['tracker']
	));
}

$section->addInput(new Form_Input(
	'After',
	'hidden',
	$after
));

$section->addInput(new Form_Input(
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

if ($if == "FloatingRules" || isset($pconfig['floating']))
	$values['match'] = 'Match';

$section->addInput(new Form_Select(
	'Action',
	$pconfig['type'],
	$values
))->setHelp('Choose what to do with packets that match the criteria specified '.
	'below.<br/>Hint: the difference between block and reject is that with '.
	'reject, a packet (TCP RST or ICMP port unreachable for UDP) is returned '.
	'to the sender, whereas with block the packet is dropped silently. In '.
	'either case, the original packet is discarded.');

$section->addInput(new Form_Checkbox(
	'Disabled',
	'Disable this rule',
	$pconfig['disabled']
))->setHelp('Set this option to disable this rule without removing it from the '.
	'list.');

if ($if == "FloatingRules" || isset($pconfig['floating']))
{
	$section->addInput(new Form_Checkbox(
		'Quick',
		'Apply the action immediately on match.',
		$pconfig['quick']
	))->setHelp('Set this option if you need to apply this action to traffic that '.
		'matches this rule immediately.');
}

$edit_disabled = isset($pconfig['associated-rule-id']);

if ($edit_disabled)
{
	$extra = '';
	foreach( $config['nat']['rule'] as $index => $nat_rule )
	{
		if ($nat_rule['associated-rule-id'] === $pconfig['associated-rule-id'] )
			$extra = '<br/><a href="firewall_nat_edit.php?id='. $index .'">'. gettext('View the NAT rule') .'</a>';
	}

	$section->add(new Form_Group(
		'Associated filter rule'
	))->setHelp('Note: This is associated to a NAT rule.<br/>You cannot edit '.
		'the interface, protocol, source, or destination of associated filter '.
		'rules.'. $extra);

	$section->addInput(new Form_Input(
		'Associated Rule ID',
		'hidden',
		$pconfig['associated-rule-id']
	));

	if (!empty($pconfig['interface']))
	{
		$section->addInput(new Form_Input(
			'Interface',
			'hidden',
			$pconfig['interface']
		));
	}
}

$interfaces = array();

/* add group interfaces */
if (is_array($config['ifgroups']['ifgroupentry']))
	foreach ($config['ifgroups']['ifgroupentry'] as $ifgen)
		if (have_ruleint_access($ifgen['ifname']))
			$interfaces[$ifgen['ifname']] = $ifgen['ifname'];

foreach (get_configured_interface_with_descr() as $ifent => $ifdesc)
{
	if (have_ruleint_access($ifent))
		$interfaces[$ifent] = $ifdesc;
}

if ($config['l2tp']['mode'] == "server" && have_ruleint_access("l2tp"))
	$interfaces['l2tp'] = 'L2TP VPN';

if ($config['pptpd']['mode'] == "server" && have_ruleint_access("pptp"))
	$interfaces['pptp'] = 'PPTP VPN';

if (is_pppoe_server_enabled() && have_ruleint_access("pppoe"))
	$interfaces['pppoe'] = "PPPoE Server";

/* add ipsec interfaces */
if (isset($config['ipsec']['enable']) || isset($config['ipsec']['client']['enable']) && have_ruleint_access("enc0"))
	$interfaces["enc0"] = "IPsec";

/* add openvpn/tun interfaces */
if  ($config['openvpn']["openvpn-server"] || $config['openvpn']["openvpn-client"])
	$interfaces["openvpn"] = "OpenVPN";

$section->addInput(new Form_Select(
	'Interface',
	$pconfig['interface'],
	$interfaces,
	($if == "FloatingRules" || isset($pconfig['floating']))
))->setHelp('Choose on which interface packets must come in to match this '.
	'rule.');

if ($if == "FloatingRules" || isset($pconfig['floating']))
{
	$section->addInput(new Form_Select(
		'Direction',
		$pconfig['direction'],
		array(
			'any' => 'any',
			'in' => 'in',
			'out' => 'out',
		)
	));

	$section->addInput(new Form_Input(
		'Floating',
		'hidden',
		'floating'
	));
}

$section->addInput(new Form_Select(
	'TCP/IP Version',
	$pconfig['ipprotocol'],
	array(
		'inet' => 'IPv4',
		'inet6' => 'IPv6',
		'inet46' => 'IPv4+IPv6',
	)
))->setHelp('Select the Internet Protocol version this rule applies to');

$section->addInput(new Form_Select(
	'Protocol',
	$pconfig['ipprotocol'],
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
		'any' => 'any',
		'carp' => 'CARP',
		'pfsync' => 'PFSYNC',
	)
))->setHelp('Select the Internet Protocol version this rule applies to');

$section->addInput(new Form_Select(
	'ICMP type',
	$pconfig['icmptype'],
	$icmptypes
))->setHelp('If you selected ICMP for the protocol above, you may specify an ICMP type here.');

$section->addInput(new Form_Select(
	'ICMPv6 type',
	$pconfig['icmptype'],
	$icmp6types
))->setHelp('If you selected ICMP for the protocol above, you may specify an ICMP type here.');

$form->add($section);

// Source and destination share a lot of logic. Loop over the two
foreach (['src' => 'Source', 'dst' => 'Destination'] as $type => $name)
{
	$section = new Form_Section($name .' details');

	$group = new Form_Group($name);
	$group->add(new Form_Checkbox(
		$name .' not',
		'Invert the sense of the match.',
		$pconfig[$type.'not']
	));

	$ruleType = $pconfig[$type];
	if (is_specialnet($pconfig[$type]))
		$ruleType = 'network';
	elseif ((is_ipaddrv6($pconfig[$type]) && $pconfig[$type.'mask'] == 128) ||
			(is_ipaddrv4($pconfig[$type]) && $pconfig[$type.'mask'] == 32) ||
			(is_alias($pconfig[$type])))
		$ruleType = 'single';

	$ruleValues = array(
		'any' => 'any',
		'single' => 'Single host or alias',
		'network' => 'Network',
	);
	if (isset($a_filter[$id]['floating']) || $if == "FloatingRules")
		$ruleValues['(self)'] = 'This Firewall (self)';
	if (have_ruleint_access("pptp"))
		$ruleValues['pptp'] = 'PPTP clients';
	if (have_ruleint_access("pppoe"))
		$ruleValues['pppoe'] = 'PPoE clients';
	if (have_ruleint_access("l2tp"))
		$ruleValues['l2tp'] = 'L2TP clients';

	foreach ($ifdisp as $ifent => $ifdesc)
	{
		if (!have_ruleint_access($ifent))
			continue;

		$ruleValues[$ifent] = $ifdesc.' net';
		$ruleValues[$ifent.'ip'] = $ifdesc.' address';
	}

	$group->add(new Form_Select(
		$name .' Type',
		$ruleType,
		$ruleValues
	));

	$group->add(new Form_Input(
		$name .' Address',
		'text',
		$pconfig[$type]
	));

	$group->add(new Form_Input(
		$name .' addressmask',
		'number',
		$pconfig[$type.'mask'],
		['min' => 1, 'max' => 127]
	));

	$section->add($group);

	$portValues = ['any' => 'any'];
	foreach ($wkports as $port => $portName)
		$portValues[$port] = $portName.' ('. $port .')';

	$group = new Form_Group($name .' port range');
	$group->add($input = new Form_Select(
		$name .' port begin',
		$pconfig[$type .'beginport'],
		$portValues
	));

	if ($type == 'src')
		$input->setHelp('Specify the source port or port range for this rule. This is '.
			'usually random and almost never equal to the destination port range (and '.
			'should usually be <b>any</b><br />Hint: you can leave the <i>to</i> field '.
			'empty if you only want to filter a single port.');
	else
		$input->setHelp('Specify the destination port or port range for this rule. <br />'.
			'Hint: you can leave the <i>to</i> field empty if you only want to filter a '.
			'single port.');

	$group->add(new Form_Input(
		$name .' port begin custom',
		'number',
		(isset($portValues[ $pconfig[$type .'beginport'] ]) ? null : $pconfig[$type .'beginport']),
		['min' => 1, 'max' => 65535]
	));

	$group->add(new Form_Select(
		$name .' port end',
		$pconfig[$type .'endport'],
		$portValues
	));

	$group->add(new Form_Input(
		$name .' port end custom',
		'number',
		(isset($portValues[ $pconfig[$type .'endport'] ]) ? null : $pconfig[$type .'endport']),
		['min' => 1, 'max' => 65535]
	));

	$section->add($group);
	$form->add($section);
}

$section = new Form_Section('Extra options');
$section->addInput(new Form_Checkbox(
	'Log',
	'Log packets that are handled by this rule',
	$pconfig['log']
))->setHelp('Hint: the firewall has limited local log space. Don"t turn on logging '.
	'for everything. If you want to do a lot of logging, consider using a remote '.
	'syslog server (see the <a href="diag_logs_settings.php">Diagnostics: System logs: '.
	'Settings</a> page).');

$section->addInput(new Form_Input(
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('You may enter a description here for your reference.');

$form->add($section);
$section = new Form_Section('Advanced options');

$section->addInput(new Form_Select(
	'Source OS',
	$pconfig['os'],
	['' => 'any'] + $ostypes
))->setHelp('Note: this only works for TCP rules. General OS choice matches all subtypes.');

$section->addInput(new Form_Select(
	'Diffserv Code Point',
	$pconfig['dscp'],
	array_combine($firewall_rules_dscp_types, $firewall_rules_dscp_types)
));

$section->addInput(new Form_Checkbox(
	'Allow IP options',
	'Allow packets with IP options to pass. Otherwise they are blocked by '.
	'default. This is usually only seen with multicast traffic.',
	$pconfig['allowopts']
));

$section->addInput(new Form_Checkbox(
	'Disable reply-to',
	'Disable auto generated reply-to for this rule.',
	$pconfig['disablereplyto']
));

$section->addInput(new Form_Input(
	'Tag',
	'text',
	$pconfig['tag']
))->setHelp('You can mark a packet matching this rule and use this mark to match '.
	'on other NAT/filter rules. It is called <b>Policy filtering</b>.');

$section->addInput(new Form_Input(
	'Tagged',
	'text',
	$pconfig['tagged']
))->setHelp('You can match packet on a mark placed before on another rule.');

$section->addInput(new Form_Input(
	'Max. states',
	'number',
	$pconfig['max']
))->setHelp('Maximum state entries this rule can create.');

$section->addInput(new Form_Input(
	'Max. src nodes',
	'number',
	$pconfig['max-src-nodes']
))->setHelp('Maximum number of unique source hosts.');

$section->addInput(new Form_Input(
	'Max. connections',
	'number',
	$pconfig['max-src-conn']
))->setHelp('Maximum number of established connections per host (TCP only).');

$section->addInput(new Form_Input(
	'Max. src. states',
	'number',
	$pconfig['max-src-states']
))->setHelp('Maximum state entries per host.');

$section->addInput(new Form_Input(
	'Max. src. conn. Rate',
	'number',
	$pconfig['max-src-conn-rate']
))->setHelp('Maximum state entries per host');

$section->addInput(new Form_Input(
	'Max. src. conn. Rates',
	'number',
	$pconfig['max-src-conn-rates'],
	['min' => 1, 'max' => 255]
))->setHelp('Maximum new connections per host / per second(s) (TCP only)');

$section->addInput(new Form_Input(
	'State timeout',
	'number',
	$pconfig['statetimeout'],
	['min' => 1, 'max' => 3600]
))->setHelp('State Timeout in seconds (TCP only)');

$form->add($section);
$section = new Form_Section('TCP Flags');

$setflags = explode(',', $pconfig['tcpflags1']);
$outofflags = explode(',', $pconfig['tcpflags2']);

foreach ($tcpflags as $tcpflag)
{
	$section->addInput(new Form_Checkbox(
		'Set '. strtoupper($tcpflag),
		null,
		(array_search($tcpflag, $setflags) !== false),
		'on'
	));

	$section->addInput(new Form_Checkbox(
		'Out of '. strtoupper($tcpflag),
		null,
		(array_search($tcpflag, $setflags) !== false),
		'on'
	));
}

$section->addInput(new Form_Checkbox(
	'Any',
	'Any flags',
	$pconfig['tcpflags_any'],
	'on'
))->setHelp('Use this to choose TCP flags that must be set or cleared for this rule to match.');

$form->add($section);
$section = new Form_Section('State Type');

$section->addInput(new Form_Checkbox(
	'No pfSync',
	'Prevent states created by this rule to be sync\'ed over pfsync.',
	$pconfig['nopfsync']
));

$section->addInput(new Form_Select(
	'State type',
	$pconfig['statetype'],
	array(
		'keep state' => 'Keep: works with all IP protocols',
		'sloppy state' => 'Sloppy: works with all IP protocols',
		'synproxy state' => 'Synproxy: proxies incoming TCP connections to help protect servers from spoofed TCP SYN floods. This option includes the functionality of keep state and modulate state combined',
		'none' => 'None: Do not use state mechanisms to keep track.  This is only useful if you\'re doing advanced queueing in certain situations',
	)
))->setHelp('Select which type of state tracking mechanism you would like to use.  If in doubt, use keep state.');

$section->addInput(new Form_Checkbox(
	'No XMLRPC Sync',
	'Prevent the rule on Master from automatically syncing to other CARP members',
	$pconfig['nosync']
))->setHelp('This does NOT prevent the rule from being overwritten on Slave.');

$vlanprio = array("none", "be", "bk", "ee", "ca", "vi", "vo", "ic", "nc");
$section->addInput(new Form_Select(
	'VLAN Prio',
	$vlanprio,
	$pconfig['vlanprio']
))->setHelp('Choose 802.1p priority to match on');

$section->addInput(new Form_Select(
	'VLAN Prio Set',
	$vlanprio,
	$pconfig['vlanprioset']
))->setHelp('Choose 802.1p priority to apply');

$schedules = array('none'); //leave none to leave rule enabled all the time
foreach ($config['schedules']['schedule'] as $schedule)
{
	if ($schedule['name'] != "")
		$schedules[] = $schedule['name'];
}

$section->addInput(new Form_Select(
	'Schedule',
	$pconfig['sched'],
	$schedules
))->setHelp('Leave as \'none\' to leave the rule enabled all the time');

$gateways = array('default' => 'default');
foreach (return_gateways_array() as $gwname => $gw)
{
	if (($pconfig['ipprotocol'] == "inet46"))
		continue;
	if (($pconfig['ipprotocol'] == "inet6") && !(($gw['ipprotocol'] == "inet6") || (is_ipaddrv6($gw['gateway']))))
		continue;
	if (($pconfig['ipprotocol'] == "inet") && !(($gw['ipprotocol'] == "inet") || (is_ipaddrv4($gw['gateway']))))
		continue;
	if ($gw == "")
		continue;

	$gateways[ $gwname ] = $gw['name'] . (empty($gw['gateway'])? '' : ' - '. $gateway_addr_str);
}

foreach ((array)$a_gatewaygroups as $gwg_name => $gwg_data)
{
	if ((empty($pconfig['ipprotocol'])) || ($pconfig['ipprotocol'] == $gwg_data['ipprotocol']))
		$gateways[ $gwg_name ] = $gwg_name;
}

$section->addInput(new Form_Select(
	'Gateway',
	$pconfig['gateway'],
	$gateways
))->setHelp('Leave as \'default\' to use the system routing table. Or choose a '.
	'gateway to utilize policy based routing.');

$group = new Form_Group('In / Out pipe');

$group->add(new Form_Select(
	'DNpipe',
	$pconfig['dnpipe'],
	array('' => 'none') + array_keys($dnqlist)
));

$group->add(new Form_Select(
	'PDNpipe',
	$pconfig['pdnpipe'],
	array('' => 'none') + array_keys($dnqlist)
));

$section->add($group)->setHelp('Choose the Out queue/Virtual interface only if '.
	'you have also selected In. The Out selection is applied to traffic leaving '.
	'the interface where the rule is created, In is applied to traffic coming '.
	'into the chosen interface.<br />If you are creating a floating rule, if the '.
	'direction is In then the same rules apply, if the direction is out the '.
	'selections are reverted Out is for incoming and In is for outgoing.'
);

$group = new Form_Group('Ackqueue / Queue');

$qlist = array_keys($qlist);
foreach ($qlist as $idx => $q)
{
	if (isset($ifdisp[$q]))
		$qlist[$idx] = $ifdisp[$q];
}

$group->add(new Form_Select(
	'Ackqueue',
	$pconfig['ackqueue'],
	$qlist
));

$group->add(new Form_Select(
	'Default Queue',
	$pconfig['defaultqueue'],
	$qlist
));

$section->add($group)->setHelp('Choose the Acknowledge Queue only if you have '.
	'selected Queue.'
);

$section->addInput(new Form_Select(
	'Layer7',
	$pconfig['l7container'],
	array_keys($l7clist)
))->setHelp('Choose a Layer7 container to apply application protocol inspection '.
	'rules. These are valid for TCP and UDP protocols only.');

$has_created_time = (isset($a_filter[$id]['created']) && is_array($a_filter[$id]['created']));
$has_updated_time = (isset($a_filter[$id]['updated']) && is_array($a_filter[$id]['updated']));


if ($has_created_time || $has_updated_time)
{
	$form->add($section);
	$section = new Form_Section('Rule Information');

	if ($has_created_time)
	{
		$form->addInput(new Form_StaticText(
			'Created',
			date('n/j/y H:i:s', $a_filter[$id]['created']['time']) . gettext('by') .'<b>'. $a_filter[$id]['created']['username'] .'</b>'
		));
	}

	if ($has_updated_time)
	{
		$form->addInput(new Form_StaticText(
			'Updated',
			date('n/j/y H:i:s', $a_filter[$id]['updated']['time']) . gettext('by') .'<b>'. $a_filter[$id]['updated']['username'] .'</b>'
		));
	}
}

$form->add($section);
echo $form;

include("foot.inc");