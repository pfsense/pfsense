<?php
/*
 * status_logs.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2019 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-diagnostics-logs-system
##|*NAME=Status: Logs: System
##|*DESCR=Allow access to the 'Status: System Logs: General' page.
##|*MATCH=status_logs.php
##|-PRIV

require_once("status_logs_common.inc");


/*
Build a list of allowed log files so we can reject others to prevent the page
from acting on unauthorized files.
*/
$allowed_logs = array(
	"system" => array("name" => gettext("General"),
		    "shortcut" => ""),
	"dhcpd" => array("name" => gettext("DHCP"),
		    "shortcut" => "dhcp"),
	"portalauth" => array("name" => gettext("Captive Portal Auth"),
		    "shortcut" => "captiveportal"),
	"ipsec" => array("name" => gettext("IPsec"),
		    "shortcut" => "ipsec"),
	"ppp" => array("name" => gettext("PPP"),
		    "shortcut" => ""),
	"openvpn" => array("name" => gettext("OpenVPN"),
		    "shortcut" => "openvpn"),
	"ntpd" => array("name" => gettext("NTP"),
		    "shortcut" => "ntp"),
	"gateways" => array("name" => gettext("Gateways"),
		    "shortcut" => "gateways"),
	"routing" => array("name" => gettext("Routing"),
		    "shortcut" => "routing"),
	"resolver" => array("name" => gettext("DNS Resolver"),
		    "shortcut" => "resolver"),
	"wireless" => array("name" => gettext("Wireless"),
		    "shortcut" => "wireless"),
);

// The logs to display are specified in a REQUEST argument. Default to 'system' logs
if (!$_REQUEST['logfile']) {
	$logfile = 'system';
} else {
	$logfile = $_REQUEST['logfile'];
	if (!array_key_exists($logfile, $allowed_logs)) {
		/* Do not let someone attempt to load an unauthorized log. */
		$logfile = 'system';
	}
}


// Log Filter Submit - System
log_filter_form_system_submit();


// Manage Log Section - Code
manage_log_code();


// Status Logs Common - Code
status_logs_common_code();


if ($filtertext) {
	$filtertextmeta="?filtertext=$filtertext";
}

if (in_array($logfile, array('system', 'gateways', 'routing', 'resolver', 'wireless'))) {
	$pgtitle = array(gettext("Status"), gettext("System Logs"), gettext("System"), $allowed_logs[$logfile]["name"]);
	$pglinks = array("", "status_logs.php", "status_logs.php", "@self");
} else {
	$pgtitle = array(gettext("Status"), gettext("System Logs"), $allowed_logs[$logfile]["name"]);
	$pglinks = array("", "status_logs.php", "@self");
}
include("head.inc");

if ($changes_applied) {
	print_apply_result_box($retval, $extra_save_msg);
	$manage_log_active = false;
}

// Tab Array
tab_array_logs_common();


// Manage Log - Section/Form
if ($system_logs_manage_log_form_hidden) {
	manage_log_section();
}


// Filter Section/Form - System
filter_form_system();


// Now the forms are complete we can draw the log table and its controls
if (!$rawfilter) {
	system_log_filter();
?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">
<?php
	print(system_log_table_panel_title());
?>
		</h2>
	</div>
	<div class="panel-body">
	   <div class="table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr class="text-nowrap">
					<th><?=gettext("Time")?></th>
					<th><?=gettext("Process")?></th>
					<th><?=gettext("PID")?></th>
					<th style="width:100%"><?=gettext("Message")?></th>
				</tr>
			</thead>
			<tbody>
<?php
	foreach ($filterlog as $filterent) {
?>
				<tr class="text-nowrap">
					<td>
						<?=htmlspecialchars($filterent['time'])?>
					</td>
					<td>
						<?=htmlspecialchars($filterent['process'])?>
					</td>
					<td>
						<?=htmlspecialchars($filterent['pid'])?>
					</td>
					<td style="word-wrap:break-word; word-break:break-all; white-space:normal">
						<?=htmlspecialchars($filterent['message'])?>
					</td>
				</tr>
<?php
	} // e-o-foreach
?>
			</tbody>
		</table>
<?php
	if (count($filterlog) == 0) {
		print_info_box(gettext('No logs to display.'));
	}
?>
		</div>
	</div>
</div>
<?php
} else {
?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">
<?php
	print(system_log_table_panel_title());
?>
		</h2>
	</div>
	<div class="table table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr class="text-nowrap">
					<th><?=gettext("Time")?></th>
					<th style="width:100%"><?=gettext("Message")?></th>
				</tr>
			</thead>
			<tbody>
<?php
	if (($logfile == 'resolver') || ($logfile == 'system')) {
		$inverse = array("ppp");
	} else {
		$inverse = null;
	}

	system_log_filter();
?>
			</tbody>
		</table>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	$("#count").html(<?=$rows?>);
});
//]]>
</script>

<?php
	if ($rows == 0) {
		print_info_box(gettext('No logs to display.'));
	}
?>
	</div>
</div>
<?php
}

# Manage Log - Section/Form
if (!$system_logs_manage_log_form_hidden) {
	manage_log_section();
}
?>

<?php include("foot.inc"); ?>
