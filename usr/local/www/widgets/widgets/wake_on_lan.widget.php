<?php
/*
	wake_on_lan.widget.php
	Copyright (C) 2010 Yehuda Katz
	
	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:
	
	1. Redistributions of source code must retain the above copyright notice,
	this list of conditions and the following disclaimer.
	
	2. Redistributions in binary form must reproduce the above copyright
	notice, this list of conditions and the following disclaimer in the
	documentation and/or other materials provided with the distribution.
	
	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INClUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
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
require_once("/usr/local/www/widgets/include/wake_on_lan.inc");

if (is_array($config['wol']['wolentry']))
	$wolcomputers = $config['wol']['wolentry'];
else
	$wolcomputers = array();

?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="wol status">
	<tr>
		<?php
		echo '<td class="widgetsubheader" align="center"><b>' . gettext("Computer / Device") . '</b></td>';
		echo '<td class="widgetsubheader" align="center"><b>' . gettext("Interface") . '</b></td>';
		echo '<td class="widgetsubheader" align="center"><b>' . gettext("Status") . '</b></td>';
		?>
		<td class="widgetsubheader">&nbsp;</td>
	</tr>
<?php

if (count($wolcomputers) > 0) {
	foreach($wolcomputers as $wolent) {
		echo '<tr><td class="listlr">' . $wolent['descr'] . '<br />' . $wolent['mac'] . '</td>' . "\n";
		echo '<td class="listr">' . convert_friendly_interface_to_friendly_descr($wolent['interface']) . '</td>' . "\n";
		
		$is_active = exec("/usr/sbin/arp -an |/usr/bin/grep {$wolent['mac']}| /usr/bin/wc -l|/usr/bin/awk '{print $1;}'");
		if($is_active == 1) {
			echo '<td class="listr" align="center">' . "\n";
			echo "<img src=\"/themes/" . $g["theme"] . "/images/icons/icon_pass.gif\" alt=\"pass\" /> " . gettext("Online") . "</td>\n";
		} else {
			echo '<td class="listbg" align="center">' . "\n";
			echo "<img src=\"/themes/" . $g["theme"] . "/images/icons/icon_block.gif\" alt=\"block\" />&nbsp;<font color=\"white\">" . gettext("Offline") . "</font></td>\n";
		}
		echo '<td valign="middle" class="list nowrap">';
		/*if($is_active) { */
			/* Will always show wake-up button even if pfsense thinks it is awake */
		/* } else { */
			echo "<a href='services_wol.php?mac={$wolent['mac']}&amp;if={$wolent['interface']}'> ";
			echo "<img title='" . gettext("Wake Up") . "' border='0' src='./themes/".$g['theme']."/images/icons/icon_wol_all.gif' alt='wol' /></a>\n";
		/* } */
		echo "</td></tr>\n";
	}
} else {
	echo "<tr><td colspan=\"3\" align=\"center\">" . gettext("No saved WoL addresses") . ".</td></tr>\n";
}
?>
</table>
<center><a href="status_dhcp_leases.php" class="navlink">DHCP Leases Status</a></center>
