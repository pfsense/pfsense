<?php
/* $Id$ */
/*
	interfaces_ppps.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
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
	pfSense_BUILDER_BINARIES:	/sbin/ifconfig
	pfSense_MODULE: interfaces
*/

##|+PRIV
##|*IDENT=page-interfaces-ppps
##|*NAME=Interfaces: ppps page
##|*DESCR=Allow access to the 'Interfaces: ppps' page.
##|*MATCH=interfaces_ppps.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");

function ppp_inuse($num) {
	global $config, $g;

	$iflist = get_configured_interface_list(false, true);
	if (!is_array($config['ppps']['ppp'])) {
		return false;
	}

	foreach ($iflist as $if) {
		if ($config['interfaces'][$if]['if'] == $config['ppps']['ppp'][$num]['if']) {
			return true;
		}
	}

	return false;
}

if ($_GET['act'] == "del") {
	/* check if still in use */
	if (ppp_inuse($_GET['id'])) {
		$input_errors[] = gettext("This point-to-point link cannot be deleted because it is still being used as an interface.");
	} elseif (is_array($config['ppps']['ppp']) && is_array($config['ppps']['ppp'][$_GET['id']])) {

		unset($config['ppps']['ppp'][$_GET['id']]['pppoe-reset-type']);
		handle_pppoe_reset($config['ppps']['ppp'][$_GET['id']]);
		unset($config['ppps']['ppp'][$_GET['id']]);
		write_config();
		header("Location: interfaces_ppps.php");
		exit;
	}
}

if (!is_array($config['ppps']['ppp'])) {
	$config['ppps']['ppp'] = array();
}
$a_ppps = $config['ppps']['ppp'];

$pgtitle = array(gettext("Interfaces"),gettext("PPPs"));
$shortcut_section = "interfaces";
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Interface assignments"), false, "interfaces_assign.php");
$tab_array[] = array(gettext("Interface Groups"), false, "interfaces_groups.php");
$tab_array[] = array(gettext("Wireless"), false, "interfaces_wireless.php");
$tab_array[] = array(gettext("VLANs"), false, "interfaces_vlan.php");
$tab_array[] = array(gettext("QinQs"), false, "interfaces_qinq.php");
$tab_array[] = array(gettext("PPPs"), true, "interfaces_ppps.php");
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
			  <th><?=gettext("Interface"); ?></th>
			  <th><?=gettext("Interface(s)/Port(s)"); ?></th>
			  <th><?=gettext("Description"); ?></th>
			  <th></th>
			</tr>
		</thead>
		<tbody>
<?php

$i = 0;

foreach ($a_ppps as $id => $ppp) {
?>
			<tr>
				<td>
					<?=htmlspecialchars($ppp['if'])?>
				</td>
				<td>
<?php
	$portlist = explode(",", $ppp['ports']);
	foreach ($portlist as $portid => $port) {
		if ($port != get_real_interface($port) && $ppp['type'] != "ppp")
			$portlist[$portid] = convert_friendly_interface_to_friendly_descr($port);
	}
					echo htmlspecialchars(implode(",", $portlist));
?>
				</td>
				<td>
					<?=htmlspecialchars($ppp['descr'])?>
				</td>
				<td>
					<a href="interfaces_ppps_edit.php?id=<?=$i?>" class="btn btn-default btn-xs"><?=gettext("Edit")?></a>
					<a href="interfaces_ppps.php?act=del&amp;id=<?=$i?>" class="btn btn-danger btn-xs"><?=gettext("Delete")?></a>
				</td>
			</tr>
<?php
	$i++;
}
?>
		</tbody>
	</table>

	<nav class="action-buttons">
	   <a href="interfaces_ppps_edit.php" class="btn btn-success"><?=gettext("Add")?></a>
	</nav>
</div>
<?php
include("foot.inc");

