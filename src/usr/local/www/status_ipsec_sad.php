<?php
/*
 * status_ipsec_sad.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-status-ipsec-sad
##|*NAME=Status: IPsec: SADs
##|*DESCR=Allow access to the 'Status: IPsec: SADs' page.
##|*MATCH=status_ipsec_sad.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("ipsec.inc");

$pgtitle = array(gettext("Status"), gettext("IPsec"), gettext("SADs"));
$pglinks = array("", "status_ipsec.php", "@self");
$shortcut_section = "ipsec";
include("head.inc");

$sad = ipsec_dump_sad();

/* delete any SA? */
if ($_POST['act'] == "del") {
	$fd = @popen("/sbin/setkey -c > /dev/null 2>&1", "w");
	if ($fd) {
		fwrite($fd, "delete {$_POST['src']} {$_POST['dst']} {$_POST['proto']} {$_POST['spi']} ;\n");
		pclose($fd);
		sleep(1);
	}
}

$tab_array = array();
$tab_array[] = array(gettext("Overview"), false, "status_ipsec.php");
$tab_array[] = array(gettext("Leases"), false, "status_ipsec_leases.php");
$tab_array[] = array(gettext("SADs"), true, "status_ipsec_sad.php");
$tab_array[] = array(gettext("SPDs"), false, "status_ipsec_spd.php");
display_top_tabs($tab_array);

if (count($sad)) {
?>
	<div table-responsive>
		<table class="table table-striped table-hover table-condensed">
			<thead>
				<tr>
					<th><?=gettext("Source")?></th>
					<th><?=gettext("Destination")?></th>
					<th><?=gettext("Protocol")?></th>
					<th><?=gettext("SPI")?></th>
					<th><?=gettext("Enc. alg.")?></th>
					<th><?=gettext("Auth. alg.")?></th>
					<th><?=gettext("Data")?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($sad as $sa) { ?>
			<tr>
				<td>
					<?=htmlspecialchars($sa['src'])?>
				</td>
				<td>
					<?=htmlspecialchars($sa['dst'])?>
				</td>
				<td>
					<?=htmlspecialchars(strtoupper($sa['proto']))?>
				</td>
				<td>
					<?=htmlspecialchars($sa['spi'])?>
				</td>
				<td>
					<?=htmlspecialchars($sa['ealgo'])?>
				</td>
				<td>
					<?=htmlspecialchars($sa['aalgo'])?>
				</td>
				<td>
					<?=htmlspecialchars($sa['data'])?></td>
				<td>
					<?php
						$args = "src=" . rawurlencode($sa['src']);
						$args .= "&amp;dst=" . rawurlencode($sa['dst']);
						$args .= "&amp;proto=" . rawurlencode($sa['proto']);
						$args .= "&amp;spi=" . rawurlencode("0x" . $sa['spi']);
					?>
					<a href="status_ipsec_sad.php?act=del&amp;<?=$args?>"><i class="fa fa-trash" title="<?=gettext("Remove this SPD Entry")?>" usepost></i></a>
				</td>
			</tr>

			<?php
			} ?>
			</tbody>
		</table>
	</div>
<?php
} else {
	print_info_box(gettext('No IPsec security associations.'));
}

if (ipsec_enabled()) {
?>
<div class="infoblock">
<?php
} else {
?>
<div class="infoblock blockopen">
<?php
}
print_info_box(sprintf(gettext('IPsec can be configured %1$shere%2$s.'), '<a href="vpn_ipsec.php">', '</a>'), 'info', false);
?>
</div>
<?php
include("foot.inc");
