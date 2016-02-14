<?php
/*
	status_logs.php
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
	"relayd" => array("name" => gettext("Load Balancer"),
		    "shortcut" => "relayd"),
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
} else {
	$pgtitle = array(gettext("Status"), gettext("System Logs"), $allowed_logs[$logfile]["name"]);
}
include("head.inc");

if (!$input_errors && $savemsg) {
	print_info_box($savemsg, 'success');
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
