#!/usr/local/bin/php -f
<?php
/*
	graph.php
	part of m0n0wall (http://m0n0.ch/wall)
	
	Copyright (C) 2004 T. Lechat <dev@lechat.org> and Manuel Kasper <mk@neon1.net>.
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

// VERSION 1.0.4

/********** HTTP GET Based Conf ***********/
$ifnum=@$_GET["ifnum"];							//BSD / SNMP interface name / number
$ifname=@$_GET["ifname"]?$_GET["ifname"]:"Interface $ifnum";		//Interface name that will be showed on top right of graph

/********* Other conf *******/
$scale_type="up";		//Autoscale default setup : "up" = only increase scale; "follow" = increase and decrease scale according to current graphed datas
$nb_plot=120;			//NB plot in graph
$time_interval=1;		//Refresh time Interval
$first_stage_time_interval=2;	//First stage time Intervall

$urldata=@$_SERVER["SCRIPT_NAME"];
$fetch_link = "ifstats.cgi?$ifnum";

//Style
$style['bg']="fill:white;stroke:none;stroke-width:0;opacity:1;";
$style['axis']="fill:black;stroke:black;stroke-width:1;";
$style['in']="fill:#435370; font-family:Tahoma, Verdana, Arial, Helvetica, sans-serif; font-size:7;";
$style['out']="fill:#8092B3; font-family:Tahoma, Verdana, Arial, Helvetica, sans-serif; font-size:7;";
$style['graph_in']="fill:none;stroke:#435370;stroke-width:1;opacity:0.8;";
$style['graph_out']="fill:none;stroke:#8092B3;stroke-width:1;opacity:0.8;";
$style['legend']="fill:black; font-family:Tahoma, Verdana, Arial, Helvetica, sans-serif; font-size:4;";
$style['graphname']="fill:#435370; font-family:Tahoma, Verdana, Arial, Helvetica, sans-serif; font-size:8;";
$style['grid_txt']="fill:gray; font-family:Tahoma, Verdana, Arial, Helvetica, sans-serif; font-size:6;";
$style['grid']="stroke:gray;stroke-width:1;opacity:0.5;";
$style['switch_unit']="fill:#435370; font-family:Tahoma, Verdana, Arial, Helvetica, sans-serif; font-size:4; text-decoration:underline;";
$style['switch_scale']="fill:#435370; font-family:Tahoma, Verdana, Arial, Helvetica, sans-serif; font-size:4; text-decoration:underline;";
$style['error']="fill:blue; font-family:Arial; font-size:4;";
$style['collect_initial']="fill:gray; font-family:Tahoma, Verdana, Arial, Helvetica, sans-serif; font-size:4;";

//Error text if we cannot fetch data : depends on which method is used
$error_text = "Cannot get data about interface $ifnum";

$height=100;		//SVG internal height : do not modify
$width=200;		//SVG internal width : do not modify

/********* Graph DATA **************/
header("Content-type: image/svg+xml");
print('<?xml version="1.0" encoding="iso-8859-1"?>' . "\n");?><svg width="100%" height="100%" viewBox="0 0 <?=$width?> <?=$height?>" preserveAspectRatio="none" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" onload="init(evt)">
<g id="graph" style="visibility:visible">
	<rect id="bg" x1="0" y1="0" x2="<?=$width?>" y2="<?=$height?>" style="<?=$style['bg']?>"/>
	<line id="axis_x" x1="0" y1="0" x2="0" y2="<?=$height?>" style="<?=$style['axis']?>"/>
	<line id="axis_y" x1="0" y1="<?=$height?>" x2="<?=$width?>" y2="<?=$height?>" style="<?=$style['axis']?>"/>
	<path id="graph_out" d="M0 <?=$height?> L 0 <?=$height?>" style="<?=$style['graph_out']?>"/>
	<path id="graph_in"  d="M0 <?=$height?> L 0 <?=$height?>" style="<?=$style['graph_in']?>"/>
	<path id="grid"  d="M0 <?=$height/4*1?> L <?=$width?> <?=$height/4*1?> M0 <?=$height/4*2?> L <?=$width?> <?=$height/4*2?> M0 <?=$height/4*3?> L <?=$width?> <?=$height/4*3?>" style="<?=$style[grid]?>"/>
	<text id="grid_txt1" x="<?=$width?>" y="<?=$height/4*1?>" style="<?=$style['grid_txt']?> text-anchor:end"> </text>
	<text id="grid_txt2" x="<?=$width?>" y="<?=$height/4*2?>" style="<?=$style['grid_txt']?> text-anchor:end"> </text>
	<text id="grid_txt3" x="<?=$width?>" y="<?=$height/4*3?>" style="<?=$style['grid_txt']?> text-anchor:end"> </text>
	<text id="graph_in_lbl" x="5" y="8" style="<?=$style['in']?>">In</text>
	<text id="graph_out_lbl" x="5" y="16" style="<?=$style['out']?> ">Out</text>
	<text id="graph_in_txt" x="20" y="8" style="<?=$style['in']?>"> </text>
	<text id="graph_out_txt" x="20" y="16" style="<?=$style['out']?> "> </text>
	<text id="ifname" x="<?=$width?>" y="8" style="<?=$style['graphname']?> text-anchor:end"><?=$ifname?></text>
	<text id="switch_unit" x="<?=$width*0.55?>" y="5" style="<?=$style['switch_unit']?>">Switch to bytes/s</text>
	<text id="switch_scale" x="<?=$width*0.55?>" y="11" style="<?=$style['switch_scale']?>">AutoScale (<?=$scale_type?>)</text>
	<text id="datetime" x="<?=$width*0.33?>" y="5" style="<?=$style['legend']?>"> </text>
	<text id="graphlast" x="<?=$width*0.55?>" y="17" style="<?=$style['legend']?>">Graph shows last <?=$time_interval*$nb_plot?> seconds</text>
	<polygon id="axis_arrow_x" style="<?=$style['axis']?>" points="<?=($width) . "," . ($height)?> <?=($width-2) . "," . ($height-2)?> <?=($width-2) . "," . $height?>"/>
	<text id="error" x="<?=$width*0.5?>" y="<?=$height*0.5?>"  style="visibility:hidden;<?=$style['error']?> text-anchor:middle"><?=$error_text?></text>
	<text id="collect_initial" x="<?=$width*0.5?>" y="<?=$height*0.5?>"  style="visibility:hidden;<?=$style['collect_initial']?> text-anchor:middle">Collecting initial data, please wait...</text>
</g>

<script type="text/ecmascript"><![CDATA[
var SVGDoc;
var last_ifin=0;
var last_ifout=0;
var last_ugmt=0;
var diff_ugmt=0;
var diff_ifin=0;
var diff_ifout=0;
var max = 0;
plot_in=new Array();
plot_out=new Array();

var isfirst=1;
var index_plot=0;
var step = <?=$width?> / <?=$nb_plot?> ;
var unit = 'bits';
var scale_type = '<?=$scale_type?>';

function init(evt) {
	SVGDoc = evt.getTarget().getOwnerDocument();
	SVGDoc.getElementById("switch_unit").addEventListener("mousedown", switch_unit, false);
	SVGDoc.getElementById("switch_scale").addEventListener("mousedown", switch_scale, false);

	go();
}

function switch_unit(event)
{
	SVGDoc.getElementById('switch_unit').getFirstChild().setData('Switch to ' + unit + '/s');
	if(unit=='bits') unit='bytes';else unit='bits';
}

function switch_scale(event)
{
	if(scale_type=='up') scale_type='follow';else scale_type='up';
	SVGDoc.getElementById('switch_scale').getFirstChild().setData('AutoScale (' + scale_type + ')');
}

function go() {
	getURL('<?=$fetch_link?>',urlcallback);
}

function urlcallback(obj) {
	var error = 0;
	now = new Date();

	//Show datetimelegend
	var datetime = (now.getMonth()+1) + "/" + now.getDate() + "/" + now.getFullYear() + ' ' + 
		LZ(now.getHours()) + ":" + LZ(now.getMinutes()) + ":" + LZ(now.getSeconds());
	SVGDoc.getElementById('datetime').getFirstChild().setData(datetime);

	//shift plot to left if nb_plot is already completed
	var i=0;
	if(index_plot > <?=$nb_plot?>)
	{
		while (i <= <?=$nb_plot?>)
		{
			var a=i+1;
			plot_in[i]=plot_in[a];
			plot_out[i]=plot_out[a];
			i=i+1;
		}
		index_plot = <?=$nb_plot?>;
		plot_in[index_plot]=0;
		plot_out[index_plot]=0;
	}

	//if Geturl returns something
	if (obj.success){
		var t=obj.content.split("|");
		var ugmt = parseFloat(t[0]);//ugmt is an unixtimestamp style
		var ifin = parseInt(t[1]);//ifin must be in bytes
		var ifout = parseInt(t[2]);//ifout must be in bytes
		var scale;

		if(!isNumber(ifin) || !isNumber(ifout)) {
			goerror();
			return;
		} else {
			SVGDoc.getElementById("error").getStyle().setProperty ('visibility', 'hidden');
		}

		diff_ugmt  = ugmt - last_ugmt;
		diff_ifin  = ifin - last_ifin;
		diff_ifout = ifout - last_ifout;
		
		if (diff_ugmt == 0)
			diff_ugmt = 1;	/* avoid division by zero */

		last_ugmt = ugmt;
		last_ifin = ifin;
		last_ifout = ifout;

		if(isfirst) {
			SVGDoc.getElementById("collect_initial").getStyle().setProperty ('visibility', 'visible');
			setTimeout('go()',<?=1000*$first_stage_time_interval?>);
			isfirst=0;
			return;
		} else SVGDoc.getElementById("collect_initial").getStyle().setProperty ('visibility', 'hidden');

		plot_in[index_plot] = diff_ifin / diff_ugmt;
		plot_out[index_plot]= diff_ifout / diff_ugmt;

		SVGDoc.getElementById('graph_in_txt').getFirstChild().setData(formatSpeed(plot_in[index_plot],unit));
		SVGDoc.getElementById('graph_out_txt').getFirstChild().setData(formatSpeed(plot_out[index_plot],unit));

		/* determine peak for sensible scaling */		
		if (scale_type == 'up') {
			if (plot_in[index_plot] > max)
				max = plot_in[index_plot];
			if (plot_out[index_plot] > max)
				max = plot_out[index_plot];		
		} else if (scale_type == 'follow') {
			i = 0;
			max = 0;
			while (i <= <?=$nb_plot?>) {
				if (plot_in[i] > max)
					max = plot_in[i];
				if (plot_out[i] > max)
					max = plot_out[i];
				i++;
			}
		}

		var rmax;
		
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
		SVGDoc.getElementById('grid_txt1').getFirstChild().setData(formatSpeed(3*rmax/4,unit));
		SVGDoc.getElementById('grid_txt2').getFirstChild().setData(formatSpeed(2*rmax/4,unit));
		SVGDoc.getElementById('grid_txt3').getFirstChild().setData(formatSpeed(rmax/4,unit));
		
		i = 0;
		
		while (i <= index_plot)
		{
			var x = step * i;
			var y_in= <?=$height?> - (plot_in[i] * scale);
			var y_out= <?=$height?> - (plot_out[i] * scale);
			if(i==0) {
				var path_in = "M" + x + " " + y_in;
				var path_out = "M" + x + " " + y_out;
			}
			else
			{
				var path_in = path_in + " L" + x + " " + y_in;
				var path_out = path_out + " L" + x + " " + y_out;
			}
			i = i + 1;
		}

		index_plot = index_plot+1;
		SVGDoc.getElementById('graph_in').setAttribute("d", path_in);
		SVGDoc.getElementById('graph_out').setAttribute("d", path_out);

		setTimeout('go()',<?=1000*$time_interval?>);
	}
	else
	{ //In case of Geturl fails
		goerror();
	}
}

function goerror() {
	SVGDoc.getElementById("error").getStyle().setProperty ('visibility', 'visible');
	setTimeout('go()',<?=1000*$time_interval?>);
}

function isNumber(a) {
    return typeof a == 'number' && isFinite(a);
}

function formatSpeed(speed,unit){
	if(unit=='bits') return formatSpeedBits(speed);
	else if(unit=='bytes') return formatSpeedBytes(speed);
}

function formatSpeedBits(speed) {
	// format speed in bits/sec, input: bytes/sec
	if (speed <	125000)
		return Math.round(speed / 125) + " Kbps";
	else if (speed < 125000000)
		return Math.round(speed / 1250)/100 + " Mbps";
	else
		return Math.round(speed / 1250000)/100 + " Gbps";	/* wow! */
}
function formatSpeedBytes(speed) {
	// format speed in bytes/sec, input:  bytes/sec
	if (speed <	1048576)
		return Math.round(speed / 10.24)/100 + " KB/s";
	else if (speed < 1073741824)
		return Math.round(speed / 10485.76)/100 + " MB/s";
	else
		return Math.round(speed / 10737418.24)/100 + " GB/s";	/* wow! */
}
function LZ(x) {
	return (x < 0 || x > 9 ? "" : "0") + x
}
]]></script>
</svg>