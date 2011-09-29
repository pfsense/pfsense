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
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<script src="/javascript/scriptaculous/prototype.js" type="text/javascript"></script>
<script src="/javascript/scriptaculous/scriptaculous.js" type="text/javascript"></script>
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
		var line = '';
		line =  '  <span class="log-action" nowrap>' + row[0] + '</span>';
		line += '  <span class="log-time" nowrap>' + row[1] + '</span>';
		line += '  <span class="log-interface" nowrap>' + row[2] + '</span>';
		line += '  <span class="log-source" nowrap>' + row[3] + '</span>';
		line += '  <span class="log-destination" nowrap>' + row[4] + '</span>';
		line += '  <span class="log-protocol" nowrap>' + row[5] + '</span>';
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
	$tab_array[] = array(gettext("OpenNTPD"), false, "diag_logs_ntpd.php");
	$tab_array[] = array(gettext("Wireless"), false, "diag_logs_wireless.php");
	$tab_array[] = array(gettext("Settings"), false, "diag_logs_settings.php");
	display_top_tabs($tab_array);
?>
 </td></tr>
  <tr>
     <td>
	<div id="mainarea">
		<div class="tabcont">
			<a href="diag_logs_filter.php"><?=gettext("Normal View");?></a> | <?=gettext("Dynamic View");?> | <a href="diag_logs_filter_summary.php"><?=gettext("Summary View");?></a>
		</div>
		<div class="listtopic">
			<?php printf(gettext("Last %s records"),$nentries);?>;   <?=gettext("Pause:");?><input valign="middle" type="checkbox" onClick="javascript:toggle_pause();">
		</div>
		<div id="log">
			<div class="log-header">
                                <span class="log-action"><?=gettext("Act");?></span>
                                <span class="log-time"><?=gettext("Time");?></span>
                                <span class="log-interface"><?=gettext("If");?></span>
                                <span class="log-source"><?=gettext("Source");?></span>
                                <span class="log-destination"><?=gettext("Destination");?></span>
                                <span class="log-protocol"><?=gettext("Proto");?></span>
			</div>
			<?php $counter=0; foreach ($filterlog as $filterent): ?>
			<div class="log-entry"<?php echo is_first_row($counter, count($filterlog)); ?>>
				<span class="log-action" nowrap><a href="#" onClick="javascript:getURL('diag_logs_filter.php?getrulenum=<?php echo "{$filterent['rulenum']},{$filterent['act']}"; ?>', outputrule);">
				<img border="0" src="<?php echo find_action_image($filterent['act']);?>" width="11" height="11" align="absmiddle" alt="<?php echo $filterent['act'];?>" title="<?php echo $filterent['act'];?>" /></a></span>
				<span class="log-time" ><?php echo htmlspecialchars($filterent['time']);?></span>
				<span class="log-interface" ><?php echo htmlspecialchars($filterent['interface']);?></span>
				<span class="log-source" ><?php echo htmlspecialchars($filterent['src']);?></span>
				<span class="log-destination" ><?php echo htmlspecialchars($filterent['dst']);?></span>
				<?php
					if ($filterent['proto'] == "TCP")
						$filterent['proto'] .= ":{$filterent['tcpflags']}";
				?>
				<span class="log-protocol" ><?php echo htmlspecialchars($filterent['proto']);?></span>
			</div>
			<?php $counter++; endforeach; ?>
		</div>
	</div>
     </td>
  </tr>
</table>
<p><span class="vexpl"><a href="http://doc.pfsense.org/index.php/What_are_TCP_Flags%3F"><?=gettext("TCP Flags"); ?></a>: F - FIN, S - SYN, A or . - ACK, R - RST, P - PSH, U - URG, E - ECE, C - CWR</span></p>

<?php include("fend.inc"); ?>
</body>
</html>
