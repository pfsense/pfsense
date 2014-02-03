#!/usr/local/bin/php -f
<?php
/* $Id$ */
/*
    openvpn.auth-user.php

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
openlog("openvpn", LOG_ODELAY, LOG_AUTH);

/* read data from environment */
$username = getenv("username");
$password = getenv("password");
$common_name = getenv("common_name");

if (!$username || !$password) {
	syslog(LOG_ERR, "invalid user authentication environment");
	exit(-1);
}

/* Replaced by a sed with propper variables used below(ldap parameters). */
//<template>

if (file_exists("{$g['varetc_path']}/openvpn/{$modeid}.ca")) {
	putenv("LDAPTLS_CACERT={$g['varetc_path']}/openvpn/{$modeid}.ca");
	putenv("LDAPTLS_REQCERT=never");
}

$authenticated = false;

if (($strictusercn === true) && ($common_name != $username)) {
	syslog(LOG_WARNING, "Username does not match certificate common name ({$username} != {$common_name}), access denied.\n");
	exit(1);
}

$attributes = array();
foreach ($authmodes as $authmode) {
	$authcfg = auth_get_authserver($authmode);
	if (!$authcfg && $authmode != "local")
		continue;

	$authenticated = authenticate_user($username, $password, $authcfg, $attributes);
	if ($authenticated == true)
		break;
}

if ($authenticated == false) {
	syslog(LOG_WARNING, "user '{$username}' could not authenticate.\n");
	exit(-1);
}

if (file_exists("/etc/inc/openvpn.attributes.php"))
        include_once("/etc/inc/openvpn.attributes.php");
        
$content = "";
if (is_array($attributes['dns-servers'])) {
        foreach ($attributes['dns-servers'] as $dnssrv) {
                if (is_ipaddr($dnssrv))
                        $content .= "push \"dhcp-option DNS {$dnssrv}\"\n";
        }
}
if (is_array($attributes['routes'])) {
        foreach ($attributes['routes'] as $route)
                        $content .= "push \"route {$route} vpn_gateway\"\n";
}

if (isset($attributes['framed_ip'])) {
/* XXX: only use when TAP windows driver >= 8.2.x */
/*      if (isset($attributes['framed_mask'])) {
                $content .= "topology subnet\n";
                $content .= "ifconfig-push {$attributes['framed_ip']} {$attributes['framed_mask']}";
        } else {
*/
                $content .= "topology net30\n";
                $content .= "ifconfig-push {$attributes['framed_ip']} ". long2ip((ip2long($attributes['framed_ip']) + 1));
//      }
}
    
if (!empty($content))
        @file_put_contents("{$g['tmp_path']}/{$username}", $content);

syslog(LOG_NOTICE, "user '{$username}' authenticated\n");

exit(0);

?>
