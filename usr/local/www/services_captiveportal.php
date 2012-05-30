<?php
/*
	services_captiveportal.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2003-2006 Manuel Kasper <mk@neon1.net>.
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
/*
	pfSense_MODULE:	captiveportal
*/

##|+PRIV
##|*IDENT=page-services-captiveportal
##|*NAME=Services: Captive portal page
##|*DESCR=Allow access to the 'Services: Captive portal' page.
##|*MATCH=services_captiveportal.php*
##|-PRIV

$statusurl = "status_captiveportal.php";
$logurl = "diag_logs_auth.php";

require("guiconfig.inc");
require("functions.inc");
require("filter.inc");
require("shaper.inc");
require("captiveportal.inc");

$pgtitle = array(gettext("Services"),gettext("Captive portal"));

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
} else if ($_GET['act'] == "viewlogouthtml") {
	echo base64_decode($config['captiveportal']['page']['logouttext']);
	exit;
}

$pconfig['cinterface'] = $config['captiveportal']['interface'];
$pconfig['maxprocperip'] = $config['captiveportal']['maxprocperip'];
$pconfig['timeout'] = $config['captiveportal']['timeout'];
$pconfig['idletimeout'] = $config['captiveportal']['idletimeout'];
$pconfig['freelogins_count'] = $config['captiveportal']['freelogins_count'];
$pconfig['freelogins_resettimeout'] = $config['captiveportal']['freelogins_resettimeout'];
$pconfig['freelogins_updatetimeouts'] = isset($config['captiveportal']['freelogins_updatetimeouts']);
$pconfig['enable'] = isset($config['captiveportal']['enable']);
$pconfig['auth_method'] = $config['captiveportal']['auth_method'];
$pconfig['radacct_enable'] = isset($config['captiveportal']['radacct_enable']);
$pconfig['radmac_enable'] = isset($config['captiveportal']['radmac_enable']);
$pconfig['radmac_secret'] = $config['captiveportal']['radmac_secret'];
$pconfig['reauthenticate'] = isset($config['captiveportal']['reauthenticate']);
$pconfig['reauthenticateacct'] = $config['captiveportal']['reauthenticateacct'];
$pconfig['httpslogin_enable'] = isset($config['captiveportal']['httpslogin']);
$pconfig['httpsname'] = $config['captiveportal']['httpsname'];
$pconfig['preauthurl'] = strtolower($config['captiveportal']['preauthurl']);
$pconfig['cert'] = base64_decode($config['captiveportal']['certificate']);
$pconfig['cacert'] = base64_decode($config['captiveportal']['cacertificate']);
$pconfig['key'] = base64_decode($config['captiveportal']['private-key']);
$pconfig['logoutwin_enable'] = isset($config['captiveportal']['logoutwin_enable']);
$pconfig['peruserbw'] = isset($config['captiveportal']['peruserbw']);
$pconfig['bwdefaultdn'] = $config['captiveportal']['bwdefaultdn'];
$pconfig['bwdefaultup'] = $config['captiveportal']['bwdefaultup'];
$pconfig['nomacfilter'] = isset($config['captiveportal']['nomacfilter']);
$pconfig['noconcurrentlogins'] = isset($config['captiveportal']['noconcurrentlogins']);
$pconfig['redirurl'] = $config['captiveportal']['redirurl'];
$pconfig['radiusip'] = $config['captiveportal']['radiusip'];
$pconfig['radiusip2'] = $config['captiveportal']['radiusip2'];
$pconfig['radiusport'] = $config['captiveportal']['radiusport'];
$pconfig['radiusport2'] = $config['captiveportal']['radiusport2'];
$pconfig['radiusacctport'] = $config['captiveportal']['radiusacctport'];
$pconfig['radiuskey'] = $config['captiveportal']['radiuskey'];
$pconfig['radiuskey2'] = $config['captiveportal']['radiuskey2'];
$pconfig['radiusvendor'] = $config['captiveportal']['radiusvendor'];
$pconfig['radiussession_timeout'] = isset($config['captiveportal']['radiussession_timeout']);
$pconfig['radiussrcip_attribute'] = $config['captiveportal']['radiussrcip_attribute'];
$pconfig['passthrumacadd'] = isset($config['captiveportal']['passthrumacadd']);
$pconfig['passthrumacaddusername'] = isset($config['captiveportal']['passthrumacaddusername']);
$pconfig['radmac_format'] = $config['captiveportal']['radmac_format'];

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['enable']) {
		$reqdfields = explode(" ", "cinterface");
		$reqdfieldsn = array(gettext("Interface"));

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

		/* make sure no interfaces are bridged */
		if (is_array($_POST['cinterface']))
			foreach ($pconfig['cinterface'] as $cpbrif)
				if (link_interface_to_bridge($cpbrif))
					$input_errors[] = sprintf(gettext("The captive portal cannot be used on interface %s since it is part of a bridge."), $cpbrif);

		if ($_POST['httpslogin_enable']) {
		 	if (!$_POST['cert'] || !$_POST['key']) {
				$input_errors[] = gettext("Certificate and key must be specified for HTTPS login.");
			} else {
				if (!strstr($_POST['cert'], "BEGIN CERTIFICATE") || !strstr($_POST['cert'], "END CERTIFICATE"))
					$input_errors[] = gettext("This certificate does not appear to be valid.");
				if (!strstr($_POST['cacert'], "BEGIN CERTIFICATE") || !strstr($_POST['cacert'], "END CERTIFICATE"))
					$input_errors[] = gettext("This intermmediate certificate does not appear to be valid.");
				if (!strstr($_POST['key'], "BEGIN RSA PRIVATE KEY") || !strstr($_POST['key'], "END RSA PRIVATE KEY"))
					$input_errors[] = gettext("This key does not appear to be valid.");
			}

			if (!$_POST['httpsname'] || !is_domain($_POST['httpsname'])) {
				$input_errors[] = gettext("The HTTPS server name must be specified for HTTPS login.");
			}
		}
	}

	if ($_POST['timeout'] && (!is_numeric($_POST['timeout']) || ($_POST['timeout'] < 1))) {
		$input_errors[] = gettext("The timeout must be at least 1 minute.");
	}
	if ($_POST['idletimeout'] && (!is_numeric($_POST['idletimeout']) || ($_POST['idletimeout'] < 1))) {
		$input_errors[] = gettext("The idle timeout must be at least 1 minute.");
	}
	if ($_POST['freelogins_count'] && (!is_numeric($_POST['freelogins_count']))) {
		$input_errors[] = gettext("The pass-through credit count must be a number or left blank.");
	} else if ($_POST['freelogins_count'] && is_numeric($_POST['freelogins_count']) && ($_POST['freelogins_count'] >= 1)) {
		if (empty($_POST['freelogins_resettimeout']) || !is_numeric($_POST['freelogins_resettimeout']) || ($_POST['freelogins_resettimeout'] <= 0)) {
			$input_errors[] = gettext("The waiting period to restore pass-through credits must be above 0 hours.");
		}
	}
	if (($_POST['radiusip'] && !is_ipaddr($_POST['radiusip']))) {
		$input_errors[] = sprintf(gettext("A valid IP address must be specified. [%s]"), $_POST['radiusip']);
	}
	if (($_POST['radiusip2'] && !is_ipaddr($_POST['radiusip2']))) {
		$input_errors[] = sprintf(gettext("A valid IP address must be specified. [%s]"), $_POST['radiusip2']);
	}
	if (($_POST['radiusport'] && !is_port($_POST['radiusport']))) {
		$input_errors[] = sprintf(gettext("A valid port number must be specified. [%s]"), $_POST['radiusport']);
	}
	if (($_POST['radiusport2'] && !is_port($_POST['radiusport2']))) {
		$input_errors[] = sprintf(gettext("A valid port number must be specified. [%s]"), $_POST['radiusport2']);
	}
	if (($_POST['radiusacctport'] && !is_port($_POST['radiusacctport']))) {
		$input_errors[] = sprintf(gettext("A valid port number must be specified. [%s]"), $_POST['radiusacctport']);
	}
	if ($_POST['maxprocperip'] && (!is_numeric($_POST['maxprocperip']) || ($_POST['maxprocperip'] < 4) || $_POST['maxprocperip'] > 100)) {
		$input_errors[] = gettext("The maximum number of concurrent connections per client IP address may not be larger than the global maximum.");
	}

	if (!$input_errors) {
		if (is_array($_POST['cinterface']))
			$config['captiveportal']['interface'] = implode(",", $_POST['cinterface']);
		$config['captiveportal']['maxprocperip'] = $_POST['maxprocperip'] ? $_POST['maxprocperip'] : false;
		$config['captiveportal']['timeout'] = $_POST['timeout'];
		$config['captiveportal']['idletimeout'] = $_POST['idletimeout'];
		$config['captiveportal']['freelogins_count'] = $_POST['freelogins_count'];
		$config['captiveportal']['freelogins_resettimeout'] = $_POST['freelogins_resettimeout'];
		$config['captiveportal']['freelogins_updatetimeouts'] = $_POST['freelogins_updatetimeouts'] ? true : false;
		$config['captiveportal']['enable'] = $_POST['enable'] ? true : false;
		$config['captiveportal']['auth_method'] = $_POST['auth_method'];
		$config['captiveportal']['radacct_enable'] = $_POST['radacct_enable'] ? true : false;
		$config['captiveportal']['reauthenticate'] = $_POST['reauthenticate'] ? true : false;
		$config['captiveportal']['radmac_enable'] = $_POST['radmac_enable'] ? true : false;
		$config['captiveportal']['radmac_secret'] = $_POST['radmac_secret'] ? $_POST['radmac_secret'] : false;
		$config['captiveportal']['reauthenticateacct'] = $_POST['reauthenticateacct'];
		$config['captiveportal']['httpslogin'] = $_POST['httpslogin_enable'] ? true : false;
		$config['captiveportal']['httpsname'] = $_POST['httpsname'];
		$config['captiveportal']['preauthurl'] = $_POST['preauthurl'];
		$config['captiveportal']['peruserbw'] = $_POST['peruserbw'] ? true : false;
		$config['captiveportal']['bwdefaultdn'] = $_POST['bwdefaultdn'];
		$config['captiveportal']['bwdefaultup'] = $_POST['bwdefaultup'];
		$config['captiveportal']['certificate'] = base64_encode($_POST['cert']);
		$config['captiveportal']['cacertificate'] = base64_encode($_POST['cacert']);
		$config['captiveportal']['private-key'] = base64_encode($_POST['key']);
		$config['captiveportal']['logoutwin_enable'] = $_POST['logoutwin_enable'] ? true : false;
		$config['captiveportal']['nomacfilter'] = $_POST['nomacfilter'] ? true : false;
		$config['captiveportal']['noconcurrentlogins'] = $_POST['noconcurrentlogins'] ? true : false;
		$config['captiveportal']['redirurl'] = $_POST['redirurl'];
		$config['captiveportal']['radiusip'] = $_POST['radiusip'];
		$config['captiveportal']['radiusip2'] = $_POST['radiusip2'];
		$config['captiveportal']['radiusport'] = $_POST['radiusport'];
		$config['captiveportal']['radiusport2'] = $_POST['radiusport2'];
		$config['captiveportal']['radiusacctport'] = $_POST['radiusacctport'];
		$config['captiveportal']['radiuskey'] = $_POST['radiuskey'];
		$config['captiveportal']['radiuskey2'] = $_POST['radiuskey2'];
		$config['captiveportal']['radiusvendor'] = $_POST['radiusvendor'] ? $_POST['radiusvendor'] : false;
		$config['captiveportal']['radiussession_timeout'] = $_POST['radiussession_timeout'] ? true : false;
		$config['captiveportal']['radiussrcip_attribute'] = $_POST['radiussrcip_attribute'];
		$config['captiveportal']['passthrumacadd'] = $_POST['passthrumacadd'] ? true : false;
		$config['captiveportal']['passthrumacaddusername'] = $_POST['passthrumacaddusername'] ? true : false;
		$config['captiveportal']['radmac_format'] = $_POST['radmac_format'] ? $_POST['radmac_format'] : false;

		/* file upload? */
		if (is_uploaded_file($_FILES['htmlfile']['tmp_name']))
			$config['captiveportal']['page']['htmltext'] = base64_encode(file_get_contents($_FILES['htmlfile']['tmp_name']));
		if (is_uploaded_file($_FILES['errfile']['tmp_name']))
			$config['captiveportal']['page']['errtext'] = base64_encode(file_get_contents($_FILES['errfile']['tmp_name']));
		if (is_uploaded_file($_FILES['logoutfile']['tmp_name']))
			$config['captiveportal']['page']['logouttext'] = base64_encode(file_get_contents($_FILES['logoutfile']['tmp_name']));

		write_config();

		$retval = 0;
		$retval = captiveportal_configure();

		$savemsg = get_std_save_message($retval);
		
		if (is_array($_POST['cinterface']))
			$pconfig['cinterface'] = implode(",", $_POST['cinterface']);

		filter_configure();
	}
}
include("head.inc");
?>
<?php include("fbegin.inc"); ?>
<script language="JavaScript">
<!--
function enable_change(enable_change) {
	var endis, radius_endis;
	endis = !(document.iform.enable.checked || enable_change);
	radius_endis = !((!endis && document.iform.auth_method[2].checked) || enable_change);

	document.iform.cinterface.disabled = endis;
	document.iform.maxprocperip.disabled = endis;
	document.iform.idletimeout.disabled = endis;
	document.iform.freelogins_count.disabled = endis;
	document.iform.freelogins_resettimeout.disabled = endis;
	document.iform.freelogins_updatetimeouts.disabled = endis;
	document.iform.timeout.disabled = endis;
	document.iform.preauthurl.disabled = endis;
	document.iform.redirurl.disabled = endis;
	document.iform.radiusip.disabled = radius_endis;
	document.iform.radiusip2.disabled = radius_endis;
	document.iform.radiusport.disabled = radius_endis;
	document.iform.radiusport2.disabled = radius_endis;
	document.iform.radiuskey.disabled = radius_endis;
	document.iform.radiuskey2.disabled = radius_endis;
	document.iform.radacct_enable.disabled = radius_endis;
	document.iform.peruserbw.disabled = endis;
	document.iform.bwdefaultdn.disabled = endis;
	document.iform.bwdefaultup.disabled = endis;
	document.iform.reauthenticate.disabled = radius_endis;
	document.iform.auth_method[0].disabled = endis;
	document.iform.auth_method[1].disabled = endis;
	document.iform.auth_method[2].disabled = endis;
	document.iform.radmac_enable.disabled = radius_endis;
	document.iform.httpslogin_enable.disabled = endis;
	document.iform.radmac_format.disabled = radius_endis;
	document.iform.httpsname.disabled = endis;
	document.iform.cert.disabled = endis;
	document.iform.cacert.disabled = endis;
	document.iform.key.disabled = endis;
	document.iform.logoutwin_enable.disabled = endis;
	document.iform.nomacfilter.disabled = endis;
	document.iform.noconcurrentlogins.disabled = endis;
	document.iform.radiusvendor.disabled = radius_endis;
	document.iform.radiussession_timeout.disabled = radius_endis;
	document.iform.radiussrcip_attribute.disabled = radius_endis;
	document.iform.htmlfile.disabled = endis;
	document.iform.errfile.disabled = endis;
	document.iform.logoutfile.disabled = endis;

	document.iform.radiusacctport.disabled = (radius_endis || !document.iform.radacct_enable.checked) && !enable_change;

	document.iform.radmac_secret.disabled = (radius_endis || !document.iform.radmac_enable.checked) && !enable_change;

	var radacct_dis = (radius_endis || !document.iform.radacct_enable.checked) && !enable_change;
	document.iform.reauthenticateacct[0].disabled = radacct_dis;
	document.iform.reauthenticateacct[1].disabled = radacct_dis;
	document.iform.reauthenticateacct[2].disabled = radacct_dis;
}
//-->
</script>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<form action="services_captiveportal.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("Captive portal"), true, "services_captiveportal.php");
	$tab_array[] = array(gettext("Pass-through MAC"), false, "services_captiveportal_mac.php");
	$tab_array[] = array(gettext("Allowed IP addresses"), false, "services_captiveportal_ip.php");
	$tab_array[] = array(gettext("Allowed Hostnames"), false, "services_captiveportal_hostname.php");	
	$tab_array[] = array(gettext("Vouchers"), false, "services_captiveportal_vouchers.php");
	$tab_array[] = array(gettext("File Manager"), false, "services_captiveportal_filemanager.php");
	display_top_tabs($tab_array, true);
?>    </td></tr>
  <tr>
  <td class="tabcont">
  <table width="100%" border="0" cellpadding="6" cellspacing="0">
	<tr>
	  <td width="22%" valign="top" class="vtable">&nbsp;</td>
	  <td width="78%" class="vtable">
		<input name="enable" type="checkbox" value="yes" <?php if ($pconfig['enable']) echo "checked"; ?> onClick="enable_change(false)">
		<strong><?=gettext("Enable captive portal"); ?> </strong></td>
	</tr>
	<tr>
	  <td width="22%" valign="top" class="vncellreq"><?=gettext("Interfaces"); ?></td>
	  <td width="78%" class="vtable">
		<select name="cinterface[]" multiple="true" size="<?php echo count($config['interfaces']); ?>" class="formselect" id="cinterface">
		  <?php 
		  $interfaces = get_configured_interface_with_descr();
		  $cselected = explode(",", $pconfig['cinterface']);
		  foreach ($interfaces as $iface => $ifacename): ?>
			  <option value="<?=$iface;?>" <?php if (in_array($iface, $cselected)) echo "selected"; ?>>
			  <?=htmlspecialchars($ifacename);?>
			  </option>
		  <?php endforeach; ?>
		</select> <br>
		<span class="vexpl"><?=gettext("Select the interface(s) to enable for captive portal."); ?></span></td>
	</tr>
	<tr>
	  <td valign="top" class="vncell"><?=gettext("Maximum concurrent connections"); ?></td>
	  <td class="vtable">
		<table cellpadding="0" cellspacing="0">
                 <tr>
           			<td><input name="maxprocperip" type="text" class="formfld unknown" id="maxprocperip" size="5" 
value="<?=htmlspecialchars($pconfig['maxprocperip']);?>"> <?=gettext("per client IP address (0 = no limit)"); ?></td>
                 </tr>
               </table>
<?=gettext("This setting limits the number of concurrent connections to the captive portal HTTP(S) server. This does not set how many users can be logged in " .
"to the captive portal, but rather how many users can load the portal page or authenticate at the same time! " .
"Possible setting allowed is: minimum 4 connections per client IP address, with a total maximum of 100 connections."); ?></td>
	</tr>
	<tr>
	  <td valign="top" class="vncell"><?=gettext("Idle timeout"); ?></td>
	  <td class="vtable">
		<input name="idletimeout" type="text" class="formfld unknown" id="idletimeout" size="6" value="<?=htmlspecialchars($pconfig['idletimeout']);?>">
<?=gettext("minutes"); ?><br>
<?=gettext("Clients will be disconnected after this amount of inactivity. They may log in again immediately, though. Leave this field blank for no idle timeout."); ?></td>
	</tr>
	<tr>
	  <td width="22%" valign="top" class="vncell"><?=gettext("Hard timeout"); ?></td>
	  <td width="78%" class="vtable">
		<input name="timeout" type="text" class="formfld unknown" id="timeout" size="6" value="<?=htmlspecialchars($pconfig['timeout']);?>">
		<?=gettext("minutes"); ?><br>
	  <?=gettext("Clients will be disconnected after this amount of time, regardless of activity. They may log in again immediately, though. Leave this field blank for no hard timeout (not recommended unless an idle timeout is set)."); ?></td>
	</tr>
	<tr>
	  <td width="22%" valign="top" class="vncell"><?=gettext("Pass-through credits allowed per MAC address"); ?></td>
	  <td width="78%" class="vtable">
		<input name="freelogins_count" type="text" class="formfld unknown" id="freelogins_count" size="6" value="<?=htmlspecialchars($pconfig['freelogins_count']);?>">
		<?=gettext("per client MAC address (0 or blank = none)"); ?><br>
		<?=gettext("This setting allows passing through the captive portal without authentication a limited number of times per MAC address. Once used up, the client can only log in with valid credentials until the waiting period specified below has expired. Recommended to set a hard timeout and/or idle timeout when using this for it to be effective."); ?></td>
	</tr>
	<tr>
	  <td width="22%" valign="top" class="vncell"><?=gettext("Waiting period to restore pass-through credits"); ?></td>
	  <td width="78%" class="vtable">
		<input name="freelogins_resettimeout" type="text" class="formfld unknown" id="freelogins_resettimeout" size="6" value="<?=htmlspecialchars($pconfig['freelogins_resettimeout']);?>">
		<?=gettext("hours"); ?><br>
		<?=gettext("Clients will have their available pass-through credits restored to the original count after this amount of time since using the first one. This must be above 0 hours if pass-through credits are enabled."); ?></td>
	</tr>
	<tr>
	  <td width="22%" valign="top" class="vncell"><?=gettext("Reset waiting period on attempted access"); ?></td>
	  <td width="78%" class="vtable">
		<input name="freelogins_updatetimeouts" type="checkbox" class="formfld" id="freelogins_updatetimeouts" value="yes" <?php if($pconfig['freelogins_updatetimeouts']) echo "checked"; ?>>
		<strong><?=gettext("Enable waiting period reset on attempted access"); ?></strong><br>
		<?=gettext("If enabled, the waiting period is reset to the original duration if access is attempted when all pass-through credits have already been exhausted."); ?></td>
	</tr>
	<tr>
	  <td width="22%" valign="top" class="vncell"><?=gettext("Logout popup window"); ?></td>
	  <td width="78%" class="vtable">
		<input name="logoutwin_enable" type="checkbox" class="formfld" id="logoutwin_enable" value="yes" <?php if($pconfig['logoutwin_enable']) echo "checked"; ?>>
		<strong><?=gettext("Enable logout popup window"); ?></strong><br>
	  <?=gettext("If enabled, a popup window will appear when clients are allowed through the captive portal. This allows clients to explicitly disconnect themselves before the idle or hard timeout occurs."); ?></td>
	</tr>
	<tr>
      <td valign="top" class="vncell"><?=gettext("Pre-authentication redirect URL"); ?> </td>
      <td class="vtable">
        <input name="preauthurl" type="text" class="formfld url" id="preauthurl" size="60" value="<?=htmlspecialchars($pconfig['preauthurl']);?>"><br>
		<?php printf(gettext("Use this field to set \$PORTAL_REDIRURL\$ variable which can be accessed using your custom captive portal index.php page or error pages."));?> 
	  </td>
	</tr>
	<tr>
	  <td valign="top" class="vncell"><?=gettext("After authentication Redirection URL"); ?></td>
	  <td class="vtable">
		<input name="redirurl" type="text" class="formfld url" id="redirurl" size="60" value="<?=htmlspecialchars($pconfig['redirurl']);?>">
		<br>
<?=gettext("If you provide a URL here, clients will be redirected to that URL instead of the one they initially tried " .
"to access after they've authenticated."); ?></td>
	</tr>
	<tr>
      <td valign="top" class="vncell"><?=gettext("Concurrent user logins"); ?></td>
      <td class="vtable">
	<input name="noconcurrentlogins" type="checkbox" class="formfld" id="noconcurrentlogins" value="yes" <?php if ($pconfig['noconcurrentlogins']) echo "checked"; ?>>
	<strong><?=gettext("Disable concurrent logins"); ?></strong><br>
	<?=gettext("If this option is set, only the most recent login per username will be active. Subsequent logins will cause machines previously logged in with the same username to be disconnected."); ?></td>
	</tr>
	<tr>
      <td valign="top" class="vncell"><?=gettext("MAC filtering"); ?> </td>
      <td class="vtable">
        <input name="nomacfilter" type="checkbox" class="formfld" id="nomacfilter" value="yes" <?php if ($pconfig['nomacfilter']) echo "checked"; ?>>
        <strong><?=gettext("Disable MAC filtering"); ?></strong><br>
    <?=gettext("If this option is set, no attempts will be made to ensure that the MAC address of clients stays the same while they're logged in." .
    "This is required when the MAC address of the client cannot be determined (usually because there are routers between"); ?> <?php echo $g['product_name'] ?> <?=gettext("and the clients)."); ?>
    <?=gettext("If this is enabled, RADIUS MAC authentication cannot be used."); ?></td>
	</tr>
	<tr>
      <td valign="top" class="vncell"><?=gettext("Pass-through MAC Auto Entry"); ?></td>
      <td class="vtable">
        <input name="passthrumacadd" type="checkbox" class="formfld" id="passthrumacadd" value="yes" <?php if ($pconfig['passthrumacadd']) echo "checked"; ?>>
        <strong><?=gettext("Enable Pass-through MAC automatic additions"); ?></strong><br>
    <?=gettext("If this option is set, a MAC passthrough entry is automatically added after the user has successfully authenticated. Users of that MAC address will never have to authenticate again."); ?> 
    <?=gettext("To remove the passthrough MAC entry you either have to log in and remove it manually from the"); ?> <a href="services_captiveportal_mac.php"><?=gettext("Pass-through MAC tab"); ?></a> <?=gettext("or send a POST from another system to remove it."); ?>
    <?=gettext("If this is enabled, RADIUS MAC authentication cannot be used. Also, the logout window will not be shown."); ?>
	<br/><br/>
        <input name="passthrumacaddusername" type="checkbox" class="formfld" id="passthrumacaddusername" value="yes" <?php if ($pconfig['passthrumacaddusername']) echo "checked"; ?>>
        <strong><?=gettext("Enable Pass-through MAC automatic addition with username"); ?></strong><br>
    <?=gettext("If this option is set, with the automatically MAC passthrough entry created the username, used during authentication, will be saved."); ?>
    <?=gettext("To remove the passthrough MAC entry you either have to log in and remove it manually from the"); ?> <a href="services_captiveportal_mac.php"><?=gettext("Pass-through MAC tab"); ?></a> <?=gettext("or send a POST from another system to remove it."); ?>
	</td>
	</tr>
	<tr>
      <td valign="top" class="vncell"><?=gettext("Per-user bandwidth restriction"); ?></td>
      <td class="vtable">
        <input name="peruserbw" type="checkbox" class="formfld" id="peruserbw" value="yes" <?php if ($pconfig['peruserbw']) echo "checked"; ?>>
        <strong><?=gettext("Enable per-user bandwidth restriction"); ?></strong><br><br>
        <table cellpadding="0" cellspacing="0">
        <tr>
        <td><?=gettext("Default download"); ?></td>
        <td><input type="text" class="formfld unknown" name="bwdefaultdn" id="bwdefaultdn" size="10" value="<?=htmlspecialchars($pconfig['bwdefaultdn']);?>"> <?=gettext("Kbit/s"); ?></td>
        </tr>
        <tr>
        <td><?=gettext("Default upload"); ?></td>
        <td><input type="text" class="formfld unknown" name="bwdefaultup" id="bwdefaultup" size="10" value="<?=htmlspecialchars($pconfig['bwdefaultup']);?>"> <?=gettext("Kbit/s"); ?></td>
        </tr></table>
        <br>
        <?=gettext("If this option is set, the captive portal will restrict each user who logs in to the specified default bandwidth. RADIUS can override the default settings. Leave empty or set to 0 for no limit."); ?> </td>
	</tr>
	<tr>
	  <td width="22%" valign="top" class="vncell"><?=gettext("Authentication"); ?></td>
	  <td width="78%" class="vtable">
		<table cellpadding="0" cellspacing="0">
		<tr>
		  <td colspan="2"><input name="auth_method" type="radio" id="auth_method" value="none" onClick="enable_change(false)" <?php if($pconfig['auth_method']!="local" && $pconfig['auth_method']!="radius") echo "checked"; ?>>
  <?=gettext("No Authentication"); ?></td>
		  </tr>
		<tr>
		  <td colspan="2"><input name="auth_method" type="radio" id="auth_method" value="local" onClick="enable_change(false)" <?php if($pconfig['auth_method']=="local") echo "checked"; ?>>
  <?=gettext("Local"); ?> <a href="system_usermanager.php"><?=gettext("User Manager"); ?></a> / <?=gettext("Vouchers"); ?></td>
		  </tr>
		<tr>
		  <td colspan="2"><input name="auth_method" type="radio" id="auth_method" value="radius" onClick="enable_change(false)" <?php if($pconfig['auth_method']=="radius") echo "checked"; ?>>
  <?=gettext("RADIUS Authentication"); ?></td>
		  </tr><tr>
		  <td>&nbsp;</td>
		  <td>&nbsp;</td>
		  </tr>
		</table>
		<table width="100%" border="0" cellpadding="6" cellspacing="0">
        	<tr>
            	<td colspan="2" valign="top" class="optsect_t2"><?=gettext("Primary RADIUS server"); ?></td>
			</tr>
			<tr>
				<td class="vncell" valign="top"><?=gettext("IP address"); ?></td>
				<td class="vtable"><input name="radiusip" type="text" class="formfld unknown" id="radiusip" size="20" value="<?=htmlspecialchars($pconfig['radiusip']);?>"><br>
				<?=gettext("Enter the IP address of the RADIUS server which users of the captive portal have to authenticate against."); ?></td>
			</tr>
			<tr>
				<td class="vncell" valign="top"><?=gettext("Port"); ?></td>
				<td class="vtable"><input name="radiusport" type="text" class="formfld unknown" id="radiusport" size="5" value="<?=htmlspecialchars($pconfig['radiusport']);?>"><br>
				 <?=gettext("Leave this field blank to use the default port (1812)."); ?></td>
			</tr>
			<tr>
				<td class="vncell" valign="top"><?=gettext("Shared secret"); ?>&nbsp;&nbsp;</td>
				<td class="vtable"><input name="radiuskey" type="text" class="formfld unknown" id="radiuskey" size="16" value="<?=htmlspecialchars($pconfig['radiuskey']);?>"><br>
				<?=gettext("Leave this field blank to not use a RADIUS shared secret (not recommended)."); ?></td>
			</tr>
			<tr>
			  <td colspan="2" class="list" height="12"></td>
			</tr>
			<tr>
				<td colspan="2" valign="top" class="optsect_t2"><?=gettext("Secondary RADIUS server"); ?></td>
			</tr>
			<tr>
				<td class="vncell" valign="top"><?=gettext("IP address"); ?></td>
				<td class="vtable"><input name="radiusip2" type="text" class="formfld unknown" id="radiusip2" size="20" value="<?=htmlspecialchars($pconfig['radiusip2']);?>"><br>
				<?=gettext("If you have a second RADIUS server, you can activate it by entering its IP address here."); ?></td>
			</tr>
			<tr>
				<td class="vncell" valign="top"><?=gettext("Port"); ?></td>
				<td class="vtable"><input name="radiusport2" type="text" class="formfld unknown" id="radiusport2" size="5" value="<?=htmlspecialchars($pconfig['radiusport2']);?>"></td>
			</tr>
			<tr>
				<td class="vncell" valign="top"><?=gettext("Shared secret"); ?>&nbsp;&nbsp;</td>
				<td class="vtable"><input name="radiuskey2" type="text" class="formfld unknown" id="radiuskey2" size="16" value="<?=htmlspecialchars($pconfig['radiuskey2']);?>"></td>
			</tr>
			<tr>
			  <td colspan="2" class="list" height="12"></td>
			</tr>
			<tr>
				<td colspan="2" valign="top" class="optsect_t2"><?=gettext("Accounting"); ?></td>
			</tr>
			<tr>
				<td class="vncell">&nbsp;</td>
				<td class="vtable"><input name="radacct_enable" type="checkbox" id="radacct_enable" value="yes" onClick="enable_change(false)" <?php if($pconfig['radacct_enable']) echo "checked"; ?>>
				<strong><?=gettext("send RADIUS accounting packets"); ?></strong><br>
				<?=gettext("If this is enabled, RADIUS accounting packets will be sent to the primary RADIUS server."); ?></td>
			</tr>
			<tr>
			  <td class="vncell" valign="top"><?=gettext("Accounting port"); ?></td>
			  <td class="vtable"><input name="radiusacctport" type="text" class="formfld unknown" id="radiusacctport" size="5" value="<?=htmlspecialchars($pconfig['radiusacctport']);?>"><br>
			  <?=gettext("Leave blank to use the default port (1813)."); ?></td>
			  </tr>
			<tr>
			  <td colspan="2" class="list" height="12"></td>
			</tr>
			<tr>
				<td colspan="2" valign="top" class="optsect_t2"><?=gettext("Reauthentication"); ?></td>
			</tr>
			<tr>
				<td class="vncell">&nbsp;</td>
				<td class="vtable"><input name="reauthenticate" type="checkbox" id="reauthenticate" value="yes" onClick="enable_change(false)" <?php if($pconfig['reauthenticate']) echo "checked"; ?>>
			  <strong><?=gettext("Reauthenticate connected users every minute"); ?></strong><br>
			  <?=gettext("If reauthentication is enabled, Access-Requests will be sent to the RADIUS server for each user that is " .
			  "logged in every minute. If an Access-Reject is received for a user, that user is disconnected from the captive portal immediately."); ?></td>
			</tr>
			<tr>
			  <td class="vncell" valign="top"><?=gettext("Accounting updates"); ?></td>
			  <td class="vtable">
			  <input name="reauthenticateacct" type="radio" value="" <?php if(!$pconfig['reauthenticateacct']) echo "checked"; ?>> <?=gettext("no accounting updates"); ?><br>
			  <input name="reauthenticateacct" type="radio" value="stopstart" <?php if($pconfig['reauthenticateacct'] == "stopstart") echo "checked"; ?>> <?=gettext("stop/start accounting"); ?><br>
			  <input name="reauthenticateacct" type="radio" value="interimupdate" <?php if($pconfig['reauthenticateacct'] == "interimupdate") echo "checked"; ?>> <?=gettext("interim update"); ?>
			  </td>
			</tr>
			<tr>
			  <td colspan="2" class="list" height="12"></td>
			</tr>
			<tr>
				<td colspan="2" valign="top" class="optsect_t2"><?=gettext("RADIUS MAC authentication"); ?></td>
			</tr>
			<tr>
				<td class="vncell">&nbsp;</td>
				<td class="vtable">
				<input name="radmac_enable" type="checkbox" id="radmac_enable" value="yes" onClick="enable_change(false)" <?php if ($pconfig['radmac_enable']) echo "checked"; ?>><strong><?=gettext("Enable RADIUS MAC authentication"); ?></strong><br>
				<?=gettext("If this option is enabled, the captive portal will try to authenticate users by sending their MAC address as the username and the password " .
				"entered below to the RADIUS server."); ?></td>
			</tr>
			<tr>
				<td class="vncell"><?=gettext("Shared secret"); ?></td>
				<td class="vtable"><input name="radmac_secret" type="text" class="formfld unknown" id="radmac_secret" size="16" value="<?=htmlspecialchars($pconfig['radmac_secret']);?>"></td>
			</tr>
			<tr>
			  <td colspan="2" class="list" height="12"></td>
			</tr>
			<tr>
				<td colspan="2" valign="top" class="optsect_t2"><?=gettext("RADIUS options"); ?></td>
			</tr>

			<tr>
				<td class="vncell" valign="top"><?=gettext("RADIUS NAS IP attribute"); ?></td>
				<td>
				<select name="radiussrcip_attribute" id="radiussrcip_attribute">
				<?php $iflist = get_configured_interface_with_descr();
					foreach ($iflist as $ifdesc => $ifdescr) {
						$ipaddr = get_interface_ip($ifdesc);
						if (is_ipaddr($ipaddr)) {
							$selected = "";
							if ($ifdesc == $pconfig['radiussrcip_attribute'])
								$selected = "selected";
							echo "<option value='{$ifdesc}' {$selected}>{$ifdescr} - {$ipaddr}</option>\n";
						}
					}
					if (is_array($config['virtualip']['vip'])) {
                				foreach ($config['virtualip']['vip'] as $sn) {
                        				if ($sn['mode'] == "proxyarp" && $sn['type'] == "network") {
                                				$start = ip2long32(gen_subnet($sn['subnet'], $sn['subnet_bits']));
                                				$end = ip2long32(gen_subnet_max($sn['subnet'], $sn['subnet_bits']));
                                				$len = $end - $start;

                                				for ($i = 0; $i <= $len; $i++) {
                                        				$snip = long2ip32($start+$i);
                                					echo "<option value='{$snip}' {$selected}>" . htmlspecialchars("{$sn['descr']} - {$snip}") . "></option>\n";
								}
							} else
                                				echo "<option value='{$sn['subnet']}' {$selected}>" . htmlspecialchars("{$sn['descr']} - {$sn['subnet']}") . "></option>\n";
						}
					}
				?>
				</select><br/>
				<?=gettext("Choose the IP to use for calling station attribute."); ?>
				</td>
			</tr>

			<tr>
				<td class="vncell" valign="top"><?=gettext("Session-Timeout"); ?></td>
				<td class="vtable"><input name="radiussession_timeout" type="checkbox" id="radiussession_timeout" value="yes" <?php if ($pconfig['radiussession_timeout']) echo "checked"; ?>><strong><?=gettext("Use RADIUS Session-Timeout attributes"); ?></strong><br>
				<?=gettext("When this is enabled, clients will be disconnected after the amount of time retrieved from the RADIUS Session-Timeout attribute."); ?></td>
			</tr>

			<tr>
				<td class="vncell" valign="top"><?=gettext("Type"); ?></td>
				<td class="vtable"><select name="radiusvendor" id="radiusvendor">
				<option><?=gettext("default"); ?></option>
				<?php
				$radiusvendors = array("cisco");
				foreach ($radiusvendors as $radiusvendor){
					if ($pconfig['radiusvendor'] == $radiusvendor)
						echo "<option selected value=\"$radiusvendor\">$radiusvendor</option>\n";
					else
						echo "<option value=\"$radiusvendor\">$radiusvendor</option>\n";
				}
				?></select><br>
				<?php printf(gettext("If RADIUS type is set to Cisco, in Access-Requests the value of Calling-Station-Id will be set to the client's IP address and " .
				"the Called-Station-Id to the client's MAC address. Default behavior is Calling-Station-Id = client's MAC address and Called-Station-Id = %s's WAN IP address."),
					$g['product_name']);?></td>
			</tr>
		</table>
	</tr>
    <tr>
        <td class="vncell" valign="top"><?=gettext("MAC address format"); ?></td>
        <td class="vtable">
        <select name="radmac_format" id="radmac_format">
        <option><?=gettext("default"); ?></option>
        <?php
        $macformats = array(gettext("singledash"),gettext("ietf"),gettext("cisco"),gettext("unformatted"));
        foreach ($macformats as $macformat) {
            if ($pconfig['radmac_format'] == $macformat)
                echo "<option selected value=\"$macformat\">$macformat</option>\n";
            else
                echo "<option value=\"$macformat\">$macformat</option>\n";
        }
        ?>
        </select></br>
        <?=gettext("This option changes the MAC address format used in the whole RADIUS system. Change this if you also"); ?>
        <?=gettext("need to change the username format for RADIUS MAC authentication."); ?><br>
        <?=gettext("default:"); ?> 00:11:22:33:44:55<br>
        <?=gettext("singledash:"); ?> 001122-334455<br>
        <?=gettext("ietf:"); ?> 00-11-22-33-44-55<br>
        <?=gettext("cisco:"); ?> 0011.2233.4455<br>
        <?=gettext("unformatted:"); ?> 001122334455
    </tr>
	<tr>
      <td valign="top" class="vncell"><?=gettext("HTTPS login"); ?></td>
      <td class="vtable">
        <input name="httpslogin_enable" type="checkbox" class="formfld" id="httpslogin_enable" value="yes" <?php if($pconfig['httpslogin_enable']) echo "checked"; ?>>
        <strong><?=gettext("Enable HTTPS login"); ?></strong><br>
    <?=gettext("If enabled, the username and password will be transmitted over an HTTPS connection to protect against eavesdroppers. A server name, certificate and matching private key must also be specified below."); ?></td>
	  </tr>
	<tr>
      <td valign="top" class="vncell"><?=gettext("HTTPS server name"); ?> </td>
      <td class="vtable">
        <input name="httpsname" type="text" class="formfld unknown" id="httpsname" size="30" value="<?=htmlspecialchars($pconfig['httpsname']);?>"><br>
	<?php printf(gettext("This name will be used in the form action for the HTTPS POST and should match the Common Name (CN) in your certificate (otherwise, the client browser will most likely display a security warning). Make sure captive portal clients can resolve this name in DNS and verify on the client that the IP resolves to the correct interface IP on %s."), $g['product_name']);?> </td>
	  </tr>
	<tr>
      <td valign="top" class="vncell"><?=gettext("HTTPS certificate"); ?></td>
      <td class="vtable">
        <textarea name="cert" cols="65" rows="7" id="cert" class="formpre"><?=htmlspecialchars($pconfig['cert']);?></textarea>
        <br>
    <?=gettext("Paste a signed certificate in X.509 PEM format here."); ?></td>
	  </tr>
	<tr>
      <td valign="top" class="vncell"><?=gettext("HTTPS private key"); ?></td>
      <td class="vtable">
        <textarea name="key" cols="65" rows="7" id="key" class="formpre"><?=htmlspecialchars($pconfig['key']);?></textarea>
        <br>
    <?=gettext("Paste an RSA private key in PEM format here."); ?></td>
	  </tr>
        <tr>
      <td valign="top" class="vncell"><?=gettext("HTTPS intermediate certificate"); ?></td>
      <td class="vtable">
        <textarea name="cacert" cols="65" rows="7" id="cacert" class="formpre"><?=htmlspecialchars($pconfig['cacert']);?></textarea>
        <br>
    <?=gettext("Paste a certificate in X.509 PEM format here."); ?></td>
          </tr>
	<tr>
	  <td width="22%" valign="top" class="vncellreq"><?=gettext("Portal page contents"); ?></td>
	  <td width="78%" class="vtable">
		<?=$mandfldhtml;?><input type="file" name="htmlfile" class="formfld file" id="htmlfile"><br>
		<?php
			list($host) = explode(":", $_SERVER['HTTP_HOST']);
			if(isset($config['captiveportal']['httpslogin'])) {
				$href = "https://$host:8001";
			} else {
				$href = "http://$host:8000";
			}
		?>
		<?php if ($config['captiveportal']['page']['htmltext']): ?>
		<a href="<?=$href?>" target="_new"><?=gettext("View current page"); ?></a>
		  <br>
		  <br>
		<?php endif; ?>
			<?php
				printf(
					gettext('Upload an HTML/PHP file for the portal page here (leave blank to keep the current one). ' .
							'Make sure to include a form (POST to %1$s) with a submit button (%2$s) and a hidden field with %3$s and %4$s. ' .
							'Include the %5$s and %6$s and/or %7$s input fields if authentication is enabled, otherwise it will always fail.'),
					"&quot;{$PORTAL_ACTION}&quot;",
					"name=&quot;accept&quot;",
					"name=&quot;redirurl&quot;",
					"value=&quot;{$PORTAL_REDIRURL}&quot;",
					"&quot;auth_user&quot;",
					"&quot;auth_pass&quot;",
					"&quot;auth_voucher&quot;");
			?>
			<?=gettext("Example code for the form:"); ?><br>
		  <br>
		  <tt>&lt;form method=&quot;post&quot; action=&quot;$PORTAL_ACTION$&quot;&gt;<br>
		  &nbsp;&nbsp;&nbsp;&lt;input name=&quot;auth_user&quot; type=&quot;text&quot;&gt;<br>
		  &nbsp;&nbsp;&nbsp;&lt;input name=&quot;auth_pass&quot; type=&quot;password&quot;&gt;<br>
		  &nbsp;&nbsp;&nbsp;&lt;input name=&quot;auth_voucher&quot; type=&quot;text&quot;&gt;<br>
		  &nbsp;&nbsp;&nbsp;&lt;input name=&quot;redirurl&quot; type=&quot;hidden&quot; value=&quot;$PORTAL_REDIRURL$&quot;&gt;<br>
&nbsp;&nbsp;&nbsp;&lt;input name=&quot;accept&quot; type=&quot;submit&quot; value=&quot;Continue&quot;&gt;<br>
		  &lt;/form&gt;</tt></td>
	</tr>
	<tr>
	  <td width="22%" valign="top" class="vncell"><?=gettext("Authentication"); ?><br>
		<?=gettext("error page"); ?><br>
		<?=gettext("contents"); ?></td>
	  <td class="vtable">
		<input name="errfile" type="file" class="formfld file" id="errfile"><br>
		<?php if ($config['captiveportal']['page']['errtext']): ?>
		<a href="?act=viewerrhtml" target="_blank"><?=gettext("View current page"); ?></a>
		  <br>
		  <br>
		<?php endif; ?>
<?=gettext("The contents of the HTML/PHP file that you upload here are displayed when an authentication error occurs. " .
"You may include"); ?> &quot;$PORTAL_MESSAGE$&quot;, <?=gettext("which will be replaced by the error or reply messages from the RADIUS server, if any."); ?></td>
	</tr>
	<tr>
	  <td width="22%" valign="top" class="vncell"><?=gettext("Logout"); ?><br>
		<?=gettext("page"); ?><br>
		<?=gettext("contents"); ?></td>
	  <td class="vtable">
		<input name="logoutfile" type="file" class="formfld file" id="logoutfile"><br>
		<?php if ($config['captiveportal']['page']['logouttext']): ?>
		<a href="?act=viewlogouthtml" target="_blank"><?=gettext("View current page"); ?></a>
		  <br>
		  <br>
		<?php endif; ?>
<?=gettext("The contents of the HTML/PHP file that you upload here are displayed on authentication success when the logout popup is enabled."); ?></td>
	</tr>
	<tr>
	  <td width="22%" valign="top">&nbsp;</td>
	  <td width="78%">
		<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" onClick="enable_change(true)">
	  </td>
	</tr>
	<tr>
	  <td width="22%" valign="top">&nbsp;</td>
	  <td width="78%"><span class="vexpl"><span class="red"><strong><?=gettext("Note:"); ?><br>
		</strong></span><?=gettext("Changing any settings on this page will disconnect all clients! Don't forget to enable the DHCP server on your captive portal interface! Make sure that the default/maximum DHCP lease time is higher than the timeout entered on this page. Also, the DNS forwarder needs to be enabled for DNS lookups by unauthenticated clients to work."); ?> </span></td>
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

