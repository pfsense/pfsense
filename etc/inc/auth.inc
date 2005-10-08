<?php
/* $Id$ */
/*
        Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>
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

require_once("config.inc");
require_once("globals.inc");

/* We only support file backed HTTP Basic auth right now */
$auth_method="htpasswd_backed_basic_auth";

/* Authenticate user - exit if failed (we should have a callback for this maybe) */
if (!$auth_method())
	exit;

function basic_auth_prompt(){
	header("WWW-Authenticate: Basic realm=\".\"");
	header("HTTP/1.0 401 Unauthorized");
	echo "You must enter valid credentials to access this resource.";
	exit;
}

function passwd_backed_basic_auth() {
	global $HTTP_SERVER_VARS;

	$authfile = file("/etc/master.passwd");

	/* Prompt three times and give up */
	for($attempt = 0; $attempt <= 3; basic_auth_prompt()){
		$attempt++;
		/* Check for AUTH_USER */
		if ($HTTP_SERVER_VARS['PHP_AUTH_USER'] <> "") {
			$HTTP_SERVER_VARS['AUTH_USER'] = $HTTP_SERVER_VARS['PHP_AUTH_USER'];
			$HTTP_SERVER_VARS['AUTH_PW'] = $HTTP_SERVER_VARS['PHP_AUTH_PW'];
		}
		if (!isset($HTTP_SERVER_VARS['AUTH_USER']))
			continue;

		/* Check to see if user even exists */
		$username = $HTTP_SERVER_VARS['AUTH_USER'];
		
		/*
		 *    auth the username
                 *    honour custom username set in webConfigurator page */
		 */ 
		if($config['system']['username'] == $HTTP_SERVER_VARS['AUTH_USER']) {
			/* we match, don't continue to next for loop */
		} else {
			/*   finally, if we're here then we didn't match the username in
			 *   the webConfigurator.  Check the password file.
                         */
	                if(!($line = array_shift(preg_grep("/^$username:.*$/", $authfile))))
				continue;
		}

		/* Get crypted password */
                preg_match("/^$username:((\\$1\\$[.\d\w_\/]{8}\\$)[.\d\w_\/]{22})$/", $line, $matches);
		$pass = $matches[1];
		$salt = $matches[2];

		/* Encrypt entered password with salt */
		$authpass = crypt($HTTP_SERVER_VARS['AUTH_PW'], $salt);

		/* And finally validate password */
		if($authpass == $pass)
			return true;
		else
			continue;
	}

	/* Should only get here if user fails login three times */
	return false;
}

function htpasswd_backed_basic_auth() {
        global $HTTP_SERVER_VARS;

        $authfile = file("/var/run/htpasswd");

	/* sanity check to ensure that /usr/local/www/.htpasswd doesn't exist */
	unlink_if_exists("/usr/local/www/.htpasswd");

        /* Prompt three times and give up */
        for($attempt = 0; $attempt <= 3; basic_auth_prompt()){
                $attempt++;
                /* Check for AUTH_USER */
		if ($HTTP_SERVER_VARS['PHP_AUTH_USER'] <> "") {
			$HTTP_SERVER_VARS['AUTH_USER'] = $HTTP_SERVER_VARS['PHP_AUTH_USER'];
			$HTTP_SERVER_VARS['AUTH_PW'] = $HTTP_SERVER_VARS['PHP_AUTH_PW'];
		}
                if (!isset($HTTP_SERVER_VARS['AUTH_USER']))
                        continue;

                /* Check to see if user even exists */
                $username = $HTTP_SERVER_VARS['AUTH_USER'];
                if(!($line = array_shift(preg_grep("/^$username:.*$/", $authfile))))
                        continue;

                /* Get crypted password */
                preg_match("/^$username:((\\$1\\$[.\d\w_\/]{8}\\$)[.\d\w_\/]{22})$/", $line, $matches);
                $pass = $matches[1];
                $salt = $matches[2];

                /* Encrypt entered password with salt */
                $authpass = crypt($HTTP_SERVER_VARS['AUTH_PW'], $salt);

                /* And finally validate password */
                if($authpass == $pass)
                        return true;
                else
                        continue;
        }

        /* Should only get here if user fails login three times */
        return false;
}

?>
