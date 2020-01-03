<?php
/*
 * firewall_nat_out.php
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
##|*IDENT=page-firewall-nat-outbound
##|*NAME=Firewall: NAT: Outbound
##|*DESCR=Allow access to the 'Firewall: NAT: Outbound' page.
##|*MATCH=firewall_nat_out.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

global $FilterIflist;
global $GatewaysList;

init_config_arr(array('nat', 'outbound', 'rule'));
$a_out = &$config['nat']['outbound']['rule'];

// update rule order, POST[rule] is an array of ordered IDs
// All rule are 'checked' before posting
if (isset($_REQUEST['order-store'])) {
	if (is_array($_REQUEST['rule']) && !empty($_REQUEST['rule'])) {

		$a_out_new = array();

		// if a rule is not in POST[rule], it has been deleted by the user
		foreach ($_REQUEST['rule'] as $id) {
			$a_out_new[] = $a_out[$id];
		}

		$a_out = $a_out_new;

		if (write_config(gettext("Firewall: NAT: Outbound - reordered outbound NAT mappings."))) {
			mark_subsystem_dirty('natconf');
		}

		header("Location: firewall_nat_out.php");
		exit;

	}
}

if (!isset($config['nat']['outbound']['mode'])) {
	$config['nat']['outbound']['mode'] = "automatic";
}

$mode = $config['nat']['outbound']['mode'];

if ($_POST['apply']) {
	$retval = 0;
	$retval |= filter_configure();

	if ($retval == 0) {
		clear_subsystem_dirty('natconf');
		clear_subsystem_dirty('filter');
	}
}

if ($_POST['save']) {
	/* mutually exclusive settings - if user wants advanced NAT, we don't generate automatic rules */
	if ($_POST['mode'] == "advanced" && ($mode == "automatic" || $mode == "hybrid")) {
		/*
		 *	user has enabled advanced outbound NAT and doesn't have rules
		 *	lets automatically create entries
		 *	for all of the interfaces to make life easier on the pip-o-chap
		 */
		if (empty($FilterIflist)) {
			filter_generate_optcfg_array();
		}

		if (empty($GatewaysList)) {
			filter_generate_gateways();
		}

		$tonathosts = filter_nat_rules_automatic_tonathosts(true);
		$automatic_rules = filter_nat_rules_outbound_automatic("");

		foreach ($tonathosts as $tonathost) {
			foreach ($automatic_rules as $natent) {
				$natent['source']['network'] = $tonathost['subnet'];
				$natent['descr'] .= sprintf(gettext(' - %1$s to %2$s'),
					$tonathost['descr'],
					convert_real_interface_to_friendly_descr($natent['interface']));
				$natent['created'] = make_config_revision_entry(null, gettext("Manual Outbound NAT Switch"));

				/* Try to detect already auto created rules and avoid duplicating them */
				$found = false;
				foreach ($a_out as $rule) {
					if ($rule['interface'] == $natent['interface'] &&
					    $rule['source']['network'] == $natent['source']['network'] &&
					    $rule['dstport'] == $natent['dstport'] &&
					    $rule['target'] == $natent['target'] &&
					    $rule['descr'] == $natent['descr']) {
						$found = true;
						break;
					}
				}

				if ($found === false) {
					$a_out[] = $natent;
				}
			}
		}
		$default_rules_msg = gettext("Default rules for each interface have been created.");
		unset($FilterIflist, $GatewaysList);
	}

	$config['nat']['outbound']['mode'] = $_POST['mode'];

	if (write_config(gettext("Firewall: NAT: Outbound - saved outbound NAT settings."))) {
		mark_subsystem_dirty('natconf');
	}

	header("Location: firewall_nat_out.php");
	exit;
}

//	Delete a single rule/map
if ($_POST['act'] == "del") {

	if ($a_out[$_POST['id']]) {
		unset($a_out[$_POST['id']]);
		if (write_config(gettext("Firewall: NAT: Outbound - deleted outbound NAT mapping."))) {
			mark_subsystem_dirty('natconf');
		}

	header("Location: firewall_nat_out.php");
	exit;
	}
}

// Delete multiple maps Only checked rules will be in the
// POST
if (isset($_POST['del_x'])) {
	/* delete selected rules */
	print('Deleting rows<br />');

	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		foreach ($_POST['rule'] as $rulei) {
			print('Deleting ' . $rulei . '<br />');
			unset($a_out[$rulei]);
		}

		if (write_config(gettext("Firewall: NAT: Outbound - deleted selected outbound NAT mappings."))) {
			mark_subsystem_dirty('natconf');
		}

		header("Location: firewall_nat_out.php");
		exit;
	}

} else if ($_POST['act'] == "toggle") {
	if ($a_out[$_POST['id']]) {
		if (isset($a_out[$_POST['id']]['disabled'])) {
			unset($a_out[$_POST['id']]['disabled']);
			$wc_msg = gettext('Firewall: NAT: Outbound - enabled outbound NAT rule.');
		} else {
			$a_out[$_POST['id']]['disabled'] = true;
			$wc_msg = gettext('Firewall: NAT: Outbound - disabled outbound NAT rule.');
		}
		if (write_config($wc_msg)) {
			mark_subsystem_dirty('natconf');
		}

		header("Location: firewall_nat_out.php");
		exit;
	}
}

$pgtitle = array(gettext("Firewall"), gettext("NAT"), gettext("Outbound"));
$pglinks = array("", "firewall_nat.php", "@self");
include("head.inc");

if ($default_rules_msg) {
	print_info_box($default_rules_msg, 'success');
}

if ($_POST['apply']) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('natconf')) {
	print_apply_box(gettext('The NAT configuration has been changed.') . '<br />' .
					gettext('The changes must be applied for them to take effect.'));
}

$tab_array = array();
$tab_array[] = array(gettext("Port Forward"), false, "firewall_nat.php");
$tab_array[] = array(gettext("1:1"), false, "firewall_nat_1to1.php");
$tab_array[] = array(gettext("Outbound"), true, "firewall_nat_out.php");
$tab_array[] = array(gettext("NPt"), false, "firewall_nat_npt.php");
display_top_tabs($tab_array);

$form = new Form();

$section = new Form_Section('Outbound NAT Mode');

$group = new Form_Group('Mode');

$group->add(new Form_Checkbox(
	'mode',
	'Mode',
	null,
	$mode == 'automatic',
	'automatic'
))->displayAsRadio()->setHelp('Automatic outbound NAT rule generation.%s(IPsec passthrough included)', '<br />');

$group->add(new Form_Checkbox(
	'mode',
	null,
	null,
	$mode == 'hybrid',
	'hybrid'
))->displayAsRadio()->setHelp('Hybrid Outbound NAT rule generation.%s(Automatic Outbound NAT + rules below)', '<br />');

$group->add(new Form_Checkbox(
	'mode',
	null,
	null,
	$mode == 'advanced',
	'advanced'
))->displayAsRadio()->setHelp('Manual Outbound NAT rule generation.%s(AON - Advanced Outbound NAT)', '<br />');

$group->add(new Form_Checkbox(
	'mode',
	null,
	null,
	$mode == 'disabled',
	'disabled'
))->displayAsRadio()->setHelp('Disable Outbound NAT rule generation.%s(No Outbound NAT rules)', '<br />');

$section->add($group);

$form->add($section);
print($form);
?>

<form action="firewall_nat_out.php" method="post" name="iform">
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Mappings')?></h2></div>
		<div class="panel-body table-responsive">
			<table id="ruletable" class="table table-hover table-striped table-condensed">
				<thead>
					<tr>
						<th><input type="checkbox" id="selectAll" name="selectAll" /></th>
						<th><!-- status	  --></th>
						<th><?=gettext("Interface")?></th>
						<th><?=gettext("Source")?></th>
						<th><?=gettext("Source Port")?></th>
						<th><?=gettext("Destination")?></th>
						<th><?=gettext("Destination Port")?></th>
						<th><?=gettext("NAT Address")?></th>
						<th><?=gettext("NAT Port")?></th>
						<th><?=gettext("Static Port")?></th>
						<th><?=gettext("Description")?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody class="user-entries">
<?php
			$i = 0;

			foreach ($a_out as $natent):
				$iconfn = "pass";
				$textss = $textse = "";
				$trclass = '';

				if ($mode == "disabled" || $mode == "automatic" || isset($natent['disabled'])) {
					$iconfn .= "_d";
					$trclass = 'class="disabled"';

				}


				$alias = rule_columns_with_alias(
					$natent['source']['network'],
					pprint_port($natent['sourceport']),
					$natent['destination']['address'],
					pprint_port($natent['dstport'])
				);
?>

					<tr id="fr<?=$i;?>" <?=$trclass?> onClick="fr_toggle(<?=$i;?>)" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$i;?>';">
						<td >
							<input type="checkbox" id="frc<?=$i;?>" onClick="fr_toggle(<?=$i;?>)" name="rule[]" value="<?=$i;?>"/>
						</td>

						<td>
<?php
				if ($mode == "disabled" || $mode == "automatic"):
?>
							<i class="fa <?= ($iconfn == "pass") ? "fa-check":"fa-times"?>" title="<?=gettext("This rule is being ignored")?>"></i>
<?php
				else:
?>
							<a href="?act=toggle&amp;id=<?=$i?>" usepost>
								<i class="fa <?= ($iconfn == "pass") ? "fa-check":"fa-times"?>" title="<?=gettext("Click to toggle enabled/disabled status")?>"></i>
							</a>

<?php
				endif;
?>
<?php 				if (isset($natent['nonat'])): ?>
							&nbsp;<i class="fa fa-hand-stop-o text-danger" title="<?=gettext("Negated: Traffic matching this rule is not translated.")?>"></i>
<?php 				endif; ?>

						</td>

						<td>
							<?=htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface']))?>
						</td>

						<td>
<?php
						$natent['source']['network'] = ($natent['source']['network'] == "(self)") ? "This Firewall" : $natent['source']['network'];

						if (isset($alias['src'])):
?>
							<a href="/firewall_aliases_edit.php?id=<?=$alias['src']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['src'])?>" data-html="true">
<?php
						endif;
?>
							<?=str_replace('_', '_<wbr>', htmlspecialchars($natent['source']['network']))?>
<?php
						if (isset($alias['src'])):
?>
							<i class='fa fa-pencil'></i></a>
<?php
	endif;
?>
						</td>

						<td>
<?php
						echo ($natent['protocol']) ? $natent['protocol'] . '/' : "" ;
						if (!$natent['sourceport']) {
							echo "*";
						} else {

							if (isset($alias['srcport'])):
?>
							<a href="/firewall_aliases_edit.php?id=<?=$alias['srcport']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['srcport'])?>" data-html="true">
<?php
							endif;
?>
							<?=str_replace('_', '_<wbr>', htmlspecialchars(pprint_port($natent['sourceport'])))?>
<?php
							if (isset($alias['srcport'])):
?>
							<i class='fa fa-pencil'></i></a>
<?php
							endif;
						}
?>
						</td>

						<td>
<?php
						if (isset($natent['destination']['any'])) {
							echo "*";
						} else {
							if (isset($natent['destination']['not'])) {
								echo "!&nbsp;";
							}


							if (isset($alias['dst'])):
?>
							<a href="/firewall_aliases_edit.php?id=<?=$alias['dst']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['dst'])?>" data-html="true">
<?php
							endif;
?>
							<?=str_replace('_', '_<wbr>', htmlspecialchars($natent['destination']['address']))?>
<?php
							if (isset($alias['dst'])):
?>
							<i class='fa fa-pencil'></i></a>
<?php
							endif;
						}
?>
						</td>

						<td>
<?php
						echo ($natent['protocol']) ? $natent['protocol'] . '/' : "" ;

						if (!$natent['dstport']) {
							echo "*";
						} else {
							if (isset($alias['dstport'])):
?>
							<a href="/firewall_aliases_edit.php?id=<?=$alias['dstport']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias details')?>" data-content="<?=alias_info_popup($alias['dstport'])?>" data-html="true">
<?php
							endif;
?>
							<?=str_replace('_', '_<wbr>', htmlspecialchars(pprint_port($natent['dstport'])))?>
<?php
							if (isset($alias['dstport'])):
?>
							<i class='fa fa-pencil'></i></a>
<?php
							endif;
						}
?>

						</td>

						<td>
<?php
						if (isset($natent['nonat'])) {
							echo '<I>NO NAT</I>';
						} elseif (!$natent['target']) {
							echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface'])) . " address";
						} elseif ($natent['target'] == "other-subnet") {
							echo $natent['targetip'] . '/' . $natent['targetip_subnet'];
						} else {
							echo $natent['target'];
						}
?>
						</td>

						<td>
<?php
						if (!$natent['natport']) {
							echo "*";
						} else {
							echo $natent['natport'];
						}
?>
						</td>

						<td>
<?php						if (isset($natent['staticnatport'])) { ?>
							<i class="fa fa-check" title="Keep Source Port Static"></i>
<?php						} else { ?>
							<i class="fa fa-random" title="Randomize Source Port"></i>
<?php						} ?>
						</td>

						<td>
							<?=htmlspecialchars($natent['descr'])?>
						</td>

						<!-- Action	 icons -->
						<td>
							<a class="fa fa-pencil"	 title="<?=gettext("Edit mapping")?>" href="firewall_nat_out_edit.php?id=<?=$i?>"></a>
							<a class="fa fa-clone" title="<?=gettext("Add a new mapping based on this one")?>" href="firewall_nat_out_edit.php?dup=<?=$i?>"></a>
							<a class="fa fa-trash"	 title="<?=gettext("Delete mapping")?>" href="firewall_nat_out.php?act=del&amp;id=<?=$i?>" usepost></a>
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
		<a href="firewall_nat_out_edit.php?after=-1" class="btn btn-sm btn-success" title="<?=gettext('Add new mapping to the top of the list')?>">
			<i class="fa fa-level-up icon-embed-btn"></i>
			<?=gettext('Add')?>
		</a>
		<a href="firewall_nat_out_edit.php" class="btn btn-sm btn-success" title="<?=gettext('Add new mapping to the end of the list')?>">
			<i class="fa fa-level-down icon-embed-btn"></i>
			<?=gettext('Add')?>
		</a>
		<button name="del_x" type="submit" class="btn btn-danger btn-sm" value="<?=gettext("Delete selected map"); ?>" title="<?=gettext('Delete selected maps')?>">
			<i class="fa fa-trash icon-embed-btn"></i>
			<?=gettext("Delete"); ?>
		</button>
		<button type="submit" id="order-store" class="btn btn-primary btn-sm" value="Save changes" disabled name="order-store" title="<?=gettext('Save mapping order')?>">
			<i class="fa fa-save icon-embed-btn"></i>
			<?=gettext("Save")?>
		</button>
	</nav>

<?php
if ($mode == "automatic" || $mode == "hybrid"):
	if (empty($FilterIflist)) {
		filter_generate_optcfg_array();
	}

	if (empty($GatewaysList)) {
		filter_generate_gateways();
	}

	$automatic_rules = filter_nat_rules_outbound_automatic(implode(" ", filter_nat_rules_automatic_tonathosts()));
	unset($FilterIflist, $GatewaysList);
?>
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext("Automatic Rules:")?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-hover table-striped table-condensed">
				<thead>
					<tr>
						<th><!-- status	  --></th>
						<th><?=gettext("Interface")?></th>
						<th><?=gettext("Source")?></th>
						<th><?=gettext("Source Port")?></th>
						<th><?=gettext("Destination")?></th>
						<th><?=gettext("Destination Port")?></th>
						<th><?=gettext("NAT Address")?></th>
						<th><?=gettext("NAT Port")?></th>
						<th><?=gettext("Static Port")?></th>
						<th><?=gettext("Description")?></th>

					</tr>
				</thead>
				<tbody>
<?php
	foreach ($automatic_rules as $natent):
?>
					<tr>
						<td>
							<i class="fa fa-check" title="<?=gettext("automatic outbound nat")?>"></i>
						</td>
						<td>
							<?=htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface'])); ?>
						</td>
						<td>
							<?=$natent['source']['network']?>
						</td>
						<td>
<?php
		echo ($natent['protocol']) ? $natent['protocol'] . '/' : "" ;

		if (!$natent['sourceport']) {
			echo "*";
		} else {
			echo $natent['sourceport'];
		}
?>
						</td>
						<td>
<?php
		if (isset($natent['destination']['any'])) {
			echo "*";
		} else {
			if (isset($natent['destination']['not'])) {
				echo "!&nbsp;";
			}

			echo $natent['destination']['address'];
		}
?>
						</td>
						<td>
<?php
		echo ($natent['protocol']) ? $natent['protocol'] . '/' : "" ;
		if (!$natent['dstport']) {
			echo "*";
		} else {
			echo $natent['dstport'];
		}
?>
						</td>
						<td>
<?php
		if (isset($natent['nonat'])) {
			echo 'NO NAT';
		} elseif (!$natent['target']) {
			echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface'])) . " address";
		} elseif ($natent['target'] == "other-subnet") {
			echo $natent['targetip'] . '/' . $natent['targetip_subnet'];
		} else {
			echo $natent['target'];
		}
?>
						</td>
						<td>
<?php
		if (!$natent['natport']) {
			echo "*";
		} else {
			echo $natent['natport'];
		}
?>
						</td>
						<td>
<?php						if (isset($natent['staticnatport'])) { ?>
							<i class="fa fa-check" title="Keep Source Port Static"></i>
<?php						} else { ?>
							<i class="fa fa-random" title="Randomize Source Port"></i>
<?php						} ?>
						</td>
						<td>
							<?=htmlspecialchars($natent['descr'])?>
						</td>
					</tr>
<?php
	endforeach;
endif;
?>
				</tbody>
			</table>
		</div>
	</div>
</form>

<div class="infoblock">
<?php
	print_info_box(
		gettext('If automatic outbound NAT is selected, a mapping is automatically generated for each interface\'s subnet (except WAN-type connections) and the rules on the "Mappings" section of this page are ignored.') .
			'<br />' .
			gettext('If manual outbound NAT is selected, outbound NAT rules will not be automatically generated and only the mappings specified on this page will be used.') .
			'<br />' .
			gettext('If hybrid outbound NAT is selected, mappings specified on this page will be used, followed by the automatically generated ones.') .
			'<br />' .
			gettext('If disable outbound NAT is selected, no rules will be used.') .
			'<br />' .
			sprintf(
				gettext('If a target address other than an interface\'s IP address is used, then depending on the way the WAN connection is setup, a %1$sVirtual IP%2$s may also be required.'),
				'<a href="firewall_virtual_ip.php">',
				'</a>'),
		'info',
		false);
?>
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
			return ("<?=gettext('One or more NAT outbound mappings have been moved but have not yet been saved')?>");
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

<?php include("foot.inc");
