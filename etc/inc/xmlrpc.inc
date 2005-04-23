<?php
/*
	$Id$

        xmlrpc.inc
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

require_once("xmlrpc_client.inc");

/*
 *   xmlrpc_params_to_php: Convert params array passed from XMLRPC server into a PHP array and return it.
 */
function xmlrpc_params_to_php($params) {
        $array = array();
        for($i = 0; $i < $params->getNumParams(); $i++) {
                $value = $params->getParam($i);
                $array[] = xmlrpc_value_to_php($value);
        }
        return $array;
}
 
/*
 *   xmlrpc_value_to_php: Convert an XMLRPC value into a PHP scalar/array and return it.
 */
function xmlrpc_value_to_php($raw_value) {
        switch($raw_value->kindOf()) {
        case "scalar":
                $return = $raw_value->scalarval();
                break;
        case "array":
                $return = array();
                for($i = 0; $i < $raw_value->arraysize(); $i++) {
                        $value = $raw_value->arraymem($i);
                        $return[] = xmlrpc_value_to_php($value);
                }
                break;
        case "struct":
                $return = array();
                for($i = 0; $i < $raw_value->arraysize(); $i++) {
                        list($key, $value) = $raw_value->structeach();
                        $return[$key] = xmlrpc_value_to_php($value);
                }
                break;
        }
        return $return;
}

/*
 *   php_value_to_xmlrpc: Convert a PHP scalar or array into its XMLRPC equivalent.
 */
function php_value_to_xmlrpc($value, $force_array = false) {
        if(gettype($value) == "array") {
                $xmlrpc_type = "array";
                $toreturn = array();
                foreach($value as $key => $val) {
                        if(is_string($key)) $xmlrpc_type = "struct";
                        $toreturn[$key] = php_value_to_xmlrpc($val);
                }
                return new XML_RPC_Value($toreturn, $xmlrpc_type);
        } else {
                if($force_array == true) {
                        return new XML_RPC_Value(array(new XML_RPC_Value($value, gettype($value))), "array");
                } else {
                        return new XML_RPC_Value($value, gettype($value));
                }
        }
}

/*
 *   xmlrpc_auth: Handle basic crypt() authentication of an XMLRPC request. This function assumes that
 *                $params[0] contains the local system's plaintext password and removes the password from
 *                the array before returning it.
 */
function xmlrpc_auth(&$params) {
        global $config;
	$localpass = $config['system']['password'];
        if(crypt($params[0], $localpass) == $localpass) {
                array_shift($params);
		return true;
	} elseif(crypt($params['xmlrpcauth'], $localpass) != $localpass) {
		unset($params['xmlrpcauth']);
		return true;
	}
        return false;
}

