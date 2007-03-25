#!/usr/local/bin/php
<?php 
/*
	firewall_shaper.php
	part of m0n0wall (http://m0n0.ch/wall)
	
	Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
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

if (!is_array($config['shaper']['rule'])) {
	$config['shaper']['rule'] = array();
}
if (!is_array($config['shaper']['pipe'])) {
	$config['shaper']['pipe'] = array();
}
if (!is_array($config['shaper']['queue'])) {
	$config['shaper']['queue'] = array();
}
$a_shaper = &$config['shaper']['rule'];
$a_pipe = &$config['shaper']['pipe'];
$a_queue = &$config['shaper']['queue'];

$pconfig['enable'] = isset($config['shaper']['enable']);

if ($_POST) {

	if ($_POST['submit']) {
		$pconfig = $_POST;
		$config['shaper']['enable'] = $_POST['enable'] ? true : false;
		write_config();
	}
	
	if ($_POST['apply'] || $_POST['submit']) {
		$retval = 0;
		if (!file_exists($d_sysrebootreqd_path)) {
			config_lock();
			require_once("m0n0/shaper.inc");
			$retval = shaper_configure();
			config_unlock();
		}
		$savemsg = get_std_save_message($retval);
		if ($retval == 0) {
			if (file_exists($d_shaperconfdirty_path))
				unlink($d_shaperconfdirty_path);
		}
	}
}

if ($_GET['act'] == "del") {
	if ($a_shaper[$_GET['id']]) {
		unset($a_shaper[$_GET['id']]);
		write_config();
		touch($d_shaperconfdirty_path);
		header("Location: firewall_shaper.php");
		exit;
	}
} else if ($_GET['act'] == "down") {
	if ($a_shaper[$_GET['id']] && $a_shaper[$_GET['id']+1]) {
		$tmp = $a_shaper[$_GET['id']+1];
		$a_shaper[$_GET['id']+1] = $a_shaper[$_GET['id']];
		$a_shaper[$_GET['id']] = $tmp;
		write_config();
		touch($d_shaperconfdirty_path);
		header("Location: firewall_shaper.php");
		exit;
	}
} else if ($_GET['act'] == "up") {
	if (($_GET['id'] > 0) && $a_shaper[$_GET['id']]) {
		$tmp = $a_shaper[$_GET['id']-1];
		$a_shaper[$_GET['id']-1] = $a_shaper[$_GET['id']];
		$a_shaper[$_GET['id']] = $tmp;
		write_config();
		touch($d_shaperconfdirty_path);
		header("Location: firewall_shaper.php");
		exit;
	}
} else if ($_GET['act'] == "toggle") {
	if ($a_shaper[$_GET['id']]) {
		$a_shaper[$_GET['id']]['disabled'] = !isset($a_shaper[$_GET['id']]['disabled']);
		write_config();
		touch($d_shaperconfdirty_path);
		header("Location: firewall_shaper.php");
		exit;
	}
}

$pgtitle = "Firewall: Traffic Shaper";
include("head.inc");

?>
<body link="#000000" vlink="#000000" alink="#000000" onload="<?= $jsevents["body"]["onload"] ?>">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<form action="firewall_shaper.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_shaperconfdirty_path)): ?><p>
<?php print_info_box_np("The traffic shaper configuration has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
</p>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
  <ul id="tabnav">
    <li class="tabact">Rules</li>
    <li class="tabinact"><a href="firewall_shaper_pipes.php">Pipes</a></li>
    <li class="tabinact"><a href="firewall_shaper_queues.php">Queues</a></li>
    <li class="tabinact"><a href="firewall_shaper_magic.php">Magic shaper wizard</a></li>
  </ul>
  </td></tr>
  <tr> 
    <td class="tabcont">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr> 
                  <td class="vtable">
                      <input name="enable" type="checkbox" id="enable" value="yes" <?php if ($pconfig['enable']) echo "checked";?>>
                      <strong>Enable traffic shaper</strong></td>
                </tr>
                <tr> 
                  <td> <input name="submit" type="submit" class="formbtn" value="Save"> 
                  </td>
                </tr>
              </table>
              &nbsp;<br>
              <table width="100%" border="0" cellpadding="0" cellspacing="0">
                      <tr> 
                        <td width="5%" class="listhdrrns">If</td>
                        <td width="5%" class="listhdrrns">Proto</td>
                        <td width="20%" class="listhdrr">Source</td>
                        <td width="20%" class="listhdrr">Destination</td>
                        <td width="15%" class="listhdrrns">Target</td>
                        <td width="25%" class="listhdr">Description</td>
                        <td width="10%" class="list"></td>
                      </tr>
                      <?php $i = 0; foreach ($a_shaper as $shaperent): ?>
                      <tr valign="top"> 
                        <td class="listlr"> 
                          <?php
				  $dis = "";
				  if (isset($shaperent['disabled'])) {
				  	$dis = "_d";
					$textss = "<span class=\"gray\">";
					$textse = "</span>";
				  } else {
				  	$textss = $textse = "";
				  }
				  $iflabels = array('lan' => 'LAN', 'wan' => 'WAN', 'pptp' => 'PPTP');
				  for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++)
				  	$iflabels['opt' . $j] = $config['interfaces']['opt' . $j]['descr'];
				  echo $textss . htmlspecialchars($iflabels[$shaperent['interface']]);
				  echo "<br>";
				  echo "<a href=\"?act=toggle&id={$i}\">";
				  if ($shaperent['direction'] != "in")
				  	echo "<img src=\"/themes/{$g['theme']}/images/icons/out{$dis}.gif\" width=\"11\" height=\"11\" border=\"0\" style=\"margin-top: 5px\" title=\"click to toggle enabled/disabled status\">";
				  if ($shaperent['direction'] != "out")
				  	echo "<img src=\"/themes/{$g['theme']}/images/icons/in{$dis}.gif\" width=\"11\" height=\"11\" border=\"0\" style=\"margin-top: 5px\" title=\"click to toggle enabled/disabled status\">";
				  echo "</a>" . $textse;;
				  ?>
                        </td>
                        <td class="listr"> 
                          <?=$textss;?><?php if (isset($shaperent['protocol'])) echo strtoupper($shaperent['protocol']); else echo "*"; ?><?=$textse;?>
                        </td>
                        <td class="listr"><?=$textss;?><?php echo htmlspecialchars(pprint_address($shaperent['source'])); ?>
						<?php if ($shaperent['source']['port']): ?><br>
						Port: <?=htmlspecialchars(pprint_port($shaperent['source']['port'])); ?> 
						<?php endif; ?><?=$textse;?>
                        </td>
                        <td class="listr"><?=$textss;?><?php echo htmlspecialchars(pprint_address($shaperent['destination'])); ?>
						<?php if ($shaperent['destination']['port']): ?><br>
						Port: <?=htmlspecialchars(pprint_port($shaperent['destination']['port'])); ?>
						<?php endif; ?><?=$textse;?>
                        </td>
                        <td class="listr"><?=$textss;?>
                          <?php 
							if (isset($shaperent['targetpipe'])) {
								if ($a_pipe[$shaperent['targetpipe']]['descr'])
									$desc = htmlspecialchars($a_pipe[$shaperent['targetpipe']]['descr']);
								else 
									$desc = "Pipe " . ($shaperent['targetpipe']+1);
								echo "<a href=\"firewall_shaper_pipes_edit.php?id={$shaperent['targetpipe']}\">{$desc}</a>";
							} else if (isset($shaperent['targetqueue'])) {
								if ($a_queue[$shaperent['targetqueue']]['descr'])
									$desc = htmlspecialchars($a_queue[$shaperent['targetqueue']]['descr']);
								else 
									$desc = "Queue " . ($shaperent['targetqueue']+1);
								echo "<a href=\"firewall_shaper_queues_edit.php?id={$shaperent['targetqueue']}\">{$desc}</a>";
							}
						  ?><?=$textse;?>
                        </td>
                        <td class="listbg"> 
                          <?=$textss;?><font color="#ffffff"><?=htmlspecialchars($shaperent['descr']);?><?=$textse;?>
                          &nbsp; </td>
                        <td valign="middle" nowrap class="list"> <a href="firewall_shaper_edit.php?id=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" title="edit rule" width="17" height="17" border="0"></a> 
                          <?php if ($i > 0): ?>
                          <a href="firewall_shaper.php?act=up&id=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_up.gif" title="move up" width="17" height="17" border="0"></a> 
                          <?php else: ?>
                          <img src="up_d.gif" width="17" height="17" border="0"> 
                          <?php endif; ?><br>
						  <a href="firewall_shaper.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this rule?')"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" title="delete rule" width="17" height="17" border="0"></a> 
                          <?php if (isset($a_shaper[$i+1])): ?>
                          <a href="firewall_shaper.php?act=down&id=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_down.gif" title="move down" width="17" height="17" border="0"></a> 
                          <?php else: ?>
                          <img src="/themes/<?= $g['theme']; ?>/images/icons/icon_down_d.gif" width="17" height="17" border="0"> 
                          <?php endif; ?>
                          <a href="firewall_shaper_edit.php?dup=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" " title="add a new rule based on this one" width="17" height="17" border="0"></a> 
                        </td>
                      </tr>
                      <?php $i++; endforeach; ?>
                      <tr> 
                        <td class="list" colspan="6"></td>
                        <td class="list"> <a href="firewall_shaper_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" " title="add rule" width="17" height="17" border="0"></a></td>
                      </tr>
                    </table>
					  
                    <table border="0" cellspacing="0" cellpadding="0">
                      <tr> 
                        <td width="16"><img src="/themes/<?php echo $g['theme']; ?>/images/icons/in.gif" width="11" height="11"></td>
                        <td>incoming (as seen by firewall)</td>
                        <td width="14"></td>
                        <td width="16"><img src="/themes/<?php echo $g['theme']; ?>/images/icons/out.gif" width="11" height="11"></td>
                        <td>outgoing (as seen by firewall)</td>
                      </tr>
                      <tr> 
                        <td colspan="5" height="4"></td>
                      </tr>
                      <tr> 
                        <td><img src="/themes/<?php echo $g['theme']; ?>/images/icons/in_d.gif" width="11" height="11"></td>
                        <td>incoming (disabled)</td>
                        <td width="14"></td>
                        <td><img src="/themes/<?php echo $g['theme']; ?>/images/icons/out_d.gif" width="11" height="11"></td>
                        <td>outgoing (disabled)</td>
                      </tr>
                    </table><br>
			        <span class="red"><strong>Note:</strong></span><strong><br>
                    </strong>the first rule that matches a packet will be executed.<br>
                    The following match patterns are not shown in the list above: 
                    IP packet length, TCP flags.</td>
	</tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
