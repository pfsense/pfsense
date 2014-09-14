<?php
/*
        $Id$
        Copyright 2007 Scott Dale
        Part of pfSense widgets (https://www.pfsense.org)
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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("/usr/local/www/widgets/include/interfaces.inc");

		$i = 0;
		$ifdescrs = get_configured_interface_with_descr();
?>

	         <table bgcolor="#990000" width="100%" border="0" cellspacing="0" cellpadding="0" summary="interfaces">
				<?php
				foreach ($ifdescrs as $ifdescr => $ifname) {
					$ifinfo = get_interface_info($ifdescr);
					$iswireless = is_interface_wireless($ifdescr);
				?>
				<tr>
				<td class="vncellt" rowspan="2">
				<?php
				if($ifinfo['ppplink']) {
					echo "<img src='./themes/{$g['theme']}/images/icons/icon_3g.gif' alt='3g' />";
				} else if($iswireless) {
					if($ifinfo['status'] == "associated") { ?>
						<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_wlan.gif" alt="wlan" />
					<?php } else { ?>
						<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_wlan_d.gif" alt="wlan_d" />
					<?php } ?>
				<?php } else { ?>
						<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_cablenic.gif" alt="cablenic" />
				<?php } ?>&nbsp;
				<strong><u>
				<span onclick="location.href='/interfaces.php?if=<?=$ifdescr; ?>'" style="cursor:pointer">
				<?=htmlspecialchars($ifname);?></span></u></strong>
				<?php
					if ($ifinfo['dhcplink'])
						echo "&nbsp;(DHCP)";
				?>
				</td>
				<?php if($ifinfo['status'] == "up" || $ifinfo['status'] == "associated") { ?>
							<td rowspan="2" class="listr" align="center">
								<div id="<?php echo $ifname;?>-up" style="display:inline" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_interface_up.gif" title="<?=$ifname;?> is up" alt="up" /></div>
							</td>
		                <?php } else if ($ifinfo['status'] == "no carrier") { ?>
							<td rowspan="2" class="listr" align="center">
								<div id="<?php echo $ifname;?>-down" style="display:inline" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_interface_down.gif" title="<?=$ifname;?> is down" alt="down" /></div>
							</td>
				<?php }  else if ($ifinfo['status'] == "down") { ?>
							<td rowspan="2" class="listr" align="center">
								<div id="<?php echo $ifname;?>-block" style="display:inline" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_block.gif" title="<?=$ifname;?> is disabled" alt="disabled" /></div>
							</td>
		                <?php } else { ?><?=htmlspecialchars($ifinfo['status']); }?>
							<td class="listr">
								<div id="<?php echo $ifname;?>-media" style="display:inline"><?=htmlspecialchars($ifinfo['media']);?></div>
							</td>
						</tr>
						<tr>
							<td class="vncellt" style="border-right:1px solid #999999;">
								<?php if($ifinfo['ipaddr'] != "") { ?>
                                                                	<div id="<?php echo $ifname;?>-ip" style="display:inline"><?=htmlspecialchars($ifinfo['ipaddr']);?> </div>
                                                                	<br />
								<?php }
								if ($ifinfo['ipaddrv6'] != "") { ?>
									<div id="<?php echo $ifname;?>-ipv6" style="display:inline"><?=htmlspecialchars($ifinfo['ipaddrv6']);?> </div>
								<?php } ?>
							</td>
						</tr>
				<?php 	}//end for each ?>
			</table>
