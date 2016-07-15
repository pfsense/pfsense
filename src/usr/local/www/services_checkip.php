<?php
/*
 * services_checkip.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
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
##|*IDENT=page-services-checkipservices
##|*NAME=Services: Check IP Service
##|*DESCR=Allow access to the 'Services: Check IP Service' page.
##|*MATCH=services_checkip.php*
##|-PRIV

require_once("guiconfig.inc");

if (!is_array($config['checkipservices']['checkipservice'])) {
	$config['checkipservices']['checkipservice'] = array();
}

$a_checkipservice = &$config['checkipservices']['checkipservice'];

$dirty = false;
if ($_GET['act'] == "del") {
	unset($a_checkipservice[$_GET['id']]);
	$dirty = true;
} else if ($_GET['act'] == "toggle") {
	if ($a_checkipservice[$_GET['id']]) {
		if (isset($a_checkipservice[$_GET['id']]['enable'])) {
			unset($a_checkipservice[$_GET['id']]['enable']);
		} else {
			$a_checkipservice[$_GET['id']]['enable'] = true;
		}
		$dirty = true;
	} else if ($_GET['id'] == count($a_checkipservice)) {
		if (isset($config['checkipservices']['disable_factory_default'])) {
			unset($config['checkipservices']['disable_factory_default']);
		} else {
			$config['checkipservices']['disable_factory_default'] = true;
		}
		$dirty = true;
	}
}
if ($dirty) {
	write_config();

	header("Location: services_checkip.php");
	exit;
}

$pgtitle = array(gettext("Services"), gettext("Dynamic DNS"), gettext("Check IP Services"));
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Dynamic DNS Clients"), false, "services_dyndns.php");
$tab_array[] = array(gettext("RFC 2136 Clients"), false, "services_rfc2136.php");
$tab_array[] = array(gettext("Check IP Services"), true, "services_checkip.php");
display_top_tabs($tab_array);

if ($input_errors) {
	print_input_errors($input_errors);
}
?>

<form action="services_checkip.php" method="post" name="iform" id="iform">
	<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Check IP Services')?></h2></div>
		<div class="panel-body">
			<div class="table-responsive">
				<table class="table table-striped table-hover table-condensed">
					<thead>
						<tr>
							<th><?=gettext("Name")?></th>
							<th><?=gettext("URL")?></th>
							<th><?=gettext("Verify SSL Peer")?></th>
							<th><?=gettext("Description")?></th>
							<th><?=gettext("Actions")?></th>
						</tr>
					</thead>
					<tbody>
<?php
// Is the factory default check IP service disabled?
if (isset($config['checkipservices']['disable_factory_default'])) {
	unset($factory_default_checkipservice['enable']);
}

// Append the factory default check IP service to the list.
$a_checkipservice[] = $factory_default_checkipservice;
$factory_default = count($a_checkipservice) - 1;

$i = 0;
foreach ($a_checkipservice as $checkipservice):

	// Hide edit and delete controls on the factory default check IP service entry (last one; id = count-1), and retain layout positioning.
	if ($i == $factory_default) {
		$visibility = 'invisible';
	} else {
		$visibility = 'visible';
	}
?>
						<tr<?=(isset($checkipservice['enable']) ? '' : ' class="disabled"')?>>
						<td>
							<?=htmlspecialchars($checkipservice['name'])?>
						</td>
						<td>
							<?=htmlspecialchars($checkipservice['url'])?>
						</td>
						<td class="text-center">
							<i<?=(isset($checkipservice['verifysslpeer'])) ? ' class="fa fa-check"' : '';?>></i>
						</td>
						<td>
							<?=htmlspecialchars($checkipservice['descr'])?>
						</td>
						<td>
							<a class="fa fa-pencil <?=$visibility?>" title="<?=gettext('Edit service')?>" href="services_checkip_edit.php?id=<?=$i?>"></a>
						<?php if (isset($checkipservice['enable'])) {
						?>
							<a	class="fa fa-ban" title="<?=gettext('Disable service')?>" href="?act=toggle&amp;id=<?=$i?>"></a>
						<?php } else {
						?>
							<a class="fa fa-check-square-o" title="<?=gettext('Enable service')?>" href="?act=toggle&amp;id=<?=$i?>"></a>
						<?php }
						?>
							<a class="fa fa-trash <?=$visibility?>" title="<?=gettext('Delete service')?>" href="services_checkip.php?act=del&amp;id=<?=$i?>"></a>
						</td>
					</tr>
<?php
	$i++;
endforeach; ?>

					</tbody>
				</table>
			</div>
		</div>
	</div>
</form>

<nav class="action-buttons">
	<a href="services_checkip_edit.php" class="btn btn-sm btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext('Add')?>
	</a>
</nav>

<?php
print_info_box(gettext('The first (highest in list) enabled check ip service will be used to ' . 
						'check IP addresses for Dynamic DNS services, and ' .
						'RFC 2136 entries that have the "Use public IP" option enabled.'));

include("foot.inc");
