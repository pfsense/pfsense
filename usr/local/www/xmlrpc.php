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

/*
 *  backup_config_section_xmlrpc: XMLRPC wrapper for backup_config_section.
 *  This method must be called with two parameters: a string containing the md5 of
 *  the local system's password followed by a string containing the section to be backed up.
 */
$backup_config_section_sig = array(array($xmlrpcString, $xmlrpcString, $xmlrpcString));
function backup_config_section_xmlrpc($params) {
	global $config;
	if($params->getNumParams() != 2) return; // Make sure we have 2 params.
	$param1 = $params->getParam(0);
	$md5 = $param1->scalarval();
	if($md5 != md5($config['system']['password'])) return; // Basic authentication.
	$param2 = $params->getParam(1);
	$section = $param2->scalarval();
	$val = new XML_RPC_Value(backup_config_section($section), 'string'); 
	return new XML_RPC_Response($val);
}

/*
 *  restore_config_section_xmlrpc: XMLRPC wrapper for restore_config_section.
 *  This method must be called with three parameters: a string containing the md5 of
 *  the local system's password, a string containing the section to be restored,
 *  and a string containing the returned value of backup_config_section() for that
 *  section. This function returns 
 */
$restore_config_section_sig = array(array($xmlrpcBoolean, $xmlrpcString, $xmlrpcString, $xmlrpcString));
function restore_config_section_xmlrpc($params) {
	global $config;
	if($params->getNumParams() != 3) return; // Make sure we have 3 params.
	$param1 = $params->getParam(0);
	$md5 = $param1->scalarval();
	if($md5 != md5($config['system']['password'])) return; // Basic authentication.
	$param2 = $params->getParam(1);
	$section - $param2->scalarval();
	$param3 = $params->getParam(2);
	$new_contents = $param3->scalarval();
	restore_config_section($section, $new_contents);
	return new XML_RPC_Response(new XML_RPC_Value(true, 'boolean'));
}

$server = new XML_RPC_Server(
        array(
            'pfsense.backup_config_section' => array('function' => 'backup_config_section_xmlrpc',
							'signature' => $backup_config_section_sig),
	    'pfsense.restore_config_section' => array('function' => 'restore_config_section_xmlrpc',
							'signature' => $restore_config_section_sig)
        )
);
?>
