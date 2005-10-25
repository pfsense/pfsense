#!/usr/local/bin/php
<?php
/* $Id$ */
/*
    firewall_nat_out_edit.php
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
//nat_out_rules_sort();

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($_GET['dup']))  { 
	$id  =  $_GET['dup']; 
	$after  =  $_GET['dup']; 
}  

if (isset($id) && $a_out[$id]) {
    list($pconfig['source'],$pconfig['source_subnet']) = explode('/', $a_out[$id]['source']['network']);
    $pconfig['sourceport'] = $a_out[$id]['sourceport'];
    address_to_pconfig($a_out[$id]['destination'], $pconfig['destination'],
	   $pconfig['destination_subnet'], $pconfig['destination_not'],
	   $none, $none); 
    $pconfig['dstport'] = $a_out[$id]['dstport'];
    $pconfig['natport'] = $a_out[$id]['natport'];
    $pconfig['target'] = $a_out[$id]['target'];
    $pconfig['interface'] = $a_out[$id]['interface'];
	if (!$pconfig['interface'])
		$pconfig['interface'] = "wan";
    $pconfig['descr'] = $a_out[$id]['descr'];
    $pconfig['nonat'] = $a_out[$id]['nonat'];
} else {
    $pconfig['source_subnet'] = 24;
    $pconfig['destination'] = "any";
    $pconfig['destination_subnet'] = 24;
	$pconfig['interface'] = "wan";
}

if (isset($_GET['dup']))
        unset($id);

if ($_POST) {

    if ($_POST['destination_type'] == "any") {
        $_POST['destination'] = "any";
        $_POST['destination_subnet'] = 24;
    }
    if ($_POST['source_type'] == "any") {
        $_POST['source'] = "any";
        $_POST['source_subnet'] = 24;
    }

    unset($input_errors);
    $pconfig = $_POST;

    /* input validation */
    $reqdfields = explode(" ", "interface source source_subnet destination destination_subnet");
    $reqdfieldsn = explode(",", "Interface,Source,Source bit count,Destination,Destination bit count");

    do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

    if ($_POST['source_type'] != "any") {
        if ($_POST['source'] && !is_ipaddr($_POST['source']) && $_POST['source'] <> "any") {
            $input_errors[] = "A valid source must be specified.";
        }
        if ($_POST['source_subnet'] && !is_numericint($_POST['source_subnet'])) {
            $input_errors[] = "A valid source bit count must be specified.";
        }
    }
    if ($_POST['sourceport'] && !is_numericint($_POST['sourceport'])) {
        $input_errors[] = "A valid source port must be specified.";
    }
    if ($_POST['destination_type'] != "any") {
        if ($_POST['destination'] && !is_ipaddr($_POST['destination'])) {
            $input_errors[] = "A valid destination must be specified.";
        }
        if ($_POST['destination_subnet'] && !is_numericint($_POST['destination_subnet'])) {
            $input_errors[] = "A valid destination bit count must be specified.";
        }
    }
    if ($_POST['destination_type'] == "any") {
	if ($_POST['destination_not'])
            $input_errors[] = "Negating destination address of \"any\" is invalid.";
    }
    if ($_POST['dstport'] && !is_numericint($_POST['dstport'])) {
        $input_errors[] = "A valid destination port must be specified.";
    }
    if ($_POST['natport'] && !is_numericint($_POST['natport'])) {
        $input_errors[] = "A valid NAT port must be specified.";
    }

    if ($_POST['target'] && !is_ipaddr($_POST['target'])) {
        $input_errors[] = "A valid target IP address must be specified.";
    }

    /* if user has selected any as source, set it here */
    if($_POST['source_type'] == "any") {
	$osn = "any";
    } else {
	$osn = gen_subnet($_POST['source'], $_POST['source_subnet']) . "/" . $_POST['source_subnet'];
    }
    
    /* check for existing entries */
    if ($_POST['destination_type'] == "any")
        $ext = "any";
    else
        $ext = gen_subnet($_POST['destination'], $_POST['destination_subnet']) . "/"
            . $_POST['destination_subnet'];

	if ($_POST['target']) {
		/* check for clashes with 1:1 NAT (NAT Addresses is OK) */
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
						($natent['destination']['address'] == $ext)) {
					//$input_errors[] = "There is already an outbound NAT rule with the specified settings.";
					break;
				}
			}
		}
    }

    if (!$input_errors) {
        $natent = array();
        $natent['source']['network'] = $osn;
        $natent['sourceport'] = $_POST['sourceport'];
        $natent['descr'] = $_POST['descr'];
        $natent['target'] = $_POST['target'];
        $natent['interface'] = $_POST['interface'];

	/* if user has selected not nat, set it here */
	if(isset($_POST['nonat'])) {
		$natent['nonat'] = true;
	} else {
		$natent['nonat'] = false;
	}

        if ($ext == "any")
            $natent['destination']['any'] = true;
        else
            $natent['destination']['address'] = $ext;

        $natent['natport'] = $_POST['natport'];
        $natent['dstport'] = $_POST['dstport'];

        if (isset($_POST['destination_not']) && $ext != "any")
            $natent['destination']['not'] = true;

	if (isset($id) && $a_out[$id])
		$a_out[$id] = $natent;
	else {
		if (is_numeric($after))
			array_splice($a_out, $after+1, 0, array($natent));
		else
			$a_out[] = $natent;
	}

        touch($d_natconfdirty_path);

        write_config();

        header("Location: firewall_nat_out.php");
        exit;
    }
}

$pgtitle = "Firewall: NAT: Outbound: Edit";
$closehead = false;
include("head.inc");

?>

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
function sourcesel_change() {
    switch (document.iform.source_type.selectedIndex) {
        case 1: // network
            document.iform.source.disabled = 0;
            document.iform.source.disabled = 0;
            break;
        default:
	    document.iform.source.value = "";
	    document.iform.sourceport.value = "";
            document.iform.source.disabled = 1;
            document.iform.source_subnet.value = "24";
            document.iform.source_subnet.disabled = 1;
            break;
    }
}
//-->
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="firewall_nat_out_edit.php" method="post" name="iform" id="iform">
              <?display_topbar()?>
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
	        <tr>
                  <td width="22%" valign="top" class="vncellreq">No nat (NOT)</td>
                  <td width="78%" class="vtable">
			<input type="checkbox" name="nonat"<?php if(isset($pconfig['nonat'])) echo " CHECKED"; ?>>
                     <span class="vexpl">Enabling this option will disable natting for the item and stop processing outgoing nat rules.
		     <br>Hint: in most cases, you'll not use this option unless you know what your doing.</span></td>
                </tr>
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
                    <table border="0" cellspacing="0" cellpadding="0">
                      <tr>
		        <td>Type:&nbsp;&nbsp;</td>
			<td>
			    <select name="source_type" class="formfld" onChange="sourcesel_change()">
                              <option value="any" <?php if ($pconfig['source'] == "any") echo "selected"; ?>>any</option>
                              <option value="network" <?php if ($pconfig['source'] != "any") echo "selected"; ?>>Network</option>
                            </select>			
			</td></tr>
                        <td>Address:&nbsp;&nbsp;</td>
                        <td><input name="source" type="text" class="formfld" id="source" size="20" value="<?=htmlspecialchars($pconfig['source']);?>">/<select name="source_subnet" class="formfld" id="source_subnet">
<?php for ($i = 32; $i >= 0; $i--): ?>
                          <option value="<?=$i;?>"<?php if ($i == $pconfig['source_subnet']) echo " selected"; ?>><?=$i;?></option>
<?php endfor; ?>
                          </select></td>
                      </tr>
                      <tr>
                        <td>&nbsp;</td>
                        <td><span class="vexpl">Enter the source network for the outbound NAT mapping.</span></td>
                      </tr>
                      <tr>
                        <td>Source port:&nbsp;&nbsp;</td>
                        <td><input name="sourceport" type="text" class="formfld" id="sourceport" size="5" value="<?=htmlspecialchars($pconfig['sourceport']);?>"> (leave blank for any)</td>
                      </tr>
                    </table></td>
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
                            <option value="any"<?php if ($pconfig['destination'] == "any") echo " selected"; ?>>
                            any</option>
                            <option value="network"<?php if ($pconfig['destination'] != "any") echo " selected"; ?>>
                            Network</option>
                          </select></td>
                      </tr>
                      <tr>
                        <td>Address:&nbsp;&nbsp;</td>
                        <td><input name="destination" type="text" class="formfld" id="destination" size="20" value="<?=htmlspecialchars($pconfig['destination']);?>">
                          /
                          <select name="destination_subnet" class="formfld" id="destination_subnet">
<?php for ($i = 32; $i >= 0; $i--): ?>
                            <option value="<?=$i;?>"<?php if ($i == $pconfig['destination_subnet']) echo " selected"; ?>><?=$i;?></option>
<?php endfor; ?>
                          </select> </td>
                      </tr>
                      <tr>
                        <td>&nbsp;</td>
                        <td><span class="vexpl">Enter the destination network for
                          the outbound NAT mapping.</span></td>
                      </tr>
                      <tr>
                        <td>Destination port:&nbsp;&nbsp;</td>
                         <td><input name="dstport" type="text" class="formfld" id="dstport" size="5" value="<?=htmlspecialchars($pconfig['dstport']);?>"> (leave blank for any)</td>
                      </tr>
                    </table>
		  </td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">Target</td>
                  <td width="78%" class="vtable">
			<table border="0" cellspacing="0" cellpadding="0">
			<tr>
			  <td>Address:&nbsp;&nbsp;</td>
			  <td><select name="target" class="formfld">
				<option value=""<?php if (!$pconfig['target']) echo " selected"; ?>>Interface address</option>
<?php	if (is_array($config['virtualip']['vip'])):
		foreach ($config['virtualip']['vip'] as $sn): ?>
				<option value="<?=$sn['subnet'];?>" <?php if ($sn['subnet'] == $pconfig['target']) echo "selected"; ?>><?=htmlspecialchars("{$sn['subnet']} ({$sn['descr']})");?></option>
<?php 		endforeach;
	endif; ?>
				<option value=""<?php if($pconfig['target'] == "any") echo " selected"; ?>>any</option>
			  </select>
			  </td>
			</tr>
			<tr><td>&nbsp;</td><td>
                     <span class="vexpl">Packets matching this rule will be mapped to the IP address given here.<br>
			If you want this rule to apply to another IP address than the IP address of the interface chosen above,
			select it here (you need to define <a href="firewall_virtual_ip.php">Virtual IP</a> addresses on the first).
			 Also note that if you are trying to redirect connections on the LAN select the "any" option.
			</span>
			</td></tr>
			<tr>
                          <td>Port:&nbsp;&nbsp;</td>
                          <td><input name="natport" type="text" class="formfld" id="natport" size="5" value="<?=htmlspecialchars($pconfig['natport']);?>"></td>
			</tr>
			<tr><td>&nbsp;</td><td>
                        <span class="vexpl">Enter the source port for the outbound NAT mapping.</span>
			</td></tr>
			</table>
		  </td>
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
                    <input name="Submit" type="submit" class="formbtn" value="Save"> <input type="button" class="formbtn" value="Cancel" onclick="history.back()">
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
