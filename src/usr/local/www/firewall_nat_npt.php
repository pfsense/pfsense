<?php
/*
	firewall_nat_npt.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *  Copyright (c)  Copyright (C) 2011 Seth Mos <seth.mos@dds.nl>
 *
 *  Some or all of this file is based on the m0n0wall project which is
 *  Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *  Redistribution and use in source and binary forms, with or without modification,
 *  are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution.
 *
 *  3. All advertising materials mentioning features or use of this software
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-firewall-nat-npt
##|*NAME=Firewall: NAT: NPt
##|*DESCR=Allow access to the 'Firewall: NAT: NPt' page.
##|*MATCH=firewall_nat_npt.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

if (!is_array($config['nat']['npt'])) {
	$config['nat']['npt'] = array();
}

$a_npt = &$config['nat']['npt'];

/* update rule order, POST[rule] is an array of ordered IDs */
if (array_key_exists('order-store', $_POST)) {
	if (is_array($_POST['rule']) && !empty($_POST['rule'])) {
		$a_npt_new = array();

		// if a rule is not in POST[rule], it has been deleted by the user
		foreach ($_POST['rule'] as $id) {
			$a_npt_new[] = $a_npt[$id];
		}

		$a_npt = $a_npt_new;

		if (write_config()) {
			mark_subsystem_dirty('natconf');
		}

		header("Location: firewall_nat_npt.php");
		exit;
	}
}

if ($_POST) {
	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;
		$retval |= filter_configure();
		$savemsg = get_std_save_message($retval);

		if ($retval == 0) {
			clear_subsystem_dirty('natconf');
			clear_subsystem_dirty('filter');
		}
	}
}

if ($_GET['act'] == "del") {
	if ($a_npt[$_GET['id']]) {
		unset($a_npt[$_GET['id']]);
		if (write_config()) {
			mark_subsystem_dirty('natconf');
		}
		header("Location: firewall_nat_npt.php");
		exit;
	}
}

if (isset($_POST['del_x'])) {
	/* delete selected rules */
	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		foreach ($_POST['rule'] as $rulei) {
			unset($a_npt[$rulei]);
		}

		if (write_config()) {
			mark_subsystem_dirty('natconf');
		}

		header("Location: firewall_nat_npt.php");
		exit;
	}

} else if ($_GET['act'] == "toggle") {
	if ($a_npt[$_GET['id']]) {
		if (isset($a_npt[$_GET['id']]['disabled'])) {
			unset($a_npt[$_GET['id']]['disabled']);
		} else {
			$a_npt[$_GET['id']]['disabled'] = true;
		}
		if (write_config(gettext("Firewall: NAT: NPt, enable/disable NAT rule"))) {
			mark_subsystem_dirty('natconf');
		}
		header("Location: firewall_nat_npt.php");
		exit;
	}
}

$pgtitle = array(gettext("Firewall"), gettext("NAT"), gettext("NPt"));
include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (is_subsystem_dirty('natconf')) {
	print_apply_box(gettext('The NAT configuration has been changed.') . '<br />' .
					gettext('The changes must be applied for them to take effect.'));
}

$tab_array = array();
$tab_array[] = array(gettext("Port Forward"), false, "firewall_nat.php");
$tab_array[] = array(gettext("1:1"), false, "firewall_nat_1to1.php");
$tab_array[] = array(gettext("Outbound"), false, "firewall_nat_out.php");
$tab_array[] = array(gettext("NPt"), true, "firewall_nat_npt.php");
display_top_tabs($tab_array);
?>
<form action="firewall_nat_npt.php" method="post">
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('NPt Mappings')?></h2></div>
		<div id="mainarea" class="table-responsive panel-body">
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th><!-- checkbox --></th>
						<th><!-- icon --></th>
						<th><?=gettext("Interface")?></th>
						<th><?=gettext("External Prefix")?></th>
						<th><?=gettext("Internal prefix")?></th>
						<th><?=gettext("Description")?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody class="user-entries">
<?php

	$textse = "</span>";
	$i = 0;
	foreach ($a_npt as $natent):
		if (isset($natent['disabled'])) {
			$textss = "<span class=\"gray\">";
			$iconfn = "pass_d";
			$trclass = 'class="disabled"';
		} else {
			$textss = "<span>";
			$iconfn = "pass";
			$trclass = '';
		}
?>
					<tr id="fr<?=$i;?>" <?=$trclass?> onClick="fr_toggle(<?=$i;?>)" ondblclick="document.location='firewall_nat_npt_edit.php?id=<?=$i;?>';">
						<td >
							<input type="checkbox" id="frc<?=$i;?>" onClick="fr_toggle(<?=$i;?>)" name="rule[]" value="<?=$i;?>"/>
						</td>
						<td>
							<a href="?act=toggle&amp;id=<?=$i?>">
								<i class="fa <?= ($iconfn == "pass") ? "fa-check":"fa-times"?>" title="<?=gettext("click to toggle enabled/disabled status")?>"></i>
							</a>
						</td>
						<td>
<?php
		echo $textss;
		if (!$natent['interface']) {
			echo htmlspecialchars(convert_friendly_interface_to_friendly_descr("wan"));
		} else {
			echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface']));
		}
		echo $textse;
?>
						</td>
						<td>
<?php
	echo $textss . pprint_address($natent['destination']) . $textse;
?>
						</td>
						<td>
<?php
	echo $textss . pprint_address($natent['source']) . $textse;
?>
						</td>
						<td>
<?php
					echo $textss . htmlspecialchars($natent['descr']) . '&nbsp;' . $textse;
?>
						</td>
						<td>
							<a class="fa fa-pencil" title="<?=gettext("Edit mapping")?>" href="firewall_nat_npt_edit.php?id=<?=$i?>"></a>
							<a class="fa fa-clone" title="<?=gettext("Add a new mapping based on this one")?>" href="firewall_nat_npt_edit.php?dup=<?=$i?>"></a>
							<a class="fa fa-trash" title="<?=gettext("Delete mapping")?>" href="firewall_nat_npt.php?act=del&amp;id=<?=$i?>"></a>
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
		<a href="firewall_nat_npt_edit.php?after=-1" class="btn btn-sm btn-success" title="<?=gettext('Add mapping to the top of the list')?>">
			<i class="fa fa-level-up icon-embed-btn"></i>
			<?=gettext('Add')?>
		</a>
		<a href="firewall_nat_npt_edit.php" class="btn btn-sm btn-success" title="<?=gettext('Add mapping to the end of the list')?>">
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

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// Make rules draggable/sortable
	$('table tbody.user-entries').sortable({
		cursor: 'grabbing',
		update: function(event, ui) {
			$('#order-store').removeAttr('disabled');
			dirty = true;
		}
	});

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
			return ("<?=gettext('One or more NPt mappings have been moved but have not yet been saved')?>");
		} else {
			return undefined;
		}
	});
});
//]]>
</script>

<?php

include("foot.inc");
