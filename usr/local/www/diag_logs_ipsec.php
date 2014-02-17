<?php
/* $Id$ */
/*
	diag_logs.php
	Copyright (C) 2004-2009 Scott Ullrich
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
	pfSense_BUILDER_BINARIES:	/sbin/ifconfig	/usr/bin/awk	
	pfSense_MODULE:	ipsec
*/

##|+PRIV
##|*IDENT=page-status-systemlogs-ipsecvpn
##|*NAME=Status: System logs: IPsec VPN page
##|*DESCR=Allow access to the 'Status: System logs: IPsec VPN' page.
##|*MATCH=diag_logs_ipsec.php*
##|-PRIV

require("guiconfig.inc");
require("ipsec.inc");

$ipsec_logfile = "{$g['varlog_path']}/ipsec.log";

/* Create array with all IPsec tunnel descriptions */
$search = array();
$replace = array();
if(is_array($config['ipsec']['phase1']))
	foreach($config['ipsec']['phase1'] as $ph1ent) {
		$gateway = ipsec_get_phase1_dst($ph1ent);
		if(!is_ipaddr($gateway))
			continue;
		$search[] = "/(racoon: )(INFO[:].*?)({$gateway}\[[0-9].+\]|{$gateway})(.*)/i";
		$search[] = "/(racoon: )(\[{$gateway}\]|{$gateway})(.*)/i";
		$replace[] = "$1<strong>[{$ph1ent['descr']}]</strong>: $2$3$4";
		$replace[] = "$1<strong>[{$ph1ent['descr']}]</strong>: $2$3$4";
	}
/* collect all our own ip addresses */
exec("/sbin/ifconfig | /usr/bin/awk '/inet/ {print $2}'", $ip_address_list);
foreach($ip_address_list as $address) {
	$search[] = "/(racoon: )(INFO[:].*?)({$address}\[[0-9].+\])/i";
	$search[] = "/(racoon: )(\[{$address}\]|{$address})(.*)/i";
	$replace[] = "$1<strong>[Self]</strong>: $2$3$4";
	$replace[] = "$1<strong>[Self]</strong>: $2$3$4";
}

$search[] = "/(time up waiting for phase1)/i";
$search[] = "/(failed to pre-process ph1 packet)/i";
$search[] = "/(failed to pre-process ph2 packet)/i";
$search[] = "/(no proposal chosen)/i";
$replace[] = "$1 <strong>[Remote Side not responding]</strong>";
$replace[] = "$1 <strong>[Check Phase 1 settings, lifetime, algorithm]</strong>";
$replace[] = "$1 <strong>[Check Phase 2 settings, networks]</strong>";
$replace[] = "$1 <strong>[Check Phase 2 settings, algorithm]</strong>";

$nentries = $config['syslog']['nentries'];
if (!$nentries)
	$nentries = 50;

if ($_POST['clear']) 
	clear_log_file($ipsec_logfile);

$ipsec_logarr = return_clog($ipsec_logfile, $nentries);

$pgtitle = array(gettext("Status"),gettext("System logs"),gettext("IPsec VPN"));
$shortcut_section = "ipsec";
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
 	<tr>
		<td>
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("System"), false, "diag_logs.php");
	$tab_array[] = array(gettext("Firewall"), false, "diag_logs_filter.php");
	$tab_array[] = array(gettext("DHCP"), false, "diag_logs_dhcp.php");
	$tab_array[] = array(gettext("Portal Auth"), false, "diag_logs_auth.php");
	$tab_array[] = array(gettext("IPsec"), true, "diag_logs_ipsec.php");
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
	<tr>
    	<td>
			<div id="mainarea">
			<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0">
		  		<tr>
					<td colspan="2" class="listtopic"><?php printf(gettext("Last %s  IPsec log entries"),$nentries);?></td>
		  		</tr>
				<?php
				foreach($ipsec_logarr as $logent){
					$logent = htmlspecialchars($logent);
					foreach($search as $string) {
						if(preg_match($string, $logent))
							$match = true;
					}
					if(isset($match)) {
						$logent = preg_replace($search, $replace, $logent);
					} else {
						$searchs = "/(racoon: )([A-Z:].*?)([0-9].+\.[0-9].+.[0-9].+.[0-9].+\[[0-9].+\])(.*)/i";
						$replaces = "$1<strong><font color=red>[".gettext("Unknown Gateway/Dynamic")."]</font></strong>: $2$3$4";
						$logent = preg_replace($searchs, $replaces, $logent);
					}
					$logent = preg_split("/\s+/", $logent, 6);
					echo "<tr valign=\"top\">\n";
					$entry_date_time = htmlspecialchars(join(" ", array_slice($logent, 0, 3)));
					echo "<td class=\"listlr\" nowrap=\"nowrap\">" . $entry_date_time  . "</td>\n";
					echo "<td class=\"listr\">" . $logent[4] . " " . $logent[5] . "</td>\n";
					echo "</tr>\n";
				}
				?>
				<tr>
					<td>
						<br />
						<form action="diag_logs_ipsec.php" method="post">
						<input name="clear" type="submit" class="formbtn" value="<?=gettext("Clear log"); ?>"/>
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
