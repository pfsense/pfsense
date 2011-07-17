<?php
/* $Id$ */
/*
	xmlparse_attr.inc
	functions to parse configuration files in XML format with attributes
	Copyright (C) 2010 Erik Fonnesbeck
	All rights reserved.

	Based on xmlparse.inc, originally part of m0n0wall (http://m0n0.ch/wall)
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

/* The following items will be treated as arrays in regdomain.xml */
function listtags_rd() {
	$ret = explode(" ",
		"band country flags freqband netband rd"
		);
	return $ret;
}

function startElement_attr($parser, $name, $attrs) {
	global $parsedcfg, $depth, $curpath, $havedata, $listtags, $parsedattrs;

	array_push($curpath, strtolower($name));

	$ptr =& $parsedcfg;
	if (!empty($attrs)) {
		$attrptr =& $parsedattrs;
		$writeattrs = true;
	}
	foreach ($curpath as $path) {
		$ptr =& $ptr[$path];
		if (isset($writeattrs))
			$attrptr =& $attrptr[$path];
	}

	/* is it an element that belongs to a list? */
	if (in_array(strtolower($name), $listtags)) {

		/* is there an array already? */
		if (!is_array($ptr)) {
			/* make an array */
			$ptr = array();
		}

		array_push($curpath, count($ptr));

		if (isset($writeattrs)) {
			if (!is_array($attrptr))
				$attrptr = array();
			$attrptr[count($ptr)] = $attrs;
		}

	} else if (isset($ptr)) {
		/* multiple entries not allowed for this element, bail out */
		die(sprintf(gettext('XML error: %1$s at line %2$d cannot occur more than once') . "\n",
				$name,
				xml_get_current_line_number($parser)));
	} else if (isset($writeattrs)) {
		$attrptr = $attrs;
	}

	$depth++;
	$havedata = $depth;
}

function endElement_attr($parser, $name) {
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

function cData_attr($parser, $data) {
	global $depth, $curpath, $parsedcfg, $havedata;

	$data = trim($data, "\t\n\r");

	if ($data != "") {
		$ptr =& $parsedcfg;
		foreach ($curpath as $path) {
			$ptr =& $ptr[$path];
		}

		if (is_string($ptr)) {
			$ptr .= html_entity_decode($data);
		} else {
			if (trim($data, " ") != "") {
				$ptr = html_entity_decode($data);
				$havedata++;
			}
		}
	}
}

function parse_xml_regdomain(&$rdattributes, $rdfile = '', $rootobj = 'regulatory-data') {
	global $g, $listtags;

	if (empty($rdfile))
		$rdfile = $g['etc_path'] . '/regdomain.xml';
	$listtags = listtags_rd();
	$parsed_xml = array();

	if (file_exists($g['tmp_path'] . '/regdomain.cache')) {
		$parsed_xml = unserialize(file_get_contents($g['tmp_path'] . '/regdomain.cache'));
		if (!empty($parsed_xml)) {
			$rdmain = $parsed_xml['main'];
			$rdattributes = $parsed_xml['attributes'];
		}
	}
	if (empty($parsed_xml) && file_exists($g['etc_path'] . '/regdomain.xml')) {
		$rdmain = parse_xml_config_raw_attr($rdfile, $rootobj, $rdattributes);

		// unset parts that aren't used before making cache
		foreach ($rdmain['regulatory-domains']['rd'] as $rdkey => $rdentry) {
			if (isset($rdmain['regulatory-domains']['rd'][$rdkey]['netband']))
				unset($rdmain['regulatory-domains']['rd'][$rdkey]['netband']);
			if (isset($rdattributes['regulatory-domains']['rd'][$rdkey]['netband']))
				unset($rdattributes['regulatory-domains']['rd'][$rdkey]['netband']);
		}
		if (isset($rdmain['shared-frequency-bands']))
			unset($rdmain['shared-frequency-bands']);
		if (isset($rdattributes['shared-frequency-bands']))
			unset($rdattributes['shared-frequency-bands']);

		$parsed_xml = array('main' => $rdmain, 'attributes' => $rdattributes);
		$rdcache = fopen($g['tmp_path'] . '/regdomain.cache', "w");
		fwrite($rdcache, serialize($parsed_xml));
		fclose($rdcache);
	}

	return $rdmain;
}

function parse_xml_config_raw_attr($cffile, $rootobj, &$parsed_attributes, $isstring = "false") {

	global $depth, $curpath, $parsedcfg, $havedata, $listtags, $parsedattrs;
	$parsedcfg = array();
	$curpath = array();
	$depth = 0;
	$havedata = 0;

	if (isset($parsed_attributes))
		$parsedattrs = array();

	$xml_parser = xml_parser_create();

	xml_set_element_handler($xml_parser, "startElement_attr", "endElement_attr");
	xml_set_character_data_handler($xml_parser, "cData_attr");
	xml_parser_set_option($xml_parser,XML_OPTION_SKIP_WHITE, 1); 

	if (!($fp = fopen($cffile, "r"))) {
		log_error(gettext("Error: could not open XML input") . "\n");
		if (isset($parsed_attributes)) {
			$parsed_attributes = array();
			unset($parsedattrs);
		}
		return -1;
	}

	while ($data = fread($fp, 4096)) {
		if (!xml_parse($xml_parser, $data, feof($fp))) {
			log_error(sprintf(gettext('XML error: %1$s at line %2$d') . "\n",
						xml_error_string(xml_get_error_code($xml_parser)),
						xml_get_current_line_number($xml_parser)));
			if (isset($parsed_attributes)) {
				$parsed_attributes = array();
				unset($parsedattrs);
			}
			return -1;
		}
	}
	xml_parser_free($xml_parser);

	if (!$parsedcfg[$rootobj]) {
		log_error(sprintf(gettext("XML error: no %s object found!") . "\n", $rootobj));
		if (isset($parsed_attributes)) {
			$parsed_attributes = array();
			unset($parsedattrs);
		}
		return -1;
	}

	if (isset($parsed_attributes)) {
		if ($parsedattrs[$rootobj])
			$parsed_attributes = $parsedattrs[$rootobj];
		unset($parsedattrs);
	}

	return $parsedcfg[$rootobj];
}

?>
