<?php
/*
 * help.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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

require_once("guiconfig.inc");

# Docs redirect base URL
$redirect_base = "https://docs.netgate.com/pfsense/help";

$pagename = "";
/* Check for parameter "page". */
if ($_REQUEST && isset($_REQUEST['page'])) {
	$pagename = $_REQUEST['page'];
}

/* If "page" is not found, check referring URL */
if (empty($pagename)) {
	/* Attempt to parse out filename */
	$uri_split = "";
	preg_match("/\/(.*)\?(.*)/", $_SERVER["HTTP_REFERER"], $uri_split);

	/* If there was no match, there were no parameters, just grab the filename
		Otherwise, use the matched filename from above. */
	if (empty($uri_split[0])) {
		$pagename = ltrim(parse_url($_SERVER["HTTP_REFERER"], PHP_URL_PATH), '/');
	} else {
		$pagename = $uri_split[1];
	}

	/* If the referrer was index.php then this was a redirect to help.php
	   because help.php was the first page the user has priv to.
	   In that case we do not want to redirect off to the dashboard help. */
	if ($pagename == "index.php") {
		$pagename = "";
	}

	/* If the filename is pkg_edit.php or wizard.php, reparse looking
		for the .xml filename */
	if (($pagename == "pkg.php") || ($pagename == "pkg_edit.php") || ($pagename == "wizard.php")) {
		$param_split = explode('&', $uri_split[2]);
		foreach ($param_split as $param) {
			if (substr($param, 0, 4) == "xml=") {
				$xmlfile = explode('=', $param);
				$pagename = $xmlfile[1];
			}
		}
	}
}

/* Using the derived page name, attempt to find in the URL mapping hash */
if (strlen($pagename) > 0) {
	/* Clean up the page a little before attempting to use it in a redirect */
	$pagename = urlencode(str_replace(array('%', ':', '..'), '', $pagename));

	/* Redirect to help page. */
	header("Location: {$redirect_base}/{$pagename}");
}

// No page name was determined, so show a message.
$pgtitle = array(gettext("Help"), gettext("About this Page"));
require_once("head.inc");

if (is_array($allowedpages) && str_replace('*', '', $allowedpages[0]) == "help.php") {
	if (count($allowedpages) == 1) {
		print_info_box(gettext("The Help page is the only page this user has privilege for."));
	} else {
		print_info_box(gettext("Displaying the Help page because it is the first page this user has privilege for."));
	}
} else {
	print_info_box(gettext("Help page accessed directly without any page parameter."));
}

include("foot.inc");
?>
