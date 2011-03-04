<?php
/*
	$Id$
	part of m0n0wall (http://m0n0.ch/wall)
	
	Copyright (C) 2004-2005 T. Lechat <dev@lechat.org>, Manuel Kasper <mk@neon1.net>
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
##|*IDENT=page-diagnostics-cpuutilization
##|*NAME=Diagnostics: CPU Utilization page
##|*DESCR=Allow access to the 'Diagnostics: CPU Utilization' page.
##|*MATCH=graph_cpu.php*
##|-PRIV

require_once("guiconfig.inc");

header("Last-Modified: " . gmdate( "D, j M Y H:i:s" ) . " GMT" );
header("Expires: " . gmdate( "D, j M Y H:i:s", time() ) . " GMT" );
header("Cache-Control: no-store, no-cache, must-revalidate" ); // HTTP/1.1
header("Cache-Control: post-check=0, pre-check=0", FALSE );
header("Pragma: no-cache"); // HTTP/1.0
header("Content-type: image/svg+xml");

/********* Other conf *******/

$nb_plot = 120;  // maximum number of data points to plot in the graph
$fetch_link = "stats.php?stats=cpu";

//SVG attributes
$attribs['axis']='fill="black" stroke="black"';
$attribs['cpu']='fill="#FF0000" font-family="Tahoma, Verdana, Arial, Helvetica, sans-serif" font-size="7"';
$attribs['graph_cpu']='fill="none" stroke="#FF0000" stroke-opacity="0.8"';
$attribs['legend']='fill="black" font-family="Tahoma, Verdana, Arial, Helvetica, sans-serif" font-size="4"';
$attribs['grid_txt']='fill="gray" font-family="Tahoma, Verdana, Arial, Helvetica, sans-serif" font-size="6"';
$attribs['grid']='stroke="gray" stroke-opacity="0.5"';
$attribs['error']='fill="blue" font-family="Arial" font-size="4"';
$attribs['collect_initial']='fill="gray" font-family="Tahoma, Verdana, Arial, Helvetica, sans-serif" font-size="4"';

$height=100;  // SVG internal height : do not modify
$width=200;   // SVG internal width  : do not modify

/********* Graph DATA **************/
print('<?xml version="1.0" encoding="iso-8859-1"?>' . "\n");?>
<svg width="100%" height="100%" viewBox="0 0 <?=$width?> <?=$height?>" preserveAspectRatio="none" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" onload="init(evt);">
  <g id="graph">
    <rect id="bg" x1="0" y1="0" width="100%" height="100%" fill="white"/>
    <line id="axis_x" x1="0" y1="0" x2="0" y2="100%" <?=$attribs['axis']?>/>
    <line id="axis_y" x1="0" y1="100%" x2="100%" y2="100%" <?=$attribs['axis']?>/>
    <polygon id="axis_arrow_x" <?=$attribs['axis']?> points="<?=($width) . "," . ($height)?> <?=($width-2) . "," . ($height-2)?> <?=($width-2) . "," . $height?>"/>
    <path id="graph_cpu" d="" <?=$attribs['graph_cpu']?>/>
    <path id="grid" d="M0 <?=$height/4*1?> L <?=$width?> <?=$height/4*1?> M0 <?=$height/4*2?> L <?=$width?> <?=$height/4*2?> M0 <?=$height/4*3?> L <?=$width?> <?=$height/4*3?>" <?=$attribs['grid']?>/>
    <text id="grid_txt1" x="100%" y="25%" <?=$attribs['grid_txt']?> text-anchor="end">75%</text>
    <text id="grid_txt2" x="100%" y="50%" <?=$attribs['grid_txt']?> text-anchor="end">50%</text>
    <text id="grid_txt3" x="100%" y="75%" <?=$attribs['grid_txt']?> text-anchor="end">25%</text>
    <text id="graph_cpu_txt" x="4" y="8" <?=$attribs['cpu']?>> </text>
    <text id="error" x="50%" y="50%"  visibility="hidden" <?=$attribs['error']?> text-anchor="middle"><?=gettext("Cannot get CPU load"); ?></text>
    <text id="collect_initial" x="50%" y="50%"  visibility="hidden" <?=$attribs['collect_initial']?> text-anchor="middle"><?=gettext("Collecting initial data, please wait"); ?>...</text>
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
var last_cpu_total = 0;
var last_cpu_idle = 0;
var diff_cpu_total = 0;
var diff_cpu_idle = 0;
var cpu_data = new Array();

var max_num_points = <?=$nb_plot?>;  // maximum number of plot data points
var step = <?=$width?> / max_num_points;  // plot X division size
var scale = <?=$height?> / 100;

function init(evt) {
  SVGDoc = evt.target.ownerDocument;
  fetch_data();
}

function fetch_data() {
  getURL('<?=$fetch_link?>', plot_cpu_data);
}

function plot_cpu_data(obj) {
  if (!obj.success)
    return handle_error();  // getURL failed to get current CPU load data

  var cpu = parseInt(obj.content);
  if (!isNumber(cpu))
    return handle_error();

  switch (cpu_data.length) {
  case 0:
    SVGDoc.getElementById("collect_initial").setAttributeNS(null, 'visibility', 'visible');
    cpu_data[0] = cpu;
    fetch_data();
    return;
  case 1:
    SVGDoc.getElementById("collect_initial").setAttributeNS(null, 'visibility', 'hidden');
    break;
  case max_num_points:
    // shift plot to left if the maximum number of plot points has been reached
    var i = 0;
    while (i < max_num_points) {
      cpu_data[i] = cpu_data[++i];
    }
    --cpu_data.length;
  }

  cpu_data[cpu_data.length] = cpu;

  var path_data = "M 0 " + (<?=$height?> - (cpu_data[0] * scale));
  for (var i = 1; i < cpu_data.length; ++i) {
    var x = step * i;
    var y_cpu = <?=$height?> - (cpu_data[i] * scale);
    path_data += " L" + x + " " + y_cpu;
  }

  SVGDoc.getElementById("error").setAttributeNS(null, 'visibility', 'hidden');
  SVGDoc.getElementById('graph_cpu_txt').firstChild.data = cpu + '%';
  SVGDoc.getElementById('graph_cpu').setAttributeNS(null, "d", path_data);

  fetch_data();
}

function handle_error() {
  SVGDoc.getElementById("error").setAttributeNS(null, 'visibility', 'visible');
  fetch_data();
}

function isNumber(a) {
  return typeof a == 'number' && isFinite(a);
}

    ]]>
  </script>
</svg>
