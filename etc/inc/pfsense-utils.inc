<?php
/****h* pfSense/pfsense-utils
 * NAME
 *   pfsense-utils.inc - Utilities specific to pfSense
 * DESCRIPTION
 *   This include contains various pfSense specific functions.
 * HISTORY
 *   $Id$
 ******
 *
 * Copyright (C) 2004-2007 Scott Ullrich (sullrich@gmail.com)
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * RISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 */

/****f* pfsense-utils/have_natonetooneruleint_access
 * NAME
 *   have_natonetooneruleint_access
 * INPUTS
 *	 none
 * RESULT
 *   returns true if user has access to edit a specific firewall nat one to one interface
 ******/
function have_natonetooneruleint_access($if) {
	$security_url = "firewall_nat_1to1_edit.php?if=". strtolower($if);
	if(isAllowedPage($security_url, $allowed)) 
		return true;
	return false;
}

/****f* pfsense-utils/have_natpfruleint_access
 * NAME
 *   have_natpfruleint_access
 * INPUTS
 *	 none
 * RESULT
 *   returns true if user has access to edit a specific firewall nat port forward interface
 ******/
function have_natpfruleint_access($if) {
	$security_url = "firewall_nat_edit.php?if=". strtolower($if);
	if(isAllowedPage($security_url, $allowed)) 
		return true;
	return false;
}

/****f* pfsense-utils/have_ruleint_access
 * NAME
 *   have_ruleint_access
 * INPUTS
 *	 none
 * RESULT
 *   returns true if user has access to edit a specific firewall interface
 ******/
function have_ruleint_access($if) {
	$security_url = "firewall_rules.php?if=". strtolower($if);
	if(isAllowedPage($security_url)) 
		return true;
	return false;
}

/****f* pfsense-utils/does_url_exist
 * NAME
 *   does_url_exist
 * INPUTS
 *	 none
 * RESULT
 *   returns true if a url is available
 ******/
function does_url_exist($url) {
	$fd = fopen("$url","r");
	if($fd) {
		fclose($fd);
   		return true;    
	} else {
        return false;
	}
}

/****f* pfsense-utils/is_private_ip
 * NAME
 *   is_private_ip
 * INPUTS
 *	 none
 * RESULT
 *   returns true if an ip address is in a private range
 ******/
function is_private_ip($iptocheck) {
        $isprivate = false;
        $ip_private_list=array(
               "10.0.0.0/8",
               "172.16.0.0/12",
               "192.168.0.0/16",
               "99.0.0.0/8"
        );
        foreach($ip_private_list as $private) {
                if(ip_in_subnet($iptocheck,$private)==true)
                        $isprivate = true;
        }
        return $isprivate;
}

/****f* pfsense-utils/get_tmp_file
 * NAME
 *   get_tmp_file
 * INPUTS
 *	 none
 * RESULT
 *   returns a temporary filename
 ******/
function get_tmp_file() {
	return "/tmp/tmp-" . time();
}

/****f* pfsense-utils/find_number_of_needed_carp_interfaces
 * NAME
 *   find_number_of_needed_carp_interfaces
 * INPUTS
 *   null
 * RESULT
 *   the number of needed carp interfacs
 ******/
function find_number_of_needed_carp_interfaces() {
	global $config, $g;
	$carp_counter=0;
	if(!$config['virtualip'])
		return 0;
	if(!$config['virtualip']['vip'])
		return 0;
	foreach($config['virtualip']['vip'] as $vip) {
		if($vip['mode'] == "carp")
			$carp_counter++;
	}
	return $carp_counter;
}

/****f* pfsense-utils/reset_carp
 * NAME
 *   reset_carp - resets carp after primary interface changes
 * INPUTS
 *   null
 * RESULT
 *   null
 ******/
function reset_carp() {
	$carp_counter=find_number_of_created_carp_interfaces();
	$needed_carp_interfaces = find_number_of_needed_carp_interfaces();
	mwexec("/sbin/sysctl net.inet.carp.allow=0");
	for($x=0; $x<$carp_counter; $x++) {
		mwexec("/sbin/ifconfig carp{$x} down");
		usleep(1000);
		mwexec("/sbin/ifconfig carp{$x} delete");
		if($needed_carp_interfaces < $carp_counter) {
			$needed_carp_interfaces--;
			//log_error("Destroying carp interface.");
			//mwexec("/sbin/ifconfig carp{$x} destroy");
		}
	}
	find_number_of_created_carp_interfaces(true);
	sleep(1);
	mwexec("/sbin/sysctl net.inet.carp.allow=1");
	interfaces_carp_configure();
}

/****f* pfsense-utils/get_dns_servers
 * NAME
 *   get_dns_servres - get system dns servers
 * INPUTS
 *   $dns_servers - an array of the dns servers
 * RESULT
 *   null
 ******/
function get_dns_servers() {
	$dns_servers = array();
	$dns = `cat /etc/resolv.conf`;
	$dns_s = split("\n", $dns);
	foreach($dns_s as $dns) {
		$matches = "";
		if (preg_match("/nameserver (.*)/", $dns, $matches))
			$dns_servers[] = $matches[1];
	}
	$dns_server_master = array();
	$lastseen = "";
	foreach($dns_servers as $t) {
		if($t <> $lastseen)
			if($t <> "")
				$dns_server_master[] = $t;
		$lastseen = $t;
	}
	return $dns_server_master;
}

/****f* pfsense-utils/enable_hardware_offloading
 * NAME
 *   enable_hardware_offloading - Enable a NIC's supported hardware features.
 * INPUTS
 *   $interface	- string containing the physical interface to work on.
 * RESULT
 *   null
 * NOTES
 *   This function only supports the fxp driver's loadable microcode.
 ******/
function enable_hardware_offloading($interface) {
	global $g, $config;

	if(stristr($interface,"lnc"))
		return;

	/* translate wan, lan, opt -> real interface if needed */
	$int = interface_translate_type_to_real($interface);
	if($int <> "") $interface = $int;
	$int_family = preg_split("/[0-9]+/", $int);
	$options = strtolower(`/sbin/ifconfig -m {$interface} | grep capabilities`);
	$supported_ints = array('fxp');
	if (in_array($int_family, $supported_ints)) {
        	if(isset($config['system']['do_not_use_nic_microcode']))
                        continue;
		mwexec("/sbin/ifconfig {$interface} link0");
        }

	/* skip vlans for checksumming and polling */
	if(stristr($interface, "vlan")) 
		return;

	if($config['system']['disablechecksumoffloading']) {
                if(stristr($options, "txcsum") == true)
                        mwexec("/sbin/ifconfig {$interface} -txcsum 2>/dev/null");
                if(stristr($options, "rxcsum") == true)
                        mwexec("/sbin/ifconfig {$interface} -rxcsum 2>/dev/null");
	} else {
               	if(stristr($options, "txcsum") == true)
                        mwexec("/sbin/ifconfig {$interface} txcsum 2>/dev/null");
        	if(stristr($options, "rxcsum") == true)
                        mwexec("/sbin/ifconfig {$interface} rxcsum 2>/dev/null");
        }

	/* if the NIC supports polling *AND* it is enabled in the GUI */
	if(interface_supports_polling($interface)) {
		$polling = isset($config['system']['polling']);	
		if($polling) {
			mwexec("sysctl kern.polling.enable=1");
                        mwexec("/sbin/ifconfig {$interface} polling 2>/dev/null");
		} else {
			mwexec("sysctl kern.polling.enable=0");
		}
	}
	return;
}

/****f* pfsense-utils/interface_supports_polling
 * NAME
 *   checks to see if an interface supports polling according to man polling
 * INPUTS
 *
 * RESULT
 *   true or false
 * NOTES
 *
 ******/
function interface_supports_polling($iface) {
	$pattern = '/([a-z].*)[0-9]/';
	preg_match($pattern, $iface, $iface2);
	$interface=$iface2[1];
	$supported_ints = array("bge",
		"dc",
		"em",
		"fwe",
		"fwip",
		"fxp",
		"ixgb",
		"nfe",
		"vge",
		"re",
		"rl",
		"sf",
		"sis",
		"ste",
		"stge",    
		"vge",
		"vr",
		"xl");
	if(in_array($interface, $supported_ints))
		return true;
	return false;
}

/****f* pfsense-utils/is_alias_inuse
 * NAME
 *   checks to see if an alias is currently in use by a rule
 * INPUTS
 *
 * RESULT
 *   true or false
 * NOTES
 *
 ******/
function is_alias_inuse($alias) {
	global $g, $config;

	if($alias == "") return false;
	/* loop through firewall rules looking for alias in use */
	if(is_array($config['filter']['rule']))
		foreach($config['filter']['rule'] as $rule) {
			if($rule['source']['address'])
				if($rule['source']['address'] == $alias)
					return true;
			if($rule['destination']['address'])
				if($rule['destination']['address'] == $alias)
					return true;
		}
	/* loop through nat rules looking for alias in use */
	if(is_array($config['nat']['rule']))
		foreach($config['nat']['rule'] as $rule) {
			if($rule['target'] == $alias)
				return true;
			if($rule['external-address'] == $alias)
				return true;
		}
	return false;
}

/****f* pfsense-utils/is_schedule_inuse
 * NAME
 *   checks to see if a schedule is currently in use by a rule
 * INPUTS
 *
 * RESULT
 *   true or false
 * NOTES
 *
 ******/
function is_schedule_inuse($schedule) {
	global $g, $config;

	if($schedule == "") return false;
	/* loop through firewall rules looking for schedule in use */
	if(is_array($config['filter']['rule']))
		foreach($config['filter']['rule'] as $rule) {
			if($rule['sched'] == $schedule)
				return true;
		}
	return false;
}

/****f* pfsense-utils/setup_polling_defaults
 * NAME
 *   sets up sysctls for pollingS
 * INPUTS
 *
 * RESULT
 *   null
 * NOTES
 *
 ******/
function setup_polling_defaults() {
	global $g, $config;
	if($config['system']['polling_each_burst'])
		mwexec("sysctl kern.polling.each_burst={$config['system']['polling_each_burst']}");
	if($config['system']['polling_burst_max'])
		mwexec("sysctl kern.polling.burst_max={$config['system']['polling_burst_max']}");
	if($config['system']['polling_user_frac'])
		mwexec("sysctl kern.polling.user_frac={$config['system']['polling_user_frac']}");
}

/****f* pfsense-utils/setup_polling
 * NAME
 *   sets up polling
 * INPUTS
 *
 * RESULT
 *   null
 * NOTES
 *
 ******/
function setup_polling() {
	global $g, $config;

	setup_polling_defaults();

	$supported_ints = array('bge', 'dc', 'em', 'fwe', 'fwip', 'fxp', 'ixgb', 'ste', 'nge', 're', 'rl', 'sf', 'sis', 'ste', 'vge', 'vr', 'xl');

	/* if list */
	$iflist = get_configured_interface_list();

	foreach ($iflist as $ifent => $ifname) {
		$real_interface = convert_friendly_interface_to_real_interface_name($ifname);
		$ifdevice = substr($real_interface, 0, -1);
		if(!in_array($ifdevice, $supported_ints)) {
			continue;
        }
		if(isset($config['system']['polling'])) {
			mwexec("/sbin/ifconfig {$real_interface} polling");
			mwexec("/sbin/sysctl kern.polling.idle_poll=1");
		} else {
			mwexec("/sbin/ifconfig {$real_interface} -polling");
		}
	}
}

/****f* pfsense-utils/setup_microcode
 * NAME
 *   enumerates all interfaces and calls enable_hardware_offloading which
 *   enables a NIC's supported hardware features.
 * INPUTS
 *
 * RESULT
 *   null
 * NOTES
 *   This function only supports the fxp driver's loadable microcode.
 ******/
function setup_microcode() {

	/* if list */
        $ifdescrs = get_configured_interface_list();

	foreach($ifdescrs as $if)
		enable_hardware_offloading($if);
}

/****f* pfsense-utils/get_carp_status
 * NAME
 *   get_carp_status - Return whether CARP is enabled or disabled.
 * RESULT
 *   boolean	- true if CARP is enabled, false if otherwise.
 ******/
function get_carp_status() {
    /* grab the current status of carp */
    $status = `/sbin/sysctl net.inet.carp.allow | cut -d" " -f2`;
    if(intval($status) == "0") return false;
    return true;
}

/****f* pfsense-utils/is_carp_defined
 * NAME
 *   is_carp_defined - Return whether CARP is detected in the kernel.
 * RESULT
 *   boolean	- true if CARP is detected, false otherwise.
 ******/
function is_carp_defined() {
	/* is carp compiled into the kernel and userland? */
	$command = "/sbin/sysctl -a | grep carp";
	$fd = popen($command . " 2>&1 ", "r");
	if(!$fd) {
		log_error("Warning, could not execute command {$command}");
		return 0;
	}
	while(!feof($fd)) {
		$tmp .= fread($fd,49);
	}
	fclose($fd);

	if($tmp == "")
		return false;
	else
		return true;
}

/****f* pfsense-utils/find_number_of_created_carp_interfaces
 * NAME
 *   find_number_of_created_carp_interfaces - Return the number of CARP interfaces.
 * RESULT
 *   $tmp	- Number of currently created CARP interfaces.
 ******/
function find_number_of_created_carp_interfaces($flush = false) {
	global $carp_interface_count_cache;

	if (!isset($carp_interface_count_cache) or $flush) {
		$command = "/sbin/ifconfig | /usr/bin/grep \"carp*:\" | /usr/bin/wc -l";
		$fd = popen($command . " 2>&1 ", "r");
		if(!$fd) {
			log_error("Warning, could not execute command {$command}");
			return 0;
		}
		while(!feof($fd)) {
			$tmp .= fread($fd,49);
		}
		fclose($fd);
		$carp_interface_count_cache = intval($tmp);
	}
	return $carp_interface_count_cache;
}

function link_carp_interface_to_parent($interface) {
	global $config;

	if ($interface == "")
		return;

	$carp_ip = find_interface_ip($interface);
	if (!is_ipaddr($carp_ip))
		return;

	/* if list */
        $ifdescrs = get_configured_interface_list();
	foreach ($ifdescrs as $ifdescr => $ifname) {
		$interfaceip = get_interface_ip($ifname);
		$subnet_bits = get_interface_subnet($ifname);
		$subnet_ip = gen_subnet("{$interfaceip}", "{$subnet_bits}");
		if(ip_in_subnet($carp_ip, "{$subnet_ip}/{$subnet_bits}"))
			return $ifname;
	}

	return "";
}

/****f* pfsense-utils/link_ip_to_carp_interface
 * NAME
 *   link_ip_to_carp_interface - Find where a CARP interface links to.
 * INPUTS
 *   $ip
 * RESULT
 *   $carp_ints
 ******/
function link_ip_to_carp_interface($ip) {
	global $config;

	if (!is_ipaddr($ip))
		return;

	$carp_ints = "";
	$num_carp_ints = find_number_of_created_carp_interfaces();
	for ($x=0; $x<$num_carp_ints; $x++) {
		$carp_int = "carp{$x}";
		$carp_ip = find_interface_ip($carp_int);
		$carp_subnet = find_virtual_ip_netmask($carp_ip);
		$starting_ip = gen_subnet("{$carp_ip}", "{$carp_subnet}");
		if (ip_in_subnet($ip, "{$starting_ip}/{$carp_subnet}"))
			if(!stristr($carp_ints, $carp_int))
				$carp_ints .= " " . $carp_int;
	}
	
	return $carp_ints;
}

/****f* pfsense-utils/find_virtual_ip_netmask
 * NAME
 *   find_virtual_ip_netmask - Finds a virtual ip's subnet mask'
 * INPUTS
 *   $ip - ip address to locate subnet mask of
 * RESULT
 *   String containing the command's result.
 * NOTES
 *   This function returns the command's stdout and stderr.
 ******/
function find_virtual_ip_netmask($ip) {
        global $config;
        foreach($config['virtualip']['vip'] as $vip) {
                if($ip == $vip['subnet'])
                        return $vip['subnet_bits'];
        }
}

/*
 * convert_ip_to_network_format($ip, $subnet): converts an ip address to network form

 */
function convert_ip_to_network_format($ip, $subnet) {
	$ipsplit = split('[.]', $ip);
	$string = $ipsplit[0] . "." . $ipsplit[1] . "." . $ipsplit[2] . ".0/" . $subnet;
	return $string;
}

/*
 * get_carp_interface_status($carpinterface): returns the status of a carp ip
 */
function get_carp_interface_status($carpinterface) {
	/* basically cache the contents of ifconfig statement
	to speed up this routine */
	global $carp_query;
	if($carp_query == "")
	$carp_query = split("\n", `/sbin/ifconfig | /usr/bin/grep carp`);
	$found_interface = 0;
	foreach($carp_query as $int) {
		if($found_interface == 1) {
			if(stristr($int, "MASTER")) return "MASTER";
			if(stristr($int, "BACKUP")) return "BACKUP";
			if(stristr($int, "INIT")) return "INIT";
			return false;
		}
		if(stristr($int, $carpinterface) == true)
		$found_interface=1;
	}
	return;
}

/*
 * get_pfsync_interface_status($pfsyncinterface): returns the status of a pfsync
 */
function get_pfsync_interface_status($pfsyncinterface) {
    $result = does_interface_exist($pfsyncinterface);
    if($result <> true) return;
    $status = exec_command("/sbin/ifconfig {$pfsyncinterface} | /usr/bin/grep \"pfsync:\" | /usr/bin/cut -d\" \" -f5");
    return $status;
}

/*
 * find_carp_interface($ip): return the carp interface where an ip is defined
 */
function find_carp_interface($ip) {
	global $find_carp_ifconfig;
	if($find_carp_ifconfig == "") {
		$find_carp_ifconfig = array();
		$num_carp_ints = find_number_of_created_carp_interfaces();
		for($x=0; $x<$num_carp_ints; $x++) {
			$find_carp_ifconfig[$x] = exec_command("/sbin/ifconfig carp{$x}");
		}
	}
	$carps = 0;
	foreach($find_carp_ifconfig as $fci) {
		if(stristr($fci, $ip . " ") == true)
			return "carp{$carps}";
		$carps++;
	}
}

/*
 * add_rule_to_anchor($anchor, $rule): adds the specified rule to an anchor
 */
function add_rule_to_anchor($anchor, $rule, $label) {
	mwexec("echo " . $rule . " | /sbin/pfctl -a " . $anchor . ":" . $label . " -f -");
}

/*
 * remove_text_from_file
 * remove $text from file $file
 */
function remove_text_from_file($file, $text) {
	global $fd_log;
	if($fd_log)
		fwrite($fd_log, "Adding needed text items:\n");
	$filecontents = file_get_contents($file);
	$textTMP = str_replace($text, "", $filecontents);
	$text = $textTMP;
	if($fd_log)
		fwrite($fd_log, $text);
	$fd = fopen($file, "w");
	fwrite($fd, $text);
	fclose($fd);
}

/*
 * add_text_to_file($file, $text): adds $text to $file.
 * replaces the text if it already exists.
 */
function add_text_to_file($file, $text, $replace = false) {
	if(file_exists($file) and is_writable($file)) {
		$filecontents = file($file);
		$fout = fopen($file, "w");

		$filecontents = array_map('rtrim', $filecontents);
		array_push($filecontents, $text);
		if ($replace)
			$filecontents = array_unique($filecontents);

		$file_text = implode("\n", $filecontents);

		fwrite($fout, $file_text);
		fclose($fout);
		return true;
	} else {
		return false;
	}
}

/*
 *   after_sync_bump_adv_skew(): create skew values by 1S
 */
function after_sync_bump_adv_skew() {
	global $config, $g;
	$processed_skew = 1;
	$a_vip = &$config['virtualip']['vip'];
	foreach ($a_vip as $vipent) {
		if($vipent['advskew'] <> "") {
			$processed_skew = 1;
			$vipent['advskew'] = $vipent['advskew']+1;
		}
	}
	if($processed_skew == 1)
		write_config("After synch increase advertising skew");
}

/*
 * get_filename_from_url($url): converts a url to its filename.
 */
function get_filename_from_url($url) {
	return basename($url);
}

/*
 *   get_dir: return an array of $dir
 */
function get_dir($dir) {
	$dir_array = array();
	$d = dir($dir);
	while (false !== ($entry = $d->read())) {
		array_push($dir_array, $entry);
	}
	$d->close();
	return $dir_array;
}

/****f* pfsense-utils/WakeOnLan
 * NAME
 *   WakeOnLan - Wake a machine up using the wake on lan format/protocol
 * RESULT
 *   true/false - true if the operation was successful
 ******/
function WakeOnLan($addr, $mac)
{
	$addr_byte = explode(':', $mac);
	$hw_addr = '';

	for ($a=0; $a < 6; $a++)
		$hw_addr .= chr(hexdec($addr_byte[$a]));

	$msg = chr(255).chr(255).chr(255).chr(255).chr(255).chr(255);

	for ($a = 1; $a <= 16; $a++)
		$msg .= $hw_addr;

	// send it to the broadcast address using UDP
	$s = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	if ($s == false) {
		log_error("Error creating socket!");
		log_error("Error code is '".socket_last_error($s)."' - " . socket_strerror(socket_last_error($s)));
	} else {
		// setting a broadcast option to socket:
		$opt_ret =  socket_set_option($s, 1, 6, TRUE);
		if($opt_ret < 0)
			log_error("setsockopt() failed, error: " . strerror($opt_ret));
		$e = socket_sendto($s, $msg, strlen($msg), 0, $addr, 2050);
		socket_close($s);
		log_error("Magic Packet sent ({$e}) to {$addr} MAC={$mac}");
		return true;
	}

	return false;
}

/*
 * gather_altq_queue_stats():  gather altq queue stats and return an array that
 *                             is queuename|qlength|measured_packets
 *                             NOTE: this command takes 5 seconds to run
 */
function gather_altq_queue_stats($dont_return_root_queues) {
	exec("/sbin/pfctl -vvsq", $stats_array);
	$queue_stats = array();
	foreach ($stats_array as $stats_line) {
		$match_array = "";
		if (preg_match_all("/queue\s+(\w+)\s+/",$stats_line,$match_array))
			$queue_name = $match_array[1][0];
		if (preg_match_all("/measured:\s+.*packets\/s\,\s(.*)\s+\]/",$stats_line,$match_array))
			$speed = $match_array[1][0];
		if (preg_match_all("/borrows:\s+(.*)/",$stats_line,$match_array))
			$borrows = $match_array[1][0];
		if (preg_match_all("/suspends:\s+(.*)/",$stats_line,$match_array))
			$suspends = $match_array[1][0];
		if (preg_match_all("/dropped pkts:\s+(.*)/",$stats_line,$match_array))
			$drops = $match_array[1][0];
		if (preg_match_all("/measured:\s+(.*)packets/",$stats_line,$match_array)) {
			$measured = $match_array[1][0];
			if($dont_return_root_queues == true)
				if(stristr($queue_name,"root_") == false)
					array_push($queue_stats, "{$queue_name}|{$speed}|{$measured}|{$borrows}|{$suspends}|{$drops}");
		}
	}
	return $queue_stats;
}

/*
 * reverse_strrchr($haystack, $needle):  Return everything in $haystack up to the *last* instance of $needle.
 *					 Useful for finding paths and stripping file extensions.
 */
function reverse_strrchr($haystack, $needle) {
	return strrpos($haystack, $needle) ? substr($haystack, 0, strrpos($haystack, $needle) +1 ) : false;
}

/*
 *  backup_config_section($section): returns as an xml file string of
 *                                   the configuration section
 */
function backup_config_section($section) {
	global $config;
	$new_section = &$config[$section];
	/* generate configuration XML */
	$xmlconfig = dump_xml_config($new_section, $section);
	$xmlconfig = str_replace("<?xml version=\"1.0\"?>", "", $xmlconfig);
	return $xmlconfig;
}

/*
 *  restore_config_section($section, new_contents): restore a configuration section,
 *                                                  and write the configuration out
 *                                                  to disk/cf.
 */
function restore_config_section($section, $new_contents) {
	global $config, $g;
	conf_mount_rw();
	$fout = fopen("{$g['tmp_path']}/tmpxml","w");
	fwrite($fout, $new_contents);
	fclose($fout);
	$section_xml = parse_xml_config($g['tmp_path'] . "/tmpxml", $section);
	$config[$section] = &$section_xml;
	unlink($g['tmp_path'] . "/tmpxml");
	write_config("Restored {$section} of config file (maybe from CARP partner)");
	conf_mount_ro();
	return;
}

/*
 *  merge_config_section($section, new_contents):   restore a configuration section,
 *                                                  and write the configuration out
 *                                                  to disk/cf.  But preserve the prior
 * 													structure if needed
 */
function merge_config_section($section, $new_contents) {
	global $config;
	conf_mount_rw();
	$fname = get_tmp_filename();
	$fout = fopen($fname, "w");
	fwrite($fout, $new_contents);
	fclose($fout);
	$section_xml = parse_xml_config($fname, $section);
	$config[$section] = $section_xml;
	unlink($fname);
	write_config("Restored {$section} of config file (maybe from CARP partner)");
	conf_mount_ro();
	return;
}

/*
 * http_post($server, $port, $url, $vars): does an http post to a web server
 *                                         posting the vars array.
 * written by nf@bigpond.net.au
 */
function http_post($server, $port, $url, $vars) {
	$user_agent = "Mozilla/4.0 (compatible; MSIE 5.5; Windows 98)";
	$urlencoded = "";
	while (list($key,$value) = each($vars))
		$urlencoded.= urlencode($key) . "=" . urlencode($value) . "&";
	$urlencoded = substr($urlencoded,0,-1);
	$content_length = strlen($urlencoded);
	$headers = "POST $url HTTP/1.1
Accept: */*
Accept-Language: en-au
Content-Type: application/x-www-form-urlencoded
User-Agent: $user_agent
Host: $server
Connection: Keep-Alive
Cache-Control: no-cache
Content-Length: $content_length

";

	$errno = "";
	$errstr = "";
	$fp = fsockopen($server, $port, $errno, $errstr);
	if (!$fp) {
		return false;
	}

	fputs($fp, $headers);
	fputs($fp, $urlencoded);

	$ret = "";
	while (!feof($fp))
		$ret.= fgets($fp, 1024);
	fclose($fp);

	return $ret;
}

/*
 *  php_check_syntax($code_tocheck, $errormessage): checks $code_to_check for errors
 */
if (!function_exists('php_check_syntax')){
	function php_check_syntax($code_to_check, &$errormessage){
		return false;
		$fout = fopen("/tmp/codetocheck.php","w");
		$code = $_POST['content'];
		$code = str_replace("<?php", "", $code);
		$code = str_replace("?>", "", $code);
		fwrite($fout, "<?php\n\n");
		fwrite($fout, $code_to_check);
		fwrite($fout, "\n\n?>\n");
		fclose($fout);
		$command = "/usr/local/bin/php -l /tmp/codetocheck.php";
		$output = exec_command($command);
		if (stristr($output, "Errors parsing") == false) {
			echo "false\n";
			$errormessage = '';
			return(false);
		} else {
			$errormessage = $output;
			return(true);
		}
	}
}

/*
 *  php_check_filename_syntax($filename, $errormessage): checks the file $filename for errors
 */
if (!function_exists('php_check_syntax')){
	function php_check_syntax($code_to_check, &$errormessage){
		return false;
		$command = "/usr/local/bin/php -l " . $code_to_check;
		$output = exec_command($command);
		if (stristr($output, "Errors parsing") == false) {
			echo "false\n";
			$errormessage = '';
			return(false);
		} else {
			$errormessage = $output;
			return(true);
		}
	}
}

/*
 * rmdir_recursive($path,$follow_links=false)
 * Recursively remove a directory tree (rm -rf path)
 * This is for directories _only_
 */
function rmdir_recursive($path,$follow_links=false) {
	$to_do = glob($path);
	if(!is_array($to_do)) $to_do = array($to_do);
	foreach($to_do as $workingdir) { // Handle wildcards by foreaching.
		if(file_exists($workingdir)) {
			if(is_dir($workingdir)) {
				$dir = opendir($workingdir);
				while ($entry = readdir($dir)) {
					if (is_file("$workingdir/$entry") || ((!$follow_links) && is_link("$workingdir/$entry")))
						unlink("$workingdir/$entry");
					elseif (is_dir("$workingdir/$entry") && $entry!='.' && $entry!='..')
						rmdir_recursive("$workingdir/$entry");
				}
				closedir($dir);
				rmdir($workingdir);
			} elseif (is_file($workingdir)) {
				unlink($workingdir);
			}
               	}
	}
	return;
}

/*
 * call_pfsense_method(): Call a method exposed by the pfsense.com XMLRPC server.
 */
function call_pfsense_method($method, $params, $timeout = 0) {
	global $g, $config;

	$ip = gethostbyname($g['product_website']);
	if($ip == $g['product_website'])
		return false;
	global $g, $config;
	$xmlrpc_base_url = $g['xmlrpcbaseurl'];
	$xmlrpc_path = $g['xmlrpcpath'];
	$msg = new XML_RPC_Message($method, array(XML_RPC_Encode($params)));
	$cli = new XML_RPC_Client($xmlrpc_path, $xmlrpc_base_url);
	$resp = $cli->send($msg, $timeout);
	if(!$resp) {
		log_error("XMLRPC communication error: " . $cli->errstr);
		return false;
	} elseif($resp->faultCode()) {
		log_error("XMLRPC request failed with error " . $resp->faultCode() . ": " . $resp->faultString());
		return false;
	} else {
		return XML_RPC_Decode($resp->value());
	}
}

/*
 * check_firmware_version(): Check whether the current firmware installed is the most recently released.
 */
function check_firmware_version($tocheck = "all", $return_php = true) {
	global $g, $config;
	$ip = gethostbyname($g['product_website']);
	if($ip == $g['product_website'])
		return false;
	$rawparams = array("firmware" => array("version" => trim(file_get_contents('/etc/version'))),
		"kernel"   => array("version" => trim(file_get_contents('/etc/version_kernel'))),
		"base"     => array("version" => trim(file_get_contents('/etc/version_base'))),
		"platform" => trim(file_get_contents('/etc/platform'))
		);
	if($tocheck == "all") {
		$params = $rawparams;
	} else {
		foreach($tocheck as $check) {
			$params['check'] = $rawparams['check'];
			$params['platform'] = $rawparams['platform'];
		}
	}
	if($config['system']['firmware']['branch']) {
		$params['branch'] = $config['system']['firmware']['branch'];
	}
	if(!$versions = call_pfsense_method('pfsense.get_firmware_version', $params)) {
		return false;
	} else {
		$versions["current"] = $params;
	}
	return $versions;
}

function get_disk_info() {
	$diskout = "";
	exec("/bin/df -h | /usr/bin/grep -w '/' | /usr/bin/awk '{ print $2, $3, $4, $5 }'", $diskout);
	return explode(' ', $diskout[0]);
	// $size, $used, $avail, $cap
}

/****f* pfsense-utils/strncpy
 * NAME
 *   strncpy - copy strings
 * INPUTS
 *   &$dst, $src, $length
 * RESULT
 *   none
 ******/
function strncpy(&$dst, $src, $length) {
	if (strlen($src) > $length) {
		$dst = substr($src, 0, $length);
	} else {
		$dst = $src;
	}
}

/****f* pfsense-utils/reload_interfaces_sync
 * NAME
 *   reload_interfaces - reload all interfaces
 * INPUTS
 *   none
 * RESULT
 *   none
 ******/
function reload_interfaces_sync() {
	global $config, $g;

	/* XXX: Use locks?! */
	if (file_exists("{$g['tmp_path']}/reloading_all")) {
		log_error("WARNING: Recursive call to interfaces sync!");
		return;
	}
	touch("{$g['tmp_path']}/reloading_all");

	if($g['debug'])
		log_error("reload_interfaces_sync() is starting.");

	/* parse config.xml again */
	$config = parse_config(true);

	/* enable routing */
	system_routing_enable();
	if($g['debug'])
		log_error("Enabling system routing");

	if($g['debug'])
		log_error("Cleaning up Interfaces");

	/* set up interfaces */
	interfaces_configure();

	/* remove reloading_all trigger */
	if($g['debug'])
		log_error("Removing {$g['tmp_path']}/reloading_all");

	/* start devd back up */
	mwexec("/bin/rm /tmp/reload*");
}

/****f* pfsense-utils/reload_all
 * NAME
 *   reload_all - triggers a reload of all settings
 *   * INPUTS
 *   none
 * RESULT
 *   none
 ******/
function reload_all() {
	touch("/tmp/reload_all");
}

/****f* pfsense-utils/reload_interfaces
 * NAME
 *   reload_interfaces - triggers a reload of all interfaces
 * INPUTS
 *   none
 * RESULT
 *   none
 ******/
function reload_interfaces() {
	touch("/tmp/reload_interfaces");
}

/****f* pfsense-utils/reload_all_sync
 * NAME
 *   reload_all - reload all settings
 *   * INPUTS
 *   none
 * RESULT
 *   none
 ******/
function reload_all_sync() {
	global $config, $g;

	$g['booting'] = false;

	/* XXX: Use locks?! */
        if (file_exists("{$g['tmp_path']}/reloading_all")) {
                log_error("WARNING: Recursive call to reload all sync!");
                return;
        }
	touch("{$g['tmp_path']}/reloading_all");

	/* parse config.xml again */
	$config = parse_config(true);

	/* set up our timezone */
	system_timezone_configure();

	/* set up our hostname */
	system_hostname_configure();

	/* make hosts file */
	system_hosts_generate();

	/* generate resolv.conf */
	system_resolvconf_generate();

	/* enable routing */
	system_routing_enable();

	/* set up interfaces */
	interfaces_configure();

	/* start dyndns service */
	services_dyndns_configure();

	/* configure cron service */
	configure_cron();

	/* start the NTP client */
	system_ntp_configure();

	/* sync pw database */
	conf_mount_rw();
	mwexec("/usr/sbin/pwd_mkdb -d /etc/ /etc/master.passwd");
	conf_mount_ro();

	/* restart sshd */
	@touch("/tmp/start_sshd");

	/* restart webConfigurator if needed */
	touch("/tmp/restart_webgui");

	mwexec("/bin/rm /tmp/reload*");
}

function auto_login($status) {
	$gettytab = file_get_contents("/etc/gettytab");
	$getty_split = split("\n", $gettytab);
	conf_mount_rw();
	$fd = fopen("/etc/gettytab", "w");
	foreach($getty_split as $gs) {
		if(stristr($gs, ":ht:np:sp#115200") ) {
			if($status == true) {
				fwrite($fd, "	:ht:np:sp#115200:al=root:\n");
			} else {
				fwrite($fd, "	:ht:np:sp#115200:\n");
			}
		} else {
			fwrite($fd, "{$gs}\n");
		}
	}
	fclose($fd);
	conf_mount_ro();
}

function setup_serial_port() {
	global $g, $config;
	conf_mount_rw();
	/* serial console - write out /boot.config */
	if(file_exists("/boot.config"))
		$boot_config = file_get_contents("/boot.config");
	else
		$boot_config = "";

	if($g['platform'] <> "cdrom") {
		$boot_config_split = split("\n", $boot_config);
		$fd = fopen("/boot.config","w");
		if($fd) {
			foreach($boot_config_split as $bcs) {
				if(stristr($bcs, "-D")) {
					/* DONT WRITE OUT, WE'LL DO IT LATER */
				} else {
					if($bcs <> "")
						fwrite($fd, "{$bcs}\n");
				}
			}
			if(isset($config['system']['enableserial'])) {
				fwrite($fd, "-D");
			}
			fclose($fd);
		}
		/* serial console - write out /boot/loader.conf */
		$boot_config = file_get_contents("/boot/loader.conf");
		$boot_config_split = split("\n", $boot_config);
		$fd = fopen("/boot/loader.conf","w");
		if($fd) {
			foreach($boot_config_split as $bcs) {
				if(stristr($bcs, "console")) {
					/* DONT WRITE OUT, WE'LL DO IT LATER */
				} else {
					if($bcs <> "")
						fwrite($fd, "{$bcs}\n");
				}
			}
			if(isset($config['system']['enableserial'])) {
				fwrite($fd, "console=\"comconsole\"\n");
			}
			fclose($fd);
		}
	}
	$ttys = file_get_contents("/etc/ttys");
	$ttys_split = split("\n", $ttys);
	$fd = fopen("/etc/ttys", "w");
	foreach($ttys_split as $tty) {
		if(stristr($tty, "ttyd0")) {
			if(isset($config['system']['enableserial'])) {
				fwrite($fd, "ttyd0	\"/usr/libexec/getty bootupcli\"	dialup	on	secure\n");
			} else {
				fwrite($fd, "ttyd0	\"/usr/libexec/getty bootupcli\"	dialup	off	secure\n");
			}
		} else {
			fwrite($fd, $tty . "\n");
		}
	}
	fclose($fd);
	if(isset($config['system']['disableconsolemenu'])) {
		auto_login(false);
	} else {
		auto_login(true);
	}
	conf_mount_ro();
	return;
}

function print_value_list($list, $count = 10, $separator = ",") {
	$list = implode($separator, array_slice($list, 0, $count));
	if(count($list) < $count) {
		$list .= ".";
	} else {
		$list .= "...";
	}
	return $list;
}

/* DHCP enabled on any interfaces? */
function is_dhcp_server_enabled() 
{
	global $config;

	$dhcpdenable = false;
	
	if (!is_array($config['dhcpd']))
		return false;

	$Iflist = get_configured_interface_list();

	foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
		if (isset($dhcpifconf['enable']) && isset($Iflist[$dhcpif])) {
			$dhcpdenable = true;
			break;
		}
	}

	return $dhcpdenable;
}

//returns interface information
function get_interface_info($ifdescr) {
	global $config, $linkinfo, $netstatrninfo;

	$ifinfo = array();
	/* if list */
	$iflist = get_configured_interface_with_descr(false,true);
	
	$found = false;
    	foreach ($iflist as $if => $ifname) {
    	if ($ifdescr == $if || $ifdescr == $ifname) {
			$ifinfo['hwif'] = $config['interfaces'][$if]['if'];
			$ifinfo['if'] = get_real_interface($if);
			$found = true;
			break;
		}
	}
	if ($found == false)
		return;

	/* run netstat to determine link info */

	unset($linkinfo);
	if ($ifinfo['if'] != $ifinfo['hwif'])
		$chkif = $ifinfo['hwif'];
	else
		$chkif = $ifinfo['if'];

	exec("/usr/bin/netstat -I {$chkif} -nWb -f link", $linkinfo);

	$linkinfo = preg_split("/\s+/", $linkinfo[1]);
	if ("{$chkif}*" == $linkinfo[0])
		$ifinfo['status'] = "down";
	else if ($chkif == $linkinfo[0])
		$ifinfo['status'] = "up";
	else
		$ifinfo['status'] = "down";

	if (preg_match("/^enc|^tun|^ppp|^pptp|^ovpn/i", $ifinfo['if'])) {
		$ifinfo['inpkts'] = $linkinfo[3];
		$ifinfo['outpkts'] = $linkinfo[6];
	} else {
		$ifinfo['macaddr'] = $linkinfo[3];
		$ifinfo['inerrs'] = $linkinfo[5];
		$ifinfo['outerrs'] = $linkinfo[8];
		$ifinfo['collisions'] = $linkinfo[10];
	}

	/* Use pfctl for non wrapping 64 bit counters */
	/* Pass */
	exec("/sbin/pfctl -vvsI -i {$ifinfo['if']}", $pfctlstats);
	$pf_in4_pass = preg_split("/ +/ ", $pfctlstats[3]);
	$pf_out4_pass = preg_split("/ +/", $pfctlstats[5]);
	$in4_pass = $pf_in4_pass[5];
	$out4_pass = $pf_out4_pass[5];
	$in4_pass_packets = $pf_in4_pass[3];
	$out4_pass_packets = $pf_out4_pass[3];
	$ifinfo['inbytespass'] = $in4_pass;
	$ifinfo['outbytespass'] = $out4_pass;
	$ifinfo['inpktspass'] = $in4_pass_packets;
	$ifinfo['outpktspass'] = $out4_pass_packets;

	/* Block */
	$pf_in4_block = preg_split("/ +/", $pfctlstats[4]);
	$pf_out4_block = preg_split("/ +/", $pfctlstats[6]);
	$in4_block = $pf_in4_block[5];
	$out4_block = $pf_out4_block[5];
	$in4_block_packets = $pf_in4_block[3];
	$out4_block_packets = $pf_out4_block[3];
	$ifinfo['inbytesblock'] = $in4_block;
	$ifinfo['outbytesblock'] = $out4_block;
	$ifinfo['inpktsblock'] = $in4_block_packets;
	$ifinfo['outpktsblock'] = $out4_block_packets;

	$ifinfo['inbytes'] = $in4_pass + $in4_block;
	$ifinfo['outbytes'] = $out4_pass + $out4_block;
	$ifinfo['inpkts'] = $in4_pass_packets + $in4_block_packets;
	$ifinfo['outpkts'] = $in4_pass_packets + $out4_block_packets;
		
	$ifconfiginfo = "";
	unset($ifconfiginfo, $link0);
	exec("/sbin/ifconfig " . $ifinfo['if'], $ifconfiginfo);
	foreach ($ifconfiginfo as $ici) {
		if (preg_match("/inet (\S+)/", $ici, $matches)) {
			$ifinfo['ipaddr'] = $matches[1];
		}
		if (preg_match("/netmask (\S+)/", $ici, $matches)) {
			if (preg_match("/^0x/", $matches[1])) {
				$ifinfo['subnet'] = long2ip(hexdec($matches[1]));
			}
		}
		if (strpos($ici, 'LINK0') !== false) {
			$link0 = "down";
		}
	}

	switch ($config['interfaces'][$if]['ipaddr']) {
	/* DHCP? -> see if dhclient is up */
	case "dhcp":
		/* see if dhclient is up */
		if (is_dhcp_running($ifinfo['if']) == true)
			$ifinfo['dhcplink'] = "up";
		else
			$ifinfo['dhcplink'] = "down";

		break;
	case "carpdev-dhcp":
		/* see if dhclient is up */
		if (is_dhcp_running($ifinfo['if']) == true)
			$ifinfo['dhcplink'] = "up";
		else
			$ifinfo['dhcplink'] = "down";

		break;
	/* PPPoE interface? -> get status from virtual interface */
	case "pppoe":
		unset($linkinfo);
		exec("/usr/bin/netstat -I " . $ifinfo['if'] . " -nWb -f link", $linkinfo);
		$linkinfo = preg_split("/\s+/", $linkinfo[1]);
		if ("{$ifinfo['if']}*" == $linkinfo[0])
			$ifinfo['pppoelink'] = "down";
		else if ($ifinfo['if'] == $linkinfo[0] && !isset($link0))
			/* get PPPoE link status for dial on demand */
			$ifinfo['pppoelink'] = "up";
		else
			$ifinfo['pppoelink'] = "down";

		break;
	/* PPTP interface? -> get status from virtual interface */
	case "pptp":
		unset($linkinfo);
		exec("/usr/bin/netstat -I " . $ifinfo['if'] . " -nWb -f link", $linkinfo);
		$linkinfo = preg_split("/\s+/", $linkinfo[1]);
		if ("{$ifinfo['if']}*" == $linkinfo[0])
			$ifinfo['pptplink'] = "down";
		else if ($ifinfo['if'] == $linkinfo[0] && !isset($link0))
			/* get PPTP link status for dial on demand */
			$ifinfo['pptplink'] = "up";
		else
			$ifinfo['pptplink'] = "down";

		break;
	default:
		break;
	}

	if ($ifinfo['status'] == "up") {
		/* try to determine media with ifconfig */
		unset($ifconfiginfo);
		exec("/sbin/ifconfig " . $ifinfo['hwif'], $ifconfiginfo);
		$matches = "";
		foreach ($ifconfiginfo as $ici) {

			/* don't list media/speed for wireless cards, as it always
			   displays 2 Mbps even though clients can connect at 11 Mbps */
			if (preg_match("/media: .*? \((.*?)\)/", $ici, $matches)) {
				$ifinfo['media'] = $matches[1];
			} else if (preg_match("/media: Ethernet (.*)/", $ici, $matches)) {
				$ifinfo['media'] = $matches[1];
			} else if (preg_match("/media: IEEE 802.11 Wireless Ethernet (.*)/", $ici, $matches)) {
				$ifinfo['media'] = $matches[1];
			}

			if (preg_match("/status: (.*)$/", $ici, $matches)) {
				if ($matches[1] != "active")
					$ifinfo['status'] = $matches[1];
			}
			if (preg_match("/channel (\S*)/", $ici, $matches)) {
				$ifinfo['channel'] = $matches[1];
			}
			if (preg_match("/ssid (\".*?\"|\S*)/", $ici, $matches)) {
				if ($matches[1][0] == '"')
					$ifinfo['ssid'] = substr($matches[1], 1, -1);
				else
					$ifinfo['ssid'] = $matches[1];
			}
		}
		/* lookup the gateway */
		if (interface_has_gateway($if)) 
			$ifinfo['gateway'] = get_interface_gateway($if);
	}

	$bridge = "";
	$bridge = link_interface_to_bridge($ifdescr);
	if($bridge) {
		$bridge_text = `/sbin/ifconfig {$bridge}`;
		if(stristr($bridge_text, "blocking") <> false) {
			$ifinfo['bridge'] = "<b><font color='red'>blocking</font></b> - check for ethernet loops";
			$ifinfo['bridgeint'] = $bridge;
		} else if(stristr($bridge_text, "learning") <> false) {
			$ifinfo['bridge'] = "learning";
			$ifinfo['bridgeint'] = $bridge;
		} else if(stristr($bridge_text, "forwarding") <> false) {
			$ifinfo['bridge'] = "forwarding";
			$ifinfo['bridgeint'] = $bridge;
		}
	}

	return $ifinfo;
}

//returns cpu speed of processor. Good for determining capabilities of machine
function get_cpu_speed() {
	 return exec("sysctl hw.clockrate | awk '{ print $2 }'");
}

/* check if the wan interface is up
 * Wait for a maximum of 10 seconds
 * If the interface is up before then continue
 */
function is_wan_interface_up($interface) {
	global $g;
	global $config;
	$i = 0;
	while($i < 10) {
		if(get_interface_gateway($interface)) {
			return true;
		} else {
			sleep(1);
		}
		$i++;
	}
	return false;
}

function add_hostname_to_watch($hostname) {
	if(!is_dir("/var/db/dnscache")) {
		mkdir("/var/db/dnscache");
	}
	if((is_fqdn($hostname)) && (!is_ipaddr($hostname))) {
		$domrecords = array();
		$domips = array();
		exec("host -t A $hostname", $domrecords, $rethost);
		if($rethost == 0) {
			foreach($domrecords as $domr) {
				$doml = explode(" ", $domr);
				$domip = $doml[3];
				/* fill array with domain ip addresses */
				if(is_ipaddr($domip)) {
					$domips[] = $domip;
				}
			}
		}
		sort($domips);
		$contents = "";
		if(! empty($domips)) {
			foreach($domips as $ip) {
				$contents .= "$ip\n";
			}
		}
		file_put_contents("/var/db/dnscache/$hostname", $contents);
	}
}

function find_dns_aliases() {
	global $config, $g;
	foreach((array) $config['aliases']['alias'] as $alias) {
		$alias_value = $alias['address'];
		$alias_name = $alias['name'];
		if(stristr($alias_value, " ")) {
			$alias_split = split(" ", $alias_value);
			foreach($alias_split as $as) {
				if(is_fqdn($as)) 
					add_hostname_to_watch($as);			
			}
		} else {
			if(is_fqdn($alias_value)) 
				add_hostname_to_watch($alias_value);
		}
	}
}

function is_fqdn($fqdn) {
	$hostname = false;
	if(preg_match("/[-A-Z0-9\.]+\.[-A-Z0-9\.]+/i", $fqdn)) {
		$hostname = true;
	}
	if(preg_match("/\.\./", $fqdn)) {
		$hostname = false;
	}
	if(preg_match("/^\./i", $fqdn)) { 
		$hostname = false;
	}
	if(preg_match("/\//i", $fqdn)) {
		$hostname = false;
	}
	return($hostname);
}

function pfsense_default_state_size() {
  /* get system memory amount */
  $memory = get_memory();
  $avail = $memory[0];
  /* Be cautious and only allocate 10% of system memory to the state table */
  $max_states = (int) ($avail/10)*1000;
  return $max_states;
}

/* Compare the current hostname DNS to the DNS cache we made
 * if it has changed we return the old records
 * if no change we return true */
function compare_hostname_to_dnscache($hostname) {
	if(!is_dir("/var/db/dnscache")) {
		mkdir("/var/db/dnscache");
	}
	$hostname = trim($hostname);
	if(is_readable("/var/db/dnscache/{$hostname}")) {
		$oldcontents = file_get_contents("/var/db/dnscache/{$hostname}");
	} else {
		$oldcontents = "";
	}
	if((is_fqdn($hostname)) && (!is_ipaddr($hostname))) {
		$domrecords = array();
		$domips = array();
		exec("host -t A $hostname", $domrecords, $rethost);
		if($rethost == 0) {
			foreach($domrecords as $domr) {
				$doml = explode(" ", $domr);
				$domip = $doml[3];
				/* fill array with domain ip addresses */
				if(is_ipaddr($domip)) {
					$domips[] = $domip;
				}
			}
		}
		sort($domips);
		$contents = "";
		if(! empty($domips)) {
			foreach($domips as $ip) {
				$contents .= "$ip\n";
			}
		}
	}

	if(trim($oldcontents) != trim($contents)) {
		log_error("DNSCACHE: Found old IP {$oldcontents} and new IP {$contents}");
		return ($oldcontents);
	} else {
		return false;
	}
}

/*
 * load_glxsb() - Load the glxsb crypto module if enabled in config.
 */
function load_glxsb() {
	global $config, $g;
	$is_loaded = `/sbin/kldstat | /usr/bin/grep -c glxsb`;
	if (isset($config['system']['glxsb_enable']) && ($is_loaded == 0)) {
		mwexec("/sbin/kldload glxsb");
	}
}

?>