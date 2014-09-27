<?php
/*
	firewall_schedule_edit.php
	Copyright (C) 2004 Scott Ullrich
	All rights reserved.

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
	pfSense_MODULE:	schedules
*/

##|+PRIV
##|*IDENT=page-firewall-schedules-edit
##|*NAME=Firewall: Schedules: Edit page
##|*DESCR=Allow access to the 'Firewall: Schedules: Edit' page.
##|*MATCH=firewall_schedule_edit.php*
##|-PRIV

function schedulecmp($a, $b) {
	return strcmp($a['name'], $b['name']);
}

function schedule_sort(){
        global $g, $config;

        if (!is_array($config['schedules']['schedule']))
                return;

        usort($config['schedules']['schedule'], "schedulecmp");
}

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

$pgtitle = array(gettext("Firewall"),gettext("Schedules"),gettext("Edit"));

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/firewall_schedule.php');

$dayArray = array (gettext('Mon'),gettext('Tues'),gettext('Wed'),gettext('Thur'),gettext('Fri'),gettext('Sat'),gettext('Sun'));
$monthArray = array (gettext('January'),gettext('February'),gettext('March'),gettext('April'),gettext('May'),gettext('June'),gettext('July'),gettext('August'),gettext('September'),gettext('October'),gettext('November'),gettext('December'));

if (!is_array($config['schedules']['schedule']))
	$config['schedules']['schedule'] = array();

$a_schedules = &$config['schedules']['schedule'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_schedules[$id]) {
	$pconfig['name'] = $a_schedules[$id]['name'];
	$pconfig['descr'] = html_entity_decode($a_schedules[$id]['descr']);
	$pconfig['timerange'] = $a_schedules[$id]['timerange'];
	$pconfig['schedlabel'] = $a_schedules[$id]['schedlabel'];
	$getSchedule = true;
}

if ($_POST) {
	
	if(strtolower($_POST['name']) == "lan")
		$input_errors[] = gettext("Schedule may not be named LAN.");
	if(strtolower($_POST['name']) == "wan")
		$input_errors[] = gettext("Schedule may not be named WAN.");
	if(strtolower($_POST['name']) == "")
		$input_errors[] = gettext("Schedule name cannot be blank.");

	$x = is_validaliasname($_POST['name']);
	if (!isset($x)) {
		$input_errors[] = gettext("Reserved word used for schedule name.");
	} else {
		if (is_validaliasname($_POST['name']) == false)
			$input_errors[] = gettext("The schedule name may only consist of the characters a-z, A-Z, 0-9");
	}
	
	/* check for name conflicts */
	foreach ($a_schedules as $schedule) {
		if (isset($id) && ($a_schedules[$id]) && ($a_schedules[$id] === $schedule))
			continue;

		if ($schedule['name'] == $_POST['name']) {
			$input_errors[] = gettext("A Schedule with this name already exists.");
			break;
		}
	}
	$schedule = array();
	
	$schedule['name'] = $_POST['name'];
	$schedule['descr'] = htmlentities($_POST['descr'], ENT_QUOTES, 'UTF-8');	
	
	$timerangeFound = false;
	for ($x=0; $x<99; $x++){
		if($_POST['schedule' . $x]) {
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
			$timedescrstr = htmlentities($_POST['timedescr' . $x], ENT_QUOTES, 'UTF-8'); 
			$dashpos = strpos($timestr, '-');
			if ($dashpos === false)
			{
				$timeparts['position'] = $timestr;
			}
			else
			{
				$tempindarray = array();
				$monthstr = "";
				$daystr = "";
				$tempindarray = explode(",", $timestr);
				foreach ($tempindarray as $currentselection)
				{
					if ($currentselection){
						if ($firstprint)
						{
							$monthstr .= ",";
							$daystr .= ",";						
						}
						$tempstr = "";
						$monthpos = strpos($currentselection, "m");
						$daypos = strpos($currentselection, "d");
						$monthstr .= substr($currentselection, $monthpos+1, $daypos-$monthpos-1);
						$daystr .=  substr($currentselection, $daypos+1);			
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
	
	if (!$timerangeFound)
		$input_errors[] = gettext("The schedule must have at least one time range configured.");
		
	if (!$input_errors) {		
		
		if (!empty($pconfig['schedlabel']))
			$schedule['schedlabel'] = $pconfig['schedlabel'];
		else
			$schedule['schedlabel'] = uniqid();

		if (isset($id) && $a_schedules[$id]){
			$a_schedules[$id] = $schedule;
		}
		else{
			$a_schedules[] = $schedule;
		}
		schedule_sort();
		if (write_config())
			filter_configure();

		header("Location: firewall_schedule.php");
		exit;
		
	}
	//we received input errors, copy data to prevent retype
	else
	{
		if (!$_POST['schedule0'])
			$getSchedule = false;
		else
			$getSchedule = true;
		$pconfig['name'] = $schedule['name'];
		$pconfig['descr'] = $schedule['descr'];
		$pconfig['timerange'] = $schedule['timerange'];
	}	

}
include("head.inc");

/* put your custom HTML head content here        */
/* using some of the $pfSenseHead function calls */
$jscriptstr = <<<EOD
<script type="text/javascript">
//<![CDATA[
var daysSelected = "";
var month_array = ['January','February','March','April','May','June','July','August','September','October','November','December'];
var day_array = ['Mon','Tues','Wed','Thur','Fri','Sat','Sun'];
var schCounter = 0;

function rgb2hex(rgb) {
	var parts = rgb.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
	if (parts == null)
		return;
	function hex(x) {
		return ("0" + parseInt(x).toString(16)).slice(-2);
	}
	return ("#" + hex(parts[1]) + hex(parts[2]) + hex(parts[3])).toUpperCase();
}

function repeatExistingDays(){
	var tempstr, tempstrdaypos, week, daypos, dayposdone = "";
	
	var dayarray = daysSelected.split(",");
	for (i=0; i<=dayarray.length; i++){
		tempstr = dayarray[i];
		tempstrdaypos = tempstr.search("p");
		week = tempstr.substring(1,tempstrdaypos);
		week = parseInt(week);
		dashpos = tempstr.search("-");		
		daypos = tempstr.substring(tempstrdaypos+1, dashpos);
		daypos = parseInt(daypos);
		
		daydone = dayposdone.search(daypos);
		tempstr = 'w' + week + 'p' + daypos;
		daycell = eval('document.getElementById(tempstr)');
		if (daydone == "-1"){
			if (rgb2hex(daycell.style.backgroundColor) == "#F08080")  // lightcoral
				daytogglerepeating(week,daypos,true);
			else
				daytogglerepeating(week,daypos,false);
			dayposdone += daypos + ",";
		}
	}	
}

function daytogglerepeating(week,daypos,bExists){
	var tempstr, daycell, dayoriginal = "";
	for (j=1; j<=53; j++)
	{						
		tempstr = 'w' + j + 'p' + daypos;
		daycell = eval('document.getElementById(tempstr)');
		dayoriginalpos =  daysSelected.indexOf(tempstr);
		
		//if bExists set to true, means cell is already select it
		//unselect it and remove original day from daysSelected string		
	
		if (daycell != null)
		{
			if (bExists){	
				daycell.style.backgroundColor = "#FFFFFF";  // white
			}
			else
			{
				daycell.style.backgroundColor = "#F08080";  // lightcoral		
			}	
	
			if (dayoriginalpos != "-1")
			{						
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
	var week = id.substring(1,tempstrdaypos);
	week = parseInt(week);
	
	if (iddashpos == "-1")
	{
		idmod = id;
		runrepeat = true;
		var daypos = id.substr(tempstrdaypos+1);	
	}
	else
	{		
		idmod = id.substring(0,iddashpos);
		var daypos = id.substring(tempstrdaypos+1,iddashpos);
	}
	
	daypos = parseInt(daypos);
	
	while (!bFoundValid){
		var daycell = document.getElementById(idmod);		
	
		if (daycell != null){
			if (rgb2hex(daycell.style.backgroundColor) == "#FF0000"){  // red
				daycell.style.backgroundColor = "#FFFFFF";  // white
				str = id + ",";
				daysSelected = daysSelected.replace(str, "");
			}
			else if (rgb2hex(daycell.style.backgroundColor) == "#F08080")  // lightcoral
			{
				daytogglerepeating(week,daypos,true);
			}
			else //color is white cell
			{
				if (!runrepeat)
				{
					daycell.style.backgroundColor = "#FF0000";  // red
				}
				else
				{
					daycell.style.backgroundColor = "#F08080";  // lightcoral
					daytogglerepeating(week,daypos,false);								
				}
				daysSelected += id + ",";
			}
			bFoundValid = true;
		}
		else
		{
			//we found an invalid cell when column was clicked, move up to the next week
			week++;
			tempstr = "w" + week + "p" + daypos;
			idmod = tempstr;			
		}
	}
}

function update_month(){
	var indexNum = document.forms[0].monthsel.selectedIndex;
	var selected = document.forms[0].monthsel.options[indexNum].text;

	for (i=0; i<=11; i++){
		option = document.forms[0].monthsel.options[i].text;
		document.popupMonthLayer = eval('document.getElementById (option)');
		
		if(selected == option) {
			document.popupMonthLayer.style.display="block";
		}
		else
			document.popupMonthLayer.style.display="none";
	}
}

function checkForRanges(){
	if (daysSelected != "")
	{
		alert("You have not saved the specified time range. Please click 'Add Time' button to save the time range.");
		return false;
	}
	else
	{
		return true;
	}
}

function processEntries(){
	var tempstr, starttimehour, starttimemin, stoptimehour, stoptimemin, errors = "";
	var passedValidiation = true;
	
	//get time specified
	starttimehour = parseInt(document.getElementById("starttimehour").value);
	starttimemin = parseInt(document.getElementById("starttimemin").value);
	stoptimehour = parseInt(document.getElementById("stoptimehour").value);
	stoptimemin = parseInt(document.getElementById("stoptimemin").value);


	//do time checks	
	if (starttimehour > stoptimehour)
	{
		errors = "Error: Start Hour cannot be greater than Stop Hour.";
		passedValidiation = false;
		
	}
	else if (starttimehour == stoptimehour)
	{
		if (starttimemin > stoptimemin){
			errors = "Error: Start Minute cannot be greater than Stop Minute.";
			passedValidiation = false;
		}
	}	
		
	if (passedValidiation){
		addTimeRange();
	}
	else {
		if (errors != "")
			alert(errors);
	}
}

function addTimeRange(){
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
	for (u=0; u<99; u++){
		findCurrentCounter = document.getElementById("schedule" + u);
		if (!findCurrentCounter)
		{
			schCounter = u;
			break;
		}
	}
		
	if (daysSelected != ""){
		//get days selected
		for (i=0; i<tempdayarray.length; i++)
		{
			tempstr = tempdayarray[i];
			if (tempstr != "")
			{			
				tempstrdaypos = tempstr.search("p");
				week = tempstr.substring(1,tempstrdaypos);
				week = parseInt(week);
				dashpos = tempstr.search("-");			
				
				if (dashpos != "-1")
				{	
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
				}
				else
				{	
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
		for (k=0; k<tempFriendlyMonthArray.length; k++){
			tempstr = tempFriendlyMonthArray[k];
			if (tempstr != ""){
				if (!firstDayFound)
				{
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
				if ((currentDay != nextDay) || (tempFriendlyMonthArray[k] != tempFriendlyMonthArray[k+1])){
					if (firstprint)
						nrtempFriendlyTime += ", ";
					currentDay--;
					if (currentDay != firstDay)
						nrtempFriendlyTime += month_array[firstMonth-1] + " " + firstDay + "-" + currentDay;
					else
						nrtempFriendlyTime += month_array[firstMonth-1] + " " + currentDay; 
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
		for (k=0; k<tempFriendlyDayArray.length; k++){
			tempstr = tempFriendlyDayArray[k];
			if (tempstr != ""){
				if (!firstDayFound)
				{
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
				if (currentDay != nextDay){
					if (firstprint)
						rtempFriendlyTime += ", ";
					currentDay--;
					if (currentDay != firstDay)
						rtempFriendlyTime += day_array[firstDay-1] + " - " + day_array[currentDay-1];
					else
						rtempFriendlyTime += day_array[firstDay-1];
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
		for (t=0; t<tempsortArray.length; t++)
		{
			if (tempsortArray[t] != ""){
				if (!isFirstdone){
					rtempID += tempsortArray[t];
					isFirstdone = true;
				}
				else
					rtempID += "," + tempsortArray[t];
			}
		} 
		
		 
		//get time specified
		starttimehour =  document.getElementById("starttimehour").value
		starttimemin = document.getElementById("starttimemin").value;
		stoptimehour = document.getElementById("stoptimehour").value;
		stoptimemin = document.getElementById("stoptimemin").value;
		
		timeRange = "||" + starttimehour + ":";
		timeRange += starttimemin + "-";
		timeRange += stoptimehour + ":";	
		timeRange += stoptimemin;		
				
		//get description for time range
		var tempdescr = document.getElementById("timerangedescr").value		
		
		if (nonrepeatingfound){
			nrtempTime += nrtempID;
			//add time ranges
			nrtempTime += timeRange;			
			//add description
			nrtempTime += "||" + tempdescr;
			insertElements(nrtempFriendlyTime, starttimehour, starttimemin, stoptimehour, stoptimemin, tempdescr, nrtempTime, nrtempID);
		}
		
		if (repeatingfound){
			rtempTime += rtempID;
			//add time ranges
			rtempTime += timeRange;
			//add description
			rtempTime += "||" + tempdescr;
			insertElements(rtempFriendlyTime, starttimehour, starttimemin, stoptimehour, stoptimemin, tempdescr, rtempTime, rtempID);
		}
		
	}
	else
	{
		//no days were selected, alert user
		alert ("You must select at least 1 day before adding time");
	}
}

function insertElements(tempFriendlyTime, starttimehour, starttimemin, stoptimehour, stoptimemin, tempdescr, tempTime, tempID){
	
		//add it to the schedule list
		d = document;
		tbody = d.getElementById("scheduletable").getElementsByTagName("tbody").item(0);
		tr = d.createElement("tr");
		td = d.createElement("td");
		td.innerHTML= "<span class='vexpl'>" + tempFriendlyTime + "<\/span>";
		tr.appendChild(td);	
			
		td = d.createElement("td");
		td.innerHTML="<input type='text' readonly class='vexpl' name='starttime" + schCounter + "' id='starttime" + schCounter + "' style=' word-wrap:break-word; width:100%; border:0px solid;' value='" + starttimehour + ":" + starttimemin + "' />";
		tr.appendChild(td);
		
		td = d.createElement("td");
		td.innerHTML="<input type='text' readonly class='vexpl' name='stoptime" + schCounter + "' id='stoptime" + schCounter + "' style=' word-wrap:break-word; width:100%; border:0px solid;' value='" + stoptimehour + ":" + stoptimemin + "' />";
		tr.appendChild(td);
		
		td = d.createElement("td");
		td.innerHTML="<input type='text' readonly class='vexpl' name='timedescr" + schCounter + "' id='timedescr" + schCounter + "' style=' word-wrap:break-word; width:100%; border:0px solid;' value='" + tempdescr + "' />";
		tr.appendChild(td);
		
		td = d.createElement("td");
		td.innerHTML = "<a onclick='editRow(\"" + tempTime + "\",this); return false;' href='#'><img border='0' src='/themes/" + theme + "/images/icons/icon_e.gif' alt='edit' /></\a>";
		tr.appendChild(td);
			
		td = d.createElement("td");
		td.innerHTML = "<a onclick='removeRow(this); return false;' href='#'><img border='0' src='/themes/" + theme + "/images/icons/icon_x.gif' alt='remove' /></\a>";
		tr.appendChild(td);
		
		td = d.createElement("td");		
		td.innerHTML="<input type='hidden' id='schedule" + schCounter + "' name='schedule" + schCounter + "' value='" + tempID + "' />";		
		tr.appendChild(td);
		tbody.appendChild(tr);
		
		schCounter++;
		
		//reset calendar and time and descr
		clearCalendar();
		clearTime();
		clearDescr();
}


function clearCalendar(){
	var tempstr, daycell = "";
	//clear days selected
	daysSelected = "";
	//loop through all 52 weeks
	for (j=1; j<=53; j++)
	{
		//loop through all 7 days
		for (k=1; k<8; k++){
			tempstr = 'w' + j + 'p' + k;
			daycell = eval('document.getElementById(tempstr)');
			if (daycell != null){
				daycell.style.backgroundColor = "#FFFFFF";  // white	
			}	
		}
	}	
}

function clearTime(){
	document.getElementById("starttimehour").value = "0";
	document.getElementById("starttimemin").value = "00";
	document.getElementById("stoptimehour").value = "23";
	document.getElementById("stoptimemin").value = "59";
}

function clearDescr(){
	document.getElementById("timerangedescr").value = "";
}

function editRow(incTime, el) {
	var check = checkForRanges();
	
	if (check){  
		
		//reset calendar and time
		clearCalendar();
		clearTime();
		
		var starttimehour, descr, days, tempstr, starttimemin, hours, stoptimehour, stoptimemin = ""; 
		
		tempArray = incTime.split ("||");
		
		days = tempArray[0];
		hours = tempArray[1];
		descr = tempArray[2];
		
		var tempdayArray = days.split(",");
		var temphourArray = hours.split("-");
		tempstr = temphourArray[0];
		var temphourArray2 = tempstr.split(":");
	
		document.getElementById("starttimehour").value = temphourArray2[0];
		document.getElementById("starttimemin").value = temphourArray2[1];	
		
		tempstr = temphourArray[1];
		temphourArray2 = tempstr.split(":");
		
		document.getElementById("stoptimehour").value = temphourArray2[0];
		document.getElementById("stoptimemin").value = temphourArray2[1];
		
		document.getElementById("timerangedescr").value = descr;
	
		//toggle the appropriate days
		for (i=0; i<tempdayArray.length; i++)
		{
			if (tempdayArray[i]){
				var tempweekstr = tempdayArray[i];
				dashpos = tempweekstr.search("-");			
						
				if (dashpos == "-1")
				{
					tempstr = "w2p" + tempdayArray[i];
				}
				else
				{
					tempstr = tempdayArray[i];
				}
				daytoggle(tempstr);
			}
		}
		removeRownoprompt(el);
	}
}

function removeRownoprompt(el) {
    var cel;
    while (el && el.nodeName.toLowerCase() != "tr")
	    el = el.parentNode;

    if (el && el.parentNode) {
	cel = el.getElementsByTagName("td").item(0);
	el.parentNode.removeChild(el);
    }
}


function removeRow(el) {
	var check = confirm ("Do you really want to delete this time range?");
	if (check){
	    var cel;
	    while (el && el.nodeName.toLowerCase() != "tr")
		    el = el.parentNode;
	
	    if (el && el.parentNode) {
		cel = el.getElementsByTagName("td").item(0);
		el.parentNode.removeChild(el);
	    }
	}
}
//]]>
</script>
EOD;
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" onload="<?= $jsevents["body"]["onload"] ?>">


<?php include("fbegin.inc");	echo $jscriptstr; ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<div id="inputerrors"></div>

<form action="firewall_schedule_edit.php" method="post" name="iform" id="iform">
	<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="firewall schedule">
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?=gettext("Schedule information");?></td>
		</tr>	
        <tr>
          <td>
			  <table width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
               	<tr>
				  <td width="15%" valign="top" class="vncellreq"><?=gettext("Schedule Name");?></td>
				  <td width="85%" class="vtable">
				  <?php if(is_schedule_inuse($pconfig['name']) == true): ?>
				  			<input name="name" type="hidden" id="name" size="40"  value="<?=htmlspecialchars($pconfig['name']);?>" />
						  <?php echo $pconfig['name']; ?>
						      <p>
						        <span class="vexpl"><?=gettext("NOTE: This schedule is in use so the name may not be modified!");?></span>
						      </p>
				<?php else: ?>
				  <input name="name" type="text" id="name" size="40" maxlength="40" class="formfld unknown" value="<?=htmlspecialchars($pconfig['name']);?>" /><br />
				      	<span class="vexpl">
     					   <?=gettext("The name of the alias may only consist of the characters a-z, A-Z and 0-9");?>
      					</span>
      			<?php endif; ?>   					
				  </td>
				</tr>
				<tr>
					<td width="15%" valign="top" class="vncell"><?=gettext("Description");?></td>
					<td width="85%" class="vtable"><input name="descr" type="text" id="descr" size="40" maxlength="40" class="formfld unknown" value="<?=htmlspecialchars($pconfig['descr']);?>" /><br />
 						<span class="vexpl">
				        	<?=gettext("You may enter a description here for your reference (not parsed).");?>
				      	</span>
				  
					</td>
				</tr>
				<!-- tr>
				</tr -->
			    <tr>
				  <td width="15%" valign="top" class="vncellreq"><?=gettext("Month");?></td>
				  <td width="85%" class="vtable">
                    <select name="monthsel" class="formselect" id="monthsel" onchange="update_month();">
                    	<?php 
                    	$monthcounter = date("n");
                    	$monthlimit = $monthcounter + 12;
                    	$yearcounter = date("Y");
                    	for ($k=0; $k<12; $k++){?>	             
                    		<option value="<?php echo $monthcounter;?>"><?php echo date("F_y", mktime(0, 0, 0, date($monthcounter), 1, date($yearcounter)));?></option>
                          <?php        	
                          if ($monthcounter == 12)
							{
								$monthcounter = 1;
								$yearcounter++;
							}
							else
							{
								$monthcounter++;
							}	
						} ?>      	
                    </select><br /><br />
            		<?php
            		$firstmonth = TRUE;
            		$monthcounter = date("n");
            		$yearcounter = date("Y");
            		for ($k=0; $k<12; $k++){
						$firstdayofmonth = date("w", mktime(0, 0, 0, date($monthcounter), 1, date($yearcounter)));
						if ($firstdayofmonth == 0)
							$firstdayofmonth = 7;
							
						$daycounter = 1;
						//number of day in month
						$numberofdays = date("t", mktime(0, 0, 0, date($monthcounter), 1, date($yearcounter)));
						$firstdayprinted = FALSE;
						$lasttr = FALSE;
						$positioncounter = 1;//7 for Sun, 1 for Mon, 2 for Tues, etc						
						?>	
	                        <div id="<?php echo date("F_y",mktime(0, 0, 0, date($monthcounter), 1, date($yearcounter)));?>" style=" position:relative; display:<?php if($firstmonth)echo "block";else echo "none";?>">    	
		                   	<table border="1" cellspacing="1" cellpadding="1" id="calTable<?=$monthcounter . $yearcounter;?>" class="tabcont" summary="month">
								<tr><td colspan="7" align="center" class="listbg"><b><?php echo date("F_Y", mktime(0, 0, 0, date($monthcounter), 1, date($yearcounter)));?></b></td>
								</tr>
								<tr>	
									<td align="center" class="listhdrr" style="cursor: pointer;" onclick="daytoggle('w1p1');"><u><b><?=gettext("Mon");?></b></u></td>
									<td align="center" class="listhdrr" style="cursor: pointer;" onclick="daytoggle('w1p2');"><u><b><?=gettext("Tue");?></b></u></td>
									<td align="center" class="listhdrr" style="cursor: pointer;" onclick="daytoggle('w1p3');"><u><b><?=gettext("Wed");?></b></u></td>
									<td align="center" class="listhdrr" style="cursor: pointer;" onclick="daytoggle('w1p4');"><u><b><?=gettext("Thu");?></b></u></td>
									<td align="center" class="listhdrr" style="cursor: pointer;" onclick="daytoggle('w1p5');"><u><b><?=gettext("Fri");?></b></u></td>
									<td align="center" class="listhdrr" style="cursor: pointer;" onclick="daytoggle('w1p6');"><u><b><?=gettext("Sat");?></b></u></td>
									<td align="center" class="listhdrr" style="cursor: pointer;" onclick="daytoggle('w1p7');"><u><b><?=gettext("Sun");?></b></u></td>
								</tr>
								<?php			
								$firstmonth = FALSE;				
								while ($daycounter<=$numberofdays){
									$weekcounter =  date("W", mktime(0, 0, 0, date($monthcounter), date($daycounter), date($yearcounter)));
									$weekcounter = ltrim($weekcounter, "0");
									if ($positioncounter == 1)
									{
										echo "<tr>";
									}											
									if ($firstdayofmonth == $positioncounter){?>
										<td align="center" style="cursor: pointer;" class="listr" id="w<?=$weekcounter;?>p<?=$positioncounter;?>" onclick="daytoggle('w<?=$weekcounter;?>p<?=$positioncounter;?>-m<?=$monthcounter;?>d<?=$daycounter;?>');">
										<?php echo $daycounter;
										$daycounter++;
										$firstdayprinted = TRUE;
										echo "</td>";
									}
									elseif ($firstdayprinted == TRUE && $daycounter <= $numberofdays){?>
										<td align="center" style="cursor: pointer;" class="listr" id="w<?=$weekcounter;?>p<?=$positioncounter;?>" onclick="daytoggle('w<?=$weekcounter;?>p<?=$positioncounter;?>-m<?=$monthcounter;?>d<?=$daycounter;?>');">
										<?php echo $daycounter;
										$daycounter++;
										echo "</td>";
									}
									else
									{
										echo "<td align=\"center\" class=\"listr\"></td>";
									}
									
									if ($positioncounter == 7 || $daycounter > $numberofdays){
										$positioncounter = 1;
										echo "</tr>";
									}
									else{
										$positioncounter++;
									}
								
								}//end while loop?>	
							</table>
							</div>
					<?php 
						
						if ($monthcounter == 12)
						{
							$monthcounter = 1;
							$yearcounter++;
						}
						else
						{
							$monthcounter++;
						}					
					} //end for loop
					?>
							<br />
					<?=gettext("Click individual date to select that date only. Click the appropriate weekday Header to select all occurrences of that weekday.");?>
	                 </td>
				</tr>
				<tr>
				  <td width="15%" valign="top" class="vncellreq"><?=gettext("Time");?></td>
				  <td width="85%" class="vtable">
				  	<table cellspacing="2" class="tabcont" summary="time">
				  		<tr>
				  			<td class="listhdrr" align="center"><?=gettext("Start Time");?></td><td></td><td class="listhdrr" align="center"><?=gettext("Stop Time");?></td>
				  		</tr>
				  		<tr>
				  			<td>
				  				<select name="starttimehour" class="formselect" id="starttimehour">
				  					<?php 
				  						for ($i=0; $i<24; $i++)
				  						{				  							
				  							echo "<option value=\"$i\">";
				  							echo $i;
				  							echo "</option>";
				  						}
				  					?>
				  				</select>&nbsp;<?=gettext("Hr"); ?>&nbsp;&nbsp;
				  				<select name="starttimemin" class="formselect" id="starttimemin">
				  					<option value="00">00</option>
				  					<option value="15">15</option>
				  					<option value="30">30</option>
				  					<option value="45">45</option>
				  					<option value="59">59</option>
				  				</select>&nbsp;<?=gettext("Min"); ?>
				  			</td>
				  			<td></td>
				  			<td>
				  				<select name="stoptimehour" class="formselect" id="stoptimehour">
				  				<?php 
				  						for ($i=0; $i<24; $i++)
				  						{
				  							if ($i==23)
				  								$selected = "selected=\"selected\"";
				  							else
				  								$selected = "";
				  								
				  							echo "<option value=\"$i\" $selected>";
				  							echo $i;
				  							echo "</option>";
				  						}
				  					?>
				  				</select>&nbsp;<?=gettext("Hr");?>&nbsp;&nbsp;
				  				<select name="stoptimemin" class="formselect" id="stoptimemin">
				  					<option value="00">00</option>
				  					<option value="15">15</option>
				  					<option value="30">30</option>
				  					<option value="45">45</option>
				  					<option value="59" selected="selected">59</option>
				  				</select>&nbsp;<?=gettext("Min");?>
				  			</td>
				  		</tr>
				  	</table><br />
                   <?=gettext("Select the time range for the day(s) selected on the Month(s) above. A full day is 0:00-23:59.")?>
					</td>
				</tr>
				<tr>
					<td width="15%" valign="top" class="vncell"><?=gettext("Time Range Description")?></td>
					<td width="85%" class="vtable"><input name="timerangedescr" type="text" class="formfld unknown" id="timerangedescr" size="40" maxlength="40" /><br />
 						<span class="vexpl">
				        	<?=gettext("You may enter a description here for your reference (not parsed).")?>
				      	</span>     
				      </td>					
				</tr>
				<tr>
				  <td width="22%" valign="top">&nbsp;</td>
				  <td width="78%">
				  	<input type="button" value="<?=gettext("Add Time");?>"  class="formbtn"  onclick="javascript:processEntries();" />&nbsp;&nbsp;&nbsp;
				  	<input type="button" value="<?=gettext("Clear Selection");?>" class="formbtn" onclick="javascript:clearCalendar(); clearTime(); clearDescr();" />
                    </td>
				</tr>
				<tr>
				  <td width="15%" valign="top" class="vtable"></td>
				  <td width="85%" class="vtable">
                    </td>
				</tr>
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?=gettext("Schedule repeat");?></td>
				</tr>	
				<tr>
					<td width="15%" valign="top" class="vncellreq"><?=gettext("Configured Ranges");?></td>
					<td width="85%">
						<table id="scheduletable" summary="range">
							<tbody>
								<tr>
									<td align="center" class="listbg" width="35%"><?=gettext("Day(s)");?></td>
									<td align="center" class="listbg" width="12%"><?=gettext("Start Time");?></td>
									<td align="center" class="listbg" width="11%"><?=gettext("Stop Time");?></td>
									<td align="center" class="listbg" width="42%"><?=gettext("Description");?></td>
								</tr>
								<?php
								if ($getSchedule){
									$counter = 0;
																		
									foreach($pconfig['timerange'] as $timerange) {
										$tempFriendlyTime = "";
										$tempID = "";
										if ($timerange){
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
											
											if ($timerange['month']){
												$tempmontharray = explode(",", $timerange['month']);
												$tempdayarray = explode(",",$timerange['day']);
												$arraycounter = 0;
												foreach ($tempmontharray as $monthtmp){
													$month = $tempmontharray[$arraycounter];
													$day = $tempdayarray[$arraycounter];
													$daypos = date("w", mktime(0, 0, 0, date($month), date($day), date("Y")));
													//if sunday, set position to 7 to get correct week number. This is due to php limitations on ISO-8601. When we move to php5.1 we can change this.
													if ($daypos == 0){
														$daypos = 7;
													}									
													$weeknumber = date("W", mktime(0, 0, 0, date($month), date($day), date("Y")));
													$weeknumber = ltrim($weeknumber, "0");		
																										
													if ($firstPrint)
													{
														$tempID .= ",";
													}
													$tempID .= "w" . $weeknumber . "p" . $daypos . "-m" .  $month . "d" . $day;
													$firstPrint = true;
													
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
														if ($firstprint2)
															$tempFriendlyTime .= ", ";
														$currentDay--;
														if ($currentDay != $firstDay)
															$tempFriendlyTime .= $monthArray[$firstmonth-1] . " " . $firstDay . " - " . $currentDay ;
														else
															$tempFriendlyTime .=  $monthArray[$month-1] . " " . $day;
														$firstDayFound = false;	
														$firstprint2 = true;
													}													
													$arraycounter++;			
												}																						
												
											}
											else
											{
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
											if (!$timerange['month']){										
												foreach ($tempFriendlyDayArray as $day){
													if ($day != ""){
														if (!$firstDayFound)
														{
															$firstDay = $tempFriendlyDayArray[$i];
															$firstDayFound = true;
														}
														$currentDay =$tempFriendlyDayArray[$i];
														//get next day
														$nextDay = $tempFriendlyDayArray[$i+1];
														$currentDay++;					
														if ($currentDay != $nextDay){
															if ($firstprint)
																$tempFriendlyTime .= ", ";
															$currentDay--;
															if ($currentDay != $firstDay)
																$tempFriendlyTime .= $dayArray[$firstDay-1] . " - " . $dayArray[$currentDay-1];
															else
																$tempFriendlyTime .= $dayArray[$firstDay-1];
															$firstDayFound = false;	
															$firstprint = true;			
														}
														$i++;
													}
												}		
											}	
												
																																													
									?>
						          <tr>
						          	<td>
						          		<span class="vexpl"><?php echo $tempFriendlyTime; ?></span>
						          	</td>
									<td>
						              <input type='text' readonly='readonly' class='vexpl' name='starttime<?php echo $counter; ?>' id='starttime<?php echo $counter; ?>' style=' word-wrap:break-word; width:100%; border:0px solid;' value='<?php echo $starttime; ?>' />
							        </td>
						            <td>
						              <input type='text' readonly='readonly' class='vexpl' name='stoptime<?php echo $counter; ?>' id='stoptime<?php echo $counter; ?>' style=' word-wrap:break-word; width:100%; border:0px solid;' value='<?php echo $stoptime; ?>' /> 
							        </td>
							        <td>
							        	<input type='text' readonly='readonly' class='vexpl' name='timedescr<?php echo $counter; ?>' id='timedescr<?php echo $counter; ?>' style=' word-wrap:break-word; width:100%; border:0px solid;' value='<?php echo $timedescr; ?>' />
							        </td>
							        <td>
							        	<a onclick='editRow("<?php echo $tempTime; ?>",this); return false;' href='#'><img border='0' src='/themes/<?php echo $g['theme']; ?>/images/icons/icon_e.gif' alt='edit' /></a>
							        </td>
							        <td>
							        	<a onclick='removeRow(this); return false;' href='#'><img border='0' src='/themes/<?php echo $g['theme']; ?>/images/icons/icon_x.gif' alt='remove' /></a>
							        </td>
							        <td>
							        	<input type='hidden' id='schedule<?php echo $counter; ?>' name='schedule<?php echo $counter; ?>' value='<?php echo $tempID; ?>' />
							        </td>
						          </tr>
									<?php
						        $counter++;
									}//end if						
						        } // end foreach	
								}//end if							
								?>
							</tbody>	
						</table>				
					</td>
				</tr>
			 	<tr>
				    <td width="15%" valign="top">&nbsp;</td>
				    <td width="85%">
				      <input id="submit" name="submit" type="submit" onclick="return checkForRanges();" class="formbtn" value="<?=gettext("Save"); ?>" />
				      <input type="button" class="formbtn" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=$referer;?>'" />
				      <?php if (isset($id) && $a_schedules[$id]): ?>
				      <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
				      <?php endif; ?>
				    </td>
			  	</tr>
			 </table>
		
</td></tr></table></form>
<?php include("fend.inc"); ?>
</body>
</html>
