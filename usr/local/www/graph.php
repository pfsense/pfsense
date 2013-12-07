<?php
/*
	graph.php
	part of m0n0wall (http://m0n0.ch/wall)
	
	Copyright (C) 2004-2006 T. Lechat <dev@lechat.org>, Manuel Kasper <mk@neon1.net>
	and Jonathan Watt <jwatt@jwatt.org>.
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
	pfSense_MODULE:	graph
*/

##|+PRIV
##|*IDENT=page-diagnostics-interfacetraffic
##|*NAME=Diagnostics: Interface Traffic page
##|*DESCR=Allow access to the 'Diagnostics: Interface Traffic' page.
##|*MATCH=graph.php*
##|-PRIV

require("globals.inc");
require("guiconfig.inc");

header("Last-Modified: " . gmdate( "D, j M Y H:i:s" ) . " GMT" );
header("Expires: " . gmdate( "D, j M Y H:i:s", time() ) . " GMT" );
header("Cache-Control: no-store, no-cache, must-revalidate" ); // HTTP/1.1
header("Cache-Control: post-check=0, pre-check=0", FALSE );
header("Pragma: no-cache"); // HTTP/1.0
header("Content-type: image/svg+xml");

/********** HTTP GET Based Conf ***********/
$ifnum=@$_GET["ifnum"];  // BSD / SNMP interface name / number
$ifnum = get_real_interface($ifnum);
$ifname=@$_GET["ifname"]?$_GET["ifname"]:"Interface $ifnum";  //Interface name that will be showed on top right of graph

/********* Other conf *******/
if (isset($config["widgets"]["trafficgraphs"]["scale_type"]))
	$scale_type = $config["widgets"]["trafficgraphs"]["scale_type"];
else
	$scale_type = "up";

$nb_plot=120;                   //NB plot in graph
if ($_GET["timeint"])
	$time_interval = $_GET["timeint"];		//Refresh time Interval
else
	$time_interval = 3;

if ($_GET["initdelay"])
	$init_delay = $_GET["initdelay"];		//Initial Delay
else
	$init_delay = 3;

//SVG attributes
$attribs['axis']='fill="black" stroke="black"';
$attribs['in']='fill="#FF0000" font-family="Tahoma, Verdana, Arial, Helvetica, sans-serif" font-size="7"';
$attribs['out']='fill="#000000" font-family="Tahoma, Verdana, Arial, Helvetica, sans-serif" font-size="7"';
$attribs['graph_in']='fill="none" stroke="#FF0000" stroke-opacity="0.8"';
$attribs['graph_out']='fill="none" stroke="#000000" stroke-opacity="0.8"';
$attribs['legend']='fill="black" font-family="Tahoma, Verdana, Arial, Helvetica, sans-serif" font-size="4"';
$attribs['graphname']='fill="#FF0000" font-family="Tahoma, Verdana, Arial, Helvetica, sans-serif" font-size="8"';
$attribs['grid_txt']='fill="gray" font-family="Tahoma, Verdana, Arial, Helvetica, sans-serif" font-size="6"';
$attribs['grid']='stroke="gray" stroke-opacity="0.5"';
$attribs['switch_unit']='fill="#FF0000" font-family="Tahoma, Verdana, Arial, Helvetica, sans-serif" font-size="4" text-decoration="underline"';
$attribs['switch_scale']='fill="#FF0000" font-family="Tahoma, Verdana, Arial, Helvetica, sans-serif" font-size="4" text-decoration="underline"';
$attribs['error']='fill="blue" font-family="Arial" font-size="4"';
$attribs['collect_initial']='fill="gray" font-family="Tahoma, Verdana, Arial, Helvetica, sans-serif" font-size="4"';

//Error text if we cannot fetch data : depends on which method is used
$error_text = "Cannot get data about interface " . htmlspecialchars($ifnum);

$height=100;            //SVG internal height : do not modify
$width=200;             //SVG internal width : do not modify

$fetch_link = "ifstats.php?if=" . htmlspecialchars($ifnum);

/* check for custom theme colors */
if(file_exists("/usr/local/www/themes/{$g['theme']}/graph.php")) {
	$themetxt = file_get_contents("/usr/local/www/themes/{$g['theme']}/graph.php");
	eval($themetxt);
} 

/********* Graph DATA **************/
print('<?xml version="1.0" encoding="iso-8859-1"?>' . "\n");?>
<svg width="100%" height="100%" viewBox="0 0 <?=$width?> <?=$height?>" preserveAspectRatio="none" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" onload="init(evt)">
  <g id="graph">
    <rect id="bg" x1="0" y1="0" width="100%" height="100%" fill="white"/>
    <line id="axis_x" x1="0" y1="0" x2="0" y2="100%" <?=$attribs['axis']?>/>
    <line id="axis_y" x1="0" y1="100%" x2="100%" y2="100%" <?=$attribs['axis']?>/>
    <path id="graph_out" d="M0 <?=$height?> L 0 <?=$height?>" <?=$attribs['graph_out']?>/>
    <path id="graph_in"  d="M0 <?=$height?> L 0 <?=$height?>" <?=$attribs['graph_in']?>/>
    <path id="grid"  d="M0 <?=$height/4*1?> L <?=$width?> <?=$height/4*1?> M0 <?=$height/4*2?> L <?=$width?> <?=$height/4*2?> M0 <?=$height/4*3?> L <?=$width?> <?=$height/4*3?>" <?=$attribs['grid']?>/>
    <text id="grid_txt1" x="<?=$width?>" y="<?=$height/4*1?>" <?=$attribs['grid_txt']?> text-anchor="end"> </text>
    <text id="grid_txt2" x="<?=$width?>" y="<?=$height/4*2?>" <?=$attribs['grid_txt']?> text-anchor="end"> </text>
    <text id="grid_txt3" x="<?=$width?>" y="<?=$height/4*3?>" <?=$attribs['grid_txt']?> text-anchor="end"> </text>
    <text id="graph_in_lbl" x="5" y="8" <?=$attribs['in']?>><?=gettext("In"); ?></text>
    <text id="graph_out_lbl" x="5" y="16" <?=$attribs['out']?>><?=gettext("Out"); ?></text>
    <text id="graph_in_txt" x="20" y="8" <?=$attribs['in']?>> </text>
    <text id="graph_out_txt" x="20" y="16" <?=$attribs['out']?>> </text>
    <text id="ifname" x="<?=$width?>" y="8" <?=$attribs['graphname']?> text-anchor="end"><?=htmlspecialchars($ifname)?></text>
    <text id="switch_unit" x="<?=$width*0.55?>" y="5" <?=$attribs['switch_unit']?>><?=gettext("Switch to bytes/s"); ?></text>
    <text id="switch_scale" x="<?=$width*0.55?>" y="11" <?=$attribs['switch_scale']?>><?=gettext("AutoScale"); ?> (<?=$scale_type?>)</text>
    <text id="datetime" x="<?=$width*0.33?>" y="5" <?=$attribs['legend']?>> </text>
    <text id="graphlast" x="<?=$width*0.55?>" y="17" <?=$attribs['legend']?>><?=gettext("Graph shows last"); ?> <?=$time_interval*$nb_plot?> <?=gettext("seconds"); ?></text>
    <polygon id="axis_arrow_x" <?=$attribs['axis']?> points="<?=($width) . "," . ($height)?> <?=($width-2) . "," . ($height-2)?> <?=($width-2) . "," . $height?>"/>
    <text id="error" x="<?=$width*0.5?>" y="<?=$height*0.5?>"  visibility="hidden" <?=$attribs['error']?> text-anchor="middle"><?=$error_text?></text>
    <text id="collect_initial" x="<?=$width*0.5?>" y="<?=$height*0.5?>"  visibility="hidden" <?=$attribs['collect_initial']?> text-anchor="middle"><?=gettext("Collecting initial data, please wait"); ?>...</text>
  </g>
  <script type="text/ecmascript">
    <![CDATA[

/**
 * getURL is a proprietary Adobe function, but it's simplicity has made it very
 * popular. If getURL is undefined we spin our own by wrapping XMLHttpRequest.
 */
if (typeof getURL == 'undefined') {
  getURL = function(url, callback) {
    if (!url)
      throw '<?=gettext("No URL for getURL"); ?>';

    try {
      if (typeof callback.operationComplete == 'function')
        callback = callback.operationComplete;
    } catch (e) {}
    if (typeof callback != 'function')
      throw '<?=gettext("No callback function for getURL"); ?>';

    var http_request = null;
    if (typeof XMLHttpRequest != 'undefined') {
      http_request = new XMLHttpRequest();
    }
    else if (typeof ActiveXObject != 'undefined') {
      try {
        http_request = new ActiveXObject('Msxml2.XMLHTTP');
      } catch (e) {
        try {
          http_request = new ActiveXObject('Microsoft.XMLHTTP');
        } catch (e) {}
      }
    }
    if (!http_request)
      throw '<?=gettext("Both getURL and XMLHttpRequest are undefined"); ?>';

    http_request.onreadystatechange = function() {
      if (http_request.readyState == 4) {
        callback( { success : true,
                    content : http_request.responseText,
                    contentType : http_request.getResponseHeader("Content-Type") } );
      }
    }
    http_request.open('GET', url, true);
    http_request.send(null);
  }
}

var SVGDoc = null;
var last_ifin = 0;
var last_ifout = 0;
var last_ugmt = 0;
var max = 0;
var plot_in = new Array();
var plot_out = new Array();

var max_num_points = <?=$nb_plot?>;  // maximum number of plot data points
var step = <?=$width?> / max_num_points ;
var unit = 'bits';
var scale_type = '<?=$scale_type?>';

function init(evt) {
  SVGDoc = evt.target.ownerDocument;
  SVGDoc.getElementById("switch_unit").addEventListener("mousedown", switch_unit, false);
  SVGDoc.getElementById("switch_scale").addEventListener("mousedown", switch_scale, false);

  fetch_data();
}

function switch_unit(event)
{
  SVGDoc.getElementById('switch_unit').firstChild.data = '<?=gettext("Switch to"); ?> ' + unit + '/s';
  unit = (unit == 'bits') ? 'bytes' : 'bits';
}

function switch_scale(event)
{
  scale_type = (scale_type == 'up') ? '<?=gettext("follow"); ?>' : '<?=gettext("up"); ?>';
  SVGDoc.getElementById('switch_scale').firstChild.data = 'AutoScale (' + scale_type + ')';
}

function fetch_data() {
  getURL('<?=$fetch_link?>', plot_data);
}

function plot_data(obj) {
  // Show datetimelegend
  var now = new Date();
  var datetime = (now.getMonth()+1) + "/" + now.getDate() + "/" + now.getFullYear() + ' ' + 
    LZ(now.getHours()) + ":" + LZ(now.getMinutes()) + ":" + LZ(now.getSeconds());
  SVGDoc.getElementById('datetime').firstChild.data = datetime;

  if (!obj.success)
    return handle_error();  // getURL failed to get data

  var t = obj.content.split("|");
  var ugmt = parseFloat(t[0]);  // ugmt is an unixtimestamp style
  var ifin = parseInt(t[1], 10);    // number of bytes received by the interface
  var ifout = parseInt(t[2], 10);   // number of bytes sent by the interface
  var scale;

  if (!isNumber(ifin) || !isNumber(ifout))
    return handle_error();

  var diff_ugmt  = ugmt - last_ugmt;
  var diff_ifin  = ifin - last_ifin;
  var diff_ifout = ifout - last_ifout;

  if (diff_ugmt == 0)
    diff_ugmt = 1;  /* avoid division by zero */

  last_ugmt = ugmt;
  last_ifin = ifin;
  last_ifout = ifout;
  var graphTimerId = 0;
  switch (plot_in.length) {
  	case 0:
  		SVGDoc.getElementById("collect_initial").setAttributeNS(null, 'visibility', 'visible');
		plot_in[0] = diff_ifin / diff_ugmt;
		plot_out[0] = diff_ifout / diff_ugmt;
		setTimeout('fetch_data()',<?=1000*($time_interval + $init_delay)?>);
		return;
	case 1:
    	SVGDoc.getElementById("collect_initial").setAttributeNS(null, 'visibility', 'hidden');
    	break;
    case max_num_points:
		// shift plot to left if the maximum number of plot points has been reached
		var i = 0;
		while (i < max_num_points) {
		  plot_in[i] = plot_in[i+1];
		  plot_out[i] = plot_out[++i];
		}
		plot_in.length--;
		plot_out.length--;
  }

  plot_in[plot_in.length] = diff_ifin / diff_ugmt;
  plot_out[plot_out.length]= diff_ifout / diff_ugmt;
  var index_plot = plot_in.length - 1;

  SVGDoc.getElementById('graph_in_txt').firstChild.data = formatSpeed(plot_in[index_plot], unit);
  SVGDoc.getElementById('graph_out_txt').firstChild.data = formatSpeed(plot_out[index_plot], unit);

  /* determine peak for sensible scaling */
  if (scale_type == 'up') {
    if (plot_in[index_plot] > max)
      max = plot_in[index_plot];
    if (plot_out[index_plot] > max)
      max = plot_out[index_plot];
  }
  else if (scale_type == 'follow') {
    i = 0;
    max = 0;
    while (i < plot_in.length) {
      if (plot_in[i] > max)
        max = plot_in[i];
      if (plot_out[i] > max)
        max = plot_out[i];
      i++;
    }
  }

  var rmax;  // max, rounded up

  if (unit == 'bits') {
    /* round up max, such that
         100 kbps -> 200 kbps -> 400 kbps -> 800 kbps -> 1 Mbps -> 2 Mbps -> ... */
    rmax = 12500;
    i = 0;
    while (max > rmax) {
      i++;
      if (i && (i % 4 == 0))
        rmax *= 1.25;
      else
        rmax *= 2;
    }
  } else {
    /* round up max, such that
         10 KB/s -> 20 KB/s -> 40 KB/s -> 80 KB/s -> 100 KB/s -> 200 KB/s -> 400 KB/s -> 800 KB/s -> 1 MB/s ... */
    rmax = 10240;
    i = 0;
    while (max > rmax) {
      i++;
      if (i && (i % 4 == 0))
        rmax *= 1.25;
      else
        rmax *= 2;
      
      if (i == 8)
        rmax *= 1.024;
    }
  }

  scale = <?=$height?> / rmax;

  /* change labels accordingly */
  SVGDoc.getElementById('grid_txt1').firstChild.data = formatSpeed(3*rmax/4,unit);
  SVGDoc.getElementById('grid_txt2').firstChild.data = formatSpeed(2*rmax/4,unit);
  SVGDoc.getElementById('grid_txt3').firstChild.data = formatSpeed(rmax/4,unit);

  var path_in = "M 0 " + (<?=$height?> - (plot_in[0] * scale));
  var path_out = "M 0 " + (<?=$height?> - (plot_out[0] * scale));
  for (i = 1; i < plot_in.length; i++)
  {
    var x = step * i;
    var y_in = <?=$height?> - (plot_in[i] * scale);
    var y_out = <?=$height?> - (plot_out[i] * scale);
    path_in += " L" + x + " " + y_in;
    path_out += " L" + x + " " + y_out;
  }

  SVGDoc.getElementById('error').setAttributeNS(null, 'visibility', 'hidden');
  SVGDoc.getElementById('graph_in').setAttributeNS(null, 'd', path_in);
  SVGDoc.getElementById('graph_out').setAttributeNS(null, 'd', path_out);

  setTimeout('fetch_data()',<?=1000*$time_interval?>);
}

function handle_error() {
  SVGDoc.getElementById("error").setAttributeNS(null, 'visibility', 'visible');
  setTimeout('fetch_data()',<?=1000*$time_interval?>);
}

function isNumber(a) {
  return typeof a == 'number' && isFinite(a);
}

function formatSpeed(speed, unit) {
  if (unit == 'bits')
    return formatSpeedBits(speed);
  if (unit == 'bytes')
    return formatSpeedBytes(speed);
}

function formatSpeedBits(speed) {
  // format speed in bits/sec, input: bytes/sec
  if (speed < 125000)
    return Math.round(speed / 125) + " <?=gettext("Kbps"); ?>";
  if (speed < 125000000)
    return Math.round(speed / 1250)/100 + " <?=gettext("Mbps"); ?>";
  // else
  return Math.round(speed / 1250000)/100 + " <?=gettext("Gbps"); ?>";  /* wow! */
}

function formatSpeedBytes(speed) {
  // format speed in bytes/sec, input:  bytes/sec
  if (speed < 1048576)
    return Math.round(speed / 10.24)/100 + " <?=gettext("KB/s"); ?>";
  if (speed < 1073741824)
    return Math.round(speed / 10485.76)/100 + " <?=gettext("MB/s"); ?>";
  // else
  return Math.round(speed / 10737418.24)/100 + " <?=gettext("GB/s"); ?>";  /* wow! */
}

function LZ(x) {
  return (x < 0 || x > 9 ? "" : "0") + x;
}

    ]]>
  </script>
</svg>
