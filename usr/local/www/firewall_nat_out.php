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
/*
	pfSense_MODULE:	nat
*/

##|+PRIV
##|*IDENT=page-firewall-nat-outbound
##|*NAME=Firewall: NAT: Outbound page
##|*DESCR=Allow access to the 'Firewall: NAT: Outbound' page.
##|*MATCH=firewall_nat_out.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

if (!is_array($config['nat']['advancedoutbound']))
	$config['nat']['advancedoutbound'] = array();

if (!is_array($config['nat']['advancedoutbound']['rule']))
	$config['nat']['advancedoutbound']['rule'] = array();

$a_out = &$config['nat']['advancedoutbound']['rule'];

if ($_POST['apply']) {
	write_config();

	$retval = 0;
	$retval |= filter_configure();

	if(stristr($retval, "error") <> true)
	        $savemsg = get_std_save_message($retval);
	else
		$savemsg = $retval;

	if ($retval == 0) {
		clear_subsystem_dirty('natconf');
		clear_subsystem_dirty('filter');
        }
}



if (isset($_POST['save']) && $_POST['save'] == "Save") {
	/* mutually exclusive settings - if user wants advanced NAT, we don't generate automatic rules */
	switch ($_POST['advancedoripsec']) {
	case "ipsecpassthru":
               	$config['nat']['ipsecpassthru']['enable'] = true;
               	unset($config['nat']['advancedoutbound']['enable']);
		break;
	case "advancedoutboundnat":
        	if (!isset($config['nat']['advancedoutbound']['enable'])) {
			$config['nat']['advancedoutbound']['enable'] = true;
			// if there are already AON rules configured, don't generate default ones
			if(!empty($a_out))
				continue;
			/*
			 *    user has enabled advanced outbound NAT and doesn't have rules
			 *    lets automatically create entries
			 *    for all of the interfaces to make life easier on the pip-o-chap
			 */
			$ifdescrs = get_configured_interface_with_descr();
				
			foreach($ifdescrs as $if => $ifdesc) {
				if (substr(get_real_interface($if), 0, 4) == "ovpn")
					continue;
				if (!interface_has_gateway($if))
					continue;
				foreach ($ifdescrs as $if2 => $ifdesc2) {
					if (interface_has_gateway($if2))
						continue;

					$osipaddr = get_interface_ip($if2);
					$ossubnet = get_interface_subnet($if2);
					if (!is_ipaddr($osipaddr) || empty($ossubnet))
						continue;
					$osn = gen_subnet($osipaddr, $ossubnet);

					$natent = array();
					$natent['source']['network'] = "{$osn}/{$ossubnet}";
					$natent['dstport'] = "500";
					$natent['descr'] = sprintf(gettext('Auto created rule for ISAKMP - %1$s to %2$s'),$ifdesc2,$ifdesc);
					$natent['target'] = "";
					$natent['interface'] = $if;
					$natent['destination']['any'] = true;
					$natent['staticnatport'] = true;
					$natent['created'] = make_config_revision_entry(null, gettext("Manual Outbound NAT Switch"));
					$a_out[] = $natent;

					$natent = array();
					$natent['source']['network'] = "{$osn}/{$ossubnet}";
					$natent['sourceport'] = "";
					$natent['descr'] = sprintf(gettext('Auto created rule for %1$s to %2$s'),$ifdesc2,$ifdesc);
					$natent['target'] = "";
					$natent['interface'] = $if;
					$natent['destination']['any'] = true;
					$natent['natport'] = "";
					$natent['created'] = make_config_revision_entry(null, gettext("Manual Outbound NAT Switch"));
					$a_out[] = $natent;
				}
				/* Localhost */
				$natent = array();
				$natent['source']['network'] = "127.0.0.0/8";
				$natent['dstport'] = "";
				$natent['descr'] = sprintf(gettext('Auto created rule for localhost to %1$s'),$ifdesc);
				$natent['target'] = "";
				$natent['interface'] = $if;
				$natent['destination']['any'] = true;
				$natent['staticnatport'] = false;
				$natent['natport'] = "1024:65535";
				$natent['created'] = make_config_revision_entry(null, gettext("Manual Outbound NAT Switch"));
				$a_out[] = $natent;
				/* PPTP subnet */
				if (($config['pptpd']['mode'] == "server") && is_private_ip($config['pptpd']['remoteip'])) {
					$pptp_size = empty($config['pptpd']['n_pptp_units']) ? 16 : $config['pptpd']['n_pptp_units'];
					$pptptopip = $pptp_size - 1;
					$pptp_subnets = ip_range_to_subnet_array($config['pptpd']['remoteip'], long2ip32(ip2long($config['pptpd']['remoteip'])+$pptptopip));
					foreach ($pptp_subnets as $pptpsn) {
						$natent = array();
						$natent['source']['network'] = $pptpsn;
						$natent['sourceport'] = "";
						$natent['descr'] = gettext("Auto created rule for PPTP server");
						$natent['target'] = "";
						$natent['interface'] = $if;
						$natent['destination']['any'] = true;
						$natent['natport'] = "";
						$natent['created'] = make_config_revision_entry(null, gettext("Manual Outbound NAT Switch"));
						$a_out[] = $natent;
					}
				}
				/* PPPoE subnet */
				if (is_pppoe_server_enabled() && have_ruleint_access("pppoe")) {
					foreach ($config['pppoes']['pppoe'] as $pppoes) {
						if (($pppoes['mode'] == "server") && is_ipaddr($pppoes['localip'])) {
							if($pppoes['pppoe_subnet'] <> "")
								$ossubnet = $pppoes['pppoe_subnet'];
							else
								$ossubnet = "32";
							$osn = gen_subnet($pppoes['localip'], $ossubnet);
							$natent = array();
							$natent['source']['network'] = "{$osn}/{$ossubnet}";
							$natent['sourceport'] = "";
							$natent['descr'] = gettext("Auto created rule for PPPoE server");
							$natent['target'] = "";
							$natent['interface'] = $if;
							$natent['destination']['any'] = true;
							$natent['natport'] = "";
							$natent['created'] = make_config_revision_entry(null, gettext("Manual Outbound NAT Switch"));
							$a_out[] = $natent;
						}
					}
				}
				/* L2TP subnet */
				if($config['l2tp']['mode'] == "server") {
					if (is_ipaddr($config['l2tp']['localip'])) {
						if($config['l2tp']['l2tp_subnet'] <> "")
							$ossubnet = $config['l2tp']['l2tp_subnet'];
						else
							$ossubnet = "32";
						$osn = gen_subnet($config['l2tp']['localip'], $ossubnet);
						$natent = array();
						$natent['source']['network'] = "{$osn}/{$ossubnet}";
						$natent['sourceport'] = "";
						$natent['descr'] = gettext("Auto created rule for L2TP server");
						$natent['target'] = "";
						$natent['interface'] = $if;
						$natent['destination']['any'] = true;
						$natent['natport'] = "";
						$natent['created'] = make_config_revision_entry(null, gettext("Manual Outbound NAT Switch"));
						$a_out[] = $natent;
					}
				}
				/* add openvpn interfaces */
				if($config['openvpn']['openvpn-server']) {
					foreach ($config['openvpn']['openvpn-server'] as $ovpnsrv) {
						$natent = array();
						$natent['source']['network'] = $ovpnsrv['tunnel_network'];
						$natent['sourceport'] = "";
						$natent['descr'] = gettext("Auto created rule for OpenVPN server");
						$natent['target'] = "";
						$natent['interface'] = $if;
						$natent['destination']['any'] = true;
						$natent['natport'] = "";
						$natent['created'] = make_config_revision_entry(null, gettext("Manual Outbound NAT Switch"));
						$a_out[] = $natent;
					}
				}
			}

			$savemsg = gettext("Default rules for each interface have been created.");
		}
		break;
	}
	if (write_config())
		mark_subsystem_dirty('natconf');
        header("Location: firewall_nat_out.php");
        exit;
}

if ($_GET['act'] == "del") {
	if ($a_out[$_GET['id']]) {
		unset($a_out[$_GET['id']]);
		if (write_config())
			mark_subsystem_dirty('natconf');
		header("Location: firewall_nat_out.php");
		exit;
	}
}

if (isset($_POST['del_x'])) {
        /* delete selected rules */
        if (is_array($_POST['rule']) && count($_POST['rule'])) {
                foreach ($_POST['rule'] as $rulei) {
                        unset($a_out[$rulei]);
                }
		if (write_config())
			mark_subsystem_dirty('natconf');
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
                if (count($a_out_new) > 0)
			$a_out = $a_out_new;
		else
			unset($config['nat']['advancedoutbound']);

		if (write_config())
			mark_subsystem_dirty('natconf');
                header("Location: firewall_nat_out.php");
                exit;
        }
}


$pgtitle = array(gettext("Firewall"),gettext("NAT"),gettext("Outbound"));
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="firewall_nat_out.php" method="post" name="iform">
<script type="text/javascript" language="javascript" src="/javascript/row_toggle.js"></script>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('natconf')): ?>
<?php print_info_box_np(gettext("The NAT configuration has been changed.")."<br/>".gettext("You must apply the changes in order for them to take effect."));?><br/>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="firwall nat outbound">  <tr><td>
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("Port Forward"), false, "firewall_nat.php");
	$tab_array[] = array(gettext("1:1"), false, "firewall_nat_1to1.php");
	$tab_array[] = array(gettext("Outbound"), true, "firewall_nat_out.php");
	$tab_array[] = array(gettext("NPt"), false, "firewall_nat_npt.php");
	display_top_tabs($tab_array);
?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
              <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="main area">
				<tr><td align="right"><b><?=gettext("Mode:"); ?></b></td>
                  <td>
                      &nbsp;&nbsp;<input name="advancedoripsec" type="radio" id="ipsecpassthru" value="ipsecpassthru" <?php if (isset($config['nat']['ipsecpassthru']['enable'])) echo "checked=\"checked\"";?> />
                      <strong><?=gettext("Automatic outbound NAT rule generation"); ?><br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?=gettext("(IPsec passthrough included)");?></strong>
                  </td>

                  <td>
                      &nbsp;&nbsp;<input name="advancedoripsec" type="radio" id="advancedoutbound" value="advancedoutboundnat" <?php if (isset($config['nat']['advancedoutbound']['enable'])) echo "checked=\"checked\"";?> />
                      <strong><?=gettext("Manual Outbound NAT rule generation") . "<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . gettext("(AON - Advanced Outbound NAT)");?></strong></td>
                  <td valign="middle" align="left">
					<input name="save" type="submit" class="formbtn" value="<?=gettext("Save");?>" />
					&nbsp;<br/>&nbsp;
                  </td>
                </tr>
				<tr>
					<td colspan="5">
						&nbsp;
					</td>
				</tr>
				<tr>
					<td  class="vtable" colspan="5">
						&nbsp;
					</td>
				</tr>
              </table>
              <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="mappings">
				<tr><td colspan="5"><b>&nbsp;<?=gettext("Mappings:"); ?></b></td></tr>
				<tr><td>&nbsp;</td></tr>
                <tr id="frheader">
                  <td width="3%" class="list">&nbsp;</td>
                  <td width="3%" class="list">&nbsp;</td>
                  <td width="10%" class="listhdrr"><?=gettext("Interface");?></td>
                  <td width="15%" class="listhdrr"><?=gettext("Source");?></td>
                  <td width="10%" class="listhdrr"><?=gettext("Source Port");?></td>
                  <td width="15%" class="listhdrr"><?=gettext("Destination");?></td>
                  <td width="10%" class="listhdrr"><?=gettext("Destination Port");?></td>
                  <td width="15%" class="listhdrr"><?=gettext("NAT Address");?></td>
                  <td width="10%" class="listhdrr"><?=gettext("NAT Port");?></td>
		  <td width="10%" class="listhdrr"><?=gettext("Static Port");?></td>
                  <td width="25%" class="listhdr"><?=gettext("Description");?></td>
                  <td width="5%" class="list">
                    <table border="0" cellspacing="0" cellpadding="1" summary="add">
                      <tr>
			<td width="17"></td>
                        <td><a href="firewall_nat_out_edit.php?after=-1"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="<?=gettext("add new mapping");?>" alt="add" /></a></td>
                      </tr>
                    </table>
		  </td>
                </tr>
              <?php $nnats = $i = 0; foreach ($a_out as $natent): ?>
                <tr valign="top" id="fr<?=$nnats;?>">
                  <td class="listt"><input type="checkbox" id="frc<?=$nnats;?>" name="rule[]" value="<?=$i;?>" onclick="fr_bgcolor('<?=$nnats;?>')" style="margin: 0; padding: 0; width: 15px; height: 15px;" /></td>
                  <td class="listt" align="center"></td>
                  <td class="listlr" onclick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$nnats;?>';">
                    <?php echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface'])); ?>
                                        &nbsp;
                  </td>
                  <td class="listr" onclick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$nnats;?>';">
                    <?=$natent['source']['network'];?>
                  </td>
                  <td class="listr" onclick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$nnats;?>';">
                    <?php
			echo ($natent['protocol']) ? $natent['protocol'] . '/' : "" ;
                      if (!$natent['sourceport'])
                          echo "*";
                      else
                          echo $natent['sourceport'];
                    ?>
                  </td>
                  <td class="listr" onclick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$nnats;?>';">
                    <?php
                      if (isset($natent['destination']['any']))
                          echo "*";
                      else {
                          if (isset($natent['destination']['not']))
                              echo "!&nbsp;";
                          echo $natent['destination']['address'];
                      }
                    ?>
                  </td>
                  <td class="listr" onclick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$nnats;?>';">
                    <?php
			echo ($natent['protocol']) ? $natent['protocol'] . '/' : "" ;
                      if (!$natent['dstport'])
                          echo "*";
                      else
                          echo $natent['dstport'];
                    ?>
                  </td>
                  <td class="listr" onclick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$nnats;?>';">
                    <?php
					if (isset($natent['nonat']))
						echo '<I>NO NAT</I>';
					elseif (!$natent['target'])
						echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface'])) . " address";
					elseif ($natent['target'] == "other-subnet")
						echo $natent['targetip'] . '/' . $natent['targetip_subnet'];
					else
						echo $natent['target'];
                    ?>
                  </td>
                  <td class="listr" onclick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$nnats;?>';">
                    <?php
                      if (!$natent['natport'])
                          echo "*";
                      else
                          echo $natent['natport'];
                    ?>
                  </td>
                  <td class="listr" onclick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$nnats;?>';" align="center">
                    <?php
			if(isset($natent['staticnatport']))
			    echo gettext("YES");
			else
			    echo gettext("NO");
                    ?>		    
                  </td>
                  <td class="listbg" onclick="fr_toggle(<?=$nnats;?>)" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$nnats;?>';">
                    <?=htmlspecialchars($natent['descr']);?>&nbsp;
                  </td>
                  <td class="list nowrap" valign="middle">
                    <table border="0" cellspacing="0" cellpadding="1" summary="move">
                      <tr>
                        <td><input onmouseover="fr_insline(<?=$nnats;?>, true)" onmouseout="fr_insline(<?=$nnats;?>, false)" name="move_<?=$i;?>" src="/themes/<?= $g['theme']; ?>/images/icons/icon_left.gif" title="<?=gettext("move selected rules before this rule");?>" type="image" style="height:17;width:17;border:0" /></td>
                        <td><a href="firewall_nat_out_edit.php?id=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" title="<?=gettext("edit mapping");?>" alt="edit" /></a></td>
                      </tr>
                      <tr>
			<td align="center" valign="middle"><a href="firewall_nat_out.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this rule?");?>')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" title="<?=gettext("delete rule");?>" alt="delete" /></a></td>
                        <td><a href="firewall_nat_out_edit.php?dup=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("add a new NAT based on this one");?>" width="17" height="17" border="0" alt="duplicate" /></a></td>
                      </tr>
                    </table>
              <?php $i++; $nnats++; endforeach; ?>
                <tr>
                  <td class="list" colspan="11"></td>
                  <td class="list nowrap" valign="middle">
                    <table border="0" cellspacing="0" cellpadding="1" summary="edit">
                      <tr>
                        <td><?php if ($nnats == 0): ?><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_left_d.gif" width="17" height="17" title="<?=gettext("move selected mappings to end");?>" border="0" alt="move" /><?php else: ?><input name="move_<?=$i;?>" type="image" src="/themes/<?= $g['theme']; ?>/images/icons/icon_left.gif" style="width:17;height:17;border:0" title="<?=gettext("move selected mappings to end");?>" /><?php endif; ?></td>
                        <td><a href="firewall_nat_out_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="<?=gettext("add new mapping");?>" alt="add" /></a></td>
                      </tr>
                      <tr>
                        <td><?php if ($nnats == 0): ?><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x_d.gif" width="17" height="17" title="<?=gettext("delete selected rules");?>" border="0" alt="delete" /><?php else: ?><input name="del" type="image" src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" style="width:17;height:17" title="<?=gettext("delete selected mappings");?>" onclick="return confirm('<?=gettext("Do you really want to delete the selected mappings?");?>')" /><?php endif; ?></td>
                      </tr>
                    </table></td>
                </tr>
                <tr>
                  <td colspan="12">
			<p><span class="vexpl"><span class="red"><strong><?=gettext("Note:"); ?><br/>
			</strong></span>
			<?=gettext("With automatic outbound NAT enabled, a mapping is automatically created " .
			"for each interface's subnet (except WAN-type connections) and the rules " .
			"on this page are ignored.<br/><br/> " .
			"If manual outbound NAT is enabled, outbound NAT rules will not be " .
			"automatically generated and only the mappings you specify on this page " .
			"will be used. <br/><br/> " .
			"If a target address other than a WAN-type interface's IP address is used, " .
			"then depending on the way the WAN connection is setup, a "); ?>
			<a href="firewall_virtual_ip.php"><?=gettext("Virtual IP"); ?></a>
			<?= gettext(" may also be required.") ?>
			<br/><br/>
			<?= gettext("To completely disable outbound NAT, switch to Manual Outbound NAT then delete any " .
			"NAT rules that appear in the list.") ?></span></p>
                    </td>
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
