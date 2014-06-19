<?php
/* $Id$ */
/*
	status_rrd_graph_img.php
	Part of pfSense
	Copyright (C) 2009 Seth Mos <seth.mos@dds.nl>
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
	pfSense_BUILDER_BINARIES:	/bin/rm	/usr/local/bin/rrdtool
	pfSense_MODULE:	system
*/

require_once("globals.inc");
require_once("guiconfig.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("rrd.inc");

global $g;

$pgtitle = array(gettext("System"),gettext("RRD Graphs"),gettext("Image viewer"));

if ($_GET['database']) {
	$curdatabase = basename($_GET['database']);
	$curdatabase = str_replace(array("<", ">", ";", "&", "'", '"'), "", htmlspecialchars_decode($curdatabase, ENT_QUOTES | ENT_HTML401));
} else {
	$curdatabase = "wan-traffic.rrd";
}

if ($_GET['style']) {
	$curstyle = $_GET['style'];
} else {
	$curstyle = "inverse";
}

/* this is used for temp name */
if ($_GET['graph']) {
	$curgraph = str_replace(array("<", ">", ";", "&", "'", '"', '.', '/'), "", htmlspecialchars_decode($_GET['graph'], ENT_QUOTES | ENT_HTML401));
} else {
	$curgraph = "custom";
}

$now = time();

if (is_numeric($_GET['start'])) {
        $start = $_GET['start'];
} else {
        $start = $now - (8 * 3600);
}

if (is_numeric($_GET['end'])) {
        $end = $_GET['end'];
} else {
        $end = $now;
}

/* this should never happen */
if($end < $start) {
	log_error("start $start is smaller than end $end");
        $end = $now;
}

$seconds = $end - $start;

$scales = array();
$scales[14400] = "MINUTE:5:MINUTE:10:MINUTE:30:0:%H%:%M";
$scales[57600] = "MINUTE:30:HOUR:1:HOUR:1:0:%H";
$scales[172800] = "HOUR:1:HOUR:6:HOUR:2:0:%H";
$scales[691200] = "HOUR:2:HOUR:12:DAY:1:0:%D %d";
$scales[2764800] = "DAY:1:WEEK:1:WEEK:1:0:Week %W";
$scales[16070400] = "WEEK:1:MONTH:1:MONTH:1:0:%b";
$scales[42854400] = "MONTH:1:MONTH:1:MONTH:1:0:%b";

$archives = array();
$archives[1] = 1200;
$archives[5] = 720;
$archives[60] = 1860;
$archives[1440] = 2284;

$defOptions = array(
	'to' => 1,
	'parts' => 1,
	'precision' => 'minute',
	'distance' => FALSE,
	'separator' => ', '
);

/* always set the average to the highest value as a fallback */
$average = 1440 * 60;
foreach($archives as $rra => $value) {
        $archivestart = $now - ($rra * 60 * $value);
        if($archivestart <= $start) {
                $average = $rra * 60;
                break;
        }
}

foreach($scales as $scalelength => $value) {
        if($scalelength >= $seconds) {
                $scale = $value;
                break;
        }
}

// log_error("start $start, end $end, archivestart $archivestart, average $average, scale $scale, seconds $seconds");

/* Deduce a interface if possible and use the description */
$curif = explode("-", $curdatabase);
$curif = "$curif[0]";
$friendly = convert_friendly_interface_to_friendly_descr(strtolower($curif));
if($friendly == "") {
	$friendly = $curif;
}
$search = array("-", ".rrd", $curif);
$replace = array(" :: ", "", $friendly);
$prettydb = ucwords(str_replace($search, $replace, $curdatabase));


$rrddbpath = "/var/db/rrd/";
$rrdtmppath = "/tmp/";
$rrdtool = "/usr/bin/nice -n20 /usr/local/bin/rrdtool";
$uptime = "/usr/bin/uptime";
$sed = "/usr/bin/sed";

$havg = timeDiff($average, $defOptions);
$hperiod = timeDiff($seconds, $defOptions);
$data = true;

/* Don't leave it up to RRD Tool to select the RRA and resolution to use. */
/* Specify the RRA and resolution to use per the graph havg value. */
switch ($havg) {
	case "1 minute":	$step = 60;		break;
	case "5 minutes":	$step = 300;	break;
	case "1 hour":		$step = 3600;	break;
	case "1 day":		$step = 86400;	break;
	default:			$step = 0;		break;
}

$rrddbpath = "/var/db/rrd/";
chdir($rrddbpath);
$databases = glob("*.rrd");
rsort($databases);

/* compare bytes/sec counters, divide bps by 8 */
read_altq_config();
if ($altq_list_queues[$curif]) {
	$altq =& $altq_list_queues[$curif];
	switch ($altq->GetBwscale()) {
        case "Gb":
                $factor = 1024 * 1024 * 1024;
        break;
        case "Mb":
                $factor = 1024 * 1024;
        break;
        case "Kb":
                $factor = 1024;
        break;
        case "b":
        default:
                $factor = 1;
        break;
        }
	$upstream = (($altq->GetBandwidth()*$factor)/8);
	if ($upstream != 0)
		$downstream = $upstream; /* XXX: Ugly hack */
	else
		$downstream = $upstream = 12500000;
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

/* Set default colors explicitly, the theme can then override them below.
   This prevents missing colors in themes from crashing the graphs. */
/* Traffic Outbound		Out-P-4,  Out-B-4,  Out-P-6,  Out-B-6 */
$colortrafficup		= array('666666', 'CCCCCC', '2217AA', '625AE7');

/* Traffic Inbound		In-P-4,   In-B-4,    In-P-6,  In-B-6 */
$colortrafficdown	= array('990000', 'CC0000', 'FFC875', 'FF9900');

/* Packets Outbound		Out-P-4,  Out-B-4,  Out-P-6,  Out-B-6 */
$colorpacketsup		= array('666666', 'CCCCCC', '2217AA', '625AE7');

/* Packets Inbound		In-P-4,   In-B-4,    In-P-6,  In-B-6 */
$colorpacketsdown	= array('990000', 'CC0000', 'FFC875', 'FF9900');

/* 95th Percentile Lines	Out,      In */
$colortraffic95		= array('660000', 'FF0000');

/* State Table			pfrate,  pfstates, pfnat,  srcip,   dstip */
$colorstates		= array('00AA00','990000','0000FF','000000','DD9B00');

/* Processor Usage		user,    nice,    system,  int,     processes */
$colorprocessor		= array('00AA00','990000','0000FF','DD9B00','000000');

/* Memory Usage			active,  inact,   free,    cache,   wire */
$colormemory		= array('00AA00','990000','0000FF','666666','DD9B00');

/* MBUF Usage			current, cache,   total,   max */
$colormbuf		= array('0080FF','00E344','FF0000','000000');

/* Traffic Shaper Queues	q1,      q2,      q3,      q4,      q5,      q6,      q7,      q8,      q9 */
$colorqueuesup		= array('000000','7B0000','0080FF','00E344','FF0000','2217AA','FFC875','FF9900','CC0000');
$colorqueuesdown	= array('000000','7B7B7B','999999','BBBBBB','CCCCCC','D9D9D9','EEEEEE','FFFFFF','CCCCCC');

$colorqueuesdropup	= array('000000','7B0000','0080FF','00E344','FF0000','2217AA','FFC875','FF9900','CC0000');
$colorqueuesdropdown	= array('000000','7B7B7B','999999','BBBBBB','CCCCCC','D9D9D9','EEEEEE','FFFFFF','CCCCCC');

/* Quality Graph Delay	>420,    180-420, 60-180,  20-60,   <20,     Delay Avg */
$colorqualityrtt	= array('990000','a83c3c','b36666','bd9090','cccccc','000000');
/* Quality Graph Loss */
$colorqualityloss	= 'ee0000';

/* Wireless Graph		SNR,     Rate,    Channel*/
/* Cellular Graph		RSSI,     */
$colorwireless		= array('333333','a83c3c','999999');

/* SPAMD Times			min area, avg area, max area, Time line */
$colorspamdtime		= array('DDDDFF', 'AAAAFF', 'DDDDFF', '000066');
/* SPAMD Connections		max area,   min area,   min line,   max line,   avg line */
$colorspamdconn		= array('AA00BB', 'FFFFFF', '660088', 'FFFF88', '006600');

/* OpenVPN Users		Online Users */
$colorvpnusers		= array('990000');

/* NTPD stats			offset, clk jit,   sys jit,   wander */
$colorntpd		= array('0080FF','00E344','FF0000','000000');

/* Captive Portal Total Users	Total Users */
/* Captive Portal Concurrent	Concurrent Users */
$colorcaptiveportalusers = array('990000');

/* select theme colors if the inclusion file exists */
$rrdcolors = "{$g['www_path']}/themes/{$g['theme']}/rrdcolors.inc.php";
if(file_exists($rrdcolors)) {
	include($rrdcolors);
} else {
	log_error(sprintf(gettext("rrdcolors.inc.php for theme %s does not exist, using defaults!"),$g['theme']));
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

function timeDiff($time, $opt = array()) {
    // The default values
    $defOptions = array(
        'to' => 0,
        'parts' => 1,
        'precision' => 'second',
        'distance' => TRUE,
        'separator' => ', '
    );
    $opt = array_merge($defOptions, $opt);
    // Default to current time if no to point is given
    (!$opt['to']) && ($opt['to'] = time());
    // Init an empty string
    $str = '';
    // To or From computation
    $diff = ($opt['to'] > $time) ? $opt['to'] - $time : $time - $opt['to'];
    // An array of label => periods of seconds;
    $periods = array(
        'decade' => 315569260,
        'year' => 31539600,
        'month' => 2629744,
        'week' => 604800,
        'day' => 86400,
        'hour' => 3600,
        'minute' => 60,
        'second' => 1
    );
	// 31539600, 31556926, 31622400
    // Round to precision
    if ($opt['precision'] != 'second')
        $diff = round(($diff / $periods[$opt['precision']])) * $periods[$opt['precision']];
    // Report the value is 'less than 1 ' precision period away
    (0 == $diff) && ($str = 'less than 1 ' . $opt['precision']);
    // Loop over each period
    foreach ($periods as $label => $value) {
        // Stitch together the time difference string
        (($x = round($diff / $value)) && $opt['parts']--) && $str .= ($str ? $opt['separator'] : '') . ($x .' '. $label. ($x > 1 ? 's' : ''));
        // Stop processing if no more parts are going to be reported.
        if ($opt['parts'] == 0 || $label == $opt['precision']) break;
        // Get ready for the next pass
        $diff -= $x * $value;
    }
    $opt['distance'] && $str .= ($str && $opt['to'] >= $time) ? ' ago' : ' away';
    return $str;
}


if((strstr($curdatabase, "-traffic.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for traffic stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$curgraph.png ";
	$graphcmd .= "--start $start --end $end --step $step --vertical-label \"bits/sec\" ";
	$graphcmd .= "--color SHADEA#eeeeee --color SHADEB#eeeeee ";
	$graphcmd .= "--title \"" . php_uname('n') . " - {$prettydb} - {$hperiod} - {$havg} average\" ";
	$graphcmd .= "--height 200 --width 620 ";
	$graphcmd .= "DEF:$curif-in_bytes_pass=$rrddbpath$curdatabase:inpass:AVERAGE:step=$step ";
	$graphcmd .= "DEF:$curif-out_bytes_pass=$rrddbpath$curdatabase:outpass:AVERAGE:step=$step ";
	$graphcmd .= "DEF:$curif-in_bytes_block=$rrddbpath$curdatabase:inblock:AVERAGE:step=$step ";
	$graphcmd .= "DEF:$curif-out_bytes_block=$rrddbpath$curdatabase:outblock:AVERAGE:step=$step ";

	$graphcmd .= "DEF:$curif-in6_bytes_pass=$rrddbpath$curdatabase:inpass6:AVERAGE:step=$step ";
	$graphcmd .= "DEF:$curif-out6_bytes_pass=$rrddbpath$curdatabase:outpass6:AVERAGE:step=$step ";
	$graphcmd .= "DEF:$curif-in6_bytes_block=$rrddbpath$curdatabase:inblock6:AVERAGE:step=$step ";
	$graphcmd .= "DEF:$curif-out6_bytes_block=$rrddbpath$curdatabase:outblock6:AVERAGE:step=$step ";

	$graphcmd .= "CDEF:\"$curif-in_bits_pass=$curif-in_bytes_pass,8,*\" ";
	$graphcmd .= "CDEF:\"$curif-out_bits_pass=$curif-out_bytes_pass,8,*\" ";
	$graphcmd .= "CDEF:\"$curif-in_bits_block=$curif-in_bytes_block,8,*\" ";
	$graphcmd .= "CDEF:\"$curif-out_bits_block=$curif-out_bytes_block,8,*\" ";

	$graphcmd .= "CDEF:\"$curif-in6_bits_pass=$curif-in6_bytes_pass,8,*\" ";
	$graphcmd .= "CDEF:\"$curif-out6_bits_pass=$curif-out6_bytes_pass,8,*\" ";
	$graphcmd .= "CDEF:\"$curif-in6_bits_block=$curif-in6_bytes_block,8,*\" ";
	$graphcmd .= "CDEF:\"$curif-out6_bits_block=$curif-out6_bytes_block,8,*\" ";

	$graphcmd .= "CDEF:\"$curif-in_bytes=$curif-in_bytes_pass,$curif-in_bytes_block,+\" ";
	$graphcmd .= "CDEF:\"$curif-out_bytes=$curif-out_bytes_pass,$curif-out_bytes_block,+\" ";
	$graphcmd .= "CDEF:\"$curif-in_bits=$curif-in_bits_pass,$curif-in_bits_block,+\" ";
	$graphcmd .= "CDEF:\"$curif-out_bits=$curif-out_bits_pass,$curif-out_bits_block,+\" ";

	$graphcmd .= "CDEF:\"$curif-in6_bytes=$curif-in6_bytes_pass,$curif-in6_bytes_block,+\" ";
	$graphcmd .= "CDEF:\"$curif-out6_bytes=$curif-out6_bytes_pass,$curif-out6_bytes_block,+\" ";
	$graphcmd .= "CDEF:\"$curif-in6_bits=$curif-in6_bits_pass,$curif-in6_bits_block,+\" ";
	$graphcmd .= "CDEF:\"$curif-out6_bits=$curif-out6_bits_pass,$curif-out6_bits_block,+\" ";

	$graphcmd .= "CDEF:\"$curif-bits_io=$curif-in_bits,$curif-out_bits,+\" ";
	$graphcmd .= "CDEF:\"$curif-out_bits_block_neg=$curif-out_bits_block,$multiplier,*\" ";
	$graphcmd .= "CDEF:\"$curif-out_bits_pass_neg=$curif-out_bits_pass,$multiplier,*\" ";

	$graphcmd .= "CDEF:\"$curif-bits6_io=$curif-in6_bits,$curif-out6_bits,+\" ";
	$graphcmd .= "CDEF:\"$curif-out6_bits_block_neg=$curif-out6_bits_block,$multiplier,*\" ";
	$graphcmd .= "CDEF:\"$curif-out6_bits_pass_neg=$curif-out6_bits_pass,$multiplier,*\" ";

	$graphcmd .= "CDEF:\"$curif-bytes_in_pass=$curif-in_bytes_pass,0,$speedlimit,LIMIT,UN,0,$curif-in_bytes_pass,IF,$average,*\" ";
	$graphcmd .= "CDEF:\"$curif-bytes_out_pass=$curif-out_bytes_pass,0,$speedlimit,LIMIT,UN,0,$curif-out_bytes_pass,IF,$average,*\" ";
	$graphcmd .= "CDEF:\"$curif-bytes_in_block=$curif-in_bytes_block,0,$speedlimit,LIMIT,UN,0,$curif-in_bytes_block,IF,$average,*\" ";
	$graphcmd .= "CDEF:\"$curif-bytes_out_block=$curif-out_bytes_block,0,$speedlimit,LIMIT,UN,0,$curif-out_bytes_block,IF,$average,*\" ";

	$graphcmd .= "CDEF:\"$curif-bytes_in6_pass=$curif-in6_bytes_pass,0,$speedlimit,LIMIT,UN,0,$curif-in6_bytes_pass,IF,$average,*\" ";
	$graphcmd .= "CDEF:\"$curif-bytes_out6_pass=$curif-out6_bytes_pass,0,$speedlimit,LIMIT,UN,0,$curif-out6_bytes_pass,IF,$average,*\" ";
	$graphcmd .= "CDEF:\"$curif-bytes_in6_block=$curif-in6_bytes_block,0,$speedlimit,LIMIT,UN,0,$curif-in6_bytes_block,IF,$average,*\" ";
	$graphcmd .= "CDEF:\"$curif-bytes_out6_block=$curif-out6_bytes_block,0,$speedlimit,LIMIT,UN,0,$curif-out6_bytes_block,IF,$average,*\" ";

	$graphcmd .= "CDEF:\"$curif-bytes_pass=$curif-bytes_in_pass,$curif-bytes_out_pass,+\" ";
	$graphcmd .= "CDEF:\"$curif-bytes_block=$curif-bytes_in_block,$curif-bytes_out_block,+\" ";

	$graphcmd .= "CDEF:\"$curif-bytes_pass6=$curif-bytes_in6_pass,$curif-bytes_out6_pass,+\" ";
	$graphcmd .= "CDEF:\"$curif-bytes_block6=$curif-bytes_in6_block,$curif-bytes_out6_block,+\" ";

	$graphcmd .= "CDEF:\"$curif-bytes_in_t_pass=$curif-in_bytes_pass,0,$speedlimit,LIMIT,UN,0,$curif-in_bytes_pass,IF,$seconds,*\" ";
	$graphcmd .= "CDEF:\"$curif-bytes_out_t_pass=$curif-out_bytes_pass,0,$speedlimit,LIMIT,UN,0,$curif-out_bytes_pass,IF,$seconds,*\" ";
	$graphcmd .= "CDEF:\"$curif-bytes_in_t_block=$curif-in_bytes_block,0,$speedlimit,LIMIT,UN,0,$curif-in_bytes_block,IF,$seconds,*\" ";
	$graphcmd .= "CDEF:\"$curif-bytes_out_t_block=$curif-out_bytes_block,0,$speedlimit,LIMIT,UN,0,$curif-out_bytes_block,IF,$seconds,*\" ";

	$graphcmd .= "CDEF:\"$curif-bytes_in6_t_pass=$curif-in6_bytes_pass,0,$speedlimit,LIMIT,UN,0,$curif-in6_bytes_pass,IF,$seconds,*\" ";
	$graphcmd .= "CDEF:\"$curif-bytes_out6_t_pass=$curif-out6_bytes_pass,0,$speedlimit,LIMIT,UN,0,$curif-out6_bytes_pass,IF,$seconds,*\" ";
	$graphcmd .= "CDEF:\"$curif-bytes_in6_t_block=$curif-in6_bytes_block,0,$speedlimit,LIMIT,UN,0,$curif-in6_bytes_block,IF,$seconds,*\" ";
	$graphcmd .= "CDEF:\"$curif-bytes_out6_t_block=$curif-out6_bytes_block,0,$speedlimit,LIMIT,UN,0,$curif-out6_bytes_block,IF,$seconds,*\" ";

	$graphcmd .= "CDEF:\"$curif-bytes_t_pass=$curif-bytes_in_t_pass,$curif-bytes_out_t_pass,+\" ";
	$graphcmd .= "CDEF:\"$curif-bytes_t_block=$curif-bytes_in_t_block,$curif-bytes_out_t_block,+\" ";
	$graphcmd .= "CDEF:\"$curif-bytes_t=$curif-bytes_t_pass,$curif-bytes_t_block,+\" ";

	$graphcmd .= "CDEF:\"$curif-bytes_t_pass6=$curif-bytes_in6_t_pass,$curif-bytes_out6_t_pass,+\" ";
	$graphcmd .= "CDEF:\"$curif-bytes_t_block6=$curif-bytes_in6_t_block,$curif-bytes_out6_t_block,+\" ";
	$graphcmd .= "CDEF:\"$curif-bytes_t6=$curif-bytes_t_pass6,$curif-bytes_t_block6,+\" ";
	$graphcmd .= "VDEF:\"$curif-in_bits_95=$curif-in_bits,95,PERCENT\" ";
	$graphcmd .= "CDEF:\"$curif-out_bits_mul=$curif-out_bits,$multiplier,*\" ";
	$perc = $multiplier > 0 ? "95" : "5";
	$graphcmd .= "VDEF:\"$curif-out_bits_95=$curif-out_bits_mul,{$perc},PERCENT\" ";

	$graphcmd .= "AREA:\"$curif-in_bits_block#{$colortrafficdown[1]}:$curif-in-block\" ";
	$graphcmd .= "AREA:\"$curif-in_bits_pass#{$colortrafficdown[0]}:$curif-in-pass:STACK\" ";
	$graphcmd .= "AREA:\"$curif-in6_bits_block#{$colortrafficdown[3]}:$curif-in6-block:STACK\" ";
	$graphcmd .= "AREA:\"$curif-in6_bits_pass#{$colortrafficdown[2]}:$curif-in6-pass:STACK\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";

	$graphcmd .= "{$AREA}:\"$curif-out_bits_block_neg#{$colortrafficup[1]}:$curif-out-block\" ";
	$graphcmd .= "{$AREA}:\"$curif-out_bits_pass_neg#{$colortrafficup[0]}:$curif-out-pass:STACK\" ";
	$graphcmd .= "{$AREA}:\"$curif-out6_bits_block_neg#{$colortrafficup[3]}:$curif-out6-block:STACK\" ";
	$graphcmd .= "{$AREA}:\"$curif-out6_bits_pass_neg#{$colortrafficup[2]}:$curif-out6-pass:STACK\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "HRULE:\"$curif-in_bits_95#{$colortraffic95[1]}:$curif-in (95%)\" ";
	$graphcmd .= "HRULE:\"$curif-out_bits_95#{$colortraffic95[0]}:$curif-out (95%)\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t\t\t maximum\t    average\t\t current\t    period\t   95th percentile\\n\" ";
	$graphcmd .= "COMMENT:\"IPv4 in-pass\t\" ";
	$graphcmd .= "GPRINT:\"$curif-in_bits_pass:MAX:%7.2lf %sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-in_bits_pass:AVERAGE:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-in_bits_pass:LAST:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-bytes_in_t_pass:AVERAGE:%7.2lf %sB i\" ";
	$graphcmd .= "GPRINT:\"$curif-in_bits_95:%7.2lf %sb/s\" ";

	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"IPv4 out-pass\t\" ";
	$graphcmd .= "GPRINT:\"$curif-out_bits_pass:MAX:%7.2lf %sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-out_bits_pass:AVERAGE:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-out_bits_pass:LAST:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-bytes_out_t_pass:AVERAGE:%7.2lf %sB o\" ";
	$graphcmd .= "GPRINT:\"$curif-out_bits_95:%7.2lf %sb/s\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"IPv4 in-block\t\" ";
	$graphcmd .= "GPRINT:\"$curif-in_bits_block:MAX:%7.2lf %sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-in_bits_block:AVERAGE:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-in_bits_block:LAST:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-bytes_in_t_block:AVERAGE:%7.2lf %sB i\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"IPv4 out-block\t\" ";
	$graphcmd .= "GPRINT:\"$curif-out_bits_block:MAX:%7.2lf %sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-out_bits_block:AVERAGE:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-out_bits_block:LAST:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-bytes_out_t_block:AVERAGE:%7.2lf %sB o\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"IPv6 in-pass\t\" ";
	$graphcmd .= "GPRINT:\"$curif-in6_bits_pass:MAX:%7.2lf %sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-in6_bits_pass:AVERAGE:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-in6_bits_pass:LAST:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-bytes_in6_t_pass:AVERAGE:%7.2lf %sB i\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"IPv6 out-pass\t\" ";
	$graphcmd .= "GPRINT:\"$curif-out6_bits_pass:MAX:%7.2lf %sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-out6_bits_pass:AVERAGE:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-out6_bits_pass:LAST:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-bytes_out6_t_pass:AVERAGE:%7.2lf %sB o\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"IPv6 in-block\t\" ";
	$graphcmd .= "GPRINT:\"$curif-in6_bits_block:MAX:%7.2lf %sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-in6_bits_block:AVERAGE:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-in6_bits_block:LAST:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-bytes_in6_t_block:AVERAGE:%7.2lf %sB i\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"IPv6 out-block\t\" ";
	$graphcmd .= "GPRINT:\"$curif-out6_bits_block:MAX:%7.2lf %sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-out6_bits_block:AVERAGE:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-out6_bits_block:LAST:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"$curif-bytes_out6_t_block:AVERAGE:%7.2lf %sB o\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t" . strftime('%b %d %H\:%M\:%S %Y') . "\" ";
}
elseif(strstr($curdatabase, "-throughput.rrd")) {
	/* define graphcmd for throughput stats */
	/* this gathers all interface statistics, the database does not actually exist */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$curgraph.png ";
	$graphcmd .= "--start $start --end $end --step $step ";
	$graphcmd .= "--vertical-label \"bits/sec\" ";
	$graphcmd .= "--color SHADEA#eeeeee --color SHADEB#eeeeee ";
	$graphcmd .= "--title \"" . php_uname('n') . " - {$prettydb} - {$hperiod} - {$havg} average\" ";
	$graphcmd .= "--height 200 --width 620 ";

	$iflist = get_configured_interface_list();
	$g = 0;
	$operand = "";
	$comma = "";
	$graphtputbip = "";
	$graphtputbop = "";
	$graphtputbtp = "";
	$graphtputbib = "";
	$graphtputbob = "";
	$graphtputbtb = "";
	$graphtputbyip = "";
	$graphtputbyop = "";
	$graphtputbytp = "";
	$graphtputbyib = "";
	$graphtputbyob = "";
	$graphtputbytb = "";
	foreach($iflist as $ifname) {
		/* collect all interface stats */
		$graphcmd .= "DEF:\"{$ifname}-in_bytes_pass={$rrddbpath}{$ifname}-traffic.rrd:inpass:AVERAGE:step=$step\" ";
		$graphcmd .= "DEF:\"{$ifname}-out_bytes_pass={$rrddbpath}{$ifname}-traffic.rrd:outpass:AVERAGE:step=$step\" ";
		$graphcmd .= "DEF:\"{$ifname}-in_bytes_block={$rrddbpath}{$ifname}-traffic.rrd:inblock:AVERAGE:step=$step\" ";
		$graphcmd .= "DEF:\"{$ifname}-out_bytes_block={$rrddbpath}{$ifname}-traffic.rrd:outblock:AVERAGE:step=$step\" ";

		$graphcmd .= "CDEF:\"{$ifname}-in_bytes={$ifname}-in_bytes_pass,{$ifname}-in_bytes_block,+\" ";
		$graphcmd .= "CDEF:\"{$ifname}-out_bytes={$ifname}-out_bytes_pass,{$ifname}-out_bytes_block,+\" ";

		$graphcmd .= "CDEF:\"{$ifname}-in_bits_pass={$ifname}-in_bytes_pass,8,*\" ";
		$graphcmd .= "CDEF:\"{$ifname}-out_bits_pass={$ifname}-out_bytes_pass,8,*\" ";
		$graphcmd .= "CDEF:\"{$ifname}-bits_io_pass={$ifname}-in_bits_pass,{$ifname}-out_bits_pass,+\" ";

		$graphcmd .= "CDEF:\"{$ifname}-in_bits_block={$ifname}-in_bytes_block,8,*\" ";
		$graphcmd .= "CDEF:\"{$ifname}-out_bits_block={$ifname}-out_bytes_block,8,*\" ";
		$graphcmd .= "CDEF:\"{$ifname}-bits_io_block={$ifname}-in_bits_block,{$ifname}-out_bits_block,+\" ";

		$graphcmd .= "CDEF:\"{$ifname}-bytes_in_pass={$ifname}-in_bytes_pass,0,$speedlimit,LIMIT,UN,0,{$ifname}-in_bytes_pass,IF,$average,*\" ";
		$graphcmd .= "CDEF:\"{$ifname}-bytes_out_pass={$ifname}-out_bytes_pass,0,$speedlimit,LIMIT,UN,0,{$ifname}-out_bytes_pass,IF,$average,*\" ";
		$graphcmd .= "CDEF:\"{$ifname}-bytes_in_block={$ifname}-in_bytes_block,0,$speedlimit,LIMIT,UN,0,{$ifname}-in_bytes_block,IF,$average,*\" ";
		$graphcmd .= "CDEF:\"{$ifname}-bytes_out_block={$ifname}-out_bytes_block,0,$speedlimit,LIMIT,UN,0,{$ifname}-out_bytes_block,IF,$average,*\" ";
		$graphcmd .= "CDEF:\"{$ifname}-bytes_pass={$ifname}-bytes_in_pass,{$ifname}-bytes_out_pass,+\" ";
		$graphcmd .= "CDEF:\"{$ifname}-bytes_block={$ifname}-bytes_in_pass,{$ifname}-bytes_out_block,+\" ";

		$graphcmd .= "CDEF:\"{$ifname}-bytes_in_t_pass={$ifname}-in_bytes,0,$speedlimit,LIMIT,UN,0,{$ifname}-in_bytes_pass,IF,$seconds,*\" ";
		$graphcmd .= "CDEF:\"{$ifname}-bytes_in_t_block={$ifname}-in_bytes,0,$speedlimit,LIMIT,UN,0,{$ifname}-in_bytes_block,IF,$seconds,*\" ";
		$graphcmd .= "CDEF:\"{$ifname}-bytes_out_t_pass={$ifname}-out_bytes,0,$speedlimit,LIMIT,UN,0,{$ifname}-out_bytes_pass,IF,$seconds,*\" ";
		$graphcmd .= "CDEF:\"{$ifname}-bytes_out_t_block={$ifname}-out_bytes,0,$speedlimit,LIMIT,UN,0,{$ifname}-out_bytes_block,IF,$seconds,*\" ";
		$graphcmd .= "CDEF:\"{$ifname}-bytes_t_pass={$ifname}-bytes_in_t_pass,{$ifname}-bytes_out_t_pass,+\" ";
		$graphcmd .= "CDEF:\"{$ifname}-bytes_t_block={$ifname}-bytes_in_t_block,{$ifname}-bytes_out_t_block,+\" ";
		if ($g > 0) {
			$operand .= ",+";
			$comma = ",";
		}
		$graphtputbip .= "{$comma}{$ifname}-in_bits_pass";
		$graphtputbop .= "{$comma}{$ifname}-out_bits_pass";
		$graphtputbtp .= "{$comma}{$ifname}-bits_io_pass";
		$graphtputbib .= "{$comma}{$ifname}-in_bits_block";
		$graphtputbob .= "{$comma}{$ifname}-out_bits_block";
		$graphtputbtb .= "{$comma}{$ifname}-bits_io_block";
		$graphtputbyip .= "{$comma}{$ifname}-bytes_in_t_pass";
		$graphtputbyop .= "{$comma}{$ifname}-bytes_out_t_pass";
		$graphtputbyib .= "{$comma}{$ifname}-bytes_in_t_block";
		$graphtputbyob .= "{$comma}{$ifname}-bytes_out_t_block";
		$graphtputbytp .= "{$comma}{$ifname}-bytes_t_pass";
		$graphtputbytb .= "{$comma}{$ifname}-bytes_t_block";
		$g++;
	}
	$graphcmd .= "CDEF:\"tput-in_bits_pass={$graphtputbip}{$operand}\" ";
	$graphcmd .= "CDEF:\"tput-out_bits_pass={$graphtputbop}{$operand}\" "; 
	$graphcmd .= "CDEF:\"tput-bits_io_pass={$graphtputbtp}{$operand}\" ";

	$graphcmd .= "CDEF:\"tput-in_bits_block={$graphtputbib}{$operand}\" ";
	$graphcmd .= "CDEF:\"tput-out_bits_block={$graphtputbob}{$operand}\" "; 
	$graphcmd .= "CDEF:\"tput-bits_io_block={$graphtputbtb}{$operand}\" ";

	$graphcmd .= "CDEF:\"tput-out_bits_pass_neg=tput-out_bits_pass,$multiplier,*\" ";
	$graphcmd .= "CDEF:\"tput-out_bits_block_neg=tput-out_bits_block,$multiplier,*\" ";

	$graphcmd .= "CDEF:\"tput-bytes_in_t_pass={$graphtputbyip}{$operand}\" ";
	$graphcmd .= "CDEF:\"tput-bytes_out_t_pass={$graphtputbyop}{$operand}\" ";
	$graphcmd .= "CDEF:\"tput-bytes_t_pass={$graphtputbytp}{$operand}\" ";

	$graphcmd .= "CDEF:\"tput-bytes_in_t_block={$graphtputbyib}{$operand}\" ";
	$graphcmd .= "CDEF:\"tput-bytes_out_t_block={$graphtputbyob}{$operand}\" ";
	$graphcmd .= "CDEF:\"tput-bytes_t_block={$graphtputbytb}{$operand}\" ";

	$graphcmd .= "AREA:\"tput-in_bits_block#{$colortrafficdown[0]}:in-block \" ";
	$graphcmd .= "AREA:\"tput-in_bits_pass#{$colortrafficdown[1]}:in-pass \" ";

	$graphcmd .= "{$AREA}:\"tput-out_bits_block_neg#{$colortrafficup[1]}:out-block \" ";
	$graphcmd .= "{$AREA}:\"tput-out_bits_pass_neg#{$colortrafficup[0]}:out-pass \" ";

	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t  maximum       average       current        period\\n\" ";
	$graphcmd .= "COMMENT:\"in-pass\t\" ";
	$graphcmd .= "GPRINT:\"tput-in_bits_pass:MAX:%7.2lf %sb/s\" ";
	$graphcmd .= "GPRINT:\"tput-in_bits_pass:AVERAGE:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"tput-in_bits_pass:LAST:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"tput-bytes_in_t_pass:AVERAGE:%7.2lf %sB i\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"out-pass\t\" ";
	$graphcmd .= "GPRINT:\"tput-out_bits_pass:MAX:%7.2lf %sb/s\" ";
	$graphcmd .= "GPRINT:\"tput-out_bits_pass:AVERAGE:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"tput-out_bits_pass:LAST:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"tput-bytes_out_t_pass:AVERAGE:%7.2lf %sB o\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"in-block\t\" ";
	$graphcmd .= "GPRINT:\"tput-in_bits_block:MAX:%7.2lf %sb/s\" ";
	$graphcmd .= "GPRINT:\"tput-in_bits_block:AVERAGE:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"tput-in_bits_block:LAST:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"tput-bytes_in_t_block:AVERAGE:%7.2lf %sB i\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"out-block\t\" ";
	$graphcmd .= "GPRINT:\"tput-out_bits_block:MAX:%7.2lf %sb/s\" ";
	$graphcmd .= "GPRINT:\"tput-out_bits_block:AVERAGE:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"tput-out_bits_block:LAST:%7.2lf %Sb/s\" ";
	$graphcmd .= "GPRINT:\"tput-bytes_out_t_block:AVERAGE:%7.2lf %sB o\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t" . strftime('%b %d %H\:%M\:%S %Y') . "\" ";
}
elseif((strstr($curdatabase, "-packets.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for packets stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$curgraph.png ";
	$graphcmd .= "--start $start --end $end --step $step ";
	$graphcmd .= "--vertical-label \"packets/sec\" ";
	$graphcmd .= "--color SHADEA#eeeeee --color SHADEB#eeeeee ";
	$graphcmd .= "--title \"" . php_uname('n') . " - {$prettydb} - {$hperiod} - {$havg} average\" ";
	$graphcmd .= "--height 200 --width 620 ";
	$graphcmd .= "DEF:\"$curif-in_pps_pass=$rrddbpath$curdatabase:inpass:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"$curif-out_pps_pass=$rrddbpath$curdatabase:outpass:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"$curif-in_pps_block=$rrddbpath$curdatabase:inblock:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"$curif-out_pps_block=$rrddbpath$curdatabase:outblock:AVERAGE:step=$step\" ";

	$graphcmd .= "DEF:\"$curif-in6_pps_pass=$rrddbpath$curdatabase:inpass6:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"$curif-out6_pps_pass=$rrddbpath$curdatabase:outpass6:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"$curif-in6_pps_block=$rrddbpath$curdatabase:inblock6:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"$curif-out6_pps_block=$rrddbpath$curdatabase:outblock6:AVERAGE:step=$step\" ";

	$graphcmd .= "CDEF:\"$curif-in_pps=$curif-in_pps_pass,$curif-in_pps_block,+\" ";
	$graphcmd .= "CDEF:\"$curif-out_pps=$curif-out_pps_pass,$curif-out_pps_block,+\" ";
	$graphcmd .= "CDEF:\"$curif-out_pps_pass_neg=$curif-out_pps_pass,$multiplier,*\" ";
	$graphcmd .= "CDEF:\"$curif-out_pps_block_neg=$curif-out_pps_block,$multiplier,*\" ";

	$graphcmd .= "CDEF:\"$curif-in6_pps=$curif-in6_pps_pass,$curif-in6_pps_block,+\" ";
	$graphcmd .= "CDEF:\"$curif-out6_pps=$curif-out6_pps_pass,$curif-out6_pps_block,+\" ";
	$graphcmd .= "CDEF:\"$curif-out6_pps_pass_neg=$curif-out6_pps_pass,$multiplier,*\" ";
	$graphcmd .= "CDEF:\"$curif-out6_pps_block_neg=$curif-out6_pps_block,$multiplier,*\" ";

	$graphcmd .= "CDEF:\"$curif-pps_in_pass=$curif-in_pps_pass,0,12500000,LIMIT,UN,0,$curif-in_pps_pass,IF,$average,*\" ";
	$graphcmd .= "CDEF:\"$curif-pps_out_pass=$curif-out_pps_pass,0,12500000,LIMIT,UN,0,$curif-out_pps_pass,IF,$average,*\" ";
	$graphcmd .= "CDEF:\"$curif-pps_in_block=$curif-in_pps_block,0,12500000,LIMIT,UN,0,$curif-in_pps_block,IF,$average,*\" ";
	$graphcmd .= "CDEF:\"$curif-pps_out_block=$curif-out_pps_block,0,12500000,LIMIT,UN,0,$curif-out_pps_block,IF,$average,*\" ";

	$graphcmd .= "CDEF:\"$curif-pps_in6_pass=$curif-in6_pps_pass,0,12500000,LIMIT,UN,0,$curif-in6_pps_pass,IF,$average,*\" ";
	$graphcmd .= "CDEF:\"$curif-pps_out6_pass=$curif-out6_pps_pass,0,12500000,LIMIT,UN,0,$curif-out6_pps_pass,IF,$average,*\" ";
	$graphcmd .= "CDEF:\"$curif-pps_in6_block=$curif-in6_pps_block,0,12500000,LIMIT,UN,0,$curif-in6_pps_block,IF,$average,*\" ";
	$graphcmd .= "CDEF:\"$curif-pps_out6_block=$curif-out6_pps_block,0,12500000,LIMIT,UN,0,$curif-out6_pps_block,IF,$average,*\" ";

	$graphcmd .= "CDEF:\"$curif-pps_io=$curif-in_pps,$curif-out_pps,+\" ";
	$graphcmd .= "CDEF:\"$curif-pps_pass=$curif-pps_in_pass,$curif-pps_out_pass,+\" ";
	$graphcmd .= "CDEF:\"$curif-pps_block=$curif-pps_in_block,$curif-pps_out_block,+\" ";

	$graphcmd .= "CDEF:\"$curif-pps_io6=$curif-in6_pps,$curif-out6_pps,+\" ";
	$graphcmd .= "CDEF:\"$curif-pps_pass6=$curif-pps_in6_pass,$curif-pps_out6_pass,+\" ";
	$graphcmd .= "CDEF:\"$curif-pps_block6=$curif-pps_in6_block,$curif-pps_out6_block,+\" ";

	$graphcmd .= "CDEF:\"$curif-pps_in_t_pass=$curif-in_pps_pass,0,12500000,LIMIT,UN,0,$curif-in_pps_pass,IF,$seconds,*\" ";
	$graphcmd .= "CDEF:\"$curif-pps_out_t_pass=$curif-out_pps_pass,0,12500000,LIMIT,UN,0,$curif-out_pps_pass,IF,$seconds,*\" ";
	$graphcmd .= "CDEF:\"$curif-pps_in_t_block=$curif-in_pps_block,0,12500000,LIMIT,UN,0,$curif-in_pps_block,IF,$seconds,*\" ";
	$graphcmd .= "CDEF:\"$curif-pps_out_t_block=$curif-out_pps_block,0,12500000,LIMIT,UN,0,$curif-out_pps_block,IF,$seconds,*\" ";

	$graphcmd .= "CDEF:\"$curif-pps_in6_t_pass=$curif-in6_pps_pass,0,12500000,LIMIT,UN,0,$curif-in6_pps_pass,IF,$seconds,*\" ";
	$graphcmd .= "CDEF:\"$curif-pps_out6_t_pass=$curif-out6_pps_pass,0,12500000,LIMIT,UN,0,$curif-out6_pps_pass,IF,$seconds,*\" ";
	$graphcmd .= "CDEF:\"$curif-pps_in6_t_block=$curif-in6_pps_block,0,12500000,LIMIT,UN,0,$curif-in6_pps_block,IF,$seconds,*\" ";
	$graphcmd .= "CDEF:\"$curif-pps_out6_t_block=$curif-out6_pps_block,0,12500000,LIMIT,UN,0,$curif-out6_pps_block,IF,$seconds,*\" ";

	$graphcmd .= "CDEF:\"$curif-pps_t_pass=$curif-pps_in_t_pass,$curif-pps_out_t_pass,+\" ";
	$graphcmd .= "CDEF:\"$curif-pps_t_block=$curif-pps_in_t_block,$curif-pps_out_t_block,+\" ";

	$graphcmd .= "CDEF:\"$curif-pps_t_pass6=$curif-pps_in6_t_pass,$curif-pps_out6_t_pass,+\" ";
	$graphcmd .= "CDEF:\"$curif-pps_t_block6=$curif-pps_in6_t_block,$curif-pps_out6_t_block,+\" ";

	$graphcmd .= "AREA:\"$curif-in_pps_block#{$colorpacketsdown[1]}:$curif-in-block\" ";
	$graphcmd .= "AREA:\"$curif-in_pps_pass#{$colorpacketsdown[0]}:$curif-in-pass:STACK\" ";
	$graphcmd .= "AREA:\"$curif-in6_pps_block#{$colorpacketsdown[3]}:$curif-in6-block:STACK\" ";
	$graphcmd .= "AREA:\"$curif-in6_pps_pass#{$colorpacketsdown[2]}:$curif-in6-pass:STACK\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "$AREA:\"$curif-out_pps_block_neg#{$colorpacketsup[1]}:$curif-out-block\" ";
	$graphcmd .= "$AREA:\"$curif-out_pps_pass_neg#{$colorpacketsup[0]}:$curif-out-pass:STACK\" ";
	$graphcmd .= "$AREA:\"$curif-out6_pps_block_neg#{$colorpacketsup[3]}:$curif-out6-block:STACK\" ";
	$graphcmd .= "$AREA:\"$curif-out6_pps_pass_neg#{$colorpacketsup[2]}:$curif-out6-pass:STACK\" ";

	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t  maximum\t\t average\t     current\t    period\\n\" ";
	$graphcmd .= "COMMENT:\"in-pass\t\" ";
	$graphcmd .= "GPRINT:\"$curif-in_pps_pass:MAX:%7.2lf %s pps\" ";
	$graphcmd .= "GPRINT:\"$curif-in_pps_pass:AVERAGE:%7.2lf %S pps\" ";
	$graphcmd .= "GPRINT:\"$curif-in_pps_pass:LAST:%7.2lf %S pps\" ";
	$graphcmd .= "GPRINT:\"$curif-pps_in_t_pass:AVERAGE:%7.2lf %s pkts\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"out-pass\t\" ";
	$graphcmd .= "GPRINT:\"$curif-out_pps_pass:MAX:%7.2lf %s pps\" ";
	$graphcmd .= "GPRINT:\"$curif-out_pps_pass:AVERAGE:%7.2lf %S pps\" ";
	$graphcmd .= "GPRINT:\"$curif-out_pps_pass:LAST:%7.2lf %S pps\" ";
	$graphcmd .= "GPRINT:\"$curif-pps_out_t_pass:AVERAGE:%7.2lf %s pkts\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"in-block\t\" ";
	$graphcmd .= "GPRINT:\"$curif-in_pps_block:MAX:%7.2lf %s pps\" ";
	$graphcmd .= "GPRINT:\"$curif-in_pps_block:AVERAGE:%7.2lf %S pps\" ";
	$graphcmd .= "GPRINT:\"$curif-in_pps_block:LAST:%7.2lf %S pps\" ";
	$graphcmd .= "GPRINT:\"$curif-pps_in_t_block:AVERAGE:%7.2lf %s pkts\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"out-block\t\" ";
	$graphcmd .= "GPRINT:\"$curif-out_pps_block:MAX:%7.2lf %s pps\" ";
	$graphcmd .= "GPRINT:\"$curif-out_pps_block:AVERAGE:%7.2lf %S pps\" ";
	$graphcmd .= "GPRINT:\"$curif-out_pps_block:LAST:%7.2lf %S pps\" ";
	$graphcmd .= "GPRINT:\"$curif-pps_out_t_block:AVERAGE:%7.2lf %s pkts\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";

	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"in-pass6\t\" ";
	$graphcmd .= "GPRINT:\"$curif-in6_pps_pass:MAX:%7.2lf %s pps\" ";
	$graphcmd .= "GPRINT:\"$curif-in6_pps_pass:AVERAGE:%7.2lf %S pps\" ";
	$graphcmd .= "GPRINT:\"$curif-in6_pps_pass:LAST:%7.2lf %S pps\" ";
	$graphcmd .= "GPRINT:\"$curif-pps_in6_t_pass:AVERAGE:%7.2lf %s pkts\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"out-pass6\t\" ";
	$graphcmd .= "GPRINT:\"$curif-out6_pps_pass:MAX:%7.2lf %s pps\" ";
	$graphcmd .= "GPRINT:\"$curif-out6_pps_pass:AVERAGE:%7.2lf %S pps\" ";
	$graphcmd .= "GPRINT:\"$curif-out6_pps_pass:LAST:%7.2lf %S pps\" ";
	$graphcmd .= "GPRINT:\"$curif-pps_out6_t_pass:AVERAGE:%7.2lf %s pkts\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"in-block6\t\" ";
	$graphcmd .= "GPRINT:\"$curif-in6_pps_block:MAX:%7.2lf %s pps\" ";
	$graphcmd .= "GPRINT:\"$curif-in6_pps_block:AVERAGE:%7.2lf %S pps\" ";
	$graphcmd .= "GPRINT:\"$curif-in6_pps_block:LAST:%7.2lf %S pps\" ";
	$graphcmd .= "GPRINT:\"$curif-pps_in6_t_block:AVERAGE:%7.2lf %s pkts\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"out-pass6\t\" ";
	$graphcmd .= "GPRINT:\"$curif-out6_pps_block:MAX:%7.2lf %s pps\" ";
	$graphcmd .= "GPRINT:\"$curif-out6_pps_block:AVERAGE:%7.2lf %S pps\" ";
	$graphcmd .= "GPRINT:\"$curif-out6_pps_block:LAST:%7.2lf %S pps\" ";
	$graphcmd .= "GPRINT:\"$curif-pps_out6_t_block:AVERAGE:%7.2lf %s pkts\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t" . strftime('%b %d %H\:%M\:%S %Y') . "\" ";
}
elseif((strstr($curdatabase, "-wireless.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for packets stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$curgraph.png ";
	$graphcmd .= "--start $start --end $end --step $step ";
	$graphcmd .= "--vertical-label \"snr/channel/rate\" ";
	$graphcmd .= "--color SHADEA#eeeeee --color SHADEB#eeeeee ";
	$graphcmd .= "--title \"" . php_uname('n') . " - {$prettydb} - {$hperiod} - {$havg} average\" ";
	$graphcmd .= "--height 200 --width 620 ";
	$graphcmd .= "DEF:\"$curif-snr=$rrddbpath$curdatabase:snr:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"$curif-rate=$rrddbpath$curdatabase:rate:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"$curif-channel=$rrddbpath$curdatabase:channel:AVERAGE:step=$step\" ";
	$graphcmd .= "LINE2:\"$curif-snr#{$colorwireless[0]}:$curif-snr\" ";
	$graphcmd .= "LINE2:\"$curif-rate#{$colorwireless[1]}:$curif-rate\" ";
	$graphcmd .= "LINE2:\"$curif-channel#{$colorwireless[2]}:$curif-channel\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t   maximum\t\t average\t     current\\n\" ";
	$graphcmd .= "COMMENT:\"SNR\t\t\" ";
	$graphcmd .= "GPRINT:\"$curif-snr:MAX:%7.2lf dBi  \" ";
	$graphcmd .= "GPRINT:\"$curif-snr:AVERAGE:%7.2lf dBi  \" ";
	$graphcmd .= "GPRINT:\"$curif-snr:LAST:%7.2lf dBi\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"RATE\t\t\" ";
	$graphcmd .= "GPRINT:\"$curif-rate:MAX:%7.2lf Mb   \" ";
	$graphcmd .= "GPRINT:\"$curif-rate:AVERAGE:%7.2lf Mb   \" ";
	$graphcmd .= "GPRINT:\"$curif-rate:LAST:%7.2lf Mb\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"Channel\t\" ";
	$graphcmd .= "GPRINT:\"$curif-channel:MAX:%7.2lf      \" ";
	$graphcmd .= "GPRINT:\"$curif-channel:AVERAGE:%7.2lf      \" ";
	$graphcmd .= "GPRINT:\"$curif-channel:LAST:%7.2lf\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t" . strftime('%b %d %H\:%M\:%S %Y') . "\" ";
}
elseif((strstr($curdatabase, "-vpnusers.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for vpn users stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$curgraph.png ";
	$graphcmd .= "--start $start --end $end --step $step ";
	$graphcmd .= "--vertical-label \"users\" ";
	$graphcmd .= "--color SHADEA#eeeeee --color SHADEB#eeeeee ";
	$graphcmd .= "--title \"" . php_uname('n') . " - {$prettydb} - {$hperiod} - {$havg} average\" ";
	$graphcmd .= "--height 200 --width 620 ";
	$graphcmd .= "DEF:\"$curif-users=$rrddbpath$curdatabase:users:AVERAGE:step=$step\" ";
	$graphcmd .= "LINE2:\"$curif-users#{$colorvpnusers[0]}:$curif-users\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t\t    maximum\t\t average\t     current\\n\" ";
	$graphcmd .= "COMMENT:\"Users Online\t\" ";
	$graphcmd .= "GPRINT:\"$curif-users:MAX:%7.2lf     \" ";
	$graphcmd .= "GPRINT:\"$curif-users:AVERAGE:%7.2lf      \" ";
	$graphcmd .= "GPRINT:\"$curif-users:LAST:%7.2lf \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t" . strftime('%b %d %H\:%M\:%S %Y') . "\" ";
}
elseif((strstr($curdatabase, "-states.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for states stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$curgraph.png ";
	$graphcmd .= "--start -$seconds -e -$average --step $step ";
	$graphcmd .= "--vertical-label \"states, ip\" ";
	$graphcmd .= "--color SHADEA#eeeeee --color SHADEB#eeeeee ";
	$graphcmd .= "--title \"" . php_uname('n') . " - {$prettydb} - {$hperiod} - {$havg} average\" ";
	$graphcmd .= "--height 200 --width 620 ";
	$graphcmd .= "DEF:\"$curif-pfrate=$rrddbpath$curdatabase:pfrate:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"$curif-pfstates=$rrddbpath$curdatabase:pfstates:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"$curif-pfnat=$rrddbpath$curdatabase:pfnat:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"$curif-srcip=$rrddbpath$curdatabase:srcip:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"$curif-dstip=$rrddbpath$curdatabase:dstip:AVERAGE:step=$step\" ";
	$graphcmd .= "CDEF:\"$curif-pfrate_t=$curif-pfrate,0,1000000,LIMIT,UN,0,$curif-pfrate,IF,$seconds,*\" ";
	$graphcmd .= "LINE1:\"$curif-pfrate#{$colorstates[0]}:$curif-pfrate\" ";
	$graphcmd .= "LINE1:\"$curif-pfstates#{$colorstates[1]}:$curif-pfstates\" ";
	$graphcmd .= "LINE1:\"$curif-pfnat#{$colorstates[2]}:$curif-pfnat\" ";
	$graphcmd .= "LINE1:\"$curif-srcip#{$colorstates[3]}:$curif-srcip\" ";
	$graphcmd .= "LINE1:\"$curif-dstip#{$colorstates[4]}:$curif-dstip\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t      minimum        average        maximum        current         period\\n\" ";
	$graphcmd .= "COMMENT:\"state changes\" ";
	$graphcmd .= "GPRINT:\"$curif-pfrate:MIN:%7.2lf %s cps\" ";
	$graphcmd .= "GPRINT:\"$curif-pfrate:AVERAGE:%7.2lf %s cps\" ";
	$graphcmd .= "GPRINT:\"$curif-pfrate:MAX:%7.2lf %s cps\" ";
	$graphcmd .= "GPRINT:\"$curif-pfrate:LAST:%7.2lf %S cps\" ";
	$graphcmd .= "GPRINT:\"$curif-pfrate_t:AVERAGE:%7.2lf %s chg\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"filter states\" ";
	$graphcmd .= "GPRINT:\"$curif-pfstates:MIN:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"$curif-pfstates:AVERAGE:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"$curif-pfstates:MAX:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"$curif-pfstates:LAST:%7.2lf %s    \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"nat states   \" ";
	$graphcmd .= "GPRINT:\"$curif-pfnat:MIN:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"$curif-pfnat:AVERAGE:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"$curif-pfnat:MAX:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"$curif-pfnat:LAST:%7.2lf %s    \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"Source addr. \" ";
	$graphcmd .= "GPRINT:\"$curif-srcip:MIN:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"$curif-srcip:AVERAGE:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"$curif-srcip:MAX:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"$curif-srcip:LAST:%7.2lf %s    \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"Dest. addr.  \" ";
	$graphcmd .= "GPRINT:\"$curif-dstip:MIN:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"$curif-dstip:AVERAGE:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"$curif-dstip:MAX:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"$curif-dstip:LAST:%7.2lf %s    \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t" . strftime('%b %d %H\:%M\:%S %Y') . "\" ";
}
elseif((strstr($curdatabase, "-processor.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for processor stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$curgraph.png ";
	$graphcmd .= "--start $start --end $end --step $step ";
	$graphcmd .= "--vertical-label \"utilization, number\" ";
	$graphcmd .= "--color SHADEA#eeeeee --color SHADEB#eeeeee ";
	$graphcmd .= "--title \"" . php_uname('n') . " - {$prettydb} - {$hperiod} - {$havg} average\" ";
	$graphcmd .= "--height 200 --width 620 ";
	$graphcmd .= "DEF:\"user=$rrddbpath$curdatabase:user:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"nice=$rrddbpath$curdatabase:nice:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"system=$rrddbpath$curdatabase:system:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"interrupt=$rrddbpath$curdatabase:interrupt:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"processes=$rrddbpath$curdatabase:processes:AVERAGE:step=$step\" ";
	$graphcmd .= "AREA:\"user#{$colorprocessor[0]}:user\" ";
	$graphcmd .= "AREA:\"nice#{$colorprocessor[1]}:nice:STACK\" ";
	$graphcmd .= "AREA:\"system#{$colorprocessor[2]}:system:STACK\" ";
	$graphcmd .= "AREA:\"interrupt#{$colorprocessor[3]}:interrupt:STACK\" ";
	$graphcmd .= "LINE2:\"processes#{$colorprocessor[4]}:processes\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t      minimum        average        maximum        current\\n\" ";
	$graphcmd .= "COMMENT:\"User util.   \" ";
	$graphcmd .= "GPRINT:\"user:MIN:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"user:AVERAGE:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"user:MAX:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"user:LAST:%7.2lf %S    \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"Nice util.   \" ";
	$graphcmd .= "GPRINT:\"nice:MIN:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"nice:AVERAGE:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"nice:MAX:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"nice:LAST:%7.2lf %s    \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"System util. \" ";
	$graphcmd .= "GPRINT:\"system:MIN:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"system:AVERAGE:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"system:MAX:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"system:LAST:%7.2lf %s    \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"Interrupt    \" ";
	$graphcmd .= "GPRINT:\"interrupt:MIN:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"interrupt:AVERAGE:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"interrupt:MAX:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"interrupt:LAST:%7.2lf %s    \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"Processes    \" ";
	$graphcmd .= "GPRINT:\"processes:MIN:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"processes:AVERAGE:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"processes:MAX:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"processes:LAST:%7.2lf %s    \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t" . strftime('%b %d %H\:%M\:%S %Y') . "\" ";
}
elseif((strstr($curdatabase, "-memory.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for memory usage stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$curgraph.png ";
	$graphcmd .= "--start $start --end $end --step $step ";
	$graphcmd .= "--vertical-label \"utilization, percent\" ";
	$graphcmd .= "--color SHADEA#eeeeee --color SHADEB#eeeeee ";
	$graphcmd .= "--title \"" . php_uname('n') . " - {$prettydb} - {$hperiod} - {$havg} average\" ";
	$graphcmd .= "--height 200 --width 620 ";
	$graphcmd .= "DEF:\"active=$rrddbpath$curdatabase:active:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"inactive=$rrddbpath$curdatabase:inactive:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"free=$rrddbpath$curdatabase:free:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"cache=$rrddbpath$curdatabase:cache:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"wire=$rrddbpath$curdatabase:wire:AVERAGE:step=$step\" ";
	$graphcmd .= "LINE2:\"active#{$colormemory[0]}:active\" ";
	$graphcmd .= "LINE2:\"inactive#{$colormemory[1]}:inactive\" ";
	$graphcmd .= "LINE2:\"free#{$colormemory[2]}:free\" ";
	$graphcmd .= "LINE2:\"cache#{$colormemory[3]}:cache\" ";
	$graphcmd .= "LINE2:\"wire#{$colormemory[4]}:wire\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t      minimum        average        maximum        current\\n\" ";
	$graphcmd .= "COMMENT:\"Active.      \" ";
	$graphcmd .= "GPRINT:\"active:MIN:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"active:AVERAGE:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"active:MAX:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"active:LAST:%7.2lf %S    \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"Inactive.    \" ";
	$graphcmd .= "GPRINT:\"inactive:MIN:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"inactive:AVERAGE:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"inactive:MAX:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"inactive:LAST:%7.2lf %S    \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"Free.        \" ";
	$graphcmd .= "GPRINT:\"free:MIN:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"free:AVERAGE:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"free:MAX:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"free:LAST:%7.2lf %S    \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"Cached.      \" ";
	$graphcmd .= "GPRINT:\"cache:MIN:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"cache:AVERAGE:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"cache:MAX:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"cache:LAST:%7.2lf %S    \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"Wired.       \" ";
	$graphcmd .= "GPRINT:\"wire:MIN:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"wire:AVERAGE:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"wire:MAX:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"wire:LAST:%7.2lf %S    \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t" . strftime('%b %d %H\:%M\:%S %Y') . "\" ";
}
elseif((strstr($curdatabase, "-mbuf.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for mbuf usage stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$curgraph.png ";
	$graphcmd .= "--start $start --end $end --step $step ";
	$graphcmd .= "--vertical-label \"utilization, percent\" ";
	$graphcmd .= "--color SHADEA#eeeeee --color SHADEB#eeeeee ";
	$graphcmd .= "--title \"" . php_uname('n') . " - {$prettydb} clusters - {$hperiod} - {$havg} average\" ";
	$graphcmd .= "--height 200 --width 620 ";
	$graphcmd .= "DEF:\"current=$rrddbpath$curdatabase:current:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"cache=$rrddbpath$curdatabase:cache:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"total=$rrddbpath$curdatabase:total:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"max=$rrddbpath$curdatabase:max:AVERAGE:step=$step\" ";
	$graphcmd .= "LINE2:\"current#{$colormbuf[0]}:current\" ";
	$graphcmd .= "LINE2:\"cache#{$colormbuf[1]}:cache\" ";
	$graphcmd .= "LINE2:\"total#{$colormbuf[2]}:total\" ";
	$graphcmd .= "LINE2:\"max#{$colormbuf[3]}:max\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t      minimum        average        maximum        current\\n\" ";
	$graphcmd .= "COMMENT:\"Current.      \" ";
	$graphcmd .= "GPRINT:\"current:MIN:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"current:AVERAGE:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"current:MAX:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"current:LAST:%7.2lf %S    \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"Cache.        \" ";
	$graphcmd .= "GPRINT:\"cache:MIN:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"cache:AVERAGE:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"cache:MAX:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"cache:LAST:%7.2lf %S    \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"Total.        \" ";
	$graphcmd .= "GPRINT:\"total:MIN:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"total:AVERAGE:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"total:MAX:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"total:LAST:%7.2lf %S    \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"Max.          \" ";
	$graphcmd .= "GPRINT:\"max:MIN:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"max:AVERAGE:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"max:MAX:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"max:LAST:%7.2lf %S    \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t" . strftime('%b %d %H\:%M\:%S %Y') . "\" ";
}
elseif((strstr($curdatabase, "-queues.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for queue stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$curgraph.png ";
	$graphcmd .= "--start $start --end $end --step $step ";
	$graphcmd .= "--vertical-label \"bits/sec\" ";
	$graphcmd .= "--color SHADEA#eeeeee --color SHADEB#eeeeee ";
	$graphcmd .= "--title \"" . php_uname('n') . " - {$prettydb} - {$hperiod} - {$havg} average\" ";
	$graphcmd .= "--height 200 --width 620 ";
	if ($altq) {
		$a_queues =& $altq->get_queue_list();
		$t = 0; 
	} else {
		$a_queues = array();
		$i = 0;
		$t = 0;
	}
	foreach ($a_queues as $name => $q) {
		$color = "$colorqueuesup[$t]";
		if($t > 0) { $stack = ":STACK"; }
		$graphcmd .= "DEF:\"$name=$rrddbpath$curdatabase:$name:AVERAGE:step=$step\" ";
		$graphcmd .= "CDEF:\"$name-bytes_out=$name,0,$speedlimit,LIMIT,UN,0,$name,IF\" ";
		$graphcmd .= "CDEF:\"$name-bits_out=$name-bytes_out,8,*\" ";
		$graphcmd .= "$AREA:\"$name-bits_out#${color}:$name$stack\" ";
		$t++;
		if($t > 7) { $t = 0; }
	}
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t" . strftime('%b %d %H\:%M\:%S %Y') . "\" ";
}
elseif((strstr($curdatabase, "-queuedrops.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for queuedrop stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$curgraph.png ";
	$graphcmd .= "--start $start --end $end --step $step ";
	$graphcmd .= "--vertical-label \"drops / sec\" ";
	$graphcmd .= "--color SHADEA#eeeeee --color SHADEB#eeeeee ";
	$graphcmd .= "--title \"" . php_uname('n') . " - {$prettydb} - {$hperiod} - {$havg} average\" ";
	$graphcmd .= "--height 200 --width 620 ";
	if ($altq) {
		$a_queues =& $altq->get_queue_list();
		$t = 0;
	} else {
        	$a_queues = array();
		$i = 0;
		$t = 0;
	}
	foreach ($a_queues as $name => $q) {
		$color = "$colorqueuesdropup[$t]";
		if($t > 0) { $stack = ":STACK"; }
		$graphcmd .= "DEF:\"$name=$rrddbpath$curdatabase:$name:AVERAGE:step=$step\" ";
		$graphcmd .= "CDEF:\"$name-bytes_out=$name,0,$speedlimit,LIMIT,UN,0,$name,IF\" ";
		$graphcmd .= "CDEF:\"$name-bits_out=$name-bytes_out,8,*\" ";
		$graphcmd .= "CDEF:\"$name-bits_out_neg=$name-bits_out,$multiplier,*\" ";
		$graphcmd .= "$AREA:\"$name-bits_out_neg#${color}:$name$stack\" ";
		$t++;
		if($t > 7) { $t = 0; }
	}
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t" . strftime('%b %d %H\:%M\:%S %Y') . "\" ";
}
elseif((strstr($curdatabase, "-quality.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* make a link quality graphcmd, we only have WAN for now, others too follow */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$curgraph.png \\
		--start $start --end $end --step $step  \\
		--title \"" . php_uname('n') . " - {$prettydb} - {$hperiod} - {$havg} average\" \\
		--color SHADEA#eeeeee --color SHADEB#eeeeee \\
		--vertical-label \"ms / %\" \\
		--height 200 --width 620 \\
		--lower-limit 0 \\
		DEF:delayraw=$rrddbpath$curdatabase:delay:AVERAGE:step=$step \\
		DEF:loss=$rrddbpath$curdatabase:loss:AVERAGE:step=$step \\
		\"CDEF:delay=delayraw,1000,*\" \\
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
		COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t" . strftime('%b %d %H\:%M\:%S %Y') . "\"";
}
elseif((strstr($curdatabase, "spamd.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* graph a spamd statistics graph */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$curgraph.png \\
		--start $start --end $end --step $step \\
		--title \"" . php_uname('n') . " - {$prettydb} - {$hperiod} - {$havg} average\" \\
		--color SHADEA#eeeeee --color SHADEB#eeeeee \\
		--vertical-label=\"Conn / Time, sec.\" \\
		--height 200 --width 620 --no-gridfit \\
		--lower-limit 0 \\
		DEF:consmin=$rrddbpath$curdatabase:conn:MIN:step=$step \\
		DEF:consavg=$rrddbpath$curdatabase:conn:AVERAGE:step=$step \\
		DEF:consmax=$rrddbpath$curdatabase:conn:MAX:step=$step \\
		DEF:timemin=$rrddbpath$curdatabase:time:MIN:step=$step \\
		DEF:timeavg=$rrddbpath$curdatabase:time:AVERAGE:step=$step \\
		DEF:timemax=$rrddbpath$curdatabase:time:MAX:step=$step \\
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
		COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t" . strftime('%b %d %H\:%M\:%S %Y') . "\" ";
}
elseif((strstr($curdatabase, "-cellular.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$curgraph.png ";
	$graphcmd .= "--start $start --end $end --step $step ";
	$graphcmd .= "--vertical-label \"signal\" ";
	$graphcmd .= "--color SHADEA#eeeeee --color SHADEB#eeeeee ";
	$graphcmd .= "--title \"" . php_uname('n') . " - {$prettydb} - {$hperiod} - {$havg} average\" ";
	$graphcmd .= "--height 200 --width 620 ";
	$graphcmd .= "DEF:\"$curif-rssi=$rrddbpath$curdatabase:rssi:AVERAGE:step=$step\" ";
	$graphcmd .= "LINE2:\"$curif-rssi#{$colorwireless[0]}:$curif-rssi\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t   maximum\t\t average\t     current\\n\" ";
	$graphcmd .= "COMMENT:\"RSSI\t\t\" ";
	$graphcmd .= "GPRINT:\"$curif-rssi:MAX:%7.2lf     \" ";
	$graphcmd .= "GPRINT:\"$curif-rssi:AVERAGE:%7.2lf     \" ";
	$graphcmd .= "GPRINT:\"$curif-rssi:LAST:%7.2lf \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t" . strftime('%b %d %H\:%M\:%S %Y') . "\" ";
}
elseif((strstr($curdatabase, "-loggedin.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for online Captive Portal users stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$curgraph.png ";
	$graphcmd .= "--start $start --end $end --step $step ";
	$graphcmd .= "--vertical-label \"Captive Portal Users\" ";
	$graphcmd .= "--color SHADEA#eeeeee --color SHADEB#eeeeee ";
	$graphcmd .= "--base=1000 ";
	$graphcmd .= "--lower-limit=0 ";
	$graphcmd .= "--slope-mode ";
	$graphcmd .= "--title \"" . php_uname('n') . " - {$prettydb} - {$hperiod} - {$havg} average\" ";
	$graphcmd .= "--height 200 --width 620 ";
	$graphcmd .= "DEF:\"$curif-loggedinusers=$rrddbpath$curdatabase:loggedinusers:AVERAGE:step=$step\" ";
	$graphcmd .= "CDEF:\"$curif-totalusers_t=PREV,UN,0,PREV,IF,$curif-loggedinusers,+\" ";
	$graphcmd .= "CDEF:\"$curif-totalusers_d=$curif-totalusers_t,FLOOR\" ";
	$graphcmd .= "AREA:\"$curif-totalusers_d#{$colorcaptiveportalusers[0]}:Total logged in users\" ";
	$graphcmd .= "GPRINT:\"$curif-totalusers_d:MAX:%8.0lf \\n\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t" . strftime('%b %d %H\:%M\:%S %Y') . "\" ";	
}
elseif((strstr($curdatabase, "-concurrent.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for online Captive Portal users stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$curgraph.png ";
	$graphcmd .= "--start $start --end $end --step $step ";
	$graphcmd .= "--vertical-label \"Captive Portal Users\" ";
	$graphcmd .= "--color SHADEA#eeeeee --color SHADEB#eeeeee ";
	$graphcmd .= "--title \"" . php_uname('n') . " - {$prettydb} - {$hperiod} - {$havg} average\" ";
	$graphcmd .= "--base=1000 ";
	$graphcmd .= "--lower-limit=0 ";
	$graphcmd .= "--slope-mode ";
	$graphcmd .= "--height 200 --width 620 ";
	$graphcmd .= "DEF:\"$curif-concurrentusers=$rrddbpath$curdatabase:concurrentusers:AVERAGE:step=$step\" ";
	$graphcmd .= "AREA:\"$curif-concurrentusers#{$colorcaptiveportalusers[0]}:Concurrent Users\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t\t    current\t\t average\t     maximum\\n\" ";
	$graphcmd .= "COMMENT:\"Users Online\t\" ";
	$graphcmd .= "GPRINT:\"$curif-concurrentusers:LAST:%8.0lf     \" ";
	$graphcmd .= "GPRINT:\"$curif-concurrentusers:AVERAGE:%8.0lf      \" ";
	$graphcmd .= "GPRINT:\"$curif-concurrentusers:MAX:%8.0lf \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t" . strftime('%b %d %H\:%M\:%S %Y') . "\" ";	
}
elseif((strstr($curdatabase, "ntpd.rrd")) && (file_exists("$rrddbpath$curdatabase"))) {
	/* define graphcmd for ntpd (was: mbuf) usage stats */
	$graphcmd = "$rrdtool graph $rrdtmppath$curdatabase-$curgraph.png ";
	$graphcmd .= "--start $start --end $end --step $step ";
	$graphcmd .= "--vertical-label \"time\" ";
	$graphcmd .= "--color SHADEA#eeeeee --color SHADEB#eeeeee ";
	$graphcmd .= "--title \"" . php_uname('n') . " - {$prettydb} - {$hperiod} - {$havg} average\" ";
	$graphcmd .= "--height 200 --width 620 ";
	$graphcmd .= "DEF:\"offset=$rrddbpath$curdatabase:offset:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"sjit=$rrddbpath$curdatabase:sjit:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"cjit=$rrddbpath$curdatabase:cjit:AVERAGE:step=$step\" ";
	$graphcmd .= "DEF:\"wander=$rrddbpath$curdatabase:wander:AVERAGE:step=$step\" ";
	$graphcmd .= "LINE2:\"offset#{$colorntpd[0]}:offset\" ";
	$graphcmd .= "LINE2:\"sjit#{$colorntpd[1]}:sjit\" ";
	$graphcmd .= "LINE2:\"cjit#{$colorntpd[2]}:cjit\" ";
	$graphcmd .= "LINE2:\"wander#{$colorntpd[3]}:wander\" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t        minimum        average        maximum        current\\n\" ";
	$graphcmd .= "COMMENT:\"Offset         \" ";
	$graphcmd .= "GPRINT:\"offset:MIN:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"offset:AVERAGE:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"offset:MAX:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"offset:LAST:%7.2lf %S    \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"System jitter  \" ";
	$graphcmd .= "GPRINT:\"sjit:MIN:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"sjit:AVERAGE:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"sjit:MAX:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"sjit:LAST:%7.2lf %S    \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"Clock jitter   \" ";
	$graphcmd .= "GPRINT:\"cjit:MIN:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"cjit:AVERAGE:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"cjit:MAX:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"cjit:LAST:%7.2lf %S    \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"Clk freq wander\" ";
	$graphcmd .= "GPRINT:\"wander:MIN:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"wander:AVERAGE:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"wander:MAX:%7.2lf %s    \" ";
	$graphcmd .= "GPRINT:\"wander:LAST:%7.2lf %S    \" ";
	$graphcmd .= "COMMENT:\"\\n\" ";
	$graphcmd .= "COMMENT:\"\t\t\t\t\t\t\t\t\t\t\t\t\t" . strftime('%b %d %H\:%M\:%S %Y') . "\" ";
}
else {
	$data = false;
	log_error(sprintf(gettext("Sorry we do not have data to graph for %s"),$curdatabase));
} 

/* check modification time to see if we need to generate image */
if (file_exists("$rrdtmppath$curdatabase-$curgraph.png")) {
	if((time() - filemtime("$rrdtmppath$curdatabase-$curgraph.png")) >= 15 ) {
		if($data)
			$_gb = exec("$graphcmd 2>&1", $graphcmdoutput, $graphcmdreturn);
			$graphcmdoutput = implode(" ", $graphcmdoutput) . $graphcmd;
			flush();
			usleep(500);
	}			
} else {
	if($data)
		$_gb = exec("$graphcmd 2>&1", $graphcmdoutput, $graphcmdreturn);
		$graphcmdoutput = implode(" ", $graphcmdoutput) . $graphcmd;
		flush();
		usleep(500);
}
if(($graphcmdreturn <> 0) || (! $data)) {
	log_error(sprintf(gettext('Failed to create graph with error code %1$s, the error is: %2$s'),$graphcmdreturn,$graphcmdoutput));
	if(strstr($curdatabase, "queues")) {
		log_error(sprintf(gettext("failed to create graph from %s%s, removing database"),$rrddbpath,$curdatabase));
		unlink_if_exists($rrddbpath . $curif . $queues);
		flush();
		usleep(500);
		enable_rrd_graphing();
	}
	if(strstr($curdatabase, "queuesdrop")) {
		log_error(sprintf(gettext("failed to create graph from %s%s, removing database"),$rrddbpath,$curdatabase));
		unlink_if_exists($rrddbpath . $curdatabase);
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
	$file = "$rrdtmppath$curdatabase-$curgraph.png";
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
