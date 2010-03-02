#!/usr/local/bin/php -f
<?php
/* $Id$ */
/*
    openvpn.auth-user.php

    Copyright (C) 2008 Shrew Soft Inc
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

require_once("config.inc");
require_once("auth.inc");

/* setup syslog logging */
openlog("openvpn", LOG_ODELAY, LOG_AUTH);

/* read data from environment */
$username = getenv("username");
$password = getenv("password");

if (!$username || !$password) {
	syslog(LOG_ERR, "invalid user authentication environment");
	exit(-1);
}

/* lookup user object by name */
if (!local_backed($username, $password)) {
	syslog(LOG_WARNING, "user {$username} supplied an invalid password\n");
	exit(-2);
}

syslog(LOG_WARNING, "user {$username} authenticated\n");

exit(0);

?>
