#!/usr/local/bin/php
<?php
/*
	firewall_aliases.php
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

require("guiconfig.inc");

if (!is_array($config['aliases']['alias']))
	$config['aliases']['alias'] = array();

aliases_sort();
$a_aliases = &$config['aliases']['alias'];

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;
		if (!file_exists($d_sysrebootreqd_path)) {
			config_lock();
			/* reload all components that use aliases */
			$retval = filter_configure();
			$retval |= shaper_configure();
			config_unlock();
		}
		$savemsg = get_std_save_message($retval);
		if ($retval == 0) {
			if (file_exists($d_aliasesdirty_path))
				unlink($d_aliasesdirty_path);
		}
	}
}

if ($_GET['act'] == "del") {
	if ($a_aliases[$_GET['id']]) {
		unset($a_aliases[$_GET['id']]);
		write_config();
		touch($d_aliasesdirty_path);
		header("Location: firewall_aliases.php");
		exit;
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Firewall: Aliases");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Firewall: Aliases</p>
<form action="firewall_aliases.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_aliasesdirty_path)): ?><p>
<?php print_info_box_np("The alias list has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<input name="apply" type="submit" class="formbtn" id="apply" value="Apply changes"></p>
<?php endif; ?>
              <table width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                  <td width="25%" class="listhdrr">Name</td>
                  <td width="30%" class="listhdrr">Address</td>
                  <td width="35%" class="listhdr">Description</td>
                  <td width="10%" class="list"></td>
				</tr>
			  <?php $i = 0; foreach ($a_aliases as $alias): ?>
                <tr>
                  <td class="listlr">
                    <?=htmlspecialchars($alias['name']);?>
                  </td>
                  <td class="listr">
                    <?=htmlspecialchars($alias['address']);?>
                  </td>
                  <td class="listbg">
                    <?=htmlspecialchars($alias['descr']);?>&nbsp;
                  </td>
                  <td valign="middle" nowrap class="list"> <a href="firewall_aliases_edit.php?id=<?=$i;?>"><img src="e.gif" width="17" height="17" border="0"></a>
                     &nbsp;<a href="firewall_aliases.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this alias? All elements that still use it will become invalid (e.g. filter rules)!')"><img src="x.gif" width="17" height="17" border="0"></a></td>
				</tr>
			  <?php $i++; endforeach; ?>
                <tr> 
                  <td class="list" colspan="3"></td>
                  <td class="list"> <a href="firewall_aliases_edit.php"><img src="plus.gif" width="17" height="17" border="0"></a></td>
				</tr>
              </table>
            </form>
<p><span class="vexpl"><span class="red"><strong>Note:<br>
                </strong></span>Aliases act as placeholders for real IP addresses 
                and can be used to minimize the number of changes that have to 
                be made if a host or network address changes. You can enter the 
                name of an alias instead of an IP address in all address fields 
                that have a blue background. The alias will be resolved to its 
                current address according to the list below. If an alias cannot 
                be resolved (e.g. because you deleted it), the corresponding element 
                (e.g. filter/NAT/shaper rule) will be considered invalid and skipped.</span></p>
<?php include("fend.inc"); ?>
</body>
</html>
