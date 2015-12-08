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
	$filtertext = getGETPOSTsettingvalue('filtertext', "");
	$filterlogentries_qty = getGETPOSTsettingvalue('filterlogentries_qty', null);
}

$filterlogentries_submit = getGETPOSTsettingvalue('filterlogentries_submit', null);

if ($filterlogentries_submit) {
	$filterfieldsarray = array();

	$filterfieldsarray['time'] = getGETPOSTsettingvalue('filterlogentries_time', null);
	$filterfieldsarray['process'] = getGETPOSTsettingvalue('filterlogentries_process', null);
	$filterfieldsarray['pid'] = getGETPOSTsettingvalue('filterlogentries_pid', null);
	$filterfieldsarray['message'] = getGETPOSTsettingvalue('filterlogentries_message', null);
	$filterlogentries_qty = getGETPOSTsettingvalue('filterlogentries_qty', null);
}

$system_logfile = "{$g['varlog_path']}/" . basename($logfile) . ".log";

$nentries = $config['syslog']['nentries'];

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

if (!isset($config['syslog']['rawfilter'])) { // Advanced log filter form
	$form = new Form(false);

	$section = new Form_Section(gettext('Advanced Log Filter'), 'adv-filter-panel', true);

	$group = new Form_Group('');

	$group->add(new Form_Input(
		'filterlogentries_time',
		null,
		'text',
		$filterfieldsarray['time']
	))->setHelp(gettext('Time'));

	$group->add(new Form_Input(
		'filterlogentries_process',
		null,
		'text',
		$filterfieldsarray['process']
	))->setHelp(gettext('Process'));

	$group->add(new Form_Input(
		'filterlogentries_pid',
		null,
		'text',
		$filterfieldsarray['pid']
	))->setHelp(gettext('PID'));

	$group->add(new Form_Input(
		'filterlogentries_qty',
		null,
		'number',
		$filterlogentries_qty,
		['placeholder' => $nentries]
	))->setHelp(gettext('Quantity'));

	$section->add($group);

	$group = new Form_Group('');

	$group->add(new Form_Input(
		'filterlogentries_message',
		null,
		'text',
		$filterfieldsarray['message']
	))->setHelp(gettext('Log Message'));

	$btnsubmit = new Form_Button(
		'filterlogentries_submit',
		' ' . gettext('Apply Filter'),
		null,
		'fa-filter'
	);
}
else { // Simple log filter form
	$form = new Form(false);

	$section = new Form_Section(gettext('Log Filter'), 'basic-filter-panel', true);

	$group = new Form_Group('');

	$group->add(new Form_Input(
		'filtertext',
		null,
		'text',
		$filtertext
	))->setHelp(gettext('Filter Expression'));

	$group->add(new Form_Input(
		'filterlogentries_qty',
		null,
		'number',
		$filterlogentries_qty,
		['placeholder' => $nentries]
	))->setHelp(gettext('Quantity'));

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
if (!isset($config['syslog']['rawfilter'])) {
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
		printf(gettext("Last %d %s log entries."), count($filterlog), $logfile);
	else
		printf(gettext("%d matched %s log entries."), count($filterlog), $logfile);

	printf(gettext(" (Maximum %d)"), $nentries);
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
				<th style="width:100%"><?=gettext("Log Message")?></th>
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
		</div>
	</div>
</div>
<?php
	if (count($filterlog) == 0)
		print_info_box(gettext('No logs to display'));
}
else
{
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Last ")?><?=$nentries?> <?=$logfile?><?=gettext(" log entries")?></h2></div>
	<div class="table table-responsive">
		<table class="table table-striped table-hover">
			<thead>
				<tr>
					<th class="col-sm-2"></th>
					<th></th>
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
	</div>
</div>
<?php
	if ($rows == 0)
		print_info_box(gettext('No logs to display'));
}
?>

<?php
$form = new Form(false);

$section = new Form_Section(gettext('Manage Log'), 'log-manager-panel', true);

$group = new Form_Group('');

$btnclear = new Form_Button(
	'clear',
	' ' . gettext('Clear log'),
	null,
	'fa-trash'
);

$btnclear->removeClass('btn-primary')->addClass('btn-danger')->addClass('btn-sm');

if ($logfile == 'dhcpd')
	print_info_box(gettext('Warning: Clearing the log file will restart the DHCP daemon.'));

$group->add(new Form_StaticText(
	'',
	$btnclear
));

$section->add($group);
$form->add($section);
print $form;
?>

<?php include("foot.inc"); ?>
