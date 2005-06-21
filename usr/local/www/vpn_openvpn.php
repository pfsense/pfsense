#!/usr/local/bin/php
<?php 
/*
	vpn_openvpn.php

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

$pgtitle = array("VPN", "OpenVPN");
require("guiconfig.inc");
require_once("openvpn.inc");

if (!is_array($config['ovpn']))
	$config['ovpn'] = array();
if (!is_array($config['ovpn']['server'])){
	$config['ovpn']['server'] =  array();
	$config['ovpn']['server']['tun_iface'] = "tun0";
	$config['ovpn']['server']['psh_options'] = array();
	/* Initialise with some sensible defaults */
	$config['ovpn']['server']['port'] = 5000;
	$config['ovpn']['server']['proto'] = 'UDP';
	$config['ovpn']['server']['maxcli'] = 25;
	$config['ovpn']['server']['crypto'] = 'BF-CBC';
	$config['ovpn']['server']['dupcn'] = true;
	$config['ovpn']['server']['verb'] = 1;
}

if ($_POST) {

	unset($input_errors);

	/* input validation */
	if ($_POST['enable']) {
		$reqdfields = explode(" ", "tun_iface bind_iface ipblock");
		$reqdfieldsn = explode(",", "Tunnel type,Interface binding,IP address block start");

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	}
	
	/* need a test here to make sure prefix and max_clients are coherent */
	
	/* Sort out the cert+key files */
	if (is_null($_POST['ca_cert']))
		$input_errors[] = "You must provide a CA certificate file";
	elseif (!strstr($_POST['ca_cert'], "BEGIN CERTIFICATE") || !strstr($_POST['ca_cert'], "END CERTIFICATE"))
		$input_errors[] = "The CA certificate does not appear to be valid.";
		
	if (is_null($_POST['srv_cert']))
		$input_errors[] = "You must provide a server certificate file";
	elseif (!strstr($_POST['srv_cert'], "BEGIN CERTIFICATE") || !strstr($_POST['srv_cert'], "END CERTIFICATE"))
		$input_errors[] = "The server certificate does not appear to be valid.";
		
	if (is_null($_POST['srv_key']))
		$input_errors[] = "You must provide a server key file";
	elseif (!strstr($_POST['srv_key'], "BEGIN RSA PRIVATE KEY") || !strstr($_POST['srv_key'], "END RSA PRIVATE KEY"))
		$input_errors[] = "The server key does not appear to be valid.";
		
	if (is_null($_POST['dh_param']))
		$input_errors[] = "You must provide a DH parameters file";
	elseif (!strstr($_POST['dh_param'], "BEGIN DH PARAMETERS") || !strstr($_POST['dh_param'], "END DH PARAMETERS"))
		$input_errors[] = "The DH parameters do not appear to be valid.";
				
	if (!$input_errors) {
		$server =& $config['ovpn']['server'];
		$server['enable'] = $_POST['enable'] ? true : false;
		
		/* Make sure that the tunnel interface type has not changed */
		if ($server['tun_iface'] != $_POST['tun_iface']){ 
			$server['tun_iface'] = $_POST['tun_iface'];
			touch($d_sysrebootreqd_path);
		}
		
		$server['bind_iface'] = $_POST['bind_iface'];
		$server['port'] = $_POST['port'];
		$server['proto'] = $_POST['proto'];
		
		/* Make sure the IP address and/or prefix have not changed */
		if ($server['ipblock'] != $_POST['ipblock']){
			$server['ipblock'] = $_POST['ipblock'];
			touch($d_sysrebootreqd_path);
		}
		if ($server['prefix'] != $_POST['prefix']){
			$server['prefix'] = $_POST['prefix'];
			touch($d_sysrebootreqd_path);
		}
		
		$server['maxcli'] = $_POST['maxcli'];
		$server['crypto'] = $_POST['crypto'];
		$server['cli2cli'] = $_POST['cli2cli'] ? true : false;
		$server['dupcn'] = $_POST['dupcn'] ? true : false;
		$server['psh_options']['redir'] = $_POST['psh_redir'] ? true : false;
		$server['psh_options']['redir_loc'] = $_POST['psh_redir_loc'] ? true : false;
		if ($_POST['psh_rtedelay'])
			$server['psh_options']['rtedelay'] = $_POST['psh_rtedelay_int'];
		if ($_POST['psh_ping'])
			$server['psh_options']['ping'] = $_POST['psh_ping_int'];
		if ($_POST['psh_pingexit'])
			$server['psh_options']['pingexit'] = $_POST['psh_pingexit_int'];
		if ($_POST['psh_pingrst'])
			$server['psh_options']['pingrst'] = $_POST['psh_pingrst_int'];
		if ($_POST['inact'])
			$server['psh_options']['inact'] = $_POST['psh_inact_int'];
		$server['ca_cert'] = base64_encode($_POST['ca_cert']);
		$server['srv_cert'] = base64_encode($_POST['srv_cert']);
		$server['srv_key'] = base64_encode($_POST['srv_key']);
		$server['dh_param'] = base64_encode($_POST['dh_param']);	
			
		write_config();

		$retval = 0;
		if (file_exists($d_sysrebootreqd_path)) {
			/* Rewrite interface definitions */
			$retval = ovpn_server_iface();
		}
		else{
			ovpn_lock();
			$retval = ovpn_config_server();
			ovpn_unlock();
		}
		$savemsg = get_std_save_message($retval);
	}
}

/* Simply take a copy of the array */
$pconfig = $config['ovpn']['server'];

?>
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if (file_exists($d_sysrebootreqd_path)) print_info_box(get_std_save_message(0)); ?>

<form action="vpn_openvpn.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
  <ul id="tabnav">	        
	<li class="tabact">Server</li>
	<li class="tabinact"><a href="vpn_openvpn_cli.php">Client</a></li>
  </ul>
  </td></tr>
  <tr>
  <td class="tabcont">
    <strong><span class="red">WARNING: This feature is experimental and modifies your optional interface configuration.
  Backup your configuration before using OpenVPN, and restore it before upgrading.<br>
&nbsp;  <br>
    </span></strong><table width="100%" border="0" cellpadding="6" cellspacing="0">
  <tr>
    <td width="22%" valign="top" class="vtable">&nbsp;</td>
    <td width="78%" class="vtable">
      <input name="enable" type="checkbox" value="yes" <?php if (isset($pconfig['enable'])) echo "checked"; ?>>
      <strong>Enable OpenVPN server </strong></td>
   </tr>
   
   <tr>
     <td width="22%" valign="top" class="vncellreq">Tunnel type</td>
     <td width="78%" class="vtable">
       <input type="radio" name="tun_iface" class="formfld" value="tun0" <?php if ($pconfig['tun_iface'] == 'tun0') echo "checked"; ?>>
          TUN&nbsp;
       <input type="radio" name="tun_iface" class="formfld" value="tap0" <?php if ($pconfig['tun_iface'] == 'tap0') echo "checked"; ?>>
          TAP
      </td>
    </tr>
    
    <tr>
      <td width="22%" valign="top" class="vncell">OpenVPN protocol/port</td>
      <td width="78%" class="vtable">
	<input type="radio" name="proto" class="formfld" value="UDP" <?php if ($pconfig['proto'] == 'UDP') echo "checked"; ?>>
           UDP&nbsp;
        <input type="radio" name="proto" class="formfld" value="TCP" <?php if ($pconfig['proto'] == 'TCP') echo "checked"; ?>>
           TCP<br><br>
        Port: 
        <input name="port" type="text" class="formfld" size="5" maxlength="5" value="<?= $pconfig['port']; ?>"><br>
        Enter the port number to use for the server (default is 5000).</td>
    </tr>
    
    <tr>
      <td width="22%" valign="top" class="vncellreq">Interface binding</td>
      <td width="78%" class="vtable">
	<select name="bind_iface" class="formfld">
        <?php 
	$interfaces = ovpn_real_interface_list();
	foreach ($interfaces as $key => $iface):
        ?>
	<option value="<?=$key;?>" <?php if ($key == $pconfig['bind_iface']) echo "selected"; ?>> <?= $iface;?>
        </option>
        <?php endforeach;?>
        </select>
        <span class="vexpl"><br>
        Choose an interface for the OpenVPN server to listen on.</span></td>
    </tr>
		
    <tr> 
      <td width="22%" valign="top" class="vncellreq">IP address block</td>
      <td width="78%" class="vtable"> 
        <input name="ipblock" type="text" class="formfld" size="20" value="<?=htmlspecialchars($pconfig['ipblock']);?>">
        / 
        <select name="prefix" class="formfld">
          <?php for ($i = 29; $i > 19; $i--): ?>
          <option value="<?=$i;?>" <?php if ($i == $pconfig['prefix']) echo "selected"; ?>>
            <?=$i;?>
          </option>
          <?php endfor; ?>
        </select>
        <br>
        Enter the IP address block for the OpenVPN server and clients to use.<br>
        <br>
	Maximum number of simultaneous clients: 
	<input name="maxcli" type="text" class="formfld" size="3" maxlength="3" value="<?=htmlspecialchars($pconfig['maxcli']);?>">
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
      <td width="22%" valign="top" class="vncellreq">Server certificate</td>
      <td width="78%" class="vtable">
        <textarea name="srv_cert" cols="65" rows="4" class="formpre"><?=htmlspecialchars(base64_decode($pconfig['srv_cert']));?></textarea>
        <br>
        Paste a server certificate in X.509 PEM format here.</td>
     </tr>
     
     <tr> 
       <td width="22%" valign="top" class="vncellreq">Server key</td>
       <td width="78%" class="vtable"> 
         <textarea name="srv_key" cols="65" rows="4" class="formpre"><?=htmlspecialchars(base64_decode($pconfig['srv_key']));?></textarea>
         <br>Paste the server RSA private key here.</td>
      </tr>
      
      <tr> 
        <td width="22%" valign="top" class="vncellreq">DH parameters</td>
        <td width="78%" class="vtable"> 
	  <textarea name="dh_param" cols="65" rows="4" class="formpre"><?=htmlspecialchars(base64_decode($pconfig['dh_param']));?></textarea>
          <br>          
          Paste the Diffie-Hellman parameters in PEM format here.</td>
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
	  Select a data channel encryption cipher.</td>
      </tr>
      
      <tr>
        <td width="22%" valign="top" class="vncell">Internal routing mode</td>
        <td width="78%" class="vtable">
	  <input name="cli2cli" type="checkbox" value="yes" <?php if (isset($pconfig['cli2cli'])) echo "checked"; ?>>
          <strong>Enable client-to-client routing</strong><br>
          If this option is on,  clients are allowed to talk to each other.</td>
      </tr>
      
      <tr>
        <td width="22%" valign="top" class="vncell">Client authentication</td>
        <td width="78%" class="vtable">
	  <input name="dupcn" type="checkbox" value="yes" <?php if (isset($pconfig['dupcn'])) echo "checked"; ?>>
          <strong>Permit duplicate client certificates</strong><br>
	  If this option is on, clients with duplicate certificates will not be disconnected.</td>
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
              <td><input type="text" name="psh_rtedelay_int" class="formfld" size="4" value="<?= $pconfig['psh_options']['rtedelay']?>"> seconds</td>
            </tr>
            <tr>
              <td><input type="checkbox" name="psh_inact" value="yes" <?php if (isset($pconfig['psh_options']['inact'])) echo "checked"; ?>>
    Inactive</td>
              <td>&nbsp;</td>
              <td><input type="text" name="psh_inact_int" class="formfld" size="4" value="<?= $pconfig['psh_options']['inact']?>">
    seconds</td>
            </tr>
            <tr>
              <td><input type="checkbox" name="psh_ping" value="yes" <?php if (isset($pconfig['psh_options']['ping'])) echo "checked"; ?>> Ping</td>
              <td>&nbsp;</td>
              <td>Interval: <input type="text" name="psh_ping_int" class="formfld" size="4" value="<?= $pconfig['psh_options']['ping']?>"> seconds</td>
            </tr>
            <tr>
              <td><input type="checkbox" name="psh_pingexit" value="yes" <?php if (isset($pconfig['psh_options']['pingexit'])) echo "checked"; ?>> Ping-exit</td>
              <td>&nbsp;</td>
              <td>Interval: <input type="text" name="psh_pingexit_int" class="formfld" size="4" value="<?= $pconfig['psh_options']['pingexit']?>"> seconds</td>
            </tr>
            <tr>
              <td><input type="checkbox" name="psh_pingrst" value="yes" <?php if (isset($pconfig['psh_options']['pingrst'])) echo "checked"; ?>> Ping-restart</td>
              <td>&nbsp;</td>
              <td>Interval: <input type="text" name="psh_pingrst_int" class="formfld" size="4" value="<?= $pconfig['psh_options']['pingrst']?>"> seconds</td>
            </tr>
          </table></td>
      </tr>
      <tr>
        <td width="22%" valign="top">&nbsp;</td>
        <td width="78%">
          <input name="Submit" type="submit" class="formbtn" value="Save">
        </td>
      </tr>
      <tr>
        <td width="22%" valign="top">&nbsp;</td>
        <td width="78%"><span class="vexpl"><span class="red"><strong>Note:<br>
          </strong></span>Changing any settings on this page will disconnect all clients!</span>
	</td>
      </tr>
    </table>  </td>
</tr>
</table>
</form>
<?php include("fend.inc"); ?>
