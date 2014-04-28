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

/*	
	pfSense_BUILDER_BINARIES:	/usr/sbin/fifolog_reader	/usr/local/sbin/clog
	pfSense_MODULE:	vpn
*/

##|+PRIV
##|*IDENT=page-diagnostics-logs-pptpvpn
##|*NAME=Diagnostics: Logs: VPN page
##|*DESCR=Allow access to the 'Diagnostics: Logs: VPN' page.
##|*MATCH=diag_logs_vpn.php*
##|-PRIV

$pgtitle = array(gettext("Status"), gettext("System logs"), gettext("VPN"));
require("guiconfig.inc");
require_once("vpn.inc");

$nentries = $config['syslog']['nentries'];
if (!$nentries)
	$nentries = 50;

if (htmlspecialchars($_POST['vpntype']))
	$vpntype = htmlspecialchars($_POST['vpntype']);
elseif (htmlspecialchars($_GET['vpntype']))
	$vpntype = htmlspecialchars($_GET['vpntype']);
else
	$vpntype = "pptp";

if (htmlspecialchars($_POST['mode']))
	$mode = htmlspecialchars($_POST['mode']);
elseif (htmlspecialchars($_GET['mode']))
	$mode = htmlspecialchars($_GET['mode']);
else
	$mode = "login";

switch ($vpntype) {
	case 'pptp':
		$logname = "pptps";
		break;
	case 'poes':
		$logname = "poes";
		break;
	case 'l2tp':
		$logname = "l2tps";
		break;
}

if ($_POST['clear']) {
	if ($mode != "raw")
		clear_log_file("/var/log/vpn.log");
	else
		clear_log_file("/var/log/{$logname}.log");
}

function dump_clog_vpn($logfile, $tail) {
	global $g, $config, $vpntype;

	$sor = isset($config['syslog']['reverse']) ? "-r" : "";

	$logarr = "";
	
	if(isset($config['system']['usefifolog'])) 
		exec("/usr/sbin/fifolog_reader " . escapeshellarg($logfile) . " | tail {$sor} -n " . $tail, $logarr);
	else 
		exec("/usr/local/sbin/clog " . escapeshellarg($logfile) . " | tail {$sor} -n " . $tail, $logarr);

	foreach ($logarr as $logent) {
		$logent = preg_split("/\s+/", $logent, 6);
		$llent = explode(",", $logent[5]);
		$iftype = substr($llent[1], 0, 4);
		if ($iftype != $vpntype)
			continue;
		echo "<tr>\n";
		echo "<td class=\"listlr nowrap\">" . htmlspecialchars(join(" ", array_slice($logent, 0, 3))) . "</td>\n";

		if ($llent[0] == "login")
			echo "<td class=\"listr\"><img src=\"/themes/{$g['theme']}/images/icons/icon_in.gif\" width=\"11\" height=\"11\" title=\"login\" alt=\"in\" /></td>\n";
		else
			echo "<td class=\"listr\"><img src=\"/themes/{$g['theme']}/images/icons/icon_out.gif\" width=\"11\" height=\"11\" title=\"logout\" alt=\"out\" /></td>\n";

		echo "<td class=\"listr\">" . htmlspecialchars($llent[3]) . "</td>\n";
		echo "<td class=\"listr\">" . htmlspecialchars($llent[2]) . "&nbsp;</td>\n";
		echo "</tr>\n";
	}
}

include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="logs vpn">
  <tr><td class="tabnavtbl">
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("System"), false, "diag_logs.php");
	$tab_array[] = array(gettext("Firewall"), false, "diag_logs_filter.php");
	$tab_array[] = array(gettext("DHCP"), false, "diag_logs_dhcp.php");
	$tab_array[] = array(gettext("Portal Auth"), false, "diag_logs_auth.php");
	$tab_array[] = array(gettext("IPsec"), false, "diag_logs_ipsec.php");
	$tab_array[] = array(gettext("PPP"), false, "diag_logs_ppp.php");
	$tab_array[] = array(gettext("VPN"), true, "diag_logs_vpn.php");
	$tab_array[] = array(gettext("Load Balancer"), false, "diag_logs_relayd.php");
	$tab_array[] = array(gettext("OpenVPN"), false, "diag_logs_openvpn.php");
	$tab_array[] = array(gettext("NTP"), false, "diag_logs_ntpd.php");
	$tab_array[] = array(gettext("Settings"), false, "diag_logs_settings.php");
	display_top_tabs($tab_array);
?>
  </td></tr>
  <tr><td class="tabnavtbl">
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("PPTP Logins"),
				(($vpntype == "pptp") && ($mode != "raw")),
				"/diag_logs_vpn.php?vpntype=pptp");
	$tab_array[] = array(gettext("PPTP Raw"),
				(($vpntype == "pptp") && ($mode == "raw")),
				"/diag_logs_vpn.php?vpntype=pptp&amp;mode=raw");
	$tab_array[] = array(gettext("PPPoE Logins"),
				(($vpntype == "poes") && ($mode != "raw")),
				"/diag_logs_vpn.php?vpntype=poes");
	$tab_array[] = array(gettext("PPPoE Raw"),
				(($vpntype == "poes") && ($mode == "raw")),
				"/diag_logs_vpn.php?vpntype=poes&amp;mode=raw");
	$tab_array[] = array(gettext("L2TP Logins"),
				(($vpntype == "l2tp") && ($mode != "raw")),
				"/diag_logs_vpn.php?vpntype=l2tp");
	$tab_array[] = array(gettext("L2TP Raw"),
				(($vpntype == "l2tp") && ($mode == "raw")),
				"/diag_logs_vpn.php?vpntype=l2tp&amp;mode=raw");
	display_top_tabs($tab_array);
?>
  </td></tr>
  <tr>
    <td class="tabcont">
	<form action="diag_logs_vpn.php" method="post">
	<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="main area">
		<tr>
		<td colspan="4" class="listtopic">
			<?php printf(gettext('Last %1$s %2$s VPN log entries'),$nentries,$vpns[$vpntype]);?></td>
		</tr>
		<?php if ($mode != "raw"): ?>
		<tr>
			<td class="listhdrr"><?=gettext("Time");?></td>
			<td class="listhdrr"><?=gettext("Action");?></td>
			<td class="listhdrr"><?=gettext("User");?></td>
			<td class="listhdrr"><?=gettext("IP address");?></td>
		</tr>
			<?php dump_clog_vpn("/var/log/vpn.log", $nentries); ?>
		<?php else: 
			dump_clog("/var/log/{$logname}.log", $nentries);
		      endif; ?>
	</table>
	<br />
	<input type="hidden" name="vpntype" id="vpntype" value="<?=$vpntype;?>" />
	<input type="hidden" name="mode" id="mode" value="<?=$mode;?>" />
	<input name="clear" type="submit" class="formbtn" value="<?=gettext("Clear log"); ?>" />
	</form>
	</td>
  </tr>
</table>
<?php include("fend.inc"); ?>
</body>
</html>
