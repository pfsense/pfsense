#!/usr/local/bin/php
<?php 
/*
	vpn_openvpn_ccd_edit.php

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

$pgtitle = array("VPN", "OpenVPN", "Edit client-specific configuration");
require("guiconfig.inc");
require_once("openvpn.inc");

if (!is_array($config['ovpn']))
	$config['ovpn'] = array();
if (!is_array($config['ovpn']['server']))
	$config['ovpn']['server'] = array();
if (!is_array($config['ovpn']['server']['ccd']))
	$config['ovpn']['server']['ccd'] = array();

$ovpnccd =& $config['ovpn']['server']['ccd'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $ovpnccd[$id]) {

	$pconfig = $config['ovpn']['server']['ccd'][$id];

	if (isset($ovpnccd[$id]['enable']))
		$pconfig['enable'] = true;

	if (is_array($config['ovpn']['server']['ccd'][$id]['options'])) {
		$pconfig['options'] = "";
		foreach ($ovpnccd[$id]['options']['option'] as $optent) {
			$pconfig['options'] .= $optent . "\n";
		}
		$pconfig['options'] = rtrim($pconfig['options']);
	}

} else {
	/* creating - set defaults */
	$pconfig = array();
	$pconfig['enable'] = true;
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "cn");
	$reqdfieldsn = explode(",", "Common name");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if (preg_match("/[^a-zA-Z0-9\.\-_\:\/\@]/", $_POST['cn']))
		$input_errors[] = "The common name contains invalid characters.";

	if ($_POST['psh_pingrst'] && $_POST['psh_pingexit'])
		$input_errors[] = "Ping-restart and Ping-exit are mutually exclusive and cannot be used together";

	if ($_POST['psh_rtedelay'] && !is_numeric($_POST['psh_rtedelay_int']))
		$input_errors[] = "Route-delay needs a numerical interval setting.";

	if ($_POST['psh_inact'] && !is_numeric($_POST['psh_inact_int']))
		$input_errors[] = "Inactive needs a numerical interval setting.";

	if ($_POST['psh_ping'] && !is_numeric($_POST['psh_ping_int']))
		$input_errors[] = "Ping needs a numerical interval setting.";
			
	if ($_POST['psh_pingexit'] && !is_numeric($_POST['psh_pingexit_int']))
		$input_errors[] = "Ping-exit needs a numerical interval setting.";

	if ($_POST['psh_pingrst'] && !is_numeric($_POST['psh_pingrst_int']))
		$input_errors[] = "Ping-restart needs a numerical interval setting.";

	/* Editing an existing entry? */
	if (!$input_errors && !(isset($id) && $ovpnccd[$id])) {
		/* make sure there are no dupes */
		foreach ($ovpnccd as $ccdent) {
			if ($ccdent['cn'] == $_POST['cn']) {
				$input_errors[] = "Another entry with the same common name already exists.";
				break;
			}
		}
	}

	if (isset($id) && $ovpnccd[$id]) {
		$ccdent = $ovpnccd[$id];

		/* Has the enable/disable state changed? */
		if (isset($ccdent['enable']) && isset($_POST['disabled'])) {
			/* status changed to disabled */
			touch($d_ovpnccddirty_path);
		}

		/* status changed to enable */
		if (!isset($ccdent['enable']) && !isset($_POST['disabled'])) {
			/* touch($d_sysrebootreqd_path); */
			touch($d_ovpnccddirty_path);
		}
	}

	if (!$input_errors) {
        
		$ccdent = array();

		if (isset($id) && $ovpnccd[$id])
			$ccdent = $ovpnccd[$id];
        
                $ccdent['cn'] = $_POST['cn'];
		$ccdent['descr'] = $_POST['descr'];
		$ccdent['enable'] = $_POST['disabled'] ? false : true;
		$ccdent['disable'] = $_POST['disable'] ? true : false;


                if (!is_array($options))
                        $options = array();
                if (!is_array($ccdent['options']))
                        $ccdent['options'] = array();

		$options['option'] = array_map('trim', explode("\n", trim($_POST['options'])));
		$ccdent['options'] = $options;

		$ccdent['psh_reset'] = $_POST['psh_reset'] ? true : false;
		$ccdent['psh_options']['redir'] = $_POST['psh_redir'] ? true : false;
		$ccdent['psh_options']['redir_loc'] = $_POST['psh_redir_loc'] ? true : false;
		$ccdent['psh_options']['rtedelay'] = $_POST['psh_rtedelay'] ? true : false;
		$ccdent['psh_options']['inact'] = $_POST['psh_inact'] ? true : false;
		$ccdent['psh_options']['ping'] = $_POST['psh_ping'] ? true : false;
		$ccdent['psh_options']['pingrst'] = $_POST['psh_pingrst'] ? true : false;
		$ccdent['psh_options']['pingexit'] = $_POST['psh_pingexit'] ? true : false;

		unset($ccdent['psh_options']['rtedelay_int']);
		unset($ccdent['psh_options']['inact_int']);
		unset($ccdent['psh_options']['ping_int']);
		unset($ccdent['psh_options']['pingrst_int']);
		unset($ccdent['psh_options']['pingexit_int']);

		if ($_POST['psh_rtedelay_int'])
			$ccdent['psh_options']['rtedelay_int'] = $_POST['psh_rtedelay_int'];
		if ($_POST['psh_inact_int'])
			$ccdent['psh_options']['inact_int'] = $_POST['psh_inact_int'];
		if ($_POST['psh_ping_int'])
			$ccdent['psh_options']['ping_int'] = $_POST['psh_ping_int'];
		if ($_POST['psh_pingrst_int'])
			$ccdent['psh_options']['pingrst_int'] = $_POST['psh_pingrst_int'];
		if ($_POST['psh_pingexit_int'])
			$ccdent['psh_options']['pingexit_int'] = $_POST['psh_pingexit_int'];

                if (isset($id) && $ovpnccd[$id])
                        $ovpnccd[$id] = $ccdent;
                else
                        $ovpnccd[] = $ccdent;
                
                write_config();
                touch($d_ovpnccddirty_path);
                
                header("Location: vpn_openvpn_ccd.php");
                exit;

	} else {

		$pconfig = $_POST;

		$pconfig['enable'] = "true";
		if (isset($_POST['disabled']))
			unset($pconfig['enable']);

		$pconfig['psh_reset'] = $_POST['psh_reset'];
		$pconfig['psh_options']['redir'] = $_POST['psh_redir'];
		$pconfig['psh_options']['redir_loc'] = $_POST['psh_redir_loc'];
		$pconfig['psh_options']['rtedelay'] = $_POST['psh_rtedelay'];
		$pconfig['psh_options']['inact'] = $_POST['psh_inact'];
		$pconfig['psh_options']['ping'] = $_POST['psh_ping'];
		$pconfig['psh_options']['pingrst'] = $_POST['psh_pingrst'];
		$pconfig['psh_options']['pingexit'] = $_POST['psh_pingexit'];

		$pconfig['psh_options']['rtedelay_int'] = $_POST['psh_rtedelay_int'];
		$pconfig['psh_options']['inact_int'] = $_POST['psh_inact_int'];
		$pconfig['psh_options']['ping_int'] = $_POST['psh_ping_int'];
		$pconfig['psh_options']['pingrst_int'] = $_POST['psh_pingrst_int'];
		$pconfig['psh_options']['pingexit_int'] = $_POST['psh_pingexit_int'];
	}
}

$pgtitle = "VPN: OpenVPN: Edit client-specific configuration";
include("head.inc");
include("fbegin.inc");
?>
<script language="JavaScript">
function enable_change(enable_over) {
	var endis;
	endis = !(!document.iform.disabled.checked || enable_over);
        
	document.iform.cn.disabled = endis;
	document.iform.disable.disabled = endis;
	document.iform.descr.disabled = endis;
	document.iform.psh_reset.disabled = endis;
	document.iform.psh_redir.disabled = endis;
	document.iform.psh_redir_loc.disabled = endis;
	document.iform.psh_rtedelay.disabled = endis;
	document.iform.psh_rtedelay_int.disabled = endis;
	document.iform.psh_inact.disabled = endis;
	document.iform.psh_inact_int.disabled = endis;
	document.iform.psh_ping.disabled = endis;
	document.iform.psh_ping_int.disabled = endis;
	document.iform.psh_pingexit.disabled = endis;
	document.iform.psh_pingexit_int.disabled = endis;
	document.iform.psh_pingrst.disabled = endis;
	document.iform.psh_pingrst_int.disabled = endis;
	document.iform.options.disabled = endis;

	if (!document.iform.disabled.checked) {
		push_change(false);
		disable_change(false);
	}

}

function disable_change(enable_over) {
	var endis;
	endis = !(!document.iform.disable.checked || enable_over);
        
	document.iform.psh_reset.disabled = endis;
	document.iform.psh_redir.disabled = endis;
	document.iform.psh_redir_loc.disabled = endis;
	document.iform.psh_rtedelay.disabled = endis;
	document.iform.psh_rtedelay_int.disabled = endis;
	document.iform.psh_inact.disabled = endis;
	document.iform.psh_inact_int.disabled = endis;
	document.iform.psh_ping.disabled = endis;
	document.iform.psh_ping_int.disabled = endis;
	document.iform.psh_pingexit.disabled = endis;
	document.iform.psh_pingexit_int.disabled = endis;
	document.iform.psh_pingrst.disabled = endis;
	document.iform.psh_pingrst_int.disabled = endis;
	document.iform.options.disabled = endis;

	if (!document.iform.disable.checked) {
		push_change(enable_over);
	}

}

function push_change(enable_over) {
	var endis;
	endis = !(document.iform.psh_reset.checked || enable_over);
        
	document.iform.psh_redir.disabled = endis;
	document.iform.psh_redir_loc.disabled = endis;
	document.iform.psh_rtedelay.disabled = endis;
	document.iform.psh_rtedelay_int.disabled = endis;
	document.iform.psh_inact.disabled = endis;
	document.iform.psh_inact_int.disabled = endis;
	document.iform.psh_ping.disabled = endis;
	document.iform.psh_ping_int.disabled = endis;
	document.iform.psh_pingexit.disabled = endis;
	document.iform.psh_pingexit_int.disabled = endis;
	document.iform.psh_pingrst.disabled = endis;
	document.iform.psh_pingrst_int.disabled = endis;
}

//-->
</script>

<?php if ($input_errors) print_input_errors($input_errors);?>
<form action="vpn_openvpn_ccd_edit.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<strong><span class="red">WARNING: This feature is experimental and modifies your optional interface configuration.
  Backup your configuration before using OpenVPN, and restore it before upgrading.<br>&nbsp;<br>
</span></strong>
<table width="100%" border="0" cellpadding="6" cellspacing="0">
    <tr>
     <td width="22%" valign="top" class="vncellreq">Disabled</td>
     <td width="78%" class="vtable">
	<input name="disabled" type="checkbox" value="yes" onclick="enable_change(false)" <?php if (!isset($pconfig['enable'])) echo "checked"; ?>>
	<strong>Disable this entry</strong><br>
	<span class="vexpl">Set this option to disable this client-specific configuration
	without removing it from the list.</span></td>
      </td>
    </tr>
   
    <tr> 
      <td width="22%" valign="top" class="vncellreq">Common Name</td>
      <td width="78%" class="vtable"> 
        <input name="cn" type="text" class="formfld" id="cn" size="40" value="<?=htmlspecialchars($pconfig['cn']);?>"> 
        <br><span class="vexpl">Enter client's X.509 common name here.</span></td>
    </tr>

    <tr> 
      <td width="22%" valign="top" class="vncell">Description</td>
      <td width="78%" class="vtable"> 
        <input name="descr" type="text" class="formfld" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>"> 
        <br><span class="vexpl">You may enter a description here for your reference (not parsed).</span></td>
    </tr>

    <tr>
     <td width="22%" valign="top" class="vncell">Block client</td>
     <td width="78%" class="vtable">
	<input name="disable" type="checkbox" value="yes" onclick="disable_change(false)" <?php if (isset($pconfig['disable'])) echo "checked"; ?>>
	<strong>Disable this client from connecting</strong><br>
	<span class="vexpl">Disable a particular client (based on the common name) from connecting.
	Don't use this option to disable a client due to key
	or password compromise. Use a CRL (certificate revocation list)
	instead.</span></td>
      </td>
    </tr>
   
    <tr> 
    <tr> 
      <td colspan="2" valign="top" height="16"></td>
    </tr>
    <tr>
      <td colspan="2" valign="top" class="listtopic">Push options</td>
    </tr>
	 
    <tr>
      <td width="22%" valign="top" class="vncell">Client-Push Inheritation</td>
      <td width="78%" class="vtable">
	<input type="checkbox" name="psh_reset" value="yes" onchange="push_change(false)" <?php if (isset($pconfig['psh_reset'])) echo "checked"; ?>>Push reset
	<br><span class="vexpl">Set this option to on, if you don't want to inherit
	the global push list for this client from the server page.</span>
      </td>
    </tr>
    <tr>
      <td width="22%" valign="top" class="vncell">Client-push options</td>
      <td width="78%" class="vtable">
	<table border="0" cellspacing="0" cellpadding="0">
	  <tr>
            <td><input type="checkbox" name="psh_redir" value="yes" <?php if (isset($pconfig['psh_options']['redir'])) echo "checked"; ?>>
            Redirect-gateway</td>
            <td>&nbsp;</td>
            <td><input type="checkbox" name="psh_redir_loc" value="yes" <?php if (isset($pconfig['psh_options']['redir_loc'])) echo "checked"; ?>>
              Local</td>
	  </tr>
          <tr>
            <td><input type="checkbox" name="psh_rtedelay" value="yes" <?php if (isset($pconfig['psh_options']['rtedelay'])) echo "checked"; ?>> Route-delay</td>
            <td width="16">&nbsp;</td>
            <td><input type="text" name="psh_rtedelay_int" class="formfld" size="4" value="<?= $pconfig['psh_options']['rtedelay_int']?>"> seconds</td>
          </tr>
          <tr>
            <td><input type="checkbox" name="psh_inact" value="yes" <?php if (isset($pconfig['psh_options']['inact'])) echo "checked"; ?>>
    Inactive</td>
            <td>&nbsp;</td>
            <td><input type="text" name="psh_inact_int" class="formfld" size="4" value="<?= $pconfig['psh_options']['inact_int']?>">
    seconds</td>
          </tr>
          <tr>
            <td><input type="checkbox" name="psh_ping" value="yes" <?php if (isset($pconfig['psh_options']['ping'])) echo "checked"; ?>> Ping</td>
            <td>&nbsp;</td>
            <td>Interval: <input type="text" name="psh_ping_int" class="formfld" size="4" value="<?= $pconfig['psh_options']['ping_int']?>"> seconds</td>
          </tr>
          <tr>
            <td><input type="checkbox" name="psh_pingexit" value="yes" <?php if (isset($pconfig['psh_options']['pingexit'])) echo "checked"; ?>> Ping-exit</td>
            <td>&nbsp;</td>
            <td>Interval: <input type="text" name="psh_pingexit_int" class="formfld" size="4" value="<?= $pconfig['psh_options']['pingexit_int']?>"> seconds</td>
          </tr>
          <tr>
            <td><input type="checkbox" name="psh_pingrst" value="yes" <?php if (isset($pconfig['psh_options']['pingrst'])) echo "checked"; ?>> Ping-restart</td>
            <td>&nbsp;</td>
            <td>Interval: <input type="text" name="psh_pingrst_int" class="formfld" size="4" value="<?= $pconfig['psh_options']['pingrst_int']?>"> seconds</td>
          </tr>
        </table></td>
    </tr>

     <tr>
      <td width="22%" valign="top" class="vncell">Custom client options</td>
      <td width="78%" class="vtable">
	<span>The following options are legal in a client-specific  context:<br>
	push, push-reset, iroute, ifconfig-push and config.</span><br>
        <textarea name="options" id="options" cols="65" rows="4" class="formpre"><?=htmlspecialchars($pconfig['options']);?></textarea>
	<strong><span class="red">Note:</span></strong><br>
	Commands in here aren't supported.</span></strong>
        </td>
    </tr>

    <tr>
      <td width="22%" valign="top">&nbsp;</td>
      <td width="78%">
        <input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true);disable_change(true)">
        <?php if (isset($id)): ?>
        <input name="id" type="hidden" value="<?=$id;?>"> 
        <?php endif; ?>
      </td>
    </tr>
</table>
</form>
<script language="JavaScript">
<!--
disable_change(false);
push_change(false);
enable_change(false);
//-->
</script>
<?php include("fend.inc");
?>
