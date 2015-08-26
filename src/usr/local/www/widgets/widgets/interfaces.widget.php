<?php
/*
	interfaces.widget.php
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP

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

<table class="table table-striped table-hover">
<?php
foreach ($ifdescrs as $ifdescr => $ifname):
	$ifinfo = get_interface_info($ifdescr);

	if ($ifinfo['ppplink']) {
		$icon = 'headphones';
	} else if (is_interface_wireless($ifdescr)) {
		if ($ifinfo['status'] == "associated") {
			$icon = 'wlan';
		} else {
			$icon = 'wlan_d';
		}
	} else {
		$icon = 'cablenic';
	}

	if ($ifinfo['status'] == "up" || $ifinfo['status'] == "associated") {
		$known_status = true;
		$up_display = "inline";
		$down_display = "none";
		$block_display = "none";
	} elseif ($ifinfo['status'] == "no carrier") {
		$known_status = true;
		$up_display = "none";
		$down_display = "inline";
		$block_display = "none";
	} elseif ($ifinfo['status'] == "down") {
		$known_status = true;
		$up_display = "none";
		$down_display = "none";
		$block_display = "inline";
	} else {
		$known_status = false;
	}
?>
	<tr>
		<td title="<?=htmlspecialchars($ifinfo['macaddr'])?>">
			<i class="icon icon-<?=$icon?>"></i>
			<a href="/interfaces.php?if=<?=$ifdescr?>">
				<?=htmlspecialchars($ifname);?>
			</a>
		</td>
		<td>
			<?php if (isset($status)):?>
				<i class="icon icon-<?=status?>-circle" alt="<?=htmlspecialchars($ifinfo['status'])?>"></i>
			<?php else: ?>
				<?=htmlspecialchars($ifinfo['status'])?>
			<?php endif; ?>
		</td>
		<td>
			<?=htmlspecialchars($ifinfo['media']);?>
		</td>
		<td<?=($ifinfo['dhcplink'] ? ' title="via dhcp"':'')?>>
			<?php if (empty($addresses)): ?>
				n/a
			<?php else: ?>
				<?= implode('<br />', $addresses)?>
			<?php endif; ?>
		</td>
	</tr>
<?php
endforeach;
?>
</table>
