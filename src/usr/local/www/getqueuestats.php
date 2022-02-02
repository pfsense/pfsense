<?php
/*
 * getqueuestats.php
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

##|+PRIV
##|*IDENT=page-getqueuestats
##|*NAME=AJAX: Get Queue Stats
##|*DESCR=Allow access to the 'AJAX: Get Stats' page.
##|*MATCH=getqueuestats.php*
##|-PRIV

header("Last-Modified: " . gmdate("D, j M Y H:i:s") . " GMT");
header("Expires: " . gmdate("D, j M Y H:i:s", time()) . " GMT");
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache"); // HTTP/1.0

require_once("auth_check.inc");
include_once("shaper.inc");

if ($_REQUEST['format'] == "json") {
	header("Content-type: application/json");
	echo json_encode(get_queue_stats());
}
