#!/usr/local/bin/php
<?php 
/*
	services_proxyarp_edit.php
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

if (!is_array($config['proxyarp']['proxyarpnet'])) {
	$config['proxyarp']['proxyarpnet'] = array();
}
proxyarp_sort();
$a_proxyarp = &$config['proxyarp']['proxyarpnet'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_proxyarp[$id]) {
	if (isset($a_proxyarp[$id]['network']))
		list($pconfig['subnet'], $pconfig['subnet_bits']) = explode("/", $a_proxyarp[$id]['network']);
	else if (isset($a_proxyarp[$id]['range'])) {
		$pconfig['range_from'] = $a_proxyarp[$id]['range']['from'];
		$pconfig['range_to'] = $a_proxyarp[$id]['range']['to'];
	}
	$pconfig['descr'] = $a_proxyarp[$id]['descr'];
} else {
	$pconfig['subnet_bits'] = 32;
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['type'] == "single") {
		$reqdfields = explode(" ", "subnet");
		$reqdfieldsn = explode(",", "Address");
		$_POST['subnet_bits'] = 32;
	} else if ($_POST['type'] == "network") {
		$reqdfields = explode(" ", "subnet subnet_bits");
		$reqdfieldsn = explode(",", "Network,Network mask");
	} else if ($_POST['type'] == "range") {
		$reqdfields = explode(" ", "range_from range_to");
		$reqdfieldsn = explode(",", "Range start,Range end");
	}
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
	if ((($_POST['type'] != "range") && $_POST['subnet'] && !is_ipaddr($_POST['subnet']))) {
		$input_errors[] = "A valid address must be specified.";
	}
	if ((($_POST['type'] == "range") && $_POST['range_from'] && !is_ipaddr($_POST['range_from']))) {
		$input_errors[] = "A valid range start must be specified.";
	}
	if ((($_POST['type'] == "range") && $_POST['range_to'] && !is_ipaddr($_POST['range_to']))) {
		$input_errors[] = "A valid range end must be specified.";
	}

	/* check for overlaps */
	foreach ($a_proxyarp as $arpent) {
		if (isset($id) && ($a_proxyarp[$id]) && ($a_proxyarp[$id] === $arpent))
			continue;
		
		if (($_POST['type'] == "range") && isset($arpent['range'])) {
			if (($_POST['range_from'] == $arpent['range']['from']) && 
				($_POST['range_to'] == $arpent['range']['to'])) {
				$input_errors[] = "This range already exists.";
				break;
			}
		} else if (isset($arpent['network'])) {
			if (($arpent['network'] == "{$_POST['subnet']}/{$_POST['subnet_bits']}")) {
				$input_errors[] = "This network already exists.";
				break;
			}
		}
	}

	if (!$input_errors) {
		$arpent = array();
		if ($_POST['type'] == "range") {
			$arpent['range']['from'] = $_POST['range_from'];
			$arpent['range']['to'] = $_POST['range_to'];
		} else
			$arpent['network'] = $_POST['subnet'] . "/" . $_POST['subnet_bits'];
		$arpent['descr'] = $_POST['descr'];

		if (isset($id) && $a_proxyarp[$id])
			$a_proxyarp[$id] = $arpent;
		else
			$a_proxyarp[] = $arpent;
		
		touch($d_proxyarpdirty_path);
		
		write_config();
		
		header("Location: services_proxyarp.php");
		exit;
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Services: Proxy ARP: Edit");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
<script language="JavaScript">
<!--
function typesel_change() {
    switch (document.iform.type.selectedIndex) {
        case 0: // single
            document.iform.subnet.disabled = 0;
            document.iform.subnet_bits.disabled = 1;
            document.iform.range_from.disabled = 1;
            document.iform.range_to.disabled = 1;
            break;
        case 1: // network
            document.iform.subnet.disabled = 0;
            document.iform.subnet_bits.disabled = 0;
            document.iform.range_from.disabled = 1;
            document.iform.range_to.disabled = 1;
            break;
        case 2: // range
            document.iform.subnet.disabled = 1;
            document.iform.subnet_bits.disabled = 1;
            document.iform.range_from.disabled = 0;
            document.iform.range_to.disabled = 0;
            break;
    }
}
//-->
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Services: Proxy ARP: Edit</p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="services_proxyarp_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr> 
                  <td valign="top" class="vncellreq">Network</td>
                  <td class="vtable">
                    <table border="0" cellspacing="0" cellpadding="0">
                      <tr> 
                        <td>Type:&nbsp;&nbsp;</td>
                        <td><select name="type" class="formfld" onChange="typesel_change()">
                            <option value="single" <?php if (!$pconfig['range_from'] && $pconfig['subnet_bits'] == 32) echo "selected"; ?>> 
                            Single address</option>
                            <option value="network" <?php if (!$pconfig['range_from'] && $pconfig['subnet_bits'] != 32) echo "selected"; ?>> 
                            Network</option>
                            <option value="range" <?php if ($pconfig['range_from']) echo "selected"; ?>> 
                            Range</option>
                          </select></td>
                      </tr>
                      <tr> 
                        <td>Address:&nbsp;&nbsp;</td>
                        <td><input name="subnet" type="text" class="formfld" id="subnet" size="20" value="<?=htmlspecialchars($pconfig['subnet']);?>">
                  / 
                          <select name="subnet_bits" class="formfld" id="select">
                            <?php for ($i = 31; $i >= 0; $i--): ?>
                            <option value="<?=$i;?>" <?php if ($i == $pconfig['subnet_bits']) echo "selected"; ?>>
                            <?=$i;?>
                      </option>
                            <?php endfor; ?>
                      </select>
 </td>
                      </tr>
                      <tr> 
                        <td>Range:&nbsp;&nbsp;</td>
                        <td><input name="range_from" type="text" class="formfld" id="range_from" size="20" value="<?=htmlspecialchars($pconfig['range_from']);?>">
- 
                          <input name="range_to" type="text" class="formfld" id="range_to" size="20" value="<?=htmlspecialchars($pconfig['range_to']);?>">                          
                          </td>
                      </tr>
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
                    <input name="Submit" type="submit" class="formbtn" value="Save">
                    <?php if (isset($id) && $a_proxyarp[$id]): ?>
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
