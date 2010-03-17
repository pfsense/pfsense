<?php
/* $Id$ */
/*
	regdomain.inc
	functions to parse /etc/regdomain.xml

	Copyright (C) 2010 Erik Fonnesbeck

	based on xmlparse.inc
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.

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

/* The following items will be treated as arrays in regdomain.xml */
function listtags_rd() {
	$ret = explode(" ",
		"band country flags freqband netband rd  "
		);
	return $ret;
}

function startElement_rd($parser, $name, $attrs) {
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

		if (!empty($attrs))
			$ptr[count($ptr)]['attributes'] = $attrs;

	} else if (isset($ptr)) {
		/* multiple entries not allowed for this element, bail out */
		die(sprintf("XML error: %s at line %d cannot occur more than once\n",
				$name,
				xml_get_current_line_number($parser)));
	} else if (!empty($attrs)) {
		$ptr['attributes'] = $attrs;
	}

	$depth++;
	$havedata = $depth;
}

function endElement_rd($parser, $name) {
	global $depth, $curpath, $parsedcfg, $havedata, $listtags;

	if ($havedata == $depth) {
		$ptr =& $parsedcfg;
		foreach ($curpath as $path) {
			$ptr =& $ptr[$path];
		}
		if (!isset($ptr))
			$ptr = "";
	}

	array_pop($curpath);

	if (in_array(strtolower($name), $listtags))
		array_pop($curpath);

	$depth--;
}

function cData_rd($parser, $data) {
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

function parse_xml_regdomain($rdfile = '/etc/regdomain.xml', $rootobj = 'regulatory-data') {
	global $listtags;
	$listtags = listtags_rd();
	return parse_xml_regdomain_raw($rdfile, $rootobj);
}

function parse_xml_regdomain_raw($rdfile, $rootobj) {

	global $depth, $curpath, $parsedcfg, $havedata, $listtags;
	$parsedcfg = array();
	$curpath = array();
	$depth = 0;
	$havedata = 0;

	$xml_parser = xml_parser_create();

	xml_set_element_handler($xml_parser, "startElement_rd", "endElement_rd");
	xml_set_character_data_handler($xml_parser, "cData_rd");
	xml_parser_set_option($xml_parser,XML_OPTION_SKIP_WHITE, 1); 

	if (!($fp = fopen($rdfile, "r"))) {
		die("Error: could not open XML input\n");
	}

	while ($data = fread($fp, 4096)) {
		if (!xml_parse($xml_parser, $data, feof($fp))) {
			log_error(sprintf("XML error: %s at line %d\n",
						xml_error_string(xml_get_error_code($xml_parser)),
						xml_get_current_line_number($xml_parser)));
			return -1;
		}
	}
	xml_parser_free($xml_parser);

	if (!$parsedcfg[$rootobj]) {
		die("XML error: no $rootobj object found!\n");
	}

	return $parsedcfg[$rootobj];
}

?>
