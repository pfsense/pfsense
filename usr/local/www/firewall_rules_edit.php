<?php
/* $Id$ */
/*
	firewall_rules_edit.php
	part of pfSense (https://www.pfsense.org)
	Copyright (C) 2005 Scott Ullrich (sullrich@gmail.com)

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
		if (isset($a_filter[$id]['interface']) && $a_filter[$id]['interface'] <> "")
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

	if($a_filter[$id]['os'] <> "")
		$pconfig['os'] = $a_filter[$id]['os'];

	address_to_pconfig($a_filter[$id]['destination'], $pconfig['dst'],
		$pconfig['dstmask'], $pconfig['dstnot'],
		$pconfig['dstbeginport'], $pconfig['dstendport']);

	if ($a_filter[$id]['dscp'] <> "")
		$pconfig['dscp'] = $a_filter[$id]['dscp'];

	$pconfig['disabled'] = isset($a_filter[$id]['disabled']);
	$pconfig['log'] = isset($a_filter[$id]['log']);
	$pconfig['descr'] = $a_filter[$id]['descr'];

	if (isset($a_filter[$id]['tcpflags_any']))
		$pconfig['tcpflags_any'] = true;
	else {
		if (isset($a_filter[$id]['tcpflags1']) && $a_filter[$id]['tcpflags1'] <> "")
			$pconfig['tcpflags1'] = $a_filter[$id]['tcpflags1'];
		if (isset($a_filter[$id]['tcpflags2']) && $a_filter[$id]['tcpflags2'] <> "")
			$pconfig['tcpflags2'] = $a_filter[$id]['tcpflags2'];
	}

	if (isset($a_filter[$id]['tag']) && $a_filter[$id]['tag'] <> "")
		$pconfig['tag'] = $a_filter[$id]['tag'];
	if (isset($a_filter[$id]['tagged']) && $a_filter[$id]['tagged'] <> "")
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
		$_POST['proto'] = $pconfig['proto'];
		if ($pconfig['proto'] == "icmp")
			$_POST['icmptype'] = $pconfig['icmptype'];
	}

	if (($_POST['ipprotocol'] <> "") && ($_POST['gateway'] <> "")) {
		if(is_array($config['gateways']['gateway_group'])) {
			foreach($config['gateways']['gateway_group'] as $gw_group) {
				if($gw_group['name'] == $_POST['gateway']) {
					$family = $a_gatewaygroups[$_POST['gateway']]['ipprotocol'];
					if($_POST['ipprotocol'] == $family) {
						continue;
					}
					if(($_POST['ipprotocol'] == "inet46") && ($_POST['ipprotocol'] != $family)) {
						$input_errors[] = gettext("You can not assign a gateway to a rule that applies to IPv4 and IPv6");
					}
					if(($_POST['ipprotocol'] == "inet6") && ($_POST['ipprotocol'] != $family)) {
						$input_errors[] = gettext("You can not assign a IPv4 gateway group on IPv6 Address Family rule");
					}
					if(($_POST['ipprotocol'] == "inet") && ($_POST['ipprotocol'] != $family)) {
						$input_errors[] = gettext("You can not assign a IPv6 gateway group on IPv4 Address Family rule");
					}
				}
			}
		}
	}
	if (($_POST['ipprotocol'] <> "") && ($_POST['gateway'] <> "") && (is_ipaddr(lookup_gateway_ip_by_name($_POST['gateway'])))) {
		if(($_POST['ipprotocol'] == "inet46") && ($_POST['gateway'] <> "")) {
			$input_errors[] = gettext("You can not assign a gateway to a rule that applies to IPv4 and IPv6");
		}
		if(($_POST['ipprotocol'] == "inet6") && (!is_ipaddrv6(lookup_gateway_ip_by_name($_POST['gateway'])))) {
			$input_errors[] = gettext("You can not assign the IPv4 Gateway to a IPv6 Filter rule");
		}
		if(($_POST['ipprotocol'] == "inet") && (!is_ipaddrv4(lookup_gateway_ip_by_name($_POST['gateway'])))) {
			$input_errors[] = gettext("You can not assign the IPv6 Gateway to a IPv4 Filter rule");
		}
	}

	if (($_POST['proto'] != "tcp") && ($_POST['proto'] != "udp") && ($_POST['proto'] != "tcp/udp") && ($_POST['proto'] != "icmp")) {
		if($_POST['ipprotocol'] == "inet46")
			$input_errors[] =  gettext("You can not assign a protocol other then ICMP, TCP, UDP or TCP/UDP to a rule that applies to IPv4 and IPv6");
	}
	if (($_POST['proto'] == "icmp") && ($_POST['icmptype'] <> "")){
		if($_POST['ipprotocol'] == "inet46")
			$input_errors[] =  gettext("You can not assign a ICMP type to a rule that applies to IPv4 and IPv6");
	}

	if (($_POST['proto'] != "tcp") && ($_POST['proto'] != "udp") && ($_POST['proto'] != "tcp/udp")) {
		$_POST['srcbeginport'] = 0;
		$_POST['srcendport'] = 0;
		$_POST['dstbeginport'] = 0;
		$_POST['dstendport'] = 0;
	} else {
		if ($_POST['srcbeginport_cust'] && !$_POST['srcbeginport'])
			$_POST['srcbeginport'] = trim($_POST['srcbeginport_cust']);
		if ($_POST['srcendport_cust'] && !$_POST['srcendport'])
			$_POST['srcendport'] = trim($_POST['srcendport_cust']);
		if ($_POST['srcbeginport'] == "any") {
			$_POST['srcbeginport'] = 0;
			$_POST['srcendport'] = 0;
		} else {
			if (!$_POST['srcendport'])
				$_POST['srcendport'] = $_POST['srcbeginport'];
		}
		if ($_POST['srcendport'] == "any")
			$_POST['srcendport'] = $_POST['srcbeginport'];

		if ($_POST['dstbeginport_cust'] && !$_POST['dstbeginport'])
			$_POST['dstbeginport'] = trim($_POST['dstbeginport_cust']);
		if ($_POST['dstendport_cust'] && !$_POST['dstendport'])
			$_POST['dstendport'] = trim($_POST['dstendport_cust']);

		if ($_POST['dstbeginport'] == "any") {
			$_POST['dstbeginport'] = 0;
			$_POST['dstendport'] = 0;
		} else {
			if (!$_POST['dstendport'])
				$_POST['dstendport'] = $_POST['dstbeginport'];
		}
		if ($_POST['dstendport'] == "any")
			$_POST['dstendport'] = $_POST['dstbeginport'];
	}

	if (is_specialnet($_POST['srctype'])) {
		$_POST['src'] = $_POST['srctype'];
		$_POST['srcmask'] = 0;
	} else if ($_POST['srctype'] == "single") {
		if (is_ipaddrv6($_POST['src']))
			$_POST['srcmask'] = 128;
		else
			$_POST['srcmask'] = 32;
	}
	if (is_specialnet($_POST['dsttype'])) {
		$_POST['dst'] = $_POST['dsttype'];
		$_POST['dstmask'] = 0;
	}  else if ($_POST['dsttype'] == "single") {
		if (is_ipaddrv6($_POST['dst']))
			$_POST['dstmask'] = 128;
		else
			$_POST['dstmask'] = 32;
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

	if($_POST['statetype'] == "modulate state" or $_POST['statetype'] == "synproxy state") {
		if( $_POST['proto'] != "tcp" )
			$input_errors[] = sprintf(gettext("%s is only valid with protocol tcp."),$_POST['statetype']);
		if(($_POST['statetype'] == "synproxy state") && ($_POST['gateway'] != ""))
			$input_errors[] = sprintf(gettext("%s is only valid if the gateway is set to 'default'."),$_POST['statetype']);
	}

	if ( isset($a_filter[$id]['associated-rule-id'])===false &&
	(!(is_specialnet($_POST['srctype']) || ($_POST['srctype'] == "single"))) ) {
		$reqdfields[] = "srcmask";
		$reqdfieldsn[] = "Source bit count";
	}
	if ( isset($a_filter[$id]['associated-rule-id'])===false &&
	(!(is_specialnet($_POST['dsttype']) || ($_POST['dsttype'] == "single"))) ) {
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

	if ($_POST['srcbeginport'] && !is_portoralias($_POST['srcbeginport']))
		$input_errors[] = sprintf(gettext("%s is not a valid start source port. It must be a port alias or integer between 1 and 65535."),$_POST['srcbeginposrt']);
	if ($_POST['srcendport'] && !is_portoralias($_POST['srcendport']))
			$input_errors[] = sprintf(gettext("%s  is not a valid end source port. It must be a port alias or integer between 1 and 65535."),$_POST['srcendport']);
	if ($_POST['dstbeginport'] && !is_portoralias($_POST['dstbeginport']))
			$input_errors[] = sprintf(gettext("%s is not a valid start destination port. It must be a port alias or integer between 1 and 65535."),$_POST['dstbeginport']);
	if ($_POST['dstendport'] && !is_portoralias($_POST['dstendport']))
			$input_errors[] = sprintf(gettext("%s is not a valid end destination port. It must be a port alias or integer between 1 and 65535."),$_POST['dstendport']);
	if ( !$_POST['srcbeginport_cust'] && $_POST['srcendport_cust'])
		if (is_alias($_POST['srcendport_cust']))
			$input_errors[] = 'If you put port alias in Source port range to: field you must put the same port alias in from: field';
	if ( $_POST['srcbeginport_cust'] && $_POST['srcendport_cust']){
		if (is_alias($_POST['srcendport_cust']) && is_alias($_POST['srcendport_cust']) && $_POST['srcbeginport_cust'] != $_POST['srcendport_cust'])
			$input_errors[] = 'The same port alias must be used in Source port range from: and to: fields';
		if ((is_alias($_POST['srcbeginport_cust']) && (!is_alias($_POST['srcendport_cust']) && $_POST['srcendport_cust']!='')) ||
		    ((!is_alias($_POST['srcbeginport_cust']) && $_POST['srcbeginport_cust']!='') && is_alias($_POST['srcendport_cust'])))
			$input_errors[] = 'You cannot specify numbers and port aliases at the same time in Source port range from: and to: field';
	}
	if ( !$_POST['dstbeginport_cust'] && $_POST['dstendport_cust'])
		if (is_alias($_POST['dstendport_cust']))
			$input_errors[] = 'If you put port alias in Destination port range to: field you must put the same port alias in from: field';
	if ( $_POST['dstbeginport_cust'] && $_POST['dstendport_cust']){
		if (is_alias($_POST['dstendport_cust']) && is_alias($_POST['dstendport_cust']) && $_POST['dstbeginport_cust'] != $_POST['dstendport_cust'])
			$input_errors[] = 'The same port alias must be used in Destination port range from: and to: fields';
		if ((is_alias($_POST['dstbeginport_cust']) && (!is_alias($_POST['dstendport_cust']) && $_POST['dstendport_cust']!='')) ||
		    ((!is_alias($_POST['dstbeginport_cust']) && $_POST['dstbeginport_cust']!='') && is_alias($_POST['dstendport_cust'])))
			$input_errors[] = 'You cannot specify numbers and port aliases at the same time in Destination port range from: and to: field';
	}

	if ($_POST['src'])
		$_POST['src'] = trim($_POST['src']);
	if ($_POST['dst'])
		$_POST['dst'] = trim($_POST['dst']);

	/* if user enters an alias and selects "network" then disallow. */
	if($_POST['srctype'] == "network") {
		if(is_alias($_POST['src']))
			$input_errors[] = gettext("You must specify single host or alias for alias entries.");
	}
	if($_POST['dsttype'] == "network") {
		if(is_alias($_POST['dst']))
			$input_errors[] = gettext("You must specify single host or alias for alias entries.");
	}

	if (!is_specialnet($_POST['srctype'])) {
		if (($_POST['src'] && !is_ipaddroralias($_POST['src']))) {
			$input_errors[] = sprintf(gettext("%s is not a valid source IP address or alias."),$_POST['src']);
		}
		if (($_POST['srcmask'] && !is_numericint($_POST['srcmask']))) {
			$input_errors[] = gettext("A valid source bit count must be specified.");
		}
	}
	if (!is_specialnet($_POST['dsttype'])) {
		if (($_POST['dst'] && !is_ipaddroralias($_POST['dst']))) {
			$input_errors[] = sprintf(gettext("%s is not a valid destination IP address or alias."),$_POST['dst']);
		}
		if (($_POST['dstmask'] && !is_numericint($_POST['dstmask']))) {
			$input_errors[] = gettext("A valid destination bit count must be specified.");
		}
	}
	if((is_ipaddr($_POST['src']) && is_ipaddr($_POST['dst']))) {
		if(!validate_address_family($_POST['src'], $_POST['dst']))
			$input_errors[] = sprintf(gettext("The Source IP address %s Address Family differs from the destination %s."), $_POST['src'], $_POST['dst']);
		if((is_ipaddrv6($_POST['src']) || is_ipaddrv6($_POST['dst'])) && ($_POST['ipprotocol'] == "inet"))
			$input_errors[] = gettext("You can not use IPv6 addresses in IPv4 rules.");
		if((is_ipaddrv4($_POST['src']) || is_ipaddrv4($_POST['dst'])) && ($_POST['ipprotocol'] == "inet6"))
			$input_errors[] = gettext("You can not use IPv4 addresses in IPv6 rules.");
	}

	if((is_ipaddr($_POST['src']) || is_ipaddr($_POST['dst'])) && ($_POST['ipprotocol'] == "inet46"))
		$input_errors[] = gettext("You can not use a IPv4 or IPv6 address in combined IPv4 + IPv6 rules.");

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
		if( $_POST['proto'] != "tcp" )
			$input_errors[] = gettext("OS detection is only valid with protocol tcp.");
		if (!in_array($_POST['os'], $ostypes))
			$input_errors[] = gettext("Invalid OS detection selection. Please select a valid OS.");
	}

	if ($_POST['ackqueue'] != "") {
		if ($_POST['defaultqueue'] == "" )
			$input_errors[] = gettext("You have to select a queue when you select an acknowledge queue too.");
		else if ($_POST['ackqueue'] == $_POST['defaultqueue'])
			$input_errors[] = gettext("Acknowledge queue and Queue cannot be the same.");
	}
	if (isset($_POST['floating']) && $_POST['pdnpipe'] != "" && (empty($_POST['direction']) || $_POST['direction'] == "any"))
		$input_errors[] = gettext("You can not use limiters in Floating rules without choosing a direction.");
	if (isset($_POST['floating']) && $_POST['gateway'] != "" && (empty($_POST['direction']) || $_POST['direction'] == "any"))
		$input_errors[] = gettext("You can not use gateways in Floating rules without choosing a direction.");
	if ($_POST['pdnpipe'] && $_POST['pdnpipe'] != "") {
		if ($_POST['dnpipe'] == "" )
			$input_errors[] = gettext("You must select a queue for the In direction before selecting one for Out too.");
		else if ($_POST['pdnpipe'] == $_POST['dnpipe'])
			$input_errors[] = gettext("In and Out Queue cannot be the same.");
		else if ($dnqlist[$_POST['pdnpipe']][0] == "?" && $dnqlist[$_POST['dnpipe']][0] <> "?")
			$input_errors[] = gettext("You cannot select one queue and one virtual interface for IN and Out. both must be from the same type.");
		else if ($dnqlist[$_POST['dnpipe']][0] == "?" && $dnqlist[$_POST['pdnpipe']][0] <> "?")
			$input_errors[] = gettext("You cannot select one queue and one virtual interface for IN and Out. both must be from the same type.");
		if ($_POST['direction'] == "out" && empty($_POST['gateway']))
			$input_errors[] = gettext("Please select a gateway, normaly the interface selected gateway, so the limiters work correctly");
	}
	if( !empty($_POST['ruleid']) && !ctype_digit($_POST['ruleid']))
		$input_errors[] = gettext('ID must be an integer');
	if($_POST['l7container'] && $_POST['l7container'] != "") {
		if(!($_POST['proto'] == "tcp" || $_POST['proto'] == "udp" || $_POST['proto'] == "tcp/udp"))
			$input_errors[] = gettext("You can only select a layer7 container for TCP and/or UDP protocols");
		if ($_POST['type'] <> "pass")
			$input_errors[] = gettext("You can only select a layer7 container for Pass type rules.");
	}

	if (!in_array($_POST['proto'], array("tcp","tcp/udp"))) {
		if (!empty($_POST['max-src-conn']))
			$input_errors[] = gettext("You can only specify the maximum number of established connections per host (advanced option) for TCP protocol.");
		if (!empty($_POST['max-src-conn-rate']) || !empty($_POST['max-src-conn-rates']))
			$input_errors[] = gettext("You can only specify the maximum new connections per host / per second(s) (advanced option) for TCP protocol.");
		if (!empty($_POST['statetimeout']))
			$input_errors[] = gettext("You can only specify the state timeout (advanced option) for TCP protocol.");
	}

	if ($_POST['type'] <> "pass") {
		if (!empty($_POST['max']))
			$input_errors[] = gettext("You can only specify the maximum state entries (advanced option) for Pass type rules.");
		if (!empty($_POST['max-src-nodes']))
			$input_errors[] = gettext("You can only specify the maximum number of unique source hosts (advanced option) for Pass type rules.");
		if (!empty($_POST['max-src-conn']))
			$input_errors[] = gettext("You can only specify the maximum number of established connections per host (advanced option) for Pass type rules.");
		if (!empty($_POST['max-src-states']))
			$input_errors[] = gettext("You can only specify the maximum state entries per host (advanced option) for Pass type rules.");
		if (!empty($_POST['max-src-conn-rate']) || !empty($_POST['max-src-conn-rates']))
			$input_errors[] = gettext("You can only specify the maximum new connections per host / per second(s) (advanced option) for Pass type rules.");
		if (!empty($_POST['statetimeout']))
			$input_errors[] = gettext("You can only specify the state timeout (advanced option) for Pass type rules.");
	}

	if (($_POST['statetype'] == "none") && (empty($_POST['l7container']))) {
		if (!empty($_POST['max']))
			$input_errors[] = gettext("You cannot specify the maximum state entries (advanced option) if statetype is none and no L7 container is selected.");
		if (!empty($_POST['max-src-nodes']))
			$input_errors[] = gettext("You cannot specify the maximum number of unique source hosts (advanced option) if statetype is none and no L7 container is selected.");
		if (!empty($_POST['max-src-conn']))
			$input_errors[] = gettext("You cannot specify the maximum number of established connections per host (advanced option) if statetype is none and no L7 container is selected.");
		if (!empty($_POST['max-src-states']))
			$input_errors[] = gettext("You cannot specify the maximum state entries per host (advanced option) if statetype is none and no L7 container is selected.");
		if (!empty($_POST['max-src-conn-rate']) || !empty($_POST['max-src-conn-rates']))
			$input_errors[] = gettext("You cannot specify the maximum new connections per host / per second(s) (advanced option) if statetype is none and no L7 container is selected.");
		if (!empty($_POST['statetimeout']))
			$input_errors[] = gettext("You cannot specify the state timeout (advanced option) if statetype is none and no L7 container is selected.");
	}

	if (($_POST['max'] != "") && !is_posnumericint($_POST['max']))
		$input_errors[] = gettext("Maximum state entries (advanced option) must be a positive integer");

	if (($_POST['max-src-nodes'] != "") && !is_posnumericint($_POST['max-src-nodes']))
		$input_errors[] = gettext("Maximum number of unique source hosts (advanced option) must be a positive integer");

	if (($_POST['max-src-conn'] != "") && !is_posnumericint($_POST['max-src-conn']))
		$input_errors[] = gettext("Maximum number of established connections per host (advanced option) must be a positive integer");

	if (($_POST['max-src-states'] != "") && !is_posnumericint($_POST['max-src-states']))
		$input_errors[] = gettext("Maximum state entries per host (advanced option) must be a positive integer");

	if (($_POST['max-src-conn-rate'] != "") && !is_posnumericint($_POST['max-src-conn-rate']))
		$input_errors[] = gettext("Maximum new connections per host / per second(s) (advanced option) must be a positive integer");

	if (($_POST['statetimeout'] != "") && !is_posnumericint($_POST['statetimeout']))
		$input_errors[] = gettext("State timeout (advanced option) must be a positive integer");

	if ((($_POST['max-src-conn-rate'] <> "" and $_POST['max-src-conn-rates'] == "")) || 
	    (($_POST['max-src-conn-rate'] == "" and $_POST['max-src-conn-rates'] <> "")))
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

		$filterent['type'] = $_POST['type'];
		if (isset($_POST['interface'] ))
			$filterent['interface'] = $_POST['interface'];

		if (isset($_POST['ipprotocol'] ))
			$filterent['ipprotocol'] = $_POST['ipprotocol'];

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
			$filterent['direction'] = $_POST['direction'];
			if (isset($_POST['quick']) && $_POST['quick'] <> "")
				$filterent['quick'] = $_POST['quick'];
			$filterent['floating'] = "yes";
			if (isset($_POST['interface']) && count($_POST['interface']) > 0)  {
				$filterent['interface'] = implode(",", $_POST['interface']);
			}
		}

		/* Advanced options */
		if ($_POST['allowopts'] == "yes")
			$filterent['allowopts'] = true;
		else
			unset($filterent['allowopts']);
		if ($_POST['disablereplyto'] == "yes")
			$filterent['disablereplyto'] = true;
		else
			unset($filterent['disablereplyto']);
		$filterent['max'] = $_POST['max'];
		$filterent['max-src-nodes'] = $_POST['max-src-nodes'];
		$filterent['max-src-conn'] = $_POST['max-src-conn'];
		$filterent['max-src-states'] = $_POST['max-src-states'];
		$filterent['statetimeout'] = $_POST['statetimeout'];
		$filterent['statetype'] = $_POST['statetype'];
		$filterent['os'] = $_POST['os'];
		if($_POST['nopfsync'] <> "")
			$filterent['nopfsync'] = true;
		else
			unset($filterent['nopfsync']);

		/* Nosync directive - do not xmlrpc sync this item */
		if($_POST['nosync'] <> "")
			$filterent['nosync'] = true;
		else
			unset($filterent['nosync']);

		/* unless both values are provided, unset the values - ticket #650 */
		if($_POST['max-src-conn-rate'] <> "" and $_POST['max-src-conn-rates'] <> "") {
			$filterent['max-src-conn-rate'] = $_POST['max-src-conn-rate'];
			$filterent['max-src-conn-rates'] = $_POST['max-src-conn-rates'];
		} else {
			unset($filterent['max-src-conn-rate']);
			unset($filterent['max-src-conn-rates']);
		}

		if ($_POST['proto'] != "any")
			$filterent['protocol'] = $_POST['proto'];
		else
			unset($filterent['protocol']);

		if ($_POST['proto'] == "icmp" && $_POST['icmptype'])
			$filterent['icmptype'] = $_POST['icmptype'];
		else
			unset($filterent['icmptype']);

		pconfig_to_address($filterent['source'], $_POST['src'],
			$_POST['srcmask'], $_POST['srcnot'],
			$_POST['srcbeginport'], $_POST['srcendport']);

		pconfig_to_address($filterent['destination'], $_POST['dst'],
			$_POST['dstmask'], $_POST['dstnot'],
			$_POST['dstbeginport'], $_POST['dstendport']);

		if ($_POST['disabled'])
			$filterent['disabled'] = true;
		else
			unset($filterent['disabled']);

		if ($_POST['dscp'])
			$filterent['dscp'] = $_POST['dscp'];

		if ($_POST['log'])
			$filterent['log'] = true;
		else
			unset($filterent['log']);
		strncpy($filterent['descr'], $_POST['descr'], 52);

		if ($_POST['gateway'] != "") {
			$filterent['gateway'] = $_POST['gateway'];
		}

		if ($_POST['defaultqueue'] != "") {
			$filterent['defaultqueue'] = $_POST['defaultqueue'];
			if ($_POST['ackqueue'] != "")
				$filterent['ackqueue'] = $_POST['ackqueue'];
		}

		if ($_POST['dnpipe'] != "") {
			$filterent['dnpipe'] = $_POST['dnpipe'];
			if ($_POST['pdnpipe'] != "")
				$filterent['pdnpipe'] = $_POST['pdnpipe'];
		}

		if ($_POST['l7container'] != "") {
			$filterent['l7container'] = $_POST['l7container'];
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

?>
<link rel="stylesheet" href="/javascript/chosen/chosen.css" />
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<script type="text/javascript" src="/javascript/jquery.ipv4v6ify.js"></script>
<script src="/javascript/chosen/chosen.jquery.js" type="text/javascript"></script>
<?php include("fbegin.inc"); ?>
<?php pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/pre_input_errors"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>

<form action="firewall_rules_edit.php" method="post" name="iform" id="iform">
<input type='hidden' name="ruleid" value="<?=(isset($pconfig['ruleid'])&&$pconfig['ruleid']>0)?htmlspecialchars($pconfig['ruleid']):''?>" />

	<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="firewall rules edit">
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?=gettext("Edit Firewall rule");?></td>
		</tr>
<?php
		// Allow extending of the firewall edit page and include custom input validation
		pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/htmlphpearly");
?>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Action");?></td>
			<td width="78%" class="vtable">
				<select name="type" class="formselect">
					<?php $types = explode(" ", "Pass Block Reject"); foreach ($types as $type): ?>
					<option value="<?=strtolower($type);?>" <?php if (strtolower($type) == strtolower($pconfig['type'])) echo "selected=\"selected\""; ?>>
					<?=htmlspecialchars($type);?>
					</option>
					<?php endforeach; ?>
<?php if ($if == "FloatingRules" || isset($pconfig['floating'])): ?>
					<option value="match" <?php if ("match" == strtolower($pconfig['type'])) echo "selected=\"selected\""; ?>>Match</option>
<?php endif; ?>
				</select>
				<br />
				<span class="vexpl">
					<?=gettext("Choose what to do with packets that match the criteria specified below.");?> <br />
					<?=gettext("Hint: the difference between block and reject is that with reject, a packet (TCP RST or ICMP port unreachable for UDP) is returned to the sender, whereas with block the packet is dropped silently. In either case, the original packet is discarded.");?>
				</span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Disabled");?></td>
			<td width="78%" class="vtable">
				<input name="disabled" type="checkbox" id="disabled" value="yes" <?php if ($pconfig['disabled']) echo "checked=\"checked\""; ?> />
				<strong><?=gettext("Disable this rule");?></strong><br />
				<span class="vexpl"><?=gettext("Set this option to disable this rule without removing it from the list.");?></span>
			</td>
		</tr>
<?php if ($if == "FloatingRules" || isset($pconfig['floating'])): ?>
		<tr>
			<td width="22%" valign="top" class="vncellreq">
				<?=gettext("Quick");?>
			</td>
			<td width="78%" class="vtable">
				<input name="quick" type="checkbox" id="quick" value="yes" <?php if ($pconfig['quick']) echo "checked=\"checked\""; ?> />
				<strong><?=gettext("Apply the action immediately on match.");?></strong><br />
				<span class="vexpl"><?=gettext("Set this option if you need to apply this action to traffic that matches this rule immediately.");?></span>
			</td>
		</tr>
<?php endif; ?>
<?php $edit_disabled = ""; ?>
<?php if( isset($pconfig['associated-rule-id']) ): ?>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Associated filter rule");?></td>
			<td width="78%" class="vtable">
				<span class="red"><strong><?=gettext("Note: ");?></strong></span><?=gettext("This is associated to a NAT rule.");?><br />
				<?=gettext("You cannot edit the interface, protocol, source, or destination of associated filter rules.");?><br />
				<br />
				<?php
					$edit_disabled = "disabled";
					if (is_array($config['nat']['rule'])) {
						foreach( $config['nat']['rule'] as $index => $nat_rule ) {
							if( isset($nat_rule['associated-rule-id']) && $nat_rule['associated-rule-id']==$pconfig['associated-rule-id'] ) {
								echo "<a href=\"firewall_nat_edit.php?id={$index}\">" . gettext("View the NAT rule") . "</a><br />";
								break;
							}
						}
					}
					echo "<input name='associated-rule-id' id='associated-rule-id' type='hidden' value='{$pconfig['associated-rule-id']}' />";
					if (!empty($pconfig['interface']))
						echo "<input name='interface' id='interface' type='hidden' value='{$pconfig['interface']}' />";
				?>
				<script type="text/javascript">
				//<![CDATA[
				editenabled = 0;
				//]]>
				</script>
			</td>
		</tr>
<?php endif; ?>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Interface");?></td>
			<td width="78%" class="vtable">
<?php if ($if == "FloatingRules" || isset($pconfig['floating'])): ?>
				<select name="interface[]" title="Select interfaces..." multiple="multiple" style="width:350px;" class="chzn-select" tabindex="2" <?=$edit_disabled;?>>
<?php else: ?>
				<select name="interface" class="formselect" <?=$edit_disabled;?>>
<?php endif;
				/* add group interfaces */
				if (is_array($config['ifgroups']['ifgroupentry']))
					foreach($config['ifgroups']['ifgroupentry'] as $ifgen)
						if (have_ruleint_access($ifgen['ifname']))
							$interfaces[$ifgen['ifname']] = $ifgen['ifname'];
				$ifdescs = get_configured_interface_with_descr();
				// Allow extending of the firewall edit page and include custom input validation
				pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/pre_interfaces_edit");
				foreach ($ifdescs as $ifent => $ifdesc)
					if(have_ruleint_access($ifent))
							$interfaces[$ifent] = $ifdesc;
					if ($config['l2tp']['mode'] == "server")
						if(have_ruleint_access("l2tp"))
							$interfaces['l2tp'] = "L2TP VPN";
					if ($config['pptpd']['mode'] == "server")
						if(have_ruleint_access("pptp"))
							$interfaces['pptp'] = "PPTP VPN";

					if (is_pppoe_server_enabled() && have_ruleint_access("pppoe"))
						$interfaces['pppoe'] = "PPPoE VPN";
					/* add ipsec interfaces */
					if (isset($config['ipsec']['enable']) || isset($config['ipsec']['client']['enable']))
						if(have_ruleint_access("enc0"))
							$interfaces["enc0"] = "IPsec";
					/* add openvpn/tun interfaces */
					if  ($config['openvpn']["openvpn-server"] || $config['openvpn']["openvpn-client"])
						$interfaces["openvpn"] = "OpenVPN";
					if (is_array($pconfig['interface']))
						$pconfig['interface'] = implode(",", $pconfig['interface']);
					$selected_interfaces = explode(",", $pconfig['interface']);
					foreach ($interfaces as $iface => $ifacename): ?>
						<option value="<?=$iface;?>" <?php if ($pconfig['interface'] <> "" && ( strcasecmp($pconfig['interface'], $iface) == 0 || in_array($iface, $selected_interfaces) )) echo "selected=\"selected\""; ?>><?=$ifacename?></option>
<?php 				endforeach; ?>
				</select>
				<br />
				<span class="vexpl"><?=gettext("Choose on which interface packets must come in to match this rule.");?></span>
			</td>
		</tr>
<?php if ($if == "FloatingRules" || isset($pconfig['floating'])): ?>
		<tr>
			<td width="22%" valign="top" class="vncellreq">
				<?=gettext("Direction");?>
			</td>
			<td width="78%" class="vtable">
				<select name="direction" class="formselect">
					<?php      $directions = array('any','in','out');
				foreach ($directions as $direction): ?>
				<option value="<?=$direction;?>"
					<?php if ($direction == $pconfig['direction']): ?>
						selected="selected"
					<?php endif; ?>
					><?=$direction;?></option>
				<?php endforeach; ?>
				</select>
				<input type="hidden" id="floating" name="floating" value="floating" />
			</td>
		<tr>
<?php endif; ?>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("TCP/IP Version");?></td>
			<td width="78%" class="vtable">
				<select name="ipprotocol" class="formselect">
					<?php      $ipproto = array('inet' => 'IPv4','inet6' => 'IPv6', 'inet46' => 'IPv4+IPv6' );
				foreach ($ipproto as $proto => $name): ?>
				<option value="<?=$proto;?>"
					<?php if ($proto == $pconfig['ipprotocol']): ?>
						selected="selected"
					<?php endif; ?>
					><?=$name;?></option>
				<?php endforeach; ?>
				</select>
				<strong><?=gettext("Select the Internet Protocol version this rule applies to");?></strong><br />
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Protocol");?></td>
			<td width="78%" class="vtable">
				<select <?=$edit_disabled;?> name="proto" class="formselect" onchange="proto_change()">
<?php
				$protocols = explode(" ", "TCP UDP TCP/UDP ICMP ESP AH GRE IPV6 IGMP PIM OSPF any carp pfsync");
				foreach ($protocols as $proto): ?>
					<option value="<?=strtolower($proto);?>" <?php if (strtolower($proto) == $pconfig['proto']) echo "selected=\"selected\""; ?>><?=htmlspecialchars($proto);?></option>
<?php 			endforeach; ?>
				</select>
				<br />
				<span class="vexpl"><?=gettext("Choose which IP protocol this rule should match.");?> <br /> <?=gettext("Hint: in most cases, you should specify ");?><em>TCP</em> &nbsp;<?=gettext("here.");?></span>
			</td>
		</tr>
		<tr id="icmpbox">
			<td valign="top" class="vncell"><?=gettext("ICMP type");?></td>
			<td class="vtable">
				<select <?=$edit_disabled;?> name="icmptype" class="formselect">
<?php
				$icmptypes = array(
				"" => gettext("any"),
				"echoreq" => gettext("Echo request"),
				"echorep" => gettext("Echo reply"),
				"unreach" => gettext("Destination unreachable"),
				"squench" => gettext("Source quench"),
				"redir" => gettext("Redirect"),
				"althost" => gettext("Alternate Host"),
				"routeradv" => gettext("Router advertisement"),
				"routersol" => gettext("Router solicitation"),
				"timex" => gettext("Time exceeded"),
				"paramprob" => gettext("Invalid IP header"),
				"timereq" => gettext("Timestamp"),
				"timerep" => gettext("Timestamp reply"),
				"inforeq" => gettext("Information request"),
				"inforep" => gettext("Information reply"),
				"maskreq" => gettext("Address mask request"),
				"maskrep" => gettext("Address mask reply")
				);

				foreach ($icmptypes as $icmptype => $descr): ?>
					<option value="<?=$icmptype;?>" <?php if ($icmptype == $pconfig['icmptype']) echo "selected=\"selected\""; ?>><?=htmlspecialchars($descr);?></option>
<?php 			endforeach; ?>
			</select>
			<br />
			<span class="vexpl"><?=gettext("If you selected ICMP for the protocol above, you may specify an ICMP type here.");?></span>
		</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Source");?></td>
			<td width="78%" class="vtable">
				<input <?=$edit_disabled;?> name="srcnot" type="checkbox" id="srcnot" value="yes" <?php if ($pconfig['srcnot']) echo "checked=\"checked\""; ?> />
				<strong><?=gettext("not");?></strong>
				<br />
				<?=gettext("Use this option to invert the sense of the match.");?>
				<br />
				<br />
				<table border="0" cellspacing="0" cellpadding="0">
					<tr>
						<td><?=gettext("Type:");?>&nbsp;&nbsp;</td>
						<td>
							<select <?=$edit_disabled;?> name="srctype" class="formselect" onchange="typesel_change()">
<?php
								$sel = is_specialnet($pconfig['src']); ?>
								<option value="any"     <?php if ($pconfig['src'] == "any") { echo "selected=\"selected\""; } ?>><?=gettext("any");?></option>
								<option value="single"
						<?php  if (!$sel &&
							    ((is_ipaddrv6($pconfig['src']) && $pconfig['srcmask'] == 128) ||
							    (is_ipaddrv4($pconfig['src']) && $pconfig['srcmask'] == 32) || is_alias($pconfig['src'])))
								{ echo "selected=\"selected\""; $sel = 1; }
						?>
								> <?=gettext("Single host or alias");?></option>
								<option value="network" <?php if (!$sel) echo "selected=\"selected\""; ?>><?=gettext("Network");?></option>
								<?PHP	if (isset($a_filter[$id]['floating']) || $if == "FloatingRules"): ?>
								<option value="(self)" <?PHP if ($pconfig['src'] == "(self)") echo "selected=\"selected\""; ?>><?=gettext("This Firewall (self)");?></option>
								<?PHP endif;?>
								<?php if(have_ruleint_access("pptp")): ?>
								<option value="pptp"    <?php if ($pconfig['src'] == "pptp") { echo "selected=\"selected\""; } ?>><?=gettext("PPTP clients");?></option>
								<?php endif; ?>
								<?php if(have_ruleint_access("pppoe")): ?>
								<option value="pppoe"   <?php if ($pconfig['src'] == "pppoe") { echo "selected=\"selected\""; } ?>><?=gettext("PPPoE clients");?></option>
								<?php endif; ?>
								<?php if(have_ruleint_access("l2tp")): ?>
								<option value="l2tp"   <?php if ($pconfig['src'] == "l2tp") { echo "selected=\"selected\""; } ?>><?=gettext("L2TP clients");?></option>
								<?php endif; ?>
<?php
								foreach ($ifdisp as $ifent => $ifdesc): ?>
								<?php if(have_ruleint_access($ifent)): ?>
									<option value="<?=$ifent;?>" <?php if ($pconfig['src'] == $ifent) { echo "selected=\"selected\""; } ?>><?=htmlspecialchars($ifdesc);?> <?=gettext("net");?></option>
									<option value="<?=$ifent;?>ip"<?php if ($pconfig['src'] ==  $ifent . "ip") { echo "selected=\"selected\""; } ?>>
										<?=$ifdesc?> <?=gettext("address");?>
									</option>
								<?php endif; ?>
<?php 							endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<td><?=gettext("Address:");?>&nbsp;&nbsp;</td>
						<td>
							<input <?=$edit_disabled;?> autocomplete='off' name="src" type="text" class="formfldalias ipv4v6" id="src" size="20" value="<?php if (!is_specialnet($pconfig['src'])) echo htmlspecialchars($pconfig['src']);?>" /> /
							<select <?=$edit_disabled;?> name="srcmask" class="formselect ipv4v6" id="srcmask">
<?php						for ($i = 127; $i > 0; $i--): ?>
								<option value="<?=$i;?>" <?php if ($i == $pconfig['srcmask']) echo "selected=\"selected\""; ?>><?=$i;?></option>
<?php 						endfor; ?>
							</select>
						</td>
					</tr>
				</table>
				<div id="showadvancedboxspr">
					<p>
					<input <?=$edit_disabled;?> type="button" onclick="show_source_port_range()" value="<?=gettext("Advanced"); ?>" /> - <?=gettext("Show source port range");?>
					</p>
				</div>
			</td>
		</tr>
		<tr style="display:none" id="sprtable">
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Source port range");?></td>
			<td width="78%" class="vtable">
				<table border="0" cellspacing="0" cellpadding="0">
					<tr>
						<td><?=gettext("from:");?>&nbsp;&nbsp;</td>
						<td>
							<select <?=$edit_disabled;?> name="srcbeginport" class="formselect" onchange="src_rep_change();ext_change()">
								<option value="">(<?=gettext("other"); ?>)</option>
								<option value="any" <?php $bfound = 0; if ($pconfig['srcbeginport'] == "any") { echo "selected=\"selected\""; $bfound = 1; } ?>><?=gettext("any");?></option>
<?php 							foreach ($wkports as $wkport => $wkportdesc): ?>
									<option value="<?=$wkport;?>" <?php if ($wkport == $pconfig['srcbeginport']) { echo "selected=\"selected\""; $bfound = 1; } ?>><?=htmlspecialchars("{$wkportdesc} ({$wkport})");?></option>
<?php 							endforeach; ?>
							</select>
							<input <?=$edit_disabled;?> autocomplete='off' class="formfldalias" name="srcbeginport_cust" id="srcbeginport_cust" type="text" size="5" value="<?php if (!$bfound && $pconfig['srcbeginport']) echo htmlspecialchars($pconfig['srcbeginport']); ?>" />
						</td>
					</tr>
					<tr>
						<td><?=gettext("to:");?></td>
						<td>
							<select <?=$edit_disabled;?> name="srcendport" class="formselect" onchange="ext_change()">
								<option value="">(<?=gettext("other"); ?>)</option>
								<option value="any" <?php $bfound = 0; if ($pconfig['srcendport'] == "any") { echo "selected=\"selected\""; $bfound = 1; } ?>><?=gettext("any");?></option>
<?php							foreach ($wkports as $wkport => $wkportdesc): ?>
									<option value="<?=$wkport;?>" <?php if ($wkport == $pconfig['srcendport']) { echo "selected=\"selected\""; $bfound = 1; } ?>><?=htmlspecialchars("{$wkportdesc} ({$wkport})");?></option>
<?php							endforeach; ?>
							</select>
							<input <?=$edit_disabled;?> autocomplete='off' class="formfldalias" name="srcendport_cust" id="srcendport_cust" type="text" size="5" value="<?php if (!$bfound && $pconfig['srcendport']) echo htmlspecialchars($pconfig['srcendport']); ?>" />
						</td>
					</tr>
				</table>
				<br />
				<span class="vexpl"><?=gettext("Specify the source port or port range for this rule."); ?> <b><?=gettext("This is usually"); ?> <em><?=gettext("random"); ?></em> <?=gettext("and almost never equal to the destination port range (and should usually be"); ?> &quot;<?=gettext("any"); ?>&quot;).</b><br /><?=gettext("Hint: you can leave the"); ?> <em><?=gettext("'to'"); ?></em> <?=gettext("field empty if you only want to filter a single port.");?></span><br />
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Destination");?></td>
			<td width="78%" class="vtable">
				<input <?=$edit_disabled;?> name="dstnot" type="checkbox" id="dstnot" value="yes" <?php if ($pconfig['dstnot']) echo "checked=\"checked\""; ?> />
				<strong><?=gettext("not");?></strong>
					<br />
				<?=gettext("Use this option to invert the sense of the match.");?>
					<br />
					<br />
				<table border="0" cellspacing="0" cellpadding="0">
					<tr>
						<td><?=gettext("Type:");?>&nbsp;&nbsp;</td>
						<td>
							<select <?=$edit_disabled;?> name="dsttype" class="formselect" onchange="typesel_change()">
<?php
								$sel = is_specialnet($pconfig['dst']); ?>
								<option value="any" <?php if ($pconfig['dst'] == "any") { echo "selected=\"selected\""; } ?>><?=gettext("any");?></option>
								<option value="single"
								<?php  if (!$sel &&
									    ((is_ipaddrv6($pconfig['dst']) && $pconfig['dstmask'] == 128) ||
									    (is_ipaddrv4($pconfig['dst']) && $pconfig['dstmask'] == 32) || is_alias($pconfig['dst'])))
										{ echo "selected=\"selected\""; $sel = 1; }
								?>
								><?=gettext("Single host or alias");?></option>
								<option value="network" <?php if (!$sel) echo "selected=\"selected\""; ?>><?=gettext("Network");?></option>
								<option value="(self)" <?PHP if ($pconfig['dst'] == "(self)") echo "selected=\"selected\""; ?>><?=gettext("This Firewall (self)");?></option>
								<?php if(have_ruleint_access("pptp")): ?>
								<option value="pptp" <?php if ($pconfig['dst'] == "pptp") { echo "selected=\"selected\""; } ?>><?=gettext("PPTP clients");?></option>
								<?php endif; ?>
								<?php if(have_ruleint_access("pppoe")): ?>
								<option value="pppoe" <?php if ($pconfig['dst'] == "pppoe") { echo "selected=\"selected\""; } ?>><?=gettext("PPPoE clients");?></option>
								<?php endif; ?>
								<?php if(have_ruleint_access("l2tp")): ?>
								<option value="l2tp" <?php if ($pconfig['dst'] == "l2tp") { echo "selected=\"selected\""; } ?>><?=gettext("L2TP clients");?></option>
								<?php endif; ?>

<?php 							foreach ($ifdisp as $if => $ifdesc): ?>
								<?php if(have_ruleint_access($if)): ?>
									<option value="<?=$if;?>" <?php if ($pconfig['dst'] == $if) { echo "selected=\"selected\""; } ?>><?=htmlspecialchars($ifdesc);?> <?=gettext("net");?></option>
									<option value="<?=$if;?>ip"<?php if ($pconfig['dst'] == $if . "ip") { echo "selected=\"selected\""; } ?>>
										<?=$ifdesc;?> <?=gettext("address");?>
									</option>
								<?php endif; ?>
<?php 							endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<td><?=gettext("Address:");?>&nbsp;&nbsp;</td>
						<td>
							<input <?=$edit_disabled;?> autocomplete='off' name="dst" type="text" class="formfldalias ipv4v6" id="dst" size="20" value="<?php if (!is_specialnet($pconfig['dst'])) echo htmlspecialchars($pconfig['dst']);?>" />
							/
							<select <?=$edit_disabled;?> name="dstmask" class="formselect ipv4v6" id="dstmask">
<?php
							for ($i = 127; $i > 0;
$i--): ?>
								<option value="<?=$i;?>" <?php if ($i == $pconfig['dstmask']) echo "selected=\"selected\""; ?>><?=$i;?></option>
<?php						endfor; ?>
							</select>
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr id="dprtr">
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Destination port range ");?></td>
			<td width="78%" class="vtable">
				<table border="0" cellspacing="0" cellpadding="0">
					<tr>
						<td><?=gettext("from:");?>&nbsp;&nbsp;</td>
						<td>
							<select <?=$edit_disabled;?> name="dstbeginport" class="formselect" onchange="dst_rep_change();ext_change()">
								<option value="">(<?=gettext("other"); ?>)</option>
								<option value="any" <?php $bfound = 0; if ($pconfig['dstbeginport'] == "any") { echo "selected=\"selected\""; $bfound = 1; } ?>><?=gettext("any");?></option>
<?php 							foreach ($wkports as $wkport => $wkportdesc): ?>
									<option value="<?=$wkport;?>" <?php if ($wkport == $pconfig['dstbeginport']) { echo "selected=\"selected\""; $bfound = 1; }?>><?=htmlspecialchars("{$wkportdesc} ({$wkport})");?></option>
<?php 							endforeach; ?>
							</select>
							<input <?=$edit_disabled;?> autocomplete='off' class="formfldalias" name="dstbeginport_cust" id="dstbeginport_cust" type="text" size="5" value="<?php if (!$bfound && $pconfig['dstbeginport']) echo htmlspecialchars($pconfig['dstbeginport']); ?>" />
						</td>
					</tr>
					<tr>
						<td><?=gettext("to:");?></td>
						<td>
							<select <?=$edit_disabled;?> name="dstendport" class="formselect" onchange="ext_change()">
								<option value="">(<?=gettext("other"); ?>)</option>
								<option value="any" <?php $bfound = 0; if ($pconfig['dstendport'] == "any") { echo "selected=\"selected\""; $bfound = 1; } ?>><?=gettext("any");?></option>
<?php							foreach ($wkports as $wkport => $wkportdesc): ?>
									<option value="<?=$wkport;?>" <?php if ($wkport == $pconfig['dstendport']) { echo "selected=\"selected\""; $bfound = 1; } ?>><?=htmlspecialchars("{$wkportdesc} ({$wkport})");?></option>
<?php 							endforeach; ?>
							</select>
								<input <?=$edit_disabled;?> autocomplete='off' class="formfldalias" name="dstendport_cust" id="dstendport_cust" type="text" size="5" value="<?php if (!$bfound && $pconfig['dstendport']) echo htmlspecialchars($pconfig['dstendport']); ?>" />
						</td>
					</tr>
				</table>
				<br />
				<span class="vexpl">
					<?=gettext("Specify the port or port range for the destination of the packet for this rule.");?>
					<br />
					<?=gettext("Hint: you can leave the"); ?> <em><?=gettext("'to'"); ?></em> <?=gettext("field empty if you only want to filter a single port");?>
				</span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncellreq"><?=gettext("Log");?></td>
			<td width="78%" class="vtable">
				<input name="log" type="checkbox" id="log" value="yes" <?php if ($pconfig['log']) echo "checked=\"checked\""; ?> />
				<strong><?=gettext("Log packets that are handled by this rule");?></strong>
				<br />
				<span class="vexpl"><?=gettext("Hint: the firewall has limited local log space. Don't turn on logging for everything. If you want to do a lot of logging, consider using a remote syslog server"); ?> (<?=gettext("see the"); ?> <a href="diag_logs_settings.php"><?=gettext("Diagnostics: System logs: Settings"); ?></a> <?=gettext("page"); ?>).</span>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Description");?></td>
			<td width="78%" class="vtable">
				<input name="descr" type="text" class="formfld unknown" id="descr" size="52" maxlength="52" value="<?=htmlspecialchars($pconfig['descr']);?>" />
				<br />
				<span class="vexpl"><?=gettext("You may enter a description here for your reference.");?></span>
			</td>
		</tr>
<?php		if (!isset($id) || !($a_filter[$id] && firewall_check_for_advanced_options($a_filter[$id]) <> "")): ?>
		<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%">
				&nbsp;<br />&nbsp;
				<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" />
				<input type="button" class="formbtn" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=$referer;?>'" />
<?php			if (isset($id) && $a_filter[$id]): ?>
					<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
<?php 			endif; ?>
				<input name="after" type="hidden" value="<?=htmlspecialchars($after);?>" />
			</td>
		</tr>
<?php		endif; ?>
		<tr>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?=gettext("Advanced features");?></td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Source OS");?></td>
			<td width="78%" class="vtable">
				<div id="showadvsourceosbox" <?php if ($pconfig['os']) echo "style='display:none'"; ?>>
					<input type="button" onclick="show_advanced_sourceos()" value="<?=gettext("Advanced"); ?>" /> - <?=gettext("Show advanced option");?>
				</div>
				<div id="showsourceosadv" <?php if (empty($pconfig['os'])) echo "style='display:none'"; ?>>
					<?=gettext("OS Type:");?>&nbsp;
					<select name="os" id="os" class="formselect">
						<option value="" <?php if (empty($pconfig['os'])) echo "selected=\"selected\""; ?>>Any</option>
<?php
						foreach ($ostypes as $ostype): ?>
							<option value="<?=$ostype;?>" <?php if ($ostype == $pconfig['os']) echo "selected=\"selected\""; ?>><?=htmlspecialchars($ostype);?></option>
<?php
					endforeach;
?>
					</select>
					<br />
					<?=gettext("Note: this only works for TCP rules. General OS choice matches all subtypes.");?>
				</div>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Diffserv Code Point");?></td>
			<td width="78%" class="vtable">
				<div id="dsadv" <?php if ($pconfig['dscp']) echo "style='display:none'"; ?>>
					<input type="button" onclick="show_dsdiv();" value="<?=gettext("Advanced"); ?>" /> - <?=gettext("Show advanced option");?>
				</div>
				<div id="dsdivmain" <?php if (empty($pconfig['dscp'])) echo "style='display:none'"; ?>>
					<select name="dscp" id="dscp">
						<option value=""></option>
						<?php foreach($firewall_rules_dscp_types as $frdt): ?>
							<option value="<?=$frdt?>"<?php if($pconfig['dscp'] == $frdt) echo " selected=\"selected\""; ?>><?=$frdt?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Advanced Options");?></td>
			<td width="78%" class="vtable">
			<div id="aoadv" <?php if (is_aoadv_used($pconfig)) echo "style='display:none'"; ?>>
				<input type="button" onclick="show_aodiv();" value="<?=gettext("Advanced"); ?>" /> - <?=gettext("Show advanced option");?>
			</div>
			<div id="aodivmain" <?php if (!is_aoadv_used($pconfig)) echo "style='display:none'"; ?>>
				<input type="checkbox" id="allowopts" value="yes" name="allowopts"<?php if($pconfig['allowopts'] == true) echo " checked=\"checked\""; ?> />
				<br /><span class="vexpl"><?=gettext("This allows packets with IP options to pass. Otherwise they are blocked by default. This is usually only seen with multicast traffic.");?>
				</span><p>
				<input type="checkbox" id="disablereplyto" value="yes" name="disablereplyto"<?php if($pconfig['disablereplyto'] == true) echo " checked=\"checked\""; ?> />
				<br /><span class="vexpl"><?=gettext("This will disable auto generated reply-to for this rule.");?>
				</span></p><p>
				<input name="tag" id="tag" value="<?=htmlspecialchars($pconfig['tag']);?>" />
				<br /><span class="vexpl"><?=gettext("You can mark a packet matching this rule and use this mark to match on other NAT/filter rules. It is called"); ?> <b><?=gettext("Policy filtering"); ?></b>
				</span></p><p>
				<input name="tagged" id="tagged" value="<?=htmlspecialchars($pconfig['tagged']);?>" />
				<br /><span class="vexpl"><?=gettext("You can match packet on a mark placed before on another rule.")?>
				</span></p><p>
				<input name="max" id="max" value="<?php echo htmlspecialchars($pconfig['max']) ?>" /><br /><?=gettext(" Maximum state entries this rule can create");?></p><p>
				<input name="max-src-nodes" id="max-src-nodes" value="<?php echo htmlspecialchars($pconfig['max-src-nodes']) ?>" /><br /><?=gettext(" Maximum number of unique source hosts");?></p><p>
				<input name="max-src-conn" id="max-src-conn" value="<?php echo htmlspecialchars($pconfig['max-src-conn']) ?>" /><br /><?=gettext(" Maximum number of established connections per host (TCP only)");?></p><p>
				<input name="max-src-states" id="max-src-states" value="<?php echo htmlspecialchars($pconfig['max-src-states']) ?>" /><br /><?=gettext(" Maximum state entries per host");?></p><p>
				<input name="max-src-conn-rate" id="max-src-conn-rate" value="<?php echo htmlspecialchars($pconfig['max-src-conn-rate']) ?>" /> /
				<select name="max-src-conn-rates" id="max-src-conn-rates">
					<option value=""<?php if(intval($pconfig['max-src-conn-rates']) < 1) echo " selected=\"selected\""; ?>></option>
<?php				for($x=1; $x<255; $x++) {
						if($x == $pconfig['max-src-conn-rates']) $selected = " selected=\"selected\""; else $selected = "";
						echo "<option value=\"{$x}\"{$selected}>{$x}</option>\n";
					} ?>
				</select><br />
				<?=gettext("Maximum new connections per host / per second(s) (TCP only)");?>
				</p><p>
				<input name="statetimeout" value="<?php echo htmlspecialchars($pconfig['statetimeout']) ?>" /><br />
				<?=gettext("State Timeout in seconds (TCP only)");?>
				</p>
				<p><strong><?=gettext("Note: Leave fields blank to disable that feature.");?></strong></p>
			</div>
			</td>
		</tr>
		<tr id="tcpflags">
			<td width="22%" valign="top" class="vncell"><?=gettext("TCP flags");?></td>
			<td width="78%" class="vtable">
			<div id="showtcpflagsbox" <?php if ($pconfig['tcpflags_any'] || $pconfig['tcpflags1'] || $pconfig['tcpflags2']) echo "style='display:none'"; ?>>
				<input type="button" onclick="show_advanced_tcpflags()" value="<?=gettext("Advanced"); ?>" /> - <?=gettext("Show advanced option");?>
			</div>
			<div id="showtcpflagsadv" <?php if (empty($pconfig['tcpflags_any']) && empty($pconfig['tcpflags1']) && empty($pconfig['tcpflags2'])) echo "style='display:none'"; ?>>
			<div id="tcpheader" align="center">
			<table border="0" cellspacing="0" cellpadding="0">
			<?php
				$setflags = explode(",", $pconfig['tcpflags1']);
				$outofflags = explode(",", $pconfig['tcpflags2']);
				$header = "<td width='40' class='nowrap'></td>";
				$tcpflags1 = "<td width='40' class='nowrap'>set</td>";
				$tcpflags2 = "<td width='40' class='nowrap'>out of</td>";
				foreach ($tcpflags as $tcpflag) {
					$header .= "<td  width='40' class='nowrap'><strong>" . strtoupper($tcpflag) . "</strong></td>\n";
					$tcpflags1 .= "<td  width='40' class='nowrap'> <input type='checkbox' name='tcpflags1_{$tcpflag}' value='on' ";
					if (array_search($tcpflag, $setflags) !== false)
						$tcpflags1 .= "checked=\"checked\"";
					$tcpflags1 .= " /></td>\n";
					$tcpflags2 .= "<td  width='40' class='nowrap'> <input type='checkbox' name='tcpflags2_{$tcpflag}' value='on' ";
					if (array_search($tcpflag, $outofflags) !== false)
						$tcpflags2 .= "checked=\"checked\"";
					$tcpflags2 .= " /></td>\n";
				}
				echo "<tr id='tcpheader'>{$header}</tr>\n";
				echo "<tr id='tcpflags1'>{$tcpflags1}</tr>\n";
				echo "<tr id='tcpflags2'>{$tcpflags2}</tr>\n";
			?>
			</table>
			</div>
			<br /><center>
			<input onclick='tcpflags_anyclick(this);' type='checkbox' name='tcpflags_any' value='on' <?php if ($pconfig['tcpflags_any']) echo "checked=\"checked\""; ?> /><strong><?=gettext("Any flags.");?></strong><br /></center>
			<br />
			<span class="vexpl"><?=gettext("Use this to choose TCP flags that must ".
			"be set or cleared for this rule to match.");?></span>
			</div>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("State Type");?></td>
			<td width="78%" class="vtable">
				<div id="showadvstatebox" <?php if (!empty($pconfig['nopfsync']) || (!empty($pconfig['statetype']) && $pconfig['statetype'] != "keep state")) echo "style='display:none'"; ?>>
					<input type="button" onclick="show_advanced_state()" value="<?=gettext("Advanced"); ?>" /> - <?=gettext("Show advanced option");?>
				</div>
				<div id="showstateadv" <?php if (empty($pconfig['nopfsync']) && (empty($pconfig['statetype']) || $pconfig['statetype'] == "keep state")) echo "style='display:none'"; ?>>
					<input name="nopfsync" type="checkbox" id="nopfsync" value="yes" <?php if ($pconfig['nopfsync']) echo "checked=\"checked\""; ?> />
					<span class="vexpl">
						NO pfsync<br />
						<?=gettext("Hint: This prevents states created by this rule to be sync'ed over pfsync.");?><br />
					</span><br />
					<select name="statetype">
						<option value="keep state" <?php if(!isset($pconfig['statetype']) or $pconfig['statetype'] == "keep state") echo "selected=\"selected\""; ?>><?=gettext("keep state");?></option>
						<option value="sloppy state" <?php if($pconfig['statetype'] == "sloppy state") echo "selected=\"selected\""; ?>><?=gettext("sloppy state");?></option>
						<option value="synproxy state"<?php if($pconfig['statetype'] == "synproxy state")  echo "selected=\"selected\""; ?>><?=gettext("synproxy state");?></option>
						<option value="none"<?php if($pconfig['statetype'] == "none") echo "selected=\"selected\""; ?>><?=gettext("none");?></option>
					</select><br />
					<span class="vexpl">
						<?=gettext("Hint: Select which type of state tracking mechanism you would like to use.  If in doubt, use keep state.");?>
					</span>
					<table width="90%">
						<tr><td width="25%"><ul><li><?=gettext("keep state");?></li></ul></td><td><?=gettext("Works with all IP protocols.");?></td></tr>
						<tr><td width="25%"><ul><li><?=gettext("sloppy state");?></li></ul></td><td><?=gettext("Works with all IP protocols.");?></td></tr>
						<tr><td width="25%"><ul><li><?=gettext("synproxy state");?></li></ul></td><td><?=gettext("Proxies incoming TCP connections to help protect servers from spoofed TCP SYN floods. This option includes the functionality of keep state and modulate state combined.");?></td></tr>
						<tr><td width="25%"><ul><li><?=gettext("none");?></li></ul></td><td><?=gettext("Do not use state mechanisms to keep track.  This is only useful if you're doing advanced queueing in certain situations.  Please check the documentation.");?></td></tr>
					</table>
				</div>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("No XMLRPC Sync");?></td>
			<td width="78%" class="vtable">
				<div id="showadvnoxmlrpcsyncbox" <?php if ($pconfig['nosync']) echo "style='display:none'"; ?>>
					<input type="button" onclick="show_advanced_noxmlrpc()" value="<?=gettext("Advanced"); ?>" /> - <?=gettext("Show advanced option");?>
				</div>
				<div id="shownoxmlrpcadv" <?php if (empty($pconfig['nosync'])) echo "style='display:none'"; ?>>
					<input type="checkbox" name="nosync"<?php if($pconfig['nosync']) echo " checked=\"checked\""; ?> /><br />
					<?=gettext("Hint: This prevents the rule on Master from automatically syncing to other CARP members. This does NOT prevent the rule from being overwritten on Slave.");?>
				</div>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("802.1p");?></td>
			<td width="78%" class="vtable">
				<div id="showadvvlanpriobox" <?php if (!empty($pconfig['vlanprio'])) echo "style='display:none'"; ?>>
					<input type="button" onclick="show_advanced_vlanprio()" value="<?=gettext("Advanced"); ?>" /> - <?=gettext("Show advanced option");?>
				</div>
				<div id="showvlanprioadv" <?php if (empty($pconfig['vlanprio'])) echo "style='display:none'"; ?>>
					<?php $vlanprio = array("none", "be", "bk", "ee", "ca", "vi", "vo", "ic", "nc"); ?>
<?php
					$opts = "";
					foreach($vlanprio as $vprio) {
						if ($vprio == $pconfig['vlanprio'])
							$selected = " selected=\"selected\"";
						else
							$selected = "";
						if ($vprio == "none")
							$opts .= "<option value=\"\">{$vprio}</option>\n";
						else
							$opts .= "<option value=\"{$vprio}\" {$selected}>" . strtoupper($vprio) . "</option>\n";
					}

					$optsset = "";
					foreach($vlanprio as $vprioset) {
						if ($vprioset == $pconfig['vlanprioset'])
							$selected = " selected=\"selected\"";
						else
							$selected = "";
						if ($vprioset == "none")
							$optsset .= "<option value=\"\">{$vprioset}</option>\n";
						else
							$optsset .= "<option value=\"{$vprioset}\" {$selected}>" . strtoupper($vprioset) . "</option>\n";
					}
?>
					<select name='vlanprio'>
					<?php echo $opts; ?>
					</select>
					<p><?=gettext("Choose 802.1p priority to match on");?></p>
					<select name='vlanprioset'>
					<?php echo $optsset; ?>
					</select>
					<p><?=gettext("Choose 802.1p priority to apply");?></p>
				</div>
			</td>
		</tr>
		<?php
			//build list of schedules
			$schedules = array();
			$schedules[] = "none";//leave none to leave rule enabled all the time
			if(is_array($config['schedules']['schedule'])) {
				foreach ($config['schedules']['schedule'] as $schedule) {
					if ($schedule['name'] <> "")
						$schedules[] = $schedule['name'];
				}
			}
		?>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Schedule");?></td>
			<td width="78%" class="vtable">
				<div id="showadvschedulebox" <?php if (!empty($pconfig['sched'])) echo "style='display:none'"; ?>>
					<input type="button" onclick="show_advanced_schedule()" value="<?=gettext("Advanced"); ?>" /> - <?=gettext("Show advanced option");?>
				</div>
				<div id="showscheduleadv" <?php if (empty($pconfig['sched'])) echo "style='display:none'"; ?>>
					<select name='sched'>
<?php
					foreach($schedules as $schedule) {
						if($schedule == $pconfig['sched']) {
							$selected = " selected=\"selected\"";
						} else {
							$selected = "";
						}
						if ($schedule == "none") {
							echo "<option value=\"\" {$selected}>{$schedule}</option>\n";
						} else {
							echo "<option value=\"{$schedule}\" {$selected}>{$schedule}</option>\n";
						}
					}
?>
					</select>
					<p><?=gettext("Leave as 'none' to leave the rule enabled all the time.");?></p>
				</div>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Gateway");?></td>
			<td width="78%" class="vtable">
				<div id="showadvgatewaybox" <?php if (!empty($pconfig['gateway'])) echo "style='display:none'"; ?>>
					<input type="button" onclick="show_advanced_gateway()" value="<?=gettext("Advanced"); ?>" /> - <?=gettext("Show advanced option");?>
				</div>
				<div id="showgatewayadv" <?php if (empty($pconfig['gateway'])) echo "style='display:none'"; ?>>
					<select name='gateway'>
					<option value="" ><?=gettext("default");?></option>
<?php
					/* build a list of gateways */
					$gateways = return_gateways_array();
					// add statically configured gateways to list
					foreach($gateways as $gwname => $gw) {
						if(($pconfig['ipprotocol'] == "inet46"))
							continue;
						if(($pconfig['ipprotocol'] == "inet6") && !(($gw['ipprotocol'] == "inet6") || (is_ipaddrv6($gw['gateway']))))
							continue;
						if(($pconfig['ipprotocol'] == "inet") && !(($gw['ipprotocol'] == "inet") || (is_ipaddrv4($gw['gateway']))))
							continue;
						if($gw == "")
							continue;
						if($gwname == $pconfig['gateway']) {
							$selected = " selected=\"selected\"";
						} else {
							$selected = "";
						}
						$gateway_addr_str = empty($gw['gateway']) ? "" : " - " . $gw[gateway];
						echo "<option value=\"{$gwname}\" {$selected}>{$gw['name']}{$gateway_addr_str}</option>\n";
					}
					/* add gateway groups to the list */
					if (is_array($a_gatewaygroups)) {
						foreach($a_gatewaygroups as $gwg_name => $gwg_data) {
							if((empty($pconfig['ipprotocol'])) || ($pconfig['ipprotocol'] == $gwg_data['ipprotocol'])) {
								if($pconfig['gateway'] == $gwg_name) {
									$selected = " selected=\"selected\"";
								} else {
									$selected = "";
								}
								echo "<option value=\"{$gwg_name}\" $selected>{$gwg_name}</option>\n";
							}
						}
					}
?>
					</select>
					<p><?=gettext("Leave as 'default' to use the system routing table.  Or choose a gateway to utilize policy based routing.");?></p>
				</div>
			</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("In/Out");?></td>
			<td width="78%" class="vtable">
				<div id="showadvinoutbox" <?php if (!empty($pconfig['dnpipe'])) echo "style='display:none'"; ?>>
					<input type="button" onclick="show_advanced_inout()" value="<?=gettext("Advanced"); ?>" /> - <?=gettext("Show advanced option");?>
				</div>
				<div id="showinoutadv" <?php if (empty($pconfig['dnpipe'])) echo "style='display:none'"; ?>>
					<select name="dnpipe">
<?php
		if (!is_array($dnqlist))
			$dnqlist = array();
		echo "<option value=\"\"";
		if (!$dnqselected) echo " selected=\"selected\"";
		echo " >none</option>";
		foreach ($dnqlist as $dnq => $dnqkey) {
			if($dnq == "")
				continue;
			echo "<option value=\"$dnq\"";
			if ($dnq == $pconfig['dnpipe']) {
				$dnqselected = 1;
				echo " selected=\"selected\"";
			}
			echo ">{$dnq}</option>";
		}
?>
			</select> /
			<select name="pdnpipe">
<?php
		$dnqselected = 0;
		echo "<option value=\"\"";
		if (!$dnqselected) echo " selected=\"selected\"";
		echo " >none</option>";
		foreach ($dnqlist as $dnq => $dnqkey) {
			if($dnq == "")
				continue;
			echo "<option value=\"$dnq\"";
			if ($dnq == $pconfig['pdnpipe']) {
				$dnqselected = 1;
				echo " selected=\"selected\"";
			}
			echo ">{$dnq}</option>";
		}
?>
				</select>
				<br />
				<span class="vexpl"><?=gettext("Choose the Out queue/Virtual interface only if you have also selected In.")."<br />".gettext("The Out selection is applied to traffic leaving the interface where the rule is created, In is applied to traffic coming into the chosen interface.")."<br />".gettext("If you are creating a floating rule, if the direction is In then the same rules apply, if the direction is out the selections are reverted Out is for incoming and In is for outgoing.");?></span>
				</div>
			</td>
		</tr>

		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Ackqueue/Queue");?></td>
			<td width="78%" class="vtable">
			<div id="showadvackqueuebox" <?php if (!empty($pconfig['defaultqueue'])) echo "style='display:none'"; ?>>
				<input type="button" onclick="show_advanced_ackqueue()" value="<?=gettext("Advanced"); ?>" /> - <?=gettext("Show advanced option");?>
			</div>
			<div id="showackqueueadv" <?php if (empty($pconfig['defaultqueue'])) echo "style='display:none'"; ?>>
				<select name="ackqueue">
<?php
			if (!is_array($qlist))
				$qlist = array();
			echo "<option value=\"\"";
			if (!$qselected) echo " selected=\"selected\"";
			echo " >none</option>";
			foreach ($qlist as $q => $qkey) {
				if($q == "")
					continue;
				echo "<option value=\"$q\"";
				if ($q == $pconfig['ackqueue']) {
					$qselected = 1;
					echo " selected=\"selected\"";
				}
				if (isset($ifdisp[$q]))
					echo ">{$ifdisp[$q]}</option>";
				else
					echo ">{$q}</option>";
			}
?>
				</select> /
				<select name="defaultqueue">
<?php
			$qselected = 0;
			echo "<option value=\"\"";
			if (!$qselected) echo " selected=\"selected\"";
			echo " >none</option>";
			foreach ($qlist as $q => $qkey) {
				if($q == "")
					continue;
				echo "<option value=\"$q\"";
				if ($q == $pconfig['defaultqueue']) {
					$qselected = 1;
					echo " selected=\"selected\"";
				}
				if (isset($ifdisp[$q]))
					echo ">{$ifdisp[$q]}</option>";
				else
					echo ">{$q}</option>";
			}
?>
				</select>
					<br />
					<span class="vexpl"><?=gettext("Choose the Acknowledge Queue only if you have selected Queue.");?></span>
					</div>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncell"><?=gettext("Layer7");?></td>
				<td width="78%" class="vtable">
					<div id="showadvlayer7box" <?php if (!empty($pconfig['l7container'])) echo "style='display:none'"; ?>>
						<input type="button" onclick="show_advanced_layer7()" value="<?=gettext("Advanced"); ?>" /> - <?=gettext("Show advanced option");?>
					</div>
					<div id="showlayer7adv" <?php if (empty($pconfig['l7container'])) echo "style='display:none'"; ?>>
						<select name="l7container">
<?php
						if (!is_array($l7clist))
							$l7clist = array();
						echo "<option value=\"\"";
						echo " >none</option>";
						foreach ($l7clist as $l7ckey) {
							echo "<option value=\"{$l7ckey}\"";
							if ($l7ckey == $pconfig['l7container']) {
								echo " selected=\"selected\"";
							}
							echo ">{$l7ckey}</option>";
						}
?>
						</select>
						<br />
						<span class="vexpl">
							<?=gettext("Choose a Layer7 container to apply application protocol inspection rules. " .
							"These are valid for TCP and UDP protocols only.");?>
						</span>
					</div>
				</td>
			</tr>
<?php
		// Allow extending of the firewall edit page and include custom input validation
		pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/htmlphplate");
?>
<?php
$has_created_time = (isset($a_filter[$id]['created']) && is_array($a_filter[$id]['created']));
$has_updated_time = (isset($a_filter[$id]['updated']) && is_array($a_filter[$id]['updated']));
?>
		<?php if ($has_created_time || $has_updated_time): ?>
		<tr>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?=gettext("Rule Information");?></td>
		</tr>
		<?php if ($has_created_time): ?>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Created");?></td>
			<td width="78%" class="vtable">
				<?= date(gettext("n/j/y H:i:s"), $a_filter[$id]['created']['time']) ?> <?= gettext("by") ?> <strong><?= $a_filter[$id]['created']['username'] ?></strong>
			</td>
		</tr>
		<?php endif; ?>
		<?php if ($has_updated_time): ?>
		<tr>
			<td width="22%" valign="top" class="vncell"><?=gettext("Updated");?></td>
			<td width="78%" class="vtable">
				<?= date(gettext("n/j/y H:i:s"), $a_filter[$id]['updated']['time']) ?> <?= gettext("by") ?> <strong><?= $a_filter[$id]['updated']['username'] ?></strong>
			</td>
		</tr>
		<?php endif; ?>
		<?php endif; ?>
		<tr>
			<td width="22%" valign="top">&nbsp;</td>
			<td width="78%">
				&nbsp;<br />&nbsp;
				<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" />
				<input type="button" class="formbtn" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=$referer;?>'" />
<?php			if (isset($id) && $a_filter[$id]): ?>
					<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
					<input name="tracker" type="hidden" value="<?=htmlspecialchars($pconfig['tracker']);?>">
<?php 			endif; ?>
				<input name="after" type="hidden" value="<?=htmlspecialchars($after);?>" />
			</td>
		</tr>
	</table>
</form>
<script type="text/javascript">
//<![CDATA[
	ext_change();
	typesel_change();
	proto_change();
	<?php if ( (!empty($pconfig['srcbeginport']) && $pconfig['srcbeginport'] != "any") || (!empty($pconfig['srcendport']) && $pconfig['srcendport'] != "any") ): ?>
	show_source_port_range();
	<?php endif; ?>

	var addressarray = <?= json_encode(get_alias_list(array("host", "network", "openvpn", "urltable"))) ?>;
	var customarray  = <?= json_encode(get_alias_list(array("port", "url_ports", "urltable_ports"))) ?>;

	var oTextbox1 = new AutoSuggestControl(document.getElementById("src"), new StateSuggestions(addressarray));
	var oTextbox2 = new AutoSuggestControl(document.getElementById("srcbeginport_cust"), new StateSuggestions(customarray));
	var oTextbox3 = new AutoSuggestControl(document.getElementById("srcendport_cust"), new StateSuggestions(customarray));
	var oTextbox4 = new AutoSuggestControl(document.getElementById("dst"), new StateSuggestions(addressarray));
	var oTextbox5 = new AutoSuggestControl(document.getElementById("dstbeginport_cust"), new StateSuggestions(customarray));
	var oTextbox6 = new AutoSuggestControl(document.getElementById("dstendport_cust"), new StateSuggestions(customarray));
//]]>
</script>
<?php include("fend.inc"); ?>
</body>
</html>
