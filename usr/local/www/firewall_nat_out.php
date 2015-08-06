<?php
/* $Id$ */
/*
	firewall_nat_out.php
	Copyright (C) 2004 Scott Ullrich
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
/*
	pfSense_MODULE: nat
*/

##|+PRIV
##|*IDENT=page-firewall-nat-outbound
##|*NAME=Firewall: NAT: Outbound page
##|*DESCR=Allow access to the 'Firewall: NAT: Outbound' page.
##|*MATCH=firewall_nat_out.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

global $FilterIflist;
global $GatewaysList;

if (!is_array($config['nat']['outbound']))
	$config['nat']['outbound'] = array();

if (!is_array($config['nat']['outbound']['rule']))
	$config['nat']['outbound']['rule'] = array();

$a_out = &$config['nat']['outbound']['rule'];

if (!isset($config['nat']['outbound']['mode']))
	$config['nat']['outbound']['mode'] = "automatic";

$mode = $config['nat']['outbound']['mode'];

if ($_POST['apply']) {
	$retval = 0;
	$retval |= filter_configure();

	if(stristr($retval, "error") != true)
			$savemsg = get_std_save_message($retval);
	else
		$savemsg = $retval;

	if ($retval == 0) {
		clear_subsystem_dirty('natconf');
		clear_subsystem_dirty('filter');
	}
}

if (isset($_POST['save']) && $_POST['save'] == "Save") {
	/* mutually exclusive settings - if user wants advanced NAT, we don't generate automatic rules */
	if ($_POST['mode'] == "advanced" && ($mode == "automatic" || $mode == "hybrid")) {
		/*
		 *	user has enabled advanced outbound NAT and doesn't have rules
		 *	lets automatically create entries
		 *	for all of the interfaces to make life easier on the pip-o-chap
		 */
		if(empty($FilterIflist))
			filter_generate_optcfg_array();
		if(empty($GatewaysList))
			filter_generate_gateways();
		$tonathosts = filter_nat_rules_automatic_tonathosts(true);
		$automatic_rules = filter_nat_rules_outbound_automatic("");

		foreach ($tonathosts as $tonathost) {
			foreach ($automatic_rules as $natent) {
				$natent['source']['network'] = $tonathost['subnet'];
				$natent['descr'] .= sprintf(gettext(' - %1$s to %2$s'),
					$tonathost['descr'],
					convert_real_interface_to_friendly_descr($natent['interface']));
				$natent['created'] = make_config_revision_entry(null, gettext("Manual Outbound NAT Switch"));

				/* Try to detect already auto created rules and avoid duplicate them */
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

				if ($found === false)
					$a_out[] = $natent;
			}
		}
		$savemsg = gettext("Default rules for each interface have been created.");
		unset($FilterIflist, $GatewaysList);
	}

	$config['nat']['outbound']['mode'] = $_POST['mode'];

	if (write_config())
		mark_subsystem_dirty('natconf');
	header("Location: firewall_nat_out.php");
	exit;
}

if ($_GET['act'] == "del") {
	if ($a_out[$_GET['id']]) {
		unset($a_out[$_GET['id']]);
		if (write_config())
			mark_subsystem_dirty('natconf');
		header("Location: firewall_nat_out.php");
		exit;
	}
}

if (isset($_POST['del_x'])) {
	/* delete selected rules */
	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		foreach ($_POST['rule'] as $rulei) {
			unset($a_out[$rulei]);
		}

		if (write_config())
			mark_subsystem_dirty('natconf');

		header("Location: firewall_nat_out.php");
		exit;
	}

} else if ($_GET['act'] == "toggle") {
	if ($a_out[$_GET['id']]) {
		if(isset($a_out[$_GET['id']]['disabled']))
			unset($a_out[$_GET['id']]['disabled']);
		else
			$a_out[$_GET['id']]['disabled'] = true;

		if (write_config("Firewall: NAT: Outbound, enable/disable NAT rule"))
			mark_subsystem_dirty('natconf');

		header("Location: firewall_nat_out.php");
		exit;
	}
} else {
	/* yuck - IE won't send value attributes for image buttons, while Mozilla does - so we use .x/.y to find move button clicks instead... */
	unset($movebtn);
	foreach ($_POST as $pn => $pd) {
		if (preg_match("/move_(\d+)_x/", $pn, $matches)) {
			$movebtn = $matches[1];
			break;
		}
	}
	/* move selected rules before this rule */
	if (isset($movebtn) && is_array($_POST['rule']) && count($_POST['rule'])) {
		$a_out_new = array();

		/* copy all rules < $movebtn and not selected */
		for ($i = 0; $i < $movebtn; $i++) {
			if (!in_array($i, $_POST['rule']))
				$a_out_new[] = $a_out[$i];
		}

		/* copy all selected rules */
		for ($i = 0; $i < count($a_out); $i++) {
			if ($i == $movebtn)
				continue;

			if (in_array($i, $_POST['rule']))
				$a_out_new[] = $a_out[$i];
		}

		/* copy $movebtn rule */
		if ($movebtn < count($a_out))
			$a_out_new[] = $a_out[$movebtn];

		/* copy all rules > $movebtn and not selected */
		for ($i = $movebtn+1; $i < count($a_out); $i++) {
			if (!in_array($i, $_POST['rule']))
				$a_out_new[] = $a_out[$i];
		}
		if (count($a_out_new) > 0)
			$a_out = $a_out_new;

		if (write_config())
			mark_subsystem_dirty('natconf');

		header("Location: firewall_nat_out.php");
		exit;
	}
}

function rule_popup($src,$srcport,$dst,$dstport){
	global $config,$g;
	$aliases_array = array();
	if ($config['aliases']['alias'] <> "" and is_array($config['aliases']['alias'])) {
		$descriptions = array ();

		foreach ($config['aliases']['alias'] as $alias_id=>$alias_name){
			$loading_image="<a><img src=\'/themes/{$g['theme']}/images/misc/loader.gif\' alt=\'loader\' /> " .gettext("loading...")."</a>";

			switch ($alias_name['type']){
				case "port":
					$width="250";
					break;
				case "urltable":
					$width="500";
					break;
				default:
					$width="350";

					break;
			}
			$span_begin = "<span style=\"cursor: help;\" onmouseover=\"var response_html=domTT_activate(this, event, 'id','ttalias_{$alias_id}','content','{$loading_image}', 'trail', true, 'delay', 300, 'fade', 'both', 'fadeMax', 93, 'styleClass', 'niceTitle','type','velcro','width',{$width});alias_popup('{$alias_id}','{$g['theme']}','".gettext('loading...')."');\" onmouseout=\"this.style.color = ''; domTT_mouseout(this, event);\"><u>";
			$span_end = "</u></span>";

			if ($alias_name['name'] == $src) {
				$descriptions['src'] = $span_begin;
				$descriptions['src_end'] = $span_end;
			}

			if ($alias_name['name'] == $srcport) {
				$descriptions['srcport'] = $span_begin;
				$descriptions['srcport_end'] = $span_end;
			}

			if ($alias_name['name'] == $dst ) {
				$descriptions['dst'] = $span_begin;
				$descriptions['dst_end'] = $span_end;
			}

			if ($alias_name['name'] == $dstport) {
				$descriptions['dstport'] = $span_begin;
				$descriptions['dstport_end'] = $span_end;
			}
		}

		return $descriptions;
	}
}

$pgtitle = array(gettext("Firewall"),gettext("NAT"),gettext("Outbound"));
include("head.inc");

if ($savemsg)
	print_info_box($savemsg, 'success');

if (is_subsystem_dirty('natconf'))
	print_info_box_np(gettext("The NAT configuration has been changed.")."<br />".gettext("You must apply the changes in order for them to take effect."));

$tab_array = array();
$tab_array[] = array(gettext("Port Forward"), false, "firewall_nat.php");
$tab_array[] = array(gettext("1:1"), false, "firewall_nat_1to1.php");
$tab_array[] = array(gettext("Outbound"), true, "firewall_nat_out.php");
$tab_array[] = array(gettext("NPt"), false, "firewall_nat_npt.php");
display_top_tabs($tab_array);

require('classes/Form.class.php');

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
<script>

// Todo: Move script to external file ?
// Check the checkbox, and change the background color when clicking on a row
function fr_toggle(id, prefix) {

	if (!prefix)
		prefix = 'fr';

	var checkbox = document.getElementById(prefix + 'c' + id);

	checkbox.checked = !checkbox.checked;
	fr_bgcolor(id, prefix);
}

function fr_bgcolor(id, prefix) {
	if (!prefix)
		prefix = 'fr';

	var row = document.getElementById(prefix + id);
	var checkbox = document.getElementById(prefix + 'c' + id);
	var cells = row.getElementsByTagName('td');
	var cellcnt = cells.length;

	for (i = 0; i < cellcnt; i++)
		cells[i].style.backgroundColor = checkbox.checked ? "#B9DEF0" : "#FFFFFF"; // #B9DEF0 = Bootstrap "info"
}
</script>

<form action="firewall_nat_out.php" method="post" name="iform">
	<div class="panel panel-default">
		<div class="panel-heading"><?=gettext('Mappings')?></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th><!-- checkbox --></th>
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
				<tbody>
<?php
			$i = 0;
			foreach ($a_out as $natent):
				$iconfn = "pass";
				$textss = $textse = "";
				if ($mode == "disabled" || $mode == "automatic" || isset($natent['disabled']))
					$iconfn .= "_d";

				//build Alias popup box
				$alias_src_span_begin = "";
				$alias_src_port_span_begin = "";
				$alias_dst_span_begin = "";
				$alias_dst_port_span_begin = "";

				$alias_popup = rule_popup($natent['source']['network'],pprint_port($natent['sourceport']),$natent['destination']['address'],pprint_port($natent['dstport']));

				$alias_src_span_begin = $alias_popup["src"];
				$alias_src_port_span_begin = $alias_popup["srcport"];
				$alias_dst_span_begin = $alias_popup["dst"];
				$alias_dst_port_span_begin = $alias_popup["dstport"];

				$alias_src_span_end = $alias_popup["src_end"];
				$alias_src_port_span_end = $alias_popup["srcport_end"];
				$alias_dst_span_end = $alias_popup["dst_end"];
				$alias_dst_port_span_end = $alias_popup["dstport_end"];
?>
					<tr id="fr<?=$i?>">
						<td>
							<input type="checkbox" id="frc<?=$i?>" name="rule[]" value="<?=$i?>" onclick="fr_bgcolor('<?=$i?>')" style="margin: 0; padding: 0; width: 15px; height: 15px;" />
						</td>

						<td>
<?php
					if ($mode == "disabled" || $mode == "automatic"):
?>
							<img src="/bootstrap/glyphicons/glyphicons-halflings.png" class="<?= ($iconfn == "pass") ? "icon-ok":"icon-remove"?>"
								title="<?=gettext("Click to toggle enabled/disabled status")?>" alt="icon" />
<?php
					else:
?>
							<a href="?act=toggle&amp;id=<?=$i?>">
								<img src="/bootstrap/glyphicons/glyphicons-halflings.png" class="<?= ($iconfn == "pass") ? "icon-ok":"icon-remove"?>"
									title="<?=gettext("Click to toggle enabled/disabled status")?>" alt="icon" />
							</a>

<?php
						endif;
?>
						</td>

						<td onclick="fr_toggle(<?=$i?>)" id="frd<?=$i?>">
							<?=htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface']))?>
						</td>

						<td onclick="fr_toggle(<?=$i?>)" id="frd<?=$i?>">
<?php
	$natent['source']['network'] = ($natent['source']['network'] == "(self)") ? "This Firewall" : $natent['source']['network'];
?>
							<?=$alias_src_span_begin . $natent['source']['network'] . $alias_src_span_end?>
						</td>

						<td onclick="fr_toggle(<?=$i?>)" id="frd<?=$i?>">
<?php
						echo ($natent['protocol']) ? $natent['protocol'] . '/' : "" ;
						if (!$natent['sourceport'])
							echo "*";
						else
							echo $alias_src_port_span_begin . $natent['sourceport'] . $alias_src_port_span_end;
?>
						</td>

						<td onclick="fr_toggle(<?=$i?>)" id="frd<?=$i?>">
<?php
						if (isset($natent['destination']['any']))
							echo "*";
						else {
							if (isset($natent['destination']['not']))
								echo "!&nbsp;";
							echo $alias_dst_span_begin . $natent['destination']['address'] . $alias_dst_span_end;
						}
?>
						</td>

						<td onclick="fr_toggle(<?=$i?>)" id="frd<?=$i?>">
<?php
						echo ($natent['protocol']) ? $natent['protocol'] . '/' : "" ;

						if (!$natent['dstport'])
							echo "*";
						else
							echo $alias_dst_port_span_begin . $natent['dstport'] . $alias_dst_port_span_end;
?>
						</td>

						<td onclick="fr_toggle(<?=$i?>)" id="frd<?=$i?>">
<?php
						if (isset($natent['nonat']))
							echo '<I>NO NAT</I>';
						elseif (!$natent['target'])
							echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface'])) . " address";
						elseif ($natent['target'] == "other-subnet")
							echo $natent['targetip'] . '/' . $natent['targetip_subnet'];
						else
							echo $natent['target'];
?>
						</td>

						<td onclick="fr_toggle(<?=$i?>)" id="frd<?=$i?>">
<?php
						if (!$natent['natport'])
							echo "*";
						else
							echo $natent['natport'];
?>
						</td>

						<td onclick="fr_toggle(<?=$i?>)" id="frd<?=$i?>">
<?php
						if(isset($natent['staticnatport']))
							echo gettext("YES");
						else
							echo gettext("NO");
?>
						</td>

						<td onclick="fr_toggle(<?=$i?>)">
							<?=htmlspecialchars($natent['descr'])?>
						</td>

						<!-- Action	 icons -->
						<td onclick="fr_toggle(<?=$nnats?>)" id="frd<?=$nnats?>">
							<input name="move_<?=$i;?>"		  title="<?=gettext("Move selected mapping(s) before this rule");?>" src="/bootstrap/glyphicons/glyphicons-halflings.png" class="icon-eject" type="image"  />
							<a class="icon icon-pencil"		  title="<?=gettext("Edit mapping"); ?>" href="firewall_nat_out.php?id=<?=$i?>"></a>
							<a class="icon icon-remove-sign"  title="<?=gettext("Delete mapping")?>" href="firewall_nat_out.php?act=del&amp;id=<?=$i?>" onclick="return confirm('<?=gettext("Do you really want to delete this mapping?")?>')"></a>
							<a class="icon icon-share-alt"	  title="<?=gettext("Add a new mapping based on this one")?>" href="firewall_nat_out_edit.php?dup=<?=$i?>"></a>
						</td>
<?php
				$i++;
			endforeach;
?>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<nav class="action-buttons">
		<a href="firewall_nat_out_edit.php?after=-1" class="icon icon-plus-sign" title="<?=gettext('Add new mapping')?>"></a>&nbsp;
<?php
if ($i > 0) {
?>
		<input name="move_<?=$i?>" type="image" src="/bootstrap/glyphicons/glyphicons-halflings.png" class="icon-fast-forward" title="<?=gettext("Move selected mappings to end")?>" />
		<input name="del" type="image" src="/bootstrap/glyphicons/glyphicons-halflings.png" class="icon-remove-sign" title="<?=gettext("Delete selected mappings")?>" onclick="return confirm('<?=gettext("Do you really want to delete the selected mappings?")?>')" />
<?php
}
?>
	</nav>

<?php
if ($mode == "automatic" || $mode == "hybrid"):
	if(empty($FilterIflist))
		filter_generate_optcfg_array();
	if(empty($GatewaysList))
		filter_generate_gateways();

	$automatic_rules = filter_nat_rules_outbound_automatic(implode(" ", filter_nat_rules_automatic_tonathosts()));
	unset($FilterIflist, $GatewaysList);
?>
	<div class="panel panel-default">
		<div class="panel-heading"><?=gettext("Automatic rules:")?></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover table-condensed">
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
<?php
	foreach ($automatic_rules as $natent):
?>
					<tr>
						<td>
							<img src="/bootstrap/glyphicons/glyphicons-halflings.png" class="icon-ok" title="<?=gettext("automatic outbound nat")?>" alt="icon" />
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

		if (!$natent['sourceport'])
			echo "*";
		else
			echo $natent['sourceport'];
?>
						</td>
						<td>
<?php
		if (isset($natent['destination']['any']))
			echo "*";
		else {
			if (isset($natent['destination']['not']))
				echo "!&nbsp;";

			echo $natent['destination']['address'];
							}
?>
						</td>
						<td>
<?php
		echo ($natent['protocol']) ? $natent['protocol'] . '/' : "" ;
		if (!$natent['dstport'])
			echo "*";
		else
			echo $natent['dstport'];
?>
						</td>
						<td>
<?php
		if (isset($natent['nonat']))
			echo 'NO NAT';
		elseif (!$natent['target'])
			echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface'])) . " address";
		elseif ($natent['target'] == "other-subnet")
			echo $natent['targetip'] . '/' . $natent['targetip_subnet'];
		else
			echo $natent['target'];
?>
						</td>
						<td>
<?php
		if (!$natent['natport'])
			echo "*";
		else
			echo $natent['natport'];
?>
						</td>
						<td>
<?php
		if(isset($natent['staticnatport']))
			echo gettext("YES");
		else
			echo gettext("NO");
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

<div>
<?php
	print_info_box(gettext('If automatic outbound NAT selected, a mapping is automatically generated for each interface\'s subnet (except WAN-type connections) and the rules ' .
							'on "Mappings" section of this page are ignored.' . '<br />' .
							'If manual outbound NAT is selected, outbound NAT rules will not be automatically generated and only the mappings you specify on this page ' .
							'will be used.' . '<br />' .
							'If hybrid outbound NAT is selected, mappings you specify on this page will be used, followed by the automatically generated ones.' . '<br />' .
							'If disable outbound NAT is selected, no rules will be used.' . '<br />' .
							'If a target address other than an interface\'s IP address is used, then depending on the way the WAN connection is setup, a ') .
							'<a href="firewall_virtual_ip.php">' . gettext("Virtual IP") . '</a>' . gettext(" may also be required.")
				   );
?>
</div>

<?php include("foot.inc");