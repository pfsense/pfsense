#!/usr/local/bin/php
<?php 
/*
	services_captiveportal_ip_edit.php
	part of m0n0wall (http://m0n0.ch/wall)
	
	Copyright (C) 2004 Dinesh Nair <dinesh@alphaque.com>
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

if (!is_array($config['captiveportal']['allowedip']))
	$config['captiveportal']['allowedip'] = array();

allowedips_sort();
$a_allowedips = &$config['captiveportal']['allowedip'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_allowedips[$id]) {
	$pconfig['ip'] = $a_allowedips[$id]['ip'];
	$pconfig['descr'] = $a_allowedips[$id]['descr'];
	$pconfig['dir'] = $a_allowedips[$id]['dir'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "ip dir");
	$reqdfieldsn = explode(",", "Allowed IP address,Direction");
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
	if (($_POST['ip'] && !is_ipaddr($_POST['ip']))) {
		$input_errors[] = "A valid IP address must be specified. [".$_POST['ip']."]";
	}

	foreach ($a_allowedips as $ipent) {
		if (isset($id) && ($a_allowedips[$id]) && ($a_allowedips[$id] === $ipent))
			continue;
		
		if (($ipent['dir'] == $_POST['dir']) && ($ipent['ip'] == $_POST['ip'])){
			$input_errors[] = "[" . $_POST['ip'] . "] already allowed." ;
			break ;
		}	
	}

	if (!$input_errors) {
		$ip = array();
		$ip['ip'] = $_POST['ip'];
		$ip['descr'] = $_POST['descr'];
		$ip['dir'] = $_POST['dir'];

		if (isset($id) && $a_allowedips[$id])
			$a_allowedips[$id] = $ip;
		else
			$a_allowedips[] = $ip;
		
		write_config();

		touch($d_allowedipsdirty_path) ;
		
		header("Location: services_captiveportal_ip.php");
		exit;
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Services: Captive portal: Edit allowed IP address");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Services: Captive portal: Edit allowed IP address</p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="services_captiveportal_ip_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
				<tr>
                  <td width="22%" valign="top" class="vncellreq">Direction</td>
                  <td width="78%" class="vtable"> 
					<select name="dir" class="formfld">
					<?php 
					$dirs = explode(" ", "From To") ;
					foreach ($dirs as $dir): ?>
					<option value="<?=strtolower($dir);?>" <?php if (strtolower($dir) == strtolower($pconfig['dir'])) echo "selected";?> >
					<?=htmlspecialchars($dir);?>
					</option>
					<?php endforeach; ?>
					</select>
                    <br> 
                    <span class="vexpl">Use <em>From</em> to always allow an IP address through the captive portal (without authentication). 
                    Use <em>To</em> to allow access from all clients (even non-authenticated ones) behind the portal to this IP address.</span></td>
                </tr>
				<tr>
                  <td width="22%" valign="top" class="vncellreq">IP address</td>
                  <td width="78%" class="vtable"> 
                    <input name="ip" type="text" class="formfld" id="ip" size="17" value="<?=htmlspecialchars($pconfig['ip']);?>">
                    <br> 
                    <span class="vexpl">IP address</span></td>
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
                    <?php if (isset($id) && $a_allowedips[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>">
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
