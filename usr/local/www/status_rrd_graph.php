<?php
/* $Id$ */
/*
	status_rrd_graph.php
	Part of pfSense
	Copyright (C) 2004 Scott Ullrich
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

if ($_GET['if']) {
	$curif = $_GET['if'];
	$ifnum = $config['interfaces'][$curif]['if'];
} else {
	$curif = "wan";
	$ifnum = get_real_wan_interface();
}

if ($_GET['graph']) {
	$curgraph = $_GET['graph'];
} else {
	$curgraph = "traffic";
}

$pgtitle = "Status: RRD Graphs";
include("head.inc");

/*  Create an array of image names.  We
 *  will use javascript code to automatically
 *  refresh the images instead of a meta refresh
 *  tag.
 */
$page_images = array();

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<script src="/javascript/scriptaculous/prototype.js" type="text/javascript"></script>
<script src="/javascript/scriptaculous/scriptaculous.js" type="text/javascript"></script>

<p class="pgtitle"><?=$pgtitle?></p>
<?php
$ifdescrs = array('wan' => 'WAN', 'lan' => 'LAN');
$graphs = array('traffic' => 'Traffic', 'quality' => 'Quality', 'queues' => 'Queues', 'packets' => 'Packets', 'spamd' => 'Spamd');

for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
	$ifdescrs['opt' . $j] = $config['interfaces']['opt' . $j]['descr'];
}
?>
<form name="form1" action="status_rrd_graph.php" method="get" style="padding-bottom: 10px; margin-bottom: 14px; 
border-bottom: 1px solid #999999">
<?php if ($curgraph <> "spamd"): ?>
Interface:
<select name="if" class="formfld" style="z-index: -10;" onchange="document.form1.submit()">
<?php
foreach ($ifdescrs as $ifn => $ifd) {
	echo "<option value=\"$ifn\"";
	if ($ifn == $curif) echo " selected";
	echo ">" . htmlspecialchars($ifd) . "</option>\n";
}
?>
</select>
<?php endif; ?>
Graph:
<select name="graph" class="formfld" style="z-index: -10;" onchange="document.form1.submit()">
<?php
foreach ($graphs as $graph => $graphd) {
	echo "<option value=\"$graph\"";
	if ($graph == $curgraph) echo " selected";
	echo ">" . htmlspecialchars($graphd) . "</option>\n";
}
?>
</select>
</form>
<p>
<div>
<?php

$periods = array("6h", "30h", "10d", "400d");
$graphs['6h']['seconds'] = 21600;
$graphs['6h']['average'] = 300;
$graphs['6h']['scale'] = "MINUTE:10:MINUTE:30:MINUTE:30:0:%H%:%M";
$graphs['30h']['seconds'] = 108000;
$graphs['30h']['average'] = 1200;
$graphs['30h']['scale'] = "HOUR:1:HOUR:6:HOUR:2:0:%H";
$graphs['10d']['seconds'] = 864000;
$graphs['10d']['average'] = 7200;
$graphs['10d']['scale'] = "HOUR:6:DAY:1:DAY:1:0:%a";
$graphs['400d']['seconds'] = 34560000;
$graphs['400d']['average'] = 86400;
$graphs['400d']['scale'] = "MONTH:1:MONTH:1:MONTH:1:0:%b";

$rrddbpath = "/var/db/rrd/";
$traffic = "-traffic.rrd";
$quality = "-quality.rrd";
$queues = "-queues.rrd";
$packets = "-packets.rrd";
$spamd = "spamd.rrd";
$rrdtool = "/usr/local/bin/rrdtool";
$uptime = "/usr/bin/uptime";
$sed = "/usr/bin/sed";

foreach($periods as $period => $interval) {

/* generate the graphs when we request the page. */
$seconds = $graphs[$interval]['seconds'];
$average = $graphs[$interval]['average'];
$scale = $graphs[$interval]['scale'];

if(($curgraph == "traffic") && (file_exists("$rrddbpath$curif$traffic"))) {
	$page_images[] = "{$curif}-{$interval}-{$curgraph}";
	/* define graphcmd for traffic stats */
	$graphcmd = "$rrdtool graph $rrddbpath$curif-$interval-$curgraph.png \\
		--start -$seconds -e -$average \\
		--vertical-label \"bits/sec\" \\
		--title \"`hostname` - $curgraph - $interval\" \\
		--height 100 --width 650 -x \"$scale\" \\
		DEF:$curif-in_bytes=$rrddbpath$curif$traffic:in:AVERAGE \\
		DEF:$curif-out_bytes=$rrddbpath$curif$traffic:out:AVERAGE \\
		\"CDEF:$curif-in_bits=$curif-in_bytes,8,*\" \\
		\"CDEF:$curif-out_bits=$curif-out_bytes,8,*\" \\
		\"CDEF:$curif-bits_io=$curif-in_bits,$curif-out_bits,+\" \\
		\"CDEF:$curif-out_bits_neg=$curif-out_bits,-1,*\" \\
		\"CDEF:$curif-bytes_in=$curif-in_bytes,0,12500000,LIMIT,UN,0,$curif-in_bytes,IF,$average,*\" \\
		\"CDEF:$curif-bytes_out=$curif-out_bytes,0,12500000,LIMIT,UN,0,$curif-out_bytes,IF,$average,*\" \\
		\"CDEF:$curif-bytes=$curif-bytes_in,$curif-bytes_out,+\" \\
		\"CDEF:$curif-bytes_in_t=$curif-in_bytes,0,12500000,LIMIT,UN,0,$curif-in_bytes,IF,$seconds,*\" \\
		\"CDEF:$curif-bytes_out_t=$curif-out_bytes,0,12500000,LIMIT,UN,0,$curif-out_bytes,IF,$seconds,*\" \\
		\"CDEF:$curif-bytes_t=$curif-bytes_in_t,$curif-bytes_out_t,+\" \\
		AREA:$curif-in_bits#990000:$curif-in \\
		AREA:$curif-out_bits_neg#666666:$curif-out \\
		COMMENT:\"\\n\"\\
		COMMENT:\"\t\t  maximum       average       current        period\\n\"\\
		COMMENT:\"in\t\"\\
		GPRINT:$curif-in_bits:MAX:'%7.2lf %sb/s'\\
		GPRINT:$curif-in_bits:AVERAGE:'%7.2lf %Sb/s'\\
		GPRINT:$curif-in_bits:LAST:'%7.2lf %Sb/s'\\
		GPRINT:$curif-bytes_in_t:AVERAGE:'%7.2lf %sB i'\\
		COMMENT:\"\\n\"\\
		COMMENT:\"out\t\"\\
		GPRINT:$curif-out_bits:MAX:'%7.2lf %sb/s'\\
		GPRINT:$curif-out_bits:AVERAGE:'%7.2lf %Sb/s'\\
		GPRINT:$curif-out_bits:LAST:'%7.2lf %Sb/s'\\
		GPRINT:$curif-bytes_out_t:AVERAGE:'%7.2lf %sB o'\\
		COMMENT:\"\\n\"\\
		COMMENT:\"totals\"\\
		GPRINT:$curif-bits_io:MAX:'%7.2lf %sb/s'\\
		GPRINT:$curif-bits_io:AVERAGE:'%7.2lf %sb/s'\\
		GPRINT:$curif-bits_io:LAST:'%7.2lf %sb/s'\\
		GPRINT:$curif-bytes_t:AVERAGE:'%7.2lf %sB t'\\
        	COMMENT:\"\\n\"\\
		COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t\t`date +\"%b %d %H\:%M\:%S %Y\"`\"";
	}
elseif(($curgraph == "packets") && (file_exists("$rrddbpath$curif$packets"))) {
	$page_images[] = "{$curif}-{$interval}-{$curgraph}";
	/* define graphcmd for packets stats */
	$graphcmd = "$rrdtool graph $rrddbpath$curif-$interval-$curgraph.png \\
		--start -$seconds -e -$average \\
		--vertical-label \"packets/sec\" \\
		--title \"`hostname` - $curgraph - $interval\" \\
		--height 100 --width 650 -x \"$scale\" \\
		DEF:$curif-in_pps=$rrddbpath$curif$packets:in:AVERAGE \\
		DEF:$curif-out_pps=$rrddbpath$curif$packets:out:AVERAGE \\
		\"CDEF:$curif-out_pps_neg=$curif-out_pps,-1,*\" \\
		\"CDEF:$curif-pps_in=$curif-in_pps,0,12500000,LIMIT,UN,0,$curif-in_pps,IF,$average,*\" \\
		\"CDEF:$curif-pps_out=$curif-out_pps,0,12500000,LIMIT,UN,0,$curif-out_pps,IF,$average,*\" \\
		\"CDEF:$curif-pps_io=$curif-in_pps,$curif-out_pps,+\" \\
		\"CDEF:$curif-pps=$curif-pps_in,$curif-pps_out,+\" \\
		\"CDEF:$curif-pps_in_t=$curif-in_pps,0,12500000,LIMIT,UN,0,$curif-in_pps,IF,$seconds,*\" \\
		\"CDEF:$curif-pps_out_t=$curif-out_pps,0,12500000,LIMIT,UN,0,$curif-out_pps,IF,$seconds,*\" \\
		\"CDEF:$curif-pps_t=$curif-pps_in_t,$curif-pps_out_t,+\" \\
		AREA:$curif-in_pps#990000:$curif-in \\
		AREA:$curif-out_pps_neg#666666:$curif-out \\
		COMMENT:\"\\n\"\\
		COMMENT:\"\t\t  maximum       average       current        period\\n\"\\
		COMMENT:\"in\t\"\\
		GPRINT:$curif-in_pps:MAX:'%7.2lf %s pps'\\
		GPRINT:$curif-in_pps:AVERAGE:'%7.2lf %S pps'\\
		GPRINT:$curif-in_pps:LAST:'%7.2lf %S pps'\\
		GPRINT:$curif-pps_in_t:AVERAGE:'%7.2lf %s pkts'\\
		COMMENT:\"\\n\"\\
		COMMENT:\"out\t\"\\
		GPRINT:$curif-out_pps:MAX:'%7.2lf %s pps'\\
		GPRINT:$curif-out_pps:AVERAGE:'%7.2lf %S pps'\\
		GPRINT:$curif-out_pps:LAST:'%7.2lf %S pps'\\
		GPRINT:$curif-pps_out_t:AVERAGE:'%7.2lf %s pkts'\\
		COMMENT:\"\\n\"\\
		COMMENT:\"totals\"\\
		GPRINT:$curif-pps_io:MAX:'%7.2lf %s pps'\\
		GPRINT:$curif-pps_io:AVERAGE:'%7.2lf %s pps'\\
		GPRINT:$curif-pps_io:LAST:'%7.2lf %s pps'\\
		GPRINT:$curif-pps_t:AVERAGE:'%7.2lf %s pkts'\\
        	COMMENT:\"\\n\"\\
		COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t\t`date +\"%b %d %H\:%M\:%S %Y\"`\"";
	}
elseif(($curgraph == "queues") && (file_exists("$rrddbpath$curif$queues"))) {
	$page_images[] = "{$curif}-{$interval}-{$curgraph}";
	/* define graphcmd for queue stats */
	$graphcmd = "$rrdtool graph $rrddbpath$curif-$interval-$curgraph.png \\
		--start -$seconds -e -$average \\
		--vertical-label \"bits/sec\" \\
		--title \"`hostname` - $curgraph - $interval\" \\
		--height 200 --width 650 -x \"$scale\" \\";
		if (!is_array($config['shaper']['queue'])) {
			$config['shaper']['queue'] = array();
		}
		$a_queues = &$config['shaper']['queue'];
		/* determine in and out interface at a later time. Asume wan for now.*/
		$i = 0;
		$t = 0;
		$colors = array('000000','7B0000','990000','BB0000','CC0000','D90000','EE0000','FF0000','CC0000');
		/* compare bytes/sec counters */
		$downstream = (22000000/8);
		$upstream   =  (2000000/8);
		foreach ($a_queues as $queue) {
			$name = $queue['name'];
			if((stristr($name, "wan")) || (stristr($name, "up"))) {
				$color = $colors[$t];
				if($t > 0) { $stack = ":STACK"; }
				$graphcmd .= "DEF:$name=$rrddbpath$curif$queues:$name:AVERAGE \\
					\"CDEF:$name-bytes_out=$name,0,$upstream,LIMIT,UN,0,$name,IF\" \\
					\"CDEF:$name-bits_out=$name-bytes_out,8,*\" \\
					\"CDEF:$name-bits_out_neg=$name-bits_out,-1,*\" \\
					AREA:$name-bits_out_neg#${color}:$name$stack \\";
					$t++;
			}
		}
		$graphcmd .= "COMMENT:\"\\n\" \\";
		$colors = array('000000','7B7B7B','999999','BBBBBB','CCCCCC','D9D9D9','EEEEEE','FFFFFF','CCCCCC');
		$stack = "";
		foreach ($a_queues as $queue) {
			$name = $queue['name'];
			if((stristr($name, "lan")) || (stristr($name, "down"))) {
				$color = $colors[$i];
				if($i > 0) { $stack = ":STACK"; }
				$graphcmd .= "DEF:$name=$rrddbpath$curif$queues:$name:AVERAGE \\
					\"CDEF:$name-bytes_in=$name,0,$downstream,LIMIT,UN,0,$name,IF\" \\
					\"CDEF:$name-bits_in=$name-bytes_in,8,*\" \\
					AREA:$name-bits_in#${color}:$name$stack \\";
					$i++;
			}
		}
		$graphcmd .= "COMMENT:\"\\n\" \\";
		$graphcmd .= "COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t\t`date +\"%b %d %H\:%M\:%S %Y\"`\"";
	}
elseif(($curgraph == "quality") && (file_exists("$rrddbpath$curif$quality"))) {
	$page_images[] = "{$curif}-{$interval}-{$curgraph}";
	/* make a link quality graphcmd, we only have WAN for now, others too follow */
	$graphcmd = "$rrdtool graph $rrddbpath$curif-$interval-$curgraph.png \\
		--start -$seconds -e -$average \\
		--title=\"Link quality last $interval for $curif\" \\
		--vertical-label \"ms / %\" \\
		--height 100 --width 650 \\
		-x \"$scale\" --lower-limit 0 \\
		DEF:roundtrip=$rrddbpath$curif$quality:roundtrip:AVERAGE \\
		DEF:loss=$rrddbpath$curif$quality:loss:AVERAGE \\
		\"CDEF:roundavg=roundtrip,PREV(roundtrip),+,2,/\" \\
		\"CDEF:loss10=loss,10,*\" \\
		\"CDEF:r0=roundtrip,20,MIN\" \\
		\"CDEF:r1=roundtrip,60,MIN\" \\
		\"CDEF:r2=roundtrip,180,MIN\" \\
		\"CDEF:r3=roundtrip,420,MIN\" \\
		COMMENT:\"               * Roundtrip *                                     * Packet loss *\\n\" \\
		COMMENT:\"\\n\" \\
		COMMENT:\"  \" \\
		AREA:roundtrip#990000:\"> 420      ms\" \\
		GPRINT:roundtrip:MIN:\"    Min\\: %7.2lf ms\" \\
		COMMENT:\"               \" \\
		GPRINT:loss:MIN:\"Min\\: %3.1lf %%\\n\" \\
		COMMENT:\"  \" \\
    		AREA:r3#a83c3c:\"180-420    ms\" \\
		GPRINT:roundtrip:AVERAGE:\"    Avg\\: %7.2lf ms\" \\
		COMMENT:\"               \" \\
		GPRINT:loss:AVERAGE:\"Avg\\: %3.1lf %%\" \\
		COMMENT:\"   Packet loss multiplied\\n\" \\
		COMMENT:\"  \" \\
		AREA:r2#b36666:\"60-180     ms\" \\
		GPRINT:roundtrip:MAX:\"    Max\\: %7.2lf ms\" \\
		COMMENT:\"               \" \\
		GPRINT:loss:MAX:\"Max\\: %3.1lf %%\" \\
		COMMENT:\"   by 10 in graph.\\n\" \\
		COMMENT:\"  \" \\
		AREA:r1#bd9090:\"20-60      ms\" \\
		COMMENT:\"\\n\" \\
		COMMENT:\"  \" \\
		AREA:r0#cccccc:\"< 20       ms\" \\
		GPRINT:roundtrip:LAST:\"    Last\\: %7.2lf ms\" \\
		COMMENT:\"              \" \\
		GPRINT:loss:LAST:\"Last\: %3.1lf %%\" \\
		COMMENT:\"   \" \\
		AREA:loss10#ee0000:\"Packet loss\\n\" \\
		COMMENT:\"  \" \\
		LINE1:roundtrip#000000:\"roundtrip average\\n\" \\
		COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t\t`date +\"%b %d %H\:%M\:%S %Y\"`\"";
	}
elseif(($curgraph == "spamd") && (file_exists("$rrddbpath$spamd"))) {
	$page_images[] = "{$curif}-{$interval}-{$curgraph}";
	/* graph a spamd statistics graph */
	$graphcmd = "$rrdtool graph $rrddbpath$curif-$interval-$curgraph.png \\
		--start -$seconds -e -$average \\
		--title=\"SpamD statistics for last $interval\" \\
		--vertical-label=\"Connections/Timespan\" --rigid \\
		--height 100 --width 650 --no-gridfit \\
		-x \"$scale\" --lower-limit 0 --upper-limit=30 \\
		DEF:timemin=$rrddbpath$spamd:time:MIN \\
		DEF:timeavg=$rrddbpath$spamd:time:AVERAGE \\
		DEF:timemax=$rrddbpath$spamd:time:MAX \\
		DEF:consmin=$rrddbpath$spamd:conn:MIN \\
		DEF:consavg=$rrddbpath$spamd:conn:AVERAGE \\
		DEF:consmax=$rrddbpath$spamd:conn:MAX \\
		CDEF:timeminadj=timemin,UN,0,timemin,IF,60,/ \\
		CDEF:timeavgadj=timeavg,UN,0,timeavg,IF,60,/ \\
		CDEF:timemaxadj=timemax,UN,0,timemax,IF,60,/ \\
		CDEF:t1=timeminadj,timeavgadj,+,2,/,timeminadj,- \\
		CDEF:t2=timeavgadj,timemaxadj,+,2,/,timeminadj,-,t1,- \\
		CDEF:t3=timemaxadj,timeminadj,-,t1,-,t2,- \\
		AREA:timeminadj \\
		AREA:t1#DDDDFF::STACK \\
		AREA:t2#AAAAFF::STACK \\
		AREA:t3#DDDDFF::STACK \\
		LINE2:timeavgadj#000066:\"Time \" \\
		GPRINT:timeminadj:MIN:\"Min\\:%6.2lf\\t\" \\
		GPRINT:timeavgadj:AVERAGE:\"Avg\\:%6.2lf\\t\" \\
		GPRINT:timemaxadj:MAX:\"Max\\:%6.2lf\\n\" \\
		AREA:consmax#00AA00BB \\
		AREA:consmin#FFFFFFFF \\
		LINE1:consmin#00660088 \\
		LINE1:consmax#FFFFFF88 \\
		LINE1:consavg#006600:\"Cons \" \\
		GPRINT:consmin:MIN:\"Min\\:%6.2lf\\t\" \\
		GPRINT:consavg:AVERAGE:\"Avg\\:%6.2lf\\t\" \\
		GPRINT:consmax:MAX:\"Max\\:%6.2lf\\n\" \\
		COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t\t`date +\"%b %d %H\:%M\:%S %Y\"`\"";
	}
else
	{
		PRINT "<b>Sorry we do not have data to graph $curgraph for $curif with.</b><br>";
		break;
	} 

	/* check modification time to see if we need to generate image */
	if (file_exists("$rrddbpath$curif-$interval-$curgraph.png")) {
		if((time() - filemtime("$rrddbpath$curif-$interval-$curgraph.png")) >= 280 ) {
			system("$graphcmd >/dev/null");
			usleep(500);
		}			
	} else {
		system("$graphcmd >/dev/null");
		usleep(500);
	}

PRINT "<B>Analysis for $curif -- $interval $curgraph</B><BR>";
PRINT "<IMG BORDER=\"1\" id=\"{$curif}-{$interval}-{$curgraph}\" name=\"{$curif}-{$interval}-{$curgraph}\" ALT=\"$ifname $curgraph Graph\" 
SRC=\"rrd/{$curif}-{$interval}-{$curgraph}.png\"><BR><BR>";
}

?>

</div>
<script language="javascript">
	function update_graph_images() {
		<?php
			/* generate update events utilizing prototype $('') feature */
			echo "\n";
			foreach($page_images as $pi) {
				echo "\t\t\$('{$pi}').src='/rrd/{$pi}.png?tmp=" . rand() . "';\n";	
			}
		?>	
		window.setTimeout('update_graph_images()', 25000);
	}
	window.setTimeout('update_graph_images()', 25000);
</script>

<!--<meta http-equiv="refresh" content="300;url=<?php print $_SERVER['PHP_SELF']; ?>">-->

<?php include("fend.inc"); ?>
</body>
</html>
