<?php
/*
	wake_on_lan.widget.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2010 Yehuda Katz
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

$nocsrf = true;

require_once("guiconfig.inc");
require_once("/usr/local/www/widgets/include/wake_on_lan.inc");

if (is_array($config['wol']['wolentry'])) {
	$wolcomputers = $config['wol']['wolentry'];
} else {
	$wolcomputers = array();
}

?>
<div class="table-responsive">
<table class="table table-hover table-striped table-condensed">
	<thead>
		<tr>
			<th class="widgetsubheader"><?=gettext("Device")?></th>
			<th class="widgetsubheader"><?=gettext("Interface")?></th>
			<th class="widgetsubheader"><?=gettext("Status")?></th>
			<th class="widgetsubheader"><?=gettext("Wake")?></th>
		</tr>
	</thead>
	<tbody>
<?php
if (count($wolcomputers) > 0) {
	foreach ($wolcomputers as $wolent) {
		echo '<tr><td>' . $wolent['descr'] . '<br />' . $wolent['mac'] . '</td>' . "\n";
		echo '<td>' . convert_friendly_interface_to_friendly_descr($wolent['interface']) . '</td>' . "\n";

		$is_active = exec("/usr/sbin/arp -an |/usr/bin/grep {$wolent['mac']}| /usr/bin/wc -l|/usr/bin/awk '{print $1;}'");
		$status = exec("/usr/sbin/arp -an | /usr/bin/awk '$4 == \"{$wolent['mac']}\" { print $7 }'");

		if ($status == 'expires') {
			echo '<i class="fa fa-arrow-right" data-toggle="tooltip" title="' . gettext("Online") . '"></i>';
		} else if ($status == 'permanent') {
			echo '<td class="text-center">' . "\n";
			echo '<i class="fa fa-arrow-right" data-toggle="tooltip" title="' . gettext("Static ARP") . '"></i>';
		} else {
			echo '<td class="text-center">' . "\n";
			echo '<i class="fa fa-ban" data-toggle="tooltip" title="' . gettext("Offline") . '"></i>';
		}

		echo "</td>\n<td>";
		echo "<a href='services_wol.php?mac={$wolent['mac']}&amp;if={$wolent['interface']}'> ";
		echo '<i class="fa fa-bed" data-toggle="tooltip" title="' . gettext("Wake up!") . '"></i></a>' . "\n";
		echo "</td></tr>\n";
	}
} else {
	echo "<tr><td colspan=\"4\" class=\"text-center\">" . gettext("No saved WoL addresses") . ".</td></tr>\n";
}
?>
	</tbody>
</table>
<p class="text-center"><a href="status_dhcp_leases.php" class="navlink">DHCP Leases Status</a></p>
</div>
