#!/usr/local/bin/php
<?php
/*
	services_proxyarp.php
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

if (!is_array($config['proxyarp']['proxyarpnet'])) {
	$config['proxyarp']['proxyarpnet'] = array();
}
proxyarp_sort();
$a_proxyarp = &$config['proxyarp']['proxyarpnet'];

if ($_POST) {
	$pconfig = $_POST;
	
	$retval = 0;
	if (!file_exists($d_sysrebootreqd_path)) {
		config_lock();
		$retval = services_proxyarp_configure();
		config_unlock();
	}
	$savemsg = get_std_save_message($retval);

	if ($retval == 0) {
		if (file_exists($d_proxyarpdirty_path))
			unlink($d_proxyarpdirty_path);
	}
}

if ($_GET['act'] == "del") {
	if ($a_proxyarp[$_GET['id']]) {
		unset($a_proxyarp[$_GET['id']]);
		write_config();
		touch($d_proxyarpdirty_path);
		header("Location: services_proxyarp.php");
		exit;
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Services: Proxy ARP");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Services: Proxy ARP</p>
<form action="services_proxyarp.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_proxyarpdirty_path)): ?><p>
<?php print_info_box_np("The proxy ARP configuration has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<input name="apply" type="submit" class="formbtn" id="apply" value="Apply changes"></p>
<?php endif; ?>
              <table width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                  <td width="20%" class="listhdrr">Interface</td>
                  <td width="30%" class="listhdrr">Network</td>
                  <td width="40%" class="listhdr">Description</td>
                  <td width="10%" class="list"></td>
				</tr>
			  <?php $i = 0; foreach ($a_proxyarp as $arpent): ?>
                <tr>
				  <td class="listlr">
                  <?php
				  	if ($arpent['interface']) {
					  $iflabels = array('lan' => 'LAN', 'wan' => 'WAN');
					  for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++)
						$iflabels['opt' . $j] = $config['interfaces']['opt' . $j]['descr'];
					  echo htmlspecialchars($iflabels[$arpent['interface']]);
					} else {
						echo "WAN";
					}
	    		  ?>
                  </td>
                  <td class="listr">
				  <?php if (isset($arpent['network'])) {
				  			list($sa,$sn) = explode("/", $arpent['network']);
							if ($sn == 32)
								echo $sa;
							else
					  			echo $arpent['network'];
						} else if (isset($arpent['range']))
							echo $arpent['range']['from'] . "-" . $arpent['range']['to'];
                    ?>&nbsp;
                  </td>
                  <td class="listbg">
                    <?=htmlspecialchars($arpent['descr']);?>&nbsp;
                  </td>
                  <td valign="middle" nowrap class="list"> <a href="services_proxyarp_edit.php?id=<?=$i;?>"><img src="e.gif" width="17" height="17" border="0"></a>
                     &nbsp;<a href="services_proxyarp.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this network?')"><img src="x.gif" width="17" height="17" border="0"></a></td>
				</tr>
			  <?php $i++; endforeach; ?>
                <tr> 
                  <td class="list" colspan="3"></td>
                  <td class="list"> <a href="services_proxyarp_edit.php"><img src="plus.gif" width="17" height="17" border="0"></a></td>
				</tr>
              </table>
            </form>
            <p class="vexpl"><span class="red"><strong>Note:<br>
                      </strong></span>Proxy ARP can be used if you need m0n0wall to send ARP
					  replies on an interface for other IP addresses than its own (e.g. for 1:1, advanced outbound or server NAT). It is not
					  necessary on the WAN interface if you have a subnet routed to you or if you use PPPoE/PPTP, and it only works on the WAN interface if it's configured with a static IP address or DHCP.</p>
            <?php include("fend.inc"); ?>
</body>
</html>
