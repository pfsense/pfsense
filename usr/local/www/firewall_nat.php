<?php
/* $Id$ */
/*
	firewall_nat.php
	Copyright© 2015 Rubicon Communications, LLC (Netgate)
	This file is a part of pfSense (C)

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
##|*IDENT=page-firewall-nat-portforward
##|*NAME=Firewall: NAT: Port Forward page
##|*DESCR=Allow access to the 'Firewall: NAT: Port Forward' page.
##|*MATCH=firewall_nat.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("itemid.inc");

if (!is_array($config['nat']['rule']))
	$config['nat']['rule'] = array();

$a_nat = &$config['nat']['rule'];

/* update rule order, POST[rule] is an array of ordered IDs */
if (is_array($_POST['rule']) && !empty($_POST['rule'])) {
	$a_nat_new = array();

	// if a rule is not in POST[rule], it has been deleted by the user
	foreach ($_POST['rule'] as $id)
		$a_nat_new[] = $a_nat[$id];

	$a_nat = $a_nat_new;
	
	if (write_config())
		mark_subsystem_dirty('filter');
		
	header("Location: firewall_nat.php");
	exit;
}

/* if a custom message has been passed along, lets process it */
if ($_GET['savemsg'])
	$savemsg = $_GET['savemsg'];

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {

		$retval = 0;

		$retval |= filter_configure();
		$savemsg = get_std_save_message($retval);

		pfSense_handle_custom_code("/usr/local/pkg/firewall_nat/apply");

		if ($retval == 0) {
			clear_subsystem_dirty('natconf');
			clear_subsystem_dirty('filter');
		}

	}
}

if ($_GET['act'] == "del") {
	if ($a_nat[$_GET['id']]) {

		if (isset($a_nat[$_GET['id']]['associated-rule-id'])) {
			delete_id($a_nat[$_GET['id']]['associated-rule-id'], $config['filter']['rule']);
			$want_dirty_filter = true;
		}
		unset($a_nat[$_GET['id']]);

		if (write_config()) {
			mark_subsystem_dirty('natconf');
			if ($want_dirty_filter)
				mark_subsystem_dirty('filter');
		}

		header("Location: firewall_nat.php");
		exit;
	}
}

if (isset($_POST['del_x'])) {
	/* delete selected rules */
	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		foreach ($_POST['rule'] as $rulei) {
		$target = $rule['target'];
			// Check for filter rule associations
			if (isset($a_nat[$rulei]['associated-rule-id'])){
				delete_id($a_nat[$rulei]['associated-rule-id'], $config['filter']['rule']);

				mark_subsystem_dirty('filter');
			}
			unset($a_nat[$rulei]);
		}

		if (write_config())
			mark_subsystem_dirty('natconf');

		header("Location: firewall_nat.php");
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
		$a_nat_new = array();

		/* copy all rules < $movebtn and not selected */
		for ($i = 0; $i < $movebtn; $i++) {
				if (!in_array($i, $_POST['rule']))
						$a_nat_new[] = $a_nat[$i];
		}

		/* copy all selected rules */
		for ($i = 0; $i < count($a_nat); $i++) {
				if ($i == $movebtn)
						continue;
				if (in_array($i, $_POST['rule']))
						$a_nat_new[] = $a_nat[$i];
		}

		/* copy $movebtn rule */
		if ($movebtn < count($a_nat))
				$a_nat_new[] = $a_nat[$movebtn];

		/* copy all rules > $movebtn and not selected */
		for ($i = $movebtn+1; $i < count($a_nat); $i++) {
				if (!in_array($i, $_POST['rule']))
						$a_nat_new[] = $a_nat[$i];
		}

	$a_nat = $a_nat_new;

	if (write_config())
		mark_subsystem_dirty('natconf');
		header("Location: firewall_nat.php");
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

?>
<script>
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
		cells[i].style.backgroundColor = checkbox.checked ? "#B9DEF0" : "#FFFFFF";
}
</script>
<?php

$closehead = false;
$pgtitle = array(gettext("Firewall"),gettext("NAT"),gettext("Port Forward"));
include("head.inc");

if ($savemsg)
	print_info_box($savemsg, 'success');

if (is_subsystem_dirty('natconf'))
	print_info_box_np(gettext('The NAT configuration has been changed.') . '<br />' .
					  gettext('You must apply the changes in order for them to take effect.') . '<br />');

$tab_array = array();
$tab_array[] = array(gettext("Port Forward"), true, "firewall_nat.php");
$tab_array[] = array(gettext("1:1"), false, "firewall_nat_1to1.php");
$tab_array[] = array(gettext("Outbound"), false, "firewall_nat_out.php");
$tab_array[] = array(gettext("NPt"), false, "firewall_nat_npt.php");
display_top_tabs($tab_array);
?>

<form action="firewall_nat.php" method="post" name="iform">
	<div class="panel panel-default">
		<div class="panel-heading"><?=gettext('Rules')?></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th><!-- Checkbox --></th>
						<th><!-- Rule type --></th>
						<th><?=gettext("If")?></th>
						<th><?=gettext("Proto")?></th>
						<th><?=gettext("Src. addr")?></th>
						<th><?=gettext("Src. ports")?></th>
						<th><?=gettext("Dest. addr")?></th>
						<th><?=gettext("Dest. ports")?></th>
						<th><?=gettext("NAT IP")?></th>
						<th><?=gettext("NAT Ports")?></th>
						<th><?=gettext("Description")?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody class='user-entries'>

<?php
$nnats = $i = 0;

foreach ($a_nat as $natent):

//build Alias popup box
$span_end = "</U></span>";

$alias_popup = rule_popup($natent['source']['address'], pprint_port($natent['source']['port']), $natent['destination']['address'], pprint_port($natent['destination']['port']));

$alias_src_span_begin	  = $alias_popup["src"];
$alias_src_port_span_begin = $alias_popup["srcport"];
$alias_dst_span_begin	  = $alias_popup["dst"];
$alias_dst_port_span_begin = $alias_popup["dstport"];

$alias_src_span_end		= $alias_popup["src_end"];
$alias_src_port_span_end   = $alias_popup["srcport_end"];
$alias_dst_span_end		= $alias_popup["dst_end"];
$alias_dst_port_span_end   = $alias_popup["dstport_end"];

$alias_popup = rule_popup("","",$natent['target'], pprint_port($natent['local-port']));

$alias_target_span_begin	 = $alias_popup["dst"];
$alias_local_port_span_begin = $alias_popup["dstport"];

$alias_target_span_end	   = $alias_popup["dst_end"];
$alias_local_port_span_end	 = $alias_popup["dstport_end"];

if (isset($natent['disabled']))
	$textss = "<span class=\"gray\">";
else
	$textss = "<span>";

$textse = "</span>";

/* if user does not have access to edit an interface skip on to the next record */
if(!have_natpfruleint_access($natent['interface']))
	continue;
?>
					<tr id="fr<?=$nnats?>">
						<td>
							<input type="hidden" name="rule[]" value="<?=$i?>" />
							<input type="checkbox" id="frc<?=$nnats?>" name="rule[]" value="<?=$i?>" onClick="fr_bgcolor('<?=$nnats?>')" style="margin: 0; padding: 0; width: 15px; height: 15px;" /></td>
						<td>
<?php
if($natent['associated-rule-id'] == "pass"):
?>
							<img src="/bootstrap/glyphicons/glyphicons-halflings.png" class="icon-play" title="<?=gettext("All traffic matching this NAT entry is passed"); ?>" border="0" alt="pass" />
<?php
elseif (!empty($natent['associated-rule-id'])):
?>
							<img src="/bootstrap/glyphicons/glyphicons-halflings.png" class="icon-random" title="<?=gettext("Firewall rule ID"); ?><?=htmlspecialchars($nnatid); ?><?=gettext("is managed with this rule"); ?>" alt="change" />
<?php
endif;
?>
						</td>
						<td onClick="fr_toggle(<?=$nnats?>)" id="frd<?=$nnats?>" >
							<?=$textss?>
<?php
if (!$natent['interface'])
	echo htmlspecialchars(convert_friendly_interface_to_friendly_descr("wan"));
else
	echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface']));
?>
							<?=$textse?>
						</td>
	
						<td onclick="fr_toggle(<?=$nnats?>)" id="frd<?=$nnats?>" >
							<?=$textss?><?=strtoupper($natent['protocol'])?><?=$textse?>
						</td>
	
						<td onclick="fr_toggle(<?=$nnats?>)" id="frd<?=$nnats?>">
							<?=$textss?><?=$alias_src_span_begin?><?=htmlspecialchars(pprint_address($natent['source']))?><?=$alias_src_span_end?><?=$textse?>
						</td>
						<td onclick="fr_toggle(<?=$nnats?>)" id="frd<?=$nnats?>" >
							<?=$textss?><?=$alias_src_port_span_begin?><?=htmlspecialchars(pprint_port($natent['source']['port']))?><?=$alias_src_port_span_end?><?=$textse?>
						</td>
	
						<td onclick="fr_toggle(<?=$nnats?>)" id="frd<?=$nnats?>" >
							<?=$textss?><?=$alias_dst_span_begin?><?=htmlspecialchars(pprint_address($natent['destination']))?><?=$alias_dst_span_end?><?=$textse?>
						</td>
						<td onclick="fr_toggle(<?=$nnats?>)" id="frd<?=$nnats?>" >
							<?=$textss?><?=$alias_dst_port_span_begin?><?=htmlspecialchars(pprint_port($natent['destination']['port']))?><?=$alias_dst_port_span_end?><?=$textse?>
						</td>
	
						<td onclick="fr_toggle(<?=$nnats?>)" id="frd<?=$nnats?>" >
							<?=$textss?><?=$alias_target_span_begin?><?=htmlspecialchars($natent['target'])?><?=$alias_target_span_end?><?=$textse?>
						</td>
						<td onclick="fr_toggle(<?=$nnats?>)" id="frd<?=$nnats?>" >
<?php
$localport = $natent['local-port'];

list($dstbeginport, $dstendport) = explode("-", $natent['destination']['port']);

if ($dstendport) {
	$localendport = $natent['local-port'] + $dstendport - $dstbeginport;
	$localport	 .= '-' . $localendport;
}
?>
							<?=$textss?><?=$alias_local_port_span_begin?><?=htmlspecialchars(pprint_port($localport))?><?=$alias_local_port_span_end?><?=$textse?>
						</td>
	
						<td onclick="fr_toggle(<?=$nnats?>)" id="frd<?=$nnats?>">
							<?=$textss?><?=htmlspecialchars($natent['descr'])?>&nbsp;<?=$textse?>
						</td>
						<td onclick="fr_toggle(<?=$nnats?>)" id="frd<?=$nnats?>">
							<a class="btn btn-xs btn-info"	title="<?=gettext("Edit rule"); ?>" href="firewall_nat_edit.php?id=<?=$i?>"><?=gettext("Edit"); ?></a>
							<a class="btn btn-xs btn-danger"  title="<?=gettext("Delete rule")?>" href="firewall_nat.php?act=del&amp;id=<?=$i?>"><?=gettext("Del")?></a>
							<a class="btn btn-xs btn-success"	  title="<?=gettext("Add a new NAT based on this one")?>" href="firewall_nat_edit.php?dup=<?=$i?>"><?=gettext("Clone")?></a>
						</td>
					</tr>
<?php
	$i++;
	$nnats++;
endforeach;
?>
				</tbody>
			</table>
		</div>
	</div>
	
	<div class="pull-right">
		<a href="firewall_nat_edit.php?after=-1" class="btn btn-sm btn-success" title="<?=gettext('Add new rule')?>"><?=gettext('Add new rule')?></a>
		<input type="submit" id="order-store" class="btn btn-primary btn-sm" value="store changes" disabled="disabled" />
	</div>
</form>

<script>
events.push(function() {
	// Make rules draggable/sortable
	$('table tbody.user-entries').sortable({
		cursor: 'grabbing',
		update: function(event, ui) {
			$('#order-store').removeAttr('disabled');
		}
	});
});
</script>
<?php

if(count($a_nat) > 0) {
?>
<!-- Legend -->
<div>
	<dl class="dl-horizontal responsive">
		<dt><?=gettext('Legend')?></dt>					<dd></dd>
		<dt><i class="icon icon-play"></i></dt>			<dd><?=gettext('Pass')?></dd>
		<dt><i class="icon icon-random"></i></dt>		<dd><?=gettext('Linked rule')?></dd>
	</dl>
</div>

<?php
}

include("foot.inc");