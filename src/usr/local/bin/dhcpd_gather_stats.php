#!/usr/local/bin/php-cgi -q
<?php
/* $Id$ */
/*
	dhcpd_gather_stats.php
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
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
require("interfaces.inc");
/* echo the rrd required syntax */
echo "N:";
$result = array();
$result['active'] = "NaN";
$result['static'] ="NaN";
$result['range'] ="NaN";

if (is_array($config['dhcpd'][$argv[1]])) {

	$leasesfile = "{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases";
	$leases_contents = file($leasesfile);
	$dhcpif = $argv[1] ; 

	function remove_duplicate($array, $field) {
		foreach ($array as $sub) {
			$cmp[] = $sub[$field];
		}
		$unique = array_unique(array_reverse($cmp, true));
		foreach ($unique as $k => $rien) {
			$new[] = $array[$k];
		}
		return $new;
	}
	$awk = "/usr/bin/awk";
	/* this pattern sticks comments into a single array item */
	$cleanpattern = "'{ gsub(\"#.*\", \"\");} { gsub(\";\", \"\"); print;}'";
	/* We then split the leases file by } */
	$splitpattern = "'BEGIN { RS=\"}\";} {for (i=1; i<=NF; i++) printf \"%s \", \$i; printf \"}\\n\";}'";

	/* stuff the leases file in a proper format into a array by line */
	exec("/bin/cat {$leasesfile} | {$awk} {$cleanpattern} | {$awk} {$splitpattern}", $leases_content);
	$pools = array();
	$leases = array();
	$i = 0;
	$l = 0;
	$p = 0;

	// Put everything together again
	foreach ($leases_content as $lease) {
		/* split the line by space */
		$data = explode(" ", $lease);
		/* walk the fields */
		$f = 0;
		$fcount = count($data);
		/* with less than 20 fields there is nothing useful */
		if ($fcount < 20) {
			$i++;
			continue;
		}
		while ($f < $fcount) {
			switch ($data[$f]) {
				case "failover":
					$pools[$p]['name'] = trim($data[$f+2], '"');
					$pools[$p]['name'] = "{$pools[$p]['name']} (" . convert_friendly_interface_to_friendly_descr(substr($pools[$p]['name'], 5)) . ")";
					$pools[$p]['mystate'] = $data[$f+7];
					$pools[$p]['peerstate'] = $data[$f+14];
					$pools[$p]['mydate'] = $data[$f+10];
					$pools[$p]['mydate'] .= " " . $data[$f+11];
					$pools[$p]['peerdate'] = $data[$f+17];
					$pools[$p]['peerdate'] .= " " . $data[$f+18];
					$p++;
					$i++;
					continue 3;
				case "lease":
					$leases[$l]['ip'] = $data[$f+1];
					$leases[$l]['type'] = "dynamic";
					$f = $f+2;
					break;
				case "starts":
					$leases[$l]['start'] = $data[$f+2];
					$leases[$l]['start'] .= " " . $data[$f+3];
					$f = $f+3;
					break;
				case "ends":
					if ($data[$f+1] == "never") {
						// Quote from dhcpd.leases(5) man page:
						// If a lease will never expire, date is never instead of an actual date.
						$leases[$l]['end'] = gettext("Never");
						$f = $f+1;
					} else {
						$leases[$l]['end'] = $data[$f+2];
						$leases[$l]['end'] .= " " . $data[$f+3];
						$f = $f+3;
					}
					break;
				case "tstp":
					$f = $f+3;
					break;
				case "tsfp":
					$f = $f+3;
					break;
				case "atsfp":
					$f = $f+3;
					break;
				case "cltt":
					$f = $f+3;
					break;
				case "binding":
					switch ($data[$f+2]) {
						case "active":
							$leases[$l]['act'] = "active";
							break;
						case "free":
							$leases[$l]['act'] = "expired";
							$leases[$l]['online'] = "offline";
							break;
						case "backup":
							$leases[$l]['act'] = "reserved";
							$leases[$l]['online'] = "offline";
							break;
					}
					$f = $f+1;
					break;
				case "next":
					/* skip the next binding statement */
					$f = $f+3;
					break;
				case "rewind":
					/* skip the rewind binding statement */
					$f = $f+3;
					break;
				case "uid":
					$f = $f+1;
					break;
			}
			$f++;
		}
		$l++;
		$i++;
		/* slowly chisel away at the source array */
		array_shift($leases_content);
	}
	/* remove the old array */
	unset($lease_content);

	/* remove duplicate items by mac address */
	if (count($leases) > 0) {
		$leases = remove_duplicate($leases, "ip");
	}

	if (count($pools) > 0) {
		$pools = remove_duplicate($pools, "name");
		asort($pools);
	}

	foreach ($config['interfaces'] as $ifname => $ifarr) {
		if (is_array($config['dhcpd'][$ifname]) &&
			is_array($config['dhcpd'][$ifname]['staticmap'])) {
			$staticmap_array_index = 0;
			foreach ($config['dhcpd'][$ifname]['staticmap'] as $static) {
				$slease = array();
				$slease['ip'] = $static['ipaddr'];
				$slease['act'] = "static";
				$slease['staticmap_array_index'] = $staticmap_array_index;
				$leases[] = $slease;
				$staticmap_array_index++;
			}
		}
	}
	$ifcfgip = get_interface_ip($dhcpif);
	$ifcfgsn = get_interface_subnet($dhcpif);
	$subnet_start = gen_subnetv4($ifcfgip, $ifcfgsn);
	$subnet_end = gen_subnetv4_max($ifcfgip, $ifcfgsn);

	$result['range'] = (ip2ulong($config['dhcpd'][$dhcpif]['range']['to'])) - (ip2ulong($config['dhcpd'][$dhcpif]['range']['from']));
	
	foreach ($leases as $data) {
		if ($data['act'] != "active" && $data['act'] != "static" && $_GET['all'] != 1)
			continue;
		if ($data['act'] != "static") {
			if (is_inrange_v4($data['ip'], $config['dhcpd'][$dhcpif]['range']['from'], $config['dhcpd'][$dhcpif]['range']['to'])) {
					$result['active'] = $result['active'] + 1;
			}
		}
		else {
			if (is_inrange_v4($data['ip'], $subnet_start, $subnet_end)) {
				$result['static'] = $result['static'] + 1;
			}
		}
	}
}
echo $result['active'].":".$result['static'].":".$result['range'] ; 
