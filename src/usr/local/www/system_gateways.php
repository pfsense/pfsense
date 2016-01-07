<?php
/*
	system_gateways.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2010 Seth Mos <seth.mos@dds.nl>
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

##|+PRIV
##|*IDENT=page-system-gateways
##|*NAME=System: Gateways
##|*DESCR=Allow access to the 'System: Gateways' page.
##|*MATCH=system_gateways.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

$a_gateways = return_gateways_array(true, false, true);
$a_gateways_arr = array();
foreach ($a_gateways as $gw) {
	$a_gateways_arr[] = $gw;
}
$a_gateways = $a_gateways_arr;

if (!is_array($config['gateways']['gateway_item'])) {
	$config['gateways']['gateway_item'] = array();
}

$a_gateway_item = &$config['gateways']['gateway_item'];

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {

		$retval = 0;

		$retval = system_routing_configure();
		$retval |= filter_configure();
		/* reconfigure our gateway monitor */
		setup_gateways_monitor();
		/* Dynamic DNS on gw groups may have changed */
		send_event("service reload dyndnsall");

		$savemsg = get_std_save_message($retval);
		if ($retval == 0) {
			clear_subsystem_dirty('staticroutes');
		}
	}
}

function can_delete_disable_gateway_item($id, $disable = false) {
	global $config, $input_errors, $a_gateways;

	if (!isset($a_gateways[$id])) {
		return false;
	}

	if (is_array($config['gateways']['gateway_group'])) {
		foreach ($config['gateways']['gateway_group'] as $group) {
			foreach ($group['item'] as $item) {
				$items = explode("|", $item);
				if ($items[0] == $a_gateways[$id]['name']) {
					if (!$disable) {
						$input_errors[] = sprintf(gettext("Gateway '%s' cannot be deleted because it is in use on Gateway Group '%s'"), $a_gateways[$id]['name'], $group['name']);
					} else {
						$input_errors[] = sprintf(gettext("Gateway '%s' cannot be disabled because it is in use on Gateway Group '%s'"), $a_gateways[$id]['name'], $group['name']);
					}
				}
			}
		}
	}

	if (is_array($config['staticroutes']['route'])) {
		foreach ($config['staticroutes']['route'] as $route) {
			if ($route['gateway'] == $a_gateways[$id]['name']) {
				if (!$disable) {
					// The user wants to delete this gateway, but there is a static route (enabled or disabled) that refers to the gateway.
					$input_errors[] = sprintf(gettext("Gateway '%s' cannot be deleted because it is in use on Static Route '%s'"), $a_gateways[$id]['name'], $route['network']);
				} else if (!isset($route['disabled'])) {
					// The user wants to disable this gateway.
					// But there is a static route that uses this gateway and is enabled (not disabled).
					$input_errors[] = sprintf(gettext("Gateway '%s' cannot be disabled because it is in use on Static Route '%s'"), $a_gateways[$id]['name'], $route['network']);
				}
			}
		}
	}

	if (isset($input_errors)) {
		return false;
	}

	return true;
}

function delete_gateway_item($id) {
	global $config, $a_gateways;

	if (!isset($a_gateways[$id])) {
		return;
	}

	/* NOTE: Cleanup static routes for the interface route if any */
	if (!empty($a_gateways[$id]) && is_ipaddr($a_gateways[$id]['gateway']) &&
	    $gateway['gateway'] != $a_gateways[$id]['gateway'] &&
	    isset($a_gateways[$id]["nonlocalgateway"])) {
		$realif = get_real_interface($a_gateways[$id]['interface']);
		$inet = (!is_ipaddrv4($a_gateways[$id]['gateway']) ? "-inet6" : "-inet");
		$cmd = "/sbin/route delete $inet " . escapeshellarg($a_gateways[$id]['gateway']) . " -iface " . escapeshellarg($realif);
		mwexec($cmd);
	}
	/* NOTE: Cleanup static routes for the monitor ip if any */
	if (!empty($a_gateways[$id]['monitor']) &&
	    $a_gateways[$id]['monitor'] != "dynamic" &&
	    is_ipaddr($a_gateways[$id]['monitor']) &&
	    $a_gateways[$id]['gateway'] != $a_gateways[$id]['monitor']) {
		if (is_ipaddrv4($a_gateways[$id]['monitor'])) {
			mwexec("/sbin/route delete " . escapeshellarg($a_gateways[$id]['monitor']));
		} else {
			mwexec("/sbin/route delete -inet6 " . escapeshellarg($a_gateways[$id]['monitor']));
		}
	}

	if ($config['interfaces'][$a_gateways[$id]['friendlyiface']]['gateway'] == $a_gateways[$id]['name']) {
		unset($config['interfaces'][$a_gateways[$id]['friendlyiface']]['gateway']);
	}
	unset($config['gateways']['gateway_item'][$a_gateways[$id]['attribute']]);
}

unset($input_errors);
if ($_GET['act'] == "del") {
	if (can_delete_disable_gateway_item($_GET['id'])) {
		$realid = $a_gateways[$_GET['id']]['attribute'];
		delete_gateway_item($_GET['id']);
		write_config("Gateways: removed gateway {$realid}");
		mark_subsystem_dirty('staticroutes');
		header("Location: system_gateways.php");
		exit;
	}
}

if (isset($_POST['del_x'])) {
	/* delete selected items */
	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		foreach ($_POST['rule'] as $rulei) {
			if (!can_delete_disable_gateway_item($rulei)) {
				break;
			}
		}

		if (!isset($input_errors)) {
			$items_deleted = "";
			foreach ($_POST['rule'] as $rulei) {
				delete_gateway_item($rulei);
				$items_deleted .= "{$rulei} ";
			}
			if (!empty($items_deleted)) {
				write_config("Gateways: removed gateways {$items_deleted}");
				mark_subsystem_dirty('staticroutes');
			}
			header("Location: system_gateways.php");
			exit;
		}
	}

} else if ($_GET['act'] == "toggle" && $a_gateways[$_GET['id']]) {
	$realid = $a_gateways[$_GET['id']]['attribute'];
	$disable_gw = !isset($a_gateway_item[$realid]['disabled']);
	if ($disable_gw) {
		// The user wants to disable the gateway, so check if that is OK.
		$ok_to_toggle = can_delete_disable_gateway_item($_GET['id'], $disable_gw);
	} else {
		// The user wants to enable the gateway. That is always OK.
		$ok_to_toggle = true;
	}
	if ($ok_to_toggle) {
		if ($disable_gw) {
			$a_gateway_item[$realid]['disabled'] = true;
		} else {
			unset($a_gateway_item[$realid]['disabled']);
		}

		if (write_config("Gateways: enable/disable")) {
			mark_subsystem_dirty('staticroutes');
		}

		header("Location: system_gateways.php");
		exit;
	}
}

$pgtitle = array(gettext("System"), gettext("Routing"), gettext("Gateways"));
$shortcut_section = "gateways";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (is_subsystem_dirty('staticroutes')) {
	print_info_box_np(gettext("The gateway configuration has been changed.") . "<br />" . gettext("You must apply the changes in order for them to take effect."));
}

$tab_array = array();
$tab_array[0] = array(gettext("Gateways"), true, "system_gateways.php");
$tab_array[1] = array(gettext("Static Routes"), false, "system_routes.php");
$tab_array[2] = array(gettext("Gateway Groups"), false, "system_gateway_groups.php");
display_top_tabs($tab_array);

?>
<table class="table">
<thead>
	<tr>
		<th></th>
		<th><?=gettext("Name")?></th>
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
		$icon = 'fa-times-circle-o';
	} elseif (isset($gateway['disabled'])) {
		$icon = 'fa-ban';
	} else {
		$icon = 'fa-check-circle-o';
	}

	if (isset($gateway['inactive'])) {
		$title = gettext("This gateway is inactive because interface is missing");
	} else {
		$title = '';
	}
?>
	<tr<?=($icon != 'fa-check-circle-o')? ' class="disabled"' : ''?>>
		<td title="<?=$title?>"><i class="fa <?=$icon?>"></i></td>
		<td>
			<?=htmlspecialchars($gateway['name'])?>
<?php
			if (isset($gateway['defaultgw'])) {
				echo " <strong>(default)</strong>";
			}
?>
		</td>
		<td>
			<?=htmlspecialchars(convert_friendly_interface_to_friendly_descr($gateway['friendlyiface']))?>
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
		<td>
			<a href="system_gateways_edit.php?id=<?=$i?>" class="fa fa-pencil" title="<?=gettext('Edit');?>"></a>
			<a href="system_gateways_edit.php?dup=<?=$i?>" class="fa fa-clone" title="<?=gettext('Copy')?>"></a>

<?php if (is_numeric($gateway['attribute'])): ?>
	<?php if (isset($gateway['disabled'])) {
	?>
			<a href="?act=toggle&amp;id=<?=$i?>" class="fa fa-check-square-o" title="<?=gettext('Enable')?>"></a>
	<?php } else {
	?>
			<a href="?act=toggle&amp;id=<?=$i?>" class="fa fa-ban" title="<?=gettext('Disable')?>"></a>
	<?php }
	?>
			<a href="system_gateways.php?act=del&amp;id=<?=$i?>" class="fa fa-trash" title="<?=gettext('Delete')?>"></a>

<?php endif; ?>
		</td>
	</tr>
<?php endforeach; ?>
</tbody>
</table>

<nav class="action-buttons">
	<a href="system_gateways_edit.php" role="button" class="btn btn-success">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add");?>
	</a>
</nav>
<?php

include("foot.inc");
