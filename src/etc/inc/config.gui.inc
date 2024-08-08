<?php
/*
 * config.gui.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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

require_once("globals.inc");

/* do not load this file twice. */
if ($config_parsed == true) {
	return;
} else {
	$config_parsed = true;
}

/* include globals from notices.inc /utility/XML parser files */
require_once('config.lib.inc');
require_once("notices.inc");
require_once("util.inc");
require_once("Net/IPv6.php");
require_once("xmlparse.inc");
require_once("crypt.inc");

/* if /debugging exists, lets set $debugging
   so we can output more information */
if (file_exists("/debugging")) {
	$debugging = true;
	$g['debug'] = true;
}

config_read_file(true, true);

/* make alias table (for faster lookups) */
alias_make_table();

/* set timezone */
$timezone = config_get_path('system/timezone');
if (isset($timezone) &&
    !empty($timezone)) {
	// noop
} elseif (isset($g['default_timezone']) && !empty(g_get('default_timezone'))) {
	$timezone = g_get('default_timezone');
} else {
	$timezone = "Etc/UTC";
}
date_default_timezone_set("$timezone");

if ($config_parsed == true) {
	/* process packager manager custom rules */
	if (is_dir("/usr/local/pkg/parse_config")) {
		run_plugins("/usr/local/pkg/parse_config/");
	}
}

?>
