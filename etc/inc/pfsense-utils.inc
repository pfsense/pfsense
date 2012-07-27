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

/*
	pfSense_BUILDER_BINARIES:	/sbin/sysctl	/sbin/ifconfig	/sbin/pfctl	/usr/local/bin/php /usr/bin/netstat
	pfSense_BUILDER_BINARIES:	/bin/df	/usr/bin/grep	/usr/bin/awk	/bin/rm	/usr/sbin/pwd_mkdb	/usr/bin/host
	pfSense_BUILDER_BINARIES:	/sbin/kldload
	pfSense_MODULE:	utils
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
	if(isAllowedPage($security_url, $_SESSION['Username'])) 
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
	global $g;
	return "{$g['tmp_path']}/tmp-" . time();
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
	$dns_s = file("/etc/resolv.conf", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	foreach($dns_s as $dns) {
		$matches = "";
		if (preg_match("/nameserver (.*)/", $dns, $matches))
			$dns_servers[] = $matches[1];
	}
	return array_unique($dns_servers);
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

	if(isset($config['system']['do_not_use_nic_microcode']))
		return;

	/* translate wan, lan, opt -> real interface if needed */
	$int = get_real_interface($interface);
	if(empty($int)) 
		return;
	$int_family = preg_split("/[0-9]+/", $int);
	$supported_ints = array('fxp');
	if (in_array($int_family, $supported_ints)) {
		if(does_interface_exist($int)) 
			pfSense_interface_flags($int, IFF_LINK0);
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
	$opts = pfSense_get_interface_addresses($iface);
	if (is_array($opts) && isset($opts['caps']['polling']))
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
			if($rule['target'] && $rule['target'] == $alias)
				return true;
			if($rule['source']['address'] && $rule['source']['address'] == $alias)
				return true;
			if($rule['destination']['address'] && $rule['destination']['address'] == $alias)
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

	if (isset($config['system']['polling']))
		mwexec("/sbin/sysctl kern.polling.idle_poll=1");
	else
		mwexec("/sbin/sysctl kern.polling.idle_poll=0");

	if($config['system']['polling_each_burst'])
		mwexec("/sbin/sysctl kern.polling.each_burst={$config['system']['polling_each_burst']}");
	if($config['system']['polling_burst_max'])
		mwexec("/sbin/sysctl kern.polling.burst_max={$config['system']['polling_burst_max']}");
	if($config['system']['polling_user_frac'])
		mwexec("/sbin/sysctl kern.polling.user_frac={$config['system']['polling_user_frac']}");
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
	$ifs = get_interface_arr();

	foreach($ifs as $if)
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
    $status = `/sbin/sysctl -n net.inet.carp.allow`;
    return (intval($status) > 0);
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
	$carp_query = "";
	exec("/sbin/ifconfig $carpinterface | /usr/bin/grep -v grep | /usr/bin/grep carp:", $carp_query);
	foreach($carp_query as $int) {
		if(stristr($int, "MASTER")) 
			return "MASTER";
		if(stristr($int, "BACKUP")) 
			return "BACKUP";
		if(stristr($int, "INIT")) 
			return "INIT";
	}
	return;
}

/*
 * get_pfsync_interface_status($pfsyncinterface): returns the status of a pfsync
 */
function get_pfsync_interface_status($pfsyncinterface) {
    $result = does_interface_exist($pfsyncinterface);
    if($result <> true) return;
    $status = exec_command("/sbin/ifconfig {$pfsyncinterface} | /usr/bin/awk '/pfsync:/ {print \$5}'");
    return $status;
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
	if(!file_exists($file) && !is_writable($file))
		return;
	$filecontents = file_get_contents($file);
	$text = str_replace($text, "", $filecontents);
	@file_put_contents($file, $text); 
}

/*
 * add_text_to_file($file, $text): adds $text to $file.
 * replaces the text if it already exists.
 */
function add_text_to_file($file, $text, $replace = false) {
	if(file_exists($file) and is_writable($file)) {
		$filecontents = file($file);
		$filecontents = array_map('rtrim', $filecontents);
		array_push($filecontents, $text);
		if ($replace)
			$filecontents = array_unique($filecontents);

		$file_text = implode("\n", $filecontents);

		@file_put_contents($file, $file_text); 
		return true;
	}
	return false;
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
	if (!is_string($haystack))
		return;
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
	if ($section_xml != -1)
		$config[$section] = &$section_xml;
	@unlink($g['tmp_path'] . "/tmpxml");
	if(file_exists("{$g['tmp_path']}/config.cache"))
		unlink("{$g['tmp_path']}/config.cache");
	write_config("Restored {$section} of config file (maybe from CARP partner)");
	disable_security_checks();
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
	disable_security_checks();
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
	global $g;
	function php_check_syntax($code_to_check, &$errormessage){
		return false;
		$fout = fopen("{$g['tmp_path']}/codetocheck.php","w");
		$code = $_POST['content'];
		$code = str_replace("<?php", "", $code);
		$code = str_replace("?>", "", $code);
		fwrite($fout, "<?php\n\n");
		fwrite($fout, $code_to_check);
		fwrite($fout, "\n\n?>\n");
		fclose($fout);
		$command = "/usr/local/bin/php -l {$g['tmp_path']}/codetocheck.php";
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

	$xmlrpc_base_url = isset($config['system']['altpkgrepo']['enable']) ? $config['system']['altpkgrepo']['xmlrpcbaseurl'] : $g['xmlrpcbaseurl'];
	$xmlrpc_path = $g['xmlrpcpath'];
	$msg = new XML_RPC_Message($method, array(XML_RPC_Encode($params)));
	$port = 0;
	$proxyurl = "";
	$proxyport = 0;
	$proxyuser = "";
	$proxypass = "";
	if (!empty($config['system']['proxyurl']))
		$proxyurl = $config['system']['proxyurl'];
	if (!empty($config['system']['proxyport']) && is_numeric($config['system']['proxyport']))
		$proxyport = $config['system']['proxyport'];
	if (!empty($config['system']['proxyuser']))
		$proxyuser = $config['system']['proxyuser'];
	if (!empty($config['system']['proxypass']))
		$proxypass = $config['system']['proxypass'];
	$cli = new XML_RPC_Client($xmlrpc_path, $xmlrpc_base_url, $port, $proxyurl, $proxyport, $proxyuser, $proxypass);
	// If the ALT PKG Repo has a username/password set, use it.
	if($config['system']['altpkgrepo']['username'] && 
	   $config['system']['altpkgrepo']['password']) {
		$username = $config['system']['altpkgrepo']['username'];
		$password = $config['system']['altpkgrepo']['password'];
		$cli->setCredentials($username, $password);
	}
	elseif($g['xmlrpcauthuser'] && $g['xmlrpcauthpass']) {
		$username = $g['xmlrpcauthuser'];
		$password = $g['xmlrpcauthpass'];
		$cli->setCredentials($username, $password);
	}
	$resp = $cli->send($msg, $timeout);
	if(!is_object($resp)) {
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
		"platform" => trim(file_get_contents('/etc/platform')),
		"config_version" => $config['version']
		);
	if($tocheck == "all") {
		$params = $rawparams;
	} else {
		foreach($tocheck as $check) {
			$params['check'] = $rawparams['check'];
			$params['platform'] = $rawparams['platform'];
		}
	}
	if($config['system']['firmware']['branch'])
		$params['branch'] = $config['system']['firmware']['branch'];

	/* XXX: What is this method? */
	if(!($versions = call_pfsense_method('pfsense.get_firmware_version', $params))) {
		return false;
	} else {
		$versions["current"] = $params;
	}

	return $versions;
}

/*
 * host_firmware_version(): Return the versions used in this install
 */
function host_firmware_version($tocheck = "") {
        global $g, $config;

        return array(
		"firmware" => array("version" => trim(file_get_contents('/etc/version', " \n"))),
                "kernel"   => array("version" => trim(file_get_contents('/etc/version_kernel', " \n"))),
                "base"     => array("version" => trim(file_get_contents('/etc/version_base', " \n"))),
                "platform" => trim(file_get_contents('/etc/platform', " \n")),
                "config_version" => $config['version']
                );
}

function get_disk_info() {
	$diskout = "";
	exec("/bin/df -h | /usr/bin/grep -w '/' | /usr/bin/awk '{ print $2, $3, $4, $5 }'", $diskout);
	return explode(' ', $diskout[0]);
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
	send_event("service reload all");
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
	send_event("interface all reload");
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
	unlink_if_exists("/etc/spwd.db.tmp");
	mwexec("/usr/sbin/pwd_mkdb -d /etc/ /etc/master.passwd");
	conf_mount_ro();

	/* restart sshd */
	send_event("service restart sshd");

	/* restart webConfigurator if needed */
	send_event("service restart webgui");
}

function auto_login() {
	global $config;

	if(isset($config['system']['disableconsolemenu']))
		$status = false;
	else
		$status = true;

	$gettytab = file_get_contents("/etc/gettytab");
	$getty_split = split("\n", $gettytab);
	conf_mount_rw();
	$fd = false;
	$tries = 0;
	while (!$fd && $tries < 100) {
		$fd = fopen("/etc/gettytab", "w");
		$tries++;
		
	}
	if (!$fd) {
		conf_mount_ro();
		log_error("Enabling auto login was not possible.");
		return;
	}
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
		$boot_config_split = explode("\n", $boot_config);
		if(count($boot_config_split) > 0) {
			$new_boot_config = array();
			// Loop through and only add lines that are not empty, and which
			//  do not contain a console directive.
			foreach($boot_config_split as $bcs)
				if(!empty($bcs) && (stripos($bcs, "console") === false))
					$new_boot_config[] = $bcs;

			if(isset($config['system']['enableserial']))
				$new_boot_config[] = 'console="comconsole"';
			file_put_contents("/boot/loader.conf", implode("\n", $new_boot_config));
		}
	}
	$ttys = file_get_contents("/etc/ttys");
	$ttys_split = split("\n", $ttys);
	$fd = fopen("/etc/ttys", "w");
	foreach($ttys_split as $tty) {
		if(stristr($tty, "ttyd0") or stristr($tty, "ttyu0")) {
			if(isset($config['system']['enableserial'])) {
				fwrite($fd, "ttyu0	\"/usr/libexec/getty bootupcli\"	cons25	on	secure\n");
			} else {
				fwrite($fd, "ttyu0	\"/usr/libexec/getty bootupcli\"	cons25	off	secure\n");
			}
		} else {
			fwrite($fd, $tty . "\n");
		}
	}
	fclose($fd);
	auto_login();

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

/* Any PPPoE servers enabled? */
function is_pppoe_server_enabled() {
	global $config;

	$pppoeenable = false;

	if (!is_array($config['pppoes']) || !is_array($config['pppoes']['pppoe']))
		return false;

	foreach ($config['pppoes']['pppoe'] as $pppoes)
		if ($pppoes['mode'] == 'server')
			$pppoeenable = true;

	return $pppoeenable;
}

function convert_seconds_to_hms($sec){
	$min=$hrs=0;
	if ($sec != 0){
		$min = floor($sec/60);
		$sec %= 60;
	}
	if ($min != 0){
		$hrs = floor($min/60);
		$min %= 60;
	}
	if ($sec < 10)
		$sec = "0".$sec;
	if ($min < 10)
		$min = "0".$min;
	if ($hrs < 10)
		$hrs = "0".$hrs;
	$result = $hrs.":".$min.":".$sec;
	return $result;
}

/* Compute the total uptime from the ppp uptime log file in the conf directory */

function get_ppp_uptime($port){
	if (file_exists("/conf/{$port}.log")){
    	$saved_time = file_get_contents("/conf/{$port}.log");
    	$uptime_data = explode("\n",$saved_time);
		$sec=0;
		foreach($uptime_data as $upt) {
			$sec += substr($upt, 1 + strpos($upt, " "));
 		}
		return convert_seconds_to_hms($sec);
	} else {
		$total_time = "No history data found!";
		return $total_time;
	}
}

//returns interface information
function get_interface_info($ifdescr) {
	global $config, $g;

	$ifinfo = array();
	if (empty($config['interfaces'][$ifdescr]))
		return;
	$ifinfo['hwif'] = $config['interfaces'][$ifdescr]['if'];
	$ifinfo['if'] = get_real_interface($ifdescr);

	$chkif = $ifinfo['if'];
	$ifinfotmp = pfSense_get_interface_addresses($chkif);
	$ifinfo['status'] = $ifinfotmp['status'];
	if (empty($ifinfo['status']))
                $ifinfo['status'] = "down";
	$ifinfo['macaddr'] = $ifinfotmp['macaddr'];
	$ifinfo['ipaddr'] = $ifinfotmp['ipaddr'];
	$ifinfo['subnet'] = $ifinfotmp['subnet'];
	if (isset($ifinfotmp['link0']))
		$link0 = "down";
	$ifinfotmp = pfSense_get_interface_stats($chkif);
        $ifinfo['inpkts'] = $ifinfotmp['inpkts'];
        $ifinfo['outpkts'] = $ifinfotmp['outpkts'];
        $ifinfo['inerrs'] = $ifinfotmp['inerrs'];
        $ifinfo['outerrs'] = $ifinfotmp['outerrs'];
        $ifinfo['collisions'] = $ifinfotmp['collisions'];

	/* Use pfctl for non wrapping 64 bit counters */
	/* Pass */
	exec("/sbin/pfctl -vvsI -i {$chkif}", $pfctlstats);
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
	$link_type = $config['interfaces'][$ifdescr]['ipaddr'];
	switch ($link_type) {
	 /* DHCP? -> see if dhclient is up */
	case "dhcp":
	case "carpdev-dhcp":
		/* see if dhclient is up */
		if (find_dhclient_process($ifinfo['if']) <> "")
			$ifinfo['dhcplink'] = "up";
		else
			$ifinfo['dhcplink'] = "down";

		break;
	/* PPPoE/PPTP/L2TP interface? -> get status from virtual interface */
	case "pppoe":
	case "pptp":
	case "l2tp":
		if ($ifinfo['status'] == "up" && !isset($link0))
			/* get PPPoE link status for dial on demand */
			$ifinfo["{$link_type}link"] = "up";
		else
			$ifinfo["{$link_type}link"] = "down";

		break;
	/* PPP interface? -> get uptime for this session and cumulative uptime from the persistant log file in conf */
	case "ppp":
		if ($ifinfo['status'] == "up")
			$ifinfo['ppplink'] = "up";
		else
			$ifinfo['ppplink'] = "down" ;

		if (empty($ifinfo['status']))
			$ifinfo['status'] = "down";
			
		if (is_array($config['ppps']['ppp']) && count($config['ppps']['ppp'])) {
			foreach ($config['ppps']['ppp'] as $pppid => $ppp) {
				if ($config['interfaces'][$ifdescr]['if'] == $ppp['if'])
					break;
			}
		}
		$dev = $ppp['ports'];
		if ($config['interfaces'][$ifdescr]['if'] != $ppp['if'] || empty($dev))
			break;
		if (!file_exists($dev)) {
			$ifinfo['nodevice'] = 1;
			$ifinfo['pppinfo'] = $dev . " device not present! Is the modem attached to the system?";	
		}
		// Calculate cumulative uptime for PPP link. Useful for connections that have per minute/hour contracts so you don't go over!
		if (isset($ppp['uptime']))
			$ifinfo['ppp_uptime_accumulated'] = "(".get_ppp_uptime($ifinfo['if']).")";
		break;
	default:
		break;
	}
	
	if (file_exists("{$g['varrun_path']}/{$link_type}_{$ifdescr}.pid")) {
		$sec = trim(`/usr/local/sbin/ppp-uptime.sh {$ifinfo['if']}`);
		$ifinfo['ppp_uptime'] = convert_seconds_to_hms($sec);
	}
	
	if ($ifinfo['status'] == "up") {
		/* try to determine media with ifconfig */
		unset($ifconfiginfo);
		exec("/sbin/ifconfig " . $ifinfo['if'], $ifconfiginfo);
		$wifconfiginfo = array();
		if(is_interface_wireless($ifdescr)) {
			exec("/sbin/ifconfig {$ifinfo['if']} list sta", $wifconfiginfo);
			array_shift($wifconfiginfo);
		}
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
				if($ifinfo['status'] == "running")
					$ifinfo['status'] = "up";
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
			if (preg_match("/laggproto (.*)$/", $ici, $matches)) {
				$ifinfo['laggproto'] = $matches[1];
			}
			if (preg_match("/laggport: (.*)$/", $ici, $matches)) {
				$ifinfo['laggport'][] = $matches[1];
			}
		}
		foreach($wifconfiginfo as $ici) {
			$elements = preg_split("/[ ]+/i", $ici);
			if ($elements[0] != "") {
				$ifinfo['bssid'] = $elements[0];
			}
			if ($elements[3] != "") {
				$ifinfo['rate'] = $elements[3];
			}
			if ($elements[4] != "") {
				$ifinfo['rssi'] = $elements[4];
			}

		}
		/* lookup the gateway */
		if (interface_has_gateway($ifdescr)) 
			$ifinfo['gateway'] = get_interface_gateway($ifdescr);
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

function pfsense_default_tables_size() {
	$current = `pfctl -sm | grep ^tables | awk '{print $4};'`;
	return $current;
}

function pfsense_default_table_entries_size() {
	$current = `pfctl -sm | grep table-entries | awk '{print $4};'`;
	return $current;
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
		if($g['debug']) {
			log_error("DNSCACHE: Found old IP {$oldcontents} and new IP {$contents}");
		}
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

/****f* pfsense-utils/isvm
 * NAME
 *   isvm
 * INPUTS
 *	 none
 * RESULT
 *   returns true if machine is running under a virtual environment
 ******/
function isvm() {
	$virtualenvs = array("vmware", "parallels", "qemu", "bochs", "plex86");
	$bios_vendor = strtolower(`/bin/kenv | /usr/bin/awk -F= '/smbios.bios.vendor/ {print $2}'`);
	if(in_array($bios_vendor, $virtualenvs)) 
		return true;
	else
		return false;
}

function get_freebsd_version() {
	$version = php_uname("r");
	return $version[0];
}

function download_file_with_progress_bar($url_file, $destination_file, $readbody = 'read_body', $connect_timeout=60, $timeout=0) {
        global $ch, $fout, $file_size, $downloaded, $config;
        $file_size  = 1;
        $downloaded = 1;
        /* open destination file */
        $fout = fopen($destination_file, "wb");

        /*
         *      Originally by Author: Keyvan Minoukadeh
         *      Modified by Scott Ullrich to return Content-Length size
         */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url_file);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, 'read_header');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        /* Don't verify SSL peers since we don't have the certificates to do so. */
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, $readbody);
        curl_setopt($ch, CURLOPT_NOPROGRESS, '1');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

	if (!empty($config['system']['proxyurl'])) {
		curl_setopt($ch, CURLOPT_PROXY, $config['system']['proxyurl']);
		if (!empty($config['system']['proxyport']))
			curl_setopt($ch, CURLOPT_PROXYPORT, $config['system']['proxyport']);
		if (!empty($config['system']['proxyuser']) && !empty($config['system']['proxypass'])) {
			@curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_ANY | CURLAUTH_ANYSAFE);
			curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$config['system']['proxyuser']}:{$config['system']['proxypass']}");
		}
	}

        @curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if($fout)
                fclose($fout);
        curl_close($ch);
        return ($http_code == 200) ? true : $http_code;
}

function read_header($ch, $string) {
        global $file_size, $fout;
        $length = strlen($string);
        $regs = "";
        ereg("(Content-Length:) (.*)", $string, $regs);
        if($regs[2] <> "") {
                $file_size = intval($regs[2]);
        }
        ob_flush();
        return $length;
}

function read_body($ch, $string) {
        global $fout, $file_size, $downloaded, $sendto, $static_status, $static_output, $lastseen;
		global $pkg_interface;
        $length = strlen($string);
        $downloaded += intval($length);
        if($file_size > 0) {
                $downloadProgress = round(100 * (1 - $downloaded / $file_size), 0);
                $downloadProgress = 100 - $downloadProgress;
        } else
                $downloadProgress = 0;
        if($lastseen <> $downloadProgress and $downloadProgress < 101) {
                if($sendto == "status") {
					if($pkg_interface == "console") {
						if(substr($downloadProgress,2,1) == "0" || count($downloadProgress) < 2) {
                        	$tostatus = $static_status . $downloadProgress . "%";
                        	update_status($tostatus);
						}
					} else {
                        $tostatus = $static_status . $downloadProgress . "%";
                        update_status($tostatus);						
					}
                } else {
					if($pkg_interface == "console") {
						if(substr($downloadProgress,2,1) == "0" || count($downloadProgress) < 2) {
                        	$tooutput = $static_output . $downloadProgress . "%";
                        	update_output_window($tooutput);
						}
					} else {
                        $tooutput = $static_output . $downloadProgress . "%";
                        update_output_window($tooutput);
					}
                }
                update_progress_bar($downloadProgress);
                $lastseen = $downloadProgress;
        }
        if($fout)
                fwrite($fout, $string);
        ob_flush();
        return $length;
}

/*
 *   update_output_window: update bottom textarea dynamically.
 */
function update_output_window($text) {
        global $pkg_interface;
        $log = ereg_replace("\n", "\\n", $text);
        if($pkg_interface != "console") {
                echo "\n<script language=\"JavaScript\">\nthis.document.forms[0].output.value = \"" . $log . "\";\n";
				echo "this.document.forms[0].output.scrollTop = this.document.forms[0].output.scrollHeight;\n";	
				echo "</script>";
        } else
		echo $text;
        /* ensure that contents are written out */
        ob_flush();
}

/*
 *   update_output_window: update top textarea dynamically.
 */
function update_status($status) {
        global $pkg_interface;

        if($pkg_interface != "console") {
                echo "\n<script type=\"text/javascript\">this.document.forms[0].status.value=\"" . $status . "\";</script>";
        } else {
                echo $status . "\n";
        }
        /* ensure that contents are written out */
        ob_flush();
}

/*
 * update_progress_bar($percent): updates the javascript driven progress bar.
 */
function update_progress_bar($percent) {
        global $pkg_interface;
        if($percent > 100) $percent = 1;
        if($pkg_interface <> "console") {
                echo "\n<script type=\"text/javascript\" language=\"javascript\">";
                echo "\ndocument.progressbar.style.width='" . $percent . "%';";
                echo "\n</script>";
        } else {
                echo " {$percent}%";
        }
}

/* Split() is being DEPRECATED as of PHP 5.3.0 and REMOVED as of PHP 6.0.0. Relying on this feature is highly discouraged. */
if(!function_exists("split")) {
	function split($seperator, $haystack, $limit = null) {
		return preg_split($seperator, $haystack, $limit);
	}
}

function update_alias_names_upon_change($section, $field, $new_alias_name, $origname) {
	global $g, $config, $pconfig, $debug;
	if(!$origname) 
		return;

	$sectionref = &$config;
	foreach($section as $sectionname) {
		if(is_array($sectionref) && isset($sectionref[$sectionname]))
			$sectionref = &$sectionref[$sectionname];
		else
			return;
	}

	if($debug) $fd = fopen("{$g['tmp_path']}/print_r", "a");
	if($debug) fwrite($fd, print_r($pconfig, true));

	if(is_array($sectionref)) {
		foreach($sectionref as $itemkey => $item) {
			if($debug) fwrite($fd, "$itemkey\n");

			$fieldfound = true;
			$fieldref = &$sectionref[$itemkey];
			foreach($field as $fieldname) {
				if(is_array($fieldref) && isset($fieldref[$fieldname]))
					$fieldref = &$fieldref[$fieldname];
				else {
					$fieldfound = false;
					break;
				}
			}
			if($fieldfound && $fieldref == $origname) {
				if($debug) fwrite($fd, "Setting old alias value $origname to $new_alias_name\n");
				$fieldref = $new_alias_name;
			}
		}
	}

	if($debug) fclose($fd);

}

function update_alias_url_data() {
	global $config, $g;

	/* item is a url type */
	$lockkey = lock('config');
	if (is_array($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $x => $alias) {
			if (empty($alias['aliasurl']))
				continue;

			/* fetch down and add in */
			$isfirst = 0;
			$temp_filename = tempnam("{$g['tmp_path']}/", "alias_import");
			unlink($temp_filename);
			$fda = fopen("{$g['tmp_path']}/tmpfetch","w");
			fwrite($fda, "/usr/bin/fetch -q -o \"{$temp_filename}/aliases\" \"" . $config['aliases']['alias'][$x]['aliasurl'] . "\"");
			fclose($fda);
			mwexec("/bin/mkdir -p {$temp_filename}");
			mwexec("/usr/bin/fetch -q -o \"{$temp_filename}/aliases\" \"" . $config['aliases']['alias'][$x]['aliasurl'] . "\"");
			/* if the item is tar gzipped then extract */
			if(stristr($alias['aliasurl'], ".tgz"))
				process_alias_tgz($temp_filename);
			else if(stristr($alias['aliasurl'], ".zip"))
				process_alias_unzip($temp_filename);
			if(file_exists("{$temp_filename}/aliases")) {
				$file_contents = file_get_contents("{$temp_filename}/aliases");
				$file_contents = str_replace("#", "\n#", $file_contents);
				$file_contents_split = split("\n", $file_contents);
				foreach($file_contents_split as $fc) {
					$tmp = trim($fc);
					if(stristr($fc, "#")) {
						$tmp_split = split("#", $tmp);
						$tmp = trim($tmp_split[0]);
					}
					if(trim($tmp) <> "") {
						if($isfirst == 1)
							$address .= " ";
						$address .= $tmp;
						$isfirst = 1;
					}
				}
				if($isfirst > 0) {
					$config['aliases']['alias'][$x]['address'] = $address;
					$updated = true;
				}
				mwexec("/bin/rm -rf {$temp_filename}");
			}
		}
	}
	if($updated)
		write_config();
	unlock($lockkey);
}

function process_alias_unzip($temp_filename) {
	if(!file_exists("/usr/local/bin/unzip"))
		return;
	mwexec("/bin/mv {$temp_filename}/aliases {$temp_filename}/aliases.zip");
	mwexec("/usr/local/bin/unzip {$temp_filename}/aliases.tgz -d {$temp_filename}/aliases/");
	unlink("{$temp_filename}/aliases.zip");
	$files_to_process = return_dir_as_array("{$temp_filename}/");
	/* foreach through all extracted files and build up aliases file */
	$fd = fopen("{$temp_filename}/aliases", "w");
	foreach($files_to_process as $f2p) {
		$file_contents = file_get_contents($f2p);
		fwrite($fd, $file_contents);
		unlink($f2p);
	}
	fclose($fd);
}

function process_alias_tgz($temp_filename) {
	if(!file_exists("/usr/bin/tar"))
		return;
	mwexec("/bin/mv {$temp_filename}/aliases {$temp_filename}/aliases.tgz");
	mwexec("/usr/bin/tar xzf {$temp_filename}/aliases.tgz -C {$temp_filename}/aliases/");
	unlink("{$temp_filename}/aliases.tgz");
	$files_to_process = return_dir_as_array("{$temp_filename}/");
	/* foreach through all extracted files and build up aliases file */
	$fd = fopen("{$temp_filename}/aliases", "w");
	foreach($files_to_process as $f2p) {
		$file_contents = file_get_contents($f2p);
		fwrite($fd, $file_contents);
		unlink($f2p);
	}
	fclose($fd);
}

function version_compare_dates($a, $b) {
	$a_time = strtotime($a);
	$b_time = strtotime($b);

	if ((!$a_time) || (!$b_time)) {
		return FALSE;
	} else {
		if ($a_time < $b_time)
			return -1;
		elseif ($$a_time == $b_time)
			return 0;
		else
			return 1;
	}
}
function version_get_string_value($a) {
	$strs = array(
		0 => "ALPHA-ALPHA",
		2 => "ALPHA",
		3 => "BETA",
		4 => "B",
		5 => "C",
		6 => "D",
		7 => "RC",
		8 => "RELEASE"
	);
	$major = 0;
	$minor = 0;
	foreach ($strs as $num => $str) {
		if (substr($a, 0, strlen($str)) == $str) {
			$major = $num;
			$n = substr($a, strlen($str));
			if (is_numeric($n))
				$minor = $n;
			break;
		}
	}
	return "{$major}.{$minor}";
}
function version_compare_string($a, $b) {
	return version_compare_numeric(version_get_string_value($a), version_get_string_value($b));
}
function version_compare_numeric($a, $b) {
	$a_arr = explode('.', rtrim($a, '.0'));
	$b_arr = explode('.', rtrim($b, '.0'));

	foreach ($a_arr as $n => $val) {
		if (array_key_exists($n, $b_arr)) {
			// So far so good, both have values at this minor version level. Compare.
			if ($val > $b_arr[$n])
				return 1;
			elseif ($val < $b_arr[$n])
				return -1;
		} else {
			// a is greater, since b doesn't have any minor version here.
			return 1;
		}
	}
	if (count($b_arr) > count($a_arr)) {
		// b is longer than a, so it must be greater.
		return -1;
	} else {
		// Both a and b are of equal length and value.
		return 0;
	}
}
function pfs_version_compare($cur_time, $cur_text, $remote) {
	// First try date compare
	$v = version_compare_dates($cur_time, $remote);
	if ($v === FALSE) {
		// If that fails, try to compare by string
		// Before anything else, simply test if the strings are equal
		if (($cur_text == $remote) || ($cur_time == $remote))
			return 0;
		list($cur_num, $cur_str) = explode('-', $cur_text);
		list($rem_num, $rem_str) = explode('-', $remote);

		// First try to compare the numeric parts of the version string.
		$v = version_compare_numeric($cur_num, $rem_num);

		// If the numeric parts are the same, compare the string parts.
		if ($v == 0)
			return version_compare_string($cur_str, $rem_str);
	}
	return $v;
}
function process_alias_urltable($name, $url, $freq, $forceupdate=false) {
	$urltable_prefix = "/var/db/aliastables/";
	$urltable_filename = $urltable_prefix . $name . ".txt";

	// Make the aliases directory if it doesn't exist
	if (!file_exists($urltable_prefix)) {
		mkdir($urltable_prefix);
	} elseif (!is_dir($urltable_prefix)) {
		unlink($urltable_prefix);
		mkdir($urltable_prefix);
	}

	// If the file doesn't exist or is older than update_freq days, fetch a new copy.
	if (!file_exists($urltable_filename)
		|| ((time() - filemtime($urltable_filename)) > ($freq * 86400))
		|| $forceupdate) {

		// Try to fetch the URL supplied
		conf_mount_rw();
		unlink_if_exists($urltable_filename . ".tmp");
		// Use fetch to grab data since these may be large files, we don't want to process them through PHP if we can help it.
		mwexec("/usr/bin/fetch -q -o " . escapeshellarg($urltable_filename . ".tmp") . " " . escapeshellarg($url));
		// Remove comments. Might need some grep-fu to only allow lines that look like IPs/subnets
		if (file_exists($urltable_filename . ".tmp")) {
			mwexec("/usr/bin/grep -v '^#' " . escapeshellarg($urltable_filename . ".tmp") . " > " . escapeshellarg($urltable_filename));
			unlink_if_exists($urltable_filename . ".tmp");
		} else
			mwexec("/usr/bin/touch {$urltable_filename}");
		conf_mount_ro();
		return true;
	} else {
		// File exists, and it doesn't need updated.
		return -1;
	}
}
function get_real_slice_from_glabel($label) {
	$label = escapeshellarg($label);
	return trim(`/sbin/glabel list | /usr/bin/grep -B2 ufs/{$label} | /usr/bin/head -n 1 | /usr/bin/cut -f3 -d' '`);
}
function nanobsd_get_boot_slice() {
	return trim(`/sbin/mount | /usr/bin/grep pfsense | /usr/bin/cut -d'/' -f4 | /usr/bin/cut -d' ' -f1`);
}
function nanobsd_get_boot_drive() {
	return trim(`/sbin/glabel list | /usr/bin/grep -B2 ufs/pfsense | /usr/bin/head -n 1 | /usr/bin/cut -f3 -d' ' | /usr/bin/cut -d's' -f1`);
}
function nanobsd_get_active_slice() {
	$boot_drive = nanobsd_get_boot_drive();
	$active = trim(`gpart show $boot_drive | grep '\[active\]' | awk '{print $3;}'`);

	return "{$boot_drive}s{$active}";
}
function nanobsd_get_size() {
	return strtoupper(file_get_contents("/etc/nanosize.txt"));
}
function nanobsd_switch_boot_slice() {
	global $SLICE, $OLDSLICE, $TOFLASH, $COMPLETE_PATH, $COMPLETE_BOOT_PATH;
	global $GLABEL_SLICE, $UFS_ID, $OLD_UFS_ID, $BOOTFLASH;
	global $BOOT_DEVICE, $REAL_BOOT_DEVICE, $BOOT_DRIVE, $ACTIVE_SLICE;
	nanobsd_detect_slice_info();

	if ($BOOTFLASH == $ACTIVE_SLICE) {
		$slice = $TOFLASH;
	} else {
		$slice = $BOOTFLASH;
	}

	for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
	ob_implicit_flush(1);
	if(strstr($slice, "s2")) {
		$ASLICE="2";
		$AOLDSLICE="1";
		$AGLABEL_SLICE="pfsense1";
		$AUFS_ID="1";
		$AOLD_UFS_ID="0";
	} else {
		$ASLICE="1";
		$AOLDSLICE="2";
		$AGLABEL_SLICE="pfsense0";
		$AUFS_ID="0";
		$AOLD_UFS_ID="1";
	}
	$ATOFLASH="{$BOOT_DRIVE}s{$ASLICE}";
	$ACOMPLETE_PATH="{$BOOT_DRIVE}s{$ASLICE}a";
	$ABOOTFLASH="{$BOOT_DRIVE}s{$AOLDSLICE}";
	conf_mount_rw();
	exec("sysctl kern.geom.debugflags=16");
	exec("gpart set -a active -i {$ASLICE} {$BOOT_DRIVE}");
	exec("/usr/sbin/boot0cfg -s {$ASLICE} -v /dev/{$BOOT_DRIVE}");
	// We can't update these if they are mounted now.
	if ($BOOTFLASH != $slice) {
		exec("/sbin/tunefs -L ${AGLABEL_SLICE} /dev/$ACOMPLETE_PATH");
		nanobsd_update_fstab($AGLABEL_SLICE, $ACOMPLETE_PATH, $AOLD_UFS_ID, $AUFS_ID);
	}
	exec("/sbin/sysctl kern.geom.debugflags=0");
	conf_mount_ro();
}
function nanobsd_clone_slice() {
	global $SLICE, $OLDSLICE, $TOFLASH, $COMPLETE_PATH, $COMPLETE_BOOT_PATH;
	global $GLABEL_SLICE, $UFS_ID, $OLD_UFS_ID, $BOOTFLASH;
	global $BOOT_DEVICE, $REAL_BOOT_DEVICE, $BOOT_DRIVE, $ACTIVE_SLICE;
	nanobsd_detect_slice_info();

	for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
	ob_implicit_flush(1);
	exec("/sbin/sysctl kern.geom.debugflags=16");
	exec("/bin/dd if=/dev/zero of=/dev/{$TOFLASH} bs=1m count=1");
	exec("/bin/dd if=/dev/{$BOOTFLASH} of=/dev/{$TOFLASH} bs=64k");
	exec("/sbin/tunefs -L {$GLABEL_SLICE} /dev/{$COMPLETE_PATH}");
	$status = nanobsd_update_fstab($GLABEL_SLICE, $COMPLETE_PATH, $OLD_UFS_ID, $UFS_ID);
	exec("/sbin/sysctl kern.geom.debugflags=0");
	if($status) {
		return false;
	} else {
		return true;
	}
}
function nanobsd_update_fstab($gslice, $complete_path, $oldufs, $newufs) {
	$tmppath = "/tmp/{$gslice}";
	$fstabpath = "/tmp/{$gslice}/etc/fstab";

	exec("/bin/mkdir {$tmppath}");
	exec("/sbin/fsck_ufs -y /dev/{$complete_path}");
	exec("/sbin/mount /dev/ufs/{$gslice} {$tmppath}");
	exec("/bin/cp /etc/fstab {$fstabpath}");

	if (!file_exists($fstabpath)) {
		$fstab = <<<EOF
/dev/ufs/{$gslice} / ufs ro,noatime 1 1
/dev/ufs/cf /cf ufs ro,noatime 1 1
EOF;
		if (file_put_contents($fstabpath, $fstab))
			$status = true;
		else
			$status = false;
	} else {
		$status = exec("sed -i \"\" \"s/pfsense{$oldufs}/pfsense{$newufs}/g\" {$fstabpath}");
	}
	exec("/sbin/umount {$tmppath}");
	exec("/bin/rmdir {$tmppath}");

	return $status;
}
function nanobsd_detect_slice_info() {
	global $SLICE, $OLDSLICE, $TOFLASH, $COMPLETE_PATH, $COMPLETE_BOOT_PATH;
	global $GLABEL_SLICE, $UFS_ID, $OLD_UFS_ID, $BOOTFLASH;
	global $BOOT_DEVICE, $REAL_BOOT_DEVICE, $BOOT_DRIVE, $ACTIVE_SLICE;

	$BOOT_DEVICE=nanobsd_get_boot_slice();
	$REAL_BOOT_DEVICE=get_real_slice_from_glabel($BOOT_DEVICE);
	$BOOT_DRIVE=nanobsd_get_boot_drive();
	$ACTIVE_SLICE=nanobsd_get_active_slice();

	// Detect which slice is active and set information.
	if(strstr($REAL_BOOT_DEVICE, "s1")) {
		$SLICE="2";
		$OLDSLICE="1";
		$GLABEL_SLICE="pfsense1";
		$UFS_ID="1";
		$OLD_UFS_ID="0";

	} else {
		$SLICE="1";
		$OLDSLICE="2";
		$GLABEL_SLICE="pfsense0";
		$UFS_ID="0";
		$OLD_UFS_ID="1";
	}
	$TOFLASH="{$BOOT_DRIVE}s{$SLICE}";
	$COMPLETE_PATH="{$BOOT_DRIVE}s{$SLICE}a";
	$COMPLETE_BOOT_PATH="{$BOOT_DRIVE}s{$OLDSLICE}";
	$BOOTFLASH="{$BOOT_DRIVE}s{$OLDSLICE}";
}

function nanobsd_friendly_slice_name($slicename) {
	global $g;
	return strtolower(str_ireplace('pfsense', $g['product_name'], $slicename));
}

function get_include_contents($filename) {
    if (is_file($filename)) {
        ob_start();
        include $filename;
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }
    return false;
}

/* This xml 2 array function is courtesy of the php.net comment section on xml_parse.
 * it is roughly 4 times faster then our existing pfSense parser but due to the large
 * size of the RRD xml dumps this is required.
 * The reason we do not use it for pfSense is that it does not know about array fields
 * which causes it to fail on array fields with single items. Possible Todo?
 */
function xml2array($contents, $get_attributes = 1, $priority = 'tag')
{
    if (!function_exists('xml_parser_create'))
    {
        return array ();
    }
    $parser = xml_parser_create('');
    xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
    xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
    xml_parse_into_struct($parser, trim($contents), $xml_values);
    xml_parser_free($parser);
    if (!$xml_values)
        return; //Hmm...
    $xml_array = array ();
    $parents = array ();
    $opened_tags = array ();
    $arr = array ();
    $current = & $xml_array;
    $repeated_tag_index = array ();
    foreach ($xml_values as $data)
    {
        unset ($attributes, $value);
        extract($data);
        $result = array ();
        $attributes_data = array ();
        if (isset ($value))
        {
            if ($priority == 'tag')
                $result = $value;
            else
                $result['value'] = $value;
        }
        if (isset ($attributes) and $get_attributes)
        {
            foreach ($attributes as $attr => $val)
            {
                if ($priority == 'tag')
                    $attributes_data[$attr] = $val;
                else
                    $result['attr'][$attr] = $val; //Set all the attributes in a array called 'attr'
            }
        }
        if ($type == "open")
        {
            $parent[$level -1] = & $current;
            if (!is_array($current) or (!in_array($tag, array_keys($current))))
            {
                $current[$tag] = $result;
                if ($attributes_data)
                    $current[$tag . '_attr'] = $attributes_data;
                $repeated_tag_index[$tag . '_' . $level] = 1;
                $current = & $current[$tag];
            }
            else
            {
                if (isset ($current[$tag][0]))
                {
                    $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
                    $repeated_tag_index[$tag . '_' . $level]++;
                }
                else
                {
                    $current[$tag] = array (
                        $current[$tag],
                        $result
                    );
                    $repeated_tag_index[$tag . '_' . $level] = 2;
                    if (isset ($current[$tag . '_attr']))
                    {
                        $current[$tag]['0_attr'] = $current[$tag . '_attr'];
                        unset ($current[$tag . '_attr']);
                    }
                }
                $last_item_index = $repeated_tag_index[$tag . '_' . $level] - 1;
                $current = & $current[$tag][$last_item_index];
            }
        }
        elseif ($type == "complete")
        {
            if (!isset ($current[$tag]))
            {
                $current[$tag] = $result;
                $repeated_tag_index[$tag . '_' . $level] = 1;
                if ($priority == 'tag' and $attributes_data)
                    $current[$tag . '_attr'] = $attributes_data;
            }
            else
            {
                if (isset ($current[$tag][0]) and is_array($current[$tag]))
                {
                    $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
                    if ($priority == 'tag' and $get_attributes and $attributes_data)
                    {
                        $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
                    }
                    $repeated_tag_index[$tag . '_' . $level]++;
                }
                else
                {
                    $current[$tag] = array (
                        $current[$tag],
                        $result
                    );
                    $repeated_tag_index[$tag . '_' . $level] = 1;
                    if ($priority == 'tag' and $get_attributes)
                    {
                        if (isset ($current[$tag . '_attr']))
                        {
                            $current[$tag]['0_attr'] = $current[$tag . '_attr'];
                            unset ($current[$tag . '_attr']);
                        }
                        if ($attributes_data)
                        {
                            $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
                        }
                    }
                    $repeated_tag_index[$tag . '_' . $level]++; //0 and 1 index is already taken
                }
            }
        }
        elseif ($type == 'close')
        {
            $current = & $parent[$level -1];
        }
    }
    return ($xml_array);
}

function get_country_name($country_code) {
	if ($country_code != "ALL" && strlen($country_code) != 2)
		return "";

	$country_names_xml = "/usr/local/share/mobile-broadband-provider-info/iso_3166-1_list_en.xml";
	$country_names_contents = file_get_contents($country_names_xml);
	$country_names = xml2array($country_names_contents);

	if($country_code == "ALL") {
		$country_list = array();
		foreach($country_names['ISO_3166-1_List_en']['ISO_3166-1_Entry'] as $country) {
			$country_list[] = array( "code" => $country['ISO_3166-1_Alpha-2_Code_element'],
						 "name" => ucwords(strtolower($country['ISO_3166-1_Country_name'])) );
		}
		return $country_list;
	}

	foreach ($country_names['ISO_3166-1_List_en']['ISO_3166-1_Entry'] as $country) {
		if ($country['ISO_3166-1_Alpha-2_Code_element'] == strtoupper($country_code)) {
			return ucwords(strtolower($country['ISO_3166-1_Country_name']));
		}
	}
	return "";
}

/* sort by interface only, retain the original order of rules that apply to
   the same interface */
function filter_rules_sort() {
	global $config;

	/* mark each rule with the sequence number (to retain the order while sorting) */
	for ($i = 0; isset($config['filter']['rule'][$i]); $i++)
		$config['filter']['rule'][$i]['seq'] = $i;

	usort($config['filter']['rule'], "filter_rules_compare");

	/* strip the sequence numbers again */
	for ($i = 0; isset($config['filter']['rule'][$i]); $i++)
		unset($config['filter']['rule'][$i]['seq']);
}
function filter_rules_compare($a, $b) {
	if (isset($a['floating']) && isset($b['floating']))
		return $a['seq'] - $b['seq'];
	else if (isset($a['floating']))
		return -1;
	else if (isset($b['floating']))
		return 1;
	else if ($a['interface'] == $b['interface'])
		return $a['seq'] - $b['seq'];
	else
		return compare_interface_friendly_names($a['interface'], $b['interface']);
}

/****f* pfsense-utils/load_mac_manufacturer_table
 * NAME
 *   load_mac_manufacturer_table
 * INPUTS
 *   none
 * RESULT
 *   returns associative array with MAC-Manufacturer pairs
 ******/
function load_mac_manufacturer_table() {
	/* load MAC-Manufacture data from the file */
	$macs = false;
	if (file_exists("/usr/local/share/nmap/nmap-mac-prefixes"))
		$macs=file("/usr/local/share/nmap/nmap-mac-prefixes");
	if ($macs){
		foreach ($macs as $line){
			if (preg_match('/([0-9A-Fa-f]{6}) (.*)$/', $line, $matches)){
				/* store values like this $mac_man['000C29']='VMware' */
				$mac_man["$matches[1]"]=$matches[2];
			}
		}
 		return $mac_man;
	} else
		return -1;

}

/****f* pfsense-utils/is_ipaddr_configured
 * NAME
 *   is_ipaddr_configured
 * INPUTS
 *   IP Address to check.
 * RESULT
 *   returns true if the IP Address is
 *   configured and present on this device.
*/
function is_ipaddr_configured($ipaddr) {
	$interface_list_ips = get_configured_ip_addresses();
	foreach($interface_list_ips as $ilips) {
		if(strcasecmp($ipaddr, $ilips) == 0) 
				return true;
	}	
}

/****f* pfsense-utils/pfSense_handle_custom_code
 * NAME
 *   pfSense_handle_custom_code
 * INPUTS
 *   directory name to process
 * RESULT
 *   globs the directory and includes the files
 */
function pfSense_handle_custom_code($src_dir) {
	// Allow extending of the nat edit page and include custom input validation 
	if(is_dir("$src_dir")) {
		$cf = glob($src_dir . "/*.inc");
		foreach($cf as $nf) {
			if($nf == "." || $nf == "..") 
				continue;
			// Include the extra handler
			include("$nf");
		}
	}
}

?>
