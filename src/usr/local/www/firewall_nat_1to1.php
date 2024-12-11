<?php
/*
 * firewall_nat_1to1.php
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
##|*IDENT=page-firewall-nat-1-1
##|*NAME=Firewall: NAT: 1:1
##|*DESCR=Allow access to the 'Firewall: NAT: 1:1' page.
##|*MATCH=firewall_nat_1to1.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("firewall_nat_1to1.inc");

$binat_exttype_flags = [SPECIALNET_IFADDR];
$binat_srctype_flags = [SPECIALNET_ANY, SPECIALNET_CLIENTS, SPECIALNET_IFADDR, SPECIALNET_IFSUB];
$binat_dsttype_flags = [SPECIALNET_ANY, SPECIALNET_CLIENTS, SPECIALNET_IFADDR, SPECIALNET_IFSUB, SPECIALNET_VIPS];

// Process $_POST/$_REQUEST =======================================================================
if ($_REQUEST['savemsg']) {
	$savemsg = $_REQUEST['savemsg'];
}

if (array_key_exists('order-store', $_REQUEST)) {
	reorder1to1NATrules($_POST);
} elseif ($_POST['apply']) {
	$retval = apply1to1NATrules();
} elseif (($_POST['act'] == "del")) {
	if (config_get_path("nat/onetoone/{$_POST['id']}")) {
		delete1to1NATrule($_POST);
	}
} elseif (isset($_POST['del_x'])) {
	/* delete selected rules */
	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		deleteMultiple1to1NATrules($_POST);
	}
} elseif (isset($_POST['toggle_x'])) {
	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		toggleMultiple1to1NATrules($_POST);
	}
} elseif (($_POST['act'] == "toggle")) {
	if (config_get_path("nat/onetoone/{$_POST['id']}")) {
		toggle1to1NATrule($_POST);
	}
}

// Construct/display the form =====================================================================
$pgtitle = array(gettext("Firewall"), gettext("NAT"), gettext("1:1"));
$pglinks = array("", "firewall_nat.php", "@self");
include("head.inc");

if ($_POST['apply']) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('natconf')) {
	print_apply_box(gettext('The NAT configuration has been changed.') . '<br />' .
	   gettext('The changes must be applied for them to take effect.'));
}

$tab_array = array();
$tab_array[] = array(gettext("Port Forward"), false, "firewall_nat.php");
$tab_array[] = array(gettext("1:1"), true, "firewall_nat_1to1.php");
$tab_array[] = array(gettext("Outbound"), false, "firewall_nat_out.php");
$tab_array[] = array(gettext("NPt"), false, "firewall_nat_npt.php");
display_top_tabs($tab_array);

global $user_settings;
$show_system_alias_popup = (array_key_exists('webgui', $user_settings) && !$user_settings['webgui']['disablealiaspopupdetail']);
$system_alias_specialnet = get_specialnet('', [SPECIALNET_IFNET, SPECIALNET_GROUP]);
?>
<form action="firewall_nat_1to1.php" method="post">
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext("NAT 1:1 Mappings")?></h2></div>
		<div id="mainarea" class="table-responsive panel-body">
			<table id="ruletable" class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th><input type="checkbox" id="selectAll" name="selectAll" /></th>
						<th><!-- icon --></th>
						<th><?=gettext("Interface"); ?></th>
						<th><?=gettext("External IP"); ?></th>
						<th><?=gettext("Internal IP"); ?></th>
						<th><?=gettext("Destination IP"); ?></th>
						<th><?=gettext("Description"); ?></th>
						<th><?=gettext('Actions')?></th>
					</tr>
				</thead>
				<tbody class="user-entries">
<?php
		$i = 0;
		foreach (config_get_path('nat/onetoone', []) as $natent):
			if (isset($natent['disabled'])) {
				$iconfn = "pass_d";
			} else {
				$iconfn = "pass";
			}

			$alias = rule_columns_with_alias(
				$natent['source']['address'],
				pprint_port($natent['source']['port']),
				$natent['destination']['address'],
				pprint_port($natrent['destination']['port']),
				$natent['external']
			);
?>
					<tr id="fr<?=$i;?>" onClick="fr_toggle(<?=$i;?>)" ondblclick="document.location='firewall_nat_1to1_edit.php?id=<?=$i;?>';" <?=(isset($natent['disabled']) ? ' class="disabled"' : '')?>>
						<td >
							<input type="checkbox" id="frc<?=$i;?>" onClick="fr_toggle(<?=$i;?>)" name="rule[]" value="<?=$i;?>"/>
						</td>

						<td>
							<a href="?act=toggle&amp;id=<?=$i?>" usepost>
								<i class="fa-solid <?= ($iconfn == "pass") ? "fa-check":"fa-times"?>" title="<?=gettext("click to toggle enabled/disabled status")?>"></i>
<?php 				if (isset($natent['nobinat'])) { ?>
								&nbsp;<i class="fa-regular fa-hand text-danger" title="<?=gettext("Negated: This rule excludes NAT from a later rule")?>"></i>
<?php 				} ?>
							</a>
						</td>
						<td>
<?php
					if (!$natent['interface']) {
						echo htmlspecialchars(convert_friendly_interface_to_friendly_descr("wan"));
					} else {
						echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface']));
					}
?>
						</td>
						<td>
							<?=htmlspecialchars(pprint_address(['network' => $natent['external']], $binat_exttype_flags))?>
						</td>
						<td>
							<?php if (isset($alias['src'])): ?>
								<a href="/firewall_aliases_edit.php?id=<?=$alias['src']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['src'])?>" data-html="true">
									<?=str_replace('_', '_<wbr>', htmlspecialchars(pprint_address($natent['source'], $binat_srctype_flags)))?>
								</a>
							<?php elseif ($show_system_alias_popup && array_key_exists($natent['source']['network'], $system_alias_specialnet)): ?>
								<a data-toggle="popover" data-trigger="hover focus" title="<?=gettext('System alias details')?>" data-content="<?=alias_info_popup(strtoupper($natent['source']['network']) . '__NETWORK', true)?>" data-html="true">
									<?=str_replace('_', '_<wbr>', htmlspecialchars(pprint_address($natent['source'], $binat_srctype_flags)))?>
								</a>
							<?php else: ?>
								<?=htmlspecialchars(pprint_address($natent['source'], $binat_srctype_flags))?>
							<?php endif; ?>
						</td>
						<td>
							<?php if (isset($alias['dst'])): ?>
								<a href="/firewall_aliases_edit.php?id=<?=$alias['dst']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['dst'])?>" data-html="true">
									<?=str_replace('_', '_<wbr>', htmlspecialchars(pprint_address($natent['destination'], $binat_dsttype_flags)))?>
								</a>
							<?php elseif ($show_system_alias_popup && array_key_exists($natent['destination']['network'], $system_alias_specialnet)): ?>
								<a data-toggle="popover" data-trigger="hover focus" title="<?=gettext('System alias details')?>" data-content="<?=alias_info_popup(strtoupper($natent['destination']['network']) . '__NETWORK', true)?>" data-html="true">
									<?=str_replace('_', '_<wbr>', htmlspecialchars(pprint_address($natent['destination'], $binat_dsttype_flags)))?>
								</a>
							<?php else: ?>
								<?=htmlspecialchars(pprint_address($natent['destination'], $binat_dsttype_flags))?>
							<?php endif; ?>
						</td>
						<td>
<?php
					echo htmlspecialchars($natent['descr']) . '&nbsp;';
?>
						</td>

						<td>
							<a class="fa-solid fa-pencil" title="<?=gettext("Edit mapping")?>" href="firewall_nat_1to1_edit.php?id=<?=$i?>"></a>
							<a class="fa-regular fa-clone" title="<?=gettext("Add a new mapping based on this one")?>" href="firewall_nat_1to1_edit.php?dup=<?=$i?>"></a>
							<a class="fa-solid fa-trash-can" title="<?=gettext("Delete mapping")?>" href="firewall_nat_1to1.php?act=del&amp;id=<?=$i?>" usepost></a>
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

	<nav class="action-buttons">
		<a href="firewall_nat_1to1_edit.php?after=-1" class="btn btn-sm btn-success" title="<?=gettext('Add mapping to the top of the list')?>">
			<i class="fa-solid fa-turn-up icon-embed-btn"></i>
			<?=gettext('Add')?>
		</a>
		<a href="firewall_nat_1to1_edit.php" class="btn btn-sm btn-success" title="<?=gettext('Add mapping to the end of the list')?>">
			<i class="fa-solid fa-turn-down icon-embed-btn"></i>
			<?=gettext('Add')?>
		</a>
		<button id="del_x" name="del_x" type="submit" class="btn btn-danger btn-sm" disabled title="<?=gettext('Delete selected mappings')?>">
			<i class="fa-solid fa-trash-can icon-embed-btn"></i>
			<?=gettext("Delete"); ?>
		</button>
		<button id="toggle_x" name="toggle_x" type="submit" class="btn btn-primary btn-sm" disabled value="<?=gettext("Toggle selected mappings"); ?>" title="<?=gettext('Toggle selected rules')?>">
			<i class="fa-solid fa-ban icon-embed-btn"></i>
			<?=gettext("Toggle"); ?>
		</button>
		<button type="submit" id="order-store" name="order-store" class="btn btn-primary btn-sm" disabled title="<?=gettext('Save mapping order')?>">
			<i class="fa-solid fa-save icon-embed-btn"></i>
			<?=gettext("Save")?>
		</button>
	</nav>
</form>

<div class="infoblock">
<?php print_info_box(sprintf(gettext('Depending on the way the WAN connection is setup, this may also need a %1$sVirtual IP%2$s.'), '<a href="firewall_virtual_ip.php">', '</a>') .
			   '<br />' .
			   gettext('If a 1:1 NAT entry is added for any of the interface IPs on this system, ' .
					   'it will make this system inaccessible on that IP address. i.e. if ' .
					   'the WAN IP address is used, any services on this system (IPsec, OpenVPN server, etc.) ' .
					   'using the WAN IP address will no longer function.'), 'info', false); ?>

</div>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

<?php if(!config_path_enabled('system/webgui', 'roworderdragging')): ?>
	// Make rules sortable
	$('table tbody.user-entries').sortable({
		cursor: 'grabbing',
		update: function(event, ui) {
			$('#order-store').removeAttr('disabled');
			dirty = true;
		}
	});
<?php endif; ?>

	// Check all of the rule checkboxes so that their values are posted
	$('#order-store').click(function () {
	   $('[id^=frc]').prop('checked', true);

		// Suppress the "Do you really want to leave the page" message
		saving = true;
	});

	$('[id^=fr]').click(function () {
		buttonsmode('frc', ['del_x', 'toggle_x']);
	});

	// Globals
	saving = false;
	dirty = false;

	// provide a warning message if the user tries to change page before saving
	$(window).bind('beforeunload', function(){
		if (!saving && dirty) {
			return ("<?=gettext('One or more NAT 1:1 mappings have been moved but have not yet been saved')?>");
		} else {
			return undefined;
		}
	});

	$('#selectAll').click(function() {
		var checkedStatus = this.checked;
		$('#ruletable tbody tr').find('td:first :checkbox').each(function() {
		$(this).prop('checked', checkedStatus);
		});
		buttonsmode('frc', ['del_x', 'toggle_x']);
	});
});
//]]>
</script>
<?php include("foot.inc"); ?>
