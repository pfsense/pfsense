<?php
/*
 * status_ipsec_spd.php
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
##|*IDENT=page-status-ipsec-spd
##|*NAME=Status: IPsec: SPD
##|*DESCR=Allow access to the 'Status: IPsec: SPD' page.
##|*MATCH=status_ipsec_spd.php*
##|-PRIV

define('RIGHTARROW', '&#x25ba;');
define('LEFTARROW',  '&#x25c4;');

require_once("guiconfig.inc");
require_once("ipsec.inc");

$pgtitle = array(gettext("Status"), gettext("IPsec"), gettext("SPDs"));
$pglinks = array("", "status_ipsec.php", "@self");
$shortcut_section = "ipsec";
include("head.inc");

$spd = ipsec_dump_spd();

$tab_array = array();
$tab_array[0] = array(gettext("Overview"), false, "status_ipsec.php");
$tab_array[1] = array(gettext("Leases"), false, "status_ipsec_leases.php");
$tab_array[2] = array(gettext("SADs"), false, "status_ipsec_sad.php");
$tab_array[3] = array(gettext("SPDs"), true, "status_ipsec_spd.php");
display_top_tabs($tab_array);

if (count($spd)) {
?>
	<div class="table-responsive">
		<table class="table table-striped table-condensed table-hover sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><?= gettext("Mode"); ?></th>
					<th><?= gettext("Source"); ?></th>
					<th><?= gettext("Destination"); ?></th>
					<th><?= gettext("Direction"); ?></th>
					<th><?= gettext("Protocol"); ?></th>
					<th><?= gettext("Tunnel endpoints"); ?></th>
				</tr>
			</thead>

			<tbody>
<?php
		foreach ($spd as $sp) {
			if ($sp['dir'] == 'in') {
				$dirstr = LEFTARROW . gettext(' Inbound');
			} else {
				$dirstr = RIGHTARROW . gettext(' Outbound');
			}
?>
				<tr>
					<td>
					<? if ($sp['scope'] == 'ifnet'): ?>
						<?=htmlspecialchars(gettext("VTI"))?>
						<? if (!empty($sp['ifname'])): ?>
							<?=htmlspecialchars($sp['ifname'])?>
						<? endif; ?>
					<? else: ?>
						<?=htmlspecialchars(gettext("Tunnel"))?>
					<? endif; ?>
					</td>
					<td>
						<?=htmlspecialchars($sp['srcid'])?>
					</td>
					<td>
						<?=htmlspecialchars($sp['dstid'])?>
					</td>
					<td>
						<?=$dirstr ?>
					</td>
					<td>
						<?=htmlspecialchars(strtoupper($sp['proto']))?>
					</td>
					<td>
					<? if ($sp['dir'] == 'in'): ?>
						<?=htmlspecialchars($sp['dst'])?> <?= LEFTARROW ?> <?=htmlspecialchars($sp['src'])?>
					<? else: ?>
						<?=htmlspecialchars($sp['src'])?> <?= RIGHTARROW ?> <?=htmlspecialchars($sp['dst'])?>
					<? endif; ?>
					</td>
				</tr>
<?php
		}
?>
			</tbody>
		</table>
	</div>
<?php
} else {
	print_info_box(gettext('No IPsec security policies configured.'));
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
