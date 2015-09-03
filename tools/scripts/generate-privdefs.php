#!/usr/local/bin/php -f
<?php
/* ====================================================================
 *  Copyright (c) 2004-2015 Electric Sheep Fencing, LLC. All rights reserved.
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *     this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *  3. All advertising materials mentioning features or use of this software
 *     must display the following acknowledgment:
 *     "This product includes software developed by the pfSense Project
 *     for use in the pfSense® software distribution. (http://www.pfsense.org/).
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *     endorse or promote products derived from this software without
 *     prior written permission. For written permission, please contact
 *     coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *     nor may "pfSense" appear in their names without prior written
 *     permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *     acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *  ====================================================================
 */

/*
 * This utility processes the <prefix>/usr/local/www
 * directory and builds a privilege definition file
 * based on the embedded metadata tags. For more info
 * please see <prefix>/etc/inc/meta.inc
 */

if (count($argv) < 2) {
	echo "usage: generate-privdefs <prefix>\n";
	echo "\n";
	echo "This utility generates privilege definitions and writes them to\n";
	echo "'<prefix>/etc/inc/priv.defs.inc'. The <prefix> parameter should\n";
	echo "be specified as your base pfSense working directory.\n";
	echo "\n";
	echo "Examples:\n";
	echo "#generate-privdefs /\n";
	echo "#generate-privdefs /home/pfsense/RELENG_1/pfSense/\n";
	echo "\n";
	exit -1;
}

$prefix = $argv[1];
if (!file_exists($prefix)) {
	echo "prefix {$prefix} is invalid";
	exit -1;
}

$metainc = $prefix."etc/inc/meta.inc";

if (!file_exists($metainc)) {
	echo "unable to locate {$metainc} file\n";
	exit -1;
}

require_once($metainc);

echo "--Locating www php files--\n";

$path = $prefix."/usr/local/www";
list_phpfiles($path, $found);

echo "--Gathering privilege metadata--\n";

$data;
foreach ($found as $fname)
	read_file_metadata($path."/".$fname, $data, "PRIV");

echo "--Generating privilege definitions--\n";
$privdef = $prefix."etc/inc/priv.defs.inc";

$fp = fopen($privdef,"w");
if (!$fp) {
	echo "unable to open {$privdef}\n";
	exit -2;
}

$pdata;
$pdata  = "<?php\n";
$pdata .= "/*\n";
$pdata .= " * priv.defs.inc - Generated privilege definitions\n";
$pdata .= " *\n";
$pdata .= " */\n";
$pdata .= "\n";
$pdata .= "\$priv_list = array();\n";
$pdata .= "\n";
$pdata .= "\$priv_list['page-all'] = array();\n";
$pdata .= "\$priv_list['page-all']['name'] = \"WebCfg - All pages\";\n";
$pdata .= "\$priv_list['page-all']['descr'] = \"Allow access to all pages\";\n";
$pdata .= "\$priv_list['page-all']['match'] = array();\n";
$pdata .= "\$priv_list['page-all']['match'][] = \"*\";\n";
$pdata .= "\n";

foreach ($data as $fname => $tags) {

	foreach ($tags as $tname => $vals) {

		$ident = "";
		$name = "";
		$descr = "";
		$match = array();

		foreach ($vals as $vname => $vlist) {

			switch ($vname) {
				case "IDENT":
					$ident = $vlist[0];
					break;
				case "NAME":
					$name = $vlist[0];
					break;
				case "DESCR":
					$descr = $vlist[0];
					break;
				case "MATCH":
					$match = $vlist;
					break;
			}
		}

		if (!$ident) {
			echo "invalid IDENT in {$fname} privilege\n";
			continue;
		}

		if (!count($match)) {
			echo "invalid MATCH in {$fname} privilege\n";
			continue;
		}

		$pdata .= "\$priv_list['{$ident}'] = array();\n";
		$pdata .= "\$priv_list['{$ident}']['name'] = \"WebCfg - {$name}\";\n";
		$pdata .= "\$priv_list['{$ident}']['descr'] = \"{$descr}\";\n";
		$pdata .= "\$priv_list['{$ident}']['match'] = array();\n";

		foreach ($match as $url)
			$pdata .= "\$priv_list['{$ident}']['match'][] = \"{$url}\";\n";

		$pdata .= "\n";
	}
}

$pdata .= "\n";
$pdata .= "\$priv_rmvd = array();\n";
$pdata .= "\n";

$pdata .= "?>\n";
fwrite($fp, $pdata);

fclose($fp);

/*
 * TODO : Build additional functionality
 *

echo "--Checking for pages without privilege definitions--\n";

foreach ($found as $fname) {
	$match = false;
	foreach ($pages_current as $pname => $pdesc) {
		if (!strcmp($pname,$fname)) {
			$match = true;
			break;
		}
	}
	if (!$match)
		echo "missing: $fname\n";
}

echo "--Checking for stale privilege definitions--\n";

foreach ($pages_current as $pname => $pdesc) {
	$match = false;
	foreach ($found as $fname) {
		if (!strncmp($fname,$pname,strlen($fname))) {
			$match = true;
			break;
		}
	}
	if (!$match)
		echo "stale: $pname\n";
}

 */

?>
