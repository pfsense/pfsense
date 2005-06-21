#!/usr/local/bin/php
<?php
/*
	vpn_ipsec_ca_edit.php
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

$pgtitle = array("VPN", "IPsec", "Edit CA certificate");
require("guiconfig.inc");

if (!is_array($config['ipsec']['cacert'])) {
	$config['ipsec']['cacert'] = array();
}
ipsec_ca_sort();
$a_secret = &$config['ipsec']['cacert'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_secret[$id]) {
	$pconfig['ident'] = $a_secret[$id]['ident'];
	$pconfig['cert'] = base64_decode($a_secret[$id]['cert']);
}

if ($_POST) {
	
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "ident cert");
	$reqdfieldsn = explode(",", "Identifier,CA Certificate");
	if (!strstr($_POST['cert'], "BEGIN CERTIFICATE") || !strstr($_POST['cert'], "END CERTIFICATE"))
			$input_errors[] = "This certificate does not appear to be valid.";
	
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
		$secretent['cert'] = base64_encode($_POST['cert']);
		
		if (isset($id) && $a_secret[$id])
			$a_secret[$id] = $secretent;
		else
			$a_secret[] = $secretent;
		
		write_config();
		touch($d_ipsecconfdirty_path);
		
		header("Location: vpn_ipsec_ca.php");
		exit;
	}
}
?>
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="vpn_ipsec_ca_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr> 
                  <td valign="top" class="vncellreq">Identifier</td>
                  <td class="vtable">
 <input name="ident" type="text" class="formfld" id="ident" size="30" value="<?=$pconfig['ident'];?>">
                    <br>
This can be any text to describe the certificate authority.       
                  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Certificate</td>
                  <td width="78%" class="vtable"> 
                    <textarea name="cert" cols="65" rows="7" id="cert" class="formpre"><?=htmlspecialchars($pconfig['cert']);?></textarea>
                    <br> 
                    Paste a CA certificate in X.509 PEM format here.</td>
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
