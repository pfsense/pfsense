<?php
/* $Id$ */
/*
	vpn_openvpn_certs_existing.php
	part of pfSense

	Copyright (C) 2008 Scott Ullrich
	Copyright (C) 2008 Ermal Luçi
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

##|+PRIV
##|*IDENT=page-vpn-openvpn-createexistingcerts
##|*NAME=VPN: OpenVPN: Create Existing Certs page
##|*DESCR=Allow access to the 'VPN: OpenVPN: Create Existing Certs' page.
##|*MATCH=vpn_openvpn_certs_existing.php*
##|-PRIV


require("guiconfig.inc");

$pgtitle = array("VPN", "OpenVPN", "Create Existing Certs");
$ovpncapath = $g['varetc_path']."/openvpn/certificates";
/* XXX: hardcoded path; worth making it a global?! */
$easyrsapath = "/usr/local/share/openvpn/certificates";

if ($_GET['ca']) {
	if ($config['openvpn']['keys'][$_GET['ca']]) {
		$data = $config['openvpn']['keys'][$_GET['ca']];
		$caname = trim($_GET['ca']);
	        $cakey = $ovpnkeys[$caname]['ca.key'];
      	 	$cacrt =  $ovpnkeys[$caname]['ca.crt'];
       		$sharedkey =  $ovpnkeys[$caname]['shared.key'];
       		$serverkey =  $ovpnkeys[$caname]['server.key'];
       		$servercrt =  $ovpnkeys[$caname]['server.crt'];
       		$dh =  $ovpnkeys[$caname]['dh_params.dh'];
	} else
		$input_errors[] = "Certificate does not exist.";
}

if ($_POST) {
	if ($_POST['caname'] && $_POST['caname'] != "") {
		$caname = $_POST['caname'];

	/* Create sane environment for easyrsa scripts */
	conf_mount_rw();
	if (!is_dir($g['varetc_path']."/openvpn"))
		safe_mkdir($g['varetc_path']."/openvpn");
	if (!is_dir($ovpncapath)) 
		safe_mkdir($ovpncapath);
	else
	    mwexec("rm -rf $ovpncapath/$caname");
	safe_mkdir("$ovpncapath/$caname", 0755);

	if (!is_dir($ovpncapath)) {
		$input_errors[] = "Failed to create environment for creating certificates. ";
		header("Location: vpn_openvpn_certs.php");
	}

        conf_mount_ro();
        if (!is_array($config['openvpn']['keys']))
                $config['openvpn']['keys'] = array();
        $ovpnkeys =& $config['openvpn']['keys'];
        if (!is_array($ovpnkeys[$caname]))
                $ovpnkeys[$caname] = array();
        /* vars */
        $ovpnkeys[$caname]['existing'] = "yes";
        /* ciphers */
        $ovpnkeys[$caname]['crl'] = $crl;
	file_put_contents("$ovpncapath/$caname/crl.pem", base64_decode($_POST['crl']));
	chown("$ovpncapath/$caname/crl.pem", 'nobody');
	chgrp("$ovpncapath/$caname/crl.pem", 'nobody');

        $ovpnkeys[$caname]['ca.crt'] = $cacrt;
	file_put_contents("$ovpncapath/$caname/ca.crt", base64_decode($_POST['ca.crt']));
        chown("$ovpncapath/$caname/ca.crt", 'nobody');
        chgrp("$ovpncapath/$caname/ca.crt", 'nobody');
        
	$ovpnkeys[$caname]['server.key'] = $serverkey;
	file_put_contents("$ovpncapath/$caname/server.key", base64_decode($_POST['server.key']));
        chown("$ovpncapath/$caname/server.key", 'nobody');
        chgrp("$ovpncapath/$caname/server.key", 'nobody');
        
	$ovpnkeys[$caname]['server.crt'] = $servercrt;
	file_put_contents("$ovpncapath/$caname/server.crt", base64_decode($_POST['server.crt']));
        chown("$ovpncapath/$caname/server.crt", 'nobody');
        chgrp("$ovpncapath/$caname/server.crt", 'nobody');
        
	$ovpnkeys[$caname]['dh_params.dh'] = $dh;
        file_put_contents("$ovpncapath/$caname/dh_params.dh", base64_decode($_POST['dh']));
        chown("$ovpncapath/$caname/dh_params.dh", 'nobody');
        chgrp("$ovpncapath/$caname/dh_params.dh", 'nobody');
	
	/* save it */
        write_config();
	
	header("Location: vpn_openvpn_certs.php");
	} else 
		$input_errors[] = "You need to specify the Certificate name";
}

	include("head.inc");
?>

    <body link="#0000CC" vlink="#0000CC" alink="#0000CC">

    <?php include("fbegin.inc"); ?>

<?php if ($input_errors) print_input_errors($input_errors); ?>

<form action="vpn_openvpn_certs_existing.php" method="post" name="iform" id="iform">
<?php if ($savemsg) print_info_box($savemsg); ?>

	  <table width="90%" border="0" cellpadding="6" cellspacing="0">
	<tr><td colspan="2">
<?php
        $tab_array = array();
        $tab_array[0] = array("Server", false, "pkg.php?xml=openvpn.xml");
        $tab_array[1] = array("Client", false, "pkg.php?xml=openvpn_cli.xml");
        $tab_array[2] = array("Client-specific configuration", false, "pkg.php?xml=openvpn_csc.xml");
        $tab_array[3] = array("Certificate Authority", true, "vpn_openvpn_certs.php");
	$tab_array[4] = array("Users", false, "vpn_openvpn_users.php");
        display_top_tabs($tab_array);
?>
	</td></tr>
	<tr><td>
        <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
                   <tr>
                      <td width="35%"  class="vncell"><B>Certificate name</td>
                      <td width="78%" class="vtable">
                        <input name="caname"  value="<?=$caname?>">
			</td>
                    </tr>
		<tr>
                      <td width="35%"  class="vncell"><B>CA certificate</td>
                      <td width="78%" class="vtable">
                        <textarea name="ca.crt" rows="8" cols="40" ><?=$cacrt;?></textarea>
                        <br/><span>Paste your CA certificate in X.509 format here.</span></td>
                    </tr>
                    <tr>
                      <td width="35%"  class="vncell"><B>Server certificate</td>
                      <td width="78%" class="vtable">
			<textarea name="server.crt" rows="8" cols="40" ><?=$servercrt;?></textarea>
			<br/><span>Paste your server certificate in X.509 format here.</span>
			</td>
                    </tr>
                   <tr>
                      <td width="35%"  class="vncell"><B>Server key</td>
                      <td width="78%" class="vtable">
                        <textarea name="server.key" rows="8" cols="40" ><?=$serverkey;?></textarea>
			<br/><span>Paste your server key in RSA format here.</span>
			</td>
                    </tr>
                   <tr>
                      <td width="35%"  class="vncell"><B>DH parameters</td>
                      <td width="78%" class="vtable">
                        <textarea name="dh" rows="8" cols="40"><?=$dh;?></textarea>
			<br/><span>Paste your Diffie Hellman parameters in PEM format here.</span>
			</td>
                    </tr>
                   <tr>
                      <td width="35%"  class="vncell"><B>CRL</td>
                      <td width="78%" class="vtable">
                        <textarea name="crl" rows="8" cols="40" ><?=$crl;?></textarea>
			<br/><span>Paste your certificate revocation list (CRL) in PEM format here (optional).</span>
			</td>
                    </tr>
		    <tr>
		      <td width="35%" >&nbsp;</td>
		      <td width="78%">
			<input name="Submit" type="submit" class="formbtn" value="Save">
			<a href="vpn_openvpn_certs.php?reset=<?=$caname;?>"><input name="Cancel" type="button" class="formbtn" value="Cancel"></a>
			</td>
		      </td>
		    </tr>
	</table></td></tr>
	</table>
    <?php include("fend.inc"); ?>
    </body>
    </html>
