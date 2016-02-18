<?php
/*
	status_ipsec_leases.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *  Redistribution and use in source and binary forms, with or without modification,
 *  are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution.
 *
 *  3. All advertising materials mentioning features or use of this software
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-status-ipsec-leases
##|*NAME=Status: IPsec: Leases
##|*DESCR=Allow access to the 'Status: IPsec: Leases' page.
##|*MATCH=status_ipsec_leases.php*
##|-PRIV

require("guiconfig.inc");
require("ipsec.inc");

$pgtitle = array(gettext("Status"), gettext("IPsec"), gettext("Leases"));
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
					<th><?=gettext("Usage")?></th>
					<th><?=gettext("Online")?></th>
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
						<?=$pool['usage']?>
					</td>
					<td>
						<?=$pool['online']?>
					</td>

<?php
				$leaserow = true;
				if (is_array($pool['lease']) && count($pool['lease']) > 0) {
					foreach ($pool['lease'] as $lease) {
						if (!$leaserow) {
							// On subsequent rows the first three columns are blank
?>
				<tr>
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
print_info_box(sprintf(gettext('You can configure IPsec %1$shere%2$s.'), '<a href="vpn_ipsec.php">', '</a>'), 'info', false);
?>
</div>
<?php
include("foot.inc");
