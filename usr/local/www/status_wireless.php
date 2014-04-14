<?php
/*
	status_wireless.php
	Copyright (C) 2004 Scott Ullrich
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
	pfSense_MODULE:	interfaces
*/

##|+PRIV
##|*IDENT=page-diagnostics-wirelessstatus
##|*NAME=Status: Wireless page
##|*DESCR=Allow access to the 'Status: Wireless' page.
##|*MATCH=status_wireless.php*
##|-PRIV

require_once("guiconfig.inc");

$pgtitle = array(gettext("Status"),gettext("Wireless"));
$shortcut_section = "wireless";
include("head.inc");

$if = $_POST['if'];
if($_GET['if'] <> "")
	$if = $_GET['if'];

$ciflist = get_configured_interface_with_descr();
if(empty($if)) {
	/* Find the first interface
	   that is wireless */
	foreach($ciflist as $interface => $ifdescr) {
		if(is_interface_wireless(get_real_interface($interface))) {
			$if = $interface;
			break;
		}
	}
}
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
include("fbegin.inc");
?>
<form action="status_wireless.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td>
<?php
$tab_array = array();
foreach($ciflist as $interface => $ifdescr) {
	if (is_interface_wireless(get_real_interface($interface))) {
		$enabled = false;
		if($if == $interface)
			$enabled = true;
		$tab_array[] = array(gettext("Status") . " ({$ifdescr})", $enabled, "status_wireless.php?if={$interface}");
	}
}
$rwlif = get_real_interface($if);
if($_POST['rescanwifi'] <> "") {
	mwexec_bg("/sbin/ifconfig {$rwlif} scan 2>&1");
	$savemsg = gettext("Rescan has been initiated in the background. Refresh this page in 10 seconds to see the results.");
}
if ($savemsg) print_info_box($savemsg);
display_top_tabs($tab_array);
?>
</td></tr>
<tr><td>
<div id="mainarea" class="tabcont">
<input type="hidden" name="if" id="if" value="<?php echo htmlspecialchars($if); ?>">
<b><input type="submit" name="rescanwifi" id="rescanwifi" value="Rescan"></b><br /><br />
<b><?php echo gettext("Nearby access points or ad-hoc peers"); ?></b>
<table class="tabcont sortable" colspan="3" cellpadding="3" width="100%">
	<thead>
		<tr bgcolor='#990000'>
			<td><b><font color='#ffffff'>SSID</font></b></td>
			<td><b><font color='#ffffff'>BSSID</font></b></td>
			<td><b><font color='#ffffff'>CHAN</font></b></td>
			<td><b><font color='#ffffff'>RATE</font></b></td>
			<td><b><font color='#ffffff'>RSSI</font></b></td>
			<td><b><font color='#ffffff'>INT</font></b></td>
			<td><b><font color='#ffffff'>CAPS</font></b></td>
		</tr>
	</thead>
	<tbody>
<?php
	exec("/sbin/ifconfig {$rwlif} list scan 2>&1", $states, $ret);
	/* Skip Header */
	array_shift($states);

	$counter=0;
	foreach($states as $state) {
		/* Split by Mac address for the SSID Field */
		$split = preg_split("/([0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f])/i", $state);
		preg_match("/([0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f])/i", $state, $bssid);
		$ssid = htmlspecialchars($split[0]);
		$bssid = $bssid[0];
		/* Split the rest by using spaces for this line using the 2nd part */
		$split = preg_split("/[ ]+/i", $split[1]);
		$channel = $split[1];
		$rate = $split[2];
		$rssi = $split[3];
		$int = $split[4];
		$caps = "$split[5] $split[6] $split[7] $split[8] $split[9] $split[10] $split[11] ";

		print "<tr>";
		print "<td>{$ssid}</td>";
		print "<td>{$bssid}</td>";
		print "<td>{$channel}</td>";
		print "<td>{$rate}</td>";
		print "<td>{$rssi}</td>";
		print "<td>{$int}</td>";
		print "<td>{$caps}</td>";
		print "</tr>\n";
	}
?>
	</tbody>
</table>
<b><?php echo gettext("Associated or ad-hoc peers"); ?></b><br />
<table class="tabcont sortable" colspan="3" cellpadding="3" width="100%">
	<thead>
		<tr bgcolor='#990000'>
			<td><b><font color='#ffffff'>ADDR</font></b></td>
			<td><b><font color='#ffffff'>AID</font></b></td>
			<td><b><font color='#ffffff'>CHAN</font></b></td>
			<td><b><font color='#ffffff'>RATE</font></b></td>
			<td><b><font color='#ffffff'>RSSI</font></b></td>
			<td><b><font color='#ffffff'>IDLE</font></b></td>
			<td><b><font color='#ffffff'>TXSEQ</font></b></td>
			<td><b><font color='#ffffff'>RXSEQ</font></b></td>
			<td><b><font color='#ffffff'>CAPS</font></b></td>
			<td><b><font color='#ffffff'>ERP</font></b></td>
		</tr>
	</thead>
	<tbody>

<?php
	$states = array();
	exec("/sbin/ifconfig {$rwlif} list sta 2>&1", $states, $ret);
	array_shift($states);

	$counter=0;
	foreach($states as $state) {
		$split = preg_split("/[ ]+/i", $state);
		/* Split the rest by using spaces for this line using the 2nd part */
		print "<tr>";
		print "<td>{$split[0]}</td>";
		print "<td>{$split[1]}</td>";
		print "<td>{$split[2]}</td>";
		print "<td>{$split[3]}</td>";
		print "<td>{$split[4]}</td>";
		print "<td>{$split[5]}</td>";
		print "<td>{$split[6]}</td>";
		print "<td>{$split[7]}</td>";
		print "<td>{$split[8]}</td>";
		print "<td>{$split[9]}</td>";
		print "</tr>\n";
	}

/* XXX: what stats to we get for adhoc mode? */

?>
	</tbody>
</table>
</div><br />
	<b>Flags:</b> A = authorized, E = Extended Rate (802.11g), P = Power save mode<br />
	<b>Capabilities:</b> E = ESS (infrastructure mode), I = IBSS (ad-hoc mode), P = privacy (WEP/TKIP/AES),
		S = Short preamble, s = Short slot time
</td></tr>
</table>

<?php include("fend.inc"); ?>
</body>
</html>
