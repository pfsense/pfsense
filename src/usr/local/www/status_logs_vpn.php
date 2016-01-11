<?php
/*
	status_logs_vpn.php
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
##|*IDENT=page-diagnostics-logs-pptpvpn
##|*NAME=Status: Logs: VPN
##|*DESCR=Allow access to the 'Status: Logs: VPN' page.
##|*MATCH=status_logs_vpn.php*
##|-PRIV


require_once("status_logs_common.inc");
require_once("vpn.inc");

/*
Build a list of allowed log files so we can reject others to prevent the page
from acting on unauthorized files.
*/
$allowed_logs = array(
	"vpn" => array("name" => "VPN Logins",
		    "shortcut" => "poes"),
	"poes" => array("name" => "PPPoE Service",
		    "shortcut" => "poes"),
	"l2tps" => array("name" => "L2TP Service",
		    "shortcut" => "l2tps"),
);

// The logs to display are specified in a GET argument. Default to 'system' logs
if (!$_GET['logfile']) {
	$logfile = 'vpn';
	$vpntype = "poes";
} else {
	$logfile = $_GET['logfile'];
	$vpntype = $_GET['vpntype'];
	if (!array_key_exists($logfile, $allowed_logs)) {
		/* Do not let someone attempt to load an unauthorized log. */
		$logfile = 'vpn';
		$vpntype = "poes";
	}
}

if ($vpntype == 'poes') { $allowed_logs['vpn']['name'] = "PPPoE Logins"; }
if ($vpntype == 'l2tp') { $allowed_logs['vpn']['name'] = "L2TP Logins"; }


// Log Filter Submit - VPN
log_filter_form_vpn_submit();


// Manage Log Section - Code
manage_log_code();


// Status Logs Common - Code
status_logs_common_code();


if ($filtertext) {
	$filtertextmeta="?filtertext=$filtertext";
}

$pgtitle = array(gettext("Status"), gettext("System logs"), gettext($allowed_logs[$logfile]["name"]));
include("head.inc");

if (!$input_errors && $savemsg) {
	print_info_box($savemsg, 'success');
	$manage_log_active = false;
}


// Tab Array
tab_array_logs_common();


// Filter Section/Form - VPN
filter_form_vpn();


// Now the forms are complete we can draw the log table and its controls
if (!$rawfilter) {
	if ($filterlogentries_submit) {
		$filterlog = conv_log_filter($logfile_path, $nentries, $nentries + 100, $filterfieldsarray);
	} else {
		$filterlog = conv_log_filter($logfile_path, $nentries, $nentries + 100, $filtertext);
	}

	// Remove those not of the selected vpn type (poes / l2tp).
	if ($logfile == "vpn") {
		foreach ($filterlog as $key => $filterent) {
			if (!preg_match('/' . $vpntype . '/', $filterent['type'])) {
				unset($filterlog[$key]);
			}
		}
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
<?php
	if ($logfile == "vpn") {
?>
				<thead>
					<tr class="text-nowrap">
						<th><?=gettext("Time")?></th>
						<th><?=gettext("Action")?></th>
						<th><?=gettext("User")?></th>
						<th><?=gettext("IP Address")?></th>
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
						<td style="word-wrap:break-word; word-break:break-all; white-space:normal">
							<?php if ($filterent['action'] == "login") { ?>
							<i class="fa fa-arrow-left" title="in"></i>
							<?php } else if ($filterent['action'] == "logout") { ?>
							<i class="fa fa-arrow-right" title="out"></i>
							<?php } else { ?>
							<i><?=htmlspecialchars($filterent['action'])?></i>
							<?php } ?>
						</td>
						<td>
							<?=htmlspecialchars($filterent['user'])?>
						</td>
						<td>
							<?=htmlspecialchars($filterent['ip_address'])?>
						</td>
					</tr>
<?php
		} // e-o-foreach
?>
				</tbody>
<?php
	} else {
?>
				<thead>
					<tr class="text-nowrap">
						<th><?=gettext("Time")?></th>
						<th><?=gettext("Type")?></th>
						<th><?=gettext("PID")?></th>
						<th style="width:100%"><?=gettext("Log Message")?></th>
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
							<?=htmlspecialchars($filterent['type'])?>
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
<?php
	}
?>
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
	<div class="panel-body">
		<pre><?php 
			$rows = dump_clog_no_table($logfile_path, $nentries, true, array($filtertext));
		?></pre>
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

<?php
# Manage Log - Section/Form
manage_log_section();
?>

<?php
function dump_clog_vpn($logfile, $tail) {
	global $g, $config, $vpntype;

	$sor = isset($config['syslog']['reverse']) ? "-r" : "";
	$specific_log = basename($logfile, '.log') . '_settings';
	if ($config['syslog'][$specific_log]['cronorder'] == 'forward') $sor = "";
	if ($config['syslog'][$specific_log]['cronorder'] == 'reverse') $sor = "-r";

	$logarr = "";

	exec("/usr/local/sbin/clog " . escapeshellarg($logfile) . " | tail {$sor} -n " . $tail, $logarr);

	$rows = 0;
	foreach ($logarr as $logent) {
		$rows++;
		$logent = preg_split("/\s+/", $logent, 6);
		$llent = explode(",", $logent[5]);
		$iftype = substr($llent[1], 0, 4);
		if ($iftype != $vpntype) {
			continue;
		}
		echo "<tr>\n";
		echo "<td>" . htmlspecialchars(join(" ", array_slice($logent, 0, 3))) . "</td>\n";

		if ($llent[0] == "login") {
			echo "<td><i class=\"fa fa-arrow-left\" alt=\"in\"></i></td>\n";
		} else {
			echo "<td><i class=\"fa fa-arrow-right\" alt=\"out\"></i></td>\n";
		}

		echo "<td>" . htmlspecialchars($llent[3]) . "</td>\n";
		echo "<td>" . htmlspecialchars($llent[2]) . "&nbsp;</td>\n";
		echo "</tr>\n";
	}
	return($rows);
}
?>

<?php
// Log Filter Submit - VPN
function log_filter_form_vpn_submit() {

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
		$filterfieldsarray['type'] = getGETPOSTsettingvalue('filterlogentries_type', null);
		$filterfieldsarray['pid'] = getGETPOSTsettingvalue('filterlogentries_pid', null);
		$filterfieldsarray['message'] = getGETPOSTsettingvalue('filterlogentries_message', null);
		$filterfieldsarray['action'] = getGETPOSTsettingvalue('filterlogentries_action', null);
		$filterfieldsarray['user'] = getGETPOSTsettingvalue('filterlogentries_user', null);
		$filterfieldsarray['ip_address'] = getGETPOSTsettingvalue('filterlogentries_ip_address', null);
		$filterlogentries_qty = getGETPOSTsettingvalue('filterlogentries_qty', null);
	}
}
?>

<?php
// Filter Section/Form - VPN
function filter_form_vpn() {

	global $filter_active, $rawfilter, $filterfieldsarray, $filtertext, $filterlogentries_qty, $nentries, $Include_Act, $interfacefilter;
	global $logfile;

	if ($filter_active) {
		$filter_state = SEC_OPEN;
	} else {
		$filter_state = SEC_CLOSED;
	}

	if (!$rawfilter) { // Advanced log filter form
		$form = new Form(false);

		$section = new Form_Section('Advanced Log Filter', 'adv-filter-panel', COLLAPSIBLE|$filter_state);

		if ($logfile == "vpn") {
			$group = new Form_Group('');

			$group->add(new Form_Input(
				'filterlogentries_time',
				null,
				'text',
				$filterfieldsarray['time']
			))->setWidth(3)->setHelp('Time');

			$group->add(new Form_Input(
				'filterlogentries_action',
				null,
				'text',
				$filterfieldsarray['action']
			))->setWidth(3)->setHelp('Action');

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
				'filterlogentries_user',
				null,
				'text',
				$filterfieldsarray['user']
			))->setWidth(3)->setHelp('User');

			$group->add(new Form_Input(
				'filterlogentries_ip_address',
				null,
				'text',
				$filterfieldsarray['ip_address']
			))->setWidth(4)->setHelp('IP Address');
		} else {
			$group = new Form_Group('');

			$group->add(new Form_Input(
				'filterlogentries_time',
				null,
				'text',
				$filterfieldsarray['time']
			))->setWidth(3)->setHelp('Time');

			$group->add(new Form_Input(
				'filterlogentries_type',
				null,
				'text',
				$filterfieldsarray['type']
			))->setWidth(2)->setHelp('Type');

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
			))->setWidth(7)->setHelp('Log Message');

		}
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
}
?>

<?php include("foot.inc"); ?>
