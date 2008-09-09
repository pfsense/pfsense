<?php
/*
        $Id$
        Copyright 2007 Scott Dale
        Part of pfSense widgets (www.pfsense.com)
        originally based on m0n0wall (http://m0n0.ch/wall)

        Copyright (C) 2004-2005 T. Lechat <dev@lechat.org>, Manuel Kasper <mk@neon1.net>
        and Jonathan Watt <jwatt@jwatt.org>.
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
require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("/usr/local/www/widgets/include/interfaces.inc");

		$i = 0; 
		$ifdescrs = get_configured_interface_with_descr();
?>
			
	         <table bgcolor="#990000" width="100%" border="0" cellspacing="0" cellpadding="0">
			<?php 
			foreach ($ifdescrs as $ifdescr => $ifname) {
					$ifinfo = get_interface_info($ifdescr);
					$iswireless = is_interface_wireless($ifdescr);
				?>
				<tr> 
				<td class="vncellt" width="30%">
				<?php if($iswireless) {
						 if($ifinfo['status'] == "associated") { ?> 
		                  <img src="./themes/<?= $g['theme']; ?>/images/icons/icon_wlan.gif" />
		                  <? } else { ?>
		                  	<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_wlan_d.gif" />
		                  	<? } ?>
		              <? } else { ?>
		                  <img src="./themes/<?= $g['theme']; ?>/images/icons/icon_cablenic.gif"/>
		                  <? } ?>&nbsp; 
				<strong><u>
					<span onClick="location.href='/interfaces.php?if=<?=$ifdescr; ?>'" style="cursor:pointer">
				
				<?=htmlspecialchars($ifname);?></span></u></strong>
				
				</td>
				<td width="70%"  class="listr">
	                 <?php if($ifinfo['status'] == "up" || $ifinfo['status'] == "associated") { ?> 
	                 		<div id="<?php echo $ifname;?>-up" style="display:inline" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_interface_up.gif" title="<?=$ifname;?> is up" /></div>
	                 		<div id="<?php echo $ifname;?>-down" style="display:none" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_interface_down.gif" title="<?=$ifname;?> is down" /></div>
	                 		<div id="<?php echo $ifname;?>-block" style="display:none" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_block.gif" title="<?=$ifname;?> is disabled" /></div>
	                 		&nbsp; 
		                <? } else if ($ifinfo['status'] == "no carrier") { ?>
		                  	<div id="<?php echo $ifname;?>-down" style="display:inline" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_interface_down.gif" title="<?=$ifname;?> is down" /></div>
		                  	<div id="<?php echo $ifname;?>-block" style="display:none" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_block.gif" title="<?=$ifname;?> is disabled" /></div>
	                 		<div id="<?php echo $ifname;?>-up" style="display:none" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_interface_up.gif" title="<?=$ifname;?> is up" /></div>
	                 		&nbsp;
						<? }  else if ($ifinfo['status'] == "down") { ?>
		                	<div id="<?php echo $ifname;?>-block" style="display:inline" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_block.gif" title="<?=$ifname;?> is disabled" /></div>
		                	<div id="<?php echo $ifname;?>-up" style="display:none" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_interface_up.gif" title="<?=$ifname;?> is up" /></div>
	                 		<div id="<?php echo $ifname;?>-down" style="display:none" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_interface_down.gif" title="<?=$ifname;?> is down" /></div>
	                 		 &nbsp;
		                <? } else { ?><?=htmlspecialchars($ifinfo['status']); }?>				
				  
	                 <div id="<?php echo $ifname;?>-ip" style="display:inline"><?=htmlspecialchars($ifinfo['ipaddr']);?>&nbsp; 
	                 <?php if ($ifinfo['dhcplink']) { ?>
						(DHCP)<br>
					 <? } ?></div>
					 	               
		             <div id="<?php echo $ifname;?>-media" style="display:inline"><?=htmlspecialchars($ifinfo['media']);?></div>
		          </td></tr><?php 
			}//end for each
			?> 
			</table>
