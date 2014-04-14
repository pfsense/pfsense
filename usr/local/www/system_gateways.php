<?php
/* $Id$ */
/*
	system_gateways.php
	part of pfSense (https://www.pfsense.org)

	Copyright (C) 2010 Seth Mos <seth.mos@dds.nl>.
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
##|*IDENT=page-system-gateways
##|*NAME=System: Gateways page
##|*DESCR=Allow access to the 'System: Gateways' page.
##|*MATCH=system_gateways.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

$a_gateways = return_gateways_array(true, false, true);
$a_gateways_arr = array();
foreach ($a_gateways as $gw)
	$a_gateways_arr[] = $gw;
$a_gateways = $a_gateways_arr;

if (!is_array($config['gateways']['gateway_item']))
	$config['gateways']['gateway_item'] = array();

$a_gateway_item = &$config['gateways']['gateway_item'];

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {

		$retval = 0;

		$retval = system_routing_configure();
		$retval |= filter_configure();
		/* reconfigure our gateway monitor */
		setup_gateways_monitor();

		$savemsg = get_std_save_message($retval);
		if ($retval == 0)
			clear_subsystem_dirty('staticroutes');
	}
}

function can_delete_gateway_item($id) {
	global $config, $input_errors, $a_gateways;

	if (!isset($a_gateways[$id]))
		return false;

	if (is_array($config['gateways']['gateway_group'])) {
		foreach ($config['gateways']['gateway_group'] as $group) {
			foreach ($group['item'] as $item) {
				$items = explode("|", $item);
				if ($items[0] == $a_gateways[$id]['name']) {
					$input_errors[] = sprintf(gettext("Gateway '%s' cannot be deleted because it is in use on Gateway Group '%s'"), $a_gateways[$id]['name'], $group['name']);
					break;
				}
			}
		}
	}

	if (is_array($config['staticroutes']['route'])) {
		foreach ($config['staticroutes']['route'] as $route) {
			if ($route['gateway'] == $a_gateways[$id]['name']) {
				$input_errors[] = sprintf(gettext("Gateway '%s' cannot be deleted because it is in use on Static Route '%s'"), $a_gateways[$id]['name'], $route['network']);
				break;
			}
		}
	}

	if (isset($input_errors))
		return false;

	return true;
}

function delete_gateway_item($id) {
	global $config, $a_gateways;

	if (!isset($a_gateways[$id]))
		return;

	/* NOTE: Cleanup static routes for the monitor ip if any */
	if (!empty($a_gateways[$id]['monitor']) &&
	    $a_gateways[$id]['monitor'] != "dynamic" &&
	    is_ipaddr($a_gateways[$id]['monitor']) &&
	    $a_gateways[$id]['gateway'] != $a_gateways[$id]['monitor']) {
		if (is_ipaddrv4($a_gateways[$id]['monitor']))
			mwexec("/sbin/route delete " . escapeshellarg($a_gateways[$id]['monitor']));
		else
			mwexec("/sbin/route delete -inet6 " . escapeshellarg($a_gateways[$id]['monitor']));
	}

	if ($config['interfaces'][$a_gateways[$id]['friendlyiface']]['gateway'] == $a_gateways[$id]['name'])
		unset($config['interfaces'][$a_gateways[$id]['friendlyiface']]['gateway']);
	unset($config['gateways']['gateway_item'][$a_gateways[$id]['attribute']]);
}

unset($input_errors);
if ($_GET['act'] == "del") {
	if (can_delete_gateway_item($_GET['id'])) {
		$realid = $a_gateways[$_GET['id']]['attribute'];
		delete_gateway_item($_GET['id']);
		write_config("Gateways: removed gateway {$realid}");
		mark_subsystem_dirty('staticroutes');
		header("Location: system_gateways.php");
		exit;
	}
}

if (isset($_POST['del_x'])) {
	/* delete selected items */
	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		foreach ($_POST['rule'] as $rulei)
			if(!can_delete_gateway_item($rulei))
				break;

		if (!isset($input_errors)) {
			$items_deleted = "";
			foreach ($_POST['rule'] as $rulei) {
				delete_gateway_item($rulei);
				$items_deleted .= "{$rulei} ";
			}
			if (!empty($items_deleted)) {
				write_config("Gateways: removed gateways {$items_deleted}");
				mark_subsystem_dirty('staticroutes');
			}
			header("Location: system_gateways.php");
			exit;
		}
	}

} else if ($_GET['act'] == "toggle" && $a_gateways[$_GET['id']]) {
	$realid = $a_gateways[$_GET['id']]['attribute'];

	if(isset($a_gateway_item[$realid]['disabled']))
		unset($a_gateway_item[$realid]['disabled']);
	else
		$a_gateway_item[$realid]['disabled'] = true;

	if (write_config("Gateways: enable/disable"))
		mark_subsystem_dirty('staticroutes');

	header("Location: system_gateways.php");
	exit;
}

$pgtitle = array(gettext("System"),gettext("Gateways"));
$shortcut_section = "gateways";

include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<form action="system_gateways.php" method="post">
<script type="text/javascript" src="/javascript/row_toggle.js"></script>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('staticroutes')): ?><p>
<?php print_info_box_np(gettext("The gateway configuration has been changed.") . "<br />" . gettext("You must apply the changes in order for them to take effect."));?><br /></p>
<?php endif; ?>
	<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="system gatewyas">
		<tr>
			<td>
<?php
			$tab_array = array();
			$tab_array[0] = array(gettext("Gateways"), true, "system_gateways.php");
			$tab_array[1] = array(gettext("Routes"), false, "system_routes.php");
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
						<td width="15%" class="listhdrr"><?=gettext("Name"); ?></td>
						<td width="10%" class="listhdrr"><?=gettext("Interface"); ?></td>
						<td width="15%" class="listhdrr"><?=gettext("Gateway"); ?></td>
						<td width="15%" class="listhdrr"><?=gettext("Monitor IP"); ?></td>
						<td width="31%" class="listhdr"><?=gettext("Description"); ?></td>
						<td width="10%" class="list">
							<table border="0" cellspacing="0" cellpadding="1" summary="add">
								<tr>
									<td width="17"></td>
									<td>
										<a href="system_gateways_edit.php">
											<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="add" />
										</a>
									</td>
								</tr>
							</table>
						</td>
					</tr>
<?php
				$textse = "</span>";
				$i = 0;
				foreach ($a_gateways as $gateway):
					if (isset($gateway['disabled']) || isset($gateway['inactive'])) {
						$textss = "<span class=\"gray\">";
						$iconfn = "pass_d";
					} else {
						$textss = "<span>";
						$iconfn = "pass";
					}
?>
					<tr valign="top" id="fr<?=$i;?>">
						<td class="listt">
<?php
						if (is_numeric($gateway['attribute'])):
?>
							<input type="checkbox" id="frc<?=$i;?>" name="rule[]" value="<?=$i;?>" onclick="fr_bgcolor('<?=$i;?>')" style="margin: 0; padding: 0; width: 15px; height: 15px;" />
<?php
						else:
?>
							&nbsp;
<?php
						endif;
?>
						</td>
						<td class="listt" align="center">
<?php
						if (isset($gateway['inactive'])):
?>
							<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_reject_d.gif" width="11" height="11" border="0"
								title="<?=gettext("This gateway is inactive because interface is missing");?>" alt="icon" />
<?php
						elseif (is_numeric($gateway['attribute'])):
?>
							<a href="?act=toggle&amp;id=<?=$i;?>">
								<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_<?=$iconfn;?>.gif" width="11" height="11" border="0"
									title="<?=gettext("click to toggle enabled/disabled status");?>" alt="icon" />
							</a>
<?php
						else:
?>
							<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_<?=$iconfn;?>.gif" width="11" height="11" border="0"
								title="<?=gettext("click to toggle enabled/disabled status");?>" alt="icon" />
<?php
						endif;
?>
						</td>
						<td class="listlr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='system_gateways_edit.php?id=<?=$i;?>';">
<?php
							echo $textss;
							echo $gateway['name'];
							if(isset($gateway['defaultgw']))
								echo " <strong>(default)</strong>";
							echo $textse;
?>
						</td>
						<td class="listr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='system_gateways_edit.php?id=<?=$i;?>';">
<?php
							echo $textss;
							echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($gateway['friendlyiface']));
							echo $textse;
?>
						</td>
						<td class="listr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='system_gateways_edit.php?id=<?=$i;?>';">
<?php
							echo $textss;
							echo $gateway['gateway'] . " ";
							echo $textse;
?>
						</td>
						<td class="listr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='system_gateways_edit.php?id=<?=$i;?>';">
<?php
							echo $textss;
							echo htmlspecialchars($gateway['monitor']) . " ";
							echo $textse;
?>
						</td>
<?php
					if (is_numeric($gateway['attribute'])):
?>
						<td class="listbg" onclick="fr_toggle(<?=$i;?>)" ondblclick="document.location='system_gateways_edit.php?id=<?=$i;?>';">
<?php
					else:
?>
						<td class="listbgns" onclick="fr_toggle(<?=$i;?>)" ondblclick="document.location='system_gateways_edit.php?id=<?=$i;?>';">
<?php
					endif;
							echo $textss;
							echo htmlspecialchars($gateway['descr']) . "&nbsp;";
							echo $textse;
?>
						</td>
						<td valign="middle" class="list nowrap">
							<table border="0" cellspacing="0" cellpadding="1" summary="icons">
								<tr>
									<td>
										<a href="system_gateways_edit.php?id=<?=$i;?>">
											<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" alt="edit" />
										</a>
									</td>
<?php
								if (is_numeric($gateway['attribute'])):
?>
									<td>
										<a href="system_gateways.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this gateway?"); ?>')">
											<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" alt="delete" />
										</a>
									</td>
<?php
								else:
?>
									<td width='17'></td>
<?php
								endif;
?>
								</tr>
								<tr>
									<td width="17"></td>
									<td>
										<a href="system_gateways_edit.php?dup=<?=$i;?>">
											<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="add" />
										</a>
									</td>
								</tr>
							</table>
						</td>
					</tr>
<?php
					$i++;
				endforeach;
?>
					<tr>
						<td class="list" colspan="7"></td>
						<td class="list">
							<table border="0" cellspacing="0" cellpadding="1" summary="edit">
								<tr>
									<td>
<?php
									if ($i == 0):
?>
										<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x_d.gif" width="17" height="17"
											title="<?=gettext("delete selected items");?>" border="0" alt="delete" />
<?php
									else:
?>
										<input name="del" type="image" src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif"
											style="width:17;height:17" title="<?=gettext("delete selected items");?>"
											onclick="return confirm('<?=gettext("Do you really want to delete the selected gateway items?");?>')" />
<?php
									endif;
?>
									</td>
									<td>
										<a href="system_gateways_edit.php">
											<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="edit" />
										</a>
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
<?php include("fend.inc"); ?>
</body>
</html>
