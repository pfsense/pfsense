#!/usr/local/bin/php
<?php
/* $Id$ */
/*
	firewall_nat.php
	Copyright (C) 2004 Scott Ullrich
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
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

if (!is_array($config['nat']['rule']))
	$config['nat']['rule'] = array();

$a_nat = &$config['nat']['rule'];
//nat_rules_sort();

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {

		write_config();

		$retval = 0;

		if (!file_exists($d_sysrebootreqd_path)) {
			config_lock();
			$retval |= filter_configure();
			config_unlock();
		}
		if(stristr($retval, "error") <> true)
		    $savemsg = get_std_save_message($retval);
		else
		    $savemsg = $retval;

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
                        unset($a_nat[$rulei]);
                }
                write_config();
                touch($d_natconfdirty_path);
                header("Location: firewall_nat.php");
                exit;
        }

} else {
        /* yuck - IE won't send value attributes for image buttons, while Mozilla does - so we use .x/.y to find move button clicks instead... */
        unset($movebtn);
        foreach ($_POST as $pn => $pd) {
                if (preg_match("/move_(\d+)_x/", $pn, $matches)) {
                        $movebtn = $matches[1];
                        break;
                }
        }
        /* move selected rules before this rule */
        if (isset($movebtn) && is_array($_POST['rule']) && count($_POST['rule'])) {
                $a_nat_new = array();

                /* copy all rules < $movebtn and not selected */
                for ($i = 0; $i < $movebtn; $i++) {
                        if (!in_array($i, $_POST['rule']))
                                $a_nat_new[] = $a_nat[$i];
                }

                /* copy all selected rules */
                for ($i = 0; $i < count($a_nat); $i++) {
                        if ($i == $movebtn)
                                continue;
                        if (in_array($i, $_POST['rule']))
                                $a_nat_new[] = $a_nat[$i];
                }

                /* copy $movebtn rule */
                if ($movebtn < count($a_nat))
                        $a_nat_new[] = $a_nat[$movebtn];

                /* copy all rules > $movebtn and not selected */
                for ($i = $movebtn+1; $i < count($a_nat); $i++) {
                        if (!in_array($i, $_POST['rule']))
                                $a_nat_new[] = $a_nat[$i];
                }
                $a_nat = $a_nat_new;
                write_config();
                touch($d_natconfdirty_path);
                header("Location: firewall_nat.php");
                exit;
        }
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Firewall: NAT: Inbound");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Firewall: NAT: Inbound</font></p>
<form action="firewall_nat.php" method="post" name="iform">
<script type="text/javascript" language="javascript" src="row_toggle.js">
</script>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_natconfdirty_path)): ?><p>
<?php print_info_box_np("The NAT configuration has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<input name="apply" type="submit" class="formbtn" id="apply" value="Apply changes"></p>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
  <ul id="tabnav">
    <li class="tabact">Inbound</a></li>
    <li class="tabinact"><a href="firewall_nat_server.php">Server NAT</a></li>
    <li class="tabinact"><a href="firewall_nat_1to1.php">1:1</a></li>
    <li class="tabinact"><a href="firewall_nat_out.php">Outbound</a></li>
    <li class="tabinact"><a href="firewall_nat_out_load_balancing.php">Outbound Load Balancing</a></li>
  </ul>
  </td></tr>
  <tr>
    <td class="tabcont">
              <table width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr id="frheader">
		  <td width="3%" class="list">&nbsp;</td>
                  <td width="3%" class="list">&nbsp;</td>
                  <td width="5%" class="listhdrr">If</td>
                  <td width="5%" class="listhdrr">Proto</td>
                  <td width="20%" class="listhdrr">Ext. port range</td>
                  <td width="20%" class="listhdrr">NAT IP</td>
                  <td width="20%" class="listhdrr">Int. port range</td>
                  <td width="20%" class="listhdr">Description</td>
                  <td width="5%" class="list"></td>
		</tr>
	<?php $nnats = $i = 0; foreach ($a_nat as $natent): ?>
                <tr valign="top" id="fr<?=$nnats;?>">
                  <td class="listt"><input type="checkbox" id="frc<?=$nnats;?>" name="rule[]" value="<?=$i;?>" onClick="fr_bgcolor('<?=$nnats;?>')" style="margin: 0; padding: 0; width: 15px; height: 15px;"></td>
                  <td class="listt" align="center"></td>
                  <td class="listlr" onClick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_edit.php?id=<?=$nnats;?>';">
                    <?php
			if (!$natent['interface'] || ($natent['interface'] == "wan"))
				echo "WAN";
			else
				echo "<font color=\"#FFFFFF\">" . htmlspecialchars($config['interfaces'][$natent['interface']]['descr']);
		    ?>
                  </td>
                  <td class="listr" onClick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_edit.php?id=<?=$nnats;?>';">
                    <?=strtoupper($natent['protocol']);?>
                  </td>
                  <td class="listr" onClick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_edit.php?id=<?=$nnats;?>';">
                    <?php
						list($beginport, $endport) = split("-", $natent['external-port']);
						if ((!$endport) || ($beginport == $endport)) {
				  			echo $beginport;
							if ($wkports[$beginport])
								echo " (" . $wkports[$beginport] . ")";
						} else
							echo $beginport . " - " . $endport;
				  ?>
                  </td>
                  <td class="listr" onClick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_edit.php?id=<?=$nnats;?>';">
                    <?=$natent['target'];?>
					<?php if ($natent['external-address'])
						echo "<br>(ext.: " . $natent['external-address'] . ")";
					?>
                  </td>
                  <td class="listr" onClick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_edit.php?id=<?=$nnats;?>';">
                    <?php if ((!$endport) || ($beginport == $endport)) {
				  			echo $natent['local-port'];
							if ($wkports[$natent['local-port']])
								echo " (" . $wkports[$natent['local-port']] . ")";
						} else
							echo $natent['local-port'] . " - " .
								($natent['local-port']+$endport-$beginport);
				  ?>
                  </td>
                  <td class="listbg" onClick="fr_toggle(<?=$nnats;?>)" ondblclick="document.location='firewall_nat_edit.php?id=<?=$nnats;?>';"><font color="#FFFFFFF">
                    <?=htmlspecialchars($natent['descr']);?>&nbsp;
                  </td>
                  <td valign="middle" class="list" nowrap>
                    <table border="0" cellspacing="0" cellpadding="1">
                      <tr>
                        <td><a href="firewall_nat_edit.php?id=<?=$i;?>"><img src="e.gif" width="17" height="17" border="0"></a></td>
                      </tr>
                      <tr>
                        <td><input onmouseover="fr_insline(<?=$nnats;?>, true)" onmouseout="fr_insline(<?=$nnats;?>, false)" name="move_<?=$i;?>" src="left.gif" title="move selected rules before this rule" height="17" type="image" width="17" border="0"></td>
                        <td><a href="firewall_nat_edit.php?dup=<?=$i;?>"><img src="plus.gif" title="add a new nat based on this one" width="17" height="17" border="0"></a></td>
                      </tr>
                    </table>
		</tr>
  	     <?php $i++; $nnats++; endforeach; ?>
                <tr>
                  <td class="list" colspan="8"></td>
                  <td class="list" valign="middle" nowrap>
                    <table border="0" cellspacing="0" cellpadding="1">
                      <tr>
                        <td><?php if ($nnats == 0): ?><img src="left_d.gif" width="17" height="17" title="move selected mappings to end" border="0"><?php else: ?><input name="move_<?=$i;?>" type="image" src="left.gif" width="17" height="17" title="move selected mappings to end" border="0"><?php endif; ?></td>
                        <td><a href="firewall_nat_edit.php"><img src="plus.gif" width="17" height="17" border="0"></a></td>
                      </tr>
                      <tr>
                        <td><?php if ($nnats == 0): ?><img src="x_d.gif" width="17" height="17" title="delete selected rules" border="0"><?php else: ?><input name="del" type="image" src="x.gif" width="17" height="17" title="delete selected mappings" onclick="return confirm('Do you really want to delete the selected mappings?')"><?php endif; ?></td>
                      </tr>
                    </table></td>
                </tr>              </table>
                    <p><span class="vexpl"><span class="red"><strong>Note:<br>
                      </strong></span>It is not possible to access NATed services
                      using the WAN IP address from within the LAN (or an optional
                      network).</span></p></td>
  </tr>
</table>

<?php
if ($pkg['tabs'] <> "") {
    echo "</td></tr></table>";
}
?>

</form>
<?php include("fend.inc"); ?>
</body>
</html>
