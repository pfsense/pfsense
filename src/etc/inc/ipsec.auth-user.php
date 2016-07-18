#!/usr/local/bin/php-cgi -f
<?php
/*
 * ipsec.auth-user.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008 Shrew Soft Inc
 * Copyright (c) 2008-2016 Electric Sheep Fencing, LLC
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

/*
 * ipsec calls this script to authenticate a user
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
function getNasID() {
	global $g;

	$nasId = gethostname();
	if (empty($nasId)) {
		$nasId = $g['product_name'];
	}
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
function getNasIP() {
	$nasIp = get_interface_ip();
	if (!$nasIp) {
		$nasIp = "0.0.0.0";
	}
	return $nasIp;
}
}
/* setup syslog logging */
openlog("charon", LOG_ODELAY, LOG_AUTH);

if (isset($_GET['username'])) {
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
	if (isset($_GET['username'])) {
		echo "FAILED";
		closelog();
		return;
	} else {
		closelog();
		exit (-1);
	}
}

$authenticated = false;

if (($strictusercn === true) && ($common_name != $username)) {
	syslog(LOG_WARNING, "Username does not match certificate common name ({$username} != {$common_name}), access denied.\n");
	if (isset($_GET['username'])) {
		echo "FAILED";
		closelog();
		return;
	} else {
		closelog();
		exit (1);
	}
}

$attributes = array();
foreach ($authmodes as $authmode) {
	$authcfg = auth_get_authserver($authmode);
	if (!$authcfg && $authmode != "Local Database") {
		continue;
	}

	$authenticated = authenticate_user($username, $password, $authcfg, $attributes);
	if ($authenticated == true) {
		if ($authmode == "Local Database") {
			$user = getUserEntry($username);
			if (!is_array($user) || !userHasPrivilege($user, "user-ipsec-xauth-dialin")) {
				$authenticated = false;
				syslog(LOG_WARNING, "user '{$username}' cannot authenticate through IPsec since the required privileges are missing.\n");
				continue;
			}
		}
		break;
	}
}

if ($authenticated == false) {
	syslog(LOG_WARNING, "user '{$username}' could not authenticate.\n");
	if (isset($_GET['username'])) {
		echo "FAILED";
		closelog();
		return;
	} else {
		closelog();
		exit (-1);
	}
}

if (file_exists("/etc/inc/ipsec.attributes.php")) {
	include_once("/etc/inc/ipsec.attributes.php");
}

syslog(LOG_NOTICE, "user '{$username}' authenticated\n");
closelog();

if (isset($_GET['username'])) {
	echo "OK";
} else {
	exit (0);
}

?>
