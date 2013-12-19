#!/usr/local/bin/php -f
<?php
/* $Id$ */
/*
	openvpn.tls-verify.php

	Copyright (C) 2011 Jim Pingle
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
 * OpenVPN calls this script to validate a certificate
 *  This script is called ONCE per DEPTH of the certificate chain
 *  Normal operation would have two runs - one for the server certificate
 *  and one for the client certificate. Beyond that, you're dealing with
 *  intermediates.
 */

require_once("globals.inc");
require_once("config.inc");
require_once("interfaces.inc");

openlog("openvpn", LOG_ODELAY, LOG_AUTH);

/* read data from command line */
if (isset($_GET)) {
	$cert_depth = $_GET['certdepth'];
	$cert_subject = urldecode($_GET['certsubject']);
	$allowed_depth = $_GET['depth'];
	$server_cn = $_GET['servercn'];
} else {
	$cert_depth = intval($argv[1]);
	$cert_subject = $argv[2];
}

/* Reserved for future use in case we decide to verify CNs and such as well
$subj = explode("/", $cert_subject);
foreach ($subj at $s) {
	list($n, $v) = explode("=", $s);
	if ($n == "CN")
		$common_name = $v;
}
*/

/* Replaced by sed with proper variables used below ( $server_cn and $allowed_depth ). */
//<template>

if (isset($allowed_depth) && ($cert_depth > $allowed_depth)) {
	syslog(LOG_WARNING, "Certificate depth {$cert_depth} exceeded max allowed depth of {$allowed_depth}.\n");
	if (isset($_GET)) {
		echo "FAILED";
		closelog();
		return;
	} else {
		closelog();
		exit(1);
	}
}

// Debug
//syslog(LOG_WARNING, "Found certificate {$argv[2]} with depth {$cert_depth}\n");

closelog();
if (isset($_GET))
	echo "OK";
else
	exit(0);

?>
