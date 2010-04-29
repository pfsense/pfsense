<?php
/* $Id$ */
/*
	system_gateway_groups.php
	part of pfSense (http://pfsense.com)

	Copyright (C) 2007 Seth Mos <seth.mos@xs4all.nl>.
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

if (!is_array($config['gateways']['gateway_group']))
	$config['gateways']['gateway_group'] = array();

$a_gateway_groups = &$config['gateways']['gateway_group'];
$a_gateways = &$config['gateways']['gateway_item'];
$changedesc = "Gateway Groups: ";

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
	if ($a_gateway_groups[$_GET['id']]) {
		$changedesc .= "removed gateway group {$_GET['id']}";
		unset($a_gateway_groups[$_GET['id']]);
		write_config($changedesc);
		mark_subsystem_dirty('staticroutes');
		header("Location: system_gateway_groups.php");
		exit;
	}
}

$pgtitle = array("System","Gateway Groups");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="system_gateway_groups.php" method="post">
<input type="hidden" name="y1" value="1">
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('staticroutes')): ?><p>
<?php print_info_box_np("The gateway configuration has been changed.<br>You must apply the changes in order for them to take 
effect.");?><br>
<?php endif; ?>
	<table width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr>
		  <td>
<?php
			$tab_array = array();
			$tab_array[0] = array("Gateways", false, "system_gateways.php");
			$tab_array[1] = array("Routes", false, "system_routes.php");
			$tab_array[2] = array("Groups", true, "system_gateway_groups.php");
			$tab_array[3] = array("Settings", false, "system_gateways_settings.php");
			display_top_tabs($tab_array);
?>
</td></tr>
 <tr>
   <td>
	<div id="mainarea">
             <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                  <td width="15%" class="listhdrr">Group Name</td>
                  <td width="15%" class="listhdrr">Gateways</td>
                  <td width="20%" class="listhdrr">Priority</td>
                  <td width="30%" class="listhdr">Description</td>
                  <td width="10%" class="list">
			<table border="0" cellspacing="0" cellpadding="1">
			   <tr>
				<td width="17"></td>
				<td><a href="system_gateway_groups_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
			   </tr>
			</table>
		  </td>
		</tr>
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
                  <td valign="middle" nowrap class="list">
			<table border="0" cellspacing="0" cellpadding="1">
			   <tr>
				<td><a href="system_gateway_groups_edit.php?id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0"></a>
				<td><a href="system_gateway_groups.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this gateway group?')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0"></a></td>
			   </tr>
			   <tr>
				<td width="17"></td>
				<td><a href="system_gateway_groups_edit.php?dup=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
			   </tr>
			</table>

		</tr>
			  <?php $i++; endforeach; ?>
                <tr>
                  <td class="list" colspan="4"></td>
                  <td class="list">
			<table border="0" cellspacing="0" cellpadding="1">
			   <tr>
				<td width="17"></td>
				<td><a href="system_gateway_groups_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
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
