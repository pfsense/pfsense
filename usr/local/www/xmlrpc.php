#!/usr/local/bin/php
<?php
/*
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
*/

require_once("xmlrpc_server.inc");
require_once("config.inc");
require_once("functions.inc");

// Helper functions.

/*
 *   xmlrpc_params_to_php: Convert params array passed from XMLRPC server into a PHP array and return it.
 *
 *   XXX: This function does not currently handle XML_RPC_Value objects of type "struct".
 */
function xmlrpc_params_to_php($params) {
	$array = array();
	$param_length = $params->getNumParams();
	for($i = 0; $i < $params->getNumParams(); $i++) {
		$value = $params->getParam($i);
		if($value->kindOf() == "scalar") {
			$array[] = $value->scalarval();
		} elseif($value->kindOf() == "array") {
			$array[] = xmlrpc_array_to_php($value);
		}
	}
	return $array;
}

/*
 *   xmlrpc_array_to_php: Convert an XMLRPC array into a PHP array and return it.
 */
function xmlrpc_array_to_php($array) {
	$return = array();
	$array_length = $array->arraysize();
	for($i = 0; $i < $array->arraysize(); $i++) {
		$value = $array->arraymem($i);
		if($value->kindOf() == "scalar") {
			$return[] = $value->scalarval();
		} elseif($value->kindOf() == "array") {
			$return[] = xmlrpc_array_to_php($value);
		}
	}
	return $return;
}

// Exposed functions.

$backup_config_section_doc = 'XMLRPC wrapper for backup_config_section. This method must be called with two parameters: a string containing the local system\'s password followed by a string containing the section to be backed up.';
$backup_config_section_sig = array(array(string, string, string));

function backup_config_section_xmlrpc($raw_params) {
	global $config;
	$params = xmlrpc_params_to_php($raw_params); // Convert XML_RPC_Value objects to a PHP array of values.
	if(crypt($params[0], $config['system']['password']) != $config['system']['password'])
		return new XML_RPC_Response(new XML_RPC_Value("FAILURE.", 'string'));
	$val = new XML_RPC_Value(backup_config_section($params[1]), 'string'); 
	return new XML_RPC_Response($val);
}

$restore_config_section_doc = 'XMLRPC wrapper for restore_config_section. This method must be called with three parameters: a string containing the local system\'s password, a string containing the section to be restored, and a string containing the returned value of backup_config_section() for that section. This function returns true upon completion.';
$restore_config_section_sig = array(array(boolean, string, string, string));

function restore_config_section_xmlrpc($raw_params) {
	global $config;
	$params = xmlrpc_params_to_php($raw_params);
	if(crypt($params[0], $config['system']['password']) != $config['system']['password']) return; // Basic authentication.
	restore_config_section($params[1], $params[2]);
	return new XML_RPC_Response(new XML_RPC_Value(true, 'boolean'));
}

$filter_configure_doc = 'Basic XMLRPC wrapper for filter_configure. This method must be called with one paramater: a string containing the local system\'s password. This function returns true upon completion.';
$filter_configure_sig = array(array(boolean, string));

function filter_configure_xmlrpc($raw_params) {
	global $config;
	$params = xmlrpc_params_to_php($raw_params);
	if(crypt($params[0], $config['system']['password']) != $config['system']['password']) return; // Basic authentication.
	filter_configure();
	return new XML_PRC_Response(new XML_RPC_Value(true, 'boolean'));
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
							'docstring' => $filter_configure_doc)
        )
);
?>
