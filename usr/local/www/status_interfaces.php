#!/usr/local/bin/php
<?php 
/*
	status_interfaces.php
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

function get_interface_info($ifdescr) {
	
	global $config, $g;
	
	$ifinfo = array();
	
	/* find out interface name */
	if ($ifdescr == "wan")
		$ifinfo['if'] = get_real_wan_interface();
	else
		$ifinfo['if'] = $config['interfaces'][$ifdescr]['if'];
	
	/* run netstat to determine link info */
	unset($linkinfo);
	exec("/usr/bin/netstat -I " . $ifinfo['if'] . " -nWb -f link", $linkinfo);
	$linkinfo = preg_split("/\s+/", $linkinfo[1]);
	if (preg_match("/\*$/", $linkinfo[0])) {
		$ifinfo['status'] = "down";
	} else {
		$ifinfo['status'] = "up";
	}
	
	if (($ifinfo['if'] != $g['pppoe_interface']) && (!strstr($ifinfo['if'],'tun'))) {
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
	
	if ($ifinfo['status'] == "up") {
		/* run netstat to determine inet info */
		unset($inetinfo);
		exec("/usr/bin/netstat -I " . $ifinfo['if'] . " -nWb -f inet", $inetinfo);
		$inetinfo = preg_split("/\s+/", $inetinfo[1]);
		
		$ifinfo['ipaddr'] = $inetinfo[3];
		
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
		
		/* try to determine netmask and media with ifconfig */
		unset($ifconfiginfo);
		exec("/sbin/ifconfig " . $ifinfo['if'], $ifconfiginfo);
		
		foreach ($ifconfiginfo as $ici) {
			if (preg_match("/netmask (\S+)/", $ici, $matches) && !$ifinfo['subnet']) {
				if (preg_match("/^0x/", $matches[1])) {
					$ifinfo['subnet'] = long2ip(hexdec($matches[1]));
				}
			}
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
			if (preg_match("/ssid (\S*)/", $ici, $matches)) {
				$ifinfo['ssid'] = $matches[1];
			}
		}
		
		/* PPPoE only: get media from underlying ethernet interface */
		if (($ifdescr == "wan") && ($config['interfaces']['wan']['ipaddr'] == "pppoe")) {
			unset($ifconfiginfo);
			exec("/sbin/ifconfig " . $config['interfaces']['wan']['if'], $ifconfiginfo);
			
			foreach ($ifconfiginfo as $ici) {
				if (preg_match("/media: .*? \((.*?)\)/", $ici, $matches)) {
					$ifinfo['media'] = $matches[1];
				} else if (preg_match("/ether (.*)/", $ici, $matches)) {
					$ifinfo['macaddr'] = $matches[1];
				}
			}

			/* get pppoe link status for dial on demand */
			unset($ifconfiginfo);
			exec("/sbin/ifconfig " . $ifinfo['if'], $ifconfiginfo);

			$ifinfo['pppoelink'] = "up";

			foreach ($ifconfiginfo as $ici) {
				if (strpos($ici, 'LINK0') !== false)
					$ifinfo['pppoelink'] = "down";
			}
		}

		/* get ppptp link status for dial on demand */
		if (($ifdescr == "wan") && ($config['interfaces']['wan']['ipaddr'] == "pptp")) {
			
			unset($ifconfiginfo);
			exec("/sbin/ifconfig " . $ifinfo['if'], $ifconfiginfo);

			$ifinfo['pptplink'] = "up";

			foreach ($ifconfiginfo as $ici) {
				if (strpos($ici, 'LINK0') !== false)
					$ifinfo['pptplink'] = "down";
			}
		}
	}
	
	return $ifinfo;
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Status: Interfaces");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
      <p class="pgtitle">Status: Interfaces</p>
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
                <td width="22%" class="listhdrr">Status</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['status']);?>
                </td>
              </tr><?php if ($ifinfo['pppoelink']): ?>
              <tr> 
                <td width="22%" class="listhdrr">PPPoE</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['pppoelink']);?>
                </td>
              </tr><?php  endif; if ($ifinfo['pptplink']): ?>
              <tr> 
                <td width="22%" class="listhdrr">PPTP</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['pptplink']);?>
                </td>
              </tr><?php  endif; if ($ifinfo['macaddr']): ?>
              <tr> 
                <td width="22%" class="listhdrr">MAC address</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['macaddr']);?>
                </td>
              </tr><?php endif; if ($ifinfo['status'] != "down"): ?>
			  <?php if ($ifinfo['ipaddr']): ?>
              <tr> 
                <td width="22%" class="listhdrr">IP address</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['ipaddr']);?>
                  &nbsp; </td>
              </tr><?php endif; ?><?php if ($ifinfo['subnet']): ?>
              <tr> 
                <td width="22%" class="listhdrr">Subnet mask</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['subnet']);?>
                </td>
              </tr><?php endif; ?><?php if ($ifinfo['gateway']): ?>
              <tr> 
                <td width="22%" class="listhdrr">Gateway</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['gateway']);?>
                </td>
              </tr><?php endif; ?><?php if ($ifinfo['media']): ?>
              <tr> 
                <td width="22%" class="listhdrr">Media</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['media']);?>
                </td>
              </tr><?php endif; ?><?php if ($ifinfo['channel']): ?>
              <tr> 
                <td width="22%" class="listhdrr">Channel</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['channel']);?>
                </td>
              </tr><?php endif; ?><?php if ($ifinfo['ssid']): ?>
              <tr> 
                <td width="22%" class="listhdrr">SSID</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['ssid']);?>
                </td>
              </tr><?php endif; ?>
              <tr> 
                <td width="22%" class="listhdrr">In/out packets</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['inpkts'] . "/" . $ifinfo['outpkts'] . " (" . 
				  		format_bytes($ifinfo['inbytes']) . "/" . format_bytes($ifinfo['outbytes']) . ")");?>
                </td>
              </tr><?php if (isset($ifinfo['inerrs'])): ?>
              <tr> 
                <td width="22%" class="listhdrr">In/out errors</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['inerrs'] . "/" . $ifinfo['outerrs']);?>
                </td>
              </tr><?php endif; ?><?php if (isset($ifinfo['collisions'])): ?>
              <tr> 
                <td width="22%" class="listhdrr">Collisions</td>
                <td width="78%" class="listr"> 
                  <?=htmlspecialchars($ifinfo['collisions']);?>
                </td>
              </tr><?php endif; ?>
	      <?php endif; ?>
              <?php $i++; endforeach; ?>
            </table>
<?php include("fend.inc"); ?>
</body>
</html>
