<?php
/*
	diag_logs_filter_summary.php
	Copyright (C) 2009 Jim Pingle (jpingle@gmail.com)
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
	pfSense_BUILDER_BINARIES:	
	pfSense_MODULE:	filter
*/

$pgtitle = gettext("Status").": ".gettext("System logs").": ".gettext("Firewall Log Summary");
$shortcut_section = "firewall";
require_once("guiconfig.inc");
include_once("filter_log.inc");

$filter_logfile = "{$g['varlog_path']}/filter.log";
$lines = 5000;
$entriesperblock = 5;

$filterlog = conv_log_filter($filter_logfile, $lines, $lines);
$gotlines = count($filterlog);
$fields = array(
	'act'       => gettext("Actions"),
	'interface' => gettext("Interfaces"),
	'proto'     => gettext("Protocols"),
	'srcip'     => gettext("Source IPs"),
	'dstip'     => gettext("Destination IPs"),
	'srcport'   => gettext("Source Ports"),
	'dstport'   => gettext("Destination Ports"));

$summary = array();
foreach (array_keys($fields) as $f) {
	$summary[$f]  = array();
}

$totals = array();

function cmp($a, $b) {
    if ($a == $b) {
        return 0;
    }
    return ($a < $b) ? 1 : -1;
}

function stat_block($summary, $stat, $num) {
	global $gotlines, $fields;
	uasort($summary[$stat] , 'cmp');
	print "<table width=\"200\" cellpadding=\"3\" cellspacing=\"0\" border=\"1\" summary=\"source destination ip\">";
	print "<tr><th colspan=\"2\">{$fields[$stat]} ".gettext("data")."</th></tr>";
	$k = array_keys($summary[$stat]);
	$total = 0;
	$numentries = 0;
	for ($i=0; $i < $num; $i++) {
		if ($k[$i]) {
			$total += $summary[$stat][$k[$i]];
			$numentries++;
			$outstr = $k[$i];
			if (is_ipaddr($outstr)) {
				$outstr = "<a href=\"diag_dns.php?host={$outstr}\" title=\"".gettext("Reverse Resolve with DNS")."\"><img border=\"0\" src=\"/themes/{$g['theme']}/images/icons/icon_log.gif\" alt=\"log\" /></a> {$outstr}";
			} elseif (substr_count($outstr, '/') == 1) {
				list($proto, $port) = explode('/', $outstr);
				$service = getservbyport($port, strtolower($proto));
				if ($service)
					$outstr .= ": {$service}";
			}
			print "<tr><td>{$outstr}</td><td width=\"50\" align=\"right\">{$summary[$stat][$k[$i]]}</td></tr>";
		}
	}
	$leftover = $gotlines - $total;
	if ($leftover > 0) {
		print "<tr><td>Other</td><td width=\"50\" align=\"right\">{$leftover}</td></tr>";
	}
	print "</table>";
}

function pie_block($summary, $stat, $num) {
	global $gotlines, $fields;
	uasort($summary[$stat] , 'cmp');
	$k = array_keys($summary[$stat]);
	$total = 0;
	$numentries = 0;
	print "\n<script type=\"text/javascript\">\n";
	print "//<![CDATA[\n";
	for ($i=0; $i < $num; $i++) {
		if ($k[$i]) {
			$total += $summary[$stat][$k[$i]];
			$numentries++;
			print "var d{$stat}{$i} = [];\n";
			print "d{$stat}{$i}.push([1, {$summary[$stat][$k[$i]]}]);\n";
		}
	}
	$leftover = $gotlines - $total;
	if ($leftover > 0) {
		print "var d{$stat}{$num} = [];\n";
		print "d{$stat}{$num}.push([1, {$leftover}]);\n";
	}

	print "Event.observe(window, 'load', function() {\n";
	print "	new Proto.Chart($('piechart{$stat}'),\n";
	print "	[\n";
	for ($i=0; $i < $num; $i++) {
		if ($k[$i]) {
			print "		{ data: d{$stat}{$i}, label: \"{$k[$i]}\"}";
			if (!(($i == ($numentries - 1)) && ($leftover <= 0)))
				print ",\n";
			else
				print "\n";
		}
	}
	if ($leftover > 0)
		print "		{ data: d{$stat}{$i}, label: \"Other\"}\n";
	print "	],\n";
	print "	{\n";
	print "		pies: {show: true, autoScale: true},\n";
	print "		legend: {show: true, labelFormatter: lblfmt}\n";
	print "	});\n";
	print "});\n";
	print "//]]>\n";
	print "</script>\n";
	print "<table cellpadding=\"3\" cellspacing=\"0\" border=\"0\" summary=\"pie chart\">";
	print "<tr><th><font size=\"+1\">{$fields[$stat]}</font></th></tr>";
	print "<tr><td><div id=\"piechart{$stat}\" style=\"width:450px;height:300px\"></div>";
	print "</table>\n";
}

foreach ($filterlog as $fe) {
	$specialfields = array('srcport', 'dstport');
	foreach (array_keys($fields) as $field) {
		if (!in_array($field, $specialfields))
			$summary[$field][$fe[$field]]++;
	}
	/* Handle some special cases */
	if ($fe['srcport'])
		$summary['srcport'][$fe['proto'].'/'.$fe['srcport']]++;
	else
		$summary['srcport'][$fe['srcport']]++;
	if ($fe['dstport'])
		$summary['dstport'][$fe['proto'].'/'.$fe['dstport']]++;
	else
		$summary['dstport'][$fe['dstport']]++;
}

include("head.inc"); ?>
<body link="#000000" vlink="#000000" alink="#000000">
<script src="/javascript/filter_log.js" type="text/javascript"></script>
<script type="text/javascript" src="/protochart/prototype.js"></script>
<script type="text/javascript" src="/protochart/ProtoChart.js"></script>
<!--[if IE]>
<script type="text/javascript" src="/protochart/excanvas.js">
</script>
<![endif]-->
<script type="text/javascript">
//<![CDATA[
	function lblfmt(lbl) {
		return '<font size=\"-2\">' + lbl + '<\/font>'
	}
//]]>
</script>

<?php include("fbegin.inc"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="logs filter summary">
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
  <tr><td class="tabnavtbl">
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("Normal View"), false, "/diag_logs_filter.php");
	$tab_array[] = array(gettext("Dynamic View"), false, "/diag_logs_filter_dynamic.php");
	$tab_array[] = array(gettext("Summary View"), true, "/diag_logs_filter_summary.php");
	display_top_tabs($tab_array);
?>
		</td>
	</tr>
  <tr>
    <td>
	<div id="mainarea">
		<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" align="center" summary="main area">
		<tr><td align="center">

<?php printf (gettext('This is a firewall log summary, of the last %1$s lines of the firewall log (Max %2$s).'), $gotlines, $lines)?><br />
<?=gettext("NOTE: IE8 users must enable compatibility view.")?>

<?php
foreach(array_keys($fields) as $field) {
	pie_block($summary, $field , $entriesperblock);
	echo "<br /><br />";
	stat_block($summary, $field , $entriesperblock);
	echo "<br /><br />";
}
?>
		</td></tr></table>
		</div>
	</td>
  </tr>
</table>
<?php include("fend.inc"); ?>
</body>
</html>