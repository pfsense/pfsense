<?php
/*
	util.inc
	part of m0n0wall (http://m0n0.ch/wall)
	
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

/* kill a process by pid file */
function killbypid($pidfile) {
	sigkillbypid($pidfile, "TERM");
}

/* sigkill a process by pid file */
function sigkillbypid($pidfile, $sig) {
	if (file_exists($pidfile)) {
		mwexec("/bin/kill -s $sig `/bin/cat " . $pidfile . "`");
	}
}

/* kill a process by name */
function killbyname($procname) {
	mwexec("/usr/bin/killall " . escapeshellarg($procname));
}

/* return the subnet address given a host address and a subnet bit count */
function gen_subnet($ipaddr, $bits) {
	if (!is_ipaddr($ipaddr) || !is_numeric($bits))
		return "";
	
	return long2ip(ip2long($ipaddr) & gen_subnet_mask_long($bits));
}

/* return the highest (broadcast) address in the subnet given a host address and a subnet bit count */
function gen_subnet_max($ipaddr, $bits) {
	if (!is_ipaddr($ipaddr) || !is_numeric($bits))
		return "";
	
	return long2ip(ip2long($ipaddr) | ~gen_subnet_mask_long($bits));
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

function is_numericint($arg) {
	return (preg_match("/[^0-9]/", $arg) ? false : true);
}

/* returns true if $ipaddr is a valid dotted IPv4 address */
function is_ipaddr($ipaddr) {
	if (!is_string($ipaddr))
		return false;
		
	$ip_long = ip2long($ipaddr);
	$ip_reverse = long2ip($ip_long);
 
	if ($ipaddr == $ip_reverse)
		return true;
	else
		return false;
}

/* returns true if $ipaddr is a valid dotted IPv4 address or an alias thereof */
function is_ipaddroralias($ipaddr) {
	
	global $aliastable;
	
	if (isset($aliastable[$ipaddr]) && is_ipaddr($aliastable[$ipaddr]))
		return true;
	else
		return is_ipaddr($ipaddr);
}

/* returns true if $ipaddr is a valid dotted IPv4 address or any alias */
function is_ipaddroranyalias($ipaddr) {
	
	global $aliastable;
	
	if (isset($aliastable[$ipaddr]))
		return true;
	else
		return is_ipaddr($ipaddr);
}

/* returns true if $subnet is a valid subnet in CIDR format */
function is_subnet($subnet) {
	if (!is_string($subnet))
		return false;
		
	list($hp,$np) = explode('/', $subnet);
	
	if (!is_ipaddr($hp))
		return false;
	
	if (!is_numeric($np) || ($np < 1) || ($np > 32))
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
		
	if (preg_match("/^[a-z0-9\-]+$/i", $hostname))
		return true;
	else
		return false;
}

/* returns true if $domain is a valid domain name */
function is_domain($domain) {
	if (!is_string($domain))
		return false;
		
	if (preg_match("/^([a-z0-9\-]+\.?)*$/i", $domain))
		return true;
	else
		return false;
}

/* returns true if $uname is a valid DynDNS username */
function is_dyndns_username($uname) {
	if (!is_string($uname))
		return false;
		
	if (preg_match("/[^a-z0-9\-.@_]/i", $uname))
		return false;
	else
		return true;
}

/* returns true if $macaddr is a valid MAC address */
function is_macaddr($macaddr) {
	if (!is_string($macaddr))
		return false;
		
	$maca = explode(":", $macaddr);
	if (count($maca) != 6)
		return false;
	
	foreach ($maca as $macel) {
		if (($macel === "") || (strlen($macel) > 2))
			return false;
		if (preg_match("/[^0-9a-f]/i", $macel))
			return false;
	}
	
	return true;
}

/* returns true if $name is a valid name for an alias */
function is_validaliasname($name) {
	if (!preg_match("/[^a-zA-Z0-9]/", $name))
		return true;
	else
		return false;
}

/* returns true if $port is a valid TCP/UDP port */
function is_port($port) {
	if (!is_numericint($port))
		return false;
		
	if (($port < 1) || ($port > 65535))
		return false;
	else
		return true;
}

/* returns a list of interfaces with MAC addresses
   (skips VLAN and other virtual interfaces) */
function get_interface_list() {
	
	global $g;
	
	/* build interface list with netstat */
	exec("/usr/bin/netstat -inW -f link", $linkinfo);
	array_shift($linkinfo);
	
	$iflist = array();
	
	foreach ($linkinfo as $link) {
		$alink = preg_split("/\s+/", $link);
		$ifname = chop($alink[0]);
		
		if (substr($ifname, -1) == "*")
			$ifname = substr($ifname, 0, strlen($ifname) - 1);
		
		if (!preg_match("/^(ppp|sl|gif|faith|lo|ng|vlan)/", $ifname)) {
			$iflist[$ifname] = array();
			
			$iflist[$ifname]['mac'] = chop($alink[3]);
			$iflist[$ifname]['up'] = false;
			
			/* find out if the link on this interface is up */
			unset($ifinfo);
			exec("/sbin/ifconfig {$ifname}", $ifinfo);
			
			foreach ($ifinfo as $ifil) {
				if (preg_match("/status: (.*)$/", $ifil, $matches)) {
					if ($matches[1] == "active")
						$iflist[$ifname]['up'] = true;
					break;
				}
			}
		}
	}
	
	return $iflist;
}

/* wrapper for exec() */
function mwexec($command) {

	global $g;
	
	if ($g['debug']) {
		if (!$_SERVER['REMOTE_ADDR'])
			echo "mwexec(): $command\n";
		passthru($command, $retval);
	} else {
		exec("$command > /dev/null 2>&1", $oarr, $retval);
	}
	
	return $retval; 
}

/* wrapper for exec() in background */
function mwexec_bg($command) {

	global $g;
	
	if ($g['debug']) {
		if (!$_SERVER['REMOTE_ADDR'])
			echo "mwexec(): $command\n";
	}
	
	exec("nohup $command > /dev/null 2>&1 &");
}

/* unlink a file, if it exists */
function unlink_if_exists($fn) {
	if (file_exists($fn))
		unlink($fn);
}

/* make a global alias table (for faster lookups) */
function alias_make_table() {
	
	global $config, $g, $aliastable;
	
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

/* expand a host or network alias, if necessary */
function alias_expand($name) {
	
	global $aliastable;
	
	if (isset($aliastable[$name]))
		return $aliastable[$name];
	else if (is_ipaddr($name) || is_subnet($name))
		return $name;
	else
		return null;
}

/* expand a host alias, if necessary */
function alias_expand_host($name) {
	
	global $aliastable;
	
	if (isset($aliastable[$name]) && is_ipaddr($aliastable[$name]))
		return $aliastable[$name];
	else if (is_ipaddr($name))
		return $name;
	else
		return null;
}

/* expand a network alias, if necessary */
function alias_expand_net($name) {
	
	global $aliastable;
	
	if (isset($aliastable[$name]) && is_subnet($aliastable[$name]))
		return $aliastable[$name];
	else if (is_subnet($name))
		return $name;
	else
		return null;
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
	
	if ($sn1 == $sn2)
		return true;
	else
		return false;
}

/* compare two IP addresses */
function ipcmp($a, $b) {
	if (ip2long($a) < ip2long($b))
		return -1;
	else if (ip2long($a) > ip2long($b))
		return 1;
	else
		return 0;
}

/* return true if $addr is in $subnet, false if not */
function ip_in_subnet($addr,$subnet) {
	list($ip, $mask) = explode('/', $subnet);
	$mask = 0xffffffff << (32 - $mask);
	return ((ip2long($addr) & $mask) == (ip2long($ip) & $mask));
}

/* verify (and remove) the digital signature on a file - returns 0 if OK */
function verify_digital_signature($fname) {

	global $g;

	return mwexec("/usr/local/bin/verifysig " .
		escapeshellarg("{$g['etc_path']}/pubkey.pem") . " " .
		escapeshellarg($fname));
}

/* obtain MAC address given an IP address by looking at the ARP table */
function arp_get_mac_by_ip($ip) {
	exec("/usr/sbin/arp -n {$ip}", $arpoutput);
	
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

?>
