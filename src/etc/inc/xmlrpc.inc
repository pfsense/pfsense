<?php
/*
 * xmlrpc.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2005-2016 Electric Sheep Fencing, LLC
 * Copyright (c) 2005-2006 Colin Smith
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once("auth.inc");
require_once("xmlrpc_client.inc");

/*
 *   xmlrpc_params_to_php: Convert params array passed from XMLRPC server into a PHP array and return it.
 */
function xmlrpc_params_to_php($params) {
	$array = array();
	for ($i = 0; $i < $params->getNumParams(); $i++) {
		$value = $params->getParam($i);
		$array[] = XML_RPC_decode($value);
	}
	return $array;
}

/*
 *   xmlrpc_value_to_php: Convert an XMLRPC value into a PHP scalar/array and return it.
 */
function xmlrpc_value_to_php($raw_value) {
	return XML_RPC_decode($raw_value);
}

/*
 *   php_value_to_xmlrpc: Convert a PHP scalar or array into its XMLRPC equivalent.
 */
function php_value_to_xmlrpc($value, $force_array = false) {
	$toreturn = XML_RPC_encode($value);
	return $force_array ? array($toreturn) : $toreturn;
}

/*
 *   xmlrpc_auth: Handle basic crypt() authentication of an XMLRPC request. This function assumes that
 *                $params[0] contains the local system's plaintext password and removes the password from
 *                the array before returning it.
 */
function xmlrpc_auth(&$params) {
	global $config, $_SERVER;

	/* XXX: Should teach caller to pass username and use it here. */
	/* XXX: Should clarify from old behaviour what is in params[0] that differs from params['xmlrpcauth'] */
	if (isset($config['system']['webgui']['authmode'])) {
		$authcfg = auth_get_authserver($config['system']['webgui']['authmode']);
		if (authenticate_user("admin", $params[0], $authcfg) ||
		    authenticate_user("admin", $params[0])) {
			array_shift($params);
			unset($params['xmlrpcauth']);
			return true;
		} else if (!empty($params['xmlrpcauth']) &&
		    (authenticate_user("admin", $params['xmlrpcauth'], $authcfg) ||
		    authenticate_user("admin", $params['xmlrpcauth']))) {
			array_shift($params);
			unset($params['xmlrpcauth']);
			return true;
		}
	} else if (authenticate_user("admin", $params[0])) {
		array_shift($params);
		unset($params['xmlrpcauth']);
		return true;
	} else if (!empty($params['xmlrpcauth']) &&
	    authenticate_user("admin", $params['xmlrpcauth'])) {
		array_shift($params);
		unset($params['xmlrpcauth']);
		return true;
	}

	array_shift($params);
	unset($params['xmlrpcauth']);
	log_error(sprintf(gettext("webConfigurator authentication error for 'admin' from %s during sync settings."), $_SERVER['REMOTE_ADDR']));
	return false;
}

?>
