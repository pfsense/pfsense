<?php
/*
 * authgui.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2003-2006 Manuel Kasper <mk@neon1.net>
 * Copyright (c) 2005-2006 Bill Marquette <bill.marquette@gmail.com>
 * Copyright (c) 2006 Paul Taylor <paultaylor@winn-dixie.com>
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2026 Rubicon Communications, LLC (Netgate)
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
include_once("config.inc");
include_once("config.lib.inc");
include_once("priv.inc");
if (!function_exists('is_platform_booting')) {
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

		$username = get_config_user();
		logger(LOG_NOTICE, localize_text("%s attempted to access %s but does not have access to that page. Redirecting to %s.", $username, $_SERVER['SCRIPT_NAME'], $page), LOG_PREFIX_WEBGUI);
		phpsession_end();

		exit;
	} else {
		// add this so they don't get stuck on the logout page when they have no permissions.
		$_SESSION["Logged_In"] = false;
		display_error_form("201", gettext("No page assigned to this user! Click here to logout."));
		phpsession_end();

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
	phpsession_end();

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
<? include('includes/iconheaders.html') ?>
		<link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css" type="text/css">
		<link rel="stylesheet" href="/css/login.css?v=<?=filemtime('/usr/local/www/css/login.css')?>" type="text/css">
		<title><?=gettext("Error"); ?></title>
	</head>

	<body id="error" >
		<div id="total">
			<header>
				<div id="headerrow">
					<div class="row">
						<div class="col-sm-4">
							<div id="logodiv" style="text-align:center" class="nowarning">
								<?php include("/usr/local/www/logo.svg"); ?>
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
						<?=print_credit()?>
					</p>
				</div>
			</footer>
		</div>
	</body>
</html>

<?php

} // end function


/* Mark and short display name for the SSO button, keyed off the issuer. The
 * mark is inlined because the login page loads no icon font and must never
 * make an external request. Falls back to the server's descriptive name, which
 * is why the label is kept short here -- long names wrap the button. */
function sso_provider($authcfg) {
	/* Admin-supplied login-button icon overrides the built-in defaults.
	 * Restricted to an https URL and escaped; the login page is pre-auth. */
	$icon_url = trim($authcfg['oidc_icon'] ?? '');
	if ($icon_url !== '' && preg_match('#^https://#i', $icon_url)) {
		return array('label' => $authcfg['name'],
			'icon' => '<img src="' . htmlspecialchars($icon_url, ENT_QUOTES) .
				'" alt="" aria-hidden="true" />');
	}
	if (stripos($authcfg['oidc_issuer'], 'accounts.google.com') !== false) {
		return array('label' => 'Google', 'icon' =>
			'<svg viewBox="0 0 48 48" aria-hidden="true">' .
			'<path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>' .
			'<path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>' .
			'<path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>' .
			'<path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>' .
			'</svg>');
	}
	return array('label' => $authcfg['name'], 'icon' =>
		'<svg viewBox="0 0 24 24" fill="none" stroke="#3c4043" stroke-width="2" ' .
		'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' .
		'<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>' .
		'<polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>');
}

function display_login_form() {
	require_once("globals.inc");
	global $g;

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
		//If session ended
		echo "SESSION_TIMEOUT";
		exit;
	}

	/* Check against locally configured IP addresses, which will catch when someone
	   port forwards WebGUI access from WAN to an internal IP on the router. */
	global $FilterIflist;

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
		foreach (config_get_path('openvpn/openvpn-server', []) as $ovpns) {
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

	// For the login form, get the settings of no particular user.
	// That ensures we will use the system default theme for the login form.
	$user_settings = get_user_settings("");
	$logincssfile = "#1e3f75";
	$login_message = print_login_message();

	if (isset($user_settings['webgui']['logincss']) && strlen($user_settings['webgui']['logincss']) == 6) {
		$logincssfile = "#" . $user_settings['webgui']['logincss'];
	}

	if (config_path_enabled('system/webgui','loginshowhost')) {
		$loginbannerstr = sprintf(gettext('%1$s.%2$s'),
								  htmlspecialchars(config_get_path('system/hostname', "")),
								  htmlspecialchars(config_get_path('system/domain', "")));
		$login_title = gettext(htmlspecialchars(config_get_path('system/hostname', "") . " - Login"));
	} Else {
		$loginbannerstr = sprintf(gettext('Login to %1$s'), g_get('product_label'));
		$login_title = sprintf(gettext("%s - Login"), g_get('product_label'));
	}

	$loginautocomplete = config_path_enabled('system/webgui','loginautocomplete') ? '' : 'autocomplete="off"';

	/* SSO: if the WebGUI auth server is an OIDC provider, offer a sign-in
	 * button. When basic auth is disabled, the password form is omitted. */
	$sso_authcfg = auth_get_authserver(config_get_path('system/webgui/authmode'));
	$sso_enabled = (($sso_authcfg['type'] ?? '') == 'oidc');
	$sso_provider = $sso_enabled ? sso_provider($sso_authcfg) : array();
	$basicauth_disabled = config_path_enabled('system/webgui', 'disable_basicauth');

	if (is_ipaddr($http_host) && !$local_ip &&
		!config_path_enabled('system/webgui','nohttpreferercheck')) {
		$warnclass = "pagebodywarn";	// Make room for a warning display row
	} else {
		$warnclass = "pagebody";
	}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta name="viewport" content="width=device-width, initial-scale=1">
<? include('includes/iconheaders.html') ?>
		<link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css" type="text/css">
		<link rel="stylesheet" href="/css/login.css?v=<?=filemtime('/usr/local/www/css/login.css')?>" type="text/css">
		<title><?=$login_title; ?></title>
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
						<!-- Header left logo box -->
						<div class="col-sm-4">
							<div id="logodiv" style="text-align:center" class="nowarning">
								<?php include("/usr/local/www/logo.svg"); ?>
							</div>
						</div>

						<!-- Header center message box -->
						<div class="col-sm-4 nowarning msgbox text-center text-danger">
<?php
						if (!empty($_POST['usernamefld']) || !empty($_SESSION['Login_Error'])) {
							print("<h4>" . htmlspecialchars($_SESSION['Login_Error']) . "</h4>");
							/* One-shot. The session is closed at this point
							 * (display_login_form() runs before the
							 * phpsession_begin() below), so reopen it or the
							 * clear is never written and the banner reprints
							 * on every later render. */
							phpsession_begin();
							unset($_SESSION['Login_Error']);
							phpsession_end(true);
						}
?>
						</div>

						<!-- Header right message box (hostname or msg)-->
						<div class="col-sm-4 nowarning msgbox text-center">
							<span id="hostspan">
								<h4><?=$loginbannerstr?></h4>
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
				<?=(empty($login_message) ? '' : $login_message)?>
	        	<div class="col-sm-4"></div>

	        	<div class="col-sm-4 offset-md-4 <?=(empty($login_message) ? 'logoCol' : '')?>">
					<div class="loginCont center-block">
		                <p class="form-title">Sign In</p>
<?php if (!$basicauth_disabled) { ?>
		                <form method="post" <?=$loginautocomplete?> class="login">
			                <input name="usernamefld" id="usernamefld" type="text" placeholder="Username" autocorrect="off" autocapitalize="none"/>
			                <input name="passwordfld" id="passwordfld" type="password" placeholder="Password" />
			                <input type="submit" name="login" value="Sign In" class="btn btn-success btn-sm" />
		                </form>
<?php } ?>
<?php if ($sso_enabled) { ?>
		                <div class="ssoCont">
<?php if (!$basicauth_disabled) { ?>
			                <div class="sso-sep"><span><?=gettext("or")?></span></div>
<?php } ?>
			                <a href="index.php?oauth=start" class="btn-sso">
				                <?=$sso_provider['icon']?>
				                <span><?=sprintf(gettext("Sign in with %s"), htmlspecialchars($sso_provider['label']))?></span>
			                </a>
		                </div>
<?php } ?>
					</div>
	            </div>

	        	<div class="col-sm-4"></div>
	        </div>

	        <footer id="3">
	            <div id="footertext">
					<p class="text-muted">
						<?=print_credit()?>
					</p>
	            </div>
	        </footer>
	    </div>

		<script type="text/javascript">
		//<![CDATA[
			/* Prevent duplicate submission  */
			events.push(function() {
				var submitted = false;

				$(form).submit(function(e){
					if (submitted) {
						e.preventDefault();
					} else {
						submitted = true;
						// Form is submitted because default action is not prevented
					}
				});
			});
		//]]>
		</script>

	    <script src="/vendor/jquery/jquery-3.7.1.min.js?v=<?=filemtime('/usr/local/www/vendor/jquery/jquery-3.7.1.min.js')?>"></script>
		<script src="/vendor/bootstrap/js/bootstrap.min.js?v=<?=filemtime('/usr/local/www/vendor/bootstrap/js/bootstrap.min.js')?>"></script>
		<script src="/js/pfSense.js?v=<?=filemtime('/usr/local/www/js/pfSense.js')?>"></script>

		<script type="text/javascript">
		//!<[CDATA[
		events.push(function() {
			document.cookie=
				"cookie_test=1" +
				"<?php echo config_get_path('system/webgui/protocol') == 'https' ? '; secure' : '';?>";

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
