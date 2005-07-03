#!/usr/local/bin/php
<?php
/*
    services_status.php
    Copyright (C) 2004, 2005 Scott Ullrich
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INClUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
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

function gentitle_pkg($pgname) {
	global $config;
	return $config['system']['hostname'] . "." . $config['system']['domain'] . " - " . $pgname;
}

if($_GET['mode'] == "restartservice" and $_GET['service']) {
	restart_service($_GET['service']);
	$savemsg = "{$_GET['service']} has been restarted.";
}

if($_GET['mode'] == "startservice" and $_GET['service']) {
        start_service($_GET['service']);
        $savemsg = "{$_GET['service']} has been started.";
}

if($_GET['mode'] == "stopservice" and $_GET['service']) {
        stop_service($_GET['service']);
        $savemsg = "{$_GET['service']} has been stopped.";
}

/* batch mode, allow other scripts to call this script */
if($_GET['batch']) exit;

$pgtitle = "Status: Services";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
include("fbegin.inc");
?>
<p class="pgtitle"><?=$pgtitle?></p>
<form action="status_services.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>

<p>

<div id="mainarea">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr>
    <td class="tabcont">
    <table width="100%" border="0" cellpadding="6" cellspacing="0">
	<tr>
	  <td class="listhdrr"><b><center>Service</center></b></td>
	  <td class="listhdrr"><b><center>Description</center></b></td>
	  <td class="listhdrr"><b><center>Status</center></b></td>
	</tr>

<?php

exec("/bin/ps ax | awk '{ print $5 }'", $psout); 
array_shift($psout);
foreach($psout as $line) {
	$ps[] = trim(array_pop(explode(' ', array_pop(explode('/', $line)))));
}

if($config['installedpackages']['service']) {
	foreach($config['installedpackages']['service'] as $service) {
		if(!$service['name']) continue;
		if(!$service['description']) $service['description'] = "Unknown";
		echo '<tr><td class="listlr">' . $service['name'] . '</td>';
		echo '<td class="listlr">' . $service['description'] . '</td>';
		if(is_service_running($service['name'], $ps)) {
			echo '<td class="listlr">Running</td>';
			$running = true;
		} else {
			echo '<td class="listbg">Stopped</td>';
			$running = false;
		}
		echo '<td valign="middle" class="list" nowrap>';
		if($running) {
			echo "<a href='status_services.php?mode=restartservice&service={$service['name']}'>";
			echo "<img title='Restart Service' border='0' src='/service_restart.gif'></a> ";
			echo "<a href='status_services.php?mode=stopservice&service={$service['name']}'>";
			echo "<img title='Stop Service' border='0' src='/service_stop.gif'> ";
			echo "</a>";
		} else {
			echo "<a href='status_services.php?mode=startservice&service={$service['name']}'> ";
			echo "<img title='Start Service' border='0' src='/service_start.gif'></a> ";
		}
		echo '</td>';
		echo '</tr>';
	}
} else {
	echo "<tr><td colspan=\"3\"><center>No services found.</td></tr>";
}
?>
</table>

</td>
</tr></table>
</div>

<?php include("fend.inc"); ?>
<script type="text/javascript">
NiftyCheck();
Rounded("div#mainarea","all","#FFF","#eeeeee","smooth");
</script>

</body>
</html>

