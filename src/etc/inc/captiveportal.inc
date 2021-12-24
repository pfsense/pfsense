<?php
/*
 * captiveportal.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
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

/* include all configuration functions */
require_once("auth.inc");
require_once("PEAR.php"); // required for bcmath
require_once("Auth/RADIUS.php"); // required for radius accounting
require_once("config.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("voucher.inc");
require_once("xmlrpc_client.inc");

/* Captiveportal Radius Accounting */
PEAR::loadExtension('bcmath');
// The RADIUS Package doesn't have these vars so we create them ourself
define("CUSTOM_RADIUS_ACCT_INPUT_GIGAWORDS", "52");
define("CUSTOM_RADIUS_ACCT_OUTPUT_GIGAWORDS", "53");
define("GIGAWORDS_RIGHT_OPERAND", '4294967296'); // 2^32

function get_captive_portal_logo() {
	global $config, $g, $cpzone;
	$logo_src = "captiveportal-default-logo.png";
	// Check if customlogo is set and if the element exists
	// Check if the image is in the directory
	if (isset($config['captiveportal'][$cpzone]['customlogo']) &&
	    is_array($config['captiveportal'][$cpzone]['element']) &&
	    !empty($config['captiveportal'][$cpzone]['element'])) {
		foreach ($config['captiveportal'][$cpzone]['element'] as $element) {
			if (strpos($element['name'], "captiveportal-logo.") !== false) {
				if (file_exists("{$g['captiveportal_path']}/{$element['name']}")) {
					$logo_src = $element['name'];
					break;
				}
			}
		}
	}
	return $logo_src;
}

function get_captive_portal_bg() {
	$bg_src = "linear-gradient(135deg, #1475CF, #2B40B5, #1C1275)";
	global $config, $g, $cpzone;
	// check if custombg is set and if the element exists
	if (isset($config['captiveportal'][$cpzone]['custombg']) &&
	    is_array($config['captiveportal'][$cpzone]['element']) &&
	    !empty($config['captiveportal'][$cpzone]['element'])) { 
		foreach ($config['captiveportal'][$cpzone]['element'] as $element) {
			if (strpos($element['name'],"captiveportal-background.") !== false) {
				if( file_exists("{$g['captiveportal_path']}/{$element['name']}")) {
					$bg_src = "url(" . $element['name'] . ")" . "center center no-repeat fixed";
					break;
				}
			}
		}
	}
	return $bg_src;
}

function get_default_captive_portal_html() {
	global $config, $g, $cpzone;

	$translated_text1 = gettext("User");
	$translated_text2 = gettext("Password");
	$translated_text3 = gettext("First Authentication Method ");
	$translated_text4 = gettext("Second Authentication Method ");
	// default images to use.
	$logo_src = get_captive_portal_logo();
	$bg_src = get_captive_portal_bg();
	// bring in terms and conditions
	$termsconditions = base64_decode($config['captiveportal'][$cpzone]['termsconditions']);
	// if there is no terms and conditions do not require the checkbox to be selected.
	$disabled = "";
	if ($termsconditions) {
		$disabled = "disabled";
	}
	$htmltext = <<<EOD
<!DOCTYPE html>
<html>

<head>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
  <title>Captive Portal Login Page</title>
  <style>
	  #content,.login,.login-card a,.login-card h1,.login-help{text-align:center}body,html{margin:0;padding:0;width:100%;height:100%;display:table}#content{font-family:'Source Sans Pro',sans-serif;background-color:#1C1275;background:{$bg_src};-webkit-background-size:cover;-moz-background-size:cover;-o-background-size:cover;background-size:cover;display:table-cell;vertical-align:middle}.login-card{padding:40px;width:280px;background-color:#F7F7F7;margin:100px auto 10px;border-radius:2px;box-shadow:0 2px 2px rgba(0,0,0,.3);overflow:hidden}.login-card h1{font-weight:400;font-size:2.3em;color:#1383c6}.login-card h1 span{color:#f26721}.login-card img{width:70%;height:70%}.login-card input[type=submit]{width:100%;display:block;margin-bottom:10px;position:relative}.login-card input[type=text],input[type=password]{height:44px;font-size:16px;width:100%;margin-bottom:10px;-webkit-appearance:none;background:#fff;border:1px solid #d9d9d9;border-top:1px solid silver;padding:0 8px;box-sizing:border-box;-moz-box-sizing:border-box}.login-card input[type=text]:hover,input[type=password]:hover{border:1px solid #b9b9b9;border-top:1px solid #a0a0a0;-moz-box-shadow:inset 0 1px 2px rgba(0,0,0,.1);-webkit-box-shadow:inset 0 1px 2px rgba(0,0,0,.1);box-shadow:inset 0 1px 2px rgba(0,0,0,.1)}.login{font-size:14px;font-family:Arial,sans-serif;font-weight:700;height:36px;padding:0 8px}.login-submit{-webkit-appearance:none;-moz-appearance:none;appearance:none;border:0;color:#fff;text-shadow:0 1px rgba(0,0,0,.1);background-color:#4d90fe}.login-submit:disabled{opacity:.6}.login-submit:hover{border:0;text-shadow:0 1px rgba(0,0,0,.3);background-color:#357ae8}.login-card a{text-decoration:none;color:#222;font-weight:400;display:inline-block;opacity:.6;transition:opacity ease .5s}.login-card a:hover{opacity:1}.login-help{width:100%;font-size:12px}.list{list-style-type:none;padding:0}.list__item{margin:0 0 .7rem;padding:0}label{display:-webkit-box;display:-webkit-flex;display:-ms-flexbox;display:flex;-webkit-box-align:center;-webkit-align-items:center;-ms-flex-align:center;align-items:center;text-align:left;font-size:14px;}input[type=checkbox]{-webkit-box-flex:0;-webkit-flex:none;-ms-flex:none;flex:none;margin-right:10px;float:left}@media screen and (max-width:450px){.login-card{width:70%!important}.login-card img{width:30%;height:30%}}textarea{width:66%;margin:auto;height:120px;max-height:120px;background-color:#f7f7f7;padding:20px}#terms{display:none;padding-top:100px;padding-bottom:300px;}.auth_source{border: 1px solid lightgray; padding:20px 8px 0px 8px; margin-top: -2em; border-radius: 2px; }.auth_head{background-color:#f7f7f7;display:inline-block;}.auth_head_div{text-align:left;}#error-message{text-align:left;color:#ff3e3e;font-style:italic;}
  </style>
</head>

<body>
<div id="content">
	<div class="login-card">
		<img src="{$logo_src}"/><br>
 		<h1></h1>
		<div id="error-message">
			\$PORTAL_MESSAGE\$
		</div>
	  <form name="login_form" method="post" action="\$PORTAL_ACTION\$">
EOD;
	if ($config['captiveportal'][$cpzone]['auth_method'] != "none"){
		if ($config['captiveportal'][$cpzone]['auth_method'] === 'authserver' && !empty($config['captiveportal'][$cpzone]['auth_server2'])) {
			$htmltext .= <<<EOD
			<div class="auth_head_div">
				<h6 class="auth_head">{$translated_text3}</h6>
			</div>
			<div class="auth_source">

EOD;
		}
		$htmltext .=<<<EOD
		<input type="text" name="auth_user" placeholder="{$translated_text1}" id="auth_user">
		<input type="password" name="auth_pass" placeholder="{$translated_text2}" id="auth_pass">
EOD;

		if ($config['captiveportal'][$cpzone]['auth_method'] === 'authserver' && !empty($config['captiveportal'][$cpzone]['auth_server2'])) {
			$htmltext .= <<<EOD
			</div>
			<div class="auth_head_div">
				<h6 class="auth_head">{$translated_text4}</h6>
			</div>
			<div class="auth_source">

			<input type="text" name="auth_user2" placeholder="{$translated_text1}" id="auth_user2">
			<input type="password" name="auth_pass2" placeholder="{$translated_text2}" id="auth_pass2">
			</div>
EOD;
		}


		if (isset($config['voucher'][$cpzone]['enable'])) {
			$translated_text = gettext("Voucher Code");
			$htmltext .= <<<EOD
				<br  /><br  />
				<input name="auth_voucher" type="text" placeholder="{$translated_text}" value="#VOUCHER#">
EOD;
		}
	}

if ($termsconditions) {
	$htmltext .= <<<EOD
		  <div class="login-help">
			<ul class="list">
				<li class="list__item">
				  <label class="label--checkbox">
					<input type="checkbox" class="checkbox" onchange="document.getElementById('login').disabled = !this.checked;">
					<span>I agree with the <a  rel="noopener" href="#terms" onclick="document.getElementById('terms').style.display = 'block';">terms & conditions</a></span>
				  </label>
				</li>
			</ul>
		  </div>
EOD;
}
	$htmltext .= <<<EOD

		<input name="redirurl" type="hidden" value="\$PORTAL_REDIRURL\$">
		<input type="submit" name="accept" class="login login-submit" value="Login" id="login" {$disabled}>
	  </form>
	  <br  />
	  <span> <i>Made with &hearts; by</i> <strong>Netgate</strong></span>
	</div>
	<div id="terms">
		<textarea readonly>{$termsconditions}</textarea>
	</div>
</div>
</body>
</html>

EOD;

	return $htmltext;
}

function captiveportal_load_modules() {
	global $config;

	mute_kernel_msgs();
	if (!is_module_loaded("ipfw.ko")) {
		mwexec("/sbin/kldload ipfw");
		/* make sure ipfw is not on pfil hooks */
		set_sysctl(array(
		    "net.inet.ip.pfil.inbound" => "pf",
		    "net.inet6.ip6.pfil.inbound" => "pf",
		    "net.inet.ip.pfil.outbound" => "pf",
		    "net.inet6.ip6.pfil.outbound" => "pf"
		));
	}
	/* Activate layer2 filtering */
	set_sysctl(array(
	    "net.link.ether.ipfw" => "1",
	    "net.inet.ip.fw.one_pass" => "1",
	    "net.inet.ip.fw.tables_max" => "65534"
	));

	/* Always load dummynet now that even allowed ip and mac passthrough use it. */
	if (!is_module_loaded("dummynet.ko")) {
		mwexec("/sbin/kldload dummynet");
		set_sysctl(array(
		    "net.inet.ip.dummynet.io_fast" => "1",
		    "net.inet.ip.dummynet.hash_size" => "256"
		));
	}
	unmute_kernel_msgs();
}

function captiveportal_configure() {
	global $config, $g, $cpzone, $cpzoneid;

	if (is_array($config['captiveportal'])) {
		foreach ($config['captiveportal'] as $cpzone) {
			if (isset($cpzone['preservedb'])) {
				$keep_online_users = true;
				break;
			}
		}
		if (!$keep_online_users) {
			/* see https://redmine.pfsense.org/issues/12455 */
			unlink_if_exists("{$g['vardb_path']}/captiveportal_online_users");
		}
		foreach ($config['captiveportal'] as $cpkey => $cp) {
			$cpzone = $cpkey;
			$cpzoneid = $cp['zoneid'];
			captiveportal_configure_zone($cp);
		}
	}
}

function captiveportal_configure_zone($cpcfg) {
	global $config, $g, $cpzone, $cpzoneid;

	$captiveportallck = lock("captiveportal{$cpzone}", LOCK_EX);

	if (isset($cpcfg['enable'])) {

		if (platform_booting()) {
			echo "Starting captive portal({$cpcfg['zone']})... ";
		} else {
			captiveportal_syslog("Reconfiguring captive portal({$cpcfg['zone']}).");
		}

		/* init ipfw rules */
		captiveportal_init_rules();

		/* kill any running minicron */
		killbypid("{$g['varrun_path']}/cp_prunedb_{$cpzone}.pid");

		/* initialize minicron interval value */
		$croninterval = $cpcfg['croninterval'] ? $cpcfg['croninterval'] : 60;

		/* double check if the $croninterval is numeric and at least 10 seconds. If not we set it to 60 to avoid problems */
		if ((!is_numeric($croninterval)) || ($croninterval < 10)) {
			$croninterval = 60;
		}

		/* write portal page */
		if (is_array($cpcfg['page']) && $cpcfg['page']['htmltext']) {
			$htmltext = base64_decode($cpcfg['page']['htmltext']);
		} else {
			/* example/template page */
			$htmltext = get_default_captive_portal_html();
		}

		$fd = @fopen("{$g['varetc_path']}/captiveportal_{$cpzone}.html", "w");
		if ($fd) {
			// Special case handling.  Convert so that we can pass this page
			// through the PHP interpreter later without clobbering the vars.
			$htmltext = str_replace("\$PORTAL_ZONE\$", "#PORTAL_ZONE#", $htmltext);
			$htmltext = str_replace("\$PORTAL_REDIRURL\$", "#PORTAL_REDIRURL#", $htmltext);
			$htmltext = str_replace("\$PORTAL_MESSAGE\$", "#PORTAL_MESSAGE#", $htmltext);
			$htmltext = str_replace("\$CLIENT_MAC\$", "#CLIENT_MAC#", $htmltext);
			$htmltext = str_replace("\$CLIENT_IP\$", "#CLIENT_IP#", $htmltext);
			$htmltext = str_replace("\$PORTAL_ACTION\$", "#PORTAL_ACTION#", $htmltext);
			if ($cpcfg['preauthurl']) {
				$htmltext = str_replace("\$PORTAL_REDIRURL\$", "{$cpcfg['preauthurl']}", $htmltext);
				$htmltext = str_replace("#PORTAL_REDIRURL#", "{$cpcfg['preauthurl']}", $htmltext);
			}
			fwrite($fd, $htmltext);
			fclose($fd);
		}
		unset($htmltext);

		/* write error page */
		if (is_array($cpcfg['page']) && $cpcfg['page']['errtext']) {
			$errtext = base64_decode($cpcfg['page']['errtext']);
		} else {
			/* example page  */
			$errtext = get_default_captive_portal_html();
		}

		$fd = @fopen("{$g['varetc_path']}/captiveportal-{$cpzone}-error.html", "w");
		if ($fd) {
			// Special case handling.  Convert so that we can pass this page
			// through the PHP interpreter later without clobbering the vars.
			$errtext = str_replace("\$PORTAL_ZONE\$", "#PORTAL_ZONE#", $errtext);
			$errtext = str_replace("\$PORTAL_REDIRURL\$", "#PORTAL_REDIRURL#", $errtext);
			$errtext = str_replace("\$PORTAL_MESSAGE\$", "#PORTAL_MESSAGE#", $errtext);
			$errtext = str_replace("\$CLIENT_MAC\$", "#CLIENT_MAC#", $errtext);
			$errtext = str_replace("\$CLIENT_IP\$", "#CLIENT_IP#", $errtext);
			$errtext = str_replace("\$PORTAL_ACTION\$", "#PORTAL_ACTION#", $errtext);
			if ($cpcfg['preauthurl']) {
				$errtext = str_replace("\$PORTAL_REDIRURL\$", "{$cpcfg['preauthurl']}", $errtext);
				$errtext = str_replace("#PORTAL_REDIRURL#", "{$cpcfg['preauthurl']}", $errtext);
			}
			fwrite($fd, $errtext);
			fclose($fd);
		}
		unset($errtext);

		/* write logout page */
		if (is_array($cpcfg['page']) && $cpcfg['page']['logouttext']) {
			$logouttext = base64_decode($cpcfg['page']['logouttext']);
		} else {
			/* example page */
			$translated_text1 = gettext("Redirecting...");
			$translated_text2 = gettext("Redirecting to");
			$translated_text3 = gettext("Logout");
			$translated_text4 = gettext("Click the button below to disconnect");
			$logouttext = <<<EOD
<html>
<head><title>{$translated_text1}</title></head>
<body>
<span style="font-family: Tahoma, Verdana, Arial, Helvetica, sans-serif; font-size: 11px;">
<b>{$translated_text2} <a href="<?=\$my_redirurl;?>"><?=\$my_redirurl;?></a>...</b>
</span>
<script type="text/javascript">
//<![CDATA[
LogoutWin = window.open('', 'Logout', 'toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=256,height=64');
if (LogoutWin) {
	LogoutWin.document.write('<html>');
	LogoutWin.document.write('<head><title>{$translated_text3}</title></head>') ;
	LogoutWin.document.write('<body style="background-color:#435370">');
	LogoutWin.document.write('<div class="text-center" style="color: #ffffff; font-family: Tahoma, Verdana, Arial, Helvetica, sans-serif; font-size: 11px;">') ;
	LogoutWin.document.write('<b>{$translated_text4}</b><p />');
	LogoutWin.document.write('<form method="POST" action="<?=\$logouturl;?>">');
	LogoutWin.document.write('<input name="logout_id" type="hidden" value="<?=\$sessionid;?>" />');
	LogoutWin.document.write('<input name="zone" type="hidden" value="<?=\$cpzone;?>" />');
	LogoutWin.document.write('<input name="logout" type="submit" value="{$translated_text3}" />');
	LogoutWin.document.write('</form>');
	LogoutWin.document.write('</div></body>');
	LogoutWin.document.write('</html>');
	LogoutWin.document.close();
}

document.location.href="<?=\$my_redirurl;?>";
//]]>
</script>
</body>
</html>

EOD;
		}

		$fd = @fopen("{$g['varetc_path']}/captiveportal-{$cpzone}-logout.html", "w");
		if ($fd) {
			fwrite($fd, $logouttext);
			fclose($fd);
		}
		unset($logouttext);

		/* write elements */
		captiveportal_write_elements();

		/* kill any running CP nginx instances */
		killbypid("{$g['varrun_path']}/nginx-{$cpzone}-CaptivePortal.pid", 0.1);
		killbypid("{$g['varrun_path']}/nginx-{$cpzone}-CaptivePortal-SSL.pid", 0.1);

		/* start up the webserving daemon */
		captiveportal_init_webgui_zone($cpcfg);

		/* Kill any existing prunecaptiveportal processes */
		if (file_exists("{$g['varrun_path']}/cp_prunedb_{$cpzone}.pid")) {
			killbypid("{$g['varrun_path']}/cp_prunedb_{$cpzone}.pid");
		}

		/* start pruning process (interval defaults to 60 seconds) */
		mwexec("/usr/local/bin/minicron $croninterval {$g['varrun_path']}/cp_prunedb_{$cpzone}.pid " .
			"/etc/rc.prunecaptiveportal {$cpzone}");

		/* delete outdated radius server database if exist */
		unlink_if_exists("{$g['vardb_path']}/captiveportal_radius_{$cpzone}.db");

		if (platform_booting()) {
			/* send Accounting-On to server */
			captiveportal_send_server_accounting('on');
			echo "done\n";

			if (isset($cpcfg['preservedb']) ||
			    captiveportal_xmlrpc_sync_get_details($syncip, $syncport, $syncuser, $syncpass)) {

				$connected_users = captiveportal_read_db();
				if (!empty($connected_users)) {
					echo "Reconnecting users to captive portal {$cpcfg['zone']}... ";
					foreach ($connected_users as $user) {
						captiveportal_reserve_ruleno($user['pipeno']);
						$bw_up = intval($user['bw_up']);
						$bw_down = intval($user['bw_down']);
						$clientip = $user['ip'];
						$clientmac = $user['mac'];
						$bw_up_pipeno = $user['pipeno'];
						$bw_down_pipeno = $user['pipeno'] + 1;

						$rule_entry = "{$clientip}/" . (is_ipaddrv6($clientip) ? "128" : "32");
						if (!isset($cpcfg['nomacfilter'])) {
							$rule_entry .= ",{$clientmac}";
						}

						$_gb = @pfSense_ipfw_pipe("pipe {$bw_up_pipeno} config bw {$bw_up}Kbit/s queue 100 buckets 16");
						$_gb = @pfSense_ipfw_pipe("pipe {$bw_down_pipeno} config bw {$bw_down}Kbit/s queue 100 buckets 16");
						$_gb = @pfSense_ipfw_table("{$cpzone}_auth_up", IP_FW_TABLE_XADD, "{$rule_entry}", $bw_up_pipeno);
						$_gb = @pfSense_ipfw_table("{$cpzone}_auth_down", IP_FW_TABLE_XADD, "{$rule_entry}", $bw_down_pipeno);
					}
					echo "done\n";
				}
			} else {
				/* reset database on unclean shutdown, see https://redmine.pfsense.org/issues/12355 */
				unlink_if_exists("{$g['vardb_path']}/captiveportal{$cpzone}.db");
			}
		}

	} else {
		killbypid("{$g['varrun_path']}/nginx-{$cpzone}-CaptivePortal.pid");
		killbypid("{$g['varrun_path']}/nginx-{$cpzone}-CaptivePortal-SSL.pid");
		killbypid("{$g['varrun_path']}/cp_prunedb_{$cpzone}.pid");
		@unlink("{$g['varetc_path']}/captiveportal_{$cpzone}.html");
		@unlink("{$g['varetc_path']}/captiveportal-{$cpzone}-error.html");
		@unlink("{$g['varetc_path']}/captiveportal-{$cpzone}-logout.html");

		captiveportal_radius_stop_all(10); // NAS-Request

		captiveportal_filterdns_configure();

		/* remove old information */
		unlink_if_exists("{$g['vardb_path']}/captiveportal{$cpzone}.db");
		unlink_if_exists("{$g['vardb_path']}/captiveportal_radius_{$cpzone}.db");
		unlink_if_exists("{$g['vardb_path']}/captiveportal_{$cpzone}.rules");
		/* Release allocated pipes for this zone */
		$pipes_to_remove = captiveportal_free_dnrules();

		captiveportal_delete_rules($pipes_to_remove);

		if (empty($config['captiveportal'])) {
			set_single_sysctl("net.link.ether.ipfw", "0");
		} else {
			/* Deactivate ipfw(4) if not needed */
			$cpactive = false;
			if (is_array($config['captiveportal'])) {
				foreach ($config['captiveportal'] as $cpkey => $cp) {
					if (isset($cp['enable'])) {
						$cpactive = true;
						break;
					}
				}
			}
			if ($cpactive === false) {
				set_single_sysctl("net.link.ether.ipfw", "0");
			}
		}
	}

	unlock($captiveportallck);

	return 0;
}

function captiveportal_init_webgui() {
	global $config, $cpzone;

	if (is_array($config['captiveportal'])) {
		foreach ($config['captiveportal'] as $cpkey => $cp) {
			$cpzone = $cpkey;
			captiveportal_init_webgui_zone($cp);
		}
	}
}

function captiveportal_init_webgui_zonename($zone) {
	global $config, $cpzone;

	if (isset($config['captiveportal'][$zone])) {
		$cpzone = $zone;
		captiveportal_init_webgui_zone($config['captiveportal'][$zone]);
	}
}

function captiveportal_init_webgui_zone($cpcfg) {
	global $g, $config, $cpzone;

	if (!isset($cpcfg['enable'])) {
		return;
	}

	if (isset($cpcfg['httpslogin'])) {
		$cert = lookup_cert($cpcfg['certref']);
		$crt = base64_decode($cert['crt']);
		$key = base64_decode($cert['prv']);
		$ca = ca_chain($cert);

		/* generate nginx configuration */
		if (!empty($cpcfg['listenporthttps'])) {
			$listenporthttps = $cpcfg['listenporthttps'];
		} else {
			$listenporthttps = 8001 + $cpcfg['zoneid'];
		}
		system_generate_nginx_config("{$g['varetc_path']}/nginx-{$cpzone}-CaptivePortal-SSL.conf",
			$crt, $key, $ca, "nginx-{$cpzone}-CaptivePortal-SSL.pid", $listenporthttps, "/usr/local/captiveportal",
			"cert-{$cpzone}-portal.pem", "ca-{$cpzone}-portal.pem", $cpzone, false);
	}

	/* generate nginx configuration */
	if (!empty($cpcfg['listenporthttp'])) {
		$listenporthttp = $cpcfg['listenporthttp'];
	} else {
		$listenporthttp = 8000 + $cpcfg['zoneid'];
	}
	system_generate_nginx_config("{$g['varetc_path']}/nginx-{$cpzone}-CaptivePortal.conf",
		"", "", "", "nginx-{$cpzone}-CaptivePortal.pid", $listenporthttp, "/usr/local/captiveportal",
		"", "", $cpzone, false);

	@unlink("{$g['varrun']}/nginx-{$cpzone}-CaptivePortal.pid");
	/* attempt to start nginx */
	$res = mwexec("/usr/local/sbin/nginx -c {$g['varetc_path']}/nginx-{$cpzone}-CaptivePortal.conf");

	/* fire up https instance */
	if (isset($cpcfg['httpslogin'])) {
		@unlink("{$g['varrun']}/nginx-{$cpzone}-CaptivePortal-SSL.pid");
		$res = mwexec("/usr/local/sbin/nginx -c {$g['varetc_path']}/nginx-{$cpzone}-CaptivePortal-SSL.conf");
	}
}

function captiveportal_init_rules_byinterface($interface) {
	global $cpzone, $cpzoneid, $config;

	if (!is_array($config['captiveportal'])) {
		return;
	}

	foreach ($config['captiveportal'] as $cpkey => $cp) {
		$cpzone = $cpkey;
		$cpzoneid = $cp['zoneid'];
		$cpinterfaces = explode(",", $cp['interface']);
		if (in_array($interface, $cpinterfaces)) {
			captiveportal_init_rules();
			break;
		}
	}
}

/* Create basic rules used by all zones */
function captiveportal_init_general_rules($flush = false) {
	global $g;

	$flush_rule = '';
	if ($flush) {
		$flush_rule = 'flush';
	}

	/* Already loaded */
	if (!$flush && (mwexec("/sbin/ipfw list 1000", true) == 0)) {
		return;
	}

	$cprules = <<<EOD
{$flush_rule}
# Allow traffic that's been passed by layer2
add 999 allow tagged 1

# Table with interfaces that have CP enabled
table cp_ifaces create type iface valtype skipto

# Redirect each CP interface to its specific rule
add 1000 skipto tablearg all from any to any via table(cp_ifaces)

# This interface has no cp zone configured
add 1100 allow all from any to any

# block everything else
add 65534 deny all from any to any
EOD;

	/* load rules */
	file_put_contents("{$g['tmp_path']}/ipfw.cp.rules", $cprules);
	mwexec("/sbin/ipfw -q {$g['tmp_path']}/ipfw.cp.rules", true);
	@unlink("{$g['tmp_path']}/ipfw_{$cpzone}.cp.rules");
	unset($cprules);
}

/* Create a string with ipfw rule and increase rulenum */
function captiveportal_create_ipfw_rule($cmd, &$rulenum, $args) {
	$rule = "{$cmd} {$rulenum} {$args}\n";
	$rulenum++;

	return $rule;
}

/* Return first rule number for a cp zone */
function captiveportal_ipfw_ruleno($id) {
	global $g;

	return 2000 + $id * $g['captiveportal_rules_interval'];
}

/* reinit will disconnect all users, be careful! */
function captiveportal_init_rules($reinit = false) {
	global $config, $g, $cpzone;

	if (!isset($config['captiveportal'][$cpzone]['enable'])) {
		return;
	}

	captiveportal_load_modules();
	captiveportal_init_general_rules();

	/* Cleanup so nothing is leaked */
	captiveportal_free_dnrules(2000, 64500, false, $reinit);
	unlink_if_exists("{$g['vardb_path']}/captiveportal_{$cpzone}.rules");

	$cpzoneid = $config['captiveportal'][$cpzone]['zoneid'];
	$skipto = captiveportal_ipfw_ruleno($cpzoneid);

	$cprules = '';

	$cpips = array();
	$ifaces = get_configured_interface_list();
	$cpinterfaces = explode(",", $config['captiveportal'][$cpzone]['interface']);
	$firsttime = 0;
	foreach ($cpinterfaces as $cpifgrp) {
		if (!isset($ifaces[$cpifgrp])) {
			continue;
		}
		$tmpif = get_real_interface($cpifgrp);
		if (empty($tmpif)) {
			continue;
		}

		$cpipm = get_interface_ip($cpifgrp);

		if (!is_ipaddr($cpipm)) {
			continue;
		}

		$cpips[] = $cpipm;
		if (is_array($config['virtualip']['vip'])) {
			foreach ($config['virtualip']['vip'] as $vip) {
				if (($vip['interface'] == $cpifgrp) &&
				    (($vip['mode'] == "carp") ||
				    ($vip['mode'] == "ipalias"))) {
					$cpips[] = $vip['subnet'];
				}
			}
		}

		$cprules .= "table cp_ifaces add {$tmpif} {$skipto}\n";
	}
	if (count($cpips) > 0) {
		$cpactive = true;
	} else {
		return false;
	}

	$captiveportallck = try_lock("captiveportal{$cpzone}", 0);

	$tables = captiveportal_get_ipfw_table_names();

	$rulenum = $skipto;
	$cprules .= "table {$cpzone}_pipe_mac create type mac valtype pipe\n";
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pipe tablearg tag 1 MAC table({$cpzone}_pipe_mac)");
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "allow pfsync from any to any");
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "allow carp from any to any\n");
	$cprules .= "# layer 2: pass ARP\n";
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pass layer2 mac-type arp,rarp");
	$cprules .= "# pfsense requires for WPA\n";
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pass layer2 mac-type 0x888e,0x88c7");
	$cprules .= "# PPP Over Ethernet Session Stage/Discovery Stage\n";
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pass layer2 mac-type 0x8863,0x8864\n");
	$cprules .= "# layer 2: block anything else non-IP(v4/v6)\n";
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "deny layer2 not mac-type ip,ipv6");

	/* These tables contain host ips */
	$cprules .= "table {$cpzone}_host_ips create type addr\n";
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pass ip from any to table({$cpzone}_host_ips) in");
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pass ip from table({$cpzone}_host_ips) to any out");
	foreach ($cpips as $cpip) {
		$cprules .= "table {$cpzone}_host_ips add {$cpip}\n";
	}
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pass ip from any to 255.255.255.255 in");
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pass ip from 255.255.255.255 to any out");

	/* Allowed ips */
	$cprules .= "table {$cpzone}_allowed_up create type addr valtype pipe\n";
	$cprules .= "table {$cpzone}_allowed_down create type addr valtype pipe\n";
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pipe tablearg ip from table({$cpzone}_allowed_up) to any in");
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pipe tablearg ip from any to table({$cpzone}_allowed_down) in");
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pipe tablearg ip from table({$cpzone}_allowed_up) to any out");
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pipe tablearg ip from any to table({$cpzone}_allowed_down) out");

	/* Authenticated users rules. */
	if (!in_array("{$cpzone}_auth_up", $tables)) {
		$cprules .= "table {$cpzone}_auth_up create type addr valtype pipe\n";
	}
	if (!in_array("{$cpzone}_auth_down", $tables)) {
		$cprules .= "table {$cpzone}_auth_down create type addr valtype pipe\n";
	}
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pipe tablearg tag 1 ip from table({$cpzone}_auth_up) to any layer2 in");
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pipe tablearg tag 1 ip from any to table({$cpzone}_auth_down) layer2 out");

	if (!empty($config['captiveportal'][$cpzone]['listenporthttp'])) {
		$listenporthttp = $config['captiveportal'][$cpzone]['listenporthttp'];
	} else {
		$listenporthttp = 8000 + $cpzoneid;
	}

	if (isset($config['captiveportal'][$cpzone]['httpslogin'])) {
		if (!empty($config['captiveportal'][$cpzone]['listenporthttps'])) {
			$listenporthttps = $config['captiveportal'][$cpzone]['listenporthttps'];
		} else {
			$listenporthttps = 8001 + $cpzoneid;
		}
		if (!isset($config['captiveportal'][$cpzone]['nohttpsforwards'])) {
			$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
			    "fwd 127.0.0.1,{$listenporthttps} tcp from any to any dst-port 443 in");
		}
	}

	$cprules .= "# redirect non-authenticated clients to captive portal\n";
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "fwd 127.0.0.1,{$listenporthttp} tcp from any to any dst-port 80 in");
	$cprules .= "# let the responses from the captive portal web server back out\n";
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pass tcp from any to any out");
	$cprules .= "# This CP zone is over, skip to last rule\n";
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "skipto 65534 all from any to any");

	/* generate passthru mac database */
	$cprules .= captiveportal_passthrumac_configure(true);
	$cprules .= "\n";

	/* allowed ipfw rules to make allowed ip work */
	$cprules .= captiveportal_allowedip_configure();

	/* allowed ipfw rules to make allowed hostnames work */
	$cprules .= captiveportal_allowedhostname_configure();

	/* load rules */
	captiveportal_delete_rules(array(), $reinit);
	file_put_contents("{$g['tmp_path']}/ipfw_{$cpzone}.cp.rules", $cprules);
	mwexec("/sbin/ipfw -q {$g['tmp_path']}/ipfw_{$cpzone}.cp.rules", true);
	@unlink("{$g['tmp_path']}/ipfw_{$cpzone}.cp.rules");
	unset($cprules);

	captiveportal_filterdns_configure();

	if ($captiveportallck) {
		unlock($captiveportallck);
	}
}

/* Delete all rules related to specific cpzone */
function captiveportal_delete_rules($pipes_to_remove = array(), $clear_auth_rules = true) {
	global $g, $config, $cpzone;

	$cpzoneid = $config['captiveportal'][$cpzone]['zoneid'];

	$skipto1 = captiveportal_ipfw_ruleno($cpzoneid);
	$skipto2 = $skipto1 + $g['captiveportal_rules_interval'];

	$cp_ifaces = pfSense_ipfw_table_list("cp_ifaces");
	if (is_array($cp_ifaces)) {
		foreach ($cp_ifaces as $cp_iface) {
			if (empty($cp_iface['skipto']) ||
			    $cp_iface['skipto'] != $skipto1) {
				continue;
			}

			pfSense_ipfw_table("cp_ifaces", IP_FW_TABLE_XDEL,
			    $cp_iface['iface']);
		}
	}

	mwexec("/sbin/ipfw delete {$skipto1}-{$skipto2}", true);

	$tables = captiveportal_get_ipfw_table_names();

	$delrules = "";
	foreach ($tables as $table) {
		if (!$clear_auth_rules && substr($table, 0, strlen($cpzone . '_auth')) == $cpzone . '_auth') {
			continue;
		} else {
			$delrules .= "table {$table} destroy\n";
		}
	}

	foreach ($pipes_to_remove as $pipeno) {
		$delrules .= "pipe delete {$pipeno}\n";
	}

	if (empty($delrules)) {
		return;
	}

	file_put_contents("{$g['tmp_path']}/ipfw_{$cpzone}.deltable.rules", $delrules);
	mwexec("/sbin/ipfw -q {$g['tmp_path']}/ipfw_{$cpzone}.deltable.rules", true);
	@unlink("{$g['tmp_path']}/ipfw_{$cpzone}.deltable.rules");
}

/*
 * Remove clients that have been around for longer than the specified amount of time
 * db file structure:
 * timestamp,ipfw_rule_no,clientip,clientmac,username,sessionid,password,session_timeout,idle_timeout,session_terminate_time,interim_interval,traffic_quota,auth_method,context
 * (password is in Base64 and only saved when reauthentication is enabled)
 */
function captiveportal_prune_old() {
	global $g, $config, $cpzone, $cpzoneid;

	if (empty($cpzone)) {
		return;
	}

	$cpcfg = $config['captiveportal'][$cpzone];
	$vcpcfg = $config['voucher'][$cpzone];

	/* check for expired entries */
	$idletimeout = 0;
	$timeout = 0;
	if (!empty($cpcfg['timeout']) && is_numeric($cpcfg['timeout'])) {
		$timeout = $cpcfg['timeout'] * 60;
	}

	if (!empty($cpcfg['idletimeout']) && is_numeric($cpcfg['idletimeout'])) {
		$idletimeout = $cpcfg['idletimeout'] * 60;
	}

	/* check for entries exceeding their traffic quota */
	$trafficquota = 0;
	if (!empty($cpcfg['trafficquota']) && is_numeric($cpcfg['trafficquota'])) {
		$trafficquota = $cpcfg['trafficquota'] * 1048576;
	}

	/* Is there any job to do? If we are in High Availability sync, are we in backup mode ? */
	if ((!$timeout && !$idletimeout && !$trafficquota && !isset($cpcfg['reauthenticate']) &&
	    !isset($cpcfg['radiussession_timeout']) && !isset($cpcfg['radiustraffic_quota']) &&
	    !isset($vcpcfg['enable']) && !isset($cpcfg['radacct_enable'])) ||
	    captiveportal_ha_is_node_in_backup_mode($cpzone)) {
		return;
	}


	/* Read database */
	/* NOTE: while this can be simplified in non radius case keep as is for now */
	$cpdb = captiveportal_read_db();

	$unsetindexes = array();
	$voucher_needs_sync = false;
	/*
	 * Snapshot the time here to use for calculation to speed up the process.
	 * If something is missed next run will catch it!
	 */
	$pruning_time = time();
	foreach ($cpdb as $cpentry) {
		$stop_time = $pruning_time;

		$timedout = false;
		$term_cause = 1;
		/* hard timeout or session_timeout from radius if enabled */
		if (isset($cpcfg['radiussession_timeout'])) {
			$utimeout = (is_numeric($cpentry[7])) ? $cpentry[7] : $timeout;
		} else {
			$utimeout = $timeout;
		}
		if ($utimeout) {
			if (($pruning_time - $cpentry[0]) >= $utimeout) {
				$timedout = true;
				$term_cause = 5; // Session-Timeout
				$logout_cause = 'SESSION TIMEOUT';
			}
		}

		/* Session-Terminate-Time */
		if (!$timedout && !empty($cpentry[9])) {
			if ($pruning_time >= $cpentry[9]) {
				$timedout = true;
				$term_cause = 5; // Session-Timeout
				$logout_cause = 'SESSION TIMEOUT';
			}
		}

		/* check if an idle_timeout has been set and if its set change the idletimeout to this value */
		$uidletimeout = (is_numeric($cpentry[8])) ? $cpentry[8] : $idletimeout;
		/* if an idle timeout is specified, get last activity timestamp from ipfw */
		if (!$timedout && $uidletimeout > 0) {
			$lastact = captiveportal_get_last_activity($cpentry[2]);
			/*	If the user has logged on but not sent any traffic they will never be logged out.
			 *	We "fix" this by setting lastact to the login timestamp.
			 */
			$lastact = $lastact ? $lastact : $cpentry[0];
			if ($lastact && (($pruning_time - $lastact) >= $uidletimeout)) {
				$timedout = true;
				$term_cause = 4; // Idle-Timeout
				$logout_cause = 'IDLE TIMEOUT';
				if (!isset($config['captiveportal'][$cpzone]['includeidletime'])) {
					$stop_time = $lastact;
				}
			}
		}

		/* if vouchers are configured, activate session timeouts */
		if (!$timedout && isset($vcpcfg['enable']) && !empty($cpentry[7])) {
			if ($pruning_time >= ($cpentry[0] + $cpentry[7])) {
				$timedout = true;
				$term_cause = 5; // Session-Timeout
				$logout_cause = 'SESSION TIMEOUT';
				$voucher_needs_sync = true;
			}
		}

		/* traffic quota, value retrieved from the radius attribute if the option is enabled */
		if (isset($cpcfg['radiustraffic_quota'])) {
			$utrafficquota = (is_numeric($cpentry[11])) ? $cpentry[11] : $trafficquota;
		} else {
			$utrafficquota = $trafficquota;
		}
		if (!$timedout && $utrafficquota > 0) {
			$volume = getVolume($cpentry[2], $cpentry[3]);
			if (($volume['input_bytes'] + $volume['output_bytes']) > $utrafficquota) {
				$timedout = true;
				$term_cause = 10; // NAS-Request
				$logout_cause = 'QUOTA EXCEEDED';
			}
		}

		if ($timedout) {
			captiveportal_disconnect($cpentry, $term_cause, $stop_time);
			captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], $logout_cause);
			$unsetindexes[] = $cpentry[5];
		}

		/* do periodic reauthentication? For Radius servers, send accounting updates? */
		if (!$timedout) {
			//Radius servers : send accounting
			if (isset($cpcfg['radacct_enable']) && $cpentry['authmethod'] === 'radius') {
				if (substr($cpcfg['reauthenticateacct'], 0, 9) === "stopstart") {
					/* stop and restart accounting */
					if ($cpcfg['reauthenticateacct'] === "stopstartfreeradius") {
						$rastart_time = 0;
						$rastop_time = 60;
					} else {
						$rastart_time = $cpentry[0];
						$rastop_time = time();
					}
					captiveportal_send_server_accounting('stop',
						$cpentry[1], // ruleno
						$cpentry[4], // username
						$cpentry[2], // clientip
						$cpentry[3], // clientmac
						$cpentry[5], // sessionid
						$rastart_time, // start time
						$rastop_time, // Stop Time
						10); // NAS Request
					$clientsn = (is_ipaddrv6($cpentry[2])) ? 128 : 32;
					pfSense_ipfw_table_zerocnt("{$cpzone}_auth_up", "{$cpentry[2]}/{$clientsn}");
					pfSense_ipfw_table_zerocnt("{$cpzone}_auth_down", "{$cpentry[2]}/{$clientsn}");
					if ($cpcfg['reauthenticateacct'] == "stopstartfreeradius") {
						/* Need to pause here or the FreeRADIUS server gets confused about packet ordering. */
						sleep(1);
					}
					captiveportal_send_server_accounting('start',
						$cpentry[1], // ruleno
						$cpentry[4], // username
						$cpentry[2], // clientip
						$cpentry[3], // clientmac
						$cpentry[5]); // sessionid
				} else if ($cpcfg['reauthenticateacct'] == "interimupdate") {
					$session_time = $pruning_time - $cpentry[0];
					if (!empty($cpentry[10]) && $cpentry[10] > 60) {
						$interval = $cpentry[10];
					} else {
						$interval = 0;
					}
					$past_interval_min = ($session_time > $interval);
					if ($interval != 0) {
						$within_interval = ($session_time % $interval >= 0 && $session_time % $interval <= 59);
					}
					if ($interval === 0 || ($interval > 0 && $past_interval_min && $within_interval)) {
					captiveportal_send_server_accounting('update',
						$cpentry[1], // ruleno
						$cpentry[4], // username
						$cpentry[2], // clientip
						$cpentry[3], // clientmac
						$cpentry[5], // sessionid
						$cpentry[0]); // start time
					}
				}
			}

			/* check this user again */
			if (isset($cpcfg['reauthenticate']) && $cpentry['context'] !== 'voucher') {
				$auth_result = captiveportal_authenticate_user(
					$cpentry[4], // username
					base64_decode($cpentry[6]), // password
					$cpentry[3], // clientmac
					$cpentry[2], // clientip
					$cpentry[1], // ruleno
					$cpentry['context']); // context
				if ($auth_result['result'] === false) {
					captiveportal_disconnect($cpentry, 17);
					captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "DISCONNECT - REAUTHENTICATION FAILED", $auth_list['reply_message']);
					$unsetindexes[] = $cpentry[5];
				} else if ($auth_result['result'] === true) {
					if ($cpentry['authmethod'] !== $auth_result['auth_method']) {
						// if the user got authenticated against another server type:  we update the database
						if (!empty($cpentry[5])) {
							captiveportal_update_entry($cpentry['sessionid'], $auth_result['auth_method'], 'authmethod');
							captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "CHANGED AUTHENTICATION SERVER", $auth_list['reply_message']);
						}
						// User was logged on a RADIUS server, but is now logged in by another server type : we send an accounting Stop
						if (isset($config['captiveportal'][$cpzone]['radacct_enable']) && $cpentry['authmethod'] == 'radius') {
							if ($cpcfg['reauthenticateacct'] === "stopstartfreeradius") {
								$rastart_time = 0;
								$rastop_time = 60;
							} else {
								$rastart_time = $cpentry[0];
								$rastop_time = time();
							}
							captiveportal_send_server_accounting('stop',
								$cpentry[1], // ruleno
								$cpentry[4], // username
								$cpentry[2], // clientip
								$cpentry[3], // clientmac
								$cpentry[5], // sessionid
								$rastart_time, // start time
								$rastop_time, // Stop Time
								3); // Lost Service
						// User was logged on a non-RADIUS Server but is now logged in by a RADIUS server : we send an accounting Start
						} else if(isset($config['captiveportal'][$cpzone]['radacct_enable']) && $auth_result['auth_method'] === 'radius') {
							captiveportal_send_server_accounting('start',
								$cpentry[1], // ruleno
								$cpentry[4], // username
								$cpentry[2], // clientip
								$cpentry[3], // clientmac
								$cpentry[5], // sessionid
								$cpentry[0]); // start_time
						}
					}
					captiveportal_reapply_attributes($cpentry, $auth_result['attributes']);
				}
			}
		}
	}
	unset($cpdb);

	captiveportal_prune_old_automac();

	if ($voucher_needs_sync == true) {
		/* perform in-use vouchers expiration using check_reload_status */
		send_event("service sync vouchers");
	}

	/* write database */
	if (!empty($unsetindexes)) {
		captiveportal_remove_entries($unsetindexes);
	}
}

function captiveportal_prune_old_automac() {
	global $g, $config, $cpzone, $cpzoneid;

	if (is_array($config['captiveportal'][$cpzone]['passthrumac']) &&
	    isset($config['captiveportal'][$cpzone]['passthrumacadd'])) {
		$tmpvoucherdb = array();
		$macrules = "";
		$writecfg = false;
		foreach ($config['captiveportal'][$cpzone]['passthrumac'] as $eid => $emac) {
			if ($emac['logintype'] != "voucher") {
				continue;
			}
			if (isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) {
				if (isset($tmpvoucherdb[$emac['username']])) {
					$temac = $config['captiveportal'][$cpzone]['passthrumac'][$tmpvoucherdb[$emac['username']]];
					$pipeno = captiveportal_get_dn_passthru_ruleno($temac['mac']);
					if ($pipeno) {
						captiveportal_free_dn_ruleno($pipeno);
						$macrules .= "table {$cpzone}_pipe_mac delete any,{$temac['mac']}\n";
						$macrules .= "table {$cpzone}_pipe_mac delete {$temac['mac']},any\n";
						$macrules .= "pipe delete {$pipeno}\n";
						++$pipeno;
						$macrules .= "pipe delete {$pipeno}\n";
					}
					$writecfg = true;
					captiveportal_logportalauth($temac['username'], $temac['mac'],
					    $temac['ip'], "DUPLICATE {$temac['username']} LOGIN - TERMINATING OLD SESSION");
					unset($config['captiveportal'][$cpzone]['passthrumac'][$tmpvoucherdb[$emac['username']]]);
				}
				$tmpvoucherdb[$emac['username']] = $eid;
			}
		}
		unset($tmpvoucherdb);
		if (!empty($macrules)) {
			@file_put_contents("{$g['tmp_path']}/macentry.prunerules.tmp", $macrules);
			unset($macrules);
			mwexec("/sbin/ipfw -q {$g['tmp_path']}/macentry.prunerules.tmp");
		}
		if ($writecfg === true) {
			write_config("Prune session for auto-added macs");
		}
	}
}

/* remove a single client according to the DB entry */
function captiveportal_disconnect($dbent, $term_cause = 1, $stop_time = null, $carp_loop = false) {
	global $g, $config, $cpzone, $cpzoneid;

	$stop_time = (empty($stop_time)) ? time() : $stop_time;

	/* this client needs to be deleted - remove ipfw rules */
	if (isset($config['captiveportal'][$cpzone]['radacct_enable']) && $dbent['authmethod'] == 'radius') {
		if ($config['captiveportal'][$cpzone]['reauthenticateacct'] == "stopstartfreeradius") {
			/*
			 * Interim updates are on so the session time must be
			 * reported as the elapsed time since the previous
			 * interim update.
			 */
			$session_time = ($stop_time - $dbent[0]) % 60;
			$start_time = $stop_time - $session_time;
		} else {
			$start_time = $dbent[0];
		}
		captiveportal_send_server_accounting('stop',
			$dbent[1], // ruleno
			$dbent[4], // username
			$dbent[2], // clientip
			$dbent[3], // clientmac
			$dbent[5], // sessionid
			$start_time, // start time
			$stop_time, // stop time
			$term_cause); // Acct-Terminate-Cause
	}

	if (is_ipaddr($dbent[2])) {
		/*
		 * Delete client's ip entry from tables auth_up and auth_down.
		 * It's not necessary to explicit specify mac address here
		 */
		$cpsession = captiveportal_isip_logged($dbent[2]);
		if (!empty($cpsession)) {
			$clientsn = (is_ipaddrv6($dbent[2])) ? 128 : 32;
			pfSense_ipfw_table("{$cpzone}_auth_up",
			    IP_FW_TABLE_XDEL, "{$dbent[2]}/{$clientsn}");
			pfSense_ipfw_table("{$cpzone}_auth_down",
			    IP_FW_TABLE_XDEL, "{$dbent[2]}/{$clientsn}");
		}
		/* XXX: Redundant?! Ensure all pf(4) states are killed. */
		$_gb = @pfSense_kill_states(utf8_encode($dbent[2]));
		$_gb = @pfSense_kill_srcstates(utf8_encode($dbent[2]));
	}

	/*
	 * These are the pipe numbers we use to control traffic shaping for
	 * each logged in user via captive portal
	 * We could get an error if the pipe doesn't exist but everything
	 * should still be fine
	 */
	if (!empty($dbent[1])) {
		/*
		 * Call captiveportal_free_dnrules() in dry_run mode to verify
		 * if there are pipes to be removed and prevent the attempt to
		 * delete invalid pipes
		 */
		$removed_pipes = captiveportal_free_dnrules($dbent[1],
		    $dbent[1]+1, true);

		if (!empty($removed_pipes)) {
			$_gb = @pfSense_ipfw_pipe("pipe delete {$dbent[1]}");
			$_gb = @pfSense_ipfw_pipe("pipe delete " .
			    ($dbent[1]+1));

			/*
			 * Release the ruleno so it can be reallocated to new
			 * clients
			 */
			captiveportal_free_dn_ruleno($dbent[1]);
		}
	}

	// XMLRPC Call over to the backup node if necessary
	if (captiveportal_xmlrpc_sync_get_details($syncip, $syncport,
	    $syncuser, $syncpass, $carp_loop)) {
		$rpc_client = new pfsense_xmlrpc_client();
		$rpc_client->setConnectionData($syncip, $syncport, $syncuser, $syncpass);
		$rpc_client->set_noticefile("CaptivePortalUserSync");
		$arguments = array(
			'sessionid' => $dbent[5],
			'term_cause' => $term_cause,
			'stop_time' => $stop_time
		);

		$rpc_client->xmlrpc_method('captive_portal_sync',
			array(
				'op' => 'disconnect_user',
				'zone' => $cpzone,
				'session' => base64_encode(serialize($arguments))
			)
		);
	}
	return true;
}

/* remove a single client by sessionid */
function captiveportal_disconnect_client($sessionid, $term_cause = 1, $logoutReason = "LOGOUT") {
	global $g, $config;

	$sessionid = SQLite3::escapeString($sessionid);
	/* read database */
	$result = captiveportal_read_db("WHERE sessionid = '{$sessionid}'");

	/* find entry */
	if (!empty($result)) {

		foreach ($result as $cpentry) {
			captiveportal_disconnect($cpentry, $term_cause);
			captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "DISCONNECT");
		}
		captiveportal_remove_entries(array($sessionid));
		unset($result);
	}
}

/* remove all clients */
function captiveportal_disconnect_all($term_cause = 6, $logoutReason = "DISCONNECT", $carp_loop = false) {
	global $g, $config, $cpzone, $cpzoneid;

	if (captiveportal_xmlrpc_sync_get_details($syncip, $syncport, $syncuser, $syncpass, $carp_loop)) {
		$rpc_client = new pfsense_xmlrpc_client();
		$rpc_client->setConnectionData($syncip, $syncport, $syncuser, $syncpass);
		$rpc_client->set_noticefile("CaptivePortalUserSync");
		$arguments = array(
			'term_cause' => $term_cause,
			'logout_reason' => $logoutReason
		);

		$rpc_client->xmlrpc_method('captive_portal_sync',
			array(
				'op' => 'disconnect_all',
				'zone' => $cpzone,
				'arguments' => base64_encode(serialize($arguments))
			)
		);
	}
	/* check if we're pruning old entries and eventually wait */
	$rcprunelock = try_lock("rcprunecaptiveportal{$cpzone}", 15);

	/* if we still don't have the lock, unlock forcefully and take it */
	if (!$rcprunelock) {
		log_error("CP zone ${cpzone}: could not obtain the lock for more than 15 seconds, lock taken forcefully to disconnect all users");
		unlock_force("rcprunecaptiveportal{$cpzone}");
		$rcprunelock = lock("rcprunecaptiveportal{$cpzone}", LOCK_EX);
	}

	/* take a lock so new users won't be able to log in */
	$cpdblck = lock("captiveportaldb{$cpzone}", LOCK_EX);

	captiveportal_radius_stop_all($term_cause, $logoutReason);

	/* remove users from the database */
	$cpdb = captiveportal_read_db();
	$unsetindexes = array_column($cpdb,5);
	if (!empty($unsetindexes)) {
		// High Availability : do not sync removed entries
		captiveportal_remove_entries($unsetindexes, true);
	}

	/* reinit ipfw rules */
	captiveportal_init_rules(true);

	unlock($cpdblck);
	unlock($rcprunelock);
	return true;
}

/* send RADIUS acct stop for all current clients connected with RADIUS servers */
function captiveportal_radius_stop_all($term_cause = 6, $logoutReason = "DISCONNECT") {
	global $g, $config, $cpzone, $cpzoneid;

	$cpdb = captiveportal_read_db();

	$radacct = isset($config['captiveportal'][$cpzone]['radacct_enable']) ? true : false;
	foreach ($cpdb as $cpentry) {
		if ($cpentry['authmethod'] === 'radius' && $radacct) {
			if ($config['captiveportal'][$cpzone]['reauthenticateacct'] == "stopstartfreeradius") {
				$session_time = (time() - $cpentry[0]) % 60;
				$start_time = time() - $session_time;
			} else {
				$start_time = $cpentry[0];
			}
			captiveportal_send_server_accounting('stop',
				$cpentry[1], // ruleno
				$cpentry[4], // username
				$cpentry[2], // clientip
				$cpentry[3], // clientmac
				$cpentry[5], // sessionid
				$start_time, // start time
				$stop_time, // stop time
				$term_cause); // Acct-Terminate-Cause
		}
		captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], $logoutReason);
	}
	unset($cpdb);
}

function captiveportal_passthrumac_configure_entry($macent, $pipeinrule = false) {
	global $config, $g, $cpzone;

	$bwUp = 0;
	if (!empty($macent['bw_up'])) {
		$bwUp = $macent['bw_up'];
	} elseif (isset($config['captiveportal'][$cpzone]['peruserbw']) &&
	    !empty($config['captiveportal'][$cpzone]['bwdefaultup'])) {
		$bwUp = $config['captiveportal'][$cpzone]['bwdefaultup'];
	}
	$bwDown = 0;
	if (!empty($macent['bw_down'])) {
		$bwDown = $macent['bw_down'];
	} elseif (isset($config['captiveportal'][$cpzone]['peruserbw']) &&
	    !empty($config['captiveportal'][$cpzone]['bwdefaultdn'])) {
		$bwDown = $config['captiveportal'][$cpzone]['bwdefaultdn'];
	}

	if ($macent['action'] == 'pass') {
		$rules = "";

		$pipeno = captiveportal_get_next_dn_ruleno('pipe_mac');

		$pipeup = $pipeno;
		if ($pipeinrule == true) {
			$_gb = @pfSense_ipfw_pipe("pipe {$pipeno} config bw {$bwUp}Kbit/s queue 100 buckets 16");
		} else {
			$rules .= "pipe {$pipeno} config bw {$bwUp}Kbit/s queue 100 buckets 16\n";
		}

		$pipedown = $pipeno + 1;
		if ($pipeinrule == true) {
			$_gb = @pfSense_ipfw_pipe("pipe {$pipedown} config bw {$bwDown}Kbit/s queue 100 buckets 16");
		} else {
			$rules .= "pipe {$pipedown} config bw {$bwDown}Kbit/s queue 100 buckets 16\n";
		}

		$rules .= "table {$cpzone}_pipe_mac add any,{$macent['mac']} {$pipeup}\n";
		$rules .= "table {$cpzone}_pipe_mac add {$macent['mac']},any {$pipedown}\n";
	}

	return $rules;
}

function captiveportal_passthrumac_delete_entry($macent) {
	global $cpzone;
	$rules = "";

	if ($macent['action'] == 'pass') {
		$pipeno = captiveportal_get_dn_passthru_ruleno($macent['mac']);

		if (!empty($pipeno)) {
			captiveportal_free_dn_ruleno($pipeno);
			$rules .= "table {$cpzone}_pipe_mac delete any,{$macent['mac']}\n";
			$rules .= "table {$cpzone}_pipe_mac delete {$macent['mac']},any\n";
			$rules .= "pipe delete " . $pipeno . "\n";
			$rules .= "pipe delete " . ++$pipeno . "\n";
		}
	}

	return $rules;
}

function captiveportal_passthrumac_configure($filename = false, $startindex = 0, $stopindex = 0) {
	global $config, $g, $cpzone;

	$rules = "";

	if (is_array($config['captiveportal'][$cpzone]['passthrumac'])) {
		if ($stopindex > 0) {
			$fd = fopen($filename, "w");
			for ($idx = $startindex; $idx <= $stopindex; $idx++) {
				if (isset($config['captiveportal'][$cpzone]['passthrumac'][$idx])) {
					$rules = captiveportal_passthrumac_configure_entry($config['captiveportal'][$cpzone]['passthrumac'][$idx]);
					fwrite($fd, $rules);
				}
			}
			fclose($fd);

			return;
		} else {
			$nentries = count($config['captiveportal'][$cpzone]['passthrumac']);
			if ($nentries > 2000) {
				$nloops = $nentries / 1000;
				$remainder= $nentries % 1000;
				for ($i = 0; $i < $nloops; $i++) {
					mwexec_bg("/usr/local/sbin/fcgicli -f /etc/rc.captiveportal_configure_mac -d \"cpzone={$cpzone}&startidx=" . ($i * 1000) . "&stopidx=" . ((($i+1) * 1000) - 1) . "\"");
				}
				if ($remainder > 0) {
					mwexec_bg("/usr/local/sbin/fcgicli -f /etc/rc.captiveportal_configure_mac -d \"cpzone={$cpzone}&startidx=" . ($i * 1000) . "&stopidx=" . (($i* 1000) + $remainder) ."\"");
				}
			} else {
				foreach ($config['captiveportal'][$cpzone]['passthrumac'] as $macent) {
					$rules .= captiveportal_passthrumac_configure_entry($macent, true);
				}
			}
		}
	}

	return $rules;
}

function captiveportal_passthrumac_findbyname($username) {
	global $config, $cpzone;

	if (is_array($config['captiveportal'][$cpzone]['passthrumac'])) {
		foreach ($config['captiveportal'][$cpzone]['passthrumac'] as $macent) {
			if ($macent['username'] == $username) {
				return $macent;
			}
		}
	}
	return NULL;
}

/*
 * table (3=IN)/(4=OUT) hold allowed ip's without bw limits
 */
function captiveportal_allowedip_configure_entry($ipent, $ishostname = false) {
	global $g, $config, $cpzone;

	/*  Instead of copying this entire function for something
	 *  easy such as hostname vs ip address add this check
	 */
	if ($ishostname === true) {
		if (!platform_booting()) {
			$ipaddress = resolve_host_addresses($ipent['hostname'], array(DNS_A), false);
			if (empty($ipaddress)) {
				return;
			}
		} else {
			$ipaddress = array();
		}
	} else {
		$ipaddress = array($ipent['ip']);
	}

	$rules = "";
	$cp_filterdns_conf = "";
	$enBwup = 0;
	if (!empty($ipent['bw_up'])) {
		$enBwup = intval($ipent['bw_up']);
	} elseif (isset($config['captiveportal'][$cpzone]['peruserbw']) &&
	    !empty($config['captiveportal'][$cpzone]['bwdefaultup'])) {
		$enBwup = $config['captiveportal'][$cpzone]['bwdefaultup'];
	}
	$enBwdown = 0;
	if (!empty($ipent['bw_down'])) {
		$enBwdown = intval($ipent['bw_down']);
	} elseif (isset($config['captiveportal'][$cpzone]['peruserbw']) &&
	    !empty($config['captiveportal'][$cpzone]['bwdefaultdn'])) {
		$enBwdown = $config['captiveportal'][$cpzone]['bwdefaultdn'];
	}

	$pipeup = captiveportal_get_next_dn_ruleno('allowed');
	$_gb = @pfSense_ipfw_pipe("pipe {$pipeup} config bw {$enBwup}Kbit/s queue 100 buckets 16");
	$pipedown = $pipeup + 1;
	$_gb = @pfSense_ipfw_pipe("pipe {$pipedown} config bw {$enBwdown}Kbit/s queue 100 buckets 16");

	if ($ishostname === true) {
		$cp_filterdns_conf .= "ipfw {$ipent['hostname']} {$cpzone}_allowed_up pipe {$pipeup}\n";
		$cp_filterdns_conf .= "ipfw {$ipent['hostname']} {$cpzone}_allowed_down pipe {$pipedown}\n";
		if (!is_ipaddr($ipaddress[0])) {
			return array("", $cp_filterdns_conf);
		}
	}

	$subnet = "";
	if (!empty($ipent['sn'])) {
		$subnet = "/{$ipent['sn']}";
	}
	foreach ($ipaddress as $ip) {
		$rules .= "table {$cpzone}_allowed_up add {$ip}{$subnet} {$pipeup}\n";
		$rules .= "table {$cpzone}_allowed_down add {$ip}{$subnet} {$pipedown}\n";
	}

	if ($ishostname === true) {
		return array($rules, $cp_filterdns_conf);
	} else {
		return $rules;
	}
}

function captiveportal_allowedhostname_configure() {
	global $config, $g, $cpzone, $cpzoneid;

	$rules = "";
	if (!is_array($config['captiveportal'][$cpzone]['allowedhostname'])) {
		return $rules;
	}

	$rules = "\n# captiveportal_allowedhostname_configure()\n";
	$cp_filterdns_conf = "";
	foreach ($config['captiveportal'][$cpzone]['allowedhostname'] as $hostnameent) {
		$tmprules = captiveportal_allowedip_configure_entry($hostnameent, true);
		$rules .= $tmprules[0];
		$cp_filterdns_conf .= $tmprules[1];
	}
	$cp_filterdns_filename = "{$g['varetc_path']}/filterdns-{$cpzone}-captiveportal.conf";
	@file_put_contents($cp_filterdns_filename, $cp_filterdns_conf);
	unset($cp_filterdns_conf);

	return $rules;
}

function captiveportal_filterdns_configure() {
	global $config, $g, $cpzone, $cpzoneid;

	$cp_filterdns_filename = $g['varetc_path'] .
	    "/filterdns-{$cpzone}-captiveportal.conf";

	if (isset($config['captiveportal'][$cpzone]['enable']) &&
	    is_array($config['captiveportal'][$cpzone]['allowedhostname']) &&
	    file_exists($cp_filterdns_filename)) {
		if (isvalidpid($g['varrun_path'] .
		    "/filterdns-{$cpzone}-cpah.pid")) {
			sigkillbypid($g['varrun_path'] .
			    "/filterdns-{$cpzone}-cpah.pid", "HUP");
		} else {
			mwexec("/usr/local/sbin/filterdns -p " .
			    "{$g['varrun_path']}/filterdns-{$cpzone}-cpah.pid" .
			    " -i 300 -c {$cp_filterdns_filename} -d 1");
		}
	} else {
		killbypid("{$g['varrun_path']}/filterdns-{$cpzone}-cpah.pid");
		@unlink("{$g['varrun_path']}/filterdns-{$cpzone}-cpah.pid");
	}

	return $rules;
}

function captiveportal_allowedip_configure() {
	global $config, $g, $cpzone;

	$rules = "";
	if (is_array($config['captiveportal'][$cpzone]['allowedip'])) {
		foreach ($config['captiveportal'][$cpzone]['allowedip'] as $ipent) {
			$rules .= captiveportal_allowedip_configure_entry($ipent);
		}
	}

	return $rules;
}

/* get last activity timestamp given client IP address */
function captiveportal_get_last_activity($ip) {
	global $cpzone;

	/* Reading only from one of the tables is enough of approximation. */
	$tables = array("{$cpzone}_allowed_up", "{$cpzone}_auth_up");

	foreach ($tables as $table) {
		$ipfw = pfSense_ipfw_table_lookup($table, $ip);
		if (is_array($ipfw)) {
			/* Workaround for #46652 */
			if ($ipfw['packets'] > 0) {
				return $ipfw['timestamp'];
			} else {
				return 0;
			}
		}
	}

	return 0;
}


/* log successful captive portal authentication to syslog */
/* part of this code from php.net */
function captiveportal_logportalauth($user, $mac, $ip, $status, $message = null) {
	// Log it
	if (!$message) {
		$message = "{$status}: {$user}, {$mac}, {$ip}";
	} else {
		$message = trim($message);
		$message = "{$status}: {$user}, {$mac}, {$ip}, {$message}";
	}
	captiveportal_syslog($message);
}

/* log simple messages to syslog */
function captiveportal_syslog($message) {
	global $cpzone;

	$message = trim($message);
	$message = "Zone: {$cpzone} - {$message}";
	openlog("logportalauth", LOG_PID, LOG_LOCAL4);
	// Log it
	syslog(LOG_INFO, $message);
	closelog();
}

/* Authenticate users using Authentication Backend */
function captiveportal_authenticate_user(&$login = '', &$password = '', $clientmac = '', $clientip = '', $pipeno = 'null', $context = 'first') {
	global $g, $config, $cpzone;
	$cpcfg = $config['captiveportal'][$cpzone];

	$login_status = 'FAILURE';
	$login_msg = gettext('Invalid credentials specified');
	$reply_attributes = array();
	$auth_method = '';
	$auth_result = null;

	/*
	Management of the reply Message (reason why the authentication failed) :
	multiple authentication servers can be used, so multiple reply messages could theoretically be returned.
	But only one message is returned (the most important one).
	The return value of authenticate_user() define how important messages are :
		- Reply message of a successful auth is more important than reply message of
		a user failed auth(invalid credentials/authorization)

		- Reply message of a user failed auth is more important than reply message of
		a server failed auth (unable to contact server)

		- When multiple user failed auth are encountered, messages returned by remote servers
		(eg. reply in RADIUS Access-Reject) are more important than pfSense error messages.

	The $authlevel variable is a flag indicating the status of authentication
	0 = failed server auth
	1 = failed user auth
	2 = failed user auth with custom server reply recieved
	3 = successful auth
	*/
	$authlevel = 0;

	/* Getting authentication servers from captiveportal configuration */
	$auth_servers = array();

	if ($cpcfg['auth_method'] === 'none') {
		$auth_servers[] = array('type' => 'none');
	} else {
		if ($context === 'second') {
			$fullauthservers = explode(",", $cpcfg['auth_server2']);
		} else {
			$fullauthservers = explode(",", $cpcfg['auth_server']);
		}

		foreach ($fullauthservers as $authserver) {
			if (strpos($authserver, ' - ') !== false) {
				$authserver = explode(' - ', $authserver);
				array_shift($authserver);
				$authserver = implode(' - ', $authserver);

				if (auth_get_authserver($authserver) !== null) {
					$auth_servers[] = auth_get_authserver($authserver);
				} else {
					log_error("Zone: {$cpzone} - Captive portal was unable to find the settings of the server '{$authserver}' used for authentication !");
				}
			}
		}
	}

	/* Unable to find the any authentication server config - shouldn't happen! - bail out */
	if (count($auth_servers) === 0) {
		log_error("Zone: {$cpzone} - No valid server could be used for authentication.");
		$login_msg = gettext("Internal Error");
	} else {
		foreach ($auth_servers as $authcfg) {
			if ($authlevel < 3) {
				$radmac_error = false;
				$attributes = array("nas_identifier" => empty($cpcfg["radiusnasid"]) ? "CaptivePortal-{$cpzone}" : $cpcfg["radiusnasid"],
					"nas_port_type" => RADIUS_ETHERNET,
					"nas_port" => $pipeno,
					"framed_ip" => $clientip);
				if (mac_format($clientmac) !== null) {
					$attributes["calling_station_id"] = mac_format($clientmac);
				}

				$result = null;
				$status = null;
				$msg = null;

				/* Radius MAC authentication */
				if ($context === 'radmac' && $clientmac) {
					if ($authcfg['type'] === 'radius') {
						$login = mac_format($clientmac);
						$status = "MACHINE LOGIN";
					} else {
						/* Trying to perform a Radius MAC authentication on a non-radius server - shouldn't happen! - bail out */
						$msg = gettext("Internal Error");
						log_error("Zone: {$cpzone} - Trying to perform RADIUS MAC authentication on a non-RADIUS server !");
						$radmac_error = true;
						$result = null;
					}
				}

				if (!$radmac_error) {
					if ($authcfg['type'] === 'none') {
						$result = true;
					} else {
						$result = authenticate_user($login, $password, $authcfg, $attributes);
					}

					if (!empty($attributes['error_message'])) {
						$msg = $attributes['error_message'];
					}

					if ($authcfg['type'] == 'Local Auth' && $result && isset($cpcfg['localauth_priv'])) {
						if (!userHasPrivilege(getUserEntry($login), "user-services-captiveportal-login")) {
							$result = false;
							$msg = gettext("Access Denied");
						}
					}
					if ($context === 'radmac' && $result === null && empty($attributes['reply_message'])) {
						$msg = gettext("RADIUS MAC Authentication Failed.");
					}

					if (empty($status)) {
						if ($result === true) {
							$status = "ACCEPT";
						} elseif ($result === null) {
							$status = "ERROR";
						} else {
							$status = "FAILURE";
						}
					}

					if ($context === 'radmac' && $login == mac_format($clientmac) || $authcfg['type'] === 'none' && empty($login)) {
						$login = "unauthenticated";
					}
				}
				// We determine a flag
				if ($result === true) {
					$val = 3;
				} elseif ($result === false && !empty($attributes['reply_message'])) {
					$val = 2;
					$msg = $attributes['reply_message'];
				} elseif ($result === false) {
					$val = 1;
				} elseif ($result === null) {
					$val = 0;
				}

				if ($val >= $authlevel) {
					$authlevel = $val;
					$auth_method = $authcfg['type'];
					$login_status = $status;
					$login_msg = $msg;
					$reply_attributes = $attributes;
					$auth_result = $result;
				}
			}
		}
	}

	return array('result'=>$auth_result, 'attributes'=>$reply_attributes, 'auth_method' =>$auth_method, 'login_status'=> $login_status, 'login_message' => $login_msg);
}

function captiveportal_opendb() {
	global $g, $config, $cpzone, $cpzoneid;

	$db_path = "{$g['vardb_path']}/captiveportal{$cpzone}.db";
	$createquery = "CREATE TABLE IF NOT EXISTS captiveportal (" .
				"allow_time INTEGER, pipeno INTEGER, ip TEXT, mac TEXT, username TEXT, " .
				"sessionid TEXT, bpassword TEXT, session_timeout INTEGER, idle_timeout INTEGER, " .
				"session_terminate_time INTEGER, interim_interval INTEGER, traffic_quota INTEGER, " .
				"bw_up INTEGER, bw_down INTEGER, authmethod TEXT, context TEXT); " .
			"CREATE UNIQUE INDEX IF NOT EXISTS idx_active ON captiveportal (sessionid, username); " .
			"CREATE INDEX IF NOT EXISTS user ON captiveportal (username); " .
			"CREATE INDEX IF NOT EXISTS ip ON captiveportal (ip); " .
			"CREATE INDEX IF NOT EXISTS starttime ON captiveportal (allow_time)";

	try {
		$DB = new SQLite3($db_path);
		$DB->busyTimeout(60000);
	} catch (Exception $e) {
		captiveportal_syslog("Could not open {$db_path} as an sqlite database for {$cpzone}. Error message: " . $e->getMessage() . " -- Trying again.");
		unlink_if_exists($db_path);
		try {
			$DB = new SQLite3($db_path);
			$DB->busyTimeout(60000);
		} catch (Exception $e) {
			captiveportal_syslog("Still could not open {$db_path} as an sqlite database for {$cpzone}. Error message: " . $e->getMessage() . " -- Remove the database file manually and ensure there is enough free space.");
			return;
		}
	}

	if (!$DB) {
		captiveportal_syslog("Could not open {$db_path} as an sqlite database for {$cpzone}. Error message: {$DB->lastErrorMsg()}. Trying again.");
		unlink_if_exists($db_path);
		$DB = new SQLite3($db_path);
		$DB->busyTimeout(60000);
		if (!$DB) {
			captiveportal_syslog("Still could not open {$db_path} as an sqlite database for {$cpzone}. Error message: {$DB->lastErrorMsg()}. Remove the database file manually and ensure there is enough free space.");
			return;
		}
	}

	if (! $DB->exec($createquery)) {
		captiveportal_syslog("Error during table {$cpzone} creation. Error message: {$DB->lastErrorMsg()}. Resetting and trying again.");

		/* If unable to initialize the database, reset and try again. */
		$DB->close();
		unset($DB);
		unlink_if_exists($db_path);
		$DB = new SQLite3($db_path);
		$DB->busyTimeout(60000);
		if ($DB->exec($createquery)) {
			captiveportal_syslog("Successfully reinitialized tables for {$cpzone} -- database has been reset.");
			if (!is_numericint($cpzoneid)) {
				if (is_array($config['captiveportal'])) {
					foreach ($config['captiveportal'] as $cpkey => $cp) {
						if ($cpzone == $cpkey) {
							$cpzoneid = $cp['zoneid'];
						}
					}
				}
			}
			if (is_numericint($cpzoneid)) {
				$table_names = captiveportal_get_ipfw_table_names();
				foreach ($table_names as $table_name) {
					mwexec("/sbin/ipfw table {$table_name} flush");
				}
				captiveportal_syslog("Flushed tables for {$cpzone} after database reset.");
			}
		} else {
			captiveportal_syslog("Still unable to create tables for {$cpzone}. Error message: {$DB->lastErrorMsg()}. Remove the database file manually and try again.");
		}
	}

	return $DB;
}

/* Get all tables for specific cpzone */
function captiveportal_get_ipfw_table_names() {
	global $cpzone;

	$result = array();
	$tables = pfSense_ipfw_tables_list();

	if (!is_array($tables)) {
		return $result;
	}

	$len = strlen($cpzone) + 1;
	foreach ($tables as $table) {
		if (substr($table['name'], 0, $len) != $cpzone . '_') {
			continue;
		}

		$result[] = $table['name'];
	}

	return $result;
}

/* read captive portal DB into array */
function captiveportal_read_db($query = "") {
	$cpdb = array();

	$DB = captiveportal_opendb();
	if ($DB) {
		$response = $DB->query("SELECT * FROM captiveportal {$query}");
		if ($response != FALSE) {
			while ($row = $response->fetchArray()) {
				$cpdb[] = $row;
			}
		}
		$DB->close();
	}

	return $cpdb;
}

function captiveportal_remove_entries($remove, $carp_loop = false) {
	global $cpzone;

	if (!is_array($remove) || empty($remove)) {
		return;
	}

	$query = "DELETE FROM captiveportal WHERE sessionid in (";
	foreach ($remove as $idx => $unindex) {
		$query .= "'{$unindex}'";
		if ($idx < (count($remove) - 1)) {
			$query .= ",";
		}
	}
	$query .= ")";
	captiveportal_write_db($query);

	if (captiveportal_xmlrpc_sync_get_details($syncip, $syncport, $syncuser, $syncpass, $carp_loop)) {
		$rpc_client = new pfsense_xmlrpc_client();
		$rpc_client->setConnectionData($syncip, $syncport, $syncuser, $syncpass);
		$rpc_client->set_noticefile("CaptivePortalUserSync");
		$rpc_client->xmlrpc_method('captive_portal_sync',
			array(
				'op' => 'remove_entries',
				'zone' => $cpzone,
				'entries' => base64_encode(serialize($remove))
			)
		);
	}
	return true;
}

/* write captive portal DB */
function captiveportal_write_db($queries) {
	global $g;

	if (is_array($queries)) {
		$query = implode(";", $queries);
	} else {
		$query = $queries;
	}

	$DB = captiveportal_opendb();
	if ($DB) {
		$DB->exec("BEGIN TRANSACTION");
		$result = $DB->exec($query);
		if (!$result) {
			captiveportal_syslog("Trying to modify DB returned error: {$DB->lastErrorMsg()}");
		} else {
			$DB->exec("END TRANSACTION");
		}
		$DB->close();
		return $result;
	} else {
		return true;
	}
}

function captiveportal_write_elements() {
	global $g, $config, $cpzone;

	$cpcfg = $config['captiveportal'][$cpzone];

	if (!is_dir($g['captiveportal_element_path'])) {
		@mkdir($g['captiveportal_element_path']);
	}

	if (is_array($cpcfg['element'])) {
		foreach ($cpcfg['element'] as $data) {
			/* Do not attempt to decode or write out empty files. */
			if (isset($data['nocontent'])) {
					continue;
			}
			if (empty($data['content']) || empty(base64_decode($data['content']))) {
				unlink_if_exists("{$g['captiveportal_element_path']}/{$data['name']}");
				touch("{$g['captiveportal_element_path']}/{$data['name']}");
			} elseif (!@file_put_contents("{$g['captiveportal_element_path']}/{$data['name']}", base64_decode($data['content']))) {
				printf(gettext('Error: cannot open \'%1$s\' in captiveportal_write_elements()%2$s'), $data['name'], "\n");
				return 1;
			}
			if (!file_exists("{$g['captiveportal_path']}/{$data['name']}")) {
				@symlink("{$g['captiveportal_element_path']}/{$data['name']}", "{$g['captiveportal_path']}/{$data['name']}");
			}
		}
	}

	return 0;
}

function captiveportal_free_dnrules($rulenos_start = 2000,
    $rulenos_range_max = 64500, $dry_run = false, $clear_auth_pipes = true) {
	global $g, $cpzone;

	$removed_pipes = array();

	if (!file_exists("{$g['vardb_path']}/captiveportaldn.rules")) {
		return $removed_pipes;
	}

	if (!$dry_run) {
		$cpruleslck = lock("captiveportalrulesdn", LOCK_EX);
	}

	$rules = unserialize(file_get_contents(
	    "{$g['vardb_path']}/captiveportaldn.rules"));
	$ridx = $rulenos_start;
	while ($ridx < $rulenos_range_max) {
		if (substr($rules[$ridx], 0, strlen($cpzone . '_')) == $cpzone . '_') {
			if (!$clear_auth_pipes && substr($rules[$ridx], 0, strlen($cpzone . '_auth')) == $cpzone . '_auth') {
				$ridx += 2;
			} else {
				if (!$dry_run) {
					$rules[$ridx] = false;
				}
				$removed_pipes[] = $ridx;
				$ridx++;
				if (!$dry_run) {
					$rules[$ridx] = false;
				}
				$removed_pipes[] = $ridx;
				$ridx++;
			}
		} else {
			$ridx += 2;
		}
	}

	if (!$dry_run) {
		file_put_contents("{$g['vardb_path']}/captiveportaldn.rules",
		    serialize($rules));
		unlock($cpruleslck);
	}

	unset($rules);

	return $removed_pipes;
}

function captiveportal_reserve_ruleno($ruleno) {
	global $g, $cpzone;

	$cpruleslck = lock("captiveportalrulesdn", LOCK_EX);
	if (file_exists("{$g['vardb_path']}/captiveportaldn.rules")) {
		$rules = unserialize(file_get_contents("{$g['vardb_path']}/captiveportaldn.rules"));
	} else {
		$rules = array_pad(array(), 64500, false);
	}
	$rules[$ruleno] = $cpzone . '_auth';
	$ruleno++;
	$rules[$ruleno] = $cpzone . '_auth';

	file_put_contents("{$g['vardb_path']}/captiveportaldn.rules", serialize($rules));
	unlock($cpruleslck);
	unset($rules);

	return $ruleno;
}

function captiveportal_get_next_dn_ruleno($rule_type = 'default', $rulenos_start = 2000, $rulenos_range_max = 64500) {
	global $config, $g, $cpzone;

	$cpruleslck = lock("captiveportalrulesdn", LOCK_EX);
	$ruleno = 0;
	if (file_exists("{$g['vardb_path']}/captiveportaldn.rules")) {
		$rules = unserialize(file_get_contents("{$g['vardb_path']}/captiveportaldn.rules"));
		$ridx = $rulenos_start;
		while ($ridx < $rulenos_range_max) {
			if (empty($rules[$ridx])) {
				$ruleno = $ridx;
				$rules[$ridx] = $cpzone . '_' . $rule_type;
				$ridx++;
				$rules[$ridx] = $cpzone . '_' . $rule_type;
				break;
			} else {
				$ridx += 2;
			}
		}
	} else {
		$rules = array_pad(array(), $rulenos_range_max, false);
		$ruleno = $rulenos_start;
		$rules[$rulenos_start] = $cpzone . '_' . $rule_type;
		$rulenos_start++;
		$rules[$rulenos_start] = $cpzone . '_' . $rule_type;
	}
	file_put_contents("{$g['vardb_path']}/captiveportaldn.rules", serialize($rules));
	unlock($cpruleslck);
	unset($rules);

	return $ruleno;
}

function captiveportal_free_dn_ruleno($ruleno) {
	global $config, $g;

	$cpruleslck = lock("captiveportalrulesdn", LOCK_EX);
	if (file_exists("{$g['vardb_path']}/captiveportaldn.rules")) {
		$rules = unserialize(file_get_contents("{$g['vardb_path']}/captiveportaldn.rules"));
		$rules[$ruleno] = false;
		$ruleno++;
		$rules[$ruleno] = false;
		file_put_contents("{$g['vardb_path']}/captiveportaldn.rules", serialize($rules));
		unset($rules);
	}
	unlock($cpruleslck);
}

function captiveportal_get_dn_passthru_ruleno($value) {
	global $config, $g, $cpzone, $cpzoneid;

	$cpcfg = $config['captiveportal'][$cpzone];
	if (!isset($cpcfg['enable'])) {
		return NULL;
	}

	$cpruleslck = lock("captiveportalrulesdn", LOCK_EX);
	$ruleno = NULL;
	if (file_exists("{$g['vardb_path']}/captiveportaldn.rules")) {
		unset($output);
		$item = pfSense_ipfw_table_lookup("{$cpzone}_pipe_mac",
		    "any,{$value}");
		if (!is_array($item) || empty($item['pipe'])) {
			unlock($cpruleslck);
			return NULL;
		}

		$ruleno = intval($item['pipe']);
		$rules = unserialize(file_get_contents("{$g['vardb_path']}/captiveportaldn.rules"));
		if (!$rules[$ruleno]) {
			$ruleno = NULL;
		}
		unset($rules);
	}
	unlock($cpruleslck);

	return $ruleno;
}

/**
 * This function will calculate the traffic produced by a client
 * based on its firewall rule
 *
 * Point of view: NAS
 *
 * Input means: from the client
 * Output means: to the client
 *
 */

function getVolume($ip) {
	global $config, $cpzone;

	$reverse = isset($config['captiveportal'][$cpzone]['reverseacct'])
	    ? true : false;
	$volume = array();
	// Initialize vars properly, since we don't want NULL vars
	$volume['input_pkts'] = $volume['input_bytes'] = 0;
	$volume['output_pkts'] = $volume['output_bytes'] = 0;

	$tables = array("allowed", "auth");

	foreach($tables as $table) {
		$ipfw = pfSense_ipfw_table_lookup("{$cpzone}_{$table}_up", $ip);
		if (!is_array($ipfw)) {
			continue;
		}
		if ($reverse) {
			$volume['output_pkts'] = $ipfw['packets'];
			$volume['output_bytes'] = $ipfw['bytes'];
		} else {
			$volume['input_pkts'] = $ipfw['packets'];
			$volume['input_bytes'] = $ipfw['bytes'];
		}
	}

	foreach($tables as $table) {
		$ipfw = pfSense_ipfw_table_lookup("{$cpzone}_{$table}_down",
		    $ip);
		if (!is_array($ipfw)) {
			continue;
		}
		if ($reverse) {
			$volume['input_pkts'] = $ipfw['packets'];
			$volume['input_bytes'] = $ipfw['bytes'];
		} else {
			$volume['output_pkts'] = $ipfw['packets'];
			$volume['output_bytes'] = $ipfw['bytes'];
		}
	}

	return $volume;
}

function portal_ip_from_client_ip($cliip) {
	global $config, $cpzone;

	$isipv6 = is_ipaddrv6($cliip);
	$interfaces = explode(",", $config['captiveportal'][$cpzone]['interface']);
	foreach ($interfaces as $cpif) {
		if ($isipv6) {
			$ip = get_interface_ipv6($cpif);
			$sn = get_interface_subnetv6($cpif);
		} else {
			$ip = get_interface_ip($cpif);
			$sn = get_interface_subnet($cpif);
		}
		if (ip_in_subnet($cliip, "{$ip}/{$sn}")) {
			return $ip;
		}
	}

	$route = route_get($cliip, 'inet', true);
	if (empty($route)) {
		return false;
	}

	$iface = $route[0]['interface-name'];
	if (!empty($iface)) {
		$ip = ($isipv6) ? find_interface_ipv6($iface)
		    : find_interface_ip($iface);
		if (is_ipaddr($ip)) {
			return $ip;
		}
	}

	// doesn't match up to any particular interface
	// so let's set the portal IP to what PHP says
	// the server IP issuing the request is.
	// allows same behavior as 1.2.x where IP isn't
	// in the subnet of any CP interface (static routes, etc.)
	// rather than forcing to DNS hostname resolution
	$ip = $_SERVER['SERVER_ADDR'];
	if (is_ipaddr($ip)) {
		return $ip;
	}

	return false;
}

function portal_hostname_from_client_ip($cliip) {
	global $config, $cpzone;

	$cpcfg = $config['captiveportal'][$cpzone];

	if (isset($cpcfg['httpslogin'])) {
		$listenporthttps = $cpcfg['listenporthttps'] ? $cpcfg['listenporthttps'] : ($cpcfg['zoneid'] + 8001);
		$ourhostname = $cpcfg['httpsname'];

		if ($listenporthttps != 443) {
			$ourhostname .= ":" . $listenporthttps;
		}
	} else {
		$listenporthttp = $cpcfg['listenporthttp'] ? $cpcfg['listenporthttp'] : ($cpcfg['zoneid'] + 8000);
		$ifip = portal_ip_from_client_ip($cliip);
		if (!$ifip) {
			$ourhostname = "{$config['system']['hostname']}.{$config['system']['domain']}";
		} else {
			$ourhostname = (is_ipaddrv6($ifip)) ? "[{$ifip}]" : "{$ifip}";
		}

		if ($listenporthttp != 80) {
			$ourhostname .= ":" . $listenporthttp;
		}
	}

	return $ourhostname;
}

/* functions move from index.php */

function portal_reply_page($redirurl, $type = null, $message = null, $clientmac = null, $clientip = null, $username = null, $password = null, $voucher = null) {
	global $g, $config, $cpzone;

	$cpcfg = $config['captiveportal'][$cpzone];
	$ourhostname = portal_hostname_from_client_ip($clientip);
	$protocol = (isset($cpcfg['httpslogin'])) ? 'https://' : 'http://';
	$portal_url = "{$protocol}{$ourhostname}/index.php?zone={$cpzone}";

	/* Get captive portal layout */
	if ($type == "redir") {
		$redirurl = is_URL($redirurl, true) ? $redirurl : $portal_url;
		header("Location: {$redirurl}");
		return;
	} else if ($type == "login") {
		$htmltext = get_include_contents("{$g['varetc_path']}/captiveportal_{$cpzone}.html");
	} else {
		$htmltext = get_include_contents("{$g['varetc_path']}/captiveportal-{$cpzone}-error.html");
	}

	/* substitute the PORTAL_REDIRURL variable */
	if ($cpcfg['preauthurl']) {
		$htmltext = str_replace("\$PORTAL_REDIRURL\$", "{$cpcfg['preauthurl']}", $htmltext);
		$htmltext = str_replace("#PORTAL_REDIRURL#", "{$cpcfg['preauthurl']}", $htmltext);
	}

	/* substitute other variables */
	$htmltext = str_replace("\$PORTAL_ACTION\$", $portal_url, $htmltext);
	$htmltext = str_replace("#PORTAL_ACTION#", $portal_url, $htmltext);

	$htmltext = str_replace("\$PORTAL_ZONE\$", htmlspecialchars($cpzone), $htmltext);
	$htmltext = str_replace("\$PORTAL_REDIRURL\$", htmlspecialchars($redirurl), $htmltext);
	$htmltext = str_replace("\$PORTAL_MESSAGE\$", htmlspecialchars($message), $htmltext);
	$htmltext = str_replace("\$CLIENT_MAC\$", htmlspecialchars($clientmac), $htmltext);
	$htmltext = str_replace("\$CLIENT_IP\$", htmlspecialchars($clientip), $htmltext);

	// Special handling case for captive portal master page so that it can be ran
	// through the PHP interpreter using the include method above.  We convert the
	// $VARIABLE$ case to #VARIABLE# in /etc/inc/captiveportal.inc before writing out.
	$htmltext = str_replace("#PORTAL_ZONE#", htmlspecialchars($cpzone), $htmltext);
	$htmltext = str_replace("#PORTAL_REDIRURL#", htmlspecialchars($redirurl), $htmltext);
	$htmltext = str_replace("#PORTAL_MESSAGE#", htmlspecialchars($message), $htmltext);
	$htmltext = str_replace("#CLIENT_MAC#", htmlspecialchars($clientmac), $htmltext);
	$htmltext = str_replace("#CLIENT_IP#", htmlspecialchars($clientip), $htmltext);
	$htmltext = str_replace("#USERNAME#", htmlspecialchars($username), $htmltext);
	$htmltext = str_replace("#PASSWORD#", htmlspecialchars($password), $htmltext);
	$htmltext = str_replace("#VOUCHER#", htmlspecialchars($voucher), $htmltext);

	echo $htmltext;
}

function captiveportal_reapply_attributes($cpentry, $attributes) {
	global $config, $cpzone, $g;

	if (isset($config['captiveportal'][$cpzone]['peruserbw'])) {
		$dwfaultbw_up = !empty($config['captiveportal'][$cpzone]['bwdefaultup']) ? $config['captiveportal'][$cpzone]['bwdefaultup'] : 0;
		$dwfaultbw_down = !empty($config['captiveportal'][$cpzone]['bwdefaultdn']) ? $config['captiveportal'][$cpzone]['bwdefaultdn'] : 0;
	} else {
		$dwfaultbw_up = $dwfaultbw_down = 0;
	}
	/* pipe throughputs must always be an integer, enforce that restriction again here. */
	if (isset($config['captiveportal'][$cpzone]['radiusperuserbw'])) {
		$bw_up = round(!empty($attributes['bw_up']) ? intval($attributes['bw_up'])/1000 : $dwfaultbw_up, 0);
		$bw_down = round(!empty($attributes['bw_down']) ? intval($attributes['bw_down'])/1000 : $dwfaultbw_down, 0);
	} else {
		$bw_up = round($dwfaultbw_up,0);
		$bw_down = round($dwfaultbw_down,0);
	}

	$bw_up_pipeno = $cpentry[1];
	$bw_down_pipeno = $cpentry[1]+1;

	if ($cpentry['bw_up'] !== $bw_up) {
		$_gb = @pfSense_ipfw_pipe("pipe {$bw_up_pipeno} config bw {$bw_up}Kbit/s queue 100 buckets 16");
		captiveportal_update_entry($cpentry['sessionid'], $bw_up, 'bw_up');
	}
	if ($cpentry['bw_down'] !== $bw_down) {
		$_gb = @pfSense_ipfw_pipe("pipe {$bw_down_pipeno} config bw {$bw_down}Kbit/s queue 100 buckets 16");
		captiveportal_update_entry($cpentry['sessionid'], $bw_down, 'bw_down');
	}
	unset($bw_up_pipeno, $bw_down_pipeno, $bw_up, $bw_down);
}

function captiveportal_update_entry($sessionid, $new_value, $field_to_update) {
	global $cpzone;

	if (!intval($new_value)) {
		$new_value = "'{$new_value}'";
	}
	captiveportal_write_db("UPDATE captiveportal SET {$field_to_update} = {$new_value} WHERE sessionid = '{$sessionid}'");
}

function portal_allow($clientip, $clientmac, $username, $password = null, $redirurl = null,
    $attributes = null, $pipeno = null, $authmethod = null, $context = 'first', $existing_sessionid = null) {
	global $g, $config, $cpzone;

	// Ensure we create an array if we are missing attributes
	if (!is_array($attributes)) {
		$attributes = array();
	}

	unset($sessionid);

	/* Do not allow concurrent login execution. */
	$cpdblck = lock("captiveportaldb{$cpzone}", LOCK_EX);

	if ($attributes['voucher']) {
		$remaining_time = $attributes['session_timeout'];
		$authmethod = "voucher"; // Set RADIUS-Attribute to Voucher to prevent ReAuth-Request for Vouchers Bug: #2155
		$context = "voucher";
	}

	$writecfg = false;
	/* If both "Add MAC addresses of connected users as pass-through MAC" and "Disable concurrent logins" are checked,
	then we need to check if the user was already authenticated using another MAC Address, and if so remove the previous Pass-Through MAC. */
	if ((isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) && ($config['captiveportal'][$cpzone]['noconcurrentlogins'] == 'last') && ($username != 'unauthenticated') && isset($config['captiveportal'][$cpzone]['passthrumacadd'])) {
		$mac = captiveportal_passthrumac_findbyname($username);
		if (!empty($mac)) {
			foreach ($config['captiveportal'][$cpzone]['passthrumac'] as $idx => $macent) {
				if ($macent['mac'] != $mac['mac']) {
					continue;
				}

				$pipeno = captiveportal_get_dn_passthru_ruleno($mac['mac']);
				if ($pipeno) {
					captiveportal_free_dn_ruleno($pipeno);
					@pfSense_ipfw_table("{$cpzone}_pipe_mac", IP_FW_TABLE_XDEL, "any,{$mac['mac']}");
					@pfSense_ipfw_table("{$cpzone}_pipe_mac", IP_FW_TABLE_XDEL, "{$mac['mac']},any");
					@pfSense_ipfw_pipe("pipe delete " . ($pipeno+1));
					@pfSense_ipfw_pipe("pipe delete " . $pipeno);
				}
				unset($config['captiveportal'][$cpzone]['passthrumac'][$idx]);
			}
		}
	}

	/* read in client database */
	$query = "WHERE ip = '{$clientip}'";
	$tmpusername = SQLite3::escapeString(strtolower($username));
	if (isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) {
		$query .= " OR (username != 'unauthenticated' AND lower(username) = '{$tmpusername}')";
	}
	$cpdb = captiveportal_read_db($query);

	/* Snapshot the timestamp */
	$allow_time = time();

	if ($existing_sessionid !== null) {
		// If we recieved this connection through XMLRPC sync :
		// we fetch allow_time from the info given by the other node
		$allow_time = $attributes['allow_time'];
	}
	$unsetindexes = array();

	foreach ($cpdb as $cpentry) {
		/* on the same ip */
		if ($cpentry[2] == $clientip) {
			if (isset($config['captiveportal'][$cpzone]['nomacfilter']) || $cpentry[3] == $clientmac) {
				captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "CONCURRENT LOGIN - REUSING OLD SESSION");
			} else {
				captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "CONCURRENT LOGIN - REUSING IP {$cpentry[2]} WITH DIFFERENT MAC ADDRESS {$cpentry[3]}");
			}
			$sessionid = $cpentry[5];
			break;
		} elseif (($attributes['voucher']) && ($username != 'unauthenticated') && ($cpentry[4] == $username)) {
			// user logged in with an active voucher. Check for how long and calculate
			// how much time we can give him (voucher credit - used time)
			$remaining_time = $cpentry[0] + $cpentry[7] - $allow_time;
			if ($remaining_time < 0) { // just in case.
				$remaining_time = 0;
			}

			/* This user was already logged in so we disconnect the old one, or 
			keep the old one, refusing the new login, or
			allow the login */

			if (!isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) {
				captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "config['captiveportal'][$cpzone]['noconcurrentlogins'] 2 does not exists = NOT set");		
			} else {
				captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "config['captiveportal'][$cpzone]['noconcurrentlogins'] 2 exists = set");		
			}

			if ($config['captiveportal'][$cpzone]['noconcurrentlogins'] == "last") {
				captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "Found last");
			} else {
				captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "Found NOT last");
			}
				
			if (!isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) {
				/* 'noconcurrentlogins' not set : accept login 'username' creating multiple sessions. */
				captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "config['captiveportal'][$cpzone]['noconcurrentlogins'] 3 does not exists = NOT set");
				captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "CONCURRENT LOGIN - NOT TERMINATING EXISTING SESSION(S)");			
			} elseif ($config['captiveportal'][$cpzone]['noconcurrentlogins'] == "last") {
				/* Classic situation : accept the new login, disconnect the old - present - connection */
				if (isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) {
					captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "config['captiveportal'][$cpzone]['noconcurrentlogins'] 4 exists = set");		
				} else {
					captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "config['captiveportal'][$cpzone]['noconcurrentlogins'] 4 does not exists = NOT set");		
				}
				
				captiveportal_disconnect($cpentry, 13);
				captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "CONCURRENT LOGIN - TERMINATING OLD SESSION");
				$unsetindexes[] = $cpentry[5];
				break;
			} else {
				/* Implicit 'first' : refuse the new login - 'username' is already logged in */
				captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "CONCURRENT VOUCHER LOGIN - NOT ALLOWED KEEPING OLD SESSION ");
				unlock($cpdblck);
				return 2;
			}
		} elseif ((isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) && ($username != 'unauthenticated')) {
			if ($config['captiveportal'][$cpzone]['noconcurrentlogins'] == "last") {
				/* on the same username */
				if (strcasecmp($cpentry[4], $username) == 0) {
					/* This user was already logged in so we disconnect the old one */
					captiveportal_disconnect($cpentry, 13);
					captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "CONCURRENT USER LOGIN - TERMINATING OLD SESSION");
					$unsetindexes[] = $cpentry[5];
					break;
				}
			} else {
				/* Implicit 'first' : refuse the new login - 'username' is already logged in */
				captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "CONCURRENT USER LOGIN - NOT ALLOWED KEEPING OLD SESSION ");
				unlock($cpdblck);
				return 2;				
			}
		}
	}
	unset($cpdb);

	if (!empty($unsetindexes)) {
		captiveportal_remove_entries($unsetindexes);
	}

	if ($attributes['voucher'] && $remaining_time <= 0) {
		return 0;       // voucher already used and no time left
	}

	if (!isset($sessionid)) {
		if ($existing_sessionid != null) { // existing_sessionid should only be set during XMLRPC sync
			$sessionid = $existing_sessionid;
		} else {
			/* generate unique session ID */
			$tod = gettimeofday();
			$sessionid = substr(md5(mt_rand() . $tod['sec'] . $tod['usec'] . $clientip . $clientmac), 0, 16);
		}

		if (isset($config['captiveportal'][$cpzone]['peruserbw'])) {
			$dwfaultbw_up = !empty($config['captiveportal'][$cpzone]['bwdefaultup']) ? $config['captiveportal'][$cpzone]['bwdefaultup'] : 0;
			$dwfaultbw_down = !empty($config['captiveportal'][$cpzone]['bwdefaultdn']) ? $config['captiveportal'][$cpzone]['bwdefaultdn'] : 0;
		} else {
			$dwfaultbw_up = $dwfaultbw_down = 0;
		}
		/* pipe throughputs must always be an integer, enforce that restriction again here. */
		if (isset($config['captiveportal'][$cpzone]['radiusperuserbw'])) {
			$bw_up = round(!empty($attributes['bw_up']) ? intval($attributes['bw_up'])/1000 : $dwfaultbw_up, 0);
			$bw_down = round(!empty($attributes['bw_down']) ? intval($attributes['bw_down'])/1000 : $dwfaultbw_down, 0);
		} else {
			$bw_up = round($dwfaultbw_up,0);
			$bw_down = round($dwfaultbw_down,0);
		}

		if (isset($config['captiveportal'][$cpzone]['passthrumacadd'])) {

			$mac = array();
			$mac['action'] = 'pass';
			$mac['mac'] = $clientmac;
			$mac['ip'] = $clientip; /* Used only for logging */
			$mac['username'] = $username;
			if ($attributes['voucher']) {
				$mac['logintype'] = "voucher";
			}
			if ($username == "unauthenticated") {
				$mac['descr'] = "Auto-added";
			} else if ($authmethod == "voucher") {
				$mac['descr'] = "Auto-added for voucher {$username}";
			} else {
				$mac['descr'] = "Auto-added for user {$username}";
			}
			if (!empty($bw_up)) {
				$mac['bw_up'] = $bw_up;
			}
			if (!empty($bw_down)) {
				$mac['bw_down'] = $bw_down;
			}
			if (!is_array($config['captiveportal'][$cpzone]['passthrumac'])) {
				$config['captiveportal'][$cpzone]['passthrumac'] = array();
			}
			//check for mac duplicates before adding it to config.
			$mac_duplicate = false;
			foreach($config['captiveportal'][$cpzone]['passthrumac'] as $mac_check){
				if($mac_check['mac'] == $mac['mac']){
					$mac_duplicate = true;
				}
			}
			if(!$mac_duplicate){
				$config['captiveportal'][$cpzone]['passthrumac'][] = $mac;
			}
			unlock($cpdblck);
			$macrules = captiveportal_passthrumac_configure_entry($mac);
			file_put_contents("{$g['tmp_path']}/macentry_{$cpzone}.rules.tmp", $macrules);
			mwexec("/sbin/ipfw -q {$g['tmp_path']}/macentry_{$cpzone}.rules.tmp");
			$writecfg = true;
		} else {
			/* See if a pipeno is passed, if not start sessions because this means there isn't one atm */
			if (is_null($pipeno)) {
				$pipeno = captiveportal_get_next_dn_ruleno('auth');
			}

			/* if the pool is empty, return appropriate message and exit */
			if (is_null($pipeno)) {
				captiveportal_syslog("Zone: {$cpzone} - WARNING!  Captive portal has reached maximum login capacity");
				unlock($cpdblck);
				return false;
			}

			$bw_up_pipeno = $pipeno;
			$bw_down_pipeno = $pipeno + 1;
			//$bw_up /= 1000; // Scale to Kbit/s
			$_gb = @pfSense_ipfw_pipe("pipe {$bw_up_pipeno} config bw {$bw_up}Kbit/s queue 100 buckets 16");
			$_gb = @pfSense_ipfw_pipe("pipe {$bw_down_pipeno} config bw {$bw_down}Kbit/s queue 100 buckets 16");

			$rule_entry = "{$clientip}/" . (is_ipaddrv6($clientip) ? "128" : "32");
			if (!isset($config['captiveportal'][$cpzone]['nomacfilter'])) {
				$rule_entry .= ",{$clientmac}";
			}
			$_gb = @pfSense_ipfw_table("{$cpzone}_auth_up", IP_FW_TABLE_XADD, "{$rule_entry}", $bw_up_pipeno);
			$_gb = @pfSense_ipfw_table("{$cpzone}_auth_down", IP_FW_TABLE_XADD, "{$rule_entry}", $bw_down_pipeno);

			if ($attributes['voucher']) {
				$attributes['session_timeout'] = $remaining_time;
			}

			/* handle empty attributes */
			$session_timeout = (!empty($attributes['session_timeout'])) ? $attributes['session_timeout'] : 'NULL';
			$idle_timeout = (!empty($attributes['idle_timeout'])) ? $attributes['idle_timeout'] : 'NULL';
			$session_terminate_time = (!empty($attributes['session_terminate_time'])) ? $attributes['session_terminate_time'] : 'NULL';
			$interim_interval = (!empty($attributes['interim_interval'])) ? $attributes['interim_interval'] : 'NULL';
			$traffic_quota = (!empty($attributes['maxbytes'])) ? $attributes['maxbytes'] : 'NULL';

			/* escape username */
			$safe_username = SQLite3::escapeString($username);

			/* encode password in Base64 just in case it contains commas */
			$bpassword = (isset($config['captiveportal'][$cpzone]['reauthenticate'])) ? base64_encode($password) : '';
			$insertquery = "INSERT INTO captiveportal (allow_time, pipeno, ip, mac, username, sessionid, bpassword, session_timeout, idle_timeout, session_terminate_time, interim_interval, traffic_quota, bw_up, bw_down, authmethod, context) ";
			$insertquery .= "VALUES ({$allow_time}, {$pipeno}, '{$clientip}', '{$clientmac}', '{$safe_username}', '{$sessionid}', '{$bpassword}', ";
			$insertquery .= "{$session_timeout}, {$idle_timeout}, {$session_terminate_time}, {$interim_interval}, {$traffic_quota}, {$bw_up}, {$bw_down}, '{$authmethod}', '{$context}')";

			/* store information to database */
			captiveportal_write_db($insertquery);
			unlock($cpdblck);
			unset($insertquery, $bpassword);

			$radacct = isset($config['captiveportal'][$cpzone]['radacct_enable']) ? true : false;
			if ($authmethod === 'radius' && $radacct) {
				captiveportal_send_server_accounting('start',
					$pipeno, // ruleno
					$username, // username
					$clientip, // clientip
					$clientmac, // clientmac
					$sessionid, // sessionid
					time());  // start time
			}
			if (captiveportal_xmlrpc_sync_get_details($syncip, $syncport, $syncuser, $syncpass, isset($existing_sessionid))) {
				// $existing_sessionid prevent carp loop : only forward
				// the connection to the other node if we generated the sessionid by ourselves
				$rpc_client = new pfsense_xmlrpc_client();
				$rpc_client->setConnectionData($syncip, $syncport, $syncuser, $syncpass);
				$rpc_client->set_noticefile("CaptivePortalUserSync");
				$arguments = array(
					'clientip' => $clientip,
					'clientmac' => $clientmac,
					'username' => $username,
					'password' => $password,
					'attributes' => $attributes,
					'allow_time' => $allow_time,
					'authmethod' => $authmethod,
					'context' => $context,
					'sessionid' => $sessionid
				);

				$rpc_client->xmlrpc_method('captive_portal_sync',
					array(
						'op' => 'connect_user',
						'zone' => $cpzone,
						'user' => base64_encode(serialize($arguments))
					)
				);
			}
		}
	} else {
		/* NOTE: #3062-11 If the pipeno has been allocated free it to not DoS the CP */
		if (!is_null($pipeno)) {
			captiveportal_free_dn_ruleno($pipeno);
		}

		unlock($cpdblck);
	}

	if ($writecfg == true) {
		write_config(gettext("Captive Portal allowed users configuration changed"));
	}

	if ($existing_sessionid !== null) {
		if (!empty($sessionid)) {
			return $sessionid;
		} else {
			return false;
		}
	}
	/* redirect user to desired destination */
	if (is_URL($attributes['url_redirection'], true)) {
		$my_redirurl = $attributes['url_redirection'];
	} else if (is_URL($config['captiveportal'][$cpzone]['redirurl'], true)) {
		$my_redirurl = $config['captiveportal'][$cpzone]['redirurl'];
	} else if (is_URL($redirurl, true)) {
		$my_redirurl = $redirurl;
	}

	if (isset($config['captiveportal'][$cpzone]['logoutwin_enable']) && !isset($config['captiveportal'][$cpzone]['passthrumacadd'])) {
		$ourhostname = portal_hostname_from_client_ip($clientip);
		$protocol = (isset($config['captiveportal'][$cpzone]['httpslogin'])) ? 'https://' : 'http://';
		$logouturl = "{$protocol}{$ourhostname}/";

		if (isset($attributes['reply_message'])) {
			$message = $attributes['reply_message'];
		} else {
			$message = 0;
		}

		include_once("{$g['varetc_path']}/captiveportal-{$cpzone}-logout.html");

	} else {
		portal_reply_page($my_redirurl, "redir", "Just redirect the user.");
	}

	return $sessionid;
}


/*
 * Used for when pass-through credits are enabled.
 * Returns true when there was at least one free login to deduct for the MAC.
 * Expired entries are removed as they are seen.
 * Active entries are updated according to the configuration.
 */
function portal_consume_passthrough_credit($clientmac) {
	global $config, $cpzone;

	if (!empty($config['captiveportal'][$cpzone]['freelogins_count']) && is_numeric($config['captiveportal'][$cpzone]['freelogins_count'])) {
		$freeloginscount = $config['captiveportal'][$cpzone]['freelogins_count'];
	} else {
		return false;
	}

	if (!empty($config['captiveportal'][$cpzone]['freelogins_resettimeout']) && is_numeric($config['captiveportal'][$cpzone]['freelogins_resettimeout'])) {
		$resettimeout = $config['captiveportal'][$cpzone]['freelogins_resettimeout'];
	} else {
		return false;
	}

	if ($freeloginscount < 1 || $resettimeout <= 0 || !$clientmac) {
		return false;
	}

	$updatetimeouts = isset($config['captiveportal'][$cpzone]['freelogins_updatetimeouts']);

	/*
	 * Read database of used MACs.  Lines are a comma-separated list
	 * of the time, MAC, then the count of pass-through credits remaining.
	 */
	$usedmacs = captiveportal_read_usedmacs_db();

	$currenttime = time();
	$found = false;
	foreach ($usedmacs as $key => $usedmac) {
		$usedmac = explode(",", $usedmac);

		if ($usedmac[1] == $clientmac) {
			if ($usedmac[0] + ($resettimeout * 3600) > $currenttime) {
				if ($usedmac[2] < 1) {
					if ($updatetimeouts) {
						$usedmac[0] = $currenttime;
						unset($usedmacs[$key]);
						$usedmacs[] = implode(",", $usedmac);
						captiveportal_write_usedmacs_db($usedmacs);
						xmlrpc_sync_usedmacs($usedmacs);
					}

					return false;
				} else {
					$usedmac[2] -= 1;
					$usedmacs[$key] = implode(",", $usedmac);
				}

				$found = true;
			} else {
				unset($usedmacs[$key]);
			}

			break;
		} else if ($usedmac[0] + ($resettimeout * 3600) <= $currenttime) {
			unset($usedmacs[$key]);
		}
	}

	if (!$found) {
		$usedmac = array($currenttime, $clientmac, $freeloginscount - 1);
		$usedmacs[] = implode(",", $usedmac);
	}

	captiveportal_write_usedmacs_db($usedmacs);
	xmlrpc_sync_usedmacs($usedmacs);
	return true;
}

function xmlrpc_sync_usedmacs($usedmacs) { 
	global $config, $cpzone;

	// XMLRPC Call over to the other node
	if (captiveportal_xmlrpc_sync_get_details($syncip, $syncport,
	    $syncuser, $syncpass, $carp_loop)) {
		$rpc_client = new pfsense_xmlrpc_client();
		$rpc_client->setConnectionData($syncip, $syncport, $syncuser, $syncpass);
		$rpc_client->set_noticefile("CaptivePortalUsedmacsSync");
		$arguments = array(
			'usedmacs' => $usedmacs
		);

		$rpc_client->xmlrpc_method('captive_portal_sync',
			array(
				'op' => 'write_usedmacs',
				'zone' => $cpzone,
				'arguments' => base64_encode(serialize($arguments))
			)
		);
	}
}

function captiveportal_read_usedmacs_db() {
	global $g, $cpzone;

	$cpumaclck = lock("captiveusedmacs{$cpzone}");
	if (file_exists("{$g['vardb_path']}/captiveportal_usedmacs_{$cpzone}.db")) {
		$usedmacs = file("{$g['vardb_path']}/captiveportal_usedmacs_{$cpzone}.db", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if (!$usedmacs) {
			$usedmacs = array();
		}
	} else {
		$usedmacs = array();
	}

	unlock($cpumaclck);
	return $usedmacs;
}

function captiveportal_write_usedmacs_db($usedmacs) {
	global $g, $cpzone;

	$cpumaclck = lock("captiveusedmacs{$cpzone}", LOCK_EX);
	@file_put_contents("{$g['vardb_path']}/captiveportal_usedmacs_{$cpzone}.db", implode("\n", $usedmacs));
	unlock($cpumaclck);
}

function captiveportal_blocked_mac($mac) {
	global $config, $g, $cpzone;

	if (empty($mac) || !is_macaddr($mac)) {
		return false;
	}

	if (!is_array($config['captiveportal'][$cpzone]['passthrumac'])) {
		return false;
	}

	foreach ($config['captiveportal'][$cpzone]['passthrumac'] as $passthrumac) {
		if (($passthrumac['action'] == 'block') &&
		    ($passthrumac['mac'] == strtolower($mac))) {
			return true;
		}
	}

	return false;

}

/* Captiveportal Radius Accounting */

function gigawords($bytes) {

	/*
	 * RFC2866 Specifies a 32bit unsigned integer, which is a max of 4294967295
	 * Currently there is a fault in the PECL radius_put_int function which can handle only 32bit signed integer.
	 */

	// We use BCMath functions since normal integers don't work with so large numbers
	$gigawords = bcdiv( bcsub( $bytes, remainder($bytes) ) , GIGAWORDS_RIGHT_OPERAND) ;

	// We need to manually set this to a zero instead of NULL for put_int() safety
	if (is_null($gigawords)) {
		$gigawords = 0;
	}

	return $gigawords;
}

function remainder($bytes) {
	// Calculate the bytes we are going to send to the radius
	$bytes = bcmod($bytes, GIGAWORDS_RIGHT_OPERAND);

	if (is_null($bytes)) {
		$bytes = 0;
	}

    return $bytes;
}

function captiveportal_send_server_accounting($type = 'on', $ruleno = null, $username = null, $clientip = null, $clientmac = null, $sessionid = null, $start_time = null, $stop_time = null, $term_cause = null) {
	global $cpzone, $config;

	$cpcfg = $config['captiveportal'][$cpzone];
	$acctcfg = auth_get_authserver($cpcfg['radacct_server']);

	if (!isset($cpcfg['radacct_enable']) || empty($acctcfg) ||
	    captiveportal_ha_is_node_in_backup_mode($cpzone)) {
		return null;
	}

	if ($type === 'on') {
		$racct = new Auth_RADIUS_Acct_On;
	} elseif ($type === 'off') {
		$racct = new Auth_RADIUS_Acct_Off;
	} elseif ($type === 'start') {
		$racct = new Auth_RADIUS_Acct_Start;
		if (!is_int($start_time)) {
			$start_time = time();
		}
	} elseif ($type === 'stop') {
		$racct = new Auth_RADIUS_Acct_Stop;
		if (!is_int($stop_time)) {
			$stop_time = time();
		}
	} elseif ($type === 'update') {
        $racct = new Auth_RADIUS_Acct_Update;
		if (!is_int($stop_time)) {
			$stop_time = time(); // "top time" here will be used only for calculating session time.
		}
	} else {
		return null;
	}

	$racct->addServer($acctcfg['host'], $acctcfg['radius_acct_port'],
		$acctcfg['radius_secret'], $acctcfg['radius_timeout']);

	$racct->authentic = RADIUS_AUTH_RADIUS;
	if ($cpcfg['auth_method'] === 'radmac' && $username === "unauthenticated" && !empty($clientmac)) {
		$racct->username = mac_format($clientmac);
	} elseif (!empty($username)) {
		$racct->username = $username;
	}

	if (PEAR::isError($racct->start())) {
		captiveportal_syslog('RADIUS ACCOUNTING FAILED : '.$racct->getError());
		$racct->close();
		return null;
	}

	$nasip = $acctcfg['radius_nasip_attribute'];
	if (!is_ipaddr($nasip)) {
		$nasip = get_interface_ip($nasip);
		if (!is_ipaddr($nasip)) {
			$nasip = get_interface_ip();//We use WAN interface IP as fallback for NAS-IP-Address
		}
	}
	$nasmac = get_interface_mac(find_ip_interface($nasip));
	$racct->putAttribute(RADIUS_NAS_IP_ADDRESS, $nasip, "addr");

	$racct->putAttribute(RADIUS_NAS_IDENTIFIER, empty($cpcfg["radiusnasid"]) ? "CaptivePortal-{$cpzone}" : $cpcfg["radiusnasid"] );

	if (is_int($ruleno)) {
		$racct->putAttribute(RADIUS_NAS_PORT_TYPE, RADIUS_ETHERNET);
		$racct->putAttribute(RADIUS_NAS_PORT, intval($ruleno), 'integer');
	}

	if (!empty($sessionid)) {
		$racct->putAttribute(RADIUS_ACCT_SESSION_ID, $sessionid);
	}

	if (!empty($clientip) && is_ipaddr($clientip)) {
		$racct->putAttribute(RADIUS_FRAMED_IP_ADDRESS, $clientip, "addr");
	}
	if (!empty($clientmac)) {
		$racct->putAttribute(RADIUS_CALLING_STATION_ID, mac_format($clientmac));
	}
	if (!empty($nasmac)) {
		$racct->putAttribute(RADIUS_CALLED_STATION_ID, mac_format($nasmac).':'.gethostname());
	}

	// Accounting request Stop and Update : send the current data volume
	if (($type === 'stop' || $type === 'update') && is_int($start_time)) {
		$volume = getVolume($clientip);
		$session_time = $stop_time - $start_time;
		$volume['input_bytes_radius'] = remainder($volume['input_bytes']);
		$volume['input_gigawords'] = gigawords($volume['input_bytes']);
		$volume['output_bytes_radius'] = remainder($volume['output_bytes']);
		$volume['output_gigawords'] = gigawords($volume['output_bytes']);

		// Volume stuff: Ingress
		$racct->putAttribute(RADIUS_ACCT_INPUT_PACKETS, intval($volume['input_pkts']), "integer");
		$racct->putAttribute(RADIUS_ACCT_INPUT_OCTETS, intval($volume['input_bytes_radius']), "integer");
		// Volume stuff: Outgress
		$racct->putAttribute(RADIUS_ACCT_OUTPUT_PACKETS, intval($volume['output_pkts']), "integer");
		$racct->putAttribute(RADIUS_ACCT_OUTPUT_OCTETS, intval($volume['output_bytes_radius']), "integer");
		$racct->putAttribute(RADIUS_ACCT_SESSION_TIME, intval($session_time), "integer");

		$racct->putAttribute(CUSTOM_RADIUS_ACCT_OUTPUT_GIGAWORDS, intval($volume['output_gigawords']), "integer");
		$racct->putAttribute(CUSTOM_RADIUS_ACCT_INPUT_GIGAWORDS, intval($volume['input_gigawords']), "integer");
		// Set session_time
		$racct->session_time = $session_time;
	}

	if ($type === 'stop') {
		if (empty($term_cause)) {
			$term_cause = 1;
		}
		$racct->putAttribute(RADIUS_ACCT_TERMINATE_CAUSE, $term_cause);
	}

	// Send request
	$result = $racct->send();

	if (PEAR::isError($result)) {
		 captiveportal_syslog('RADIUS ACCOUNTING FAILED : '.$racct->getError());
		 $result = null;
	} elseif ($result !== true) {
		$result = false;
	}

	$racct->close();
	return $result;
}

function captiveportal_isip_logged($clientip) {
	global $g, $cpzone;

	/* read in client database */
	$query = "WHERE ip = '{$clientip}'";
	$cpdb = captiveportal_read_db($query);
	foreach ($cpdb as $cpentry) {
		return $cpentry;
	}
}

?>
