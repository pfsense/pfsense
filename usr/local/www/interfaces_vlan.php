#!/usr/local/bin/php
<?php 
/*
	interfaces_vlan.php
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

if (!is_array($config['vlans']['vlan']))
	$config['vlans']['vlan'] = array();

$a_vlans = &$config['vlans']['vlan'] ;

function vlan_inuse($num) {
	global $config, $g;

	if ($config['interfaces']['lan']['if'] == "vlan{$num}")
		return true;
	if ($config['interfaces']['wan']['if'] == "vlan{$num}")
		return true;
	
	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
		if ($config['interfaces']['opt' . $i]['if'] == "vlan{$num}")
			return true;
	}
	
	return false;
}

function renumber_vlan($if, $delvlan) {
	if (!preg_match("/^vlan/", $if))
		return $if;
	
	$vlan = substr($if, 4);
	if ($vlan > $delvlan)
		return "vlan" . ($vlan - 1);
	else
		return $if;
}

if ($_GET['act'] == "del") {
	/* check if still in use */
	if (vlan_inuse($_GET['id'])) {
		$input_errors[] = "This VLAN cannot be deleted because it is still being used as an interface.";
	} else {
		unset($a_vlans[$_GET['id']]);
		
		/* renumber all interfaces that use VLANs */
		$config['interfaces']['lan']['if'] = renumber_vlan($config['interfaces']['lan']['if'], $_GET['id']);
		$config['interfaces']['wan']['if'] = renumber_vlan($config['interfaces']['wan']['if'], $_GET['id']);
		for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++)
			$config['interfaces']['opt' . $i]['if'] = renumber_vlan($config['interfaces']['opt' . $i]['if'], $_GET['id']);
		
		write_config();
		touch($d_sysrebootreqd_path);
		header("Location: interfaces_vlan.php");
		exit;
	}
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Interfaces: Assign network ports: VLANs");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Interfaces: Assign network ports: VLANs</p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if (file_exists($d_sysrebootreqd_path)) print_info_box(get_std_save_message(0)); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
  <ul id="tabnav">
    <li class="tabinact"><a href="interfaces_assign.php">Interface assignments</a></li>
    <li class="tabact">VLANs</li>
  </ul>
  </td></tr>
  <tr> 
    <td class="tabcont">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                  <td width="20%" class="listhdrr">Interface</td>
                  <td width="20%" class="listhdrr">VLAN tag</td>
                  <td width="50%" class="listhdr">Description</td>
                  <td width="10%" class="list"></td>
				</tr>
			  <?php $i = 0; foreach ($a_vlans as $vlan): ?>
                <tr>
                  <td class="listlr">
					<?=htmlspecialchars($vlan['if']);?>
                  </td>
                  <td class="listr">
					<?=htmlspecialchars($vlan['tag']);?>
                  </td>
                  <td class="listbg">
                    <?=htmlspecialchars($vlan['descr']);?>&nbsp;
                  </td>
                  <td valign="middle" nowrap class="list"> <a href="interfaces_vlan_edit.php?id=<?=$i;?>"><img src="e.gif" width="17" height="17" border="0"></a>
                     &nbsp;<a href="interfaces_vlan.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this VLAN?')"><img src="x.gif" width="17" height="17" border="0"></a></td>
				</tr>
			  <?php $i++; endforeach; ?>
                <tr> 
                  <td class="list" colspan="3">&nbsp;</td>
                  <td class="list"> <a href="interfaces_vlan_edit.php"><img src="plus.gif" width="17" height="17" border="0"></a></td>
				</tr>
				<tr>
				<td colspan="3" class="list"><p class="vexpl"><span class="red"><strong>
				  Note:<br>
				  </strong></span>
				  Not all drivers/NICs support 802.1Q VLAN tagging properly. On cards that do not explicitly support it, VLAN tagging will still work, but the reduced MTU may cause problems. See the m0n0wall homepage for information on supported cards. </p>
				  </td>
				<td class="list">&nbsp;</td>
				</tr>
              </table>
			  </td>
	</tr>
</table>
<?php include("fend.inc"); ?>
</body>
</html>
