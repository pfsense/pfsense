#!/usr/local/bin/php
<?php
/*
	status_queues.php
	Copyright (C) 2004, 2005 Scott Ullrich
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

if($_GET['reset'] <> "") {
	mwexec("killall -9 pfctl php");
	exit;
}

if (!is_array($config['shaper']['queue'])) {
	$config['shaper']['queue'] = array();
}
$a_queues = &$config['shaper']['queue'];

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Status: Traffic shaper: Queues");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Status: Traffic shaper: Queues</p>
<form action="status_queues.php" method="post">
              <table width="100%" border="0" cellpadding="0" cellspacing="0">
                      <tr>
                        <td class="listhdr">Name</td>
                      </tr>
                      <?php $i = 0; foreach ($a_queues as $queue): ?>
                      <tr valign="top">
                        <td class="listbg">
                          <font color="#FFFFFF"><?=htmlspecialchars($queue['name']);?>
                          &nbsp;<br>
<?php
			$cpuUsage = 0;
			echo "<img src='bar_left.gif' height='15' width='4' border='0' align='absmiddle'>";
			echo "<img src='bar_blue.gif' height='15' name='queue{$i}widtha' id='queue{$i}widtha' width='" . $cpuUsage . "' border='0' align='absmiddle'>";
			echo "<img src='bar_gray.gif' height='15' name='queue{$i}widthb' id='queue{$i}widthb' width='" . (400 - $cpuUsage) . "' border='0' align='absmiddle'>";
			echo "<nobr><img src='bar_right.gif' height='15' width='5' border='0' align='absmiddle'> ";
			echo "<br><input style='border: 0px solid white; background-color:#990000; color:#FFFFFF;' size='100' name='queue{$i}meter' id='queue{$i}meter' value='( Loading )'></nobr>";
?>

			</td>
                      </tr>
                      <?php $i++; endforeach; $total_queues = $i; ?>
                    </table>
		    <p>
                    <strong><span class="red">Note:</span></strong><strong><br></strong>
		      Queue graphs take 5 seconds to sample data.
		    </p>
            </form>
<br><a href="status_queues.php?reset=true">Reset</a> queues if they do not load.
<?php include("fend.inc"); ?>
</body>
</html>

<?php

sleep(3);

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
		if($stat_line_split[2] == "" and $counter > 1) {
			mwexec("/usr/bin/killall -9 pfctl php");
			exit;
		}

		$stat_line_split = split("\|", $stats_line);
		$packet_sampled = intval($stat_line_split[2]);
		$speed = $stat_line_split[1];
		$borrows = intval($stat_line_split[3]);

		echo "<script language='javascript'>\n";

		$packet_s = round(400 * (1 - $packet_sampled / $total_packets_s), 0);

		echo "document.queue{$i}widthb.style.width='{$packet_s}';\n";
		echo "document.queue{$i}widtha.style.width='" . (400 - $packet_s) . "';\n";
		$borrows_txt = "";
		if(intval($borrows > 0))
			$borrows_txt = " - {$borrows} borrows";
		echo "document.forms[0].queue{$i}meter.value = '" . $packet_sampled . "/pps - " . $speed . "{$borrows_txt}';\n";
		echo "</script>\n";
		$i++;
	}

	/*
	 *   prevent user from running out of ram.
	 *   firefox and ie can be a bear on ram usage!
         */
	$counter++;
	if($counter > 40) {
		echo "Redirecting to <a href=\"status_queues.php\">Queue Status</a>.<p>";
		echo "<meta http-equiv=\"refresh\" content=\"1;url=status_queues.php\">";
		mwexec("/usr/bin/killall -9 pfctl");
		exit;
	}
}

mwexec("/usr/bin/killall -9 pfctl php");

?>
