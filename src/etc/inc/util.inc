<?php
/*
 * util.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

define('VIP_ALL', 1);
define('VIP_CARP', 2);
define('VIP_IPALIAS', 3);

/* kill a process by pid file */
function killbypid($pidfile, $waitfor = 0) {
	return sigkillbypid($pidfile, "TERM", $waitfor);
}

function isvalidpid($pidfile) {
	$output = "";
	if (file_exists($pidfile)) {
		exec("/bin/pgrep -qnF {$pidfile} 2>/dev/null", $output, $retval);
		return (intval($retval) == 0);
	}
	return false;
}

function is_process_running($process) {
	$output = "";
	if (!empty($process)) {
		exec("/bin/pgrep -anx " . escapeshellarg($process), $output, $retval);
		return (intval($retval) == 0);
	}
	return false;
}

function isvalidproc($proc) {
	return is_process_running($proc);
}

/* sigkill a process by pid file, and wait for it to terminate or remove the .pid file for $waitfor seconds */
/* return 1 for success and 0 for a failure */
function sigkillbypid($pidfile, $sig, $waitfor = 0) {
	if (isvalidpid($pidfile)) {
		$result = mwexec("/bin/pkill " . escapeshellarg("-{$sig}") .
		    " -F {$pidfile}", true);
		$waitcounter = $waitfor * 10;
		while(isvalidpid($pidfile) && $waitcounter > 0) {
			$waitcounter = $waitcounter - 1;
			usleep(100000);
		}
		return $result;
	}

	return 0;
}

/* kill a process by name */
function sigkillbyname($procname, $sig) {
	if (isvalidproc($procname)) {
		return mwexec("/usr/bin/killall " . escapeshellarg("-{$sig}") . " " . escapeshellarg($procname), true);
	}
}

/* kill a process by name */
function killbyname($procname) {
	if (isvalidproc($procname)) {
		mwexec("/usr/bin/killall " . escapeshellarg($procname));
	}
}

function is_subsystem_dirty($subsystem = "") {
	global $g;

	if ($subsystem == "") {
		return false;
	}

	if (file_exists("{$g['varrun_path']}/{$subsystem}.dirty")) {
		return true;
	}

	return false;
}

function mark_subsystem_dirty($subsystem = "") {
	global $g;

	if (!file_put_contents("{$g['varrun_path']}/{$subsystem}.dirty", "DIRTY")) {
		log_error(sprintf(gettext("WARNING: Could not mark subsystem: %s dirty"), $subsystem));
	}
}

function clear_subsystem_dirty($subsystem = "") {
	global $g;

	@unlink("{$g['varrun_path']}/{$subsystem}.dirty");
}

/* lock configuration file */
function lock($lock, $op = LOCK_SH) {
	global $g;
	if (!$lock) {
		die(gettext("WARNING: A name must be given as parameter to lock() function."));
	}
	if (!file_exists("{$g['tmp_path']}/{$lock}.lock")) {
		@touch("{$g['tmp_path']}/{$lock}.lock");
		@chmod("{$g['tmp_path']}/{$lock}.lock", 0666);
	}
	if ($fp = fopen("{$g['tmp_path']}/{$lock}.lock", "w")) {
		if (flock($fp, $op)) {
			return $fp;
		} else {
			fclose($fp);
		}
	}
}

function try_lock($lock, $timeout = 5) {
	global $g;
	if (!$lock) {
		die(gettext("WARNING: A name must be given as parameter to try_lock() function."));
	}
	if (!file_exists("{$g['tmp_path']}/{$lock}.lock")) {
		@touch("{$g['tmp_path']}/{$lock}.lock");
		@chmod("{$g['tmp_path']}/{$lock}.lock", 0666);
	}
	if ($fp = fopen("{$g['tmp_path']}/{$lock}.lock", "w")) {
		$trycounter = 0;
		while (!flock($fp, LOCK_EX | LOCK_NB)) {
			if ($trycounter >= $timeout) {
				fclose($fp);
				return NULL;
			}
			sleep(1);
			$trycounter++;
		}

		return $fp;
	}

	return NULL;
}

/* unlock configuration file */
function unlock($cfglckkey = 0) {
	global $g;
	flock($cfglckkey, LOCK_UN);
	fclose($cfglckkey);
	return;
}

/* unlock forcefully configuration file */
function unlock_force($lock) {
	global $g;

	@unlink("{$g['tmp_path']}/{$lock}.lock");
}

function send_event($cmd) {
	global $g;

	if (!isset($g['event_address'])) {
		$g['event_address'] = "unix:///var/run/check_reload_status";
	}

	$try = 0;
	while ($try < 3) {
		$fd = @fsockopen($g['event_address']);
		if ($fd) {
			fwrite($fd, $cmd);
			$resp = fread($fd, 4096);
			if ($resp != "OK\n") {
				log_error("send_event: sent {$cmd} got {$resp}");
			}
			fclose($fd);
			$try = 3;
		} else if (!is_process_running("check_reload_status")) {
			mwexec_bg("/usr/bin/nice -n20 /usr/local/sbin/check_reload_status");
		}
		$try++;
	}
}

function send_multiple_events($cmds) {
	global $g;

	if (!isset($g['event_address'])) {
		$g['event_address'] = "unix:///var/run/check_reload_status";
	}

	if (!is_array($cmds)) {
		return;
	}

	while ($try < 3) {
		$fd = @fsockopen($g['event_address']);
		if ($fd) {
			foreach ($cmds as $cmd) {
				fwrite($fd, $cmd);
				$resp = fread($fd, 4096);
				if ($resp != "OK\n") {
					log_error("send_event: sent {$cmd} got {$resp}");
				}
			}
			fclose($fd);
			$try = 3;
		} else if (!is_process_running("check_reload_status")) {
			mwexec_bg("/usr/bin/nice -n20 /usr/local/sbin/check_reload_status");
		}
		$try++;
	}
}

function is_module_loaded($module_name) {
	$module_name = str_replace(".ko", "", $module_name);
	$running = 0;
	$_gb = exec("/sbin/kldstat -qn {$module_name} 2>&1", $_gb, $running);
	if (intval($running) == 0) {
		return true;
	} else {
		return false;
	}
}

/* validate non-negative numeric string, or equivalent numeric variable */
function is_numericint($arg) {
	return (((is_int($arg) && $arg >= 0) || (is_string($arg) && strlen($arg) > 0 && ctype_digit($arg))) ? true : false);
}

/* Generate the (human readable) ipv4 or ipv6 subnet address (i.e., netmask, or subnet start IP)
   given an (human readable) ipv4 or ipv6 host address and subnet bit count */
function gen_subnet($ipaddr, $bits) {
	if (($sn = gen_subnetv6($ipaddr, $bits)) == '') {
		$sn = gen_subnetv4($ipaddr, $bits);  // try to avoid rechecking IPv4/v6
	}
	return $sn;
}

/* same as gen_subnet() but accepts IPv4 only */
function gen_subnetv4($ipaddr, $bits) {
	if (is_ipaddrv4($ipaddr) && is_numericint($bits) && $bits <= 32) {
		if ($bits == 0) {
			return '0.0.0.0';  // avoids <<32
		}
		return long2ip(ip2long($ipaddr) & ((0xFFFFFFFF << (32 - $bits)) & 0xFFFFFFFF));
	}
	return "";
}

/* same as gen_subnet() but accepts IPv6 only */
function gen_subnetv6($ipaddr, $bits) {
	if (is_ipaddrv6($ipaddr) && is_numericint($bits) && $bits <= 128) {
		return text_to_compressed_ip6(Net_IPv6::getNetmask($ipaddr, $bits));
	}
	return "";
}

/* Generate the (human readable) ipv4 or ipv6 subnet end address (i.e., highest address, end IP, or IPv4 broadcast address)
   given an (human readable) ipv4 or ipv6 host address and subnet bit count. */
function gen_subnet_max($ipaddr, $bits) {
	if (($sn = gen_subnetv6_max($ipaddr, $bits)) == '') {
		$sn = gen_subnetv4_max($ipaddr, $bits);  // try to avoid rechecking IPv4/v6
	}
	return $sn;
}

/* same as gen_subnet_max() but validates IPv4 only */
function gen_subnetv4_max($ipaddr, $bits) {
	if (is_ipaddrv4($ipaddr) && is_numericint($bits) && $bits <= 32) {
		if ($bits == 32) {
			return $ipaddr;
		}
		return long2ip32(ip2long($ipaddr) | (~gen_subnet_mask_long($bits) & 0xFFFFFFFF));
	}
	return "";
}

/* same as gen_subnet_max() but validates IPv6 only */
function gen_subnetv6_max($ipaddr, $bits) {
	if (is_ipaddrv6($ipaddr) && is_numericint($bits) && $bits <= 128) {
		$endip_bin = substr(ip6_to_bin($ipaddr), 0, $bits) . str_repeat('1', 128 - $bits);
		return bin_to_compressed_ip6($endip_bin);
	}
	return "";
}

/* returns a subnet mask (long given a bit count) */
function gen_subnet_mask_long($bits) {
	$sm = 0;
	for ($i = 0; $i < $bits; $i++) {
		$sm >>= 1;
		$sm |= 0x80000000;
	}
	return $sm;
}

/* same as above but returns a string */
function gen_subnet_mask($bits) {
	return long2ip(gen_subnet_mask_long($bits));
}

/* Convert a prefix length to an IPv6 address-like mask notation. Very rare but at least ntp needs it. See #4463 */
function gen_subnet_mask_v6($bits) {
	/* Binary representation of the prefix length */
	$bin = str_repeat('1', $bits);
	/* Pad right with zeroes to reach the full address length */
	$bin = str_pad($bin, 128, '0', STR_PAD_RIGHT);
	/* Convert back to an IPv6 address style notation */
	return bin_to_ip6($bin);
}

/* Convert long int to IPv4 address
   Returns '' if not valid IPv4 (including if any bits >32 are non-zero) */
function long2ip32($ip) {
	return long2ip($ip & 0xFFFFFFFF);
}

/* Convert IPv4 address to long int, truncated to 32-bits to avoid sign extension on 64-bit platforms.
   Returns '' if not valid IPv4. */
function ip2long32($ip) {
	return (ip2long($ip) & 0xFFFFFFFF);
}

/* Convert IPv4 address to unsigned long int.
   Returns '' if not valid IPv4. */
function ip2ulong($ip) {
	return sprintf("%u", ip2long32($ip));
}

/*
 * Convert IPv6 address to binary
 *
 * Obtained from: pear-Net_IPv6
 */
function ip6_to_bin($ip) {
	$binstr = '';

	$ip = Net_IPv6::removeNetmaskSpec($ip);
	$ip = Net_IPv6::Uncompress($ip);

	$parts = explode(':', $ip);

	foreach ( $parts as $v ) {

		$str     = base_convert($v, 16, 2);
		$binstr .= str_pad($str, 16, '0', STR_PAD_LEFT);

	}

	return $binstr;
}

/*
 * Convert IPv6 binary to uncompressed address
 *
 * Obtained from: pear-Net_IPv6
 */
function bin_to_ip6($bin) {
	$ip = "";

	if (strlen($bin) < 128) {
		$bin = str_pad($bin, 128, '0', STR_PAD_LEFT);
	}

	$parts = str_split($bin, "16");

	foreach ( $parts as $v ) {
		$str = base_convert($v, 2, 16);
		$ip .= $str.":";
	}

	$ip = substr($ip, 0, -1);

	return $ip;
}

/*
 * Convert IPv6 binary to compressed address
 */
function bin_to_compressed_ip6($bin) {
	return text_to_compressed_ip6(bin_to_ip6($bin));
}

/*
 * Convert textual IPv6 address string to compressed address
 */
function text_to_compressed_ip6($text) {
	// Force re-compression by passing parameter 2 (force) true.
	// This ensures that supposedly-compressed formats are uncompressed
	// first then re-compressed into strictly correct form.
	// e.g. 2001:0:0:4:0:0:0:1
	// 2001::4:0:0:0:1 is a strictly-incorrect compression,
	// but maybe the user entered it like that.
	// The "force" parameter will ensure it is returned as:
	// 2001:0:0:4::1
	return Net_IPv6::compress($text, true);
}

/* Find out how many IPs are contained within a given IP range
 *  e.g. 192.168.0.0 to 192.168.0.255 returns 256
 */
function ip_range_size_v4($startip, $endip) {
	if (is_ipaddrv4($startip) && is_ipaddrv4($endip)) {
		// Operate as unsigned long because otherwise it wouldn't work
		//   when crossing over from 127.255.255.255 / 128.0.0.0 barrier
		return abs(ip2ulong($startip) - ip2ulong($endip)) + 1;
	}
	return -1;
}

/* Find the smallest possible subnet mask which can contain a given number of IPs
 *  e.g. 512 IPs can fit in a /23, but 513 IPs need a /22
 */
function find_smallest_cidr_v4($number) {
	$smallest = 1;
	for ($b=32; $b > 0; $b--) {
		$smallest = ($number <= pow(2, $b)) ? $b : $smallest;
	}
	return (32-$smallest);
}

/* Return the previous IP address before the given address */
function ip_before($ip, $offset = 1) {
	return long2ip32(ip2long($ip) - $offset);
}

/* Return the next IP address after the given address */
function ip_after($ip, $offset = 1) {
	return long2ip32(ip2long($ip) + $offset);
}

/* Return true if the first IP is 'before' the second */
function ip_less_than($ip1, $ip2) {
	// Compare as unsigned long because otherwise it wouldn't work when
	//   crossing over from 127.255.255.255 / 128.0.0.0 barrier
	return ip2ulong($ip1) < ip2ulong($ip2);
}

/* Return true if the first IP is 'after' the second */
function ip_greater_than($ip1, $ip2) {
	// Compare as unsigned long because otherwise it wouldn't work
	//   when crossing over from 127.255.255.255 / 128.0.0.0 barrier
	return ip2ulong($ip1) > ip2ulong($ip2);
}

/* compare two IP addresses */
function ipcmp($a, $b) {
	if (is_subnet($a)) {
		list($a, $amask) = explode('/', $a);
	}
	if (is_subnet($b)) {
		list($b, $bmask) = explode('/', $b);
	}
	if (ip_less_than($a, $b)) {
		return -1;
	} else if (ip_greater_than($a, $b)) {
		return 1;
	} else {
		return 0;
	}
}

/* Convert a range of IPv4 addresses to an array of individual addresses. */
/* Note: IPv6 ranges are not yet supported here. */
function ip_range_to_address_array($startip, $endip, $max_size = 5000) {
	if (!is_ipaddrv4($startip) || !is_ipaddrv4($endip)) {
		return false;
	}

	if (ip_greater_than($startip, $endip)) {
		// Swap start and end so we can process sensibly.
		$temp = $startip;
		$startip = $endip;
		$endip = $temp;
	}

	if (ip_range_size_v4($startip, $endip) > $max_size) {
		return false;
	}

	// Container for IP addresses within this range.
	$rangeaddresses = array();
	$end_int = ip2ulong($endip);
	for ($ip_int = ip2ulong($startip); $ip_int <= $end_int; $ip_int++) {
		$rangeaddresses[] = long2ip($ip_int);
	}

	return $rangeaddresses;
}

/*
 * Convert an IPv4 or IPv6 IP range to an array of subnets which can contain the range.
 * Algorithm and embodying code PD'ed by Stilez - enjoy as you like :-)
 *
 * Documented on pfsense dev list 19-20 May 2013. Summary:
 *
 * The algorithm looks at patterns of 0's and 1's in the least significant bit(s), whether IPv4 or IPv6.
 * These are all that needs checking to identify a _guaranteed_ correct, minimal and optimal subnet array.
 *
 * As a result, string/binary pattern matching of the binary IP is very efficient. It uses just 2 pattern-matching rules
 * to chop off increasingly larger subnets at both ends that can't be part of larger subnets, until nothing's left.
 *
 * (a) If any range has EITHER low bit 1 (in startip) or 0 (in endip), that end-point is _always guaranteed_ to be optimally
 * represented by its own 'single IP' CIDR; the remaining range then shrinks by one IP up or down, causing the new end-point's
 * low bit to change from 1->0 (startip) or 0->1 (endip). Only one edge case needs checking: if a range contains exactly 2
 * adjacent IPs of this format, then the two IPs themselves are required to span it, and we're done.
 * Once this rule is applied, the remaining range is _guaranteed_ to end in 0's and 1's so rule (b) can now be used, and its
 * low bits can now be ignored.
 *
 * (b) If any range has BOTH startip and endip ending in some number of 0's and 1's respectively, these low bits can
 * *always* be ignored and "bit-shifted" for subnet spanning. So provided we remember the bits we've place-shifted, we can
 * _always_ right-shift and chop off those bits, leaving a smaller range that has EITHER startip ending in 1 or endip ending
 * in 0 (ie can now apply (a) again) or the entire range has vanished and we're done.
 * We then loop to redo (a) again on the remaining (place shifted) range until after a few loops, the remaining (place shifted)
 * range 'vanishes' by meeting the exit criteria of (a) or (b), and we're done.
 */
function ip_range_to_subnet_array($ip1, $ip2) {

	if (is_ipaddrv4($ip1) && is_ipaddrv4($ip2)) {
		$proto = 'ipv4';  // for clarity
		$bits = 32;
		$ip1bin = decbin(ip2long32($ip1));
		$ip2bin = decbin(ip2long32($ip2));
	} elseif (is_ipaddrv6($ip1) && is_ipaddrv6($ip2)) {
		$proto = 'ipv6';
		$bits = 128;
		$ip1bin = ip6_to_bin($ip1);
		$ip2bin = ip6_to_bin($ip2);
	} else {
		return array();
	}

	// it's *crucial* that binary strings are guaranteed the expected length;  do this for certainty even though for IPv6 it's redundant
	$ip1bin = str_pad($ip1bin, $bits, '0', STR_PAD_LEFT);
	$ip2bin = str_pad($ip2bin, $bits, '0', STR_PAD_LEFT);

	if ($ip1bin == $ip2bin) {
		return array($ip1 . '/' . $bits); // exit if ip1=ip2 (trivial case)
	}

	if ($ip1bin > $ip2bin) {
		list ($ip1bin, $ip2bin) = array($ip2bin, $ip1bin);  // swap if needed (ensures ip1 < ip2)
	}

	$rangesubnets = array();
	$netsize = 0;

	do {
		// at loop start, $ip1 is guaranteed strictly less than $ip2 (important for edge case trapping and preventing accidental binary wrapround)
		// which means the assignments $ip1 += 1 and $ip2 -= 1 will always be "binary-wrapround-safe"

		// step #1 if start ip (as shifted) ends in any '1's, then it must have a single cidr to itself (any cidr would include the '0' below it)

		if (substr($ip1bin, -1, 1) == '1') {
			// the start ip must be in a separate one-IP cidr range
			$new_subnet_ip = substr($ip1bin, $netsize, $bits - $netsize) . str_repeat('0', $netsize);
			$rangesubnets[$new_subnet_ip] = $bits - $netsize;
			$n = strrpos($ip1bin, '0');  //can't be all 1's
			$ip1bin = ($n == 0 ? '' : substr($ip1bin, 0, $n)) . '1' . str_repeat('0', $bits - $n - 1);  // BINARY VERSION OF $ip1 += 1
		}

		// step #2, if end ip (as shifted) ends in any zeros then that must have a cidr to itself (as cidr cant span the 1->0 gap)

		if (substr($ip2bin, -1, 1) == '0') {
			// the end ip must be in a separate one-IP cidr range
			$new_subnet_ip = substr($ip2bin, $netsize, $bits - $netsize) . str_repeat('0', $netsize);
			$rangesubnets[$new_subnet_ip] = $bits - $netsize;
			$n = strrpos($ip2bin, '1');  //can't be all 0's
			$ip2bin = ($n == 0 ? '' : substr($ip2bin, 0, $n)) . '0' . str_repeat('1', $bits - $n - 1);  // BINARY VERSION OF $ip2 -= 1
			// already checked for the edge case where end = start+1 and start ends in 0x1, above, so it's safe
		}

		// this is the only edge case arising from increment/decrement.
		// it happens if the range at start of loop is exactly 2 adjacent ips, that spanned the 1->0 gap. (we will have enumerated both by now)

		if ($ip2bin < $ip1bin) {
			continue;
		}

		// step #3 the start and end ip MUST now end in '0's and '1's respectively
		// so we have a non-trivial range AND the last N bits are no longer important for CIDR purposes.

		$shift = $bits - max(strrpos($ip1bin, '0'), strrpos($ip2bin, '1'));  // num of low bits which are '0' in ip1 and '1' in ip2
		$ip1bin = str_repeat('0', $shift) . substr($ip1bin, 0, $bits - $shift);
		$ip2bin = str_repeat('0', $shift) . substr($ip2bin, 0, $bits - $shift);
		$netsize += $shift;
		if ($ip1bin == $ip2bin) {
			// we're done.
			$new_subnet_ip = substr($ip1bin, $netsize, $bits - $netsize) . str_repeat('0', $netsize);
			$rangesubnets[$new_subnet_ip] = $bits - $netsize;
			continue;
		}

		// at this point there's still a remaining range, and either startip ends with '1', or endip ends with '0'. So repeat cycle.
	} while ($ip1bin < $ip2bin);

	// subnets are ordered by bit size. Re sort by IP ("naturally") and convert back to IPv4/IPv6

	ksort($rangesubnets, SORT_STRING);
	$out = array();

	foreach ($rangesubnets as $ip => $netmask) {
		if ($proto == 'ipv4') {
			$i = str_split($ip, 8);
			$out[] = implode('.', array(bindec($i[0]), bindec($i[1]), bindec($i[2]), bindec($i[3]))) . '/' . $netmask;
		} else {
			$out[] = bin_to_compressed_ip6($ip) . '/' . $netmask;
		}
	}

	return $out;
}

/* returns true if $range is a valid pair of IPv4 or IPv6 addresses separated by a "-"
	false - if not a valid pair
	true (numeric 4 or 6) - if valid, gives type of addresses */
function is_iprange($range) {
	if (substr_count($range, '-') != 1) {
		return false;
	}
	list($ip1, $ip2) = explode ('-', $range);
	if (is_ipaddrv4($ip1) && is_ipaddrv4($ip2)) {
		return 4;
	}
	if (is_ipaddrv6($ip1) && is_ipaddrv6($ip2)) {
		return 6;
	}
	return false;
}

/* returns true if $ipaddr is a valid dotted IPv4 address or a IPv6
	false - not valid
	true (numeric 4 or 6) - if valid, gives type of address */
function is_ipaddr($ipaddr) {
	if (is_ipaddrv4($ipaddr)) {
		return 4;
	}
	if (is_ipaddrv6($ipaddr)) {
		return 6;
	}
	return false;
}

/* returns true if $ipaddr is a valid IPv6 address */
function is_ipaddrv6($ipaddr) {
	if (!is_string($ipaddr) || empty($ipaddr)) {
		return false;
	}
	/*
	 * While Net_IPv6::checkIPv6() considers IPv6/mask a valid IPv6,
	 * is_ipaddrv6() needs to be more strict to keep the compatibility
	 * with is_ipaddrv4().
	 */
	if (strstr($ipaddr, "/")) {
		return false;
	}
	if (strstr($ipaddr, "%") && is_linklocal($ipaddr)) {
		$tmpip = explode("%", $ipaddr);
		$ipaddr = $tmpip[0];
	}
	return Net_IPv6::checkIPv6($ipaddr);
}

function is_ipaddrv6_v4map($ipaddr) {
	/* check RFC4291 par 2.2.2 format, ex: fd00::1.2.3.4
	 * see https://redmine.pfsense.org/issues/11446 */
	if (is_ipaddrv6($ipaddr) && preg_match('/^[0-9a-f:]{2,30}[0-9.]{7,15}$/i', $ipaddr)) {
		return true;
	}
	return false;
}

/* returns true if $ipaddr is a valid dotted IPv4 address */
function is_ipaddrv4($ipaddr) {
	if (!is_string($ipaddr) || empty($ipaddr) || ip2long($ipaddr) === FALSE) {
		return false;
	}
	return true;
}

function is_mcast($ipaddr) {
	if (is_mcastv4($ipaddr)) {
		return 4;
	}
	if (is_mcastv6($ipaddr)) {
		return 6;
	}
	return false;
}

function is_mcastv4($ipaddr) {
	if (!is_ipaddrv4($ipaddr) ||
	    !preg_match('/^2(?:2[4-9]|3\d)(?:\.(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d?|0)){3}$/', $ipaddr)) {
		return false;
	}
	return true;
}

function is_mcastv6($ipaddr) {
	if (!is_ipaddrv6($ipaddr) || !preg_match('/^ff.+$/', $ipaddr)) {
		return false;
	}
	return true;
}

/* returns 4 or 6 respectively (== TRUE) if $ipaddr is a valid IPv4 or IPv6 linklocal address
   returns '' if not a valid linklocal address */
function is_linklocal($ipaddr) {
	if (is_ipaddrv4($ipaddr)) {
		// input is IPv4
		// test if it's 169.254.x.x per rfc3927 2.1
		$ip4 = explode(".", $ipaddr);
		if ($ip4[0] == '169' && $ip4[1] == '254') {
			return 4;
		}
	} elseif (Net_IPv6::getAddressType($ipaddr) == NET_IPV6_LOCAL_LINK) {
		return 6;
	}
	return '';
}

/* returns scope of a linklocal address */
function get_ll_scope($addr) {
	if (!is_linklocal($addr) || !strstr($addr, "%")) {
		return "";
	}
	list ($ll, $scope) = explode("%", $addr);
	return $scope;
}

/* returns true if $ipaddr is a valid literal IPv6 address */
function is_literalipaddrv6($ipaddr) {
	if (substr($ipaddr,0,1) == '[' && substr($ipaddr,-1,1) == ']') {
		// if it's data wrapped in "[ ... ]" then test if middle part is valid IPv6
		return is_ipaddrv6(substr($ipaddr,1,-1));
	}
	return false;
}

/* returns true if $iport is a valid IPv4:port or [Literal IPv6]:port
	false - not valid
	true (numeric 4 or 6) - if valid, gives type of address */
function is_ipaddrwithport($ipport) {
	$c = strrpos($ipport, ":");
	if ($c === false) {
		return false;  // can't split at final colon if no colon exists
	}

	if (!is_port(substr($ipport, $c + 1))) {
		return false;  // no valid port after last colon
	}

	$ip = substr($ipport, 0, $c);  // else is text before last colon a valid IP
	if (is_literalipaddrv6($ip)) {
		return 6;
	} elseif (is_ipaddrv4($ip)) {
		return 4;
	} else {
		return false;
	}
}

function is_hostnamewithport($hostport) {
	$parts = explode(":", $hostport);
	// no need to validate with is_string(); if it's not a string then explode won't return 2 parts anyway
	if (count($parts) == 2) {
		return is_hostname($parts[0]) && is_port($parts[1]);
	}
	return false;
}

/* returns true if $ipaddr is a valid dotted IPv4 address or an alias thereof */
function is_ipaddroralias($ipaddr) {
	global $config;

	if (is_alias($ipaddr)) {
		if (is_array($config['aliases']['alias'])) {
			foreach ($config['aliases']['alias'] as $alias) {
				if ($alias['name'] == $ipaddr && !preg_match("/port/i", $alias['type'])) {
					return true;
				}
			}
		}
		return false;
	} else {
		return is_ipaddr($ipaddr);
	}

}

/* returns true if $subnet is a valid IPv4 or IPv6 subnet in CIDR format
	false - if not a valid subnet
	true (numeric 4 or 6) - if valid, gives type of subnet */
function is_subnet($subnet) {
	if (is_string($subnet) && preg_match('/^(?:([0-9.]{7,15})|([0-9a-f:]{2,39}|[0-9a-f:]{2,30}[0-9.]{7,15}))\/(\d{1,3})$/i', $subnet, $parts)) {
		if (is_ipaddrv4($parts[1]) && $parts[3] <= 32) {
			return 4;
		}
		if (is_ipaddrv6($parts[2]) && $parts[3] <= 128) {
			return 6;
		}
	}
	return false;
}

function is_v4($ip_or_subnet) {
	return is_ipaddrv4($ip_or_subnet) || is_subnetv4($ip_or_subnet);
}

function is_v6($ip_or_subnet) {
	return is_ipaddrv6($ip_or_subnet) || is_subnetv6($ip_or_subnet);
}

/* same as is_subnet() but accepts IPv4 only */
function is_subnetv4($subnet) {
	return (is_subnet($subnet) == 4);
}

/* same as is_subnet() but accepts IPv6 only */
function is_subnetv6($subnet) {
	return (is_subnet($subnet) == 6);
}

/* returns true if $subnet is a valid subnet in CIDR format or an alias thereof */
function is_subnetoralias($subnet) {
	global $aliastable;

	if (isset($aliastable[$subnet]) && is_subnet($aliastable[$subnet])) {
		return true;
	} else {
		return is_subnet($subnet);
	}
}

/* Get number of addresses in an IPv4/IPv6 subnet (represented as a string)
   optional $exact=true forces error (0) to be returned if it can't be represented exactly
   Exact result not possible above PHP_MAX_INT which is about 2^31 addresses on x32 or 2^63 on x64
   Returns 0 for bad data or if cannot represent size as an INT when $exact is set. */
function subnet_size($subnet, $exact=false) {
	$parts = explode("/", $subnet);
	$iptype = is_ipaddr($parts[0]);
	if (count($parts) == 2 && $iptype) {
		return subnet_size_by_netmask($iptype, $parts[1], $exact);
	}
	return 0;
}

/* Get number of addresses in an IPv4/IPv6 subnet (represented numerically as IP type + bits)
   optional $exact=true forces error (0) to be returned if it can't be represented exactly
   Hard to think where we might need to count exactly a huge subnet but an overflow detection option is probably sensible
   Returns 0 for bad data or if cannot represent size as an INT when $exact is set. */
function subnet_size_by_netmask($iptype, $bits, $exact=false) {
	if (!is_numericint($bits)) {
		return 0;
	} elseif ($iptype == 4 && $bits <= 32) {
		$snsize = 32 - $bits;
	} elseif ($iptype == 6 && $bits <= 128) {
		$snsize = 128 - $bits;
	} else {
		return 0;
	}

	// 2**N returns an exact result as an INT if possible, and a float/double if not.
	// Detect this switch, rather than comparing $result<=PHP_MAX_INT or $bits >=8*PHP_INT_SIZE as it's (probably) easier to get completely reliable
	$result = 2 ** $snsize;

	if ($exact && !is_int($result)) {
		//exact required but can't represent result exactly as an INT
		return 0;
	} else {
		// result ok, will be an INT where possible (guaranteed up to 2^31 addresses on x32/x64) and a float for 'huge' subnets
		return $result;
	}
}

/* function used by pfblockerng */
function subnetv4_expand($subnet) {
	$result = array();
	list ($ip, $bits) = explode("/", $subnet);
	$net = ip2long($ip);
	$mask = (0xffffffff << (32 - $bits));
	$net &= $mask;
	$size = round(exp(log(2) * (32 - $bits)));
	for ($i = 0; $i < $size; $i += 1) {
		$result[] = long2ip($net | $i);
	}
	return $result;
}

/* find out whether two IPv4/IPv6 CIDR subnets overlap.
   Note: CIDR overlap implies one is identical or included so largest sn will be the same */
function check_subnets_overlap($subnet1, $bits1, $subnet2, $bits2) {
	if (is_ipaddrv4($subnet1)) {
		return check_subnetsv4_overlap($subnet1, $bits1, $subnet2, $bits2);
	} else {
		return check_subnetsv6_overlap($subnet1, $bits1, $subnet2, $bits2);
	}
}

/* find out whether two IPv4 CIDR subnets overlap.
   Note: CIDR overlap means sn1/sn2 are identical or one is included in other. So sn using largest $bits will be the same  */
function check_subnetsv4_overlap($subnet1, $bits1, $subnet2, $bits2) {
	$largest_sn = min($bits1, $bits2);
	$subnetv4_start1 = gen_subnetv4($subnet1, $largest_sn);
	$subnetv4_start2 = gen_subnetv4($subnet2, $largest_sn);

	if ($subnetv4_start1 == '' || $subnetv4_start2 == '') {
		// One or both args is not a valid IPv4 subnet
		//FIXME: needs to return "bad data" not true/false if bad. For now return false, best we can do until fixed
		return false;
	}
	return ($subnetv4_start1 == $subnetv4_start2);
}

/* find out whether two IPv6 CIDR subnets overlap.
   Note: CIDR overlap means sn1/sn2 are identical or one is included in other. So sn using largest $bits will be the same  */
function check_subnetsv6_overlap($subnet1, $bits1, $subnet2, $bits2) {
	$largest_sn = min($bits1, $bits2);
	$subnetv6_start1 = gen_subnetv6($subnet1, $largest_sn);
	$subnetv6_start2 = gen_subnetv6($subnet2, $largest_sn);

	if ($subnetv6_start1 == '' || $subnetv6_start2 == '') {
		// One or both args is not a valid IPv6 subnet
		//FIXME: needs to return "bad data" not true/false if bad. For now return false, best we can do until fixed
		return false;
	}
	return ($subnetv6_start1 == $subnetv6_start2);
}

/* return all PTR zones for a IPv6 network */
function get_v6_ptr_zones($subnet, $bits) {
	$result = array();

	if (!is_ipaddrv6($subnet)) {
		return $result;
	}

	if (!is_numericint($bits) || $bits > 128) {
		return $result;
	}

	/*
	 * Find a small nibble boundary subnet mask
	 * e.g. a /29 will create 8 /32 PTR zones
	 */
	$small_sn = $bits;
	while ($small_sn % 4 != 0) {
		$small_sn++;
	}

	/* Get network prefix */
	$small_subnet = Net_IPv6::getNetmask($subnet, $bits);

	/*
	 * While small network is part of bigger one, increase 4-bit in last
	 * digit to get next small network
	 */
	while (Net_IPv6::isInNetmask($small_subnet, $subnet, $bits)) {
		/* Get a pure hex value */
		$unpacked = unpack('H*hex', inet_pton($small_subnet));
		/* Create PTR record using $small_sn / 4 chars */
		$result[] = implode('.', array_reverse(str_split(substr(
		    $unpacked['hex'], 0, $small_sn / 4)))).'.ip6.arpa';

		/* Detect what part of IP should be increased */
		$change_part = (int) ($small_sn / 16);
		if ($small_sn % 16 == 0) {
			$change_part--;
		}

		/* Increase 1 to desired part */
		$parts = explode(":", Net_IPv6::uncompress($small_subnet));
		$parts[$change_part]++;
		$small_subnet = implode(":", $parts);
	}

	return $result;
}

/* return true if $addr is in $subnet, false if not */
function ip_in_subnet($addr, $subnet) {
	if (is_ipaddrv6($addr) && is_subnetv6($subnet)) {
		return (Net_IPv6::isInNetmask($addr, $subnet));
	} else if (is_ipaddrv4($addr) && is_subnetv4($subnet)) {
		list($ip, $mask) = explode('/', $subnet);
		$mask = (0xffffffff << (32 - $mask)) & 0xffffffff;
		return ((ip2long($addr) & $mask) == (ip2long($ip) & $mask));
	}
	return false;
}

/* returns true if $hostname is just a valid hostname (top part without any of the domain part) */
function is_unqualified_hostname($hostname) {
	if (!is_string($hostname)) {
		return false;
	}

	if (preg_match('/^(?:[a-z0-9_]|[a-z0-9_][a-z0-9_\-]*[a-z0-9_])$/i', $hostname)) {
		return true;
	} else {
		return false;
	}
}

/* returns true if $hostname is a valid hostname, with or without being a fully-qualified domain name. */
function is_hostname($hostname, $allow_wildcard=false) {
	if (!is_string($hostname)) {
		return false;
	}

	if (is_domain($hostname, $allow_wildcard)) {
		if ((substr_count($hostname, ".") == 1) && ($hostname[strlen($hostname)-1] == ".")) {
			/* Only a single dot at the end like "test." - hosts cannot be directly in the root domain. */
			return false;
		} elseif (!is_numericint($hostname)) {
			/* OS interprets numeric value as decimal IP address, see https://redmine.pfsense.org/issues/12000 */
			return true;
		}
	}
	return false;
}

/* returns true if $domain is a valid domain name */
function is_domain($domain, $allow_wildcard=false, $trailing_dot=true) {
	if (!is_string($domain)) {
		return false;
	}
	if (!$trailing_dot && ($domain[strlen($domain)-1] == ".")) {
		return false;
	}
	if ($allow_wildcard) {
		$domain_regex = '/^(?:(?:[a-z_0-9\*]|[a-z_0-9][a-z_0-9\-]*[a-z_0-9])\.)*(?:[a-z_0-9]|[a-z_0-9][a-z_0-9\-]*[a-z_0-9\.])$/i';
	} else {
		$domain_regex = '/^(?:(?:[a-z_0-9]|[a-z_0-9][a-z_0-9\-]*[a-z_0-9])\.)*(?:[a-z_0-9]|[a-z_0-9][a-z_0-9\-]*[a-z_0-9\.])$/i';
	}

	if (preg_match($domain_regex, $domain)) {
		return true;
	} else {
		return false;
	}
}

/* returns true if $macaddr is a valid MAC address */
function is_macaddr($macaddr, $partial=false) {
	$values = explode(":", $macaddr);

	/* Verify if the MAC address has a proper amount of parts for either a partial or full match. */
	if ($partial) {
		if ((count($values) < 1) || (count($values) > 6)) {
			return false;
		}
	} elseif (count($values) != 6) {
		return false;
	}
	for ($i = 0; $i < count($values); $i++) {
		if (ctype_xdigit($values[$i]) == false)
			return false;
		if (hexdec($values[$i]) < 0 || hexdec($values[$i]) > 255)
			return false;
	}

	return true;
}

/*
	If $return_message is true then
		returns a text message about the reason that the name is invalid.
		the text includes the type of "thing" that is being checked, passed in $object. (e.g. "alias", "gateway group", "schedule")
	else
		returns true if $name is a valid name for an alias
		returns false if $name is not a valid name for an alias

	Aliases cannot be:
		bad chars: anything except a-z 0-9 and underscore
		bad names: empty string, pure numeric, pure underscore
		reserved words: pre-defined service/protocol/port names which should not be ambiguous, and the words "port" and  "pass" */

function is_validaliasname($name, $return_message = false, $object = "alias") {
	/* Array of reserved words */
	$reserved = array("port", "pass");

	if (!is_string($name) || strlen($name) >= 32 || preg_match('/(^_*$|^\d*$|[^a-z0-9_])/i', $name)) {
		if ($return_message) {
			return sprintf(gettext('The %1$s name must be less than 32 characters long, may not consist of only numbers, may not consist of only underscores, and may only contain the following characters: %2$s'), $object, 'a-z, A-Z, 0-9, _');
		} else {
			return false;
		}
	}
	if (in_array($name, $reserved, true)) {
		if ($return_message) {
			return sprintf(gettext('The %1$s name must not be either of the reserved words %2$s or %3$s.'), $object, "'port'", "'pass'");
		} else {
			return false;
		}
	}
	if (getprotobyname($name)) {
		if ($return_message) {
			return sprintf(gettext('The %1$s name must not be an IP protocol name such as TCP, UDP, ICMP etc.'), $object);
		} else {
			return false;
		}
	}
	if (getservbyname($name, "tcp") || getservbyname($name, "udp")) {
		if ($return_message) {
			return sprintf(gettext('The %1$s name must not be a well-known or registered TCP or UDP port name such as ssh, smtp, pop3, tftp, http, openvpn etc.'), $object);
		} else {
			return false;
		}
	}
	if ($return_message) {
		return sprintf(gettext("The %1$s name is valid."), $object);
	} else {
		return true;
	}
}

/* returns a text message indicating if the alias name is valid, or the reason it is not valid. */
function invalidaliasnamemsg($name, $object = "alias") {
	return is_validaliasname($name, true, $object);
}

/*
 * returns true if $range is a valid integer range between $min and $max
 * range delimiter can be ':' or '-'
 */
function is_intrange($range, $min, $max) {
	$values = preg_split("/[:-]/", $range);

	if (!is_array($values) || count($values) != 2) {
		return false;
	}

	if (!ctype_digit($values[0]) || !ctype_digit($values[1])) {
		return false;
	}

	$values[0] = intval($values[0]);
	$values[1] = intval($values[1]);

	if ($values[0] >= $values[1]) {
		return false;
	}

	if ($values[0] < $min || $values[1] > $max) {
		return false;
	}

	return true;
}

/* returns true if $port is a valid TCP/UDP port */
function is_port($port) {
	if (ctype_digit($port) && ((intval($port) >= 1) && (intval($port) <= 65535))) {
		return true;
	}
	if (getservbyname($port, "tcp") || getservbyname($port, "udp")) {
		return true;
	}
	return false;
}

/* returns true if $port is in use */
function is_port_in_use($port, $proto = "tcp", $ip_version = 4) {
	$port_info = array();
	exec("/usr/bin/netstat --libxo json -an " . escapeshellarg('-' . $ip_version) . " -p " . escapeshellarg($proto), $rawdata, $rc);
	if ($rc == 0) {
		$netstatarr = json_decode(implode(" ", $rawdata), JSON_OBJECT_AS_ARRAY);
		$netstatarr = $netstatarr['statistics']['socket'];

		foreach($netstatarr as $index => $portstats){
			array_push($port_info, $portstats['local']['port']);
		}
	}

	return in_array($port, $port_info);
}

/* returns true if $portrange is a valid TCP/UDP portrange ("<port>:<port>") */
function is_portrange($portrange) {
	$ports = explode(":", $portrange);

	return (count($ports) == 2 && is_port($ports[0]) && is_port($ports[1]));
}

/* returns true if $port is a valid TCP/UDP port number or range ("<port>:<port>") */
function is_port_or_range($port) {
	return (is_port($port) || is_portrange($port));
}

/* returns true if $port is an alias that is a port type */
function is_portalias($port) {
	global $config;

	if (is_alias($port)) {
		if (is_array($config['aliases']['alias'])) {
			foreach ($config['aliases']['alias'] as $alias) {
				if ($alias['name'] == $port && preg_match("/port/i", $alias['type'])) {
					return true;
				}
			}
		}
	}
	return false;
}

/* returns true if $port is a valid port number or an alias thereof */
function is_port_or_alias($port) {
	return (is_port($port) || is_portalias($port));
}

/* returns true if $port is a valid TCP/UDP port number or range ("<port>:<port>") or an alias thereof */
function is_port_or_range_or_alias($port) {
	return (is_port($port) || is_portrange($port) || is_portalias($port));
}

/* create ranges of sequential port numbers (200:215) and remove duplicates */
function group_ports($ports, $kflc = false) {
	if (!is_array($ports) || empty($ports)) {
		return;
	}

	$uniq = array();
	$comments = array();
	foreach ($ports as $port) {
		if (($kflc) && (strpos($port, '#') === 0)) {	// Keep Full Line Comments (lines beginning with #).
			$comments[] = $port;
		} else if (is_portrange($port)) {
			list($begin, $end) = explode(":", $port);
			if ($begin > $end) {
				$aux = $begin;
				$begin = $end;
				$end = $aux;
			}
			for ($i = $begin; $i <= $end; $i++) {
				if (!in_array($i, $uniq)) {
					$uniq[] = $i;
				}
			}
		} else if (is_port($port)) {
			if (!in_array($port, $uniq)) {
				$uniq[] = $port;
			}
		}
	}
	sort($uniq, SORT_NUMERIC);

	$result = array();
	foreach ($uniq as $idx => $port) {
		if ($idx == 0) {
			$result[] = $port;
			continue;
		}

		$last = end($result);
		if (is_portrange($last)) {
			list($begin, $end) = explode(":", $last);
		} else {
			$begin = $end = $last;
		}

		if ($port == ($end+1)) {
			$end++;
			$result[count($result)-1] = "{$begin}:{$end}";
		} else {
			$result[] = $port;
		}
	}

	return array_merge($comments, $result);
}

/* returns true if $val is a valid shaper bandwidth value */
function is_valid_shaperbw($val) {
	return (preg_match("/^(\d+(?:\.\d+)?)([MKG]?b|%)$/", $val));
}

/* returns true if $test is in the range between $start and $end */
function is_inrange_v4($test, $start, $end) {
	if (!is_ipaddrv4($test) || !is_ipaddrv4($start) || !is_ipaddrv4($end)) {
		return false;
	}

	if (ip2ulong($test) <= ip2ulong($end) &&
	    ip2ulong($test) >= ip2ulong($start)) {
		return true;
	}

	return false;
}

/* returns true if $test is in the range between $start and $end */
function is_inrange_v6($test, $start, $end) {
	if (!is_ipaddrv6($test) || !is_ipaddrv6($start) || !is_ipaddrv6($end)) {
		return false;
	}

	if (inet_pton($test) <= inet_pton($end) &&
	    inet_pton($test) >= inet_pton($start)) {
		return true;
	}

	return false;
}

/* returns true if $test is in the range between $start and $end */
function is_inrange($test, $start, $end) {
	return is_ipaddrv6($test) ? is_inrange_v6($test, $start, $end) : is_inrange_v4($test, $start, $end);
}

function build_vip_list($fif, $family = "all") {
	$list = array('address' => gettext('Interface Address'));

	$viplist = get_configured_vip_list($family);
	foreach ($viplist as $vip => $address) {
		if ($fif == get_configured_vip_interface($vip)) {
			$list[$vip] = "$address";
			if (get_vip_descr($address)) {
				$list[$vip] .= " (". get_vip_descr($address) .")";
			}
		}
	}

	return($list);
}

function get_configured_vip_list($family = 'all', $type = VIP_ALL) {
	global $config;

	$list = array();
	if (!is_array($config['virtualip']) ||
	    !is_array($config['virtualip']['vip']) ||
	    empty($config['virtualip']['vip'])) {
		return ($list);
	}

	$viparr = &$config['virtualip']['vip'];
	foreach ($viparr as $vip) {

		if ($type == VIP_CARP) {
			if ($vip['mode'] != "carp")
				continue;
		} elseif ($type == VIP_IPALIAS) {
			if ($vip['mode'] != "ipalias")
				continue;
		} else {
			if ($vip['mode'] != "carp" && $vip['mode'] != "ipalias")
				continue;
		}

		if ($family == 'all' ||
		    ($family == 'inet' && is_ipaddrv4($vip['subnet'])) ||
		    ($family == 'inet6' && is_ipaddrv6($vip['subnet']))) {
			$list["_vip{$vip['uniqid']}"] = $vip['subnet'];
		}
	}
	return ($list);
}

function get_configured_vip($vipinterface = '') {

	return (get_configured_vip_detail($vipinterface, 'all', 'vip'));
}

function get_configured_vip_interface($vipinterface = '') {

	return (get_configured_vip_detail($vipinterface, 'all', 'iface'));
}

function get_configured_vip_ipv4($vipinterface = '') {

	return (get_configured_vip_detail($vipinterface, 'inet', 'ip'));
}

function get_configured_vip_ipv6($vipinterface = '') {

	return (get_configured_vip_detail($vipinterface, 'inet6', 'ip'));
}

function get_configured_vip_subnetv4($vipinterface = '') {

	return (get_configured_vip_detail($vipinterface, 'inet', 'subnet'));
}

function get_configured_vip_subnetv6($vipinterface = '') {

	return (get_configured_vip_detail($vipinterface, 'inet6', 'subnet'));
}

function get_configured_vip_detail($vipinterface = '', $family = 'inet', $what = 'ip') {
	global $config;

	if (empty($vipinterface) ||
	    !is_array($config['virtualip']) ||
	    !is_array($config['virtualip']['vip']) ||
	    empty($config['virtualip']['vip'])) {
		return (NULL);
	}

	$viparr = &$config['virtualip']['vip'];
	foreach ($viparr as $vip) {
		if ($vip['mode'] != "carp" && $vip['mode'] != "ipalias") {
			continue;
		}

		if ($vipinterface != "_vip{$vip['uniqid']}") {
			continue;
		}

		switch ($what) {
			case 'subnet':
				if ($family == 'inet' && is_ipaddrv4($vip['subnet']))
					return ($vip['subnet_bits']);
				else if ($family == 'inet6' && is_ipaddrv6($vip['subnet']))
					return ($vip['subnet_bits']);
				break;
			case 'iface':
				return ($vip['interface']);
				break;
			case 'vip':
				return ($vip);
				break;
			case 'ip':
			default:
				if ($family == 'inet' && is_ipaddrv4($vip['subnet'])) {
					return ($vip['subnet']);
				} else if ($family == 'inet6' && is_ipaddrv6($vip['subnet'])) {
					return ($vip['subnet']);
				}
				break;
		}
		break;
	}

	return (NULL);
}

/* comparison function for sorting by the order in which interfaces are normally created */
function compare_interface_friendly_names($a, $b) {
	if ($a == $b) {
		return 0;
	} else if ($a == 'wan') {
		return -1;
	} else if ($b == 'wan') {
		return 1;
	} else if ($a == 'lan') {
		return -1;
	} else if ($b == 'lan') {
		return 1;
	}

	return strnatcmp($a, $b);
}

/* return the configured interfaces list. */
function get_configured_interface_list($withdisabled = false) {
	global $config;

	$iflist = array();

	/* if list */
	foreach ($config['interfaces'] as $if => $ifdetail) {
		if (isset($ifdetail['enable']) || $withdisabled == true) {
			$iflist[$if] = $if;
		}
	}

	return $iflist;
}

/* return the configured interfaces list. */
function get_configured_interface_list_by_realif($withdisabled = false) {
	global $config;

	$iflist = array();

	/* if list */
	foreach ($config['interfaces'] as $if => $ifdetail) {
		if (isset($ifdetail['enable']) || $withdisabled == true) {
			$tmpif = get_real_interface($if);
			if (!empty($tmpif)) {
				$iflist[$tmpif] = $if;
			}
		}
	}

	return $iflist;
}

/* return the configured interfaces list with their description. */
function get_configured_interface_with_descr($withdisabled = false) {
	global $config, $user_settings;

	$iflist = array();

	/* if list */
	foreach ($config['interfaces'] as $if => $ifdetail) {
		if (isset($ifdetail['enable']) || $withdisabled == true) {
			if (empty($ifdetail['descr'])) {
				$iflist[$if] = strtoupper($if);
			} else {
				$iflist[$if] = strtoupper($ifdetail['descr']);
			}
		}
	}

	if ($user_settings['webgui']['interfacessort']) {
		asort($iflist);
	}

	return $iflist;
}

/*
 *   get_configured_ip_addresses() - Return a list of all configured
 *   IPv4 addresses.
 *
 */
function get_configured_ip_addresses() {
	global $config;

	if (!function_exists('get_interface_ip')) {
		require_once("interfaces.inc");
	}
	$ip_array = array();
	$interfaces = get_configured_interface_list();
	if (is_array($interfaces)) {
		foreach ($interfaces as $int) {
			$ipaddr = get_interface_ip($int);
			$ip_array[$int] = $ipaddr;
		}
	}
	$interfaces = get_configured_vip_list('inet');
	if (is_array($interfaces)) {
		foreach ($interfaces as $int => $ipaddr) {
			$ip_array[$int] = $ipaddr;
		}
	}

	/* pppoe server */
	if (is_array($config['pppoes']) && is_array($config['pppoes']['pppoe'])) {
		foreach ($config['pppoes']['pppoe'] as $pppoe) {
			if ($pppoe['mode'] == "server") {
				if (is_ipaddr($pppoe['localip'])) {
					$int = "poes". $pppoe['pppoeid'];
					$ip_array[$int] = $pppoe['localip'];
				}
			}
		}
	}

	return $ip_array;
}

/*
 *   get_configured_ipv6_addresses() - Return a list of all configured
 *   IPv6 addresses.
 *
 */
function get_configured_ipv6_addresses($linklocal_fallback = false) {
	require_once("interfaces.inc");
	$ipv6_array = array();
	$interfaces = get_configured_interface_list();
	if (is_array($interfaces)) {
		foreach ($interfaces as $int) {
			$ipaddrv6 = text_to_compressed_ip6(get_interface_ipv6($int, false, $linklocal_fallback));
			$ipv6_array[$int] = $ipaddrv6;
		}
	}
	$interfaces = get_configured_vip_list('inet6');
	if (is_array($interfaces)) {
		foreach ($interfaces as $int => $ipaddrv6) {
			$ipv6_array[$int] = text_to_compressed_ip6($ipaddrv6);
		}
	}
	return $ipv6_array;
}

/*
 *   get_interface_list() - Return a list of all physical interfaces
 *   along with MAC and status.
 *
 *   $mode = "active" - use ifconfig -lu
 *           "media"  - use ifconfig to check physical connection
 *			status (much slower)
 */
function get_interface_list($mode = "active", $keyby = "physical", $vfaces = "") {
	global $config;
	$upints = array();
	/* get a list of virtual interface types */
	if (!$vfaces) {
		$vfaces = array(
				'bridge',
				'ppp',
				'pppoe',
				'poes',
				'pptp',
				'l2tp',
				'l2tps',
				'sl',
				'gif',
				'gre',
				'faith',
				'lo',
				'ng',
				'_vlan',
				'_wlan',
				'pflog',
				'plip',
				'pfsync',
				'enc',
				'tun',
				'lagg',
				'vip',
				'ipfw'
		);
	} else {
		$vfaces = array(
				'bridge',
				'poes',
				'sl',
				'faith',
				'lo',
				'ng',
				'_vlan',
				'_wlan',
				'pflog',
				'plip',
				'pfsync',
				'enc',
				'tun',
				'lagg',
				'vip',
				'ipfw',
				'l2tps'
		);
	}
	switch ($mode) {
		case "active":
			$upints = pfSense_interface_listget(IFF_UP);
			break;
		case "media":
			$intlist = pfSense_interface_listget();
			$ifconfig = "";
			exec("/sbin/ifconfig -a", $ifconfig);
			$regexp = '/(' . implode('|', $intlist) . '):\s/';
			$ifstatus = preg_grep('/status:/', $ifconfig);
			foreach ($ifstatus as $status) {
				$int = array_shift($intlist);
				if (stristr($status, "active")) {
					$upints[] = $int;
				}
			}
			break;
		default:
			$upints = pfSense_interface_listget();
			break;
	}
	/* build interface list with netstat */
	$linkinfo = "";
	exec("/usr/bin/netstat -inW -f link | awk '{ print $1, $4 }'", $linkinfo);
	array_shift($linkinfo);
	/* build ip address list with netstat */
	$ipinfo = "";
	exec("/usr/bin/netstat -inW -f inet | awk '{ print $1, $4 }'", $ipinfo);
	array_shift($ipinfo);
	foreach ($linkinfo as $link) {
		$friendly = "";
		$alink = explode(" ", $link);
		$ifname = rtrim(trim($alink[0]), '*');
		/* trim out all numbers before checking for vfaces */
		if (!in_array(array_shift(preg_split('/(\d-)*\d$/', $ifname)), $vfaces) &&
		    interface_is_vlan($ifname) == NULL &&
		    interface_is_qinq($ifname) == NULL &&
		    !stristr($ifname, "_wlan") &&
		    !stristr($ifname, "_stf")) {
			$toput = array(
					"mac" => trim($alink[1]),
					"up" => in_array($ifname, $upints)
				);
			foreach ($ipinfo as $ip) {
				$aip = explode(" ", $ip);
				if ($aip[0] == $ifname) {
					$toput['ipaddr'] = $aip[1];
				}
			}
			if (is_array($config['interfaces'])) {
				foreach ($config['interfaces'] as $name => $int) {
					if ($int['if'] == $ifname) {
						$friendly = $name;
					}
				}
			}
			switch ($keyby) {
			case "physical":
				if ($friendly != "") {
					$toput['friendly'] = $friendly;
				}
				$dmesg_arr = array();
				exec("/sbin/dmesg |grep $ifname | head -n1", $dmesg_arr);
				preg_match_all("/<(.*?)>/i", $dmesg_arr[0], $dmesg);
				$toput['dmesg'] = $dmesg[1][0];
				$iflist[$ifname] = $toput;
				break;
			case "ppp":

			case "friendly":
				if ($friendly != "") {
					$toput['if'] = $ifname;
					$iflist[$friendly] = $toput;
				}
				break;
			}
		}
	}
	return $iflist;
}

function get_lagg_interface_list() {
	global $config;

	$plist = array();
	if (isset($config['laggs']['lagg']) && is_array($config['laggs']['lagg'])) {
		foreach ($config['laggs']['lagg'] as $lagg) {
			$lagg['mac'] = get_interface_mac($lagg['laggif']);
			$lagg['islagg'] = true;
			$plist[$lagg['laggif']] = $lagg;
		}
	}

	return ($plist);
}

/****f* util/log_error
* NAME
*   log_error  - Sends a string to syslog.
* INPUTS
*   $error     - string containing the syslog message.
* RESULT
*   null
******/
function log_error($error) {
	global $g;
	$page = $_SERVER['SCRIPT_NAME'];
	if (empty($page)) {
		$files = get_included_files();
		$page = basename($files[0]);
	}
	syslog(LOG_ERR, "$page: $error");
	if ($g['debug']) {
		syslog(LOG_WARNING, var_dump(debug_backtrace()));
	}
	return;
}

/****f* util/log_auth
* NAME
*   log_auth   - Sends a string to syslog as LOG_AUTH facility
* INPUTS
*   $error     - string containing the syslog message.
* RESULT
*   null
******/
function log_auth($error) {
	global $g;
	$page = $_SERVER['SCRIPT_NAME'];
	syslog(LOG_AUTH, "$page: $error");
	if ($g['debug']) {
		syslog(LOG_WARNING, var_dump(debug_backtrace()));
	}
	return;
}

/****f* util/exec_command
 * NAME
 *   exec_command - Execute a command and return a string of the result.
 * INPUTS
 *   $command   - String of the command to be executed.
 * RESULT
 *   String containing the command's result.
 * NOTES
 *   This function returns the command's stdout and stderr.
 ******/
function exec_command($command) {
	$output = array();
	exec($command . ' 2>&1', $output);
	return(implode("\n", $output));
}

/* wrapper for exec()
   Executes in background or foreground.
   For background execution, returns PID of background process to allow calling code control */
function mwexec($command, $nologentry = false, $clearsigmask = false, $background = false) {
	global $g;
	$retval = 0;

	if ($g['debug']) {
		if (!$_SERVER['REMOTE_ADDR']) {
			echo "mwexec(): $command" . ($background ? " [BG]":"") . "\n";
		}
	}
	if ($clearsigmask) {
		$oldset = array();
		pcntl_sigprocmask(SIG_SETMASK, array(), $oldset);
	}

	if ($background) {
		// start background process and return PID
		$retval = exec("/usr/bin/nohup $command > /dev/null 2>&1 & echo $!");
	} else {
		// run in foreground, and (optionally) log if nonzero return
		$outputarray = array();
		exec("$command 2>&1", $outputarray, $retval);
		if (($retval <> 0) && (!$nologentry || isset($config['system']['developerspew']))) {
			log_error(sprintf(gettext("The command '%1\$s' returned exit code '%2\$d', the output was '%3\$s' "), $command, $retval, implode(" ", $outputarray)));
		}
	}

	if ($clearsigmask) {
		pcntl_sigprocmask(SIG_SETMASK, $oldset);
	}

	return $retval;
}

/* wrapper for exec() in background */
function mwexec_bg($command, $clearsigmask = false) {
	return mwexec($command, false, $clearsigmask, true);
}

/*
 * Unlink a file, or pattern-match of a file, if it exists
 *
 * If the file/path contains glob() compatible wildcards, all matching files
 * will be unlinked.
 * Any warning/errors are suppressed (e.g. no matching files to delete)
 * If there are matching file(s) and they were all unlinked OK, then return
 * true.  Otherwise return false (the requested file(s) did not exist, or
 * could not be deleted), this allows the caller to know if they were the one
 * to successfully delete the file(s).
 */
function unlink_if_exists($fn) {
	$to_do = glob($fn);
	if (is_array($to_do) && count($to_do) > 0) {
		// Returns an array of boolean indicating if each unlink worked
		$results = @array_map("unlink", $to_do);
		// If there is no false in the array, then all went well
		$result = !in_array(false, $results, true);
	} else {
		$result = @unlink($fn);
	}
	return $result;
}

/* make a global alias table (for faster lookups) */
function alias_make_table() {
	global $aliastable, $config;

	$aliastable = array();

	init_config_arr(array('aliases', 'alias'));
	foreach ($config['aliases']['alias'] as $alias) {
		if ($alias['name']) {
			$aliastable[$alias['name']] = $alias['address'];
		}
	}
}

/* check if an alias exists */
function is_alias($name) {
	global $aliastable;

	return isset($aliastable[$name]);
}

function alias_get_type($name) {
	global $config;

	if (is_array($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $alias) {
			if ($name == $alias['name']) {
				return $alias['type'];
			}
		}
	}

	return "";
}

/* expand a host or network alias, if necessary */
function alias_expand($name) {
	global $config, $aliastable;
	$urltable_prefix = "/var/db/aliastables/";
	$urltable_filename = $urltable_prefix . $name . ".txt";

	if (isset($aliastable[$name])) {
		// alias names cannot be strictly numeric. redmine #4289
		if (is_numericint($name)) {
			return null;
		}
		/*
		 * make sure if it's a ports alias, it actually exists.
		 * redmine #5845
		 */
		foreach ($config['aliases']['alias'] as $alias) {
			if ($alias['name'] == $name) {
				if ($alias['type'] == "urltable_ports") {
					if (is_URL($alias['url']) &&
					    file_exists($urltable_filename) &&
					    !empty(trim(file_get_contents($urltable_filename)))) {
						return "\${$name}";
					} else {
						return null;
					}
				}
			}
		}
		return "\${$name}";
	} else if (is_ipaddr($name) || is_subnet($name) ||
	    is_port_or_range($name)) {
		return "{$name}";
	} else {
		return null;
	}
}

function alias_expand_urltable($name) {
	global $config;
	$urltable_prefix = "/var/db/aliastables/";
	$urltable_filename = $urltable_prefix . $name . ".txt";

	if (!is_array($config['aliases']['alias'])) {
		return null;
	}

	foreach ($config['aliases']['alias'] as $alias) {
		if (!preg_match("/urltable/i", $alias['type']) ||
		    ($alias['name'] != $name)) {
			continue;
		}

		if (is_URL($alias["url"]) && file_exists($urltable_filename)) {
			if (!filesize($urltable_filename)) {
				// file exists, but is empty, try to sync
				send_event("service sync alias {$name}");
			}
			return $urltable_filename;
		} else {
			send_event("service sync alias {$name}");
			break;
		}
	}
	return null;
}

/* obtain MAC address given an IP address by looking at the ARP/NDP table */
function arp_get_mac_by_ip($ip, $do_ping = true) {
	unset($macaddr);
	$retval = 1;
	switch (is_ipaddr($ip)) {
		case 4:
			if ($do_ping === true) {
				mwexec("/sbin/ping -c 1 -t 1 " . escapeshellarg($ip), true);
			}
			$macaddr = exec("/usr/sbin/arp -n " . escapeshellarg($ip) . " | /usr/bin/awk '{print $4}'", $output, $retval);
			break;
		case 6:
			if ($do_ping === true) {
				mwexec("/sbin/ping6 -c 1 -X 1 " . escapeshellarg($ip), true);
			}
			$macaddr = exec("/usr/sbin/ndp -n " . escapeshellarg($ip) . " | /usr/bin/awk '{print $2}'", $output, $retval);
			break;
	}
	if ($retval == 0 && is_macaddr($macaddr)) {
		return $macaddr;
	} else {
		return false;
	}
}

/* return a fieldname that is safe for xml usage */
function xml_safe_fieldname($fieldname) {
	$replace = array(
	    '/', '-', ' ', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')',
	    '_', '+', '=', '{', '}', '[', ']', '|', '/', '<', '>', '?',
	    ':', ',', '.', '\'', '\\'
	);
	return strtolower(str_replace($replace, "", $fieldname));
}

function mac_format($clientmac) {
	global $config, $cpzone;

	$mac = explode(":", $clientmac);
	$mac_format = $cpzone ? $config['captiveportal'][$cpzone]['radmac_format'] : false;

	switch ($mac_format) {
		case 'singledash':
			return "$mac[0]$mac[1]$mac[2]-$mac[3]$mac[4]$mac[5]";

		case 'ietf':
			return "$mac[0]-$mac[1]-$mac[2]-$mac[3]-$mac[4]-$mac[5]";

		case 'cisco':
			return "$mac[0]$mac[1].$mac[2]$mac[3].$mac[4]$mac[5]";

		case 'unformatted':
			return "$mac[0]$mac[1]$mac[2]$mac[3]$mac[4]$mac[5]";

		default:
			return $clientmac;
	}
}

function resolve_retry($hostname, $protocol = 'inet') {
	$retries = 50;
	$numrecords = 1;
	$recresult = array();
	$returnres = array();

	switch ($protocol) {
		case 'any':
			$checkproto = 'is_ipaddr';
			$dnsproto = DNS_ANY;
			$dnstype = array('A', 'AAAA');
			break;
		case 'inet6':
			$checkproto = 'is_ipaddrv6';
			$dnsproto = DNS_AAAA;
			$dnstype = array('AAAA');
			break;
		case 'inet': 
		default:
			$checkproto = 'is_ipaddrv4';
			$dnsproto = DNS_A;
			$dnstype = array('A');
			break;
	}

	for ($i = 0; $i < $retries; $i++) {
		if ($checkproto($hostname)) {
			return $hostname;
		}

		$dnsresult = @dns_get_record($hostname, $dnsproto);

		if (!empty($dnsresult)) {
			foreach ($dnsresult as $dnsrec => $ip) {
				if (is_array($ip)) {
					if (in_array($ip['type'], $dnstype)) {
						if ($checkproto($ip['ip'])) { 
							$recresult[] = $ip['ip'];
						}

						if ($checkproto($ip['ipv6'])) { 
							$recresult[] = $ip['ipv6'];
						}
					}
				}
			}
		}

		// Return on success
		if (!empty($recresult)) {
			if ($numrecords == 1) {
				return $recresult[0];
			} else {
				return array_slice($recresult, 0, $numrecords);
			}
		}

		usleep(100000);
	}

	return false;
}

function format_bytes($bytes) {
	if ($bytes >= 1099511627776) {
		return sprintf("%.2f TiB", $bytes/1099511627776);
	} else if ($bytes >= 1073741824) {
		return sprintf("%.2f GiB", $bytes/1073741824);
	} else if ($bytes >= 1048576) {
		return sprintf("%.2f MiB", $bytes/1048576);
	} else if ($bytes >= 1024) {
		return sprintf("%.0f KiB", $bytes/1024);
	} else {
		return sprintf("%d B", $bytes);
	}
}

function format_number($num, $precision = 3) {
	$units = array('', 'K', 'M', 'G', 'T');

	$i = 0;
	while ($num > 1000 && $i < count($units)) {
		$num /= 1000;
		$i++;
	}
	$num = round($num, $precision);

	return ("$num {$units[$i]}");
}


function unformat_number($formated_num) {
	$num = strtoupper($formated_num);
    
	if ( strpos($num,"T") !== false ) {
		$num = str_replace("T","",$num) * 1000 * 1000 * 1000 * 1000;
	} else if ( strpos($num,"G") !== false ) {
		$num = str_replace("G","",$num) * 1000 * 1000 * 1000;
	} else if ( strpos($num,"M") !== false ) {
		$num = str_replace("M","",$num) * 1000 * 1000;
	} else if ( strpos($num,"K") !== false ) {
		$num = str_replace("K","",$num) * 1000;
	}
    
	return $num;
}

function update_filter_reload_status($text, $new=false) {
	global $g;

	if ($new) {
		file_put_contents("{$g['varrun_path']}/filter_reload_status", $text  . PHP_EOL);
	} else {
		file_put_contents("{$g['varrun_path']}/filter_reload_status", $text  . PHP_EOL, FILE_APPEND);
	}
}

/****** util/return_dir_as_array
 * NAME
 *   return_dir_as_array - Return a directory's contents as an array.
 * INPUTS
 *   $dir          - string containing the path to the desired directory.
 *   $filter_regex - string containing a regular expression to filter file names. Default empty.
 * RESULT
 *   $dir_array - array containing the directory's contents. This array will be empty if the path specified is invalid.
 ******/
function return_dir_as_array($dir, $filter_regex = '') {
	$dir_array = array();
	if (is_dir($dir)) {
		if ($dh = opendir($dir)) {
			while (($file = readdir($dh)) !== false) {
				if (($file == ".") || ($file == "..")) {
					continue;
				}

				if (empty($filter_regex) || preg_match($filter_regex, $file)) {
					array_push($dir_array, $file);
				}
			}
			closedir($dh);
		}
	}
	return $dir_array;
}

function run_plugins($directory) {
	global $config, $g;

	/* process packager manager custom rules */
	$files = return_dir_as_array($directory);
	if (is_array($files)) {
		foreach ($files as $file) {
			if (stristr($file, ".sh") == true) {
				mwexec($directory . $file . " start");
			} else if (!is_dir($directory . "/" . $file) && stristr($file, ".inc")) {
				require_once($directory . "/" . $file);
			}
		}
	}
}

/*
 *    safe_mkdir($path, $mode = 0755)
 *    create directory if it doesn't already exist and isn't a file!
 */
function safe_mkdir($path, $mode = 0755) {
	global $g;

	if (!is_file($path) && !is_dir($path)) {
		return @mkdir($path, $mode, true);
	} else {
		return false;
	}
}

/*
 * get_sysctl($names)
 * Get values of sysctl OID's listed in $names (accepts an array or a single
 * name) and return an array of key/value pairs set for those that exist
 */
function get_sysctl($names) {
	if (empty($names)) {
		return array();
	}

	if (is_array($names)) {
		$name_list = array();
		foreach ($names as $name) {
			$name_list[] = escapeshellarg($name);
		}
	} else {
		$name_list = array(escapeshellarg($names));
	}

	exec("/sbin/sysctl -iq " . implode(" ", $name_list), $output);
	$values = array();
	foreach ($output as $line) {
		$line = explode(": ", $line, 2);
		if (count($line) == 2) {
			$values[$line[0]] = $line[1];
		}
	}

	return $values;
}

/*
 * get_single_sysctl($name)
 * Wrapper for get_sysctl() to simplify read of a single sysctl value
 * return the value for sysctl $name or empty string if it doesn't exist
 */
function get_single_sysctl($name) {
	if (empty($name)) {
		return "";
	}

	$value = get_sysctl($name);
	if (empty($value) || !isset($value[$name])) {
		return "";
	}

	return $value[$name];
}

/*
 * set_sysctl($value_list)
 * Set sysctl OID's listed as key/value pairs and return
 * an array with keys set for those that succeeded
 */
function set_sysctl($values) {
	if (empty($values)) {
		return array();
	}

	$value_list = array();
	foreach ($values as $key => $value) {
		$value_list[] = escapeshellarg($key) . "=" . escapeshellarg($value);
	}

	exec("/sbin/sysctl -iq " . implode(" ", $value_list), $output, $success);

	/* Retry individually if failed (one or more read-only) */
	if ($success <> 0 && count($value_list) > 1) {
		foreach ($value_list as $value) {
			exec("/sbin/sysctl -iq " . $value, $output);
		}
	}

	$ret = array();
	foreach ($output as $line) {
		$line = explode(": ", $line, 2);
		if (count($line) == 2) {
			$ret[$line[0]] = true;
		}
	}

	return $ret;
}

/*
 * set_single_sysctl($name, $value)
 * Wrapper to set_sysctl() to make it simple to set only one sysctl
 * returns boolean meaning if it succeeded
 */
function set_single_sysctl($name, $value) {
	if (empty($name)) {
		return false;
	}

	$result = set_sysctl(array($name => $value));

	if (!isset($result[$name]) || $result[$name] != $value) {
		return false;
	}

	return true;
}

/*
 *     get_memory()
 *     returns an array listing the amount of
 *     memory installed in the hardware
 *     [0] net memory available for the OS (FreeBSD) after some is taken by BIOS, video or whatever - e.g. 235 MBytes
 *     [1] real (actual) memory of the system, should be the size of the RAM card/s - e.g. 256 MBytes
 */
function get_memory() {
	$physmem = get_single_sysctl("hw.physmem");
	$realmem = get_single_sysctl("hw.realmem");
	/* convert from bytes to megabytes */
	return array(($physmem/1048576), ($realmem/1048576));
}

function mute_kernel_msgs() {
	global $g, $config;

	if ($config['system']['enableserial']) {
		return;
	}
	exec("/sbin/conscontrol mute on");
}

function unmute_kernel_msgs() {
	global $g;

	exec("/sbin/conscontrol mute off");
}

function start_devd() {
	global $g;

	/* Generate hints for the kernel loader. */
	$module_paths = explode(";", get_single_sysctl("kern.module_path"));
	foreach ($module_paths as $id => $path) {
		if (!is_dir($path) || file_exists("{$path}/linker.hints")) {
			continue;
		}
		if (($files = scandir($path)) == false) {
			continue;
		}
		$found = false;
		foreach ($files as $id => $file) {
			if (strlen($file) > 3 &&
			    strcasecmp(substr($file, -3), ".ko") == 0) {
				$found = true;
				break;
			}
		}
		if ($found == false) {
			continue;
		}
		$_gb = exec("/usr/sbin/kldxref $path");
		unset($_gb);
	}

	/* Use the undocumented -q options of devd to quiet its log spamming */
	$_gb = exec("/sbin/devd -q -f /etc/{$g['product_name']}-devd.conf");
	sleep(1);
	unset($_gb);
}

function is_interface_vlan_mismatch() {
	global $config, $g;

	if (is_array($config['vlans']['vlan'])) {
		foreach ($config['vlans']['vlan'] as $vlan) {
			if (substr($vlan['if'], 0, 4) == "lagg") {
				return false;
			}
			if (does_interface_exist($vlan['if']) == false) {
				return true;
			}
		}
	}

	return false;
}

function is_interface_mismatch() {
	global $config, $g;

	$do_assign = false;
	$i = 0;
	$missing_interfaces = array();
	if (is_array($config['interfaces'])) {
		foreach ($config['interfaces'] as $ifname => $ifcfg) {
			if (interface_is_vlan($ifcfg['if']) != NULL ||
			    interface_is_qinq($ifcfg['if']) != NULL ||
			    preg_match("/^enc|^cua|^tun|^tap|^l2tp|^pptp|^ppp|^ovpn|^ipsec|^gif|^gre|^lagg|^bridge|vlan|_wlan|_\d{0,4}_\d{0,4}$/i", $ifcfg['if'])) {
				// Do not check these interfaces.
				$i++;
				continue;
			} else if (does_interface_exist($ifcfg['if']) == false) {
				$missing_interfaces[] = $ifcfg['if'];
				$do_assign = true;
			} else {
				$i++;
			}
		}
	}

	if (file_exists("{$g['tmp_path']}/assign_complete")) {
		$do_assign = false;
	}

	if (!empty($missing_interfaces) && $do_assign) {
		file_put_contents("{$g['tmp_path']}/missing_interfaces", implode(' ', $missing_interfaces));
	} else {
		@unlink("{$g['tmp_path']}/missing_interfaces");
	}

	return $do_assign;
}

/* sync carp entries to other firewalls */
function carp_sync_client() {
	global $g;
	send_event("filter sync");
}

/****f* util/isAjax
 * NAME
 *   isAjax - reports if the request is driven from prototype
 * INPUTS
 *   none
 * RESULT
 *   true/false
 ******/
function isAjax() {
	return isset ($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
}

/****f* util/timeout
 * NAME
 *   timeout - console input with timeout countdown. Note: erases 2 char of screen for timer. Leave space.
 * INPUTS
 *   optional, seconds to wait before timeout. Default 9 seconds.
 * RESULT
 *   returns 1 char of user input or null if no input.
 ******/
function timeout($timer = 9) {
	while (!isset($key)) {
		if ($timer >= 9) {
			echo chr(8) . chr(8) . ($timer == 9 ? chr(32) : null) . "{$timer}";
		} else {
			echo chr(8). "{$timer}";
		}
		`/bin/stty -icanon min 0 time 25`;
		$key = trim(`KEY=\`dd count=1 2>/dev/null\`; echo \$KEY`);
		`/bin/stty icanon`;
		if ($key == '') {
			unset($key);
		}
		$timer--;
		if ($timer == 0) {
			break;
		}
	}
	return $key;
}

/****f* util/msort
 * NAME
 *   msort - sort array
 * INPUTS
 *   $array to be sorted, field to sort by, direction of sort
 * RESULT
 *   returns newly sorted array
 ******/
function msort($array, $id = "id", $sort_ascending = true) {
	$temp_array = array();
	if (!is_array($array)) {
		return $temp_array;
	}
	while (count($array)>0) {
		$lowest_id = 0;
		$index = 0;
		foreach ($array as $item) {
			if (isset($item[$id])) {
				if ($array[$lowest_id][$id]) {
					if (strtolower($item[$id]) < strtolower($array[$lowest_id][$id])) {
						$lowest_id = $index;
					}
				}
			}
			$index++;
		}
		$temp_array[] = $array[$lowest_id];
		$array = array_merge(array_slice($array, 0, $lowest_id), array_slice($array, $lowest_id + 1));
	}
	if ($sort_ascending) {
		return $temp_array;
	} else {
		return array_reverse($temp_array);
	}
}

/****f* util/is_URL
 * NAME
 *   is_URL
 * INPUTS
 *   string to check
 * RESULT
 *   Returns true if item is a URL
 ******/
function is_URL($url) {
	$match = preg_match("'\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))'", $url);
	if ($match) {
		return true;
	}
	return false;
}

function is_file_included($file = "") {
	$files = get_included_files();
	if (in_array($file, $files)) {
		return true;
	}

	return false;
}

/*
 * Replace a value on a deep associative array using regex
 */
function array_replace_values_recursive($data, $match, $replace) {
	if (empty($data)) {
		return $data;
	}

	if (is_string($data)) {
		$data = preg_replace("/{$match}/", $replace, $data);
	} else if (is_array($data)) {
		foreach ($data as $k => $v) {
			$data[$k] = array_replace_values_recursive($v, $match, $replace);
		}
	}

	return $data;
}

/*
	This function was borrowed from a comment on PHP.net at the following URL:
	http://www.php.net/manual/en/function.array-merge-recursive.php#73843
 */
function array_merge_recursive_unique($array0, $array1) {

	$arrays = func_get_args();
	$remains = $arrays;

	// We walk through each arrays and put value in the results (without
	// considering previous value).
	$result = array();

	// loop available array
	foreach ($arrays as $array) {

		// The first remaining array is $array. We are processing it. So
		// we remove it from remaining arrays.
		array_shift($remains);

		// We don't care non array param, like array_merge since PHP 5.0.
		if (is_array($array)) {
			// Loop values
			foreach ($array as $key => $value) {
				if (is_array($value)) {
					// we gather all remaining arrays that have such key available
					$args = array();
					foreach ($remains as $remain) {
						if (array_key_exists($key, $remain)) {
							array_push($args, $remain[$key]);
						}
					}

					if (count($args) > 2) {
						// put the recursion
						$result[$key] = call_user_func_array(__FUNCTION__, $args);
					} else {
						foreach ($value as $vkey => $vval) {
							if (!is_array($result[$key])) {
								$result[$key] = array();
							}
							$result[$key][$vkey] = $vval;
						}
					}
				} else {
					// simply put the value
					$result[$key] = $value;
				}
			}
		}
	}
	return $result;
}


/*
 * converts a string like "a,b,c,d"
 * into an array like array("a" => "b", "c" => "d")
 */
function explode_assoc($delimiter, $string) {
	$array = explode($delimiter, $string);
	$result = array();
	$numkeys = floor(count($array) / 2);
	for ($i = 0; $i < $numkeys; $i += 1) {
		$result[$array[$i * 2]] = $array[$i * 2 + 1];
	}
	return $result;
}

/*
 * Given a string of text with some delimiter, look for occurrences
 * of some string and replace all of those.
 * $text - the text string (e.g. "abc,defg,x123,ipv4,xyz")
 * $delimiter - the delimiter (e.g. ",")
 * $element - the element to match (e.g. "defg")
 * $replacement - the string to replace it with (e.g. "42")
 * Returns the resulting delimited string (e.g. "abc,42,x123,ipv4,xyz")
 */
function replace_element_in_list($text, $delimiter, $element, $replacement) {
	$textArray = explode($delimiter, $text);
	while (($entry = array_search($element, $textArray)) !== false) {
		$textArray[$entry] = $replacement;
	}
	return implode(',', $textArray);
}

/* Return system's route table */
function route_table() {
	$_gb = exec("/usr/bin/netstat --libxo json -nWr", $rawdata, $rc);

	if ($rc != 0) {
		return array();
	}

	$netstatarr = json_decode(implode(" ", $rawdata), JSON_OBJECT_AS_ARRAY);
	$netstatarr = $netstatarr['statistics']['route-information']
	    ['route-table']['rt-family'];

	$result = array();
	$result['inet'] = array();
	$result['inet6'] = array();
	foreach ($netstatarr as $item) {
		if ($item['address-family'] == 'Internet') {
			$result['inet'] = $item['rt-entry'];
		} else if ($item['address-family'] == 'Internet6') {
			$result['inet6'] = $item['rt-entry'];
		}
	}
	unset($netstatarr);

	return $result;
}

/* Get static route for specific destination */
function route_get($target, $ipprotocol = '', $useroute = false) {
	global $config;

	if (!empty($ipprotocol)) {
		$family = $ipprotocol;
	} else if (is_v4($target)) {
		$family = 'inet';
	} else if (is_v6($target)) {
		$family = 'inet6';
	}

	if (empty($family)) {
		return array();
	}

	if ($useroute) {
		if ($family == 'inet') {
			$inet = '4';
		} else {
			$inet = '6';
		}
		$interface = exec("/sbin/route -n{$inet} get {$target} 2>/dev/null | /usr/bin/awk '/interface:/{print $2}'");
		if (empty($interface)) {
			return array();
		} elseif ($interface == 'lo0') {
			// interface assigned IP address
			foreach ($config['interfaces'] as $intf => $infconf) {
				if ((($inet == '4') && (get_interface_ip($intf) == $target)) ||
				    (($inet == '6') && (get_interface_ipv6($intf) == $target))) {
					$interface = convert_friendly_interface_to_real_interface_name($intf);
					$gateway = $interface;
					break;
				}
			}
		} else {
			$gateway = exec("/sbin/route -n{$inet} get {$target} 2>/dev/null | /usr/bin/awk '/gateway:/{print $2}'");
			if (!$gateway) {
				// non-local gateway
				$gateway = get_interface_mac($interface);
			}
		}
		$result[] = array('gateway' => $gateway, 'interface-name' => $interface);
	} else {
		$rtable = route_table();
		if (empty($rtable)) {
			return array();
		}

		$result = array();
		foreach ($rtable[$family] as $item) {
			if ($item['destination'] == $target ||
			    ip_in_subnet($target, $item['destination'])) {
				$result[] = $item;
			}
		}
	}

	return $result;
}

/* Get default route */
function route_get_default($ipprotocol) {
	if (empty($ipprotocol) || ($ipprotocol != 'inet' &&
	    $ipprotocol != 'inet6')) {
		return '';
	}

	$route = route_get('default', $ipprotocol, true);

	if (empty($route)) {
		return '';
	}

	if (!isset($route[0]['gateway'])) {
		return '';
	}

	return $route[0]['gateway'];
}

/* Delete a static route */
function route_del($target, $ipprotocol = '') {
	global $config;

	if (empty($target)) {
		return;
	}

	if (!empty($ipprotocol) && $ipprotocol != 'inet' &&
	    $ipprotocol != 'inet6') {
		return false;
	}

	$route = route_get($target, $ipprotocol, true);

	if (empty($route)) {
		return;
	}

	$target_prefix = '';
	if (is_subnet($target)) {
		$target_prefix = '-net';
	} else if (is_ipaddr($target)) {
		$target_prefix = '-host';
	}

	if (!empty($ipprotocol)) {
		$target_prefix .= " -{$ipprotocol}";
	} else if (is_v6($target)) {
		$target_prefix .= ' -inet6';
	} else if (is_v4($target)) {
		$target_prefix .= ' -inet';
	}

	foreach ($route as $item) {
		if (substr($item['gateway'], 0, 5) == 'link#') {
			continue;
		}

		if (is_macaddr($item['gateway'])) {
			$gw = '-iface ' . $item['interface-name'];
		} else {
			$gw = $item['gateway'];
		}

		$_gb = exec(escapeshellcmd("/sbin/route del {$target_prefix} " .
		    "{$target} {$gw}"), $output, $rc);

		if (isset($config['system']['route-debug'])) {
			log_error("ROUTING debug: " . microtime() .
			    " - DEL RC={$rc} - {$target} - gw: " . $gw);
			file_put_contents("/dev/console", "\n[" . getmypid() .
			    "] ROUTE DEL: {$target_prefix} {$target} {$gw} " .
			    "result: {$rc}");
		}
	}
}

/*
 * Add static route.  If it already exists, remove it and re-add
 *
 * $target - IP, subnet or 'default'
 * $gw     - gateway address
 * $iface  - Network interface
 * $args   - Extra arguments for /sbin/route
 * $ipprotocol - 'inet' or 'inet6'.  Mandatory when $target == 'default'
 *
 */
function route_add_or_change($target, $gw, $iface = '', $args = '',
    $ipprotocol = '') {
	global $config;

	if (empty($target) || (empty($gw) && empty($iface))) {
		return false;
	}

	if ($target == 'default' && empty($ipprotocol)) {
		return false;
	}

	if (!empty($ipprotocol) && $ipprotocol != 'inet' &&
	    $ipprotocol != 'inet6') {
		return false;
	}

	/* use '-host' for IPv6 /128 routes, see https://redmine.pfsense.org/issues/11594 */
	if (is_subnetv4($target) || (is_subnetv6($target) && (subnet_size($target) > 1))) {
		$target_prefix = '-net';
	} else if (is_ipaddr($target)) {
		$target_prefix = '-host';
	}

	if (!empty($ipprotocol)) {
		$target_prefix .= " -{$ipprotocol}";
	} else if (is_v6($target)) {
		$target_prefix .= ' -inet6';
	} else if (is_v4($target)) {
		$target_prefix .= ' -inet';
	}

	/* If there is another route to the same target, remove it */
	route_del($target, $ipprotocol);

	$params = '';
	if (!empty($iface) && does_interface_exist($iface)) {
		$params .= " -iface {$iface}";
	}
	if (is_ipaddr($gw)) {
		/* set correct linklocal gateway address,
		 * see https://redmine.pfsense.org/issues/11713 
		 * and https://redmine.pfsense.org/issues/11806 */
		if (is_ipaddrv6($gw) && is_linklocal($gw) && empty(get_ll_scope($gw))) {
			$routeget = route_get($gw, 'inet6', true);
			$gw .= "%" . $routeget[0]['interface-name'];
		}
		$params .= " " . $gw;
	}

	if (empty($params)) {
		log_error("route_add_or_change: Invalid gateway {$gw} and/or " .
		    "network interface {$iface}");
		return false;
	}

	$_gb = exec(escapeshellcmd("/sbin/route add {$target_prefix} " .
	    "{$target} {$args} {$params}"), $output, $rc);

	if (isset($config['system']['route-debug'])) {
		log_error("ROUTING debug: " . microtime() .
		    " - ADD RC={$rc} - {$target} {$args}");
		file_put_contents("/dev/console", "\n[" . getmypid() .
		    "] ROUTE ADD: {$target_prefix} {$target} {$args} " .
		    "{$params} result: {$rc}");
	}

	return ($rc == 0);
}

function set_ipv6routes_mtu($interface, $mtu) {
	global $config, $g;

	$ipv6mturoutes = array();
	$if = convert_real_interface_to_friendly_interface_name($interface);
	if (!$config['interfaces'][$if]['ipaddrv6']) {
		return;
	}
	$a_gateways = return_gateways_array();
	$a_staticroutes = get_staticroutes(false, false, true);
	foreach ($a_gateways as $gate) {
		foreach ($a_staticroutes as $sroute) {
			if (($gate['interface'] == $interface) &&
			    ($sroute['gateway'] == $gate['name'])) {
				$tgt = $sroute['network'];
				$gateway = $gate['gateway'];
				$ipv6mturoutes[$tgt] = $gateway;
			}
		}
		if (($gate['interface'] == $interface) &&
		    $gate['isdefaultgw'] && is_ipaddrv6($gate['gateway'])) {
			$tgt = "default";
			$gateway = $gate['gateway'];
			$ipv6mturoutes[$tgt] = $gateway;
		}
	}
	foreach ($ipv6mturoutes as $tgt => $gateway) {
		mwexec("/sbin/route change -6 -mtu " . escapeshellarg($mtu) .
		    " " . escapeshellarg($tgt) . " " .
		    escapeshellarg($gateway));
	}
}

function alias_to_subnets_recursive($name, $returnhostnames = false) {
	global $aliastable;
	$result = array();
	if (!isset($aliastable[$name])) {
		return $result;
	}
	$subnets = preg_split('/\s+/', $aliastable[$name]);
	foreach ($subnets as $net) {
		if (is_alias($net)) {
			$sub = alias_to_subnets_recursive($net, $returnhostnames);
			$result = array_merge($result, $sub);
			continue;
		} elseif (!is_subnet($net)) {
			if (is_ipaddrv4($net)) {
				$net .= "/32";
			} else if (is_ipaddrv6($net)) {
				$net .= "/128";
			} else if ($returnhostnames === false || !is_fqdn($net)) {
				continue;
			}
		}
		$result[] = $net;
	}
	return $result;
}

function get_staticroutes($returnsubnetsonly = false, $returnhostnames = false, $returnenabledroutesonly = false) {
	global $config, $aliastable;

	/* Bail if there are no routes, but return an array always so callers don't have to check. */
	init_config_arr(array('staticroutes', 'route'));
	if (empty($config['staticroutes']['route'])) {
		return array();
	}

	$allstaticroutes = array();
	$allsubnets = array();
	/* Loop through routes and expand aliases as we find them. */
	foreach ($config['staticroutes']['route'] as $route) {
		if ($returnenabledroutesonly && isset($route['disabled'])) {
			continue;
		}

		if (is_alias($route['network'])) {
			foreach (alias_to_subnets_recursive($route['network'], $returnhostnames) as $net) {
				$temproute = $route;
				$temproute['network'] = $net;
				$allstaticroutes[] = $temproute;
				$allsubnets[] = $net;
			}
		} elseif (is_subnet($route['network'])) {
			$allstaticroutes[] = $route;
			$allsubnets[] = $route['network'];
		}
	}
	if ($returnsubnetsonly) {
		return $allsubnets;
	} else {
		return $allstaticroutes;
	}
}

/****f* util/get_alias_list
 * NAME
 *   get_alias_list - Provide a list of aliases.
 * INPUTS
 *   $type          - Optional, can be a string or array specifying what type(s) of aliases you need.
 * RESULT
 *   Array containing list of aliases.
 *   If $type is unspecified, all aliases are returned.
 *   If $type is a string, all aliases of the type specified in $type are returned.
 *   If $type is an array, all aliases of any type specified in any element of $type are returned.
 */
function get_alias_list($type = null) {
	global $config;
	$result = array();
	if ($config['aliases']['alias'] <> "" && is_array($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $alias) {
			if ($type === null) {
				$result[] = $alias['name'];
			} else if (is_array($type)) {
				if (in_array($alias['type'], $type)) {
					$result[] = $alias['name'];
				}
			} else if ($type === $alias['type']) {
				$result[] = $alias['name'];
			}
		}
	}
	return $result;
}

/* returns an array consisting of every element of $haystack that is not equal to $needle. */
function array_exclude($needle, $haystack) {
	$result = array();
	if (is_array($haystack)) {
		foreach ($haystack as $thing) {
			if ($needle !== $thing) {
				$result[] = $thing;
			}
		}
	}
	return $result;
}

/* Define what is preferred, IPv4 or IPv6 */
function prefer_ipv4_or_ipv6() {
	global $config;

	if (isset($config['system']['prefer_ipv4'])) {
		mwexec("/etc/rc.d/ip6addrctl prefer_ipv4");
	} else {
		mwexec("/etc/rc.d/ip6addrctl prefer_ipv6");
	}
}

/* Redirect to page passing parameters via POST */
function post_redirect($page, $params) {
	if (!is_array($params)) {
		return;
	}

	print "<html><body><form action=\"{$page}\" name=\"formredir\" method=\"post\">\n";
	foreach ($params as $key => $value) {
		print "<input type=\"hidden\" name=\"{$key}\" value=\"{$value}\" />\n";
	}
	print "</form>\n";
	print "<script type=\"text/javascript\">\n";
	print "//<![CDATA[\n";
	print "document.formredir.submit();\n";
	print "//]]>\n";
	print "</script>\n";
	print "</body></html>\n";
}

/* Locate disks that can be queried for S.M.A.R.T. data. */
function get_smart_drive_list() {
	/* SMART supports some disks directly, and some controllers directly,
	 * See https://redmine.pfsense.org/issues/9042 */
	$supported_disk_types = array("ad", "da", "ada");
	$supported_controller_types = array("nvme");
	$disk_list = explode(" ", get_single_sysctl("kern.disks"));
	foreach ($disk_list as $id => $disk) {
		// We only want certain kinds of disks for S.M.A.R.T.
		// 1 is a match, 0 is no match, False is any problem processing the regex
		if (preg_match("/^(" . implode("|", $supported_disk_types) . ").*[0-9]{1,2}$/", $disk) !== 1) {
			unset($disk_list[$id]);
			continue;
		}
	}
	foreach ($supported_controller_types as $controller) {
		$devices = glob("/dev/{$controller}*");
		if (!is_array($devices)) {
			continue;
		}
		foreach ($devices as $device) {
			$disk_list[] = basename($device);
		}
	}
	sort($disk_list);
	return $disk_list;
}

// Validate a network address
//	$addr: the address to validate
//	$type: IPV4|IPV6|IPV4V6
//	$label: the label used by the GUI to display this value. Required to compose an error message
//	$err_msg: pointer to the callers error message array so that error messages can be added to it here
//	$alias: are aliases permitted for this address?
// Returns:
//	IPV4 - if $addr is a valid IPv4 address
//	IPV6 - if $addr is a valid IPv6 address
//	ALIAS - if $alias=true and $addr is an alias
//	false - otherwise

function validateipaddr(&$addr, $type, $label, &$err_msg, $alias=false) {
	switch ($type) {
		case IPV4:
			if (is_ipaddrv4($addr)) {
				return IPV4;
			} else if ($alias) {
				if (is_alias($addr)) {
					return ALIAS;
				} else {
					$err_msg[] = sprintf(gettext("%s must be a valid IPv4 address or alias."), $label);
					return false;
				}
			} else {
				$err_msg[] = sprintf(gettext("%s must be a valid IPv4 address."), $label);
				return false;
			}
		break;
		case IPV6:
			if (is_ipaddrv6($addr)) {
				$addr = strtolower($addr);
				return IPV6;
			} else if ($alias) {
				if (is_alias($addr)) {
					return ALIAS;
				} else {
					$err_msg[] = sprintf(gettext("%s must be a valid IPv6 address or alias."), $label);
					return false;
				}
			} else {
				$err_msg[] = sprintf(gettext("%s must be a valid IPv6 address."), $label);
				return false;
			}
		break;
		case IPV4V6:
			if (is_ipaddrv6($addr)) {
				$addr = strtolower($addr);
				return IPV6;
			} else if (is_ipaddrv4($addr)) {
				return IPV4;
			} else if ($alias) {
				if (is_alias($addr)) {
					return ALIAS;
				} else {
					$err_msg[] = sprintf(gettext("%s must be a valid IPv4 or IPv6 address or alias."), $label);
					return false;
				}
			} else {
				$err_msg[] = sprintf(gettext("%s must be a valid IPv4 or IPv6 address."), $label);
				return false;
			}
		break;
	}

	return false;
}

/* From DUID configuration inputs, format a string that looks (more) like the expected raw DUID format:
 * 1) For DUIDs entered as a known DUID type, convert to a hexstring and prepend the DUID number, after having done the following:
 *     a) For DUID-LLT and DUID-EN, convert the time/enterprise ID input to hex and append the link-layer address/identifier input.
 *     b) For DUID-LLT and DUID-LL, prepend a hardware type of 1.
 *     c) For DUID-UUID, remove any "-".
 * 2) Replace any remaining "-" with ":".
 * 3) If any components are input with just a single char (hex digit hopefully), put a "0" in front.
 * 4) The first two components should be a 16-bit integer (little- or big-endian, depending on the current machine type) that
 *    is equal to the number of other components. If not, prepend this as "nn:00" (all pfSense builds are little-endian).
 *    This is convenience, because the DUID reported by dhcp6c in logs does not include this count, which corresponds to the
 *    option-len field of DHCPv6's OPTION_CLIENTID option.
 *
 * The final result should be closer to:
 *
 * "nn:00:00:0n:nn:nn:nn:..."
 *
 * This function does not validate the input. is_duid() will do validation.
 */
function format_duid($duidtype, $duidpt1, $duidpt2=null) {
	if ($duidpt2)
		$duidpt1 = implode(':', str_split(str_pad(dechex($duidpt1), 8, '0', STR_PAD_LEFT), 2)) . ':' . $duidpt2;

	/* Make hexstrings */
	if ($duidtype) {
		switch ($duidtype) {
		/* Add a hardware type to DUID-LLT and DUID-LL; assume Ethernet */
		case 1:
		case 3:
			$duidpt1 = '00:01:' . $duidpt1;
			break;
		/* Remove '-' from given UUID and insert ':' every 2 characters */
		case 4:
			$duidpt1 = implode(':', str_split(str_replace('-', '', $duidpt1), 2));
			break;
		default:
		}
		$duidpt1 = '00:0' . $duidtype . ':' . $duidpt1;
	}

	$values = explode(':', strtolower(str_replace('-', ':', $duidpt1)));

	if (hexdec($values[0]) != count($values) - 2)
		array_unshift($values, dechex(count($values)), '00');

	array_walk($values, function(&$value) {
		$value = str_pad($value, 2, '0', STR_PAD_LEFT);
	});

	return implode(":", $values);
}

/* Returns true if $dhcp6duid is a valid DUID entry.
 * Parse the entry to check for valid length according to known DUID types.
 */
function is_duid($dhcp6duid) {
	$values = explode(":", $dhcp6duid);
	if (hexdec($values[0]) == count($values) - 2) {
		switch (hexdec($values[2] . $values[3])) {
		case 0:
			return false;
			break;
		case 1:
			if (count($values) != 16 || strlen($dhcp6duid) != 47)
				return false;
			break;
		case 3:
			if (count($values) != 12 || strlen($dhcp6duid) != 35)
				return false;
			break;
		case 4:
			if (count($values) != 20 || strlen($dhcp6duid) != 59)
				return false;
			break;
		/* DUID is up to 128 octets; allow 2 octets for type code, 2 more for option-len */
		default:
			if (count($values) > 132 || strlen($dhcp6duid) != count($values) * 3 - 1)
				return false;
		}
	} else
		return false;

	for ($i = 0; $i < count($values); $i++) {
		if (ctype_xdigit($values[$i]) == false)
			return false;
		if (hexdec($values[$i]) < 0 || hexdec($values[$i]) > 255)
			return false;
	}

	return true;
}

/* Write the DHCP6 DUID file */
function write_dhcp6_duid($duidstring) {
	// Create the hex array from the dhcp6duid config entry and write to file
	global $g;

	if(!is_duid($duidstring)) {
		log_error(gettext("Error: attempting to write DUID file - Invalid DUID detected"));
		return false;
	}
	$temp = str_replace(":","",$duidstring);
	$duid_binstring = pack("H*",$temp);
	if ($fd = fopen("{$g['vardb_path']}/dhcp6c_duid", "wb")) {
		fwrite($fd, $duid_binstring);
		fclose($fd);
		return true;
	}
	log_error(gettext("Error: attempting to write DUID file - File write error"));
	return false;
}

/* returns duid string from 'vardb_path']}/dhcp6c_duid' */
function get_duid_from_file() {
	global $g;

	$duid_ASCII = "";
	$count = 0;

	if (file_exists("{$g['vardb_path']}/dhcp6c_duid") &&
	    ($fd = fopen("{$g['vardb_path']}/dhcp6c_duid", "r"))) {
		$fsize = filesize("{$g['vardb_path']}/dhcp6c_duid");
		if ($fsize <= 132) {
			$buffer = fread($fd, $fsize);
			while($count < $fsize) {
				$duid_ASCII .= bin2hex($buffer[$count]);
				$count++;
				if($count < $fsize) {
					$duid_ASCII .= ":";
				}
			}
		}
		fclose($fd);
	}
	//if no file or error with read then the string returns blanked DUID string
	if(!is_duid($duid_ASCII)) {
		return "--:--:--:--:--:--:--:--:--:--:--:--:--:--:--:--";
	}
	return($duid_ASCII);
}

/* Replaces the Mac OS 9 and earlier (\r) and DOS/Windows (\r\n) newlines with the Unix equivalent (\n). */
function unixnewlines($text) {
	return preg_replace('/\r\n?/', "\n", $text);
}

function array_remove_duplicate($array, $field) {
	foreach ($array as $sub) {
		if (isset($sub[$field])) {
			$cmp[] = $sub[$field];
		}
	}
	$unique = array_unique(array_reverse($cmp, true));
	foreach ($unique as $k => $rien) {
		$new[] = $array[$k];
	}
	return $new;
}

function dhcpd_date_adjust_gmt($dt) {
	global $config;

	init_config_arr(array('dhcpd'));

	foreach ($config['dhcpd'] as $dhcpditem) {
		if ($dhcpditem['dhcpleaseinlocaltime'] == "yes") {
			$ts = strtotime($dt . " GMT");
			if ($ts !== false) {
				return strftime("%Y/%m/%d %H:%M:%S", $ts);
			}
		}
	}

	/*
	 * If we did not need to convert to local time or the conversion
	 * failed, just return the input.
	 */
	return $dt;
}

global $supported_image_types;
$supported_image_types = array(
	IMAGETYPE_JPEG,
	IMAGETYPE_PNG,
	IMAGETYPE_GIF,
	IMAGETYPE_WEBP
);

function is_supported_image($image_filename) {
	global $supported_image_types;
	$img_info = getimagesize($image_filename);

	/* If it's not an image, or it isn't in the supported list, return false */
	if (($img_info === false) ||
	    !in_array($img_info[2], array_keys($supported_image_types))) {
		return false;
	} else {
		return $img_info[2];
	}
}

function get_lagg_ports ($laggport) {
	$laggp = array();
	foreach ($laggport as $lgp) {
		list($lpname, $lpinfo) = explode(" ", $lgp);
		preg_match('~<(.+)>~', $lpinfo, $lgportmode);
		if ($lgportmode[1]) {
			$laggp[] = $lpname . " (" . $lgportmode[1] . ")";
		} else {
			$laggp[] = $lpname;
		}
	}
	if ($laggp) {
		return implode(", ", $laggp);
	} else {
		return false;
	}
}

function cisco_to_cidr($addr) {
	if (!is_ipaddr($addr)) {
		throw new Exception('Invalid IP Addr');
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

function parse_cisco_acl_rule($rule, $devname, $dir, $proto) {
	$rule_orig = $rule;
	$rule = explode(" ", $rule);
	$tmprule = "";
	$index = 0;

	if ($rule[$index] == "permit") {
		$startrule = "pass {$dir} quick on {$devname} ";
	} else if ($rule[$index] == "deny") {
		$startrule = "block {$dir} quick on {$devname} ";
	} else {
		return;
	}

	$index++;

	switch ($rule[$index]) {
		case "ip":
			break;
		case "icmp":
			$icmp = ($proto == "inet") ? "icmp" : "ipv6-icmp";
			$tmprule .= "proto {$icmp} ";
			break;
		case "tcp":
		case "udp":
			$tmprule .= "proto {$rule[$index]} ";
			break;
		default:
			syslog(LOG_WARNING, "Error parsing rule {$rule_orig}: Invalid protocol.");
			return;
	}
	$index++;

	/* Source */
	if (trim($rule[$index]) == "host") {
		$index++;
		if ((($proto == 'inet') && (is_ipaddrv4(trim($rule[$index])) || (trim($rule[$index]) == "{clientip}"))) ||
		    (($proto == 'inet6') && (is_ipaddrv6(trim($rule[$index])) || (trim($rule[$index]) == "{clientipv6}")))) {
			if ($GLOBALS['attributes']['framed_ip']) {
				$tmprule .= "from {$GLOBALS['attributes']['framed_ip']} ";
			} else {
				$tmprule .= "from {$rule[$index]} ";
			}
			$index++;
		} else {
			syslog(LOG_WARNING, "Error parsing rule {$rule_orig}: Invalid source host '{$rule[$index]}'.");
			return;
		}
	} elseif (is_subnetv6(trim($rule[$index])) && ($proto == "inet6")) {
		$tmprule .= "from {$rule[$index]} ";
		$index++;
	} elseif (trim($rule[$index]) == "any") {
		$tmprule .= "from any ";
		$index++;
	} else {
		$network = $rule[$index];
		$netmask = $rule[++$index];

		if (is_ipaddrv4($network) && ($proto == "inet")) {
			try {
				$netmask = cisco_to_cidr($netmask);
			} catch(Exception $e) {
				syslog(LOG_WARNING, "Error parsing rule {$rule_orig}: Invalid source netmask '$netmask'.");
				return;
			}
			$tmprule .= "from {$network}/{$netmask} ";
		} else {
			syslog(LOG_WARNING, "Error parsing rule {$rule_orig}: Invalid source network '$network'.");
			return;
		}

		$index++;
	}

	/* Source Operator */
	if (in_array(trim($rule[$index]), array("lt", "gt", "eq", "neq"))) {
		switch(trim($rule[$index])) {
			case "lt":
				$operator = "<";
				break;
			case "gt":
				$operator = ">";
				break;
			case "eq":
				$operator = "=";
				break;
			case "neq":
				$operator = "!=";
				break;
		}

		$port = $rule[++$index];
		if (is_port($port)) {
			$tmprule .= "port {$operator} {$port} ";
		} else {
			syslog(LOG_WARNING, "Error parsing rule {$rule_orig}: Invalid source port: '$port' not a numeric value between 0 and 65535.");
			return;
		}
		$index++;
	} else if (trim($rule[$index]) == "range") {
		$port = array($rule[++$index], $rule[++$index]);
		if (is_port($port[0]) && is_port($port[1])) {
			$tmprule .= "port {$port[0]}:{$port[1]} ";
		} else {
			syslog(LOG_WARNING, "Error parsing rule {$rule_orig}: Invalid source ports: '$port[0]' & '$port[1]' one or both are not a numeric value between 0 and 65535.");
			return;
		}
		$index++;
	}

	/* Destination */
	if (trim($rule[$index]) == "host") {
		$index++;
		if ((($proto == 'inet') && (is_ipaddrv4(trim($rule[$index])) || (trim($rule[$index]) == "{clientip}"))) ||
		    (($proto == 'inet6') && (is_ipaddrv6(trim($rule[$index])) || (trim($rule[$index]) == "{clientipv6}")))) {
			$tmprule .= "to {$rule[$index]} ";
			$index++;
		} else {
			syslog(LOG_WARNING, "Error parsing rule {$rule_orig}: Invalid destination host '{$rule[$index]}'.");
			return;
		}
	} elseif (is_subnetv6(trim($rule[$index])) && ($proto == "inet6")) {
		$tmprule .= "to {$rule[$index]} ";
		$index++;
	} elseif (trim($rule[$index]) == "any") {
		$tmprule .= "to any ";
		$index++;
	} else {
		$network = $rule[$index];
		$netmask = $rule[++$index];

		if (is_ipaddrv4($network) && ($proto == "inet")) {
			try {
				$netmask = cisco_to_cidr($netmask);
			} catch(Exception $e) {
				syslog(LOG_WARNING, "Error parsing rule {$rule_orig}: Invalid destination network '$network'.");
				return;
			}
			$tmprule .= "to {$network}/{$netmask} ";
		} else {
			syslog(LOG_WARNING, "Error parsing rule {$rule_orig}: Invalid destination network '$network'.");
			return;
		}

		$index++;
	}

	/* Destination Operator */
	if (in_array(trim($rule[$index]), array("lt", "gt", "eq", "neq"))) {
		switch(trim($rule[$index])) {
			case "lt":
				$operator = "<";
				break;
			case "gt":
				$operator = ">";
				break;
			case "eq":
				$operator = "=";
				break;
			case "neq":
				$operator = "!=";
				break;
		}

		$port = $rule[++$index];
		if (is_port($port)) {
			$tmprule .= "port {$operator} {$port} ";
		} else {
			syslog(LOG_WARNING, "Error parsing rule {$rule_orig}: Invalid destination port: '$port' not a numeric value between 0 and 65535.");
			return;
		}
		$index++;
	} else if (trim($rule[$index]) == "range") {
		$port = array($rule[++$index], $rule[++$index]);
		if (is_port($port[0]) && is_port($port[1])) {
			$tmprule .= "port {$port[0]}:{$port[1]} ";
		} else {
			syslog(LOG_WARNING, "Error parsing rule {$rule_orig}: Invalid destination ports: '$port[0]' '$port[1]' one or both are not a numeric value between 0 and 65535.");
			return;
		}
		$index++;
	}

	$tmprule = $startrule . $proto . " " . $tmprule;
	return $tmprule;
}

function parse_cisco_acl($attribs, $dev) {
	global $attributes;

	if (!is_array($attribs)) {
		return "";
	}
	$finalrules = "";
	if (is_array($attribs['ciscoavpair'])) {
		$inrules = array('inet' => array(), 'inet6' => array());
		$outrules = array('inet' => array(), 'inet6' => array());
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

			if (strstr($rule[0], "ipv6")) {
				$proto = "inet6";
			} else {
				$proto = "inet";
			}

			$tmprule = parse_cisco_acl_rule($rule[1], $dev, $dir, $proto);

			if ($dir == "in") {
				$inrules[$proto][$rindex] = $tmprule;
			} else if ($dir == "out") {
				$outrules[$proto][$rindex] = $tmprule;
			}
		}


		$state = "";
		foreach (array('inet', 'inet6') as $ip) {
			if (!empty($outrules[$ip])) {
				$state = "no state";
			}
			ksort($inrules[$ip], SORT_NUMERIC);
			foreach ($inrules[$ip] as $inrule) {
				$finalrules .= "{$inrule} {$state}\n";
			}
			if (!empty($outrules[$ip])) {
				ksort($outrules[$ip], SORT_NUMERIC);
				foreach ($outrules[$ip] as $outrule) {
					$finalrules .= "{$outrule} {$state}\n";
				}
			}
		}
	}
	return $finalrules;
}

function alias_idn_to_utf8($alias) {
	if (is_alias($alias)) {
		return $alias;
	} else {
		return idn_to_utf8($alias);
	}
}

function alias_idn_to_ascii($alias) {
	if (is_alias($alias)) {
		return $alias;
	} else {
		return idn_to_ascii($alias);
	}
}

// These funtions were in guiconfig.inc but have been moved here so non GUI processes can use them
function address_to_pconfig($adr, &$padr, &$pmask, &$pnot, &$pbeginport, &$pendport) {
	if (isset($adr['any'])) {
		$padr = "any";
	} else if ($adr['network']) {
		$padr = $adr['network'];
	} else if ($adr['address']) {
		list($padr, $pmask) = explode("/", $adr['address']);
		if (!$pmask) {
			if (is_ipaddrv6($padr)) {
				$pmask = 128;
			} else {
				$pmask = 32;
			}
		}
	}

	if (isset($adr['not'])) {
		$pnot = 1;
	} else {
		$pnot = 0;
	}

	if ($adr['port']) {
		list($pbeginport, $pendport) = explode("-", $adr['port']);
		if (!$pendport) {
			$pendport = $pbeginport;
		}
	} else if (!is_alias($pbeginport) && !is_alias($pendport)) {
		$pbeginport = "any";
		$pendport = "any";
	}
}

function pconfig_to_address(&$adr, $padr, $pmask, $pnot = false, $pbeginport = 0, $pendport = 0) {
	$adr = array();

	if ($padr == "any") {
		$adr['any'] = true;
	} else if (is_specialnet($padr)) {
		$adr['network'] = $padr;
	} else {
		$adr['address'] = $padr;
		if (is_ipaddrv6($padr)) {
			if ($pmask != 128) {
				$adr['address'] .= "/" . $pmask;
			}
		} else {
			if ($pmask != 32) {
				$adr['address'] .= "/" . $pmask;
			}
		}
	}

	if ($pnot) {
		$adr['not'] = true;
	} else {
		unset($adr['not']);
	}

	if (($pbeginport != 0) && ($pbeginport != "any")) {
		if ($pbeginport != $pendport) {
			$adr['port'] = $pbeginport . "-" . $pendport;
		} else {
			$adr['port'] = $pbeginport;
		}
	}

	/*
	 * If the port is still unset, then it must not be numeric, but could
	 * be an alias or a well-known/registered service.
	 * See https://redmine.pfsense.org/issues/8410
	 */
	if (!isset($adr['port']) && is_port_or_alias($pbeginport)) {
		$adr['port'] = $pbeginport;
	}
}

function is_specialnet($net) {
	global $specialsrcdst;

	if (!$net) {
		return false;
	}
	if (in_array($net, $specialsrcdst)) {
		return true;
	} else {
		return false;
	}
}

function is_interface_ipaddr($interface) {
	global $config;

	if (is_array($config['interfaces'][$interface]) &&
	    isset($config['interfaces'][$interface]['ipaddr']) &&
	    !empty($config['interfaces'][$interface]['ipaddr'])) {
		return true;
	}
	return false;
}

function is_interface_ipaddrv6($interface) {
	global $config;

	if (is_array($config['interfaces'][$interface]) &&
	    isset($config['interfaces'][$interface]['ipaddrv6']) &&
	    !empty($config['interfaces'][$interface]['ipaddrv6'])) {
		return true;
	}
	return false;
}

?>
