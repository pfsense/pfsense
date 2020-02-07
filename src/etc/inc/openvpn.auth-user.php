<?php
/*
 * openvpn.auth-user.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008 Shrew Soft Inc
 * Copyright (c) 2008-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
 * OpenVPN calls this script to authenticate a user
 * based on a username and password. We lookup these
 * in our config.xml file and check the credentials.
 */

require_once("globals.inc");
require_once("config.inc");
require_once("auth.inc");
require_once("interfaces.inc");

/* setup syslog logging */
openlog("openvpn", LOG_ODELAY, LOG_AUTH);

global $common_name, $username;

if (isset($_GET['username'])) {
	$authmodes = explode(",", base64_decode($_GET['authcfg']));
	/* Any string retrieved through $_GET is automatically urlDecoded */
	$username = base64_decode($_GET['username']);
	$password = base64_decode($_GET['password']);
	$common_name = $_GET['cn'];
	$modeid = $_GET['modeid'];
	$strictusercn = $_GET['strictcn'] == "false" ? false : true;
} else {
	/* read data from environment */
	$username = getenv("username");
	$password = getenv("password");
	$common_name = getenv("common_name");
}

if (!$username) {
	syslog(LOG_ERR, "invalid user authentication environment");
	if (isset($_GET['username'])) {
		echo "FAILED";
		closelog();
		return;
	} else {
		closelog();
		return (-1);
	}
}

/* Replaced by a sed with proper variables used below(ldap parameters). */
//<template>

$authenticated = false;

if (($strictusercn === true) && (mb_strtolower($common_name) !== mb_strtolower($username))) {
	syslog(LOG_WARNING, "Username does not match certificate common name (\"{$username}\" != \"{$common_name}\"), access denied.");
	if (isset($_GET['username'])) {
		echo "FAILED";
		closelog();
		return;
	} else {
		closelog();
		return (1);
	}
}

if (!is_array($authmodes)) {
	syslog(LOG_WARNING, "No authentication server has been selected to authenticate against. Denying authentication for user {$username}");
	if (isset($_GET['username'])) {
		echo "FAILED";
		closelog();
		return;
	} else {
		closelog();
		return (1);
	}
}


$attributes = array("nas_identifier" => "openVPN",
	"nas_port_type" => RADIUS_VIRTUAL,
	"nas_port" => $_GET['nas_port'],
	"calling_station_id" => get_interface_ip() . ":" . $_GET['nas_port']);
	
foreach ($authmodes as $authmode) {
	$authcfg = auth_get_authserver($authmode);
	if (!$authcfg && $authmode != "Local Database") {
		continue;
	}

	$authenticated = authenticate_user($username, $password, $authcfg, $attributes);
	if ($authenticated == true) {
		break;
	}
}

if ($authenticated == false) {
	syslog(LOG_WARNING, "user '{$username}' could not authenticate.");
	if (isset($_GET['username'])) {
		echo "FAILED";
		closelog();
		return;
	} else {
		closelog();
		return (-1);
	}
}

if (file_exists("/etc/inc/openvpn.attributes.php")) {
	include_once("/etc/inc/openvpn.attributes.php");
}

$content = "";
if (is_array($attributes['dns-servers'])) {
	foreach ($attributes['dns-servers'] as $dnssrv) {
		if (is_ipaddr($dnssrv)) {
			$content .= "push \"dhcp-option DNS {$dnssrv}\"\n";
		}
	}
}
if (is_array($attributes['routes'])) {
	foreach ($attributes['routes'] as $route) {
		$content .= "push \"route {$route} vpn_gateway\"\n";
	}
}

if (isset($attributes['framed_ip'])) {
	if (isset($attributes['framed_mask'])) {
		$content .= "topology subnet\n";
		$content .= "ifconfig-push {$attributes['framed_ip']} {$attributes['framed_mask']}";
	} else {
		$content .= "topology net30\n";
		$content .= "ifconfig-push {$attributes['framed_ip']} ". long2ip((ip2long($attributes['framed_ip']) - 1));
	}
}

if (!empty($content)) {
	@file_put_contents("{$g['tmp_path']}/{$username}", $content);
}

syslog(LOG_NOTICE, "user '{$username}' authenticated");
closelog();

if (isset($_GET['username'])) {
	echo "OK";
} else {
	return (0);
}

?>
