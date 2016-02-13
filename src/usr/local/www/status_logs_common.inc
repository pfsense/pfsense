<?php
/*
	status_logs_common.inc
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *  Some or all of this file is based on the m0n0wall project which is
 *  Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
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

require_once("guiconfig.inc");
require_once("filter_log.inc");


// Status Logs Common - Code
function status_logs_common_code() {
	global $g, $config, $specific_log, $nentries, $filterlogentries_qty, $logfile_path, $shortcut_section, $allowed_logs, $logfile;
	global $system_logs_filter_form_hidden, $system_logs_manage_log_form_hidden, $view;

	$logfile_path = "{$g['varlog_path']}/" . basename($logfile) . ".log";

	isset($config['syslog'][$specific_log]['nentries']) ? $nentries = $config['syslog'][$specific_log]['nentries'] : $nentries = $config['syslog']['nentries'];

	// Override Display Quantity
	if ($filterlogentries_qty) {
		$nentries = $filterlogentries_qty;
	}

	if (!$nentries || !is_numeric($nentries)) {
		$nentries = 50;
	}

	if ($_POST['clear']) {
		clear_log_file($logfile_path);
	}

	/* Setup shortcuts if they exist */

	if (!empty($allowed_logs[$logfile]["shortcut"])) {
		$shortcut_section = $allowed_logs[$logfile]["shortcut"];
	}

	// Get the configured options for Show/Hide Log Filter and Manage Log panels.
	$system_logs_filter_form_hidden = isset($config['system']['webgui']['systemlogsfilterpanel']) ? false : true;
	$system_logs_manage_log_form_hidden = isset($config['system']['webgui']['systemlogsmanagelogpanel']) ? false : true;

	if ($logfile == 'filter' && $view == 'summary') {
		$system_logs_filter_form_hidden = false;
		$system_logs_manage_log_form_hidden = false;
	}
}

// Tab Array
function tab_array_logs_common() {
	global $tab_array, $logfile, $vpntype, $view;

	$is_system_log = in_array($logfile, array('system', 'gateways', 'routing', 'resolver', 'wireless'));
	$is_filter_log = in_array($logfile, array('filter'));
	$is_pppoe_l2tp_vpn_log = in_array($logfile, array('poes', 'l2tps', 'vpn'));

	$tab_array = array();
	$tab_array[] = array(gettext("System"), $is_system_log, "status_logs.php");
	$tab_array[] = array(gettext("Firewall"), $is_filter_log, "status_logs_filter.php");
	$tab_array[] = array(gettext("DHCP"), ($logfile == 'dhcpd'), "status_logs.php?logfile=dhcpd");
	$tab_array[] = array(gettext("Captive Portal Auth"), ($logfile == 'portalauth'), "status_logs.php?logfile=portalauth");
	$tab_array[] = array(gettext("IPsec"), ($logfile == 'ipsec'), "status_logs.php?logfile=ipsec");
	$tab_array[] = array(gettext("PPP"), ($logfile == 'ppp'), "status_logs.php?logfile=ppp");
	$tab_array[] = array(gettext("VPN"), $is_pppoe_l2tp_vpn_log, "status_logs_vpn.php");
	$tab_array[] = array(gettext("Load Balancer"), ($logfile == 'relayd'), "status_logs.php?logfile=relayd");
	$tab_array[] = array(gettext("OpenVPN"), ($logfile == 'openvpn'), "status_logs.php?logfile=openvpn");
	$tab_array[] = array(gettext("NTP"), ($logfile == 'ntpd'), "status_logs.php?logfile=ntpd");
	$tab_array[] = array(gettext("Settings"), false, "status_logs_settings.php");
	display_top_tabs($tab_array);

	$tab_array = array();
	if ($is_system_log) {
		$tab_array[] = array(gettext("General"), ($logfile == 'system'), "/status_logs.php");
		$tab_array[] = array(gettext("Gateways"), ($logfile == 'gateways'), "/status_logs.php?logfile=gateways");
		$tab_array[] = array(gettext("Routing"), ($logfile == 'routing'), "/status_logs.php?logfile=routing");
		$tab_array[] = array(gettext("DNS Resolver"), ($logfile == 'resolver'), "/status_logs.php?logfile=resolver");
		$tab_array[] = array(gettext("Wireless"), ($logfile == 'wireless'), "/status_logs.php?logfile=wireless");
	}
	else if ($is_filter_log) {
		$tab_array[] = array(gettext("Normal View"), ($view == 'normal'), "/status_logs_filter.php");
		$tab_array[] = array(gettext("Dynamic View"), ($view == 'dynamic'), "/status_logs_filter_dynamic.php?logfile=filter&amp;view=dynamic");
		$tab_array[] = array(gettext("Summary View"), ($view == 'summary'), "/status_logs_filter_summary.php?logfile=filter&amp;view=summary");
	}
	else if ($is_pppoe_l2tp_vpn_log) {
		$tab_array[] = array(gettext("PPPoE Logins"),
					(($logfile == 'vpn') && ($vpntype == "poes")),
					"/status_logs_vpn.php?logfile=vpn&amp;vpntype=poes");
		$tab_array[] = array(gettext("PPPoE Service"),
					(($logfile == 'poes') && ($vpntype == "poes")),
					"/status_logs_vpn.php?logfile=poes&amp;vpntype=poes");
		$tab_array[] = array(gettext("L2TP Logins"),
					(($logfile == 'vpn') && ($vpntype == "l2tp")),
					"/status_logs_vpn.php?logfile=vpn&amp;vpntype=l2tp");
		$tab_array[] = array(gettext("L2TP Service"),
					(($logfile == 'l2tps') && ($vpntype == "l2tp")),
					"/status_logs_vpn.php?logfile=l2tps&amp;vpntype=l2tp");
	}
	if ($tab_array) {
		display_top_tabs($tab_array, false, 'tabs');
	}
}


// Log Table header
function system_log_table_panel_title() {
	global $rawfilter, $filtersubmit, $filterlogentries_submit, $filterlog, $nentries;
	global $allowed_logs, $logfile;

	$rtnstr = '';

	if ($rawfilter) {
		if (($filtersubmit) || ($filterlogentries_submit)) {
			$rtnstr .= sprintf(gettext("%s Matched %s Log Entries."), "<span id='count'>_ _</span>", gettext($allowed_logs[$logfile]["name"]));
		} else {
			$rtnstr .= sprintf(gettext("Last %s %s Log Entries."), "<span id='count'>_ _</span>", gettext($allowed_logs[$logfile]["name"]));
		}
	} else {
		if (($filtersubmit) || ($filterlogentries_submit)) {
			$rtnstr .= sprintf(gettext("%d Matched %s Log Entries."), count($filterlog), gettext($allowed_logs[$logfile]["name"]));
		} else {
			$rtnstr .= sprintf(gettext("Last %d %s Log Entries."), count($filterlog), gettext($allowed_logs[$logfile]["name"]));
		}
	}

	$rtnstr .= sprintf(" (" . gettext("Maximum %d") . ")", $nentries);

	return $rtnstr;
}


// Log Filter
function system_log_filter() {
	global $rawfilter, $filtersubmit, $filterlogentries_submit, $filterlog, $nentries;
	global $logfile_path, $filtertext, $filterfieldsarray, $interfacefilter, $inverse, $rows;

	if ($rawfilter) {
		if (($filtersubmit) || ($filterlogentries_submit)) {
			$rows = dump_clog($logfile_path, $nentries, true, array("$filtertext"), $inverse);
		} else {
			$rows = dump_clog($logfile_path, $nentries, true, array(), $inverse);
		}
	} else {
		if (($filtersubmit) || ($filterlogentries_submit)) {
			$filterlog = conv_log_filter($logfile_path, $nentries, $nentries + 100, $filterfieldsarray);
		} else {
			$filterlog = conv_log_filter($logfile_path, $nentries, $nentries + 100, $filtertext, $interfacefilter);
		}
	}
}


// Log Filter Submit - System
function log_filter_form_system_submit() {

	global $filtersubmit, $interfacefilter, $filtertext;
	global $filterlogentries_submit, $filterfieldsarray, $actpass, $actblock;
	global $filter_active, $filterlogentries_qty;

	$filtersubmit = getGETPOSTsettingvalue('filtersubmit', null);

	if ($filtersubmit) {
		$filter_active = true;
		$filtertext = getGETPOSTsettingvalue('filtertext', "");
		$filterlogentries_qty = getGETPOSTsettingvalue('filterlogentries_qty', null);
	}

	$filterlogentries_submit = getGETPOSTsettingvalue('filterlogentries_submit', null);

	if ($filterlogentries_submit) {
		$filter_active = true;
		$filterfieldsarray = array();

		$filterfieldsarray['time'] = getGETPOSTsettingvalue('filterlogentries_time', null);
		$filterfieldsarray['process'] = getGETPOSTsettingvalue('filterlogentries_process', null);
		$filterfieldsarray['pid'] = getGETPOSTsettingvalue('filterlogentries_pid', null);
		$filterfieldsarray['message'] = getGETPOSTsettingvalue('filterlogentries_message', null);
		$filterlogentries_qty = getGETPOSTsettingvalue('filterlogentries_qty', null);
	}
}

// Filter Section/Form - System
function filter_form_system() {

	global $filter_active, $rawfilter, $filterfieldsarray, $filtertext, $filterlogentries_qty, $nentries, $Include_Act, $interfacefilter;
	global $system_logs_filter_form_hidden;

	if ($filter_active) {
		$panel_state = 'in';
		$panel_body_state = SEC_OPEN;
	} else {
		if ($system_logs_filter_form_hidden) {
			$panel_state = 'out';
			$panel_body_state = SEC_OPEN;
		} else {
			$panel_state = 'in';
			$panel_body_state = SEC_CLOSED;
		}
	}

	if (!$rawfilter) { // Advanced log filter form
		$form = new Form(false);
		$form->setAttribute('id', 'filter-form')->addClass('collapse ' . $panel_state);

		$section = new Form_Section('Advanced Log Filter', 'filter-panel', COLLAPSIBLE|$panel_body_state);

		$group = new Form_Group('');

		$group->add(new Form_Input(
			'filterlogentries_time',
			null,
			'text',
			$filterfieldsarray['time']
		))->setWidth(3)->setHelp('Time');

		$group->add(new Form_Input(
			'filterlogentries_process',
			null,
			'text',
			$filterfieldsarray['process']
		))->setWidth(2)->setHelp('Process');

		$group->add(new Form_Input(
			'filterlogentries_pid',
			null,
			'text',
			$filterfieldsarray['pid']
		))->setWidth(2)->setHelp('PID');

		$group->add(new Form_Input(
			'filterlogentries_qty',
			null,
			'number',
			$filterlogentries_qty,
			['placeholder' => $nentries]
		))->setWidth(2)->setHelp('Quantity');

		$section->add($group);

		$group = new Form_Group('');

		$group->add(new Form_Input(
			'filterlogentries_message',
			null,
			'text',
			$filterfieldsarray['message']
		))->setWidth(7)->setHelp('Message');

		$btnsubmit = new Form_Button(
			'filterlogentries_submit',
			' ' . gettext('Apply Filter'),
			null,
			'fa-filter'
		);
	} else { // Simple log filter form
		$form = new Form(false);
		$form->setAttribute('id', 'filter-form')->addClass('collapse ' . $panel_state);

		$section = new Form_Section('Log Filter', 'filter-panel', COLLAPSIBLE|$panel_body_state);

		$group = new Form_Group('');

		$group->add(new Form_Input(
			'filtertext',
			null,
			'text',
			$filtertext
		))->setWidth(6)->setHelp('Filter Expression');

		$group->add(new Form_Input(
			'filterlogentries_qty',
			null,
			'number',
			$filterlogentries_qty,
			['placeholder' => $nentries]
		))->setWidth(2)->setHelp('Quantity');

		$btnsubmit = new Form_Button(
			'filtersubmit',
			' ' . gettext('Apply Filter'),
			null,
			'fa-filter'
		);
	}

	$btnsubmit->removeClass('btn-primary')->addClass('btn-sm btn-success');

	$group->add(new Form_StaticText(
		'',
		$btnsubmit
	));

	$group->setHelp('<a target="_blank" href="http://www.php.net/manual/en/book.pcre.php">' . gettext('Regular expression reference') . '</a> ' . gettext('Precede with exclamation (!) to exclude match.'));
	$section->add($group);
	$form->add($section);
	print $form;
}


// Log Filter Submit - Firewall
function log_filter_form_firewall_submit() {

	global $filtersubmit, $interfacefilter, $filtertext;
	global $filterlogentries_submit, $filterfieldsarray, $actpass, $actblock;
	global $filter_active, $filterlogentries_qty;

	$filtersubmit = getGETPOSTsettingvalue('filtersubmit', null);

	if ($filtersubmit) {
		$filter_active = true;
		$interfacefilter = getGETPOSTsettingvalue('interface', null);
		$filtertext = getGETPOSTsettingvalue('filtertext', "");
		$filterlogentries_qty = getGETPOSTsettingvalue('filterlogentries_qty', null);
	}

	$filterlogentries_submit = getGETPOSTsettingvalue('filterlogentries_submit', null);

	if ($filterlogentries_submit) {
		$filter_active = true;
		$filterfieldsarray = array();

		$actpass = getGETPOSTsettingvalue('actpass', null);
		$actblock = getGETPOSTsettingvalue('actblock', null);
		$filterfieldsarray['act'] = str_replace("  ", " ", trim($actpass . " " . $actblock));
		$filterfieldsarray['act'] = $filterfieldsarray['act'] != "" ? $filterfieldsarray['act'] : 'All';
		$filterfieldsarray['time'] = getGETPOSTsettingvalue('filterlogentries_time', null);
		$filterfieldsarray['interface'] = getGETPOSTsettingvalue('filterlogentries_interfaces', null);
		$filterfieldsarray['srcip'] = getGETPOSTsettingvalue('filterlogentries_sourceipaddress', null);
		$filterfieldsarray['srcport'] = getGETPOSTsettingvalue('filterlogentries_sourceport', null);
		$filterfieldsarray['dstip'] = getGETPOSTsettingvalue('filterlogentries_destinationipaddress', null);
		$filterfieldsarray['dstport'] = getGETPOSTsettingvalue('filterlogentries_destinationport', null);
		$filterfieldsarray['proto'] = getGETPOSTsettingvalue('filterlogentries_protocol', null);
		$filterfieldsarray['tcpflags'] = getGETPOSTsettingvalue('filterlogentries_protocolflags', null);
		$filterlogentries_qty = getGETPOSTsettingvalue('filterlogentries_qty', null);
	}
}

// Filter Section/Form - Firewall
function filter_form_firewall() {

	global $filter_active, $rawfilter, $filterfieldsarray, $filtertext, $filterlogentries_qty, $nentries, $interfacefilter;
	global $system_logs_filter_form_hidden;

	$Include_Act = explode(",", str_replace(" ", ",", $filterfieldsarray['act']));
	if ($filterfieldsarray['interface'] == "All") {
		$interface = "";
	}

	if ($filter_active) {
		$panel_state = 'in';
		$panel_body_state = SEC_OPEN;
	} else {
		if ($system_logs_filter_form_hidden) {
			$panel_state = 'out';
			$panel_body_state = SEC_OPEN;
		} else {
			$panel_state = 'in';
			$panel_body_state = SEC_CLOSED;
		}
	}

	if (!$rawfilter) { // Advanced log filter form
		$form = new Form(false);
		$form->setAttribute('id', 'filter-form')->addClass('collapse ' . $panel_state);

		$section = new Form_Section('Advanced Log Filter', 'filter-panel', COLLAPSIBLE|$panel_body_state);

		$group = new Form_Group('');

		$group->add(new Form_Input(
			'filterlogentries_sourceipaddress',
			null,
			'text',
			$filterfieldsarray['srcip']
		))->setHelp('Source IP Address');

		$group->add(new Form_Input(
			'filterlogentries_destinationipaddress',
			null,
			'text',
			$filterfieldsarray['dstip']
		))->setHelp('Destination IP Address');

		$section->add($group);
		$group = new Form_Group('');

		$group->add(new Form_Checkbox(
			'actpass',
			'Pass',
			'Pass',
			in_arrayi('Pass', $Include_Act),
			'Pass'
		))->setWidth(1);

		$group->add(new Form_Input(
			'filterlogentries_time',
			null,
			'text',
			$filterfieldsarray['time']
		))->setWidth(3)->setHelp('Time');

		$group->add(new Form_Input(
			'filterlogentries_sourceport',
			null,
			'text',
			$filterfieldsarray['srcport']
		))->setWidth(2)->setHelp('Source Port');

		$group->add(new Form_Input(
			'filterlogentries_protocol',
			null,
			'text',
			$filterfieldsarray['proto']
		))->setWidth(2)->setHelp('Protocol');

		$group->add(new Form_Input(
			'filterlogentries_qty',
			null,
			'number',
			$filterlogentries_qty,
			['placeholder' => $nentries]
		))->setWidth(2)->setHelp('Quantity');

		$section->add($group);

		$group = new Form_Group('');

		$group->add(new Form_Checkbox(
			'actblock',
			'Block',
			'Block',
			in_arrayi('Block', $Include_Act),
			'Block'
		))->setWidth(1);

		$group->add(new Form_Input(
			'filterlogentries_interfaces',
			null,
			'text',
			$filterfieldsarray['interface']
		))->setWidth(3)->setHelp('Interface');

		$group->add(new Form_Input(
			'filterlogentries_destinationport',
			null,
			'text',
			$filterfieldsarray['dstport']
		))->setWidth(2)->setHelp('Destination Port');

		$group->add(new Form_Input(
			'filterlogentries_protocolflags',
			null,
			'text',
			$filterfieldsarray['tcpflags']
		))->setWidth(2)->setHelp('Protocol Flags');

		$btnsubmit = new Form_Button(
			'filterlogentries_submit',
			' ' . gettext('Apply Filter'),
			null,
			'fa-filter'
		);
	} else { // Simple log filter form
		$form = new Form(false);
		$form->setAttribute('id', 'filter-form')->addClass('collapse ' . $panel_state);

		$section = new Form_Section('Log Filter', 'filter-panel', COLLAPSIBLE|$panel_body_state);

		$group = new Form_Group('');

		$group->add(new Form_Select(
			'interface',
			'Interface',
			$interfacefilter,
			status_logs_build_if_list()
		))->setWidth(2)->setHelp('Interface');

		$group->add(new Form_Input(
			'filterlogentries_qty',
			null,
			'number',
			$filterlogentries_qty,
			['placeholder' => $nentries]
		))->setWidth(2)->setHelp('Quantity');

		$section->add($group);

		$group = new Form_Group('');

		$group->add(new Form_Input(
			'filtertext',
			null,
			'text',
			$filtertext
		))->setWidth(6)->setHelp('Filter Expression');

		$btnsubmit = new Form_Button(
			'filtersubmit',
			' ' . gettext('Apply Filter'),
			null,
			'fa-filter'
		);
	}

	$btnsubmit->removeClass('btn-primary')->addClass('btn-sm btn-success');

	$group->add(new Form_StaticText(
		'',
		$btnsubmit
	));

	$group->setHelp('<a target="_blank" href="http://www.php.net/manual/en/book.pcre.php">' . gettext('Regular expression reference') . '</a> ' . gettext('Precede with exclamation (!) to exclude match.'));
	$section->add($group);
	$form->add($section);
	print($form);
}


function status_logs_build_if_list() {
	$iflist = get_configured_interface_with_descr(false, true);
	//$iflist = get_interface_list();
	// Allow extending of the firewall edit interfaces
	pfSense_handle_custom_code("/usr/local/pkg/firewall_nat/pre_interfaces_edit");
	foreach ($iflist as $if => $ifdesc) {
		$interfaces[$if] = $ifdesc;
	}

	if ($config['l2tp']['mode'] == "server") {
		$interfaces['l2tp'] = "L2TP VPN";
	}

	if (is_pppoe_server_enabled() && have_ruleint_access("pppoe")) {
		$interfaces['pppoe'] = "PPPoE Server";
	}

	/* add ipsec interfaces */
	if (ipsec_enabled()) {
		$interfaces["enc0"] = "IPsec";
	}

	/* add openvpn/tun interfaces */
	if	($config['openvpn']["openvpn-server"] || $config['openvpn']["openvpn-client"]) {
		$interfaces["openvpn"] = "OpenVPN";
	}

	return($interfaces);
}


// Manage Log Section - Code
function manage_log_code() {
	global $logfile, $specific_log, $config, $pconfig, $rawfilter, $save_settings, $input_errors, $savemsg;

	$specific_log = basename($logfile) . '_settings';

	// Common to All Logs
	$pconfig['cronorder'] = $config['syslog'][$specific_log]['cronorder'];
	$pconfig['nentries'] = $config['syslog'][$specific_log]['nentries'];
	$pconfig['logfilesize'] = $config['syslog'][$specific_log]['logfilesize'];
	$pconfig['format'] = $config['syslog'][$specific_log]['format'];

	// Specific to System General (main) Log
	if ($logfile == 'system') {
		$pconfig['lognginx'] = !isset($config['syslog']['nolognginx']);
	}

	// Specific to Firewall Log
	if ($logfile == 'filter') {
		$pconfig['logdefaultblock'] = !isset($config['syslog']['nologdefaultblock']);
		$pconfig['logdefaultpass'] = isset($config['syslog']['nologdefaultpass']);
		$pconfig['logbogons'] = !isset($config['syslog']['nologbogons']);
		$pconfig['logprivatenets'] = !isset($config['syslog']['nologprivatenets']);
		$pconfig['filterdescriptions'] = $config['syslog']['filterdescriptions'];
	}

	$save_settings = getGETPOSTsettingvalue('save_settings', null);

	if ($save_settings) {

		// Common to All Logs
		$cronorder = getGETPOSTsettingvalue('cronorder',  null);
		$nentries = getGETPOSTsettingvalue('nentries', null);
		$logfilesize = getGETPOSTsettingvalue('logfilesize', null);
		$format  = getGETPOSTsettingvalue('format',  null);

		// Specific to System General (main) Log
		if ($logfile == 'system') {
			$lognginx  = getGETPOSTsettingvalue('lognginx',  null);
		}

		// Specific to Firewall Log
		if ($logfile == 'filter') {
			$logdefaultblock = getGETPOSTsettingvalue('logdefaultblock',  null);
			$logdefaultpass = getGETPOSTsettingvalue('logdefaultpass', null);
			$logbogons = getGETPOSTsettingvalue('logbogons', null);
			$logprivatenets  = getGETPOSTsettingvalue('logprivatenets',  null);
			$filterdescriptions  = getGETPOSTsettingvalue('filterdescriptions',  null);
		}

		unset($input_errors);
		global $input_errors;
		$pconfig = $_POST;

		/* input validation */
		// Common to All Logs
		if (isset($nentries) && (strlen($nentries) > 0)) {
			if (!is_numeric($nentries) || ($nentries < 5) || ($nentries > 2000)) {
				$input_errors[] = gettext("Number of log entries to show must be between 5 and 2000.");
			}
		}

		if (isset($logfilesize) && (strlen($logfilesize) > 0)) {
			if (!is_numeric($logfilesize) || ($logfilesize < 100000)) {
				$input_errors[] = gettext("Log file size must be numeric and greater than or equal to 100000.");
			}
		}

		if (!$input_errors) {

			# Clear out the specific log settings and leave only the applied settings to override the general logging options (global) settings.
			if (isset($config['syslog'][$specific_log])) {
				unset($config['syslog'][$specific_log]);
			}

		// Common to All Logs
			if ($cronorder != '') { # if not using the general logging options setting (global)
				$config['syslog'][$specific_log]['cronorder'] = $cronorder;
			}

			if (isset($nentries) && (strlen($nentries) > 0)) {
				$config['syslog'][$specific_log]['nentries'] = (int)$nentries;
			}

			if (isset($logfilesize) && (strlen($logfilesize) > 0)) {
				$config['syslog'][$specific_log]['logfilesize'] = (int)$logfilesize;
			}

			if ($format != '') { # if not using the general logging options setting (global)
				$config['syslog'][$specific_log]['format'] = $format;
			}

		// Specific to System General (main) Log
			if ($logfile == 'system') {
				$oldnolognginx = isset($config['syslog']['nolognginx']);
				$config['syslog']['nolognginx'] = $lognginx ? false : true;

				if ($oldnolognginx !== $config['syslog']['nolognginx']) {
					$logging_changed = $nginx_logging_changed = true;
				}
			}

		// Specific to  Firewall Log
			if ($logfile == 'filter') {
				$oldnologdefaultblock = isset($config['syslog']['nologdefaultblock']);
				$oldnologdefaultpass = isset($config['syslog']['nologdefaultpass']);
				$oldnologbogons = isset($config['syslog']['nologbogons']);
				$oldnologprivatenets = isset($config['syslog']['nologprivatenets']);

				$config['syslog']['nologdefaultblock'] = $logdefaultblock ? false : true;
				$config['syslog']['nologdefaultpass'] = $logdefaultpass ? true : false;
				$config['syslog']['nologbogons'] = $logbogons ? false : true;
				$config['syslog']['nologprivatenets'] = $logprivatenets ? false : true;

				if (is_numeric($filterdescriptions) && $filterdescriptions > 0) {
					$config['syslog']['filterdescriptions'] = $filterdescriptions;
				} else {
					unset($config['syslog']['filterdescriptions']);
				}

				if (
				    ($oldnologdefaultblock !== $config['syslog']['nologdefaultblock']) ||
				    ($oldnologdefaultpass !== $config['syslog']['nologdefaultpass']) ||
				    ($oldnologbogons !== $config['syslog']['nologbogons']) ||
				    ($oldnologprivatenets !== $config['syslog']['nologprivatenets'])) {
					$logging_changed = $firewall_logging_changed = true;
				}
			}


		// If any of the logging settings were changed then backup and sync (standard write_config).  Otherwise only write config (don't backup, don't sync).
		if ($logging_changed) {
			write_config($desc = gettext("Log Display Settings Saved: ") . gettext($allowed_logs[$logfile]["name"]), $backup = true, $write_config_only = false);
			$retval = 0;
			$retval = system_syslogd_start();
			$savemsg = gettext("The changes have been applied successfully.");
		} else {
			write_config($desc = gettext("Log Display Settings Saved (no backup, no sync): ") . gettext($allowed_logs[$logfile]["name"]), $backup = false, $write_config_only = true);
			$savemsg = '';
		}

		// Specific to System General (main) Log
		if ($logfile == 'system') {
			if ($nginx_logging_changed) {
				ob_flush();
				flush();
				log_error(gettext("webConfigurator configuration has changed. Restarting webConfigurator."));
				send_event("service restart webgui");
				$savemsg .= "<br />" . gettext("WebGUI process is restarting.");
			}
		}

		// Specific to Firewall Log
			if ($logfile == 'filter') {
				if ($firewall_logging_changed) {
					require_once("filter.inc");
					$retval |= filter_configure();
					filter_pflog_start(true);

					$savemsg = get_std_save_message($retval);
				}
			}
		}
	}


	// Formatted/Raw Display
	if ($config['syslog'][$specific_log]['format'] == 'formatted') {
		$rawfilter = false;
	} else if ($config['syslog'][$specific_log]['format'] == 'raw') {
		$rawfilter = true;
	} else {	//  Use the general logging options setting (global).
		$rawfilter = isset($config['syslog']['rawfilter']);
	}
}

# Manage Log Section/Form
function manage_log_section() {

	global $input_errors, $allowed_logs, $logfile, $config, $pconfig;
	global $system_logs_manage_log_form_hidden;

	if ($input_errors) {
		print_input_errors($input_errors);
		$manage_log_active = true;
	}

	if ($filter_active) {
		$panel_state = 'in';
		$panel_body_state = SEC_OPEN;
	} else {
		if ($system_logs_manage_log_form_hidden) {
			$panel_state = 'out';
			$panel_body_state = SEC_OPEN;
		} else {
			$panel_state = 'in';
			$panel_body_state = SEC_CLOSED;
		}
	}

	$form = new Form(false);
	$form->setAttribute('id', 'manage-log-form')->addClass('collapse ' . $panel_state);

	$section = new Form_Section(gettext('Manage') . ' ' . gettext($allowed_logs[$logfile]["name"]) . ' ' . gettext('Log'), 'manage-log-panel', COLLAPSIBLE|$panel_body_state);

	$section->addInput(new Form_StaticText(
		'',
		'These settings override the "General Logging Options" settings.'
	));


	// Common to All Logs
	$group = new Form_Group('Forward/Reverse Display');

	$group->add(new Form_Checkbox(
		'cronorder',
		null,
		'Forward',
		($pconfig['cronorder'] == 'forward') ? true : false,
		'forward'
	))->displayAsRadio()->setHelp('(newest at bottom)');

	$group->add(new Form_Checkbox(
		'cronorder',
		null,
		'Reverse',
		($pconfig['cronorder'] == 'reverse') ? true : false,
		'reverse'
	))->displayAsRadio()->setHelp('(newest at top)');

	$group->add(new Form_Checkbox(
		'cronorder',
		null,
		'General Logging Options Setting',
		($pconfig['cronorder'] == '') ? true : false,
		''
	))->displayAsRadio();

	$group->setHelp('Show log entries in forward or reverse order.');
	$section->add($group);

	$group = new Form_Group('GUI Log Entries');

	// Use the general logging options setting (global) as placeholder.
	$group->add(new Form_Input(
		'nentries',
		'GUI Log Entries',
		'number',
		$pconfig['nentries'],
		['min' => 5, 'max' => 2000, 'placeholder' => $config['syslog']['nentries'] ? $config['syslog']['nentries'] : "50"]
	))->setWidth(2);

	$group->setHelp('This is the number of log entries displayed in the GUI. It does not affect how many entries are contained in the log.');
	$section->add($group);

	$group = new Form_Group('Log file size (Bytes)');

	// Use the general logging options setting (global) as placeholder.
	$group->add(new Form_Input(
		'logfilesize',
		'Log file size (Bytes)',
		'number',
		$pconfig['logfilesize'],
		['min' => 100000, 'placeholder' => $config['syslog']['logfilesize'] ? $config['syslog']['logfilesize'] : "511488"]
	))->setWidth(2);
	$group->setHelp("The log is held in a constant-size circular log file. This field controls how large the log file is, and thus how many entries may exist inside the log. The default is approximately 500KB." .
						'<br /><br />' .
				"NOTE: The log size is changed the next time it is cleared. To immediately change the log size, first save the options to set the size, then clear the log using the \"Clear Log\" action below. ");
	$section->add($group);

	$group = new Form_Group('Formatted/Raw Display');

	$group->add(new Form_Checkbox(
		'format',
		null,
		'Formatted',
		($pconfig['format'] == 'formatted') ? true : false,
		'formatted'
	))->displayAsRadio();

	$group->add(new Form_Checkbox(
		'format',
		null,
		'Raw',
		($pconfig['format'] == 'raw') ? true : false,
		'raw'
	))->displayAsRadio();

	$group->add(new Form_Checkbox(
		'format',
		null,
		'General Logging Options Setting',
		($pconfig['format'] == '') ? true : false,
		''
	))->displayAsRadio();

	$group->setHelp('Show the log entries as formatted or raw output as generated by the service. The raw output will reveal more detailed information, but it is more difficult to read.');
	$section->add($group);


	// Specific to System General (main) Log
	if ($logfile == 'system') {
		$section->addInput(new Form_Checkbox(
			'lognginx',
			'Web Server Log',
			'Log errors from the web server process',
			$pconfig['lognginx']
		))->setHelp('If this is checked, errors from the nginx web server process for the GUI or Captive Portal will appear in the system log.');
	}


	// Specific to Firewall Log
	if ($logfile == 'filter') {
		$section->addInput(new Form_Checkbox(
			'logdefaultblock',
			'Log firewall default blocks',
			'Log packets matched from the default block rules in the ruleset',
			$pconfig['logdefaultblock']
		))->setHelp('Packets that are blocked by the implicit default block rule will not be logged if this option is unchecked. Per-rule logging options are still respected.');

		$section->addInput(new Form_Checkbox(
			'logdefaultpass',
			null,
			'Log packets matched from the default pass rules put in the ruleset',
			$pconfig['logdefaultpass']
		))->setHelp('Packets that are allowed by the implicit default pass rule will be logged if this option is checked. Per-rule logging options are still respected. ');

		$section->addInput(new Form_Checkbox(
			'logbogons',
			null,
			'Log packets blocked by \'Block Bogon Networks\' rules',
			$pconfig['logbogons']
		));

		$section->addInput(new Form_Checkbox(
			'logprivatenets',
			null,
			'Log packets blocked by \'Block Private Networks\' rules',
			$pconfig['logprivatenets']
		));

		$section->addInput(new Form_Select(
			'filterdescriptions',
			'Where to show rule descriptions',
			!isset($pconfig['filterdescriptions']) ? '0':$pconfig['filterdescriptions'],
			array(
				'0' => gettext('Dont load descriptions'),
				'1' => gettext('Display as column'),
				'2' => gettext('Display as second row')
			)
		))->setHelp('Show the applied rule description below or in the firewall log rows' . '<br />' .
					'Displaying rule descriptions for all lines in the log might affect performance with large rule sets');
	}


	// Common to All Logs
	$group = new Form_Group('Action');

	$btnsavesettings = new Form_Button(
		'save_settings',
		' ' . gettext('Save'),
		null,
		'fa-save'
	);

	$btnsavesettings->addClass('btn-sm btn-primary');

	$group->add(new Form_StaticText(
		'',
		$btnsavesettings
	))->setHelp('Saves changed settings.');


	$btnclear = new Form_Button(
		'clear',
		' ' . gettext('Clear log'),
		null,
		'fa-trash'
	);

	$btnclear->removeClass('btn-primary')->addClass('btn-sm btn-danger');

	$group->add(new Form_StaticText(
		'',
		$btnclear
	))->setHelp('Clears local log file and reinitializes it as an empty log. Save any settings changes first.');

	$section->add($group);

	$form->add($section);
	print $form;
}
?>
