<?php
/*
 * interfaces_vlan.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-interfaces-vlan
##|*NAME=Interfaces: VLAN
##|*DESCR=Allow access to the 'Interfaces: VLAN' page.
##|*MATCH=interfaces_vlan.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("interfaces_fast.inc");

global $profile;

init_config_arr(array('vlans', 'vlan'));
$a_vlans = &$config['vlans']['vlan'];

if ($_POST['act'] == "del") {
	if (!isset($_POST['id'])) {
		$input_errors[] = gettext("Wrong parameters supplied");
	} else if (empty($a_vlans[$_POST['id']])) {
		$input_errors[] = gettext("Wrong index supplied");
	/* check if still in use */
	} else if (vlan_inuse($a_vlans[$_POST['id']])) {
		$input_errors[] = gettext("This VLAN cannot be deleted because it is still being used as an interface.");
	} else {
		if (does_interface_exist($a_vlans[$_POST['id']]['vlanif'])) {
			pfSense_interface_destroy($a_vlans[$_POST['id']]['vlanif']);
		}
		unset($a_vlans[$_POST['id']]);

		write_config("VLAN interface deleted");

		header("Location: interfaces_vlan.php");
		exit;
	}
}


$pgtitle = array(gettext("Interfaces"), gettext("VLANs"));
$shortcut_section = "interfaces";
include('head.inc');

if ($input_errors) print_input_errors($input_errors);

$tab_array = array();
$tab_array[] = array(gettext("Interface Assignments"), false, "interfaces_assign.php");
$tab_array[] = array(gettext("Interface Groups"), false, "interfaces_groups.php");
$tab_array[] = array(gettext("Wireless"), false, "interfaces_wireless.php");
$tab_array[] = array(gettext("VLANs"), true, "interfaces_vlan.php");
$tab_array[] = array(gettext("QinQs"), false, "interfaces_qinq.php");
$tab_array[] = array(gettext("PPPs"), false, "interfaces_ppps.php");
$tab_array[] = array(gettext("GREs"), false, "interfaces_gre.php");
$tab_array[] = array(gettext("GIFs"), false, "interfaces_gif.php");
$tab_array[] = array(gettext("Bridges"), false, "interfaces_bridge.php");
$tab_array[] = array(gettext("LAGGs"), false, "interfaces_lagg.php");
display_top_tabs($tab_array);

?>
<form action="interfaces_vlan.php" method="post">
	<input id="act" type="hidden" name="act" value="" />
	<input id="id" type="hidden" name="id" value=""/>

	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('VLAN Interfaces')?></h2></div>
		<div class="panel-body">
			<div class="table-responsive">
				<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap table-rowdblclickedit" data-sortable>
					<thead>
						<tr>
							<th><?=gettext('Interface');?></th>
							<th><?=gettext('VLAN tag');?></th>
							<th><?=gettext('Priority');?></th>
							<th><?=gettext('Description');?></th>
							<th><?=gettext('Actions');?></th>
						</tr>
					</thead>
					<tbody>
<?php
	$i = 0;
	$gettext_array = array('edit'=>gettext('Edit VLAN'),'del'=>gettext('Delete VLAN'));
	$ifaces = convert_real_interface_to_friendly_interface_name_fast(array());
	foreach ($a_vlans as $vlan) {
?>
						<tr>
							<td>
<?php
	printf("%s", htmlspecialchars($vlan['if']));
	if (isset($ifaces[$vlan['if']]) && strlen($ifaces[$vlan['if']]) > 0)
		printf(" (%s)", htmlspecialchars($ifaces[$vlan['if']]));
?>
							</td>
							<td><?=htmlspecialchars($vlan['tag']);?></td>
							<td><?=htmlspecialchars($vlan['pcp']);?></td>
							<td><?=htmlspecialchars($vlan['descr']);?></td>
							<td>
								<a class="fa fa-pencil"	title="<?=$gettext_array['edit']?>"	role="button" href="interfaces_vlan_edit.php?id=<?=$i?>" ></a>
								<a class="fa fa-trash no-confirm"	title="<?=$gettext_array['del']?>"	role="button" id="del-<?=$i?>"></a>
							</td>
						</tr>
<?php
			$i++;
	}
?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<nav class="action-buttons">
		<a class="btn btn-success btn-sm" role="button" href="interfaces_vlan_edit.php">
			<i class="fa fa-plus icon-embed-btn"></i>
			<?=gettext('Add'); ?>
		</a>
	</nav>

</form>

<div class="infoblock">
	<?php print_info_box(sprintf(gettext('Not all drivers/NICs support 802.1Q '.
		'VLAN tagging properly. %1$sOn cards that do not explicitly support it, VLAN '.
		'tagging will still work, but the reduced MTU may cause problems.%1$sSee the '.
		'%2$s handbook for information on supported cards.'), '<br />', $g['product_label']), 'info', false); ?>
</div>

<?php
	$delmsg = gettext("Are you sure you want to delete this VLAN?");
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// Select 'delete button' clicks, extract the id, set the hidden input values and submit
	$('[id^=del-]').click(function(event) {
		if (confirm("<?=$delmsg?>")) {
			$('#act').val('del');
			$('#id').val(this.id.replace("del-", ""));
			$(this).parents('form').submit();
		}
	});

});
//]]>
</script>
<?php
include("foot.inc");
