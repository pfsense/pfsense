<?php
/*
	services_captiveportal_ip.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2004 Dinesh Nair <dinesh@alphaque.com>
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
	pfSense_BUILDER_BINARIES:	/sbin/ipfw
	pfSense_MODULE:	captiveportal
*/

##|+PRIV
##|*IDENT=page-services-captiveportal-allowedips
##|*NAME=Services: Captive portal: Allowed IPs page
##|*DESCR=Allow access to the 'Services: Captive portal: Allowed IPs' page.
##|*MATCH=services_captiveportal_ip.php*
##|-PRIV

require("guiconfig.inc");
require("functions.inc");
require_once("filter.inc");
require("shaper.inc");
require("captiveportal.inc");

$cpzone = $_GET['zone'];
if (isset($_POST['zone']))
        $cpzone = $_POST['zone'];

if (empty($cpzone) || empty($config['captiveportal'][$cpzone])) {
        header("Location: services_captiveportal_zones.php");
        exit;
}

if (!is_array($config['captiveportal']))
        $config['captiveportal'] = array();
$a_cp =& $config['captiveportal'];

$pgtitle = array(gettext("Services"),gettext("Captive portal"), $a_cp[$cpzone]['zone']);
$shortcut_section = "captiveportal";

if ($_GET['act'] == "del") {
	$a_allowedips =& $config['captiveportal'][$cpzone]['allowedip'];
	if ($a_allowedips[$_GET['id']]) {
		$ipent = $a_allowedips[$_GET['id']];
		
		if (isset($config['captiveportal'][$cpzone]['enable'])) {
			$mask = (!empty($ipent['sn'])) ? $ipent['sn'] : 32;
			
			$ipfw = pfSense_ipfw_getTablestats($cpzone, 3, $ipent['ip'], $mask);
			pfSense_ipfw_Tableaction($cpzone, IP_FW_TABLE_DEL, 3, $ipent['ip'], $mask);
			pfSense_ipfw_Tableaction($cpzone, IP_FW_TABLE_DEL, 4, $ipent['ip'], $mask);
			
			if (is_array($ipfw)) {
				captiveportal_free_dn_ruleno($ipfw['dnpipe']);
				pfSense_pipe_action("pipe delete {$ipfw['dnpipe']}");
				pfSense_pipe_action("pipe delete " . ($ipfw['dnpipe']+1));
			}
		}
		
		unset($a_allowedips[$_GET['id']]);
		write_config();
		header("Location: services_captiveportal_ip.php?zone={$cpzone}");
		exit;
	}
}


include("head.inc");
?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="services_captiveportal_ip.php" method="post">
<input type="hidden" name="zone" id="zone" value="<?=htmlspecialchars($cpzone);?>" />
<?php if ($savemsg) print_info_box($savemsg); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="captiveportal ip addresses">
  <tr><td class="tabnavtbl">
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("Captive portal(s)"), false, "services_captiveportal.php?zone={$cpzone}");
	$tab_array[] = array(gettext("MAC"), false, "services_captiveportal_mac.php?zone={$cpzone}");
	$tab_array[] = array(gettext("Allowed IP addresses"), true, "services_captiveportal_ip.php?zone={$cpzone}");
	$tab_array[] = array(gettext("Allowed Hostnames"), false, "services_captiveportal_hostname.php?zone={$cpzone}");
	$tab_array[] = array(gettext("Vouchers"), false, "services_captiveportal_vouchers.php?zone={$cpzone}");
	$tab_array[] = array(gettext("File Manager"), false, "services_captiveportal_filemanager.php?zone={$cpzone}");
	display_top_tabs($tab_array, true);
?>
  </td></tr>
  <tr>
  <td class="tabcont">
  <table width="100%" border="0" cellpadding="0" cellspacing="0" summary="main">
	<tr>
	  <td width="40%" class="listhdrr"><?=gettext("IP address"); ?></td>
	  <td width="50%" class="listhdr"><?=gettext("Description"); ?></td>
	  <td width="10%" class="list">
		<table border="0" cellspacing="0" cellpadding="1" summary="add">
		   <tr>
			<td width="17" height="17"></td>
			<td><a href="services_captiveportal_ip_edit.php?zone=<?=$cpzone;?>"><img src="/themes/<?php echo $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("add address"); ?>" width="17" height="17" border="0" alt="add" /></a></td>
		   </tr>
		</table>
	  </td>
	</tr>
<?php	if (is_array($a_cp[$cpzone]['allowedip'])):
		$i = 0; foreach ($a_cp[$cpzone]['allowedip'] as $ip): ?>
	<tr ondblclick="document.location='services_captiveportal_ip_edit.php?zone=<?=$cpzone;?>&amp;id=<?=$i;?>'">
	  <td class="listlr">
		<?php
		if($ip['dir'] == "to") {
			echo "any <img src=\"/themes/{$g['theme']}/images/icons/icon_in.gif\" width=\"11\" height=\"11\" align=\"middle\" alt=\"in\" /> ";
		}
		if($ip['dir'] == "both") {
			echo "<img src=\"/themes/{$g['theme']}/images/icons/icon_pass.gif\" width=\"11\" height=\"11\" align=\"middle\" alt=\"pass\" />   ";
		}
		echo strtolower($ip['ip']);
		if($ip['sn'] != "32" && is_numeric($ip['sn'])) {
			$sn = $ip['sn'];
			echo "/$sn";	
		}
		if($ip['dir'] == "from") {
			echo "<img src=\"/themes/{$g['theme']}/images/icons/icon_in.gif\" width=\"11\" height=\"11\" align=\"middle\" alt=\"any\" /> any";
		}
		
		?>	
	  </td>
	  <td class="listbg">
		<?=htmlspecialchars($ip['descr']);?>&nbsp;
	  </td>
	  <td valign="middle" class="list nowrap"><a href="services_captiveportal_ip_edit.php?zone=<?=$cpzone;?>&amp;id=<?=$i;?>"><img src="/themes/<?php echo $g['theme']; ?>/images/icons/icon_e.gif" title="<?=gettext("edit address"); ?>" width="17" height="17" border="0" alt="edit" /></a>
		 &nbsp;<a href="services_captiveportal_ip.php?zone=<?=$cpzone;?>&amp;act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this address?"); ?>')"><img src="/themes/<?php echo $g['theme']; ?>/images/icons/icon_x.gif" title="<?=gettext("delete address"); ?>" width="17" height="17" border="0" alt="delete" /></a></td>
	</tr>
  <?php $i++; endforeach; endif; ?>
	<tr>
	  <td class="list" colspan="2">&nbsp;</td>
	  <td class="list">
		<table border="0" cellspacing="0" cellpadding="1" summary="add">
		   <tr>
			<td width="17" height="17"></td>
			<td><a href="services_captiveportal_ip_edit.php?zone=<?=$cpzone;?>"><img src="/themes/<?php echo $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("add address"); ?>" width="17" height="17" border="0" alt="add" /></a></td>
		   </tr>
		</table>
	  </td>
	</tr>
	<tr>
	<td colspan="2" class="list"><p class="vexpl"><span class="red"><strong>
	  <?=gettext("Note:"); ?><br />
	  </strong></span>
	  <?=gettext("Adding allowed IP addresses will allow IP access to/from these addresses through the captive portal without being taken to the portal page. This can be used for a web server serving images for the portal page or a DNS server on another network, for example. By specifying <em>from</em> addresses, it may be used to always allow pass-through access from a client behind the captive portal."); ?></p>
	  <table border="0" cellspacing="0" cellpadding="0" summary="icons">
		<tr>
		  <td><span class="vexpl"><?=gettext("any"); ?> <img src="/themes/<?=$g['theme'];?>/images/icons/icon_in.gif" width="11" height="11" align="middle" alt="in" /> x.x.x.x </span></td>
		  <td><span class="vexpl"><?=gettext("All connections"); ?> <strong><?=gettext("to"); ?></strong> <?=gettext("the IP address are allowed"); ?></span></td>
		</tr>
		<tr>
		  <td colspan="5" height="4"></td>
		</tr>
		<tr>
		  <td>x.x.x.x <span class="vexpl"><img src="/themes/<?=$g['theme'];?>/images/icons/icon_in.gif" width="11" height="11" align="middle" alt="in" /></span> <?=gettext("any"); ?>&nbsp;&nbsp;&nbsp; </td>
		  <td><span class="vexpl"><?=gettext("All connections"); ?> <strong><?=gettext("from"); ?></strong> <?=gettext("the IP address are allowed"); ?> </span></td>
		</tr>
		<tr>
		  <td><span class="vexpl"><img src="/themes/<?=$g['theme'];?>/images/icons/icon_pass.gif" width="11" height="11" align="right" alt="pass" /></span>&nbsp;&nbsp;&nbsp;&nbsp; </td>
		  <td><span class="vexpl"> All connections <strong>to</strong> and <strong>from</strong> the IP address are allowed </span></td>
		</tr>
	  </table></td>
	<td class="list">&nbsp;</td>
	</tr>
  </table>
  </td>
  </tr>
  </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>