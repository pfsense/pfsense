#!/usr/local/bin/php
<?php
/* $Id$ */
/*
	firewall_shaper_queues.php
	Copyright (C) 2004, 2005 Scott Ullrich
	All rights reserved.

	Originally part of m0n0wall (http://m0n0.ch/wall)
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

if($_GET['reset'] <> "") {
	mwexec("killall -9 pfctl php");
	exit;
}

if (!is_array($config['shaper']['queue'])) {
	$config['shaper']['queue'] = array();
}
$a_queues = &$config['shaper']['queue'];

$iflist = array("lan" => "LAN", "wan" => "WAN");

for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
	$iflist['opt' . $i] = $config['interfaces']['opt' . $i]['descr'];
}

if ($_POST['apply'] || $_POST['submit']) {
	$config['shaper']['enable'] = true;
	write_config();

	$retval = 0;
	$savemsg = get_std_save_message($retval);
	/* Setup pf rules since the user may have changed the optimization value */
	config_lock();
	$retval = filter_configure();
	config_unlock();
	if(stristr($retval, "error") <> true)
	    $savemsg = get_std_save_message($retval);
	else
	    $savemsg = $retval;

	if(file_exists($d_shaperconfdirty_path))
	  unlink($d_shaperconfdirty_path);
}

if ($_GET['act'] == "del") {
	if ($a_queues[$_GET['id']]) {
		/* check that no rule references this queue */
		if (is_array($config['shaper']['rule'])) {
			foreach ($config['shaper']['rule'] as $rule) {
				if (isset($rule['targetqueue']) && ($rule['targetqueue'] == $_GET['id'])) {
					$input_errors[] = "This queue cannot be deleted because it is still referenced by a rule.";
					break;
				}
			}
		}

		if (!$input_errors) {
			unset($a_queues[$_GET['id']]);

			/* renumber all rules */
			if (is_array($config['shaper']['rule'])) {
				for ($i = 0; isset($config['shaper']['rule'][$i]); $i++) {
					$currule = &$config['shaper']['rule'][$i];
					if (isset($currule['targetqueue']) && ($currule['targetqueue'] > $_GET['id']))
						$currule['targetqueue']--;
				}
			}

			write_config();
			touch($d_shaperconfdirty_path);
			header("Location: firewall_shaper_queues.php");
			exit;
		}
	}
}

$pgtitle = "Firewall: Shaper: Queues";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<form action="firewall_shaper_queues.php" method="post">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_shaperconfdirty_path)): ?><p>
<?php print_info_box_np("The traffic shaper configuration has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
<?php
	$tab_array = array();
	$tab_array[0] = array("Rules", false, "firewall_shaper.php");
	$tab_array[1] = array("Queues", true, "firewall_shaper_queues.php");
	$tab_array[2] = array("EZ Shaper wizard", false, "wizard.php?xml=traffic_shaper_wizard.xml");
	display_top_tabs($tab_array);
?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
              <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
                      <tr>
                        <td width="5%" class="listhdrr">No.</td>
			<td width="5%" class="listhdrr">Flags</td>
                        <td width="5%" class="listhdrr">Priority</td>
			<td width="5%" class="listhdr">Default</td>
			<td width="5%" class="listhdr">Bandwidth</td>
                        <td width="70%" class="listhdr">Name</td>
                        <td width="10%" class="list"></td>
                      </tr>
                      <?php $i = 0; foreach ($a_queues as $queue): ?>
                      <tr valign="top">
                        <td class="listlr" ondblclick="document.location='firewall_shaper_queues_edit.php?id=<?=$i;?>';">
                          <?=($i+1);?>
			</td>
			<td class="listlr" ondblclick="document.location='firewall_shaper_queues_edit.php?id=<?=$i;?>';">
			  <?php
			     if($queue['red'] <> "") echo " RED";
			     if($queue['rio'] <> "") echo " RIO";
			     if($queue['ecn'] <> "") echo " ECN";
			     if($queue['borrow'] <> "") echo " Borrow";
			     if(isset($queue['ack'])) echo "ACK"
			  ?>
			  &nbsp;
			</td>
                        <td class="listr" ondblclick="document.location='firewall_shaper_queues_edit.php?id=<?=$i;?>';">
                          <?=$queue['priority'];?>&nbsp;
			</td>
			<td class="listr" ondblclick="document.location='firewall_shaper_queues_edit.php?id=<?=$i;?>';">
			  <?php
				if($queue['defaultqueue'] <> "") {
					echo "Yes";
				} else {
					echo "No";
				}
			  ?>
			</td>
                        <td class="listr" ondblclick="document.location='firewall_shaper_queues_edit.php?id=<?=$i;?>';">
                          <?=htmlspecialchars($queue['bandwidth']);?> <?=htmlspecialchars($queue['bandwidthtype']);?>
                          &nbsp;
			</td>
                        <td class="listbg" ondblclick="document.location='firewall_shaper_queues_edit.php?id=<?=$i;?>';">
                          <font color="#FFFFFF"><?=htmlspecialchars($queue['name']);?>
                          &nbsp;
			</td>
                        <td valign="middle" nowrap class="list">
                          <table border="0" cellspacing="0" cellpadding="1">
                            <tr>
                              <td valign="middle"><a href="firewall_shaper_queues_edit.php?id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0"></a></td>
                              <td valign="middle"><a href="firewall_shaper_queues.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this queue?')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0"></a></td>
                            </tr>
                         </table>
                        </td>
                      </tr>
                      <?php $i++; endforeach; $total_queues = $i; ?>
                      <tr>
                        <td class="list" colspan="6"></td>
                        <td class="list">
                          <table border="0" cellspacing="0" cellpadding="1">
                            <tr>
                              <td valign="middle"><a href="firewall_shaper_queues_edit.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
                            </tr>
                          </table>
                        </td>
                      </tr>
		      <tr><td colspan="6">
		    <p>
                    <strong><span class="red">Note:</span></strong><strong><br></strong>
                      A queue can only be deleted if it is not referenced by any rules.<br>
                      You can check the results of your queues at <a href="status_queues.php">Status:Queues</a>.
		    </p>
		      </td></tr>
                    </table>
		</div>
	  </td>
	</tr>
</table>
            </form>
<?php include("fend.inc"); ?>
</body>
</html>
