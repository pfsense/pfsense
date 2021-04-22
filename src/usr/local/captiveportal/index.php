<?php
/*
 * index.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Originally part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2006 Manuel Kasper <mk@neon1.net>.
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

require_once("auth.inc");
require_once("util.inc");
require_once("functions.inc");
require_once("captiveportal.inc");

header("Expires: 0");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Connection: close");

global $cpzone, $cpzoneid;

$cpzone = strtolower($_REQUEST['zone']);
$cpcfg = $config['captiveportal'][$cpzone];

/* NOTE: IE 8/9 is buggy and that is why this is needed */
$orig_request = trim($_REQUEST['redirurl'], " /");

/* If the post-auth redirect is set, always use it. Otherwise take what was supplied in URL. */
if (!empty($cpcfg) && is_URL($cpcfg['redirurl'])) {
	$redirurl = $cpcfg['redirurl'];
} elseif (preg_match("/redirurl=(.*)/", $orig_request, $matches)) {
	$redirurl = urldecode($matches[1]);
} elseif ($_REQUEST['redirurl']) {
	$redirurl = $_REQUEST['redirurl'];
}
/* Sanity check: If the redirect target is not a URL, do not attempt to use it like one. */
if (!is_URL(urldecode($redirurl))) {
	$redirurl = "";
}

if (empty($cpcfg)) {
	log_error("Submission to captiveportal with unknown parameter zone: " . htmlspecialchars($cpzone));
	portal_reply_page($redirurl, "error", gettext("Internal error"));
	ob_flush();
	return;
}

$cpzoneid = $cpcfg['zoneid'];
$orig_host = $_SERVER['HTTP_HOST'];
$clientip = $_SERVER['REMOTE_ADDR'];

if (!$clientip) {
	/* not good - bail out */
	log_error("Zone: {$cpzone} - Captive portal could not determine client's IP address.");
	$errormsg = gettext("An error occurred.  Please check the system logs for more information.");
	portal_reply_page($redirurl, "error", $errormsg);
	ob_flush();
	return;
}

$cpsession = captiveportal_isip_logged($clientip);
$ourhostname = portal_hostname_from_client_ip($clientip);
/* Automatically switching to the logout page requires a custom logout page to be present. */
if ((!empty($cpsession)) && (! $_POST['logout_id']) && (!empty($cpcfg['page']['logouttext']))) {
	/* if client already connected and a custom logout page is set : show logout page */
	$protocol = (isset($config['captiveportal'][$cpzone]['httpslogin'])) ? 'https://' : 'http://';
	$logouturl = "{$protocol}{$ourhostname}/";

	$sessionid = $cpsession['sessionid'];
	$attributes = array();
	if (!empty($cpsession['session_timeout']))
		$attributes['session_timeout'] = $cpsession['session_timeout'];
	if (!empty($cpsession['session_terminate_time']))
		$attributes['session_terminate_time'] = $cpsession['session_terminate_time'];

	include("{$g['varetc_path']}/captiveportal-{$cpzone}-logout.html");
	ob_flush();
	return;
} elseif (!empty($cpsession) && (!isset($_POST['logout_id']) || !isset($config['captiveportal'][$cpzone]['logoutwin_enable']))) {
	/* If client try to access captive portal page while already connected, 
		but no custom logout page does exist and logout popup is disabled */	
	echo gettext("You are connected.<br/>");
	if (!empty($redirurl)) {
		$redirurl = htmlspecialchars($redirurl);
		echo ("You can proceed to: <a href='{$redirurl}'>{$redirurl}</a>");
	} 
	ob_flush();
	return;
} elseif ($orig_host != $ourhostname) {
	/* the client thinks it's connected to the desired web server, but instead
	   it's connected to us. Issue a redirect... */
	$protocol = (isset($cpcfg['httpslogin'])) ? 'https://' : 'http://';
	header("Location: {$protocol}{$ourhostname}/index.php?zone={$cpzone}&redirurl=" . urlencode("http://{$orig_host}/{$orig_request}"));

	ob_flush();
	return;
}

$macfilter = !isset($cpcfg['nomacfilter']);

/* find MAC address for client */
if ($macfilter || isset($cpcfg['passthrumacadd'])) {
	$tmpres = pfSense_ip_to_mac($clientip);
	if (!is_array($tmpres)) {
		/* unable to find MAC address - shouldn't happen! - bail out */
		captiveportal_logportalauth("unauthenticated", "noclientmac", $clientip, "ERROR");
		echo "An error occurred.  Please check the system logs for more information.";
		log_error("Zone: {$cpzone} - Captive portal could not determine client's MAC address.  Disable MAC address filtering in captive portal if you do not need this functionality.");
		ob_flush();
		return;
	}
	$clientmac = $tmpres['macaddr'];
	unset($tmpres);
}

if ($_POST['logout_id']) {
	$safe_logout_id = SQLite3::escapeString($_POST['logout_id']);
	captiveportal_disconnect_client($safe_logout_id);
	header("Location: index.php?zone=".$cpzone);
} elseif (($_POST['accept'] || $cpcfg['auth_method'] === 'radmac' || !empty($cpcfg['blockedmacsurl'])) && $macfilter && $clientmac && captiveportal_blocked_mac($clientmac)) {
	captiveportal_logportalauth($clientmac, $clientmac, $clientip, "Blocked MAC address");
	if (!empty($cpcfg['blockedmacsurl'])) {
		portal_reply_page($cpcfg['blockedmacsurl'], "redir");
	} else {
		if ($cpcfg['auth_method'] === 'radmac') {
			echo gettext("This MAC address has been blocked");
		} else {
			portal_reply_page($redirurl, "error", "This MAC address has been blocked");
		}
	}
} elseif (portal_consume_passthrough_credit($clientmac)) {
	/* allow the client through if it had a pass-through credit for its MAC */
	captiveportal_logportalauth("unauthenticated", $clientmac, $clientip, "ACCEPT");
	portal_allow($clientip, $clientmac, "unauthenticated", null, $redirurl);

} elseif (isset($config['voucher'][$cpzone]['enable']) && ($_POST['accept'] && $_POST['auth_voucher']) || $_GET['voucher']) {
	if (isset($_POST['auth_voucher'])) {
		$voucher = trim($_POST['auth_voucher']);
	} else {
		/* submit voucher via URL, see https://redmine.pfsense.org/issues/1984 */
		$voucher = trim($_GET['voucher']);
		portal_reply_page($redirurl, "login", null, $clientmac, $clientip, null, null, $voucher);
		return;
	}
	$errormsg = gettext("Invalid credentials specified.");
	$timecredit = voucher_auth($voucher);
	// $timecredit contains either a credit in minutes or an error message
	if ($timecredit > 0) {  // voucher is valid. Remaining minutes returned
		// if multiple vouchers given, use the first as username
		$a_vouchers = preg_split("/[\t\n\r ]+/s", $voucher);
		$voucher = $a_vouchers[0];
		$attr = array(
			'voucher' => 1,
			'session_timeout' => $timecredit*60,
			'session_terminate_time' => 0);
		if (portal_allow($clientip, $clientmac, $voucher, null, $redirurl, $attr, null, 'voucher', 'voucher') === 2) {
			portal_reply_page($redirurl, "error", "Reuse of identification not allowed.");
		} elseif (portal_allow($clientip, $clientmac, $voucher, null, $redirurl, $attr, null, 'voucher', 'voucher')) {
			// YES: user is good for $timecredit minutes.
			captiveportal_logportalauth($voucher, $clientmac, $clientip, "Voucher login good for $timecredit min.");
		} else {
			portal_reply_page($redirurl, "error", $config['voucher'][$cpzone]['descrmsgexpired'] ? $config['voucher'][$cpzone]['descrmsgexpired']: $errormsg);
		}
	} elseif (-1 == $timecredit) {  // valid but expired
		captiveportal_logportalauth($voucher, $clientmac, $clientip, "FAILURE", "voucher expired");
		portal_reply_page($redirurl, "error", $config['voucher'][$cpzone]['descrmsgexpired'] ? $config['voucher'][$cpzone]['descrmsgexpired']: $errormsg);
	} else {
		captiveportal_logportalauth($voucher, $clientmac, $clientip, "FAILURE");
		portal_reply_page($redirurl, "error", $config['voucher'][$cpzone]['descrmsgnoaccess'] ? $config['voucher'][$cpzone]['descrmsgnoaccess'] : $errormsg);
	}

} elseif ($_POST['accept'] || $cpcfg['auth_method'] === 'radmac') {
	
		if ($cpcfg['auth_method'] === 'radmac' && !isset($_POST['accept'])) {
			$user = $clientmac; 
			$passwd = $cpcfg['radmac_secret'];
			$context = 'radmac'; // Radius MAC authentication
		} elseif (!empty(trim($_POST['auth_user2']))) { 
			$user = trim($_POST['auth_user2']);
			$passwd = $_POST['auth_pass2'];
			$context = 'second'; // Assume users to use the first context if auth_user2 is empty/does not exist
		} else {
			$user = trim($_POST['auth_user']);
			$passwd = $_POST['auth_pass'];
			$context = 'first';
		}
	
	$pipeno = captiveportal_get_next_dn_ruleno('auth');
	/* if the pool is empty, return appropriate message and exit */
	if (is_null($pipeno)) {
		$replymsg = gettext("System reached maximum login capacity");
		if ($cpcfg['auth_method'] === 'radmac') {
			echo $replymsg;
			ob_flush();
			return;
		} else {
			portal_reply_page($redirurl, "error", $replymsg);
		}
		log_error("Zone: {$cpzone} - WARNING!  Captive portal has reached maximum login capacity");
		
	}
	
	$auth_result = captiveportal_authenticate_user($user, $passwd, $clientmac, $clientip, $pipeno, $context);
	
	if ($auth_result['result']) {
		captiveportal_logportalauth($user, $clientmac, $clientip, $auth_result['login_status']);
		portal_allow($clientip, $clientmac, $user, $passwd, $redirurl, $auth_result['attributes'], $pipeno, $auth_result['auth_method'], $context);

	} else {
		captiveportal_free_dn_ruleno($pipeno);
		$type = "error";
			
		if (is_URL($auth_result['attributes']['url_redirection'])) {
			$redirurl = $auth_result['attributes']['url_redirection'];
			$type = "redir";
		}
		
		if ($auth_result['login_message']) {
			$replymsg = $auth_result['login_message'];
		} else {
			$replymsg = gettext("Invalid credentials specified.");
		}
		
		captiveportal_logportalauth($user, $clientmac, $clientip, $auth_result['login_status'], $replymsg);

		/* Radius MAC authentication. */
		if ($context === 'radmac' && $type !== 'redir' && !isset($cpcfg['radmac_fallback'])) {
			echo $replymsg;
		} else {
			portal_reply_page($redirurl, $type, $replymsg);
		}
	}
} else {
	/* display captive portal page */
	portal_reply_page($redirurl, "login", null, $clientmac, $clientip);
}

ob_flush();

?>
