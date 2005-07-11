#!/usr/local/bin/php
<?php
/*
    diag_dump_states.php
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

require_once("guiconfig.inc");
require_once("xmlparse.inc");

function gentitle_pkg($pgname) {
	global $config;
	return $config['system']['hostname'] . "." . $config['system']['domain'] . " - " . $pgname;
}

$pgtitle = "CARP: Status";
include("head.inc");

$states=split("\n",`/sbin/pfctl -ss`);

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
include("fbegin.inc");
?>
<p class="pgtitle"><?=$pgtitle?></p>
<form action="carp_status.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>

<div id="mainlevel">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td class="tabcont">
<table colspan="3" cellpadding="3" width="100%">
<?php

/* table header */
print "\n<tr><!-- " . count($state_split) . " -->";
print "<tr bgcolor='#990000'>";
print "<td><b><font color='#ffffff'>Type</td>";
print "<td><b><font color='#ffffff'>Proto</td>";
print "<td colspan='7'><b><font color='#ffffff'>Source -> Destination</td>";
print "<td><b><font color='#ffffff'>State</td>";
print "</tr>";

foreach($states as $state) {
	$state_fixed=str_replace("  ", " ", $state);
	$state_fixed=str_replace("  ", " ", $state_fixed);
	$state_split = split(" ", $state_fixed);
	if(count($state_split) == 7) {
		print "<td>{$state_split[0]}</td>";
		print "<td>{$state_split[1]}</td>";
		print "<td>{$state_split[2]}</td>";
		print "<td>{$state_split[3]}</td>";
		print "<td>{$state_split[4]}</td>";
		print "<td>{$state_split[5]}</td>";
		print "<td>&nbsp;</td>";
		print "<td>&nbsp;</td>";
		print "<td>&nbsp;</td>";
		print "<td>{$state_split[6]}</td>";
	} else if(count($state_split) == 8) {
		print "<td>{$state_split[0]}</td>";
		print "<td>{$state_split[1]}</td>";
		print "<td>{$state_split[2]}</td>";
		print "<td>{$state_split[3]}</td>";
		print "<td>{$state_split[4]}</td>";
		print "<td>{$state_split[5]}</td>";
		print "<td>{$state_split[6]}</td>";
		print "<td>&nbsp;</td>";
		print "<td>&nbsp;</td>";
		print "<td>{$state_split[7]}</td>";	
	} else if(count($state_split) == 9) {
		print "<td>{$state_split[0]}</td>";
		print "<td>{$state_split[1]}</td>";
		print "<td>{$state_split[2]}</td>";
		print "<td>{$state_split[3]}</td>";
		print "<td>{$state_split[4]}</td>";
		print "<td>{$state_split[5]}</td>";
		print "<td>{$state_split[6]}</td>";
		print "<td>{$state_split[7]}</td>";	
		print "<td>&nbsp;</td>";
		print "<td>{$state_split[8]}</td>";	
	}
	print "</tr>";
}

?>
</table>

</center>
</td></tr>
</table>
</div>

<?php include("fend.inc"); ?>
<script type="text/javascript">
NiftyCheck();
Rounded("div#mainlevel","bl br","#FFF","#eeeeee","smooth");
</script>

</body>
</html>

