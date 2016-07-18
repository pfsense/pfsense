#!/usr/local/bin/php-cgi -f
<?php
/*
 * openvpn.auth-user.php
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
 * OpenVPN calls this script to authenticate a user
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
 * We will return "openVPN" so that connections can be distinguished by the Radius
 */
if (!function_exists("getNasID")) {
function getNasID() {
	return "openVPN";
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

/**
 * Set the NAS-Port-Type
 *
 * Should be "Virtual" since that denotes VPN connections
 */
if (!function_exists("getNasPortType")) {
function getNasPortType() {
	return RADIUS_VIRTUAL;
}
}

/**
 * Set the NAS-Port
 *
 * We will return the port the client connected to
 */
if (!function_exists("getNasPort")) {
function getNasPort() {
	return $_GET['nas_port'];
}
}

/**
 * Set the Called-Station-ID
 *
 * We will return the IP and port the client connected to
 */
if (!function_exists("getCalledStationId")) {
function getCalledStationId() {
	return get_interface_ip() . ":" . getNasPort();
}
}

/* setup syslog logging */
openlog("openvpn", LOG_ODELAY, LOG_AUTH);

if (isset($_GET['username'])) {
	$authmodes = explode(",", $_GET['authcfg']);
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

if (!$username || !$password) {
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

if (file_exists("{$g['varetc_path']}/openvpn/{$modeid}.ca")) {
	putenv("LDAPTLS_CACERT={$g['varetc_path']}/openvpn/{$modeid}.ca");
	putenv("LDAPTLS_REQCERT=never");
}

$authenticated = false;

if (($strictusercn === true) && (mb_strtolower($common_name) !== mb_strtolower($username))) {
	syslog(LOG_WARNING, "Username does not match certificate common name ({$username} != {$common_name}), access denied.\n");
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

$attributes = array();
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
	syslog(LOG_WARNING, "user '{$username}' could not authenticate.\n");
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

syslog(LOG_NOTICE, "user '{$username}' authenticated\n");
closelog();

if (isset($_GET['username'])) {
	echo "OK";
} else {
	return (0);
}

?>
