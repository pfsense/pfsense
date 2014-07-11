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

require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("captiveportal.inc");

if (substr($_GET['act'], 0, 3) == "get")
	$nocsrf = true;

require_once("guiconfig.inc");

global $cpzone;
global $cpzoneid;

$cpzoneid = 1; /* Just a default */
$cpzone = $_GET['zone'];
if (isset($_POST['zone']))
	$cpzone = $_POST['zone'];

if (empty($cpzone) || empty($config['captiveportal'][$cpzone])) {
	header("Location: services_captiveportal_zones.php");
	exit;
}

if (!is_array($config['captiveportal']))
	$config['captiveportal'] = array();
$a_cp =& $config['captiveportal'];

$pgtitle = array(gettext("Services"),gettext("Captive portal"), $a_cp[$cpzone]['zone']);
$shortcut_section = "captiveportal";

if ($_GET['act'] == "viewhtml") {
	if ($a_cp[$cpzone] && $a_cp[$cpzone]['page']['htmltext'])
		echo base64_decode($a_cp[$cpzone]['page']['htmltext']);
	exit;
} else if ($_GET['act'] == "gethtmlhtml" && $a_cp[$cpzone] && $a_cp[$cpzone]['page']['htmltext']) {
	$file_data = base64_decode($a_cp[$cpzone]['page']['htmltext']);
	$file_size = strlen($file_data);

	header("Content-Type: text/html");
	header("Content-Disposition: attachment; filename=portal.html");
	header("Content-Length: $file_size");
	echo $file_data;

	exit;
} else if ($_GET['act'] == "delhtmlhtml" && $a_cp[$cpzone] && $a_cp[$cpzone]['page']['htmltext']) {
	unset($a_cp[$cpzone]['page']['htmltext']);
	write_config(sprintf(gettext("Captive Portal: zone %s: Restore default portal page"), $cpzone));
	header("Location: services_captiveportal.php?zone={$cpzone}");
	exit;
} else if ($_GET['act'] == "viewerrhtml") {
	if ($a_cp[$cpzone] && $a_cp[$cpzone]['page']['errtext'])
		echo base64_decode($a_cp[$cpzone]['page']['errtext']);
	exit;
} else if ($_GET['act'] == "geterrhtml" && $a_cp[$cpzone] && $a_cp[$cpzone]['page']['errtext']) {
	$file_data = base64_decode($a_cp[$cpzone]['page']['errtext']);
	$file_size = strlen($file_data);

	header("Content-Type: text/html");
	header("Content-Disposition: attachment; filename=err.html");
	header("Content-Length: $file_size");
	echo $file_data;

	exit;
} else if ($_GET['act'] == "delerrhtml" && $a_cp[$cpzone] && $a_cp[$cpzone]['page']['errtext']) {
	unset($a_cp[$cpzone]['page']['errtext']);
	write_config(sprintf(gettext("Captive Portal: zone %s: Restore default error page"), $cpzone));
	header("Location: services_captiveportal.php?zone={$cpzone}");
	exit;
} else if ($_GET['act'] == "viewlogouthtml") {
	if ($a_cp[$cpzone] && $a_cp[$cpzone]['page']['logouttext'])
		echo base64_decode($a_cp[$cpzone]['page']['logouttext']);
	exit;
} else if ($_GET['act'] == "getlogouthtml" && $a_cp[$cpzone] && $a_cp[$cpzone]['page']['logouttext']) {
	$file_data = base64_decode($a_cp[$cpzone]['page']['logouttext']);
	$file_size = strlen($file_data);

	header("Content-Type: text/html");
	header("Content-Disposition: attachment; filename=logout.html");
	header("Content-Length: $file_size");
	echo $file_data;

	exit;
} else if ($_GET['act'] == "dellogouthtml" && $a_cp[$cpzone] && $a_cp[$cpzone]['page']['logouttext']) {
	unset($a_cp[$cpzone]['page']['logouttext']);
	write_config(sprintf(gettext("Captive Portal: zone %s: Restore default logout page"), $cpzone));
	header("Location: services_captiveportal.php?zone={$cpzone}");
	exit;
}

if (!is_array($config['ca']))
	$config['ca'] = array();

$a_ca =& $config['ca'];

if (!is_array($config['cert']))
	$config['cert'] = array();

$a_cert =& $config['cert'];

if ($a_cp[$cpzone]) {
	$cpzoneid = $pconfig['zoneid'] = $a_cp[$cpzone]['zoneid'];
	$pconfig['cinterface'] = $a_cp[$cpzone]['interface'];
	$pconfig['maxproc'] = $a_cp[$cpzone]['maxproc'];
	$pconfig['maxprocperip'] = $a_cp[$cpzone]['maxprocperip'];
	$pconfig['timeout'] = $a_cp[$cpzone]['timeout'];
	$pconfig['idletimeout'] = $a_cp[$cpzone]['idletimeout'];
	$pconfig['freelogins_count'] = $a_cp[$cpzone]['freelogins_count'];
	$pconfig['freelogins_resettimeout'] = $a_cp[$cpzone]['freelogins_resettimeout'];
	$pconfig['freelogins_updatetimeouts'] = isset($a_cp[$cpzone]['freelogins_updatetimeouts']);
	$pconfig['enable'] = isset($a_cp[$cpzone]['enable']);
	$pconfig['auth_method'] = $a_cp[$cpzone]['auth_method'];
	$pconfig['localauth_priv'] = isset($a_cp[$cpzone]['localauth_priv']);
	$pconfig['radacct_enable'] = isset($a_cp[$cpzone]['radacct_enable']);
	$pconfig['radmac_enable'] = isset($a_cp[$cpzone]['radmac_enable']);
	$pconfig['radmac_secret'] = $a_cp[$cpzone]['radmac_secret'];
	$pconfig['reauthenticate'] = isset($a_cp[$cpzone]['reauthenticate']);
	$pconfig['reauthenticateacct'] = $a_cp[$cpzone]['reauthenticateacct'];
	$pconfig['httpslogin_enable'] = isset($a_cp[$cpzone]['httpslogin']);
	$pconfig['httpsname'] = $a_cp[$cpzone]['httpsname'];
	$pconfig['preauthurl'] = strtolower($a_cp[$cpzone]['preauthurl']);
	$pconfig['blockedmacsurl'] = strtolower($a_cp[$cpzone]['blockedmacsurl']);
	$pconfig['certref'] = $a_cp[$cpzone]['certref'];
	$pconfig['nohttpsforwards'] = isset($a_cp[$cpzone]['nohttpsforwards']);
	$pconfig['logoutwin_enable'] = isset($a_cp[$cpzone]['logoutwin_enable']);
	$pconfig['peruserbw'] = isset($a_cp[$cpzone]['peruserbw']);
	$pconfig['bwdefaultdn'] = $a_cp[$cpzone]['bwdefaultdn'];
	$pconfig['bwdefaultup'] = $a_cp[$cpzone]['bwdefaultup'];
	$pconfig['nomacfilter'] = isset($a_cp[$cpzone]['nomacfilter']);
	$pconfig['noconcurrentlogins'] = isset($a_cp[$cpzone]['noconcurrentlogins']);
	$pconfig['radius_protocol'] = $a_cp[$cpzone]['radius_protocol'];
	$pconfig['redirurl'] = $a_cp[$cpzone]['redirurl'];
	$pconfig['radiusip'] = $a_cp[$cpzone]['radiusip'];
	$pconfig['radiusip2'] = $a_cp[$cpzone]['radiusip2'];
	$pconfig['radiusip3'] = $a_cp[$cpzone]['radiusip3'];
	$pconfig['radiusip4'] = $a_cp[$cpzone]['radiusip4'];
	$pconfig['radiusport'] = $a_cp[$cpzone]['radiusport'];
	$pconfig['radiusport2'] = $a_cp[$cpzone]['radiusport2'];
	$pconfig['radiusport3'] = $a_cp[$cpzone]['radiusport3'];
	$pconfig['radiusport4'] = $a_cp[$cpzone]['radiusport4'];
	$pconfig['radiusacctport'] = $a_cp[$cpzone]['radiusacctport'];
	$pconfig['radiuskey'] = $a_cp[$cpzone]['radiuskey'];
	$pconfig['radiuskey2'] = $a_cp[$cpzone]['radiuskey2'];
	$pconfig['radiuskey3'] = $a_cp[$cpzone]['radiuskey3'];
	$pconfig['radiuskey4'] = $a_cp[$cpzone]['radiuskey4'];
	$pconfig['radiusvendor'] = $a_cp[$cpzone]['radiusvendor'];
	$pconfig['radiussession_timeout'] = isset($a_cp[$cpzone]['radiussession_timeout']);
	$pconfig['radiussrcip_attribute'] = $a_cp[$cpzone]['radiussrcip_attribute'];
	$pconfig['passthrumacadd'] = isset($a_cp[$cpzone]['passthrumacadd']);
	$pconfig['passthrumacaddusername'] = isset($a_cp[$cpzone]['passthrumacaddusername']);
	$pconfig['radmac_format'] = $a_cp[$cpzone]['radmac_format'];
	$pconfig['reverseacct'] = isset($a_cp[$cpzone]['reverseacct']);
	$pconfig['radiusnasid'] = $a_cp[$cpzone]['radiusnasid'];
	$pconfig['page'] = array();
	if ($a_cp[$cpzone]['page']['htmltext'])
		$pconfig['page']['htmltext'] = $a_cp[$cpzone]['page']['htmltext'];
	if ($a_cp[$cpzone]['page']['errtext'])
		$pconfig['page']['errtext'] = $a_cp[$cpzone]['page']['errtext'];
	if ($a_cp[$cpzone]['page']['logouttext'])
		$pconfig['page']['logouttext'] = $a_cp[$cpzone]['page']['logouttext'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['enable']) {
		$reqdfields = explode(" ", "zone cinterface");
		$reqdfieldsn = array(gettext("Zone name"), gettext("Interface"));

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

		/* make sure no interfaces are bridged or used on other zones */
		if (is_array($_POST['cinterface'])) {
			foreach ($pconfig['cinterface'] as $cpbrif) {
				if (link_interface_to_bridge($cpbrif))
					$input_errors[] = sprintf(gettext("The captive portal cannot be used on interface %s since it is part of a bridge."), $cpbrif);
				foreach ($a_cp as $cpkey => $cp) {
					if ($cpkey != $cpzone || empty($cpzone)) {
						if (in_array($cpbrif, explode(",", $cp['interface'])))
							$input_errors[] = sprintf(gettext("The captive portal cannot be used on interface %s since it is used already on %s instance."), $cpbrif, $cp['zone']);
					}
				}
			}
		}

		if ($_POST['httpslogin_enable']) {
			if (!$_POST['certref']) {
				$input_errors[] = gettext("Certificate must be specified for HTTPS login.");
			}
			if (!$_POST['httpsname'] || !is_domain($_POST['httpsname'])) {
				$input_errors[] = gettext("The HTTPS server name must be specified for HTTPS login.");
			}
		}
	}

	if ($_POST['timeout']) {
		if (!is_numeric($_POST['timeout']) || ($_POST['timeout'] < 1))
			$input_errors[] = gettext("The timeout must be at least 1 minute.");
		else if (isset($config['dhcpd']) && is_array($config['dhcpd'])) {
			foreach ($config['dhcpd'] as $dhcpd_if => $dhcpd_data) {
				if (!isset($dhcpd_data['enable']))
					continue;
				if (!is_array($_POST['cinterface']) || !in_array($dhcpd_if, $_POST['cinterface']))
					continue;

				$deftime = 7200; // Default lease time
				if (isset($dhcpd_data['defaultleasetime']) && is_numeric($dhcpd_data['defaultleasetime']))
					$deftime = $dhcpd_data['defaultleasetime'];

				if ($_POST['timeout'] > $deftime)
					$input_errors[] = gettext("Hard timeout must be less or equal Default lease time set on DHCP Server");
			}
		}
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
	if (($_POST['radiusip3'] && !is_ipaddr($_POST['radiusip3']))) {
		$input_errors[] = sprintf(gettext("A valid IP address must be specified. [%s]"), $_POST['radiusip3']);
	}
	if (($_POST['radiusip4'] && !is_ipaddr($_POST['radiusip4']))) {
		$input_errors[] = sprintf(gettext("A valid IP address must be specified. [%s]"), $_POST['radiusip4']);
	}
	if (($_POST['radiusport'] && !is_port($_POST['radiusport']))) {
		$input_errors[] = sprintf(gettext("A valid port number must be specified. [%s]"), $_POST['radiusport']);
	}
	if (($_POST['radiusport2'] && !is_port($_POST['radiusport2']))) {
		$input_errors[] = sprintf(gettext("A valid port number must be specified. [%s]"), $_POST['radiusport2']);
	}
	if (($_POST['radiusport3'] && !is_port($_POST['radiusport3']))) {
		$input_errors[] = sprintf(gettext("A valid port number must be specified. [%s]"), $_POST['radiusport3']);
	}
	if (($_POST['radiusport4'] && !is_port($_POST['radiusport4']))) {
		$input_errors[] = sprintf(gettext("A valid port number must be specified. [%s]"), $_POST['radiusport4']);
	}
	if (($_POST['radiusacctport'] && !is_port($_POST['radiusacctport']))) {
		$input_errors[] = sprintf(gettext("A valid port number must be specified. [%s]"), $_POST['radiusacctport']);
	}
	if ($_POST['maxproc'] && (!is_numeric($_POST['maxproc']) || ($_POST['maxproc'] < 4) || ($_POST['maxproc'] > 100))) {
		$input_errors[] = gettext("The maximum number of concurrent connections per client IP address may not be larger than the global maximum.");
	}
	if (trim($_POST['radiusnasid']) !== "" && !preg_match("/^[\x21-\x7e]{3,253}$/i", trim($_POST['radiusnasid']))) {
		$input_errors[] = gettext("The NAS-Identifier must be 3-253 characters long and should only contain ASCII characters.");
	}

	if (!$input_errors) {
		$newcp =& $a_cp[$cpzone];
		//$newcp['zoneid'] = $a_cp[$cpzone]['zoneid'];
		if (empty($newcp['zoneid'])) {
			$newcp['zoneid'] = 2;
			foreach ($a_cp as $keycpzone => $cp) {
				if ($cp['zoneid'] == $newcp['zoneid'] && $keycpzone != $cpzone)
					$newcp['zoneid'] += 2; /* Resreve space for SSL config if needed */
			}
			$cpzoneid = $newcp['zoneid'];
		}
		$oldifaces = explode(",", $newcp['interface']);
		if (is_array($_POST['cinterface']))
			$newcp['interface'] = implode(",", $_POST['cinterface']);
		$newcp['maxproc'] = $_POST['maxproc'];
		$newcp['maxprocperip'] = $_POST['maxprocperip'] ? $_POST['maxprocperip'] : false;
		$newcp['timeout'] = $_POST['timeout'];
		$newcp['idletimeout'] = $_POST['idletimeout'];
		$newcp['freelogins_count'] = $_POST['freelogins_count'];
		$newcp['freelogins_resettimeout'] = $_POST['freelogins_resettimeout'];
		$newcp['freelogins_updatetimeouts'] = $_POST['freelogins_updatetimeouts'] ? true : false;
		if ($_POST['enable'])
			$newcp['enable'] = true;
		else
			unset($newcp['enable']);
		$newcp['auth_method'] = $_POST['auth_method'];
		$newcp['localauth_priv'] = isset($_POST['localauth_priv']);
		$newcp['radacct_enable'] = $_POST['radacct_enable'] ? true : false;
		$newcp['reauthenticate'] = $_POST['reauthenticate'] ? true : false;
		$newcp['radmac_enable'] = $_POST['radmac_enable'] ? true : false;
		$newcp['radmac_secret'] = $_POST['radmac_secret'] ? $_POST['radmac_secret'] : false;
		$newcp['reauthenticateacct'] = $_POST['reauthenticateacct'];
		if ($_POST['httpslogin_enable'])
			$newcp['httpslogin'] = true;
		else
			unset($newcp['httpslogin']);
		$newcp['httpsname'] = $_POST['httpsname'];
		$newcp['preauthurl'] = $_POST['preauthurl'];
		$newcp['blockedmacsurl'] = $_POST['blockedmacsurl'];
		$newcp['peruserbw'] = $_POST['peruserbw'] ? true : false;
		$newcp['bwdefaultdn'] = $_POST['bwdefaultdn'];
		$newcp['bwdefaultup'] = $_POST['bwdefaultup'];
		$newcp['certref'] = $_POST['certref'];
		$newcp['nohttpsforwards'] = $_POST['nohttpsforwards'] ? true : false;
		$newcp['logoutwin_enable'] = $_POST['logoutwin_enable'] ? true : false;
		$newcp['nomacfilter'] = $_POST['nomacfilter'] ? true : false;
		$newcp['noconcurrentlogins'] = $_POST['noconcurrentlogins'] ? true : false;
		$newcp['radius_protocol'] = $_POST['radius_protocol'];
		$newcp['redirurl'] = $_POST['redirurl'];
		if (isset($_POST['radiusip']))
			$newcp['radiusip'] = $_POST['radiusip'];
		else
			unset($newcp['radiusip']);
		if (isset($_POST['radiusip2']))
			$newcp['radiusip2'] = $_POST['radiusip2'];
		else
			unset($newcp['radiusip2']);
		if (isset($_POST['radiusip3']))
			$newcp['radiusip3'] = $_POST['radiusip3'];
		else
			unset($newcp['radiusip3']);
		if (isset($_POST['radiusip4']))
			$newcp['radiusip4'] = $_POST['radiusip4'];
		else
			unset($newcp['radiusip4']);
		$newcp['radiusport'] = $_POST['radiusport'];
		$newcp['radiusport2'] = $_POST['radiusport2'];
		if (isset($_POST['radiusport3']))
			$newcp['radiusport3'] = $_POST['radiusport3'];
		if (isset($_POST['radiusport4']))
			$newcp['radiusport4'] = $_POST['radiusport4'];
		$newcp['radiusacctport'] = $_POST['radiusacctport'];
		$newcp['radiuskey'] = $_POST['radiuskey'];
		$newcp['radiuskey2'] = $_POST['radiuskey2'];
		$newcp['radiuskey3'] = $_POST['radiuskey3'];
		$newcp['radiuskey4'] = $_POST['radiuskey4'];
		$newcp['radiusvendor'] = $_POST['radiusvendor'] ? $_POST['radiusvendor'] : false;
		$newcp['radiussession_timeout'] = $_POST['radiussession_timeout'] ? true : false;
		$newcp['radiussrcip_attribute'] = $_POST['radiussrcip_attribute'];
		$newcp['passthrumacadd'] = $_POST['passthrumacadd'] ? true : false;
		$newcp['passthrumacaddusername'] = $_POST['passthrumacaddusername'] ? true : false;
		$newcp['radmac_format'] = $_POST['radmac_format'] ? $_POST['radmac_format'] : false;
		$newcp['reverseacct'] = $_POST['reverseacct'] ? true : false;
		$newcp['radiusnasid'] = trim($_POST['radiusnasid']);
		if (!is_array($newcp['page']))
			$newcp['page'] = array();

		/* file upload? */
		if (is_uploaded_file($_FILES['htmlfile']['tmp_name']))
			$newcp['page']['htmltext'] = base64_encode(file_get_contents($_FILES['htmlfile']['tmp_name']));
		if (is_uploaded_file($_FILES['errfile']['tmp_name']))
			$newcp['page']['errtext'] = base64_encode(file_get_contents($_FILES['errfile']['tmp_name']));
		if (is_uploaded_file($_FILES['logoutfile']['tmp_name']))
			$newcp['page']['logouttext'] = base64_encode(file_get_contents($_FILES['logoutfile']['tmp_name']));

		write_config();

		/* Clear up unselected interfaces */
		$newifaces = explode(",", $newcp['interface']);
		$toremove = array_diff($oldifaces, $newifaces);
		if (!empty($toremove)) {
			foreach ($toremove as $removeif) {
				$removeif = get_real_interface($removeif);
				mwexec("/sbin/ipfw zone {$cpzoneid} mdel {$removeif}");
			}
		}
		captiveportal_configure_zone($newcp);
		unset($newcp, $newifaces, $toremove);
		filter_configure();
		header("Location: services_captiveportal_zones.php");
		exit;
	} else {
		if (is_array($_POST['cinterface']))
			$pconfig['cinterface'] = implode(",", $_POST['cinterface']);
	}
}
$closehead = false;
include("head.inc");
?>
<script type="text/javascript">
//<![CDATA[
function enable_change(enable_change) {
	var endis, radius_endis;
	endis = !(document.iform.enable.checked || enable_change);
	localauth_endis = !((!endis && document.iform.auth_method[1].checked) || enable_change);
	radius_endis = !((!endis && document.iform.auth_method[2].checked) || enable_change);
	https_endis = !((!endis && document.iform.httpslogin_enable.checked) || enable_change);

	document.iform.cinterface.disabled = endis;
	//document.iform.maxproc.disabled = endis;
	document.iform.maxprocperip.disabled = endis;
	document.iform.idletimeout.disabled = endis;
	document.iform.freelogins_count.disabled = endis;
	document.iform.freelogins_resettimeout.disabled = endis;
	document.iform.freelogins_updatetimeouts.disabled = endis;
	document.iform.timeout.disabled = endis;
	document.iform.preauthurl.disabled = endis;
	document.iform.blockedmacsurl.disabled = endis;
	document.iform.redirurl.disabled = endis;
	document.iform.localauth_priv.disabled = localauth_endis;
	document.iform.radiusip.disabled = radius_endis;
	document.iform.radiusip2.disabled = radius_endis;
	document.iform.radiusip3.disabled = radius_endis;
	document.iform.radiusip4.disabled = radius_endis;
	document.iform.radiusport.disabled = radius_endis;
	document.iform.radiusport3.disabled = radius_endis;
	document.iform.radiusport4.disabled = radius_endis;
	document.iform.radiusport2.disabled = radius_endis;
	document.iform.radiuskey.disabled = radius_endis;
	document.iform.radiuskey2.disabled = radius_endis;
	document.iform.radiuskey3.disabled = radius_endis;
	document.iform.radiuskey4.disabled = radius_endis;
	document.iform.radacct_enable.disabled = radius_endis;
	document.iform.peruserbw.disabled = endis;
	document.iform.bwdefaultdn.disabled = endis;
	document.iform.bwdefaultup.disabled = endis;
	document.iform.reauthenticate.disabled = radius_endis;
	document.iform.auth_method[0].disabled = endis;
	document.iform.auth_method[1].disabled = endis;
	document.iform.auth_method[2].disabled = endis;
	document.iform.radius_protocol[0].disabled = radius_endis;
	document.iform.radius_protocol[1].disabled = radius_endis;
	document.iform.radius_protocol[2].disabled = radius_endis;
	document.iform.radius_protocol[3].disabled = radius_endis;
	document.iform.radmac_enable.disabled = radius_endis;
	document.iform.httpslogin_enable.disabled = endis;
	document.iform.radmac_format.disabled = radius_endis;
	document.iform.httpsname.disabled = https_endis;
	document.iform.certref.disabled = https_endis;
	document.iform.nohttpsforwards.disabled = https_endis;
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
	document.iform.reverseacct.disabled = (radius_endis || !document.iform.radacct_enable.checked) && !enable_change;
	document.iform.radiusnasid.disabled = radius_endis;
}
//]]>
</script>
</head>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<form action="services_captiveportal.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="captive portal">
  <tr><td class="tabnavtbl">
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("Captive portal(s)"), true, "services_captiveportal.php?zone={$cpzone}");
	$tab_array[] = array(gettext("MAC"), false, "services_captiveportal_mac.php?zone={$cpzone}");
	$tab_array[] = array(gettext("Allowed IP addresses"), false, "services_captiveportal_ip.php?zone={$cpzone}");
	$tab_array[] = array(gettext("Allowed Hostnames"), false, "services_captiveportal_hostname.php?zone={$cpzone}");
	$tab_array[] = array(gettext("Vouchers"), false, "services_captiveportal_vouchers.php?zone={$cpzone}");
	$tab_array[] = array(gettext("File Manager"), false, "services_captiveportal_filemanager.php?zone={$cpzone}");
	display_top_tabs($tab_array, true);
?>    </td></tr>
  <tr>
  <td class="tabcont">
  <table width="100%" border="0" cellpadding="6" cellspacing="0" summary="main">
	<tr>
	  <td width="22%" valign="top" class="vtable">&nbsp;</td>
	  <td width="78%" class="vtable">
		<input name="enable" type="checkbox" value="yes" <?php if ($pconfig['enable']) echo "checked=\"checked\""; ?> onclick="enable_change(false)" />
		<strong><?=gettext("Enable captive portal"); ?> </strong></td>
	</tr>
	<tr>
	  <td width="22%" valign="top" class="vncellreq"><?=gettext("Interfaces"); ?></td>
	  <td width="78%" class="vtable">
		<select name="cinterface[]" multiple="multiple" size="<?php echo count($config['interfaces']); ?>" class="formselect" id="cinterface">
		  <?php
		  $interfaces = get_configured_interface_with_descr();
		  $cselected = explode(",", $pconfig['cinterface']);
		  foreach ($interfaces as $iface => $ifacename): ?>
			  <option value="<?=$iface;?>" <?php if (in_array($iface, $cselected)) echo "selected=\"selected\""; ?>>
			  <?=htmlspecialchars($ifacename);?>
			  </option>
		  <?php endforeach; ?>
		</select> <br />
		<span class="vexpl"><?=gettext("Select the interface(s) to enable for captive portal."); ?></span></td>
	</tr>
	<tr>
	  <td valign="top" class="vncell"><?=gettext("Maximum concurrent connections"); ?></td>
	  <td class="vtable">
		<table cellpadding="0" cellspacing="0" summary="connections">
                 <tr>
           			<td><input name="maxprocperip" type="text" class="formfld unknown" id="maxprocperip" size="5" value="<?=htmlspecialchars($pconfig['maxprocperip']);?>" /> <?=gettext("per client IP address (0 = no limit)"); ?></td>
                 </tr>
               </table>
<?=gettext("This setting limits the number of concurrent connections to the captive portal HTTP(S) server. This does not set how many users can be logged in " .
"to the captive portal, but rather how many users can load the portal page or authenticate at the same time! " .
"Possible setting allowed is: minimum 4 connections per client IP address, with a total maximum of 100 connections."); ?></td>
	</tr>
	<tr>
	  <td valign="top" class="vncell"><?=gettext("Idle timeout"); ?></td>
	  <td class="vtable">
		<input name="idletimeout" type="text" class="formfld unknown" id="idletimeout" size="6" value="<?=htmlspecialchars($pconfig['idletimeout']);?>" />
<?=gettext("minutes"); ?><br />
<?=gettext("Clients will be disconnected after this amount of inactivity. They may log in again immediately, though. Leave this field blank for no idle timeout."); ?></td>
	</tr>
	<tr>
	  <td width="22%" valign="top" class="vncell"><?=gettext("Hard timeout"); ?></td>
	  <td width="78%" class="vtable">
		<input name="timeout" type="text" class="formfld unknown" id="timeout" size="6" value="<?=htmlspecialchars($pconfig['timeout']);?>" />
		<?=gettext("minutes"); ?><br />
	  <?=gettext("Clients will be disconnected after this amount of time, regardless of activity. They may log in again immediately, though. Leave this field blank for no hard timeout (not recommended unless an idle timeout is set)."); ?></td>
	</tr>
	<tr>
	  <td width="22%" valign="top" class="vncell"><?=gettext("Pass-through credits allowed per MAC address"); ?></td>
	  <td width="78%" class="vtable">
		<input name="freelogins_count" type="text" class="formfld unknown" id="freelogins_count" size="6" value="<?=htmlspecialchars($pconfig['freelogins_count']);?>" />
		<?=gettext("per client MAC address (0 or blank = none)"); ?><br />
		<?=gettext("This setting allows passing through the captive portal without authentication a limited number of times per MAC address. Once used up, the client can only log in with valid credentials until the waiting period specified below has expired. Recommended to set a hard timeout and/or idle timeout when using this for it to be effective."); ?></td>
	</tr>
	<tr>
	  <td width="22%" valign="top" class="vncell"><?=gettext("Waiting period to restore pass-through credits"); ?></td>
	  <td width="78%" class="vtable">
		<input name="freelogins_resettimeout" type="text" class="formfld unknown" id="freelogins_resettimeout" size="6" value="<?=htmlspecialchars($pconfig['freelogins_resettimeout']);?>" />
		<?=gettext("hours"); ?><br />
		<?=gettext("Clients will have their available pass-through credits restored to the original count after this amount of time since using the first one. This must be above 0 hours if pass-through credits are enabled."); ?></td>
	</tr>
	<tr>
	  <td width="22%" valign="top" class="vncell"><?=gettext("Reset waiting period on attempted access"); ?></td>
	  <td width="78%" class="vtable">
		<input name="freelogins_updatetimeouts" type="checkbox" class="formfld" id="freelogins_updatetimeouts" value="yes" <?php if($pconfig['freelogins_updatetimeouts']) echo "checked=\"checked\""; ?> />
		<strong><?=gettext("Enable waiting period reset on attempted access"); ?></strong><br />
		<?=gettext("If enabled, the waiting period is reset to the original duration if access is attempted when all pass-through credits have already been exhausted."); ?></td>
	</tr>
	<tr>
	  <td width="22%" valign="top" class="vncell"><?=gettext("Logout popup window"); ?></td>
	  <td width="78%" class="vtable">
		<input name="logoutwin_enable" type="checkbox" class="formfld" id="logoutwin_enable" value="yes" <?php if($pconfig['logoutwin_enable']) echo "checked=\"checked\""; ?> />
		<strong><?=gettext("Enable logout popup window"); ?></strong><br />
	  <?=gettext("If enabled, a popup window will appear when clients are allowed through the captive portal. This allows clients to explicitly disconnect themselves before the idle or hard timeout occurs."); ?></td>
	</tr>
	<tr>
      <td valign="top" class="vncell"><?=gettext("Pre-authentication redirect URL"); ?> </td>
      <td class="vtable">
        <input name="preauthurl" type="text" class="formfld url" id="preauthurl" size="60" value="<?=htmlspecialchars($pconfig['preauthurl']);?>" /><br />
		<?php printf(gettext("Use this field to set \$PORTAL_REDIRURL\$ variable which can be accessed using your custom captive portal index.php page or error pages."));?>
	  </td>
	</tr>
	<tr>
	  <td valign="top" class="vncell"><?=gettext("After authentication Redirection URL"); ?></td>
	  <td class="vtable">
		<input name="redirurl" type="text" class="formfld url" id="redirurl" size="60" value="<?=htmlspecialchars($pconfig['redirurl']);?>" />
		<br />
<?=gettext("If you provide a URL here, clients will be redirected to that URL instead of the one they initially tried " .
"to access after they've authenticated."); ?></td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?=gettext("Blocked MAC address redirect URL"); ?> </td>
		<td class="vtable">
			<input name="blockedmacsurl" type="text" class="formfld url" id="blockedmacsurl" size="60" value="<?=htmlspecialchars($pconfig['blockedmacsurl']);?>" /><br />
			<?php printf(gettext("If you provide a URL here, MAC addresses set to be blocked will be redirect to that URL when attempt to access anything."));?>
		</td>
	</tr>
	<tr>
      <td valign="top" class="vncell"><?=gettext("Concurrent user logins"); ?></td>
      <td class="vtable">
	<input name="noconcurrentlogins" type="checkbox" class="formfld" id="noconcurrentlogins" value="yes" <?php if ($pconfig['noconcurrentlogins']) echo "checked=\"checked\""; ?> />
	<strong><?=gettext("Disable concurrent logins"); ?></strong><br />
	<?=gettext("If this option is set, only the most recent login per username will be active. Subsequent logins will cause machines previously logged in with the same username to be disconnected."); ?></td>
	</tr>
	<tr>
      <td valign="top" class="vncell"><?=gettext("MAC filtering"); ?> </td>
      <td class="vtable">
        <input name="nomacfilter" type="checkbox" class="formfld" id="nomacfilter" value="yes" <?php if ($pconfig['nomacfilter']) echo "checked=\"checked\""; ?> />
        <strong><?=gettext("Disable MAC filtering"); ?></strong><br />
    <?=gettext("If this option is set, no attempts will be made to ensure that the MAC address of clients stays the same while they're logged in." .
    "This is required when the MAC address of the client cannot be determined (usually because there are routers between"); ?> <?php echo $g['product_name'] ?> <?=gettext("and the clients)."); ?>
    <?=gettext("If this is enabled, RADIUS MAC authentication cannot be used."); ?></td>
	</tr>
	<tr>
      <td valign="top" class="vncell"><?=gettext("Pass-through MAC Auto Entry"); ?></td>
      <td class="vtable">
        <input name="passthrumacadd" type="checkbox" class="formfld" id="passthrumacadd" value="yes" <?php if ($pconfig['passthrumacadd']) echo "checked=\"checked\""; ?> />
        <strong><?=gettext("Enable Pass-through MAC automatic additions"); ?></strong><br />
    <?=gettext("If this option is set, a MAC passthrough entry is automatically added after the user has successfully authenticated. Users of that MAC address will never have to authenticate again."); ?>
    <?=gettext("To remove the passthrough MAC entry you either have to log in and remove it manually from the"); ?> <a href="services_captiveportal_mac.php"><?=gettext("MAC tab"); ?></a> <?=gettext("or send a POST from another system to remove it."); ?>
    <?=gettext("If this is enabled, RADIUS MAC authentication cannot be used. Also, the logout window will not be shown."); ?>
	<br /><br />
        <input name="passthrumacaddusername" type="checkbox" class="formfld" id="passthrumacaddusername" value="yes" <?php if ($pconfig['passthrumacaddusername']) echo "checked=\"checked\""; ?> />
        <strong><?=gettext("Enable Pass-through MAC automatic addition with username"); ?></strong><br />
    <?=gettext("If this option is set, with the automatically MAC passthrough entry created the username, used during authentication, will be saved."); ?>
    <?=gettext("To remove the passthrough MAC entry you either have to log in and remove it manually from the"); ?> <a href="services_captiveportal_mac.php"><?=gettext("MAC tab"); ?></a> <?=gettext("or send a POST from another system to remove it."); ?>
	</td>
	</tr>
	<tr>
      <td valign="top" class="vncell"><?=gettext("Per-user bandwidth restriction"); ?></td>
      <td class="vtable">
        <input name="peruserbw" type="checkbox" class="formfld" id="peruserbw" value="yes" <?php if ($pconfig['peruserbw']) echo "checked=\"checked\""; ?> />
        <strong><?=gettext("Enable per-user bandwidth restriction"); ?></strong><br /><br />
        <table cellpadding="0" cellspacing="0" summary="bandwidth">
        <tr>
        <td><?=gettext("Default download"); ?></td>
        <td><input type="text" class="formfld unknown" name="bwdefaultdn" id="bwdefaultdn" size="10" value="<?=htmlspecialchars($pconfig['bwdefaultdn']);?>" /> <?=gettext("Kbit/s"); ?></td>
        </tr>
        <tr>
        <td><?=gettext("Default upload"); ?></td>
        <td><input type="text" class="formfld unknown" name="bwdefaultup" id="bwdefaultup" size="10" value="<?=htmlspecialchars($pconfig['bwdefaultup']);?>" /> <?=gettext("Kbit/s"); ?></td>
        </tr></table>
        <br />
        <?=gettext("If this option is set, the captive portal will restrict each user who logs in to the specified default bandwidth. RADIUS can override the default settings. Leave empty or set to 0 for no limit."); ?> </td>
	</tr>
	<tr>
	  <td width="22%" valign="top" class="vncell"><?=gettext("Authentication"); ?></td>
	  <td width="78%" class="vtable">
		<table cellpadding="0" cellspacing="0" summary="authentication">
		<tr>
		  <td colspan="2"><input name="auth_method" type="radio" id="auth_method" value="none" onclick="enable_change(false)" <?php if($pconfig['auth_method']!="local" && $pconfig['auth_method']!="radius") echo "checked=\"checked\""; ?> />
  <?=gettext("No Authentication"); ?></td>
		</tr>
		<tr>
		  <td colspan="2"><input name="auth_method" type="radio" id="auth_method" value="local" onclick="enable_change(false)" <?php if($pconfig['auth_method']=="local") echo "checked=\"checked\""; ?> />
  <?=gettext("Local"); ?> <a href="system_usermanager.php"><?=gettext("User Manager"); ?></a> / <?=gettext("Vouchers"); ?></td>
		</tr>
		<tr>
		  <td>&nbsp;</td>
		  <td>&nbsp;</td>
		</tr>
		<tr>
		  <td>&nbsp;</td>
		  <td><input name="localauth_priv" type="checkbox" id="localauth_priv" value="yes" onclick="enable_change(false)" <?php if($pconfig['localauth_priv']=="yes") echo "checked=\"checked\""; ?> />
  <?=gettext("Allow only users/groups with 'Captive portal login' privilege set"); ?></td>
		</tr><tr>
		  <td colspan="2"><input name="auth_method" type="radio" id="auth_method" value="radius" onclick="enable_change(false)" <?php if($pconfig['auth_method']=="radius") echo "checked=\"checked\""; ?> />
  <?=gettext("RADIUS Authentication"); ?></td>
		</tr><tr>
		  <td>&nbsp;</td>
		  <td>&nbsp;</td>
                </tr>
<tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("Radius Protocol"); ?></td>
                  <td width="78%" class="vtable">
                    <table cellpadding="0" cellspacing="0" summary="radius">
                    <tr>
                      <td colspan="2"><input name="radius_protocol" type="radio" id="radius_protocol" value="PAP" onclick="enable_change(false)" <?php if($pconfig['auth_method']=="radius" && $pconfig['radius_protocol']!="CHAP_MD5" && $pconfig['radius_protocol']!="MSCHAPv1" && $pconfig['radius_protocol']!="MSCHAPv2") echo "checked=\"checked\""; ?> />
      <?=gettext("PAP"); ?></td>
                      </tr>
                    <tr>
                      <td colspan="2"><input name="radius_protocol" type="radio" id="radius_protocol" value="CHAP_MD5" onclick="enable_change(false)" <?php if($pconfig['auth_method']=="radius" && $pconfig['radius_protocol']=="CHAP_MD5") echo "checked=\"checked\""; ?> />
      <?=gettext("CHAP_MD5"); ?></td>
                      </tr>
                    <tr>
                      <td colspan="2"><input name="radius_protocol" type="radio" id="radius_protocol" value="MSCHAPv1" onclick="enable_change(false)" <?php if($pconfig['auth_method']=="radius" && $pconfig['radius_protocol']=="MSCHAPv1") echo "checked=\"checked\""; ?> />
      <?=gettext("MSCHAPv1"); ?></td>
                      </tr>
                    <tr>
                      <td colspan="2"><input name="radius_protocol" type="radio" id="radius_protocol" value="MSCHAPv2" onclick="enable_change(false)" <?php if($pconfig['auth_method']=="radius" && $pconfig['radius_protocol']=="MSCHAPv2") echo "checked=\"checked\""; ?> />
      <?=gettext("MSCHAPv2"); ?></td>
                      </tr><tr>
                      <td>&nbsp;</td>
                      <td>&nbsp;</td>
                      </tr>
                    </table>
                  </td>
                  </tr><tr>
                  <td>&nbsp;</td>
                  <td>&nbsp;</td>
                  </tr>
                </table>
		<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="primary athentication">
			<tr>
				<td colspan="2" valign="top" class="listtopic">Primary Authentication Source</td>
			</tr>
			<tr>
				<td colspan="2" valign="top" class="optsect_t2"><?=gettext("Primary RADIUS server"); ?></td>
			</tr>
			<tr>
				<td class="vncell" valign="top"><?=gettext("IP address"); ?></td>
				<td class="vtable"><input name="radiusip" type="text" class="formfld unknown" id="radiusip" size="20" value="<?=htmlspecialchars($pconfig['radiusip']);?>" /><br />
				<?=gettext("Enter the IP address of the RADIUS server which users of the captive portal have to authenticate against."); ?></td>
			</tr>
			<tr>
				<td class="vncell" valign="top"><?=gettext("Port"); ?></td>
				<td class="vtable"><input name="radiusport" type="text" class="formfld unknown" id="radiusport" size="5" value="<?=htmlspecialchars($pconfig['radiusport']);?>" /><br />
				 <?=gettext("Leave this field blank to use the default port (1812)."); ?></td>
			</tr>
			<tr>
				<td class="vncell" valign="top"><?=gettext("Shared secret"); ?>&nbsp;&nbsp;</td>
				<td class="vtable"><input name="radiuskey" type="text" class="formfld unknown" id="radiuskey" size="16" value="<?=htmlspecialchars($pconfig['radiuskey']);?>" /><br />
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
				<td class="vtable"><input name="radiusip2" type="text" class="formfld unknown" id="radiusip2" size="20" value="<?=htmlspecialchars($pconfig['radiusip2']);?>" /><br />
				<?=gettext("If you have a second RADIUS server, you can activate it by entering its IP address here."); ?></td>
			</tr>
			<tr>
				<td class="vncell" valign="top"><?=gettext("Port"); ?></td>
				<td class="vtable"><input name="radiusport2" type="text" class="formfld unknown" id="radiusport2" size="5" value="<?=htmlspecialchars($pconfig['radiusport2']);?>" /></td>
			</tr>
			<tr>
				<td class="vncell" valign="top"><?=gettext("Shared secret"); ?>&nbsp;&nbsp;</td>
				<td class="vtable"><input name="radiuskey2" type="text" class="formfld unknown" id="radiuskey2" size="16" value="<?=htmlspecialchars($pconfig['radiuskey2']);?>" /></td>
			</tr>
			<tr>
			  <td colspan="2" class="list" height="12"></td>
			</tr>
			<tr>
				<td colspan="2" valign="top" class="listtopic">Secondary Authentication Source</td>
			</tr>
			<tr>
				<td colspan="2" valign="top" class="optsect_t2"><?=gettext("Primary RADIUS server"); ?></td>
			</tr>
			<tr>
				<td class="vncell" valign="top"><?=gettext("IP address"); ?></td>
				<td class="vtable"><input name="radiusip3" type="text" class="formfld unknown" id="radiusip3" size="20" value="<?=htmlspecialchars($pconfig['radiusip3']);?>" /><br />
				<?=gettext("If you have a third RADIUS server, you can activate it by entering its IP address here."); ?></td>
			</tr>
			<tr>
				<td class="vncell" valign="top"><?=gettext("Port"); ?></td>
				<td class="vtable"><input name="radiusport3" type="text" class="formfld unknown" id="radiusport3" size="5" value="<?=htmlspecialchars($pconfig['radiusport3']);?>" /></td>
			</tr>
			<tr>
				<td class="vncell" valign="top"><?=gettext("Shared secret"); ?>&nbsp;&nbsp;</td>
				<td class="vtable"><input name="radiuskey3" type="text" class="formfld unknown" id="radiuskey3" size="16" value="<?=htmlspecialchars($pconfig['radiuskey3']);?>" /></td>
			</tr>
			<tr>
			  <td colspan="2" class="list" height="12"></td>
			</tr>
			<tr>
				<td colspan="2" valign="top" class="optsect_t2"><?=gettext("Secondary RADIUS server"); ?></td>
			</tr>
			<tr>
				<td class="vncell" valign="top"><?=gettext("IP address"); ?></td>
				<td class="vtable"><input name="radiusip4" type="text" class="formfld unknown" id="radiusip4" size="20" value="<?=htmlspecialchars($pconfig['radiusip4']);?>" /><br />
				<?=gettext("If you have a fourth RADIUS server, you can activate it by entering its IP address here."); ?></td>
			</tr>
			<tr>
				<td class="vncell" valign="top"><?=gettext("Port"); ?></td>
				<td class="vtable"><input name="radiusport4" type="text" class="formfld unknown" id="radiusport4" size="5" value="<?=htmlspecialchars($pconfig['radiusport4']);?>" /></td>
			</tr>
			<tr>
				<td class="vncell" valign="top"><?=gettext("Shared secret"); ?>&nbsp;&nbsp;</td>
				<td class="vtable"><input name="radiuskey4" type="text" class="formfld unknown" id="radiuskey4" size="16" value="<?=htmlspecialchars($pconfig['radiuskey4']);?>" /></td>
			</tr>
			<tr>
			  <td colspan="2" class="list" height="12"></td>
			</tr>
			<tr>
				<td colspan="2" valign="top" class="listtopic"><?=gettext("Accounting"); ?></td>
			</tr>
			<tr>
				<td class="vncell">&nbsp;</td>
				<td class="vtable"><input name="radacct_enable" type="checkbox" id="radacct_enable" value="yes" onclick="enable_change(false)" <?php if($pconfig['radacct_enable']) echo "checked=\"checked\""; ?> />
				<strong><?=gettext("send RADIUS accounting packets"); ?></strong><br />
				<?=gettext("If this is enabled, RADIUS accounting packets will be sent to the primary RADIUS server."); ?></td>
			</tr>
			<tr>
			  <td class="vncell" valign="top"><?=gettext("Accounting port"); ?></td>
			  <td class="vtable"><input name="radiusacctport" type="text" class="formfld unknown" id="radiusacctport" size="5" value="<?=htmlspecialchars($pconfig['radiusacctport']);?>" /><br />
			  <?=gettext("Leave blank to use the default port (1813)."); ?></td>
			  </tr>
			<tr>
			  <td colspan="2" class="list" height="12"></td>
			</tr>
			<tr>
			  <td class="vncell" valign="top"><?=gettext("Accounting updates"); ?></td>
			  <td class="vtable">
			  <input name="reauthenticateacct" type="radio" value="" <?php if(!$pconfig['reauthenticateacct']) echo "checked=\"checked\""; ?> /> <?=gettext("no accounting updates"); ?><br />
			  <input name="reauthenticateacct" type="radio" value="stopstart" <?php if($pconfig['reauthenticateacct'] == "stopstart") echo "checked=\"checked\""; ?> /> <?=gettext("stop/start accounting"); ?><br />
			  <input name="reauthenticateacct" type="radio" value="interimupdate" <?php if($pconfig['reauthenticateacct'] == "interimupdate") echo "checked=\"checked\""; ?> /> <?=gettext("interim update"); ?>
			  </td>
			</tr>
			<tr>
			  <td colspan="2" class="list" height="12"></td>
			</tr>
			<tr>
				<td colspan="2" valign="top" class="listtopic"><?=gettext("RADIUS options"); ?></td>
			</tr>
			<tr>
				<td class="vncell"><?=gettext("Reauthentication"); ?></td>
				<td class="vtable"><input name="reauthenticate" type="checkbox" id="reauthenticate" value="yes" onclick="enable_change(false)" <?php if($pconfig['reauthenticate']) echo "checked=\"checked\""; ?> />
				<strong><?=gettext("Reauthenticate connected users every minute"); ?></strong><br />
				<?=gettext("If reauthentication is enabled, Access-Requests will be sent to the RADIUS server for each user that is " .
				"logged in every minute. If an Access-Reject is received for a user, that user is disconnected from the captive portal immediately."); ?></td>
			</tr>
			<tr>
				<td class=""><?=gettext("RADIUS MAC authentication"); ?></td>
				<td class="">
				<input name="radmac_enable" type="checkbox" id="radmac_enable" value="yes" onclick="enable_change(false)" <?php if ($pconfig['radmac_enable']) echo "checked=\"checked\""; ?> /><strong><?=gettext("Enable RADIUS MAC authentication"); ?></strong><br />
				<?=gettext("If this option is enabled, the captive portal will try to authenticate users by sending their MAC address as the username and the password " .
				"entered below to the RADIUS server."); ?></td>
			</tr>
			<tr>
				<td class="vncell"><?=gettext("MAC authentication secret"); ?></td>
				<td class="vtable"><input name="radmac_secret" type="text" class="formfld unknown" id="radmac_secret" size="16" value="<?=htmlspecialchars($pconfig['radmac_secret']);?>" /></td>
			</tr>
			<tr>
				<td class="vncell" valign="top"><?=gettext("RADIUS NAS IP attribute"); ?></td>
				<td class="vtable">
				<select name="radiussrcip_attribute" id="radiussrcip_attribute">
				<?php $iflist = get_configured_interface_with_descr();
					foreach ($iflist as $ifdesc => $ifdescr) {
						$ipaddr = get_interface_ip($ifdesc);
						if (is_ipaddr($ipaddr)) {
							$selected = "";
							if ($ifdesc == $pconfig['radiussrcip_attribute'])
								$selected= "selected=\"selected\"";
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
				</select><br />
				<?=gettext("Choose the IP to use for calling station attribute."); ?>
				</td>
			</tr>

			<tr>
				<td class="vncell" valign="top"><?=gettext("Session-Timeout"); ?></td>
				<td class="vtable"><input name="radiussession_timeout" type="checkbox" id="radiussession_timeout" value="yes" <?php if ($pconfig['radiussession_timeout']) echo "checked=\"checked\""; ?> /><strong><?=gettext("Use RADIUS Session-Timeout attributes"); ?></strong><br />
				<?=gettext("When this is enabled, clients will be disconnected after the amount of time retrieved from the RADIUS Session-Timeout attribute."); ?></td>
			</tr>

			<tr>
				<td class="vncell" valign="top"><?=gettext("Type"); ?></td>
				<td class="vtable"><select name="radiusvendor" id="radiusvendor">
				<option value="default"><?php echo gettext("default"); ?></option>
				<?php
				$radiusvendors = array("cisco");
				foreach ($radiusvendors as $radiusvendor){
					if ($pconfig['radiusvendor'] == $radiusvendor)
						echo "<option selected=\"selected\" value=\"$radiusvendor\">$radiusvendor</option>\n";
					else
						echo "<option value=\"$radiusvendor\">$radiusvendor</option>\n";
				}
				?></select><br />
				<?php printf(gettext("If RADIUS type is set to Cisco, in Access-Requests the value of Calling-Station-Id will be set to the client's IP address and " .
				"the Called-Station-Id to the client's MAC address. Default behavior is Calling-Station-Id = client's MAC address and Called-Station-Id = %s's WAN IP address."),
					$g['product_name']);?></td>
			</tr>

			<tr>
				<td class="vncell" valign="top"><?=gettext("Accounting Style"); ?></td>
				<td class="vtable"><input name="reverseacct" type="checkbox" id="reverseacct" value="yes" <?php if ($pconfig['reverseacct']) echo "checked=\"checked\""; ?> /><strong><?=gettext("Invert Acct-Input-Octets and Acct-Output-Octets"); ?></strong><br />
				<?=gettext("When this is enabled, data counts for RADIUS accounting packets will be taken from the client perspective, not the NAS. Acct-Input-Octets will represent download, and Acct-Output-Octets will represent upload."); ?></td>
			</tr>

			<tr>
				<td class="vncell" valign="top"><?=gettext("NAS Identifier"); ?></td>
				<td class="vtable"><input name="radiusnasid" type="text" maxlength="253" class="formfld unknown" id="radiusnasid" value="<?=htmlspecialchars($pconfig['radiusnasid']);?>" /><br />
					<?=gettext("Specify a NAS identifier to override the default value") . " (" . php_uname("n") . ")"; ?></td>
			</tr>
			<tr>
				<td class="vncell" valign="top"><?=gettext("MAC address format"); ?></td>
				<td class="vtable">
					<select name="radmac_format" id="radmac_format">
						<option value="default"><?php echo gettext("default"); ?></option>
						<?php
						$macformats = array("singledash","ietf","cisco","unformatted");
						foreach ($macformats as $macformat) {
							if ($pconfig['radmac_format'] == $macformat) {
								echo "<option selected=\"selected\" value=\"$macformat\">",gettext($macformat),"</option>\n";
							} else {
								echo "<option value=\"$macformat\">",gettext($macformat),"</option>\n";
							}
						}
						?>
					</select><br />
					<?=gettext("This option changes the MAC address format used in the whole RADIUS system. Change this if you also"); ?>
					<?=gettext("need to change the username format for RADIUS MAC authentication."); ?><br />
					<?=gettext("default:"); ?> 00:11:22:33:44:55<br />
					<?=gettext("singledash:"); ?> 001122-334455<br />
					<?=gettext("ietf:"); ?> 00-11-22-33-44-55<br />
					<?=gettext("cisco:"); ?> 0011.2233.4455<br />
					<?=gettext("unformatted:"); ?> 001122334455
				</td>
			</tr>
		</table>
		</td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?=gettext("HTTPS login"); ?></td>
		<td class="vtable">
			<input name="httpslogin_enable" type="checkbox" class="formfld" id="httpslogin_enable" value="yes" onclick="enable_change(false)" <?php if($pconfig['httpslogin_enable']) echo "checked=\"checked\""; ?> />
			<strong><?=gettext("Enable HTTPS login"); ?></strong><br />
			<?=gettext("If enabled, the username and password will be transmitted over an HTTPS connection to protect against eavesdroppers. A server name and certificate must also be specified below."); ?></td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?=gettext("HTTPS server name"); ?> </td>
		<td class="vtable">
			<input name="httpsname" type="text" class="formfld unknown" id="httpsname" size="30" value="<?=htmlspecialchars($pconfig['httpsname']);?>" /><br />
			<?php printf(gettext("This name will be used in the form action for the HTTPS POST and should match the Common Name (CN) in your certificate (otherwise, the client browser will most likely display a security warning). Make sure captive portal clients can resolve this name in DNS and verify on the client that the IP resolves to the correct interface IP on %s."), $g['product_name']);?> </td>
	</tr>
	<tr id="ssl_opts">
		<td width="22%" valign="top" class="vncell"><?=gettext("SSL Certificate"); ?></td>
		<td width="78%" class="vtable">
			<?php if (count($a_cert)): ?>
			<select name="certref" id="certref" class="formselect">
				<?php
					foreach($a_cert as $cert):
						$selected = "";
						if ($pconfig['certref'] == $cert['refid'])
							$selected = " selected=\"selected\"";
				?>
				<option value="<?=$cert['refid'];?>"<?=$selected;?>><?=$cert['descr'];?></option>
			<?php endforeach; ?>
			</select>
			<?php else: ?>
				<b><?=gettext("No Certificates defined."); ?></b> <br />Create one under <a href="system_certmanager.php">System &gt; Cert Manager</a>.
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?=gettext("Disable HTTPS forwards"); ?></td>
		<td class="vtable">
			<input name="nohttpsforwards" type="checkbox" class="formfld" id="nohttpsforwards" value="yes" <?php if ($pconfig['nohttpsforwards']) echo "checked=\"checked\""; ?> />
			<strong><?=gettext("Disable HTTPS forwards"); ?></strong><br/>
			<?=gettext("If this option is set, attempts to connect to SSL/HTTPS (Port 443) sites will not be forwarded to the captive portal.  This prevents certificate errors from being presented to the user even if HTTPS logins are enabled.  Users must attempt a connecton to an HTTP (Port 80) site to get forwarded to the captive portal. If HTTPS logins are enabled, the user will be redirected to the HTTPS login page."); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?=gettext("Portal page contents"); ?></td>
		<td width="78%" class="vtable">
		<?=$mandfldhtml;?><input type="file" name="htmlfile" class="formfld file" id="htmlfile" /><br />
		<?php
			list($host) = explode(":", $_SERVER['HTTP_HOST']);
			$zoneid = $pconfig['zoneid'] ? $pconfig['zoneid'] : 8000;
			if ($pconfig['httpslogin_enable']) {
				$port = $pconfig['listenporthttps'] ? $pconfig['listenporthttps'] : ($zoneid + 1);
				$href = "https://{$host}:{$port}";
			} else {
				$port = $pconfig['listenporthttp']  ? $pconfig['listenporthttp']  : $zoneid;
				$href = "http://{$host}:{$port}";
			}
		?>
		<?php if ($pconfig['page']['htmltext']): ?>
		<a href="<?=$href?>" target="_blank"><?=gettext("View current page"); ?></a>
		<br />
		<a href="?zone=<?=$cpzone?>&amp;act=gethtmlhtml" target="_blank"><?=gettext("Download current page"); ?></a>
		<br />
		<a href="?zone=<?=$cpzone?>&amp;act=delhtmlhtml" onclick="return confirm('Do you really want to restore default page?')" target="_blank">
			<?=gettext("Restore default portal page"); ?>
		</a>
		  <br />
		  <br />
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
			<?=gettext("Example code for the form:"); ?><br />
		  <br />
		  <tt>&lt;form method=&quot;post&quot; action=&quot;$PORTAL_ACTION$&quot;&gt;<br />
		  &nbsp;&nbsp;&nbsp;&lt;input name=&quot;auth_user&quot; type=&quot;text&quot;&gt;<br />
		  &nbsp;&nbsp;&nbsp;&lt;input name=&quot;auth_pass&quot; type=&quot;password&quot;&gt;<br />
		  &nbsp;&nbsp;&nbsp;&lt;input name=&quot;auth_voucher&quot; type=&quot;text&quot;&gt;<br />
		  &nbsp;&nbsp;&nbsp;&lt;input name=&quot;redirurl&quot; type=&quot;hidden&quot; value=&quot;$PORTAL_REDIRURL$&quot;&gt;<br />
&nbsp;&nbsp;&nbsp;&lt;input name=&quot;accept&quot; type=&quot;submit&quot; value=&quot;Continue&quot;&gt;<br />
		  &lt;/form&gt;</tt></td>
	</tr>
	<tr>
	  <td width="22%" valign="top" class="vncell"><?=gettext("Authentication"); ?><br />
		<?=gettext("error page"); ?><br />
		<?=gettext("contents"); ?></td>
	  <td class="vtable">
		<input name="errfile" type="file" class="formfld file" id="errfile" /><br />
		<?php if ($pconfig['page']['errtext']): ?>
		<a href="?zone=<?=$cpzone?>&amp;act=viewerrhtml" target="_blank"><?=gettext("View current page"); ?></a>
		<br />
		<a href="?zone=<?=$cpzone?>&amp;act=geterrhtml" target="_blank"><?=gettext("Download current page"); ?></a>
		<br />
		<a href="?zone=<?=$cpzone?>&amp;act=delerrhtml" onclick="return confirm('Do you really want to restore default page?')" target="_blank">
			<?=gettext("Restore default error page"); ?>
		</a>
		  <br />
		  <br />
		<?php endif; ?>
<?=gettext("The contents of the HTML/PHP file that you upload here are displayed when an authentication error occurs. " .
"You may include"); ?> &quot;$PORTAL_MESSAGE$&quot;, <?=gettext("which will be replaced by the error or reply messages from the RADIUS server, if any."); ?></td>
	</tr>
	<tr>
	  <td width="22%" valign="top" class="vncell"><?=gettext("Logout"); ?><br />
		<?=gettext("page"); ?><br />
		<?=gettext("contents"); ?></td>
	  <td class="vtable">
		<input name="logoutfile" type="file" class="formfld file" id="logoutfile" /><br />
		<?php if ($pconfig['page']['logouttext']): ?>
		<a href="?zone=<?=$cpzone?>&amp;act=viewlogouthtml" target="_blank"><?=gettext("View current page"); ?></a>
		<br />
		<a href="?zone=<?=$cpzone?>&amp;act=getlogouthtml" target="_blank"><?=gettext("Download current page"); ?></a>
		<br />
		<a href="?zone=<?=$cpzone?>&amp;act=dellogouthtml" onclick="return confirm('Do you really want to restore default page?')" target="_blank">
			<?=gettext("Restore default logout page"); ?>
		</a>
		  <br />
		  <br />
		<?php endif; ?>
<?=gettext("The contents of the HTML/PHP file that you upload here are displayed on authentication success when the logout popup is enabled."); ?></td>
	</tr>
	<tr>
	  <td width="22%" valign="top">&nbsp;</td>
	  <td width="78%">
		<?php echo "<input name='zone' id='zone' type='hidden' value='" . htmlspecialchars($cpzone) . "' />"; ?>
		<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" onclick="enable_change(true)" />
		<a href="services_captiveportal_zones.php"><input name="Cancel" type="button" class="formbtn" value="<?=gettext("Cancel"); ?>" onclick="enable_change(true)" /></a>
	  </td>
	</tr>
	<tr>
	  <td width="22%" valign="top">&nbsp;</td>
	  <td width="78%"><span class="vexpl"><span class="red"><strong><?=gettext("Note:"); ?><br />
		</strong></span><?=gettext("Changing any settings on this page will disconnect all clients! Don't forget to enable the DHCP server on your captive portal interface! Make sure that the default/maximum DHCP lease time is higher than the timeout entered on this page. Also, the DNS forwarder needs to be enabled for DNS lookups by unauthenticated clients to work."); ?> </span></td>
	</tr>
  </table>
  </td>
  </tr>
  </table>
</form>
<script type="text/javascript">
//<![CDATA[
enable_change(false);
//]]>
</script>
<?php include("fend.inc"); ?>
</body>
</html>
