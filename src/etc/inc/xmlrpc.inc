<?php
/*
	xmlrpc.inc

	part of pfSense (https://www.pfsense.org)
	Copyright (C) 2005-2006 Colin Smith
	Copyright (c) 2005-2016 Electric Sheep Fencing, LLC.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in
	   the documentation and/or other materials provided with the
	   distribution.

	3. All advertising materials mentioning features or use of this software
	   must display the following acknowledgment:
	   "This product includes software developed by the pfSense Project
	   for use in the pfSense® software distribution. (http://www.pfsense.org/).

	4. The names "pfSense" and "pfSense Project" must not be used to
	   endorse or promote products derived from this software without
	   prior written permission. For written permission, please contact
	   coreteam@pfsense.org.

	5. Products derived from this software may not be called "pfSense"
	   nor may "pfSense" appear in their names without prior written
	   permission of the Electric Sheep Fencing, LLC.

	6. Redistributions of any form whatsoever must retain the following
	   acknowledgment:

	"This product includes software developed by the pfSense Project
	for use in the pfSense software distribution (http://www.pfsense.org/).

	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
	OF THE POSSIBILITY OF SUCH DAMAGE.
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
