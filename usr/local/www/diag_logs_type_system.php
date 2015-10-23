<?php
/* $Id$ */
/*
	diag_logs.php
	Copyright (C) 2004-2009 Scott Ullrich
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
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
	pfSense_MODULE:	system
*/

##|+PRIV
##|*IDENT=page-diagnostics-logs-system
##|*NAME=Diagnostics: Logs: System page
##|*DESCR=Allow access to the 'Diagnostics: Logs: System' page.
##|*MATCH=diag_logs_type_system.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("filter_log.inc");

if ($system_tab) {
	if ($general_tab) {
		$logfile = "{$g['varlog_path']}/system.log";
#		$logfile_backup = "/cf/conf/system.log";
		$pgtitle = array(gettext("Status"), gettext("System logs"), gettext("General"));
		$log_name = 'System';
		$diag_logs_php_file = 'diag_logs.php';
		$grepinvert = 'ppp';	// Exclude lines containing "ppp".
	}
	elseif ($gateways_tab) {
		$logfile = "{$g['varlog_path']}/gateways.log";
#		$logfile_backup = "/cf/conf/gateways.log";
		$pgtitle = array(gettext("Status"), gettext("System logs"), gettext("Gateways"));
		$shortcut_section = "gateways";
		$log_name = 'Gateways';
		$diag_logs_php_file = 'diag_logs_gateways.php';
	}
	elseif ($routing_tab) {
		$logfile = "{$g['varlog_path']}/routing.log";
#		$logfile_backup = "/cf/conf/routing.log";
		$pgtitle = array(gettext("Status"),gettext("System logs"),gettext("Routing"));
		$shortcut_section = "routing";
		$log_name = 'Routing';
		$diag_logs_php_file = 'diag_logs_routing.php';
	}
	elseif ($resolver_tab) {
		$logfile = "{$g['varlog_path']}/resolver.log";
#		$logfile_backup = "/cf/conf/resolver.log";
		$pgtitle = array(gettext("Status"), gettext("System logs"), gettext("Resolver"));
		if (isset($config['dnsmasq']['enable']))
			$shortcut_section = "forwarder";
		else
			$shortcut_section = "resolver";
		$log_name = 'Resolver';
		$diag_logs_php_file = 'diag_logs_resolver.php';
		$grepinvert = 'ppp';	// Exclude lines containing "ppp".
	}
}
elseif ($dhcp_tab) {
	$logfile = "{$g['varlog_path']}/dhcpd.log";
#	$logfile_backup = "/cf/conf/dhcpd.log";
	$pgtitle = array(gettext("Status"), gettext("System logs"), gettext("DHCP"));
	$shortcut_section = "dhcp";
	$log_name = 'DHCP';
	$diag_logs_php_file = 'diag_logs_dhcp.php';
}
elseif ($portal_auth_tab) {
	$logfile = "{$g['varlog_path']}/portalauth.log";
#	$logfile_backup = "/cf/conf/portalauth.log";
	$pgtitle = array(gettext("Status"),gettext("System logs"),gettext("Portal Auth"));
	$shortcut_section = "captiveportal";
	$log_name = 'Portal Auth';
	$diag_logs_php_file = 'diag_logs_auth.php';
}
elseif ($openvpn_tab) {
	$logfile = "{$g['varlog_path']}/openvpn.log";
#	$logfile_backup = "/cf/conf/openvpn.log";
	$pgtitle = array(gettext("Status"), gettext("System logs"), gettext("OpenVPN"));
	$shortcut_section = "openvpn";
	$log_name = 'OpenVPN';
	$diag_logs_php_file = 'diag_logs_openvpn.php';
}
elseif ($ntp_tab) {
	$logfile = "{$g['varlog_path']}/ntpd.log";
#	$logfile_backup = "/cf/conf/ntpd.log";
	$pgtitle = array(gettext("Status"), gettext("System logs"), gettext("NTP"));
	$shortcut_section = "ntp";
	$log_name = 'NTP';
	$diag_logs_php_file = 'diag_logs_ntp.php';
}


function getGETPOSTsettingvalue($settingname, $default)
{
	$settingvalue = $default;
	if($_GET[$settingname])
		$settingvalue = $_GET[$settingname];
	if($_POST[$settingname])
		$settingvalue = $_POST[$settingname];
	return $settingvalue;
}

$filtersubmit = getGETPOSTsettingvalue('filtersubmit', null);
if ($filtersubmit) {
	$filtertext = getGETPOSTsettingvalue('filtertext', "");
	$filterlogentries_qty = getGETPOSTsettingvalue('filterlogentries_qty', null);
}

$filterlogentries_submit = getGETPOSTsettingvalue('filterlogentries_submit', null);
if ($filterlogentries_submit) {
	$filterfieldsarray = array();
	$filterfieldsarray['time'] = getGETPOSTsettingvalue('filterlogentries_time', null);
	$filterfieldsarray['process'] = getGETPOSTsettingvalue('filterlogentries_process', null);
	$filterfieldsarray['pid'] = getGETPOSTsettingvalue('filterlogentries_pid', null);
	$filterfieldsarray['message'] = getGETPOSTsettingvalue('filterlogentries_message', null);
	$filterlogentries_qty = getGETPOSTsettingvalue('filterlogentries_qty', null);
}

$nentries = $config['syslog']['nentries'];

# Override Display Quantity
if ($filterlogentries_qty)
	$nentries = $filterlogentries_qty;

if (!$nentries || !is_numeric($nentries))
	$nentries = 50;

if ($_POST['clear']) {
	clear_log_file($logfile);
	if ($dhcp_tab) {
		killbyname("dhcpd");
		services_dhcpd_configure();
	}
}

#if ($_POST['restore'])
#	restore_log_file($logfile, $logfile_backup);

#if ($_POST['backup'])
#	backup_log_file($logfile, $logfile_backup);

include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="logs system">
	<tr>
		<td>
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("System"), $system_tab, "diag_logs.php");
	$tab_array[] = array(gettext("Firewall"), false, "diag_logs_filter.php");
	$tab_array[] = array(gettext("DHCP"), $dhcp_tab, "diag_logs_dhcp.php");
	$tab_array[] = array(gettext("Portal Auth"), false, "diag_logs_auth.php");
	$tab_array[] = array(gettext("IPsec"), false, "diag_logs_ipsec.php");
	$tab_array[] = array(gettext("PPP"), false, "diag_logs_ppp.php");
	$tab_array[] = array(gettext("VPN"), false, "diag_logs_vpn.php");
	$tab_array[] = array(gettext("Load Balancer"), false, "diag_logs_relayd.php");
	$tab_array[] = array(gettext("OpenVPN"), $openvpn_tab, "diag_logs_openvpn.php");
	$tab_array[] = array(gettext("NTP"), $ntp_tab, "diag_logs_ntpd.php");
	$tab_array[] = array(gettext("Settings"), false, "diag_logs_settings.php");
	display_top_tabs($tab_array);
?>
		</td>
	</tr>
  <tr><td class="tabnavtbl">
<?php
if ($system_tab) {
	$tab_array = array();
	$tab_array[] = array(gettext("General"), $general_tab, "/diag_logs.php");
	$tab_array[] = array(gettext("Gateways"), $gateways_tab, "/diag_logs_gateways.php");
	$tab_array[] = array(gettext("Routing"), $routing_tab, "/diag_logs_routing.php");
	$tab_array[] = array(gettext("Resolver"), $resolver_tab, "/diag_logs_resolver.php");
	$tab_array[] = array(gettext("Wireless"), false, "/diag_logs_wireless.php");
	display_top_tabs($tab_array);
}
?>
		</td>
	</tr>
	<tr>
		<td>
			<div id="mainarea">
			<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0" summary="main area">
				<tr>
					<td colspan="<?=(!isset($config['syslog']['rawfilter']))?7:2?>" align="left" valign="middle">
					<div id="logentries_show" class="widgetconfigdiv" style="<?=(!isset($config['syslog']['rawfilter']))?"":"display:none"?>">
						<form id="filterlogentries" name="filterlogentries" action="<?=$diag_logs_php_file?>" method="post">
						<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="action">
						<tr>
							<td>
								<div align="center"><?=gettext("Time");?></div>
								<div align="center"><input id="filterlogentries_time" name="filterlogentries_time" class="formfld search" type="text" size="16" value="<?= htmlspecialchars($filterfieldsarray['time']) ?>" /></div>
							</td>
							<td>
								<div align="center"><?=gettext("Process");?></div>
								<div align="center"><input id="filterlogentries_process" name="filterlogentries_process" class="formfld search" type="text" size="10" value="<?= htmlspecialchars($filterfieldsarray['process']) ?>" /></div>
							</td>
							<td>
								<div align="center"><?=gettext("PID");?></div>
								<div align="center"><input id="filterlogentries_pid" name="filterlogentries_pid" class="formfld search" type="text" size="4" value="<?= htmlspecialchars($filterfieldsarray['pid']) ?>" /></div>
							</td>
							<td>
								<div align="center"><?=gettext("Log Message");?></div>
								<div align="center"><input id="filterlogentries_message" name="filterlogentries_message" class="formfld search" type="text" size="40" value="<?= htmlspecialchars($filterfieldsarray['message']) ?>" /></div>
							</td>
							<td>
								<div align="center" style="vertical-align:top;"><?=gettext("Quantity");?></div>
								<div align="center" style="vertical-align:top;"><input id="filterlogentries_qty" name="filterlogentries_qty" class="" type="text" size="6" value="<?= htmlspecialchars($filterlogentries_qty) ?>" /></div>
							</td>
							<td valign="bottom">
								<div align="center"><input id="filterlogentries_submit" name="filterlogentries_submit" type="submit" class="formbtn" value="<?=gettext("Filter");?>" /></div>
							</td>
						</tr>
						<tr>
							<td colspan="4">
								<?printf(gettext('Matches %1$s regular expression%2$s.'), '<a target="_blank" href="http://www.php.net/manual/en/book.pcre.php">', '</a>');?>&nbsp;&nbsp;
								<?=gettext("Precede with exclamation (!) as first character to exclude match.");?>&nbsp;&nbsp;
							</td>
						</tr>
						</table>
						</form>
						<div style="float: right; vertical-align:middle">
							<br />
						</div>
					</div>
					</td>
				</tr>
<?php if (!isset($config['syslog']['rawfilter'])):
	if ($filterlogentries_submit) 
		$filterlog = conv_log_filter($logfile, $nentries, $nentries + 100, $filterfieldsarray);
	else
		$filterlog = conv_log_filter($logfile, $nentries, $nentries + 100, $filtertext);
?>
				<tr>
					<td colspan="<?=$config['syslog']['filterdescriptions']==="1"?7:6?>" class="listtopic">
						<?php
						if (!$filterfieldsarray)
							printf(gettext("Last %s %s log entries.") . ' ', count($filterlog), $log_name);
						else
							printf(gettext("%s matched %s log entries.") . ' ', count($filterlog), $log_name);
						printf(gettext("Max(%s)"),$nentries);
						?>
					</td>
				</tr>
				<tr class="sortableHeaderRowIdentifier">
					<td width="80px" class="listhdrr"><?=gettext("Time");?></td>
					<td width="42px" class="listhdrr"><?=gettext("Process");?></td>
					<td width="21px" class="listhdrr"><?=gettext("PID");?></td>
					<td width="78%" class="listhdrr"><?=gettext("Log Message");?></td>
				</tr>
				<?php
				$rowIndex = 0;
				foreach ($filterlog as $filterent): 
				$evenRowClass = $rowIndex % 2 ? " listMReven" : " listMRodd";
				$rowIndex++;?>
				<tr class="<?=$evenRowClass?>">
					<td class="listMRr nowrap"><?php echo htmlspecialchars($filterent['time']);?></td>
					<td class="listMRr nowrap"><?php echo htmlspecialchars($filterent['process']);?></td>
					<td class="listMRr nowrap"><?php echo htmlspecialchars($filterent['pid']);?></td>
					<td class="listMRr" style="word-wrap:break-word; max-width:490px"><?php echo htmlspecialchars($filterent['message']);?></td>
				</tr>
				<?php
				endforeach; 
				?>
<?php else: ?>
				<tr>
					<td colspan="2" class="listtopic">
						<?php
						printf(gettext("Last %s log entries.") . ' ', $log_name);
						printf(gettext("Max(%s)"),$nentries);
						?>
					</td>
				</tr>
				<?php
				if ( ($filtertext) && ($grepinvert) )
					dump_clog($logfile, $nentries, true, array("$filtertext"), array("$grepinvert"));
				elseif ($filtertext) 
					dump_clog($logfile, $nentries, true, array("$filtertext"));
				elseif ($grepinvert)
					dump_clog($logfile, $nentries, true, array(), array("$grepinvert"));
				else
					dump_clog($logfile, $nentries);
				?>
<?php endif; ?>
			</table>
			<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0" summary="admin area">
				<tr>
					<td align="left">
						<form id="logmanagerform" name="logmanagerform" action="<?=$diag_logs_php_file?>" method="post">
							<input id="clear" name="clear" type="submit" class="formbtn" value="<?=gettext("Clear log");?>" />
<!--
							<input id="restore" name="restore" type="submit" class="formbtn" value="<?=gettext("Restore log");?>" />
							<input id="backup" name="backup" type="submit" class="formbtn" value="<?=gettext("Backup log");?>" />
-->
						</form>
<?php if ($dhcp_tab):?>
						<br />NOTE: Clearing the log file will restart the DHCP daemon.
<?php endif; ?>
					</td>
					<td align="right">
					<div id="filterform_show" style="<?=(!isset($config['syslog']['rawfilter']))?"display:none":""?>">
						<form id="filterform" name="filterform" action="<?=$diag_logs_php_file?>" method="post">
						<table width="0%" border="0" cellpadding="0" cellspacing="0" summary="firewall log">
						<tr>
							<td>
								<div align="center" style="vertical-align:top;"><?=gettext("Filter expression");?></div>
								<div align="center" style="vertical-align:top;"><input id="filtertext" name="filtertext" class="formfld search" style="vertical-align:top;" type="text" size="40" value="<?= htmlspecialchars($filtertext) ?>" /></div>
							</td>
							<td>
								<div align="center" style="vertical-align:top;"><?=gettext("Quantity");?></div>
								<div align="center" style="vertical-align:top;"><input id="filterlogentries_qty_raw" name="filterlogentries_qty" class="" style="vertical-align:top;" type="text" size="6" value="<?= htmlspecialchars($filterlogentries_qty) ?>" /></div>
							</td>
							<td>
								<div align="center" style="vertical-align:top;">&nbsp;</div>
								<div align="center" style="vertical-align:top;"><input id="filtersubmit" name="filtersubmit" type="submit" class="formbtn" style="vertical-align:top;" value="<?=gettext("Filter");?>" /></div>
							</td>
						</tr>
						<tr>
							<td colspan="2">
								<?printf(gettext('Matches %1$s regular expression%2$s.'), '<a target="_blank" href="http://www.php.net/manual/en/book.pcre.php">', '</a>');?>&nbsp;&nbsp;
							</td>
						</tr>
						</table>
						</form>
					</div>
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
