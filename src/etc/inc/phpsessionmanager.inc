<?php
/*
 * phpsessionmanager.inc
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

$session_opencounter = 0;
$session_write = false;
$session_action_list = array();

function simplestacktrace() {
	$stack = debug_backtrace();
	$str = "";
	foreach($stack as $s) {
		// $s['args']
		$str .= "\n{$s['function']}(..) - {$s['file']}:{$s['line']}";
	}
	return $str;
}

function phpsession_begin() {
	global $session_opencounter, $session_action_list;
	$session_action_list[] = "#### phpsession_begin ####" . simplestacktrace();
	if ($session_opencounter == 0) {
		session_start();
	}
	$session_opencounter++;
}

function phpsession_destroy() {
	global $session_opencounter, $session_action_list;
	$session_action_list[] = "#### phpsession_destroy ####" . simplestacktrace();
	session_destroy();
	$session_opencounter = 0;
}

function phpsession_end($write = false) {
	global $session_opencounter, $session_write, $session_action_list;
	$session_action_list[] = "#### phpsession_end ####" . simplestacktrace();
	$session_write |= $write;
	$session_opencounter--;
	if ($session_opencounter == 0) {
		if ($session_write) {
			session_commit();
			$session_write = false;
		} else {
			session_abort();
		}
	}
	if ($session_opencounter < 0) {
		$session_opencounter = 0;
		syslog(LOG_WARNING, "PHPSESSION closed more often than opened!" . simplestacktrace());
	}
}

function phpsession_cleanupcheck() {
	global $session_opencounter, $session_action_list;
	if ($session_opencounter > 0) {
		syslog(LOG_WARNING, "PHPSESSION {$session_opencounter} open sessions left at shutdown script!".print_r($session_action_list, true));
	}
}

register_shutdown_function('phpsession_cleanupcheck');
