<?php
/*
 * status_logs_vpn.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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
	"vpn" => array("name" => gettext("VPN Logins"),
		    "shortcut" => "poes"),
	"poes" => array("name" => gettext("PPPoE Service"),
		    "shortcut" => "pppoes"),
	"l2tps" => array("name" => gettext("L2TP Service"),
		    "shortcut" => "l2tps"),
);

// The logs to display are specified in a REQUEST argument. Default to 'system' logs
if (!$_REQUEST['logfile']) {
	$logfile = 'vpn';
	$vpntype = "poes";
} else {
	$logfile = $_REQUEST['logfile'];
	$vpntype = $_REQUEST['vpntype'];
	if (!array_key_exists($logfile, $allowed_logs)) {
		/* Do not let someone attempt to load an unauthorized log. */
		$logfile = 'vpn';
		$vpntype = "poes";
	}
}

if ($vpntype == 'poes') { $allowed_logs['vpn']['name'] = gettext("PPPoE Logins"); }
if ($vpntype == 'l2tp') { $allowed_logs['vpn']['name'] = gettext("L2TP Logins"); }


// Log Filter Submit - VPN
log_filter_form_vpn_submit();


// Manage Log Section - Code
manage_log_code();


// Status Logs Common - Code
status_logs_common_code();


if ($filtertext) {
	$filtertextmeta="?filtertext=$filtertext";
}

$pgtitle = array(gettext("Status"), gettext("System Logs"), gettext("VPN"), gettext($allowed_logs[$logfile]["name"]));
$pglinks = array("", "status_logs.php", "status_logs_vpn.php", "@self");
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


// Filter Section/Form - VPN
filter_form_vpn();


// Now the forms are complete we can draw the log table and its controls
if (!$rawfilter) {
	system_log_filter();

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
	print(system_log_table_panel_title());
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
							<i class="fa fa-sign-in" title="User Logged In"></i>
							<?php } else if ($filterent['action'] == "logout") { ?>
							<i class="fa fa-sign-out" title="User Logged Out"></i>
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
	<div class="panel-body">
		<pre><?php
			$rows = dump_log($logfile_path, $nentries, true, array($filtertext), null, 'notable');
		?></pre>

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

// Filter Section/Form - VPN
function filter_form_vpn() {

	global $filter_active, $rawfilter, $filterfieldsarray, $filtertext, $filterlogentries_qty, $nentries, $Include_Act, $interfacefilter;
	global $logfile;
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
			gettext('Apply Filter'),
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
			gettext('Apply Filter'),
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
