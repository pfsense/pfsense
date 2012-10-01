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

function getGETPOSTsettingvalue($settingname, $default)
{
	$settingvalue = $default;
	if($_GET[$settingname])
		$settingvalue = $_GET[$settingname];
	if($_POST[$settingname])
		$settingvalue = $_POST[$settingname];
	return $settingvalue;
}

$rulenum = getGETPOSTsettingvalue('getrulenum', null);
if($rulenum) {
	list($rulenum, $type) = explode(',', $rulenum);
	$rule = find_rule_by_number($rulenum, $type);
	echo gettext("The rule that triggered this action is") . ":\n\n{$rule}";
	exit;
}

$interfacefilter = getGETPOSTsettingvalue('interface', null);
$filtertext = htmlspecialchars(getGETPOSTsettingvalue('filtertext', ""));

$filter_logfile = "{$g['varlog_path']}/filter.log";

$nentries = $config['syslog']['nentries'];
if (!$nentries)
	$nentries = 50;

if ($_POST['clear'])
	clear_log_file($filter_logfile);

$pgtitle = array(gettext("Status"),gettext("System logs"),gettext("Firewall"));
$shortcut_section = "firewall";
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
	$tab_array[] = array(gettext("NTP"), false, "diag_logs_ntpd.php");
	$tab_array[] = array(gettext("Settings"), false, "diag_logs_settings.php");
	display_top_tabs($tab_array);
?>
 </td></tr>
  <tr>
    <td>
	<div id="mainarea">
		<table class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0" sortableMultirow="<?=$config['syslog']['filterdescriptions'] === "2"?2:1?>">
			<thead>
			<tr>
				<td colspan="<?=(!isset($config['syslog']['rawfilter']))?7:2?>" align="left" valign="middle">
				<div style="float: right; vertical-align:middle">
					<form id="filterform" name="filterform" action="diag_logs_filter.php" method="post">
						<select name="interface" onChange="dst_change(this.value,iface_old,document.iform.dsttype.value);iface_old = document.iform.interface.value;typesel_change();">
						<option value="" <?=$interfacefilter?"":"selected"?>>*Any interface</option>
						<?php						
						$iflist = get_configured_interface_with_descr(false, true);
						//$iflist = get_interface_list();
						// Allow extending of the firewall edit interfaces 
						pfSense_handle_custom_code("/usr/local/pkg/firewall_nat/pre_interfaces_edit");
						foreach ($iflist as $if => $ifdesc)
							$interfaces[$if] = $ifdesc;

						if ($config['l2tp']['mode'] == "server")
							$interfaces['l2tp'] = "L2TP VPN";

						if ($config['pptpd']['mode'] == "server")
							$interfaces['pptp'] = "PPTP VPN";

						if (is_pppoe_server_enabled() && have_ruleint_access("pppoe"))
							$interfaces['pppoe'] = "PPPoE VPN";

						/* add ipsec interfaces */
						if (isset($config['ipsec']['enable']) || isset($config['ipsec']['client']['enable']))
							$interfaces["enc0"] = "IPsec";

						/* add openvpn/tun interfaces */
						if  ($config['openvpn']["openvpn-server"] || $config['openvpn']["openvpn-client"])
							$interfaces["openvpn"] = "OpenVPN";
						
						foreach ($interfaces as $iface => $ifacename): ?>
						<option value="<?=$iface;?>" <?=($iface==$interfacefilter)?"selected":"";?>><?=htmlspecialchars($ifacename);?></option>
						<?php endforeach; ?>
						</select>	
						
						<input id="filtertext" name="filtertext" class="formfld search" style="vertical-align:top;" value="<?=gettext($filtertext);?>" />
						<input id="filtersubmit" name="filtersubmit" type="submit" class="formbtn" style="vertical-align:top;" value="<?=gettext("Filter");?>" />
					</form>
					<br/>
				</div>
				<div style="float: left;">
					<?=gettext("Normal View");?> | <a href="diag_logs_filter_dynamic.php"><?=gettext("Dynamic View");?></a> | <a href="diag_logs_filter_summary.php"><?=gettext("Summary View");?></a>
					<br/><br/>
					<?php if (isset($config['syslog']['filterdescriptions']) && $config['syslog']['filterdescriptions'] === "2"):?>
					<a href="#" onclick="toggleListDescriptions()">Show/hide rule descriptions</a>
					<?php endif;?>
					<br/>
				</div>
				</td>	
			</tr>
<?php if (!isset($config['syslog']['rawfilter'])):
	$iflist = get_configured_interface_with_descr(false, true);
	if ($iflist[$interfacefilter])
		$interfacefilter = $iflist[$interfacefilter];
	$filterlog = conv_log_filter($filter_logfile, $nentries, $nentries + 100, $filtertext, $interfacefilter);
?>
			<tr>
			  <td colspan="<?=$config['syslog']['filterdescriptions']==="1"?7:6?>" class="listtopic">
				<?php if (!$filtertext)
					printf(gettext("Last %s firewall log entries."),count($filterlog));
				else
					echo count($filterlog). ' ' . gettext("matched log entries.");
			    printf(gettext("Max(%s)"),$nentries);?>
			  </td>
			</tr>
			<tr class="sortableHeaderRowIdentifier">
			  <td width="10%" class="listhdrr"><?=gettext("Act");?></ td>
			  <td width="10%" class="listhdrr"><?=gettext("Time");?></ td>
			  <td width="15%" class="listhdrr"><?=gettext("If");?></ td>
			  <?php if ($config['syslog']['filterdescriptions'] === "1"):?>
				<td width="10%" class="listhdrr"><?=gettext("Rule");?></ td>
			  <?php endif;?>
			  <td width="25%" class="listhdrr"><?=gettext("Source");?></ td>
			  <td width="25%" class="listhdrr"><?=gettext("Destination");?></ td>
			  <td width="15%" class="listhdrr"><?=gettext("Proto");?></ td>
			</tr>
			</thead>
			<?php
			if ($config['syslog']['filterdescriptions'])
				buffer_rules_load();
			$rowIndex = 0;
			foreach ($filterlog as $filterent): 
			$evenRowClass = $rowIndex % 2 ? " listMReven" : " listMRodd";
			$rowIndex++;?>
			<tr class="<?=$evenRowClass?>">
			  <td class="listMRlr" nowrap="nowrap" align="center" sorttable_customkey="<?=$filterent['act']?>">
			  <center>
			  <a href="#" onclick="javascript:getURL('diag_logs_filter.php?getrulenum=<?php echo "{$filterent['rulenum']},{$filterent['act']}"; ?>', outputrule);">
			  <img border="0" src="<?php echo find_action_image($filterent['act']);?>" width="11" height="11" align="middle" alt="<?php echo $filterent['act'];?>" title="<?php echo $filterent['act'];?>" />
			  <?php if ($filterent['count']) echo $filterent['count'];?></a></center></td>
			  <td class="listMRr" nowrap="nowrap"><?php echo htmlspecialchars($filterent['time']);?></td>
			  <td class="listMRr" nowrap="nowrap"><?php echo htmlspecialchars($filterent['interface']);?></td>
			  <?php 
			  if ($config['syslog']['filterdescriptions'] === "1")
				echo("<td class=\"listMRr\" nowrap=\"nowrap\">".find_rule_by_number_buffer($filterent['rulenum'],$filterent['act'])."</td>");
				
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
			  <td class="listMRr" nowrap="nowrap">
				<a href="diag_dns.php?host=<?php echo $filterent['srcip']; ?>" title="<?=gettext("Reverse Resolve with DNS");?>">
				<img border="0" src="/themes/<?= $g['theme']; ?>/images/icons/icon_log.gif" alt="Icon Reverse Resolve with DNS"/></a>
				<a href="easyrule.php?<?php echo "action=block&amp;int={$int}&amp;src={$filterent['srcip']}&amp;ipproto={$ipproto}"; ?>" title="<?=gettext("Easy Rule: Add to Block List");?>" onclick="return confirm('<?=gettext("Do you really want to add this BLOCK rule?")."\n\n".gettext("Easy Rule is still experimental.")."\n".gettext("Continue at risk of your own peril.")."\n".gettext("Backups are also nice.")?>')">
				<img border="0" src="/themes/<?= $g['theme']; ?>/images/icons/icon_block_add.gif" alt="Icon Easy Rule: Add to Block List" /></a>
				<?php echo $srcstr;?>
			  </td>
			  <td class="listMRr" nowrap="nowrap">
				<a href="diag_dns.php?host=<?php echo $filterent['dstip']; ?>" title="<?=gettext("Reverse Resolve with DNS");?>">
				<img border="0" src="/themes/<?= $g['theme']; ?>/images/icons/icon_log.gif" alt="Icon Reverse Resolve with DNS" /></a>
				<a href="easyrule.php?<?php echo "action=pass&amp;int={$int}&amp;proto={$proto}&amp;src={$filterent['srcip']}&amp;dst={$filterent['dstip']}&amp;dstport={$filterent['dstport']}&amp;ipproto={$ipproto}"; ?>" title="<?=gettext("Easy Rule: Pass this traffic");?>" onclick="return confirm('<?=gettext("Do you really want to add this PASS rule?")."\n\n".gettext("Easy Rule is still experimental.")."\n".gettext("Continue at risk of your own peril.")."\n".gettext("Backups are also nice.");?>')">
				<img border="0" src="/themes/<?= $g['theme']; ?>/images/icons/icon_pass_add.gif" alt="Icon Easy Rule: Pass this traffic" /></a>
				<?php echo $dststr;?>
			  </td>
			  <?php
				if ($filterent['proto'] == "TCP")
					$filterent['proto'] .= ":{$filterent['tcpflags']}";
			  ?>
			  <td class="listMRr" nowrap="nowrap"><?php echo htmlspecialchars($filterent['proto']);?></td>
			</tr>
			<?php if (isset($config['syslog']['filterdescriptions']) && $config['syslog']['filterdescriptions'] === "2"):?>
			<tr class="<?=$evenRowClass?>">
			  <td colspan="2" class="listMRDescriptionL listMRlr" />
			  <td colspan="4" class="listMRDescriptionR listMRr" nowrap="nowrap"><?=find_rule_by_number_buffer($filterent['rulenum'],$filterent['act']);?></td>
			</tr>
			<?php endif;
			endforeach; 
			buffer_rules_clear(); ?>
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
		<tfoot>
		<tr>
			<td align="left" valign="top" colspan="3">
				<form id="clearform" name="clearform" action="diag_logs_filter.php" method="post" style="margin-top: 14px;">
					<input id="submit" name="clear" type="submit" class="formbtn" value="<?=gettext("Clear log");?>" />
				</form>
			</td>
		</tr>
		</tfoot>
		</table>
		</div>
	</td>
  </tr>
</table>

<p><span class="vexpl"><a href="http://doc.pfsense.org/index.php/What_are_TCP_Flags%3F">TCP Flags</a>: F - FIN, S - SYN, A or . - ACK, R - RST, P - PSH, U - URG, E - ECE, C - CWR</span></p>

<?php include("fend.inc"); ?>
</body>
</html>
