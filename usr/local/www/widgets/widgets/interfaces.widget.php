<?php
/*
        $Id$
        Copyright (C) 2013-2014 Electric Sheep Fencing, LP

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

$ifdescrs = get_configured_interface_with_descr();
?>

<table bgcolor="#990000" width="100%" border="0" cellspacing="0" cellpadding="0" summary="interfaces">
<?php
foreach ($ifdescrs as $ifdescr => $ifname):
	$ifinfo = get_interface_info($ifdescr);

	if ($ifinfo['ppplink']) {
		$icon = '3g';
	} else if (is_interface_wireless($ifdescr)) {
		if($ifinfo['status'] == "associated")
			$icon = 'wlan';
		else
			$icon = 'wlan_d';
	} else
		$icon = 'cablenic';

	if ($ifinfo['status'] == "up" || $ifinfo['status'] == "associated") {
		$status = '-up';
		$status_text = 'up';
		$status_icon = 'icon_interface_up.gif';
	} elseif ($ifinfo['status'] == "no carrier") {
		$status = '-down';
		$status_text = 'down';
		$status_icon = 'icon_interface_down.gif';
	} elseif ($ifinfo['status'] == "down") {
		$status = '-block';
		$status_text = 'disabled';
		$status_icon = 'icon_block.gif';
	} else
		$status = '';
?>
	<tr>
		<td class="vncellt" rowspan="2">
			<span onclick="location.href='/interfaces.php?if=<?=$ifdescr; ?>'" style="cursor:pointer; white-space:nowrap">
				<img src="./themes/<?=$g['theme'];?>/images/icons/icon_<?=$icon;?>.gif" alt="<?=$icon;?>" />
				<u><?=htmlspecialchars($ifname);?></u>
			</span>
<?php
		if ($ifinfo['dhcplink'])
			echo "<br />(DHCP)";
?>
		</td>
<?php
	if ($status === ''):
		echo htmlspecialchars($ifinfo['status']);
	else:
?>
		<td rowspan="2" class="listr" align="center">
			<div id="<?php echo $ifname . $status;?>" style="display:inline" >
				<img src="./themes/<?= $g['theme']; ?>/images/icons/<?=$status_icon;?>" title="<?=$ifname;?> is <?=$status_text;?>" alt="<?=$status;?>" />
			</div>
		</td>
<?php
	endif;
?>
		<td class="listr">
			<div id="<?php echo $ifname;?>-media" style="display:inline"><?=htmlspecialchars($ifinfo['media']);?></div>
		</td>
	</tr>
	<tr>
		<td class="listr">
<?php
		if($ifinfo['ipaddr'] != ""):
?>
			<div id="<?php echo $ifname;?>-ip" style="display:inline"><strong><?=htmlspecialchars($ifinfo['ipaddr']);?> </strong></div>
			<br />
<?php
		endif;
		if ($ifinfo['ipaddrv6'] != ""):
?>
			<div id="<?php echo $ifname;?>-ipv6" style="display:inline"><strong><?=htmlspecialchars($ifinfo['ipaddrv6']);?> </strong></div>
<?php
		endif;
?>
		</td>
	</tr>
<?php
endforeach;
?>
</table>
