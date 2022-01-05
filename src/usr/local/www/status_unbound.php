<?php
/*
 * status_unbound.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-status-dns-resolver
##|*NAME=Status: DNS Resolver
##|*DESCR=Allow access to the 'Status: DNS Resolver' page.
##|*MATCH=status_unbound.php*
##|-PRIV

require_once("guiconfig.inc");

$pgtitle = array(gettext("Status"), gettext("DNS Resolver"));
$shortcut_section = "resolver";

include("head.inc");

$infra_cache_entries = array();
$errors = "";

// Check if unbound is enabled and running, bail if not
global $config;
if (!isset($config['unbound']['enable']) || !is_service_running('unbound')) {
	print_info_box(gettext("The DNS Resolver is disabled or stopped."), 'warning', false);
} else {
	exec("/usr/local/sbin/unbound-control -c {$g['unbound_chroot_path']}/unbound.conf dump_infra", $infra_cache_entries, $ubc_ret);
}

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("DNS Resolver Infrastructure Cache Speed")?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th><?=gettext("Server")?></th>
						<th><?=gettext("Zone")?></th>
						<th><?=gettext("TTL")?></th>
						<th><?=gettext("Ping")?></th>
						<th><?=gettext("Var")?></th>
						<th><?=gettext("RTT")?></th>
						<th><?=gettext("RTO")?></th>
						<th><?=gettext("Timeout A")?></th>
						<th><?=gettext("Timeout AAAA")?></th>
						<th><?=gettext("Timeout Other")?></th>
					</tr>
				</thead>
				<tbody>
<?php if (empty($infra_cache_entries)): ?>
					<tr>
						<td colspan="10">
							<i><?= gettext("No Data") ?></i>
						</td>
					</tr>
<?php endif; ?>
<?php
foreach ($infra_cache_entries as $ice) {
	$line = explode(' ', $ice);
?>
					<tr>
						<td>
							<?=$line[0]?>
						</td>
						<td>
							<?=$line[1]?>
						</td>
<?php
	if ($line[3] == "expired"):
?>
						<td colspan="4">
							<?=$line[3]?>
						</td>
						<td>
							<?=$line[5]?>
						</td>
						<td colspan="4">
							&nbsp;
						</td>
<?php	else: ?>
						<td>
							<?=$line[3]?>
						</td>
						<td>
							<?=$line[5]?>
						</td>
						<td>
							<?=$line[7]?>
						</td>
						<td>
							<?=$line[9]?>
						</td>
						<td>
							<?=$line[11]?>
						</td>
						<td>
							<?=$line[13]?>
						</td>
						<td>
							<?=$line[15]?>
						</td>
						<td>
							<?=$line[17]?>
						</td>
					</tr>

<?php	endif;
}
?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("DNS Resolver Infrastructure Cache Stats")?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th><?=gettext("Server")?></th>
						<th><?=gettext("Zone")?></th>
						<th><?=gettext("eDNS Lame Known")?></th>
						<th><?=gettext("eDNS Version")?></th>
						<th><?=gettext("Probe Delay")?></th>
						<th><?=gettext("Lame DNSSEC")?></th>
						<th><?=gettext("Lame Rec")?></th>
						<th><?=gettext("Lame A")?></th>
						<th><?=gettext("Lame Other")?></th>
					</tr>
				</thead>
				<tbody>
<?php if (empty($infra_cache_entries)): ?>
					<tr>
						<td colspan="17">
							<i><?= gettext("No Data") ?></i>
						</td>
					</tr>
<?php endif; ?>
<?php
foreach ($infra_cache_entries as $ice) {
	$line = explode(' ', $ice);
?>
					<tr>
						<td>
							<?=$line[0]?>
						</td>
						<td>
							<?=$line[1]?>
						</td>
<?php
	if ($line[3] == "expired"):
?>
						<td colspan="7">
							<?=$line[3]?>
							&nbsp;
						</td>
<?php	else: ?>
						<td>
							<?=$line[19]?>
						</td>
						<td>
							<?=$line[21]?>
						</td>
						<td>
							<?=$line[23]?>
						</td>
						<td>
							<?=$line[26]?>
						</td>
						<td>
							<?=$line[28]?>
						</td>
						<td>
							<?=$line[30]?>
						</td>
						<td>
							<?=$line[32]?>
						</td>
					</tr>

<?php	endif;
}
?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?php
include("foot.inc");
