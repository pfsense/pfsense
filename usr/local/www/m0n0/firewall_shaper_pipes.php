#!/usr/local/bin/php
<?php 
/*
	firewall_shaper_pipes.php
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

$pgtitle = array("Firewall", "Traffic shaper");
require("guiconfig.inc");

if (!is_array($config['shaper']['pipe'])) {
	$config['shaper']['pipe'] = array();
}
if (!is_array($config['shaper']['queue'])) {
	$config['shaper']['queue'] = array();
}
$a_pipes = &$config['shaper']['pipe'];

if ($_GET['act'] == "del") {
	if ($a_pipes[$_GET['id']]) {
		/* check that no rule references this pipe */
		if (is_array($config['shaper']['rule'])) {
			foreach ($config['shaper']['rule'] as $rule) {
				if (isset($rule['targetpipe']) && ($rule['targetpipe'] == $_GET['id'])) {
					$input_errors[] = "This pipe cannot be deleted because it is still referenced by a rule.";
					break;
				}
			}
		}
		
		/* check that no queue references this pipe */
		if (is_array($config['shaper']['queue'])) {
			foreach ($config['shaper']['queue'] as $queue) {
				if ($queue['targetpipe'] == $_GET['id']) {
					$input_errors[] = "This pipe cannot be deleted because it is still referenced by a queue.";
					break;
				}
			}
		}
		
		if (!$input_errors) {
			unset($a_pipes[$_GET['id']]);
			
			/* renumber all rules and queues */
			if (is_array($config['shaper']['rule'])) {
				for ($i = 0; isset($config['shaper']['rule'][$i]); $i++) {
					$currule = &$config['shaper']['rule'][$i];
					if (isset($currule['targetpipe']) && ($currule['targetpipe'] > $_GET['id']))
						$currule['targetpipe']--;
				}
			}
			if (is_array($config['shaper']['queue'])) {
				for ($i = 0; isset($config['shaper']['queue'][$i]); $i++) {
					$curqueue = &$config['shaper']['queue'][$i];
					if ($curqueue['targetpipe'] > $_GET['id'])
						$curqueue['targetpipe']--;
				}
			}
			
			write_config();
			touch($d_shaperconfdirty_path);
			header("Location: firewall_shaper_pipes.php");
			exit;
		}
	}
}
$pgtitle = "Firewall: Traffic Shaper - Pipes";
include("head.inc");

?>
<body link="#000000" vlink="#000000" alink="#000000" onload="<?= $jsevents["body"]["onload"] ?>">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<form action="firewall_shaper.php" method="post">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_shaperconfdirty_path)): ?><p>
<?php print_info_box_np("The traffic shaper configuration has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
</p>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
  <ul id="tabnav">
    <li class="tabinact1"><a href="firewall_shaper.php">Rules</a></li>
    <li class="tabact">Pipes</li>
    <li class="tabinact"><a href="firewall_shaper_queues.php">Queues</a></li>
    <li class="tabinact"><a href="firewall_shaper_magic.php">Magic shaper wizard</a></li>
  </ul>
  </td></tr>
  <tr> 
    <td class="tabcont">
              <table width="100%" border="0" cellpadding="0" cellspacing="0">
                      <tr> 
                        <td width="10%" class="listhdrr">No.</td>
                        <td width="15%" class="listhdrr">Bandwidth</td>
                        <td width="10%" class="listhdrr">Delay</td>
                        <td width="10%" class="listhdrr">PLR</td>
                        <td width="10%" class="listhdrr">Queue</td>
                        <td width="15%" class="listhdrr">Mask</td>
                        <td width="20%" class="listhdr">Description</td>
                        <td width="10%" class="list"></td>
                      </tr>
                      <?php $i = 0; foreach ($a_pipes as $pipe): ?>
                      <tr valign="top">
                        <td class="listlr"> 
                          <?=($i+1);?></td>
                        <td class="listr"> 
                          <?=htmlspecialchars($pipe['bandwidth']);?>
                          Kbit/s </td>
                        <td class="listr"> 
                          <?php if ($pipe['delay']): ?>
                          <?=$pipe['delay'];?>
                          ms 
                          <?php endif; ?>
                          &nbsp; </td>
                        <td class="listr"> 
                          <?php if ($pipe['plr']): ?>
                          <?=$pipe['plr'];?>
                          <?php endif; ?>
                          &nbsp; </td>
                        <td class="listr"> 
                          <?php if ($pipe['qsize']): ?>
                          <?=htmlspecialchars($pipe['qsize']);?>
                          <?php endif; ?>
                          &nbsp; </td>
                        <td class="listr"> 
                          <?php if ($pipe['mask']): ?>
                          <?=$pipe['mask'];?>
                          <?php endif; ?>
                          &nbsp; </td>
                        <td class="listbg">
			  <font color="#ffffff">
                          <?=htmlspecialchars($pipe['descr']);?>
                          &nbsp; </td>
                        <td valign="middle" nowrap class="list"> <a href="firewall_shaper_pipes_edit.php?id=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" title="edit pipe" width="17" height="17" border="0"></a> 
                          &nbsp;<a href="firewall_shaper_pipes.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this pipe?')"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" title="delete pipe" width="17" height="17" border="0"></a></td>
                      </tr>
                      <?php $i++; endforeach; ?>
                      <tr> 
                        <td class="list" colspan="7"></td>
                        <td class="list"> <a href="firewall_shaper_pipes_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="add pipe" width="17" height="17" border="0"></a></td>
                      </tr>
                    </table><br>
                    <strong><span class="red">Note:</span></strong> a pipe can 
                    only be deleted if it is not referenced by any rules or queues.</td>
	</tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
