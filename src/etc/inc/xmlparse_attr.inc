<?php
/*
 * xmlparse_attr.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2010-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2010 Erik Fonnesbeck
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
		if (isset($writeattrs)) {
			$attrptr =& $attrptr[$path];
		}
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
			if (!is_array($attrptr)) {
				$attrptr = array();
			}
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

	if (in_array(strtolower($name), $listtags)) {
		array_pop($curpath);
	}

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

	if (empty($rdfile)) {
		$rdfile = $g['etc_path'] . '/regdomain.xml';
	}
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
			if (isset($rdmain['regulatory-domains']['rd'][$rdkey]['netband'])) {
				unset($rdmain['regulatory-domains']['rd'][$rdkey]['netband']);
			}
			if (isset($rdattributes['regulatory-domains']['rd'][$rdkey]['netband'])) {
				unset($rdattributes['regulatory-domains']['rd'][$rdkey]['netband']);
			}
		}
		if (isset($rdmain['shared-frequency-bands'])) {
			unset($rdmain['shared-frequency-bands']);
		}
		if (isset($rdattributes['shared-frequency-bands'])) {
			unset($rdattributes['shared-frequency-bands']);
		}

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

	if (isset($parsed_attributes)) {
		$parsedattrs = array();
	}

	$xml_parser = xml_parser_create();

	xml_set_element_handler($xml_parser, "startElement_attr", "endElement_attr");
	xml_set_character_data_handler($xml_parser, "cData_attr");
	xml_parser_set_option($xml_parser, XML_OPTION_SKIP_WHITE, 1);

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
		if ($parsedattrs[$rootobj]) {
			$parsed_attributes = $parsedattrs[$rootobj];
		}
		unset($parsedattrs);
	}

	return $parsedcfg[$rootobj];
}

?>
