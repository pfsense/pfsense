#!/usr/local/bin/php
<?php 
/*
	firewall_aliases_edit.php
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

if (!is_array($config['aliases']['alias']))
	$config['aliases']['alias'] = array();

aliases_sort();
$a_aliases = &$config['aliases']['alias'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_aliases[$id]) {
	$pconfig['name'] = $a_aliases[$id]['name'];
	list($pconfig['address'],$pconfig['address_subnet']) = 
		explode('/', $a_aliases[$id]['address']);
	if ($pconfig['address_subnet'])
		$pconfig['type'] = "network";
	else
		$pconfig['type'] = "host";
	$pconfig['descr'] = $a_aliases[$id]['descr'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "name address");
	$reqdfieldsn = explode(",", "Name,Address");
	
	if ($_POST['type'] == "network") {
		$reqdfields[] = "address_subnet";
		$reqdfieldsn[] = "Subnet bit count";
	}
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
	if (($_POST['name'] && !is_validaliasname($_POST['name']))) {
		$input_errors[] = "The alias name may only consist of the characters a-z, A-Z, 0-9.";
	}
	if (($_POST['address'] && !is_ipaddr($_POST['address']))) {
		$input_errors[] = "A valid address must be specified.";
	}
	if (($_POST['address_subnet'] && !is_numeric($_POST['address_subnet']))) {
		$input_errors[] = "A valid subnet bit count must be specified.";
	}
	
	/* check for name conflicts */
	foreach ($a_aliases as $alias) {
		if (isset($id) && ($a_aliases[$id]) && ($a_aliases[$id] === $alias))
			continue;

		if ($alias['name'] == $_POST['name']) {
			$input_errors[] = "An alias with this name already exists.";
			break;
		}
	}

	if (!$input_errors) {
		$alias = array();
		$alias['name'] = $_POST['name'];
		if ($_POST['type'] == "network")
			$alias['address'] = $_POST['address'] . "/" . $_POST['address_subnet'];
		else
			$alias['address'] = $_POST['address'];
		$alias['descr'] = $_POST['descr'];

		if (isset($id) && $a_aliases[$id])
			$a_aliases[$id] = $alias;
		else
			$a_aliases[] = $alias;
		
		touch($d_aliasesdirty_path);
		
		write_config();
		
		header("Location: firewall_aliases.php");
		exit;
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("System: Firewall: Aliases: Edit alias");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
<script language="JavaScript">
<!--
function typesel_change() {
	switch (document.iform.type.selectedIndex) {
		case 0:	/* host */
			document.iform.address_subnet.disabled = 1;
			document.iform.address_subnet.value = "";
			break;
		case 1:	/* network */
			document.iform.address_subnet.disabled = 0;
			break;
	}
}
//-->
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Firewall: Aliases: Edit alias</p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="firewall_aliases_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr> 
                  <td valign="top" class="vncellreq">Name</td>
                  <td class="vtable"> <input name="name" type="text" class="formfld" id="name" size="40" value="<?=htmlspecialchars($pconfig['name']);?>"> 
                    <br> <span class="vexpl">The name of the alias may only consist 
                    of the characters a-z, A-Z and 0-9.</span></td>
                </tr>
                <tr> 
                  <td valign="top" class="vncellreq">Type</td>
                  <td class="vtable"> 
                    <select name="type" class="formfld" id="type" onChange="typesel_change()">
                      <option value="host" <?php if ($pconfig['type'] == "host") echo "selected"; ?>>Host</option>
                      <option value="network" <?php if ($pconfig['type'] == "network") echo "selected"; ?>>Network</option>
                    </select>
                  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Address</td>
                  <td width="78%" class="vtable"> <input name="address" type="text" class="formfld" id="address" size="20" value="<?=htmlspecialchars($pconfig['address']);?>">
                    / 
                    <select name="address_subnet" class="formfld" id="address_subnet">
                      <?php for ($i = 32; $i >= 1; $i--): ?>
                      <option value="<?=$i;?>" <?php if ($i == $pconfig['address_subnet']) echo "selected"; ?>> 
                      <?=$i;?>
                      </option>
                      <?php endfor; ?>
                    </select> <br> <span class="vexpl">The address that this alias 
                    represents.</span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">Description</td>
                  <td width="78%" class="vtable"> <input name="descr" type="text" class="formfld" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>"> 
                    <br> <span class="vexpl">You may enter a description here 
                    for your reference (not parsed).</span></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> <input name="Submit" type="submit" class="formbtn" value="Save"> 
                    <?php if (isset($id) && $a_aliases[$id]): ?>
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
