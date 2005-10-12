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
	if (is_array($ovpncli[$id]['expertmode'])) {
		$pconfig['expertmode_options'] = "";
		foreach ($ovpncli[$id]['expertmode']['option'] as $optent) {
			$pconfig['expertmode_options'] .= $optent . "\n"; 
		}
		$pconfig['expertmode_options'] = rtrim($pconfig['expertmode_options']); 
	}

} else {
	/* creating - set defaults */
	$pconfig = array();
	$pconfig['authentication_method'] = "rsasig";
	$pconfig['type'] = 'tun';
	$pconfig['proto'] = 'udp';
	$pconfig['sport'] = '1194';
	$pconfig['ver'] = '2';
	$pconfig['crypto'] = 'BF-CBC';
	$pconfig['pull'] = true;
	$pconfig['enable'] = true;
}

if ($_POST) {

	/* Called from form */
	unset($input_errors);

	/* input validation */
	$reqdfields = explode(" ", "type saddr sport");
	$reqdfieldsn = explode(",", "Tunnel type,Address,Port");

	if ($_POST['authentication_method'] == "pre_shared_key") {
		$reqdfields  = array_merge($reqdfields, explode(" ", "lipaddr pre-shared-key"));
		$reqdfieldsn = array_merge($reqdfieldsn, explode(",", "Local IP address,Pre-shared secret"));

		if ($_POST['type'] == "tun") {
			/* tun */
			$reqdfields  = array_merge($reqdfields, explode(" ", "ripaddr"));
			$reqdfieldsn = array_merge($reqdfieldsn, explode(",", "Remote IP address"));

			/* subnet or ip address */
			if ($_POST['ripaddr']) {
				if (!is_ipaddr($_POST['ripaddr']))
					$input_errors[] = "A valid static remote IP address must be specified.";
				else if (ip2long($_POST['lipaddr']) == ip2long($_POST['ripaddr']))
					$input_errors[] = "Local IP address and remote IP address are the same.";
			}
			if ($_POST['lipaddr'])
				if (!is_ipaddr($_POST['lipaddr']))
					$input_errors[] = "A valid static local IP address must be specified.";

		} else {
			/* tap */
			if ($_POST['lipaddr']) {
				if (!is_ipaddr($_POST['lipaddr']))
					$input_errors[] = "A valid static local IP address must be specified.";
				else if (gen_subnet($_POST['lipaddr'], $_POST['netmask']) == $_POST['lipaddr']) 
					$input_errors[] = "Local IP address is subnet address.";
				else if (gen_subnet_max($_POST['lipaddr'], $_POST['netmask']) == $_POST['lipaddr']) 
					$input_errors[] = "Local IP address is broadcast address.";
			}
		}

		if (!empty($_POST['pre-shared-key']) &&
		   (!strstr($_POST['pre-shared-key'], "BEGIN OpenVPN Static key") ||
		    !strstr($_POST['pre-shared-key'], "END OpenVPN Static key")))
			$input_errors[] = "Pre-shared secret does not appear to be valid.";

	} else {
		/* rsa */
		$reqdfields  = array_merge($reqdfields, explode(" ", "ca_cert cli_cert cli_key"));
		$reqdfieldsn = array_merge($reqdfieldsn, explode(",", "CA certificate,Client certificate,Client key"));

		if (!empty($_POST['ca_cert']) &&
		   (!strstr($_POST['ca_cert'], "BEGIN CERTIFICATE") ||
		    !strstr($_POST['ca_cert'], "END CERTIFICATE")))
			$input_errors[] = "The CA certificate does not appear to be valid.";
		
		if (!empty($_POST['cli_cert']) &&
		   (!strstr($_POST['cli_cert'], "BEGIN CERTIFICATE") ||
		    !strstr($_POST['cli_cert'], "END CERTIFICATE")))
			$input_errors[] = "The client certificate does not appear to be valid.";
		
		if (!empty($_POST['cli_key']) &&
		   (!strstr($_POST['cli_key'], "BEGIN RSA PRIVATE KEY") ||
		    !strstr($_POST['cli_key'], "END RSA PRIVATE KEY")))
			$input_errors[] = "The client key does not appear to be valid.";

		if (!empty($_POST['pre-shared-key']) &&
		   (!strstr($_POST['pre-shared-key'], "BEGIN OpenVPN Static key") ||
		    !strstr($_POST['pre-shared-key'], "END OpenVPN Static key")))
			$input_errors[] = "Pre-shared secret does not appear to be valid.";

		if (isset($_POST['tlsauth']) && empty($_POST['pre-shared-key'])) {
			$reqdfields  = array_merge($reqdfields, explode(" ", "pre-shared-key"));
			$reqdfieldsn = array_merge($reqdfieldsn, explode(",", "Pre-shared secret"));
		}
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
 
	/* valid Port */
	if (($_POST['sport'] && !is_port($_POST['sport'])))
		$input_errors[] = "The server's port must be an integer between 1 and 65535.";

	/* valid FQDN or IP address */
	if (($_POST['saddr'] && !is_ipaddr($_POST['saddr']) && !is_domain($_POST['saddr'])))
		$input_errors[] = "The server name contains invalid characters.";

	if (isset($id) && $ovpncli[$id]) {
		/* Editing an existing entry */
		$ovpnent = $ovpncli[$id];

		if ($ovpncli[$id]['bridge'] != $_POST['bridge']) {
			/* double bridging? */
			if ($_POST['bridge'] &&
			    $_POST['type'] == "tap" &&
			    $_POST['authentication_method'] == "rsasig")
				$retval = check_bridging($_POST['bridge']);

			if (!empty($retval))
				$input_errors[] = $retval;
			else 
				ovpn_cli_dirty($ovpnent['if']);
		}

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
		if ($ovpnent['type'] != $_POST['type'])
			$input_errors[] = "Delete this interface first before changing the type of the tunnel to "
			                . strtoupper($_POST['type']) .".";

		/* Has the enable/disable state changed? */
		if (isset($ovpnent['enable']) && isset($_POST['disabled'])) {
			ovpn_cli_dirty($ovpnent['if']);
		}
		if (!isset($ovpnent['enable']) && !isset($_POST['disabled'])) {

			/* check if port number is free, else choose another one */
			if (in_array($ovpnent['cport'], used_port_list()))
				$ovpnent['cport'] = getnxt_port();

			ovpn_cli_dirty($ovpnent['if']);
		}
	} else {
		/* Creating a new entry */
		$ovpnent = array();
		if (!($ovpnent['if'] = getnxt_if($_POST['type'])))
			$input_errors[] = "Run out of devices for a tunnel of type {$_POST['type']}";

		$ovpnent['cport'] = getnxt_port();

		/* double bridging? */
		if ($_POST['bridge'] &&
		    $_POST['type'] == "tap" &&
		    $_POST['authentication_method'] == "rsasig") {
			$retval = check_bridging($_POST['bridge']);

			if (!empty($retval))
				$input_errors[] = $retval;
			else
				ovpn_cli_dirty($ovpnent['if']);
		}
	}

	if (!$input_errors) {

		$ovpnent['enable'] = isset($_POST['disabled']) ? false : true;
		$ovpnent['type'] = $_POST['type'];
		$ovpnent['authentication_method'] = $_POST['authentication_method'];
		$ovpnent['proto'] = $_POST['proto'];
		$ovpnent['sport'] = $_POST['sport'];
		$ovpnent['ver'] = $_POST['ver'];
		$ovpnent['saddr'] = $_POST['saddr'];
		$ovpnent['descr'] = $_POST['descr'];
		$ovpnent['ca_cert'] = $pconfig['ca_cert'];
		$ovpnent['cli_cert'] = $pconfig['cli_cert'];
		$ovpnent['cli_key'] = $pconfig['cli_key'];
		$ovpnent['crypto'] = $_POST['crypto'];
		$ovpnent['ns_cert_type'] = $_POST['ns_cert_type'] ? true : false;
		$ovpnent['pull'] = $_POST['pull'] ? true : false;
		$ovpnent['tlsauth'] = $_POST['tlsauth'] ? true : false;
		$ovpnent['bridge'] = $_POST['bridge'];
		$ovpnent['lipaddr'] = $_POST['lipaddr'];
		$ovpnent['ripaddr'] = $_POST['ripaddr'];
		$ovpnent['netmask'] = $_POST['netmask'];

		unset($ovpnent['pre-shared-key']);
		if ($_POST['pre-shared-key'])
			$ovpnent['pre-shared-key'] = base64_encode($_POST['pre-shared-key']);   

		$ovpnent['ca_cert'] = base64_encode($_POST['ca_cert']);
		$ovpnent['cli_cert'] = base64_encode($_POST['cli_cert']);
		$ovpnent['cli_key'] = base64_encode($_POST['cli_key']);

		/* expertmode params */
		$ovpnent['expertmode_enabled'] = $_POST['expertmode_enabled'] ? true : false;

		if (!is_array($options))
			$options = array();
		if (!is_array($ovpnent['expertmode']))
			$ovpnent['expertmode'] = array();

		$options['option'] = array_map('trim', explode("\n", trim($_POST['expertmode_options'])));
		$ovpnent['expertmode'] = $options;

		if (isset($id) && $ovpncli[$id]){
			$ovpncli[$id] = $ovpnent;
		}
		else{
			$ovpncli[] = $ovpnent;
		}
		
		write_config();
		ovpn_cli_dirty($ovpnent['if']);

		header("Location: vpn_openvpn_cli.php");
		exit;
	} else {
		$pconfig = $_POST;

		$pconfig['enable'] = "true";
		if (isset($_POST['disabled']))
			unset($pconfig['enable']);

		$pconfig['pre-shared-key'] = base64_encode($_POST['pre-shared-key']); 
		$pconfig['ca_cert'] = base64_encode($_POST['ca_cert']);
		$pconfig['cli_cert'] = base64_encode($_POST['cli_cert']);
		$pconfig['cli_key'] = base64_encode($_POST['cli_key']);
	}
}


$pgtitle = "VPN: OpenVPN: Edit client";
include("head.inc");

?>

<?php include("fbegin.inc"); ?>
<script language="JavaScript">
function enable_change(enable_over) {
	var endis;
	endis = !(!document.iform.disabled.checked || enable_over);

	document.iform.type[0].disabled = endis;
	document.iform.type[1].disabled = endis;
	document.iform.proto[0].disabled = endis;
	document.iform.proto[1].disabled = endis;
	document.iform.sport.disabled = endis;
	document.iform.saddr.disabled = endis;
	document.iform.ver[0].disabled = endis;
	document.iform.ver[1].disabled = endis;
	document.iform.descr.disabled = endis;
	document.iform.authentication_method.disabled = endis;
	document.iform.ca_cert.disabled = endis;
	document.iform.cli_cert.disabled = endis;
	document.iform.cli_key.disabled = endis;
	document.iform.crypto.disabled = endis;
	document.iform.ns_cert_type.disabled = endis;
	document.iform.pull.disabled = endis;
	document.iform.tlsauth.disabled = endis;
	document.iform.lipaddr.disabled = endis;
	document.iform.ripaddr.disabled = endis;
	document.iform.netmask.disabled = endis;
	document.iform.psk.disabled = endis;
	document.iform.expertmode_enabled.disabled = endis;
	document.iform.expertmode_options.disabled = endis;

	if (!document.iform.disabled.checked) {
		tls_change(enable_over);
		expertmode_change(enable_over);
		methodsel_change(enable_over);
	}
}

function expertmode_change(enable_over) {
	var endis;
	endis = !(document.iform.expertmode_enabled.checked || enable_over);

	document.iform.expertmode_options.disabled = endis;
}


function tls_change(enable_over) {
	var endis;
	endis = !(document.iform.tlsauth.checked || enable_over);

	document.iform.psk.disabled = endis;
}

function methodsel_change(enable_over) {
	var endis;

	switch (document.iform.authentication_method.selectedIndex) {
		case 1: /* rsa */
			if (get_radio_value(document.iform.type) == "tap") {
				/* tap */
				document.iform.bridge.disabled = 0;
			} else {
				/* tun */
				document.iform.bridge.disabled = 1;
				document.iform.bridge.selectedIndex = 0;
			}

			document.iform.psk.disabled = 1;
			document.iform.ca_cert.disabled = 0;
			document.iform.cli_cert.disabled = 0;
			document.iform.cli_key.disabled = 0;
			document.iform.ns_cert_type.disabled = 0;
			document.iform.tlsauth.disabled = 0;
			document.iform.lipaddr.disabled = 1;
			document.iform.ripaddr.disabled = 1;
			document.iform.netmask.disabled = 1;
			document.iform.pull.disabled = 0;
			tls_change();
			break;
		default: /* pre-shared */
			if (get_radio_value(document.iform.type) == "tap") {
				/* tap */
				document.iform.ripaddr.disabled = 1;
				document.iform.netmask.disabled = 0;
			} else {
				/* tun */
				document.iform.ripaddr.disabled = 0;
				document.iform.netmask.disabled = 1;
			}

			document.iform.lipaddr.disabled = 0;
			document.iform.psk.disabled = 0;
			document.iform.ca_cert.disabled = 1;
			document.iform.cli_cert.disabled = 1;
			document.iform.cli_key.disabled = 1;
			document.iform.ns_cert_type.disabled = 1;
			document.iform.tlsauth.disabled = 1;
			document.iform.bridge.disabled = 1;
			document.iform.bridge.selectedIndex = 0;
			document.iform.pull.disabled = 1;
			break;
	}

	if (enable_over) {
		document.iform.psk.disabled = 0;
		document.iform.ca_cert.disabled = 0;
		document.iform.cli_cert.disabled = 0;
		document.iform.cli_key.disabled = 0;
		document.iform.tlsauth.disabled = 0;
		document.iform.bridge.disabled = 0;
		document.iform.lipaddr.disabled = 0;
		document.iform.ripaddr.disabled = 0;
		document.iform.netmask.disabled = 0;
		document.iform.pull.disabled = 0;
	}
}

function get_radio_value(obj) {
	for (i = 0; i < obj.length; i++) {
		if (obj[i].checked)
		return obj[i].value;
	}
	return null;
}

//-->
</script>

<?php if ($input_errors) print_input_errors($input_errors); ?>

<form action="vpn_openvpn_cli_edit.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
  <table width="100%" border="0" cellpadding="6" cellspacing="0">
    <tr>
      <td width="22%" valign="top" class="vncellreq">Disabled</td>
      <td width="78%" class="vtable"> 
        <input name="disabled" type="checkbox" id="disabled" value="yes" onclick="enable_change(false)" <?php if (!isset($pconfig['enable'])) echo "checked"; ?>>
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
      <td width="22%" valign="top" class="vncellreq">Address</td>
      <td width="78%" class="vtable"> 
	<input name="saddr" type="text" class="formfld" size="20" maxlength="255" value="<?=htmlspecialchars($pconfig['saddr']);?>">
	<br>
	Enter the server's IP address or FQDN.</td>
    </tr>
    
    <tr>
      <td width="22%" valign="top" class="vncellreq">Port</td>
      <td width="78%" class="vtable">
        <input name="sport" type="text" class="formfld" size="5" maxlength="5" value="<?=htmlspecialchars($pconfig['sport']);?>"><br>
        Enter the server's port number (default is 1194).</td>
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
      <td colspan="2" valign="top" class="listtopic">Cryptographic options</td>
    </tr>
    <tr> 
      <td width="22%" valign="top" class="vncellreq">Authentication method</td>
      <td width="78%" class="vtable"> 
        <select name="authentication_method" class="formfld" onchange="methodsel_change(false)">
          <?php foreach ($p1_authentication_methods as $method => $methodname): ?>
            <option value="<?=$method;?>" <?php if ($method == $pconfig['authentication_method']) echo "selected"; ?>>
                <?=htmlspecialchars($methodname);?>
            </option>
          <?php endforeach; ?>
        </select> <br> <span class="vexpl">Must match the setting chosen on the remote side.</span></td>
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
        <td width="22%" valign="top" class="vncell">nsCertType</td>
        <td width="78%" class="vtable">
	  <input name="ns_cert_type" type="checkbox" value="yes" <?php if (isset($pconfig['ns_cert_type'])) echo "checked";?>>
	  <strong>nsCertType</strong><br>
	  Require  that  peer  certificate  was  signed  with  an explicit
	  nsCertType designation of "server".
	  This is a useful security option for clients, to ensure that the
	  host they connect with is a designated server.
      </tr>

      <tr>
        <td width="22%" valign="top" class="vncell">TLS auth</td>
        <td width="78%" class="vtable">
	  <input name="tlsauth" type="checkbox" value="yes" onclick="tls_change(false)" <?php if (isset($pconfig['tlsauth'])) echo "checked";?>>
	  <strong>TLS auth</strong><br>
          The tls-auth directive adds an additional HMAC signature to all SSL/TLS handshake packets for integrity verification.</td>
      </tr>

      <tr> 
	<td width="22%" valign="top" class="vncell">Pre-shared secret</td>
	<td width="78%" class="vtable">
	  <textarea name="pre-shared-key" id="psk" cols="65" rows="4" class="formpre"><?=htmlspecialchars(base64_decode($pconfig['pre-shared-key']));?></textarea>
	  <br>
	  Paste your own pre-shared secret here.</td>
      </tr>

    <tr> 
      <td colspan="2" class="list" height="12"></td>
    </tr>
    
    <tr> 
      <td colspan="2" valign="top" class="listtopic">Client configuration</td>
    </tr>
    
    <tr>
      <td valign="top" class="vncellreq">Tunnel type</td>
      <td class="vtable">
	<input name="type" type="radio" class="formfld" value="tun" onclick="methodsel_change(false)" <?php if ($pconfig['type'] == 'tun') echo "checked"; ?>> TUN&nbsp;
	<input name="type" type="radio" class="formfld" value="tap" onclick="methodsel_change(false)" <?php if ($pconfig['type'] == 'tap') echo "checked"; ?>> TAP</td>
    </tr> 
    
    <tr>
      <td width="22%" valign="top" class="vncellreq">Tunnel protocol</td>
      <td width="78%" class="vtable">
<input name="proto" type="radio" class="formfld" value="udp" <?php if ($pconfig['proto'] == 'udp') echo "checked"; ?>> UDP&nbsp;
<input name="proto" type="radio" class="formfld" value="tcp" <?php if ($pconfig['proto'] == 'tcp') echo "checked"; ?>> TCP<br>
       <span class="vexpl">Important: These settings must match the server's configuration.</span></td>
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
      <td width="22%" valign="top" class="vncell">Bridge with</td>
      <td width="78%" class="vtable">
	<select name="bridge" class="formfld" id="bridge" onchange="methodsel_change(false)">
	<option <?php if (!$pconfig['bridge']) echo "selected";?> value="">none</option>
	<?php $opts = array('lan' => "LAN", 'wan' => "WAN");
	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
		if ($i != $index && !($config['interfaces']['opt' . $i]['ovpn']))
			$opts['opt' . $i] = "Optional " . $i . " (" . $config['interfaces']['opt' . $i]['descr'] . ")";
	}
	foreach ($opts as $opt => $optname): ?>
		<option <?php if ($opt == $pconfig['bridge']) echo "selected";?> value="<?=htmlspecialchars($opt);?>"> 
		<?=htmlspecialchars($optname);?>
		</option>
	<?php endforeach; ?>
	</select> <br> <span class="vexpl">Only supported with authentication method set to RSA signature.</span>
      </td>
    </tr>

    <tr> 
      <td width="22%" valign="top" class="vncell">OpenVPN address assignment</td>
      <td width="78%" class="vtable"> 
        When using pre-shared keys, enter the IP address and subnet mask
        of the local and remote VPN endpoint here. For TAP devices, only the
	IP address of the local VPN endpoint is needed. The netmask is the subnet mask
        of the virtual ethernet segment which is being created or connected to.<br>
        <br>
        <table cellpadding="0" cellspacing="0">
          <tr>
            <td>Local IP address:&nbsp;&nbsp;</td>
            <td valign="top"><input name="lipaddr" id="lipaddr" type="text" class="formfld" size="20" value="<?=htmlspecialchars($pconfig['lipaddr']);?>">
                / 
                <select name="netmask" id="netmask" class="formfld">
                <?php for ($i = 30; $i > 19; $i--): ?>
                  <option value="<?=$i;?>" <?php if ($i == $pconfig['netmask']) echo "selected"; ?>>
                    <?=$i;?>
                  </option>
                <?php endfor; ?>
                </select>
            </td>
          </tr>
          <tr>
            <td>Remote IP address:&nbsp;&nbsp;</td>
            <td valign="top"><input name="ripaddr" id="ripaddr" type="text" class="formfld" size="20" value="<?=htmlspecialchars($pconfig['ripaddr']);?>">
            </td>
          </tr>
        </table>
      </td>
    </tr>
     
    <tr> 
      <td colspan="2" valign="top" height="12"></td>
    </tr>
    <tr>
      <td colspan="2" valign="top" class="listtopic">Client Options</td>
    </tr>
    <tr>

     <tr>
       <td width="22%" valign="top" class="vncell">Options</td>
       <td width="78%" class="vtable">
         <input type="checkbox" name="pull" value="yes" <?php if ($pconfig['pull']) echo "checked"; ?>> 
         <strong>Client-pull</strong></td>
     </tr>

     <tr>
      <td width="22%" valign="top" class="vncell">Expert mode</td>
      <td width="78%" class="vtable">
        <input name="expertmode_enabled" type="checkbox" value="yes" onclick="expertmode_change(false)" <?php if (isset($pconfig['expertmode_enabled'])) echo "checked"; ?>>
        <strong>Enable expert OpenVPN mode</strong><br>
        If this option is on, you can specify your own extra commands for the OpenVPN server.<br/>
        <textarea name="expertmode_options" id="expertmode_options" cols="65" rows="4" class="formpre"><?=htmlspecialchars($pconfig['expertmode_options']);?></textarea>
        <strong><span class="red">Note:</span></strong><br>
	Commands in expert mode aren't supported.
      </td>
    </tr>

    <tr> 
      <td width="22%" valign="top">&nbsp;</td>
      <td width="78%"> 
         <input name="Submit" type="submit" class="formbtn" value="Save" onclick="methodsel_change(true);tls_change(true);expertmode_change(true);enable_change(true)">
         <?php if (isset($id)): ?>
         <input name="id" type="hidden" value="<?=$id;?>"> 
         <?php endif; ?>
      </td>
    </tr>
  </table>
</form>
<script language="JavaScript">
<!--
tls_change(false);
methodsel_change(false);
expertmode_change(false);
enable_change(false);
//-->
</script>
<?php include("fend.inc"); ?>
