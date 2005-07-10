#!/usr/local/bin/php
<?php
/* $Id$ */
/*
	diag_logs_filter.php
	part of pfSesne by Scott Ullrich
	originally based on m0n0wall (http://m0n0.ch/wall)

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

$filter_logfile = "{$g['varlog_path']}/filter.log";
exec("/usr/sbin/tcpdump -n -e -ttt -r {$g['varlog_path']}/pflog | logger -t pf -p local0.info ");

$nentries = $config['syslog']['nentries'];
if (!$nentries)
	$nentries = 50;

if ($_POST['clear']) {
	exec("/usr/sbin/clog -i -s 262144 /var/log/filter.log");
}

/* format filter logs */
function conv_clog($logfile, $tail) {
	global $config;

	$nentries = $config['syslog']['nentries'];
	if (!$nentries)
		$nentries = 50;

	/* make interface/port table */
	$iftable = array();
	$iftable[$config['interfaces']['lan']['if']] = "LAN";
	$iftable[get_real_wan_interface()] = "WAN";
	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++)
		$iftable[$config['interfaces']['opt' . $i]['if']] = $config['interfaces']['opt' . $i]['descr'];

	$sor = isset($config['syslog']['reverse']) ? "-r" : "";

	exec("/usr/sbin/clog {$logfile} | /usr/bin/tail {$sor} -n {$tail}", $logarr);

	$filterlog = array();

	$counter = 0;

	foreach ($logarr as $logent) {

		preg_match("/(.*)\s(.*)\spf:.*rule.*\(match\):\s(\w+)\sin\son\s(\w+:)\s([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,7})\s([\<|\>])\s([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,7}):.*/",$logent,$log_split);

		$flent['proto'] 	= "TCP";
		if(stristr($logent, "UDP") == true)
			$flent['proto'] = "UDP";
		
		/* XXX: fetch time? */
		$flent['time'] 		= $log_split[1];
		$flent['act'] 		= $log_split[3];
		$flent['interface'] 	= strtoupper(convert_real_interface_to_friendly_interface_name(str_replace(":","",$log_split[4])));
		$flent['src'] 		= $log_split[5];
		$flent['dst'] 		= $log_split[7];
				
		
		
		if($flent['src'] == "" or $flent['dst'] == "") {
			/* do not display me! */
		} else {
			$counter++;
			$filterlog[] = $flent;
		}
		
	}

	return $filterlog;
}

function convert_port_period_to_colon($addr) {
	$addr_split = split("\.", $addr);
	if($addr_split[4] == "")
		$newvar = $addr_split[0] . "." . $addr_split[1] . "." . $addr_split[2] . "." . $addr_split[3];
	else
		$newvar = $addr_split[0] . "." . $addr_split[1] . "." . $addr_split[2] . "." . $addr_split[3] . ":" . $addr_split[4];
	return $newvar;
}

function format_ipf_ip($ipfip) {
	list($ip,$port) = explode(",", $ipfip);
	if (!$port)
		return $ip;

	return $ip . ", port " . $port;
}

$pgtitle = "Diagnostics: System logs: Firewall";
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
<?php
	$tab_array = array();
	$tab_array[0] = array("System", false, "diag_logs.php");
	$tab_array[1] = array("Firewall", true, "diag_logs_filter.php");
	$tab_array[2] = array("DHCP", false, "diag_logs_dhcp.php");
	$tab_array[3] = array("Portal Auth", false, "diag_logs_auth.php");
	$tab_array[4] = array("IPSEC VPN", false, "diag_logs_ipsec.php");
	$tab_array[5] = array("PPTP VPN", false, "diag_logs_vpn.php");
	$tab_array[6] = array("Settings", false, "diag_logs_settings.php");
	display_top_tabs($tab_array);
?>
 </td></tr>
  <tr>
    <td>
	<div id="mainarea">
		<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
<?php if (!isset($config['syslog']['rawfilter'])):
	$filterlog = conv_clog($filter_logfile, $nentries);
?>
		<tr>
		  <td colspan="6" class="listtopic">
			    Last <?=$nentries;?> firewall log entries</td>
			</tr>
			<tr>
			  <td width="10%" class="listhdrr">Act</td>
			  <td width="10%" class="listhdrr">Time</td>
			  <td width="15%" class="listhdrr">If</td>
			  <td width="25%" class="listhdrr">Source</td>
			  <td width="25%" class="listhdrr">Destination</td>
			  <td width="15%" class="listhdrr">Proto</td>
			</tr><?php foreach ($filterlog as $filterent): ?>
			<tr>
			  <td class="listlr" nowrap align="middle">
			  <?php if (strstr(strtolower($filterent['act']), "p"))
			  			$img = "pass.gif";
					 else
					 	$img = "block.gif";
			 	?>
			  <img src="<?=$img;?>" width="11" height="11" align="absmiddle">
			  <?php if ($filterent['count']) echo $filterent['count'];?></td>
			  <td class="listr" nowrap><?=htmlspecialchars($filterent['time']);?></td>
			  <td class="listr" nowrap><?=htmlspecialchars(convert_real_interface_to_friendly_interface_name($filterent['interface']));?></td>
			  <td class="listr" nowrap><?=htmlspecialchars($filterent['src']);?></td>
			  <td class="listr" nowrap><?=htmlspecialchars($filterent['dst']);?></td>
			  <td class="listr" nowrap><?=htmlspecialchars($filterent['proto']);?></td>
			</tr><?php endforeach; ?>
<?php else: ?>
		  <tr>
			<td colspan="2" class="listtopic">
			  Last <?=$nentries;?> firewall log entries</td>
		  </tr>
		  <?php dump_clog($filter_logfile, $nentries); ?>
<?php endif; ?>
		<tr><td><br><form action="diag_logs_filter.php" method="post">
<input name="clear" type="submit" class="formbtn" value="Clear log"></td></tr>
</form>
		</table>
		</div>
	</td>
  </tr>
</table>
<?php include("fend.inc"); ?>
</body>
</html>
