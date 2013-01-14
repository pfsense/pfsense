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

header("Last-Modified: " . gmdate( "D, j M Y H:i:s" ) . " GMT" );
header("Expires: " . gmdate( "D, j M Y H:i:s", time() ) . " GMT" );
header("Cache-Control: no-store, no-cache, must-revalidate" ); // HTTP/1.1
header("Cache-Control: post-check=0, pre-check=0", FALSE );
header("Pragma: no-cache"); // HTTP/1.0

require("guiconfig.inc");

if (!file_exists("{$g['varrun_path']}/qstats.pid") || !isvalidpid("{$g['varrun_path']}/qstats.pid")) {
	/* Start in the background so we don't hang up the GUI */
	mwexec_bg("/usr/local/sbin/qstats -p {$g['varrun_path']}/qstats.pid");
	/* Give it a moment to start up */
	sleep(1);
}

$fd = @fsockopen("unix://{$g['varrun_path']}/qstats");
if (!$fd) {
	$error = "Something wrong happened during comunication with stat gathering";
} else {
	$stats = "";
	while(!feof($fd))
		$stats .= fread($fd, 4096);
	fclose($fd);
	@file_put_contents("{$g['tmp_path']}/qstats", $stats);
	$altqstats = @parse_xml_config("{$g['tmp_path']}/qstats", array("altqstats"));
	if ($altqstats == -1)
		$error = "No queue statistics could be read.";
}
if ($_REQUEST['getactivity']) {
        /* calculate total packets being moved through all queues. */
        $total_packets_s = 0;
        foreach($altqstats['queue'] as $q) {
		if (strstr($q['name'], "root_"))
			continue;
                $total_packets_s = $total_packets_s + intval($q['pkts']);
        }

	$finscript = "";
        foreach($altqstats['queue'] as $q) {
		if (strstr($q['name'], "root_"))
			continue;

                $packet_s = round(400 * (1 - $q['pkts']/ $total_packets_s), 0);

                $finscript .= "jQuery('#queue{$q['name']}{$q['interface']}widthb').width('{$packet_s}');";
                $finscript .= "jQuery('#queue{$q['name']}{$q['interface']}widtha').width('" . (400 - $packet_s) . "');";
                $finscript .= "jQuery('#queue{$q['name']}{$q['interface']}pps').val('{$q['measured']}/pps');";
                $finscript .= "jQuery('#queue{$q['name']}{$q['interface']}bps').val('{$q['measuredspeed']}');";
                $finscript .= "jQuery('#queue{$q['name']}{$q['interface']}borrows').val('{$q['borrows']} borrows');";
                $finscript .= "jQuery('#queue{$q['name']}{$q['interface']}suspends').val('{$q['suspends']} suspends');";
                $finscript .= "jQuery('#queue{$q['name']}{$q['interface']}drops').val('{$q['droppedpkts']} drops');";
        }
	header("Content-type: text/javascript");
	echo $finscript;
	exit;
}

$pgtitle = array(gettext("Status"),gettext("Traffic shaper"),gettext("Queues"));
$shortcut_section = "trafficshaper";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php
if(!is_array($config['shaper']['queue']) || count($config['shaper']['queue']) < 1) {
	echo gettext("Traffic shaping is not configured.");
	include("fend.inc");
	exit;	
}
?>
<?php if (!$error): ?>
<form action="status_queues.php" method="post">
<script type="text/javascript">
        function getqueueactivity() {
                var url = "/status_queues.php";
                var pars = 'getactivity=yes';
                jQuery.ajax(
                        url,
                        {
                                type: 'post',
                                data: pars,
                                complete: activitycallback
                        });
        }
        function activitycallback(transport) {
                setTimeout('getqueueactivity()', 5100);
        }
        jQuery(document).ready(function(){
          setTimeout('getqueueactivity()', 150);
        });
</script>
<?php endif; ?>
              <table width="100%" border="0" cellpadding="0" cellspacing="0">
<?php if ($error): ?>
		<tr><td><?php echo $error; ?></td></tr>
<?php else: ?>
                      <tr>
                        <td class="listhdr" colspan="1"><?=gettext("Queue"); ?></td>
			<td class="listhdr" colspan="6"><?=gettext("Statistics"); ?></td>
                      </tr>
                      <?php $i = 0; foreach ($altqstats['queue'] as $q): 
				if (strstr($q['name'], "root_"))
					continue;
			?>
		      <tr><td bgcolor="#DDDDDD" colspan="7">&nbsp;</td></tr>
                      <tr valign="top">
                        <td bgcolor="#DDDDDD">
                          <font color="#000000">&nbsp;&nbsp;&nbsp;<?echo "<a href=\"firewall_shaper.php?id={$q['name']}\">" . htmlspecialchars($q['name'] . " on " . convert_real_interface_to_friendly_descr($q['interface'])) . "</a>";?>&nbsp;&nbsp;&nbsp;</td>
			<td bgcolor="#DDDDDD">
			<nobr>
<?php
			$cpuUsage = 0;
			echo "<img src='./themes/".$g['theme']."/images/misc/bar_left.gif' height='10' width='4' border='0' align='absmiddle'>";
			echo "<img src='./themes/".$g['theme']."/images/misc/bar_blue.gif' height='10' name='queue{$q['name']}{$q['interface']}widtha' id='queue{$q['name']}{$q['interface']}widtha' width='" . $cpuUsage . "' border='0' align='absmiddle'>";
			echo "<img src='./themes/".$g['theme']."/images/misc/bar_gray.gif' height='10' name='queue{$q['name']}{$q['interface']}widthb' id='queue{$q['name']}{$q['interface']}widthb' width='" . (400 - $cpuUsage) . "' border='0' align='absmiddle'>";
			echo "<nobr><img src='./themes/".$g['theme']."/images/misc/bar_right.gif' height='10' width='5' border='0' align='absmiddle'> ";
			echo "</nobr></td></tr>";
			echo "<tr><td bgcolor=\"#DDDDDD\" colspan=\"7\">";
			echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
			echo "<nobr>";
			echo "<input style='border: 0px solid white; background-color:#DDDDDD; color:#000000;' size='10' name='queue{$q['name']}{$q['interface']}pps' id='queue{$q['name']}{$q['interface']}pps' value='(" . gettext("Loading") . ")' align='left'>";
			echo "<input style='border: 0px solid white; background-color:#DDDDDD; color:#000000;' size='10' name='queue{$q['name']}{$q['interface']}bps' id='queue{$q['name']}{$q['interface']}bps' value='' align='right'>";
			echo "<input style='border: 0px solid white; background-color:#DDDDDD; color:#000000;' size='10' name='queue{$q['name']}{$q['interface']}borrows' id='queue{$q['name']}{$q['interface']}borrows' value='' align='right'>";
			echo "<input style='border: 0px solid white; background-color:#DDDDDD; color:#000000;' size='10' name='queue{$q['name']}{$q['interface']}suspends' id='queue{$q['name']}{$q['interface']}suspends' value='' align='right'>";
			echo "<input style='border: 0px solid white; background-color:#DDDDDD; color:#000000;' size='10' name='queue{$q['name']}{$q['interface']}drops' id='queue{$q['name']}{$q['interface']}drops' value='' align='right'>";
			echo "</nobr>";
?>

			</td>
                      </tr>
		      <tr><td class="vncell" bgcolor="#DDDDDD" colspan="7">&nbsp;</td></tr>
                      <?php $i++; endforeach; $total_queues = $i; ?>
<?php endif; ?>
                    </table>
		    <p>
                    <strong><span class="red"><?=gettext("Note"); ?>:</span></strong><strong><br></strong>
		      <?=gettext("Queue graphs take 5 seconds to sample data"); ?>.<br>
                      <?=gettext("You can configure the Traffic Shaper"); ?> <a href="/firewall_shaper_wizards.php"><?=gettext("here"); ?></a>.
		    </p>
<?php include("fend.inc"); ?>
</body>
</html>
