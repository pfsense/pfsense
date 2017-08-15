<?php
/*
 * authgui.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2003-2006 Manuel Kasper <mk@neon1.net>
 * Copyright (c) 2005-2006 Bill Marquette <bill.marquette@gmail.com>
 * Copyright (c) 2006 Paul Taylor <paultaylor@winn-dixie.com>
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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
require_once('pfsense-utils.inc');

/* Authenticate user - exit if failed */
if (!session_auth()) {
	display_login_form();
	exit;
}
phpsession_begin();
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
 * A session can be reactivated from calling phpsession_begin again
 */
phpsession_end(true);

/*
 * determine if the user is allowed access to the requested page
 */
function display_error_form($http_code, $desc) {
	global $config, $user_settings, $g;

	if (isAjax()) {
		printf(gettext('Error: %1$s Description: %2$s'), $http_code, $desc);
		return;
	}

	$logincssfile = "#770101";
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta name="viewport" content="width=device-width, initial-scale=1">
	    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css" type="text/css">
	    <link rel="stylesheet" href="/css/login.css" type="text/css">
		<title><?=gettext("Error"); ?></title>
	</head>

	<body id="error" >
		<div id="total">
			<header>
				<div id="headerrow">
					<div class="row">
						<div class="col-sm-4">
							<div id="logodiv" style="text-align:center" class="nowarning">
								<svg role="img" aria-labelledby="pfsense-logo" x="0px" y="0px" viewBox="0 0 282.8 84.2" width="240" height="100%">
									<title id="pfsense-logo-svg">pfSense Logo</title>
									<style type="text/css">
										.logo-st0{fill:#2B40B5;}
										.logo-st1{fill:#1475CF;}
										.logo-st2{fill:#1C1275;}
									</style>
									<path class="logo-st0" d="M27.8,57.7c2.9,0,5.4-0.9,7.5-2.6c2.1-1.7,3.6-4,4.4-6.8c0.8-2.8,0.6-5.1-0.5-6.8c-1.1-1.7-3.2-2.6-6.1-2.6 c-2.9,0-5.4,0.9-7.5,2.6c-2.1,1.7-3.5,4-4.3,6.8c-0.8,2.8-0.7,5.1,0.5,6.8C22.8,56.9,24.8,57.7,27.8,57.7"/>
									<path class="logo-st0" d="M115.1,46.6c-1.5-0.8-3-1.4-4.7-1.8c-1.7-0.4-3.2-0.7-4.7-1.1c-1.5-0.3-2.7-0.7-3.6-1.1c-0.9-0.4-1.4-1.1-1.4-2 c0-1.1,0.5-1.9,1.4-2.4c0.9-0.5,1.9-0.7,2.8-0.7c2.8,0,5,1,6.7,3.1l7-7c-1.7-1.8-3.9-3.1-6.4-3.8c-2.5-0.7-5-1.1-7.4-1.1 c-1.9,0-3.9,0.2-5.7,0.7c-1.9,0.5-3.6,1.2-5,2.3c-1.5,1-2.6,2.3-3.5,3.9c-0.9,1.6-1.3,3.5-1.3,5.7c0,2.3,0.5,4.2,1.4,5.6 c0.9,1.4,2.1,2.5,3.6,3.3c1.5,0.8,3,1.3,4.7,1.7c1.7,0.4,3.2,0.7,4.7,1.1c1.5,0.3,2.7,0.7,3.6,1.2c0.9,0.5,1.4,1.2,1.4,2.2 c0,1-0.5,1.7-1.6,2.1c-1.1,0.4-2.3,0.6-3.6,0.6c-1.7,0-3.3-0.3-4.6-1c-1.3-0.7-2.5-1.7-3.6-3l-7,7.7c1.8,1.9,4.1,3.2,6.7,3.9 c2.7,0.7,5.3,1.1,7.9,1.1c2,0,4-0.2,6.1-0.6c2-0.4,3.9-1,5.5-2c1.6-0.9,3-2.2,4-3.8c1-1.6,1.6-3.5,1.6-5.9c0-2.3-0.5-4.2-1.4-5.6 C117.7,48.6,116.5,47.4,115.1,46.6"/>
									<path class="logo-st0" d="M156.3,34.1c-1.5-1.7-3.3-3-5.5-3.9c-2.2-0.9-4.6-1.4-7.2-1.4c-2.9,0-5.6,0.5-8.1,1.4c-2.5,0.9-4.7,2.2-6.6,3.9 c-1.9,1.7-3.3,3.8-4.4,6.2c-1.1,2.4-1.6,5.1-1.6,8c0,3,0.5,5.6,1.6,8c1.1,2.4,2.5,4.5,4.4,6.2c1.9,1.7,4.1,3,6.6,3.9 c2.5,0.9,5.2,1.4,8.1,1.4c3,0,5.9-0.6,8.7-1.9c2.8-1.3,5.1-3.1,7-5.4l-8-5.9c-1,1.3-2.1,2.4-3.4,3.3c-1.3,0.8-2.9,1.3-4.8,1.3 c-2.2,0-4.1-0.7-5.7-2c-1.5-1.3-2.5-3.1-3-5.2H161v-3.6c0-3-0.4-5.6-1.2-8C159,37.9,157.8,35.8,156.3,34.1 M134.3,44.1 c0.1-0.9,0.3-1.8,0.7-2.6c0.4-0.8,0.9-1.6,1.6-2.2c0.7-0.6,1.5-1.2,2.5-1.6c1-0.4,2.1-0.6,3.4-0.6c2.1,0,3.8,0.7,5.1,2.1 c1.3,1.4,2,3,1.9,5H134.3z"/>
									<path class="logo-st0" d="M198.3,33.8c-1-1.6-2.4-2.8-4.2-3.7c-1.8-0.9-4.1-1.3-7-1.3c-1.4,0-2.7,0.2-3.8,0.5c-1.2,0.4-2.2,0.8-3.1,1.4 c-0.9,0.6-1.7,1.2-2.4,1.9c-0.7,0.7-1.2,1.4-1.5,2.1H176v-5.1h-11v37.2h11.5V48.4c0-1.2,0.1-2.4,0.2-3.5c0.2-1.1,0.5-2.1,1-3 c0.5-0.9,1.2-1.6,2.1-2.1c0.9-0.5,2.1-0.8,3.6-0.8c1.5,0,2.6,0.3,3.4,0.9c0.8,0.6,1.4,1.4,1.8,2.4c0.4,1,0.6,2,0.7,3.2 c0.1,1.1,0.1,2.3,0.1,3.3v18.2h11.5V46.4c0-2.5-0.2-4.8-0.5-7C199.9,37.3,199.3,35.4,198.3,33.8"/>
									<path class="logo-st0" d="M231.5,46.6c-1.5-0.8-3-1.4-4.7-1.8c-1.7-0.4-3.2-0.7-4.7-1.1c-1.5-0.3-2.7-0.7-3.6-1.1c-0.9-0.4-1.4-1.1-1.4-2 c0-1.1,0.5-1.9,1.4-2.4c0.9-0.5,1.9-0.7,2.8-0.7c2.8,0,5,1,6.7,3.1l7-7c-1.7-1.8-3.9-3.1-6.4-3.8c-2.5-0.7-5-1.1-7.4-1.1 c-1.9,0-3.9,0.2-5.7,0.7c-1.9,0.5-3.6,1.2-5,2.3c-1.5,1-2.6,2.3-3.5,3.9c-0.9,1.6-1.3,3.5-1.3,5.7c0,2.3,0.5,4.2,1.4,5.6 c0.9,1.4,2.1,2.5,3.6,3.3c1.5,0.8,3,1.3,4.7,1.7c1.7,0.4,3.2,0.7,4.7,1.1c1.5,0.3,2.7,0.7,3.6,1.2c0.9,0.5,1.4,1.2,1.4,2.2 c0,1-0.5,1.7-1.6,2.1c-1.1,0.4-2.3,0.6-3.6,0.6c-1.7,0-3.3-0.3-4.6-1c-1.3-0.7-2.5-1.7-3.6-3l-7,7.7c1.8,1.9,4.1,3.2,6.7,3.9 c2.7,0.7,5.3,1.1,7.9,1.1c2,0,4-0.2,6.1-0.6c2-0.4,3.9-1,5.5-2c1.6-0.9,3-2.2,4-3.8c1-1.6,1.6-3.5,1.6-5.9c0-2.3-0.5-4.2-1.4-5.6 C234.1,48.6,232.9,47.4,231.5,46.6"/>
									<path class="logo-st0" d="M277.4,51.9v-4.2c-0.1-2.7-0.5-5.2-1.2-7.4c-0.8-2.4-2-4.5-3.5-6.2c-1.5-1.7-3.3-3-5.5-3.9 c-2.2-0.9-4.6-1.4-7.2-1.4c-2.9,0-5.6,0.5-8.1,1.4c-2.5,0.9-4.7,2.2-6.6,3.9c-1.9,1.7-3.3,3.8-4.4,6.2c-1.1,2.4-1.6,5.1-1.6,8 c0,3,0.5,5.6,1.6,8c1.1,2.4,2.5,4.5,4.4,6.2c1.9,1.7,4.1,3,6.6,3.9c2.5,0.9,5.2,1.4,8.1,1.4c3,0,5.9-0.6,8.7-1.9 c2.8-1.3,5.1-3.1,7-5.4l-8-5.9c-1,1.3-2.1,2.4-3.4,3.3c-1.3,0.8-2.9,1.3-4.8,1.3c-2.2,0-4.1-0.7-5.7-2c-1.5-1.3-2.5-3.1-3-5.2H277.4 z M250.7,44.1c0.1-0.9,0.3-1.8,0.7-2.6c0.4-0.8,0.9-1.6,1.6-2.2c0.7-0.6,1.5-1.2,2.5-1.6c1-0.4,2.1-0.6,3.4-0.6 c2.1,0,3.8,0.7,5.1,2.1c1.3,1.4,2,3,1.9,5H250.7z"/>
									<path class="logo-st1" d="M52.6,38.9l2.6-9.2h4.6l1.8-6.6c0.6-2,1.3-4,2.2-5.8c0.8-1.8,2-3.4,3.4-4.8c1.4-1.4,3.2-2.5,5.3-3.3 c2.1-0.8,4.8-1.2,7.9-1.2c0.8,0,1.5,0,2.3,0.1c-0.7-2.9-3.3-5-6.3-5.1H11.9c-3.6,0-6.5,3-6.5,6.6V67l10.5-37.3h10.6l-1.4,4.9h0.2 c0.6-0.7,1.4-1.3,2.4-2c1-0.7,2-1.3,3.1-1.9c1.1-0.6,2.3-1,3.6-1.4c1.3-0.4,2.6-0.5,3.9-0.5c2.8,0,5.1,0.5,7.1,1.4 c2,0.9,3.5,2.3,4.7,4c1,1.5,1.6,3.3,1.9,5.4l0.8-0.6H52.6z"/>
									<path class="logo-st2" d="M82.1,17.9c-0.5-0.1-1.1-0.2-1.8-0.2c-1.8,0-3.3,0.4-4.5,1.2c-1.1,0.8-2.1,2.4-2.8,4.9l-1.7,5.9h6.5l1.6,5.1 l-4.2,4.1h-6.5l-7.9,28H49.4l7.9-28h-4.4L52,39.5c0,0.2,0.1,0.5,0.1,0.7c0.2,2.3-0.1,4.9-0.9,7.7c-0.7,2.6-1.8,5.1-3.3,7.5 c-1.5,2.4-3.2,4.5-5.1,6.3c-2,1.8-4.2,3.3-6.6,4.4c-2.4,1.1-4.9,1.6-7.6,1.6c-2.4,0-4.5-0.4-6.4-1.1c-1.9-0.7-3.2-2-4-3.8h-0.2 l-5,17.7h63.3c3.6,0,6.6-2.9,6.6-6.6V18.2C82.6,18.1,82.3,18,82.1,17.9"/>
									<path class="logo-st0" d="M277.6,68.5h0.8c0.4,0,0.6-0.1,0.7-0.2c0.1-0.1,0.2-0.2,0.2-0.4c0-0.1,0-0.2-0.1-0.3c-0.1-0.1-0.1-0.2-0.3-0.2 c-0.1,0-0.3-0.1-0.6-0.1h-0.7V68.5z M277,70.6v-3.8h1.3c0.5,0,0.8,0,1,0.1c0.2,0.1,0.4,0.2,0.5,0.4c0.1,0.2,0.2,0.4,0.2,0.6 c0,0.3-0.1,0.5-0.3,0.7c-0.2,0.2-0.5,0.3-0.8,0.3c0.1,0.1,0.2,0.1,0.3,0.2c0.2,0.2,0.3,0.4,0.6,0.8l0.5,0.7h-0.8l-0.3-0.6 c-0.3-0.5-0.5-0.8-0.6-0.9c-0.1-0.1-0.3-0.1-0.5-0.1h-0.4v1.6H277z M278.6,65.7c-0.5,0-1,0.1-1.5,0.4c-0.5,0.3-0.8,0.6-1.1,1.1 c-0.3,0.5-0.4,1-0.4,1.5c0,0.5,0.1,1,0.4,1.5c0.3,0.5,0.6,0.8,1.1,1.1c0.5,0.3,1,0.4,1.5,0.4c0.5,0,1-0.1,1.5-0.4 c0.5-0.3,0.8-0.6,1.1-1.1c0.3-0.5,0.4-1,0.4-1.5c0-0.5-0.1-1-0.4-1.5c-0.3-0.5-0.6-0.8-1.1-1.1C279.6,65.8,279.1,65.7,278.6,65.7z M278.6,65.1c0.6,0,1.2,0.2,1.8,0.5c0.6,0.3,1,0.7,1.3,1.3c0.3,0.6,0.5,1.2,0.5,1.8c0,0.6-0.2,1.2-0.5,1.8c-0.3,0.6-0.8,1-1.3,1.3 c-0.6,0.3-1.2,0.5-1.8,0.5c-0.6,0-1.2-0.2-1.8-0.5c-0.6-0.3-1-0.8-1.3-1.3c-0.3-0.6-0.5-1.2-0.5-1.8c0-0.6,0.2-1.2,0.5-1.8 c0.3-0.6,0.8-1,1.3-1.3C277.4,65.2,278,65.1,278.6,65.1z"/>
								</svg>
							</div>
						</div>
						<div class="col-sm-8 nowarning msgbox text-center">
							<span id="hostspan">
							</span>
						</div>
					</div>
				</div>
			</header>

			<div style="background: <?=$logincssfile?>;" class="pagebody">
				<div class="col-sm-2"></div>

				<div class="col-sm-8 offset-md-4 logoCol">
					<div class="loginCont center-block error-panel">
						<a href="index.php?logout"><?=$desc;?></a>
					</div>
				</div>

			<div class="col-sm-2"></div>
			</div>

			<footer id="3">
			<div id="footertext">
					<p class="text-muted">
						<a target="_blank" href="https://www.pfsense.org/?gui=bootstrap">pfSense</a> is &copy;
						2004 - 2017 by <a href="https://pfsense.org/license" class="tblnk">Rubicon Communications, LLC (Netgate)</a>. All Rights Reserved.
						[<a href="/license.php" class="tblnk">view license</a>]
					</p>
				</div>
			</footer>
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

	$logincssfile = "#1e3f75";

	if (isset($user_settings['webgui']['logincss']) && strlen($user_settings['webgui']['logincss']) == 6) {
		$logincssfile = "#" . $user_settings['webgui']['logincss'];
	}

	if (isset($config['system']['webgui']['loginshowhost'])) {
		$loginbannerstr = sprintf(gettext('%1$s.%2$s'), htmlspecialchars($config['system']['hostname']), htmlspecialchars($config['system']['domain']));
	} else {
		$loginbannerstr = sprintf(gettext('Login to %1$s'), $g['product_name']);
	}

	$loginautocomplete = isset($config['system']['webgui']['loginautocomplete']) ? '' : 'autocomplete="off"';

	if (is_ipaddr($http_host) && !$local_ip && !isset($config['system']['webgui']['nohttpreferercheck'])) {
		$warnclass = "pagebodywarn";	// Make room for a warning display row
	} else {
		$warnclass = "pagebody";
	}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta name="viewport" content="width=device-width, initial-scale=1">
	    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css" type="text/css">
	    <link rel="stylesheet" href="/css/login.css" type="text/css">
		<title><?=gettext("Login"); ?></title>
		<script type="text/javascript">
			//<![CDATA{
			var events = events || [];
			//]]>
		</script>
	</head>

	<body id="login" >
		<div id="total">
			<header>
				<div id="headerrow">
					<div class="row">
						<div class="col-sm-4">
							<div id="logodiv" style="text-align:center" class="nowarning">
								<svg role="img" aria-labelledby="pfsense-logo" x="0px" y="0px" viewBox="0 0 282.8 84.2" width="240" height="100%">
									<title id="pfsense-logo-svg">pfSense Logo</title>
									<style type="text/css">
										.logo-st0{fill:#2B40B5;}
										.logo-st1{fill:#1475CF;}
										.logo-st2{fill:#1C1275;}
									</style>
									<path class="logo-st0" d="M27.8,57.7c2.9,0,5.4-0.9,7.5-2.6c2.1-1.7,3.6-4,4.4-6.8c0.8-2.8,0.6-5.1-0.5-6.8c-1.1-1.7-3.2-2.6-6.1-2.6 c-2.9,0-5.4,0.9-7.5,2.6c-2.1,1.7-3.5,4-4.3,6.8c-0.8,2.8-0.7,5.1,0.5,6.8C22.8,56.9,24.8,57.7,27.8,57.7"/>
									<path class="logo-st0" d="M115.1,46.6c-1.5-0.8-3-1.4-4.7-1.8c-1.7-0.4-3.2-0.7-4.7-1.1c-1.5-0.3-2.7-0.7-3.6-1.1c-0.9-0.4-1.4-1.1-1.4-2 c0-1.1,0.5-1.9,1.4-2.4c0.9-0.5,1.9-0.7,2.8-0.7c2.8,0,5,1,6.7,3.1l7-7c-1.7-1.8-3.9-3.1-6.4-3.8c-2.5-0.7-5-1.1-7.4-1.1 c-1.9,0-3.9,0.2-5.7,0.7c-1.9,0.5-3.6,1.2-5,2.3c-1.5,1-2.6,2.3-3.5,3.9c-0.9,1.6-1.3,3.5-1.3,5.7c0,2.3,0.5,4.2,1.4,5.6 c0.9,1.4,2.1,2.5,3.6,3.3c1.5,0.8,3,1.3,4.7,1.7c1.7,0.4,3.2,0.7,4.7,1.1c1.5,0.3,2.7,0.7,3.6,1.2c0.9,0.5,1.4,1.2,1.4,2.2 c0,1-0.5,1.7-1.6,2.1c-1.1,0.4-2.3,0.6-3.6,0.6c-1.7,0-3.3-0.3-4.6-1c-1.3-0.7-2.5-1.7-3.6-3l-7,7.7c1.8,1.9,4.1,3.2,6.7,3.9 c2.7,0.7,5.3,1.1,7.9,1.1c2,0,4-0.2,6.1-0.6c2-0.4,3.9-1,5.5-2c1.6-0.9,3-2.2,4-3.8c1-1.6,1.6-3.5,1.6-5.9c0-2.3-0.5-4.2-1.4-5.6 C117.7,48.6,116.5,47.4,115.1,46.6"/>
									<path class="logo-st0" d="M156.3,34.1c-1.5-1.7-3.3-3-5.5-3.9c-2.2-0.9-4.6-1.4-7.2-1.4c-2.9,0-5.6,0.5-8.1,1.4c-2.5,0.9-4.7,2.2-6.6,3.9 c-1.9,1.7-3.3,3.8-4.4,6.2c-1.1,2.4-1.6,5.1-1.6,8c0,3,0.5,5.6,1.6,8c1.1,2.4,2.5,4.5,4.4,6.2c1.9,1.7,4.1,3,6.6,3.9 c2.5,0.9,5.2,1.4,8.1,1.4c3,0,5.9-0.6,8.7-1.9c2.8-1.3,5.1-3.1,7-5.4l-8-5.9c-1,1.3-2.1,2.4-3.4,3.3c-1.3,0.8-2.9,1.3-4.8,1.3 c-2.2,0-4.1-0.7-5.7-2c-1.5-1.3-2.5-3.1-3-5.2H161v-3.6c0-3-0.4-5.6-1.2-8C159,37.9,157.8,35.8,156.3,34.1 M134.3,44.1 c0.1-0.9,0.3-1.8,0.7-2.6c0.4-0.8,0.9-1.6,1.6-2.2c0.7-0.6,1.5-1.2,2.5-1.6c1-0.4,2.1-0.6,3.4-0.6c2.1,0,3.8,0.7,5.1,2.1 c1.3,1.4,2,3,1.9,5H134.3z"/>
									<path class="logo-st0" d="M198.3,33.8c-1-1.6-2.4-2.8-4.2-3.7c-1.8-0.9-4.1-1.3-7-1.3c-1.4,0-2.7,0.2-3.8,0.5c-1.2,0.4-2.2,0.8-3.1,1.4 c-0.9,0.6-1.7,1.2-2.4,1.9c-0.7,0.7-1.2,1.4-1.5,2.1H176v-5.1h-11v37.2h11.5V48.4c0-1.2,0.1-2.4,0.2-3.5c0.2-1.1,0.5-2.1,1-3 c0.5-0.9,1.2-1.6,2.1-2.1c0.9-0.5,2.1-0.8,3.6-0.8c1.5,0,2.6,0.3,3.4,0.9c0.8,0.6,1.4,1.4,1.8,2.4c0.4,1,0.6,2,0.7,3.2 c0.1,1.1,0.1,2.3,0.1,3.3v18.2h11.5V46.4c0-2.5-0.2-4.8-0.5-7C199.9,37.3,199.3,35.4,198.3,33.8"/>
									<path class="logo-st0" d="M231.5,46.6c-1.5-0.8-3-1.4-4.7-1.8c-1.7-0.4-3.2-0.7-4.7-1.1c-1.5-0.3-2.7-0.7-3.6-1.1c-0.9-0.4-1.4-1.1-1.4-2 c0-1.1,0.5-1.9,1.4-2.4c0.9-0.5,1.9-0.7,2.8-0.7c2.8,0,5,1,6.7,3.1l7-7c-1.7-1.8-3.9-3.1-6.4-3.8c-2.5-0.7-5-1.1-7.4-1.1 c-1.9,0-3.9,0.2-5.7,0.7c-1.9,0.5-3.6,1.2-5,2.3c-1.5,1-2.6,2.3-3.5,3.9c-0.9,1.6-1.3,3.5-1.3,5.7c0,2.3,0.5,4.2,1.4,5.6 c0.9,1.4,2.1,2.5,3.6,3.3c1.5,0.8,3,1.3,4.7,1.7c1.7,0.4,3.2,0.7,4.7,1.1c1.5,0.3,2.7,0.7,3.6,1.2c0.9,0.5,1.4,1.2,1.4,2.2 c0,1-0.5,1.7-1.6,2.1c-1.1,0.4-2.3,0.6-3.6,0.6c-1.7,0-3.3-0.3-4.6-1c-1.3-0.7-2.5-1.7-3.6-3l-7,7.7c1.8,1.9,4.1,3.2,6.7,3.9 c2.7,0.7,5.3,1.1,7.9,1.1c2,0,4-0.2,6.1-0.6c2-0.4,3.9-1,5.5-2c1.6-0.9,3-2.2,4-3.8c1-1.6,1.6-3.5,1.6-5.9c0-2.3-0.5-4.2-1.4-5.6 C234.1,48.6,232.9,47.4,231.5,46.6"/>
									<path class="logo-st0" d="M277.4,51.9v-4.2c-0.1-2.7-0.5-5.2-1.2-7.4c-0.8-2.4-2-4.5-3.5-6.2c-1.5-1.7-3.3-3-5.5-3.9 c-2.2-0.9-4.6-1.4-7.2-1.4c-2.9,0-5.6,0.5-8.1,1.4c-2.5,0.9-4.7,2.2-6.6,3.9c-1.9,1.7-3.3,3.8-4.4,6.2c-1.1,2.4-1.6,5.1-1.6,8 c0,3,0.5,5.6,1.6,8c1.1,2.4,2.5,4.5,4.4,6.2c1.9,1.7,4.1,3,6.6,3.9c2.5,0.9,5.2,1.4,8.1,1.4c3,0,5.9-0.6,8.7-1.9 c2.8-1.3,5.1-3.1,7-5.4l-8-5.9c-1,1.3-2.1,2.4-3.4,3.3c-1.3,0.8-2.9,1.3-4.8,1.3c-2.2,0-4.1-0.7-5.7-2c-1.5-1.3-2.5-3.1-3-5.2H277.4 z M250.7,44.1c0.1-0.9,0.3-1.8,0.7-2.6c0.4-0.8,0.9-1.6,1.6-2.2c0.7-0.6,1.5-1.2,2.5-1.6c1-0.4,2.1-0.6,3.4-0.6 c2.1,0,3.8,0.7,5.1,2.1c1.3,1.4,2,3,1.9,5H250.7z"/>
									<path class="logo-st1" d="M52.6,38.9l2.6-9.2h4.6l1.8-6.6c0.6-2,1.3-4,2.2-5.8c0.8-1.8,2-3.4,3.4-4.8c1.4-1.4,3.2-2.5,5.3-3.3 c2.1-0.8,4.8-1.2,7.9-1.2c0.8,0,1.5,0,2.3,0.1c-0.7-2.9-3.3-5-6.3-5.1H11.9c-3.6,0-6.5,3-6.5,6.6V67l10.5-37.3h10.6l-1.4,4.9h0.2 c0.6-0.7,1.4-1.3,2.4-2c1-0.7,2-1.3,3.1-1.9c1.1-0.6,2.3-1,3.6-1.4c1.3-0.4,2.6-0.5,3.9-0.5c2.8,0,5.1,0.5,7.1,1.4 c2,0.9,3.5,2.3,4.7,4c1,1.5,1.6,3.3,1.9,5.4l0.8-0.6H52.6z"/>
									<path class="logo-st2" d="M82.1,17.9c-0.5-0.1-1.1-0.2-1.8-0.2c-1.8,0-3.3,0.4-4.5,1.2c-1.1,0.8-2.1,2.4-2.8,4.9l-1.7,5.9h6.5l1.6,5.1 l-4.2,4.1h-6.5l-7.9,28H49.4l7.9-28h-4.4L52,39.5c0,0.2,0.1,0.5,0.1,0.7c0.2,2.3-0.1,4.9-0.9,7.7c-0.7,2.6-1.8,5.1-3.3,7.5 c-1.5,2.4-3.2,4.5-5.1,6.3c-2,1.8-4.2,3.3-6.6,4.4c-2.4,1.1-4.9,1.6-7.6,1.6c-2.4,0-4.5-0.4-6.4-1.1c-1.9-0.7-3.2-2-4-3.8h-0.2 l-5,17.7h63.3c3.6,0,6.6-2.9,6.6-6.6V18.2C82.6,18.1,82.3,18,82.1,17.9"/>
									<path class="logo-st0" d="M277.6,68.5h0.8c0.4,0,0.6-0.1,0.7-0.2c0.1-0.1,0.2-0.2,0.2-0.4c0-0.1,0-0.2-0.1-0.3c-0.1-0.1-0.1-0.2-0.3-0.2 c-0.1,0-0.3-0.1-0.6-0.1h-0.7V68.5z M277,70.6v-3.8h1.3c0.5,0,0.8,0,1,0.1c0.2,0.1,0.4,0.2,0.5,0.4c0.1,0.2,0.2,0.4,0.2,0.6 c0,0.3-0.1,0.5-0.3,0.7c-0.2,0.2-0.5,0.3-0.8,0.3c0.1,0.1,0.2,0.1,0.3,0.2c0.2,0.2,0.3,0.4,0.6,0.8l0.5,0.7h-0.8l-0.3-0.6 c-0.3-0.5-0.5-0.8-0.6-0.9c-0.1-0.1-0.3-0.1-0.5-0.1h-0.4v1.6H277z M278.6,65.7c-0.5,0-1,0.1-1.5,0.4c-0.5,0.3-0.8,0.6-1.1,1.1 c-0.3,0.5-0.4,1-0.4,1.5c0,0.5,0.1,1,0.4,1.5c0.3,0.5,0.6,0.8,1.1,1.1c0.5,0.3,1,0.4,1.5,0.4c0.5,0,1-0.1,1.5-0.4 c0.5-0.3,0.8-0.6,1.1-1.1c0.3-0.5,0.4-1,0.4-1.5c0-0.5-0.1-1-0.4-1.5c-0.3-0.5-0.6-0.8-1.1-1.1C279.6,65.8,279.1,65.7,278.6,65.7z M278.6,65.1c0.6,0,1.2,0.2,1.8,0.5c0.6,0.3,1,0.7,1.3,1.3c0.3,0.6,0.5,1.2,0.5,1.8c0,0.6-0.2,1.2-0.5,1.8c-0.3,0.6-0.8,1-1.3,1.3 c-0.6,0.3-1.2,0.5-1.8,0.5c-0.6,0-1.2-0.2-1.8-0.5c-0.6-0.3-1-0.8-1.3-1.3c-0.3-0.6-0.5-1.2-0.5-1.8c0-0.6,0.2-1.2,0.5-1.8 c0.3-0.6,0.8-1,1.3-1.3C277.4,65.2,278,65.1,278.6,65.1z"/>
								</svg>
							</div>
						</div>
						<div class="col-sm-8 nowarning msgbox text-center">
							<span id="hostspan">
								<a><h4><?=$loginbannerstr?></h4></a>
							</span>
						</div>
					</div>
<?php
	if ($warnclass == "pagebodywarn") {
?>
					<div class="row">
						<div class="col-sm-12">
							<div class="alert alert-warning <?=$warnclass?>">
								<?=gettext("The IP address being used to access this router is not configured locally, which may be forwarded by NAT or other means.
								If this forwarding is unexpected, it should be verified that a man-in-the-middle attack is not taking place.")?>
							</div>
						</div>
					</div>
<?php
	}
?>
	            </div>
	        </header>

	        <div style="background: <?=$logincssfile?>;" class="<?=$warnclass?>">
	        	<div class="col-sm-4"></div>

	        	<div class="col-sm-4 offset-md-4 logoCol">
					<div class="loginCont center-block">
		                <form method="post" <?=$loginautocomplete?> class="login">
			                <p class="form-title">Sign In</p>
			                <input name="usernamefld" id="usernamefld" type="text" placeholder="Username" autocorrect="off" autocapitalize="none"/>
			                <input name="passwordfld" id="passwordfld" type="password" placeholder="Password" />
			                <input type="submit" name="login" value="Sign In" class="btn btn-success btn-sm" />
		                </form>
					</div>
	            </div>

	        	<div class="col-sm-4"></div>
	        </div>

	        <footer id="3">
	            <div id="footertext">
					<p class="text-muted">
						<a target="_blank" href="https://www.pfsense.org/?gui=bootstrap">pfSense</a> is &copy;
						2004 - 2017 by <a href="https://pfsense.org/license" class="tblnk">Rubicon Communications, LLC (Netgate)</a>. All Rights Reserved.
						[<a href="/license.php" class="tblnk">view license</a>]
					</p>
	            </div>
	        </footer>
	    </div>

		<script src="/vendor/jquery/jquery-1.12.0.min.js?v=<?=filemtime('/usr/local/www/vendor/jquery/jquery-1.12.0.min.js')?>"></script>
		<script src="/vendor/bootstrap/js/bootstrap.min.js?v=<?=filemtime('/usr/local/www/vendor/bootstrap/js/bootstrap.min.js')?>"></script>
		<script src="/js/pfSense.js?v=<?=filemtime('/usr/local/www/js/pfSense.js')?>"></script>

		<script type="text/javascript">
		//!<[CDATA[
		events.push(function() {
			document.cookie=
				"cookie_test=1" +
				"<?php echo $config['system']['webgui']['protocol'] == 'https' ? '; secure' : '';?>";

			if (document.cookie.indexOf("cookie_test") == -1) {
				alert("<?=gettext('The browser must support cookies to login.')?>");
			}

			// Delete it
			document.cookie = "cookie_test=1; expires=Thu, 01-Jan-1970 00:00:01 GMT";
		});
		//]]>
		</script>

	</body>
</html>

<?php
} // end function

