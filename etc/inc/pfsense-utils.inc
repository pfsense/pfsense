<?php
/*
	pfSense-utils.inc
	Utilities specific to pfSense
	part of pfSense (www.pfSense.com)

	Copyright (C) 2005 Scott Ullrich (sullrich@gmail.com)
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
 * log_error: send string to syslog
 */
function log_error($error) {
    syslog(LOG_WARNING, $error);
    return;
}

/*
 * return_dir_as_array($dir): returns $dir contents as an array
 */
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

/*
 * return_dir_as_array($filenaem): returns $filename contents as a string
 */
function return_filename_as_string($filename) {
    $tmp = "";
    $fd = fopen($filename, "r");
    if(!$fd) {
	log_error("Could not open {$filename}");
	return;
    }
    while(!feof($fd)) {
	$tmp .= fread($fd,49);
    }
    fclose($fd);
    return $tmp;
}

/*
 * is_carp_defined: returns true if carp is detected in kernel
 */
function is_carp_defined() {
	/* is carp compiled into the kernel and userland? */
	$command = "/sbin/sysctl -a | grep carp";
	$fd = popen($command . " 2>&1 ", "r");
	if(!$fd) {
		log_error("Warning, could not execute command ");
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

/*
 * exec_command($command): execute command return string of result
 */
function exec_command($command) {
            $counter = 0;
            $tmp = "";
            $fd = popen($command . " 2>&1 ", "r");
            while(!feof($fd)) {
                        $tmp .= fread($fd,49);
            }
            fclose($fd);
            return $tmp;
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
    $ip = exec_command("/sbin/ifconfig {$interface} | grep -w \"inet\" | cut -d\" \" -f 2");
    return $ip;
}

/*
 * find_ip_interface($ip): return the interface where an ip is defined
 */
function find_ip_interface($ip) {
	$i = 0;
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
 * get_carp_interface_status($carpinterface): returns the status of a carp ip
 */
function get_carp_interface_status($carpinterface) {
    $status = exec_command("/sbin/ifconfig {$carpinterface} | grep \"carp:\" | cut -d\" \" -f2");
    return $status;
}

/*
 * get_pfsync_interface_status($pfsyncinterface): returns the status of a pfsync
 */
function get_pfsync_interface_status($pfsyncinterface) {
    $status = exec_command("/sbin/ifconfig {$pfsyncinterface} | grep \"pfsync:\" | cut -d\" \" -f5");
    return $status;
}

/*
 * find_carp_interface($ip): return the carp interface where an ip is defined
 */
function find_carp_interface($ip) {
    for($x=0; $x<99; $x++) {
	$ifconfig = exec_command("/sbin/ifconfig {$int}");
	if(stristr($ifconfig,$ip) <> false)
	    return "carp" . $x;
    }
}

/*
 * add_rule_to_anchor($anchor, $rule): adds the specified rule to an anchor
 */
function add_rule_to_anchor($anchor, $rule) {
    mwexec($rule . " | pfctl -a " . $anchor . " -f -");
}

?>
