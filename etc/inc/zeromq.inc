<?php 
/*
	zeromq.inc
	part of the pfSense project (https://www.pfsense.org)
	Copyright 2010 Scott Ullrich <sullrich@gmail.com>
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

define('ZEROMQ_AUTH_FAIL', 'authfail');
define('ZEROMQ_TRUE', 'true');
define('ZEROMQ_FASLE', 'false');

$do_not_include_config_gui_inc = true;
require("auth.inc");

//$debug = true; 

/* zeromq_send: Send a message to a member node */
function zeromq_send($protocol = "tcp", $ipaddress = "127.0.0.1", $port = "8888", 
					 $method, $params, $username, $password) {

	global $debug;

	/* Set calling function and auth information */
	$xmlparams = array(
		$username,
		$password,
		$method,
		$params
	);
	
	/* Create new queue object */
	$queue = new ZMQSocket(new ZMQContext(), ZMQ::SOCKET_REQ, "MySock1");
	$queue->connect("{$protocol}://{$ipaddress}:{$port}");

	/* Assign socket 1 to the queue, send and receive */
	$result = $queue->send(serialize($xmlparams))->recv();

	/* xmlrpc_params_to_php() the result and return */
	$unserializedresult = unserialize($result);
	
	/* Return the result to the caller */
	return $unserializedresult;
}

function zeromq_server($protocol = "tcp", $ipaddress = "127.0.0.1", $port = "8888") {
	global $debug;
	if(!$ipaddress || !$port) {
		if($debug) 
			echo "ERROR: You must pass, proto, ipaddress and port\n";
		return;
	}
	if($debug)
		echo "Creating ZMQSocket()\n";
	$server = new ZMQSocket(new ZMQContext(), ZMQ::SOCKET_REP);
	if($debug)
		echo "Binding to {$protocol}://{$ipaddress}:{$port}\n";
	$server->bind("{$protocol}://{$ipaddress}:{$port}");
	if($debug) 
		echo "Entering while() loop\n";
	while ($msg = $server->recv()) {
		// Convert the XML to a PHP array
		$message = unserialize($msg);
		if($debug) {
			echo "Message received:\n";
			print_r($message);
		}
		switch ($message[2]) {
			case "pfsense.exec_shell":
				$function_to_call = "exec_shell_zeromq";
				break;
			case "pfsense.exec_php":
				$function_to_call = "exec_php_zeromq";
				break;
			case "pfsense.filter_configure":
				$function_to_call = "filter_configure_zeromq";
				break;
			case "pfsense.interfaces_carp_configure":
				$function_to_call = "interfaces_carp_configure_zeromq";
				break;
			case "pfsense.backup_config_section":
				$function_to_call = "backup_config_section_zeromq";
				break;
			case "pfsense.restore_config_section":
				$function_to_call = "restore_config_section_zeromq";
				break;
			case "pfsense.merge_config_section":
				$function_to_call = "merge_config_section_zeromq";
				break;
			case "pfsense.merge_installedpackages_section_zeromq":
				$function_to_call = "merge_installedpackages_section_zeromq";
				break;
			case "pfsense.check_firmware_version":
				$function_to_call = "check_firmware_version_zeromq";
				break;
			case "pfsense.reboot":
				$function_to_call = "reboot_zeromq";
				break;
			case "pfsense.get_notices":
				$function_to_call = "get_notices_zeromq";
				break;
		}
		if(!$function_to_call) {
			if($debug)
				echo "ERROR:  Could not find a function to call";
			return;
		} else {
			if($debug) 
				echo "Invoking function {$message[2]}()\n;";
		}
		/* Call function that is being invoked */
		$result = $function_to_call($message);
		/* echo back the result */
		$server->send($result);  
	}
}

function zeromq_auth($params) {
	global $config, $g, $debug;	

	$username = $params[0];
	$passwd = $params[1];
	
	$user = getUserEntry($username);
	if (!$user) {
		if($debug) 
			echo "Could not locate user $username with getUserEntry()\n";
		return false;
	}

	if (is_account_disabled($username) || is_account_expired($username)) {
		if($debug) 
			echo "Returning account expired/disabled\n";
		return false;
	}
		
	if ($user['password']) {
		$passwd = crypt($passwd, $user['password']);
		if ($passwd == $user['password'])
			return true;
	}

	if ($user['md5-hash']) {
		$passwd = md5($passwd);
		if ($passwd == $user['md5-hash'])
			return true;
	}

	if($debug) 
		echo "zeromq_auth() fall through == false\n";

	return false;
}

function exec_php_zeromq($raw_params) {
	global $config, $g, $debug;
	$params = $raw_params;
	if(zeromq_auth($raw_params) == false) {
		if($debug)
			echo "Auth failed in exec_shell_zeromq()\n";
		return ZEROMQ_AUTH_FAIL;
	}
	$exec_php = $params[3];
	if($debug) 
		echo "Running exec_php_zeromq(): {$exec_php}\n";
	eval($exec_php);
	if($toreturn) {
		return serialize($toreturn);
	} else
		return ZEROMQ_FASLE;
}

function exec_shell_zeromq($raw_params) {
	global $config, $g, $debug;
	$params = $raw_params;
	if(zeromq_auth($raw_params) == false) {
		if($debug)
			echo "Auth failed in exec_shell_zeromq()\n";
		return ZEROMQ_AUTH_FAIL;
	}
	$shell_cmd = $params[3];
	if($debug) 
		echo "Running exec_shell_zeromq(): {$shell_cmd}\n";
	mwexec($shell_cmd);
	return ZEROMQ_FASLE;
}

function backup_config_section_zeromq($raw_params) {
	global $config, $g, $debug;
	$params = $raw_params;
	if(zeromq_auth($raw_params) == false)
		return ZEROMQ_AUTH_FAIL;
	$val = array_intersect_key($config, array_flip($params[3]));
	return serialize($val);
}

function restore_config_section_zeromq($raw_params) {
	global $config, $g, $debug;
	$params = $raw_params;
	if(zeromq_auth($raw_params) == false) 
		return ZEROMQ_AUTH_FAIL;
	$config = array_merge($config, $params[3]);
	$mergedkeys = implode(",", array_keys($params[3]));
	write_config(sprintf(gettext("Merged in config (%s sections) from ZeroMQ client."),$mergedkeys));
	return ZEROMQ_FASLE;
}

function merge_installedpackages_section_zeromq($raw_params) {
	global $config, $g, $debug;
	$params = $raw_params;
	if(zeromq_auth($raw_params) == false) 
		return ZEROMQ_AUTH_FAIL;
	$config['installedpackages'] = array_merge($config['installedpackages'], $params[0]);
	$mergedkeys = implode(",", array_keys($params[3]));
	write_config(sprintf(gettext("Merged in config (%s sections) from ZeroMQ client."),$mergedkeys));
	return ZEROMQ_FASLE;
}

function merge_config_section_zeromq($raw_params) {
	global $config, $g, $debug;
	$params = $raw_params;
	if(zeromq_auth($raw_params) == false)
	 	return ZEROMQ_AUTH_FAIL;
	$config = array_merge_recursive_unique($config, $params[0]);
	$mergedkeys = implode(",", array_keys($params[3]));
	write_config("Merged in config ({$mergedkeys} sections) from ZeroMQ client.");
	return ZEROMQ_FASLE;
}

function filter_configure_zeromq($raw_params) {
	global $config, $g, $debug;
	$params = $raw_params;
	if(zeromq_auth($raw_params) == false)
		return ZEROMQ_AUTH_FAIL;
	filter_configure();
	system_routing_configure();
	setup_gateways_monitor();
	relayd_configure();
	require_once("openvpn.inc");
	openvpn_resync_all();
	services_dhcpd_configure();
	services_dnsmasq_configure();
	local_sync_accounts();
	return ZEROMQ_FASLE;
}

function interfaces_carp_configure_zeromq($raw_params) {
	global $config, $g, $debug;
	$params = $raw_params;
	if(zeromq_auth($raw_params) == false) 
		return ZEROMQ_AUTH_FAIL;
	interfaces_carp_setup();
	interfaces_vips_configure();
	return ZEROMQ_FASLE;
}

function check_firmware_version_zeromq($raw_params) {
	global $config, $g, $debug;
	$params = $raw_params;
	if(zeromq_auth($raw_params) == false) 
		return ZEROMQ_AUTH_FAIL;
	return serialize(check_firmware_version(false));
}

function reboot_zeromq($raw_params) {
	global $config, $g, $debug;
	$params = $raw_params;
	if(zeromq_auth($raw_params) == false)
		return ZEROMQ_AUTH_FAIL;
	mwexec_bg("/etc/rc.reboot");
	return ZEROMQ_FASLE;
}

function get_notices_zeromq($raw_params) {
	global $config, $g, $debug;
	$params = $raw_params;
	if(zeromq_auth($raw_params) == false)
		return ZEROMQ_AUTH_FAIL;
	require("notices.inc");
	if(!$params) {
		$toreturn = get_notices();
	} else {
		$toreturn = get_notices($params);
	}
	return serialize($toreturn);
}

?>
