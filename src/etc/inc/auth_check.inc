<?php
/*
 * auth_check.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2017 Rubicon Communications, LLC (Netgate)
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

// this function is a duplicate from cmp_page_matches() in priv.inc
// however unconditionally including priv.inc takes significant more time/cpu
function cmp_page_matches2($page, & $matches, $fullwc = true) {

//	$dbg_matches = implode(",", $matches);
//	log_error("debug: checking page {$page} match with {$dbg_matches}");

	if (!is_array($matches)) {
		return false;
	}

	/* skip any leading fwdslash */
	$test = strpos($page, "/");
	if ($test !== false && $test == 0) {
		$page = substr($page, 1);
	}

	/* look for a match */
	foreach ($matches as $match) {

		/* possibly ignore full wildcard match */
		if (!$fullwc && !strcmp($match , "*")) {
			continue;
		}

		/* compare exact or wildcard match */
		$match = str_replace(array(".", "*", "?"), array("\.", ".*", "\?"), $match);
		$result = preg_match("@^/{$match}$@", "/{$page}");

		if ($result) {
			return true;
		}
	}

	return false;
}

function session_read_single_var($varname) {
	$session_started = false;
	if (!session_id()) {
		session_start();
		$session_started = true;
	}
	$result = $_SESSION[$varname];
	if ($session_started) {
		// if we started the session then lets close it..
		session_abort();
	}
	return $result;
}

$session_pagematch = session_read_single_var("page-match");

$pageuri = $_SERVER['REQUEST_URI'];
if (cmp_page_matches2($pageuri, $session_pagematch)) {	
	return; // auth OK
}
require_once("authgui.inc");