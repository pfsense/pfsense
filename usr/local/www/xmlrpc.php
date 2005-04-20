#!/usr/local/bin/php
<?php
/*
	$Id$
	
        xmlrpc.php
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

	TODO:
		* Expose more functions.
		* Add syslog handling of errors.
		* Define XML_RPC_erruser.
		* Write handlers for PHP -> XML_RPC_Value.
		* xmlrpc_params_to_php currently does *not* handle structs.
*/

require_once("xmlrpc_server.inc");
require_once("xmlrpc.inc");
require_once("xmlparse_pkg.inc");
require_once("config.inc");
require_once("functions.inc");

// Exposed functions.

$backup_config_section_doc = 'XMLRPC wrapper for backup_config_section. This method must be called with two parameters: a string containing the local system\'s password followed by a string containing the section to be backed up.';
$backup_config_section_sig = array(array(string, string, string));

function backup_config_section_xmlrpc($raw_params) {
	$params = xmlrpc_params_to_php($raw_params); // Convert XML_RPC_Value objects to a PHP array of values.
	if(!xmlrpc_auth($params)) return new XML_RPC_Response(new XML_RPC_Value("auth_failure", 'string'));
	$val = new XML_RPC_Value(backup_config_section($params[0]), 'string'); 
	return new XML_RPC_Response($val);
}

$restore_config_section_doc = 'XMLRPC wrapper for restore_config_section. This method must be called with three parameters: a string containing the local system\'s password, a string containing the section to be restored, and a string containing the returned value of backup_config_section() for that section. This function returns true upon completion.';
$restore_config_section_sig = array(array(boolean, string, array(), array()));

function restore_config_section_xmlrpc($raw_params) {
	$params = xmlrpc_params_to_php($raw_params);
	$i = 0;
	if(!xmlrpc_auth($params)) return new XML_RPC_Response(new XML_RPC_Value("auth_failure", 'string'));
	foreach($params[0] as $section) {
		restore_config_section($section, $params[1][$i]);
		$i++;
	}
	return new XML_RPC_Response(new XML_RPC_Value(true, 'boolean'));
}

$filter_configure_doc = 'Basic XMLRPC wrapper for filter_configure. This method must be called with one paramater: a string containing the local system\'s password. This function returns true upon completion.';
$filter_configure_sig = array(array(boolean, string));

function filter_configure_xmlrpc($raw_params) {
	$params = xmlrpc_params_to_php($raw_params);
	if(!xmlrpc_auth($params)) return new XML_RPC_Response(new XML_RPC_Value("auth_failure", 'string'));
	filter_configure();
	return new XML_RPC_Response(new XML_RPC_Value(true, 'boolean'));
}

$check_firmware_version_doc = 'Basic XMLRPC wrapper for filter_configure. This function will return the output of check_firmware_version upon completion.';
$check_firmware_version_sig = array(array(string, string));

function check_firmware_version_xmlrpc($raw_params) {
	return new XML_RPC_Response(new XML_RPC_Value(check_firmware_version(false), 'string'));
}


$auto_update_doc = 'Basic XMLRPC wrapper for auto_update. This method must be called with one paramater: a string containing the local system\'s password. This function will return true upon completion.';
$auto_update_sig = array(array(boolean, string));

function auto_update_xmlrpc($raw_params) {
	$params = xmlrpc_params_to_php($raw_params);
        if(!xmlrpc_auth($params)) return new XML_RPC_Response(new XML_RPC_Value("auth_failure", 'string'));
	auto_update();
	return new XML_RPC_Response(new XML_RPC_Value(true, 'boolean'));
}

$server = new XML_RPC_Server(
        array(
            'pfsense.backup_config_section' => 	array('function' => 'backup_config_section_xmlrpc',
							'signature' => $backup_config_section_sig,
							'docstring' => $backup_config_section_doc),
	    'pfsense.restore_config_section' => array('function' => 'restore_config_section_xmlrpc',
							'signature' => $restore_config_section_sig,
							'docstring' => $restore_config_section_doc),
	    'pfsense.filter_configure' => 	array('function' => 'filter_configure_xmlrpc',
							'signature' => $filter_configure_sig,
							'docstring' => $filter_configure_doc),
	    'pfsense.check_firmware_version' =>	array('function' => 'check_firmware_version_xmlrpc',
							'signature' => $check_firmware_version_sig,
							'docstring' => $check_firmware_version_doc)
//	    'pfsense.auto_update' =>		array('function' => 'auto_update_xmlrpc',
//							'signature' => $auto_update_sig,
//							'docstring' => $auto_update_doc)
        )
);
?>
