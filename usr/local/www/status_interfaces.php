#!/usr/local/bin/php
<?php 
/*
	status_interfaces.php
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

$pgtitle = array("Status", "Interfaces");
require("guiconfig.inc");

$wancfg = &$config['interfaces']['wan'];

if ($_POST) {
	if ($_POST['submit'] == "Disconnect" || $_POST['submit'] == "Release") {
		if ($wancfg['ipaddr'] == "dhcp")
			interfaces_wan_dhcp_down();
		else if ($wancfg['ipaddr'] == "pppoe")
			interfaces_wan_pppoe_down();
		else if ($wancfg['ipaddr'] == "pptp")
			interfaces_wan_pptp_down();
	} else if ($_POST['submit'] == "Connect" || $_POST['submit'] == "Renew") {
		if ($wancfg['ipaddr'] == "dhcp")
			interfaces_wan_dhcp_up();
		else if ($wancfg['ipaddr'] == "pppoe")
			interfaces_wan_pppoe_up();
		else if ($wancfg['ipaddr'] == "pptp")
			interfaces_wan_pptp_up();
	} else {
		header("Location: index.php");
		exit;
	}
}

function get_interface_info($ifdescr) {
	
	global $config, $g;
	
	$ifinfo = array();
	
	/* find out interface name */
	$ifinfo['hwif'] = $config['interfaces'][$ifdescr]['if'];
	if ($ifdescr == "wan")
		$ifinfo['if'] = get_real_wan_interface();
	else
		$ifinfo['if'] = $ifinfo['hwif'];
	
	/* run netstat to determine link info */
	unset($linkinfo);
	exec("/usr/bin/netstat -I " . $ifinfo['hwif'] . " -nWb -f link", $linkinfo);
	$linkinfo = preg_split("/\s+/", $linkinfo[1]);
	if (preg_match("/\*$/", $linkinfo[0])) {
		$ifinfo['status'] = "down";
	} else {
		$ifinfo['status'] = "up";
	}
	
	if (!strstr($ifinfo['if'],'tun')) {
		$ifinfo['macaddr'] = $linkinfo[3];
		$ifinfo['inpkts'] = $linkinfo[4];
		$ifinfo['inerrs'] = $linkinfo[5];
		$ifinfo['inbytes'] = $linkinfo[6];
		$ifinfo['outpkts'] = $linkinfo[7];
		$ifinfo['outerrs'] = $linkinfo[8];
		$ifinfo['outbytes'] = $linkinfo[9];
		$ifinfo['collisions'] = $linkinfo[10];
	} else {
		$ifinfo['inpkts'] = $linkinfo[3];
		$ifinfo['inbytes'] = $linkinfo[5];
		$ifinfo['outpkts'] = $linkinfo[6];
		$ifinfo['outbytes'] = $linkinfo[8];
	}
	
	/* DHCP? -> see if dhclient is up */
	if (($ifdescr == "wan") && ($config['interfaces']['wan']['ipaddr'] == "dhcp")) {
		/* see if dhclient is up */
		if ($ifinfo['status'] == "up" && file_exists("{$g['varrun_path']}/dhclient.pid"))
			$ifinfo['dhcplink'] = "up";
		else
			$ifinfo['dhcplink'] = "down";
	}
	
	/* PPPoE interface? -> get status from virtual interface */
	if (($ifdescr == "wan") && ($config['interfaces']['wan']['ipaddr'] == "pppoe")) {
		unset($linkinfo);
		exec("/usr/bin/netstat -I " . $ifinfo['if'] . " -nWb -f link", $linkinfo);
		$linkinfo = preg_split("/\s+/", $linkinfo[1]);
		if (preg_match("/\*$/", $linkinfo[0])) {
			$ifinfo['pppoelink'] = "down";
		} else {
			/* get PPPoE link status for dial on demand */
			unset($ifconfiginfo);
			exec("/sbin/ifconfig " . $ifinfo['if'], $ifconfiginfo);
	
			$ifinfo['pppoelink'] = "up";
	
			foreach ($ifconfiginfo as $ici) {
				if (strpos($ici, 'LINK0') !== false)
					$ifinfo['pppoelink'] = "down";
			}
		}
	}
	
	/* PPTP interface? -> get status from virtual interface */
	if (($ifdescr == "wan") && ($config['interfaces']['wan']['ipaddr'] == "pptp")) {
		unset($linkinfo);
		exec("/usr/bin/netstat -I " . $ifinfo['if'] . " -nWb -f link", $linkinfo);
		$linkinfo = preg_split("/\s+/", $linkinfo[1]);
		if (preg_match("/\*$/", $linkinfo[0])) {
			$ifinfo['pptplink'] = "down";
		} else {
			/* get PPTP link status for dial on demand */
			unset($ifconfiginfo);
			exec("/sbin/ifconfig " . $ifinfo['if'], $ifconfiginfo);
	
			$ifinfo['pptplink'] = "up";
	
			foreach ($ifconfiginfo as $ici) {
				if (strpos($ici, 'LINK0') !== false)
					$ifinfo['pptplink'] = "down";
			}
		}
	}
	
	if ($ifinfo['status'] == "up") {
		/* try to determine media with ifconfig */
		unset($ifconfiginfo);
		exec("/sbin/ifconfig " . $ifinfo['hwif'], $ifconfiginfo);
		
		foreach ($ifconfiginfo as $ici) {
			if (!isset($config['interfaces'][$ifdescr]['wireless'])) {
				/* don't list media/speed for wireless cards, as it always
				   displays 2 Mbps even though clients can connect at 11 Mbps */
				if (preg_match("/media: .*? \((.*?)\)/", $ici, $matches)) {
					$ifinfo['media'] = $matches[1];
				} else if (preg_match("/media: Ethernet (.*)/", $ici, $matches)) {
					$ifinfo['media'] = $matches[1];
				}
			}
			if (preg_match("/status: (.*)$/", $ici, $matches)) {
				if ($matches[1] != "active")
					$ifinfo['status'] = $matches[1];
			}
			if (preg_match("/channel (\S*)/", $ici, $matches)) {
				$ifinfo['channel'] = $matches[1];
			}
			if (preg_match("/ssid (\".*?\"|\S*)/", $ici, $matches)) {
				if ($matches[1][0] == '"')
					$ifinfo['ssid'] = substr($matches[1], 1, -1);
				else
					$ifinfo['ssid'] = $matches[1];
			}
		}
		
		if ($ifinfo['pppoelink'] != "down" && $ifinfo['pptplink'] != "down") {
			/* try to determine IP address and netmask with ifconfig */
			unset($ifconfiginfo);
			exec("/sbin/ifconfig " . $ifinfo['if'], $ifconfiginfo);
			
			foreach ($ifconfiginfo as $ici) {
				if (preg_match("/inet (\S+)/", $ici, $matches)) {
					$ifinfo['ipaddr'] = $matches[1];
				}
				if (preg_match("/netmask (\S+)/", $ici, $matches)) {
					if (preg_match("/^0x/", $matches[1]))
						$ifinfo['subnet'] = long2ip(hexdec($matches[1]));
				}
			}
			
			if ($ifdescr == "wan") {
				/* run netstat to determine the default gateway */
				unset($netstatrninfo);
				exec("/usr/bin/netstat -rnf inet", $netstatrninfo);
				
				foreach ($netstatrninfo as $nsr) {
					if (preg_match("/^default\s*(\S+)/", $nsr, $matches)) {
						$ifinfo['gateway'] = $matches[1];
					}
				}
			}
		}
	}
	
	return $ifinfo;
}

?>
<?php include("fbegin.inc"); ?>
<form action="" method="post">
            <table width="100%" border="0" cellspacing="0" cellpadding="0">
              <?php $i = 0; $ifdescrs = array('wan' => 'WAN', 'lan' => 'LAN');
						
					for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
						$ifdescrs['opt' . $j] = $config['interfaces']['opt' . $j]['descr'];
					}
					
			      foreach ($ifdescrs as $ifdescr => $ifname): 
				  $ifinfo = get_interface_info($ifdescr);
				  ?>
              <?php if ($i): ?>
              <tr>
				  <td colspan="8" class="list" height="12"></td>
				</tr>
				<?php endif; ?>
              <tr> 
                <td colspan="2" class="listtopic"> 
                  <?=htmlspecialchars($ifname);?>
                  interface</td>
              </tr>
              <tr> 
                <td width="22%" class="vncellt">Status</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['status']);?>
                </td>
              </tr><?php if ($ifinfo['dhcplink']): ?>
			  <tr> 
				<td width="22%" class="vncellt">DHCP</td>
				<td width="78%" class="listr"> 
				  <?=htmlspecialchars($ifinfo['dhcplink']);?>&nbsp;&nbsp;
				  <?php if ($ifinfo['dhcplink'] == "up"): ?>
				  <input type="submit" name="submit" value="Release" class="formbtns">
				  <?php else: ?>
				  <input type="submit" name="submit" value="Renew" class="formbtns">
				  <?php endif; ?>
				</td>
			  </tr><?php endif; if ($ifinfo['pppoelink']): ?>
              <tr> 
                <td width="22%" class="vncellt">PPPoE</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['pppoelink']);?>&nbsp;&nbsp;
				  <?php if ($ifinfo['pppoelink'] == "up"): ?>
				  <input type="submit" name="submit" value="Disconnect" class="formbtns">
				  <?php else: ?>
				  <input type="submit" name="submit" value="Connect" class="formbtns">
				  <?php endif; ?>
                </td>
              </tr><?php  endif; if ($ifinfo['pptplink']): ?>
              <tr> 
                <td width="22%" class="vncellt">PPTP</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['pptplink']);?>&nbsp;&nbsp;
				  <?php if ($ifinfo['pptplink'] == "up"): ?>
				  <input type="submit" name="submit" value="Disconnect" class="formbtns">
				  <?php else: ?>
				  <input type="submit" name="submit" value="Connect" class="formbtns">
				  <?php endif; ?>
                </td>
              </tr><?php  endif; if ($ifinfo['macaddr']): ?>
              <tr> 
                <td width="22%" class="vncellt">MAC address</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['macaddr']);?>
                </td>
              </tr><?php endif; if ($ifinfo['status'] != "down"): ?>
			  <?php if ($ifinfo['dhcplink'] != "down" && $ifinfo['pppoelink'] != "down" && $ifinfo['pptplink'] != "down"): ?>
			  <?php if ($ifinfo['ipaddr']): ?>
              <tr> 
                <td width="22%" class="vncellt">IP address</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['ipaddr']);?>
                  &nbsp; </td>
              </tr><?php endif; ?><?php if ($ifinfo['subnet']): ?>
              <tr> 
                <td width="22%" class="vncellt">Subnet mask</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['subnet']);?>
                </td>
              </tr><?php endif; ?><?php if ($ifinfo['gateway']): ?>
              <tr> 
                <td width="22%" class="vncellt">Gateway</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['gateway']);?>
                </td>
              </tr><?php endif; if ($ifdescr == "wan" && file_exists("{$g['varetc_path']}/nameservers.conf")): ?>
                <td width="22%" class="vncellt">ISP DNS servers</td>
                <td width="78%" class="listr"><?php echo nl2br(file_get_contents("{$g['varetc_path']}/nameservers.conf")); ?></td>
			  <?php endif; endif; if ($ifinfo['media']): ?>
              <tr> 
                <td width="22%" class="vncellt">Media</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['media']);?>
                </td>
              </tr><?php endif; ?><?php if ($ifinfo['channel']): ?>
              <tr> 
                <td width="22%" class="vncellt">Channel</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['channel']);?>
                </td>
              </tr><?php endif; ?><?php if ($ifinfo['ssid']): ?>
              <tr> 
                <td width="22%" class="vncellt">SSID</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['ssid']);?>
                </td>
              </tr><?php endif; ?>
              <tr> 
                <td width="22%" class="vncellt">In/out packets</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['inpkts'] . "/" . $ifinfo['outpkts'] . " (" . 
				  		format_bytes($ifinfo['inbytes']) . "/" . format_bytes($ifinfo['outbytes']) . ")");?>
                </td>
              </tr><?php if (isset($ifinfo['inerrs'])): ?>
              <tr> 
                <td width="22%" class="vncellt">In/out errors</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['inerrs'] . "/" . $ifinfo['outerrs']);?>
                </td>
              </tr><?php endif; ?><?php if (isset($ifinfo['collisions'])): ?>
              <tr> 
                <td width="22%" class="vncellt">Collisions</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['collisions']);?>
                </td>
              </tr><?php endif; ?>
	      <?php endif; ?>
              <?php $i++; endforeach; ?>
            </table>
</form>
<br>
<strong class="red">Note:<br>
</strong>Using dial-on-demand will bring the connection up again if any packet
triggers it. To substantiate this point: disconnecting manually 
will <strong>not</strong> prevent dial-on-demand from making connections
to the outside! Don't use dial-on-demand if you want to make sure that the line is kept disconnected.
<?php include("fend.inc"); ?>
