<?php
/*
 * status_ipsec_leases.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-status-ipsec-leases
##|*NAME=Status: IPsec: Leases
##|*DESCR=Allow access to the 'Status: IPsec: Leases' page.
##|*MATCH=status_ipsec_leases.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("ipsec.inc");

$pgtitle = array(gettext("Status"), gettext("IPsec"), gettext("Leases"));
$pglinks = array("", "status_ipsec.php", "@self");
$shortcut_section = "ipsec";
include("head.inc");

$mobile = ipsec_dump_mobile();

$tab_array = array();
$tab_array[] = array(gettext("Overview"), false, "status_ipsec.php");
$tab_array[] = array(gettext("Leases"), true, "status_ipsec_leases.php");
$tab_array[] = array(gettext("SADs"), false, "status_ipsec_sad.php");
$tab_array[] = array(gettext("SPDs"), false, "status_ipsec_spd.php");
display_top_tabs($tab_array);

if (isset($mobile['pool']) && is_array($mobile['pool'])) {
?>
	<div class="table-responsive">
		<table class="table table-striped table-condensed table-hover sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><?=gettext("Pool")?></th>
					<th><?=gettext("Base")?></th>
					<th><?=gettext("Online")?></th>
					<th><?=gettext("Total Usage")?></th>
					<th><?=gettext("ID")?></th>
					<th><?=gettext("Host")?></th>
					<th><?=gettext("Status")?></th>
				</tr>
			</thead>
			<tbody>
<?php
			foreach ($mobile['pool'] as $pool) {
				// The first row of each pool includes the pool information
?>
				<tr>
					<td>
						<?=$pool['name']?>
					</td>
					<td>
						<?=$pool['base']?>
					</td>
					<td>
						<?=$pool['online']?>
					</td>
					<td>
						<?php if ($pool['size'] > 0): ?>
						<?=$pool['online'] + $pool['offline']?> / <?=$pool['size']?>
						<?php endif; ?>
					</td>

<?php
				$leaserow = true;
				if (is_array($pool['lease']) && (count($pool['lease']) > 0)) {
					foreach ($pool['lease'] as $lease) {
						if (!$leaserow) {
							// On subsequent rows the first three columns are blank
?>
				<tr>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
<?php
						}
						$leaserow = false;
?>
					<td>
						<?=htmlspecialchars($lease['id'])?>
					</td>
					<td>
						<?=htmlspecialchars($lease['host'])?>
					</td>
					<td>
						<?=htmlspecialchars($lease['status'])?>
					</td>
				</tr>
<?php

					}
				} else {
?>
					<td colspan="3" class="warning"><?=gettext('No leases from this pool yet.')?></td>
				</tr>
<?php
				}
			}
?>
			</tbody>
		</table>
	</div>
<?php
} else {
	print_info_box(gettext('No IPsec pools.'));
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
