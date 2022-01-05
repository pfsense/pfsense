<?php
/*
 * auth_check.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2017-2022 Rubicon Communications, LLC (Netgate)
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
/*
 * Light weight authentication check that is meant as a substitute for guiconfig.inc
 * in cases where frequent automatic requests are made like graphs and widget pages.
 */

include_once('phpsessionmanager.inc');
require_once("auth_func.inc");

function session_read_single_var($varname) {
	@phpsession_begin();
	$result = $_SESSION[$varname];
	// Perform a commit to avoid the session timing out prematurely. See https://redmine.pfsense.org/issues/8116
	@phpsession_end(true);
	return $result;
}

$session_pagematch = session_read_single_var("page-match");

$pageuri = $_SERVER['REQUEST_URI'];
if (cmp_page_matches($pageuri, $session_pagematch)) {
	return; // auth OK
}
require_once("authgui.inc");
