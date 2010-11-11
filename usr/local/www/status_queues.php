#!/usr/local/bin/php
<?php
/* $Id$ */
/*
	status_queues.php
        Part of the pfSense project
	Copyright (C) 2004, 2005 Scott Ullrich
	Copyright (C) 2009 Ermal Luçi
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
	pfSense_BUILDER_BINARIES:	/sbin/pfctl
	pfSense_MODULE:	shaper
*/

##|+PRIV
##|*IDENT=page-status-trafficshaper-queues
##|*NAME=Status: Traffic shaper: Queues page
##|*DESCR=Allow access to the 'Status: Traffic shaper: Queues' page.
##|*MATCH=status_queues.php*
##|-PRIV

// Handle Javascript/AJAX
function csrf_startup() {
	csrf_conf('rewrite-js', '/csrf/csrf-magic.js');
}
require("guiconfig.inc");

if ($_REQUEST['getactivity']) {
	$stats_array = gather_altq_queue_stats(true);

        /* calculate total packets being moved through all queues. */
        $total_packets_s = 0;
        foreach($stats_array as $stats_line) {
                $stat_line_split = split("\|", $stats_line);
                $total_packets_s = $total_packets_s + intval($stat_line_split[2]);
        }

        $i = 0;
	$finscript = "";
        foreach($stats_array as $stats_line) {
                if($stat_line_split[2] == "" and $counter > 1) {
                        continue;
                }

                $stat_line_split = split("\|", $stats_line);
                $packet_sampled = intval($stat_line_split[2]);
                $speed = $stat_line_split[1];
                $borrows = intval($stat_line_split[3]);
                $suspends = intval($stat_line_split[4]);
                $drops = intval($stat_line_split[5]);


                $packet_s = round(400 * (1 - $packet_sampled / $total_packets_s), 0);

                $finscript .= "$('queue{$i}widthb').width='{$packet_s}';";
                $finscript .= "$('queue{$i}widtha').width='" . (400 - $packet_s) . "';";
                $borrows_txt = "{$borrows} borrows";
                $suspends_txt = "{$suspends} suspends";
                $drops_txt = "${drops} drops";
                $finscript .= "$('queue{$i}pps').value = '{$packet_sampled}/pps';";
                $finscript .= "$('queue{$i}bps').value = '{$speed}';";
                $finscript .= "$('queue{$i}borrows').value = '{$borrows_txt}';";
                $finscript .= "$('queue{$i}suspends').value = '{$suspends_txt}';";
                $finscript .= "$('queue{$i}drops').value = '{$drops_txt}';";
                $i++;
        }
	header("Content-type: text/javascript");
	echo $finscript;
	exit;
}

$a_queues = array();

exec("/sbin/pfctl -vsq", $pfctl_vsq_array);
foreach($pfctl_vsq_array as $pfctl) {
	if (preg_match_all("/queue\s+(\w+)\s+(\w+)\s+(\w+)\s+/",$pfctl,$match_array)) {
		if (stristr($match_array[1][0],"root_"))
			continue;
               $a_queues[] = $match_array[1][0] . " on " .
				convert_real_interface_to_friendly_descr($match_array[3][0]);
	}
}

$pgtitle = array(gettext("Status"),gettext("Traffic shaper"),gettext("Queues"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php
if(!is_array($config['shaper']['queue']) && count($config['shaper']['queue']) < 1) {
	echo gettext("Traffic shaping is not configured.");
	include("fend.inc");
	exit;	
}
?>

<form action="status_queues.php" method="post">
<script type="text/javascript">
        function getqueueactivity() {
                var url = "/status_queues.php";
                var pars = 'getactivity=yes';
                var myAjax = new Ajax.Request(
                        url,
                        {
                                method: 'post',
                                parameters: pars,
                                onComplete: activitycallback
                        });
        }
        function activitycallback(transport) {
                setTimeout('getqueueactivity()', 5100);
        }
        document.observe('dom:loaded', function(){
          setTimeout('getqueueactivity()', 150);
        });
</script>
              <table width="100%" border="0" cellpadding="0" cellspacing="0">
                      <tr>
                        <td class="listhdr" colspan="1"><?=gettext("Queue"); ?></td>
			<td class="listhdr" colspan="6"><?=gettext("Statistics"); ?></td>
                      </tr>
                      <?php $i = 0; foreach ($a_queues as $queue): ?>
		      <tr><td bgcolor="#DDDDDD" colspan="7">&nbsp;</td></tr>
                      <tr valign="top">
                        <td bgcolor="#DDDDDD">
                          <font color="#000000">&nbsp;&nbsp;&nbsp;<?echo "<a href=\"firewall_shaper.php?id={$queue}\">" . htmlspecialchars($queue) . "</a>";?>&nbsp;&nbsp;&nbsp;</td>
			<td bgcolor="#DDDDDD">
			<nobr>
<?php
			$cpuUsage = 0;
			echo "<img src='./themes/".$g['theme']."/images/misc/bar_left.gif' height='10' width='4' border='0' align='absmiddle'>";
			echo "<img src='./themes/".$g['theme']."/images/misc/bar_blue.gif' height='10' name='queue{$i}widtha' id='queue{$i}widtha' width='" . $cpuUsage . "' border='0' align='absmiddle'>";
			echo "<img src='./themes/".$g['theme']."/images/misc/bar_gray.gif' height='10' name='queue{$i}widthb' id='queue{$i}widthb' width='" . (400 - $cpuUsage) . "' border='0' align='absmiddle'>";
			echo "<nobr><img src='./themes/".$g['theme']."/images/misc/bar_right.gif' height='10' width='5' border='0' align='absmiddle'> ";
			echo "</nobr></td></tr>";
			echo "<tr><td bgcolor=\"#DDDDDD\" colspan=\"7\">";
			echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
			echo "<nobr>";
			echo "<input style='border: 0px solid white; background-color:#DDDDDD; color:#000000;' size='10' name='queue{$i}pps' id='queue{$i}pps' value='(" . gettext("Loading") . ")' align='left'>";
			echo "<input style='border: 0px solid white; background-color:#DDDDDD; color:#000000;' size='10' name='queue{$i}bps' id='queue{$i}bps' value='' align='right'>";
			echo "<input style='border: 0px solid white; background-color:#DDDDDD; color:#000000;' size='10' name='queue{$i}borrows' id='queue{$i}borrows' value='' align='right'>";
			echo "<input style='border: 0px solid white; background-color:#DDDDDD; color:#000000;' size='10' name='queue{$i}suspends' id='queue{$i}suspends' value='' align='right'>";
			echo "<input style='border: 0px solid white; background-color:#DDDDDD; color:#000000;' size='10' name='queue{$i}drops' id='queue{$i}drops' value='' align='right'>";
			echo "</nobr>";
?>

			</td>
                      </tr>
		      <tr><td class="vncell" bgcolor="#DDDDDD" colspan="7">&nbsp;</td></tr>
                      <?php $i++; endforeach; $total_queues = $i; ?>
                    </table>
		    <p>
                    <strong><span class="red"><?=gettext("Note"); ?>:</span></strong><strong><br></strong>
		      <?=gettext("Queue graphs take 5 seconds to sample data"); ?>.<br>
                      <?=gettext("You can configure the Traffic Shaper"); ?> <a href="/firewall_shaper_wizards.php"><?=gettext("here"); ?></a>.
		    </p>
            </form>
<?php include("fend.inc"); ?>
</body>
</html>
