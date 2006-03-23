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

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<?php
$ifdescrs = array('wan' => 'WAN', 'lan' => 'LAN');
$graphs = array('traffic' => 'Traffic', 'quality' => 'Quality');

for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
	$ifdescrs['opt' . $j] = $config['interfaces']['opt' . $j]['descr'];
}
?>
<form name="form1" action="status_rrd_graph.php" method="get" style="padding-bottom: 10px; margin-bottom: 14px; 
border-bottom: 1px solid #999999">
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

$periods = array("day", "week", "month", "year");
$graphs['day']['seconds'] = 86400;
$graphs['day']['average'] = 300;
$graphs['day']['scale'] = "HOUR:1:HOUR:6:HOUR:2:0:%H";
$graphs['week']['seconds'] = 604800;
$graphs['week']['average'] = 1800;
$graphs['week']['scale'] = "HOUR:6:DAY:1:DAY:1:0:%a";
$graphs['month']['seconds'] = 2592000;
$graphs['month']['average'] = 7200;
$graphs['month']['scale'] = "DAY:1:WEEK:1:WEEK:1:0:Week %W";
$graphs['year']['seconds'] = 31536000;
$graphs['year']['average'] = 86400;
$graphs['year']['scale'] = "MONTH:1:MONTH:1:MONTH:1:0:%b";

$rrddbpath = "/var/run/rrd/";
$traffic = "-traffic.rrd";
$quality = "-quality.rrd";
$queues = "-queues.rrd";
$rrdtool = "/usr/local/bin/rrdtool";
$uptime = "/usr/bin/uptime";
$sed = "/usr/bin/sed";

foreach($periods as $period => $interval) {

/* generate the graphs when we request the page. */
$seconds = $graphs[$interval]['seconds'];
$average = $graphs[$interval]['average'];
$scale = $graphs[$interval]['scale'];

if(($curgraph == "traffic") && (file_exists("$rrddbpath$curif$traffic"))) {
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
		\"CDEF:$curif-out_bits_neg=$curif-out_bits,-1,*\" \\
		\"CDEF:$curif-bytes_in=$curif-in_bytes,0,12500000,LIMIT,UN,0,$curif-in_bytes,IF,$average,*\" \\
		\"CDEF:$curif-bytes_out=$curif-out_bytes,0,12500000,LIMIT,UN,0,$curif-out_bytes,IF,$average,*\" \\
		\"CDEF:$curif-bytes=$curif-bytes_in,$curif-bytes_out,+\" \\
		AREA:$curif-in_bits#990000:$curif-in \\
		AREA:$curif-out_bits_neg#666666:$curif-out \\
		COMMENT:\"\\n\"\\
		COMMENT:\"\t\t  maximum       average       current\\n\"\\
		COMMENT:\"in\t\"\\
		GPRINT:$curif-in_bits:MAX:'%7.2lf %sb/s'\\
		GPRINT:$curif-in_bits:AVERAGE:'%7.2lf %Sb/s'\\
		GPRINT:$curif-in_bits:LAST:'%7.2lf %Sb/s'\\
		COMMENT:\"\\n\"\\
		COMMENT:\"out\t\"\\
		GPRINT:$curif-out_bits:MAX:'%7.2lf %Sb/s'\\
		GPRINT:$curif-out_bits:AVERAGE:'%7.2lf %Sb/s'\\
		GPRINT:$curif-out_bits:LAST:'%7.2lf %Sb/s'\\
		COMMENT:\"\\n\"\\
		COMMENT:\"totals\"\\
       		GPRINT:$curif-bytes_in:AVERAGE:'%7.2lf %sB i'\\
        	GPRINT:$curif-bytes_out:AVERAGE:'%7.2lf %sB o'\\
        	GPRINT:$curif-bytes:AVERAGE:'%7.2lf %sB t'\\
        	COMMENT:\"\\n\"\\
		COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t\t`date +\"%b %d %H\:%M\:%S %Y\"`\"";
	}
elseif(($curgraph == "quality") && (file_exists("$rrddbpath$curif$quality"))) {
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
		LINE1:roundtrip#ff0000:\"roundtrip average\\n\" \\
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
PRINT "<IMG BORDER=1 ALT=\"$ifname $curgraph Graph\" 
SRC=\"rrd/$curif-$interval-$curgraph.png\"><BR><BR>";
}

?>

</div>

<meta http-equiv="refresh" content="300;url=<?php print $_SERVER['PHP_SELF']; ?>">

<?php include("fend.inc"); ?>
</body>
</html>
