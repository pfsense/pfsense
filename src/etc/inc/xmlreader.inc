<?php
/*
 * xmlreader.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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
		'ca', 'cacert', 'cert', 'crl', 'clone', 'config',
	       	'container', 'columnitem', 'checkipservice',
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
		'timerange', 'tunnel', 'user', 'vip', 'virtual_server', 'vlan', 'vlangroup', 'voucherdbfile',
		'vxlan', 'winsserver', 'wolentry', 'widget', 'xmldatafile'
	);
	return array_flip($ret);
}

/* Package XML tags that should be treat as a list not as a traditional array */
function listtags_pkg() {
	$ret = array('depends_on_package', 'onetoone', 'queue', 'rule', 'servernat', 'alias', 'additional_files_needed', 'tab', 'template', 'menu', 'rowhelperfield', 'service', 'step', 'package', 'columnitem', 'option', 'item', 'field', 'package', 'file');

	return array_flip($ret);
}

function add_elements(&$cfgarray, &$parser) {
	global $listtags;

	while ($parser->read()) {
		switch ($parser->nodeType) {
			case XMLReader::WHITESPACE:
			case XMLReader::SIGNIFICANT_WHITESPACE:
				break;
			case XMLReader::ELEMENT:
				if (isset($listtags[strtolower($parser->name)])) {
					$cfgref =& $cfgarray[$parser->name][count($cfgarray[$parser->name])];
					if (!$parser->isEmptyElement) {
						add_elements($cfgref, $parser);
					} else {
						$cfgref = array();
					}
				} else {
					if (isset($cfgarray[$parser->name]) && (!is_array($cfgarray[$parser->name]) || !isset($cfgarray[$parser->name][0]))) {
						$nodebkp = $cfgarray[$parser->name];
						$cfgarray[$parser->name] = array();
						$cfgarray[$parser->name][] = $nodebkp;
						$cfgref =& $cfgarray[$parser->name][0];
						unset($nodebkp);
					} else {
						$cfgref =& $cfgarray[$parser->name];
					}

					if ($parser->isEmptyElement) {
						if (is_array($cfgref)) {
							$cfgref[] = array();
						} else {
							$cfgref = "";
						}
					} else {
						if (is_array($cfgref)) {
							$cfgref =& $cfgarray[$parser->name][count($cfgarray[$parser->name])];
							add_elements($cfgref, $parser);
						} else {
							add_elements($cfgref, $parser);
						}
					}
				}

				$i = 0;
				while ($parser->moveToAttributeNo($i)) {
					$cfgref[$parser->name] = $parser->value;
					$i++;
				}
				break;
			case XMLReader::TEXT:
			case XMLReader::CDATA:
				$cfgarray = $parser->value;
				break;
			case XMLReader::END_ELEMENT:
				return;
				break;
			default:
				break;
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

	return parse_xml_config_raw($cffile, $rootobj);
}

function parse_xml_config_pkg($cffile, $rootobj, $isstring = "false") {
	global $listtags;

	$listtags = listtags_pkg();
	if (isset($GLOBALS['custom_listtags_pkg'])) {
		foreach ($GLOBALS['custom_listtags_pkg'] as $tag) {
			$listtags[$tag] = $tag;
		}
	}
	return parse_xml_config_raw($cffile, $rootobj, $isstring);
}

function parse_xml_config_raw($cffile, $rootobj, $isstring = "false") {
	global $listtags;

	$parsedcfg = array();

	$par = new XMLReader();
	if ($par->open($cffile, "UTF-8", LIBXML_NOERROR | LIBXML_NOWARNING)) {
		add_elements($parsedcfg, $par);
		$par->close();
	} else {
		log_error(sprintf(gettext("Error returned while trying to parse %s"), $cffile));
	}

	if ($rootobj) {
		if (!is_array($rootobj)) {
			$rootobj = array($rootobj);
		}
		foreach ($rootobj as $rootobj_name) {
			if ($parsedcfg[$rootobj_name]) {
				break;
			}
		}

		return $parsedcfg[$rootobj_name];
	} else {
		return $parsedcfg;
	}
}

function dump_xml_config_sub(& $writer, $arr) {
	global $listtags;

	foreach ($arr as $ent => $val) {
		if (is_array($val)) {
			/* is it just a list of multiple values? */
			if (isset($listtags[strtolower($ent)])) {
				foreach ($val as $cval) {
					if (is_array($cval)) {
						if (empty($cval)) {
							$writer->startElement($ent);
							$writer->endElement();
						} else {
							$writer->startElement($ent);
							dump_xml_config_sub($writer, $cval);
							$writer->endElement();
						}
					} else {
						if ($cval === false) {
							continue;
						}
						if ((is_bool($val) && ($val == true)) || ($val === "")) {
							$writer->startElement($ent);
							$writer->endElement();
						} else if (!is_bool($val)) {
							$writer->writeElement($ent, $cval);
						}
					}
				}
			} else if (empty($val)) {
				$writer->startElement($ent);
				$writer->endElement();
			} else {
				/* it's an array */
				$writer->startElement($ent);
				dump_xml_config_sub($writer, $val);
				$writer->endElement();
			}
		} else {
			if ((is_bool($val) && ($val == true)) || ($val === "")) {
				$writer->startElement($ent);
				$writer->endElement();
			} else if (!is_bool($val)) {
				$writer->writeElement($ent, $val);
			}
		}
	}
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

	$writer = new XMLWriter();
	$writer->openMemory();
	$writer->setIndent(true);
	$writer->setIndentString("\t");
	$writer->startDocument("1.0", "UTF-8");
	$writer->startElement($rootobj);

	dump_xml_config_sub($writer, $arr);

	$writer->endElement();
	$writer->endDocument();
	$xmlconfig = $writer->outputMemory(true);

	return $xmlconfig;
}

?>
