<?php
/* $Id$ */
/*
	interfaces_bridge.php

	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	Copyright (C) 2008 Ermal Luçi
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
	pfSense_BUILDER_BINARIES:	/bin/rm
	pfSense_MODULE: interfaces_assign
*/

##|+PRIV
##|*IDENT=page-interfaces-bridge
##|*NAME=Interfaces: Bridge page
##|*DESCR=Allow access to the 'Interfaces: Bridge' page.
##|*MATCH=interfaces_bridge.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['bridges']['bridged'])) {
	$config['bridges']['bridged'] = array();
}

$a_bridges = &$config['bridges']['bridged'] ;

function bridge_inuse($num) {
	global $config, $a_bridges;

	$iflist = get_configured_interface_list(false, true);

	foreach ($iflist as $if) {
		if ($config['interfaces'][$if]['if'] == $a_bridges[$num]['bridgeif']) {
			return true;
		}
	}

	return false;
}

if ($_GET['act'] == "del") {
	if (!isset($_GET['id'])) {
		$input_errors[] = gettext("Wrong parameters supplied");
	} else if (empty($a_bridges[$_GET['id']])) {
		$input_errors[] = gettext("Wrong index supplied");
	/* check if still in use */
	} else if (bridge_inuse($_GET['id'])) {
		$input_errors[] = gettext("This bridge cannot be deleted because it is assigned as an interface.");
	} else {
		if (!does_interface_exist($a_bridges[$_GET['id']]['bridgeif'])) {
			log_error("Bridge interface does not exist, skipping ifconfig destroy.");
		} else {
			mwexec("/sbin/ifconfig " . $a_bridges[$_GET['id']]['bridgeif'] . " destroy");
		}

		unset($a_bridges[$_GET['id']]);

		write_config();

		header("Location: interfaces_bridge.php");
		exit;
	}
}

$pgtitle = array(gettext("Interfaces"),gettext("Bridge"));
$shortcut_section = "interfaces";
include("head.inc");
if ($input_errors)
	print_input_errors($input_errors); ?>

<?php
$tab_array = array();
$tab_array[] = array(gettext("Interface assignments"), false, "interfaces_assign.php");
$tab_array[] = array(gettext("Interface Groups"), false, "interfaces_groups.php");
$tab_array[] = array(gettext("Wireless"), false, "interfaces_wireless.php");
$tab_array[] = array(gettext("VLANs"), false, "interfaces_vlan.php");
$tab_array[] = array(gettext("QinQs"), false, "interfaces_qinq.php");
$tab_array[] = array(gettext("PPPs"), false, "interfaces_ppps.php");
$tab_array[] = array(gettext("GRE"), false, "interfaces_gre.php");
$tab_array[] = array(gettext("GIF"), false, "interfaces_gif.php");
$tab_array[] = array(gettext("Bridges"), true, "interfaces_bridge.php");
$tab_array[] = array(gettext("LAGG"), false, "interfaces_lagg.php");
display_top_tabs($tab_array);
?>

<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
		<thead>
			<tr>
			  <th><?=gettext("Interface"); ?></th>
			  <th><?=gettext("Members"); ?></th>
			  <th><?=gettext("Description"); ?></th>
			  <th></th>
			</tr>
		</thead>
		<tbody>
<?php

$i = 0;
$ifdescrs = get_configured_interface_with_descr();

foreach ($a_bridges as $bridge) {
?>
			<tr>
				<td>
					<?=htmlspecialchars(strtoupper($bridge['bridgeif']))?>
				</td>
				<td>
<?php
	$members = explode(',', $bridge['members']);
	$j = 0;
	foreach ($members as $member) {
		if (isset($ifdescrs[$member])) {
			echo $ifdescrs[$member];
			$j++;
		}
		if ($j > 0 && $j < count($members))
			echo ", ";
	}
?>
				</td>
				<td>
					<?=htmlspecialchars($bridge['descr'])?>
				</td>
				<td>
					<a href="interfaces_bridge_edit.php?id=<?=$i?>" class="btn btn-default btn-xs"><?=gettext("Edit")?></a>
					<a href="interfaces_bridge.php?act=del&amp;id=<?=$i?>" class="btn btn-danger btn-xs"><?=gettext("Delete")?></a>
				</td>
			</tr>
<?php
	$i++;
}
?>
		</tbody>
	</table>

	<nav class="action-buttons">
		<a href="interfaces_bridge_edit.php" class="btn btn-success"><?=gettext("Add")?></a>
	</nav>

</div>

<?php include("foot.inc");