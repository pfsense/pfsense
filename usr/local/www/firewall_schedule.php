<?php
/*
	firewall_schedule.php
	Copyright (C) 2004 Scott Ullrich
	All rights reserved.

	originially part of m0n0wall (http://m0n0.ch/wall)
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


$pgtitle = "Firewall: Schedules";

$dayArray = array ('Mon','Tues','Wed','Thur','Fri','Sat','Sun');
$monthArray = array ('January','February','March','April','May','June','July','August','September','October','November','December');

require("guiconfig.inc");

if (!is_array($config['schedules']['schedule']))
	$config['schedules']['schedule'] = array();

schedule_sort();
$a_schedules = &$config['schedules']['schedule'];


if ($_GET['act'] == "del") {
	if ($a_schedules[$_GET['id']]) {
		/* make sure rule is not being referenced by any nat or filter rules */
		$is_schedule_referenced = false;
		$referenced_by = false;
		$schedule_name = $a_schedules[$_GET['id']]['name'];

		if(is_array($config['filter']['rule'])) {
			foreach($config['filter']['rule'] as $rule) {
				//check for this later once this is established
				if ($rule['sched'] == $schedule_name){
					$referenced_by = $rule['descr'];
					$is_schedule_referenced = true;
					break;
				}
			}
		}

		if($is_schedule_referenced == true) {
			$savemsg = "Cannot delete Schedule.  Currently in use by {$referenced_by}";
		} else {
			unset($a_schedules[$_GET['id']]);
			write_config();
			header("Location: firewall_schedule.php");
			exit;
		}
	}
}

include("head.inc");
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<?php if ($savemsg) print_info_box($savemsg); ?>
<form action="firewall_schedule.php" method="post">
	<table class="sortable" width="98%" border="0" cellpadding="0" cellspacing="0">
	<tr>
	  <td width="25%" class="listhdrr">Name</td>
	  <td width="35%" class="listhdrr">Time Range(s)</td>
	  <td width="35%" class="listhdr">Description</td>
	  <td width="5%" class="list sort_ignore"></td>
	</tr>
	<?php $i = 0; foreach ($a_schedules as $schedule): ?>
	<tr>
	   <td class="listlr" ondblclick="document.location='firewall_schedule_edit.php?id=<?=$i;?>';">
    <?=htmlspecialchars($schedule['name']);?>
  		</td>
  		<td class="listlr" ondblclick="document.location='firewall_schedule_edit.php?id=<?=$i;?>';">
			<?php 					

					foreach($schedule['timerange'] as $timerange) {
						$tempFriendlyTime = "";
						$tempID = "";
						$firstprint = false;
						if ($timerange){
							$dayFriendly = "";
							$tempFriendlyTime = "";							
							$daytimeseparator = strrpos($timerange, ",");
								
							//get hours
							$temptimerange = $timerange['hour'];
							$temptimeseparator = strrpos($temptimerange, "-");
							
							$starttime = substr ($temptimerange, 0, $temptimeseparator); 
							$stoptime = substr ($temptimerange, $temptimeseparator+1); 
								
							if ($timerange['month']){
								$tempmontharray = explode(",", $timerange['month']);
								$tempdayarray = explode(",",$timerange['day']);
								$arraycounter = 0;
								foreach ($tempmontharray as $monthtmp){
									if ($firstprint)
									{
										$dayFriendly .= ", ";										
									}	
									$month = $tempmontharray[$arraycounter];
									$day = $tempdayarray[$arraycounter];
									$monthstr = $monthArray[$month-1];
									$dayFriendly .= $monthstr . " " . $day;
									$arraycounter++;
									$firstprint = true;
				
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
		
						
							$dayFriendly .= " : " . $starttime . "-" . $stoptime . " : " . $timerange['rangedescr'];											
							echo $dayFriendly;	
							echo "<br/>";
						}
					}//end for?>
	  </td>
	 <td class="listbg" ondblclick="document.location='firewall_schedule_edit.php?id=<?=$i;?>';">
    	<font color="#FFFFFF">
    		<?=htmlspecialchars($schedule['descr']);?>&nbsp;
  		</td>
  		  <td valign="middle" nowrap class="list">
    <table border="0" cellspacing="0" cellpadding="1">
      <tr>
        <td valign="middle"><a href="firewall_schedule_edit.php?id=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" title="edit alias"></a></td>
        <td><a href="firewall_schedule.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this schedule?')"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" title="delete alias"></a></td>
      </tr>
    </table>
  </td>
</tr>
<?php $i++; endforeach; ?>
<tr>
  <td class="list" colspan="3"></td>
  <td class="list">
    <table border="0" cellspacing="0" cellpadding="1">
      <tr>
	        <td valign="middle">
	          <a href="firewall_schedule_edit.php">
	            <img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="<?=gettext("add a new schedule");?>" alt="" />
	          </a>
	         </td>
	      </tr>
	    </table>
	  </td>
	</tr>
	<tr>
	  <td class="tabcont" colspan="3">
	   <p><span class="vexpl"><span class="red"><strong>Note:<br></strong></span>Schedules act as placeholders for time ranges to be used in Firewall Rules.</span></p>
	  </td>
	</tr>
	</table>

</form>

<?php include("fend.inc"); ?>
</body>
</html>
