<?php
/* $Id$ */
/*
	firewall_rules.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved. 
 *  Copyright (c)  2004 Scott Ullrich
 *  Copyright (c)  2003-2004 Manuel Kasper <mk@neon1.net>
 *	Originally part of pfSense (https://www.pfsense.org)
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
	pfSense_MODULE: filter
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

$pgtitle = array(gettext("Firewall"), gettext("Rules"));
$shortcut_section = "firewall";

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
		$iflist['l2tp'] = "L2TP VPN";
	}
}

if ($config['pptpd']['mode'] == "server") {
	if (have_ruleint_access("pptp")) {
		$iflist['pptp'] = "PPTP VPN";
	}
}

if (is_array($config['pppoes']['pppoe'])) {
	foreach ($config['pppoes']['pppoe'] as $pppoes) {
		if (($pppoes['mode'] == 'server') && have_ruleint_access("pppoe")) {
			$iflist['pppoe'] = "PPPoE Server";
		}
	}
}

/* add ipsec interfaces */
if (isset($config['ipsec']['enable']) || isset($config['ipsec']['client']['enable'])) {
	if (have_ruleint_access("enc0")) {
		$iflist["enc0"] = "IPsec";
	}
}

/* add openvpn/tun interfaces */
if ($config['openvpn']["openvpn-server"] || $config['openvpn']["openvpn-client"]) {
	$iflist["openvpn"] = "OpenVPN";
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

		$savemsg = sprintf(gettext("The settings have been applied. The firewall rules are now reloading in the background.<br />You can also %s monitor %s the reload progress"), "<a href='status_filter_reload.php'>", "</a>");
	}
}

if ($_GET['act'] == "del") {
	if ($a_filter[$_GET['id']]) {
		if (!empty($a_filter[$_GET['id']]['associated-rule-id'])) {
			delete_nat_association($a_filter[$_GET['id']]['associated-rule-id']);
		}
		unset($a_filter[$_GET['id']]);
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

if ($_GET['act'] == "toggle") {
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
} else {
	/* update rule order, POST[rule] is an array of ordered IDs */
	if (is_array($_POST['rule']) && !empty($_POST['rule'])) {
		$a_filter_new = array();

		// if a rule is not in POST[rule], it has been deleted by the user
		foreach ($_POST['rule'] as $id)
			$a_filter_new[] = $a_filter[$id];

		$a_filter = $a_filter_new;
		if (write_config()) {
			mark_subsystem_dirty('filter');
		}
		header("Location: firewall_rules.php?if=" . htmlspecialchars($if));
		exit;
	}
}

include("head.inc");
$nrules = 0;

if ($savemsg)
	print_info_box($savemsg, 'success');

if (is_subsystem_dirty('filter'))
	print_info_box_np(gettext("The firewall rule configuration has been changed.") . "<br />" . gettext("You must apply the changes in order for them to take effect."), "apply", "", true);

$tab_array = array(array(gettext("Floating"), ("FloatingRules" == $if), "firewall_rules.php?if=FloatingRules"));

foreach ($iflist as $ifent => $ifname)
	$tab_array[] = array($ifname, ($ifent == $if), "firewall_rules.php?if={$ifent}");

display_top_tabs($tab_array);

?>
<form method="post">
	<div class="panel panel-default">
		<div class="panel-heading"><?=gettext("Rules (Drag to change order)")?></div>
		<div id="mainarea" class="table-responsive panel-body">
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th><!-- status icons --></th>
						<th><?=gettext("Proto");?></th>
						<th><?=gettext("Source");?></th>
						<th><?=gettext("Port");?></th>
						<th><?=gettext("Destination");?></th>
						<th><?=gettext("Port");?></th>
						<th><?=gettext("Gateway");?></th>
						<th><?=gettext("Queue");?></th>
						<th><?=gettext("Schedule");?></th>
						<th><?=gettext("Description");?></th>
						<th><!-- buttons --></th>
					</tr>
				</thead>
				<tbody>
<?php
		// Show the anti-lockout rule if it's enabled, and we are on LAN with an if count > 1, or WAN with an if count of 1.
	if (!isset($config['system']['webgui']['noantilockout']) &&
	    (((count($config['interfaces']) > 1) && ($if == 'lan')) ||
	     ((count($config['interfaces']) == 1) && ($if == 'wan')))):
		$alports = implode('<br />', filter_get_antilockout_ports(true));
?>
					<tr id="antilockout">
						<td title="<?=gettext("traffic is passed")?>"><i class="icon icon-ok"></i></td>
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
							<a href="system_advanced_admin.php" class="btn btn-xs btn-primary">edit</a>
						</td>
					</tr>
<?php endif;?>
<?php if (isset($config['interfaces'][$if]['blockpriv'])): ?>
					<tr id="frrfc1918">
						<td title="<?=gettext("traffic is blocked")?>"><i class="icon icon-remove"></i></td>
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
							<a href="system_advanced_admin.php" class="btn btn-xs btn-primary" title="<?=gettext("edit rule");?>">edit</a>
						</td>
					</tr>
<?php endif;?>
<?php if (isset($config['interfaces'][$if]['blockbogons'])): ?>
					<tr id="frrfc1918">
						<td title="<?=gettext("traffic is blocked")?>"><i class="icon icon-remove"></i></td>
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
							<a href="system_advanced_admin.php" class="btn btn-xs btn-primary">edit</a>
						</td>
					</tr>
<?php endif;?>
			</tbody>

			<tbody class="user-entries">
<?php for ($i = 0; isset($a_filter[$i]); $i++):
	$filterent = $a_filter[$i];
	
	if ($filterent['interface'] != $if && !isset($filterent['floating']))
		continue;
	if (isset($filterent['floating']) && "FloatingRules" != $if)
		continue;

	$nrules++;
?>
					<tr<?=(isset($filterent['disabled']) ? ' class="disabled"' : '')?>>
						<td title="<?=gettext("traffic is ").$filterent['type']."ed"?>">
							<input type="hidden" name="rule[]" value="<?=$i?>" />

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
					<i class="icon icon-<?=$iconfn?>"></i>
	<?php
		$isadvset = firewall_check_for_advanced_options($filterent);
		if ($isadvset)
			print '<i class="icon icon-cog" title="'. gettext("advanced setting") .': '. $isadvset .'"></i>';

		if (isset($filterent['log']))
			print '<i class="icon icon-tasks" title="'. gettext("traffic is logged") .'"></i>';
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
			foreach ($a_schedules as $schedule)
			{
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

									if (!$firstDayFound)
									{
										$firstDay = $day;
										$firstmonth = $month;
										$firstDayFound = true;
									}

									$currentDay = $day;
									$nextDay = $tempdayarray[$arraycounter+1];
									$currentDay++;
									if (($currentDay != $nextDay) || ($tempmontharray[$arraycounter] != $tempmontharray[$arraycounter+1])) {
										if ($firstPrint)
											$dayFriendly .= ", ";
										$currentDay--;
										if ($currentDay != $firstDay)
											$dayFriendly .= $monthArray[$firstmonth-1] . " " . $firstDay . " - " . $currentDay ;
										else
											$dayFriendly .=	 $monthArray[$month-1] . " " . $day;
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
								foreach ($tempFriendlyDayArray as $day) {
									if ($day != "") {
										if (!$firstDayFound)
										{
											$firstDay = $tempFriendlyDayArray[$counter];
											$firstDayFound = true;
										}
										$currentDay =$tempFriendlyDayArray[$counter];
										//get next day
										$nextDay = $tempFriendlyDayArray[$counter+1];
										$currentDay++;
										if ($currentDay != $nextDay) {
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
					#FIXME
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
				echo ' <span style="cursor: help;" title="ICMP type: ' .
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
							<?=htmlspecialchars(pprint_address($filterent['destination']))?>
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
								<?=htmlspecialchars(pprint_port($filterent['gateway']))?>
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
						<td>
							<?=htmlspecialchars($filterent['descr']);?>
						</td>
						<td>
							<a href="firewall_rules_edit.php?id=<?=$i;?>" class="btn btn-xs btn-primary">edit</a>
							<a href="firewall_rules_edit.php?dup=<?=$i;?>" class="btn btn-xs btn-default">copy</a>
							<a href="?act=toggle&amp;if=<?=htmlspecialchars($if);?>&amp;id=<?=$i;?>" class="btn btn-xs btn-warning"><?=(isset($filterent['disabled']) ? 'enable' : 'disable')?></a>
							<a href="?act=del&amp;if=<?=htmlspecialchars($if);?>&amp;id=<?=$i;?>" class="btn btn-xs btn-danger">delete</a>
						</td>
					</tr>
<?php
		endfor;
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
			<?=gettext("All incoming connections on this interface will be blocked until you add pass rules.");?>
		<?php endif;?>
			<?=gettext("Click the button to add a new rule.");?>
		</p>
	</div>
<?php endif;?>

	<nav class="action-buttons">
		<input type="submit" id="order-store" class="btn btn-sm btn-primary" value="store changes" disabled="disabled" />
		<a href="firewall_rules_edit.php?if=<?=htmlspecialchars($if);?>" role="button" class="btn btn-sm btn-success">
			<?=gettext("add new");?>
		</a>
	</nav>
</form>
<!-- Legend -->
<div>
	<dl class="dl-horizontal responsive">
		<dt><?=gettext('Legend')?></dt>				<dd></dd>
		<dt><i class="icon icon-ok"></i></dt>		<dd><?=gettext("pass");?></dd>
		<dt><i class="icon icon-filter"></i></dt>	<dd><?=gettext("match");?></dd>
		<dt><i class="icon icon-remove"></i></dt>	<dd><?=gettext("block");?></dd>
		<dt><i class="icon icon-fire"></i></dt>		<dd><?=gettext("reject");?></dd>
		<dt><i class="icon icon-tasks"></i></dt>	<dd> <?=gettext("log");?></dd>
		<dt><i class="icon icon-cog"></i></dt>		<dd> <?=gettext("advanced filter");?></dd>
	</dl>
</div>

<?php
if ("FloatingRules" != $if)
	print_info_box(gettext("Rules are evaluated on a first-match basis (i.e. " .
		"the action of the first rule to match a packet will be executed). ") . '<br />' .
		gettext("This means that if you use block rules, you'll have to pay attention " .
		"to the rule order. Everything that isn't explicitly passed is blocked " .
		"by default. "));
else
	print_info_box(gettext("Floating rules are evaluated on a first-match basis (i.e. " .
		"the action of the first rule to match a packet will be executed) only " .
		"if the 'quick' option is checked on a rule. Otherwise they will only apply if no " .
		"other rules match. Pay close attention to the rule order and options " .
		"chosen. If no rule here matches, the per-interface or default rules are used. "));

?>

<script>
events.push(function() {
	// Make rules sortable
	$('table tbody.user-entries').sortable({
		cursor: 'grabbing',
		update: function(event, ui) {
			$('#order-store').removeAttr('disabled');
		}
	});

	// Replace direct delete with quicker front-end action
/*FIXME: event ordering
	$('.btn-danger').on('click', function(e){
		$(this).parents('tr').remove();

		$('#order-store').removeAttr('disabled');
	});
*/});
</script>
<?php include("foot.inc");?>