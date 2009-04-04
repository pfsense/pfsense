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

##|+PRIV
##|*IDENT=page-diagnostics-logs-firewall
##|*NAME=Diagnostics: Logs: Firewall page
##|*DESCR=Allow access to the 'Diagnostics: Logs: Firewall' page.
##|*MATCH=diag_logs_filter.php*
##|-PRIV

require("guiconfig.inc");
require_once("filter_log.inc");

if($_GET['getrulenum'] or $_POST['getrulenum']) {
	if($_GET['getrulenum'])
		$rulenum = $_GET['getrulenum'];
	if($_POST['getrulenum'])
		$rulenum = $_POST['getrulenum'];
	list($rulenum, $type) = explode(',', $rulenum);
	$rule = find_rule_by_number($rulenum, $type);
	echo "The rule that triggered this action is:\n\n{$rule}";
	exit;
}

$filter_logfile = "{$g['varlog_path']}/filter.log";

$nentries = $config['syslog']['nentries'];
if (!$nentries)
	$nentries = 50;

if ($_POST['clear']) 
	clear_log_file($filter_logfile);

$pgtitle = array("Status","System logs","Firewall");
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<script src="/javascript/filter_log.js" type="text/javascript"></script>
<?php include("fbegin.inc"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
<?php
	$tab_array = array();
	$tab_array[] = array("System", false, "diag_logs.php");
	$tab_array[] = array("Firewall", true, "diag_logs_filter.php");
	$tab_array[] = array("DHCP", false, "diag_logs_dhcp.php");
	$tab_array[] = array("Portal Auth", false, "diag_logs_auth.php");
	$tab_array[] = array("IPsec VPN", false, "diag_logs_ipsec.php");
	$tab_array[] = array("PPTP VPN", false, "diag_logs_vpn.php");
	$tab_array[] = array("Load Balancer", false, "diag_logs_relayd.php");
	$tab_array[] = array("OpenVPN", false, "diag_logs_openvpn.php");
	$tab_array[] = array("OpenNTPD", false, "diag_logs_ntpd.php");
	$tab_array[] = array("Settings", false, "diag_logs_settings.php");
	display_top_tabs($tab_array);
?>
 </td></tr>
  <tr>
    <td>
	<div id="mainarea">
		<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
<?php if (!isset($config['syslog']['rawfilter'])):
	$filterlog = conv_log_filter($filter_logfile, $nentries, $nentries + 100);
?>
		<tr>
		  <td colspan="6" class="listtopic">
			    Last <?php echo $nentries;?> firewall log entries &nbsp;&nbsp;&nbsp;(<a href='/diag_logs_filter_dynamic.php'>switch</a> to dynamic view)</td>
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
			  <center>
			  <a href="#" onClick="javascript:getURL('diag_logs_filter.php?getrulenum=<?php echo "{$filterent['rulenum']},{$filterent['act']}"; ?>', outputrule);">
			  <img border="0" src="<?php echo find_action_image($filterent['act']);?>" width="11" height="11" align="absmiddle" alt="<?php echo $filterent['act'];?>" title="<?php echo $filterent['act'];?>" />
			  <?php if ($filterent['count']) echo $filterent['count'];?></td>
			  <td class="listr" nowrap><?php echo htmlspecialchars($filterent['time']);?></td>
			  <td class="listr" nowrap><?php echo htmlspecialchars($filterent['interface']);?></td>
			  <td class="listr" nowrap><?php echo htmlspecialchars($filterent['src']);?></td>
			  <td class="listr" nowrap><?php echo htmlspecialchars($filterent['dst']);?></td>
			  <?php
				if ($filterent['proto'] == "TCP")
					$filterent['proto'] .= ":{$filterent['tcpflags']}";
			  ?>
			  <td class="listr" nowrap><?php echo htmlspecialchars($filterent['proto']);?></td>
			</tr><?php endforeach; ?>
<?php else: ?>
		  <tr>
			<td colspan="2" class="listtopic">
			  Last <?php echo $nentries;?> firewall log entries</td>
		  </tr>
		  <?php dump_clog($filter_logfile, $nentries); ?>
<?php endif; ?>
		<tr><td><br /><form action="diag_logs_filter.php" method="post">
<input name="clear" type="submit" class="formbtn" value="Clear log" /></td></tr>
</form>
		</table>
		</div>
	</td>
  </tr>
</table>

<p><span class="vexpl"><a href="http://doc.pfsense.org/index.php/What_are_TCP_Flags%3F">TCP Flags</a>: F - FIN, S - SYN, A or . - ACK, R - RST, P - PSH, U - URG, E - ECE, C - CWR</span></p>

<?php include("fend.inc"); ?>
</body>
</html>
