<?php
/*
	ipsec.attributes.php

	part of pfSense (https://www.pfsense.org)
	Copyright (c) 2011-2016 Electric Sheep Fencing, LLC.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in
	   the documentation and/or other materials provided with the
	   distribution.

	3. All advertising materials mentioning features or use of this software
	   must display the following acknowledgment:
	   "This product includes software developed by the pfSense Project
	   for use in the pfSense® software distribution. (http://www.pfsense.org/).

	4. The names "pfSense" and "pfSense Project" must not be used to
	   endorse or promote products derived from this software without
	   prior written permission. For written permission, please contact
	   coreteam@pfsense.org.

	5. Products derived from this software may not be called "pfSense"
	   nor may "pfSense" appear in their names without prior written
	   permission of the Electric Sheep Fencing, LLC.

	6. Redistributions of any form whatsoever must retain the following
	   acknowledgment:

	"This product includes software developed by the pfSense Project
	for use in the pfSense software distribution (http://www.pfsense.org/).

	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
	OF THE POSSIBILITY OF SUCH DAMAGE.
*/

if (empty($common_name)) {
	$common_name = getenv("common_name");
	if (empty($common_name)) {
		$common_name = getenv("username");
	}
}

function cisco_to_cidr($addr) {
	if (!is_ipaddr($addr)) {
		return 0;
	}
	$mask = decbin(~ip2long($addr));
	$mask = substr($mask, -32);
	$k = 0;
	for ($i = 0; $i <= 32; $i++) {
		$k += intval($mask[$i]);
	}
	return $k;
}

function cisco_extract_index($prule) {

	$index = explode("#", $prule);
	if (is_numeric($index[1])) {
		return intval($index[1]);
	} else {
		syslog(LOG_WARNING, "Error parsing rule {$prule}: Could not extract index");
	}
	return -1;;
}

function parse_cisco_acl($attribs) {
	global $attributes;
	if (!is_array($attribs)) {
		return "";
	}

	$devname = "enc0";
	$finalrules = "";
	if (is_array($attribs['ciscoavpair'])) {
		$inrules = array();
		$outrules = array();
		foreach ($attribs['ciscoavpair'] as $avrules) {
			$rule = explode("=", $avrules);
			$dir = "";
			if (strstr($rule[0], "inacl")) {
				$dir = "in";
			} else if (strstr($rule[0], "outacl")) {
				$dir = "out";
			} else if (strstr($rule[0], "dns-servers")) {
				$attributes['dns-servers'] = explode(" ", $rule[1]);
				continue;
			} else if (strstr($rule[0], "route")) {
				if (!is_array($attributes['routes'])) {
					$attributes['routes'] = array();
				}
				$attributes['routes'][] = $rule[1];
				continue;
			}
			$rindex = cisco_extract_index($rule[0]);
			if ($rindex < 0) {
				continue;
			}

			$rule = $rule[1];
			$rule = explode(" ", $rule);
			$tmprule = "";
			$index = 0;
			$isblock = false;
			if ($rule[$index] == "permit") {
				$tmprule = "pass {$dir} quick on {$devname} ";
			} else if ($rule[$index] == "deny") {
				//continue;
				$isblock = true;
				$tmprule = "block {$dir} quick on {$devname} ";
			} else {
				continue;
			}

			$index++;

			switch ($rule[$index]) {
				case "tcp":
				case "udp":
					$tmprule .= "proto {$rule[$index]} ";
					break;
			}

			$index++;
			/* Source */
			if (trim($rule[$index]) == "host") {
				$index++;
				$tmprule .= "from {$rule[$index]} ";
				$index++;
				if ($isblock == true) {
					$isblock = false;
				}
			} else if (trim($rule[$index]) == "any") {
				$tmprule .= "from any";
				$index++;
			} else {
				$tmprule .= "from {$rule[$index]}";
				$index++;
				$netmask = cisco_to_cidr($rule[$index]);
				$tmprule .= "/{$netmask} ";
				$index++;
				if ($isblock == true) {
					$isblock = false;
				}
			}
			/* Destination */
			if (trim($rule[$index]) == "host") {
				$index++;
				$tmprule .= "to {$rule[$index]} ";
				$index++;
				if ($isblock == true) {
					$isblock = false;
				}
			} else if (trim($rule[$index]) == "any") {
				$index++;
				$tmprule .= "to any";
			} else {
				$tmprule .= "to {$rule[$index]}";
				$index++;
				$netmask = cisco_to_cidr($rule[$index]);
				$tmprule .= "/{$netmask} ";
				$index++;
				if ($isblock == true) {
					$isblock = false;
				}
			}

			if ($isblock == true) {
				continue;
			}

			if ($dir == "in") {
				$inrules[$rindex] = $tmprule;
			} else if ($dir == "out") {
				$outrules[$rindex] = $tmprule;
			}
		}


		$state = "";
		if (!empty($outrules)) {
			$state = "no state";
		}
		ksort($inrules, SORT_NUMERIC);
		foreach ($inrules as $inrule) {
			$finalrules .= "{$inrule} {$state}\n";
		}
		if (!empty($outrules)) {
			ksort($outrules, SORT_NUMERIC);
			foreach ($outrules as $outrule) {
				$finalrules .= "{$outrule} {$state}\n";
			}
		}
	}
	return $finalrules;
}

$rules = parse_cisco_acl($attributes);
if (!empty($rules)) {
	$pid = posix_getpid();
	@file_put_contents("/tmp/ipsec_{$pid}{$common_name}.rules", $rules);
	mwexec("/sbin/pfctl -a " . escapeshellarg("ipsec/{$common_name}") . " -f {$g['tmp_path']}/ipsec_{$pid}" . escapeshellarg($common_name) . ".rules");
	@unlink("{$g['tmp_path']}/ipsec_{$pid}{$common_name}.rules");
}

?>
