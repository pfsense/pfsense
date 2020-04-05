<?php
/*
 * xmlparse.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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

/* The following items will be treated as arrays in config.xml */
function listtags() {
	/*
	 * Please keep this list alpha sorted and no longer than 80 characters
	 * I know it's a pain, but it's a pain to find stuff too if it's not
	 */
	$ret = array(
		'acls', 'alias', 'aliasurl', 'allowedip', 'allowedhostname', 'authserver',
		'bridged', 'build_port_path',
		'ca', 'cacert', 'cert', 'crl', 'clone', 'config', 'container', 'columnitem',
		'checkipservice',
		'depends_on_package', 'disk', 'dnsserver', 'dnsupdate', 'domainoverrides', 'dyndns',
		'earlyshellcmd', 'element', 'encryption-algorithm-option',
		'field', 'fieldname',
		'gateway_item', 'gateway_group', 'gif', 'gre', 'group',
		'hash-algorithm-option', 'hosts', 'ifgroupentry', 'igmpentry', 'interface_array', 'item', 'key',
		'lagg', 'lbaction', 'lbpool', 'l7rules', 'lbprotocol',
		'member', 'menu', 'tab', 'mobilekey', 'monitor_type', 'mount',
		'npt', 'ntpserver',
		'onetoone', 'openvpn-server', 'openvpn-client', 'openvpn-csc', 'option',
		'package', 'passthrumac', 'phase1', 'phase2', 'ppp', 'pppoe', 'priv', 'proxyarpnet', 'pool',
		'qinqentry', 'queue',
		'pages', 'pipe', 'radnsserver', 'roll', 'route', 'row', 'rrddatafile', 'rule',
		'schedule', 'service', 'servernat', 'servers',
		'serversdisabled', 'shellcmd', 'staticmap', 'subqueue', 'switch', 'swport',
		'timerange', 'tunnel', 'user', 'vip', 'virtual_server', 'vlan', 'vlangroup',
		'winsserver', 'wolentry', 'widget'
	);
	return array_flip($ret);
}

/* Package XML tags that should be treated as a list not as a traditional array */
function listtags_pkg() {
	$ret = array('build_port_path', 'depends_on_package', 'onetoone', 'queue', 'rule', 'servernat', 'alias', 'additional_files_needed', 'tab', 'template', 'menu', 'rowhelperfield', 'service', 'step', 'package', 'columnitem', 'option', 'item', 'field', 'package', 'file');

	return array_flip($ret);
}

function startElement($parser, $name, $attrs) {
	global $parsedcfg, $depth, $curpath, $havedata, $listtags;

	array_push($curpath, strtolower($name));

	$ptr =& $parsedcfg;
	foreach ($curpath as $path) {
		$ptr =& $ptr[$path];
	}

	/* is it an element that belongs to a list? */
	if (isset($listtags[strtolower($name)])) {

		/* is there an array already? */
		if (!is_array($ptr)) {
			/* make an array */
			$ptr = array();
		}

		array_push($curpath, count($ptr));

	} else if (isset($ptr)) {
		/* multiple entries not allowed for this element, bail out */
		throw new Exception(
		    sprintf(gettext('XML error: %1$s at line %2$d cannot occur more than once') . "\n",
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

	if (isset($listtags[strtolower($name)])) {
		array_pop($curpath);
	}

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
			$ptr .= html_entity_decode($data);
		} else {
			if (trim($data, " ") != "") {
				$ptr = html_entity_decode($data);
				$havedata++;
			}
		}
	}
}

function parse_xml_config($cffile, $rootobj, $isstring = "false") {
	global $listtags;
	$listtags = listtags();
	if (isset($GLOBALS['custom_listtags'])) {
		foreach ($GLOBALS['custom_listtags'] as $tag) {
			$listtags[$tag] = $tag;
		}
	}
	return parse_xml_config_raw($cffile, $rootobj, $isstring);
}

function parse_xml_config_pkg($cffile, $rootobj, $isstring = "false") {
	global $listtags;
	$listtags = listtags_pkg();
	if (isset($GLOBALS['custom_listtags_pkg'])) {
		foreach ($GLOBALS['custom_listtags_pkg'] as $tag) {
			$listtags[$tag] = $tag;
		}
	}
	$cfg =parse_xml_config_raw($cffile, $rootobj, $isstring);
	if ($cfg == -1) {
		return array();
	}

	return $cfg;
}

function parse_xml_config_raw($cffile, $rootobj, $isstring = "false") {

	global $depth, $curpath, $parsedcfg, $havedata, $listtags;
	$parsedcfg = array();
	$curpath = array();
	$depth = 0;
	$havedata = 0;

	$xml_parser = xml_parser_create();

	xml_set_element_handler($xml_parser, "startElement", "endElement");
	xml_set_character_data_handler($xml_parser, "cdata");
	xml_parser_set_option($xml_parser, XML_OPTION_SKIP_WHITE, 1);

	if (!($fp = fopen($cffile, "r"))) {
		log_error(gettext("Error: could not open XML input") . "\n");
		return -1;
	}

	while ($data = fread($fp, 4096)) {
		if (!xml_parse($xml_parser, $data, feof($fp))) {
			log_error(sprintf(gettext('XML error: %1$s at line %2$d in %3$s') . "\n",
				xml_error_string(xml_get_error_code($xml_parser)),
				xml_get_current_line_number($xml_parser),
				$cffile));
			return -1;
		}
	}
	xml_parser_free($xml_parser);

	if ($rootobj) {
		if (!is_array($rootobj)) {
			$rootobj = array($rootobj);
		}
		foreach ($rootobj as $rootobj_name) {
			if ($parsedcfg[$rootobj_name]) {
				break;
			}
		}

		if (!$parsedcfg[$rootobj_name]) {
			log_error(sprintf(gettext("XML error: no %s object found!") . "\n", implode(" or ", $rootobj)));
			return -1;
		}
		return $parsedcfg[$rootobj_name];
	} else {
		return $parsedcfg;
	}
}

/* Return true if a field should be cdata encoded */
function is_cdata_entity($ent) {
	$cdata_fields = array(
		'auth_pass', 'auth_prompt', 'auth_user', 'certca', 'certname', 'city', 'common_name',
		'descr', 'detail', 'email', 'encryption_password', 'ldap_attr',
		'ldap_authcn', 'ldap_basedn', 'ldap_basedomain', 'ldap_bind', 'ldapbinddn',
		'ldapbindpass', 'ldap_extended_query', 'ldap_filter', 'ldap_pass', 'ldap_user',
		'login_banner', 'organization', 'password', 'rangedescr', 'state', 'text',
		'username', 'varusersusername', 'varuserspassword'
	);

	/* Check if the entity name starts with any of the strings above */
	return preg_match('/(^' . implode('|^', $cdata_fields) . ')/', $ent) === 1;
}

function dump_xml_config_sub($arr, $indent) {

	global $listtags;

	if (!is_array($arr)) {
		return null;
	}

	$xmlconfig = "";

	foreach ($arr as $ent => $val) {
		if (is_array($val)) {
			/* is it just a list of multiple values? */
			if (isset($listtags[strtolower($ent)])) {
				foreach ($val as $cval) {
					if (is_array($cval)) {
						if (empty($cval)) {
							$xmlconfig .= str_repeat("\t", $indent);
							$xmlconfig .= "<$ent></$ent>\n";
						} else {
							$xmlconfig .= str_repeat("\t", $indent);
							$xmlconfig .= "<$ent>\n";
							$xmlconfig .= dump_xml_config_sub($cval, $indent + 1);
							$xmlconfig .= str_repeat("\t", $indent);
							$xmlconfig .= "</$ent>\n";
						}
					} else {
						if ($cval === false) {
							continue;
						}
						$xmlconfig .= str_repeat("\t", $indent);
						if ((is_bool($cval) && $cval == true) || ($cval === "")) {
							$xmlconfig .= "<$ent></$ent>\n";
						} else if (is_cdata_entity($ent)) {
							$xmlconfig .= "<$ent><![CDATA[" . htmlentities($cval) . "]]></$ent>\n";
						} else {
							$xmlconfig .= "<$ent>" . htmlentities($cval) . "</$ent>\n";
						}
					}
				}
			} else if (empty($val)) {
				$xmlconfig .= str_repeat("\t", $indent);
				$xmlconfig .= "<$ent></$ent>\n";
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
				$xmlconfig .= "<$ent></$ent>\n";
			} else if (!is_bool($val)) {
				$xmlconfig .= str_repeat("\t", $indent);
				if (is_cdata_entity($ent)) {
					$xmlconfig .= "<$ent><![CDATA[" . htmlentities($val) . "]]></$ent>\n";
				} else {
					$xmlconfig .= "<$ent>" . htmlentities($val) . "</$ent>\n";
				}
			}
		}
	}

	return $xmlconfig;
}

function dump_xml_config($arr, $rootobj) {
	global $listtags;
	$listtags = listtags();
	if (isset($GLOBALS['custom_listtags'])) {
		foreach ($GLOBALS['custom_listtags'] as $tag) {
			$listtags[$tag] = $tag;
		}
	}
	return dump_xml_config_raw($arr, $rootobj);
}

function dump_xml_config_pkg($arr, $rootobj) {
	global $listtags;
	$listtags = listtags_pkg();
	if (isset($GLOBALS['custom_listtags_pkg'])) {
		foreach ($GLOBALS['custom_listtags_pkg'] as $tag) {
			$listtags[$tag] = $tag;
		}
	}
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
