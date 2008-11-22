<?php
/* $Id$ */
/*
	status_rrd_graph_img.php
	Part of pfSense
	Copyright (C) 2007 Seth Mos <seth.mos@xs4all.nl>
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

$pgtitle = array("System","RRD Graphs","Image viewer");

if ($_GET['database']) {
	$curdatabase = $_GET['database'];
} else {
	$curdatabase = "wan-traffic.rrd";
}

if ($_GET['style']) {
	$curstyle = $_GET['style'];
} else {
	$curstyle = "inverse";
}

if ($_GET['interval']) {
	$interval = $_GET['interval'];
} else {
	$interval = "4h";
}

/* Deduce a interface if possible and use the description */
$curif = split("-", $curdatabase);
$curif = "$curif[0]";
$friendly = convert_friendly_interface_to_friendly_descr(strtolower($curif));
$search = array("-", ".rrd", $curif);
$replace = array(" :: ", "", $friendly);
$prettydb = ucwords(str_replace($search, $replace, $curdatabase));

$periods = array("4h", "16h", "48h", "32d", "6m", "1y", "4y");

$found = 0;
foreach($periods as $period) if($period == $interval) $found = 1;
if($found == 0) {
	PRINT "Graph interval $interval is not valid <br />\n";
	exit();
}

$graphs['4h']['seconds'] = 14400;
$graphs['4h']['average'] = 60;
$graphs['4h']['scale'] = "MINUTE:5:MINUTE:10:MINUTE:30:0:%H%:%M";
$graphs['16h']['seconds'] = 57600;
$graphs['16h']['average'] = 60;
$graphs['16h']['scale'] = "MINUTE:30:HOUR:1:HOUR:1:0:%H";
$graphs['48h']['seconds'] = 172800;
$graphs['48h']['average'] = 300;
$graphs['48h']['scale'] = "HOUR:1:HOUR:6:HOUR:2:0:%H";
$graphs['32d']['seconds'] = 2764800;
$graphs['32d']['average'] = 3600;
$graphs['32d']['scale'] = "DAY:1:WEEK:1:WEEK:1:0:Week %W";
$graphs['6m']['seconds'] = 16070400;
$graphs['6m']['average'] = 43200;
$graphs['6m']['scale'] = "WEEK:1:MONTH:1:MONTH:1:0:%b";
$graphs['1y']['seconds'] = 31622400;
$graphs['1y']['average'] = 43200;
$graphs['1y']['scale'] = "MONTH:1:MONTH:3:MONTH:1:0:%b";
$graphs['4y']['seconds'] = 126489600;
$graphs['4y']['average'] = 86400;
$graphs['4y']['scale'] = "MONTH:1:YEAR:1:MONTH:3:0:%b";

/* generate the graphs when we request the page. */
$seconds = $graphs[$interval]['seconds'];
$average = $graphs[$interval]['average'];
$scale = $graphs[$interval]['scale'];

$rrddbpath = "/var/db/rrd/";
$rrdtmppath = "/tmp/";
$rrdtool = "/usr/local/bin/rrdtool";
$uptime = "/usr/bin/uptime";
$sed = "/usr/bin/sed";

$havg = humantime($average);
$hperiod = humantime($seconds);
$data = true;

/* XXX: (billm) do we have an exec() type function that does this type of thing? */
exec("cd $rrddbpath;/usr/bin/find -name *.rrd", $databases);
rsort($databases);

/* compare bytes/sec counters, divide bps by 8 */
read_altq_config();
if ($altq_list_queues[$curif]) {
	$altq =& $altq_list_queues[$curif];
	switch ($altq->GetBwscale()) {
        case "Gb":
                $factor = 1000 * 1000 * 1000;
        break;
        case "Mb":
                $factor = 1000 * 1000;
        break;
        case "Kb":
                $factor = 1000;
        break;
        case "b":
        default:
                $factor = 1;
        break;
        }
	$upstream = (($altq->GetBandwidth()*$factor)/8);
	$downstream = $upstream; /* XXX: Ugly hack */
	$upif = $curif;
	$downif = "lan"; /* XXX should this be set to something else?! */
} else {
	$altq = null;
	$downstream = 12500000;
	$upstream = 12500000;
	$upif = "wan";
	$downif = "lan";
}

$speedlimit = ($upstream + $downstream);

/* select theme colors if the inclusion file exists */
$rrdcolors = "./themes/{$g['theme']}/rrdcolors.inc.php";
if(file_exists($rrdcolors)) {
	include($rrdcolors);
} else {
	// log_error("rrdcolors.inc.php for theme {$g['theme']} does not exist, using defaults!");
	$colortrafficup = "666666";
	$colortrafficdown = "990000";
	$colorpacketsup = "666666";
	$colorpacketsdown = "990000";
	$colorstates = array('990000','a83c3c','b36666','bd9090','cccccc','000000');
	$colorprocessor = array('990000','a83c3c','b36666','bd9090','cccccc','000000');
	$colormemory = array('990000','a83c3c','b36666','bd9090','cccccc','000000');
	$colorqueuesup = array('000000','7B0000','990000','BB0000','CC0000','D90000','EE0000','FF0000','CC0000');
	$colorqueuesdown = array('000000','7B7B7B','999999','BBBBBB','CCCCCC','D9D9D9','EEEEEE','FFFFFF','CCCCCC');
	$colorqueuesdropup = array('000000','7B0000','990000','BB0000','CC0000','D90000','EE0000','FF0000','CC0000');
	$colorqueuesdropdown = array('000000','7B7B7B','999999','BBBBBB','CCCCCC','D9D9D9','EEEEEE','FFFFFF','CCCCCC');
	$colorqualityrtt = array('990000','a83c3c','b36666','bd9090','cccccc','000000');
	$colorqualityloss = "ee0000";
	$colorwireless = array('990000','a83c3c','b36666');
	$colorspamdtime = array('DDDDFF', 'AAAAFF', 'DDDDFF', '000066'); 
	$colorspamdconn = array('00AA00BB', 'FFFFFFFF', '00660088', 'FFFFFF88', '006600');
}

switch ($curstyle) {
case "absolute":
	$multiplier = 1;
	$AREA = "LINE1";
	break;
default:
	$multiplier = -1;
	$AREA = "AREA";
	break;
}

function humantime($timestamp){
	$difference = $timestamp;
	$periods = array("second", "minute", "hour", "day", "week", "month", "year", "decade");
	$lengths = array("60","60","24","7","4.35","12","10");
	for($j = 0; $difference >= $lengths[$j]; $j++)
		$difference /= $lengths[$j];
		$difference = round($difference);
	if($difference != 1) $periods[$j].= "s";
		$text = "$difference $periods[$j]";
   return $text;
}

if((strstr($curdatabase, "-traffic.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for traffic stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$interval.png \\
		--start -$seconds -e -$average \\
		--vertical-label \"bits/sec\" \\
		--color SHADEA#eeeeee --color SHADEB#eeeeee \\
		--title \"`hostname` - $prettydb - $hperiod - $havg average\" \\
		--height 200 --width 620 -x \"$scale\" \\
		DEF:$curif-in_bytes=$rrddbpath$curdatabase:in:AVERAGE \\
		DEF:$curif-out_bytes=$rrddbpath$curdatabase:out:AVERAGE \\
		\"CDEF:$curif-in_bits=$curif-in_bytes,8,*\" \\
		\"CDEF:$curif-out_bits=$curif-out_bytes,8,*\" \\
		\"CDEF:$curif-bits_io=$curif-in_bits,$curif-out_bits,+\" \\
		\"CDEF:$curif-out_bits_neg=$curif-out_bits,$multiplier,*\" \\
		\"CDEF:$curif-bytes_in=$curif-in_bytes,0,$speedlimit,LIMIT,UN,0,$curif-in_bytes,IF,$average,*\" \\
		\"CDEF:$curif-bytes_out=$curif-out_bytes,0,$speedlimit,LIMIT,UN,0,$curif-out_bytes,IF,$average,*\" \\
		\"CDEF:$curif-bytes=$curif-bytes_in,$curif-bytes_out,+\" \\
		\"CDEF:$curif-bytes_in_t=$curif-in_bytes,0,$speedlimit,LIMIT,UN,0,$curif-in_bytes,IF,$seconds,*\" \\
		\"CDEF:$curif-bytes_out_t=$curif-out_bytes,0,$speedlimit,LIMIT,UN,0,$curif-out_bytes,IF,$seconds,*\" \\
		\"CDEF:$curif-bytes_t=$curif-bytes_in_t,$curif-bytes_out_t,+\" \\
		AREA:$curif-in_bits#$colortrafficdown:$curif-in \\
		$AREA:$curif-out_bits_neg#$colortrafficup:$curif-out \\
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
		COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t`date +\"%b %d %H\:%M\:%S %Y\"`\"";
	}
elseif(strstr($curdatabase, "-throughput.rrd")) {
	/* define graphcmd for throughput stats */
	/* this gathers all interface statistics, the database does not actually exist */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$interval.png \\
		--start -$seconds -e -$average \\
		--vertical-label \"bits/sec\" \\
		--color SHADEA#eeeeee --color SHADEB#eeeeee \\
		--title \"`hostname` - $prettydb - $hperiod - $havg average\" \\
		--height 200 --width 620 -x \"$scale\" ";
	$vfaces = array ("vlan.?*");
	$ifdescrs = get_interface_list();
	$g = 0;
	$operand = "";
	$comma = "";
	$graphtputbi = "";
	$graphtputbo = "";
	$graphtputbt = "";
	$graphtputbyi = "";
	$graphtputbyo = "";
	$graphtputbyt = "";
	foreach($ifdescrs as $ifdescr) {
		$ifname = $ifdescr['friendly'];
		$state = $ifdescr['up'];
		/* skip interfaces that do not have a friendly name */
		if ("$ifname" == "") {
			continue;
		}
		/* or are down */
		if (!$state) {
			continue;
		}
		/* collect all interface stats */
		$graphcmd .= "\"DEF:{$ifname}-in_bytes={$rrddbpath}{$ifname}-traffic.rrd:in:AVERAGE\" ";
		$graphcmd .= "\"DEF:{$ifname}-out_bytes={$rrddbpath}{$ifname}-traffic.rrd:out:AVERAGE\" ";
		$graphcmd .= "\"CDEF:{$ifname}-in_bits={$ifname}-in_bytes,8,*\"  ";
		$graphcmd .= "\"CDEF:{$ifname}-out_bits={$ifname}-out_bytes,8,*\" ";
		$graphcmd .= "\"CDEF:{$ifname}-bits_io={$ifname}-in_bits,{$ifname}-out_bits,+\" ";
		$graphcmd .= "\"CDEF:{$ifname}-bytes_in={$ifname}-in_bytes,0,$speedlimit,LIMIT,UN,0,{$ifname}-in_bytes,IF,$average,*\" ";
		$graphcmd .= "\"CDEF:{$ifname}-bytes_out={$ifname}-out_bytes,0,$speedlimit,LIMIT,UN,0,{$ifname}-out_bytes,IF,$average,*\" ";
		$graphcmd .= "\"CDEF:{$ifname}-bytes={$ifname}-bytes_in,{$ifname}-bytes_out,+\" ";
		$graphcmd .= "\"CDEF:{$ifname}-bytes_in_t={$ifname}-in_bytes,0,$speedlimit,LIMIT,UN,0,{$ifname}-in_bytes,IF,$seconds,*\" ";
		$graphcmd .= "\"CDEF:{$ifname}-bytes_out_t={$ifname}-out_bytes,0,$speedlimit,LIMIT,UN,0,{$ifname}-out_bytes,IF,$seconds,*\" ";
		$graphcmd .= "\"CDEF:{$ifname}-bytes_t={$ifname}-bytes_in_t,{$ifname}-bytes_out_t,+\" ";
		if ($g > 0) {
			$operand .= ",+";
			$comma = ",";
		}
		$graphtputbi .= "{$comma}{$ifname}-in_bits";
		$graphtputbo .= "{$comma}{$ifname}-out_bits";
		$graphtputbt .= "{$comma}{$ifname}-bits_io";
		$graphtputbyi .= "{$comma}{$ifname}-bytes_in_t";
		$graphtputbyo .= "{$comma}{$ifname}-bytes_out_t";
		$graphtputbyt .= "{$comma}{$ifname}-bytes_t";
		$g++;
	}
	$graphcmd .= "\"CDEF:tput-in_bits={$graphtputbi}{$operand}\" ";
	$graphcmd .= "\"CDEF:tput-out_bits={$graphtputbo}{$operand}\" ";
	$graphcmd .= "\"CDEF:tput-bits_io={$graphtputbt}{$operand}\" ";
	$graphcmd .= "\"CDEF:tput-out_bits_neg=tput-out_bits,$multiplier,*\" ";
	$graphcmd .= "\"CDEF:tput-bytes_in_t={$graphtputbyi}{$operand}\" ";
	$graphcmd .= "\"CDEF:tput-bytes_out_t={$graphtputbyo}{$operand}\" ";
	$graphcmd .= "\"CDEF:tput-bytes_t={$graphtputbyt}{$operand}\" ";
	$graphcmd .= "AREA:tput-in_bits#$colortrafficdown:in \\
		$AREA:tput-out_bits_neg#$colortrafficup:out \\
		COMMENT:\"\\n\"\\
		COMMENT:\"\t\t  maximum       average       current        period\\n\"\\
		COMMENT:\"in\t\"\\
		GPRINT:tput-in_bits:MAX:'%7.2lf %sb/s'\\
		GPRINT:tput-in_bits:AVERAGE:'%7.2lf %Sb/s'\\
		GPRINT:tput-in_bits:LAST:'%7.2lf %Sb/s'\\
		GPRINT:tput-bytes_in_t:AVERAGE:'%7.2lf %sB i'\\
		COMMENT:\"\\n\"\\
		COMMENT:\"out\t\"\\
		GPRINT:tput-out_bits:MAX:'%7.2lf %sb/s'\\
		GPRINT:tput-out_bits:AVERAGE:'%7.2lf %Sb/s'\\
		GPRINT:tput-out_bits:LAST:'%7.2lf %Sb/s'\\
		GPRINT:tput-bytes_out_t:AVERAGE:'%7.2lf %sB o'\\
		COMMENT:\"\\n\"\\
		COMMENT:\"totals\"\\
		GPRINT:tput-bits_io:MAX:'%7.2lf %sb/s'\\
		GPRINT:tput-bits_io:AVERAGE:'%7.2lf %sb/s'\\
		GPRINT:tput-bits_io:LAST:'%7.2lf %sb/s'\\
		GPRINT:tput-bytes_t:AVERAGE:'%7.2lf %sB t'\\
        	COMMENT:\"\\n\"\\
		COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t`date +\"%b %d %H\:%M\:%S %Y\"`\"";
	}
elseif((strstr($curdatabase, "-packets.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for packets stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$interval.png \\
		--start -$seconds -e -$average \\
		--vertical-label \"packets/sec\" \\
		--color SHADEA#eeeeee --color SHADEB#eeeeee \\
		--title \"`hostname` - $prettydb - $hperiod - $havg average\" \\
		--height 200 --width 620 -x \"$scale\" \\
		DEF:$curif-in_pps=$rrddbpath$curdatabase:in:AVERAGE \\
		DEF:$curif-out_pps=$rrddbpath$curdatabase:out:AVERAGE \\
		\"CDEF:$curif-out_pps_neg=$curif-out_pps,$multiplier,*\" \\
		\"CDEF:$curif-pps_in=$curif-in_pps,0,12500000,LIMIT,UN,0,$curif-in_pps,IF,$average,*\" \\
		\"CDEF:$curif-pps_out=$curif-out_pps,0,12500000,LIMIT,UN,0,$curif-out_pps,IF,$average,*\" \\
		\"CDEF:$curif-pps_io=$curif-in_pps,$curif-out_pps,+\" \\
		\"CDEF:$curif-pps=$curif-pps_in,$curif-pps_out,+\" \\
		\"CDEF:$curif-pps_in_t=$curif-in_pps,0,12500000,LIMIT,UN,0,$curif-in_pps,IF,$seconds,*\" \\
		\"CDEF:$curif-pps_out_t=$curif-out_pps,0,12500000,LIMIT,UN,0,$curif-out_pps,IF,$seconds,*\" \\
		\"CDEF:$curif-pps_t=$curif-pps_in_t,$curif-pps_out_t,+\" \\
		AREA:$curif-in_pps#$colorpacketsdown:$curif-in \\
		$AREA:$curif-out_pps_neg#$colorpacketsup:$curif-out \\
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
		COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t`date +\"%b %d %H\:%M\:%S %Y\"`\"";
	}
elseif((strstr($curdatabase, "-wireless.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for packets stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$interval.png \\
		--start -$seconds -e -$average \\
		--vertical-label \"snr/channel/rate\" \\
		--color SHADEA#eeeeee --color SHADEB#eeeeee \\
		--title \"`hostname` - $prettydb - $hperiod - $havg average\" \\
		--height 200 --width 620 -x \"$scale\" \\
		DEF:$curif-snr=$rrddbpath$curdatabase:snr:AVERAGE \\
		DEF:$curif-rate=$rrddbpath$curdatabase:rate:AVERAGE \\
		DEF:$curif-channel=$rrddbpath$curdatabase:channel:AVERAGE \\
		LINE1:$curif-snr#{$colorwireless[0]}:$curif-snr \\
		LINE1:$curif-rate#{$colorwireless[1]}:$curif-rate \\
		LINE1:$curif-channel#{$colorwireless[2]}:$curif-channel \\
		COMMENT:\"\\n\"\\
		COMMENT:\"\t\t  maximum       average       current        period\\n\"\\
		COMMENT:\"SNR\t\t\"\\
		GPRINT:$curif-snr:MAX:'%7.2lf %s dBi'\\
		GPRINT:$curif-snr:AVERAGE:'%7.2lf %S dBi'\\
		GPRINT:$curif-snr:LAST:'%7.2lf %S dBi'\\
		COMMENT:\"\\n\"\\
		COMMENT:\"RATE\t\t\"\\
		GPRINT:$curif-rate:MAX:'%7.2lf %s Mb '\\
		GPRINT:$curif-rate:AVERAGE:'%7.2lf %S Mb '\\
		GPRINT:$curif-rate:LAST:'%7.2lf %S Mb '\\
		COMMENT:\"\\n\"\\
		COMMENT:\"Channel\t\"\\
		GPRINT:$curif-channel:MAX:'%7.2lf %s    '\\
		GPRINT:$curif-channel:AVERAGE:'%7.2lf %s    '\\
		GPRINT:$curif-channel:LAST:'%7.2lf %s    '\\
        	COMMENT:\"\\n\"\\
		COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t`date +\"%b %d %H\:%M\:%S %Y\"`\"";
	}
elseif((strstr($curdatabase, "-states.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for states stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$interval.png \\
		--start -$seconds -e -$average \\
		--vertical-label \"states, ip\" \\
		--color SHADEA#eeeeee --color SHADEB#eeeeee \\
		--title \"`hostname` - $prettydb - $hperiod - $havg average\" \\
		--height 200 --width 620 -x \"$scale\" \\
		DEF:$curif-pfrate=$rrddbpath$curdatabase:pfrate:AVERAGE \\
		DEF:$curif-pfstates=$rrddbpath$curdatabase:pfstates:AVERAGE \\
		DEF:$curif-pfnat=$rrddbpath$curdatabase:pfnat:AVERAGE \\
		DEF:$curif-srcip=$rrddbpath$curdatabase:srcip:AVERAGE \\
		DEF:$curif-dstip=$rrddbpath$curdatabase:dstip:AVERAGE \\
		\"CDEF:$curif-pfrate_t=$curif-pfrate,0,1000000,LIMIT,UN,0,$curif-pfrate,IF,$seconds,*\" \\
		LINE1:$curif-pfrate#{$colorstates[0]}:$curif-pfrate \\
		LINE1:$curif-pfstates#{$colorstates[1]}:$curif-pfstates \\
		LINE1:$curif-pfnat#{$colorstates[2]}:$curif-pfnat \\
		LINE1:$curif-srcip#{$colorstates[3]}:$curif-srcip \\
		LINE1:$curif-dstip#{$colorstates[4]}:$curif-dstip \\
		COMMENT:\"\\n\"\\
		COMMENT:\"\t\t      minimum        average        maximum        current         period\\n\"\\
		COMMENT:\"state changes\"\\
		GPRINT:$curif-pfrate:MIN:'%7.2lf %s cps'\\
		GPRINT:$curif-pfrate:AVERAGE:'%7.2lf %s cps'\\
		GPRINT:$curif-pfrate:MAX:'%7.2lf %s cps'\\
		GPRINT:$curif-pfrate:LAST:'%7.2lf %S cps'\\
		GPRINT:$curif-pfrate_t:AVERAGE:'%7.2lf %s chg'\\
		COMMENT:\"\\n\"\\
		COMMENT:\"filter states\"\\
		GPRINT:$curif-pfstates:MIN:'%7.2lf %s    '\\
		GPRINT:$curif-pfstates:AVERAGE:'%7.2lf %s    '\\
		GPRINT:$curif-pfstates:MAX:'%7.2lf %s    '\\
		GPRINT:$curif-pfstates:LAST:'%7.2lf %s    '\\
		COMMENT:\"\\n\"\\
		COMMENT:\"nat states   \"\\
		GPRINT:$curif-pfnat:MIN:'%7.2lf %s    '\\
		GPRINT:$curif-pfnat:AVERAGE:'%7.2lf %s    '\\
		GPRINT:$curif-pfnat:MAX:'%7.2lf %s    '\\
		GPRINT:$curif-pfnat:LAST:'%7.2lf %s    '\\
        	COMMENT:\"\\n\"\\
		COMMENT:\"Source addr. \"\\
		GPRINT:$curif-srcip:MIN:'%7.2lf %s    '\\
		GPRINT:$curif-srcip:AVERAGE:'%7.2lf %s    '\\
		GPRINT:$curif-srcip:MAX:'%7.2lf %s    '\\
		GPRINT:$curif-srcip:LAST:'%7.2lf %s    '\\
        	COMMENT:\"\\n\"\\
		COMMENT:\"Dest. addr.  \"\\
		GPRINT:$curif-dstip:MIN:'%7.2lf %s    '\\
		GPRINT:$curif-dstip:AVERAGE:'%7.2lf %s    '\\
		GPRINT:$curif-dstip:MAX:'%7.2lf %s    '\\
		GPRINT:$curif-dstip:LAST:'%7.2lf %s    '\\
        	COMMENT:\"\\n\"\\
		COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t`date +\"%b %d %H\:%M\:%S %Y\"`\"";
	}
elseif((strstr($curdatabase, "-processor.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for processor stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$interval.png \\
		--start -$seconds -e -$average \\
		--vertical-label \"utilization, number\" \\
		--color SHADEA#eeeeee --color SHADEB#eeeeee \\
		--title \"`hostname` - $prettydb - $hperiod - $havg average\" \\
		--height 200 --width 620 -x \"$scale\" \\
		DEF:user=$rrddbpath$curdatabase:user:AVERAGE \\
		DEF:nice=$rrddbpath$curdatabase:nice:AVERAGE \\
		DEF:system=$rrddbpath$curdatabase:system:AVERAGE \\
		DEF:interrupt=$rrddbpath$curdatabase:interrupt:AVERAGE \\
		DEF:processes=$rrddbpath$curdatabase:processes:AVERAGE \\
		AREA:user#{$colorprocessor[0]}:user \\
		AREA:nice#{$colorprocessor[1]}:nice:STACK \\
		AREA:system#{$colorprocessor[2]}:system:STACK \\
		AREA:interrupt#{$colorprocessor[3]}:interrupt:STACK \\
		LINE2:processes#{$colorprocessor[4]}:processes \\
		COMMENT:\"\\n\"\\
		COMMENT:\"\t\t      minimum        average        maximum        current\\n\"\\
		COMMENT:\"User util.   \"\\
		GPRINT:user:MIN:'%7.2lf %s    '\\
		GPRINT:user:AVERAGE:'%7.2lf %s    '\\
		GPRINT:user:MAX:'%7.2lf %s    '\\
		GPRINT:user:LAST:'%7.2lf %S    '\\
		COMMENT:\"\\n\"\\
		COMMENT:\"Nice util.   \"\\
		GPRINT:nice:MIN:'%7.2lf %s    '\\
		GPRINT:nice:AVERAGE:'%7.2lf %s    '\\
		GPRINT:nice:MAX:'%7.2lf %s    '\\
		GPRINT:nice:LAST:'%7.2lf %s    '\\
		COMMENT:\"\\n\"\\
		COMMENT:\"System util. \"\\
		GPRINT:system:MIN:'%7.2lf %s    '\\
		GPRINT:system:AVERAGE:'%7.2lf %s    '\\
		GPRINT:system:MAX:'%7.2lf %s    '\\
		GPRINT:system:LAST:'%7.2lf %s    '\\
        	COMMENT:\"\\n\"\\
		COMMENT:\"Interrupt    \"\\
		GPRINT:interrupt:MIN:'%7.2lf %s    '\\
		GPRINT:interrupt:AVERAGE:'%7.2lf %s    '\\
		GPRINT:interrupt:MAX:'%7.2lf %s    '\\
		GPRINT:interrupt:LAST:'%7.2lf %s    '\\
        	COMMENT:\"\\n\"\\
		COMMENT:\"Processes    \"\\
		GPRINT:processes:MIN:'%7.2lf %s    '\\
		GPRINT:processes:AVERAGE:'%7.2lf %s    '\\
		GPRINT:processes:MAX:'%7.2lf %s    '\\
		GPRINT:processes:LAST:'%7.2lf %s    '\\
        	COMMENT:\"\\n\"\\
		COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t`date +\"%b %d %H\:%M\:%S %Y\"`\"";
	}
elseif((strstr($curdatabase, "-memory.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for memory usage stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$interval.png \\
		--start -$seconds -e -$average \\
		--vertical-label \"utilization, percent\" \\
		--color SHADEA#eeeeee --color SHADEB#eeeeee \\
		--title \"`hostname` - $prettydb - $hperiod - $havg average\" \\
		--height 200 --width 620 -x \"$scale\" \\
		DEF:active=$rrddbpath$curdatabase:active:AVERAGE \\
		DEF:inactive=$rrddbpath$curdatabase:inactive:AVERAGE \\
		DEF:free=$rrddbpath$curdatabase:free:AVERAGE \\
		DEF:cache=$rrddbpath$curdatabase:cache:AVERAGE \\
		DEF:wire=$rrddbpath$curdatabase:wire:AVERAGE \\
		LINE2:active#{$colormemory[0]}:active \\
		LINE2:inactive#{$colormemory[1]}:inactive \\
		LINE2:free#{$colormemory[2]}:free \\
		LINE2:cache#{$colormemory[3]}:cache \\
		LINE2:wire#{$colormemory[4]}:wire \\
		COMMENT:\"\\n\"\\
		COMMENT:\"\t\t      minimum        average        maximum        current\\n\"\\
		COMMENT:\"Active.      \"\\
		GPRINT:active:MIN:'%7.2lf %s    '\\
		GPRINT:active:AVERAGE:'%7.2lf %s    '\\
		GPRINT:active:MAX:'%7.2lf %s    '\\
		GPRINT:active:LAST:'%7.2lf %S    '\\
		COMMENT:\"\\n\"\\
		COMMENT:\"Inactive.    \"\\
		GPRINT:inactive:MIN:'%7.2lf %s    '\\
		GPRINT:inactive:AVERAGE:'%7.2lf %s    '\\
		GPRINT:inactive:MAX:'%7.2lf %s    '\\
		GPRINT:inactive:LAST:'%7.2lf %S    '\\
		COMMENT:\"\\n\"\\
		COMMENT:\"Free.        \"\\
		GPRINT:free:MIN:'%7.2lf %s    '\\
		GPRINT:free:AVERAGE:'%7.2lf %s    '\\
		GPRINT:free:MAX:'%7.2lf %s    '\\
		GPRINT:free:LAST:'%7.2lf %S    '\\
        	COMMENT:\"\\n\"\\
		COMMENT:\"Cached.      \"\\
		GPRINT:cache:MIN:'%7.2lf %s    '\\
		GPRINT:cache:AVERAGE:'%7.2lf %s    '\\
		GPRINT:cache:MAX:'%7.2lf %s    '\\
		GPRINT:cache:LAST:'%7.2lf %S    '\\
        	COMMENT:\"\\n\"\\
		COMMENT:\"Wired.       \"\\
		GPRINT:wire:MIN:'%7.2lf %s    '\\
		GPRINT:wire:AVERAGE:'%7.2lf %s    '\\
		GPRINT:wire:MAX:'%7.2lf %s    '\\
		GPRINT:wire:LAST:'%7.2lf %S    '\\
        	COMMENT:\"\\n\"\\
		COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t`date +\"%b %d %H\:%M\:%S %Y\"`\"";
	}
elseif((strstr($curdatabase, "-queues.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for queue stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$interval.png \\
		--start -$seconds -e -$average \\
		--vertical-label \"bits/sec\" \\
		--color SHADEA#eeeeee --color SHADEB#eeeeee \\
		--title \"`hostname` - $prettydb - $hperiod - $havg average\" \\
		--height 200 --width 620 -x \"$scale\" \\";
		if ($altq) 
			$a_queues =& $altq->get_queue_list();
		else
			$a_queues = array();
		$i = 0;
		$t = 0;
		foreach ($a_queues as $name => $q) {
				$color = "$colorqueuesup[$t]";
				//if($t > 0) { $stack = ":STACK"; }
				$graphcmd .= "DEF:$name=$rrddbpath$curdatabase:$name:AVERAGE \\
					\"CDEF:$name-bytes_out=$name,0,$speedlimit,LIMIT,UN,0,$name,IF\" \\
					\"CDEF:$name-bits_out=$name-bytes_out,8,*\" \\
					$AREA:$name-bits_out#${color}:$name \\";
					$t++;
					if($t > 7) { $t = 0; }
		}
		$graphcmd .= "COMMENT:\"\\n\" \\";
		$graphcmd .= "COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t`date +\"%b %d %H\:%M\:%S %Y\"`\"";
	}
elseif((strstr($curdatabase, "-queuedrops.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for queuedrop stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$interval.png \\
		--start -$seconds -e -$average \\
		--vertical-label \"drops / sec\" \\
		--color SHADEA#eeeeee --color SHADEB#eeeeee \\
		--title \"`hostname` - $prettydb - $hperiod - $havg average\" \\
		--height 200 --width 620 -x \"$scale\" \\";
		if ($altq) 
                        $a_queues =& $altq->get_queue_list();
                else
                        $a_queues = array();
		$i = 0;
		$t = 0;
		foreach ($a_queues as $name => $q) {
				$color = "$colorqueuesdropup[$t]";
				//if($t > 0) { $stack = ":STACK"; }
				$graphcmd .= "DEF:$name=$rrddbpath$curdatabase:$name:AVERAGE \\
					\"CDEF:$name-bytes_out=$name,0,$speedlimit,LIMIT,UN,0,$name,IF\" \\
					\"CDEF:$name-bits_out=$name-bytes_out,8,*\" \\
					\"CDEF:$name-bits_out_neg=$name-bits_out,$multiplier,*\" \\
					$AREA:$name-bits_out_neg#${color}:$name \\";
					$t++;
					if($t > 7) { $t = 0; }
		}
		$graphcmd .= "COMMENT:\"\\n\" \\";
		$graphcmd .= "COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t`date +\"%b %d %H\:%M\:%S %Y\"`\"";
	}
elseif((strstr($curdatabase, "-quality.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* make a link quality graphcmd, we only have WAN for now, others too follow */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$interval.png \\
		--start -$seconds -e -$average \\
		--title \"`hostname` - $prettydb - $hperiod - $havg average\" \\
		--color SHADEA#eeeeee --color SHADEB#eeeeee \\
		--vertical-label \"ms / %\" \\
		--height 200 --width 620 \\
		-x \"$scale\" --lower-limit 0 \\
		DEF:delay=$rrddbpath$curdatabase:delay:AVERAGE \\
		DEF:loss=$rrddbpath$curdatabase:loss:AVERAGE \\
		\"CDEF:roundavg=delay,PREV(delay),+,2,/\" \\
		\"CDEF:loss10=loss,$multiplier,*\" \\
		\"CDEF:r0=delay,20,MIN\" \\
		\"CDEF:r1=delay,60,MIN\" \\
		\"CDEF:r2=delay,180,MIN\" \\
		\"CDEF:r3=delay,420,MIN\" \\
		COMMENT:\"\t\t\t\t\tDelay\t\t\tPacket loss\\n\" \\
		AREA:delay#$colorqualityrtt[0]:\"> 420      ms\" \\
		GPRINT:delay:MIN:\"\t\tMin\\:  %7.2lf ms\" \\
		GPRINT:loss:MIN:\"\tMin\\: %3.1lf %%\\n\" \\
    		AREA:r3#$colorqualityrtt[1]:\"180-420    ms\" \\
		GPRINT:delay:AVERAGE:\"\t\tAvg\\:  %7.2lf ms\" \\
		GPRINT:loss:AVERAGE:\"\tAvg\\: %3.1lf %%\\n\" \\
		AREA:r2#$colorqualityrtt[2]:\"60-180     ms\" \\
		GPRINT:delay:MAX:\"\t\tMax\\:  %7.2lf ms\" \\
		GPRINT:loss:MAX:\"\tMax\\: %3.1lf %%\\n\" \\
		AREA:r1#$colorqualityrtt[3]:\"20-60      ms\\n\" \\
		AREA:r0#$colorqualityrtt[4]:\"< 20       ms\" \\
		GPRINT:delay:LAST:\"\t\tLast\\: %7.2lf ms\" \\
		GPRINT:loss:LAST:\"\tLast\: %3.1lf %%\\n\" \\
		AREA:loss10#$colorqualityloss:\"Packet loss\\n\" \\
		LINE1:delay#$colorqualityrtt[5]:\"Delay average\\n\" \\
		COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t`date +\"%b %d %H\:%M\:%S %Y\"`\"";
	}
elseif((strstr($curdatabase, "spamd.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* graph a spamd statistics graph */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$interval.png \\
		--start -$seconds -e -$average \\
		--title \"`hostname` - $prettydb - $hperiod - $havg average\" \\
		--color SHADEA#eeeeee --color SHADEB#eeeeee \\
		--vertical-label=\"Conn / Time, sec.\" \\
		--height 200 --width 620 --no-gridfit \\
		-x \"$scale\" --lower-limit 0  \\
		DEF:consmin=$rrddbpath$curdatabase:conn:MIN \\
		DEF:consavg=$rrddbpath$curdatabase:conn:AVERAGE \\
		DEF:consmax=$rrddbpath$curdatabase:conn:MAX \\
		DEF:timemin=$rrddbpath$curdatabase:time:MIN \\
		DEF:timeavg=$rrddbpath$curdatabase:time:AVERAGE \\
		DEF:timemax=$rrddbpath$curdatabase:time:MAX \\
		\"CDEF:timeminadj=timemin,0,86400,LIMIT,UN,0,timemin,IF\" \\
		\"CDEF:timeavgadj=timeavg,0,86400,LIMIT,UN,0,timeavg,IF\" \\
		\"CDEF:timemaxadj=timemax,0,86400,LIMIT,UN,0,timemax,IF\" \\
		\"CDEF:t1=timeminadj,timeavgadj,+,2,/,timeminadj,-\" \\
		\"CDEF:t2=timeavgadj,timemaxadj,+,2,/,timeminadj,-,t1,-\" \\
		\"CDEF:t3=timemaxadj,timeminadj,-,t1,-,t2,-\" \\
		AREA:timeminadj \\
		AREA:t1#$colorspamdtime[0]::STACK \\
		AREA:t2#$colorspamdtime[1]::STACK \\
		AREA:t3#$colorspamdtime[2]::STACK \\
		LINE2:timeavgadj#$colorspamdtime[3]:\"Time \" \\
		GPRINT:timeminadj:MIN:\"Min\\:%6.2lf\\t\" \\
		GPRINT:timeavgadj:AVERAGE:\"Avg\\:%6.2lf\\t\" \\
		GPRINT:timemaxadj:MAX:\"Max\\:%6.2lf\\n\" \\
		AREA:consmax#$colorspamdconn[0] \\
		AREA:consmin#$colorspamdconn[1] \\
		LINE1:consmin#$colorspamdconn[2] \\
		LINE1:consmax#$colorspamdconn[3] \\
		LINE1:consavg#$colorspamdconn[4]:\"Cons \" \\
		GPRINT:consmin:MIN:\"Min\\:%6.2lf\\t\" \\
		GPRINT:consavg:AVERAGE:\"Avg\\:%6.2lf\\t\" \\
		GPRINT:consmax:MAX:\"Max\\:%6.2lf\\n\" \\
		COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t`date +\"%b %d %H\:%M\:%S %Y\"`\"";
	}
else
	{
		$data = false;
		log_error("Sorry we do not have data to graph for $curdatabase");
	} 

	/* check modification time to see if we need to generate image */
	if (file_exists("$rrdtmppath$curdatabase-$interval.png")) {
		if((time() - filemtime("$rrdtmppath$curdatabase-$interval.png")) >= 55 ) {
			if($data)
				exec("$graphcmd 2>&1", $graphcmdoutput, $graphcmdreturn);
				flush();
				usleep(500);
		}			
	} else {
		if($data)
			exec("$graphcmd 2>&1", $graphcmdoutput, $graphcmdreturn);
			flush();
			usleep(500);
	}
	if(!empty($graphcmdoutput)) {
		$graphcmdoutput = implode(" ", $graphcmdoutput);
	}
	if(($graphcmdreturn != 0) || (! $data)) {
		log_error("Failed to create graph with error code $graphcmdreturn, the error is: $graphcmdoutput");
		if(strstr($curdatabase, "queues")) {
			log_error("failed to create graph from $rrddbpath$curdatabase, removing database");
			exec("/bin/rm -f $rrddbpath$curif$queues");
			flush();
			usleep(500);
			enable_rrd_graphing();
		}
		if(strstr($curdatabase, "queuesdrop")) {
			log_error("failed to create graph from $rrddbpath$curdatabase, removing database");
			exec("/bin/rm -f $rrddbpath$curdatabase");
			flush();
			usleep(500);
			enable_rrd_graphing();
		}
		header("Content-type: image/png");
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
		$file= "/usr/local/www/themes/{$g['theme']}/images/misc/rrd_error.png";
		readfile($file);
	} else {
		$file = "$rrdtmppath$curdatabase-$interval.png";
		if(file_exists("$file")) {
			header("Content-type: image/png");
			header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
			header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
			header("Cache-Control: no-store, no-cache, must-revalidate");
			header("Cache-Control: post-check=0, pre-check=0", false);
			header("Pragma: no-cache");
			readfile($file);
		}
	}

?>
