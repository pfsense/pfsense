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

$a_gateways = return_gateways_array(true);
$a_gateways_arr = array();
foreach ($a_gateways as $gw)
	$a_gateways_arr[] = $gw;
$a_gateways = $a_gateways_arr;

if (!is_array($config['gateways']['gateway_item']))
        $config['gateways']['gateway_item'] = array();

$a_gateway_item = &$config['gateways']['gateway_item'];

$changedesc = "Gateways: ";

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

if ($_GET['act'] == "del") {
	if ($a_gateways[$_GET['id']]) {
		/* remove the real entry */
		$realid = $a_gateways[$_GET['id']]['attribute'];
		$remove = true;
		if (is_array($config['gateways']['gateway_group'])) {
			foreach ($config['gateways']['gateway_group'] as $group) {
				foreach ($group['item'] as $item) {
					$items = explode("|", $item);
					if ($items[0] == $a_gateways[$_GET['id']]['name']) {
						$input_errors[] = "Gateway cannot be deleted because it is in use on Gateway Group '{$group['name']}'";
						$remove = false;
						break;
					}
						
				}
			}
		}
		if (is_array($config['staticroutes']['route'])) {
			foreach ($config['staticroutes']['route'] as $route) {
				if ($route['gateway'] == $a_gateways[$_GET['id']]['name']) {
					$input_errors[] = "Gateway cannot be deleted because it is in use on Static Routes '{$route['network']}'";
						$remove = false;
					break;
				}
			}
		}
		if ($remove == true) {
			/* NOTE: Cleanup static routes for the monitor ip if any */
                        if (!empty($a_gateways[$_GET['id']]['monitor']) && $a_gateways[$_GET['id']]['monitor'] != "dynamic" && is_ipaddr($a_gateways[$_GET['id']]['monitor']) &&
                            $a_gateways[$_GET['id']]['monitor'] != $a_gateways[$_GET['id']]['monitor'] && $a_gateways[$_GET['id']]['gateway'] != $a_gateways[$_GET['id']]['monitor']) {
                                if (is_ipaddrv4($a_gateways[$_GET['id']]['monitor']))
                                        mwexec("/sbin/route delete " . escapeshellarg($a_gateways[$_GET['id']]['monitor']));
                                else
                                        mwexec("/sbin/route delete -inet6 " . escapeshellarg($a_gateways[$_GET['id']]['monitor']));
                        }

			if ($config['interfaces'][$a_gateways[$_GET['id']]['friendlyiface']]['gateway'] == $a_gateways[$_GET['id']]['name'])
				unset($config['interfaces'][$a_gateways[$_GET['id']]['friendlyiface']]['gateway']);
			$changedesc .= "removed gateway {$realid}";
			unset($a_gateway_item[$realid]);
			write_config($changedesc);
			mark_subsystem_dirty('staticroutes');
			header("Location: system_gateways.php");
			exit;
		}
	}
}


$pgtitle = array(gettext("System"),gettext("Gateways"));
$shortcut_section = "gateways";

include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<form action="system_gateways.php" method="post">
<input type="hidden" name="y1" value="1" />
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('staticroutes')): ?><p>
<?php print_info_box_np(gettext("The gateway configuration has been changed.") . "<br/>" . gettext("You must apply the changes in order for them to take effect."));?><br/></p>
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
</td></tr>
 <tr>
   <td>
	<div id="mainarea">
             <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="main area">
                <tr>
                  <td width="15%" class="listhdrr"><?=gettext("Name"); ?></td>
                  <td width="15%" class="listhdrr"><?=gettext("Interface"); ?></td>
                  <td width="20%" class="listhdrr"><?=gettext("Gateway"); ?></td>
                  <td width="20%" class="listhdrr"><?=gettext("Monitor IP"); ?></td>
                  <td width="30%" class="listhdr"><?=gettext("Description"); ?></td>
                  <td width="10%" class="list">
			<table border="0" cellspacing="0" cellpadding="1" summary="add">
			   <tr>
				<td width="17"></td>
				<td><a href="system_gateways_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="add" /></a></td>
			   </tr>
			</table>
		  </td>
		</tr>
			  <?php $i = 0; foreach ($a_gateways as $gateway): ?>
                <tr>
                  <td class="listlr" ondblclick="document.location='system_gateways_edit.php?id=<?=$i;?>';">
		<?php
			echo $gateway['name'];
			if(isset($gateway['defaultgw']))
				echo " <strong>(default)</strong>";
		?>
                  </td>
                  <td class="listr" ondblclick="document.location='system_gateways_edit.php?id=<?=$i;?>';">
		<?php
			echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($gateway['friendlyiface']));
		?>
                  </td>
                  <td class="listr" ondblclick="document.location='system_gateways_edit.php?id=<?=$i;?>';">
		<?php
			echo $gateway['gateway'] . " ";
		?>
                  </td>
                  <td class="listr" ondblclick="document.location='system_gateways_edit.php?id=<?=$i;?>';">
		<?php
			echo htmlspecialchars($gateway['monitor']) . " ";
		?>
                  </td>
		<?php if (is_numeric($gateway['attribute'])) : ?>
                  <td class="listbg" ondblclick="document.location='system_gateways_edit.php?id=<?=$i;?>';">
		<?php else : ?>
                  <td class="listbgns" ondblclick="document.location='system_gateways_edit.php?id=<?=$i;?>';">
		<?php endif; ?>
                    <?=htmlspecialchars($gateway['descr']);?>&nbsp;
                  </td>

                  <td valign="middle" class="list nowrap">
			<table border="0" cellspacing="0" cellpadding="1" summary="icons">
			   <tr>
				<td><a href="system_gateways_edit.php?id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" alt="edit" /></a></td>
				<?php
				if (is_numeric($gateway['attribute'])) : ?>
					<td>
						<a href="system_gateways.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this gateway?"); ?>')">
							<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" alt="delete" />
						</a>
					</td>
				<?php else : ?>
					<td width='17'></td>
				<?php endif; ?>
			   </tr>
			   <tr>
				<td width="17"></td>
				<td><a href="system_gateways_edit.php?dup=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="add" /></a></td>
			   </tr>
			</table>
			</td>
		</tr>
		  <?php $i++; endforeach; ?>
                <tr>
                  <td class="list" colspan="5"></td>
                  <td class="list">
			<table border="0" cellspacing="0" cellpadding="1" summary="edit">
			   <tr>
				<td width="17"></td>
				<td><a href="system_gateways_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="edit" /></a></td>
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
