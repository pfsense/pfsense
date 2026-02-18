<?php
/*
 * status_gateway_groups.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2026 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2010 Seth Mos <seth.mos@dds.nl>
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
##|*IDENT=page-status-gatewaygroups
##|*NAME=Status: Gateway Groups
##|*DESCR=Allow access to the 'Status: Gateway Groups' page.
##|*MATCH=status_gateway_groups.php*
##|-PRIV

define('COLOR', true);

require_once("guiconfig.inc");

if ($_POST['act'] == 'killgw') {
	if (!empty($_POST['gwname'])) {
		remove_failover_states($_POST['gwname']);
	} elseif (!empty($_POST['gwip']) && is_ipaddr($_POST['gwip'])) {
		list($ipaddr, $scope) = explode('%', $_POST['gwip']);
		mwexec("/sbin/pfctl -k gateway -k " . escapeshellarg($ipaddr));
	}

	header("Location: status_gateways.php");
	exit;
}

$changedesc = gettext("Gateway Groups") . ": ";

$pgtitle = array(gettext("Status"), gettext("Gateways"), gettext("Gateway Groups"));
$pglinks = array("", "status_gateways.php", "@self");
$shortcut_section = "gateway-groups";
include("head.inc");

$tab_array = array();
$tab_array[0] = array(gettext("Gateways"), false, "status_gateways.php");
$tab_array[1] = array(gettext("Gateway Groups"), true, "status_gateway_groups.php");
display_top_tabs($tab_array);
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Gateway Groups')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-hover table-condensed table-striped">
				<thead>
					<tr>
						<th><?=gettext("Group Name"); ?></th>
						<th><?=gettext("Gateways"); ?></th>
						<th><?=gettext("Description"); ?></th>
						<th><?=gettext("Action"); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach (config_get_path('gateways/gateway_group', []) as $gateway_group): ?>
					<tr>
						<td>
							<?=htmlspecialchars($gateway_group['name'])?>
						</td>
						<td>
							<table class="table table-bordered table-condensed">
<?php
						/* process which priorities we have */
						$priorities = array();
						foreach ($gateway_group['item'] as $item) {
							$itemsplit = explode("|", $item);
							$priorities[$itemsplit[1]] = true;
						}
						$priority_count = count($priorities);
						ksort($priorities);
?>
								<thead>
									<tr>
<?php
							// Make a column for each tier
							foreach ($priorities as $number => $tier) {
								echo "<th>" . sprintf(gettext("Tier %s"), $number) . "</th>";
							}
?>
									</tr>
								</thead>
								<tbody>
<?php
							/* inverse gateway group to gateway priority */
							$priority_arr = array();
							foreach ($gateway_group['item'] as $item) {
								$itemsplit = explode("|", $item);
								$priority_arr[$itemsplit[1]][] = $itemsplit[0];
							}
							ksort($priority_arr);
							$p = 1;
							foreach ($priority_arr as $tier) {
								/* for each priority process the gateways */
								foreach ($tier as $member) {
									/* we always have $priority_count fields */
?>
									<tr>
<?php
									list($gateway_status, $gateway_details) = get_gateway_status($member);
									$gwip = array_get_path($gateway_details, 'config/gateway');
									$c = 1;
									while ($c <= $priority_count) {
										if ($p == $c) {
											$gatewy_status_text = get_gateway_status_text($gateway_status);
											$status_text = $gatewy_status_text['reason'];
											$bgcolor = match ($gatewy_status_text['level']) {
												GW_STATUS_LEVEL_SUCCESS => 'bg-success',
												GW_STATUS_LEVEL_WARNING => 'bg-warning',
												GW_STATUS_LEVEL_FAILURE => 'bg-danger',
												default => 'bg-info',
											};

											if (!COLOR) {
												$bgcolor = "";
											}
?>
										<td class="<?=$bgcolor?>">
											<?=htmlspecialchars(array_get_path($gateway_details, 'config/name', ''));?>
<?php if (!empty($gwip) && is_ipaddr($gwip)): ?>
											<a href="?act=killgw&amp;gwip=<?=urlencode($gwip);?>" class="fa-regular fa-circle-xmark do-confirm" title="<?=gettext('Kill all firewall states using this gateway IP address via policy routing and reply-to.')?>" usepost></a>
<?php endif; ?>
											<br/><?=$status_text?>
										</td>

<?php
										} else {
?>
										<td>
										</td>
<?php							}
										$c++;
									}
?>
									</tr>
<?php
								}
								$p++;
							}
?>
								</tbody>
							</table>
						</td>
						<td>
							<?=htmlspecialchars($gateway_group['descr'])?>
						</td>
						<td>
							<a href="?act=killgwg&amp;gwgname=<?=urlencode($gateway_group['name']);?>" class="fa-solid fa-times-circle do-confirm" title="<?=gettext('Kill firewall states created by policy routing rules using this specific gateway group.')?>" usepost></a>
						</td>
					</tr>
			<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?php include("foot.inc");
