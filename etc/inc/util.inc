<?php 
/*
	util.inc
	part of the pfSense project (https://www.pfsense.org)

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
	pfSense_BUILDER_BINARIES:	/bin/ps	/bin/kill	/usr/bin/killall	/sbin/ifconfig	/usr/bin/netstat
	pfSense_BUILDER_BINARIES:	/usr/bin/awk	/sbin/dmesg		/sbin/ping /usr/local/sbin/gzsig	/usr/sbin/arp
	pfSense_BUILDER_BINARIES:	/sbin/conscontrol	/sbin/devd	/bin/ps
	pfSense_MODULE:	utils
*/

/* kill a process by pid file */
function killbypid($pidfile) {
	return sigkillbypid($pidfile, "TERM");
}

function isvalidpid($pidfile) {
	$output = "";
	if (file_exists($pidfile)) {
		exec("/bin/pgrep -nF {$pidfile}", $output, $retval);
		return (intval($retval) == 0);
	}
	return false;
}

function is_process_running($process) {
	$output = "";
	exec("/bin/pgrep -anx " . escapeshellarg($process), $output, $retval);

	return (intval($retval) == 0);
}

function isvalidproc($proc) {
	return is_process_running($proc);
}

/* sigkill a process by pid file */
/* return 1 for success and 0 for a failure */
function sigkillbypid($pidfile, $sig) {
	if (file_exists($pidfile))
		return mwexec("/bin/pkill " . escapeshellarg("-{$sig}") . " -F {$pidfile}", true);

	return 0;
}

/* kill a process by name */
function sigkillbyname($procname, $sig) {
	if(isvalidproc($procname))
		return mwexec("/usr/bin/killall " . escapeshellarg("-{$sig}") . " " . escapeshellarg($procname), true);
}

/* kill a process by name */
function killbyname($procname) {
	if(isvalidproc($procname)) 
		mwexec("/usr/bin/killall " . escapeshellarg($procname));
}

function is_subsystem_dirty($subsystem = "") {
	global $g;

	if ($subsystem == "")
		return false;

	if (file_exists("{$g['varrun_path']}/{$subsystem}.dirty"))
		return true;

	return false;
}

function mark_subsystem_dirty($subsystem = "") {
	global $g;

	if (!file_put_contents("{$g['varrun_path']}/{$subsystem}.dirty", "DIRTY"))
		log_error(sprintf(gettext("WARNING: Could not mark subsystem: %s dirty"), $subsystem));
}

function clear_subsystem_dirty($subsystem = "") {
	global $g;

	@unlink("{$g['varrun_path']}/{$subsystem}.dirty");
}

function config_lock() {
	return;
}
function config_unlock() {
	return;
}

/* lock configuration file */
function lock($lock, $op = LOCK_SH) {
	global $g, $cfglckkeyconsumers;
	if (!$lock)
		die(gettext("WARNING: You must give a name as parameter to lock() function."));
	if (!file_exists("{$g['tmp_path']}/{$lock}.lock")) {
		@touch("{$g['tmp_path']}/{$lock}.lock");
		@chmod("{$g['tmp_path']}/{$lock}.lock", 0666);
	}
	$cfglckkeyconsumers++;
	if ($fp = fopen("{$g['tmp_path']}/{$lock}.lock", "w")) {
		if (flock($fp, $op))
			return $fp;
		else
			fclose($fp);
	}
}

/* unlock configuration file */
function unlock($cfglckkey = 0) {
	global $g, $cfglckkeyconsumers;
	flock($cfglckkey, LOCK_UN);
	fclose($cfglckkey);
	return;
}

function send_event($cmd) {
	global $g;

	if(!isset($g['event_address']))
		$g['event_address'] = "unix:///var/run/check_reload_status";
		
	$try = 0;
	while ($try < 3) {
		$fd = @fsockopen($g['event_address']);
		if ($fd) {
			fwrite($fd, $cmd);
			$resp = fread($fd, 4096);
			if ($resp != "OK\n")
				log_error("send_event: sent {$cmd} got {$resp}");
			fclose($fd);
			$try = 3;
		} else if (!is_process_running("check_reload_status"))
			mwexec_bg("/usr/bin/nice -n20 /usr/local/sbin/check_reload_status");
		$try++;
	}
}

function send_multiple_events($cmds) {
	global $g;

	if(!isset($g['event_address']))
		$g['event_address'] = "unix:///var/run/check_reload_status";
			
	if (!is_array($cmds))
		return;

	while ($try < 3) {
		$fd = @fsockopen($g['event_address']);
		if ($fd) {
			foreach ($cmds as $cmd) {
				fwrite($fd, $cmd);
				$resp = fread($fd, 4096);
				if ($resp != "OK\n")
					log_error("send_event: sent {$cmd} got {$resp}");
			}
			fclose($fd);
			$try = 3;
		} else if (!is_process_running("check_reload_status"))
			mwexec_bg("/usr/bin/nice -n20 /usr/local/sbin/check_reload_status");
		$try++;
	}
}

function refcount_init($reference) {
	$shmid = @shmop_open($reference, "c", 0644, 10);
	@shmop_write($shmid, str_pad("0", 10, "\x0", STR_PAD_RIGHT), 0);
	@shmop_close($shmid);
}

function refcount_reference($reference) {
	/* Take out a lock across the shared memory read, increment, write sequence to make it atomic. */
	$shm_lck = lock("shm{$reference}", LOCK_EX);
	try {
		/* NOTE: A warning is generated when shared memory does not exist */
		$shmid = @shmop_open($reference, "w", 0, 0);
		if (!$shmid) {
			refcount_init($reference);
			$shmid = @shmop_open($reference, "w", 0, 0);
			if (!$shmid) {
				log_error(gettext("Could not open shared memory {$reference}"));
				unlock($shm_lck);
				return;
			}
		}
		$shm_data = @shmop_read($shmid, 0, 10);
		$shm_data = intval($shm_data) + 1;
		@shmop_write($shmid, str_pad($shm_data, 10, "\x0", STR_PAD_RIGHT), 0);
		@shmop_close($shmid);
		unlock($shm_lck);
	} catch (Exception $e) {
		log_error($e->getMessage());
		unlock($shm_lck);
	}

	return $shm_data;
}

function refcount_unreference($reference) {
	/* Take out a lock across the shared memory read, decrement, write sequence to make it atomic. */
	$shm_lck = lock("shm{$reference}", LOCK_EX);
	try {
		$shmid = @shmop_open($reference, "w", 0, 0);
		if (!$shmid) {
			refcount_init($reference);
			log_error(gettext("Could not open shared memory {$reference}"));
			unlock($shm_lck);
			return;
		}
		$shm_data = @shmop_read($shmid, 0, 10);
		$shm_data = intval($shm_data) - 1;
		if ($shm_data < 0) {
			//debug_backtrace();
			log_error(sprintf(gettext("Reference %s is going negative, not doing unreference."), $reference));
		} else
			@shmop_write($shmid, str_pad($shm_data, 10, "\x0", STR_PAD_RIGHT), 0);
		@shmop_close($shmid);
		unlock($shm_lck);
	} catch (Exception $e) {
		log_error($e->getMessage());
		unlock($shm_lck);
	}

	return $shm_data;
}

function refcount_read($reference) {
	/* This function just reads the current value of the refcount for information. */
	/* There is no need for locking. */
	$shmid = @shmop_open($reference, "a", 0, 0);
	if (!$shmid) {
		log_error(gettext("Could not open shared memory for read {$reference}"));
		return -1;
	}
	$shm_data = @shmop_read($shmid, 0, 10);
	@shmop_close($shmid);
	return $shm_data;
}

function is_module_loaded($module_name) {
	$running = `/sbin/kldstat | grep {$module_name} | /usr/bin/grep -v grep | /usr/bin/wc -l`;
	if (intval($running) >= 1)
		return true;
	else
		return false;
}

/* return the subnet address given a host address and a subnet bit count */
function gen_subnet($ipaddr, $bits) {
	if (!is_ipaddr($ipaddr) || !is_numeric($bits))
		return "";
	return long2ip(ip2long($ipaddr) & gen_subnet_mask_long($bits));
}

/* return the subnet address given a host address and a subnet bit count */
function gen_subnetv6($ipaddr, $bits) {
	if (!is_ipaddrv6($ipaddr) || !is_numeric($bits))
		return "";

	$address = Net_IPv6::getNetmask($ipaddr, $bits);
	$address = Net_IPv6::compress($address);
	return $address;
}

/* return the highest (broadcast) address in the subnet given a host address and a subnet bit count */
function gen_subnet_max($ipaddr, $bits) {
	if (!is_ipaddr($ipaddr) || !is_numeric($bits))
		return "";

	return long2ip32(ip2long($ipaddr) | ~gen_subnet_mask_long($bits));
}

/* Generate end number for a given ipv6 subnet mask */
function gen_subnetv6_max($ipaddr, $bits) {
	if(!is_ipaddrv6($ipaddr))
		return false;
	
	$mask = Net_IPv6::getNetmask('FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF',$bits);
	
	$inet_ip = (binary)inet_pton($ipaddr);
	$inet_mask = (binary)inet_pton($mask);

	$inet_end = $inet_ip | ~$inet_mask;

	return (inet_ntop($inet_end));
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

/* Convert long int to IP address, truncating to 32-bits. */
function long2ip32($ip) {
	return long2ip($ip & 0xFFFFFFFF);
}

/* Convert IP address to long int, truncated to 32-bits to avoid sign extension on 64-bit platforms. */
function ip2long32($ip) {
	return ( ip2long($ip) & 0xFFFFFFFF );
}

/* Convert IP address to unsigned long int. */
function ip2ulong($ip) {
	return sprintf("%u", ip2long32($ip));
}

/* Find out how many IPs are contained within a given IP range
 *  e.g. 192.168.0.0 to 192.168.0.255 returns 256
 */
function ip_range_size($startip, $endip) {
	if (is_ipaddr($startip) && is_ipaddr($endip)) {
		// Operate as unsigned long because otherwise it wouldn't work
		//   when crossing over from 127.255.255.255 / 128.0.0.0 barrier
		return abs(ip2ulong($startip) - ip2ulong($endip)) + 1;
	}
	return -1;
}

/* Find the smallest possible subnet mask which can contain a given number of IPs
 *  e.g. 512 IPs can fit in a /23, but 513 IPs need a /22
 */
function find_smallest_cidr($number) {
	$smallest = 1;
	for ($b=32; $b > 0; $b--) {
		$smallest = ($number <= pow(2,$b)) ? $b : $smallest;
	}
	return (32-$smallest);
}

/* Return the previous IP address before the given address */
function ip_before($ip) {
	return long2ip32(ip2long($ip)-1);
}

/* Return the next IP address after the given address */
function ip_after($ip) {
	return long2ip32(ip2long($ip)+1);
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

/* Convert a range of IPs to an array of subnets which can contain the range. */
function ip_range_to_subnet_array($startip, $endip) {
	if (!is_ipaddr($startip) || !is_ipaddr($endip)) {
		return array();
	}

	// Container for subnets within this range.
	$rangesubnets = array();

	// Figure out what the smallest subnet is that holds the number of IPs in the given range.
	$cidr = find_smallest_cidr(ip_range_size($startip, $endip));

	// Loop here to reduce subnet size and retest as needed. We need to make sure
	//   that the target subnet is wholly contained between $startip and $endip.
	for ($cidr; $cidr <= 32; $cidr++) {
		// Find the network and broadcast addresses for the subnet being tested.
		$targetsub_min = gen_subnet($startip, $cidr);
		$targetsub_max = gen_subnet_max($startip, $cidr);

		// Check best case where the range is exactly one subnet.
		if (($targetsub_min == $startip) && ($targetsub_max == $endip)) {
			// Hooray, the range is exactly this subnet!
			return array("{$startip}/{$cidr}");
		}

		// These remaining scenarios will find a subnet that uses the largest
		//  chunk possible of the range being tested, and leave the rest to be
		//  tested recursively after the loop.

		// Check if the subnet begins with $startip and ends before $endip
		if (($targetsub_min == $startip) && ip_less_than($targetsub_max, $endip)) {
			break;
		}

		// Check if the subnet ends at $endip and starts after $startip
		if (ip_greater_than($targetsub_min, $startip) && ($targetsub_max == $endip)) {
			break;
		}

		// Check if the subnet is between $startip and $endip
		if (ip_greater_than($targetsub_min, $startip) && ip_less_than($targetsub_max, $endip)) {
			break;
		}
	}

	// Some logic that will recursivly search from $startip to the first IP before the start of the subnet we just found.
	// NOTE: This may never be hit, the way the above algo turned out, but is left for completeness.
	if ($startip != $targetsub_min) {
		$rangesubnets = array_merge($rangesubnets, ip_range_to_subnet_array($startip, ip_before($targetsub_min)));
	}

	// Add in the subnet we found before, to preserve ordering
	$rangesubnets[] = "{$targetsub_min}/{$cidr}";

	// And some more logic that will search after the subnet we found to fill in to the end of the range.
	if ($endip != $targetsub_max) {
		$rangesubnets = array_merge($rangesubnets, ip_range_to_subnet_array(ip_after($targetsub_max), $endip));
	}
	return $rangesubnets;
}

function is_iprange($range) {
	if (substr_count($range, '-') != 1) {
		return false;
	}
	list($ip1, $ip2) = explode ('-', $range);
	return (is_ipaddr($ip1) && is_ipaddr($ip2));
}

function is_numericint($arg) {
	return (preg_match("/[^0-9]/", $arg) ? false : true);
}


/* returns true if $ipaddr is a valid dotted IPv4 address or a IPv6 */
function is_ipaddr($ipaddr) {
	if(is_ipaddrv4($ipaddr)) {
		return true;
	}
	if(is_ipaddrv6($ipaddr)) {
		return true;
	}
	return false;
}

/* returns true if $ipaddr is a valid IPv6 address */
function is_ipaddrv6($ipaddr) {
	if (!is_string($ipaddr) || empty($ipaddr))
		return false;
	if (strstr($ipaddr, "%") && is_linklocal($ipaddr)) {
		$tmpip = explode("%", $ipaddr);
		$ipaddr = $tmpip[0];
	}
	return Net_IPv6::checkIPv6($ipaddr);
}

/* returns true if $ipaddr is a valid dotted IPv4 address */
function is_ipaddrv4($ipaddr) {
	if (!is_string($ipaddr) || empty($ipaddr))
		return false;

	$ip_long = ip2long($ipaddr);
	$ip_reverse = long2ip32($ip_long);

	if ($ipaddr == $ip_reverse)
		return true;
	else
		return false;
}

/* returns true if $ipaddr is a valid linklocal address */
function is_linklocal($ipaddr) {
	return (strtolower(substr($ipaddr, 0, 5)) == "fe80:");
}

/* returns scope of a linklocal address */
function get_ll_scope($addr) {
	if (!is_linklocal($addr) || !strstr($addr, "%"))
		return "";
	list ($ll, $scope) = explode("%", $addr);
	return $scope;
}

/* returns true if $ipaddr is a valid literal IPv6 address */
function is_literalipaddrv6($ipaddr) {
	if(preg_match("/\[([0-9a-f:]+)\]/i", $ipaddr, $match))
		$ipaddr = $match[1];
	else
		return false;

	return is_ipaddrv6($ipaddr);
}

function is_ipaddrwithport($ipport) {
	$parts = explode(":", $ipport);
	$port = array_pop($parts);
	if (count($parts) == 1) {
		return is_ipaddrv4($parts[0]) && is_port($port);
	} elseif (count($parts) > 1) {
		return is_literalipaddrv6(implode(":", $parts)) && is_port($port);
	} else {
		return false;
	}
}

function is_hostnamewithport($hostport) {
	$parts = explode(":", $hostport);
	$port = array_pop($parts);
	if (count($parts) == 1) {
		return is_hostname($parts[0]) && is_port($port);
	} else {
		return false;
	}
}

/* returns true if $ipaddr is a valid dotted IPv4 address or an alias thereof */
function is_ipaddroralias($ipaddr) {
	global $config;

	if (is_alias($ipaddr)) {
		if (is_array($config['aliases']['alias'])) {
			foreach ($config['aliases']['alias'] as $alias) {
				if ($alias['name'] == $ipaddr && $alias['type'] != "port")
					return true;
			}
		}
		return false;
	} else
		return is_ipaddr($ipaddr);

}

/* returns true if $subnet is a valid IPv4 or IPv6 subnet in CIDR format */
function is_subnet($subnet) {
	if(is_subnetv4($subnet)) {
		return true;
	}
	if(is_subnetv6($subnet)) {
		return true;
	}
	return false;
}

/* returns true if $subnet is a valid IPv4 subnet in CIDR format */
function is_subnetv4($subnet) {
	if (!is_string($subnet))
		return false;

	list($hp,$np) = explode('/', $subnet);

	if (!is_ipaddrv4($hp))
		return false;

	if (!is_numeric($np) || ($np < 1) || ($np > 32))
		return false;

	return true;
}

/* returns true if $subnet is a valid IPv6 subnet in CIDR format */
function is_subnetv6($subnet) {
	if (!is_string($subnet))
		return false;

	list($hp,$np) = explode('/', $subnet);

	if (!is_ipaddrv6($hp))
		return false;

	if (!is_numeric($np) || ($np < 1) || ($np > 128))
		return false;

	return true;
}


/* returns true if $subnet is a valid subnet in CIDR format or an alias thereof */
function is_subnetoralias($subnet) {
	global $aliastable;

	if (isset($aliastable[$subnet]) && is_subnet($aliastable[$subnet]))
		return true;
	else
		return is_subnet($subnet);
}

/* returns true if $hostname is a valid hostname */
function is_hostname($hostname) {
	if (!is_string($hostname))
		return false;

	if (preg_match('/^(?:(?:[a-z0-9_]|[a-z0-9_][a-z0-9_\-]*[a-z0-9_])\.)*(?:[a-z0-9_]|[a-z0-9_][a-z0-9_\-]*[a-z0-9_])$/i', $hostname))
		return true;
	else
		return false;
}

/* returns true if $domain is a valid domain name */
function is_domain($domain) {
	if (!is_string($domain))
		return false;

	if (preg_match('/^(?:(?:[a-z0-9]|[a-z0-9][a-z0-9\-]*[a-z0-9])\.)*(?:[a-z0-9]|[a-z0-9][a-z0-9\-]*[a-z0-9])$/i', $domain))
		return true;
	else
		return false;
}

/* returns true if $macaddr is a valid MAC address */
function is_macaddr($macaddr, $partial=false) {
	$repeat = ($partial) ? '1,5' : '5';
	return preg_match('/^[0-9A-F]{2}(?:[:][0-9A-F]{2}){'.$repeat.'}$/i', $macaddr) == 1 ? true : false;
}

/* returns true if $name is a valid name for an alias */
/* returns NULL if a reserved word is used */
function is_validaliasname($name) {
	/* Array of reserved words */
	$reserved = array("port", "pass");
	if (in_array($name, $reserved, true))
		return; /* return NULL */
	if (!preg_match("/[^a-zA-Z0-9_]/", $name) && (strlen($name) < 32))
		return true;
	else
		return false;
}

/* returns true if $port is a valid TCP/UDP port */
function is_port($port) {
	$tmpports = explode(":", $port);
	foreach($tmpports as $tmpport) {
		if (getservbyname($tmpport, "tcp") || getservbyname($tmpport, "udp"))
			continue;
		if (!ctype_digit($tmpport))
			return false;
		else if ((intval($tmpport) < 1) || (intval($tmpport) > 65535))
			return false;
	}
	return true;
}

/* returns true if $portrange is a valid TCP/UDP portrange ("<port>:<port>") */
function is_portrange($portrange) {
	$ports = explode(":", $portrange);

	return (count($ports) == 2 && is_port($ports[0]) && is_port($ports[1]));
}

/* returns true if $port is a valid port number or an alias thereof */
function is_portoralias($port) {
	global $config;

	if (is_alias($port)) {
		if (is_array($config['aliases']['alias'])) {
			foreach ($config['aliases']['alias'] as $alias) {
				if ($alias['name'] == $port && $alias['type'] == "port")
					return true;
				}
			}
			return false;
	} else
		return is_port($port);
}

/* returns true if $val is a valid shaper bandwidth value */
function is_valid_shaperbw($val) {
	return (preg_match("/^(\d+(?:\.\d+)?)([MKG]?b|%)$/", $val));
}

/* returns true if $test is in the range between $start and $end */
function is_inrange_v4($test, $start, $end) {
	if ( (ip2ulong($test) <= ip2ulong($end)) && (ip2ulong($test) >= ip2ulong($start)) )
		return true;
	else
		return false;
}

/* returns true if $test is in the range between $start and $end */
function is_inrange_v6($test, $start, $end) {
	if ( (inet_pton($test) <= inet_pton($end)) && (inet_pton($test) >= inet_pton($start)) )
		return true;
	else
		return false;
}

/* returns true if $test is in the range between $start and $end */
function is_inrange($test, $start, $end) {
	return is_ipaddrv6($test) ? is_inrange_v6($test, $start, $end) : is_inrange_v4($test, $start, $end);
}

/* return the configured carp interface list */
function get_configured_carp_interface_list() {
	global $config;

	$iflist = array();

	if(is_array($config['virtualip']['vip'])) {
		$viparr = &$config['virtualip']['vip'];
		foreach ($viparr as $vip) {
			switch ($vip['mode']) {
			case "carp":
				$vipif = "{$vip['interface']}_vip{$vip['vhid']}";
				$iflist[$vipif] = $vip['subnet'];
				break;
			}
		}
	}

	return $iflist;
}

/* return the configured IP aliases list */
function get_configured_ip_aliases_list($returnfullentry = false) {
	global $config;

	$alias_list=array();

	if(is_array($config['virtualip']['vip'])) {
		$viparr = &$config['virtualip']['vip'];
		foreach ($viparr as $vip) {
			if ($vip['mode']=="ipalias") {
				if ($returnfullentry)
					$alias_list[$vip['subnet']] = $vip;
				else
					$alias_list[$vip['subnet']] = $vip['interface'];
			}
		}
	}

	return $alias_list;
}

/* return all configured aliases list (IP, carp, proxyarp and other) */
function get_configured_vips_list() {
	global $config;

	$alias_list=array();

	if(is_array($config['virtualip']['vip'])) {
		$viparr = &$config['virtualip']['vip'];
		foreach ($viparr as $vip)
			$alias_list[] = array("ipaddr" => $vip['subnet'], "if" => $vip['interface']);
	}

	return $alias_list;
}

/* comparison function for sorting by the order in which interfaces are normally created */
function compare_interface_friendly_names($a, $b) {
	if ($a == $b)
		return 0;
	else if ($a == 'wan')
		return -1;
	else if ($b == 'wan')
		return 1;
	else if ($a == 'lan')
		return -1;
	else if ($b == 'lan')
		return 1;

	return strnatcmp($a, $b);
}

/* return the configured interfaces list. */
function get_configured_interface_list($only_opt = false, $withdisabled = false) {
	global $config;

	$iflist = array();

	/* if list */
	foreach($config['interfaces'] as $if => $ifdetail) {
		if ($only_opt && ($if == "wan" || $if == "lan"))
			continue;
		if (isset($ifdetail['enable']) || $withdisabled == true)
			$iflist[$if] = $if;
	}

	return $iflist;
}

/* return the configured interfaces list. */
function get_configured_interface_list_by_realif($only_opt = false, $withdisabled = false) {
	global $config;

	$iflist = array();

	/* if list */
	foreach($config['interfaces'] as $if => $ifdetail) {
		if ($only_opt && ($if == "wan" || $if == "lan"))
			continue;
		if (isset($ifdetail['enable']) || $withdisabled == true) {
			$tmpif = get_real_interface($if);
			if (!empty($tmpif))
				$iflist[$tmpif] = $if;
		}
	}

	return $iflist;
}

/* return the configured interfaces list with their description. */
function get_configured_interface_with_descr($only_opt = false, $withdisabled = false) {
	global $config;

	$iflist = array();

	/* if list */
	foreach($config['interfaces'] as $if => $ifdetail) {
		if ($only_opt && ($if == "wan" || $if == "lan"))
			continue;
		if (isset($ifdetail['enable']) || $withdisabled == true) {
			if(empty($ifdetail['descr']))
				$iflist[$if] = strtoupper($if);
			else
				$iflist[$if] = strtoupper($ifdetail['descr']);
		}
	}

	return $iflist;
}

/*
 *   get_configured_ip_addresses() - Return a list of all configured
 *   interfaces IP Addresses
 *
 */
function get_configured_ip_addresses() {
	global $config;

	if (!function_exists('get_interface_ip'))
		require_once("interfaces.inc");
	$ip_array = array();
	$interfaces = get_configured_interface_list();
	if (is_array($interfaces)) {
		foreach($interfaces as $int) {
			$ipaddr = get_interface_ip($int);
			$ip_array[$int] = $ipaddr;
		}
	}
	$interfaces = get_configured_carp_interface_list();
	if (is_array($interfaces)) 
		foreach($interfaces as $int => $ipaddr) 
			$ip_array[$int] = $ipaddr;

	/* pppoe server */
	if (is_array($config['pppoes']) && is_array($config['pppoes']['pppoe'])) {
		foreach($config['pppoes']['pppoe'] as $pppoe) {
			if ($pppoe['mode'] == "server") {
				if(is_ipaddr($pppoe['localip'])) {
					$int = "pppoes". $pppoe['pppoeid'];
					$ip_array[$int] = $pppoe['localip'];
				}
			}
		}
	}

	return $ip_array;
}

/*
 *   get_configured_ipv6_addresses() - Return a list of all configured
 *   interfaces IPv6 Addresses
 *
 */
function get_configured_ipv6_addresses() {
	require_once("interfaces.inc");
	$ipv6_array = array();
	$interfaces = get_configured_interface_list();
	if(is_array($interfaces)) {
		foreach($interfaces as $int) {
			$ipaddrv6 = get_interface_ipv6($int);
			$ipv6_array[$int] = $ipaddrv6;
		}
	}
	$interfaces = get_configured_carp_interface_list();
	if(is_array($interfaces)) 
		foreach($interfaces as $int => $ipaddrv6) 
			$ipv6_array[$int] = $ipaddrv6;
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
        if(!$vfaces) {
		$vfaces = array (
				'bridge',
				'ppp',
				'pppoe',
				'pptp',
				'l2tp',
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
				'carp',
				'lagg',
				'vip',
				'ipfw'
		);
	}
	switch($mode) {
	case "active":
                $upints = pfSense_interface_listget(IFF_UP);
        	break;
	case "media":
		$intlist = pfSense_interface_listget();
                $ifconfig = "";
                exec("/sbin/ifconfig -a", $ifconfig);
                $regexp = '/(' . implode('|', $intlist) . '):\s/';
                $ifstatus = preg_grep('/status:/', $ifconfig);
		foreach($ifstatus as $status) {
			$int = array_shift($intlist);
			if(stristr($status, "active")) $upints[] = $int;
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
	foreach($linkinfo as $link) {
		$friendly = "";
		$alink = explode(" ", $link);
		$ifname = rtrim(trim($alink[0]), '*');
		/* trim out all numbers before checking for vfaces */
		if (!in_array(array_shift(preg_split('/\d/', $ifname)), $vfaces) &&
			!stristr($ifname, "_vlan") && !stristr($ifname, "_wlan")) {
			$toput = array(
					"mac" => trim($alink[1]),
					"up" => in_array($ifname, $upints)
				);
			foreach($ipinfo as $ip) {
				$aip = explode(" ", $ip);
				if($aip[0] == $ifname) {
					$toput['ipaddr'] = $aip[1];
				}
			}
			if (is_array($config['interfaces'])) {
				foreach($config['interfaces'] as $name => $int)
					if($int['if'] == $ifname) $friendly = $name;
			}
			switch($keyby) {
			case "physical":
				if($friendly != "") {
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
				if($friendly != "") {
					$toput['if'] = $ifname;
					$iflist[$friendly] = $toput;
				}
				break;
			}
		}
	}
	return $iflist;
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
	if ($g['debug'])
		syslog(LOG_WARNING, var_dump(debug_backtrace()));
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
	if ($g['debug'])
		syslog(LOG_WARNING, var_dump(debug_backtrace()));
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

/* wrapper for exec() */
function mwexec($command, $mute = false, $clearsigmask = false) {
	global $g;

	if ($g['debug']) {
		if (!$_SERVER['REMOTE_ADDR'])
			echo "mwexec(): $command\n";
	}
	$oarr = array();
	$retval = 0;

	if ($clearsigmask) {
		$oldset = array();
		pcntl_sigprocmask(SIG_SETMASK, array(), $oldset);
	}
	$garbage = exec("$command 2>&1", $oarr, $retval);
	if ($clearsigmask) {
		pcntl_sigprocmask(SIG_SETMASK, $oldset);
	}

	if(isset($config['system']['developerspew']))
		$mute = false;
	if(($retval <> 0) && ($mute === false)) {
		$output = implode(" ", $oarr);
		log_error(sprintf(gettext("The command '%1\$s' returned exit code '%2\$d', the output was '%3\$s' "), $command, $retval, $output));
	}
	return $retval;
}

/* wrapper for exec() in background */
function mwexec_bg($command, $clearsigmask = false) {
	global $g;

	if ($g['debug']) {
		if (!$_SERVER['REMOTE_ADDR'])
			echo "mwexec(): $command\n";
	}

	if ($clearsigmask) {
		$oldset = array();
		pcntl_sigprocmask(SIG_SETMASK, array(), $oldset);
	}
	$_gb = exec("/usr/bin/nohup $command > /dev/null 2>&1 &");
	if ($clearsigmask) {
		pcntl_sigprocmask(SIG_SETMASK, $oldset);
	}
	unset($_gb);
}

/* unlink a file, if it exists */
function unlink_if_exists($fn) {
	$to_do = glob($fn);
	if(is_array($to_do)) {
		foreach($to_do as $filename)
			@unlink($filename);
	} else {
		@unlink($fn);
	}
}
/* make a global alias table (for faster lookups) */
function alias_make_table($config) {
	global $aliastable;

	$aliastable = array();

	if (is_array($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $alias) {
			if ($alias['name'])
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
			if ($name == $alias['name'])
				return $alias['type'];
		}
	}

        return "";
}

/* expand a host or network alias, if necessary */
function alias_expand($name) {
	global $aliastable;

	if (isset($aliastable[$name]))
		return "\${$name}";
	else if (is_ipaddr($name) || is_subnet($name) || is_port($name))
		return "{$name}";
	else
		return null;
}

function alias_expand_urltable($name) {
	global $config;
	$urltable_prefix = "/var/db/aliastables/";
	$urltable_filename = $urltable_prefix . $name . ".txt";

	if (is_array($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $alias) {
			if (($alias['type'] == 'urltable') && ($alias['name'] == $name)) {
				if (is_URL($alias["url"]) && file_exists($urltable_filename) && filesize($urltable_filename))
					return $urltable_filename;
				else if (process_alias_urltable($name, $alias["url"], 0, true))
					return $urltable_filename;
			}
		}
	}
	return null;
}

function subnet_size($subnet) {
	if (is_subnetv4($subnet)) {
		list ($ip, $bits) = explode("/", $subnet);
		return round(exp(log(2) * (32 - $bits)));
	}
	else if (is_subnetv6($subnet)) {
		list ($ip, $bits) = explode("/", $subnet);
		return round(exp(log(2) * (128 - $bits)));
	}
	else {
		return 0;
	}
}

function subnet_expand($subnet) {
	if (is_subnetv4($subnet)) {
		return subnetv4_expand($subnet);
	} else if (is_subnetv6($subnet)) {
		return subnetv6_expand($subnet);
	} else {
		return $subnet;
	}
}

function subnetv4_expand($subnet) {
	$result = array();
	list ($ip, $bits) = explode("/", $subnet);
	$net  = ip2long($ip);
	$mask = (0xffffffff << (32 - $bits));
	$net &= $mask;
	$size = round(exp(log(2) * (32 - $bits)));
	for ($i = 0; $i < $size; $i += 1) {
		$result[] = long2ip($net | $i);
	}
	return $result;
}

/* find out whether two subnets overlap */
function check_subnets_overlap($subnet1, $bits1, $subnet2, $bits2) {

	if (!is_numeric($bits1))
		$bits1 = 32;
	if (!is_numeric($bits2))
		$bits2 = 32;

	if ($bits1 < $bits2)
		$relbits = $bits1;
	else
		$relbits = $bits2;

	$sn1 = gen_subnet_mask_long($relbits) & ip2long($subnet1);
	$sn2 = gen_subnet_mask_long($relbits) & ip2long($subnet2);

	return ($sn1 == $sn2);
}

/* find out whether two IPv6 subnets overlap */
function check_subnetsv6_overlap($subnet1, $bits1, $subnet2, $bits2) {
	$sub1_min = gen_subnetv6($subnet1, $bits1);
	$sub1_max = gen_subnetv6_max($subnet1, $bits1);
	$sub2_min = gen_subnetv6($subnet2, $bits2);
	$sub2_max = gen_subnetv6_max($subnet2, $bits2);
	
	return (is_inrange_v6($sub1_min, $sub2_min, $sub2_max) || is_inrange_v6($sub1_max, $sub2_min, $sub2_max) || is_inrange_v6($sub2_min, $sub1_min, $sub1_max));
}

/* compare two IP addresses */
function ipcmp($a, $b) {
	if (ip_less_than($a, $b))
		return -1;
	else if (ip_greater_than($a, $b))
		return 1;
	else
		return 0;
}

/* return true if $addr is in $subnet, false if not */
function ip_in_subnet($addr,$subnet) {
	if(is_ipaddrv6($addr)) {
		return (Net_IPv6::isInNetmask($addr, $subnet));
	} else { /* XXX: Maybe check for IPv4 */
		list($ip, $mask) = explode('/', $subnet);
		$mask = (0xffffffff << (32 - $mask)) & 0xffffffff;
		return ((ip2long($addr) & $mask) == (ip2long($ip) & $mask));
	}
}

/* verify (and remove) the digital signature on a file - returns 0 if OK */
function verify_digital_signature($fname) {
	global $g;

	if(!file_exists("/usr/local/sbin/gzsig"))
		return 4;

	return mwexec("/usr/local/sbin/gzsig verify {$g['etc_path']}/pubkey.pem < " . escapeshellarg($fname));
}

/* obtain MAC address given an IP address by looking at the ARP table */
function arp_get_mac_by_ip($ip) {
	mwexec("/sbin/ping -c 1 -t 1 " . escapeshellarg($ip), true);
	$arpoutput = "";
	exec("/usr/sbin/arp -n " . escapeshellarg($ip), $arpoutput);

	if ($arpoutput[0]) {
		$arpi = explode(" ", $arpoutput[0]);
		$macaddr = $arpi[3];
		if (is_macaddr($macaddr))
			return $macaddr;
		else
			return false;
	}

	return false;
}

/* return a fieldname that is safe for xml usage */
function xml_safe_fieldname($fieldname) {
	$replace = array('/', '-', ' ', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')',
			 '_', '+', '=', '{', '}', '[', ']', '|', '/', '<', '>', '?',
			 ':', ',', '.', '\'', '\\'
		);
	return strtolower(str_replace($replace, "", $fieldname));
}

function mac_format($clientmac) {
    global $config, $cpzone;

    $mac = explode(":", $clientmac);
    $mac_format = $cpzone ? $config['captiveportal'][$cpzone]['radmac_format'] : false;

    switch($mac_format) {
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

function resolve_retry($hostname, $retries = 5) {

	if (is_ipaddr($hostname))
		return $hostname;

       for ($i = 0; $i < $retries; $i++) {
		// FIXME: gethostbyname does not work for AAAA hostnames, boo, hiss
               $ip = gethostbyname($hostname);

		if ($ip && $ip != $hostname) {
			/* success */
			return $ip;
		}

		sleep(1);
	}

	return false;
}

function format_bytes($bytes) {
	if ($bytes >= 1073741824) {
		return sprintf("%.2f GB", $bytes/1073741824);
	} else if ($bytes >= 1048576) {
		return sprintf("%.2f MB", $bytes/1048576);
	} else if ($bytes >= 1024) {
		return sprintf("%.0f KB", $bytes/1024);
	} else {
		return sprintf("%d bytes", $bytes);
	}
}

function update_filter_reload_status($text) {
	global $g;

	file_put_contents("{$g['varrun_path']}/filter_reload_status", $text);
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
				if (($file == ".") || ($file == ".."))
					continue;

				if (empty($filter_regex) || preg_match($filter_regex, $file))
					array_push($dir_array, $file);
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
			if (stristr($file, ".sh") == true)
				mwexec($directory . $file . " start");
			else if (!is_dir($directory . "/" . $file) && stristr($file,".inc")) 
				require_once($directory . "/" . $file);
		}
	}
}

/*
 *    safe_mkdir($path, $mode = 0755)
 *    create directory if it doesn't already exist and isn't a file!
 */
function safe_mkdir($path, $mode=0755) {
	global $g;

	if (!is_file($path) && !is_dir($path)) {
		return @mkdir($path, $mode, true);
	} else {
		return false;
	}
}

/*
 * make_dirs($path, $mode = 0755)
 * create directory tree recursively (mkdir -p)
 */
function make_dirs($path, $mode = 0755) {
	$base = '';
	foreach (explode('/', $path) as $dir) {
		$base .= "/$dir";
		if (!is_dir($base)) {
			if (!@mkdir($base, $mode))
				return false;
		}
	}
	return true;
}

/*
 * get_sysctl($names)
 * Get values of sysctl OID's listed in $names (accepts an array or a single
 * name) and return an array of key/value pairs set for those that exist
 */
function get_sysctl($names) {
	if (empty($names))
		return array();

	if (is_array($names)) {
		$name_list = array();
		foreach ($names as $name) {
			$name_list[] = escapeshellarg($name);
		}
	} else
		$name_list = array(escapeshellarg($names));

	exec("/sbin/sysctl -i " . implode(" ", $name_list), $output);
	$values = array();
	foreach ($output as $line) {
		$line = explode(": ", $line, 2);
		if (count($line) == 2)
			$values[$line[0]] = $line[1];
	}

	return $values;
}

/*
 * set_sysctl($value_list)
 * Set sysctl OID's listed as key/value pairs and return
 * an array with keys set for those that succeeded
 */
function set_sysctl($values) {
	if (empty($values))
		return array();

	$value_list = array();
	foreach ($values as $key => $value) {
		$value_list[] = escapeshellarg($key) . "=" . escapeshellarg($value);
	}

	exec("/sbin/sysctl -i " . implode(" ", $value_list), $output, $success);

	/* Retry individually if failed (one or more read-only) */
	if ($success <> 0 && count($value_list) > 1) {
		foreach ($value_list as $value) {
			exec("/sbin/sysctl -i " . $value, $output);
		}
	}

	$ret = array();
	foreach ($output as $line) {
		$line = explode(": ", $line, 2);
		if (count($line) == 2)
			$ret[$line[0]] = true;
	}

	return $ret;
}

/*
 *     get_memory()
 *     returns an array listing the amount of
 *     memory installed in the hardware
 *     [0] net memory available for the OS (FreeBSD) after some is taken by BIOS, video or whatever - e.g. 235 MBytes
 *     [1] real (actual) memory of the system, should be the size of the RAM card/s - e.g. 256 MBytes
 */
function get_memory() {

	$physmem = trim(`sysctl -n hw.physmem`, " \n");
	$realmem = trim(`sysctl -n hw.realmem`, " \n");
	/* convert from bytes to megabytes */
	return array(($physmem/1048576),($realmem/1048576));
}

function mute_kernel_msgs() {
	global $config;
	// Do not mute serial console.  The kernel gets very very cranky
	// and will start dishing you cannot control tty errors.
	switch (trim(file_get_contents("/etc/platform"))) {
		case "nanobsd":
		case "jail":
			return;
	}
	if($config['system']['enableserial']) 
		return;			
	exec("/sbin/conscontrol mute on");
}

function unmute_kernel_msgs() {
	global $config;
	// Do not mute serial console.  The kernel gets very very cranky
	// and will start dishing you cannot control tty errors.
	switch (trim(file_get_contents("/etc/platform"))) {
		case "nanobsd":
		case "jail":
			return;
	}
	exec("/sbin/conscontrol mute off");
}

function start_devd() {
	global $g;

	if ($g['platform'] == 'jail')
		return;
	exec("/sbin/devd");
	sleep(1);
}

function is_interface_vlan_mismatch() {
	global $config, $g;

	if (is_array($config['vlans']['vlan'])) {
		foreach ($config['vlans']['vlan'] as $vlan) {
			if (does_interface_exist($vlan['if']) == false)
				return true;
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
			if (preg_match("/^enc|^cua|^tun|^tap|^l2tp|^pptp|^ppp|^ovpn|^gif|^gre|^lagg|^bridge|vlan|_wlan/i", $ifcfg['if'])) {
				// Do not check these interfaces.
				$i++;
				continue;
			}
			else if (does_interface_exist($ifcfg['if']) == false) {
				$missing_interfaces[] = $ifcfg['if'];
				$do_assign = true;
			} else
				$i++;
		}
	}

	if ($g['minimum_nic_count'] > $i) {
		$do_assign = true;
	} else if (file_exists("{$g['tmp_path']}/assign_complete"))
		$do_assign = false;

	if (!empty($missing_interfaces) && $do_assign)
		file_put_contents("{$g['tmp_path']}/missing_interfaces", implode(' ', $missing_interfaces));
	else
		@unlink("{$g['tmp_path']}/missing_interfaces");

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
	while(!isset($key)) {
		if ($timer >= 9) { echo chr(8) . chr(8) . ($timer==9 ? chr(32) : null)  . "{$timer}";  }
		else { echo chr(8). "{$timer}"; }
		`/bin/stty -icanon min 0 time 25`;
		$key = trim(`KEY=\`dd count=1 2>/dev/null\`; echo \$KEY`);
		`/bin/stty icanon`;
		if ($key == '')
			unset($key);
		$timer--;
		if ($timer == 0)
			break;
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
function msort($array, $id="id", $sort_ascending=true) {
	$temp_array = array();
	while(count($array)>0) {
		$lowest_id = 0;
		$index=0;
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
		$array = array_merge(array_slice($array, 0,$lowest_id), array_slice($array, $lowest_id+1));
	}
	if ($sort_ascending) {
		return $temp_array;
	} else {
    		return array_reverse($temp_array);
	}
}

/****f* util/color
 * NAME
 *   color - outputs a color code to the ansi terminal if supported
 * INPUTS
 *   color code or color name
 * RESULT
 *   Outputs the ansi color sequence for the color specified.  Default resets terminal.
 ******/
function color($color = "0m") {
	/*
		Color codes available:
		 0m reset; clears all colors and styles (to white on black)
		 1m bold on (see below)
		 3m italics on
		 4m underline on
		 7m inverse on; reverses foreground & background colors
		 9m strikethrough on
		 22m bold off (see below)
		 23m italics off
		 24m underline off
		 27m inverse off
		 29m strikethrough off
		 30m set foreground color to black
		 31m set foreground color to red
		 32m set foreground color to green
		 33m set foreground color to yellow
		 34m set foreground color to blue
		 35m set foreground color to magenta (purple)
		 36m set foreground color to cyan
		 37m set foreground color to white
		 40m  set background color to black
		 41m set background color to red
		 42m set background color to green
		 43m set background color to yellow
		 44m set background color to blue
		 45m set background color to magenta (purple)
		 46m set background color to cyan
		 47m set background color to white
		 49m set background color to default (black)
	*/	
	// Allow caching of TERM to 
	// speedup subequence requests.
	global $TERM;
	if(!$TERM) 
		$TERM=`/usr/bin/env | grep color`;
	if(!$TERM)
		$TERM=`/usr/bin/env | grep cons25`;
	if($TERM) {
		$ESCAPE=chr(27);
		switch ($color) {
			case "black":
				return "{$ESCAPE}[30m"; 
			case "red":
				return "{$ESCAPE}[31m"; 
			case "green":
				return "{$ESCAPE}[32m"; 
			case "yellow":
				return "{$ESCAPE}[33m"; 
			case "blue":
				return "{$ESCAPE}[34m"; 
			case "magenta":
				return "{$ESCAPE}[35m"; 
			case "cyan":
				return "{$ESCAPE}[36m"; 
			case "white":
				return "{$ESCAPE}[37m"; 
			case "default":
				return "{$ESCAPE}[39m"; 
		}
		return "{$ESCAPE}[{$color}";
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
	if($match)
		return true;	
	return false;
}

function is_file_included($file = "") {
	$files = get_included_files();
	if (in_array($file, $files))
		return true;
	
	return false;
}

/*
 * Replace a value on a deep associative array using regex
 */
function array_replace_values_recursive($data, $match, $replace) {
	if (empty($data))
		return $data;

	if (is_string($data))
		$data = preg_replace("/{$match}/", $replace, $data);
	else if (is_array($data))
		foreach ($data as $k => $v)
			$data[$k] = array_replace_values_recursive($v, $match, $replace);

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
	foreach($arrays as $array) {

		// The first remaining array is $array. We are processing it. So
		// we remove it from remaing arrays.
        array_shift($remains);

		// We don't care non array param, like array_merge since PHP 5.0.
		if(is_array($array)) {
			// Loop values
			foreach($array as $key => $value) {
				if(is_array($value)) {
					// we gather all remaining arrays that have such key available
					$args = array();
					foreach($remains as $remain) {
						if(array_key_exists($key, $remain)) {
							array_push($args, $remain[$key]);
						}
					}

					if(count($args) > 2) {
						// put the recursion
						$result[$key] = call_user_func_array(__FUNCTION__, $args);
					} else {
						foreach($value as $vkey => $vval) {
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

function get_staticroutes($returnsubnetsonly = false) {
	global $config;

	/* Bail if there are no routes, but return an array always so callers don't have to check. */
	if (!is_array($config['staticroutes']['route']))
		return array();

	$allstaticroutes = array();
	$allsubnets = array();
	/* Loop through routes and expand aliases as we find them. */
	foreach ($config['staticroutes']['route'] as $route) {
		if (is_alias($route['network'])) {
			if (!function_exists('filter_expand_alias_array'))
				require_once('filter.inc');
			$subnets = filter_expand_alias_array($route['network']);
			foreach ($subnets as $net) {
				if (!is_subnet($net)) {
					if (is_ipaddrv4($net))
						$net .= "/32";
					else if (is_ipaddrv6($net))
						$net .= "/128";
					/* This must be a hostname, we can't use it. */
					else
						continue;
				}
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
	if ($returnsubnetsonly)
		return $allsubnets;
	else
		return $allstaticroutes;
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
			}
			else if (is_array($type)) {
				if (in_array($alias['type'], $type)) {
					$result[] = $alias['name'];
				}
			}
			else if ($type === $alias['type']) {
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

function setup_library_paths() {
	$current_library_paths = explode(":", exec("/sbin/ldconfig -r | /usr/bin/grep 'search directories' | /usr/bin/awk '{print $3;}'"));
	$pbi_library_paths = array_merge(glob("/usr/pbi/*/lib", GLOB_ONLYDIR), glob("/usr/pbi/*/lib/*", GLOB_ONLYDIR));
	foreach ($pbi_library_paths as $pbilib) {
		if (!in_array($pbilib, $current_library_paths))
			exec("/sbin/ldconfig -m {$pbilib}");
	}
}

function get_current_theme() {
	global $config, $g;
	/*
	 *   if user has selected a custom template, use it.
	 *   otherwise default to pfsense tempalte
	 */
	if (($g["disablethemeselection"] === true) && !empty($g["default_theme"]) && (is_dir($g["www_path"].'/themes/'.$g["default_theme"])))
		$theme = $g["default_theme"];
	elseif($config['theme'] <> "" && (is_dir($g["www_path"].'/themes/'.$config['theme'])))
		$theme = $config['theme'];
	else
		$theme = "pfsense";
	/*
	 *  If this device is an apple ipod/iphone
	 *  switch the theme to one that works with it.
	 */
	$lowres_ua = array("iPhone", "iPod", "iPad", "Android", "BlackBerry", "Opera Mini", "Opera Mobi", "PlayBook", "IEMobile");
	foreach($lowres_ua as $useragent)
		if(strstr($_SERVER['HTTP_USER_AGENT'], $useragent))
			$theme = (empty($g['theme_lowres']) && (is_dir($g["www_path"].'/themes/'.$g['theme_lowres']))) ? "pfsense" : $g['theme_lowres'];
	return $theme;
}

?>
