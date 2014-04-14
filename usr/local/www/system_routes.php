<?php
/* $Id$ */
/*
	system_routes.php
	part of m0n0wall (http://m0n0.ch/wall)

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
	pfSense_MODULE:	routing
*/

##|+PRIV
##|*IDENT=page-system-staticroutes
##|*NAME=System: Static Routes page
##|*DESCR=Allow access to the 'System: Static Routes' page.
##|*MATCH=system_routes.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

if (!is_array($config['staticroutes']['route']))
	$config['staticroutes']['route'] = array();

$a_routes = &$config['staticroutes']['route'];
$a_gateways = return_gateways_array(true, true, true);
$changedesc_prefix = gettext("Static Routes") . ": ";

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {

		$retval = 0;

		if (file_exists("{$g['tmp_path']}/.system_routes.apply")) {
			$toapplylist = unserialize(file_get_contents("{$g['tmp_path']}/.system_routes.apply"));
			foreach ($toapplylist as $toapply)
				mwexec("{$toapply}");

			@unlink("{$g['tmp_path']}/.system_routes.apply");
		}

		$retval = system_routing_configure();
		$retval |= filter_configure();
		/* reconfigure our gateway monitor */
		setup_gateways_monitor();

		$savemsg = get_std_save_message($retval);
		if ($retval == 0)
			clear_subsystem_dirty('staticroutes');
	}
}

function delete_static_route($id) {
	global $config, $a_routes, $changedesc_prefix;

	if (!isset($a_routes[$id]))
		return;

	$targets = array();
	if (is_alias($a_routes[$id]['network'])) {
		foreach (filter_expand_alias_array($a_routes[$id]['network']) as $tgt) {
			if (is_ipaddrv4($tgt))
				$tgt .= "/32";
			else if (is_ipaddrv6($tgt))
				$tgt .= "/128";
			if (!is_subnet($tgt))
				continue;
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
		if(isset($a_routes[$_GET['id']]['disabled'])) {
			unset($a_routes[$_GET['id']]['disabled']);
			$changedesc = $changedesc_prefix . gettext("enabled route to") . " " . $a_routes[$id]['network'];
		} else {
			delete_static_route($_GET['id']);
			$a_routes[$_GET['id']]['disabled'] = true;
			$changedesc = $changedesc_prefix . gettext("disabled route to") . " " . $a_routes[$id]['network'];
		}

		if (write_config($changedesc))
			mark_subsystem_dirty('staticroutes');
		header("Location: system_routes.php");
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
	/* move selected routes before this route */
	if (isset($movebtn) && is_array($_POST['route']) && count($_POST['route'])) {
		$a_routes_new = array();

		/* copy all routes < $movebtn and not selected */
		for ($i = 0; $i < $movebtn; $i++) {
			if (!in_array($i, $_POST['route']))
				$a_routes_new[] = $a_routes[$i];
		}

		/* copy all selected routes */
		for ($i = 0; $i < count($a_routes); $i++) {
			if ($i == $movebtn)
				continue;
			if (in_array($i, $_POST['route']))
				$a_routes_new[] = $a_routes[$i];
		}

		/* copy $movebtn route */
		if ($movebtn < count($a_routes))
			$a_routes_new[] = $a_routes[$movebtn];

		/* copy all routes > $movebtn and not selected */
		for ($i = $movebtn+1; $i < count($a_routes); $i++) {
			if (!in_array($i, $_POST['route']))
				$a_routes_new[] = $a_routes[$i];
		}
		if (count($a_routes_new) > 0)
			$a_routes = $a_routes_new;

		if (write_config())
			mark_subsystem_dirty('staticroutes');
		header("Location: system_routes.php");
		exit;
	}
}

$pgtitle = array(gettext("System"),gettext("Static Routes"));
$shortcut_section = "routing";

include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="system_routes.php" method="post">
<script type="text/javascript" src="/javascript/row_toggle.js"></script>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('staticroutes')): ?><p>
<?php print_info_box_np(sprintf(gettext("The static route configuration has been changed.%sYou must apply the changes in order for them to take effect."), "<br />"));?><br /></p>
<?php endif; ?>

<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="system routes">
	<tr>
		<td>
		<?php
			$tab_array = array();
			$tab_array[0] = array(gettext("Gateways"), false, "system_gateways.php");
			$tab_array[1] = array(gettext("Routes"), true, "system_routes.php");
			$tab_array[2] = array(gettext("Groups"), false, "system_gateway_groups.php");
			display_top_tabs($tab_array);
		?>
		</td>
	</tr>
	<tr>
		<td>
			<div id="mainarea">
				<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="main area">
					<tr id="frheader">
						<td width="2%" class="list">&nbsp;</td>
						<td width="2%" class="list">&nbsp;</td>
						<td width="22%" class="listhdrr"><?=gettext("Network");?></td>
						<td width="20%" class="listhdrr"><?=gettext("Gateway");?></td>
						<td width="15%" class="listhdrr"><?=gettext("Interface");?></td>
						<td width="29%" class="listhdr"><?=gettext("Description");?></td>
						<td width="10%" class="list">
							<table border="0" cellspacing="0" cellpadding="1" summary="add">
								<tr>
									<td width="17"></td>
									<td><a href="system_routes_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="add" /></a></td>
								</tr>
							</table>
						</td>
					</tr>
					<?php $i = 0; foreach ($a_routes as $route): ?>
					<tr valign="top" id="fr<?=$i;?>">
					<?php
						$iconfn = "pass";
						if (isset($route['disabled'])) {
							$textss = "<span class=\"gray\">";
							$textse = "</span>";
							$iconfn .= "_d";
						} else
							$textss = $textse = "";
					?>
						<td class="listt">
							<input type="checkbox" id="frc<?=$i;?>" name="route[]" value="<?=$i;?>" onclick="fr_bgcolor('<?=$i;?>')" style="margin: 0; padding: 0; width: 15px; height: 15px;" />
						</td>
						<td class="listt" align="center">
							<a href="?act=toggle&amp;id=<?=$i;?>">
								<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_<?=$iconfn;?>.gif" width="11" height="11" border="0"
									title="<?=gettext("click to toggle enabled/disabled status");?>" alt="icon" />
							</a>
						</td>
						<td class="listlr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='system_routes_edit.php?id=<?=$i;?>';">
							<?=$textss;?><?=strtolower($route['network']);?><?=$textse;?>
						</td>
						<td class="listr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='system_routes_edit.php?id=<?=$i;?>';">
							<?=$textss;?>
							<?php
								echo htmlentities($a_gateways[$route['gateway']]['name']) . " - " . htmlentities($a_gateways[$route['gateway']]['gateway']);
							?>
							<?=$textse;?>
						</td>
						<td class="listr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='system_routes_edit.php?id=<?=$i;?>';">
							<?=$textss;?>
							<?php
								echo convert_friendly_interface_to_friendly_descr($a_gateways[$route['gateway']]['friendlyiface']) . " ";
							?>
							<?=$textse;?>
						</td>
						<td class="listbg" onclick="fr_toggle(<?=$i;?>)" ondblclick="document.location='system_routes_edit.php?id=<?=$i;?>';">
							<?=$textss;?><?=htmlspecialchars($route['descr']);?>&nbsp;<?=$textse;?>
						</td>
						<td class="list nowrap" valign="middle">
							<table border="0" cellspacing="0" cellpadding="1" summary="move">
								<tr>
									<td>
										<input onmouseover="fr_insline(<?=$i;?>, true)" onmouseout="fr_insline(<?=$i;?>, false)" name="move_<?=$i;?>"
											src="/themes/<?= $g['theme']; ?>/images/icons/icon_left.gif"
											title="<?=gettext("move selected rules before this rule");?>"
											type="image" style="height:17;width:17;border:0" />
									</td>
									<td>
										<a href="system_routes_edit.php?id=<?=$i;?>">
											<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" title="<?=gettext("edit rule");?>" alt="edit" />
										</a>
									</td>
								</tr>
								<tr>
									<td align="center" valign="middle">
										<a href="system_routes.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this rule?");?>')">
											<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" title="<?=gettext("delete rule");?>" alt="delete" />
										</a>
									</td>
									<td>
										<a href="system_routes_edit.php?dup=<?=$i;?>">
											<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("add a new rule based on this one");?>" width="17" height="17" border="0" alt="duplicate" />
										</a>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<?php $i++; endforeach; ?>
					<tr>
						<td class="list" colspan="6"></td>
						<td class="list nowrap" valign="middle">
							<table border="0" cellspacing="0" cellpadding="1" summary="edit">
								<tr>
									<td>
<?php
									if ($i == 0):
?>
										<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_left_d.gif" width="17" height="17"
											title="<?=gettext("move selected routes to end");?>" border="0" alt="move" />
<?php
									else:
?>
										<input name="move_<?=$i;?>" type="image" src="/themes/<?= $g['theme']; ?>/images/icons/icon_left.gif"
											style="width:17;height:17;border:0" title="<?=gettext("move selected routes to end");?>" />
<?php
									endif;
?>
									</td>
									<td>
										<a href="system_routes_edit.php">
											<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"
											title="<?=gettext("add new route");?>" alt="add" />
										</a>
									</td>
								</tr>
								<tr>
									<td>
<?php
									if ($i == 0):
?>
										<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x_d.gif" width="17" height="17"
											title="<?=gettext("delete selected rules");?>" border="0" alt="delete" />
<?php
									else:
?>
										<input name="del" type="image" src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif"
											style="width:17;height:17" title="<?=gettext("delete selected routes");?>"
											onclick="return confirm('<?=gettext("Do you really want to delete the selected routes?");?>')" />
<?php
									endif;
?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>
			</div>
		</td>
	</tr>
</table>
</form>
<p><b><?=gettext("Note:");?></b>  <?=gettext("Do not enter static routes for networks assigned on any interface of this firewall.  Static routes are only used for networks reachable via a different router, and not reachable via your default gateway.");?></p>
<?php include("fend.inc"); ?>
</body>
</html>
