<?php
/*
 * system_advanced_sysctl.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008 Shrew Soft Inc
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
##|*IDENT=page-system-advanced-sysctl
##|*NAME=System: Advanced: Tunables
##|*DESCR=Allow access to the 'System: Advanced: Tunables' page.
##|*MATCH=system_advanced_sysctl.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("system_advanced_sysctl.inc");

init_config_arr(array('sysctl', 'item'));
$a_tunable = &$config['sysctl']['item'];
$tunables = getTunables();

if (isset($_REQUEST['id'])) {
	$id = htmlspecialchars_decode($_REQUEST['id']);
}

if ($_POST['act'] == "del") {
	if (deleteTunable($id)) {
		exit;
	}
}

if ($_POST['apply']) {
	$retval = 0;
	system_setup_sysctl();
	clear_subsystem_dirty('sysctl');
}

if ($_POST['save'] == gettext("Save")) {
	$rv = saveTunable($_POST, $id);

	$input_errors = $rv['input_errors'];
	$pconfig = $rv['pconfig'];

	if (!$input_errors) {
		pfSenseHeader("system_advanced_sysctl.php");
		exit;
	}
}

$act = $_REQUEST['act'];

if ($act == "edit") {
	if (isset($a_tunable[$id])) {
		$pconfig['tunable'] = $a_tunable[$id]['tunable'];
		$pconfig['value'] = $a_tunable[$id]['value'];
		$pconfig['descr'] = $a_tunable[$id]['descr'];

	} else if (isset($tunables[$id])) {
		$pconfig['tunable'] = $tunables[$id]['tunable'];
		$pconfig['value'] = $tunables[$id]['value'];
		$pconfig['descr'] = $tunables[$id]['descr'];
	}
}

$pgtitle = array(gettext("System"), gettext("Advanced"), gettext("System Tunables"));
$pglinks = array("", "system_advanced_admin.php", "system_advanced_sysctl.php");

if ($act == "edit") {
	$pgtitle[] = gettext('Edit');
	$pglinks[] = "@self";
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($_POST['apply']) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('sysctl') && ($act != "edit" )) {
	print_apply_box(gettext("The firewall tunables have changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
}

$tab_array = array();
$tab_array[] = array(gettext("Admin Access"), false, "system_advanced_admin.php");
$tab_array[] = array(htmlspecialchars(gettext("Firewall & NAT")), false, "system_advanced_firewall.php");
$tab_array[] = array(gettext("Networking"), false, "system_advanced_network.php");
$tab_array[] = array(gettext("Miscellaneous"), false, "system_advanced_misc.php");
$tab_array[] = array(gettext("System Tunables"), true, "system_advanced_sysctl.php");
$tab_array[] = array(gettext("Notifications"), false, "system_advanced_notifications.php");
display_top_tabs($tab_array);

if ($act != "edit"): ?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext('System Tunables'); ?></h2>
	</div>
	<div class="panel-body">
		<div class="form-group">
			<table class="table table-responsive table-hover table-condensed">
				<caption><strong><?=gettext('NOTE: '); ?></strong><?=gettext('The options on this page are intended for use by advanced users only.'); ?></caption>
				<thead>
					<tr>
						<th class="col-sm-3"><?=gettext("Tunable Name"); ?></th>
						<th><?=gettext("Description"); ?></th>
						<th class="col-sm-1"><?=gettext("Value"); ?></th>
						<th><a class="btn btn-xs btn-success" href="system_advanced_sysctl.php?act=edit"><i class="fa fa-plus icon-embed-btn"></i><?=gettext('New'); ?></a></th>
					</tr>
				</thead>
				<?php
					foreach ($tunables as $i => $tunable):
						if (!isset($tunable['modified'])) {
							$i = $tunable['tunable'];
						}
				?>
				<tr>
					<td><?=$tunable['tunable']; ?></td>
					<td><?=$tunable['descr']; ?></td>
					<td><?=$tunable['value']; ?>
					<?php
						if ($tunable['value'] == "default") {
							echo "(" . get_default_sysctl_value($tunable['tunable']) . ")";
						}
					?>
					</td>
					<td>
					<a class="fa fa-pencil" title="<?=gettext("Edit tunable"); ?>" href="system_advanced_sysctl.php?act=edit&amp;id=<?=$i;?>"></a>
						<?php if (isset($tunable['modified'])): ?>
						<a class="fa fa-trash" title="<?=gettext("Delete/Reset tunable")?>" href="system_advanced_sysctl.php?act=del&amp;id=<?=$i;?>" usepost></a>
						<?php endif; ?>
					</td>
				</tr>
				<?php
					endforeach;
					unset($tunables);
				?>
			</table>
		</div>
	</div>
</div>

<?php else:
	$form = new Form;
	$section = new Form_Section('Edit Tunable');

	$section->addInput(new Form_Input(
		'tunable',
		'*Tunable',
		'text',
		$pconfig['tunable']
	))->setWidth(4);

	$section->addInput(new Form_Input(
		'value',
		'*Value',
		'text',
		$pconfig['value']
	))->setWidth(4);

	$section->addInput(new Form_Input(
		'descr',
		'Description',
		'text',
		$pconfig['descr']
	))->setWidth(4);

	if (isset($id) && $a_tunable[$id]) {
		$form->addGlobal(new Form_Input(
			'id',
			'id',
			'hidden',
			$id
		));
	}

	$form->add($section);

	print $form;

endif;

include("foot.inc");
