#!/usr/local/bin/php -f
<?php
/* $Id$ */
/*
    openvpn.auth-radius.php

    Copyright (C) 2010 Ermal  Luçi
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
	pfSense_BUILDER_BINARIES:	
	pfSense_MODULE:	openvpn
*/

require_once("config.inc");
require_once("system.inc");
require_once("radius.inc");

/* setup syslog logging */
openlog("openvpn", LOG_ODELAY, LOG_AUTH);

/* read data from environment */
$username = getenv("username");
$password = getenv("password");

if (empty($username) || empty($password)) {
	syslog(LOG_ERR, "invalid user authentication environment");
	exit(-1);
}

/* Replaced by a sed with propper variables used below(server parameters). */
//<template>

$authcfg = system_get_authserver($authmode);
$radsrv="{$authcfg['host']}";
$radport="{$authcfg['radius_auth_port']}";
$radsecret="{$authcfg['radius_secret']}";

$rauth = new Auth_RADIUS_PAP($username, $password);
/* Add server to our instance */
$rauth->addServer($radsrv, $radport, $radsecret);

if (!$rauth->start()) {
	syslog(LOG_ERROR, "ERROR! " . $rauth->getError());
	exit(-2);
}

/* Send request */
$result = $rauth->send();
if (PEAR::isError($result)) {
	syslog(LOG_WARNING, "Something went wrong trying to authenticate {$username}: " . $result->getMessage() . " \n");
	exit(-1);
} else if ($result === true) {
	syslog(LOG_WARNING, "user {$username} authenticated\n");
} else {
	syslog(LOG_WARNING, "user {$username} could not authenticate. \n");
	exit(-3);
}

// close OO RADIUS_AUTHENTICATION
$rauth->close();

exit(0);

?>
