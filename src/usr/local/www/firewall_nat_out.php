<?php
/*
	firewall_nat_out.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-firewall-nat-outbound
##|*NAME=Firewall: NAT: Outbound
##|*DESCR=Allow access to the 'Firewall: NAT: Outbound' page.
##|*MATCH=firewall_nat_out.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

global $FilterIflist;
global $GatewaysList;

if (!is_array($config['nat']['outbound'])) {
	$config['nat']['outbound'] = array();
}

if (!is_array($config['nat']['outbound']['rule'])) {
	$config['nat']['outbound']['rule'] = array();
}

$a_out = &$config['nat']['outbound']['rule'];

// update rule order, POST[rule] is an array of ordered IDs
// All rule are 'checked' before posting
if (isset($_POST['order-store'])) {
	if (is_array($_POST['rule']) && !empty($_POST['rule'])) {

		$a_out_new = array();

		// if a rule is not in POST[rule], it has been deleted by the user
		foreach ($_POST['rule'] as $id) {
			$a_out_new[] = $a_out[$id];
		}

		$a_out = $a_out_new;

		if (write_config()) {
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

	if (stristr($retval, "error") <> true) {
			$savemsg = get_std_save_message($retval);
	} else {
		$savemsg = $retval;
	}

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
		$savemsg = gettext("Default rules for each interface have been created.");
		unset($FilterIflist, $GatewaysList);
	}

	$config['nat']['outbound']['mode'] = $_POST['mode'];

	if (write_config()) {
		mark_subsystem_dirty('natconf');
	}

	header("Location: firewall_nat_out.php");
	exit;
}

//	Delete a single rule/map
if ($_GET['act'] == "del") {

	if ($a_out[$_GET['id']]) {
		unset($a_out[$_GET['id']]);
		if (write_config()) {
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

		if (write_config()) {
			mark_subsystem_dirty('natconf');
		}

		header("Location: firewall_nat_out.php");
		exit;
	}

} else if ($_GET['act'] == "toggle") {
	if ($a_out[$_GET['id']]) {
		if (isset($a_out[$_GET['id']]['disabled'])) {
			unset($a_out[$_GET['id']]['disabled']);
		} else {
			$a_out[$_GET['id']]['disabled'] = true;
		}
		if (write_config("Firewall: NAT: Outbound, enable/disable NAT rule")) {
			mark_subsystem_dirty('natconf');
		}

		header("Location: firewall_nat_out.php");
		exit;
	}
}

$pgtitle = array(gettext("Firewall"), gettext("NAT"), gettext("Outbound"));
include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (is_subsystem_dirty('natconf')) {
	print_info_box_np(gettext("The NAT configuration has been changed.")."<br />".gettext("You must apply the changes in order for them to take effect."));
}

$tab_array = array();
$tab_array[] = array(gettext("Port Forward"), false, "firewall_nat.php");
$tab_array[] = array(gettext("1:1"), false, "firewall_nat_1to1.php");
$tab_array[] = array(gettext("Outbound"), true, "firewall_nat_out.php");
$tab_array[] = array(gettext("NPt"), false, "firewall_nat_npt.php");
display_top_tabs($tab_array);

$form = new Form();

$section = new Form_Section('General Logging Options');

$group = new Form_Group('Mode');

$group->add(new Form_Checkbox(
	'mode',
	'Mode',
	null,
	$mode == 'automatic',
	'automatic'
))->displayAsRadio()->setHelp('Automatic outbound NAT rule generation.' . '<br />' . '(IPsec passthrough included)');

$group->add(new Form_Checkbox(
	'mode',
	null,
	null,
	$mode == 'hybrid',
	'hybrid'
))->displayAsRadio()->setHelp('Hybrid Outbound NAT rule generation.' . '<br />' . '(Automatic Outbound NAT + rules below)');

$group->add(new Form_Checkbox(
	'mode',
	null,
	null,
	$mode == 'advanced',
	'advanced'
))->displayAsRadio()->setHelp('Manual Outbound NAT rule generation.' . '<br />' . '(AON - Advanced Outbound NAT)');

$group->add(new Form_Checkbox(
	'mode',
	null,
	null,
	$mode == 'disabled',
	'disabled'
))->displayAsRadio()->setHelp('Disable Outbound NAT rule generation.' . '<br />' . '(No Outbound NAT rules)');

$section->add($group);

$form->add($section);
print($form);
?>

<form action="firewall_nat_out.php" method="post" name="iform">
	<div class="panel panel-default">
		<div class="panel-heading"><?=gettext('Mappings')?></div>
		<div class="panel-body table-responsive">
			<table class="table table-hover table-striped table-condensed">
				<thead>
					<tr>
						<th><!-- checkbox	  --></th>
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
				if ($mode == "disabled" || $mode == "automatic" || isset($natent['disabled'])) {
					$iconfn .= "_d";
				}


				$alias = rule_columns_with_alias(
					$natent['source']['address'],
					pprint_port($natent['source']['port']),
					$natent['destination']['address'],
					pprint_port($natent['destination']['port'])
				);
?>

					<tr id="fr<?=$i;?>" onClick="fr_toggle(<?=$i;?>)" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$i;?>';">
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
							<a href="?act=toggle&amp;id=<?=$i?>">
								<i class="fa <?= ($iconfn == "pass") ? "fa-check":"fa-hidden"?>" title="<?=gettext("Click to toggle enabled/disabled status")?>"></i>
							</a>

<?php
				endif;
?>
						</td>

						<td>
							<?=htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface']))?>
						</td>

						<td>
<?php
						$natent['source']['network'] = ($natent['source']['network'] == "(self)") ? "This Firewall" : $natent['source']['network'];
?>
<?php
						if (isset($alias['src'])):
?>
							<a href="/firewall_aliases_edit.php?id=<?=$alias['src']?>" data-toggle="popover" data-trigger="hover focus" title="Alias details" data-content="<?=alias_info_popup($alias['src'])?>" data-html="true">
<?php
						endif;
?>
							<?=htmlspecialchars($natent['source']['network'])?>
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
							<a href="/firewall_aliases_edit.php?id=<?=$alias['srcport']?>" data-toggle="popover" data-trigger="hover focus" title="Alias details" data-content="<?=alias_info_popup($alias['srcport'])?>" data-html="true">
<?php
							endif;
?>
							<?=htmlspecialchars($natent['sourceport'])?>
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
							<a href="/firewall_aliases_edit.php?id=<?=$alias['dst']?>" data-toggle="popover" data-trigger="hover focus" title="Alias details" data-content="<?=alias_info_popup($alias['dst'])?>" data-html="true">
<?php
							endif;
?>
							<?=htmlspecialchars($natent['destination']['address'])?>
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
							<a href="/firewall_aliases_edit.php?id=<?=$alias['dstport']?>" data-toggle="popover" data-trigger="hover focus" title="Alias details" data-content="<?=alias_info_popup($alias['dstport'])?>" data-html="true">
<?php
							endif;
?>
							<?=htmlspecialchars($natent['dstport'])?>
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
<?php
						if (isset($natent['staticnatport'])) {
							echo gettext("YES");
						} else {
							echo gettext("NO");
						}
?>
						</td>

						<td>
							<?=htmlspecialchars($natent['descr'])?>
						</td>

						<!-- Action	 icons -->
						<td>
							<a class="fa fa-pencil"	 title="<?=gettext("Edit mapping")?>" href="firewall_nat_out_edit.php?id=<?=$i?>"></a>
							<a class="fa fa-clone" title="<?=gettext("Add a new mapping based on this one")?>" href="firewall_nat_out_edit.php?dup=<?=$i?>"></a>
							<a class="fa fa-trash"	 title="<?=gettext("Delete mapping")?>" href="firewall_nat_out.php?act=del&amp;id=<?=$i?>"></a>
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
		<button type="submit" id="order-store" class="btn btn-primary btn-sm" value="Save changes" disabled name="order-store" title="<?=gettext('Save map order')?>">
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
		<div class="panel-heading"><?=gettext("Automatic rules:")?></div>
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
<?php
		if (isset($natent['staticnatport'])) {
			echo gettext("YES");
		} else {
			echo gettext("NO");
		}
?>
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
	print_info_box(gettext('If automatic outbound NAT is selected, a mapping is automatically generated for each interface\'s subnet (except WAN-type connections) and the rules ' .
							'on the "Mappings" section of this page are ignored.' . '<br />' .
							'If manual outbound NAT is selected, outbound NAT rules will not be automatically generated and only the mappings you specify on this page ' .
							'will be used.' . '<br />' .
							'If hybrid outbound NAT is selected, mappings you specify on this page will be used, followed by the automatically generated ones.' . '<br />' .
							'If disable outbound NAT is selected, no rules will be used.' . '<br />' .
							'If a target address other than an interface\'s IP address is used, then depending on the way the WAN connection is setup, a ') .
							'<a href="firewall_virtual_ip.php">' . gettext("Virtual IP") . '</a>' . gettext(" may also be required."),
				   info);
?>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Make rules sortable
	$('table tbody.user-entries').sortable({
		cursor: 'grabbing',
		update: function(event, ui) {
			$('#order-store').removeAttr('disabled');
		}
	});

	// Check all of the rule checkboxes so that their values are posted
	$('#order-store').click(function () {
	   $('[id^=frc]').prop('checked', true);
	});
});
//]]>
</script>

<?php include("foot.inc");
