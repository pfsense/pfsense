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
 * return_filename_as_array($filename): returns $filename contents as a string
 */
function return_filename_as_array($filename) {
    $file = array();
    if(!file_exists($filename)) return $file;
    $text = return_filename_as_string($filename);
    $text_split = split("\n", $text);
    return $text_split;
}

/*
 * return_dir_as_array($filename): returns $filename contents as a string
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
 * lookup pkg array id#
 */
function get_pkg_id($pkg_name) {
            global $config;
            global $pkg_config;
            $i=0;
            foreach ($config['installedpackages']['package'] as $pkg) {
                        if($pkg['name'] == $pkg_name) return $i;
                        $i++;
            }
            return -1;
}

/*
 *  get_latest_package_version($pkgname): get current version of a package.
 *  returns latest package version
 */
function get_latest_package_version($pkg_name) {
    global $config;
    fetch_latest_pkg_config();
    $pkg_config = parse_xml_config_pkg("{$g['tmp_path']}/pkg_config.xml", "pfsensepkgs");
    foreach($pkg_config['package']['packages'] as $pkg) {
	if($pkg['name'] == $pkg_name) {
	    return $pkg['version'];
	}
    }
    return;
}

/*
 * Lookup pkg_id in pkg_config.xml
 */
function get_available_pkg_id($pkg_name) {
    fetch_latest_pkg_config();
    $pkg_config = parse_xml_config_pkg("{$g['tmp_path']}/pkg_config.xml", "pfsensepkgs");
    $id = 0;
    foreach($pkg_config as $pkg) {
	if($pkg_config['name'] == $pkg_name) {
	    return $id;
	}
	$id++;
    }
    return;
}

/*
 * fetch_latest_pkg_config: download the latest pkg_config.xml to /tmp/ directory
 */
function fetch_latest_pkg_config() {
    global $g;
    if(!file_exists("/tmp/pkg_config.xml")) {
	mwexec("/usr/bin/fetch -o {$g['tmp_path']}/pkg_config.xml {$g['pkg_config_location']}");
	if(!file_exists("{$g['tmp_path']}/pkg_config.xml")) {
	    print_info_box_np("Could not download pkg_config.xml from pfSense.com.  Check your DNS settings.");
	    die;
	}
    }
    return;
}

/*
 * add_text_to_file($file, $text): adds $text to $file.
 * replaces the text if it already exists.
 */
function add_text_to_file($file, $text) {
    global $fd_log;
    fwrite($fd_log, "Adding needed text items:\n");
    $filecontents = exec_command_and_return_text("cat " . $file);
    $filecontents = str_replace($text, "", $filecontents);
    $text = $filecontents . $text;
    fwrite($fd_log, $text . "\n");
    $fd = fopen($file, "w");
    fwrite($fd, $text . "\n");
    fclose($fd);
}

/*
 * get_filename_from_url($url): converts a url to its filename.
 */
function get_filename_from_url($url) {
            $filenamesplit = split("/", $url);
            foreach($filenamesplit as $fn) $filename = $fn;
            return $filename;
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
            $counter = 0;
            $fd = popen($command . " 2>&1 ", "r");
            while(!feof($fd)) {
                        $tmp .= fread($fd,49);
            }
            fclose($fd);
            $temp_array = split("\n", $tmp);
            return $tmp_array;
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
 * update_progress_bar($percent): updates the javascript driven progress bar.
 */
function update_progress_bar($percent) {
            if($percent > 100) $percent = 1;
            echo "\n<script type=\"text/javascript\" language=\"javascript\">";
            echo "\ndocument.progressbar.style.width='" . $percent . "%';";
            echo "\n</script>";
}


?>