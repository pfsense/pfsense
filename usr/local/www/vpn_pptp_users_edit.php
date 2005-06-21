#!/usr/local/bin/php
<?php
/*
	vpn_pptp_users_edit.php
	part of m0n0wall (http://m0n0.ch/wall)
	
	Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
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

$pgtitle = array("VPN", "PPTP", "Edit user");
require("guiconfig.inc");

if (!is_array($config['pptpd']['user'])) {
	$config['pptpd']['user'] = array();
}
pptpd_users_sort();
$a_secret = &$config['pptpd']['user'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_secret[$id]) {
	$pconfig['username'] = $a_secret[$id]['name'];
	$pconfig['ip'] = $a_secret[$id]['ip'];
}

if ($_POST) {
	
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if (isset($id) && ($a_secret[$id])) {
		$reqdfields = explode(" ", "username");
		$reqdfieldsn = explode(",", "Username");
	} else {
		$reqdfields = explode(" ", "username password");
		$reqdfieldsn = explode(",", "Username,Password");
	}
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
	if (preg_match("/[^a-zA-Z0-9\.\-_]/", $_POST['username']))
		$input_errors[] = "The username contains invalid characters.";
		
	if (preg_match("/[^a-zA-Z0-9\.\-_]/", $_POST['password']))
		$input_errors[] = "The password contains invalid characters.";
	
	if (($_POST['password']) && ($_POST['password'] != $_POST['password2'])) {
		$input_errors[] = "The passwords do not match.";
	}
	if (($_POST['ip'] && !is_ipaddr($_POST['ip']))) {
		$input_errors[] = "The IP address entered is not valid.";
	}
	
	if (!$input_errors && !(isset($id) && $a_secret[$id])) {
		/* make sure there are no dupes */
		foreach ($a_secret as $secretent) {
			if ($secretent['name'] == $_POST['username']) {
				$input_errors[] = "Another entry with the same username already exists.";
				break;
			}
		}
	}

	if (!$input_errors) {
	
		if (isset($id) && $a_secret[$id])
			$secretent = $a_secret[$id];
	
		$secretent['name'] = $_POST['username'];
		$secretent['ip'] = $_POST['ip'];
		
		if ($_POST['password'])
			$secretent['password'] = $_POST['password'];
		
		if (isset($id) && $a_secret[$id])
			$a_secret[$id] = $secretent;
		else
			$a_secret[] = $secretent;
		
		write_config();
		touch($d_pptpuserdirty_path);
		
		header("Location: vpn_pptp_users.php");
		exit;
	}
}
?>
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="vpn_pptp_users_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Username</td>
                  <td width="78%" class="vtable">
					<?=$mandfldhtml;?><input name="username" type="text" class="formfld" id="username" size="20" value="<?=htmlspecialchars($pconfig['username']);?>"> 
                  </td>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Password</td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="password" type="password" class="formfld" id="password" size="20"> 
                    <br><?=$mandfldhtml;?><input name="password2" type="password" class="formfld" id="password2" size="20"> 
                    &nbsp;(confirmation)<?php if (isset($id) && $a_secret[$id]): ?><br>
                    <span class="vexpl">If you want to change the users' password, 
                    enter it here twice.</span><?php endif; ?></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">IP address</td>
                  <td width="78%" class="vtable"> 
                    <input name="ip" type="text" class="formfld" id="ip" size="20" value="<?=htmlspecialchars($pconfig['ip']);?>"> 
                    <br><span class="vexpl">If you want the user to be assigned a specific IP address, enter it here.</span></td>
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
