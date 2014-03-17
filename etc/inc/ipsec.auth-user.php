#!/usr/local/bin/php -f
<?php
/*
    ipsec.auth-user.php

    Copyright (C) 2008 Shrew Soft Inc
    Copyright (C) 2010 Ermal Luçi
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

	DISABLE_PHP_LINT_CHECKING
*/
/*
	pfSense_BUILDER_BINARIES:	
	pfSense_MODULE:	openvpn
*/
/*
 * racoon calls this script to authenticate a user
 * based on a username and password. We lookup these
 * in our config.xml file and check the credentials.
 */

require_once("globals.inc");
require_once("config.inc");
require_once("radius.inc");
require_once("auth.inc");
require_once("interfaces.inc");

/**
 * Get the NAS-Identifier
 *
 * We will use our local hostname to make up the nas_id
 */
if (!function_exists("getNasID")) {
function getNasID()
{
    global $g;

    $nasId = gethostname();
    if(empty($nasId))
        $nasId = $g['product_name'];
    return $nasId;
}
}

/**
 * Get the NAS-IP-Address based on the current wan address
 *
 * Use functions in interfaces.inc to find this out
 *
 */
if (!function_exists("getNasIP")) {
function getNasIP()
{
    $nasIp = get_interface_ip();
    if(!$nasIp)
        $nasIp = "0.0.0.0";
    return $nasIp;
}
}
/* setup syslog logging */
openlog("racoon", LOG_ODELAY, LOG_AUTH);

if (isset($_GET)) {
	$authmodes = explode(",", $_GET['authcfg']);
	$username = $_GET['username'];
	$password = $_GET['password'];
	$common_name = $_GET['cn'];
} else {
	/* read data from environment */
	$username = getenv("username");
	$password = getenv("password");
	$common_name = getenv("common_name");
	$authmodes = explode(",", getenv("authcfg"));
}

if (!$username || !$password) {
	syslog(LOG_ERR, "invalid user authentication environment");
	if (isset($_GET)) {
		echo "FAILED";
		closelog();
		return;
	} else {
		closelog();
		exit(-1);
	}
}

$authenticated = false;

if (($strictusercn === true) && ($common_name != $username)) {
	syslog(LOG_WARNING, "Username does not match certificate common name ({$username} != {$common_name}), access denied.\n");
	if (isset($_GET)) {
		echo "FAILED";
		closelog();
		return;
	} else {
		closelog();
		exit(1);
	}
}

$attributes = array();
foreach ($authmodes as $authmode) {
	$authcfg = auth_get_authserver($authmode);
	if (!$authcfg && $authmode != "local")
		continue;

	$authenticated = authenticate_user($username, $password, $authcfg, $attributes);
	if ($authenticated == true) {
		if (stristr($authmode, "local")) {
			$user = getUserEntry($username); 
			if (!is_array($user) || !userHasPrivilege($user, "user-ipsec-xauth-dialin")) {
				$authenticated = false;
				syslog(LOG_WARNING, "user '{$username}' cannot authenticate through IPSec since the required privileges are missing.\n");
				continue;
			}
		}
		break;
	}
}

if ($authenticated == false) {
	syslog(LOG_WARNING, "user '{$username}' could not authenticate.\n");
	if (isset($_GET)) {
		echo "FAILED";
		closelog();
		return;
	} else {
		closelog();
		exit(-1);
	}
}

if (file_exists("/etc/inc/ipsec.attributes.php"))
        include_once("/etc/inc/ipsec.attributes.php");
        
syslog(LOG_NOTICE, "user '{$username}' authenticated\n");
closelog();

if (isset($_GET))
	echo "OK";
else
	exit(0);

?>
