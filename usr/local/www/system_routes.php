#!/usr/local/bin/php
<?php
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

require("guiconfig.inc");

if (!is_array($config['staticroutes']['route']))
	$config['staticroutes']['route'] = array();

staticroutes_sort();
$a_routes = &$config['staticroutes']['route'];

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;
		if (!file_exists($d_sysrebootreqd_path)) {
			$retval = system_routing_configure();
			$retval |= filter_configure();
		}
		$savemsg = get_std_save_message($retval);
		if ($retval == 0) {
			if (file_exists($d_staticroutesdirty_path)) {
				config_lock();
				unlink($d_staticroutesdirty_path);
				config_unlock();
			}
		}
	}
}

if ($_GET['act'] == "del") {
	if ($a_routes[$_GET['id']]) {
		unset($a_routes[$_GET['id']]);
		write_config();
		touch($d_staticroutesdirty_path);
		header("Location: system_routes.php");
		exit;
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("System: Static routes");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">System: Static routes</p>
<form action="system_routes.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_staticroutesdirty_path)): ?><p>
<?php print_info_box_np("The static route configuration has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<input name="apply" type="submit" class="formbtn" id="apply" value="Apply changes"></p>
<?php endif; ?>
              <table width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                  <td width="15%" class="listhdrr">Interface</td>
                  <td width="25%" class="listhdrr">Network</td>
                  <td width="20%" class="listhdrr">Gateway</td>
                  <td width="30%" class="listhdr">Description</td>
                  <td width="10%" class="list"></td>
				</tr>
			  <?php $i = 0; foreach ($a_routes as $route): ?>
                <tr>
                  <td class="listlr">
                    <?php
				  $iflabels = array('lan' => 'LAN', 'wan' => 'WAN', 'pptp' => 'PPTP');
				  for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++)
				  	$iflabels['opt' . $j] = $config['interfaces']['opt' . $j]['descr'];
				  echo htmlspecialchars($iflabels[$route['interface']]); ?>
                  </td>
                  <td class="listr">
                    <?=strtolower($route['network']);?>
                  </td>
                  <td class="listr">
                    <?=strtolower($route['gateway']);?>
                  </td>
                  <td class="listbg">
                    <?=htmlspecialchars($route['descr']);?>&nbsp;
                  </td>
                  <td valign="middle" nowrap class="list"> <a href="system_routes_edit.php?id=<?=$i;?>"><img src="e.gif" width="17" height="17" border="0"></a>
                     &nbsp;<a href="system_routes.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this route?')"><img src="x.gif" width="17" height="17" border="0"></a></td>
				</tr>
			  <?php $i++; endforeach; ?>
                <tr> 
                  <td class="list" colspan="4"></td>
                  <td class="list"> <a href="system_routes_edit.php"><img src="plus.gif" width="17" height="17" border="0"></a></td>
				</tr>
              </table>
            </form>
<?php include("fend.inc"); ?>
</body>
</html>
