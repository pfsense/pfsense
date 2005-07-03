#!/usr/local/bin/php
<?php
/* $Id$ */
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

require("guiconfig.inc");

if (!is_array($config['captiveportal']['allowedip']))
	$config['captiveportal']['allowedip'] = array();

allowedips_sort();
$a_allowedips = &$config['captiveportal']['allowedip'] ;

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;
		if (!file_exists($d_sysrebootreqd_path)) {
			$retval = captiveportal_allowedip_configure();
		}
		$savemsg = get_std_save_message($retval);
		if ($retval == 0) {
			if (file_exists($d_allowedipsdirty_path)) {
				config_lock();
				unlink($d_allowedipsdirty_path);
				config_unlock();
			}
		}
	}
}

if ($_GET['act'] == "del") {
	if ($a_allowedips[$_GET['id']]) {
		unset($a_allowedips[$_GET['id']]);
		write_config();
		touch($d_allowedipsdirty_path);
		header("Location: services_captiveportal_ip.php");
		exit;
	}
}

$pgtitle = "Services: Captive Portal: Allowed IP's";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<form action="services_captiveportal_ip.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_allowedipsdirty_path)): ?><p>
<?php print_info_box_np("The captive portal IP address configuration has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<?php endif; ?>
<div id="mainarea">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
<?php
	$tab_array = array();
	$tab_array[0] = array("Captive portal", false, "services_captiveportal.php");
	$tab_array[1] = array("Pass-through MAC", false, "services_captiveportal_mac.php");
	$tab_array[2] = array("Allowed IP addresses", true, "services_captiveportal_ip.php");
	$tab_array[3] = array("Users", false, "services_captiveportal_users.php");
	display_top_tabs($tab_array);
?>  
  </td></tr>
  <tr>
  <td class="tabcont">
  <table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
	  <td width="30%" class="listhdrr">IP address</td>
	  <td width="60%" class="listhdr">Description</td>
	  <td width="10%" class="list"></td>
	</tr>
  <?php $i = 0; foreach ($a_allowedips as $ip): ?>
	<tr>
	  <td class="listlr" ondblclick="document.location='services_captiveportal_ip_edit.php?id=<?=$i;?>';">
		<?php if($ip['dir'] == "to")
			echo "any <img src=\"in.gif\" width=\"11\" height=\"11\" align=\"absmiddle\">";
		?>
		<?=strtolower($ip['ip']);?>
		<?php if($ip['dir'] == "from")
			echo "<img src=\"in.gif\" width=\"11\" height=\"11\" align=\"absmiddle\"> any";
		?>
	  </td>
	  <td class="listbg" ondblclick="document.location='services_captiveportal_ip_edit.php?id=<?=$i;?>';">
		<font color="white"><?=htmlspecialchars($ip['descr']);?>&nbsp;</font>
	  </td>
	  <td valign="middle" nowrap class="list">
            <table border="0" cellspacing="0" cellpadding="1">
              <tr>
                <td valign="middle"><a href="services_captiveportal_ip_edit.php?id=<?=$i;?>"><img src="e.gif" width="17" height="17" border="0"></a></td>
		<td valign="middle"><a href="services_captiveportal_ip.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this address?')"><img src="x.gif" width="17" height="17" border="0"></a></td>
              </tr>
            </table>
          </td>
	</tr>
  <?php $i++; endforeach; ?>
	<tr>
	  <td class="list" colspan="2">&nbsp;</td>
	  <td class="list">
            <table border="0" cellspacing="0" cellpadding="1">
              <tr>
                <td valign="middle"><a href="services_captiveportal_ip_edit.php"><img src="plus.gif" width="17" height="17" border="0"></a></td>
              </td>
            </table>
          </td>
	</tr>
	<tr>
	<td colspan="2" class="list"><p class="vexpl"><span class="red"><strong>
	  Note:<br>
	  </strong></span>
	  Adding allowed IP addresses will allow IP access to/from these addresses through the captive portal without being taken to the portal page. This can be used for a web server serving images for the portal page or a DNS server on another network, for example. By specifying <em>from</em> addresses, it may be used to always allow pass-through access from a client behind the captive portal.</p>
	  <table border="0" cellspacing="0" cellpadding="0">
		<tr>
		  <td><span class="vexpl">any <img src="in.gif" width="11" height="11" align="absmiddle"> x.x.x.x </span></td>
		  <td><span class="vexpl">All connections <strong>to</strong> the IP address are allowed</span></td>
		</tr>
		<tr>
		  <td colspan="5" height="4"></td>
		</tr>
		<tr>
		  <td>x.x.x.x <span class="vexpl"><img src="in.gif" width="11" height="11" align="absmiddle"></span> any&nbsp;&nbsp;&nbsp; </td>
		  <td><span class="vexpl">All connections <strong>from</strong> the IP address are allowed </span></td>
		</tr>
	  </table></td>
	<td class="list">&nbsp;</td>
	</tr>
  </table>
  </td>
  </tr>
  </table>
  </div>
</form>
<?php include("fend.inc"); ?>
<script type="text/javascript">
NiftyCheck();
Rounded("div#mainarea","bl br","#FFF","#eeeeee","smooth");
</script>
</body>
</html>
