<?php
/*
	services_captiveportal_zones.php

	Copyright (C) 2013-2015 Electric Sheep Fencing, LP

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

##|+PRIV
##|*IDENT=page-services-captiveportal-zones
##|*NAME=Services: Captive portal Zones page
##|*DESCR=Allow access to the 'Services: Captive portal Zones' page.
##|*MATCH=services_captiveportal_zones.php*
##|-PRIV

require("guiconfig.inc");
require("functions.inc");
require_once("filter.inc");
require("shaper.inc");
require("captiveportal.inc");

global $cpzone;
global $cpzoneid;

if (!is_array($config['captiveportal'])) {
	$config['captiveportal'] = array();
}
$a_cp = &$config['captiveportal'];

if ($_GET['act'] == "del" && !empty($_GET['zone'])) {
	$cpzone = htmlspecialchars($_GET['zone']);
	if ($a_cp[$cpzone]) {
		$cpzoneid = $a_cp[$cpzone]['zoneid'];
		unset($a_cp[$cpzone]['enable']);
		captiveportal_configure_zone($a_cp[$cpzone]);
		unset($a_cp[$cpzone]);
		if (isset($config['voucher'][$cpzone])) {
			unset($config['voucher'][$cpzone]);
		}
		write_config();
	}
	header("Location: services_captiveportal_zones.php");
	exit;
}

$pgtitle = array(gettext("Captive Portal"), gettext("Zones"));
$shortcut_section = "captiveportal";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="services_captiveportal_zones.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('captiveportal')): ?><p>
<?php print_info_box_np(gettext("The CaptivePortal entry list has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));?>
<?php endif; ?>

<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="captive portal">
	<tr>
		<td width="15%" class="listhdrr"><?=gettext("Zone");?></td>
		<td width="30%" class="listhdrr"><?=gettext("Interfaces");?></td>
		<td width="10%" class="listhdrr"><?=gettext("Number of users");?></td>
		<td width="40%" class="listhdrr"><?=gettext("Description");?></td>
		<td width="5%" class="list">
			<table border="0" cellspacing="0" cellpadding="1" summary="icons">
				<tr>
					<td valign="middle" width="17">&nbsp;</td>
					<td valign="middle">
						<a href="services_captiveportal_zones_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="<?=gettext("add a new captiveportal instance");?>" alt="add" /></a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
<?php
	foreach ($a_cp as $cpzone => $cpitem):
		if (!is_array($cpitem)) {
			continue;
		}
?>
	<tr>
		<td class="listlr" ondblclick="document.location='services_captiveportal.php?zone=<?=$cpzone;?>';">
			<?=htmlspecialchars($cpitem['zone']);?>
		</td>
		<td class="listlr" ondblclick="document.location='services_captiveportal.php?zone=<?=$cpzone;?>';">
<?php
		$cpifaces = explode(",", $cpitem['interface']);
		foreach ($cpifaces as $cpiface) {
			echo convert_friendly_interface_to_friendly_descr($cpiface) . " ";
		}
?>
		</td>
		<td class="listr" ondblclick="document.location='services_captiveportal.php?zone=<?=$cpzone;?>';">
			<?=count(captiveportal_read_db());?>
		</td>
		<td class="listbg" ondblclick="document.location='services_captiveportal.php?zone=<?=$cpzone;?>';">
			<?=htmlspecialchars($cpitem['descr']);?>&nbsp;
		</td>
		<td valign="middle" class="list nowrap">
			<table border="0" cellspacing="0" cellpadding="1" summary="icons">
				<tr>
					<td valign="middle"><a href="services_captiveportal.php?zone=<?=$cpzone?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" title="<?=gettext("edit captiveportal instance"); ?>" alt="edit" /></a></td>
					<td>
						<a href="services_captiveportal_zones.php?act=del&amp;zone=<?=$cpzone;?>" onclick="return confirm('<?=gettext("Do you really want to delete this entry?");?>')"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" title="<?=gettext("delete captiveportal instance");?>" alt="delete" /></a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
<?php
	endforeach;
?>
	<tr>
		<td class="list" colspan="4"></td>
		<td class="list">
			<table border="0" cellspacing="0" cellpadding="1" summary="add">
				<tr>
					<td valign="middle" width="17">&nbsp;</td>
					<td valign="middle">
						<a href="services_captiveportal_zones_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="<?=gettext("add a new captiveportal instance");?>" alt="add" /></a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
