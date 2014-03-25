<?php 
/* $Id$ */
/*
	status_captiveportal.php
	part of m0n0wall (http://m0n0.ch/wall)
	
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
	pfSense_MODULE:	captiveportal
*/

##|+PRIV
##|*IDENT=page-status-captiveportal
##|*NAME=Status: Captive portal page
##|*DESCR=Allow access to the 'Status: Captive portal' page.
##|*MATCH=status_captiveportal.php*
##|-PRIV

require("guiconfig.inc");
require("functions.inc");
require_once("filter.inc");
require("shaper.inc");
require("captiveportal.inc");

$cpzone = $_GET['zone'];
if (isset($_POST['zone']))
	$cpzone = $_POST['zone'];

if ($_GET['act'] == "del" && !empty($cpzone)) {
	captiveportal_disconnect_client($_GET['id']);
	Header("Location: status_captiveportal.php?zone={$cpzone}");
	exit;
}

$pgtitle = array(gettext("Status: Captive portal"));
$shortcut_section = "captiveportal";

if (!is_array($config['captiveportal']))
        $config['captiveportal'] = array();
$a_cp =& $config['captiveportal'];

include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php

flush();

function clientcmp($a, $b) {
	global $order;
	return strcmp($a[$order], $b[$order]);
}

if (!empty($cpzone)) {
	$cpdb = captiveportal_read_db();

	if ($_GET['order']) {
		if ($_GET['order'] == "ip")
			$order = 2;
		else if ($_GET['order'] == "mac")
			$order = 3;
		else if ($_GET['order'] == "user")
			$order = 4;
		else if ($_GET['order'] == "lastact")
			$order = 5;
		else
			$order = 0;
		usort($cpdb, "clientcmp");
	}
}

// Load MAC-Manufacturer table
$mac_man = load_mac_manufacturer_table();

?>

<?php if (!empty($cpzone) && isset($config['voucher'][$cpzone]['enable'])): ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="tab pane">
<tr><td class="tabnavtbl">
<?php 
	$tab_array = array();
        $tab_array[] = array(gettext("Active Users"), true, "status_captiveportal.php?zone={$cpzone}");
        $tab_array[] = array(gettext("Active Vouchers"), false, "status_captiveportal_vouchers.php?zone={$cpzone}");
        $tab_array[] = array(gettext("Voucher Rolls"), false, "status_captiveportal_voucher_rolls.php?zone={$cpzone}");
        $tab_array[] = array(gettext("Test Vouchers"), false, "status_captiveportal_test.php?zone={$cpzone}");
	$tab_array[] = array(gettext("Expire Vouchers"), false, "status_captiveportal_expire.php?zone={$cpzone}");
        display_top_tabs($tab_array);
?> 
</td></tr>
<tr>
<td class="tabcont">
<?php endif; ?>

<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr>
	<td width="20%" class="vncell" valign="top"> 
               <br/><?=gettext("Captive Portal Zone"); ?><br/<br/>
	</td>
	<td "class="vncell" width="30%" align="center"> 
	<form action="status_captiveportal.php" method="post" enctype="multipart/form-data" name="form1" id="form1">
		<select name="zone" class="formselect" onchange="document.form1.submit()">
		echo "<option value="">none</option>\n";
		<?php foreach ($a_cp as $cpkey => $cp) {
		       echo "<option value='{$cpkey}' ";
		       if ($cpzone == $cpkey)
			       echo "selected";
		       echo ">" . htmlspecialchars($cp['zone']) . "</option>\n";
		       }
               ?>
               </select>
		<br/>
	</form>
	</td>
	<td colspan="3" width="50%"></td>
  </tr>
  <tr><td colspan="5"><br/></tr>
<?php if (!empty($cpzone)): ?>
  <tr>
	<td colspan="5" valign="top" class="listtopic"><?=gettext("Captiveportal status");?></td>
  </tr>
  <tr>
    <td class="listhdrr"><a href="?zone=<?=$cpzone?>&amp;order=ip&amp;showact=<?=htmlspecialchars($_GET['showact']);?>"><?=gettext("IP address");?></a></td>
    <td class="listhdrr"><a href="?zone=<?=$cpzone?>&amp;order=mac&amp;showact=<?=htmlspecialchars($_GET['showact']);?>"><?=gettext("MAC address");?></a></td>
    <td class="listhdrr"><a href="?zone=<?=$cpzone?>&amp;order=user&amp;showact=<?=htmlspecialchars($_GET['showact']);?>"><?=gettext("Username");?></a></td>
	<?php if ($_GET['showact']): ?>
    <td class="listhdrr"><a href="?zone=<?=$cpzone?>&amp;order=start&amp;showact=<?=htmlspecialchars($_GET['showact']);?>"><?=gettext("Session start");?></a></td>
    <td class="listhdr"><a href="?zone=<?=$cpzone?>&amp;order=lastact&amp;showact=<?=htmlspecialchars($_GET['showact']);?>"><?=gettext("Last activity");?></a></td>
	<?php else: ?>
    <td class="listhdr"><a href="?zone=<?=$cpzone?>&amp;order=start&amp;showact=<?=htmlspecialchars($_GET['showact']);?>"><?=gettext("Session start");?></a></td>
	<?php endif; ?>
    <td class="list sort_ignore"></td>
  </tr>
<?php foreach ($cpdb as $cpent): ?>
  <tr>
    <td class="listlr"><?=$cpent[2];?></td>
	<td class="listr">
		<?php
		$mac=trim($cpent[3]);
		if (!empty($mac)) {
			$mac_hi = strtoupper($mac[0] . $mac[1] . $mac[3] . $mac[4] . $mac[6] . $mac[7]);
			print htmlentities($mac);
			if(isset($mac_man[$mac_hi])){ print "<br/><font size=\"-2\"><i>{$mac_man[$mac_hi]}</i></font>"; }
		}
		?>&nbsp;
	</td>
    <td class="listr"><?=$cpent[4];?>&nbsp;</td>
    <td class="listr"><?=htmlspecialchars(date("m/d/Y H:i:s", $cpent[0]));?></td>
	<?php if ($_GET['showact']):
	$last_act = captiveportal_get_last_activity($cpent[2]); ?>
    <td class="listr"><?php if ($last_act != 0) echo htmlspecialchars(date("m/d/Y H:i:s", $last_act));?></td>
	<?php endif; ?>
	<td valign="middle" class="list" nowrap>
	<a href="?zone=<?=$cpzone;?>&order=<?=$_GET['order'];?>&showact=<?=htmlspecialchars($_GET['showact']);?>&act=del&id=<?=$cpent[5];?>" onclick="return confirm('<?=gettext("Do you really want to disconnect this client?");?>')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" title="<?=gettext("Disconnect");?>"></a></td>
  </tr>
<?php endforeach; endif; ?>
</table>

<?php if (!empty($cpzone) && isset($config['voucher'][$cpzone]['enable'])): ?>
</td>
</tr>
</table>
<?php endif; ?>

<form action="status_captiveportal.php" method="get" style="margin: 14px;">
<input type="hidden" name="order" value="<?=htmlspecialchars($_GET['order']);?>" />
<?php if (!empty($cpzone)): ?>
<?php if ($_GET['showact']): ?>
<input type="hidden" name="showact" value="0" />
<input type="submit" class="formbtn" value="<?=gettext("Don't show last activity");?>" />
<?php else: ?>
<input type="hidden" name="showact" value="1" />
<input type="submit" class="formbtn" value="<?=gettext("Show last activity");?>" />
<?php endif; ?>
<input type="hidden" name="zone" value="<?=htmlspecialchars($cpzone);?>" />
<?php endif; ?>
</form>
<?php include("fend.inc"); ?>

</body>
</html>
