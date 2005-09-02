#!/usr/local/bin/php
<?php 
/*
	vpn_openvpn_cli_edit.php

	Copyright (C) 2004 Peter Curran (peter@closeconsultants.com).
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

$pgtitle = array("VPN", "OpenVPN", "Edit client");
require("guiconfig.inc");
require_once("openvpn.inc");

if (!is_array($config['ovpn']))
	$config['ovpn'] = array();
if (!is_array($config['ovpn']['client'])){
	$config['ovpn']['client'] =  array();
	$config['ovpn']['client']['tunnel'] = array();
}


$ovpncli =& $config['ovpn']['client']['tunnel'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $ovpncli[$id]) {
	$pconfig = $config['ovpn']['client']['tunnel'][$id];
	if (isset($ovpncli[$id]['pull']))
		$pconfig['pull'] = true;
}
else {
	/* creating - set defaults */
	$pconfig = array();
	$pconfig['type'] = 'tun';
	$pconfig['proto'] = 'udp';
	$pconfig['sport'] = '1194';
	$pconfig['ver'] = '2';
	$pconfig['crypto'] = 'BF-CBC';
	$pconfig['pull'] = true;
	$pconfig['enable'] = true;
}

if (isset($_POST['pull'])) {

	$pconfig = $_POST;

	$pconfig['ca_cert'] = base64_encode($pconfig['ca_cert']);
	$pconfig['cli_cert'] = base64_encode($pconfig['cli_cert']);
	$pconfig['cli_key'] = base64_encode($pconfig['cli_key']);

	/* Called from form */
	unset($input_errors);

	/* input validation */
	$reqdfields = explode(" ", "type saddr sport");
	$reqdfieldsn = explode(",", "Tunnel type,Address,Port");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
 
	/* valid Port */
	if (($_POST['sport'] && !is_port($_POST['sport'])))
		$input_errors[] = "The server's port must be an integer between 1 and 65535 (default 1194).";

	if (is_null($_POST['ca_cert']))
		$input_errors[] = "You must provide a CA certificate file";
	elseif (!strstr($_POST['ca_cert'], "BEGIN CERTIFICATE") || !strstr($_POST['ca_cert'], "END CERTIFICATE"))
		$input_errors[] = "The CA certificate does not appear to be valid.";
		
	if (is_null($_POST['cli_cert']))
		$input_errors[] = "You must provide a client certificate file";
	elseif (!strstr($_POST['cli_cert'], "BEGIN CERTIFICATE") || !strstr($_POST['cli_cert'], "END CERTIFICATE"))
		$input_errors[] = "The client certificate does not appear to be valid.";
		
	if (is_null($_POST['cli_key']))
		$input_errors[] = "You must provide a client key file";
	elseif (!strstr($_POST['cli_key'], "BEGIN RSA PRIVATE KEY") || !strstr($_POST['cli_key'], "END RSA PRIVATE KEY"))
		$input_errors[] = "The client key does not appear to be valid.";
	
	if (!$input_errors) {
		if (isset($id)) {
			/* Editing an existing entry */
			$ovpnent = $ovpncli[$id];

			if ( $ovpncli[$id]['sport'] != $_POST['sport'] ||
				$ovpncli[$id]['proto'] != $_POST['proto'] ) {

				/* some entries changed */
				for ($i = 0; isset($config['ovpn']['client']['tunnel'][$i]); $i++) {
					$current = &$config['ovpn']['client']['tunnel'][$i];

					if ($current['sport'] == $_POST['sport'])
						if ($current['proto'] == $_POST['proto'])
							$input_errors[] = "You already have this combination for port and protocol settings. You can't use it twice";
				}
			}

			/* Test Server type hasn't changed */
			if ($ovpnent['type'] != $_POST['type']) {
				$nxt_if = getnxt_client_if($_POST['type']);
				if (!$nxt_if)
					$input_errors[] = "Run out of devices for a tunnel of type {$_POST['type']}";
				else
					$ovpnent['if'] = $nxt_if;
				/* Need to reboot in order to create interfaces cleanly */
				touch($d_sysrebootreqd_path);
			}
			/* Has the enable/disable state changed? */
			if (isset($ovpnent['enable']) && isset($_POST['disabled'])) {
				touch($d_ovpnclidirty_path);
			}
			if (!isset($ovpnent['enable']) && !isset($_POST['disabled'])) {
				touch($d_ovpnclidirty_path);
			}
		}
		else {
			/* Creating a new entry */
			$ovpnent = array();
			$nxt_if = getnxt_client_if($_POST['type']);
			if (!$nxt_if)
				$input_errors[] = "Run out of devices for a tunnel of type {$_POST['type']}";
			else
				$ovpnent['if'] = $nxt_if;
			$ovpnent['port'] = getnxt_client_port();
			/* I think we have to reboot to have the interface created cleanly */
			touch($d_sysrebootreqd_path);
		}

		$ovpnent['type'] = $_POST['type'];
		$ovpnent['proto'] = $_POST['proto'];
		$ovpnent['sport'] = $_POST['sport'];
		$ovpnent['ver'] = $_POST['ver'];
		$ovpnent['saddr'] = $_POST['saddr'];
		$ovpnent['descr'] = $_POST['descr'];
		$ovpnent['ca_cert'] = $pconfig['ca_cert'];
		$ovpnent['cli_cert'] = $pconfig['cli_cert'];
		$ovpnent['cli_key'] = $pconfig['cli_key'];
		$ovpnent['crypto'] = $_POST['crypto'];
		$ovpnent['pull'] = true; //This is a fixed config for this version
		$ovpnent['enable'] = isset($_POST['disabled']) ? false : true;
		
	
		if (isset($id) && $ovpncli[$id]){
			$ovpncli[$id] = $ovpnent;
		}
		else{
			$ovpncli[] = $ovpnent;
		}
		
		write_config();
		touch($d_ovpnclidirty_path);

		header("Location: vpn_openvpn_cli.php");
		exit;
	}
}

?>
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>

<form action="vpn_openvpn_cli_edit.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
  <table width="100%" border="0" cellpadding="6" cellspacing="0">
    <tr>
      <td width="22%" valign="top" class="vncellreq">Disabled</td>
      <td width="78%" class="vtable"> 
        <input name="disabled" type="checkbox" id="disabled" value="yes" <?php if (!isset($pconfig['enable'])) echo "checked"; ?>>
        <strong>Disable this client</strong><br>
        <span class="vexpl">Set this option to disable this client without removing it from the list.</span>
      </td>
    </tr>
	
    <tr> 
      <td colspan="2" class="list" height="12"></td>
    </tr>
    
    <tr>
      <td colspan="2" valign="top" class="listtopic">Server information</td>
    </tr>
    <tr>
      <td valign="top" class="vncellreq">Tunnel type</td>
      <td class="vtable">
          <input name="type" type="radio" class="formfld" value="tun" <?php if ($pconfig['type'] == 'tun') echo "checked"; ?>> TUN&nbsp;
<input name="type" type="radio" class="formfld" value="tap" <?php if ($pconfig['type'] == 'tap') echo "checked"; ?>> TAP</td>
    </tr> 
    
    <tr>
      <td width="22%" valign="top" class="vncellreq">Tunnel protocol</td>
      <td width="78%" class="vtable">
<input name="proto" type="radio" class="formfld" value="udp" <?php if ($pconfig['proto'] == 'udp') echo "checked"; ?>> UDP&nbsp;
<input name="proto" type="radio" class="formfld" value="tcp" <?php if ($pconfig['proto'] == 'tcp') echo "checked"; ?>> TCP<br>
       <span class="vexpl">Important: These settings must match the server's configuration.</span></td>
     </tr>
    
    <tr>
      <td width="22%" valign="top" class="vncellreq">Port</td>
      <td width="78%" class="vtable">
        <input name="sport" type="text" class="formfld" size="5" maxlength="5" value="<?=htmlspecialchars($pconfig['sport']);?>"><br>
        Enter the server's port number (default is 1194).</td>
    </tr>
    
    <tr>
      <td width="22%" valign="top" class="vncellreq">Address</td>
      <td width="78%" class="vtable"> 
	<input name="saddr" type="text" class="formfld" size="20" maxlength="255" value="<?=htmlspecialchars($pconfig['saddr']);?>">
	<br>
	Enter the server's IP address or FQDN.</td>
    </tr>
    
    <tr>
      <td width="22%" valign="top" class="vncellreq">Version</td>
      <td width="78%" class="vtable"> 
        <input name="ver" type="radio" class="formfld" value="2" <?php if ($pconfig['ver'] == '2') echo "checked"; ?>> 2.0&nbsp;
	<input name="ver" type="radio" class="formfld" value="1" <?php if ($pconfig['ver'] == '1') echo "checked"; ?>> 1.x
	<br>
	Specify which version of the OpenVPN protocol the server runs.</td>
    </tr>
    
    <tr> 
      <td width="22%" valign="top" class="vncell">Description</td>
      <td width="78%" class="vtable"> 
        <input name="descr" type="text" class="formfld" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>"> 
        <br> <span class="vexpl">You may enter a description here for your reference (not parsed).</span></td>
    </tr>
    
    <tr> 
      <td colspan="2" class="list" height="12"></td>
    </tr>
    
    <tr> 
      <td colspan="2" valign="top" class="listtopic">Client configuration</td>
    </tr>
    
    <tr> 
      <td width="22%" valign="top" class="vncell">Interface</td>
      <td width="78%" class="vtable">
        <strong>Auto</strong>
      </td>
    </tr>
    
    <tr>
      <td width="22%" valign="top" class="vncell">Port</td>
      <td width="78%" class="vtable">
	<strong>Auto</strong>
      </td>
    </tr>
     
    <tr> 
      <td width="22%" valign="top" class="vncellreq">CA certificate</td>
      <td width="78%" class="vtable"> 
      <textarea name="ca_cert" cols="65" rows="4" class="formpre"><?=htmlspecialchars(base64_decode($pconfig['ca_cert']));?></textarea>
      <br>      
      Paste a CA certificate in X.509 PEM format here.</td>
    </tr>
		
    <tr> 
      <td width="22%" valign="top" class="vncellreq">Client certificate</td>
      <td width="78%" class="vtable">
        <textarea name="cli_cert" cols="65" rows="4" class="formpre"><?=htmlspecialchars(base64_decode($pconfig['cli_cert']));?></textarea>
        <br>
        Paste a client certificate in X.509 PEM format here.</td>
     </tr>
     
     <tr> 
       <td width="22%" valign="top" class="vncellreq">Client key</td>
       <td width="78%" class="vtable"> 
         <textarea name="cli_key" cols="65" rows="4" class="formpre"><?=htmlspecialchars(base64_decode($pconfig['cli_key']));?></textarea>
         <br>Paste the client RSA private key here.</td>
     </tr>
     
        
      <tr>
        <td width="22%" valign="top" class="vncell">Crypto</td>
        <td width="78%" class="vtable">
          <select name="crypto" class="formfld">
	    <?php $cipher_list = ovpn_get_cipher_list();
	    foreach($cipher_list as $key => $value){
	    ?>
	      <option value="<?= $key ?>" <?php if ($pconfig['crypto'] == $key) echo "selected"; ?>>
	        <?= $value ?>
	      </option>
	    <?php
	    }
	    ?>
	  </select>
	  <br>
	  Select the data channel encryption cipher.  This must match the setting on the server.
	</td>
      </tr>
      
     <tr>
       <td width="22%" valign="top" class="vncellreq">Options</td>
       <td width="78%" class="vtable">
         <input type="checkbox" name="pull" value="yes" <?php if ($pconfig['pull']) echo "checked"; ?>> 
         Client-pull</td>
     </tr>
     
     <tr> 
       <td width="22%" valign="top">&nbsp;</td>
       <td width="78%"> 
         <input name="Submit" type="submit" class="formbtn" value="Save"> 
         <?php if (isset($id)): ?>
         <input name="id" type="hidden" value="<?=$id;?>"> 
         <?php endif; ?>
       </td>
     </tr>
   </table>
</form>

<?php include("fend.inc"); ?>
