#!/usr/local/bin/php
<?php 
/*
	vpn_openvpn_crl_edit.php

	Copyright (C) 2005 Peter Allgeyer (allgeyer@web.de).
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
require_once("openvpn.inc");

if (!is_array($config['ovpn']))
	$config['ovpn'] = array();
if (!is_array($config['ovpn']['server']))
	$config['ovpn']['server'] = array();
if (!is_array($config['ovpn']['server']['crl']))
	$config['ovpn']['server']['crl'] = array();

$ovpncrl =& $config['ovpn']['server']['crl'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $ovpncrl[$id]) {

	$pconfig = $config['ovpn']['server']['crl'][$id];

	if (isset($ovpncrl[$id]['enable']))
		$pconfig['enable'] = true;

} else {
	/* creating - set defaults */
	$pconfig = array();
	$pconfig['enable'] = true;
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "crlname");
	$reqdfieldsn = explode(",", "Name");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if (preg_match("/[^a-zA-Z0-9\.\-_]/", $_POST['crlname']))
		$input_errors[] = "The name contains invalid characters.";

	/* Editing an existing entry? */
	if (!$input_errors && !(isset($id) && $ovpncrl[$id])) {
		/* make sure there are no dupes */
		foreach ($ovpncrl as $crlent) {
			if ($crlent['crlname'] == $_POST['crlname']) {
				$input_errors[] = "Another entry with the same name already exists.";
				break;
			}
		}
	}

	/* check if a crl was given */
	if (is_uploaded_file($_FILES['filename']['tmp_name']) && !empty($_FILES['filename']['size'])) {
		$content = file_get_contents($_FILES['filename']['tmp_name']);
	} else if (!empty($_POST['crl_list'])) {
		$content = $_POST['crl_list'];
	} else {
		$content = "";
		$input_errors[] = "A valid X.509 CRL is required.";
	}

	/* check if crl is valid */
	if (!empty($content) &&
	   (!strstr($content, "BEGIN X509 CRL") ||
	    !strstr($content, "END X509 CRL")))
		$input_errors[] = "The X.509 CRL file content does not appear to be valid.";

	if (isset($id) && $ovpncrl[$id]) {
		$crlent = $ovpncrl[$id];

		/* Has the enable/disable state changed? */
		if (isset($crlent['enable']) && isset($_POST['disabled'])) {
			/* status changed to disabled */
			ovpn_crl_dirty($ovpncrl['crlname']);
		} else if (!isset($crlent['enable']) && !isset($_POST['disabled'])) {
			/* status changed to enable */
			ovpn_crl_dirty($ovpncrl['crlname']);
		}
	}

	if (!$input_errors) {
        
		$crlent = array();

		if (isset($id) && $ovpncrl[$id])
			$crlent = $ovpncrl[$id];
        
                $crlent['crlname'] = $_POST['crlname'];
		$crlent['descr'] = $_POST['descr'];
		$crlent['enable'] = $_POST['disabled'] ? false : true;

		/* file upload? */
		if ($_POST['crlname'] && is_uploaded_file($_FILES['filename']['tmp_name']))
			$crlent['crl_list'] = base64_encode(file_get_contents($_FILES['filename']['tmp_name']));
		else if (!empty($_POST['crl_list']))
			$crlent['crl_list'] = base64_encode($_POST['crl_list']);

                if (isset($id) && $ovpncrl[$id])
                        $ovpncrl[$id] = $crlent;
                else
                        $ovpncrl[] = $crlent;
                
                write_config();
		ovpn_crl_dirty($ovpncrl['crlname']);
                
                header("Location: vpn_openvpn_crl.php");
                exit;

	} else {

		$pconfig = $_POST;

		$pconfig['enable'] = "true";
		if (isset($_POST['disabled']))
			unset($pconfig['enable']);

		$pconfig['crl_list'] = base64_encode($_POST['crl_list']);
	}
}

$pgtitle = "VPN: OpenVPN: Edit client-specific configuration";
include("head.inc");

?>

<?php include("fbegin.inc"); ?>
<script language="JavaScript">
function enable_change(enable_over) {
	var endis;
	endis = !(!document.iform.disabled.checked || enable_over);
        
	document.iform.crlname.disabled = endis;
	document.iform.descr.disabled = endis;
	document.iform.crl_list.disabled = endis;
	document.iform.filename.disabled = endis;

}

//-->
</script>

<?php if ($input_errors) print_input_errors($input_errors);?>
<form action="vpn_openvpn_crl_edit.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<strong><span class="red">WARNING: This feature is experimental and modifies your optional interface configuration.
  Backup your configuration before using OpenVPN, and restore it before upgrading.<br>&nbsp;<br>
</span></strong>
<table width="100%" border="0" cellpadding="6" cellspacing="0">
    <tr>
     <td width="22%" valign="top" class="vncellreq">Disabled</td>
     <td width="78%" class="vtable">
	<input name="disabled" type="checkbox" value="yes" onclick="enable_change(false)" <?php if (!isset($pconfig['enable'])) echo "checked"; ?>>
	<strong>Disable this X.509 CRL list</strong><br>
	<span class="vexpl">Set this option to on to disable this X.509 CRL file
	without removing it from the list.</span></td>
      </td>
    </tr>
   
    <tr> 
      <td width="22%" valign="top" class="vncellreq">Name</td>
      <td width="78%" class="vtable"> 
        <input name="crlname" type="text" class="formfld" id="crlname" size="40" value="<?=htmlspecialchars($pconfig['crlname']);?>"> 
        <br><span class="vexpl">Enter a unique name here, to describe the X.509 CRL list.</span></td>
    </tr>

    <tr> 
      <td width="22%" valign="top" class="vncell">Description</td>
      <td width="78%" class="vtable"> 
        <input name="descr" type="text" class="formfld" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>"> 
        <br><span class="vexpl">You may enter a description here for your reference (not parsed).</span></td>
    </tr>

    <tr>
      <td valign="top" class="vncellreq">X.509 CRL file content</td>
      <td class="vtable">
        <textarea name="crl_list" cols="65" rows="4" class="formpre"><?=htmlspecialchars(base64_decode($pconfig['crl_list']));?></textarea>
        <br>
	Paste the contents of a X.509 CRL file in PEM format here.</td>
    </tr>

    <tr>
      <td width="22%" valign="top" class="vncell">X.509 CRL file</td>
      <td class="vtable">
	<input name="filename" type="file" class="formfld" id="filename"><br>
	Instead of pasting the contents of a X.509 CRL file above,
	you can upload a X.509 CRL file in PEM format here. It will
	overwrite the values entered in the "X.509 CRL file content"
	field.
      </td>
    </tr>

    <tr>
      <td width="22%" valign="top">&nbsp;</td>
      <td width="78%">
        <input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)">
        <?php if (isset($id)): ?>
        <input name="id" type="hidden" value="<?=$id;?>"> 
        <?php endif; ?>
      </td>
    </tr>
</table>
</form>
<script language="JavaScript">
<!--
enable_change(false);
//-->
</script>
<?php include("fend.inc");
?>
