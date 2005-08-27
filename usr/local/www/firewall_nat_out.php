#!/usr/local/bin/php
<?php
/* $Id$ */
/*
    firewall_nat_out.php
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

if (!is_array($config['nat']['advancedoutbound']['rule']))
    $config['nat']['advancedoutbound']['rule'] = array();

$a_out = &$config['nat']['advancedoutbound']['rule'];

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



if (isset($_POST['save'])) {
        
        /* mutually exclusive settings - if user wants advanced NAT, we don't help with IPSec */
        if ($_POST['ipsecpassthru'] == true) {
                $config['nat']['ipsecpassthru']['enable'] = true;
                $config['nat']['advancedoutbound']['enable'] = false;
        }
        if ($_POST['advancedoutbound'] == true) {
                $config['nat']['advancedoutbound']['enable'] = true;
                $config['nat']['ipsecpassthru']['enable'] = false;
        }
        if ($_POST['ipsecpassthru'] == false)
                $config['nat']['ipsecpassthru']['enable'] = false;
        if ($_POST['advancedoutbound'] == false)
                $config['nat']['advancedoutbound']['enable'] = false;
        if($config['nat']['advancedoutbound']['enable'] and $_POST['advancedoutbound'] <> "") {
                /*
                 *    user has enabled advanced outbound nat -- lets automatically create entries
                 *    for all of the interfaces to make life easier on the pip-o-chap
                 */
                $a_out = &$config['nat']['advancedoutbound']['rule'];
                $ifdescrs = array('lan');
                for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) 
                        $ifdescrs[] = "opt" . $j;
                foreach($ifdescrs as $if) {
			if($if <> "lan" and $if <> "wan") {
				/* interface is an optional.  is it enabled? */
				if(!isset($config['interfaces'][$if]['enabled'])) {
					continue;
				}
			}
                        $natent = array();
                        $osn = convert_ip_to_network_format($config['interfaces'][$if]['ipaddr'],
                                $config['interfaces'][$if]['subnet']);
                        $natent['source']['network'] = $osn;
                        $natent['sourceport'] = "";
                        $natent['descr'] = "Auto created rule for {$if}";
                        $natent['target'] = "";
                        $natent['interface'] = "wan";
                        $natent['destination']['any'] = true;
                        $natent['natport'] = "";
                        $a_out[] = $natent;
                }
                $savemsg = "Default rules for each interface have been created.";
        }
        write_config();
        touch($d_natconfdirty_path);
        header("Location: firewall_nat_out.php");
        exit;
}

if (isset($_POST['del_x'])) {
        /* delete selected rules */
        if (is_array($_POST['rule']) && count($_POST['rule'])) {
                foreach ($_POST['rule'] as $rulei) {
                        unset($a_out[$rulei]);
                }
                write_config();
                touch($d_natconfdirty_path);
                header("Location: firewall_nat_out.php");
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
                $a_out_new = array();

                /* copy all rules < $movebtn and not selected */
                for ($i = 0; $i < $movebtn; $i++) {
                        if (!in_array($i, $_POST['rule']))
                                $a_out_new[] = $a_out[$i];
                }

                /* copy all selected rules */
                for ($i = 0; $i < count($a_out); $i++) {
                        if ($i == $movebtn)
                                continue;
                        if (in_array($i, $_POST['rule']))
                                $a_out_new[] = $a_out[$i];
                }

                /* copy $movebtn rule */
                if ($movebtn < count($a_out))
                        $a_out_new[] = $a_out[$movebtn];

                /* copy all rules > $movebtn and not selected */
                for ($i = $movebtn+1; $i < count($a_out); $i++) {
                        if (!in_array($i, $_POST['rule']))
                                $a_out_new[] = $a_out[$i];
                }
                $a_out = $a_out_new;
                write_config();
                touch($d_natconfdirty_path);
                header("Location: firewall_nat_out.php");
                exit;
        }
}


$pgtitle = "Firewall: NAT: Outbound";
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Firewall: NAT: Outbound</p>
<form action="firewall_nat_out.php" method="post" name="iform">
<script type="text/javascript" language="javascript" src="row_toggle.js">
</script>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_natconfdirty_path)): ?><p>
<?php print_info_box_np("The NAT configuration has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">  <tr><td>
<?php
	$tab_array = array();
	$tab_array[] = array("Port Forward", false, "firewall_nat.php");
	$tab_array[] = array("1:1", false, "firewall_nat_1to1.php");
	$tab_array[] = array("Outbound", true, "firewall_nat_out.php");
	display_top_tabs($tab_array);
?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
              <table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
              <tr>
                  <td class="vtable"><p>
                      <input name="ipsecpassthru" type="checkbox" id="ipsecpassthru" value="yes" onClick="document.iform.advancedoutbound.checked=false" <?php if (isset($config['nat']['ipsecpassthru']['enable'])) echo "checked";?>>
                      <strong>Enable IPSec passthru</strong></p>
                  </td>
                </tr>
                <tr>
                  <td class="vtable"><p>
                      <input name="advancedoutbound" type="checkbox" id="advancedoutbound" value="yes" onClick="document.iform.ipsecpassthru.checked=false" <?php if (isset($config['nat']['advancedoutbound']['enable'])) echo "checked";?>>
                      <strong>Enable advanced outbound NAT</strong></p></td>
                </tr>
                <tr>
                  <td> <input name="save" type="submit" class="formbtn" value="Save">
                  </td>
                </tr>
                <tr>
                  <td colspan="2"><p><span class="vexpl"><span class="red"><strong>Note:<br>
                      </strong></span>If advanced outbound NAT is enabled, no outbound NAT
                      rules will be automatically generated anymore. Instead, only the mappings
                      you specify below will be used. With advanced outbound NAT disabled,
                      a mapping is automatically created for each interface's subnet
                      (except WAN).</span> If you use target addresses other than the WAN interface's IP address, then depending on<span class="vexpl"> the way your WAN connection is setup, you may also need a <a href="firewall_virtual_ip.php">Virtual IP</a>.</span><br>
                      <br>
                      You may enter your own mappings below.</p>
                    </td>
                </tr>
              </table>
              <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr id="frheader">
                  <td width="3%" class="list">&nbsp;</td>
                  <td width="3%" class="list">&nbsp;</td>
                  <td width="10%" class="listhdrr">Interface</td>
                  <td width="20%" class="listhdrr">Source</td>
                  <td width="20%" class="listhdrr">Source Port</td>
                  <td width="20%" class="listhdrr">Destination</td>
                  <td width="20%" class="listhdrr">NAT Port</td>
                  <td width="20%" class="listhdrr">Target</td>
                  <td width="25%" class="listhdr">Description</td>
                  <td width="5%" class="list"></td>
                </tr>
              <?php $nnats = $i = 0; foreach ($a_out as $natent): ?>
                <tr valign="top" id="fr<?=$nnats;?>">
                  <td class="listt"><input type="checkbox" id="frc<?=$nnats;?>" name="rule[]" value="<?=$i;?>" onClick="fr_bgcolor('<?=$nnats;?>')" style="margin: 0; padding: 0; width: 15px; height: 15px;"></td>
                  <td class="listt" align="center"></td>
                  <td class="listlr" onClick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$nnats;?>';">
                    <?php
					if (!$natent['interface'] || ($natent['interface'] == "wan"))
					  	echo "WAN";
					else
						echo htmlspecialchars($config['interfaces'][$natent['interface']]['descr']);
					?>
                                        &nbsp;
                  </td>
                  <td class="listr" onClick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$nnats;?>';">
                    <?=$natent['source']['network'];?>
                  </td>
                  <td class="listr" onClick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$nnats;?>';">
                    <?php
                      if (!$natent['sourceport'])
                          echo "*";
                      else
                          echo $natent['sourceport'];
                    ?>
                  </td>
                  <td class="listr" onClick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$nnats;?>';">
                    <?php
                      if (isset($natent['destination']['any']))
                          echo "*";
                      else {
                          if (isset($natent['destination']['not']))
                              echo "!&nbsp;";
                          echo $natent['destination']['network'];
                      }
                    ?>
                  </td>
                  <td class="listr" onClick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$nnats;?>';">
                    <?php
                      if (!$natent['natport'])
                          echo "*";
                      else
                          echo $natent['natport'];
                    ?>
                  </td>
                  <td class="listr" onClick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$nnats;?>';">
                    <?php
                      if (!$natent['target'])
                          echo "*";
                      else
                          echo $natent['target'];
                    ?>
                  </td>
                  <td class="listbg"  onClick="fr_toggle(<?=$nnats;?>)" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$nnats;?>';">
                    <font color="#FFFFFF"><?=htmlspecialchars($natent['descr']);?>&nbsp;
                  </td>
                  <td class="list" valign="middle" nowrap>
                    <table border="0" cellspacing="0" cellpadding="1">
                      <tr>
                        <td><a href="firewall_nat_out_edit.php?id=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" title="edit mapping"></a></td>
                      </tr>
                      <tr>
                        <td><input onmouseover="fr_insline(<?=$nnats;?>, true)" onmouseout="fr_insline(<?=$nnats;?>, false)" name="move_<?=$i;?>" src="/themes/<?= $g['theme']; ?>/images/icons/icon_left.gif" title="move selected rules before this rule" height="17" type="image" width="17" border="0"></td>
                        <td><a href="firewall_nat_out_edit.php?dup=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="add a new nat based on this one" width="17" height="17" border="0"></a></td>
                      </tr>
                    </table>
              <?php $i++; $nnats++; endforeach; ?>
                <tr>
                  <td class="list" colspan="9"></td>
                  <td class="list" valign="middle" nowrap>
                    <table border="0" cellspacing="0" cellpadding="1">
                      <tr>
                        <td><?php if ($nnats == 0): ?><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_left_d.gif" width="17" height="17" title="move selected mappings to end" border="0"><?php else: ?><input name="move_<?=$i;?>" type="image" src="/themes/<?= $g['theme']; ?>/images/icons/icon_left.gif" width="17" height="17" title="move selected mappings to end" border="0"><?php endif; ?></td>
                        <td><a href="firewall_nat_out_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="add new mapping"></a></td>
                      </tr>
                      <tr>
                        <td><?php if ($nnats == 0): ?><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x_d.gif" width="17" height="17" title="delete selected rules" border="0"><?php else: ?><input name="del" type="image" src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" title="delete selected mappings" onclick="return confirm('Do you really want to delete the selected mappings?')"><?php endif; ?></td>
                      </tr>
                    </table></td>
                </tr>
              </table>
	    </div>
</td>
  </tr>
</table>
            </form>
<?php include("fend.inc"); ?>
</body>
</html>
