<?php
/*
 * status_gateway_groups.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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

init_config_arr(array('gateways', 'gateway_group'));
$a_gateway_groups = &$config['gateways']['gateway_group'];
$changedesc = gettext("Gateway Groups") . ": ";

$gateways_status = return_gateways_status();

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
					</tr>
				</thead>
				<tbody>
					<?php foreach ($a_gateway_groups as $gateway_group): ?>
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
							foreach ($priority_arr as $number => $tier) {
								/* for each priority process the gateways */
								foreach ($tier as $member) {
									/* we always have $priority_count fields */
?>
									<tr>
<?php
									$c = 1;
									while ($c <= $priority_count) {
										$monitor = lookup_gateway_monitor_ip_by_name($member);
										if ($p == $c) {
											$status = $gateways_status[$monitor]['status'];
											if (stristr($status, "down")) {
													$online = gettext("Offline");
													$bgcolor = "bg-danger";
											} elseif (stristr($status, "loss")) {
													$online = gettext("Warning, Packetloss");
													$bgcolor = "bg-warning";
											} elseif (stristr($status, "delay")) {
													$online = gettext("Warning, Latency");
													$bgcolor = "bg-warning";
											} elseif ($status == "none") {
													$online = gettext("Online");
													$bgcolor = "bg-success";
											} else {
												$online = gettext("Gathering data");
												$bgcolor = "bg-info";
											}

											if (!COLOR) {
												$bgcolor = "";
											}
?>
										<td class="<?=$bgcolor?>">
											<?=htmlspecialchars($member);?><br/><?=$online?>
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
					</tr>
			<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?php include("foot.inc");
