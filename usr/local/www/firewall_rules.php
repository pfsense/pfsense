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

$if = $_GET['if'];
if ($_POST['if'])
	$if = $_POST['if'];
	
$iflist = array("lan" => "LAN", "wan" => "WAN");

if ($config['pptpd']['mode'] == "server")
	$iflist['pptp'] = "PPTP VPN";

for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
	$iflist['opt' . $i] = $config['interfaces']['opt' . $i]['descr'];
}

if (!$if || !isset($iflist[$if]))
	$if = "lan";

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

if (isset($_POST['del_x'])) {
	/* delete selected rules */
	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		foreach ($_POST['rule'] as $rulei) {
			unset($a_filter[$rulei]);
		}
		write_config();
		touch($d_filterconfdirty_path);
		header("Location: firewall_rules.php?if={$if}");
		exit;
	}
} else if ($_GET['act'] == "toggle") {
	if ($a_filter[$_GET['id']]) {
		$a_filter[$_GET['id']]['disabled'] = !isset($a_filter[$_GET['id']]['disabled']);
		write_config();
		touch($d_filterconfdirty_path);
		header("Location: firewall_rules.php?if={$if}");
		exit;
	}
} else {
	/* yuck - IE won't send value attributes for image buttons, while Mozilla does - 
	   so we use .x/.y to fine move button clicks instead... */
	unset($movebtn);
	foreach ($_POST as $pn => $pd) {
		if (preg_match("/move_(\d+)_x/", $pn, $matches)) {
			$movebtn = $matches[1];
			break;
		}
	}
	/* move selected rules before this rule */
	if (isset($movebtn) && is_array($_POST['rule']) && count($_POST['rule'])) {
		$a_filter_new = array();
		
		/* copy all rules < $movebtn and not selected */
		for ($i = 0; $i < $movebtn; $i++) {
			if (!in_array($i, $_POST['rule']))
				$a_filter_new[] = $a_filter[$i];
		}
		
		/* copy all selected rules */
		for ($i = 0; $i < count($a_filter); $i++) {
			if ($i == $movebtn)
				continue;
			if (in_array($i, $_POST['rule']))
				$a_filter_new[] = $a_filter[$i];
		}
		
		/* copy $movebtn rule */
		if ($movebtn < count($a_filter))
			$a_filter_new[] = $a_filter[$movebtn];
		
		/* copy all rules > $movebtn and not selected */
		for ($i = $movebtn+1; $i < count($a_filter); $i++) {
			if (!in_array($i, $_POST['rule']))
				$a_filter_new[] = $a_filter[$i];
		}
		
		$a_filter = $a_filter_new;
		write_config();
		touch($d_filterconfdirty_path);
		header("Location: firewall_rules.php?if={$if}");
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
  <tr><td>
  <ul id="tabnav">
<?php foreach ($iflist as $ifent => $ifname):
	if ($ifent == $if): ?>
    <li class="tabact"><?=htmlspecialchars($ifname);?></li>
<?php else: ?>
    <li class="tabinact"><a href="firewall_rules.php?if=<?=$ifent;?>"><?=htmlspecialchars($ifname);?></a></li>
<?php endif; ?>
<?php endforeach; ?>
  </ul>
  </td></tr>
  <tr> 
    <td class="tabcont">
              <table width="100%" border="0" cellpadding="0" cellspacing="0">
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
				<?php $nrules = 0; for ($i = 0; isset($a_filter[$i]); $i++):
					$filterent = $a_filter[$i];
					if ($filterent['interface'] != $if)
						continue;
				?>
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
				  <a href="?if=<?=$if;?>&act=toggle&id=<?=$i;?>"><img src="<?=$iconfn;?>.gif" width="11" height="11" border="0" title="click to toggle enabled/disabled status"></a>
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
                    <?=$textss;?><?=htmlspecialchars($filterent['descr']);?>&nbsp;<?=$textse;?>
                  </td>
                  <td valign="middle" nowrap class="list">
				    <table border="0" cellspacing="0" cellpadding="1">
					<tr>
					  <td><a href="firewall_rules_edit.php?id=<?=$i;?>"><img src="e.gif" title="edit rule" width="17" height="17" border="0"></a></td>
					  <td align="center" valign="middle"><input type="checkbox" name="rule[]" value="<?=$i;?>" style="margin: 0; padding: 0; width: 15px; height: 15px;"></td>
					</tr>
					<tr>
					  <td><input name="move_<?=$i;?>" type="image" src="left.gif" width="17" height="17" title="move selected rules before this rule"></td>
					  <td><a href="firewall_rules_edit.php?dup=<?=$i;?>"><img src="plus.gif" title="add a new rule based on this one" width="17" height="17" border="0"></a></td>
					</tr>
					</table>
				  </td>
				</tr>
			  <?php $nrules++; endfor; ?>
			  <?php if ($nrules == 0): ?>
			  <td class="listt"></td>
			  <td class="listlr" colspan="6" align="center" valign="middle">
			  <span class="gray">
			  No rules are currently defined for this interface.<br>
			  All incoming connections on this interface will be blocked until you add pass rules.<br><br>
			  Click the <a href="firewall_rules_edit.php?if=<?=$if;?>"><img src="plus.gif" title="add new rule" border="0" width="17" height="17" align="absmiddle"></a> button to add a new rule.</span>
			  </td>
			  <?php endif; ?>
                <tr> 
                  <td class="list" colspan="7"></td>
                  <td class="list">
				    <table border="0" cellspacing="0" cellpadding="1">
					<tr>
				      <td>
					  <?php if ($nrules == 0): ?><img src="left_d.gif" width="17" height="17" title="move selected rules to end" border="0"><?php else: ?><input name="move_<?=$i;?>" type="image" src="left.gif" width="17" height="17" title="move selected rules to end"><?php endif; ?></td>
					  <td><a href="firewall_rules_edit.php?if=<?=$if;?>"><img src="plus.gif" title="add new rule" width="17" height="17" border="0"></a></td>
				    </tr>
					<tr>
					  <td><?php if ($nrules == 0): ?><img src="x_d.gif" width="17" height="17" title="delete selected rules" border="0"><?php else: ?><input name="del" type="image" src="x.gif" width="17" height="17" title="delete selected rules" onclick="return confirm('Do you really want to delete the selected rules?')"><?php endif; ?></td>
					  <td></td>
					</tr>
				    </table>
				  </td>
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
    </td>
  </tr>
</table>
  <p>
  <strong><span class="red">Hint:<br>
  </span></strong>rules are evaluated on a first-match basis (i.e. 
  the action of the first rule to match a packet will be executed). 
  This means that if you use block rules, you'll have to pay attention 
  to the rule order. Everything that isn't explicitly passed is blocked 
  by default.</p>
<input type="hidden" name="if" value="<?=$if;?>">
</form>
<?php include("fend.inc"); ?>
</body>
</html>
