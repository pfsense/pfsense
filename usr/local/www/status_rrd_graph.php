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

function gettext($text) {
	return $text;	
}
if ($_GET['if']) {
	$curif = $_GET['if'];
	$ifnum = $config['interfaces'][$curif]['if'];
} else {
	$curif = "wan";
}

if ($_GET['graph']) {
	$curgraph = $_GET['graph'];
} else {
	$curgraph = "traffic";
}

$pgtitle = gettext("Status: RRD Graphs");
include("head.inc");

?>

<script src="/javascript/scriptaculous/prototype.js" type="text/javascript"></script>
<script src="/javascript/scriptaculous/scriptaculous.js" type="text/javascript"></script>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
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
<?=gettext("Interface:");?>
<select name="if" class="formfld" style="z-index: -10;" onchange="document.form1.submit()">
<?php
foreach ($ifdescrs as $ifn => $ifd) {
	echo "<option value=\"$ifn\"";
	if ($ifn == $curif) echo " selected";
	echo ">" . htmlspecialchars($ifd) . "</option>\n";
}
?>
</select>
<?=gettext("Graph:");?>
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

$periods = array("2h", "6h", "48h", "14d", "2m", "18m");

foreach($periods as $period => $interval) {

	PRINT "<B>Analysis for $curif -- $interval $curgraph</B><BR>";
	PRINT "\n<IMG BORDER='1' name='{$curif}-{$interval}-{$curgraph}' id='{$curif}-{$interval}-{$curgraph}' ALT=\"$ifname $curgraph Graph\" SRC=\"status_rrd_graph_img.php?if=$curif&interval=$interval&graph=$curgraph\"><BR><BR>";
}

?>

</div>

<script language="javascript">
	function update_graph_images() {
		//alert('updating');
		var randomid = Math.floor(Math.random()*11);
		<?php
			/* generate update events utilizing prototype $('') feature */
			echo "\n";
			foreach($periods as $period => $interval) 
				echo "\t\t\$('{$curif}-{$interval}-{$curgraph}').src='status_rrd_graph_img.php?if={$curif}&interval={$interval}&graph={$curgraph}&tmp=' + randomid;\n";	
		?>	
		window.setTimeout('update_graph_images()', 355000);
	}
	window.setTimeout('update_graph_images()', 355000);
</script>

<?php include("fend.inc"); ?>
</body>
</html>
