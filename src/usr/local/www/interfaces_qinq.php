<?php
/*
 * interfaces_qinq.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-interfaces-qinq
##|*NAME=Interfaces: QinQ
##|*DESCR=Allow access to the 'Interfaces: QinQ' page.
##|*MATCH=interfaces_qinq.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");

if ($_POST['act'] == "del") {
	$id = is_numericint($_POST['id']) ? $_POST['id'] : null;

	/*
	 * Check user privileges to test if the user is allowed to make changes.
	 * Otherwise users can end up in an inconsistent state where some changes are
	 * performed and others denied. See https://redmine.pfsense.org/issues/15318
	 */
	phpsession_begin();
	$guiuser = getUserEntry($_SESSION['Username']);
	$read_only = (is_array($guiuser) && userHasPrivilege($guiuser['item'], "user-config-readonly"));
	phpsession_end();

	if ($read_only) {
		$input_errors = array(gettext("Insufficient privileges to make the requested change (read only)."));
	}

	$this_qinq_config = config_get_path("qinqs/qinqentry/{$id}");
	/* check if still in use */
	if ((config_get_path('qinqs/qinqentry') !== null) && vlan_inuse($this_qinq_config)) {
		$input_errors[] = gettext("This QinQ cannot be deleted because it is still being used as an interface.");
	} elseif (empty($this_qinq_config['vlanif']) || !does_interface_exist($this_qinq_config['vlanif'])) {
		$input_errors[] = gettext("QinQ interface does not exist");
	} else {
		$delmembers = explode(" ", $this_qinq_config['members']);
		foreach ($delmembers as $tag) {
			if (qinq_inuse($this_qinq_config, $tag)) {
				$input_errors[] = gettext("This QinQ cannot be deleted because one of it tags is still being used as an interface.");
				break;
			}
		}
	}

	if (empty($input_errors)) {
		$delmembers = explode(" ", $this_qinq_config['members']);
		foreach ($delmembers as $tag) {
			exec("/sbin/ifconfig {$this_qinq_config['vlanif']}.{$tag} destroy");
		}
		pfSense_interface_destroy($this_qinq_config['vlanif']);
		config_del_path("qinqs/qinqentry/{$id}");

		write_config("QinQ interface deleted");

		header("Location: interfaces_qinq.php");
		exit;
	}
}

$pgtitle = array(gettext("Interfaces"), gettext("QinQs"));
$shortcut_section = "interfaces";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array(gettext("Interface Assignments"), false, "interfaces_assign.php");
$tab_array[] = array(gettext("Interface Groups"), false, "interfaces_groups.php");
$tab_array[] = array(gettext("Wireless"), false, "interfaces_wireless.php");
$tab_array[] = array(gettext("VLANs"), false, "interfaces_vlan.php");
$tab_array[] = array(gettext("QinQs"), true, "interfaces_qinq.php");
$tab_array[] = array(gettext("PPPs"), false, "interfaces_ppps.php");
$tab_array[] = array(gettext("GREs"), false, "interfaces_gre.php");
$tab_array[] = array(gettext("GIFs"), false, "interfaces_gif.php");
$tab_array[] = array(gettext("Bridges"), false, "interfaces_bridge.php");
$tab_array[] = array(gettext("LAGGs"), false, "interfaces_lagg.php");
display_top_tabs($tab_array);

?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('QinQ Interfaces')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr>
						<th><?=gettext("Interface"); ?></th>
						<th><?=gettext("Tag");?></th>
						<th><?=gettext("QinQ members"); ?></th>
						<th><?=gettext("Description"); ?></th>
						<th><?=gettext("Actions"); ?></th>
					</tr>
				</thead>
				<tbody>
<?php foreach (config_get_path('qinqs/qinqentry', []) as $i => $qinq):?>
					<tr>
						<td>
							<?=htmlspecialchars($qinq['if'])?>
						</td>
						<td>
							<?=htmlspecialchars($qinq['tag'])?>
						</td>
						<td>
<?php if (strlen($qinq['members']) > 20):?>
							<?=substr(htmlspecialchars($qinq['members']), 0, 20)?>&hellip;
<?php else:?>
							<?=htmlspecialchars($qinq['members'])?>
<?php endif; ?>
						</td>
						<td>
							<?=htmlspecialchars($qinq['descr'])?>&nbsp;
						</td>
						<td>
							<a class="fa-solid fa-pencil"	title="<?=gettext('Edit Q-in-Q interface')?>"	href="interfaces_qinq_edit.php?id=<?=$i?>"></a>
							<a class="fa-solid fa-trash-can"	title="<?=gettext('Delete Q-in-Q interface')?>"	href="interfaces_qinq.php?act=del&amp;id=<?=$i?>" usepost></a>
						</td>
					</tr>
<?php
endforeach;
?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<nav class="action-buttons">
	<a href="interfaces_qinq_edit.php" class="btn btn-success btn-sm">
		<i class="fa-solid fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>

<div class="infoblock">
	<?php print_info_box(sprintf(gettext('Not all drivers/NICs support 802.1Q QinQ tagging properly. %1$sOn cards that do not explicitly support it, ' .
		'QinQ tagging will still work, but the reduced MTU may cause problems.%1$s' .
		'See the %2$s handbook for information on supported cards.'), '<br />', g_get('product_label')), 'info', false); ?>
</div>

<?php
include("foot.inc");
