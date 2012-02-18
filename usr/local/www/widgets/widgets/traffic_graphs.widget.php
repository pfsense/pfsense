<?php
/*
	$Id$
	Copyright 2007 Scott Dale
	Part of pfSense widgets (www.pfsense.com)
	originally based on m0n0wall (http://m0n0.ch/wall)

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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");

$ifdescrs = get_configured_interface_with_descr();

?>
<input type="hidden" id="traffic_graphs-config" name="traffic_graphs-config" value="">
<?php
	//set variables for traffic graph
	$width = "100%";
	$height = "150";
	$showngraphlist = explode(",", $config['widgets']['traffic_graphs-config']);
	$graphcounter = 0;
	if($config['widgets']['traffic_graphs-config']) {
		$graphlistcount = count($showngraphlist);
		$refreshintervalstring = $showngraphlist[$graphlistcount-1];
		$eqposition = strpos($refreshintervalstring,"=");
		$refreshInterval = substr($refreshintervalstring, $eqposition +1 );
	} else {
		$refreshInterval = "10";
	}
?>

<div id="traffic_graphs-settings" name="traffic_graphs-settings" class="widgetconfigdiv" style="display:none;">
Refresh Interval:
	<select name="refreshInterval" class="formfld" id="refreshInterval" onchange="updateGraphDisplays();">
		<option value="1" <?php if ($refreshInterval == "1") echo "SELECTED";?>>1</option>
		<option value="2" <?php if ($refreshInterval == "2") echo "SELECTED";?>>2</option>
		<option value="3" <?php if ($refreshInterval == "3") echo "SELECTED";?>>3</option>
		<option value="4" <?php if ($refreshInterval == "4") echo "SELECTED";?>>4</option>
		<option value="5" <?php if ($refreshInterval == "5") echo "SELECTED";?>>5</option>
		<option value="6" <?php if ($refreshInterval == "6") echo "SELECTED";?>>6</option>
		<option value="7" <?php if ($refreshInterval == "7") echo "SELECTED";?>>7</option>
		<option value="8" <?php if ($refreshInterval == "8") echo "SELECTED";?>>8</option>
		<option value="9" <?php if ($refreshInterval == "9") echo "SELECTED";?>>9</option>
		<option value="10" <?php if ($refreshInterval == "10") echo "SELECTED";?>>10</option>
	</select>&nbsp; Seconds<br>&nbsp; &nbsp; &nbsp; <b>Note:</b> changing this setting will increase CPU utilization<br><br>
	<input id="submit" name="submit" type="submit" onclick="return updatePref();" class="formbtn" value="Save Settings" />
</div>

<script language="javascript" type="text/javascript">
	d = document;
	selectIntLink = "traffic_graphs-configure";
	textlink = d.getElementById(selectIntLink);
	textlink.style.display = "inline";
</script>

<?php
	foreach ($ifdescrs as $ifdescr => $ifname) {
		$ifinfo = get_interface_info($ifdescr);
		$currentgraph = $showngraphlist[$graphcounter];
		$colposition = strpos($currentgraph,":");
		$currentgraph = substr($currentgraph, $colposition+1);
		if($config['widgets']['traffic_graphs-config']) {
			if ($currentgraph =="show") {
				$mingraphbutton = "inline";
				$showgraphbutton = "none";
				$graphdisplay = "inline";
				$interfacevalue = "show";
			} else {
				$mingraphbutton = "none";
				$showgraphbutton = "inline";
				$graphdisplay = "none";
				$interfacevalue = "hide";
			}
		} else {
			if(!$firstgraphshown) {
				$mingraphbutton = "inline";
				$showgraphbutton = "none";
				$graphdisplay = "inline";
				$interfacevalue = "show";
			} else {
				$mingraphbutton = "none";
				$showgraphbutton = "inline";
				$graphdisplay = "none";
				$interfacevalue = "hide";
			}
		}

	 if ($ifinfo['status'] != "down") {
	?>
	<div id="<?=$ifname;?>trafficdiv" style="padding: 5px">
	<input type="hidden" id="<?php echo $ifname;?>_graph-config" name="<?php echo $ifname;?>_graph-config" class="graphsettings" value="<?=$interfacevalue;?>">
		<div id="<?=$ifname;?>topic" class="widgetsubheader">
			<div style="float:left;width:49%">
				<span onClick="location.href='/status_graph.php?if=<?=$ifdescr;?>'" style="cursor:pointer">Current <?=$ifname;?> Traffic</span>
			</div>
			<div align="right" style="float:right;width:49%">
				<div id="<?=$ifname;?>graphdiv-min" onclick='return trafficminimizeDiv("<?php echo $ifname;?>",true)' style="display:<?php echo $mingraphbutton;?>; cursor:pointer" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_minus.gif" alt="Minimize <?=$ifname;?> traffic graph" /></div>
				<div id="<?=$ifname;?>graphdiv-open" onclick='return trafficshowDiv("<?php echo $ifname;?>",true)' style="display:<?php echo $showgraphbutton;?>; cursor:pointer" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_open.gif" alt="Show <?=$ifname;?> traffic graph" /></div>
			</div>
			<div style="clear:both;"></div>
		</div>
		<div id="<?=$ifname;?>graphdiv" style="display:<?php echo $graphdisplay;?>">
			<embed id="graph" src="graph.php?ifnum=<?=$ifdescr;?>&ifname=<?=rawurlencode($ifname);?>&timeint=<?=$refreshInterval;?>&initdelay=<?=($graphcounter+1) * 2;?>" type="image/svg+xml" width="<? echo $width; ?>" height="<? echo $height; ?>" pluginspage="http://www.adobe.com/svg/viewer/install/auto" />
		</div>
<? $firstgraphshown = true; $graphcounter++; ?>
	</div>
	 <? }
	}
?>
