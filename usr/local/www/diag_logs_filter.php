#!/usr/local/bin/php
<?php 
/*
	diag_logs_filter.php
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
	exec("/usr/sbin/clog -i -s 262144 /var/log/filter.log");
}

function dump_clog($logfile, $tail, $withorig = true) {
	global $g, $config;

	$sor = isset($config['syslog']['reverse']) ? "-r" : "";

	exec("/usr/sbin/clog " . $logfile . " | tail {$sor} -n " . $tail, $logarr);
	
	foreach ($logarr as $logent) {
		$logent = preg_split("/\s+/", $logent, 6);
		echo "<tr valign=\"top\">\n";
		
		if ($withorig) {
			echo "<td class=\"listlr\" nowrap>" . htmlspecialchars(join(" ", array_slice($logent, 0, 3))) . "</td>\n";
			echo "<td class=\"listr\">" . htmlspecialchars($logent[4] . " " . $logent[5]) . "</td>\n";
		} else {
			echo "<td class=\"listlr\" colspan=\"2\">" . htmlspecialchars($logent[5]) . "</td>\n";
		}
		echo "</tr>\n";
	}
}

function conv_clog($logfile, $tail) {
	global $g, $config;
	
	/* make interface/port table */
	$iftable = array();
	$iftable[$config['interfaces']['lan']['if']] = "LAN";
	$iftable[get_real_wan_interface()] = "WAN";
	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++)
		$iftable[$config['interfaces']['opt' . $i]['if']] = $config['interfaces']['opt' . $i]['descr'];

	$sor = isset($config['syslog']['reverse']) ? "-r" : "";

	exec("/usr/sbin/clog " . $logfile . " | tail {$sor} -n " . $tail, $logarr);
	
	$filterlog = array();
	
	foreach ($logarr as $logent) {
		$logent = preg_split("/\s+/", $logent, 6);
		$ipfa = explode(" ", $logent[5]);
		
		$flent = array();
		$i = 0;
		$flent['time'] = $ipfa[$i];
		$i++;
		if (substr($ipfa[$i], -1) == "x") {
			$flent['count'] = substr($ipfa[$i], 0, -1);
			$i++;
		}
		if ($iftable[$ipfa[$i]])
			$flent['interface'] = $iftable[$ipfa[$i]];
		else
			$flent['interface'] = $ipfa[$i];
		$i += 2;
		$flent['act'] = $ipfa[$i];
		$i++;
		$flent['src'] = format_ipf_ip($ipfa[$i]);
		$i += 2;
		$flent['dst'] = format_ipf_ip($ipfa[$i]);
		$i += 2;
		$flent['proto'] = strtoupper($ipfa[$i]);
		
		$filterlog[] = $flent;
	}
	
	return $filterlog;
}

function format_ipf_ip($ipfip) {
	list($ip,$port) = explode(",", $ipfip);
	if (!$port)
		return $ip;
	
	return $ip . ", port " . $port;
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
    <li class="tabact">Firewall</li>
    <li class="tabinact"><a href="diag_logs_dhcp.php">DHCP</a></li>
    <li class="tabinact"><a href="diag_logs_vpn.php">PPTP VPN</a></li>
    <li class="tabinact"><a href="diag_logs_settings.php">Settings</a></li>
  </ul>
  </td></tr>
  <tr>
    <td class="tabcont">
<?php if (!isset($config['syslog']['rawfilter'])):
	$filterlog = conv_clog("/var/log/filter.log", $nentries);
?>
		<table width="100%" border="0" cellpadding="0" cellspacing="0"><tr>
		  <td colspan="6" class="listtopic"> 
			    Last <?=$nentries;?> firewall log entries</td>
			</tr>
			<tr>
			  <td width="10%" class="listhdrr">Act</td>
			  <td width="20%" class="listhdrr">Time</td>
			  <td width="10%" class="listhdrr">If</td>
			  <td width="20%" class="listhdrr">Source</td>
			  <td width="20%" class="listhdrr">Destination</td>
			  <td width="10%" class="listhdrr">Proto</td>
			</tr><?php foreach ($filterlog as $filterent): ?>
			<tr>
			  <td class="listlr" nowrap>
			  <?php if (strstr(strtolower($filterent['act']), "p"))
			  			$img = "pass.gif";
					 else 
					 	$img = "block.gif";
			 	?>
			  <img src="<?=$img;?>" width="11" height="11" align="absmiddle">
			  <?php if ($filterent['count']) echo $filterent['count'];?></td>
			  <td class="listr" nowrap><?=htmlspecialchars($filterent['time']);?></td>
			  <td class="listr" nowrap><?=htmlspecialchars($filterent['interface']);?></td>
			  <td class="listr" nowrap><?=htmlspecialchars($filterent['src']);?></td>
			  <td class="listr" nowrap><?=htmlspecialchars($filterent['dst']);?></td>
			  <td class="listr" nowrap><?=htmlspecialchars($filterent['proto']);?></td>
			</tr><?php endforeach; ?>
                    </table>
<?php else: ?>
		<table width="100%" border="0" cellspacing="0" cellpadding="0">
		  <tr> 
			<td colspan="2" class="listtopic"> 
			  Last <?=$nentries;?> firewall log entries</td>
		  </tr>
		  <?php dump_clog("/var/log/filter.log", $nentries, false); ?>
		</table>
<?php endif; ?>
		<br><form action="diag_logs_filter.php" method="post">
<input name="clear" type="submit" class="formbtn" value="Clear log">
</form>
	</td>
  </tr>
</table>
<?php include("fend.inc"); ?>
</body>
</html>
