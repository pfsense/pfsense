<?php
/*
	firewall_rules.php
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
##|*IDENT=page-firewall-rules
##|*NAME=Firewall: Rules
##|*DESCR=Allow access to the 'Firewall: Rules' page.
##|*MATCH=firewall_rules.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("ipsec.inc");
require_once("shaper.inc");

$XmoveTitle = gettext("Move checked rules above this one. Shift+Click to move checked rules below.");
$ShXmoveTitle = gettext("Move checked rules below this one. Release shift to move checked rules above.");

$pgtitle = array(gettext("Firewall"), gettext("Rules"));
$shortcut_section = "firewall";

function get_pf_rules($rules, $tracker) {

	if ($rules == NULL || !is_array($rules))
		return (NULL);

	$arr = array();
	foreach ($rules as $rule) {
		if ($rule['tracker'] === $tracker) {
			$arr[] = $rule;
		}
	}

	if (count($arr) == 0)
		return (NULL);

	return ($arr);
}

function print_states($tracker) {
	global $rulescnt;

	$rulesid = "";
	$bytes = 0;
	$states = 0;
	$packets = 0;
	$evaluations = 0;
	$stcreations = 0;
	$rules = get_pf_rules($rulescnt, $tracker);
	if (is_array($rules)) {
		foreach ($rules as $rule) {
			$bytes += $rule['bytes'];
			$states += $rule['states'];
			$packets += $rule['packets'];
			$evaluations += $rule['evaluations'];
			$stcreations += $rule['state creations'];
			if (strlen($rulesid) > 0) {
				$rulesid .= ",";
			}
			$rulesid .= "{$rule['id']}";
		}
	}

	printf("<a href=\"diag_dump_states.php?ruleid=%s\" data-toggle=\"popover\" data-trigger=\"hover focus\" title=\"%s\" ",
	    $rulesid, gettext("States details"));
	printf("data-content=\"evaluations: %s<br>packets: %s<br>bytes: %s<br>states: %s<br>state creations: %s\" data-html=\"true\">",
	    format_number($evaluations), format_number($packets), format_bytes($bytes),
	    format_number($states), format_number($stcreations));
	printf("%d/%s</a><br>", format_number($states), format_bytes($bytes));
}

function delete_nat_association($id) {
	global $config;

	if (!$id || !is_array($config['nat']['rule'])) {
		return;
	}

	$a_nat = &$config['nat']['rule'];

	foreach ($a_nat as &$natent) {
		if ($natent['associated-rule-id'] == $id) {
			$natent['associated-rule-id'] = '';
		}
	}
}

if (!is_array($config['filter']['rule'])) {
	$config['filter']['rule'] = array();
}

filter_rules_sort();
$a_filter = &$config['filter']['rule'];

$if = $_GET['if'];

if ($_POST['if']) {
	$if = $_POST['if'];
}

$ifdescs = get_configured_interface_with_descr();

/* add group interfaces */
if (is_array($config['ifgroups']['ifgroupentry'])) {
	foreach ($config['ifgroups']['ifgroupentry'] as $ifgen) {
		if (have_ruleint_access($ifgen['ifname'])) {
			$iflist[$ifgen['ifname']] = $ifgen['ifname'];
		}
	}
}

foreach ($ifdescs as $ifent => $ifdesc) {
	if (have_ruleint_access($ifent)) {
		$iflist[$ifent] = $ifdesc;
	}
}

if ($config['l2tp']['mode'] == "server") {
	if (have_ruleint_access("l2tp")) {
		$iflist['l2tp'] = gettext("L2TP VPN");
	}
}

if (is_array($config['pppoes']['pppoe'])) {
	foreach ($config['pppoes']['pppoe'] as $pppoes) {
		if (($pppoes['mode'] == 'server') && have_ruleint_access("pppoe")) {
			$iflist['pppoe'] = gettext("PPPoE Server");
		}
	}
}

/* add ipsec interfaces */
if (ipsec_enabled() && have_ruleint_access("enc0")) {
	$iflist["enc0"] = gettext("IPsec");
}

/* add openvpn/tun interfaces */
if ($config['openvpn']["openvpn-server"] || $config['openvpn']["openvpn-client"]) {
	$iflist["openvpn"] = gettext("OpenVPN");
}

if (!$if || !isset($iflist[$if])) {
	if ("any" == $if) {
		$if = "FloatingRules";
	} else if ("FloatingRules" != $if) {
		if (isset($iflist['wan'])) {
			$if = "wan";
		} else {
			$if = "FloatingRules";
		}
	}
}

if ($_POST) {
	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;
		$retval = filter_configure();

		clear_subsystem_dirty('filter');

		$savemsg = sprintf(gettext("The settings have been applied. The firewall rules are now reloading in the background.<br />%s Monitor %s the reload progress."),
									"<a href='status_filter_reload.php'>", "</a>");
	}
}

if ($_GET['act'] == "del") {
	if ($a_filter[$_GET['id']]) {
		if (!empty($a_filter[$_GET['id']]['associated-rule-id'])) {
			delete_nat_association($a_filter[$_GET['id']]['associated-rule-id']);
		}
		unset($a_filter[$_GET['id']]);

		// Update the separators
		$a_separators = &$config['filter']['separator'][strtolower($if)];
		$ridx = ifridx($if, $_GET['id']);	// get rule index within interface
		$mvnrows = -1;
		move_separators($a_separators, $ridx, $mvnrows);

		if (write_config()) {
			mark_subsystem_dirty('filter');
		}

		header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
		exit;
	}
}

// Handle save msg if defined
if ($_REQUEST['savemsg']) {
	$savemsg = htmlentities($_REQUEST['savemsg']);
}

if (isset($_POST['del_x'])) {
	/* delete selected rules */
	$deleted = false;

	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		$a_separators = &$config['filter']['separator'][strtolower($if)];

		foreach ($_POST['rule'] as $rulei) {
			delete_nat_association($a_filter[$rulei]['associated-rule-id']);
			unset($a_filter[$rulei]);
			$deleted = true;

			// Update the separators
			$ridx = ifridx($if, $rulei);	// get rule index within interface
			$mvnrows = -1;
			move_separators($a_separators, $ridx, $mvnrows);
		}

		if ($deleted) {
			if (write_config()) {
				mark_subsystem_dirty('filter');
			}
		}

		header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
		exit;
	}
} else if ($_GET['act'] == "toggle") {
	if ($a_filter[$_GET['id']]) {
		if (isset($a_filter[$_GET['id']]['disabled'])) {
			unset($a_filter[$_GET['id']]['disabled']);
		} else {
			$a_filter[$_GET['id']]['disabled'] = true;
		}
		if (write_config()) {
			mark_subsystem_dirty('filter');
		}

		header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
		exit;
	}
} else if ($_POST['order-store']) {

	/* update rule order, POST[rule] is an array of ordered IDs */
	if (is_array($_POST['rule']) && !empty($_POST['rule'])) {
		$a_filter_new = array();

		// Include the rules of other interfaces listed in config before this (the selected) interface.
		foreach ($a_filter as $filteri_before => $filterent) {
			if (($filterent['interface'] == $if && !isset($filterent['floating'])) || (isset($filterent['floating']) && "FloatingRules" == $if)) {
				break;
			} else {
				$a_filter_new[] = $filterent;
			}
		}

		// Include the rules of this (the selected) interface.
		// If a rule is not in POST[rule], it has been deleted by the user
		foreach ($_POST['rule'] as $id) {
			$a_filter_new[] = $a_filter[$id];
		}

		// Include the rules of other interfaces listed in config after this (the selected) interface.
		foreach ($a_filter as $filteri_after => $filterent) {
			if ($filteri_before > $filteri_after) {
				continue;
			}
			if (($filterent['interface'] == $if && !isset($filterent['floating'])) || (isset($filterent['floating']) && "FloatingRules" == $if)) {
				continue;
			} else {
				$a_filter_new[] = $filterent;
			}
		}

		$a_filter = $a_filter_new;

		$config['filter']['separator'][strtolower($if)] = "";

		if ($_POST['separator']) {
			$idx = 0;
			foreach ($_POST['separator'] as $separator) {
				$config['filter']['separator'][strtolower($separator['if'])]['sep' . $idx++] = $separator;
			}
		}

		if (write_config()) {
			mark_subsystem_dirty('filter');
		}

		header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
		exit;
	}
}

$tab_array = array(array(gettext("Floating"), ("FloatingRules" == $if), "firewall_rules.php?if=FloatingRules"));

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
$shortcut_section = "firewall";

include("head.inc");
$nrules = 0;

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (is_subsystem_dirty('filter')) {
	print_apply_box(gettext("The firewall rule configuration has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
}

display_top_tabs($tab_array);

$showantilockout = false;
$showprivate = false;
$showblockbogons = false;

if (!isset($config['system']['webgui']['noantilockout']) &&
    (((count($config['interfaces']) > 1) && ($if == 'lan')) ||
    ((count($config['interfaces']) == 1) && ($if == 'wan')))) {
	$showantilockout = true;
}

if (isset($config['interfaces'][$if]['blockpriv'])) {
	$showprivate = true;
}

if (isset($config['interfaces'][$if]['blockbogons'])) {
	$showblockbogons = true;
}

/* Load the counter data of each pf rule. */
$rulescnt = pfSense_get_pf_rules();

// Update this if you add or remove columns!
$columns_in_table = 13;

?>
<form method="post">
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext("Rules (Drag to Change Order)")?></h2></div>
		<div id="mainarea" class="table-responsive panel-body">
			<table id="ruletable" class="table table-hover table-striped table-condensed">
				<thead>
					<tr>
						<th><!-- checkbox --></th>
						<th><!-- status icons --></th>
						<th><?=gettext("States")?></th>
						<th><?=gettext("Protocol")?></th>
						<th><?=gettext("Source")?></th>
						<th><?=gettext("Port")?></th>
						<th><?=gettext("Destination")?></th>
						<th><?=gettext("Port")?></th>
						<th><?=gettext("Gateway")?></th>
						<th><?=gettext("Queue")?></th>
						<th><?=gettext("Schedule")?></th>
						<th><?=gettext("Description")?></th>
						<th><?=gettext("Actions")?></th>
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
						<td title="<?=gettext("traffic is passed")?>"><i class="fa fa-check text-success"></i></td>
						<td><?php print_states(intval(ANTILOCKOUT_TRACKER)); ?></td>
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
							<a href="system_advanced_admin.php" title="<?=gettext("Settings");?>"><i class="fa fa-cog"></i></a>
						</td>
					</tr>
<?php 	endif;?>
<?php 	if ($showprivate): ?>
					<tr id="private">
						<td></td>
						<td title="<?=gettext("traffic is blocked")?>"><i class="fa fa-times text-danger"></i></td>
						<td><?php print_states(intval(RFC1918_TRACKER)); ?></td>
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
							<a href="interfaces.php?if=<?=htmlspecialchars($if)?>" title="<?=gettext("Settings");?>"><i class="fa fa-cog"></i></a>
						</td>
					</tr>
<?php 	endif;?>
<?php 	if ($showblockbogons): ?>
					<tr id="bogons">
						<td></td>
						<td title="<?=gettext("traffic is blocked")?>"><i class="fa fa-times text-danger"></i></td>
						<td><?php print_states(intval(BOGONS_TRACKER)); ?></td>
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
							<a href="interfaces.php?if=<?=htmlspecialchars($if)?>" title="<?=gettext("Settings");?>"><i class="fa fa-cog"></i></a>
						</td>
					</tr>
<?php 	endif;?>
			</tbody>
<?php endif;?>
			<tbody class="user-entries">
<?php
$nrules = 0;
$separators = $config['filter']['separator'][strtolower($if)];

// Get a list of separator rows and use it to call the display separator function only for rows which there are separator(s).
// More efficient than looping through the list of separators on every row.
$seprows = separator_rows($separators);

foreach ($a_filter as $filteri => $filterent):

	if (($filterent['interface'] == $if && !isset($filterent['floating'])) || (isset($filterent['floating']) && "FloatingRules" == $if)) {

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
			$iconfn = "times text-danger";
			$title_text = gettext("traffic is blocked");
		} else if ($filterent['type'] == "reject") {
			$iconfn = "hand-stop-o text-warning";
			$title_text = gettext("traffic is rejected");
		} else if ($filterent['type'] == "match") {
			$iconfn = "filter";
			$title_text = gettext("traffic is matched");
		} else {
			$iconfn = "check text-success";
			$title_text = gettext("traffic is passed");
		}
	?>
						<td title="<?=$title_text?>">
							<a href="?if=<?=htmlspecialchars($if);?>&amp;act=toggle&amp;id=<?=$filteri;?>">
								<i class="fa fa-<?=$iconfn?>" title="<?=gettext("click to toggle enabled/disabled status");?>"></i>
							</a>
	<?php
		if ($filterent['quick'] == 'yes') {
			print '<i class="fa fa-forward text-success" title="'. gettext("&quot;Quick&quot; rule. Applied immediately on match.") .'" style="cursor: pointer;"></i>';
		}

		$isadvset = firewall_check_for_advanced_options($filterent);
		if ($isadvset) {
			print '<i class="fa fa-cog" title="'. gettext("advanced setting") .': '. $isadvset .'"></i>';
		}

		if (isset($filterent['log'])) {
			print '<i class="fa fa-tasks" title="'. gettext("traffic is logged") .'" style="cursor: pointer;"></i>';
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
		$a_schedules = &$config['schedules']['schedule'];
		$schedule_span_begin = "";
		$schedule_span_end = "";
		$sched_caption_escaped = "";
		$sched_content = "";
		$schedstatus = false;
		$dayArray = array (gettext('Mon'), gettext('Tues'), gettext('Wed'), gettext('Thur'), gettext('Fri'), gettext('Sat'), gettext('Sun'));
		$monthArray = array (gettext('January'), gettext('February'), gettext('March'), gettext('April'), gettext('May'), gettext('June'), gettext('July'), gettext('August'), gettext('September'), gettext('October'), gettext('November'), gettext('December'));
		if ($config['schedules']['schedule'] != "" && is_array($config['schedules']['schedule'])) {
			$idx = 0;
			foreach ($a_schedules as $schedule) {
				if ($schedule['name'] == $filterent['sched']) {
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
			}
			$idx++;
		}
		$printicon = false;
		$alttext = "";
		$image = "";
		if (!isset($filterent['disabled'])) {
			if ($schedstatus) {
				if ($filterent['type'] == "block" || $filterent['type'] == "reject") {
					$image = "times-circle";
					$dispcolor = "text-danger";
					$alttext = gettext("Traffic matching this rule is currently being denied");
				} else {
					$image = "play-circle";
					$dispcolor = "text-success";
					$alttext = gettext("Traffic matching this rule is currently being allowed");
				}
				$printicon = true;
			} else if ($filterent['sched']) {
				if ($filterent['type'] == "block" || $filterent['type'] == "reject") {
					$image = "times-circle";
				} else {
					$image = "play-circle";
				}
				$alttext = gettext("This rule is not currently active because its period has expired");
				$dispcolor = "text-warning";
				$printicon = true;
			}
		}
	?>
				<td><?php print_states(intval($filterent['tracker'])); ?></td>
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
				echo ' <span style="cursor: help;" title="' . gettext('ICMP type') . ': ' .
					($filterent['ipprotocol'] == "inet6" ? $icmp6types[$filterent['icmptype']] : $icmptypes[$filterent['icmptype']]) .
					'"><u>';
				echo $filterent['icmptype'];
				echo '</u></span>';
			}
		} else echo "*";

	?>
						</td>
						<td>
							<?php if (isset($alias['src'])): ?>
								<a href="/firewall_aliases_edit.php?id=<?=$alias['src']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['src'])?>" data-html="true">
									<?=str_replace('_', ' ', htmlspecialchars(pprint_address($filterent['source'])))?>
								</a>
							<?php else: ?>
								<?=htmlspecialchars(pprint_address($filterent['source']))?>
							<?php endif; ?>
						</td>
						<td>
							<?php if (isset($alias['srcport'])): ?>
								<a href="/firewall_aliases_edit.php?id=<?=$alias['srcport']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['srcport'])?>" data-html="true">
									<?=str_replace('_', ' ', htmlspecialchars(pprint_port($filterent['source']['port'])))?>
								</a>
							<?php else: ?>
								<?=htmlspecialchars(pprint_port($filterent['source']['port']))?>
							<?php endif; ?>
						</td>
						<td>
							<?php if (isset($alias['dst'])): ?>
								<a href="/firewall_aliases_edit.php?id=<?=$alias['dst']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['dst'])?>" data-html="true">
									<?=str_replace('_', ' ', htmlspecialchars(pprint_address($filterent['destination'])))?>
								</a>
							<?php else: ?>
								<?=htmlspecialchars(pprint_address($filterent['destination']))?>
							<?php endif; ?>
						</td>
						<td>
							<?php if (isset($alias['dstport'])): ?>
								<a href="/firewall_aliases_edit.php?id=<?=$alias['dstport']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['dstport'])?>" data-html="true">
									<?=str_replace('_', ' ', htmlspecialchars(pprint_port($filterent['destination']['port'])))?>
								</a>
							<?php else: ?>
								<?=htmlspecialchars(pprint_port($filterent['destination']['port']))?>
							<?php endif; ?>
						</td>
						<td>
							<?php if (isset($config['interfaces'][$filterent['gateway']]['descr'])):?>
								<?=str_replace('_', ' ', htmlspecialchars($config['interfaces'][$filterent['gateway']]['descr']))?>
							<?php else: ?>
								<?=htmlspecialchars(pprint_port($filterent['gateway']))?>
							<?php endif; ?>
						</td>
						<td>
							<?php
								if (isset($filterent['ackqueue']) && isset($filterent['defaultqueue'])) {
									$desc = str_replace('_', ' ', $filterent['ackqueue']);
									echo "<a href=\"firewall_shaper_queues.php?queue={$filterent['ackqueue']}&amp;action=show\">{$desc}</a>";
									$desc = str_replace('_', ' ', $filterent['defaultqueue']);
									echo "/<a href=\"firewall_shaper_queues.php?queue={$filterent['defaultqueue']}&amp;action=show\">{$desc}</a>";
								} else if (isset($filterent['defaultqueue'])) {
									$desc = str_replace('_', ' ', $filterent['defaultqueue']);
									echo "<a href=\"firewall_shaper_queues.php?queue={$filterent['defaultqueue']}&amp;action=show\">{$desc}</a>";
								} else {
									echo gettext("none");
								}
							?>
						</td>
						<td>
							<?php if ($printicon) { ?>
								<i class="fa fa-<?=$image?> <?=$dispcolor?>" title="<?=$alttext;?>"></i>
							<?php } ?>
							<?=$schedule_span_begin;?><?=str_replace('_', ' ', htmlspecialchars($filterent['sched']));?>&nbsp;<?=$schedule_span_end;?>
						</td>
						<td>
							<?=htmlspecialchars($filterent['descr']);?>
						</td>
						<td class="action-icons">
						<!-- <?=(isset($filterent['disabled']) ? 'enable' : 'disable')?> -->
							<a	class="fa fa-anchor icon-pointer" id="Xmove_<?=$filteri?>" title="<?=$XmoveTitle?>"></a>
							<a href="firewall_rules_edit.php?id=<?=$filteri;?>" class="fa fa-pencil" title="<?=gettext('Edit')?>"></a>
							<a href="firewall_rules_edit.php?dup=<?=$filteri;?>" class="fa fa-clone" title="<?=gettext('Copy')?>"></a>
<?php if (isset($filterent['disabled'])) {
?>
							<a href="?act=toggle&amp;if=<?=htmlspecialchars($if);?>&amp;id=<?=$filteri;?>" class="fa fa-check-square-o" title="<?=gettext('Enable')?>"></a>
<?php } else {
?>
							<a href="?act=toggle&amp;if=<?=htmlspecialchars($if);?>&amp;id=<?=$filteri;?>" class="fa fa-ban" title="<?=gettext('Disable')?>"></a>
<?php }
?>
							<a href="?act=del&amp;if=<?=htmlspecialchars($if);?>&amp;id=<?=$filteri;?>" class="fa fa-trash" title="<?=gettext('Delete this rule')?>"></a>
						</td>
					</tr>
<?php
		$nrules++;
	}
endforeach;

// There can be separator(s) after the last rule listed.
if ($seprows[$nrules]) {
	display_separator($separators, $nrules, $columns_in_table);
}
?>
				</tbody>
			</table>
		</div>
	</div>

<?php if ($nrules == 0): ?>
	<div class="alert alert-warning" role="alert">
		<p>
		<?php if ($_REQUEST['if'] == "FloatingRules"): ?>
			<?=gettext("No floating rules are currently defined.");?>
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
			<i class="fa fa-level-up icon-embed-btn"></i>
			<?=gettext("Add");?>
		</a>
		<a href="firewall_rules_edit.php?if=<?=htmlspecialchars($if);?>" role="button" class="btn btn-sm btn-success" title="<?=gettext('Add rule to the end of the list')?>">
			<i class="fa fa-level-down icon-embed-btn"></i>
			<?=gettext("Add");?>
		</a>
		<button name="del_x" type="submit" class="btn btn-danger btn-sm" value="<?=gettext("Delete selected rules"); ?>" title="<?=gettext('Delete selected rules')?>">
			<i class="fa fa-trash icon-embed-btn"></i>
			<?=gettext("Delete"); ?>
		</button>
		<button type="submit" id="order-store" name="order-store" class="btn btn-sm btn-primary" value="store changes" disabled title="<?=gettext('Save rule order')?>">
			<i class="fa fa-save icon-embed-btn"></i>
			<?=gettext("Save")?>
		</button>
		<button type="submit" id="addsep" name="addsep" class="btn btn-sm btn-warning" title="<?=gettext('Add separator')?>">
			<i class="fa fa-plus icon-embed-btn"></i>
			<?=gettext("Separator")?>
		</button>
	</nav>
</form>

<div class="infoblock">
	<div class="alert alert-info clearfix" role="alert"><div class="pull-left">
		<dl class="dl-horizontal responsive">
		<!-- Legend -->
			<dt><?=gettext('Legend')?></dt>				<dd></dd>
			<dt><i class="fa fa-check text-success"></i></dt>		<dd><?=gettext("Pass");?></dd>
			<dt><i class="fa fa-filter"></i></dt>	<dd><?=gettext("Match");?></dd>
			<dt><i class="fa fa-times text-danger"></i></dt>	<dd><?=gettext("Block");?></dd>
			<dt><i class="fa fa-hand-stop-o text-warning"></i></dt>		<dd><?=gettext("Reject");?></dd>
			<dt><i class="fa fa-tasks"></i></dt>	<dd> <?=gettext("Log");?></dd>
			<dt><i class="fa fa-cog"></i></dt>		<dd> <?=gettext("Advanced filter");?></dd>
			<dt><i class="fa fa-forward text-success"></i></dt><dd> <?=gettext("&quot;Quick&quot; rule. Applied immediately on match.")?></dd>
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

	printf(gettext("%sClick the anchor icon %s to move checked rules before the clicked row. Hold down " .
			"the shift key and click to move the rules after the clicked row."), '<br /><br />', '<i class="fa fa-anchor"></i>')
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

		// If so, change the icon to show the insetion point
		if (ruleselected) {
			if (e.shiftKey) {
				$(this).removeClass().addClass("fa fa-lg fa-arrow-down text-danger");
			} else {
				$(this).removeClass().addClass("fa fa-lg fa-arrow-up text-danger");
			}
		}
	}).mouseout(function(e) {
		$(this).removeClass().addClass("fa fa-anchor");
	});

	// Make rules sortable. Hiding the table before applying sortable, then showing it again is
	// a work-around for very slow sorting on FireFox
	$('table tbody.user-entries').hide();

	$('table tbody.user-entries').sortable({
		cursor: 'grabbing',
		update: function(event, ui) {
			$('#order-store').removeAttr('disabled');
			reindex_rules(ui.item.parent('tbody'));
			dirty = true;
		}
	});

	$('table tbody.user-entries').show();

	// Check all of the rule checkboxes so that their values are posted
	$('#order-store').click(function () {
		$('[id^=frc]').prop('checked', true);

		// Save the separator bar configuration
		save_separators();

		// Suppress the "Do you really want to leave the page" message
		saving = true;
	});

	// Provide a warning message if the user tries to change page before saving
	$(window).bind('beforeunload', function(){
		if ((!saving && dirty) || newSeperator) {
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
});
//]]>
</script>

<?php include("foot.inc");?>
