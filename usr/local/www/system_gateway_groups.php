<?php
/* $Id$ */
/*
	system_gateway_groups.php
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
##|*IDENT=page-system-gatewaygroups
##|*NAME=System: Gateway Groups page
##|*DESCR=Allow access to the 'System: Gateway Groups' page.
##|*MATCH=system_gateway_groups.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("openvpn.inc");

if (!is_array($config['gateways']['gateway_group']))
	$config['gateways']['gateway_group'] = array();

$a_gateway_groups = &$config['gateways']['gateway_group'];
$a_gateways = &$config['gateways']['gateway_item'];
$changedesc = gettext("Gateway Groups") . ": ";

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {

		$retval = 0;

		$retval = system_routing_configure();
		send_multiple_events(array("service reload dyndnsall", "service reload ipsecdns", "filter reload"));

		/* reconfigure our gateway monitor */
		setup_gateways_monitor();

		$savemsg = get_std_save_message($retval);
		if ($retval == 0)
			clear_subsystem_dirty('staticroutes');

		foreach ($a_gateway_groups as $gateway_group) {
			$gw_subsystem = 'gwgroup.' . $gateway_group['name'];
			if (is_subsystem_dirty($gw_subsystem)) {
				openvpn_resync_gwgroup($gateway_group['name']);
				clear_subsystem_dirty($gw_subsystem);
			}
		}
	}
}

if ($_GET['act'] == "del") {
	if ($a_gateway_groups[$_GET['id']]) {
		$changedesc .= gettext("removed gateway group") . " {$_GET['id']}";
		foreach ($config['filter']['rule'] as $idx => $rule) {
			if ($rule['gateway'] == $a_gateway_groups[$_GET['id']]['name'])
				unset($config['filter']['rule'][$idx]['gateway']);
		}
		unset($a_gateway_groups[$_GET['id']]);
		write_config($changedesc);
		mark_subsystem_dirty('staticroutes');
		header("Location: system_gateway_groups.php");
		exit;
	}
}

$pgtitle = array(gettext("System"),gettext("Gateway Groups"));
$shortcut_section = "gateway-groups";

include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="system_gateway_groups.php" method="post">
<input type="hidden" name="y1" value="1" />
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('staticroutes')): ?><p>
<?php print_info_box_np(sprintf(gettext("The gateway configuration has been changed.%sYou must apply the changes in order for them to take effect."), "<br/>"));?><br/></p>
<?php endif; ?>
	<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="system groups">
		<tr>
		  <td>
<?php
			$tab_array = array();
			$tab_array[0] = array(gettext("Gateways"), false, "system_gateways.php");
			$tab_array[1] = array(gettext("Routes"), false, "system_routes.php");
			$tab_array[2] = array(gettext("Groups"), true, "system_gateway_groups.php");
			display_top_tabs($tab_array);
?>
</td></tr>
 <tr>
   <td>
	<div id="mainarea">
             <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="main area">
		<thead>
                <tr>
                  <td width="15%" class="listhdrr"><?=gettext("Group Name");?></td>
                  <td width="15%" class="listhdrr"><?=gettext("Gateways");?></td>
                  <td width="20%" class="listhdrr"><?=gettext("Priority");?></td>
                  <td width="30%" class="listhdr"><?=gettext("Description");?></td>
                  <td width="10%" class="list">
			<table border="0" cellspacing="0" cellpadding="1" summary="icons">
			   <tr>
				<td width="17"></td>
				<td><a href="system_gateway_groups_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="edit" /></a></td>
			   </tr>
			</table>
		  </td>
		</tr>
		</thead>
		<tfoot>
                  <tr><td class="list" colspan="4"></td>
                  <td class="list">
			<table border="0" cellspacing="0" cellpadding="1" summary="edit">
			   <tr>
				<td width="17"></td>
				<td><a href="system_gateway_groups_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="edit" /></a></td>
			   </tr>
		                    </table>
				  </td>
		                </tr>
		</tfoot>
		<tbody>
			  <?php $i = 0; foreach ($a_gateway_groups as $gateway_group): ?>
                <tr>
                  <td class="listlr" ondblclick="document.location='system_gateway_groups_edit.php?id=<?=$i;?>';">
                    <?php
			echo $gateway_group['name'];
		?>
                  </td>
                  <td class="listr" ondblclick="document.location='system_gateway_groups_edit.php?id=<?=$i;?>';">
                    <?php
			foreach($gateway_group['item'] as $item) {
				$itemsplit = explode("|", $item);
				echo htmlspecialchars(strtoupper($itemsplit[0])) . "<br/>\n";
			}
		    ?>
                  </td>
                  <td class="listr" ondblclick="document.location='system_gateway_groups_edit.php?id=<?=$i;?>';">
		    <?php
			foreach($gateway_group['item'] as $item) {
				$itemsplit = explode("|", $item);
				echo "Tier ". htmlspecialchars($itemsplit[1]) . "<br/>\n";
			}
		    ?>
                  </td>
                  <td class="listbg" ondblclick="document.location='system_gateway_groups_edit.php?id=<?=$i;?>';">
				<?=htmlspecialchars($gateway_group['descr']);?>&nbsp;
                  </td>
                  <td valign="middle" class="list nowrap">
			<table border="0" cellspacing="0" cellpadding="1" summary="edit">
			   <tr>
				<td><a href="system_gateway_groups_edit.php?id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" alt="edit" /></a>
				<td><a href="system_gateway_groups.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this gateway group?");?>')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" alt="delete" /></a></td>
			   </tr>
			   <tr>
				<td width="17"></td>
				<td><a href="system_gateway_groups_edit.php?dup=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="duplicate" /></a></td>
			   </tr>
			</table>

		</tr>
			  <?php $i++; endforeach; ?>
                <tr><td>&nbsp;</td></tr>
		</tbody>
			</table>
			</div>
			</td>
		  </tr>
		</table>
            </form>
	<p><b><?=gettext("Note:");?></b>  <?=gettext("Remember to use these Gateway Groups in firewall rules in order to enable load balancing, failover, or policy-based routing. Without rules directing traffic into the Gateway Groups, they will not be used.");?></p>
<?php include("fend.inc"); ?>
</body>
</html>
