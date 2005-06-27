<?php
/* $Id$ */
/*
	xmlparse.inc
	functions to parse/dump configuration files in XML format
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/

function startElement($parser, $name, $attrs) {
	global $parsedcfg, $depth, $curpath, $havedata, $listtags;

	array_push($curpath, strtolower($name));

	$ptr =& $parsedcfg;
	foreach ($curpath as $path) {
		$ptr =& $ptr[$path];
	}

	/* is it an element that belongs to a list? */
	if (in_array(strtolower($name), $listtags)) {

		/* is there an array already? */
		if (!is_array($ptr)) {
			/* make an array */
			$ptr = array();
		}

		array_push($curpath, count($ptr));

	} else if (isset($ptr)) {
		/* multiple entries not allowed for this element, bail out */
		die(sprintf("XML error: %s at line %d cannot occur more than once\n",
				$name,
				xml_get_current_line_number($parser)));
	}

	$depth++;
	$havedata = $depth;
}

function endElement($parser, $name) {
	global $depth, $curpath, $parsedcfg, $havedata, $listtags;

	if ($havedata == $depth) {
		$ptr =& $parsedcfg;
		foreach ($curpath as $path) {
			$ptr =& $ptr[$path];
		}
		$ptr = "";
	}

	array_pop($curpath);

	if (in_array(strtolower($name), $listtags))
		array_pop($curpath);

	$depth--;
}

function cData($parser, $data) {
	global $depth, $curpath, $parsedcfg, $havedata;

	$data = trim($data, "\t\n\r");

	if ($data != "") {
		$ptr =& $parsedcfg;
		foreach ($curpath as $path) {
			$ptr =& $ptr[$path];
		}

		if (is_string($ptr)) {
			$ptr .= $data;
		} else {
			if (trim($data, " ") != "") {
				$ptr = $data;
				$havedata++;
			}
		}
	}
}

function parse_xml_config($cffile, $rootobj) {
	global $listtags;
	$listtags = explode(" ", "cacert row config package columnitem option item fieldname field rule user key subqueue " .
                "dnsserver winsserver encryption-algorithm-option hash-algorithm-option hosts tunnel " .
                "onetoone staticmap route alias pipe queue shellcmd earlyshellcmd mobilekey " .
                "service servernat proxyarpnet passthrumac allowedip wolentry vlan menu domainoverrides");
	return parse_xml_config_raw($cffile, $rootobj);
}

function parse_xml_config_pkg($cffile, $rootobj) {
	global $listtags;
	$listtags = array("onetoone", "queue", "rule", "servernat", "alias", "additional_files_needed", "tab", "template", "menu", "rowhelperfield", "service", "step", "package", "columnitem", "option", "item", "field", "package");
	return parse_xml_config_raw($cffile, $rootobj);
}

function parse_xml_config_raw($cffile, $rootobj) {

	global $depth, $curpath, $parsedcfg, $havedata, $listtags;
	$parsedcfg = array();
	$curpath = array();
	$depth = 0;
	$havedata = 0;

	$xml_parser = xml_parser_create();

	xml_set_element_handler($xml_parser, "startElement", "endElement");
	xml_set_character_data_handler($xml_parser, "cdata");

	if (!($fp = fopen($cffile, "r"))) {
		die("Error: could not open XML input\n");
	}

	while ($data = fread($fp, 4096)) {
		if (!xml_parse($xml_parser, $data, feof($fp))) {
			die(sprintf("XML error: %s at line %d\n",
						xml_error_string(xml_get_error_code($xml_parser)),
						xml_get_current_line_number($xml_parser)));
		}
	}
	xml_parser_free($xml_parser);

	if (!$parsedcfg[$rootobj]) {
		die("XML error: no $rootobj object found!\n");
	}

	return $parsedcfg[$rootobj];
}

function dump_xml_config_sub($arr, $indent) {

	global $listtags;

	$xmlconfig = "";

	foreach ($arr as $ent => $val) {
		if (is_array($val)) {
			/* is it just a list of multiple values? */
			if (in_array(strtolower($ent), $listtags)) {
				foreach ($val as $cval) {
					if (is_array($cval)) {
						$xmlconfig .= str_repeat("\t", $indent);
						$xmlconfig .= "<$ent>\n";
						$xmlconfig .= dump_xml_config_sub($cval, $indent + 1);
						$xmlconfig .= str_repeat("\t", $indent);
						$xmlconfig .= "</$ent>\n";
					} else {
						$xmlconfig .= str_repeat("\t", $indent);
						if ((is_bool($cval) && ($cval == true)) ||
							($cval === ""))
							$xmlconfig .= "<$ent/>\n";
						else if (!is_bool($cval))
							$xmlconfig .= "<$ent>" . htmlspecialchars($cval) . "</$ent>\n";
					}
				}
			} else {
				/* it's an array */
				$xmlconfig .= str_repeat("\t", $indent);
				$xmlconfig .= "<$ent>\n";
				$xmlconfig .= dump_xml_config_sub($val, $indent + 1);
				$xmlconfig .= str_repeat("\t", $indent);
				$xmlconfig .= "</$ent>\n";
			}
		} else {
			if ((is_bool($val) && ($val == true)) || ($val === "")) {
				$xmlconfig .= str_repeat("\t", $indent);
				$xmlconfig .= "<$ent/>\n";
			} else if (!is_bool($val)) {
				$xmlconfig .= str_repeat("\t", $indent);
				$xmlconfig .= "<$ent>" . htmlspecialchars($val) . "</$ent>\n";
			}
		}
	}

	return $xmlconfig;
}

function dump_xml_config($arr, $rootobj) {
	global $listtags;
	$listtags = explode(" ", "cacert row config package columnitem option item fieldname field rule user key subqueue " .
			"dnsserver winsserver encryption-algorithm-option hash-algorithm-option hosts tunnel " .
			"onetoone staticmap route alias pipe queue shellcmd earlyshellcmd mobilekey " .
			"service servernat proxyarpnet passthrumac allowedip wolentry vlan menu domainoverrides");
	return dump_xml_config_raw($arr, $rootobj);
}

function dump_xml_config_pkg($arr, $rootobj) {
	global $listtags;
	$listtags = array("onetoone", "queue", "rule", "servernat", "alias", "additional_files_needed", "tab", "template", "menu", "rowhelperfield", "service", "step", "package", "columnitem", "option", "item", "field", "package");
	return dump_xml_config_raw($arr, $rootobj);
}

function dump_xml_config_raw($arr, $rootobj) {

	$xmlconfig = "<?xml version=\"1.0\"?" . ">\n";
	$xmlconfig .= "<$rootobj>\n";

	$xmlconfig .= dump_xml_config_sub($arr, 1);

	$xmlconfig .= "</$rootobj>\n";

	return $xmlconfig;
}

?>
