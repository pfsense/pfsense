#!/usr/local/bin/php
<?php 
/*
	diag_logs_vpn.php
	part of m0n0wall (http://m0n0.ch/wall)
	
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

$nentries = $config['syslog']['nentries'];
if (!$nentries)
	$nentries = 50;

if ($_POST['clear']) {
	exec("/usr/sbin/clog -i -s 65536 /var/log/vpn.log");
}

function dump_clog($logfile, $tail) {
	global $g, $config;

	$sor = isset($config['syslog']['reverse']) ? "-r" : "";

	exec("/usr/sbin/clog " . $logfile . " | tail {$sor} -n " . $tail, $logarr);
	
	foreach ($logarr as $logent) {
		$logent = preg_split("/\s+/", $logent, 6);
		$llent = explode(",", $logent[5]);
		
		echo "<tr>\n";
		echo "<td class=\"listlr\" nowrap>" . htmlspecialchars(join(" ", array_slice($logent, 0, 3))) . "</td>\n";
		
		if ($llent[0] == "login")
			echo "<td class=\"listr\"><img src=\"in.gif\" width=\"11\" height=\"11\" title=\"login\"></td>\n";
		else
			echo "<td class=\"listr\"><img src=\"out.gif\" width=\"11\" height=\"11\" title=\"logout\"></td>\n";
		
		echo "<td class=\"listr\">" . htmlspecialchars($llent[3]) . "</td>\n";
		echo "<td class=\"listr\">" . htmlspecialchars($llent[2]) . "&nbsp;</td>\n";
		echo "</tr>\n";
	}
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Diagnostics: System logs");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Diagnostics: System logs</p>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
  <ul id="tabnav">
    <li class="tabinact"><a href="diag_logs.php">System</a></li>
    <li class="tabinact"><a href="diag_logs_filter.php">Firewall</a></li>
    <li class="tabinact"><a href="diag_logs_dhcp.php">DHCP</a></li>
    <li class="tabact">PPTP VPN</li>
    <li class="tabinact"><a href="diag_logs_settings.php">Settings</a></li>
  </ul>
  </td></tr>
  <tr>
    <td class="tabcont">
		<table width="100%" border="0" cellpadding="0" cellspacing="0"><tr>
		  <td colspan="4" class="listtopic"> 
			    Last <?=$nentries;?> firewall log entries</td>
			</tr>
			<tr>
			  <td class="listhdrr">Time</td>
			  <td class="listhdrr">Action</td>
			  <td class="listhdrr">User</td>
			  <td class="listhdrr">IP address</td>
			</tr>
			<?php dump_clog("/var/log/vpn.log", $nentries); ?>
          </table>
		<br><form action="diag_logs_vpn.php" method="post">
<input name="clear" type="submit" class="formbtn" value="Clear log">
</form>
	</td>
  </tr>
</table>
<?php include("fend.inc"); ?>
</body>
</html>
