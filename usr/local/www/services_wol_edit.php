#!/usr/local/bin/php
<?php 
/*
	services_wol_edit.php
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

if (!is_array($config['wol']['wolentry'])) {
	$config['wol']['wolentry'] = array();
}
wol_sort();
$a_wol = &$config['wol']['wolentry'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_wol[$id]) {
	$pconfig['interface'] = $a_wol[$id]['interface'];
	$pconfig['mac'] = $a_wol[$id]['mac'];
	$pconfig['descr'] = $a_wol[$id]['descr'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "interface mac");
	$reqdfieldsn = explode(",", "Interface,MAC address");
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
	if (($_POST['mac'] && !is_macaddr($_POST['mac']))) {
		$input_errors[] = "A valid MAC address must be specified.";
	}

	if (!$input_errors) {
		$wolent = array();
		$wolent['interface'] = $_POST['interface'];
		$wolent['mac'] = $_POST['mac'];
		$wolent['descr'] = $_POST['descr'];

		if (isset($id) && $a_wol[$id])
			$a_wol[$id] = $wolent;
		else
			$a_wol[] = $wolent;
		
		write_config();
		
		header("Location: services_wol.php");
		exit;
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Services: Wake on LAN: Edit entry");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Services: Wake on LAN: Edit entry</p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="services_wol_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
			  <tr> 
                  <td width="22%" valign="top" class="vncellreq">Interface</td>
                  <td width="78%" class="vtable">
<select name="interface" class="formfld">
                      <?php $interfaces = array('lan' => 'LAN');
					  for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
					    if (isset($config['interfaces']['opt' . $i]['enable']) &&
							!$config['interfaces']['opt' . $i]['bridge'])
					  		$interfaces['opt' . $i] = $config['interfaces']['opt' . $i]['descr'];
					  }
					  foreach ($interfaces as $iface => $ifacename): ?>
                      <option value="<?=$iface;?>" <?php if ($iface == $pconfig['interface']) echo "selected"; ?>> 
                      <?=htmlspecialchars($ifacename);?>
                      </option>
                      <?php endforeach; ?>
                    </select> <br>
                    <span class="vexpl">Choose which interface this host is connected to.</span></td>
                </tr>
				<tr>
                  <td width="22%" valign="top" class="vncellreq">MAC address</td>
                  <td width="78%" class="vtable"> 
                    <input name="mac" type="text" class="formfld" id="mac" size="20" value="<?=htmlspecialchars($pconfig['mac']);?>">
                    <br> 
                    <span class="vexpl">Enter a MAC address  in the following format: 
                    xx:xx:xx:xx:xx:xx<em></em></span></td>
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
                    <?php if (isset($id) && $a_wol[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>">
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
