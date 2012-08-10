<?php
/* $Id$ */
/*
	diag_logs_gateways.php
	Copyright (C) 2004-2009 Scott Ullrich
	Copyright (C) 2012 Seth Mos
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
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

/*		
	pfSense_MODULE:	system
*/

##|+PRIV
##|*IDENT=page-diagnostics-logs-gateways
##|*NAME=Diagnostics: Logs: System: Gateways page
##|*DESCR=Allow access to the 'Diagnostics: Logs: System: Gateways' page.
##|*MATCH=diag_logs_gateways.php*
##|-PRIV

require("guiconfig.inc");

$system_logfile = "{$g['varlog_path']}/gateways.log";

$nentries = $config['syslog']['nentries'];
if (!$nentries)
	$nentries = 50;

if ($_POST['clear']) 
	clear_log_file($system_logfile);

if ($_GET['filtertext'])
	$filtertext = htmlspecialchars($_GET['filtertext']);

if ($_POST['filtertext'])
	$filtertext = htmlspecialchars($_POST['filtertext']);

if ($filtertext)
	$filtertextmeta="?filtertext=$filtertext";

$pgtitle = array(gettext("Status"),gettext("System logs"),gettext("Gateways"));
$shortcut_section = "gateways";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td>
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("System"), true, "diag_logs.php");
	$tab_array[] = array(gettext("Firewall"), false, "diag_logs_filter.php");
	$tab_array[] = array(gettext("DHCP"), false, "diag_logs_dhcp.php");
	$tab_array[] = array(gettext("Portal Auth"), false, "diag_logs_auth.php");
	$tab_array[] = array(gettext("IPsec"), false, "diag_logs_ipsec.php");
	$tab_array[] = array(gettext("PPP"), false, "diag_logs_ppp.php");
	$tab_array[] = array(gettext("VPN"), false, "diag_logs_vpn.php");
	$tab_array[] = array(gettext("Load Balancer"), false, "diag_logs_relayd.php");
	$tab_array[] = array(gettext("OpenVPN"), false, "diag_logs_openvpn.php");
	$tab_array[] = array(gettext("NTP"), false, "diag_logs_ntpd.php");
	$tab_array[] = array(gettext("Settings"), false, "diag_logs_settings.php");
	display_top_tabs($tab_array);
?>
		</td>
	</tr>
  <tr><td class="tabnavtbl">
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("General"), false, "/diag_logs.php");
	$tab_array[] = array(gettext("Gateways"), true, "/diag_logs_gateways.php");
	$tab_array[] = array(gettext("Routing"), false, "/diag_logs_routing.php");
	$tab_array[] = array(gettext("Resolver"), false, "/diag_logs_resolver.php");
	$tab_array[] = array(gettext("Wireless"), false, "/diag_logs_wireless.php");
	display_top_tabs($tab_array);
?>
		</td>
	</tr>
	<tr>
		<td>
			<div id="mainarea">
			<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0">
				<tr>
					<td colspan="2" class="listtopic"><?php printf(gettext("Last %s system log entries"),$nentries); ?></td>
				</tr>
				<?php
					if($filtertext)
						dump_clog($system_logfile, $nentries, true, array("$filtertext"));
					else
						dump_clog($system_logfile, $nentries, true, array());
				?>
				<tr>
					<td align="left" valign="top">
						<form id="filterform" name="filterform" action="diag_logs_gateways.php" method="post" style="margin-top: 14px;">
              				<input id="submit" name="clear" type="submit" class="formbtn" value="<?=gettext("Clear log");?>" />
						</form>
					</td>
					<td align="right" valign="top" >
						<form id="clearform" name="clearform" action="diag_logs_gateways.php" method="post" style="margin-top: 14px;">
              				<input id="filtertext" name="filtertext" value="<?=$filtertext;?>" />
              				<input id="filtersubmit" name="filtersubmit" type="submit" class="formbtn" value="<?=gettext("Filter");?>" />
						</form>
					</td>
				</tr>
			</table>
	    	</div>
		</td>
	</tr>
</table>
<?php include("fend.inc"); ?>
</body>
</html>
