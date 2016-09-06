<?php
/*
 * prefixes.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *
 * 3. All advertising materials mentioning features or use of this software
 *    must display the following acknowledgment:
 *    "This product includes software developed by the pfSense Project
 *    for use in the pfSense® software distribution. (http://www.pfsense.org/).
 *
 * 4. The names "pfSense" and "pfSense Project" must not be used to
 *    endorse or promote products derived from this software without
 *    prior written permission. For written permission, please contact
 *    coreteam@pfsense.org.
 *
 * 5. Products derived from this software may not be called "pfSense"
 *    nor may "pfSense" appear in their names without prior written
 *    permission of the Electric Sheep Fencing, LLC.
 *
 * 6. Redistributions of any form whatsoever must retain the following
 *    acknowledgment:
 *
 * "This product includes software developed by the pfSense Project
 * for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 * THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 * EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 * ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 * STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 * OF THE POSSIBILITY OF SUCH DAMAGE.
 */

$leases_file = "/var/dhcpd/var/db/dhcpd6.leases";
if (!file_exists($leases_file)) {
	exit(1);
}

$fd = fopen($leases_file, 'r');

$duid_arr = array();
while (( $line = fgets($fd, 4096)) !== false) {
	// echo "$line";
	
	/* Originally: preg_match("/^(ia-[np][ad])[ ]+\"(.*?)\"/i", $line, $duidmatch)
	   That is: \"(.*?)\"
	   , which is a non-greedy matching. However that does not go well with the legal
	   substring \" in the IAID+DUID lease string format of ISC DHCPDv6,
	   because it truncates before we reach the end of the IAID+DUID string!
	   Instead we use: \"(.*)\"
	   (Might fail if content of the lease file is not well formed.)

	   Maybe someone would argue to e.g. use \"(.*?)\"[ \t]*{[ \t]*$
	   instead
	   (Either we get a valid result or nothing at all.)
	   , but I'll leave it to others to decide! */
	if (preg_match("/^(ia-[np][ad])[ ]+\"(.*)\"/i", $line, $duidmatch)) {
		$type = $duidmatch[1];
		$duid = extract_duid($duidmatch[2]);
		continue;
	}

	/* is it active? otherwise just discard */
	if (preg_match("/binding state active/i", $line, $activematch)) {
		$active = true;
		continue;
	}

	if (preg_match("/iaaddr[ ]+([0-9a-f:]+)[ ]+/i", $line, $addressmatch)) {
		$ia_na = $addressmatch[1];
		continue;
	}

	if (preg_match("/iaprefix[ ]+([0-9a-f:\/]+)[ ]+/i", $line, $prefixmatch)) {
		$ia_pd = $prefixmatch[1];
		continue;
	}

	/* closing bracket */
	if (preg_match("/^}/i", $line)) {
		if (isset($duid) && $duid !== false && $active === true) {
			switch ($type) {
				case "ia-na":
					$duid_arr[$duid][$type] = $ia_na;
					break;
				case "ia-pd":
					$duid_arr[$duid][$type] = $ia_pd;
					break;
				default:
					break;
			}
		}
		unset($type);
		unset($duid);
		unset($active);
		unset($ia_na);
		unset($ia_pd);
		continue;
	}
}
fclose($fd);

$routes = array();
foreach ($duid_arr as $entry) {
	if (!empty($entry['ia-pd'])) {
		$routes[$entry['ia-na']] = $entry['ia-pd'];
	}
}

// echo "add routes\n";
if (count($routes) > 0) {
	foreach ($routes as $address => $prefix) {
		echo "/sbin/route change -inet6 {$prefix} {$address}\n";
	}
}

/* get clog from dhcpd */
$dhcpdlogfile = "/var/log/dhcpd.log";
$expires = array();
if (file_exists($dhcpdlogfile)) {
	$fd = popen("clog $dhcpdlogfile", 'r');
	while (($line = fgets($fd)) !== false) {
		//echo $line;
		if (preg_match("/releases[ ]+prefix[ ]+([0-9a-f:]+\/[0-9]+)/i", $line, $expire)) {
			if (in_array($expire[1], $routes)) {
				continue;
			}
			$expires[$expire[1]] = $expire[1];
		}
	}
	pclose($fd);
}

// echo "remove routes\n";
if (count($expires) > 0) {
	foreach ($expires as $prefix) {
		echo "/sbin/route delete -inet6 {$prefix['prefix']}\n";
	}
}

/* handle quotify_buf - https://source.isc.org/cgi-bin/gitweb.cgi?p=dhcp.git;a=blob;f=common/print.c */
function extract_duid($ia_string) {
	for ($i = 0, $iaid_counter = 0, $len = strlen($ia_string); $i < $len && $iaid_counter < 4; $i++, $iaid_counter++) {
		if ($ia_string[$i] !== '\\') {
			continue;
		}
		else if ($len - $i >= 2) {
			if (($ia_string[$i+1] === '\\') || ($ia_string[$i+1] === '"')) {
				$i += 1;
				continue;
			}
			else if ($len - $i >= 4) {
				if (preg_match('/[0-3][0-7]{2}/', substr($ia_string, $i+1, 3))) {
					$i += 3;
					continue;
				}
			}
		}

		return false;
	}

	/* Return anything after the first 4 octets! */
	if ($iaid_counter === 4) {
		/* substr returns false when $len == $i */
		return substr($ia_string, $i);
	}

	return false;
}

?>
