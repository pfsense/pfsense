#!/usr/local/bin/php-cgi -f
<?php
/*
 * openvpn.tls-verify.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2013 BSD Perimeter
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
if (isset($_GET['certdepth'])) {
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
	if ($n == "CN") {
		$common_name = $v;
	}
}
*/

/* Replaced by sed with proper variables used below ( $server_cn and $allowed_depth ). */
//<template>

if (isset($allowed_depth) && ($cert_depth > $allowed_depth)) {
	syslog(LOG_WARNING, "Certificate depth {$cert_depth} exceeded max allowed depth of {$allowed_depth}.");
	if (isset($_GET['certdepth'])) {
		echo "FAILED";
		closelog();
		return;
	} else {
		closelog();
		exit(1);
	}
}

// Debug
//syslog(LOG_WARNING, "Found certificate {$argv[2]} with depth {$cert_depth}");

closelog();
if (isset($_GET['certdepth'])) {
	echo "OK";
} else {
	exit(0);
}

?>
