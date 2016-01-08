<?php
/*
	firewall_nat_edit.php
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
##|*IDENT=page-firewall-nat-portforward-edit
##|*NAME=Firewall: NAT: Port Forward: Edit
##|*DESCR=Allow access to the 'Firewall: NAT: Port Forward: Edit' page.
##|*MATCH=firewall_nat_edit.php*
##|-PRIV

require("guiconfig.inc");
require_once("itemid.inc");
require_once("ipsec.inc");
require_once("filter.inc");
require("shaper.inc");

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/firewall_nat.php');

$specialsrcdst = explode(" ", "any (self) pptp pppoe l2tp openvpn");
$ifdisp = get_configured_interface_with_descr();

foreach ($ifdisp as $kif => $kdescr) {
	$specialsrcdst[] = "{$kif}";
	$specialsrcdst[] = "{$kif}ip";
}

if (!is_array($config['nat']['rule'])) {
	$config['nat']['rule'] = array();
}

$a_nat = &$config['nat']['rule'];

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

if (isset($_GET['dup']) && is_numericint($_GET['dup'])) {
	unset($id);
}

/*	run through $_POST items encoding HTML entities so that the user
 *	cannot think he is slick and perform a XSS attack on the unwilling
 */
unset($input_errors);

foreach ($_POST as $key => $value) {
	$temp = $value;
	$newpost = htmlentities($temp);
	if ($newpost != $temp) {
		$input_errors[] = sprintf(gettext("Invalid characters detected %s. Please remove invalid characters and save again."), $temp);
	}
}

if ($_POST) {

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
	} else if ($_POST['srctype'] == "single") {
		$_POST['srcmask'] = 32;
	}

	if (is_specialnet($_POST['dsttype'])) {
		$_POST['dst'] = $_POST['dsttype'];
		$_POST['dstmask'] = 0;
	} else if ($_POST['dsttype'] == "single") {
		$_POST['dstmask'] = 32;
	} else if (is_ipaddr($_POST['dsttype'])) {
		$_POST['dst'] = $_POST['dsttype'];
		$_POST['dstmask'] = 32;
		$_POST['dsttype'] = "single";
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

	if (!isset($_POST['nordr']) && ($_POST['localip'] && !is_ipaddroralias($_POST['localip']))) {
		$input_errors[] = sprintf(gettext("\"%s\" is not a valid redirect target IP address or host alias."), $_POST['localip']);
	}

	if ($_POST['srcbeginport'] && !is_portoralias($_POST['srcbeginport'])) {
		$input_errors[] = sprintf(gettext("%s is not a valid start source port. It must be a port alias or integer between 1 and 65535."), $_POST['srcbeginport']);
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

	if ((strtoupper($_POST['proto']) == "TCP" || strtoupper($_POST['proto']) == "UDP" || strtoupper($_POST['proto']) == "TCP/UDP") && (!isset($_POST['nordr']) && !is_portoralias($_POST['localbeginport']))) {
		$input_errors[] = sprintf(gettext("A valid redirect target port must be specified. It must be a port alias or integer between 1 and 65535."), $_POST['localbeginport']);
	}

	/* if user enters an alias and selects "network" then disallow. */
	if (($_POST['srctype'] == "network" && is_alias($_POST['src'])) ||
	    ($_POST['dsttype'] == "network" && is_alias($_POST['dst']))) {
		$input_errors[] = gettext("You must specify single host or alias for alias entries.");
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
		if (!isset($_POST['nordr']) && ($_POST['dstendport'] - $_POST['dstbeginport'] + $_POST['localbeginport']) > 65535) {
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

		$natent['disabled'] = isset($_POST['disabled']) ? true:false;
		$natent['nordr'] = isset($_POST['nordr']) ? true:false;

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

		$natent['protocol'] = $_POST['proto'];

		if (!$natent['nordr']) {
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
			$filterent['protocol'] = $_POST['proto'];
			$filterent['destination']['address'] = $_POST['localip'];

			$dstpfrom = $_POST['localbeginport'];
			$dstpto = $dstpfrom + $_POST['dstendport'] - $_POST['dstbeginport'];

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
			$a_nat[$id] = $natent;
		} else {
			$natent['created'] = make_config_revision_entry();
			if (is_numeric($after)) {
				array_splice($a_nat, $after+1, 0, array($natent));
			} else {
				$a_nat[] = $natent;
			}
		}

		if (write_config()) {
			mark_subsystem_dirty('natconf');
		}

		header("Location: firewall_nat.php");
		exit;
	}
}

function build_srctype_list() {
	global $pconfig, $ifdisp, $config;

	$list = array('any' => 'Any', 'single' => 'Single host or alias', 'network' => 'Network');

	$sel = is_specialnet($pconfig['src']);

	if (have_ruleint_access("pppoe")) {
		$list['pppoe'] = 'PPPoE clients';
	}

	if (have_ruleint_access("l2tp")) {
		$list['l2tp'] = 'L2TP clients';
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

	$sel = is_specialnet($pconfig['src']);
	if (!$sel) {
		if ($pconfig['srcmask'] == 32) {
			$selected = 'single';
		} else {
			$selected = 'network';
		}
	} else {
		$selected = $pconfig['src'];
	}


	return($selected);
}

function build_dsttype_list() {
	global $pconfig, $config, $ifdisp;

	$sel = is_specialnet($pconfig['dst']);
	$list = array('any' => 'Any', 'single' => 'Single host or alias', 'network' => 'Network', '(self)' => 'This Firewall (self)');

	if (have_ruleint_access("pppoe")) {
		$list['pppoe'] = 'PPPoE clients';
	}

	if (have_ruleint_access("l2tp")) {
		$list['l2tp'] = 'L2TP clients';
	}

	foreach ($ifdisp as $if => $ifdesc) {
		if (have_ruleint_access($if)) {
			$list[$if] = $ifdesc;
			$list[$if . 'ip'] = $ifdesc . ' address';
		}
	}

	if (is_array($config['virtualip']['vip'])) {
		foreach ($config['virtualip']['vip'] as $sn) {
			if ($sn['mode'] == "proxyarp" && $sn['type'] == "network") {
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

				$list[$sn['subnet']] = $sn['subnet'] . ' (' . $sn['descr'] . ')';
			} else {
				$list[$sn['subnet']] = $sn['subnet'] . ' (' . $sn['descr'] . ')';
			}
		}
	}

	return($list);
}

function dsttype_selected() {
	global $pconfig, $config;

	$selected = "";

	if (is_array($config['virtualip']['vip'])) {
		$selected = $pconfig['dst'];
	} else {
		$sel = is_specialnet($pconfig['dst']);
		if (!$sel) {
			if ($pconfig['dstmask'] == 32) {
				$selected = 'single';
			} else {
				$selected = 'network';
			}
		} else {
			$selected = $pconfig['dst'];
		}
	}

	return($selected);
}

$pgtitle = array(gettext("Firewall"), gettext("NAT"), gettext("Port Forward"), gettext("Edit"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form(new Form_Button(
	'Submit',
	gettext("Save")
));

$section = new Form_Section('Edit Redirect entry');

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
))->setHelp('This option is rarely needed, don\'t use this unless you know what you\'re doing.');

$iflist = get_configured_interface_with_descr(false, true);

foreach ($iflist as $if => $ifdesc) {
	if (have_ruleint_access($if)) {
		$interfaces[$if] = $ifdesc;
	}
}

if ($config['l2tp']['mode'] == "server") {
	if (have_ruleint_access("l2tp")) {
		$interfaces['l2tp'] = "L2TP VPN";
	}
}

if (is_pppoe_server_enabled() && have_ruleint_access("pppoe")) {
	$interfaces['pppoe'] = "PPPoE Server";
}

/* add ipsec interfaces */
if (ipsec_enabled() && have_ruleint_access("enc0")) {
	$interfaces["enc0"] = "IPsec";
}

/* add openvpn/tun interfaces */
if ($config['openvpn']["openvpn-server"] || $config['openvpn']["openvpn-client"]) {
	$interfaces["openvpn"] = "OpenVPN";
}

$section->addInput(new Form_Select(
	'interface',
	'Interface',
	$pconfig['interface'],
	$interfaces
))->setHelp('Choose which interface this rule applies to. In most cases "WAN" is specified.');

$protocols = "TCP UDP TCP/UDP ICMP ESP AH GRE IPV6 IGMP PIM OSPF";

$section->addInput(new Form_Select(
	'proto',
	'Protocol',
	$pconfig['proto'],
	array_combine(explode(" ", strtolower($protocols)), explode(" ", $protocols))
))->setHelp('Choose which protocol this rule should match. In most cases "TCP" is specified.');

$btnsrcadv = new Form_Button(
	'srcadv',
	'Advanced'
);

$btnsrcadv->removeClass('btn-primary')->addClass('btn-default');

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
	is_specialnet($pconfig['src']) ? '': $pconfig['src']
))->setPattern('[.a-zA-Z0-9_]+')->addMask('srcmask', $pconfig['srcmask'])->setHelp('Address/mask');

$section->add($group);

$portlist = array("" => 'Other', 'any' => 'Any');

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
	'number',
	is_numeric($pconfig['srcbeginport']) ? $pconfig['srcbeginport'] : null,
	['min' => '1', 'max' => '65536']
))->setHelp('Custom');

$group->add(new Form_Select(
	'srcendport',
	null,
	$pconfig['srcendport'],
	$portlist
))->setHelp('To port');

$group->add(new Form_Input(
	'srcendport_cust',
	null,
	'number',
	is_numeric($pconfig['srcendport']) ? $pconfig['srcendport'] : null,
	['min' => '1', 'max' => '65536']
))->setHelp('Custom');

$group->setHelp('Specify the source port or port range for this rule. This is usually random and almost never ' .
				'equal to the destination port range (and should usually be \'any\'). You can leave the \'to\' field ' .
				'empty if you only want to filter a single port.');

$section->add($group);

$group = new Form_Group('Destination');

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
	is_specialnet($pconfig['dst']) ? '': $pconfig['dst']
))->setPattern('[.a-zA-Z0-9_]+')->addMask('dstmask', $pconfig['dstmask'], 31)->setHelp('Address/mask');

$section->add($group);

$group = new Form_Group('Destination port range');
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
	'number',
	is_numeric($pconfig['dstbeginport']) ? $pconfig['dstbeginport'] : null,
	['min' => '1', 'max' => '65536']
))->setHelp('Custom');

$group->add(new Form_Select(
	'dstendport',
	null,
	$pconfig['dstendport'],
	$portlist
))->setHelp('To port');

$group->add(new Form_Input(
	'dstendport_cust',
	null,
	'number',
	is_numeric($pconfig['dstendport']) ? $pconfig['dstendport'] : null,
	['min' => '1', 'max' => '65536']
))->setHelp('Custom');

$group->setHelp('Specify the port or port range for the destination of the packet for this mapping. ' .
				'You can leave the \'to\' field empty if you only want to map a single port ');

$section->add($group);

$section->addInput(new Form_IpAddress(
	'localip',
	'Redirect target IP',
	$pconfig['localip']
))->setPattern('[.a-zA-Z0-9_]+')->setHelp('Enter the internal IP address of the server on which you want to map the ports.' . '<br />' .
			'e.g.: 192.168.1.12');

$group = new Form_Group('Redirect target port');
$group->addClass('lclportrange');

$group->add(new Form_Select(
	'localbeginport',
	null,
	$pconfig['localbeginport'],
	array('' => 'Other') + $wkports
))->setHelp('Port');

$group->setHelp('Specify the port on the machine with the IP address entered above. In case of a port range, specify the ' .
				'beginning port of the range (the end port will be calculated automatically).' . '<br />' .
				'this is usually identical to "From port" above');

$group->add(new Form_Input(
	'localbeginport_cust',
	null,
	'number',
	is_numeric($pconfig['localbeginport']) ? $pconfig['localbeginport'] : null,
	['min' => '1', 'max' => '65536']
))->setHelp('Custom');

$section->add($group);

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('You may enter a description here for your reference (not parsed).');


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
		'default' => 'Use system default',
		'enable'  => 'Enable (NAT + Proxy)',
		'purenat' => 'Enable (Pure NAT)',
		'disable' => 'Disable'
	)
));

if (isset($id) && $a_nat[$id] && (!isset($_GET['dup']) || !is_numericint($_GET['dup']))) {

	$hlpstr = '';
	$rulelist = array('' => 'None', 'pass' => 'Pass');

	if (is_array($config['filter']['rule'])) {
		filter_rules_sort();

		foreach ($config['filter']['rule'] as $filter_id => $filter_rule) {
			if (isset($filter_rule['associated-rule-id'])) {
				$rulelist[$filter_rule['associated-rule-id']] = 'Rule ' . $filter_rule['descr'];

				if ($filter_rule['associated-rule-id'] == $pconfig['associated-rule-id']) {
					$hlpstr = '<a href="firewall_rules_edit.php?id=' . $filter_id . '">' . gettext("View the filter rule") . '</a><br />';
				}
			}
		}
	}

	if (isset($pconfig['associated-rule-id'])) {
		$rulelist['new'] = 'Create new associated filter rule';
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
			'add-associated'  => 'Add associated filter rule',
			'add-unassociated' => 'Add unassociated filter rule',
			'pass' => 'Pass'
		)
	))->setHelp('The "pass" selection does not work properly with Multi-WAN. It will only work on an interface containing the default gateway.');
}

$form->add($section);

$has_created_time = (isset($a_nat[$id]['created']) && is_array($a_nat[$id]['created']));
$has_updated_time = (isset($a_nat[$id]['updated']) && is_array($a_nat[$id]['updated']));

if ($has_created_time || $has_updated_time) {
	$section = new Form_Section('Rule Information');

	if ($has_created_time) {
		$section->addInput(new Form_StaticText(
			'Created',
			date(gettext("n/j/y H:i:s"), $a_nat[$id]['created']['time']) . gettext(" by ") . $a_nat[$id]['created']['username']
		));
	}

	if ($has_updated_time) {
		$section->addInput(new Form_StaticText(
			'Updated',
			date(gettext("n/j/y H:i:s"), $a_nat[$id]['updated']['time']) . gettext(" by ") . $a_nat[$id]['updated']['username']
		));
	}

	$form->add($section);
}

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
	var portsenabled = 1;
	var dstenabled = 1;
	var showsource = 0;
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

		if (($('#dstbeginport').find(":selected").index() == 0) && portsenabled && dstenabled) {
			disableInput('dstbeginport_cust', false);
		} else {
			$('#dstbeginport_cust').val('');
			disableInput('dstbeginport_cust', true);
		}

		if (($('#dstendport').find(":selected").index() == 0) && portsenabled && dstenabled) {
			disableInput('dstendport_cust', false);
		} else {
			$('#dstendport_cust').val('');
			disableInput('dstendport_cust', true);
		}

		if (($('#localbeginport').find(":selected").index() == 0) && portsenabled) {
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
			if (dstenabled) {
				disableInput('dstbeginport', false);
				disableInput('dstendport', false);
			}
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
		//
		for (i = 0; i < customarray.length; i++) {
			if ($('#dstbeginport_cust').val() == customarray[i]) {
				$('#dstendport_cust').val(customarray[i]);
				$('#localbeginport_cust').val(customarray[i]);
				disableInput('dstendport_cust', true);
				disableInput('localbeginport', true);
				disableInput('localbeginport_cust', true);
				disableInput('dstendport_cust', false);
				disableInput('localbeginport', false);
				disableInput('localbeginport_cust', false);
			}
			if ($('#dstbeginport').val() == customarray[i]) {
				$('#dstendport_cust').val(customarray[i]);
				$('#localbeginport_cust').val(customarray[i]);
				disableInput('dstendport_cust', true);
				disableInput('localbeginport', true);
				disableInput('localbeginport_cust', true);
				disableInput('dstendport_cust', false);
				disableInput('localbeginport', false);
				disableInput('localbeginport_cust', false);
			}
			if ($('#dstendport_cust').val() == customarray[i]) {
				$('#dstendport_cust').val(customarray[i]);
				$('#localbeginport_cust').val(customarray[i]);
				disableInput('dstendport_cust', true);
				disableInput('localbeginport', true);
				disableInput('localbeginport_cust', true);
				disableInput('dstendport_cust', false);
				disableInput('localbeginport', false);
				disableInput('localbeginport_cust', false);
			}
			if ($('#dstendport').val() == customarray[i]) {
				$('#dstendport_cust').val(customarray[i]);
				$('#localbeginport_cust').val(customarray[i]);
				disableInput('dstendport_cust', true);
				disableInput('localbeginport', true);
				disableInput('localbeginport_cust', true);
				disableInput('dstendport_cust', false);
				ddisableInput('localbeginport', false);
				disableInput('localbeginport_cust', false);
			}

		}
	}

	function proto_change() {
		if ($('#proto').find(":selected").index() >= 0 && $('#proto').find(":selected").index() <= 2) {
			portsenabled = 1;
		} else {
			portsenabled = 0;
		}

		if (portsenabled) {
			hideClass('srcportrange', showsource == 1);
			hideClass('dstportrange', false);
			hideClass('lclportrange', false);
		} else {
			hideClass('srcportrange', true);
			hideClass('dstportrange', true);
			hideClass('lclportrange', true);
			$('#dstbeginport').prop("selectedIndex", 0).selectmenu('refresh');
			$('#dstbeginport_cust').val('');
			$('#dstendport').prop("selectedIndex", 0).selectmenu('refresh');
			$('#dstendport_cust').val('');
			$('#localbeginport').prop("selectedIndex", 0).selectmenu('refresh');
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

		if (dstenabled) {
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
		hideClass('srcadv', hide);
		hideClass('srcportrange', hide || !portsenabled);
		hideInput('srcadv', !hide);
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

	$('#srctype').click(function () {
		typesel_change();
	});

	$('#dsttype').click(function () {
		typesel_change();
	});

    $("#srcadv").click(function() {
        hideSource(false);
    });
	// ---------- On initial page load --------------------------------------------------------------------------------

	$("#srcadv").prop('type', 'button');
	ext_change();
	dst_change($('#interface').val(),'<?=htmlspecialchars($pconfig['interface'])?>','<?=htmlspecialchars($pconfig['dst'])?>');
	iface_old = $('#interface').val();
	typesel_change();
	proto_change();
	nordr_change();
	hideSource(true);

	// --------- Autocomplete -----------------------------------------------------------------------------------------
	var addressarray = <?= json_encode(get_alias_list(array("host", "network", "openvpn", "urltable"))) ?>;
	var customarray = <?= json_encode(get_alias_list(array("port", "url_ports", "urltable_ports"))) ?>;

	$('#localip, #src, #dst').autocomplete({
		source: addressarray
	});

	$('#dstbeginport_cust, #dstendport_cust, #srcbeginport_cust, #srcendport_cust, localbeginport_cust').autocomplete({
		source: customarray
	});
});
//]]>
</script>

<?php include("foot.inc");
