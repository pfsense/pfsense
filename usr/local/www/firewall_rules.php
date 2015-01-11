<?php
/* $Id$ */
/*
	firewall_rules.php
	part of pfSense (https://www.pfsense.org)
	Copyright (C) 2005 Scott Ullrich (sullrich@gmail.com)
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
/*
	pfSense_MODULE:	filter
*/

##|+PRIV
##|*IDENT=page-firewall-rules
##|*NAME=Firewall: Rules page
##|*DESCR=Allow access to the 'Firewall: Rules' page.
##|*MATCH=firewall_rules.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

$pgtitle = array(gettext("Firewall"),gettext("Rules"));
$shortcut_section = "firewall";

function delete_nat_association($id) {
	global $config;

	if (!$id || !is_array($config['nat']['rule']))
		return;

	$a_nat = &$config['nat']['rule'];

	foreach ($a_nat as &$natent)
		if ($natent['associated-rule-id'] == $id)
			$natent['associated-rule-id'] = '';
}

if (!is_array($config['filter']['rule'])) {
	$config['filter']['rule'] = array();
}
filter_rules_sort();
$a_filter = &$config['filter']['rule'];

$if = $_GET['if'];
if ($_POST['if'])
	$if = $_POST['if'];

$ifdescs = get_configured_interface_with_descr();

// Drag and drop reordering
if($_REQUEST['dragdroporder']) {
	// First create a new ruleset array and tmp arrays
	$a_filter_before = array();
	$a_filter_order = array();
	$a_filter_order_tmp = array();
	$a_filter_after = array();
	$found = false;
	$drag_order = $_REQUEST['dragtable'];
	// Next traverse through rules building a new order for interface
	for ($i = 0; isset($a_filter[$i]); $i++) {
		if(( $_REQUEST['if'] == "FloatingRules" && isset($a_filter[$i]['floating']) ) || ( $a_filter[$i]['interface'] == $_REQUEST['if'] && !isset($a_filter[$i]['floating']) )) {
			$a_filter_order_tmp[] = $a_filter[$i];
			$found = true;
		} else if (!$found)
			$a_filter_before[] = $a_filter[$i];
		else
			$a_filter_after[] = $a_filter[$i];
	}
	// Reorder rules with the posted order
	for ($i = 0; $i<count($drag_order); $i++)
		$a_filter_order[] = $a_filter_order_tmp[$drag_order[$i]];
	// In case $drag_order didn't account for some rules, make sure we don't lose them
	if(count($a_filter_order) < count($a_filter_order_tmp)) {
		for ($i = 0; $i<count($a_filter_order_tmp); $i++)
			if(!in_array($i, $drag_order))
				$a_filter_order[] = $a_filter_order_tmp[$i];
	}
	// Overwrite filter rules with newly created items
	$config['filter']['rule'] = array_merge($a_filter_before, $a_filter_order, $a_filter_after);
	// Write configuration
	$config = write_config(gettext("Drag and drop firewall rules ordering update."));
	// Redirect back to page
	mark_subsystem_dirty('filter');
	$undo = array();
	foreach($_REQUEST['dragtable'] as $dt)
		$undo[] = "";
	$counter = 0;
	foreach($_REQUEST['dragtable'] as $dt) {
		$undo[$dt] = $counter;
		$counter++;
	}
	foreach($undo as $dt)
		$undotxt .= "&dragtable[]={$dt}";
	header("Location: firewall_rules.php?if=" . $_REQUEST['if'] . "&undodrag=true" . $undotxt);
	exit;
}

/* add group interfaces */
if (is_array($config['ifgroups']['ifgroupentry']))
	foreach($config['ifgroups']['ifgroupentry'] as $ifgen)
		if (have_ruleint_access($ifgen['ifname']))
			$iflist[$ifgen['ifname']] = $ifgen['ifname'];

foreach ($ifdescs as $ifent => $ifdesc)
	if(have_ruleint_access($ifent))
		$iflist[$ifent] = $ifdesc;

if ($config['l2tp']['mode'] == "server")
	if(have_ruleint_access("l2tp"))
		$iflist['l2tp'] = "L2TP VPN";

if ($config['pptpd']['mode'] == "server")
	if(have_ruleint_access("pptp"))
		$iflist['pptp'] = "PPTP VPN";

if (is_array($config['pppoes']['pppoe'])) {
	foreach ($config['pppoes']['pppoe'] as $pppoes)
		if (($pppoes['mode'] == 'server') && have_ruleint_access("pppoe"))
			$iflist['pppoe'] = "PPPoE Server";
}

/* add ipsec interfaces */
if (isset($config['ipsec']['enable']) || isset($config['ipsec']['client']['enable']))
	if(have_ruleint_access("enc0"))
		$iflist["enc0"] = "IPsec";

/* add openvpn/tun interfaces */
if  ($config['openvpn']["openvpn-server"] || $config['openvpn']["openvpn-client"])
	$iflist["openvpn"] = "OpenVPN";

pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/interfaces_override");

if (!$if || !isset($iflist[$if])) {
	if ("any" == $if)
		$if = "FloatingRules";
	else if ("FloatingRules" != $if) {
		if (isset($iflist['wan']))
			$if = "wan";
		else
			$if = "FloatingRules";
	}
}

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;
		$retval = filter_configure();

		clear_subsystem_dirty('filter');

		pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/apply");

		$savemsg = sprintf(gettext("The settings have been applied. The firewall rules are now reloading in the background.<br />You can also %s monitor %s the reload progress"),"<a href='status_filter_reload.php'>","</a>");
	}
}

if ($_GET['act'] == "del") {
	if ($a_filter[$_GET['id']]) {
		if (!empty($a_filter[$_GET['id']]['associated-rule-id'])) {
			delete_nat_association($a_filter[$_GET['id']]['associated-rule-id']);
		}
		unset($a_filter[$_GET['id']]);
		if (write_config())
			mark_subsystem_dirty('filter');
		header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
		exit;
	}
}

// Handle save msg if defined
if($_REQUEST['savemsg'])
	$savemsg = htmlentities($_REQUEST['savemsg']);

if (isset($_POST['del_x'])) {
	/* delete selected rules */
	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		foreach ($_POST['rule'] as $rulei) {
			delete_nat_association($a_filter[$rulei]['associated-rule-id']);
			unset($a_filter[$rulei]);
		}
		if (write_config())
			mark_subsystem_dirty('filter');
		header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
		exit;
	}
} else if ($_GET['act'] == "toggle") {
	if ($a_filter[$_GET['id']]) {
		if(isset($a_filter[$_GET['id']]['disabled']))
			unset($a_filter[$_GET['id']]['disabled']);
		else
			$a_filter[$_GET['id']]['disabled'] = true;
		if (write_config())
			mark_subsystem_dirty('filter');
		header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
		exit;
	}
} else {
	/* yuck - IE won't send value attributes for image buttons, while Mozilla does -
	   so we use .x/.y to fine move button clicks instead... */
	unset($movebtn);
	foreach ($_POST as $pn => $pd) {
		if (preg_match("/move_(\d+)_x/", $pn, $matches)) {
			$movebtn = $matches[1];
			break;
		}
	}
	/* move selected rules before this rule */
	if (isset($movebtn) && is_array($_POST['rule']) && count($_POST['rule'])) {
		$a_filter_new = array();

		/* copy all rules < $movebtn and not selected */
		for ($i = 0; $i < $movebtn; $i++) {
			if (!in_array($i, $_POST['rule']))
				$a_filter_new[] = $a_filter[$i];
		}

		/* copy all selected rules */
		for ($i = 0; $i < count($a_filter); $i++) {
			if ($i == $movebtn)
				continue;
			if (in_array($i, $_POST['rule']))
				$a_filter_new[] = $a_filter[$i];
		}

		/* copy $movebtn rule */
		if ($movebtn < count($a_filter))
			$a_filter_new[] = $a_filter[$movebtn];

		/* copy all rules > $movebtn and not selected */
		for ($i = $movebtn+1; $i < count($a_filter); $i++) {
			if (!in_array($i, $_POST['rule']))
				$a_filter_new[] = $a_filter[$i];
		}

		$a_filter = $a_filter_new;
		if (write_config())
			mark_subsystem_dirty('filter');
		header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
		exit;
	}
}

$pgtitle = array(gettext("System"),gettext("General Setup"));
include("head.inc");
$nrules = 0;

?>
<form action="firewall_rules.php" method="post">

<?php if ($savemsg) print_info_box($savemsg);?>
<?php if (is_subsystem_dirty('filter')): ?><p>
<?php
if($_REQUEST['undodrag']) {
	foreach($_REQUEST['dragtable'] as $dt)
		$dragtable .= "&dragtable[]={$dt}";
	print_info_box_np_undo(gettext("The firewall rule configuration has been changed.<br />You must apply the changes in order for them to take effect."), "apply" , gettext("Apply changes") , "firewall_rules.php?if={$_REQUEST['if']}&dragdroporder=true&{$dragtable}");
} else {
	print_info_box_np(gettext("The firewall rule configuration has been changed.<br />You must apply the changes in order for them to take effect."));
}
?>
<br />
<?php endif;?>
<?php
	pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/before_table");
?>

<?php
/* active tabs */
$tab_array = array(array(gettext("Floating"), ("FloatingRules" == $if), "firewall_rules.php?if=FloatingRules"));

foreach ($iflist as $ifent => $ifname) {
	$tab_array[] = array($ifname, ($ifent == $if), "firewall_rules.php?if={$ifent}");
}

display_top_tabs($tab_array);
?>
<table class="table table-striped">
<?php
	pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/before_first_tr");
?>
	<thead>
	<tr>
		<th colspan="2"></th>
		<th><?=gettext("ID");?></th>
<?php
	pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/pre_id_tablehead");
?>
		<th><?=gettext("Proto");?></th>
		<th><?=gettext("Source");?></th>
		<th><?=gettext("Port");?></th>
		<th><?=gettext("Destination");?></th>
		<th><?=gettext("Port");?></th>
		<th><?=gettext("Gateway");?></th>
		<th><?=gettext("Queue");?></th>
		<th><?=gettext("Schedule");?></th>
<?php
	pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/pre_desc_tablehead");
?>
		<th><?=gettext("Description");?></th>
	</tr>
	</thead>
	<tbody>
	<?php   // Show the anti-lockout rule if it's enabled, and we are on LAN with an if count > 1, or WAN with an if count of 1.
		if (!isset($config['system']['webgui']['noantilockout']) &&
			(((count($config['interfaces']) > 1) && ($if == 'lan'))
			|| ((count($config['interfaces']) == 1) && ($if == 'wan')))):

			$alports = implode('<br />', filter_get_antilockout_ports(true));
	?>
	<tr id="antilockout">
		<td></td>
		<td><i class="icon icon-ok-circle"></i></td>
		<td></td>
		<?php
			pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/pre_id_tr_antilockout");
		?>
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
			<a href="system_advanced_admin.php" title="<?=gettext("edit rule");?>"><i class="icon icon-pencil"></i></a>
			<a href="#" title="<?=gettext("copy this rule");?>"><i class="icon icon-random"></i></a>
		</td>
	</tr>
<?php endif;?>

<?php if (isset($config['interfaces'][$if]['blockpriv'])): ?>
	<tr id="frrfc1918">
		<td></td>
		<td><i class="icon icon-remove-circle"></i></td>
		<td></td>
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
			<a href="system_advanced_admin.php" title="<?=gettext("edit rule");?>"><i class="icon icon-pencil"></i></a>
			<a href="#" title="<?=gettext("copy this rule");?>"><i class="icon icon-random"></i></a>
		</td>
	</tr>
<?php endif;?>
<?php if (isset($config['interfaces'][$if]['blockbogons'])): ?>
	<tr id="frrfc1918">
		<td></td>
		<td><i class="icon icon-remove-circle"></i></td>
		<td></td>
		<td>*</td>
		<td><?=gettext("Reserved/not assigned by IANA");?></td>
		<td>*</td>
		<td>*</td>
		<td>*</td>
		<td>*</td>
		<td>*</td>
		<td>*</td>
		<td><?=gettext("Block bogon networks");?></td>
		<td>
			<a href="system_advanced_admin.php" title="<?=gettext("edit rule");?>"><i class="icon icon-pencil"></i></a>
			<a href="#" title="<?=gettext("copy this rule");?>"><i class="icon icon-random"></i></a>
		</td>
	</tr>
<?php endif;?>

<?php for ($i = 0; isset($a_filter[$i]); $i++):
	pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/row_start");
	$filterent = $a_filter[$i];
	if ($filterent['interface'] != $if && !isset($filterent['floating']))
		continue;
	if (isset($filterent['floating']) && "FloatingRules" != $if)
		continue;

	$nrules++;
?>
	<tr id="fr<?=$i?>"<?=(isset($filterent['disabled']) ? ' class="disabled"' : '')?>>
	<td>
		<input type="checkbox" id="frc<?=$i?>" name="rule[]" value="<?=$i?>" />
	</td>
	<td>
	<?php
		if ($filterent['type'] == "block")
			$iconfn = "remove";
		else if ($filterent['type'] == "reject")
			$iconfn = "fire";
		else if ($filterent['type'] == "match")
			$iconfn = "filter";
		else
			$iconfn = "ok";
	?>
	<a href="?if=<?=htmlspecialchars($if);?>&amp;act=toggle&amp;id=<?=$i;?>" title="Toggle">
		<i class="icon icon-<?=$iconfn?>"></i>
	</a>
	<?php
		$isadvset = firewall_check_for_advanced_options($filterent);
		if($isadvset)
			print '<i class="icon icon-cog" title="'. gettext("advanced settings set") .': '. $isadvset .'"></i>';

		if (isset($filterent['log'])):
			print '<i class="icon icon-tasks" title="'. gettext("traffic is logged") .'"></i>';
	?>

<?php endif;?>
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
		$dayArray = array (gettext('Mon'),gettext('Tues'),gettext('Wed'),gettext('Thur'),gettext('Fri'),gettext('Sat'),gettext('Sun'));
		$monthArray = array (gettext('January'),gettext('February'),gettext('March'),gettext('April'),gettext('May'),gettext('June'),gettext('July'),gettext('August'),gettext('September'),gettext('October'),gettext('November'),gettext('December'));
		if($config['schedules']['schedule'] <> "" and is_array($config['schedules']['schedule'])) {
			foreach ($a_schedules as $schedule)
			{
				if ($schedule['name'] == $filterent['sched'] ){
					$schedstatus = filter_get_time_based_rule_status($schedule);

					foreach($schedule['timerange'] as $timerange) {
						$tempFriendlyTime = "";
						$tempID = "";
						$firstprint = false;
						if ($timerange){
							$dayFriendly = "";
							$tempFriendlyTime = "";

							//get hours
							$temptimerange = $timerange['hour'];
							$temptimeseparator = strrpos($temptimerange, "-");

							$starttime = substr ($temptimerange, 0, $temptimeseparator);
							$stoptime = substr ($temptimerange, $temptimeseparator+1);

							if ($timerange['month']){
								$tempmontharray = explode(",", $timerange['month']);
								$tempdayarray = explode(",",$timerange['day']);
								$arraycounter = 0;
								$firstDayFound = false;
								$firstPrint = false;
								foreach ($tempmontharray as $monthtmp){
									$month = $tempmontharray[$arraycounter];
									$day = $tempdayarray[$arraycounter];

									if (!$firstDayFound)
									{
										$firstDay = $day;
										$firstmonth = $month;
										$firstDayFound = true;
									}

									$currentDay = $day;
									$nextDay = $tempdayarray[$arraycounter+1];
									$currentDay++;
									if (($currentDay != $nextDay) || ($tempmontharray[$arraycounter] != $tempmontharray[$arraycounter+1])){
										if ($firstPrint)
											$dayFriendly .= ", ";
										$currentDay--;
										if ($currentDay != $firstDay)
											$dayFriendly .= $monthArray[$firstmonth-1] . " " . $firstDay . " - " . $currentDay ;
										else
											$dayFriendly .=  $monthArray[$month-1] . " " . $day;
										$firstDayFound = false;
										$firstPrint = true;
									}
									$arraycounter++;
								}
							}
							else
							{
								$tempdayFriendly = $timerange['position'];
								$firstDayFound = false;
								$tempFriendlyDayArray = explode(",", $tempdayFriendly);
								$currentDay = "";
								$firstDay = "";
								$nextDay = "";
								$counter = 0;
								foreach ($tempFriendlyDayArray as $day){
									if ($day != ""){
										if (!$firstDayFound)
										{
											$firstDay = $tempFriendlyDayArray[$counter];
											$firstDayFound = true;
										}
										$currentDay =$tempFriendlyDayArray[$counter];
										//get next day
										$nextDay = $tempFriendlyDayArray[$counter+1];
										$currentDay++;
										if ($currentDay != $nextDay){
											if ($firstprint)
												$dayFriendly .= ", ";
											$currentDay--;
											if ($currentDay != $firstDay)
												$dayFriendly .= $dayArray[$firstDay-1] . " - " . $dayArray[$currentDay-1];
											else
												$dayFriendly .= $dayArray[$firstDay-1];
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
					$sched_caption_escaped = str_replace("'", "\'", $schedule['descr']);
					$schedule_span_begin = "<span style=\"cursor: help;\" onmouseover=\"domTT_activate(this, event, 'content', '<h1>{$sched_caption_escaped}</h1><p>{$sched_content}</p>', 'trail', true, 'delay', 0, 'fade', 'both', 'fadeMax', 93, 'styleClass', 'niceTitle');\" onmouseout=\"this.style.color = ''; domTT_mouseout(this, event);\"><u>";
					$schedule_span_end = "</u></span>";
				}
			}
		}
		$printicon = false;
		$alttext = "";
		$image = "";
		if (!isset($filterent['disabled'])) {
			if ($schedstatus) {
				if ($iconfn == "block" || $iconfn == "reject") {
					$image = "icon_block";
					$alttext = gettext("Traffic matching this rule is currently being denied");
				} else {
					$image = "icon_pass";
					$alttext = gettext("Traffic matching this rule is currently being allowed");
				}
				$printicon = true;
			} else if ($filterent['sched']) {
				if ($iconfn == "block" || $iconfn == "reject")
					$image = "icon_block_d";
				else
					$image = "icon_block";
				$alttext = gettext("This rule is not currently active because its period has expired");
				$printicon = true;
			}
		}
	?>
	<td><?=$filterent['id']?></td>
	<?php
		pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/pre_id_tr");
	?>
	<td>
	<?php
		if (isset($filterent['ipprotocol'])) {
			switch($filterent['ipprotocol']) {
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
				echo ' <span style="cursor: help;" title="ICMP type: ' .
					( $filterent['ipprotocol'] == "inet6" ?  $icmp6types[$filterent['icmptype']] : $icmptypes[$filterent['icmptype']] ) .
					'"><u>';
				echo $filterent['icmptype'];
				echo '</u></span>';
			}
		} else echo "*";
	?>
	</td>
	<td>
		<?php if (isset($alias['src'])): ?>
			<a href="/firewall_aliases_edit.php?id=<?=$alias['src']?>" data-toggle="popover" data-trigger="hover focus" title="Alias details" data-content="<?=alias_info_popup($alias['src'])?>" data-html="true">
		<?php endif; ?>
		<?=htmlspecialchars(pprint_address($filterent['source']))?>
		<?php if (isset($alias['src'])): ?>
			<i class='icon icon-pencil'></i></a>
		<?php endif; ?>
	</td>
	<td>
		<?php if (isset($alias['srcport'])): ?>
			<a href="/firewall_aliases_edit.php?id=<?=$alias['srcport']?>" data-toggle="popover" data-trigger="hover focus" title="Alias details" data-content="<?=alias_info_popup($alias['srcport'])?>" data-html="true">
		<?php endif; ?>
		<?=htmlspecialchars(pprint_port($filterent['source']['port']))?>
		<?php if (isset($alias['srcport'])): ?>
			<i class='icon icon-pencil'></i></a>
		<?php endif; ?>
	</td>
	<td>
		<?php if (isset($alias['dst'])): ?>
			<a href="/firewall_aliases_edit.php?id=<?=$alias['dst']?>" data-toggle="popover" data-trigger="hover focus" title="Alias details" data-content="<?=alias_info_popup($alias['dstport'])?>" data-html="true">
		<?php endif; ?>
		<?=htmlspecialchars(pprint_address($filterent['destination']['address']))?>
		<?php if (isset($alias['dst'])): ?>
			<i class='icon icon-pencil'></i></a>
		<?php endif; ?>
	</td>
	<td>
		<?php if (isset($alias['dstport'])): ?>
			<a href="/firewall_aliases_edit.php?id=<?=$alias['dstport']?>" data-toggle="popover" data-trigger="hover focus" title="Alias details" data-content="<?=alias_info_popup($alias['dstport'])?>" data-html="true">
		<?php endif; ?>
		<?=htmlspecialchars(pprint_port($filterent['destination']['port']))?>
		<?php if (isset($alias['dstport'])): ?>
			<i class='icon icon-pencil'></i></a>
		<?php endif; ?>
	</td>
	<td>
		<?php if (isset($config['interfaces'][$filterent['gateway']]['descr'])):?>
			<?=htmlspecialchars($config['interfaces'][$filterent['gateway']]['descr'])?>
		<?php else: ?>
			<?=htmlspecialchars(pprint_port($filterent['gateway']))?><a>
		<?php endif; ?>
	</td>
	<td>
	<?php
		if (isset($filterent['ackqueue']) && isset($filterent['defaultqueue'])) {
			$desc = $filterent['ackqueue'] ;
			echo "<a href=\"firewall_shaper_queues.php?queue={$filterent['ackqueue']}&amp;action=show\">{$desc}</a>";
			$desc = $filterent['defaultqueue'];
			echo "/<a href=\"firewall_shaper_queues.php?queue={$filterent['defaultqueue']}&amp;action=show\">{$desc}</a>";
		} else if (isset($filterent['defaultqueue'])) {
			$desc = $filterent['defaultqueue'];
			echo "<a href=\"firewall_shaper_queues.php?queue={$filterent['defaultqueue']}&amp;action=show\">{$desc}</a>";
		} else
			echo gettext("none");
	?>
	</td>
	<td>
		<?php if ($printicon) { ?><img src="./themes/<?= $g['theme'];?>/images/icons/<?=$image;?>.gif" title="<?=$alttext;?>" border="0" alt="icon" /><?php } ?>
		<?=$schedule_span_begin;?><?=htmlspecialchars($filterent['sched']);?>&nbsp;<?=$schedule_span_end;?>
	</td>
	<?php
		pfSense_handle_custom_code("/usr/local/pkg/firewall_rules/pre_descr_tr");
	?>
	<td>
		<?=htmlspecialchars($filterent['descr']);?>
	</td>
	<td>
		<a href="firewall_rules_edit.php?id=<?=$i;?>" title="<?=gettext("edit rule");?>"><i class="icon icon-pencil"></i></a>
		<a href="firewall_rules_edit.php?dup=<?=$i;?>" title="<?=gettext("copy this rule");?>"><i class="icon icon-random"></i></a>
		<a href="firewall_rules.php?act=del&amp;if=<?=htmlspecialchars($if);?>&amp;id=<?=$i;?>"><i class="icon icon-delete"></i></a>
	</td>
	</tr>
	<?php endfor;?>
</tbody>
</table>

<?php if ($nrules == 0): ?>
	<div class="alert alert-warning" role="alert">
		<?php if ($_REQUEST['if'] == "FloatingRules"): ?>
			<?=gettext("No floating rules are currently defined.");?>
		<?php else: ?>
			<?=gettext("No rules are currently defined for this interface");?><br />
			<?=gettext("All incoming connections on this interface will be blocked until you add pass rules.");?>
		<?php endif;?>
			</p>
			<?=gettext("Click the button to add a new rule.");?></span>
	</div>
<?php endif;?>
</tbody>
</table>

<a href="firewall_rules_edit.php?if=<?=htmlspecialchars($if);?>" role="button" class="btn btn-success">
	<?=gettext("add new rule");?>
</a>
<?php if ($i > 0): ?>
	<a href="#" role="button" class="btn btn-danger">
		<?=gettext("delete selected rules");?>
	</a>
	<!-- onclick="return confirm('<?=gettext('Do you really want to delete the selected rules?');?>')" />-->
<?php endif;?>

<h2>Legend</h2>
<ul>
	<li><i class="icon icon-ok"></i> <?=gettext("pass");?></li>
	<li><i class="icon icon-filter"></i> <?=gettext("match");?></li>
	<li><i class="icon icon-remove"></i> <?=gettext("block");?></li>
	<li><i class="icon icon-fire"></i> <?=gettext("reject");?></li>
	<li><i class="icon icon-tasks"></i> <?=gettext("log");?></li>
	<li><i class="icon icon-cog"></i> <?=gettext("advanced filter");?></li>
</ul>

<p>
<?php if ("FloatingRules" != $if): ?>
<?=gettext("Rules are evaluated on a first-match basis (i.e. " .
	"the action of the first rule to match a packet will be executed). " .
	"This means that if you use block rules, you'll have to pay attention " .
	"to the rule order. Everything that isn't explicitly passed is blocked " .
	"by default. ");?>
<?php else: ?>
<?=gettext("Floating rules are evaluated on a first-match basis (i.e. " .
	"the action of the first rule to match a packet will be executed) only " .
	"if the 'quick' option is checked on a rule. Otherwise they will only apply if no " .
	"other rules match. Pay close attention to the rule order and options " .
	"chosen. If no rule here matches, the per-interface or default rules are used. ");?>
<?php endif;?>
</p>
	<input type="hidden" name="if" value="<?=htmlspecialchars($if);?>" />
</form>
<?php include("foot.inc");?>