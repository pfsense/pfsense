<?php
/*
	vpn_openvpn_certs.php
	part of pfSense

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

require("guiconfig.inc");

//$pgtitle = array("OpenVPN", "Certificate management");
$ovpncapath = $g['varetc_path']."/openvpn/certificates";

if ($_GET['reset']) {
	mwexec("killall -9 openssl");
	if (is_dir($_GET['reset']))
		mwexec("rm -rf $ovpncapath/".$_GET['reset']);
}
if ($_GET['delete']) {
	if (!is_dir($ovpncapath."/".$_GET['delete'])) 
		$input_error[] = "Certificate does not exist!";
	else
	    mwexec("rm -rf ".$g['varetc_path']."/openvpn/certificates/".$_GET['delete']);
	if (is_array($config['openvpn']['keys'])) {
		if (is_array($config['openvpn']['keys'][$_GET['delete']])) {
			unset($config['openvpn']['keys'][$_GET['delete']]);
			if (count($config['openvpn']['keys']) < 1)
				unset($config['openvpn']);
			write_config();
		}
	}
}

exec("cd ".$g['varetc_path']."/openvpn/certificates && /usr/bin/find . -type d -name \"[a-zA-Z0-9_]*\"", $certificates);
rsort($certificates);
function cleanup($text) {
	return preg_replace("/^\.\//", "", $text);
}
$certificates = array_map(cleanup, $certificates);
	
include("head.inc");
?>

    <body link="#0000CC" vlink="#0000CC" alink="#0000CC">
    <?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<?php if ($input_errors) print_input_errors($input_errors); ?>

<form action="vpn_openvpn_certs.php" method="post" name="iform" id="iform">
<?php if ($savemsg) print_info_box($savemsg); ?>

	  <table width="100%" border="0" cellpadding="6" cellspacing="0">
	  <tr><td>
<?php
	$tab_array = array();
	$tab_array[0] = array("Server", false, "pkg.php?xml=openvpn.xml");
	$tab_array[1] = array("Client", false, "pkg.php?xml=openvpn_cli.xml");
	$tab_array[2] = array("Client-specific configuration", false, "pkg.php?xml=openvpn_csc.xml");
	$tab_array[3] = array("Certificates", true, "vpn_openvpn_certs.php");
	display_top_tabs($tab_array);
?>
  	</td></tr>
      <tr><td class="listhdrr">Certificates</td></tr>
	 <?php foreach ($certificates as $cert) { ?>
	  				<tr class="vtable">
                      <td class="listt">
                        <a href="vpn_openvpn_certs_create.php?ca=<?=$cert;?>"><?=$cert?></a>
                        </td>
					<td><a href="vpn_openvpn_certs.php?delete=<?=$cert;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" title="<?=gettext("delete certificate");?>" width="17" height="17" border="0" alt="" /></a></td>
                    </tr>
 	<?php } ?>
				<tr><td><a href="vpn_openvpn_certs_create.php"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("add a new certificate");?> width="17" height="17" border="0" alt="" /></a></td></tr>
	</table>
    <?php include("fend.inc"); ?>
</body>
</html>
