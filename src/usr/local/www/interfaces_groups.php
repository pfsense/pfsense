<?php
/*
	interfaces_groups.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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
##|*IDENT=page-interfaces-groups
##|*NAME=Interfaces: Groups
##|*DESCR=Create interface groups
##|*MATCH=interfaces_groups.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");

if (!is_array($config['ifgroups']['ifgroupentry'])) {
	$config['ifgroups']['ifgroupentry'] = array();
}

$a_ifgroups = &$config['ifgroups']['ifgroupentry'];

if ($_GET['act'] == "del") {
	if ($a_ifgroups[$_GET['id']]) {
		$members = explode(" ", $a_ifgroups[$_GET['id']]['members']);
		foreach ($members as $ifs) {
			$realif = get_real_interface($ifs);
			if ($realif) {
				mwexec("/sbin/ifconfig {$realif} -group " . $a_ifgroups[$_GET['id']]['ifname']);
			}
		}
		unset($a_ifgroups[$_GET['id']]);
		write_config();
		header("Location: interfaces_groups.php");
		exit;
	}
}

$pgtitle = array(gettext("Interfaces"), gettext("Groups"));
$shortcut_section = "interfaces";

include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Interface assignments"), false, "interfaces_assign.php");
$tab_array[] = array(gettext("Interface Groups"), true, "interfaces_groups.php");
$tab_array[] = array(gettext("Wireless"), false, "interfaces_wireless.php");
$tab_array[] = array(gettext("VLANs"), false, "interfaces_vlan.php");
$tab_array[] = array(gettext("QinQs"), false, "interfaces_qinq.php");
$tab_array[] = array(gettext("PPPs"), false, "interfaces_ppps.php");
$tab_array[] = array(gettext("GRE"), false, "interfaces_gre.php");
$tab_array[] = array(gettext("GIF"), false, "interfaces_gif.php");
$tab_array[] = array(gettext("Bridges"), false, "interfaces_bridge.php");
$tab_array[] = array(gettext("LAGG"), false, "interfaces_lagg.php");
display_top_tabs($tab_array);
?>
<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
		<thead>
			<tr>
				<th><?=gettext('Name');?></th>
				<th><?=gettext('Members');?></th>
				<th><?=gettext('Description');?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
<?php foreach ($a_ifgroups as $i => $ifgroupentry): ?>
			<tr>
				<td>
					<?=htmlspecialchars($ifgroupentry['ifname']); ?>
				</td>
				<td>
<?php
		$members_arr = explode(" ", $ifgroupentry['members']);
		$iflist = get_configured_interface_with_descr(false, true);
		$memberses_arr = array();
		foreach ($members_arr as $memb) {
			$memberses_arr[] = $iflist[$memb] ? $iflist[$memb] : $memb;
		}

		unset($iflist);
		$memberses = implode(", ", $memberses_arr);
		echo $memberses;
		if (count($members_arr) >= 10) {
			echo '&hellip;';
		}
?>
				</td>
				<td>
					<?=htmlspecialchars($ifgroupentry['descr']);?>
				</td>
				<td>
					<a class="fa fa-pencil"	title="<?=gettext('Edit group')?>"	href="interfaces_groups_edit.php?id=<?=$i; ?>"></a>
					<a class="fa fa-trash"	title="<?=gettext('Delete group')?>"	href="interfaces_groups.php?act=del&amp;id=<?=$i; ?>"></a>
				</td>
			</tr>
<?php endforeach; ?>
		</tbody>
	</table>
</div>

<nav class="action-buttons">
	<a class="btn btn-success btn-sm" href="interfaces_groups_edit.php" role="button">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add");?>
	</a>
</nav>

<div class="infoblock">
	<?=print_info_box(gettext('Interface Groups allow you to setup rules for multiple interfaces without duplicating the rules.<br />' .
					   'If you remove members from an interface group, the group rules are no longer applicable to that interface.'), 'info')?>

</div>
<?php

include("foot.inc");
