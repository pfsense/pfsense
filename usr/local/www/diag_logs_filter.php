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

if($_GET['getrulenum'] or $_POST['getrulenum']) {
	if($_GET['getrulenum'])
		$rulenum = escapeshellarg($_GET['getrulenum']);
	if($_POST['getrulenum'])
		$rulenum = escapeshellarg($_POST['getrulenum']);
	$rule = `pfctl -vvsr | grep @{$rulenum}`;
	echo "The rule that triggered this action is:\n\n{$rule}";
	exit;
}

$filter_logfile = "{$g['varlog_path']}/filter.log";

$nentries = $config['syslog']['nentries'];
if (!$nentries)
	$nentries = 50;

if ($_POST['clear']) {
	if(isset($config['system']['disablesyslogclog'])) {
		unlink("/var/log/filter.log");
		touch("/var/log/filter.log");
	} else {	
		exec("killall syslogd");
		exec("/usr/sbin/clog -i -s 512144 /var/log/filter.log");
		system_syslogd_start();
	}
}

/* format filter logs */
function conv_clog($logfile, $tail = 50) {
	global $config, $nentries, $g;
	$logarr = "";
	/* make interface/port table */
	$iftable = array();
	$iflist = get_configured_interface_with_descr();
	foreach ($iflist as $if => $ifdesc)
		$iftable[get_real_interface($if)] = $ifdesc;

	$sor = isset($config['syslog']['reverse']) ? "-r" : "";

	exec("/usr/sbin/clog {$logfile} | /usr/bin/tail {$sor} -n 500", $logarr);

	$filterlog = array();

	$counter = 1;

	foreach ($logarr as $logent) {

		if($counter > $nentries)
			break;

		$log_split = "";


		preg_match("/(\b(?:\d{1,3}\.){3}\d{1,3}(\.\w+)?)\s.*\s(\b(?:\d{1,3}\.){3}\d{1,3}(\.\w+)?)/", $logent, $log_split);

		$flent['src'] 		= convert_port_period_to_colon($log_split[1]);
		$flent['dst'] 		= convert_port_period_to_colon($log_split[3]);

		preg_match("/(.*)\s.*\spf:\s.*\srule\s(.*)\(match\)\:\s(.*)\s\w+\son\s(\w+)\:\s(.*)\s>\s(.*)\:\s.*/", $logent, $log_split);

		$beforeupper = $logent;
		$logent = strtoupper($logent);

		if(stristr(strtoupper($logent), "UDP") == true)
			$flent['proto'] = "UDP";
		else if(stristr(strtoupper($logent), "TCP") == true)
			$flent['proto'] = "TCP";
		else if(stristr(strtoupper($logent), "ICMP") == true)
			$flent['proto'] = "ICMP";
		else if(stristr(strtoupper($logent), "HSRP") == true)
			$flent['proto'] = "HSRP";
		else if(stristr(strtoupper($logent), "ESP") == true)
			$flent['proto'] = "ESP";
		else if(stristr(strtoupper($logent), "AH") == true)
			$flent['proto'] = "AH";
		else if(stristr(strtoupper($logent), "GRE") == true)
			$flent['proto'] = "GRE";
		else if(stristr(strtoupper($logent), "IGMP") == true)
			$flent['proto'] = "IGMP";
		else if(stristr(strtoupper($logent), "CARP") == true)
			$flent['proto'] = "CARP";
		else if(stristr(strtoupper($logent), "VRRP") == true)
			$flent['proto'] = "VRRP";
		else if(stristr(strtoupper($logent), "PFSYNC") == true)
			$flent['proto'] = "PFSYNC";
		else if(stristr($logent, "sack") == true)
			$flent['proto'] = "TCP";
		else
			$flent['proto'] = "TCP";

		$flent['time'] 		= $log_split[1];
		$flent['act'] 		= $log_split[3];

		$flent['interface'] = convert_real_interface_to_friendly_descr($log_split[4]);

		$tmp = split("/", $log_split[2]);
		$flent['rulenum'] = $tmp[0];

		$shouldadd = true;

		if(trim($flent['src']) == "")
			$shouldadd = false;
		if(trim($flent['dst']) == "")
			$shouldadd = false;
		if(trim($flent['time']) == "")
			$shouldadd = false;

		if($shouldadd == true) {
			$counter++;
			$filterlog[] = $flent;
		} else {
			if($g['debug']) {
				log_error("There was a error parsing rule: $beforeupper .   Please report to mailing list or forum.");
			}
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
	if($newvar == "...")
		return $addr;
	return $newvar;
}

function format_ipf_ip($ipfip) {
	list($ip,$port) = explode(",", $ipfip);
	if (!$port)
		return $ip;

	return $ip . ", port " . $port;
}

$pgtitle = array("Status","System logs","Firewall");
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
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
	$filterlog = conv_clog($filter_logfile, $nentries);
?>
		<tr>
		  <td colspan="6" class="listtopic">
			    Last <?=$nentries;?> firewall log entries &nbsp;&nbsp;&nbsp;(<a href='/diag_logs_filter_dynamic.php'>switch</a> to dynamic view)</td>
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
			  <a href="#" onClick="javascript:getURL('diag_logs_filter.php?getrulenum=<?php echo $filterent['rulenum']; ?>', outputrule);">
			  <?php if (strstr(strtolower($filterent['act']), "p"))
			  			$img = "/themes/{$g['theme']}/images/icons/icon_pass.gif";
					else if(strstr(strtolower($filterent['act']), "r"))
					 	$img = "/themes/{$g['theme']}/images/icons/icon_reject.gif";
					else
						$img = "/themes/{$g['theme']}/images/icons/icon_block.gif";
			  ?>
			  <img border="0" src="<?=$img;?>" width="11" height="11" align="absmiddle">
			  <?php if ($filterent['count']) echo $filterent['count'];?></td>
			  <td class="listr" nowrap><?=htmlspecialchars($filterent['time']);?></td>
			  <td class="listr" nowrap><?=htmlspecialchars($filterent['interface']);?></td>
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
<script language="javascript">
if (typeof getURL == 'undefined') {
	getURL = function(url, callback) {
		if (!url)
			throw 'No URL for getURL';
		try {
			if (typeof callback.operationComplete == 'function')
				callback = callback.operationComplete;
		} catch (e) {}
			if (typeof callback != 'function')
				throw 'No callback function for getURL';
		var http_request = null;
		if (typeof XMLHttpRequest != 'undefined') {
		    http_request = new XMLHttpRequest();
		}
		else if (typeof ActiveXObject != 'undefined') {
			try {
				http_request = new ActiveXObject('Msxml2.XMLHTTP');
			} catch (e) {
				try {
					http_request = new ActiveXObject('Microsoft.XMLHTTP');
				} catch (e) {}
			}
		}
		if (!http_request)
			throw 'Both getURL and XMLHttpRequest are undefined';
		http_request.onreadystatechange = function() {
			if (http_request.readyState == 4) {
				callback( { success : true,
				  content : http_request.responseText,
				  contentType : http_request.getResponseHeader("Content-Type") } );
			}
		}
		http_request.open('GET', url, true);
		http_request.send(null);
	}
}

function outputrule(req) {
	alert(req.content);
}
</script>
<?php include("fend.inc"); ?>
</body>
</html>
