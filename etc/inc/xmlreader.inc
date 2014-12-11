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

/*
	pfSense_MODULE:	utils
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
		'depends_on_package', 'disk', 'dnsserver', 'dnsupdate', 'domainoverrides', 'dyndns',
		'earlyshellcmd', 'element', 'encryption-algorithm-option',
		'field', 'fieldname',
		'gateway_item', 'gateway_group', 'gif', 'gre', 'group',
		'hash-algorithm-option', 'hosts', 'member', 'ifgroupentry', 'igmpentry', 'interface_array', 'item', 'key',
		'lagg', 'lbaction', 'lbpool', 'l7rules', 'lbprotocol',
		'member', 'menu', 'tab', 'mobilekey', 'monitor_type', 'mount',
		'npt', 'ntpserver',
		'onetoone', 'openvpn-server', 'openvpn-client', 'openvpn-csc', 'option',
		'package', 'passthrumac', 'phase1', 'phase2', 'ppp', 'pppoe', 'priv', 'proxyarpnet', 'pool',
		'qinqentry', 'queue',
		'pages', 'pipe', 'radnsserver', 'roll', 'route', 'row', 'rrddatafile', 'rule',
		'schedule', 'service', 'servernat', 'servers',
		'serversdisabled', 'shellcmd', 'staticmap', 'subqueue',
		'timerange', 'tunnel', 'user', 'vip', 'virtual_server', 'vlan',
		'winsserver', 'wolentry', 'widget'
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
				} else
					$cfgref = array();
					
                        } else {
				if (isset($cfgarray[$parser->name]) && (!is_array($cfgarray[$parser->name]) || !isset($cfgarray[$parser->name][0]))) {
					$nodebkp = $cfgarray[$parser->name];
					$cfgarray[$parser->name] = array();
					$cfgarray[$parser->name][] = $nodebkp;
					$cfgref =& $cfgarray[$parser->name][0];
					unset($nodebkp);
				} else
					$cfgref =& $cfgarray[$parser->name];

                        	if ($parser->isEmptyElement) {
					if (is_array($cfgref))
						$cfgref[] = array();
					else
						$cfgref = "";
				} else {
					if (is_array($cfgref)) {
						$cfgref =& $cfgarray[$parser->name][count($cfgarray[$parser->name])];
						add_elements($cfgref, $parser);
					} else
						add_elements($cfgref, $parser);
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
          foreach($GLOBALS['custom_listtags'] as $tag) {
            $listtags[$tag] = $tag;
          }
        }

	return parse_xml_config_raw($cffile, $rootobj);
}

function parse_xml_config_pkg($cffile, $rootobj, $isstring = "false") {
	global $listtags;

	$listtags = listtags_pkg();
        if (isset($GLOBALS['custom_listtags_pkg'])) {
          foreach($GLOBALS['custom_listtags_pkg'] as $tag) {
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
	} else
		log_error(sprintf(gettext("Error returned while trying to parse %s"), $cffile));

	if ($rootobj) {
		if (!is_array($rootobj))
			$rootobj = array($rootobj);
		foreach ($rootobj as $rootobj_name)
			if ($parsedcfg[$rootobj_name])
				break;
		
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
                                                if (empty($cval))
                                                        $writer->writeElement($ent);
                                                else {
                                                        $writer->startElement($ent);
                                                        dump_xml_config_sub($writer, $cval);
                                                        $writer->endElement();
                                                }
                                        } else {
                                                if($cval === false) continue;
                                                if ((is_bool($val) && ($val == true)) || ($val === ""))
                                                        $writer->writeElement($ent);
                                                else if (!is_bool($val))
							$writer->writeElement($ent, $cval);
                                        }
                                }
                        } else if (empty($val)) {
                                $writer->writeElement($ent);
                        } else {
                                /* it's an array */
                                $writer->startElement($ent);
                                dump_xml_config_sub($writer, $val);
                                $writer->endElement();
                        }
                } else {
			if ((is_bool($val) && ($val == true)) || ($val === ""))
                                $writer->writeElement($ent);
                        else if (!is_bool($val))
                                $writer->writeElement($ent, $val);
                }
        }
}

function dump_xml_config($arr, $rootobj) {
	global $listtags;

	$listtags = listtags();
        if (isset($GLOBALS['custom_listtags'])) {
          foreach($GLOBALS['custom_listtags'] as $tag) {
            $listtags[$tag] = $tag;
          }
        }
	return dump_xml_config_raw($arr, $rootobj);
}

function dump_xml_config_pkg($arr, $rootobj) {
	global $listtags;

	$listtags = listtags_pkg();
        if (isset($GLOBALS['custom_listtags_pkg'])) {
          foreach($GLOBALS['custom_listtags_pkg'] as $tag) {
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
