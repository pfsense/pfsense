#!/usr/local/bin/php
<?php
/*
 * uploadconfig.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
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

##|+PRIV
##|*IDENT=page-hidden-uploadconfiguration
##|*NAME=Hidden: Upload Configuration
##|*DESCR=Allow access to the 'Hidden: Upload Configuration' page.
##|*MATCH=uploadconfig.php*
##|-PRIV


require_once("guiconfig.inc");

header("Content-Type: text/plain");

/* get config.xml in POST variable "config" */
if ($_POST['config']) {
	$fd = @fopen("{$g['tmp_path']}/config.xml", "w");
	if (!$fd) {
		echo gettext("ERR Could not save configuration.")."\n";
		exit(0);
	}
	fwrite($fd, $_POST['config']);
	fclose($fd);
	if (config_install("{$g['tmp_path']}/config.xml") == 0) {
		echo gettext("OK")."\n";
		system_reboot();
	} else {
		echo gettext("ERR Could not install configuration.")."\n";
	}
} else {
	echo gettext("ERR Invalid configuration received.")."\n";
}

exit(0);
?>
