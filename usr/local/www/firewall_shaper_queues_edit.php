#!/usr/local/bin/php
<?php
/*
	firewall_shaper_queues_edit.php
	part of m0n0wall (http://m0n0.ch/wall)

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

require("guiconfig.inc");

$a_queues = &$config['pfqueueing']['queue'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_queues[$id]) {
	$pconfig['bandwidth'] = $a_queues[$id]['bandwidth'] . $a_queues[$id]['bandwidthtype'];
	$pconfig['priority'] = $a_queues[$id]['priority'];
	$pconfig['mask'] = $a_queues[$id]['mask'];
	$pconfig['name'] = $a_queues[$id]['name'];
	$pconfig['options'] = $a_queues[$id]['options'];

	if(isset($pconfig['options']['red'])) {
		$pconfig['options']['red'] = $queue['options']['red'];
		$red = "on";
	}
	if(isset($pconfig['options']['ecn'])) {
		$pconfig['options']['ecn'] = $queue['options']['ecn'];
		$ecn = "on";
	}
	if(isset($pconfig['options']['default'])) {
		$pconfig['options']['default'] = $queue['options']['default'];
		$default = "on";
	}

	$pconfig['bandwidth'] = $a_queues[$id]['bandwidth'];
	$pconfig['bandwidthtype'] = $a_queues[$id]['bandwidthtype'];

}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "priority");
	$reqdfieldsn = explode(",", "Priority");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if ($_POST['priority'] && (!is_numericint($_POST['priority'])
			|| ($_POST['priority'] < 1) || ($_POST['priority'] > 100))) {
		$input_errors[] = "The priority must be an integer between 1 and 100.";
	}

	if (!$input_errors) {
		$queue = array();

		$queue['schedulertype'] = $_POST['scheduler'];
		$queue['bandwidth'] = $_POST['bandwidth'];
		$queue['priority'] = $_POST['priority'];
		$queue['name'] = ereg_replace(" ", "", $_POST['name']);

		$scheduleroptions="";
		if($_POST['red'] == "on")
			$queue['options']['red'] = "enabled";

		if($_POST['ecn'] == "on")
			$queue['options']['ecn'] = "enabled";

		if($_POST['default'] == "on")
			$queue['options']['default'] = "eanbled";

		if (isset($id) && $a_queues[$id])
			$a_queues[$id] = $queue;
		else
			$a_queues[] = $queue;

		write_config();
		touch($d_shaperconfdirty_path);

		header("Location: firewall_shaper_queues.php");
		exit;
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Firewall: Traffic shaper: Edit queue");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Firewall: Traffic shaper: Edit queue</p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
	<form action="firewall_shaper_queues_edit.php" method="post" name="iform" id="iform">
	  <table width="100%" border="0" cellpadding="6" cellspacing="0">
	    <tr>
	      <td valign="top" class="vncellreq">Bandwidth</td>
	      <td class="vtable"> <input name="bandwidth" class="formfld" value="<?=htmlspecialchars($pconfig['bandwidth']);?>">
		<select name="bandwidthtype">
			<option value="<?=htmlspecialchars($pconfig['bandwidthtype']);?>"><?=htmlspecialchars($pconfig['bandwidthtype']);?></option>
			<option value="b">bit/s</option>
			<option value="Kb">Kilobit/s</option>
			<option value="Mb">Megabit/s</option>
			<option value="Gb">Gigabit/s</option>
		</select>
	      		<br>
		<span class="vexpl">Choose the amount of bandwidth for this queue
		</span></td>
	    </tr>
	    <tr>
	      <td width="22%" valign="top" class="vncellreq">Priority</td>
	      <td width="78%" class="vtable"> <input name="priority" type="text" id="priority" size="5" value="<?=htmlspecialchars($pconfig['priority']);?>">
		<br> <span class="vexpl">The priority of the queue. For class based queueing the priority range is 0 to 7 and for priority based queueing the range is 0 to 15. Priority 0 is the lowest priority. When not specified, a default of 1 is used.
		</span></td>
	    </tr>
	    <tr>
	      <td width="22%" valign="top" class="vncell"><b>Name</b></td>
	      <td width="78%" class="vtable"> <input name="name" type="text" class="formfld" id="name" size="40" value="<?=htmlspecialchars($pconfig['name']);?>">
		<br> <span class="vexpl">Enter the name of the queue here.  Do not use spaces!
		</span></td>
	    </tr>

	    <tr>
	      <td width="22%" valign="top" class="vncell"><b>Scheduler</b> </td>
	      <td width="78%" class="vtable">
		<select name="scheduler">
			<option value="priq">Priority based queueing</option>
			<!--<option value="cbq">Class based queueing</option>-->
			<!--<option value="hfsc">Hierarchical Fair Service Curve queueing</option>-->
		</select>
		<br> <span class="vexpl">Select which type of queueing you would like to use
		</span></td>
	    </tr>
	    <tr>
	      <td width="22%" valign="top" class="vncell">Scheduler options</td>
	      <td width="78%" class="vtable">
	      <?php
		$red = strpos($pconfig['options'], "red");
		$ecn = strpos($pconfig['options'], "ecn");
		$upperlimit = strpos($pconfig['options'], "upperlimit");
		$realtime = strpos($pconfig['options'], "realtime");
		$parentqueue = strpos($pconfig['options'], "parentqueue");
		$linkshare = strpos($pconfig['options'], "linkshare");
		$default = strpos($pconfig['options'], "default");
	      ?>
	        <input type=checkbox name="default" <?php if($default) echo " CHECKED";?> > Default (Classed based queueing only)<br>
		<input type=checkbox name="parentqueue" <?php if($parentqueue) echo " CHECKED";?> > This is a parent queue of HFSC/CBQ<br>
		<input type=checkbox name="red" <?php if($red) echo " CHECKED";?> > Random Early Detection<br>
		<input type=checkbox name="ecn" <?php if($ecn) echo " CHECKED";?> > Explicit Congestion Notification<br>
		<input type=checkbox name="upperlimit" <?php if($upperlimit) echo " CHECKED";?> > Upperlimit: <input size="3" name="upperlimit1"> <input size="3" name="upperlimit2"> <input size="3" name="upperlimit3"> <br>
		<input type=checkbox name="realtime" <?php if($realtime) echo " CHECKED";?> > Real time: <input size="3" name="realtime1"> <input size="3" name="realtime2"> <input size="3" name="realtime3"><br>
		<input type=checkbox name="linkshare" <?php if($linkshare) echo " CHECKED";?> > Link share: <input size="3" name="linkshare1"> <input size="3" name="linkshare2"> <input size="3" name="linkshare3"><br>				
		<br> <span class="vexpl">Select options for this queue
		</span></td>
	    </tr>

	    <tr>
		<td width="22%" valign="top" class="vncell">Parent queue (CBQ or HFSC only):</td>
		<td width="78%" class="vtable">
		   <select name="childqueue">
			<?php
			 if (is_array($config['pfqueueing']['queue'])) {
			 	foreach ($config['pfqueueing']['queue'] as $queue) {
			 		if(is_subqueue($queue['name']) == 0)
			 			echo "<option value=\"" . $queue['name'] . "\">" . $queue['name'] . "</option>";
			 	}
			 }
			?>
		   </select>
		</td>
	    </tr>

	<!-- XXX: add javascript to show/hide queueing options such as low bandwidth (hfsc, cbq) -->

	    <tr>
	      <td width="22%" valign="top">&nbsp;</td>
	      <td width="78%"> <input name="Submit" type="submit" class="formbtn" value="Save">
		<?php if (isset($id) && $a_queues[$id]): ?>
		<input name="id" type="hidden" value="<?=$id;?>">
		<?php endif; ?>
	      </td>
	    </tr>
	  </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
