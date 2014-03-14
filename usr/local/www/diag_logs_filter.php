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

# --- AJAX RESOLVE ---
if (isset($_POST['resolve'])) {
	$ip = strtolower($_POST['resolve']);
	$res = (is_ipaddr($ip) ? gethostbyaddr($ip) : '');
	
	if ($res && $res != $ip)
		$response = array('resolve_ip' => $ip, 'resolve_text' => $res);
	else
		$response = array('resolve_ip' => $ip, 'resolve_text' => gettext("Cannot resolve"));
	
	echo json_encode(str_replace("\\","\\\\", $response)); // single escape chars can break JSON decode
	exit;
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

$rulenum = getGETPOSTsettingvalue('getrulenum', null);
if($rulenum) {
	list($rulenum, $type) = explode(',', $rulenum);
	$rule = find_rule_by_number($rulenum, $type);
	echo gettext("The rule that triggered this action is") . ":\n\n{$rule}";
	exit;
}

$filtersubmit = getGETPOSTsettingvalue('filtersubmit', null);
if ($filtersubmit) {
	$interfacefilter = getGETPOSTsettingvalue('interface', null);
	$filtertext = getGETPOSTsettingvalue('filtertext', "");
	$filterlogentries_qty = getGETPOSTsettingvalue('filterlogentries_qty', null);
}

$filterlogentries_submit = getGETPOSTsettingvalue('filterlogentries_submit', null);
if ($filterlogentries_submit) {
	$filterfieldsarray = array("act", "time", "interface", "srcip", "srcport", "dstip", "dstport", "proto", "tcpflags");

	$actpass = getGETPOSTsettingvalue('actpass', null);
	$actblock = getGETPOSTsettingvalue('actblock', null);
	$actreject = getGETPOSTsettingvalue('actreject', null);

	$filterfieldsarray['act'] = trim($actpass . " " . $actblock . " " . $actreject);
	$filterfieldsarray['act'] = $filterfieldsarray['act'] ? $filterfieldsarray['act'] : 'All';
	$filterfieldsarray['time'] = getGETPOSTsettingvalue('filterlogentries_time', null);
	$filterfieldsarray['interface'] = getGETPOSTsettingvalue('filterlogentries_interfaces', null);
	$filterfieldsarray['srcip'] = getGETPOSTsettingvalue('filterlogentries_sourceipaddress', null);
	$filterfieldsarray['srcport'] = getGETPOSTsettingvalue('filterlogentries_sourceport', null);
	$filterfieldsarray['dstip'] = getGETPOSTsettingvalue('filterlogentries_destinationipaddress', null);
	$filterfieldsarray['dstport'] = getGETPOSTsettingvalue('filterlogentries_destinationport', null);
	$filterfieldsarray['proto'] = getGETPOSTsettingvalue('filterlogentries_protocol', null);
	$filterfieldsarray['tcpflags'] = getGETPOSTsettingvalue('filterlogentries_protocolflags', null);
	$filterlogentries_qty = getGETPOSTsettingvalue('filterlogentries_qty', null);
}

$filter_logfile = "{$g['varlog_path']}/filter.log";

$nentries = $config['syslog']['nentries'];

# Override Display Quantity
if ($filterlogentries_qty)
	$nentries = $filterlogentries_qty;

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
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="logs filter">
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
	$tab_array[] = array(gettext("Normal View"), true, "/diag_logs_filter.php");
	$tab_array[] = array(gettext("Dynamic View"), false, "/diag_logs_filter_dynamic.php");
	$tab_array[] = array(gettext("Summary View"), false, "/diag_logs_filter_summary.php");
	display_top_tabs($tab_array);
?>
		</td>
	</tr>
  <tr>
    <td>
	<div id="mainarea">
		<table class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0" sortableMultirow="<?=$config['syslog']['filterdescriptions'] === "2"?2:1?>" summary="main area">
			<thead>
			<tr>
				<td colspan="<?=(!isset($config['syslog']['rawfilter']))?7:2?>" align="left" valign="middle">
				<div id="filterlogentries_show" class="widgetconfigdiv" style=<?=(!isset($config['syslog']['rawfilter']))?"":"display:none"?>>
					<form id="filterlogentries" name="filterlogentries" action="diag_logs_filter.php" method="post">
						<?php 
							$Include_Act = explode(",", str_replace(" ", ",", $filterfieldsarray['act']));
							if ($filterfieldsarray['interface'] == "All") $interface = "";
						?>
					<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="action">
					<tr>
						<td rowspan="2">
							<div align="center"><?=gettext("Action");?></div>
							<div align="left">
							<input id="actpass"   name="actpass"   type="checkbox" value="Pass"   <?php if (in_arrayi('Pass',   $Include_Act)) echo "checked=\"checked\""; ?> /> Pass<br />
							<input id="actblock"  name="actblock"  type="checkbox" value="Block"  <?php if (in_arrayi('Block',  $Include_Act)) echo "checked=\"checked\""; ?> /> Block<br />
							<input id="actreject" name="actreject" type="checkbox" value="Reject" <?php if (in_arrayi('Reject', $Include_Act)) echo "checked=\"checked\""; ?> /> Reject<br />
							</div>
						</td>
						<td>
							<div align="center"><?=gettext("Time");?></div>
							<div align="center"><input id="filterlogentries_time" name="filterlogentries_time" class="formfld search" type="text" size="12" value="<?= $filterfieldsarray['time'] ?>" /></div>
						</td>
						<td>
							<div align="center"><?=gettext("Source IP Address");?></div>
							<div align="center"><input id="filterlogentries_sourceipaddress" name="filterlogentries_sourceipaddress" class="formfld search" type="text" size="35" value="<?= $filterfieldsarray['srcip'] ?>" /></div>
						</td>
						<td>
							<div align="center"><?=gettext("Source Port");?></div>
							<div align="center"><input id="filterlogentries_sourceport" name="filterlogentries_sourceport" class="formfld search" type="text" size="10" value="<?= $filterfieldsarray['srcport'] ?>" /></div>
						</td>
						<td>
							<div align="center"><?=gettext("Protocol");?></div>
							<div align="center"><input id="filterlogentries_protocol" name="filterlogentries_protocol" class="formfld search" type="text" size="5" value="<?= $filterfieldsarray['proto'] ?>" /></div>
						</td>
						<td>
							<div align="center" style="vertical-align:top;"><?=gettext("Quantity");?></div>
							<div align="center" style="vertical-align:top;"><input id="filterlogentries_qty" name="filterlogentries_qty" class="" type="text" size="6" value="<?= $filterlogentries_qty ?>" /></div>
						</td>
					</tr>
					<tr>
						<td valign="top">
							<div align="center"><?=gettext("Interface");?></div>
							<div align="center"><input id="filterlogentries_interfaces" name="filterlogentries_interfaces" class="formfld search" type="text" size="12" value="<?= $filterfieldsarray['interface'] ?>" /></div>
						</td>
						<td valign="top">
							<div align="center"><?=gettext("Destination IP Address");?></div>
							<div align="center"><input id="filterlogentries_destinationipaddress" name="filterlogentries_destinationipaddress" class="formfld search" type="text" size="35" value="<?= $filterfieldsarray['dstip'] ?>" /></div>
						</td>
						<td valign="top">
							<div align="center"><?=gettext("Destination Port");?></div>
							<div align="center"><input id="filterlogentries_destinationport" name="filterlogentries_destinationport" class="formfld search" type="text" size="10" value="<?= $filterfieldsarray['dstport'] ?>" /></div>
						</td>
						<td valign="top">
							<div align="center"><?=gettext("Protocol Flags");?></div>
							<div align="center"><input id="filterlogentries_protocolflags" name="filterlogentries_protocolflags" class="formfld search" type="text" size="5" value="<?= $filterfieldsarray['tcpflags'] ?>" /></div>
						</td>
						<td valign="bottom">
							<div align="center"><input id="filterlogentries_submit" name="filterlogentries_submit" type="submit" class="formbtn" value="<?=gettext("Filter");?>" /></div>
						</td>
					</tr>
					<tr>
						<td></td>
						<td colspan="5">
							<?printf(gettext('Matches %1$s regular expression%2$s.'), '<a target="_blank" href="http://www.php.net/manual/en/book.pcre.php">', '</a>');?>&nbsp;&nbsp;
							<?=gettext("Precede with exclamation (!) as first character to exclude match.");?>&nbsp;&nbsp;
						</td>
					</tr>
					</table>
					</form>
				</div>
				<div id="filterform_show" class="widgetconfigdiv" style=<?=(!isset($config['syslog']['rawfilter']))?"display:none":""?>>
					<form id="filterform" name="filterform" action="diag_logs_filter.php" method="post">
					<table width="0%" border="0" cellpadding="0" cellspacing="0" summary="firewall log">
					<tr>
						<td>
							<div align="center" style="vertical-align:top;"><?=gettext("Interface");?></div>
							<div align="center" style="vertical-align:top;">
							<select name="interface" onChange="dst_change(this.value,iface_old,document.iform.dsttype.value);iface_old = document.iform.interface.value;typesel_change();">
							<option value="" <?=$interfacefilter?"":"selected=\"selected\""?>>*Any interface</option>
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
							<option value="<?=$iface;?>" <?=($iface==$interfacefilter)?"selected=\"selected\"":"";?>><?=htmlspecialchars($ifacename);?></option>
							<?php endforeach; ?>
							</select>
							</div>
						</td>
						<td>
							<div align="center" style="vertical-align:top;"><?=gettext("Filter expression");?></div>
							<div align="center" style="vertical-align:top;"><input id="filtertext" name="filtertext" class="formfld search" style="vertical-align:top;" type="text" size="35" value="<?=$filtertext?>" /></div>
						</td>
						<td>
							<div align="center" style="vertical-align:top;"><?=gettext("Quantity");?></div>
							<div align="center" style="vertical-align:top;"><input id="filterlogentries_qty" name="filterlogentries_qty" class="" style="vertical-align:top;" type="text" size="6" value="<?= $filterlogentries_qty ?>" /></div>
						</td>
						<td>
							<div align="center" style="vertical-align:top;">&nbsp;</div>
							<div align="center" style="vertical-align:top;"><input id="filtersubmit" name="filtersubmit" type="submit" class="formbtn" style="vertical-align:top;" value="<?=gettext("Filter");?>" /></div>
						</td>
					</tr>
					<tr>
						<td></td>
						<td colspan="2">
							<?printf(gettext('Matches %1$s regular expression%2$s.'), '<a target="_blank" href="http://www.php.net/manual/en/book.pcre.php">', '</a>');?>&nbsp;&nbsp;
						</td>
					</tr>
					</table>
					</form>
				</div>
				<div style="float: right; vertical-align:middle">
					<br />
					<?php if (!isset($config['syslog']['rawfilter']) && (isset($config['syslog']['filterdescriptions']) && $config['syslog']['filterdescriptions'] === "2")):?>
					<a href="#" onclick="toggleListDescriptions()">Show/hide rule descriptions</a>
					<?php endif;?>
				</div>
				</td>	
			</tr>
<?php if (!isset($config['syslog']['rawfilter'])):
	$iflist = get_configured_interface_with_descr(false, true);
	if ($iflist[$interfacefilter])
		$interfacefilter = $iflist[$interfacefilter];
	if ($filterlogentries_submit) 
		$filterlog = conv_log_filter($filter_logfile, $nentries, $nentries + 100, $filterfieldsarray);
	else
		$filterlog = conv_log_filter($filter_logfile, $nentries, $nentries + 100, $filtertext, $interfacefilter);
?>
			<tr>
			  <td colspan="<?=$config['syslog']['filterdescriptions']==="1"?7:6?>" class="listtopic">
				<?php if ( (!$filtertext) && (!$filterfieldsarray) )
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
			  <td class="listMRlr nowrap" align="center" sorttable_customkey="<?=$filterent['act']?>">
			  <center>
			  <a onclick="javascript:getURL('diag_logs_filter.php?getrulenum=<?php echo "{$filterent['rulenum']},{$filterent['act']}"; ?>', outputrule);">
			  <img border="0" src="<?php echo find_action_image($filterent['act']);?>" width="11" height="11" align="middle" alt="<?php echo $filterent['act'];?>" title="<?php echo $filterent['act'];?>" />
			  <?php if ($filterent['count']) echo $filterent['count'];?></a></center></td>
			  <td class="listMRr" nowrap="nowrap"><?php echo htmlspecialchars($filterent['time']);?></td>
			  <td class="listMRr" nowrap="nowrap">
				<?php if ($filterent['direction'] == "out"): ?>
				<img border="0" src="/themes/<?= $g['theme']; ?>/images/icons/out.gif" alt="Direction=OUT" title="Direction=OUT"/>
				<?php endif; ?>
				<?php echo htmlspecialchars($filterent['interface']);?></td>
			  <?php 
			  if ($config['syslog']['filterdescriptions'] === "1")
				echo("<td class=\"listMRr nowrap\">".find_rule_by_number_buffer($filterent['rulenum'],$filterent['act'])."</td>");
				
			  $int = strtolower($filterent['interface']);
			  $proto = strtolower($filterent['proto']);
			  if($filterent['version'] == '6') {
				$ipproto = "inet6";
				$filterent['srcip'] = "[{$filterent['srcip']}]";
				$filterent['dstip'] = "[{$filterent['dstip']}]";
			  } else {
			        $ipproto = "inet";
			  }

			  $srcstr = $filterent['srcip'] . get_port_with_service($filterent['srcport'], $proto);
			  $src_htmlclass = str_replace(array('.', ':'), '-', $filterent['srcip']);
			  $dststr = $filterent['dstip'] . get_port_with_service($filterent['dstport'], $proto);
			  $dst_htmlclass = str_replace(array('.', ':'), '-', $filterent['dstip']);
			  ?>
			  <td class="listMRr nowrap">
				<img onclick="javascript:resolve_with_ajax('<?php echo "{$filterent['srcip']}"; ?>');" title="<?=gettext("Click to resolve");?>" class="ICON-<?= $src_htmlclass; ?>" border="0" src="/themes/<?= $g['theme']; ?>/images/icons/icon_log.gif" alt="Icon Reverse Resolve with DNS"/>
				<a href="easyrule.php?<?php echo "action=block&amp;int={$int}&amp;src={$filterent['srcip']}&amp;ipproto={$ipproto}"; ?>" title="<?=gettext("Easy Rule: Add to Block List");?>" onclick="return confirm('<?=gettext("Do you really want to add this BLOCK rule?")."\n\n".gettext("Easy Rule is still experimental.")."\n".gettext("Continue at risk of your own peril.")."\n".gettext("Backups are also nice.")?>')">
				<img border="0" src="/themes/<?= $g['theme']; ?>/images/icons/icon_block_add.gif" alt="Icon Easy Rule: Add to Block List" /></a>
				<?php echo $srcstr . '<span class="RESOLVE-' . $src_htmlclass . '"></span>';?>
			  </td>
			  <td class="listMRr nowrap">
				<img onclick="javascript:resolve_with_ajax('<?php echo "{$filterent['dstip']}"; ?>');" title="<?=gettext("Click to resolve");?>" class="ICON-<?= $dst_htmlclass; ?>" border="0" src="/themes/<?= $g['theme']; ?>/images/icons/icon_log.gif" alt="Icon Reverse Resolve with DNS"/>
				<a href="easyrule.php?<?php echo "action=pass&amp;int={$int}&amp;proto={$proto}&amp;src={$filterent['srcip']}&amp;dst={$filterent['dstip']}&amp;dstport={$filterent['dstport']}&amp;ipproto={$ipproto}"; ?>" title="<?=gettext("Easy Rule: Pass this traffic");?>" onclick="return confirm('<?=gettext("Do you really want to add this PASS rule?")."\n\n".gettext("Easy Rule is still experimental.")."\n".gettext("Continue at risk of your own peril.")."\n".gettext("Backups are also nice.");?>')">
				<img border="0" src="/themes/<?= $g['theme']; ?>/images/icons/icon_pass_add.gif" alt="Icon Easy Rule: Pass this traffic" /></a>
				<?php echo $dststr . '<span class="RESOLVE-' . $dst_htmlclass . '"></span>';?>
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
			  <td colspan="4" class="listMRDescriptionR listMRr nowrap"><?=find_rule_by_number_buffer($filterent['rulenum'],$filterent['act']);?></td>
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

<p><span class="vexpl"><a href="https://doc.pfsense.org/index.php/What_are_TCP_Flags%3F">TCP Flags</a>: F - FIN, S - SYN, A or . - ACK, R - RST, P - PSH, U - URG, E - ECE, W - CWR</span></p>

<?php include("fend.inc"); ?>

<!-- AJAXY STUFF -->
<script type="text/javascript">

function resolve_with_ajax(ip_to_resolve) {
	var url = "/diag_logs_filter.php";

	jQuery.ajax(
		url,
		{
			type: 'post',
			dataType: 'json',
			data: {
				resolve: ip_to_resolve,
				},
			complete: resolve_ip_callback
		});

}

function resolve_ip_callback(transport) {
	var response = jQuery.parseJSON(transport.responseText);
	var resolve_class = htmlspecialchars(response.resolve_ip.replace(/[.:]/g, '-'));
	var resolve_text = '<small><br />' + htmlspecialchars(response.resolve_text) + '</small>';
	
	jQuery('span.RESOLVE-' + resolve_class).html(resolve_text);
	jQuery('img.ICON-' + resolve_class).removeAttr('title');
	jQuery('img.ICON-' + resolve_class).removeAttr('alt');
	jQuery('img.ICON-' + resolve_class).attr('src', '/themes/<?= $g['theme']; ?>/images/icons/icon_log_d.gif');
	jQuery('img.ICON-' + resolve_class).prop('onclick', null); 
	  // jQuery cautions that "removeAttr('onclick')" fails in some versions of IE
}

// From http://stackoverflow.com/questions/5499078/fastest-method-to-escape-html-tags-as-html-entities
function htmlspecialchars(str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&apos;');
}
</script>

</body>
</html>
