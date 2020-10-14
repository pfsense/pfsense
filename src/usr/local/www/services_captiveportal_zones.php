<?php
/*
 * services_captiveportal_zones.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-services-captiveportal-zones
##|*NAME=Services: Captive Portal Zones
##|*DESCR=Allow access to the 'Services: Captive Portal Zones' page.
##|*MATCH=services_captiveportal_zones.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("captiveportal.inc");

global $cpzone;
global $cpzoneid;

init_config_arr(array('captiveportal'));
$a_cp = &$config['captiveportal'];

if ($_POST['act'] == "del" && !empty($_POST['zone'])) {
	$cpzone = strtolower(htmlspecialchars($_POST['zone']));
	if ($a_cp[$cpzone]) {
		$cpzoneid = $a_cp[$cpzone]['zoneid'];
		unset($a_cp[$cpzone]['enable']);
		captiveportal_configure_zone($a_cp[$cpzone]);
		unset($a_cp[$cpzone]);
		if (isset($config['voucher'][$cpzone])) {
			unset($config['voucher'][$cpzone]);
		}
		unlink_if_exists("/var/db/captiveportal{$cpzone}.db");
		unlink_if_exists("/var/db/captiveportal_usedmacs_{$cpzone}.db");
		unlink_if_exists("/var/db/voucher_{$cpzone}_*.db");
		write_config();
	}
	header("Location: services_captiveportal_zones.php");
	exit;
}

$pgtitle = array(gettext("Services"), gettext("Captive Portal"));
$shortcut_section = "captiveportal";
include("head.inc");

if (is_subsystem_dirty('captiveportal')) {
	print_apply_box(gettext("The Captive Portal entry list has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
}
?>
<form action="services_captiveportal_zones.php" method="post">
	<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Captive Portal Zones')?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover table-rowdblclickedit sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th><?=gettext('Zone')?></th>
						<th><?=gettext('Interfaces')?></th>
						<th><?=gettext('Number of users'); ?></th>
						<th><?=gettext('Description'); ?></th>
						<th data-sortable="false"><?=gettext('Actions'); ?></th>
					</tr>
				</thead>
				<tbody>

<?php
	foreach ($a_cp as $cpzone => $cpitem):
		if (!is_array($cpitem)) {
			continue;
		}
?>
					<tr>
						<td><?=htmlspecialchars($cpitem['zone']);?></td>
						<td>
<?php
		$cpifaces = explode(",", $cpitem['interface']);
		foreach ($cpifaces as $cpiface) {
			echo convert_friendly_interface_to_friendly_descr($cpiface) . " ";
		}
?>
						</td>
						<td><?=count(captiveportal_read_db());?></td>
						<td><?=htmlspecialchars($cpitem['descr']);?>&nbsp;</td>
						<td>
							<a class="fa fa-pencil" title="<?=gettext("Edit zone"); ?>" href="services_captiveportal.php?zone=<?=$cpzone?>"></a>
							<a class="fa fa-trash"  title="<?=gettext("Delete zone")?>" href="services_captiveportal_zones.php?act=del&amp;zone=<?=$cpzone;?>" usepost></a>
						</td>
					</tr>
<?php
	endforeach;
?>
				</tbody>
			</table>
		</div>
	</div>
</form>

<nav class="action-buttons">
	<a href="services_captiveportal_zones_edit.php" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext('Add')?>
	</a>
</nav>

<?php include("foot.inc"); ?>
