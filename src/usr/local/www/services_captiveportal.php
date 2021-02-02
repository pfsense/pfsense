<?php
/*
 * services_captiveportal.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-services-captiveportal
##|*NAME=Services: Captive Portal
##|*DESCR=Allow access to the 'Services: Captive Portal' page.
##|*MATCH=services_captiveportal.php*
##|-PRIV

require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("captiveportal.inc");

if (substr($_REQUEST['act'], 0, 3) == "get") {
	$nocsrf = true;
}

require_once("guiconfig.inc");

global $cpzone;
global $cpzoneid;

$concurrentlogins_list = array("disabled" => "Disabled", "multiple" => "Multiple", "last" => "Last login", "first" => "First login");
$cpzoneid = 1; /* Just a default */
$cpzone = $_REQUEST['zone'];

$cpzone = strtolower($cpzone);

if (empty($cpzone) || empty($config['captiveportal'][$cpzone])) {
	header("Location: services_captiveportal_zones.php");
	exit;
}

init_config_arr(array('captiveportal'));
$a_cp = &$config['captiveportal'];

$pgtitle = array(gettext("Services"), gettext("Captive Portal"), $a_cp[$cpzone]['zone'], gettext("Configuration"));
$pglinks = array("", "services_captiveportal_zones.php", "@self", "@self");
$shortcut_section = "captiveportal";

if ($_REQUEST['act'] == "viewhtml") {
	if ($a_cp[$cpzone] && $a_cp[$cpzone]['page']['htmltext']) {
		echo base64_decode($a_cp[$cpzone]['page']['htmltext']);
	}
	exit;
} else if ($_REQUEST['act'] == "gethtmlhtml" && $a_cp[$cpzone] && $a_cp[$cpzone]['page']['htmltext']) {
	send_user_download('data', base64_decode($a_cp[$cpzone]['page']['htmltext']), "portal.html", "text/html");
} else if ($_REQUEST['act'] == "delhtmlhtml" && $a_cp[$cpzone] && $a_cp[$cpzone]['page']['htmltext']) {
	unset($a_cp[$cpzone]['page']['htmltext']);
	write_config(sprintf(gettext("Captive Portal: zone %s: Restore default portal page"), $cpzone));
	header("Location: services_captiveportal.php?zone={$cpzone}");
	exit;
} else if ($_REQUEST['act'] == "viewerrhtml") {
	if ($a_cp[$cpzone] && $a_cp[$cpzone]['page']['errtext']) {
		echo base64_decode($a_cp[$cpzone]['page']['errtext']);
	}
	exit;
} else if ($_REQUEST['act'] == "geterrhtml" && $a_cp[$cpzone] && $a_cp[$cpzone]['page']['errtext']) {
	send_user_download('data', base64_decode($a_cp[$cpzone]['page']['errtext']), "err.html", "text/html");
} else if ($_REQUEST['act'] == "delerrhtml" && $a_cp[$cpzone] && $a_cp[$cpzone]['page']['errtext']) {
	unset($a_cp[$cpzone]['page']['errtext']);
	write_config(sprintf(gettext("Captive Portal: zone %s: Restore default error page"), $cpzone));
	header("Location: services_captiveportal.php?zone={$cpzone}");
	exit;
} else if ($_REQUEST['act'] == "viewlogouthtml") {
	if ($a_cp[$cpzone] && $a_cp[$cpzone]['page']['logouttext']) {
		echo base64_decode($a_cp[$cpzone]['page']['logouttext']);
	}
	exit;
} else if ($_REQUEST['act'] == "getlogouthtml" && $a_cp[$cpzone] && $a_cp[$cpzone]['page']['logouttext']) {
	send_user_download('data', base64_decode($a_cp[$cpzone]['page']['logouttext']), "logout.html", "text/html");
} else if ($_REQUEST['act'] == "dellogouthtml" && $a_cp[$cpzone] && $a_cp[$cpzone]['page']['logouttext']) {
	unset($a_cp[$cpzone]['page']['logouttext']);
	write_config(sprintf(gettext("Captive Portal: zone %s: Restore default logout page"), $cpzone));
	header("Location: services_captiveportal.php?zone={$cpzone}");
	exit;
}

init_config_arr(array('ca'));
$a_ca = &$config['ca'];

init_config_arr(array('cert'));
$a_cert = &$config['cert'];

if ($a_cp[$cpzone]) {
	$cpzoneid = $pconfig['zoneid'] = $a_cp[$cpzone]['zoneid'];
	$pconfig['descr'] = $a_cp[$cpzone]['descr'];
	$pconfig['cinterface'] = $a_cp[$cpzone]['interface'];
	$pconfig['maxproc'] = $a_cp[$cpzone]['maxproc'];
	$pconfig['maxprocperip'] = $a_cp[$cpzone]['maxprocperip'];
	$pconfig['timeout'] = $a_cp[$cpzone]['timeout'];
	$pconfig['idletimeout'] = $a_cp[$cpzone]['idletimeout'];
	$pconfig['trafficquota'] = $a_cp[$cpzone]['trafficquota'];
	$pconfig['freelogins_count'] = $a_cp[$cpzone]['freelogins_count'];
	$pconfig['freelogins_resettimeout'] = $a_cp[$cpzone]['freelogins_resettimeout'];
	$pconfig['freelogins_updatetimeouts'] = isset($a_cp[$cpzone]['freelogins_updatetimeouts']);
	$pconfig['enable'] = isset($a_cp[$cpzone]['enable']);
	$pconfig['auth_method'] = $a_cp[$cpzone]['auth_method'];
	$pconfig['auth_server'] = explode(",", $a_cp[$cpzone]['auth_server']);
	$pconfig['auth_server2'] = explode(",", $a_cp[$cpzone]['auth_server2']);
	$pconfig['localauth_priv'] = isset($a_cp[$cpzone]['localauth_priv']);
	$pconfig['radacct_server'] = $a_cp[$cpzone]['radacct_server'];
	$pconfig['radacct_enable'] = isset($a_cp[$cpzone]['radacct_enable']);
	$pconfig['radmac_secret'] = $a_cp[$cpzone]['radmac_secret'];
	$pconfig['radmac_fallback'] = isset($a_cp[$cpzone]['radmac_fallback']);
	$pconfig['reauthenticate'] = isset($a_cp[$cpzone]['reauthenticate']);
	$pconfig['preservedb'] = isset($a_cp[$cpzone]['preservedb']);
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
	if (isset($a_cp[$cpzone]['noconcurrentlogins'])) {
		if (!empty($a_cp[$cpzone]['noconcurrentlogins'])) {
			$pconfig['noconcurrentlogins'] = $a_cp[$cpzone]['noconcurrentlogins'];
		} else {
			$pconfig['noconcurrentlogins'] = 'disabled';
		}
	} else {
		$pconfig['noconcurrentlogins'] = 'multiple';
	}
	$pconfig['redirurl'] = $a_cp[$cpzone]['redirurl'];
	$pconfig['radiussession_timeout'] = isset($a_cp[$cpzone]['radiussession_timeout']);
	$pconfig['radiustraffic_quota'] = isset($a_cp[$cpzone]['radiustraffic_quota']);
	$pconfig['radiusperuserbw'] = isset($a_cp[$cpzone]['radiusperuserbw']);
	$pconfig['passthrumacadd'] = isset($a_cp[$cpzone]['passthrumacadd']);
	$pconfig['radmac_format'] = $a_cp[$cpzone]['radmac_format'];
	$pconfig['reverseacct'] = isset($a_cp[$cpzone]['reverseacct']);
	$pconfig['includeidletime'] = isset($a_cp[$cpzone]['includeidletime']);
	$pconfig['radiusnasid'] = $a_cp[$cpzone]['radiusnasid'];
	$pconfig['page'] = array();
	if ($a_cp[$cpzone]['page']['htmltext']) {
		$pconfig['page']['htmltext'] = $a_cp[$cpzone]['page']['htmltext'];
	}
	if ($a_cp[$cpzone]['page']['errtext']) {
		$pconfig['page']['errtext'] = $a_cp[$cpzone]['page']['errtext'];
	}
	if ($a_cp[$cpzone]['page']['logouttext']) {
		$pconfig['page']['logouttext'] = $a_cp[$cpzone]['page']['logouttext'];
	}
	$pconfig['customlogo'] = isset($a_cp[$cpzone]['customlogo']);
	$pconfig['custombg'] = isset($a_cp[$cpzone]['custombg']);
	$pconfig['customhtml'] = isset($pconfig['page']['htmltext']);
	$pconfig['termsconditions'] = base64_decode($a_cp[$cpzone]['termsconditions']);
}

if ($_POST['save']) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['enable']) {
		$reqdfields = explode(" ", "zone cinterface auth_method");
		$reqdfieldsn = array(gettext("Zone name"), gettext("Interface"), gettext("Authentication method"));

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

		/* make sure no interfaces are bridged or used on other zones */
		if (is_array($_POST['cinterface'])) {
			foreach ($pconfig['cinterface'] as $cpbrif) {
				if (link_interface_to_bridge($cpbrif)) {
					$input_errors[] = sprintf(gettext("The captive portal cannot be used on interface %s since it is part of a bridge."), $cpbrif);
				}
				foreach ($a_cp as $cpkey => $cp) {
					if ($cpkey != $cpzone || empty($cpzone)) {
						if (in_array($cpbrif, explode(",", $cp['interface']))) {
							$input_errors[] = sprintf(gettext('The captive portal cannot be used on interface %1$s since it is used already on %2$s instance.'), $cpbrif, $cp['zone']);
						}
					}
				}
			}
		}

		if ($_POST['auth_method'] && !in_array($_POST['auth_method'], array('none', 'authserver', 'radmac'))) {
			$input_errors[] = gettext("Authentication method is invalid.");
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
		if (!is_numeric($_POST['timeout']) || ($_POST['timeout'] < 1)) {
			$input_errors[] = gettext("The timeout must be at least 1 minute.");
		} else if (isset($config['dhcpd']) && is_array($config['dhcpd'])) {
			foreach ($config['dhcpd'] as $dhcpd_if => $dhcpd_data) {
				if (!isset($dhcpd_data['enable'])) {
					continue;
				}
				if (!is_array($_POST['cinterface']) || !in_array($dhcpd_if, $_POST['cinterface'])) {
					continue;
				}

				$deftime = 7200; // Default lease time
				if (isset($dhcpd_data['defaultleasetime']) && is_numeric($dhcpd_data['defaultleasetime'])) {
					$deftime = $dhcpd_data['defaultleasetime'];
				}

				if ($_POST['timeout'] > $deftime) {
					$input_errors[] = gettext("Hard timeout must be less than or equal to the Default lease time set on DHCP Server");
				}
			}
		}
	}

	if ($_POST['idletimeout'] && (!is_numeric($_POST['idletimeout']) || ($_POST['idletimeout'] < 1))) {
		$input_errors[] = gettext("The idle timeout must be at least 1 minute.");
	}

	if ($_POST['trafficquota'] && (!is_numeric($_POST['trafficquota']) || ($_POST['trafficquota'] < 1))) {
		$input_errors[] = gettext("The traffic quota must be at least 1 megabyte.");
	}

	if ($_POST['freelogins_count'] && (!is_numeric($_POST['freelogins_count']))) {
		$input_errors[] = gettext("The pass-through credit count must be a number or left blank.");
	} else if ($_POST['freelogins_count'] && is_numeric($_POST['freelogins_count']) && ($_POST['freelogins_count'] >= 1)) {
		if (empty($_POST['freelogins_resettimeout']) || !is_numeric($_POST['freelogins_resettimeout']) || ($_POST['freelogins_resettimeout'] <= 0)) {
			$input_errors[] = gettext("The waiting period to restore pass-through credits must be above 0 hours.");
		}
	}

	if ($_POST['maxproc'] && (!is_numeric($_POST['maxproc']) || ($_POST['maxproc'] < 4) || ($_POST['maxproc'] > 100))) {
		$input_errors[] = gettext("The maximum number of concurrent connections per client IP address may not be larger than the global maximum.");
	}

	if ($_POST['auth_method']) {
		if ($_POST['auth_method'] !== 'none' && empty($_POST['auth_server'])) {
			$input_errors[] = gettext("You need to select at least one authentication server.");
		}
		/* If RADMAC auth method is selected : carefully check that the selected server is a RADIUS one */
		if ($_POST['auth_method'] === 'radmac') {
			foreach ($_POST['auth_server'] as $server) {

				$realauthserver = explode(' - ', $server);
				array_shift($realauthserver);
				$realauthserver = implode(' - ', $realauthserver);
				$realauthserver = auth_get_authserver($realauthserver);

				if ($realauthserver === null || $realauthserver['type'] !== 'radius') {
					$input_errors[] = gettext("RADIUS MAC Authentication can only be performed on a RADIUS server.");
				}
			}

			if (isset($_POST['nomacfilter'])) {
				$input_errors[] = gettext("RADIUS MAC Authentication cannot be used if MAC filtering is disabled");
			}
		}
	}

	if (isset($_POST['noconcurrentlogins']) && !in_array($_POST['noconcurrentlogins'], array_keys($concurrentlogins_list))) {
		$input_errors[] = gettext("You need to select an option for Concurrent user logins.");
	}

	if (isset($_POST['radacct_enable']) && empty(auth_get_authserver($_POST['radacct_server']))) {
		$input_errors[] = gettext("You need to select at least one accounting server.");
	}
	if (isset($_POST['radacct_enable']) && !in_array($_POST['reauthenticateacct'], array('none', 'stopstart', 'stopstartfreeradius', 'interimupdate'))) {
		$input_errors[] = gettext("You need to select an option for Accounting Updates !");
	}
	if (trim($_POST['radiusnasid']) !== "" && !preg_match("/^[\x21-\x7e]{3,253}$/i", trim($_POST['radiusnasid']))) {
		$input_errors[] = gettext("The NAS-Identifier must be 3-253 characters long and should only contain ASCII characters.");
	}
	if (is_uploaded_file($_FILES['logo-img']['tmp_name']) &&
	    (is_supported_image($_FILES['logo-img']['tmp_name']) === false)) {
		$input_errors[] = gettext("Unsupported logo image type.");
	}
	if (is_uploaded_file($_FILES['background-img']['tmp_name']) &&
	    (is_supported_image($_FILES['background-img']['tmp_name']) === false)) {
		$input_errors[] = gettext("Unsupported background image type.");
	}

	if (!$input_errors) {
		init_config_arr(array('captiveportal', $cpzone));
		$newcp = &$a_cp[$cpzone];
		//$newcp['zoneid'] = $a_cp[$cpzone]['zoneid'];
		if (empty($newcp['zoneid'])) {
			$newcp['zoneid'] = 2;
			foreach ($a_cp as $keycpzone => $cp) {
				if ($cp['zoneid'] == $newcp['zoneid'] && $keycpzone != $cpzone) {
					$newcp['zoneid'] += 2; /* Reserve space for SSL/TLS config if needed */
				}
			}

			$cpzoneid = $newcp['zoneid'];
		}
		if (is_array($_POST['cinterface'])) {
			$newcp['interface'] = implode(",", $_POST['cinterface']);
		}
		$newcp['descr'] = $_POST['descr'];
		$newcp['maxproc'] = $_POST['maxproc'];
		$newcp['maxprocperip'] = $_POST['maxprocperip'] ? $_POST['maxprocperip'] : false;
		$newcp['timeout'] = $_POST['timeout'];
		$newcp['idletimeout'] = $_POST['idletimeout'];
		$newcp['trafficquota'] = $_POST['trafficquota'];
		$newcp['freelogins_count'] = $_POST['freelogins_count'];
		$newcp['freelogins_resettimeout'] = $_POST['freelogins_resettimeout'];
		$newcp['freelogins_updatetimeouts'] = $_POST['freelogins_updatetimeouts'] ? true : false;
		if ($_POST['enable']) {
			$newcp['enable'] = true;
		} else {
			unset($newcp['enable']);
		}
		$newcp['auth_method'] = $_POST['auth_method'];
		$newcp['auth_server'] = '';
		if ($_POST['auth_method'] != 'none') {
			$newcp['auth_server'] = implode(",", $_POST['auth_server']);
		}
		$newcp['auth_server2'] = '';
		if (!empty($_POST['auth_server2']) && $_POST['auth_method'] === 'authserver') {
			$newcp['auth_server2'] = implode(",", $_POST['auth_server2']);
		}
		$newcp['radacct_server'] = $_POST['radacct_server'];
		$newcp['localauth_priv'] = isset($_POST['localauth_priv']);
		$newcp['radacct_enable'] = $_POST['radacct_enable'] ? true : false;
		$newcp['reauthenticate'] = $_POST['reauthenticate'] ? true : false;
		$newcp['preservedb'] = $_POST['preservedb'] ? true : false;
		$newcp['radmac_secret'] = $_POST['radmac_secret'] ? $_POST['radmac_secret'] : false;
		$newcp['radmac_fallback'] = $_POST['radmac_fallback'] ? true : false;
		$newcp['reauthenticateacct'] = $_POST['reauthenticateacct'];
		if ($_POST['httpslogin_enable']) {
			$newcp['httpslogin'] = true;
		} else {
			unset($newcp['httpslogin']);
		}
		$newcp['httpsname'] = $_POST['httpsname'];
		$newcp['preauthurl'] = $_POST['preauthurl'];
		$newcp['blockedmacsurl'] = $_POST['blockedmacsurl'];
		if ($_POST['peruserbw']) {
			$newcp['peruserbw'] = true;
			if (isset($_POST['bwdefaultdn'])) {
				$newcp['bwdefaultdn'] = $_POST['bwdefaultdn'];
			} else {
				unset($newcp['bwdefaultdn']);
			}
			if (isset($_POST['bwdefaultup'])) {
				$newcp['bwdefaultup'] = $_POST['bwdefaultup'];
			} else {
				unset($newcp['bwdefaultup']);
			}
		} else {
			unset($newcp['peruserbw']);
			unset($newcp['bwdefaultdn']);
			unset($newcp['bwdefaultup']);
		}
		$newcp['certref'] = $_POST['certref'];
		$newcp['nohttpsforwards'] = $_POST['nohttpsforwards'] ? true : false;
		$newcp['logoutwin_enable'] = $_POST['logoutwin_enable'] ? true : false;
		$newcp['nomacfilter'] = $_POST['nomacfilter'] ? true : false;
		if ($_POST['noconcurrentlogins'] == 'multiple') {
			unset($newcp['noconcurrentlogins']);
		} elseif ($_POST['noconcurrentlogins'] == 'disabled') {
			$newcp['noconcurrentlogins'] = true;
		} else {
			$newcp['noconcurrentlogins'] = $_POST['noconcurrentlogins'];
		}
		$newcp['redirurl'] = $_POST['redirurl'];
		$newcp['radiussession_timeout'] = $_POST['radiussession_timeout'] ? true : false;
		$newcp['radiustraffic_quota'] = $_POST['radiustraffic_quota'] ? true : false;
		$newcp['radiusperuserbw'] = $_POST['radiusperuserbw'] ? true : false;
		$newcp['passthrumacadd'] = $_POST['passthrumacadd'] ? true : false;
		$newcp['radmac_format'] = $_POST['radmac_format'] ? $_POST['radmac_format'] : false;
		$newcp['reverseacct'] = $_POST['reverseacct'] ? true : false;
		$newcp['includeidletime'] = $_POST['includeidletime'] ? true : false;
		$newcp['radiusnasid'] = trim($_POST['radiusnasid']);
		if ($_POST['customhtml']) {
			$newcp['customhtml'] = true;
		} else {
			unset($newcp['customhtml']);
		}
		if ($_POST['customlogo']) {
			$newcp['customlogo'] = true;
		} else {
			unset($newcp['customlogo']);
		}
		if ($_POST['custombg']) {
			$newcp['custombg'] = true;
		} else {
			unset($newcp['custombg']);
		}
		$newcp['termsconditions'] = base64_encode(strip_tags($_POST['termsconditions']));
		if (!is_array($newcp['page'])) {
			$newcp['page'] = array();
		}

		/* file upload? */
		if (is_uploaded_file($_FILES['htmlfile']['tmp_name'])) {
			$newcp['page']['htmltext'] = base64_encode(file_get_contents($_FILES['htmlfile']['tmp_name']));
		}
		if (is_uploaded_file($_FILES['errfile']['tmp_name'])) {
			$newcp['page']['errtext'] = base64_encode(file_get_contents($_FILES['errfile']['tmp_name']));
		}
		if (is_uploaded_file($_FILES['logoutfile']['tmp_name'])) {
			$newcp['page']['logouttext'] = base64_encode(file_get_contents($_FILES['logoutfile']['tmp_name']));
		}

		// Check for uploaded images for the default CP login
		if (is_uploaded_file($_FILES['logo-img']['tmp_name'])) {

			/* Validated above, so returned value is OK */
			$logo_name = "captiveportal-logo." . image_type_to_extension(is_supported_image($_FILES['logo-img']['tmp_name']));
			if (is_array($a_cp[$cpzone]['element']) && !empty($a_cp[$cpzone]['element'])) {
				for ($i = 0; $i < count($a_cp[$cpzone]['element']); $i++) {
					if (strpos($a_cp[$cpzone]['element'][$i]['name'], "captiveportal-logo.") !== false){
						// remove old image before replacing it.
						@unlink("{$g['captiveportal_element_path']}/" . $a_cp[$cpzone]['element'][$i]['name']);
						@unlink("{$g['captiveportal_path']}/" . $a_cp[$cpzone]['element'][$i]['name']);
						unset($a_cp[$cpzone]['element'][$i]);
					}
				}
			}
			$element = array();
			$element['name'] = $logo_name;
			$element['size'] = filesize($_FILES['logo-img']['tmp_name']);
			// Set this so it will still show up in file manager but won't be
			// deleted for having no content.
			$element['nocontent'] = true;
			$newcp['element'][] = $element;
			$target = "{$g['captiveportal_path']}/" . $logo_name;
			move_uploaded_file( $_FILES['logo-img']['tmp_name'], $target);
		}
		if (is_uploaded_file($_FILES['background-img']['tmp_name'])) {
			/* Validated above, so returned value is OK */
			$background_name = "captiveportal-background." . image_type_to_extension(is_supported_image($_FILES['background-img']['tmp_name']));
			// is there already a file with that name?
			if (is_array($a_cp[$cpzone]['element']) && !empty($a_cp[$cpzone]['element'])) {
				for ($i = 0; $i < count($a_cp[$cpzone]['element']); $i++) {
					if (strpos($a_cp[$cpzone]['element'][$i]['name'], "captiveportal-background.") !== false){
						// remove old image and replace it.
						@unlink("{$g['captiveportal_element_path']}/" . $a_cp[$cpzone]['element'][$i]['name']);
						@unlink("{$g['captiveportal_path']}/" . $a_cp[$cpzone]['element'][$i]['name']);
						unset($a_cp[$cpzone]['element'][$i]);
					}
				}
			}

			$element = array();
			$element['name'] = $background_name;
			$element['size'] = filesize($_FILES['background-img']['tmp_name']);
			// Set this so it will still show up in file manager but won't be
			// deleted for having no content.
			$element['nocontent'] = true;
			$newcp['element'][] = $element;
			$target = "{$g['captiveportal_path']}/" . $background_name;
			move_uploaded_file( $_FILES['background-img']['tmp_name'], $target);
		}

		write_config("Captive portal settings saved");

		captiveportal_configure_zone($newcp);
		unset($newcp);
		filter_configure();
		header("Location: services_captiveportal_zones.php");
		exit;
	} else {
		if (is_array($_POST['cinterface'])) {
			$pconfig['cinterface'] = implode(",", $_POST['cinterface']);
		}
	}
}

function build_authserver_list() {

	$authlist = auth_get_authserver_list();
	$options = array();

	/* auth types are used by javascript */
	foreach ($authlist as $i => $auth) {
		if ($auth['type'] != 'radius' || $auth['type'] == 'radius' && !empty($auth['radius_auth_port'])) {
			$options[$auth['type'].' - '.$auth['name']] = $auth['name'];
		}
	}
	return $options;
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array(gettext("Configuration"), true, "services_captiveportal.php?zone={$cpzone}");
$tab_array[] = array(gettext("MACs"), false, "services_captiveportal_mac.php?zone={$cpzone}");
$tab_array[] = array(gettext("Allowed IP Addresses"), false, "services_captiveportal_ip.php?zone={$cpzone}");
$tab_array[] = array(gettext("Allowed Hostnames"), false, "services_captiveportal_hostname.php?zone={$cpzone}");
$tab_array[] = array(gettext("Vouchers"), false, "services_captiveportal_vouchers.php?zone={$cpzone}");
$tab_array[] = array(gettext("High Availability"), false, "services_captiveportal_hasync.php?zone={$cpzone}");
$tab_array[] = array(gettext("File Manager"), false, "services_captiveportal_filemanager.php?zone={$cpzone}");
display_top_tabs($tab_array, true);

$form = new Form();
$form->setMultipartEncoding();

$section = new Form_Section('Captive Portal Configuration');

$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	'Enable Captive Portal',
	$pconfig['enable']
));

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

$section->addInput(new Form_Select(
	'cinterface',
	'*Interfaces',
	explode(",", $pconfig['cinterface']),
	get_configured_interface_with_descr(),
	true
))->addClass('general')->setHelp('Select the interface(s) to enable for captive portal.');

$section->addInput(new Form_Input(
	'maxprocperip',
	'Maximum concurrent connections',
	'number',
	$pconfig['maxprocperip'],
	['min' => '0', 'max' => '100']
))->setHelp('Limits the number of concurrent connections to the captive portal HTTP(S) server. This does not set how many users can be logged in ' .
			'to the captive portal, but rather how many connections a single IP can establish to the portal web server.');

$section->addInput(new Form_Input(
	'idletimeout',
	'Idle timeout (Minutes)',
	'number',
	$pconfig['idletimeout']
))->setHelp('Clients will be disconnected after this amount of inactivity. They may log in again immediately, though. Leave this field blank for no idle timeout.');

$section->addInput(new Form_Input(
	'timeout',
	'Hard timeout (Minutes)',
	'number',
	$pconfig['timeout']
))->setHelp('Clients will be disconnected after this amount of time, regardless of activity. They may log in again immediately, though. ' .
			'Leave this field blank for no hard timeout (not recommended unless an idle timeout is set).');

$section->addInput(new Form_Input(
	'trafficquota',
	'Traffic quota (Megabytes)',
	'number',
	$pconfig['trafficquota']
))->setHelp('Clients will be disconnected after exceeding this amount of traffic, inclusive of both downloads and uploads. They may log in again immediately, though. ' .
			'Leave this field blank for no traffic quota.');

$section->addInput(new Form_Input(
	'freelogins_count',
	'Pass-through credits per MAC address.',
	'number',
	$pconfig['freelogins_count']
))->setHelp('Allows passing through the captive portal without authentication a limited number of times per MAC address. Once used up, ' .
			'the client can only log in with valid credentials until the waiting period specified below has expired. Recommended to set ' .
			'a hard timeout and/or idle timeout when using this for it to be effective.');

$section->addInput(new Form_Input(
	'freelogins_resettimeout',
	'Waiting period to restore pass-through credits. (Hours)',
	'number',
	$pconfig['freelogins_resettimeout']
))->setHelp('Clients will have their available pass-through credits restored to the original count after this amount of time since using the first one. ' .
			'This must be above 0 hours if pass-through credits are enabled.');

$section->addInput(new Form_Checkbox(
	'freelogins_updatetimeouts',
	'Reset waiting period',
	'Enable waiting period reset on attempted access',
	$pconfig['freelogins_updatetimeouts']
))->setHelp('If enabled, the waiting period is reset to the original duration if access is attempted when all pass-through credits have already been exhausted.');

$section->addInput(new Form_Checkbox(
	'logoutwin_enable',
	'Logout popup window',
	'Enable logout popup window',
	$pconfig['logoutwin_enable']
))->setHelp('If enabled, a popup window will appear when clients are allowed through the captive portal. ' .
			'This allows clients to explicitly disconnect themselves before the idle or hard timeout occurs.');

$section->addInput(new Form_Input(
	'preauthurl',
	'Pre-authentication redirect URL',
	'text',
	$pconfig['preauthurl']
))->setHelp('Set a default redirection URL. Visitors will be redirected to this URL after authentication only if the captive portal doesn\'t know where to redirect them. This field will be accessible through $PORTAL_REDIRURL$ variable in captiveportal\'s HTML pages.');

$section->addInput(new Form_Input(
	'redirurl',
	'After authentication Redirection URL',
	'text',
	$pconfig['redirurl']
))->setHelp('Set a forced redirection URL. Clients will be redirected to this URL instead of the one they initially tried to access after they\'ve authenticated.');

$section->addInput(new Form_Input(
	'blockedmacsurl',
	'Blocked MAC address redirect URL',
	'text',
	$pconfig['blockedmacsurl']
))->setHelp('Blocked MAC addresses will be redirected to this URL when attempting access.');

if (captiveportal_xmlrpc_sync_get_details($tmpsyncip, $tmpport, $tmpusername, $tmppassword)) {
	$section->addInput(new Form_Checkbox(
	'preservedb_disabled',
	'Preserve users database',
	'Preserve connected users across reboot',
	'yes'
	))->setDisabled()->setHelp("If enabled, connected users won't be disconnected during a %s reboot. This setting is not editable because High Availability is enabled.", $g['product_label']);
} else {
	$section->addInput(new Form_Checkbox(
	'preservedb',
	'Preserve users database',
	'Preserve connected users across reboot',
	$pconfig['preservedb']
	))->setHelp("If enabled, connected users won't be disconnected during a %s reboot.", $g['product_label']);
}

$section->addInput(new Form_Select(
	'noconcurrentlogins',
	'Concurrent user logins',
	$pconfig['noconcurrentlogins'],
	$concurrentlogins_list
))->setHelp('Disabled: Do not allow concurrent logins per username or voucher.%1$s' .
			'Multiple: No restrictions to the number of logins per username or voucher will be applied.%1$s' .
			'Last login: Only the most recent login per username or voucher will be granted. ' .
			'Previous logins will be disconnected.%1$s' .
			'First login: Only the first login per username or voucher will be granted. ' .
			'Further login attempts using the username or voucher will not be possible while an initial user is already active.', '<br />');

$section->addInput(new Form_Checkbox(
	'nomacfilter',
	'MAC filtering',
	'Disable MAC filtering',
	$pconfig['nomacfilter']
))->setHelp('If enabled no attempts will be made to ensure that the MAC address of clients stays the same while they are logged in. ' .
			'This is required when the MAC address of the client cannot be determined (usually because there are routers between %s and the clients). ' .
			'If this is enabled, RADIUS MAC authentication cannot be used.', $g['product_label']);

$section->addInput(new Form_Checkbox(
	'passthrumacadd',
	'Pass-through MAC Auto Entry',
	'Enable Pass-through MAC automatic additions',
	$pconfig['passthrumacadd']
))->setHelp('When enabled, a MAC passthrough entry is automatically added after the user has successfully authenticated. Users of that MAC address will ' .
			'never have to authenticate again. To remove the passthrough MAC entry either log in and remove it manually from the ' .
			'%1$sMAC tab%2$s or send a POST from another system. '  .
			'If this is enabled, the logout window will not be shown.', "<a href=\"services_captiveportal_mac.php?zone={$cpzone}\">", '</a>');

$section->addInput(new Form_Checkbox(
	'peruserbw',
	'Per-user bandwidth restriction',
	'Enable per-user bandwidth restriction',
	$pconfig['peruserbw']
));

$section->addInput(new Form_Input(
	'bwdefaultdn',
	'Default download (Kbit/s)',
	'number',
	$pconfig['bwdefaultdn']
));

$section->addInput(new Form_Input(
	'bwdefaultup',
	'Default upload (Kbit/s)',
	'number',
	$pconfig['bwdefaultup']
))->setHelp('If this option is set, the captive portal will restrict each user who logs in to the specified default bandwidth. ' .
			'RADIUS servers can override the default settings. Leave empty for no limit.');

$section->addInput(new Form_Checkbox(
	'customhtml',
	'Use custom captive portal page',
	'Enable to use a custom captive portal login page',
	$pconfig['customhtml']
))->setHelp('If set a portal.html page must be created and uploaded. If unchecked the default template will be used');

$form->add($section);

$section = new Form_Section('HTML Page Contents');
$section->addClass('Custom-HTML');

$section->addInput(new Form_Input(
	'htmlfile',
	'Portal page contents',
	'file',
	$pconfig['htmlfile']
))->setHelp('Upload an HTML/PHP file for the portal page here (leave blank to keep the current one). Make sure to include a form (POST to "$PORTAL_ACTION$") ' .
			'with a submit button (name="accept") and a hidden field with name="redirurl" and value="$PORTAL_REDIRURL$". ' .
			'Include the "auth_user" and "auth_pass" and/or "auth_voucher" input fields if authentication is enabled, otherwise it will always fail.%1$s' .
			'Example code for the form: %1$s' .
			'&lt;form method=&quot;post&quot; action=&quot;$PORTAL_ACTION$&quot;&gt;%1$s' .
			'&nbsp;&nbsp;&nbsp;&lt;input name=&quot;auth_user&quot; type=&quot;text&quot;&gt;%1$s' .
			'&nbsp;&nbsp;&nbsp;&lt;input name=&quot;auth_pass&quot; type=&quot;password&quot;&gt;%1$s' .
			'&nbsp;&nbsp;&nbsp;&lt;input name=&quot;auth_voucher&quot; type=&quot;text&quot;&gt;%1$s' .
			'&nbsp;&nbsp;&nbsp;&lt;input name=&quot;redirurl&quot; type=&quot;hidden&quot; value=&quot;$PORTAL_REDIRURL$&quot;&gt;%1$s' .
			'&nbsp;&nbsp;&nbsp;&lt;input name=&quot;zone&quot; type=&quot;hidden&quot; value=&quot;$PORTAL_ZONE$&quot;&gt;%1$s' .
			'&nbsp;&nbsp;&nbsp;&lt;input name=&quot;accept&quot; type=&quot;submit&quot; value=&quot;Continue&quot;&gt;%1$s' .
			'&lt;/form&gt;', '<br />')->addClass('btn btn-info btn-sm');

list($host) = explode(":", $_SERVER['HTTP_HOST']);
$zoneid = $pconfig['zoneid'] ? $pconfig['zoneid'] : 8000;
if ($pconfig['httpslogin_enable']) {
	$port = $pconfig['listenporthttps'] ? $pconfig['listenporthttps'] : ($zoneid + 8001);
	$href = "https://{$host}:{$port}/?zone={$cpzone}";
} else {
	$port = $pconfig['listenporthttp'] ? $pconfig['listenporthttp'] : ($zoneid + 8000);
	$href = "http://{$host}:{$port}/?zone={$cpzone}";
}

if ($pconfig['page']['htmltext']) {
	$group = new Form_Group('Current Portal Page');
	$group->add(new Form_Button(
		'btnliveview',
		'Live View',
		$href,
		'fa-file-text-o'
	))->addClass('btn btn-info btn-xs')->setAttribute("target", "_blank");

	$group->add(new Form_Button(
		'btnview',
		'View Page Contents',
		'?zone=' . $cpzone . '&act=viewhtml',
		'fa-file-text-o'
	))->addClass('btn btn-info btn-xs')->setAttribute("target", "_blank");

	$group->add(new Form_Button(
		'btndownload',
		'Download',
		'?zone=' . $cpzone . '&act=gethtmlhtml',
		'fa-download'
	))->addClass('btn btn-primary btn-xs')->setAttribute("target", "_blank");

	$group->add(new Form_Button(
		'btndownload',
		'Restore Default Page',
		'?zone=' . $cpzone . '&act=delhtmlhtml',
		'fa-undo'
	))->addClass('btn btn-danger btn-xs')->setAttribute("target", "_blank");
	$section->add($group);
}

$section->addInput(new Form_Input(
	'errfile',
	'Auth error page contents',
	'file',
	$pconfig['errfile']
))->setHelp('The contents of the HTML/PHP file that is uploaded here are displayed when an authentication error occurs. ' .
			'It may include "$PORTAL_MESSAGE$", which will be replaced by the error or reply messages from the RADIUS ' .
			'server, if any.')->addClass('btn btn-info btn-sm');

if ($pconfig['page']['errtext']) {
	$group = new Form_Group('Current Auth Error Page');
	$group->add(new Form_Button(
		'btnview',
		'View Page Contents',
		'?zone=' . $cpzone . '&act=viewerrhtml',
		'fa-file-text-o'
	))->addClass('btn btn-info btn-xs')->setAttribute("target", "_blank");

	$group->add(new Form_Button(
		'btndownload',
		'Download',
		'?zone=' . $cpzone . '&act=geterrhtml',
		'fa-download'
	))->addClass('btn btn-primary btn-xs')->setAttribute("target", "_blank");

	$group->add(new Form_Button(
		'btndownload',
		'Restore Default Page',
		'?zone=' . $cpzone . '&act=delerrhtml',
		'fa-undo'
	))->addClass('btn btn-danger btn-xs')->setAttribute("target", "_blank");
	$section->add($group);
}

$section->addInput(new Form_Input(
	'logoutfile',
	'Logout page contents',
	'file',
	$pconfig['logoutfile']
))->setHelp('The contents of the HTML/PHP file that is uploaded here are displayed on authentication success when the logout popup is enabled.')->addClass('btn btn-info btn-sm');

if ($pconfig['page']['logouttext']) {
	$group = new Form_Group('Current Logout Page');
	$group->add(new Form_Button(
		'btnview',
		'View Page Contents',
		'?zone=' . $cpzone . '&act=viewlogouthtml',
		'fa-file-text-o'
	))->addClass('btn btn-info btn-xs')->setAttribute("target", "_blank");

	$group->add(new Form_Button(
		'btndownload',
		'Download',
		'?zone=' . $cpzone . '&act=getlogouthtml',
		'fa-download'
	))->addClass('btn btn-primary btn-xs')->setAttribute("target", "_blank");

	$group->add(new Form_Button(
		'btndownload',
		'Restore Default Page',
		'?zone=' . $cpzone . '&act=dellogouthtml',
		'fa-undo'
	))->addClass('btn btn-danger btn-xs')->setAttribute("target", "_blank");
	$section->add($group);
}
$form->addGlobal(new Form_Input(
	'zone',
	null,
	'hidden',
	$cpzone
));

$form->add($section);

$section = new Form_Section('Captive Portal Login Page');
$section->addClass('Default-HTML');

$section->addInput(new Form_Checkbox(
	'customlogo',
	'Display custom logo image',
	'Enable to use a custom uploaded logo',
	$pconfig['customlogo']
));

$section->addInput(new Form_Input(
	'logo-img',
	'Logo Image',
	'file',
	''
))->setHelp('Add a logo for use in the default portal login screen. File will be renamed captiveportal-logo.* The image will be resized to fit within the given area, It can be of any image type: .png, .jpg, .svg <strong>This image will not be stored in the config</strong>. The default logo will be used if no custom image is present.')->addClass("btn btn-info btn-sm");

$section->addInput(new Form_Checkbox(
	'custombg',
	'Display custom background image',
	'Enable to use a custom uploaded background image',
	$pconfig['custombg']
));

$section->addInput(new Form_Input(
	'background-img',
	'Background Image',
	'file',
	''
))->setHelp('Add a background image for use in the default portal login screen. File will be renamed captiveportal-background.* The background image will fill the screen. <strong>This image will not be stored in the config</strong>. The default background image will be used if no custom background is present.')->addClass("btn btn-info btn-sm");

$section->addInput(new Form_Textarea(
	'termsconditions',
	'Terms and Conditions',
	$pconfig['termsconditions']
	))->setHelp('Copy and paste terms and conditions for use in the captive portal. HTML tags will be stripped out');

$form->add($section);

$section = new Form_Section('Authentication');
$section->addClass('Authentication');

$group = new Form_Group('*Authentication Method');

$options['authserver'] = 'Use an Authentication backend';
$options['none'] = 'None, don\'t authenticate users';
$options['radmac'] = 'Use RADIUS MAC Authentication';

$group->add(new Form_Select(
	'auth_method',
	'Authentication Method',
	$pconfig['auth_method'],
	$options
))->setHelp('Select an Authentication Method to use for this zone. One method must be selected.<br />'.
'- "Authentication backend" will force the login page to be displayed and will authenticate users using their login and password, or using vouchers.<br />'.
'- "None" method will force the login page to be displayed but will accept any visitor that clicks the "submit" button.<br/>'.
'- "RADIUS MAC Authentication" method will try to authenticate devices automatically with their MAC address without displaying any login page.');

$section->add($group);

$group = new Form_Group('*Authentication Server');
$group->addClass('auth_server');

$group->add(new Form_Select(
	'auth_server',
	'*Authentication Server',
	$pconfig['auth_server'],
	build_authserver_list(),
	true
))->setHelp("You can add a remote authentication server in the <a href=\"/system_authservers.php\">User Manager</a>.<br/>".
	"<span class=\"vouchers_helptext\">Vouchers could also be used, please go to ".
	"the <a href=\"services_captiveportal_vouchers.php?zone={$cpzone}\">Vouchers Page</a> to enable them.</span>");
$section->add($group);

$group = new Form_Group('Secondary authentication Server');
$group->addClass('auth_server2');

$group->add(new Form_Select(
	'auth_server2',
	'',
	$pconfig['auth_server2'],
	build_authserver_list(),
	true
))->setHelp("You can optionally select a second set of servers to to authenticate users. Users will then be able to login using separated HTML inputs.<br />".
			"This setting is useful if you want to provide multiple authentication method to your users. If you don't need multiple authentication method, then leave this setting empty.");
$section->add($group);

$section->addInput(new Form_Input(
	'radiusnasid',
	'NAS Identifier',
	'text',
	$pconfig['radiusnasid']
))->setHelp('Specify a NAS identifier to override the default value (CaptivePortal-%s)', $cpzone);

$section->addInput(new Form_Checkbox(
	'reauthenticate',
	'Reauthenticate Users',
	'Reauthenticate connected users every minute',
	$pconfig['reauthenticate']
))->setHelp('If reauthentication is enabled, request are made to the server for each user that is logged in every minute. ' .
			'If an access denied is received for a user, that user is disconnected from the captive portal immediately. ' .
			'Reauthentication requires user credentials to be cached in the captive portal database while a user is logged in; ' .
			'The cached credentials are necessary for the portal to perform automatic reauthentication requests.');



$section->addInput(new Form_Checkbox(
	'localauth_priv',
	'Local Authentication Privileges',
	'Allow only users/groups with "Captive portal login" privilege set',
	$pconfig['localauth_priv']
));


$section->addInput(new Form_Input(
	'radmac_secret',
	'RADIUS MAC Secret',
	'text',
	$pconfig['radmac_secret']
))->setHelp('RADIUS MAC will automatically try to authenticate devices with their MAC address as username, and the password entered below as password. Devices will still need to make one HTTP request to get connected, through.');

$section->addInput(new Form_Checkbox(
	'radmac_fallback',
	'Login page Fallback',
	'Display the login page as fallback if RADIUS MAC authentication failed.',
	$pconfig['radmac_fallback']
))->setHelp('When enabled, users will be redirected to the captive portal login page when RADIUS MAC authentication failed.');

$section->addInput(new Form_Checkbox(
	'radiussession_timeout',
	'Session timeout',
	'Use RADIUS Session-Timeout attributes',
	$pconfig['radiussession_timeout']
))->setHelp('When enabled, clients will be disconnected after the amount of time retrieved from the RADIUS Session-Timeout attribute.');

$section->addInput(new Form_Checkbox(
	'radiustraffic_quota',
	'Traffic quota',
	'Use RADIUS pfSense-Max-Total-Octets attribute',
	$pconfig['radiustraffic_quota']
))->setHelp('When enabled, clients will be disconnected after exceeding the amount of traffic, inclusive of both downloads and uploads, retrieved from the RADIUS pfSense-Max-Total-Octets attribute.');

$section->addInput(new Form_Checkbox(
	'radiusperuserbw',
	'Per-user bandwidth restrictions',
	'Use RADIUS pfSense-Bandwidth-Max-Up and pfSense-Bandwidth-Max-Down attributes',
	$pconfig['radiusperuserbw']
))->setHelp('When enabled, the bandwidth assigned to a client will be limited to the values retrieved from the RADIUS pfSense-Bandwidth-Max-Up and ' .
			'pfSense-Bandwidth-Max-Down attributes or from the comparable WISPr attributes.');

$section->addInput(new Form_Select(
	'radmac_format',
	'MAC address format',
	$pconfig['radmac_format'],
	['default' => 'Default', 'singledash' => gettext('Single dash'), 'ietf' => 'IETF', 'cisco' => 'Cisco', 'unformatted' => gettext('Unformatted')]
))->setHelp('This option changes the MAC address format used when performing a RADIUS authentication. %1$s' .
			'Default: 00:11:22:33:44:55 %1$s' .
			'Single dash: 001122-334455 %1$s' .
			'IETF: 00-11-22-33-44-55 %1$s' .
			'Cisco: 0011.2233.4455 %1$s' .
			'Unformatted: 001122334455', '<br />');

$form->add($section);

$section = new Form_Section('Accounting');
$section->addClass('Accounting');

$section->addInput(new Form_Checkbox(
	'radacct_enable',
	'RADIUS',
	'Send RADIUS accounting packets.',
	$pconfig['radacct_enable']
))->setHelp('If enabled, accounting request will be made for users identified against any RADIUS server.');


$options = array();

foreach (auth_get_authserver_list() as $i => $auth) {
	if ($auth['type'] == 'radius' && !empty($auth['radius_acct_port'])) {
		$options[$auth['name']] = $auth['name'];
	}
}

$group = new Form_Group('Accounting Server');


$group->add(new Form_Select(
	'radacct_server',
	'Accounting Server',
	$pconfig['radacct_server'],
	$options
));

$group->addClass('radacct_enable');
$group->setHelp('You can add a Radius Accounting server in the <a href="/system_authservers.php">User Manager</a>.');

$section->add($group);

$group = new Form_Group('Send accounting updates');

$group->addClass('reauthenticateacct');

$group->add(new Form_Checkbox(
	'reauthenticateacct',
	null,
	'No updates',
	$pconfig['reauthenticateacct'] == 'none',
	"none"
))->displayasRadio();

$group->add(new Form_Checkbox(
	'reauthenticateacct',
	null,
	'Stop/Start',
	$pconfig['reauthenticateacct'] == 'stopstart',
	"stopstart"
))->displayasRadio();

$group->add(new Form_Checkbox(
	'reauthenticateacct',
	null,
	'Stop/Start (FreeRADIUS)',
	$pconfig['reauthenticateacct'] == 'stopstartfreeradius',
	"stopstartfreeradius"
))->displayasRadio();

$group->add(new Form_Checkbox(
	'reauthenticateacct',
	null,
	'Interim',
	$pconfig['reauthenticateacct'] == 'interimupdate',
	"interimupdate"
))->displayasRadio();

$group->setHelp('This field set the way Accounting Updates should be done : <br />'.
				'- If "No updates" is selected, then only one "Accounting Start" and one "Accounting Stop" request will be sent, when any user get connected and disconnected.<br />'.
				'- If "Interim" is selected, then "Accounting Update" requests will be send regularly (every minute) to the RADIUS server, for each connected user.<br />'.
				'- In some rare cases, you would like to simulate users to disconnect and reconnect every minute (eg, to send an Accounting Stop then an Accounting Start) instead of sending Accounting updates, this is the purpose of "Stop/Start" option. FreeRADIUS does not support this option very well, you should select "Stop/Start (FreeRADIUS)" instead.');

$section->add($group);

$section->addInput(new Form_Checkbox(
	'reverseacct',
	'Accounting style',
	'Invert Acct-Input-Octets and Acct-Output-Octets',
	$pconfig['reverseacct']
))->setHelp('When enabled, data counts for RADIUS accounting packets will be taken from the client perspective, not the NAS. ' .
			'Acct-Input-Octets will represent download, and Acct-Output-Octets will represent upload.');

$section->addInput(new Form_Checkbox(
	'includeidletime',
	'Idle time accounting',
	'Include idle time when users get disconnected due to idle timeout',
	$pconfig['includeidletime']
))->setHelp('This setting change the stop time that will be send in the Accounting Stop request, when a user get disconnected after exceeding the idle timeout. ' .
			'If not checked, the sent stop time will be the last activity time.');
$form->add($section);

$section = new Form_Section('HTTPS Options');
$section->addClass('HTTPS');

$section->addInput(new Form_Checkbox(
	'httpslogin_enable',
	'Login',
	'Enable HTTPS login',
	$pconfig['httpslogin_enable']
))->setHelp('When enabled, the username and password will be transmitted over an HTTPS connection to protect against eavesdroppers. ' .
			'A server name and certificate must also be specified below.');

$section->addInput(new Form_Input(
	'httpsname',
	'*HTTPS server name',
	'text',
	$pconfig['httpsname']
))->setHelp('This name will be used in the form action for the HTTPS POST and should match the Common Name (CN) in the certificate ' .
			'(otherwise, the client browser will most likely display a security warning). ' .
			'Make sure captive portal clients can resolve this name in DNS and verify on the client that the IP resolves to the correct interface IP on %s.',
			$g['product_label']);

$section->addInput(new Form_Select(
	'certref',
	'*SSL/TLS Certificate',
	$pconfig['certref'],
	cert_build_list('cert', 'HTTPS')
))->setHelp('Certificates known to be incompatible with use for HTTPS are not included in this list. If no certificates are defined, one may be defined here: %1$sSystem &gt; Cert. Manager%2$s', '<a href="system_certmanager.php">', '</a>');

$section->addInput(new Form_Checkbox(
	'nohttpsforwards',
	'HTTPS Forwards',
	'Disable HTTPS Forwards',
	$pconfig['nohttpsforwards']
))->setHelp('If this option is set, attempts to connect to HTTPS (SSL/TLS on port 443) sites will not be forwarded to the captive portal. ' .
			'This prevents certificate errors from being presented to the user even if HTTPS logins are enabled. ' .
			'Users must attempt a connection to an HTTP (Port 80) site to get forwarded to the captive portal. ' .
			'If HTTPS logins are enabled, the user will be redirected to the HTTPS login page.');

$form->add($section);

print($form);

print_info_box(gettext('Don\'t forget to enable the DHCP server on the captive portal interface! ' .
					   'Make sure that the default/maximum DHCP lease time is higher than the hard timeout entered on this page. ' .
					   'Also, the DNS Forwarder or Resolver must be enabled for DNS lookups by unauthenticated clients to work.'));

?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// ------- Show/hide sections based on checkbox settings --------------------------------------
	function hideSections(hide) {
		hideClass('Authentication', hide);
		hideHTTPS();
		hideClass('HTTPS', hide);
		hideClass('HTML', hide);
		hideClass('Custom-HTML', (hide || !$('#customhtml').prop('checked')));
		hideClass('Default-HTML', (hide || $('#customhtml').prop('checked')));
		hideGeneral(hide);
		hideClass('Accounting', hide);
	}

	function hideRadius(hide) {
		hideCheckbox('radiussession_timeout', hide);
		hideInput('radmac_format', hide);
		hideCheckbox('radiusperuserbw', hide);
		hideCheckbox('radiustraffic_quota', hide);
		hideInput('radiusnasid', hide);
	}

	function hideHTTPS() {
		hide = (!$('#httpslogin_enable').prop('checked') || !$('#enable').prop('checked'));

		hideInput('httpsname', hide);
		hideInput('certref', hide);
		hideCheckbox('nohttpsforwards', hide);
	}

	function hideGeneral(hide) {
		hideMultiClass('general', hide);
		hideInput('maxprocperip', hide);
		hideInput('idletimeout', hide);
		hideInput('timeout', hide);
		hideInput('trafficquota', hide);
		hideInput('freelogins_count', hide);
		hideInput('freelogins_resettimeout', hide);
		hideCheckbox('freelogins_updatetimeouts', hide);
		hideCheckbox('logoutwin_enable', hide);
		hideInput('preauthurl', hide);
		hideInput('redirurl', hide);
		hideInput('blockedmacsurl', hide);
		hideInput('noconcurrentlogins', hide);
		hideCheckbox('preservedb', hide);
		hideCheckbox('preservedb_disabled', hide);
		hideCheckbox('nomacfilter', hide);
		hideCheckbox('passthrumacadd', hide);
		hideCheckbox('peruserbw', hide);
		hideCheckbox('reauthenticate', hide);
		hideCheckbox('customhtml', hide);
	}

	function hideRadiusAccounting(radiusServerSelected, hide) {
		if(radiusServerSelected) return;

		hideInput('radacct_server', hide);
		hideClass('reauthenticateacct', hide);
		hideCheckbox('reverseacct', hide);
		hideCheckbox('includeidletime', hide);
	}

	function hidePassthru(hide) {
		if(!$('#enable').prop('checked')) {
			hide = true;
		}
		else if(!hide) {
			$('#logoutwin_enable').prop('checked', false);
		}

		disableInput("logoutwin_enable", !hide);
	}

	function hidePerUserBandwith(hide) {
		if(!$('#enable').prop('checked')) {
			hide = true;
		}
		hideInput('bwdefaultdn', hide);
		hideInput('bwdefaultup', hide);
	}

	function triggerChangesAuthMethod() {
		if(!$('#enable').prop('checked')) return;

		let authserver_list = <?php echo json_encode(build_authserver_list()); ?>;
		let auth_method =  $('#auth_method').val();
		let saved_values = $('select[name="auth_server[]"]').val(); // we save the current list of selected servers

		if(auth_method.indexOf("authserver") === 0) {
			// If authserver is selected : we display all the server list.
			$('select[name="auth_server[]"]').find('option').remove();
			$.each(authserver_list, function(key, value) {
				$('<option>').val(key).text(value).appendTo($('select[name="auth_server[]"]'));
			});

			hideCheckbox('reauthenticate', false);
			hideClass('auth_server', false);
			hideInput('radmac_secret', true);
			hideCheckbox('radmac_fallback', true);
			$('.auth_server .vouchers_helptext').removeClass('hidden');
		}
		else if(auth_method.indexOf("radmac") === 0) {
			// If Radmac is selected : only RADIUS servers should be displayed
			$('select[name="auth_server[]"]').find('option').remove();

			$.each(authserver_list, function(key, value) {
				if(key.indexOf("radius") === 0) {
					$('<option>').val(key).text(value).appendTo($('select[name="auth_server[]"]'));
				}
			});
			hideCheckbox('reauthenticate', false);
			hideClass('auth_server', false);
			hideInput('radmac_secret', false);
			hideCheckbox('radmac_fallback', false);
			$('.auth_server .vouchers_helptext').addClass('hidden');
		} else {
			// if "none" is selected : we hide most of authentication settings
			hideRadius(true);
			hideCheckbox('reauthenticate', true);
			hideClass('auth_server', true);
			hideInput('radmac_secret', true);
			hideCheckbox('radmac_fallback', true);
		}


		// we try to restore all previous selected servers
		$.each(saved_values, function(key, value) {
			$('select[name="auth_server[]"] option').filter(function(i, e) {return $(e).val() == value}).attr("selected", "selected");
		});

		triggerChangesAuthServer();
	}

	function triggerChangesAuthServer() {
		if(!$('#enable').prop('checked')) return;

		let shouldHideLocal = true;
		let shouldHideRadius = true;
		let shouldHideLdap = true;
		let shouldHideSecondAuth = true;

		if($('#auth_method').val().indexOf("none") !== 0) {
			$.each($('select[name="auth_server[]"]').val(), function(key,value) {
				if(value.indexOf("radius") === 0) {
					shouldHideRadius = false;
				} else if(value.indexOf("ldap") === 0) {
					shouldHideLdap = false;
				}
				else if(value.indexOf("Local Auth") === 0) {
					shouldHideLocal = false;
				}
				if($('#auth_method').val().indexOf("authserver") === 0) { // There is no second auth possibility when none/radmac are selected
					shouldHideSecondAuth = false;
				}
			});

			$.each($('select[name="auth_server2[]"]').val(), function(key,value) {
				if(value.indexOf("radius") === 0) {
					shouldHideRadius = false;
				} else if(value.indexOf("ldap") === 0) {
					shouldHideLdap = false;
				}
				else if(value.indexOf("Local Auth") === 0) {
					shouldHideLocal = false;
				}
			});
		}
		hideCheckbox('localauth_priv', shouldHideLocal); // Hide/Show Local Auth options
		hideClass('auth_server2', shouldHideSecondAuth); // Hide/show second auth context
		hideRadius(shouldHideRadius); // Hide/Show Radius authentication options
		hideClass('Accounting', shouldHideRadius);
		hideRadiusAccounting(shouldHideRadius, !$('input[name="radacct_enable"]').prop('checked'));
	}
	// ---------- Click checkbox handlers ---------------------------------------------------------
	$("#enable").click(function() {
		hideSections(!this.checked);
		hidePerUserBandwith(!$("#peruserbw").prop('checked'));
		hidePassthru(!$("#passthrumacadd").prop('checked'));
		triggerChangesAuthMethod();
		triggerChangesAuthServer();
	});

	$('select[name="auth_server[]"]').on('change', function() {
		triggerChangesAuthServer();
	});
	$('select[name="auth_server2[]"]').on('change', function() {
		triggerChangesAuthServer();
	});
	$('select[name="auth_method"]').on('change', function() {
		triggerChangesAuthMethod();
	});
	$('input[name="radacct_enable"]').on('change', function() {
		hideRadiusAccounting(false, !this.checked);
	});


	$("#httpslogin_enable").click(function() {
		hideHTTPS(!this.checked);
	});
	$("#nomacfilter").click(function()
	{
		let radmac_option = $('select[name="auth_method"] option[value="radmac"]');
		if(this.checked) {
			radmac_option.prop('disabled','disabled');
			if($('select[name="auth_method"]').val() == radmac_option.val() || $('select[name="auth_method"]').val() == null) {
				$('select[name="auth_method"]').val($('select[name="auth_method"] option:first').val());
			}
		} else {
			radmac_option.removeAttr('disabled');
		}
	});


	$("#peruserbw").click(function() {
		hidePerUserBandwith(!this.checked);
	});

	$("#passthrumacadd").click(function() {
		hidePassthru(!this.checked);
	});

	$("#customhtml").click(function() {
		hideClass('Custom-HTML', !this.checked);
		hideClass('Default-HTML', this.checked);
	})

	// ---------- On initial page load ------------------------------------------------------------
	hideSections(!$('#enable').prop('checked'));
	hidePerUserBandwith(!$("#peruserbw").prop('checked'));
	hidePassthru(!$("#passthrumacadd").prop('checked'));
	triggerChangesAuthMethod();
	triggerChangesAuthServer();

});
//]]>
</script>

<?php include("foot.inc");
