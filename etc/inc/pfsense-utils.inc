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
 * Copyright (C) 2005 Scott Ullrich (sullrich@gmail.com)
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

function get_tmp_file() {
	return "/tmp/tmp-" . time();
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
		if (preg_match("/nameserver (.*)/", $dns, $matches))
			$dns_servers[] = $matches[1];		
	}
	return $dns_servers;
}

 	
/****f* pfsense-utils/log_error
* NAME
*   log_error  - Sends a string to syslog.
* INPUTS
*   $error     - string containing the syslog message.
* RESULT
*   null
******/
function log_error($error) {
    $page = $_SERVER['PHP_SELF'];
    syslog(LOG_WARNING, "$page: $error");
    return;
}

/****f* pfsense-utils/get_interface_mac_address
 * NAME
 *   get_interface_mac_address - Return a interfaces mac address
 * INPUTS
 *   $interface	- interface to obtain mac address from
 * RESULT
 *   $mac - the mac address of the interface
 ******/
function get_interface_mac_address($interface) {
    $mac = exec("ifconfig {$interface} | awk '/ether/ {print $2}'");
    return trim($mac);
}

/****f* pfsense-utils/return_dir_as_array
 * NAME
 *   return_dir_as_array - Return a directory's contents as an array.
 * INPUTS
 *   $dir	- string containing the path to the desired directory.
 * RESULT
 *   $dir_array - array containing the directory's contents. This array will be empty if the path specified is invalid.
 ******/
function return_dir_as_array($dir) {
    $dir_array = array();
    if (is_dir($dir)) {
	if ($dh = opendir($dir)) {
	    while (($file = readdir($dh)) !== false) {
		$canadd = 0;
		if($file == ".") $canadd = 1;
		if($file == "..") $canadd = 1;
		if($canadd == 0)
		    array_push($dir_array, $file);
	    }
	    closedir($dh);
	}
    }
    return $dir_array;
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
    global $g;
    if($g['booting']) {
	/* translate wan, lan, opt -> real interface if needed */
	$int = filter_translate_type_to_real_interface($interface);
	if($int <> "") $interface = $int;
	$options = strtolower(`/sbin/ifconfig {$interface} | grep options`);
	$supported_ints = array('fxp');
	foreach($supported_ints as $int) {
		if(stristr($interface,$int) != false) {
			mwexec("/sbin/ifconfig {$interface} link0");
		}
	}
	if(stristr($options, "txcsum") == true)
	    mwexec("/sbin/ifconfig {$interface} txcsum 2>/dev/null");
	if(stristr($options, "rxcsum") == true)    
	    mwexec("/sbin/ifconfig {$interface} rxcsum 2>/dev/null");    
	if(stristr($options, "polling") == true)
	    mwexec("/sbin/ifconfig {$interface} polling 2>/dev/null");
    }
    return;
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
   global $config;
    $ifdescrs = array('wan', 'lan');
    for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
	$ifdescrs['opt' . $j] = "opt" . $j;
    }
    foreach($ifdescrs as $if)
	enable_hardware_offloading($if);
}

/****f* pfsense-utils/return_filename_as_array
 * NAME
 *   return_filename_as_array - Return a file's contents as an array.
 * INPUTS
 *   $filename	- string containing the path to the desired file.
 *   $strip	- array of characters to strip - default is '#'.
 * RESULT
 *   $file	- array containing the file's contents.
 * NOTES
 *   This function strips lines starting with '#' and leading/trailing whitespace by default.
 ******/
function return_filename_as_array($filename, $strip = array('#')) {
    if(file_exists($filename)) $file = file($filename);
    if(is_array($file)) {
	foreach($file as $line) $line = trim($line);
        foreach($strip as $tostrip) $file = preg_grep("/^{$tostrip}/", $file, PREG_GREP_INVERT);
    }
    return $file;
}

/****f* pfsense-utils/file_put_contents
 * NAME
 *   file_put_contents - Wrapper for file_put_contents if it doesn't exist
 * RESULT
 *   none
 ******/
if(!function_exists("file_put_contents")) {
    function file_put_contents($filename, $data) {
	$fd = fopen($filename,"w");
	fwrite($fd, $data);
	fclose($fd);
    }
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
function find_number_of_created_carp_interfaces() {
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
    $tmp = intval($tmp);
    return $tmp;
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
	if($ip == "") return;

	$ifdescrs = array('wan', 'lan');
	for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
		$ifdescrs['opt' . $j] = "opt" . $j;
	}

	$ft = split("\.", $ip);
	$ft_ip = $ft[0] . "." . $ft[1] . "." . $ft[2] . ".";

	$carp_ints = "";
	$num_carp_ints = find_number_of_created_carp_interfaces();
	foreach ($ifdescrs as $ifdescr => $ifname) {
		for($x=0; $x<$num_carp_ints; $x++) {
			$carp_int = "carp{$x}";
			$carp_ip = find_interface_ip($carp_int);
			$carp_ft = split("\.", $carp_ip);
			$carp_ft_ip = $carp_ft[0] . "." . $carp_ft[1] . "." . $carp_ft[2] . ".";
			$result = does_interface_exist($carp_int);
			if($result <> true) break;
			if($ft_ip == $carp_ft_ip)
			if(stristr($carp_ints,$carp_int) == false)
			$carp_ints .= " " . $carp_int;
		}
	}
	return $carp_ints;
}

/****f* pfsense-utils/exec_command
 * NAME
 *   exec_command - Execute a command and return a string of the result.
 * INPUTS
 *   $command	- String of the command to be executed.
 * RESULT
 *   String containing the command's result.
 * NOTES
 *   This function returns the command's stdout and stderr.
 ******/
function exec_command($command) {
    $output = array();
    exec($command . ' 2>&1 ', $output);
    return(implode("\n", $output));
}

/*
 * does_interface_exist($interface): return true or false if a interface is detected.
 */
function does_interface_exist($interface) {
    $ints = exec_command("/sbin/ifconfig -l");
    if(stristr($ints, $interface) !== false)
	return true;
    else
	return false;
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
 * find_interface_ip($interface): return the interface ip (first found)
 */
function find_interface_ip($interface) {
    if(does_interface_exist($interface) == false) return;
    $ip = exec_command("/sbin/ifconfig {$interface} | /usr/bin/grep -w \"inet\" | /usr/bin/cut -d\" \" -f 2");
    $ip = str_replace("\n","",$ip);
    return $ip;
}

function guess_interface_from_ip($ipaddress) {
    $ints = `/sbin/ifconfig -l`;
    $ints_split = split(" ", $ints);
    $ip_subnet_split = split("\.", $ipaddress);
    $ip_subnet = $ip_subnet_split[0] . "." . $ip_subnet_split[1] . "." . $ip_subnet_split[2] . ".";
    foreach($ints_split as $int) {
        $ip = find_interface_ip($int);
        $ip_split = split("\.", $ip);
        $ip_tocheck = $ip_split[0] . "." . $ip_split[1] . "." . $ip_split[2] . ".";
        if(stristr($ip_tocheck, $ip_subnet) != false) return $int;
    }
}

function filter_opt_interface_to_real($opt) {
    global $config;
    return $config['interfaces'][$opt]['if'];
}

function filter_get_opt_interface_descr($opt) {
    global $config;
    return $config['interfaces'][$opt]['descr'];
}

function get_friendly_interface_list_as_array() {
    global $config;
    $ints = array();
    $ifdescrs = array('wan', 'lan');
    for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
		$ifdescrs['opt' . $j] = "opt" . $j;
    }
    $ifdescrs = get_interface_list();
    foreach ($ifdescrs as $ifdescr => $ifname) {
		array_push($ints,$ifdescr);
    }
    return $ints;
}

/*
 * find_ip_interface($ip): return the interface where an ip is defined
 */
function find_ip_interface($ip) {
    global $config;
    $ifdescrs = array('wan', 'lan');
    for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
	$ifdescrs['opt' . $j] = "opt" . $j;
    }
    foreach ($ifdescrs as $ifdescr => $ifname) {
	$int = filter_translate_type_to_real_interface($ifname);
	$ifconfig = exec_command("/sbin/ifconfig {$int}");
	if(stristr($ifconfig,$ip) <> false)
	    return $int;
    }
    return false;
}

/*
 *  filter_translate_type_to_real_interface($interface): returns the real interface name
 *                                                       for a friendly interface.  ie: wan
 */
function filter_translate_type_to_real_interface($interface) {
    global $config;
    return $config['interfaces'][$interface]['if'];
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
			if(stristr($int, "MASTER") == true) return "MASTER";
			if(stristr($int, "BACKUP") == true) return "BACKUP";
			if(stristr($int, "INIT") == true) return "INIT";
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
	if(stristr($fci, $ip) == true)
	    return "carp{$carps}";
	$carps++;
    }
}

/*
 * find_number_of_created_bridges(): returns the number of currently created bridges
 */
function find_number_of_created_bridges() {
    return `/sbin/ifconfig | grep \"bridge[0-999]\:" | wc -l`;
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
    fwrite($fd_log, "Adding needed text items:\n");
    $filecontents = exec_command_and_return_text("cat " . $file);
    $textTMP = str_replace($text, "", $filecontents);
    $text .= $textTMP;
    fwrite($fd_log, $text . "\n");
    $fd = fopen($file, "w");
    fwrite($fd, $text);
    fclose($fd);
}

/*
 * add_text_to_file($file, $text): adds $text to $file.
 * replaces the text if it already exists.
 */
function add_text_to_file($file, $text) {
	if(file_exists($file) and is_writable($file)) {
		$filecontents = file($file);
		$filecontents[] = $text;
		$tmpfile = get_tmp_file();
		$fout = fopen($tmpfile, "w");
		foreach($filecontents as $line) {
			fwrite($fout, rtrim($line) . "\n");
		}
		fclose($fout);
		rename($tmpfile, $file);
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
 *   update_output_window: update bottom textarea dynamically.
 */
function update_output_window($text) {
    $log = ereg_replace("\n", "\\n", $text);
    echo "\n<script language=\"JavaScript\">this.document.forms[0].output.value = \"" . $log . "\";</script>";
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

/*
 *   update_output_window: update top textarea dynamically.
 */
function update_status($status) {
    echo "\n<script language=\"JavaScript\">document.forms[0].status.value=\"" . $status . "\";</script>";
}

/*
 *   exec_command_and_return_text_array: execute command and return output
 */
function exec_command_and_return_text_array($command) {
	$fd = popen($command . " 2>&1 ", "r");
	while(!feof($fd)) {
		$tmp .= fread($fd,49);
	}
	fclose($fd);
	$temp_array = split("\n", $tmp);
	return $temp_array;
}

/*
 *   exec_command_and_return_text: execute command and return output
 */
function exec_command_and_return_text($command) {
    return exec_command($command);
}

/*
 *   exec_command_and_return_text: execute command and update output window dynamically
 */
function execute_command_return_output($command) {
    global $fd_log;
    $fd = popen($command . " 2>&1 ", "r");
    echo "\n<script language=\"JavaScript\">this.document.forms[0].output.value = \"\";</script>";
    $counter = 0;
    $counter2 = 0;
    while(!feof($fd)) {
	$tmp = fread($fd, 50);
	$tmp1 = ereg_replace("\n","\\n", $tmp);
	$text = ereg_replace("\"","'", $tmp1);
	if($lasttext == "..") {
	    $text = "";
	    $lasttext = "";
	    $counter=$counter-2;
	} else {
	    $lasttext .= $text;
	}
	if($counter > 51) {
	    $counter = 0;
	    $extrabreak = "\\n";
	} else {
	    $extrabreak = "";
	    $counter++;
	}
	if($counter2 > 600) {
	    echo "\n<script language=\"JavaScript\">this.document.forms[0].output.value = \"\";</script>";
	    $counter2 = 0;
	} else
	    $counter2++;
	echo "\n<script language=\"JavaScript\">this.document.forms[0].output.value = this.document.forms[0].output.value + \"" . $text . $extrabreak .  "\"; f('output'); </script>";
    }
    fclose($fd);
}

/*
 * convert_friendly_interface_to_real_interface_name($interface): convert WAN to FXP0
 */
function convert_friendly_interface_to_real_interface_name($interface) {
    global $config;
    $lc_interface = strtolower($interface);
    if($lc_interface == "lan") return $config['interfaces']['lan']['if'];
    if($lc_interface == "wan") return $config['interfaces']['wan']['if'];
    $ifdescrs = array();
    for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++)
	$ifdescrs['opt' . $j] = "opt" . $j;
    foreach ($ifdescrs as $ifdescr => $ifname) {
	if(strtolower($ifname) == $lc_interface)
	    return $config['interfaces'][$ifname]['if'];
	if(strtolower($config['interfaces'][$ifname]['descr']) == $lc_interface)
	    return $config['interfaces'][$ifname]['if'];
    }
    return $interface;
}

/*
 * convert_real_interface_to_friendly_interface_name($interface): convert fxp0 -> wan, etc.
 */
function convert_real_interface_to_friendly_interface_name($interface) {
    global $config;
    $ifdescrs = array('wan', 'lan');
    for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++)
	$ifdescrs['opt' . $j] = "opt" . $j;
    foreach ($ifdescrs as $ifdescr => $ifname) {
	$int = filter_translate_type_to_real_interface($ifname);
	if($ifname == $interface) return $ifname;
	if($int == $interface) return $ifname;
    }
    return $interface;
}

/*
 * update_progress_bar($percent): updates the javascript driven progress bar.
 */
function update_progress_bar($percent) {
    if($percent > 100) $percent = 1;
    echo "\n<script type=\"text/javascript\" language=\"javascript\">";
    echo "\ndocument.progressbar.style.width='" . $percent . "%';";
    echo "\n</script>";
}

/*
 * gather_altq_queue_stats():  gather alq queue stats and return an array that
 *                             is queuename|qlength|measured_packets
 *                             NOTE: this command takes 5 seconds to run
 */
function gather_altq_queue_stats($dont_return_root_queues) {
    mwexec("/usr/bin/killall -9 pfctl");
    $stats = `/sbin/pfctl -vvsq & /bin/sleep 5;/usr/bin/killall pfctl 2>/dev/null`;
    $stats_array = split("\n", $stats);
    $queue_stats = array();
    foreach ($stats_array as $stats_line) {
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
function reverse_strrchr($haystack, $needle)
{
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
 *  backup_config_section($section): returns as an xml file string of
 *                                   the configuration section
 */
function backup_vip_config_section() {
    global $config;
    $new_section = &$config['virtualip'];
    foreach($new_section as $section) {
	if($section['mode'] == "proxyarp") {
		unset($section);		
	}
	if($section['advskew'] <> "") {
		$section['advskew']++;
	}
    }
    /* generate configuration XML */
    $xmlconfig = dump_xml_config($new_section, "virtualip");
    $xmlconfig = str_replace("<?xml version=\"1.0\"?>", "", $xmlconfig);
    return $xmlconfig;
}

/*
 *  restore_config_section($section, new_contents): restore a configuration section,
 *                                                  and write the configuration out
 *                                                  to disk/cf.
 */
function restore_config_section($section, $new_contents) {
    global $config;
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
 * safe_mkdir($path, $mode = 0755)
 * create directory if it doesn't already exist and isn't a file!
 */
function safe_mkdir($path, $mode=0755) {
	global $g;

	/* cdrom is ro. */
	if($g['platform'] == "cdrom")
		return false;
	
	if (!is_file($path) && !is_dir($path))
		return mkdir($path, $mode);
	else
		return false;
}

/*
 * make_dirs($path, $mode = 0755)
 * create directory tree recursively (mkdir -p)
 */
function make_dirs($path, $mode = 0755) {
	/* is dir already created? */
	if(is_dir($path)) return;
	/* create directory in question */
	$to_create = explode("/", $path);
	foreach($to_create as $tc) 
	    if(!is_dir($tc))
		safe_mkdir($path, $mode);
}

/*
 * check_firmware_version(): Check whether the current firmware installed is the most recently released.
 */
function check_firmware_version($tocheck = "all", $return_php = true) {
        global $g, $config;
	$xmlrpc_base_url = $g['xmlrpcbaseurl'];
        $xmlrpc_path = $g['xmlrpcpath'];
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
	$xmlparams = php_value_to_xmlrpc($params);
        $msg = new XML_RPC_Message('pfsense.get_firmware_version', array($xmlparams));
        $cli = new XML_RPC_Client($xmlrpc_path, $xmlrpc_base_url);
	//$cli->setDebug(1);
	$resp = $cli->send($msg, 10);
	if(!$resp or $resp->faultCode()) {
		$raw_versions = false;
	} else {
		$raw_versions = xmlrpc_value_to_php($resp->value());
		$raw_versions["current"] = $params;
	}
	return $raw_versions;
}

function get_disk_info() {
        exec("df -h | grep -w '/' | awk '{ print $2, $3, $4, $5 }'", $diskout);
        return explode(' ', $diskout[0]);
        // $size, $used, $avail, $cap
}

/****f* pfsense-utils/display_top_tabs
 * NAME
 *   display_top_tabs - display tabs with rounded edges
 * INPUTS
 *   $text	- array of tabs
 * RESULT
 *   null
 ******/
    function display_top_tabs($tab_array) {
	    echo "<table cellpadding='0' cellspacing='0'>\n";
	    echo " <tr height='1'>\n";
	    $tabscounter = 0;
	    foreach ($tab_array as $ta) {
		    if($ta[1] == true) {
			    echo "  <td bgcolor='#EEEEEE' onClick=\"document.location='{$ta[2]}'\"><div id='tabactive'></div></td>\n";
		    } else {
			    echo "  <td bgcolor='#777777' onClick=\"document.location='{$ta[2]}'\"><div id='tabdeactive{$tabscounter}'></div></td>\n";
		    }
		    $tabscounter++;
	    }
	    echo "</tr>\n<tr>\n";
	    foreach ($tab_array as $ta) {
		    if($ta[1] == true) {
			    echo "  <td bgcolor='#EEEEEE' onClick=\"document.location='{$ta[2]}'\"><B>&nbsp;&nbsp;&nbsp;{$ta[0]}";
			    echo "&nbsp;&nbsp;&nbsp;";
			    echo "<font size='-12'>&nbsp;</td>\n";
		    } else {
			    echo "  <td bgcolor='#777777' onClick=\"document.location='{$ta[2]}'\"><B>&nbsp;&nbsp;&nbsp;<a href='{$ta[2]}'>";
			    echo "<font color='white'>{$ta[0]}</a>&nbsp;&nbsp;&nbsp;";
			    echo "<font size='-12'>&nbsp;</td>\n";
		    }
	    }
	    echo "</tr>\n<tr height='5px'>\n";
	    foreach ($tab_array as $ta) {
		    if($ta[1] == true) {
			    echo "  <td bgcolor='#EEEEEE' onClick=\"document.location='{$ta[2]}'\"></td>\n";
		    } else {
			    echo "  <td bgcolor='#777777' onClick=\"document.location='{$ta[2]}'\"></td>\n";
		    }
		    $tabscounter++;
	    }
	    echo " </tr>\n";
	    echo "</table>\n";
	    
	    echo "<script type=\"text/javascript\">";
	    echo "NiftyCheck();\n";
	    echo "Rounded(\"div#tabactive\",\"top\",\"#FFF\",\"#EEEEEE\",\"smooth\");\n";
	    for($x=0; $x<$tabscounter; $x++) 
		    echo "Rounded(\"div#tabdeactive{$x}\",\"top\",\"#FFF\",\"#777777\",\"smooth\");\n";
	    echo "</script>";
    }


/****f* pfsense-utils/display_topbar
 * NAME
 *   display_topbar - top a table off with rounded edges
 * INPUTS
 *   $text	- (optional) Text to include in bar
 * RESULT
 *   null
 ******/
function display_topbar($text = "", $bg_color="#990000", $replace_color="#FFFFFF", $rounding_style="smooth") {	    
	echo "     <table width='100%' cellpadding='0' cellspacing='0'>\n";
	echo "       <tr height='1'>\n";
	echo "         <td width='100%' valign='top' color='{$bg_color}' bgcolor='{$bg_color}'>";
	echo "		<div id='topbar'></div></td>\n";
	echo "       </tr>\n";
	echo "       <tr height='1'>\n";
	if ($text != "")
		echo "         <td height='1' class='listtopic'>{$text}</td>\n";
	else
		echo "         <td height='1' class='listtopic'></td>\n";
	echo "       </tr>\n";
	echo "     </table>";
	echo "<script type=\"text/javascript\">";
	echo "NiftyCheck();\n";
	echo "Rounded(\"div#topbar\",\"top\",\"{$replace_color}\",\"{$bg_color}\",\"{$rounding_style}\");\n";
	echo "</script>";
}

/****f* pfsense-utils/generate_random_mac_address
 * NAME
 *   generate_random_mac - generates a random mac address
 * INPUTS
 *   none
 * RESULT
 *   $mac - a random mac address
 ******/
function generate_random_mac_address() {
	$mac = "00:a0:8e";
	for($x=0; $x<3; $x++) 
	    $mac .= ":" . dechex(rand(16, 255));

	return $mac;
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
	
	if(file_exists("{$g['tmp_path']}/config.cache"))
		unlink("{$g['tmp_path']}/config.cache");
	
	/* parse config.xml again */
	$config = parse_config(true);
	
	/* set up LAN interface */
	interfaces_lan_configure();

	/* set up WAN interface */
	interfaces_wan_configure();

	/* set up Optional interfaces */
	interfaces_optional_configure();
        
	/* set up static routes */
	system_routing_configure();
	
	/* bring up carp interfaces */
	interfaces_carp_bringup();

	/* enable routing */
	system_routing_enable();
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

/****f* pfsense-utils/sync_webgui_passwords
 * NAME
 *   sync_webgui_passwords - syncs webgui and ssh passwords
 * INPUTS
 *   none
 * RESULT
 *   none
 ******/
function sync_webgui_passwords() {
	conf_mount_rw();
	mwexec("/usr/sbin/pwd_mkdb -d /etc/ /etc/master.passwd");
	mwexec("/usr/sbin/pwd_mkdb /etc/master.passwd");
	conf_mount_ro();
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
	
	if(file_exists("{$g['tmp_path']}/config.cache"))
		unlink("{$g['tmp_path']}/config.cache");
	
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

	/* set up LAN interface */
	interfaces_lan_configure();

	/* set up WAN interface */
	interfaces_wan_configure();

	/* set up Optional interfaces */
	interfaces_optional_configure();
        
	/* bring up carp interfaces */
	interfaces_carp_configure();
	
	/* set up static routes */
	system_routing_configure();

	/* enable routing */
	system_routing_enable();
	
	/* ensure passwords are sync'd */
	system_password_configure();

	/* start dnsmasq service */
	services_dnsmasq_configure();

	/* start dyndns service */
	services_dyndns_configure();

	/* start DHCP service */
	services_dhcpd_configure();

	/* start the NTP client */
	system_ntp_configure();

	/* start ftp proxy helpers if they are enabled */
	system_start_ftp_helpers();

	/* bring up carp interfaces */
	interfaces_carp_bringup();

        /* reload the filter */
	filter_configure();
	
	/* sync pw database */
	conf_mount_rw();
	mwexec("/usr/sbin/pwd_mkdb -d /etc/ /etc/master.passwd");
	mwexec("/usr/sbin/pwd_mkdb /etc/master.passwd");
	conf_mount_ro();
	
}

?>