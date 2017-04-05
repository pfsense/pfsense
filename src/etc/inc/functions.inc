<?php
/*
 * functions.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

/* BEGIN compatibility goo with HEAD */
if (!function_exists("pfSenseHeader")) {
	/****f* pfsense-utils/pfSenseHeader
	 * NAME
	 *   pfSenseHeader
	 * INPUTS
	 *   none
	 * RESULT
	 *   Javascript header change or browser Location:
	 ******/
	function pfSenseHeader($text) {
		global $_SERVER;
		if (isAjax()) {
			if ($_SERVER['HTTPS'] == "on") {
				$protocol = "https";
			} else {
				$protocol = "http";
			}

			$port = ":{$_SERVER['SERVER_PORT']}";
			if ($_SERVER['SERVER_PORT'] == "80" && $protocol == "http") {
				$port = "";
			}
			if ($_SERVER['SERVER_PORT'] == "443" && $protocol == "https") {
				$port = "";
			}
			$complete_url = "{$protocol}://{$_SERVER['HTTP_HOST']}{$port}/{$text}";
			echo "\ndocument.location.href = '{$complete_url}';\n";
		} else {
			header("Location: $text");
		}
	}
}
/* END compatibility goo with HEAD */

/* include all configuration functions */
require_once("interfaces.inc");
require_once("gwlb.inc");
require_once("services.inc");
require_once("pfsense-utils.inc");
require_once("certs.inc");
require_once("system.inc");
require_once("vslb.inc");

?>
