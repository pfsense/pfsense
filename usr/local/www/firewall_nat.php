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
/*
	pfSense_MODULE:	nat
*/

##|+PRIV
##|*IDENT=page-firewall-nat-portforward
##|*NAME=Firewall: NAT: Port Forward page
##|*DESCR=Allow access to the 'Firewall: NAT: Port Forward' page.
##|*MATCH=firewall_nat.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("itemid.inc");

if (!is_array($config['nat']['rule']))
	$config['nat']['rule'] = array();

$a_nat = &$config['nat']['rule'];

/* if a custom message has been passed along, lets process it */
if ($_GET['savemsg'])
	$savemsg = $_GET['savemsg'];

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {

		write_config();

		$retval = 0;

		unlink_if_exists("/tmp/config.cache");
		$retval |= filter_configure();
		$savemsg = get_std_save_message($retval);

		pfSense_handle_custom_code("/usr/local/pkg/firewall_nat/apply");

		if ($retval == 0) {
			clear_subsystem_dirty('natconf');
			clear_subsystem_dirty('filter');
		}

	}
}

if ($_GET['act'] == "del") {
	if ($a_nat[$_GET['id']]) {

		if (isset($a_nat[$_GET['id']]['associated-rule-id'])) {
			delete_id($a_nat[$_GET['id']]['associated-rule-id'], $config['filter']['rule']);
			$want_dirty_filter = true;
		}
		unset($a_nat[$_GET['id']]);

		if (write_config()) {
			mark_subsystem_dirty('natconf');
			if ($want_dirty_filter)
				mark_subsystem_dirty('filter');
		}
		header("Location: firewall_nat.php");
		exit;
	}
}

if (isset($_POST['del_x'])) {
    /* delete selected rules */
    if (is_array($_POST['rule']) && count($_POST['rule'])) {
	    foreach ($_POST['rule'] as $rulei) {
		$target = $rule['target'];
			// Check for filter rule associations
			if (isset($a_nat[$rulei]['associated-rule-id'])){
				delete_id($a_nat[$rulei]['associated-rule-id'], $config['filter']['rule']);
				
				mark_subsystem_dirty('filter');
			}
	        unset($a_nat[$rulei]);
	    }
		if (write_config())
			mark_subsystem_dirty('natconf');
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
		if (write_config())
			mark_subsystem_dirty('natconf');
                header("Location: firewall_nat.php");
                exit;
        }
}

$closehead = false;
$pgtitle = array(gettext("Firewall"),gettext("NAT"),gettext("Port Forward"));
include("head.inc");

echo "<script type=\"text/javascript\" language=\"javascript\" src=\"/javascript/domTT/domLib.js\"></script>";
echo "<script type=\"text/javascript\" language=\"javascript\" src=\"/javascript/domTT/domTT.js\"></script>";
echo "<script type=\"text/javascript\" language=\"javascript\" src=\"/javascript/domTT/behaviour.js\"></script>";
echo "<script type=\"text/javascript\" language=\"javascript\" src=\"/javascript/domTT/fadomatic.js\"></script>";

?>
</head>

<body link="#000000" vlink="#000000" alink="#000000">
<?php include("fbegin.inc"); ?>
<form action="firewall_nat.php" method="post" name="iform">
<script type="text/javascript" language="javascript" src="/javascript/row_toggle.js"></script>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('natconf')): ?>
<?php print_info_box_np(gettext("The NAT configuration has been changed") . ".<br/>" . gettext("You must apply the changes in order for them to take effect."));?><br/>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="firewall nat">
  <tr><td>
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("Port Forward"), true, "firewall_nat.php");
	$tab_array[] = array(gettext("1:1"), false, "firewall_nat_1to1.php");
	$tab_array[] = array(gettext("Outbound"), false, "firewall_nat_out.php");
	$tab_array[] = array(gettext("NPt"), false, "firewall_nat_npt.php");
	display_top_tabs($tab_array);
?>
 </td></tr>
  <tr>
    <td>
	<div id="mainarea">
              <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="main area">
                <tr id="frheader">
		  <td width="3%" class="list">&nbsp;</td>
                  <td width="3%" class="list">&nbsp;</td>
		  <td width="5%" class="listhdrr"><?=gettext("If");?></td>
		  <td width="5%" class="listhdrr"><?=gettext("Proto");?></td>
		  <td width="11%" class="listhdrr nowrap"><?=gettext("Src. addr");?></td>
		  <td width="11%" class="listhdrr nowrap"><?=gettext("Src. ports");?></td>
		  <td width="11%" class="listhdrr nowrap"><?=gettext("Dest. addr");?></td>
		  <td width="11%" class="listhdrr nowrap"><?=gettext("Dest. ports");?></td>
		  <td width="11%" class="listhdrr nowrap"><?=gettext("NAT IP");?></td>
		  <td width="11%" class="listhdrr nowrap"><?=gettext("NAT Ports");?></td>
		  <td width="11%" class="listhdr"><?=gettext("Description");?></td>
                  <td width="5%" class="list">
                    <table border="0" cellspacing="0" cellpadding="1" summary="list">
                      <tr>
			<td width="17">
			<?php if (count($a_nat) == 0): ?>
				<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x_d.gif" width="17" height="17" title="<?=gettext("delete selected rules");?>" border="0" alt="delete" />
			<?php else: ?>
				<input name="del" type="image" src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" style="width:17; height:17" title="<?=gettext("delete selected rules"); ?>" onclick="return confirm('<?=gettext("Do you really want to delete the selected rules?");?>')" />
			<?php endif; ?>
			</td>
                        <td><a href="firewall_nat_edit.php?after=-1"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="add" /></a></td>
                      </tr>
                    </table>
		  </td>
		</tr>
	<?php $nnats = $i = 0; foreach ($a_nat as $natent): ?>
	<?php 
	
		//build Alias popup box
		$span_end = "</U></span>";

		$alias_popup = rule_popup($natent['source']['address'], pprint_port($natent['source']['port']), $natent['destination']['address'], pprint_port($natent['destination']['port']));

		$alias_src_span_begin      = $alias_popup["src"];
		$alias_src_port_span_begin = $alias_popup["srcport"];
		$alias_dst_span_begin      = $alias_popup["dst"];
		$alias_dst_port_span_begin = $alias_popup["dstport"];

		$alias_src_span_end        = $alias_popup["src_end"];
		$alias_src_port_span_end   = $alias_popup["srcport_end"];
		$alias_dst_span_end        = $alias_popup["dst_end"];
		$alias_dst_port_span_end   = $alias_popup["dstport_end"];

		$alias_popup = rule_popup("","",$natent['target'], pprint_port($natent['local-port']));

		$alias_target_span_begin     = $alias_popup["dst"];
		$alias_local_port_span_begin = $alias_popup["dstport"];

		$alias_target_span_end       = $alias_popup["dst_end"];
		$alias_local_port_span_end   = $alias_popup["dstport_end"];

		if (isset($natent['disabled']))
			$textss = "<span class=\"gray\">";
		else
			$textss = "<span>";

		$textse = "</span>";
	
		/* if user does not have access to edit an interface skip on to the next record */
		if(!have_natpfruleint_access($natent['interface'])) 
			continue;
	?>
                <tr valign="top" id="fr<?=$nnats;?>">
                  <td class="listt"><input type="checkbox" id="frc<?=$nnats;?>" name="rule[]" value="<?=$i;?>" onclick="fr_bgcolor('<?=$nnats;?>')" style="margin: 0; padding: 0; width: 15px; height: 15px;" /></td>
                  <td class="listt" align="center">
					<?php if($natent['associated-rule-id'] == "pass"): ?>
					<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_pass.gif" title="<?=gettext("All traffic matching this NAT entry is passed"); ?>" border="0" alt="pass" />
					<?php elseif (!empty($natent['associated-rule-id'])): ?>
					<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_chain.png" width="17" height="17" title="<?=gettext("Firewall rule ID"); ?> <?=htmlspecialchars($nnatid); ?> <?=gettext("is managed with this rule"); ?>" border="0" alt="change" />
					<?php endif; ?>
				  </td>
                  <td class="listlr" onclick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_edit.php?id=<?=$nnats;?>';">
                    <?=$textss;?>
		    <?php
			if (!$natent['interface'])
				echo htmlspecialchars(convert_friendly_interface_to_friendly_descr("wan"));
			else
				echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface']));
		    ?>
                    <?=$textse;?>
                  </td>

                  <td class="listr" onclick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_edit.php?id=<?=$nnats;?>';">
					<?=$textss;?><?=strtoupper($natent['protocol']);?><?=$textse;?>
                  </td>

                  <td class="listr" onclick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_edit.php?id=<?=$nnats;?>';">
				    <?=$textss;?><?php echo $alias_src_span_begin;?><?php echo htmlspecialchars(pprint_address($natent['source']));?><?php echo $alias_src_span_end;?><?=$textse;?>
                  </td>
                  <td class="listr" onclick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_edit.php?id=<?=$nnats;?>';">
				    <?=$textss;?><?php echo $alias_src_port_span_begin;?><?php echo htmlspecialchars(pprint_port($natent['source']['port']));?><?php echo $alias_src_port_span_end;?><?=$textse;?>
                  </td>

                  <td class="listr" onclick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_edit.php?id=<?=$nnats;?>';">
				    <?=$textss;?><?php echo $alias_dst_span_begin;?><?php echo htmlspecialchars(pprint_address($natent['destination']));?><?php echo $alias_dst_span_end;?><?=$textse;?>
                  </td>
                  <td class="listr" onclick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_edit.php?id=<?=$nnats;?>';">
				    <?=$textss;?><?php echo $alias_dst_port_span_begin;?><?php echo htmlspecialchars(pprint_port($natent['destination']['port']));?><?php echo $alias_dst_port_span_end;?><?=$textse;?>
                  </td>

                  <td class="listr" onclick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_edit.php?id=<?=$nnats;?>';">
				    <?=$textss;?><?php echo $alias_target_span_begin;?><?php echo htmlspecialchars($natent['target']);?><?php echo $alias_target_span_end;?><?=$textse;?>
                  </td>
                  <td class="listr" onclick="fr_toggle(<?=$nnats;?>)" id="frd<?=$nnats;?>" ondblclick="document.location='firewall_nat_edit.php?id=<?=$nnats;?>';">
					<?php
						$localport = $natent['local-port'];

						list($dstbeginport, $dstendport) = explode("-", $natent['destination']['port']);

						if ($dstendport) {
							$localendport = $natent['local-port'] + $dstendport - $dstbeginport;
							$localport   .= '-' . $localendport;
						}
					?>
				    <?=$textss;?><?php echo $alias_local_port_span_begin;?><?php echo htmlspecialchars(pprint_port($localport));?><?php echo $alias_local_port_span_end;?><?=$textse;?>
                  </td>

                  <td class="listbg" onclick="fr_toggle(<?=$nnats;?>)" ondblclick="document.location='firewall_nat_edit.php?id=<?=$nnats;?>';">
				  <?=$textss;?><?=htmlspecialchars($natent['descr']);?>&nbsp;<?=$textse;?>
                  </td>
                  <td valign="middle" class="list nowrap">
                    <table border="0" cellspacing="0" cellpadding="1" summary="move">
                      <tr>
			<td><input onmouseover="fr_insline(<?=$nnats;?>, true)" onmouseout="fr_insline(<?=$nnats;?>, false)" name="move_<?=$i;?>" src="/themes/<?= $g['theme']; ?>/images/icons/icon_left.gif" title="<?=gettext("move selected rules before this rule");?>" type="image" style="width:17; height:17; border:0" /></td>
                        <td><a href="firewall_nat_edit.php?id=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" title="<?=gettext("edit rule"); ?>" alt="edit" /></a></td>
                      </tr>
                      <tr>
					    <td align="center" valign="middle"><a href="firewall_nat.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this rule?");?>')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" title="<?=gettext("delete rule");?>" alt="delete" /></a></td>
			<td><a href="firewall_nat_edit.php?dup=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("add a new NAT based on this one");?>" width="17" height="17" border="0" alt="add" /></a></td>
                      </tr>
                    </table>
				  </td>
		</tr>
  	     <?php $i++; $nnats++; endforeach; ?>
                <tr>
                  <td class="list" colspan="8"></td>
                  <td>&nbsp;</td>
                  <td>&nbsp;</td>
                  <td>&nbsp;</td>
                  <td class="list nowrap" valign="middle">
                    <table border="0" cellspacing="0" cellpadding="1" summary="move">
                      <tr>
			<td><?php if ($nnats == 0): ?><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_left_d.gif" width="17" height="17" title="<?=gettext("move selected rules to end"); ?>" border="0" alt="move" /><?php else: ?><input name="move_<?=$i;?>" type="image" src="/themes/<?= $g['theme']; ?>/images/icons/icon_left.gif" style="width:17;height:17;border:0" title="<?=gettext("move selected rules to end");?>" /><?php endif; ?></td>
                      </tr>
                      <tr>
			<td width="17">
			<?php if (count($a_nat) == 0): ?>
				<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x_d.gif" width="17" height="17" title="<?=gettext("delete selected rules");?>" border="0" alt="delete" />
			<?php else: ?>
				<input name="del" type="image" src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" style="width:17; height:17" title="<?=gettext("delete selected rules"); ?>" onclick="return confirm('<?=gettext("Do you really want to delete the selected rules?");?>')" />
			<?php endif; ?>
			</td>
                        <td><a href="firewall_nat_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="add" /></a></td>
                      </tr>
                    </table>
		  </td>
		</tr>
		<tr><td>&nbsp;</td></tr>
          <tr>
            <td width="16"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_pass.gif" width="11" height="11" alt="pass" /></td>
            <td colspan="3"><?=gettext("pass"); ?></td>
			</tr>
		   <tr>
            <td width="14"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_chain.png" width="11" height="11" alt="chain" /></td>
	    <td colspan="3"><?=gettext("linked rule");?></td>
          </tr>
    </table>
	</div>
	</td>
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
