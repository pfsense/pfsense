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

/* AJAX related routines */
handle_ajax();

$nentries = $config['syslog']['nentries'];
if (!$nentries)
	$nentries = 50;

if ($_POST['clear']) {
	exec("killall syslogd");
	exec("/usr/sbin/clog -i -s 262144 /var/log/filter.log");
	system_syslogd_start();
}

/* format filter logs */
function conv_clog_filter($logfile, $tail = 50) {
	global $config, $nentries, $logfile;

	$logfile = "/var/log/filter.log";

	/* make interface/port table */
	$iftable = array();
	$iftable[$config['interfaces']['lan']['if']] = "LAN";
	$iftable[get_real_wan_interface()] = "WAN";
	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++)
		$iftable[$config['interfaces']['opt' . $i]['if']] = $config['interfaces']['opt' . $i]['descr'];

	$sor = isset($config['syslog']['reverse']) ? "-r" : "";

	$logarr = "";
	exec("/usr/sbin/clog {$logfile} | /usr/bin/tail {$sor} -n {$tail}", $logarr);

	$filterlog = array();

	$counter = 0;

	foreach ($logarr as $logent) {

		if($counter > $nentries)
			break;

		$log_split = "";
		
		//old reg ex
		//preg_match("/(.*)\s.*\spf:\s.*\srule\s(.*)\(match\)\:\s(.*)\s\w+\son\s(\w+)\:\s(.*)\s>\s(.*)\:\s.*/", $logent, $log_split);
		
		preg_match("/(.*)\s.*\spf:\s.*\srule\s(.*)\(match\)\:\s(.*)\s\w+\son\s(\w+)\:\s.*\slength\:.*\s(.*)\s>\s(.*)\:\s.*/", $logent, $log_split);

		$logent = strtoupper($logent);

		$do_not_display = false;

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
		else if(stristr(strtoupper($logent), "PFSYNC") == true)
			$flent['proto'] = "PFSYNC";
		else
			$flent['proto'] = "TCP";

		$flent['time'] 		= $log_split[1];
		$flent['act'] 		= $log_split[3];

		$friendly_int = convert_real_interface_to_friendly_interface_name($log_split[4]);

		$flent['interface'] 	=  strtoupper($friendly_int);

		if($config['interfaces'][$friendly_int]['descr'] <> "")
			$flent['interface'] = "{$config['interfaces'][$friendly_int]['descr']}";

		$flent['src'] 		= convert_port_period_to_colon($log_split[5]);
		$flent['dst'] 		= convert_port_period_to_colon($log_split[6]);

		$flent['dst'] = str_replace(": NBT UDP PACKET(137)", "", $flent['dst']);

		$tmp = split("/", $log_split[2]);
		$flent['rulenum'] = $tmp[0];

		$counter++;
		$filterlog[] = $flent;

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

$filterlog = conv_clog_filter($filter_logfile, $nentries);

$pgtitle = "Diagnostics: System logs: Firewall";
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
<?php
	if(isset($config['syslog']['reverse']))
		echo "	var isReverse = true;\n";
	else
		echo "	var isReverse = false;\n";
?>
</script>
<br>
<table width="100%">
	<tr>
		<td>
			<div class="pgtitle"><?=$pgtitle?></div>
		</td>
		<td align="right">
			Pause:<input valign="middle" type="checkbox" onClick="javascript:toggle_pause();">
		</td>
	</tr>
</table>
<br>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
<?php
	$tab_array = array();
	$tab_array[] = array("System", false, "diag_logs.php");
	$tab_array[] = array("Firewall", true, "diag_logs_filter.php");
	$tab_array[] = array("DHCP", false, "diag_logs_dhcp.php");
	$tab_array[] = array("Portal Auth", false, "diag_logs_auth.php");
	$tab_array[] = array("IPSEC VPN", false, "diag_logs_ipsec.php");
	$tab_array[] = array("PPTP VPN", false, "diag_logs_vpn.php");
	$tab_array[] = array("Load Balancer", false, "diag_logs_slbd.php");
	$tab_array[] = array("OpenVPN", false, "diag_logs_openvpn.php");
	$tab_array[] = array("OpenNTPD", false, "diag_logs_ntpd.php");
	$tab_array[] = array("Settings", false, "diag_logs_settings.php");
	display_top_tabs($tab_array);
?>
 </td></tr>
  <tr>
     <td>
	<div id="mainarea">
		<div class="listtopic">
			Last <?php echo $nentries; ?> records
		</div>
		<div id="log">
			<div class="log-header">
                                <span class="log-action">Act</span>
                                <span class="log-time">Time</span>
                                <span class="log-interface">If</span>
                                <span class="log-source">Source</span>
                                <span class="log-destination">Destination</span>
                                <span class="log-protocol">Proto</span>
			</div>
			<?php $counter=0; foreach ($filterlog as $filterent): ?>
			<?php
				if(isset($config['syslog']['reverse'])) {
					/* honour reverse logging setting */
					if($counter == 0)
						$activerow = " id=\"firstrow\"";
					else
						$activerow = "";

				} else {
					/* non-reverse logging */
					if($counter == count($filterlog))
						$activerow = " id=\"firstrow\"";
					else
						$activerow = "";
				}
			?>
			<div class="log-entry" <?php echo $activerow; ?>>
				<span class="log-action" nowrap><a href="#" onClick="javascript:getURL('diag_logs_filter.php?getrulenum=<?php echo $filterent['rulenum']; ?>', outputrule);">
				<?php
					if (strstr(strtolower($filterent['act']), "p"))
						$img = "/themes/metallic/images/icons/icon_pass.gif";
					else if(strstr(strtolower($filterent['act']), "r"))
						$img = "/themes/metallic/images/icons/icon_reject.gif";
					else
						$img = "/themes/metallic/images/icons/icon_block.gif";
				?>
				<img border="0" src="<?=$img;?>" width="11" height="11" align="absmiddle"></a></span>
				<span class="log-time" ><?=htmlspecialchars($filterent['time']);?></span>
				<span class="log-interface" ><?=htmlspecialchars(convert_real_interface_to_friendly_interface_name($filterent['interface']));?></span>
				<span class="log-source" ><?=htmlspecialchars($filterent['src']);?></span>
				<span class="log-destination" ><?=htmlspecialchars($filterent['dst']);?></span>
				<span class="log-protocol" ><?=htmlspecialchars($filterent['proto']);?></span>
			</div>
		<?php $counter++; endforeach; ?>
		</div>
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
function fetch_new_rules() {
	if(isPaused)
		return;
	if(isBusy)
		return;
	isBusy = true;
	getURL('diag_logs_filter_dynamic.php?lastsawtime=' + lastsawtime, fetch_new_rules_callback);
}
function fetch_new_rules_callback(callback_data) {
	if(isPaused)
		return;

	var data_split;
	var new_data_to_add = Array();
	var data = callback_data.content;

	data_split = data.split("\n");

	for(var x=0; x<data_split.length-1; x++) {
		/* loop through rows */
		row_split = data_split[x].split("||");
		var line = '';
		line = '<div class="log-entry">';
		line += '  <span class="log-action" nowrap>' + row_split[0] + '</span>';
		line += '  <span class="log-time" nowrap>' + row_split[1] + '</span>';
		line += '  <span class="log-interface" nowrap>' + row_split[2] + '</span>';
		line += '  <span class="log-source" nowrap>' + row_split[3] + '</span>';
		line += '  <span class="log-destination" nowrap>' + row_split[4] + '</span>';
		line += '  <span class="log-protocol" nowrap>' + row_split[5] + '</span>';
		line += '</div>';
		lastsawtime = row_split[6];
		new_data_to_add[new_data_to_add.length] = line;
	}
	update_div_rows(new_data_to_add);
	isBusy = false;
}
function update_div_rows(data) {
	if(isPaused)
		return;

	var isIE = navigator.appName.indexOf('Microsoft') != -1;
	var isSafari = navigator.userAgent.indexOf('Safari') != -1;
	var isOpera = navigator.userAgent.indexOf('Opera') != -1;
	var rulestable = document.getElementById('log');
	var rows = rulestable.getElementsByTagName('div');
	var showanim = 1;
	if (isIE) {
		showanim = 0;
	}
	//alert(data.length);
	for(var x=0; x<data.length; x++) {
		var numrows = rows.length;
		var appearatrow;
		/*    if reverse logging is enabled we need to show the
		 *    records in a reverse order with new items appearing
         *    on the top
         */
		//if(isReverse == false) {
		//	for (var i = 2; i < numrows; i++) {
		//		nextrecord = i + 1;
		//		if(nextrecord < numrows)
		//			rows[i].innerHTML = rows[nextrecord].innerHTML;
		//	}
		//	appearatrow = numrows - 1;
		//} else {
			for (var i = numrows; i > 0; i--) {
				nextrecord = i + 1;
				if(nextrecord < numrows)
					rows[nextrecord].innerHTML = rows[i].innerHTML;
			}
			appearatrow = 1;
		//}
		var item = document.getElementById('firstrow');
		if(x == data.length-1) {
			/* nothing */
			showanim = false;
		} else {
			showanim = false;
		}
		if (showanim) {
			rows[appearatrow].style.display = 'none';
			rows[appearatrow].innerHTML = data[x];
			new Effect.Appear(rows[appearatrow]);
		} else {
			rows[appearatrow].innerHTML = data[x];
		}
	}
	/* rechedule AJAX interval */
	timer = setInterval('fetch_new_rules()', updateDelay);
}
function toggle_pause() {
	if(isPaused) {
		isPaused = false;
		fetch_new_rules();
	} else {
		isPaused = true;
	}
}
/* start local AJAX engine */
lastsawtime = '<?php echo time(); ?>;';
timer = setInterval('fetch_new_rules()', updateDelay);
</script>
<?php include("fend.inc"); ?>
</body>
</html>
<?php

/* AJAX specific handlers */
function handle_ajax() {
	if($_GET['getrulenum'] or $_POST['getrulenum']) {
		if($_GET['getrulenum'])
			$rulenum = $_GET['getrulenum'];
		if($_POST['getrulenum'])
			$rulenum = $_POST['getrulenum'];
		$rule = `pfctl -vvsr | grep '^@{$rulenum} '`;
		echo "The rule that triggered this action is:\n\n{$rule}";
		exit;
	}

	if($_GET['lastsawtime'] or $_POST['lastsawtime']) {
		global $filter_logfile,$filterent;
		if($_GET['lastsawtime'])
			$lastsawtime = $_GET['lastsawtime'];
		if($_POST['lastsawtime'])
			$lastsawtime = $_POST['lastsawtime'];
		/*  compare lastsawrule's time stamp to filter logs.
		 *  afterwards return the newer records so that client
                 *  can update AJAX interface screen.
		 */
		$new_rules = "";
		$filterlog = conv_clog_filter($filter_logfile, 50);
		foreach($filterlog as $log_row) {
			$time_regex = "";
			preg_match("/.*([0-9][0-9]:[0-9][0-9]:[0-9][0-9])/", $log_row['time'], $time_regex);
			$row_time = strtotime($time_regex[1]);
			if (strstr(strtolower($log_row['act']), "p"))
				$img = "<img border='0' src='/themes/metallic/images/icons/icon_pass.gif'>";
			else if(strstr(strtolower($filterent['act']), "r"))
				$img = "<img border='0' src='/themes/metallic/images/icons/icon_reject.gif'>";
			else
				$img = "<img border='0' src='/themes/metallic/images/icons/icon_block.gif'>";
			//echo "{$time_regex[1]} - $row_time > $lastsawtime<p>";
			if($row_time > $lastsawtime)
				$new_rules .= "{$img}||{$log_row['time']}||{$log_row['interface']}||{$log_row['src']}||{$log_row['dst']}||{$log_row['proto']}||" . time() . "||\n";
		}
		echo $new_rules;
		exit;
	}
}

?>