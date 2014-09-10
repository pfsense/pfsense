<?php
/*
	$Id$

        xmlrpc.php
        Copyright (C) 2009, 2010 Scott Ullrich
        Copyright (C) 2005 Colin Smith
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

##|+PRIV
##|*IDENT=page-xmlrpclibrary
##|*NAME=XMLRPC Library page
##|*DESCR=Allow access to the 'XMLRPC Library' page.
##|*MATCH=xmlrpc.php*
##|-PRIV

require("config.inc");
require("functions.inc");
require_once("filter.inc");
require("ipsec.inc");
require("vpn.inc");
require("shaper.inc");
require("xmlrpc_server.inc");
require("xmlrpc.inc");
require("array_intersect_key.inc");

function xmlrpc_loop_detect() {
	global $config;

	/* grab sync to ip if enabled */
	if ($config['hasync'])
		$synchronizetoip = $config['hasync']['synchronizetoip'];
	if($synchronizetoip) {
		if($synchronizetoip == $_SERVER['REMOTE_ADDR'])
			return true;	
	}

	return false;
}

$xmlrpc_g = array(
	"return" => array(
		"true" => new XML_RPC_Response(new XML_RPC_Value(true, $XML_RPC_Boolean)),
		"false" => new XML_RPC_Response(new XML_RPC_Value(false, $XML_RPC_Boolean)),
		"authfail" => new XML_RPC_Response(new XML_RPC_Value(gettext("Authentication failed"), $XML_RPC_String))
	)
);

/*
 *   pfSense XMLRPC errors
 *   $XML_RPC_erruser + 1 = Auth failure
 */
$XML_RPC_erruser = 200;

/* EXPOSED FUNCTIONS */
$exec_php_doc = gettext("XMLRPC wrapper for eval(). This method must be called with two parameters: a string containing the local system\'s password followed by the PHP code to evaluate.");
$exec_php_sig = array(
	array(
		$XML_RPC_Boolean, // First signature element is return value.
		$XML_RPC_String, // password
		$XML_RPC_String, // shell code to exec
	)
);

function xmlrpc_authfail() {
	log_auth("webConfigurator authentication error for 'admin' from {$_SERVER['REMOTE_ADDR']}");
}

function exec_php_xmlrpc($raw_params) {
	global $config, $xmlrpc_g;

	$params = xmlrpc_params_to_php($raw_params);
	if(!xmlrpc_auth($params)) {
		xmlrpc_authfail();
		return $xmlrpc_g['return']['authfail'];
	}
	$exec_php = $params[0];
	eval($exec_php);
	if($toreturn) {
		$response = XML_RPC_encode($toreturn);
		return new XML_RPC_Response($response);
	} else
		return $xmlrpc_g['return']['true'];
}

/*****************************/
$exec_shell_doc = gettext("XMLRPC wrapper for mwexec(). This method must be called with two parameters: a string containing the local system\'s password followed by an shell command to execute.");
$exec_shell_sig = array(
	array(
		$XML_RPC_Boolean, // First signature element is return value.
		$XML_RPC_String, // password
		$XML_RPC_String, // shell code to exec
	)
);

function exec_shell_xmlrpc($raw_params) {
	global $config, $xmlrpc_g;

	$params = xmlrpc_params_to_php($raw_params);
	if(!xmlrpc_auth($params)) {
		xmlrpc_authfail();
		return $xmlrpc_g['return']['authfail'];
	}
	$shell_cmd = $params[0];
	mwexec($shell_cmd);

	return $xmlrpc_g['return']['true'];
}

/*****************************/
$backup_config_section_doc = gettext("XMLRPC wrapper for backup_config_section. This method must be called with two parameters: a string containing the local system\'s password followed by an array containing the keys to be backed up.");
$backup_config_section_sig = array(
	array(
		$XML_RPC_Struct, // First signature element is return value.
		$XML_RPC_String,
		$XML_RPC_Array
	)
);

function backup_config_section_xmlrpc($raw_params) {
	global $config, $xmlrpc_g;

	if (xmlrpc_loop_detect()) {
		log_error("Disallowing CARP sync loop");
		return;
	}

	$params = xmlrpc_params_to_php($raw_params);
	if(!xmlrpc_auth($params)) {
		xmlrpc_authfail();
		return $xmlrpc_g['return']['authfail'];
	}
	$val = array_intersect_key($config, array_flip($params[0]));

	return new XML_RPC_Response(XML_RPC_encode($val));
}

/*****************************/
$restore_config_section_doc = gettext("XMLRPC wrapper for restore_config_section. This method must be called with two parameters: a string containing the local system\'s password and an array to merge into the system\'s config. This function returns true upon completion.");
$restore_config_section_sig = array(
	array(
		$XML_RPC_Boolean,
		$XML_RPC_String,
		$XML_RPC_Struct
	)
);

function restore_config_section_xmlrpc($raw_params) {
	global $config, $xmlrpc_g;

	$old_config = $config;

	if (xmlrpc_loop_detect()) {
		log_error("Disallowing CARP sync loop");
		return;
	}

	$params = xmlrpc_params_to_php($raw_params);
	if(!xmlrpc_auth($params)) {
		xmlrpc_authfail();
		return $xmlrpc_g['return']['authfail'];
	}

	// Some sections should just be copied and not merged or we end
	//   up unable to sync the deletion of the last item in a section
	$sync_full = array('ipsec', 'aliases', 'wol', 'load_balancer', 'openvpn', 'cert', 'ca', 'crl', 'schedules', 'filter', 'nat', 'dhcpd', 'dhcpv6');
	$sync_full_done = array();
	foreach ($sync_full as $syncfull) {
		if (isset($params[0][$syncfull])) {
			$config[$syncfull] = $params[0][$syncfull];
			unset($params[0][$syncfull]);
			$sync_full_done[] = $syncfull;
		}
	}

	$vipbackup = array();
	$oldvips = array();
	if (isset($params[0]['virtualip'])) {
		if (is_array($config['virtualip']['vip'])) {
			foreach ($config['virtualip']['vip'] as $vipindex => $vip) {
				if ($vip['mode'] == "carp")
					$oldvips["{$vip['interface']}_vip{$vip['vhid']}"] = "{$vip['password']}{$vip['advskew']}{$vip['subnet']}{$vip['subnet_bits']}{$vip['advbase']}";
				else if ($vip['mode'] == "ipalias" && (strstr($vip['interface'], "_vip") || strstr($vip['interface'], "lo0")))
					$oldvips[$vip['subnet']] = "{$vip['interface']}{$vip['subnet']}{$vip['subnet_bits']}";
				else if (($vip['mode'] == "ipalias" || $vip['mode'] == 'proxyarp') && !(strstr($vip['interface'], "_vip") || strstr($vip['interface'], "lo0")))
					$vipbackup[] = $vip;
			}
		}
	}

        // For vip section, first keep items sent from the master
	$config = array_merge_recursive_unique($config, $params[0]);

        /* Then add ipalias and proxyarp types already defined on the backup */
	if (is_array($vipbackup) && !empty($vipbackup)) {
		if (!is_array($config['virtualip']))
			$config['virtualip'] = array();
		if (!is_array($config['virtualip']['vip']))
			$config['virtualip']['vip'] = array();
		foreach ($vipbackup as $vip)
			array_unshift($config['virtualip']['vip'], $vip);
	}

	/* Log what happened */
	$mergedkeys = implode(",", array_merge(array_keys($params[0]), $sync_full_done));
	write_config(sprintf(gettext("Merged in config (%s sections) from XMLRPC client."),$mergedkeys));

	/* 
	 * The real work on handling the vips specially
	 * This is a copy of intefaces_vips_configure with addition of not reloading existing/not changed carps
	 */
	if (isset($params[0]['virtualip']) && is_array($config['virtualip']) && is_array($config['virtualip']['vip'])) {
		$carp_setuped = false;
		$anyproxyarp = false;
		foreach ($config['virtualip']['vip'] as $vip) {
			if ($vip['mode'] == "carp" && isset($oldvips["{$vip['interface']}_vip{$vip['vhid']}"])) {
				if ($oldvips["{$vip['interface']}_vip{$vip['vhid']}"] == "{$vip['password']}{$vip['advskew']}{$vip['subnet']}{$vip['subnet_bits']}{$vip['advbase']}") {
					if (does_vip_exist($vip)) {
						unset($oldvips["{$vip['interface']}_vip{$vip['vhid']}"]);
						continue; // Skip reconfiguring this vips since nothing has changed.
					}
				}
				unset($oldvips["{$vip['interface']}_vip{$vip['vhid']}"]);
			} else if ($vip['mode'] == "ipalias" && strstr($vip['interface'], "_vip") && isset($oldvips[$vip['subnet']])) {
				if ($oldvips[$vip['subnet']] == "{$vip['interface']}{$vip['subnet']}{$vip['subnet_bits']}") {
					if (does_vip_exist($vip)) {
						unset($oldvips[$vip['subnet']]);
						continue; // Skip reconfiguring this vips since nothing has changed.
					}
				}
				unset($oldvips[$vip['subnet']]);
			}

			switch ($vip['mode']) {
			case "proxyarp":
				$anyproxyarp = true;
				break;
			case "ipalias":
				interface_ipalias_configure($vip);
				break;
			case "carp":
				if ($carp_setuped == false)
                                        $carp_setuped = true;
				interface_carp_configure($vip);
				break;
			}
		}
		/* Cleanup remaining old carps */
		foreach ($oldvips as $oldvipif => $oldvippar) {
			$oldvipif = get_real_interface($oldvippar['interface']);
			if (!empty($oldvipif))
				pfSense_interface_deladdress($oldvipif, $oldvipar['subnet']);
		}
		if ($carp_setuped == true)
			interfaces_carp_setup();
		if ($anyproxyarp == true)
			interface_proxyarp_configure();
	}

	if (isset($old_config['ipsec']['enable']) !== isset($config['ipsec']['enable']))
		vpn_ipsec_configure();

	unset($old_config);

	return $xmlrpc_g['return']['true'];
}

/*****************************/
$merge_config_section_doc = gettext("XMLRPC wrapper for merging package sections. This method must be called with two parameters: a string containing the local system\'s password and an array to merge into the system\'s config. This function returns true upon completion.");
$merge_config_section_sig = array(
	array(
		$XML_RPC_Boolean,
		$XML_RPC_String,
		$XML_RPC_Struct
	)
);

function merge_installedpackages_section_xmlrpc($raw_params) {
	global $config, $xmlrpc_g;

	if (xmlrpc_loop_detect()) {
		log_error("Disallowing CARP sync loop");
		return;
	}

	$params = xmlrpc_params_to_php($raw_params);
	if(!xmlrpc_auth($params)) {
		xmlrpc_authfail();
		return $xmlrpc_g['return']['authfail'];
	}
	$config['installedpackages'] = array_merge($config['installedpackages'], $params[0]);
	$mergedkeys = implode(",", array_keys($params[0]));
	write_config(sprintf(gettext("Merged in config (%s sections) from XMLRPC client."),$mergedkeys));

	return $xmlrpc_g['return']['true'];
}

/*****************************/
$merge_config_section_doc = gettext("XMLRPC wrapper for merge_config_section. This method must be called with two parameters: a string containing the local system\'s password and an array to merge into the system\'s config. This function returns true upon completion.");
$merge_config_section_sig = array(
	array(
		$XML_RPC_Boolean,
		$XML_RPC_String,
		$XML_RPC_Struct
	)
);

function merge_config_section_xmlrpc($raw_params) {
	global $config, $xmlrpc_g;

	if (xmlrpc_loop_detect()) {
		log_error("Disallowing CARP sync loop");
		return;
	}

	$params = xmlrpc_params_to_php($raw_params);
	if(!xmlrpc_auth($params)) {
		xmlrpc_authfail();
		return $xmlrpc_g['return']['authfail'];
	}
	$config_new = array_overlay($config, $params[0]);
	$config = $config_new;
	$mergedkeys = implode(",", array_keys($params[0]));
	write_config(sprintf(gettext("Merged in config (%s sections) from XMLRPC client."), $mergedkeys));
	return $xmlrpc_g['return']['true'];
}

/*****************************/
$filter_configure_doc = gettext("Basic XMLRPC wrapper for filter_configure. This method must be called with one paramater: a string containing the local system\'s password. This function returns true upon completion.");
$filter_configure_sig = array(
	array(
		$XML_RPC_Boolean,
		$XML_RPC_String
	)
);

function filter_configure_xmlrpc($raw_params) {
	global $xmlrpc_g, $config;

	$params = xmlrpc_params_to_php($raw_params);
	if(!xmlrpc_auth($params)) {
		xmlrpc_authfail();
		return $xmlrpc_g['return']['authfail'];
	}
	filter_configure();
	system_routing_configure();
	setup_gateways_monitor();
	relayd_configure();
	require_once("openvpn.inc");
	openvpn_resync_all();
	if (isset($config['dnsmasq']['enable']))
		services_dnsmasq_configure();
	elseif (isset($config['unbound']['enable']))
		services_unbound_configure();
	else
		# Both calls above run services_dhcpd_configure(), then we just
		# need to call it when them are not called to avoid restart dhcpd
		# twice, as described on ticket #3797
		services_dhcpd_configure();
	local_sync_accounts();

	return $xmlrpc_g['return']['true'];
}

/*****************************/
$carp_configure_doc = gettext("Basic XMLRPC wrapper for configuring CARP interfaces.");
$carp_configure_sig = array(
	array(
		$XML_RPC_Boolean,
		$XML_RPC_String
	)
);

function interfaces_carp_configure_xmlrpc($raw_params) {
	global $xmlrpc_g;

	if (xmlrpc_loop_detect()) {
		log_error("Disallowing CARP sync loop");
		return;
	}

	$params = xmlrpc_params_to_php($raw_params);
	if(!xmlrpc_auth($params)) {
		xmlrpc_authfail();
		return $xmlrpc_g['return']['authfail'];
	}
	interfaces_vips_configure();

	return $xmlrpc_g['return']['true'];
}

/*****************************/
$check_firmware_version_doc = gettext("Basic XMLRPC wrapper for check_firmware_version. This function will return the output of check_firmware_version upon completion.");

$check_firmware_version_sig = array(
	array(
		$XML_RPC_String,
		$XML_RPC_String
	)
);

function check_firmware_version_xmlrpc($raw_params) {
	global $xmlrpc_g, $XML_RPC_String;

	$params = xmlrpc_params_to_php($raw_params);
	if(!xmlrpc_auth($params)) {
		xmlrpc_authfail();
		return $xmlrpc_g['return']['authfail'];
	}
	return new XML_RPC_Response(new XML_RPC_Value(check_firmware_version(false), $XML_RPC_String));
}

/*****************************/
$pfsense_firmware_version_doc = gettext("Basic XMLRPC wrapper for check_firmware_version. This function will return the output of check_firmware_version upon completion.");

$pfsense_firmware_version_sig = array (
        array (
                $XML_RPC_Struct,
                $XML_RPC_String
        )
);

function pfsense_firmware_version_xmlrpc($raw_params) {
        global $xmlrpc_g;

        $params = xmlrpc_params_to_php($raw_params);
        if(!xmlrpc_auth($params)) {
			xmlrpc_authfail();
			return $xmlrpc_g['return']['authfail'];
		}
        return new XML_RPC_Response(XML_RPC_encode(host_firmware_version()));
}

/*****************************/
$reboot_doc = gettext("Basic XMLRPC wrapper for rc.reboot.");
$reboot_sig = array(array($XML_RPC_Boolean, $XML_RPC_String));
function reboot_xmlrpc($raw_params) {
	global $xmlrpc_g;

	$params = xmlrpc_params_to_php($raw_params);
	if(!xmlrpc_auth($params)) {
		xmlrpc_authfail();
		return $xmlrpc_g['return']['authfail'];
	}
	mwexec_bg("/etc/rc.reboot");

	return $xmlrpc_g['return']['true'];
}

/*****************************/
$get_notices_sig = array(
	array(
		$XML_RPC_Array,
		$XML_RPC_String
	),
	array(
		$XML_RPC_Array
	)
);

function get_notices_xmlrpc($raw_params) {
	global $g, $xmlrpc_g;

	$params = xmlrpc_params_to_php($raw_params);
	if(!xmlrpc_auth($params)) {
		xmlrpc_authfail();
		return $xmlrpc_g['return']['authfail'];
	}
	if(!function_exists("get_notices"))
		require("notices.inc");
	if(!$params) {
		$toreturn = get_notices();
	} else {
		$toreturn = get_notices($params);
	}
	$response = new XML_RPC_Response(XML_RPC_encode($toreturn));

	return $response;
}

$xmlrpclockkey = lock('xmlrpc', LOCK_EX);

/*****************************/
$server = new XML_RPC_Server(
        array(
		'pfsense.exec_shell' => array('function' => 'exec_shell_xmlrpc',
			'signature' => $exec_shell_sig,
			'docstring' => $exec_shell_doc),
		'pfsense.exec_php' => array('function' => 'exec_php_xmlrpc',
			'signature' => $exec_php_sig,
			'docstring' => $exec_php_doc),	
		'pfsense.filter_configure' => array('function' => 'filter_configure_xmlrpc',
			'signature' => $filter_configure_sig,
			'docstring' => $filter_configure_doc),
		'pfsense.interfaces_carp_configure' => array('function' => 'interfaces_carp_configure_xmlrpc',
			'docstring' => $carp_configure_sig),
		'pfsense.backup_config_section' => array('function' => 'backup_config_section_xmlrpc',
			'signature' => $backup_config_section_sig,
			'docstring' => $backup_config_section_doc),
		'pfsense.restore_config_section' => array('function' => 'restore_config_section_xmlrpc',
			'signature' => $restore_config_section_sig,
			'docstring' => $restore_config_section_doc),
		'pfsense.merge_config_section' => array('function' => 'merge_config_section_xmlrpc',
			'signature' => $merge_config_section_sig,
			'docstring' => $merge_config_section_doc),
		'pfsense.merge_installedpackages_section_xmlrpc' => array('function' => 'merge_installedpackages_section_xmlrpc',
			'signature' => $merge_config_section_sig,
			'docstring' => $merge_config_section_doc),							
		'pfsense.check_firmware_version' => array('function' => 'check_firmware_version_xmlrpc',
			'signature' => $check_firmware_version_sig,
			'docstring' => $check_firmware_version_doc),
		'pfsense.host_firmware_version' => array('function' => 'pfsense_firmware_version_xmlrpc',
			'signature' => $pfsense_firmware_version_sig,
			'docstring' => $host_firmware_version_doc),
		'pfsense.reboot' => array('function' => 'reboot_xmlrpc',
			'signature' => $reboot_sig,
			'docstring' => $reboot_doc),
		'pfsense.get_notices' => array('function' => 'get_notices_xmlrpc',
			'signature' => $get_notices_sig)
        )
);

unlock($xmlrpclockkey);

    function array_overlay($a1,$a2)
    {
        foreach($a1 as $k => $v) {
            if(!array_key_exists($k,$a2)) continue;
            if(is_array($v) && is_array($a2[$k])){
                $a1[$k] = array_overlay($v,$a2[$k]);
            }else{
                $a1[$k] = $a2[$k];
            }
        }
        return $a1;
    }

?>
