<?php
/* $Id$ */
/*
	status_rrd_graph.php
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

$pgtitle = gettext("Status: RRD Graphs");
include("head.inc");

$rrddbpath = "/var/db/rrd/";

/* XXX: (billm) do we have an exec() type function that does this type of thing? */
exec("cd $rrddbpath;/usr/bin/find -name *.rrd", $databases);
rsort($databases);

/* Deduce a interface if possible and use the description */
$curif = split("-", $curdatabase);
$curif = "$curif[0]";
$friendly = convert_friendly_interface_to_friendly_descr(strtolower($curif));
$search = array("-", ".rrd", $curif);
$replace = array(" :: ", "", $friendly);
$prettydb = ucwords(str_replace($search, $replace, $curdatabase));

$styles = array('inverse' => 'Inverse', 'absolute' => 'Absolute');

?>
<script src="/javascript/scriptaculous/prototype.js" type="text/javascript"></script>
<script src="/javascript/scriptaculous/scriptaculous.js" type="text/javascript"></script>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<p><b><?=gettext("Note: Change of color and/or style may not take effect until the next 
refresh");?></b></p>
<form name="form1" action="status_rrd_graph.php" method="get" style="padding-bottom: 10px; margin-bottom: 14px; border-bottom: 1px solid #999999">
<?=gettext("Graphs:");?>
<select name="database" class="formselect" style="z-index: -10;" onchange="document.form1.submit()">
<?php
foreach ($databases as $db => $database) {
	echo "<option value=\"$database\"";
	if ($database == $curdatabase) echo " selected";
	/* Deduce a interface if possible and use the description */
	$curif = split("-", $database);
	$curif = "$curif[0]";
	$friendly = convert_friendly_interface_to_friendly_descr(strtolower($curif));
	$search = array("-", ".rrd", $curif);
	$replace = array(" :: ", "", $friendly);
	$prettyprint = ucwords(str_replace($search, $replace, $database));
	echo ">" . htmlspecialchars($prettyprint) . "</option>\n";
}
?>
</select>
<?=gettext("Style:");?>
<select name="style" class="formselect" style="z-index: -10;" onchange="document.form1.submit()">
<?php
foreach ($styles as $style => $styled) {
	echo "<option value=\"$style\"";
	if ($style == $curstyle) echo " selected";
	echo ">" . htmlspecialchars($styled) . "</option>\n";
}
?>
</select>
</form>

<div style="text-align: center;">
<?php

$periods = array("4h", "16h", "48h", "32d", "6m", "16m");

if((file_exists("{$rrddbpath}{$curdatabase}"))) {
	foreach($periods as $period => $interval) {
		PRINT "<B>Analysis for $prettydb -- $interval</B><br />";
		PRINT "\n<IMG BORDER='1' name='{$interval}-{$curif}' id='{$interval}-{$curif}' ALT=\"$prettydb Graph\" SRC=\"status_rrd_graph_img.php?interval=$interval&amp;database={$curdatabase}&amp;style={$curstyle}\" /><br /><br />";
	}
} else {
	PRINT "<b>There is no database available to generate $prettydb from.</b><br />";
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
				echo "\t\t\$('{$interval}-{$curif}').src='status_rrd_graph_img.php?interval={$interval}&database={$curdatabase}&style={$curstyle}&tmp=' + randomid;\n";
		?>
		window.setTimeout('update_graph_images()', 355000);
	}
	window.setTimeout('update_graph_images()', 355000);
</script>

<?php include("fend.inc"); ?>
</body>
</html>
