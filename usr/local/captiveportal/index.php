<?php
/*
	$Id$
	part of m0n0wall (http://m0n0.ch/wall)

	Copyrigth (C) 2009	    Ermal Luçi
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

require_once("auth.inc");
require_once("functions.inc");
require_once("captiveportal.inc");

$errormsg = "Invalid credentials specified.";

header("Expires: 0");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Connection: close");

global $cpzone, $cpzoneid;

$cpzone = $_REQUEST['zone'];
$cpcfg = $config['captiveportal'][$cpzone];
if (empty($cpcfg)) {
	log_error("Submission to captiveportal with unkown parameter zone: " . htmlspecialchars($cpzone));
	portal_reply_page($redirurl, "error", $errormsg);
	ob_flush();
	return;
}

$cpzoneid = $cpcfg['zoneid'];

$orig_host = $_SERVER['HTTP_HOST'];
/* NOTE: IE 8/9 is buggy and that is why this is needed */
$orig_request = trim($_REQUEST['redirurl'], " /");
$clientip = $_SERVER['REMOTE_ADDR'];

if (!$clientip) {
	/* not good - bail out */
	log_error("Zone: {$cpzone} - Captive portal could not determine client's IP address.");
	$error_message = "An error occurred.  Please check the system logs for more information.";
	portal_reply_page($redirurl, "error", $errormsg);
	ob_flush();
	return;
}

$ourhostname = portal_hostname_from_client_ip($clientip);
if ($orig_host != $ourhostname) {
	/* the client thinks it's connected to the desired web server, but instead
	   it's connected to us. Issue a redirect... */
	$protocol = (isset($cpcfg['httpslogin'])) ? 'https://' : 'http://';
	header("Location: {$protocol}{$ourhostname}/index.php?zone={$cpzone}&redirurl=" . urlencode("http://{$orig_host}/{$orig_request}"));

	ob_flush();
	return;
}

if (!empty($cpcfg['redirurl']))
	$redirurl = $cpcfg['redirurl'];
else if (preg_match("/redirurl=(.*)/", $orig_request, $matches))
	$redirurl = urldecode($matches[1]);
else if ($_REQUEST['redirurl'])
	$redirurl = $_REQUEST['redirurl'];

$macfilter = !isset($cpcfg['nomacfilter']);
$passthrumac = isset($cpcfg['passthrumacadd']);

/* find MAC address for client */
if ($macfilter || $passthrumac) {
	$tmpres = pfSense_ip_to_mac($clientip);
	if (!is_array($tmpres)) {
		/* unable to find MAC address - shouldn't happen! - bail out */
		captiveportal_logportalauth("unauthenticated","noclientmac",$clientip,"ERROR");
		echo "An error occurred.  Please check the system logs for more information.";
		log_error("Zone: {$cpzone} - Captive portal could not determine client's MAC address.  Disable MAC address filtering in captive portal if you do not need this functionality.");
		ob_flush();
		return;
	}
	$clientmac = $tmpres['macaddr'];
	unset($tmpres);
}

/* find out if we need RADIUS + RADIUSMAC or not */
if (file_exists("{$g['vardb_path']}/captiveportal_radius_{$cpzone}.db")) {
	$radius_enable = TRUE;
	if (isset($cpcfg['radmac_enable']))
		$radmac_enable = TRUE;
}

/* find radius context */
$radiusctx = 'first';
if ($_POST['auth_user2'])
	$radiusctx = 'second';

if ($_POST['logout_id']) {
	echo <<<EOD
<html>
<head><title>Disconnecting...</title></head>
<body bgcolor="#435370">
<span style="color: #ffffff; font-family: Tahoma, Verdana, Arial, Helvetica, sans-serif; font-size: 11px;">
<b>You have been disconnected.</b>
</span>
<script type="text/javascript">
<!--
setTimeout('window.close();',5000) ;
-->
</script>
</body>
</html>

EOD;
	captiveportal_disconnect_client($_POST['logout_id']);

} else if ($macfilter && $clientmac && captiveportal_blocked_mac($clientmac)) {
	captiveportal_logportalauth($clientmac,$clientmac,$clientip,"Blocked MAC address");
	if (!empty($cpcfg['blockedmacsurl']))
		portal_reply_page($cpcfg['blockedmacsurl'], "redir");
	else
		portal_reply_page($redirurl, "error", "This MAC address has been blocked");

} else if ($clientmac && $radmac_enable && portal_mac_radius($clientmac,$clientip, $radiusctx)) {
	/* radius functions handle everything so we exit here since we're done */

} else if (portal_consume_passthrough_credit($clientmac)) {
	/* allow the client through if it had a pass-through credit for its MAC */
	captiveportal_logportalauth("unauthenticated",$clientmac,$clientip,"ACCEPT");
	portal_allow($clientip, $clientmac, "unauthenticated");

} else if (isset($config['voucher'][$cpzone]['enable']) && $_POST['accept'] && $_POST['auth_voucher']) {
	$voucher = trim($_POST['auth_voucher']);
	$timecredit = voucher_auth($voucher);
	// $timecredit contains either a credit in minutes or an error message
	if ($timecredit > 0) {  // voucher is valid. Remaining minutes returned
		// if multiple vouchers given, use the first as username
		$a_vouchers = preg_split("/[\t\n\r ]+/s",$voucher);
		$voucher = $a_vouchers[0];
		$attr = array( 'voucher' => 1,
				'session_timeout' => $timecredit*60,
				'session_terminate_time' => 0);
		if (portal_allow($clientip, $clientmac,$voucher,null,$attr)) {
			// YES: user is good for $timecredit minutes.
			captiveportal_logportalauth($voucher,$clientmac,$clientip,"Voucher login good for $timecredit min.");
		} else {
			portal_reply_page($redirurl, "error", $config['voucher'][$cpzone]['msgexpired'] ? $config['voucher'][$cpzone]['msgexpired']: $errormsg);
		}
	} else if (-1 == $timecredit) {  // valid but expired
		captiveportal_logportalauth($voucher,$clientmac,$clientip,"FAILURE","voucher expired");
		portal_reply_page($redirurl, "error", $config['voucher'][$cpzone]['msgexpired'] ? $config['voucher'][$cpzone]['msgexpired']: $errormsg);
	} else {
		captiveportal_logportalauth($voucher,$clientmac,$clientip,"FAILURE");
		portal_reply_page($redirurl, "error", $config['voucher'][$cpzone]['msgnoaccess'] ? $config['voucher'][$cpzone]['msgnoaccess'] : $errormsg);
	}

} else if ($_POST['accept'] && $radius_enable) {
	if (($_POST['auth_user'] && isset($_POST['auth_pass'])) || ($_POST['auth_user2'] && isset($_POST['auth_pass2']))) {
		if (!empty($_POST['auth_user'])) {
			$user = $_POST['auth_user'];
			$paswd = $_POST['auth_pass'];
		} else if (!empty($_POST['auth_user2'])) {
			$user = $_POST['auth_user2'];
			$paswd = $_POST['auth_pass2'];
		}
		$auth_list = radius($user,$paswd,$clientip,$clientmac,"USER LOGIN", $radiusctx);
		$type = "error";
		if (!empty($auth_list['url_redirection'])) {
			$redirurl = $auth_list['url_redirection'];
			$type = "redir";
		}

		if ($auth_list['auth_val'] == 1) {
			captiveportal_logportalauth($user,$clientmac,$clientip,"ERROR",$auth_list['error']);
			portal_reply_page($redirurl, $type, $auth_list['error'] ? $auth_list['error'] : $errormsg);
		} else if ($auth_list['auth_val'] == 3) {
			captiveportal_logportalauth($user,$clientmac,$clientip,"FAILURE",$auth_list['reply_message']);
			portal_reply_page($redirurl, $type, $auth_list['reply_message'] ? $auth_list['reply_message'] : $errormsg);
		}
	} else {
		if (!empty($_POST['auth_user']))
			$user = $_POST['auth_user'];
		else if (!empty($_POST['auth_user2']))
			$user = $_POST['auth_user2'];
		else 
			$user = 'unknown';
		captiveportal_logportalauth($user ,$clientmac,$clientip,"ERROR");
		portal_reply_page($redirurl, "error", $errormsg);
	}

} else if ($_POST['accept'] && $cpcfg['auth_method'] == "local") {
	if ($_POST['auth_user'] && $_POST['auth_pass']) {
		//check against local user manager
		$loginok = local_backed($_POST['auth_user'], $_POST['auth_pass']);

		if ($loginok && isset($cpcfg['localauth_priv']))
			$loginok = userHasPrivilege(getUserEntry($_POST['auth_user']), "user-services-captiveportal-login");

		if ($loginok){
			captiveportal_logportalauth($_POST['auth_user'],$clientmac,$clientip,"LOGIN");
			portal_allow($clientip, $clientmac,$_POST['auth_user']);
		} else {
			captiveportal_logportalauth($_POST['auth_user'],$clientmac,$clientip,"FAILURE");
			portal_reply_page($redirurl, "error", $errormsg);
		}
	} else
		portal_reply_page($redirurl, "error", $errormsg);

} else if ($_POST['accept'] && $clientip && $cpcfg['auth_method'] == "none") {
	captiveportal_logportalauth("unauthenticated",$clientmac,$clientip,"ACCEPT");
	portal_allow($clientip, $clientmac, "unauthenticated");

} else {
	/* display captive portal page */
	portal_reply_page($redirurl, "login",null,$clientmac,$clientip);
}

ob_flush();

?>
