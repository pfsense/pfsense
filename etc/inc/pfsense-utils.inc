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
	If ($dh = opendir($dir)) {
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
 * enable_hardware_offloading() enables hardware features of nics if they are supported
 */
function enable_hardware_offloading($interface) {
    global $config;
    global $g;
    if($g['booting']) {
	$supported_ints = array('fxp');
	foreach($supported_ints as $int) {
	    if(stristr($interface,$int) != false) {
		mwexec("/sbin/ifconfig $interface link0");
	    }
	}
    }
}

/*
 * return_filename_as_array($filename): returns $filename contents as a string
 */
function return_filename_as_array($filename) {
    $file = array();
    if(file_exists($filename)) {
        $text = return_filename_as_string($filename);
        $text_split = split("\n", $text);

        /* Strip out comments */
        while (($line = array_shift($text_split)) != NULL) {
            if(strpos($line, "#") !== 0)
                array_push($file, $line);
        }
    }
    return $file;
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

/*
 * find_number_of_created_carp_interfaces() returns the number of currently created carp interfaces
 */
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

/*
 * link_ip_to_carp_interface($ip): finds where a carp interface links to.
*/
function link_ip_to_carp_interface($ip) {
	global $config;
	if($ip == "") return;
        $i = 0;

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
                        $interface = filter_opt_interface_to_real($ifname);
			if($ft_ip == $carp_ft_ip)
			    if(stristr($carp_ints,$carp_int) == false)
				$carp_ints .= " " . $carp_int;
                }
        }
	return $carp_ints;
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
	$i = 0;
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
    $result = does_interface_exist($carpinterface);
    if($result <> true) return false;
    $status = exec_command("/sbin/ifconfig {$carpinterface} | /usr/bin/grep \"carp:\" | /usr/bin/cut -d\" \" -f2");
    return $status;
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
    $num_carp_ints = find_number_of_created_carp_interfaces();
    for($x=0; $x<$num_carp_ints; $x++) {
        $result = does_interface_exist("carp{$x}");
	if($result <> true) return;
	$ifconfig = exec_command("/sbin/ifconfig carp{$x}");
	if(stristr($ifconfig,$ip))
	    return "carp" . $x;
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
    global $g;
    fetch_latest_pkg_config();
    $pkg_config = parse_xml_config_pkg("{$g['tmp_path']}/pkg_config.xml", "pfsensepkgs");
    foreach($pkg_config['packages']['package'] as $pkg) {
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
    if(!file_exists("{$g['tmp_path']}/pkg_config.xml")) {
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
 * convert_friendly_interface_to_real_interface_name($interface): convert WAN to FXP0
 */
function convert_friendly_interface_to_real_interface_name($interface) {
    global $config;
    $lc_interface = strtolower($interface);
    if($config['interfaces'][$interface]['if'] <> "")
	return $config['interfaces'][$interface]['if'];
    $i = 0;
    $ifdescrs = array();
    for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
	    $ifdescrs['opt' . $j] = "opt" . $j;
    }
    foreach ($ifdescrs as $ifdescr => $ifname) {
	if($config['interfaces'][$ifname]['descr'] == $interface)
	    return $config['interfaces'][$ifname]['if'];
    }
    return $interface;
}

/*
 * convert_real_interface_to_friendly_interface_name($interface): convert fxp0 -> wan, etc.
 */
function convert_real_interface_to_friendly_interface_name($interface) {
	global $config;
	$i = 0;
	$ifdescrs = array('wan', 'lan');
	for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
		$ifdescrs['opt' . $j] = "opt" . $j;
	}
	foreach ($ifdescrs as $ifdescr => $ifname) {
	    $int = filter_translate_type_to_real_interface($ifname);
	    if($ifname == $interface) return $ifname;
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
 * resync_all_package_configs_bootup() Force packages to setup their configuration and rc.d files at bootup.
 * This function also prints output to the terminal indicating progress.
 */
function resync_all_package_configs_bootup($show_message) {
    global $config;
    log_error("Resyncing configuration for all packages.");
    if(!$config['installedpackages']['package']) return;
    if($show_message == true) print "Syncing packages:";
    foreach($config['installedpackages']['package'] as $package) {
	if(isset($pkg_config['nosync'])) continue;
	if($show_message == true) print " " . $package['name'];
	$error_message = '';
        if($pkg_config['custom_php_command_before_form'] <> "") {
	    if(php_check_syntax($pkg_config['custom_php_command_before_form'], $error_message) === true) {
		eval($pkg_config['custom_php_command_before_form']);
	    } else {
		print "(!)";
		continue;
	    }
	}
        if($pkg_config['custom_php_resync_config_command'] <> "") {
        //    if(php_check_syntax($pkg_config['custom_php_resync_config_command'], $error_message) === true) {
		eval($pkg_config['custom_php_resync_config_command']);
	//  } else {
	//	print "(!)";
	//	continue;
	//  }
	}
	$depends = get_pkg_depends($package['name'], ".xml", "files", 0); // Call dependency handler and do a little more error checking.
        if(is_array($depends))
	    foreach($depends as $item) {
			    $item_config = parse_xml_config_pkg("/usr/local/pkg/" . $item, "packagegui");
			    if (isset($item_config['nosync'])) continue;
			    if($item_config['custom_php_command_before_form'] <> "")
				eval($item_config['custom_php_command_before_form']);
			    if($item_config['custom_php_resync_config_command'] <> "")
				eval($item_config['custom_php_resync_config_command']);
			    if($show_message == true) print " " . $item_config['name'];
		    }
	    }
	if($show_message == true) print ".\n";
}

/*
 * sweep_package_processes(): Periodically kill a package's unnecessary processes
 *			      that may still be running (a server that does not automatically timeout, for example)
 */
function sweep_package_processes() {
    global $config;
    if(!$config['installedpackages']['package']) return;
    foreach($config['installedpackages']['package'] as $package) {
        $pkg_config = parse_xml_config_pkg("/usr/local/pkg/" . $package['configurationfile'], "packagegui");
        if($pkg_config['swept_processes'] <> "") {
                mwexec("/usr/bin/killall " . $pkg_config['swept_processes']);
                log_error("Killed " . $package['name'] . "'s unnecessary processes.");
        }
    }
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
function reverse_strrchr($haystack, $needle) {
	$pos = strrpos($haystack, $needle);
	if($post === false) {
		return $haystack;
	}
	return substr($haystack, 0, $post + 1);
}

/*
 * get_pkg_depends($pkg_name, $filetype = ".xml", $format = "files", return_nosync = 1):  Return a package's XML dependencies.
 *
 * $filetype = "all" || ".xml", ".tgz", etc.
 * $format = "files" (full filenames) || "names" (stripped / parsed depend names)
 * $return_nosync = 1 (return depends that have nosync set) | 0 (ignore packages with nosync)
 *
 */
function get_pkg_depends($pkg_name, $filetype = ".xml", $format = "files", $return_nosync = 1) {
        global $config;
        foreach($config['installedpackages']['package'] as $package) {
                if ($package['name'] == $pkg_name) {
                        $package_xml = $package;
                        if(!file_exists("/usr/local/pkg/" . $package['configurationfile'])) { // If the package's config file doesn't exist, log an error and fetch it.
                                log_error("Fetching missing configuration XML for " . $pkg_name);
                                mwexec("/usr/bin/fetch -o /usr/local/pkg/" . $package['configurationfile'] . " http://www.pfsense.com/packages/config/" . $package['configurationf
ile']);
                        }
                        break;
                }
        }
        $pkg_xml = parse_xml_config_pkg("/usr/local/pkg/" . $package_xml['configurationfile'], "packagegui");
        if($pkg_xml['additional_files_needed'] != "") {
                foreach($pkg_xml['additional_files_needed'] as $item) {
			if (($return_nosync == 0) && (isset($item['nosync']))) continue; // Do not return depends with nosync set if not required.
                        $depend_file = substr(strrchr($item['item']['0'], '/'),1); // Strip URLs down to filenames.
                        $depend_name = substr(substr($depend_file,0,strpos($depend_file,".")+1),0,-1); // Strip filename down to dependency name.
                        if (($filetype != "all") && (!preg_match("/${filetype}/i", $depend_file))) continue;
                        if ($item['prefix'] != "") {
                                $prefix = $item['prefix'];
                        } else {
                                $prefix = "/usr/local/pkg/";
                        }
                        if(!file_exists($prefix . $pkg_name)) {
                                log_error("Fetching missing dependency (" . $depend_name . ") for " . $pkg_name);
                                mwexec("/usr/local/bin/fetch -o " . $prefix . $depend_file . " " . $item['name']['0']);
                                if($item['chmod'] != "") mwexec("/bin/chmod " . $item['chmod'] . $prefix . $depend_file); // Handle chmods.
                        }
                        switch ($format) {
                        case "files":
                                $depends[] = $depend_file;
                                break;
                        case "names":
                                switch ($filetype) {
                                case "all":
                                        if(preg_match("/\.xml/i", $depend_file)) {
                                                $depend_xml = parse_xml_config_pkg("/usr/local/pkg/" . $depend_file, "packagegui");
                                                $depends[] = $depend_xml['name'];
                                                break;
                                        } else {
                                                $depends[] = $depend_name; // If this dependency isn't package XML, use the stripped filename.
                                                break;
                                        }
                                case ".xml":
                                        $depend_xml = parse_xml_config_pkg("/usr/local/pkg/" . $depend_file, "packagegui");
                                        $depends[] = $depend_xml['name'];
                                        break;
                                default:
                                        $depends[] = $depend_name; // If we aren't looking for XML, use the stripped filename (it's all we have).
                                        break;
                                }
                        }
                }
        return $depends;
        }
}

/*
 * is_service_running($service_name): checks to see if a service is running.
 *                                    if the service is running returns 1.
 */
function is_service_running($service_name) {
    $status = `/bin/ps ax | grep {$service_name} | grep -v grep`;
    $status_split = split("\n", $service_name);
    $counter = 0;
    foreach ($status_split as $ss) $counter++;
    if($counter > 0) return 1;
    return 0;
}

/*
 *  backup_config_section($section): returns as an xml file string of
 *                                   the configuration section
 */
function backup_config_section($section) {
    global $config;
    $new_section = &$config['section'];
    /* generate configuration XML */
    $xmlconfig = dump_xml_config($new_section, $section);
    return $new_section;
}

/*
 *  restore_config_section($section, new_contents): restore a configuration section,
 *                                                  and write the configuration out
 *                                                  to disk/cf.
 */
function restore_config_section($section, $new_contents) {
    global $config;
    conf_mount_rw();
    config_lock();
    $fout = fopen("{$g['tmp_path']}/tmpxml","w");
    fwrite($fout, $current_rules_section);
    fclose($fout);
    $section_xml = parse_xml_config_pkg($g['tmp_path'] . "/tmpxml", "packagegui");
    $config[$section] = &$section_xml;
    unlink($g['tmp_path'] . "/tmpxml");
    write_config();
    conf_mount_ro();
    config_unlock();
    return;
}

/*
 * http_post($server, po$port, $url, $vars): does an http post to a web server
 *                                           posting the vars array.
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
        $fout = fopen("/tmp/codetocheck.php","w");
        $code = $_POST['content'];
        $code = str_replace("<?php", "", $code);
        $code = str_replace("?>", "", $code);
        fwrite($fout, "<?php\n\n");
        fwrite($fout, $code);
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
 * sync_package($pkg_name, $show_message) Force a package to setup its configuration and rc.d files.
 */
function sync_package($pkg_name, $sync_depends = true, $show_message = false) {
	global $config;                                        
	if(!$config['installedpackages']['package']) return;
	if(!is_numeric($pkg_name)) {
		$pkg_id = get_pkg_id($pkg_name);
		if($pkg_id == -1) return -1; // This package doesn't really exist - exit the function.
	} else {
		$pkg_id = $pkg_name;
		if(!isset($config['installedpackages'][$pkg_id])) return; // No package belongs to the pkg_id passed to this function.
	}
	$package = $config['installedpackages'][$pkg_id];
	if(!file_exists("/usr/local/pkg/" . $package['configurationfile'])) {
		//if($show_message == true) print "(f)"; Don't mess with this until the package system has settled.
		log_error("Fetching missing configuration XML for " . $package['name']);
		mwexec("/usr/bin/fetch -o /usr/local/pkg/" . $package['configurationfile'] . " http://www.pfsense.com/packages/config/" . $package['configurationfile']);
        }
        $pkg_config = parse_xml_config_pkg("/usr/local/pkg/" . $package['configurationfile'], "packagegui");
        if(isset($pkg_config['nosync'])) continue;
	if($show_message == true) print "Syncing " . $pkg_name;
        if($pkg_config['custom_php_command_before_form'] <> "") {
		eval($pkg_config['custom_php_command_before_form']);
	}
        if($pkg_config['custom_php_resync_config_command'] <> "") {
	   	eval($pkg_config['custom_php_resync_config_command']);
        }
        if($sync_depends == true) {
		$depends = get_pkg_depends($package['name'], ".xml", "files", 0); // Call dependency handler and do a little more error checking.
        	if(is_array($depends)) {
            		foreach($depends as $item) {
				$item_config = parse_xml_config_pkg("/usr/local/pkg/" . $item, "packagegui");
				if(isset($item_config['nosync'])) continue;
				if($item_config['custom_php_command_before_form'] <> "")
					eval($item_config['custom_php_command_before_form']);
				if($item_config['custom_php_resync_config_command'] <> "")
					eval($item_config['custom_php_resync_config_command']);
				if($show_message == true) print " " . $item_config['name'];
			}
		}
	}
	if($show_message == true) print ".";
}
?>
