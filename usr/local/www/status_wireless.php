#!/usr/local/bin/php
<?php
/*
	status_wireless.php
	Copyright (C) 2004 Scott Ullrich
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

require_once("config.inc");
require_once("guiconfig.inc");

function gentitle_pkg($pgname) {
	global $config;
	return $config['system']['hostname'] . "." . $config['system']['domain'] . " - " . $pgname;
}

$pgtitle = "Diagnostics: Wireless Status";
include("head.inc");

$if = $_POST['if'];
if($_GET['if'] <> "")
	$if = $_GET['if'];
if($if == "") {
	/* Find the first interface
	   that is wireless */
	foreach($config['interfaces'] as $interface) {
		if($interface['wireless'] <> "") {
			$if = $interface['if'];
			break;
		}
	}
}
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
include("fbegin.inc");
?>
<p class="pgtitle"><?=$pgtitle?></p>
<form action="carp_status.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>

<div id="mainarea">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td>
<?php
$tab_array = array();
foreach($config['interfaces'] as $interface) {
	if($interface['wireless'] <> "") {
		if($if == $interface['if'])
			$enabled = true;
		else
			$enabled = false;
		$friendly = convert_real_interface_to_friendly_interface_name($interface['if']);
		if($interface['descr'] <> "")
			$friendly = $interface['descr'];
		$tab_array[] = array("Status ($friendly)", $enabled, "status_wireless.php?if={$interface['if']}");
		break;
	}
}
/* XXX: add other wireless interfaces here */
display_top_tabs($tab_array);
?>
</td></tr>
<tr><td class="tabcont">
<table colspan="3" cellpadding="3" width="100%">

<?php

/* table header */
print "\n<tr><!-- " . count($state_split) . " -->";
print "<tr bgcolor='#990000'>";
print "<td><b><font color='#ffffff'>ADDR</td>";
print "<td><b><font color='#ffffff'>AID</td>";
print "<td><b><font color='#ffffff'>CHAN</td>";
print "<td><b><font color='#ffffff'>RATE</td>";
print "<td><b><font color='#ffffff'>RSSI</td>";
print "<td><b><font color='#ffffff'>IDLE</td>";
print "<td><b><font color='#ffffff'>TXSEQ</td>";
print "<td><b><font color='#ffffff'>RXSEQ</td>";
print "<td><b><font color='#ffffff'>CAPS</td>";
print "<td><b><font color='#ffffff'>ERP</td>";
print "</tr>\n\n";

$states=split("\n",`/sbin/ifconfig {$if} list sta | grep -v "AID CHAN"`);

$counter=0;
foreach($states as $state) {
	$state_fixed = str_replace("  ", " ", $state);
	$state_fixed = str_replace("  ", " ", $state_fixed);
	$state_fixed = str_replace("  ", " ", $state_fixed);
	$state_split = split(" ", $state_fixed);
	print "<tr>";
	print "<td>{$state_split[0]}</td>";
	print "<td>{$state_split[1]}</td>";
	print "<td>{$state_split[2]}</td>";
	print "<td>{$state_split[3]}</td>";
	print "<td>{$state_split[4]}</td>";
	print "<td>{$state_split[5]}</td>";
	print "<td>{$state_split[6]}</td>";
	print "<td>{$state_split[7]}</td>";
	print "<td>{$state_split[8]}</td>";
	print "<td>{$state_split[9]}</td>";
	print "</tr>\n";
	print "<!-- $state_fixed -->\n";
}

?>
</table>

</center>
</td></tr>
</table>
</div>

<?php include("fend.inc"); ?>
<meta http-equiv="refresh" content="120;url=status_wireless.php?if=<?php echo $if; ?>">
</body>
</html>
