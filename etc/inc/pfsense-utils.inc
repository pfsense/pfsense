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
	pfSense_BUILDER_BINARIES:	/sbin/ifconfig	/sbin/pfctl	/usr/local/bin/php /usr/bin/netstat
	pfSense_BUILDER_BINARIES:	/bin/df	/usr/bin/grep	/usr/bin/awk	/bin/rm	/usr/sbin/pwd_mkdb	/usr/bin/host
	pfSense_BUILDER_BINARIES:	/sbin/kldload
	pfSense_MODULE:	utils
*/

/****f* pfsense-utils/have_natpfruleint_access
 * NAME
 *   have_natpfruleint_access
 * INPUTS
 *	none
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
 *	none
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
 *	none
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
 *	none
 * RESULT
 *   returns true if an ip address is in a private range
 ******/
function is_private_ip($iptocheck) {
	$isprivate = false;
	$ip_private_list=array(
		"10.0.0.0/8",
		"100.64.0.0/10",
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
 *	none
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
		set_single_sysctl("kern.polling.idle_poll", "1");
	else
		set_single_sysctl("kern.polling.idle_poll", "0");

	if($config['system']['polling_each_burst'])
		set_single_sysctl("kern.polling.each_burst", $config['system']['polling_each_burst']);
	if($config['system']['polling_burst_max'])
		set_single_sysctl("kern.polling.burst_max", $config['system']['polling_burst_max']);
	if($config['system']['polling_user_frac'])
		set_single_sysctl("kern.polling.user_frac", $config['system']['polling_user_frac']);
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
	$status = get_single_sysctl('net.inet.carp.allow');
	return (intval($status) > 0);
}

/*
 * convert_ip_to_network_format($ip, $subnet): converts an ip address to network form

 */
function convert_ip_to_network_format($ip, $subnet) {
	$ipsplit = explode('.', $ip);
	$string = $ipsplit[0] . "." . $ipsplit[1] . "." . $ipsplit[2] . ".0/" . $subnet;
	return $string;
}

/*
 * get_carp_interface_status($carpinterface): returns the status of a carp ip
 */
function get_carp_interface_status($carpinterface) {
	$carp_query = "";

	/* XXX: Need to fidn a better way for this! */
	list ($interface, $vhid) = explode("_vip", $carpinterface);
	$interface = get_real_interface($interface);
	exec("/sbin/ifconfig $interface | /usr/bin/grep -v grep | /usr/bin/grep carp: | /usr/bin/grep 'vhid {$vhid}'", $carp_query);
	foreach($carp_query as $int) {
		if(stristr($int, "MASTER"))
			return gettext("MASTER");
		if(stristr($int, "BACKUP"))
			return gettext("BACKUP");
		if(stristr($int, "INIT"))
			return gettext("INIT");
	}
	return;
}

/*
 * get_pfsync_interface_status($pfsyncinterface): returns the status of a pfsync
 */
function get_pfsync_interface_status($pfsyncinterface) {
	if (!does_interface_exist($pfsyncinterface))
		return;

	return exec_command("/sbin/ifconfig {$pfsyncinterface} | /usr/bin/awk '/pfsync:/ {print \$5}'");
}

/*
 * add_rule_to_anchor($anchor, $rule): adds the specified rule to an anchor
 */
function add_rule_to_anchor($anchor, $rule, $label) {
	mwexec("echo " . escapeshellarg($rule) . " | /sbin/pfctl -a " . escapeshellarg($anchor) . ":" . escapeshellarg($label) . " -f -");
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
		write_config(gettext("After synch increase advertising skew"));
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
		log_error(gettext("Error creating socket!"));
		log_error(sprintf(gettext("Error code is '%1\$s' - %2\$s"), socket_last_error($s), socket_strerror(socket_last_error($s))));
	} else {
		// setting a broadcast option to socket:
		$opt_ret =  socket_set_option($s, 1, 6, TRUE);
		if($opt_ret < 0)
			log_error(sprintf(gettext("setsockopt() failed, error: %s"), strerror($opt_ret)));
		$e = socket_sendto($s, $msg, strlen($msg), 0, $addr, 2050);
		socket_close($s);
		log_error(sprintf(gettext('Magic Packet sent (%1$s) to {%2$s} MAC=%3$s'), $e, $addr, $mac));
		return true;
	}

	return false;
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
function backup_config_section($section_name) {
	global $config;
	$new_section = &$config[$section_name];
	/* generate configuration XML */
	$xmlconfig = dump_xml_config($new_section, $section_name);
	$xmlconfig = str_replace("<?xml version=\"1.0\"?>", "", $xmlconfig);
	return $xmlconfig;
}

/*
 *  restore_config_section($section_name, new_contents): restore a configuration section,
 *                                                  and write the configuration out
 *                                                  to disk/cf.
 */
function restore_config_section($section_name, $new_contents) {
	global $config, $g;
	conf_mount_rw();
	$fout = fopen("{$g['tmp_path']}/tmpxml","w");
	fwrite($fout, $new_contents);
	fclose($fout);

	$xml = parse_xml_config($g['tmp_path'] . "/tmpxml", null);
	if ($xml['pfsense']) {
		$xml = $xml['pfsense'];
	}
	else if ($xml['m0n0wall']) {
		$xml = $xml['m0n0wall'];
	}
	if ($xml[$section_name]) {
		$section_xml = $xml[$section_name];
	} else {
		$section_xml = -1;
	}

	@unlink($g['tmp_path'] . "/tmpxml");
	if ($section_xml === -1) {
		return false;
	}
	$config[$section_name] = &$section_xml;
	if(file_exists("{$g['tmp_path']}/config.cache"))
		unlink("{$g['tmp_path']}/config.cache");
	write_config(sprintf(gettext("Restored %s of config file (maybe from CARP partner)"), $section_name));
	disable_security_checks();
	conf_mount_ro();
	return true;
}

/*
 *  merge_config_section($section_name, new_contents):   restore a configuration section,
 *                                                  and write the configuration out
 *                                                  to disk/cf.  But preserve the prior
 * 													structure if needed
 */
function merge_config_section($section_name, $new_contents) {
	global $config;
	conf_mount_rw();
	$fname = get_tmp_filename();
	$fout = fopen($fname, "w");
	fwrite($fout, $new_contents);
	fclose($fout);
	$section_xml = parse_xml_config($fname, $section_name);
	$config[$section_name] = $section_xml;
	unlink($fname);
	write_config(sprintf(gettext("Restored %s of config file (maybe from CARP partner)"), $section_name));
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
		$command = "/usr/local/bin/php -l " . escapeshellarg($code_to_check);
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
 * call_pfsense_method(): Call a method exposed by the pfsense.org XMLRPC server.
 */
function call_pfsense_method($method, $params, $timeout = 0) {
	global $g, $config;

	$xmlrpc_base_url = get_active_xml_rpc_base_url();
	$xmlrpc_path = $g['xmlrpcpath'];
	
	$xmlrpcfqdn = preg_replace("(https?://)", "", $xmlrpc_base_url);
	$ip = gethostbyname($xmlrpcfqdn);
	if($ip == $xmlrpcfqdn)
		return false;

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
	$resp = $cli->send($msg, $timeout);
	if(!is_object($resp)) {
		log_error(sprintf(gettext("XMLRPC communication error: %s"), $cli->errstr));
		return false;
	} elseif($resp->faultCode()) {
		log_error(sprintf(gettext('XMLRPC request failed with error %1$s: %2$s'), $resp->faultCode(), $resp->faultString()));
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
	
	$xmlrpc_base_url = get_active_xml_rpc_base_url();
	$xmlrpcfqdn = preg_replace("(https?://)", "", $xmlrpc_base_url);
	$ip = gethostbyname($xmlrpcfqdn);
	if($ip == $xmlrpcfqdn)
		return false;
	$version = php_uname('r');
	$version = explode('-', $version);
	$rawparams = array("firmware" => array("version" => trim(file_get_contents('/etc/version'))),
		"kernel"   => array("version" => $version[0]),
		"base"     => array("version" => $version[0]),
		"platform" => trim(file_get_contents('/etc/platform')),
		"config_version" => $config['version']
		);
	unset($version);

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

	$os_version = trim(substr(php_uname("r"), 0, strpos(php_uname("r"), '-')));

	return array(
		"firmware" => array("version" => trim(file_get_contents('/etc/version', " \n"))),
		"kernel"   => array("version" => $os_version),
		"base"     => array("version" => $os_version),
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
		log_error(gettext("reload_interfaces_sync() is starting."));

	/* parse config.xml again */
	$config = parse_config(true);

	/* enable routing */
	system_routing_enable();
	if($g['debug'])
		log_error(gettext("Enabling system routing"));

	if($g['debug'])
		log_error(gettext("Cleaning up Interfaces"));

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

function setup_serial_port($when="save", $path="") {
	global $g, $config;
	conf_mount_rw();
	$prefix = "";
	if (($when == "upgrade") && (!empty($path)) && is_dir($path.'/boot/'))
		$prefix = "/tmp/{$path}";
	$boot_config_file = "{$path}/boot.config";
	$loader_conf_file = "{$path}/boot/loader.conf";
	/* serial console - write out /boot.config */
	if(file_exists($boot_config_file))
		$boot_config = file_get_contents($boot_config_file);
	else
		$boot_config = "";

	$serialspeed = (is_numeric($config['system']['serialspeed'])) ? $config['system']['serialspeed'] : "115200";
	if ($g['platform'] != "cdrom") {
		$boot_config_split = explode("\n", $boot_config);
		$fd = fopen($boot_config_file,"w");
		if($fd) {
			foreach($boot_config_split as $bcs) {
				if(stristr($bcs, "-D") || stristr($bcs, "-h")) {
					/* DONT WRITE OUT, WE'LL DO IT LATER */
				} else {
					if($bcs <> "")
						fwrite($fd, "{$bcs}\n");
				}
			}
			if (($g['platform'] == "nanobsd") && !file_exists("/etc/nano_use_vga.txt"))
				fwrite($fd, "-S{$serialspeed} -h");
			else if (is_serial_enabled())
				fwrite($fd, "-S{$serialspeed} -D");
			fclose($fd);
		}

		/* serial console - write out /boot/loader.conf */
		if ($when == "upgrade")
			system("echo \"Reading {$loader_conf_file}...\" >> /conf/upgrade_log.txt");
		$boot_config = file_get_contents($loader_conf_file);
		$boot_config_split = explode("\n", $boot_config);
		if(count($boot_config_split) > 0) {
			$new_boot_config = array();
			// Loop through and only add lines that are not empty, and which
			//  do not contain a console directive.
			foreach($boot_config_split as $bcs)
				if(!empty($bcs)
					&& (stripos($bcs, "console") === false)
					&& (stripos($bcs, "boot_multicons") === false)
					&& (stripos($bcs, "boot_serial") === false)
					&& (stripos($bcs, "hw.usb.no_pf") === false))
					$new_boot_config[] = $bcs;

			if (($g['platform'] == "nanobsd") && !file_exists("/etc/nano_use_vga.txt")) {
				$new_boot_config[] = 'boot_serial="YES"';
				$new_boot_config[] = 'console="comconsole"';
			} else if (is_serial_enabled()) {
				$new_boot_config[] = 'boot_multicons="YES"';
				$new_boot_config[] = 'boot_serial="YES"';
				$primaryconsole = isset($g['primaryconsole_force']) ? $g['primaryconsole_force'] : $config['system']['primaryconsole'];
				switch ($primaryconsole) {
					case "video":
						$new_boot_config[] = 'console="vidconsole,comconsole"';
						break;
					case "serial":
					default:
						$new_boot_config[] = 'console="comconsole,vidconsole"';
				}
			}
			$new_boot_config[] = 'comconsole_speed="' . $serialspeed . '"';
			$new_boot_config[] = 'hw.usb.no_pf="1"';

			file_put_contents($loader_conf_file, implode("\n", $new_boot_config) . "\n");
		}
	}
	$ttys = file_get_contents("/etc/ttys");
	$ttys_split = explode("\n", $ttys);
	$fd = fopen("/etc/ttys", "w");

	$on_off = (is_serial_enabled() ? 'on' : 'off');

	if (isset($config['system']['disableconsolemenu'])) {
		$console_type = 'Pc';
		$serial_type = 'std.' . $serialspeed;
	} else {
		$console_type = 'al.Pc';
		$serial_type = 'al.' . $serialspeed;
	}
	foreach($ttys_split as $tty) {
		if (stristr($tty, "ttyv0"))
			fwrite($fd, "ttyv0	\"/usr/libexec/getty {$console_type}\"	cons25	on	secure\n");
		else if (stristr($tty, "ttyu0"))
			fwrite($fd, "ttyu0	\"/usr/libexec/getty {$serial_type}\"	cons25	{$on_off}	secure\n");
		else
			fwrite($fd, $tty . "\n");
	}
	unset($on_off, $console_type, $serial_type);
	fclose($fd);
	reload_ttys();

	conf_mount_ro();
	return;
}

function is_serial_enabled() {
	global $g, $config;

	if (!isset($g['enableserial_force']) &&
	    !isset($config['system']['enableserial']) &&
	    ($g['platform'] == "pfSense" || $g['platform'] == "cdrom" || file_exists("/etc/nano_use_vga.txt")))
		return false;

	return true;
}

function reload_ttys() {
	// Send a HUP signal to init will make it reload /etc/ttys
	posix_kill(1, SIGHUP);
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
function is_dhcp_server_enabled() {
	global $config;

	if (!is_array($config['dhcpd']))
		return false;

	foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
		if (isset($dhcpifconf['enable']) && !empty($config['interfaces'][$dhcpif]))
			return true;
	}

	return false;
}

/* DHCP enabled on any interfaces? */
function is_dhcpv6_server_enabled() {
	global $config;

	if (is_array($config['interfaces'])) {
		foreach ($config['interfaces'] as $ifcfg) {
			if (isset($ifcfg['enable']) && !empty($ifcfg['track6-interface']))
				return true;
		}
	}

	if (!is_array($config['dhcpdv6']))
		return false;

	foreach ($config['dhcpdv6'] as $dhcpv6if => $dhcpv6ifconf) {
		if (isset($dhcpv6ifconf['enable']) && !empty($config['interfaces'][$dhcpv6if]))
			return true;
	}

	return false;
}

/* radvd enabled on any interfaces? */
function is_radvd_enabled() {
	global $config;

	if (!is_array($config['dhcpdv6']))
		$config['dhcpdv6'] = array();

	$dhcpdv6cfg = $config['dhcpdv6'];
	$Iflist = get_configured_interface_list();

	/* handle manually configured DHCP6 server settings first */
	foreach ($dhcpdv6cfg as $dhcpv6if => $dhcpv6ifconf) {
		if(!isset($config['interfaces'][$dhcpv6if]['enable']))
			continue;

		if(!isset($dhcpv6ifconf['ramode']))
			$dhcpv6ifconf['ramode'] = $dhcpv6ifconf['mode'];

		if($dhcpv6ifconf['ramode'] == "disabled")
			continue;

		$ifcfgipv6 = get_interface_ipv6($dhcpv6if);
		if(!is_ipaddrv6($ifcfgipv6))
			continue;

		return true;
	}

	/* handle DHCP-PD prefixes and 6RD dynamic interfaces */
	foreach ($Iflist as $if => $ifdescr) {
		if(!isset($config['interfaces'][$if]['track6-interface']))
			continue;
		if(!isset($config['interfaces'][$if]['enable']))
			continue;

		$ifcfgipv6 = get_interface_ipv6($if);
		if(!is_ipaddrv6($ifcfgipv6))
			continue;

		$ifcfgsnv6 = get_interface_subnetv6($if);
		$subnetv6 = gen_subnetv6($ifcfgipv6, $ifcfgsnv6);

		if(!is_ipaddrv6($subnetv6))
			continue;

		return true;
	}

	return false;
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
		$total_time = gettext("No history data found!");
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
	$ifinfo['linklocal'] = get_interface_linklocal($ifdescr);
	$ifinfo['ipaddrv6'] = get_interface_ipv6($ifdescr);
	$ifinfo['subnetv6'] = get_interface_subnetv6($ifdescr);
	if (isset($ifinfotmp['link0']))
		$link0 = "down";
	$ifinfotmp = pfSense_get_interface_stats($chkif);
	// $ifinfo['inpkts'] = $ifinfotmp['inpkts'];
	// $ifinfo['outpkts'] = $ifinfotmp['outpkts'];
	$ifinfo['inerrs'] = $ifinfotmp['inerrs'];
	$ifinfo['outerrs'] = $ifinfotmp['outerrs'];
	$ifinfo['collisions'] = $ifinfotmp['collisions'];

	/* Use pfctl for non wrapping 64 bit counters */
	/* Pass */
	exec("/sbin/pfctl -vvsI -i {$chkif}", $pfctlstats);
	$pf_in4_pass = preg_split("/ +/ ", $pfctlstats[3]);
	$pf_out4_pass = preg_split("/ +/", $pfctlstats[5]);
	$pf_in6_pass = preg_split("/ +/ ", $pfctlstats[7]);
	$pf_out6_pass = preg_split("/ +/", $pfctlstats[9]);
	$in4_pass = $pf_in4_pass[5];
	$out4_pass = $pf_out4_pass[5];
	$in4_pass_packets = $pf_in4_pass[3];
	$out4_pass_packets = $pf_out4_pass[3];
	$in6_pass = $pf_in6_pass[5];
	$out6_pass = $pf_out6_pass[5];
	$in6_pass_packets = $pf_in6_pass[3];
	$out6_pass_packets = $pf_out6_pass[3];
	$ifinfo['inbytespass'] = $in4_pass + $in6_pass;
	$ifinfo['outbytespass'] = $out4_pass + $out6_pass;
	$ifinfo['inpktspass'] = $in4_pass_packets + $in6_pass_packets;
	$ifinfo['outpktspass'] = $out4_pass_packets + $out6_pass_packets;

	/* Block */
	$pf_in4_block = preg_split("/ +/", $pfctlstats[4]);
	$pf_out4_block = preg_split("/ +/", $pfctlstats[6]);
	$pf_in6_block = preg_split("/ +/", $pfctlstats[8]);
	$pf_out6_block = preg_split("/ +/", $pfctlstats[10]);
	$in4_block = $pf_in4_block[5];
	$out4_block = $pf_out4_block[5];
	$in4_block_packets = $pf_in4_block[3];
	$out4_block_packets = $pf_out4_block[3];
	$in6_block = $pf_in6_block[5];
	$out6_block = $pf_out6_block[5];
	$in6_block_packets = $pf_in6_block[3];
	$out6_block_packets = $pf_out6_block[3];
	$ifinfo['inbytesblock'] = $in4_block + $in6_block;
	$ifinfo['outbytesblock'] = $out4_block + $out6_block;
	$ifinfo['inpktsblock'] = $in4_block_packets + $in6_block_packets;
	$ifinfo['outpktsblock'] = $out4_block_packets + $out6_block_packets;

	$ifinfo['inbytes'] = $in4_pass + $in6_pass;
	$ifinfo['outbytes'] = $out4_pass + $out6_pass;
	$ifinfo['inpkts'] = $in4_pass_packets + $in6_pass_packets;
	$ifinfo['outpkts'] = $out4_pass_packets + $out6_pass_packets;

	$ifconfiginfo = "";
	$link_type = $config['interfaces'][$ifdescr]['ipaddr'];
	switch ($link_type) {
	/* DHCP? -> see if dhclient is up */
	case "dhcp":
		/* see if dhclient is up */
		if (find_dhclient_process($ifinfo['if']) != 0)
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
			$ifinfo['pppinfo'] = $dev . " " . gettext("device not present! Is the modem attached to the system?");
		}

		$usbmodemoutput = array();
		exec("usbconfig", $usbmodemoutput);
		$mondev = "{$g['tmp_path']}/3gstats.{$ifdescr}";
		if(file_exists($mondev)) {
			$cellstats = file($mondev);
			/* skip header */
			$a_cellstats = explode(",", $cellstats[1]);
			if(preg_match("/huawei/i", implode("\n", $usbmodemoutput))) {
				$ifinfo['cell_rssi'] = huawei_rssi_to_string($a_cellstats[1]);
				$ifinfo['cell_mode'] = huawei_mode_to_string($a_cellstats[2], $a_cellstats[3]);
				$ifinfo['cell_simstate'] = huawei_simstate_to_string($a_cellstats[10]);
				$ifinfo['cell_service'] = huawei_service_to_string(trim($a_cellstats[11]));
			}
			if(preg_match("/zte/i", implode("\n", $usbmodemoutput))) {
				$ifinfo['cell_rssi'] = zte_rssi_to_string($a_cellstats[1]);
				$ifinfo['cell_mode'] = zte_mode_to_string($a_cellstats[2], $a_cellstats[3]);
				$ifinfo['cell_simstate'] = zte_simstate_to_string($a_cellstats[10]);
				$ifinfo['cell_service'] = zte_service_to_string(trim($a_cellstats[11]));
			}
			$ifinfo['cell_upstream'] = $a_cellstats[4];
			$ifinfo['cell_downstream'] = trim($a_cellstats[5]);
			$ifinfo['cell_sent'] = $a_cellstats[6];
			$ifinfo['cell_received'] = trim($a_cellstats[7]);
			$ifinfo['cell_bwupstream'] = $a_cellstats[8];
			$ifinfo['cell_bwdownstream'] = trim($a_cellstats[9]);
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
				if($ifinfo['status'] == gettext("running"))
					$ifinfo['status'] = gettext("up");
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
		if (interface_has_gateway($ifdescr)) {
			$ifinfo['gateway'] = get_interface_gateway($ifdescr);
			$ifinfo['gatewayv6'] = get_interface_gateway_v6($ifdescr);
		}
	}

	$bridge = "";
	$bridge = link_interface_to_bridge($ifdescr);
	if($bridge) {
		$bridge_text = `/sbin/ifconfig {$bridge}`;
		if(stristr($bridge_text, "blocking") <> false) {
			$ifinfo['bridge'] = "<b><font color='red'>" . gettext("blocking") . "</font></b> - " . gettext("check for ethernet loops");
			$ifinfo['bridgeint'] = $bridge;
		} else if(stristr($bridge_text, "learning") <> false) {
			$ifinfo['bridge'] = gettext("learning");
			$ifinfo['bridgeint'] = $bridge;
		} else if(stristr($bridge_text, "forwarding") <> false) {
			$ifinfo['bridge'] = gettext("forwarding");
			$ifinfo['bridgeint'] = $bridge;
		}
	}

	return $ifinfo;
}

//returns cpu speed of processor. Good for determining capabilities of machine
function get_cpu_speed() {
	return get_single_sysctl("hw.clockrate");
}

function get_uptime_sec() {
	$boottime = "";
	$matches = "";
	$boottime = get_single_sysctl("kern.boottime");
	preg_match("/sec = (\d+)/", $boottime, $matches);
	$boottime = $matches[1];
	if(intval($boottime) == 0)
		return 0;

	$uptime = time() - $boottime;
	return $uptime;
}

function add_hostname_to_watch($hostname) {
	if(!is_dir("/var/db/dnscache")) {
		mkdir("/var/db/dnscache");
	}
	$result = array();
	if((is_fqdn($hostname)) && (!is_ipaddr($hostname))) {
		$domrecords = array();
		$domips = array();
		exec("host -t A " . escapeshellarg($hostname), $domrecords, $rethost);
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
		/* Remove empty elements */
		$result = array_filter(explode("\n", $contents), 'strlen');
	}
	return $result;
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
	$physmem = $memory[0];
	/* Be cautious and only allocate 10% of system memory to the state table */
	$max_states = (int) ($physmem/10)*1000;
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
 * if no change we return false */
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
		exec("host -t A " . escapeshellarg($hostname), $domrecords, $rethost);
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
			log_error(sprintf(gettext('DNSCACHE: Found old IP %1$s and new IP %2$s'), $oldcontents, $contents));
		}
		return ($oldcontents);
	} else {
		return false;
	}
}

/*
 * load_crypto() - Load crypto modules if enabled in config.
 */
function load_crypto() {
	global $config, $g;
	$crypto_modules = array('glxsb', 'aesni');

	if (!in_array($config['system']['crypto_hardware'], $crypto_modules))
		return false;

	if (!empty($config['system']['crypto_hardware']) && !is_module_loaded($config['system']['crypto_hardware'])) {
		log_error("Loading {$config['system']['crypto_hardware']} cryptographic accelerator module.");
		mwexec("/sbin/kldload {$config['system']['crypto_hardware']}");
	}
}

/*
 * load_thermal_hardware() - Load temperature monitor kernel module
 */
function load_thermal_hardware() {
	global $config, $g;
	$thermal_hardware_modules = array('coretemp', 'amdtemp');

	if (!in_array($config['system']['thermal_hardware'], $thermal_hardware_modules))
		return false;

	if (!empty($config['system']['thermal_hardware']) && !is_module_loaded($config['system']['thermal_hardware'])) {
		log_error("Loading {$config['system']['thermal_hardware']} thermal monitor module.");
		mwexec("/sbin/kldload {$config['system']['thermal_hardware']}");
	}
}

/****f* pfsense-utils/isvm
 * NAME
 *   isvm
 * INPUTS
 *	none
 * RESULT
 *   returns true if machine is running under a virtual environment
 ******/
function isvm() {
	$virtualenvs = array("vmware", "parallels", "qemu", "bochs", "plex86");
	$bios_product = trim(`/bin/kenv smbios.system.product`);
	foreach ($virtualenvs as $virtualenv)
		if (stripos($bios_product, $virtualenv) !== false)
			return true;

	return false;
}

function get_freebsd_version() {
	$version = explode(".", php_uname("r"));
	return $version[0];
}

function download_file($url, $destination, $verify_ssl = false, $connect_timeout = 60, $timeout = 0) {
	global $config, $g;

	$fp = fopen($destination, "wb");

	if (!$fp)
		return false;

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify_ssl);
	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_USERAGENT, $g['product_name'] . '/' . rtrim(file_get_contents("/etc/version")));

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
	fclose($fp);
	curl_close($ch);
	return ($http_code == 200) ? true : $http_code;
}

function download_file_with_progress_bar($url_file, $destination_file, $readbody = 'read_body', $connect_timeout=60, $timeout=0) {
	global $ch, $fout, $file_size, $downloaded, $config, $first_progress_update;
	$file_size  = 1;
	$downloaded = 1;
	$first_progress_update = TRUE;
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
	curl_setopt($ch, CURLOPT_USERAGENT, $g['product_name'] . '/' . rtrim(file_get_contents("/etc/version")));

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
	preg_match("/(Content-Length:) (.*)/", $string, $regs);
	if($regs[2] <> "") {
		$file_size = intval($regs[2]);
	}
	ob_flush();
	return $length;
}

function read_body($ch, $string) {
	global $fout, $file_size, $downloaded, $sendto, $static_status, $static_output, $lastseen, $first_progress_update;
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
				if(($downloadProgress % 10) == 0 || $downloadProgress < 10) {
					$tostatus = $static_status . $downloadProgress . "%";
					if ($downloadProgress == 100) {
						$tostatus = $tostatus . "\r";
					}
					update_status($tostatus);
				}
			} else {
				$tostatus = $static_status . $downloadProgress . "%";
				update_status($tostatus);
			}
		} else {
			if($pkg_interface == "console") {
				if(($downloadProgress % 10) == 0 || $downloadProgress < 10) {
					$tooutput = $static_output . $downloadProgress . "%";
					if ($downloadProgress == 100) {
						$tooutput = $tooutput . "\r";
					}
					update_output_window($tooutput);
				}
			} else {
				$tooutput = $static_output . $downloadProgress . "%";
				update_output_window($tooutput);
			}
		}
				if(($pkg_interface != "console") || (($downloadProgress % 10) == 0) || ($downloadProgress < 10)) {
					update_progress_bar($downloadProgress, $first_progress_update);
					$first_progress_update = FALSE;
				}
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
	$log = preg_replace("/\n/", "\\n", $text);
	if($pkg_interface != "console") {
		echo "\n<script type=\"text/javascript\">";
		echo "\n//<![CDATA[";
		echo "\nthis.document.forms[0].output.value = \"" . $log . "\";";
		echo "\nthis.document.forms[0].output.scrollTop = this.document.forms[0].output.scrollHeight;";
		echo "\n//]]>";
		echo "\n</script>";
	}
	/* ensure that contents are written out */
	ob_flush();
}

/*
 *   update_status: update top textarea dynamically.
 */
function update_status($status) {
	global $pkg_interface;
	if($pkg_interface == "console") {
		echo "\r{$status}";
	} else {
		echo "\n<script type=\"text/javascript\">";
		echo "\n//<![CDATA[";
		echo "\nthis.document.forms[0].status.value=\"" . $status . "\";";
		echo "\n//]]>";
		echo "\n</script>";
	}
	/* ensure that contents are written out */
	ob_flush();
}

/*
 * update_progress_bar($percent, $first_time): updates the javascript driven progress bar.
 */
function update_progress_bar($percent, $first_time) {
	global $pkg_interface;
	if($percent > 100) $percent = 1;
	if($pkg_interface <> "console") {
		echo "\n<script type=\"text/javascript\">";
		echo "\n//<![CDATA[";
		echo "\ndocument.progressbar.style.width='" . $percent . "%';";
		echo "\n//]]>";
		echo "\n</script>";
	} else {
		if(!($first_time))
			echo "\x08\x08\x08\x08\x08";
		echo sprintf("%4d%%", $percent);
	}
}

/* Split() is being DEPRECATED as of PHP 5.3.0 and REMOVED as of PHP 6.0.0. Relying on this feature is highly discouraged. */
if(!function_exists("split")) {
	function split($separator, $haystack, $limit = null) {
		log_error("deprecated split() call with separator '{$separator}'");
		return preg_split($separator, $haystack, $limit);
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

	$updated = false;

	/* item is a url type */
	$lockkey = lock('aliasurl');
	if (is_array($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $x => $alias) {
			if (empty($alias['aliasurl']))
				continue;

			$address = "";
			$isfirst = 0;
			foreach ($alias['aliasurl'] as $alias_url) {
				/* fetch down and add in */
				$temp_filename = tempnam("{$g['tmp_path']}/", "alias_import");
				unlink($temp_filename);
				$verify_ssl = isset($config['system']['checkaliasesurlcert']);
				mkdir($temp_filename);
				download_file($alias_url, $temp_filename . "/aliases", $verify_ssl);

				/* if the item is tar gzipped then extract */
				if (stripos($alias_url, '.tgz')) {
					if (!process_alias_tgz($temp_filename))
						continue;
				} else if (stripos($alias_url, '.zip')) {
					if (!process_alias_unzip($temp_filename))
						continue;
				}
				if (file_exists("{$temp_filename}/aliases")) {
					$fd = @fopen("{$temp_filename}/aliases", 'r');
					if (!$fd) {
						log_error(gettext("Could not process aliases from alias: {$alias_url}"));
						continue;
					}
					/* NOTE: fgetss() is not a typo RTFM before being smart */
					while (($fc = fgetss($fd)) !== FALSE) {
						$tmp = trim($fc, " \t\n\r");
						if (empty($tmp))
							continue;
						$tmp_str = strstr($tmp, '#', true);
						if (!empty($tmp_str))
							$tmp = $tmp_str;
						if ($isfirst == 1)
							$address .= ' ';
						$address .= $tmp;
						$isfirst = 1;
					}
					fclose($fd);
					mwexec("/bin/rm -rf {$temp_filename}");
				}
			}
			if (!empty($address)) {
				$config['aliases']['alias'][$x]['address'] = $address;
				$updated = true;
			}
		}
	}
	unlock($lockkey);

	/* Report status to callers as well */
	return $updated;
}

function process_alias_unzip($temp_filename) {
	if(!file_exists("/usr/local/bin/unzip")) {
		log_error(gettext("Alias archive is a .zip file which cannot be decompressed because utility is missing!"));
		return false;
	}
	rename("{$temp_filename}/aliases", "{$temp_filename}/aliases.zip");
	mwexec("/usr/local/bin/unzip {$temp_filename}/aliases.tgz -d {$temp_filename}/aliases/");
	unlink("{$temp_filename}/aliases.zip");
	$files_to_process = return_dir_as_array("{$temp_filename}/");
	/* foreach through all extracted files and build up aliases file */
	$fd = @fopen("{$temp_filename}/aliases", "w");
	if (!$fd) {
		log_error(gettext("Could not open {$temp_filename}/aliases for writing!"));
		return false;
	}
	foreach($files_to_process as $f2p) {
		$tmpfd = @fopen($f2p, 'r');
		if (!$tmpfd) {
			log_error(gettext("The following file could not be read {$f2p} from {$temp_filename}"));
			continue;
		}
		while (($tmpbuf = fread($tmpfd, 65536)) !== FALSE)
			fwrite($fd, $tmpbuf);
		fclose($tmpfd);
		unlink($f2p);
	}
	fclose($fd);
	unset($tmpbuf);

	return true;
}

function process_alias_tgz($temp_filename) {
	if(!file_exists('/usr/bin/tar')) {
		log_error(gettext("Alias archive is a .tar/tgz file which cannot be decompressed because utility is missing!"));
		return false;
	}
	rename("{$temp_filename}/aliases", "{$temp_filename}/aliases.tgz");
	mwexec("/usr/bin/tar xzf {$temp_filename}/aliases.tgz -C {$temp_filename}/aliases/");
	unlink("{$temp_filename}/aliases.tgz");
	$files_to_process = return_dir_as_array("{$temp_filename}/");
	/* foreach through all extracted files and build up aliases file */
	$fd = @fopen("{$temp_filename}/aliases", "w");
	if (!$fd) {
		log_error(gettext("Could not open {$temp_filename}/aliases for writing!"));
		return false;
	}
	foreach($files_to_process as $f2p) {
		$tmpfd = @fopen($f2p, 'r');
		if (!$tmpfd) {
			log_error(gettext("The following file could not be read {$f2p} from {$temp_filename}"));
			continue;
		}
		while (($tmpbuf = fread($tmpfd, 65536)) !== FALSE)
			fwrite($fd, $tmpbuf);
		fclose($tmpfd);
		unlink($f2p);
	}
	fclose($fd);
	unset($tmpbuf);

	return true;
}

function version_compare_dates($a, $b) {
	$a_time = strtotime($a);
	$b_time = strtotime($b);

	if ((!$a_time) || (!$b_time)) {
		return FALSE;
	} else {
		if ($a_time < $b_time)
			return -1;
		elseif ($a_time == $b_time)
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
		8 => "RELEASE",
		9 => "*"			// Matches all release levels
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
	// Only compare string parts if both versions give a specific release
	// (If either version lacks a string part, assume intended to match all release levels)
	if (isset($a) && isset($b))
		return version_compare_numeric(version_get_string_value($a), version_get_string_value($b));
	else
		return 0;
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
	global $config;

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
		|| ((time() - filemtime($urltable_filename)) > ($freq * 86400 - 90))
		|| $forceupdate) {

		// Try to fetch the URL supplied
		conf_mount_rw();
		unlink_if_exists($urltable_filename . ".tmp");
		$verify_ssl = isset($config['system']['checkaliasesurlcert']);
		if (download_file($url, $urltable_filename . ".tmp", $verify_ssl)) {
			mwexec("/usr/bin/sed -E 's/\;.*//g; /^[[:space:]]*($|#)/d' ". escapeshellarg($urltable_filename . ".tmp") . " > " . escapeshellarg($urltable_filename));
			if (alias_get_type($name) == "urltable_ports") {
				$ports = explode("\n", file_get_contents($urltable_filename));
				$ports = group_ports($ports);
				file_put_contents($urltable_filename, implode("\n", $ports));
			}
			unlink_if_exists($urltable_filename . ".tmp");
		} else
			touch($urltable_filename);
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
	set_single_sysctl("kern.geom.debugflags", "16");
	exec("gpart set -a active -i {$ASLICE} {$BOOT_DRIVE}");
	exec("/usr/sbin/boot0cfg -s {$ASLICE} -v /dev/{$BOOT_DRIVE}");
	// We can't update these if they are mounted now.
	if ($BOOTFLASH != $slice) {
		exec("/sbin/tunefs -L ${AGLABEL_SLICE} /dev/$ACOMPLETE_PATH");
		nanobsd_update_fstab($AGLABEL_SLICE, $ACOMPLETE_PATH, $AOLD_UFS_ID, $AUFS_ID);
	}
	set_single_sysctl("kern.geom.debugflags", "0");
	conf_mount_ro();
}
function nanobsd_clone_slice() {
	global $SLICE, $OLDSLICE, $TOFLASH, $COMPLETE_PATH, $COMPLETE_BOOT_PATH;
	global $GLABEL_SLICE, $UFS_ID, $OLD_UFS_ID, $BOOTFLASH;
	global $BOOT_DEVICE, $REAL_BOOT_DEVICE, $BOOT_DRIVE, $ACTIVE_SLICE;
	nanobsd_detect_slice_info();

	for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
	ob_implicit_flush(1);
	set_single_sysctl("kern.geom.debugflags", "16");
	exec("/bin/dd if=/dev/zero of=/dev/{$TOFLASH} bs=1m count=1");
	exec("/bin/dd if=/dev/{$BOOTFLASH} of=/dev/{$TOFLASH} bs=64k");
	exec("/sbin/tunefs -L {$GLABEL_SLICE} /dev/{$COMPLETE_PATH}");
	$status = nanobsd_update_fstab($GLABEL_SLICE, $COMPLETE_PATH, $OLD_UFS_ID, $UFS_ID);
	set_single_sysctl("kern.geom.debugflags", "0");
	if($status) {
		return false;
	} else {
		return true;
	}
}
function nanobsd_update_fstab($gslice, $complete_path, $oldufs, $newufs) {
	$tmppath = "/tmp/{$gslice}";
	$fstabpath = "/tmp/{$gslice}/etc/fstab";

	mkdir($tmppath);
	exec("/sbin/fsck_ufs -y /dev/{$complete_path}");
	exec("/sbin/mount /dev/ufs/{$gslice} {$tmppath}");
	copy("/etc/fstab", $fstabpath);

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
	rmdir($tmppath);

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
			$country_list[] = array("code" => $country['ISO_3166-1_Alpha-2_Code_element'],
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

function generate_ipv6_from_mac($mac) {
	$elements = explode(":", $mac);
	if(count($elements) <> 6)
		return false;

	$i = 0;
	$ipv6 = "fe80::";
	foreach($elements as $byte) {
		if($i == 0) {
			$hexadecimal =  substr($byte, 1, 2);
			$bitmap = base_convert($hexadecimal, 16, 2);
			$bitmap = str_pad($bitmap, 4, "0", STR_PAD_LEFT);
			$bitmap = substr($bitmap, 0, 2) ."1". substr($bitmap, 3,4);
			$byte = substr($byte, 0, 1) . base_convert($bitmap, 2, 16);
		}
		$ipv6 .= $byte;
		if($i == 1) {
			$ipv6 .= ":";
		}
		if($i == 3) {
			$ipv6 .= ":";
		}
		if($i == 2) {
			$ipv6 .= "ff:fe";
		}

		$i++;
	}
	return $ipv6;
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
 *   If ignore_if is a VIP (not carp), vip array index is passed after string _virtualip
 * RESULT
 *   returns true if the IP Address is
 *   configured and present on this device.
*/
function is_ipaddr_configured($ipaddr, $ignore_if = "", $check_localip = false, $check_subnets = false) {
	global $config;

	$pos = strpos($ignore_if, '_virtualip');
	if ($pos !== false) {
		$ignore_vip_id = substr($ignore_if, $pos+10);
		$ignore_vip_if = substr($ignore_if, 0, $pos);
	} else {
		$ignore_vip_id = -1;
		$ignore_vip_if = $ignore_if;
	}

	$isipv6 = is_ipaddrv6($ipaddr);

	if ($check_subnets) {
		$iflist = get_configured_interface_list();
		foreach ($iflist as $if => $ifname) {
			if ($ignore_if == $if)
				continue;

			if ($isipv6 === true) {
				$bitmask = get_interface_subnetv6($if);
				$subnet = gen_subnetv6(get_interface_ipv6($if), $bitmask);
			} else {
				$bitmask = get_interface_subnet($if);
				$subnet = gen_subnet(get_interface_ip($if), $bitmask);
			}

			if (ip_in_subnet($ipaddr, $subnet . '/' . $bitmask))
				return true;
		}
	} else {
		if ($isipv6 === true)
			$interface_list_ips = get_configured_ipv6_addresses();
		else
			$interface_list_ips = get_configured_ip_addresses();

		foreach($interface_list_ips as $if => $ilips) {
			if ($ignore_if == $if)
				continue;
			if (strcasecmp($ipaddr, $ilips) == 0)
				return true;
		}
	}

	$interface_list_vips = get_configured_vips_list(true);
	foreach ($interface_list_vips as $id => $vip) {
		/* Skip CARP interfaces here since they were already checked above */
		if ($id == $ignore_vip_id || (strstr($ignore_if, '_vip') && $ignore_vip_if == $vip['if']))
			continue;
		if (strcasecmp($ipaddr, $vip['ipaddr']) == 0)
			return true;
	}

	if ($check_localip) {
		if (is_array($config['pptpd']) && !empty($config['pptpd']['localip']) && (strcasecmp($ipaddr, $config['pptpd']['localip']) == 0))
			return true;

		if (!is_array($config['l2tp']) && !empty($config['l2tp']['localip']) && (strcasecmp($ipaddr, $config['l2tp']['localip']) == 0))
			return true;
	}

	return false;
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

function set_language($lang = 'en_US', $encoding = "UTF-8") {
	putenv("LANG={$lang}.{$encoding}");
	setlocale(LC_ALL, "{$lang}.{$encoding}");
	textdomain("pfSense");
	bindtextdomain("pfSense","/usr/local/share/locale");
	bind_textdomain_codeset("pfSense","{$lang}.{$encoding}");
}

function get_locale_list() {
	$locales = array(
		"en_US" => gettext("English"),
		"pt_BR" => gettext("Portuguese (Brazil)"),
		"tr" => gettext("Turkish"),
	);
	asort($locales);
	return $locales;
}

function system_get_language_code() {
	global $config, $g_languages;

	// a language code, as per [RFC3066]
	$language = $config['system']['language'];
	//$code = $g_languages[$language]['code'];
	$code = str_replace("_", "-", $language);

	if (empty($code))
		$code = "en-US"; // Set default code.

	return $code;
}

function system_get_language_codeset() {
	global $config, $g_languages;

	$language = $config['system']['language'];
	$codeset = $g_languages[$language]['codeset'];

	if (empty($codeset))
		$codeset = "UTF-8"; // Set default codeset.

	return $codeset;
}

/* Available languages/locales */
$g_languages = array (
	"sq"    => array("codeset" => "UTF-8", "desc" => gettext("Albanian")),
	"bg"    => array("codeset" => "UTF-8", "desc" => gettext("Bulgarian")),
	"zh_CN" => array("codeset" => "UTF-8", "desc" => gettext("Chinese (Simplified)")),
	"zh_TW" => array("codeset" => "UTF-8", "desc" => gettext("Chinese (Traditional)")),
	"nl"    => array("codeset" => "UTF-8", "desc" => gettext("Dutch")),
	"da"    => array("codeset" => "UTF-8", "desc" => gettext("Danish")),
	"en_US" => array("codeset" => "UTF-8", "desc" => gettext("English")),
	"fi"    => array("codeset" => "UTF-8", "desc" => gettext("Finnish")),
	"fr"    => array("codeset" => "UTF-8", "desc" => gettext("French")),
	"de"    => array("codeset" => "UTF-8", "desc" => gettext("German")),
	"el"    => array("codeset" => "UTF-8", "desc" => gettext("Greek")),
	"hu"    => array("codeset" => "UTF-8", "desc" => gettext("Hungarian")),
	"it"    => array("codeset" => "UTF-8", "desc" => gettext("Italian")),
	"ja"    => array("codeset" => "UTF-8", "desc" => gettext("Japanese")),
	"ko"    => array("codeset" => "UTF-8", "desc" => gettext("Korean")),
	"lv"    => array("codeset" => "UTF-8", "desc" => gettext("Latvian")),
	"nb"    => array("codeset" => "UTF-8", "desc" => gettext("Norwegian (Bokmal)")),
	"pl"    => array("codeset" => "UTF-8", "desc" => gettext("Polish")),
	"pt_BR" => array("codeset" => "ISO-8859-1", "desc" => gettext("Portuguese (Brazil)")),
	"pt"    => array("codeset" => "UTF-8", "desc" => gettext("Portuguese (Portugal)")),
	"ro"    => array("codeset" => "UTF-8", "desc" => gettext("Romanian")),
	"ru"    => array("codeset" => "UTF-8", "desc" => gettext("Russian")),
	"sl"    => array("codeset" => "UTF-8", "desc" => gettext("Slovenian")),
	"tr"    => array("codeset" => "UTF-8", "desc" => gettext("Turkish")),
	"es"    => array("codeset" => "UTF-8", "desc" => gettext("Spanish")),
	"sv"    => array("codeset" => "UTF-8", "desc" => gettext("Swedish")),
	"sk"    => array("codeset" => "UTF-8", "desc" => gettext("Slovak")),
	"cs"    => array("codeset" => "UTF-8", "desc" => gettext("Czech"))
);

function return_hex_ipv4($ipv4) {
	if(!is_ipaddrv4($ipv4))
		return(false);

	/* we need the hex form of the interface IPv4 address */
	$ip4arr = explode(".", $ipv4);
	return (sprintf("%02x%02x%02x%02x", $ip4arr[0], $ip4arr[1], $ip4arr[2], $ip4arr[3]));
}

function convert_ipv6_to_128bit($ipv6) {
	if(!is_ipaddrv6($ipv6))
		return(false);

	$ip6arr = array();
	$ip6prefix = Net_IPv6::uncompress($ipv6);
	$ip6arr = explode(":", $ip6prefix);
	/* binary presentation of the prefix for all 128 bits. */
	$ip6prefixbin = "";
	foreach($ip6arr as $element) {
		$ip6prefixbin .= sprintf("%016b", hexdec($element));
	}
	return($ip6prefixbin);
}

function convert_128bit_to_ipv6($ip6bin) {
	if(strlen($ip6bin) <> 128)
		return(false);

	$ip6arr = array();
	$ip6binarr = array();
	$ip6binarr = str_split($ip6bin, 16);
	foreach($ip6binarr as $binpart)
		$ip6arr[] = dechex(bindec($binpart));
	$ip6addr = Net_IPv6::compress(implode(":", $ip6arr));

	return($ip6addr);
}


/* Returns the calculated bit length of the prefix delegation from the WAN interface */
/* DHCP-PD is variable, calculate from the prefix-len on the WAN interface */
/* 6rd is variable, calculate from 64 - (v6 prefixlen - (32 - v4 prefixlen)) */
/* 6to4 is 16 bits, e.g. 65535 */
function calculate_ipv6_delegation_length($if) {
	global $config;

	if(!is_array($config['interfaces'][$if]))
		return false;

	switch($config['interfaces'][$if]['ipaddrv6']) {
		case "6to4":
			$pdlen = 16;
			break;
		case "6rd":
			$rd6cfg = $config['interfaces'][$if];
			$rd6plen = explode("/", $rd6cfg['prefix-6rd']);
			$pdlen = (64 - ($rd6plen[1] + (32 - $rd6cfg['prefix-6rd-v4plen'])));
			break;
		case "dhcp6":
			$dhcp6cfg = $config['interfaces'][$if];
			$pdlen = $dhcp6cfg['dhcp6-ia-pd-len'];
			break;
		default:
			$pdlen = 0;
			break;
	}
	return($pdlen);
}

function huawei_rssi_to_string($rssi) {
	$dbm = array();
	$i = 0;
	$dbstart = -113;
	while($i < 32) {
		$dbm[$i] = $dbstart + ($i * 2);
		$i++;
	}
	$percent = round(($rssi / 31) * 100);
	$string = "rssi:{$rssi} level:{$dbm[$rssi]}dBm percent:{$percent}%";
	return $string;
}

function huawei_mode_to_string($mode, $submode) {
	$modes[0] = "None";
	$modes[1] = "AMPS";
	$modes[2] = "CDMA";
	$modes[3] = "GSM/GPRS";
	$modes[4] = "HDR";
	$modes[5] = "WCDMA";
	$modes[6] = "GPS";

	$submodes[0] = "No Service";
	$submodes[1] = "GSM";
	$submodes[2] = "GPRS";
	$submodes[3] = "EDGE";
	$submodes[4] = "WCDMA";
	$submodes[5] = "HSDPA";
	$submodes[6] = "HSUPA";
	$submodes[7] = "HSDPA+HSUPA";
	$submodes[8] = "TD-SCDMA";
	$submodes[9] = "HSPA+";
	$string = "{$modes[$mode]}, {$submodes[$submode]} Mode";
	return $string;
}

function huawei_service_to_string($state) {
	$modes[0] = "No";
	$modes[1] = "Restricted";
	$modes[2] = "Valid";
	$modes[3] = "Restricted Regional";
	$modes[4] = "Powersaving";
	$string = "{$modes[$state]} Service";
	return $string;
}

function huawei_simstate_to_string($state) {
	$modes[0] = "Invalid SIM/locked";
	$modes[1] = "Valid SIM";
	$modes[2] = "Invalid SIM CS";
	$modes[3] = "Invalid SIM PS";
	$modes[4] = "Invalid SIM CS/PS";
	$modes[255] = "Missing SIM";
	$string = "{$modes[$state]} State";
	return $string;
}

function zte_rssi_to_string($rssi) {
	return huawei_rssi_to_string($rssi);
}

function zte_mode_to_string($mode, $submode) {
	$modes[0] = "No Service";
	$modes[1] = "Limited Service";
	$modes[2] = "GPRS";
	$modes[3] = "GSM";
	$modes[4] = "UMTS";
	$modes[5] = "EDGE";
	$modes[6] = "HSDPA";

	$submodes[0] = "CS_ONLY";
	$submodes[1] = "PS_ONLY";
	$submodes[2] = "CS_PS";
	$submodes[3] = "CAMPED";
	$string = "{$modes[$mode]}, {$submodes[$submode]} Mode";
	return $string;
}

function zte_service_to_string($state) {
	$modes[0] = "Initializing";
	$modes[1] = "Network Lock error";
	$modes[2] = "Network Locked";
	$modes[3] = "Unlocked or correct MCC/MNC";
	$string = "{$modes[$state]} Service";
	return $string;
}

function zte_simstate_to_string($state) {
	$modes[0] = "No action";
	$modes[1] = "Network lock";
	$modes[2] = "(U)SIM card lock";
	$modes[3] = "Network Lock and (U)SIM card Lock";
	$string = "{$modes[$state]} State";
	return $string;
}

function get_configured_pppoe_server_interfaces() {
	global $config;
	$iflist = array();
	if (is_array($config['pppoes']['pppoe'])) {
		foreach($config['pppoes']['pppoe'] as $pppoe) {
			if ($pppoe['mode'] == "server") {
				$int = "poes". $pppoe['pppoeid'];
				$iflist[$int] = strtoupper($int);
			}
		}
	}
	return $iflist;
}

function get_pppoes_child_interfaces($ifpattern) {
	$if_arr = array();
	if($ifpattern == "")
		return;

	exec("ifconfig", $out, $ret);
	foreach($out as $line) {
		if(preg_match("/^({$ifpattern}[0-9]+):/i", $line, $match)) {
			$if_arr[] = $match[1];
		}
	}
	return $if_arr;

}

/****f* pfsense-utils/pkg_call_plugins
 * NAME
 *   pkg_call_plugins
 * INPUTS
 *   $plugin_type value used to search in package configuration if the plugin is used, also used to create the function name
 *   $plugin_params parameters to pass to the plugin function for passing multiple parameters a array can be used.
 * RESULT
 *   returns associative array results from the plugin calls for each package
 * NOTES
 *   This generic function can be used to notify or retrieve results from functions that are defined in packages.
 ******/
function pkg_call_plugins($plugin_type, $plugin_params) {
	global $g, $config;
	$results = array();
	if (!is_array($config['installedpackages']['package']))
		return $results;
	foreach ($config['installedpackages']['package'] as $package) {
		if(!file_exists("/usr/local/pkg/" . $package['configurationfile']))
			continue;
		$pkg_config = parse_xml_config_pkg("/usr/local/pkg/" . $package['configurationfile'], 'packagegui');
		$pkgname = substr(reverse_strrchr($package['configurationfile'], "."),0,-1);
		if (is_array($pkg_config['plugins']['item']))
			foreach ($pkg_config['plugins']['item'] as $plugin) {
				if ($plugin['type'] == $plugin_type) {
					if (file_exists($pkg_config['include_file']))
						require_once($pkg_config['include_file']);
					else
						continue;
					$plugin_function = $pkgname . '_'. $plugin_type;
					$results[$pkgname] = @eval($plugin_function($plugin_params));
				}
			}
	}
	return $results;
}

/* Function to find and return the active XML RPC base URL to avoid code duplication */
function get_active_xml_rpc_base_url() {
	global $config, $g;
	/* If the user has activated the option to enable an alternate xmlrpcbaseurl, and it's not empty, then use it */
	if (isset($config['system']['altpkgrepo']['enable']) && !empty($config['system']['altpkgrepo']['xmlrpcbaseurl'])) {
		return $config['system']['altpkgrepo']['xmlrpcbaseurl'];
	} else {
		return $g['xmlrpcbaseurl'];
	}
}

?>
