<?php
/*
 * services_pppoe.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-services-pppoeserver
##|*NAME=Services: PPPoE Server
##|*DESCR=Allow access to the 'Services: PPPoE Server' page.
##|*MATCH=services_pppoe.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("filter.inc");
require_once("vpn.inc");

init_config_arr(array('pppoes', 'pppoe'));
$a_pppoes = &$config['pppoes']['pppoe'];


if ($_POST['apply']) {
	if (file_exists("{$g['tmp_path']}/.vpn_pppoe.apply")) {
		$toapplylist = unserialize(file_get_contents("{$g['tmp_path']}/.vpn_pppoe.apply"));
		foreach ($toapplylist as $pppoeid) {
			if (!is_numeric($pppoeid)) {
				continue;
			}
			if (is_array($config['pppoes']['pppoe'])) {
				foreach ($config['pppoes']['pppoe'] as $pppoe) {
					if ($pppoe['pppoeid'] == $pppoeid) {
						vpn_pppoe_configure($pppoe);
						break;
					}
				}
			}
		}
		@unlink("{$g['tmp_path']}/.vpn_pppoe.apply");
	}
	$retval = 0;
	$retval |= filter_configure();
	clear_subsystem_dirty('vpnpppoe');
}

if ($_POST['act'] == "del") {
	if ($a_pppoes[$_POST['id']]) {
		if ("{$g['varrun_path']}/pppoe" . $a_pppoes[$_POST['id']]['pppoeid'] . "-vpn.pid") {
			killbypid("{$g['varrun_path']}/pppoe" . $a_pppoes[$_POST['id']]['pppoeid'] . "-vpn.pid");
		}
		if (is_dir("{$g['varetc_path']}/pppoe" . $a_pppoes[$_POST['id']]['pppoeid'])) {
			mwexec("/bin/rm -r {$g['varetc_path']}/pppoe" . $a_pppoes[$_POST['id']]['pppoeid']);
		}
		unset($a_pppoes[$_POST['id']]);
		write_config("PPPoE Server deleted");
		header("Location: services_pppoe.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("PPPoE Server"));
$shortcut_section = "pppoes";
include("head.inc");

if ($_POST['apply']) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('vpnpppoe')) {
	print_apply_box(gettext('The PPPoE entry list has been changed.') . '<br />' . gettext('The changes must be applied for them to take effect.'));
}
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('PPPoE Server')?></h2></div>
	<div class="panel-body">

	<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed table-rowdblclickedit">
		<thead>
			<tr>
				<th><?=gettext("Interface")?></th>
				<th><?=gettext("Local IP")?></th>
				<th><?=gettext("Number of users")?></th>
				<th><?=gettext("Description")?></th>
				<th><?=gettext("Actions")?></th>
			</tr>
		</thead>
		<tbody>
<?php
$i = 0;
foreach ($a_pppoes as $pppoe):
?>
			<tr>
				<td>
					<?=htmlspecialchars(convert_friendly_interface_to_friendly_descr($pppoe['interface']))?>
				</td>
				<td>
					<?=htmlspecialchars($pppoe['localip'])?>
				</td>
				<td>
					<?=htmlspecialchars($pppoe['n_pppoe_units'])?>
				</td>
				<td>
					<?=htmlspecialchars($pppoe['descr'])?>
				</td>
				<td>
					<a class="fa fa-pencil"	title="<?=gettext('Edit PPPoE instance')?>"	href="services_pppoe_edit.php?id=<?=$i?>"></a>
					<a class="fa fa-trash" title="<?=gettext('Delete PPPoE instance')?>" href="services_pppoe.php?act=del&amp;id=<?=$i?>" usepost></a>
				</td>
			</tr>
<?php
	$i++;
endforeach;
?>
		</tbody>
	</table>
</div>

	</div>
</div>

<nav class="action-buttons">
	<a href="services_pppoe_edit.php" class="btn btn-success">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>

<?php
include("foot.inc");
