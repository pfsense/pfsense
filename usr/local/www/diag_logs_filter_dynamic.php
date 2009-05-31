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

/* In an effort to reduce duplicate code, many shared functions have been moved here. */
require_once("includes/log.inc.php");

$filter_logfile = "{$g['varlog_path']}/filter.log";

/* AJAX related routines */
handle_ajax(100, true);

/* Hardcode this. AJAX doesn't do so well with large numbers */
$nentries = 50;

if ($_POST['clear']) {
	exec("killall syslogd");
	exec("/usr/sbin/clog -i -s 262144 /var/log/filter.log");
	system_syslogd_start();
}

$filterlog = conv_clog_filter($filter_logfile, $nentries, $nentries + 100);

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
		echo "var isReverse = true;\n";
	else
		echo "var isReverse = false;\n";
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
	$tab_array[] = array("IPsec VPN", false, "diag_logs_ipsec.php");
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
			Last <?php echo $nentries; ?> records  (<a href="diag_logs_filter.php">Switch to regular view</a>)
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
					if($counter == count($filterlog) - 1)
						$activerow = " id=\"firstrow\"";
					else
						$activerow = "";
				}
			?>
			<div class="log-entry"<?php echo $activerow; ?>>
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
				  <?php
					if ($filterent['proto'] == "TCP")
						$filterent['proto'] .= ":" . $filterent['tcpflags'];
				  ?>
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
		line = '  <span class="log-action" nowrap>' + row_split[0] + '</span>';
		line += '  <span class="log-time" nowrap>' + row_split[1] + '</span>';
		line += '  <span class="log-interface" nowrap>' + row_split[2] + '</span>';
		line += '  <span class="log-source" nowrap>' + row_split[3] + '</span>';
		line += '  <span class="log-destination" nowrap>' + row_split[4] + '</span>';
		line += '  <span class="log-protocol" nowrap>' + row_split[5] + '</span>';
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
		/*    if reverse logging is enabled we need to show the
		 *    records in a reverse order with new items appearing
		 *    on the top
		 */
		if(isReverse == false) {
			for (var i = 1; i < numrows; i++) {
				nextrecord = i + 1;
				if(nextrecord < numrows)
					rows[i].innerHTML = rows[nextrecord].innerHTML;
			}
		} else {
			for (var i = numrows; i > 0; i--) {
				nextrecord = i + 1;
				if(nextrecord < numrows)
					rows[nextrecord].innerHTML = rows[i].innerHTML;
			}
		}
		var item = document.getElementById('firstrow');
		if(x == data.length-1) {
			/* nothing */
			showanim = false;
		} else {
			showanim = false;
		}
		if (showanim) {
			item.style.display = 'none';
			item.innerHTML = data[x];
			new Effect.Appear(item);
		} else {
			item.innerHTML = data[x];
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

<p><span class="vexpl"><a href="http://doc.pfsense.org/index.php/What_are_TCP_Flags%3F">TCP Flags</a>: F - FIN, S - SYN, A or . - ACK, R - RST, P - PSH, U - URG, E - ECE, C - CWR</span></p>

<?php include("fend.inc"); ?>
</body>
</html>
<?php


?>