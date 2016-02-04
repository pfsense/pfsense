<?php

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
		if (isset($duid) && $duid !== false) {
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
				if (preg_match('/[0-7]{3}/', substr($ia_string, $i+1, $i+4))) {
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
