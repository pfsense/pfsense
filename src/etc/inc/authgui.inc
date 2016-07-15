<?php
/*
 * authgui.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2003-2006 Manuel Kasper <mk@neon1.net>
 * Copyright (c) 2005-2006 Bill Marquette <bill.marquette@gmail.com>
 * Copyright (c) 2006 Paul Taylor <paultaylor@winn-dixie.com>
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
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
 * Get user-based preference settings so they can be easily referenced.
 */
$user_settings = get_user_settings($_SESSION['Username']);

/*
 * redirect to first allowed page if requesting a wrong url
 */

/* Fix this up otherwise the privilege check will fail. See Redmine #5909. */
if ($_SERVER['REQUEST_URI'] == "/") {
	$_SERVER['REQUEST_URI'] = "/index.php";
}

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
	global $config, $user_settings, $g;

	if (isAjax()) {
		printf(gettext('Error: %1$s Description: %2$s'), $http_code, $desc);
		return;
	}

	$cssfile = "/css/pfSense.css";

	if (isset($user_settings['webgui']['webguicss'])) {
		if (file_exists("/usr/local/www/css/" . $user_settings['webgui']['webguicss'])) {
			$cssfile = "/css/" . $user_settings['webgui']['webguicss'];
		}
	}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="<?=$cssfile?>" />
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

// For the login form, get the settings of no particular user.
// That ensures we will use the system default theme for the login form.
$user_settings = get_user_settings("");
$cssfile = "/css/pfSense.css";

if (isset($user_settings['webgui']['webguicss'])) {
	if (file_exists("/usr/local/www/css/" . $user_settings['webgui']['webguicss'])) {
		$cssfile = "/css/" . $user_settings['webgui']['webguicss'];
	}
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="<?=$cssfile?>" />
	<title><?=gettext("Login"); ?></title>
	<script type="text/javascript">
	//<![CDATA{
	var events = events || [];
	//]]>
	</script>
</head>
<body id="login" class="no-menu">
	<div id="jumbotron">
		<div class="container">
			<div class="col-sm-offset-3 col-sm-6 col-xs-12">
<?php
	if (is_ipaddr($http_host) && !$local_ip && !isset($config['system']['webgui']['nohttpreferercheck'])) {
		$nifty_background = "#999";
		print_info_box(gettext("The IP address being used to access this router is not configured locally, which may be forwarded by NAT or other means. <br /><br />If this forwarding is unexpected, it should be verified that a man-in-the-middle attack is not taking place."));
	}

	$loginautocomplete = isset($config['system']['webgui']['loginautocomplete']) ? '' : 'autocomplete="off"';
?>

				<div class="panel panel-default">
					<div class="panel-heading">
						<h2 class="panel-title"><?=sprintf(gettext("Login to %s"), $g['product_name'])?></h2>
					</div>

					<div class="panel-body">
<?php if (!empty($_SESSION['Login_Error'])): ?>
						<div class="alert alert-danger" role="alert"><?=$_SESSION['Login_Error'];?></div>
<?php endif ?>
						<div class="alert alert-warning hidden" id="no_cookies"><?= gettext("The browser must support cookies to login."); ?></div>

						<form method="post" <?= $loginautocomplete ?> action="<?=$_SERVER['SCRIPT_NAME'];?>" class="form-horizontal">
							<div class="form-group">
								<label for="usernamefld" class="col-sm-3 control-label"><?=gettext("Username")?></label>
								<div class="col-sm-9 col-md-7">
									<input type="text" class="form-control" name="usernamefld" id="usernamefld" placeholder="<?=gettext("Enter your username")?>" autocorrect="off" autocapitalize="none" spellcheck="false">
								</div>
							</div>

							<div class="form-group">
								<label for="passwordfld" class="col-sm-3 control-label"><?=gettext("Password")?></label>
								<div class="col-sm-9 col-md-7">
									<input type="password" class="form-control" name="passwordfld" id="passwordfld" placeholder="<?=gettext("Enter your password")?>">
								</div>
							</div>

							<div class="form-group">
								<div class="col-sm-offset-3 col-sm-9 col-md-7">
									<button type="submit" class="btn btn-primary" name="login"><?=gettext("Login")?></button>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>

		<script type="text/javascript">
		//!<[CDATA[
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
		//]]>
		</script>
<?php
require_once('foot.inc');

} // end function
