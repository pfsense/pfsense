#!/usr/local/bin/php
<?php 
/*
	status_wireless.php
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

$pgtitle = array("Status", "Wireless");
require("guiconfig.inc");

function get_wireless_info($ifdescr) {
	
	global $config, $g;
	
	$ifinfo = array();
	$ifinfo['if'] = $config['interfaces'][$ifdescr]['if'];
	
	/* get signal strength cache */
	exec("/usr/sbin/wicontrol -i " . $ifinfo['if'] . " -C", $sscache);
	
	$ifinfo['sscache'] = array();
	foreach ($sscache as $ss) {
		if ($ss) {
			$ssa = preg_split("/\s+/", $ss);
			$sscent = array();
			$sscent['mac'] = chop($ssa[1], ",");
			$sscent['ipaddr'] = chop($ssa[2], ",");
			$sscent['sig'] = chop($ssa[4], ",");
			$sscent['noise'] = chop($ssa[6], ",");
			$sscent['qual'] = chop($ssa[8], ",");
			$ifinfo['sscache'][] = $sscent;
		}
	}
	
	/* if in hostap mode: get associated stations */
	if ($config['interfaces'][$ifdescr]['wireless']['mode'] == "hostap") {
		exec("/usr/sbin/wicontrol -i " . $ifinfo['if'] . " -l", $aslist);
		
		$ifinfo['aslist'] = array();
		array_shift($aslist);
		foreach ($aslist as $as) {
			if ($as) {
				if (preg_match("/^ap/", $as)) {
					if (is_array($aslent) && count($aslent))
						$ifinfo['aslist'][] = $aslent;
					$aslent = array();
				} else if (preg_match("/BSSID:\s*\[ (.*) \]/", $as, $matches)) {
					$aslent['bssid'] = $matches[1];
				} else if (preg_match("/\[dBm\]:\s*\[ .* \/ (.*) \/ (.*) \]/", $as, $matches)) {
					$aslent['sig'] = $matches[1];
					$aslent['noise'] = $matches[2];
				}
			}
		}
		if (is_array($aslent) && count($aslent))
			$ifinfo['aslist'][] = $aslent;
	}
	
	return $ifinfo;
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("pfSense webGUI");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<form>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php $i = 0; $ifdescrs = array();

	if (is_array($config['interfaces']['wan']['wireless']) &&
			(strstr($config['interfaces']['wan']['if'], "wi") || strstr($config['interfaces']['wan']['if'], "ath")))
			$ifdescrs['wan'] = 'WAN';
			
	if (is_array($config['interfaces']['lan']['wireless']) &&
			(strstr($config['interfaces']['lan']['if'], "wi") || strstr($config['interfaces']['lan']['if'], "ath")))
			$ifdescrs['lan'] = 'LAN';
	
	for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
		if (is_array($config['interfaces']['opt' . $j]['wireless']) &&
			isset($config['interfaces']['opt' . $j]['enable']) &&
			(strstr($config['interfaces']['opt' . $j]['if'], "wi") || strstr($config['interfaces']['opt' . $j]['if'], "ath")))
			$ifdescrs['opt' . $j] = $config['interfaces']['opt' . $j]['descr'];
	}
		
	if (count($ifdescrs) > 0): ?>
            <table width="100%" border="0" cellspacing="0" cellpadding="0">
              <?php
			      foreach ($ifdescrs as $ifdescr => $ifname): 
				  $ifinfo = get_wireless_info($ifdescr);
			  ?>
              <?php if ($i): ?>
              <tr> 
                <td colspan="8" class="list" height="12"></td>
              </tr>
              <?php endif; ?>
              <tr> 
                <td colspan="2" class="listtopic"> 
                  <?=htmlspecialchars($ifname);?> interface (SSID &quot;<?=htmlspecialchars($config['interfaces'][$ifdescr]['wireless']['ssid']);?>&quot;)</td>
              </tr>
              <tr> 
                <td width="22%" valign="top" class="vncellt">Signal strength 
                  cache</td>
                <td width="78%" class="listrpad"> 
                  <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr> 
                      <td width="30%" class="listhdrr">MAC address</td>
                      <td width="25%" class="listhdrr">IP address</td>
                      <td width="15%" class="listhdrr">Signal</td>
                      <td width="15%" class="listhdrr">Noise</td>
                      <td width="15%" class="listhdr">Quality</td>
                    </tr>
                    <?php foreach ($ifinfo['sscache'] as $ss): ?>
                    <tr> 
                      <td class="listlr"> 
                        <?=htmlspecialchars($ss['mac']);?>
                      </td>
                      <td class="listr"> 
                        <?=htmlspecialchars($ss['ipaddr']);?>
                      </td>
                      <td class="listr"> 
                        <?=htmlspecialchars($ss['sig']);?>
                      </td>
                      <td class="listr"> 
                        <?=htmlspecialchars($ss['noise']);?>
                      </td>
                      <td class="listr"> 
                        <?=htmlspecialchars($ss['qual']);?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </table></td>
              </tr><?php if ($ifinfo['aslist']): ?>
              <tr> 
                <td width="22%" valign="top" class="vncellt">Associated stations 
                </td>
                <td width="78%" class="listrpad"> 
                  <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr> 
                      <td width="40%" class="listhdrr">BSSID</td>
                      <td width="30%" class="listhdrr">Signal</td>
                      <td width="30%" class="listhdrr">Noise</td>
                    </tr>
                    <?php foreach ($ifinfo['aslist'] as $as): ?>
                    <tr> 
                      <td class="listlr"> 
                        <?=htmlspecialchars($as['bssid']);?>
                      </td>
                      <td class="listr"> 
                        <?=htmlspecialchars($as['sig']);?> dBm
                      </td>
                      <td class="listr"> 
                        <?=htmlspecialchars($as['noise']);?> dBm
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </table></td>
              </tr><?php endif; ?>
              <?php $i++; endforeach; ?>
            </table>
<?php else: ?>
<strong>No supported wireless interfaces were found for status display (only cards that use the wi[n] or ath[n] driver are supported).</strong>
<?php endif; ?>
<?php include("fend.inc"); ?>
