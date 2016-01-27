<?php
/*
	config.gui.inc

	part of pfSense (https://www.pfsense.org)
	Copyright (c) 2004-2016 Electric Sheep Fencing, LLC. All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
require_once("IPv6.inc");
if (file_exists("/cf/conf/use_xmlreader")) {
	require_once("xmlreader.inc");
} else {
	require_once("xmlparse.inc");
}
require_once("crypt.inc");

/* read platform */
if (file_exists("{$g['etc_path']}/platform")) {
	$g['platform'] = chop(file_get_contents("{$g['etc_path']}/platform"));
} else {
	$g['platform'] = "unknown";
}

/* if /debugging exists, lets set $debugging
   so we can output more information */
if (file_exists("/debugging")) {
	$debugging = true;
	$g['debug'] = true;
}

$config = parse_config();

/* set timezone */
if (isset($config['system']['timezone']) &&
    !empty($config['system']['timezone'])) {
	$timezone = $config['system']['timezone'];
} elseif (isset($g['default_timezone']) && !empty($g['default_timezone'])) {
	$timezone = $g['default_timezone'];
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
