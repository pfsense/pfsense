#!/usr/local/bin/php
<?php
/*
	vpn_ipsec.php
	part of m0n0wall (http://m0n0.ch/wall)
	
	Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
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

$pgtitle = array("VPN", "IPsec");
require("guiconfig.inc");

if (!is_array($config['ipsec']['tunnel'])) {
	$config['ipsec']['tunnel'] = array();
}
$a_ipsec = &$config['ipsec']['tunnel'];
$wancfg = &$config['interfaces']['wan'];

$pconfig['enable'] = isset($config['ipsec']['enable']);

if ($_POST) {

	if ($_POST['apply']) {
		$retval = 0;
		if (!file_exists($d_sysrebootreqd_path))
			$retval = vpn_ipsec_configure();
		$savemsg = get_std_save_message($retval);
		if ($retval == 0) {
			if (file_exists($d_ipsecconfdirty_path))
				unlink($d_ipsecconfdirty_path);
		}
	} else if ($_POST['submit']) {
		$pconfig = $_POST;
		
		$config['ipsec']['enable'] = $_POST['enable'] ? true : false;
		
		write_config();
	
		$retval = 0;
		if (!file_exists($d_sysrebootreqd_path)) {
			config_lock();
			$retval = vpn_ipsec_configure();
			config_unlock();
		}
		$savemsg = get_std_save_message($retval);
		if ($retval == 0) {
			if (file_exists($d_ipsecconfdirty_path))
				unlink($d_ipsecconfdirty_path);
		}
	}
}

if ($_GET['act'] == "del") {
	if ($a_ipsec[$_GET['id']]) {
		unset($a_ipsec[$_GET['id']]);
		write_config();
		touch($d_ipsecconfdirty_path);
		header("Location: vpn_ipsec.php");
		exit;
	}
}
?>
<?php include("fbegin.inc"); ?>
<form action="vpn_ipsec.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_ipsecconfdirty_path)): ?><p>
<?php print_info_box_np("The IPsec tunnel configuration has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<input name="apply" type="submit" class="formbtn" id="apply" value="Apply changes"></p>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
  <ul id="tabnav">
    <li class="tabact">Tunnels</li>
    <li class="tabinact"><a href="vpn_ipsec_mobile.php">Mobile clients</a></li>
    <li class="tabinact"><a href="vpn_ipsec_keys.php">Pre-shared keys</a></li>
    <li class="tabinact"><a href="vpn_ipsec_ca.php">CAs</a></li>
  </ul>
  </td></tr>
  <tr> 
    <td class="tabcont">
		        <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr> 
                  <td class="vtable">
                      <input name="enable" type="checkbox" id="enable" value="yes" <?php if ($pconfig['enable']) echo "checked";?>>
                      <strong>Enable IPsec</strong></td>
                </tr>
                <tr> 
                  <td> <input name="submit" type="submit" class="formbtn" value="Save"> 
                  </td>
                </tr>
              </table>
              &nbsp;<br>
              <table width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                  <td nowrap class="listhdrr">Local net<br>
                    Remote net</td>
                  <td class="listhdrr">Interface<br>Remote gw</td>
                  <td class="listhdrr">P1 mode</td>
                  <td class="listhdrr">P1 Enc. Algo</td>
                  <td class="listhdrr">P1 Hash Algo</td>
                  <td class="listhdr">Description</td>
                  <td class="list"></td>
				</tr>
                <?php $i = 0; foreach ($a_ipsec as $ipsecent):
					if (isset($ipsecent['disabled'])) {
						$spans = "<span class=\"gray\">";
						$spane = "</span>";
					} else {
						$spans = $spane = "";
					}
				?>
                <tr valign="top">
                  <td nowrap class="listlr"><?=$spans;?> 
                    <?php	if ($ipsecent['local-subnet']['network'])
								echo strtoupper($ipsecent['local-subnet']['network']);
							else
								echo $ipsecent['local-subnet']['address'];
					?>
                    <br>
                    <?=$ipsecent['remote-subnet'];?>
                  <?=$spane;?></td>
                  <td class="listr"><?=$spans;?>
				  <?php if ($ipsecent['interface']) {
							$iflabels = array('lan' => 'LAN', 'wan' => 'WAN');
							  for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++)
								$iflabels['opt' . $j] = $config['interfaces']['opt' . $j]['descr'];
							  $if = htmlspecialchars($iflabels[$ipsecent['interface']]);
						} else
							$if = "WAN";
						
						echo $if . "<br>" . $ipsecent['remote-gateway'];
					?>
                  <?=$spane;?></td>
                  <td class="listr"><?=$spans;?>
				    <?=$ipsecent['p1']['mode'];?>
                  <?=$spane;?></td>
                  <td class="listr"><?=$spans;?>
				    <?=$p1_ealgos[$ipsecent['p1']['encryption-algorithm']];?>
                  <?=$spane;?></td>
                  <td class="listr"><?=$spans;?>
				    <?=$p1_halgos[$ipsecent['p1']['hash-algorithm']];?>
                  <?=$spane;?></td>
                  <td class="listbg"><?=$spans;?>
                    <?=htmlspecialchars($ipsecent['descr']);?>&nbsp;
                  <?=$spane;?></td>
                  <td valign="middle" nowrap class="list"> <a href="vpn_ipsec_edit.php?id=<?=$i;?>"><img src="e.gif" title="edit tunnel" width="17" height="17" border="0"></a> 
                    &nbsp;<a href="vpn_ipsec.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this tunnel?')"><img src="x.gif" title="delete tunnel" width="17" height="17" border="0"></a></td>
				</tr>
			  <?php $i++; endforeach; ?>
                <tr> 
                  <td class="list" colspan="6"></td>
                  <td class="list"> <a href="vpn_ipsec_edit.php"><img src="plus.gif" title="add tunnel" width="17" height="17" border="0"></a></td>
				</tr>
              </table>
			</td>
	</tr>
</table>
</form>
<?php include("fend.inc"); ?>
