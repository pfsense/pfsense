#!/usr/local/bin/php
<?php 
/*
    firewall_nat_out_edit.php
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

if (!is_array($config['nat']['advancedoutbound']['rule']))
    $config['nat']['advancedoutbound']['rule'] = array();
    
$a_out = &$config['nat']['advancedoutbound']['rule'];
nat_out_rules_sort();

$id = $_GET['id'];
if (isset($_POST['id']))
    $id = $_POST['id'];

function network_to_pconfig($adr, &$padr, &$pmask, &$pnot) {

    if (isset($adr['any']))
        $padr = "any";
    else if ($adr['network']) {
        list($padr, $pmask) = explode("/", $adr['network']);
        if (!$pmask)
            $pmask = 32;
    }

    if (isset($adr['not']))
        $pnot = 1;
    else
        $pnot = 0;
}

if (isset($id) && $a_out[$id]) {
    list($pconfig['source'],$pconfig['source_subnet']) = explode('/', $a_out[$id]['source']['network']);
    network_to_pconfig($a_out[$id]['destination'], $pconfig['destination'],
	   $pconfig['destination_subnet'], $pconfig['destination_not']);
    $pconfig['target'] = $a_out[$id]['target'];
    $pconfig['interface'] = $a_out[$id]['interface'];
	if (!$pconfig['interface'])
		$pconfig['interface'] = "wan";
    $pconfig['descr'] = $a_out[$id]['descr'];
} else {
    $pconfig['source_subnet'] = 24;
    $pconfig['destination'] = "any";
    $pconfig['destination_subnet'] = 24;
	$pconfig['interface'] = "wan";
}

if ($_POST) {
    
    if ($_POST['destination_type'] == "any") {
        $_POST['destination'] = "any";
        $_POST['destination_subnet'] = 24;
    }
    
    unset($input_errors);
    $pconfig = $_POST;

    /* input validation */
    $reqdfields = explode(" ", "interface source source_subnet destination destination_subnet");
    $reqdfieldsn = explode(",", "Interface,Source,Source bit count,Destination,Destination bit count");
    
    do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

    if ($_POST['source'] && !is_ipaddr($_POST['source'])) {
        $input_errors[] = "A valid source must be specified.";
    }
    if ($_POST['source_subnet'] && !is_numericint($_POST['source_subnet'])) {
        $input_errors[] = "A valid source bit count must be specified.";
    }
    if ($_POST['destination_type'] != "any") {
        if ($_POST['destination'] && !is_ipaddr($_POST['destination'])) {
            $input_errors[] = "A valid destination must be specified.";
        }
        if ($_POST['destination_subnet'] && !is_numericint($_POST['destination_subnet'])) {
            $input_errors[] = "A valid destination bit count must be specified.";
        }
    }
    if ($_POST['target'] && !is_ipaddr($_POST['target'])) {
        $input_errors[] = "A valid target IP address must be specified.";
    }
    
    /* check for existing entries */
    $osn = gen_subnet($_POST['source'], $_POST['source_subnet']) . "/" . $_POST['source_subnet'];
    if ($_POST['destination_type'] == "any")
        $ext = "any";
    else
        $ext = gen_subnet($_POST['destination'], $_POST['destination_subnet']) . "/"
            . $_POST['destination_subnet'];
			
	if ($_POST['target']) {
		/* check for clashes with 1:1 NAT (Server NAT is OK) */
		if (is_array($config['nat']['onetoone'])) {
			foreach ($config['nat']['onetoone'] as $natent) {
				if (check_subnets_overlap($_POST['target'], 32, $natent['external'], $natent['subnet'])) {
					$input_errors[] = "A 1:1 NAT mapping overlaps with the specified target IP address.";
					break;
				}
			}
		}
	}
    
    foreach ($a_out as $natent) {
        if (isset($id) && ($a_out[$id]) && ($a_out[$id] === $natent))
            continue;
        
		if (!$natent['interface'])
			$natent['interface'] == "wan";
		
		if (($natent['interface'] == $_POST['interface']) && ($natent['source']['network'] == $osn)) {
			if (isset($natent['destination']['not']) == isset($_POST['destination_not'])) {
				if ((isset($natent['destination']['any']) && ($ext == "any")) ||
						($natent['destination']['network'] == $ext)) {
					$input_errors[] = "There is already an outbound NAT rule with the specified settings.";
					break;
				}
			}
		}
    }

    if (!$input_errors) {
        $natent = array();
        $natent['source']['network'] = $osn;
        $natent['descr'] = $_POST['descr'];
        $natent['target'] = $_POST['target'];
        $natent['interface'] = $_POST['interface'];
        
        if ($ext == "any")
            $natent['destination']['any'] = true;
        else
            $natent['destination']['network'] = $ext;
        
        if (isset($_POST['destination_not']) && $ext != "any")
            $natent['destination']['not'] = true;
        
        if (isset($id) && $a_out[$id])
            $a_out[$id] = $natent;
        else
            $a_out[] = $natent;
        
        touch($d_natconfdirty_path);
        
        write_config();
        
        header("Location: firewall_nat_out.php");
        exit;
    }
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Firewall: NAT: Edit outbound mapping");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
<script language="JavaScript">
<!--
function typesel_change() {
    switch (document.iform.destination_type.selectedIndex) {
        case 1: // network
            document.iform.destination.disabled = 0;
            document.iform.destination_subnet.disabled = 0;
            break;
        default:
            document.iform.destination.value = "";
            document.iform.destination.disabled = 1;
            document.iform.destination_subnet.value = "24";
            document.iform.destination_subnet.disabled = 1;
            break;
    }
}
//-->
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Firewall: NAT: Edit outbound mapping</p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="firewall_nat_out_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
			      <tr>
                  <td width="22%" valign="top" class="vncellreq">Interface</td>
                  <td width="78%" class="vtable">
					<select name="interface" class="formfld">
						<?php
						$interfaces = array('wan' => 'WAN');
						for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
							$interfaces['opt' . $i] = $config['interfaces']['opt' . $i]['descr'];
						}
						foreach ($interfaces as $iface => $ifacename): ?>
						<option value="<?=$iface;?>" <?php if ($iface == $pconfig['interface']) echo "selected"; ?>> 
						<?=htmlspecialchars($ifacename);?>
						</option>
						<?php endforeach; ?>
					</select><br>
                     <span class="vexpl">Choose which interface this rule applies to.<br>
                     Hint: in most cases, you'll want to use WAN here.</span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Source</td>
                  <td width="78%" class="vtable">
<input name="source" type="text" class="formfld" id="source" size="20" value="<?=htmlspecialchars($pconfig['source']);?>">
                     
                  / 
                    <select name="source_subnet" class="formfld" id="source_subnet">
                      <?php for ($i = 32; $i >= 0; $i--): ?>
                      <option value="<?=$i;?>" <?php if ($i == $pconfig['source_subnet']) echo "selected"; ?>>
                      <?=$i;?>
                      </option>
                      <?php endfor; ?>
                    </select>
                    <br>
                     <span class="vexpl">Enter the source network for the outbound NAT mapping.</span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Destination</td>
                  <td width="78%" class="vtable">
<input name="destination_not" type="checkbox" id="destination_not" value="yes" <?php if ($pconfig['destination_not']) echo "checked"; ?>>
                    <strong>not</strong><br>
                    Use this option to invert the sense of the match.<br>
                    <br>
                    <table border="0" cellspacing="0" cellpadding="0">
                      <tr> 
                        <td>Type:&nbsp;&nbsp;</td>
                        <td><select name="destination_type" class="formfld" onChange="typesel_change()">
                            <option value="any" <?php if ($pconfig['destination'] == "any") echo "selected"; ?>> 
                            any</option>
                            <option value="network" <?php if ($pconfig['destination'] != "any") echo "selected"; ?>> 
                            Network</option>
                          </select></td>
                      </tr>
                      <tr> 
                        <td>Address:&nbsp;&nbsp;</td>
                        <td><input name="destination" type="text" class="formfld" id="destination" size="20" value="<?=htmlspecialchars($pconfig['destination']);?>">
                          / 
                          <select name="destination_subnet" class="formfld" id="destination_subnet">
                            <?php for ($i = 32; $i >= 0; $i--): ?>
                            <option value="<?=$i;?>" <?php if ($i == $pconfig['destination_subnet']) echo "selected"; ?>> 
                            <?=$i;?>
                            </option>
                            <?php endfor; ?>
                          </select> </td>
                      </tr>
                      <tr>
                        <td>&nbsp;</td>
                        <td><span class="vexpl">Enter the destination network for 
                          the outbound NAT mapping.</span></td>
                      </tr>
                    </table></td>
                </tr>
                <tr> 
                  <td valign="top" class="vncell">Target</td>
                  <td class="vtable">
<input name="target" type="text" class="formfld" id="target" size="20" value="<?=htmlspecialchars($pconfig['target']);?>">
                    <br>
                     <span class="vexpl">Packets matching this rule will be mapped to the IP address given here. Leave blank to use the selected interface's IP address.</span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">Description</td>
                  <td width="78%" class="vtable"> 
                    <input name="descr" type="text" class="formfld" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>"> 
                    <br> <span class="vexpl">You may enter a description here 
                    for your reference (not parsed).</span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="Save"> 
                    <?php if (isset($id) && $a_out[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>"> 
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<script language="JavaScript">
<!--
typesel_change();
//-->
</script>
<?php include("fend.inc"); ?>
</body>
</html>
