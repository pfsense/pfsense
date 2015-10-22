<?php
/* $Id$ */
/*
	pfSense_MODULE: authgui
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2004, 2005 Scott Ullrich
 *	Copyright (c)  2005-2006 Bill Marquette <bill.marquette@gmail.com>
 *	Copyright (c)  2006 Paul Taylor <paultaylor@winn-dixie.com>.
 *	Copyright (c)  2003-2006 Manuel Kasper <mk@neon1.net>.
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */
include_once("auth.inc");
include_once("priv.inc");
if (!function_exists('platform_booting')) {
	require_once('globals.inc');
}

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
$allowedpages = getAllowedPages($_SESSION['Username'], $_SESSION['user_radius_attributes']);

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
		if (!empty($_SERVER['REMOTE_ADDR'])) {
			$username .= '@' . $_SERVER['REMOTE_ADDR'];
		}
		log_error("{$username} attempted to access {$_SERVER['SCRIPT_NAME']} but does not have access to that page. Redirecting to {$page}.");

		exit;
	} else {
		display_error_form("201", gettext("No page assigned to this user! Click here to logout."));
		exit;
	}
} else {
	$_SESSION['Post_Login'] = true;
}

/*
 * redirect browsers post-login to avoid pages
 * taking action in response to a POST request
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

	if (isAjax()) {
		printf(gettext('Error: %1$s Description: %2$s'), $http_code, $desc);
		return;
	}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<link rel="stylesheet" href="/bootstrap/css/pfSense.css" />
	<title><?=gettext("Error: not allowed"); ?></title>
</head>
<body id="error" class="no-menu">
	<div id="jumbotron">
		<div class="container">
			<div class="col-sm-offset-3 col-sm-6 col-xs-12">
				<!-- FIXME: We really need to POST the logout action -->
				<div class="alert alert-danger" role="alert"><a href="index.php?logout"><?=$desc;?></a></div>
			</div>
		</div>
	</div>
</body>
</html>
<?php

} // end function


function display_login_form() {
	require_once("globals.inc");
	global $config, $g;

	unset($input_errors);

	if (isAjax()) {
		if (isset($_POST['login'])) {
			if ($_SESSION['Logged_In'] <> "True") {
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
if (strpos($_SERVER['HTTP_HOST'], ":") === FALSE) {
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
	if ($iflist['ip'] == $http_host) {
		$local_ip = true;
	} else if ($iflist['ipv6'] == $http_host) {
		$local_ip = true;
	} else if (is_array($iflist['vips'])) {
		foreach ($iflist['vips'] as $vip) {
			if ($vip['ip'] == $http_host) {
				$local_ip = true;
				break;
			}
		}
		unset($vip);
	}
	if ($local_ip == true) {
		break;
	}
}
unset($FilterIflist);
unset($iflist);

if ($local_ip == false) {
	if (is_array($config['openvpn']['openvpn-server'])) {
		foreach ($config['openvpn']['openvpn-server'] as $ovpns) {
			if (is_ipaddrv4($http_host) && !empty($ovpns['tunnel_network']) && ip_in_subnet($http_host, $ovpns['tunnel_network'])) {
				$local_ip = true;
			} else if (is_ipaddrv6($http_host) && !empty($ovpns['tunnel_networkv6']) && ip_in_subnet($http_host, $ovpns['tunnel_networkv6'])) {
				$local_ip = true;
			}
			if ($local_ip == true) {
				break;
			}
		}
	}
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<link rel="stylesheet" href="/bootstrap/css/pfSense.css" />
	<title><?=gettext("Login"); ?></title>
	<script>var events = events || [];</script>
</head>
<body id="login" class="no-menu">
	<div id="jumbotron">
		<div class="container">
			<div class="col-sm-offset-3 col-sm-6 col-xs-12">
<?php
	if (is_ipaddr($http_host) && !$local_ip && !isset($config['system']['webgui']['nohttpreferercheck'])) {
		$nifty_background = "#999";
		print_info_box(gettext("You are accessing this router by an IP address not configured locally, which may be forwarded by NAT or other means. <br /><br />If you did not setup this forwarding, you may be the target of a man-in-the-middle attack."));
	}

	$loginautocomplete = isset($config['system']['webgui']['loginautocomplete']) ? '' : 'autocomplete="off"';
?>

				<div class="panel panel-default">
					<div class="panel-heading">
						<h2 class="panel-title">Login to pfSense</h2>
					</div>

					<div class="panel-body">
<?php if (!empty($_SESSION['Login_Error'])): ?>
						<div class="alert alert-danger" role="alert"><?=$_SESSION['Login_Error'];?></div>
<?php endif ?>
						<div class="alert alert-warning" class="hidden" id="no_cookies"><?= gettext("Your browser must support cookies to login."); ?></div>

						<form method="post" <?= $loginautocomplete ?> action="<?=$_SERVER['SCRIPT_NAME'];?>" class="form-horizontal">
							<div class="form-group">
								<label for="usernamefld" class="col-sm-3 control-label">Username</label>
								<div class="col-sm-9 col-md-7">
									<input type="text" class="form-control" name="usernamefld" id="usernamefld" placeholder="Enter your username">
								</div>
							</div>

							<div class="form-group">
								<label for="passwordfld" class="col-sm-3 control-label">Password</label>
								<div class="col-sm-9 col-md-7">
									<input type="password" class="form-control" name="passwordfld" id="passwordfld" placeholder="Enter your password">
								</div>
							</div>

							<div class="form-group">
								<div class="col-sm-offset-3 col-sm-9 col-md-7">
									<button type="submit" class="btn btn-primary" name="login">Login</button>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>

		<script>
		events.push(function() {
			document.cookie=
				"cookie_test=1" +
				"<?php echo $config['system']['webgui']['protocol'] == 'https' ? '; secure' : '';?>";

			if (document.cookie.indexOf("cookie_test") == -1)
				document.getElementById("no_cookies").style.display="";
			else
				document.getElementById("no_cookies").style.display="none";

			// Delete it
			document.cookie = "cookie_test=1; expires=Thu, 01-Jan-1970 00:00:01 GMT";
		});
		</script>
<?php
require('foot.inc');

} // end function
