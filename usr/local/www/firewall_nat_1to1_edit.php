#!/usr/local/bin/php
<?php 
/*
	firewall_nat_1to1_edit.php
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

if (!is_array($config['nat']['onetoone'])) {
	$config['nat']['onetoone'] = array();
}
nat_1to1_rules_sort();
$a_1to1 = &$config['nat']['onetoone'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_1to1[$id]) {
	$pconfig['external'] = $a_1to1[$id]['external'];
	$pconfig['internal'] = $a_1to1[$id]['internal'];
	$pconfig['interface'] = $a_1to1[$id]['interface'];
	if (!$pconfig['interface'])
		$pconfig['interface'] = "wan";
	if (!$a_1to1[$id]['subnet'])
		$pconfig['subnet'] = 32;
	else
		$pconfig['subnet'] = $a_1to1[$id]['subnet'];
	$pconfig['descr'] = $a_1to1[$id]['descr'];
} else {
    $pconfig['subnet'] = 32;
	$pconfig['interface'] = "wan";
}

if ($_POST) {
	
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "interface external internal");
	$reqdfieldsn = explode(",", "Interface,External subnet,Internal subnet");
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if (($_POST['external'] && !is_ipaddr($_POST['external']))) {
		$input_errors[] = "A valid external subnet must be specified.";
	}
	if (($_POST['internal'] && !is_ipaddr($_POST['internal']))) {
		$input_errors[] = "A valid internal subnet must be specified.";
	}
	
	if (is_ipaddr($config['interfaces']['wan']['ipaddr'])) {
		if (check_subnets_overlap($_POST['external'], $_POST['subnet'], 
				$config['interfaces']['wan']['ipaddr'], 32))
			$input_errors[] = "The WAN IP address may not be used in a 1:1 rule.";
	}
	
	/* check for overlaps with other 1:1 */
	foreach ($a_1to1 as $natent) {
		if (isset($id) && ($a_1to1[$id]) && ($a_1to1[$id] === $natent))
			continue;
		
		if (check_subnets_overlap($_POST['external'], $_POST['subnet'], $natent['external'], $natent['subnet'])) {
			$input_errors[] = "Another 1:1 rule overlaps with the specified external subnet.";
			break;
		} else if (check_subnets_overlap($_POST['internal'], $_POST['subnet'], $natent['internal'], $natent['subnet'])) {
			$input_errors[] = "Another 1:1 rule overlaps with the specified internal subnet.";
			break;
		}
	}
	
	/* check for overlaps with server NAT */
	if (is_array($config['nat']['servernat'])) {
		foreach ($config['nat']['servernat'] as $natent) {
			if (check_subnets_overlap($_POST['external'], $_POST['subnet'],
				$natent['ipaddr'], 32)) {
				$input_errors[] = "A server NAT entry overlaps with the specified external subnet.";
				break;
			}
		}
	}
	
	/* check for overlaps with advanced outbound NAT */
	if (is_array($config['nat']['advancedoutbound']['rule'])) {
		foreach ($config['nat']['advancedoutbound']['rule'] as $natent) {
			if ($natent['target'] && 
				check_subnets_overlap($_POST['external'], $_POST['subnet'], $natent['target'], 32)) {
				$input_errors[] = "An advanced outbound NAT entry overlaps with the specified external subnet.";
				break;
			}
		}
	}

	if (!$input_errors) {
		$natent = array();
		$natent['external'] = $_POST['external'];
		$natent['internal'] = $_POST['internal'];
		$natent['subnet'] = $_POST['subnet'];
		$natent['descr'] = $_POST['descr'];
		$natent['interface'] = $_POST['interface'];
		
		if (isset($id) && $a_1to1[$id])
			$a_1to1[$id] = $natent;
		else
			$a_1to1[] = $natent;
		
		touch($d_natconfdirty_path);
		
		write_config();
		
		header("Location: firewall_nat_1to1.php");
		exit;
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Firewall: NAT: Edit 1:1");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Firewall: NAT: Edit 1:1</p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="firewall_nat_1to1_edit.php" method="post" name="iform" id="iform">
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
                  <td width="22%" valign="top" class="vncellreq">External subnet</td>
                  <td width="78%" class="vtable"> 
                    <input name="external" type="text" class="formfld" id="external" size="20" value="<?=htmlspecialchars($pconfig['external']);?>">
                    / 
                    <select name="subnet" class="formfld" id="subnet">
                      <?php for ($i = 32; $i >= 0; $i--): ?>
                      <option value="<?=$i;?>" <?php if ($i == $pconfig['subnet']) echo "selected"; ?>>
                      <?=$i;?>
                      </option>
                      <?php endfor; ?>
                    </select>
                    <br>
                    <span class="vexpl">Enter the external (WAN) subnet for the 1:1 mapping. You may map single IP addresses by specifying a /32 subnet.</span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Internal subnet</td>
                  <td width="78%" class="vtable"> 
                    <input name="internal" type="text" class="formfld" id="internal" size="20" value="<?=htmlspecialchars($pconfig['internal']);?>"> 
                    <br>
                     <span class="vexpl">Enter the internal (LAN) subnet for the 1:1 mapping. The subnet size specified for the external subnet also applies to the internal subnet (they  have to be the same).</span></td>
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
                    <?php if (isset($id) && $a_1to1[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>"> 
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
