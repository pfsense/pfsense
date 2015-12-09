<?php
/*
	diag_logs.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *  Some or all of this file is based on the m0n0wall project which is
 *  Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *  Redistribution and use in source and binary forms, with or without modification,
 *  are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution.
 *
 *  3. All advertising materials mentioning features or use of this software
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
 */

/*
	pfSense_MODULE:	system
*/

##|+PRIV
##|*IDENT=page-diagnostics-logs-system
##|*NAME=Status: Logs: System
##|*DESCR=Allow access to the 'Status: System Logs: General' page.
##|*MATCH=diag_logs.php
##|-PRIV

require("guiconfig.inc");
require_once("filter_log.inc");

/*
Build a list of allowed log files so we can reject others to prevent the page
from acting on unauthorized files.
*/
$allowed_logs = array(
	"system" => array("name" => "General",
		    "shortcut" => ""),
	"dhcpd" => array("name" => "DHCP",
		    "shortcut" => "dhcp"),
	"portalauth" => array("name" => "Captive Portal Authentication",
		    "shortcut" => "captiveportal"),
	"ipsec" => array("name" => "IPsec",
		    "shortcut" => "ipsec"),
	"ppp" => array("name" => "PPP",
		    "shortcut" => ""),
	"relayd" => array("name" => "Load Balancer",
		    "shortcut" => "relayd"),
	"openvpn" => array("name" => "OpenVPN",
		    "shortcut" => "openvpn"),
	"ntpd" => array("name" => "NTPd",
		    "shortcut" => "ntp"),
	"gateways" => array("name" => "Gateways",
		    "shortcut" => "gateways"),
	"routing" => array("name" => "Routing",
		    "shortcut" => "routing"),
	"resolver" => array("name" => "DNS Resolver",
		    "shortcut" => "resolver"),
	"wireless" => array("name" => "Wireless",
		    "shortcut" => "wireless"),
);

// The logs to display are specified in a GET argument. Default to 'system' logs
if (!$_GET['logfile']) {
	$logfile = 'system';
} else {
	$logfile = $_GET['logfile'];
	if (!array_key_exists($logfile, $allowed_logs)) {
		/* Do not let someone attempt to load an unauthorized log. */
		$logfile = 'system';
	}
}

$system_logfile = "{$g['varlog_path']}/" . basename($logfile) . ".log";


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


# Manage Log - Code

$specific_log = basename($logfile) . '_settings';

# All
$pconfig['cronorder'] = $config['syslog'][$specific_log]['cronorder'];
$pconfig['nentries'] = $config['syslog'][$specific_log]['nentries'];
$pconfig['logfilesize'] = $config['syslog'][$specific_log]['logfilesize'];
$pconfig['format'] = $config['syslog'][$specific_log]['format'];

# System General (main) Specific
$pconfig['loglighttpd'] = !isset($config['syslog']['nologlighttpd']);

$save_settings = getGETPOSTsettingvalue('save_settings', null);

if ($save_settings) {

	# All
	$cronorder = getGETPOSTsettingvalue('cronorder',  null);
	$nentries = getGETPOSTsettingvalue('nentries', null);
	$logfilesize = getGETPOSTsettingvalue('logfilesize', null);
	$format  = getGETPOSTsettingvalue('format',  null);

	# System General (main) Specific
	$loglighttpd  = getGETPOSTsettingvalue('loglighttpd',  null);

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	# All
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
	}
}


# Formatted/Raw Display
if ($config['syslog'][$specific_log]['format'] == 'formatted') {
	$rawfilter = false;
}
else if ($config['syslog'][$specific_log]['format'] == 'raw') {
	$rawfilter = true;
}	
else {	# Use the general logging options setting (global).
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
	clear_log_file($system_logfile);
}

if ($filtertext) {
	$filtertextmeta="?filtertext=$filtertext";
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

$tab_array = array();
$tab_array[] = array(gettext("System"), ($logfile == 'system'), "diag_logs.php");
$tab_array[] = array(gettext("Firewall"), false, "diag_logs_filter.php");
$tab_array[] = array(gettext("DHCP"), ($logfile == 'dhcpd'), "diag_logs.php?logfile=dhcpd");
$tab_array[] = array(gettext("Portal Auth"), ($logfile == 'portalauth'), "diag_logs.php?logfile=portalauth");
$tab_array[] = array(gettext("IPsec"), ($logfile == 'ipsec'), "diag_logs.php?logfile=ipsec");
$tab_array[] = array(gettext("PPP"), ($logfile == 'ppp'), "diag_logs.php?logfile=ppp");
$tab_array[] = array(gettext("VPN"), false, "diag_logs_vpn.php");
$tab_array[] = array(gettext("Load Balancer"), ($logfile == 'relayd'), "diag_logs.php?logfile=relayd");
$tab_array[] = array(gettext("OpenVPN"), ($logfile == 'openvpn'), "diag_logs.php?logfile=openvpn");
$tab_array[] = array(gettext("NTP"), ($logfile == 'ntpd'), "diag_logs.php?logfile=ntpd");
$tab_array[] = array(gettext("Settings"), false, "diag_logs_settings.php");
display_top_tabs($tab_array);

$tab_array = array();
if (in_array($logfile, array('system', 'gateways', 'routing', 'resolver', 'wireless')))	 {
	$tab_array[] = array(gettext("General"), ($logfile == 'system'), "/diag_logs.php");
	$tab_array[] = array(gettext("Gateways"), ($logfile == 'gateways'), "/diag_logs.php?logfile=gateways");
	$tab_array[] = array(gettext("Routing"), ($logfile == 'routing'), "/diag_logs.php?logfile=routing");
	$tab_array[] = array(gettext("Resolver"), ($logfile == 'resolver'), "/diag_logs.php?logfile=resolver");
	$tab_array[] = array(gettext("Wireless"), ($logfile == 'wireless'), "/diag_logs.php?logfile=wireless");
	display_top_tabs($tab_array, false, 'nav nav-tabs');
}

define(SEC_OPEN, 0x00);
define(SEC_CLOSED, 0x04);

if ($filter_active)
	$filter_state = SEC_OPEN;
else
	$filter_state = SEC_CLOSED;

if (!$rawfilter) { // Advanced log filter form
	$form = new Form(false);

	$section = new Form_Section('Advanced Log Filter', 'adv-filter-panel', COLLAPSIBLE|$filter_state);

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
}
else { // Simple log filter form
	$form = new Form(false);

	$section = new Form_Section('Log Filter', 'basic-filter-panel', COLLAPSIBLE|$filter_state);

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

$btnsubmit->removeClass('btn-primary')->addClass('btn-success')->addClass('btn-sm');

$group->add(new Form_StaticText(
	'',
	$btnsubmit
));

$group->setHelp('<a target="_blank" href="http://www.php.net/manual/en/book.pcre.php">' . gettext('Regular expression reference') . '</a> ' . gettext('Precede with exclamation (!) to exclude match.'));
$section->add($group);
$form->add($section);
print $form;

// Now the forms are complete we can draw the log table and its controls
if (!$rawfilter) {
	if ($filterlogentries_submit)
		$filterlog = conv_log_filter($system_logfile, $nentries, $nentries + 100, $filterfieldsarray);
	else
		$filterlog = conv_log_filter($system_logfile, $nentries, $nentries + 100, $filtertext);
?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">
<?php
	if ((!$filtertext) && (!$filterfieldsarray))
		printf(gettext("Last %d %s log entries."), count($filterlog), gettext($allowed_logs[$logfile]["name"]));
	else
		printf(gettext("%d matched %s log entries."), count($filterlog), gettext($allowed_logs[$logfile]["name"]));

	printf(" (" . gettext("Maximum %d") . ")", $nentries);
?>
		</h2>
	</div>
	<div class="panel-body">
	   <div class="table-responsive">
		<table class="table table-striped table-hover table-compact">
			<tr>
				<th><?=gettext("Time")?></th>
				<th><?=gettext("Process")?></th>
				<th><?=gettext("PID")?></th>
				<th style="width:100%"><?=gettext("Message")?></th>
			</tr>
<?php
	foreach ($filterlog as $filterent) {
?>
			<tr>
				<td style="white-space:nowrap;">
					<?=htmlspecialchars($filterent['time'])?>
				</td>
				<td style="white-space:nowrap;">
					<?=htmlspecialchars($filterent['process'])?>
				</td>
				<td style="white-space:nowrap;">
					<?=htmlspecialchars($filterent['pid'])?>
				</td>
				<td style="word-wrap:break-word; word-break:break-all; white-space:normal">
					<?=htmlspecialchars($filterent['message'])?>
				</td>
			</tr>
<?php
	} // e-o-foreach
?>
		</table>
<?php
	if (count($filterlog) == 0)
		print_info_box(gettext('No logs to display'));
?>
		</div>
	</div>
</div>
<?php
}
else
{
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Last ")?><?=$nentries?> <?=gettext($allowed_logs[$logfile]["name"])?><?=gettext(" log entries")?></h2></div>
	<div class="table table-responsive">
		<table class="table table-striped table-hover">
			<thead>
				<tr>
					<th><?=gettext("Time")?></th>
					<th style="width:100%"><?=gettext("Message")?></th>
				</tr>
			</thead>
			<tbody>
<?php
	if (($logfile == 'resolver') || ($logfile == 'system'))
		$inverse = array("ppp");
	else
		$inverse = null;

	if ($filtertext)
		$rows = dump_clog($system_logfile, $nentries, true, array("$filtertext"), $inverse);
	else
		$rows = dump_clog($system_logfile, $nentries, true, array(), $inverse);
?>
			</tbody>
		</table>
<?php
	if ($rows == 0)
		print_info_box(gettext('No logs to display'));
?>
	</div>
</div>
<?php
}
?>

<?php
# Manage Log - Section/Form

if ($input_errors) {
	print_input_errors($input_errors);
	$manage_log_active = true;
}

if ($manage_log_active)
	$manage_log_state = SEC_OPEN;
else
	$manage_log_state = SEC_CLOSED;

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
))->displayAsRadio();

$group->add(new Form_Checkbox(
	'cronorder',
	null,
	'Reverse',
	($pconfig['cronorder'] == 'reverse') ? true : false,
	'reverse'
))->displayAsRadio();

$group->add(new Form_Checkbox(
	'cronorder',
	null,
	'General Logging Options Setting',
	($pconfig['cronorder'] == '') ? true : false,
	''
))->displayAsRadio();

$group->setHelp('Show log entries in forward (newest at bottom) or reverse (newest at top) order.');
$section->add($group);

$group = new Form_Group('GUI Log Entries');

# Use the general logging options setting (global) as placeholder.
$group->add(new Form_Input(
	'nentries',
	'GUI Log Entries',
	'number',
	$pconfig['nentries'],
	['placeholder' => $config['syslog']['nentries']]
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
	['placeholder' => $config['syslog']['logfilesize'] ? $config['syslog']['logfilesize'] : "511488"]
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

<?php include("foot.inc"); ?>
