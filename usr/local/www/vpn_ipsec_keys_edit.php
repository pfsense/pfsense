#!/usr/local/bin/php
<?php
/*
	vpn_ipsec_keys_edit.php
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

if (!is_array($config['ipsec']['mobilekey'])) {
	$config['ipsec']['mobilekey'] = array();
}
ipsec_mobilekey_sort();
$a_secret = &$config['ipsec']['mobilekey'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_secret[$id]) {
	$pconfig['ident'] = $a_secret[$id]['ident'];
	$pconfig['psk'] = $a_secret[$id]['pre-shared-key'];
}

if ($_POST) {
	
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "ident psk");
	$reqdfieldsn = explode(",", "Identifier,Pre-shared key");
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
	if (preg_match("/[^a-zA-Z0-9@\.\-]/", $_POST['ident']))
		$input_errors[] = "The identifier contains invalid characters.";
	
	if (!$input_errors && !(isset($id) && $a_secret[$id])) {
		/* make sure there are no dupes */
		foreach ($a_secret as $secretent) {
			if ($secretent['ident'] == $_POST['ident']) {
				$input_errors[] = "Another entry with the same identifier already exists.";
				break;
			}
		}
	}

	if (!$input_errors) {
	
		if (isset($id) && $a_secret[$id])
			$secretent = $a_secret[$id];
	
		$secretent['ident'] = $_POST['ident'];
		$secretent['pre-shared-key'] = $_POST['psk'];
		
		if (isset($id) && $a_secret[$id])
			$a_secret[$id] = $secretent;
		else
			$a_secret[] = $secretent;
		
		write_config();
		touch($d_ipsecconfdirty_path);
		
		header("Location: vpn_ipsec_keys.php");
		exit;
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("VPN: IPsec: Edit pre-shared key");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">VPN: IPsec: Edit pre-shared key</p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="vpn_ipsec_keys_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr> 
                  <td valign="top" class="vncellreq">Identifier</td>
                  <td class="vtable">
 <input name="ident" type="text" class="formfld" id="ident" size="30" value="<?=$pconfig['ident'];?>">
                    <br>
This can be either an IP address, fully qualified domain name or an e-mail address.       
                  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Pre-shared key</td>
                  <td width="78%" class="vtable"> 
                    <input name="psk" type="text" class="formfld" id="psk" size="40" value="<?=htmlspecialchars($pconfig['psk']);?>">
                  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="Save"> 
                    <?php if (isset($id) && $a_secret[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>">
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
