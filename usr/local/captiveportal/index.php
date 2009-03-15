<?php
/*
    $Id$
    part of m0n0wall (http://m0n0.ch/wall)

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

require_once("auth.inc");
require_once("functions.inc");

header("Expires: 0");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$orig_host = $_ENV['HTTP_HOST'];
$orig_request = $_GET['redirurl'];
$clientip = $_SERVER['REMOTE_ADDR'];

if (!$clientip) {
    /* not good - bail out */
    echo "An error occured.  Please check the system logs for more information.";
    log_error("Captive portal could not deterimine clients ip address.");
    exit;
}

if (isset($config['captiveportal']['httpslogin']))
    $ourhostname = $config['captiveportal']['httpsname'] . ":8001";
else
    $ourhostname = $config['interfaces'][$config['captiveportal']['interface']]['ipaddr'] . ":8000";

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

$macfilter = !isset($config['captiveportal']['nomacfilter']);

/* find MAC address for client */
$clientmac = arp_get_mac_by_ip($clientip);
if (!$clientmac && $macfilter) {
    /* unable to find MAC address - shouldn't happen! - bail out */
    captiveportal_logportalauth("unauthenticated","noclientmac",$clientip,"ERROR");
    echo "An error occured.  Please check the system logs for more information.";
    log_error("Captive portal could not deterimine clients MAC address.  Disable MAC address filtering in captive portal if you do not needs this functionality.");
    exit;
}

/* find out if we need RADIUS + RADIUSMAC or not */
if (file_exists("{$g['vardb_path']}/captiveportal_radius.db")) {
    $radius_enable = TRUE;
    if ($radius_enable && isset($config['captiveportal']['radmac_enable']))
        $radmac_enable = TRUE;
}

if ($_POST['logout_id']) {
    disconnect_client($_POST['logout_id']);
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
/* The $macfilter can be removed safely since we first check if the $clientmac is present, if not we fail */
} else if ($clientmac && portal_mac_fixed($clientmac)) {
    /* punch hole in ipfw for pass thru mac addresses */
    portal_allow($clientip, $clientmac, "unauthenticated");
    exit;

} else if ($clientmac && $radmac_enable && portal_mac_radius($clientmac,$clientip)) {
    /* radius functions handle everything so we exit here since we're done */
    exit;

} else if ($_POST['accept'] && $radius_enable) {

    if ($_POST['auth_user'] && $_POST['auth_pass']) {
        $auth_list = radius($_POST['auth_user'],$_POST['auth_pass'],$clientip,$clientmac,"USER LOGIN");

        if ($auth_list['auth_val'] == 1) {
            captiveportal_logportalauth($_POST['auth_user'],$clientmac,$clientip,"ERROR",$auth_list['error']);
            portal_reply_page($redirurl, "error", $auth_list['error']);
        }
        else if ($auth_list['auth_val'] == 3) {
            captiveportal_logportalauth($_POST['auth_user'],$clientmac,$clientip,"FAILURE",$auth_list['reply_message']);
            portal_reply_page($redirurl, "error", $auth_list['reply_message']);
        }
    } else {
        captiveportal_logportalauth($_POST['auth_user'],$clientmac,$clientip,"ERROR");
        portal_reply_page($redirurl, "error");
    }

} else if ($_POST['accept'] && $config['captiveportal']['auth_method'] == "local") {

	//check against local user manager
	$loginok = local_backed($_POST['auth_user'], $_POST['auth_pass']);
    if ($loginok){
        captiveportal_logportalauth($_POST['auth_user'],$clientmac,$clientip,"LOGIN");
        portal_allow($clientip, $clientmac,$_POST['auth_user']);
    } else {
        captiveportal_logportalauth($_POST['auth_user'],$clientmac,$clientip,"FAILURE");
        portal_reply_page($redirurl, "error");
    }
} else if ($_POST['accept'] && $clientip) {
    captiveportal_logportalauth("unauthenticated",$clientmac,$clientip,"ACCEPT");
    portal_allow($clientip, $clientmac, "unauthenticated");
} else {
    /* display captive portal page */
    portal_reply_page($redirurl, "login",null,$clientmac,$clientip);
}

exit;

function portal_reply_page($redirurl, $type = null, $message = null, $clientmac = null, $clientip = null) {
    global $g, $config;

    /* Get captive portal layout */
    if ($type == "login")
        $htmltext = file_get_contents("{$g['varetc_path']}/captiveportal.html");
    else
        $htmltext = file_get_contents("{$g['varetc_path']}/captiveportal-error.html");

    /* substitute other variables */
    if (isset($config['captiveportal']['httpslogin']))
        $htmltext = str_replace("\$PORTAL_ACTION\$", "https://{$config['captiveportal']['httpsname']}:8001/", $htmltext);
    else
        $htmltext = str_replace("\$PORTAL_ACTION\$", "http://{$config['interfaces'][$config['captiveportal']['interface']]['ipaddr']}:8000/", $htmltext);

    $htmltext = str_replace("\$PORTAL_REDIRURL\$", htmlspecialchars($redirurl), $htmltext);
    $htmltext = str_replace("\$PORTAL_MESSAGE\$", htmlspecialchars($message), $htmltext);
    $htmltext = str_replace("\$CLIENT_MAC\$", htmlspecialchars($clientmac), $htmltext);
    $htmltext = str_replace("\$CLIENT_IP\$", htmlspecialchars($clientip), $htmltext);

    echo $htmltext;
}

function portal_mac_radius($clientmac,$clientip) {
    global $config ;

    $radmac_secret = $config['captiveportal']['radmac_secret'];

    /* authentication against the radius server */
    $username = mac_format($clientmac);
    $auth_list = radius($username,$radmac_secret,$clientip,$clientmac,"MACHINE LOGIN");
    if ($auth_list['auth_val'] == 2) {
        return TRUE;
    }
    return FALSE;
}

function portal_allow($clientip,$clientmac,$username,$password = null, $attributes = null, $ruleno = null)  {

    global $redirurl, $g, $config, $url_redirection, $type;

    /* See if a ruleno is passed, if not start locking the sessions because this means there isn't one atm */
    if ($ruleno == null) {
        captiveportal_lock();
        $ruleno = captiveportal_get_next_ipfw_ruleno();
    }

    /* if the pool is empty, return appropriate message and exit */
    if (is_null($ruleno)) {
        portal_reply_page($redirurl, "error", "System reached maximum login capacity");
        log_error("WARNING!  Captive portal has reached maximum login capacity");
        captiveportal_unlock();
        exit;
    }

    // Ensure we create an array if we are missing attributes
    if (!is_array($attributes))
        $attributes = array();

    /* read in client database */
    $cpdb = captiveportal_read_db();

    $radiusservers = captiveportal_get_radius_servers();

    /* Find an existing session */
    for ($i = 0; $i < count($cpdb); $i++) {
        /* on the same ip */
        if($cpdb[$i][2] == $clientip) {
            captiveportal_logportalauth($cpdb[$i][4],$cpdb[$i][3],$cpdb[$i][2],"CONCURRENT LOGIN - REUSING OLD SESSION");
            $sessionid = $cpdb[$i][5];
            break;
        }
        elseif ((isset($config['captiveportal']['noconcurrentlogins'])) && ($username != 'unauthenticated')) {
            /* on the same username */
            if ($cpdb[$i][4] == $username) {
                /* This user was already logged in so we disconnect the old one */
                captiveportal_disconnect($cpdb[$i],$radiusservers,13);
                captiveportal_logportalauth($cpdb[$i][4],$cpdb[$i][3],$cpdb[$i][2],"CONCURRENT LOGIN - TERMINATING OLD SESSION");
                unset($cpdb[$i]);
                break;
            }
        }
    }

    if (!isset($sessionid)) {

        /* generate unique session ID */
        $tod = gettimeofday();
        $sessionid = substr(md5(mt_rand() . $tod['sec'] . $tod['usec'] . $clientip . $clientmac), 0, 16);

        /* Add rules for traffic shaping
         * We don't need to add extra l3 allow rules since traffic will pass due to the following kernel option
         * net.inet.ip.fw.one_pass: 1
         */
        $peruserbw = isset($config['captiveportal']['peruserbw']);

        $bw_up = isset($attributes['bw_up']) ? trim($attributes['bw_up']) : $config['captiveportal']['bwdefaultup'];
        $bw_down = isset($attributes['bw_down']) ? trim($attributes['bw_down']) : $config['captiveportal']['bwdefaultdn'];

        if ($peruserbw && !empty($bw_up) && is_numeric($bw_up)) {
            $bw_up_pipeno = $ruleno + 40500;
            exec("/sbin/ipfw add $ruleno set 2 pipe $bw_up_pipeno ip from $clientip to any in");
            exec("/sbin/ipfw pipe $bw_up_pipeno config bw {$bw_up}Kbit/s queue 100");
        } else {
            exec("/sbin/ipfw add $ruleno set 2 skipto 50000 ip from $clientip to any in");
        }
        if ($peruserbw && !empty($bw_down) && is_numeric($bw_down)) {
            $bw_down_pipeno = $ruleno + 45500;
            exec("/sbin/ipfw add $ruleno set 2 pipe $bw_down_pipeno ip from any to $clientip out");
            exec("/sbin/ipfw pipe $bw_down_pipeno config bw {$bw_down}Kbit/s queue 100");
        } else {
            exec("/sbin/ipfw add $ruleno set 2 skipto 50000 ip from any to $clientip out");
        }

        /* add ipfw rules for layer 2 */
        if (!isset($config['captiveportal']['nomacfilter'])) {
            $l2ruleno = $ruleno + 10000;
            exec("/sbin/ipfw add $l2ruleno set 3 deny all from $clientip to any not MAC any $clientmac layer2 in");
            exec("/sbin/ipfw add $l2ruleno set 3 deny all from any to $clientip not MAC $clientmac any layer2 out");
        }

        /* encode password in Base64 just in case it contains commas */
        $bpassword = base64_encode($password);
        $cpdb[] = array(time(), $ruleno, $clientip, $clientmac, $username, $sessionid, $bpassword,
                $attributes['session_timeout'],
                $attributes['idle_timeout'],
                $attributes['session_terminate_time']);

        if (isset($config['captiveportal']['radacct_enable']) && isset($radiusservers[0])) {
            $acct_val = RADIUS_ACCOUNTING_START($ruleno,
                                                            $username,
                                                            $sessionid,
                                                            $radiusservers[0]['ipaddr'],
                                                            $radiusservers[0]['acctport'],
                                                            $radiusservers[0]['key'],
                                                            $clientip,
                                                            $clientmac);

            if ($acct_val == 1)
                captiveportal_logportalauth($username,$clientmac,$clientip,$type,"RADIUS ACCOUNTING FAILED");
        }


    }

    /* rewrite information to database */
    captiveportal_write_db($cpdb);

    /* redirect user to desired destination */
    if ($url_redirection)
        $my_redirurl = $url_redirection;
    else if ($config['captiveportal']['redirurl'])
        $my_redirurl = $config['captiveportal']['redirurl'];
    else
        $my_redirurl = $redirurl;

    if(isset($config['captiveportal']['logoutwin_enable'])) {

        if (isset($config['captiveportal']['httpslogin']))
            $logouturl = "https://{$config['captiveportal']['httpsname']}:8001/";
        else
            $logouturl = "http://{$config['interfaces'][$config['captiveportal']['interface']]['ipaddr']}:8000/";

        echo <<<EOD
<HTML>
<HEAD><TITLE>Redirecting...</TITLE></HEAD>
<BODY>
<SPAN STYLE="font-family: Tahoma, Verdana, Arial, Helvetica, sans-serif; font-size: 11px;">
<B>Redirecting to <A HREF="{$my_redirurl}">{$my_redirurl}</A>...</B>
</SPAN>
<SCRIPT LANGUAGE="JavaScript">
<!--
LogoutWin = window.open('', 'Logout', 'toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=256,height=64');
if (LogoutWin) {
    LogoutWin.document.write('<HTML>');
    LogoutWin.document.write('<HEAD><TITLE>Logout</TITLE></HEAD>') ;
    LogoutWin.document.write('<BODY BGCOLOR="#435370">');
    LogoutWin.document.write('<DIV ALIGN="center" STYLE="color: #ffffff; font-family: Tahoma, Verdana, Arial, Helvetica, sans-serif; font-size: 11px;">') ;
    LogoutWin.document.write('<B>Click the button below to disconnect</B><P>');
    LogoutWin.document.write('<FORM METHOD="POST" ACTION="{$logouturl}">');
    LogoutWin.document.write('<INPUT NAME="logout_id" TYPE="hidden" VALUE="{$sessionid}">');
    LogoutWin.document.write('<INPUT NAME="logout" TYPE="submit" VALUE="Logout">');
    LogoutWin.document.write('</FORM>');
    LogoutWin.document.write('</DIV></BODY>');
    LogoutWin.document.write('</HTML>');
    LogoutWin.document.close();
}

document.location.href="{$my_redirurl}";
-->
</SCRIPT>
</BODY>
</HTML>

EOD;
    } else {
		captiveportal_unlock();	
        header("Location: " . $my_redirurl);
		return $sessionid;
    }

    captiveportal_unlock();
    return $sessionid;
}



/* remove a single client by session ID
   by Dinesh Nair
 */
function disconnect_client($sessionid, $logoutReason = "LOGOUT", $term_cause = 1) {

    global $g, $config;

    captiveportal_lock();
    /* read database */
    $cpdb = captiveportal_read_db();

    $radiusservers = captiveportal_get_radius_servers();

    /* find entry */
    for ($i = 0; $i < count($cpdb); $i++) {
        if ($cpdb[$i][5] == $sessionid) {
            captiveportal_disconnect($cpdb[$i],$radiusservers, $term_cause);
            captiveportal_logportalauth($cpdb[$i][4],$cpdb[$i][3],$cpdb[$i][2],$logoutReason);
            unset($cpdb[$i]);
            break;
        }
    }

    /* write database */
    captiveportal_write_db($cpdb);

    captiveportal_unlock();
}

?>
