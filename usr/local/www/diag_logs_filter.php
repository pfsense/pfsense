<?php
/* $Id$ */
/*
	diag_logs_filter.php
	part of pfSense 
	Copyright (C) 2004-2009 Scott Ullrich
	originally based on m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2003-2009 Manuel Kasper <mk@neon1.net>,
	Jim Pingle jim@pingle.org
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
	pfSense_MODULE:	filter
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
	echo gettext("The rule that triggered this action is") . ":\n\n{$rule}";
	exit;
}

if($_GET['dnsip'] or $_POST['dnsip']) {
	if($_GET['dnsip'])
		$dnsip = $_GET['dnsip'];
	if($_POST['dnsip'])
		$dnsip = $_POST['dnsip'];
	$host = get_reverse_dns($dnsip);
	if ($host == $ip) {
		$host = "No PTR Record";
	}
	echo "IP: {$dnsip}\nHost: {$host}";
	exit;
}

$filtertext = "";
if($_GET['filtertext'] or $_POST['filtertext']) {
	if($_GET['filtertext'])
		$filtertext = htmlspecialchars($_GET['filtertext']);
	if($_POST['filtertext'])
		$filtertext = htmlspecialchars($_POST['filtertext']);
}

$filter_logfile = "{$g['varlog_path']}/filter.log";

$nentries = $config['syslog']['nentries'];
if (!$nentries)
	$nentries = 50;

if ($_POST['clear'])
	clear_log_file($filter_logfile);

$pgtitle = array(gettext("Status"),gettext("System logs"),gettext("Firewall"));
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<script src="/javascript/filter_log.js" type="text/javascript"></script>
<?php include("fbegin.inc"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("System"), false, "diag_logs.php");
	$tab_array[] = array(gettext("Firewall"), true, "diag_logs_filter.php");
	$tab_array[] = array(gettext("DHCP"), false, "diag_logs_dhcp.php");
	$tab_array[] = array(gettext("Portal Auth"), false, "diag_logs_auth.php");
	$tab_array[] = array(gettext("IPsec"), false, "diag_logs_ipsec.php");
	$tab_array[] = array(gettext("PPP"), false, "diag_logs_ppp.php");
	$tab_array[] = array(gettext("VPN"), false, "diag_logs_vpn.php");
	$tab_array[] = array(gettext("Load Balancer"), false, "diag_logs_relayd.php");
	$tab_array[] = array(gettext("OpenVPN"), false, "diag_logs_openvpn.php");
	$tab_array[] = array(gettext("OpenNTPD"), false, "diag_logs_ntpd.php");
	$tab_array[] = array(gettext("Settings"), false, "diag_logs_settings.php");
	display_top_tabs($tab_array);
?>
 </td></tr>
  <tr>
    <td>
	<div id="mainarea">
		<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr><td colspan="6" align="left">
			<?=gettext("Normal View");?> | <a href="diag_logs_filter_dynamic.php"><?=gettext("Dynamic View");?></a> | <a href="diag_logs_filter_summary.php"><?=gettext("Summary View");?></a><br/><br/>
		</td></tr>
<?php if (!isset($config['syslog']['rawfilter'])):
	$filterlog = conv_log_filter($filter_logfile, $nentries, $nentries + 100, $filtertext);
?>
		<tr>
		  <td colspan="6" class="listtopic">
				<?php if (!$filtertext) { ?>
				<?php printf(gettext("Last %s firewall log entries."),count($filterlog));?>
				<?php } else { ?>
				<?php echo count($filterlog). ' ' . gettext("matched log entries."); ?>
				<?php } ?>
			    	<?php printf(gettext("Max(%s)"),$nentries);?>
			</tr>
			<tr>
			  <td width="10%" class="listhdrr"><?=gettext("Act");?></td>
			  <td width="10%" class="listhdrr"><?=gettext("Time");?></td>
			  <td width="15%" class="listhdrr"><?=gettext("If");?></td>
			  <td width="25%" class="listhdrr"><?=gettext("Source");?></td>
			  <td width="25%" class="listhdrr"><?=gettext("Destination");?></td>
			  <td width="15%" class="listhdrr"><?=gettext("Proto");?></td>
			</tr><?php foreach ($filterlog as $filterent): ?>
			<tr>
			  <td class="listlr" nowrap align="middle">
			  <center>
			  <a href="#" onClick="javascript:getURL('diag_logs_filter.php?getrulenum=<?php echo "{$filterent['rulenum']},{$filterent['act']}"; ?>', outputrule);">
			  <img border="0" src="<?php echo find_action_image($filterent['act']);?>" width="11" height="11" align="absmiddle" alt="<?php echo $filterent['act'];?>" title="<?php echo $filterent['act'];?>" />
			  <?php if ($filterent['count']) echo $filterent['count'];?></td>
			  <td class="listr" nowrap><?php echo htmlspecialchars($filterent['time']);?></td>
			  <td class="listr" nowrap><?php echo htmlspecialchars($filterent['interface']);?></td>
			  <?php
			  $int = strtolower($filterent['interface']);
			  $proto = strtolower($filterent['proto']);
			  if(is_ipaddrv6($filterent['srcip'])) {
				$ipproto = "inet6";
				$filterent['srcip'] = "[{$filterent['srcip']}]";
				$filterent['dstip'] = "[{$filterent['dstip']}]";
			  } else {
			        $ipproto = "inet";
			  }

			  $srcstr = $filterent['srcip'] . get_port_with_service($filterent['srcport'], $proto);
			  $dststr = $filterent['dstip'] . get_port_with_service($filterent['dstport'], $proto);
			  ?>
			  <td class="listr" nowrap>
				<a href="diag_dns.php?host=<?php echo $filterent['srcip']; ?>" title="<?=gettext("Reverse Resolve with DNS");?>"><img border="0" src="/themes/<?= $g['theme']; ?>/images/icons/icon_log.gif"></a>
				<a href="easyrule.php?<?php echo "action=block&int={$int}&src={$filterent['srcip']}&ipproto={$ipproto}"; ?>" title="<?=gettext("Easy Rule: Add to Block List");?>" onclick="return confirm('<?=gettext("Do you really want to add this BLOCK rule?")."\n\n".gettext("Easy Rule is still experimental.")."\n".gettext("Continue at risk of your own peril.")."\n".gettext("Backups are also nice.")?>')"><img border="0" src="/themes/<?= $g['theme']; ?>/images/icons/icon_block_add.gif"></a>
				<?php echo $srcstr;?>
			  </td>
			  <td class="listr" nowrap>
				<a href="diag_dns.php?host=<?php echo $filterent['dstip']; ?>" title="<?=gettext("Reverse Resolve with DNS");?>"><img border="0" src="/themes/<?= $g['theme']; ?>/images/icons/icon_log.gif"></a>
				<a href="easyrule.php?<?php echo "action=pass&int={$int}&proto={$proto}&src={$filterent['srcip']}&dst={$filterent['dstip']}&dstport={$filterent['dstport']}&ipproto={$ipproto}"; ?>" title="<?=gettext("Easy Rule: Pass this traffic");?>" onclick="return confirm('<?=gettext("Do you really want to add this PASS rule?")."\n\n".gettext("Easy Rule is still experimental.")."\n".gettext("Continue at risk of your own peril.")."\n".gettext("Backups are also nice.");?>')"><img border="0" src="/themes/<?= $g['theme']; ?>/images/icons/icon_pass_add.gif"></a>
				<?php echo $dststr;?>
			  </td>
			  <?php
				if ($filterent['proto'] == "TCP")
					$filterent['proto'] .= ":{$filterent['tcpflags']}";
			  ?>
			  <td class="listr" nowrap><?php echo htmlspecialchars($filterent['proto']);?></td>
			</tr><?php endforeach; ?>
<?php else: ?>
		  <tr>
			<td colspan="2" class="listtopic">
			  <?php printf(gettext("Last %s firewall log entries"),$nentries);?></td>
		  </tr>
		  <?php
			if($filtertext)
				dump_clog($filter_logfile, $nentries, true, array("$filtertext"));
			else
				dump_clog($filter_logfile, $nentries);
		  ?>
<?php endif; ?>
		<tr>
			<td align="left" valign="top" colspan="3">
				<form id="clearform" name="clearform" action="diag_logs_filter.php" method="post" style="margin-top: 14px;">
					<input id="submit" name="clear" type="submit" class="formbtn" value="<?=gettext("Clear log");?>" />
				</form>
			</td>
			<td align="right" valign="top" colspan="3">
				<form id="filterform" name="filterform" action="diag_logs_filter.php" method="post" style="margin-top: 14px;">
					<input id="filtertext" name="filtertext" value="<?=gettext($filtertext);?>" />
					<input id="filtersubmit" name="filtersubmit" type="submit" class="formbtn" value="<?=gettext("Filter");?>" />
				</form>
			</td>

		</tr>
		</table>
		</div>
	</td>
  </tr>
</table>

<p><span class="vexpl"><a href="http://doc.pfsense.org/index.php/What_are_TCP_Flags%3F">TCP Flags</a>: F - FIN, S - SYN, A or . - ACK, R - RST, P - PSH, U - URG, E - ECE, C - CWR</span></p>

<?php include("fend.inc"); ?>
</body>
</html>
