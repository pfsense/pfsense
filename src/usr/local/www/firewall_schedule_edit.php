<?php
/*
 * firewall_schedule_edit.php
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
##|*IDENT=page-firewall-schedules-edit
##|*NAME=Firewall: Schedules: Edit
##|*DESCR=Allow access to the 'Firewall: Schedules: Edit' page.
##|*MATCH=firewall_schedule_edit.php*
##|-PRIV

function schedulecmp($a, $b) {
	return strcmp($a['name'], $b['name']);
}

function schedule_sort() {
	global $g, $config;

	if (!is_array($config['schedules']['schedule'])) {
		return;
	}

	usort($config['schedules']['schedule'], "schedulecmp");
}

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

$pgtitle = array(gettext("Firewall"), gettext("Schedules"), gettext("Edit"));
$pglinks = array("", "firewall_schedule.php", "@self");

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/firewall_schedule.php');

$dayArray = array (gettext('Mon'), gettext('Tues'), gettext('Wed'), gettext('Thur'), gettext('Fri'), gettext('Sat'), gettext('Sun'));
$monthArray = array (gettext('January'), gettext('February'), gettext('March'), gettext('April'), gettext('May'), gettext('June'), gettext('July'), gettext('August'), gettext('September'), gettext('October'), gettext('November'), gettext('December'));

init_config_arr(array('schedules', 'schedule'));
$a_schedules = &$config['schedules']['schedule'];

if (isset($_REQUEST['id']) && is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

if (isset($id) && $a_schedules[$id]) {
	$pconfig['name'] = $a_schedules[$id]['name'];
	$pconfig['descr'] = html_entity_decode($a_schedules[$id]['descr']);
	$pconfig['timerange'] = $a_schedules[$id]['timerange'];
	$pconfig['schedlabel'] = $a_schedules[$id]['schedlabel'];
	$getSchedule = true;
}

if ($_POST['save']) {

	if (strtolower($_POST['name']) == "lan") {
		$input_errors[] = gettext("Schedule may not be named LAN.");
	}

	if (strtolower($_POST['name']) == "wan") {
		$input_errors[] = gettext("Schedule may not be named WAN.");
	}

	if (strtolower($_POST['name']) == "") {
		$input_errors[] = gettext("Schedule name cannot be blank.");
	}

	if (!is_validaliasname($_POST['name'])) {
		$input_errors[] = invalidaliasnamemsg($_POST['name'], gettext("schedule"));
	}

	/* check for name conflicts */
	foreach ($a_schedules as $schedule) {
		if (isset($id) && ($a_schedules[$id]) && ($a_schedules[$id] === $schedule)) {
			continue;
		}

		if ($schedule['name'] == $_POST['name']) {
			$input_errors[] = gettext("A Schedule with this name already exists.");
			break;
		}
	}

	$schedule = array();

	$schedule['name'] = $_POST['name'];
	$schedule['descr'] = $_POST['descr'];

	$timerangeFound = false;

	for ($x = 0; $x < 99; $x++) {
		if ($_POST['schedule' . $x]) {
			if (!preg_match('/^[0-9]+:[0-9]+$/', $_POST['starttime' . $x])) {
				$input_errors[] = sprintf(gettext("Invalid start time - '%s'"), $_POST['starttime' . $x]);
				continue;
			}

			if (!preg_match('/^[0-9]+:[0-9]+$/', $_POST['stoptime' . $x])) {
				$input_errors[] = sprintf(gettext("Invalid stop time - '%s'"), $_POST['stoptime' . $x]);
				continue;
			}

			$timerangeFound = true;
			$timeparts = array();
			$firstprint = false;
			$timestr = $_POST['schedule' . $x];
			$timehourstr = $_POST['starttime' . $x];
			$timehourstr .= "-";
			$timehourstr .= $_POST['stoptime' . $x];
			$timedescrstr = $_POST['timedescr' . $x];
			$dashpos = strpos($timestr, '-');

			if ($dashpos === false) {
				$timeparts['position'] = $timestr;
			} else {
				$tempindarray = array();
				$monthstr = "";
				$daystr = "";
				$tempindarray = explode(",", $timestr);
				foreach ($tempindarray as $currentselection) {
					if ($currentselection) {
						if ($firstprint) {
							$monthstr .= ",";
							$daystr .= ",";
						}
						$tempstr = "";
						$monthpos = strpos($currentselection, "m");
						$daypos = strpos($currentselection, "d");
						$monthstr .= substr($currentselection, $monthpos+1, $daypos-$monthpos-1);
						$daystr .=	substr($currentselection, $daypos+1);
						$firstprint = true;
					}
				}

				$timeparts['month'] = $monthstr;
				$timeparts['day'] = $daystr;
			}

			$timeparts['hour'] = $timehourstr;
			$timeparts['rangedescr'] = $timedescrstr;
			$schedule['timerange'][$x] = $timeparts;
		}
	}

	if (!$timerangeFound) {
		$input_errors[] = gettext("The schedule must have at least one time range configured.");
	}

	if (!$input_errors) {

		if (!empty($pconfig['schedlabel'])) {
			$schedule['schedlabel'] = $pconfig['schedlabel'];
		} else {
			$schedule['schedlabel'] = uniqid();
		}

		if (isset($id) && $a_schedules[$id]) {
			$a_schedules[$id] = $schedule;
		} else {
			$a_schedules[] = $schedule;
		}

		schedule_sort();

		if (write_config(gettext("Firewall schedule configured."))) {
			filter_configure();
		}

		header("Location: firewall_schedule.php");
		exit;

	}
	//we received input errors, copy data to prevent retype
	else {
		if (!$_POST['schedule0']) {
			$getSchedule = false;
		} else {
			$getSchedule = true;
		}

		$pconfig['name'] = $schedule['name'];
		$pconfig['descr'] = $schedule['descr'];
		$pconfig['timerange'] = $schedule['timerange'];
	}

}

include("head.inc");

// Returns a string containing the HTML to display a calendar table
function build_date_table() {
	$tblstr = "";

	$firstmonth = TRUE;
	$monthcounter = date("n");
	$yearcounter = date("Y");

	for ($k = 0; $k < 12; $k++) {
		$firstdayofmonth = date("w", mktime(0, 0, 0, date($monthcounter), 1, date($yearcounter)));

		if ($firstdayofmonth == 0) {
			$firstdayofmonth = 7;
		}

		$daycounter = 1;
		//number of day in month
		$numberofdays = date("t", mktime(0, 0, 0, date($monthcounter), 1, date($yearcounter)));
		$firstdayprinted = FALSE;
		$lasttr = FALSE;
		$positioncounter = 1;//7 for Sun, 1 for Mon, 2 for Tues, etc

		$mostr = '<div id="' . date("F_y", mktime(0, 0, 0, date($monthcounter), 1, date($yearcounter))) ;
		$mostr .= '" style="position:relative; display:';

		if ($firstmonth) {
			$mostr .= "block";
		} else {
			$mostr .= "none";
		}

		$mostr .= '" class="col-md-6">';

		$mostr .=
			'<table class="table table-condensed table-bordered" id="calTable' . $monthcounter . $yearcounter . '" >
				<thead><tr><td colspan="7" class="text-center"><b>' . date("F_Y", mktime(0, 0, 0, date($monthcounter), 1, date($yearcounter))) . '</b></td>
				</tr>
				<tr>
					<th class="text-center" style="cursor: pointer;" onclick="daytoggle(\'w1p1\');">' . gettext("Mon") . '</th>
					<th class="text-center" style="cursor: pointer;" onclick="daytoggle(\'w1p2\');">' . gettext("Tue") . '</th>
					<th class="text-center" style="cursor: pointer;" onclick="daytoggle(\'w1p3\');">' . gettext("Wed") . '</th>
					<th class="text-center" style="cursor: pointer;" onclick="daytoggle(\'w1p4\');">' . gettext("Thu") . '</th>
					<th class="text-center" style="cursor: pointer;" onclick="daytoggle(\'w1p5\');">' . gettext("Fri") . '</th>
					<th class="text-center" style="cursor: pointer;" onclick="daytoggle(\'w1p6\');">' . gettext("Sat") . '</th>
					<th class="text-center" style="cursor: pointer;" onclick="daytoggle(\'w1p7\');">' . gettext("Sun") . '</th>
				</tr>
				</thead>
				<tbody>' . "\r\n";

				$firstmonth = FALSE;

				while ($daycounter<=$numberofdays) {
					$weekcounter =	date("W", mktime(0, 0, 0, date($monthcounter), date($daycounter), date($yearcounter)));
					$weekcounter = ltrim($weekcounter, "0");
					if ($positioncounter == 1) {
						$mostr .= "<tr>";
					}

					if ($firstdayofmonth == $positioncounter) {

						$mostr .= '<td class="text-center" style="cursor: pointer;" id="w' . $weekcounter . 'p' . $positioncounter . '" onclick="daytoggle(\'w' . $weekcounter . 'p' . $positioncounter . '-m' . $monthcounter . 'd' . $daycounter . '\');">' . $daycounter . "\r\n";

						$daycounter++;
						$firstdayprinted = TRUE;
						$mostr .= "</td>";
					} elseif ($firstdayprinted == TRUE && $daycounter <= $numberofdays) {
						$mostr .= '<td class="text-center" style="cursor: pointer;" id="w' . $weekcounter . 'p' . $positioncounter . '" onclick="daytoggle(\'w' . $weekcounter . 'p' . $positioncounter . '-m' . $monthcounter . 'd' . $daycounter . '\');">' . $daycounter . "\r\n";
						$daycounter++;
						$mostr .= "</td>";
					} else {
						$mostr .= '<td class="text-center"></td>';
					}

					if ($daycounter > $numberofdays) {
						while ($positioncounter < 7) {
							$mostr .= '<td class="text-center"></td>';
							$positioncounter++;
						}
					}

					if ($positioncounter == 7) {
						$positioncounter = 1;
						$mostr .= "</tr>";
					} else {
						$positioncounter++;
					}

				}

			$mostr .= '</tbody></table><br /><p>';
			$mostr .= gettext('Click individual date to select that date only. Click the appropriate weekday Header to select all occurrences of that weekday. ');
			$mostr .= '</p></div>';

		if ($monthcounter == 12) {
			$monthcounter = 1;
			$yearcounter++;
		} else {
			$monthcounter++;
		}

	$tblstr .= $mostr;
	} //end for loop

	return($tblstr);
}

function build_month_list() {

	$list = array();

	$monthcounter = date("n");
	$monthlimit = $monthcounter + 12;
	$yearcounter = date("Y");

	for ($k = 0; $k < 12; $k++) {
		$list[$monthcounter] = date("F_y", mktime(0, 0, 0, date($monthcounter), 1, date($yearcounter)));

		if ($monthcounter == 12) {
			$monthcounter = 1;
			$yearcounter++;
		} else {
			$monthcounter++;
		}
	}

	return($list);
}

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('Schedule Information');

$input = new Form_Input(
	'name',
	'*Schedule Name',
	'text',
	$pconfig['name']
);

if (is_schedule_inuse($pconfig['name']) != true) {
	$input->setHelp('The name of the schedule may only consist of the characters "a-z, A-Z, 0-9 and _".');
} else {
	$input->setHelp('This schedule is in use so the name may not be modified!');
}

if (is_schedule_inuse($pconfig['name']) == true) {
	$input->setReadonly();
}

$section->addInput($input);

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed). ');

$section->addInput(new Form_Select(
	'monthsel',
	'Month',
	null,
	build_month_list()
));

$section->addInput(new Form_StaticText(
	'*Date',
	 build_date_table()
));

$group = new Form_Group('*Time');

$group->add(new Form_Select(
	'starttimehour',
	null,
	null,
	array_combine(range(0, 23, 1), range(0, 23, 1))
))->setHelp('Start Hrs');

$group->add(new Form_Select(
	'starttimemin',
	null,
	null,
	array('00' => '00', '15' => '15', '30' => '30', '45' => '45', '59' => '59')
))->setHelp('Start Mins');

$group->add(new Form_Select(
	'stoptimehour',
	null,
	'23',
	array_combine(range(0, 23, 1), range(0, 23, 1))
))->setHelp('Stop Hrs');

$group->add(new Form_Select(
	'stoptimemin',
	null,
	'59',
	array('00' => '00', '15' => '15', '30' => '30', '45' => '45', '59' => '59')
))->setHelp('Stop Mins');

$group->setHelp('Select the time range for the day(s) selected on the Month(s) above. A full day is 0:00-23:59.');

$section->add($group);

$section->addInput(new Form_Input(
	'timerangedescr',
	'Time range description',
	'text',
	$pconfig['timerangedescr']
))->setHelp('A description may be entered here for administrative reference (not parsed). ');

$group = new Form_Group(null);

$group->add(new Form_Button(
	'btnaddtime',
	'Add Time',
	null,
	'fa-plus'
))->setAttribute('type','button')->addClass('btn-success btn-sm');

$group->add(new Form_Button(
	'btnclrsel',
	'Clear selection',
	null,
	'fa-undo'
))->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->add($group);

if (isset($id) && $a_schedules[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->add($section);

$section = new Form_Section('Configured Ranges');
$counter = 0;

if ($getSchedule) {
	$maxrows = count($pconfig['timerange']) -1;

	foreach ($pconfig['timerange'] as $timerange) {
		$tempFriendlyTime = "";
		$tempID = "";
		if ($timerange) {
			$dayFriendly = "";
			$tempFriendlyTime = "";
			$timedescr = $timerange['rangedescr'];

			//get hours
			$temptimerange = $timerange['hour'];
			$temptimeseparator = strrpos($temptimerange, "-");

			$starttime = substr ($temptimerange, 0, $temptimeseparator);
			$stoptime = substr ($temptimerange, $temptimeseparator+1);
			$currentDay = "";
			$firstDay = "";
			$nextDay = "";
			$foundEnd = false;
			$firstDayFound = false;
			$firstPrint = false;
			$firstprint2 = false;

			if ($timerange['month']) {
				$tempmontharray = explode(",", $timerange['month']);
				$tempdayarray = explode(",", $timerange['day']);
				$arraycounter = 0;
				foreach ($tempmontharray as $monthtmp) {
					$month = $tempmontharray[$arraycounter];
					$day = $tempdayarray[$arraycounter];
					$daypos = date("w", mktime(0, 0, 0, date($month), date($day), date("Y")));
					//if sunday, set position to 7 to get correct week number. This is due to php limitations on ISO-8601. When we move to php5.1 we can change this.
					if ($daypos == 0) {
						$daypos = 7;
					}

					$weeknumber = date("W", mktime(0, 0, 0, date($month), date($day), date("Y")));
					$weeknumber = ltrim($weeknumber, "0");

					if ($firstPrint) {
						$tempID .= ",";
					}

					$tempID .= "w" . $weeknumber . "p" . $daypos . "-m" .  $month . "d" . $day;
					$firstPrint = true;

					if (!$firstDayFound) {
						$firstDay = $day;
						$firstmonth = $month;
						$firstDayFound = true;
					}

					$currentDay = $day;
					$nextDay = $tempdayarray[$arraycounter+1];
					$currentDay++;
					if (($currentDay != $nextDay) || ($tempmontharray[$arraycounter] != $tempmontharray[$arraycounter+1])) {
						if ($firstprint2) {
							$tempFriendlyTime .= ", ";
						}

						$currentDay--;

						if ($currentDay != $firstDay) {
							$tempFriendlyTime .= $monthArray[$firstmonth-1] . " " . $firstDay . " - " . $currentDay ;
						} else {
							$tempFriendlyTime .=  $monthArray[$month-1] . " " . $day;
						}

						$firstDayFound = false;
						$firstprint2 = true;
					}
					$arraycounter++;
				}

			} else {
				$dayFriendly = $timerange['position'];
				$tempID = $dayFriendly;
			}

			$tempTime = $tempID . "||" . $starttime . "-" . $stoptime . "||" . $timedescr;

			//following code makes the days friendly appearing, IE instead of Mon, Tues, Wed it will show Mon - Wed
			$foundEnd = false;
			$firstDayFound = false;
			$firstprint = false;
			$tempFriendlyDayArray = explode(",", $dayFriendly);
			$currentDay = "";
			$firstDay = "";
			$nextDay = "";
			$i = 0;

			if (!$timerange['month']) {
				foreach ($tempFriendlyDayArray as $day) {
					if ($day != "") {
						if (!$firstDayFound) {
							$firstDay = $tempFriendlyDayArray[$i];
							$firstDayFound = true;
						}

						$currentDay =$tempFriendlyDayArray[$i];
						//get next day
						$nextDay = $tempFriendlyDayArray[$i+1];
						$currentDay++;

						if ($currentDay != $nextDay) {
							if ($firstprint) {
								$tempFriendlyTime .= ", ";
							}

							$currentDay--;

							if ($currentDay != $firstDay) {
								$tempFriendlyTime .= $dayArray[$firstDay-1] . " - " . $dayArray[$currentDay-1];
							} else {
								$tempFriendlyTime .= $dayArray[$firstDay-1];
							}

							$firstDayFound = false;
							$firstprint = true;
						}
						$i++;
					}
				}
			}

			$group = new Form_Group('');
			$group->add(new Form_Input(
				'tempFriendlyTime' . $counter,
				null,
				'text',
				$tempFriendlyTime
			))->setWidth(2)->setReadonly()->setHelp($counter == $maxrows ? 'Day(s)':'');

			$group->add(new Form_Input(
				'starttime' . $counter,
				null,
				'text',
				$starttime
			))->setWidth(2)->setReadonly()->setHelp($counter == $maxrows ? 'Start time':'');

			$group->add(new Form_Input(
				'stoptime' . $counter,
				null,
				'text',
				$stoptime
			))->setWidth(2)->setReadonly()->setHelp($counter == $maxrows ? 'Stop time':'');

			$group->add(new Form_Input(
				'timedescr' . $counter,
				null,
				'text',
				$timedescr
			))->setWidth(2)->setHelp($counter == $maxrows ? 'Description':'');

			$group->add(new Form_Button(
				'Delete' . $counter,
				'Delete',
				null,
				'fa-trash'
			))->setAttribute('type','button')->addClass('btn-xs btn-warning');

			$form->addGlobal(new Form_Input(
				'schedule' . $counter,
				null,
				'hidden',
				$tempID
			));

			$group->addClass('schedulegrp' . $counter);

			$counter++;
			$section->add($group);
		}
	}
}

// This is just a marker that the javascript can use to insertBefore() when adding new rows
$section->addInput(new Form_Input(
	'marker',
	null,
	'hidden'
))->addClass('noranges');

$form->add($section);

print($form);
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	//Update the calendar when a new month is selected
	$('#monthsel').on('change', function() {
		update_month();
	});

	$('#btnclrsel').click(function() {
		clearCalendar();
		clearTime();
		clearDescr();
	});

	$('#btnaddtime').click(function() {
		processEntries();
	});

	$('[id^=Delete]').click(function(event) {
		fse_delete_row(event.target.id.slice(6));
	});
});
//]]>
</script>

<script type="text/javascript">
//<![CDATA[
var daysSelected = "";
var month_array = ['January','February','March','April','May','June','July','August','September','October','November','December'];
var day_array = ['Mon','Tues','Wed','Thur','Fri','Sat','Sun'];
var schCounter = 0;

function repeatExistingDays() {
	var tempstr, tempstrdaypos, week, daypos, dayposdone = "";

	var dayarray = daysSelected.split(",");

	for (i=0; i<=dayarray.length; i++) {
		tempstr = dayarray[i];
		tempstrdaypos = tempstr.search("p");
		week = tempstr.substring(1, tempstrdaypos);
		week = parseInt(week);
		dashpos = tempstr.search("-");
		daypos = tempstr.substring(tempstrdaypos+1, dashpos);
		daypos = parseInt(daypos);

		daydone = dayposdone.search(daypos);
		tempstr = 'w' + week + 'p' + daypos;
		daycell = eval('document.getElementById(tempstr)');
		if (daydone == "-1") {
			if ($("#"+tempstr).hasClass("bg-info")) {
				daytogglerepeating(week,daypos,true);
			} else {
				daytogglerepeating(week,daypos,false);
			}

			dayposdone += daypos + ",";
		}
	}
}

function daytogglerepeating(week, daypos, bExists) {
	var tempstr, daycell, dayoriginal = "";

	for (j=1; j<=53; j++) {
		tempstr = 'w' + j + 'p' + daypos;
		daycell = eval('document.getElementById(tempstr)');
		dayoriginalpos =  daysSelected.indexOf(tempstr);

		//if bExists set to true, means cell is already select it
		//unselect it and remove original day from daysSelected string

		if (daycell != null) {
			if (bExists) {
				$("#"+tempstr).removeClass("bg-info");
			} else {
				$("#"+tempstr).addClass("bg-info");
			}

			if (dayoriginalpos != "-1") {
				dayoriginalend = daysSelected.indexOf(',', dayoriginalpos);
				tempstr = daysSelected.substring(dayoriginalpos, dayoriginalend+1);
				daysSelected = daysSelected.replace(tempstr, "");
			}
		}
	}
}

function daytoggle(id) {
	var runrepeat, tempstr = "";
	var bFoundValid = false;

	iddashpos = id.search("-");

	var tempstrdaypos = id.search("p");
	var week = id.substring(1, tempstrdaypos);

	week = parseInt(week);

	if (iddashpos == "-1") {
		idmod = id;
		runrepeat = true;
		var daypos = id.substr(tempstrdaypos+1);
	} else {
		idmod = id.substring(0, iddashpos);
		var daypos = id.substring(tempstrdaypos+1, iddashpos);
	}

	daypos = parseInt(daypos);

	while (!bFoundValid) {
		var daycell = document.getElementById(idmod);

		if (daycell != null) {
			if ($("#"+idmod).hasClass("bg-success")) {
				$("#"+idmod).removeClass("bg-success");
				str = id + ",";
				daysSelected = daysSelected.replace(str, "");
			} else if ($("#"+idmod).hasClass("bg-info")) {
				daytogglerepeating(week,daypos,true);
			} else { //color is white cell
				if (!runrepeat) {
					$("#"+idmod).addClass("bg-success");
				} else {
					$("#"+idmod).addClass("bg-info");
					daytogglerepeating(week,daypos,false);
				}
				daysSelected += id + ",";
			}
			bFoundValid = true;
		} else {
			//we found an invalid cell when column was clicked, move up to the next week
			week++;
			tempstr = "w" + week + "p" + daypos;
			idmod = tempstr;
		}
	}
}

function update_month() {
	var indexNum = document.forms[0].monthsel.selectedIndex;
	var selected = document.forms[0].monthsel.options[indexNum].text;

	for (i = 0; i <= 11; i++) {
		option = document.forms[0].monthsel.options[i].text;
		document.popupMonthLayer = eval('document.getElementById (option)');

		if (selected == option) {
			document.popupMonthLayer.style.display="block";
		} else {
			document.popupMonthLayer.style.display="none";
		}
	}
}

function checkForRanges() {
	if (daysSelected != "") {
		alert("The specified time range has not been saved. Please click 'Add Time' button to save the time range.");
		return false;
	} else {
		return true;
	}
}

function processEntries() {
	var tempstr, starttimehour, starttimemin, stoptimehour, stoptimemin, errors = "";
	var passedValidiation = true;

	//get time specified
	starttimehour = parseInt(document.getElementById("starttimehour").value);
	starttimemin = parseInt(document.getElementById("starttimemin").value);
	stoptimehour = parseInt(document.getElementById("stoptimehour").value);
	stoptimemin = parseInt(document.getElementById("stoptimemin").value);

	//do time checks
	if (starttimehour > stoptimehour) {
		errors = "Error: Start Hour cannot be greater than Stop Hour.";
		passedValidiation = false;

	} else if (starttimehour == stoptimehour) {
		if (starttimemin > stoptimemin) {
			errors = "Error: Start Minute cannot be greater than Stop Minute.";
			passedValidiation = false;
		}
	}

	if (passedValidiation) {
		addTimeRange();
	} else {
		if (errors != "") {
			alert(errors);
		}
	}
}

function addTimeRange() {
	var tempdayarray = daysSelected.split(",");
	var tempstr, tempFriendlyDay, starttimehour, starttimemin, stoptimehour, nrtempFriendlyTime, rtempFriendlyTime, nrtempID, rtempID = "";
	var stoptimemin, timeRange, tempstrdaypos, week, daypos, day, month, dashpos, nrtempTime, rtempTime, monthstr, daystr = "";
	rtempFriendlyTime = "";
	nrtempFriendlyTime = "";
	nrtempID = "";
	rtempID = "";
	nrtempTime = "";
	rtempTime = "";
	tempdayarray.sort();
	rtempFriendlyDay = "";
	monthstr = "";
	daystr = "";

	//check for existing entries
	var findCurrentCounter;

	for (u = 0; u < 99; u++) {
		findCurrentCounter = document.getElementById("schedule" + u);
		if (!findCurrentCounter) {
			schCounter = u;
			break;
		}
	}

	if (daysSelected != "") {
		//get days selected

		for (i = 0; i < tempdayarray.length; i++) {
			tempstr = tempdayarray[i];
			if (tempstr != "") {
				tempstrdaypos = tempstr.search("p");
				week = tempstr.substring(1, tempstrdaypos);
				week = parseInt(week);
				dashpos = tempstr.search("-");

				if (dashpos != "-1") {
					var nonrepeatingfound = true;
					daypos = tempstr.substring(tempstrdaypos+1, dashpos);
					daypos = parseInt(daypos);
					monthpos = tempstr.search("m");
					tempstrdaypos = tempstr.search("d");
					month = tempstr.substring(monthpos+1, tempstrdaypos);
					month = parseInt(month);
					day = tempstr.substring(tempstrdaypos+1);
					day = parseInt(day);
					monthstr += month + ",";
					daystr += day + ",";
					nrtempID += tempstr + ",";
				} else {
					var repeatingfound = true;
					daypos = tempstr.substr(tempstrdaypos+1);
					daypos = parseInt(daypos);
					rtempFriendlyDay += daypos + ",";
					rtempID += daypos + ",";
				}
			}
		}

		//code below spits out friendly look format for nonrepeating schedules
		var foundEnd = false;
		var firstDayFound = false;
		var firstprint = false;
		var tempFriendlyMonthArray = monthstr.split(",");
		var tempFriendlyDayArray = daystr.split(",");
		var currentDay, firstDay, nextDay, currentMonth, nextMonth, firstDay, firstMonth = "";

		for (k = 0; k < tempFriendlyMonthArray.length; k++) {
			tempstr = tempFriendlyMonthArray[k];
			if (tempstr != "") {
				if (!firstDayFound) {
					firstDay = tempFriendlyDayArray[k];
					firstDay = parseInt(firstDay);
					firstMonth = tempFriendlyMonthArray[k];
					firstMonth = parseInt(firstMonth);
					firstDayFound = true;
				}

				currentDay = tempFriendlyDayArray[k];
				currentDay = parseInt(currentDay);
				//get next day
				nextDay = tempFriendlyDayArray[k+1];
				nextDay = parseInt(nextDay);
				//get next month

				currentDay++;
				if ((currentDay != nextDay) || (tempFriendlyMonthArray[k] != tempFriendlyMonthArray[k+1])) {
					if (firstprint) {
						nrtempFriendlyTime += ", ";
					}

					currentDay--;

					if (currentDay != firstDay) {
						nrtempFriendlyTime += month_array[firstMonth-1] + " " + firstDay + "-" + currentDay;
					} else {
						nrtempFriendlyTime += month_array[firstMonth-1] + " " + currentDay;
					}

					firstDayFound = false;
					firstprint = true;
				}
			}
		}

		//code below spits out friendly look format for repeating schedules
		foundEnd = false;
		firstDayFound = false;
		firstprint = false;
		tempFriendlyDayArray = rtempFriendlyDay.split(",");
		tempFriendlyDayArray.sort();
		currentDay, firstDay, nextDay = "";

		for (k = 0; k < tempFriendlyDayArray.length; k++) {
			tempstr = tempFriendlyDayArray[k];
			if (tempstr != "") {
				if (!firstDayFound) {
					firstDay = tempFriendlyDayArray[k];
					firstDay = parseInt(firstDay);
					firstDayFound = true;
				}

				currentDay = tempFriendlyDayArray[k];
				currentDay = parseInt(currentDay);
				//get next day
				nextDay = tempFriendlyDayArray[k+1];
				nextDay = parseInt(nextDay);
				currentDay++;

				if (currentDay != nextDay) {
					if (firstprint) {
						rtempFriendlyTime += ", ";
					}

					currentDay--;

					if (currentDay != firstDay) {
						rtempFriendlyTime += day_array[firstDay-1] + " - " + day_array[currentDay-1];
					} else {
						rtempFriendlyTime += day_array[firstDay-1];
					}

					firstDayFound = false;
					firstprint = true;
				}
			}
		}

		//sort the tempID
		var tempsortArray = rtempID.split(",");
		var isFirstdone = false;

		tempsortArray.sort();

		//clear tempID
		rtempID = "";
		for (t = 0; t < tempsortArray.length; t++) {
			if (tempsortArray[t] != "") {
				if (!isFirstdone) {
					rtempID += tempsortArray[t];
					isFirstdone = true;
				} else {
					rtempID += "," + tempsortArray[t];
				}
			}
		}

		//get time specified
		starttimehour =	 document.getElementById("starttimehour").value
		starttimemin = document.getElementById("starttimemin").value;
		stoptimehour = document.getElementById("stoptimehour").value;
		stoptimemin = document.getElementById("stoptimemin").value;

		timeRange = "||" + starttimehour + ":";
		timeRange += starttimemin + "-";
		timeRange += stoptimehour + ":";
		timeRange += stoptimemin;

		//get description for time range
		var tempdescr = document.getElementById("timerangedescr").value

		if (nonrepeatingfound) {
			nrtempTime += nrtempID;
			//add time ranges
			nrtempTime += timeRange;
			//add description
			nrtempTime += "||" + tempdescr;
			insertElements(nrtempFriendlyTime, starttimehour, starttimemin, stoptimehour, stoptimemin, tempdescr, nrtempTime, nrtempID);
		}

		if (repeatingfound) {
			rtempTime += rtempID;
			//add time ranges
			rtempTime += timeRange;
			//add description
			rtempTime += "||" + tempdescr;
			insertElements(rtempFriendlyTime, starttimehour, starttimemin, stoptimehour, stoptimemin, tempdescr, rtempTime, rtempID);
		}

	} else {
		//no days were selected, alert user
		alert ("At least 1 day must be selected before adding time");
	}
}

function clearCalendar() {
	var tempstr, daycell = "";
	//clear days selected
	daysSelected = "";
	//loop through all 52 weeks
	for (j = 1; j <= 53; j++) {
		//loop through all 7 days
		for (k = 1; k < 8; k++) {
			tempstr = 'w' + j + 'p' + k;
			daycell = eval('document.getElementById(tempstr)');
			if (daycell != null) {
				$("#"+tempstr).removeClass("bg-success bg-info");
			}
		}
	}
}

function clearTime() {
	document.getElementById("starttimehour").value = "0";
	document.getElementById("starttimemin").value = "00";
	document.getElementById("stoptimehour").value = "23";
	document.getElementById("stoptimemin").value = "59";
}

function clearDescr() {
	document.getElementById("timerangedescr").value = "";
}

var counter = -1;

// Using an HTML template, build a time-range row and add it after the last row in the display
function insertElements(tempFriendlyTime, starttimehour, starttimemin, stoptimehour, stoptimemin, tempdescr, tempTime, tempID) {
	var rowhtml;

	if (counter < 0) {
		counter = <?php if (!isset($counter)) echo '0'; else echo $counter ?>;
		rows_displayed = counter;
	}

	// Template for the schedule definition. '@' will be replaced with the row number using .replace()
	rowhtml =
	'<div class="form-group schedulegrp' + counter + '">' +
		'<label for="tempFriendlyTime@" class="col-sm-2 control-label"></label>' +
		'<div class="col-sm-2">' +
			'<input class="form-control" name="tempFriendlyTime@" id="tempFriendlyTime@" type="text" readonly="readonly" value="' + tempFriendlyTime + '"/>' +
			'<span class="help-block">Day(s)</span>' +
		'</div>' +
		'<div class="col-sm-2">' +
			'<input class="form-control" name="starttime@" id="starttime@" type="text" readonly="readonly" value="' + starttimehour + ':' + starttimemin + '"/>' +
			'<span class="help-block">Start time</span>' +
		'</div>' +
		'<div class="col-sm-2">' +
			'<input class="form-control" name="stoptime@" id="stoptime@" type="text" readonly="readonly" value="' + stoptimehour + ':' + stoptimemin + '"/>' +
			'<span class="help-block">Stop time</span>' +
		'</div>' +
		'<div class="col-sm-2">' +
			'<input class="form-control" name="timedescr@" id="timedescr@" type="text" value="' + tempdescr + '"/>' +
			'<span class="help-block">Description</span>' +
		'</div>' +
		'<div class="col-sm-2">' +
			'<input class="form-control" name="schedule@" id="schedule@" type="hidden" value="' + tempID + '"/>' +
		'</div>' +
		'<div class="col-sm-2">' +
			'<a class="btn btn-xs btn-warning" name="delete@" id="delete@" type="button" value="@"><i class="fa fa-trash icon-embed-btn"></i><?= gettext("Delete") ?></a>' +
		'</div>' +
	'</div>';

	$('.help-block').hide();

	var node = $('.noranges').parent().parent();
	$(rowhtml.replace(/@/g, counter)).insertBefore(node);

	$('[id^=delete]').click(function(event) {
		fse_delete_row(event.target.id.slice(6));
	});

	counter++;

	schCounter++;

	//reset calendar and time and descr
	clearCalendar();
	clearTime();
	clearDescr();
}

// If only everything were this simple
function fse_delete_row(row) {
	$('.schedulegrp' + row).remove();
}
//]]>
</script>

<?php

include("foot.inc");
