#!/usr/local/bin/php
<?php
/*
	firewall_rules.php
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

if (!is_array($config['filter']['rule'])) {
	$config['filter']['rule'] = array();
}
filter_rules_sort();
$a_filter = &$config['filter']['rule'];

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;
		if (!file_exists($d_sysrebootreqd_path)) {
			config_lock();
			$retval = filter_configure();
			config_unlock();
		}
		$savemsg = get_std_save_message($retval);
		if ($retval == 0) {
			if (file_exists($d_natconfdirty_path))
				unlink($d_natconfdirty_path);
			if (file_exists($d_filterconfdirty_path))
				unlink($d_filterconfdirty_path);
		}
	}
}

if ($_GET['act'] == "del") {
	if ($a_filter[$_GET['id']]) {
		unset($a_filter[$_GET['id']]);
		write_config();
		touch($d_filterconfdirty_path);
		header("Location: firewall_rules.php");
		exit;
	}
} else if ($_GET['act'] == "down") {
	if ($a_filter[$_GET['id']] && $a_filter[$_GET['id']+1]) {
		$tmp = $a_filter[$_GET['id']+1];
		$a_filter[$_GET['id']+1] = $a_filter[$_GET['id']];
		$a_filter[$_GET['id']] = $tmp;
		write_config();
		touch($d_filterconfdirty_path);
		header("Location: firewall_rules.php");
		exit;
	}
} else if ($_GET['act'] == "up") {
	if (($_GET['id'] > 0) && $a_filter[$_GET['id']]) {
		$tmp = $a_filter[$_GET['id']-1];
		$a_filter[$_GET['id']-1] = $a_filter[$_GET['id']];
		$a_filter[$_GET['id']] = $tmp;
		write_config();
		touch($d_filterconfdirty_path);
		header("Location: firewall_rules.php");
		exit;
	}
} else if ($_GET['act'] == "toggle") {
	if ($a_filter[$_GET['id']]) {
		$a_filter[$_GET['id']]['disabled'] = !isset($a_filter[$_GET['id']]['disabled']);
		write_config();
		touch($d_filterconfdirty_path);
		header("Location: firewall_rules.php");
		exit;
	}
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Firewall: Rules");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Firewall: Rules</p>
<form action="firewall_rules.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_filterconfdirty_path)): ?><p>
<?php print_info_box_np("The firewall rule configuration has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<input name="apply" type="submit" class="formbtn" id="apply" value="Apply changes"></p>
<?php endif; ?>
              <table width="100%" border="0" cellpadding="0" cellspacing="0">

	      	<tr><td colspan="9">
		<ul id="tabnav">
		  <li class="tabact">Rules</li>
		  <li class="tabinact"><a href="firewall_shaper_queues.php">Queues</a></li>
		</ul>
		</td></tr>
		<tr>
			<td class="tabcont">
			<table width="100%" border="0" cellpadding="0" cellspacing="0">

	        <?php $lastif = ""; for ($i = 0; isset($a_filter[$i]); $i++):
				$filterent = $a_filter[$i];
                if ($filterent['interface'] != $lastif):
                if ($i):
				?>

                <tr>
                  <td colspan="8" class="list" height="12"></td>
				</tr>
				<?php endif; ?>
                <tr>
                  <td colspan="7" class="listtopic"><?php
				  $iflabels = array('lan' => 'LAN interface', 'wan' => 'WAN interface', 'pptp' => 'PPTP clients');
				  for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++)
				  	$iflabels['opt' . $j] = $config['interfaces']['opt' . $j]['name'] . " interface";
				  echo htmlspecialchars($iflabels[$filterent['interface']]); ?></td>
				  <td class="list"></td>
				</tr>
                <tr>
                  <td width="5%" class="list">&nbsp;</td>
                  <td width="10%" class="listhdrr">Proto</td>
                  <td width="15%" class="listhdrr">Source</td>
                  <td width="10%" class="listhdrr">Port</td>
                  <td width="15%" class="listhdrr">Destination</td>
                  <td width="10%" class="listhdrr">Port</td>
                  <td width="25%" class="listhdr">Description</td>
                  <td width="10%" class="list"></td>
				</tr>
				<?php $lastif = $filterent['interface']; endif; ?>
                <tr valign="top">
                  <td class="listt">
				  <?php if ($filterent['type'] == "block")
				  			$iconfn = "block";
						else if ($filterent['type'] == "reject") {
							if ($filterent['protocol'] == "tcp" || $filterent['protocol'] == "udp")
								$iconfn = "reject";
							else
								$iconfn = "block";
						} else
							$iconfn = "pass";
						if (isset($filterent['disabled'])) {
							$textss = "<span class=\"gray\">";
							$textse = "</span>";
							$iconfn .= "_d";
						} else {
							$textss = $textse = "";
						}
				  ?>
				  <a href="?act=toggle&id=<?=$i;?>"><img src="<?=$iconfn;?>.gif" width="11" height="11" border="0" title="click to toggle enabled/disabled status"></a>
				  <?php if (isset($filterent['log'])):
							$iconfn = "log_s";
						if (isset($filterent['disabled']))
							$iconfn .= "_d";
				  	?>
				  <br><img src="<?=$iconfn;?>.gif" width="11" height="15" border="0">
				  <?php endif; ?>
				  </td>
                  <td class="listlr">
                    <?=$textss;?><?php if (isset($filterent['protocol'])) echo strtoupper($filterent['protocol']); else echo "*"; ?><?=$textse;?>
                  </td>
                  <td class="listr">
				    <?=$textss;?><?php echo htmlspecialchars(pprint_address($filterent['source'])); ?><?=$textse;?>
                  </td>
                  <td class="listr">
                    <?=$textss;?><?php echo htmlspecialchars(pprint_port($filterent['source']['port'])); ?><?=$textse;?>
                  </td>
                  <td class="listr">
				    <?=$textss;?><?php echo htmlspecialchars(pprint_address($filterent['destination'])); ?><?=$textse;?>
                  </td>
                  <td class="listr">
                    <?=$textss;?><?php echo htmlspecialchars(pprint_port($filterent['destination']['port'])); ?><?=$textse;?>
                  </td>
                  <td class="listbg">
                    <?=$textss;?><font color="#FFFFFF"><?=htmlspecialchars($filterent['descr']);?>&nbsp;<?=$textse;?>
                  </td>
                  <td valign="middle" nowrap class="list">
				    <a href="firewall_rules_edit.php?id=<?=$i;?>"><img src="e.gif" title="edit rule" width="17" height="17" border="0"></a>
					<?php if (($i > 0) && ($a_filter[$i-1]['interface'] == $filterent['interface'])): ?>
					<a href="firewall_rules.php?act=up&id=<?=$i;?>"><img src="up.gif" title="move up" width="17" height="17" border="0"></a>
					<?php else: ?>
					<img src="up_d.gif" width="17" height="17" border="0">
					<?php endif; ?><br>
					<a href="firewall_rules.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this rule?')"><img src="x.gif" title="delete rule" width="17" height="17" border="0"></a>
					<?php if ($a_filter[$i+1]['interface'] == $filterent['interface']): ?>
					<a href="firewall_rules.php?act=down&id=<?=$i;?>"><img src="down.gif" title="move down" width="17" height="17" border="0"></a>
                    <?php else: ?>
					<img src="down_d.gif" width="17" height="17" border="0">
					<?php endif; ?>
					<a href="firewall_rules_edit.php?dup=<?=$i;?>"><img src="plus.gif" title="add a new rule based on this one" width="17" height="17" border="0"></a>
				  </td>
				</tr>
			  <?php endfor; ?>
                <tr>
                  <td class="list" colspan="7"></td>
                  <td class="list"> <a href="firewall_rules_edit.php"><img src="plus.gif" title="add new rule" width="17" height="17" border="0"></a></td>
				</tr>
              </table>
			  <table border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <td width="16"><img src="pass.gif" width="11" height="11"></td>
                  <td>pass</td>
                  <td width="14"></td>
                  <td width="16"><img src="block.gif" width="11" height="11"></td>
                  <td>block</td>
                  <td width="14"></td>
                  <td width="16"><img src="reject.gif" width="11" height="11"></td>
                  <td>reject</td>
                  <td width="14"></td>
                  <td width="16"><img src="log.gif" width="11" height="11"></td>
                  <td>log</td>
                </tr>
                <tr>
                  <td colspan="5" height="4"></td>
                </tr>
                <tr>
                  <td><img src="pass_d.gif" width="11" height="11"></td>
                  <td>pass (disabled)</td>
                  <td></td>
                  <td><img src="block_d.gif" width="11" height="11"></td>
                  <td>block (disabled)</td>
                  <td></td>
                  <td><img src="reject_d.gif" width="11" height="11"></td>
                  <td>reject (disabled)</td>
                  <td></td>
                  <td width="16"><img src="log_d.gif" width="11" height="11"></td>
                  <td>log (disabled)</td>
                </tr>
              </table>
	      </tr></table>
              <p>
              <strong><span class="red">Hint:<br>
              </span></strong>rules are evaluated on a first-match basis (i.e.
              the action of the first rule to match a packet will be executed).
              This means that if you use block rules, you'll have to pay attention
              to the rule order. Everything that isn't explicitly passed is blocked
              by default.</p>
            </form>
<?php include("fend.inc"); ?>
</body>
</html>
