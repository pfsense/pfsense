<?php
/*
 * firewall_nat_1to1.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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

init_config_arr(array('nat', 'onetoone'));
$a_1to1 = &$config['nat']['onetoone'];

/* update rule order, POST[rule] is an array of ordered IDs */
if (array_key_exists('order-store', $_POST)) {
	if (is_array($_POST['rule']) && !empty($_POST['rule'])) {
		$a_1to1_new = array();

		// if a rule is not in POST[rule], it has been deleted by the user
		foreach ($_POST['rule'] as $id) {
			$a_1to1_new[] = $a_1to1[$id];
		}

		$a_1to1 = $a_1to1_new;

		if (write_config(gettext("Firewall: NAT: 1:1 - reordered NAT 1:1 mappings."))) {
			mark_subsystem_dirty('natconf');
		}

		header("Location: firewall_nat_1to1.php");
		exit;
	}
}


if ($_POST['apply']) {
	$retval = 0;
	$retval |= filter_configure();

	if ($retval == 0) {
		clear_subsystem_dirty('natconf');
		clear_subsystem_dirty('filter');
	}
}

if ($_POST['act'] == "del") {
	if ($a_1to1[$_POST['id']]) {
		unset($a_1to1[$_POST['id']]);
		if (write_config(gettext("Firewall: NAT: 1:1 - deleted NAT 1:1 mapping."))) {
			mark_subsystem_dirty('natconf');
		}

		header("Location: firewall_nat_1to1.php");
		exit;
	}
}

if (isset($_POST['del_x'])) {
	/* delete selected rules */
	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		foreach ($_POST['rule'] as $rulei) {
			unset($a_1to1[$rulei]);
		}

		if (write_config(gettext("Firewall: NAT: 1:1 - deleted selected NAT 1:1 mappings."))) {
			mark_subsystem_dirty('natconf');
		}

		header("Location: firewall_nat_1to1.php");
		exit;
	}

} else if ($_POST['act'] == "toggle") {
	if ($a_1to1[$_POST['id']]) {
		if (isset($a_1to1[$_POST['id']]['disabled'])) {
			unset($a_1to1[$_POST['id']]['disabled']);
			$wc_msg = gettext('Firewall: NAT: 1:1 - enabled a NAT 1:1 rule.');
		} else {
			$a_1to1[$_POST['id']]['disabled'] = true;
			$wc_msg = gettext('Firewall: NAT: 1:1 - disabled a NAT 1:1 rule.');
		}
		if (write_config($wc_msg)) {
			mark_subsystem_dirty('natconf');
		}
		header("Location: firewall_nat_1to1.php");
		exit;
	}
}

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
		foreach ($a_1to1 as $natent):
			if (isset($natent['disabled'])) {
				$iconfn = "pass_d";
			} else {
				$iconfn = "pass";
			}

			$alias = rule_columns_with_alias(
			$natent['source']['address'],
			pprint_port($natent['source']['port']),
			$natent['destination']['address'],
			pprint_port($natrent['destination']['port'])
);
?>
					<tr id="fr<?=$i;?>" onClick="fr_toggle(<?=$i;?>)" ondblclick="document.location='firewall_nat_1to1_edit.php?id=<?=$i;?>';" <?=(isset($natent['disabled']) ? ' class="disabled"' : '')?>>
						<td >
							<input type="checkbox" id="frc<?=$i;?>" onClick="fr_toggle(<?=$i;?>)" name="rule[]" value="<?=$i;?>"/>
						</td>

						<td>
							<a href="?act=toggle&amp;id=<?=$i?>" usepost>
								<i class="fa <?= ($iconfn == "pass") ? "fa-check":"fa-times"?>" title="<?=gettext("click to toggle enabled/disabled status")?>"></i>
<?php 				if (isset($natent['nobinat'])) { ?>
								&nbsp;<i class="fa fa-hand-stop-o text-danger" title="<?=gettext("Negated: This rule excludes NAT from a later rule")?>"></i>
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
<?php
					$source_net = pprint_address($natent['source']);
					$source_cidr = strstr($source_net, '/');
					echo $natent['external'] . $source_cidr;
?>
						</td>
						<td>
<?php
					echo $source_net;
?>
						</td>
						<td>
							<?php if (isset($alias['dst'])): ?>
								<a href="/firewall_aliases_edit.php?id=<?=$alias['dst']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['dst'])?>" data-html="true">
									<?=str_replace('_', '_<wbr>', htmlspecialchars(pprint_address($natent['destination'])))?>
								</a>
							<?php else: ?>
								<?=htmlspecialchars(pprint_address($natent['destination']))?>
							<?php endif; ?>
						</td>
						<td>
<?php
					echo htmlspecialchars($natent['descr']) . '&nbsp;';
?>
						</td>

						<td>
							<a class="fa fa-pencil" title="<?=gettext("Edit mapping")?>" href="firewall_nat_1to1_edit.php?id=<?=$i?>"></a>
							<a class="fa fa-clone" title="<?=gettext("Add a new mapping based on this one")?>" href="firewall_nat_1to1_edit.php?dup=<?=$i?>"></a>
							<a class="fa fa-trash" title="<?=gettext("Delete mapping")?>" href="firewall_nat_1to1.php?act=del&amp;id=<?=$i?>" usepost></a>
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
			<i class="fa fa-level-up icon-embed-btn"></i>
			<?=gettext('Add')?>
		</a>
		<a href="firewall_nat_1to1_edit.php" class="btn btn-sm btn-success" title="<?=gettext('Add mapping to the end of the list')?>">
			<i class="fa fa-level-down icon-embed-btn"></i>
			<?=gettext('Add')?>
		</a>
		<button name="del_x" type="submit" class="btn btn-danger btn-sm" title="<?=gettext('Delete selected mappings')?>">
			<i class="fa fa-trash icon-embed-btn"></i>
			<?=gettext("Delete"); ?>
		</button>
		<button type="submit" id="order-store" name="order-store" class="btn btn-primary btn-sm" disabled title="<?=gettext('Save mapping order')?>">
			<i class="fa fa-save icon-embed-btn"></i>
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

<?php if(!isset($config['system']['webgui']['roworderdragging'])): ?>
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
	});
});
//]]>
</script>
<?php include("foot.inc"); ?>
