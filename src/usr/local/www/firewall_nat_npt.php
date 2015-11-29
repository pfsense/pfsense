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
/*
	pfSense_MODULE: nat
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

if (!is_array($config['nat']['npt']))
	$config['nat']['npt'] = array();

$a_npt = &$config['nat']['npt'];

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

$pgtitle = array(gettext("Firewall"), gettext("NAT"), gettext("NPt"));
include("head.inc");

if ($savemsg)
	print_info_box($savemsg, 'success');

if (is_subsystem_dirty('natconf'))
	print_info_box_np(gettext("The NAT configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));

$tab_array = array();
$tab_array[] = array(gettext("Port Forward"), false, "firewall_nat.php");
$tab_array[] = array(gettext("1:1"), false, "firewall_nat_1to1.php");
$tab_array[] = array(gettext("Outbound"), false, "firewall_nat_out.php");
$tab_array[] = array(gettext("NPt"), true, "firewall_nat_npt.php");
display_top_tabs($tab_array);
?>

<div class="panel-body table responsive">
	<form method="post">
	<table class="table table-striped table-hover table-condensed">
		<thead>
			<tr>
				<th><?=gettext("Interface")?></th>
				<th><?=gettext("External Prefix")?></th>
				<th><?=gettext("Internal prefix")?></th>
				<th><?=gettext("Description")?></th>
				<th><!-- Buttons --></th>
			</tr>
		</thead>
		<tbody class="user-entries">
<?php

$i = 0;
foreach ($a_npt as $natent):
?>
			<tr<?=isset($natent['disabled'])? ' class="disabled"' : ''?>>
				<td>
					<input type="hidden" name="rule[]" value="<?=$i?>" />
<?php
	if (!$natent['interface'])
		print(htmlspecialchars(convert_friendly_interface_to_friendly_descr("wan")));
	else
		print(htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface'])));
?>
				</td>
<?php
	$source_net = pprint_address($natent['source']);
	$source_cidr = strstr($source_net, '/');
	$destination_net = pprint_address($natent['destination']);
	$destination_cidr = strstr($destination_net, '/');
?>
				<td>
					<?=$destination_net?>
				</td>
				<td>
					<?=$source_net?>
				</td>
				<td>
					<?=htmlspecialchars($natent['descr'])?>
				</td>
				<td>
					<a href="firewall_nat_npt_edit.php?id=<?=$i?>" class="btn btn-xs btn-info"><?=gettext("Edit")?></a>
					<a href="firewall_nat_npt.php?act=del&amp;id=<?=$i?>" class="btn btn-xs btn-danger"><?=gettext("Delete")?></a>
				</td>
			</tr>
<?php
	$i++;
endforeach;
?>
		</tbody>
	</table>
</div>

<nav class="action-buttons">
	<a href="firewall_nat_npt_edit.php" class="btn btn-sm btn-success">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
	<button type="submit" id="order-store" class="btn btn-primary btn-sm" value="store changes" disabled">
		<i class="fa fa-save icon-embed-btn"></i>
		<?=gettext("Save")?>
	</button>
</nav>

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

include("foot.inc");
