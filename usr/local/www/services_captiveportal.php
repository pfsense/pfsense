#!/usr/local/bin/php
<?php 
/*
	services_captiveportal.php
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

if (!is_array($config['captiveportal'])) {
	$config['captiveportal'] = array();
	$config['captiveportal']['page'] = array();
	$config['captiveportal']['timeout'] = 60;
}

if ($_GET['act'] == "viewhtml") {
	echo base64_decode($config['captiveportal']['page']['htmltext']);
	exit;
} else if ($_GET['act'] == "viewerrhtml") {
	echo base64_decode($config['captiveportal']['page']['errtext']);
	exit;
}

$pconfig['cinterface'] = $config['captiveportal']['interface'];
$pconfig['timeout'] = $config['captiveportal']['timeout'];
$pconfig['idletimeout'] = $config['captiveportal']['idletimeout'];
$pconfig['enable'] = isset($config['captiveportal']['enable']);
$pconfig['radacct_enable'] = isset($config['captiveportal']['radacct_enable']);
$pconfig['httpslogin_enable'] = isset($config['captiveportal']['httpslogin']);
$pconfig['httpsname'] = $config['captiveportal']['httpsname'];
$pconfig['cert'] = base64_decode($config['captiveportal']['certificate']);
$pconfig['key'] = base64_decode($config['captiveportal']['private-key']);
$pconfig['logoutwin_enable'] = isset($config['captiveportal']['logoutwin_enable']);
$pconfig['nomacfilter'] = isset($config['captiveportal']['nomacfilter']);
$pconfig['redirurl'] = $config['captiveportal']['redirurl'];
$pconfig['radiusip'] = $config['captiveportal']['radiusip'];
$pconfig['radiusport'] = $config['captiveportal']['radiusport'];
$pconfig['radiusacctport'] = $config['captiveportal']['radiusacctport'];
$pconfig['radiuskey'] = $config['captiveportal']['radiuskey'];

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['enable']) {
		$reqdfields = explode(" ", "cinterface");
		$reqdfieldsn = explode(",", "Interface");
		
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
		
		/* make sure no interfaces are bridged */
		for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
			$coptif = &$config['interfaces']['opt' . $i];
			if (isset($coptif['enable']) && $coptif['bridge']) {
				$input_errors[] = "The captive portal cannot be used when one or more interfaces are bridged.";
				break;
			}
		}
		
		if ($_POST['httpslogin_enable']) {
		 	if (!$_POST['cert'] || !$_POST['key']) {
				$input_errors[] = "Certificate and key must be specified for HTTPS login.";
			} else {
				if (!strstr($_POST['cert'], "BEGIN CERTIFICATE") || !strstr($_POST['cert'], "END CERTIFICATE"))
					$input_errors[] = "This certificate does not appear to be valid.";
				if (!strstr($_POST['key'], "BEGIN RSA PRIVATE KEY") || !strstr($_POST['key'], "END RSA PRIVATE KEY"))
					$input_errors[] = "This key does not appear to be valid.";
			}
			
			if (!$_POST['httpsname'] || !is_domain($_POST['httpsname'])) {
				$input_errors[] = "The HTTPS server name must be specified for HTTPS login.";
			}
		}
	}
	
	if ($_POST['timeout'] && (!is_numeric($_POST['timeout']) || ($_POST['timeout'] < 1))) {
		$input_errors[] = "The timeout must be at least 1 minute.";
	}
	if ($_POST['idletimeout'] && (!is_numeric($_POST['idletimeout']) || ($_POST['idletimeout'] < 1))) {
		$input_errors[] = "The idle timeout must be at least 1 minute.";
	}
	if (($_POST['radiusip'] && !is_ipaddr($_POST['radiusip']))) {
		$input_errors[] = "A valid IP address must be specified. [".$_POST['radiusip']."]";
	}
	if (($_POST['radiusport'] && !is_port($_POST['radiusport']))) {
		$input_errors[] = "A valid port number must be specified. [".$_POST['radiusport']."]";
	}
	if (($_POST['radiusacctport'] && !is_port($_POST['radiusacctport']))) {
		$input_errors[] = "A valid port number must be specified. [".$_POST['radiusport']."]";
	}

	if (!$input_errors) {
		$config['captiveportal']['interface'] = $_POST['cinterface'];
		$config['captiveportal']['timeout'] = $_POST['timeout'];
		$config['captiveportal']['idletimeout'] = $_POST['idletimeout'];
		$config['captiveportal']['enable'] = $_POST['enable'] ? true : false;
		$config['captiveportal']['radacct_enable'] = $_POST['radacct_enable'] ? true : false;
		$config['captiveportal']['httpslogin'] = $_POST['httpslogin_enable'] ? true : false;
		$config['captiveportal']['httpsname'] = $_POST['httpsname'];
		$config['captiveportal']['certificate'] = base64_encode($_POST['cert']);
		$config['captiveportal']['private-key'] = base64_encode($_POST['key']);
		$config['captiveportal']['logoutwin_enable'] = $_POST['logoutwin_enable'] ? true : false;
		$config['captiveportal']['nomacfilter'] = $_POST['nomacfilter'] ? true : false;
		$config['captiveportal']['redirurl'] = $_POST['redirurl'];
		$config['captiveportal']['radiusip'] = $_POST['radiusip'];
		$config['captiveportal']['radiusport'] = $_POST['radiusport'];
		$config['captiveportal']['radiusacctport'] = $_POST['radiusacctport'];
		$config['captiveportal']['radiuskey'] = $_POST['radiuskey'];
		
		/* file upload? */
		if (is_uploaded_file($_FILES['htmlfile']['tmp_name']))
			$config['captiveportal']['page']['htmltext'] = base64_encode(file_get_contents($_FILES['htmlfile']['tmp_name']));
		if (is_uploaded_file($_FILES['errfile']['tmp_name']))
			$config['captiveportal']['page']['errtext'] = base64_encode(file_get_contents($_FILES['errfile']['tmp_name']));
			
		write_config();
		
		$retval = 0;
		if (!file_exists($d_sysrebootreqd_path)) {
			config_lock();
			$retval = captiveportal_configure();
			config_unlock();
		}
		$savemsg = get_std_save_message($retval);
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Services: Captive portal");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
<script language="JavaScript">
<!--
function radacct_change() {
	if (document.iform.radacct_enable.checked) {
		document.iform.logoutwin_enable.checked = 1;
	} 
}

function enable_change(enable_change) {
	var endis;
	endis = !(document.iform.enable.checked || enable_change);
	
	document.iform.cinterface.disabled = endis;
	document.iform.idletimeout.disabled = endis;
	document.iform.timeout.disabled = endis;
	document.iform.redirurl.disabled = endis;
	document.iform.radiusip.disabled = endis;
	document.iform.radiusport.disabled = endis;
	document.iform.radiuskey.disabled = endis;
	document.iform.radacct_enable.disabled = endis;
	document.iform.httpslogin_enable.disabled = endis;
	document.iform.httpsname.disabled = endis;
	document.iform.cert.disabled = endis;
	document.iform.key.disabled = endis;
	document.iform.logoutwin_enable.disabled = endis;
	document.iform.nomacfilter.disabled = endis;
	document.iform.htmlfile.disabled = endis;
	document.iform.errfile.disabled = endis;
	
	if (enable_change && document.iform.radacct_enable.checked) {
		document.iform.logoutwin_enable.checked = 1;
	}
}
//-->
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Services: Captive portal</p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<form action="services_captiveportal.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
  <ul id="tabnav">
	<li class="tabact">Captive portal</li>
	<li class="tabinact"><a href="services_captiveportal_mac.php">Pass-through MAC</a></li>
	<li class="tabinact"><a href="services_captiveportal_ip.php">Allowed IP addresses</a></li>
  </ul>
  </td></tr>
  <tr>
  <td class="tabcont">
  <table width="100%" border="0" cellpadding="6" cellspacing="0">
	<tr> 
	  <td width="22%" valign="top" class="vtable">&nbsp;</td>
	  <td width="78%" class="vtable">
		<input name="enable" type="checkbox" value="yes" <?php if ($pconfig['enable']) echo "checked"; ?> onClick="enable_change(false)">
		<strong>Enable captive portal </strong></td>
	</tr>
	<tr> 
	  <td width="22%" valign="top" class="vncellreq">Interface</td>
	  <td width="78%" class="vtable">
		<select name="cinterface" class="formfld" id="cinterface">
		  <?php $interfaces = array('lan' => 'LAN');
		  for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
			if (isset($config['interfaces']['opt' . $i]['enable']))
				$interfaces['opt' . $i] = $config['interfaces']['opt' . $i]['descr'];
		  }
		  foreach ($interfaces as $iface => $ifacename): ?>
		  <option value="<?=$iface;?>" <?php if ($iface == $pconfig['cinterface']) echo "selected"; ?>> 
		  <?=htmlspecialchars($ifacename);?>
		  </option>
		  <?php endforeach; ?>
		</select> <br>
		<span class="vexpl">Choose which interface to run the captive portal on.</span></td>
	</tr>
	<tr>
	  <td valign="top" class="vncell">Idle timeout</td>
	  <td class="vtable">
		<input name="idletimeout" type="text" class="formfld" id="idletimeout" size="6" value="<?=htmlspecialchars($pconfig['idletimeout']);?>">
minutes<br>
Clients will be disconnected after this amount of inactivity. They may log in again immediately, though. Leave this field blank for no idle timeout.</td>
	</tr>
	<tr> 
	  <td width="22%" valign="top" class="vncell">Hard timeout</td>
	  <td width="78%" class="vtable"> 
		<input name="timeout" type="text" class="formfld" id="timeout" size="6" value="<?=htmlspecialchars($pconfig['timeout']);?>"> 
		minutes<br>
	  Clients will be disconnected after this amount of time, regardless of activity. They may log in again immediately, though. Leave this field blank for no hard timeout (not recommended unless an idle timeout is set).</td>
	</tr>
	<tr> 
	  <td width="22%" valign="top" class="vncell">Logout popup window</td>
	  <td width="78%" class="vtable"> 
		<input name="logoutwin_enable" type="checkbox" class="formfld" id="logoutwin_enable" value="yes" <?php if($pconfig['logoutwin_enable']) echo "checked"; ?>>
		<strong>Enable logout popup window</strong><br>
	  If enabled, a popup window will appear when clients are allowed through the captive portal. This allows clients to explicitly disconnect themselves before the idle or hard timeout occurs. When RADIUS accounting is  enabled, this option is implied.</td>
	</tr>
	<tr>
	  <td valign="top" class="vncell">Redirection URL</td>
	  <td class="vtable">
		<input name="redirurl" type="text" class="formfld" id="redirurl" size="60" value="<?=htmlspecialchars($pconfig['redirurl']);?>">
		<br>
If you provide a URL here, clients will be redirected to that URL instead of the one they initially tried
to access after they've authenticated.</td>
	</tr>
	<tr>
      <td valign="top" class="vncell">MAC filtering </td>
      <td class="vtable">
        <input name="nomacfilter" type="checkbox" class="formfld" id="nomacfilter" value="yes" <?php if ($pconfig['nomacfilter']) echo "checked"; ?>>
        <strong>Disable MAC filtering</strong><br>
    If this option is set, no attempts will be made to ensure that the MAC address of clients stays the same while they're logged in. This is required when the MAC address of cannot be determined (usually because there are routers between m0n0wall and the clients).</td>
	  </tr>
	<tr> 
	  <td width="22%" valign="top" class="vncell">RADIUS server</td>
	  <td width="78%" class="vtable"> 
		<table cellpadding="0" cellspacing="0">
		<tr>
		<td>IP address:</td>
		<td><input name="radiusip" type="text" class="formfld" id="radiusip" size="20" value="<?=htmlspecialchars($pconfig['radiusip']);?>"></td>
		</tr><tr>
		<td>Port:</td>
		<td><input name="radiusport" type="text" class="formfld" id="radiusport" size="5" value="<?=htmlspecialchars($pconfig['radiusport']);?>"></td>
		</tr><tr>
		<td>Shared secret:&nbsp;&nbsp;</td>
		<td><input name="radiuskey" type="text" class="formfld" id="radiuskey" size="16" value="<?=htmlspecialchars($pconfig['radiuskey']);?>"> </td>
 		</tr>
		<tr>
          <td>Accounting:&nbsp;&nbsp;</td>
          <td><input name="radacct_enable" type="checkbox" id="radacct_enable" value="yes" <?php if($pconfig['radacct_enable']) echo "checked"; ?> onClick="radacct_change()"></td>
		  </tr>
		<tr>
          <td>Accounting port:&nbsp;&nbsp;</td>
          <td><input name="radiusacctport" type="text" class="formfld" id="radiusacctport" size="5" value="<?=htmlspecialchars($pconfig['radiusacctport']);?>"></td>
		  </tr></table>
 		<br>
 	Enter the IP address and port of the RADIUS server which users of the captive portal have to authenticate against. Leave blank to disable RADIUS authentication. Leave port number blank to use the default port (1812). Leave the RADIUS shared secret blank to not use a RADIUS shared secret. RADIUS accounting packets will also be sent to the RADIUS server if  accounting is enabled (default port is 1813).
	</tr>
	<tr>
      <td valign="top" class="vncell">HTTPS login</td>
      <td class="vtable">
        <input name="httpslogin_enable" type="checkbox" class="formfld" id="httpslogin_enable" value="yes" <?php if($pconfig['httpslogin_enable']) echo "checked"; ?>>
        <strong>Enable HTTPS login</strong><br>
    If enabled, the username and password will be transmitted over an HTTPS connection to protect against eavesdroppers. This option only applies when RADIUS authentication is used. A server name, certificate and matching private key must also be specified below.</td>
	  </tr>
	<tr>
      <td valign="top" class="vncell">HTTPS server name </td>
      <td class="vtable">
        <input name="httpsname" type="text" class="formfld" id="httpsname" size="30" value="<?=htmlspecialchars($pconfig['httpsname']);?>"><br>
    This name will be used in the form action for the HTTPS POST and should match the Common Name (CN) in your certificate (otherwise, the client browser will most likely display a security warning). Make sure captive portal clients can resolve this name in DNS. </td>
	  </tr>
	<tr>
      <td valign="top" class="vncell">HTTPS certificate</td>
      <td class="vtable">
        <textarea name="cert" cols="65" rows="7" id="cert" class="formpre"><?=htmlspecialchars($pconfig['cert']);?></textarea>
        <br>
    Paste a signed certificate in X.509 PEM format here.</td>
	  </tr>
	<tr>
      <td valign="top" class="vncell">HTTPS private key</td>
      <td class="vtable">
        <textarea name="key" cols="65" rows="7" id="key" class="formpre"><?=htmlspecialchars($pconfig['key']);?></textarea>
        <br>
    Paste an RSA private key in PEM format here.</td>
	  </tr>
	<tr> 
	  <td width="22%" valign="top" class="vncellreq">Portal page contents</td>
	  <td width="78%" class="vtable">    
		<input type="file" name="htmlfile" class="formfld" id="htmlfile"><br>
		<?php if ($config['captiveportal']['page']['htmltext']): ?>
		<a href="?act=viewhtml" target="_blank">View current page</a>                      
		  <br>
		  <br>
		<?php endif; ?>
		  Upload an HTML file for the portal page here (leave blank to keep the current one). Make sure to include a form (POST to &quot;$PORTAL_ACTION$&quot;)
with a submit button (name=&quot;accept&quot;) and a hidden field with name=&quot;redirurl&quot; and value=&quot;$PORTAL_REDIRURL$&quot;. Include the &quot;auth_user&quot; and &quot;auth_pass&quot; input elements if RADIUS authentication is enabled. If RADIUS is enabled and no &quot;auth_user&quot; is present, authentication will always fail. If RADIUS is not enabled, you can omit both of these input elements. Example code for the form:<br>
		  <br>
		  <tt>&lt;form method=&quot;post&quot; action=&quot;$PORTAL_ACTION$&quot;&gt;<br>
		  &nbsp;&nbsp;&nbsp;&lt;input name=&quot;auth_user&quot; type=&quot;text&quot;&gt;<br>
		  &nbsp;&nbsp;&nbsp;&lt;input name=&quot;auth_pass&quot; type=&quot;password&quot;&gt;<br>
		  &nbsp;&nbsp;&nbsp;&lt;input name=&quot;redirurl&quot; type=&quot;hidden&quot; value=&quot;$PORTAL_REDIRURL$&quot;&gt;<br>
&nbsp;&nbsp;&nbsp;&lt;input name=&quot;accept&quot; type=&quot;submit&quot; value=&quot;Continue&quot;&gt;<br>
		  &lt;/form&gt;</tt></td>
	</tr>
	<tr>
	  <td width="22%" valign="top" class="vncell">Authentication<br>
		error page<br>
		contents</td>
	  <td class="vtable">
		<input name="errfile" type="file" class="formfld" id="errfile"><br>
		<?php if ($config['captiveportal']['page']['errtext']): ?>
		<a href="?act=viewerrhtml" target="_blank">View current page</a>                      
		  <br>
		  <br>
		<?php endif; ?>
The contents of the HTML file that you upload here are displayed when a RADIUS authentication error occurs.</td>
	</tr>
	<tr> 
	  <td width="22%" valign="top">&nbsp;</td>
	  <td width="78%"> 
		<input name="Submit" type="submit" class="formbtn" value="Save" onClick="enable_change(true)"> 
	  </td>
	</tr>
	<tr> 
	  <td width="22%" valign="top">&nbsp;</td>
	  <td width="78%"><span class="vexpl"><span class="red"><strong>Note:<br>
		</strong></span>Changing any settings on this page will disconnect all clients! Don't forget to enable the DHCP server on your captive portal interface! Make sure that the default/maximum DHCP lease time is higher than the timeout entered on this page. Also, the DNS forwarder needs to be enabled for DNS lookups by unauthenticated clients to work. </span></td>
	</tr>
  </table>
  </td>
  </tr>
  </table>
</form>
<script language="JavaScript">
<!--
enable_change(false);
//-->
</script>
<?php include("fend.inc"); ?>
</body>
</html>
