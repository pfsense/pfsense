<?php
/*
 * firewall_nat.php
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
##|*IDENT=page-firewall-nat-portforward
##|*NAME=Firewall: NAT: Port Forward
##|*DESCR=Allow access to the 'Firewall: NAT: Port Forward' page.
##|*MATCH=firewall_nat.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("itemid.inc");

init_config_arr(array('filter', 'rule'));
init_config_arr(array('nat', 'separator'));
init_config_arr(array('nat', 'rule'));
$a_nat = &$config['nat']['rule'];
$a_separators = &$config['nat']['separator'];

$specialsrcdst = explode(" ", "any pptp pppoe l2tp openvpn");
$ifdisp = get_configured_interface_with_descr();
foreach ($ifdisp as $kif => $kdescr) {
	$specialsrcdst[] = "{$kif}";
	$specialsrcdst[] = "{$kif}ip";
}

if (array_key_exists('order-store', $_REQUEST) && have_natpfruleint_access($natent['interface'])) {
	$updated = false;
	$dirty = false;

	/* update rule order, POST[rule] is an array of ordered IDs */
	if (is_array($_POST['rule']) && !empty($_POST['rule'])) {
		$a_nat_new = array();

		// if a rule is not in POST[rule], it has been deleted by the user
		foreach ($_POST['rule'] as $id) {
			$a_nat_new[] = $a_nat[$id];
		}

		if ($a_nat !== $a_nat_new) {
			$a_nat = $a_nat_new;
			$dirty = true;
		}
	}

	/* update separator order, POST[separator] is an array of ordered IDs */
	if (is_array($_POST['separator']) && !empty($_POST['separator'])) {
		$new_separator = array();
		$idx = 0;

		foreach ($_POST['separator'] as $separator) {
			$new_separator['sep' . $idx++] = $separator;
		}

		if ($a_separators !== $new_separator) {
			$a_separators = $new_separator;
			$updated = true;
		}			
	} else if (!empty($a_separators)) {
		$a_separators = "";
		$updated = true;
	}

	if ($updated || $dirty) {
		if (write_config("NAT: Rule order changed")) {
			if ($dirty) {
				mark_subsystem_dirty('natconf');
			}
		}
	}

	header("Location: firewall_nat.php");
	exit;
}

/* if a custom message has been passed along, lets process it */
if ($_REQUEST['savemsg']) {
	$savemsg = $_REQUEST['savemsg'];
}

if ($_POST['apply'] && have_natpfruleint_access($natent['interface'])) {

	$retval = 0;

	$retval |= filter_configure();

	pfSense_handle_custom_code("/usr/local/pkg/firewall_nat/apply");

	if ($retval == 0) {
		clear_subsystem_dirty('natconf');
		clear_subsystem_dirty('filter');
	}

}

if (($_POST['act'] == "del") && have_natpfruleint_access($natent['interface'])) {
	if ($a_nat[$_POST['id']]) {

		if (isset($a_nat[$_POST['id']]['associated-rule-id'])) {
			delete_id($a_nat[$_POST['id']]['associated-rule-id'], $config['filter']['rule']);
			$want_dirty_filter = true;
		}

		unset($a_nat[$_POST['id']]);

		// Update the separators
		$ridx = $_POST['id'];
		$mvnrows = -1;
		move_separators($a_separators, $ridx, $mvnrows);

		if (write_config("NAT: Rule deleted")) {
			mark_subsystem_dirty('natconf');
			if ($want_dirty_filter) {
				mark_subsystem_dirty('filter');
			}
		}

		header("Location: firewall_nat.php");
		exit;
	}
}

if (isset($_POST['del_x']) && have_natpfruleint_access($natent['interface'])) {

	/* delete selected rules */
	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		$first_idx = 0;		
		$num_deleted = 0;

		foreach ($_POST['rule'] as $rulei) {
			// Check for filter rule associations
			if (isset($a_nat[$rulei]['associated-rule-id'])) {
				delete_id($a_nat[$rulei]['associated-rule-id'], $config['filter']['rule']);
				mark_subsystem_dirty('filter');
			}

			unset($a_nat[$rulei]);

			// Capture first changed filter index for later separator shifting
			if (!$first_idx) $first_idx = $rulei;
			$num_deleted++;
		}

		if ($num_deleted) {
			move_separators($a_separators, $first_idx, -$num_deleted);
			if (write_config("NAT: Rule deleted")) {
				mark_subsystem_dirty('natconf');
			}
		}

		header("Location: firewall_nat.php");
		exit;
	}
} elseif (($_POST['act'] == "toggle") && have_natpfruleint_access($natent['interface'])) {
	if ($a_nat[$_POST['id']]) {
		if (isset($a_nat[$_POST['id']]['disabled'])) {
			unset($a_nat[$_POST['id']]['disabled']);
			$rule_status = true;
		} else {
			$a_nat[$_POST['id']]['disabled'] = true;
			$rule_status = false;
		}

		// Check for filter rule associations
		if (isset($a_nat[$_POST['id']]['associated-rule-id'])) {
			toggle_id($a_nat[$_POST['id']]['associated-rule-id'],
			    $config['filter']['rule'], $rule_status);
			unset($rule_status);
			mark_subsystem_dirty('filter');
		}

		if (write_config(gettext("Firewall: NAT: Port forward, enable/disable NAT rule"))) {
			mark_subsystem_dirty('natconf');
		}
		header("Location: firewall_nat.php");
		exit;
	}
}

$pgtitle = array(gettext("Firewall"), gettext("NAT"), gettext("Port Forward"));
$pglinks = array("", "@self", "@self");
include("head.inc");

if ($_POST['apply']) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('natconf') && have_natpfruleint_access($natent['interface'])) {
	print_apply_box(gettext('The NAT configuration has been changed.') . '<br />' .
					gettext('The changes must be applied for them to take effect.'));
}

$tab_array = array();
$tab_array[] = array(gettext("Port Forward"), true, "firewall_nat.php");
$tab_array[] = array(gettext("1:1"), false, "firewall_nat_1to1.php");
$tab_array[] = array(gettext("Outbound"), false, "firewall_nat_out.php");
$tab_array[] = array(gettext("NPt"), false, "firewall_nat_npt.php");
display_top_tabs($tab_array);

$columns_in_table = 13;
?>
<!-- Allow table to scroll when dragging outside of the display window -->
<style>
.table-responsive {
    clear: both;
    overflow-x: visible;
    margin-bottom: 0px;
}
</style>

<form action="firewall_nat.php" method="post" name="iform">
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Rules')?></h2></div>
		<div class="panel-body table-responsive">
			<table id="ruletable" class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th style="padding-left:10px;">  <input type="checkbox" id="selectAll" name="selectAll" /></th>
						<th><!-- Icon --></th>
						<th><!-- Rule type --></th>
						<th><?=gettext("Interface")?></th>
						<th><?=gettext("Protocol")?></th>
						<th><?=gettext("Source Address")?></th>
						<th><?=gettext("Source Ports")?></th>
						<th><?=gettext("Dest. Address")?></th>
						<th><?=gettext("Dest. Ports")?></th>
						<th><?=gettext("NAT IP")?></th>
						<th><?=gettext("NAT Ports")?></th>
						<th><?=gettext("Description")?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody class='user-entries'>
<?php

$nnats = $i = 0;
$separators = $config['nat']['separator'];

// Get a list of separator rows and use it to call the display separator function only for rows which there are separator(s).
// More efficient than looping through the list of separators on every row.
$seprows = separator_rows($separators);

foreach ($a_nat as $natent):

	// Display separator(s) for section beginning at rule n
	if ($seprows[$nnats]) {
		display_separator($separators, $nnats, $columns_in_table);
	}

	$localport = $natent['local-port'];

	list($dstbeginport, $dstendport) = explode("-", $natent['destination']['port']);

	if ($dstendport) {
		$localendport = $natent['local-port'] + $dstendport - $dstbeginport;
		$localport	 .= '-' . $localendport;
	}

	$alias = rule_columns_with_alias(
		$natent['source']['address'],
		pprint_port($natent['source']['port']),
		$natent['destination']['address'],
		pprint_port($natent['destination']['port']),
		$natent['target'],
		$localport
	);

	if (isset($natent['disabled'])) {
		$iconfn = "pass_d";
		$trclass = 'class="disabled"';
	} else {
		$iconfn = "pass";
		$trclass = '';
	}

	if (is_specialnet($natent['target'])) {
		foreach ($ifdisp as $kif => $kdescr) {
			if ($natent['target'] == "{$kif}ip") {
				$natent['target'] = $kdescr . ' address';
				break;
			}
		}
	}
?>

					<tr id="fr<?=$nnats;?>" <?=$trclass?> onClick="fr_toggle(<?=$nnats;?>)" ondblclick="document.location='firewall_nat_edit.php?id=<?=$i;?>';">
						<td >
<?php	if (have_natpfruleint_access($natent['interface'])): ?>
							<input type="checkbox" id="frc<?=$nnats;?>" onClick="fr_toggle(<?=$nnats;?>)" name="rule[]" value="<?=$i;?>"/>
<?php	endif; ?>
						</td>
						<td>
<?php	if (have_natpfruleint_access($natent['interface'])): ?>
							<a href="?act=toggle&amp;id=<?=$i?>" usepost>
								<i class="fa fa-check" title="<?=gettext("click to toggle enabled/disabled status")?>"></i>
							</a>
<?php	endif; ?>
<?php 	if (isset($natent['nordr'])) { ?>
								&nbsp;<i class="fa fa-hand-stop-o text-danger" title="<?=gettext("Negated: This rule excludes NAT from a later rule")?>"></i>
<?php 	} ?>
						</td>
						<td>
<?php
	if ($natent['associated-rule-id'] == "pass"):
?>
							<i class="fa fa-play" title="<?=gettext("All traffic matching this NAT entry is passed")?>"></i>
<?php
	elseif (!empty($natent['associated-rule-id'])):
?>
							<i class="fa fa-random" title="<?=sprintf(gettext("Firewall rule ID %s is managed by this rule"), htmlspecialchars($natent['associated-rule-id']))?>"></i>
<?php
	endif;
?>
						</td>
						<td>
							<?=$textss?>
<?php
	if (!$natent['interface']) {
		echo htmlspecialchars(convert_friendly_interface_to_friendly_descr("wan"));
	} else {
		echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface']));
	}
?>
							<?=$textse?>
						</td>

						<td>
							<?=$textss?><?=strtoupper($natent['protocol'])?><?=$textse?>
						</td>

						<td>


<?php
	if (isset($alias['src'])):
?>
							<a href="/firewall_aliases_edit.php?id=<?=$alias['src']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['src'])?>" data-html="true">
<?php
	endif;
?>
							<?=str_replace('_', '_<wbr>', htmlspecialchars(pprint_address($natent['source'])))?>
<?php
	if (isset($alias['src'])):
?>
							</a>
<?php
	endif;
?>
						</td>
						<td>
<?php
	if (isset($alias['srcport'])):
?>
							<a href="/firewall_aliases_edit.php?id=<?=$alias['srcport']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['srcport'])?>" data-html="true">
<?php
	endif;
?>
							<?=str_replace('_', '_<wbr>', htmlspecialchars(pprint_port($natent['source']['port'])))?>
<?php
	if (isset($alias['srcport'])):
?>
							</a>
<?php
	endif;
?>
						</td>

						<td>
<?php
	if (isset($alias['dst'])):
?>
							<a href="/firewall_aliases_edit.php?id=<?=$alias['dst']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['dst'])?>" data-html="true">
<?php
	endif;
?>
							<?=str_replace('_', '_<wbr>', htmlspecialchars(pprint_address($natent['destination'])))?>
<?php
	if (isset($alias['dst'])):
?>
							</a>
<?php
	endif;
?>
						</td>
						<td>
<?php
	if (isset($alias['dstport'])):
?>
							<a href="/firewall_aliases_edit.php?id=<?=$alias['dstport']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['dstport'])?>" data-html="true">
<?php
	endif;
?>
							<?=str_replace('_', '_<wbr>', htmlspecialchars(pprint_port($natent['destination']['port'])))?>
<?php
	if (isset($alias['dstport'])):
?>
							</a>
<?php
	endif;
?>
						</td>
						<td>
<?php
	if (isset($alias['target'])):
?>
							<a href="/firewall_aliases_edit.php?id=<?=$alias['target']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['target'])?>" data-html="true" >
<?php
	endif;
?>

							<?=str_replace('_', '_<wbr>', htmlspecialchars($natent['target']))?>
<?php
	if (isset($alias['target'])):
?>
							</a>
<?php
	endif;
?>
						</td>
						<td>
<?php
	if (isset($alias['targetport'])):
?>
							<a href="/firewall_aliases_edit.php?id=<?=$alias['targetport']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['targetport'])?>" data-html="true">
<?php
	endif;
?>
							<?=str_replace('_', '_<wbr>', htmlspecialchars(pprint_port($localport)))?>
<?php
	if (isset($alias['targetport'])):
?>
							</a>
<?php
	endif;
?>
						</td>

						<td>
							<?=htmlspecialchars($natent['descr'])?>
						</td>
						<td>
<?php	if (have_natpfruleint_access($natent['interface'])): ?>
							<a class="fa fa-pencil" title="<?=gettext("Edit rule"); ?>" href="firewall_nat_edit.php?id=<?=$i?>"></a>
							<a class="fa fa-clone"	  title="<?=gettext("Add a new NAT based on this one")?>" href="firewall_nat_edit.php?dup=<?=$i?>"></a>
							<a class="fa fa-trash"	title="<?=gettext("Delete rule")?>" href="firewall_nat.php?act=del&amp;id=<?=$i?>" usepost></a>
<?php	else: ?>
							-
<?php	endif; ?>
						</td>
					</tr>
<?php
	$i++;
	$nnats++;

endforeach;

// There can be separator(s) after the last rule listed.
if ($seprows[$nnats]) {
	display_separator($separators, $nnats, $columns_in_table);
}
?>
				</tbody>
			</table>
		</div>
	</div>

<?php	if (have_natpfruleint_access($natent['interface'])): ?>
	<nav class="action-buttons">
		<a href="firewall_nat_edit.php?after=-1" class="btn btn-sm btn-success" title="<?=gettext('Add rule to the top of the list')?>">
			<i class="fa fa-level-up icon-embed-btn"></i>
			<?=gettext('Add')?>
		</a>
		<a href="firewall_nat_edit.php" class="btn btn-sm btn-success" title="<?=gettext('Add rule to the end of the list')?>">
			<i class="fa fa-level-down icon-embed-btn"></i>
			<?=gettext('Add')?>
		</a>
		<button name="del_x" type="submit" class="btn btn-danger btn-sm" title="<?=gettext('Delete selected rules')?>">
			<i class="fa fa-trash icon-embed-btn"></i>
			<?=gettext("Delete"); ?>
		</button>
		<button type="submit" id="order-store" name="order-store" class="btn btn-primary btn-sm" disabled title="<?=gettext('Save rule order')?>">
			<i class="fa fa-save icon-embed-btn"></i>
			<?=gettext("Save")?>
		</button>
		<button type="submit" id="addsep" name="addsep" class="btn btn-sm btn-warning" title="<?=gettext('Add separator')?>">
			<i class="fa fa-plus icon-embed-btn"></i>
			<?=gettext("Separator")?>
		</button>
	</nav>
<?php	endif; ?>
</form>

<script type="text/javascript">
//<![CDATA[
//Need to create some variables here so that jquery/pfSenseHelpers.js can read them
iface = "<?=strtolower($if)?>";
cncltxt = '<?=gettext("Cancel")?>';
svtxt = '<?=gettext("Save")?>';
svbtnplaceholder = '<?=gettext("Enter a description, Save, then drag to final location.")?>';
configsection = "nat";
dirty = false;

events.push(function() {

<?php if(!isset($config['system']['webgui']['roworderdragging'])): ?>
	// Make rules sortable
	$('table tbody.user-entries').sortable({
		cursor: 'grabbing',
		update: function(event, ui) {
			$('#order-store').removeAttr('disabled');
			dirty = true;
			reindex_rules(ui.item.parent('tbody'));
			dirty = true;
		}
	});
<?php endif; ?>

	// Check all of the rule checkboxes so that their values are posted
	$('#order-store').click(function () {
	   $('[id^=frc]').prop('checked', true);

		// Save the separator bar configuration
		save_separators();

		// Suppress the "Do you really want to leave the page" message
		saving = true;

	});

	// Globals
	saving = false;
	dirty = false;

	// provide a warning message if the user tries to change page before saving
	$(window).bind('beforeunload', function(){
		if (!saving && dirty) {
			return ("<?=gettext('One or more Port Forward rules have been moved but have not yet been saved')?>");
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
<?php

if (count($a_nat) > 0) {
?>
<!-- Legend -->
<div>
	<dl class="dl-horizontal responsive">
		<dt><?=gettext('Legend')?></dt>					<dd></dd>
		<dt><i class="fa fa-play"></i></dt>			<dd><?=gettext('Pass')?></dd>
		<dt><i class="fa fa-random"></i></dt>		<dd><?=gettext('Linked rule')?></dd>
	</dl>
</div>

<?php
}

include("foot.inc");
