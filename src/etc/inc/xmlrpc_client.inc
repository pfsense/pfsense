<?php
/*
 * xmlrpc_client.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016 Electric Sheep Fencing, LLC
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

require_once("XML/RPC2/Client.php");

class pfsense_xmlrpc_client {
	
	private $username, $password, $url, $filenotice, $error;
	
	public function __construct($syncip, $port, $username, $password) {
		global $config;
		$this->username = $username;
		$this->password = $password;
		$this->filenotice = "sync_settings";
		
		$protocol = "http";
		if (is_array($config['system']) && 
			is_array($config['system']['webgui']) && 
			!empty($config['system']['webgui']['protocol']) &&
			$config['system']['webgui']['protocol'] == "https") {
			$protocol = "https";
		}
		if (is_ipaddrv6($syncip)) {
			$syncip = "[{$syncip}]";
		}
		//TODO: check if the url needs to end with?:  
		if ($protocol == "https" || $port == "443") {
			$this->url = "https://{$syncip}:{$port}/xmlrpc.php";
		} else {
			$this->url = "http://{$syncip}:{$port}/xmlrpc.php";
		}
	}
	
	function set_noticefile($noticefile) {
		$this->filenotice = $noticefile;
	}

	function xmlrpc_internal($method, $parameter, $timeout = 240) {
		$this->error = null;
		$options = array(
			'prefix' => 'pfsense.',
			'sslverify' => false,
			'connectionTimeout' => $timeout
		);
		
		$numberofruns = 0;
		while ($numberofruns < 2) {
			$numberofruns++;

			log_error(sprintf(gettext("Beginning XMLRPC sync data to %s."), $this->url));
			$cli = XML_RPC2_Client::create($this->url, $options);
			if (!is_object($cli)) {
				$this->error = sprintf(gettext("A communications error occurred while attempting XMLRPC sync with %s (pfsense.%s)."), $this->url, $method);
				log_error($this->error);
				file_notice($this->filenotice, $this->error, "Settings Sync", "");
				continue;
			}
			try {//restore_config_section
				$resp = $cli->$method($this->username, $this->password, $parameter);
			} catch (XML_RPC2_FaultException $e) {
				// The XMLRPC server returns a XMLRPC error
				$this->error = "Exception calling XMLRPC method1 {$method} #" . $e->getFaultCode() . ' : ' . $e->getFaultString();
				log_error($this->error);
				file_notice($this->filenotice, $this->error, "Communications error occurred", "");
				continue;
			} catch (Exception $e) {
				// Other errors (HTTP or networking problems...)
				$this->error = "Exception calling XMLRPC method2 {$method} # " . $e->getMessage() . htmlspecialchars(print_r(debug_backtrace(),true))."DUS";
				log_error($this->error);
				file_notice($this->filenotice, $this->error, gettext("Error code received"), "");
				continue;
			}

			if (!is_array($resp) && trim($resp) == "Authentication failed") {
				$this->error = "An authentication failure occurred while trying to access {$this->url} ({$method}).";
				log_error($this->error);
				file_notice($this->filenotice, $this->error, "Settings Sync", "");
				continue;
			}
			log_error(sprintf(gettext("XMLRPC reload data success with %s (pfsense.{$method})."), $this->url));
			return $resp;
		}
		return null;
	}
	
	function xmlrpc_exec_php($execcmd, $timeout = 240) {
		$resp = $this->xmlrpc_internal("exec_php", $execcmd, $timeout);
		return $resp;
	}
	
	function xmlrpc_method($method, $parameter = "", $timeout = 240) {
		$resp = $this->xmlrpc_internal($method, $parameter, $timeout);
		return $resp;
	}
	function get_error() {
		return $this->error;
	}
}
