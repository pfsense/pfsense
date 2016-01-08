<?php
/*
	interfaces_vlan.php
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
##|*IDENT=page-interfaces-vlan
##|*NAME=Interfaces: VLAN
##|*DESCR=Allow access to the 'Interfaces: VLAN' page.
##|*MATCH=interfaces_vlan.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['vlans']['vlan'])) {
	$config['vlans']['vlan'] = array();
}

$a_vlans = &$config['vlans']['vlan'] ;

function vlan_inuse($num) {
	global $config, $a_vlans;

	$iflist = get_configured_interface_list(false, true);
	foreach ($iflist as $if) {
		if ($config['interfaces'][$if]['if'] == $a_vlans[$num]['vlanif']) {
			return true;
		}
	}

	return false;
}

if ($_POST['act'] == "del") {
	if (!isset($_POST['id'])) {
		$input_errors[] = gettext("Wrong parameters supplied");
	} else if (empty($a_vlans[$_POST['id']])) {
		$input_errors[] = gettext("Wrong index supplied");
	/* check if still in use */
	} else if (vlan_inuse($_POST['id'])) {
		$input_errors[] = gettext("This VLAN cannot be deleted because it is still being used as an interface.");
	} else {
		if (does_interface_exist($a_vlans[$_POST['id']]['vlanif'])) {
			pfSense_interface_destroy($a_vlans[$_POST['id']]['vlanif']);
		}
		unset($a_vlans[$_POST['id']]);

		write_config();

		header("Location: interfaces_vlan.php");
		exit;
	}
}


$pgtitle = array(gettext("Interfaces"), gettext("VLAN"));
$shortcut_section = "interfaces";
include('head.inc');

if ($input_errors) print_input_errors($input_errors);

$tab_array = array();
$tab_array[] = array(gettext("Interface assignments"), false, "interfaces_assign.php");
$tab_array[] = array(gettext("Interface Groups"), false, "interfaces_groups.php");
$tab_array[] = array(gettext("Wireless"), false, "interfaces_wireless.php");
$tab_array[] = array(gettext("VLANs"), true, "interfaces_vlan.php");
$tab_array[] = array(gettext("QinQs"), false, "interfaces_qinq.php");
$tab_array[] = array(gettext("PPPs"), false, "interfaces_ppps.php");
$tab_array[] = array(gettext("GRE"), false, "interfaces_gre.php");
$tab_array[] = array(gettext("GIF"), false, "interfaces_gif.php");
$tab_array[] = array(gettext("Bridges"), false, "interfaces_bridge.php");
$tab_array[] = array(gettext("LAGG"), false, "interfaces_lagg.php");
display_top_tabs($tab_array);

?>
<form action="interfaces_vlan.php" method="post">
	<input id="act" type="hidden" name="act" value="" />
	<input id="id" type="hidden" name="id" value=""/>

	<div class="table-responsive">
		<table class="table table-striped table-hover table-condensed">
			<thead>
				<tr>
					<th><?=gettext('Interface');?></th>
					<th><?=gettext('VLAN tag');?></th>
					<th><?=gettext('Priority');?></th>
					<th><?=gettext('Description');?></th>
					<th></th>
				</tr>
			</thead>
<?php
	$i = 0;
	foreach ($a_vlans as $vlan) {
?>
				<tr>
					<td><?=htmlspecialchars($vlan['if']);?></td>
					<td><?=htmlspecialchars($vlan['tag']);?></td>
					<td><?=htmlspecialchars($vlan['pcp']);?></td>
					<td><?=htmlspecialchars($vlan['descr']);?></td>
					<td>
						<a class="fa fa-pencil"	title="<?=gettext('Edit VLAN')?>"	role="button" href="interfaces_vlan_edit.php?id=<?=$i?>"></a>
<!--						<a class="btn btn-danger btn-xs" role="button" href="interfaces_vlan.php?act=del&amp;id=<?=$i?>"><?=gettext('Delete')?></a></td> -->
						<a class="fa fa-trash"	title="<?=gettext('Delete VLAN')?>"	role="button" id="del-<?=$i?>"></a>
					</td>
				</tr>
<?php
			$i++;
	}
?>
		</table>
		<nav class="action-buttons">
			<a class="btn btn-success btn-sm" role="button" href="interfaces_vlan_edit.php">
				<i class="fa fa-plus icon-embed-btn"></i>
				<?=gettext('Add'); ?>
			</a>
		</nav>
	</div>
</form>

<div class="infoblock">
	<?=print_info_box(sprintf(gettext('NOTE: Not all drivers/NICs support 802.1Q '.
		'VLAN tagging properly. <br />On cards that do not explicitly support it, VLAN '.
		'tagging will still work, but the reduced MTU may cause problems.<br />See the '.
		'%s handbook for information on supported cards.'), $g['product_name']), 'info')?>
</div>
<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// Select 'delete button' clicks, extract the id, set the hidden input values and submit
	$('[id^=del-]').click(function(event) {
		$('#act').val('del');
		$('#id').val(this.id.replace("del-", ""));
		$(this).parents('form').submit();
	});
});
//]]>
</script>
<?php
include("foot.inc");
