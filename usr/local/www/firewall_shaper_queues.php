#!/usr/local/bin/php
<?php
/*
	firewall_shaper_queues.php
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

if (!is_array($config['shaper']['pipe'])) {
	$config['shaper']['pipe'] = array();
}
if (!is_array($config['shaper']['queue'])) {
	$config['shaper']['queue'] = array();
}
$a_queues = &$config['shaper']['queue'];
$a_pipe = &$config['shaper']['pipe'];

$iflist = array("lan" => "LAN", "wan" => "WAN");

for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
	$iflist['opt' . $i] = $config['interfaces']['opt' . $i]['descr'];
}

if ($_POST['apply'] || $_POST['submit']) {
	$config['shaper']['enable'] = true;
	write_config();

	$retval = 0;
	$savemsg = get_std_save_message($retval);
	/* Setup pf rules since the user may have changed the optimization value */
	//config_lock();
	$retval = filter_configure();
	//config_unlock();
	if(stristr($retval, "error") <> true)
	    $savemsg = get_std_save_message($retval);
	else
	    $savemsg = $retval;

	if(file_exists($d_shaperconfdirty_path))
	  unlink($d_shaperconfdirty_path);
}

if ($_GET['act'] == "del") {
	if ($a_queues[$_GET['id']]) {
		/* check that no rule references this queue */
		if (is_array($config['shaper']['rule'])) {
			foreach ($config['shaper']['rule'] as $rule) {
				if (isset($rule['targetqueue']) && ($rule['targetqueue'] == $_GET['id'])) {
					$input_errors[] = "This queue cannot be deleted because it is still referenced by a rule.";
					break;
				}
			}
		}

		if (!$input_errors) {
			unset($a_queues[$_GET['id']]);

			/* renumber all rules */
			if (is_array($config['shaper']['rule'])) {
				for ($i = 0; isset($config['shaper']['rule'][$i]); $i++) {
					$currule = &$config['shaper']['rule'][$i];
					if (isset($currule['targetqueue']) && ($currule['targetqueue'] > $_GET['id']))
						$currule['targetqueue']--;
				}
			}

			write_config();
			touch($d_shaperconfdirty_path);
			header("Location: firewall_shaper_queues.php");
			exit;
		}
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Firewall: Traffic shaper: Queues");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Firewall: Traffic shaper: Queues</p>
<form action="firewall_shaper_queues.php" method="post">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_shaperconfdirty_path)): ?><p>
<?php print_info_box_np("The traffic shaper configuration has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<input name="apply" type="submit" class="formbtn" id="apply" value="Apply changes"></p>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
  <ul id="tabnav">
    <li class="tabinact"><a href="firewall_shaper.php">Rules</a></li>
    <li class="tabact">Queues</a></li>
    <li class="tabinact"><a href="firewall_shaper_magic.php">Magic shaper wizard</a></li>
  </ul>
  </td></tr>
  <tr>
    <td class="tabcont">
              <table width="100%" border="0" cellpadding="0" cellspacing="0">
                      <tr>
                        <td width="10%" class="listhdrr">No.</td>
                        <td width="20%" class="listhdrr">Priority</td>
			<td width="10%" class="listhdr">Default</td>
                        <td width="50%" class="listhdr">Name</td>
                        <td width="10%" class="list"></td>
                      </tr>
                      <?php $i = 0; foreach ($a_queues as $queue): ?>
                      <tr valign="top">
                        <td class="listlr">
                          <?=($i+1);?>
			</td>
                        <td class="listr">
                          <?=$queue['priority'];?>&nbsp;
			</td>
			<td class="listr">
			  <?php
				if($queue['defaultqueue'] <> "") {
					echo "Yes";
				} else {
					echo "No";
				}
			  ?>
			</td>
                        <td class="listbg">
                          <font color="#FFFFFF"><?=htmlspecialchars($queue['name']);?>
                          &nbsp;<br>
<?php
			$cpuUsage = 0;
			echo "<img src='bar_left.gif' height='15' width='4' border='0' align='absmiddle'>";
			echo "<img src='bar_blue.gif' height='15' name='queue{$i}widtha' id='queue{$i}widtha' width='" . $cpuUsage . "' border='0' align='absmiddle'>";
			echo "<img src='bar_gray.gif' height='15' name='queue{$i}widthb' id='queue{$i}widthb' width='" . (100 - $cpuUsage) . "' border='0' align='absmiddle'>";
			echo "<img src='bar_right.gif' height='15' width='5' border='0' align='absmiddle'> ";
			echo "<input style='border: 0px solid white; background-color:#990000; color:#FFFFFF;' size='15' name='queue{$i}meter' id='queue{$i}meter' value='{$cpuUsage}'>";
?>

			</td>
                        <td valign="middle" nowrap class="list"> <a href="firewall_shaper_queues_edit.php?id=<?=$i;?>"><img src="e.gif" width="17" height="17" border="0"></a>
                          &nbsp;<a href="firewall_shaper_queues.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this queue?')"><img src="x.gif" width="17" height="17" border="0"></a></td>
                      </tr>
                      <?php $i++; endforeach; $total_queues = $i; ?>
                      <tr>
                        <td class="list" colspan="5"></td>
                        <td class="list"> <a href="firewall_shaper_queues_edit.php"><img src="plus.gif" width="17" height="17" border="0"></a></td>
                      </tr>
                    </table>
		    <p>
                    <strong><span class="red">Note:</span></strong><strong><br></strong>
                      1)  A queue can only be deleted if it is not referenced by any rules.<br>
		      2)  Queue graphs take 5 seconds to sample data.
		      </td>
		    </p>
	</tr>
</table>
            </form>
<?php include("fend.inc"); ?>
</body>
</html>


<?php

$counter = 0;
While(!Connection_Aborted()) {

	$stats_array = gather_altq_queue_stats(true);

	/* calculate total packets being moved through all queues. */
	$total_packets_s = 0;
	foreach($stats_array as $stats_line) {
		$stat_line_split = split("\|", $stats_line);
		$total_packets_s = $total_packets_s + intval($stat_line_split[2]);
	}

	$i = 0;
	foreach($stats_array as $stats_line) {
		$stat_line_split = split("\|", $stats_line);
		$packet_sampled = intval($stat_line_split[2]);
		$speed = $stat_line_split[1];
		echo "<script language='javascript'>\n";

		$packet_s = round(100 * (1 - $packet_sampled / $total_packets_s), 0);

		echo "document.queue{$i}widthb.style.width='{$packet_s}';\n";
		echo "document.queue{$i}widtha.style.width='" . (100 - $packet_s) . "';\n";
		echo "document.forms[0].queue{$i}meter.value = '" . $packet_sampled . "/pps - " . $speed . "';\n";
		echo "</script>\n";
		$i++;
	}

	/*
	 *   prevent user from running out of ram.
	 *   firefox and ie can be a bear on ram usage!
         */
	$counter++;
	if($counter > 10000) {
		echo "Redirecting to <a href=\"firewall_shaper_queues.php\">Firewall Shaper Queues</a>.<p>";
		echo "<meta http-equiv=\"refresh\" content=\"1;url=firewall_shaper_queues.php\">";
		exit;
	}

}

?>