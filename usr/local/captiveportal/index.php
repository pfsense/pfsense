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
	pfSense_BUILDER_BINARIES:	/sbin/ipfw	
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

$orig_host = $_ENV['HTTP_HOST'];
$orig_request = $_REQUEST['redirurl'];
$clientip = $_SERVER['REMOTE_ADDR'];

if (!$clientip) {
	/* not good - bail out */
	log_error("Captive portal could not determine client's IP address.");
	$error_message = "An error occurred.  Please check the system logs for more information.";
	portal_reply_page($redirurl, "error", $errormsg);
	exit;
}

if (isset($config['captiveportal']['httpslogin']))
    $ourhostname = $config['captiveportal']['httpsname'] . ":8001";
else {
    $ifip = portal_ip_from_client_ip($clientip);
    if (!$ifip)
    	$ourhostname = $config['system']['hostname'] . ":8000";
    else
    	$ourhostname = "{$ifip}:8000";
}

if ($orig_host != $ourhostname) {
    /* the client thinks it's connected to the desired web server, but instead
       it's connected to us. Issue a redirect... */

    if (isset($config['captiveportal']['httpslogin']))
        header("Location: https://{$ourhostname}/index.php?redirurl=" . urlencode("http://{$orig_host}{$orig_request}"));
    else
        header("Location: http://{$ourhostname}/index.php?redirurl=" . urlencode("http://{$orig_host}{$orig_request}"));

    exit;
}
if (preg_match("/redirurl=(.*)/", $orig_request, $matches))
    $redirurl = urldecode($matches[1]);
if ($_POST['redirurl'])
    $redirurl = $_POST['redirurl'];
if (!empty($config['captiveportal']['redirurl']))
	$redirurl = $config['captiveportal']['redirurl'];

$macfilter = !isset($config['captiveportal']['nomacfilter']);
$passthrumac = isset($config['captiveportal']['passthrumacadd']);

/* find MAC address for client */
$clientmac = arp_get_mac_by_ip($clientip);
if (!$clientmac && ($macfilter || $passthrumac)) {
    /* unable to find MAC address - shouldn't happen! - bail out */
    captiveportal_logportalauth("unauthenticated","noclientmac",$clientip,"ERROR");
    echo "An error occurred.  Please check the system logs for more information.";
    log_error("Captive portal could not determine client's MAC address.  Disable MAC address filtering in captive portal if you do not need this functionality.");
    exit;
}

/* find out if we need RADIUS + RADIUSMAC or not */
if (file_exists("{$g['vardb_path']}/captiveportal_radius.db")) {
    $radius_enable = TRUE;
    if (isset($config['captiveportal']['radmac_enable']))
        $radmac_enable = TRUE;
}

if ($_POST['logout_id']) {
	echo <<<EOD
<HTML>
<HEAD><TITLE>Disconnecting...</TITLE></HEAD>
<BODY BGCOLOR="#435370">
<SPAN STYLE="color: #ffffff; font-family: Tahoma, Verdana, Arial, Helvetica, sans-serif; font-size: 11px;">
<B>You have been disconnected.</B>
</SPAN>
<SCRIPT LANGUAGE="JavaScript">
<!--
setTimeout('window.close();',5000) ;
-->
</SCRIPT>
</BODY>
</HTML>

EOD;
	disconnect_client($_POST['logout_id']);
	exit;
} else if ($clientmac && $radmac_enable && portal_mac_radius($clientmac,$clientip)) {
    /* radius functions handle everything so we exit here since we're done */
    exit;

} else if (portal_consume_passthrough_credit($clientmac)) {
    /* allow the client through if it had a pass-through credit for its MAC */
    captiveportal_logportalauth("unauthenticated",$clientmac,$clientip,"ACCEPT");
    portal_allow($clientip, $clientmac, "unauthenticated");

} else if ($_POST['accept'] && $_POST['auth_voucher']) {

    $voucher = trim($_POST['auth_voucher']);
    $timecredit = voucher_auth($voucher);
    // $timecredit contains either a credit in minutes or an error message
    if ($timecredit > 0) {  // voucher is valid. Remaining minutes returned
        // if multiple vouchers given, use the first as username
        $a_vouchers = split("[\t\n\r ]+",$voucher);
        $voucher = $a_vouchers[0];
        $attr = array( 'voucher' => 1,
                'session_timeout' => $timecredit*60,
                'session_terminate_time' => 0);
        if (portal_allow($clientip, $clientmac,$voucher,null,$attr)) {

            // YES: user is good for $timecredit minutes.
            captiveportal_logportalauth($voucher,$clientmac,$clientip,"Voucher login good for $timecredit min.");
        } else {
            portal_reply_page($redirurl, "error", $config['voucher']['msgexpired'] ? $config['voucher']['msgexpired']: $errormsg);
        }
    } else if (-1 == $timecredit) {  // valid but expired
        captiveportal_logportalauth($voucher,$clientmac,$clientip,"FAILURE","voucher expired");
        portal_reply_page($redirurl, "error", $config['voucher']['msgexpired'] ? $config['voucher']['msgexpired']: $errormsg);
    } else {
        captiveportal_logportalauth($voucher,$clientmac,$clientip,"FAILURE");
        portal_reply_page($redirurl, "error", $config['voucher']['msgnoaccess'] ? $config['voucher']['msgnoaccess'] : $errormsg);
    }

} else if ($_POST['accept'] && $radius_enable) {

    if ($_POST['auth_user'] && $_POST['auth_pass']) {
        $auth_list = radius($_POST['auth_user'],$_POST['auth_pass'],$clientip,$clientmac,"USER LOGIN");
	$type = "error";
	if (!empty($auth_list['url_redirection'])) {
		$redirurl = $auth_list['url_redirection'];
		$type = "redir";
	}

        if ($auth_list['auth_val'] == 1) {
            captiveportal_logportalauth($_POST['auth_user'],$clientmac,$clientip,"ERROR",$auth_list['error']);
 	    portal_reply_page($redirurl, $type, $auth_list['error'] ? $auth_list['error'] : $errormsg);
        }
        else if ($auth_list['auth_val'] == 3) {
            captiveportal_logportalauth($_POST['auth_user'],$clientmac,$clientip,"FAILURE",$auth_list['reply_message']);
            portal_reply_page($redirurl, $type, $auth_list['reply_message'] ? $auth_list['reply_message'] : $errormsg);
        }
    } else {
        captiveportal_logportalauth($_POST['auth_user'],$clientmac,$clientip,"ERROR");
        portal_reply_page($redirurl, "error", $errormsg);
    }

} else if ($_POST['accept'] && $config['captiveportal']['auth_method'] == "local") {

	//check against local user manager
	$loginok = local_backed($_POST['auth_user'], $_POST['auth_pass']);
    if ($loginok){
        captiveportal_logportalauth($_POST['auth_user'],$clientmac,$clientip,"LOGIN");
        portal_allow($clientip, $clientmac,$_POST['auth_user']);
    } else {
        captiveportal_logportalauth($_POST['auth_user'],$clientmac,$clientip,"FAILURE");
        portal_reply_page($redirurl, "error", $errormsg);
    }
} else if ($_POST['accept'] && $clientip) {
    captiveportal_logportalauth("unauthenticated",$clientmac,$clientip,"ACCEPT");
    portal_allow($clientip, $clientmac, "unauthenticated");
} else {
    /* display captive portal page */
    portal_reply_page($redirurl, "login",null,$clientmac,$clientip);
}

exit;

function portal_reply_page($redirurl, $type = null, $message = null, $clientmac = null, $clientip = null, $username = null, $password = null) {
	global $g, $config;

	/* Get captive portal layout */
	if ($type == "redir") {
		header("Location: {$redirurl}");
		return;
	} else if ($type == "login")
		$htmltext = get_include_contents("{$g['varetc_path']}/captiveportal.html");
	else
		$htmltext = get_include_contents("{$g['varetc_path']}/captiveportal-error.html");

	/* substitute the PORTAL_REDIRURL variable */
	if ($config['captiveportal']['preauthurl']) {
		$htmltext = str_replace("\$PORTAL_REDIRURL\$", "{$config['captiveportal']['preauthurl']}", $htmltext);
		$htmltext = str_replace("#PORTAL_REDIRURL#", "{$config['captiveportal']['preauthurl']}", $htmltext);
	}

	/* substitute other variables */
	if (isset($config['captiveportal']['httpslogin'])) {
		$htmltext = str_replace("\$PORTAL_ACTION\$", "https://{$config['captiveportal']['httpsname']}:8001/", $htmltext);
		$htmltext = str_replace("#PORTAL_ACTION#", "https://{$config['captiveportal']['httpsname']}:8001/", $htmltext);
	} else {
		$ifip = portal_ip_from_client_ip($clientip);
		if (!$ifip)
			$ourhostname = $config['system']['hostname'] . ":8000";
		else
			$ourhostname = "{$ifip}:8000";
		$htmltext = str_replace("\$PORTAL_ACTION\$", "http://{$ourhostname}/", $htmltext);
		$htmltext = str_replace("#PORTAL_ACTION#", "http://{$ourhostname}/", $htmltext);
	}

	$htmltext = str_replace("\$PORTAL_REDIRURL\$", htmlspecialchars($redirurl), $htmltext);
	$htmltext = str_replace("\$PORTAL_MESSAGE\$", htmlspecialchars($message), $htmltext);
	$htmltext = str_replace("\$CLIENT_MAC\$", htmlspecialchars($clientmac), $htmltext);
	$htmltext = str_replace("\$CLIENT_IP\$", htmlspecialchars($clientip), $htmltext);

	// Special handling case for captive portal master page so that it can be ran 
	// through the PHP interpreter using the include method above.  We convert the
	// $VARIABLE$ case to #VARIABLE# in /etc/inc/captiveportal.inc before writing out.
	$htmltext = str_replace("#PORTAL_REDIRURL#", htmlspecialchars($redirurl), $htmltext);
	$htmltext = str_replace("#PORTAL_MESSAGE#", htmlspecialchars($message), $htmltext);
	$htmltext = str_replace("#CLIENT_MAC#", htmlspecialchars($clientmac), $htmltext);
	$htmltext = str_replace("#CLIENT_IP#", htmlspecialchars($clientip), $htmltext);
	$htmltext = str_replace("#USERNAME#", htmlspecialchars($username), $htmltext);
	$htmltext = str_replace("#PASSWORD#", htmlspecialchars($password), $htmltext);

    echo $htmltext;
}

function portal_mac_radius($clientmac,$clientip) {
    global $config ;

    $radmac_secret = $config['captiveportal']['radmac_secret'];

    /* authentication against the radius server */
    $username = mac_format($clientmac);
    $auth_list = radius($username,$radmac_secret,$clientip,$clientmac,"MACHINE LOGIN");
    if ($auth_list['auth_val'] == 2)
        return TRUE;
    if (!empty($auth_list['url_redirection']))
	portal_reply_page($auth_list['url_redirection'], "redir");

    return FALSE;
}

function portal_allow($clientip,$clientmac,$username,$password = null, $attributes = null, $ruleno = null)  {

	global $redirurl, $g, $config, $type, $passthrumac, $_POST;

	/* See if a ruleno is passed, if not start sessions because this means there isn't one atm */
	if ($ruleno == null)
		$ruleno = captiveportal_get_next_ipfw_ruleno();

	/* if the pool is empty, return appropriate message and exit */
	if (is_null($ruleno)) {
		portal_reply_page($redirurl, "error", "System reached maximum login capacity");
		log_error("WARNING!  Captive portal has reached maximum login capacity");
		exit;
	}

	// Ensure we create an array if we are missing attributes
	if (!is_array($attributes))
		$attributes = array();

	/* read in client database */
	$cpdb = captiveportal_read_db();

	$radiusservers = captiveportal_get_radius_servers();

	if ($attributes['voucher'])
		$remaining_time = $attributes['session_timeout'];

	$writecfg = false;
	/* Find an existing session */
	if ((isset($config['captiveportal']['noconcurrentlogins'])) && $passthrumac) {
		if (isset($config['captiveportal']['passthrumacadd'])) {
			$mac = captiveportal_passthrumac_findbyname($username);
			if (!empty($mac)) {
				if ($_POST['replacemacpassthru']) {
					foreach ($config['captiveportal']['passthrumac'] as $idx => $macent) {
						if ($macent['mac'] == $mac['mac']) {
							$macrules = "";
							$ruleno = captiveportal_get_ipfw_passthru_ruleno($mac['mac']);
                                			if ($ruleno) {
								captiveportal_free_ipfw_ruleno($ruleno, true);
                                        			$macrules .= "delete {$ruleno}\n";
								++$ruleno;
                                        			$macrules .= "delete {$ruleno}\n";
                                			}
							unset($config['captiveportal']['passthrumac'][$idx]);
							$mac['mac'] = $clientmac;
							$config['captiveportal']['passthrumac'][] = $mac;
							$macrules .= captiveportal_passthrumac_configure_entry($mac);
							file_put_contents("{$g['tmp_path']}/macentry.rules.tmp", $macrules);
							mwexec("/sbin/ipfw -q {$g['tmp_path']}/macentry.rules.tmp");
							$writecfg = true;
							$sessionid = true;
							break;
						}
					}
                                } else {
					portal_reply_page($redirurl, "error", "Username: {$username} is already authenticated using another MAC address.",
						$clientmac, $clientip, $username, $password);
					exit;
				}
			}
		}
	}

	$nousers = count($cpdb);
	for ($i = 0; $i < $nousers; $i++) {
		/* on the same ip */
		if($cpdb[$i][2] == $clientip) {
			captiveportal_logportalauth($cpdb[$i][4],$cpdb[$i][3],$cpdb[$i][2],"CONCURRENT LOGIN - REUSING OLD SESSION");
			$sessionid = $cpdb[$i][5];
			break;
		}
		elseif (($attributes['voucher']) && ($username != 'unauthenticated') && ($cpdb[$i][4] == $username)) {
			// user logged in with an active voucher. Check for how long and calculate 
			// how much time we can give him (voucher credit - used time)
			$remaining_time = $cpdb[$i][0] + $cpdb[$i][7] - time();
			if ($remaining_time < 0)    // just in case. 
				$remaining_time = 0;

			/* This user was already logged in so we disconnect the old one */
			captiveportal_disconnect($cpdb[$i],$radiusservers,13);
			captiveportal_logportalauth($cpdb[$i][4],$cpdb[$i][3],$cpdb[$i][2],"CONCURRENT LOGIN - TERMINATING OLD SESSION");
			unset($cpdb[$i]);
			break;
		}
		elseif ((isset($config['captiveportal']['noconcurrentlogins'])) && ($username != 'unauthenticated')) {
			/* on the same username */
			if (strcasecmp($cpdb[$i][4], $username) == 0) {
				/* This user was already logged in so we disconnect the old one */
				captiveportal_disconnect($cpdb[$i],$radiusservers,13);
				captiveportal_logportalauth($cpdb[$i][4],$cpdb[$i][3],$cpdb[$i][2],"CONCURRENT LOGIN - TERMINATING OLD SESSION");
				unset($cpdb[$i]);
				break;
			}
		}
	}

	if ($attributes['voucher'] && $remaining_time <= 0)
		return 0;       // voucher already used and no time left

	if (!isset($sessionid)) {
		/* generate unique session ID */
		$tod = gettimeofday();
		$sessionid = substr(md5(mt_rand() . $tod['sec'] . $tod['usec'] . $clientip . $clientmac), 0, 16);

		/* Add rules for traffic shaping
		 * We don't need to add extra rules since traffic will pass due to the following kernel option
		 * net.inet.ip.fw.one_pass: 1
		 */
		$peruserbw = isset($config['captiveportal']['peruserbw']);

		$bw_up = isset($attributes['bw_up']) ? trim($attributes['bw_up']) : $config['captiveportal']['bwdefaultup'];
		$bw_down = isset($attributes['bw_down']) ? trim($attributes['bw_down']) : $config['captiveportal']['bwdefaultdn'];

		if ($passthrumac) {
			$mac = array();
			$mac['mac'] = $clientmac;
			if (isset($config['captiveportal']['passthrumacaddusername']))
				$mac['username'] = $username;
			$mac['descr'] =  "Auto added pass-through MAC for user {$username}";
			if (!empty($bw_up))
				$mac['bw_up'] = $bw_up;
			if (!empty($bw_down))
				$mac['bw_down'] = $bw_down;
			if (!is_array($config['captiveportal']['passthrumac']))
				$config['captiveportal']['passthrumac'] = array();
			$config['captiveportal']['passthrumac'][] = $mac;
			$macrules = captiveportal_passthrumac_configure_entry($mac);
			file_put_contents("{$g['tmp_path']}/macentry.rules.tmp", $macrules);
			mwexec("/sbin/ipfw -q {$g['tmp_path']}/macentry.rules.tmp");
			$writecfg = true;
		} else {
			if ($peruserbw && !empty($bw_up) && is_numeric($bw_up)) {
				$bw_up_pipeno = $ruleno + 20000;
				//$bw_up /= 1000; // Scale to Kbit/s
				mwexec("/sbin/ipfw pipe {$bw_up_pipeno} config bw {$bw_up}Kbit/s queue 100");

				if (!isset($config['captiveportal']['nomacfilter']))
					mwexec("/sbin/ipfw table 1 add {$clientip} mac {$clientmac} {$bw_up_pipeno}");
				else
					mwexec("/sbin/ipfw table 1 add {$clientip} {$bw_up_pipeno}");
			} else {
				if (!isset($config['captiveportal']['nomacfilter']))
					mwexec("/sbin/ipfw table 1 add {$clientip} mac {$clientmac}");
				else
					mwexec("/sbin/ipfw table 1 add {$clientip}");
			}
			if ($peruserbw && !empty($bw_down) && is_numeric($bw_down)) {
				$bw_down_pipeno = $ruleno + 20001;
				//$bw_down /= 1000; // Scale to Kbit/s
				mwexec("/sbin/ipfw pipe {$bw_down_pipeno} config bw {$bw_down}Kbit/s queue 100");

				if (!isset($config['captiveportal']['nomacfilter']))
					mwexec("/sbin/ipfw table 2 add {$clientip} mac {$clientmac} {$bw_down_pipeno}");
				else
					mwexec("/sbin/ipfw table 2 add {$clientip} {$bw_down_pipeno}");
			} else {
				if (!isset($config['captiveportal']['nomacfilter']))
					mwexec("/sbin/ipfw table 2 add {$clientip} mac {$clientmac}");
				else
					mwexec("/sbin/ipfw table 2 add {$clientip}");
			}

			if ($attributes['voucher'])
				$attributes['session_timeout'] = $remaining_time;

			/* encode password in Base64 just in case it contains commas */
			$bpassword = base64_encode($password);
			$cpdb[] = array(time(), $ruleno, $clientip, $clientmac, $username, $sessionid, $bpassword,
				$attributes['session_timeout'], $attributes['idle_timeout'], $attributes['session_terminate_time']);

			if (isset($config['captiveportal']['radacct_enable']) && !empty($radiusservers)) {
				$acct_val = RADIUS_ACCOUNTING_START($ruleno,
                                		$username, $sessionid, $radiusservers, $clientip, $clientmac);
				if ($acct_val == 1)
					captiveportal_logportalauth($username,$clientmac,$clientip,$type,"RADIUS ACCOUNTING FAILED");
			}

			/* rewrite information to database */
			captiveportal_write_db($cpdb);
		}
	}

	if ($writecfg == true)
		write_config();

	/* redirect user to desired destination */
	if (!empty($attributes['url_redirection']))
		$my_redirurl = $attributes['url_redirection'];
	else if ($config['captiveportal']['redirurl'])
		$my_redirurl = $config['captiveportal']['redirurl'];
	else
		$my_redirurl = $redirurl;

	if(isset($config['captiveportal']['logoutwin_enable']) && !$passthrumac) {

		if (isset($config['captiveportal']['httpslogin']))
			$logouturl = "https://{$config['captiveportal']['httpsname']}:8001/";
		else {
			$ifip = portal_ip_from_client_ip($clientip);
			if (!$ifip)
				$ourhostname = $config['system']['hostname'] . ":8000";
			else
				$ourhostname = "{$ifip}:8000";
			$logouturl = "http://{$ourhostname}/";
		}

		if (isset($attributes['reply_message']))
			$message = $attributes['reply_message'];
		else
			$message = 0;

		include("{$g['varetc_path']}/captiveportal-logout.html");

	} else {
		header("Location: " . $my_redirurl);
	}

	return $sessionid;
}



/* remove a single client by session ID
 *  by Dinesh Nair
 */
function disconnect_client($sessionid, $logoutReason = "LOGOUT", $term_cause = 1) {
    global $g, $config;

    /* read database */
    $cpdb = captiveportal_read_db();

    $radiusservers = captiveportal_get_radius_servers();

    /* find entry */
    $dbcount = count($cpdb);
    for ($i = 0; $i < $dbcount; $i++) {
        if ($cpdb[$i][5] == $sessionid) {
            captiveportal_disconnect($cpdb[$i],$radiusservers, $term_cause);
            captiveportal_logportalauth($cpdb[$i][4],$cpdb[$i][3],$cpdb[$i][2],$logoutReason);
            unset($cpdb[$i]);
            break;
        }
    }

    /* write database */
    captiveportal_write_db($cpdb);
}

/*
 * Used for when pass-through credits are enabled.
 * Returns true when there was at least one free login to deduct for the MAC.
 * Expired entries are removed as they are seen.
 * Active entries are updated according to the configuration.
 */
function portal_consume_passthrough_credit($clientmac) {
	global $config;

	if (!empty($config['captiveportal']['freelogins_count']) && is_numeric($config['captiveportal']['freelogins_count']))
		$freeloginscount = $config['captiveportal']['freelogins_count'];
	else
		return false;

	if (!empty($config['captiveportal']['freelogins_resettimeout']) && is_numeric($config['captiveportal']['freelogins_resettimeout']))
		$resettimeout = $config['captiveportal']['freelogins_resettimeout'];
	else
		return false;

	if ($freeloginscount < 1 || $resettimeout <= 0 || !clientmac)
		return false;

	$updatetimeouts = isset($config['captiveportal']['freelogins_updatetimeouts']);

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
					}

					return false;
				} else {
					$usedmac[2] -= 1;
					$usedmacs[$key] = implode(",", $usedmac);
				}

				$found = true;
			} else
				unset($usedmacs[$key]);

			break;
		} else if ($usedmac[0] + ($resettimeout * 3600) <= $currenttime)
				unset($usedmacs[$key]);
	}

	if (!$found) {
		$usedmac = array($currenttime, $clientmac, $freeloginscount - 1);
		$usedmacs[] = implode(",", $usedmac);
	}

	captiveportal_write_usedmacs_db($usedmacs);
	return true;
}

function captiveportal_read_usedmacs_db() {
	global $g;

	$cpumaclck = lock('captiveusedmacs');
	if (file_exists("{$g['vardb_path']}/captiveportal_usedmacs.db")) {
		$usedmacs = file("{$g['vardb_path']}/captiveportal_usedmacs.db", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if (!usedmacs)
			$usedmacs = array();
	} else
		$usedmacs = array();

	unlock($cpumaclck);
	return $usedmacs;
}

function captiveportal_write_usedmacs_db($usedmacs) {
	global $g;

	$cpumaclck = lock('captiveusedmacs', LOCK_EX);
	@file_put_contents("{$g['vardb_path']}/captiveportal_usedmacs.db", implode("\n", $usedmacs));
	unlock($cpumaclck);
}

?>
