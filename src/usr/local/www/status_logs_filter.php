<?php
/*
	status_logs_filter.php
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

##|+PRIV
##|*IDENT=page-diagnostics-logs-firewall
##|*NAME=Status: Logs: Firewall
##|*DESCR=Allow access to the 'Status: Logs: Firewall' page.
##|*MATCH=status_logs_filter.php*
##|-PRIV

require("guiconfig.inc");
require_once("ipsec.inc");
require_once("filter_log.inc");

# --- AJAX RESOLVE ---
if (isset($_POST['resolve'])) {
	$ip = strtolower($_POST['resolve']);
	$res = (is_ipaddr($ip) ? gethostbyaddr($ip) : '');

	if ($res && $res != $ip) {
		$response = array('resolve_ip' => $ip, 'resolve_text' => $res);
	} else {
		$response = array('resolve_ip' => $ip, 'resolve_text' => gettext("Cannot resolve"));
	}

	echo json_encode(str_replace("\\", "\\\\", $response)); // single escape chars can break JSON decode
	exit;
}

/*
Build a list of allowed log files so we can reject others to prevent the page
from acting on unauthorized files.
*/
$allowed_logs = array(
	"filter" => array("name" => "Firewall",
		    "shortcut" => "filter"),
);

// The logs to display are specified in a GET argument. Default to 'system' logs
if (!$_GET['logfile']) {
	$logfile = 'filter';
} else {
	$logfile = $_GET['logfile'];
	if (!array_key_exists($logfile, $allowed_logs)) {
		/* Do not let someone attempt to load an unauthorized log. */
		$logfile = 'filter';
	}
}

$filter_logfile = "{$g['varlog_path']}/" . basename($logfile) . ".log";

function getGETPOSTsettingvalue($settingname, $default) {
	$settingvalue = $default;
	if ($_GET[$settingname]) {
		$settingvalue = $_GET[$settingname];
	}
	if ($_POST[$settingname]) {
		$settingvalue = $_POST[$settingname];
	}
	return $settingvalue;
}

$rulenum = getGETPOSTsettingvalue('getrulenum', null);

if ($rulenum) {
	list($rulenum, $tracker, $type) = explode(',', $rulenum);
	$rule = find_rule_by_number($rulenum, $tracker, $type);
	echo gettext("The rule that triggered this action is") . ":\n\n{$rule}";
	exit;
}

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

# Manage Log - Code

$specific_log = basename($logfile) . '_settings';

# All
$pconfig['cronorder'] = $config['syslog'][$specific_log]['cronorder'];
$pconfig['nentries'] = $config['syslog'][$specific_log]['nentries'];
$pconfig['logfilesize'] = $config['syslog'][$specific_log]['logfilesize'];
$pconfig['format'] = $config['syslog'][$specific_log]['format'];

# System General (main) Specific
$pconfig['loglighttpd'] = !isset($config['syslog']['nologlighttpd']);

# Firewall Specific
$pconfig['logdefaultblock'] = !isset($config['syslog']['nologdefaultblock']);
$pconfig['logdefaultpass'] = isset($config['syslog']['nologdefaultpass']);
$pconfig['logbogons'] = !isset($config['syslog']['nologbogons']);
$pconfig['logprivatenets'] = !isset($config['syslog']['nologprivatenets']);
$pconfig['filterdescriptions'] = $config['syslog']['filterdescriptions'];

$save_settings = getGETPOSTsettingvalue('save_settings', null);

if ($save_settings) {

	# All
	$cronorder = getGETPOSTsettingvalue('cronorder',  null);
	$nentries = getGETPOSTsettingvalue('nentries', null);
	$logfilesize = getGETPOSTsettingvalue('logfilesize', null);
	$format  = getGETPOSTsettingvalue('format',  null);

	# System General (main) Specific
	$loglighttpd  = getGETPOSTsettingvalue('loglighttpd',  null);

	# Firewall Specific
	$logdefaultblock = getGETPOSTsettingvalue('logdefaultblock',  null);
	$logdefaultpass = getGETPOSTsettingvalue('logdefaultpass', null);
	$logbogons = getGETPOSTsettingvalue('logbogons', null);
	$logprivatenets  = getGETPOSTsettingvalue('logprivatenets',  null);
	$filterdescriptions  = getGETPOSTsettingvalue('filterdescriptions',  null);

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
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
		unset($config['syslog'][$specific_log]);

	# All
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

	# System General (main) Specific
		if ($logfile == 'system') {
			$oldnologlighttpd = isset($config['syslog']['nologlighttpd']);
			$config['syslog']['nologlighttpd'] = $loglighttpd ? false : true;
		}

	# Firewall Specific
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
		}


		write_config($desc = "Log Display Settings Saved: " . gettext($allowed_logs[$logfile]["name"]));

		$retval = 0;
		$savemsg = get_std_save_message($retval);

	# System General (main) Specific
		if ($logfile == 'system') {
			if ($oldnologlighttpd !== isset($config['syslog']['nologlighttpd'])) {
				ob_flush();
				flush();
				log_error(gettext("webConfigurator configuration has changed. Restarting webConfigurator."));
				send_event("service restart webgui");
				$savemsg .= "<br />" . gettext("WebGUI process is restarting.");
			}
		}

	# Firewall Specific
		if ($logfile == 'filter') {
			if (($oldnologdefaultblock !== isset($config['syslog']['nologdefaultblock'])) ||
			    ($oldnologdefaultpass !== isset($config['syslog']['nologdefaultpass'])) ||
			    ($oldnologbogons !== isset($config['syslog']['nologbogons'])) ||
			    ($oldnologprivatenets !== isset($config['syslog']['nologprivatenets']))) {

				require_once("filter.inc");
				$retval |= filter_configure();
				filter_pflog_start(true);
			}
		}
	}
}


# Formatted/Raw Display
if ($config['syslog'][$specific_log]['format'] == 'formatted') {
	$rawfilter = false;
} else if ($config['syslog'][$specific_log]['format'] == 'raw') {
	$rawfilter = true;
} else {	# Use the general logging options setting (global).
	$rawfilter = isset($config['syslog']['rawfilter']);
}


isset($config['syslog'][$specific_log]['nentries']) ? $nentries = $config['syslog'][$specific_log]['nentries'] : $nentries = $config['syslog']['nentries'];

# Override Display Quantity
if ($filterlogentries_qty) {
	$nentries = $filterlogentries_qty;
}

if (!$nentries || !is_numeric($nentries)) {
	$nentries = 50;
}

if ($_POST['clear']) {
	clear_log_file($filter_logfile);
}

/* Setup shortcuts if they exist */

if (!empty($allowed_logs[$logfile]["shortcut"])) {
	$shortcut_section = $allowed_logs[$logfile]["shortcut"];
}

$pgtitle = array(gettext("Status"), gettext("System logs"), gettext($allowed_logs[$logfile]["name"]));
include("head.inc");

if (!$input_errors && $savemsg) {
	print_info_box($savemsg);
	$manage_log_active = false;
}


function build_if_list() {
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

$Include_Act = explode(",", str_replace(" ", ",", $filterfieldsarray['act']));

if ($filterfieldsarray['interface'] == "All") {
	$interface = "";
}

$tab_array = array();
$tab_array[] = array(gettext("System"), ($logfile == 'system'), "status_logs.php");
$tab_array[] = array(gettext("Firewall"), ($logfile == 'filter'), "status_logs_filter.php");
$tab_array[] = array(gettext("DHCP"), ($logfile == 'dhcpd'), "status_logs.php?logfile=dhcpd");
$tab_array[] = array(gettext("Portal Auth"), ($logfile == 'portalauth'), "status_logs.php?logfile=portalauth");
$tab_array[] = array(gettext("IPsec"), ($logfile == 'ipsec'), "status_logs.php?logfile=ipsec");
$tab_array[] = array(gettext("PPP"), ($logfile == 'ppp'), "status_logs.php?logfile=ppp");
$tab_array[] = array(gettext("VPN"), false, "status_logs_vpn.php");
$tab_array[] = array(gettext("Load Balancer"), ($logfile == 'relayd'), "status_logs.php?logfile=relayd");
$tab_array[] = array(gettext("OpenVPN"), ($logfile == 'openvpn'), "status_logs.php?logfile=openvpn");
$tab_array[] = array(gettext("NTP"), ($logfile == 'ntpd'), "status_logs.php?logfile=ntpd");
$tab_array[] = array(gettext("Settings"), false, "status_logs_settings.php");
display_top_tabs($tab_array);

$tab_array = array();
$tab_array[] = array(gettext("Normal View"), true, "/status_logs_filter.php");
$tab_array[] = array(gettext("Dynamic View"), false, "/status_logs_filter_dynamic.php");
$tab_array[] = array(gettext("Summary View"), false, "/status_logs_filter_summary.php");
display_top_tabs($tab_array, false, 'nav nav-tabs');

if ($filter_active) {
	$filter_state = SEC_OPEN;
} else {
	$filter_state = SEC_CLOSED;
}

if (!$rawfilter) { // Advanced log filter form
	$form = new Form(false);

	$section = new Form_Section('Advanced Log Filter', 'adv-filter-panel', COLLAPSIBLE|$filter_state);

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
		null,
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
		null,
		'Block',
		in_arrayi('Block', $Include_Act),
		'Block'
	))->setWidth(1);

	$group->add(new Form_Input(
		'filterlogentries_interfaces',
		null,
		'text',
		$filterfieldsarray['interface']
	))->setWidth(2)->setHelp('Interface');

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

	$section = new Form_Section('Log Filter', 'basic-filter-panel', COLLAPSIBLE|$filter_state);

	$group = new Form_Group('');

	$group->add(new Form_Select(
		'interface',
		null,
		$interfacefilter,
		build_if_list()
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

$btnsubmit->removeClass('btn-primary')->addClass('btn-success')->addClass('btn-sm');

$group->add(new Form_StaticText(
	'',
	$btnsubmit
));

$group->setHelp('<a target="_blank" href="http://www.php.net/manual/en/book.pcre.php">' . gettext('Regular expression reference') . '</a> ' . gettext('Precede with exclamation (!) to exclude match.'));
$section->add($group);
$form->add($section);
print($form);

// Now the forms are complete we can draw the log table and its controls
if (!$rawfilter) {
	$iflist = get_configured_interface_with_descr(false, true);

	if ($iflist[$interfacefilter]) {
		$interfacefilter = $iflist[$interfacefilter];
	}

	if ($filterlogentries_submit) {
		$filterlog = conv_log_filter($filter_logfile, $nentries, $nentries + 100, $filterfieldsarray);
	} else {
		$filterlog = conv_log_filter($filter_logfile, $nentries, $nentries + 100, $filtertext, $interfacefilter);
	}
?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">
<?php
	if ((!$filtertext) && (!$filterfieldsarray)) {
		printf(gettext("Last %d %s log entries."), count($filterlog), gettext($allowed_logs[$logfile]["name"]));
	} else {
		printf(gettext("%d matched %s log entries."), count($filterlog), gettext($allowed_logs[$logfile]["name"]));
	}

	printf(" (" . gettext("Maximum %d") . ")", $nentries);
?>
		</h2>
	</div>
	<div class="panel-body">
	   <div class="table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr style="white-space:nowrap;">
					<th><?=gettext("Act")?></th>
					<th><?=gettext("Time")?></th>
					<th><?=gettext("IF")?></th>
<?php
	if ($config['syslog']['filterdescriptions'] === "1") {
?>
					<th style="width:100%">
						<?=gettext("Rule")?>
					</th>
<?php
	}
?>
					<th><?=gettext("Source")?></th>
					<th><?=gettext("Destination")?></th>
					<th><?=gettext("Proto")?></th>
				</tr>
			</thead>
			<tbody>
<?php
	if ($config['syslog']['filterdescriptions']) {
		buffer_rules_load();
	}

	foreach ($filterlog as $filterent) {
?>
				<tr style="white-space:nowrap;">
					<td>
<?php
		if ($filterent['act'] == "block") {
			$icon_act = "fa-times text-danger";
		} else {
			$icon_act = "fa-check text-success";
		}

		if ($filterent['count']) {
			$margin_left = '0em';
		} else {
			$margin_left = '0.4em';
		}
?>
						<i style="margin-left:<?php echo $margin_left;?>" class="fa <?php echo $icon_act;?> icon-pointer" title="<?php echo $filterent['act'] .'/'. $filterent['tracker'];?>" onclick="javascript:getURL('status_logs_filter.php?getrulenum=<?="{$filterent['rulenum']},{$filterent['tracker']},{$filterent['act']}"; ?>', outputrule);"></i>
<?php
		if ($filterent['count']) {
			echo $filterent['count'];
		}
?>
					</td>
					<td>
		<?=htmlspecialchars($filterent['time'])?>
					</td>
					<td>
<?php
		if ($filterent['direction'] == "out") {
			print('&#x25ba;' . ' ');
		}
?>
		<?=htmlspecialchars($filterent['interface'])?>
					</td>
<?php
		if ($config['syslog']['filterdescriptions'] === "1") {
?>
					<td style="white-space:normal;">
			<?=find_rule_by_number_buffer($filterent['rulenum'], $filterent['tracker'], $filterent['act'])?>
					</td>
<?php
		}

		$int = strtolower($filterent['interface']);
		$proto = strtolower($filterent['proto']);

		if ($filterent['version'] == '6') {
			$ipproto = "inet6";
			$filterent['srcip'] = "[{$filterent['srcip']}]";
			$filterent['dstip'] = "[{$filterent['dstip']}]";
		} else {
			$ipproto = "inet";
		}

		$srcstr = $filterent['srcip'] . get_port_with_service($filterent['srcport'], $proto);
		$src_htmlclass = str_replace(array('.', ':'), '-', $filterent['srcip']);
		$dststr = $filterent['dstip'] . get_port_with_service($filterent['dstport'], $proto);
		$dst_htmlclass = str_replace(array('.', ':'), '-', $filterent['dstip']);
?>
					<td style="white-space:nowrap;">
						<i class="fa fa-info icon-pointer icon-primary" onclick="javascript:resolve_with_ajax('<?="{$filterent['srcip']}"; ?>');" title="<?=gettext("Click to resolve")?>">
						</i>

						<a class="fa fa-minus-square-o icon-pointer icon-primary" href="easyrule.php?<?="action=block&amp;int={$int}&amp;src={$filterent['srcip']}&amp;ipproto={$ipproto}"; ?>" title="<?=gettext("Easy Rule: Add to Block List")?>" onclick="return confirm('<?=gettext("Do you really want to add this BLOCK rule?")?>')">
						</a>

						<?=$srcstr . '<span class="RESOLVE-' . $src_htmlclass . '"></span>'?>
					</td>
					<td style="white-space:nowrap;">
						<i class="fa fa-info icon-pointer icon-primary; ICON-<?= $dst_htmlclass; ?>" onclick="javascript:resolve_with_ajax('<?="{$filterent['dstip']}"; ?>');" title="<?=gettext("Click to resolve")?>">
						</i>

						<a class="fa fa-plus-square-o icon-pointer icon-primary" href="easyrule.php?<?="action=pass&amp;int={$int}&amp;proto={$proto}&amp;src={$filterent['srcip']}&amp;dst={$filterent['dstip']}&amp;dstport={$filterent['dstport']}&amp;ipproto={$ipproto}"; ?>" title="<?=gettext("Easy Rule: Pass this traffic")?>" onclick="return confirm('<?=gettext("Do you really want to add this PASS rule?")?>')">
						</a>
						<?=$dststr . '<span class="RESOLVE-' . $dst_htmlclass . '"></span>'?>
					</td>
<?php
		if ($filterent['proto'] == "TCP") {
			$filterent['proto'] .= ":{$filterent['tcpflags']}";
		}
?>
					<td>
						<?=htmlspecialchars($filterent['proto'])?>
					</td>
				</tr>
<?php
		if (isset($config['syslog']['filterdescriptions']) && $config['syslog']['filterdescriptions'] === "2") {
?>
				<tr>
					<td colspan="2" />
					<td colspan="4"><?=find_rule_by_number_buffer($filterent['rulenum'], $filterent['tracker'], $filterent['act'])?></td>
				</tr>
<?php
		}
	} // e-o-foreach
	buffer_rules_clear();
?>
			</tbody>
		</table>
<?php
	if (count($filterlog) == 0) {
		print_info_box(gettext('No logs to display'));
	}
?>
		</div>
	</div>
</div>

<?php
} else {
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Last ")?><?=$nentries?> <?=gettext($allowed_logs[$logfile]["name"])?><?=gettext(" log entries")?></h2></div>
	<div class="table table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr style="white-space:nowrap;">
					<th><?=gettext("Time")?></th>
					<th style="width:100%"><?=gettext("Message")?></th>
				</tr>
			</thead>
			<tbody>
<?php
	if ($filtertext) {
		$rows = dump_clog($filter_logfile, $nentries, true, array("$filtertext"));
	} else {
		$rows = dump_clog($filter_logfile, $nentries, true, array());
	}
?>
			</tbody>
		</table>
<?php
	if ($rows == 0) {
		print_info_box(gettext('No logs to display'));
	}
?>
	</div>
</div>
<?php
}
?>

<div id="infoblock">

<?php

print_info_box('<a href="https://doc.pfsense.org/index.php/What_are_TCP_Flags%3F">' .
	gettext("TCP Flags") . '</a>: F - FIN, S - SYN, A or . - ACK, R - RST, P - PSH, U - URG, E - ECE, C - CWR' . '<br />' .
	'<i class="fa fa-minus-square-o icon-primary"></i> = Add to block list., <i class="fa fa-plus-square-o icon-primary"></i> = Pass traffic, <i class="fa fa-info icon-primary"></i> = Resolve');

?>
</div>

<?php
# Manage Log - Section/Form

if ($input_errors) {
	print_input_errors($input_errors);
	$manage_log_active = true;
}

if ($manage_log_active) {
	$manage_log_state = SEC_OPEN;
} else {
	$manage_log_state = SEC_CLOSED;
}

$form = new Form(false);

$section = new Form_Section(gettext('Manage') . ' ' . gettext($allowed_logs[$logfile]["name"]) . ' ' . gettext('Log'), 'log-manager-panel', COLLAPSIBLE|$manage_log_state);

$section->addInput(new Form_StaticText(
	'',
	'These settings override the "General Logging Options" settings.'
));


# All
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

# Use the general logging options setting (global) as placeholder.
$group->add(new Form_Input(
	'nentries',
	'GUI Log Entries',
	'number',
	$pconfig['nentries'],
	['min' => 5, 'max' => 2000, 'placeholder' => $config['syslog']['nentries']]
))->setWidth(2);

$group->setHelp('This is the number of log entries displayed in the GUI. It does not affect how many entries are contained in the log.');
$section->add($group);

$group = new Form_Group('Log file size (Bytes)');

# Use the general logging options setting (global) as placeholder.
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


# System General (main) Specific
if ($logfile == 'system') {
	$section->addInput(new Form_Checkbox(
		'loglighttpd',
		'Web Server Log',
		'Log errors from the web server process',
		$pconfig['loglighttpd']
	))->setHelp('If this is checked, errors from the lighttpd web server process for the GUI or Captive Portal will appear in the system log.');
}


# Firewall Specific
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
		'0' => 'Dont load descriptions',
		'1' => 'Display as column',
		'2' => 'Display as second row'
	)
))->setHelp('Show the applied rule description below or in the firewall log rows' . '<br />' .
			'Displaying rule descriptions for all lines in the log might affect performance with large rule sets');
}


$group = new Form_Group('Action');

$btnsavesettings = new Form_Button(
	'save_settings',
	gettext('Save'),
	null
);

$btnsavesettings->addClass('btn-sm');

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

$btnclear->removeClass('btn-primary')->addClass('btn-danger')->addClass('btn-sm');

$group->add(new Form_StaticText(
	'',
	$btnclear
))->setHelp('Clears local log file and reinitializes it as an empty log. Save any settings changes first.');

$section->add($group);
$form->add($section);
print $form;
?>


<!-- AJAXY STUFF -->
<script type="text/javascript">
//<![CDATA[
function outputrule(req) {
	alert(req.content);
}

function resolve_with_ajax(ip_to_resolve) {
	var url = "/status_logs_filter.php";

	$.ajax(
		url,
		{
			method: 'post',
			dataType: 'json',
			data: {
				resolve: ip_to_resolve,
				},
			complete: resolve_ip_callback
		});

}

function resolve_ip_callback(transport) {
	var response = $.parseJSON(transport.responseText);
	var resolve_class = htmlspecialchars(response.resolve_ip.replace(/[.:]/g, '-'));
	var resolve_text = '<small><br />' + htmlspecialchars(response.resolve_text) + '<\/small>';

	jQuery('span.RESOLVE-' + resolve_class).html(resolve_text);
}

// From http://stackoverflow.com/questions/5499078/fastest-method-to-escape-html-tags-as-html-entities
function htmlspecialchars(str) {
	return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&apos;');
}

if (typeof getURL == 'undefined') {
	getURL = function(url, callback) {
		if (!url)
			throw 'No URL for getURL';
		try {
			if (typeof callback.operationComplete == 'function')
				callback = callback.operationComplete;
		} catch (e) {}
			if (typeof callback != 'function')
				throw 'No callback function for getURL';
		var http_request = null;
		if (typeof XMLHttpRequest != 'undefined') {
			http_request = new XMLHttpRequest();
		}
		else if (typeof ActiveXObject != 'undefined') {
			try {
				http_request = new ActiveXObject('Msxml2.XMLHTTP');
			} catch (e) {
				try {
					http_request = new ActiveXObject('Microsoft.XMLHTTP');
				} catch (e) {}
			}
		}
		if (!http_request)
			throw 'Both getURL and XMLHttpRequest are undefined';
		http_request.onreadystatechange = function() {
			if (http_request.readyState == 4) {
				callback( { success : true,
				  content : http_request.responseText,
				  contentType : http_request.getResponseHeader("Content-Type") } );
			}
		};
		http_request.open('GET', url, true);
		http_request.send(null);
	};
}

events.push(function() {
    $('.fa').tooltip();
});
//]]>
</script>

<?php include("foot.inc");
?>
