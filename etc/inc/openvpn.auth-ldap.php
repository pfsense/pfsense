#!/usr/local/bin/php -f
<?php
/* $Id$ */
/*
    openvpn.auth-ldap.php

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
*/
/*
	pfSense_BUILDER_BINARIES:	
	pfSense_MODULE:	openvpn
*/

/* setup syslog logging */
openlog("openvpn", LOG_ODELAY, LOG_AUTH);

/* read data from environment */
$username = getenv("username");
$password = getenv("password");

if (empty($username) || empty($password)) {
	syslog(LOG_ERR, "invalid user authentication environment");
	exit(-1);
}

/* Replaced by a sed with propper variables used below(ldap parameters). */
//<template>

$usernamedn = $username;
if (!strstr($username, "@") && !strstr($username, "\\"))
	$usernamedn .= $ldapbasedn;

/* Make sure we can connect to LDAP */
putenv('LDAPTLS_REQCERT=never');
if (!($ldap = @ldap_connect($ldaphost, $ldapport))) {
	syslog(LOG_ERROR, "ERROR! Could not connect to server {$ldaphost}.");
	exit(-2);
}

ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, (int)$ldapver);

/* ok, its up.  now, lets bind as the bind user so we can search it */
if (!($res = @ldap_bind($ldap, $username, $password)) || !($res = @ldap_bind($ldap, $usernamedn, $password))) {
	syslog(LOG_WARNING, "user {$username} could not authenticate\n");
	ldap_close($ldap);
	exit(-3);
}

syslog(LOG_WARNING, "user {$username} authenticated\n");
ldap_unbind($ldap);

exit(0);

?>
