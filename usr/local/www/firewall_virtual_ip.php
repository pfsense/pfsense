<?php
/* $Id$ */
/*
	firewall_virtual_ip.php
	part of pfSense (http://www.pfsense.com/)

	Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
	All rights reserved.

	Includes code from m0n0wall which is:
	Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	Includes code from pfSense which is:
	Copyright (C) 2004-2005 Scott Ullrich <geekgod@pfsense.com>.
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

if (!is_array($config['virtualip']['vip'])) {
	$config['virtualip']['vip'] = array();
}
$a_vip = &$config['virtualip']['vip'];

if ($_GET['act'] == "del") {
	if ($a_vip[$_GET['id']]) {
		/* make sure no inbound NAT mappings reference this entry */
		if (is_array($config['nat']['rule'])) {
			foreach ($config['nat']['rule'] as $rule) {
				if($rule['external-address'] <> "") {
					if ($rule['external-address'] == $a_vip[$_GET['id']]['ipaddr']) {
						$input_errors[] = "This entry cannot be deleted because it is still referenced by at least one NAT mapping.";
						unlink("/tmp/carp_reboot_needed");
						break;
					}
				}
			}
		}

		if (!$input_errors) {
			if($a_vip[$_GET['id']]['mode'] == "carp")  
				touch("/tmp/carp_reboot_needed");
			unset($a_vip[$_GET['id']]);
			write_config();
			touch($d_vipconfdirty_path);
			header("Location: firewall_virtual_ip.php");
			exit;
		}
	}
}

if ($_POST['apply']) {
	if (file_exists("/tmp/carp_reboot_needed")) {
		$savemsg = "The firewall is now rebooting, please wait.";	
		unlink_if_exists($d_vipconfdirty_path);
	}
}

if ($_POST) {
			unlink_if_exists($d_vipconfdirty_path);
}

$pgtitle = "Firewall: Virtual IP Addresses";
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<form action="firewall_virtual_ip.php" method="post">
<?php 
	if ($input_errors) 
		print_input_errors($input_errors);
	else if ($savemsg) 
		print_info_box($savemsg);
	else if (file_exists("/tmp/carp_reboot_needed"))
		print_info_box_np("The VIP configuration changes require a reboot to apply. <br>THE FIREWALL WILL REBOOT WHEN YOU CLICK APPLY CHANGES.<br>You must manually reboot your secondary firewall for these changes to apply to it.");
	else if (file_exists($d_vipconfdirty_path))
		print_info_box_np("The VIP configuration has been changed.<br>You must apply the changes in order for them to take effect.");
?>
<p>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
  <?php
        /* active tabs */
        $tab_array = array();
        $tab_array[] = array("Virtual IPs", true, "firewall_virtual_ip.php");
        $tab_array[] = array("CARP Settings", false, "pkg_edit.php?xml=carp_settings.xml&id=0");
        display_top_tabs($tab_array);
  ?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
              <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                  <td width="30%" class="listhdrr">Virtual IP address</td>
                  <td width="10%" class="listhdrr">Type</td>
                  <td width="40%" class="listhdr">Description</td>
                  <td width="10%" class="list">
                    <table border="0" cellspacing="0" cellpadding="1">
                      <tr>
			<td width="17"></td>
                        <td valign="middle"><a href="firewall_virtual_ip_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
                      </tr>
                    </table>
		  </td>
		</tr>
			  <?php $i = 0; foreach ($a_vip as $vipent): ?>
			  <?php if($vipent['subnet'] <> "" or $vipent['range'] <> "" or
			        $vipent['subnet_bits'] <> "" or $vipent['range']['from'] <> ""): ?>
                <tr>
                  <td class="listlr" ondblclick="document.location='firewall_virtual_ip_edit.php?id=<?=$i;?>';">
					<?php	if (($vipent['type'] == "single") || ($vipent['type'] == "network"))
								echo "{$vipent['subnet']}/{$vipent['subnet_bits']}";
							if ($vipent['type'] == "range")
								echo "{$vipent['range']['from']}-{$vipent['range']['to']}";
					?>
					<?php if($vipent['mode'] == "carp") echo " (vhid {$vipent['vhid']})"; ?>
                  </td>
                  <td class="listlr" align="center" ondblclick="document.location='firewall_virtual_ip_edit.php?id=<?=$i;?>';">
                    <? if($vipent['mode'] == "proxyarp") echo "<img src='./themes/".$g['theme']."/images/icons/icon_parp.gif' title='Proxy ARP'>"; elseif($vipent['mode'] == "carp") echo "<img src='./themes/".$g['theme']."/images/icons/icon_carp.gif' title='CARP'>"; elseif($vipent['mode'] == "other") echo "<img src='./themes/".$g['theme']."/images/icons/icon_other.gif' title='Other'>";?>
                  </td>
                  <td class="listbg" ondblclick="document.location='firewall_virtual_ip_edit.php?id=<?=$i;?>';">
                    <font color="#FFFFFF"><?=htmlspecialchars($vipent['descr']);?>&nbsp;
                  </td>
                  <td class="list" nowrap>
                    <table border="0" cellspacing="0" cellpadding="1">
                      <tr>
                        <td valign="middle"><a href="firewall_virtual_ip_edit.php?id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0"></a></td>
                        <td valign="middle"><a href="firewall_virtual_ip.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this entry?')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0"></a></td>
                      </tr>
                    </table>
                  </td>
                </tr>
		<?php endif; ?>
                <?php $i++; endforeach; ?>
                <tr>
                  <td class="list" colspan="3"></td>
                  <td class="list">
                    <table border="0" cellspacing="0" cellpadding="1">
                      <tr>
			<td width="17"></td>
                        <td valign="middle"><a href="firewall_virtual_ip_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
                      </tr>
                    </table>
                  </td>
                </tr>
		<tr>
		  <td colspan="4">
		      <p><span class="vexpl"><span class="red"><strong>Note:<br>
                      </strong></span>The virtual IP addresses defined on this page may be used in <a href="firewall_nat.php">NAT</a> mappings.<br>
                      You can check the status of your CARP Virtual IPs and interfaces <a href="carp_status.php">here</a>.</span></p>
		  </td>
		</tr>
              </table>
	   </div>
	</table>
            </form>
<?php include("fend.inc"); ?>
</body>
</html>

<?php 

if ($_POST) {
	$pconfig = $_POST;

	if ($_POST['apply']) {
		if (file_exists("/tmp/carp_reboot_needed")) {
			flush();
			ob_flush();
			usleep(1000);
			mwexec_bg("sleep 3; /sbin/shutdown -r now");
			exit;
		} else {
			$retval = 0;
	
			config_lock();
			$retval = services_proxyarp_configure();
			/* Bring up any configured CARP interfaces */
			reset_carp();
			$retval |= filter_configure();
			config_unlock();
			
			unlink_if_exists($d_vipconfdirty_path);
		}
	}
}

?>