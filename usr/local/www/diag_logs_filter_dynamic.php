<?php
/* $Id$ */
/*
	diag_logs_filter.php
	part of pfSesne
	Copyright (C) 2004-2009 Scott Ullrich
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

/*	
	pfSense_MODULE:	filter
*/

##|+PRIV
##|*IDENT=page-hidden-nolongerincluded
##|*NAME=Hidden: No longer included page
##|*DESCR=Allow access to the 'Hidden: No longer included' page.
##|*MATCH=diag_logs_filter_dynamic.php*
##|-PRIV

require("guiconfig.inc");
require_once("filter_log.inc");

$filter_logfile = "{$g['varlog_path']}/filter.log";

/* Hardcode this. AJAX doesn't do so well with large numbers */
$nentries = 50;

/* AJAX related routines */
handle_ajax($nentries, $nentries + 20);

if ($_POST['clear']) 
	clear_log_file($filter_logfile);

$filterlog = conv_log_filter($filter_logfile, $nentries, $nentries + 100);

$pgtitle = array(gettext("Status"),gettext("System logs"),gettext("Firewall (Dynamic View)"));
$shortcut_section = "firewall";
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<script language="javascript">
	lastsawtime = '<?php echo time(); ?>;';
	var lines = Array();
	var timer;
	var updateDelay = 25500;
	var isBusy = false;
	var isPaused = false;
	var nentries = <?php echo $nentries; ?>;
<?php
	if(isset($config['syslog']['reverse']))
		echo "var isReverse = true;\n";
	else
		echo "var isReverse = false;\n";
?>
	/* Called by the AJAX updater */
	function format_log_line(row) {
		var i = 0;
		var line = '<td class="listMRlr" nowrap="nowrap" align="center">' + row[i++] + '</td>';
		while (i < 6) {
			line += '<td class="listMRr" nowrap="nowrap">' + row[i++] + '</td>';
		}
		return line;
	}
</script>
<script src="/javascript/filter_log.js" type="text/javascript"></script>
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
	$tab_array[] = array(gettext("NTP"), false, "diag_logs_ntpd.php");
	$tab_array[] = array(gettext("Settings"), false, "diag_logs_settings.php");
	display_top_tabs($tab_array);
?>
 </td></tr>
  <tr>
     <td>
	<div id="mainarea">
		<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
			<thead>
			<tr>
				<td colspan="6" align"left" valign="middle">
				<a href="diag_logs_filter.php"><?=gettext("Normal View");?></a> | <?=gettext("Dynamic View");?> | <a href="diag_logs_filter_summary.php"><?=gettext("Summary View");?></a>
				<br/><br/>
				</td>
			</tr>
			<tr>
				<td colspan="6" class="listtopic">
				<?php printf(gettext("Last %s records"),$nentries);?>;   <?=gettext("Pause:");?><input valign="middle" type="checkbox" onClick="javascript:toggle_pause();">
				</td>
			</tr>
			<tr>
				<td width="10%" class="listhdrr"><?=gettext("Act");?></ td>
				<td width="10%" class="listhdrr"><?=gettext("Time");?></ td>
				<td width="15%" class="listhdrr"><?=gettext("If");?></ td>
				<td width="25%" class="listhdrr"><?=gettext("Source");?></ td>
				<td width="25%" class="listhdrr"><?=gettext("Destination");?></ td>
				<td width="15%" class="listhdrr"><?=gettext("Proto");?></ td>
			</tr>
			</thead>
			<tbody id="filter-log-entries">
			<?php
			$rowIndex = 0;
			foreach ($filterlog as $filterent):
			$evenRowClass = $rowIndex % 2 ? " listMReven" : " listMRodd";
			$rowIndex++;?>
			<tr class="<?=$evenRowClass?>">
				<td class="listMRlr" nowrap="nowrap" align="center">
				<a href="#" onclick="javascript:getURL('diag_logs_filter.php?getrulenum=<?php echo "{$filterent['rulenum']},{$filterent['act']}"; ?>', outputrule);">
				<img border="0" src="<?php echo find_action_image($filterent['act']);?>" width="11" height="11" alt="<?php echo $filterent['act'];?>" title="<?php echo $filterent['act'];?>" />
				</a>
				</td>
				<td class="listMRr" nowrap="nowrap"><?php echo htmlspecialchars($filterent['time']);?></td>
				<td class="listMRr" nowrap="nowrap"><?php echo htmlspecialchars($filterent['interface']);?></td>
				<td class="listMRr" nowrap="nowrap"><?php echo htmlspecialchars($filterent['src']);?></td>
				<td class="listMRr" nowrap="nowrap"><?php echo htmlspecialchars($filterent['dst']);?></td>
				<?php
					if ($filterent['proto'] == "TCP")
						$filterent['proto'] .= ":{$filterent['tcpflags']}";
				?>
				<td class="listMRr" nowrap="nowrap"><?php echo htmlspecialchars($filterent['proto']);?></td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
     </td>
  </tr>
</table>
<p><span class="vexpl"><a href="https://doc.pfsense.org/index.php/What_are_TCP_Flags%3F"><?=gettext("TCP Flags"); ?></a>: F - FIN, S - SYN, A or . - ACK, R - RST, P - PSH, U - URG, E - ECE, C - CWR</span></p>
<?php include("fend.inc"); ?>
</body>
</html>
