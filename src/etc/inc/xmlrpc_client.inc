<?php
/*
 * xmlrpc_client.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016 Electric Sheep Fencing
 * Copyright (c) 2016-2022 Rubicon Communications, LLC (Netgate)
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

	private $username, $password, $url, $logurl, $filenotice, $error;

	public function __construct() {
		global $config;
		$hasync = $config['hasync'];

		if (empty($hasync['username'])) {
			$username = "admin";
		} else {
			$username = $hasync['username'];
		}
		/* if port is empty lets rely on the protocol selection */
		$port = $config['system']['webgui']['port'];
		if (empty($port)) {
			if ($config['system']['webgui']['protocol'] == "http") {
				$port = "80";
			} else {
				$port = "443";
			}
		}
		$this->setConnectionData($hasync['synchronizetoip'], $port, $username, $hasync['password']);
	}

	public function setConnectionData($syncip, $port, $username, $password, $scheme = "") {
		global $config;
		$this->username = $username;
		$this->password = $password;
		$this->filenotice = "sync_settings";
		if (empty($scheme)) {
			$scheme = "http";
			if ($port == "443") {
				$scheme = "https";
			} else if (is_array($config['system']) &&
				is_array($config['system']['webgui']) &&
				!empty($config['system']['webgui']['protocol']) &&
				$config['system']['webgui']['protocol'] == "https") {
				$scheme = "https";
			}
		}
		if (is_ipaddrv6($syncip)) {
			$syncip = "[{$syncip}]";
		}
		$user = rawurlencode($this->username);
		$pass = rawurlencode($this->password);

		$this->logurl = "{$scheme}://{$syncip}:{$port}/xmlrpc.php";
		$this->url = "{$scheme}://{$user}:{$pass}@{$syncip}:{$port}/xmlrpc.php";
	}

	public function set_noticefile($noticefile) {
		$this->filenotice = $noticefile;
	}

	private function xmlrpc_internal($method, $parameter, $timeout = 240) {
		$options = array(
			'prefix' => 'pfsense.',
			'sslverify' => false,
			'connectionTimeout' => $timeout
		);

		$max_attempts = 4;
		$numberofruns = 0;
		while ($numberofruns < $max_attempts) {
			$numberofruns++;
			$this->error = null;

			log_error(sprintf(gettext("Beginning XMLRPC sync data to %s."), $this->logurl));
			$cli = XML_RPC2_Client::create($this->url, $options);
			if (!is_object($cli)) {
				$this->error = sprintf(gettext("A communications error occurred while attempting XMLRPC sync with %s (pfsense.%s)."), $this->log, $method);
			}
			try {//restore_config_section
				$REQUEST_URI = $_SERVER['REQUEST_URI'];
				unset($_SERVER['REQUEST_URI']); // force use of 'toText()' when setting XML_RPC2_CurlException message
				$resp = $cli->$method($parameter, $timeout);
			} catch (XML_RPC2_FaultException $e) {
				// The XMLRPC server returns a XMLRPC error
				$this->error = "Exception calling XMLRPC method {$method} #" . $e->getFaultCode() . ' : ' . $e->getFaultString();
				log_error($this->error);
				file_notice($this->filenotice, $this->error, "Communications error occurred", "");
			} catch (XML_RPC2_CurlException $e) {
				$previouserror = $e->getPrevious();// HTTP_Request2_ConnectionException
				if ($previouserror == null) {
					// CurlException doesnt get filled with PreviousError,
					// however we dont want to show the stacktrace included in the 'message' to non sysadmin users
					preg_match("/HTTP_Request2_ConnectionException: (.*) in \/.*/", $e->getMessage(), $errormsg);
					if (empty($errormsg) || (is_array($errormsg) && empty($errormsg[1]))) {
						$errormsg = $e->getMessage();
					}
					$this->error = "A communications error occurred while attempting to call XMLRPC method {$method}: {$errormsg}";
				} else {
					$this->error = "CurlException calling XMLRPC method {$method} #" . $previouserror->getMessage();
				}
			} catch (Exception $e) {
				// Other errors (HTTP or networking problems...)
				$this->error = "Exception calling XMLRPC method {$method} # " . $e->getMessage();
			} finally {
				if (isset($REQUEST_URI)) {
					// restore the unset variable to its previous state.
					$_SERVER['REQUEST_URI'] = $REQUEST_URI;
				}
			}

			if (!is_array($resp) && trim($resp) == "Authentication failed") {
				$this->error = "An authentication failure occurred while trying to access {$this->logurl} ({$method}).";
			}
			if (empty($this->error)) {
				log_error(sprintf(gettext("XMLRPC reload data success with %s (pfsense.{$method})."), $this->logurl));
				return $resp;
			} elseif ($numberofruns < $max_attempts) {
				log_error(sprintf(gettext("Retrying XMLRPC Request due to error: %s"), $this->error));
				sleep(1);
			} else {
				log_error($this->error);
				file_notice($this->filenotice, $this->error, "XMLRPC Error", "");
			}
		}
		return null;
	}

	public function xmlrpc_exec_php($execcmd, $timeout = 240) {
		$resp = $this->xmlrpc_internal("exec_php", $execcmd, $timeout);
		return $resp;
	}

	public function xmlrpc_method($method, $parameter = "", $timeout = 240) {
		$resp = $this->xmlrpc_internal($method, $parameter, $timeout);
		return $resp;
	}

	public function get_error() {
		return $this->error;
	}

	public function getUrl() {
		return $this->logurl;
	}
}
