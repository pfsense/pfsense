#!/usr/local/bin/php
<?php
/* $Id$ */
/*
	firewall_shaper_queues_edit.php
	Copyright (C) 2004, 2005 Scott Ullrich
	All rights reserved.

	Originally part of m0n0wall (http://m0n0.ch/wall)
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

$a_queues = &$config['shaper']['queue'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

$pconfig['schedulertype'] = $config['system']['schedulertype'];
$schedulertype = $config['system']['schedulertype'];

if (isset($id)) {
	if (!is_numeric($id)) {
		$i = 0;
		foreach($config['shaper']['queue'] as $queue) {
			if ($queue['name'] == $id) {
				$id = $i;
				break;
			} else
				$i++;
		}
	}
	if ($a_queues[$id]) {
		$pconfig['priority'] = $a_queues[$id]['priority'];
		$pconfig['mask'] = $a_queues[$id]['mask'];
		$pconfig['name'] = $a_queues[$id]['name'];
		$pconfig = $a_queues[$id];
		$pconfig['ack'] = $a_queues[$id]['ack'];
		$pconfig['red'] = $a_queues[$id]['red'];
		$pconfig['ecn'] = $a_queues[$id]['ecn'];
		$pconfig['rio'] = $a_queues[$id]['rio'];
		$pconfig['borrow'] = $a_queues[$id]['borrow'];
		$pconfig['defaultqueue'] = $a_queues[$id]['defaultqueue'];
		$pconfig['parentqueue'] = $a_queues[$id]['parentqueue'];
		$pconfig['upperlimit1'] = $a_queues[$id]['upperlimit1'];
		$pconfig['upperlimit2'] = $a_queues[$id]['upperlimit2'];
		$pconfig['upperlimit3'] = $a_queues[$id]['upperlimit3'];
		$pconfig['realtime1'] = $a_queues[$id]['realtime1'];
		$pconfig['realtime2'] = $a_queues[$id]['realtime2'];
		$pconfig['realtime3'] = $a_queues[$id]['realtime3'];
		$pconfig['linkshare1'] = $a_queues[$id]['linkshare1'];
		$pconfig['linkshare2'] = $a_queues[$id]['linkshare2'];
		$pconfig['linkshare3'] = $a_queues[$id]['linkshare3'];
		$pconfig['bandwidth'] = $a_queues[$id]['bandwidth'];
		$pconfig['bandwidthtype'] = $a_queues[$id]['bandwidthtype'];
		$pconfig['associatedrule'] = $a_queues[$id]['associatedrule'];
		$pconfig['attachtoqueue'] = $a_queues[$id]['attachtoqueue'];
	}
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
		if($_POST['bandwidth'] == "") 
			unset($queue['bandwidthtype']);
		if($_POST['bandwidthtype'] == "")
			unset($queue['bandwidth']);
		$queue['priority'] = $_POST['priority'];
		$queue['name'] = ereg_replace(" ", "", $_POST['name']);
		$queue['name'] = substr($queue['name'], 0, 15);
		$queue['borrow'] = $_POST['borrow'];
		$queue['linkshare'] = $_POST['linkshare'];
		$queue['linkshare3'] = $_POST['linkshare3'];
		$queue['linkshare2'] = $_POST['linkshare2'];
		$queue['linkshare1'] = $_POST['linkshare1'];
		$queue['realtime'] = $_POST['realtime'];
		$queue['realtime3'] = $_POST['realtime3'];
		$queue['realtime2'] = $_POST['realtime2'];
		$queue['realtime1'] = $_POST['realtime1'];
		$queue['upperlimit'] = $_POST['upperlimit'];
		$queue['upperlimit3'] = $_POST['upperlimit3'];
		$queue['upperlimit2'] = $_POST['upperlimit2'];
		$queue['upperlimit1'] = $_POST['upperlimit1'];
		$queue['parentqueue'] = $_POST['parentqueue'];
		$queue['attachtoqueue'] = $_POST['attachtoqueue'];
		$queue['associatedrule'] = $_POST['associatedrule'];
		$scheduleroptions="";
		if ($_POST['ack'])
			$queue['ack'] = $_POST['ack'];
		$queue['rio'] = $_POST['rio'];
		$queue['red'] = $_POST['red'];
		$queue['ecn'] = $_POST['ecn'];
		$queue['defaultqueue'] = $_POST['defaultqueue'];
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

	$ack = $pconfig["ack"];
	$red = $pconfig["red"];
	$ecn = $pconfig["ecn"];
	$rio = $pconfig["rio"];
	$borrow = $pconfig["borrow"];
	$upperlimit = $pconfig["upperlimit"];
	$upperlimit1 = $pconfig["upperlimit1"];
	$upperlimit2 = $pconfig["upperlimit2"];
	$upperlimit3 = $pconfig["upperlimit3"];
	$realtime = $pconfig["realtime"];
	$realtime1 = $pconfig["realtime1"];
	$realtime2 = $pconfig["realtime2"];
	$realtime3 = $pconfig["realtime3"];
	$linkshare = $pconfig["linkshare"];
	$linkshare1 = $pconfig["linkshare1"];
	$linkshare2 = $pconfig["linkshare2"];
	$linkshare3 = $pconfig["linkshare3"];
	$parentqueue = $pconfig["parentqueue"];
	$defaultqueue = $pconfig["defaultqueue"];
	$attachtoqueue = $pconfig['attachtoqueue'];
	$parent = $pconfig["parent"];

$pgtitle = "Firewall: Shaper: Queues: Edit";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
	<form action="firewall_shaper_queues_edit.php" method="post" name="iform" id="iform">
              <?display_topbar()?>
	  <table width="100%" border="0" cellpadding="6" cellspacing="0">
	    <tr>
	      <td valign="top" class="vncellreq">Scheduler Type</td>
	      <td class="vtable">
		<?php
			if($schedulertype == "hfsc") echo "Hierarchical Fair Service Curve queueing";
			else if($schedulertype == "priq") echo "<a target=\"_new\" href=\"http://www.openbsd.org/faq/pf/queueing.html#priq\">Priority based queueing</a>";
			else if($schedulertype == "cbq") echo "<a target=\"_new\" href=\"http://www.openbsd.org/faq/pf/queueing.html#cbq\">Class based queueing</a>";
		?>
	      </td>
	    </tr>
	    <?php if ($schedulertype == "cbq"): ?>
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
	    <? endif; ?>
	    <tr>
	      <td width="22%" valign="top" class="vncellreq">Priority</td>
	      <td width="78%" class="vtable"> <input name="priority" type="text" id="priority" size="5" value="<?=htmlspecialchars($pconfig['priority']);?>">
		<br> <span class="vexpl">The priority of the queue. For class based queueing the priority range is 0 to 7 and for priority based queueing the range is 0 to 15. Priority 0 is the lowest priority. When not specified, a default of 1 is used.
		</span></td>
	    </tr>
	    <tr>
	      <td width="22%" valign="top" class="vncell"><b>Name</b></td>
	      <td width="78%" class="vtable"> <input name="name" type="text" class="formfld" id="name" size="15" value="<?=htmlspecialchars($pconfig['name']);?>">
		<br> <span class="vexpl">Enter the name of the queue here.  Do not use spaces and limit the size to 15 characters.
		</span></td>
	    </tr>
	    <tr>
	      <td width="22%" valign="top" class="vncell">Scheduler options</td>
	      <td width="78%" class="vtable">
	<?php
		$isdefaultdefined = 0;
		foreach($config['shaper'] as $queue) {
			if(isset($queue["defaultqueue"])) $isdefaultdefined = 1;
		}
	?>
	<?php if ($isdefaultdefined == 0): ?>
	        <input type="checkbox" id="defaultqueue" name="defaultqueue" <?php if($defaultqueue) echo " CHECKED";?> > Default queue<br>
	<? endif; ?>
	<?php if ($schedulertype == "cbq"): ?>
		<input type="checkbox" id="borrow" name="borrow" <?php if($borrow) echo " CHECKED";?> > Borrow from other queues when available<br>
	<? endif; ?>
		<input type="checkbox" id="ack" name="ack" <?php if(isset($ack)) echo " CHECKED";?> > ACK/low-delay queue.  At least one queue per interface should have this checked.<br>
		<input type="checkbox" id="red" name="red" <?php if($red) echo " CHECKED";?> > <a target="_new" href="http://www.openbsd.org/faq/pf/queueing.html#red">Random Early Detection</a><br>
		<input type="checkbox" id="rio" name="rio" <?php if($rio) echo " CHECKED";?> > <a target="_new" href="http://www.openbsd.org/faq/pf/queueing.html#red">Random Early Detection In and Out</a><br>
		<input type="checkbox" id="ecn" name="ecn" <?php if($ecn) echo " CHECKED";?> > <a target="_new" href="http://www.openbsd.org/faq/pf/queueing.html#ecn">Explicit Congestion Notification</a><br>
	<?php if ($schedulertype == "hfsc" or $schedulertype == "cbq"): ?>
		<input type="checkbox" id="parentqueue" name="parentqueue" <?php if($parentqueue) echo " CHECKED";?> > This is a parent queue of HFSC/CBQ<br>
	<?php endif; ?>
	<?php if ($schedulertype == "hfsc"): ?>
		<table>
		<tr><td>&nbsp;</td><td><b><center>m1</td><td><b><center>d</td><td><b><center>m2</td></tr>
		<tr><td><input type="checkbox" id="upperlimit" name="upperlimit" <?php if($upperlimit) echo " CHECKED";?> > Upperlimit:</td><td><input size="3" value="<?=htmlspecialchars($upperlimit1);?>" name="upperlimit1"></td><td><input size="3" value="<?=htmlspecialchars($upperlimit2);?>" name="upperlimit2"></td><td><input size="3" value="<?=htmlspecialchars($upperlimit3);?>" name="upperlimit3"></td><td>The maximum allowed bandwidth for the queue.</td></tr>
		<tr><td><input type="checkbox" id="realtime" name="realtime" <?php if($realtime) echo " CHECKED";?> > Real time:</td><td><input size="3" value="<?=htmlspecialchars($realtime1);?>" name="realtime1"></td><td><input size="3" value="<?=htmlspecialchars($realtime2); ?>" name="realtime2"></td><td><input size="3" value="<?=htmlspecialchars($realtime3);?>" name="realtime3"></td><td>The minimum required bandwidth for the queue.</td></tr>
		<tr><td><input type="checkbox" id="linkshare" id="linkshare" name="linkshare" <?php if($linkshare) echo " CHECKED";?> > Link share:</td><td><input size="3" value="<?=htmlspecialchars($linkshare1);?>" value="<?=htmlspecialchars($linkshare1);?>" id="linkshare1" name="linkshare1"></td><td><input size="3" value="<?=htmlspecialchars($linkshare2);?>" id="linkshare2" name="linkshare2"></td><td><input size="3" value="<?=htmlspecialchars($linkshare3);?>" id="linkshare3" name="linkshare3"></td><td>The bandwidth share of a backlogged queue.</td></tr>
		</table><br>		
			The format for service curve specifications is (m1, d, m2).  m2 controls
			the bandwidth assigned to the queue.  m1 and d are optional and can be 
			used to control the initial bandwidth assignment.  For the first d milliseconds the queue gets the bandwidth given as m1, afterwards the value
			given in m2.
		<p> <span class="vexpl">Select options for this queue
		</span></td>
	    </tr>
	<?php endif; ?>
	<?php if ($schedulertype == "hfsc" or $schedulertype == "cbq"): ?>
	    <tr>
		<td width="22%" valign="top" class="vncell">Parent queue (CBQ or HFSC only):</td>
		<td width="78%" class="vtable">
		   <select id="attachtoqueue" name="attachtoqueue">
			<?php
			if($pconfig['attachtoqueue'] <> "")
				echo "<option value=\"" . $pconfig['attachtoqueue'] . "\">" . $pconfig['attachtoqueue'] . "</option>";
			else
				echo "<option value=\"\"></option>";
			if (is_array($config['shaper']['queue'])) {
			 	foreach ($config['shaper']['queue'] as $queue) {
					if($queue['parentqueue'] <> "")
			 			echo "<option value=\"" . $queue['name'] . "\">" . $queue['name'] . "</option>";
			 	}
			}
			?>
		   </select>
		</td>
	    </tr>
	<?php endif; ?>
	    <tr>
	      <td width="22%" valign="top">&nbsp;</td>
	      <td width="78%"> <input name="Submit" type="submit" class="formbtn" value="Save"> <input type="button" class="formbtn" value="Cancel" onclick="history.back()">
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
