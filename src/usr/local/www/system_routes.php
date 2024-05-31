<?php
/*
 * system_routes.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-system-staticroutes
##|*NAME=System: Static Routes
##|*DESCR=Allow access to the 'System: Static Routes' page.
##|*MATCH=system_routes.php*
##|-PRIV

require_once('guiconfig.inc');
require_once('functions.inc');
require_once('filter.inc');
require_once('shaper.inc');

config_init_path('staticroutes/route');
$a_gateways = get_gateways(GW_CACHE_ALL);
$changedesc_prefix = gettext('Static Routes') . ": ";

if ($_POST['apply']) {
	$pconfig = $_POST;
	$retval = 0;
	
	$routes_apply_file = g_get('tmp_path') . '/.system_routes.apply';
	if (file_exists($routes_apply_file)) {
		$toapplylist = unserialize_data(file_get_contents($routes_apply_file), []);
		foreach ($toapplylist as $toapply) {
			mwexec($toapply);
		}

		unlink($routes_apply_file);
	}

	$retval |= system_routing_configure();
	$retval |= filter_configure();
	/* reconfigure our gateway monitor */
	setup_gateways_monitor();

	if ($retval == 0) {
		clear_subsystem_dirty('staticroutes');
	}
}

if ($_POST['act'] === 'del') {
	if (config_get_path("staticroutes/route/{$_POST['id']}")) {
		$changedesc = $changedesc_prefix . sprintf(gettext('removed route to %s'), config_get_path("staticroutes/route/{$_POST['id']}/network"));
		delete_static_route($_POST['id'], true);
		config_del_path("staticroutes/route/{$_POST['id']}");
		write_config($changedesc);
		mark_subsystem_dirty('staticroutes');
		header('Location: system_routes.php');
		exit;
	}
}

if (isset($_POST['del_x'])) {
	/* delete selected routes */
	if (is_array($_POST['route']) && count($_POST['route'])) {
		$deleted_routes = '';
		foreach ($_POST['route'] as $routei) {
			$deleted_routes .= ' ' . config_get_path("staticroutes/route/{$routei}/network");
			delete_static_route($routei, true);
			config_del_path("staticroutes/route/{$routei}");
		}
		$changedesc = $changedesc_prefix . sprintf(gettext('removed route to %s'), $deleted_routes);
		write_config($changedesc);
		mark_subsystem_dirty('staticroutes');
		header('Location: system_routes.php');
		exit;
	}
}

if ($_POST['act'] === 'toggle') {
	$this_route_config = config_get_path("staticroutes/route/{$_POST['id']}");
	if ($this_route_config) {
		$do_update_config = true;
		if (isset($this_route_config['disabled'])) {
			// Do not enable a route whose gateway is disabled
			if (isset($a_gateways[$this_route_config['gateway']]['disabled'])) {
				$do_update_config = false;
				$input_errors[] = $changedesc_prefix . sprintf(gettext('gateway is disabled, cannot enable route to %s'), $this_route_config['network']);
			} else {
				config_del_path("staticroutes/route/{$_POST['id']}/disabled");
				$changedesc = $changedesc_prefix . sprintf(gettext('enabled route to %s'), $this_route_config['network']);
			}
		} else {
			delete_static_route($_POST['id']);
			config_set_path("staticroutes/route/{$_POST['id']}/disabled", true);
			$changedesc = $changedesc_prefix . sprintf(gettext('disabled route to %s'), $this_route_config['network']);
		}

		if ($do_update_config) {
			if (write_config($changedesc)) {
				mark_subsystem_dirty('staticroutes');
			}
			header('Location: system_routes.php');
			exit;
		}
	}
}

if($_POST['save']) {
	/* yuck - IE won't send value attributes for image buttons, while Mozilla does - so we use .x/.y to find move button clicks instead... */
	unset($movebtn);
	foreach ($_POST as $pn => $pd) {
		if (preg_match("/move_(\d+)_x/", $pn, $matches)) {
			$movebtn = $matches[1];
			break;
		}
	}
	/* move selected routes before this route */
	if (isset($movebtn) && is_array($_POST['route']) && count($_POST['route'])) {
		$a_routes = config_get_path('staticroutes/route');
		$a_routes_new = array();

		/* copy all routes < $movebtn and not selected */
		for ($i = 0; $i < $movebtn; $i++) {
			if (!in_array($i, $_POST['route'])) {
				$a_routes_new[] = $a_routes[$i];
			}
		}

		/* copy all selected routes */
		for ($i = 0; $i < count($a_routes); $i++) {
			if ($i == $movebtn) {
				continue;
			}
			if (in_array($i, $_POST['route'])) {
				$a_routes_new[] = $a_routes[$i];
			}
		}

		/* copy $movebtn route */
		if ($movebtn < count($a_routes)) {
			$a_routes_new[] = $a_routes[$movebtn];
		}

		/* copy all routes > $movebtn and not selected */
		for ($i = $movebtn+1; $i < count($a_routes); $i++) {
			if (!in_array($i, $_POST['route'])) {
				$a_routes_new[] = $a_routes[$i];
			}
		}
		if (count($a_routes_new) > 0) {
			config_set_path('staticroutes/route', $a_routes_new);
		}

		if (write_config(gettext('Saved static routes configuration.'))) {
			mark_subsystem_dirty('staticroutes');
		}
		header('Location: system_routes.php');
		exit;
	}
}


$pgtitle = [gettext('System'), gettext('Routing'), gettext('Static Routes')];
$pglinks = ['', 'system_gateways.php', '@self'];
$shortcut_section = 'routing';

include('head.inc');

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($_POST['apply']) {
	print_apply_result_box($retval);
}
if (is_subsystem_dirty('staticroutes')) {
	print_apply_box(gettext('The static route configuration has been changed.') . '<br />' . gettext('The changes must be applied for them to take effect.'));
}

$tab_array = [];
$tab_array[0] = [gettext('Gateways'), false, 'system_gateways.php'];
$tab_array[1] = [gettext('Static Routes'), true, 'system_routes.php'];
$tab_array[2] = [gettext('Gateway Groups'), false, 'system_gateway_groups.php'];
display_top_tabs($tab_array);

?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Static Routes')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr>
						<th></th>
						<th><?=gettext('Network')?></th>
						<th><?=gettext('Gateway')?></th>
						<th><?=gettext('Interface')?></th>
						<th><?=gettext('Description')?></th>
						<th><?=gettext('Actions')?></th>
					</tr>
				</thead>
				<tbody>
<?php
foreach (config_get_path('staticroutes/route', []) as $i => $route):
	if (isset($a_gateways[$route['gateway']]['inactive'])) {
		$icon = 'fa-regular fa-circle-xmark';
		$title = gettext('Route inactive, gateway interface is missing');
	} elseif (isset($route['disabled'])) {
		$icon = 'fa-solid fa-ban';
		$title = gettext('Route disabled');
	} else {
		$icon = 'fa-regular fa-circle-check';
		$title = gettext('Route enabled');
	}
?>
				<tr<?=($icon != 'fa-regular fa-circle-check')? ' class="disabled"' : ''?>>
					<td title="<?=$title?>"><i class="<?=$icon?>"></i></td>
					<td>
						<?=strtolower($route['network'])?>
					</td>
					<td>
						<?=htmlentities($a_gateways[$route['gateway']]['name']) . " - " . htmlentities($a_gateways[$route['gateway']]['gateway'])?>
					</td>
					<td>
						<?=convert_friendly_interface_to_friendly_descr($a_gateways[$route['gateway']]['friendlyiface'])?>
					</td>
					<td>
						<?=htmlspecialchars($route['descr'])?>
					</td>
					<td>
						<a href="system_routes_edit.php?id=<?=$i?>" class="fa-solid fa-pencil" title="<?=gettext('Edit route')?>"></a>

						<a href="system_routes_edit.php?dup=<?=$i?>" class="fa-regular fa-clone" title="<?=gettext('Copy route')?>"></a>

				<?php if (isset($route['disabled'])) {
				?>
						<a href="?act=toggle&amp;id=<?=$i?>" class="fa-regular fa-square-check" title="<?=gettext('Enable route')?>" usepost></a>
				<?php } else {
				?>
						<a href="?act=toggle&amp;id=<?=$i?>" class="fa-solid fa-ban" title="<?=gettext('Disable route')?>" usepost></a>
				<?php }
				?>
						<a href="system_routes.php?act=del&amp;id=<?=$i?>" class="fa-solid fa-trash-can" title="<?=gettext('Delete route')?>" usepost></a>

					</td>
				</tr>
<?php endforeach; ?>
			</table>
		</div>
	</div>
</div>

<nav class="action-buttons">
	<a href="system_routes_edit.php" role="button" class="btn btn-success btn-sm">
		<i class="fa-solid fa-plus icon-embed-btn"></i>
		<?=gettext('Add')?>
	</a>
</nav>
<div class="infoblock">
<?php
print_info_box(
	sprintf(gettext('%1$s Route is inactive, gateway interface is missing'), '<br /><strong><i class="fa-regular fa-circle-xmark"></i></strong>') .
	sprintf(gettext('%1$s Route disabled'), '<br /><strong><i class="fa-solid fa-ban"></i></strong>') .
	sprintf(gettext('%1$s Route enabled'), '<br /><strong><i class="fa-regular fa-circle-check"></i></strong>')
	);
?>
</div>
<?php

include('foot.inc');
