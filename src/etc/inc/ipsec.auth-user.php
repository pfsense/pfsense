#!/usr/local/bin/php-cgi -f
<?php
/*
 * ipsec.auth-user.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008 Shrew Soft Inc
 * Copyright (c) 2008-2013 BSD Perimeter
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

/*
 * ipsec calls this script to authenticate a user
 * based on a username and password. We lookup these
 * in our config.xml file and check the credentials.
 */

require_once("globals.inc");
require_once("config.inc");
require_once("auth.inc");
require_once("interfaces.inc");

if (isset($_GET['username'])) {
	$authmodes = array_filter(explode(",", $_GET['authcfg']));
	$username = $_GET['username'];
	$password = $_GET['password'];
	$common_name = $_GET['cn'];
} else {
	/* read data from environment */
	$username = getenv("username");
	$password = getenv("password");
	$common_name = getenv("common_name");
	$authmodes = array_filter(explode(",", getenv("authcfg")));
}

if (!$username) {
	logger(LOG_ERR, localize_text("invalid user authentication environment"), LOG_PREFIX_IPSEC, LOG_AUTH);
	if (isset($_GET['username'])) {
		echo "FAILED";
		return;
	} else {
		exit (-1);
	}
}

$authenticated = false;

if (($strictusercn === true) && ($common_name != $username)) {
	logger(LOG_WARNING, localize_text("Username does not match certificate common name (%s != %s), access denied.", $username, $common_name), LOG_PREFIX_IPSEC, LOG_AUTH);
	if (isset($_GET['username'])) {
		echo "FAILED";
		return;
	} else {
		exit (1);
	}
}

$attributes = array("nas_identifier" => "xauthIPsec");
if ((config_get_path('ipsec/client/group_source') == 'enabled') &&
    !empty(config_get_path('ipsec/client/auth_groups'))) {
	$ipsec_groups = explode(",", config_get_path('ipsec/client/auth_groups', ''));
} else { 
	$ipsec_groups = '';
}
foreach ($authmodes as $authmode) {
	$authcfg = auth_get_authserver($authmode);
	if (!$authcfg && $authmode != "Local Database") {
		continue;
	}

	$authenticated = authenticate_user($username, $password, $authcfg, $attributes);
	if ($authenticated == true) {
		$userGroups = getUserGroups($username, $authcfg, $attributes);
		if ($authmode == "Local Database") {
			$user = getUserEntry($username);
			$user = $user['item'];
			if (!is_array($user) || !userHasPrivilege($user, "user-ipsec-xauth-dialin") ||
			    (!empty($ipsec_groups) && (count(array_intersect($userGroups, $ipsec_groups)) == 0))) {
				$authenticated = false;
				logger(LOG_WARNING, localize_text("user '%s' cannot authenticate through IPsec since the required privileges are missing.", $username), LOG_PREFIX_IPSEC, LOG_AUTH);
				continue;
			}
		}
		break;
	}
}

if ($authenticated == false) {
	logger(LOG_WARNING, localize_text("user '%s' could not authenticate.", $username), LOG_PREFIX_IPSEC, LOG_AUTH);
	if (isset($_GET['username'])) {
		echo "FAILED";
		return;
	} else {
		exit (-1);
	}
}

if (file_exists("/etc/inc/ipsec.attributes.php")) {
	include_once("/etc/inc/ipsec.attributes.php");
}

logger(LOG_NOTICE, localize_text("user '%s' authenticated", $username), LOG_PREFIX_IPSEC, LOG_AUTH);

if (isset($_GET['username'])) {
	echo "OK";
} else {
	exit (0);
}

?>
