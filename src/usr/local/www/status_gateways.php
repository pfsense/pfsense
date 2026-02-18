<?php
/*
 * status_gateways.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2026 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2010 Seth Mos <seth.mos@dds.nl>
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
##|*IDENT=page-status-gateways
##|*NAME=Status: Gateways
##|*DESCR=Allow access to the 'Status: Gateways' page.
##|*MATCH=status_gateways.php*
##|-PRIV

require_once("guiconfig.inc");

if ($_POST['act'] == 'killgw') {
	if (!empty($_POST['gwname'])) {
		mwexec("/sbin/pfctl -k label -k " . escapeshellarg(make_rule_label_string($_POST['gwname'], RULE_LABEL_KEY_GATEWAY, false)));
	} elseif (!empty($_POST['gwip']) && is_ipaddr($_POST['gwip'])) {
		list($ipaddr, $scope) = explode('%', $_POST['gwip']);
		mwexec("/sbin/pfctl -k gateway -k " . escapeshellarg($ipaddr));
	} elseif (!empty($_POST['gwdef4'])) {
		mwexec("/sbin/pfctl -k gateway -k '0.0.0.0'");
	} elseif (!empty($_POST['gwdef6'])) {
		mwexec("/sbin/pfctl -k gateway -k '::'");
	}

	header("Location: status_gateways.php");
	exit;
}

$pgtitle = array(gettext("Status"), gettext("Gateways"));
$pglinks = array("", "@self");
$shortcut_section = "gateways";
include("head.inc");

/* active tabs */
$tab_array = array();
$tab_array[] = array(gettext("Gateways"), true, "status_gateways.php");
$tab_array[] = array(gettext("Gateway Groups"), false, "status_gateway_groups.php");
display_top_tabs($tab_array);
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Gateways')?></h2></div>
	<div class="panel-body">

<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
		<thead>
			<tr>
				<th><?=gettext("Name"); ?></th>
				<th><?=gettext("Gateway"); ?></th>
				<th><?=gettext("Monitor"); ?></th>
				<th><?=gettext("RTT"); ?></th>
				<th><?=gettext("RTTsd"); ?></th>
				<th><?=gettext("Loss"); ?></th>
				<th><?=gettext("Status"); ?></th>
				<th><?=gettext("Description"); ?></th>
				<th><?=gettext("Action"); ?></th>
			</tr>
		</thead>
		<tbody>
<?php		foreach (get_gateways() as $gateway) {
			list($gateway_status, $gateway_details) = get_gateway_status($gateway);
			$gwip = array_get_path($gateway_details, 'config/gateway');
?>
			<tr>
				<td>
					<?=htmlspecialchars(array_get_path($gateway_details, 'config/name', ''));?>
<?php		
					if (!is_null(array_get_path($gateway_details, 'config/isdefaultgw'))) {
						echo " <strong>(default)</strong>";
					}
?>			
				</td>
				<td>
					<?=$gwip;?>
				</td>
				<td>
<?php
					if ($gateway_status != GW_STATUS_UNKNOWN) {
						if (($gateway_status == GW_STATUS_ONLINE_FORCED) || ($gateway_status == GW_STATUS_OFFLINE_FORCED)) {
							echo "(unmonitored)";
						} else {
							echo array_get_path($gateway_details, 'status/monitorip');
						}
					}
?>
				</td>
				<td>
<?php
					if ($gateway_status != GW_STATUS_UNKNOWN) {
						if (($gateway_status != GW_STATUS_ONLINE_FORCED) && ($gateway_status != GW_STATUS_OFFLINE_FORCED)) {
							echo array_get_path($gateway_details, 'status/delay');
						}
					} else {
						echo gettext("Pending");
					}
?>
				</td>
				<td>
<?php
					if ($gateway_status != GW_STATUS_UNKNOWN) {
						if (($gateway_status != GW_STATUS_ONLINE_FORCED) && ($gateway_status != GW_STATUS_OFFLINE_FORCED)) {
							echo array_get_path($gateway_details, 'status/stddev');
						}
					} else {
						echo gettext("Pending");
					}
?>
				</td>
				<td>
<?php
					if ($gateway_status != GW_STATUS_UNKNOWN) {
						if (($gateway_status != GW_STATUS_ONLINE_FORCED) && ($gateway_status != GW_STATUS_OFFLINE_FORCED)) {
							echo array_get_path($gateway_details, 'status/loss');
						}
					} else {
						echo gettext("Pending");
					}
?>
				</td>
<?php
					$gatewy_status_text = get_gateway_status_text($gateway_status);
					$status_text = $gatewy_status_text['reason'];
					$bgcolor = match ($gatewy_status_text['level']) {
						GW_STATUS_LEVEL_SUCCESS => 'bg-success',
						GW_STATUS_LEVEL_WARNING => 'bg-warning',
						GW_STATUS_LEVEL_FAILURE => 'bg-danger',
						default => 'bg-info',
					};
?>
				<td class="<?=$bgcolor?>">
					<strong><?=$status_text?></strong>
				</td>
				<td>
					<?=htmlspecialchars(array_get_path($gateway_details, 'config/descr', '')); ?>
				</td>
				<td>
					<a href="?act=killgw&amp;gwname=<?=urlencode(array_get_path($gateway_details, 'config/name', ''));?>" class="fa-solid fa-times-circle do-confirm" title="<?=gettext('Kill all firewall states created by policy routing rules using this specific gateway by name.')?>" usepost></a>
<?php if (!empty($gwip) && is_ipaddr($gwip)): ?>
					<a href="?act=killgw&amp;gwip=<?=urlencode($gwip);?>" class="fa-regular fa-circle-xmark do-confirm" title="<?=gettext('Kill all firewall states using this gateway IP address via policy routing and reply-to.')?>" usepost></a>
<?php endif; ?>
<?php if (!is_null(array_get_path($gateway_details, 'config/isdefaultgw'))): ?>
	<?php if (array_get_path($gateway_details, 'config/ipprotocol') != 'inet6'): ?>
					<a href="?act=killgw&amp;gwdef4=true" class="fa-solid fa-times do-confirm" title="<?=gettext('Kill all firewall states which use the default IPv4 gateway (0.0.0.0) and not policy routing or reply-to rules.')?>" usepost></a>
	<?php else: ?>
					<a href="?act=killgw&amp;gwdef6=true" class="fa-solid fa-times do-confirm" title="<?=gettext('Kill all firewall states which use the default IPv6 gateway (::) and not policy routing or reply-to rules.')?>" usepost></a>
	<?php endif; ?>
<?php endif; ?>
				</td>
			</tr>
<?php	} ?>	<!-- End-of-foreach -->
		</tbody>
	</table>
</div>

	</div>
</div>

<?php include("foot.inc"); ?>
