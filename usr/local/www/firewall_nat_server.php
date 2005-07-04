#!/usr/local/bin/php
<?php
/* $Id$ */
/*
	firewall_nat_server.php
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

require("guiconfig.inc");

if (!is_array($config['nat']['servernat'])) {
	$config['nat']['servernat'] = array();
}
$a_snat = &$config['nat']['servernat'];
nat_server_rules_sort();

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;
		if (!file_exists($d_sysrebootreqd_path)) {
			config_lock();
			$retval |= filter_configure();
			config_unlock();
		}
		$savemsg = get_std_save_message($retval);

		if ($retval == 0) {
			if (file_exists($d_natconfdirty_path))
				unlink($d_natconfdirty_path);
			if (file_exists($d_filterconfdirty_path))
				unlink($d_filterconfdirty_path);
		}
	}
}

if ($_GET['act'] == "del") {
	if ($a_snat[$_GET['id']]) {
		/* make sure no inbound NAT mappings reference this entry */
		if (is_array($config['nat']['rule'])) {
			foreach ($config['nat']['rule'] as $rule) {
				if ($rule['external-address'] == $a_snat[$_GET['id']]['ipaddr']) {
					$input_errors[] = "This entry cannot be deleted because it is still referenced by at least one inbound NAT mapping.";
					break;
				}
			}
		}

		if (!$input_errors) {
			unset($a_snat[$_GET['id']]);
			write_config();
			touch($d_natconfdirty_path);
			header("Location: firewall_nat_server.php");
			exit;
		}
	}
}

$pgtitle = "Firewall: NAT: NAT Addresses";
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<form action="firewall_nat_server.php" method="post">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_natconfdirty_path)): ?><p>
<?php print_info_box_np("The NAT configuration has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">  <tr><td>
<?php
	$tab_array = array();
	$tab_array[0] = array("Port Forward", false, "firewall_nat.php");
	$tab_array[1] = array("NAT Addresses", true, "firewall_nat_server.php");
	$tab_array[2] = array("1:1", false, "firewall_nat_1to1.php");
	$tab_array[3] = array("Outbound", false, "firewall_nat_out.php");
	$tab_array[4] = array("Outbound Load Balancing", false, "firewall_nat_out_load_balancing.php");
	display_top_tabs($tab_array);
?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
              <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                  <td width="40%" class="listhdrr">External IP address</td>
                  <td width="50%" class="listhdr">Description</td>
                  <td width="10%" class="list"></td>
				</tr>
			  <?php $i = 0; foreach ($a_snat as $natent): ?>
                <tr>
                  <td class="listlr" ondblclick="document.location='firewall_nat_server_edit.php?id=<?=$i;?>';">
                    <?=$natent['ipaddr'];?>
                  </td>
                  <td class="listbg" ondblclick="document.location='firewall_nat_server_edit.php?id=<?=$i;?>';">
                    <font color="#FFFFFF"><?=htmlspecialchars($natent['descr']);?>&nbsp;
                  </td>
                  <td class="list" nowrap>
                    <table border="0" cellspacing="0" cellpadding="1">
                      <tr>
                        <td valign="middle"><a href="firewall_nat_server_edit.php?id=<?=$i;?>"><img src="e.gif" width="17" height="17" border="0"></a></td>
                        <td valign="middle"><a href="firewall_nat_server.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this entry?')"><img src="x.gif" width="17" height="17" border="0"></a></td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <?php $i++; endforeach; ?>
                <tr>
                  <td class="list" colspan="2"></td>
                  <td class="list">
                    <table border="0" cellspacing="0" cellpadding="1">
                      <tr>
                        <td valign="middle"><a href="firewall_nat_server_edit.php"><img src="plus.gif" width="17" height="17" border="0"></a></td>
                      </tr>
                    </table>
                  </td>
                </tr>
		<tr>
		  <td colspan="2">
		      <p><span class="vexpl"><span class="red"><strong>Note:<br>
                      </strong></span>The external IP addresses defined on this page may be used in <a href="firewall_nat.php">inbound NAT</a> mappings. Depending on the way your WAN connection is setup, you may also need <a href="services_proxyarp.php">proxy ARP</a>.</span></p>
		  </td>
		</tr>
              </table>
	   </div>
	</table>
            </form>
<?php include("fend.inc"); ?>
</body>
</html>
