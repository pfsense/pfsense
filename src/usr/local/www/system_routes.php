<?php
/*
	system_routes.php
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
##|*IDENT=page-system-staticroutes
##|*NAME=System: Static Routes
##|*DESCR=Allow access to the 'System: Static Routes' page.
##|*MATCH=system_routes.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

if (!is_array($config['staticroutes']['route'])) {
	$config['staticroutes']['route'] = array();
}

$a_routes = &$config['staticroutes']['route'];
$a_gateways = return_gateways_array(true, true, true);
$changedesc_prefix = gettext("Static Routes") . ": ";
unset($input_errors);

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {

		$retval = 0;

		if (file_exists("{$g['tmp_path']}/.system_routes.apply")) {
			$toapplylist = unserialize(file_get_contents("{$g['tmp_path']}/.system_routes.apply"));
			foreach ($toapplylist as $toapply) {
				mwexec("{$toapply}");
			}

			@unlink("{$g['tmp_path']}/.system_routes.apply");
		}

		$retval = system_routing_configure();
		$retval |= filter_configure();
		/* reconfigure our gateway monitor */
		setup_gateways_monitor();

		$savemsg = get_std_save_message($retval);
		if ($retval == 0) {
			clear_subsystem_dirty('staticroutes');
		}
	}
}

function delete_static_route($id) {
	global $config, $a_routes, $changedesc_prefix;

	if (!isset($a_routes[$id])) {
		return;
	}

	$targets = array();
	if (is_alias($a_routes[$id]['network'])) {
		foreach (filter_expand_alias_array($a_routes[$id]['network']) as $tgt) {
			if (is_ipaddrv4($tgt)) {
				$tgt .= "/32";
			} else if (is_ipaddrv6($tgt)) {
				$tgt .= "/128";
			}
			if (!is_subnet($tgt)) {
				continue;
			}
			$targets[] = $tgt;
		}
	} else {
		$targets[] = $a_routes[$id]['network'];
	}

	foreach ($targets as $tgt) {
		$family = (is_subnetv6($tgt) ? "-inet6" : "-inet");
		mwexec("/sbin/route delete {$family} " . escapeshellarg($tgt));
	}

	unset($targets);
}

if ($_GET['act'] == "del") {
	if ($a_routes[$_GET['id']]) {
		$changedesc = $changedesc_prefix . gettext("removed route to") . " " . $a_routes[$_GET['id']]['network'];
		delete_static_route($_GET['id']);
		unset($a_routes[$_GET['id']]);
		write_config($changedesc);
		header("Location: system_routes.php");
		exit;
	}
}

if (isset($_POST['del_x'])) {
	/* delete selected routes */
	if (is_array($_POST['route']) && count($_POST['route'])) {
		$changedesc = $changedesc_prefix . gettext("removed route to");
		foreach ($_POST['route'] as $routei) {
			$changedesc .= " " . $a_routes[$routei]['network'];
			delete_static_route($routei);
			unset($a_routes[$routei]);
		}
		write_config($changedesc);
		header("Location: system_routes.php");
		exit;
	}

} else if ($_GET['act'] == "toggle") {
	if ($a_routes[$_GET['id']]) {
		$do_update_config = true;
		if (isset($a_routes[$_GET['id']]['disabled'])) {
			// Do not enable a route whose gateway is disabled
			if (isset($a_gateways[$a_routes[$_GET['id']]['gateway']]['disabled'])) {
				$do_update_config = false;
				$input_errors[] = $changedesc_prefix . gettext("gateway is disabled, cannot enable route to") . " " . $a_routes[$_GET['id']]['network'];
			} else {
				unset($a_routes[$_GET['id']]['disabled']);
				$changedesc = $changedesc_prefix . gettext("enabled route to") . " " . $a_routes[$_GET['id']]['network'];
			}
		} else {
			delete_static_route($_GET['id']);
			$a_routes[$_GET['id']]['disabled'] = true;
			$changedesc = $changedesc_prefix . gettext("disabled route to") . " " . $a_routes[$_GET['id']]['network'];
		}

		if ($do_update_config) {
			if (write_config($changedesc)) {
				mark_subsystem_dirty('staticroutes');
			}
			header("Location: system_routes.php");
			exit;
		}
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
	/* move selected routes before this route */
	if (isset($movebtn) && is_array($_POST['route']) && count($_POST['route'])) {
		$a_routes_new = array();

		/* copy all routes < $movebtn and not selected */
		for ($i = 0; $i < $movebtn; $i++) {
			if (!in_array($i, $_POST['route'])) {
				$a_routes_new[] = $a_routes[$i];
			}
		}

		/* copy all selected routes */
		for ($i = 0; $i < count($a_routes); $i++) {
			if ($i == $movebtn) {
				continue;
			}
			if (in_array($i, $_POST['route'])) {
				$a_routes_new[] = $a_routes[$i];
			}
		}

		/* copy $movebtn route */
		if ($movebtn < count($a_routes)) {
			$a_routes_new[] = $a_routes[$movebtn];
		}

		/* copy all routes > $movebtn and not selected */
		for ($i = $movebtn+1; $i < count($a_routes); $i++) {
			if (!in_array($i, $_POST['route'])) {
				$a_routes_new[] = $a_routes[$i];
			}
		}
		if (count($a_routes_new) > 0) {
			$a_routes = $a_routes_new;
		}

		if (write_config()) {
			mark_subsystem_dirty('staticroutes');
		}
		header("Location: system_routes.php");
		exit;
	}
}

$pgtitle = array(gettext("System"), gettext("Routing"), gettext("Static Routes"));
$shortcut_section = "routing";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg);
}
if (is_subsystem_dirty('staticroutes')) {
	print_info_box_np(gettext("The static route configuration has been changed.") . "<br />" . gettext("You must apply the changes in order for them to take effect."));
}

$tab_array = array();
$tab_array[0] = array(gettext("Gateways"), false, "system_gateways.php");
$tab_array[1] = array(gettext("Static Routes"), true, "system_routes.php");
$tab_array[2] = array(gettext("Gateway Groups"), false, "system_gateway_groups.php");
display_top_tabs($tab_array);

?>
<table class="table">
<thead>
	<tr>
		<th></th>
		<th><?=gettext("Network")?></th>
		<th><?=gettext("Gateway")?></th>
		<th><?=gettext("Interface")?></th>
		<th><?=gettext("Description")?></th>
		<th><?=gettext("Actions")?></th>
	</tr>
</thead>
<tbody>
<?php
foreach ($a_routes as $i => $route):
	if (isset($route['disabled'])) {
		$icon = 'fa-ban';
	} else {
		$icon = 'fa-check-circle-o';
	}
?>
	<tr<?=($icon != 'fa-check-circle-o')? ' class="disabled"' : ''?>>
		<td><i class="fa <?=$icon?>"></i></td>
		<td>
			<?=strtolower($route['network'])?>
		</td>
		<td>
			<?=htmlentities($a_gateways[$route['gateway']]['name']) . " - " . htmlentities($a_gateways[$route['gateway']]['gateway'])?>
		</td>
		<td>
			<?=convert_friendly_interface_to_friendly_descr($a_gateways[$route['gateway']]['friendlyiface'])?>
		</td>
		<td>
			<?=htmlspecialchars($route['descr'])?>
		</td>
		<td>
			<a href="system_routes_edit.php?id=<?=$i?>" class="fa fa-pencil" title="<?=gettext('Edit')?>"></a>

			<a href="system_routes_edit.php?dup=<?=$i?>" class="fa fa-clone" title="<?=gettext('Copy')?>"></a>

	<?php if (isset($route['disabled'])) {
	?>
			<a href="?act=toggle&amp;id=<?=$i?>" class="fa fa-check-square-o" title="<?=gettext('Enable')?>"></a>
	<?php } else {
	?>
			<a href="?act=toggle&amp;id=<?=$i?>" class="fa fa-ban" title="<?=gettext('Disable')?>"></a>
	<?php }
	?>
			<a href="system_routes.php?act=del&amp;id=<?=$i?>" class="fa fa-trash" title="<?=gettext('Delete')?>"></a>

		</td>
	</tr>
<?php endforeach; ?>
</table>

<nav class="action-buttons">
	<a href="system_routes_edit.php" role="button" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>
<?php

include("foot.inc");
