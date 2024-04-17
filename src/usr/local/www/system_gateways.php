<?php
/*
 * system_gateways.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-system-gateways
##|*NAME=System: Gateways
##|*DESCR=Allow access to the 'System: Gateways' page.
##|*MATCH=system_gateways.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("gwlb.inc");

$simplefields = array('defaultgw4', 'defaultgw6');

config_init_path('gateways/gateway_item');
refresh_gateways(); // make sure we're working on a current gateway list

$pconfig = $_REQUEST;

if ($_POST['order-store']) {
	// Include the rules of this (the selected) interface.
	// If a rule is not in POST[rule], it has been deleted by the user
	$a_gateway_item_new = array();
	//print "<pre>";
	foreach ($_POST['row'] as $id) {
		//print " $id";
		$a_gateway_item_new[] = config_get_path("gateways/gateway_item/{$id}");
	}
	//print_r($a_gateway_item);
	//print_r($a_gateway_item_new);
	//print "</pre>";
	config_set_path('gateways/gateway_item', $a_gateway_item_new);
	//mark_subsystem_dirty('staticroutes');
	write_config("System - Gateways: save default gateway");
} else if ($_POST['save']) {
	unset($input_errors);
	$pconfig = $_POST;
	foreach($simplefields as $field) {
		config_set_path("gateways/{$field}", $pconfig[$field]);
	}
	mark_subsystem_dirty('staticroutes');
	write_config("System - Gateways: save default gateway");
}

if ($_POST['apply']) {

	$retval = 0;

	/* reconfigure our gateway monitor */
	setup_gateways_monitor();
	$retval |= system_routing_configure();
	$retval |= system_resolvconf_generate();
	$retval |= filter_configure();
	/* Dynamic DNS on gw groups may have changed */
	send_event("service reload dyndnsall");

	if ($retval == 0) {
		clear_subsystem_dirty('staticroutes');
	}
}

$a_gateways = get_gateways(GW_CACHE_INDEXED);

function can_delete_disable_gateway_item($id, $disable = false) {
	global $input_errors, $a_gateways;

	if (!isset($a_gateways[$id])) {
		return false;
	}

	foreach (config_get_path('gateways/gateway_group', []) as $group) {
		foreach ($group['item'] as $item) {
			$items = explode("|", $item);
			if ($items[0] == $a_gateways[$id]['name']) {
				if (!$disable) {
					$input_errors[] = sprintf(gettext('Gateway "%1$s" cannot be deleted because it is in use on Gateway Group "%2$s"'), $a_gateways[$id]['name'], $group['name']);
				} else {
					$input_errors[] = sprintf(gettext('Gateway "%1$s" cannot be disabled because it is in use on Gateway Group "%2$s"'), $a_gateways[$id]['name'], $group['name']);
				}
			}
		}
	}

	foreach (config_get_path('staticroutes/route', []) as $route) {
		if ($route['gateway'] == $a_gateways[$id]['name']) {
			if (!$disable) {
				// The user wants to delete this gateway, but there is a static route (enabled or disabled) that refers to the gateway.
				$input_errors[] = sprintf(gettext('Gateway "%1$s" cannot be deleted because it is in use on Static Route "%2$s"'), $a_gateways[$id]['name'], $route['network']);
			} else if (!isset($route['disabled'])) {
				// The user wants to disable this gateway.
				// But there is a static route that uses this gateway and is enabled (not disabled).
				$input_errors[] = sprintf(gettext('Gateway "%1$s" cannot be disabled because it is in use on Static Route "%2$s"'), $a_gateways[$id]['name'], $route['network']);
			}
		}
	}

	/* prevent removing a gateway if it's still in use by DNS servers
	 * see https://redmine.pfsense.org/issues/8390 */
	$dnsgw_counter = 1;
	config_init_path('system/dnsserver');
	foreach (config_get_path('system/dnsserver', []) as $dnsserver) {
		if (config_path_enabled("system", "dns{$dnsgw_counter}gw") &&
		    ($a_gateways[$id]['name'] == config_get_path("system/dns{$dnsgw_counter}gw"))) {
				if (!$disable) {
					// The user wants to delete this gateway, but there is a static route to the DNS server that refers to the gateway.
					$input_errors[] = sprintf(gettext('Gateway "%1$s" cannot be deleted because it is in use by DNS Server "%2$s"'), $a_gateways[$id]['name'], $dnsserver);
				} else {
					// The user wants to disable this gateway, but there is a static route to the DNS server that refers to the gateway.
					$input_errors[] = sprintf(gettext('Gateway "%1$s" cannot be disabled because it is in use by DNS Server "%2$s"'), $a_gateways[$id]['name'], $dnsserver);
				}
		}
		$dnsgw_counter++;
	}

	if (isset($input_errors)) {
		return false;
	}

	return true;
}

function delete_gateway_item($id) {
	global $a_gateways;

	if (!isset($a_gateways[$id])) {
		return;
	}

	/* If the removed gateway was the default route, remove the default route */
	if (!empty($a_gateways[$id]) && is_ipaddr($a_gateways[$id]['gateway']) &&
	    !isset($a_gateways[$id]['disabled']) &&
	    isset($a_gateways[$id]['isdefaultgw'])) {
		$inet = (!is_ipaddrv4($a_gateways[$id]['gateway'])
		    ? 'inet6' : 'inet');
		route_del('default', $inet);
	}

	/* NOTE: Cleanup static routes for the interface route if any */
	if (!empty($a_gateways[$id]) && is_ipaddr($a_gateways[$id]['gateway']) &&
	    isset($a_gateways[$id]["nonlocalgateway"])) {
		route_del($a_gateways[$id]['gateway']);
	}
	/* NOTE: Cleanup static routes for the monitor ip if any */
	if (!empty($a_gateways[$id]['monitor']) &&
	    $a_gateways[$id]['monitor'] != "dynamic" &&
	    is_ipaddr($a_gateways[$id]['monitor']) &&
	    $a_gateways[$id]['gateway'] != $a_gateways[$id]['monitor']) {
		route_del($a_gateways[$id]['monitor']);
	}

	if (config_get_path("interfaces/{$a_gateways[$id]['friendlyiface']}/gateway") == $a_gateways[$id]['name']) {
		config_del_path("interfaces/{$a_gateways[$id]['friendlyiface']}/gateway");
	}
	config_del_path("gateways/gateway_item/{$a_gateways[$id]['attribute']}");
}

unset($input_errors);
if ($_REQUEST['act'] == "del") {
	if (can_delete_disable_gateway_item($_REQUEST['id'])) {
		$realid = $a_gateways[$_REQUEST['id']]['attribute'];
		delete_gateway_item($_REQUEST['id']);
		write_config("Gateways: removed gateway {$realid}");
		mark_subsystem_dirty('staticroutes');
		header("Location: system_gateways.php");
		exit;
	}
}

if (isset($_REQUEST['del_x'])) {
	/* delete selected items */
	if (is_array($_REQUEST['rule']) && count($_REQUEST['rule'])) {
		foreach ($_REQUEST['rule'] as $rulei) {
			if (!can_delete_disable_gateway_item($rulei)) {
				break;
			}
		}

		if (!isset($input_errors)) {
			$items_deleted = "";
			foreach ($_REQUEST['rule'] as $rulei) {
				delete_gateway_item($rulei);
				$items_deleted .= "{$rulei} ";
			}
			if (!empty($items_deleted)) {
				write_config(sprintf(gettext("Gateways: removed gateways %s"), $items_deleted));
				mark_subsystem_dirty('staticroutes');
			}
			header("Location: system_gateways.php");
			exit;
		}
	}

} else if ($_REQUEST['act'] == "toggle" && $a_gateways[$_REQUEST['id']]) {
	$realid = $a_gateways[$_REQUEST['id']]['attribute'];
	$disable_gw = config_get_path("gateways/gateway_item/{$realid}/disabled") === null;
	if ($disable_gw) {
		// The user wants to disable the gateway, so check if that is OK.
		$ok_to_toggle = can_delete_disable_gateway_item($_REQUEST['id'], $disable_gw);
	} else {
		// The user wants to enable the gateway. That is always OK.
		$ok_to_toggle = true;
	}
	if ($ok_to_toggle) {
		gateway_set_enabled(config_get_path("gateways/gateway_item/{$realid}/name"), !$disable_gw);

		if (write_config("Gateways: enable/disable")) {
			mark_subsystem_dirty('staticroutes');
		}

		header("Location: system_gateways.php");
		exit;
	}
}

foreach($simplefields as $field) {
	$pconfig[$field] = config_get_path("gateways/{$field}");
}

$pgtitle = array(gettext("System"), gettext("Routing"), gettext("Gateways"));
$pglinks = array("", "@self", "@self");
$shortcut_section = "gateways";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($_POST['apply']) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('staticroutes')) {
	print_apply_box(gettext("The gateway configuration has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
}

$tab_array = array();
$tab_array[0] = array(gettext("Gateways"), true, "system_gateways.php");
$tab_array[1] = array(gettext("Static Routes"), false, "system_routes.php");
$tab_array[2] = array(gettext("Gateway Groups"), false, "system_gateway_groups.php");
display_top_tabs($tab_array);

?>
<form method="post">
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Gateways')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table id="gateways" class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr>
						<th></th>
						<th></th>
						<th><?=gettext("Name")?></th>
						<th><?=gettext("Default")?></th>
						<th><?=gettext("Interface")?></th>
						<th><?=gettext("Gateway")?></th>
						<th><?=gettext("Monitor IP")?></th>
						<th><?=gettext("Description")?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody>
<?php
foreach ($a_gateways as $i => $gateway):
	if (isset($gateway['inactive'])) {
		$title = gettext("Gateway inactive, interface is missing");
		$icon = 'fa-regular fa-circle-xmark';
	} elseif (isset($gateway['disabled'])) {
		$icon = 'fa-solid fa-ban';
		$title = gettext("Gateway disabled");
	} else {
		$icon = 'fa-regular fa-circle-check';
		$title = gettext("Gateway enabled");
	}

	$gtitle = "";
	if (isset($gateway['isdefaultgw'])) {
		$gtitle = gettext("Default gateway");
	}

	$id = $gateway['attribute'];
?>
					<tr<?=($icon != 'fa-regular fa-circle-check')? ' class="disabled"' : ''?> onClick="fr_toggle(<?=$id;?>)" id="fr<?=$id;?>">
						<td style="white-space: nowrap;">
							<?php 
							if (is_numeric($id)) :?>
								<input type='checkbox' id='frc<?=$id?>' onClick='fr_toggle(<?=$id?>)' name='row[]' value='<?=$id?>'/>
								<a class='fa-solid fa-anchor' id='Xmove_<?=$id?>' title='"<?=gettext("Move checked entries to here")?>"'></a>
							<?php endif; ?>
						</td>
						<td title="<?=$title?>"><i class="<?=$icon?>"></i></td>
						<td title="<?=$gtitle?>">
						<?=htmlspecialchars($gateway['name'])?>
<?php
							if (isset($gateway['isdefaultgw'])) {
								echo ' <i class="fa-solid fa-globe"></i>';
							}
?>
						</td>
						<td>
							<?=htmlspecialchars($gateway['tiername'])?>
						</td>
						<td>
							<?=htmlspecialchars($gateway['friendlyifdescr'])?>
						</td>
						<td>
							<?=htmlspecialchars($gateway['gateway'])?>
						</td>
						<td>
							<?=htmlspecialchars($gateway['monitor'])?>
						</td>
						<td>
							<?=htmlspecialchars($gateway['descr'])?>
						</td>
						<td style="white-space: nowrap;">
							<a href="system_gateways_edit.php?id=<?=$i?>" class="fa-solid fa-pencil" title="<?=gettext('Edit gateway');?>"></a>
							<a href="system_gateways_edit.php?dup=<?=$i?>" class="fa-regular fa-clone" title="<?=gettext('Copy gateway')?>"></a>

<?php if (is_numeric($gateway['attribute'])): ?>
	<?php if (isset($gateway['disabled'])) {
	?>
							<a href="?act=toggle&amp;id=<?=$i?>" class="fa-regular fa-square-check" title="<?=gettext('Enable gateway')?>" usepost></a>
	<?php } else {
	?>
							<a href="?act=toggle&amp;id=<?=$i?>" class="fa-solid fa-ban" title="<?=gettext('Disable gateway')?>" usepost></a>
	<?php }
	?>
							<a href="system_gateways.php?act=del&amp;id=<?=$i?>" class="fa-solid fa-trash-can" title="<?=gettext('Delete gateway')?>" usepost></a>

<?php endif; ?>
						</td>
					</tr>
<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<nav class="action-buttons">
	<button type="submit" id="order-store" name="order-store" class="btn btn-sm btn-primary" value="store changes" disabled title="<?=gettext('Save rule order')?>">
		<i class="fa-solid fa-save icon-embed-btn"></i>
		<?=gettext("Save")?>
	</button>
	<a href="system_gateways_edit.php" role="button" class="btn btn-success">
		<i class="fa-solid fa-plus icon-embed-btn"></i>
		<?=gettext("Add");?>
	</a>
</nav>
</form>
<?php

$form = new Form;
$section = new Form_Section('Default gateway');

$dflts = available_default_gateways();

$section->addInput(new Form_Select(
	'defaultgw4',
	'Default gateway IPv4',
	$pconfig['defaultgw4'],
	$dflts['v4']
))->setHelp('Select a gateway or failover gateway group to use as the default gateway.');

$section->addInput(new Form_Select(
	'defaultgw6',
	'Default gateway IPv6',
	$pconfig['defaultgw6'],
	$dflts['v6']
))->setHelp('Select a gateway or failover gateway group to use as the default gateway.');

$form->add($section);
print $form;

?>
<div class="infoblock">
<?php
print_info_box(
	sprintf(gettext('%1$s The current default route as present in the current routing table of the operating system'), '<strong><i class="fa-solid fa-globe"></i></strong>') .
	sprintf(gettext('%1$s Gateway is inactive, interface is missing'), '<br /><strong><i class="fa-regular fa-circle-xmark"></i></strong>') .
	sprintf(gettext('%1$s Gateway disabled'), '<br /><strong><i class="fa-solid fa-ban"></i></strong>') .
	sprintf(gettext('%1$s Gateway enabled'), '<br /><strong><i class="fa-regular fa-circle-check"></i></strong>')
	);
?>
</div>
<script type="text/javascript">
//<![CDATA[
events.push(function() {
	$('#order-store').click(function () {
		// Check all of the rule checkboxes so that their values are posted
	   $('[id^=frc]').prop('checked', true);
	});

	$('[id^=Xmove_]').click(function (event) {
		// anchor click to move gateways around..
		moveRowUpAboveAnchor(event.target.id.slice(6),"gateways");
		return false;
	});
	$('[id^=Xmove_]').css('cursor', 'pointer');
});
	function moveRowUpAboveAnchor(rowId, tableId) {
		var table = $('#'+tableId);
		var viewcheckboxes = $('[id^=frc]input:checked', table);
		var rowview = $("#fr" + rowId, table);
		var moveabove = rowview;
		//var parent = moveabove[0].parentNode;
		
		viewcheckboxes.each(function( index ) {
			var moveid = this.value;
			console.log( index + ": " + this.id );

			var prevrowview = $("#fr" + moveid, table);
			prevrowview.insertBefore(moveabove);
			$('#order-store').removeAttr('disabled');
		});
	}
//]]>
</script>

<?php include("foot.inc");
