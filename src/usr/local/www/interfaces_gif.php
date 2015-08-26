<?php
/* $Id$ */
/*
	interfaces_gif.php

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
	pfSense_BUILDER_BINARIES:	/sbin/ifconfig
	pfSense_MODULE: interfaces
*/

##|+PRIV
##|*IDENT=page-interfaces-gif
##|*NAME=Interfaces: GIF page
##|*DESCR=Allow access to the 'Interfaces: GIF' page.
##|*MATCH=interfaces_gif.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['gifs']['gif'])) {
	$config['gifs']['gif'] = array();
}

$a_gifs = &$config['gifs']['gif'] ;

function gif_inuse($num) {
	global $config, $a_gifs;

	$iflist = get_configured_interface_list(false, true);
	foreach ($iflist as $if) {
		if ($config['interfaces'][$if]['if'] == $a_gifs[$num]['gifif']) {
			return true;
		}
	}

	return false;
}

if ($_GET['act'] == "del") {
	if (!isset($_GET['id'])) {
		$input_errors[] = gettext("Wrong parameters supplied");
	} else if (empty($a_gifs[$_GET['id']])) {
		$input_errors[] = gettext("Wrong index supplied");
	/* check if still in use */
	} else if (gif_inuse($_GET['id'])) {
		$input_errors[] = gettext("This gif TUNNEL cannot be deleted because it is still being used as an interface.");
	} else {
		mwexec("/sbin/ifconfig " . $a_gifs[$_GET['id']]['gifif'] . " destroy");
		unset($a_gifs[$_GET['id']]);

		write_config();

		header("Location: interfaces_gif.php");
		exit;
	}
}

$pgtitle = array(gettext("Interfaces"), gettext("GIF"));
$shortcut_section = "interfaces";
include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

$tab_array = array();
$tab_array[] = array(gettext("Interface assignments"), false, "interfaces_assign.php");
$tab_array[] = array(gettext("Interface Groups"), false, "interfaces_groups.php");
$tab_array[] = array(gettext("Wireless"), false, "interfaces_wireless.php");
$tab_array[] = array(gettext("VLANs"), false, "interfaces_vlan.php");
$tab_array[] = array(gettext("QinQs"), false, "interfaces_qinq.php");
$tab_array[] = array(gettext("PPPs"), false, "interfaces_ppps.php");
$tab_array[] = array(gettext("GRE"), false, "interfaces_gre.php");
$tab_array[] = array(gettext("GIF"), true, "interfaces_gif.php");
$tab_array[] = array(gettext("Bridges"), false, "interfaces_bridge.php");
$tab_array[] = array(gettext("LAGG"), false, "interfaces_lagg.php");
display_top_tabs($tab_array);
?>

<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
		<thead>
			<tr>
				<th><?=gettext("Interface"); ?></th>
				<th><?=gettext("Tunnel to &hellip;"); ?></th>
				<th><?=gettext("Description"); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
<?php foreach ($a_gifs as $i => $gif): ?>
			<tr>
				<td>
					<?=htmlspecialchars(convert_friendly_interface_to_friendly_descr($gif['if']))?>
				</td>
				<td>
					<?=htmlspecialchars($gif['remote-addr'])?>
				</td>
				<td>
					<?=htmlspecialchars($gif['descr'])?>
				</td>
				<td>
					<a href="interfaces_gif_edit.php?id=<?=$i?>" class="btn btn-default btn-xs"><?=gettext("Edit")?></a>
					<a href="interfaces_gif.php?act=del&amp;id=<?=$i?>" class="btn btn-danger btn-xs"><?=gettext("Delete")?></a>
				</td>
			</tr>
<?php endforeach; ?>
		</tbody>
	</table>
</div>

<nav class="action-buttons">
	<a href="interfaces_gif_edit.php" class="btn btn-success">
		<?=gettext("Add")?>
	</a>
</nav>

<?php include("foot.inc");