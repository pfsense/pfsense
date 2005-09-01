#!/usr/local/bin/php
<?php
/* $Id$ */
/*
	status_queues.php
        Part of the pfSense project
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
	mwexec("killall -9 pfctl pfctl");
	Header("Location: status_queues.php");
	exit;
}

if (!is_array($config['shaper']['queue'])) {
	$config['shaper']['queue'] = array();
}

$a_queues = array();

$pfctl_vsq = `/sbin/pfctl -vsq`;
$pfctl_vsq_array = split("\n", $pfctl_vsq);
foreach($pfctl_vsq_array as $pfctl) {
	if (preg_match_all("/queue\s+(\w+)\s+/",$pfctl,$match_array))
		if(stristr($match_array[1][0],"root_")==false)
			$a_queues[] = $match_array[1][0];	
}

$pgtitle = "Status: Traffic shaper: Queues";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<?php
if(!isset($config['shaper']['enable'])) {
	echo "Traffic shaping is currently disabled.";
	include("fend.inc");
	exit;	
}
?>

<form action="status_queues.php" method="post">
              <table width="100%" border="0" cellpadding="0" cellspacing="0">
                      <tr>
                        <td class="listhdr" colspan="1">Queue</td>
			<td class="listhdr" colspan="6">Statistics</td>
                      </tr>
                      <?php $i = 0; foreach ($a_queues as $queue): ?>
		      <tr><td bgcolor="#DDDDDD" colspan="7">&nbsp;</td></tr>
                      <tr valign="top">
                        <td bgcolor="#DDDDDD">
                          <font color="#000000">&nbsp;&nbsp;&nbsp;<?echo "<a href=\"firewall_shaper_queues_edit.php?id={$queue}\">" . htmlspecialchars($queue) . "</a>";?>&nbsp;&nbsp;&nbsp;</td>
			<td bgcolor="#DDDDDD">
			<nobr>
<?php
			$cpuUsage = 0;
			echo "<img src='./themes/".$g['theme']."/images/misc/bar_left.gif' height='15' width='4' border='0' align='absmiddle'>";
			echo "<img src='./themes/".$g['theme']."/images/misc/bar_blue.gif' height='15' name='queue{$i}widtha' id='queue{$i}widtha' width='" . $cpuUsage . "' border='0' align='absmiddle'>";
			echo "<img src='./themes/".$g['theme']."/images/misc/bar_gray.gif' height='15' name='queue{$i}widthb' id='queue{$i}widthb' width='" . (400 - $cpuUsage) . "' border='0' align='absmiddle'>";
			echo "<nobr><img src='./themes/".$g['theme']."/images/misc/bar_right.gif' height='15' width='5' border='0' align='absmiddle'> ";
			echo "</nobr></td></tr>";
			echo "<tr><td bgcolor=\"#DDDDDD\" colspan=\"7\">";
			echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
			echo "<nobr>";
			echo "<input style='border: 0px solid white; background-color:#DDDDDD; color:#000000;' size='15' name='queue{$i}pps' id='queue{$i}pps' value='                (Loading)' align='right'>";
			echo "<input style='border: 0px solid white; background-color:#DDDDDD; color:#000000;' size='15' name='queue{$i}bps' id='queue{$i}bps' value='' align='right'>";
			echo "<input style='border: 0px solid white; background-color:#DDDDDD; color:#000000;' size='15' name='queue{$i}borrows' id='queue{$i}borrows' value='' align='right'>";
			echo "<input style='border: 0px solid white; background-color:#DDDDDD; color:#000000;' size='15' name='queue{$i}suspends' id='queue{$i}suspends' value='' align='right'>";
			echo "<input style='border: 0px solid white; background-color:#DDDDDD; color:#000000;' size='15' name='queue{$i}drops' id='queue{$i}drops' value='' align='right'>";
			echo "</nobr>";
?>

			</td>
                      </tr>
		      <tr><td class="vncell" bgcolor="#DDDDDD" colspan="7">&nbsp;</td></tr>
                      <?php $i++; endforeach; $total_queues = $i; ?>
                    </table>
		    <p>
                    <strong><span class="red">Note:</span></strong><strong><br></strong>
		      Queue graphs take 5 seconds to sample data.<br>
                      You can configure the TrafficShaper <a href="firewall_shaper.php?reset=true">here</a>.
		    </p>
            </form>
<br><a href="status_queues.php?reset=true">Reset</a> queues if they do not load.

<meta http-equiv="refresh" content="60;url=<?php print $_SERVER['PHP_SELF']; ?>">

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
		$suspends = intval($stat_line_split[4]);
		$drops = intval($stat_line_split[5]);

		echo "<script language='javascript'>\n";

		$packet_s = round(400 * (1 - $packet_sampled / $total_packets_s), 0);

		echo "document.queue{$i}widthb.style.width='{$packet_s}px';\n";
		echo "document.queue{$i}widtha.style.width='" . (400 - $packet_s) . "px';\n";
		$borrows_txt = "{$borrows} borrows";
		$suspends_txt = "{$suspends} suspends";
		$drops_txt = "${drops} drops";
		echo "document.forms[0].queue{$i}pps.value = '{$packet_sampled}/pps';\n";
		echo "document.forms[0].queue{$i}bps.value = '{$speed}';\n";
		echo "document.forms[0].queue{$i}borrows.value = '{$borrows_txt}';\n";
		echo "document.forms[0].queue{$i}suspends.value = '{$suspends_txt}';\n";
		echo "document.forms[0].queue{$i}drops.value = '{$drops_txt}';\n";
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
		mwexec("/usr/bin/killall -9 pfctl php");
		exit;
	}
}

mwexec("/usr/bin/killall -9 pfctl pfctl");
mwexec("/usr/bin/killall -9 pfctl php");

?>
