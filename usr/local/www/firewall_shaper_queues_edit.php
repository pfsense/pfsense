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

$wan_sched = $config['interfaces']['wan']['schedulertype'];
$lan_sched = $config['interfaces']['lan']['schedulertype'];

if (isset($id) && $a_queues[$id]) {
	$pconfig['bandwidth'] = $a_queues[$id]['bandwidth'] . $a_queues[$id]['bandwidthtype'];
	$pconfig['priority'] = $a_queues[$id]['priority'];
	$pconfig['mask'] = $a_queues[$id]['mask'];
	$pconfig['name'] = $a_queues[$id]['name'];
	$pconfig['options'] = $a_queues[$id]['options'];
	$pconfig['options']['red'] = $a_queues[$id]['options']['red'];
	$pconfig['options']['ecn'] = $a_queues[$id]['options']['ecn'];
	$pconfig['options']['rio'] = $a_queues[$id]['options']['rio'];
	$pconfig['options']['borrow'] = $a_queues[$id]['options']['borrow'];
	$pconfig['options']['defaultqueue'] = $a_queues[$id]['options']['defaultqueue'];
	$pconfig['options']['parentqueue'] = $a_queues[$id]['options']['parentqueue'];
	$pconfig['options']['upperlimit1'] = $a_queues[$id]['options']['upperlimit1'];
	$pconfig['options']['upperlimit2'] = $a_queues[$id]['options']['upperlimit2'];
	$pconfig['options']['upperlimit3'] = $a_queues[$id]['options']['upperlimit3'];
	$pconfig['options']['realtime1'] = $a_queues[$id]['options']['realtime1'];
	$pconfig['options']['realtime2'] = $a_queues[$id]['options']['realtime2'];
	$pconfig['options']['realtime3'] = $a_queues[$id]['options']['realtime3'];
	$pconfig['options']['linkshare1'] = $a_queues[$id]['options']['linkshare1'];
	$pconfig['options']['linkshare2'] = $a_queues[$id]['options']['linkshare2'];
	$pconfig['options']['linkshare3'] = $a_queues[$id]['options']['linkshare3'];
	$pconfig['schedulertype'] = $a_queues[$id]['schedulertype'];
	$pconfig['bandwidth'] = $a_queues[$id]['bandwidth'];
	$pconfig['bandwidthtype'] = $a_queues[$id]['bandwidthtype'];
	$pconfig['associatedrule'] = $a_queues[$id]['associatedrule'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	//$reqdfields = explode(" ", "priority");
	//$reqdfieldsn = explode(",", "Priority");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if ($_POST['priority'] && (!is_numericint($_POST['priority'])
			|| ($_POST['priority'] < 1) || ($_POST['priority'] > 100))) {
		$input_errors[] = "The priority must be an integer between 1 and 100.";
	}

	if (!$input_errors) {
		$queue = array();

		$queue['schedulertype'] = $_POST['schedulertype'];
		$queue['bandwidth'] = $_POST['bandwidth'];
		$queue['bandwidthtype'] = $_POST['bandwidthtype'];
		$queue['priority'] = $_POST['priority'];
		$queue['name'] = ereg_replace(" ", "", $_POST['name']);
		$queue['options']['borrow'] = $_POST['borrow'];
		$queue['options']['linkshare'] = $_POST['linkshare'];
		$queue['options']['linkshare3'] = $_POST['linkshare3'];
		$queue['options']['linkshare2'] = $_POST['linkshare2'];
		$queue['options']['linkshare1'] = $_POST['linkshare1'];
		$queue['options']['realtime'] = $_POST['realtime'];
		$queue['options']['realtime3'] = $_POST['realtime3'];
		$queue['options']['realtime2'] = $_POST['realtime2'];
		$queue['options']['realtime1'] = $_POST['realtime1'];
		$queue['options']['upperlimit'] = $_POST['upperlimit'];
		$queue['options']['upperlimit3'] = $_POST['upperlimit3'];
		$queue['options']['upperlimit2'] = $_POST['upperlimit2'];
		$queue['options']['upperlimit1'] = $_POST['upperlimit1'];
		$queue['options']['parentqueue'] = $_POST['parentqueue'];
		$queue['options']['associatedrule'] = $_POST['associatedrule'];
		$scheduleroptions="";
		$queue['options']['rio'] = $_POST['rio'];
		$queue['options']['red'] = $_POST['red'];
		$queue['options']['ecn'] = $_POST['ecn'];
		$queue['options']['defaultqueue'] = $_POST['defaultqueue'];
		if (isset($id) && $a_queues[$id])
			$a_queues[$id] = $queue;
		else
			$a_queues[] = $queue;

		write_config();

		foreach($config['filter']['rule'] as $rule) {
			if($rule['descr'] == $_POST['associatedrule']) {
				$rule['queue'] = $_POST['associatedrule'];
			}
		}

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

<script language="JavaScript">
<!--
function sync_scheduler_options() {
	var wan = '<?=$wan_sched?>';
	var lan = '<?=$lan_sched?>';
	var indexNum = document.forms[0].associatedrule.selectedIndex;
	var associatedrule = document.forms[0].associatedrule.options[indexNum].text;
	var tmp = associatedrule.split(" - ");
	var interface_type_a = '' + eval(tmp[0]) + '';
	var interface_type = String(interface_type_a);
	if(interface_type == 'priq') {
		document.forms[0].rio.disabled = 0;
		document.forms[0].bandwidth.disabled = 1;
		document.forms[0].bandwidthtype.disabled = 1;
		document.forms[0].bandwidth.value = "";
		document.forms[0].bandwidthtype.value = "";
		document.forms[0].defaultqueue.disabled = 0;
		document.forms[0].borrow.disabled = 1;
		document.forms[0].parentqueue.disabled = 1;
		document.forms[0].red.disabled = 0;
		document.forms[0].ecn.disabled = 0;
		document.forms[0].upperlimit.disabled = 1;
		document.forms[0].upperlimit1.disabled = 1;
		document.forms[0].upperlimit2.disabled = 1;
		document.forms[0].upperlimit3.disabled = 1;
		document.forms[0].realtime.disabled = 1;
		document.forms[0].realtime1.disabled = 1;
		document.forms[0].realtime2.disabled = 1;
		document.forms[0].realtime3.disabled = 1;
		document.forms[0].linkshare.disabled = 1;
		document.forms[0].linkshare1.disabled = 1;
		document.forms[0].linkshare2.disabled = 1;
		document.forms[0].linkshare3.disabled = 1;
		document.forms[0].childqueue.disabled = 1;
		document.forms[0].priority.disabled = 0;
	} else if(interface_type == 'cbq') {
		document.forms[0].rio.disabled = 0;
		document.forms[0].borrow.disabled = 0;
		document.forms[0].bandwidth.disabled = 0;
		document.forms[0].bandwidthtype.disabled = 0;
		document.forms[0].defaultqueue.disabled = 0;
		document.forms[0].parentqueue.disabled = 0;
		document.forms[0].red.disabled = 0;
		document.forms[0].ecn.disabled = 0;
		document.forms[0].upperlimit.disabled = 1;
		document.forms[0].upperlimit1.disabled = 1;
		document.forms[0].upperlimit2.disabled = 1;
		document.forms[0].upperlimit3.disabled = 1;
		document.forms[0].realtime.disabled = 1;
		document.forms[0].realtime1.disabled = 1;
		document.forms[0].realtime2.disabled = 1;
		document.forms[0].realtime3.disabled = 1;
		document.forms[0].linkshare.disabled = 1;
		document.forms[0].linkshare1.disabled = 1;
		document.forms[0].linkshare2.disabled = 1;
		document.forms[0].linkshare3.disabled = 1;
		document.forms[0].childqueue.disabled = 0;
		document.forms[0].priority.disabled = 0;
	} else if(interface_type == 'hfsc') {
		document.forms[0].rio.disabled = 0;
		document.forms[0].borrow.disabled = 0;
		document.forms[0].bandwidth.disabled = 0;
		document.forms[0].bandwidthtype.disabled = 0;
		document.forms[0].red.disabled = 0;
		document.forms[0].ecn.disabled = 0;
		document.forms[0].defaultqueue.disabled = 0;
		document.forms[0].parentqueue.disabled = 0;
		document.forms[0].upperlimit.disabled = 0;
		document.forms[0].upperlimit1.disabled = 0;
		document.forms[0].upperlimit2.disabled = 0;
		document.forms[0].upperlimit3.disabled = 0;
		document.forms[0].realtime.disabled = 0;
		document.forms[0].realtime1.disabled = 0;
		document.forms[0].realtime2.disabled = 0;
		document.forms[0].realtime3.disabled = 0;
		document.forms[0].linkshare.disabled = 0;
		document.forms[0].linkshare1.disabled = 0;
		document.forms[0].linkshare2.disabled = 0;
		document.forms[0].linkshare3.disabled = 0;
		document.forms[0].childqueue.disabled = 0;
		document.forms[0].priority.disabled = 0;
	}
}
-->
</script>
</head>

<body onLoad="sync_scheduler_options();" link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php include("fbegin.inc"); ?>
<?php
	$red = $pconfig['options']["red"];
	$ecn = $pconfig['options']["ecn"];
	$rio = $pconfig['options']["rio"];
	$borrow = $pconfig['options']["borrow"];
	$upperlimit = $pconfig['options']["upperlimit"];
	$upperlimit1 = $pconfig['options']["upperlimit1"];
	$upperlimit2 = $pconfig['options']["upperlimit2"];
	$upperlimit3 = $pconfig['options']["upperlimit3"];
	$realtime = $pconfig['options']["realtime"];
	$realtime1 = $pconfig['options']["realtime1"];
	$realtime2 = $pconfig['options']["realtime2"];
	$realtime3 = $pconfig['options']["realtime3"];
	$linkshare = $pconfig['options']["linkshare"];
	$linkshare1 = $pconfig['options']["linkshare1"];
	$linkshare2 = $pconfig['options']["linkshare2"];
	$linkshare3 = $pconfig['options']["linkshare3"];
	$parentqueue = $pconfig['options']["parentqueue"];
	$defaultqueue = $pconfig['options']["defaultqueue"];
	$parent = $pconfig['options']["parent"];
	$schedulertype = $pconfig["schedulertype"];
?>
<p class="pgtitle">Firewall: Traffic shaper: Edit queue</p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
	<form action="firewall_shaper_queues_edit.php" method="post" name="iform" id="iform">
	  <table width="100%" border="0" cellpadding="6" cellspacing="0">
	    <tr>
	      <td valign="top" class="vncellreq">Associate with rule</td>
	      <td class="vtable">
		<select name="associatedrule" onChange="sync_scheduler_options();">
		<?php
			if($pconfig['options']['associatedrule']) {
				echo "<option value=\"" . $pconfig['options']['associatedrule'] ."\">" . filter_get_rule_interface($pconfig['options']['associatedrule']) . " - " . filter_get_altq_queue_scheduler_type($pconfig['options']['associatedrule']) . " - " . $pconfig['options']['associatedrule'] . "</option>";
			}
			foreach ($config['filter']['rule'] as $rule) {
				echo "<option value=\"" . $rule['descr'] ."\">" . $rule['interface'] . " - " . filter_get_altq_queue_scheduler_type($rule['descr']) . " - " . $rule['descr'] . "</option>";
			}
		?>
		</select>
	      		<br>
		<span class="vexpl">Choose which rule to attach this queue to.
		</span></td>
	    </tr>
	    <tr>
	      <td valign="top" class="vncellreq">Bandwidth</td>
	      <td class="vtable"> <input name="bandwidth" class="formfld" value="<?=htmlspecialchars($pconfig['bandwidth']);?>">
		<select name="bandwidthtype">
			<option value="<?=htmlspecialchars($pconfig['bandwidthtype']);?>"><?=htmlspecialchars($pconfig['bandwidthtype']);?></option>
			<option value="b">bit/s</option>
			<option value="Kb">Kilobit/s</option>
			<option value="Mb">Megabit/s</option>
			<option value="Gb">Gigabit/s</option>
			<option value="%">Percent</option>
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
	      <td width="22%" valign="top" class="vncell">Scheduler options</td>
	      <td width="78%" class="vtable">
	        <input type="checkbox" id="defaultqueue" name="defaultqueue" <?php if($defaultqueue) echo " CHECKED";?> > Default queue<br>
		<input type="checkbox" id="borrow" name="borrow" <?php if($borrow) echo " CHECKED";?> > Borrow from other queues when evailable<br>
		<input type="checkbox" id="red" name="red" <?php if($red) echo " CHECKED";?> > Random Early Detection<br>
		<input type="checkbox" id="rio" name="rio" <?php if($rio) echo " CHECKED";?> > Random Early Detection In and Out<br>
		<input type="checkbox" id="ecn" name="ecn" <?php if($ecn) echo " CHECKED";?> > Explicit Congestion Notification<br>
		<input type="checkbox" id="parentqueue" name="parentqueue" <?php if($parentqueue) echo " CHECKED";?> > This is a parent queue of HFSC/CBQ<br>
		<input type="checkbox" id="upperlimit" name="upperlimit" <?php if($upperlimit) echo " CHECKED";?> > Upperlimit: <input size="3" value="<?=htmlspecialchars($upperlimit1);?>" name="upperlimit1"> <input size="3" value="<?=htmlspecialchars($upperlimit2);?>" name="upperlimit2"> <input size="3" value="<?=htmlspecialchars($upperlimit3);?>" name="upperlimit3"> <br>
		<input type="checkbox" id="realtime" name="realtime" <?php if($realtime) echo " CHECKED";?> > Real time: <input size="3" value="<?=htmlspecialchars($realtime1);?>" name="realtime1"> <input size="3" value="<?=htmlspecialchars($realtime2); ?>" name="realtime2"> <input size="3" value="<?=htmlspecialchars($realtime3);?>" name="realtime3"><br>
		<input type="checkbox" id="linkshare" id="linkshare" name="linkshare" <?php if($linkshare) echo " CHECKED";?> > Link share: <input size="3" value="<?=htmlspecialchars($linkshare1);?>" value="<?=htmlspecialchars($linkshare1);?>" id="linkshare1" name="linkshare1"> <input size="3" value="<?=htmlspecialchars($linkshare2);?>" id="linkshare2" name="linkshare2"> <input size="3" value="<?=htmlspecialchars($linkshare3);?>" id="linkshare3" name="linkshare3"><br>
		<br> <span class="vexpl">Select options for this queue
		</span></td>
	    </tr>
	    <tr>
		<td width="22%" valign="top" class="vncell">Parent queue (CBQ or HFSC only):</td>
		<td width="78%" class="vtable">
		   <select id="childqueue" name="childqueue">
			<?php
			if(isset($pconfig['pfqueueing']['childqueue']))
				echo "<option value=\"" . $pconfig['pfqueueing']['childqueue'] . "\">" . $pconfig['pfqueueing']['childqueue'] . "</option>";
			else
				echo "<option value=\"\"></option>";
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
<script language="JavaScript">
sync_scheduler_options();
</script>
</body>
</html>
