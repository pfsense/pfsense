<?php
/*
 * firewall_rules.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-firewall-rules
##|*NAME=Firewall: Rules
##|*DESCR=Allow access to the 'Firewall: Rules' page.
##|*MATCH=firewall_rules.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("ipsec.inc");
require_once("shaper.inc");

$XmoveTitle = gettext("Move checked rules above this one. Shift+Click to move checked rules below.");
$ShXmoveTitle = gettext("Move checked rules below this one. Release shift to move checked rules above.");

$shortcut_section = "firewall";

$filter_srcdsttype_flags = [
	SPECIALNET_ANY, SPECIALNET_COMPAT_ADDRAL, SPECIALNET_NET, SPECIALNET_SELF,
	SPECIALNET_CLIENTS, SPECIALNET_IFADDR, SPECIALNET_IFNET, SPECIALNET_GROUP
];

function get_pf_rules($rules, $tracker_start, $tracker_end) {

	if ($rules == NULL || !is_array($rules)) {
		return (NULL);
	}

	$arr = array();
	foreach ($rules as $key => $rule) {
		/* key 'error' indicates a call to pfctl_get_rule() returned
		 * nonzero. We may have a partial list of rules if that is the case */
		if ($key != 'error' &&
			$rule['tracker'] >= $tracker_start &&
		    $rule['tracker'] <= $tracker_end) {
			$arr[] = $rule;
		}
	}

	if (count($arr) == 0)
		return (NULL);

	return ($arr);
}

function print_states($tracker_start, $tracker_end = -1) {
	global $rulescnt;

	if (empty($tracker_start)) {
		return;
	}

	if ($tracker_end === -1) {
		$tracker_end = $tracker_start;
	} elseif ($tracker_end < $tracker_start) {
		return;
	}

	$rulesid = "";
	$bytes = 0;
	$states = 0;
	$packets = 0;
	$evaluations = 0;
	$stcreations = 0;
	$rules = get_pf_rules($rulescnt, $tracker_start, $tracker_end);
	if (is_array($rules)) {
		foreach ($rules as $rule) {
			$evaluations += $rule['evaluations'];
			$packets += $rule['packets'];
			$bytes += $rule['bytes'];
			$states += $rule['states'];
			$stcreations += $rule['state creations'];
			if (strlen($rulesid) > 0) {
				$rulesid .= ",";
			}
			$rulesid .= "{$rule['id']}";
		}
	}

	$trackertext = "Tracking ID: {$tracker_start}";
	if ($tracker_end != $tracker_start) {
		$trackertext .= '-' . $tracker_end;
	}
	$trackertext .= "<br />";

	$title = (gettext('States details'));
	$href = ('diag_dump_states.php?ruleid=' . $rulesid);
	printf("<a href=\"%s\" " .
	    "data-toggle=\"popover\" data-trigger=\"hover focus\" " .
	    "title=\"%s\" ", $href, $title);
	printf("data-content=\"{$trackertext}evaluations: %s<br />packets: " .
	    "%s<br />bytes: %s<br />", format_number($evaluations), format_number($packets),
	    format_bytes($bytes));
	printf("states: %s<br />state creations: %s", format_number($states),
	    format_number($stcreations));
	printf("\" data-html=\"true\" usepost>");
	printf("%s/%s</a><br />", format_number($states), format_bytes($bytes));
}

function delete_nat_association($id) {
	$a_nat = config_get_path('nat/rule');
	if (!$id || !is_array($a_nat)) {
		return;
	}

	foreach ($a_nat as &$natent) {
		if ($natent['associated-rule-id'] == $id) {
			$natent['associated-rule-id'] = '';
		}
	}
	config_set_path('nat/rule', $a_nat);
}

config_init_path('filter/rule');
filter_rules_sort();

if ($_REQUEST['if']) {
	$if = $_REQUEST['if'];
}

$ifdescs = get_configured_interface_with_descr();

$iflist = filter_get_interface_list();

if (!$if || !isset($iflist[$if])) {
	if ($if != 'any' &&
	    $if != 'EthernetRules' &&
	    $if != 'FloatingRules' &&
	    !config_path_enabled('system/webgui', 'requirefirewallinterface')) {
		/* default to the first configured interface */
		$if = array_key_first($ifdescs);
	}
}

if ($_POST['apply']) {
	$retval = 0;
	$retval |= filter_configure();

	clear_subsystem_dirty('filter');
}

if ($_POST['act'] == "del") {
	if (config_get_path("filter/rule/{$_POST['id']}")) {
		// separators must be updated before the rule is removed
		$ridx = get_interface_ruleindex($if, $_POST['id']);
		$a_separators = config_get_path('filter/separator/' . strtolower($if), []);
		shift_separators($a_separators, $ridx['index'], true);
		config_set_path('filter/separator/' . strtolower($if), $a_separators);

		// remove the rule
		if (!empty(config_get_path("filter/rule/{$_POST['id']}/associated-rule-id"))) {
			delete_nat_association(config_get_path("filter/rule/{$_POST['id']}/associated-rule-id"));
		}
		config_del_path("filter/rule/{$_POST['id']}");

		if (write_config(gettext("Firewall: Rules - deleted a firewall rule."))) {
			mark_subsystem_dirty('filter');
		}

		header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
		exit;
	}
}

if (($_POST['act'] == 'killid') &&
    (!empty($_POST['tracker'])) &&
    (!empty($if))) {
	mwexec("/sbin/pfctl -k label -k " . escapeshellarg("id:{$_POST['tracker']}"));
	header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
	exit;
}

// Handle save msg if defined
if ($_REQUEST['savemsg']) {
	$savemsg = htmlentities($_REQUEST['savemsg']);
}

if (isset($_POST['del_x'])) {
	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		$removed = false;
		$a_separators = config_get_path('filter/separator/' . strtolower($if), []);
		foreach ($_POST['rule'] as $rulei) {
			// separators must be updated before the rule is removed
			$ridx = get_interface_ruleindex($if, $rulei);
			shift_separators($a_separators, $ridx['index'], true);

			// remove the rule
			delete_nat_association(config_get_path("filter/rule/{$rulei}/associated-rule-id"));
			config_del_path("filter/rule/{$rulei}");
			$removed = true;
		}
		config_set_path('filter/separator/' . strtolower($if), $a_separators);

		if ($removed) {
			if (write_config(gettext("Firewall: Rules - deleted selected firewall rules."))) {
				mark_subsystem_dirty('filter');
			}
		}

		header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
		exit;
	}
} elseif (isset($_POST['toggle_x'])) {
	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		foreach ($_POST['rule'] as $rulei) {
			if (config_path_enabled("filter/rule/{$rulei}", 'disabled')) {
				config_del_path("filter/rule/{$rulei}/disabled");
			} else {
				config_set_path("filter/rule/{$rulei}/disabled", true);
			}
		}
		if (write_config(gettext("Firewall: Rules - toggle selected firewall rules."))) {
			mark_subsystem_dirty('filter');
		}

		header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
		exit;
	}
} else if ($_POST['act'] == "toggle") {
	if (config_get_path("filter/rule/{$_POST['id']}")) {
		if (config_path_enabled("filter/rule/{$_POST['id']}", 'disabled')) {
			config_del_path("filter/rule/{$_POST['id']}/disabled");
			$wc_msg = gettext('Firewall: Rules - enabled a firewall rule.');
		} else {
			config_set_path("filter/rule/{$_POST['id']}/disabled", true);
			$wc_msg = gettext('Firewall: Rules - disabled a firewall rule.');
		}
		if (write_config($wc_msg)) {
			mark_subsystem_dirty('filter');
		}

		header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
		exit;
	}
} else if ($_POST['order-store']) {
	$updated = false;
	$dirty = false;
	/* update rule order, POST[rule] is an array of ordered IDs */
	if (is_array($_POST['rule']) && !empty($_POST['rule'])) {
		$a_filter_new = array();

		// Include the rules of other interfaces listed in config before this (the selected) interface.
		$filteri_before = null;
		foreach (config_get_path('filter/rule', []) as $idx => $filterent) {
			if (($filterent['interface'] == $if && !isset($filterent['floating'])) || (isset($filterent['floating']) && "FloatingRules" == $if)) {
				$filteri_before = $idx;
				break;
			} else {
				$a_filter_new[] = $filterent;
			}
		}

		// Include the rules of this (the selected) interface.
		// If a rule is not in POST[rule], it has been deleted by the user
		foreach ($_POST['rule'] as $id) {
			$a_filter_new[] = config_get_path("filter/rule/{$id}");
		}

		// Include the rules of other interfaces listed in config after this (the selected) interface.
		foreach (config_get_path('filter/rule', []) as $filteri_after => $filterent) {
			if ($filteri_before > $filteri_after) {
				continue;
			}
			if (($filterent['interface'] == $if && !isset($filterent['floating'])) || (isset($filterent['floating']) && "FloatingRules" == $if)) {
				continue;
			} else {
				$a_filter_new[] = $filterent;
			}
		}

		if (config_get_path('filter/rule') !== $a_filter_new) {
			config_set_path('filter/rule', $a_filter_new);
			$dirty = true;
		}
	}

	$a_separators = config_get_path('filter/separator/' . strtolower($if), []);

	/* update separator order, POST[separator] is an array of ordered IDs */
	if (is_array($_POST['separator']) && !empty($_POST['separator'])) {
		$new_separator = array();
		$idx = 0;

		foreach ($_POST['separator'] as $separator) {
			$new_separator['sep' . $idx++] = $separator;
		}

		if ($a_separators !== $new_separator) {
			$a_separators = $new_separator;
			$updated = true;
		}
	} else if (!empty($a_separators)) {
		$a_separators = "";
		$updated = true;
	}

	if ($updated || $dirty) {
		config_set_path('filter/separator/' . strtolower($if), $a_separators);
		if (write_config(gettext("Firewall: Rules - reordered firewall rules."))) {
			if ($dirty) {
				mark_subsystem_dirty('filter');
			}
		}
	}

	header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
	exit;
} elseif (isset($_POST['dstif']) && !empty($_POST['dstif']) &&
    isset($iflist[$_POST['dstif']]) && have_ruleint_access($_POST['dstif']) &&
    is_array($_POST['rule']) && count($_POST['rule'])) {
	$confiflist = get_configured_interface_list();
	/* Use this as a starting point and increase as we go, otherwise if the
	 * loop runs fast there can be duplicates.
	 * https://redmine.pfsense.org/issues/13507 */
	$tracker = (int)microtime(true);
	foreach ($_POST['rule'] as $rulei) {
		$filterent = config_get_path("filter/rule/{$rulei}");
		$filterent['tracker'] = $tracker++;
		$filterent['interface'] = $_POST['dstif'];
		if (($_POST['convertif'] == 'true') && ($if != $_POST['dstif']) &&
		    in_array($_POST['dstif'], $confiflist)) {
			if (isset($filterent['source']['network']) &&
			    ($filterent['source']['network'] == $if)) {
				$filterent['source']['network'] = $_POST['dstif'];
			}
			if (isset($filterent['destination']['network']) &&
			    ($filterent['destination']['network'] == $if)) {
				$filterent['destination']['network'] = $_POST['dstif'];
			}
			if (isset($filterent['source']['network']) &&
			    ($filterent['source']['network'] == ($if . 'ip'))) {
				$filterent['source']['network'] = $_POST['dstif'] . 'ip';
			}
			if (isset($filterent['destination']['network']) &&
			    ($filterent['destination']['network'] == ($if . 'ip'))) {
				$filterent['destination']['network'] = $_POST['dstif'] . 'ip';
			}
		}
		config_set_path('filter/rule/', $filterent);
	}
	if (write_config(gettext("Firewall: Rules - copying selected firewall rules."))) {
		mark_subsystem_dirty('filter');
	}

	header("Location: firewall_rules.php?if=" . htmlspecialchars($_POST['dstif']));
	exit;
}

$tab_array[] = [gettext('Floating'), ($if === 'FloatingRules'), 'firewall_rules.php?if=FloatingRules'];

foreach ($iflist as $ifent => $ifname) {
	$tab_array[] = array($ifname, ($ifent == $if), "firewall_rules.php?if={$ifent}");
}

foreach ($tab_array as $dtab) {
	if ($dtab[1]) {
		$bctab = $dtab[0];
		break;
	}
}

$pgtitle = array(gettext("Firewall"), gettext("Rules"), $bctab);
$pglinks = array("", "firewall_rules.php", "@self");
$shortcut_section = "firewall";

include("head.inc");
$nrules = 0;

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if ($_POST['apply']) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('filter')) {
	print_apply_box(gettext("The firewall rule configuration has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
}

display_top_tabs($tab_array, false, 'pills');

$showantilockout = false;
$showprivate = false;
$showblockbogons = false;

if (!config_path_enabled('system/webgui', 'noantilockout') &&
    (((count(config_get_path('interfaces', [])) > 1) && ($if == 'lan')) ||
    ((count(config_get_path('interfaces', [])) == 1) && ($if == 'wan')))) {
	$showantilockout = true;
}

if (config_path_enabled("interfaces/{$if}", "blockpriv")) {
	$showprivate = true;
}

if (config_path_enabled("interfaces/{$if}", "blockbogons")) {
	$showblockbogons = true;
}

if (config_path_enabled('system/webgui', 'roworderdragging')) {
	$rules_header_text = gettext('Rules');
} else {
	$rules_header_text = gettext('Rules (Drag to Change Order)');
}

/* Load the counter data of each pf rule. */
$rulescnt = pfSense_get_pf_rules();

// Update this if you add or remove columns!
$columns_in_table = 13;

/* Floating rules tab has one extra column
 * https://redmine.pfsense.org/issues/10667 */
if ($if === 'FloatingRules') {
	$columns_in_table++;
}

// Only show rules table if interface is set.
if (isset($if)):

?>
<!-- Allow table to scroll when dragging outside of the display window -->
<style>
.table-responsive {
    clear: both;
    overflow-x: visible;
    margin-bottom: 0px;
}
</style>

<form id="mainform" method="post">
	<input name="if" id="if" type="hidden" value="<?=$if?>" />
	<input name="dstif" id="dstif" type="hidden" value="" />
	<input name="convertif" id="convertif" type="hidden" value="" />
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=$rules_header_text?></h2></div>
		<div id="mainarea" class="table-responsive panel-body">
			<table id="ruletable" class="table table-hover table-striped table-condensed" style="overflow-x: 'visible'">
				<thead>
					<tr>
						<th><input type="checkbox" id="selectAll" name="selectAll" /></th>
						<th><!-- status icons --></th>
						<th><?=gettext('States')?></th>
<?php if ($if === 'FloatingRules'): ?>
						<th><?=gettext('Interfaces')?></th>
<?php endif; ?>
						<th><?=gettext('Protocol')?></th>
						<th><?=gettext('Source')?></th>
						<th><?=gettext('Port')?></th>
						<th><?=gettext('Destination')?></th>
						<th><?=gettext('Port')?></th>
						<th><?=gettext('Gateway')?></th>
						<th><?=gettext('Queue')?></th>
						<th><?=gettext('Schedule')?></th>
						<th><?=gettext('Description')?></th>
						<th><?=gettext('Actions')?></th>
					</tr>
				</thead>

<?php if ($showblockbogons || $showantilockout || $showprivate) :
?>
				<tbody>
<?php
		// Show the anti-lockout rule if it's enabled, and we are on LAN with an if count > 1, or WAN with an if count of 1.
		if ($showantilockout):
			$alports = implode('<br />', filter_get_antilockout_ports(true));
?>
					<tr id="antilockout">
						<td></td>
						<td title="<?=gettext("traffic is passed")?>"><i class="fa-solid fa-check text-success"></i></td>
						<td><?php print_states(intval(ANTILOCKOUT_TRACKER_START), intval(ANTILOCKOUT_TRACKER_END)); ?></td>
						<td>*</td>
						<td>*</td>
						<td>*</td>
						<td><?=$iflist[$if];?> Address</td>
						<td><?=$alports?></td>
						<td>*</td>
						<td>*</td>
						<td></td>
						<td><?=gettext("Anti-Lockout Rule");?></td>
						<td>
							<a href="system_advanced_admin.php" title="<?=gettext("Settings");?>"><i class="fa-solid fa-cog"></i></a>
						</td>
					</tr>
<?php 	endif;?>
<?php 	if ($showprivate): ?>
					<tr id="private">
						<td></td>
						<td title="<?=gettext("traffic is blocked")?>"><i class="fa-solid fa-times text-danger"></i></td>
						<td><?php print_states(intval(RFC1918_TRACKER_START), intval(RFC1918_TRACKER_END)); ?></td>
						<td>*</td>
						<td><?=gettext("RFC 1918 networks");?></td>
						<td>*</td>
						<td>*</td>
						<td>*</td>
						<td>*</td>
						<td>*</td>
						<td></td>
						<td><?=gettext("Block private networks");?></td>
						<td>
							<a href="interfaces.php?if=<?=htmlspecialchars($if)?>" title="<?=gettext("Settings");?>" usepost><i class="fa-solid fa-cog"></i></a>
						</td>
					</tr>
<?php 	endif;?>
<?php 	if ($showblockbogons): ?>
					<tr id="bogons">
						<td></td>
						<td title="<?=gettext("traffic is blocked")?>"><i class="fa-solid fa-times text-danger"></i></td>
						<td><?php print_states(intval(BOGONS_TRACKER_START), intval(BOGONS_TRACKER_END)); ?></td>
						<td>*</td>
						<td><?=sprintf(gettext("Reserved%sNot assigned by IANA"), "<br />");?></td>
						<td>*</td>
						<td>*</td>
						<td>*</td>
						<td>*</td>
						<td>*</td>
						<td></td>
						<td><?=gettext("Block bogon networks");?></td>
						<td>
							<a href="interfaces.php?if=<?=htmlspecialchars($if)?>" title="<?=gettext("Settings");?>" usepost><i class="fa-solid fa-cog"></i></a>
						</td>
					</tr>
<?php 	endif;?>
			</tbody>
<?php endif;?>
			<tbody class="user-entries">
<?php
$nrules = 0;
$separators = config_get_path('filter/separator/'.strtolower($if));

// Get a list of separator rows and use it to call the display separator function only for rows which there are separator(s).
// More efficient than looping through the list of separators on every row.
$seprows = separator_rows($separators);

/* Cache gateway status for this page load.
 * See https://redmine.pfsense.org/issues/12174 */
$gateways_status = return_gateways_status(true);

global $user_settings;
$show_system_alias_popup = (array_key_exists('webgui', $user_settings) && !$user_settings['webgui']['disablealiaspopupdetail']);
$system_alias_specialnet = get_specialnet('', [SPECIALNET_IFNET, SPECIALNET_GROUP]);
config_init_path('schedules/schedule');
$a_schedules = config_get_path('schedules/schedule');
$if_config = config_get_path('interfaces');
foreach (config_get_path('filter/rule', []) as $filteri => $filterent):

	if (($filterent['interface'] == $if && !isset($filterent['floating'])) ||
	    (isset($filterent['floating']) && $if === 'FloatingRules')) {

		// Display separator(s) for section beginning at rule n
		if ($seprows[$nrules]) {
			display_separator($separators, $nrules, $columns_in_table);
		}
?>
					<tr id="fr<?=$nrules;?>" onClick="fr_toggle(<?=$nrules;?>)" ondblclick="document.location='firewall_rules_edit.php?id=<?=$filteri;?>';" <?=(isset($filterent['disabled']) ? ' class="disabled"' : '')?>>
						<td>
							<input type="checkbox" id="frc<?=$nrules;?>" onClick="fr_toggle(<?=$nrules;?>)" name="rule[]" value="<?=$filteri;?>"/>
						</td>

	<?php
		if ($filterent['type'] == "block") {
			$iconfn = "fa-solid fa-times text-danger";
			$title_text = gettext("traffic is blocked");
		} else if ($filterent['type'] == "reject") {
			$iconfn = "fa-regular fa-hand text-warning";
			$title_text = gettext("traffic is rejected");
		} else if ($filterent['type'] == "match") {
			$iconfn = "fa-solid fa-filter";
			$title_text = gettext("traffic is matched");
		} else {
			$iconfn = "fa-solid fa-check text-success";
			$title_text = gettext("traffic is passed");
		}
	?>
						<td title="<?=$title_text?>">
							<a href="?if=<?=htmlspecialchars($if);?>&amp;act=toggle&amp;id=<?=$filteri;?>" usepost>
								<i class="<?=$iconfn?>" title="<?=gettext("click to toggle enabled/disabled status");?>"></i>
							</a>
	<?php
		if ($filterent['quick'] == 'yes') {
			print '<i class="fa-solid fa-forward text-success" title="'. gettext("&quot;Quick&quot; rule. Applied immediately on match.") .'" style="cursor: pointer;"></i>';
		}

		$isadvset = firewall_check_for_advanced_options($filterent);
		if ($isadvset) {
			print '<i class="fa-solid fa-cog" title="'. gettext("advanced setting") .': '. $isadvset .'" style="cursor: pointer;"></i>';
		}

		if (isset($filterent['log'])) {
			print '<i class="fa-solid fa-tasks" title="'. gettext("traffic is logged") .'" style="cursor: pointer;"></i>';
		}

		if (isset($filterent['direction']) &&
		    ($if == "FloatingRules")) {
			if ($filterent['direction'] == 'in') {
				print '<i class="fa-regular fa-circle-left" title="'. gettext("direction is in") .'" style="cursor: pointer;"></i>';
			} elseif ($filterent['direction'] == 'out') {
				print '<i class="fa-regular fa-circle-right" title="'. gettext("direction is out") .'" style="cursor: pointer;"></i>';
			}
		}
	?>
						</td>
	<?php
		$alias = rule_columns_with_alias(
			$filterent['source']['address'],
			pprint_port($filterent['source']['port']),
			$filterent['destination']['address'],
			pprint_port($filterent['destination']['port'])
		);

		//build Schedule popup box
		$schedule_span_begin = "";
		$schedule_span_end = "";
		$sched_caption_escaped = "";
		$sched_content = "";
		$schedstatus = false;
		$dayArray = array (gettext('Mon'), gettext('Tues'), gettext('Wed'), gettext('Thur'), gettext('Fri'), gettext('Sat'), gettext('Sun'));
		$monthArray = array (gettext('January'), gettext('February'), gettext('March'), gettext('April'), gettext('May'), gettext('June'), gettext('July'), gettext('August'), gettext('September'), gettext('October'), gettext('November'), gettext('December'));
		if ($a_schedules != "" && is_array($a_schedules)) {
			$idx = 0;
			foreach ($a_schedules as $schedule) {
				if (!empty($schedule['name']) &&
				    $schedule['name'] == $filterent['sched']) {
					$schedstatus = filter_get_time_based_rule_status($schedule);

					foreach ($schedule['timerange'] as $timerange) {
						$tempFriendlyTime = "";
						$tempID = "";
						$firstprint = false;
						if ($timerange) {
							$dayFriendly = "";
							$tempFriendlyTime = "";

							//get hours
							$temptimerange = $timerange['hour'];
							$temptimeseparator = strrpos($temptimerange, "-");

							$starttime = substr ($temptimerange, 0, $temptimeseparator);
							$stoptime = substr ($temptimerange, $temptimeseparator+1);

							if ($timerange['month']) {
								$tempmontharray = explode(",", $timerange['month']);
								$tempdayarray = explode(",", $timerange['day']);
								$arraycounter = 0;
								$firstDayFound = false;
								$firstPrint = false;
								foreach ($tempmontharray as $monthtmp) {
									$month = $tempmontharray[$arraycounter];
									$day = $tempdayarray[$arraycounter];

									if (!$firstDayFound) {
										$firstDay = $day;
										$firstmonth = $month;
										$firstDayFound = true;
									}

									$currentDay = $day;
									$nextDay = $tempdayarray[$arraycounter+1];
									$currentDay++;
									if (($currentDay != $nextDay) || ($tempmontharray[$arraycounter] != $tempmontharray[$arraycounter+1])) {
										if ($firstPrint) {
											$dayFriendly .= ", ";
										}
										$currentDay--;
										if ($currentDay != $firstDay) {
											$dayFriendly .= $monthArray[$firstmonth-1] . " " . $firstDay . " - " . $currentDay ;
										} else {
											$dayFriendly .=	 $monthArray[$month-1] . " " . $day;
										}
										$firstDayFound = false;
										$firstPrint = true;
									}
									$arraycounter++;
								}
							} else {
								$tempdayFriendly = $timerange['position'];
								$firstDayFound = false;
								$tempFriendlyDayArray = explode(",", $tempdayFriendly);
								$currentDay = "";
								$firstDay = "";
								$nextDay = "";
								$counter = 0;
								foreach ($tempFriendlyDayArray as $day) {
									if ($day != "") {
										if (!$firstDayFound) {
											$firstDay = $tempFriendlyDayArray[$counter];
											$firstDayFound = true;
										}
										$currentDay =$tempFriendlyDayArray[$counter];
										//get next day
										$nextDay = $tempFriendlyDayArray[$counter+1];
										$currentDay++;
										if ($currentDay != $nextDay) {
											if ($firstprint) {
												$dayFriendly .= ", ";
											}
											$currentDay--;
											if ($currentDay != $firstDay) {
												$dayFriendly .= $dayArray[$firstDay-1] . " - " . $dayArray[$currentDay-1];
											} else {
												$dayFriendly .= $dayArray[$firstDay-1];
											}
											$firstDayFound = false;
											$firstprint = true;
										}
										$counter++;
									}
								}
							}
							$timeFriendly = $starttime . " - " . $stoptime;
							$description = $timerange['rangedescr'];
							$sched_content .= $dayFriendly . "; " . $timeFriendly . "<br />";
						}
					}
					#FIXME
					$sched_caption_escaped = str_replace("'", "\'", $schedule['descr']);
					$schedule_span_begin = '<a href="/firewall_schedule_edit.php?id=' . $idx . '" data-toggle="popover" data-trigger="hover focus" title="' . $schedule['name'] . '" data-content="' .
						$sched_caption_escaped . '" data-html="true">';
					$schedule_span_end = "</a>";
				}
				$idx++;
			}
		}
		$printicon = false;
		$alttext = "";
		$image = "";
		if (!isset($filterent['disabled'])) {
			if ($schedstatus) {
				if ($filterent['type'] == "block" || $filterent['type'] == "reject") {
					$image = "fa-solid fa-times-circle";
					$dispcolor = "text-danger";
					$alttext = gettext("Traffic matching this rule is currently being denied");
				} else {
					$image = "fa-solid fa-play-circle";
					$dispcolor = "text-success";
					$alttext = gettext("Traffic matching this rule is currently being allowed");
				}
				$printicon = true;
			} else if ($filterent['sched']) {
				if ($filterent['type'] == "block" || $filterent['type'] == "reject") {
					$image = "fa-solid fa-times-circle";
				} else {
					$image = "fa-solid fa-play-circle";
				}
				$alttext = gettext("This rule is not currently active because its period has expired");
				$dispcolor = "text-warning";
				$printicon = true;
			}
		}
	?>
				<td><?php print_states(intval($filterent['tracker']), -1); ?></td>
	<?php
		if ($if === 'FloatingRules') {
	?>
			<td onclick="fr_toggle(<?=$nrules;?>)" id="frd<?=$nrules;?>" ondblclick="document.location='firewall_rules_edit.php?id=<?=$i;?>';">
	<?php
			if (isset($filterent['interface'])) {
				$selected_interfaces = explode(',', $filterent['interface']);
				unset($selected_descs);
				foreach ($selected_interfaces as $interface) {
					if (isset($ifdescs[$interface])) {
						$selected_descs[] = $ifdescs[$interface];
					} else {
						switch ($interface) {
						case 'l2tp':
							if (config_get_path('l2tp/mode') == 'server') {
								$selected_descs[] = 'L2TP VPN';
							}
							break;
						case 'pppoe':
							if (is_pppoe_server_enabled()) {
								$selected_descs[] = 'PPPoE Server';
							}
							break;
						case 'enc0':
							if (ipsec_enabled()) {
								$selected_descs[] = 'IPsec';
							}
							break;
						case 'openvpn':
							if (!empty(config_get_path('openvpn/openvpn-server', [])) ||
							    !empty(config_get_path('openvpn/openvpn-client', []))) {
								$selected_descs[] = 'OpenVPN';
							}
							break;
						case 'any':
							$selected_descs[] = 'Any';
							break;
						default:
							$selected_descs[] = $interface;
							break;
						}
					}
				}
				if (!empty($selected_descs)) {
					$desclist = '';
					$desclength = 0;
					foreach ($selected_descs as $desc) {
						$desclength += strlen($desc);
						if ($desclength > 18) {
							$desclist .= ',<br/>';
							$desclength = 0;
						} elseif ($desclist) {
							$desclist .= ', ';
							$desclength += 2;
						}
						$desclist .= $desc;
					}
					echo $desclist;
				}
			}
	?>
			</td>
	<?php
		}
	?>
			<td>
	<?php
		if (isset($filterent['ipprotocol'])) {
			switch ($filterent['ipprotocol']) {
				case "inet":
					echo "IPv4 ";
					break;
				case "inet6":
					echo "IPv6 ";
					break;
				case "inet46":
					echo "IPv4+6 ";
					break;
			}
		} else {
			echo "IPv4 ";
		}

		if (isset($filterent['protocol'])) {
			echo strtoupper($filterent['protocol']);

			if (strtoupper($filterent['protocol']) == "ICMP" && !empty($filterent['icmptype'])) {
				// replace each comma-separated icmptype item by its (localised) full description
				$t = 	implode(', ',
						array_map(
						        function($type) {
								global $icmptypes;
								return $icmptypes[$type]['descrip'];
							},
							explode(',', $filterent['icmptype'])
						)
					);
				echo sprintf('<br /><div style="cursor:help;padding:1px;line-height:1.1em;max-height:2.5em;max-width:180px;overflow-y:auto;overflow-x:hidden" title="%s:%s%s"><small><u>%s</u></small></div>', gettext('ICMP subtypes'), chr(13), $t, str_replace(',', '</u>, <u>',$filterent['icmptype']));
			}
		} else {
			echo " *";
		}
	?>
						</td>
						<td>
							<?php if (isset($alias['src'])): ?>
								<a href="/firewall_aliases_edit.php?id=<?=$alias['src']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['src'])?>" data-html="true">
									<?=str_replace('_', '_<wbr>', htmlspecialchars(pprint_address($filterent['source'])))?>
								</a>
							<?php elseif ($show_system_alias_popup && array_key_exists($filterent['source']['network'], $system_alias_specialnet)): ?>
								<a data-toggle="popover" data-trigger="hover focus" title="<?=gettext('System alias details')?>" data-content="<?=system_alias_info_popup($filterent['source']['network'])?>" data-html="true">
									<?=str_replace('_', '_<wbr>', htmlspecialchars(pprint_address($filterent['source'], $filter_srcdsttype_flags)))?>
								</a>
							<?php else: ?>
								<?=htmlspecialchars(pprint_address($filterent['source'], $filter_srcdsttype_flags))?>
							<?php endif; ?>
						</td>
						<td>
							<?php if (isset($alias['srcport'])): ?>
								<a href="/firewall_aliases_edit.php?id=<?=$alias['srcport']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['srcport'])?>" data-html="true">
									<?=str_replace('_', '_<wbr>', htmlspecialchars(pprint_port($filterent['source']['port'])))?>
								</a>
							<?php else: ?>
								<?=htmlspecialchars(pprint_port($filterent['source']['port']))?>
							<?php endif; ?>
						</td>
						<td>
							<?php if (isset($alias['dst'])): ?>
								<a href="/firewall_aliases_edit.php?id=<?=$alias['dst']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['dst'])?>" data-html="true">
									<?=str_replace('_', '_<wbr>', htmlspecialchars(pprint_address($filterent['destination'])))?>
								</a>
							<?php elseif ($show_system_alias_popup && array_key_exists($filterent['destination']['network'], $system_alias_specialnet)): ?>
								<a data-toggle="popover" data-trigger="hover focus" title="<?=gettext('System alias details')?>" data-content="<?=system_alias_info_popup($filterent['destination']['network'])?>" data-html="true">
									<?=str_replace('_', '_<wbr>', htmlspecialchars(pprint_address($filterent['destination'], $filter_srcdsttype_flags)))?>
								</a>
							<?php else: ?>
								<?=htmlspecialchars(pprint_address($filterent['destination'], $filter_srcdsttype_flags))?>
							<?php endif; ?>
						</td>
						<td>
							<?php if (isset($alias['dstport'])): ?>
								<a href="/firewall_aliases_edit.php?id=<?=$alias['dstport']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['dstport'])?>" data-html="true">
									<?=str_replace('_', '_<wbr>', htmlspecialchars(pprint_port($filterent['destination']['port'])))?>
								</a>
							<?php else: ?>
								<?=htmlspecialchars(pprint_port($filterent['destination']['port']))?>
							<?php endif; ?>
						</td>
						<td>
							<?php if (isset($filterent['gateway'])): ?>
								<?php
									/* Cache gateway status for this page load.
									 * See https://redmine.pfsense.org/issues/12174 */
									if (!is_array($gw_info)) {
										$gw_info = array();
									}
									if (empty($gw_info[$filterent['gateway']])) {
										$gw_info[$filterent['gateway']] = gateway_info_popup($filterent['gateway'], $gateways_status);
									}
								?>
								<?php if (!empty($gw_info[$filterent['gateway']])): ?>
									<?=$gw_info[$filterent['gateway']]?>
								<?php else: ?>
									<span>
								<?php endif; ?>
							<?php else: ?>
								<span>
							<?php endif; ?>
								<?php if (isset($if_config[$filterent['gateway']]['descr'])): ?>
									<?=str_replace('_', '_<wbr>', htmlspecialchars($if_config[$filterent['gateway']]['descr']))?>
								<?php else: ?>
									<?=htmlspecialchars(pprint_port($filterent['gateway']))?>
								<?php endif; ?>
							</span>
						</td>
						<td>
							<?php
								if (isset($filterent['ackqueue']) && isset($filterent['defaultqueue'])) {
									$desc = str_replace('_', ' ', $filterent['ackqueue']);
									echo "<a href=\"firewall_shaper_queues.php?queue={$filterent['ackqueue']}&amp;action=show\">{$desc}</a>";
									$desc = str_replace('_', '_<wbr>', $filterent['defaultqueue']);
									echo "/<a href=\"firewall_shaper_queues.php?queue={$filterent['defaultqueue']}&amp;action=show\">{$desc}</a>";
								} else if (isset($filterent['defaultqueue'])) {
									$desc = str_replace('_', '_<wbr>', $filterent['defaultqueue']);
									echo "<a href=\"firewall_shaper_queues.php?queue={$filterent['defaultqueue']}&amp;action=show\">{$desc}</a>";
								} else {
									echo gettext("none");
								}
							?>
						</td>
						<td>
							<?php if ($printicon) { ?>
								<i class="<?=$image?> <?=$dispcolor?>" title="<?=$alttext;?>"></i>
							<?php } ?>
							<?=$schedule_span_begin;?><?=str_replace('_', '_<wbr>', htmlspecialchars($filterent['sched']));?>&nbsp;<?=$schedule_span_end;?>
						</td>
						<td>
							<?=htmlspecialchars($filterent['descr']);?>
						</td>
						<td class="action-icons">
						<!-- <?=(isset($filterent['disabled']) ? 'enable' : 'disable')?> -->
							<a	class="fa-solid fa-anchor icon-pointer" id="Xmove_<?=$filteri?>" title="<?=$XmoveTitle?>"></a>
							<a href="firewall_rules_edit.php?id=<?=$filteri;?>" class="fa-solid fa-pencil" title="<?=gettext('Edit')?>"></a>
							<a href="firewall_rules_edit.php?dup=<?=$filteri;?>" class="fa-regular fa-clone" title="<?=gettext('Copy')?>"></a>
<?php if (isset($filterent['disabled'])) {
?>
							<a href="?act=toggle&amp;if=<?=htmlspecialchars($if);?>&amp;id=<?=$filteri;?>" class="fa-regular fa-square-check" title="<?=gettext('Enable')?>" usepost></a>
<?php } else {
?>
							<a href="?act=toggle&amp;if=<?=htmlspecialchars($if);?>&amp;id=<?=$filteri;?>" class="fa-solid fa-ban" title="<?=gettext('Disable')?>" usepost></a>
<?php }
?>
							<a href="?act=del&amp;if=<?=htmlspecialchars($if);?>&amp;id=<?=$filteri;?>" class="fa-solid fa-trash-can" title="<?=gettext('Delete this rule')?>" usepost></a>
<?php if (($filterent['type'] == 'pass') &&
	    !empty($filterent['tracker'])): ?>
							<a href="?act=killid&amp;if=<?=htmlspecialchars($if);?>&amp;id=<?=$filteri;?>&amp;tracker=<?=$filterent['tracker']?>" class="fa-solid fa-times do-confirm" title="<?=gettext('Kill states on this interface created by this rule')?>" usepost></a>
<?php endif; ?>
						</td>
					</tr>
<?php
		$nrules++;
	}
endforeach;

// There can be separator(s) after the last rule listed.
foreach ($seprows as $idx => $sep) {
	if ($idx >= $nrules) {
		display_separator($separators, $idx, $columns_in_table);
	}
}
?>
				</tbody>
			</table>
		</div>
	</div>

<?php if ($nrules == 0): ?>
	<div class="alert alert-warning" role="alert">
		<p>
		<?php if ($if === 'FloatingRules'): ?>
			<?=gettext('No floating rules are currently defined.');?>
		<?php else: ?>
			<?=gettext("No rules are currently defined for this interface");?><br />
			<?=gettext("All incoming connections on this interface will be blocked until pass rules are added.");?>
		<?php endif;?>
			<?=gettext("Click the button to add a new rule.");?>
		</p>
	</div>
<?php endif;?>

	<nav class="action-buttons">
		<a href="firewall_rules_edit.php?if=<?=htmlspecialchars($if);?>&amp;after=-1" role="button" class="btn btn-sm btn-success" title="<?=gettext('Add rule to the top of the list')?>">
			<i class="fa-solid fa-turn-up icon-embed-btn"></i>
			<?=gettext("Add");?>
		</a>
		<a href="firewall_rules_edit.php?if=<?=htmlspecialchars($if);?>" role="button" class="btn btn-sm btn-success" title="<?=gettext('Add rule to the end of the list')?>">
			<i class="fa-solid fa-turn-down icon-embed-btn"></i>
			<?=gettext("Add");?>
		</a>
		<button id="del_x" name="del_x" type="submit" class="btn btn-danger btn-sm" value="<?=gettext("Delete selected rules"); ?>" disabled title="<?=gettext('Delete selected rules')?>">
			<i class="fa-solid fa-trash-can icon-embed-btn"></i>
			<?=gettext("Delete"); ?>
		</button>
		<button id="toggle_x" name="toggle_x" type="submit" class="btn btn-primary btn-sm" value="<?=gettext("Toggle selected rules"); ?>" disabled title="<?=gettext('Toggle selected rules')?>">
			<i class="fa-solid fa-ban icon-embed-btn"></i>
			<?=gettext("Toggle"); ?>
		</button>
		<?php if ($if !== 'FloatingRules'):?>
		<button id="copy_x" name="copy_x" type="button" class="btn btn-primary btn-sm" value="<?=gettext("Copy selected rules"); ?>" disabled title="<?=gettext('Copy selected rules')?>" data-toggle="modal" data-target="#rulescopy">
			<i class="fa-regular fa-clone icon-embed-btn"></i>
			<?=gettext("Copy"); ?>
		</button>
		<?php endif;?>
		<button type="submit" id="order-store" name="order-store" class="btn btn-sm btn-primary" value="store changes" disabled title="<?=gettext('Save rule order')?>">
			<i class="fa-solid fa-save icon-embed-btn"></i>
			<?=gettext("Save")?>
		</button>
		<button type="submit" id="addsep" name="addsep" class="btn btn-sm btn-warning" title="<?=gettext('Add separator')?>">
			<i class="fa-solid fa-plus icon-embed-btn"></i>
			<?=gettext("Separator")?>
		</button>
	</nav>
</form>
<?php
// Create a Modal object to display Rules Copy window
$form = new Form(false);
$modal = new Modal('Copy selected rules', 'rulescopy', true);
$modal->addInput(new Form_Select(
	'copyr_dstif',
	'*Destination Interface',
	$if,
	filter_get_interface_list()
))->setHelp('Select the destination interface where the rules should be copied. Rules will be added after existing rules on that interface.');
$modal->addInput(new Form_Checkbox(
	'copyr_convertif',
	'Convert interface definitions',
	'Enable Interface Address/Net conversion',
	false
))->setHelp('Convert source Interface Address/Net definitions to the destination Interface Address/Net.%1$s' .
	    'For example: LAN Address -> OPT1 Address, or LAN net -> OPT1 net.%1$s' .
	    'Interface groups and some special interfaces (IPsec, OpenVPN), do not support this feature.', '<br />');
$btncopyrules = new Form_Button(
	'copyr',
	'Paste',
	null,
	'fa-regular fa-clone'
);
$btncopyrules->setAttribute('type','button')->addClass('btn-success');
$btncancelcopyrules = new Form_Button(
	'cancel_copyr',
	'Cancel',
	null,
	'fa-solid fa-undo'
);
$btncancelcopyrules->setAttribute('type','button')->addClass('btn-warning');
$modal->addInput(new Form_StaticText(
	null,
	$btncopyrules . $btncancelcopyrules
));
$form->add($modal);
print($form);

else: ?>
	<div class="alert alert-warning" role="alert">
		<p>
			<?= gettext("Select an interface to view firewall rules.") ?>
			<br />
			<?php
				echo sprintf(gettext("See %sSystem > General Setup%s, %sRequire Firewall Interface%s."),
				'<a href="/system.php">',
				'</a>',
				'<strong>',
				'</strong>');
			?>
		</p>
	</div>

<?php endif; ?>

<div class="infoblock">
	<div class="alert alert-info clearfix" role="alert"><div class="pull-left">
		<dl class="dl-horizontal responsive">
		<!-- Legend -->
			<dt><?=gettext('Legend')?></dt>				<dd></dd>
			<dt><i class="fa-solid fa-check text-success"></i></dt>		<dd><?=gettext("Pass");?></dd>
			<dt><i class="fa-solid fa-filter"></i></dt>	<dd><?=gettext("Match");?></dd>
			<dt><i class="fa-solid fa-times text-danger"></i></dt>	<dd><?=gettext("Block");?></dd>
			<dt><i class="fa-regular fa-hand text-warning"></i></dt>		<dd><?=gettext("Reject");?></dd>
			<dt><i class="fa-solid fa-tasks"></i></dt>	<dd> <?=gettext("Log");?></dd>
			<dt><i class="fa-solid fa-cog"></i></dt>		<dd> <?=gettext("Advanced filter");?></dd>
			<dt><i class="fa-solid fa-forward text-success"></i></dt><dd> <?=gettext("&quot;Quick&quot; rule. Applied immediately on match.")?></dd>
		</dl>

<?php
	if ("FloatingRules" != $if) {
		print(gettext("Rules are evaluated on a first-match basis (i.e. " .
			"the action of the first rule to match a packet will be executed). ") . '<br />' .
			gettext("This means that if block rules are used, it is important to pay attention " .
			"to the rule order. Everything that isn't explicitly passed is blocked " .
			"by default. "));
	} else {
		print(gettext("Floating rules are evaluated on a first-match basis (i.e. " .
			"the action of the first rule to match a packet will be executed) only " .
			"if the 'quick' option is checked on a rule. Otherwise they will only match if no " .
			"other rules match. Pay close attention to the rule order and options " .
			"chosen. If no rule here matches, the per-interface or default rules are used. "));
	}

	printf(gettext('%1$sClick the anchor icon %2$s to move checked rules before the clicked row. Hold down ' .
			'the shift key and click to move the rules after the clicked row.'), '<br /><br />', '<i class="fa-solid fa-anchor"></i>');
?>
	</div>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[

//Need to create some variables here so that jquery/pfSenseHelpers.js can read them
iface = "<?=strtolower($if)?>";
cncltxt = '<?=gettext("Cancel")?>';
svtxt = '<?=gettext("Save")?>';
svbtnplaceholder = '<?=gettext("Enter a description, Save, then drag to final location.")?>';
configsection = "filter";

events.push(function() {

	// "Move to here" (anchor) action
	$('[id^=Xmove_]').click(function (event) {

		// Prevent click from toggling row
		event.stopImmediatePropagation();

		// Save the target rule position
		var anchor_row = $(this).parents("tr:first");

		if (event.shiftKey) {
			$($('#ruletable > tbody  > tr').get().reverse()).each(function() {
				ruleid = this.id.slice(2);

				if (ruleid && !isNaN(ruleid)) {
					if ($('#frc' + ruleid).prop('checked')) {
						// Move the selected rows, un-select them and add highlight class
						$(this).insertAfter(anchor_row);
						fr_toggle(ruleid, "fr");
						$('#fr' + ruleid).addClass("highlight");
					}
				}
			});
		} else {
			$('#ruletable > tbody  > tr').each(function() {
				ruleid = this.id.slice(2);

				if (ruleid && !isNaN(ruleid)) {
					if ($('#frc' + ruleid).prop('checked')) {
						// Move the selected rows, un-select them and add highlight class
						$(this).insertBefore(anchor_row);
						fr_toggle(ruleid, "fr");
						$('#fr' + ruleid).addClass("highlight");
					}
				}
			});
		}

		// Temporarily set background color so user can more easily see the moved rules, then fade
		$('.highlight').effect("highlight", {color: "#739b4b;"}, 4000);
		$('#ruletable tr').removeClass("highlight");
		$('#order-store').removeAttr('disabled');
		reindex_rules($(anchor_row).parent('tbody'));
		dirty = true;
	}).mouseover(function(e) {
		var ruleselected = false;

		$(this).css("cursor", "default");

		// Are any rules currently selected?
		$('[id^=frc]').each(function () {
			if ($(this).prop("checked")) {
				ruleselected = true;
			}
		});

		// If so, change the icon to show the insertion point
		if (ruleselected) {
			if (e.shiftKey) {
				$(this).removeClass().addClass("fa-solid fa-lg fa-arrow-down text-danger");
			} else {
				$(this).removeClass().addClass("fa-solid fa-lg fa-arrow-up text-danger");
			}
		}
	}).mouseout(function(e) {
		$(this).removeClass().addClass("fa-solid fa-anchor");
	});

<?php if(!config_path_enabled('system/webgui', 'roworderdragging')): ?>
	// Make rules sortable. Hiding the table before applying sortable, then showing it again is
	// a work-around for very slow sorting on FireFox
	$('table tbody.user-entries').hide();

	$('table tbody.user-entries').sortable({
		cursor: 'grabbing',
		scroll: true,
		overflow: 'scroll',
		scrollSensitivity: 100,
		update: function(event, ui) {
			$('#order-store').removeAttr('disabled');
			reindex_rules(ui.item.parent('tbody'));
			dirty = true;
		}
	});

	$('table tbody.user-entries').show();
<?php endif; ?>

	// Check all of the rule checkboxes so that their values are posted
	$('#order-store').click(function () {
		$('[id^=frc]').prop('checked', true);

		// Save the separator bar configuration
		save_separators();

		// Suppress the "Do you really want to leave the page" message
		saving = true;
	});

	$('[id^=fr]').click(function () {
		buttonsmode('frc', ['del_x', 'toggle_x', 'copy_x']);
	});

	// Provide a warning message if the user tries to change page before saving
	$(window).bind('beforeunload', function(){
		if ((!saving && dirty) || newSeparator) {
			return ("<?=gettext('One or more rules have been moved but have not yet been saved')?>");
		} else {
			return undefined;
		}
	});

	$(document).on('keyup keydown', function(e){
		if (e.shiftKey) {
			$('[id^=Xmove_]').attr("title", "<?=$ShXmoveTitle?>");
		} else {
			$('[id^=Xmove_]').attr("title", "<?=$XmoveTitle?>");
		}
	});

	$('#selectAll').click(function() {
		var checkedStatus = this.checked;
		$('#ruletable tbody tr').find('td:first :checkbox').each(function() {
		$(this).prop('checked', checkedStatus);
		});
		buttonsmode('frc', ['del_x', 'toggle_x', 'copy_x']);
	});

	$("#copyr").click(function() {
		$("#rulescopy").modal('hide');
		$("#dstif").val($("#copyr_dstif").val());
		$("#convertif").val($("#copyr_convertif").prop('checked'));
		document.getElementById('mainform').submit();
	});

	$("#cancel_copyr").click(function() {
		$("#rulescopy").modal('hide');
	});

});
//]]>
</script>

<?php include("foot.inc");?>
