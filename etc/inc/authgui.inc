<?php
/* $Id$ */
/*
	Copyright (C) 2007, 2008 Scott Ullrich <sullrich@gmail.com>
	All rights reserved.

        Copyright (C) 2005-2006 Bill Marquette <bill.marquette@gmail.com>
        All rights reserved.

        Copyright (C) 2006 Paul Taylor <paultaylor@winn-dixie.com>.
        All rights reserved.

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

		pfSense_MODULE: authgui
*/

include_once("auth.inc");
include_once("priv.inc");

/* Authenticate user - exit if failed */
if (!session_auth()) {
	display_login_form();
	exit;
}

/*
 * Once here, the user has authenticated with the web server.
 * We give them access only to the appropriate pages based on
 * the user or group privileges.
 */
$allowedpages = getAllowedPages($HTTP_SERVER_VARS['AUTH_USER']);

/*
 * redirect to first allowed page if requesting a wrong url
 */
if (!isAllowedPage($_SERVER['REQUEST_URI'])) {
	if (count($allowedpages) > 0) {
		$page = str_replace('*', '', $allowedpages[0]);
		$_SESSION['Post_Login'] = true;
		require_once("functions.inc");
		pfSenseHeader("/{$page}");

		$username = empty($_SESSION["Username"]) ? "(system)" : $_SESSION['Username'];
		if (!empty($_SERVER['REMOTE_ADDR']))
			$username .= '@' . $_SERVER['REMOTE_ADDR'];
		log_error("{$username} attempted to access {$_SERVER['SCRIPT_NAME']} but does not have access to that page. Redirecting to {$page}.");

		exit;
	} else {
		display_error_form("201", gettext("No page assigned to this user! Click here to logout."));
		exit;
	}
} else 
	$_SESSION['Post_Login'] = true;

/*
 * redirect browsers post-login to avoid pages
 * taking action in reponse to a POST request
 */
if (!$_SESSION['Post_Login']) {
	$_SESSION['Post_Login'] = true;
	require_once("functions.inc");
	pfSenseHeader($_SERVER['REQUEST_URI']);
	exit;
}

/* 
 * Close session data to allow other scripts from same host to come in.
 * A session can be reactivated from calling session_start again
 */
session_commit();

/*
 * determine if the user is allowed access to the requested page
 */
function display_error_form($http_code, $desc) {
	global $config, $g;
	$g['theme'] = get_current_theme();
	if(isAjax()) {
		printf(gettext('Error: %1$s Description: %2$s'), $http_code, $desc);
		return;
	}

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
   "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
	<head>
		<script type="text/javascript" src="/javascript/jquery.js"></script>
		<title><?=$http_code?></title>
		<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
		<link rel="shortcut icon" href="/themes/<?= $g['theme'] ?>/images/icons/favicon.ico" />
		<?php if (file_exists("{$g['www_path']}/themes/{$g['theme']}/login.css")): ?>
		<link rel="stylesheet" type="text/css" href="/themes/<?= $g['theme'] ?>/login.css" media="all" />
		<?php else: ?>
		<link rel="stylesheet" type="text/css" href="/themes/<?= $g['theme'] ?>/all.css" media="all" />
		<?php endif; ?>
		<script type="text/javascript">
		//<![CDATA[
			function page_load() {}
			function clearError() {
				if($('#inputerrors'))
				$('#inputerrors').html('');
			}
			<?php
				require("headjs.php");
				echo getHeadJS();
			?>
		//]]>
		</script>
		<script type="text/javascript" src="/themes/<?= $g['theme'] ?>/javascript/niftyjsCode.js"></script>
	</head>
	<body onload="page_load();">
		<div id="errordesc">
			<h1>&nbsp</h1>
			<a href="/index.php?logout">
			<p id="errortext" style="vertical-align: middle; text-align: center;">
				<span style="color: #000000; font-weight: bold;">
					<?=$desc;?>
				</span>
			</p>
		</div>
	</body>
</html>

<?php

} // end function


function display_login_form() {
	require_once("globals.inc");
	global $config, $g;
	$g['theme'] = get_current_theme();

	unset($input_errors);

	if(isAjax()) {
		if (isset($_POST['login'])) {
			if($_SESSION['Logged_In'] <> "True") {
				isset($_SESSION['Login_Error']) ? $login_error = $_SESSION['Login_Error'] : $login_error = gettext("unknown reason");
				printf("showajaxmessage('" . gettext("Invalid login (%s).") . "')", $login_error);
			}
			if (file_exists("{$g['tmp_path']}/webconfigurator.lock")) {
				// TODO: add the IP from the user who did lock the device
				$whom = file_get_contents("{$g['tmp_path']}/webconfigurator.lock");
				printf("showajaxmessage('" . gettext("This device is currently being maintained by: %s.") . "');", $whom);
			}
		}
		exit;
	}

/* Check against locally configured IP addresses, which will catch when someone 
   port forwards WebGUI access from WAN to an internal IP on the router. */
global $FilterIflist, $nifty_background;
$local_ip = false;
if(strstr($_SERVER['HTTP_HOST'], ":")) {
	$http_host_port = explode(":", $_SERVER['HTTP_HOST']);
	$http_host = $http_host_port[0];
} else {
	$http_host = $_SERVER['HTTP_HOST'];
}
if (empty($FilterIflist)) {
	require_once('filter.inc');
	require_once('shaper.inc');
	filter_generate_optcfg_array();
}
foreach ($FilterIflist as $iflist) {
	if($iflist['ip'] == $http_host)
		$local_ip = true;
	if($iflist['ipv6'] == $http_host)
		$local_ip = true;
}
if($config['virtualip']) {
	if($config['virtualip']['vip']) {
		foreach($config['virtualip']['vip'] as $vip) {
			if($vip['subnet'] == $http_host)
				$local_ip = true;
		}
	}
}
if (is_array($config['openvpn']['openvpn-server'])) {
	foreach ($config['openvpn']['openvpn-server'] as $ovpns) {
		if (is_ipaddrv4($http_host) && !empty($ovpns['tunnel_network']) && ip_in_subnet($http_host, $ovpns['tunnel_network'])) {
			$local_ip = true;
			break;
		}

		if (is_ipaddrv6($http_host) && !empty($ovpns['tunnel_networkv6']) && ip_in_subnet($http_host, $ovpns['tunnel_networkv6'])) {
			$local_ip = true;
			break;
		}
	}
}
setcookie("cookie_test", time() + 3600);
$have_cookies = isset($_COOKIE["cookie_test"]);

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
   "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
	<head>
		<script type="text/javascript" src="/javascript/jquery.js"></script>
		<script type="text/javascript">
		//<![CDATA[
		$(document).ready(function() { jQuery('#usernamefld').focus(); });
		//]]>
		</script>

		<title><?=gettext("Login"); ?></title>
		<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
		<link rel="shortcut icon" href="/themes/<?= $g['theme'] ?>/images/icons/favicon.ico" />
		<?php if (file_exists("{$g['www_path']}/themes/{$g['theme']}/login.css")): ?>
		<link rel="stylesheet" type="text/css" href="/themes/<?= $g['theme'] ?>/login.css" media="all" />
		<?php else: ?>
		<link rel="stylesheet" type="text/css" href="/themes/<?= $g['theme'] ?>/all.css" media="all" />
		<?php endif; ?>
		<script type="text/javascript">
		//<![CDATA[
			function page_load() {}
			function clearError() {
				if($('#inputerrors'))
				$('#inputerrors').html('');
			}
			<?php
				require("headjs.php");
				echo getHeadJS();
			?>
		//]]>
		</script>
		<script type="text/javascript" src="/themes/<?= $g['theme'] ?>/javascript/niftyjsCode.js"></script>
	</head>
	<body onload="page_load()">
		<div id="login">
			<?php 
				if(is_ipaddr($http_host) && !$local_ip && !isset($config['system']['webgui']['nohttpreferercheck'])) {
					$nifty_background = "#999";
					print_info_box(gettext("You are accessing this router by an IP address not configured locally, which may be forwarded by NAT or other means. <br/><br/>If you did not setup this forwarding, you may be the target of a man-in-the-middle attack.")); 
				}
				$noautocomplete = isset($config['system']['webgui']['noautocomplete']) ? 'autocomplete="off"' : '';
			?>
			<form id="iform" name="iform" method="post" <?= $noautocomplete ?> action="<?=$_SERVER['SCRIPT_NAME'];?>">
				<h1>&nbsp;</h1>
				<div id="inputerrors"><?=$_SESSION['Login_Error'];?></div>
				<p>
					<span style="text-align:left">
						<?=gettext("Username:"); ?><br />
						<input onclick="clearError();" onchange="clearError();" id="usernamefld" type="text" name="usernamefld" class="formfld user" tabindex="1" />
					</span>
				</p>
				<p>
					<br />
					<span style="text-align:left">
						<?=gettext("Password:"); ?> <br />
						<input onclick="clearError();" onchange="clearError();" id="passwordfld" type="password" name="passwordfld" class="formfld pwd" tabindex="2" />
					</span>
				</p>
				<p>
					<br />
					<span style="text-align:center; font-weight: normal ; font-style: italic">
						<?=gettext("Enter username and password to login."); ?>
					</span>

					<?php if (!$have_cookies && isset($_POST['login'])): ?>
					<br/><br/>
					<span style="text-align:center; font-weight: normal ; font-style: italic; color: #ff0000">
						<?= gettext("Your browser must support cookies to login."); ?>
					</span>
					<?php endif; ?>
				</p>        
				<p>
					<span style="text-align:center">
						<input type="submit" name="login" class="formbtn" value="<?=gettext("Login"); ?>" tabindex="3" />
					</span>
				</p>
			</form>
		</div>
	</body>
</html>
<?php
} // end function

?>
