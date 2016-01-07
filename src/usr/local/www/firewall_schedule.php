<?php
/*
	firewall_schedule.php
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
##|*IDENT=page-firewall-schedules
##|*NAME=Firewall: Schedules
##|*DESCR=Allow access to the 'Firewall: Schedules' page.
##|*MATCH=firewall_schedule.php*
##|-PRIV

define('CLOCK', '<i class="fa fa-clock-o icon-black"></i>');

$dayArray = array (gettext('Mon'), gettext('Tues'), gettext('Wed'), gettext('Thur'), gettext('Fri'), gettext('Sat'), gettext('Sun'));
$monthArray = array (gettext('January'), gettext('February'), gettext('March'), gettext('April'), gettext('May'), gettext('June'), gettext('July'), gettext('August'), gettext('September'), gettext('October'), gettext('November'), gettext('December'));

require("guiconfig.inc");
require_once("filter.inc");
require("shaper.inc");

$pgtitle = array(gettext("Firewall"), gettext("Schedules"));

if (!is_array($config['schedules']['schedule'])) {
	$config['schedules']['schedule'] = array();
}

$a_schedules = &$config['schedules']['schedule'];

if ($_GET['act'] == "del") {
	if ($a_schedules[$_GET['id']]) {
		/* make sure rule is not being referenced by any nat or filter rules */
		$is_schedule_referenced = false;
		$referenced_by = false;
		$schedule_name = $a_schedules[$_GET['id']]['name'];

		if (is_array($config['filter']['rule'])) {
			foreach ($config['filter']['rule'] as $rule) {
				//check for this later once this is established
				if ($rule['sched'] == $schedule_name) {
					$referenced_by = $rule['descr'];
					$is_schedule_referenced = true;
					break;
				}
			}
		}

		if ($is_schedule_referenced == true) {
			$savemsg = sprintf(gettext("Cannot delete Schedule. Currently in use by %s"), $referenced_by);
		} else {
			unset($a_schedules[$_GET['id']]);
			write_config();
			header("Location: firewall_schedule.php");
			exit;
		}
	}
}

include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Schedules')?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed">
			<thead>
				<tr>
					<th><!--"Active" indicator--></th>
					<th><?=gettext("Name")?></th>
					<th><?=gettext("Range: Date / Times / Name")?></th>
					<th><?=gettext("Description")?></th>
					<th><!--Buttons--></th>
				</tr>
			</thead>
			<tbody>
<?php
$i = 0;
foreach ($a_schedules as $schedule):
	$schedstatus = filter_get_time_based_rule_status($schedule);
?>
				<tr>
					<td>
						<?=($schedstatus) ? '<a title="' . gettext("Schedule is currently active") . '">' . CLOCK . '</a>':''?>
					</td>
					<td>
						 <?=htmlspecialchars($schedule['name'])?>
					</td>
					<td>
<?php
	$first = true;
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
							$dayFriendly .= "<br />";
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
								$dayFriendly .= "<br />";
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

			$timeFriendly = $starttime . "-" . $stoptime;
			$description = $timerange['rangedescr'];

			print(($first ? '':'<br />') . $dayFriendly . ' / ' . $timeFriendly . ' / ' . $description);
		}
	$first = false;
	}
?>
					</td>

					<td>
						<?=htmlspecialchars($schedule['descr'])?>&nbsp;
					</td>

					<td>
						<a class="fa fa-pencil" title="<?=gettext("Edit schedule"); ?>" href="firewall_schedule_edit.php?id=<?=$i?>"></a>
						<a class="fa fa-trash" title="<?=gettext("Delete schedule")?>" href="firewall_schedule.php?act=del&amp;id=<?=$i?>"></a>

					</td>
				</tr>
<?php
	$i++;
endforeach;
?>
			</tbody>
		</table>
	</div>
</div>

<?=($i > 0) ? CLOCK . gettext(' Indicates that the schedule is currently active.'):''?>

<nav class="action-buttons">
	<a href="firewall_schedule_edit.php" class="btn btn-sm btn-success">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>

<div class="infoblock">
	<?=print_info_box(gettext('Schedules act as placeholders for time ranges to be used in Firewall Rules.'), 'info')?>
</div>

<?php

include("foot.inc");
