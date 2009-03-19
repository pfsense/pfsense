#!/usr/local/bin/php
<?php
/*
	diag_logs_vpn.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2003-2006 Manuel Kasper <mk@neon1.net>.
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

##|+PRIV
##|*IDENT=page-diagnostics-logs-pptpvpn
##|*NAME=Diagnostics: Logs: PPTP VPN page
##|*DESCR=Allow access to the 'Diagnostics: Logs: PPTP VPN' page.
##|*MATCH=diag_logs_vpn.php*
##|-PRIV


$pgtitle = array("Status","System logs","PPTP VPN");
require("guiconfig.inc");

$nentries = $config['syslog']['nentries'];
if (!$nentries)
	$nentries = 50;

if ($_POST['clear']) {
	if(isset($config['system']['disablesyslogclog'])) {
		unlink("/var/log/vpn.log");
		touch("/var/log/vpn.log");
	} else {
//		exec("killall syslogd");
//		sleep(1);
//		if(file_exists("/var/log/vpn.log"))	
//			unlink("/var/log/vpn.log");
		exec("/usr/sbin/fifolog_create -s 50688 /var/log/vpn.log");
		exec("/usr/bin/killall -HUP syslogd");
	}
	/* redirect to avoid reposting form data on refresh */
	header("Location: diag_logs_vpn.php");
	exit;
}

function dump_clog_vpn($logfile, $tail) {
	global $g, $config;

	$sor = isset($config['syslog']['reverse']) ? "-r" : "";

	$logarr = "";
	exec("/usr/sbin/fifolog_reader " . $logfile . " | tail {$sor} -n " . $tail, $logarr);

	foreach ($logarr as $logent) {
		$logent = preg_split("/\s+/", $logent, 6);
		$llent = explode(",", $logent[5]);

		echo "<tr>\n";
		echo "<td class=\"listlr\" nowrap>" . htmlspecialchars(join(" ", array_slice($logent, 0, 3))) . "</td>\n";

		if ($llent[0] == "login")
			echo "<td class=\"listr\"><img src=\"/themes/{$g['theme']}/images/icons/icon_in.gif\" width=\"11\" height=\"11\" title=\"login\"></td>\n";
		else
			echo "<td class=\"listr\"><img src=\"/themes/{$g['theme']}/images/icons/icon_out.gif\" width=\"11\" height=\"11\" title=\"logout\"></td>\n";

		echo "<td class=\"listr\">" . htmlspecialchars($llent[3]) . "</td>\n";
		echo "<td class=\"listr\">" . htmlspecialchars($llent[2]) . "&nbsp;</td>\n";
		echo "</tr>\n";
	}
}

include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
<?php
	$tab_array = array();
	$tab_array[] = array("System", false, "diag_logs.php");
	$tab_array[] = array("Firewall", false, "diag_logs_filter.php");
	$tab_array[] = array("DHCP", false, "diag_logs_dhcp.php");
	$tab_array[] = array("Portal Auth", false, "diag_logs_auth.php");
	$tab_array[] = array("IPsec VPN", false, "diag_logs_ipsec.php");
	$tab_array[] = array("PPTP VPN", true, "diag_logs_vpn.php");
	$tab_array[] = array("Load Balancer", false, "diag_logs_relayd.php");
	$tab_array[] = array("OpenVPN", false, "diag_logs_openvpn.php");
	$tab_array[] = array("OpenNTPD", false, "diag_logs_ntpd.php");
	$tab_array[] = array("Settings", false, "diag_logs_settings.php");
	display_top_tabs($tab_array);
?>
  </td></tr>
  <tr>
    <td class="tabcont">
		<table width="100%" border="0" cellpadding="0" cellspacing="0"><tr>
		  <td colspan="4" class="listtopic">
			    Last <?=$nentries;?> PPTP VPN log entries</td>
			</tr>
			<tr>
			  <td class="listhdrr">Time</td>
			  <td class="listhdrr">Action</td>
			  <td class="listhdrr">User</td>
			  <td class="listhdrr">IP address</td>
			</tr>
			<?php dump_clog_vpn("/var/log/vpn.log", $nentries); ?>
          </table>
		<br><form action="diag_logs_vpn.php" method="post">
<input name="clear" type="submit" class="formbtn" value="Clear log">
</form>
	</td>
  </tr>
</table>
<?php include("fend.inc"); ?>
