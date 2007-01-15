<?php
/* $Id$ */
/*
	status_rrd_graph_img.php
	Part of pfSense
	Copyright (C) 2006 Seth Mos <seth.mos@xs4all.nl>
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

$periods = array("4h", "16h", "48h", "32d", "6m", "16m");

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
$graphs['16m']['seconds'] = 42854400;
$graphs['16m']['average'] = 43200;
$graphs['16m']['scale'] = "MONTH:1:MONTH:1:MONTH:1:0:%b";

$rrddbpath = "/var/db/rrd/";
$rrdtmppath = "/tmp/";
$traffic = "-traffic.rrd";
$quality = "-quality.rrd";
$queues = "-queues.rrd";
$queuesdrop = "-queuesdrop.rrd";
$packets = "-packets.rrd";
$states = "-states.rrd";
$spamd = "-spamd.rrd";
$rrdtool = "/usr/local/bin/rrdtool";
$uptime = "/usr/bin/uptime";
$sed = "/usr/bin/sed";

/* compare bytes/sec counters, divide bps by 8 */
if (isset($config['ezshaper']['step2']['download'])) {
	$downstream = (($config['ezshaper']['step2']['download']*1024)/8);
	$upstream = (($config['ezshaper']['step2']['upload']*1024)/8);
	$upif = $config['ezshaper']['step2']['outside_int'];
	$downif = $config['ezshaper']['step2']['inside_int'];
} else {
	$downstream = 12500000;
	$upstream = 12500000;
	$upif = "wan";
	$downif = "lan";
}


/* generate the graphs when we request the page. */
$seconds = $graphs[$interval]['seconds'];
$average = $graphs[$interval]['average'];
$scale = $graphs[$interval]['scale'];

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
	$colorqueuesup = array('000000','7B0000','990000','BB0000','CC0000','D90000','EE0000','FF0000','CC0000');
	$colorqueuesdown = array('000000','7B7B7B','999999','BBBBBB','CCCCCC','D9D9D9','EEEEEE','FFFFFF','CCCCCC');
	$colorqueuesdropup = array('000000','7B0000','990000','BB0000','CC0000','D90000','EE0000','FF0000','CC0000');
	$colorqueuesdropdown = array('000000','7B7B7B','999999','BBBBBB','CCCCCC','D9D9D9','EEEEEE','FFFFFF','CCCCCC');
	$colorqualityrtt = array('990000','a83c3c','b36666','bd9090','cccccc','000000');
	$colorqualityloss = "ee0000";
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
	$periods = array("second", "minute", "hour", "day", "week", "month", "years", "decade");
	$lengths = array("60","60","24","7","4.35","12","10");
	for($j = 0; $difference >= $lengths[$j]; $j++)
		$difference /= $lengths[$j];
		$difference = round($difference);
	if($difference != 1) $periods[$j].= "s";
		$text = "$difference $periods[$j]";
   return $text;
}

$havg = humantime($average);
$hperiod = humantime($seconds);

if((strstr($curdatabase, "-traffic.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for traffic stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$interval.png \\
		--start -$seconds -e -$average \\
		--vertical-label \"bits/sec\" \\
		--title \"`hostname` - $prettydb - $hperiod - $havg average\" \\
		--height 200 --width 620 -x \"$scale\" \\
		DEF:$curif-in_bytes=$rrddbpath$curdatabase:in:AVERAGE \\
		DEF:$curif-out_bytes=$rrddbpath$curdatabase:out:AVERAGE \\
		\"CDEF:$curif-in_bits=$curif-in_bytes,8,*\" \\
		\"CDEF:$curif-out_bits=$curif-out_bytes,8,*\" \\
		\"CDEF:$curif-bits_io=$curif-in_bits,$curif-out_bits,+\" \\
		\"CDEF:$curif-out_bits_neg=$curif-out_bits,$multiplier,*\" \\
		\"CDEF:$curif-bytes_in=$curif-in_bytes,0,$downstream,LIMIT,UN,0,$curif-in_bytes,IF,$average,*\" \\
		\"CDEF:$curif-bytes_out=$curif-out_bytes,0,$upstream,LIMIT,UN,0,$curif-out_bytes,IF,$average,*\" \\
		\"CDEF:$curif-bytes=$curif-bytes_in,$curif-bytes_out,+\" \\
		\"CDEF:$curif-bytes_in_t=$curif-in_bytes,0,$downstream,LIMIT,UN,0,$curif-in_bytes,IF,$seconds,*\" \\
		\"CDEF:$curif-bytes_out_t=$curif-out_bytes,0,$upstream,LIMIT,UN,0,$curif-out_bytes,IF,$seconds,*\" \\
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
elseif((strstr($curdatabase, "-packets.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for packets stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$interval.png \\
		--start -$seconds -e -$average \\
		--vertical-label \"packets/sec\" \\
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
elseif((strstr($curdatabase, "-states.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for states stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$interval.png \\
		--start -$seconds -e -$average \\
		--vertical-label \"states, ip\" \\
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
elseif((strstr($curdatabase, "-queues.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for queue stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$interval.png \\
		--start -$seconds -e -$average \\
		--vertical-label \"bits/sec\" \\
		--title \"`hostname` - $prettydb - $hperiod - $havg average\" \\
		--height 200 --width 620 -x \"$scale\" \\";
		if (!is_array($config['shaper']['queue'])) {
			$config['shaper']['queue'] = array();
		}
		$a_queues = &$config['shaper']['queue'];
		$i = 0;
		$t = 0;
		foreach ($a_queues as $queue) {
			$name = $queue['name'];
			if((stristr($name, "$upif")) || (stristr($name, "up"))) {
				$color = "$colorqueuesup[$t]";
				if($t > 0) { $stack = ":STACK"; }
				$graphcmd .= "DEF:$name=$rrddbpath$curdatabase:$name:AVERAGE \\
					\"CDEF:$name-bytes_out=$name,0,$upstream,LIMIT,UN,0,$name,IF\" \\
					\"CDEF:$name-bits_out=$name-bytes_out,8,*\" \\
					\"CDEF:$name-bits_out_neg=$name-bits_out,$multiplier,*\" \\
					$AREA:$name-bits_out_neg#${color}:$name$stack \\";
					$t++;
					if($t > 7) { $t = 0; }
			}
		}
		$graphcmd .= "COMMENT:\"\\n\" \\";
		$stack = "";
		foreach ($a_queues as $queue) {
			$name = $queue['name'];
			if((stristr($name, "$downif")) || (stristr($name, "down"))) {
				$color = "$colorqueuesdown[$i]";
				if($i > 0) { $stack = ":STACK"; }
				$graphcmd .= "DEF:$name=$rrddbpath$curdatabase:$name:AVERAGE \\
					\"CDEF:$name-bytes_in=$name,0,$downstream,LIMIT,UN,0,$name,IF\" \\
					\"CDEF:$name-bits_in=$name-bytes_in,8,*\" \\
					$AREA:$name-bits_in#${color}:$name$stack \\";
					$i++;
					if($i > 7) { $i = 0; }
			}
		}
		$graphcmd .= "COMMENT:\"\\n\" \\";
		$graphcmd .= "COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t`date +\"%b %d %H\:%M\:%S %Y\"`\"";
	}
elseif((strstr($curdatabase, "-queuesdrop.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for queuedrop stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$interval.png \\
		--start -$seconds -e -$average \\
		--vertical-label \"drops / sec\" \\
		--title \"`hostname` - $prettydb - $hperiod - $havg average\" \\
		--height 200 --width 620 -x \"$scale\" \\";
		if (!is_array($config['shaper']['queue'])) {
			$config['shaper']['queue'] = array();
		}
		$a_queues = &$config['shaper']['queue'];
		$i = 0;
		$t = 0;
		foreach ($a_queues as $queue) {
			$name = $queue['name'];
			if((stristr($name, "$upif")) || (stristr($name, "up"))) {
				$color = "$colorqueuesdropup[$t]";
				if($t > 0) { $stack = ":STACK"; }
				$graphcmd .= "DEF:$name=$rrddbpath$curdatabase:$name:AVERAGE \\
					\"CDEF:$name-bytes_out=$name,0,$upstream,LIMIT,UN,0,$name,IF\" \\
					\"CDEF:$name-bits_out=$name-bytes_out,8,*\" \\
					\"CDEF:$name-bits_out_neg=$name-bits_out,$multiplier,*\" \\
					$AREA:$name-bits_out_neg#${color}:$name \\";
					$t++;
					if($t > 7) { $t = 0; }
			}
		}
		$graphcmd .= "COMMENT:\"\\n\" \\";
		$stack = "";
		foreach ($a_queues as $queue) {
			$name = $queue['name'];
			if((stristr($name, "$downif")) || (stristr($name, "down"))) {
				$color = "$colorqueuesdropdown[$i]";
				if($i > 0) { $stack = ":STACK"; }
				$graphcmd .= "DEF:$name=$rrddbpath$curdatabase:$name:AVERAGE \\
					\"CDEF:$name-bytes_in=$name,0,$downstream,LIMIT,UN,0,$name,IF\" \\
					\"CDEF:$name-bits_in=$name-bytes_in,8,*\" \\
					LINE1:$name-bits_in#${color}:$name \\";
					$i++;
					if($i > 7) { $i = 0; }
			}
		}
		$graphcmd .= "COMMENT:\"\\n\" \\";
		$graphcmd .= "COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t`date +\"%b %d %H\:%M\:%S %Y\"`\"";
	}
elseif((strstr($curdatabase, "-quality.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* make a link quality graphcmd, we only have WAN for now, others too follow */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$interval.png \\
		--start -$seconds -e -$average \\
		--title \"`hostname` - $prettydb - $hperiod - $havg average\" \\
		--vertical-label \"ms / %\" \\
		--height 200 --width 620 \\
		-x \"$scale\" --lower-limit 0 \\
		DEF:roundtrip=$rrddbpath$curdatabase:roundtrip:AVERAGE \\
		DEF:loss=$rrddbpath$curdatabase:loss:AVERAGE \\
		\"CDEF:roundavg=roundtrip,PREV(roundtrip),+,2,/\" \\
		\"CDEF:loss10=loss,$multiplier,*\" \\
		\"CDEF:r0=roundtrip,20,MIN\" \\
		\"CDEF:r1=roundtrip,60,MIN\" \\
		\"CDEF:r2=roundtrip,180,MIN\" \\
		\"CDEF:r3=roundtrip,420,MIN\" \\
		COMMENT:\"\t\t\t\t\t\tRoundtrip\t\t\tPacket loss\\n\" \\
		AREA:roundtrip#$colorqualityrtt[0]:\"> 420      ms\" \\
		GPRINT:roundtrip:MIN:\"    Min\\: %7.2lf ms\" \\
		GPRINT:loss:MIN:\"Min\\: %3.1lf %%\\n\" \\
    		AREA:r3#$colorqualityrtt[1]:\"180-420    ms\" \\
		GPRINT:roundtrip:AVERAGE:\"    Avg\\:  %7.2lf ms\" \\
		GPRINT:loss:AVERAGE:\"Avg\\: %3.1lf %%\\n\" \\
		AREA:r2#$colorqualityrtt[2]:\"60-180     ms\" \\
		GPRINT:roundtrip:MAX:\"    Max\\: %7.2lf ms\" \\
		GPRINT:loss:MAX:\"Max\\: %3.1lf %%\\n\" \\
		AREA:r1#$colorqualityrtt[3]:\"20-60      ms\\n\" \\
		AREA:r0#$colorqualityrtt[4]:\"< 20       ms\" \\
		GPRINT:roundtrip:LAST:\"    Last\\: %7.2lf ms\" \\
		GPRINT:loss:LAST:\"Last\: %3.1lf %%\\n\" \\
		AREA:loss10#$colorqualityloss:\"Packet loss\\n\" \\
		LINE1:roundtrip#$colorqualityrtt[5]:\"roundtrip average\\n\" \\
		COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t`date +\"%b %d %H\:%M\:%S %Y\"`\"";
	}
elseif((strstr($curdatabase, "spamd.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* graph a spamd statistics graph */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$interval.png \\
		--start -$seconds -e -$average \\
		--title \"`hostname` - $prettydb - $hperiod - $havg average\" \\
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
		$nodata = 1;
		log_error("Sorry we do not have data to graph for $curdatabase");
	} 

	/* check modification time to see if we need to generate image */
	if (file_exists("$rrdtmppath$curdatabase-$interval.png")) {
		if((time() - filemtime("$rrdtmppath$curdatabase-$interval.png")) >= 55 ) {
			exec("$graphcmd 2>&1", $graphcmdoutput, $graphcmdreturn);
			flush();
			usleep(500);
		}			
	} else {
		exec("$graphcmd 2>&1", $graphcmdoutput, $graphcmdreturn);
		flush();
		usleep(500);
	}

	if(($graphcmdreturn != 0) || ($nodata != 0)) {
		log_error("Failed to create graph with error code $graphcmdreturn, the error is: $graphcmdoutput[0]");
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
		$size= filesize($file);
		header("Content-Length: $size bytes");
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
			$size= filesize($file);
			header("Content-Length: $size bytes");
			readfile($file);
		}
	}

?>
